<?php
/**
 * Blog Carousel homepage Elementor widget.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'OC_Widget_Home_Carousel_Base' ) ) {
	require_once __DIR__ . '/widget-home-carousel-base.php';
}

class OC_Widget_Blog_Carousel extends OC_Widget_Home_Carousel_Base {

	public function get_name() {
		return 'oc_blog_carousel';
	}

	public function get_title() {
		return __( 'OC Blog Carousel', 'owambe-connect-core' );
	}

	public function get_icon() {
		return 'eicon-posts-carousel';
	}

	public function get_keywords() {
		return [ 'blog', 'posts', 'articles', 'carousel', 'owambe' ];
	}

	protected function get_shortcode_tag() {
		return 'oc_blog_carousel';
	}

	protected function supports_count() {
		return true;
	}

	protected function get_default_count() {
		return 6;
	}

	protected function get_count_description() {
		return __( 'The newest published posts are shown first.', 'owambe-connect-core' );
	}

	protected function get_heading_placeholder() {
		return __( 'Latest from the Blog', 'owambe-connect-core' );
	}

	protected function get_subheading_placeholder() {
		return __( 'Ideas and inspiration for a memorable celebration.', 'owambe-connect-core' );
	}

	protected function get_view_all_placeholder() {
		return __( 'Read all articles', 'owambe-connect-core' );
	}
}
