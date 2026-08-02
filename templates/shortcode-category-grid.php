<?php
/**
 * Category grid shortcode template.
 *
 * @package OwambeConnect
 * @var int $limit
 */
defined( 'ABSPATH' ) || exit;

$terms = OC_Queries::categories_with_counts();
if ( empty( $terms ) ) return;
// Homepage tiles show top-level categories only (P15) — subcategories live
// in the directory/hero selects; the directory tax_query includes children.
$terms       = array_values( array_filter( $terms, function ( $t ) {
	return 0 === (int) ( $t->depth ?? 0 );
} ) );
if ( empty( $terms ) ) return;
$limit       = isset( $limit ) && $limit > 0 ? (int) $limit : 12;
$terms       = array_slice( $terms, 0, $limit );
$directory   = oc_page_url( 'vendors' );
$heading     = ! empty( $heading )    ? $heading    : __( 'Browse by Category', 'owambe-connect-core' );
$subheading  = ! empty( $subheading ) ? $subheading : __( 'Find the right vendors for every part of your event.', 'owambe-connect-core' );

// Layout: 'scroll' (horizontal carousel — default, client request §6.6) or 'grid'.
// Defaults to the carousel; only an explicit 'grid' falls back to the wrapping grid.
$layout      = ( 'grid' === $layout ) ? 'grid' : 'scroll';

// Card style is decided per term: a category WITH an image always gets the
// full-bleed photo card (image flush to the card edges, name + count below);
// only categories without an image fall back to the compact icon card.
// The widget/shortcode card_style attribute is kept for the section class
// but no longer forces images into the padded icon slot.
$card_style  = ( isset( $card_style ) && 'icons' === $card_style ) ? 'icons' : 'images';

