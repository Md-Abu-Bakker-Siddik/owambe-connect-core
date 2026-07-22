<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class OC_Widget_Contact_Form extends Widget_Base {

	public function get_name()       { return 'oc_contact_form'; }
	public function get_title()      { return __( 'OC Contact Form', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-email'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'contact', 'form', 'email', 'owambe' ]; }
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
			'placeholder' => __( 'Get in touch', 'owambe-connect-core' ),
		] );

		$this->add_control( 'subheading', [
			'label'       => __( 'Subheading', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXTAREA,
			'default'     => '',
			'placeholder' => __( 'Questions, partnerships, vendor support — we\'d love to hear from you.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'button_text', [
			'label'       => __( 'Submit Button Label', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Send message', 'owambe-connect-core' ),
		] );

		$this->add_control( 'recipient_email', [
			'label'       => __( 'Recipient Email', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => get_option( 'admin_email' ),
			'description' => __( 'Leave blank to use the site admin email.', 'owambe-connect-core' ),
		] );

		$this->end_controls_section();

		// ── Contact Info side panel ──────────────────────────────
		$this->start_controls_section( 'section_info', [
			'label' => __( 'Contact Info Panel', 'owambe-connect-core' ),
		] );

		$this->add_control( 'show_info', [
			'label'        => __( 'Show Contact Info Panel', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'info_heading', [
			'label'     => __( 'Info Heading', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'Talk to us', 'owambe-connect-core' ),
			'condition' => [ 'show_info' => 'yes' ],
		] );

		$this->add_control( 'info_email', [
			'label'     => __( 'Display Email', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => 'hello@owambeconnect.com',
			'condition' => [ 'show_info' => 'yes' ],
		] );

		$this->add_control( 'info_phone', [
			'label'     => __( 'Display Phone', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => '+44 20 0000 0000',
			'condition' => [ 'show_info' => 'yes' ],
		] );

		$this->add_control( 'info_whatsapp', [
			'label'       => __( 'WhatsApp Number', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'description' => __( 'Include country code, no spaces (e.g. 447700000000).', 'owambe-connect-core' ),
			'condition'   => [ 'show_info' => 'yes' ],
		] );

		$this->add_control( 'info_address', [
			'label'     => __( 'Address', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXTAREA,
			'default'   => __( 'London, United Kingdom', 'owambe-connect-core' ),
			'condition' => [ 'show_info' => 'yes' ],
		] );

		$this->add_control( 'info_hours', [
			'label'     => __( 'Hours', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'Mon–Fri · 9am – 6pm GMT', 'owambe-connect-core' ),
			'condition' => [ 'show_info' => 'yes' ],
		] );

		$this->add_control( 'info_response', [
			'label'     => __( 'Response Time Note', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'We typically reply within 1 business day.', 'owambe-connect-core' ),
			'condition' => [ 'show_info' => 'yes' ],
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
				'{{WRAPPER}} .oc-contact-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$attr = '';
		if ( ! empty( $s['heading'] ) )    { $attr .= ' heading="' . esc_attr( $s['heading'] ) . '"'; }
		if ( ! empty( $s['subheading'] ) ) { $attr .= ' subheading="' . esc_attr( $s['subheading'] ) . '"'; }
		if ( ! empty( $s['button_text'] ) ) { $attr .= ' button_text="' . esc_attr( $s['button_text'] ) . '"'; }
		if ( ! empty( $s['recipient_email'] ) && is_email( $s['recipient_email'] ) ) {
			$attr .= ' recipient_email="' . esc_attr( $s['recipient_email'] ) . '"';
		}
		$attr .= ' show_info="' . ( 'yes' === ( $s['show_info'] ?? 'yes' ) ? 'yes' : 'no' ) . '"';
		foreach ( [ 'info_heading', 'info_email', 'info_phone', 'info_whatsapp', 'info_address', 'info_hours', 'info_response' ] as $k ) {
			if ( ! empty( $s[ $k ] ) ) { $attr .= ' ' . $k . '="' . esc_attr( $s[ $k ] ) . '"'; }
		}
		echo do_shortcode( '[oc_contact_form' . $attr . ']' );
	}
}
