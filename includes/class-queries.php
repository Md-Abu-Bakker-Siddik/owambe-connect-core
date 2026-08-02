<?php
/**
 * Vendor query helpers — used by directory, featured, and category shortcodes.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Queries {

	/** Option holding aggregated search / category analytics counters. Not autoloaded. */
	const SEARCH_OPTION = 'oc_search_log';

	/** Max distinct keywords kept per keyword map — least-used are evicted beyond this. */
	const KEYWORD_CAP = 300;

	public static function directory( array $args = [] ) {
		$defaults = [
			'paged'    => max( 1, (int) get_query_var( 'paged', 1 ) ),
			'per_page' => (int) oc_get_setting( 'directory_per_page', 12 ),
			'category' => '',
			'search'   => '',
			'location' => '',
			'city'     => '',
			'cultural' => '', // cultural-specialty key, e.g. 'african_events'
			'nigerian' => '', // truthy → only Nigerian-events specialists
			'near_lat' => '', // radius search centre (from "near me" / city pick)
			'near_lng' => '',
			'radius'   => 0,  // miles; 0 = radius search off
		];
		$a = wp_parse_args( $args, $defaults );

		$query_args = [
			'post_type'      => OC_CPT,
			'post_status'    => OC_STATUS_APPROVED,
			'posts_per_page' => (int) $a['per_page'],
			'paged'          => (int) $a['paged'],
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$search_term = sanitize_text_field( $a['search'] );
		$meta_query = [];

		// Build meta_query for location/city filtering.
		if ( ! empty( $a['location'] ) ) {
			$meta_query[] = [
				'key'     => '_oc_location',
				'value'   => sanitize_text_field( $a['location'] ),
				'compare' => 'LIKE',
			];
		}
		if ( ! empty( $a['city'] ) ) {
			$meta_query[] = [
				'key'     => '_oc_location',
				'value'   => sanitize_text_field( $a['city'] ),
				'compare' => 'LIKE',
			];
		}
		// Cultural specialty — now term-backed: an indexed tax_query on the
		// `cultural_specialty` taxonomy (dual-write keeps terms == meta).
		// Falls back to the legacy serialized-meta LIKE only while the term
		// doesn't exist yet (pre-migration safety).
		$tax_query = [];
		if ( ! empty( $a['cultural'] ) ) {
			$cultural_slug = sanitize_key( $a['cultural'] );
			if ( class_exists( 'OC_Vendor_Tags' )
				&& taxonomy_exists( OC_Vendor_Tags::TAX_CULTURE )
				&& term_exists( $cultural_slug, OC_Vendor_Tags::TAX_CULTURE ) ) {
				$tax_query[] = [
					'taxonomy' => OC_Vendor_Tags::TAX_CULTURE,
					'field'    => 'slug',
					'terms'    => $cultural_slug,
				];
			} else {
				$meta_query[] = [
					'key'     => '_oc_cultural_specialties',
					'value'   => $cultural_slug,
					'compare' => 'LIKE',
				];
			}
		}
		// Nigerian-events specialists (separate boolean meta).
		if ( ! empty( $a['nigerian'] ) ) {
			$meta_query[] = [
				'key'     => '_oc_nigerian_specialty',
				'value'   => 'yes',
				'compare' => '=',
			];
		}
		if ( count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'AND';
		}
		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query;
		}

		// Handle category filter.
		if ( ! empty( $a['category'] ) ) {
			$tax_query[] = [
				'taxonomy' => OC_TAX,
				'field'    => 'slug',
				'terms'    => sanitize_title( $a['category'] ),
			];
		}
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		if ( $tax_query ) {
			$query_args['tax_query'] = $tax_query;
		}

		// If search term provided, use custom WHERE filter to search title + services.
		if ( ! empty( $search_term ) ) {
			$query_args['s'] = $search_term;
			add_filter( 'posts_where', [ __CLASS__, 'filter_posts_where_title_services' ], 10, 2 );
		}

		// Radius search (P12): coarse bounding-box meta_query prunes by the
		// numeric index, then an exact Haversine in posts_clauses filters the
		// box corners out and orders by distance (nearest first).
		$lat    = is_numeric( $a['near_lat'] ) ? (float) $a['near_lat'] : null;
		$lng    = is_numeric( $a['near_lng'] ) ? (float) $a['near_lng'] : null;
		$radius = max( 0, (float) $a['radius'] );
		$geo_on = null !== $lat && null !== $lng && $radius > 0
			&& $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
		if ( $geo_on ) {
			$d_lat = $radius / 69.0; // ~miles per degree latitude.
			$d_lng = $radius / max( 0.01, 69.0 * cos( deg2rad( $lat ) ) );
			$box   = [
				'relation' => 'AND',
				[ 'key' => '_oc_lat', 'value' => [ $lat - $d_lat, $lat + $d_lat ], 'compare' => 'BETWEEN', 'type' => 'DECIMAL(10,6)' ],
				[ 'key' => '_oc_lng', 'value' => [ $lng - $d_lng, $lng + $d_lng ], 'compare' => 'BETWEEN', 'type' => 'DECIMAL(10,6)' ],
			];
			$query_args['meta_query'] = empty( $query_args['meta_query'] )
				? $box
				: [ 'relation' => 'AND', $query_args['meta_query'], $box ];

			self::$geo_center = [ $lat, $lng, $radius ];
			add_filter( 'posts_clauses', [ __CLASS__, 'filter_posts_clauses_haversine' ], 10, 2 );
		}

		$q = new WP_Query( $query_args );

		if ( $geo_on ) {
			remove_filter( 'posts_clauses', [ __CLASS__, 'filter_posts_clauses_haversine' ], 10 );
			self::$geo_center = null;
		}

		if ( ! empty( $search_term ) ) {
			remove_filter( 'posts_where', [ __CLASS__, 'filter_posts_where_title_services' ], 10 );
		}

		self::maybe_log_search( $a, $q );

		return $q;
	}

	/** Radius-search centre while a directory geo query runs: [lat, lng, miles]. */
	private static $geo_center = null;

	/**
	 * Exact great-circle distance for radius search. Joins the coord meta,
	 * keeps rows within the radius (the bounding box already pruned the bulk)
	 * and orders nearest-first. acos() input is clamped to [-1, 1] so
	 * identical points can't produce NULL from float drift.
	 */
	public static function filter_posts_clauses_haversine( $clauses, $query ) {
		global $wpdb;
		if ( null === self::$geo_center ) {
			return $clauses;
		}
		list( $lat, $lng, $radius ) = self::$geo_center;

		$clauses['join']  .= $wpdb->prepare(
			" INNER JOIN {$wpdb->postmeta} oc_geo_lat ON oc_geo_lat.post_id = {$wpdb->posts}.ID AND oc_geo_lat.meta_key = %s" .
			" INNER JOIN {$wpdb->postmeta} oc_geo_lng ON oc_geo_lng.post_id = {$wpdb->posts}.ID AND oc_geo_lng.meta_key = %s",
			'_oc_lat', '_oc_lng'
		);

		$distance = $wpdb->prepare(
			'( 3959 * ACOS( LEAST( 1, GREATEST( -1,' .
			' COS( RADIANS(%f) ) * COS( RADIANS( oc_geo_lat.meta_value + 0 ) ) * COS( RADIANS( oc_geo_lng.meta_value + 0 ) - RADIANS(%f) )' .
			' + SIN( RADIANS(%f) ) * SIN( RADIANS( oc_geo_lat.meta_value + 0 ) ) ) ) ) )',
			$lat, $lng, $lat
		);

		$clauses['where']  .= $wpdb->prepare( " AND {$distance} <= %f", $radius );
		$clauses['orderby'] = "{$distance} ASC, " . $clauses['orderby'];

		return $clauses;
	}

	/**
	 * Custom WHERE filter to search in post_title and _oc_services meta field only.
	 * Removes post_content from search and adds services meta search.
	 */
	public static function filter_posts_where_title_services( $where, $query ) {
		global $wpdb;

		if ( ! isset( $query->query_vars['s'] ) || empty( $query->query_vars['s'] ) ) {
			return $where;
		}

		$search = esc_sql( $query->query_vars['s'] );

		// Remove post_content from the WHERE clause.
		$where = preg_replace(
			"/ OR \({$wpdb->posts}\.post_content LIKE[^)]*\)/",
			'',
			$where
		);

		// Add meta query for services.
		$where .= " OR ({$wpdb->posts}.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_oc_services' AND meta_value LIKE '%{$search}%'))";

		return $where;
	}

	public static function featured( $count = 6, $type = 'homepage', $category = '' ) {
		$type = sanitize_key( $type );
		$meta = [
			'relation' => 'AND',
			[ 'key' => '_oc_featured', 'value' => '1' ],
			[
				'relation' => 'OR',
				[ 'key' => '_oc_featured_type', 'value' => $type ],
				[ 'key' => '_oc_featured_type', 'value' => 'both' ],
			],
		];
		$args = [
			'post_type'      => OC_CPT,
			'post_status'    => OC_STATUS_APPROVED,
			'posts_per_page' => (int) $count,
			'meta_query'     => $meta,
			'orderby'        => 'rand',
		];
		if ( $category ) {
			$args['tax_query'] = [ [ 'taxonomy' => OC_TAX, 'field' => 'slug', 'terms' => sanitize_title( $category ) ] ];
		}
		$q = new WP_Query( $args );

		// Preserve the homepage's legacy fallback, but category promotion strips
		// must never label ordinary vendors as featured.
		if ( ! $q->have_posts() && 'homepage' === $type && ! $category ) {
			$q = new WP_Query( [
				'post_type'      => OC_CPT,
				'post_status'    => OC_STATUS_APPROVED,
				'posts_per_page' => (int) $count,
				'orderby'        => 'date',
				'order'          => 'DESC',
			] );
		}
		return $q;
	}

	/**
	 * Newest approved vendors for the homepage Recently Added carousel.
	 *
	 * @param int $count Maximum vendors to return.
	 * @return WP_Query
	 */
	public static function recently_added( $count = 6 ) {
		return new WP_Query( [
			'post_type'              => OC_CPT,
			'post_status'            => OC_STATUS_APPROVED,
			'posts_per_page'         => max( 1, min( 24, (int) $count ) ),
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_term_cache' => true,
			'update_post_meta_cache' => true,
		] );
	}

	/**
	 * Every approved vendor whose Premium plan currently grants paid access.
	 *
	 * The status branch mirrors OC_Vendor_Subscription::get_vendor_subscription():
	 * active/trialing subscriptions qualify, as does an unexpired free period
	 * carrying the Premium tier. Merely having stale `_oc_sub_plan=premium`
	 * metadata is deliberately not enough.
	 *
	 * @return WP_Query
	 */
	public static function premium_collection() {
		return new WP_Query( [
			'post_type'              => OC_CPT,
			'post_status'            => OC_STATUS_APPROVED,
			'posts_per_page'         => -1,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_term_cache' => true,
			'update_post_meta_cache' => true,
			'meta_query'             => [
				'relation' => 'AND',
				[
					'key'     => '_oc_sub_plan',
					'value'   => 'premium',
					'compare' => '=',
				],
				[
					'relation' => 'OR',
					[
						'key'     => '_oc_sub_status',
						'value'   => [ 'active', 'trialing' ],
						'compare' => 'IN',
					],
					[
						'relation' => 'AND',
						[
							'key'     => '_oc_sub_status',
							'value'   => 'free',
							'compare' => '=',
						],
						[
							'key'     => '_oc_free_until',
							'value'   => time(),
							'compare' => '>',
							'type'    => 'NUMERIC',
						],
					],
				],
			],
		] );
	}

	/**
	 * Latest published WordPress posts for the homepage Blog carousel.
	 *
	 * @param int $count Maximum posts to return.
	 * @return WP_Query
	 */
	public static function latest_posts( $count = 6 ) {
		return new WP_Query( [
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( 24, (int) $count ) ),
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		] );
	}

	/**
	 * Vendor categories in hierarchical order (P15): each parent followed by
	 * its children, A→Z within each level, with a `depth` property (0, 1, …)
	 * added so consumers can indent. Return shape is otherwise unchanged —
	 * consumers that ignore depth keep working, just with tree ordering.
	 * Orphaned children (parent deleted) are appended at depth 0 rather than
	 * silently dropped.
	 */
	public static function categories_with_counts() {
		$terms = get_terms( [
			'taxonomy'   => OC_TAX,
			'hide_empty' => false,
			'orderby'    => 'name',
		] );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$roots    = [];
		$children = [];
		foreach ( $terms as $t ) {
			if ( (int) $t->parent > 0 ) {
				$children[ (int) $t->parent ][] = $t;
			} else {
				$roots[] = $t;
			}
		}

		$out  = [];
		$walk = function ( $term, $depth ) use ( &$walk, &$out, &$children ) {
			$term->depth = $depth;
			$out[]       = $term;
			foreach ( $children[ (int) $term->term_id ] ?? [] as $child ) {
				$walk( $child, $depth + 1 );
			}
			unset( $children[ (int) $term->term_id ] );
		};
		foreach ( $roots as $root ) {
			$walk( $root, 0 );
		}
		foreach ( $children as $orphans ) {
			foreach ( $orphans as $orphan ) {
				$orphan->depth = 0;
				$out[]         = $orphan;
			}
		}
		return $out;
	}

	public static function pending_count() {
		$counts = wp_count_posts( OC_CPT );
		return isset( $counts->{OC_STATUS_PENDING} ) ? (int) $counts->{OC_STATUS_PENDING} : 0;
	}

	// =================================================================
	//  Search & category analytics  (aggregated in the SEARCH_OPTION option)
	// =================================================================

	/**
	 * Record one directory query into the aggregated search log. Called from
	 * directory() — the single funnel every front-end search and category
	 * browse passes through.
	 *
	 * Tracks four things:
	 *   1. Top keywords     — every non-empty free-text search term.
	 *   2. Empty searches   — keywords whose query returned 0 vendors.
	 *   3. Searched categories — the category filter used ALONGSIDE another
	 *      filter (text/location/city), i.e. the category picker in the search bar.
	 *   4. Clicked categories  — the category arrived at on its own, i.e. a
	 *      category grid card / pill that links to /vendors/?cat=slug.
	 *
	 * The click-vs-search split is a heuristic on the accompanying filters (the
	 * only signal available server-side): it is directionally right, not exact.
	 *
	 * @param array    $a Parsed directory() args (search, category, location, city, paged).
	 * @param WP_Query $q The executed query — read for found_posts.
	 */
	private static function maybe_log_search( array $a, $q ) {
		// Front-end, human, first page only — so pagination and background
		// requests don't inflate a single search into many.
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_preview() ) {
			return;
		}
		if ( (int) $a['paged'] > 1 || self::is_search_bot() ) {
			return;
		}
		// Don't let admins testing the directory pollute the numbers.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$term     = sanitize_text_field( (string) $a['search'] );
		$category = sanitize_title( (string) $a['category'] );
		if ( '' === $term && '' === $category ) {
			return; // plain directory landing — nothing to log.
		}

		$found = ( $q instanceof WP_Query ) ? (int) $q->found_posts : 0;
		$log   = self::get_search_log();

		// 1 + 2 — keyword search.
		if ( '' !== $term ) {
			$norm = self::normalize_keyword( $term );
			if ( '' !== $norm ) {
				$log['keywords'][ $norm ] = ( $log['keywords'][ $norm ] ?? 0 ) + 1;
				if ( 0 === $found ) {
					$log['empty'][ $norm ] = ( $log['empty'][ $norm ] ?? 0 ) + 1;
				}
			}
		}

		// 3 + 4 — category usage.
		if ( '' !== $category ) {
			$has_other_filter = ( '' !== $term ) || ! empty( $a['location'] ) || ! empty( $a['city'] );
			$bucket           = $has_other_filter ? 'cat_search' : 'cat_click';
			$log[ $bucket ][ $category ] = ( $log[ $bucket ][ $category ] ?? 0 ) + 1;
		}

		// Keep the unbounded keyword maps from growing without limit.
		$log['keywords'] = self::cap_map( $log['keywords'], self::KEYWORD_CAP );
		$log['empty']    = self::cap_map( $log['empty'], self::KEYWORD_CAP );

		update_option( self::SEARCH_OPTION, $log, false );
	}

	/**
	 * The stored search log, normalised so all four buckets are always arrays.
	 *
	 * @return array{keywords:array,empty:array,cat_search:array,cat_click:array}
	 */
	public static function get_search_log() {
		$log = get_option( self::SEARCH_OPTION, [] );
		if ( ! is_array( $log ) ) {
			$log = [];
		}
		foreach ( [ 'keywords', 'empty', 'cat_search', 'cat_click' ] as $bucket ) {
			if ( empty( $log[ $bucket ] ) || ! is_array( $log[ $bucket ] ) ) {
				$log[ $bucket ] = [];
			}
		}
		return $log;
	}

	/** Wipe all recorded search analytics (e.g. for an admin "reset" control). */
	public static function clear_search_log() {
		delete_option( self::SEARCH_OPTION );
	}

	/**
	 * Top free-text keywords, most-searched first.
	 *
	 * @return array<int, array{term:string,count:int}>
	 */
	public static function top_keywords( $limit = 20 ) {
		return self::rows_from_map( self::get_search_log()['keywords'], $limit );
	}

	/**
	 * Keywords that returned zero vendors, most-frequent first — the content gaps.
	 *
	 * @return array<int, array{term:string,count:int}>
	 */
	public static function top_empty_searches( $limit = 20 ) {
		return self::rows_from_map( self::get_search_log()['empty'], $limit );
	}

	/**
	 * Categories used as a search filter, most-used first (slug resolved to name).
	 *
	 * @return array<int, array{slug:string,name:string,count:int}>
	 */
	public static function top_searched_categories( $limit = 20 ) {
		return self::category_rows( self::get_search_log()['cat_search'], $limit );
	}

	/**
	 * Categories arrived at by a browse/click, most-clicked first.
	 *
	 * @return array<int, array{slug:string,name:string,count:int}>
	 */
	public static function top_clicked_categories( $limit = 20 ) {
		return self::category_rows( self::get_search_log()['cat_click'], $limit );
	}

	// ─── Internals ──────────────────────────────────────────────────────────

	/** Sort a term=>count map descending and shape it into display rows. */
	private static function rows_from_map( array $map, $limit ) {
		arsort( $map );
		$out = [];
		foreach ( array_slice( $map, 0, max( 1, (int) $limit ), true ) as $term => $count ) {
			$out[] = [ 'term' => (string) $term, 'count' => (int) $count ];
		}
		return $out;
	}

	/** Sort a slug=>count map descending, resolve names, shape into display rows. */
	private static function category_rows( array $map, $limit ) {
		arsort( $map );
		$out = [];
		foreach ( array_slice( $map, 0, max( 1, (int) $limit ), true ) as $slug => $count ) {
			$term  = get_term_by( 'slug', $slug, OC_TAX );
			$out[] = [
				'slug'  => (string) $slug,
				'name'  => $term ? $term->name : (string) $slug,
				'count' => (int) $count,
			];
		}
		return $out;
	}

	/** Trim a keyword map to its $cap most-used entries. */
	private static function cap_map( array $map, $cap ) {
		if ( count( $map ) <= (int) $cap ) {
			return $map;
		}
		arsort( $map );
		return array_slice( $map, 0, (int) $cap, true );
	}

	/** Lowercase, collapse whitespace and length-cap a search term for grouping. */
	private static function normalize_keyword( $term ) {
		$term = preg_replace( '/\s+/', ' ', trim( (string) $term ) );
		$term = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term, 'UTF-8' ) : strtolower( $term );
		return function_exists( 'mb_substr' ) ? mb_substr( $term, 0, 80, 'UTF-8' ) : substr( $term, 0, 80 );
	}

	/** Empty UA or obvious crawler UA — search bots must not skew the log. */
	private static function is_search_bot() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		return '' === $ua || (bool) preg_match( '/bot|crawl|spider|slurp|curl|wget/i', $ua );
	}
}
