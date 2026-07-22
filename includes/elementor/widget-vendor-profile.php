<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class OC_Widget_Vendor_Profile extends Widget_Base {

	public function get_name()       { return 'oc_vendor_profile'; }
	public function get_title()      { return __( 'OC Vendor Profile', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-single-post'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'profile', 'vendor', 'owambe' ]; }
	public function get_script_depends() { return [ 'oc-frontend' ]; }

	protected function register_controls() {

		// ── Content tab ──────────────────────────────────────────
		$this->start_controls_section( 'section_content', [
			'label' => __( 'Settings', 'owambe-connect-core' ),
		] );

		$this->add_control( 'vendor_slug', [
			'label'       => __( 'Vendor Slug', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => 'e.g. wedding-catering-london',
			'description' => __( 'Enter the slug of the vendor post to display. Leave blank to use the ?v= query parameter.', 'owambe-connect-core' ),
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
				'{{WRAPPER}} .oc-vp' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$attr = '';
		if ( ! empty( $s['vendor_slug'] ) ) {
			$attr = ' slug="' . esc_attr( $s['vendor_slug'] ) . '"';
		}
		echo do_shortcode( '[oc_vendor_profile' . $attr . ']' );
	}
}
