<?php
/**
 * Stripe scaffolding — API wrapper, webhook receiver, connection test.
 *
 * Week 1 foundations only: a thin authenticated wrapper around the Stripe
 * REST API (no SDK — pure WP HTTP API, same approach as OC_Mailchimp), a
 * signature-verified webhook endpoint at oc/v1/stripe-webhook that fans
 * events out via the 'oc_stripe_event' action, and an admin "test
 * connection" handler for the Settings page. Checkout / subscription
 * logic lands in Week 4 and builds on top of this class.
 *
 * The secret key is read from settings on every call and is NEVER echoed,
 * logged or included in any error message.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Stripe {

	const API_BASE    = 'https://api.stripe.com/v1/';
	const API_VERSION = '2024-06-20';
	const ACTION_TEST = 'oc_stripe_test';

	/**
	 * Max allowed clock drift between Stripe's signature timestamp and us
	 * (mirrors the official SDK's default tolerance).
	 */
	const SIGNATURE_TOLERANCE = 300;

	public function register() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		// Settings page "Test connection" button.
		add_action( 'admin_post_' . self::ACTION_TEST, [ $this, 'test_connection' ] );
	}

	public function register_routes() {
		register_rest_route( 'oc/v1', '/stripe-webhook', [
			'methods'  => 'POST',
			'callback' => [ $this, 'webhook' ],
			// Deliberately open: Stripe can't authenticate as a WP user.
			// The real permission check is the Stripe-Signature HMAC
			// verification inside webhook() — unsigned or stale requests
			// are rejected with a 400 before anything is processed.
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * True when a Stripe secret key has been saved in Settings.
	 */
	public static function is_configured() {
		return (bool) oc_get_setting( 'stripe_sk' );
	}

	/**
	 * Minimal Stripe API client.
	 *
	 * @param string $method HTTP method (GET/POST/DELETE — case-insensitive).
	 * @param string $path   Path under /v1, e.g. 'balance' or '/customers/cus_x'.
	 * @param array  $body   Request params; form-encoded for POST/DELETE.
	 * @return array|WP_Error Decoded JSON on 2xx, WP_Error 'stripe_error' otherwise.
	 */
	public static function request( $method, $path, $body = [] ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'stripe_error', __( 'Stripe secret key is not configured.', 'owambe-connect-core' ) );
		}

		$method = strtoupper( (string) $method );

		$args = [
			'method'  => $method,
			'timeout' => 15,
			'headers' => [
				'Authorization'  => 'Bearer ' . oc_get_setting( 'stripe_sk' ),
				'Stripe-Version' => self::API_VERSION,
			],
		];

		if ( ! empty( $body ) && in_array( $method, [ 'POST', 'DELETE' ], true ) ) {
			$args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
			$args['body'] = http_build_query( $body );
		}

		$response = wp_remote_request( self::API_BASE . ltrim( (string) $path, '/' ), $args );

		// Transport failure (DNS, timeout, …) — message contains no secrets.
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'stripe_error', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 ) {
			return is_array( $data ) ? $data : [];
		}

		// Prefer Stripe's own human-readable error message. It never echoes
		// back the API key, so it is safe to surface in admin notices.
		$message = isset( $data['error']['message'] ) && is_string( $data['error']['message'] )
			? $data['error']['message']
			: sprintf( 'Stripe API error (HTTP %d).', $code );

		return new WP_Error( 'stripe_error', $message );
	}

	/**
	 * Webhook receiver — verifies the Stripe-Signature header (HMAC-SHA256
	 * over "{timestamp}.{raw body}") before trusting the payload, then hands
	 * the event to listeners via do_action( 'oc_stripe_event', $event ).
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function webhook( $request ) {
		$body   = $request->get_body();
		$sig    = (string) $request->get_header( 'stripe-signature' );
		$secret = (string) oc_get_setting( 'stripe_webhook_secret' );

		if ( '' === $secret || '' === $sig ) {
			return new WP_REST_Response( [ 'error' => 'missing signature' ], 400 );
		}

		// Header shape: "t=1700000000,v1=abc…,v1=def…,v0=…" — collect the
		// timestamp and every v1 candidate (Stripe sends multiple v1 values
		// while a webhook secret is being rolled).
		$timestamp  = '';
		$signatures = [];
		foreach ( explode( ',', $sig ) as $pair ) {
			$kv = explode( '=', trim( $pair ), 2 );
			if ( 2 !== count( $kv ) ) {
				continue;
			}
			if ( 't' === $kv[0] ) {
				$timestamp = $kv[1];
			} elseif ( 'v1' === $kv[0] ) {
				$signatures[] = $kv[1];
			}
		}

		$valid = false;
		if ( '' !== $timestamp && ! empty( $signatures ) ) {
			$expected = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
			foreach ( $signatures as $candidate ) {
				if ( hash_equals( $expected, $candidate ) ) {
					$valid = true;
					break;
				}
			}
			// Replay protection: reject signatures older/newer than 5 minutes.
			if ( $valid && abs( time() - (int) $timestamp ) > self::SIGNATURE_TOLERANCE ) {
				$valid = false;
			}
		}

		if ( ! $valid ) {
			return new WP_REST_Response( [ 'error' => 'invalid signature' ], 400 );
		}

		$event = json_decode( $body, true );
		if ( ! is_array( $event ) ) {
			$event = [];
		}

		/**
		 * Fires for every signature-verified Stripe event. Week 4 checkout /
		 * subscription handlers subscribe here.
		 *
		 * @param array $event Decoded Stripe event payload.
		 */
		do_action( 'oc_stripe_event', $event );

		if ( function_exists( 'oc_debug_log' ) ) {
			// $force: webhook requests are unauthenticated, so the normal
			// admin+debug gate would suppress this entirely.
			oc_debug_log( 'stripe event ' . ( isset( $event['type'] ) ? (string) $event['type'] : 'unknown' ), [], true );
		}

		return new WP_REST_Response( [ 'received' => true ], 200 );
	}

	/**
	 * Settings page "Test connection" — pings GET /v1/balance with the saved
	 * secret key and redirects back with a result flag.
	 */
	public function test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'owambe-connect-core' ) );
		}
		check_admin_referer( self::ACTION_TEST );

		$settings_url = admin_url( 'edit.php?post_type=' . OC_CPT . '&page=oc-settings' );

		$result = self::request( 'GET', 'balance' );

		if ( is_wp_error( $result ) ) {
			// Short reason only — request() never puts the key in a message.
			$reason = substr( $result->get_error_message(), 0, 160 );
			wp_safe_redirect( add_query_arg( [
				'oc_stripe'     => 'fail',
				'oc_stripe_msg' => rawurlencode( $reason ),
			], $settings_url ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'oc_stripe', 'ok', $settings_url ) );
		exit;
	}
}
