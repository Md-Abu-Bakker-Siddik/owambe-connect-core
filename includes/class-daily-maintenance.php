<?php
/**
 * Shared daily maintenance sweep (WP-Cron: oc_daily_maintenance).
 *
 * One daily event owns every periodic clean-up job:
 *
 *  1. FEATURED EXPIRY (merged from the old oc_expire_featured cron) — vendors
 *     whose `_oc_featured_until` window has passed lose the featured flag/type.
 *     Manually-featured vendors with no expiry stamp are left untouched.
 *
 *  2. SUBSCRIPTION EXPIRY — vendors on a free period (`_oc_sub_status=free`):
 *     - T-14 and T-3 days before `_oc_free_until`, a renewal reminder email is
 *       sent to the vendor owner (each threshold once per free window — the
 *       sent-stamp records WHICH window it was sent for, so a new/extended
 *       window re-arms the reminders, and re-running the sweep never re-sends);
 *     - once the window has lapsed the status is flipped to 'expired';
 *     - every wp_mail() boolean is logged via oc_debug_log().
 *
 * Everything here is idempotent: running the sweep twice in a row produces no
 * additional writes, emails, or log spam.
 *
 * The legacy oc_expire_featured event is unscheduled automatically on the first
 * ensure_scheduled() after this version deploys.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Daily_Maintenance {

	/** Cron hook + recurrence. */
	const HOOK        = 'oc_daily_maintenance';
	const LEGACY_HOOK = 'oc_expire_featured';
	const SCHEDULE    = 'daily';

	/** Featured post-meta keys. */
	const META_FEATURED = '_oc_featured';
	const META_UNTIL    = '_oc_featured_until';
	const META_TYPE     = '_oc_featured_type';

	/** Renewal-reminder sent-stamps (value = the free_until they were sent for). */
	const META_MAIL_T14 = '_oc_sub_mail_t14';
	const META_MAIL_T3  = '_oc_sub_mail_t3';

	/** Reminder thresholds, in days before the free period ends. */
	const T14 = 14;
	const T3  = 3;

	/** Safety cap on how many rows each job touches in a single run. */
	const BATCH = 200;

	public function register() {
		add_action( self::HOOK, [ $this, 'run' ] );
		// Self-heal: make sure the event exists (and the legacy one is gone)
		// even on an already-active site — activation only runs once.
		add_action( 'init', [ __CLASS__, 'ensure_scheduled' ] );
	}

	/**
	 * Schedule the shared daily event if it isn't already, migrating away from
	 * the legacy featured-only hook when one is still scheduled.
	 */
	public static function ensure_scheduled() {
		if ( wp_next_scheduled( self::LEGACY_HOOK ) ) {
			wp_clear_scheduled_hook( self::LEGACY_HOOK );
		}
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

	/** Plugin deactivation hook target — clear both events so nothing lingers. */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::LEGACY_HOOK );
	}

	/** Cron callback: run every daily job. */
	public function run() {
		$this->expire_featured();
		$this->subscription_expiry();
	}

	// ─── Job 1 · Featured expiry ────────────────────────────────────────────

	/**
	 * Remove featured status from every vendor whose promotion window has
	 * elapsed. Idempotent and safe to run repeatedly.
	 *
	 * @return int Number of vendors expired this run.
	 */
	public function expire_featured() {
		$now = time();

		$ids = get_posts( [
			'post_type'      => self::cpt(),
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

	// ─── Job 2 · Subscription expiry + renewal reminders ────────────────────

	/**
	 * Sweep vendors on a free period: send the T-14/T-3 renewal reminders and
	 * flip lapsed windows to 'expired'.
	 *
	 * @return array{flipped:int,mailed:int}
	 */
	public function subscription_expiry() {
		$now = time();

		$ids = get_posts( [
			'post_type'      => self::cpt(),
			'post_status'    => 'any',
			'numberposts'    => self::BATCH,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'cache_results'  => false,
			'meta_query'     => [
				[ 'key' => OC_Vendor_Subscription::META_STATUS, 'value' => 'free' ],
				[ 'key' => OC_Vendor_Subscription::META_FREE_UNTIL, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ],
			],
		] );

		$flipped = 0;
		$mailed  = 0;

		foreach ( $ids as $id ) {
			$until = (int) get_post_meta( $id, OC_Vendor_Subscription::META_FREE_UNTIL, true );
			if ( $until <= 0 ) {
				continue;
			}

			// Lapsed → flip to expired (idempotent: the meta_query above only
			// matches status=free, so a flipped vendor never re-enters the sweep).
			if ( $until <= $now ) {
				update_post_meta( $id, OC_Vendor_Subscription::META_STATUS, 'expired' );

				/**
				 * Fires when a vendor's free period lapses and the status flips.
				 *
				 * @param int $id    Vendor post id.
				 * @param int $until GMT Unix timestamp the free period ended.
				 */
				do_action( 'oc_vendor_subscription_expired', $id, $until );
				do_action( 'oc_vendor_subscription_updated', $id, OC_Vendor_Subscription::get_vendor_subscription( $id ) );

				if ( function_exists( 'oc_debug_log' ) ) {
					oc_debug_log( 'subscription expiry: vendor flipped to expired', [ 'vendor' => $id, 'free_until' => $until ], true );
				}
				$flipped++;
				continue;
			}

			// Approaching → send each reminder once per free window. The stamp
			// stores the free_until it was sent for, so an extended window
			// re-arms the reminder and a re-run never re-sends.
			//
			// floor(), not ceil(): "ends in 14 days" means 14 FULL days remain,
			// so 14.8 days out is inside the T-14 band. ceil() would round that
			// to 15 and skip a vendor who is already within two weeks of expiry.
			$days_left = (int) floor( ( $until - $now ) / DAY_IN_SECONDS );
			if ( $days_left <= self::T3 ) {
				if ( (int) get_post_meta( $id, self::META_MAIL_T3, true ) !== $until ) {
					$this->send_renewal_mail( $id, $until, $days_left, 'T-3' );
					update_post_meta( $id, self::META_MAIL_T3, $until );
					$mailed++;
				}
			} elseif ( $days_left <= self::T14 ) {
				if ( (int) get_post_meta( $id, self::META_MAIL_T14, true ) !== $until ) {
					$this->send_renewal_mail( $id, $until, $days_left, 'T-14' );
					update_post_meta( $id, self::META_MAIL_T14, $until );
					$mailed++;
				}
			}
		}

		// Always log a summary — a manual Crontrol run should be visible in the
		// debug log even when nothing was due, so "did it run?" is answerable.
		if ( function_exists( 'oc_debug_log' ) ) {
			oc_debug_log(
				'subscription expiry sweep',
				[ 'scanned' => count( $ids ), 'flipped' => $flipped, 'mailed' => $mailed ],
				true
			);
		}

		return [ 'flipped' => $flipped, 'mailed' => $mailed ];
	}

	/**
	 * Send one renewal reminder to the vendor owner and log the wp_mail() bool.
	 *
	 * @param int    $vendor_id
	 * @param int    $until     GMT Unix timestamp the free period ends.
	 * @param int    $days_left
	 * @param string $threshold 'T-14' | 'T-3' (for the log line).
	 * @return bool  The wp_mail() result (false too when no valid recipient).
	 */
	private function send_renewal_mail( $vendor_id, $until, $days_left, $threshold ) {
		$vendor = get_post( $vendor_id );
		$owner  = $vendor ? get_userdata( (int) $vendor->post_author ) : null;
		$to     = ( $owner && is_email( $owner->user_email ) ) ? $owner->user_email : '';

		$sent = false;
		if ( '' !== $to && class_exists( 'OC_Mail' ) ) {
			$site      = get_bloginfo( 'name' );
			$end_date  = date_i18n( (string) get_option( 'date_format' ), $until );
			$dashboard = function_exists( 'oc_page_url' ) ? oc_page_url( 'vendor-dashboard' ) : home_url( '/' );

			if ( $days_left <= 0 ) {
				// Final day (0 full days remain — floor()ed above).
				/* translators: %s: site name */
				$subject = sprintf( __( 'Your free period ends today — %s', 'owambe-connect-core' ), $site );
			} else {
				$subject = sprintf(
					/* translators: 1: number of days, 2: site name */
					_n( 'Your free period ends in %1$d day — %2$s', 'Your free period ends in %1$d days — %2$s', $days_left, 'owambe-connect-core' ),
					$days_left,
					$site
				);
			}

			$body  = '<h2>' . esc_html__( 'Your free period is ending soon', 'owambe-connect-core' ) . '</h2>';
			$body .= '<p>' . sprintf(
				/* translators: 1: vendor business name, 2: end date */
				esc_html__( 'The free subscription period for %1$s ends on %2$s.', 'owambe-connect-core' ),
				'<strong>' . esc_html( $vendor->post_title ) . '</strong>',
				'<strong>' . esc_html( $end_date ) . '</strong>'
			) . '</p>';
			$body .= '<p>' . esc_html__( 'Choose a plan before then to keep your profile benefits without interruption.', 'owambe-connect-core' ) . '</p>';
			$body .= '<p><a href="' . esc_url( $dashboard ) . '">' . esc_html__( 'Open my vendor dashboard', 'owambe-connect-core' ) . '</a></p>';

			$sent = (bool) OC_Mail::send( $to, $subject, $body );
		}

		if ( function_exists( 'oc_debug_log' ) ) {
			oc_debug_log(
				'subscription renewal reminder ' . $threshold,
				[ 'vendor' => (int) $vendor_id, 'to' => $to, 'days_left' => (int) $days_left, 'sent' => $sent ],
				true
			);
		}

		return $sent;
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
		if ( ! $post_id || get_post_type( $post_id ) !== self::cpt() ) {
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

	private static function cpt() {
		return defined( 'OC_CPT' ) ? OC_CPT : 'oc_vendor';
	}
}
