<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class OC_Widget_Stats extends Widget_Base {

	public function get_name()       { return 'oc_stats'; }
	public function get_title()      { return __( 'OC Stats Row', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-counter'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'stats', 'numbers', 'counter', 'owambe' ]; }
	public function get_script_depends() { return [ 'oc-frontend' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'section_content', [
			'label' => __( 'Content', 'owambe-connect-core' ),
		] );

		$this->add_control( 'heading', [
			'label'   => __( 'Heading (optional)', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => '',
		] );

		$this->add_control( 'subheading', [
			'label'   => __( 'Subheading (optional)', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => '',
		] );

		$repeater = new Repeater();
		$repeater->add_control( 'value', [
			'label'   => __( 'Number / Value', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => '100+',
		] );
		$repeater->add_control( 'label', [
			'label'   => __( 'Label', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'Vendors listed', 'owambe-connect-core' ),
		] );

		$this->add_control( 'items', [
			'label'       => __( 'Stats', 'owambe-connect-core' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'title_field' => '{{{ value }}} — {{{ label }}}',
			'default'     => [
				[ 'value' => '200+',  'label' => __( 'Vendors listed', 'owambe-connect-core' ) ],
				[ 'value' => '15',    'label' => __( 'UK cities covered', 'owambe-connect-core' ) ],
				[ 'value' => '10',    'label' => __( 'Vendor categories', 'owambe-connect-core' ) ],
				[ 'value' => '100%',  'label' => __( 'Free during MVP', 'owambe-connect-core' ) ],
			],
		] );

		$this->end_controls_section();

		$this->start_controls_section( 'section_style', [
			'label' => __( 'Style', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );
		$this->add_responsive_control( 'section_padding', [
			'label'      => __( 'Section Padding', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-stats' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$attr = '';
		if ( ! empty( $s['heading'] ) )    { $attr .= ' heading="' . esc_attr( $s['heading'] ) . '"'; }
		if ( ! empty( $s['subheading'] ) ) { $attr .= ' subheading="' . esc_attr( $s['subheading'] ) . '"'; }
		if ( ! empty( $s['items'] ) ) {
			$attr .= ' items="' . esc_attr( wp_json_encode( $s['items'] ) ) . '"';
		}
		echo do_shortcode( '[oc_stats' . $attr . ']' );
	}
}
