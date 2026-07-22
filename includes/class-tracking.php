<?php
/**
 * Owambe Connect — vendor analytics & enquiry tracking (Phase 2, §6.3).
 *
 * Records profile views (server-side, single funnel via
 * OC_Shortcodes::vendor_profile()) and per-channel contact clicks (frontend
 * sendBeacon → the plugin's first wp_ajax_nopriv_* endpoint, `oc_track`)
 * into the {$wpdb->prefix}oc_vendor_stats table (one row per
 * vendor/date/metric, created by OC_Activator). Also exposes the static
 * read API that OC_Admin_Analytics and the vendor-facing analytics tab
 * both reuse.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Tracking {

	/** Every metric the stats table knows about. */
	const METRICS = [ 'view', 'click_whatsapp', 'click_email', 'click_instagram', 'click_facebook', 'click_website' ];

	/** Per-IP beacon throttle: max events per window. */
	const THROTTLE_MAX = 30;

	/** Per-IP beacon throttle window in seconds (10 minutes). */
	const THROTTLE_WINDOW = 600;

	/** View dedupe window in seconds (6 hours). */
	const VIEW_DEDUPE_WINDOW = 6 * HOUR_IN_SECONDS;

	public function register() {
		add_action( 'wp_ajax_oc_track',        [ $this, 'handle' ] );
		add_action( 'wp_ajax_nopriv_oc_track', [ $this, 'handle' ] );
	}

	// ─── Click beacon endpoint ──────────────────────────────────────────────

	/**
	 * `oc_track` AJAX handler (logged-in AND anonymous).
	 *
	 * Deliberately NO nonce: vendor profiles are served from page/CDN cache,
	 * so a nonce baked into cached markup goes stale within hours and would
	 * silently kill tracking (§4.6). Nothing user-visible is mutated here —
	 * strict input validation, the per-IP throttle and the bot filter below
	 * are the guards instead.
	 */
	public function handle() {
		$vendor_id = isset( $_POST['vendor_id'] ) ? absint( wp_unslash( $_POST['vendor_id'] ) ) : 0;
		$metric    = isset( $_POST['metric'] ) ? sanitize_key( wp_unslash( $_POST['metric'] ) ) : '';

		// 'view' is recorded server-side only (maybe_record_profile_view) — the
		// public beacon must never be able to inflate view counts.
		if ( ! $vendor_id || 'view' === $metric || ! in_array( $metric, self::METRICS, true ) ) {
			wp_send_json_error( null, 400 );
		}

		$vendor = get_post( $vendor_id );
		if ( ! $vendor || OC_CPT !== $vendor->post_type || OC_STATUS_APPROVED !== $vendor->post_status ) {
			wp_send_json_error( null, 400 );
		}

		// Bots: accept silently (200) but record nothing — checked BEFORE the
		// throttle so crawler traffic doesn't consume real visitors' budget.
		if ( self::is_bot() ) {
			wp_send_json_success();
		}

		// Per-IP + per-vendor throttle (trustworthy IP via oc_client_ip). Scoped
		// per vendor so many real visitors behind one NAT/CGNAT address don't
		// share a single global budget and silently lose click tracking.
		$key   = 'oc_track_rl_' . md5( oc_client_ip() . '|' . $vendor_id );
		$count = (int) get_transient( $key );
		if ( $count >= self::THROTTLE_MAX ) {
			wp_send_json_error( null, 429 );
		}
		set_transient( $key, $count + 1, self::THROTTLE_WINDOW );

		static::record( $vendor_id, $metric );

		// Signed-in clients get the vendor pushed onto their "recently
		// contacted" list. This MUTATES user-visible state, so — unlike the
		// anonymous stats write above — it must be CSRF-protected: require the
		// same-origin nonce that oc-frontend.js attaches for logged-in users.
		// (Logged-in users are never served stale full-page cache, so a fresh
		// nonce is always available — the cache rationale only covers anon hits.)
		if ( is_user_logged_in() && class_exists( 'OC_Client' ) && OC_Client::is_client() ) {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( wp_verify_nonce( $nonce, 'oc_saved_nonce' ) ) {
				OC_Client::push_recent_contact( get_current_user_id(), $vendor_id, str_replace( 'click_', '', $metric ) );
			}
		}

		wp_send_json_success();
	}

	// ─── Write API ──────────────────────────────────────────────────────────

	/**
	 * Increment a metric for a vendor for today. One row per
	 * vendor/date/metric (PK), so writes are a single upsert.
	 *
	 * @return bool True on success, false on validation/DB failure.
	 */
	public static function record( $vendor_id, $metric ) {
		global $wpdb;

		$vendor_id = absint( $vendor_id );
		if ( ! $vendor_id || ! in_array( $metric, self::METRICS, true ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'oc_vendor_stats';
		$today = current_time( 'Y-m-d' );

		$result = $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (vendor_id, stat_date, metric, `count`)
			 VALUES (%d, %s, %s, 1)
			 ON DUPLICATE KEY UPDATE `count` = `count` + 1, stat_date = %s",
			$vendor_id,
			$today,
			$metric,
			$today
		) );

		if ( false === $result ) {
			if ( function_exists( 'oc_debug_log' ) ) {
				oc_debug_log( 'oc_tracking_record_failed', [
					'vendor_id' => $vendor_id,
					'metric'    => $metric,
					'db_error'  => $wpdb->last_error,
				] );
			}
			return false;
		}
		return true;
	}

	/**
	 * Server-side profile-view funnel — called from
	 * OC_Shortcodes::vendor_profile() before the template returns. Applies
	 * every exclusion (admin/feed/REST/preview/self/bot) plus a per-IP
	 * transient dedupe so reload spam doesn't inflate counts.
	 */
	public static function maybe_record_profile_view( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || is_admin() ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || OC_CPT !== $post->post_type || OC_STATUS_APPROVED !== $post->post_status ) {
			return;
		}

		if ( is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_preview() ) {
			return;
		}

		// Self-views never count: the owning vendor (OC_CAP_EDIT_OWN — vendors
		// lack edit_post, that check would only exclude admins) and admins.
		if ( current_user_can( OC_CAP_EDIT_OWN, $post_id ) || current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( self::is_bot() ) {
			return;
		}

		// Per-IP dedupe: one view per profile per 6 hours.
		$key = 'oc_pv_' . md5( oc_client_ip() . '|' . $post_id );
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, self::VIEW_DEDUPE_WINDOW );

		static::record( $post_id, 'view' );
	}

	// ─── Read API (shared by OC_Admin_Analytics + vendor analytics tab) ─────

	/**
	 * Per-metric sums for one vendor over the last $days (window ends today).
	 * Always returns every metric key, zero-filled.
	 *
	 * @return array metric => int
	 */
	public static function counts( $vendor_id, $days = 30 ) {
		global $wpdb;

		$vendor_id = absint( $vendor_id );
		$table     = $wpdb->prefix . 'oc_vendor_stats';
		[ $from, $to ] = self::window( $days );

		$out = array_fill_keys( self::METRICS, 0 );
		if ( ! $vendor_id ) {
			return $out;
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT metric, SUM(`count`) AS total
			 FROM {$table}
			 WHERE vendor_id = %d AND stat_date BETWEEN %s AND %s
			 GROUP BY metric",
			$vendor_id,
			$from,
			$to
		), ARRAY_A );

		foreach ( (array) $rows as $row ) {
			$metric = (string) ( $row['metric'] ?? '' );
			if ( isset( $out[ $metric ] ) ) {
				$out[ $metric ] = (int) $row['total'];
			}
		}
		return $out;
	}

	/**
	 * Per-metric sums for one vendor over an explicit [from,to] date window
	 * (Y-m-d). Range-based sibling of counts() — used by the admin per-vendor
	 * analytics view where the date filter is a real range, not "last N days".
	 * Always returns every metric key, zero-filled.
	 *
	 * @return array metric => int
	 */
	public static function counts_range( $vendor_id, $from, $to ) {
		global $wpdb;

		$vendor_id = absint( $vendor_id );
		$table     = $wpdb->prefix . 'oc_vendor_stats';
		[ $from, $to ] = self::clamp_range( $from, $to );

		$out = array_fill_keys( self::METRICS, 0 );
		if ( ! $vendor_id ) {
			return $out;
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT metric, SUM(`count`) AS total
			 FROM {$table}
			 WHERE vendor_id = %d AND stat_date BETWEEN %s AND %s
			 GROUP BY metric",
			$vendor_id,
			$from,
			$to
		), ARRAY_A );

		foreach ( (array) $rows as $row ) {
			$metric = (string) ( $row['metric'] ?? '' );
			if ( isset( $out[ $metric ] ) ) {
				$out[ $metric ] = (int) $row['total'];
			}
		}
		return $out;
	}

	/**
	 * Site-wide totals over the last $days (window ends today).
	 *
	 * @return array [ 'views' => int, 'clicks' => int ]
	 */
	public static function totals( $days = 30 ) {
		[ $from, $to ] = self::window( $days );
		return self::totals_range( $from, $to );
	}

	/**
	 * Site-wide totals over an explicit [from,to] date window (Y-m-d). Use this
	 * when the caller has a real range (e.g. the admin analytics date filter)
	 * rather than a "last N days ending today" span.
	 *
	 * @return array [ 'views' => int, 'clicks' => int ]
	 */
	public static function totals_range( $from, $to ) {
		global $wpdb;

		$table = $wpdb->prefix . 'oc_vendor_stats';
		[ $from, $to ] = self::clamp_range( $from, $to );
		$click_like = $wpdb->esc_like( 'click_' ) . '%';

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COALESCE( SUM( CASE WHEN metric = 'view' THEN `count` ELSE 0 END ), 0 ) AS views,
				COALESCE( SUM( CASE WHEN metric LIKE %s THEN `count` ELSE 0 END ), 0 ) AS clicks
			 FROM {$table}
			 WHERE stat_date BETWEEN %s AND %s",
			$click_like,
			$from,
			$to
		), ARRAY_A );

		return [
			'views'  => (int) ( $row['views'] ?? 0 ),
			'clicks' => (int) ( $row['clicks'] ?? 0 ),
		];
	}

	/**
	 * Most-viewed vendors over an explicit [from,to] window (Y-m-d), ranked by
	 * profile views then contact clicks. Skips rows whose vendor post no longer
	 * exists or is not approved, so the leaderboard only shows live vendors.
	 *
	 * @return array<int, array{ vendor_id:int, title:string, views:int, clicks:int }>
	 */
	public static function top_vendors_range( $from, $to, $limit = 8 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'oc_vendor_stats';
		[ $from, $to ] = self::clamp_range( $from, $to );
		$click_like = $wpdb->esc_like( 'click_' ) . '%';
		$limit      = max( 1, (int) $limit );

		// Over-fetch so we can drop deleted/unapproved vendors and still fill $limit.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT vendor_id,
				COALESCE( SUM( CASE WHEN metric = 'view'  THEN `count` ELSE 0 END ), 0 ) AS views,
				COALESCE( SUM( CASE WHEN metric LIKE %s    THEN `count` ELSE 0 END ), 0 ) AS clicks
			 FROM {$table}
			 WHERE stat_date BETWEEN %s AND %s
			 GROUP BY vendor_id
			 HAVING views > 0 OR clicks > 0
			 ORDER BY views DESC, clicks DESC
			 LIMIT %d",
			$click_like,
			$from,
			$to,
			$limit * 3
		), ARRAY_A );

		$out = [];
		foreach ( (array) $rows as $row ) {
			$vendor_id = (int) $row['vendor_id'];
			$post      = get_post( $vendor_id );
			if ( ! $post || OC_CPT !== $post->post_type || OC_STATUS_APPROVED !== $post->post_status ) {
				continue;
			}
			$out[] = [
				'vendor_id' => $vendor_id,
				'title'     => get_the_title( $vendor_id ),
				'views'     => (int) $row['views'],
				'clicks'    => (int) $row['clicks'],
			];
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Daily views + clicks over the last $days (window ends today), for one
	 * vendor or site-wide ($vendor_id = 0). Zero-filled for every day in the
	 * window, ordered oldest → newest — chart-ready.
	 *
	 * @return array 'Y-m-d' => [ 'views' => int, 'clicks' => int ]
	 */
	public static function timeseries( $days = 30, $vendor_id = 0 ) {
		[ $from, $to ] = self::window( $days );
		return self::timeseries_range( $from, $to, $vendor_id );
	}

	/**
	 * Daily views + clicks over an explicit [from,to] window (Y-m-d), zero-filled
	 * and ordered oldest → newest. Range-based sibling of timeseries().
	 *
	 * @return array 'Y-m-d' => [ 'views' => int, 'clicks' => int ]
	 */
	public static function timeseries_range( $from, $to, $vendor_id = 0 ) {
		global $wpdb;

		$vendor_id = absint( $vendor_id );
		$table     = $wpdb->prefix . 'oc_vendor_stats';
		[ $from, $to ] = self::clamp_range( $from, $to );
		$click_like = $wpdb->esc_like( 'click_' ) . '%';

		$sql  = "SELECT stat_date,
				COALESCE( SUM( CASE WHEN metric = 'view' THEN `count` ELSE 0 END ), 0 ) AS views,
				COALESCE( SUM( CASE WHEN metric LIKE %s THEN `count` ELSE 0 END ), 0 ) AS clicks
			 FROM {$table}
			 WHERE stat_date BETWEEN %s AND %s";
		$args = [ $click_like, $from, $to ];
		if ( $vendor_id > 0 ) {
			$sql   .= ' AND vendor_id = %d';
			$args[] = $vendor_id;
		}
		$sql .= ' GROUP BY stat_date';

		$rows    = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		$by_date = [];
		foreach ( (array) $rows as $row ) {
			$by_date[ (string) $row['stat_date'] ] = [
				'views'  => (int) $row['views'],
				'clicks' => (int) $row['clicks'],
			];
		}

		$out = [];
		$ts  = strtotime( $from . ' UTC' );
		$end = strtotime( $to . ' UTC' );
		while ( $ts <= $end ) {
			$day         = gmdate( 'Y-m-d', $ts );
			$out[ $day ] = $by_date[ $day ] ?? [ 'views' => 0, 'clicks' => 0 ];
			$ts         += DAY_IN_SECONDS;
		}
		return $out;
	}

	// ─── Internals ──────────────────────────────────────────────────────────

	/**
	 * [$from, $to] Y-m-d strings for a $days window ending today
	 * (site-local "today" via current_time).
	 */
	private static function window( $days ) {
		$days = max( 1, (int) $days );
		$to   = current_time( 'Y-m-d' );
		$from = gmdate( 'Y-m-d', strtotime( $to . ' UTC' ) - ( $days - 1 ) * DAY_IN_SECONDS );
		return [ $from, $to ];
	}

	/**
	 * Normalise an explicit [from,to] window: coerce to Y-m-d, order correctly,
	 * clamp $to to today (stats can't exist in the future), and cap the total
	 * span so a pathological "all time" range can't zero-fill millions of rows.
	 *
	 * @return array [ from Y-m-d, to Y-m-d ]
	 */
	private static function clamp_range( $from, $to ) {
		$today = current_time( 'Y-m-d' );

		$to_ts   = strtotime( (string) $to );
		$from_ts = strtotime( (string) $from );
		if ( ! $to_ts )   { $to_ts = strtotime( $today ); }
		if ( ! $from_ts ) { $from_ts = $to_ts; }

		// Order + clamp the end to today.
		if ( $from_ts > $to_ts ) { [ $from_ts, $to_ts ] = [ $to_ts, $from_ts ]; }
		$today_ts = strtotime( $today );
		if ( $to_ts > $today_ts ) { $to_ts = $today_ts; }

		// Cap the span (~2 years) to bound the zero-fill loop.
		$max_span = 731 * DAY_IN_SECONDS;
		if ( $to_ts - $from_ts > $max_span ) {
			$from_ts = $to_ts - $max_span;
		}

		return [ gmdate( 'Y-m-d', $from_ts ), gmdate( 'Y-m-d', $to_ts ) ];
	}

	/** Empty UA or obvious crawler UA. */
	private static function is_bot() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		return '' === $ua || (bool) preg_match( '/bot|crawl|spider|slurp|curl|wget/i', $ua );
	}
}
