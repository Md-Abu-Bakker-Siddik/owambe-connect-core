<?php
/**
 * Single vendor profile — full detail page.
 *
 * Rendered both by the [oc_vendor_profile] shortcode and auto-injected on
 * /vendor/<slug>/ URLs by OC_Shortcodes::auto_inject_profile().
 *
 * @package OwambeConnect
 * @var int $post_id
 */
defined( 'ABSPATH' ) || exit;

$id = (int) $post_id;
if ( ! $id || OC_CPT !== get_post_type( $id ) ) {
	return;
}

$post      = get_post( $id );
$title     = get_the_title( $id );
$bio       = (string) get_post_meta( $id, '_oc_bio',         true );
$services  = (string) get_post_meta( $id, '_oc_services',    true );
$location  = (string) get_post_meta( $id, '_oc_location',    true );
$price     = (string) get_post_meta( $id, '_oc_price_range', true );
$whatsapp  = (string) get_post_meta( $id, '_oc_whatsapp',    true );
$instagram = (string) get_post_meta( $id, '_oc_instagram',   true );
$facebook  = (string) get_post_meta( $id, '_oc_facebook',    true );
$website   = (string) get_post_meta( $id, '_oc_website',     true );
$languages = get_post_meta( $id, '_oc_languages',   true );
$languages = is_array( $languages ) ? $languages : ( $languages ? array_map( 'trim', explode( ',', (string) $languages ) ) : [] );
$logo_id   = (int) get_post_meta( $id, '_oc_logo_id',   true );
$banner_id = (int) get_post_meta( $id, '_oc_banner_id', true );
$gallery_ids = (array) get_post_meta( $id, '_oc_gallery_ids', true );
$gallery_ids = array_values( array_filter( array_map( 'intval', $gallery_ids ) ) );
$featured  = (int) get_post_meta( $id, '_oc_featured', true ) === 1;
$cats      = wp_get_post_terms( $id, OC_TAX );
if ( is_wp_error( $cats ) ) $cats = [];
$prices    = oc_price_range_options();
$price_lbl = ( $price && isset( $prices[ $price ] ) ) ? $prices[ $price ] : '';

// ── May 2026 client-feedback fields — read once and normalise. ──────
$country_slug   = (string) get_post_meta( $id, '_oc_location_country', true );
$areas          = (array)  get_post_meta( $id, '_oc_location_areas', true );
$areas          = array_values( array_filter( array_map( 'trim', $areas ) ) );
$regions        = (array)  get_post_meta( $id, '_oc_location_regions', true );
$regions        = array_values( array_filter( array_map( 'trim', $regions ) ) );
$cultural       = (array)  get_post_meta( $id, '_oc_cultural_specialties', true );
$cultural       = array_values( array_filter( array_map( 'trim', $cultural ) ) );
$reg_biz        = (string) get_post_meta( $id, '_oc_registered_business', true );
$nigerian       = (string) get_post_meta( $id, '_oc_nigerian_specialty', true );
$vendor_tags    = (array)  get_post_meta( $id, '_oc_vendor_tags', true );
$vendor_tags    = array_values( array_filter( array_map( 'trim', $vendor_tags ) ) );
$public_email   = (string) get_post_meta( $id, '_oc_public_email', true );

$country_labels   = function_exists( 'oc_country_options' )            ? oc_country_options()            : [];
$country_label    = ( $country_slug && isset( $country_labels[ $country_slug ] ) ) ? $country_labels[ $country_slug ] : '';
$cultural_options = function_exists( 'oc_cultural_specialty_options' ) ? oc_cultural_specialty_options() : [];

// Phase 2 — the wa.me link now carries the pre-filled enquiry message so
// vendors can see the lead came from Owambe Connect.
$wa_link  = oc_whatsapp_link( $whatsapp, function_exists( 'oc_whatsapp_prefill' ) ? oc_whatsapp_prefill( $id ) : '' );
$ig_link  = oc_instagram_link( $instagram );
$fb_link  = oc_facebook_link( $facebook );
$website_host = $website ? wp_parse_url( $website, PHP_URL_HOST ) : '';
$initial  = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 1 ) : substr( $title, 0, 1 );

$dir_url        = oc_page_url( 'vendors' );
$listed_since   = strtotime( $post->post_date );
$primary_cat    = ! empty( $cats ) ? $cats[0] : null;
// Hero uses the vendor's Display picture (banner) only — no gallery-pick or logo
// fallback. With no banner, the burgundy fallback gradient shows instead.
$banner_url     = $banner_id ? ( wp_get_attachment_image_url( $banner_id, 'oc-banner' ) ?: wp_get_attachment_image_url( $banner_id, 'large' ) ) : '';
$logo_url       = $logo_id   ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

// Similar vendors — same category, exclude self.
$similar = [];
if ( $primary_cat ) {
	$similar = get_posts( [
		'post_type'      => OC_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => 4,
		'post__not_in'   => [ $id ],
		'orderby'        => 'rand',
		'tax_query'      => [ [
			'taxonomy' => OC_TAX,
			'field'    => 'term_id',
			'terms'    => [ (int) $primary_cat->term_id ],
		] ],
	] );
}

