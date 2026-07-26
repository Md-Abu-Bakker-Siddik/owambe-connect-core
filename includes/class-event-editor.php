<?php
/**
 * Event editor — the client-facing form for an oc_event page.
 *
 * [oc_event_editor] renders a sectioned form (schema from oc_event_fields()).
 * Posting to admin-post.php (oc_save_event) creates the client's single event
 * on first save (unlisted, published so the share link works) and updates it
 * thereafter. One event per client to start (oc_get_current_event_post()).
 * Follows the Phase 2 public-form standard: nonce + honeypot, ownership check,
 * redirect back with a notice, never wiping typed input (values persist in
 * meta and reload into the form).
 *
 * Image/PDF upload sections (cover, gallery, invitation) are added in the next
 * step (P2) on top of this scalar-field editor.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Event_Editor {

	const ACTION      = 'oc_save_event';
	const NONCE_FIELD = 'oc_event_nonce';

	public function register() {
		add_shortcode( 'oc_event_editor', [ $this, 'shortcode' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_save' ] );
	}

	/** Meta key for an editor field key (e.g. 'date' → '_oc_event_date'). */
	public static function meta_key( $field ) {
		return '_oc_event_' . sanitize_key( $field );
	}

	/** Flatten oc_event_fields() sections into field_key => spec. */
	private static function flat_fields() {
		$flat = [];
		foreach ( oc_event_fields() as $section ) {
			if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
				foreach ( $section['fields'] as $key => $spec ) {
					$flat[ $key ] = $spec;
				}
			}
		}
		return $flat;
	}

	// ─── Front end ──────────────────────────────────────────────────────────

	public function shortcode( $atts = [] ) {
		if ( ! is_user_logged_in() ) {
			$login = function_exists( 'oc_page_url' ) ? oc_page_url( 'client-login' ) : wp_login_url();
			$login = add_query_arg( 'redirect_to', rawurlencode( self::current_url() ), $login );
			return '<div class="oc-notice">'
				. esc_html__( 'Please sign in to create your event page.', 'owambe-connect-core' )
				. ' <a href="' . esc_url( $login ) . '">' . esc_html__( 'Sign in', 'owambe-connect-core' ) . '</a></div>';
		}

		return oc_get_template( 'shortcode-event-editor.php', [
			'event' => oc_get_current_event_post(),
		] );
	}

	// ─── Save ───────────────────────────────────────────────────────────────

	public function handle_save() {
		if ( ! is_user_logged_in() ) {
			$this->bail( __( 'Please sign in to save your event.', 'owambe-connect-core' ) );
		}
		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			$this->bail( __( 'Your session expired. Please try again.', 'owambe-connect-core' ) );
		}
		// Honeypot — real users never fill this.
		if ( ! empty( $_POST['oc_hp'] ) ) {
			$this->finish( 'saved' ); // pretend success; bots get no signal.
		}

		$user_id = get_current_user_id();
		$event   = oc_get_current_event_post();

		$title = isset( $_POST['event_title'] ) ? sanitize_text_field( wp_unslash( $_POST['event_title'] ) ) : '';
		if ( '' === $title ) {
			$title = __( 'My Event', 'owambe-connect-core' );
		}

		if ( ! $event ) {
			// Create the client's single event, published (unlisted CPT) so the
			// share link works immediately; the slug token is baked in on publish.
			$new_id = wp_insert_post( [
				'post_type'   => 'oc_event',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => $user_id,
			], true );
			if ( is_wp_error( $new_id ) ) {
				$this->bail( __( 'Could not create your event. Please try again.', 'owambe-connect-core' ) );
			}
			$event = get_post( $new_id );
		} else {
			// Ownership guard.
			if ( (int) $event->post_author !== (int) $user_id && ! current_user_can( 'manage_options' ) ) {
				$this->bail( __( 'You cannot edit this event.', 'owambe-connect-core' ) );
			}
			wp_update_post( [ 'ID' => $event->ID, 'post_title' => $title ] );
		}

		// Store each scalar field, sanitised by its declared type.
		foreach ( self::flat_fields() as $key => $spec ) {
			$raw   = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			$clean = self::sanitize_field( $raw, isset( $spec['type'] ) ? $spec['type'] : 'text' );
			update_post_meta( $event->ID, self::meta_key( $key ), $clean );
		}

		/**
		 * Fires after a client saves their event.
		 *
		 * @param int $event_id
		 */
		do_action( 'oc_event_saved', $event->ID );

		$this->finish( 'saved' );
	}

	/** Type-aware sanitiser for one field value. */
	private static function sanitize_field( $value, $type ) {
		switch ( $type ) {
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'url':
				return esc_url_raw( trim( (string) $value ) );
			case 'date':
			case 'time':
			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	// ─── Helpers ────────────────────────────────────────────────────────────

	/** Where the editor lives (the my-event page), for redirects. */
	private static function editor_url() {
		$url = function_exists( 'oc_page_url' ) ? oc_page_url( 'my-event' ) : home_url( '/' );
		return $url ?: home_url( '/' );
	}

	private static function current_url() {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri;
	}

	private function finish( $notice ) {
		wp_safe_redirect( add_query_arg( 'oc_notice', $notice, self::editor_url() ) );
		exit;
	}

	private function bail( $message ) {
		wp_safe_redirect( add_query_arg( 'oc_error', rawurlencode( $message ), self::editor_url() ) );
		exit;
	}
}
