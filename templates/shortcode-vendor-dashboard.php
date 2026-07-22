<?php
/**
 * Vendor dashboard — sidebar + content layout.
 *
 * @package OwambeConnect
 * @var WP_Post|null $vendor_post
 */
defined( 'ABSPATH' ) || exit;

$user       = wp_get_current_user();
$err        = isset( $_GET['oc_error'] )   ? wp_unslash( $_GET['oc_error'] )   : '';
$notice     = isset( $_GET['oc_notice'] )  ? wp_unslash( $_GET['oc_notice'] )  : '';

// Translate ?oc_verify=… (sent by the email-verification flow) into the
// generic notice/error machinery so we don't need a separate toast pipe.
if ( isset( $_GET['oc_verify'] ) ) {
	switch ( (string) $_GET['oc_verify'] ) {
		case 'ok':         $notice = __( 'Email verified — your profile is one step closer to going live.', 'owambe-connect-core' ); break;
		case 'resent':     $notice = __( 'We\'ve sent a new verification email — check your inbox.', 'owambe-connect-core' ); break;
		case 'throttled':  $err    = __( 'Please wait a minute before requesting another verification email.', 'owambe-connect-core' ); break;
		case 'expired':    $err    = __( 'That verification link has expired. Hit "Resend verification email" to get a new one.', 'owambe-connect-core' ); break;
		case 'invalid':    $err    = __( 'That verification link is no longer valid.', 'owambe-connect-core' ); break;
		case 'login':      $err    = __( 'Please log in first, then resend the verification email.', 'owambe-connect-core' ); break;
		case 'no_listing': $err    = __( 'We can\'t find a vendor listing on your account.', 'owambe-connect-core' ); break;
	}
}
$applied    = isset( $_GET['oc_applied'] );
$welcome    = isset( $_GET['oc_welcome'] );
$categories = OC_Queries::categories_with_counts();
$languages  = oc_language_options();
$prices     = oc_price_range_options();

// Show the dashboard form to ANY logged-in user, even before a vendor
// post exists on their account. On the first Save, class-dashboard.php's
// update_listing() handler creates the post + adds the oc_vendor role.
// Synthesise a placeholder stdClass so subsequent property reads
// ($vendor_post->ID, ->post_status, etc.) work without conditional gates
// throughout the template.
$has_post = $vendor_post instanceof WP_Post;
if ( ! $has_post ) {
	$vendor_post = (object) [
		'ID'          => 0,
		'post_status' => OC_STATUS_PENDING,
		'post_title'  => '',
		'post_date'   => current_time( 'mysql' ),
		'post_author' => get_current_user_id(),
	];
}

$id          = (int) $vendor_post->ID;
$status      = $vendor_post->post_status;
$rejection   = (string) get_post_meta( $id, '_oc_rejection_note', true );
$current_cat = wp_list_pluck( wp_get_post_terms( $id, OC_TAX ), 'term_id' );
$values = [
	'business_name'        => get_post_meta( $id, '_oc_business_name', true ) ?: get_the_title( $id ),
	'location'             => get_post_meta( $id, '_oc_location',      true ),
	'location_country'     => get_post_meta( $id, '_oc_location_country', true ),
	'location_areas'       => (array) get_post_meta( $id, '_oc_location_areas', true ),
	'location_regions'     => (array) get_post_meta( $id, '_oc_location_regions', true ),
	'cultural_specialties' => (array) get_post_meta( $id, '_oc_cultural_specialties', true ),
	'nigerian_specialty'   => get_post_meta( $id, '_oc_nigerian_specialty', true ),
	'registered_business'  => get_post_meta( $id, '_oc_registered_business', true ),
	'vendor_tags'          => (array) get_post_meta( $id, '_oc_vendor_tags', true ),
	'bio'                  => get_post_meta( $id, '_oc_bio',           true ),
	'services'             => get_post_meta( $id, '_oc_services',      true ),
	'price_range'          => get_post_meta( $id, '_oc_price_range',   true ),
	'whatsapp'             => get_post_meta( $id, '_oc_whatsapp',      true ),
	'whatsapp_local'       => oc_uk_whatsapp_local( get_post_meta( $id, '_oc_whatsapp', true ) ),
	'public_email'         => get_post_meta( $id, '_oc_public_email',  true ),
	'instagram'            => get_post_meta( $id, '_oc_instagram',     true ),
	'facebook'             => get_post_meta( $id, '_oc_facebook',      true ),
	'website'               => get_post_meta( $id, '_oc_website',       true ),
	'languages'            => (array) get_post_meta( $id, '_oc_languages', true ),
];
$logo_id          = (int) get_post_meta( $id, '_oc_logo_id',   true );
$banner_id        = (int) get_post_meta( $id, '_oc_banner_id', true );
$gallery_ids      = (array) get_post_meta( $id, '_oc_gallery_ids', true );
$gallery_ids      = array_values( array_filter( array_map( 'intval', $gallery_ids ) ) );
$gallery_max      = function_exists( 'oc_vendor_gallery_cap' ) ? oc_vendor_gallery_cap( $id ) : (int) oc_get_setting( 'gallery_max_images', 6 );
$gallery_mb       = (int) oc_get_setting( 'gallery_max_mb', 3 );
$is_featured      = (int) get_post_meta( $id, '_oc_featured', true ) === 1;
$is_verified      = (int) get_post_meta( $id, '_oc_verified', true ) === 1;
$vendor_number    = (string) get_post_meta( $id, '_oc_vendor_number', true );

$country_options   = oc_country_options();
$cities_by_country = function_exists( 'oc_cities_by_country' ) ? oc_cities_by_country() : [];
$region_options    = function_exists( 'oc_region_options' ) ? oc_region_options() : [];
$cultural_options  = oc_cultural_specialty_options();
$tag_groups        = oc_vendor_tag_options();
$email_verified    = (function () use ( $id ) {
	$raw = get_post_meta( $id, '_oc_email_verified', true );
	return ( '' === $raw || null === $raw ) ? true : ( (int) $raw === 1 );
})();

$submitted    = oc_is_submitted_for_review( $id );
$is_draft     = ( OC_STATUS_PENDING === $status ) && ! $submitted;
$status_label = oc_display_status_label( $id, $status );
$status_color = $is_draft ? '#6B6361' : ( [
	OC_STATUS_PENDING  => '#B8860B',
	OC_STATUS_APPROVED => '#2E7D5B',
	OC_STATUS_REJECTED => '#B0354F',
][ $status ] ?? '#6B6361' );

$initial   = function_exists( 'mb_substr' ) ? mb_substr( $values['business_name'], 0, 1 ) : substr( $values['business_name'], 0, 1 );
$logo_url  = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
$listed_ts = strtotime( $vendor_post->post_date );

$completion       = oc_profile_completion( $id );
$pct              = (int) $completion['percent'];
$ring_dash        = 100; // SVG circumference (r=15.915 normalised)
$ring_offset      = max( 0, 100 - $pct );
$missing_items    = array_values( array_filter( $completion['checklist'], function ( $i ) { return empty( $i['done'] ); } ) );
$completed_items  = array_values( array_filter( $completion['checklist'], function ( $i ) { return ! empty( $i['done'] ); } ) );

// May-2026 dashboard split — the old "My Listing" was a giant scroll;
// it's now broken into Business / Story / Contact so each tab has a
// focused purpose. Order in the sidebar follows the natural fill-in flow.
$tabs = [
	'overview' => [ 'label' => __( 'Overview',         'owambe-connect-core' ), 'icon' => 'admin-home' ],
	'business' => [ 'label' => __( 'Business',         'owambe-connect-core' ), 'icon' => 'store' ],
	'story'    => [ 'label' => __( 'Story & tags',     'owambe-connect-core' ), 'icon' => 'editor-quote' ],
	'contact'  => [ 'label' => __( 'Contact channels', 'owambe-connect-core' ), 'icon' => 'phone' ],
	'photos'   => [ 'label' => __( 'Photos & Gallery', 'owambe-connect-core' ), 'icon' => 'format-gallery' ],
	'account'  => [ 'label' => __( 'Account',          'owambe-connect-core' ), 'icon' => 'admin-users' ],
];

