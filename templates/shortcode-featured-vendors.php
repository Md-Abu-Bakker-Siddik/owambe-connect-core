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
$autoplay      = isset( $autoplay ) && 'no' === $autoplay ? 'no' : 'yes';
$interval      = isset( $interval ) ? max( 2500, min( 15000, (int) $interval ) ) : 5000;
$heading_id    = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'oc-featured-heading-' ) : 'oc-featured-heading';
$track_id      = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'oc-featured-track-' ) : 'oc-featured-track';
?>
<section class="oc-section oc-featured oc-home-carousel" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
	<div class="oc-container">
		<div class="oc-section__head">
			<h2 id="<?php echo esc_attr( $heading_id ); ?>" class="oc-section__title"><?php echo esc_html( $heading ); ?></h2>
			<?php if ( $subheading ) : ?><p class="oc-section__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
		</div>
		<div class="oc-carousel" data-oc-carousel data-oc-carousel-autoplay="<?php echo esc_attr( $autoplay ); ?>" data-oc-carousel-interval="<?php echo (int) $interval; ?>" role="region" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" aria-roledescription="<?php esc_attr_e( 'carousel', 'owambe-connect-core' ); ?>">
			<button class="oc-carousel__arrow oc-carousel__arrow--prev" type="button" data-oc-carousel-prev aria-controls="<?php echo esc_attr( $track_id ); ?>" aria-label="<?php esc_attr_e( 'Previous vendors', 'owambe-connect-core' ); ?>">&#8249;</button>
			<div id="<?php echo esc_attr( $track_id ); ?>" class="oc-carousel__track oc-home-carousel__track" tabindex="0">
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				echo oc_get_template( 'partials/vendor-card.php', [ 'post_id' => get_the_ID() ] );
			}
			wp_reset_postdata();
			?>
			</div>
			<button class="oc-carousel__arrow oc-carousel__arrow--next" type="button" data-oc-carousel-next aria-controls="<?php echo esc_attr( $track_id ); ?>" aria-label="<?php esc_attr_e( 'Next vendors', 'owambe-connect-core' ); ?>">&#8250;</button>
		</div>
		<?php if ( $view_all_text ) : ?>
		<div class="oc-section__cta">
			<a class="oc-btn oc-btn-outline" href="<?php echo esc_url( $view_all_url ); ?>"><?php echo esc_html( $view_all_text ); ?> <span aria-hidden="true">&rarr;</span></a>
		</div>
		<?php endif; ?>
	</div>
</section>
