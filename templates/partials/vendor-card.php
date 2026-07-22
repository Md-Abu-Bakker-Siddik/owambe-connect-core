<?php
/**
 * Vendor card partial.
 *
 * Stretched-link pattern (Bootstrap-style): the visible "View profile"
 * link is the only anchor on the card, and its ::after pseudo-element
 * absolutely covers the whole card so clicks anywhere navigate. That
 * keeps one — and only one — anchor in the DOM (so screen readers
 * announce "View Red Artistry, link" once, not twice) and means no
 * empty overlay anchor for parent-theme CSS to fight with.
 *
 * @package OwambeConnect
 * @var int $post_id
 */
defined( 'ABSPATH' ) || exit;

$id        = (int) $post_id;
$title     = get_the_title( $id );
$permalink = get_permalink( $id );
$location  = get_post_meta( $id, '_oc_location',    true );
$price     = get_post_meta( $id, '_oc_price_range', true );
$logo_id    = (int) get_post_meta( $id, '_oc_logo_id',   true );
$banner_id  = (int) get_post_meta( $id, '_oc_banner_id', true );
// The card image is the vendor's Display picture (banner) only — no gallery-pick
// or logo fallback. With no banner, oc_image_or_placeholder() shows the placeholder.
$card_image = $banner_id;
$prices     = oc_price_range_options();
$cats       = wp_get_post_terms( $id, OC_TAX );
$bio        = wp_trim_words( wp_strip_all_tags( get_post_meta( $id, '_oc_bio', true ) ), 18 );
$verified   = function_exists( 'oc_verified_badge_html' ) ? oc_verified_badge_html( $id ) : '';
$founding   = function_exists( 'oc_founding_badge_html' ) ? oc_founding_badge_html( $id ) : '';

/* translators: %s: vendor business name */
$cta_label = sprintf( __( 'View %s', 'owambe-connect-core' ), $title );
?>
<article class="oc-card oc-card--clickable">

	<div class="oc-card__media">
		<?php echo oc_image_or_placeholder( $card_image, 'oc-card', $title ); ?>
		<?php if ( ! empty( $cats ) ) : ?>
			<span class="oc-card__tag"><?php echo esc_html( $cats[0]->name ); ?></span>
		<?php endif; ?>
		<?php
		// Phase 2 — save-to-list heart. position:relative + z-index in CSS keeps
		// it clickable above the card's stretched-link ::after overlay.
		if ( is_user_logged_in() ) :
			$card_saved = function_exists( 'oc_is_vendor_saved' ) && oc_is_vendor_saved( $id );
			?>
			<button type="button" class="oc-save-btn<?php echo $card_saved ? ' is-saved' : ''; ?>" data-oc-save="<?php echo (int) $id; ?>" aria-pressed="<?php echo $card_saved ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Save this vendor to your list', 'owambe-connect-core' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
			</button>
		<?php endif; ?>
	</div>

	<div class="oc-card__body">
		<header class="oc-card__head">
			<?php if ( $logo_id ) : ?>
				<span class="oc-card__logo"><?php echo wp_get_attachment_image( $logo_id, [ 56, 56 ], false, [ 'alt' => $title ] ); ?></span>
			<?php endif; ?>
			<h3 class="oc-card__title">
				<?php echo esc_html( $title ); ?>
				<?php echo $verified; // phpcs:ignore — trusted inline SVG ?>
				<?php echo $founding; // phpcs:ignore — trusted inline SVG ?>
			</h3>
		</header>

		<?php if ( $location ) : ?>
			<p class="oc-card__meta">
				<span class="oc-card__meta-icon" aria-hidden="true">📍</span>
				<?php echo esc_html( $location ); ?>
				<?php if ( $price && isset( $prices[ $price ] ) ) : ?>
					<span class="oc-card__sep">·</span> <?php echo esc_html( $prices[ $price ] ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php
		// Phase 2 — rating line (only when approved reviews exist).
		$card_rating_count = (int) get_post_meta( $id, '_oc_rating_count', true );
		$card_rating_avg   = (float) get_post_meta( $id, '_oc_rating_avg', true );
		if ( $card_rating_count > 0 && class_exists( 'OC_Reviews' ) ) : ?>
			<p class="oc-card__rating"><?php echo wp_kses_post( OC_Reviews::stars_html( $card_rating_avg, $card_rating_count ) ); ?></p>
		<?php endif; ?>

		<?php if ( $bio ) : ?>
			<p class="oc-card__bio"><?php echo esc_html( $bio ); ?></p>
		<?php endif; ?>

		<a class="oc-card__cta" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $cta_label ); ?>">
			<?php esc_html_e( 'View profile', 'owambe-connect-core' ); ?>
			<svg class="oc-card__cta-arrow" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
		</a>
	</div>
</article>
