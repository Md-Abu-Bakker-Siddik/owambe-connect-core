<?php
/**
 * Google Identity Services sign-in ("Sign in with Google").
 *
 * Renders the GIS button (client login, vendor login) and handles the
 * credential callback via admin-post — never wp-login.php, which Phase 1
 * intercepts. The ID token is verified server-side against Google's
 * tokeninfo endpoint and FAILS CLOSED: any network error, non-200 response
 * or claim mismatch rejects the sign-in. Existing accounts are linked by
 * email; new users are created as clients (OC_CLIENT_ROLE).
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Google_Auth {

	const ACTION = 'oc_google_auth';

	/** Google-sub user meta key (stable account link, survives email changes). */
	const META_SUB = '_oc_google_sub';

	/** Per-IP throttle: 10 attempts per 15 minutes. */
	const THROTTLE_MAX    = 10;
	const THROTTLE_WINDOW = 15 * MINUTE_IN_SECONDS;

	/** Print the GSI loader script at most once per page. */
	private static $script_printed = false;

	public function register() {
		add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'handle' ] );
		add_action( 'admin_post_'        . self::ACTION, [ $this, 'handle' ] );
		// Clean, query-less callback URL for Google's redirect_uri (admin-post.php
		// needs a ?action= query string, which Google's redirect-URI matching
		// rejects). Same handler; it verifies the token and redirects itself.
		add_action( 'rest_api_init', function () {
			register_rest_route( 'oc/v1', '/google-login', [
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => '__return_true',
			] );
		} );
	}

	// ─── Callback handler ───────────────────────────────────────────────────

	public function handle() {
		// 1) Google double-submit CSRF cookie: POST token must match cookie.
		//    Checked BEFORE the throttle so random cross-site probes (which can't
		//    carry the victim's g_csrf_token cookie) never consume throttle slots.
		$credential = isset( $_POST['credential'] )   ? trim( (string) wp_unslash( $_POST['credential'] ) )   : '';
		$csrf_post  = isset( $_POST['g_csrf_token'] ) ? (string) wp_unslash( $_POST['g_csrf_token'] )         : '';
		$csrf_cookie= isset( $_COOKIE['g_csrf_token'] ) ? (string) wp_unslash( $_COOKIE['g_csrf_token'] )     : '';

		if ( '' === $credential || '' === $csrf_post || '' === $csrf_cookie || ! hash_equals( $csrf_cookie, $csrf_post ) ) {
			$this->fail();
		}

		// 2) Local per-IP throttle (GIS posts here cross-site, so the shared
		//    nonce throttles don't apply — we keep our own bucket, keyed on a
		//    trustworthy IP so it can't be spoofed onto a victim's bucket).
		$this->throttle();

		// 3) Verify the ID token server-side. FAIL CLOSED on any doubt.
		$claims = $this->verify_id_token( $credential );
		if ( ! $claims ) {
			$this->fail();
		}

		$email      = $claims['email'];
		$sub        = $claims['sub'];
		$name       = $claims['name'];
		$given_name = $claims['given_name'];

		// 4) Find the account: Google sub first, then link by email, else create.
		$user_id = $this->find_user_by_sub( $sub );

		if ( ! $user_id ) {
			$existing = email_exists( $email );
			if ( $existing ) {
				// LINK — but only for accounts that legitimately belong to the
				// public marketplace (client/vendor) OR that previously opted
				// into Google. SECURITY: this is a nopriv endpoint; auto-linking
				// ANY matching verified email would let whoever controls that
				// Google mailbox log in as an administrator/editor whose WP
				// account happens to use the same address — bypassing the WP
				// password and any login-hardening plugin. wp_set_auth_cookie()
				// skips the authenticate filter chain, so 2FA there wouldn't
				// catch it. Privileged / non-marketplace accounts must link
				// Google from an already-authenticated session instead.
				$existing   = (int) $existing;
				$u          = get_userdata( $existing );
				$roles      = $u ? (array) $u->roles : [];
				$is_market  = in_array( OC_CLIENT_ROLE, $roles, true ) || in_array( OC_ROLE, $roles, true );
				$prev_link  = '' !== (string) get_user_meta( $existing, self::META_SUB, true );
				$privileged = ! $u || user_can( $u, 'manage_options' ) || user_can( $u, 'edit_others_posts' );

				if ( $privileged || ( ! $is_market && ! $prev_link ) ) {
					$this->fail( __( 'This email already has an account. Please sign in with your email and password.', 'owambe-connect-core' ) );
				}

				$user_id = $existing;
				update_user_meta( $user_id, self::META_SUB, $sub );
			} else {
				// CREATE requires T&C consent — the sign-in gate drops this cookie
				// only after the visitor ticks "I accept the Terms & Conditions".
				// Existing users signing in are unaffected (they never reach here).
				if ( empty( $_COOKIE['oc_terms_accepted'] ) ) {
					$this->fail( __( 'Please tick "I accept the Terms & Conditions" before continuing with Google.', 'owambe-connect-core' ) );
				}

				// CREATE: brand-new client account with a random password.
				$args = [
					'user_login' => $email,
					'user_email' => $email,
					'user_pass'  => wp_generate_password( 24 ),
					'role'       => OC_CLIENT_ROLE,
				];
				$display = $name ?: $given_name;
				if ( '' !== $display )    $args['display_name'] = $display;
				if ( '' !== $given_name ) $args['first_name']   = $given_name;

				$user_id = wp_insert_user( $args );
				if ( is_wp_error( $user_id ) ) {
					$this->fail();
				}
				update_user_meta( $user_id, self::META_SUB, $sub );
				update_user_meta( $user_id, '_oc_terms_accepted', time() );

				do_action( 'oc_after_client_registered', $user_id );

				if ( class_exists( 'OC_Mail' ) && method_exists( 'OC_Mail', 'client_welcome' ) ) {
					OC_Mail::client_welcome( $user_id );
				}
			}
		}

		// 5) Log them in.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		// 6) Redirect: explicit redirect_to first (arrives via the login_uri
		//    query string or the POST body), else role-routed dashboard.
		$requested = '';
		if ( isset( $_POST['redirect_to'] ) ) {
			$requested = (string) wp_unslash( $_POST['redirect_to'] );
		} elseif ( isset( $_GET['redirect_to'] ) ) {
			$requested = (string) wp_unslash( $_GET['redirect_to'] );
		}
		$redirect = $requested ? wp_validate_redirect( $requested, '' ) : '';

		if ( '' === $redirect ) {
			$user = get_userdata( $user_id );
			if ( $user && in_array( OC_ROLE, (array) $user->roles, true ) ) {
				$redirect = oc_page_url( 'vendor-dashboard' );
			} elseif ( class_exists( 'OC_Client' ) ) {
				$redirect = OC_Client::dashboard_url();
			} else {
				$redirect = home_url( '/' );
			}
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	// ─── Token verification (fail closed) ───────────────────────────────────

	/**
	 * Verify a GIS ID token against Google's tokeninfo endpoint.
	 *
	 * Unlike oc_verify_recaptcha() this FAILS CLOSED: an unreachable Google,
	 * a non-200 response or any missing/mismatched claim rejects the login.
	 *
	 * @param string $credential  The raw ID token (JWT) posted by GIS.
	 * @return array|false  [ 'email', 'sub', 'name', 'given_name' ] or false.
	 */
	private function verify_id_token( $credential ) {
		$client_id = (string) oc_get_setting( 'google_client_id', '' );
		if ( '' === $client_id ) return false;

		$res = wp_remote_get(
			'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode( $credential ),
			[ 'timeout' => 10 ]
		);
		if ( is_wp_error( $res ) ) return false;
		if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) return false;

		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $body ) ) return false;

		// Token must be minted for OUR client ID and still be valid.
		if ( ! isset( $body['aud'] ) || ! hash_equals( $client_id, (string) $body['aud'] ) ) return false;
		if ( empty( $body['exp'] ) || (int) $body['exp'] <= time() ) return false;

		// tokeninfo returns booleans as strings; accept either shape.
		$verified = $body['email_verified'] ?? false;
		if ( true !== $verified && 'true' !== $verified ) return false;

		$email = isset( $body['email'] ) ? sanitize_email( (string) $body['email'] ) : '';
		$sub   = isset( $body['sub'] )   ? sanitize_text_field( (string) $body['sub'] ) : '';
		if ( ! is_email( $email ) || '' === $sub ) return false;

		return [
			'email'      => $email,
			'sub'        => $sub,
			'name'       => isset( $body['name'] )       ? sanitize_text_field( (string) $body['name'] )       : '',
			'given_name' => isset( $body['given_name'] ) ? sanitize_text_field( (string) $body['given_name'] ) : '',
		];
	}

	/** @return int User ID previously linked to this Google sub, or 0. */
	private function find_user_by_sub( $sub ) {
		$ids = get_users( [
			'meta_key'   => self::META_SUB,
			'meta_value' => $sub,
			'number'     => 1,
			'fields'     => 'ID',
		] );
		return $ids ? (int) $ids[0] : 0;
	}

	// ─── Throttle / error handling ──────────────────────────────────────────

	private function throttle() {
		$max    = (int) apply_filters( 'oc_google_auth_throttle_max',    self::THROTTLE_MAX );
		$window = (int) apply_filters( 'oc_google_auth_throttle_window', self::THROTTLE_WINDOW );

		$key   = 'oc_rl_google_' . md5( oc_client_ip() );
		$count = (int) get_transient( $key );
		if ( $count >= $max ) {
			$this->fail( __( 'Too many sign-in attempts. Please wait a few minutes and try again.', 'owambe-connect-core' ) );
		}
		set_transient( $key, $count + 1, $window );
	}

	/**
	 * Bail out with a generic message. Never echoes token details — Google
	 * errors all collapse into one user-facing string.
	 */
	private function fail( $message = '' ) {
		if ( '' === $message ) {
			$message = __( 'Google sign-in didn\'t work. Please try again, or log in with your email and password.', 'owambe-connect-core' );
		}
		wp_safe_redirect( add_query_arg( 'oc_error', rawurlencode( $message ), oc_page_url( 'client-login' ) ) );
		exit;
	}

	// ─── Public API (templates call these) ──────────────────────────────────

	/** True when a Google Client ID has been configured in settings. */
	public static function is_configured() {
		return '' !== (string) oc_get_setting( 'google_client_id', '' );
	}

	/**
	 * GIS button markup. Prints the loader script once per page.
	 *
	 * @param string $redirect_to  Where to send the user after sign-in
	 *                             (validated via wp_validate_redirect).
	 * @return string
	 */
	public static function button_html( $redirect_to = '' ) {
		if ( ! self::is_configured() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p class="oc-google-signin oc-google-signin--note" style="margin:12px 0;padding:10px 14px;border:1px dashed #c9a86a;border-radius:8px;background:#fdf8ef;color:#6b5a3e;font-size:14px;">'
					. esc_html__( 'Google sign-in is not active yet: add your Google Client ID in Owambe Settings. Only administrators see this note.', 'owambe-connect-core' )
					. '</p>';
			}
			return '';
		}

		$client_id = (string) oc_get_setting( 'google_client_id', '' );
		// Clean, query-less callback URL (REST endpoint) — register THIS exact
		// value as an "Authorised redirect URI" in Google Cloud Console. Google
		// requires an exact match and dislikes query strings, so admin-post.php
		// (?action=…) fails. The handler routes users by role after login.
		$login_uri = rest_url( 'oc/v1/google-login' );

		$terms_url   = function_exists( 'oc_client_terms_url' ) ? oc_client_terms_url() : ( function_exists( 'oc_page_url' ) ? oc_page_url( 'terms' ) : '#' );
		$privacy_url = function_exists( 'oc_page_url' ) ? oc_page_url( 'privacy' ) : '#';
		// Terms gate: the GIS button stays disabled until the visitor accepts the T&C.
		// Ticking the box also drops a short-lived cookie that handle() re-checks
		// server-side before creating a brand-new account.
		$html  = '<div class="oc-google-signin oc-google-gate oc-google-gate--locked" data-oc-google-gate>';
		$html .= '<label class="oc-checkbox oc-google-gate__terms"><input type="checkbox" data-oc-google-terms /><span>' . wp_kses( sprintf( __( 'I accept the <a href="%1$s" target="_blank" rel="noopener">Terms &amp; Conditions</a> and <a href="%2$s" target="_blank" rel="noopener">Privacy Policy</a>.', 'owambe-connect-core' ), esc_url( $terms_url ), esc_url( $privacy_url ) ), [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] ) . '</span></label>';
		$html .= '<div class="oc-google-gate__btn">';
		// ux_mode=redirect is REQUIRED with data-login_uri: it makes GSI do a
		// full-page redirect that POSTs the credential to login_uri. Without it
		// GSI defaults to popup mode, tries to postMessage the token to a null
		// opener, and dies on accounts.google.com/gsi/transform (blank page).
		$html .= '<div id="g_id_onload"'
			. ' data-client_id="' . esc_attr( $client_id ) . '"'
			. ' data-login_uri="' . esc_url( $login_uri ) . '"'
			. ' data-ux_mode="redirect"'
			. ' data-auto_prompt="false"></div>';
		$html .= '<div class="g_id_signin"'
			. ' data-type="standard"'
			. ' data-theme="outline"'
			. ' data-size="large"'
			. ' data-shape="pill"'
			. ' data-width="340"'
			. ' data-locale="en"'
			. ' data-logo_alignment="center"'
			. ' data-text="continue_with"></div>';
		$html .= '</div>'; // .oc-google-gate__btn
		$html .= '<p class="oc-google-gate__hint">' . esc_html__( 'Please accept the Terms & Conditions to continue with Google.', 'owambe-connect-core' ) . '</p>';
		$html .= '</div>'; // .oc-google-signin

		if ( ! self::$script_printed ) {
			self::$script_printed = true;
			$html .= '<style>.oc-google-gate__terms{display:flex;gap:8px;align-items:flex-start;justify-content:center;text-align:left;font-size:13px;line-height:1.4;margin:0 auto 10px;max-width:340px;}.oc-google-gate__btn{transition:opacity .15s ease;}.oc-google-gate--locked .oc-google-gate__btn{opacity:.45;pointer-events:none;}.oc-google-gate__hint{display:none;color:#6B6361;font-size:12.5px;margin:8px 0 0;}.oc-google-gate--locked .oc-google-gate__hint{display:block;}</style>';
			$html .= '<script>(function(){function wire(box){var cb=box.querySelector("[data-oc-google-terms]");if(!cb)return;function sync(){var ok=cb.checked;box.classList.toggle("oc-google-gate--locked",!ok);document.cookie="oc_terms_accepted="+(ok?"1":"")+"; path=/; max-age="+(ok?1800:0)+"; samesite=lax";}cb.addEventListener("change",sync);sync();}function init(){var b=document.querySelectorAll("[data-oc-google-gate]");for(var i=0;i<b.length;i++){wire(b[i]);}}if(document.readyState!=="loading"){init();}else{document.addEventListener("DOMContentLoaded",init);}})();</script>';
			// Force the widget language via the script's hl param — GSI reads this
			// (and often ignores data-locale), otherwise it falls back to the
			// visitor's browser/Google language (e.g. Bengali).
			$html .= '<script src="' . esc_url( 'https://accounts.google.com/gsi/client?hl=en' ) . '" async defer></script>';
		}

		return $html;
	}
}
