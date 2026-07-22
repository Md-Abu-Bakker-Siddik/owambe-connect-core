<?php
/**
 * Vendor CPT, taxonomy, custom statuses, role, and meta registration.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_CPT_Manager {

	/**
	 * Custom SVG mark mirroring the Owambe Connect logo's interlocking
	 * O + C. base64-encoded so WordPress accepts it as menu_icon.
	 */
	public static function menu_icon_svg() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><g fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="7.5" cy="10" r="4.6"/><path d="M16.6 7.4a4.6 4.6 0 1 0 0 5.2"/></g></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	public function register() {
		add_action( 'init', [ __CLASS__, 'register_post_type' ] );
		add_action( 'init', [ __CLASS__, 'register_taxonomy' ] );
		add_action( 'init', [ __CLASS__, 'register_statuses' ] );
		add_action( 'init', [ __CLASS__, 'register_meta' ] );
		add_action( 'init', [ __CLASS__, 'register_role' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'public_query_only_approved' ] );
		add_filter( 'map_meta_cap',   [ __CLASS__, 'map_edit_own_vendor_cap' ], 10, 4 );

		// Single source of truth for vendor status transitions:
		// emails the vendor + fires oc_vendor_status_changed for activity logging.
		add_action( 'transition_post_status', [ __CLASS__, 'on_vendor_transition' ], 10, 3 );
	}

	public static function register_post_type() {
		register_post_type( OC_CPT, [
			'labels' => [
				'name'               => __( 'Vendors',          'owambe-connect-core' ),
				'singular_name'      => __( 'Vendor',           'owambe-connect-core' ),
				'add_new'            => __( 'Add New Vendor',   'owambe-connect-core' ),
				'add_new_item'       => __( 'Add New Vendor',   'owambe-connect-core' ),
				'edit_item'          => __( 'Edit Vendor',      'owambe-connect-core' ),
				'new_item'           => __( 'New Vendor',       'owambe-connect-core' ),
				'view_item'          => __( 'View Vendor',      'owambe-connect-core' ),
				'search_items'       => __( 'Search Vendors',   'owambe-connect-core' ),
				'not_found'          => __( 'No vendors found', 'owambe-connect-core' ),
				'menu_name'          => __( 'Vendors',          'owambe-connect-core' ),
			],
			'public'              => true,
			'has_archive'         => false,
			'show_in_rest'        => true,
			'menu_icon'           => self::menu_icon_svg(),
			'menu_position'       => 20,
			'supports'            => [ 'title', 'editor', 'thumbnail', 'author' ],
			'rewrite'             => [ 'slug' => 'vendor', 'with_front' => false ],
			'capability_type'     => 'post',
			'exclude_from_search' => false,
		] );
	}

	public static function register_taxonomy() {
		register_taxonomy( OC_TAX, OC_CPT, [
			'labels' => [
				'name'          => __( 'Categories', 'owambe-connect-core' ),
				'singular_name' => __( 'Category',   'owambe-connect-core' ),
				'menu_name'     => __( 'Categories', 'owambe-connect-core' ),
			],
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => [ 'slug' => 'vendors/category', 'with_front' => false ],
		] );
	}

	public static function register_statuses() {
		register_post_status( OC_STATUS_PENDING, [
			'label'                     => _x( 'Pending Review', 'post status', 'owambe-connect-core' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of pending vendors */
			'label_count'               => _n_noop( 'Pending Review <span class="count">(%s)</span>', 'Pending Review <span class="count">(%s)</span>', 'owambe-connect-core' ),
		] );

		register_post_status( OC_STATUS_REJECTED, [
			'label'                     => _x( 'Needs Changes', 'post status', 'owambe-connect-core' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Needs Changes <span class="count">(%s)</span>', 'Needs Changes <span class="count">(%s)</span>', 'owambe-connect-core' ),
		] );
	}

	public static function register_meta() {
		foreach ( oc_vendor_fields() as $key => $field ) {
			register_post_meta( OC_CPT, $key, [
				'show_in_rest'  => false,
				'single'        => true,
				'type'          => 'multi' === $field['type'] ? 'array' : 'string',
				'auth_callback' => static function () { return current_user_can( 'edit_posts' ); },
			] );
		}
	}

	public static function register_role() {
		if ( ! get_role( OC_ROLE ) ) {
			add_role( OC_ROLE, __( 'Vendor', 'owambe-connect-core' ), [
				'read'              => true,
				OC_CAP_EDIT_OWN     => true,
				'upload_files'      => true,
			] );
		} else {
			$role = get_role( OC_ROLE );
			$role->add_cap( OC_CAP_EDIT_OWN );
			$role->add_cap( 'upload_files' );
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( OC_CAP_EDIT_OWN );
		}
	}

	/**
	 * Maps the custom 'oc_edit_own_vendor' cap to ownership of the vendor post.
	 */
	public static function map_edit_own_vendor_cap( $caps, $cap, $user_id, $args ) {
		if ( OC_CAP_EDIT_OWN !== $cap ) {
			return $caps;
		}
		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( ! $post_id ) {
			return [ 'do_not_allow' ];
		}
		return oc_user_can_edit_vendor( $user_id, $post_id ) ? [ OC_CAP_EDIT_OWN ] : [ 'do_not_allow' ];
	}

	/**
	 * Central handler for any oc_vendor status change.
	 *
	 * Triggers vendor email + activity log on every transition, regardless of
	 * which path caused it (Approve button, Reject button, Quick Edit, bulk
	 * action, or a programmatic wp_update_post in some future feature).
	 *
	 * Skips when the post author triggered the change themselves (e.g. a
	 * vendor re-saving their rejected listing flips it to pending — they
	 * already know, no need to email).
	 */
	public static function on_vendor_transition( $new_status, $old_status, $post ) {
		if ( ! $post instanceof WP_Post || OC_CPT !== $post->post_type ) return;
		if ( $new_status === $old_status ) return;
		if ( 'auto-draft' === $new_status || 'new' === $old_status ) return; // initial creation noise

		$actor_id  = (int) get_current_user_id();
		$author_id = (int) $post->post_author;

		// "Self-action" = a vendor (no admin caps) editing their own listing from the
		// frontend dashboard. Skip the email-to-self in that case.
		//
		// IMPORTANT: do NOT just compare actor==author — admin-created vendors often
		// have post_author = the admin's user ID. We'd then misclassify every admin
		// edit as self-action and silently skip the vendor email.
		$actor_is_admin  = $actor_id && user_can( $actor_id, 'manage_options' );
		$is_self_action  = $actor_id && $actor_id === $author_id && ! $actor_is_admin;

		if ( ! $is_self_action ) {
			if ( OC_STATUS_APPROVED === $new_status ) {
				OC_Mail::vendor_approved( $post->ID );
			} elseif ( OC_STATUS_REJECTED === $new_status ) {
				$reason = (string) get_post_meta( $post->ID, '_oc_rejection_note', true );
				OC_Mail::vendor_rejected( $post->ID, $reason );
			} elseif ( OC_STATUS_PENDING === $new_status && OC_STATUS_APPROVED === $old_status ) {
				// Admin un-published an approved vendor (sent back to pending). Notify.
				OC_Mail::vendor_rejected( $post->ID, __( 'Your listing has been put back into pending review. The admin will follow up.', 'owambe-connect-core' ) );
			}
		}

		// Always fire the activity hook (whether self-action or admin-action — actor distinguishes).
		do_action( 'oc_vendor_status_changed', $post->ID, $new_status, $old_status, $actor_id, $is_self_action );

		// Legacy hooks kept for backward compatibility.
		if ( OC_STATUS_APPROVED === $new_status ) {
			do_action( 'oc_after_vendor_approved', $post->ID );
		} elseif ( OC_STATUS_REJECTED === $new_status ) {
			do_action( 'oc_after_vendor_rejected', $post->ID, (string) get_post_meta( $post->ID, '_oc_rejection_note', true ) );
		}
	}

	/**
	 * Hide non-approved vendor posts from public queries.
	 */
	public static function public_query_only_approved( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$post_type = $query->get( 'post_type' );
		if ( OC_CPT === $post_type || ( is_array( $post_type ) && in_array( OC_CPT, $post_type, true ) ) ) {
			$query->set( 'post_status', [ OC_STATUS_APPROVED ] );
		}
	}
}
