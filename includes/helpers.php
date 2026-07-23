<?php
/**
 * Shared helper functions and constants.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'OC_CPT' ) ) {
	define( 'OC_CPT', 'oc_vendor' );
	define( 'OC_TAX', 'oc_vendor_category' );
	define( 'OC_ROLE', 'oc_vendor' );
	define( 'OC_CAP_EDIT_OWN', 'oc_edit_own_vendor' );
	define( 'OC_STATUS_PENDING', 'oc_pending' );
	define( 'OC_STATUS_APPROVED', 'publish' );
	define( 'OC_STATUS_REJECTED', 'oc_rejected' );
	define( 'OC_CLIENT_ROLE', 'oc_client' );
}

/**
 * Default seed categories (final list to be confirmed by client).
 *
 * @return array<int, array{slug: string, name: string}>
 */
function oc_default_categories() {
	return [
		[ 'slug' => 'catering',     'name' => __( 'Catering',          'owambe-connect-core' ) ],
		[ 'slug' => 'photography',  'name' => __( 'Photography',       'owambe-connect-core' ) ],
		[ 'slug' => 'videography',  'name' => __( 'Videography',       'owambe-connect-core' ) ],
		[ 'slug' => 'decor',        'name' => __( 'Decor & Styling',   'owambe-connect-core' ) ],
		[ 'slug' => 'dj-music',     'name' => __( 'DJ & Live Music',   'owambe-connect-core' ) ],
		[ 'slug' => 'venues',       'name' => __( 'Venues',            'owambe-connect-core' ) ],
		[ 'slug' => 'mua',          'name' => __( 'Makeup & Hair',     'owambe-connect-core' ) ],
		[ 'slug' => 'cakes',        'name' => __( 'Cakes & Desserts',  'owambe-connect-core' ) ],
		[ 'slug' => 'planners',     'name' => __( 'Event Planners',    'owambe-connect-core' ) ],
		[ 'slug' => 'attire',       'name' => __( 'Attire & Aso Ebi',  'owambe-connect-core' ) ],
	];
}

/**
 * Vendor profile meta keys, registered as a single source of truth.
 *
 * @return array<string, array{label: string, type: string, sanitize: string}>
 */
function oc_vendor_fields() {
	return [
		'_oc_business_name'        => [ 'label' => __( 'Business Name',     'owambe-connect-core' ), 'type' => 'text',     'sanitize' => 'sanitize_text_field' ],
		'_oc_location'             => [ 'label' => __( 'Service Area / Location', 'owambe-connect-core' ), 'type' => 'text', 'sanitize' => 'sanitize_text_field' ],
		'_oc_location_country'     => [ 'label' => __( 'Country / Region',  'owambe-connect-core' ), 'type' => 'select',   'sanitize' => 'sanitize_text_field' ],
		'_oc_location_areas'       => [ 'label' => __( 'Cities / Areas covered', 'owambe-connect-core' ), 'type' => 'multi', 'sanitize' => 'oc_sanitize_csv' ],
		'_oc_location_regions'     => [ 'label' => __( 'Regions covered',  'owambe-connect-core' ), 'type' => 'multi', 'sanitize' => 'oc_sanitize_csv' ],
		'_oc_cultural_specialties' => [ 'label' => __( 'Cultural specialties', 'owambe-connect-core' ), 'type' => 'multi',  'sanitize' => 'oc_sanitize_csv' ],
		'_oc_nigerian_specialty'   => [ 'label' => __( 'Specialises in Nigerian events', 'owambe-connect-core' ), 'type' => 'select', 'sanitize' => 'sanitize_text_field' ],
		'_oc_registered_business'  => [ 'label' => __( 'Registered business', 'owambe-connect-core' ), 'type' => 'select', 'sanitize' => 'sanitize_text_field' ],
		'_oc_vendor_tags'          => [ 'label' => __( 'Vendor tags',       'owambe-connect-core' ), 'type' => 'multi',    'sanitize' => 'oc_sanitize_csv' ],
		'_oc_bio'                  => [ 'label' => __( 'About / Bio',       'owambe-connect-core' ), 'type' => 'textarea', 'sanitize' => 'wp_kses_post' ],
		'_oc_services'             => [ 'label' => __( 'Services Offered',  'owambe-connect-core' ), 'type' => 'textarea', 'sanitize' => 'wp_kses_post' ],
		'_oc_price_range'          => [ 'label' => __( 'Price Range',       'owambe-connect-core' ), 'type' => 'select',   'sanitize' => 'sanitize_text_field' ],
		'_oc_whatsapp'             => [ 'label' => __( 'WhatsApp Number',   'owambe-connect-core' ), 'type' => 'tel',      'sanitize' => 'oc_sanitize_phone' ],
		'_oc_public_email'         => [ 'label' => __( 'Public Contact Email', 'owambe-connect-core' ), 'type' => 'email', 'sanitize' => 'sanitize_email' ],
		'_oc_instagram'            => [ 'label' => __( 'Instagram Handle',  'owambe-connect-core' ), 'type' => 'text',     'sanitize' => 'oc_sanitize_handle' ],
		'_oc_facebook'             => [ 'label' => __( 'Facebook Handle / URL', 'owambe-connect-core' ), 'type' => 'text', 'sanitize' => 'sanitize_text_field' ],
		'_oc_website'              => [ 'label' => __( 'Website',           'owambe-connect-core' ), 'type' => 'url',      'sanitize' => 'esc_url_raw' ],
		'_oc_languages'            => [ 'label' => __( 'Languages Spoken',  'owambe-connect-core' ), 'type' => 'multi',    'sanitize' => 'oc_sanitize_csv' ],
		'_oc_logo_id'              => [ 'label' => __( 'Logo',              'owambe-connect-core' ), 'type' => 'image',    'sanitize' => 'absint' ],
		'_oc_banner_id'            => [ 'label' => __( 'Banner',            'owambe-connect-core' ), 'type' => 'image',    'sanitize' => 'absint' ],
		'_oc_gallery_display_id'   => [ 'label' => __( 'Display photo (gallery)', 'owambe-connect-core' ), 'type' => 'image', 'sanitize' => 'absint' ],
		'_oc_vendor_number'        => [ 'label' => __( 'Vendor Registration Number', 'owambe-connect-core' ), 'type' => 'text', 'sanitize' => 'sanitize_text_field' ],
		'_oc_verified'             => [ 'label' => __( 'Verified Vendor',   'owambe-connect-core' ), 'type' => 'bool',     'sanitize' => 'oc_sanitize_bool' ],
		'_oc_founding_vendor'      => [ 'label' => __( 'Founding Vendor',   'owambe-connect-core' ), 'type' => 'bool',     'sanitize' => 'oc_sanitize_bool' ],
		'_oc_featured'             => [ 'label' => __( 'Featured',          'owambe-connect-core' ), 'type' => 'bool',     'sanitize' => 'oc_sanitize_bool' ],
		'_oc_rejection_note'       => [ 'label' => __( 'Rejection Reason',  'owambe-connect-core' ), 'type' => 'textarea', 'sanitize' => 'sanitize_textarea_field' ],
	];
}

function oc_price_range_options() {
	return [
		'budget'    => __( '£ — Budget',     'owambe-connect-core' ),
		'mid'       => __( '££ — Mid-range', 'owambe-connect-core' ),
		'premium'   => __( '£££ — Premium',  'owambe-connect-core' ),
		'luxury'    => __( '££££ — Luxury',  'owambe-connect-core' ),
	];
}

