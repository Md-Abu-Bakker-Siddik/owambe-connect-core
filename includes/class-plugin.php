<?php
/**
 * Main plugin orchestrator. Wires every component together.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot() {
		load_plugin_textdomain( 'owambe-connect-core', false, dirname( plugin_basename( OC_PLUGIN_FILE ) ) . '/languages' );

		// Only admins (manage_options) see the WP toolbar on the front-end.
		add_filter( 'show_admin_bar', static function ( $show ) {
			return current_user_can( 'manage_options' ) ? $show : false;
		} );

		( new OC_Security() )->register();
		( new OC_CPT_Manager() )->register();
		( new OC_Registration() )->register();
		( new OC_Email_Verification() )->register();
		( new OC_Dashboard() )->register();
		( new OC_Shortcodes() )->register();
		( new OC_Assets() )->register();
		( new OC_Settings() )->register();
		( new OC_Mailchimp() )->register();
		( new OC_Vendor_Activity() )->register();
		( new OC_Enquiry_Log() )->register();
		( new OC_Client() )->register();
		( new OC_Google_Auth() )->register();
		( new OC_Tracking() )->register();
		( new OC_Stripe() )->register();
		( new OC_Reviews() )->register();
		( new OC_Business_Card() )->register();

		// One-time rewrite flush when rewrite-affecting changes ship (Phase 2:
		// native /vendor/{slug}/ URLs). Bump the version string to re-flush.
		add_action( 'init', [ $this, 'maybe_flush_rewrites' ], 99 );

		// Ensure vendors page exists even on the frontend (essential for search to work).
		add_action( 'template_redirect', [ $this, 'ensure_vendors_page_exists' ], 1 );

		// Elementor widgets — hooks fire on `init` priority 0, after plugins_loaded.
		// Do NOT wrap in elementor/loaded: that action fires during Elementor's plugin
		// include phase, before plugins_loaded, so our listener would never run.
		add_action( 'elementor/elements/categories_registered', function ( $manager ) {
			if ( ! class_exists( 'OC_Elementor' ) ) {
				require_once OC_PLUGIN_DIR . 'includes/class-elementor.php';
			}
			( new OC_Elementor() )->register_category( $manager );
		} );

		add_action( 'elementor/widgets/register', function ( $manager ) {
			if ( ! class_exists( 'OC_Elementor' ) ) {
				require_once OC_PLUGIN_DIR . 'includes/class-elementor.php';
			}
			( new OC_Elementor() )->register_widgets( $manager );
		} );

		if ( is_admin() ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php'; // is_plugin_active() in security health.
			( new OC_Admin() )->register();
			( new OC_Admin_Reviews() )->register();
			( new OC_Admin_Clients() )->register();
			( new OC_Admin_Vendors_List() )->register();
			( new OC_Admin_Add_Vendor() )->register();
			( new OC_Admin_Import() )->register();
			( new OC_Admin_Emails() )->register();
		( new OC_Category_Icons() )->register();
			( new OC_Admin_Analytics() )->register();
			( new OC_Admin_Security_Health() )->register();
			( new OC_Admin_Guide() )->register();
			( new OC_Page_Seeder() )->register();

			// Self-heal /terms and /privacy pages if they're missing — runs
			// once in admin (not on every front-end hit) so an uploaded copy
			// of the plugin can repair itself without a re-activation.
			add_action( 'admin_init', [ $this, 'maybe_self_heal_legal_pages' ] );
			add_action( 'admin_init', [ $this, 'maybe_self_heal_auth_pages' ] );
			add_action( 'admin_init', [ $this, 'maybe_self_heal_client_pages' ] );
			add_action( 'admin_init', [ $this, 'maybe_self_heal_safety_page' ] );
			add_action( 'admin_init', [ $this, 'maybe_self_heal_marketplace_pages' ] );
		}
	}

	/**
	 * Versioned self-heal for a file-copy deploy (upload the plugin zip without
	 * re-activating). Runs the same one-time work the activation hook does but
	 * keyed on a version option so it fires once per release:
	 *  - dbDelta the Phase 2 tables (oc_vendor_stats, oc_rsvps) — without this
	 *    an in-place update leaves tracking + analytics silently broken;
	 *  - flush rewrites for the native /vendor/{slug}/ URLs.
	 * Both are idempotent, so running once per version bump is safe.
	 */
	public function maybe_flush_rewrites() {
		if ( get_option( 'oc_rewrite_version' ) === OC_VERSION ) {
			return;
		}
		if ( class_exists( 'OC_Activator' ) ) {
			OC_Activator::create_tables();
		}
		flush_rewrite_rules();
		update_option( 'oc_rewrite_version', OC_VERSION, true );
	}

	/**
	 * Throttled idempotent check — recreates /terms and /privacy if either
	 * page is missing. The transient stops us from hitting get_page_by_path()
	 * on every admin request once they're confirmed present.
	 */
	public function maybe_self_heal_legal_pages() {
		if ( get_transient( 'oc_legal_pages_ok' ) ) {
			return;
		}
		$terms   = get_page_by_path( 'terms' );
		$privacy = get_page_by_path( 'privacy' );
		if ( ! $terms || ! $privacy ) {
			if ( class_exists( 'OC_Activator' ) ) {
				OC_Activator::ensure_legal_pages();
			}
		}
		set_transient( 'oc_legal_pages_ok', 1, DAY_IN_SECONDS );
	}

	/**
	 * Same idea for the branded auth pages — /forgot-password/ and
	 * /reset-password/ were added after the original plugin launch, so
	 * sites that activated before this feature shipped won't have them
	 * unless we self-heal here.
	 */
	public function maybe_self_heal_auth_pages() {
		if ( get_transient( 'oc_auth_pages_ok' ) ) {
			return;
		}
		$forgot = get_page_by_path( 'forgot-password' );
		$reset  = get_page_by_path( 'reset-password' );
		if ( ! $forgot ) {
			wp_insert_post( [
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Forgot Password', 'owambe-connect-core' ),
				'post_name'    => 'forgot-password',
				'post_content' => '[oc_forgot_password]',
			] );
		}
		if ( ! $reset ) {
			wp_insert_post( [
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Reset Password', 'owambe-connect-core' ),
				'post_name'    => 'reset-password',
				'post_content' => '[oc_reset_password]',
			] );
		}
		set_transient( 'oc_auth_pages_ok', 1, DAY_IN_SECONDS );
	}

	/**
	 * Same self-heal pattern for the Phase 2 client pages — /client-login/
	 * and /client-dashboard/ — so an uploaded plugin copy repairs itself
	 * without a re-activation.
	 */
	public function maybe_self_heal_client_pages() {
		if ( get_transient( 'oc_client_pages_ok' ) ) {
			return;
		}
		$login     = get_page_by_path( 'client-login' );
		$dashboard = get_page_by_path( 'client-dashboard' );
		if ( ! $login ) {
			wp_insert_post( [
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Sign In', 'owambe-connect-core' ),
				'post_name'    => 'client-login',
				'post_content' => '[oc_client_login]',
			] );
		}
		if ( ! $dashboard ) {
			wp_insert_post( [
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'My Dashboard', 'owambe-connect-core' ),
				'post_name'    => 'client-dashboard',
				'post_content' => '[oc_client_dashboard]',
			] );
		}
		set_transient( 'oc_client_pages_ok', 1, DAY_IN_SECONDS );
	}

	/**
	 * Self-heal the /safety/ page (Website Safety Information) for installs that
	 * predate it, so an uploaded plugin copy repairs itself without re-activation.
	 */
	public function maybe_self_heal_safety_page() {
		if ( get_transient( 'oc_safety_page_ok' ) ) {
			return;
		}
		if ( ! get_page_by_path( 'safety' ) ) {
			wp_insert_post( [
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Website Safety', 'owambe-connect-core' ),
				'post_name'    => 'safety',
				'post_content' => '[oc_safety_info]',
			] );
		}
		set_transient( 'oc_safety_page_ok', 1, DAY_IN_SECONDS );
	}

	/**
	 * Self-heal the vendors directory page if it's missing.
	 * Essential for the vendor search to work — without this page, queries
	 * like /vendors/?s=keyword result in 404s.
	 */
	public function maybe_self_heal_marketplace_pages() {
		if ( get_transient( 'oc_marketplace_pages_ok' ) ) {
			return;
		}
		$vendors = get_page_by_path( 'vendors' );
		if ( ! $vendors ) {
			$result = wp_insert_post( [
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Find Vendors', 'owambe-connect-core' ),
				'post_name'    => 'vendors',
				'post_content' => '[oc_directory]',
			], true );
			if ( is_wp_error( $result ) ) {
				return;
			}
		}
		set_transient( 'oc_marketplace_pages_ok', 1, DAY_IN_SECONDS );
	}

	/**
	 * Frontend self-heal — creates the vendors page if missing, without waiting
	 * for an admin visit. Runs early on template_redirect before WordPress tries
	 * to render a 404. This fixes vendor search immediately.
	 */
	public function ensure_vendors_page_exists() {
		if ( is_admin() || is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}
		$vendors = get_page_by_path( 'vendors' );
		if ( ! $vendors ) {
			wp_insert_post( [
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Find Vendors', 'owambe-connect-core' ),
				'post_name'    => 'vendors',
				'post_content' => '[oc_directory]',
			], true );
			flush_rewrite_rules( false );
		}
	}

	private function __construct() {}
}
