<?php
/**
 * Paid/requested featured placements.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Featured {
	const ACTION       = 'oc_featured_request';
	const CANCEL_ACTION = 'oc_featured_cancel';
	const ADMIN_ACTION = 'oc_featured_decide';
	const PURPOSE      = 'featured';
	const META_REQUEST = '_oc_featured_request';
	const META_CREDITS = '_oc_featured_credit_used';
	const META_YEAR    = '_oc_featured_credit_year';

	public function register() {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_request' ] );
		add_action( 'admin_post_' . self::CANCEL_ACTION, [ $this, 'handle_cancel' ] );
		add_action( 'admin_post_' . self::ADMIN_ACTION, [ $this, 'handle_admin' ] );
		add_action( 'oc_stripe_event', [ $this, 'on_stripe_event' ] );
		add_action( 'template_redirect', [ $this, 'confirm_return' ], 2 );
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_action( 'admin_init', [ $this, 'maybe_cleanup_stale_requests' ] );
		add_action( 'oc_daily_maintenance', [ $this, 'cleanup_stale_requests' ], 20 );
		add_action( 'oc_after_vendor_rejected', [ __CLASS__, 'clear_request' ], 10, 1 );
	}

	public static function durations() {
		return [
			'day'   => [ 'label' => __( '1 day', 'owambe-connect-core' ), 'days' => 1, 'price' => (float) oc_get_setting( 'featured_price_day', 5 ) ],
			'week'  => [ 'label' => __( '1 week', 'owambe-connect-core' ), 'days' => 7, 'price' => (float) oc_get_setting( 'featured_price_week', 25 ) ],
			'month' => [ 'label' => __( '1 month', 'owambe-connect-core' ), 'days' => 30, 'price' => (float) oc_get_setting( 'featured_price_month', 75 ) ],
		];
	}

	public static function credits( $vendor_id ) {
		$year = (int) wp_date( 'Y' );
		$used = (int) get_post_meta( $vendor_id, self::META_CREDITS, true );
		if ( (int) get_post_meta( $vendor_id, self::META_YEAR, true ) !== $year ) {
			$used = 0;
		}
		return [ 'used' => min( 2, $used ), 'remaining' => max( 0, 2 - $used ), 'year' => $year ];
	}

	private static function vendor_for_user() {
		$posts = get_posts( [
			'post_type' => OC_CPT, 'post_status' => 'any', 'author' => get_current_user_id(),
			'numberposts' => 1, 'fields' => 'ids',
		] );
		return $posts ? (int) $posts[0] : 0;
	}

	/**
	 * Delete every local request marker for a vendor. Post meta is the
	 * authoritative queue record; user meta is removed as well to clean up
	 * markers written by older versions of the flow.
	 */
	public static function clear_request( $vendor_id ) {
		$vendor_id = absint( $vendor_id );
		if ( ! $vendor_id ) {
			return;
		}
		delete_post_meta( $vendor_id, self::META_REQUEST );
		$vendor = get_post( $vendor_id );
		if ( $vendor && $vendor->post_author ) {
			delete_user_meta( (int) $vendor->post_author, self::META_REQUEST );
		}
	}

	/**
	 * Return a validated admin-queue request, or an empty array.
	 *
	 * Invalid legacy flags, incomplete arrays and obsolete checkout markers do
	 * not represent rows in the Featured Requests queue, so they are purged.
	 */
	public static function pending_request( $vendor_id ) {
		$vendor_id = absint( $vendor_id );
		$has_meta  = $vendor_id && metadata_exists( 'post', $vendor_id, self::META_REQUEST );
		$request   = $vendor_id ? get_post_meta( $vendor_id, self::META_REQUEST, true ) : [];

		// User meta is never authoritative. Remove any legacy duplicate marker
		// even while a valid post-level request exists.
		$vendor = $vendor_id ? get_post( $vendor_id ) : null;
		if ( $vendor && $vendor->post_author ) {
			delete_user_meta( (int) $vendor->post_author, self::META_REQUEST );
		}

		$durations    = self::durations();
		$duration_key = sanitize_key( $request['duration'] ?? '' );
		$valid     = is_array( $request )
			&& 'pending' === ( $request['status'] ?? '' )
			&& isset( $durations[ $duration_key ] )
			&& absint( $request['days'] ?? 0 ) === (int) ( $durations[ $duration_key ]['days'] ?? 0 )
			&& in_array( sanitize_key( $request['type'] ?? '' ), [ 'homepage', 'category', 'both' ], true )
			&& absint( $request['created'] ?? 0 ) > 0;

		if ( ! $valid ) {
			if ( $has_meta ) {
				self::clear_request( $vendor_id );
			}
			return [];
		}
		return $request;
	}

	/** Remove a featured placement and all request/expiry state. */
	public static function unfeature( $vendor_id ) {
		$vendor_id = absint( $vendor_id );
		if ( ! $vendor_id ) {
			return;
		}
		update_post_meta( $vendor_id, '_oc_featured', 0 );
		delete_post_meta( $vendor_id, '_oc_featured_until' );
		delete_post_meta( $vendor_id, '_oc_featured_type' );
		self::clear_request( $vendor_id );
	}

	/** Periodic fallback for malformed/orphaned request flags. */
	public function cleanup_stale_requests() {
		$ids = get_posts( [
			'post_type'      => OC_CPT,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [ [ 'key' => self::META_REQUEST, 'compare' => 'EXISTS' ] ],
		] );
		foreach ( $ids as $vendor_id ) {
			self::pending_request( $vendor_id );
		}
		// The current implementation never stores request state on users.
		// Therefore every remaining user-level marker is an orphan from an
		// older build and can be removed unconditionally.
		$user_ids = get_users( [ 'meta_key' => self::META_REQUEST, 'fields' => 'ids' ] );
		foreach ( $user_ids as $user_id ) {
			delete_user_meta( (int) $user_id, self::META_REQUEST );
		}
	}

	/** Throttle the fallback sweep on ordinary admin traffic. */
	public function maybe_cleanup_stale_requests() {
		if ( get_transient( 'oc_featured_request_cleanup' ) ) {
			return;
		}
		$this->cleanup_stale_requests();
		set_transient( 'oc_featured_request_cleanup', 1, 6 * HOUR_IN_SECONDS );
	}

	public function handle_request() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( self::ACTION );
		$vendor_id = self::vendor_for_user();
		$duration  = sanitize_key( wp_unslash( $_POST['duration'] ?? '' ) );
		$type      = sanitize_key( wp_unslash( $_POST['featured_type'] ?? '' ) );
		$options   = self::durations();
		if ( ! $vendor_id || ! isset( $options[ $duration ] ) || ! in_array( $type, [ 'homepage', 'category', 'both' ], true ) ) {
			$this->redirect( '', __( 'Please choose a valid placement and duration.', 'owambe-connect-core' ), true );
		}
		if ( OC_STATUS_APPROVED !== get_post_status( $vendor_id ) ) {
			$this->redirect( '', __( 'Your listing must be approved before it can be featured.', 'owambe-connect-core' ), true );
		}
		if ( self::pending_request( $vendor_id ) ) {
			$this->redirect( '', __( 'You already have a featured request in progress.', 'owambe-connect-core' ), true );
		}

		$request = [ 'status' => 'pending', 'duration' => $duration, 'days' => $options[ $duration ]['days'], 'type' => $type, 'created' => time() ];
		$credits = self::credits( $vendor_id );
		if ( 'premium' === oc_vendor_plan( $vendor_id ) && $credits['remaining'] > 0 ) {
			$this->consume_credit( $vendor_id, $credits );
			self::grant( $vendor_id, $request );
			$this->redirect( __( 'Your Premium credit was applied and your featured placement is live.', 'owambe-connect-core' ) );
		}

		if ( ! (int) oc_get_setting( 'billing_enabled', 0 ) ) {
			update_post_meta( $vendor_id, self::META_REQUEST, $request );
			$this->notify_admin( $vendor_id, $request );
			$this->redirect( __( 'Your featured request was sent to the Owambe Connect team.', 'owambe-connect-core' ) );
		}
		if ( ! OC_Stripe::is_configured() ) {
			$this->redirect( '', __( 'Featured checkout is temporarily unavailable.', 'owambe-connect-core' ), true );
		}
		$url = self::checkout( $vendor_id, $request, $options[ $duration ]['price'] );
		if ( is_wp_error( $url ) ) {
			$this->redirect( '', $url->get_error_message(), true );
		}
		wp_redirect( $url );
		exit;
	}

	private function consume_credit( $vendor_id, array $credits ) {
		update_post_meta( $vendor_id, self::META_YEAR, $credits['year'] );
		update_post_meta( $vendor_id, self::META_CREDITS, $credits['used'] + 1 );
	}

	public static function checkout( $vendor_id, array $request, $price ) {
		$return  = add_query_arg( 'oc_featured', 'success', oc_page_url( 'vendor-dashboard' ) );
		$success = $return . '&session_id={CHECKOUT_SESSION_ID}';
		$meta    = [ 'oc_purpose' => self::PURPOSE, 'vendor_id' => (string) $vendor_id, 'duration' => $request['duration'], 'days' => (string) $request['days'], 'featured_type' => $request['type'] ];
		$params  = [
			'mode' => 'payment',
			'line_items' => [ [ 'quantity' => 1, 'price_data' => [ 'currency' => 'gbp', 'unit_amount' => (int) round( $price * 100 ), 'product_data' => [ 'name' => sprintf( __( 'Featured vendor — %s', 'owambe-connect-core' ), self::durations()[ $request['duration'] ]['label'] ) ] ] ] ],
			'success_url' => $success, 'cancel_url' => add_query_arg( [ 'oc_featured' => 'cancel', 'tab' => 'featured', '_wpnonce' => wp_create_nonce( 'oc_featured_checkout_cancel' ) ], oc_page_url( 'vendor-dashboard' ) ),
			'client_reference_id' => (string) $vendor_id, 'metadata' => $meta, 'payment_intent_data' => [ 'metadata' => $meta ],
		];
		$customer = (string) get_post_meta( $vendor_id, '_oc_sub_stripe_customer', true );
		if ( $customer ) {
			$params['customer'] = $customer;
		}
		$session = OC_Stripe::request( 'POST', 'checkout/sessions', $params );
		return is_wp_error( $session ) || empty( $session['url'] ) ? ( is_wp_error( $session ) ? $session : new WP_Error( 'oc_featured', __( 'Could not start checkout.', 'owambe-connect-core' ) ) ) : $session['url'];
	}

	public function on_stripe_event( $event ) {
		if ( 'checkout.session.completed' === ( $event['type'] ?? '' ) ) {
			$this->complete_session( $event['data']['object'] ?? [] );
		}
	}

	public function confirm_return() {
		$state = sanitize_key( wp_unslash( $_GET['oc_featured'] ?? '' ) );
		if ( 'cancel' === $state && is_user_logged_in() && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'oc_featured_checkout_cancel' ) ) {
			self::clear_request( self::vendor_for_user() );
			wp_safe_redirect( add_query_arg( [
				'tab'       => 'featured',
				'oc_notice' => __( 'Checkout cancelled. You can choose a featured placement whenever you are ready.', 'owambe-connect-core' ),
			], oc_page_url( 'vendor-dashboard' ) ) );
			exit;
		}
		if ( 'success' !== $state || empty( $_GET['session_id'] ) || ! is_user_logged_in() ) {
			return;
		}
		$session = OC_Stripe::request( 'GET', 'checkout/sessions/' . rawurlencode( sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) ) );
		if ( ! is_wp_error( $session ) ) {
			$this->complete_session( $session );
		}
		wp_safe_redirect( add_query_arg( [ 'tab' => 'featured', 'oc_notice' => __( 'Your featured placement is live.', 'owambe-connect-core' ) ], oc_page_url( 'vendor-dashboard' ) ) );
		exit;
	}

	/** Let a vendor withdraw a billing-off request that is still in the queue. */
	public function handle_cancel() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( self::CANCEL_ACTION );
		self::clear_request( self::vendor_for_user() );
		$this->redirect( __( 'Your featured request was cancelled. You can submit a new request at any time.', 'owambe-connect-core' ) );
	}

	private function complete_session( $session ) {
		if ( self::PURPOSE !== ( $session['metadata']['oc_purpose'] ?? '' ) || 'paid' !== ( $session['payment_status'] ?? '' ) ) {
			return;
		}
		$vendor_id = absint( $session['metadata']['vendor_id'] ?? $session['client_reference_id'] ?? 0 );
		if ( ! $vendor_id || get_post_meta( $vendor_id, '_oc_featured_payment_' . sanitize_key( $session['id'] ?? '' ), true ) ) {
			return;
		}
		$request = [ 'duration' => sanitize_key( $session['metadata']['duration'] ?? '' ), 'days' => absint( $session['metadata']['days'] ?? 0 ), 'type' => sanitize_key( $session['metadata']['featured_type'] ?? '' ) ];
		if ( $request['days'] && in_array( $request['type'], [ 'homepage', 'category', 'both' ], true ) ) {
			self::grant( $vendor_id, $request );
			update_post_meta( $vendor_id, '_oc_featured_payment_' . sanitize_key( $session['id'] ?? '' ), 1 );
		}
	}

	public static function grant( $vendor_id, array $request ) {
		$days = max( 1, absint( $request['days'] ?? 0 ) );
		$type = sanitize_key( $request['type'] ?? '' );
		$current = (int) get_post_meta( $vendor_id, '_oc_featured_until', true );
		$until   = max( time(), $current ) + $days * DAY_IN_SECONDS;
		update_post_meta( $vendor_id, '_oc_featured', 1 );
		update_post_meta( $vendor_id, '_oc_featured_until', $until );
		update_post_meta( $vendor_id, '_oc_featured_type', $type );
		self::clear_request( $vendor_id );
		do_action( 'oc_featured_scheduled', $vendor_id, $until, $type );
	}

	private function notify_admin( $vendor_id, array $request ) {
		$post = get_post( $vendor_id );
		$body = sprintf( '<p>%s</p><p><strong>%s</strong><br>%s: %s<br>%s: %d days</p><p><a href="%s">%s</a></p>', esc_html__( 'A vendor requested a featured placement.', 'owambe-connect-core' ), esc_html( $post->post_title ), esc_html__( 'Placement', 'owambe-connect-core' ), esc_html( $request['type'] ), esc_html__( 'Duration', 'owambe-connect-core' ), (int) $request['days'], esc_url( admin_url( 'edit.php?post_type=' . OC_CPT . '&page=oc-featured-requests' ) ), esc_html__( 'Review request', 'owambe-connect-core' ) );
		OC_Mail::send( OC_Mail::notification_recipient(), __( 'New featured vendor request', 'owambe-connect-core' ), $body );
	}

	public function admin_menu() {
		add_submenu_page( 'edit.php?post_type=' . OC_CPT, __( 'Featured Requests', 'owambe-connect-core' ), __( 'Featured Requests', 'owambe-connect-core' ), 'manage_options', 'oc-featured-requests', [ $this, 'render_admin' ] );
	}

	public function render_admin() {
		$vendors = get_posts( [ 'post_type' => OC_CPT, 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => self::META_REQUEST ] );
		echo '<div class="wrap"><h1>' . esc_html__( 'Featured Requests', 'owambe-connect-core' ) . '</h1><table class="widefat striped"><thead><tr><th>Vendor</th><th>Placement</th><th>Duration</th><th>Action</th></tr></thead><tbody>';
		$rows = 0;
		foreach ( $vendors as $vendor ) {
			$r = self::pending_request( $vendor->ID );
			if ( ! $r ) {
				continue;
			}
			$rows++;
			$base = admin_url( 'admin-post.php?action=' . self::ADMIN_ACTION . '&vendor_id=' . $vendor->ID );
			$approve = wp_nonce_url( add_query_arg( 'decision', 'approve', $base ), self::ADMIN_ACTION . '_' . $vendor->ID );
			$deny = wp_nonce_url( add_query_arg( 'decision', 'deny', $base ), self::ADMIN_ACTION . '_' . $vendor->ID );
			echo '<tr><td>' . esc_html( $vendor->post_title ) . '</td><td>' . esc_html( $r['type'] ?? '' ) . '</td><td>' . absint( $r['days'] ?? 0 ) . ' days</td><td><a class="button button-primary" href="' . esc_url( $approve ) . '">Approve</a> <a class="button" href="' . esc_url( $deny ) . '">Deny</a></td></tr>';
		}
		if ( ! $rows ) echo '<tr><td colspan="4">' . esc_html__( 'No pending requests.', 'owambe-connect-core' ) . '</td></tr>';
		echo '</tbody></table></div>';
	}

	public function handle_admin() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		$id = absint( $_GET['vendor_id'] ?? 0 );
		check_admin_referer( self::ADMIN_ACTION . '_' . $id );
		$r = self::pending_request( $id );
		if ( 'approve' === ( $_GET['decision'] ?? '' ) && $r ) self::grant( $id, $r );
		else self::clear_request( $id );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . OC_CPT . '&page=oc-featured-requests' ) );
		exit;
	}

	private function redirect( $notice = '', $error = '', $is_error = false ) {
		$args = [ 'tab' => 'featured' ];
		if ( $notice ) $args['oc_notice'] = $notice;
		if ( $error ) $args['oc_error'] = $error;
		wp_safe_redirect( add_query_arg( $args, oc_page_url( 'vendor-dashboard' ) ) );
		exit;
	}
}
