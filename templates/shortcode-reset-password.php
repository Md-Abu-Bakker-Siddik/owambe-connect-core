<?php
/**
 * Branded "Set a new password" form. Reached from the password-reset
 * email link with ?key=…&login=…. We validate the key on render so we can
 * either show the form OR show an "expired link" message immediately,
 * before the visitor wastes time typing a new password.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$err    = isset( $_GET['oc_error'] )  ? wp_unslash( $_GET['oc_error'] )  : '';
$notice = isset( $_GET['oc_notice'] ) ? wp_unslash( $_GET['oc_notice'] ) : '';

$key   = isset( $_GET['key'] )   ? sanitize_text_field( wp_unslash( $_GET['key'] ) )   : '';
$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';

$heading     = ! empty( $heading )     ? $heading     : __( 'Set a new password', 'owambe-connect-core' );
$subheading  = ! empty( $subheading )  ? $subheading  : __( 'Choose something only you would know. Minimum 8 characters.', 'owambe-connect-core' );
$button_text = ! empty( $button_text ) ? $button_text : __( 'Save new password', 'owambe-connect-core' );

// Validate the link the visitor arrived on. We only render the form if
// it's still valid — otherwise we point them back at /forgot-password/.
$user = ( $key && $login ) ? check_password_reset_key( $key, $login ) : null;
$link_valid = $user instanceof WP_User;
?>
<section class="oc-section oc-auth oc-auth--reset">
	<div class="oc-container oc-auth__container">
		<header class="oc-auth__head">
			<h1 class="oc-auth__title"><?php echo esc_html( $heading ); ?></h1>
			<?php if ( $link_valid && $subheading ) : ?><p class="oc-auth__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
		</header>

		<div class="oc-auth__body oc-auth__body--single">

			<div class="oc-auth__form-wrap">
				<?php if ( $err ) : ?>
					<div class="oc-alert oc-alert--error" role="alert"><?php echo esc_html( $err ); ?></div>
				<?php endif; ?>
				<?php if ( $notice ) : ?>
					<div class="oc-alert oc-alert--success" role="status"><?php echo esc_html( $notice ); ?></div>
				<?php endif; ?>

				<?php if ( ! $link_valid ) : ?>
					<div class="oc-alert oc-alert--error" role="alert">
						<?php esc_html_e( 'This password reset link has expired or is invalid. Please request a fresh one.', 'owambe-connect-core' ); ?>
					</div>
					<p class="oc-help oc-help--center">
						<a class="oc-btn oc-btn-primary" href="<?php echo esc_url( oc_page_url( 'forgot-password' ) ); ?>"><?php esc_html_e( 'Request a new link', 'owambe-connect-core' ); ?></a>
					</p>
				<?php else : ?>

					<form class="oc-form oc-auth__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( OC_Dashboard::ACTION_RESET_PASSWORD ); ?>" />
						<input type="hidden" name="key"    value="<?php echo esc_attr( $key ); ?>" />
						<input type="hidden" name="login"  value="<?php echo esc_attr( $login ); ?>" />
						<?php wp_nonce_field( OC_Dashboard::ACTION_RESET_PASSWORD, 'oc_rp_nonce' ); ?>

						<div class="oc-field oc-field--with-icon oc-field--pw">
							<label for="oc-rp-pass1"><?php esc_html_e( 'New password', 'owambe-connect-core' ); ?> <span class="oc-req">*</span></label>
							<div class="oc-field-inner">
								<span class="oc-field__icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
								</span>
								<input id="oc-rp-pass1" type="password" name="pass1" required minlength="8" autocomplete="new-password"/>
								<button type="button" class="oc-field__pw-toggle" data-oc-pw-toggle="oc-rp-pass1" aria-label="<?php esc_attr_e( 'Show password', 'owambe-connect-core' ); ?>">
									<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
								</button>
							</div>
							<small><?php esc_html_e( 'Minimum 8 characters.', 'owambe-connect-core' ); ?></small>
						</div>

						<div class="oc-field oc-field--with-icon oc-field--pw">
							<label for="oc-rp-pass2"><?php esc_html_e( 'Confirm new password', 'owambe-connect-core' ); ?> <span class="oc-req">*</span></label>
							<div class="oc-field-inner">
								<span class="oc-field__icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
								</span>
								<input id="oc-rp-pass2" type="password" name="pass2" required minlength="8" autocomplete="new-password"/>
								<button type="button" class="oc-field__pw-toggle" data-oc-pw-toggle="oc-rp-pass2" aria-label="<?php esc_attr_e( 'Show password', 'owambe-connect-core' ); ?>">
									<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
								</button>
							</div>
						</div>

						<div class="oc-form__actions">
							<button type="submit" class="oc-btn oc-btn-primary oc-btn-lg oc-btn-block"><?php echo esc_html( $button_text ); ?></button>
						</div>
					</form>

					<script>
					(function () {
						document.querySelectorAll('[data-oc-pw-toggle]').forEach(function (btn) {
							var t = document.getElementById(btn.getAttribute('data-oc-pw-toggle'));
							if (!t) return;
							btn.addEventListener('click', function () {
								var isHidden = t.type === 'password';
								t.type = isHidden ? 'text' : 'password';
								btn.setAttribute('aria-label', isHidden ? '<?php echo esc_js( __( 'Hide password', 'owambe-connect-core' ) ); ?>' : '<?php echo esc_js( __( 'Show password', 'owambe-connect-core' ) ); ?>');
							});
						});
					})();
					</script>

				<?php endif; ?>
			</div>

		</div>
	</div>
</section>
