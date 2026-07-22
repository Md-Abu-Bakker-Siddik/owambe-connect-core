<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class OC_Widget_Register_Form extends Widget_Base {

	public function get_name()       { return 'oc_register_form'; }
	public function get_title()      { return __( 'OC Register Form', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-form-horizontal'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'register', 'signup', 'form', 'owambe' ]; }
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
			'placeholder' => __( 'Become a Vendor', 'owambe-connect-core' ),
		] );

		$this->add_control( 'subheading', [
			'label'       => __( 'Subheading', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXTAREA,
			'default'     => '',
			'placeholder' => __( 'Create your account in 30 seconds.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'button_text', [
			'label'       => __( 'Submit Button Label', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Create my account', 'owambe-connect-core' ),
		] );

		$this->add_control( 'redirect_url', [
			'label'       => __( 'Redirect URL After Registration', 'owambe-connect-core' ),
			'type'        => Controls_Manager::URL,
			'placeholder' => home_url( '/vendor-dashboard/' ),
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
				'{{WRAPPER}} .oc-auth-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$attr = '';
		if ( ! empty( $s['heading'] ) )             { $attr .= ' heading="' . esc_attr( $s['heading'] ) . '"'; }
		if ( ! empty( $s['subheading'] ) )          { $attr .= ' subheading="' . esc_attr( $s['subheading'] ) . '"'; }
		if ( ! empty( $s['button_text'] ) )         { $attr .= ' button_text="' . esc_attr( $s['button_text'] ) . '"'; }
		if ( ! empty( $s['redirect_url']['url'] ) ) { $attr .= ' redirect_url="' . esc_attr( $s['redirect_url']['url'] ) . '"'; }
		echo do_shortcode( '[oc_register_form' . $attr . ']' );
	}
}