function oc_language_options() {
	$csv = oc_get_setting( 'languages', 'English, Yoruba, Igbo, Hausa, Pidgin, Urdu, Hindi, Punjabi, Bengali, Mandarin, Cantonese, Arabic, French, Spanish' );
	$list = array_map( 'trim', explode( ',', (string) $csv ) );
	return array_values( array_filter( $list ) );
}

/**
 * UK constituent country options (vendor onboarding).
 */
function oc_country_options() {
	return [
		'england'          => __( 'England',          'owambe-connect-core' ),
		'scotland'         => __( 'Scotland',         'owambe-connect-core' ),
		'wales'            => __( 'Wales',            'owambe-connect-core' ),
		'northern-ireland' => __( 'Northern Ireland', 'owambe-connect-core' ),
	];
}

/**
 * UK city/area options, grouped by constituent country. This is the
 * single source of truth — the flat `oc_city_options()` list and the
 * `oc_country_for_city()` lookup both derive from this map. Filterable
 * via the `oc_cities_by_country` filter so themes / site-specific
 * plugins can extend without forking core.
 */
function oc_cities_by_country() {
	// Canonical UK city list — the 76 places with official city status as
	// published on gov.uk. This is the client-locked reference: every
	// consumer (vendor dashboard checkboxes, hero search typeahead,
	// Request-a-Vendor form, admin Add Vendor form) reads from here.
	// Updates must come from gov.uk's list, not arbitrary growth.
	$cities = [
		'england' => [
			'Bath', 'Birmingham', 'Bradford', 'Brighton & Hove', 'Bristol',
			'Cambridge', 'Canterbury', 'Carlisle', 'Chelmsford', 'Chester',
			'Chichester', 'Colchester', 'Coventry', 'Derby', 'Doncaster',
			'Durham', 'Ely', 'Exeter', 'Gloucester', 'Hereford',
			'Kingston-upon-Hull', 'Lancaster', 'Leeds', 'Leicester',
			'Lichfield', 'Lincoln', 'Liverpool', 'London', 'Manchester',
			'Milton Keynes', 'Newcastle-upon-Tyne', 'Norwich', 'Nottingham',
			'Oxford', 'Peterborough', 'Plymouth', 'Portsmouth', 'Preston',
			'Ripon', 'Salford', 'Salisbury', 'Sheffield', 'Southampton',
			'Southend-on-Sea', 'St Albans', 'Stoke on Trent', 'Sunderland',
			'Truro', 'Wakefield', 'Wells', 'Westminster', 'Winchester',
			'Wolverhampton', 'Worcester', 'York',
		],
		'scotland' => [
			'Aberdeen', 'Dundee', 'Dunfermline', 'Edinburgh', 'Glasgow',
			'Inverness', 'Perth', 'Stirling',
		],
		'wales' => [
			'Bangor', 'Cardiff', 'Newport', 'St Asaph', 'St Davids',
			'Swansea', 'Wrexham',
		],
		'northern-ireland' => [
			'Armagh', 'Bangor (NI)', 'Belfast', 'Lisburn', 'Londonderry',
			'Newry',
		],
	];
	// Alphabetise each country block after merge so the dashboard wall of
	// checkboxes scans cleanly. The flat oc_city_options() list still
	// preserves the popular-first ordering by re-merging in raw order.
	foreach ( $cities as $k => $list ) {
		$alpha = $list;
		sort( $alpha, SORT_NATURAL | SORT_FLAG_CASE );
		$cities[ $k ] = $alpha;
	}
	return apply_filters( 'oc_cities_by_country', $cities );
}

/**
 * Flat list of city options. When $country is one of the UK constituent
 * slugs we return only that country's cities; otherwise we return the
 * full UK list. Hero search + bulk import use the unfiltered call;
 * the vendor / admin forms use it scoped to whichever country the
 * vendor has selected.
 */
function oc_city_options( $country = null ) {
	$by_country = oc_cities_by_country();
	if ( $country && isset( $by_country[ $country ] ) ) {
		return array_values( $by_country[ $country ] );
	}
	$all = [];
	foreach ( $by_country as $cities ) {
		$all = array_merge( $all, $cities );
	}
	return array_values( apply_filters( 'oc_city_options', $all ) );
}

/**
 * Reverse lookup — which country does a city belong to? Used by the
 * vendor-dashboard form to tag each chip with data-country so the
 * JS country filter can show / hide them.
 */
function oc_country_for_city( $city ) {
	foreach ( oc_cities_by_country() as $country => $cities ) {
		if ( in_array( $city, $cities, true ) ) {
			return $country;
		}
	}
	return '';
}

/**
 * The 9 official England regions (gov.uk ITL1 / former GOR list). Added so
 * vendors whose town isn't on the city list can still place themselves — a
 * region covers every town within it. England-only: the other UK constituent
 * countries use the city list. Stored in `_oc_location_regions` and folded
 * into the searchable `_oc_location` summary on save. Filterable so the list
 * can be extended without forking core.
 *
 * Returns a flat list of canonical region labels — the SAME strings are used
 * for the form chips, the saved value, the directory dropdown, and the hero
 * search suggestions, so a LIKE match against `_oc_location` always lines up.
 */
function oc_region_options() {
	return array_keys( oc_cities_by_region() );
}

/**
 * England's cities grouped under their official region (gov.uk ITL1 / former
 * Government Office Region). This is the single source of truth for the
 * Country → Region → City hierarchy: oc_region_options() derives its 9 region
 * labels from the keys here, and oc_region_for_city() reverse-looks-up a city's
 * region so the form can tag each chip with data-region and narrow the city
 * list to the selected region(s).
 *
 * Only England has regions — Scotland / Wales / Northern Ireland keep their
 * cities directly under the country (oc_cities_by_country()). Every English
 * city in oc_cities_by_country()['england'] must appear here exactly once.
 * Filterable so the map can be extended without forking core.
 */
function oc_cities_by_region() {
	return apply_filters( 'oc_cities_by_region', [
		'North East England'       => [ 'Durham', 'Newcastle-upon-Tyne', 'Sunderland' ],
		'North West England'       => [ 'Carlisle', 'Chester', 'Lancaster', 'Liverpool', 'Manchester', 'Preston', 'Salford' ],
		'Yorkshire and the Humber' => [ 'Bradford', 'Doncaster', 'Kingston-upon-Hull', 'Leeds', 'Ripon', 'Sheffield', 'Wakefield', 'York' ],
		'East Midlands'            => [ 'Derby', 'Leicester', 'Lincoln', 'Nottingham' ],
		'West Midlands'            => [ 'Birmingham', 'Coventry', 'Hereford', 'Lichfield', 'Stoke on Trent', 'Wolverhampton', 'Worcester' ],
		'East of England'          => [ 'Cambridge', 'Chelmsford', 'Colchester', 'Ely', 'Norwich', 'Peterborough', 'Southend-on-Sea', 'St Albans' ],
		'London'                   => [ 'London', 'Westminster' ],
		'South East England'       => [ 'Brighton & Hove', 'Canterbury', 'Chichester', 'Milton Keynes', 'Oxford', 'Portsmouth', 'Southampton', 'Winchester' ],
		'South West England'       => [ 'Bath', 'Bristol', 'Exeter', 'Gloucester', 'Plymouth', 'Salisbury', 'Truro', 'Wells' ],
	] );
}

