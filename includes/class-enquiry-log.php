<?php
/**
 * Persistent log of every public-form enquiry (Request a Vendor + Contact
 * form). Every submission is written to wp_options BEFORE the mail send
 * is attempted, so even when SMTP silently rejects a message (e.g. the
 * From address isn't on the allowed senders list at Mailgun) the enquiry
 * itself is never lost — admin can read it from the dashboard.
 *
 * Also listens for wp_mail_failed so silent delivery failures surface in
 * the admin instead of disappearing.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Enquiry_Log {

	const OPTION_KEY       = 'oc_enquiry_log';
	const MAIL_ERRORS_KEY  = 'oc_recent_mail_errors';
	const MAX_ENTRIES      = 200;
	const MAX_MAIL_ERRORS  = 50;
	const MENU_SLUG        = 'oc-enquiries';
	const SEEN_OPTION      = 'oc_enquiry_log_last_seen';

	public function register() {
		add_action( 'admin_menu',     [ $this, 'admin_menu' ] );
		add_action( 'admin_notices',  [ $this, 'maybe_notice' ] );
		add_action( 'wp_mail_failed', [ $this, 'on_mail_failed' ] );
	}

	/**
	 * Public API — called from the submit handlers BEFORE wp_mail. Returns
	 * the ID of the new entry so the caller can update its mail-status flag
	 * once the send attempt is done.
	 */
	public static function record( $type, array $data, $mail_target = '' ) {
		$entry = [
			'id'      => uniqid( '', true ),
			'type'    => sanitize_key( $type ),                              // 'vendor_request' | 'contact_message'
			'time'    => current_time( 'mysql' ),
			'time_ts' => time(),
			'ip'      => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '',
			'mail_to' => (string) $mail_target,
			'mail_ok' => null,                                               // null until we know
			'data'    => array_map( 'wp_kses_post', $data ),                 // keep raw values for admin to read
		];
		$log = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $log ) ) $log = [];
		array_unshift( $log, $entry );
		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_ENTRIES );
		}
		update_option( self::OPTION_KEY, $log, false );
		return $entry['id'];
	}

	/** Update the mail_ok flag once we know whether wp_mail returned true. */
	public static function update_status( $entry_id, $ok ) {
		$log = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $log ) ) return;
		foreach ( $log as &$e ) {
			if ( isset( $e['id'] ) && $e['id'] === $entry_id ) {
				$e['mail_ok'] = (bool) $ok;
				break;
			}
		}
		unset( $e );
		update_option( self::OPTION_KEY, $log, false );
	}

	/** Listen for wp_mail failures and stash the error message. */
	public function on_mail_failed( $error ) {
		$msg  = $error instanceof WP_Error ? $error->get_error_message() : (string) $error;
		$data = $error instanceof WP_Error ? (array) $error->get_error_data() : [];
		$row  = [
			'time'    => current_time( 'mysql' ),
			'message' => $msg,
			'to'      => isset( $data['to'] ) ? ( is_array( $data['to'] ) ? implode( ', ', $data['to'] ) : (string) $data['to'] ) : '',
			'subject' => isset( $data['subject'] ) ? (string) $data['subject'] : '',
		];
		$errors = get_option( self::MAIL_ERRORS_KEY, [] );
		if ( ! is_array( $errors ) ) $errors = [];
		array_unshift( $errors, $row );
		if ( count( $errors ) > self::MAX_MAIL_ERRORS ) {
			$errors = array_slice( $errors, 0, self::MAX_MAIL_ERRORS );
		}
		update_option( self::MAIL_ERRORS_KEY, $errors, false );
	}

	public function admin_menu() {
		// Build a friendly menu title: dashicon + label + optional unread count
		// (rendered as the standard WordPress "awaiting moderation" yellow pill).
		$unread = $this->unread_count();
		$icon   = '<span class="dashicons dashicons-email-alt2" style="font-size:17px;width:17px;height:17px;vertical-align:text-bottom;margin-right:6px;"></span>';
		$label  = __( 'Enquiries', 'owambe-connect-core' );
		$badge  = $unread > 0 ? ' <span class="awaiting-mod">' . (int) $unread . '</span>' : '';
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Enquiries', 'owambe-connect-core' ),
			$icon . $label . $badge,
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_page' ]
		);
	}

	private function unread_count() {
		$log = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $log ) || empty( $log ) ) return 0;
		$last_seen = (int) get_option( self::SEEN_OPTION, 0 );
		$n = 0;
		foreach ( $log as $e ) {
			if ( (int) ( $e['time_ts'] ?? 0 ) > $last_seen ) $n++;
		}
		return $n;
	}

	/** Show admin notice on every wp-admin screen when there are unread enquiries. */
	public function maybe_notice() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$unread = $this->unread_count();
		if ( $unread <= 0 ) return;
		$url = admin_url( 'edit.php?post_type=' . OC_CPT . '&page=' . self::MENU_SLUG );
		printf(
			'<div class="notice notice-info is-dismissible"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Owambe Connect:', 'owambe-connect-core' ),
			esc_html( sprintf( _n( '%d new enquiry waiting for you.', '%d new enquiries waiting for you.', $unread, 'owambe-connect-core' ), $unread ) ),
			esc_url( $url ),
			esc_html__( 'View enquiries', 'owambe-connect-core' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Unauthorised.', 'owambe-connect-core' ) );

		// Mark all as read on view.
		update_option( self::SEEN_OPTION, time(), false );

		$log    = get_option( self::OPTION_KEY, [] );
		$errors = get_option( self::MAIL_ERRORS_KEY, [] );
		if ( ! is_array( $log ) )    $log = [];
		if ( ! is_array( $errors ) ) $errors = [];

		// ── Tabs ────────────────────────────────────────────────
		$active_tab    = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : 'all';
		$count_all     = count( $log );
		$count_vrq     = 0;
		$count_contact = 0;
		foreach ( $log as $e ) {
			$t = $e['type'] ?? '';
			if ( $t === 'vendor_request' )  $count_vrq++;
			if ( $t === 'contact_message' ) $count_contact++;
		}
		if ( $active_tab === 'vendor_request' ) {
			$log = array_values( array_filter( $log, function ( $e ) { return ( $e['type'] ?? '' ) === 'vendor_request'; } ) );
		} elseif ( $active_tab === 'contact_message' ) {
			$log = array_values( array_filter( $log, function ( $e ) { return ( $e['type'] ?? '' ) === 'contact_message'; } ) );
		}
		$base_url = admin_url( 'edit.php?post_type=' . OC_CPT . '&page=' . self::MENU_SLUG );
		?>
		<div class="wrap">
			<h1 style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
				<?php esc_html_e( 'Enquiries', 'owambe-connect-core' ); ?>
			</h1>
			<p style="color:#6B6361;max-width:780px;margin:6px 0 16px;">
				<?php esc_html_e( 'Every public-form submission ("Request a Vendor" floating button + the Contact page) is recorded here the moment it comes in — independent of email delivery. Use this list as your source of truth if mail is delayed or silently dropped by your SMTP provider.', 'owambe-connect-core' ); ?>
			</p>

			<h2 class="nav-tab-wrapper" style="margin-bottom:16px;">
				<a href="<?php echo esc_url( $base_url ); ?>" class="nav-tab<?php echo $active_tab === 'all' ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'All', 'owambe-connect-core' ); ?> <span class="oc-tab-count" style="background:rgba(110,15,44,.12);color:#6E0F2C;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:600;margin-left:4px;"><?php echo (int) $count_all; ?></span>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'type', 'vendor_request', $base_url ) ); ?>" class="nav-tab<?php echo $active_tab === 'vendor_request' ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Vendor requests', 'owambe-connect-core' ); ?> <span class="oc-tab-count" style="background:rgba(110,15,44,.12);color:#6E0F2C;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:600;margin-left:4px;"><?php echo (int) $count_vrq; ?></span>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'type', 'contact_message', $base_url ) ); ?>" class="nav-tab<?php echo $active_tab === 'contact_message' ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Contact messages', 'owambe-connect-core' ); ?> <span class="oc-tab-count" style="background:rgba(110,15,44,.12);color:#6E0F2C;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:600;margin-left:4px;"><?php echo (int) $count_contact; ?></span>
				</a>
			</h2>

			<?php if ( ! empty( $errors ) ) : ?>
				<div class="notice notice-error" style="margin:18px 0;">
					<p><strong><?php esc_html_e( 'Recent mail-delivery failures', 'owambe-connect-core' ); ?></strong> — <?php esc_html_e( 'wp_mail reported these errors. Check your SMTP plugin (FluentSMTP / Mailgun) — the "From" address may not be on the allowed senders list.', 'owambe-connect-core' ); ?></p>
					<table class="widefat striped" style="margin-top:6px;">
						<thead><tr><th><?php esc_html_e( 'When', 'owambe-connect-core' ); ?></th><th><?php esc_html_e( 'Subject', 'owambe-connect-core' ); ?></th><th><?php esc_html_e( 'To', 'owambe-connect-core' ); ?></th><th><?php esc_html_e( 'Error', 'owambe-connect-core' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( array_slice( $errors, 0, 10 ) as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['time'] ?? '' ); ?></td>
								<td><?php echo esc_html( $row['subject'] ?? '' ); ?></td>
								<td><?php echo esc_html( $row['to'] ?? '' ); ?></td>
								<td style="color:#B0354F;"><?php echo esc_html( $row['message'] ?? '' ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( empty( $log ) ) : ?>
				<p style="background:#fff;padding:24px;border:1px solid #ccd0d4;border-radius:6px;color:#6B6361;">
					<?php esc_html_e( 'No enquiries yet. The list will populate as soon as someone submits the Request-a-Vendor or Contact form.', 'owambe-connect-core' ); ?>
				</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width:140px;"><?php esc_html_e( 'When', 'owambe-connect-core' ); ?></th>
							<th style="width:120px;"><?php esc_html_e( 'Type', 'owambe-connect-core' ); ?></th>
							<th><?php esc_html_e( 'From', 'owambe-connect-core' ); ?></th>
							<th><?php esc_html_e( 'Details', 'owambe-connect-core' ); ?></th>
							<th style="width:80px;"><?php esc_html_e( 'Email sent', 'owambe-connect-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $log as $e ) :
						$data = is_array( $e['data'] ?? null ) ? $e['data'] : [];
						$name  = (string) ( $data['name']  ?? '' );
						$mail  = (string) ( $data['email'] ?? '' );
						$phone = (string) ( $data['phone'] ?? '' );
						$type_label = ( $e['type'] === 'vendor_request' ) ? __( 'Vendor request', 'owambe-connect-core' ) : __( 'Contact form', 'owambe-connect-core' );
						$ok = $e['mail_ok'] ?? null;
						$badge_bg = '#6B6361'; $badge_text = __( 'unknown', 'owambe-connect-core' );
						if ( $ok === true )  { $badge_bg = '#2E7D5B'; $badge_text = __( 'sent',   'owambe-connect-core' ); }
						if ( $ok === false ) { $badge_bg = '#B0354F'; $badge_text = __( 'failed', 'owambe-connect-core' ); }
					?>
						<tr>
							<td>
								<?php echo esc_html( $e['time'] ?? '' ); ?>
								<?php if ( ! empty( $e['ip'] ) ) : ?>
									<div style="color:#6B6361;font-size:11px;"><?php echo esc_html( $e['ip'] ); ?></div>
								<?php endif; ?>
							</td>
							<td><strong><?php echo esc_html( $type_label ); ?></strong></td>
							<td>
								<?php echo esc_html( $name ?: '—' ); ?>
								<?php if ( $mail ) : ?>
									<div><a href="mailto:<?php echo esc_attr( $mail ); ?>"><?php echo esc_html( $mail ); ?></a></div>
								<?php endif; ?>
								<?php if ( $phone ) : ?>
									<div style="color:#6B6361;"><?php echo esc_html( $phone ); ?></div>
								<?php endif; ?>
							</td>
							<td>
								<?php
								$fields = [
									__( 'Event date', 'owambe-connect-core' ) => $data['event_date'] ?? '',
									__( 'Event type', 'owambe-connect-core' ) => $data['event_type'] ?? '',
									__( 'Location',   'owambe-connect-core' ) => $data['location']   ?? '',
									__( 'Budget',     'owambe-connect-core' ) => $data['budget']     ?? '',
								];
								$bits = [];
								foreach ( $fields as $label => $v ) {
									if ( $v !== '' && $v !== null ) {
										$bits[] = '<span style="color:#6B6361;">' . esc_html( $label ) . ':</span> ' . esc_html( $v );
									}
								}
								if ( $bits ) {
									echo '<div style="margin-bottom:6px;">' . implode( ' &nbsp;·&nbsp; ', $bits ) . '</div>';
								}
								$desc = (string) ( $data['description'] ?? $data['message'] ?? '' );
								if ( $desc !== '' ) {
									echo '<div style="white-space:pre-wrap;color:#1F1B1A;line-height:1.5;">' . esc_html( $desc ) . '</div>';
								}
								?>
							</td>
							<td>
								<span style="display:inline-block;padding:3px 10px;border-radius:999px;background:<?php echo esc_attr( $badge_bg ); ?>;color:#fff;font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;">
									<?php echo esc_html( $badge_text ); ?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