// Phase 2 — reviews tab always present; analytics tab only when the admin
// has switched vendor-facing analytics on (client decision: hidden until
// vendors are getting meaningful traffic).
$vendor_analytics_on = (int) oc_get_setting( 'vendor_analytics_enabled', 0 ) === 1;
$tabs_extra          = [];
if ( $vendor_analytics_on && class_exists( 'OC_Tracking' ) ) {
	$tabs_extra['analytics'] = [ 'label' => __( 'Analytics', 'owambe-connect-core' ), 'icon' => 'chart-bar' ];
}
if ( class_exists( 'OC_Reviews' ) ) {
	$tabs_extra['reviews'] = [ 'label' => __( 'Reviews', 'owambe-connect-core' ), 'icon' => 'star-half' ];
}
if ( $tabs_extra ) {
	// Insert after the profile fill-in steps (Business → Story → Contact →
	// Photos) and before Account — Analytics/Reviews are read-only results,
	// so they shouldn't interrupt the setup flow.
	$tabs = array_slice( $tabs, 0, 5, true ) + $tabs_extra + array_slice( $tabs, 5, null, true );
}
?>
<?php
// ─── Debug overlay (admin-only) ────────────────────────
// Only renders when ?oc_debug=1 AND the current user is an administrator.
// Vendors cannot trigger this — protects internal post IDs, role data, and
// the inline save trace from leaking to ordinary users.
$_oc_debug = isset( $_GET['oc_debug'] ) && '1' === $_GET['oc_debug'] && current_user_can( 'manage_options' );
if ( $_oc_debug ) {
	$current_user = wp_get_current_user();
	$all_posts    = get_posts( [
		'author'      => $current_user->ID,
		'post_type'   => OC_CPT,
		'post_status' => 'any',
		'numberposts' => -1,
	] );
	$last_save = get_transient( 'oc_last_save_dbg_' . $current_user->ID );
	if ( $last_save ) {
		// One-shot — clear after reading so refresh doesn't repeat the panel.
		delete_transient( 'oc_last_save_dbg_' . $current_user->ID );
	}
	?>
	<div style="margin:20px;padding:16px;background:#fff4d2;border:2px solid #b8860b;border-radius:8px;font-family:ui-monospace,Menlo,Monaco,monospace;font-size:12.5px;line-height:1.6;">
		<strong style="font-size:14px;">OC DASHBOARD DEBUG</strong> <span style="color:#666;">(only because <code>?oc_debug=1</code>)</span>
		<br><br>
		<strong>Current state</strong><br>
		User: <strong><?php echo esc_html( $current_user->user_login ); ?></strong> (ID=<?php echo (int) $current_user->ID; ?>, roles=<?php echo esc_html( implode( ',', (array) $current_user->roles ) ); ?>)<br>
		Editing post: <strong>#<?php echo (int) $vendor_post->ID; ?></strong> "<?php echo esc_html( $vendor_post->post_title ); ?>" (status=<?php echo esc_html( $vendor_post->post_status ); ?>, author=<?php echo (int) $vendor_post->post_author; ?>)<br>
		Cap check (<code><?php echo esc_html( OC_CAP_EDIT_OWN ); ?></code>) on this post: <strong style="color:<?php echo current_user_can( OC_CAP_EDIT_OWN, $vendor_post->ID ) ? '#2E7D5B' : '#B0354F'; ?>;"><?php echo current_user_can( OC_CAP_EDIT_OWN, $vendor_post->ID ) ? 'YES — can save' : 'NO — save will be blocked'; ?></strong><br>
		User has <code>oc_edit_own_vendor</code> in role: <strong><?php echo ! empty( $current_user->allcaps['oc_edit_own_vendor'] ) ? 'YES' : 'NO (← role is broken — re-activate plugin)'; ?></strong><br>
		Total vendor posts owned by user: <strong><?php echo count( $all_posts ); ?></strong>
		<?php if ( count( $all_posts ) > 1 ) : ?>
			<div style="margin-top:8px;padding:10px;background:#fbe5e6;border-left:4px solid #b0354f;">
				⚠️ <strong>Multiple vendor posts owned by this user (this also breaks things)</strong>:<br>
				<?php foreach ( $all_posts as $p ) :
					$is_current = $p->ID === $vendor_post->ID ? ' ← dashboard is using THIS' : '';
				?>
					• #<?php echo (int) $p->ID; ?> "<?php echo esc_html( $p->post_title ); ?>" (<?php echo esc_html( $p->post_status ); ?>)<?php echo esc_html( $is_current ); ?><br>
				<?php endforeach; ?>
				Fix: WP Admin → Vendors → delete duplicates.
			</div>
		<?php endif; ?>

		<?php if ( $last_save ) : ?>
			<br>
			<strong>Last save attempt</strong> (<?php echo esc_html( $last_save['timestamp'] ?? '' ); ?>)<br>
			Outcome: <strong style="color:<?php echo 'success' === ( $last_save['outcome'] ?? '' ) ? '#2E7D5B' : '#B0354F'; ?>;">
				<?php echo esc_html( $last_save['outcome'] ?? '?' ); ?>
			</strong><br>
			<?php if ( ! empty( $last_save['reason'] ) ) : ?>
				Reason: <strong><?php echo esc_html( $last_save['reason'] ); ?></strong><br>
			<?php endif; ?>
			Nonce valid: <strong><?php echo ! empty( $last_save['nonce_valid'] ) ? 'YES' : 'NO'; ?></strong><br>
			Cap OK: <strong><?php echo ! empty( $last_save['cap_ok'] ) ? 'YES' : 'NO'; ?></strong><br>
			Post ID from form: <strong>#<?php echo (int) ( $last_save['post_id_in_post'] ?? 0 ); ?></strong>
			(post_author=<?php echo (int) ( $last_save['post_author'] ?? 0 ); ?>, current_user=<?php echo (int) ( $last_save['current_user'] ?? 0 ); ?>)<br>
			Fields received from form: <strong><?php echo esc_html( implode( ', ', (array) ( $last_save['fields_received'] ?? [] ) ) ); ?></strong><br>
			Categories submitted: <strong><?php echo esc_html( implode( ',', (array) ( $last_save['category_ids'] ?? [] ) ) ); ?></strong>
			<?php if ( ! empty( $last_save['meta_after'] ) ) : ?>
				<br><br><strong>What's in the DB right after save:</strong>
				<pre style="background:#fff;border:1px solid #ddd;padding:10px;overflow:auto;font-size:12px;margin:6px 0 0;"><?php echo esc_html( wp_json_encode( [
					'meta'  => $last_save['meta_after'],
					'terms' => $last_save['terms_after'],
				], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
			<?php endif; ?>
		<?php else : ?>
			<br><em style="color:#666;">No save attempt recorded yet. Submit the form to see what happens.</em>
		<?php endif; ?>
	</div>
	<?php
}
?>
<?php
// Email-verification gate. When the vendor has a real post (not the
// placeholder-for-new-users state) and that post's _oc_email_verified flag
// is 0, we lock the dashboard: the inner content gets blurred and a single
// centered overlay tells them to check their inbox. They can still log out
// from the navbar — only the dashboard body is gated.
// Email verification is a NON-blocking reminder — NOT a hard lock. Vendors must
// be able to build their profile straight after signing up (dashboard-guided
// completion is the whole onboarding model). Verification is still enforced
// before the listing can go live: it appears in the profile-completion
// checklist (weight 0 — visible reminder only, does not affect percentage). Previously the
// whole dashboard was blurred + overlaid until verified, so any vendor whose
// verification email didn't arrive (deliverability/spam) was locked out and
// could never fill in their details — the cause of the "unfinished profiles".
$needs_verify     = $has_post && ! $email_verified;
$current_user_obj = wp_get_current_user();
$verify_email_to  = $current_user_obj instanceof WP_User ? $current_user_obj->user_email : '';
?>
<section class="oc-vd" id="oc-vendor-dashboard">
	<div class="oc-vd__container">
		<?php if ( $needs_verify ) : ?>
			<div class="oc-vd__alert oc-vd__alert--verify" role="region" aria-label="<?php esc_attr_e( 'Email verification', 'owambe-connect-core' ); ?>">
				<div class="oc-vd__verify-banner-text">
					<strong><?php esc_html_e( 'Verify your email to publish your listing.', 'owambe-connect-core' ); ?></strong>
					<?php
					if ( $verify_email_to ) {
						printf(
							/* translators: %s: user's email */
							esc_html__( ' We sent a link to %s — click it to confirm. You can keep building your profile meanwhile; it just can\'t go live until verified. Check your spam folder, and add info@owambeconnect.com to your contacts.', 'owambe-connect-core' ),
							'<strong>' . esc_html( $verify_email_to ) . '</strong>'
						);
					} else {
						esc_html_e( ' We sent you a confirmation link — click it to confirm. You can keep building your profile meanwhile; it just can\'t go live until verified.', 'owambe-connect-core' );
					}
					?>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="oc-vd__verify-banner-form">
					<input type="hidden" name="action" value="oc_email_verify_resend"/>
					<?php wp_nonce_field( 'oc_email_verify_resend', 'oc_resend_nonce' ); ?>
					<button type="submit" class="oc-vd__btn oc-vd__btn--mini"><?php esc_html_e( 'Resend email', 'owambe-connect-core' ); ?></button>
				</form>
			</div>
		<?php endif; ?>

		<nav class="oc-vd__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'owambe-connect-core' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'owambe-connect-core' ); ?></a>
			<span aria-hidden="true">/</span>
			<span class="oc-vd__crumb-current"><?php esc_html_e( 'Vendor Dashboard', 'owambe-connect-core' ); ?></span>
		</nav>

		<?php if ( $welcome ) : ?>
			<div class="oc-vd__alert oc-vd__alert--success" role="status" aria-live="polite">
				<strong><?php esc_html_e( 'Welcome to Owambe Connect!', 'owambe-connect-core' ); ?></strong>
				<?php esc_html_e( 'Your account is ready. Complete the checklist below — once it\'s in good shape, hit "Submit for review" and we\'ll get your listing live.', 'owambe-connect-core' ); ?>
			</div>
		<?php elseif ( $applied ) : ?>
			<div class="oc-vd__alert oc-vd__alert--success" role="status" aria-live="polite"><?php esc_html_e( 'Application received! We\'ll email you as soon as it\'s reviewed.', 'owambe-connect-core' ); ?></div>
		<?php endif; ?>
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
					<div class="oc-vd__avatar-wrap" data-oc-tab-jump="overview" title="<?php
						/* translators: %d: completion percent */
						echo esc_attr( sprintf( __( 'Profile %d%% complete — open Overview', 'owambe-connect-core' ), $pct ) );
					?>">
						<svg class="oc-vd__ring" viewBox="0 0 36 36" aria-hidden="true">
							<circle class="oc-vd__ring-bg" cx="18" cy="18" r="15.915"/>
							<circle class="oc-vd__ring-fg" cx="18" cy="18" r="15.915"
								stroke="<?php echo esc_attr( $completion['tier_color'] ); ?>"
								stroke-dasharray="<?php echo esc_attr( $pct ); ?> 100"/>
						</svg>
						<div class="oc-vd__avatar">
							<?php if ( $logo_url ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $values['business_name'] ); ?>"/>
							<?php else : ?>
								<span class="oc-vd__avatar-fallback" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
							<?php endif; ?>
							<?php if ( $is_featured ) : ?><span class="oc-vd__avatar-star" title="<?php esc_attr_e( 'Featured vendor', 'owambe-connect-core' ); ?>">★</span><?php endif; ?>
						</div>
					</div>
					<div class="oc-vd__profile-meta">
						<strong><?php echo esc_html( $values['business_name'] ); ?></strong>
						<small><?php echo esc_html( $user->user_email ); ?></small>
						<div class="oc-vd__profile-pills">
							<span class="oc-vd__status-pill" style="--c:<?php echo esc_attr( $status_color ); ?>"><?php echo esc_html( $status_label ); ?></span>
							<button type="button" class="oc-vd__cmp-pill" data-oc-tab-jump="overview" style="--c:<?php echo esc_attr( $completion['tier_color'] ); ?>">
								<?php /* translators: %d: completion percent */ printf( esc_html__( '%d%% complete', 'owambe-connect-core' ), $pct ); ?>
							</button>
						</div>
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
					<?php if ( OC_STATUS_APPROVED === $status ) : ?>
						<a class="oc-vd__menu-link" href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener">
							<span class="dashicons dashicons-external"></span>
							<span><?php esc_html_e( 'View public profile', 'owambe-connect-core' ); ?></span>
						</a>
						<?php
						// Share my business — copy + WhatsApp + email, all pointing
						// at the public profile URL (per-vendor OG tags make the
						// pasted link preview correctly for free).
						$share_url  = get_permalink( $id );
						$share_text = sprintf(
							/* translators: 1: business name, 2: profile URL */
							__( 'Check out %1$s on Owambe Connect: %2$s', 'owambe-connect-core' ),
							get_the_title( $id ),
							$share_url
						);
						?>
						<button type="button" class="oc-vd__menu-link" data-oc-copy-link="<?php echo esc_attr( $share_url ); ?>">
							<span class="dashicons dashicons-admin-page"></span>
							<span><?php esc_html_e( 'Copy profile link', 'owambe-connect-core' ); ?></span>
						</button>
						<a class="oc-vd__menu-link" href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( $share_text ) ); ?>" target="_blank" rel="noopener">
							<span class="dashicons dashicons-share"></span>
							<span><?php esc_html_e( 'Share on WhatsApp', 'owambe-connect-core' ); ?></span>
						</a>
						<a class="oc-vd__menu-link" href="<?php echo esc_url( 'mailto:?subject=' . rawurlencode( get_the_title( $id ) . ' — Owambe Connect' ) . '&body=' . rawurlencode( $share_text ) ); ?>">
							<span class="dashicons dashicons-email"></span>
							<span><?php esc_html_e( 'Share by email', 'owambe-connect-core' ); ?></span>
						</a>
						<a class="oc-vd__menu-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=oc_business_card&format=png' ), 'oc_business_card' ) ); ?>">
							<span class="dashicons dashicons-id-alt"></span>
							<span><?php esc_html_e( 'Business card (PNG)', 'owambe-connect-core' ); ?></span>
						</a>
						<a class="oc-vd__menu-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=oc_business_card&format=pdf' ), 'oc_business_card' ) ); ?>">
							<span class="dashicons dashicons-pdf"></span>
							<span><?php esc_html_e( 'Business card (PDF)', 'owambe-connect-core' ); ?></span>
						</a>
					<?php endif; ?>
					<a class="oc-vd__menu-link oc-vd__menu-link--logout" href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">
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
						<h1><?php esc_html_e( 'Overview', 'owambe-connect-core' ); ?></h1>
						<p><?php esc_html_e( 'Quick snapshot of your listing.', 'owambe-connect-core' ); ?></p>
					</header>

					<!-- Profile completion -->
					<div class="oc-vd__cmp oc-vd__cmp--<?php echo esc_attr( $completion['tier'] ); ?>" style="--c:<?php echo esc_attr( $completion['tier_color'] ); ?>">
						<div class="oc-vd__cmp-head">
							<div>
								<small><?php esc_html_e( 'Profile completion', 'owambe-connect-core' ); ?></small>
								<strong><?php echo (int) $pct; ?>%<span class="oc-vd__cmp-tier"><?php echo esc_html( $completion['tier_label'] ); ?></span></strong>
								<p>
									<?php if ( $pct >= 90 ) : ?>
										<?php esc_html_e( 'Your profile is in excellent shape — customers will love it.', 'owambe-connect-core' ); ?>
									<?php elseif ( $pct >= 70 ) : ?>
										<?php esc_html_e( 'You\'re ready for review. Add the items below to make your profile shine.', 'owambe-connect-core' ); ?>
									<?php elseif ( $pct >= 40 ) : ?>
										<?php esc_html_e( 'Almost there. Complete the highlighted items so we can review your profile faster.', 'owambe-connect-core' ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Your profile needs more details before we can review it. Tackle the items below.', 'owambe-connect-core' ); ?>
									<?php endif; ?>
								</p>
							</div>
							<div class="oc-vd__cmp-count">
								<strong><?php echo (int) $completion['completed_count']; ?> / <?php echo (int) $completion['total_count']; ?></strong>
								<small><?php esc_html_e( 'items done', 'owambe-connect-core' ); ?></small>
							</div>
						</div>

						<div class="oc-vd__cmp-bar" role="progressbar" aria-valuenow="<?php echo (int) $pct; ?>" aria-valuemin="0" aria-valuemax="100">
							<span style="width:<?php echo (int) $pct; ?>%; background:<?php echo esc_attr( $completion['tier_color'] ); ?>"></span>
						</div>

						<?php if ( $missing_items ) : ?>
							<div class="oc-vd__cmp-list-wrap" data-oc-collapsible>
								<button type="button" class="oc-vd__cmp-list-toggle" data-oc-collapsible-toggle aria-expanded="false">
									<?php
									/* translators: %d: number of items remaining */
									printf( esc_html__( '%d to do — tap to expand', 'owambe-connect-core' ), count( $missing_items ) );
									?>
									<span class="oc-vd__cmp-list-toggle-chev" aria-hidden="true">▾</span>
								</button>
								<ul class="oc-vd__cmp-list" data-oc-collapsible-body>
									<?php foreach ( $missing_items as $item ) : ?>
										<li>
											<button type="button" class="oc-vd__cmp-item"
												data-oc-tab-jump="<?php echo esc_attr( $item['tab'] ); ?>"
												<?php if ( ! empty( $item['focus'] ) ) : ?>data-oc-focus="<?php echo esc_attr( $item['focus'] ); ?>"<?php endif; ?>>
												<span class="oc-vd__cmp-dot" aria-hidden="true"></span>
												<span class="oc-vd__cmp-label"><?php echo esc_html( $item['label'] ); ?></span>
												<span class="oc-vd__cmp-arrow" aria-hidden="true">→</span>
											</button>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>

						<?php if ( $completed_items && count( $completed_items ) < count( $completion['checklist'] ) ) : ?>
							<details class="oc-vd__cmp-done">
								<summary><?php
									/* translators: %d: number completed */
									printf( esc_html__( '✓ %d items done', 'owambe-connect-core' ), count( $completed_items ) );
								?></summary>
								<ul>
									<?php foreach ( $completed_items as $item ) : ?>
										<li class="oc-vd__cmp-done-item"><?php echo esc_html( $item['label'] ); ?></li>
									<?php endforeach; ?>
								</ul>
							</details>
						<?php endif; ?>
					</div>

					<div class="oc-vd__status-card oc-vd__status-card--<?php echo esc_attr( ! $has_post ? 'new' : ( $is_draft ? 'draft' : str_replace( 'oc_', '', $status ) ) ); ?>">
						<div>
							<small><?php esc_html_e( 'Status', 'owambe-connect-core' ); ?></small>
							<strong><?php echo esc_html( ! $has_post ? __( 'New vendor — getting started', 'owambe-connect-core' ) : $status_label ); ?></strong>
							<?php if ( ! $has_post ) : ?>
								<p><?php esc_html_e( 'Welcome! Fill in your business details across the tabs, then click "Save changes". Your listing is created on first save — you\'ll be set up as a vendor automatically. Once everything\'s filled in (100% complete), the "Submit for review" button below will enable.', 'owambe-connect-core' ); ?></p>
							<?php elseif ( $is_draft ) : ?>
								<p><?php esc_html_e( 'Your listing is in draft. Finish the checklist above, then submit it for review when you\'re ready — admin won\'t see it until you do.', 'owambe-connect-core' ); ?></p>
							<?php elseif ( OC_STATUS_PENDING === $status ) : ?>
								<p><?php esc_html_e( 'Your listing is awaiting admin review. You can keep updating details below — your changes will be saved.', 'owambe-connect-core' ); ?></p>
							<?php elseif ( OC_STATUS_REJECTED === $status ) : ?>
								<p><?php esc_html_e( 'Updates needed before your listing goes live.', 'owambe-connect-core' ); ?>
									<?php if ( $rejection ) : ?><br><em><?php echo esc_html( $rejection ); ?></em><?php endif; ?>
								</p>
							<?php elseif ( OC_STATUS_APPROVED === $status ) : ?>
								<p><?php esc_html_e( 'Your listing is live and visible to customers.', 'owambe-connect-core' ); ?></p>
							<?php endif; ?>
						</div>
						<?php if ( $has_post && $is_draft ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
								<input type="hidden" name="action"  value="<?php echo esc_attr( OC_Dashboard::ACTION_SUBMIT ); ?>"/>
								<input type="hidden" name="post_id" value="<?php echo esc_attr( $id ); ?>"/>
								<?php wp_nonce_field( OC_Dashboard::ACTION_SUBMIT, 'oc_submit_nonce' ); ?>
								<button type="submit"
									class="oc-vd__btn oc-vd__btn--primary"
									<?php disabled( ! $completion['submittable'] ); ?>
									title="<?php echo esc_attr( $completion['submittable']
										? __( 'Send your listing to admin for review', 'owambe-connect-core' )
										: sprintf( __( 'Reach %d%% profile completion to submit', 'owambe-connect-core' ), (int) $completion['threshold'] )
									); ?>">
									<?php esc_html_e( 'Submit for review', 'owambe-connect-core' ); ?> →
								</button>
							</form>
						<?php elseif ( OC_STATUS_APPROVED === $status && $has_post ) : ?>
							<a class="oc-vd__btn oc-vd__btn--primary" href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View profile', 'owambe-connect-core' ); ?> →</a>
						<?php endif; ?>
					</div>

					<div class="oc-vd__stats">
						<div class="oc-vd__stat">
							<span class="dashicons dashicons-calendar-alt"></span>
							<div><strong><?php echo esc_html( date_i18n( 'M Y', $listed_ts ) ); ?></strong><small><?php esc_html_e( 'Member since', 'owambe-connect-core' ); ?></small></div>
						</div>
						<div class="oc-vd__stat">
							<span class="dashicons dashicons-format-gallery"></span>
							<div><strong><?php echo (int) count( $gallery_ids ); ?> / <?php echo (int) $gallery_max; ?></strong><small><?php esc_html_e( 'Gallery slots', 'owambe-connect-core' ); ?></small></div>
						</div>
						<div class="oc-vd__stat">
							<span class="dashicons dashicons-tag"></span>
							<div><strong><?php echo (int) count( $current_cat ); ?></strong><small><?php esc_html_e( 'Categories', 'owambe-connect-core' ); ?></small></div>
						</div>
						<div class="oc-vd__stat">
							<span class="dashicons dashicons-heart"></span>
							<div><strong><?php echo (int) count( $values['cultural_specialties'] ); ?></strong><small><?php esc_html_e( 'Cultural specialties', 'owambe-connect-core' ); ?></small></div>
						</div>
					</div>

					<div class="oc-vd__quick-actions">
						<button type="button" class="oc-vd__action" data-oc-tab-jump="business">
							<span class="dashicons dashicons-store"></span>
							<div><strong><?php esc_html_e( 'Business basics', 'owambe-connect-core' ); ?></strong><small><?php esc_html_e( 'Name, location, price tier', 'owambe-connect-core' ); ?></small></div>
						</button>
						<button type="button" class="oc-vd__action" data-oc-tab-jump="story">
							<span class="dashicons dashicons-editor-quote"></span>
							<div><strong><?php esc_html_e( 'Story &amp; tags', 'owambe-connect-core' ); ?></strong><small><?php esc_html_e( 'Bio, services, vendor tags', 'owambe-connect-core' ); ?></small></div>
						</button>
						<button type="button" class="oc-vd__action" data-oc-tab-jump="contact">
							<span class="dashicons dashicons-phone"></span>
							<div><strong><?php esc_html_e( 'Contact channels', 'owambe-connect-core' ); ?></strong><small><?php esc_html_e( 'WhatsApp, email, socials', 'owambe-connect-core' ); ?></small></div>
						</button>
						<button type="button" class="oc-vd__action" data-oc-tab-jump="photos">
							<span class="dashicons dashicons-camera"></span>
							<div><strong><?php esc_html_e( 'Update photos', 'owambe-connect-core' ); ?></strong><small><?php esc_html_e( 'Logo, banner, gallery', 'owambe-connect-core' ); ?></small></div>
						</button>
						<button type="button" class="oc-vd__action" data-oc-tab-jump="account">
							<span class="dashicons dashicons-lock"></span>
							<div><strong><?php esc_html_e( 'Change password', 'owambe-connect-core' ); ?></strong><small><?php esc_html_e( 'Account security', 'owambe-connect-core' ); ?></small></div>
						</button>
					</div>
				</section>

				<!-- ============== Listing + Photos (single form) ============== -->
				<form class="oc-vd__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" novalidate>
					<input type="hidden" name="action"  value="<?php echo esc_attr( OC_Dashboard::ACTION_UPDATE ); ?>"/>
					<input type="hidden" name="post_id" value="<?php echo esc_attr( $id ); ?>"/>
					<input type="hidden" name="_oc_tab" value="business"/>
					<?php if ( $_oc_debug ) : ?><input type="hidden" name="oc_debug" value="1"/><?php endif; ?>
					<?php wp_nonce_field( OC_Dashboard::ACTION_UPDATE, 'oc_update_nonce' ); ?>

					<!-- ─── Business tab ─── -->
					<section class="oc-vd__panel" data-oc-panel="business">
						<header class="oc-vd__panel-head">
							<h1><?php esc_html_e( 'Business basics', 'owambe-connect-core' ); ?></h1>
							<p><?php esc_html_e( 'Your business name, where you cover, and your price tier.', 'owambe-connect-core' ); ?></p>
						</header>

						<div class="oc-vd__card">
							<h2><?php esc_html_e( 'Who you are', 'owambe-connect-core' ); ?></h2>

							<div class="oc-vd__field" data-oc-field="business_name">
								<label for="d-name"><?php esc_html_e( 'Business name', 'owambe-connect-core' ); ?></label>
								<input id="d-name" type="text" name="business_name" value="<?php echo esc_attr( $values['business_name'] ); ?>"/>
								<span class="oc-vd__field-error" aria-live="polite"></span>
							</div>

							<div class="oc-vd__field" data-oc-field="categories">
								<label><?php esc_html_e( 'Categories', 'owambe-connect-core' ); ?>
									<small><?php esc_html_e( 'Pick the headline category (or two) clients will browse under.', 'owambe-connect-core' ); ?></small>
								</label>
								<div class="oc-vd__chips">
									<?php foreach ( $categories as $term ) : ?>
										<label class="oc-vd__chip">
											<input type="checkbox" name="categories[]" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( in_array( $term->term_id, $current_cat, true ) ); ?>/>
											<span><?php echo esc_html( $term->name ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
								<span class="oc-vd__field-error" aria-live="polite"></span>
							</div>

							<div class="oc-vd__field" data-oc-field="price">
								<label for="d-price"><?php esc_html_e( 'Price range', 'owambe-connect-core' ); ?></label>
								<select id="d-price" name="price_range">
									<option value=""><?php esc_html_e( '— Select —', 'owambe-connect-core' ); ?></option>
									<?php foreach ( $prices as $k => $label ) : ?>
										<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $values['price_range'], $k ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div class="oc-vd__card">
							<h2><?php esc_html_e( 'Where you cover', 'owambe-connect-core' ); ?></h2>

							<div class="oc-vd__field" data-oc-field="country">
								<label for="d-country"><?php esc_html_e( 'Country / region', 'owambe-connect-core' ); ?>
									<small><?php esc_html_e( 'England is selected by default — the cities below filter to match whichever country is picked.', 'owambe-connect-core' ); ?></small>
								</label>
								<?php
								// Default to "england" so new vendors see English cities
								// out of the box. Saved values still win; only a truly
								// empty meta falls through to this default.
								$effective_country = $values['location_country'] ?: 'england';
								?>
								<select id="d-country" name="location_country" data-oc-country-select>
									<?php foreach ( $country_options as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $effective_country, $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<span class="oc-vd__field-error" aria-live="polite"></span>
							</div>

							<?php if ( $region_options ) : ?>
							<div class="oc-vd__field" data-oc-field="regions" data-oc-regions-field>
								<label id="d-regions-label"><?php esc_html_e( 'Regions covered (England)', 'owambe-connect-core' ); ?>
									<small><?php esc_html_e( 'Start here — tick the region(s) you cover (a region includes every town within it). Then add specific cities below if you want to.', 'owambe-connect-core' ); ?></small>
								</label>
								<div id="d-regions" class="oc-vd__chips" role="group" aria-labelledby="d-regions-label">
									<?php foreach ( $region_options as $region ) : ?>
										<label class="oc-vd__chip">
											<input type="checkbox" name="location_regions[]" value="<?php echo esc_attr( $region ); ?>" <?php checked( in_array( $region, (array) $values['location_regions'], true ) ); ?>/>
											<span><?php echo esc_html( $region ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
							<?php endif; ?>

							<div class="oc-vd__field" data-oc-field="areas">
								<label id="d-areas-label"><?php esc_html_e( 'Cities / areas you cover', 'owambe-connect-core' ); ?>
									<small><?php esc_html_e( 'Pick a region above to reveal its cities — or tick "Show all cities" for the full list. You can also search by name.', 'owambe-connect-core' ); ?></small>
								</label>
								<div class="oc-vd__chip-actions">
									<button type="button" class="oc-vd__btn oc-vd__btn--mini" data-oc-areas-action="select"><?php esc_html_e( 'Select all cities', 'owambe-connect-core' ); ?></button>
									<button type="button" class="oc-vd__btn oc-vd__btn--mini" data-oc-areas-action="clear"><?php esc_html_e( 'Clear', 'owambe-connect-core' ); ?></button>
									<label class="oc-vd__chip-showall"><input type="checkbox" data-oc-areas-showall/> <?php esc_html_e( 'Show all cities (ignore region filter)', 'owambe-connect-core' ); ?></label>
								</div>
								<input
									type="search"
									class="oc-vd__areas-search"
									placeholder="<?php esc_attr_e( 'Filter cities — start typing (e.g. Don… for Doncaster)', 'owambe-connect-core' ); ?>"
									data-oc-areas-search
									autocomplete="off"
								/>
								<div id="d-areas" class="oc-vd__chips" role="group" aria-labelledby="d-areas-label" data-oc-areas-wrap>
									<?php foreach ( $cities_by_country as $country_slug => $country_cities ) : ?>
										<?php foreach ( $country_cities as $city ) :
											$chip_region = ( 'england' === $country_slug && function_exists( 'oc_region_for_city' ) ) ? oc_region_for_city( $city ) : ''; ?>
											<label class="oc-vd__chip" data-oc-area-chip data-country="<?php echo esc_attr( $country_slug ); ?>" data-region="<?php echo esc_attr( $chip_region ); ?>">
												<input type="checkbox" name="location_areas[]" value="<?php echo esc_attr( $city ); ?>" <?php checked( in_array( $city, $values['location_areas'], true ) ); ?>/>
												<span><?php echo esc_html( $city ); ?></span>
											</label>
										<?php endforeach; ?>
									<?php endforeach; ?>
									<p class="oc-vd__areas-hint" data-oc-areas-hint hidden><?php esc_html_e( 'Pick a region above to see its cities — or tick "Show all cities" for the full list. You can also search by name.', 'owambe-connect-core' ); ?></p>
								</div>
								<span class="oc-vd__field-error" aria-live="polite"></span>
							</div>
						</div>

						<div class="oc-vd__sticky-bar">
							<button type="submit" class="oc-vd__btn oc-vd__btn--primary"><?php esc_html_e( 'Save business basics', 'owambe-connect-core' ); ?></button>
						</div>
					</section>

					<!-- ─── Story & tags tab ─── -->
					<section class="oc-vd__panel" data-oc-panel="story">
						<header class="oc-vd__panel-head">
							<h1><?php esc_html_e( 'Your story &amp; tags', 'owambe-connect-core' ); ?></h1>
							<p><?php esc_html_e( 'Write the words clients will read and pick the tags that surface you in search.', 'owambe-connect-core' ); ?></p>
						</header>

						<div class="oc-vd__card">
							<h2><?php esc_html_e( 'About your business', 'owambe-connect-core' ); ?></h2>

							<div class="oc-vd__field" data-oc-field="bio">
								<label for="d-bio"><?php esc_html_e( 'About / bio', 'owambe-connect-core' ); ?></label>
								<textarea id="d-bio" name="bio" rows="4" placeholder="<?php esc_attr_e( '…tell clients who you are, how long you have been in the business, and why potential clients should trust you', 'owambe-connect-core' ); ?>"><?php echo esc_textarea( $values['bio'] ); ?></textarea>
								<small><?php esc_html_e( 'Tell clients who you are and why they should choose you.', 'owambe-connect-core' ); ?></small>
								<span class="oc-vd__field-error" aria-live="polite"></span>
							</div>

							<div class="oc-vd__field" data-oc-field="services">
								<label for="d-services"><?php esc_html_e( 'Services offered', 'owambe-connect-core' ); ?></label>
								<textarea id="d-services" name="services" rows="3" placeholder="<?php esc_attr_e( 'e.g. Bridal makeup, Gele artistry, Trial sessions — what do you offer and what makes it different?', 'owambe-connect-core' ); ?>"><?php echo esc_textarea( $values['services'] ); ?></textarea>
								<span class="oc-vd__field-error" aria-live="polite"></span>
							</div>

							<div class="oc-vd__row-2">
								<div class="oc-vd__field" data-oc-field="registered_business">
									<label id="d-regbiz-label"><?php esc_html_e( 'Is your business officially registered?', 'owambe-connect-core' ); ?></label>
									<div id="d-regbiz" class="oc-vd__radio-row" role="radiogroup" aria-labelledby="d-regbiz-label">
										<label class="oc-vd__radio"><input type="radio" name="registered_business" value="yes" <?php checked( $values['registered_business'], 'yes' ); ?>/> <?php esc_html_e( 'Yes', 'owambe-connect-core' ); ?></label>
										<label class="oc-vd__radio"><input type="radio" name="registered_business" value="no"  <?php checked( $values['registered_business'], 'no' ); ?>/> <?php esc_html_e( 'No', 'owambe-connect-core' ); ?></label>
									</div>
									<span class="oc-vd__field-error" aria-live="polite"></span>
								</div>
								<div class="oc-vd__field" data-oc-field="nigerian">
									<label id="d-nig-label"><?php esc_html_e( 'Do you specialise in Nigerian events?', 'owambe-connect-core' ); ?></label>
									<div id="d-nigerian" class="oc-vd__radio-row" role="radiogroup" aria-labelledby="d-nig-label">
										<label class="oc-vd__radio"><input type="radio" name="nigerian_specialty" value="yes" <?php checked( $values['nigerian_specialty'], 'yes' ); ?>/> <?php esc_html_e( 'Yes', 'owambe-connect-core' ); ?></label>
										<label class="oc-vd__radio"><input type="radio" name="nigerian_specialty" value="no"  <?php checked( $values['nigerian_specialty'], 'no' ); ?>/> <?php esc_html_e( 'No', 'owambe-connect-core' ); ?></label>
									</div>
								</div>
							</div>
						</div>

						<div class="oc-vd__card">
							<h2><?php esc_html_e( 'Cultural specialties', 'owambe-connect-core' ); ?></h2>

							<div class="oc-vd__field" data-oc-field="cultural">
								<label id="d-cultural-label">
									<small><?php esc_html_e( 'Pick all the cultures of events you serve.', 'owambe-connect-core' ); ?></small>
								</label>
								<div id="d-cultural" class="oc-vd__chips" role="group" aria-labelledby="d-cultural-label">
									<?php foreach ( $cultural_options as $key => $label ) : ?>
										<label class="oc-vd__chip">
											<input type="checkbox" name="cultural_specialties[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $values['cultural_specialties'], true ) ); ?>/>
											<span><?php echo esc_html( $label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
								<span class="oc-vd__field-error" aria-live="polite"></span>
							</div>
						</div>

						<div class="oc-vd__card">
							<div class="oc-vd__field" data-oc-field="tags" style="margin:0;">
								<div class="oc-vd__tag-head">
									<label id="d-tags-label" style="margin:0;">
										<span><?php esc_html_e( 'Vendor tags', 'owambe-connect-core' ); ?></span>
										<small><?php esc_html_e( 'Tick everything that describes your work — every group is always visible so you don\'t miss a relevant tag.', 'owambe-connect-core' ); ?></small>
									</label>
								</div>
								<div id="d-tags" class="oc-vd__tag-flat" data-oc-tag-accordion aria-labelledby="d-tags-label">
									<?php foreach ( $tag_groups as $group_label => $tag_list ) :
										$group_selected = array_intersect( $tag_list, $values['vendor_tags'] );
										$selected_count = count( $group_selected );
									?>
										<div class="oc-vd__tag-group<?php if ( $selected_count > 0 ) echo ' has-selections'; ?>" data-oc-tag-group>
											<div class="oc-vd__tag-group-head">
												<span class="oc-vd__tag-group-title"><?php echo esc_html( $group_label ); ?></span>
												<span class="oc-vd__tag-group-count" data-oc-tag-count>
													<span data-oc-tag-count-n><?php echo (int) $selected_count; ?></span>
													/ <?php echo (int) count( $tag_list ); ?>
												</span>
											</div>
											<div class="oc-vd__tag-group-body">
												<div class="oc-vd__chips">
													<?php foreach ( $tag_list as $tag ) : ?>
														<label class="oc-vd__chip">
															<input type="checkbox" name="vendor_tags[]" value="<?php echo esc_attr( $tag ); ?>" <?php checked( in_array( $tag, $values['vendor_tags'], true ) ); ?>/>
															<span><?php echo esc_html( $tag ); ?></span>
														</label>
													<?php endforeach; ?>
												</div>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
								<span class="oc-vd__field-error" aria-live="polite"></span>
							</div>
						</div>

						<div class="oc-vd__sticky-bar">
							<button type="submit" class="oc-vd__btn oc-vd__btn--primary"><?php esc_html_e( 'Save story &amp; tags', 'owambe-connect-core' ); ?></button>
						</div>
					</section>

					<!-- ─── Contact channels tab ─── -->
					<section class="oc-vd__panel" data-oc-panel="contact">
						<header class="oc-vd__panel-head">
							<h1><?php esc_html_e( 'Contact channels', 'owambe-connect-core' ); ?></h1>
							<p><?php esc_html_e( 'How clients reach you. WhatsApp is the most common — but offer at least one alternative for clients who don\'t use it.', 'owambe-connect-core' ); ?></p>
						</header>

						<div class="oc-vd__card">
							<h2><?php esc_html_e( 'Direct contact', 'owambe-connect-core' ); ?></h2>

							<div class="oc-vd__field" data-oc-field="whatsapp">
								<label for="d-wa-local"><?php esc_html_e( 'WhatsApp number', 'owambe-connect-core' ); ?></label>
								<div class="oc-vd__input-prefix">
									<span class="oc-vd__input-prefix-tag" aria-hidden="true">+44</span>
									<input id="d-wa-local" type="tel" name="whatsapp_local"
										value="<?php echo esc_attr( $values['whatsapp_local'] ); ?>"
										inputmode="numeric"
										autocomplete="tel-national"
										pattern="0?[0-9]{10}"
										maxlength="11"
										placeholder="7424688636"
										aria-describedby="d-wa-hint" />
								</div>
								<small id="d-wa-hint"><?php esc_html_e( 'Enter your UK mobile number — 10 or 11 digits. e.g. 07424 688636 or 7424 688636.', 'owambe-connect-core' ); ?></small>
								<span class="oc-vd__field-error" aria-live="polite"></span>
							</div>

							<div class="oc-vd__field" data-oc-field="public_email">
								<label for="d-pubmail"><?php esc_html_e( 'Public contact email', 'owambe-connect-core' ); ?></label>
								<input id="d-pubmail" type="email" name="public_email" value="<?php echo esc_attr( $values['public_email'] ); ?>" placeholder="hello@yourbusiness.co.uk"/>
								<small><?php esc_html_e( 'Shown on your public profile — can differ from your sign-in email.', 'owambe-connect-core' ); ?></small>
								<span class="oc-vd__field-error" aria-live="polite"></span>
							</div>
						</div>

						<div class="oc-vd__card">
							<h2><?php esc_html_e( 'Social &amp; web', 'owambe-connect-core' ); ?></h2>
							<div class="oc-vd__row-2">
								<div class="oc-vd__field"><label for="d-ig"><?php esc_html_e( 'Instagram handle', 'owambe-connect-core' ); ?></label>
									<input id="d-ig" type="text" name="instagram" value="<?php echo esc_attr( $values['instagram'] ); ?>" placeholder="@yourbusiness"/></div>
								<div class="oc-vd__field"><label for="d-fb"><?php esc_html_e( 'Facebook page', 'owambe-connect-core' ); ?></label>
									<input id="d-fb" type="text" name="facebook" value="<?php echo esc_attr( $values['facebook'] ); ?>"/></div>
							</div>
							<div class="oc-vd__field"><label for="d-web"><?php esc_html_e( 'Website', 'owambe-connect-core' ); ?></label>
								<input id="d-web" type="url" name="website" value="<?php echo esc_attr( $values['website'] ); ?>" placeholder="https://"/></div>
						</div>

						<div class="oc-vd__sticky-bar">
							<button type="submit" class="oc-vd__btn oc-vd__btn--primary"><?php esc_html_e( 'Save contact channels', 'owambe-connect-core' ); ?></button>
						</div>
					</section>

					<!-- Photos tab -->
					<section class="oc-vd__panel" data-oc-panel="photos">
						<header class="oc-vd__panel-head">
							<h1><?php esc_html_e( 'Photos & Gallery', 'owambe-connect-core' ); ?></h1>
							<p><?php esc_html_e( 'A great profile starts with great photos. Logo for the badge, display picture for your card and profile, gallery for the proof.', 'owambe-connect-core' ); ?></p>
						</header>

						<div class="oc-vd__photo-guide" role="note">
							<p><span class="oc-vd__photo-guide-icon" aria-hidden="true">💡</span><?php esc_html_e( 'To help your profile stand out beautifully on Owambe Connect, we recommend using a clean, sharp image for your display picture — ideally one showcasing your work, and without much written text or flyers on the image. Simple professional images tend to look more premium and help clients focus on your brand.', 'owambe-connect-core' ); ?></p>
							<p><?php esc_html_e( 'You can still include flyers, price lists, or promotional graphics within your gallery.', 'owambe-connect-core' ); ?></p>
						</div>

						<div class="oc-vd__card">
							<h2><?php esc_html_e( 'Logo & display picture', 'owambe-connect-core' ); ?></h2>
							<div class="oc-vd__row-2">
								<div class="oc-vd__field oc-vd__single-uploader"
									data-oc-single-uploader="logo"
									data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
									data-ajax-action="<?php echo esc_attr( OC_Dashboard::ACTION_GALLERY_UPLOAD ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( OC_Dashboard::ACTION_GALLERY_UPLOAD ) ); ?>"
									data-max-mb="2">
									<label><?php esc_html_e( 'Logo', 'owambe-connect-core' ); ?></label>
									<div class="oc-vd__img-preview oc-vd__img-preview--square<?php if ( $logo_id ) echo ' oc-vd__img-preview--has-image'; ?>" data-oc-preview="logo">
										<?php if ( $logo_id ) echo wp_get_attachment_image( $logo_id, 'thumbnail' );
										else echo '<span>' . esc_html__( 'No logo yet', 'owambe-connect-core' ) . '</span>'; ?>
									</div>
									<input type="file" accept="image/jpeg,image/png,image/webp" data-oc-single-uploader-input/>
									<div class="oc-vd__single-uploader-status" data-oc-single-uploader-status></div>
									<small class="oc-vd__img-hint"><?php esc_html_e( 'Square · 400 × 400 px recommended · Max 2 MB · Uploads instantly when you pick a file', 'owambe-connect-core' ); ?></small>
								</div>
								<div class="oc-vd__field oc-vd__single-uploader"
									data-oc-single-uploader="banner"
									data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
									data-ajax-action="<?php echo esc_attr( OC_Dashboard::ACTION_GALLERY_UPLOAD ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( OC_Dashboard::ACTION_GALLERY_UPLOAD ) ); ?>"
									data-max-mb="5">
									<label><?php esc_html_e( 'Display picture', 'owambe-connect-core' ); ?></label>
									<div class="oc-vd__img-preview oc-vd__img-preview--wide<?php if ( $banner_id ) echo ' oc-vd__img-preview--has-image'; ?>" data-oc-preview="banner">
										<?php if ( $banner_id ) echo wp_get_attachment_image( $banner_id, 'large' );
										else echo '<span>' . esc_html__( 'Display picture preview (16:9)', 'owambe-connect-core' ) . '</span>'; ?>
									</div>
									<input type="file" accept="image/jpeg,image/png,image/webp" data-oc-single-uploader-input/>
									<div class="oc-vd__single-uploader-status" data-oc-single-uploader-status></div>
									<small class="oc-vd__img-hint">
										<strong><?php esc_html_e( '1280 × 720 px recommended (16:9 ratio)', 'owambe-connect-core' ); ?></strong>
										<?php esc_html_e( ' · Max 5 MB · Uploads instantly. This preview is exactly how your photo appears on your listing card and profile.', 'owambe-connect-core' ); ?>
									</small>
								</div>
							</div>
						</div>

						<?php if ( $gallery_max > 0 ) :
							$slots_left = max( 0, $gallery_max - count( $gallery_ids ) ); ?>
							<div class="oc-vd__card">
								<h2><?php esc_html_e( 'Portfolio gallery', 'owambe-connect-core' ); ?>
									<small style="font-weight:400;color:#6B6361;font-size:13px;font-family:Inter,sans-serif;"><?php
										printf( esc_html__( '%1$d / %2$d used', 'owambe-connect-core' ), count( $gallery_ids ), $gallery_max );
									?></small>
								</h2>

								<?php if ( $gallery_ids ) : ?>
									<p class="oc-vd__gallery-help">
										<?php esc_html_e( 'Your gallery shows off your work. Tick "Remove" to drop one and free a slot. Your card and profile picture come from the Display picture above.', 'owambe-connect-core' ); ?>
									</p>
									<div class="oc-vd__gallery-grid">
										<?php foreach ( $gallery_ids as $gid ) : ?>
											<div class="oc-vd__gallery-item">
												<?php echo wp_get_attachment_image( $gid, 'thumbnail', false, [ 'class' => 'oc-vd__gallery-img' ] ); ?>
												<div class="oc-vd__gallery-tools">
													<label class="oc-vd__gallery-rm">
														<input type="checkbox" name="gallery_remove[]" value="<?php echo (int) $gid; ?>"/>
														<?php esc_html_e( 'Remove on save', 'owambe-connect-core' ); ?>
													</label>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<p style="color:#6B6361;font-style:italic;margin:8px 0 14px;"><?php esc_html_e( 'No gallery images yet. Add a few to bring your profile to life.', 'owambe-connect-core' ); ?></p>
								<?php endif; ?>

								<?php if ( $slots_left > 0 ) : ?>
									<div class="oc-vd__field oc-vd__uploader"
										data-oc-uploader
										data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
										data-ajax-action="<?php echo esc_attr( OC_Dashboard::ACTION_GALLERY_UPLOAD ); ?>"
										data-nonce="<?php echo esc_attr( wp_create_nonce( OC_Dashboard::ACTION_GALLERY_UPLOAD ) ); ?>"
										data-slots-left="<?php echo (int) $slots_left; ?>"
										data-max-mb="<?php echo (int) $gallery_mb; ?>">
										<label for="oc-gal-input"><?php esc_html_e( 'Add new images', 'owambe-connect-core' ); ?></label>
										<div class="oc-vd__uploader-rules">
											<?php
											printf(
												/* translators: 1: number of free slots, 2: max MB per file */
												esc_html__( '📸 Up to %1$d more photo(s). Each image must be %2$d MB or less. JPG / PNG / WEBP only.', 'owambe-connect-core' ),
												(int) $slots_left,
												(int) $gallery_mb
											);
											?>
										</div>
										<input id="oc-gal-input" type="file"
											accept="image/jpeg,image/png,image/webp"
											multiple
											data-oc-uploader-input/>
										<small><?php esc_html_e( 'Each image uploads instantly when you choose it.', 'owambe-connect-core' ); ?></small>
										<div class="oc-vd__uploader-errors" data-oc-uploader-errors hidden></div>
										<div class="oc-vd__uploader-queue" data-oc-uploader-queue></div>
									</div>
								<?php else : ?>
									<small style="color:#B8860B;"><?php esc_html_e( 'Gallery is full. Tick a "Remove" box and save to free a slot.', 'owambe-connect-core' ); ?></small>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<div class="oc-vd__sticky-bar">
							<button type="submit" class="oc-vd__btn oc-vd__btn--primary"><?php esc_html_e( 'Save photos', 'owambe-connect-core' ); ?></button>
						</div>
					</section>
				</form>

				<?php if ( $vendor_analytics_on && class_exists( 'OC_Tracking' ) ) : ?>
					<!-- ============== Analytics (Phase 2, read-only, OUTSIDE the master form) ============== -->
					<section class="oc-vd__panel" data-oc-panel="analytics">
						<?php
						// Selectable window: 7 / 30 / 90 days via ?an_range= (tab
						// survives the reload through the ?tab= query param).
						$an_range = isset( $_GET['an_range'] ) ? absint( $_GET['an_range'] ) : 30;
						if ( ! in_array( $an_range, [ 7, 30, 90 ], true ) ) {
							$an_range = 30;
						}
						?>
						<header class="oc-vd__panel-head">
							<h1><?php esc_html_e( 'Analytics', 'owambe-connect-core' ); ?></h1>
							<p><?php printf( esc_html__( 'How people find and contact you on Owambe Connect (last %d days).', 'owambe-connect-core' ), (int) $an_range ); ?></p>
						</header>
						<div class="oc-an-range" role="group" aria-label="<?php esc_attr_e( 'Analytics time range', 'owambe-connect-core' ); ?>">
							<?php foreach ( [ 7, 30, 90 ] as $an_opt ) :
								$an_url = add_query_arg( [ 'tab' => 'analytics', 'an_range' => $an_opt ] );
								?>
								<a class="oc-an-range__btn<?php echo $an_opt === $an_range ? ' is-active' : ''; ?>" href="<?php echo esc_url( $an_url ); ?>">
									<?php printf( esc_html__( '%d days', 'owambe-connect-core' ), (int) $an_opt ); ?>
								</a>
							<?php endforeach; ?>
						</div>
						<?php
						$an_counts = OC_Tracking::counts( $id, $an_range );
						$an_views  = (int) ( $an_counts['view'] ?? 0 );
						$an_clicks = array_sum( array_intersect_key( $an_counts, array_flip( [ 'click_whatsapp', 'click_email', 'click_instagram', 'click_facebook', 'click_website' ] ) ) );
						$an_series = OC_Tracking::timeseries( $an_range, $id );
						$an_max    = 1;
						foreach ( $an_series as $an_day ) {
							$an_max = max( $an_max, (int) $an_day['views'], (int) $an_day['clicks'] );
						}
						?>
						<div class="oc-vd__stats">
							<div class="oc-vd__stat"><strong><?php echo esc_html( number_format_i18n( $an_views ) ); ?></strong><span><?php esc_html_e( 'Profile views', 'owambe-connect-core' ); ?></span></div>
							<div class="oc-vd__stat"><strong><?php echo esc_html( number_format_i18n( $an_clicks ) ); ?></strong><span><?php esc_html_e( 'Contact clicks', 'owambe-connect-core' ); ?></span></div>
							<div class="oc-vd__stat"><strong><?php echo esc_html( $an_views > 0 ? round( ( $an_clicks / $an_views ) * 100, 1 ) . '%' : '—' ); ?></strong><span><?php esc_html_e( 'Click-through rate', 'owambe-connect-core' ); ?></span></div>
						</div>
						<div class="oc-vd__card">
							<h3 style="margin-top:0"><?php esc_html_e( 'Clicks by channel', 'owambe-connect-core' ); ?></h3>
							<?php
							$an_channels = [
								'click_whatsapp'  => __( 'WhatsApp',  'owambe-connect-core' ),
								'click_email'     => __( 'Email',     'owambe-connect-core' ),
								'click_instagram' => __( 'Instagram', 'owambe-connect-core' ),
								'click_facebook'  => __( 'Facebook',  'owambe-connect-core' ),
								'click_website'   => __( 'Website',   'owambe-connect-core' ),
							];
							$an_ch_max = 1;
							foreach ( $an_channels as $an_metric => $an_label ) {
								$an_ch_max = max( $an_ch_max, (int) ( $an_counts[ $an_metric ] ?? 0 ) );
							}
							?>
							<div class="oc-an-channels">
								<?php foreach ( $an_channels as $an_metric => $an_label ) :
									$an_val = (int) ( $an_counts[ $an_metric ] ?? 0 );
									$an_pct = round( ( $an_val / $an_ch_max ) * 100 );
									?>
									<div class="oc-an-channels__row">
										<span class="oc-an-channels__label"><?php echo esc_html( $an_label ); ?></span>
										<span class="oc-an-channels__track"><span class="oc-an-channels__fill" style="width:<?php echo esc_attr( $an_pct ); ?>%"></span></span>
										<span class="oc-an-channels__val"><?php echo esc_html( number_format_i18n( $an_val ) ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="oc-vd__card">
							<h3 style="margin-top:0"><?php esc_html_e( 'Daily activity', 'owambe-connect-core' ); ?></h3>
							<div class="oc-an-mini" role="img" aria-label="<?php esc_attr_e( 'Daily views and clicks bar chart', 'owambe-connect-core' ); ?>">
								<?php foreach ( $an_series as $an_date => $an_day ) : ?>
									<div class="oc-an-mini__col" title="<?php echo esc_attr( $an_date . ' — ' . sprintf( __( '%1$d views, %2$d clicks', 'owambe-connect-core' ), (int) $an_day['views'], (int) $an_day['clicks'] ) ); ?>">
										<span class="oc-an-mini__bar oc-an-mini__bar--views" style="height:<?php echo esc_attr( round( ( (int) $an_day['views'] / $an_max ) * 100 ) ); ?>%"></span>
										<span class="oc-an-mini__bar oc-an-mini__bar--clicks" style="height:<?php echo esc_attr( round( ( (int) $an_day['clicks'] / $an_max ) * 100 ) ); ?>%"></span>
									</div>
								<?php endforeach; ?>
							</div>
							<p class="oc-vd__hint" style="margin-bottom:0">
								<span class="oc-an-mini__key oc-an-mini__key--views"></span> <?php esc_html_e( 'Views', 'owambe-connect-core' ); ?>
								&nbsp;&nbsp;<span class="oc-an-mini__key oc-an-mini__key--clicks"></span> <?php esc_html_e( 'Clicks', 'owambe-connect-core' ); ?>
								&nbsp;·&nbsp;<?php esc_html_e( 'Clicks are counted when someone taps a contact button — what happens inside WhatsApp afterwards can\'t be tracked.', 'owambe-connect-core' ); ?>
							</p>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( class_exists( 'OC_Reviews' ) ) : ?>
					<!-- ============== Reviews (Phase 2, read-only, OUTSIDE the master form) ============== -->
					<section class="oc-vd__panel" data-oc-panel="reviews">
						<header class="oc-vd__panel-head">
							<h1><?php esc_html_e( 'Reviews', 'owambe-connect-core' ); ?></h1>
							<p><?php esc_html_e( 'What clients say about you. Reviews are approved by the Owambe Connect team before going live.', 'owambe-connect-core' ); ?></p>
						</header>
						<?php
						$rv_count = (int) get_post_meta( $id, '_oc_rating_count', true );
						$rv_avg   = (float) get_post_meta( $id, '_oc_rating_avg', true );
						$rv_list  = OC_Reviews::for_vendor( $id, 20 );
						?>
						<?php if ( $rv_count > 0 ) : ?>
							<div class="oc-vd__stats">
								<div class="oc-vd__stat"><strong><?php echo esc_html( number_format_i18n( $rv_avg, 1 ) ); ?></strong><span><?php esc_html_e( 'Average rating', 'owambe-connect-core' ); ?></span></div>
								<div class="oc-vd__stat"><strong><?php echo esc_html( number_format_i18n( $rv_count ) ); ?></strong><span><?php esc_html_e( 'Approved reviews', 'owambe-connect-core' ); ?></span></div>
							</div>
						<?php endif; ?>
						<?php if ( $rv_list ) : ?>
							<div class="oc-vd__card">
								<?php foreach ( $rv_list as $rv ) : $rv_author = get_user_by( 'id', $rv->post_author ); ?>
									<div class="oc-vd__review-row" style="padding:12px 0;border-bottom:1px solid #F0EAE0;">
										<?php echo wp_kses_post( OC_Reviews::stars_html( (int) get_post_meta( $rv->ID, '_oc_review_rating', true ) ) ); ?>
										<strong style="margin-left:6px"><?php echo esc_html( $rv_author ? $rv_author->display_name : __( 'A client', 'owambe-connect-core' ) ); ?></strong>
										<span style="color:#9a938f;font-size:.85em;margin-left:6px"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $rv->post_date ) ) ); ?></span>
										<p style="margin:6px 0 0;color:#444"><?php echo esc_html( $rv->post_content ); ?></p>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div class="oc-vd__card">
								<p style="margin:0;color:#6B6361"><?php esc_html_e( 'No reviews yet. Share your review link with happy clients — reviews build trust and bookings.', 'owambe-connect-core' ); ?></p>
							</div>
						<?php endif; ?>
						<?php if ( OC_STATUS_APPROVED === $status ) : ?>
							<div class="oc-vd__card">
								<h3 style="margin-top:0"><?php esc_html_e( 'Request a review', 'owambe-connect-core' ); ?></h3>
								<p style="color:#6B6361"><?php esc_html_e( 'Send this link to past clients — they sign in with Google and leave a review, and it appears here once approved.', 'owambe-connect-core' ); ?></p>
								<?php $rv_link = get_permalink( $id ) . '#reviews'; ?>
								<button type="button" class="oc-vd__btn oc-vd__btn--primary" data-oc-copy-link="<?php echo esc_attr( $rv_link ); ?>"><?php esc_html_e( 'Copy review link', 'owambe-connect-core' ); ?></button>
								<a class="oc-vd__btn" style="margin-left:8px" target="_blank" rel="noopener noreferrer" href="https://wa.me/?text=<?php echo rawurlencode( __( 'Thanks for celebrating with us! Would you mind leaving a quick review of my services on Owambe Connect? ', 'owambe-connect-core' ) . $rv_link ); ?>"><?php esc_html_e( 'Share via WhatsApp', 'owambe-connect-core' ); ?></a>
								<a class="oc-vd__btn" style="margin-left:8px" href="mailto:?subject=<?php echo rawurlencode( __( 'Would you leave me a quick review?', 'owambe-connect-core' ) ); ?>&body=<?php echo rawurlencode( __( 'Thanks for celebrating with us! Would you mind leaving a quick review of my services on Owambe Connect? ', 'owambe-connect-core' ) . $rv_link ); ?>"><?php esc_html_e( 'Share by email', 'owambe-connect-core' ); ?></a>
							</div>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<!-- ============== Account ============== -->
				<section class="oc-vd__panel" data-oc-panel="account">
					<header class="oc-vd__panel-head">
						<h1><?php esc_html_e( 'Account', 'owambe-connect-core' ); ?></h1>
						<p><?php esc_html_e( 'Manage your sign-in details.', 'owambe-connect-core' ); ?></p>
					</header>

					<?php if ( ! $email_verified ) : ?>
						<div class="oc-vd__verify-cta" role="region" aria-labelledby="oc-verify-h">
							<div class="oc-vd__verify-cta-body">
								<h2 id="oc-verify-h"><?php esc_html_e( 'Verify your email address', 'owambe-connect-core' ); ?></h2>
								<p><?php
									printf(
										/* translators: %s: user's email */
										esc_html__( 'We sent a confirmation link to %s. Click it to complete your sign-up. Your listing can\'t go live until your email is verified.', 'owambe-connect-core' ),
										'<strong>' . esc_html( $user->user_email ) . '</strong>'
									);
								?></p>
							</div>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="oc_email_verify_resend"/>
								<?php wp_nonce_field( 'oc_email_verify_resend', 'oc_resend_nonce' ); ?>
								<button type="submit" class="oc-vd__btn oc-vd__btn--primary"><?php esc_html_e( 'Resend verification email', 'owambe-connect-core' ); ?></button>
							</form>
						</div>
					<?php endif; ?>

					<?php if ( $vendor_number ) : ?>
						<div class="oc-vd__card oc-vd__card--vendor-id">
							<h2><?php esc_html_e( 'Your vendor registration number', 'owambe-connect-core' ); ?></h2>
							<div class="oc-vd__vendor-id"><?php echo esc_html( $vendor_number ); ?></div>
							<small><?php esc_html_e( 'Quote this when you contact support. It\'s also shown on your public profile.', 'owambe-connect-core' ); ?></small>
						</div>
					<?php endif; ?>

					<div class="oc-vd__card">
						<h2><?php esc_html_e( 'Login email', 'owambe-connect-core' ); ?>
							<?php if ( $email_verified ) : ?><span class="oc-vd__verified-pill" title="<?php esc_attr_e( 'Verified', 'owambe-connect-core' ); ?>">✓ <?php esc_html_e( 'Verified', 'owambe-connect-core' ); ?></span><?php endif; ?>
						</h2>
						<p style="margin:0;color:#1F1B1A;font-weight:500;"><?php echo esc_html( $user->user_email ); ?></p>
						<small style="color:#6B6361;"><?php esc_html_e( 'Contact support if you need to change this.', 'owambe-connect-core' ); ?></small>
					</div>

					<form class="oc-vd__form-msg" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( OC_Dashboard::ACTION_SUPPORT ); ?>"/>
						<?php wp_nonce_field( OC_Dashboard::ACTION_SUPPORT, 'oc_support_nonce' ); ?>
						<div class="oc-vd__card">
							<h2><?php esc_html_e( 'Contact support', 'owambe-connect-core' ); ?></h2>
							<p style="margin:0 0 10px;color:#6B6361;font-size:13px;"><?php esc_html_e( 'Stuck, blocked, or need help with your listing? Drop us a note — we usually reply within 24 hours.', 'owambe-connect-core' ); ?></p>
							<div class="oc-vd__field">
								<label for="oc-supp-subject"><?php esc_html_e( 'Subject', 'owambe-connect-core' ); ?></label>
								<input id="oc-supp-subject" type="text" name="subject" required maxlength="120" placeholder="<?php esc_attr_e( 'What do you need help with?', 'owambe-connect-core' ); ?>"/>
							</div>
							<div class="oc-vd__field">
								<label for="oc-supp-msg"><?php esc_html_e( 'Message', 'owambe-connect-core' ); ?></label>
								<textarea id="oc-supp-msg" name="message" rows="4" required minlength="10" placeholder="<?php esc_attr_e( 'Share as much detail as you can…', 'owambe-connect-core' ); ?>"></textarea>
							</div>
							<div class="oc-honeypot" aria-hidden="true" style="position:absolute;left:-9999px;">
								<label><?php esc_html_e( 'Leave this field empty', 'owambe-connect-core' ); ?><input type="text" name="oc_hp" tabindex="-1" autocomplete="off"/></label>
							</div>
							<div class="oc-vd__sticky-bar oc-vd__sticky-bar--inline">
								<button type="submit" class="oc-vd__btn oc-vd__btn--primary"><?php esc_html_e( 'Send to support', 'owambe-connect-core' ); ?></button>
							</div>
						</div>
					</form>

					<form class="oc-vd__form-msg" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( OC_Dashboard::ACTION_FEEDBACK ); ?>"/>
						<?php wp_nonce_field( OC_Dashboard::ACTION_FEEDBACK, 'oc_feedback_nonce' ); ?>
						<div class="oc-vd__card">
							<h2><?php esc_html_e( 'Make a suggestion', 'owambe-connect-core' ); ?></h2>
							<p style="margin:0 0 10px;color:#6B6361;font-size:13px;"><?php esc_html_e( 'Have an idea to make Owambe Connect better? Spot something that could be smoother? Tell us.', 'owambe-connect-core' ); ?></p>
							<div class="oc-vd__field">
								<label for="oc-fb-topic"><?php esc_html_e( 'Topic', 'owambe-connect-core' ); ?></label>
								<select id="oc-fb-topic" name="topic" required>
									<option value=""><?php esc_html_e( '— Pick a topic —', 'owambe-connect-core' ); ?></option>
									<option value="Dashboard improvement"><?php esc_html_e( 'Dashboard improvement', 'owambe-connect-core' ); ?></option>
									<option value="Public site / search"><?php esc_html_e( 'Public site / search', 'owambe-connect-core' ); ?></option>
									<option value="Listing / profile field"><?php esc_html_e( 'Listing / profile field', 'owambe-connect-core' ); ?></option>
									<option value="Photos / gallery"><?php esc_html_e( 'Photos / gallery', 'owambe-connect-core' ); ?></option>
									<option value="New feature idea"><?php esc_html_e( 'New feature idea', 'owambe-connect-core' ); ?></option>
									<option value="Bug report"><?php esc_html_e( 'Bug report', 'owambe-connect-core' ); ?></option>
									<option value="Other"><?php esc_html_e( 'Other', 'owambe-connect-core' ); ?></option>
								</select>
							</div>
							<div class="oc-vd__field">
								<label for="oc-fb-msg"><?php esc_html_e( 'Your suggestion', 'owambe-connect-core' ); ?></label>
								<textarea id="oc-fb-msg" name="message" rows="4" required minlength="10" placeholder="<?php esc_attr_e( 'Tell us what you\'d change and why…', 'owambe-connect-core' ); ?>"></textarea>
							</div>
							<div class="oc-honeypot" aria-hidden="true" style="position:absolute;left:-9999px;">
								<label><?php esc_html_e( 'Leave this field empty', 'owambe-connect-core' ); ?><input type="text" name="oc_hp" tabindex="-1" autocomplete="off"/></label>
							</div>
							<div class="oc-vd__sticky-bar oc-vd__sticky-bar--inline">
								<button type="submit" class="oc-vd__btn oc-vd__btn--primary"><?php esc_html_e( 'Send suggestion', 'owambe-connect-core' ); ?></button>
							</div>
						</div>
					</form>

					<form class="oc-vd__form-pw" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( OC_Dashboard::ACTION_PASSWORD ); ?>"/>
						<?php wp_nonce_field( OC_Dashboard::ACTION_PASSWORD, 'oc_password_nonce' ); ?>

						<div class="oc-vd__card">
							<h2><?php esc_html_e( 'Change password', 'owambe-connect-core' ); ?></h2>
							<div class="oc-vd__row-3">
								<div class="oc-vd__field oc-vd__field--pw"><label for="d-cur"><?php esc_html_e( 'Current', 'owambe-connect-core' ); ?></label>
									<input id="d-cur" type="password" name="current_password" autocomplete="current-password" required/>
									<button type="button" class="oc-vd__pw-toggle" data-oc-pw-toggle="d-cur" aria-label="<?php esc_attr_e( 'Show password', 'owambe-connect-core' ); ?>">
										<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
									</button></div>
								<div class="oc-vd__field oc-vd__field--pw"><label for="d-new"><?php esc_html_e( 'New', 'owambe-connect-core' ); ?></label>
									<input id="d-new" type="password" name="new_password" minlength="8" autocomplete="new-password" required/>
									<button type="button" class="oc-vd__pw-toggle" data-oc-pw-toggle="d-new" aria-label="<?php esc_attr_e( 'Show password', 'owambe-connect-core' ); ?>">
										<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
									</button></div>
								<div class="oc-vd__field oc-vd__field--pw"><label for="d-conf"><?php esc_html_e( 'Confirm', 'owambe-connect-core' ); ?></label>
									<input id="d-conf" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required/>
									<button type="button" class="oc-vd__pw-toggle" data-oc-pw-toggle="d-conf" aria-label="<?php esc_attr_e( 'Show password', 'owambe-connect-core' ); ?>">
										<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
									</button></div>
							</div>
							<div class="oc-vd__sticky-bar oc-vd__sticky-bar--inline">
								<button type="submit" class="oc-vd__btn oc-vd__btn--primary"><?php esc_html_e( 'Update password', 'owambe-connect-core' ); ?></button>
							</div>
						</div>
					</form>
				</section>

			</main>
		</div>
	</div>
</section>

<script>
(function () {
	// Scope to the vendor dashboard's own root by ID — the client dashboard
	// also carries class .oc-vd, so a bare .oc-vd selector would bind the wrong
	// root when both render on one page (tabs would die).
	var root      = document.getElementById('oc-vendor-dashboard');
	if (!root) { return; }
	var menuBtns  = root.querySelectorAll('[data-oc-tab]');
	var panels    = root.querySelectorAll('[data-oc-panel]');
	var jumps     = root.querySelectorAll('[data-oc-tab-jump]');
	var validTabs = Array.prototype.map.call(menuBtns, function (b) { return b.dataset.ocTab; });

	// Live state — kept in sync with any hidden _oc_tab inputs inside forms.
	var currentTab = 'overview';
	var tabInputs  = root.querySelectorAll('input[name="_oc_tab"]');

	function syncTabInputs(tab) {
		tabInputs.forEach(function (i) { i.value = tab; });
	}

	function setTab(tab, push, focusId) {
		if (validTabs.indexOf(tab) === -1) tab = validTabs[0];
		currentTab = tab;
		menuBtns.forEach(function (b) { b.classList.toggle('is-active', b.dataset.ocTab === tab); b.setAttribute('aria-selected', b.dataset.ocTab === tab ? 'true' : 'false'); });
		panels.forEach(function (p) { p.classList.toggle('is-active', p.dataset.ocPanel === tab); });
		syncTabInputs(tab);
		if (push) {
			// Use a query param so the tab survives form POST redirects (hashes aren't sent in HTTP).
			var url = new URL(window.location.href);
			url.searchParams.set('tab', tab);
			url.hash = ''; // drop legacy hash if present
			history.replaceState(null, '', url.toString());
		}
		// Scroll the new tab's panel head into view when switching. Previously
		// this only fired on mobile, which meant a desktop user who'd scrolled
		// halfway through Tab A would land mid-page on Tab B with the new
		// content above the fold — forcing a manual scroll-to-top every time.
		// Now: any time the panel head isn't already visible at the top of
		// the viewport, glide back up. If it's already in view, do nothing.
		var head = root.querySelector('[data-oc-panel="' + tab + '"] .oc-vd__panel-head');
		if (head) {
			var rect = head.getBoundingClientRect();
			if (rect.top < 0 || rect.top > 120) {
				// On desktop leave a small offset so any sticky header doesn't
				// cover the panel title. Mobile keeps block: 'start' for the
				// tightest possible alignment with the sticky tab bar.
				if (window.innerWidth >= 900) {
					var headerOffset = 80;
					var y = window.pageYOffset + rect.top - headerOffset;
					window.scrollTo({ top: y, behavior: 'smooth' });
				} else {
					head.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
			}
		}
		if (focusId) {
			var el = document.getElementById(focusId);
			if (el) {
				setTimeout(function () {
					el.scrollIntoView({ behavior: 'smooth', block: 'center' });
					try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); }
					el.classList.add('oc-vd__field--flash');
					setTimeout(function () { el.classList.remove('oc-vd__field--flash'); }, 1800);
				}, 280);
			}
		}
	}

	menuBtns.forEach(function (b) { b.addEventListener('click', function () { setTab(b.dataset.ocTab, true); }); });
	// Use delegation so dynamically rebuilt completion-checklist buttons work without re-wiring.
	root.addEventListener('click', function (e) {
		var b = e.target.closest('[data-oc-tab-jump]');
		if (b) setTab(b.dataset.ocTabJump, true, b.dataset.ocFocus || '');
	});

	// Resolve initial tab: query param wins, then legacy hash, then default.
	var initialQs   = new URLSearchParams(window.location.search);
	var initialTab  = initialQs.get('tab') || (window.location.hash || '').replace('#', '') || 'overview';
	setTab(initialTab, false);
	window.addEventListener('hashchange', function () { setTab((window.location.hash || '').replace('#', '') || currentTab, false); });

	// ─────────────── Toast notifications ───────────────
	// Render any oc_notice / oc_error from the URL as a slide-in toast, then strip
	// them from the URL so refresh doesn't re-show. Stacked, auto-dismiss, close btn.
	function showToast(message, type) {
		if (!message) return;
		var holder = document.querySelector('.oc-toast-stack');
		if (!holder) {
			holder = document.createElement('div');
			holder.className = 'oc-toast-stack';
			document.body.appendChild(holder);
		}
		var toast = document.createElement('div');
		toast.className = 'oc-toast oc-toast--' + (type || 'info');
		toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
		toast.innerHTML =
			'<span class="oc-toast__icon" aria-hidden="true">' + (type === 'error' ? '⚠️' : '✓') + '</span>' +
			'<span class="oc-toast__msg"></span>' +
			'<button type="button" class="oc-toast__close" aria-label="Dismiss">×</button>';
		toast.querySelector('.oc-toast__msg').textContent = message;
		holder.appendChild(toast);
		requestAnimationFrame(function () { toast.classList.add('is-in'); });
		var dismiss = function () {
			toast.classList.remove('is-in');
			setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 250);
		};
		toast.querySelector('.oc-toast__close').addEventListener('click', dismiss);
		setTimeout(dismiss, type === 'error' ? 7000 : 4000);
	}

	(function showInitialToasts() {
		var notice = initialQs.get('oc_notice');
		var error  = initialQs.get('oc_error');
		if (notice) showToast(decodeURIComponent(notice.replace(/\+/g, ' ')), 'success');
		if (error)  showToast(decodeURIComponent(error.replace(/\+/g,  ' ')), 'error');
		if (notice || error) {
			var u = new URL(window.location.href);
			u.searchParams.delete('oc_notice');
			u.searchParams.delete('oc_error');
			history.replaceState(null, '', u.toString());
		}
	})();

	// Live image preview for logo and banner file inputs.
	root.querySelectorAll('[data-oc-file-preview]').forEach(function (input) {
		input.addEventListener('change', function () {
			var file = input.files && input.files[0];
			if (!file || !file.type.startsWith('image/')) return;
			var key = input.getAttribute('data-oc-file-preview');
			var preview = root.querySelector('[data-oc-preview="' + key + '"]');
			if (!preview) return;
			var reader = new FileReader();
			reader.onload = function (e) {
				preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="width:100%;height:100%;object-fit:cover;display:block;">';
				preview.classList.add('oc-vd__img-preview--has-image');
			};
			reader.readAsDataURL(file);
		});
	});

	// ─────────────── Country → City filtering ───────────────
	// When the vendor picks a country, hide the city chips that don't
	// belong to it AND uncheck them so submitted data matches what's
	// visually selected. Reversing the country to "" shows all again.
	// Lives in both the vendor dashboard AND the admin form — same JS,
	// same data-attribute contract, single source of truth.
	(function () {
		var country = root.querySelector('[data-oc-country-select]');
		var wrap    = root.querySelector('[data-oc-areas-wrap]');
		if (!country || !wrap) return;
		var chips  = wrap.querySelectorAll('[data-oc-area-chip]');
		var hint   = wrap.querySelector('[data-oc-areas-hint]');
		var search = root.querySelector('[data-oc-areas-search]');
		// Regions are England-only — show the field only when England is picked,
		// and uncheck region boxes when it's hidden so submitted data is clean.
		var regionsField = root.querySelector('[data-oc-regions-field]');
		var showAll      = root.querySelector('[data-oc-areas-showall]');

		function toggleRegions(selected) {
			if (!regionsField) return;
			var show = selected === 'england';
			regionsField.style.display = show ? '' : 'none';
			if (!show) {
				regionsField.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = false; });
			}
		}

		function selectedRegions() {
			var set = {};
			if (regionsField) {
				regionsField.querySelectorAll('input[type="checkbox"]:checked').forEach(function (cb) { set[ cb.value ] = true; });
			}
			return set;
		}

		// Single source of truth for city-chip visibility. A chip shows only if it
		// passes EVERY active filter — country, region (England only) and text
		// search. Region is a VIEW filter: it narrows the list to the ticked
		// region(s) but never unchecks a city (already-checked cities always stay
		// visible), so a vendor can cover cities across several regions without
		// losing selections. Switching country is the only action that unchecks
		// (the old country's cities no longer apply).
		function applyFilters(opts) {
			opts = opts || {};
			var sel        = country.value;
			var q          = search ? ( search.value || '' ).trim().toLowerCase() : '';
			var regs       = selectedRegions();
			var regionKeys = Object.keys( regs );
			var showingAll = !!( showAll && showAll.checked );
			var isEngland  = sel === 'england';
			var anyVisible = false;
			chips.forEach(function (chip) {
				var cb      = chip.querySelector('input[type="checkbox"]');
				var checked = !!( cb && cb.checked );

				// 1) Country.
				var ok = !sel || chip.getAttribute('data-country') === sel;
				if (!ok && checked && opts.clearHiddenCountry) { cb.checked = false; checked = false; }

				if (ok) {
					if (q) {
						// Typing searches across the whole country (bypasses region gating).
						ok = ( chip.textContent || '' ).toLowerCase().indexOf( q ) !== -1;
					} else if (isEngland && ! showingAll) {
						// England cities are gated (regions-first): the long list stays
						// hidden until the vendor picks a region, ticks "Show all cities",
						// or the city is already checked. A ticked region reveals its
						// cities; "Show all cities" reveals every city.
						ok = regionKeys.length > 0 ? ( !!regs[ chip.getAttribute('data-region') ] || checked ) : checked;
					}
				}

				chip.style.display = ok ? '' : 'none';
				if (ok) anyVisible = true;
			});
			if (hint) hint.hidden = anyVisible;
		}

		country.addEventListener('change', function () {
			toggleRegions(country.value);
			applyFilters({ clearHiddenCountry: true });
		});
		if (search) search.addEventListener('input', function () { applyFilters(); });
		if (regionsField) regionsField.addEventListener('change', function () { applyFilters(); });
		if (showAll) showAll.addEventListener('change', function () { applyFilters(); });

		// Initial render — preserve saved selections (no unchecking).
		toggleRegions(country.value);
		applyFilters();

		// ─── Select all / Clear ──────────────────────────────
		// "Select all cities" = nationwide: tick EVERY city for the selected
		// country (not just the visible ones) in one click. Checked cities always
		// stay visible, so they all appear. "Clear" unticks every city and resets
		// the "Show all cities" toggle.
		root.querySelectorAll('[data-oc-areas-action]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var select = btn.getAttribute('data-oc-areas-action') === 'select';
				var sel    = country.value;
				chips.forEach(function (chip) {
					var cb = chip.querySelector('input[type="checkbox"]');
					if (!cb) return;
					if (select) {
						if (!sel || chip.getAttribute('data-country') === sel) cb.checked = true;
					} else {
						cb.checked = false;
					}
				});
				if (!select && showAll) showAll.checked = false;
				applyFilters();
			});
		});
	})();

	// ─────────────── +44 WhatsApp input ───────────────
	// Strip non-digits and any leading 0, cap at 10 digits — matches what
	// the server normaliser does so the displayed value mirrors what's saved.
	root.querySelectorAll('input[name="whatsapp_local"]').forEach(function (input) {
		var sanitise = function () {
			var v = input.value.replace(/\D/g, '');
			v = v.replace(/^0+/, '');
			if (v.length > 10) v = v.slice(0, 10);
			if (v !== input.value) input.value = v;
		};
		input.addEventListener('input', sanitise);
		input.addEventListener('blur', sanitise);
		// Pre-clean any stale 0-prefixed value rendered from the DB.
		sanitise();
	});

	// ─────────────── Password visibility toggles ───────────────
	root.querySelectorAll('[data-oc-pw-toggle]').forEach(function (btn) {
		var target = document.getElementById(btn.getAttribute('data-oc-pw-toggle'));
		if (!target) return;
		btn.addEventListener('click', function () {
			var isHidden = target.type === 'password';
			target.type = isHidden ? 'text' : 'password';
			btn.setAttribute('aria-label', isHidden ? '<?php echo esc_js( __( 'Hide password', 'owambe-connect-core' ) ); ?>' : '<?php echo esc_js( __( 'Show password', 'owambe-connect-core' ) ); ?>');
			var icon = btn.querySelector('.dashicons');
			if (icon) icon.classList.toggle('dashicons-visibility', !isHidden) , icon.classList.toggle('dashicons-hidden', isHidden);
		});
	});

	// ─────────────── Vendor-tag accordion ───────────────
	// Custom accordion (replaces native <details>) so we can show a live
	// "X / Y selected" badge per group + brand the chevron + the
	// has-selections highlight.
	root.querySelectorAll('[data-oc-tag-accordion]').forEach(function (acc) {
		acc.querySelectorAll('[data-oc-tag-toggle]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var group = btn.closest('[data-oc-tag-group]');
				if (!group) return;
				var body  = group.querySelector('[data-oc-tag-body]');
				var open  = btn.getAttribute('aria-expanded') === 'true';
				btn.setAttribute('aria-expanded', open ? 'false' : 'true');
				if (body) {
					if (open) body.setAttribute('hidden', '');
					else      body.removeAttribute('hidden');
				}
			});
		});

		acc.querySelectorAll('input[name="vendor_tags[]"]').forEach(function (cb) {
			cb.addEventListener('change', function () {
				var group = cb.closest('[data-oc-tag-group]');
				if (!group) return;
				var checked = group.querySelectorAll('input[type="checkbox"]:checked').length;
				var numEl   = group.querySelector('[data-oc-tag-count-n]');
				if (numEl) numEl.textContent = checked;
				group.classList.toggle('has-selections', checked > 0);
			});
		});
	});

	// Expand all / Collapse all — operates on the accordion in the same
	// field wrapper as the action button.
	root.querySelectorAll('[data-oc-tags-action]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var wrap = btn.closest('[data-oc-field="tags"]');
			if (!wrap) return;
			var acc = wrap.querySelector('[data-oc-tag-accordion]');
			if (!acc) return;
			var expand = btn.getAttribute('data-oc-tags-action') === 'expand';
			acc.querySelectorAll('[data-oc-tag-toggle]').forEach(function (t) {
				var body = t.closest('[data-oc-tag-group]').querySelector('[data-oc-tag-body]');
				t.setAttribute('aria-expanded', expand ? 'true' : 'false');
				if (body) {
					if (expand) body.removeAttribute('hidden');
					else        body.setAttribute('hidden', '');
				}
			});
		});
	});

	// ─────────────── Mobile collapsible (missing items checklist) ───────────────
	// Expand by default on desktop, collapse on small screens, toggleable.
	root.querySelectorAll('[data-oc-collapsible]').forEach(function (wrap) {
		var toggle = wrap.querySelector('[data-oc-collapsible-toggle]');
		var body   = wrap.querySelector('[data-oc-collapsible-body]');
		if (!toggle || !body) return;
		var apply = function (open) {
			wrap.classList.toggle('is-open', open);
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		};
		apply(window.innerWidth >= 700);
		toggle.addEventListener('click', function () {
			apply(!wrap.classList.contains('is-open'));
		});
		// Re-apply on resize so rotating a tablet feels natural.
		var lastWide = window.innerWidth >= 700;
		window.addEventListener('resize', function () {
			var wide = window.innerWidth >= 700;
			if (wide !== lastWide) { apply(wide); lastWide = wide; }
		});
	});

	// ─────────────── Auto-advance from checklist click ───────────────
	// Tapping a missing-item button already jumps to the right tab + field
	// (data-oc-tab-jump / data-oc-focus). On small screens, also briefly
	// confirm by flashing the chip pulse class. (Behaviour is already wired
	// in setTab() above — this just adds a confirmation pulse on the source.)
	root.querySelectorAll('.oc-vd__cmp-item').forEach(function (btn) {
		btn.addEventListener('click', function () {
			btn.classList.add('is-pulsing');
			setTimeout(function () { btn.classList.remove('is-pulsing'); }, 600);
		});
	});

	// ─────────────── Gallery file picker hard-cap ───────────────
	root.querySelectorAll('input[type="file"][data-oc-max-files]').forEach(function (input) {
		var max = parseInt(input.getAttribute('data-oc-max-files'), 10) || 6;
		var maxMb = parseInt(input.getAttribute('data-oc-max-mb'), 10) || 3;
		input.addEventListener('change', function () {
			if (!input.files) return;
			if (input.files.length > max) {
				showToast(
					'Only ' + max + ' more photo' + (max === 1 ? '' : 's') + ' fit — picking the first ' + max + '.',
					'error'
				);
				// Browsers don't let us mutate FileList; trim by creating a DataTransfer.
				try {
					var dt = new DataTransfer();
					for (var i = 0; i < max; i++) dt.items.add(input.files[i]);
					input.files = dt.files;
				} catch (e) { /* Fallback: leave and let server enforce. */ }
			}
			// Per-file size guard.
			for (var i = 0; i < input.files.length; i++) {
				if (input.files[i].size > maxMb * 1024 * 1024) {
					showToast(input.files[i].name + ' is over ' + maxMb + ' MB — please pick a smaller photo.', 'error');
					input.value = '';
					return;
				}
			}
		});
	});

	// ─────────────── Listing form submit ───────────────
	// Now that every image (logo / banner / gallery) is AJAX-uploaded
	// BEFORE the form is submitted, the actual save POST carries only
	// text fields + a handful of hidden attachment IDs. No XHR
	// interception needed — plain browser submit + server redirect is
	// the simplest and most reliable path. We just disable the submit
	// button + relabel it so the click feels acknowledged.
	var listingForm = root.querySelector('form.oc-vd__form');
	if (listingForm) {
		// ── Tab-aware validation guard ──────────────────────────────
		// The listing form is set to `novalidate` because it spans hidden
		// tab panels: native HTML5 validation can't focus a display:none
		// field, so an invalid control on an INACTIVE tab (e.g. WhatsApp on
		// Contact while you're on Photos) used to silently kill Save / Save &
		// continue — the button just did nothing. We now run the constraint
		// check ourselves, switch to the offending field's tab, then let the
		// browser point at it. Capture phase + stopImmediatePropagation so the
		// "Saving…" relabel below never runs on a blocked submit (which would
		// otherwise leave the button stuck on "Saving…").
		listingForm.addEventListener('submit', function (ev) {
			if (listingForm.checkValidity()) return; // all valid — let it submit
			ev.preventDefault();
			ev.stopImmediatePropagation();
			var firstInvalid = listingForm.querySelector(':invalid');
			if (!firstInvalid) return;
			var panel = firstInvalid.closest('[data-oc-panel]');
			if (panel && panel.dataset.ocPanel && !panel.classList.contains('is-active')) {
				setTab(panel.dataset.ocPanel);
			}
			// Wait for the panel to become visible before pointing at the field.
			setTimeout(function () { try { firstInvalid.reportValidity(); } catch (e) {} }, 300);
		}, true);

		// ── AJAX save — no full-page reload ─────────────────────────────────────
		var ajaxUrl    = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var ajaxAction = <?php echo wp_json_encode( OC_Dashboard::ACTION_AJAX_SAVE ); ?>;

		// Track the last mousedown'd submit button so we can read data-next-tab
		// even in browsers that don't support ev.submitter (Safari <15.4).
		var lastClickedSubmit = null;
		var rememberSubmit = function (e) {
			var b = e.target.closest('button[type="submit"]');
			if (b && listingForm.contains(b)) lastClickedSubmit = b;
		};
		listingForm.addEventListener('mousedown', rememberSubmit, true);
		// Keyboard fallback — Safari < 15.4 has no ev.submitter, so a keyboard
		// user activating "Save & continue" with Enter/Space (or just focusing
		// it and pressing Enter) would otherwise lose its data-next-tab. Track
		// the submit button they activate by key and the one they focus.
		listingForm.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') rememberSubmit(e);
		}, true);
		listingForm.addEventListener('focusin', rememberSubmit, true);

		// Tier-based description strings for the completion block (mirrors PHP).
		var cmpMessages = [
			[90, <?php echo wp_json_encode( __( 'Your profile is in excellent shape — customers will love it.',                               'owambe-connect-core' ) ); ?>],
			[70, <?php echo wp_json_encode( __( 'You\'re ready for review. Add the items below to make your profile shine.',                  'owambe-connect-core' ) ); ?>],
			[40, <?php echo wp_json_encode( __( 'Almost there. Complete the highlighted items so we can review your profile faster.',         'owambe-connect-core' ) ); ?>],
			[0,  <?php echo wp_json_encode( __( 'Your profile needs more details before we can review it. Tackle the items below.',          'owambe-connect-core' ) ); ?>],
		];
		function cmpMessage(pct) {
			for (var i = 0; i < cmpMessages.length; i++) {
				if (pct >= cmpMessages[i][0]) return cmpMessages[i][1];
			}
			return '';
		}

		function refreshCompletion(c) {
			var pct   = c.percent;
			var color = c.tier_color;

			// Sidebar: ring fill
			var ringFg = root.querySelector('.oc-vd__ring-fg');
			if (ringFg) {
				ringFg.setAttribute('stroke', color);
				ringFg.setAttribute('stroke-dasharray', pct + ' 100');
			}
			// Sidebar: avatar-wrap title
			var wrap = root.querySelector('.oc-vd__avatar-wrap');
			if (wrap) wrap.title = pct + '% complete — open Overview';

			// Sidebar: % pill
			var pill = root.querySelector('.oc-vd__cmp-pill');
			if (pill) {
				pill.textContent = pct + '% complete';
				pill.style.setProperty('--c', color);
			}

			// Overview: completion block container
			var cmp = root.querySelector('.oc-vd__cmp');
			if (cmp) {
				cmp.className = 'oc-vd__cmp oc-vd__cmp--' + c.tier;
				cmp.style.setProperty('--c', color);

				// % heading
				var head = cmp.querySelector('.oc-vd__cmp-head strong');
				if (head) head.innerHTML = pct + '%<span class="oc-vd__cmp-tier">' + c.tier_label + '</span>';

				// Tier description
				var desc = cmp.querySelector('.oc-vd__cmp-head p');
				if (desc) desc.textContent = cmpMessage(pct);

				// Item count
				var cnt = cmp.querySelector('.oc-vd__cmp-count strong');
				if (cnt) cnt.textContent = c.completed_count + ' / ' + c.total_count;

				// Progress bar
				var bar = cmp.querySelector('.oc-vd__cmp-bar');
				if (bar) {
					bar.setAttribute('aria-valuenow', pct);
					var fill = bar.querySelector('span');
					if (fill) { fill.style.width = pct + '%'; fill.style.background = color; }
				}

				// Missing items list
				var missing = (c.checklist || []).filter(function (i) { return !i.done; });
				var listWrap = cmp.querySelector('[data-oc-collapsible]');
				if (listWrap) {
					if (missing.length === 0) {
						listWrap.hidden = true;
					} else {
						listWrap.hidden = false;
						var toggle = listWrap.querySelector('[data-oc-collapsible-toggle]');
						if (toggle) toggle.firstChild.textContent = missing.length + ' to do — tap to expand ';
						var ul = listWrap.querySelector('ul.oc-vd__cmp-list');
						if (ul) {
							ul.innerHTML = '';
							missing.forEach(function (item) {
								var li  = document.createElement('li');
								var btn = document.createElement('button');
								btn.type = 'button';
								btn.className = 'oc-vd__cmp-item';
								btn.dataset.ocTabJump = item.tab;
								if (item.focus) btn.dataset.ocFocus = item.focus;
								btn.innerHTML =
									'<span class="oc-vd__cmp-dot" aria-hidden="true"></span>' +
									'<span class="oc-vd__cmp-label">' + item.label + '</span>' +
									'<span class="oc-vd__cmp-arrow" aria-hidden="true">→</span>';
								li.appendChild(btn);
								ul.appendChild(li);
							});
						}
					}
				}
			}

			// Submit-for-review button: enable once submittable
			var submitBtn = root.querySelector('[data-oc-panel="overview"] button[type="submit"]');
			if (submitBtn) {
				submitBtn.disabled = !c.submittable;
				submitBtn.title = c.submittable
					? <?php echo wp_json_encode( __( 'Send your listing to admin for review', 'owambe-connect-core' ) ); ?>
					: <?php echo wp_json_encode( __( 'Reach 100% profile completion to submit', 'owambe-connect-core' ) ); ?>;
			}
		}

		listingForm.addEventListener('submit', function (ev) {
			ev.preventDefault();

			var submitter = ev.submitter || lastClickedSubmit;
			var nextTab   = submitter && submitter.dataset.nextTab ? submitter.dataset.nextTab : null;

			var savingBtns = listingForm.querySelectorAll('button[type="submit"]');
			savingBtns.forEach(function (b) {
				b._origLabel = b.textContent;
				b.textContent = <?php echo wp_json_encode( __( 'Saving…', 'owambe-connect-core' ) ); ?>;
				b.disabled = true;
			});

			var fd = new FormData(listingForm);
			fd.set('action', ajaxAction);

			fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					// First-ever save just materialised the vendor's draft post. The
					// status card and the "Submit for review" form are server-rendered
					// and only exist once the post does, so refreshing them in place
					// isn't possible — do ONE reload here. Carry the success toast and
					// target tab through the URL so the reloaded page shows them, and
					// keep the "Saving…" button state so there's no flash before reload.
					if (data && data.success && data.data && data.data.created) {
						var u = new URL(window.location.href);
						u.searchParams.set('oc_notice', data.data.notice || <?php echo wp_json_encode( __( 'Saved!', 'owambe-connect-core' ) ); ?>);
						u.searchParams.set('tab', nextTab || currentTab);
						u.hash = '';
						window.location.href = u.toString();
						return;
					}
					savingBtns.forEach(function (b) {
						b.disabled = false;
						if (b._origLabel) b.textContent = b._origLabel;
					});
					if (!data.success) {
						showToast(data.data && data.data.message ? data.data.message : <?php echo wp_json_encode( __( 'Save failed. Please try again.', 'owambe-connect-core' ) ); ?>, 'error');
						return;
					}
					showToast(data.data.notice || <?php echo wp_json_encode( __( 'Saved!', 'owambe-connect-core' ) ); ?>, 'success');
					if (data.data.completion) refreshCompletion(data.data.completion);
					if (nextTab) setTab(nextTab, true);
				})
				.catch(function () {
					savingBtns.forEach(function (b) {
						b.disabled = false;
						if (b._origLabel) b.textContent = b._origLabel;
					});
					showToast(<?php echo wp_json_encode( __( 'Connection error — please try again.', 'owambe-connect-core' ) ); ?>, 'error');
				});
		});
	}

	// ─────────────── "Saving…" state on every non-XHR form (password, support, suggestion) ──────
	// Cheap UX win: when the browser is mid-redirect on a plain POST, the
	// submit button stays clickable, which invites double-clicks. Disabling
	// it + relabelling once is enough to make the click feel acknowledged.
	root.querySelectorAll('form').forEach(function (form) {
		if (form === listingForm) return; // listingForm handled above (file vs no-file split)
		form.addEventListener('submit', function () {
			form.querySelectorAll('button[type="submit"]').forEach(function (b) {
				if (b.dataset.ocSavingApplied) return;
				b.dataset.ocSavingApplied = '1';
				b.dataset.ocLabel = b.textContent;
				b.textContent = '<?php echo esc_js( __( 'Saving…', 'owambe-connect-core' ) ); ?>';
				b.disabled = true;
				// Safety net — if the server redirects back super-fast or
				// validation pulls us back, restore the button after 8s so
				// the user isn't stuck with a dead-looking form.
				setTimeout(function () {
					if (b.dataset.ocLabel) {
						b.textContent = b.dataset.ocLabel;
						b.disabled = false;
						delete b.dataset.ocSavingApplied;
					}
				}, 8000);
			});
		});
	});

	// ─────────────── Client-side required-field highlight on submit ───────────────
	// Server still validates; this is just an early "your eye should go HERE" cue
	// addressing the "highlight wrongly inputed fields" feedback.
	root.querySelectorAll('form').forEach(function (form) {
		form.addEventListener('submit', function () {
			form.querySelectorAll('[data-oc-field]').forEach(function (wrap) {
				wrap.classList.remove('has-error');
			});
		}, true);
		form.addEventListener('invalid', function (ev) {
			var input = ev.target;
			var wrap  = input.closest('[data-oc-field]');
			if (wrap) {
				wrap.classList.add('has-error');
				var err = wrap.querySelector('.oc-vd__field-error');
				if (err) err.textContent = input.validationMessage || '<?php echo esc_js( __( 'Please complete this field.', 'owambe-connect-core' ) ); ?>';
			}
		}, true);
	});
})();
</script>