/**
 * Reverse lookup — which England region does a city belong to? Returns '' for
 * cities outside England (Scotland / Wales / NI) or anything unmapped, which the
 * form treats as "no region tag" (chip shows under its country, not a region).
 */
function oc_region_for_city( $city ) {
	foreach ( oc_cities_by_region() as $region => $cities ) {
		if ( in_array( $city, $cities, true ) ) {
			return $region;
		}
	}
	return '';
}

/**
 * Cultural event specialty options. Single source of truth for the
 * vendor dashboard checkbox group + the public profile pills.
 */
function oc_cultural_specialty_options() {
	return [
		'african'        => __( 'African Events',        'owambe-connect-core' ),
		'caribbean'      => __( 'Caribbean Events',      'owambe-connect-core' ),
		'south-asian'    => __( 'South Asian Events',    'owambe-connect-core' ),
		'multicultural'  => __( 'Multicultural Events',  'owambe-connect-core' ),
		'luxury'         => __( 'Luxury Events',         'owambe-connect-core' ),
		'contemporary'   => __( 'Contemporary Events',   'owambe-connect-core' ),
	];
}

/**
 * Vendor tag options — grouped by Event Types and Services.
 * Returns associative array of [group => [tag, tag, …]] for rendering, and
 * the flat list is also exposed via oc_vendor_tag_options_flat().
 */
function oc_vendor_tag_options() {
	// 14 narrow groups — each 3-7 items — so the accordion in the dashboard
	// stays scannable. Order is "event types first, then services" (planning
	// → decor → food → photo → music → MCs → beauty → fashion → print →
	// rentals → logistics). Existing per-vendor selections are unaffected
	// by re-grouping because tags are saved as flat strings.
	return [
		// ── Event types ─────────────────────────────────────────
		__( 'Cultural events',          'owambe-connect-core' ) => [
			'African Events', 'Caribbean Events', 'South Asian Events',
			'Multicultural Events', 'Luxury Events', 'Contemporary Events',
		],
		__( 'Weddings & celebrations',  'owambe-connect-core' ) => [
			'Traditional Weddings', 'White Weddings', 'Engagement Ceremonies',
			'Bridal Showers', 'Birthday Celebrations', 'Baby Showers',
		],
		__( 'Other events',             'owambe-connect-core' ) => [
			'Corporate Events', 'Private Parties', 'Intimate Events',
			'Large-Scale Celebrations', 'Destination Events',
			'Outdoor Events', 'Indoor Events',
		],
		// ── Services ────────────────────────────────────────────
		__( 'Planning & coordination',  'owambe-connect-core' ) => [
			'Event Planner', 'Wedding Planner', 'Day Coordinator',
		],
		__( 'Decor & styling',          'owambe-connect-core' ) => [
			'Decor & Styling', 'Event Designer', 'Floral Design', 'Balloon Styling',
		],
		__( 'Food & drinks',            'owambe-connect-core' ) => [
			'Catering', 'Small Chops', 'Dessert Table', 'Cakes & Desserts',
			'Drinks Vendor', 'Cocktail Bar', 'Mobile Bar',
		],
		__( 'Photo & video',            'owambe-connect-core' ) => [
			'Photography', 'Videography', 'Content Creator', 'Photo Booth', '360 Booth',
		],
		__( 'Music & entertainment',    'owambe-connect-core' ) => [
			'DJ Services', 'Live Band', 'Saxophonist', 'Violinist', 'Drummer',
		],
		__( 'MCs & hosts',              'owambe-connect-core' ) => [
			'MC & Hosting', 'Traditional MC', 'Wedding Host',
		],
		__( 'Beauty & styling',         'owambe-connect-core' ) => [
			'Makeup Artist', 'Bridal Makeup', 'Hair Styling',
			'Gele Artist', 'Asoebi Styling',
		],
		__( 'Fashion & attire',         'owambe-connect-core' ) => [
			'Fashion Designer', 'Tailoring', 'Bridal Wear', 'Groom Styling',
			'Jewellery', 'Accessories',
		],
		__( 'Print & stationery',       'owambe-connect-core' ) => [
			'Printing Services', 'Invitation Cards', 'Signage & Welcome Boards',
		],
		__( 'Rentals & setup',          'owambe-connect-core' ) => [
			'Event Rentals', 'Furniture Hire', 'Lighting & Effects',
			'Stage Setup', 'Venue Provider',
		],
		__( 'Logistics & support',      'owambe-connect-core' ) => [
			'Security Services', 'Travel Services', 'Accommodation Services',
		],
	];
}

function oc_vendor_tag_options_flat() {
	$out = [];
	foreach ( oc_vendor_tag_options() as $group => $tags ) {
		foreach ( $tags as $t ) {
			$out[] = $t;
		}
	}
	return $out;
}

/**
 * Normalise a UK mobile/landline number into the canonical
 * `+44XXXXXXXXXX` form so WhatsApp deep-links work and we never end up
 * with a `+44(0)…` mistake that drops the trailing digit.
 *
 * Accepts: raw 10 digits, `0XXXXXXXXXX`, `+44…`, `0044…`, spaces, dashes.
 */
function oc_normalize_uk_whatsapp( $value ) {
	$digits = preg_replace( '/\D/', '', (string) $value );
	if ( '' === $digits ) {
		return '';
	}
	if ( strpos( $digits, '0044' ) === 0 ) {
		$digits = substr( $digits, 4 );
	} elseif ( strpos( $digits, '44' ) === 0 && strlen( $digits ) > 10 ) {
		$digits = substr( $digits, 2 );
	}
	$digits = ltrim( $digits, '0' );
	if ( '' === $digits ) {
		return '';
	}
	return '+44' . $digits;
}

/**
 * Return just the local 10-digit part of a UK WhatsApp number — used to
 * pre-fill the dashboard input where the `+44` prefix is rendered as a
 * fixed adornment.
 */
function oc_uk_whatsapp_local( $value ) {
	$normalised = oc_normalize_uk_whatsapp( $value );
	if ( '' === $normalised ) {
		return '';
	}
	return preg_replace( '/^\+44/', '', $normalised );
}

/**
 * Read a Settings value with fallback. Wraps OC_Settings::get for callers
 * that don't want to depend on the class name directly.
 */
function oc_get_setting( $key, $fallback = null ) {
	if ( class_exists( 'OC_Settings' ) ) {
		return OC_Settings::get( $key, $fallback );
	}
	return $fallback;
}

function oc_sanitize_phone( $value ) {
	return preg_replace( '/[^0-9+\s\-()]/', '', (string) $value );
}

function oc_sanitize_handle( $value ) {
	$value = sanitize_text_field( (string) $value );
	return ltrim( $value, '@' );
}

function oc_sanitize_csv( $value ) {
	if ( is_string( $value ) ) {
		$value = array_map( 'trim', explode( ',', $value ) );
	}
	if ( ! is_array( $value ) ) {
		return [];
	}
	return array_values( array_filter( array_map( 'sanitize_text_field', $value ) ) );
}

function oc_sanitize_bool( $value ) {
	return ! empty( $value ) ? 1 : 0;
}

