<?php
/**
 * Vendor query helpers — used by directory, featured, and category shortcodes.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Queries {

	public static function directory( array $args = [] ) {
		$defaults = [
			'paged'    => max( 1, (int) get_query_var( 'paged', 1 ) ),
			'per_page' => (int) oc_get_setting( 'directory_per_page', 12 ),
			'category' => '',
			'search'   => '',
			'location' => '',
			'city'     => '',
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
}