<script>
/* ────────────────────────────────────────────────────────────
   Save & continue — injects a secondary submit button next to
   every tab's existing Save button. Clicking it sets the
   hidden _oc_tab input to the NEXT tab before submitting, so
   after the redirect the user lands on the next step instead
   of staying on the one they just saved.
   ──────────────────────────────────────────────────────────── */
(function () {
	var form = document.querySelector('.oc-vd__form');
	if (!form) return;
	var tabOrder = ['business', 'story', 'contact', 'photos', 'overview'];
	var labels = {
		business: <?php echo wp_json_encode( __( 'Save & continue to Story',   'owambe-connect-core' ) ); ?>,
		story:    <?php echo wp_json_encode( __( 'Save & continue to Contact', 'owambe-connect-core' ) ); ?>,
		contact:  <?php echo wp_json_encode( __( 'Save & continue to Photos',  'owambe-connect-core' ) ); ?>,
		photos:   <?php echo wp_json_encode( __( 'Save & finish',              'owambe-connect-core' ) ); ?>
	};
	tabOrder.slice(0, -1).forEach(function (panelKey, idx) {
		var panel = form.querySelector('[data-oc-panel="' + panelKey + '"]');
		if (!panel) return;
		var bar = panel.querySelector('.oc-vd__sticky-bar:not(.oc-vd__sticky-bar--inline)');
		if (!bar) return;
		var primary = bar.querySelector('button[type="submit"]');
		if (!primary) return;
		var nextKey = tabOrder[idx + 1];
		var btn = document.createElement('button');
		btn.type = 'submit';
		btn.className = 'oc-vd__btn oc-vd__btn--ghost';
		btn.textContent = labels[panelKey] || 'Save & continue';
		btn.dataset.nextTab = nextKey;
		btn.addEventListener('click', function () {
			// Keep the hidden input in sync for graceful fallback (no-JS / fetch error).
			form.querySelectorAll('input[name="_oc_tab"]').forEach(function (i) { i.value = nextKey; });
		});
		bar.appendChild(btn);
	});
})();
</script>

