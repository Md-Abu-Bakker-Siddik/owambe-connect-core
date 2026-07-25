<?php
/**
 * £15 Vendor Verification — one-time Stripe payment that marks a vendor as
 * "Verified Vendor".
 *
 * Flow: a logged-in vendor clicks "Get verified" → a one-time Stripe Checkout
 * Session (mode=payment) for the fee → on the signature-verified
 * checkout.session.completed webhook (routed via OC_Stripe's 'oc_stripe_event'),
 * we flip the vendor's _oc_verified post meta to 1 (the same flag admins use)
 * and stamp the user. Idempotent and fail-closed: only a *paid* session that
 * carries our oc_purpose marker grants verification.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Vendor_Verification {

	/** admin-post action that starts the payment. */
	const ACTION = 'oc_verify_pay';

	/** Marker set on the Checkout Session so the webhook knows what it's for. */
	const PURPOSE = 'vendor_verification';

	/** Fee in the smallest currency unit (1500 = £15.00). */
	const FEE_AMOUNT   = 1500;
	const FEE_CURRENCY = 'gbp';

	/** User meta mirroring the paid/verified state. */
	const META_STATUS = '_oc_verification_status';
	const META_PAID   = '_oc_verification_paid';

	public function register() {
		add_action( 'oc_stripe_event', [ $this, 'on_stripe_event' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_checkout' ] );
		add_shortcode( 'oc_vendor_verify', [ $this, 'shortcode' ] );

		// Auto-render the verification card/badge on the vendor dashboard
		// Overview panel — no manual shortcode placement required. The template
		// exposes this hook; the shortcode self-guards (vendor-only, status-aware).
		add_action( 'oc_vendor_dashboard_overview', [ $this, 'render_dashboard_card' ] );

		// Confirm the payment when Stripe redirects back to ?oc_verify=success —
		// a safety net so verification is granted even if the webhook never
		// reaches the site (e.g. localhost). Runs before content renders, so the
		// dashboard shows the badge on the very same request.
		add_action( 'template_redirect', [ $this, 'maybe_confirm_success' ] );
	}

	/**
	 * On return from Stripe (?oc_verify=success&session_id=cs_…), retrieve the
	 * Checkout Session and grant verification ONLY if it is genuinely paid, is a
	 * verification-purpose session, and belongs to the current user. This never
	 * trusts the bare URL flag, so it can't be spoofed by visiting the URL.
	 */
	public function maybe_confirm_success() {
		if ( is_admin() || ! is_user_logged_in() ) {
			return;
		}
		if ( 'success' !== ( isset( $_GET['oc_verify'] ) ? sanitize_key( wp_unslash( $_GET['oc_verify'] ) ) : '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$user_id = get_current_user_id();
		if ( self::is_verified( $user_id ) ) {
			return; // Already granted (e.g. the webhook got there first).
		}

		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $session_id || ! OC_Stripe::is_configured() ) {
			return; // Nothing we can safely confirm — leave it to the webhook.
		}

		$session = OC_Stripe::request( 'GET', 'checkout/sessions/' . rawurlencode( $session_id ) );
		if ( is_wp_error( $session ) || ! is_array( $session ) ) {
			return;
		}

		$paid       = ( 'paid' === ( $session['payment_status'] ?? '' ) ) || ( 'complete' === ( $session['status'] ?? '' ) );
		$purpose_ok = ( self::PURPOSE === ( $session['metadata']['oc_purpose'] ?? '' ) );

		$owner = absint( $session['client_reference_id'] ?? 0 );
		if ( ! $owner && isset( $session['metadata']['user_id'] ) ) {
			$owner = absint( $session['metadata']['user_id'] );
		}

		if ( $paid && $purpose_ok && $owner === (int) $user_id ) {
			self::mark_verified( $user_id );
		}
	}

	/**
	 * Echo the verification card/badge into the vendor dashboard Overview.
	 * Fired by the 'oc_vendor_dashboard_overview' action. Delegates to the
	 * shortcode, which already checks login, role and verification status and
	 * escapes all output, so this is safe to echo directly.
	 */
	public function render_dashboard_card( $vendor_id = 0 ) {
		// Resolve the viewer robustly. Normally this is the logged-in vendor;
		// fall back to the passed vendor post's author if, for any reason, the
		// current user isn't set in this context.
		$user_id = get_current_user_id();
		if ( ! $user_id && $vendor_id ) {
			$user_id = (int) get_post_field( 'post_author', (int) $vendor_id );
		}
		if ( ! $user_id ) {
			return;
		}
		// Render directly for this user — no public vendor-role gate here, since
		// reaching the dashboard already establishes the vendor/owner context
		// (this also lets an admin preview the card).
		echo $this->render_for_user( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// ─── Fee helpers ────────────────────────────────────────────────────────

	public static function fee_amount() {
		return (int) apply_filters( 'oc_verification_fee', self::FEE_AMOUNT );
	}
	public static function fee_currency() {
		return strtolower( (string) apply_filters( 'oc_verification_currency', self::FEE_CURRENCY ) );
	}
	/** Human fee, e.g. "£15.00". */
	public static function fee_display() {
		$symbols = [ 'gbp' => '£', 'usd' => '$', 'eur' => '€', 'ngn' => '₦' ];
		$cur     = self::fee_currency();
		$symbol  = isset( $symbols[ $cur ] ) ? $symbols[ $cur ] : strtoupper( $cur ) . ' ';
		return $symbol . number_format( self::fee_amount() / 100, 2 );
	}

	// ─── Checkout ───────────────────────────────────────────────────────────

	/**
	 * One-time Checkout Session for the verification fee.
	 *
	 * @param int $user_id
	 * @return string|WP_Error Hosted checkout URL, or WP_Error.
	 */
	public static function create_checkout_session( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return new WP_Error( 'oc_verify', __( 'You must be signed in to get verified.', 'owambe-connect-core' ) );
		}

		$return = self::return_url();

		// Stripe substitutes {CHECKOUT_SESSION_ID} on redirect. We confirm that
		// session server-side (maybe_confirm_success) before granting — so the
		// flow works even when webhooks can't reach the site (e.g. localhost),
		// and a bare ?oc_verify=success URL grants nothing on its own. The
		// placeholder braces must stay literal, so it's appended, not passed
		// through add_query_arg (which would URL-encode them).
		$success  = add_query_arg( 'oc_verify', 'success', $return );
		$success .= ( false === strpos( $success, '?' ) ? '?' : '&' ) . 'session_id={CHECKOUT_SESSION_ID}';

		$params = [
			'mode'                => 'payment',
			'line_items'          => [ [
				'quantity'   => 1,
				'price_data' => [
					'currency'     => self::fee_currency(),
					'unit_amount'  => self::fee_amount(),
					'product_data' => [ 'name' => __( 'Vendor Verification', 'owambe-connect-core' ) ],
				],
			] ],
			'success_url'         => $success,
			'cancel_url'          => add_query_arg( 'oc_verify', 'cancel', $return ),
			'client_reference_id' => (string) $user->ID,
			'customer_email'      => $user->user_email,
			// Marker + user id echoed on BOTH the session and the PaymentIntent
			// so the webhook can attribute the payment with certainty.
			'metadata'            => [ 'oc_purpose' => self::PURPOSE, 'user_id' => (string) $user->ID ],
			'payment_intent_data' => [ 'metadata' => [ 'oc_purpose' => self::PURPOSE, 'user_id' => (string) $user->ID ] ],
		];

		// Reuse a Stripe customer we already know (e.g. from subscriptions).
		$customer = (string) get_user_meta( $user->ID, '_oc_stripe_customer_id', true );
		if ( '' !== $customer ) {
			$params['customer'] = $customer;
			unset( $params['customer_email'] ); // can't send both.
		}

		$session = OC_Stripe::request( 'POST', 'checkout/sessions', $params );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		if ( empty( $session['url'] ) ) {
			return new WP_Error( 'oc_verify', __( 'Could not start checkout. Please try again.', 'owambe-connect-core' ) );
		}
		return (string) $session['url'];
	}

	/** admin-post: vendor clicks "Get verified" → redirect to Stripe. */
	public function handle_checkout() {
		if ( ! is_user_logged_in() ) {
			$this->bail( __( 'Please sign in to get verified.', 'owambe-connect-core' ) );
		}
		check_admin_referer( self::ACTION );

		$user_id = get_current_user_id();
		if ( self::is_verified( $user_id ) ) {
			wp_safe_redirect( add_query_arg( 'oc_verify', 'already', self::return_url() ) );
			exit;
		}
		$url = self::create_checkout_session( $user_id );
		if ( is_wp_error( $url ) ) {
			$this->bail( $url->get_error_message() );
		}
		wp_redirect( $url ); // Stripe-hosted URL — wp_safe_redirect would strip it.
		exit;
	}

	// ─── Webhook → grant verification ───────────────────────────────────────

	/**
	 * Fires for every signature-verified Stripe event (OC_Stripe::webhook).
	 * Only a PAID verification-purpose one-time checkout grants the badge.
	 *
	 * @param array $event
	 */
	public function on_stripe_event( $event ) {
		if ( ! is_array( $event ) || 'checkout.session.completed' !== ( $event['type'] ?? '' ) ) {
			return;
		}
		$o = ( isset( $event['data']['object'] ) && is_array( $event['data']['object'] ) ) ? $event['data']['object'] : [];

		if ( 'payment' !== ( $o['mode'] ?? '' ) ) {
			return; // subscriptions are handled by OC_Subscriptions.
		}
		if ( self::PURPOSE !== ( $o['metadata']['oc_purpose'] ?? '' ) ) {
			return; // not a verification payment.
		}
		if ( isset( $o['payment_status'] ) && 'paid' !== $o['payment_status'] ) {
			return; // fail closed on unpaid/async-pending sessions.
		}

		$user_id = absint( $o['client_reference_id'] ?? 0 );
		if ( ! $user_id && isset( $o['metadata']['user_id'] ) ) {
			$user_id = absint( $o['metadata']['user_id'] );
		}
		if ( $user_id ) {
			self::mark_verified( $user_id );
		}
	}

	/**
	 * Grant "Verified Vendor" status: flip the vendor post's _oc_verified flag
	 * (canonical, same as the admin toggle) and stamp the user. Idempotent.
	 *
	 * @param int $user_id
	 */
	public static function mark_verified( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return;
		}
		update_user_meta( $user_id, self::META_STATUS, 'verified' );
		update_user_meta( $user_id, self::META_PAID, time() );

		$vendor_id = self::vendor_post_id_for_user( $user_id );
		if ( $vendor_id ) {
			update_post_meta( $vendor_id, '_oc_verified', 1 );
		}

		/**
		 * Fires after a vendor is verified via paid checkout.
		 *
		 * @param int $user_id
		 * @param int $vendor_id Vendor post id (0 if none found yet).
		 */
		do_action( 'oc_vendor_verified', $user_id, $vendor_id );

		if ( function_exists( 'oc_debug_log' ) ) {
			oc_debug_log( 'vendor verified via payment: user ' . $user_id, [], true );
		}
	}

	// ─── State ──────────────────────────────────────────────────────────────

	/** True when the vendor is already verified (post flag or user stamp). */
	public static function is_verified( $user_id ) {
		if ( 'verified' === (string) get_user_meta( (int) $user_id, self::META_STATUS, true ) ) {
			return true;
		}
		$vendor_id = self::vendor_post_id_for_user( $user_id );
		return $vendor_id && 1 === (int) get_post_meta( $vendor_id, '_oc_verified', true );
	}

	/** @return int The user's vendor post id, or 0. */
	private static function vendor_post_id_for_user( $user_id ) {
		$ids = get_posts( [
			'post_type'   => defined( 'OC_CPT' ) ? OC_CPT : 'oc_vendor',
			'author'      => (int) $user_id,
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
		] );
		return $ids ? (int) $ids[0] : 0;
	}

	// ─── Front-end trigger (shortcode) ──────────────────────────────────────

	/**
	 * [oc_vendor_verify] — public shortcode. Vendor-gated so it stays inert if
	 * pasted on a public page by mistake. The dashboard auto-render path calls
	 * render_for_user() directly (no public gate).
	 */
	public function shortcode( $atts = [] ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$user      = wp_get_current_user();
		$is_vendor = defined( 'OC_ROLE' ) ? in_array( OC_ROLE, (array) $user->roles, true ) : true;
		if ( ! $is_vendor ) {
			return '';
		}
		return $this->render_for_user( (int) $user->ID );
	}

	/**
	 * Build the verified badge (if verified) or the "Get verified" card for a
	 * specific user. Always returns something for an unverified vendor — when
	 * Stripe isn't configured yet the button is shown disabled with a note,
	 * rather than the card silently disappearing. All output is escaped.
	 *
	 * @param int $user_id
	 * @return string
	 */
	private function render_for_user( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return '';
		}
		$flag = isset( $_GET['oc_verify'] ) ? sanitize_key( wp_unslash( $_GET['oc_verify'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		ob_start();
		echo '<div class="oc-verify">';

		if ( 'cancel' === $flag ) {
			echo '<p class="oc-verify__msg oc-verify__msg--warn">' . esc_html__( 'Payment cancelled — you can try again any time.', 'owambe-connect-core' ) . '</p>';
		} elseif ( 'error' === $flag ) {
			$m = isset( $_GET['oc_verify_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['oc_verify_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<p class="oc-verify__msg oc-verify__msg--err">' . esc_html( $m ?: __( 'Something went wrong. Please try again.', 'owambe-connect-core' ) ) . '</p>';
		}

		if ( self::is_verified( $user->ID ) ) {
			echo '<p class="oc-verify__badge">✓ ' . esc_html__( 'Verified Vendor', 'owambe-connect-core' ) . '</p>';
		} else {
			$configured = OC_Stripe::is_configured();
			?>
			<div class="oc-verify__card">
				<div class="oc-verify__card-main">
					<span class="oc-verify__card-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M12 2l7 3v6c0 5-3.5 8.5-7 9.5C8.5 19.5 5 16 5 11V5l7-3z"/>
							<path d="M9 12l2 2 4-4"/>
						</svg>
					</span>
					<div class="oc-verify__card-text">
						<h3 class="oc-verify__title"><?php esc_html_e( 'Become a Verified Vendor', 'owambe-connect-core' ); ?></h3>
						<p class="oc-verify__sub"><?php esc_html_e( 'Earn the ✓ Verified badge and build trust with clients — a one-time fee.', 'owambe-connect-core' ); ?></p>
					</div>
				</div>
				<div class="oc-verify__card-action">
					<?php if ( $configured ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
							<?php wp_nonce_field( self::ACTION ); ?>
							<button type="submit" class="oc-btn oc-btn-primary oc-btn-lg">
								<?php printf( esc_html__( 'Get verified — %s', 'owambe-connect-core' ), esc_html( self::fee_display() ) ); ?>
							</button>
						</form>
					<?php else : ?>
						<button type="button" class="oc-btn oc-btn-primary oc-btn-lg" disabled aria-disabled="true">
							<?php printf( esc_html__( 'Get verified — %s', 'owambe-connect-core' ), esc_html( self::fee_display() ) ); ?>
						</button>
						<p class="oc-verify__note"><?php esc_html_e( 'Online payment isn\'t switched on yet — please check back soon.', 'owambe-connect-core' ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( ! $configured && current_user_can( 'manage_options' ) ) : ?>
					<p class="oc-verify__msg oc-verify__msg--warn oc-verify__card-admin"><?php esc_html_e( 'Admin: add your Stripe keys in Owambe Settings to enable payments. (Only admins see this.)', 'owambe-connect-core' ); ?></p>
				<?php endif; ?>
			</div>
			<?php
		}

		echo '</div>';

		echo '<style>
			.oc-verify{--oc-v-burgundy:#6E0F2C;}
			/* Full-width action banner: icon + text on the left, button on the right. */
			.oc-verify__card{
				display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap;
				width:100%;max-width:none;margin:0 0 1.25rem;padding:20px 24px;text-align:left;
				background:linear-gradient(135deg,rgba(110,15,44,.05),rgba(201,169,97,.06));
				border:1px solid rgba(110,15,44,.16);border-left:4px solid var(--oc-burgundy,#6E0F2C);
				border-radius:14px;box-shadow:0 6px 20px rgba(110,15,44,.06);
			}
			.oc-verify__card-main{display:flex;align-items:center;gap:16px;min-width:0;flex:1 1 300px;}
			.oc-verify__card-icon{
				flex:0 0 auto;width:48px;height:48px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;
				background:var(--oc-burgundy,#6E0F2C);color:#fff;box-shadow:0 4px 12px rgba(110,15,44,.28);
			}
			.oc-verify__card-text{min-width:0;}
			.oc-verify__title{margin:0 0 .2rem;color:var(--oc-burgundy,#6E0F2C);font-size:1.2rem;font-weight:700;line-height:1.2;}
			.oc-verify__sub{margin:0;color:#6B6361;font-size:14px;line-height:1.5;}
			.oc-verify__card-action{flex:0 0 auto;display:flex;flex-direction:column;align-items:flex-end;gap:6px;}
			.oc-verify__card-action form{margin:0;}
			.oc-verify__card-action .oc-btn{white-space:nowrap;}
			.oc-verify__card .oc-btn[disabled]{opacity:.55;cursor:not-allowed;}
			.oc-verify__note{margin:0;color:#9A938C;font-size:12.5px;}
			.oc-verify__card-admin{flex:1 1 100%;margin:0;text-align:left;}
			.oc-verify__badge{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:rgba(40,140,70,.10);color:#1e7a42;border:1px solid rgba(40,140,70,.25);border-radius:999px;font-weight:700;}
			.oc-verify__msg{margin:0 0 .75rem;padding:10px 14px;border-radius:10px;font-size:14px;}
			.oc-verify__msg--warn{background:#fdf6e3;color:#8a6d1a;border:1px solid #efe0b0;}
			.oc-verify__msg--err{background:#fdecea;color:#b32d2e;border:1px solid #f5c6c2;}
			@media (max-width:640px){
				.oc-verify__card{flex-direction:column;align-items:stretch;gap:16px;}
				.oc-verify__card-action{align-items:stretch;}
				.oc-verify__card-action .oc-btn{width:100%;}
			}
		</style>';

		return ob_get_clean();
	}

	// ─── Misc ───────────────────────────────────────────────────────────────

	private static function return_url() {
		$url = function_exists( 'oc_page_url' ) ? oc_page_url( 'vendor-dashboard' ) : home_url( '/' );
		return apply_filters( 'oc_verification_return_url', $url ?: home_url( '/' ) );
	}

	private function bail( $message ) {
		wp_safe_redirect( add_query_arg( [ 'oc_verify' => 'error', 'oc_verify_msg' => rawurlencode( $message ) ], self::return_url() ) );
		exit;
	}
}
