<?php
/**
 * OC Feature Row — flexible 2-column section (image + text).
 *
 * The missing-but-asked-for block: drop this on the homepage, About page,
 * or anywhere you want a "story" row — an image on one side, an eyebrow +
 * headline + body + optional CTA on the other. Image side is switchable,
 * aspect ratio is set per-widget, and the section background can be
 * transparent, a brand colour, or an image with overlay.
 *
 * Use [oc_feature_row] from any page, or drop the Elementor widget.
 * Follows the same comprehensive Style-tab pattern as Featured Vendors —
 * every visible element is restylable from Elementor without code.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

class OC_Widget_Feature_Row extends Widget_Base {

	public function get_name()       { return 'oc_feature_row'; }
	public function get_title()      { return __( 'OC Feature Row', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-image-box'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'feature', 'row', 'about', 'image', 'two column', '2col', 'owambe' ]; }

	protected function register_controls() {

		// ─────────────────────────────────────────────────────────
		//  CONTENT TAB
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_content', [
			'label' => __( 'Content', 'owambe-connect-core' ),
		] );

		$this->add_control( 'eyebrow', [
			'label'       => __( 'Eyebrow text', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => __( 'Our story', 'owambe-connect-core' ),
		] );

		$this->add_control( 'heading', [
			'label'       => __( 'Heading', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( 'Built for our communities', 'owambe-connect-core' ),
			'placeholder' => __( 'Your headline here', 'owambe-connect-core' ),
		] );

		$this->add_control( 'body', [
			'label'   => __( 'Body text', 'owambe-connect-core' ),
			'type'    => Controls_Manager::WYSIWYG,
			'default' => __( 'Designed for the UK\'s diverse event scene — celebrating African, Caribbean, South Asian, multicultural, luxury, and contemporary events with the visibility they deserve.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'cta_text', [
			'label'       => __( 'CTA button label', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => __( 'Find vendors', 'owambe-connect-core' ),
			'description' => __( 'Leave blank to hide the button.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'cta_url', [
			'label'     => __( 'CTA URL', 'owambe-connect-core' ),
			'type'      => Controls_Manager::URL,
			'condition' => [ 'cta_text!' => '' ],
		] );

		$this->add_control( 'image', [
			'label'   => __( 'Image', 'owambe-connect-core' ),
			'type'    => Controls_Manager::MEDIA,
			'default' => [ 'url' => '' ],
		] );

		$this->add_control( 'image_position', [
			'label'   => __( 'Image position', 'owambe-connect-core' ),
			'type'    => Controls_Manager::CHOOSE,
			'options' => [
				'left'  => [ 'title' => __( 'Left',  'owambe-connect-core' ), 'icon' => 'eicon-h-align-left' ],
				'right' => [ 'title' => __( 'Right', 'owambe-connect-core' ), 'icon' => 'eicon-h-align-right' ],
			],
			'default' => 'left',
			'toggle'  => false,
		] );

		$this->add_responsive_control( 'image_ratio', [
			'label'   => __( 'Image aspect ratio', 'owambe-connect-core' ),
			'type'    => Controls_Manager::SELECT,
			'options' => [
				'auto'   => __( 'Auto (use the image\'s own dimensions)', 'owambe-connect-core' ),
				'1/1'    => __( 'Square — 1:1',  'owambe-connect-core' ),
				'4/5'    => __( 'Portrait — 4:5', 'owambe-connect-core' ),
				'3/4'    => __( 'Portrait — 3:4', 'owambe-connect-core' ),
				'3/2'    => __( 'Landscape — 3:2', 'owambe-connect-core' ),
				'4/3'    => __( 'Landscape — 4:3', 'owambe-connect-core' ),
				'16/9'   => __( 'Wide — 16:9',    'owambe-connect-core' ),
			],
			'default' => 'auto',
			'selectors' => [
				'{{WRAPPER}} .oc-feature-row__media img' => 'aspect-ratio: {{VALUE}}; object-fit: cover;',
			],
		] );

		$this->add_control( 'vertical_align', [
			'label'   => __( 'Vertical alignment', 'owambe-connect-core' ),
			'type'    => Controls_Manager::CHOOSE,
			'options' => [
				'start'  => [ 'title' => __( 'Top',    'owambe-connect-core' ), 'icon' => 'eicon-v-align-top' ],
				'center' => [ 'title' => __( 'Center', 'owambe-connect-core' ), 'icon' => 'eicon-v-align-middle' ],
				'end'    => [ 'title' => __( 'Bottom', 'owambe-connect-core' ), 'icon' => 'eicon-v-align-bottom' ],
			],
			'default'   => 'center',
			'toggle'    => false,
			'selectors' => [
				'{{WRAPPER}} .oc-feature-row__inner' => 'align-items: {{VALUE}};',
			],
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Section (background + padding)
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_section', [
			'label' => __( 'Section', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_group_control( Group_Control_Background::get_type(), [
			'name'     => 'section_bg',
			'types'    => [ 'classic', 'gradient' ],
			'selector' => '{{WRAPPER}} .oc-feature-row',
		] );

		$this->add_control( 'section_overlay', [
			'label'     => __( 'Background overlay (sits on top of image)', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .oc-feature-row::before' => 'background: {{VALUE}};',
			],
			'description' => __( 'Use a semi-transparent colour like rgba(31,27,26,.5) when the background is an image, so text stays readable.', 'owambe-connect-core' ),
		] );

		$this->add_responsive_control( 'section_padding', [
			'label'      => __( 'Padding', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-feature-row' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_responsive_control( 'columns_gap', [
			'label'      => __( 'Gap between columns', 'owambe-connect-core' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 16, 'max' => 120 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 56 ],
			'selectors'  => [
				'{{WRAPPER}} .oc-feature-row__inner' => 'gap: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Image
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_image', [
			'label' => __( 'Image', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_responsive_control( 'image_radius', [
			'label'      => __( 'Border radius', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-feature-row__media img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_group_control( Group_Control_Box_Shadow::get_type(), [
			'name'     => 'image_shadow',
			'selector' => '{{WRAPPER}} .oc-feature-row__media img',
		] );

		$this->add_responsive_control( 'image_max_width', [
			'label'      => __( 'Max width', 'owambe-connect-core' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px', '%' ],
			'range'      => [ 'px' => [ 'min' => 200, 'max' => 800 ], '%' => [ 'min' => 30, 'max' => 100 ] ],
			'default'    => [ 'unit' => '%', 'size' => 100 ],
			'selectors'  => [
				'{{WRAPPER}} .oc-feature-row__media img' => 'max-width: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Eyebrow
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_eyebrow', [
			'label'     => __( 'Eyebrow', 'owambe-connect-core' ),
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => [ 'eyebrow!' => '' ],
		] );

		$this->add_control( 'eyebrow_color', [
			'label'     => __( 'Colour', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-feature-row__eyebrow' => 'color: {{VALUE}};' ],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'eyebrow_typo',
			'selector' => '{{WRAPPER}} .oc-feature-row__eyebrow',
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Heading
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_heading', [
			'label' => __( 'Heading', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'heading_color', [
			'label'     => __( 'Colour', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-feature-row__title' => 'color: {{VALUE}};' ],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'heading_typo',
			'selector' => '{{WRAPPER}} .oc-feature-row__title',
		] );

		$this->add_responsive_control( 'heading_margin', [
			'label'      => __( 'Margin', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-feature-row__title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Body
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_body', [
			'label' => __( 'Body text', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'body_color', [
			'label'     => __( 'Colour', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-feature-row__body' => 'color: {{VALUE}};' ],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'body_typo',
			'selector' => '{{WRAPPER}} .oc-feature-row__body',
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — CTA button
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_cta', [
			'label'     => __( 'CTA button', 'owambe-connect-core' ),
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => [ 'cta_text!' => '' ],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'cta_typo',
			'selector' => '{{WRAPPER}} .oc-feature-row__cta',
		] );

		$this->add_responsive_control( 'cta_padding', [
			'label'      => __( 'Padding', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-feature-row__cta' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_control( 'cta_radius', [
			'label'      => __( 'Border radius', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-feature-row__cta' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->start_controls_tabs( 'cta_tabs' );

		$this->start_controls_tab( 'cta_tab_normal', [ 'label' => __( 'Normal', 'owambe-connect-core' ) ] );
		$this->add_control( 'cta_color', [
			'label'     => __( 'Text colour', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-feature-row__cta' => 'color: {{VALUE}};' ],
		] );
		$this->add_control( 'cta_bg', [
			'label'     => __( 'Background', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-feature-row__cta' => 'background: {{VALUE}};' ],
		] );
		$this->end_controls_tab();

		$this->start_controls_tab( 'cta_tab_hover', [ 'label' => __( 'Hover', 'owambe-connect-core' ) ] );
		$this->add_control( 'cta_color_hover', [
			'label'     => __( 'Text colour', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-feature-row__cta:hover' => 'color: {{VALUE}};' ],
		] );
		$this->add_control( 'cta_bg_hover', [
			'label'     => __( 'Background', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-feature-row__cta:hover' => 'background: {{VALUE}};' ],
		] );
		$this->end_controls_tab();

		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$attr = '';
		if ( ! empty( $s['eyebrow'] ) )           { $attr .= ' eyebrow="' . esc_attr( $s['eyebrow'] ) . '"'; }
		if ( ! empty( $s['heading'] ) )           { $attr .= ' heading="' . esc_attr( $s['heading'] ) . '"'; }
		if ( ! empty( $s['cta_text'] ) )          { $attr .= ' cta_text="' . esc_attr( $s['cta_text'] ) . '"'; }
		if ( ! empty( $s['cta_url']['url'] ) )    { $attr .= ' cta_url="' . esc_attr( $s['cta_url']['url'] ) . '"'; }
		if ( ! empty( $s['image']['url'] ) )      { $attr .= ' image="' . esc_attr( $s['image']['url'] ) . '"'; }
		if ( ! empty( $s['image_position'] ) )    { $attr .= ' image_position="' . esc_attr( $s['image_position'] ) . '"'; }

		// Body comes through as WYSIWYG HTML; pass it via shortcode content rather
		// than an attribute to preserve markup integrity.
		$body = isset( $s['body'] ) ? wp_kses_post( $s['body'] ) : '';
		echo do_shortcode( '[oc_feature_row' . $attr . ']' . $body . '[/oc_feature_row]' );
	}
}
