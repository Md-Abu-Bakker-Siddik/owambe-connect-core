<?php
/**
 * Bulk vendor importer — Vendors → Bulk Import.
 *
 * Lets an admin upload a CSV of vendors and create user + CPT rows in one
 * pass. Tolerant about column names (fuzzy match against a canonical
 * key set) so the existing Google-Forms export imports as-is. Idempotent
 * by vendor email — re-uploading the same file is a no-op on existing
 * vendors. Always supports a dry-run so admin can preview outcomes
 * without committing.
 *
 * Flow:
 *   1. Admin uploads CSV.
 *   2. We resolve every CSV column to a canonical field via aliases.
 *   3. Each row is validated + mapped + logged.
 *   4. Dry-run: show preview only. Commit: actually create users + posts.
 *   5. Result page lists per-row outcomes with optional error CSV download.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Import {

	const PAGE              = 'oc-import-vendors';
	const ACTION            = 'oc_import_vendors';
	const BATCH_ACTION      = 'oc_import_vendors_batch';
	const TEMPLATE_ACTION   = 'oc_vendor_template_csv';
	const EXPORT_ACTION     = 'oc_export_vendors_csv';
	const DELETE_BATCH      = 'oc_delete_import_batch';
	const TRANSIENT_REPORT  = 'oc_import_report_';
	const TRANSIENT_BATCH   = 'oc_import_batch_';
	const TRANSIENT_SESSION = 'oc_import_session_';
	const NONCE             = 'oc_import_nonce';
	const UPLOAD_MAX_MB     = 8;
	const BATCH_SIZE        = 25;   // rows per AJAX tick
	const INLINE_THRESHOLD  = 50;   // anything ≤ this runs inline (fast enough)

	/**
	 * Canonical field → list of accepted column-name aliases (lower-case,
	 * with punctuation + extra spaces stripped before comparison).
	 */
	private static $aliases = [
		'business_name' => [ 'business name', 'business', 'vendor name', 'company', 'company name' ],
		'first_name'    => [ 'first name', 'firstname', 'given name' ],
		'last_name'     => [ 'last name', 'lastname', 'surname', 'family name' ],
		'contact_first' => [ 'contact first name', 'contact first' ],
		'contact_last'  => [ 'contact last name', 'contact last', 'contact surname' ],
		'phone'         => [ 'phone number whatsapp preferred', 'phone number', 'phone', 'mobile', 'whatsapp', 'whatsapp number', 'tel', 'contact number' ],
		'email'         => [ 'email address', 'email', 'contact email', 'business email', 'e-mail' ],
		'category'      => [ 'service category', 'category', 'service', 'vendor type', 'type' ],
		'location'      => [ 'location', 'city', 'town', 'base location', 'based in' ],
		'country'       => [ 'country', 'region', 'country/region' ],
		'areas'         => [ 'areas you cover', 'service area', 'service areas', 'areas covered', 'coverage', 'areas', 'service area location' ],
		'instagram'     => [ 'instagram handle', 'instagram', 'ig handle', 'ig', 'insta' ],
		'facebook'      => [ 'facebook', 'facebook page', 'fb' ],
		'website'       => [ 'website optional', 'website', 'url', 'site', 'web' ],
		'description'   => [ 'short description', 'description', 'bio', 'about', 'about us', 'business description' ],
		'services'      => [ 'services offered', 'services', 'what we offer', 'offerings' ],
		'registered'    => [ 'is your business officially registered', 'registered', 'business registered', 'officially registered' ],
		'price_range'   => [ 'price range', 'budget', 'price tier' ],
		'cultural'      => [ 'cultural specialties', 'cultural events specialties', 'cultural specialty' ],
		'tags'          => [ 'vendor tags', 'tags' ],
		'preferred'     => [ 'preferred contact method', 'preferred contact', 'how to contact' ],
		'agreement'     => [ 'agreement', 'consent', 'terms' ], // discarded — we just check it's present
		'timestamp'     => [ 'timestamp', 'submitted at', 'submission date', 'date' ], // discarded
		// Round-trip helpers — exported by stream_export(), recognised on import
		// so re-importing an exported file doesn't generate "unknown column"
		// noise. None of these alter the row outcome.
		'vendor_number' => [ 'vendor number', 'vendor registration number', 'vrn' ],
		'status'        => [ 'status', 'listing status' ],
		'verified'      => [ 'verified', 'verified vendor' ],
		'founding'      => [ 'founding', 'founding vendor' ],
	];

	/**
	 * Known free-text category → canonical taxonomy slug. Unknown values
	 * are slugified and created on demand (with admin-visible note).
	 */
	private static $category_map = [
		'caterer'         => 'catering',
		'catering'        => 'catering',
		'caterers'        => 'catering',
		'food vendor'     => 'catering',
		'photographer'    => 'photography',
		'photography'     => 'photography',
		'photographers'   => 'photography',
		'videographer'    => 'videography',
		'videography'     => 'videography',
		'decorator'       => 'decor',
		'decor'           => 'decor',
		'decor & styling' => 'decor',
		'event decorator' => 'decor',
		'dj'              => 'dj-music',
		'dj & music'      => 'dj-music',
		'live music'      => 'dj-music',
		'venue'           => 'venues',
		'venues'          => 'venues',
		'makeup artist'   => 'mua',
		'mua'             => 'mua',
		'makeup'          => 'mua',
		'hair stylist'    => 'mua',
		'cake vendor'     => 'cakes',
		'cake'            => 'cakes',
		'cakes'           => 'cakes',
		'baker'           => 'cakes',
		'planner'         => 'planners',
		'planners'        => 'planners',
		'event planner'   => 'planners',
		'mc'              => 'planners', // map MC to planners until a dedicated term is added
		'host'            => 'planners',
		'wedding dress'   => 'attire',
		'attire'          => 'attire',
		'fashion'         => 'attire',
		'tailoring'       => 'attire',
		'transport'       => 'transport',
		// "Others" / blank → no category assigned (admin can sort later).
	];

	/** Recognised UK constituent countries (key matches oc_country_options() slugs). */
	private static $country_keywords = [
		'scotland'         => [ 'scotland', 'edinburgh', 'glasgow', 'aberdeen', 'dundee', 'inverness', 'stirling', 'paisley' ],
		'wales'            => [ 'wales', 'cardiff', 'swansea', 'newport', 'wrexham', 'bangor' ],
		'northern-ireland' => [ 'northern ireland', 'belfast', 'derry', 'londonderry', 'lisburn', 'bangor (ni)' ],
		// Everything else with a UK city → england (default fallback below).
	];

	public function register() {
		add_action( 'admin_menu',                              [ $this, 'menu' ], 11 );
		add_action( 'admin_post_' . self::ACTION,              [ $this, 'handle' ] );
		add_action( 'admin_post_' . self::TEMPLATE_ACTION,     [ $this, 'stream_template' ] );
		add_action( 'admin_post_' . self::EXPORT_ACTION,       [ $this, 'stream_export' ] );
		add_action( 'admin_post_' . self::DELETE_BATCH,        [ $this, 'handle_delete_batch' ] );
		add_action( 'wp_ajax_'    . self::BATCH_ACTION,        [ $this, 'handle_batch' ] );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Bulk Import Vendors', 'owambe-connect-core' ),
			__( 'Bulk Import', 'owambe-connect-core' ),
			'manage_options',
			self::PAGE,
			[ $this, 'render' ]
		);
	}

	// ─────────────────────────────────────────────────────────
	//  Render page
	// ─────────────────────────────────────────────────────────
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$report_key = isset( $_GET['report'] ) ? sanitize_key( $_GET['report'] ) : '';
		$report     = $report_key ? get_transient( self::TRANSIENT_REPORT . $report_key ) : null;
		// Surface the silent "ghost report" failure mode: URL says we have a
		// report key but the transient lookup came back empty (expired,
		// cleared by an object cache, or a key-encoding mismatch).
		$report_missing = $report_key && ! $report;

		// Active batch session — admin landed here after uploading a >50-row
		// CSV. We render the progress UI instead of the upload form.
		$session_key = isset( $_GET['session'] ) ? sanitize_key( $_GET['session'] ) : '';
		$session     = $session_key ? get_transient( self::TRANSIENT_SESSION . $session_key ) : null;

		// Build the canonical column legend for the help section.
		$canonical = self::canonical_columns();
		?>
		<div class="wrap oc-imp">
			<h1><?php esc_html_e( 'Bulk Import Vendors', 'owambe-connect-core' ); ?></h1>
			<p class="oc-imp__lead"><?php esc_html_e( 'Upload a CSV to create vendor accounts + listings in one go. Column names are matched flexibly — the existing Google-Forms export works as-is. Existing vendors (matched by email) are skipped, so re-uploading the same file is safe.', 'owambe-connect-core' ); ?></p>

			<?php if ( $report ) : $this->render_report( $report ); endif; ?>
			<?php if ( $report_missing ) : ?>
				<div class="notice notice-warning" style="margin:18px 0;">
					<p>
						<strong><?php esc_html_e( "Couldn't find a report for that link.", 'owambe-connect-core' ); ?></strong>
						<?php esc_html_e( 'The import either finished more than 30 minutes ago (reports expire) or the page was refreshed past the result. Run the import again to see fresh output.', 'owambe-connect-core' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( $session ) : $this->render_progress( $session_key, $session ); else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="oc-imp__form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>"/>
				<?php wp_nonce_field( self::ACTION, self::NONCE ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="oc-imp-file"><?php esc_html_e( 'CSV file', 'owambe-connect-core' ); ?></label></th>
						<td>
							<input id="oc-imp-file" type="file" name="csv" accept=".csv,text/csv" required/>
							<p class="description"><?php
								printf(
									/* translators: %d: max megabytes */
									esc_html__( 'UTF-8 CSV, header row required, max %d MB.', 'owambe-connect-core' ),
									self::UPLOAD_MAX_MB
								);
							?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default listing status', 'owambe-connect-core' ); ?></th>
						<td>
							<label><input type="radio" name="default_status" value="<?php echo esc_attr( OC_STATUS_PENDING ); ?>" checked/> <?php esc_html_e( 'Pending review (recommended — admin approves later)', 'owambe-connect-core' ); ?></label><br>
							<label><input type="radio" name="default_status" value="<?php echo esc_attr( OC_STATUS_APPROVED ); ?>"/> <?php esc_html_e( 'Published immediately', 'owambe-connect-core' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'owambe-connect-core' ); ?></th>
						<td>
							<label><input type="checkbox" name="skip_existing" value="1" checked/> <?php esc_html_e( 'Skip rows where a user with that email already exists', 'owambe-connect-core' ); ?></label><br>
							<label><input type="checkbox" name="grandfather_verify" value="1" checked/> <?php esc_html_e( 'Mark imported vendors as email-verified (skip the verification flow)', 'owambe-connect-core' ); ?></label><br>
							<label><input type="checkbox" name="send_welcome" value="1"/> <?php esc_html_e( 'Send each vendor the "application received" welcome email', 'owambe-connect-core' ); ?></label>
							<p class="description"><?php esc_html_e( 'Leave this OFF for bulk historical imports — sending 200+ emails at once is noisy and often gets flagged as spam.', 'owambe-connect-core' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="mode" value="dry_run" class="button"><?php esc_html_e( 'Dry run (preview only)', 'owambe-connect-core' ); ?></button>
					<button type="submit" name="mode" value="commit" class="button button-primary"><?php esc_html_e( 'Run import', 'owambe-connect-core' ); ?></button>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::TEMPLATE_ACTION ), self::TEMPLATE_ACTION ) ); ?>"><?php esc_html_e( 'Download template CSV', 'owambe-connect-core' ); ?></a>
				</p>
			</form>
			<?php endif; // session vs upload form ?>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="oc-imp__form oc-imp__form--export">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT_ACTION ); ?>"/>
				<?php wp_nonce_field( self::EXPORT_ACTION, '_wpnonce', false ); ?>
				<h2 style="margin-top:0;"><?php esc_html_e( 'Export current vendors to CSV', 'owambe-connect-core' ); ?></h2>
				<p class="description" style="margin-top:-4px;"><?php esc_html_e( 'Download every vendor on this site as a CSV in the same column shape the importer reads — so you can pull a snapshot, edit it, and re-upload via the form above.', 'owambe-connect-core' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="oc-exp-status"><?php esc_html_e( 'Status filter', 'owambe-connect-core' ); ?></label></th>
						<td>
							<select id="oc-exp-status" name="status">
								<option value="all"><?php esc_html_e( 'All (approved + pending + needs changes)', 'owambe-connect-core' ); ?></option>
								<option value="<?php echo esc_attr( OC_STATUS_APPROVED ); ?>"><?php esc_html_e( 'Approved only', 'owambe-connect-core' ); ?></option>
								<option value="<?php echo esc_attr( OC_STATUS_PENDING ); ?>"><?php esc_html_e( 'Pending review only', 'owambe-connect-core' ); ?></option>
								<option value="<?php echo esc_attr( OC_STATUS_REJECTED ); ?>"><?php esc_html_e( 'Needs changes only', 'owambe-connect-core' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'owambe-connect-core' ); ?></th>
						<td>
							<label><input type="checkbox" name="include_drafts" value="1"/> <?php esc_html_e( 'Include draft (not-yet-submitted) listings', 'owambe-connect-core' ); ?></label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Download vendors CSV', 'owambe-connect-core' ); ?></button>
				</p>
			</form>

			<details class="oc-imp__legend" open>
				<summary><strong><?php esc_html_e( 'Column legend — what the importer reads', 'owambe-connect-core' ); ?></strong></summary>
				<p><?php esc_html_e( 'You can include any of the column names below in your CSV. The matcher is case-insensitive and ignores extra spaces/punctuation, so "Email Address", "email", "Contact Email" all map to the same field.', 'owambe-connect-core' ); ?></p>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Internal field', 'owambe-connect-core' ); ?></th>
						<th><?php esc_html_e( 'Accepted column names (any of)', 'owambe-connect-core' ); ?></th>
						<th><?php esc_html_e( 'Required?', 'owambe-connect-core' ); ?></th>
					</tr></thead>
					<tbody>
						<?php foreach ( $canonical as $key => $row ) : ?>
							<tr>
								<td><code><?php echo esc_html( $key ); ?></code><br><small><?php echo esc_html( $row['hint'] ); ?></small></td>
								<td><?php echo esc_html( implode( ' • ', self::$aliases[ $key ] ?? [] ) ); ?></td>
								<td><?php echo $row['required'] ? '<strong style="color:#B0354F">' . esc_html__( 'Yes', 'owambe-connect-core' ) . '</strong>' : esc_html__( 'No', 'owambe-connect-core' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</details>
		</div>

		<style>
			.oc-imp__lead { color:#6B6361; max-width:780px; }
			.oc-imp__form { background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:18px 24px; margin:14px 0; }
			.oc-imp__legend { margin-top:24px; background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:14px 18px; }
			.oc-imp__legend table { margin-top:10px; }
			.oc-imp-report { background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:18px 24px; margin-bottom:18px; }
			.oc-imp-report__head { display:flex; flex-wrap:wrap; gap:14px; align-items:center; }
			.oc-imp-report__pill { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:999px; font-weight:600; font-size:13px; }
			.oc-imp-report__pill--ok    { background:#E9F5EF; color:#1F4D3A; border:1px solid #2E7D5B; }
			.oc-imp-report__pill--skip  { background:#FAF1DE; color:#A8893D; border:1px solid #C9A961; }
			.oc-imp-report__pill--err   { background:#FBE5E6; color:#7B1F2C; border:1px solid #B0354F; }
			.oc-imp-report__mode        { font-weight:600; color:#1F1B1A; }
			.oc-imp-report__mode--dry   { color:#B8860B; }
			.oc-imp-report__table       { margin-top:12px; }
			.oc-imp-report__table .col-status { width:90px; }
			.oc-imp-report__row-status--created { color:#1F4D3A; font-weight:700; }
			.oc-imp-report__row-status--skipped { color:#A8893D; font-weight:700; }
			.oc-imp-report__row-status--error   { color:#B0354F; font-weight:700; }

			/* Full-screen loader shown while the importer is processing. The
			   CSV form is a regular POST → admin-post.php so the browser
			   itself blocks the page until the redirect comes back; this
			   overlay just gives the admin visible feedback that work is
			   happening instead of a frozen-looking screen. */
			.oc-imp-overlay {
				position: fixed; inset: 0;
				display: flex; flex-direction: column;
				align-items: center; justify-content: center;
				background: rgba(31, 27, 26, .72);
				backdrop-filter: blur(3px);
				z-index: 99999;
				color: #fff;
			}
			.oc-imp-overlay__spinner {
				width: 56px; height: 56px;
				border: 4px solid rgba(255,255,255,.18);
				border-top-color: #C9A961;
				border-right-color: #C9A961;
				border-radius: 50%;
				animation: oc-imp-spin .9s linear infinite;
				margin-bottom: 18px;
			}
			.oc-imp-overlay__title {
				font-family: Georgia, serif;
				font-size: 1.4rem;
				margin: 0 0 4px;
				color: #fff;
			}
			.oc-imp-overlay__sub {
				color: #E4DDD2;
				font-size: 14px;
				max-width: 460px;
				text-align: center;
				margin: 0;
				line-height: 1.55;
			}
			@keyframes oc-imp-spin { to { transform: rotate(360deg); } }
		</style>

		<script>
		(function () {
			// Import form: show a loader overlay during the POST so admin
			// doesn't think the browser has frozen on a 200-row commit.
			var importForm = document.querySelector('.oc-imp__form:not(.oc-imp__form--export)');
			if (importForm) {
				importForm.addEventListener('submit', function (e) {
					var btn = e.submitter || importForm.querySelector('button[type=submit]');
					var isCommit = btn && btn.value === 'commit';
					// CRITICAL: persist the chosen mode in a hidden input
					// BEFORE we disable the submit button. Browsers drop the
					// name=value of a disabled submit button from the POST
					// body, so without this the server side always reads the
					// default mode (dry_run) and the user's "Run import"
					// click silently becomes a preview.
					var hidden = importForm.querySelector('input[type="hidden"][name="mode"]');
					if (!hidden) {
						hidden = document.createElement('input');
						hidden.type = 'hidden';
						hidden.name = 'mode';
						importForm.appendChild(hidden);
					}
					hidden.value = isCommit ? 'commit' : 'dry_run';
					var overlay = document.createElement('div');
					overlay.className = 'oc-imp-overlay';
					overlay.innerHTML =
						'<div class="oc-imp-overlay__spinner" role="status" aria-label="<?php echo esc_js( __( 'Importing', 'owambe-connect-core' ) ); ?>"></div>' +
						'<h2 class="oc-imp-overlay__title">' +
							(isCommit
								? <?php echo wp_json_encode( __( 'Importing vendors…', 'owambe-connect-core' ) ); ?>
								: <?php echo wp_json_encode( __( 'Running dry-run…', 'owambe-connect-core' ) ); ?>) +
						'</h2>' +
						'<p class="oc-imp-overlay__sub">' +
						(isCommit
							? <?php echo wp_json_encode( __( 'Creating accounts + listings for every row in the CSV. This can take a minute for large files — please don\'t close the tab. We\'ll redirect you to the result page when it\'s done.', 'owambe-connect-core' ) ); ?>
							: <?php echo wp_json_encode( __( 'Reading the CSV and matching every column. No changes are being saved.', 'owambe-connect-core' ) ); ?>) +
						'</p>';
					document.body.appendChild(overlay);
					// Only disable the submit BUTTONS to prevent double-submit.
					// We MUST NOT disable the whole form's elements — browsers
					// omit `disabled` fields from the POST body, which would
					// strip the hidden `action` + nonce and land the request
					// on /wp-admin/admin-post.php with no handler to route to
					// (= a blank white screen).
					importForm.querySelectorAll('button[type="submit"]').forEach(function (b) {
						b.disabled = true;
					});
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Render the live progress UI when ?session=<key> is set — the page
	 * the admin lands on after uploading a >50-row CSV. The JS makes
	 * paced AJAX calls to handle_batch() until every row is processed,
	 * then redirects to the standard ?report= result page.
	 */
	private function render_progress( $session_key, array $session ) {
		$total = (int) ( $session['total'] ?? 0 );
		$mode  = ( $session['opts']['mode'] ?? 'dry_run' ) === 'commit' ? 'commit' : 'dry_run';
		$nonce = wp_create_nonce( self::BATCH_ACTION );
		?>
		<div class="oc-imp__progress"
			data-session="<?php echo esc_attr( $session_key ); ?>"
			data-nonce="<?php echo esc_attr( $nonce ); ?>"
			data-total="<?php echo (int) $total; ?>"
			data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-action="<?php echo esc_attr( self::BATCH_ACTION ); ?>"
			style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:28px 32px;margin:18px 0 24px;max-width:780px;">
			<h2 style="margin:0 0 6px;color:#6E0F2C;font-family:Georgia,serif;">
				<?php echo $mode === 'commit'
					? esc_html__( 'Importing vendors…', 'owambe-connect-core' )
					: esc_html__( 'Running dry-run preview…', 'owambe-connect-core' ); ?>
			</h2>
			<p class="oc-imp__progress-status" style="color:#6B6361;margin:0 0 14px;">
				<?php printf(
					/* translators: %d: total rows */
					esc_html__( 'Processed 0 of %d rows.', 'owambe-connect-core' ),
					$total
				); ?>
			</p>
			<div style="height:10px;background:#EFEAE2;border-radius:999px;overflow:hidden;margin:0 0 12px;">
				<span class="oc-imp__progress-fill" style="display:block;height:100%;width:0%;background:linear-gradient(90deg,#6E0F2C,#C9A961);transition:width .3s ease;"></span>
			</div>
			<p class="oc-imp__progress-counts" style="color:#3D3735;font-size:13px;margin:0 0 6px;">
				<span style="color:#2E7D5B;font-weight:600;"><?php esc_html_e( 'Created', 'owambe-connect-core' ); ?>: <span data-k="created">0</span></span>
				&nbsp;&middot;&nbsp;
				<span style="color:#6B6361;font-weight:600;"><?php esc_html_e( 'Skipped', 'owambe-connect-core' ); ?>: <span data-k="skipped">0</span></span>
				&nbsp;&middot;&nbsp;
				<span style="color:#B0354F;font-weight:600;"><?php esc_html_e( 'Errored', 'owambe-connect-core' ); ?>: <span data-k="errored">0</span></span>
			</p>
			<p style="color:#9B9290;font-size:12px;margin:14px 0 0;">
				<?php esc_html_e( 'You can leave this tab open — the import runs automatically in small batches so it never times out. Closing the tab will pause the import; just reload the page to resume.', 'owambe-connect-core' ); ?>
			</p>
		</div>
		<script>
		(function () {
			var box = document.querySelector('.oc-imp__progress');
			if (!box) return;
			var statusEl = box.querySelector('.oc-imp__progress-status');
			var fillEl   = box.querySelector('.oc-imp__progress-fill');
			var counts   = {
				created: box.querySelector('[data-k="created"]'),
				skipped: box.querySelector('[data-k="skipped"]'),
				errored: box.querySelector('[data-k="errored"]')
			};
			var total = parseInt(box.dataset.total, 10) || 0;
			var ajaxUrl = box.dataset.ajax;
			var payload = function () {
				var fd = new FormData();
				fd.append('action', box.dataset.action);
				fd.append('session', box.dataset.session);
				fd.append('_nonce', box.dataset.nonce);
				return fd;
			};
			function tick() {
				fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: payload() })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						if (!res || !res.success || !res.data) {
							statusEl.textContent = (res && res.data && res.data.msg)
								? res.data.msg
								: <?php echo wp_json_encode( __( 'Something went wrong with the import. Reload the page to try again.', 'owambe-connect-core' ) ); ?>;
							statusEl.style.color = '#B0354F';
							return;
						}
						var d = res.data;
						var pct = total > 0 ? Math.round((d.processed / total) * 100) : 100;
						fillEl.style.width = pct + '%';
						statusEl.textContent = <?php echo wp_json_encode( __( 'Processed', 'owambe-connect-core' ) ); ?>
							+ ' ' + d.processed + ' / ' + d.total + ' (' + pct + '%)';
						counts.created.textContent = d.counts.created;
						counts.skipped.textContent = d.counts.skipped;
						counts.errored.textContent = d.counts.errored;
						if (d.done) {
							statusEl.textContent = <?php echo wp_json_encode( __( 'Import complete — opening the report…', 'owambe-connect-core' ) ); ?>;
							setTimeout(function () {
								if (d.report_url) window.location.href = d.report_url;
							}, 600);
						} else {
							// Small gap between batches gives the DB a breath
							// and keeps the UI feeling responsive on slow hosts.
							setTimeout(tick, 250);
						}
					})
					.catch(function () {
						statusEl.textContent = <?php echo wp_json_encode( __( 'Network error — retrying in a few seconds…', 'owambe-connect-core' ) ); ?>;
						setTimeout(tick, 3000);
					});
			}
			tick();
		})();
		</script>
		<?php
	}

	/** Render the result/report block when ?report=<key> is set. */
	private function render_report( array $report ) {
		// Some reports (like batch-delete confirmations) are just a banner —
		// short-circuit the regular import-stats UI in that case.
		if ( ! empty( $report['banner'] ) && empty( $report['rows'] ) ) {
			?>
			<div class="oc-imp-report" style="border-color:#2E7D5B;background:#E9F5EF;">
				<strong style="color:#1F4D3A;">✓ <?php echo esc_html( $report['banner'] ); ?></strong>
			</div>
			<?php
			return;
		}
		$is_dry      = ( $report['mode'] ?? '' ) === 'dry_run';
		$mode_label  = $is_dry
			? __( 'Dry run — no changes saved', 'owambe-connect-core' )
			: __( 'Live import — vendors saved to the database', 'owambe-connect-core' );
		$mode_class  = $is_dry ? 'oc-imp-report__mode--dry' : '';
		$created     = (int) ( $report['counts']['created'] ?? 0 );
		$skipped     = (int) ( $report['counts']['skipped'] ?? 0 );
		$errors      = (int) ( $report['counts']['errored'] ?? 0 );
		$rows        = (array) ( $report['rows'] ?? [] );
		?>

		<?php if ( $is_dry ) : ?>
			<div style="background:#FFF8E6;border:1px solid #C9A961;border-left:4px solid #C9A961;border-radius:8px;padding:16px 20px;margin:18px 0;">
				<p style="margin:0 0 8px;color:#6E0F2C;font-size:14px;font-weight:700;">
					<?php esc_html_e( "👀 This was just a preview — nothing has been saved yet.", 'owambe-connect-core' ); ?>
				</p>
				<p style="margin:0 0 4px;color:#3D3735;font-size:13.5px;line-height:1.55;">
					<?php
					printf(
						/* translators: 1: created count, 2: errored count */
						esc_html__( '%1$d rows look ready to import, %2$d have errors that need fixing first.', 'owambe-connect-core' ),
						$created,
						$errors
					);
					?>
				</p>
				<p style="margin:0;color:#3D3735;font-size:13.5px;line-height:1.55;">
					<?php
					if ( $errors === 0 ) {
						esc_html_e( 'Ready to go — scroll up to the upload form, choose the same CSV again, and click the burgundy "Run import" button to save them to the database.', 'owambe-connect-core' );
					} else {
						esc_html_e( 'Fix the rows marked Error below (or remove them from the CSV), then re-upload and click "Run import" to save the rest.', 'owambe-connect-core' );
					}
					?>
				</p>
			</div>
		<?php else : ?>
			<div style="background:#E9F5EF;border:1px solid #2E7D5B;border-left:4px solid #2E7D5B;border-radius:8px;padding:16px 20px;margin:18px 0;">
				<p style="margin:0;color:#1F4D3A;font-size:14px;font-weight:700;">
					<?php
					printf(
						/* translators: 1: created count */
						esc_html__( '✓ Import complete — %d vendor(s) saved to the database.', 'owambe-connect-core' ),
						$created
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<div class="oc-imp-report">
			<div class="oc-imp-report__head">
				<span class="oc-imp-report__mode <?php echo esc_attr( $mode_class ); ?>"><?php echo esc_html( $mode_label ); ?></span>
				<span class="oc-imp-report__pill oc-imp-report__pill--ok">✓ <?php
					/* translators: %d count */
					printf( esc_html( _n( '%d created', '%d created', $created, 'owambe-connect-core' ) ), $created );
				?></span>
				<span class="oc-imp-report__pill oc-imp-report__pill--skip">↻ <?php
					printf( esc_html( _n( '%d skipped', '%d skipped', $skipped, 'owambe-connect-core' ) ), $skipped );
				?></span>
				<span class="oc-imp-report__pill oc-imp-report__pill--err">✕ <?php
					printf( esc_html( _n( '%d error', '%d errors', $errors, 'owambe-connect-core' ) ), $errors );
				?></span>
				<?php if ( ! empty( $report['unknown_columns'] ) ) : ?>
					<span style="color:#6B6361;font-size:13px;">
						<?php
						/* translators: %s comma-separated column names */
						printf( esc_html__( 'Unrecognised columns ignored: %s', 'owambe-connect-core' ), '<em>' . esc_html( implode( ', ', $report['unknown_columns'] ) ) . '</em>' );
						?>
					</span>
				<?php endif; ?>
				<?php if ( ! empty( $report['created_categories'] ) ) : ?>
					<span style="color:#6B6361;font-size:13px;">
						<?php
						printf( esc_html__( 'New taxonomy terms auto-created: %s', 'owambe-connect-core' ), '<em>' . esc_html( implode( ', ', $report['created_categories'] ) ) . '</em>' );
						?>
					</span>
				<?php endif; ?>
			</div>

			<?php
			$batch_post_ids = (array) ( $report['created_post_ids'] ?? [] );
			$report_key     = isset( $_GET['report'] ) ? sanitize_key( $_GET['report'] ) : '';
			if ( ( $report['mode'] ?? '' ) === 'commit' && $report_key && $batch_post_ids ) : ?>
				<div style="margin-top:14px;padding:12px 14px;background:#FBE5E6;border:1px solid #B0354F;border-radius:6px;display:flex;flex-wrap:wrap;align-items:center;gap:12px;justify-content:space-between;">
					<div>
						<strong style="color:#7B1F2C;">⚠ <?php esc_html_e( 'Made a mistake?', 'owambe-connect-core' ); ?></strong>
						<span style="color:#1F1B1A;"><?php
							/* translators: %d: vendor count */
							printf( esc_html__( 'You can permanently delete every vendor + user account created in this import (%d total) in one click. This cannot be undone.', 'owambe-connect-core' ), count( $batch_post_ids ) );
						?></span>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return self.confirm_oc_batch_delete(this);">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::DELETE_BATCH ); ?>"/>
						<input type="hidden" name="batch_key" value="<?php echo esc_attr( $report_key ); ?>"/>
						<?php wp_nonce_field( self::DELETE_BATCH . '_' . $report_key ); ?>
						<button type="submit" class="button" style="background:#B0354F;border-color:#B0354F;color:#fff;">
							<?php
							/* translators: %d count */
							printf( esc_html__( '✕ Delete this batch (%d vendors)', 'owambe-connect-core' ), count( $batch_post_ids ) );
							?>
						</button>
					</form>
				</div>
				<script>
				self.confirm_oc_batch_delete = function (form) {
					var phrase = <?php echo wp_json_encode( __( 'DELETE', 'owambe-connect-core' ) ); ?>;
					var prompt_msg = <?php echo wp_json_encode( sprintf(
						/* translators: %d count */
						__( 'You are about to permanently delete %d vendor account(s) and their listings created in this import.\n\nThis cannot be undone. Type DELETE to confirm:', 'owambe-connect-core' ),
						count( $batch_post_ids )
					) ); ?>;
					var typed = window.prompt(prompt_msg, '');
					if (typed !== phrase) {
						if (typed !== null) window.alert(<?php echo wp_json_encode( __( 'Cancelled — confirmation phrase did not match.', 'owambe-connect-core' ) ); ?>);
						return false;
					}
					return true;
				};
				</script>
			<?php endif; ?>

			<?php if ( $rows ) : ?>
				<table class="widefat striped oc-imp-report__table">
					<thead><tr>
						<th class="col-status"><?php esc_html_e( 'Status', 'owambe-connect-core' ); ?></th>
						<th><?php esc_html_e( 'Row', 'owambe-connect-core' ); ?></th>
						<th><?php esc_html_e( 'Business', 'owambe-connect-core' ); ?></th>
						<th><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?></th>
						<th><?php esc_html_e( 'Category', 'owambe-connect-core' ); ?></th>
						<th><?php esc_html_e( 'Notes', 'owambe-connect-core' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td class="oc-imp-report__row-status--<?php echo esc_attr( $r['status'] ); ?>"><?php echo esc_html( ucfirst( $r['status'] ) ); ?></td>
							<td><?php echo (int) $r['line']; ?></td>
							<td><?php echo esc_html( $r['business_name'] ?? '' ); ?></td>
							<td><?php echo esc_html( $r['email'] ?? '' ); ?></td>
							<td><?php echo esc_html( $r['category'] ?? '' ); ?></td>
							<td><?php echo esc_html( $r['note'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────
	//  Handle upload
	// ─────────────────────────────────────────────────────────
	public function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}
		check_admin_referer( self::ACTION, self::NONCE );

		// Big CSVs (200+ rows) blow past Hostinger's default 30-60s PHP
		// execution limit during commit. Dry-run is fast because it never
		// touches the DB, so admin sees a working preview but a silent fail
		// on the real run. Lifting these limits + skipping every non-
		// essential bit of WP overhead keeps the import inside the window.
		@set_time_limit( 0 );
		@ini_set( 'memory_limit', '512M' );
		@ini_set( 'max_execution_time', '0' );
		ignore_user_abort( true );

		$mode               = isset( $_POST['mode'] ) && 'commit' === $_POST['mode'] ? 'commit' : 'dry_run';
		$default_status     = isset( $_POST['default_status'] ) && OC_STATUS_APPROVED === $_POST['default_status']
			? OC_STATUS_APPROVED : OC_STATUS_PENDING;
		$skip_existing      = ! empty( $_POST['skip_existing'] );
		$grandfather_verify = ! empty( $_POST['grandfather_verify'] );
		$send_welcome       = ! empty( $_POST['send_welcome'] );

		if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) {
			$this->redirect_with_error( __( 'No CSV uploaded.', 'owambe-connect-core' ) );
		}
		if ( (int) $_FILES['csv']['size'] > self::UPLOAD_MAX_MB * 1024 * 1024 ) {
			$this->redirect_with_error( __( 'CSV is too large.', 'owambe-connect-core' ) );
		}

		// Read + normalise to a UTF-8 array of rows.
		$rows = self::read_csv( $_FILES['csv']['tmp_name'] );
		if ( count( $rows ) < 2 ) {
			$this->redirect_with_error( __( 'CSV must have a header row and at least one data row.', 'owambe-connect-core' ) );
		}

		$header  = array_shift( $rows );
		$mapping = self::map_columns( $header );
		$opts    = [
			'mode'               => $mode,
			'default_status'     => $default_status,
			'skip_existing'      => $skip_existing,
			'grandfather_verify' => $grandfather_verify,
			'send_welcome'       => $send_welcome,
		];

		// ─── Batched mode for big files ─────────────────────────────
		// Anything over INLINE_THRESHOLD rows is processed in 25-row AJAX
		// batches so a single PHP request never has to do all the work at
		// once. Each batch fits comfortably inside even strict shared-host
		// timeouts, and the user gets a live progress bar instead of a
		// silent "did it work?" wait.
		if ( count( $rows ) > self::INLINE_THRESHOLD ) {
			$session_key = strtolower( wp_generate_password( 16, false, false ) );
			$session     = [
				'rows'               => $rows,
				'mapping'            => $mapping,
				'opts'               => $opts,
				'offset'             => 0,
				'total'              => count( $rows ),
				'started_at'         => time(),
				'report_rows'        => [],
				'counts'             => [ 'created' => 0, 'skipped' => 0, 'errored' => 0 ],
				'created_categories' => [],
				'created_post_ids'   => [],
				'created_user_ids'   => [],
				'unknown_columns'    => $mapping['_unknown'],
				'mode'               => $mode,
			];
			set_transient( self::TRANSIENT_SESSION . $session_key, $session, HOUR_IN_SECONDS );
			wp_safe_redirect( add_query_arg( [
				'page'    => self::PAGE,
				'session' => $session_key,
			], admin_url( 'admin.php' ) ) );
			exit;
		}

		// ─── Inline mode (small files, ≤ INLINE_THRESHOLD rows) ────
		// Defer everything WordPress would otherwise do per-row that adds
		// up over 200+ vendors: counting taxonomy terms, queuing background
		// jobs, persisting cache writes, sending the "you just created an
		// account" notification to every imported user. These each look
		// cheap individually but multiply badly at scale.
		$committing = ( 'commit' === $mode );
		if ( $committing ) {
			wp_defer_term_counting( true );
			wp_defer_comment_counting( true );
			wp_suspend_cache_addition( true );
			// Silence WP's per-user "new user" email — we have our own welcome
			// email path (gated by the $send_welcome checkbox) and the core
			// notifier just slows the loop down for no benefit.
			add_filter( 'send_email_change_email', '__return_false' );
			add_filter( 'send_password_change_email', '__return_false' );
			remove_action( 'register_new_user',     'wp_send_new_user_notifications' );
			remove_action( 'edit_user_created_user', 'wp_send_new_user_notifications', 10 );

			// CRITICAL: detach the per-row verification-email send. The
			// oc_after_vendor_registered action is hooked by
			// OC_Email_Verification::issue_token_for_new_vendor — which
			// fires wp_mail() once for every imported vendor. On 200+ rows
			// that's 200+ SMTP round-trips inside one PHP request and is
			// the primary cause of silent timeouts on shared hosting. When
			// grandfather_verify is on we don't need verification emails at
			// all; when it's off, admin should use the "Bulk welcome /
			// reset password" tool afterwards (it's paced + AJAX-driven).
			if ( class_exists( 'OC_Email_Verification' ) ) {
				remove_action( 'oc_after_vendor_registered', [ 'OC_Email_Verification', 'issue_token_for_new_vendor' ], 20 );
			}
		}

		$report = $this->process( $rows, $mapping, $opts );

		if ( $committing ) {
			wp_defer_term_counting( false );
			wp_defer_comment_counting( false );
			wp_suspend_cache_addition( false );
			if ( class_exists( 'OC_Email_Verification' ) ) {
				// Reattach so single-vendor sign-ups after this import still
				// trigger their verification email like normal.
				add_action( 'oc_after_vendor_registered', [ 'OC_Email_Verification', 'issue_token_for_new_vendor' ], 20, 2 );
			}
		}
		$report['unknown_columns'] = $mapping['_unknown'];
		$report['mode']            = $mode;

		// Stash the report for the result page (transient, 30 min).
		// IMPORTANT: lowercase only — the render side reads ?report= through
		// sanitize_key() which lowercases everything, so a mixed-case key
		// would never match what we just stored.
		$key = strtolower( wp_generate_password( 12, false, false ) );
		set_transient( self::TRANSIENT_REPORT . $key, $report, 30 * MINUTE_IN_SECONDS );

		// Stash the import-batch IDs under a separate 24h transient — feeds
		// the "Delete this batch" cleanup button + the "Email this batch"
		// cohort picker on the emails page.
		if ( 'commit' === $mode && ! empty( $report['created_post_ids'] ) ) {
			$batch = [
				'created_at'   => time(),
				'post_ids'     => array_values( array_filter( array_map( 'intval', $report['created_post_ids'] ) ) ),
				'user_ids'     => array_values( array_filter( array_map( 'intval', $report['created_user_ids'] ?? [] ) ) ),
				'report_key'   => $key,
			];
			set_transient( self::TRANSIENT_BATCH . $key, $batch, DAY_IN_SECONDS );
			// Track the latest batch key in an option so the email tool can
			// find it even after the user navigates away from the report.
			update_option( 'oc_last_import_batch', $key, false );
		}

		wp_safe_redirect( add_query_arg( [
			'page'   => self::PAGE,
			'report' => $key,
		], admin_url( 'admin.php' ) ) );
		exit;
	}

	private function redirect_with_error( $message ) {
		wp_die(
			esc_html( $message ),
			__( 'Import failed', 'owambe-connect-core' ),
			[ 'back_link' => true ]
		);
	}

	/**
	 * AJAX handler — process one BATCH_SIZE slice of the upload session.
	 * The page-side JS polls this until done; each call fits comfortably
	 * inside even a strict 30-second PHP execution limit, then returns
	 * progress JSON so the UI can update its bar + counts and call again.
	 */
	public function handle_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'msg' => __( 'Unauthorised.', 'owambe-connect-core' ) ], 403 );
		}
		check_ajax_referer( self::BATCH_ACTION, '_nonce' );

		$session_key = isset( $_POST['session'] ) ? sanitize_key( wp_unslash( $_POST['session'] ) ) : '';
		$session     = $session_key ? get_transient( self::TRANSIENT_SESSION . $session_key ) : null;
		if ( ! is_array( $session ) ) {
			wp_send_json_error( [ 'msg' => __( 'This import session expired. Please re-upload your CSV.', 'owambe-connect-core' ) ] );
		}

		// Same lift + defer setup as the inline path — applied PER batch
		// because each AJAX request is its own PHP process.
		@set_time_limit( 0 );
		@ini_set( 'memory_limit', '512M' );
		@ini_set( 'max_execution_time', '0' );
		ignore_user_abort( true );

		$committing = ( 'commit' === ( $session['opts']['mode'] ?? 'dry_run' ) );
		if ( $committing ) {
			wp_defer_term_counting( true );
			wp_defer_comment_counting( true );
			wp_suspend_cache_addition( true );
			add_filter( 'send_email_change_email', '__return_false' );
			add_filter( 'send_password_change_email', '__return_false' );
			remove_action( 'register_new_user',     'wp_send_new_user_notifications' );
			remove_action( 'edit_user_created_user', 'wp_send_new_user_notifications', 10 );
			if ( class_exists( 'OC_Email_Verification' ) ) {
				remove_action( 'oc_after_vendor_registered', [ 'OC_Email_Verification', 'issue_token_for_new_vendor' ], 20 );
			}
		}

		$offset = (int) ( $session['offset'] ?? 0 );
		$slice  = array_slice( $session['rows'], $offset, self::BATCH_SIZE );
		$partial = $this->process( $slice, $session['mapping'], $session['opts'] );

		// Merge the partial into the rolling accumulators.
		$session['report_rows']        = array_merge( $session['report_rows'],        (array) ( $partial['rows']               ?? [] ) );
		$session['created_post_ids']   = array_merge( $session['created_post_ids'],   (array) ( $partial['created_post_ids']   ?? [] ) );
		$session['created_user_ids']   = array_merge( $session['created_user_ids'],   (array) ( $partial['created_user_ids']   ?? [] ) );
		$session['created_categories'] = array_merge( $session['created_categories'], (array) ( $partial['created_categories'] ?? [] ) );
		$session['counts']['created'] += (int) ( $partial['counts']['created'] ?? 0 );
		$session['counts']['skipped'] += (int) ( $partial['counts']['skipped'] ?? 0 );
		$session['counts']['errored'] += (int) ( $partial['counts']['errored'] ?? 0 );
		$session['offset']             = $offset + count( $slice );

		$done = $session['offset'] >= $session['total'];

		if ( $done ) {
			// All rows processed — assemble the final report transient that
			// the existing render_report() path will pick up.
			$report_key = strtolower( wp_generate_password( 12, false, false ) );
			$report = [
				'rows'                => $session['report_rows'],
				'counts'              => $session['counts'],
				'created_categories' => $session['created_categories'],
				'created_post_ids'    => $session['created_post_ids'],
				'created_user_ids'    => $session['created_user_ids'],
				'unknown_columns'     => $session['unknown_columns'],
				'mode'                => $session['opts']['mode'] ?? 'dry_run',
			];
			set_transient( self::TRANSIENT_REPORT . $report_key, $report, 30 * MINUTE_IN_SECONDS );

			// Batch-tracker transient for the "Delete batch" button + the
			// "Email this batch" cohort picker on the emails page.
			if ( $committing && ! empty( $session['created_post_ids'] ) ) {
				$batch_payload = [
					'created_at' => time(),
					'post_ids'   => array_values( array_filter( array_map( 'intval', $session['created_post_ids'] ) ) ),
					'user_ids'   => array_values( array_filter( array_map( 'intval', $session['created_user_ids'] ) ) ),
					'report_key' => $report_key,
				];
				set_transient( self::TRANSIENT_BATCH . $report_key, $batch_payload, DAY_IN_SECONDS );
				update_option( 'oc_last_import_batch', $report_key, false );
			}

			delete_transient( self::TRANSIENT_SESSION . $session_key );

			wp_send_json_success( [
				'done'       => true,
				'processed'  => (int) $session['offset'],
				'total'      => (int) $session['total'],
				'counts'     => $session['counts'],
				'report_url' => add_query_arg(
					[ 'page' => self::PAGE, 'report' => $report_key ],
					admin_url( 'admin.php' )
				),
			] );
		}

		set_transient( self::TRANSIENT_SESSION . $session_key, $session, HOUR_IN_SECONDS );

		wp_send_json_success( [
			'done'      => false,
			'processed' => (int) $session['offset'],
			'total'     => (int) $session['total'],
			'counts'    => $session['counts'],
		] );
	}

	/**
	 * "Delete this batch" — wipes every vendor created in a single import.
	 * Reads the batch IDs from the per-batch transient (24-hour TTL) and
	 * delegates per-vendor cleanup to OC_Admin_Vendors_List::delete_vendor_completely()
	 * so the same user-safety guards apply.
	 */
	public function handle_delete_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}
		$batch_key = isset( $_POST['batch_key'] ) ? sanitize_key( wp_unslash( $_POST['batch_key'] ) ) : '';
		if ( ! $batch_key ) {
			$this->redirect_with_error( __( 'Missing batch reference.', 'owambe-connect-core' ) );
		}
		check_admin_referer( self::DELETE_BATCH . '_' . $batch_key );

		$batch = get_transient( self::TRANSIENT_BATCH . $batch_key );
		if ( ! is_array( $batch ) || empty( $batch['post_ids'] ) ) {
			$this->redirect_with_error( __( 'That import batch has expired or no longer exists.', 'owambe-connect-core' ) );
		}

		$deleted = 0;
		foreach ( (array) $batch['post_ids'] as $post_id ) {
			if ( ! current_user_can( 'delete_post', $post_id ) ) {
				continue;
			}
			if ( OC_Admin_Vendors_List::delete_vendor_completely( (int) $post_id ) ) {
				$deleted++;
			}
		}

		// One-shot batch — drop the transient + the "last batch" pointer.
		delete_transient( self::TRANSIENT_BATCH . $batch_key );
		if ( get_option( 'oc_last_import_batch' ) === $batch_key ) {
			delete_option( 'oc_last_import_batch' );
		}

		// Stash a tiny one-shot success notice via a fresh report transient
		// so the redirect target shows the outcome. Lowercase to round-trip
		// through sanitize_key() on the receiving render page.
		$msg_key = strtolower( wp_generate_password( 12, false, false ) );
		set_transient( self::TRANSIENT_REPORT . $msg_key, [
			'mode'   => 'commit',
			'counts' => [ 'created' => 0, 'skipped' => 0, 'errored' => 0 ],
			'rows'   => [],
			'unknown_columns' => [],
			'banner' => sprintf(
				/* translators: %d count */
				__( 'Import batch deleted — %d vendor(s) + their user accounts removed.', 'owambe-connect-core' ),
				$deleted
			),
		], 10 * MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( [
			'page'   => self::PAGE,
			'report' => $msg_key,
		], admin_url( 'admin.php' ) ) );
		exit;
	}

	// ─────────────────────────────────────────────────────────
	//  CSV reader (UTF-8 BOM-safe, picks delimiter)
	// ─────────────────────────────────────────────────────────
	private static function read_csv( $path ) {
		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			return [];
		}
		// Strip UTF-8 BOM if present.
		if ( substr( $contents, 0, 3 ) === "\xEF\xBB\xBF" ) {
			$contents = substr( $contents, 3 );
		}
		// Detect delimiter: comma vs semicolon by the first non-empty header line.
		$first = strstr( $contents, "\n", true ) ?: $contents;
		$delim = ( substr_count( $first, ';' ) > substr_count( $first, ',' ) ) ? ';' : ',';

		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, $contents );
		rewind( $fh );
		$out = [];
		while ( ( $row = fgetcsv( $fh, 0, $delim, '"', '\\' ) ) !== false ) {
			// Skip completely empty rows.
			if ( count( array_filter( $row, static fn ( $v ) => null !== $v && '' !== trim( (string) $v ) ) ) === 0 ) {
				continue;
			}
			$out[] = $row;
		}
		fclose( $fh );
		return $out;
	}

	// ─────────────────────────────────────────────────────────
	//  Column mapping (fuzzy)
	// ─────────────────────────────────────────────────────────
	private static function map_columns( array $header ) {
		$mapping  = [ '_unknown' => [] ];
		$alias_lookup = [];
		foreach ( self::$aliases as $key => $aliases ) {
			foreach ( $aliases as $a ) {
				$alias_lookup[ self::normalise_key( $a ) ] = $key;
			}
		}
		foreach ( $header as $i => $col ) {
			$norm = self::normalise_key( (string) $col );
			if ( '' === $norm ) {
				continue;
			}
			if ( isset( $alias_lookup[ $norm ] ) ) {
				$mapping[ $alias_lookup[ $norm ] ] = $i;
			} else {
				$mapping['_unknown'][] = trim( (string) $col );
			}
		}
		return $mapping;
	}

	private static function normalise_key( $s ) {
		$s = strtolower( (string) $s );
		// Drop anything that isn't a letter or number → single space.
		$s = preg_replace( '/[^a-z0-9]+/', ' ', $s );
		return trim( $s );
	}

	// ─────────────────────────────────────────────────────────
	//  Main row-by-row processor
	// ─────────────────────────────────────────────────────────
	private function process( array $rows, array $mapping, array $opts ) {
		$created_categories = [];
		$report_rows = [];
		$created_post_ids = [];
		$created_user_ids = [];
		$counts = [ 'created' => 0, 'skipped' => 0, 'errored' => 0 ];

		$get = function ( array $row, $key ) use ( $mapping ) {
			if ( ! isset( $mapping[ $key ] ) ) {
				return '';
			}
			$i = (int) $mapping[ $key ];
			return isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
		};

		foreach ( $rows as $idx => $row ) {
			$line_no = $idx + 2; // header is line 1
			$business_name = $get( $row, 'business_name' );
			$email_raw     = $get( $row, 'email' );
			$email         = $email_raw ? sanitize_email( $email_raw ) : '';

			$entry = [
				'line'          => $line_no,
				'business_name' => $business_name,
				'email'         => $email_raw,
				'category'      => $get( $row, 'category' ),
				'status'        => 'skipped',
				'note'          => '',
			];

			// Validation gate.
			if ( '' === $business_name && '' === $get( $row, 'first_name' ) ) {
				$entry['status'] = 'error';
				$entry['note']   = __( 'No business name (or first name) — skipped.', 'owambe-connect-core' );
				$report_rows[]   = $entry;
				$counts['errored']++;
				continue;
			}
			if ( '' === $email || ! is_email( $email ) ) {
				$entry['status'] = 'error';
				$entry['note']   = __( 'Missing or invalid email.', 'owambe-connect-core' );
				$report_rows[]   = $entry;
				$counts['errored']++;
				continue;
			}

			// Fallback: if business_name is blank but first/last name exist, use them.
			if ( '' === $business_name ) {
				$business_name = trim( $get( $row, 'first_name' ) . ' ' . $get( $row, 'last_name' ) );
			}
			$entry['business_name'] = $business_name;

			// Idempotency by email.
			$existing_user = email_exists( $email ) ?: username_exists( $email );
			if ( $existing_user && $opts['skip_existing'] ) {
				$entry['status'] = 'skipped';
				$entry['note']   = __( 'User already exists with this email.', 'owambe-connect-core' );
				$report_rows[]   = $entry;
				$counts['skipped']++;
				continue;
			}

			// Resolve category to a taxonomy term (create if missing + tracked).
			$category_raw    = $get( $row, 'category' );
			$category_term_id = 0;
			if ( '' !== $category_raw ) {
				$category_term_id = self::resolve_category_term(
					$category_raw,
					$opts['mode'] === 'commit',
					$created_categories
				);
			}

			$entry['category'] = $category_raw . ( $category_term_id
				? ''
				: ( '' === $category_raw ? '' : ' ⚠ uncategorised' )
			);

			// Build meta payload.
			$phone_local  = $get( $row, 'phone' );
			$phone_canon  = function_exists( 'oc_normalize_uk_whatsapp' ) ? oc_normalize_uk_whatsapp( $phone_local ) : $phone_local;
			$instagram    = self::clean_instagram( $get( $row, 'instagram' ) );
			$website      = self::clean_website( $get( $row, 'website' ) );
			$facebook     = $get( $row, 'facebook' );
			$description  = $get( $row, 'description' );
			$services     = $get( $row, 'services' );
			$registered   = self::clean_yes_no( $get( $row, 'registered' ) );
			$location_raw = $get( $row, 'location' );
			$country_raw  = $get( $row, 'country' );
			$country      = self::resolve_country( $country_raw, $location_raw );
			$areas        = self::split_areas( $get( $row, 'areas' ), $location_raw );

			// Dry run: only log what WOULD happen.
			if ( $opts['mode'] === 'dry_run' ) {
				$entry['status'] = 'created';
				$entry['note']   = sprintf(
					/* translators: 1: country slug, 2: areas count, 3: yes/no, 4: yes/no */
					__( 'Would create. Country=%1$s, areas=%2$d, registered=%3$s, phone=%4$s', 'owambe-connect-core' ),
					$country ?: '—',
					count( $areas ),
					$registered ?: '—',
					$phone_canon ?: '—'
				);
				$report_rows[] = $entry;
				$counts['created']++;
				continue;
			}

			// Commit.
			$user_id = self::create_vendor_user( $email, $business_name );
			if ( is_wp_error( $user_id ) ) {
				$entry['status'] = 'error';
				$entry['note']   = $user_id->get_error_message();
				$report_rows[]   = $entry;
				$counts['errored']++;
				continue;
			}

			$post_id = wp_insert_post( [
				'post_type'    => OC_CPT,
				'post_status'  => $opts['default_status'],
				'post_title'   => $business_name,
				'post_content' => $description,
				'post_author'  => $user_id,
			], true );
			if ( is_wp_error( $post_id ) ) {
				wp_delete_user( $user_id );
				$entry['status'] = 'error';
				$entry['note']   = $post_id->get_error_message();
				$report_rows[]   = $entry;
				$counts['errored']++;
				continue;
			}

			$loc_summary = self::compose_location_summary( $country, $areas, $location_raw );

			$meta_pairs = [
				'_oc_business_name'        => $business_name,
				'_oc_bio'                  => $description,
				'_oc_services'             => $services,
				'_oc_whatsapp'             => $phone_canon,
				'_oc_public_email'         => $email,
				'_oc_instagram'            => $instagram,
				'_oc_facebook'             => $facebook,
				'_oc_website'              => $website,
				'_oc_location'             => $loc_summary,
				'_oc_location_country'     => $country,
				'_oc_location_areas'       => $areas,
				'_oc_registered_business'  => $registered,
				'_oc_submitted_for_review' => 1, // bulk-imported = treated as submitted
				'_oc_email_verified'       => $opts['grandfather_verify'] ? 1 : 0,
			];
			foreach ( $meta_pairs as $k => $v ) {
				update_post_meta( $post_id, $k, $v );
			}

			if ( $category_term_id ) {
				wp_set_object_terms( $post_id, [ $category_term_id ], OC_TAX );
			}

			// Trigger the registered hook so vendor number + completion % are
			// computed and any other listeners fire.
			do_action( 'oc_after_vendor_registered', $post_id, $user_id );

			if ( $opts['send_welcome'] && class_exists( 'OC_Mail' ) ) {
				OC_Mail::application_received( $post_id );
			}

			$created_post_ids[] = (int) $post_id;
			$created_user_ids[] = (int) $user_id;

			$entry['status']  = 'created';
			$entry['post_id'] = (int) $post_id;
			$entry['user_id'] = (int) $user_id;
			$entry['note']    = sprintf(
				/* translators: 1: vendor number, 2: post id */
				__( 'Created. Vendor # %1$s · post #%2$d', 'owambe-connect-core' ),
				(string) get_post_meta( $post_id, '_oc_vendor_number', true ) ?: '—',
				$post_id
			);
			$report_rows[] = $entry;
			$counts['created']++;
		}

		return [
			'rows'                => $report_rows,
			'counts'              => $counts,
			'created_categories'  => $created_categories,
			'created_post_ids'    => $created_post_ids,
			'created_user_ids'    => $created_user_ids,
		];
	}

	// ─────────────────────────────────────────────────────────
	//  Helpers
	// ─────────────────────────────────────────────────────────
	private static function create_vendor_user( $email, $display_name ) {
		// Pick a unique login. Prefer the email; if that's taken, append a
		// numeric suffix until we land on a free slot.
		$base  = sanitize_user( $email, true );
		$login = $base;
		$i     = 1;
		while ( username_exists( $login ) ) {
			$login = $base . '-' . $i;
			$i++;
			if ( $i > 50 ) break;
		}
		return wp_insert_user( [
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 16 ),
			'display_name' => $display_name,
			'role'         => OC_ROLE,
		] );
	}

	private static function clean_instagram( $raw ) {
		$h = trim( (string) $raw );
		if ( '' === $h ) return '';
		// If it's a full URL, extract the path.
		if ( preg_match( '#instagram\.com/([A-Za-z0-9_.]+)#i', $h, $m ) ) {
			return $m[1];
		}
		return ltrim( $h, '@' );
	}

	private static function clean_website( $raw ) {
		$w = trim( (string) $raw );
		if ( '' === $w ) return '';
		if ( ! preg_match( '#^https?://#i', $w ) ) {
			$w = 'https://' . ltrim( $w, '/' );
		}
		return esc_url_raw( $w );
	}

	private static function clean_yes_no( $raw ) {
		$v = strtolower( trim( (string) $raw ) );
		if ( '' === $v ) return '';
		if ( in_array( $v, [ 'yes', 'y', 'true', '1', 'registered' ], true ) ) return 'yes';
		if ( in_array( $v, [ 'no',  'n', 'false', '0', 'not registered' ], true ) ) return 'no';
		return '';
	}

	private static function resolve_country( $country_raw, $location_raw ) {
		$haystack = strtolower( trim( $country_raw . ' ' . $location_raw ) );
		if ( '' === $haystack ) return '';

		foreach ( self::$country_keywords as $slug => $keywords ) {
			foreach ( $keywords as $kw ) {
				if ( strpos( $haystack, $kw ) !== false ) {
					return $slug;
				}
			}
		}
		// Default: if the row mentions a UK marker, treat as England. Otherwise blank.
		// List intentionally broad — covers free-text locations seen in the
		// historical Google Forms export (Brighton, Aylesbury, West Yorkshire,
		// Nuneaton, Surrey, Hampshire, Exeter, Cornwall, Devon, …).
		$uk_markers = [ 'uk', 'united kingdom', 'england', 'britain', 'great britain', 'gb',
			'london', 'manchester', 'birmingham', 'liverpool', 'leeds', 'sheffield', 'bristol',
			'nottingham', 'coventry', 'leicester', 'bradford', 'newcastle', 'milton keynes',
			'reading', 'luton', 'southampton', 'kent', 'essex', 'hatfield', 'derby',
			'doncaster', 'middlesbrough', 'stoke', 'sunderland', 'bedfordshire', 'northampton',
			'brighton', 'hove', 'aylesbury', 'west yorkshire', 'yorkshire', 'nuneaton',
			'west midlands', 'midlands', 'exeter', 'surrey', 'hampshire', 'stowmarket',
			'cornwall', 'devon', 'lincoln', 'gloucester', 'oxford', 'cambridge', 'norwich',
			'ipswich', 'hertfordshire', 'sussex', 'east sussex', 'west sussex', 'lowestoft',
			'great yarmouth', 'plymouth', 'portsmouth', 'york', 'huddersfield', 'wolverhampton',
			'colchester', 'poole', 'solihull', 'cheltenham', 'gateshead', 'worthing',
			'basildon', 'chelmsford', 'bedford', 'rochdale', 'wigan', 'st helens',
			'eastbourne',
		];
		foreach ( $uk_markers as $m ) {
			if ( strpos( $haystack, $m ) !== false ) {
				return 'england';
			}
		}
		return '';
	}

	private static function split_areas( $areas_raw, $location_raw ) {
		$combined = trim( $areas_raw . ';' . $location_raw, "; \t\n" );
		if ( '' === $combined ) return [];
		// Split on common separators.
		$parts = preg_split( '#\s*(?:[;,/]|\band\b|\b&\b)\s*#i', $combined );
		$out = [];
		foreach ( (array) $parts as $p ) {
			$p = trim( (string) $p );
			if ( '' === $p ) continue;
			// Collapse free-text "nationwide" variations to a canonical token.
			$lower = strtolower( $p );
			if ( in_array( $lower, [ 'nationwide', 'nation wide', 'uk wide', 'ukwide', 'united kingdom', 'uk', 'everywhere', 'everywhere in the uk', 'all over the uk' ], true ) ) {
				$p = 'Nationwide';
			}
			$out[ strtolower( $p ) ] = $p;
		}
		return array_values( $out );
	}

	private static function compose_location_summary( $country, $areas, $location_raw ) {
		$parts = [];
		if ( $areas ) {
			$parts[] = implode( ', ', array_slice( $areas, 0, 6 ) );
		} elseif ( '' !== $location_raw ) {
			$parts[] = $location_raw;
		}
		if ( $country ) {
			$labels = function_exists( 'oc_country_options' ) ? oc_country_options() : [];
			if ( isset( $labels[ $country ] ) ) {
				$parts[] = $labels[ $country ];
			}
		}
		return implode( ' — ', $parts );
	}

	/**
	 * Map a raw category string to an existing taxonomy term, creating
	 * one on the fly when there's no mapping. Tracks newly-created terms
	 * so they surface in the import report.
	 *
	 * @param string $raw            Category text from the CSV.
	 * @param bool   $can_create     False during dry-run.
	 * @param array  &$created_log   Mutated — caller reads the list of new terms.
	 * @return int  Term ID (0 if blank / "others" or we couldn't create one).
	 */
	private static function resolve_category_term( $raw, $can_create, array &$created_log ) {
		$lower = strtolower( trim( $raw ) );
		if ( '' === $lower || in_array( $lower, [ 'others', 'other', 'n/a' ], true ) ) {
			return 0;
		}
		$slug = self::$category_map[ $lower ] ?? '';
		if ( '' === $slug ) {
			// Try a sanitised slug.
			$slug = sanitize_title( $raw );
		}
		$term = get_term_by( 'slug', $slug, OC_TAX );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
		if ( ! $can_create ) {
			return 0;
		}
		$inserted = wp_insert_term( $raw, OC_TAX, [ 'slug' => $slug ] );
		if ( is_wp_error( $inserted ) ) {
			return 0;
		}
		$created_log[] = $raw;
		return (int) $inserted['term_id'];
	}

	// ─────────────────────────────────────────────────────────
	//  Canonical column legend
	// ─────────────────────────────────────────────────────────
	private static function canonical_columns() {
		return [
			'business_name' => [ 'hint' => __( 'The vendor\'s business name — used as listing title.', 'owambe-connect-core' ), 'required' => true ],
			'email'         => [ 'hint' => __( 'Login + public contact email. Must be unique per vendor.', 'owambe-connect-core' ), 'required' => true ],
			'phone'         => [ 'hint' => __( 'WhatsApp / mobile. Auto-normalised to +44XXXXXXXXXX.', 'owambe-connect-core' ), 'required' => false ],
			'category'      => [ 'hint' => __( 'Caterer, Photographer, Decorator, MUA, Cake Vendor, DJ, MC, etc.', 'owambe-connect-core' ), 'required' => false ],
			'location'      => [ 'hint' => __( 'Primary city / town (free text — country is auto-detected from this).', 'owambe-connect-core' ), 'required' => false ],
			'country'       => [ 'hint' => __( 'Explicit UK constituent country (overrides auto-detection from location).', 'owambe-connect-core' ), 'required' => false ],
			'areas'         => [ 'hint' => __( 'Free-text areas covered, comma / "and" / ";" separated.', 'owambe-connect-core' ), 'required' => false ],
			'instagram'     => [ 'hint' => __( 'Handle or full instagram.com URL — stored as the handle only.', 'owambe-connect-core' ), 'required' => false ],
			'facebook'      => [ 'hint' => __( 'Page handle or full URL.', 'owambe-connect-core' ), 'required' => false ],
			'website'       => [ 'hint' => __( 'Public website URL. Missing protocol is added automatically.', 'owambe-connect-core' ), 'required' => false ],
			'description'   => [ 'hint' => __( 'Short bio / business description — used on the public profile.', 'owambe-connect-core' ), 'required' => false ],
			'services'      => [ 'hint' => __( 'List of services offered. Comma / semicolon separated.', 'owambe-connect-core' ), 'required' => false ],
			'registered'    => [ 'hint' => __( 'Yes / No — populates the new "Registered business" field.', 'owambe-connect-core' ), 'required' => false ],
			'first_name'    => [ 'hint' => __( 'Vendor owner first name. Used as a fallback if business_name is blank.', 'owambe-connect-core' ), 'required' => false ],
			'last_name'     => [ 'hint' => __( 'Vendor owner last name. Used as a fallback if business_name is blank.', 'owambe-connect-core' ), 'required' => false ],
			'contact_first' => [ 'hint' => __( 'Display name for the contact person — informational only.', 'owambe-connect-core' ), 'required' => false ],
			'contact_last'  => [ 'hint' => __( 'Display name for the contact person — informational only.', 'owambe-connect-core' ), 'required' => false ],
			'price_range'   => [ 'hint' => __( 'budget / mid / premium / luxury — maps to the dashboard price-range field.', 'owambe-connect-core' ), 'required' => false ],
			'cultural'      => [ 'hint' => __( 'Comma-separated cultural specialties (african, caribbean, south-asian, multicultural, luxury, contemporary).', 'owambe-connect-core' ), 'required' => false ],
			'tags'          => [ 'hint' => __( 'Comma-separated vendor tags — see the dashboard tag list.', 'owambe-connect-core' ), 'required' => false ],
			'preferred'     => [ 'hint' => __( 'Preferred contact method — recorded as a meta but not surfaced on the profile.', 'owambe-connect-core' ), 'required' => false ],
		];
	}

	// ─────────────────────────────────────────────────────────
	//  Template CSV download
	// ─────────────────────────────────────────────────────────
	public function stream_template() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}
		check_admin_referer( self::TEMPLATE_ACTION );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="owambe-connect-vendor-import-template.csv"' );

		$out = fopen( 'php://output', 'w' );
		// UTF-8 BOM so Excel detects encoding.
		fwrite( $out, "\xEF\xBB\xBF" );

		$cols = [
			'Business Name', 'Email Address', 'Phone Number (WhatsApp preferred)',
			'Service Category', 'Location', 'Areas You Cover',
			'Instagram Handle', 'Facebook', 'Website',
			'Short Description', 'Services Offered',
			'Is your Business Officially Registered?', 'Preferred Contact Method',
		];
		fputcsv( $out, $cols );

		// Example row 1 — minimal valid
		fputcsv( $out, [
			'Lagos Luxe Catering', 'hello@lagoslux.example', '+447424111111',
			'Caterer', 'London', 'London, Kent, Essex',
			'@lagoslux', '', 'https://lagoslux.example',
			'Premium Nigerian catering for weddings and corporate events.',
			'Nigerian buffet; Small chops; Drinks service',
			'Yes', 'WhatsApp',
		] );
		// Example row 2 — Scotland / minimal
		fputcsv( $out, [
			'Edinburgh Bridal Glow', 'glow@edinburghbridal.example', '07000123456',
			'Makeup Artist', 'Edinburgh', 'Scotland',
			'edinburghbridal', '', '',
			'Bridal makeup specialist serving Scottish weddings.',
			'Bridal makeup; Trial sessions',
			'No', 'Email',
		] );
		fclose( $out );
		exit;
	}

	// ─────────────────────────────────────────────────────────
	//  Export — stream every vendor as a CSV the importer can re-read
	// ─────────────────────────────────────────────────────────
	public function stream_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}
		check_admin_referer( self::EXPORT_ACTION );

		// Resolve filters.
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
		$include_drafts = ! empty( $_GET['include_drafts'] );

		$statuses = [ OC_STATUS_APPROVED, OC_STATUS_PENDING, OC_STATUS_REJECTED ];
		if ( 'all' !== $status_filter && in_array( $status_filter, $statuses, true ) ) {
			$statuses = [ $status_filter ];
		}

		$query = new WP_Query( [
			'post_type'      => OC_CPT,
			'post_status'    => $statuses,
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		] );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="owambe-connect-vendors-' . gmdate( 'Y-m-d-Hi' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM for Excel

		// Column order is the canonical-template shape + a few informational
		// columns at the end (Vendor Number, Status, …) — those are
		// recognised-but-ignored on re-import, so the file is round-trip safe.
		$headers = [
			'Business Name',
			'Email Address',
			'Phone Number (WhatsApp preferred)',
			'Service Category',
			'Country',
			'Location',
			'Areas You Cover',
			'Instagram Handle',
			'Facebook',
			'Website',
			'Short Description',
			'Services Offered',
			'Is your Business Officially Registered?',
			'Cultural Specialties',
			'Vendor Tags',
			'Price Range',
			'Preferred Contact Method',
			'Vendor Number',
			'Status',
			'Verified',
			'Founding Vendor',
			'Submitted At',
		];
		fputcsv( $out, $headers );

		$country_labels = function_exists( 'oc_country_options' ) ? oc_country_options() : [];

		$status_labels = [
			OC_STATUS_APPROVED => __( 'Approved', 'owambe-connect-core' ),
			OC_STATUS_PENDING  => __( 'Pending',  'owambe-connect-core' ),
			OC_STATUS_REJECTED => __( 'Needs changes', 'owambe-connect-core' ),
		];

		while ( $query->have_posts() ) {
			$query->the_post();
			global $post;

			// Draft vendors (submitted_for_review = 0) are hidden by default.
			$submitted = function_exists( 'oc_is_submitted_for_review' )
				? oc_is_submitted_for_review( $post->ID )
				: true;
			if ( ! $submitted && ! $include_drafts ) {
				continue;
			}

			$user  = get_user_by( 'id', $post->post_author );
			$cats  = wp_get_object_terms( $post->ID, OC_TAX, [ 'fields' => 'names' ] );
			$cats  = is_wp_error( $cats ) ? [] : $cats;

			$country_slug  = (string) get_post_meta( $post->ID, '_oc_location_country', true );
			$country_label = $country_slug && isset( $country_labels[ $country_slug ] )
				? $country_labels[ $country_slug ]
				: $country_slug;
			$areas         = (array) get_post_meta( $post->ID, '_oc_location_areas', true );
			$areas         = array_values( array_filter( array_map( 'trim', $areas ) ) );
			$cultural      = (array) get_post_meta( $post->ID, '_oc_cultural_specialties', true );
			$cultural      = array_values( array_filter( array_map( 'trim', $cultural ) ) );
			$tags          = (array) get_post_meta( $post->ID, '_oc_vendor_tags', true );
			$tags          = array_values( array_filter( array_map( 'trim', $tags ) ) );

			$row = [
				(string) get_post_meta( $post->ID, '_oc_business_name', true ) ?: $post->post_title,
				$user ? $user->user_email : '',
				(string) get_post_meta( $post->ID, '_oc_whatsapp', true ),
				$cats ? implode( '; ', $cats ) : '',
				$country_label,
				(string) get_post_meta( $post->ID, '_oc_location', true ),
				implode( '; ', $areas ),
				(string) get_post_meta( $post->ID, '_oc_instagram', true ),
				(string) get_post_meta( $post->ID, '_oc_facebook', true ),
				(string) get_post_meta( $post->ID, '_oc_website', true ),
				(string) get_post_meta( $post->ID, '_oc_bio', true ),
				(string) get_post_meta( $post->ID, '_oc_services', true ),
				(string) get_post_meta( $post->ID, '_oc_registered_business', true ),
				implode( '; ', $cultural ),
				implode( '; ', $tags ),
				(string) get_post_meta( $post->ID, '_oc_price_range', true ),
				'', // Preferred Contact Method — not stored on the vendor post; left blank.
				(string) get_post_meta( $post->ID, '_oc_vendor_number', true ),
				$status_labels[ $post->post_status ] ?? $post->post_status,
				(int) get_post_meta( $post->ID, '_oc_verified', true ) === 1 ? 'Yes' : 'No',
				(int) get_post_meta( $post->ID, '_oc_founding_vendor', true ) === 1 ? 'Yes' : 'No',
				get_post_time( 'Y-m-d H:i', false, $post ),
			];
			fputcsv( $out, $row );
		}
		wp_reset_postdata();

		fclose( $out );
		exit;
	}
}
