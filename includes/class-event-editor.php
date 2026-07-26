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

	const ACTION       = 'oc_save_event';
	const NONCE_FIELD  = 'oc_event_nonce';
	const ACTION_UPLOAD = 'oc_event_upload';

	/** Attachment meta keys. */
	const META_COVER      = '_oc_event_cover_id';
	const META_GALLERY    = '_oc_event_gallery_ids';
	const META_INVITATION = '_oc_event_invitation_id';

	public function register() {
		add_shortcode( 'oc_event_editor', [ $this, 'shortcode' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_save' ] );
		add_action( 'wp_ajax_' . self::ACTION_UPLOAD, [ $this, 'ajax_upload' ] );

		// The editor renders its own hero H1, so drop the theme's page title on
		// /my-event/ (avoids a bare, duplicate heading).
		add_filter( 'oc_suppress_page_title_slugs', [ $this, 'suppress_page_title' ] );
	}

	/** Add 'my-event' to the list of pages whose theme title is suppressed. */
	public function suppress_page_title( $slugs ) {
		$slugs[] = 'my-event';
		return $slugs;
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

		// Commit uploaded attachments. TAMPER-GUARD: only accept ids whose
		// post_parent is THIS event, so a posted id can't reference someone
		// else's (or an unrelated) attachment. ids were stamped with this parent
		// by ajax_upload().
		$cover = $this->own_attachment( isset( $_POST['cover_id'] ) ? (int) $_POST['cover_id'] : 0, $event->ID );
		$cover ? update_post_meta( $event->ID, self::META_COVER, $cover ) : delete_post_meta( $event->ID, self::META_COVER );

		$inv = $this->own_attachment( isset( $_POST['invitation_id'] ) ? (int) $_POST['invitation_id'] : 0, $event->ID );
		$inv ? update_post_meta( $event->ID, self::META_INVITATION, $inv ) : delete_post_meta( $event->ID, self::META_INVITATION );

		$gallery = [];
		if ( isset( $_POST['gallery_ids'] ) && is_array( $_POST['gallery_ids'] ) ) {
			foreach ( wp_unslash( $_POST['gallery_ids'] ) as $gid ) {
				$ok = $this->own_attachment( (int) $gid, $event->ID );
				if ( $ok ) {
					$gallery[] = $ok;
				}
			}
		}
		update_post_meta( $event->ID, self::META_GALLERY, $gallery );

		/**
		 * Fires after a client saves their event.
		 *
		 * @param int $event_id
		 */
		do_action( 'oc_event_saved', $event->ID );

		$this->finish( 'saved' );
	}

	/** @return int The attachment id if it belongs to this event, else 0 (tamper-guard). */
	private function own_attachment( $att_id, $event_id ) {
		$att_id = (int) $att_id;
		if ( ! $att_id ) {
			return 0;
		}
		$att = get_post( $att_id );
		return ( $att && 'attachment' === $att->post_type && (int) $att->post_parent === (int) $event_id ) ? $att_id : 0;
	}

	// ─── AJAX upload (cloned from the vendor gallery uploader) ──────────────

	/**
	 * Upload one cover/gallery/invitation file to the current event. Images for
	 * cover + gallery; images OR PDF for invitation. Each attachment is stamped
	 * with post_parent = the event id so the save-time tamper-guard can trust it.
	 */
	public function ajax_upload() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Please sign in.', 'owambe-connect-core' ) ], 403 );
		}
		check_ajax_referer( self::ACTION_UPLOAD, '_nonce' );

		$user_id = get_current_user_id();
		$event   = oc_get_current_event_post();
		if ( ! $event instanceof WP_Post ) {
			// Create-if-missing so uploads work before the first text save.
			$new = wp_insert_post( [
				'post_type'   => 'oc_event',
				'post_status' => 'publish',
				'post_title'  => __( 'My Event', 'owambe-connect-core' ),
				'post_author' => $user_id,
			], true );
			if ( is_wp_error( $new ) ) {
				wp_send_json_error( [ 'message' => __( 'Could not start your event. Please try again.', 'owambe-connect-core' ) ] );
			}
			$event = get_post( $new );
		} elseif ( (int) $event->post_author !== (int) $user_id && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You cannot edit this event.', 'owambe-connect-core' ) ], 403 );
		}

		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			wp_send_json_error( [ 'message' => __( 'No file received.', 'owambe-connect-core' ) ] );
		}

		$slot = isset( $_POST['slot'] ) ? sanitize_key( wp_unslash( $_POST['slot'] ) ) : 'gallery';
		if ( ! in_array( $slot, [ 'cover', 'gallery', 'invitation' ], true ) ) {
			$slot = 'gallery';
		}

		// Invitation accepts PDF too; cover + gallery are images only.
		$allowed = ( 'invitation' === $slot ) ? oc_document_mimes_only() : oc_image_mimes_only();
		$max_mb  = (int) apply_filters( 'oc_event_upload_max_mb', ( 'invitation' === $slot ? 8 : 5 ), $slot );
		$max_mb  = max( 1, $max_mb );
		if ( (int) ( $_FILES['file']['size'] ?? 0 ) > $max_mb * 1024 * 1024 ) {
			wp_send_json_error( [ 'message' => sprintf(
				/* translators: %d: max MB */
				__( 'That file is over the %d MB limit.', 'owambe-connect-core' ),
				$max_mb
			) ] );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$upload = wp_handle_upload( $_FILES['file'], [ 'test_form' => false, 'mimes' => $allowed ] );
		if ( isset( $upload['error'] ) ) {
			wp_send_json_error( [ 'message' => (string) $upload['error'] ] );
		}

		$filetype = wp_check_filetype( $upload['file'], $allowed );
		$att_id   = wp_insert_attachment( [
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_text_field( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
			'post_status'    => 'inherit',
			'post_parent'    => (int) $event->ID, // tamper-guard anchor.
		], $upload['file'], (int) $event->ID );

		if ( is_wp_error( $att_id ) || ! $att_id ) {
			wp_send_json_error( [ 'message' => __( 'Could not save the file.', 'owambe-connect-core' ) ] );
		}

		$is_image  = 0 === strpos( (string) $filetype['type'], 'image/' );
		$thumb_url = '';
		if ( $is_image ) {
			wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $upload['file'] ) );
			$src       = wp_get_attachment_image_src( $att_id, 'medium' );
			$thumb_url = $src ? $src[0] : wp_get_attachment_url( $att_id );
		}

		wp_send_json_success( [
			'id'        => (int) $att_id,
			'slot'      => $slot,
			'is_image'  => $is_image,
			'thumb_url' => esc_url_raw( $thumb_url ),
			'url'       => esc_url_raw( (string) wp_get_attachment_url( $att_id ) ),
			'filename'  => basename( $upload['file'] ),
		] );
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
