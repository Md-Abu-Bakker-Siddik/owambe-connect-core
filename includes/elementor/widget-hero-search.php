<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class OC_Widget_Hero_Search extends Widget_Base {

	public function get_name()        { return 'oc_hero_search'; }
	public function get_title()       { return __( 'OC Hero Search', 'owambe-connect-core' ); }
	public function get_icon()        { return 'eicon-search-bold'; }
	public function get_categories()  { return [ 'owambe-connect' ]; }
	public function get_keywords()    { return [ 'hero', 'search', 'owambe', 'vendor', 'banner' ]; }
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
			'placeholder' => __( 'e.g. Nigeria\'s #1 Vendor Platform', 'owambe-connect-core' ),
			'description' => __( 'Small label above the main heading.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'heading', [
			'label'   => __( 'Heading', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'Find Your Perfect Vendor', 'owambe-connect-core' ),
		] );

		$this->add_control( 'subheading', [
			'label'   => __( 'Subheading', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => __( 'Browse trusted vendors for every occasion.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'show_search', [
			'label'        => __( 'Show Search Form', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'search_btn_label', [
			'label'       => __( 'Search Button Label', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Search Vendors', 'owambe-connect-core' ),
			'condition'   => [ 'show_search' => 'yes' ],
		] );

		$this->add_control( 'show_popular', [
			'label'        => __( 'Show Popular Categories', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'popular_label', [
			'label'       => __( '"Popular:" Label', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Popular:', 'owambe-connect-core' ),
			'condition'   => [ 'show_popular' => 'yes' ],
		] );

		$this->add_control( 'button_text', [
			'label'       => __( 'Extra CTA Button Label', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'description' => __( 'Optional extra button shown above the search form.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'button_url', [
			'label'       => __( 'Extra CTA Button URL', 'owambe-connect-core' ),
			'type'        => Controls_Manager::URL,
			'placeholder' => home_url( '/vendors/' ),
			'condition'   => [ 'button_text!' => '' ],
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
				'{{WRAPPER}} .oc-hero' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_control( 'bg_image', [
			'label'       => __( 'Background Image', 'owambe-connect-core' ),
			'type'        => Controls_Manager::MEDIA,
			'description' => __( 'Optional photo shown on the right side of the hero. Works best with landscape event/decor shots.', 'owambe-connect-core' ),
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
		if ( ! empty( $s['search_btn_label'] ) )    { $attr .= ' search_btn_label="' . esc_attr( $s['search_btn_label'] ) . '"'; }
		if ( ! empty( $s['popular_label'] ) )       { $attr .= ' popular_label="' . esc_attr( $s['popular_label'] ) . '"'; }
		$attr .= ' show_search="' . ( 'yes' === ( $s['show_search'] ?? 'yes' ) ? 'yes' : 'no' ) . '"';
		$attr .= ' show_popular="' . ( 'yes' === ( $s['show_popular'] ?? 'yes' ) ? 'yes' : 'no' ) . '"';

		$bg_url = ! empty( $s['bg_image']['url'] ) ? $s['bg_image']['url'] : '';
		if ( $bg_url ) {
			$attr .= ' bg_image_url="' . esc_attr( $bg_url ) . '"';
		}

		echo do_shortcode( '[oc_hero_search' . $attr . ']' );
	}
}
