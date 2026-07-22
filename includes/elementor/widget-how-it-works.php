<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class OC_Widget_How_It_Works extends Widget_Base {

	public function get_name()       { return 'oc_how_it_works'; }
	public function get_title()      { return __( 'OC How It Works', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-steps'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'steps', 'how it works', 'process', 'owambe' ]; }
	public function get_script_depends() { return [ 'oc-frontend' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'section_content', [
			'label' => __( 'Content', 'owambe-connect-core' ),
		] );

		$this->add_control( 'heading', [
			'label'       => __( 'Heading', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( 'How it works', 'owambe-connect-core' ),
		] );

		$this->add_control( 'subheading', [
			'label'   => __( 'Subheading', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => __( 'From discovery to booking in just a few steps.', 'owambe-connect-core' ),
		] );

		$repeater = new Repeater();
		$repeater->add_control( 'icon', [
			'label'   => __( 'Icon / Emoji', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => '🔍',
		] );
		$repeater->add_control( 'title', [
			'label'   => __( 'Step Title', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'Step title', 'owambe-connect-core' ),
		] );
		$repeater->add_control( 'description', [
			'label'   => __( 'Step Description', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => __( 'Describe what happens in this step.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'steps', [
			'label'       => __( 'Steps', 'owambe-connect-core' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'title_field' => '{{{ title }}}',
			'default'     => [
				[
					'icon'        => '🔍',
					'title'       => __( 'Browse vendors', 'owambe-connect-core' ),
					'description' => __( 'Search by category, location or budget to find vendors that fit your event.', 'owambe-connect-core' ),
				],
				[
					'icon'        => '💬',
					'title'       => __( 'Reach out directly', 'owambe-connect-core' ),
					'description' => __( 'Message any vendor on WhatsApp or Instagram — no platform middleman.', 'owambe-connect-core' ),
				],
				[
					'icon'        => '🎉',
					'title'       => __( 'Book with confidence', 'owambe-connect-core' ),
					'description' => __( 'Lock in your vendor, finalise the details and celebrate.', 'owambe-connect-core' ),
				],
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
				'{{WRAPPER}} .oc-hiw' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$attr = '';
		if ( ! empty( $s['heading'] ) )    { $attr .= ' heading="' . esc_attr( $s['heading'] ) . '"'; }
		if ( ! empty( $s['subheading'] ) ) { $attr .= ' subheading="' . esc_attr( $s['subheading'] ) . '"'; }
		if ( ! empty( $s['steps'] ) ) {
			$attr .= ' items="' . esc_attr( wp_json_encode( $s['steps'] ) ) . '"';
		}
		echo do_shortcode( '[oc_how_it_works' . $attr . ']' );
	}
}
