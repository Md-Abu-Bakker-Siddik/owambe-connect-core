<?php
/**
 * Plugin Name:       Owambe Connect Core
 * Plugin URI:        https://owambeconnect.com
 * Description:       Core engine for the Owambe Connect vendor marketplace — vendor CPT, application & approval workflow, vendor dashboard, and shortcodes that compose every page. Phase 1 (MVP).
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Instaquirk
 * Author URI:        https://instaquirk.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       owambe-connect-core
 * Domain Path:       /languages
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

define( 'OC_VERSION', '1.2.0' );
define( 'OC_PLUGIN_FILE', __FILE__ );
define( 'OC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OC_TEMPLATE_DIR', OC_PLUGIN_DIR . 'templates/' );

require_once OC_PLUGIN_DIR . 'includes/class-settings.php';
require_once OC_PLUGIN_DIR . 'includes/helpers.php';
require_once OC_PLUGIN_DIR . 'includes/class-security.php';
require_once OC_PLUGIN_DIR . 'includes/class-activator.php';
require_once OC_PLUGIN_DIR . 'includes/class-cpt.php';
require_once OC_PLUGIN_DIR . 'includes/class-mail.php';
require_once OC_PLUGIN_DIR . 'includes/class-queries.php';
require_once OC_PLUGIN_DIR . 'includes/class-registration.php';
require_once OC_PLUGIN_DIR . 'includes/class-email-verification.php';
require_once OC_PLUGIN_DIR . 'includes/class-dashboard.php';
require_once OC_PLUGIN_DIR . 'includes/class-admin.php';
require_once OC_PLUGIN_DIR . 'includes/class-admin-vendors-list.php';
require_once OC_PLUGIN_DIR . 'includes/class-admin-add-vendor.php';
require_once OC_PLUGIN_DIR . 'includes/class-admin-import.php';
require_once OC_PLUGIN_DIR . 'includes/class-admin-emails.php';
require_once OC_PLUGIN_DIR . 'includes/class-category-icons.php';
require_once OC_PLUGIN_DIR . 'includes/class-admin-analytics.php';
require_once OC_PLUGIN_DIR . 'includes/class-admin-security-health.php';
require_once OC_PLUGIN_DIR . 'includes/class-admin-guide.php';
require_once OC_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once OC_PLUGIN_DIR . 'includes/class-assets.php';
require_once OC_PLUGIN_DIR . 'includes/class-page-seeder.php';
require_once OC_PLUGIN_DIR . 'includes/class-mailchimp.php';
require_once OC_PLUGIN_DIR . 'includes/class-vendor-activity.php';
require_once OC_PLUGIN_DIR . 'includes/class-enquiry-log.php';
require_once OC_PLUGIN_DIR . 'includes/class-client.php';
require_once OC_PLUGIN_DIR . 'includes/class-google-auth.php';
require_once OC_PLUGIN_DIR . 'includes/class-tracking.php';
require_once OC_PLUGIN_DIR . 'includes/class-stripe.php';
require_once OC_PLUGIN_DIR . 'includes/class-reviews.php';
require_once OC_PLUGIN_DIR . 'includes/class-admin-reviews.php';
require_once OC_PLUGIN_DIR . 'includes/class-admin-clients.php';
require_once OC_PLUGIN_DIR . 'includes/class-business-card.php';
require_once OC_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, [ 'OC_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'OC_Activator', 'deactivate' ] );

add_action( 'plugins_loaded', static function () {
	OC_Plugin::instance()->boot();
} );
