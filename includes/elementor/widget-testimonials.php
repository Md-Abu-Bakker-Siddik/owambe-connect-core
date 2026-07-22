<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class OC_Widget_Testimonials extends Widget_Base {

	public function get_name()       { return 'oc_testimonials'; }
	public function get_title()      { return __( 'OC Testimonials', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-testimonial'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'testimonials', 'reviews', 'quotes', 'owambe' ]; }
	public function get_script_depends() { return [ 'oc-frontend' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'section_content', [
			'label' => __( 'Content', 'owambe-connect-core' ),
		] );

		$this->add_control( 'heading', [
			'label'   => __( 'Heading', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'What our community says', 'owambe-connect-core' ),
		] );

		$this->add_control( 'subheading', [
			'label'   => __( 'Subheading', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => __( 'Real stories from event planners and vendors.', 'owambe-connect-core' ),
		] );

		$repeater = new Repeater();
		$repeater->add_control( 'quote', [
			'label'   => __( 'Quote', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => __( 'A short, punchy quote from a happy customer.', 'owambe-connect-core' ),
		] );
		$repeater->add_control( 'name', [
			'label'   => __( 'Name', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'Adaeze O.', 'owambe-connect-core' ),
		] );
		$repeater->add_control( 'role', [
			'label'   => __( 'Role / Location', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'Wedding planner, London', 'owambe-connect-core' ),
		] );
		$repeater->add_control( 'avatar', [
			'label' => __( 'Avatar Image', 'owambe-connect-core' ),
			'type'  => Controls_Manager::MEDIA,
		] );

		$this->add_control( 'items', [
			'label'       => __( 'Testimonials', 'owambe-connect-core' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'title_field' => '{{{ name }}}',
			'default'     => [
				[
					'quote' => __( 'Found my caterer in 10 minutes. The cultural fit was exactly what I needed — they knew the menu without me explaining anything.', 'owambe-connect-core' ),
					'name'  => __( 'Adaeze O.', 'owambe-connect-core' ),
					'role'  => __( 'Bride, Manchester', 'owambe-connect-core' ),
				],
				[
					'quote' => __( 'Switched our directory listings to Owambe Connect and tripled our bookings in the first quarter. The audience is so much more aligned.', 'owambe-connect-core' ),
					'name'  => __( 'Tunde B.', 'owambe-connect-core' ),
					'role'  => __( 'Photographer, London', 'owambe-connect-core' ),
				],
				[
					'quote' => __( 'My family wanted authentic Pakistani decor — I was about to give up before finding three options here. So glad this platform exists.', 'owambe-connect-core' ),
					'name'  => __( 'Sara M.', 'owambe-connect-core' ),
					'role'  => __( 'Event planner, Birmingham', 'owambe-connect-core' ),
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
				'{{WRAPPER}} .oc-testimonials' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$attr = '';
		if ( ! empty( $s['heading'] ) )    { $attr .= ' heading="' . esc_attr( $s['heading'] ) . '"'; }
		if ( ! empty( $s['subheading'] ) ) { $attr .= ' subheading="' . esc_attr( $s['subheading'] ) . '"'; }

		// Repeater payload — flatten avatar URL.
		$flat = [];
		foreach ( (array) $s['items'] as $it ) {
			$flat[] = [
				'quote'  => $it['quote']  ?? '',
				'name'   => $it['name']   ?? '',
				'role'   => $it['role']   ?? '',
				'avatar' => isset( $it['avatar']['url'] ) ? $it['avatar']['url'] : '',
			];
		}
		if ( ! empty( $flat ) ) {
			$attr .= ' items="' . esc_attr( wp_json_encode( $flat ) ) . '"';
		}
		echo do_shortcode( '[oc_testimonials' . $attr . ']' );
	}
}
