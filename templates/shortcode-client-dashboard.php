<?php
/**
 * Client dashboard — sidebar + content layout.
 *
 * Reuses the vendor-dashboard shell (.oc-vd__* classes) so the existing
 * theme CSS applies without duplication; client-specific bits use the
 * .oc-cd__* namespace (styled in the theme's phase2.css).
 *
 * Tabs: overview | saved | contacted | event | account.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

// ─── Logged-out gate ─────────────────────────────────────
// The shortcode/page normally redirects logged-out visitors, but the
// template must be standalone-safe (Elementor preview, direct render):
// show a sign-in prompt card and bail.
if ( ! is_user_logged_in() ) : ?>
<div class="oc-cd oc-vd" id="oc-client-dashboard">
	<div class="oc-vd__container">
		<div class="oc-vd__card oc-cd__signin">
			<h2><?php esc_html_e( 'Sign in to see your dashboard', 'owambe-connect-core' ); ?></h2>
			<p><?php esc_html_e( 'Your saved vendors, recent contacts and event page all live here. Sign in to pick up where you left off.', 'owambe-connect-core' ); ?></p>
			<a class="oc-vd__btn oc-vd__btn--primary" href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( oc_page_url( 'client-dashboard' ) ), oc_page_url( 'client-login' ) ) ); ?>">
				<?php esc_html_e( 'Sign in', 'owambe-connect-core' ); ?> →
			</a>
		</div>
	</div>
</div>
<?php
	return;
endif;

$user   = wp_get_current_user();
$err    = isset( $_GET['oc_error'] )  ? wp_unslash( $_GET['oc_error'] )  : '';
$notice = isset( $_GET['oc_notice'] ) ? wp_unslash( $_GET['oc_notice'] ) : '';

// ─── Saved vendors (defensive: only published oc_vendor posts) ───
$saved_ids   = ( class_exists( 'OC_Client' ) ) ? (array) OC_Client::saved_vendors( $user->ID ) : [];
$saved_posts = [];
foreach ( $saved_ids as $sid ) {
	$sp = get_post( (int) $sid );
	if ( $sp instanceof WP_Post && OC_CPT === $sp->post_type && OC_STATUS_APPROVED === $sp->post_status ) {
		$saved_posts[] = $sp;
	}
}

// ─── Recently contacted (defensive: resolve rows, skip missing vendors) ───
$contact_rows = ( class_exists( 'OC_Client' ) ) ? (array) OC_Client::recent_contacts( $user->ID ) : [];
$contacts     = [];
foreach ( $contact_rows as $row ) {
	$row       = (array) $row;
	$vendor_id = (int) ( $row['vendor_id'] ?? $row[0] ?? 0 );
	$channel   = (string) ( $row['channel'] ?? $row[1] ?? '' );
	$ts        = (int) ( $row['ts'] ?? $row[2] ?? 0 );
	$vp        = $vendor_id ? get_post( $vendor_id ) : null;
	if ( ! ( $vp instanceof WP_Post ) || OC_CPT !== $vp->post_type || OC_STATUS_APPROVED !== $vp->post_status ) {
		continue; // Vendor deleted / unpublished since the contact — skip.
	}
	$contacts[] = [ 'post' => $vp, 'channel' => $channel, 'ts' => $ts ];
}

$saved_count   = count( $saved_posts );
$contact_count = count( $contacts );

// Channel → label + pill colour. Channels arrive as e.g. 'whatsapp' (the
// beacon strips the 'click_' metric prefix) but strip defensively anyway.
$channel_labels = [
	'whatsapp'  => __( 'WhatsApp',  'owambe-connect-core' ),
	'email'     => __( 'Email',     'owambe-connect-core' ),
	'instagram' => __( 'Instagram', 'owambe-connect-core' ),
	'facebook'  => __( 'Facebook',  'owambe-connect-core' ),
	'website'   => __( 'Website',   'owambe-connect-core' ),
];
$channel_colors = [
	'whatsapp'  => '#2E7D5B',
	'email'     => '#B8860B',
	'instagram' => '#B0354F',
	'facebook'  => '#3B5998',
	'website'   => '#6B6361',
];

$google_sub  = (string) get_user_meta( $user->ID, '_oc_google_sub', true );
$vendors_url = oc_page_url( 'vendors' );
$member_ts   = strtotime( $user->user_registered );
$display     = $user->display_name ?: $user->user_email;
$initial     = function_exists( 'mb_substr' ) ? mb_substr( $display, 0, 1 ) : substr( $display, 0, 1 );

$tabs = [
	'overview'  => [ 'label' => __( 'Overview',           'owambe-connect-core' ), 'icon' => 'admin-home' ],
	'saved'     => [ 'label' => __( 'Saved vendors',      'owambe-connect-core' ), 'icon' => 'heart' ],
	'contacted' => [ 'label' => __( 'Recently contacted', 'owambe-connect-core' ), 'icon' => 'email-alt' ],
	'event'     => [ 'label' => __( 'My event page',      'owambe-connect-core' ), 'icon' => 'calendar-alt' ],
	'account'   => [ 'label' => __( 'Account',            'owambe-connect-core' ), 'icon' => 'admin-users' ],
];
?>
<div class="oc-cd oc-vd" id="oc-client-dashboard">
	<div class="oc-vd__container">

		<nav class="oc-vd__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'owambe-connect-core' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'owambe-connect-core' ); ?></a>
			<span aria-hidden="true">/</span>
			<span class="oc-vd__crumb-current"><?php esc_html_e( 'My Dashboard', 'owambe-connect-core' ); ?></span>
		</nav>

		<?php if ( $err ) : ?>
			<div class="oc-vd__alert oc-vd__alert--error" role="alert"><?php echo esc_html( $err ); ?></div>
		<?php endif; ?>
		<?php if ( $notice ) : ?>
			<div class="oc-vd__alert oc-vd__alert--success" role="status" aria-live="polite"><?php echo esc_html( $notice ); ?></div>
		<?php endif; ?>

		<div class="oc-vd__layout">

			<!-- ─────────── Sidebar ─────────── -->
			<aside class="oc-vd__sidebar" aria-label="<?php esc_attr_e( 'Dashboard menu', 'owambe-connect-core' ); ?>">
				<div class="oc-vd__profile">
					<div class="oc-vd__avatar-wrap">
						<div class="oc-vd__avatar">
							<span class="oc-vd__avatar-fallback" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
						</div>
					</div>
					<div class="oc-vd__profile-meta">
						<strong><?php echo esc_html( $display ); ?></strong>
						<small><?php echo esc_html( $user->user_email ); ?></small>
					</div>
				</div>

				<ul class="oc-vd__menu" role="tablist">
					<?php foreach ( $tabs as $key => $t ) : ?>
						<li>
							<button type="button" role="tab" data-oc-tab="<?php echo esc_attr( $key ); ?>" class="oc-vd__menu-btn">
								<span class="dashicons dashicons-<?php echo esc_attr( $t['icon'] ); ?>"></span>
								<span><?php echo esc_html( $t['label'] ); ?></span>
							</button>
						</li>
					<?php endforeach; ?>
				</ul>

				<div class="oc-vd__menu-foot">
					<a class="oc-vd__menu-link" href="<?php echo esc_url( $vendors_url ); ?>">
						<span class="dashicons dashicons-search"></span>
						<span><?php esc_html_e( 'Browse vendors', 'owambe-connect-core' ); ?></span>
					</a>
					<a class="oc-vd__menu-link oc-vd__menu-link--logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
						<span class="dashicons dashicons-exit"></span>
						<span><?php esc_html_e( 'Log out', 'owambe-connect-core' ); ?></span>
					</a>
				</div>
			</aside>

			<!-- ─────────── Main content ─────────── -->
			<main class="oc-vd__content">

				<!-- ============== Overview ============== -->
				<section class="oc-vd__panel" data-oc-panel="overview">
					<header class="oc-vd__panel-head">
						<h1><?php
							/* translators: %s: user's display name */
							printf( esc_html__( 'Welcome back, %s', 'owambe-connect-core' ), esc_html( $display ) );
						?></h1>
						<p><?php esc_html_e( 'Your saved vendors and recent activity, all in one place.', 'owambe-connect-core' ); ?></p>
					</header>

					<div class="oc-vd__stats">
						<div class="oc-vd__stat">
							<span class="dashicons dashicons-heart"></span>
							<div><strong><?php echo (int) $saved_count; ?></strong><small><?php esc_html_e( 'Saved vendors', 'owambe-connect-core' ); ?></small></div>
						</div>
						<div class="oc-vd__stat">
							<span class="dashicons dashicons-email-alt"></span>
							<div><strong><?php echo (int) $contact_count; ?></strong><small><?php esc_html_e( 'Vendors contacted', 'owambe-connect-core' ); ?></small></div>
						</div>
						<div class="oc-vd__stat">
							<span class="dashicons dashicons-calendar-alt"></span>
							<div><strong><?php echo esc_html( $member_ts ? date_i18n( 'M Y', $member_ts ) : '—' ); ?></strong><small><?php esc_html_e( 'Member since', 'owambe-connect-core' ); ?></small></div>
						</div>
					</div>

					<div class="oc-vd__quick-actions">
						<a class="oc-vd__action" href="<?php echo esc_url( $vendors_url ); ?>">
							<span class="dashicons dashicons-search"></span>
							<div><strong><?php esc_html_e( 'Browse vendors', 'owambe-connect-core' ); ?></strong><small><?php esc_html_e( 'Find the right people for your event', 'owambe-connect-core' ); ?></small></div>
						</a>
						<button type="button" class="oc-vd__action" data-oc-tab-jump="saved">
							<span class="dashicons dashicons-heart"></span>
							<div><strong><?php esc_html_e( 'View saved', 'owambe-connect-core' ); ?></strong><small><?php esc_html_e( 'Your shortlisted vendors', 'owambe-connect-core' ); ?></small></div>
						</button>
					</div>
				</section>

				<!-- ============== Saved vendors ============== -->
				<section class="oc-vd__panel" data-oc-panel="saved">
					<header class="oc-vd__panel-head">
						<h1><?php esc_html_e( 'Saved vendors', 'owambe-connect-core' ); ?></h1>
						<p><?php esc_html_e( 'Vendors you\'ve shortlisted. Tap the heart on any profile to add or remove.', 'owambe-connect-core' ); ?></p>
					</header>

					<?php if ( $saved_posts ) : ?>
						<div class="oc-grid oc-grid--vendors">
							<?php foreach ( $saved_posts as $sp ) : ?>
								<?php echo oc_get_template( 'partials/vendor-card.php', [ 'post_id' => $sp->ID ] ); // phpcs:ignore — template escapes internally ?>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="oc-cd__empty">
							<span class="dashicons dashicons-heart" aria-hidden="true"></span>
							<h2><?php esc_html_e( 'No saved vendors yet', 'owambe-connect-core' ); ?></h2>
							<p><?php esc_html_e( 'Tap the heart on any vendor card or profile to keep them here for later.', 'owambe-connect-core' ); ?></p>
							<a class="oc-vd__btn oc-vd__btn--primary" href="<?php echo esc_url( $vendors_url ); ?>"><?php esc_html_e( 'Browse vendors', 'owambe-connect-core' ); ?> →</a>
						</div>
					<?php endif; ?>
				</section>

				<!-- ============== Recently contacted ============== -->
				<section class="oc-vd__panel" data-oc-panel="contacted">
					<header class="oc-vd__panel-head">
						<h1><?php esc_html_e( 'Recently contacted', 'owambe-connect-core' ); ?></h1>
						<p><?php esc_html_e( 'Vendors you\'ve reached out to recently, newest first.', 'owambe-connect-core' ); ?></p>
					</header>

					<?php if ( $contacts ) : ?>
						<div class="oc-vd__card">
							<?php foreach ( $contacts as $c ) :
								$key   = str_replace( 'click_', '', strtolower( $c['channel'] ) );
								$label = $channel_labels[ $key ] ?? ucfirst( $key );
								$color = $channel_colors[ $key ] ?? '#6B6361';
							?>
								<div class="oc-cd__contact-row">
									<a class="oc-cd__contact-vendor" href="<?php echo esc_url( get_permalink( $c['post'] ) ); ?>"><?php echo esc_html( get_the_title( $c['post'] ) ); ?></a>
									<span class="oc-vd__status-pill oc-cd__contact-channel" style="--c:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $label ); ?></span>
									<?php if ( $c['ts'] ) : ?>
										<time class="oc-cd__contact-ago" datetime="<?php echo esc_attr( gmdate( 'c', $c['ts'] ) ); ?>">
											<?php
											/* translators: %s: human-readable time difference, e.g. "3 days" */
											printf( esc_html__( '%s ago', 'owambe-connect-core' ), esc_html( human_time_diff( min( $c['ts'], time() ), time() ) ) );
											?>
										</time>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="oc-cd__empty">
							<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
							<h2><?php esc_html_e( 'No contacts yet', 'owambe-connect-core' ); ?></h2>
							<p><?php esc_html_e( 'When you message a vendor on WhatsApp, email or their socials, they\'ll show up here so you never lose track.', 'owambe-connect-core' ); ?></p>
							<a class="oc-vd__btn oc-vd__btn--primary" href="<?php echo esc_url( $vendors_url ); ?>"><?php esc_html_e( 'Browse vendors', 'owambe-connect-core' ); ?> →</a>
						</div>
					<?php endif; ?>
				</section>

				<!-- ============== My event page ============== -->
				<section class="oc-vd__panel" data-oc-panel="event">
					<header class="oc-vd__panel-head">
						<h1><?php esc_html_e( 'My event page', 'owambe-connect-core' ); ?></h1>
						<p><?php esc_html_e( 'A shareable page for your big day.', 'owambe-connect-core' ); ?></p>
					</header>

					<div class="oc-cd__empty">
						<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
						<h2><?php esc_html_e( 'Your event page is coming soon', 'owambe-connect-core' ); ?></h2>
						<p><?php esc_html_e( 'Soon you\'ll be able to build a beautiful page for your event — with your story, schedule, RSVP and gift registry — and share it with guests on WhatsApp.', 'owambe-connect-core' ); ?></p>
					</div>
				</section>

				<!-- ============== Account ============== -->
				<section class="oc-vd__panel" data-oc-panel="account">
					<header class="oc-vd__panel-head">
						<h1><?php esc_html_e( 'Account', 'owambe-connect-core' ); ?></h1>
						<p><?php esc_html_e( 'Your sign-in details.', 'owambe-connect-core' ); ?></p>
					</header>

					<div class="oc-vd__card">
						<h2><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?></h2>
						<p style="margin:0;color:#1F1B1A;font-weight:500;"><?php echo esc_html( $user->user_email ); ?></p>
						<?php if ( $google_sub ) : ?>
							<small style="color:#6B6361;"><?php esc_html_e( 'Signed in with Google — no password needed, just use "Continue with Google".', 'owambe-connect-core' ); ?></small>
						<?php else : ?>
							<small style="color:#6B6361;"><?php esc_html_e( 'Contact support if you need to change this.', 'owambe-connect-core' ); ?></small>
						<?php endif; ?>
					</div>

					<div class="oc-vd__card">
						<h2><?php esc_html_e( 'Log out', 'owambe-connect-core' ); ?></h2>
						<p style="margin:0 0 10px;color:#6B6361;font-size:13px;"><?php esc_html_e( 'Sign out of your account on this device.', 'owambe-connect-core' ); ?></p>
						<a class="oc-vd__btn oc-vd__btn--primary" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'owambe-connect-core' ); ?></a>
					</div>
				</section>

			</main>
		</div>
	</div>