<script>
/* ────────────────────────────────────────────────────────────
   Single-image uploaders (logo, banner) — same AJAX endpoint as
   the gallery, but slot-specific. Replaces the existing image
   the moment a new file is picked and updates the preview tile
   live. The slot's hidden input ({slot}_new_id) is what the
   server save handler reads to commit the swap.
   ──────────────────────────────────────────────────────────── */
(function () {
	document.querySelectorAll('[data-oc-single-uploader]').forEach(function (box) {
		var slot     = box.getAttribute('data-oc-single-uploader');
		var input    = box.querySelector('[data-oc-single-uploader-input]');
		var preview  = box.querySelector('[data-oc-preview="' + slot + '"]');
		var statusEl = box.querySelector('[data-oc-single-uploader-status]');
		var ajaxUrl  = box.dataset.ajaxUrl;
		var action   = box.dataset.ajaxAction;
		var nonce    = box.dataset.nonce;
		var maxMb    = parseInt(box.dataset.maxMb, 10) || 5;
		if (!input || !preview) return;

		var form = box.closest('form');

		function setStatus(text, kind) {
			if (!statusEl) return;
			statusEl.textContent = text || '';
			statusEl.className = 'oc-vd__single-uploader-status' + (kind ? ' is-' + kind : '');
		}

		function paintPreview(url) {
			preview.classList.add('oc-vd__img-preview--has-image');
			preview.innerHTML = '<img src="' + url + '" alt=""/>';
		}

		function ensureHidden(value) {
			var name = slot + '_new_id';
			var hidden = form ? form.querySelector('input[type="hidden"][name="' + name + '"]') : null;
			if (!hidden && form) {
				hidden = document.createElement('input');
				hidden.type = 'hidden';
				hidden.name = name;
				form.appendChild(hidden);
			}
			if (hidden) hidden.value = value;
		}

		input.addEventListener('change', function () {
			var file = input.files && input.files[0];
			if (!file) return;
			if (file.size > maxMb * 1024 * 1024) {
				setStatus(<?php echo wp_json_encode( __( 'File too large for this slot.', 'owambe-connect-core' ) ); ?>, 'error');
				input.value = '';
				return;
			}

			// Show a local preview immediately while we upload.
			try {
				var reader = new FileReader();
				reader.onload = function (e) { paintPreview(e.target.result); };
				reader.readAsDataURL(file);
			} catch (e) {}
			setStatus(<?php echo wp_json_encode( __( 'Uploading…', 'owambe-connect-core' ) ); ?>, 'uploading');

			var fd = new FormData();
			fd.append('action', action);
			fd.append('_nonce', nonce);
			fd.append('slot',   slot);
			fd.append('file',   file);

			var xhr = new XMLHttpRequest();
			xhr.open('POST', ajaxUrl, true);
			xhr.upload.addEventListener('progress', function (e) {
				if (e.lengthComputable) {
					var pct = Math.round((e.loaded / e.total) * 100);
					setStatus(<?php echo wp_json_encode( __( 'Uploading', 'owambe-connect-core' ) ); ?> + '… ' + pct + '%', 'uploading');
				}
			});
			xhr.onload = function () {
				var data = null;
				try { data = JSON.parse(xhr.responseText); } catch (e) {}
				if (xhr.status >= 200 && xhr.status < 300 && data && data.success && data.data && data.data.id) {
					if (data.data.thumb_url) paintPreview(data.data.thumb_url);
					ensureHidden(data.data.id);
					setStatus(<?php echo wp_json_encode( __( 'Ready — will save when you click the Save button', 'owambe-connect-core' ) ); ?>, 'done');
				} else {
					setStatus((data && data.data && data.data.message) || <?php echo wp_json_encode( __( 'Upload failed. Try again.', 'owambe-connect-core' ) ); ?>, 'error');
				}
				input.value = '';
			};
			xhr.onerror = function () {
				setStatus(<?php echo wp_json_encode( __( 'Network error. Try again.', 'owambe-connect-core' ) ); ?>, 'error');
				input.value = '';
			};
			xhr.send(fd);
		});
	});
})();
</script>

