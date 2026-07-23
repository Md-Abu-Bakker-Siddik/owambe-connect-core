<?php
/**
 * Plugin asset enqueueing.
 *
 * CSS ownership has moved to the Owambe Connect theme (v1.2.0+).
 * The plugin now only enqueues:
 *   - oc-frontend.js  (interactive JS needed regardless of active theme)
 *   - oc-admin.css    (wp-admin only)
 *
 * Frontend CSS is enqueued by the theme's inc/assets.php so that a child
 * theme or the WP Customizer can override design tokens at the theme level.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Assets {

	public function register() {
		add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_frontend' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
	}

	public function enqueue_frontend() {
		wp_enqueue_script(
			'oc-frontend',
			OC_PLUGIN_URL . 'assets/js/oc-frontend.js',
			[],
			// filemtime so JS edits cache-bust immediately (OC_VERSION is a fixed constant).
			(string) ( @filemtime( OC_PLUGIN_DIR . 'assets/js/oc-frontend.js' ) ?: OC_VERSION ),
			true
		);

		wp_localize_script( 'oc-frontend', 'OC_DATA', [
			'ajax_url'         => admin_url( 'admin-ajax.php' ),
			'apply_url'        => oc_page_url( 'apply' ),
			'login_url'        => oc_page_url( 'vendor-login' ),
			'client_login_url' => oc_page_url( 'client-login' ),
			// Save-vendor toggle needs a nonce (logged-in only). Click beacons
			// are deliberately nonce-less: public pages may be full-page cached,
			// so a baked-in nonce would go stale — the endpoint rate-limits instead.
			'saved_nonce'      => is_user_logged_in() ? wp_create_nonce( 'oc_saved_nonce' ) : '',
		] );
	}

	public function enqueue_admin() {
		wp_enqueue_style(
			'oc-admin',
			OC_PLUGIN_URL . 'assets/css/oc-admin.css',
			[],
			OC_VERSION
		);
	}
}
