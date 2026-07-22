<?php
/**
 * Owambe Connect Core — uninstall.
 * Runs only when the user explicitly deletes the plugin from wp-admin.
 *
 * @package OwambeConnect
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Only nuke if the admin opted in (default: keep data, since vendor records are valuable).
$keep = (bool) get_option( 'oc_uninstall_keep_data', true );
if ( $keep ) {
	delete_option( 'oc_uninstall_keep_data' );
	return;
}

// Vendor posts and their meta.
$vendor_ids = $wpdb->get_col( $wpdb->prepare(
	"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
	'oc_vendor'
) );
foreach ( (array) $vendor_ids as $id ) {
	wp_delete_post( (int) $id, true );
}

// Taxonomy terms.
$term_ids = $wpdb->get_col( $wpdb->prepare(
	"SELECT t.term_id FROM {$wpdb->terms} t
	 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
	 WHERE tt.taxonomy = %s",
	'oc_vendor_category'
) );
foreach ( (array) $term_ids as $tid ) {
	wp_delete_term( (int) $tid, 'oc_vendor_category' );
}

// Review posts (Phase 2) — delete before users so author checks don't block.
$review_ids = $wpdb->get_col( $wpdb->prepare(
	"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
	'oc_review'
) );
foreach ( (array) $review_ids as $id ) {
	wp_delete_post( (int) $id, true );
}

// Vendor users.
$vendor_users = get_users( [ 'role' => 'oc_vendor', 'fields' => 'ID' ] );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( (array) $vendor_users as $uid ) {
	wp_delete_user( (int) $uid );
}
remove_role( 'oc_vendor' );

// Client users (Phase 2).
$client_users = get_users( [ 'role' => 'oc_client', 'fields' => 'ID' ] );
foreach ( (array) $client_users as $uid ) {
	wp_delete_user( (int) $uid );
}
remove_role( 'oc_client' );

// Plugin pages.
foreach ( [ 'home', 'vendors', 'become-a-vendor', 'apply', 'vendor-login', 'vendor-dashboard', 'forgot-password', 'reset-password', 'about', 'contact', 'client-login', 'client-dashboard' ] as $slug ) {
	$page = get_page_by_path( $slug );
	if ( $page ) wp_delete_post( $page->ID, true );
}

// Phase 2 tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}oc_vendor_stats" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}oc_rsvps" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'oc_settings' );
delete_option( 'oc_vendor_seq' );
delete_option( 'oc_rewrite_version' );
delete_option( 'oc_uninstall_keep_data' );
