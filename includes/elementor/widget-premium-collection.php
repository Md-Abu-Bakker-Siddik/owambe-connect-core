<?php
/**
 * Premium Collection homepage carousel Elementor widget.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'OC_Widget_Home_Carousel_Base' ) ) {
	require_once __DIR__ . '/widget-home-carousel-base.php';
}

class OC_Widget_Premium_Collection extends OC_Widget_Home_Carousel_Base {

	public function get_name() {
		return 'oc_premium_collection';
	}

	public function get_title() {
		return __( 'OC Premium Collection', 'owambe-connect-core' );
	}

	public function get_icon() {
		return 'eicon-rating';
	}

	public function get_keywords() {
		return [ 'premium', 'collection', 'vendors', 'carousel', 'owambe' ];
	}

	protected function get_shortcode_tag() {
		return 'oc_premium_collection';
	}

	protected function get_heading_placeholder() {
		return __( 'Premium Collection', 'owambe-connect-core' );
	}

	protected function get_subheading_placeholder() {
		return __( 'Discover outstanding Premium vendors for your celebration.', 'owambe-connect-core' );
	}

	protected function get_view_all_placeholder() {
		return __( 'Explore Premium vendors', 'owambe-connect-core' );
	}
}
