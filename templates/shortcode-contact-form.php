<?php
/**
 * Contact form shortcode template.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$err    = isset( $_GET['oc_error'] )  ? wp_unslash( $_GET['oc_error'] )  : '';
$notice = isset( $_GET['oc_notice'] ) ? wp_unslash( $_GET['oc_notice'] ) : '';

$heading         = ! empty( $heading )         ? $heading         : __( 'Get in touch', 'owambe-connect-core' );
$subheading      = ! empty( $subheading )      ? $subheading      : __( 'Questions, partnerships, vendor support — we\'d love to hear from you.', 'owambe-connect-core' );
$button_text     = ! empty( $button_text )     ? $button_text     : __( 'Send message', 'owambe-connect-core' );
$recipient_email = ! empty( $recipient_email ) ? sanitize_email( $recipient_email ) : '';

$show_info     = ! isset( $show_info ) || 'yes' === $show_info;
$info_heading  = ! empty( $info_heading )  ? $info_heading  : __( 'Talk to us', 'owambe-connect-core' );

// Fall back to Customizer values when the widget passes blanks, so updating
// the brand details in one place (Appearance → Customize → Owambe Connect →
// Contact details / Social Links) propagates to the Contact page without
// touching this template or the Elementor widget for each site.
$info_email    = ! empty( $info_email )    ? $info_email    : ( function_exists( 'oc_get_contact_email' ) ? oc_get_contact_email() : '' );
$info_phone    = ! empty( $info_phone )    ? $info_phone    : ( function_exists( 'oc_get_contact_phone' ) ? oc_get_contact_phone() : '' );
$wa_source     = ! empty( $info_whatsapp ) ? $info_whatsapp : ( function_exists( 'oc_get_contact_phone' ) ? oc_get_contact_phone() : '' );
$info_whatsapp = $wa_source ? preg_replace( '/[^0-9]/', '', $wa_source ) : '';
$info_address  = ! empty( $info_address )  ? $info_address  : '';
$info_hours    = ! empty( $info_hours )    ? $info_hours    : '';
$info_response = ! empty( $info_response ) ? $info_response : '';

// Customizer-sourced social URLs (so Instagram + Facebook show on the Contact
// page out of the box per client feedback §7.5).
$_oc_socials   = function_exists( 'oc_get_social_links' ) ? oc_get_social_links() : [];

$has_info = $show_info && ( $info_email || $info_phone || $info_whatsapp || $info_address || $info_hours );
?>
<section class="oc-section oc-contact">
	<div class="oc-container oc-contact__container">
		<header class="oc-contact__head">
			<h1 class="oc-contact__title"><?php echo esc_html( $heading ); ?></h1>
			<?php if ( $subheading ) : ?><p class="oc-contact__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
		</header>

		<div class="oc-contact__body<?php echo $has_info ? '' : ' oc-contact__body--solo'; ?>">

			<div class="oc-contact__form-wrap">
				<?php if ( $err ) : ?>
					<div class="oc-alert oc-alert--error" role="alert"><?php echo esc_html( $err ); ?></div>
				<?php endif; ?>
				<?php if ( $notice ) : ?>
					<div class="oc-alert oc-alert--success" role="status"><?php echo esc_html( $notice ); ?></div>
				<?php endif; ?>

				<form class="oc-form oc-contact__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( OC_Dashboard::ACTION_CONTACT ); ?>" />
					<?php if ( $recipient_email ) : ?>
						<input type="hidden" name="recipient_email" value="<?php echo esc_attr( $recipient_email ); ?>" />
					<?php endif; ?>
					<?php wp_nonce_field( OC_Dashboard::ACTION_CONTACT, 'oc_contact_nonce' ); ?>

					<div class="oc-grid-2">
						<div class="oc-field">
							<label for="c-name"><?php esc_html_e( 'Your name', 'owambe-connect-core' ); ?></label>
							<input id="c-name" type="text" name="name" required />
						</div>
						<div class="oc-field">
							<label for="c-email"><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?></label>
							<input id="c-email" type="email" name="email" required />
						</div>
					</div>
					<div class="oc-field">
						<label for="c-subject"><?php esc_html_e( 'Subject', 'owambe-connect-core' ); ?></label>
						<input id="c-subject" type="text" name="subject" placeholder="<?php esc_attr_e( 'What is this about?', 'owambe-connect-core' ); ?>" />
					</div>
					<div class="oc-field">
						<label for="c-message"><?php esc_html_e( 'Message', 'owambe-connect-core' ); ?></label>
						<textarea id="c-message" name="message" rows="6" required minlength="10" placeholder="<?php esc_attr_e( 'Tell us what you need…', 'owambe-connect-core' ); ?>"></textarea>
					</div>
					<div class="oc-honeypot" aria-hidden="true">
						<label>Leave this empty<input type="text" name="oc_hp" tabindex="-1" autocomplete="off" /></label>
					</div>
					<?php oc_recaptcha_field( 'contact' ); ?>
					<div class="oc-form__actions">
						<button type="submit" class="oc-btn oc-btn-primary oc-btn-lg"><?php echo esc_html( $button_text ); ?></button>
					</div>
				</form>
			</div>

			<?php if ( $has_info ) : ?>
				<aside class="oc-contact__info">
					<h2 class="oc-contact__info-title"><?php echo esc_html( $info_heading ); ?></h2>

					<?php if ( $info_response ) : ?>
						<p class="oc-contact__response"><?php echo esc_html( $info_response ); ?></p>
					<?php endif; ?>

					<ul class="oc-contact__list">
						<?php if ( $info_email ) : ?>
							<li class="oc-contact__row">
								<span class="oc-contact__icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
								</span>
								<span class="oc-contact__detail">
									<span class="oc-contact__label"><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?></span>
									<a href="mailto:<?php echo esc_attr( $info_email ); ?>"><?php echo esc_html( $info_email ); ?></a>
								</span>
							</li>
						<?php endif; ?>
						<?php if ( $info_phone ) : ?>
							<li class="oc-contact__row">
								<span class="oc-contact__icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
								</span>
								<span class="oc-contact__detail">
									<span class="oc-contact__label"><?php esc_html_e( 'Phone', 'owambe-connect-core' ); ?></span>
									<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $info_phone ) ); ?>"><?php echo esc_html( $info_phone ); ?></a>
								</span>
							</li>
						<?php endif; ?>
						<?php if ( $info_whatsapp ) : ?>
							<li class="oc-contact__row">
								<span class="oc-contact__icon oc-contact__icon--wa" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M20.52 3.45A11.81 11.81 0 0012.05 0C5.5 0 .18 5.32.18 11.86a11.78 11.78 0 001.6 5.94L0 24l6.36-1.66a11.85 11.85 0 005.7 1.45h.01c6.55 0 11.87-5.32 11.87-11.86 0-3.17-1.24-6.15-3.42-8.48zM12.07 21.8h-.01a9.85 9.85 0 01-5.02-1.37l-.36-.21-3.78.99 1-3.69-.24-.38a9.84 9.84 0 01-1.51-5.28c0-5.45 4.43-9.88 9.88-9.88 2.64 0 5.12 1.03 6.99 2.9a9.81 9.81 0 012.9 6.99c0 5.45-4.43 9.93-9.85 9.93z"/></svg>
								</span>
								<span class="oc-contact__detail">
									<span class="oc-contact__label"><?php esc_html_e( 'WhatsApp', 'owambe-connect-core' ); ?></span>
									<a href="https://wa.me/<?php echo esc_attr( $info_whatsapp ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Message us', 'owambe-connect-core' ); ?></a>
								</span>
							</li>
						<?php endif; ?>
						<?php if ( $info_address ) : ?>
							<li class="oc-contact__row">
								<span class="oc-contact__icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
								</span>
								<span class="oc-contact__detail">
									<span class="oc-contact__label"><?php esc_html_e( 'Location', 'owambe-connect-core' ); ?></span>
									<span><?php echo nl2br( esc_html( $info_address ) ); ?></span>
								</span>
							</li>
						<?php endif; ?>
						<?php if ( $info_hours ) : ?>
							<li class="oc-contact__row">
								<span class="oc-contact__icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
								</span>
								<span class="oc-contact__detail">
									<span class="oc-contact__label"><?php esc_html_e( 'Hours', 'owambe-connect-core' ); ?></span>
									<span><?php echo esc_html( $info_hours ); ?></span>
								</span>
							</li>
						<?php endif; ?>
						<?php if ( ! empty( $_oc_socials['instagram'] ) ) : ?>
							<li class="oc-contact__row">
								<span class="oc-contact__icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 01-1.38-.9 3.7 3.7 0 01-.9-1.38c-.16-.42-.36-1.06-.41-2.23-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16zM12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63a5.86 5.86 0 00-2.13 1.38A5.86 5.86 0 00.63 4.14C.33 4.9.13 5.78.07 7.05.01 8.33 0 8.74 0 12c0 3.26.01 3.67.07 4.95.06 1.27.26 2.15.56 2.91.32.78.74 1.45 1.38 2.13.68.64 1.35 1.06 2.13 1.38.76.3 1.64.5 2.91.56C8.33 23.99 8.74 24 12 24c3.26 0 3.67-.01 4.95-.07 1.27-.06 2.15-.26 2.91-.56.78-.32 1.45-.74 2.13-1.38a5.86 5.86 0 001.38-2.13c.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95 0-3.26-.01-3.67-.07-4.95-.06-1.27-.26-2.15-.56-2.91A5.86 5.86 0 0021.99 2.01 5.86 5.86 0 0019.86.63c-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0zm0 5.84A6.16 6.16 0 1018.16 12 6.16 6.16 0 0012 5.84zm0 10.16A4 4 0 1116 12a4 4 0 01-4 4z"/></svg>
								</span>
								<span class="oc-contact__detail">
									<span class="oc-contact__label"><?php esc_html_e( 'Instagram', 'owambe-connect-core' ); ?></span>
									<a href="<?php echo esc_url( $_oc_socials['instagram'] ); ?>" target="_blank" rel="noopener noreferrer">@owambeconnectuk</a>
								</span>
							</li>
						<?php endif; ?>
						<?php if ( ! empty( $_oc_socials['facebook'] ) ) : ?>
							<li class="oc-contact__row">
								<span class="oc-contact__icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M22 12a10 10 0 10-11.56 9.88V14.9H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.77l-.44 2.9h-2.33v6.98A10 10 0 0022 12z"/></svg>
								</span>
								<span class="oc-contact__detail">
									<span class="oc-contact__label"><?php esc_html_e( 'Facebook', 'owambe-connect-core' ); ?></span>
									<a href="<?php echo esc_url( $_oc_socials['facebook'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Visit our page', 'owambe-connect-core' ); ?></a>
								</span>
							</li>
						<?php endif; ?>
					</ul>
				</aside>
			<?php endif; ?>

		</div>
	</div>
</section>
