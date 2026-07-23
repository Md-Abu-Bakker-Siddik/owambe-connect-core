<?php
/**
 * Client login/register shortcode template.
 *
 * Clients can sign in with Google (OC_Google_Auth) OR with a native
 * email/password account — the latter added so people without a Google
 * account (e.g. Yahoo/Outlook users) can still register and sign in.
 *
 * Two server-rendered modes, toggled via ?mode= (no JS required):
 *   - login    (default) — email/password sign-in
 *   - register           — username(optional)/email/password sign-up
 *
 * Honours ?redirect_to= so protected pages (e.g. /client-dashboard/) round-trip
 * back after auth. The native forms POST to admin-post.php and are handled by
 * OC_Client::handle_login() / handle_register().
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$err    = isset( $_GET['oc_error'] )  ? wp_unslash( $_GET['oc_error'] )  : '';
$notice = isset( $_GET['oc_notice'] ) ? wp_unslash( $_GET['oc_notice'] ) : '';

// login (default) or register — server-side toggle, so it works without JS.
$mode = ( isset( $_GET['mode'] ) && 'register' === sanitize_key( wp_unslash( $_GET['mode'] ) ) ) ? 'register' : 'login';

$heading    = ! empty( $heading ) ? $heading
	: ( 'register' === $mode ? __( 'Create your account', 'owambe-connect-core' ) : __( 'Sign in', 'owambe-connect-core' ) );
$subheading = ! empty( $subheading ) ? $subheading
	: ( 'register' === $mode
		? __( 'Save vendors you love and keep your event planning in one place.', 'owambe-connect-core' )
		: __( 'Use Google or your email and password to pick up where you left off.', 'owambe-connect-core' ) );

// Honor ?redirect_to=<url> (set when we bounce a logged-out user here from a
// protected page). wp_validate_redirect keeps it on-site.
$redirect_to = ! empty( $redirect_url ) ? $redirect_url : '';
if ( '' === $redirect_to && ! empty( $_GET['redirect_to'] ) ) {
	$redirect_to = wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ), '' );
}

// Mode-toggle links that preserve the redirect round-trip.
$login_base  = oc_page_url( 'client-login' );
$keep        = $redirect_to ? [ 'redirect_to' => rawurlencode( $redirect_to ) ] : [];
$to_register = add_query_arg( array_merge( $keep, [ 'mode' => 'register' ] ), $login_base );
$to_login    = $keep ? add_query_arg( $keep, $login_base ) : $login_base;

// Defensive: OC_Google_Auth is loaded by the plugin bootstrap, but the button
// degrades to a friendly notice if the class is missing or unconfigured.
$google_button = '';
if ( class_exists( 'OC_Google_Auth' ) ) {
	$google_button = (string) OC_Google_Auth::button_html( $redirect_to );
}
$post_url = admin_url( 'admin-post.php' );
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
					<div class="oc-auth__divider" aria-hidden="true"><span><?php esc_html_e( 'or use your email', 'owambe-connect-core' ); ?></span></div>
				<?php endif; ?>

				<?php if ( 'register' === $mode ) : ?>

					<!-- Native registration — for clients without a Google account. -->
					<form class="oc-form oc-auth__form" method="post" action="<?php echo esc_url( $post_url ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( OC_Client::ACTION_REGISTER ); ?>" />
						<?php if ( $redirect_to ) : ?><input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" /><?php endif; ?>
						<?php wp_nonce_field( OC_Client::ACTION_REGISTER, 'oc_client_register_nonce' ); ?>

						<div class="oc-field">
							<label for="oc-cr-name"><?php esc_html_e( 'Your name', 'owambe-connect-core' ); ?></label>
							<input id="oc-cr-name" type="text" name="display_name" autocomplete="name" />
						</div>
						<div class="oc-field">
							<label for="oc-cr-user"><?php esc_html_e( 'Username', 'owambe-connect-core' ); ?> <span class="oc-field__opt">(<?php esc_html_e( 'optional', 'owambe-connect-core' ); ?>)</span></label>
							<input id="oc-cr-user" type="text" name="username" autocomplete="username" />
						</div>
						<div class="oc-field">
							<label for="oc-cr-email"><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?></label>
							<input id="oc-cr-email" type="email" name="email" required autocomplete="email" />
						</div>
						<div class="oc-field">
							<label for="oc-cr-pass"><?php esc_html_e( 'Password', 'owambe-connect-core' ); ?></label>
							<input id="oc-cr-pass" type="password" name="password" required minlength="8" autocomplete="new-password" />
							<small class="oc-field__hint"><?php esc_html_e( 'At least 8 characters.', 'owambe-connect-core' ); ?></small>
						</div>
						<div class="oc-field">
							<label for="oc-cr-pass2"><?php esc_html_e( 'Confirm password', 'owambe-connect-core' ); ?></label>
							<input id="oc-cr-pass2" type="password" name="password2" required minlength="8" autocomplete="new-password" />
						</div>
						<div class="oc-form__actions">
							<button type="submit" class="oc-btn oc-btn-primary oc-btn-lg oc-btn-block"><?php esc_html_e( 'Create account', 'owambe-connect-core' ); ?></button>
						</div>
						<p class="oc-help oc-help--center">
							<?php esc_html_e( 'Already have an account?', 'owambe-connect-core' ); ?>
							<a href="<?php echo esc_url( $to_login ); ?>"><?php esc_html_e( 'Sign in', 'owambe-connect-core' ); ?></a>
						</p>
					</form>

				<?php else : ?>

					<!-- Native sign-in — email/username + password. -->
					<form class="oc-form oc-auth__form" method="post" action="<?php echo esc_url( $post_url ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( OC_Client::ACTION_LOGIN ); ?>" />
						<?php if ( $redirect_to ) : ?><input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" /><?php endif; ?>
						<?php wp_nonce_field( OC_Client::ACTION_LOGIN, 'oc_client_login_nonce' ); ?>

						<div class="oc-field">
							<label for="oc-cl-log"><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?></label>
							<input id="oc-cl-log" type="email" name="log" required autocomplete="email" />
						</div>
						<div class="oc-field">
							<label for="oc-cl-pwd"><?php esc_html_e( 'Password', 'owambe-connect-core' ); ?></label>
							<input id="oc-cl-pwd" type="password" name="pwd" required autocomplete="current-password" />
						</div>
						<div class="oc-field oc-field--row">
							<label class="oc-checkbox"><input type="checkbox" name="rememberme" value="1" /> <span><?php esc_html_e( 'Remember me', 'owambe-connect-core' ); ?></span></label>
							<a class="oc-link" href="<?php echo esc_url( wp_lostpassword_url( $login_base ) ); ?>"><?php esc_html_e( 'Forgot password?', 'owambe-connect-core' ); ?></a>
						</div>
						<div class="oc-form__actions">
							<button type="submit" class="oc-btn oc-btn-primary oc-btn-lg oc-btn-block"><?php esc_html_e( 'Sign in', 'owambe-connect-core' ); ?></button>
						</div>
						<p class="oc-help oc-help--center">
							<?php esc_html_e( 'New to Owambe Connect?', 'owambe-connect-core' ); ?>
							<a href="<?php echo esc_url( $to_register ); ?>"><?php esc_html_e( 'Create an account', 'owambe-connect-core' ); ?></a>
						</p>
					</form>

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
