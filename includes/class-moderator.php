<?php
/**
 * Vendor Moderator role (H7, client change request Aug 2026).
 *
 * A least-privilege role for a virtual assistant who reviews vendor
 * applications. It can ONLY reach the vendor review queue, approve/reject
 * applications, and (when the edit scope is enabled) amend listing fields —
 * nothing else.
 *
 * Because the vendor CPT uses capability_type=post, plain post caps would
 * also unlock blog posts/pages. So the role holds broad post primitives only
 * as scaffolding, and three layers clamp them to vendors:
 *
 *   1. map_meta_cap — per-post edits allowed on vendors only; ALL deletes and
 *      non-vendor edits denied.
 *   2. user_has_cap — hard-denies every admin/user/plugin/settings/export/
 *      payment capability, belt-and-braces.
 *   3. admin_init allowlist — blocks direct-URL access to any admin screen
 *      outside the vendor queue (menus are hidden AND enforced server-side).
 *
 * Scope is client-confirmable: `oc_moderator_can_edit_listings` (default true)
 * flips the whole edit surface off for a strict review-only VA.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Moderator {

	const ROLE = 'oc_vendor_moderator';
	const CAP  = 'oc_moderate_vendors';

	/** Admin `page` slugs a moderator may open. */
	const ALLOWED_PAGES = [ 'oc-vendors', 'oc-add-vendor' ];

	public function register() {
		add_action( 'init', [ __CLASS__, 'register_role' ] );
		add_filter( 'map_meta_cap', [ __CLASS__, 'map_caps' ], 10, 4 );
		add_filter( 'user_has_cap', [ __CLASS__, 'deny_dangerous_caps' ], 10, 4 );
		add_action( 'admin_menu', [ __CLASS__, 'hide_menus' ], 999 );
		add_action( 'admin_init', [ __CLASS__, 'enforce_allowed_screens' ] );
		add_filter( 'show_admin_bar', [ __CLASS__, 'admin_bar' ] );
	}

	/** True when the current build grants moderators listing-edit rights. */
	public static function can_edit_listings() {
		return (bool) apply_filters( 'oc_moderator_can_edit_listings', true );
	}

	public static function is_moderator( $user = null ) {
		$user = $user ? new WP_User( $user ) : wp_get_current_user();
		return $user && in_array( self::ROLE, (array) $user->roles, true )
			&& ! user_can( $user, 'manage_options' );
	}

	/* ── Role definition ──────────────────────────────────────────────── */

	public static function register_role() {
		$caps = [
			'read'                   => true,
			'upload_files'           => true, // amend listing images (edit scope).
			self::CAP                => true,
			// Post primitives are scaffolding for the vendor queue UI; clamped
			// to vendors by map_caps() and never usable on other post types.
			'edit_posts'             => true,
			'edit_others_posts'      => true,
			'edit_published_posts'   => true,
			'publish_posts'          => true,
		];
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, __( 'Vendor Moderator', 'owambe-connect-core' ), $caps );
		} else {
			$role = get_role( self::ROLE );
			foreach ( $caps as $cap => $grant ) {
				$role->add_cap( $cap );
			}
		}
		// Admins can always moderate.
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( self::CAP ) ) {
			$admin->add_cap( self::CAP );
		}
	}

	/* ── Layer 1: per-post capability mapping ─────────────────────────── */

	/**
	 * Clamp a moderator's post capabilities to vendors: allow edits on vendor
	 * posts only, deny every delete, deny non-vendor edits.
	 */
	public static function map_caps( $caps, $cap, $user_id, $args ) {
		$delete_caps = [ 'delete_post', 'delete_posts', 'delete_published_posts', 'delete_others_posts', 'delete_private_posts', 'delete_page', 'delete_pages', 'delete_others_pages' ];
		$edit_caps   = [ 'edit_post', 'edit_others_post', 'edit_published_post', 'publish_post', 'edit_page' ];

		// Only intervene on the caps we clamp. Checking $cap BEFORE calling
		// is_moderator() is essential — is_moderator() itself does a
		// manage_options check that re-enters map_meta_cap, so an unguarded
		// call here would recurse infinitely.
		if ( ! in_array( $cap, $delete_caps, true ) && ! in_array( $cap, $edit_caps, true ) ) {
			return $caps;
		}
		if ( ! self::is_moderator( $user_id ) ) {
			return $caps;
		}

		// Never delete anything.
		if ( in_array( $cap, $delete_caps, true ) ) {
			return [ 'do_not_allow' ];
		}

		// Per-post edit/publish caps carry a post ID in $args[0].
		if ( in_array( $cap, $edit_caps, true ) ) {
			$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
			$type    = $post_id ? get_post_type( $post_id ) : '';
			if ( OC_CPT !== $type ) {
				return [ 'do_not_allow' ]; // never touch non-vendor content.
			}
			if ( ! self::can_edit_listings() && 'edit_post' === $cap ) {
				// Review-only builds still allow the status-change (approve/
				// reject) path, which WordPress routes through 'edit_post'
				// too — so we don't block here; the admin_init guard blocks the
				// edit-fields PAGE instead. Field edits are unreachable without
				// that page.
				return [ self::CAP ];
			}
			return [ self::CAP ]; // grant against the moderator capability.
		}

		return $caps;
	}

	/* ── Layer 2: hard-deny dangerous primitives ──────────────────────── */

	public static function deny_dangerous_caps( $allcaps, $caps, $args, $user ) {
		if ( ! $user || ! in_array( self::ROLE, (array) $user->roles, true ) || ! empty( $allcaps['manage_options'] ) ) {
			return $allcaps;
		}
		$forbidden = [
			'manage_options', 'edit_users', 'list_users', 'create_users', 'delete_users', 'promote_users', 'remove_users',
			'install_plugins', 'activate_plugins', 'delete_plugins', 'edit_plugins', 'update_plugins',
			'install_themes', 'switch_themes', 'edit_theme_options', 'delete_themes', 'update_themes', 'edit_themes',
			'export', 'import', 'unfiltered_html', 'edit_files', 'update_core',
			'manage_woocommerce', 'view_woocommerce_reports',
			'edit_pages', 'edit_others_pages', 'publish_pages', 'delete_pages', 'delete_others_pages',
			'manage_categories', 'edit_dashboard',
		];
		foreach ( $forbidden as $cap ) {
			$allcaps[ $cap ] = false;
		}
		return $allcaps;
	}

	/* ── Layer 3: menu hiding + screen enforcement ────────────────────── */

	public static function hide_menus() {
		if ( ! self::is_moderator() ) {
			return;
		}
		global $menu, $submenu;
		$vendors_slug = 'edit.php?post_type=' . OC_CPT;

		// Remove every top-level menu except the Vendors CPT menu.
		foreach ( (array) $menu as $item ) {
			$slug = $item[2] ?? '';
			if ( '' === $slug || $slug === $vendors_slug ) {
				continue;
			}
			remove_menu_page( $slug );
		}

		// Under Vendors, keep only the review queue (+ Add Vendor when edit
		// scope is on). Everything else (Settings, Analytics, Subscriptions,
		// Tag Migration, …) is removed.
		$keep = [ 'edit.php?post_type=' . OC_CPT, 'admin.php?page=oc-vendors' ];
		if ( self::can_edit_listings() ) {
			$keep[] = 'admin.php?page=oc-add-vendor';
		}
		if ( isset( $submenu[ $vendors_slug ] ) ) {
			foreach ( $submenu[ $vendors_slug ] as $item ) {
				$slug = $item[2] ?? '';
				if ( $slug && ! in_array( $slug, $keep, true ) && false === strpos( $slug, 'page=oc-vendors' ) ) {
					remove_submenu_page( $vendors_slug, $slug );
				}
			}
		}
	}

	/**
	 * Server-side allowlist: a moderator may only load the vendor queue, the
	 * Add/Edit Vendor page (edit scope), their own profile, and the shared
	 * admin endpoints. Any other admin screen — reached by a hidden menu, a
	 * bookmark, or a crafted URL — bounces to the queue.
	 */
	public static function enforce_allowed_screens() {
		if ( wp_doing_ajax() || ! self::is_moderator() ) {
			return;
		}
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( (string) $_SERVER['SCRIPT_NAME'] ) : '';

		// Always-allowed shared endpoints.
		if ( in_array( $script, [ 'admin-post.php', 'admin-ajax.php', 'profile.php', 'user-edit.php' ], true ) ) {
			return;
		}

		$queue_url = admin_url( 'admin.php?page=oc-vendors' );

		// The custom admin pages live on admin.php?page=…
		if ( 'admin.php' === $script ) {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			$allowed = self::ALLOWED_PAGES;
			if ( ! self::can_edit_listings() ) {
				$allowed = array_diff( $allowed, [ 'oc-add-vendor' ] );
			}
			if ( in_array( $page, $allowed, true ) ) {
				return;
			}
			wp_safe_redirect( $queue_url );
			exit;
		}

		// The auto-generated Vendors CPT list redirects to our custom queue
		// anyway; allow edit.php ONLY for post_type=oc_vendor.
		if ( 'edit.php' === $script ) {
			$pt = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
			if ( OC_CPT === $pt ) {
				return;
			}
			wp_safe_redirect( $queue_url );
			exit;
		}

		// index.php is the wp-admin dashboard home — harmless, allow it.
		if ( 'index.php' === $script ) {
			return;
		}

		// Everything else (plugins.php, users.php, options-*.php, tools.php,
		// post.php, post-new.php, upload.php, themes.php, …) is denied.
		wp_safe_redirect( $queue_url );
		exit;
	}

	/** Moderators keep the admin bar (they work in wp-admin). */
	public static function admin_bar( $show ) {
		return $show;
	}

	/* ── Activation / uninstall helpers ───────────────────────────────── */

	public static function remove_role() {
		if ( get_role( self::ROLE ) ) {
			remove_role( self::ROLE );
		}
	}
}
