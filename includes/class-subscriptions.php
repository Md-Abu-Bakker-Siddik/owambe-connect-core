<?php
/**
 * Subscription tiers + Stripe subscription lifecycle.
 *
 * Defines the Professional / Elite / Premium plans, starts Stripe Checkout for
 * a chosen tier, opens the Billing Portal, and — driven by the signature-
 * verified webhook in OC_Stripe (which fires 'oc_stripe_event') — keeps each
 * user's subscription state (tier / status / renewal date) in sync when Stripe
 * reports a change. All state lives in user meta; Stripe is the source of truth.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Subscriptions {

	/** admin-post actions. */
	const ACTION_CHECKOUT = 'oc_start_checkout';
	const ACTION_PORTAL   = 'oc_billing_portal';

	/** User meta keys (Stripe is authoritative; these are a local mirror). */
	const META_CUSTOMER   = '_oc_stripe_customer_id';
	const META_SUB_ID     = '_oc_stripe_sub_id';
	const META_TIER       = '_oc_sub_tier';
	const META_STATUS     = '_oc_sub_status';
	const META_PERIOD_END = '_oc_sub_period_end';

	/** Stripe statuses that grant access. */
	const ACTIVE_STATUSES = [ 'active', 'trialing' ];

	public function register() {
		// React to every signature-verified Stripe event (see OC_Stripe::webhook).
		add_action( 'oc_stripe_event', [ $this, 'handle_stripe_event' ] );

		// Front-end (logged-in) actions.
		add_action( 'admin_post_' . self::ACTION_CHECKOUT, [ $this, 'handle_checkout' ] );
		add_action( 'admin_post_' . self::ACTION_PORTAL,   [ $this, 'handle_portal' ] );
	}

	// ─── Tier catalogue ─────────────────────────────────────────────────────

	/**
	 * The subscription catalogue. Price IDs come from Settings; the rest is
	 * presentation. Filter 'oc_subscription_tiers' to customise.
	 *
	 * @return array<string,array> slug => [ label, price_id, rank, features ].
	 */
	public static function tiers() {
		$tiers = [
			'professional' => [
				'label'    => __( 'Professional', 'owambe-connect-core' ),
				'price_id' => (string) oc_get_setting( 'stripe_price_professional', '' ),
				'rank'     => 1,
				'features' => [
					__( 'Verified vendor profile', 'owambe-connect-core' ),
					__( 'Unlimited enquiries', 'owambe-connect-core' ),
					__( 'Up to 10 gallery photos', 'owambe-connect-core' ),
				],
			],
			'elite' => [
				'label'    => __( 'Elite', 'owambe-connect-core' ),
				'price_id' => (string) oc_get_setting( 'stripe_price_elite', '' ),
				'rank'     => 2,
				'features' => [
					__( 'Everything in Professional', 'owambe-connect-core' ),
					__( 'Priority placement in search', 'owambe-connect-core' ),
					__( 'Up to 30 gallery photos', 'owambe-connect-core' ),
					__( 'Featured badge', 'owambe-connect-core' ),
				],
			],
			'premium' => [
				'label'    => __( 'Premium', 'owambe-connect-core' ),
				'price_id' => (string) oc_get_setting( 'stripe_price_premium', '' ),
				'rank'     => 3,
				'features' => [
					__( 'Everything in Elite', 'owambe-connect-core' ),
					__( 'Homepage spotlight', 'owambe-connect-core' ),
					__( 'Unlimited gallery photos', 'owambe-connect-core' ),
					__( 'Dedicated account support', 'owambe-connect-core' ),
				],
			],
		];

		return (array) apply_filters( 'oc_subscription_tiers', $tiers );
	}

	/** @return array|null The tier config for a slug, or null. */
	public static function get_tier( $slug ) {
		$tiers = self::tiers();
		return isset( $tiers[ $slug ] ) ? $tiers[ $slug ] : null;
	}

	/** Reverse lookup: Stripe Price ID → tier slug, or '' if not one of ours. */
	public static function tier_for_price( $price_id ) {
		$price_id = (string) $price_id;
		if ( '' === $price_id ) {
			return '';
		}
		foreach ( self::tiers() as $slug => $tier ) {
			if ( ! empty( $tier['price_id'] ) && hash_equals( (string) $tier['price_id'], $price_id ) ) {
				return $slug;
			}
		}
		return '';
	}

	// ─── Read a user's subscription ─────────────────────────────────────────

	/**
	 * @param int $user_id
	 * @return array{ tier:string, status:string, period_end:int, active:bool }
	 */
	public static function get_subscription( $user_id ) {
		$user_id = (int) $user_id;
		$status  = (string) get_user_meta( $user_id, self::META_STATUS, true );
		return [
			'tier'       => (string) get_user_meta( $user_id, self::META_TIER, true ),
			'status'     => $status,
			'period_end' => (int) get_user_meta( $user_id, self::META_PERIOD_END, true ),
			'active'     => in_array( $status, self::ACTIVE_STATUSES, true ),
		];
	}

	/** True when the user has an access-granting subscription (optionally ≥ a tier). */
	public static function is_active( $user_id, $min_tier = '' ) {
		$sub = self::get_subscription( $user_id );
		if ( ! $sub['active'] ) {
			return false;
		}
		if ( '' === $min_tier ) {
			return true;
		}
		$have = self::get_tier( $sub['tier'] );
		$need = self::get_tier( $min_tier );
		return $have && $need && (int) $have['rank'] >= (int) $need['rank'];
	}

	// ─── Checkout + Billing Portal ──────────────────────────────────────────

	/**
	 * Create a Stripe Checkout Session for a tier and return its hosted URL.
	 *
	 * @param string $tier_slug professional|elite|premium
	 * @param int    $user_id
	 * @return string|WP_Error Hosted checkout URL, or WP_Error.
	 */
	public static function create_checkout_session( $tier_slug, $user_id ) {
		$tier = self::get_tier( $tier_slug );
		if ( ! $tier || empty( $tier['price_id'] ) ) {
			return new WP_Error( 'oc_sub', __( 'That plan is not available right now.', 'owambe-connect-core' ) );
		}
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return new WP_Error( 'oc_sub', __( 'You must be signed in to subscribe.', 'owambe-connect-core' ) );
		}

		$return = self::return_url();
		$params = [
			'mode'                  => 'subscription',
			'line_items'            => [ [ 'price' => $tier['price_id'], 'quantity' => 1 ] ],
			'success_url'           => add_query_arg( [ 'oc_sub' => 'success', 'tier' => $tier_slug ], $return ),
			'cancel_url'            => add_query_arg( 'oc_sub', 'cancel', $return ),
			'client_reference_id'   => (string) $user->ID,
			'allow_promotion_codes' => 'true',
			// Stamp the WP user id on both the session and the subscription so the
			// webhook can map Stripe objects back to the account with certainty.
			'metadata'              => [ 'user_id' => (string) $user->ID, 'tier' => $tier_slug ],
			'subscription_data'     => [ 'metadata' => [ 'user_id' => (string) $user->ID, 'tier' => $tier_slug ] ],
		];

		// Reuse an existing Stripe customer if we already know one; else let
		// Stripe create one, pre-filled with the account email.
		$customer = (string) get_user_meta( $user->ID, self::META_CUSTOMER, true );
		if ( '' !== $customer ) {
			$params['customer'] = $customer;
		} else {
			$params['customer_email'] = $user->user_email;
		}

		$session = OC_Stripe::request( 'POST', 'checkout/sessions', $params );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		if ( empty( $session['url'] ) ) {
			return new WP_Error( 'oc_sub', __( 'Could not start checkout. Please try again.', 'owambe-connect-core' ) );
		}
		return (string) $session['url'];
	}

	/** admin-post: logged-in user picks a tier → redirect to Stripe Checkout. */
	public function handle_checkout() {
		if ( ! is_user_logged_in() ) {
			$this->bail( __( 'Please sign in to subscribe.', 'owambe-connect-core' ) );
		}
		check_admin_referer( self::ACTION_CHECKOUT );

		$tier = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : '';
		$url  = self::create_checkout_session( $tier, get_current_user_id() );
		if ( is_wp_error( $url ) ) {
			$this->bail( $url->get_error_message() );
		}
		wp_redirect( $url ); // Stripe-hosted URL — wp_safe_redirect would strip it.
		exit;
	}

	/** admin-post: open the Stripe Billing Portal for the current customer. */
	public function handle_portal() {
		if ( ! is_user_logged_in() ) {
			$this->bail( __( 'Please sign in.', 'owambe-connect-core' ) );
		}
		check_admin_referer( self::ACTION_PORTAL );

		$customer = (string) get_user_meta( get_current_user_id(), self::META_CUSTOMER, true );
		if ( '' === $customer ) {
			$this->bail( __( 'No billing account found yet.', 'owambe-connect-core' ) );
		}
		$session = OC_Stripe::request( 'POST', 'billing_portal/sessions', [
			'customer'   => $customer,
			'return_url' => self::return_url(),
		] );
		if ( is_wp_error( $session ) || empty( $session['url'] ) ) {
			$this->bail( __( 'Could not open the billing portal. Please try again.', 'owambe-connect-core' ) );
		}
		wp_redirect( (string) $session['url'] );
		exit;
	}

	// ─── Webhook: keep local state in sync with Stripe ──────────────────────

	/**
	 * Router for signature-verified Stripe events (fired by OC_Stripe::webhook).
	 *
	 * @param array $event Decoded Stripe event.
	 */
	public function handle_stripe_event( $event ) {
		$type   = isset( $event['type'] ) ? (string) $event['type'] : '';
		$object = ( isset( $event['data']['object'] ) && is_array( $event['data']['object'] ) ) ? $event['data']['object'] : [];

		switch ( $type ) {
			case 'checkout.session.completed':
				$this->on_checkout_completed( $object );
				break;

			case 'customer.subscription.created':
			case 'customer.subscription.updated':
				$user_id = $this->resolve_user( $object );
				if ( $user_id ) {
					$this->apply_subscription( $user_id, $object );
				}
				break;

			case 'customer.subscription.deleted':
				$user_id = $this->resolve_user( $object );
				if ( $user_id ) {
					$this->apply_subscription( $user_id, $object ); // status becomes 'canceled'
				}
				break;

			case 'invoice.payment_failed':
				$customer = isset( $object['customer'] ) ? (string) $object['customer'] : '';
				$user_id  = $customer ? $this->find_user_by_customer( $customer ) : 0;
				if ( $user_id ) {
					update_user_meta( $user_id, self::META_STATUS, 'past_due' );
					do_action( 'oc_subscription_updated', $user_id, self::get_subscription( $user_id ) );
				}
				break;
		}
	}

	/**
	 * checkout.session.completed — link the customer to the WP account, then
	 * hydrate the full subscription (the session alone lacks price/period).
	 *
	 * @param array $session
	 */
	private function on_checkout_completed( array $session ) {
		// Only subscription checkouts carry a subscription id.
		if ( isset( $session['mode'] ) && 'subscription' !== $session['mode'] ) {
			return;
		}

		$user_id = isset( $session['client_reference_id'] ) ? absint( $session['client_reference_id'] ) : 0;
		if ( ! $user_id && isset( $session['metadata']['user_id'] ) ) {
			$user_id = absint( $session['metadata']['user_id'] );
		}
		$customer = isset( $session['customer'] ) ? (string) $session['customer'] : '';
		if ( ! $user_id && $customer ) {
			$user_id = $this->find_user_by_customer( $customer );
		}
		if ( ! $user_id ) {
			return;
		}

		if ( $customer ) {
			update_user_meta( $user_id, self::META_CUSTOMER, $customer );
		}

		$sub_id = isset( $session['subscription'] ) ? (string) $session['subscription'] : '';
		if ( '' === $sub_id ) {
			return;
		}
		$subscription = OC_Stripe::request( 'GET', 'subscriptions/' . rawurlencode( $sub_id ) );
		if ( is_wp_error( $subscription ) || ! is_array( $subscription ) ) {
			return;
		}
		$this->apply_subscription( $user_id, $subscription );
	}

	/**
	 * Persist a Stripe subscription object onto the WP user.
	 *
	 * @param int   $user_id
	 * @param array $sub      Stripe subscription object.
	 */
	private function apply_subscription( $user_id, array $sub ) {
		$status   = isset( $sub['status'] ) ? sanitize_text_field( (string) $sub['status'] ) : '';
		$price_id = '';
		if ( isset( $sub['items']['data'][0]['price']['id'] ) ) {
			$price_id = (string) $sub['items']['data'][0]['price']['id'];
		} elseif ( isset( $sub['plan']['id'] ) ) {
			$price_id = (string) $sub['plan']['id'];
		}
		$tier       = self::tier_for_price( $price_id );
		$period_end = isset( $sub['current_period_end'] ) ? (int) $sub['current_period_end'] : 0;

		if ( isset( $sub['id'] ) ) {
			update_user_meta( $user_id, self::META_SUB_ID, sanitize_text_field( (string) $sub['id'] ) );
		}
		if ( isset( $sub['customer'] ) ) {
			update_user_meta( $user_id, self::META_CUSTOMER, sanitize_text_field( (string) $sub['customer'] ) );
		}
		update_user_meta( $user_id, self::META_STATUS, $status );
		update_user_meta( $user_id, self::META_TIER, $tier );
		update_user_meta( $user_id, self::META_PERIOD_END, $period_end );

		/**
		 * Fires after a user's subscription state changes (grant/revoke perks here).
		 *
		 * @param int   $user_id
		 * @param array $subscription Local snapshot from get_subscription().
		 */
		do_action( 'oc_subscription_updated', $user_id, self::get_subscription( $user_id ) );
	}

	/** Map a Stripe subscription object to a WP user id (metadata → customer). */
	private function resolve_user( array $sub ) {
		if ( isset( $sub['metadata']['user_id'] ) && absint( $sub['metadata']['user_id'] ) ) {
			return absint( $sub['metadata']['user_id'] );
		}
		$customer = isset( $sub['customer'] ) ? (string) $sub['customer'] : '';
		return $customer ? $this->find_user_by_customer( $customer ) : 0;
	}

	/** @return int WP user id linked to a Stripe customer, or 0. */
	private function find_user_by_customer( $customer_id ) {
		$ids = get_users( [
			'meta_key'   => self::META_CUSTOMER,
			'meta_value' => (string) $customer_id,
			'number'     => 1,
			'fields'     => 'ID',
		] );
		return $ids ? (int) $ids[0] : 0;
	}

	// ─── Helpers ────────────────────────────────────────────────────────────

	/** Where checkout / portal return to (vendor dashboard by default). */
	private static function return_url() {
		$url = function_exists( 'oc_page_url' ) ? oc_page_url( 'vendor-dashboard' ) : home_url( '/' );
		return apply_filters( 'oc_subscription_return_url', $url ?: home_url( '/' ) );
	}

	/** Redirect back with an error flag. Always exits. */
	private function bail( $message ) {
		wp_safe_redirect( add_query_arg( [ 'oc_sub' => 'error', 'oc_sub_msg' => rawurlencode( $message ) ], self::return_url() ) );
		exit;
	}
}
