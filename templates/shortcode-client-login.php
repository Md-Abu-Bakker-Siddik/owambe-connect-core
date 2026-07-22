<?php
/**
 * Client login shortcode template — Google-only sign-in card.
 *
 * Clients authenticate exclusively via Google (OC_Google_Auth); there is no
 * email/password form here by design. Vendors are pointed to their own login
 * page below the divider. Honours ?redirect_to= so protected pages (e.g.
 * /client-dashboard/) round-trip back after sign-in.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$err    = isset( $_GET['oc_error'] )  ? wp_unslash( $_GET['oc_error'] )  : '';
$notice = isset( $_GET['oc_notice'] ) ? wp_unslash( $_GET['oc_notice'] ) : '';

$heading    = ! empty( $heading )    ? $heading    : __( 'Sign in', 'owambe-connect-core' );
$subheading = ! empty( $subheading ) ? $subheading : __( 'Use your Google account to save vendors and pick up planning where you left off.', 'owambe-connect-core' );

// Honor ?redirect_to=<url> from the URL (set when we bounce a logged-out user
// here from a protected page like /client-dashboard/) — wp_validate_redirect
// keeps it on-site.
$redirect_to = ! empty( $redirect_url ) ? $redirect_url : '';
if ( '' === $redirect_to && ! empty( $_GET['redirect_to'] ) ) {
	$redirect_to = wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ), '' );
}

// Defensive: OC_Google_Auth is loaded by the plugin bootstrap, but the button
// degrades to a friendly notice if the class is missing or unconfigured.
$google_button = '';
if ( class_exists( 'OC_Google_Auth' ) ) {
	$google_button = (string) OC_Google_Auth::button_html( $redirect_to );
}
?>
<section class="oc-section oc-auth oc-auth--client">
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

				<?php if ( $google_button ) : ?>
					<div class="oc-google-signin">
						<?php echo $google_button; // phpcs:ignore WordPress.Security.EscapeOutput -- trusted markup built by OC_Google_Auth::button_html(). ?>
					</div>
				<?php else : ?>
					<div class="oc-alert oc-alert--error" role="alert">
						<?php esc_html_e( 'Sign-in is temporarily unavailable. Please try again shortly.', 'owambe-connect-core' ); ?>
					</div>
				<?php endif; ?>

				<div class="oc-auth__divider" aria-hidden="true"><span><?php esc_html_e( 'or', 'owambe-connect-core' ); ?></span></div>

				<p class="oc-help oc-help--center">
					<?php esc_html_e( 'Are you a vendor?', 'owambe-connect-core' ); ?>
					<a href="<?php echo esc_url( oc_page_url( 'vendor-login' ) ); ?>"><?php esc_html_e( 'Sign in at the vendor login page', 'owambe-connect-core' ); ?></a>
				</p>
			</div>

		</div>
	</div>
</section>