/**
 * Centralized image upload handler with MIME + size validation.
 * Restricts uploads to JPG / PNG / WebP regardless of WP's global settings.
 *
 * @param string $field      $_FILES key (e.g. 'logo', 'banner').
 * @param int    $post_id    Attach the upload to this post.
 * @param int    $max_bytes  Reject anything larger.
 * @return int|WP_Error|false  Attachment ID on success, WP_Error on validation
 *                             failure, false if no file present.
 */
function oc_handle_image_upload( $field, $post_id, $max_bytes ) {
	if ( empty( $_FILES[ $field ]['name'] ) || empty( $_FILES[ $field ]['tmp_name'] ) ) {
		return false;
	}
	$file = $_FILES[ $field ];

	if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== $file['error'] ) {
		return new WP_Error( 'oc_upload_error', __( 'Upload failed. Please try again.', 'owambe-connect-core' ) );
	}
	if ( (int) $file['size'] > (int) $max_bytes ) {
		return new WP_Error( 'oc_file_too_large', __( 'Image is too large.', 'owambe-connect-core' ) );
	}

	// Verify the *actual* file contents, not the browser-supplied $file['type'].
	$allowed = [ 'image/jpeg', 'image/png', 'image/webp' ];
	$detected = function_exists( 'mime_content_type' ) ? mime_content_type( $file['tmp_name'] ) : '';
	if ( ! $detected && function_exists( 'finfo_open' ) ) {
		$f        = finfo_open( FILEINFO_MIME_TYPE );
		$detected = $f ? finfo_file( $f, $file['tmp_name'] ) : '';
		if ( $f ) finfo_close( $f );
	}
	if ( ! in_array( $detected, $allowed, true ) ) {
		return new WP_Error( 'oc_file_type', __( 'Only JPG, PNG, or WebP images are allowed.', 'owambe-connect-core' ) );
	}

	// Belt-and-braces: confirm the file actually decodes as an image and reject
	// decompression-bomb files (huge pixel dimensions that fit under the byte
	// limit but expand massively in memory when WordPress generates thumbnails).
	$dims = @getimagesize( $file['tmp_name'] );
	if ( ! is_array( $dims ) || empty( $dims[0] ) || empty( $dims[1] ) ) {
		return new WP_Error( 'oc_file_corrupt', __( 'Image file is corrupt or not a real image.', 'owambe-connect-core' ) );
	}
	if ( ( (int) $dims[0] * (int) $dims[1] ) > 50000000 ) { // ~50 megapixels
		return new WP_Error( 'oc_file_too_big', __( 'Image dimensions are too large (max ~7000×7000).', 'owambe-connect-core' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	add_filter( 'upload_mimes', 'oc_image_mimes_only' );
	$id = media_handle_upload( $field, $post_id );
	remove_filter( 'upload_mimes', 'oc_image_mimes_only' );

	return $id;
}

function oc_image_mimes_only() {
	return [
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
	];
}

/**
 * Process a multi-file gallery upload (`<input type="file" name="gallery[]" multiple>`).
 * Each file is validated through oc_handle_image_upload(). Returns array of
 * attachment IDs. Existing IDs in $existing_ids are preserved.
 *
 * @param string $field
 * @param int    $post_id
 * @param int    $max_count        Max images allowed (incoming + existing combined).
 * @param int    $max_bytes_each
 * @param int[]  $existing_ids     Already-saved IDs (kept as-is).
 * @return int[]
 */
function oc_handle_gallery_upload( $field, $post_id, $max_count, $max_bytes_each, $existing_ids = [] ) {
	$existing_ids = array_values( array_filter( array_map( 'intval', (array) $existing_ids ) ) );
	if ( empty( $_FILES[ $field ]['name'] ) ) return $existing_ids;
	$names = (array) $_FILES[ $field ]['name'];
	if ( ! count( array_filter( $names ) ) ) return $existing_ids;

	$slot_left = max( 0, (int) $max_count - count( $existing_ids ) );
	if ( $slot_left <= 0 ) return $existing_ids;

	$ids = $existing_ids;
	$src = $_FILES[ $field ];
	$count = count( $names );
	for ( $i = 0; $i < $count && $slot_left > 0; $i++ ) {
		if ( empty( $src['name'][ $i ] ) ) continue;

		$_FILES['_oc_gallery_one'] = [
			'name'     => $src['name'][ $i ],
			'type'     => isset( $src['type'][ $i ] )     ? $src['type'][ $i ]     : '',
			'tmp_name' => isset( $src['tmp_name'][ $i ] ) ? $src['tmp_name'][ $i ] : '',
			'error'    => isset( $src['error'][ $i ] )    ? (int) $src['error'][ $i ] : UPLOAD_ERR_NO_FILE,
			'size'     => isset( $src['size'][ $i ] )     ? (int) $src['size'][ $i ]  : 0,
		];
		$id = oc_handle_image_upload( '_oc_gallery_one', $post_id, $max_bytes_each );
		unset( $_FILES['_oc_gallery_one'] );

		if ( $id && ! is_wp_error( $id ) ) {
			$ids[] = (int) $id;
			$slot_left--;
		}
	}
	return array_values( array_unique( $ids ) );
}

/**
 * Delete one image from a vendor gallery (used by dashboard "remove" buttons).
 * Only removes the meta entry — does NOT delete the attachment file from
 * the media library, so admins can keep / reuse it.
 */
function oc_remove_gallery_image( $post_id, $attachment_id ) {
	$ids = (array) get_post_meta( $post_id, '_oc_gallery_ids', true );
	$ids = array_values( array_filter( array_map( 'intval', $ids ), function ( $i ) use ( $attachment_id ) {
		return $i !== (int) $attachment_id;
	} ) );
	update_post_meta( $post_id, '_oc_gallery_ids', $ids );
}

/**
 * Server-side reCAPTCHA v3 verification. Returns true when:
 *   - reCAPTCHA isn't configured (graceful fallback so the site never breaks)
 *   - or the token validates with score >= configured threshold
 *
 * @param string $token  The g-recaptcha-response token from the form.
 * @return bool
 */
function oc_verify_recaptcha( $token ) {
	$secret = (string) oc_get_setting( 'recaptcha_secret_key', '' );
	if ( '' === $secret ) return true; // Not configured → don't block.
	if ( ! is_string( $token ) || '' === $token ) return false;

	$res = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
		'timeout' => 8,
		'body'    => [
			'secret'   => $secret,
			'response' => $token,
			'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '',
		],
	] );
	if ( is_wp_error( $res ) ) return true; // Google unreachable → don't punish user.

	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $body ) || empty( $body['success'] ) ) return false;

	$threshold = (float) oc_get_setting( 'recaptcha_threshold', '0.5' );
	$score     = isset( $body['score'] ) ? (float) $body['score'] : 1.0;
	return $score >= $threshold;
}

/** True if both reCAPTCHA keys are filled in. */
function oc_recaptcha_enabled() {
	return (string) oc_get_setting( 'recaptcha_site_key', '' ) !== ''
	    && (string) oc_get_setting( 'recaptcha_secret_key', '' ) !== '';
}

/**
 * Print the reCAPTCHA v3 script + a hidden token input. Templates call this
 * inside the form. No-op when keys aren't set.
 *
 * @param string $action  reCAPTCHA action label, used for filtering by Google.
 */
