<?php
/**
 * Vendor registration shortcode template.
 *
 * Minimal signup — email, password, business name. Everything else is
 * collected progressively from the vendor dashboard so people don't bounce
 * off a wall of fields before creating an account.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$err              = isset( $_GET['oc_error'] )        ? wp_unslash( $_GET['oc_error'] )        : '';
$prev_email       = isset( $_GET['oc_email'] )        ? sanitize_email( wp_unslash( $_GET['oc_email'] ) ) : '';
$prev_business    = isset( $_GET['oc_business_name'] ) ? sanitize_text_field( wp_unslash( $_GET['oc_business_name'] ) ) : '';

$heading      = ! empty( $heading )      ? $heading      : __( 'Become a Vendor', 'owambe-connect-core' );
$subheading   = ! empty( $subheading )   ? $subheading   : __( 'Create your account in 30 seconds. You\'ll finish your profile from the dashboard once you\'re in — no need to do everything at once.', 'owambe-connect-core' );
$button_text  = ! empty( $button_text )  ? $button_text  : '';
$redirect_url = ! empty( $redirect_url ) ? $redirect_url : '';
?>
<section class="oc-section oc-auth oc-auth--register">
	<div class="oc-container oc-auth__container">
		<header class="oc-auth__head">
			<h1 class="oc-auth__title"><?php echo esc_html( $heading ); ?></h1>
			<?php if ( $subheading ) : ?><p class="oc-auth__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
		</header>

		<div class="oc-auth__body">

			<div class="oc-auth__form-wrap">
				<?php if ( $err ) : ?>
					<div class="oc-alert oc-alert--error" role="alert"><?php echo esc_html( $err ); ?></div>
				<?php endif; ?>

				<form class="oc-form oc-auth__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
					<input type="hidden" name="action" value="<?php echo esc_attr( OC_Registration::ACTION ); ?>" />
					<?php if ( $redirect_url ) : ?>
						<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_url ); ?>" />
					<?php endif; ?>
					<?php wp_nonce_field( OC_Registration::ACTION, OC_Registration::NONCE ); ?>

					<div class="oc-field">
						<label for="oc-business"><?php esc_html_e( 'Business name', 'owambe-connect-core' ); ?> <span class="oc-req">*</span></label>
						<input id="oc-business" type="text" name="business_name" required maxlength="120" value="<?php echo esc_attr( $prev_business ); ?>" autocomplete="organization" />
					</div>

					<div class="oc-grid-2">
						<div class="oc-field oc-field--with-icon">
							<label for="oc-email"><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?> <span class="oc-req">*</span></label>
							<div class="oc-field-inner">
								<span class="oc-field__icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
								</span>
								<input id="oc-email" type="email" name="email" required value="<?php echo esc_attr( $prev_email ); ?>" autocomplete="email" />
							</div>
							<small class="oc-help-micro"><?php esc_html_e( 'We\'ll send a confirmation link here.', 'owambe-connect-core' ); ?></small>
						</div>
						<div class="oc-field oc-field--with-icon oc-field--pw">
							<label for="oc-password"><?php esc_html_e( 'Password', 'owambe-connect-core' ); ?> <span class="oc-req">*</span></label>
							<div class="oc-field-inner">
								<span class="oc-field__icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
								</span>
								<input id="oc-password" type="password" name="password" required minlength="8" autocomplete="new-password" />
								<button type="button" class="oc-field__pw-toggle" data-oc-pw-toggle="oc-password" aria-label="<?php esc_attr_e( 'Show password', 'owambe-connect-core' ); ?>">
									<svg class="oc-field__pw-eye" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
								</button>
							</div>
							<small><?php esc_html_e( 'Minimum 8 characters.', 'owambe-connect-core' ); ?></small>
						</div>
					</div>

					<p class="oc-auth__reassure">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
						<?php esc_html_e( 'Showcase your services. Reach more clients. Free during MVP.', 'owambe-connect-core' ); ?>
					</p>

					<div class="oc-field oc-auth__terms">
						<label class="oc-checkbox">
							<input type="checkbox" name="terms_consent" value="1" required />
							<span>
								<?php
								/* translators: 1: Terms link, 2: Privacy link */
								printf(
									wp_kses(
										__( 'I accept the <a href="%1$s" target="_blank" rel="noopener">Terms</a> and <a href="%2$s" target="_blank" rel="noopener">Privacy Policy</a>, and confirm I\'m authorised to represent this business.', 'owambe-connect-core' ),
										[ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ]
									),
									esc_url( oc_page_url( 'terms' ) ),
									esc_url( oc_page_url( 'privacy' ) )
								);
								?>
							</span>
						</label>
					</div>

					<?php oc_recaptcha_field( 'register' ); ?>

					<div class="oc-form__actions">
						<button type="submit" class="oc-btn oc-btn-primary oc-btn-lg oc-btn-block"><?php echo esc_html( $button_text ?: __( 'Create my account', 'owambe-connect-core' ) ); ?></button>
					</div>

					<p class="oc-help oc-help--center">
						<?php esc_html_e( 'Already have an account?', 'owambe-connect-core' ); ?>
						<a href="<?php echo esc_url( oc_page_url( 'vendor-login' ) ); ?>"><?php esc_html_e( 'Log in', 'owambe-connect-core' ); ?></a>
					</p>
				</form>
			</div>

			<aside class="oc-auth__info">
				<h2 class="oc-auth__info-title"><?php esc_html_e( 'Why list with us', 'owambe-connect-core' ); ?></h2>
				<p class="oc-auth__info-lead"><?php esc_html_e( 'Get in front of UK planners actively looking for vendors who understand their culture.', 'owambe-connect-core' ); ?></p>
				<ul class="oc-auth__perks">
					<li>
						<span class="oc-auth__perk-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
						</span>
						<span>
							<strong><?php esc_html_e( 'Free during MVP', 'owambe-connect-core' ); ?></strong>
							<span><?php esc_html_e( 'No subscription, no listing fees while we build the community.', 'owambe-connect-core' ); ?></span>
						</span>
					</li>
					<li>
						<span class="oc-auth__perk-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
						</span>
						<span>
							<strong><?php esc_html_e( 'Direct enquiries', 'owambe-connect-core' ); ?></strong>
							<span><?php esc_html_e( 'Customers contact you on WhatsApp, Instagram or email — no commission.', 'owambe-connect-core' ); ?></span>
						</span>
					</li>
					<li>
						<span class="oc-auth__perk-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z"/></svg>
						</span>
						<span>
							<strong><?php esc_html_e( 'Built for our communities', 'owambe-connect-core' ); ?></strong>
							<span><?php esc_html_e( 'Designed for the UK\'s diverse event scene — celebrating African, Caribbean, South Asian, multicultural, luxury, and contemporary events with the visibility they deserve.', 'owambe-connect-core' ); ?></span>
						</span>
					</li>
				</ul>
				<p class="oc-auth__info-foot">
					<?php esc_html_e( 'After signup we\'ll guide you through a short profile checklist. Listings only go live after admin review.', 'owambe-connect-core' ); ?>
				</p>
			</aside>

		</div>
	</div>
</section>
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
