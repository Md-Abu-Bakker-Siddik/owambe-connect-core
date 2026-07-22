<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class OC_Widget_Client_Dashboard extends Widget_Base {

	public function get_name()       { return 'oc_client_dashboard'; }
	public function get_title()      { return __( 'OC Client Dashboard', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-dashboard'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'dashboard', 'client', 'saved', 'owambe' ]; }
	public function get_script_depends() { return [ 'oc-frontend' ]; }

	protected function register_controls() {

		$this->start_controls_section( 'section_note', [
			'label' => __( 'Note', 'owambe-connect-core' ),
		] );

		$this->add_control( 'note', [
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => __( 'This widget renders the full client dashboard (saved vendors, recent contacts, account). Logged-out visitors see a sign-in prompt instead.', 'owambe-connect-core' ),
			'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
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
				'{{WRAPPER}} .oc-cd' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		echo do_shortcode( '[oc_client_dashboard]' );
	}
}