<script>
/* ────────────────────────────────────────────────────────────
   Gallery uploader — AJAX, one file at a time.

   Why this exists: the old multi-file form upload bundled up to 6
   photos into a single multipart POST, easily hitting PHP's
   post_max_size on shared hosting and 404'ing the entire save. Each
   file now uploads in its own small request the moment the user
   picks it. Successful uploads become hidden gallery_new_ids[]
   inputs that the main Save handler commits to _oc_gallery_ids.
   ──────────────────────────────────────────────────────────── */
(function () {
	var box = document.querySelector('[data-oc-uploader]');
	if (!box) return;
	var input    = box.querySelector('[data-oc-uploader-input]');
	var queue    = box.querySelector('[data-oc-uploader-queue]');
	var errBox   = box.querySelector('[data-oc-uploader-errors]');
	if (!input || !queue) return;
	var ajaxUrl  = box.dataset.ajaxUrl;
	var action   = box.dataset.ajaxAction;
	var nonce    = box.dataset.nonce;
	var maxMb    = parseInt(box.dataset.maxMb,  10) || 3;
	var slotsLeft = parseInt(box.dataset.slotsLeft, 10) || 0;
	// slotsReserved counts files we've STARTED uploading (in flight or
	// done), not just successful ones. Without this, picking 6 files at
	// once would all pass the cap check because none have completed yet.
	var slotsReserved = 0;

	function remainingSlots() { return Math.max(0, slotsLeft - slotsReserved); }

	// Surface every rejection / error in a single inline banner above the
	// queue, with auto-clearing after 6 seconds. Beats native alerts —
	// they're modal, screen-reader-noisy, and easy to dismiss accidentally.
	var errorTimer = null;
	function pushError(message) {
		if (!errBox) return;
		errBox.hidden = false;
		var row = document.createElement('div');
		row.className = 'oc-vd__uploader-error';
		row.textContent = message;
		errBox.appendChild(row);
		clearTimeout(errorTimer);
		errorTimer = setTimeout(function () {
			errBox.innerHTML = '';
			errBox.hidden = true;
		}, 6000);
	}

	function tile(state) {
		var el = document.createElement('div');
		el.className = 'oc-vd__uploader-tile is-' + state.status;
		el.innerHTML =
			'<div class="oc-vd__uploader-thumb"></div>' +
			'<div class="oc-vd__uploader-info">' +
				'<div class="oc-vd__uploader-name"></div>' +
				'<div class="oc-vd__uploader-status"></div>' +
				'<div class="oc-vd__uploader-bar"><span></span></div>' +
			'</div>' +
			'<button type="button" class="oc-vd__uploader-remove" aria-label="<?php echo esc_js( __( 'Remove', 'owambe-connect-core' ) ); ?>">&times;</button>';
		el.querySelector('.oc-vd__uploader-name').textContent = state.name;
		return el;
	}

	function upload(file) {
		if (remainingSlots() <= 0) {
			pushError(<?php echo wp_json_encode( sprintf(
				/* translators: %s: filename */
				__( "Can't add %s — you've reached the photo limit for your gallery. Tick a 'Remove' box on an existing image to free a slot.", 'owambe-connect-core' ),
				'__FILE__'
			) ); ?>.replace('__FILE__', file.name));
			return;
		}
		if (file.size > maxMb * 1024 * 1024) {
			var sizeMb = (file.size / (1024 * 1024)).toFixed(1);
			pushError(<?php echo wp_json_encode( sprintf(
				/* translators: 1: filename, 2: actual size in MB, 3: max MB */
				__( '%1$s is %2$s MB — over the %3$d MB-per-image limit. Resize it and try again.', 'owambe-connect-core' ),
				'__FILE__', '__SIZE__', $gallery_mb
			) ); ?>.replace('__FILE__', file.name).replace('__SIZE__', sizeMb));
			return;
		}
		// Block image types the server would reject anyway — earlier feedback to the user.
		var okTypes = ['image/jpeg', 'image/png', 'image/webp'];
		if (file.type && okTypes.indexOf(file.type) === -1) {
			pushError(<?php echo wp_json_encode( __( 'Only JPG, PNG, or WEBP images are allowed.', 'owambe-connect-core' ) ); ?>);
			return;
		}
		// Reserve the slot synchronously — before the upload even starts —
		// so a burst of file picks can't all sneak past the cap check.
		slotsReserved++;

		var node = tile({ status: 'uploading', name: file.name });
		var statusEl = node.querySelector('.oc-vd__uploader-status');
		var barEl    = node.querySelector('.oc-vd__uploader-bar span');
		var thumbEl  = node.querySelector('.oc-vd__uploader-thumb');
		var removeBtn = node.querySelector('.oc-vd__uploader-remove');
		statusEl.textContent = <?php echo wp_json_encode( __( 'Uploading…', 'owambe-connect-core' ) ); ?>;
		queue.appendChild(node);

		// Local preview while server processes
		try {
			var reader = new FileReader();
			reader.onload = function (e) { thumbEl.style.backgroundImage = 'url(' + e.target.result + ')'; };
			reader.readAsDataURL(file);
		} catch (e) {}

		var fd = new FormData();
		fd.append('action', action);
		fd.append('_nonce', nonce);
		fd.append('file', file);

		var xhr = new XMLHttpRequest();
		xhr.open('POST', ajaxUrl, true);
		xhr.upload.addEventListener('progress', function (e) {
			if (e.lengthComputable) {
				var pct = Math.round((e.loaded / e.total) * 100);
				barEl.style.width = pct + '%';
				statusEl.textContent = <?php echo wp_json_encode( __( 'Uploading', 'owambe-connect-core' ) ); ?> + '… ' + pct + '%';
			}
		});
		xhr.onload = function () {
			var data = null;
			try { data = JSON.parse(xhr.responseText); } catch (e) {}
			if (xhr.status >= 200 && xhr.status < 300 && data && data.success && data.data && data.data.id) {
				node.classList.remove('is-uploading'); node.classList.add('is-done');
				statusEl.textContent = <?php echo wp_json_encode( __( 'Ready — will be saved when you click "Save photos"', 'owambe-connect-core' ) ); ?>;
				barEl.style.width = '100%';
				if (data.data.thumb_url) thumbEl.style.backgroundImage = 'url(' + data.data.thumb_url + ')';
				// Hidden input so update_listing() picks it up on save.
				var hidden = document.createElement('input');
				hidden.type = 'hidden';
				hidden.name = 'gallery_new_ids[]';
				hidden.value = data.data.id;
				node.appendChild(hidden);
				// Locally remove just unlinks from queue + drops the input,
				// the attached media stays on the server (orphan) until next
				// admin cleanup — accepted trade-off for instant UX.
				removeBtn.addEventListener('click', function () {
					if (node.parentNode) node.parentNode.removeChild(node);
					slotsReserved = Math.max(0, slotsReserved - 1);
				});
			} else {
				// Upload failed — give the slot back so the user can pick again.
				slotsReserved = Math.max(0, slotsReserved - 1);
				node.classList.remove('is-uploading'); node.classList.add('is-error');
				statusEl.textContent = (data && data.data && data.data.message)
					? data.data.message
					: <?php echo wp_json_encode( __( 'Upload failed. Try again.', 'owambe-connect-core' ) ); ?>;
				removeBtn.addEventListener('click', function () {
					if (node.parentNode) node.parentNode.removeChild(node);
				});
			}
		};
		xhr.onerror = function () {
			slotsReserved = Math.max(0, slotsReserved - 1);
			node.classList.remove('is-uploading'); node.classList.add('is-error');
			statusEl.textContent = <?php echo wp_json_encode( __( 'Network error. Try again.', 'owambe-connect-core' ) ); ?>;
			removeBtn.addEventListener('click', function () {
				if (node.parentNode) node.parentNode.removeChild(node);
			});
		};
		xhr.send(fd);
	}

	input.addEventListener('change', function () {
		Array.prototype.slice.call(input.files || []).forEach(upload);
		input.value = ''; // allow picking the same file again if needed
	});
})();
</script>
