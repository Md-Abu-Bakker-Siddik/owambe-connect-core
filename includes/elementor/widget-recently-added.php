<?php
/**
 * Recently Added homepage carousel Elementor widget.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'OC_Widget_Home_Carousel_Base' ) ) {
	require_once __DIR__ . '/widget-home-carousel-base.php';
}

class OC_Widget_Recently_Added extends OC_Widget_Home_Carousel_Base {

	public function get_name() {
		return 'oc_recently_added';
	}

	public function get_title() {
		return __( 'OC Recently Added', 'owambe-connect-core' );
	}

	public function get_icon() {
		return 'eicon-post-list';
	}

	public function get_keywords() {
		return [ 'recent', 'new', 'vendors', 'carousel', 'owambe' ];
	}

	protected function get_shortcode_tag() {
		return 'oc_recently_added';
	}

	protected function supports_count() {
		return true;
	}

	protected function get_default_count() {
		return 0;
	}

	protected function get_count_min() {
		return 0;
	}

	protected function get_count_description() {
		return __( 'Enter 0 to use the Recently Added slot count configured in Vendors → Settings.', 'owambe-connect-core' );
	}

	protected function get_heading_placeholder() {
		return __( 'Recently Added', 'owambe-connect-core' );
	}

	protected function get_subheading_placeholder() {
		return __( 'Meet the newest approved vendors on Owambe Connect.', 'owambe-connect-core' );
	}

	protected function get_view_all_placeholder() {
		return __( 'Browse all vendors', 'owambe-connect-core' );
	}
}