</div>

<script>
(function () {
	var root = document.getElementById('oc-client-dashboard');
	if (!root) return;
	var menuBtns  = root.querySelectorAll('[data-oc-tab]');
	var panels    = root.querySelectorAll('[data-oc-panel]');
	var validTabs = Array.prototype.map.call(menuBtns, function (b) { return b.dataset.ocTab; });
	var currentTab = 'overview';

	function setTab(tab, push) {
		if (validTabs.indexOf(tab) === -1) tab = validTabs[0];
		currentTab = tab;
		menuBtns.forEach(function (b) { b.classList.toggle('is-active', b.dataset.ocTab === tab); b.setAttribute('aria-selected', b.dataset.ocTab === tab ? 'true' : 'false'); });
		panels.forEach(function (p) { p.classList.toggle('is-active', p.dataset.ocPanel === tab); });
		if (push) {
			// Query param (not hash) so the tab survives redirects/deep links.
			var url = new URL(window.location.href);
			url.searchParams.set('tab', tab);
			url.hash = '';
			history.replaceState(null, '', url.toString());
		}
		// Glide the new panel's head back into view if it isn't already.
		var head = root.querySelector('[data-oc-panel="' + tab + '"] .oc-vd__panel-head');
		if (head) {
			var rect = head.getBoundingClientRect();
			if (rect.top < 0 || rect.top > 120) {
				if (window.innerWidth >= 900) {
					window.scrollTo({ top: window.pageYOffset + rect.top - 80, behavior: 'smooth' });
				} else {
					head.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
			}
		}
	}

	menuBtns.forEach(function (b) { b.addEventListener('click', function () { setTab(b.dataset.ocTab, true); }); });
	// Delegation so quick-action jump cards work without individual wiring.
	root.addEventListener('click', function (e) {
		var b = e.target.closest('[data-oc-tab-jump]');
		if (b) setTab(b.dataset.ocTabJump, true);
	});

	// Resolve initial tab: ?tab= wins, then legacy hash, then default.
	var initialQs  = new URLSearchParams(window.location.search);
	var initialTab = initialQs.get('tab') || (window.location.hash || '').replace('#', '') || 'overview';
	setTab(initialTab, false);
	window.addEventListener('hashchange', function () { setTab((window.location.hash || '').replace('#', '') || currentTab, false); });
})();
</script>
