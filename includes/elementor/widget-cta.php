<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class OC_Widget_CTA extends Widget_Base {

	public function get_name()       { return 'oc_become_a_vendor_cta'; }
	public function get_title()      { return __( 'OC Become a Vendor CTA', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-call-to-action'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'cta', 'vendor', 'apply', 'owambe' ]; }
	public function get_script_depends() { return [ 'oc-frontend' ]; }

	protected function register_controls() {

		// ── Content tab ──────────────────────────────────────────
		$this->start_controls_section( 'section_content', [
			'label' => __( 'Content', 'owambe-connect-core' ),
		] );

		$this->add_control( 'eyebrow', [
			'label'       => __( 'Eyebrow Text', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Grow your event business', 'owambe-connect-core' ),
		] );

		$this->add_control( 'heading', [
			'label'   => __( 'Heading', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => '',
			'placeholder' => __( 'Get found by people planning real events.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'subheading', [
			'label'   => __( 'Subheading', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => '',
		] );

		$this->add_control( 'button_text', [
			'label'       => __( 'Primary Button Text', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Start your application', 'owambe-connect-core' ),
		] );

		$this->add_control( 'button_url', [
			'label'       => __( 'Primary Button URL', 'owambe-connect-core' ),
			'type'        => Controls_Manager::URL,
			'placeholder' => home_url( '/apply/' ),
		] );

		$this->add_control( 'secondary_text', [
			'label'       => __( 'Secondary Button Text', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( "I'm already a vendor", 'owambe-connect-core' ),
			'description' => __( 'Leave blank to hide the secondary button.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'secondary_url', [
			'label'     => __( 'Secondary Button URL', 'owambe-connect-core' ),
			'type'      => Controls_Manager::URL,
			'condition' => [ 'secondary_text!' => '' ],
		] );

		$this->add_control( 'show_features', [
			'label'        => __( 'Show Features Grid', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'show_steps', [
			'label'        => __( 'Show "How it works" Section', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->end_controls_section();

		// ── Style tab ────────────────────────────────────────────
		$this->start_controls_section( 'section_style', [
			'label' => __( 'Style', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'bg_color', [
			'label'     => __( 'Background Color', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .oc-cta' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_responsive_control( 'section_padding', [
			'label'      => __( 'Section Padding', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-cta' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$attr = '';

		if ( ! empty( $s['eyebrow'] ) )             { $attr .= ' eyebrow="' . esc_attr( $s['eyebrow'] ) . '"'; }
		if ( ! empty( $s['heading'] ) )             { $attr .= ' heading="' . esc_attr( $s['heading'] ) . '"'; }
		if ( ! empty( $s['subheading'] ) )          { $attr .= ' subheading="' . esc_attr( $s['subheading'] ) . '"'; }
		if ( ! empty( $s['button_text'] ) )         { $attr .= ' button_text="' . esc_attr( $s['button_text'] ) . '"'; }
		if ( ! empty( $s['button_url']['url'] ) )   { $attr .= ' button_url="' . esc_attr( $s['button_url']['url'] ) . '"'; }
		if ( ! empty( $s['secondary_text'] ) )      { $attr .= ' secondary_text="' . esc_attr( $s['secondary_text'] ) . '"'; }
		if ( ! empty( $s['secondary_url']['url'] ) ) { $attr .= ' secondary_url="' . esc_attr( $s['secondary_url']['url'] ) . '"'; }
		$attr .= ' show_features="' . ( 'yes' === ( $s['show_features'] ?? 'yes' ) ? 'yes' : 'no' ) . '"';
		$attr .= ' show_steps="' . ( 'yes' === ( $s['show_steps'] ?? 'yes' ) ? 'yes' : 'no' ) . '"';

		echo do_shortcode( '[oc_become_a_vendor_cta' . $attr . ']' );
	}
}
