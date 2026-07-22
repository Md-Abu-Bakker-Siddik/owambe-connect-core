<?php
/**
 * "Request a Vendor" floating-action button + modal form.
 *
 * Auto-injected on every page via wp_footer (see OC_Shortcodes::maybe_render_fab).
 * Closed by default — opens a centered modal with a short request form.
 * Submissions POST to admin-post.php?action=oc_vendor_request and email
 * the configured admin recipient (Settings → Owambe Connect → Notification Email).
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

// Sensible default event-type list. Filterable so this can be tuned per
// site without forking the template.
$event_types = apply_filters( 'oc_vendor_request_event_types', [
	__( 'Wedding',              'owambe-connect-core' ),
	__( 'Engagement',           'owambe-connect-core' ),
	__( 'Birthday',             'owambe-connect-core' ),
	__( 'Baby shower',          'owambe-connect-core' ),
	__( 'Corporate event',      'owambe-connect-core' ),
	__( 'Private party',        'owambe-connect-core' ),
	__( 'Cultural celebration', 'owambe-connect-core' ),
	__( 'Other',                'owambe-connect-core' ),
] );
$budget_ranges = apply_filters( 'oc_vendor_request_budget_ranges', [
	__( 'Under £500',     'owambe-connect-core' ),
	__( '£500 – £1,500',  'owambe-connect-core' ),
	__( '£1,500 – £5,000','owambe-connect-core' ),
	__( '£5,000 – £15,000','owambe-connect-core' ),
	__( '£15,000+',       'owambe-connect-core' ),
	__( 'Not sure yet',   'owambe-connect-core' ),
] );
$cities = function_exists( 'oc_city_options' ) ? oc_city_options() : [];
?>
<button type="button" class="oc-vrq-fab" data-oc-vrq-open aria-haspopup="dialog" aria-controls="oc-vrq-modal" aria-label="<?php esc_attr_e( 'Request a vendor', 'owambe-connect-core' ); ?>">
	<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
	<span class="oc-vrq-fab__label"><?php esc_html_e( 'Request a vendor', 'owambe-connect-core' ); ?></span>
</button>

<div class="oc-vrq-modal" id="oc-vrq-modal" hidden role="dialog" aria-modal="true" aria-labelledby="oc-vrq-title">
	<div class="oc-vrq-modal__backdrop" data-oc-vrq-close tabindex="-1"></div>
	<div class="oc-vrq-modal__panel">
		<button type="button" class="oc-vrq-modal__close" data-oc-vrq-close aria-label="<?php esc_attr_e( 'Close', 'owambe-connect-core' ); ?>">×</button>
		<header class="oc-vrq-modal__head">
			<h2 id="oc-vrq-title"><?php esc_html_e( "Can't find the vendor you need?", 'owambe-connect-core' ); ?></h2>
			<p><?php esc_html_e( 'Tell us what you\'re planning and we\'ll match you with a vendor from our network.', 'owambe-connect-core' ); ?></p>
		</header>

		<form class="oc-vrq-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
			<input type="hidden" name="action" value="<?php echo esc_attr( OC_Dashboard::ACTION_VENDOR_REQUEST ); ?>"/>
			<?php wp_nonce_field( OC_Dashboard::ACTION_VENDOR_REQUEST, 'oc_vrq_nonce' ); ?>

			<div class="oc-vrq-grid">
				<div class="oc-vrq-field">
					<label for="oc-vrq-name"><?php esc_html_e( 'Your name', 'owambe-connect-core' ); ?> <span class="oc-req">*</span></label>
					<input id="oc-vrq-name" type="text" name="name" required maxlength="120" autocomplete="name"/>
				</div>
				<div class="oc-vrq-field">
					<label for="oc-vrq-email"><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?> <span class="oc-req">*</span></label>
					<input id="oc-vrq-email" type="email" name="email" required autocomplete="email"/>
				</div>
				<div class="oc-vrq-field">
					<label for="oc-vrq-phone"><?php esc_html_e( 'Phone (optional)', 'owambe-connect-core' ); ?></label>
					<input id="oc-vrq-phone" type="tel" name="phone" autocomplete="tel" placeholder="+44…"/>
				</div>
				<div class="oc-vrq-field">
					<label for="oc-vrq-date"><?php esc_html_e( 'Event date', 'owambe-connect-core' ); ?></label>
					<input id="oc-vrq-date" type="date" name="event_date"/>
				</div>
				<div class="oc-vrq-field">
					<label for="oc-vrq-type"><?php esc_html_e( 'Event type', 'owambe-connect-core' ); ?></label>
					<select id="oc-vrq-type" name="event_type">
						<option value=""><?php esc_html_e( '— Select —', 'owambe-connect-core' ); ?></option>
						<?php foreach ( $event_types as $type ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="oc-vrq-field">
					<label for="oc-vrq-location"><?php esc_html_e( 'Location', 'owambe-connect-core' ); ?></label>
					<select id="oc-vrq-location" name="location">
						<option value=""><?php esc_html_e( '— Select city —', 'owambe-connect-core' ); ?></option>
						<?php foreach ( $cities as $city ) : ?>
							<option value="<?php echo esc_attr( $city ); ?>"><?php echo esc_html( $city ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="oc-vrq-field oc-vrq-field--full">
					<label for="oc-vrq-budget"><?php esc_html_e( 'Budget range', 'owambe-connect-core' ); ?></label>
					<select id="oc-vrq-budget" name="budget">
						<option value=""><?php esc_html_e( '— Select —', 'owambe-connect-core' ); ?></option>
						<?php foreach ( $budget_ranges as $b ) : ?>
							<option value="<?php echo esc_attr( $b ); ?>"><?php echo esc_html( $b ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="oc-vrq-field oc-vrq-field--full">
					<label for="oc-vrq-desc"><?php esc_html_e( 'What kind of vendor are you looking for?', 'owambe-connect-core' ); ?> <span class="oc-req">*</span></label>
					<textarea id="oc-vrq-desc" name="description" rows="3" required minlength="10" placeholder="<?php esc_attr_e( 'e.g. South Asian catering for 200 people, halal, vegetarian options…', 'owambe-connect-core' ); ?>"></textarea>
				</div>
			</div>

			<div class="oc-honeypot" aria-hidden="true" style="position:absolute;left:-9999px;">
				<label><?php esc_html_e( 'Leave this field empty', 'owambe-connect-core' ); ?><input type="text" name="oc_hp" tabindex="-1" autocomplete="off"/></label>
			</div>

			<?php oc_recaptcha_field( 'vendor_request' ); ?>

			<div class="oc-vrq-actions">
				<button type="button" class="oc-btn oc-btn-ghost" data-oc-vrq-close><?php esc_html_e( 'Cancel', 'owambe-connect-core' ); ?></button>
				<button type="submit" class="oc-btn oc-btn-primary"><?php esc_html_e( 'Send request', 'owambe-connect-core' ); ?></button>
			</div>
		</form>
	</div>
</div>

<script>
(function () {
	var fab    = document.querySelector('.oc-vrq-fab');
	var modal  = document.getElementById('oc-vrq-modal');
	if (!fab || !modal) return;

	var lastActive = null;
	function open() {
		lastActive = document.activeElement;
		modal.hidden = false;
		requestAnimationFrame(function () { modal.classList.add('is-open'); });
		document.body.style.overflow = 'hidden';
		// Focus the first real input for keyboard users.
		var first = modal.querySelector('input, select, textarea');
		if (first) setTimeout(function () { try { first.focus(); } catch (e) {} }, 80);
	}
	function close() {
		modal.classList.remove('is-open');
		setTimeout(function () {
			modal.hidden = true;
			document.body.style.overflow = '';
			if (lastActive && lastActive.focus) lastActive.focus();
		}, 200);
	}

	fab.addEventListener('click', open);
	modal.querySelectorAll('[data-oc-vrq-close]').forEach(function (el) {
		el.addEventListener('click', close);
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !modal.hidden) close();
	});
})();
/* The global ?oc_notice/?oc_error toast renderer used to live here, which
   meant pages that suppress the FAB lost their form feedback. It moved to
   assets/js/oc-frontend.js (Phase 2) — loaded on every front-end page. */
</script>
