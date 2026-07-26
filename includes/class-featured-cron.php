<?php
/**
 * Featured / promotion expiry via WP-Cron.
 *
 * Featured listings can be given a duration by stamping the vendor post with
 * `_oc_featured_until` (a GMT Unix timestamp) and, optionally, a
 * `_oc_featured_type` (which promotion the vendor bought). A DAILY WP-Cron event
 * sweeps for vendors whose window has passed and removes their featured status:
 * it deletes `_oc_featured_until` and `_oc_featured_type` and clears the
 * canonical `_oc_featured` flag (→ 0) so the badge disappears everywhere it's
 * read (directory, admin list, analytics).
 *
 * Manually-featured vendors with NO `_oc_featured_until` are left untouched —
 * only time-boxed promotions expire. The event is (re)scheduled on activation
 * and self-heals on init, so an already-active install starts sweeping too.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Featured_Cron {

	/** Cron hook + recurrence. */
	const HOOK     = 'oc_expire_featured';
	const SCHEDULE = 'daily';

	/** Post-meta keys. */
	const META_FEATURED = '_oc_featured';
	const META_UNTIL    = '_oc_featured_until';
	const META_TYPE     = '_oc_featured_type';

	/** Safety cap on how many expire in a single run. */
	const BATCH = 200;

	public function register() {
		add_action( self::HOOK, [ $this, 'expire_featured' ] );
		// Self-heal: make sure the event exists (and uses the current recurrence)
		// even on an already-active site — activation only runs once.
		add_action( 'init', [ __CLASS__, 'ensure_scheduled' ] );
	}

	/**
	 * Schedule the recurring event if it isn't already — and reschedule if the
	 * recurrence changed (e.g. an install still on the old hourly event migrates
	 * to daily automatically on the next request).
	 */
	public static function ensure_scheduled() {
		$next = wp_next_scheduled( self::HOOK );
		if ( $next && wp_get_schedule( self::HOOK ) !== self::SCHEDULE ) {
			wp_clear_scheduled_hook( self::HOOK );
			$next = false;
		}
		if ( ! $next ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE, self::HOOK );
		}
	}

	/** Plugin activation hook target. */
	public static function activate() {
		self::ensure_scheduled();
	}

	/** Plugin deactivation hook target — clear the event so it doesn't linger. */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Cron callback: remove featured status from every vendor whose promotion
	 * window has elapsed. Idempotent and safe to run repeatedly.
	 *
	 * @return int Number of vendors expired this run.
	 */
	public function expire_featured() {
		$now = time();

		$ids = get_posts( [
			'post_type'      => defined( 'OC_CPT' ) ? OC_CPT : 'oc_vendor',
			'post_status'    => 'any',
			'numberposts'    => self::BATCH,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'cache_results'  => false,
			// Every vendor whose featured window (_oc_featured_until) has passed.
			'meta_query'     => [
				[
					'key'     => self::META_UNTIL,
					'value'   => $now,
					'compare' => '<=',
					'type'    => 'NUMERIC',
				],
			],
		] );

		$expired = 0;
		foreach ( $ids as $id ) {
			// Re-check numerically to avoid any string-comparison edge cases, and
			// skip rows with a missing/zero stamp (manual "featured forever").
			$until = (int) get_post_meta( $id, self::META_UNTIL, true );
			if ( $until <= 0 || $until > $now ) {
				continue;
			}

			// Remove featured status: clear the expiry + type and drop the flag.
			delete_post_meta( $id, self::META_UNTIL );
			delete_post_meta( $id, self::META_TYPE );
			update_post_meta( $id, self::META_FEATURED, 0 );

			/**
			 * Fires when a vendor's featured promotion expires.
			 *
			 * @param int $id Vendor post id.
			 */
			do_action( 'oc_featured_expired', $id );
			$expired++;
		}

		if ( $expired && function_exists( 'oc_debug_log' ) ) {
			// $force: cron runs unauthenticated, so the admin+debug gate would
			// otherwise suppress this.
			oc_debug_log( 'featured expiry sweep removed ' . $expired . ' listing(s)', [], true );
		}

		return $expired;
	}

	// ─── Helper for creating timed promotions ───────────────────────────────

	/**
	 * Feature a vendor for a fixed number of days. Stamps the expiry (and an
	 * optional promotion type) so the daily sweep removes it automatically when
	 * the window ends.
	 *
	 * @param int    $post_id Vendor post id.
	 * @param int    $days    Duration in days (min 1).
	 * @param string $type    Optional promotion type (e.g. 'homepage', 'category').
	 * @return bool
	 */
	public static function feature_for_days( $post_id, $days, $type = '' ) {
		$post_id = (int) $post_id;
		if ( ! $post_id || get_post_type( $post_id ) !== ( defined( 'OC_CPT' ) ? OC_CPT : 'oc_vendor' ) ) {
			return false;
		}
		$days  = max( 1, (int) $days );
		$until = time() + ( $days * DAY_IN_SECONDS );

		update_post_meta( $post_id, self::META_FEATURED, 1 );
		update_post_meta( $post_id, self::META_UNTIL, $until );
		$type = sanitize_key( $type );
		if ( '' !== $type ) {
			update_post_meta( $post_id, self::META_TYPE, $type );
		}

		/**
		 * Fires when a vendor is given a time-boxed featured promotion.
		 *
		 * @param int    $post_id Vendor post id.
		 * @param int    $until   GMT Unix timestamp the promotion ends.
		 * @param string $type    Promotion type ('' if none).
		 */
		do_action( 'oc_featured_scheduled', $post_id, $until, $type );
		return true;
	}
}
