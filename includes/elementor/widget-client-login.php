<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class OC_Widget_Client_Login extends Widget_Base {

	public function get_name()       { return 'oc_client_login'; }
	public function get_title()      { return __( 'OC Client Login', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-lock-user'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'client', 'login', 'google', 'sign in', 'owambe' ]; }
	public function get_script_depends() { return [ 'oc-frontend' ]; }

	protected function register_controls() {

		// ── Content tab ──────────────────────────────────────────
		$this->start_controls_section( 'section_content', [
			'label' => __( 'Settings', 'owambe-connect-core' ),
		] );

		$this->add_control( 'heading', [
			'label'       => __( 'Heading', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Sign in', 'owambe-connect-core' ),
		] );

		$this->add_control( 'subheading', [
			'label'       => __( 'Subheading', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXTAREA,
			'default'     => '',
			'placeholder' => __( 'Use your Google account to save vendors and pick up planning where you left off.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'redirect_url', [
			'label'       => __( 'Redirect URL After Login', 'owambe-connect-core' ),
			'type'        => Controls_Manager::URL,
			'placeholder' => home_url( '/client-dashboard/' ),
		] );

		$this->end_controls_section();

		// ── Style tab ────────────────────────────────────────────
		$this->start_controls_section( 'section_style', [
			'label' => __( 'Style', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_responsive_control( 'section_padding', [
			'label'      => __( 'Section Padding', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-auth' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		// Render the template directly with RAW values (the template escapes
		// them itself). Round-tripping through a shortcode STRING would let a
		// heading/subheading containing "]" terminate the shortcode early and
		// garble the page — esc_attr() does not escape brackets.
		$s = $this->get_settings_for_display();
		echo oc_get_template( 'shortcode-client-login.php', [
			'heading'    => (string) ( $s['heading'] ?? '' ),
			'subheading' => (string) ( $s['subheading'] ?? '' ),
		] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — template self-escapes
	}
}
