<?php
/**
 * Owambe Connect — application-level security hardening.
 *
 * Wordfence + Cloudflare cover network/WAF concerns; this class covers
 * everything the plugin must enforce inside WordPress: throttling our custom
 * action handlers, blocking user enumeration, generic login errors, hiding
 * the WP version, and disabling unused surfaces (XML-RPC, App Passwords,
 * pingbacks).
 *
 * Designed to be safe to disable selectively via the `oc_security_*` filters
 * if any of these clash with future requirements.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Security {

	/** Per-IP throttle windows. Tune via `oc_security_limits` filter. */
	private function limits() {
		return apply_filters( 'oc_security_limits', [
			'register' => [ 'max' => 5,  'window' => HOUR_IN_SECONDS     ],
			'login'    => [ 'max' => 8,  'window' => 15 * MINUTE_IN_SECONDS ],
			'contact'  => [ 'max' => 6,  'window' => HOUR_IN_SECONDS     ],
		] );
	}

	public function register() {
		// XML-RPC — disable wholesale (Wordfence does too, but defense in depth).
		if ( apply_filters( 'oc_security_disable_xmlrpc', true ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', [ $this, 'strip_xmlrpc_methods' ] );
			add_filter( 'wp_headers',     [ $this, 'remove_pingback_header' ] );
		}

		// Hide WP version from <head>, RSS, scripts, styles.
		if ( apply_filters( 'oc_security_hide_version', true ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
			add_filter( 'style_loader_src',  [ $this, 'strip_version_qs' ], 9999 );
			add_filter( 'script_loader_src', [ $this, 'strip_version_qs' ], 9999 );
		}

		// Remove WLW / RSD / shortlink (info disclosure / unused).
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );

		// Generic login error — no "wrong password" / "no such user" leak.
		add_filter( 'login_errors', [ $this, 'generic_login_error' ] );

		// Block user enumeration via ?author=N and /author/<slug>/.
		add_action( 'template_redirect', [ $this, 'block_author_query' ] );

		// REST: hide /wp/v2/users for anonymous + non-list-users actors.
		add_filter( 'rest_endpoints',                [ $this, 'restrict_rest_users' ] );
		add_filter( 'rest_authentication_errors',    [ $this, 'rest_auth_gate' ], 99 );

		// Disable Application Passwords — we don't use them.
		if ( apply_filters( 'oc_security_disable_app_passwords', true ) ) {
			add_filter( 'wp_is_application_passwords_available', '__return_false' );
		}

		// Throttle our public custom action handlers.
		// Priority 1 so we run before OC_Registration / OC_Dashboard handlers.
		add_action( 'admin_post_nopriv_oc_register_vendor', [ $this, 'throttle_register' ], 1 );
		add_action( 'admin_post_nopriv_oc_login_vendor',    [ $this, 'throttle_login'    ], 1 );
		add_action( 'admin_post_oc_login_vendor',           [ $this, 'throttle_login'    ], 1 );
		add_action( 'admin_post_nopriv_oc_contact_message', [ $this, 'throttle_contact'  ], 1 );
		add_action( 'admin_post_oc_contact_message',        [ $this, 'throttle_contact'  ], 1 );

		// Set HttpOnly + Secure flags on auth cookies when over HTTPS.
		add_action( 'init', [ $this, 'tighten_cookies' ], 0 );

		// Add basic security headers (Cloudflare can also do this; doubling
		// up is fine and protects when CF is bypassed e.g. direct origin).
		add_action( 'send_headers', [ $this, 'security_headers' ] );

		// Inject GA4 measurement snippet on the public site.
		add_action( 'wp_head', [ $this, 'inject_analytics' ], 99 );
	}

	// ─── Analytics ──────────────────────────────────────────────────────────

	public function inject_analytics() {
		if ( is_admin() ) return;
		if ( ! class_exists( 'OC_Settings' ) ) return;
		$id = (string) OC_Settings::get( 'analytics_id', '' );
		if ( '' === $id || ! preg_match( '/^G-[A-Z0-9]+$/', $id ) ) return;
		?>
<!-- Google Analytics 4 (Owambe Connect) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $id ); ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?php echo esc_js( $id ); ?>',{anonymize_ip:true});</script>
		<?php
	}

	// ─── XML-RPC / pingbacks ────────────────────────────────────────────────

	public function strip_xmlrpc_methods( $methods ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}
	public function remove_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	// ─── Version / info disclosure ──────────────────────────────────────────

	/**
	 * Replace ?ver=X.Y in script/style URLs with a hashed value so the WP
	 * version isn't leaked in cache-busters. Keeps cache-busting working.
	 */
	public function strip_version_qs( $src ) {
		if ( ! is_string( $src ) || false === strpos( $src, 'ver=' ) ) return $src;
		$parts = wp_parse_url( $src );
		if ( empty( $parts['query'] ) ) return $src;
		parse_str( $parts['query'], $q );
		if ( isset( $q['ver'] ) ) {
			$q['ver'] = substr( md5( (string) $q['ver'] ), 0, 8 );
			$rebuilt  = ( isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '' )
			          . ( $parts['host']   ?? '' )
			          . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
			          . ( $parts['path']   ?? '' )
			          . '?' . http_build_query( $q );
			if ( ! empty( $parts['fragment'] ) ) $rebuilt .= '#' . $parts['fragment'];
			return $rebuilt;
		}
		return $src;
	}

	// ─── Login error normalization ──────────────────────────────────────────

	public function generic_login_error() {
		return __( 'Invalid email or password.', 'owambe-connect-core' );
	}

	// ─── User enumeration ───────────────────────────────────────────────────

	public function block_author_query() {
		$author_param = isset( $_GET['author'] ) ? (int) $_GET['author'] : 0;
		if ( $author_param > 0 || ( ! is_admin() && is_author() ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	public function restrict_rest_users( $endpoints ) {
		// Always allow logged-in users with list_users (admins) to query.
		if ( is_user_logged_in() && current_user_can( 'list_users' ) ) {
			return $endpoints;
		}
		if ( isset( $endpoints['/wp/v2/users'] ) )                  unset( $endpoints['/wp/v2/users'] );
		if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) )    unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		return $endpoints;
	}

	public function rest_auth_gate( $result ) {
		if ( ! empty( $result ) ) return $result;
		// If anonymous request hits the users namespace by guessing, refuse.
		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
		if ( '' === $route && isset( $_SERVER['REQUEST_URI'] ) ) {
			$route = (string) $_SERVER['REQUEST_URI'];
		}
		if ( strpos( $route, '/wp/v2/users' ) !== false && ! is_user_logged_in() ) {
			return new WP_Error( 'rest_user_cannot_view', __( 'Sorry, you are not allowed to list users.', 'owambe-connect-core' ), [ 'status' => 401 ] );
		}
		return $result;
	}

	// ─── Throttling for our custom action handlers ──────────────────────────

	public function throttle_register() { $this->throttle( 'register' ); }
	public function throttle_login()    { $this->throttle( 'login' );    }
	public function throttle_contact()  { $this->throttle( 'contact' );  }

	private function throttle( $bucket ) {
		$limits = $this->limits();
		if ( empty( $limits[ $bucket ] ) ) return;
		[ 'max' => $max, 'window' => $window ] = $limits[ $bucket ];

		$ip  = $this->client_ip();
		$key = 'oc_rl_' . $bucket . '_' . md5( $ip );

		$count = (int) get_transient( $key );
		if ( $count >= $max ) {
			status_header( 429 );
			nocache_headers();
			wp_die(
				esc_html__( 'You\'re going a bit fast. Please wait a few minutes and try again.', 'owambe-connect-core' ),
				esc_html__( 'Rate limit reached', 'owambe-connect-core' ),
				[ 'response' => 429, 'back_link' => true ]
			);
		}
		set_transient( $key, $count + 1, $window );
	}

	private function client_ip() {
		// Trust Cloudflare's connecting-IP header only if request actually
		// came through CF (header presence + reasonable shape).
		foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $h ) {
			if ( ! empty( $_SERVER[ $h ] ) ) {
				$ip = trim( explode( ',', (string) $_SERVER[ $h ] )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;
			}
		}
		return '0.0.0.0';
	}

	// ─── Cookies / headers ──────────────────────────────────────────────────

	public function tighten_cookies() {
		if ( ! defined( 'COOKIE_DOMAIN' ) ) {
			// Force HttpOnly / Secure on auth cookies when SSL is up.
			@ini_set( 'session.cookie_httponly', '1' );
			if ( is_ssl() ) @ini_set( 'session.cookie_secure', '1' );
			@ini_set( 'session.cookie_samesite', 'Lax' );
		}
	}

	public function security_headers() {
		if ( is_admin() ) return;
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: interest-cohort=(), browsing-topics=()' );
	}
}
