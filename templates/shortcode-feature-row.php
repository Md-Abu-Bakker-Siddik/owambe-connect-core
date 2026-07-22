<?php
/**
 * Feature Row template — image + text 2-column section.
 *
 * Designed to drop into any page (Elementor or pure-shortcode). The image
 * side flips with a single class, the body is plain HTML (passed via
 * shortcode content so authors can keep <strong> / <a> / <em> markup),
 * and the section background is left transparent so Elementor's Section
 * Background controls (or the widget's own Section Background) can paint
 * underneath.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$eyebrow        = $eyebrow        ?? '';
$heading        = $heading        ?? '';
$body           = $body           ?? '';
$cta_text       = $cta_text       ?? '';
$cta_url        = $cta_url        ?? '';
$image          = $image          ?? '';
$image_position = ( ( $image_position ?? 'left' ) === 'right' ) ? 'right' : 'left';
?>
<section class="oc-section oc-feature-row oc-feature-row--image-<?php echo esc_attr( $image_position ); ?>">
	<div class="oc-container oc-feature-row__inner">

		<?php if ( $image ) : ?>
			<div class="oc-feature-row__media">
				<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $heading ?: '' ); ?>" loading="lazy"/>
			</div>
		<?php endif; ?>

		<div class="oc-feature-row__content">
			<?php if ( $eyebrow ) : ?>
				<p class="oc-feature-row__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<?php if ( $heading ) : ?>
				<h2 class="oc-feature-row__title"><?php echo esc_html( $heading ); ?></h2>
			<?php endif; ?>
			<?php if ( $body ) : ?>
				<div class="oc-feature-row__body"><?php echo wp_kses_post( wpautop( $body ) ); ?></div>
			<?php endif; ?>
			<?php if ( $cta_text ) : ?>
				<a class="oc-feature-row__cta" href="<?php echo esc_url( $cta_url ?: '#' ); ?>">
					<?php echo esc_html( $cta_text ); ?>
					<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
				</a>
			<?php endif; ?>
		</div>
	</div>
</section>