function oc_recaptcha_field( $action = 'submit' ) {
	if ( ! oc_recaptcha_enabled() ) return;
	$site_key = esc_attr( (string) oc_get_setting( 'recaptcha_site_key', '' ) );
	$action   = esc_js( preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $action ) ?: 'submit' );
	?>
	<input type="hidden" name="oc_recaptcha_token" class="oc-recaptcha-token" value=""/>
	<script>
	(function () {
		if (window.__ocRecaptchaLoaded) { ocRcInject(); return; }
		window.__ocRecaptchaLoaded = true;
		var s = document.createElement('script');
		s.src = 'https://www.google.com/recaptcha/api.js?render=<?php echo $site_key; ?>';
		s.async = true; s.defer = true;
		s.onload = ocRcInject;
		document.head.appendChild(s);
		function ocRcInject() {
			if (!window.grecaptcha || !grecaptcha.ready) return;
			grecaptcha.ready(function () {
				document.querySelectorAll('form .oc-recaptcha-token').forEach(function (input) {
					var form = input.form;
					if (!form || form.__ocRcWired) return;
					form.__ocRcWired = true;
					form.addEventListener('submit', function (e) {
						if (input.value) return; // already set this round
						e.preventDefault();
						grecaptcha.execute('<?php echo $site_key; ?>', { action: '<?php echo $action; ?>' }).then(function (token) {
							input.value = token;
							form.submit();
						});
					});
				});
			});
		}
	})();
	</script>
	<?php
}

/**
 * Locate a template, allowing the active theme to override
 * via /owambe-connect/<template>.php.
 */
function oc_get_template( $name, array $args = [] ) {
	$theme_path = locate_template( 'owambe-connect/' . $name );
	$path       = $theme_path ?: OC_TEMPLATE_DIR . $name;

	if ( ! file_exists( $path ) ) {
		return '';
	}

	if ( $args ) {
		extract( $args, EXTR_SKIP );
	}

	ob_start();
	include $path;
	return ob_get_clean();
}

function oc_page_url( $slug ) {
	$page = get_page_by_path( $slug );
	return $page ? get_permalink( $page->ID ) : home_url( '/' . ltrim( $slug, '/' ) );
}

/**
 * Client-facing Terms & Conditions URL for the signup/login consent links.
 * Prefers the `client_terms_url` plugin option (oc_settings); falls back to the
 * built-in /terms/ page when it's not configured.
 *
 * @return string
 */
function oc_client_terms_url() {
	$url = (string) oc_get_setting( 'client_terms_url', '' );
	if ( '' !== $url ) {
		return $url;
	}
	return oc_page_url( 'terms' );
}

/**
 * Build a wa.me deep link. Optional $text pre-fills the message so vendors
 * can see the enquiry came from Owambe Connect (Phase 2).
 */
function oc_whatsapp_link( $number, $text = '' ) {
	$digits = preg_replace( '/\D/', '', (string) $number );
	if ( ! $digits ) {
		return '';
	}
	$url = 'https://wa.me/' . $digits;
	if ( '' !== trim( (string) $text ) ) {
		$url .= '?text=' . rawurlencode( $text );
	}
	return $url;
}

/**
 * The standard pre-filled WhatsApp enquiry message for a vendor profile.
 * Template lives in settings so the client can tweak the wording;
 * {business} is replaced with the vendor's business name.
 */
function oc_whatsapp_prefill( $post_id ) {
	$template = (string) oc_get_setting(
		'whatsapp_prefill_template',
		/* translators: %s: vendor business name */
		__( 'Hi {business}, I found your profile on Owambe Connect and would like to enquire about your services for an upcoming event.', 'owambe-connect-core' )
	);
	$business = get_post_meta( $post_id, '_oc_business_name', true );
	if ( ! $business ) {
		$business = get_the_title( $post_id );
	}
	return str_replace( '{business}', (string) $business, $template );
}

/**
 * Trustworthy client IP for rate-limiting and dedupe keys.
 *
 * SECURITY: HTTP_CF_CONNECTING_IP / HTTP_X_FORWARDED_FOR are client-supplied
 * and forgeable when the request hits the origin directly. Trusting them lets
 * an attacker pick any bucket key — spoofing throttles, dedupe, and inflating
 * counters at will. So we default to REMOTE_ADDR (the only value the origin
 * itself sets) and only honour forwarded headers when the site explicitly
 * declares it sits behind a trusted proxy via the `oc_trusted_proxy` filter
 * (e.g. add_filter('oc_trusted_proxy','__return_true') once Cloudflare fronts
 * the site).
 */
function oc_client_ip() {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '';

	if ( apply_filters( 'oc_trusted_proxy', false ) ) {
		foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR' ] as $h ) {
			if ( ! empty( $_SERVER[ $h ] ) ) {
				$ip = trim( explode( ',', (string) $_SERVER[ $h ] )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
	}

	return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';
}

/**
 * Whether the current (or given) user has saved a vendor to their list.
 */
function oc_is_vendor_saved( $vendor_id, $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id || ! class_exists( 'OC_Client' ) ) {
		return false;
	}
	return in_array( (int) $vendor_id, OC_Client::saved_vendors( $user_id ), true );
}

function oc_instagram_link( $handle ) {
	$handle = ltrim( (string) $handle, '@' );
	return $handle ? 'https://instagram.com/' . rawurlencode( $handle ) : '';
}

function oc_facebook_link( $handle ) {
	$handle = trim( (string) $handle );
	if ( ! $handle ) {
		return '';
	}
	if ( preg_match( '#^https?://#i', $handle ) ) {
		return esc_url( $handle );
	}
	return 'https://facebook.com/' . rawurlencode( ltrim( $handle, '@' ) );
}

/**
 * The vendor user owns the post via post_author.
 */
function oc_user_can_edit_vendor( $user_id, $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || OC_CPT !== $post->post_type ) {
		return false;
	}
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return false;
	}
	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}
	return (int) $post->post_author === $user_id;
}

function oc_get_current_vendor_post() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return null;
	}
	$posts = get_posts( [
		'author'      => $user_id,
		'post_type'   => OC_CPT,
		'post_status' => [ OC_STATUS_PENDING, OC_STATUS_APPROVED, OC_STATUS_REJECTED ],
		'numberposts' => 1,
		'orderby'     => 'ID',
		'order'       => 'ASC',
	] );
	$post = $posts ? $posts[0] : null;
	if ( $post ) {
		oc_debug_log( 'oc_get_current_vendor_post', [
			'user_id'    => $user_id,
			'post_id'    => $post->ID,
			'post_title' => $post->post_title,
			'status'     => $post->post_status,
			'all_count'  => count( get_posts( [ 'author' => $user_id, 'post_type' => OC_CPT, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) ),
		] );
	}
	return $post;
}

/**
 * Returns true when admin-level debugging is active for the current request.
 * Cheap helper used to gate debug log writes + the dashboard inline panel.
 * Vendor users cannot trigger this; only `manage_options` capability holders.
 */
function oc_debug_enabled() {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}
	$has_param = ( isset( $_GET['oc_debug'] )  && '1' === $_GET['oc_debug'] )
		|| ( isset( $_POST['oc_debug'] ) && '1' === $_POST['oc_debug'] );
	$is_admin  = function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	$cached    = ( defined( 'WP_DEBUG' ) && WP_DEBUG && $is_admin ) || ( $has_param && $is_admin );
	return $cached;
}

