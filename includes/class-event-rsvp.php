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

	public function register() {
		add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'handle' ] );
		add_action( 'admin_post_'        . self::ACTION, [ $this, 'handle' ] );
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

		// 5) Store. `created` is omitted so the column's CURRENT_TIMESTAMP default
		//    fills it. %s/%d formats keep the row strictly typed.
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'oc_rsvps',
			[
				'event_id'    => $event_id,
				'guest_name'  => $guest_name,
				'guest_email' => $guest_email,
				'attending'   => $attending,
				'guest_count' => $guest_count,
				'note'        => $note,
			],
			[ '%d', '%s', '%s', '%s', '%d', '%s' ]
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
}
