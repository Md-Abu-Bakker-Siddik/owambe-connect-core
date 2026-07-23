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
		// Cultural specialty — meta stores a serialized array of keys, so a LIKE
		// on the key string matches vendors who selected it.
		if ( ! empty( $a['cultural'] ) ) {
			$meta_query[] = [
				'key'     => '_oc_cultural_specialties',
				'value'   => sanitize_key( $a['cultural'] ),
				'compare' => 'LIKE',
			];
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
			$query_args['tax_query'] = [
				[
					'taxonomy' => OC_TAX,
					'field'    => 'slug',
					'terms'    => sanitize_title( $a['category'] ),
				],
			];
		}

		// If search term provided, use custom WHERE filter to search title + services.
		if ( ! empty( $search_term ) ) {
			$query_args['s'] = $search_term;
			add_filter( 'posts_where', [ __CLASS__, 'filter_posts_where_title_services' ], 10, 2 );
		}

		$q = new WP_Query( $query_args );

		if ( ! empty( $search_term ) ) {
			remove_filter( 'posts_where', [ __CLASS__, 'filter_posts_where_title_services' ], 10 );
		}

		self::maybe_log_search( $a, $q );

		return $q;
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

	public static function featured( $count = 6 ) {
		$q = new WP_Query( [
			'post_type'      => OC_CPT,
			'post_status'    => OC_STATUS_APPROVED,
			'posts_per_page' => (int) $count,
			'meta_query'     => [
				[ 'key' => '_oc_featured', 'value' => '1' ],
			],
			'orderby'        => 'rand',
		] );

		// Fall back to most recent if no featured vendors yet.
		if ( ! $q->have_posts() ) {
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

	public static function categories_with_counts() {
		return get_terms( [
			'taxonomy'   => OC_TAX,
			'hide_empty' => false,
			'orderby'    => 'name',
		] );
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
