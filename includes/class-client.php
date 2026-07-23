<?php
/**
 * Client accounts: the oc_client role, client-page routing guards, and the
 * saved-vendors / recently-contacted storage that powers the client dashboard.
 *
 * Clients are event planners (not vendors). They sign in with Google
 * (OC_Google_Auth), save vendors they like, and see who they recently
 * contacted. This class owns:
 *
 *  - the OC_CLIENT_ROLE role (read-only, idempotent init registration)
 *  - template_redirect gating for /client-dashboard/ and /client-login/,
 *    plus the guard that keeps clients off the vendor dashboard (its
 *    create-on-save flow would silently promote them to vendors)
 *  - user-meta APIs: '_oc_saved_vendors' (int[]) and '_oc_recent_contacts'
 *    (array of [vendor_id, channel, ts], newest first, capped at 20)
 *  - the wp_ajax_oc_toggle_saved endpoint behind the heart buttons
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Client {

	/** AJAX action behind the save/heart buttons (logged-in only — no nopriv). */
	const ACTION_TOGGLE_SAVED = 'oc_toggle_saved';

	/** Nonce action; JS sends the value as POST field 'nonce' (OC_DATA.saved_nonce). */
	const NONCE_SAVED = 'oc_saved_nonce';

	/** User meta: array of saved vendor post IDs (newest first). */
	const META_SAVED = '_oc_saved_vendors';

	/** User meta: array of [vendor_id, channel, ts] rows (newest first). */
	const META_CONTACTS = '_oc_recent_contacts';

	/** Max recent-contact rows kept per user. */
	const CONTACTS_CAP = 20;

	/** Window inside which a repeat vendor+channel contact is not re-recorded. */
	const CONTACT_DEDUPE_WINDOW = HOUR_IN_SECONDS;

	/** admin-post actions for native (non-Google) client auth. */
	const ACTION_REGISTER = 'oc_client_register';
	const ACTION_LOGIN    = 'oc_client_login';

	public function register() {
		add_action( 'init', [ __CLASS__, 'register_role' ] );

		// Client-page routing (must run before headers are sent). Mirrors the
		// vendor guards in OC_Shortcodes::redirect_logged_out_from_dashboard().
		add_action( 'template_redirect', [ $this, 'gate_client_pages' ] );

		add_action( 'wp_ajax_' . self::ACTION_TOGGLE_SAVED, [ __CLASS__, 'ajax_toggle_saved' ] );

		// Native email/password client auth (for people without a Google account,
		// e.g. Yahoo/Outlook users). Both nopriv (form submit) and priv (defensive).
		add_action( 'admin_post_nopriv_' . self::ACTION_REGISTER, [ __CLASS__, 'handle_register' ] );
		add_action( 'admin_post_'        . self::ACTION_REGISTER, [ __CLASS__, 'handle_register' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION_LOGIN,    [ __CLASS__, 'handle_login' ] );
		add_action( 'admin_post_'        . self::ACTION_LOGIN,    [ __CLASS__, 'handle_login' ] );

		// Password reset is shared with vendors (OC_Dashboard). Route clients back
		// to the client login afterwards — this is also how a Google-only user
		// (random password) sets a real password so they can use email/password.
		add_filter( 'oc_reset_password_login_url', [ __CLASS__, 'filter_reset_login_url' ], 10, 2 );
	}

	/**
	 * After a branded password reset, send clients back to the client login page
	 * instead of the vendor login (OC_Dashboard's default). Vendors are left alone.
	 * Works for both native email/password clients and Google-OAuth clients who
	 * used "Forgot password" to set a usable password.
	 */
	public static function filter_reset_login_url( $url, $user ) {
		if ( $user instanceof WP_User
			&& in_array( OC_CLIENT_ROLE, (array) $user->roles, true )
			&& ! in_array( OC_ROLE, (array) $user->roles, true ) ) {
			return oc_page_url( 'client-login' );
		}
		return $url;
	}

	/**
	 * Idempotent role registration, cloned from OC_CPT_Manager::register_role().
	 *
	 * Clients only ever read the site and use frontend features driven by
	 * user meta — 'read' is the whole capability set. Administrators are
	 * deliberately left untouched: their access flows from manage_options,
	 * and there is no client cap that needs mirroring onto them (unlike
	 * OC_CAP_EDIT_OWN for vendors).
	 */
	public static function register_role() {
		if ( ! get_role( OC_CLIENT_ROLE ) ) {
			add_role( OC_CLIENT_ROLE, __( 'Client', 'owambe-connect-core' ), [
				'read' => true,
			] );
		}
	}

	/** True when the user's roles include OC_CLIENT_ROLE. Defaults to the current user. */
	public static function is_client( $user_id = 0 ) {
		$user = $user_id ? get_user_by( 'id', (int) $user_id ) : wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		return in_array( OC_CLIENT_ROLE, (array) $user->roles, true );
	}

	/** Canonical client dashboard URL. */
	public static function dashboard_url() {
		return oc_page_url( 'client-dashboard' );
	}

	/**
	 * template_redirect router for the client pages.
	 *
	 * 1. Logged-out visitor on /client-dashboard/ → /client-login/?redirect_to=…
	 * 2. Logged-in client on /client-login/ → /client-dashboard/
	 * 3. Logged-in client WITHOUT a vendor listing on /vendor-dashboard/ →
	 *    /client-dashboard/. This one is load-bearing: the vendor dashboard's
	 *    create-on-save flow (OC_Dashboard::run_save) silently promotes any
	 *    logged-in user who saves the form into a vendor. Clients must never
	 *    reach that form by accident.
	 */
	public function gate_client_pages() {
		if ( is_admin() || ! is_page() ) {
			return;
		}
		$current_id = (int) get_queried_object_id();
		if ( ! $current_id ) {
			return;
		}

		$dashboard_page = get_page_by_path( 'client-dashboard' );
		$dashboard_id   = $dashboard_page ? (int) $dashboard_page->ID : 0;

		// 1. Client dashboard requires login — bounce with a return path.
		if ( ! is_user_logged_in() ) {
			if ( $dashboard_id && $current_id === $dashboard_id ) {
				wp_safe_redirect( add_query_arg(
					'redirect_to',
					rawurlencode( self::dashboard_url() ),
					oc_page_url( 'client-login' )
				) );
				exit;
			}
			return; // Nothing else applies to logged-out visitors.
		}

		// 2. Signed-in clients don't need the login page again.
		if ( self::is_client() ) {
			$login_page = get_page_by_path( 'client-login' );
			if ( $login_page && $current_id === (int) $login_page->ID ) {
				wp_safe_redirect( self::dashboard_url() );
				exit;
			}
		}

		// 3. Keep vendor-less clients off the vendor dashboard (create-on-save
		// auto-promotion guard). Admins keep manage_options-based access and
		// are never rerouted, even if they also carry the client role.
		if ( self::is_client() && ! current_user_can( 'manage_options' ) ) {
			$vendor_dashboard = get_page_by_path( 'vendor-dashboard' );
			if ( $vendor_dashboard && $current_id === (int) $vendor_dashboard->ID && ! oc_get_current_vendor_post() ) {
				wp_safe_redirect( self::dashboard_url() );
				exit;
			}
		}
	}

	/* ───────────────────── Native email/password auth ──────────────────── */

	/**
	 * Redirect back to the client-login page with an error, preserving the
	 * sign-in vs create-account mode and any redirect_to round-trip. add_query_arg
	 * does not URL-encode, so values are rawurlencode()'d to match OC_Google_Auth.
	 */
	private static function bounce_to_login( $error = '', $mode = 'login' ) {
		$q = [];
		if ( 'register' === $mode ) {
			$q['mode'] = 'register';
		}
		if ( ! empty( $_POST['redirect_to'] ) ) {
			$q['redirect_to'] = rawurlencode( esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) );
		}
		if ( '' !== $error ) {
			$q['oc_error'] = rawurlencode( $error );
		}
		$url = oc_page_url( 'client-login' );
		wp_safe_redirect( $q ? add_query_arg( $q, $url ) : $url );
		exit;
	}

	/** Resolve the post-auth destination: honour a safe redirect_to, else dashboard. */
	private static function auth_destination( $default ) {
		if ( ! empty( $_POST['redirect_to'] ) ) {
			$maybe = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ), '' );
			if ( '' !== $maybe ) {
				return $maybe;
			}
		}
		return $default;
	}

	/**
	 * Create a client account from the native registration form and sign them in.
	 * Bypasses the users_can_register option deliberately: this is a first-party
	 * client sign-up, gated by nonce + validation, not open WP registration.
	 */
	public static function handle_register() {
		if ( ! isset( $_POST['oc_client_register_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oc_client_register_nonce'] ) ), self::ACTION_REGISTER ) ) {
			self::bounce_to_login( __( 'Security check failed. Please try again.', 'owambe-connect-core' ), 'register' );
		}

		// Already signed in — nothing to create.
		if ( is_user_logged_in() ) {
			wp_safe_redirect( self::dashboard_url() );
			exit;
		}

		$email = isset( $_POST['email'] )        ? sanitize_email( wp_unslash( $_POST['email'] ) )              : '';
		$login = isset( $_POST['username'] )     ? sanitize_user( wp_unslash( $_POST['username'] ), true )      : '';
		$name  = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) )  : '';
		$pass  = isset( $_POST['password'] )     ? (string) wp_unslash( $_POST['password'] )                    : '';
		$pass2 = isset( $_POST['password2'] )    ? (string) wp_unslash( $_POST['password2'] )                   : '';

		if ( ! is_email( $email ) ) {
			self::bounce_to_login( __( 'Please enter a valid email address.', 'owambe-connect-core' ), 'register' );
		}
		if ( strlen( $pass ) < 8 ) {
			self::bounce_to_login( __( 'Your password must be at least 8 characters.', 'owambe-connect-core' ), 'register' );
		}
		if ( $pass !== $pass2 ) {
			self::bounce_to_login( __( 'The two passwords do not match.', 'owambe-connect-core' ), 'register' );
		}
		// Already registered — could be a native OR a Google account on this email.
		// Send them to sign-in; if they only ever used Google, "Forgot password"
		// on that page lets them set a password (both methods then work).
		if ( email_exists( $email ) ) {
			self::bounce_to_login( __( 'That email is already registered. Please sign in — or use "Forgot password" to set a password.', 'owambe-connect-core' ), 'login' );
		}

		// Derive a username from the email when none was supplied, and guarantee
		// uniqueness by suffixing an incrementing number.
		if ( '' === $login ) {
			$parts = explode( '@', $email );
			$login = sanitize_user( $parts[0], true );
		}
		if ( '' === $login || username_exists( $login ) ) {
			$base = '' !== $login ? $login : 'client';
			$i    = 1;
			do {
				$login = $base . $i;
				$i++;
			} while ( username_exists( $login ) );
		}

		$user_id = wp_insert_user( [
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => $pass,
			'role'         => OC_CLIENT_ROLE,
			'display_name' => '' !== $name ? $name : $login,
		] );
		if ( is_wp_error( $user_id ) ) {
			self::bounce_to_login( __( 'We could not create your account. Please try again.', 'owambe-connect-core' ), 'register' );
		}

		do_action( 'oc_after_client_registered', $user_id );
		if ( class_exists( 'OC_Mail' ) && method_exists( 'OC_Mail', 'client_welcome' ) ) {
			OC_Mail::client_welcome( $user_id );
		}

		// Sign them straight in.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		wp_safe_redirect( self::auth_destination( self::dashboard_url() ) );
		exit;
	}

	/**
	 * Native email/password sign-in for clients. wp_signon accepts either the
	 * email or the username in 'user_login'. Vendors are role-routed to their
	 * own dashboard so this page works for any account.
	 */
	public static function handle_login() {
		if ( ! isset( $_POST['oc_client_login_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oc_client_login_nonce'] ) ), self::ACTION_LOGIN ) ) {
			self::bounce_to_login( __( 'Security check failed. Please try again.', 'owambe-connect-core' ), 'login' );
		}

		$creds = [
			'user_login'    => isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '',
			'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '',
			'remember'      => ! empty( $_POST['rememberme'] ),
		];
		$user = wp_signon( $creds, is_ssl() );
		if ( is_wp_error( $user ) ) {
			self::bounce_to_login( __( 'Invalid email or password.', 'owambe-connect-core' ), 'login' );
		}

		$default = ( $user instanceof WP_User && in_array( OC_ROLE, (array) $user->roles, true ) )
			? oc_page_url( 'vendor-dashboard' )
			: self::dashboard_url();

		wp_safe_redirect( self::auth_destination( $default ) );
		exit;
	}

	/* ─────────────────────────── Saved vendors ─────────────────────────── */

	/**
	 * Saved vendor IDs for a user, newest first.
	 *
	 * @return int[]
	 */
	public static function saved_vendors( $user_id ) {
		$saved = get_user_meta( (int) $user_id, self::META_SAVED, true );
		if ( ! is_array( $saved ) ) {
			return [];
		}
		return array_values( array_unique( array_filter( array_map( 'intval', $saved ) ) ) );
	}

	/** Hard cap on a client's saved list — bounds meta size and dashboard render. */
	const SAVED_CAP = 200;

	/**
	 * Toggle a vendor in the user's saved list.
	 *
	 * @return bool|string  true = now saved, false = now unsaved, 'full' = at the
	 *                       cap and this vendor wasn't already saved (not added).
	 */
	public static function toggle_saved( $user_id, $vendor_id ) {
		$user_id   = (int) $user_id;
		$vendor_id = (int) $vendor_id;
		$saved     = self::saved_vendors( $user_id );

		$index = array_search( $vendor_id, $saved, true );
		if ( false !== $index ) {
			unset( $saved[ $index ] );
			$now_saved = false;
		} else {
			// Cap the list so a scripted account can't grow unbounded user meta
			// (and a pathological dashboard render). Reject with an explicit
			// signal rather than silently dropping the oldest save.
			if ( count( $saved ) >= self::SAVED_CAP ) {
				return 'full';
			}
			array_unshift( $saved, $vendor_id );
			$now_saved = true;
		}

		update_user_meta( $user_id, self::META_SAVED, array_values( $saved ) );
		return $now_saved;
	}

	/**
	 * AJAX: toggle a saved vendor (heart button). Logged-in only — the button
	 * for logged-out visitors links to client-login instead of firing this.
	 *
	 * POST: nonce (from OC_DATA.saved_nonce, action 'oc_saved_nonce'), vendor_id.
	 * Success payload: { saved: bool } — the new state.
	 */
	public static function ajax_toggle_saved() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_SAVED ) ) {
			wp_send_json_error( [ 'message' => __( 'Your session expired. Please refresh the page and try again.', 'owambe-connect-core' ) ], 403 );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Please sign in to save vendors.', 'owambe-connect-core' ) ], 403 );
		}

		$vendor_id = isset( $_POST['vendor_id'] ) ? (int) $_POST['vendor_id'] : 0;
		$vendor    = $vendor_id ? get_post( $vendor_id ) : null;
		if ( ! $vendor || OC_CPT !== $vendor->post_type || OC_STATUS_APPROVED !== $vendor->post_status ) {
			wp_send_json_error( [ 'message' => __( 'Vendor not found.', 'owambe-connect-core' ) ] );
		}

		$saved = self::toggle_saved( get_current_user_id(), $vendor_id );
		if ( 'full' === $saved ) {
			wp_send_json_error( [ 'message' => sprintf( __( 'Your saved list is full (%d). Remove a few before adding more.', 'owambe-connect-core' ), self::SAVED_CAP ) ] );
		}
		wp_send_json_success( [ 'saved' => (bool) $saved ] );
	}

	/* ────────────────────────── Recent contacts ────────────────────────── */

	/**
	 * Recently contacted vendors, newest first, capped at CONTACTS_CAP.
	 *
	 * @return array[] Rows of [ 'vendor_id' => int, 'channel' => string, 'ts' => int ].
	 */
	public static function recent_contacts( $user_id ) {
		$rows = get_user_meta( (int) $user_id, self::META_CONTACTS, true );
		if ( ! is_array( $rows ) ) {
			return [];
		}
		$out = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['vendor_id'] ) ) {
				continue;
			}
			$out[] = [
				'vendor_id' => (int) $row['vendor_id'],
				'channel'   => isset( $row['channel'] ) ? (string) $row['channel'] : '',
				'ts'        => isset( $row['ts'] ) ? (int) $row['ts'] : 0,
			];
		}
		return array_slice( $out, 0, self::CONTACTS_CAP );
	}

	/**
	 * Record a contact click (fed by the W1 tracking beacon for logged-in
	 * clients). Repeat clicks on the same vendor + channel within an hour are
	 * ignored — a burst of WhatsApp taps is one contact, not five.
	 */
	public static function push_recent_contact( $user_id, $vendor_id, $channel ) {
		$user_id   = (int) $user_id;
		$vendor_id = (int) $vendor_id;
		$channel   = sanitize_key( $channel );
		if ( ! $user_id || ! $vendor_id ) {
			return;
		}

		$rows = self::recent_contacts( $user_id );
		$now  = time();

		foreach ( $rows as $row ) {
			if ( $row['vendor_id'] === $vendor_id && $row['channel'] === $channel
				&& ( $now - $row['ts'] ) < self::CONTACT_DEDUPE_WINDOW ) {
				return; // Duplicate within the window — keep the existing row.
			}
		}

		array_unshift( $rows, [
			'vendor_id' => $vendor_id,
			'channel'   => $channel,
			'ts'        => $now,
		] );

		update_user_meta( $user_id, self::META_CONTACTS, array_slice( $rows, 0, self::CONTACTS_CAP ) );
	}
}
