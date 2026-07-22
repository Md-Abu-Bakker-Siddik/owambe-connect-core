<?php
/**
 * Frontend vendor dashboard handlers.
 *
 * Vendors edit their own listing and change password from the dashboard.
 * They never see wp-admin.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Dashboard {

	const ACTION_UPDATE         = 'oc_update_vendor';
	const ACTION_PASSWORD       = 'oc_update_password';
	const ACTION_LOGIN          = 'oc_login_vendor';
	const ACTION_CONTACT        = 'oc_contact_message';
	const ACTION_SUBMIT         = 'oc_submit_for_review';
	const ACTION_SUPPORT        = 'oc_vendor_support';
	const ACTION_FEEDBACK       = 'oc_vendor_feedback';
	const ACTION_VENDOR_REQUEST = 'oc_vendor_request';
	const ACTION_LOST_PASSWORD  = 'oc_lost_password';
	const ACTION_RESET_PASSWORD = 'oc_reset_password';
	const ACTION_GALLERY_UPLOAD = 'oc_gallery_upload_one';
	const ACTION_AJAX_SAVE      = 'oc_ajax_save_listing';

	public function register() {
		add_action( 'admin_post_'        . self::ACTION_UPDATE,         [ $this, 'update_listing' ] );
		add_action( 'admin_post_'        . self::ACTION_PASSWORD,       [ $this, 'update_password' ] );
		add_action( 'admin_post_'        . self::ACTION_SUBMIT,         [ $this, 'submit_for_review' ] );
		add_action( 'admin_post_'        . self::ACTION_SUPPORT,        [ $this, 'support_ticket' ] );
		add_action( 'admin_post_'        . self::ACTION_FEEDBACK,       [ $this, 'vendor_feedback' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION_LOGIN,          [ $this, 'login' ] );
		add_action( 'admin_post_'        . self::ACTION_LOGIN,          [ $this, 'login' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION_CONTACT,        [ $this, 'contact' ] );
		add_action( 'admin_post_'        . self::ACTION_CONTACT,        [ $this, 'contact' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION_VENDOR_REQUEST, [ $this, 'vendor_request' ] );
		add_action( 'admin_post_'        . self::ACTION_VENDOR_REQUEST, [ $this, 'vendor_request' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION_LOST_PASSWORD,  [ $this, 'lost_password' ] );
		add_action( 'admin_post_'        . self::ACTION_LOST_PASSWORD,  [ $this, 'lost_password' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION_RESET_PASSWORD, [ $this, 'reset_password' ] );
		add_action( 'admin_post_'        . self::ACTION_RESET_PASSWORD, [ $this, 'reset_password' ] );

		// AJAX gallery uploader — accepts ONE file at a time, well under any
		// PHP post_max_size limit. Replaces the old multi-file form upload
		// that was 404'ing on Hostinger when 6 photos totalled > 32 MB.
		add_action( 'wp_ajax_' . self::ACTION_GALLERY_UPLOAD, [ $this, 'ajax_gallery_upload' ] );
		add_action( 'wp_ajax_' . self::ACTION_AJAX_SAVE,      [ $this, 'ajax_save_listing' ] );

		// Route wp_lostpassword_url() and the default WP reset URL to our
		// branded pages so any "set your password" link from anywhere in the
		// plugin (login form, admin "send login" email, etc.) lands users on
		// the themed flow instead of /wp-login.php.
		add_filter( 'lostpassword_url', [ $this, 'filter_lostpassword_url' ], 10, 2 );
		add_filter( 'network_site_url', [ $this, 'rewrite_reset_url_in_emails' ], 10, 3 );

		// Hide /wp-login.php from end-users entirely. Any direct hit on
		// /wp-login.php (after logout, after a bookmark, after a typo in an
		// older email link) gets bounced to the branded equivalent — admins
		// can still reach it via /wp-admin/ which routes through cookies.
		add_action( 'login_init', [ $this, 'redirect_wp_login_to_branded' ], 1 );

		// Send vendor users to /vendor-login/ after logging out, not the
		// default /wp-login.php?loggedout=true screen.
		add_filter( 'logout_redirect', [ $this, 'redirect_after_logout' ], 10, 3 );

		// Block wp-admin for vendor-only users.
		add_action( 'admin_init', [ $this, 'block_wp_admin_for_vendors' ] );
		add_action( 'wp_login',   [ $this, 'redirect_vendor_after_login' ], 10, 2 );
	}

	public function block_wp_admin_for_vendors() {
		if ( wp_doing_ajax() || ! is_admin() ) return;

		// CRITICAL: do NOT redirect vendors away from admin-post.php — that's where
		// their own dashboard forms POST to (oc_update_vendor, oc_update_password,
		// oc_submit_for_review, oc_contact_message). Without this exemption, every
		// vendor form submission gets intercepted BEFORE its admin_post_* handler
		// runs, silently dropping the user back on the dashboard with no save.
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( (string) $_SERVER['SCRIPT_NAME'] ) : '';
		if ( in_array( $script, [ 'admin-post.php', 'admin-ajax.php' ], true ) ) {
			return;
		}

		$user  = wp_get_current_user();
		$roles = $user ? (array) $user->roles : [];
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		// Vendors go to the vendor dashboard; Phase 2 clients to theirs.
		// Both roles get the frontend-only experience — never /wp-admin.
		if ( in_array( OC_ROLE, $roles, true ) ) {
			wp_safe_redirect( oc_page_url( 'vendor-dashboard' ) );
			exit;
		}
		if ( in_array( OC_CLIENT_ROLE, $roles, true ) ) {
			wp_safe_redirect( oc_page_url( 'client-dashboard' ) );
			exit;
		}
	}

	public function redirect_vendor_after_login( $user_login, $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return;
		}
		$roles = (array) $user->roles;
		if ( ! in_array( OC_ROLE, $roles, true ) && ! in_array( OC_CLIENT_ROLE, $roles, true ) ) {
			return;
		}
		// This fires inside wp_signon(), BEFORE OC_Dashboard::login() gets to
		// its own redirect — so honor a posted redirect_to here (protected-page
		// bounce round-trip). wp_safe_redirect's allowlist prevents open redirects.
		if ( ! empty( $_POST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_safe_redirect( esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			exit;
		}
		// Vendor wins when a user holds both roles (roles stack when a client
		// later becomes a vendor) — their primary surface is the listing.
		if ( in_array( OC_ROLE, $roles, true ) ) {
			wp_safe_redirect( oc_page_url( 'vendor-dashboard' ) );
			exit;
		}
		wp_safe_redirect( oc_page_url( 'client-dashboard' ) );
		exit;
	}

	public function login() {
		$ref = wp_get_referer() ?: oc_page_url( 'vendor-login' );
		if ( ! isset( $_POST['oc_login_nonce'] ) || ! wp_verify_nonce( $_POST['oc_login_nonce'], self::ACTION_LOGIN ) ) {
			$this->redirect( $ref, __( 'Security check failed.', 'owambe-connect-core' ) );
		}
		$creds = [
			'user_login'    => isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '',
			'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '',
			'remember'      => ! empty( $_POST['rememberme'] ),
		];
		$user = wp_signon( $creds, is_ssl() );
		if ( is_wp_error( $user ) ) {
			$this->redirect( $ref, __( 'Invalid email or password.', 'owambe-connect-core' ) );
		}
		// Honor redirect_to from the form (set when we bounce a logged-out
		// visitor here from a protected page). wp_safe_redirect's allowed-hosts
		// check keeps off-site URLs from being used as an open redirect.
		$dest = oc_page_url( 'vendor-dashboard' );
		if ( ! empty( $_POST['redirect_to'] ) ) {
			$dest = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) );
		}
		wp_safe_redirect( $dest );
		exit;
	}

	public function update_listing() {
		$ref = wp_get_referer() ?: oc_page_url( 'vendor-dashboard' );
		$r   = $this->run_save();
		if ( ! $r['ok'] ) {
			$dest = ! empty( $r['login_redirect'] ) ? oc_page_url( 'vendor-login' ) : $ref;
			$this->redirect( $dest, $r['error'] );
		}
		$this->redirect( $ref, '', $r['notice'] );
	}

	public function ajax_save_listing() {
		$r = $this->run_save();
		if ( ! $r['ok'] ) {
			wp_send_json_error( [ 'message' => $r['error'] ] );
		}
		wp_send_json_success( [
			'notice'     => $r['notice'],
			'post_id'    => $r['post_id'],
			'completion' => oc_profile_completion( $r['post_id'] ),
		] );
	}

	private function run_save(): array {
		$debug_on    = function_exists( 'oc_debug_enabled' ) && oc_debug_enabled();
		$current_uid = get_current_user_id();
		$dbg         = [
			'timestamp'       => current_time( 'mysql' ),
			'is_logged_in'    => is_user_logged_in(),
			'current_user'    => $current_uid,
			'nonce_valid'     => isset( $_POST['oc_update_nonce'] ) && (bool) wp_verify_nonce( $_POST['oc_update_nonce'], self::ACTION_UPDATE ),
			'post_id_in_post' => isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0,
			'fields_received' => array_keys( array_intersect_key( $_POST, array_flip( [ 'business_name','location','location_country','location_areas','location_regions','cultural_specialties','nigerian_specialty','registered_business','vendor_tags','bio','services','price_range','whatsapp_local','public_email','instagram','facebook','website','languages','categories' ] ) ) ),
			'category_count'  => isset( $_POST['categories'] ) ? count( (array) $_POST['categories'] ) : 0,
			'cap_ok'          => null,
			'post_author'     => null,
			'post_status'     => null,
			'outcome'         => 'pending',
			'reason'          => '',
		];

		// Transient debug snapshot — only written when admin debug is on, never for vendor users.
		$store_dbg = function () use ( &$dbg, $debug_on, $current_uid ) {
			if ( $debug_on && $current_uid ) {
				set_transient( 'oc_last_save_dbg_' . $current_uid, $dbg, 5 * MINUTE_IN_SECONDS );
			}
			if ( $debug_on ) {
				oc_debug_log( 'update_listing.' . $dbg['outcome'], $dbg );
			}
		};

		// Shorthand for early-exit errors. login_redirect=true sends the POST
		// wrapper to /vendor-login/; the AJAX handler ignores it and 400s.
		$err = static function ( string $msg, bool $auth = false ) use ( &$dbg, $store_dbg ): array {
			$store_dbg();
			return [ 'ok' => false, 'error' => $msg, 'login_redirect' => $auth, 'notice' => '', 'post_id' => 0 ];
		};

		if ( $debug_on ) { oc_debug_log( 'update_listing.start', $dbg ); }

		// ── Auth gate ──────────────────────────────────────────────
		if ( ! $dbg['is_logged_in'] ) {
			$dbg['outcome'] = 'aborted_not_logged_in';
			return $err( __( 'Please log in.', 'owambe-connect-core' ), true );
		}
		if ( ! $dbg['nonce_valid'] ) {
			$dbg['outcome'] = 'aborted_bad_nonce';
			$dbg['reason']  = 'Nonce missing or expired.';
			return $err( __( 'Security check failed. Please reload the page and try again.', 'owambe-connect-core' ) );
		}

		// ── Rate limit — 1 save per 2 seconds per user (anti-double-submit). ──
		$rate_key = 'oc_save_rl_' . $current_uid;
		if ( get_transient( $rate_key ) ) {
			$dbg['outcome'] = 'aborted_rate_limit';
			return $err( __( 'You\'re saving too quickly. Wait a moment and try again.', 'owambe-connect-core' ) );
		}
		set_transient( $rate_key, 1, 2 ); // 2-second cooldown.

		// ── Create-on-save: dashboard renders the form even when the user
		// has no vendor post yet. The first save materialises the post and
		// promotes the user to the oc_vendor role so the rest of the
		// handler can proceed as if it had always existed. ──
		$post_id = $dbg['post_id_in_post'];
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || OC_CPT !== $post->post_type ) {
			// Maybe the user already has a post but the form didn't pass the
			// id (stale form, JS strip, etc.) — pick it up before creating
			// a duplicate.
			$existing = function_exists( 'oc_get_current_vendor_post' ) ? oc_get_current_vendor_post() : null;
			if ( $existing instanceof WP_Post ) {
				$post    = $existing;
				$post_id = (int) $existing->ID;
			} else {
				// Genuinely new — spin up a fresh vendor post.
				$proposed_name = isset( $_POST['business_name'] )
					? trim( sanitize_text_field( wp_unslash( $_POST['business_name'] ) ) )
					: '';
				if ( '' === $proposed_name ) {
					$dbg['outcome'] = 'aborted_no_business_name';
					return $err( __( 'Please enter your business name to create your listing.', 'owambe-connect-core' ) );
				}

				$user = get_user_by( 'id', $current_uid );

				// AUTHZ: create-on-save must never silently promote an arbitrary
				// logged-in poster to a vendor. Becoming a vendor is an explicit
				// act that goes through /apply/ (OC_Registration). A client-role
				// account (or any non-vendor) that reaches this handler — e.g.
				// via an [oc_vendor_dashboard] embedded on a non-canonical page
				// where the template_redirect gate doesn't fire — is rejected
				// here, where the role is actually granted, not in the view layer.
				if ( $user && ! in_array( OC_ROLE, (array) $user->roles, true ) && ! current_user_can( 'manage_options' ) ) {
					if ( in_array( OC_CLIENT_ROLE, (array) $user->roles, true ) ) {
						$dbg['outcome'] = 'blocked_client_create_on_save';
						return $err( __( 'Your account is a client account. To list a business, apply as a vendor.', 'owambe-connect-core' ) );
					}
				}

				if ( $user && ! in_array( OC_ROLE, (array) $user->roles, true ) ) {
					$user->add_role( OC_ROLE );
					// Refresh the current user's caps cache so the
					// upcoming current_user_can( OC_CAP_EDIT_OWN ) check
					// sees the just-added role within this same request.
					wp_set_current_user( $current_uid );
				}

				$new_id = wp_insert_post( [
					'post_type'    => OC_CPT,
					'post_status'  => OC_STATUS_PENDING,
					'post_title'   => $proposed_name,
					'post_content' => '',
					'post_author'  => $current_uid,
				], true );
				if ( is_wp_error( $new_id ) ) {
					$dbg['outcome'] = 'aborted_post_create_failed';
					$dbg['reason']  = $new_id->get_error_message();
					return $err( $new_id->get_error_message() );
				}

				update_post_meta( $new_id, '_oc_business_name',        $proposed_name );
				update_post_meta( $new_id, '_oc_submitted_for_review', 0 );

				// Fire the same hook as the regular /apply/ signup so vendor
				// number assignment, completion %, and the email-verification
				// dispatcher all stay aligned across both flows.
				do_action( 'oc_after_vendor_registered', $new_id, $current_uid );

				$post    = get_post( $new_id );
				$post_id = (int) $new_id;
			}

			// Make the rest of the handler see this resolved post.
			$_POST['post_id']       = $post_id;
			$dbg['post_id_in_post'] = $post_id;
		}

		// ── Capability check ──────────────────────────────────────
		$dbg['post_author'] = $post ? (int) $post->post_author : null;
		$dbg['post_status'] = $post ? $post->post_status : null;
		$dbg['cap_ok']      = current_user_can( OC_CAP_EDIT_OWN, $post_id );

		if ( ! $dbg['cap_ok'] ) {
			$dbg['outcome'] = 'aborted_no_cap';
			$dbg['reason']  = $post
				? "post_author={$post->post_author} vs current_user={$current_uid}"
				: "post #{$post_id} does not exist";
			return $err( __( 'You cannot edit this listing.', 'owambe-connect-core' ) );
		}

		// Belt-and-braces: confirm the post is the vendor's CPT (not someone smuggling a non-vendor post_id).
		if ( ! $post || OC_CPT !== $post->post_type ) {
			$dbg['outcome'] = 'aborted_wrong_post_type';
			return $err( __( 'Invalid listing reference.', 'owambe-connect-core' ) );
		}

		// ── Defensive length caps (defence-in-depth — also matches DB column sane sizes). ──
		$cap = function ( $val, $max ) {
			$val = (string) $val;
			return function_exists( 'mb_substr' ) ? mb_substr( $val, 0, $max ) : substr( $val, 0, $max );
		};

		$business_name = isset( $_POST['business_name'] ) ? $cap( sanitize_text_field( wp_unslash( $_POST['business_name'] ) ), 150 ) : '';
		if ( '' !== $business_name ) {
			// IMPORTANT — preserve the listing's current status across this edit.
			// wp_update_post() → wp_insert_post() silently downgrades a 'publish'
			// post to 'pending' when the editing user lacks `publish_posts`, and
			// vendors deliberately don't have that cap. Without this guard, every
			// time an APPROVED vendor edited their profile (or photos) WordPress
			// kicked them back to "awaiting approval". A vendor editing their own
			// listing must never change its review status — only submit_for_review()
			// and the admin Approve/Reject actions do that.
			$preserve_status = $post->post_status;
			$pin_status = static function ( $data ) use ( $preserve_status ) {
				$data['post_status'] = $preserve_status;
				return $data;
			};
			add_filter( 'wp_insert_post_data', $pin_status, 99 );
			wp_update_post( [
				'ID'           => $post_id,
				'post_title'   => $business_name,
				'post_content' => isset( $_POST['bio'] ) ? $cap( wp_kses_post( wp_unslash( $_POST['bio'] ) ), 5000 ) : '',
			] );
			remove_filter( 'wp_insert_post_data', $pin_status, 99 );
			update_post_meta( $post_id, '_oc_business_name', $business_name );
		}

		// WhatsApp: form posts the local 10-digit portion (whatsapp_local). We
		// always normalise back to canonical +44XXXXXXXXXX so deep-links work
		// and we never end up with "(0)..." drops. Legacy `whatsapp` field is
		// still accepted for backwards-compat with any old form layouts.
		$wa_raw = '';
		if ( isset( $_POST['whatsapp_local'] ) ) {
			$wa_raw = (string) wp_unslash( $_POST['whatsapp_local'] );
		} elseif ( isset( $_POST['whatsapp'] ) ) {
			$wa_raw = (string) wp_unslash( $_POST['whatsapp'] );
		}
		$wa_normalised = oc_normalize_uk_whatsapp( $wa_raw );

		// Service area: country select + multi-select areas. We keep writing
		// the legacy `_oc_location` so any old template/queries that read it
		// still work — it becomes a human-readable summary of country + areas.
		$country = isset( $_POST['location_country'] )
			? $cap( sanitize_text_field( wp_unslash( $_POST['location_country'] ) ), 40 )
			: '';
		$areas   = isset( $_POST['location_areas'] )
			? oc_sanitize_csv( wp_unslash( $_POST['location_areas'] ) )
			: [];
		$country_options = oc_country_options();
		if ( $country && ! isset( $country_options[ $country ] ) ) {
			$country = ''; // drop unknown values
		}
		// Regions are England-only. Validate against the canonical list and
		// drop everything if the vendor isn't in England, so the data stays clean.
		$region_opts = function_exists( 'oc_region_options' ) ? oc_region_options() : [];
		$regions     = isset( $_POST['location_regions'] )
			? array_values( array_intersect( oc_sanitize_csv( wp_unslash( $_POST['location_regions'] ) ), $region_opts ) )
			: [];
		if ( 'england' !== $country ) {
			$regions = [];
		}
		// Fold areas + regions + country into the legacy `_oc_location` summary —
		// this is the field the directory / hero search LIKE-match against, so
		// regions must live here to be findable.
		$loc_summary = '';
		if ( $country || $areas || $regions ) {
			$parts = [];
			if ( $areas )   $parts[] = implode( ', ', array_slice( $areas, 0, 10 ) );
			if ( $regions ) $parts[] = implode( ', ', $regions );
			if ( $country ) $parts[] = $country_options[ $country ];
			$loc_summary = implode( ' — ', $parts );
		} elseif ( isset( $_POST['location'] ) ) {
			$loc_summary = $cap( sanitize_text_field( wp_unslash( $_POST['location'] ) ), 150 );
		}

		// Cultural specialties + vendor tags: validate against canonical option lists.
		$cultural_keys = array_keys( oc_cultural_specialty_options() );
		$cultural      = isset( $_POST['cultural_specialties'] )
			? array_values( array_intersect( oc_sanitize_csv( wp_unslash( $_POST['cultural_specialties'] ) ), $cultural_keys ) )
			: [];

		$allowed_tags = oc_vendor_tag_options_flat();
		$tags         = isset( $_POST['vendor_tags'] )
			? array_values( array_intersect( oc_sanitize_csv( wp_unslash( $_POST['vendor_tags'] ) ), $allowed_tags ) )
			: [];

		// Yes / No fields — drop anything not in the allowed set so admin
		// queries are reliable ('' = unanswered, 'yes' / 'no' = answered).
		$reg_biz_raw = isset( $_POST['registered_business'] ) ? sanitize_key( wp_unslash( $_POST['registered_business'] ) ) : '';
		$reg_biz     = in_array( $reg_biz_raw, [ 'yes', 'no' ], true ) ? $reg_biz_raw : '';
		$nigerian_raw = isset( $_POST['nigerian_specialty'] ) ? sanitize_key( wp_unslash( $_POST['nigerian_specialty'] ) ) : '';
		$nigerian     = in_array( $nigerian_raw, [ 'yes', 'no' ], true ) ? $nigerian_raw : '';

		$pairs = [
			'_oc_location'             => $loc_summary,
			'_oc_location_country'     => $country,
			'_oc_location_areas'       => $areas,
			'_oc_location_regions'     => $regions,
			'_oc_cultural_specialties' => $cultural,
			'_oc_nigerian_specialty'   => $nigerian,
			'_oc_registered_business'  => $reg_biz,
			'_oc_vendor_tags'          => $tags,
			'_oc_bio'                  => isset( $_POST['bio'] )         ? $cap( wp_kses_post( wp_unslash( $_POST['bio'] ) ), 5000 ) : '',
			'_oc_services'             => isset( $_POST['services'] )    ? $cap( wp_kses_post( wp_unslash( $_POST['services'] ) ), 3000 ) : '',
			'_oc_price_range'          => isset( $_POST['price_range'] ) ? $cap( sanitize_text_field( wp_unslash( $_POST['price_range'] ) ), 20 ) : '',
			'_oc_whatsapp'             => $cap( $wa_normalised, 30 ),
			'_oc_public_email'         => isset( $_POST['public_email'] ) ? $cap( sanitize_email( wp_unslash( $_POST['public_email'] ) ), 120 ) : '',
			'_oc_instagram'            => isset( $_POST['instagram'] )   ? $cap( oc_sanitize_handle( wp_unslash( $_POST['instagram'] ) ), 60 ) : '',
			'_oc_facebook'             => isset( $_POST['facebook'] )    ? $cap( sanitize_text_field( wp_unslash( $_POST['facebook'] ) ), 200 ) : '',
			'_oc_website'              => isset( $_POST['website'] )     ? $cap( esc_url_raw( wp_unslash( $_POST['website'] ) ), 200 ) : '',
		];
		foreach ( $pairs as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		// Languages: only overwrite when the form actually posted a value.
		// Per client feedback v1, this field is suspended from the dashboard UI
		// and replaced by Cultural Specialties — but existing per-vendor data is
		// preserved untouched so admins can re-surface it later if needed.
		if ( isset( $_POST['languages'] ) ) {
			update_post_meta( $post_id, '_oc_languages', oc_sanitize_csv( wp_unslash( $_POST['languages'] ) ) );
		}

		// (Removed) Gallery "set as display" — the card and profile picture now
		// come solely from the vendor's Display picture (banner). Gallery photos
		// are portfolio-only and never promoted to the card. Any legacy
		// _oc_gallery_display_id meta is simply ignored.

		// ── Categories: restrict to terms that actually exist in our taxonomy. ──
		if ( isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ) {
			$raw_ids   = array_slice( array_map( 'absint', $_POST['categories'] ), 0, 15 ); // hard cap
			$valid_ids = [];
			foreach ( $raw_ids as $tid ) {
				if ( $tid && get_term( $tid, OC_TAX ) ) {
					$valid_ids[] = $tid;
				}
			}
			wp_set_object_terms( $post_id, $valid_ids, OC_TAX );
		}

		$logo_max          = max( 1, (int) oc_get_setting( 'logo_max_mb',   2 ) ) * 1024 * 1024;
		$banner_max        = max( 1, (int) oc_get_setting( 'banner_max_mb', 5 ) ) * 1024 * 1024;
		$gallery_max_count = function_exists( 'oc_vendor_gallery_cap' ) ? oc_vendor_gallery_cap( $post_id ) : max( 0, (int) oc_get_setting( 'gallery_max_images', 6 ) );
		$gallery_max_bytes = max( 1, (int) oc_get_setting( 'gallery_max_mb',     3 ) ) * 1024 * 1024;
		$upload_errors     = [];

		// Logo + banner now arrive via the AJAX uploader (hidden inputs
		// logo_new_id / banner_new_id). Legacy form file inputs are still
		// honoured as a fallback so this stays backwards-compatible with
		// the bulk-import and admin Add Vendor flows that don't use AJAX.
		$logo_new_id = isset( $_POST['logo_new_id'] ) ? (int) $_POST['logo_new_id'] : 0;
		if ( $logo_new_id > 0 ) {
			$logo_att = get_post( $logo_new_id );
			if ( $logo_att && 'attachment' === $logo_att->post_type && (int) $logo_att->post_parent === (int) $post_id ) {
				update_post_meta( $post_id, '_oc_logo_id', $logo_new_id );
				set_post_thumbnail( $post_id, $logo_new_id );
			}
		} else {
			$logo_id = oc_handle_image_upload( 'logo', $post_id, $logo_max );
			if ( $logo_id && ! is_wp_error( $logo_id ) ) {
				update_post_meta( $post_id, '_oc_logo_id', $logo_id );
				set_post_thumbnail( $post_id, $logo_id );
			} elseif ( is_wp_error( $logo_id ) ) {
				$upload_errors[] = $logo_id->get_error_message();
			}
		}

		$banner_new_id = isset( $_POST['banner_new_id'] ) ? (int) $_POST['banner_new_id'] : 0;
		if ( $banner_new_id > 0 ) {
			$banner_att = get_post( $banner_new_id );
			if ( $banner_att && 'attachment' === $banner_att->post_type && (int) $banner_att->post_parent === (int) $post_id ) {
				update_post_meta( $post_id, '_oc_banner_id', $banner_new_id );
			}
		} else {
			$banner_id = oc_handle_image_upload( 'banner', $post_id, $banner_max );
			if ( $banner_id && ! is_wp_error( $banner_id ) ) {
				update_post_meta( $post_id, '_oc_banner_id', $banner_id );
			} elseif ( is_wp_error( $banner_id ) ) {
				$upload_errors[] = $banner_id->get_error_message();
			}
		}

		// Gallery — append; never overwrite existing items unless an explicit
		// remove was requested via `gallery_remove[]`.
		$existing_gallery = (array) get_post_meta( $post_id, '_oc_gallery_ids', true );
		$existing_gallery = array_values( array_filter( array_map( 'intval', $existing_gallery ) ) );
		if ( ! empty( $_POST['gallery_remove'] ) && is_array( $_POST['gallery_remove'] ) ) {
			$drop = array_map( 'intval', $_POST['gallery_remove'] );
			$existing_gallery = array_values( array_diff( $existing_gallery, $drop ) );
		}
		if ( $gallery_max_count > 0 ) {
			$existing_gallery = oc_handle_gallery_upload( 'gallery', $post_id, $gallery_max_count, $gallery_max_bytes, $existing_gallery );
		}

		// Append IDs that were already AJAX-uploaded via the new per-file
		// uploader. Each one was attached to this post in the upload handler,
		// so we just need to splice them into _oc_gallery_ids on save and
		// respect the max-count cap.
		if ( ! empty( $_POST['gallery_new_ids'] ) && is_array( $_POST['gallery_new_ids'] ) ) {
			$new_ids = array_values( array_filter( array_map( 'intval', wp_unslash( $_POST['gallery_new_ids'] ) ) ) );
			foreach ( $new_ids as $aid ) {
				if ( count( $existing_gallery ) >= $gallery_max_count ) break;
				if ( in_array( $aid, $existing_gallery, true ) ) continue;
				$att = get_post( $aid );
				if ( ! $att || 'attachment' !== $att->post_type ) continue;
				if ( (int) $att->post_parent !== (int) $post_id ) continue; // tamper guard
				$existing_gallery[] = $aid;
			}
		}
		update_post_meta( $post_id, '_oc_gallery_ids', $existing_gallery );

		if ( $upload_errors ) {
			return $err( implode( ' ', $upload_errors ) );
		}

		// If listing was rejected, mark for re-review.
		$post = get_post( $post_id );
		if ( $post && OC_STATUS_REJECTED === $post->post_status ) {
			wp_update_post( [ 'ID' => $post_id, 'post_status' => OC_STATUS_PENDING ] );
			delete_post_meta( $post_id, '_oc_rejection_note' );
			OC_Mail::admin_new_application( $post_id );
		}

		do_action( 'oc_after_vendor_updated', $post_id );

		$dbg['outcome'] = 'success';
		if ( $debug_on ) {
			// Snapshot DB state only when admin debug is active — saves work on every prod save.
			$dbg['meta_after'] = [
				'_oc_business_name' => get_post_meta( $post_id, '_oc_business_name', true ),
				'_oc_location'      => get_post_meta( $post_id, '_oc_location', true ),
				'_oc_bio_len'       => strlen( (string) get_post_meta( $post_id, '_oc_bio', true ) ),
				'_oc_services_len'  => strlen( (string) get_post_meta( $post_id, '_oc_services', true ) ),
				'_oc_whatsapp'      => get_post_meta( $post_id, '_oc_whatsapp', true ),
				'_oc_price_range'   => get_post_meta( $post_id, '_oc_price_range', true ),
				'_oc_languages'     => (array) get_post_meta( $post_id, '_oc_languages', true ),
			];
			$dbg['terms_after'] = wp_get_object_terms( $post_id, OC_TAX, [ 'fields' => 'slugs' ] );
		}
		$store_dbg();

		return [
			'ok'            => true,
			'error'         => '',
			'notice'        => __( 'Listing updated.', 'owambe-connect-core' ),
			'login_redirect' => false,
			'post_id'       => $post_id,
		];
	}

	public function update_password() {
		$ref = wp_get_referer() ?: oc_page_url( 'vendor-dashboard' );
		if ( ! is_user_logged_in() ) {
			$this->redirect( oc_page_url( 'vendor-login' ), __( 'Please log in.', 'owambe-connect-core' ) );
		}
		if ( ! isset( $_POST['oc_password_nonce'] ) || ! wp_verify_nonce( $_POST['oc_password_nonce'], self::ACTION_PASSWORD ) ) {
			$this->redirect( $ref, __( 'Security check failed.', 'owambe-connect-core' ) );
		}
		$current = isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : '';
		$new     = isset( $_POST['new_password'] )     ? (string) wp_unslash( $_POST['new_password'] )     : '';
		$confirm = isset( $_POST['confirm_password'] ) ? (string) wp_unslash( $_POST['confirm_password'] ) : '';

		$user = wp_get_current_user();
		if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
			$this->redirect( $ref, __( 'Current password is incorrect.', 'owambe-connect-core' ) );
		}
		if ( strlen( $new ) < 8 ) {
			$this->redirect( $ref, __( 'New password must be at least 8 characters.', 'owambe-connect-core' ) );
		}
		if ( $new !== $confirm ) {
			$this->redirect( $ref, __( 'Passwords do not match.', 'owambe-connect-core' ) );
		}
		wp_set_password( $new, $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		$this->redirect( $ref, '', __( 'Password updated.', 'owambe-connect-core' ) );
	}

	public function contact() {
		$ref = wp_get_referer() ?: oc_page_url( 'contact' );
		if ( ! isset( $_POST['oc_contact_nonce'] ) || ! wp_verify_nonce( $_POST['oc_contact_nonce'], self::ACTION_CONTACT ) ) {
			$this->redirect( $ref, __( 'Security check failed.', 'owambe-connect-core' ) );
		}
		// Honeypot — silently succeed (don't tell bots they're caught).
		if ( ! empty( $_POST['oc_hp'] ) ) {
			$this->redirect( $ref, '', __( 'Thanks — we\'ll be in touch.', 'owambe-connect-core' ) );
		}
		// reCAPTCHA v3.
		$rc_token = isset( $_POST['oc_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['oc_recaptcha_token'] ) ) : '';
		if ( ! oc_verify_recaptcha( $rc_token ) ) {
			$this->redirect( $ref, __( 'Spam check failed. Please try again.', 'owambe-connect-core' ) );
		}
		$name    = isset( $_POST['name'] )    ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email   = isset( $_POST['email'] )   ? sanitize_email( wp_unslash( $_POST['email'] ) )     : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( '' === $name || ! is_email( $email ) || strlen( $message ) < 10 ) {
			$this->redirect( $ref, __( 'Please fill in all fields with a complete message.', 'owambe-connect-core' ) );
		}

		// Persist every contact-form submission so it's never lost even if
		// SMTP drops the notification email silently.
		$entry_id = class_exists( 'OC_Enquiry_Log' )
			? OC_Enquiry_Log::record( 'contact_message', [ 'name' => $name, 'email' => $email, 'message' => $message ], OC_Mail::notification_recipient() )
			: null;
		$ok = OC_Mail::contact_message( $name, $email, $message );
		if ( $entry_id && class_exists( 'OC_Enquiry_Log' ) ) {
			OC_Enquiry_Log::update_status( $entry_id, (bool) $ok );
		}

		$this->redirect( $ref, '', __( 'Thanks — your message is on its way.', 'owambe-connect-core' ) );
	}

	public function submit_for_review() {
		$ref = wp_get_referer() ?: oc_page_url( 'vendor-dashboard' );
		if ( ! is_user_logged_in() ) {
			$this->redirect( oc_page_url( 'vendor-login' ), __( 'Please log in.', 'owambe-connect-core' ) );
		}
		if ( ! isset( $_POST['oc_submit_nonce'] ) || ! wp_verify_nonce( $_POST['oc_submit_nonce'], self::ACTION_SUBMIT ) ) {
			$this->redirect( $ref, __( 'Security check failed.', 'owambe-connect-core' ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! current_user_can( OC_CAP_EDIT_OWN, $post_id ) ) {
			$this->redirect( $ref, __( 'You cannot submit this listing.', 'owambe-connect-core' ) );
		}

		if ( oc_is_submitted_for_review( $post_id ) ) {
			$this->redirect( $ref, '', __( 'Your listing is already under review.', 'owambe-connect-core' ) );
		}

		$completion = oc_profile_completion( $post_id );
		if ( empty( $completion['submittable'] ) ) {
			$this->redirect( $ref, __( 'Your profile isn\'t quite ready yet — please complete the highlighted items first.', 'owambe-connect-core' ) );
		}

		update_post_meta( $post_id, '_oc_submitted_for_review',    1 );
		update_post_meta( $post_id, '_oc_submitted_for_review_at', time() );

		// Auto-approve setting now applies at submit-time, not signup-time.
		$auto_approve = (int) oc_get_setting( 'auto_approve', 0 ) === 1;
		if ( $auto_approve ) {
			wp_update_post( [ 'ID' => $post_id, 'post_status' => OC_STATUS_APPROVED ] );
			OC_Mail::vendor_approved( $post_id );
			do_action( 'oc_after_vendor_approved', $post_id );
			$this->redirect( $ref, '', __( 'Submitted and approved — your listing is now live.', 'owambe-connect-core' ) );
		}

		// Make sure the post is in pending state (in case it was previously rejected and edited).
		$post = get_post( $post_id );
		if ( $post && OC_STATUS_PENDING !== $post->post_status ) {
			wp_update_post( [ 'ID' => $post_id, 'post_status' => OC_STATUS_PENDING ] );
		}

		OC_Mail::admin_new_application( $post_id );

		$this->redirect( $ref, '', __( 'Submitted for review — we\'ll email you once it\'s approved.', 'owambe-connect-core' ) );
	}

	/**
	 * Vendor-side support ticket form (Account tab → Contact support card).
	 * Validates auth + nonce + rate-limit, then dispatches via OC_Mail to the
	 * admin notification recipient (set in OC Settings → Notification Email).
	 */
	public function support_ticket() {
		$ref = wp_get_referer() ?: oc_page_url( 'vendor-dashboard' );
		if ( ! is_user_logged_in() ) {
			$this->redirect( oc_page_url( 'vendor-login' ), __( 'Please log in.', 'owambe-connect-core' ) );
		}
		if ( ! isset( $_POST['oc_support_nonce'] ) || ! wp_verify_nonce( $_POST['oc_support_nonce'], self::ACTION_SUPPORT ) ) {
			$this->redirect( $ref, __( 'Security check failed.', 'owambe-connect-core' ) );
		}
		// Honeypot.
		if ( ! empty( $_POST['oc_hp'] ) ) {
			$this->redirect( $ref, '', __( 'Thanks — your message is on its way.', 'owambe-connect-core' ) );
		}
		// 1-per-30s rate limit so accidental double-clicks don't spam the inbox.
		$rate_key = 'oc_support_rl_' . get_current_user_id();
		if ( get_transient( $rate_key ) ) {
			$this->redirect( $ref, __( 'You sent a message moments ago — please wait a bit before sending another.', 'owambe-connect-core' ) );
		}
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		if ( '' === $subject || strlen( $message ) < 10 ) {
			$this->redirect( $ref, __( 'Please add a subject and a complete message (at least 10 characters).', 'owambe-connect-core' ) );
		}
		set_transient( $rate_key, 1, 30 );

		$post = function_exists( 'oc_get_current_vendor_post' ) ? oc_get_current_vendor_post() : null;
		OC_Mail::support_ticket( $post ? $post->ID : 0, $subject, $message );
		$this->redirect( $ref, '', __( 'Support message sent — we\'ll get back to you.', 'owambe-connect-core' ) );
	}

	/**
	 * Vendor-side feedback / suggestion form (Account tab → Make a suggestion).
	 * Same shape as the support handler but tagged differently in OC_Mail so
	 * admin can filter feedback away from urgent support tickets.
	 */
	public function vendor_feedback() {
		$ref = wp_get_referer() ?: oc_page_url( 'vendor-dashboard' );
		if ( ! is_user_logged_in() ) {
			$this->redirect( oc_page_url( 'vendor-login' ), __( 'Please log in.', 'owambe-connect-core' ) );
		}
		if ( ! isset( $_POST['oc_feedback_nonce'] ) || ! wp_verify_nonce( $_POST['oc_feedback_nonce'], self::ACTION_FEEDBACK ) ) {
			$this->redirect( $ref, __( 'Security check failed.', 'owambe-connect-core' ) );
		}
		if ( ! empty( $_POST['oc_hp'] ) ) {
			$this->redirect( $ref, '', __( 'Thanks for your suggestion.', 'owambe-connect-core' ) );
		}
		$rate_key = 'oc_feedback_rl_' . get_current_user_id();
		if ( get_transient( $rate_key ) ) {
			$this->redirect( $ref, __( 'You sent feedback moments ago — please wait a bit before sending another.', 'owambe-connect-core' ) );
		}
		$topic   = isset( $_POST['topic'] )   ? sanitize_text_field( wp_unslash( $_POST['topic'] ) )      : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		if ( '' === $topic || strlen( $message ) < 10 ) {
			$this->redirect( $ref, __( 'Please pick a topic and write at least 10 characters of feedback.', 'owambe-connect-core' ) );
		}
		set_transient( $rate_key, 1, 30 );

		$post = function_exists( 'oc_get_current_vendor_post' ) ? oc_get_current_vendor_post() : null;
		OC_Mail::vendor_feedback( $post ? $post->ID : 0, $topic, $message );
		$this->redirect( $ref, '', __( 'Thanks — your suggestion is in our inbox.', 'owambe-connect-core' ) );
	}

	/**
	 * Public "Request a vendor" floating-button form. Open to logged-out
	 * users, so we lean harder on the honeypot + reCAPTCHA + per-IP rate
	 * limit to keep junk out of admin's inbox.
	 */
	public function vendor_request() {
		$ref = wp_get_referer() ?: home_url( '/' );
		if ( ! isset( $_POST['oc_vrq_nonce'] ) || ! wp_verify_nonce( $_POST['oc_vrq_nonce'], self::ACTION_VENDOR_REQUEST ) ) {
			$this->redirect( $ref, __( 'Security check failed.', 'owambe-connect-core' ) );
		}
		if ( ! empty( $_POST['oc_hp'] ) ) {
			$this->redirect( $ref, '', __( 'Thanks — we\'ll be in touch.', 'owambe-connect-core' ) );
		}
		$rc_token = isset( $_POST['oc_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['oc_recaptcha_token'] ) ) : '';
		if ( ! oc_verify_recaptcha( $rc_token ) ) {
			$this->redirect( $ref, __( 'Spam check failed. Please try again.', 'owambe-connect-core' ) );
		}
		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
		$rate_key = 'oc_vrq_rl_' . md5( $ip );
		if ( $ip && get_transient( $rate_key ) ) {
			$this->redirect( $ref, __( 'You sent a request moments ago — please wait a minute before sending another.', 'owambe-connect-core' ) );
		}

		$data = [
			'name'        => isset( $_POST['name'] )        ? sanitize_text_field( wp_unslash( $_POST['name'] ) )        : '',
			'email'       => isset( $_POST['email'] )       ? sanitize_email( wp_unslash( $_POST['email'] ) )            : '',
			'phone'       => isset( $_POST['phone'] )       ? sanitize_text_field( wp_unslash( $_POST['phone'] ) )       : '',
			'event_date'  => isset( $_POST['event_date'] )  ? sanitize_text_field( wp_unslash( $_POST['event_date'] ) )  : '',
			'event_type'  => isset( $_POST['event_type'] )  ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) )  : '',
			'location'    => isset( $_POST['location'] )    ? sanitize_text_field( wp_unslash( $_POST['location'] ) )    : '',
			'budget'      => isset( $_POST['budget'] )      ? sanitize_text_field( wp_unslash( $_POST['budget'] ) )      : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
		];

		if ( '' === $data['name'] || ! is_email( $data['email'] ) || strlen( $data['description'] ) < 10 ) {
			$this->redirect( $ref, __( 'Please fill in your name, a valid email, and a short description.', 'owambe-connect-core' ) );
		}
		if ( $ip ) set_transient( $rate_key, 1, MINUTE_IN_SECONDS );

		// Persist the enquiry FIRST — every submission is recorded in the
		// admin "Enquiries" page even when the SMTP layer (FluentSMTP /
		// Mailgun / etc.) silently drops the message. Update the entry's
		// mail-status flag after the send attempt so admin can see at a
		// glance whether the notification got out.
		$entry_id = class_exists( 'OC_Enquiry_Log' )
			? OC_Enquiry_Log::record( 'vendor_request', $data, OC_Mail::notification_recipient() )
			: null;
		$ok = OC_Mail::vendor_request( $data );
		if ( $entry_id && class_exists( 'OC_Enquiry_Log' ) ) {
			OC_Enquiry_Log::update_status( $entry_id, (bool) $ok );
		}

		$this->redirect( $ref, '', __( 'Thanks — we received your vendor request and will get back to you shortly.', 'owambe-connect-core' ) );
	}

	/**
	 * AJAX endpoint — receive ONE image at a time (logo / banner / gallery)
	 * and attach it to the current vendor's post. Returning attachment ID +
	 * preview URL lets the dashboard JS render the new image immediately
	 * and store a hidden {slot}_new_id input that update_listing() picks
	 * up on the next form save.
	 *
	 * Each call is a small (≤ a few MB) request so it never hits the PHP
	 * post_max_size ceiling that was 404'ing the old multi-file form post.
	 */
	public function ajax_gallery_upload() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Please log in.', 'owambe-connect-core' ) ], 403 );
		}
		check_ajax_referer( self::ACTION_GALLERY_UPLOAD, '_nonce' );

		$post = function_exists( 'oc_get_current_vendor_post' ) ? oc_get_current_vendor_post() : null;
		if ( ! $post instanceof WP_Post ) {
			wp_send_json_error( [ 'message' => __( 'No vendor listing found for your account.', 'owambe-connect-core' ) ] );
		}
		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( "You can't edit this listing.", 'owambe-connect-core' ) ], 403 );
		}

		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			wp_send_json_error( [ 'message' => __( 'No file received.', 'owambe-connect-core' ) ] );
		}

		// Slot routing: each slot has its own per-file MB cap configured in
		// plugin settings. Default to gallery rules if a stray slot value
		// somehow arrives.
		$slot = isset( $_POST['slot'] ) ? sanitize_key( wp_unslash( $_POST['slot'] ) ) : 'gallery';
		if ( ! in_array( $slot, [ 'logo', 'banner', 'gallery' ], true ) ) {
			$slot = 'gallery';
		}
		$slot_settings = [
			'logo'    => [ 'setting' => 'logo_max_mb',    'default' => 2, 'preview' => 'thumbnail' ],
			'banner'  => [ 'setting' => 'banner_max_mb',  'default' => 5, 'preview' => 'large' ],
			'gallery' => [ 'setting' => 'gallery_max_mb', 'default' => 3, 'preview' => 'thumbnail' ],
		];
		$cfg       = $slot_settings[ $slot ];
		$max_mb    = max( 1, (int) oc_get_setting( $cfg['setting'], $cfg['default'] ) );
		$max_bytes = $max_mb * 1024 * 1024;
		if ( (int) ( $_FILES['file']['size'] ?? 0 ) > $max_bytes ) {
			wp_send_json_error( [ 'message' => sprintf(
				/* translators: %d: max MB */
				__( 'That image is over the %d MB limit per file.', 'owambe-connect-core' ),
				$max_mb
			) ] );
		}

		$allowed_mimes = [ 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ];

		// wp_handle_upload + wp_insert_attachment dance. media_handle_upload
		// would be even shorter but it pulls in admin scripts; the manual
		// path keeps front-end memory lean.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$upload = wp_handle_upload( $_FILES['file'], [
			'test_form' => false,
			'mimes'     => $allowed_mimes,
		] );
		if ( isset( $upload['error'] ) ) {
			wp_send_json_error( [ 'message' => (string) $upload['error'] ] );
		}

		$filetype = wp_check_filetype( $upload['file'], $allowed_mimes );
		$att_id   = wp_insert_attachment( [
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_text_field( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => (int) $post->ID,
		], $upload['file'], (int) $post->ID );

		if ( is_wp_error( $att_id ) || ! $att_id ) {
			wp_send_json_error( [ 'message' => __( 'Could not save attachment.', 'owambe-connect-core' ) ] );
		}

		$meta = wp_generate_attachment_metadata( $att_id, $upload['file'] );
		wp_update_attachment_metadata( $att_id, $meta );

		$preview = wp_get_attachment_image_src( $att_id, $cfg['preview'] );

		wp_send_json_success( [
			'id'        => (int) $att_id,
			'slot'      => $slot,
			'thumb_url' => $preview ? esc_url_raw( $preview[0] ) : esc_url_raw( wp_get_attachment_url( $att_id ) ),
			'filename'  => basename( $upload['file'] ),
		] );
	}

	private function redirect( $url, $error = '', $notice = '' ) {
		$args = [];
		if ( $error )  $args['oc_error']  = rawurlencode( $error );
		if ( $notice ) $args['oc_notice'] = rawurlencode( $notice );

		// Preserve the active dashboard tab across the round-trip so the user
		// lands back where they were (the form sends `_oc_tab` as a hidden field).
		if ( isset( $_POST['_oc_tab'] ) ) {
			$tab = sanitize_key( wp_unslash( $_POST['_oc_tab'] ) );
			// May-2026 split: listing was broken into business + story + contact.
			// Keeping 'listing' in the whitelist for backwards-compat — the JS
			// redirects unknown values to "overview", so a stale URL is safe.
			if ( in_array( $tab, [ 'overview', 'business', 'story', 'contact', 'listing', 'photos', 'account' ], true ) ) {
				$args['tab'] = $tab;
			}
		}
		wp_safe_redirect( $args ? add_query_arg( $args, $url ) : $url );
		exit;
	}

	// ──────────────────────────────────────────────────────────
	//  Branded password reset (forgot → email → set new password)
	// ──────────────────────────────────────────────────────────

	/**
	 * Step 1 — visitor submitted /forgot-password/ with their email. We
	 * generate a reset key and send our own templated email pointing at
	 * /reset-password/. Always shows a generic "if that email exists you'll
	 * get a link" success message so we don't leak which emails are on file.
	 */
	public function lost_password() {
		$forgot_url = oc_page_url( 'forgot-password' );
		if ( ! isset( $_POST['oc_lp_nonce'] ) || ! wp_verify_nonce( $_POST['oc_lp_nonce'], self::ACTION_LOST_PASSWORD ) ) {
			$this->redirect( $forgot_url, __( 'Security check failed. Please try again.', 'owambe-connect-core' ) );
		}

		$email = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		$generic_ok = __( 'If an account exists for that email we\'ve sent a reset link. Check your inbox (and spam folder).', 'owambe-connect-core' );

		if ( '' === $email ) {
			$this->redirect( $forgot_url, __( 'Please enter your email address.', 'owambe-connect-core' ) );
		}

		// Look up user by email first, then login (covers either input).
		$user = is_email( $email ) ? get_user_by( 'email', $email ) : null;
		if ( ! $user ) $user = get_user_by( 'login', $email );

		// Anti-enumeration: success message is the same whether the user
		// exists or not. The actual mail is only attempted if it does.
		if ( $user instanceof WP_User ) {
			$key = get_password_reset_key( $user );
			if ( ! is_wp_error( $key ) ) {
				$reset_url = add_query_arg(
					[
						'key'   => $key,
						'login' => rawurlencode( $user->user_login ),
					],
					oc_page_url( 'reset-password' )
				);
				if ( class_exists( 'OC_Mail' ) ) {
					OC_Mail::password_reset( $user, $reset_url );
				}
			}
		}

		$this->redirect( $forgot_url, '', $generic_ok );
	}

	/**
	 * Step 2 — visitor clicked the email link, landed on /reset-password/,
	 * filled in their new password and submitted. We validate the key+login
	 * via WP core, then call reset_password() which hashes + saves.
	 */
	public function reset_password() {
		$reset_url = oc_page_url( 'reset-password' );
		$login_url = oc_page_url( 'vendor-login' );

		if ( ! isset( $_POST['oc_rp_nonce'] ) || ! wp_verify_nonce( $_POST['oc_rp_nonce'], self::ACTION_RESET_PASSWORD ) ) {
			$this->redirect( $login_url, __( 'Security check failed. Please request a fresh link.', 'owambe-connect-core' ) );
		}

		$login = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';
		$key   = isset( $_POST['key'] )   ? sanitize_text_field( wp_unslash( $_POST['key'] ) )   : '';
		$pass1 = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
		$pass2 = isset( $_POST['pass2'] ) ? (string) wp_unslash( $_POST['pass2'] ) : '';

		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			$this->redirect( oc_page_url( 'forgot-password' ), __( 'This reset link has expired or is invalid. Please request a fresh one.', 'owambe-connect-core' ) );
		}

		if ( strlen( $pass1 ) < 8 ) {
			$this->redirect(
				add_query_arg( [ 'key' => $key, 'login' => rawurlencode( $login ) ], $reset_url ),
				__( 'Password must be at least 8 characters.', 'owambe-connect-core' )
			);
		}
		if ( $pass1 !== $pass2 ) {
			$this->redirect(
				add_query_arg( [ 'key' => $key, 'login' => rawurlencode( $login ) ], $reset_url ),
				__( 'The two password fields don\'t match.', 'owambe-connect-core' )
			);
		}

		reset_password( $user, $pass1 );
		$this->redirect( $login_url, '', __( 'Password updated — please log in with your new password.', 'owambe-connect-core' ) );
	}

	/** Send wp_lostpassword_url() to our branded page instead of /wp-login.php. */
	public function filter_lostpassword_url( $url, $redirect = '' ) {
		$dest = oc_page_url( 'forgot-password' );
		if ( $redirect ) {
			$dest = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $dest );
		}
		return $dest;
	}

	/**
	 * Intercept every front-end hit on /wp-login.php and bounce to the
	 * matching branded page. Runs on login_init (priority 1) BEFORE any
	 * WP-side rendering so users never see the default WP auth screens.
	 *
	 * Exemptions:
	 *   - POST submissions to the real wp-login form (so admins can still
	 *     sign in via direct /wp-login.php access).
	 *   - The interim-login modal Gmail / oEmbed uses to renew sessions.
	 *   - logout / postpass / confirmaction (one-time-key) actions — these
	 *     need to run on wp-login.php to fire core hooks, and they redirect
	 *     onward themselves.
	 */
	public function redirect_wp_login_to_branded() {
		// Only intercept when the user is hitting wp-login.php directly.
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( (string) $_SERVER['SCRIPT_NAME'] ) : '';
		if ( 'wp-login.php' !== $script ) return;
		if ( ! empty( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) return;

		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		// Let WP core handle these — they need to run on wp-login.php.
		if ( in_array( $action, [ 'logout', 'postpass', 'confirmaction', 'confirm_admin_email' ], true ) ) {
			return;
		}
		if ( isset( $_GET['interim-login'] ) ) return;

		// Forgot-password form on wp-login.php → branded /forgot-password/.
		if ( 'lostpassword' === $action || 'retrievepassword' === $action ) {
			wp_safe_redirect( oc_page_url( 'forgot-password' ), 302 );
			exit;
		}
		// Reset form arriving with a key from a stale (pre-rewrite) email link
		// → forward the key + login to our branded reset page.
		if ( 'rp' === $action || 'resetpass' === $action ) {
			$key   = isset( $_GET['key'] )   ? sanitize_text_field( wp_unslash( $_GET['key'] ) )   : '';
			$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';
			$dest  = oc_page_url( 'reset-password' );
			if ( $key && $login ) {
				$dest = add_query_arg( [ 'key' => $key, 'login' => rawurlencode( $login ) ], $dest );
			}
			wp_safe_redirect( $dest, 302 );
			exit;
		}
		// New-user "set your password" flow uses the same key+login params.
		if ( 'register' === $action ) {
			wp_safe_redirect( oc_page_url( 'apply' ), 302 );
			exit;
		}
		// Default action (login form) — admins should still reach wp-admin
		// without seeing the bare WP-login form. Front-end visitors get our
		// branded /vendor-login/.
		if ( '' === $action || 'login' === $action ) {
			$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
			// If the redirect target is /wp-admin/, the user is most likely
			// an admin signing in — leave them on wp-login.php (otherwise
			// they'd land on a vendor login that only lets oc_vendor in).
			if ( $redirect && false !== strpos( $redirect, '/wp-admin' ) ) return;
			$dest = oc_page_url( 'vendor-login' );
			if ( $redirect ) {
				$dest = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $dest );
			}
			wp_safe_redirect( $dest, 302 );
			exit;
		}
	}

	/**
	 * After logging out, vendor users land on our branded /vendor-login/
	 * with a friendly "you've been signed out" message instead of the
	 * default /wp-login.php?loggedout=true screen.
	 */
	public function redirect_after_logout( $redirect_to, $requested, $user ) {
		if ( ! ( $user instanceof WP_User ) || user_can( $user, 'manage_options' ) ) {
			return $redirect_to;
		}
		$roles  = (array) $user->roles;
		$notice = rawurlencode( __( 'You\'ve been signed out.', 'owambe-connect-core' ) );
		if ( in_array( OC_ROLE, $roles, true ) ) {
			return add_query_arg( 'oc_notice', $notice, oc_page_url( 'vendor-login' ) );
		}
		if ( in_array( OC_CLIENT_ROLE, $roles, true ) ) {
			return add_query_arg( 'oc_notice', $notice, oc_page_url( 'client-login' ) );
		}
		return $redirect_to;
	}

	/**
	 * WordPress core builds its password-reset email URL via
	 * network_site_url("wp-login.php?action=rp&key=...&login=...", 'login').
	 * Rewrite that single URL pattern so any reset email sent via the WP
	 * core path also lands users on our branded page.
	 */
	public function rewrite_reset_url_in_emails( $url, $path, $scheme ) {
		if ( 'login' !== $scheme ) return $url;
		if ( strpos( (string) $path, 'wp-login.php' ) !== 0 ) return $url;
		if ( strpos( (string) $path, 'action=rp' ) === false ) return $url;
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! $query ) return $url;
		parse_str( $query, $args );
		if ( empty( $args['key'] ) || empty( $args['login'] ) ) return $url;
		return add_query_arg(
			[ 'key' => $args['key'], 'login' => $args['login'] ],
			oc_page_url( 'reset-password' )
		);
	}
}
