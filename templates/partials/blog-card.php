<?php
/**
 * Blog carousel card.
 *
 * @package OwambeConnect
 * @var int $post_id
 */

defined( 'ABSPATH' ) || exit;

$id = isset( $post_id ) ? absint( $post_id ) : get_the_ID();

if ( ! $id || 'post' !== get_post_type( $id ) ) {
	return;
}

$title     = get_the_title( $id );
$permalink = get_permalink( $id );
$excerpt   = get_the_excerpt( $id );

if ( ! $excerpt ) {
	$excerpt = get_post_field( 'post_content', $id );
}

$excerpt    = wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $excerpt ) ), 24, '…' );
$date_iso   = get_the_date( DATE_W3C, $id );
$date_label = get_the_date( '', $id );
$link_label = sprintf(
	/* translators: %s: blog post title. */
	__( 'Read %s', 'owambe-connect-core' ),
	$title
);
?>
<article class="oc-blog-card">
	<div class="oc-blog-card__media">
		<?php if ( has_post_thumbnail( $id ) ) : ?>
			<?php
			echo get_the_post_thumbnail(
				$id,
				'oc-card',
				[
					'class'    => 'oc-blog-card__image',
					'loading'  => 'lazy',
					'decoding' => 'async',
					'alt'      => $title,
				]
			);
			?>
		<?php else : ?>
			<div class="oc-blog-card__placeholder" aria-hidden="true">
				<svg viewBox="0 0 64 64" width="52" height="52" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
					<rect x="10" y="12" width="44" height="40" rx="5"></rect>
					<path d="M18 23h28M18 31h20M18 39h24"></path>
				</svg>
			</div>
		<?php endif; ?>
	</div>

	<div class="oc-blog-card__body">
		<?php if ( $date_label ) : ?>
			<time class="oc-blog-card__date" datetime="<?php echo esc_attr( $date_iso ); ?>">
				<span class="screen-reader-text"><?php esc_html_e( 'Published on ', 'owambe-connect-core' ); ?></span>
				<?php echo esc_html( $date_label ); ?>
			</time>
		<?php endif; ?>

		<h3 class="oc-blog-card__title">
			<a class="oc-blog-card__link" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $link_label ); ?>">
				<?php echo esc_html( $title ); ?>
			</a>
		</h3>

		<?php if ( $excerpt ) : ?>
			<p class="oc-blog-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
		<?php endif; ?>

		<span class="oc-blog-card__cta" aria-hidden="true">
			<?php esc_html_e( 'Read article', 'owambe-connect-core' ); ?>
			<span>&rarr;</span>
		</span>
	</div>
</article>
