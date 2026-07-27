<?php
/**
 * Vendor (oc_vendor CPT) subscription layer on top of Stripe.
 *
 * - Creates subscription Checkout Sessions for the Professional / Elite /
 *   Premium tiers (price IDs from Settings; catalogue is tiers() below).
 * - Consumes the signature-verified webhook (OC_Stripe fires 'oc_stripe_event')
 *   and mirrors the subscription state onto the VENDOR POST meta:
 *     _oc_sub_plan, _oc_sub_status, _oc_sub_stripe_customer, _oc_sub_stripe_sub
 * - Grants a free subscription period on approval: 12 months for founding
 *   vendors (_oc_founding_vendor), 3 months for new ones. The remaining free
 *   time is passed to Stripe as the subscription trial, so subscribing early
 *   never shortens the free window.
 *
 * Stripe is the source of truth for paid state; the post meta is a local mirror.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Vendor_Subscription {

	/** admin-post action to start a subscription checkout. */
	const ACTION = 'oc_vendor_subscribe';

	/** Vendor post-meta keys (as specified). */
	const META_PLAN       = '_oc_sub_plan';
	const META_STATUS     = '_oc_sub_status';
	const META_CUSTOMER   = '_oc_sub_stripe_customer';
	const META_SUB        = '_oc_sub_stripe_sub';
	const META_FREE_UNTIL = '_oc_free_until'; // plan-specified key (§5.4 / §9.1)

	/** Free-period lengths, in months. */
	const FOUNDING_MONTHS = 12;
	const NEW_MONTHS      = 3;

	/** Statuses that grant access. */
	const ACTIVE_STATUSES = [ 'active', 'trialing' ];

	public function register() {
		add_action( 'oc_stripe_event', [ $this, 'handle_event' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_checkout' ] );

		// Grant the free period when a vendor becomes approved (status → publish).
		add_action( 'transition_post_status', [ $this, 'maybe_start_free_period' ], 10, 3 );

		// Read-only subscription status line at the top of the dashboard Overview.
		add_action( 'oc_vendor_dashboard_overview', [ $this, 'render_dashboard_status' ], 5 );
	}

	/**
	 * Compact, read-only "Subscription" line for the vendor dashboard Overview.
	 * Shows the current plan, status, and free-period end date. Fired by the
	 * 'oc_vendor_dashboard_overview' action ($vendor_id passed).
	 *
	 * @param int $vendor_id
	 */
	public function render_dashboard_status( $vendor_id = 0 ) {
		$vendor_id = (int) $vendor_id;
		if ( ! $vendor_id && function_exists( 'oc_get_current_vendor_post' ) ) {
			$p = oc_get_current_vendor_post();
			$vendor_id = $p ? (int) $p->ID : 0;
		}
		if ( ! $vendor_id ) {
			return;
		}

		$sub    = self::get_vendor_subscription( $vendor_id );
		$status = (string) $sub['status'];

		$labels = [
			'free'     => __( 'Free period', 'owambe-connect-core' ),
			'trialing' => __( 'Trial', 'owambe-connect-core' ),
			'active'   => __( 'Active', 'owambe-connect-core' ),
			'past_due' => __( 'Payment due', 'owambe-connect-core' ),
			'canceled' => __( 'Cancelled', 'owambe-connect-core' ),
			''         => __( 'No active plan', 'owambe-connect-core' ),
		];
		$status_label = isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( str_replace( '_', ' ', $status ) );

		$tiers      = self::tiers();
		$plan_label = ( '' !== (string) $sub['plan'] && isset( $tiers[ $sub['plan'] ] ) )
			? $tiers[ $sub['plan'] ]['label']
			: '—';

		// State → colour (semantic, not the brand accent).
		$tone = 'muted';
		if ( ! empty( $sub['active'] ) ) {
			$tone = 'ok';
		} elseif ( in_array( $status, [ 'past_due', 'canceled' ], true ) ) {
			$tone = 'warn';
		}

		$when = '';
		if ( 'free' === $status && (int) $sub['free_until'] > 0 ) {
			$when = sprintf(
				/* translators: %s: date the free period ends */
				__( 'Free until %s', 'owambe-connect-core' ),
				date_i18n( (string) get_option( 'date_format' ), (int) $sub['free_until'] )
			);
		}
		?>
		<div class="oc-subline oc-subline--<?php echo esc_attr( $tone ); ?>">
			<span class="oc-subline__label"><?php esc_html_e( 'Subscription', 'owambe-connect-core' ); ?></span>
			<span class="oc-subline__dot" aria-hidden="true"></span>
			<span class="oc-subline__status"><?php echo esc_html( $status_label ); ?></span>
			<span class="oc-subline__sep" aria-hidden="true">·</span>
			<span class="oc-subline__plan"><?php printf( esc_html__( 'Plan: %s', 'owambe-connect-core' ), esc_html( $plan_label ) ); ?></span>
			<?php if ( '' !== $when ) : ?>
				<span class="oc-subline__sep" aria-hidden="true">·</span>
				<span class="oc-subline__when"><?php echo esc_html( $when ); ?></span>
			<?php endif; ?>
		</div>
		<style>
			.oc-subline{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:0 0 1rem;padding:9px 14px;
				background:#fff;border:1px solid var(--oc-border,#E4DDD2);border-radius:10px;
				font-size:13.5px;color:#6B6361;}
			.oc-subline__label{font-weight:700;color:#3A3330;text-transform:uppercase;letter-spacing:.04em;font-size:11.5px;}
			.oc-subline__dot{width:8px;height:8px;border-radius:50%;background:#9A938C;}
			.oc-subline--ok .oc-subline__dot{background:#2E7D52;}
			.oc-subline--warn .oc-subline__dot{background:#C77D0A;}
			.oc-subline__status{font-weight:600;color:#3A3330;}
			.oc-subline--ok .oc-subline__status{color:#1e7a42;}
			.oc-subline--warn .oc-subline__status{color:#8a5a0a;}
			.oc-subline__sep{color:#C9BFC1;}
		</style>
		<?php
	}

	// ─── Tier catalogue (single source of truth) ────────────────────────────

	/**
	 * The subscription catalogue. Price IDs come from Settings; the rest is
	 * presentation. Filter 'oc_subscription_tiers' to customise.
	 *
	 * @return array<string,array> slug => [ label, price_id, rank, features ].
	 */
	public static function tiers() {
		// Display label + price come from Settings (Tier definitions & prices,
		// gated by billing_enabled there); a blank label falls back to the
		// built-in name so the catalogue always renders sensibly.
		$tiers = [
			'professional' => [
				'label'    => (string) oc_get_setting( 'tier_label_professional', '' ) ?: __( 'Professional', 'owambe-connect-core' ),
				'price'    => (string) oc_get_setting( 'tier_price_professional', '' ),
				'price_id' => (string) oc_get_setting( 'stripe_price_professional', '' ),
				'rank'     => 1,
				'features' => [
					__( 'Verified vendor profile', 'owambe-connect-core' ),
					__( 'Unlimited enquiries', 'owambe-connect-core' ),
					__( 'Up to 10 gallery photos', 'owambe-connect-core' ),
				],
			],
			'elite' => [
				'label'    => (string) oc_get_setting( 'tier_label_elite', '' ) ?: __( 'Elite', 'owambe-connect-core' ),
				'price'    => (string) oc_get_setting( 'tier_price_elite', '' ),
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
				'label'    => (string) oc_get_setting( 'tier_label_premium', '' ) ?: __( 'Premium', 'owambe-connect-core' ),
				'price'    => (string) oc_get_setting( 'tier_price_premium', '' ),
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

	// ─── Free subscription period ───────────────────────────────────────────

	/** Free months this vendor qualifies for (12 founding / 3 new), filterable. */
	public static function free_months_for( $vendor_id ) {
		$founding = 1 === (int) get_post_meta( (int) $vendor_id, '_oc_founding_vendor', true );
		$months   = $founding ? self::FOUNDING_MONTHS : self::NEW_MONTHS;
		return (int) apply_filters( 'oc_vendor_free_months', $months, (int) $vendor_id, $founding );
	}

	/**
	 * Start the free period for a vendor (once). Sets status = 'free' and stamps
	 * when it ends. Won't clobber an existing free/paid subscription unless forced.
	 *
	 * @param int  $vendor_id
	 * @param bool $force
	 * @return bool True if a free period was set.
	 */
	public static function start_free_period( $vendor_id, $force = false ) {
		$vendor_id = (int) $vendor_id;
		if ( ! $vendor_id || get_post_type( $vendor_id ) !== self::cpt() ) {
			return false;
		}
		$already = (int) get_post_meta( $vendor_id, self::META_FREE_UNTIL, true );
		$status  = (string) get_post_meta( $vendor_id, self::META_STATUS, true );
		if ( ! $force && ( $already > 0 || in_array( $status, self::ACTIVE_STATUSES, true ) ) ) {
			return false; // already on a free or paid plan.
		}

		$months = self::free_months_for( $vendor_id );
		$until  = strtotime( '+' . $months . ' months' );

		update_post_meta( $vendor_id, self::META_STATUS, 'free' );
		update_post_meta( $vendor_id, self::META_FREE_UNTIL, $until );
		if ( '' === (string) get_post_meta( $vendor_id, self::META_PLAN, true ) ) {
			update_post_meta( $vendor_id, self::META_PLAN, '' ); // no paid plan yet.
		}

		/**
		 * Fires when a vendor's free subscription period begins.
		 *
		 * @param int $vendor_id
		 * @param int $until  GMT Unix timestamp the free period ends.
		 * @param int $months Length granted.
		 */
		do_action( 'oc_vendor_free_period_started', $vendor_id, $until, $months );
		return true;
	}

	/** Start the free period the first time a vendor is approved (published). */
	public function maybe_start_free_period( $new_status, $old_status, $post ) {
		if ( ! $post || $post->post_type !== self::cpt() ) {
			return;
		}
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return; // only on the transition INTO approved.
		}
		self::start_free_period( (int) $post->ID );
	}

	// ─── Checkout ───────────────────────────────────────────────────────────

	/**
	 * Create a subscription Checkout Session for a vendor + tier.
	 *
	 * @param int    $vendor_id
	 * @param string $tier_slug professional|elite|premium
	 * @return string|WP_Error Hosted checkout URL.
	 */
	public static function create_checkout_session( $vendor_id, $tier_slug ) {
		$vendor = get_post( (int) $vendor_id );
		if ( ! $vendor || $vendor->post_type !== self::cpt() ) {
			return new WP_Error( 'oc_vsub', __( 'Vendor not found.', 'owambe-connect-core' ) );
		}
		$tier = self::get_tier( $tier_slug );
		if ( ! $tier || empty( $tier['price_id'] ) ) {
			return new WP_Error( 'oc_vsub', __( 'That plan is not available right now.', 'owambe-connect-core' ) );
		}
		$owner = get_userdata( (int) $vendor->post_author );

		$return = self::return_url();
		$params = [
			'mode'                => 'subscription',
			'line_items'          => [ [ 'price' => $tier['price_id'], 'quantity' => 1 ] ],
			'success_url'         => add_query_arg( [ 'oc_sub' => 'success', 'plan' => $tier_slug ], $return ),
			'cancel_url'          => add_query_arg( 'oc_sub', 'cancel', $return ),
			'client_reference_id' => (string) $vendor->ID,
			'allow_promotion_codes' => 'true',
			// Stamp both ids so the webhook can map to the vendor post reliably
			// (vendor_id first, user_id as a secondary hint).
			'metadata'            => [ 'vendor_id' => (string) $vendor->ID, 'user_id' => (string) $vendor->post_author, 'tier' => $tier_slug ],
			'subscription_data'   => [ 'metadata' => [ 'vendor_id' => (string) $vendor->ID, 'user_id' => (string) $vendor->post_author, 'tier' => $tier_slug ] ],
		];

		// Carry any remaining free period over as the Stripe trial, so the vendor
		// isn't billed until their free window actually ends.
		$free_until = (int) get_post_meta( $vendor->ID, self::META_FREE_UNTIL, true );
		if ( $free_until > time() ) {
			$params['subscription_data']['trial_end'] = $free_until;
		}

		$customer = (string) get_post_meta( $vendor->ID, self::META_CUSTOMER, true );
		if ( '' !== $customer ) {
			$params['customer'] = $customer;
		} elseif ( $owner && is_email( $owner->user_email ) ) {
			$params['customer_email'] = $owner->user_email;
		}

		$session = OC_Stripe::request( 'POST', 'checkout/sessions', $params );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		if ( empty( $session['url'] ) ) {
			return new WP_Error( 'oc_vsub', __( 'Could not start checkout. Please try again.', 'owambe-connect-core' ) );
		}
		return (string) $session['url'];
	}

	/** admin-post: the logged-in vendor subscribes to a tier. */
	public function handle_checkout() {
		if ( ! is_user_logged_in() ) {
			$this->bail( __( 'Please sign in to subscribe.', 'owambe-connect-core' ) );
		}
		check_admin_referer( self::ACTION );

		$vendor_id = self::vendor_for_user( get_current_user_id() );
		if ( ! $vendor_id ) {
			$this->bail( __( 'No vendor profile found for your account.', 'owambe-connect-core' ) );
		}
		$tier = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : '';
		$url  = self::create_checkout_session( $vendor_id, $tier );
		if ( is_wp_error( $url ) ) {
			$this->bail( $url->get_error_message() );
		}
		wp_redirect( $url ); // Stripe-hosted — wp_safe_redirect would strip it.
		exit;
	}

	// ─── Webhook → vendor post meta ─────────────────────────────────────────

	/**
	 * Route signature-verified Stripe events onto the vendor post meta.
	 *
	 * @param array $event
	 */
	public function handle_event( $event ) {
		$type   = isset( $event['type'] ) ? (string) $event['type'] : '';
		$object = ( isset( $event['data']['object'] ) && is_array( $event['data']['object'] ) ) ? $event['data']['object'] : [];

		switch ( $type ) {
			case 'checkout.session.completed':
				if ( 'subscription' !== ( $object['mode'] ?? '' ) ) {
					return; // one-time payments (e.g. verification) aren't ours.
				}
				$vendor_id = absint( $object['metadata']['vendor_id'] ?? 0 );
				if ( ! $vendor_id ) {
					$vendor_id = absint( $object['client_reference_id'] ?? 0 );
				}
				$customer = isset( $object['customer'] ) ? (string) $object['customer'] : '';
				if ( ! $vendor_id && $customer ) {
					$vendor_id = self::find_vendor_by_customer( $customer );
				}
				if ( ! $vendor_id || get_post_type( $vendor_id ) !== self::cpt() ) {
					return;
				}
				if ( $customer ) {
					update_post_meta( $vendor_id, self::META_CUSTOMER, sanitize_text_field( $customer ) );
				}
				$sub_id = isset( $object['subscription'] ) ? (string) $object['subscription'] : '';
				if ( '' !== $sub_id ) {
					$sub = OC_Stripe::request( 'GET', 'subscriptions/' . rawurlencode( $sub_id ) );
					if ( ! is_wp_error( $sub ) && is_array( $sub ) ) {
						$this->apply( $vendor_id, $sub );
					}
				}
				break;

			case 'customer.subscription.created':
			case 'customer.subscription.updated':
			case 'customer.subscription.deleted':
				$vendor_id = absint( $object['metadata']['vendor_id'] ?? 0 );
				if ( ! $vendor_id && ! empty( $object['customer'] ) ) {
					$vendor_id = self::find_vendor_by_customer( (string) $object['customer'] );
				}
				if ( $vendor_id ) {
					$this->apply( $vendor_id, $object );
				}
				break;

			case 'invoice.payment_failed':
				$vendor_id = ! empty( $object['customer'] ) ? self::find_vendor_by_customer( (string) $object['customer'] ) : 0;
				if ( $vendor_id ) {
					update_post_meta( $vendor_id, self::META_STATUS, 'past_due' );
					do_action( 'oc_vendor_subscription_updated', $vendor_id, self::get_vendor_subscription( $vendor_id ) );
				}
				break;
		}
	}

	/**
	 * Write a Stripe subscription object onto the vendor post meta.
	 *
	 * @param int   $vendor_id
	 * @param array $sub Stripe subscription object.
	 */
	private function apply( $vendor_id, array $sub ) {
		$status   = isset( $sub['status'] ) ? sanitize_text_field( (string) $sub['status'] ) : '';
		$price_id = '';
		if ( isset( $sub['items']['data'][0]['price']['id'] ) ) {
			$price_id = (string) $sub['items']['data'][0]['price']['id'];
		} elseif ( isset( $sub['plan']['id'] ) ) {
			$price_id = (string) $sub['plan']['id'];
		}
		$plan = self::tier_for_price( $price_id );

		update_post_meta( $vendor_id, self::META_PLAN, $plan );
		update_post_meta( $vendor_id, self::META_STATUS, $status );
		if ( isset( $sub['id'] ) ) {
			update_post_meta( $vendor_id, self::META_SUB, sanitize_text_field( (string) $sub['id'] ) );
		}
		if ( isset( $sub['customer'] ) ) {
			update_post_meta( $vendor_id, self::META_CUSTOMER, sanitize_text_field( (string) $sub['customer'] ) );
		}

		/**
		 * Fires after a vendor's subscription meta changes.
		 *
		 * @param int   $vendor_id
		 * @param array $subscription Local snapshot from get_vendor_subscription().
		 */
		do_action( 'oc_vendor_subscription_updated', $vendor_id, self::get_vendor_subscription( $vendor_id ) );
	}

	// ─── Read state ─────────────────────────────────────────────────────────

	/**
	 * @param int $vendor_id
	 * @return array{ plan:string, status:string, free_until:int, active:bool }
	 */
	public static function get_vendor_subscription( $vendor_id ) {
		$vendor_id  = (int) $vendor_id;
		$status     = (string) get_post_meta( $vendor_id, self::META_STATUS, true );
		$free_until = (int) get_post_meta( $vendor_id, self::META_FREE_UNTIL, true );

		$active = in_array( $status, self::ACTIVE_STATUSES, true )
			|| ( 'free' === $status && $free_until > time() );

		return [
			'plan'       => (string) get_post_meta( $vendor_id, self::META_PLAN, true ),
			'status'     => $status,
			'free_until' => $free_until,
			'active'     => $active,
		];
	}

	// ─── Helpers ────────────────────────────────────────────────────────────

	private static function cpt() {
		return defined( 'OC_CPT' ) ? OC_CPT : 'oc_vendor';
	}

	/** @return int Vendor post id for a Stripe customer, or 0. */
	private static function find_vendor_by_customer( $customer_id ) {
		$ids = get_posts( [
			'post_type'   => self::cpt(),
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => self::META_CUSTOMER,
			'meta_value'  => (string) $customer_id,
		] );
		return $ids ? (int) $ids[0] : 0;
	}

	/** @return int The user's vendor post id, or 0. */
	private static function vendor_for_user( $user_id ) {
		$ids = get_posts( [
			'post_type'   => self::cpt(),
			'author'      => (int) $user_id,
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
		] );
		return $ids ? (int) $ids[0] : 0;
	}

	private static function return_url() {
		$url = function_exists( 'oc_page_url' ) ? oc_page_url( 'vendor-dashboard' ) : home_url( '/' );
		return apply_filters( 'oc_vendor_subscription_return_url', $url ?: home_url( '/' ) );
	}

	private function bail( $message ) {
		wp_safe_redirect( add_query_arg( [ 'oc_sub' => 'error', 'oc_sub_msg' => rawurlencode( $message ) ], self::return_url() ) );
		exit;
	}
}
