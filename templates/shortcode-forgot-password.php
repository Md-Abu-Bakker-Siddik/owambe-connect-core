<?php
/**
 * Branded "Forgot your password?" form. Visitor enters their email; the
 * handler in OC_Dashboard::lost_password() generates a reset key and sends
 * a branded email with a link to /reset-password/.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$err    = isset( $_GET['oc_error'] )  ? wp_unslash( $_GET['oc_error'] )  : '';
$notice = isset( $_GET['oc_notice'] ) ? wp_unslash( $_GET['oc_notice'] ) : '';

$heading      = ! empty( $heading )      ? $heading      : __( 'Forgot your password?', 'owambe-connect-core' );
$subheading   = ! empty( $subheading )   ? $subheading   : __( 'Enter your email address and we\'ll send you a link to set a new one.', 'owambe-connect-core' );
$button_text  = ! empty( $button_text )  ? $button_text  : __( 'Send reset link', 'owambe-connect-core' );
?>
<section class="oc-section oc-auth oc-auth--forgot">
	<div class="oc-container oc-auth__container">
		<header class="oc-auth__head">
			<h1 class="oc-auth__title"><?php echo esc_html( $heading ); ?></h1>
			<?php if ( $subheading ) : ?><p class="oc-auth__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
		</header>

		<div class="oc-auth__body oc-auth__body--single">

			<div class="oc-auth__form-wrap">
				<?php if ( $err ) : ?>
					<div class="oc-alert oc-alert--error" role="alert"><?php echo esc_html( $err ); ?></div>
				<?php endif; ?>
				<?php if ( $notice ) : ?>
					<div class="oc-alert oc-alert--success" role="status"><?php echo esc_html( $notice ); ?></div>
				<?php endif; ?>

				<form class="oc-form oc-auth__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( OC_Dashboard::ACTION_LOST_PASSWORD ); ?>" />
					<?php wp_nonce_field( OC_Dashboard::ACTION_LOST_PASSWORD, 'oc_lp_nonce' ); ?>

					<div class="oc-field oc-field--with-icon">
						<label for="oc-lp-email"><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?> <span class="oc-req">*</span></label>
						<div class="oc-field-inner">
							<span class="oc-field__icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
							</span>
							<input id="oc-lp-email" type="email" name="user_login" required autocomplete="email" placeholder="you@example.com"/>
						</div>
					</div>

					<div class="oc-form__actions">
						<button type="submit" class="oc-btn oc-btn-primary oc-btn-lg oc-btn-block"><?php echo esc_html( $button_text ); ?></button>
					</div>

					<p class="oc-help oc-help--center">
						<a class="oc-link" href="<?php echo esc_url( oc_page_url( 'vendor-login' ) ); ?>"><?php esc_html_e( '← Back to login', 'owambe-connect-core' ); ?></a>
					</p>
				</form>
			</div>

		</div>
	</div>
</section>
