<?php
/**
 * Published reviews list partial (vendor profile → #reviews section).
 *
 * Shows the aggregate stars + the 10 newest approved reviews; pending and
 * rejected reviews never render here.
 *
 * Expected var (via oc_get_template): $vendor_id.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$vendor_id = isset( $vendor_id ) ? (int) $vendor_id : 0;
if ( ! $vendor_id ) {
	return;
}

$count = OC_Reviews::count_for_vendor( $vendor_id );
?>
<div class="oc-reviews" id="reviews">

	<?php if ( $count > 0 ) : ?>

		<header class="oc-reviews__head">
			<h2 class="oc-reviews__title"><?php esc_html_e( 'Reviews', 'owambe-connect-core' ); ?></h2>
			<?php echo OC_Reviews::stars_html( (float) get_post_meta( $vendor_id, '_oc_rating_avg', true ), $count ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in stars_html(). ?>
		</header>

		<?php foreach ( OC_Reviews::for_vendor( $vendor_id, 10 ) as $review ) : ?>
			<?php $rating = (int) get_post_meta( $review->ID, '_oc_review_rating', true ); ?>
			<article class="oc-review">
				<div class="oc-review__head">
					<?php echo OC_Reviews::stars_html( $rating ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in stars_html(). ?>
					<strong><?php echo esc_html( get_the_author_meta( 'display_name', $review->post_author ) ); ?></strong>
					<span class="oc-review__meta"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $review->post_date ) ) ); ?></span>
				</div>
				<div class="oc-review__text"><?php echo wpautop( esc_html( $review->post_content ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- esc_html'd before wpautop. ?></div>
			</article>
		<?php endforeach; ?>

	<?php else : ?>

		<p class="oc-reviews__empty"><?php esc_html_e( 'No reviews yet — be the first to review this vendor.', 'owambe-connect-core' ); ?></p>

	<?php endif; ?>

</div>
