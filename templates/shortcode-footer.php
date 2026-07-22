<?php
/**
 * Footer widget template.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$brand_type         = $brand_type         ?? 'text';
$brand_image        = $brand_image        ?? '';
$brand_mark         = $brand_mark         ?? 'OWAMBE';
$brand_sub          = $brand_sub          ?? 'Connect';
$tagline            = $tagline            ?? '';
$columns            = ! empty( $columns ) ? $columns : [
	[
		'heading' => __( 'Marketplace', 'owambe-connect-core' ),
		'links'   => [
			[ 'label' => __( 'Find Vendors', 'owambe-connect-core' ),    'icon' => 'search',   'url' => [ 'url' => function_exists( 'oc_page_url' ) ? oc_page_url( 'vendors' )         : home_url( '/vendors/' ) ] ],
			[ 'label' => __( 'Become a Vendor', 'owambe-connect-core' ), 'icon' => 'store',    'url' => [ 'url' => function_exists( 'oc_page_url' ) ? oc_page_url( 'become-a-vendor' ) : home_url( '/become-a-vendor/' ) ] ],
			[ 'label' => __( 'Vendor Login', 'owambe-connect-core' ),    'icon' => 'login',    'url' => [ 'url' => function_exists( 'oc_page_url' ) ? oc_page_url( 'vendor-login' )    : home_url( '/vendor-login/' ) ] ],
		],
	],
	[
		'heading' => __( 'Company', 'owambe-connect-core' ),
		'links'   => [
			[ 'label' => __( 'About',           'owambe-connect-core' ), 'icon' => 'info',     'url' => [ 'url' => function_exists( 'oc_page_url' ) ? oc_page_url( 'about' )   : home_url( '/about/' ) ] ],
			[ 'label' => __( 'Contact',         'owambe-connect-core' ), 'icon' => 'message',  'url' => [ 'url' => function_exists( 'oc_page_url' ) ? oc_page_url( 'contact' ) : home_url( '/contact/' ) ] ],
			[ 'label' => __( 'Contact support', 'owambe-connect-core' ), 'icon' => 'support',  'url' => [ 'url' => function_exists( 'oc_page_url' ) ? oc_page_url( 'contact' ) : home_url( '/contact/' ) ] ],
		],
	],
	[
		'heading' => __( 'Legal', 'owambe-connect-core' ),
		'links'   => [
			[ 'label' => __( 'Privacy', 'owambe-connect-core' ), 'icon' => 'shield', 'url' => [ 'url' => function_exists( 'oc_page_url' ) ? oc_page_url( 'privacy' ) : '#' ] ],
			[ 'label' => __( 'Terms',   'owambe-connect-core' ), 'icon' => 'file',   'url' => [ 'url' => function_exists( 'oc_page_url' ) ? oc_page_url( 'terms' )   : '#' ] ],
		],
	],
];
$show_social        = $show_social        ?? true;
$social_instagram   = $social_instagram   ?? '';
$social_facebook    = $social_facebook    ?? '';
$social_twitter     = $social_twitter     ?? '';
$social_tiktok      = $social_tiktok      ?? '';
$social_youtube     = $social_youtube     ?? '';
$social_whatsapp    = $social_whatsapp    ?? '';
$show_newsletter    = $show_newsletter    ?? false;
$newsletter_heading = $newsletter_heading ?? '';
$newsletter_text    = $newsletter_text    ?? '';
$newsletter_action  = $newsletter_action  ?? '';
$copyright          = $copyright          ?? '';
// Per client feedback v1, no "Built by Instaquirk" credit on the footer.
// We accept the variable for backwards-compat but ignore the default value
// unless an Elementor widget explicitly passes a non-empty $credit.
$credit             = $credit             ?? '';
$bottom_links       = $bottom_links       ?? [];

$socials = array_filter( [
	'whatsapp'  => $social_whatsapp,
	'instagram' => $social_instagram,
	'facebook'  => $social_facebook,
	'twitter'   => $social_twitter,
	'tiktok'    => $social_tiktok,
	'youtube'   => $social_youtube,
] );

// 18×18 monoline SVGs for footer link bullets. Keep small + stroke-current
// so they pick up the link colour on hover.
$footer_link_icons = [
	'search'  => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
	'store'   => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l1-5h16l1 5"/><path d="M5 9v11h14V9"/></svg>',
	'login'   => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>',
	'info'    => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
	'message' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.4 8.4 0 01-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.4 8.4 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>',
	'support' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
	'shield'  => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
	'file'    => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
];

$copyright_rendered = $copyright ? str_replace( '{year}', date_i18n( 'Y' ), $copyright ) : '';

$social_svgs = [
	'whatsapp'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M20.52 3.48A11.78 11.78 0 0012.07 0C5.56 0 .27 5.29.27 11.81a11.7 11.7 0 001.6 5.93L0 24l6.4-1.68a11.79 11.79 0 005.66 1.44h.01c6.51 0 11.8-5.29 11.8-11.81a11.74 11.74 0 00-3.35-8.47zM12.07 21.79h-.01a9.93 9.93 0 01-5.06-1.38l-.36-.21-3.8 1 1.02-3.7-.24-.38a9.92 9.92 0 01-1.52-5.31c0-5.49 4.47-9.96 9.97-9.96a9.93 9.93 0 019.95 9.97c0 5.5-4.47 9.97-9.95 9.97zm5.46-7.46c-.3-.15-1.77-.87-2.05-.97-.27-.1-.47-.15-.67.15s-.77.97-.94 1.17c-.17.2-.35.22-.65.07a8.2 8.2 0 01-2.41-1.49 9.05 9.05 0 01-1.67-2.08c-.17-.3 0-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5s.05-.37-.02-.52c-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51l-.57-.01a1.1 1.1 0 00-.8.37 3.34 3.34 0 00-1.04 2.48c0 1.46 1.06 2.87 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.62.71.23 1.36.2 1.87.12.57-.09 1.77-.72 2.02-1.42.25-.7.25-1.29.17-1.42-.07-.13-.27-.2-.57-.35z"/></svg>',
	'instagram' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 01-1.38-.9 3.7 3.7 0 01-.9-1.38c-.16-.42-.36-1.06-.41-2.23-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16zM12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63a5.86 5.86 0 00-2.13 1.38A5.86 5.86 0 00.63 4.14C.33 4.9.13 5.78.07 7.05.01 8.33 0 8.74 0 12c0 3.26.01 3.67.07 4.95.06 1.27.26 2.15.56 2.91.32.78.74 1.45 1.38 2.13.68.64 1.35 1.06 2.13 1.38.76.3 1.64.5 2.91.56C8.33 23.99 8.74 24 12 24c3.26 0 3.67-.01 4.95-.07 1.27-.06 2.15-.26 2.91-.56.78-.32 1.45-.74 2.13-1.38a5.86 5.86 0 001.38-2.13c.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95 0-3.26-.01-3.67-.07-4.95-.06-1.27-.26-2.15-.56-2.91A5.86 5.86 0 0021.99 2.01 5.86 5.86 0 0019.86.63c-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0zm0 5.84A6.16 6.16 0 1018.16 12 6.16 6.16 0 0012 5.84zm0 10.16A4 4 0 1116 12a4 4 0 01-4 4zm6.4-11.84a1.44 1.44 0 11-1.44-1.44 1.44 1.44 0 011.44 1.44z"/></svg>',
	'facebook'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 10-11.56 9.88V14.9H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.77l-.44 2.9h-2.33v6.98A10 10 0 0022 12z"/></svg>',
	'twitter'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18.244 2H21l-6.49 7.41L22 22h-6.18l-4.84-6.32L5.4 22H2.64l6.94-7.93L2 2h6.32l4.37 5.78L18.244 2z"/></svg>',
	'tiktok'    => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005.8 20.1a6.34 6.34 0 0010.86-4.43V8.55a8.16 8.16 0 004.77 1.52V6.69a4.85 4.85 0 01-1.84-0z"/></svg>',
	'youtube'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M23.5 6.2a3 3 0 00-2.1-2.1C19.6 3.6 12 3.6 12 3.6s-7.6 0-9.4.5A3 3 0 00.5 6.2C0 8 0 12 0 12s0 4 .5 5.8a3 3 0 002.1 2.1c1.8.5 9.4.5 9.4.5s7.6 0 9.4-.5a3 3 0 002.1-2.1C24 16 24 12 24 12s0-4-.5-5.8zM9.6 15.4V8.6L15.8 12l-6.2 3.4z"/></svg>',
];
?>
<footer class="oc-footer" role="contentinfo">
	<div class="oc-footer__inner">
		<div class="oc-footer__top">

			<div class="oc-footer__brand">
				<a class="oc-footer__brand-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php if ( 'image' === $brand_type && $brand_image ) : ?>
						<img class="oc-footer__brand-image" src="<?php echo esc_url( $brand_image ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
					<?php else : ?>
						<span class="oc-footer__brand-text">
							<?php if ( $brand_mark ) : ?><span class="oc-footer__brand-mark"><?php echo esc_html( $brand_mark ); ?></span><?php endif; ?>
							<?php if ( $brand_sub ) : ?><span class="oc-footer__brand-sub"><?php echo esc_html( $brand_sub ); ?></span><?php endif; ?>
						</span>
					<?php endif; ?>
				</a>
				<?php if ( $tagline ) : ?>
					<p class="oc-footer__tagline"><?php echo esc_html( $tagline ); ?></p>
				<?php endif; ?>

				<?php
				// Public contact email — uses the Customizer value when set,
				// otherwise falls back to the brand's published address so the
				// footer always shows a real way to reach the team.
				$_oc_footer_email = function_exists( 'oc_get_contact_email' ) ? oc_get_contact_email() : '';
				if ( ! $_oc_footer_email || ! is_email( $_oc_footer_email ) || $_oc_footer_email === get_option( 'admin_email' ) ) {
					$_oc_footer_email = 'info@owambeconnect.com';
				}
				?>
				<p class="oc-footer__contact">
					<a class="oc-footer__contact-link" href="mailto:<?php echo esc_attr( $_oc_footer_email ); ?>">
						<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
						<?php echo esc_html( $_oc_footer_email ); ?>
					</a>
				</p>

				<?php if ( $show_social && ! empty( $socials ) ) : ?>
					<ul class="oc-footer__social">
						<?php foreach ( $socials as $key => $url ) : ?>
							<li>
								<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( ucfirst( $key ) ); ?>">
									<?php echo $social_svgs[ $key ] ?? ''; // phpcs:ignore — trusted SVG strings ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="oc-footer__cols">
				<?php foreach ( $columns as $col ) : ?>
					<div class="oc-footer__col">
						<?php if ( ! empty( $col['heading'] ) ) : ?>
							<h4 class="oc-footer__col-heading"><?php echo esc_html( $col['heading'] ); ?></h4>
						<?php endif; ?>
						<?php if ( ! empty( $col['links'] ) && is_array( $col['links'] ) ) : ?>
							<ul class="oc-footer__col-list">
								<?php foreach ( $col['links'] as $link ) :
									$url      = isset( $link['url']['url'] ) ? $link['url']['url'] : ( $link['url'] ?? '' );
									$icon_key = isset( $link['icon'] ) ? (string) $link['icon'] : '';
									$icon_svg = $icon_key && isset( $footer_link_icons[ $icon_key ] ) ? $footer_link_icons[ $icon_key ] : '';
								?>
									<li>
										<a href="<?php echo esc_url( $url ?: '#' ); ?>" class="oc-footer__col-link">
											<?php if ( $icon_svg ) : ?>
												<span class="oc-footer__col-link-icon" aria-hidden="true"><?php echo $icon_svg; // phpcs:ignore — trusted inline SVG ?></span>
											<?php endif; ?>
											<span><?php echo esc_html( $link['label'] ?? '' ); ?></span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $show_newsletter ) : ?>
				<div class="oc-footer__newsletter">
					<?php if ( $newsletter_heading ) : ?>
						<h4 class="oc-footer__col-heading"><?php echo esc_html( $newsletter_heading ); ?></h4>
					<?php endif; ?>
					<?php if ( $newsletter_text ) : ?>
						<p class="oc-footer__newsletter-text"><?php echo esc_html( $newsletter_text ); ?></p>
					<?php endif; ?>
					<form class="oc-footer__newsletter-form" action="<?php echo esc_url( $newsletter_action ?: '#' ); ?>" method="post">
						<input type="email" name="email" required placeholder="<?php esc_attr_e( 'your@email.com', 'owambe-connect-core' ); ?>" aria-label="<?php esc_attr_e( 'Your email address', 'owambe-connect-core' ); ?>" />
						<button type="submit" class="oc-btn oc-btn-primary"><?php esc_html_e( 'Subscribe', 'owambe-connect-core' ); ?></button>
					</form>
				</div>
			<?php endif; ?>
		</div>

		<div class="oc-footer__bottom">
			<?php if ( $copyright_rendered ) : ?>
				<span class="oc-footer__copyright"><?php echo wp_kses_post( $copyright_rendered ); ?></span>
			<?php endif; ?>

			<?php if ( ! empty( $bottom_links ) ) : ?>
				<ul class="oc-footer__bottom-links">
					<?php foreach ( $bottom_links as $link ) :
						$url = isset( $link['url']['url'] ) ? $link['url']['url'] : ( $link['url'] ?? '' );
					?>
						<li><a href="<?php echo esc_url( $url ?: '#' ); ?>"><?php echo esc_html( $link['label'] ?? '' ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $credit ) : ?>
				<span class="oc-footer__credit"><?php echo esc_html( $credit ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</footer>
