<?php
/**
 * Become-a-Vendor CTA shortcode template.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$eyebrow         = ! empty( $eyebrow )        ? $eyebrow        : __( 'Grow your event business', 'owambe-connect-core' );
$heading         = ! empty( $heading )        ? $heading        : __( 'Get found by people planning real events.', 'owambe-connect-core' );
$subheading      = ! empty( $subheading )     ? $subheading     : __( 'Owambe Connect puts your business in front of UK event planners actively looking for vendors who understand the cultures they\'re celebrating.', 'owambe-connect-core' );
$button_text     = ! empty( $button_text )    ? $button_text    : __( 'Start your application', 'owambe-connect-core' );
$button_url      = ! empty( $button_url )     ? $button_url     : oc_page_url( 'apply' );
$secondary_text  = ! empty( $secondary_text ) ? $secondary_text : __( 'I\'m already a vendor', 'owambe-connect-core' );
$secondary_url   = ! empty( $secondary_url )  ? $secondary_url  : oc_page_url( 'vendor-login' );
$show_features   = ! isset( $show_features )  || 'yes' === $show_features;
$show_steps      = ! isset( $show_steps )     || 'yes' === $show_steps;
$section_style   = ! empty( $bg_color )       ? ' style="background:' . esc_attr( $bg_color ) . ';"' : '';
?>
<section class="oc-section oc-bav"<?php echo $section_style; // phpcs:ignore ?>>
	<div class="oc-container">
		<div class="oc-bav__hero">
			<?php if ( $eyebrow ) : ?><p class="oc-bav__eyebrow"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
			<h1 class="oc-bav__title"><?php echo esc_html( $heading ); ?></h1>
			<?php if ( $subheading ) : ?><p class="oc-bav__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
			<div class="oc-bav__cta">
				<?php if ( $button_text ) : ?>
					<a class="oc-btn oc-btn-primary oc-btn-lg" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button_text ); ?></a>
				<?php endif; ?>
				<?php if ( $secondary_text ) : ?>
					<a class="oc-btn oc-btn-ghost" href="<?php echo esc_url( $secondary_url ); ?>"><?php echo esc_html( $secondary_text ); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $show_features ) : ?>
		<div class="oc-bav__features oc-feature-grid">
			<div class="oc-feature">
				<span class="oc-feature__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.4 5.6L20 10l-5.6 2.4L12 18l-2.4-5.6L4 10l5.6-2.4L12 2z"/></svg>
				</span>
				<h3><?php esc_html_e( 'Free during MVP', 'owambe-connect-core' ); ?></h3>
				<p><?php esc_html_e( 'No subscription, no listing fees while we build the community. Get in early.', 'owambe-connect-core' ); ?></p>
			</div>
			<div class="oc-feature">
				<span class="oc-feature__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.4 8.4 0 01-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.4 8.4 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
				</span>
				<h3><?php esc_html_e( 'Direct enquiries', 'owambe-connect-core' ); ?></h3>
				<p><?php esc_html_e( 'Customers contact you on WhatsApp, Instagram or email — no commission, no middleman.', 'owambe-connect-core' ); ?></p>
			</div>
			<div class="oc-feature">
				<span class="oc-feature__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
				</span>
				<h3><?php esc_html_e( 'Built for our communities', 'owambe-connect-core' ); ?></h3>
				<p><?php esc_html_e( 'Designed for the UK\'s diverse event scene — celebrating African, Caribbean, South Asian, multicultural, luxury, and contemporary events with the visibility they deserve.', 'owambe-connect-core' ); ?></p>
			</div>
			<div class="oc-feature">
				<span class="oc-feature__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33h.01A1.65 1.65 0 009 3.09V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
				</span>
				<h3><?php esc_html_e( 'You stay in control', 'owambe-connect-core' ); ?></h3>
				<p><?php esc_html_e( 'A self-service dashboard means you update your listing whenever you need to.', 'owambe-connect-core' ); ?></p>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( $show_steps ) : ?>
		<div class="oc-bav__steps oc-section--how-it-works">
			<h2 class="oc-section__title"><?php esc_html_e( 'How it works', 'owambe-connect-core' ); ?></h2>
			<ol class="oc-steps oc-how-it-works__steps">
				<li class="oc-step">
					<span class="oc-step__icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
					</span>
					<strong>1.</strong> <?php esc_html_e( 'Submit your application — takes 5 minutes.', 'owambe-connect-core' ); ?>
				</li>
				<li class="oc-step">
					<span class="oc-step__icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
					</span>
					<strong>2.</strong> <?php esc_html_e( 'We review and approve qualifying vendors.', 'owambe-connect-core' ); ?>
				</li>
				<li class="oc-step">
					<span class="oc-step__icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/></svg>
					</span>
					<strong>3.</strong> <?php esc_html_e( 'Your listing goes live and customers can find you.', 'owambe-connect-core' ); ?>
				</li>
				<li class="oc-step">
					<span class="oc-step__icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
					</span>
					<strong>4.</strong> <?php esc_html_e( 'Manage your profile from your dashboard whenever you need to.', 'owambe-connect-core' ); ?>
				</li>
			</ol>
			<div class="oc-bav__cta">
				<a class="oc-btn oc-btn-primary oc-btn-lg" href="<?php echo esc_url( $button_url ); ?>"><?php esc_html_e( 'Apply now — it\'s free', 'owambe-connect-core' ); ?></a>
			</div>
		</div>
		<?php endif; ?>
	</div>
</section>
