<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class OC_Widget_Navbar extends Widget_Base {

	public function get_name()       { return 'oc_navbar'; }
	public function get_title()      { return __( 'OC Navbar', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-nav-menu'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'navbar', 'header', 'menu', 'logo', 'owambe' ]; }
	public function get_script_depends() { return [ 'oc-frontend' ]; }

	protected function register_controls() {

		// ── Logo ──────────────────────────────────────────
		$this->start_controls_section( 'section_logo', [
			'label' => __( 'Logo', 'owambe-connect-core' ),
		] );

		$this->add_control( 'logo_type', [
			'label'   => __( 'Logo Type', 'owambe-connect-core' ),
			'type'    => Controls_Manager::CHOOSE,
			'options' => [
				'text'  => [ 'title' => __( 'Text', 'owambe-connect-core' ),  'icon' => 'eicon-t-letter-bold' ],
				'image' => [ 'title' => __( 'Image', 'owambe-connect-core' ), 'icon' => 'eicon-image-bold' ],
			],
			'default' => 'text',
			'toggle'  => false,
		] );

		$this->add_control( 'logo_image', [
			'label'     => __( 'Logo Image', 'owambe-connect-core' ),
			'type'      => Controls_Manager::MEDIA,
			'condition' => [ 'logo_type' => 'image' ],
		] );

		$this->add_control( 'logo_image_height', [
			'label'      => __( 'Logo Image Height', 'owambe-connect-core' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 20, 'max' => 120 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 48 ],
			'selectors'  => [ '{{WRAPPER}} .oc-brand img' => 'max-height: {{SIZE}}{{UNIT}}; width: auto;' ],
			'condition'  => [ 'logo_type' => 'image' ],
		] );

		$this->add_control( 'logo_text_mark', [
			'label'     => __( 'Brand Text (main)', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => 'OWAMBE',
			'condition' => [ 'logo_type' => 'text' ],
		] );

		$this->add_control( 'logo_text_sub', [
			'label'       => __( 'Brand Subtext', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => 'Connect',
			'description' => __( 'Small caption rendered below the main brand text. Leave blank to hide.', 'owambe-connect-core' ),
			'condition'   => [ 'logo_type' => 'text' ],
		] );

		$this->add_control( 'logo_link', [
			'label'       => __( 'Logo Links To', 'owambe-connect-core' ),
			'type'        => Controls_Manager::URL,
			'placeholder' => home_url( '/' ),
			'default'     => [ 'url' => home_url( '/' ) ],
		] );

		$this->end_controls_section();

		// ── Menu ──────────────────────────────────────────
		$this->start_controls_section( 'section_menu', [
			'label' => __( 'Menu', 'owambe-connect-core' ),
		] );

		$menus = $this->get_menus();
		$this->add_control( 'menu_id', [
			'label'       => __( 'WordPress Menu', 'owambe-connect-core' ),
			'type'        => Controls_Manager::SELECT,
			'options'     => array_merge( [ '' => __( '— Use Primary Menu Location —', 'owambe-connect-core' ) ], $menus ),
			'default'     => '',
			'description' => empty( $menus )
				? sprintf(
					/* translators: %s: Appearance → Menus URL */
					__( 'No menus found. <a href="%s" target="_blank">Create one</a> in Appearance → Menus.', 'owambe-connect-core' ),
					esc_url( admin_url( 'nav-menus.php' ) )
				)
				: __( 'Pick a specific menu or leave blank to use the menu assigned to the Primary location.', 'owambe-connect-core' ),
		] );

		$this->end_controls_section();

		// ── Action buttons ────────────────────────────────
		$this->start_controls_section( 'section_actions', [
			'label' => __( 'Action Buttons', 'owambe-connect-core' ),
		] );

		$this->add_control( 'show_login', [
			'label'        => __( 'Show Login Button (logged-out users)', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'login_text', [
			'label'     => __( 'Login Button Text', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'Log In', 'owambe-connect-core' ),
			'condition' => [ 'show_login' => 'yes' ],
		] );

		$this->add_control( 'show_cta', [
			'label'        => __( 'Show Secondary CTA Button', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'no',
			'description'  => __( 'Optional extra button alongside Login (e.g. "List your business").', 'owambe-connect-core' ),
		] );

		$this->add_control( 'cta_text', [
			'label'     => __( 'CTA Button Text', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'List Your Business', 'owambe-connect-core' ),
			'condition' => [ 'show_cta' => 'yes' ],
		] );

		$this->add_control( 'cta_url', [
			'label'     => __( 'CTA Button URL', 'owambe-connect-core' ),
			'type'      => Controls_Manager::URL,
			'default'   => [ 'url' => home_url( '/become-a-vendor/' ) ],
			'condition' => [ 'show_cta' => 'yes' ],
		] );

		$this->add_control( 'show_dashboard_when_logged_in', [
			'label'        => __( 'Show Dashboard Link (logged-in vendors)', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'show_logout_when_logged_in', [
			'label'        => __( 'Show Log Out Link (logged-in users)', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'no',
			'description'  => __( 'Off by default — users can log out from inside the dashboard.', 'owambe-connect-core' ),
		] );

		$this->end_controls_section();

		// ── Layout ────────────────────────────────────────
		$this->start_controls_section( 'section_layout', [
			'label' => __( 'Layout', 'owambe-connect-core' ),
		] );

		$this->add_control( 'sticky', [
			'label'        => __( 'Sticky Header', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'show_border', [
			'label'        => __( 'Bottom Border', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->end_controls_section();

		// ── Style tab ─────────────────────────────────────
		$this->start_controls_section( 'section_style', [
			'label' => __( 'Style', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'bg_color', [
			'label'     => __( 'Background Color', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-site-header' => 'background: {{VALUE}};' ],
		] );

		$this->add_control( 'link_color', [
			'label'     => __( 'Nav Link Color', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-nav a' => 'color: {{VALUE}};' ],
		] );

		$this->add_control( 'brand_color', [
			'label'     => __( 'Brand Text Color', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-brand__text-mark' => 'color: {{VALUE}};' ],
		] );

		$this->add_responsive_control( 'header_padding', [
			'label'      => __( 'Header Padding', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-site-header__inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();
	}

	private function get_menus() {
		$out   = [];
		$menus = wp_get_nav_menus();
		foreach ( $menus as $menu ) {
			$out[ (string) $menu->term_id ] = $menu->name;
		}
		return $out;
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$payload = [
			'logo_type'         => $s['logo_type']         ?? 'text',
			'logo_image'        => isset( $s['logo_image']['url'] ) ? $s['logo_image']['url'] : '',
			'logo_image_alt'    => isset( $s['logo_image']['alt'] ) ? $s['logo_image']['alt'] : get_bloginfo( 'name' ),
			'logo_text_mark'    => $s['logo_text_mark']    ?? '',
			'logo_text_sub'     => $s['logo_text_sub']     ?? '',
			'logo_link'         => isset( $s['logo_link']['url'] ) ? $s['logo_link']['url'] : home_url( '/' ),
			'menu_id'           => $s['menu_id']           ?? '',
			'show_login'        => ( $s['show_login']      ?? 'yes' ) === 'yes',
			'login_text'        => $s['login_text']        ?? __( 'Log In', 'owambe-connect-core' ),
			'show_cta'          => ( $s['show_cta']        ?? 'yes' ) === 'yes',
			'cta_text'          => $s['cta_text']          ?? '',
			'cta_url'           => isset( $s['cta_url']['url'] ) ? $s['cta_url']['url'] : '',
			'show_dashboard'    => ( $s['show_dashboard_when_logged_in'] ?? 'yes' ) === 'yes',
			'show_logout'       => ( $s['show_logout_when_logged_in']    ?? 'yes' ) === 'yes',
			'sticky'            => ( $s['sticky']          ?? 'yes' ) === 'yes',
			'show_border'       => ( $s['show_border']     ?? 'yes' ) === 'yes',
		];

		echo oc_get_template( 'shortcode-navbar.php', $payload );
	}
}
