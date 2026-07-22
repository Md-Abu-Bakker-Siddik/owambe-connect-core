<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class OC_Widget_Category_Grid extends Widget_Base {

	public function get_name()       { return 'oc_category_grid'; }
	public function get_title()      { return __( 'OC Category Grid', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-gallery-grid'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'category', 'grid', 'owambe', 'vendor' ]; }
	public function get_script_depends() { return [ 'oc-frontend' ]; }

	protected function register_controls() {

		// ── Content tab ──────────────────────────────────────────
		$this->start_controls_section( 'section_content', [
			'label' => __( 'Settings', 'owambe-connect-core' ),
		] );

		$this->add_control( 'heading', [
			'label'       => __( 'Section Heading', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Browse by Category', 'owambe-connect-core' ),
		] );

		$this->add_control( 'subheading', [
			'label'       => __( 'Section Subheading', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXTAREA,
			'default'     => '',
			'placeholder' => __( 'Find the right vendors for every part of your event.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'count', [
			'label'   => __( 'Number of Categories', 'owambe-connect-core' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => 12,
			'min'     => 1,
			'max'     => 30,
		] );

		$this->add_control( 'layout', [
			'label'   => __( 'Layout', 'owambe-connect-core' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'scroll',
			'options' => [
				'scroll' => __( 'Horizontal carousel', 'owambe-connect-core' ),
				'grid'   => __( 'Grid (wrap)', 'owambe-connect-core' ),
			],
		] );

		$this->add_control( 'card_style', [
			'label'       => __( 'Card Style', 'owambe-connect-core' ),
			'type'        => Controls_Manager::SELECT,
			'default'     => 'images',
			'options'     => [
				'images' => __( 'Style 1 — Photos', 'owambe-connect-core' ),
				'icons'  => __( 'Style 2 — Icons', 'owambe-connect-core' ),
			],
			'description' => __( 'Photos: category image on top with name and count below. Icons: compact white card with SVG icon.', 'owambe-connect-core' ),
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
				'{{WRAPPER}} .oc-categories' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$attr  = ' count="' . (int) $s['count'] . '"';
		$attr .= ' layout="' . esc_attr( ( isset( $s['layout'] ) && 'grid' === $s['layout'] ) ? 'grid' : 'scroll' ) . '"';
		$attr .= ' card_style="' . esc_attr( ( isset( $s['card_style'] ) && 'icons' === $s['card_style'] ) ? 'icons' : 'images' ) . '"';
		if ( ! empty( $s['heading'] ) )    { $attr .= ' heading="' . esc_attr( $s['heading'] ) . '"'; }
		if ( ! empty( $s['subheading'] ) ) { $attr .= ' subheading="' . esc_attr( $s['subheading'] ) . '"'; }
		echo do_shortcode( '[oc_category_grid' . $attr . ']' );
	}
}
