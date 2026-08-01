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

		// Dual-write: whenever legacy tag meta changes — dashboard save, admin
		// form, CSV import, anything — resync that vendor's taxonomy terms.
		// Hooking the meta layer covers every save path with zero per-form code.
		add_action( 'added_post_meta',   [ __CLASS__, 'on_meta_change' ], 10, 3 );
		add_action( 'updated_post_meta', [ __CLASS__, 'on_meta_change' ], 10, 3 );
		add_action( 'deleted_post_meta', [ __CLASS__, 'on_meta_change' ], 10, 3 );
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

	/* ── Legacy-value mapping (single source of truth — the migration tool
	      and the dual-write sync both resolve through these) ─────────────── */

	/**
	 * Legacy meta holds either a serialized array (live data) or a CSV
	 * string (early format). Return a clean flat list of strings.
	 */
	public static function parse_values( $raw ) {
		if ( is_array( $raw ) ) {
			$list = $raw;
		} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$list = explode( ',', $raw );
		} else {
			return [];
		}
		$out = [];
		foreach ( $list as $item ) {
			if ( is_scalar( $item ) ) {
				$item = trim( (string) $item );
				if ( '' !== $item ) {
					$out[] = $item;
				}
			}
		}
		return $out;
	}

	/**
	 * Map legacy cultural-specialty values (slugs, name fallback) to term IDs.
	 *
	 * @param string[] $values Raw meta values.
	 * @return array{ids: int[], unmapped: string[]}
	 */
	public static function map_culture_values( array $values ) {
		$lookup = self::lookups();
		$ids    = [];
		$un     = [];
		foreach ( $values as $v ) {
			$key = sanitize_title( $v );
			if ( isset( $lookup['culture_slug'][ $key ] ) ) {
				$ids[] = $lookup['culture_slug'][ $key ];
			} elseif ( isset( $lookup['culture_name'][ self::norm( $v ) ] ) ) {
				$ids[] = $lookup['culture_name'][ self::norm( $v ) ];
			} else {
				$un[] = $v;
			}
		}
		return [ 'ids' => array_values( array_unique( $ids ) ), 'unmapped' => $un ];
	}

	/**
	 * Map legacy vendor-tag labels to CHILD term IDs (never groups — the
	 * "Decor & Styling" tag must not resolve to the "Decor & styling" group).
	 *
	 * @param string[] $values Raw meta values.
	 * @return array{ids: int[], unmapped: string[]}
	 */
	public static function map_tag_values( array $values ) {
		$lookup = self::lookups();
		$ids    = [];
		$un     = [];
		foreach ( $values as $v ) {
			if ( isset( $lookup['tag_name'][ self::norm( $v ) ] ) ) {
				$ids[] = $lookup['tag_name'][ self::norm( $v ) ];
			} elseif ( isset( $lookup['tag_slug'][ sanitize_title( $v ) ] ) ) {
				$ids[] = $lookup['tag_slug'][ sanitize_title( $v ) ];
			} else {
				$un[] = $v;
			}
		}
		return [ 'ids' => array_values( array_unique( $ids ) ), 'unmapped' => $un ];
	}

	/** Case/space/entity-insensitive comparison key. */
	public static function norm( $value ) {
		return mb_strtolower( trim( wp_specialchars_decode( (string) $value, ENT_QUOTES ) ) );
	}

	/**
	 * Term lookup tables, built once per request.
	 */
	private static function lookups() {
		static $lookup = null;
		if ( null !== $lookup ) {
			return $lookup;
		}
		$lookup = [ 'culture_slug' => [], 'culture_name' => [], 'tag_name' => [], 'tag_slug' => [] ];

		$terms = get_terms( [ 'taxonomy' => self::TAX_CULTURE, 'hide_empty' => false ] );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$lookup['culture_slug'][ $t->slug ]               = (int) $t->term_id;
				$lookup['culture_name'][ self::norm( $t->name ) ] = (int) $t->term_id;
			}
		}

		$terms = get_terms( [ 'taxonomy' => self::TAX_TAG, 'hide_empty' => false ] );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( 0 === (int) $t->parent ) {
					continue; // groups are containers, never assignment targets.
				}
				$lookup['tag_name'][ self::norm( $t->name ) ] = (int) $t->term_id;
				$lookup['tag_slug'][ $t->slug ]               = (int) $t->term_id;
			}
		}

		return $lookup;
	}

	/* ── Dual-write sync (meta stays the write model during transition) ──── */

	/**
	 * Meta-layer listener: fires on add/update/delete of ANY post meta;
	 * resyncs terms when one of the two legacy tag keys changed on a vendor.
	 * Covers every save path — dashboard, admin form, CSV import — without
	 * per-form code.
	 *
	 * @param int|int[] $meta_id   Meta row ID(s) — unused.
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key that changed.
	 */
	public static function on_meta_change( $meta_id, $object_id, $meta_key ) {
		if ( '_oc_cultural_specialties' !== $meta_key && '_oc_vendor_tags' !== $meta_key ) {
			return;
		}
		if ( OC_CPT !== get_post_type( $object_id ) ) {
			return;
		}
		self::sync_vendor_terms( (int) $object_id, $meta_key );
	}

	/**
	 * Replace-mode sync: the vendor's meta selection is authoritative, so
	 * terms are SET (not appended) — unchecking a tag removes its term.
	 *
	 * @param int    $vendor_id Vendor post ID.
	 * @param string $only_key  Optional: limit sync to the taxonomy backing
	 *                          this meta key; default syncs both.
	 */
	public static function sync_vendor_terms( $vendor_id, $only_key = '' ) {
		if ( ! taxonomy_exists( self::TAX_CULTURE ) || ! taxonomy_exists( self::TAX_TAG ) ) {
			return;
		}
		if ( '' === $only_key || '_oc_cultural_specialties' === $only_key ) {
			$values = self::parse_values( get_post_meta( $vendor_id, '_oc_cultural_specialties', true ) );
			$map    = self::map_culture_values( $values );
			wp_set_object_terms( $vendor_id, $map['ids'], self::TAX_CULTURE, false );
		}
		if ( '' === $only_key || '_oc_vendor_tags' === $only_key ) {
			$values = self::parse_values( get_post_meta( $vendor_id, '_oc_vendor_tags', true ) );
			$map    = self::map_tag_values( $values );
			wp_set_object_terms( $vendor_id, $map['ids'], self::TAX_TAG, false );
		}
	}

	/* ── Read helpers (terms first, legacy meta fallback) ────────────────── */

	/**
	 * Vendor's cultural specialties as slugs — term-backed, falling back to
	 * legacy meta for vendors that have not been migrated/synced yet.
	 *
	 * @param int $vendor_id Vendor post ID.
	 * @return string[]
	 */
	public static function vendor_culture_slugs( $vendor_id ) {
		if ( taxonomy_exists( self::TAX_CULTURE ) ) {
			$slugs = wp_get_object_terms( $vendor_id, self::TAX_CULTURE, [ 'fields' => 'slugs' ] );
			if ( ! is_wp_error( $slugs ) && $slugs ) {
				return array_values( $slugs );
			}
		}
		return self::parse_values( get_post_meta( $vendor_id, '_oc_cultural_specialties', true ) );
	}

	/**
	 * Vendor's tags as display labels (decoded child-term names) — term-backed
	 * with legacy meta fallback.
	 *
	 * @param int $vendor_id Vendor post ID.
	 * @return string[]
	 */
	public static function vendor_tag_labels( $vendor_id ) {
		if ( taxonomy_exists( self::TAX_TAG ) ) {
			$names = wp_get_object_terms( $vendor_id, self::TAX_TAG, [ 'fields' => 'names' ] );
			if ( ! is_wp_error( $names ) && $names ) {
				return array_map( function ( $n ) {
					return wp_specialchars_decode( $n, ENT_QUOTES );
				}, array_values( $names ) );
			}
		}
		return self::parse_values( get_post_meta( $vendor_id, '_oc_vendor_tags', true ) );
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