/**
 * Structured debug logger. Writes to wp-content/uploads/oc-debug.log only when
 * oc_debug_enabled() is true (admin + WP_DEBUG or ?oc_debug=1). No-op for everyone else.
 *
 * $force bypasses the admin+debug gate for server-to-server contexts that have
 * no logged-in user (e.g. Stripe webhooks) but still want a log line when
 * WP_DEBUG is on. It never logs on a production site with WP_DEBUG off.
 */
function oc_debug_log( $event, $data = [], $force = false ) {
	$allow = oc_debug_enabled() || ( $force && defined( 'WP_DEBUG' ) && WP_DEBUG );
	if ( ! $allow ) {
		return;
	}
	$line = sprintf(
		"[%s] %s | user=%d | %s\n",
		gmdate( 'Y-m-d H:i:s' ),
		$event,
		(int) get_current_user_id(),
		wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
	);
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['basedir'] ) ) {
		@file_put_contents( trailingslashit( $uploads['basedir'] ) . 'oc-debug.log', $line, FILE_APPEND, LOCK_EX );
	}
}

function oc_image_or_placeholder( $attachment_id, $size = 'medium', $alt = '' ) {
	if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
		return wp_get_attachment_image( $attachment_id, $size, false, [ 'alt' => $alt, 'loading' => 'lazy' ] );
	}
	return '<div class="oc-image-placeholder" aria-hidden="true"><span>OC</span></div>';
}

function oc_status_label( $status ) {
	switch ( $status ) {
		case OC_STATUS_PENDING:  return __( 'Pending Review', 'owambe-connect-core' );
		case OC_STATUS_APPROVED: return __( 'Approved & Live', 'owambe-connect-core' );
		case OC_STATUS_REJECTED: return __( 'Needs Changes', 'owambe-connect-core' );
		default:                 return ucfirst( str_replace( 'oc_', '', (string) $status ) );
	}
}

/**
 * Whether a vendor has submitted their listing for admin review. Vendors who
 * just signed up are in a "draft" state — they finish their profile in the
 * dashboard, then submit when ready. Until then, admin doesn't see them in the
 * pending queue.
 *
 * Posts that pre-date the draft/submit flow won't have the meta set at all —
 * we treat those as submitted (anything pre-existing was already in admin's
 * pending queue under the old flow). Only an explicit `0` marks a draft.
 */
function oc_is_submitted_for_review( $post_id ) {
	$post_id = (int) $post_id;
	// No post yet = nothing has been submitted. This matters for the new
	// "show dashboard with empty values before the post exists" flow:
	// without this guard the grandfathering branch below treats an empty
	// meta as submitted, which would mis-label a brand-new vendor's
	// status pill as "Pending Review" before they've saved anything.
	if ( $post_id <= 0 ) {
		return false;
	}
	$raw = get_post_meta( $post_id, '_oc_submitted_for_review', true );
	if ( '' === $raw || null === $raw ) {
		return true;
	}
	return (int) $raw === 1;
}

/**
 * Display label that distinguishes the pre-submission "Draft" state from a
 * truly pending listing. Status alone can't carry this — both states share
 * post_status `oc_pending`.
 */
function oc_display_status_label( $post_id, $status ) {
	if ( OC_STATUS_PENDING === $status && ! oc_is_submitted_for_review( $post_id ) ) {
		return __( 'Draft', 'owambe-connect-core' );
	}
	return oc_status_label( $status );
}

/**
 * Weighted profile-completion checklist for a vendor post.
 *
 * Total weights sum to 100 so `percent` is meaningful as-is. The
 * threshold determines when a vendor's profile is considered ready
 * for admin review (soft gate — surface only, not enforced).
 *
 * @param int $post_id  Vendor CPT post ID.
 * @return array{percent:int,tier:string,tier_label:string,tier_color:string,threshold:int,submittable:bool,completed_count:int,total_count:int,checklist:array<int,array{key:string,label:string,weight:int,done:bool,tab:string,focus?:string}>}
 */
