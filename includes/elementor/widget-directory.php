<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class OC_Widget_Directory extends Widget_Base {

	public function get_name()       { return 'oc_directory'; }
	public function get_title()      { return __( 'OC Vendor Directory', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-search'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'directory', 'vendors', 'search', 'owambe' ]; }
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
			'placeholder' => __( 'Find Vendors', 'owambe-connect-core' ),
		] );

		$this->add_control( 'subheading', [
			'label'       => __( 'Subheading', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXTAREA,
			'default'     => '',
		] );

		$this->add_control( 'per_page', [
			'label'   => __( 'Vendors Per Page', 'owambe-connect-core' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => 12,
			'min'     => 3,
			'max'     => 48,
		] );

		$this->add_control( 'show_filters', [
			'label'        => __( 'Show Filters', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'owambe-connect-core' ),
			'label_off'    => __( 'No', 'owambe-connect-core' ),
			'return_value' => 'yes',
			'default'      => 'yes',
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
				'{{WRAPPER}} .oc-directory' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$attr = ' per_page="' . (int) $s['per_page'] . '"';
		$attr .= ' show_filters="' . ( 'yes' === ( $s['show_filters'] ?? 'yes' ) ? 'yes' : 'no' ) . '"';
		if ( ! empty( $s['heading'] ) )    { $attr .= ' heading="' . esc_attr( $s['heading'] ) . '"'; }
		if ( ! empty( $s['subheading'] ) ) { $attr .= ' subheading="' . esc_attr( $s['subheading'] ) . '"'; }
		echo do_shortcode( '[oc_directory' . $attr . ']' );
	}
}
