<?php
/**
 * Elementor integration — registers the OC widget category and all widgets.
 *
 * Loaded only when the `elementor/loaded` action fires, so there is no
 * fatal error if Elementor is deactivated.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Elementor {

	public function init() {
		add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );
		add_action( 'elementor/widgets/register',              [ $this, 'register_widgets'  ] );
	}

	public function register_category( $manager ) {
		$manager->add_category( 'owambe-connect', [
			'title' => __( 'Owambe Connect', 'owambe-connect-core' ),
			'icon'  => 'eicon-apps',
		] );
	}

	public function register_widgets( $manager ) {
		$dir = OC_PLUGIN_DIR . 'includes/elementor/';

		require_once $dir . 'widget-hero-search.php';
		require_once $dir . 'widget-category-grid.php';
		require_once $dir . 'widget-featured-vendors.php';
		require_once $dir . 'widget-directory.php';
		require_once $dir . 'widget-vendor-profile.php';
		require_once $dir . 'widget-register-form.php';
		require_once $dir . 'widget-login-form.php';
		require_once $dir . 'widget-vendor-dashboard.php';
		require_once $dir . 'widget-contact-form.php';
		require_once $dir . 'widget-cta.php';
		require_once $dir . 'widget-how-it-works.php';
		require_once $dir . 'widget-testimonials.php';
		require_once $dir . 'widget-faq.php';
		require_once $dir . 'widget-stats.php';
		require_once $dir . 'widget-about-blocks.php';
		require_once $dir . 'widget-feature-row.php';
		require_once $dir . 'widget-navbar.php';
		require_once $dir . 'widget-footer.php';
		require_once $dir . 'widget-client-login.php';
		require_once $dir . 'widget-client-dashboard.php';

		$manager->register( new OC_Widget_Hero_Search() );
		$manager->register( new OC_Widget_Category_Grid() );
		$manager->register( new OC_Widget_Featured_Vendors() );
		$manager->register( new OC_Widget_Directory() );
		$manager->register( new OC_Widget_Vendor_Profile() );
		$manager->register( new OC_Widget_Register_Form() );
		$manager->register( new OC_Widget_Login_Form() );
		$manager->register( new OC_Widget_Vendor_Dashboard() );
		$manager->register( new OC_Widget_Contact_Form() );
		$manager->register( new OC_Widget_CTA() );
		$manager->register( new OC_Widget_How_It_Works() );
		$manager->register( new OC_Widget_Testimonials() );
		$manager->register( new OC_Widget_FAQ() );
		$manager->register( new OC_Widget_Stats() );
		$manager->register( new OC_Widget_About_Blocks() );
		$manager->register( new OC_Widget_Feature_Row() );
		$manager->register( new OC_Widget_Navbar() );
		$manager->register( new OC_Widget_Footer() );
		$manager->register( new OC_Widget_Client_Login() );
		$manager->register( new OC_Widget_Client_Dashboard() );
	}
}
