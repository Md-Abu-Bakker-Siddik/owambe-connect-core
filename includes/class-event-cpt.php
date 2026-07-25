<?php
/**
 * Event CPT registration for Owambe Connect.
 *
 * Public (front-end viewable) but strictly unlisted: excluded from search,
 * no archive, and hidden from the REST API. The admin UI/menu is enabled so
 * events can be managed from the WP Admin sidebar.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Event_CPT {

	/** Bump when the rewrite rules change to trigger a one-time re-flush. */
	const REWRITE_VERSION = 'event-1';

	public function register() {
		add_action( 'init', [ __CLASS__, 'register_post_type' ] );

		// Flush rewrite rules once after the CPT is registered (priority 11 >
		// the CPT's 10) so already-active installs pick up the /event/ permalink
		// without a manual re-activation. Guarded so it runs only when needed.
		add_action( 'init', [ __CLASS__, 'maybe_flush_rewrites' ], 11 );

		// Slug hardening + SEO guard for unlisted events.
		add_filter( 'wp_insert_post_data', [ __CLASS__, 'tokenize_slug_on_publish' ], 10, 2 );
		add_filter( 'wp_robots',           [ __CLASS__, 'noindex_single_event' ] );
	}

	/**
	 * One-time rewrite flush: fixes 404s on event permalinks after this update
	 * ships to an already-active site. flush_rewrite_rules() is expensive, so
	 * an option flag ensures it fires at most once per REWRITE_VERSION.
	 */
	public static function maybe_flush_rewrites() {
		if ( self::REWRITE_VERSION === get_option( 'oc_event_rewrite_version' ) ) {
			return;
		}
		flush_rewrite_rules();
		update_option( 'oc_event_rewrite_version', self::REWRITE_VERSION );
	}

	/**
	 * Append a 20-char cryptographic token to an event's slug the first time it
	 * is published, so the public URL is effectively unguessable. Runs before
	 * the row is written (wp_insert_post_data); wp_unique_post_slug still runs
	 * afterwards, but the random token already guarantees uniqueness.
	 *
	 * @param array $data    Sanitised, slashed post data about to be saved.
	 * @param array $postarr Raw post array (holds the ID for existing posts).
	 * @return array
	 */
	public static function tokenize_slug_on_publish( $data, $postarr ) {
		if ( 'oc_event' !== ( isset( $data['post_type'] ) ? $data['post_type'] : '' ) ) {
			return $data;
		}
		if ( 'publish' !== ( isset( $data['post_status'] ) ? $data['post_status'] : '' ) ) {
			return $data;
		}

		// Only on the FIRST publish: skip if it was already published, or if the
		// slug already carries a token (e.g. unpublished then re-published).
		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
			return $data;
		}
		$current = isset( $data['post_name'] ) ? (string) $data['post_name'] : '';
		if ( preg_match( '/-[0-9a-f]{20}$/', $current ) ) {
			return $data;
		}

		$base  = '' !== $current ? $current : sanitize_title( isset( $data['post_title'] ) ? $data['post_title'] : '' );
		$token = bin2hex( random_bytes( 10 ) ); // 20 hex chars, cryptographically secure.

		$data['post_name'] = sanitize_title( $base . '-' . $token );

		return $data;
	}

	/**
	 * Keep single event pages out of search engines: force noindex, nofollow.
	 *
	 * @param array $robots Directives keyed for the robots meta tag.
	 * @return array
	 */
	public static function noindex_single_event( $robots ) {
		if ( is_singular( 'oc_event' ) ) {
			unset( $robots['index'], $robots['follow'] );
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	public static function register_post_type() {
		register_post_type( 'oc_event', [
			'labels' => [
				'name'          => __( 'Events',          'owambe-connect-core' ),
				'singular_name' => __( 'Event',           'owambe-connect-core' ),
				'add_new'       => __( 'Add New Event',   'owambe-connect-core' ),
				'add_new_item'  => __( 'Add New Event',   'owambe-connect-core' ),
				'edit_item'     => __( 'Edit Event',      'owambe-connect-core' ),
				'new_item'      => __( 'New Event',       'owambe-connect-core' ),
				'view_item'     => __( 'View Event',      'owambe-connect-core' ),
				'search_items'  => __( 'Search Events',   'owambe-connect-core' ),
				'not_found'     => __( 'No events found', 'owambe-connect-core' ),
				'menu_name'     => __( 'Events',          'owambe-connect-core' ),
			],
			// Public (front-end viewable) but strictly unlisted.
			'public'              => true,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'show_in_rest'        => false,
			'publicly_queryable'  => true,
			// Show the admin UI + sidebar menu so events can be managed.
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-calendar-alt',
			// Only title, editor, and custom fields — no thumbnail/excerpt/etc.
			'supports'            => [ 'title', 'editor', 'custom-fields' ],
			'capability_type'     => 'post',
			'rewrite'             => [ 'slug' => 'event', 'with_front' => false ],
		] );
	}
}
