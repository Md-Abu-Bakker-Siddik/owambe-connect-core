<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class OC_Widget_Footer extends Widget_Base {

	public function get_name()       { return 'oc_footer'; }
	public function get_title()      { return __( 'OC Footer', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-footer'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'footer', 'brand', 'social', 'owambe' ]; }
	public function get_script_depends() { return [ 'oc-frontend' ]; }

	protected function register_controls() {

		// ── Brand ─────────────────────────────────────────
		$this->start_controls_section( 'section_brand', [
			'label' => __( 'Brand Column', 'owambe-connect-core' ),
		] );

		$this->add_control( 'brand_type', [
			'label'   => __( 'Brand Logo Type', 'owambe-connect-core' ),
			'type'    => Controls_Manager::CHOOSE,
			'options' => [
				'text'  => [ 'title' => __( 'Text', 'owambe-connect-core' ),  'icon' => 'eicon-t-letter-bold' ],
				'image' => [ 'title' => __( 'Image', 'owambe-connect-core' ), 'icon' => 'eicon-image-bold' ],
			],
			'default' => 'text',
			'toggle'  => false,
		] );

		$this->add_control( 'brand_image', [
			'label'     => __( 'Brand Image', 'owambe-connect-core' ),
			'type'      => Controls_Manager::MEDIA,
			'condition' => [ 'brand_type' => 'image' ],
		] );

		$this->add_control( 'brand_mark', [
			'label'     => __( 'Brand Text (main)', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => 'OWAMBE',
			'condition' => [ 'brand_type' => 'text' ],
		] );

		$this->add_control( 'brand_sub', [
			'label'     => __( 'Brand Subtext', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => 'Connect',
			'condition' => [ 'brand_type' => 'text' ],
		] );

		$this->add_control( 'tagline', [
			'label'   => __( 'Tagline', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => __( 'Connecting Events. Celebrating Culture. The UK\'s home for finding event vendors who understand the cultures we celebrate.', 'owambe-connect-core' ),
		] );

		$this->end_controls_section();

		// ── Link Columns ──────────────────────────────────
		$this->start_controls_section( 'section_columns', [
			'label' => __( 'Link Columns', 'owambe-connect-core' ),
		] );

		$link_repeater = new Repeater();
		$link_repeater->add_control( 'label', [
			'label'   => __( 'Link Label', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'Link', 'owambe-connect-core' ),
		] );
		$link_repeater->add_control( 'url', [
			'label' => __( 'Link URL', 'owambe-connect-core' ),
			'type'  => Controls_Manager::URL,
		] );

		$col_repeater = new Repeater();
		$col_repeater->add_control( 'heading', [
			'label'   => __( 'Column Heading', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'Heading', 'owambe-connect-core' ),
		] );
		$col_repeater->add_control( 'links', [
			'label'       => __( 'Links', 'owambe-connect-core' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $link_repeater->get_controls(),
			'title_field' => '{{{ label }}}',
		] );

		$this->add_control( 'columns', [
			'label'       => __( 'Columns', 'owambe-connect-core' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $col_repeater->get_controls(),
			'title_field' => '{{{ heading }}}',
			'default'     => [
				[
					'heading' => __( 'Marketplace', 'owambe-connect-core' ),
					'links'   => [
						[ 'label' => __( 'Find Vendors', 'owambe-connect-core' ),    'url' => [ 'url' => home_url( '/vendors/' ) ] ],
						[ 'label' => __( 'Become a Vendor', 'owambe-connect-core' ), 'url' => [ 'url' => home_url( '/become-a-vendor/' ) ] ],
						[ 'label' => __( 'Vendor Login', 'owambe-connect-core' ),    'url' => [ 'url' => home_url( '/vendor-login/' ) ] ],
					],
				],
				[
					'heading' => __( 'Company', 'owambe-connect-core' ),
					'links'   => [
						[ 'label' => __( 'About', 'owambe-connect-core' ),   'url' => [ 'url' => home_url( '/about/' ) ] ],
						[ 'label' => __( 'Contact', 'owambe-connect-core' ), 'url' => [ 'url' => home_url( '/contact/' ) ] ],
					],
				],
				[
					'heading' => __( 'Legal', 'owambe-connect-core' ),
					'links'   => [
						[ 'label' => __( 'Privacy', 'owambe-connect-core' ), 'url' => [ 'url' => home_url( '/privacy/' ) ] ],
						[ 'label' => __( 'Terms', 'owambe-connect-core' ),   'url' => [ 'url' => home_url( '/terms/' ) ] ],
					],
				],
			],
		] );

		$this->end_controls_section();

		// ── Social ────────────────────────────────────────
		$this->start_controls_section( 'section_social', [
			'label' => __( 'Social Links', 'owambe-connect-core' ),
		] );

		$this->add_control( 'show_social', [
			'label'        => __( 'Show Social Icons', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'social_whatsapp', [
			'label'       => __( 'WhatsApp URL', 'owambe-connect-core' ),
			'type'        => Controls_Manager::URL,
			'placeholder' => 'https://wa.me/447424688636',
			'condition'   => [ 'show_social' => 'yes' ],
		] );

		$this->add_control( 'social_instagram', [
			'label'       => __( 'Instagram URL', 'owambe-connect-core' ),
			'type'        => Controls_Manager::URL,
			'placeholder' => 'https://instagram.com/owambeconnectuk',
			'condition'   => [ 'show_social' => 'yes' ],
		] );

		$this->add_control( 'social_facebook', [
			'label'     => __( 'Facebook URL', 'owambe-connect-core' ),
			'type'      => Controls_Manager::URL,
			'condition' => [ 'show_social' => 'yes' ],
		] );

		$this->add_control( 'social_twitter', [
			'label'     => __( 'X / Twitter URL', 'owambe-connect-core' ),
			'type'      => Controls_Manager::URL,
			'condition' => [ 'show_social' => 'yes' ],
		] );

		$this->add_control( 'social_tiktok', [
			'label'     => __( 'TikTok URL', 'owambe-connect-core' ),
			'type'      => Controls_Manager::URL,
			'condition' => [ 'show_social' => 'yes' ],
		] );

		$this->add_control( 'social_youtube', [
			'label'     => __( 'YouTube URL', 'owambe-connect-core' ),
			'type'      => Controls_Manager::URL,
			'condition' => [ 'show_social' => 'yes' ],
		] );

		$this->end_controls_section();

		// ── Newsletter ────────────────────────────────────
		$this->start_controls_section( 'section_newsletter', [
			'label' => __( 'Newsletter (optional)', 'owambe-connect-core' ),
		] );

		$this->add_control( 'show_newsletter', [
			'label'        => __( 'Show Newsletter Signup', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'no',
		] );

		$this->add_control( 'newsletter_heading', [
			'label'     => __( 'Heading', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'Stay in the loop', 'owambe-connect-core' ),
			'condition' => [ 'show_newsletter' => 'yes' ],
		] );

		$this->add_control( 'newsletter_text', [
			'label'     => __( 'Helper Text', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXTAREA,
			'default'   => __( 'New vendors and event tips, monthly. No spam.', 'owambe-connect-core' ),
			'condition' => [ 'show_newsletter' => 'yes' ],
		] );

		$this->add_control( 'newsletter_action', [
			'label'       => __( 'Form Action URL', 'owambe-connect-core' ),
			'type'        => Controls_Manager::URL,
			'placeholder' => __( 'Your mailing list endpoint', 'owambe-connect-core' ),
			'condition'   => [ 'show_newsletter' => 'yes' ],
		] );

		$this->end_controls_section();

		// ── Bottom Bar ────────────────────────────────────
		$this->start_controls_section( 'section_bottom', [
			'label' => __( 'Bottom Bar', 'owambe-connect-core' ),
		] );

		$this->add_control( 'copyright', [
			'label'       => __( 'Copyright Text', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( '&copy; {year} Owambe Connect. All rights reserved.', 'owambe-connect-core' ),
			'description' => __( 'Use {year} to insert the current year.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'credit', [
			'label'       => __( 'Credit Line', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'description' => __( 'Leave blank for no credit line in the footer.', 'owambe-connect-core' ),
		] );

		$bottom_link_repeater = new Repeater();
		$bottom_link_repeater->add_control( 'label', [
			'label'   => __( 'Label', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'Privacy', 'owambe-connect-core' ),
		] );
		$bottom_link_repeater->add_control( 'url', [
			'label' => __( 'URL', 'owambe-connect-core' ),
			'type'  => Controls_Manager::URL,
		] );

		$this->add_control( 'bottom_links', [
			'label'       => __( 'Bottom Bar Links', 'owambe-connect-core' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $bottom_link_repeater->get_controls(),
			'title_field' => '{{{ label }}}',
			'default'     => [],
		] );

		$this->end_controls_section();

		// ── Style ─────────────────────────────────────────
		$this->start_controls_section( 'section_style', [
			'label' => __( 'Style', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'bg_color', [
			'label'     => __( 'Background Color', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-footer' => 'background: {{VALUE}};' ],
		] );

		$this->add_control( 'heading_color', [
			'label'     => __( 'Heading Color', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-footer__col-heading, {{WRAPPER}} .oc-footer__brand-mark' => 'color: {{VALUE}};' ],
		] );

		$this->add_responsive_control( 'footer_padding', [
			'label'      => __( 'Footer Padding', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-footer__inner' => 'padding-top: {{TOP}}{{UNIT}}; padding-right: {{RIGHT}}{{UNIT}}; padding-bottom: {{BOTTOM}}{{UNIT}}; padding-left: {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		// Theme-mod defaults seeded with the client-supplied social URLs.
		// When the widget's own URL controls are blank we fall back to these
		// so the footer always shows the brand's real socials — Customizer
		// becomes the single source of truth.
		$social_defaults = function_exists( 'oc_get_social_links' ) ? oc_get_social_links() : [];

		// Heal any saved widget rows that still have "#" or empty URLs by
		// matching the label to a known page. An earlier version of this
		// widget shipped with hardcoded "#" URLs for Privacy / Terms / Help
		// center — sites that saved the widget with those defaults still
		// carry them in the DB. Rather than force every admin to re-edit,
		// we substitute at render time based on label.
		$columns = self::heal_column_urls( $s['columns'] ?? [] );

		$payload = [
			'brand_type'         => $s['brand_type']         ?? 'text',
			'brand_image'        => isset( $s['brand_image']['url'] ) ? $s['brand_image']['url'] : '',
			'brand_mark'         => $s['brand_mark']         ?? '',
			'brand_sub'          => $s['brand_sub']          ?? '',
			'tagline'            => $s['tagline']            ?? '',
			'columns'            => $columns,
			'show_social'        => ( $s['show_social']      ?? 'yes' ) === 'yes',
			'social_whatsapp'    => ! empty( $s['social_whatsapp']['url'] )  ? $s['social_whatsapp']['url']  : ( $social_defaults['whatsapp']  ?? '' ),
			'social_instagram'   => ! empty( $s['social_instagram']['url'] ) ? $s['social_instagram']['url'] : ( $social_defaults['instagram'] ?? '' ),
			'social_facebook'    => ! empty( $s['social_facebook']['url'] )  ? $s['social_facebook']['url']  : ( $social_defaults['facebook']  ?? '' ),
			'social_twitter'     => ! empty( $s['social_twitter']['url'] )   ? $s['social_twitter']['url']   : ( $social_defaults['twitter']   ?? '' ),
			'social_tiktok'      => ! empty( $s['social_tiktok']['url'] )    ? $s['social_tiktok']['url']    : ( $social_defaults['tiktok']    ?? '' ),
			'social_youtube'     => ! empty( $s['social_youtube']['url'] )   ? $s['social_youtube']['url']   : ( $social_defaults['youtube']   ?? '' ),
			'show_newsletter'    => ( $s['show_newsletter']  ?? 'no' ) === 'yes',
			'newsletter_heading' => $s['newsletter_heading'] ?? '',
			'newsletter_text'    => $s['newsletter_text']    ?? '',
			'newsletter_action'  => isset( $s['newsletter_action']['url'] ) ? $s['newsletter_action']['url'] : '',
			'copyright'          => $s['copyright']          ?? '',
			'credit'             => $s['credit']             ?? '',
			'bottom_links'       => $s['bottom_links']       ?? [],
		];

		echo oc_get_template( 'shortcode-footer.php', $payload );
	}

	/**
	 * Resolve "#" / empty link URLs in saved footer widget data by matching
	 * the link label to a known page slug. Lets existing live widgets that
	 * were saved with the older "#" defaults render with working links
	 * without anyone having to re-edit the widget in Elementor.
	 */
	private static function heal_column_urls( $columns ) {
		if ( ! is_array( $columns ) || empty( $columns ) ) {
			return $columns;
		}
		$label_to_slug = [
			'find vendors'     => 'vendors',
			'vendor directory' => 'vendors',
			'become a vendor'  => 'become-a-vendor',
			'become vendor'    => 'become-a-vendor',
			'vendor login'     => 'vendor-login',
			'login'            => 'vendor-login',
			'sign in'          => 'vendor-login',
			'apply'            => 'apply',
			'about'            => 'about',
			'about us'         => 'about',
			'contact'          => 'contact',
			'contact us'       => 'contact',
			'contact support'  => 'contact',
			'support'          => 'contact',
			'help center'      => 'contact',
			'help centre'      => 'contact',
			'help'             => 'contact',
			'privacy'          => 'privacy',
			'privacy policy'   => 'privacy',
			'terms'            => 'terms',
			'terms of service' => 'terms',
			'terms & conditions' => 'terms',
			'terms and conditions' => 'terms',
		];
		foreach ( $columns as &$col ) {
			if ( empty( $col['links'] ) || ! is_array( $col['links'] ) ) continue;
			foreach ( $col['links'] as &$link ) {
				$url = isset( $link['url']['url'] ) ? trim( (string) $link['url']['url'] ) : '';
				if ( '' !== $url && '#' !== $url ) continue;
				$label_key = strtolower( trim( (string) ( $link['label'] ?? '' ) ) );
				if ( isset( $label_to_slug[ $label_key ] ) ) {
					$slug = $label_to_slug[ $label_key ];
					$resolved = function_exists( 'oc_page_url' ) ? oc_page_url( $slug ) : home_url( '/' . $slug . '/' );
					$link['url']['url'] = $resolved;
				}
			}
			unset( $link );
		}
		unset( $col );
		return $columns;
	}
}
