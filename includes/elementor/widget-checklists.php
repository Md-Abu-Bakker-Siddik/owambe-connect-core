<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

/**
 * Planning Resources / checklists widget (P14) — the Elementor leg of the
 * [oc_checklists] triple.
 */
class OC_Widget_Checklists extends Widget_Base {

	public function get_name()       { return 'oc_checklists'; }
	public function get_title()      { return __( 'OC Planning Resources', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-checkbox'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'checklist', 'resources', 'download', 'pdf', 'owambe' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'section_content', [
			'label' => __( 'Content', 'owambe-connect-core' ),
		] );
		$this->add_control( 'heading', [
			'label'       => __( 'Heading', 'owambe-connect-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Planning Resources', 'owambe-connect-core' ),
		] );
		$this->add_control( 'subheading', [
			'label'   => __( 'Subheading', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => '',
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$parts = [];
		if ( ! empty( $s['heading'] ) ) {
			$parts[] = 'heading="' . esc_attr( $s['heading'] ) . '"';
		}
		if ( ! empty( $s['subheading'] ) ) {
			$parts[] = 'subheading="' . esc_attr( $s['subheading'] ) . '"';
		}
		echo do_shortcode( '[oc_checklists ' . implode( ' ', $parts ) . ']' );
	}
}