function oc_profile_completion( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return [
			'percent'         => 0,
			'tier'            => 'red',
			'tier_label'      => __( 'Not started', 'owambe-connect-core' ),
			'tier_color'      => '#B0354F',
			'threshold'       => 100,
			'submittable'     => false,
			'completed_count' => 0,
			'total_count'     => 0,
			'checklist'       => [],
		];
	}

	$name        = trim( (string) get_post_meta( $post_id, '_oc_business_name', true ) );
	if ( '' === $name ) $name = (string) get_the_title( $post_id );
	$country     = trim( (string) get_post_meta( $post_id, '_oc_location_country', true ) );
	$areas       = (array) get_post_meta( $post_id, '_oc_location_areas', true );
	$areas       = array_values( array_filter( array_map( 'trim', $areas ) ) );
	$regions     = (array) get_post_meta( $post_id, '_oc_location_regions', true );
	$regions     = array_values( array_filter( array_map( 'trim', $regions ) ) );
	$cultural    = (array) get_post_meta( $post_id, '_oc_cultural_specialties', true );
	$cultural    = array_values( array_filter( array_map( 'trim', $cultural ) ) );
	$tags        = (array) get_post_meta( $post_id, '_oc_vendor_tags', true );
	$tags        = array_values( array_filter( array_map( 'trim', $tags ) ) );
	$reg_biz     = trim( (string) get_post_meta( $post_id, '_oc_registered_business', true ) );
	$bio         = trim( wp_strip_all_tags( (string) get_post_meta( $post_id, '_oc_bio', true ) ) );
	$services    = trim( wp_strip_all_tags( (string) get_post_meta( $post_id, '_oc_services', true ) ) );
	$price       = trim( (string) get_post_meta( $post_id, '_oc_price_range', true ) );
	$whatsapp    = trim( (string) get_post_meta( $post_id, '_oc_whatsapp', true ) );
	$pub_email   = trim( (string) get_post_meta( $post_id, '_oc_public_email', true ) );
	$instagram   = trim( (string) get_post_meta( $post_id, '_oc_instagram', true ) );
	$facebook    = trim( (string) get_post_meta( $post_id, '_oc_facebook', true ) );
	$website     = trim( (string) get_post_meta( $post_id, '_oc_website', true ) );
	$logo_id     = (int) get_post_meta( $post_id, '_oc_logo_id', true );
	$banner_id   = (int) get_post_meta( $post_id, '_oc_banner_id', true );
	$gallery     = (array) get_post_meta( $post_id, '_oc_gallery_ids', true );
	$gallery     = array_values( array_filter( array_map( 'intval', $gallery ) ) );

	$cats = wp_get_object_terms( $post_id, OC_TAX, [ 'fields' => 'ids' ] );
	if ( is_wp_error( $cats ) ) $cats = [];

	$has_contact = ( '' !== $whatsapp ) || ( '' !== $pub_email ) || ( '' !== $instagram ) || ( '' !== $facebook ) || ( '' !== $website );
	$bio_len     = function_exists( 'mb_strlen' ) ? mb_strlen( $bio )      : strlen( $bio );
	$svc_len     = function_exists( 'mb_strlen' ) ? mb_strlen( $services ) : strlen( $services );

	// Email-verified: vendors registered before this feature have no meta row,
	// and we treat that as verified (pre-flight grandfathering) so they don't
	// get locked out. Only an explicit `0` (issued at registration) blocks them.
	$ev_raw           = get_post_meta( $post_id, '_oc_email_verified', true );
	$email_verified   = ( '' === $ev_raw || null === $ev_raw ) ? true : ( (int) $ev_raw === 1 );

	// Each checklist item points at the dashboard tab the relevant input
	// lives on, so clicking the item jumps the user straight to the field.
	// Tab map (May 2026 split): business · story · contact · photos · account
	$items = [
		[ 'key' => 'business_name',    'weight' => 10, 'done' => '' !== $name,                                                      'label' => __( 'Business name', 'owambe-connect-core' ),                              'tab' => 'business', 'focus' => 'd-name' ],
		[ 'key' => 'categories',       'weight' => 5,  'done' => count( $cats ) > 0,                                                'label' => __( 'Pick at least one category', 'owambe-connect-core' ),                 'tab' => 'business' ],
		[ 'key' => 'country',          'weight' => 4,  'done' => '' !== $country,                                                   'label' => __( 'Country / region', 'owambe-connect-core' ),                           'tab' => 'business', 'focus' => 'd-country' ],
		[ 'key' => 'areas',            'weight' => 5,  'done' => count( $areas ) > 0 || count( $regions ) > 0,                       'label' => __( 'Cities / areas or a region you cover', 'owambe-connect-core' ),     'tab' => 'business', 'focus' => 'd-areas' ],
		[ 'key' => 'price',            'weight' => 2,  'done' => '' !== $price,                                                     'label' => __( 'Set a price range', 'owambe-connect-core' ),                          'tab' => 'business', 'focus' => 'd-price' ],

		[ 'key' => 'bio',              'weight' => 12, 'done' => $bio_len > 0,                                                      'label' => __( 'Write a bio', 'owambe-connect-core' ),                               'tab' => 'story',    'focus' => 'd-bio' ],
		[ 'key' => 'services',         'weight' => 8,  'done' => $svc_len > 0,                                                      'label' => __( 'Describe services offered', 'owambe-connect-core' ),                  'tab' => 'story',    'focus' => 'd-services' ],
		[ 'key' => 'cultural',         'weight' => 4,  'done' => count( $cultural ) > 0,                                            'label' => __( 'Cultural events / specialties', 'owambe-connect-core' ),              'tab' => 'story',    'focus' => 'd-cultural' ],
		[ 'key' => 'registered_biz',   'weight' => 3,  'done' => in_array( $reg_biz, [ 'yes', 'no' ], true ),                       'label' => __( 'Answer: is your business registered?', 'owambe-connect-core' ),       'tab' => 'story',    'focus' => 'd-regbiz' ],
		[ 'key' => 'vendor_tags',      'weight' => 5,  'done' => count( $tags ) > 0,                                                'label' => __( 'Pick vendor tags', 'owambe-connect-core' ),                           'tab' => 'story',    'focus' => 'd-tags' ],

		[ 'key' => 'contact',          'weight' => 7,  'done' => $has_contact,                                                      'label' => __( 'Add a contact channel (WhatsApp / Email / IG / FB / web)', 'owambe-connect-core' ), 'tab' => 'contact', 'focus' => 'd-wa-local' ],

		[ 'key' => 'logo',             'weight' => 12, 'done' => $logo_id > 0,                                                      'label' => __( 'Upload a logo', 'owambe-connect-core' ),                              'tab' => 'photos' ],
		[ 'key' => 'banner',           'weight' => 7,  'done' => $banner_id > 0,                                                    'label' => __( 'Add a banner image', 'owambe-connect-core' ),                         'tab' => 'photos' ],
		[ 'key' => 'gallery',          'weight' => 12, 'done' => count( $gallery ) >= 3,                                            'label' => __( 'Upload at least 3 gallery photos', 'owambe-connect-core' ),           'tab' => 'photos' ],

		// Email verification is an account-level action, not a listing quality
		// indicator. Weight = 0 so it appears as a checklist reminder without
		// depressing the percentage for vendors who haven't verified yet.
		[ 'key' => 'email_verified',   'weight' => 0,  'done' => $email_verified,                                                   'label' => __( 'Verify your email address', 'owambe-connect-core' ),                  'tab' => 'account' ],
	];

	$earned = 0;
	$total  = 0;
	$done   = 0;
	foreach ( $items as $i ) {
		$total += (int) $i['weight'];
		if ( $i['done'] ) {
			$earned += (int) $i['weight'];
			$done++;
		}
	}
	$percent = $total > 0 ? (int) round( $earned * 100 / $total ) : 0;

	[ $tier, $tier_label, $tier_color ] = oc_completion_tier( $percent );

	// 100% gate per client: vendors can only submit once every item is ticked.
	$threshold = 100;

	return [
		'percent'         => $percent,
		'tier'            => $tier,
		'tier_label'      => $tier_label,
		'tier_color'      => $tier_color,
		'threshold'       => $threshold,
		'submittable'     => $percent >= $threshold,
		'completed_count' => $done,
		'total_count'     => count( $items ),
		'checklist'       => $items,
	];
}

/**
 * Map a completion percent to a tier slug + label + colour.
 *
 * @return array{0:string,1:string,2:string} [ slug, label, hex ]
 */
function oc_completion_tier( $percent ) {
	$p = (int) $percent;
	if ( $p >= 90 ) return [ 'gold',  __( 'Excellent',            'owambe-connect-core' ), '#A8893D' ];
	if ( $p >= 70 ) return [ 'green', __( 'Ready for review',     'owambe-connect-core' ), '#2E7D5B' ];
	if ( $p >= 40 ) return [ 'amber', __( 'Almost reviewable',    'owambe-connect-core' ), '#B8860B' ];
	return                 [ 'red',   __( 'Just getting started', 'owambe-connect-core' ), '#B0354F' ];
}

/**
 * Recompute completion percent and cache it in post meta so admin
 * list queries can read it without recomputing per row.
 */
function oc_profile_completion_save( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || OC_CPT !== get_post_type( $post_id ) ) return;
	$data = oc_profile_completion( $post_id );
	update_post_meta( $post_id, '_oc_completion_pct', (int) $data['percent'] );
}
add_action( 'oc_after_vendor_registered', 'oc_profile_completion_save', 10, 1 );
add_action( 'oc_after_vendor_updated',    'oc_profile_completion_save', 10, 1 );

/**
 * Format a sequential integer as the vendor registration number
 * (e.g. 1 → `OC0001VRN`). 4-digit zero-padded core, brackets out at 10000+.
 */
function oc_format_vendor_number( $n ) {
	$n = (int) $n;
	if ( $n <= 0 ) {
		return '';
	}
	return 'OC' . str_pad( (string) $n, 4, '0', STR_PAD_LEFT ) . 'VRN';
}

/**
 * Atomically reserve the next vendor sequence number using a WP option
 * (relies on `update_option`'s row-level UPDATE, plus a transient lock
 * to make double-clicks safe). Returns the integer; callers wrap it via
 * oc_format_vendor_number() for display.
 */
function oc_next_vendor_sequence() {
	$lock_key = 'oc_vendor_seq_lock';
	$retries  = 0;
	while ( get_transient( $lock_key ) && $retries < 20 ) {
		usleep( 50000 ); // 50ms
		$retries++;
	}
	set_transient( $lock_key, 1, 5 );

	$current = (int) get_option( 'oc_vendor_seq', 0 );
	$next    = $current + 1;
	update_option( 'oc_vendor_seq', $next, false );

	delete_transient( $lock_key );
	return $next;
}