// JSON-LD for SEO.
$json_ld = [
	'@context'    => 'https://schema.org',
	'@type'       => 'LocalBusiness',
	'name'        => $title,
	'description' => wp_strip_all_tags( $bio ),
	'url'         => get_permalink( $id ),
];
if ( $logo_url )    $json_ld['image']      = $logo_url;
if ( $location )    $json_ld['areaServed'] = $location;
if ( $areas )       $json_ld['areaServed'] = $areas;
if ( $whatsapp )    $json_ld['telephone']  = $whatsapp;
if ( $public_email && is_email( $public_email ) ) $json_ld['email'] = $public_email;
if ( $price_lbl )   $json_ld['priceRange'] = preg_replace( '/[^£$€¥]/', '', $price_lbl ) ?: $price_lbl;
$same_as = array_filter( [ $ig_link, $fb_link, $website ] );
if ( $same_as ) $json_ld['sameAs'] = array_values( $same_as );

// Phase 2 — reviews aggregate (only when approved reviews exist).
$rating_avg   = (float) get_post_meta( $id, '_oc_rating_avg', true );
$rating_count = (int) get_post_meta( $id, '_oc_rating_count', true );
if ( $rating_count > 0 && $rating_avg > 0 ) {
	$json_ld['aggregateRating'] = [
		'@type'       => 'AggregateRating',
		'ratingValue' => $rating_avg,
		'reviewCount' => $rating_count,
		'bestRating'  => 5,
	];
}
?>
<article class="oc-vp" id="oc-vendor-<?php echo (int) $id; ?>">

	<?php
	// Phase 2 — sticky in-page section nav (mini-site feel), placed above the
	// breadcrumb so it's visible immediately and pins to the top on scroll.
	// Order MUST match the on-page section order below. Only links to sections
	// that actually render. NOT classed .oc-nav (theme header CSS hides that
	// class on mobile).
	$vp_nav_items = array_filter( [
		'oc-about'      => $bio ? __( 'About', 'owambe-connect-core' ) : '',
		'oc-services'   => $services ? __( 'Services', 'owambe-connect-core' ) : '',
		'oc-portfolio'  => $gallery_ids ? __( 'Portfolio', 'owambe-connect-core' ) : '',
		'oc-how'        => __( 'How to book', 'owambe-connect-core' ),
		'reviews'       => class_exists( 'OC_Reviews' ) ? __( 'Reviews', 'owambe-connect-core' ) : '',
		'oc-contact'    => __( 'Contact', 'owambe-connect-core' ),
	] );
	?>
	<?php if ( count( $vp_nav_items ) > 2 ) : ?>
		<nav class="oc-vp-nav" aria-label="<?php esc_attr_e( 'Profile sections', 'owambe-connect-core' ); ?>">
			<div class="oc-container oc-vp-nav__inner">
				<?php foreach ( $vp_nav_items as $anchor => $label ) : ?>
					<a class="oc-vp-nav__link" href="#<?php echo esc_attr( $anchor ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</div>
		</nav>
	<?php endif; ?>

	<!-- Banner hero -->
	<header class="oc-vp__hero">
		<div class="oc-container oc-vp__hero-top">
			<nav class="oc-vp__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'owambe-connect-core' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'owambe-connect-core' ); ?></a>
				<span aria-hidden="true">›</span>
				<a href="<?php echo esc_url( $dir_url ); ?>"><?php esc_html_e( 'Vendors', 'owambe-connect-core' ); ?></a>
				<?php if ( $primary_cat ) : ?>
					<span aria-hidden="true">›</span>
					<a href="<?php echo esc_url( add_query_arg( 'cat', $primary_cat->slug, $dir_url ) ); ?>"><?php echo esc_html( $primary_cat->name ); ?></a>
				<?php endif; ?>
				<span aria-hidden="true">›</span>
				<span class="oc-vp__crumb-current"><?php echo esc_html( $title ); ?></span>
			</nav>
			<div class="oc-vp__banner" <?php if ( $banner_url ) printf( 'style="background-image:url(%s)"', esc_url( $banner_url ) ); ?>>
				<?php if ( ! $banner_url ) : ?>
					<div class="oc-vp__banner-fallback" aria-hidden="true"></div>
				<?php endif; ?>
			</div>
		</div>

		<div class="oc-container oc-vp__hero-inner">
			<div class="oc-vp__identity">
				<div class="oc-vp__logo">
					<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $title ); ?>"/>
					<?php else : ?>
						<span class="oc-vp__logo-fallback" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
					<?php endif; ?>
					<?php if ( $featured ) : ?><span class="oc-vp__featured" title="<?php esc_attr_e( 'Featured vendor', 'owambe-connect-core' ); ?>">★</span><?php endif; ?>
				</div>

				<div class="oc-vp__heading">
					<h1 class="oc-vp__title">
						<?php echo esc_html( $title ); ?>
						<?php
						// Trust badges — only render when actually applicable.
						echo oc_verified_badge_html( $post->ID ); // phpcs:ignore — trusted markup
						echo oc_founding_badge_html( $post->ID ); // phpcs:ignore — trusted markup
						?>
					</h1>
					<?php $oc_vendor_number_pub = oc_get_vendor_number( $post->ID ); ?>
					<?php if ( $oc_vendor_number_pub ) : ?>
						<div class="oc-vp__vendor-num" title="<?php esc_attr_e( 'Vendor registration number on Owambe Connect', 'owambe-connect-core' ); ?>">
							<?php echo esc_html( $oc_vendor_number_pub ); ?>
						</div>
					<?php endif; ?>
					<?php if ( $rating_count > 0 && class_exists( 'OC_Reviews' ) ) : ?>
						<a class="oc-vp__rating" href="#reviews">
							<?php echo wp_kses_post( OC_Reviews::stars_html( $rating_avg, $rating_count ) ); ?>
						</a>
					<?php endif; ?>
					<div class="oc-vp__meta">
						<?php
						// Prefer the structured areas + country fields; fall back to the
						// legacy free-text location if neither is set.
						$location_display = '';
						if ( $areas || $regions || $country_label ) {
							$bits = [];
							if ( $areas ) {
								$shown = array_slice( $areas, 0, 3 );
								$extra = count( $areas ) - count( $shown );
								$bits[] = esc_html( implode( ', ', $shown ) ) . ( $extra > 0 ? ' +' . (int) $extra : '' );
							}
							if ( $regions ) {
								$bits[] = esc_html( implode( ', ', $regions ) );
							}
							if ( $country_label ) {
								$bits[] = esc_html( $country_label );
							}
							$location_display = implode( ' — ', $bits );
						} elseif ( $location ) {
							$location_display = esc_html( $location );
						}
						?>
						<?php if ( $location_display ) : ?>
							<span class="oc-vp__meta-item"><span class="oc-vp__meta-icon">📍</span><?php echo $location_display; // phpcs:ignore — pre-escaped ?></span>
						<?php endif; ?>
						<?php if ( $price_lbl ) : ?>
							<span class="oc-vp__meta-item"><span class="oc-vp__meta-icon">💷</span><?php echo esc_html( $price_lbl ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $languages ) ) : ?>
							<span class="oc-vp__meta-item"><span class="oc-vp__meta-icon">🌍</span><?php echo esc_html( implode( ', ', array_slice( $languages, 0, 3 ) ) ); ?><?php if ( count( $languages ) > 3 ) echo ' +' . ( count( $languages ) - 3 ); ?></span>
						<?php endif; ?>
						<span class="oc-vp__meta-item"><span class="oc-vp__meta-icon">📅</span><?php
							/* translators: %s: time difference, e.g. "2 months" */
							printf( esc_html__( 'Listed %s ago', 'owambe-connect-core' ), esc_html( human_time_diff( $listed_since, current_time( 'timestamp' ) ) ) );
						?></span>
					</div>
					<?php if ( ! empty( $cats ) ) : ?>
						<div class="oc-vp__cats">
							<?php foreach ( $cats as $c ) : ?>
								<a class="oc-vp__pill" href="<?php echo esc_url( add_query_arg( 'cat', $c->slug, $dir_url ) ); ?>"><?php echo esc_html( $c->name ); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="oc-vp__cta">
					<?php
					// Phase 2 — save-to-list heart. Logged-out visitors are sent to
					// client sign-in with a return path; the toggle itself is AJAX.
					$is_saved = function_exists( 'oc_is_vendor_saved' ) && oc_is_vendor_saved( $id );
					if ( is_user_logged_in() ) : ?>
						<button type="button" class="oc-save-btn<?php echo $is_saved ? ' is-saved' : ''; ?>" data-oc-save="<?php echo (int) $id; ?>" aria-pressed="<?php echo $is_saved ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Save this vendor to your list', 'owambe-connect-core' ); ?>" title="<?php esc_attr_e( 'Save vendor', 'owambe-connect-core' ); ?>">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
						</button>
					<?php else : ?>
						<a class="oc-save-btn" href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( get_permalink( $id ) ), oc_page_url( 'client-login' ) ) ); ?>" aria-label="<?php esc_attr_e( 'Sign in to save this vendor', 'owambe-connect-core' ); ?>" title="<?php esc_attr_e( 'Sign in to save', 'owambe-connect-core' ); ?>">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
						</a>
					<?php endif; ?>
					<?php if ( $wa_link ) : ?>
						<a class="oc-vp__btn oc-vp__btn--wa" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $wa_link ); ?>" data-oc-track="whatsapp" data-vendor="<?php echo (int) $id; ?>">
							<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M20.52 3.45A11.81 11.81 0 0012.05 0C5.5 0 .18 5.32.18 11.86a11.78 11.78 0 001.6 5.94L0 24l6.36-1.66a11.85 11.85 0 005.7 1.45h.01c6.55 0 11.87-5.32 11.87-11.86 0-3.17-1.24-6.15-3.42-8.48zM12.07 21.8h-.01a9.85 9.85 0 01-5.02-1.37l-.36-.21-3.78.99 1-3.69-.24-.38a9.84 9.84 0 01-1.51-5.28c0-5.45 4.43-9.88 9.88-9.88 2.64 0 5.12 1.03 6.99 2.9a9.81 9.81 0 012.9 6.99c0 5.45-4.43 9.93-9.85 9.93zm5.42-7.4c-.3-.15-1.76-.87-2.04-.97-.27-.1-.47-.15-.66.15-.2.3-.76.97-.93 1.17-.17.2-.34.22-.64.07-.3-.15-1.27-.47-2.42-1.5-.9-.8-1.5-1.78-1.67-2.08-.17-.3-.02-.46.13-.61.13-.13.3-.34.45-.51.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.66-1.6-.9-2.18-.24-.58-.48-.5-.66-.51-.17-.01-.37-.01-.57-.01-.2 0-.5.07-.77.37-.27.3-1.02 1-1.02 2.43s1.05 2.82 1.2 3.02c.15.2 2.07 3.16 5.02 4.43.7.3 1.25.48 1.68.62.7.22 1.34.19 1.85.12.56-.08 1.76-.72 2-1.42.25-.7.25-1.3.17-1.42-.07-.13-.27-.2-.57-.35z"/></svg>
							<?php esc_html_e( 'Message on WhatsApp', 'owambe-connect-core' ); ?>
						</a>
					<?php endif; ?>
					<?php
					$share_url   = get_permalink( $id );
					$share_title = $title;
					$share_text  = sprintf( __( 'Check out %s on Owambe Connect', 'owambe-connect-core' ), $title );
					?>
					<div class="oc-vp__share" data-oc-share-wrap>
						<button type="button" class="oc-vp__btn oc-vp__btn--ghost" data-oc-share aria-haspopup="true" aria-expanded="false">
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 12v7a2 2 0 002 2h12a2 2 0 002-2v-7"/><path d="M16 6l-4-4-4 4"/><path d="M12 2v14"/></svg>
							<?php esc_html_e( 'Share', 'owambe-connect-core' ); ?>
						</button>
						<div class="oc-vp__share-menu" role="menu" hidden>
							<a class="oc-vp__share-item" role="menuitem" target="_blank" rel="noopener noreferrer" href="https://wa.me/?text=<?php echo rawurlencode( $share_text . ' ' . $share_url ); ?>">
								<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M20.52 3.45A11.81 11.81 0 0012.05 0C5.5 0 .18 5.32.18 11.86a11.78 11.78 0 001.6 5.94L0 24l6.36-1.66a11.85 11.85 0 005.7 1.45h.01c6.55 0 11.87-5.32 11.87-11.86 0-3.17-1.24-6.15-3.42-8.48zM12.07 21.8h-.01a9.85 9.85 0 01-5.02-1.37l-.36-.21-3.78.99 1-3.69-.24-.38a9.84 9.84 0 01-1.51-5.28c0-5.45 4.43-9.88 9.88-9.88 2.64 0 5.12 1.03 6.99 2.9a9.81 9.81 0 012.9 6.99c0 5.45-4.43 9.93-9.85 9.93z"/></svg>
								<?php esc_html_e( 'WhatsApp', 'owambe-connect-core' ); ?>
							</a>
							<a class="oc-vp__share-item" role="menuitem" target="_blank" rel="noopener noreferrer" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( $share_url ); ?>">
								<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 10-11.56 9.88V14.9H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.77l-.44 2.9h-2.33v6.98A10 10 0 0022 12z"/></svg>
								<?php esc_html_e( 'Facebook', 'owambe-connect-core' ); ?>
							</a>
							<a class="oc-vp__share-item" role="menuitem" target="_blank" rel="noopener noreferrer" href="https://twitter.com/intent/tweet?text=<?php echo rawurlencode( $share_text ); ?>&url=<?php echo rawurlencode( $share_url ); ?>">
								<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18.244 2H21l-6.49 7.41L22 22h-6.18l-4.84-6.32L5.4 22H2.64l6.94-7.93L2 2h6.32l4.37 5.78L18.244 2zm-2.17 18h1.7L7.97 4H6.16l9.914 16z"/></svg>
								<?php esc_html_e( 'X (Twitter)', 'owambe-connect-core' ); ?>
							</a>
							<a class="oc-vp__share-item" role="menuitem" href="mailto:?subject=<?php echo rawurlencode( $share_title ); ?>&body=<?php echo rawurlencode( $share_text . "\n\n" . $share_url ); ?>">
								<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
								<?php esc_html_e( 'Email', 'owambe-connect-core' ); ?>
							</a>
							<button type="button" class="oc-vp__share-item" role="menuitem" data-oc-share-copy data-url="<?php echo esc_attr( $share_url ); ?>">
								<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
								<span data-oc-copy-label><?php esc_html_e( 'Copy link', 'owambe-connect-core' ); ?></span>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</header>

	<!-- Body -->
	<div class="oc-container oc-vp__body">
		<div class="oc-vp__main">
			<?php if ( $bio ) : ?>
				<section class="oc-vp__section" id="oc-about">
					<h2><?php esc_html_e( 'About', 'owambe-connect-core' ); ?></h2>
					<div class="oc-prose"><?php echo wp_kses_post( wpautop( $bio ) ); ?></div>
				</section>
			<?php endif; ?>

			<?php
			// Specialties & credentials — only render when there's something to show.
			$has_specialties = $cultural || 'yes' === $nigerian || in_array( $reg_biz, [ 'yes', 'no' ], true );
			?>
			<?php if ( $has_specialties ) : ?>
				<section class="oc-vp__section">
					<h2><?php esc_html_e( 'Specialties &amp; credentials', 'owambe-connect-core' ); ?></h2>
					<div class="oc-vp__cultural-row">
						<?php foreach ( $cultural as $key ) :
							$label = $cultural_options[ $key ] ?? $key;
						?>
							<span class="oc-vp__cultural-chip oc-vp__cultural-chip--<?php echo esc_attr( $key ); ?>">
								<svg class="oc-vp__cultural-chip-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l2.4 5.6L20 10l-5.6 2.4L12 18l-2.4-5.6L4 10l5.6-2.4L12 2z"/></svg>
								<?php echo esc_html( $label ); ?>
							</span>
						<?php endforeach; ?>
						<?php if ( 'yes' === $nigerian ) : ?>
							<span class="oc-vp__cultural-chip oc-vp__cultural-chip--nigerian">
								<span aria-hidden="true">🇳🇬</span>
								<?php esc_html_e( 'Nigerian events specialist', 'owambe-connect-core' ); ?>
							</span>
						<?php endif; ?>
						<?php if ( 'yes' === $reg_biz ) : ?>
							<span class="oc-vp__cultural-chip oc-vp__cultural-chip--registered">
								<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
								<?php esc_html_e( 'Registered business', 'owambe-connect-core' ); ?>
							</span>
						<?php elseif ( 'no' === $reg_biz ) : ?>
							<span class="oc-vp__cultural-chip oc-vp__cultural-chip--unregistered" title="<?php esc_attr_e( 'Vendor has indicated they are not a registered business.', 'owambe-connect-core' ); ?>">
								<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
								<?php esc_html_e( 'Sole trader / not yet registered', 'owambe-connect-core' ); ?>
							</span>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $services ) : ?>
				<section class="oc-vp__section" id="oc-services">
					<h2><?php esc_html_e( 'Services Offered', 'owambe-connect-core' ); ?></h2>
					<div class="oc-prose"><?php echo wp_kses_post( wpautop( $services ) ); ?></div>
				</section>
			<?php endif; ?>

			<?php
			// Vendor tags — bucket the saved flat tags back into the canonical
			// sub-group structure so we can render them under tidy headings
			// rather than as one giant chip wall.
			if ( $vendor_tags && function_exists( 'oc_vendor_tag_options' ) ) :
				$tag_groups_def = oc_vendor_tag_options();
				$tags_by_group  = [];
				foreach ( $tag_groups_def as $group_label => $group_tag_list ) {
					$matched = array_values( array_intersect( $group_tag_list, $vendor_tags ) );
					if ( $matched ) $tags_by_group[ $group_label ] = $matched;
				}
				if ( $tags_by_group ) : ?>
					<section class="oc-vp__section">
						<h2><?php esc_html_e( 'What we offer', 'owambe-connect-core' ); ?></h2>
						<div class="oc-vp__tag-groups">
							<?php foreach ( $tags_by_group as $group => $tag_list ) : ?>
								<div class="oc-vp__tag-group">
									<h3 class="oc-vp__tag-group-title"><?php echo esc_html( $group ); ?></h3>
									<div class="oc-vp__tag-row">
										<?php foreach ( $tag_list as $tag ) : ?>
											<span class="oc-vp__tag-chip"><?php echo esc_html( $tag ); ?></span>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif;
			endif; ?>

			<?php if ( $gallery_ids ) : ?>
				<section class="oc-vp__section oc-vp__gallery-section" id="oc-portfolio">
					<h2><?php esc_html_e( 'Portfolio', 'owambe-connect-core' ); ?></h2>
					<div class="oc-vp__gallery" data-oc-lightbox>
						<?php foreach ( $gallery_ids as $g_idx => $gid ) :
							$thumb = wp_get_attachment_image_url( $gid, 'medium' );
							$full  = wp_get_attachment_image_url( $gid, 'large' ) ?: wp_get_attachment_image_url( $gid, 'full' );
							$alt   = trim( (string) get_post_meta( $gid, '_wp_attachment_image_alt', true ) ) ?: $title;
							if ( ! $thumb ) continue;
							?>
							<a class="oc-vp__gallery-item" href="<?php echo esc_url( $full ?: $thumb ); ?>" data-oc-lb-idx="<?php echo (int) $g_idx; ?>">
								<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy"/>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $languages ) ) : ?>
				<section class="oc-vp__section">
					<h2><?php esc_html_e( 'Languages spoken', 'owambe-connect-core' ); ?></h2>
					<div class="oc-vp__lang-row">
						<?php foreach ( $languages as $lang ) : ?>
							<span class="oc-vp__lang-chip">🗣️ <?php echo esc_html( $lang ); ?></span>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<section class="oc-vp__section oc-vp__how" id="oc-how">
				<h2><?php esc_html_e( 'How to book', 'owambe-connect-core' ); ?></h2>
				<ol class="oc-vp__steps">
					<li>
						<span class="oc-vp__step-num">1</span>
						<div>
							<strong><?php esc_html_e( 'Reach out', 'owambe-connect-core' ); ?></strong>
							<p><?php esc_html_e( 'Tap WhatsApp, DM on Instagram, or visit the website. Mention "Owambe Connect" so the vendor knows where you found them.', 'owambe-connect-core' ); ?></p>
						</div>
					</li>
					<li>
						<span class="oc-vp__step-num">2</span>
						<div>
							<strong><?php esc_html_e( 'Share your details', 'owambe-connect-core' ); ?></strong>
							<p><?php esc_html_e( 'Date, location, headcount, and any specific requests. The more detail, the more accurate the quote.', 'owambe-connect-core' ); ?></p>
						</div>
					</li>
					<li>
						<span class="oc-vp__step-num">3</span>
						<div>
							<strong><?php esc_html_e( 'Confirm and celebrate', 'owambe-connect-core' ); ?></strong>
							<p><?php esc_html_e( 'Lock the booking with the vendor. Owambe Connect doesn\'t take a cut — your contract is directly with them.', 'owambe-connect-core' ); ?></p>
						</div>
					</li>
				</ol>
			</section>

			<?php if ( class_exists( 'OC_Reviews' ) ) : ?>
				<section class="oc-vp__section oc-vp__reviews-section">
					<?php echo oc_get_template( 'partials/review-list.php', [ 'vendor_id' => $id ] ); // phpcs:ignore — template output ?>
					<?php echo oc_get_template( 'partials/review-form.php', [ 'vendor_id' => $id ] ); // phpcs:ignore — template output ?>
				</section>
			<?php endif; ?>
		</div>

		<aside class="oc-vp__aside">
			<div class="oc-vp__card oc-vp__card--contact" id="oc-contact">
				<h3><?php esc_html_e( 'Get in touch', 'owambe-connect-core' ); ?></h3>

				<?php if ( $wa_link ) : ?>
					<a class="oc-vp__channel" href="<?php echo esc_url( $wa_link ); ?>" target="_blank" rel="noopener noreferrer" data-oc-track="whatsapp" data-vendor="<?php echo (int) $id; ?>">
						<span class="oc-vp__channel-icon" style="background:#25D366">💬</span>
						<span class="oc-vp__channel-info">
							<strong><?php esc_html_e( 'WhatsApp', 'owambe-connect-core' ); ?></strong>
							<small><?php echo esc_html( $whatsapp ); ?></small>
						</span>
						<span class="oc-vp__channel-arrow" aria-hidden="true">→</span>
					</a>
				<?php endif; ?>

				<?php if ( $public_email && is_email( $public_email ) ) : ?>
					<a class="oc-vp__channel" href="mailto:<?php echo esc_attr( $public_email ); ?>" data-oc-track="email" data-vendor="<?php echo (int) $id; ?>">
						<span class="oc-vp__channel-icon" style="background:#A8893D">✉</span>
						<span class="oc-vp__channel-info">
							<strong><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?></strong>
							<small><?php echo esc_html( $public_email ); ?></small>
						</span>
						<span class="oc-vp__channel-arrow" aria-hidden="true">→</span>
					</a>
				<?php endif; ?>

				<?php if ( $ig_link ) : ?>
					<a class="oc-vp__channel" href="<?php echo esc_url( $ig_link ); ?>" target="_blank" rel="noopener noreferrer" data-oc-track="instagram" data-vendor="<?php echo (int) $id; ?>">
						<span class="oc-vp__channel-icon" style="background:linear-gradient(45deg,#F58529,#DD2A7B,#8134AF)">IG</span>
						<span class="oc-vp__channel-info">
							<strong><?php esc_html_e( 'Instagram', 'owambe-connect-core' ); ?></strong>
							<small>@<?php echo esc_html( $instagram ); ?></small>
						</span>
						<span class="oc-vp__channel-arrow" aria-hidden="true">→</span>
					</a>
				<?php endif; ?>

				<?php if ( $fb_link ) : ?>
					<a class="oc-vp__channel" href="<?php echo esc_url( $fb_link ); ?>" target="_blank" rel="noopener noreferrer" data-oc-track="facebook" data-vendor="<?php echo (int) $id; ?>">
						<span class="oc-vp__channel-icon" style="background:#1877F2">f</span>
						<span class="oc-vp__channel-info">
							<strong><?php esc_html_e( 'Facebook', 'owambe-connect-core' ); ?></strong>
							<small><?php esc_html_e( 'Visit page', 'owambe-connect-core' ); ?></small>
						</span>
						<span class="oc-vp__channel-arrow" aria-hidden="true">→</span>
					</a>
				<?php endif; ?>

				<?php if ( $website ) : ?>
					<a class="oc-vp__channel" href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener noreferrer" data-oc-track="website" data-vendor="<?php echo (int) $id; ?>">
						<span class="oc-vp__channel-icon" style="background:var(--oc-burgundy,#6E0F2C)">🌐</span>
						<span class="oc-vp__channel-info">
							<strong><?php esc_html_e( 'Website', 'owambe-connect-core' ); ?></strong>
							<small><?php echo esc_html( $website_host ?: $website ); ?></small>
						</span>
						<span class="oc-vp__channel-arrow" aria-hidden="true">→</span>
					</a>
				<?php endif; ?>

				<?php if ( ! $wa_link && ! $ig_link && ! $fb_link && ! $website && ! ( $public_email && is_email( $public_email ) ) ) : ?>
					<p class="oc-vp__no-contact"><?php esc_html_e( 'This vendor hasn\'t added contact details yet.', 'owambe-connect-core' ); ?></p>
				<?php endif; ?>
			</div>

			<?php
			// Build the Quick info rows in one place. Each row is icon + label +
			// value, with the icon picked from a small line-icon set so the
			// sidebar feels like a structured fact-sheet, not a paragraph dump.
			$quick_rows = [];

			if ( $country_label ) {
				$quick_rows[] = [
					'icon'  => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>',
					'label' => __( 'Country', 'owambe-connect-core' ),
					'value' => $country_label,
				];
			}

			if ( $areas ) {
				$quick_rows[] = [
					'icon'  => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>',
					'label' => __( 'Areas covered', 'owambe-connect-core' ),
					'value' => implode( ', ', $areas ),
				];
			}

			if ( $regions ) {
				$quick_rows[] = [
					'icon'  => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>',
					'label' => __( 'Regions covered', 'owambe-connect-core' ),
					'value' => implode( ', ', $regions ),
				];
			}

			if ( ! $areas && ! $regions && $location ) {
				$quick_rows[] = [
					'icon'  => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>',
					'label' => __( 'Service area', 'owambe-connect-core' ),
					'value' => $location,
				];
			}

			if ( $cultural ) {
				$cultural_labels = array_map( function ( $k ) use ( $cultural_options ) {
					return $cultural_options[ $k ] ?? $k;
				}, $cultural );
				$quick_rows[] = [
					'icon'  => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.4 5.6L20 10l-5.6 2.4L12 18l-2.4-5.6L4 10l5.6-2.4L12 2z"/></svg>',
					'label' => __( 'Cultural specialties', 'owambe-connect-core' ),
					'value' => implode( ', ', $cultural_labels ),
				];
			}

			if ( in_array( $reg_biz, [ 'yes', 'no' ], true ) ) {
				$quick_rows[] = [
					'icon'  => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>',
					'label' => __( 'Registered business', 'owambe-connect-core' ),
					'value' => 'yes' === $reg_biz ? __( 'Yes', 'owambe-connect-core' ) : __( 'No', 'owambe-connect-core' ),
				];
			}

			if ( 'yes' === $nigerian ) {
				$quick_rows[] = [
					'icon'  => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>',
					'label' => __( 'Nigerian events', 'owambe-connect-core' ),
					'value' => __( 'Specialist', 'owambe-connect-core' ),
				];
			}

			if ( $price_lbl ) {
				$quick_rows[] = [
					'icon'  => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 7c0-2.21-2.69-4-6-4S6 4.79 6 7v4H4v3h2v3c0 2.21 2.69 4 6 4s6-1.79 6-4"/><line x1="6" y1="14" x2="14" y2="14"/></svg>',
					'label' => __( 'Price tier', 'owambe-connect-core' ),
					'value' => $price_lbl,
				];
			}

			if ( ! empty( $cats ) ) {
				$quick_rows[] = [
					'icon'  => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
					'label' => __( 'Categories', 'owambe-connect-core' ),
					'value' => implode( ', ', wp_list_pluck( $cats, 'name' ) ),
				];
			}

			$quick_rows[] = [
				'icon'  => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8"  y1="2" x2="8"  y2="6"/><line x1="3"  y1="10" x2="21" y2="10"/></svg>',
				'label' => __( 'Member since', 'owambe-connect-core' ),
				'value' => date_i18n( 'F Y', $listed_since ),
			];
			?>
			<div class="oc-vp__card oc-vp__card--quick">
				<h3 class="oc-vp__card-title"><?php esc_html_e( 'Quick info', 'owambe-connect-core' ); ?></h3>
				<ul class="oc-vp__facts">
					<?php foreach ( $quick_rows as $row ) : ?>
						<li class="oc-vp__fact">
							<span class="oc-vp__fact-icon" aria-hidden="true"><?php echo $row['icon']; // phpcs:ignore — trusted inline SVG ?></span>
							<span class="oc-vp__fact-text">
								<span class="oc-vp__fact-label"><?php echo esc_html( $row['label'] ); ?></span>
								<span class="oc-vp__fact-value"><?php echo esc_html( $row['value'] ); ?></span>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<a class="oc-vp__report" href="<?php echo esc_url( oc_page_url( 'contact' ) . '?subject=' . rawurlencode( 'Report listing: ' . $title ) ); ?>">
				<span class="oc-vp__report-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
				</span>
				<span class="oc-vp__report-text">
					<strong><?php esc_html_e( 'Report this vendor', 'owambe-connect-core' ); ?></strong>
					<small><?php esc_html_e( 'Something off about this listing? Let us know.', 'owambe-connect-core' ); ?></small>
				</span>
				<span class="oc-vp__report-arrow" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"/></svg>
				</span>
			</a>
		</aside>
	</div>

	<?php if ( $similar ) : ?>
		<section class="oc-vp__similar">
			<div class="oc-container">
				<header class="oc-vp__similar-head">
					<h2><?php
						/* translators: %s: category name */
						printf( esc_html__( 'More %s', 'owambe-connect-core' ), $primary_cat ? esc_html( $primary_cat->name ) : esc_html__( 'vendors', 'owambe-connect-core' ) );
					?></h2>
					<a class="oc-vp__similar-all" href="<?php echo esc_url( $primary_cat ? add_query_arg( 'cat', $primary_cat->slug, $dir_url ) : $dir_url ); ?>"><?php esc_html_e( 'See all', 'owambe-connect-core' ); ?> →</a>
				</header>
				<div class="oc-vp__similar-grid">
					<?php foreach ( $similar as $sp ) :
						$s_logo  = (int) get_post_meta( $sp->ID, '_oc_logo_id', true );
						$s_loc   = (string) get_post_meta( $sp->ID, '_oc_location', true );
						$s_price = (string) get_post_meta( $sp->ID, '_oc_price_range', true );
						$s_url   = $s_logo ? wp_get_attachment_image_url( $s_logo, 'medium' ) : '';
						?>
						<a class="oc-vp__similar-card" href="<?php echo esc_url( get_permalink( $sp ) ); ?>">
							<div class="oc-vp__similar-img" <?php if ( $s_url ) printf( 'style="background-image:url(%s)"', esc_url( $s_url ) ); ?>>
								<?php if ( ! $s_url ) : ?>
									<span><?php echo esc_html( function_exists( 'mb_substr' ) ? mb_substr( $sp->post_title, 0, 1 ) : substr( $sp->post_title, 0, 1 ) ); ?></span>
								<?php endif; ?>
							</div>
							<div class="oc-vp__similar-body">
								<strong><?php echo esc_html( $sp->post_title ); ?></strong>
								<small><?php
									$bits = array_filter( [ $s_loc, ( $s_price && isset( $prices[ $s_price ] ) ) ? $prices[ $s_price ] : '' ] );
									echo esc_html( implode( ' · ', $bits ) );
								?></small>
							</div>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<script type="application/ld+json"><?php echo wp_json_encode( $json_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>

	<script>
	(function () {
		var root = document.getElementById('oc-vendor-<?php echo (int) $id; ?>');
		if (!root) return;

		// Share dropdown
		var shareWrap = root.querySelector('[data-oc-share-wrap]');
		if (shareWrap) {
			var shareBtn  = shareWrap.querySelector('[data-oc-share]');
			var shareMenu = shareWrap.querySelector('.oc-vp__share-menu');
			var copyBtn   = shareWrap.querySelector('[data-oc-share-copy]');
			var copyLabel = shareWrap.querySelector('[data-oc-copy-label]');
			var originalCopyText = copyLabel ? copyLabel.textContent : '';

			var closeMenu = function () {
				shareMenu.hidden = true;
				shareBtn.setAttribute('aria-expanded', 'false');
				shareWrap.classList.remove('is-open');
			};
			var openMenu = function () {
				shareMenu.hidden = false;
				shareBtn.setAttribute('aria-expanded', 'true');
				shareWrap.classList.add('is-open');
			};

			shareBtn.addEventListener('click', function (e) {
				e.stopPropagation();
				if (shareMenu.hidden) { openMenu(); } else { closeMenu(); }
			});

			document.addEventListener('click', function (e) {
				if (!shareWrap.contains(e.target)) closeMenu();
			});
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') closeMenu();
			});

			// Close on scroll — prevents a stuck-open menu after the trigger has scrolled off
			var lastScrollY = window.scrollY;
			window.addEventListener('scroll', function () {
				if (shareMenu.hidden) return;
				if (Math.abs(window.scrollY - lastScrollY) > 40) {
					closeMenu();
				}
				lastScrollY = window.scrollY;
			}, { passive: true });

			if (copyBtn) {
				copyBtn.addEventListener('click', function () {
					var url = copyBtn.getAttribute('data-url') || window.location.href;
					var done = function () {
						if (copyLabel) copyLabel.textContent = <?php echo wp_json_encode( __( '✓ Copied!', 'owambe-connect-core' ) ); ?>;
						setTimeout(function () {
							if (copyLabel) copyLabel.textContent = originalCopyText;
							closeMenu();
						}, 1200);
					};
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(url).then(done).catch(function () {
							// Fallback for insecure context
							var ta = document.createElement('textarea');
							ta.value = url;
							ta.style.position = 'fixed';
							ta.style.opacity = '0';
							document.body.appendChild(ta);
							ta.select();
							try { document.execCommand('copy'); } catch (err) {}
							document.body.removeChild(ta);
							done();
						});
					} else {
						var ta = document.createElement('textarea');
						ta.value = url;
						ta.style.position = 'fixed';
						ta.style.opacity = '0';
						document.body.appendChild(ta);
						ta.select();
						try { document.execCommand('copy'); } catch (err) {}
						document.body.removeChild(ta);
						done();
					}
				});
			}
		}

		// Lightbox
		var gallery = root.querySelector('[data-oc-lightbox]');
		if (gallery) {
			var items = Array.prototype.slice.call(gallery.querySelectorAll('.oc-vp__gallery-item'));
			if (items.length) {
				var box = document.createElement('div');
				box.className = 'oc-vp__lb';
				box.innerHTML =
					'<button class="oc-vp__lb-close" aria-label="Close">&times;</button>' +
					'<button class="oc-vp__lb-prev" aria-label="Previous">‹</button>' +
					'<button class="oc-vp__lb-next" aria-label="Next">›</button>' +
					'<img class="oc-vp__lb-img" alt=""/>' +
					'<div class="oc-vp__lb-caption"></div>';
				document.body.appendChild(box);

				var img    = box.querySelector('.oc-vp__lb-img');
				var cap    = box.querySelector('.oc-vp__lb-caption');
				var idx    = 0;

				function show(i) {
					idx = (i + items.length) % items.length;
					var a = items[idx];
					img.src = a.getAttribute('href');
					var im = a.querySelector('img');
					img.alt = im ? im.getAttribute('alt') || '' : '';
					cap.textContent = (idx + 1) + ' / ' + items.length;
					box.classList.add('is-open');
					document.body.style.overflow = 'hidden';
				}
				function close() {
					box.classList.remove('is-open');
					document.body.style.overflow = '';
				}

				items.forEach(function (a, i) {
					a.addEventListener('click', function (e) { e.preventDefault(); show(i); });
				});
				box.querySelector('.oc-vp__lb-close').addEventListener('click', close);
				box.querySelector('.oc-vp__lb-prev').addEventListener('click', function () { show(idx - 1); });
				box.querySelector('.oc-vp__lb-next').addEventListener('click', function () { show(idx + 1); });
				box.addEventListener('click', function (e) { if (e.target === box) close(); });
				document.addEventListener('keydown', function (e) {
					if (!box.classList.contains('is-open')) return;
					if (e.key === 'Escape')      close();
					if (e.key === 'ArrowLeft')   show(idx - 1);
					if (e.key === 'ArrowRight')  show(idx + 1);
				});
			}
		}
	})();
	</script>

</article>

