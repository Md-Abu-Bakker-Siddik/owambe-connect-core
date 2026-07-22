<?php
/**
 * Featured Vendors — Elementor widget with full standard Style controls.
 *
 * This is the reference implementation for "what a properly Elementor-
 * stylable widget looks like" — every visible element on the rendered
 * card has its own Style sub-section so admin can re-skin the section
 * entirely from inside Elementor without touching CSS. The pattern
 * (Heading → Subheading → Layout → Card → Tag → Logo → Title → Meta →
 * Bio → Button → View-all) can be lifted into the other OC widgets.
 *
 * Selectors all scope under {{WRAPPER}} so two instances of the widget
 * on the same page can be styled independently.
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

class OC_Widget_Featured_Vendors extends Widget_Base {

	public function get_name()       { return 'oc_featured_vendors'; }
	public function get_title()      { return __( 'OC Featured Vendors', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-person'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'featured', 'vendors', 'owambe' ]; }
	public function get_script_depends() { return [ 'oc-frontend' ]; }

	protected function register_controls() {

		// ─────────────────────────────────────────────────────────
		//  CONTENT TAB
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_content', [
			'label' => __( 'Settings', 'owambe-connect-core' ),
		] );

		$this->add_control( 'heading', [
			'label'       => __( 'Section Heading', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Featured Vendors', 'owambe-connect-core' ),
		] );

		$this->add_control( 'subheading', [
			'label'       => __( 'Section Subheading', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXTAREA,
			'default'     => '',
			'placeholder' => __( 'Hand-picked professionals trusted by our community.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'count', [
			'label'   => __( 'Number of Vendors', 'owambe-connect-core' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => 6,
			'min'     => 1,
			'max'     => 24,
		] );

		$this->add_control( 'view_all_text', [
			'label'       => __( '"View all" Button Label', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Browse all vendors', 'owambe-connect-core' ),
			'description' => __( 'Leave blank to hide the button.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'view_all_url', [
			'label'     => __( '"View all" Button URL', 'owambe-connect-core' ),
			'type'      => Controls_Manager::URL,
			'condition' => [ 'view_all_text!' => '' ],
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Section
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_section', [
			'label' => __( 'Section', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_group_control( Group_Control_Background::get_type(), [
			'name'     => 'section_bg',
			'types'    => [ 'classic', 'gradient' ],
			'selector' => '{{WRAPPER}} .oc-featured-vendors',
		] );

		$this->add_responsive_control( 'section_padding', [
			'label'      => __( 'Padding', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-featured-vendors' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_responsive_control( 'header_align', [
			'label'        => __( 'Header alignment', 'owambe-connect-core' ),
			'type'         => Controls_Manager::CHOOSE,
			'options'      => [
				'left'   => [ 'title' => __( 'Left',   'owambe-connect-core' ), 'icon' => 'eicon-text-align-left' ],
				'center' => [ 'title' => __( 'Center', 'owambe-connect-core' ), 'icon' => 'eicon-text-align-center' ],
				'right'  => [ 'title' => __( 'Right',  'owambe-connect-core' ), 'icon' => 'eicon-text-align-right' ],
			],
			'selectors'    => [
				'{{WRAPPER}} .oc-section__head' => 'text-align: {{VALUE}};',
			],
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Heading
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_heading', [
			'label' => __( 'Section Heading', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'heading_color', [
			'label'     => __( 'Color', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .oc-section__title' => 'color: {{VALUE}};',
			],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'heading_typo',
			'selector' => '{{WRAPPER}} .oc-section__title',
		] );

		$this->add_responsive_control( 'heading_margin', [
			'label'      => __( 'Margin', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-section__title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Subheading
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_subheading', [
			'label' => __( 'Subheading', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'subheading_color', [
			'label'     => __( 'Color', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .oc-section__lead' => 'color: {{VALUE}};',
			],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'subheading_typo',
			'selector' => '{{WRAPPER}} .oc-section__lead',
		] );

		$this->add_responsive_control( 'subheading_margin', [
			'label'      => __( 'Margin', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-section__lead' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Layout / Grid
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_layout', [
			'label' => __( 'Layout', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_responsive_control( 'grid_columns', [
			'label'       => __( 'Columns', 'owambe-connect-core' ),
			'type'        => Controls_Manager::NUMBER,
			'min'         => 1,
			'max'         => 6,
			'default'     => 3,
			'tablet_default' => 2,
			'mobile_default' => 1,
			'selectors'   => [
				'{{WRAPPER}} .oc-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
			],
		] );

		$this->add_responsive_control( 'grid_gap', [
			'label'      => __( 'Gap between cards', 'owambe-connect-core' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 24 ],
			'selectors'  => [
				'{{WRAPPER}} .oc-grid' => 'gap: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Card (normal + hover, via tabs)
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_card', [
			'label' => __( 'Card', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_responsive_control( 'card_padding', [
			'label'      => __( 'Body padding', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-card__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_responsive_control( 'card_radius', [
			'label'      => __( 'Border radius', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->start_controls_tabs( 'card_state_tabs' );

		// — Normal state
		$this->start_controls_tab( 'card_tab_normal', [ 'label' => __( 'Normal', 'owambe-connect-core' ) ] );

		$this->add_group_control( Group_Control_Background::get_type(), [
			'name'     => 'card_bg',
			'types'    => [ 'classic', 'gradient' ],
			'selector' => '{{WRAPPER}} .oc-card',
		] );

		$this->add_group_control( Group_Control_Border::get_type(), [
			'name'     => 'card_border',
			'selector' => '{{WRAPPER}} .oc-card',
		] );

		$this->add_group_control( Group_Control_Box_Shadow::get_type(), [
			'name'     => 'card_shadow',
			'selector' => '{{WRAPPER}} .oc-card',
		] );

		$this->end_controls_tab();

		// — Hover state
		$this->start_controls_tab( 'card_tab_hover', [ 'label' => __( 'Hover', 'owambe-connect-core' ) ] );

		$this->add_group_control( Group_Control_Background::get_type(), [
			'name'     => 'card_bg_hover',
			'types'    => [ 'classic', 'gradient' ],
			'selector' => '{{WRAPPER}} .oc-card:hover',
		] );

		$this->add_control( 'card_border_color_hover', [
			'label'     => __( 'Border colour on hover', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .oc-card:hover' => 'border-color: {{VALUE}};',
			],
		] );

		$this->add_group_control( Group_Control_Box_Shadow::get_type(), [
			'name'     => 'card_shadow_hover',
			'selector' => '{{WRAPPER}} .oc-card:hover',
		] );

		$this->add_control( 'card_lift_hover', [
			'label'      => __( 'Lift on hover', 'owambe-connect-core' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 24 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 4 ],
			'selectors'  => [
				'{{WRAPPER}} .oc-card:hover' => 'transform: translateY(-{{SIZE}}{{UNIT}});',
			],
		] );

		$this->end_controls_tab();

		$this->end_controls_tabs();
		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Category tag (over the image)
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_tag', [
			'label' => __( 'Category tag', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'tag_color', [
			'label'     => __( 'Text colour', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-card__tag' => 'color: {{VALUE}};' ],
		] );

		$this->add_control( 'tag_bg', [
			'label'     => __( 'Background', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-card__tag' => 'background: {{VALUE}};' ],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'tag_typo',
			'selector' => '{{WRAPPER}} .oc-card__tag',
		] );

		$this->add_responsive_control( 'tag_padding', [
			'label'      => __( 'Padding', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-card__tag' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_control( 'tag_radius', [
			'label'      => __( 'Border radius', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-card__tag' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Logo
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_logo', [
			'label' => __( 'Logo', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'logo_size', [
			'label'      => __( 'Size', 'owambe-connect-core' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 24, 'max' => 96 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 44 ],
			'selectors'  => [
				'{{WRAPPER}} .oc-card__logo img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			],
		] );

		$this->add_control( 'logo_border_color', [
			'label'     => __( 'Border colour', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-card__logo img' => 'border-color: {{VALUE}};' ],
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Title
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_title', [
			'label' => __( 'Title', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->start_controls_tabs( 'title_state_tabs' );

		$this->start_controls_tab( 'title_tab_normal', [ 'label' => __( 'Normal', 'owambe-connect-core' ) ] );
		$this->add_control( 'title_color', [
			'label'     => __( 'Colour', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-card__title' => 'color: {{VALUE}};' ],
		] );
		$this->end_controls_tab();

		$this->start_controls_tab( 'title_tab_hover', [ 'label' => __( 'Hover', 'owambe-connect-core' ) ] );
		$this->add_control( 'title_color_hover', [
			'label'     => __( 'Colour on card hover', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-card:hover .oc-card__title' => 'color: {{VALUE}};' ],
		] );
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'title_typo',
			'selector' => '{{WRAPPER}} .oc-card__title',
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Meta (location · price)
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_meta', [
			'label' => __( 'Meta (location · price)', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'meta_color', [
			'label'     => __( 'Text colour', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-card__meta' => 'color: {{VALUE}};' ],
		] );

		$this->add_control( 'meta_sep_color', [
			'label'     => __( 'Separator (·) colour', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-card__sep' => 'color: {{VALUE}};' ],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'meta_typo',
			'selector' => '{{WRAPPER}} .oc-card__meta',
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — Description / bio
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_bio', [
			'label' => __( 'Description', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'bio_color', [
			'label'     => __( 'Colour', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-card__bio' => 'color: {{VALUE}};' ],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'bio_typo',
			'selector' => '{{WRAPPER}} .oc-card__bio',
		] );

		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — View profile button
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_button', [
			'label' => __( 'View Profile button', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'btn_typo',
			'selector' => '{{WRAPPER}} .oc-card__cta',
		] );

		$this->add_responsive_control( 'btn_padding', [
			'label'      => __( 'Padding', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-card__cta' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_control( 'btn_radius', [
			'label'      => __( 'Border radius', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-card__cta' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->start_controls_tabs( 'btn_state_tabs' );

		$this->start_controls_tab( 'btn_tab_normal', [ 'label' => __( 'Normal', 'owambe-connect-core' ) ] );
		$this->add_control( 'btn_color', [
			'label'     => __( 'Text colour', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-card__cta' => 'color: {{VALUE}};' ],
		] );
		$this->add_control( 'btn_bg', [
			'label'     => __( 'Background', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-card__cta' => 'background: {{VALUE}};' ],
		] );
		$this->end_controls_tab();

		$this->start_controls_tab( 'btn_tab_hover', [ 'label' => __( 'Hover', 'owambe-connect-core' ) ] );
		$this->add_control( 'btn_color_hover', [
			'label'     => __( 'Text colour on card hover', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-card:hover .oc-card__cta' => 'color: {{VALUE}};' ],
		] );
		$this->add_control( 'btn_bg_hover', [
			'label'     => __( 'Background on card hover', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-card:hover .oc-card__cta' => 'background: {{VALUE}};' ],
		] );
		$this->end_controls_tab();

		$this->end_controls_tabs();
		$this->end_controls_section();

		// ─────────────────────────────────────────────────────────
		//  STYLE TAB — View all button (under the grid)
		// ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_view_all', [
			'label'     => __( '"View all" button', 'owambe-connect-core' ),
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => [ 'view_all_text!' => '' ],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'va_typo',
			'selector' => '{{WRAPPER}} .oc-featured-vendors__view-all',
		] );

		$this->add_control( 'va_color', [
			'label'     => __( 'Text colour', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-featured-vendors__view-all' => 'color: {{VALUE}};' ],
		] );

		$this->add_control( 'va_bg', [
			'label'     => __( 'Background', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-featured-vendors__view-all' => 'background: {{VALUE}};' ],
		] );

		$this->add_group_control( Group_Control_Border::get_type(), [
			'name'     => 'va_border',
			'selector' => '{{WRAPPER}} .oc-featured-vendors__view-all',
		] );

		$this->add_responsive_control( 'va_padding', [
			'label'      => __( 'Padding', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-featured-vendors__view-all' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_control( 'va_radius', [
			'label'      => __( 'Border radius', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-featured-vendors__view-all' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_control( 'va_color_hover', [
			'label'     => __( 'Text colour on hover', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-featured-vendors__view-all:hover' => 'color: {{VALUE}};' ],
		] );

		$this->add_control( 'va_bg_hover', [
			'label'     => __( 'Background on hover', 'owambe-connect-core' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .oc-featured-vendors__view-all:hover' => 'background: {{VALUE}};' ],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$attr = ' count="' . (int) $s['count'] . '"';
		if ( ! empty( $s['heading'] ) )             { $attr .= ' heading="' . esc_attr( $s['heading'] ) . '"'; }
		if ( ! empty( $s['subheading'] ) )          { $attr .= ' subheading="' . esc_attr( $s['subheading'] ) . '"'; }
		if ( ! empty( $s['view_all_text'] ) )       { $attr .= ' view_all_text="' . esc_attr( $s['view_all_text'] ) . '"'; }
		if ( ! empty( $s['view_all_url']['url'] ) ) { $attr .= ' view_all_url="' . esc_attr( $s['view_all_url']['url'] ) . '"'; }
		echo do_shortcode( '[oc_featured_vendors' . $attr . ']' );
	}
}
