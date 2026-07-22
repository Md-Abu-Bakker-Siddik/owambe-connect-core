<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class OC_Widget_FAQ extends Widget_Base {

	public function get_name()       { return 'oc_faq'; }
	public function get_title()      { return __( 'OC FAQ', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-help-o'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'faq', 'questions', 'accordion', 'owambe' ]; }
	public function get_script_depends() { return [ 'oc-frontend' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'section_content', [
			'label' => __( 'Content', 'owambe-connect-core' ),
		] );

		$this->add_control( 'heading', [
			'label'   => __( 'Heading', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'Frequently asked questions', 'owambe-connect-core' ),
		] );

		$this->add_control( 'subheading', [
			'label'   => __( 'Subheading', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => '',
		] );

		$repeater = new Repeater();
		$repeater->add_control( 'question', [
			'label'   => __( 'Question', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'A common question', 'owambe-connect-core' ),
		] );
		$repeater->add_control( 'answer', [
			'label'   => __( 'Answer', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => __( 'A clear, helpful answer.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'items', [
			'label'       => __( 'FAQ Items', 'owambe-connect-core' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'title_field' => '{{{ question }}}',
			'default'     => [
				[ 'question' => __( 'Is Owambe Connect free to use?', 'owambe-connect-core' ),
				  'answer'   => __( 'Yes — browsing vendors is completely free. Listing as a vendor is also free during our MVP launch.', 'owambe-connect-core' ) ],
				[ 'question' => __( 'How do I contact a vendor?', 'owambe-connect-core' ),
				  'answer'   => __( 'Every vendor profile has a "Message on WhatsApp" button and social links. You message vendors directly — we don\'t sit in the middle.', 'owambe-connect-core' ) ],
				[ 'question' => __( 'Do you only serve Nigerian events?', 'owambe-connect-core' ),
				  'answer'   => __( 'No — we serve the UK\'s wider minority event community: Nigerian, Pakistani, Indian, Chinese and more.', 'owambe-connect-core' ) ],
				[ 'question' => __( 'How long does vendor approval take?', 'owambe-connect-core' ),
				  'answer'   => __( 'Most applications are reviewed within 2 business days. We\'ll email you as soon as your listing is live.', 'owambe-connect-core' ) ],
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
				'{{WRAPPER}} .oc-faq' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		echo do_shortcode( '[oc_faq' . $attr . ']' );
	}
}
