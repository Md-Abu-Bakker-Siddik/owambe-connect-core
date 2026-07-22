<?php
/**
 * Review submission form partial (vendor profile → #reviews section).
 *
 * Branches by viewer: logged-out visitors get a sign-in link that round-trips
 * back here; logged-in non-clients get a short note; clients who already
 * reviewed get a thanks note; everyone else gets the star + text form.
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

// Round-trip feedback from the submit handler (values arrive rawurlencoded).
$err    = isset( $_GET['oc_error'] )  ? sanitize_text_field( wp_unslash( $_GET['oc_error'] ) )  : '';
$notice = isset( $_GET['oc_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['oc_notice'] ) ) : '';

// Preserved input from a failed validation trip — never wipe what was typed.
$prefill_rating = isset( $_GET['oc_rating'] )      ? absint( $_GET['oc_rating'] ) : 0;
$prefill_text   = isset( $_GET['oc_review_text'] ) ? sanitize_textarea_field( wp_unslash( $_GET['oc_review_text'] ) ) : '';

$viewer    = wp_get_current_user();
$is_client = is_user_logged_in() && in_array( OC_CLIENT_ROLE, (array) $viewer->roles, true );
$can_post  = $is_client || current_user_can( 'manage_options' );
?>
<div class="oc-review-form-wrap">

	<?php if ( $err ) : ?>
		<div class="oc-alert oc-alert--error" role="alert"><?php echo esc_html( $err ); ?></div>
	<?php endif; ?>
	<?php if ( $notice ) : ?>
		<div class="oc-alert oc-alert--success" role="status"><?php echo esc_html( $notice ); ?></div>
	<?php endif; ?>

	<?php if ( ! is_user_logged_in() ) : ?>

		<?php
		$signin_url = add_query_arg(
			'redirect_to',
			rawurlencode( get_permalink( $vendor_id ) . '#reviews' ),
			oc_page_url( 'client-login' )
		);
		?>
		<p class="oc-review-form__signin">
			<a href="<?php echo esc_url( $signin_url ); ?>"><?php esc_html_e( 'Sign in to leave a review', 'owambe-connect-core' ); ?></a>
		</p>

	<?php elseif ( ! $can_post ) : ?>

		<p class="oc-review-form__note"><?php esc_html_e( 'Reviews are for client accounts.', 'owambe-connect-core' ); ?></p>

	<?php elseif ( OC_Reviews::user_has_reviewed( $viewer->ID, $vendor_id ) ) : ?>

		<p class="oc-review-form__note"><?php esc_html_e( 'Thanks — you\'ve already reviewed this vendor.', 'owambe-connect-core' ); ?></p>

	<?php else : ?>

		<form class="oc-review-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oc_submit_review" />
			<?php wp_nonce_field( 'oc_submit_review', 'oc_review_nonce' ); ?>
			<input type="hidden" name="vendor_id" value="<?php echo esc_attr( $vendor_id ); ?>" />

			<div style="display:none" aria-hidden="true">
				<label>Leave this empty<input type="text" name="oc_hp" tabindex="-1" autocomplete="off" /></label>
			</div>

			<fieldset class="oc-review-form__rating">
				<legend><?php esc_html_e( 'Your rating', 'owambe-connect-core' ); ?></legend>
				<div class="oc-review-form__stars">
					<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
						<input
							type="radio"
							id="oc-review-star-<?php echo (int) $i; ?>"
							name="rating"
							value="<?php echo (int) $i; ?>"
							<?php checked( $prefill_rating, $i ); ?>
							required
						/>
						<label for="oc-review-star-<?php echo (int) $i; ?>" title="<?php
							/* translators: %d: number of stars */
							echo esc_attr( sprintf( _n( '%d star', '%d stars', $i, 'owambe-connect-core' ), $i ) );
						?>"><span aria-hidden="true">&#9733;</span><span class="screen-reader-text"><?php
							/* translators: %d: number of stars */
							echo esc_html( sprintf( _n( '%d star', '%d stars', $i, 'owambe-connect-core' ), $i ) );
						?></span></label>
					<?php endfor; ?>
				</div>
			</fieldset>

			<div class="oc-field">
				<label for="oc-review-text"><?php esc_html_e( 'Your review', 'owambe-connect-core' ); ?></label>
				<textarea
					id="oc-review-text"
					name="review_text"
					rows="5"
					minlength="20"
					maxlength="2000"
					required
					placeholder="<?php esc_attr_e( 'Share your experience with this vendor — what they did, how it went (at least 20 characters)…', 'owambe-connect-core' ); ?>"
				><?php echo esc_textarea( $prefill_text ); ?></textarea>
			</div>

			<?php oc_recaptcha_field( 'review' ); ?>

			<button type="submit" class="oc-btn oc-btn--primary"><?php esc_html_e( 'Submit review', 'owambe-connect-core' ); ?></button>
		</form>

	<?php endif; ?>

</div>
