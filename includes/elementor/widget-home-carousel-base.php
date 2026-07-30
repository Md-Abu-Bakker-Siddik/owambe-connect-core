<?php
/**
 * Shared Elementor controls and rendering for the P11 homepage carousels.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

/**
 * Keeps the Recently Added, Premium Collection, and Blog Carousel widgets
 * consistent while each concrete widget retains its own Elementor identity.
 */
abstract class OC_Widget_Home_Carousel_Base extends Widget_Base {

	/**
	 * Shortcode tag rendered by the concrete widget.
	 *
	 * @return string
	 */
	abstract protected function get_shortcode_tag();

	public function get_categories() {
		return [ 'owambe-connect' ];
	}

	public function get_script_depends() {
		return [ 'oc-frontend' ];
	}

	/**
	 * Whether this carousel exposes a count control.
	 *
	 * @return bool
	 */
	protected function supports_count() {
		return false;
	}

	/**
	 * Default count used when supports_count() is true.
	 *
	 * @return int
	 */
	protected function get_default_count() {
		return 6;
	}

	/**
	 * Smallest value accepted by the count control.
	 *
	 * @return int
	 */
	protected function get_count_min() {
		return 1;
	}

	/**
	 * Help text shown below the count control.
	 *
	 * @return string
	 */
	protected function get_count_description() {
		return '';
	}

	/**
	 * Placeholder copy can be specialised without duplicating controls.
	 *
	 * @return string
	 */
	protected function get_heading_placeholder() {
		return __( 'Section heading', 'owambe-connect-core' );
	}

	/**
	 * @return string
	 */
	protected function get_subheading_placeholder() {
		return __( 'Optional supporting text', 'owambe-connect-core' );
	}

	/**
	 * @return string
	 */
	protected function get_view_all_placeholder() {
		return __( 'View all', 'owambe-connect-core' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			[
				'label' => __( 'Content', 'owambe-connect-core' ),
			]
		);

		$this->add_control(
			'heading',
			[
				'label'       => __( 'Heading', 'owambe-connect-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => $this->get_heading_placeholder(),
				'label_block' => true,
			]
		);

		$this->add_control(
			'subheading',
			[
				'label'       => __( 'Subheading', 'owambe-connect-core' ),
				'type'        => Controls_Manager::TEXTAREA,
				'default'     => '',
				'placeholder' => $this->get_subheading_placeholder(),
				'rows'        => 3,
			]
		);

		if ( $this->supports_count() ) {
			$this->add_control(
				'count',
				[
					'label'       => __( 'Number of items', 'owambe-connect-core' ),
					'type'        => Controls_Manager::NUMBER,
					'default'     => $this->get_default_count(),
					'min'         => $this->get_count_min(),
					'max'         => 24,
					'step'        => 1,
					'description' => $this->get_count_description(),
				]
			);
		}

		$this->add_control(
			'view_all_text',
			[
				'label'       => __( 'View-all label', 'owambe-connect-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => $this->get_view_all_placeholder(),
				'label_block' => true,
			]
		);

		$this->add_control(
			'view_all_url',
			[
				'label'       => __( 'View-all URL', 'owambe-connect-core' ),
				'type'        => Controls_Manager::URL,
				'placeholder' => home_url( '/' ),
				'options'     => [ 'url', 'is_external', 'nofollow' ],
				'dynamic'     => [ 'active' => true ],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_carousel',
			[
				'label' => __( 'Carousel', 'owambe-connect-core' ),
			]
		);

		$this->add_control(
			'autoplay',
			[
				'label'        => __( 'Autoplay', 'owambe-connect-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'owambe-connect-core' ),
				'label_off'    => __( 'Off', 'owambe-connect-core' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'interval',
			[
				'label'       => __( 'Autoplay interval (milliseconds)', 'owambe-connect-core' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 5000,
				'min'         => 2500,
				'max'         => 15000,
				'step'        => 500,
				'condition'   => [ 'autoplay' => 'yes' ],
				'description' => __( 'A gentle interval of 5,000 milliseconds is recommended.', 'owambe-connect-core' ),
			]
		);

		$this->end_controls_section();

		// These selectors are shared by all three shortcode templates.
		$this->start_controls_section(
			'section_style_header',
			[
				'label' => __( 'Section header', 'owambe-connect-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'header_alignment',
			[
				'label'     => __( 'Alignment', 'owambe-connect-core' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'left'   => [
						'title' => __( 'Left', 'owambe-connect-core' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'owambe-connect-core' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'owambe-connect-core' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'toggle'    => true,
				'selectors' => [
					'{{WRAPPER}} .oc-section__head' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'heading_color',
			[
				'label'     => __( 'Heading colour', 'owambe-connect-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .oc-section__title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'heading_typography',
				'selector' => '{{WRAPPER}} .oc-section__title',
			]
		);

		$this->add_control(
			'subheading_color',
			[
				'label'     => __( 'Subheading colour', 'owambe-connect-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .oc-section__lead' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'subheading_typography',
				'selector' => '{{WRAPPER}} .oc-section__lead',
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render the matching shortcode with a small, sanitised attribute set.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$attrs    = [];

		if ( ! empty( $settings['heading'] ) ) {
			$attrs['heading'] = sanitize_text_field( $settings['heading'] );
		}
		if ( ! empty( $settings['subheading'] ) ) {
			$attrs['subheading'] = sanitize_textarea_field( $settings['subheading'] );
		}
		if ( ! empty( $settings['view_all_text'] ) ) {
			$attrs['view_all_text'] = sanitize_text_field( $settings['view_all_text'] );
		}
		if ( ! empty( $settings['view_all_url']['url'] ) ) {
			$attrs['view_all_url'] = esc_url_raw( $settings['view_all_url']['url'] );
		}

		$attrs['autoplay'] = 'yes' === ( $settings['autoplay'] ?? 'yes' ) ? 'yes' : 'no';
		$attrs['interval'] = max( 2500, min( 15000, absint( $settings['interval'] ?? 5000 ) ) );

		if ( $this->supports_count() ) {
			$attrs['count'] = min( 24, max( $this->get_count_min(), absint( $settings['count'] ?? $this->get_default_count() ) ) );
		}

		$tag      = sanitize_key( $this->get_shortcode_tag() );
		$shortcode = '[' . $tag;
		foreach ( $attrs as $key => $value ) {
			$shortcode .= sprintf( ' %s="%s"', sanitize_key( $key ), esc_attr( (string) $value ) );
		}
		$shortcode .= ']';

		echo do_shortcode( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted plugin shortcode output.
	}
}
