<?php
/**
 * Public RSVP form processing for the oc_event CPT.
 *
 * Handles the front-end RSVP submission posted to admin-post.php: verifies the
 * nonce + honeypot, stores the sanitised response in {prefix}oc_rsvps, and
 * notifies the event host (with Reply-To set to the guest so a reply lands in
 * the guest's inbox). Never trusts POST data — everything is sanitised and the
 * event must be a published oc_event.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Event_RSVP {

	/** admin-post action + nonce action. */
	const ACTION = 'oc_submit_rsvp';

	/** Nonce field name rendered in the form. */
	const NONCE_FIELD = 'oc_rsvp_nonce';

	/** admin-post action for the host-only guest-list CSV export. */
	const EXPORT_ACTION = 'oc_rsvp_export';

	public function register() {
		add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'handle' ] );
		add_action( 'admin_post_'        . self::ACTION, [ $this, 'handle' ] );

		// Guest-list CSV export — logged-in only (no nopriv); the handler gates
		// on event ownership. Also inject a download button into the event editor.
		add_action( 'admin_post_' . self::EXPORT_ACTION, [ $this, 'export' ] );
		add_action( 'oc_event_editor_sections', [ $this, 'render_export_section' ], 30 );
	}

	/**
	 * True when the current user may see/export this event's guest list:
	 * the event author, or any admin.
	 */
	private static function user_owns_event( $event ) {
		if ( ! ( $event instanceof WP_Post ) || 'oc_event' !== $event->post_type ) {
			return false;
		}
		return (int) $event->post_author === get_current_user_id() || current_user_can( 'manage_options' );
	}

	/** Nonced admin-post URL that streams this event's guest list as CSV. */
	public static function export_url( $event_id ) {
		$event_id = (int) $event_id;
		return wp_nonce_url(
			add_query_arg(
				[
					'action'   => self::EXPORT_ACTION,
					'event_id' => $event_id,
				],
				admin_url( 'admin-post.php' )
			),
			self::EXPORT_ACTION . '_' . $event_id
		);
	}

	public function handle() {
		global $wpdb;

		// 1) Nonce — reject forged/stale submissions.
		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			$this->redirect_back( 0, 'error' );
		}

		// 2) Honeypot — a real user never fills the hidden oc_hp field. Bail
		//    quietly as if it succeeded so bots get no signal.
		if ( ! empty( $_POST['oc_hp'] ) ) {
			$this->redirect_back( absint( $_POST['event_id'] ?? 0 ), 'ok' );
		}

		// 3) Sanitise input.
		$event_id    = isset( $_POST['event_id'] )    ? absint( $_POST['event_id'] )                                        : 0;
		$guest_name  = isset( $_POST['guest_name'] )  ? sanitize_text_field( wp_unslash( $_POST['guest_name'] ) )           : '';
		$guest_email = isset( $_POST['guest_email'] ) ? sanitize_email( wp_unslash( $_POST['guest_email'] ) )              : '';
		$attending   = isset( $_POST['attending'] )   ? sanitize_key( wp_unslash( $_POST['attending'] ) )                   : '';
		$guest_count = isset( $_POST['guest_count'] ) ? absint( $_POST['guest_count'] )                                     : 1;
		$note        = isset( $_POST['note'] )        ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) )             : '';

		// 4) Validate. The event must be a published oc_event; a name is required;
		//    a supplied email must be well-formed; attending is a short enum.
		$event = $event_id ? get_post( $event_id ) : null;
		if ( ! $event || 'oc_event' !== $event->post_type || 'publish' !== $event->post_status ) {
			$this->redirect_back( 0, 'error' );
		}
		if ( '' === $guest_name ) {
			$this->redirect_back( $event_id, 'error' );
		}
		if ( '' !== $guest_email && ! is_email( $guest_email ) ) {
			$this->redirect_back( $event_id, 'error' );
		}
		if ( ! in_array( $attending, [ 'yes', 'no', 'maybe' ], true ) ) {
			$attending = 'yes';
		}
		$guest_count = max( 1, $guest_count );

		// 5) Store. `created` is set explicitly to the site-local submission time
		//    rather than relying on the column default — some older installs have
		//    an oc_rsvps table whose `created` column lost its CURRENT_TIMESTAMP
		//    default and would otherwise store 0000-00-00. %s/%d formats keep the
		//    row strictly typed.
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'oc_rsvps',
			[
				'event_id'    => $event_id,
				'guest_name'  => $guest_name,
				'guest_email' => $guest_email,
				'attending'   => $attending,
				'guest_count' => $guest_count,
				'note'        => $note,
				'created'     => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s', '%d', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			$this->redirect_back( $event_id, 'error' );
		}

		// 6) Notify the host. Reply-To = guest email (when given) so the host can
		//    reply straight to the guest.
		$this->notify_host( $event, compact( 'guest_name', 'guest_email', 'attending', 'guest_count', 'note' ) );

		$this->redirect_back( $event_id, 'ok' );
	}

	/**
	 * Email the event host about a new RSVP.
	 *
	 * @param WP_Post $event The oc_event post.
	 * @param array   $rsvp  Sanitised RSVP fields.
	 */
	private function notify_host( $event, array $rsvp ) {
		if ( ! class_exists( 'OC_Mail' ) ) {
			return;
		}

		// Host = event author, overridable. Fall back to the site admin.
		$host    = get_userdata( (int) $event->post_author );
		$to      = $host && is_email( $host->user_email ) ? $host->user_email : get_option( 'admin_email' );
		$to      = apply_filters( 'oc_rsvp_host_email', $to, $event, $rsvp );
		if ( ! is_email( $to ) ) {
			return;
		}

		$labels  = [ 'yes' => __( 'Attending', 'owambe-connect-core' ), 'no' => __( 'Not attending', 'owambe-connect-core' ), 'maybe' => __( 'Maybe', 'owambe-connect-core' ) ];
		$status  = isset( $labels[ $rsvp['attending'] ] ) ? $labels[ $rsvp['attending'] ] : $rsvp['attending'];

		$subject = sprintf(
			/* translators: 1: guest name, 2: event title */
			__( 'New RSVP from %1$s — %2$s', 'owambe-connect-core' ),
			$rsvp['guest_name'],
			$event->post_title
		);

		// Escape every guest-supplied value — this is HTML email content.
		$rows  = '<p><strong>' . esc_html__( 'Event:', 'owambe-connect-core' ) . '</strong> ' . esc_html( $event->post_title ) . '</p>';
		$rows .= '<p><strong>' . esc_html__( 'Guest:', 'owambe-connect-core' ) . '</strong> ' . esc_html( $rsvp['guest_name'] ) . '</p>';
		if ( '' !== $rsvp['guest_email'] ) {
			$rows .= '<p><strong>' . esc_html__( 'Email:', 'owambe-connect-core' ) . '</strong> ' . esc_html( $rsvp['guest_email'] ) . '</p>';
		}
		$rows .= '<p><strong>' . esc_html__( 'Response:', 'owambe-connect-core' ) . '</strong> ' . esc_html( $status ) . '</p>';
		$rows .= '<p><strong>' . esc_html__( 'Guests:', 'owambe-connect-core' ) . '</strong> ' . (int) $rsvp['guest_count'] . '</p>';
		if ( '' !== $rsvp['note'] ) {
			$rows .= '<p><strong>' . esc_html__( 'Note:', 'owambe-connect-core' ) . '</strong><br>' . nl2br( esc_html( $rsvp['note'] ) ) . '</p>';
		}
		$body = '<h2>' . esc_html__( 'New RSVP', 'owambe-connect-core' ) . '</h2>' . $rows;

		// Reply-To = guest email when provided.
		$headers = is_email( $rsvp['guest_email'] ) ? [ 'Reply-To: ' . $rsvp['guest_email'] ] : [];

		OC_Mail::send( $to, $subject, $body, $headers );
	}

	/**
	 * Redirect back to the event page (or referer/home) with a status flag.
	 * Always exits.
	 *
	 * @param int    $event_id Event to return to, or 0.
	 * @param string $status   'ok' | 'error'.
	 */
	private function redirect_back( $event_id, $status ) {
		$base = $event_id ? get_permalink( $event_id ) : '';
		if ( ! $base ) {
			$base = wp_get_referer() ?: home_url( '/' );
		}
		wp_safe_redirect( add_query_arg( 'oc_rsvp', $status, $base ) );
		exit;
	}

	// ─── Guest-list CSV export ────────────────────────────────────────────────

	/**
	 * Neutralise spreadsheet formula injection: a cell that a guest could make
	 * begin with =, +, -, or @ is prefixed with an apostrophe so Excel/Sheets
	 * treats it as text, not a formula. Applied to guest-supplied fields only.
	 */
	private static function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], [ '=', '+', '-', '@' ], true ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	/**
	 * Stream this event's RSVPs as a UTF-8 CSV. Clones the admin importer's
	 * stream_export() approach — BOM for Excel + streamed fputcsv — but is gated
	 * on event ownership rather than manage_options, so a client can download the
	 * guest list for their own event.
	 */
	public function export() {
		if ( ! is_user_logged_in() ) {
			wp_die( -1, 403 );
		}
		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
		check_admin_referer( self::EXPORT_ACTION . '_' . $event_id );

		$event = $event_id ? get_post( $event_id ) : null;
		if ( ! $event || 'oc_event' !== $event->post_type ) {
			wp_die( esc_html__( 'Event not found.', 'owambe-connect-core' ), 404 );
		}
		if ( ! self::user_owns_event( $event ) ) {
			wp_die( esc_html__( 'You are not allowed to export this guest list.', 'owambe-connect-core' ), 403 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'oc_rsvps';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT guest_name, guest_email, attending, guest_count, note, created
				 FROM {$table} WHERE event_id = %d ORDER BY created ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$event_id
			),
			ARRAY_A
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		$slug = sanitize_title( $event->post_title );
		$slug = '' !== $slug ? $slug : 'event';
		header( 'Content-Disposition: attachment; filename="rsvps-' . $slug . '-' . gmdate( 'Y-m-d-Hi' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM so Excel detects UTF-8.

		fputcsv( $out, [
			__( 'Guest Name', 'owambe-connect-core' ),
			__( 'Email', 'owambe-connect-core' ),
			__( 'Response', 'owambe-connect-core' ),
			__( 'Party Size', 'owambe-connect-core' ),
			__( 'Note', 'owambe-connect-core' ),
			__( 'Submitted At', 'owambe-connect-core' ),
		] );

		// Map the stored response to a readable word. Includes the legacy
		// tinyint(1) values (1/0) from pre-migration tables so historic rows read
		// sensibly rather than showing a bare number.
		$labels = [
			'yes'   => __( 'Yes', 'owambe-connect-core' ),
			'no'    => __( 'No', 'owambe-connect-core' ),
			'maybe' => __( 'Maybe', 'owambe-connect-core' ),
			'1'     => __( 'Yes', 'owambe-connect-core' ),
			'0'     => __( 'No', 'owambe-connect-core' ),
		];

		foreach ( (array) $rows as $r ) {
			$key       = strtolower( trim( (string) $r['attending'] ) );
			$attending = isset( $labels[ $key ] ) ? $labels[ $key ] : ( '' === $key ? __( 'No response', 'owambe-connect-core' ) : (string) $r['attending'] );

			// A zero/empty datetime (from a table that lost its default) is noise
			// in the export — show it blank rather than 0000-00-00 00:00:00.
			$created = (string) $r['created'];
			if ( '' === $created || 0 === strpos( $created, '0000-00-00' ) ) {
				$created = '';
			}

			fputcsv( $out, [
				self::csv_cell( $r['guest_name'] ),
				self::csv_cell( $r['guest_email'] ),
				$attending,
				(int) $r['guest_count'],
				self::csv_cell( $r['note'] ),
				$created,
			] );
		}

		fclose( $out );
		exit;
	}

	/**
	 * Render a "Guest list" card inside the event editor with an RSVP count and
	 * a CSV download button. Only shown for a saved event the current user owns.
	 *
	 * @param WP_Post|null $event
	 */
	public function render_export_section( $event ) {
		if ( ! self::user_owns_event( $event ) ) {
			return;
		}
		$event_id = (int) $event->ID;
		if ( ! $event_id ) {
			return; // nothing to export until the event is first saved.
		}
		global $wpdb;
		$table = $wpdb->prefix . 'oc_rsvps';
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event_id = %d", $event_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$dl_icon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M6 11l6 6 6-6M5 21h14"/></svg>';
		?>
		<div class="oc-eved__section oc-guests">
			<h2 class="oc-eved__section-title"><?php esc_html_e( 'Guest list', 'owambe-connect-core' ); ?></h2>
			<div class="oc-guests__card">
				<div class="oc-guests__stat">
					<span class="oc-guests__ico" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
					</span>
					<span class="oc-guests__figures">
						<strong class="oc-guests__num"><?php echo (int) $count; ?></strong>
						<span class="oc-guests__lbl"><?php echo esc_html( _n( 'RSVP received', 'RSVPs received', $count, 'owambe-connect-core' ) ); ?></span>
					</span>
				</div>
				<?php if ( $count > 0 ) : ?>
					<a class="oc-guests__btn" href="<?php echo esc_url( self::export_url( $event_id ) ); ?>">
						<?php echo $dl_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?>
						<?php esc_html_e( 'Download CSV', 'owambe-connect-core' ); ?>
					</a>
				<?php else : ?>
					<span class="oc-guests__btn oc-guests__btn--off" aria-disabled="true">
						<?php echo $dl_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?>
						<?php esc_html_e( 'Download CSV', 'owambe-connect-core' ); ?>
					</span>
				<?php endif; ?>
			</div>
			<p class="oc-guests__hint">
				<?php
				if ( $count > 0 ) {
					esc_html_e( 'Everyone who has responded — names, emails, responses, party sizes and notes — in a spreadsheet-ready file for Excel or Google Sheets.', 'owambe-connect-core' );
				} else {
					esc_html_e( 'As soon as your first guest responds, you can download the full list here.', 'owambe-connect-core' );
				}
				?>
			</p>
		</div>
		<style>
			.oc-guests__card{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:18px 20px;background:#FAF8F5;border:1px solid var(--oc-border,#EAE2DA);border-radius:14px;}
			.oc-guests__stat{display:flex;align-items:center;gap:14px;}
			.oc-guests__ico{flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:13px;background:linear-gradient(135deg,#FBEEF1,#F3DFE5);color:#6E0F2C;}
			.oc-guests__figures{display:flex;flex-direction:column;line-height:1.15;}
			.oc-guests__num{font-size:23px;font-weight:800;color:#3A1220;font-variant-numeric:tabular-nums;}
			.oc-guests__lbl{margin-top:3px;font-size:11.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#8A7F82;}
			.oc-guests__btn{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border-radius:10px;background:#600E26;color:#fff;font-size:14px;font-weight:600;text-decoration:none;box-shadow:0 4px 12px rgba(96,14,38,.18);transition:background .15s ease,box-shadow .15s ease,transform .1s ease;}
			.oc-guests__btn:hover{background:#7a1230;color:#fff;box-shadow:0 7px 18px rgba(96,14,38,.26);transform:translateY(-1px);}
			.oc-guests__btn--off{background:#EFE7E9;color:#A99BA0;box-shadow:none;cursor:default;pointer-events:none;}
			.oc-guests__hint{margin:12px 2px 0;color:#6B6361;font-size:13.5px;line-height:1.55;padding-top:15px;}
		</style>
		<?php
	}
}
