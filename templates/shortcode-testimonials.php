<?php
defined( 'ABSPATH' ) || exit;

$heading    = ! empty( $heading )    ? $heading    : __( 'What our community says', 'owambe-connect-core' );
$subheading = ! empty( $subheading ) ? $subheading : '';
$items_raw  = ! empty( $items )      ? $items      : '';
$items      = [];
if ( $items_raw ) {
	$decoded = json_decode( $items_raw, true );
	if ( is_array( $decoded ) ) { $items = $decoded; }
}
if ( empty( $items ) ) { return; }
?>
<section class="oc-section oc-testimonials">
	<div class="oc-container">
		<div class="oc-section__head">
			<h2 class="oc-section__title"><?php echo esc_html( $heading ); ?></h2>
			<?php if ( $subheading ) : ?><p class="oc-section__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
		</div>
		<div class="oc-testimonials__grid">
			<?php foreach ( $items as $t ) :
				$initial = mb_substr( $t['name'] ?? '?', 0, 1 );
				?>
				<figure class="oc-testimonial">
					<svg class="oc-testimonial__mark" viewBox="0 0 24 24" width="32" height="32" fill="currentColor" aria-hidden="true"><path d="M9.983 3v6.926H7.06c-.13 0-.241.06-.314.151L4.43 12.66a.4.4 0 00-.085.246v.426c0 .222.18.4.4.4h5.222a.4.4 0 00.4-.4V3a.4.4 0 00-.4-.4H10.4a.417.417 0 00-.417.4zm9.034 0v6.926h-2.924c-.13 0-.24.06-.313.151l-2.317 2.583a.4.4 0 00-.085.246v.426c0 .222.18.4.4.4h5.222a.4.4 0 00.4-.4V3a.4.4 0 00-.4-.4h-.566a.417.417 0 00-.417.4z"/></svg>
					<blockquote class="oc-testimonial__quote"><?php echo esc_html( $t['quote'] ?? '' ); ?></blockquote>
					<figcaption class="oc-testimonial__person">
						<?php if ( ! empty( $t['avatar'] ) ) : ?>
							<img class="oc-testimonial__avatar" src="<?php echo esc_url( $t['avatar'] ); ?>" alt="<?php echo esc_attr( $t['name'] ?? '' ); ?>" loading="lazy" />
						<?php else : ?>
							<span class="oc-testimonial__avatar oc-testimonial__avatar--initial"><?php echo esc_html( $initial ); ?></span>
						<?php endif; ?>
						<span class="oc-testimonial__meta">
							<strong class="oc-testimonial__name"><?php echo esc_html( $t['name'] ?? '' ); ?></strong>
							<?php if ( ! empty( $t['role'] ) ) : ?><span class="oc-testimonial__role"><?php echo esc_html( $t['role'] ); ?></span><?php endif; ?>
						</span>
					</figcaption>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
</section>