/**
 * Assign a vendor registration number to a vendor post if it doesn't
 * already have one. Idempotent — safe to call repeatedly. Hooked into
 * `oc_after_vendor_registered` so every new vendor gets one immediately.
 */
function oc_assign_vendor_number( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || OC_CPT !== get_post_type( $post_id ) ) {
		return '';
	}
	$existing = (string) get_post_meta( $post_id, '_oc_vendor_number', true );
	if ( '' !== $existing ) {
		return $existing;
	}
	$num    = oc_next_vendor_sequence();
	$number = oc_format_vendor_number( $num );
	update_post_meta( $post_id, '_oc_vendor_number',     $number );
	update_post_meta( $post_id, '_oc_vendor_number_int', $num );
	return $number;
}
add_action( 'oc_after_vendor_registered', 'oc_assign_vendor_number', 5, 1 );

/**
 * Public accessor — returns the stored vendor registration number string
 * or empty when the vendor pre-dates the feature and hasn't been
 * backfilled yet.
 */
function oc_get_vendor_number( $post_id ) {
	return (string) get_post_meta( (int) $post_id, '_oc_vendor_number', true );
}

/**
 * One-shot backfill — assigns numbers to every vendor post that doesn't
 * have one. Called from the plugin activator and from the admin "tools"
 * screen. Safe to re-run.
 */
function oc_backfill_vendor_numbers() {
	$ids = get_posts( [
		'post_type'      => OC_CPT,
		'post_status'    => 'any',
		'fields'         => 'ids',
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'posts_per_page' => -1,
		'meta_query'     => [
			[ 'key' => '_oc_vendor_number', 'compare' => 'NOT EXISTS' ],
		],
	] );
	foreach ( $ids as $pid ) {
		oc_assign_vendor_number( $pid );
	}
	return count( $ids );
}

/**
 * Resolve the icon for a vendor category term. Cascade:
 *
 *   1. Term-meta image (uploaded by admin)
 *   2. Term-meta emoji (set by admin)
 *   3. Hardcoded default emoji for the 11 known slugs
 *   4. Empty — caller draws a generic fallback SVG
 *
 * Returns a normalised array so callers don't need to reach into term meta
 * directly. Pass either a WP_Term or a term ID.
 */
function oc_get_category_icon( $term ) {
	if ( is_numeric( $term ) ) {
		$term = get_term( (int) $term, OC_TAX );
	}
	$out = [
		'emoji'     => '',
		'image_id'  => 0,
		'image_url' => '',
		'source'    => 'fallback', // 'image' | 'emoji_custom' | 'emoji_default' | 'fallback'
	];
	if ( ! $term || is_wp_error( $term ) ) {
		return $out;
	}

	$image_id = (int) get_term_meta( $term->term_id, '_oc_cat_icon_image_id', true );
	if ( $image_id && wp_attachment_is_image( $image_id ) ) {
		$out['image_id']  = $image_id;
		$out['image_url'] = wp_get_attachment_image_url( $image_id, 'thumbnail' );
		$out['source']    = 'image';
		// Image present but we still resolve an emoji underneath as the
		// dropdown fallback (selects can't render images).
	}

	$custom_emoji = (string) get_term_meta( $term->term_id, '_oc_cat_icon_emoji', true );
	if ( '' !== $custom_emoji ) {
		$out['emoji']  = $custom_emoji;
		if ( 'image' !== $out['source'] ) {
			$out['source'] = 'emoji_custom';
		}
		return $out;
	}

	// Fallback to the hardcoded default for known slugs.
	$default = class_exists( 'OC_Category_Icons' )
		? OC_Category_Icons::default_emoji_for_slug( $term->slug )
		: '';
	if ( '' !== $default ) {
		$out['emoji'] = $default;
		if ( 'image' !== $out['source'] ) {
			$out['source'] = 'emoji_default';
		}
	}
	return $out;
}

/**
 * Render the "Verified vendor" badge. Uses the Heroicons-style scalloped
 * circle ("patch-check") in burgundy with a white check on top — same
 * visual language clients already recognise from Twitter/Instagram/App Store
 * verified marks. Pure icon (no text label) so it can sit cleanly inline
 * with the H1 title without breaking the heading flow.
 */
function oc_verified_badge_html( $post_id ) {
	$verified = (int) get_post_meta( (int) $post_id, '_oc_verified', true ) === 1;
	if ( ! $verified ) {
		return '';
	}
	$svg  = '<svg class="oc-badge__svg" viewBox="0 0 24 24" width="22" height="22" fill="none" aria-hidden="true" focusable="false">';
	// Scalloped circle background.
	$svg .= '<path class="oc-badge__bg" fill="currentColor" d="M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>';
	// Checkmark on top.
	$svg .= '<path d="M9 12.5l2.2 2.2L15.5 10" stroke="#fff" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>';
	$svg .= '</svg>';
	return '<span class="oc-badge oc-badge--verified" role="img" aria-label="' . esc_attr__( 'Verified vendor', 'owambe-connect-core' ) . '" title="' . esc_attr__( 'Verified by Owambe Connect', 'owambe-connect-core' ) . '">' . $svg . '</span>';
}

/**
 * Render the "Founding vendor" badge — same scalloped silhouette as the
 * verified badge so the two read as a system, but in brand gold with a
 * star inside to differentiate at a glance.
 */
function oc_founding_badge_html( $post_id ) {
	$founding = (int) get_post_meta( (int) $post_id, '_oc_founding_vendor', true ) === 1;
	if ( ! $founding ) {
		return '';
	}
	$svg  = '<svg class="oc-badge__svg" viewBox="0 0 24 24" width="22" height="22" fill="none" aria-hidden="true" focusable="false">';
	$svg .= '<path class="oc-badge__bg" fill="currentColor" d="M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>';
	// Five-point star.
	$svg .= '<path fill="#fff" d="M12 7.4l1.34 2.72 3 .44-2.17 2.11.51 2.99L12 14.24l-2.68 1.42.51-2.99-2.17-2.11 3-.44L12 7.4z"/>';
	$svg .= '</svg>';
	return '<span class="oc-badge oc-badge--founding" role="img" aria-label="' . esc_attr__( 'Founding vendor', 'owambe-connect-core' ) . '" title="' . esc_attr__( 'Founding vendor on Owambe Connect', 'owambe-connect-core' ) . '">' . $svg . '</span>';
}

/**
 * Gallery slot cap for one vendor — plan-derived hook point (Phase 2 §7.2).
 *
 * Today every vendor gets the flat `gallery_max_images` setting (default 6).
 * When subscription tiers land (W4), paid plans raise this to ~10 by filtering
 * `oc_vendor_gallery_cap` — consumers never need to change again.
 *
 * @param int $vendor_id  Vendor post ID (0 = generic/site default).
 * @return int
 */
function oc_vendor_gallery_cap( $vendor_id = 0 ) {
	$cap = max( 0, (int) oc_get_setting( 'gallery_max_images', 6 ) );
	return max( 0, (int) apply_filters( 'oc_vendor_gallery_cap', $cap, (int) $vendor_id ) );
}