// Monoline SVG glyphs — sit consistently against the page chrome and pick
// up the burgundy/gold accents from the child theme.
$icons_svg = [
	'catering'    => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11h18"/><path d="M5 11a7 7 0 0114 0"/><path d="M2 19h20"/><path d="M5 19l-1-2"/><path d="M19 19l1-2"/></svg>',
	'photography' => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>',
	'videography' => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>',
	'decor'       => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l1.6 4.4L18 8l-4.4 1.6L12 14l-1.6-4.4L6 8l4.4-1.6L12 2z"/><path d="M19 14l.8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8L19 14z"/><path d="M5 16l.8 2.2L8 19l-2.2.8L5 22l-.8-2.2L2 19l2.2-.8L5 16z"/></svg>',
	'dj-music'    => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
	'venues'      => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V9l7-5 7 5v12"/><path d="M10 21v-6h4v6"/></svg>',
	'mua'         => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a4 4 0 014 4v3a4 4 0 11-8 0V6a4 4 0 014-4z"/><path d="M6 22a6 6 0 0112 0"/><path d="M9 12c1 1 5 1 6 0"/></svg>',
	'cakes'       => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v3"/><path d="M3 12V9a2 2 0 012-2h14a2 2 0 012 2v3"/><path d="M3 17V12h18v5"/><path d="M3 21h18"/></svg>',
	'planners'    => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="18" rx="2"/><path d="M9 2v4"/><path d="M15 2v4"/><path d="M4 10h16"/><path d="M9 14h6"/><path d="M9 18h4"/></svg>',
	'attire'      => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M16 2l-4 4-4-4"/><path d="M8 2L3 8v14h18V8l-5-6"/></svg>',
	'transport'   => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17H3v-5l2-6h12l3 6v5h-2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>',
];
$fallback_svg = '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3 6 6 1-4.5 4 1 6L12 16l-5.5 3 1-6L3 9l6-1 3-6z"/></svg>';
$carousel_id = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'oc-category-track-' ) : 'oc-category-track-' . substr( md5( (string) $heading ), 0, 8 );
$heading_id  = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'oc-category-title-' ) : 'oc-category-title-' . substr( md5( (string) $heading ), 0, 8 );
?>
<section class="oc-section oc-categories oc-categories--<?php echo esc_attr( $layout ); ?> oc-categories--<?php echo esc_attr( $card_style ); ?>" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
	<div class="oc-container">
		<div class="oc-section__head">
			<h2 id="<?php echo esc_attr( $heading_id ); ?>" class="oc-section__title"><?php echo esc_html( $heading ); ?></h2>
			<?php if ( $subheading ) : ?><p class="oc-section__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
		</div>

		<?php if ( 'scroll' === $layout ) : ?>
		<div
			class="oc-carousel oc-category-carousel"
			data-oc-carousel
			data-oc-carousel-autoplay="yes"
			data-oc-carousel-interval="3500"
			data-oc-carousel-step="group"
			role="region"
			aria-labelledby="<?php echo esc_attr( $heading_id ); ?>"
			aria-roledescription="<?php esc_attr_e( 'carousel', 'owambe-connect-core' ); ?>"
		>
			<button
				class="oc-carousel__arrow oc-carousel__arrow--prev"
				type="button"
				data-oc-carousel-prev
				aria-controls="<?php echo esc_attr( $carousel_id ); ?>"
				aria-label="<?php esc_attr_e( 'Show previous vendor categories', 'owambe-connect-core' ); ?>"
			>&#8249;</button>
		<?php endif; ?>

		<div
			id="<?php echo esc_attr( $carousel_id ); ?>"
			class="oc-grid oc-grid--categories<?php echo 'scroll' === $layout ? ' oc-category-grid--scroll oc-carousel__track' : ''; ?>"
			<?php if ( 'scroll' === $layout ) : ?>tabindex="0"<?php endif; ?>
		>
			<?php foreach ( $terms as $term ) :
				$url  = add_query_arg( 'cat', $term->slug, $directory );
				$icon = function_exists( 'oc_get_category_icon' ) ? oc_get_category_icon( $term ) : [];
				$count_str = sprintf( _n( '%d vendor', '%d vendors', (int) $term->count, 'owambe-connect-core' ), (int) $term->count );

				if ( ! empty( $icon['image_url'] ) ) : ?>
					<a class="oc-cat-card oc-cat-card--photo" href="<?php echo esc_url( $url ); ?>">
						<div class="oc-cat-card__photo-wrap" aria-hidden="true">
							<img class="oc-cat-card__photo-img" src="<?php echo esc_url( $icon['image_url'] ); ?>" alt=""/>
						</div>
						<div class="oc-cat-card__photo-body">
							<span class="oc-cat-card__name"><?php echo esc_html( $term->name ); ?></span>
							<span class="oc-cat-card__count"><?php echo esc_html( $count_str ); ?></span>
						</div>
					</a>

				<?php else : ?>
					<a class="oc-cat-card" href="<?php echo esc_url( $url ); ?>">
						<span class="oc-cat-card__icon" aria-hidden="true">
							<?php if ( 'emoji_custom' === ( $icon['source'] ?? '' ) ) : ?>
								<span class="oc-cat-card__icon-emoji"><?php echo esc_html( $icon['emoji'] ); ?></span>
							<?php else :
								$svg = $icons_svg[ $term->slug ] ?? $fallback_svg;
								echo $svg; // phpcs:ignore — trusted inline SVG
							endif; ?>
						</span>
						<span class="oc-cat-card__name"><?php echo esc_html( $term->name ); ?></span>
						<span class="oc-cat-card__count"><?php echo esc_html( $count_str ); ?></span>
					</a>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php if ( 'scroll' === $layout ) : ?>
			<button
				class="oc-carousel__arrow oc-carousel__arrow--next"
				type="button"
				data-oc-carousel-next
				aria-controls="<?php echo esc_attr( $carousel_id ); ?>"
				aria-label="<?php esc_attr_e( 'Show more vendor categories', 'owambe-connect-core' ); ?>"
			>&#8250;</button>
			<div class="oc-cat-dots oc-carousel__dots" data-oc-carousel-dots></div>
		</div>
		<?php endif; ?>
	</div>
</section>
