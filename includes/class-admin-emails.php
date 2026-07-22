<?php
/**
 * Vendors → Vendor Emails — batched welcome / reset email sender.
 *
 * Use case: after a bulk CSV import we want every newly-created vendor to
 * receive an email asking them to (a) set a password via the WordPress
 * reset flow, and (b) log in and finish their profile. Sending 200+ emails
 * in one request would exhaust PHP timeouts + trip spam filters, so this
 * tool drives an AJAX-paced queue: admin clicks "Start", the JS posts
 * to admin-ajax.php in a loop sending 5 emails per tick with a small
 * delay, updating a progress bar live.
 *
 * State:
 *   - Queue + run-state kept in transients keyed off a single "run_id"
 *     (so multiple runs can't clobber each other) with a 24-hour TTL.
 *   - Each row records sent / skipped / errored outcomes for the result table.
 *
 * Cohort options:
 *   - Latest import batch (read from oc_last_import_batch + the batch transient)
 *   - All pending vendors
 *   - All vendors not yet email-verified
 *   - All vendors (be careful)
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Emails {

	const PAGE              = 'oc-vendor-emails';
	const AJAX_TICK         = 'oc_email_tick';
	const NONCE             = 'oc_emails_nonce';
	const TRANSIENT_RUN     = 'oc_emails_run_';
	const BATCH_SIZE        = 5;     // emails per AJAX tick — keeps each request well under PHP timeout
	const RUN_TTL           = DAY_IN_SECONDS;

	public function register() {
		add_action( 'admin_menu',                       [ $this, 'menu' ], 12 );
		add_action( 'wp_ajax_' . self::AJAX_TICK,       [ $this, 'ajax_tick' ] );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Vendor Emails', 'owambe-connect-core' ),
			__( 'Vendor Emails', 'owambe-connect-core' ),
			'manage_options',
			self::PAGE,
			[ $this, 'render' ]
		);
	}

	// ─────────────────────────────────────────────────────────
	//  Render page (cohort picker + email body editor + start button)
	// ─────────────────────────────────────────────────────────
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$latest_batch_key = (string) get_option( 'oc_last_import_batch', '' );
		$latest_batch     = $latest_batch_key
			? get_transient( OC_Admin_Import::TRANSIENT_BATCH . $latest_batch_key )
			: null;
		$latest_batch_size = ( is_array( $latest_batch ) && ! empty( $latest_batch['post_ids'] ) )
			? count( $latest_batch['post_ids'] )
			: 0;
		$latest_batch_at   = ( is_array( $latest_batch ) && ! empty( $latest_batch['created_at'] ) )
			? gmdate( 'Y-m-d H:i', (int) $latest_batch['created_at'] )
			: '';

		$cohort_counts = self::cohort_counts();
		$default_subject = sprintf(
			/* translators: %s: site name */
			__( 'Welcome to %s — finish setting up your vendor profile', 'owambe-connect-core' ),
			get_bloginfo( 'name' )
		);
		$default_body = self::default_body_template();
		?>
		<div class="wrap oc-emails">
			<h1><?php esc_html_e( 'Vendor Emails', 'owambe-connect-core' ); ?></h1>
			<p class="description" style="max-width:780px;"><?php esc_html_e( 'Send a single email to every selected vendor with a one-click password reset link and a link to their dashboard profile. Emails go out in small batches with a short delay between each — safe to leave running in a background tab.', 'owambe-connect-core' ); ?></p>

			<form id="oc-emails-form" class="oc-emails__form" onsubmit="return false;">
				<?php wp_nonce_field( self::NONCE, 'oc_emails_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Who should receive this?', 'owambe-connect-core' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:6px;">
								<input type="radio" name="cohort" value="latest_batch" <?php checked( true, $latest_batch_size > 0 ); disabled( true, $latest_batch_size === 0 ); ?>/>
								<?php
								if ( $latest_batch_size ) {
									/* translators: 1: count, 2: ISO date */
									printf( esc_html__( 'Latest import batch — %1$d vendor(s) imported on %2$s', 'owambe-connect-core' ), $latest_batch_size, esc_html( $latest_batch_at ) );
								} else {
									esc_html_e( 'Latest import batch — none recorded (run a bulk import first)', 'owambe-connect-core' );
								}
								?>
							</label>
							<label style="display:block;margin-bottom:6px;">
								<input type="radio" name="cohort" value="pending"/>
								<?php
								/* translators: %d count */
								printf( esc_html__( 'All pending vendors — %d total', 'owambe-connect-core' ), (int) $cohort_counts['pending'] );
								?>
							</label>
							<label style="display:block;margin-bottom:6px;">
								<input type="radio" name="cohort" value="unverified"/>
								<?php
								printf( esc_html__( 'All vendors who have not yet verified their email — %d total', 'owambe-connect-core' ), (int) $cohort_counts['unverified'] );
								?>
							</label>
							<label style="display:block;margin-bottom:6px;">
								<input type="radio" name="cohort" value="all"/>
								<?php
								printf( esc_html__( 'Every vendor on the site — %d total (be careful)', 'owambe-connect-core' ), (int) $cohort_counts['all'] );
								?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-emails-subject"><?php esc_html_e( 'Subject', 'owambe-connect-core' ); ?></label></th>
						<td>
							<input id="oc-emails-subject" name="subject" type="text" class="regular-text" value="<?php echo esc_attr( $default_subject ); ?>" maxlength="180"/>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-emails-body"><?php esc_html_e( 'Email body', 'owambe-connect-core' ); ?></label></th>
						<td>
							<textarea id="oc-emails-body" name="body" rows="14" class="large-text code"><?php echo esc_textarea( $default_body ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Placeholders: {first_name}, {business_name}, {site_name}, {reset_url}, {dashboard_url}, {vendor_number}. HTML allowed; line breaks become paragraphs automatically.', 'owambe-connect-core' ); ?></p>
						</td>
					</tr>
				</table>

				<p style="background:#FFF8E6;border:1px solid #E4DDD2;border-radius:8px;padding:12px 14px;margin:8px 0;">
					<label style="display:flex;align-items:flex-start;gap:8px;font-size:13.5px;color:#3D3735;line-height:1.5;cursor:pointer;">
						<input type="checkbox" name="force_resend" id="oc-emails-force" value="1" style="margin-top:3px;"/>
						<span>
							<strong><?php esc_html_e( 'Resend to vendors who already received this email', 'owambe-connect-core' ); ?></strong>
							<br>
							<span style="color:#6B6361;font-size:12.5px;"><?php esc_html_e( 'OFF (recommended): the system skips anyone with a recorded onboarding-email send. ON: every selected vendor gets a fresh copy regardless of history.', 'owambe-connect-core' ); ?></span>
						</span>
					</label>
				</p>

				<p class="submit">
					<button type="button" id="oc-emails-start"  class="button button-primary"><?php esc_html_e( 'Start sending', 'owambe-connect-core' ); ?></button>
					<button type="button" id="oc-emails-cancel" class="button" disabled><?php esc_html_e( 'Cancel', 'owambe-connect-core' ); ?></button>
					<span id="oc-emails-status" style="margin-left:14px;color:#6B6361;"></span>
				</p>

				<div id="oc-emails-progress" hidden style="margin:14px 0;">
					<div style="background:#EFEAE2;border-radius:999px;overflow:hidden;height:14px;margin-bottom:8px;">
						<div id="oc-emails-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#6E0F2C,#C9A961);transition:width .25s ease;"></div>
					</div>
					<div id="oc-emails-counters" style="display:flex;gap:18px;font-size:13px;">
						<span><strong id="oc-emails-sent">0</strong> <?php esc_html_e( 'sent', 'owambe-connect-core' ); ?></span>
						<span><strong id="oc-emails-skipped">0</strong> <?php esc_html_e( 'skipped', 'owambe-connect-core' ); ?></span>
						<span style="color:#B0354F;"><strong id="oc-emails-errored">0</strong> <?php esc_html_e( 'errored', 'owambe-connect-core' ); ?></span>
						<span style="color:#6B6361;"><?php esc_html_e( 'of', 'owambe-connect-core' ); ?> <strong id="oc-emails-total">0</strong></span>
					</div>
				</div>

				<table id="oc-emails-log" class="widefat striped" style="display:none;margin-top:14px;">
					<thead><tr>
						<th style="width:80px;"><?php esc_html_e( 'Status', 'owambe-connect-core' ); ?></th>
						<th><?php esc_html_e( 'Business', 'owambe-connect-core' ); ?></th>
						<th><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?></th>
						<th><?php esc_html_e( 'Note', 'owambe-connect-core' ); ?></th>
					</tr></thead>
					<tbody></tbody>
				</table>
			</form>
		</div>

		<style>
			.oc-emails__form { background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:18px 24px; margin-top:14px; }
			#oc-emails-log tr.is-sent    td:first-child { color:#1F4D3A; font-weight:600; }
			#oc-emails-log tr.is-skipped td:first-child { color:#A8893D; font-weight:600; }
			#oc-emails-log tr.is-error   td:first-child { color:#B0354F; font-weight:600; }
		</style>

		<script>
		(function () {
			var form    = document.getElementById('oc-emails-form');
			var startBtn= document.getElementById('oc-emails-start');
			var cancelBtn=document.getElementById('oc-emails-cancel');
			var statusEl= document.getElementById('oc-emails-status');
			var progEl  = document.getElementById('oc-emails-progress');
			var barEl   = document.getElementById('oc-emails-bar');
			var sentEl  = document.getElementById('oc-emails-sent');
			var skipEl  = document.getElementById('oc-emails-skipped');
			var errEl   = document.getElementById('oc-emails-errored');
			var totalEl = document.getElementById('oc-emails-total');
			var logTbl  = document.getElementById('oc-emails-log');
			var logBody = logTbl.querySelector('tbody');
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = form.querySelector('input[name="oc_emails_nonce"]').value;

			var runId    = null;
			var cancelled = false;

			function setRunning(running) {
				startBtn.disabled  = running;
				cancelBtn.disabled = !running;
				form.querySelectorAll('input[name="cohort"], #oc-emails-subject, #oc-emails-body').forEach(function (el) {
					el.disabled = running;
				});
			}

			function appendLog(rows) {
				if (!rows.length) return;
				logTbl.style.display = '';
				rows.forEach(function (r) {
					var tr = document.createElement('tr');
					tr.className = 'is-' + r.status;
					tr.innerHTML =
						'<td>' + r.status + '</td>' +
						'<td></td><td></td><td></td>';
					tr.children[1].textContent = r.business_name || '';
					tr.children[2].textContent = r.email || '';
					tr.children[3].textContent = r.note || '';
					logBody.appendChild(tr);
				});
			}

			function tick() {
				if (cancelled) return;
				var fd = new FormData();
				fd.append('action', <?php echo wp_json_encode( self::AJAX_TICK ); ?>);
				fd.append('_ajax_nonce', nonce);
				fd.append('run_id', runId || '');

				if (!runId) {
					// First tick — also pass the form payload to spin up the queue.
					fd.append('cohort',  form.querySelector('input[name="cohort"]:checked').value);
					fd.append('subject', form.querySelector('#oc-emails-subject').value);
					fd.append('body',    form.querySelector('#oc-emails-body').value);
					var force = form.querySelector('#oc-emails-force');
					fd.append('force',   (force && force.checked) ? '1' : '0');
				}

				fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						if (!res || !res.success) {
							statusEl.textContent = (res && res.data && res.data.message) || 'Unexpected error.';
							statusEl.style.color = '#B0354F';
							setRunning(false);
							return;
						}
						var d = res.data;
						runId       = d.run_id;
						totalEl.textContent = d.total;
						sentEl.textContent  = d.counts.sent;
						skipEl.textContent  = d.counts.skipped;
						errEl.textContent   = d.counts.errored;
						barEl.style.width   = d.total > 0
							? Math.round(((d.counts.sent + d.counts.skipped + d.counts.errored) * 100) / d.total) + '%'
							: '100%';
						appendLog(d.batch_log || []);
						statusEl.textContent = d.message || '';
						if (d.done) {
							setRunning(false);
							statusEl.style.color = '#1F4D3A';
						} else {
							// Tiny pause between ticks — keeps mailer happy + UI responsive.
							setTimeout(tick, 400);
						}
					})
					.catch(function (err) {
						statusEl.textContent = err && err.message ? err.message : 'Network error — retrying in 4s…';
						statusEl.style.color = '#B0354F';
						setTimeout(tick, 4000);
					});
			}

			startBtn.addEventListener('click', function () {
				if (!form.querySelector('input[name="cohort"]:checked')) {
					alert(<?php echo wp_json_encode( __( 'Pick a cohort first.', 'owambe-connect-core' ) ); ?>);
					return;
				}
				var size = form.querySelector('input[name="cohort"]:checked').parentNode.textContent;
				if (!confirm(<?php echo wp_json_encode( __( 'About to start sending. You can cancel partway through. Continue?', 'owambe-connect-core' ) ); ?> + '\n\n' + size)) return;
				cancelled = false;
				runId = null;
				logBody.innerHTML = '';
				progEl.hidden = false;
				statusEl.style.color = '#6B6361';
				statusEl.textContent = <?php echo wp_json_encode( __( 'Spinning up queue…', 'owambe-connect-core' ) ); ?>;
				setRunning(true);
				tick();
			});

			cancelBtn.addEventListener('click', function () {
				cancelled = true;
				statusEl.textContent = <?php echo wp_json_encode( __( 'Cancelled — already-sent emails were not recalled.', 'owambe-connect-core' ) ); ?>;
				setRunning(false);
			});
		})();
		</script>
		<?php
	}

	// ─────────────────────────────────────────────────────────
	//  AJAX tick — pulls run state, sends N emails, returns progress
	// ─────────────────────────────────────────────────────────
	public function ajax_tick() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'owambe-connect-core' ) ], 403 );
		}
		check_ajax_referer( self::NONCE, '_ajax_nonce' );

		$run_id  = isset( $_POST['run_id'] )  ? sanitize_key( wp_unslash( $_POST['run_id'] ) )  : '';
		$subject = isset( $_POST['subject'] ) ? wp_strip_all_tags( wp_unslash( $_POST['subject'] ) ) : '';
		$body    = isset( $_POST['body'] )    ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
		$cohort  = isset( $_POST['cohort'] )  ? sanitize_key( wp_unslash( $_POST['cohort'] ) )  : '';
		$force   = ! empty( $_POST['force'] );

		// First tick: build the queue.
		if ( '' === $run_id ) {
			$post_ids = self::resolve_cohort( $cohort );
			if ( empty( $post_ids ) ) {
				wp_send_json_error( [ 'message' => __( 'No vendors in that cohort.', 'owambe-connect-core' ) ] );
			}
			// IMPORTANT: lowercase only — the next tick reads run_id through
			// sanitize_key() which forces lowercase, so a mixed-case key here
			// would never match what we just stored in the transient.
			$run_id = strtolower( wp_generate_password( 14, false, false ) );
			$run    = [
				'queue'    => $post_ids,
				'subject'  => $subject ?: __( 'Welcome to Owambe Connect', 'owambe-connect-core' ),
				'body'     => $body    ?: self::default_body_template(),
				'total'    => count( $post_ids ),
				'counts'   => [ 'sent' => 0, 'skipped' => 0, 'errored' => 0 ],
				'started'  => time(),
				'force'    => (bool) $force,
			];
			set_transient( self::TRANSIENT_RUN . $run_id, $run, self::RUN_TTL );
		} else {
			$run = get_transient( self::TRANSIENT_RUN . $run_id );
			if ( ! is_array( $run ) ) {
				wp_send_json_error( [ 'message' => __( 'Run expired or not found. Refresh and start again.', 'owambe-connect-core' ) ] );
			}
		}

		// Pop N from the queue + send.
		$batch_log = [];
		$slice     = array_splice( $run['queue'], 0, self::BATCH_SIZE );
		$run_force = ! empty( $run['force'] );
		foreach ( $slice as $post_id ) {
			$outcome = self::send_one( (int) $post_id, $run['subject'], $run['body'], $run_force );
			$run['counts'][ $outcome['status'] ]++;
			$batch_log[] = $outcome;
		}

		$done = empty( $run['queue'] );
		if ( $done ) {
			delete_transient( self::TRANSIENT_RUN . $run_id );
		} else {
			set_transient( self::TRANSIENT_RUN . $run_id, $run, self::RUN_TTL );
		}

		$processed = $run['counts']['sent'] + $run['counts']['skipped'] + $run['counts']['errored'];
		wp_send_json_success( [
			'run_id'    => $run_id,
			'total'     => (int) $run['total'],
			'counts'    => [
				'sent'    => (int) $run['counts']['sent'],
				'skipped' => (int) $run['counts']['skipped'],
				'errored' => (int) $run['counts']['errored'],
			],
			'batch_log' => $batch_log,
			'done'      => (bool) $done,
			'message'   => $done
				? sprintf(
					/* translators: 1: sent, 2: skipped, 3: errored */
					__( 'All done — %1$d sent, %2$d skipped, %3$d errored.', 'owambe-connect-core' ),
					$run['counts']['sent'], $run['counts']['skipped'], $run['counts']['errored']
				)
				: sprintf(
					/* translators: 1: processed, 2: total */
					__( '%1$d / %2$d processed…', 'owambe-connect-core' ),
					$processed, $run['total']
				),
		] );
	}

	// ─────────────────────────────────────────────────────────
	//  Cohort resolvers + send-one helper
	// ─────────────────────────────────────────────────────────
	private static function cohort_counts() {
		global $wpdb;
		$counts   = wp_count_posts( OC_CPT );
		$pending  = isset( $counts->{OC_STATUS_PENDING} )  ? (int) $counts->{OC_STATUS_PENDING}  : 0;
		$approved = isset( $counts->{OC_STATUS_APPROVED} ) ? (int) $counts->{OC_STATUS_APPROVED} : 0;
		$rejected = isset( $counts->{OC_STATUS_REJECTED} ) ? (int) $counts->{OC_STATUS_REJECTED} : 0;

		// Unverified = (no _oc_email_verified meta OR value = 0)  AND post is published/pending.
		$unverified = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm
			   ON pm.post_id = p.ID AND pm.meta_key = '_oc_email_verified'
			 WHERE p.post_type = %s
			   AND p.post_status IN (%s, %s, %s)
			   AND ( pm.meta_value IS NULL OR pm.meta_value = '0' )",
			OC_CPT, OC_STATUS_PENDING, OC_STATUS_APPROVED, OC_STATUS_REJECTED
		) );

		return [
			'pending'    => $pending,
			'unverified' => $unverified,
			'all'        => $pending + $approved + $rejected,
		];
	}

	private static function resolve_cohort( $cohort ) {
		switch ( $cohort ) {
			case 'latest_batch':
				$key   = (string) get_option( 'oc_last_import_batch', '' );
				$batch = $key ? get_transient( OC_Admin_Import::TRANSIENT_BATCH . $key ) : null;
				return is_array( $batch ) && ! empty( $batch['post_ids'] )
					? array_map( 'intval', (array) $batch['post_ids'] )
					: [];

			case 'pending':
				return get_posts( [
					'post_type'      => OC_CPT,
					'post_status'    => OC_STATUS_PENDING,
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'no_found_rows'  => true,
				] );

			case 'unverified':
				return get_posts( [
					'post_type'      => OC_CPT,
					'post_status'    => [ OC_STATUS_PENDING, OC_STATUS_APPROVED, OC_STATUS_REJECTED ],
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'no_found_rows'  => true,
					'meta_query'     => [
						'relation' => 'OR',
						[ 'key' => '_oc_email_verified', 'compare' => 'NOT EXISTS' ],
						[ 'key' => '_oc_email_verified', 'value'   => '0' ],
					],
				] );

			case 'all':
				return get_posts( [
					'post_type'      => OC_CPT,
					'post_status'    => [ OC_STATUS_PENDING, OC_STATUS_APPROVED, OC_STATUS_REJECTED ],
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'no_found_rows'  => true,
				] );
		}
		return [];
	}

	/**
	 * Send a single onboarding email. Builds a fresh password-reset key via
	 * the standard WordPress flow so the link uses wp-login.php and shows
	 * the familiar "Enter a new password" screen.
	 *
	 * When $force is false (the default) we skip any user who already has
	 * `_oc_onboard_email_sent_at` set — that's how we avoid emailing the
	 * same vendor twice across multiple runs of this tool.
	 */
	private static function send_one( $post_id, $subject_tpl, $body_tpl, $force = false ) {
		$post = get_post( $post_id );
		$out  = [
			'status'        => 'skipped',
			'business_name' => '',
			'email'         => '',
			'note'          => '',
		];
		if ( ! $post || OC_CPT !== $post->post_type ) {
			$out['note'] = 'invalid post';
			return $out;
		}
		$user = get_user_by( 'id', $post->post_author );
		if ( $user && ! $force ) {
			$prev = (int) get_user_meta( $user->ID, '_oc_onboard_email_sent_at', true );
			if ( $prev > 0 ) {
				$out['business_name'] = $post->post_title;
				$out['email']         = $user->user_email;
				$out['note']          = sprintf(
					/* translators: %s: human-readable date */
					__( 'Already emailed on %s — skipped. Tick "Resend" to send again.', 'owambe-connect-core' ),
					date_i18n( get_option( 'date_format' ), $prev )
				);
				return $out;
			}
		}
		if ( ! $user || ! is_email( $user->user_email ) ) {
			$out['note'] = 'no user / invalid email';
			return $out;
		}
		$out['business_name'] = $post->post_title;
		$out['email']         = $user->user_email;

		// Build the password reset URL pointing at the branded /reset-password/
		// page so the vendor never sees a /wp-login.php screen.
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			$out['status'] = 'error';
			$out['note']   = $key->get_error_message();
			return $out;
		}
		$reset_page = function_exists( 'oc_page_url' ) ? oc_page_url( 'reset-password' ) : home_url( '/reset-password/' );
		$reset_url  = add_query_arg(
			[
				'key'   => $key,
				'login' => rawurlencode( $user->user_login ),
			],
			$reset_page
		);
		$dashboard_url = function_exists( 'oc_page_url' ) ? oc_page_url( 'vendor-dashboard' ) : home_url( '/' );
		$vendor_number = (string) get_post_meta( $post->ID, '_oc_vendor_number', true );

		$placeholders = [
			'{first_name}'    => self::guess_first_name( $user ),
			'{business_name}' => $post->post_title,
			'{site_name}'     => get_bloginfo( 'name' ),
			'{reset_url}'     => $reset_url,
			'{dashboard_url}' => $dashboard_url,
			'{vendor_number}' => $vendor_number,
		];
		$subject = strtr( $subject_tpl, $placeholders );
		$body    = strtr( $body_tpl,    $placeholders );

		// Wrap plain-line breaks into paragraph tags so the email reads cleanly.
		$body_html = wpautop( $body );

		// Wrap in a tiny branded shell.
		$site_name = get_bloginfo( 'name' );
		$html  = '<div style="font-family:Inter,Arial,Helvetica,sans-serif;color:#1F1B1A;line-height:1.55;max-width:560px;margin:0 auto;">';
		$html .= $body_html;
		$html .= '<hr style="border:0;border-top:1px solid #EFEAE2;margin:24px 0 12px;">';
		$html .= '<p style="font-size:12px;color:#6B6361;">' . esc_html( $site_name ) . ' · ' . esc_html__( 'You are receiving this because your business was listed on our vendor directory.', 'owambe-connect-core' ) . '</p>';
		$html .= '</div>';

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>',
				class_exists( 'OC_Mail' ) ? OC_Mail::from_name()  : $site_name,
				class_exists( 'OC_Mail' ) ? OC_Mail::from_email() : get_option( 'admin_email' )
			),
		];

		$sent = wp_mail( $user->user_email, $subject, $html, $headers );
		if ( $sent ) {
			update_user_meta( $user->ID, '_oc_onboard_email_sent_at', time() );
			$out['status'] = 'sent';
			$out['note']   = '';
		} else {
			$out['status'] = 'error';
			$out['note']   = 'wp_mail() returned false';
		}
		return $out;
	}

	private static function guess_first_name( WP_User $user ) {
		if ( $user->first_name ) return $user->first_name;
		$parts = preg_split( '/\s+/', (string) $user->display_name );
		return ! empty( $parts[0] ) ? $parts[0] : __( 'there', 'owambe-connect-core' );
	}

	/**
	 * Default body — kept as plain text with line breaks; wpautop wraps it
	 * into paragraphs at send time. Placeholders are documented in the page UI.
	 */
	public static function default_body_template() {
		return <<<TXT
Hi {first_name},

Your business <strong>{business_name}</strong> has been added to {site_name} — welcome!

To finish setting up your account, click the link below to choose a password:

<a href="{reset_url}">{reset_url}</a>

Once you're in, you can check your profile, add photos, and make sure everything looks the way you want it to:

<a href="{dashboard_url}">Open my vendor dashboard</a>

If anything looks off, hit reply to this email and we'll sort it out.

Thanks,
The {site_name} team
TXT;
	}
}
