<?php
/**
 * Navbar widget template — renders the site header with full editability.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$logo_type      = $logo_type      ?? 'text';
$logo_image     = $logo_image     ?? '';
$logo_image_alt = $logo_image_alt ?? get_bloginfo( 'name' );
$logo_text_mark = $logo_text_mark ?? 'OWAMBE';
$logo_text_sub  = $logo_text_sub  ?? 'Connect';
$logo_link      = $logo_link      ?? home_url( '/' );
$menu_id        = $menu_id        ?? '';
$show_login     = $show_login     ?? true;
$login_text     = $login_text     ?? __( 'Log In', 'owambe-connect-core' );
$show_cta       = $show_cta       ?? true;
$cta_text       = $cta_text       ?? __( 'List Your Business', 'owambe-connect-core' );
$cta_url        = $cta_url        ?? home_url( '/become-a-vendor/' );
$show_dashboard = $show_dashboard ?? true;
$show_logout    = $show_logout    ?? true;
$sticky         = $sticky         ?? true;
$show_border    = $show_border    ?? true;

$header_classes  = [ 'oc-site-header', 'oc-site-header--widget' ];
if ( $sticky )      { $header_classes[] = 'oc-site-header--sticky'; }
if ( $show_border ) { $header_classes[] = 'oc-site-header--bordered'; }

$menu_args = [
	'container'   => false,
	'menu_class'  => 'oc-nav__list',
	'fallback_cb' => 'oc_default_menu',
	'depth'       => 3,
	'items_wrap'  => '<ul id="%1$s" class="%2$s" role="menubar">%3$s</ul>',
];
if ( $menu_id ) {
	$menu_args['menu'] = (int) $menu_id;
} else {
	$menu_args['theme_location'] = 'primary';
}
?>
<header class="<?php echo esc_attr( implode( ' ', $header_classes ) ); ?>" role="banner">
	<div class="oc-site-header__inner">

		<a class="oc-brand" href="<?php echo esc_url( $logo_link ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php if ( 'image' === $logo_type && $logo_image ) : ?>
				<img src="<?php echo esc_url( $logo_image ); ?>" alt="<?php echo esc_attr( $logo_image_alt ); ?>" />
			<?php elseif ( $logo_text_mark || $logo_text_sub ) : ?>
				<span class="oc-brand__text" aria-hidden="true">
					<?php if ( $logo_text_mark ) : ?>
						<span class="oc-brand__text-mark"><?php echo esc_html( $logo_text_mark ); ?></span>
					<?php endif; ?>
					<?php if ( $logo_text_sub ) : ?>
						<span class="oc-brand__text-sub"><?php echo esc_html( $logo_text_sub ); ?></span>
					<?php endif; ?>
				</span>
			<?php endif; ?>
		</a>

		<nav class="oc-nav" id="oc-primary-nav" role="navigation"
		     aria-label="<?php esc_attr_e( 'Primary navigation', 'owambe-connect-core' ); ?>">
			<?php wp_nav_menu( $menu_args ); ?>
		</nav>

		<div class="oc-header-actions">
			<?php if ( is_user_logged_in() ) : ?>
				<?php if ( $show_dashboard ) :
					// Role-aware dashboard target: clients (Phase 2) go to the
					// client dashboard, everyone else keeps the vendor dashboard.
					$oc_nav_user      = wp_get_current_user();
					$oc_nav_is_client = defined( 'OC_CLIENT_ROLE' )
						&& in_array( OC_CLIENT_ROLE, (array) $oc_nav_user->roles, true )
						&& ! in_array( OC_ROLE, (array) $oc_nav_user->roles, true );
					$oc_nav_dash_slug = $oc_nav_is_client ? 'client-dashboard' : 'vendor-dashboard';
					?>
					<a class="oc-btn oc-btn--primary oc-action--mobile-primary"
					   href="<?php echo esc_url( function_exists( 'oc_page_url' ) ? oc_page_url( $oc_nav_dash_slug ) : home_url( '/' . $oc_nav_dash_slug . '/' ) ); ?>"
					   aria-label="<?php esc_attr_e( 'Dashboard', 'owambe-connect-core' ); ?>">
						<span class="oc-action__icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
						</span>
						<span class="oc-action__label"><?php esc_html_e( 'Dashboard', 'owambe-connect-core' ); ?></span>
					</a>
				<?php endif; ?>
				<?php if ( $show_logout ) : ?>
					<a class="oc-btn oc-btn--ghost"
					   href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
						<?php esc_html_e( 'Log Out', 'owambe-connect-core' ); ?>
					</a>
				<?php endif; ?>
			<?php else : ?>
				<?php if ( $show_login ) : ?>
					<a class="oc-btn oc-btn--primary oc-action--mobile-primary"
					   href="<?php echo esc_url( function_exists( 'oc_page_url' ) ? oc_page_url( 'vendor-login' ) : home_url( '/vendor-login/' ) ); ?>"
					   aria-label="<?php echo esc_attr( $login_text ); ?>">
						<span class="oc-action__icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
						</span>
						<span class="oc-action__label"><?php echo esc_html( $login_text ); ?></span>
					</a>
				<?php endif; ?>
				<?php if ( $show_cta && $cta_text ) : ?>
					<a class="oc-btn oc-btn--ghost"
					   href="<?php echo esc_url( $cta_url ?: home_url( '/become-a-vendor/' ) ); ?>">
						<?php echo esc_html( $cta_text ); ?>
					</a>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<button class="oc-nav-toggle"
		        aria-controls="oc-primary-nav"
		        aria-expanded="false"
		        aria-label="<?php esc_attr_e( 'Open navigation menu', 'owambe-connect-core' ); ?>">
			<span class="oc-nav-toggle__bars" aria-hidden="true"></span>
		</button>

	</div>
</header>
