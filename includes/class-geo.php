<?php
/**
 * Geolocation for vendors (P12 — radius search).
 *
 * Coordinates come from a STATIC lat/lng table covering the client-locked
 * gov.uk city list (helpers.php `oc_cities_by_country()`), so the common
 * case costs zero API calls. Resolution order per vendor:
 *
 *   1. First selected city in `_oc_location_areas` → city table
 *      (multi-area vendors use their FIRST area — documented behaviour).
 *   2. First selected region in `_oc_location_regions` → region centroid.
 *   3. Google Geocoding (only when `maps_api_key` is configured) for the
 *      `_oc_location` free-text summary — transient-cached 30 days.
 *   4. Unresolvable → coords meta is deleted so stale points never linger.
 *
 * Written on every vendor save via `oc_after_vendor_updated` /
 * `oc_after_vendor_registered`; existing vendors are covered by the
 * Vendors → Geo Backfill admin tool.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Geo {

	const META_LAT = '_oc_lat';
	const META_LNG = '_oc_lng';
	const META_LOC_NORM = '_oc_location_norm';
	const GEOCODE_TTL = 30 * DAY_IN_SECONDS;

	/** Bump to re-run the location-norm backfill once after deploy. */
	const NORM_BACKFILL_VERSION = 1;
	const NORM_BACKFILL_OPTION  = 'oc_location_norm_backfilled';

	public function register() {
		add_action( 'oc_after_vendor_updated',    [ __CLASS__, 'write_coords' ] );
		add_action( 'oc_after_vendor_registered', [ __CLASS__, 'write_coords' ] );

		// Meta-layer safety net: the admin Add/Edit Vendor page (and any other
		// path that never fires the actions above) still updates the location
		// meta — recompute coords whenever those keys change. Runs per changed
		// key with NO memo guard on purpose: the last run sees the fully-saved
		// state, so partial mid-save reads always get corrected.
		add_action( 'added_post_meta',   [ __CLASS__, 'on_location_meta_change' ], 10, 3 );
		add_action( 'updated_post_meta', [ __CLASS__, 'on_location_meta_change' ], 10, 3 );
		add_action( 'deleted_post_meta', [ __CLASS__, 'on_location_meta_change' ], 10, 3 );

		// H2 — one-time search-normalization backfill for existing vendors
		// (option-versioned; runs on the first wp-admin load after deploy).
		add_action( 'admin_init', [ __CLASS__, 'maybe_backfill_location_norm' ] );
	}

	/**
	 * @param int|int[] $meta_id   Unused.
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key that changed.
	 */
	public static function on_location_meta_change( $meta_id, $object_id, $meta_key ) {
		if ( ! in_array( $meta_key, [ '_oc_location_areas', '_oc_location_regions', '_oc_location' ], true ) ) {
			return;
		}
		if ( OC_CPT !== get_post_type( $object_id ) ) {
			return;
		}
		self::write_coords( (int) $object_id );
	}

	/**
	 * City-centre coordinates for the 75 gov.uk cities (client-locked list).
	 * Keys must match `oc_cities_by_country()` labels exactly.
	 *
	 * @return array<string, array{0: float, 1: float}>
	 */
	public static function city_coords() {
		return apply_filters( 'oc_city_coords', [
			// England
			'Bath'                => [ 51.3811, -2.3590 ],
			'Birmingham'          => [ 52.4862, -1.8904 ],
			'Bradford'            => [ 53.7960, -1.7594 ],
			'Brighton & Hove'     => [ 50.8225, -0.1372 ],
			'Bristol'             => [ 51.4545, -2.5879 ],
			'Cambridge'           => [ 52.2053,  0.1218 ],
			'Canterbury'          => [ 51.2802,  1.0789 ],
			'Carlisle'            => [ 54.8925, -2.9329 ],
			'Chelmsford'          => [ 51.7356,  0.4685 ],
			'Chester'             => [ 53.1934, -2.8931 ],
			'Chichester'          => [ 50.8365, -0.7792 ],
			'Colchester'          => [ 51.8959,  0.8919 ],
			'Coventry'            => [ 52.4068, -1.5197 ],
			'Derby'               => [ 52.9226, -1.4746 ],
			'Doncaster'           => [ 53.5228, -1.1285 ],
			'Durham'              => [ 54.7753, -1.5849 ],
			'Ely'                 => [ 52.3981,  0.2622 ],
			'Exeter'              => [ 50.7184, -3.5339 ],
			'Gloucester'          => [ 51.8642, -2.2382 ],
			'Hereford'            => [ 52.0565, -2.7160 ],
			'Kingston-upon-Hull'  => [ 53.7457, -0.3367 ],
			'Lancaster'           => [ 54.0466, -2.8007 ],
			'Leeds'               => [ 53.8008, -1.5491 ],
			'Leicester'           => [ 52.6369, -1.1398 ],
			'Lichfield'           => [ 52.6816, -1.8262 ],
			'Lincoln'             => [ 53.2307, -0.5406 ],
			'Liverpool'           => [ 53.4084, -2.9916 ],
			'London'              => [ 51.5074, -0.1278 ],
			'Manchester'          => [ 53.4808, -2.2426 ],
			'Milton Keynes'       => [ 52.0406, -0.7594 ],
			'Newcastle-upon-Tyne' => [ 54.9783, -1.6178 ],
			'Norwich'             => [ 52.6309,  1.2974 ],
			'Nottingham'          => [ 52.9548, -1.1581 ],
			'Oxford'              => [ 51.7520, -1.2577 ],
			'Peterborough'        => [ 52.5695, -0.2405 ],
			'Plymouth'            => [ 50.3755, -4.1427 ],
			'Portsmouth'          => [ 50.8198, -1.0880 ],
			'Preston'             => [ 53.7632, -2.7031 ],
			'Ripon'               => [ 54.1380, -1.5240 ],
			'Salford'             => [ 53.4875, -2.2901 ],
			'Salisbury'           => [ 51.0688, -1.7945 ],
			'Sheffield'           => [ 53.3811, -1.4701 ],
			'Southampton'         => [ 50.9097, -1.4044 ],
			'Southend-on-Sea'     => [ 51.5459,  0.7077 ],
			'St Albans'           => [ 51.7520, -0.3360 ],
			'Stoke on Trent'      => [ 53.0027, -2.1794 ],
			'Sunderland'          => [ 54.9069, -1.3838 ],
			'Truro'               => [ 50.2632, -5.0510 ],
			'Wakefield'           => [ 53.6833, -1.4977 ],
			'Wells'               => [ 51.2090, -2.6470 ],
			'Westminster'         => [ 51.4975, -0.1357 ],
			'Winchester'          => [ 51.0632, -1.3080 ],
			'Wolverhampton'       => [ 52.5870, -2.1288 ],
			'Worcester'           => [ 52.1936, -2.2216 ],
			'York'                => [ 53.9600, -1.0873 ],
			// Scotland
			'Aberdeen'            => [ 57.1497, -2.0943 ],
			'Dundee'              => [ 56.4620, -2.9707 ],
			'Dunfermline'         => [ 56.0719, -3.4523 ],
			'Edinburgh'           => [ 55.9533, -3.1883 ],
			'Glasgow'             => [ 55.8642, -4.2518 ],
			'Inverness'           => [ 57.4778, -4.2247 ],
			'Perth'               => [ 56.3950, -3.4308 ],
			'Stirling'            => [ 56.1165, -3.9369 ],
			// Wales
			'Bangor'              => [ 53.2280, -4.1280 ],
			'Cardiff'             => [ 51.4816, -3.1791 ],
			'Newport'             => [ 51.5842, -2.9977 ],
			'St Asaph'            => [ 53.2580, -3.4420 ],
			'St Davids'           => [ 51.8812, -5.2660 ],
			'Swansea'             => [ 51.6214, -3.9436 ],
			'Wrexham'             => [ 53.0430, -2.9925 ],
			// Northern Ireland
			'Armagh'              => [ 54.3499, -6.6546 ],
			'Bangor (NI)'         => [ 54.6534, -5.6683 ],
			'Belfast'             => [ 54.5973, -5.9301 ],
			'Lisburn'             => [ 54.5162, -6.0580 ],
			'Londonderry'         => [ 54.9966, -7.3086 ],
			'Newry'               => [ 54.1751, -6.3402 ],
		] );
	}

	/**
	 * Centroids for the 9 England regions — fallback for vendors who picked
	 * a region but no city. Keys match `oc_region_options()` labels.
	 */
	public static function region_coords() {
		return apply_filters( 'oc_region_coords', [
			'North East England'       => [ 54.95, -1.65 ],
			'North West England'       => [ 53.62, -2.60 ],
			'Yorkshire and the Humber' => [ 53.75, -1.30 ],
			'East Midlands'            => [ 52.90, -1.00 ],
			'West Midlands'            => [ 52.48, -2.00 ],
			'East of England'          => [ 52.20,  0.40 ],
			'London'                   => [ 51.5074, -0.1278 ],
			'South East England'       => [ 51.30, -0.80 ],
			'South West England'       => [ 50.95, -3.20 ],
		] );
	}

	/**
	 * Resolve a vendor's coordinates. Returns [lat, lng] floats or null.
	 * $allow_geocode=false lets the backfill dry-run classify without
	 * spending API calls.
	 *
	 * @return array{0: float, 1: float}|null
	 */
	public static function resolve_coords( $vendor_id, $allow_geocode = true ) {
		$areas = array_values( array_filter( array_map( 'trim', (array) get_post_meta( $vendor_id, '_oc_location_areas', true ) ) ) );
		if ( $areas ) {
			$table = self::city_coords();
			if ( isset( $table[ $areas[0] ] ) ) {
				return $table[ $areas[0] ];
			}
		}

		$regions = array_values( array_filter( array_map( 'trim', (array) get_post_meta( $vendor_id, '_oc_location_regions', true ) ) ) );
		if ( $regions ) {
			$table = self::region_coords();
			if ( isset( $table[ $regions[0] ] ) ) {
				return $table[ $regions[0] ];
			}
		}

		if ( $allow_geocode ) {
			$summary = trim( (string) get_post_meta( $vendor_id, '_oc_location', true ) );
			$query   = $summary ? $summary : ( $areas ? $areas[0] : '' );
			if ( '' !== $query ) {
				$hit = self::geocode( $query );
				if ( $hit ) {
					return $hit;
				}
			}
		}

		return null;
	}

	/**
	 * Save-path writer (hooked). Deletes coords when unresolvable so a
	 * vendor who removes their location drops out of radius results.
	 * Also refreshes the search-normalization shadow meta (H2) — same
	 * triggers, same lifecycle.
	 */
	public static function write_coords( $vendor_id ) {
		$vendor_id = (int) $vendor_id;
		if ( ! $vendor_id || OC_CPT !== get_post_type( $vendor_id ) ) {
			return;
		}
		$coords = self::resolve_coords( $vendor_id );
		if ( $coords ) {
			update_post_meta( $vendor_id, self::META_LAT, sprintf( '%.6F', $coords[0] ) );
			update_post_meta( $vendor_id, self::META_LNG, sprintf( '%.6F', $coords[1] ) );
		} else {
			delete_post_meta( $vendor_id, self::META_LAT );
			delete_post_meta( $vendor_id, self::META_LNG );
		}
		self::write_location_norm( $vendor_id );
	}

	/**
	 * H2 — normalized copy of the `_oc_location` search summary, so
	 * punctuation/case differences can never break location matching.
	 */
	public static function write_location_norm( $vendor_id ) {
		$norm = oc_normalize_location( get_post_meta( $vendor_id, '_oc_location', true ) );
		if ( '' !== $norm ) {
			update_post_meta( $vendor_id, self::META_LOC_NORM, $norm );
		} else {
			delete_post_meta( $vendor_id, self::META_LOC_NORM );
		}
	}

	/**
	 * One-time (versioned) backfill of `_oc_location_norm` for the back
	 * catalogue. ~1 quick meta write per vendor; guarded so it runs once.
	 */
	public static function maybe_backfill_location_norm() {
		if ( (int) get_option( self::NORM_BACKFILL_OPTION, 0 ) >= self::NORM_BACKFILL_VERSION ) {
			return;
		}
		$ids = get_posts( [
			'post_type'     => OC_CPT,
			'post_status'   => array_values( array_diff( array_keys( get_post_stati() ), [ 'trash', 'auto-draft', 'inherit' ] ) ),
			'numberposts'   => -1,
			'fields'        => 'ids',
			'no_found_rows' => true,
		] );
		foreach ( $ids as $id ) {
			self::write_location_norm( $id );
		}
		update_option( self::NORM_BACKFILL_OPTION, self::NORM_BACKFILL_VERSION );
	}

	/**
	 * Google Geocoding fallback — UK-biased, transient-cached (30 days,
	 * misses cached too so a bad address never hammers the API). Returns
	 * [lat, lng] or null. No-op without a configured maps_api_key.
	 */
	public static function geocode( $query ) {
		$key = trim( (string) oc_get_setting( 'maps_api_key', '' ) );
		if ( '' === $key ) {
			return null;
		}
		// sanitize_key() would lowercase anyway — md5 is already lowercase.
		$cache_key = 'oc_geo_' . md5( mb_strtolower( $query ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached ? $cached : null; // [] = cached miss.
		}

		$url = add_query_arg( [
			'address'    => rawurlencode( $query . ', United Kingdom' ),
			'region'     => 'gb',
			'components' => 'country:GB',
			'key'        => $key,
		], 'https://maps.googleapis.com/maps/api/geocode/json' );

		$response = wp_remote_get( $url, [ 'timeout' => 6 ] );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null; // transient not set — transport errors may be transient.
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$loc  = $body['results'][0]['geometry']['location'] ?? null;

		if ( isset( $loc['lat'], $loc['lng'] ) ) {
			$coords = [ (float) $loc['lat'], (float) $loc['lng'] ];
			set_transient( $cache_key, $coords, self::GEOCODE_TTL );
			return $coords;
		}
		set_transient( $cache_key, [], self::GEOCODE_TTL ); // definitive miss.
		return null;
	}
}
