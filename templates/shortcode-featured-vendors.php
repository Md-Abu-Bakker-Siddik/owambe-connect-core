<?php
/**
 * Featured vendors shortcode template.
 *
 * @package OwambeConnect
 * @var WP_Query $query
 */
defined( 'ABSPATH' ) || exit;
if ( ! $query->have_posts() ) return;

$heading       = ! empty( $heading )       ? $heading       : __( 'Featured Vendors', 'owambe-connect-core' );
$subheading    = ! empty( $subheading )    ? $subheading    : __( 'Hand-picked professionals trusted by our community.', 'owambe-connect-core' );
$view_all_text = ! empty( $view_all_text ) ? $view_all_text : __( 'Browse all vendors', 'owambe-connect-core' );
$view_all_url  = ! empty( $view_all_url )  ? $view_all_url  : oc_page_url( 'vendors' );
?>
<section class="oc-section oc-featured">
	<div class="oc-container">
		<div class="oc-section__head">
			<h2 class="oc-section__title"><?php echo esc_html( $heading ); ?></h2>
			<?php if ( $subheading ) : ?><p class="oc-section__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
		</div>
		<div class="oc-carousel" data-oc-carousel>
			<button class="oc-carousel__arrow oc-carousel__arrow--prev" type="button" data-oc-carousel-prev aria-label="<?php esc_attr_e( 'Previous vendors', 'owambe-connect-core' ); ?>">&#8249;</button>
			<div class="oc-carousel__track">
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				echo oc_get_template( 'partials/vendor-card.php', [ 'post_id' => get_the_ID() ] );
			}
			wp_reset_postdata();
			?>
			</div>
			<button class="oc-carousel__arrow oc-carousel__arrow--next" type="button" data-oc-carousel-next aria-label="<?php esc_attr_e( 'Next vendors', 'owambe-connect-core' ); ?>">&#8250;</button>
		</div>
		<?php if ( $view_all_text ) : ?>
		<div class="oc-section__cta">
			<a class="oc-btn oc-btn-outline" href="<?php echo esc_url( $view_all_url ); ?>"><?php echo esc_html( $view_all_text ); ?> →</a>
		</div>
		<?php endif; ?>
	</div>
</section>
