<?php
/**
 * Dynamic vendor tag taxonomies (client change, Aug 2026).
 *
 * Replaces the hardcoded cultural-specialty / vendor-tag option arrays with
 * two real taxonomies the admin can manage from wp-admin without code edits:
 *
 *  - `cultural_specialty` — flat. Slugs mirror the legacy meta values stored
 *    in `_oc_cultural_specialties` (african, caribbean, …) so the migration
 *    maps 1:1.
 *  - `vendor_tag` — hierarchical. The dashboard's accordion groups are the
 *    parent terms; individual tags are their children. Legacy meta
 *    `_oc_vendor_tags` stores display labels, so child term NAMES must stay
 *    byte-identical to the legacy strings for the migration to match.
 *
 * The seeder is idempotent and versioned: activation calls it directly, and
 * an admin_init self-heal covers the deploy-without-reactivation case (files
 * replaced on the server, activation hook never fires).
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Vendor_Tags {

	const TAX_CULTURE = 'cultural_specialty';
	const TAX_TAG     = 'vendor_tag';

	/** Bump when seed content changes so the self-heal re-runs once. */
	const SEED_VERSION = 1;
	const SEED_OPTION  = 'oc_vendor_tags_seed_version';

	public function register() {
		// Priority 11: OC_CPT_Manager registers the vendor CPT at 10.
		add_action( 'init', [ __CLASS__, 'register_taxonomies' ], 11 );
		add_action( 'admin_init', [ __CLASS__, 'maybe_seed' ] );
	}

	public static function register_taxonomies() {
		register_taxonomy( self::TAX_CULTURE, OC_CPT, [
			'labels'            => [
				'name'          => __( 'Cultural Specialties', 'owambe-connect-core' ),
				'singular_name' => __( 'Cultural Specialty',   'owambe-connect-core' ),
				'add_new_item'  => __( 'Add New Cultural Specialty', 'owambe-connect-core' ),
				'edit_item'     => __( 'Edit Cultural Specialty',    'owambe-connect-core' ),
				'search_items'  => __( 'Search Cultural Specialties', 'owambe-connect-core' ),
				'not_found'     => __( 'No cultural specialties found.', 'owambe-connect-core' ),
				'menu_name'     => __( 'Cultural Specialties', 'owambe-connect-core' ),
			],
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => false,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'rewrite'           => false,
		] );

		register_taxonomy( self::TAX_TAG, OC_CPT, [
			'labels'            => [
				'name'          => __( 'Vendor Tags', 'owambe-connect-core' ),
				'singular_name' => __( 'Vendor Tag',  'owambe-connect-core' ),
				'add_new_item'  => __( 'Add New Vendor Tag', 'owambe-connect-core' ),
				'edit_item'     => __( 'Edit Vendor Tag',    'owambe-connect-core' ),
				'search_items'  => __( 'Search Vendor Tags', 'owambe-connect-core' ),
				'not_found'     => __( 'No vendor tags found.', 'owambe-connect-core' ),
				'parent_item'   => __( 'Parent Group', 'owambe-connect-core' ),
				'menu_name'     => __( 'Vendor Tags', 'owambe-connect-core' ),
			],
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => false,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'rewrite'           => false,
		] );
	}

	/**
	 * admin_init self-heal: seeds once per SEED_VERSION. Covers deploys where
	 * plugin files are replaced without the activation hook firing.
	 */
	public static function maybe_seed() {
		if ( (int) get_option( self::SEED_OPTION, 0 ) >= self::SEED_VERSION ) {
			return;
		}
		self::seed_default_terms();
	}

	/**
	 * Idempotent seeder — creates any missing default term, never duplicates,
	 * never modifies terms the admin has renamed or added.
	 *
	 * @return array{culture: int, groups: int, tags: int} Number of terms created.
	 */
	public static function seed_default_terms() {
		if ( ! taxonomy_exists( self::TAX_CULTURE ) || ! taxonomy_exists( self::TAX_TAG ) ) {
			self::register_taxonomies(); // Activation runs before `init`.
		}

		$created = [ 'culture' => 0, 'groups' => 0, 'tags' => 0 ];

		foreach ( oc_cultural_specialty_defaults() as $slug => $label ) {
			if ( term_exists( $slug, self::TAX_CULTURE ) ) {
				continue;
			}
			$result = wp_insert_term( $label, self::TAX_CULTURE, [ 'slug' => $slug ] );
			if ( ! is_wp_error( $result ) ) {
				$created['culture']++;
			}
		}

		foreach ( oc_vendor_tag_defaults() as $group => $tags ) {
			// Group names and tag names can collide case-insensitively
			// ("Decor & styling" group vs "Decor & Styling" tag), so groups are
			// looked up parent-scoped rather than with global term_exists().
			$parent_id = self::find_group_id( $group );
			if ( ! $parent_id ) {
				$result = wp_insert_term( $group, self::TAX_TAG );
				if ( is_wp_error( $result ) ) {
					continue;
				}
				$parent_id = (int) $result['term_id'];
				$created['groups']++;
			}

			foreach ( $tags as $tag ) {
				if ( term_exists( $tag, self::TAX_TAG, $parent_id ) ) {
					continue;
				}
				$result = wp_insert_term( $tag, self::TAX_TAG, [ 'parent' => $parent_id ] );
				if ( ! is_wp_error( $result ) ) {
					$created['tags']++;
				}
			}
		}

		update_option( self::SEED_OPTION, self::SEED_VERSION );

		return $created;
	}

	/**
	 * Find a top-level vendor_tag group by exact name, ignoring child terms.
	 *
	 * @param string $name Group display name.
	 * @return int Term ID, or 0 when the group does not exist yet.
	 */
	private static function find_group_id( $name ) {
		$terms = get_terms( [
			'taxonomy'   => self::TAX_TAG,
			'hide_empty' => false,
			'parent'     => 0,
			'name'       => $name,
			'fields'     => 'ids',
			'number'     => 1,
		] );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 0;
		}
		return (int) $terms[0];
	}
}
