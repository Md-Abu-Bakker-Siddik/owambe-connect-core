<?php
/**
 * Registers all [oc_*] shortcodes. Each shortcode renders a template
 * which the active theme can override at /owambe-connect/<template>.php.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Shortcodes {

	public function register() {
		add_shortcode( 'oc_hero_search',          [ $this, 'hero_search' ] );
		add_shortcode( 'oc_category_grid',        [ $this, 'category_grid' ] );
		add_shortcode( 'oc_about_blocks',         [ $this, 'about_blocks' ] );
		add_shortcode( 'oc_feature_row',          [ $this, 'feature_row' ] );
		add_shortcode( 'oc_vendor_request_fab',   [ $this, 'vendor_request_fab' ] );

		// Auto-inject the "Request a vendor" FAB on every front-end page.
		// The fab is silenced inside wp-admin, the Customizer preview, the
		// vendor dashboard (vendors don't need it), and on the request-success
		// landing to avoid double-rendering when the shortcode is also placed
		// manually somewhere.
		add_action( 'wp_footer', [ $this, 'maybe_render_fab' ], 50 );
		add_shortcode( 'oc_featured_vendors',     [ $this, 'featured_vendors' ] );
		add_shortcode( 'oc_directory',            [ $this, 'directory' ] );
		add_shortcode( 'oc_vendor_profile',       [ $this, 'vendor_profile' ] );
		add_shortcode( 'oc_register_form',        [ $this, 'register_form' ] );
		add_shortcode( 'oc_login_form',           [ $this, 'login_form' ] );
		add_shortcode( 'oc_vendor_dashboard',     [ $this, 'vendor_dashboard' ] );
		add_shortcode( 'oc_forgot_password',      [ $this, 'forgot_password' ] );
		add_shortcode( 'oc_reset_password',       [ $this, 'reset_password_form' ] );
		add_shortcode( 'oc_become_a_vendor_cta',  [ $this, 'become_a_vendor' ] );
		add_shortcode( 'oc_contact_form',         [ $this, 'contact_form' ] );
		add_shortcode( 'oc_how_it_works',         [ $this, 'how_it_works' ] );
		add_shortcode( 'oc_testimonials',         [ $this, 'testimonials' ] );
		add_shortcode( 'oc_faq',                  [ $this, 'faq' ] );
		add_shortcode( 'oc_stats',                [ $this, 'stats' ] );
		add_shortcode( 'oc_navbar',               [ $this, 'navbar' ] );
		add_shortcode( 'oc_footer',               [ $this, 'footer' ] );
		add_shortcode( 'oc_breadcrumb',           [ $this, 'breadcrumb' ] );
		add_shortcode( 'oc_client_login',         [ $this, 'client_login' ] );
		add_shortcode( 'oc_client_dashboard',     [ $this, 'client_dashboard' ] );
		add_shortcode( 'oc_safety_info',          [ $this, 'safety_info' ] );

		// Auto-inject a burgundy breadcrumb band on a small set of seeded
		// content pages (about, terms, privacy, contact) so they get the
		// branded page header without anyone having to edit content. Skipped
		// for pages already containing [oc_breadcrumb] or built with Elementor.
		add_filter( 'the_content', [ $this, 'maybe_inject_breadcrumb' ], 5 );

		// Phase 2 (W2 mini-site): vendor profiles now live at the native CPT URL
		// /vendor/{slug}/ — served by the THEME's single-oc_vendor.php via the
		// normal template hierarchy. The two Phase 1 hooks that rerouted
		// get_permalink() to /vendor-profile/?v={slug} are gone; instead we
		// 301 the OLD query-param URL to the clean one so indexed links and
		// old shares keep working.
		add_action( 'template_redirect', [ $this, 'redirect_legacy_query_url' ] );

		// Serve the plugin's bundled single-oc_vendor.php as a FALLBACK when the
		// active theme ships no override — so /vendor/{slug}/ renders the profile
		// even after a theme swap (the owambe-connect theme provides its own).
		add_filter( 'template_include', [ $this, 'use_single_template' ] );

		// Auth-state redirects (must run before headers are sent).
		add_action( 'template_redirect', [ $this, 'redirect_logged_in_from_auth_pages' ] );
		add_action( 'template_redirect', [ $this, 'redirect_logged_out_from_dashboard' ] );

		// Suppress the duplicate page-title H1 that themes render above the
		// content on our shortcode pages. Each of these shortcodes already
		// includes its own properly styled hero heading inside the template,
		// so the theme's the_title() output is redundant (and breaks SEO by
		// shipping two H1s on the same page).
		add_filter( 'the_title', [ $this, 'suppress_duplicate_page_title' ], 10, 2 );
	}

	public function suppress_duplicate_page_title( $title, $post_id = 0 ) {
		// Only touch the front-end page title — leave admin lists, menus,
		// breadcrumbs, document titles, and anything not in the main loop
		// completely alone.
		if ( is_admin() || ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
			return $title;
		}
		if ( (int) $post_id !== (int) get_queried_object_id() ) {
			return $title;
		}
		$slug = (string) get_post_field( 'post_name', $post_id );
		$slugs = apply_filters( 'oc_suppress_page_title_slugs', [
			'apply',
			'vendor-login',
			'vendor-dashboard',
			'forgot-password',
			'reset-password',
			'contact',
			'vendors',
			'become-a-vendor',
			'client-login',
			'client-dashboard',
		] );
		return in_array( $slug, $slugs, true ) ? '' : $title;
	}

	/** Send logged-in users away from the login/apply pages straight to their dashboard. */
	public function redirect_logged_in_from_auth_pages() {
		if ( is_admin() || ! is_user_logged_in() || ! is_page() ) {
			return;
		}
		$current_id = (int) get_queried_object_id();
		if ( ! $current_id ) {
			return;
		}
		$login_page = get_page_by_path( 'vendor-login' );
		$apply_page = get_page_by_path( 'apply' );
		$auth_ids   = array_filter( [
			$login_page ? (int) $login_page->ID : 0,
			$apply_page ? (int) $apply_page->ID : 0,
		] );
		if ( in_array( $current_id, $auth_ids, true ) ) {
			// Role-aware since Phase 2: a signed-in CLIENT hitting the vendor
			// login page must not be bounced into the vendor dashboard (whose
			// create-on-save would silently promote them to a vendor).
			$user = wp_get_current_user();
			if ( ! in_array( OC_ROLE, (array) $user->roles, true ) && in_array( OC_CLIENT_ROLE, (array) $user->roles, true ) ) {
				wp_safe_redirect( oc_page_url( 'client-dashboard' ) );
			} else {
				wp_safe_redirect( oc_page_url( 'vendor-dashboard' ) );
			}
			exit;
		}
	}

	/** Send logged-out visitors of the vendor dashboard to the login page (with a return path). */
	public function redirect_logged_out_from_dashboard() {
		if ( is_admin() || is_user_logged_in() || ! is_page() ) {
			return;
		}
		$current_id = (int) get_queried_object_id();
		if ( ! $current_id ) {
			return;
		}
		$dashboard_page = get_page_by_path( 'vendor-dashboard' );
		if ( ! $dashboard_page || (int) $dashboard_page->ID !== $current_id ) {
			return;
		}
		$login_url = oc_page_url( 'vendor-login' );
		$return_to = oc_page_url( 'vendor-dashboard' );
		wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( $return_to ), $login_url ) );
		exit;
	}

	/**
	 * Fallback single template for the vendor CPT. The active theme's own
	 * single-oc_vendor.php always wins (locate_template returns non-empty);
	 * we only step in when a theme provides none, so the profile still renders.
	 */
	public function use_single_template( $template ) {
		if ( is_singular( OC_CPT ) && '' === locate_template( 'single-oc_vendor.php' ) ) {
			$fallback = OC_TEMPLATE_DIR . 'single-oc_vendor.php';
			if ( file_exists( $fallback ) ) {
				return $fallback;
			}
		}
		return $template;
	}

	/**
	 * 301 the Phase 1 URL shape (/vendor-profile/?v={slug}) to the native
	 * /vendor/{slug}/ permalink. Preserves indexed URLs + old shared links.
	 */
	public function redirect_legacy_query_url() {
		if ( is_admin() || ! is_page( 'vendor-profile' ) ) {
			return;
		}
		$slug = isset( $_GET['v'] ) ? sanitize_title( wp_unslash( $_GET['v'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $slug ) {
			return;
		}
		$vendor = get_page_by_path( $slug, OBJECT, OC_CPT );
		if ( ! $vendor || OC_STATUS_APPROVED !== $vendor->post_status ) {
			return; // Let the page render its own not-found state.
		}
		wp_safe_redirect( get_permalink( $vendor ), 301 );
		exit;
	}

	public function hero_search( $atts = [] ) {
		$atts = shortcode_atts( [
			'eyebrow'           => '',
			'heading'           => '',
			'subheading'        => '',
			'button_text'       => '',
			'button_url'        => '',
			'show_search'       => 'yes',
			'show_popular'      => 'yes',
			'search_btn_label'  => '',
			'popular_label'     => '',
			'bg_image_url'      => '',
		], $atts );
		return oc_get_template( 'shortcode-hero-search.php', $atts );
	}

	public function category_grid( $atts ) {
		$atts = shortcode_atts( [
			'count'      => 12,
			'heading'    => '',
			'subheading' => '',
			'layout'     => 'scroll',  // 'scroll' (horizontal carousel) | 'grid'
			'card_style' => 'images',  // 'images' (Style 1, production default) | 'icons' (Style 2)
		], $atts );
		$atts['limit'] = (int) $atts['count'];
		return oc_get_template( 'shortcode-category-grid.php', $atts );
	}

	/**
	 * About-blocks shortcode — renders the same template the Elementor
	 * widget uses, but invokable from any page without Elementor active.
	 * Per client feedback, the Vision/Mission/Story defaults are populated
	 * inside the template / widget so a plain [oc_about_blocks] tag on the
	 * About page renders the agreed copy with no further configuration.
	 */
	public function about_blocks( $atts ) {
		$atts = shortcode_atts( [
			'show_story'     => 'yes',
			'show_mission'   => 'yes',
			'show_values'    => 'yes',
			'show_timeline'  => 'no',
			'show_cta'       => 'yes',

			'story_eyebrow'  => __( 'Our story', 'owambe-connect-core' ),
			'story_heading'  => __( 'Our story', 'owambe-connect-core' ),
			'story_body'     => __( "At Owambe Connect, we believe culturally rich celebrations deserve to be seen, valued, and beautifully represented.\n\nWe created Owambe Connect to make it easier for people planning weddings, parties, corporate events, traditional ceremonies, birthdays, and community celebrations to discover trusted vendors who truly understand the beauty of multicultural events.\n\nFrom photographers and caterers to decorators, DJs, makeup artists, event planners, venues, and beyond, our platform connects clients with vendors who bring culture, creativity, and unforgettable experiences to life.\n\nBut Owambe Connect is more than just a directory. It is a growing community built to support visibility, connection, collaboration, and growth for vendors across the UK's diverse event industry.", 'owambe-connect-core' ),

			'mission_title'  => __( 'Our mission', 'owambe-connect-core' ),
			'mission_text'   => __( 'To connect people with trusted vendors, simplify event planning, and help businesses grow within the communities they serve.', 'owambe-connect-core' ),

			'vision_title'   => __( 'Our vision', 'owambe-connect-core' ),
			'vision_text'    => __( "Owambe Connect was created to give culturally rich events the visibility, elegance, and trusted vendor network they deserve. Our vision is to build the UK's leading platform for planning, discovering and connecting with exceptional event vendors across African, Caribbean, South Asian, multicultural, luxury, and contemporary celebrations.", 'owambe-connect-core' ),

			'values_heading' => __( 'What we stand for', 'owambe-connect-core' ),
			'cta_heading'    => __( 'Ready to find your vendors?', 'owambe-connect-core' ),
			'cta_text'       => __( 'Browse hundreds of trusted event vendors across the UK — or list your business with us.', 'owambe-connect-core' ),
			'cta_primary_text'   => __( 'Find vendors', 'owambe-connect-core' ),
			'cta_secondary_text' => __( 'List your business', 'owambe-connect-core' ),
		], $atts );

		// Reworded "What we stand for" defaults from the feedback xlsx.
		$atts['values_items'] = [
			[ 'icon' => '🤝', 'title' => __( 'Cultural fluency', 'owambe-connect-core' ),
			  'description' => __( 'Every vendor genuinely understands the communities they serve.', 'owambe-connect-core' ) ],
			[ 'icon' => '💬', 'title' => __( 'Direct, no middlemen', 'owambe-connect-core' ),
			  'description' => __( 'You message vendors on their channels — WhatsApp, Instagram, email. We don\'t sit between you and your booking.', 'owambe-connect-core' ) ],
			[ 'icon' => '✨', 'title' => __( 'Quality over volume', 'owambe-connect-core' ),
			  'description' => __( 'Every listing is reviewed before going live. We\'d rather have 50 trusted vendors than 5,000 noisy ones.', 'owambe-connect-core' ) ],
			[ 'icon' => '🌍', 'title' => __( 'Built for UK\'s real mix', 'owambe-connect-core' ),
			  'description' => __( 'Built for UK\'s real mix, celebrating African, Caribbean, South Asian, multicultural, luxury, and contemporary events.', 'owambe-connect-core' ) ],
		];

		return oc_get_template( 'shortcode-about-blocks.php', $atts );
	}

	/**
	 * Feature Row — 2-column image + text section. Accepts inner content
	 * as the body, so authors can keep inline markup (links, em, strong)
	 * inside the body text. Used by the new OC Feature Row Elementor widget,
	 * but also works as a plain shortcode on non-Elementor pages.
	 */
	public function feature_row( $atts, $content = '' ) {
		$atts = shortcode_atts( [
			'eyebrow'        => '',
			'heading'        => '',
			'cta_text'       => '',
			'cta_url'        => '',
			'image'          => '',
			'image_position' => 'left', // 'left' | 'right'
		], $atts, 'oc_feature_row' );

		// `$content` is the body passed between the shortcode tags. Strip
		// PHP-added autop wrapping the shortcode itself, but keep the
		// author's intentional markup intact.
		$atts['body'] = trim( (string) $content );
		return oc_get_template( 'shortcode-feature-row.php', $atts );
	}

	/**
	 * Direct shortcode invocation of the "Request a vendor" FAB. Most sites
	 * will rely on the wp_footer auto-injection (see maybe_render_fab) so
	 * the button shows on every page without needing to be placed manually.
	 */
	public function vendor_request_fab( $atts ) {
		static $rendered_once = false;
		if ( $rendered_once ) {
			return ''; // never double-render even if the shortcode appears twice
		}
		$rendered_once = true;
		return oc_get_template( 'shortcode-vendor-request-fab.php', [] );
	}

	/**
	 * wp_footer callback. Renders the FAB on every front-end page unless
	 * the request is admin / customizer / dashboard / a page whose
	 * post_content already contains the [oc_vendor_request_fab] shortcode
	 * (so manual placement still wins and never doubles up).
	 */
	public function maybe_render_fab() {
		if ( is_admin() || is_customize_preview() ) {
			return;
		}
		// Hide on the vendor dashboard — vendors are creators, not customers.
		if ( is_page( 'vendor-dashboard' ) || is_page( 'vendor-login' ) || is_page( 'apply' ) ) {
			return;
		}
		// Skip if the current post already includes the shortcode.
		$post = get_post();
		if ( $post && is_string( $post->post_content ) && has_shortcode( $post->post_content, 'oc_vendor_request_fab' ) ) {
			return;
		}
		// Allow themes / agencies to opt the page out without code changes.
		if ( ! apply_filters( 'oc_show_vendor_request_fab', true, $post ) ) {
			return;
		}
		echo $this->vendor_request_fab( [] ); // phpcs:ignore — trusted template
	}

	public function featured_vendors( $atts ) {
		$default = (int) oc_get_setting( 'featured_count', 6 );
		$atts    = shortcode_atts( [
			'count'         => $default,
			'heading'       => '',
			'subheading'    => '',
			'view_all_text' => '',
			'view_all_url'  => '',
		], $atts );
		$atts['query'] = OC_Queries::featured( (int) $atts['count'] );
		return oc_get_template( 'shortcode-featured-vendors.php', $atts );
	}

	public function directory( $atts = [] ) {
		$atts = shortcode_atts( [
			'per_page'     => 12,
			'show_filters' => 'yes',
			'heading'      => '',
			'subheading'   => '',
		], $atts );
		$atts['query'] = OC_Queries::directory( [
			'paged'    => max( 1, (int) get_query_var( 'paged', isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ) ),
			'category' => isset( $_GET['cat'] )         ? sanitize_title( $_GET['cat'] )                    : '',
			'search'   => isset( $_GET['vendor_name'] ) ? sanitize_text_field( wp_unslash( $_GET['vendor_name'] ) ) : '',
			'location' => isset( $_GET['location'] )    ? sanitize_text_field( wp_unslash( $_GET['location'] ) ) : '',
			'city'     => isset( $_GET['city'] )        ? sanitize_text_field( wp_unslash( $_GET['city'] ) )     : '',
			'cultural' => isset( $_GET['cultural'] )    ? sanitize_key( wp_unslash( $_GET['cultural'] ) )        : '',
			'nigerian' => ! empty( $_GET['nigerian'] )  ? '1'                                                    : '',
			'per_page' => (int) $atts['per_page'],
		] );
		return oc_get_template( 'shortcode-directory.php', $atts );
	}

	public function vendor_profile( $atts = [] ) {
		$atts = shortcode_atts( [
			'id'   => 0,
			'slug' => '',
		], $atts );

		$post_id = 0;

		// 1. Explicit id attribute.
		if ( ! empty( $atts['id'] ) ) {
			$candidate = (int) $atts['id'];
			if ( $candidate && OC_CPT === get_post_type( $candidate ) ) {
				$post_id = $candidate;
			}
		}

		// 2. Explicit slug attribute.
		if ( ! $post_id && ! empty( $atts['slug'] ) ) {
			$post = get_page_by_path( sanitize_title( $atts['slug'] ), OBJECT, OC_CPT );
			if ( $post ) $post_id = (int) $post->ID;
		}

		// 3. URL query param: ?v=<slug> or ?vendor=<slug>.
		if ( ! $post_id ) {
			$qs = '';
			if ( ! empty( $_GET['v'] ) )      $qs = wp_unslash( $_GET['v'] );
			elseif ( ! empty( $_GET['vendor'] ) ) $qs = wp_unslash( $_GET['vendor'] );
			if ( $qs ) {
				$post = get_page_by_path( sanitize_title( $qs ), OBJECT, OC_CPT );
				if ( $post ) $post_id = (int) $post->ID;
			}
		}

		// 4. Inside the vendor CPT loop (legacy fallback).
		if ( ! $post_id ) {
			$current = get_the_ID();
			if ( $current && OC_CPT === get_post_type( $current ) ) {
				$post_id = (int) $current;
			}
		}

		if ( ! $post_id ) {
			return $this->vendor_not_found( __( 'No vendor selected.', 'owambe-connect-core' ) );
		}

		// Only show approved vendors to the public; let admins preview pending/rejected.
		$status = get_post_status( $post_id );
		if ( OC_STATUS_APPROVED !== $status && ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->vendor_not_found( __( 'This vendor isn\'t available right now.', 'owambe-connect-core' ) );
		}

		// Phase 2 — profile-view tracking. This is the single funnel every
		// public render passes through (native /vendor/{slug}/, legacy ?v=,
		// and shortcode embeds). Self-views + bots are excluded inside.
		if ( class_exists( 'OC_Tracking' ) ) {
			OC_Tracking::maybe_record_profile_view( $post_id );
		}

		return oc_get_template( 'shortcode-vendor-profile.php', [ 'post_id' => $post_id ] );
	}

	/** Phase 2 — client (event host) sign-in page. */
	public function client_login( $atts = [] ) {
		$atts = shortcode_atts( [
			'heading'      => '',
			'subheading'   => '',
			'redirect_url' => '',
		], $atts );
		return oc_get_template( 'shortcode-client-login.php', $atts );
	}

	/** Phase 2 — client dashboard (saved vendors, recently contacted). */
	public function client_dashboard( $atts = [] ) {
		return oc_get_template( 'shortcode-client-dashboard.php', [] );
	}

	private function vendor_not_found( $reason ) {
		$dir = oc_page_url( 'vendors' );
		ob_start();
		?>
		<section class="oc-vp-404" style="padding:60px 20px;text-align:center;max-width:560px;margin:0 auto;">
			<div style="font-size:48px;line-height:1;color:#C9A961;margin-bottom:14px;">🔍</div>
			<h2 style="font-family:Georgia,serif;color:#6E0F2C;margin:0 0 8px;font-size:1.6rem;"><?php esc_html_e( 'Vendor not found', 'owambe-connect-core' ); ?></h2>
			<p style="color:#6B6361;margin:0 0 20px;"><?php echo esc_html( $reason ); ?> <?php esc_html_e( 'The link may be broken or the vendor may no longer be listed.', 'owambe-connect-core' ); ?></p>
			<a href="<?php echo esc_url( $dir ); ?>" style="display:inline-block;background:#6E0F2C;color:#fff;padding:11px 22px;border-radius:6px;text-decoration:none;font-weight:600;"><?php esc_html_e( 'Browse all vendors', 'owambe-connect-core' ); ?></a>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public function register_form( $atts = [] ) {
		if ( is_user_logged_in() ) {
			return '<div class="oc-notice">' . esc_html__( 'You are already signed in.', 'owambe-connect-core' )
				. ' <a href="' . esc_url( oc_page_url( 'vendor-dashboard' ) ) . '">' . esc_html__( 'Go to your dashboard', 'owambe-connect-core' ) . '</a>.</div>';
		}
		$atts = shortcode_atts( [
			'heading'      => '',
			'subheading'   => '',
			'redirect_url' => '',
			'button_text'  => '',
		], $atts );
		return oc_get_template( 'shortcode-register-form.php', $atts );
	}

	public function login_form( $atts = [] ) {
		// Logged-in users are redirected to dashboard via template_redirect hook (above).
		// If they somehow reach the shortcode while logged in (e.g. shortcode placed on a
		// different page), show a friendly notice instead of failing.
		if ( is_user_logged_in() ) {
			$user      = wp_get_current_user();
			$dash_slug = ( ! in_array( OC_ROLE, (array) $user->roles, true ) && in_array( OC_CLIENT_ROLE, (array) $user->roles, true ) ) ? 'client-dashboard' : 'vendor-dashboard';
			return '<div class="oc-notice">' . esc_html__( 'You are already signed in.', 'owambe-connect-core' )
				. ' <a href="' . esc_url( oc_page_url( $dash_slug ) ) . '">' . esc_html__( 'Go to your dashboard', 'owambe-connect-core' ) . '</a>.</div>';
		}
		$atts = shortcode_atts( [
			'heading'      => '',
			'subheading'   => '',
			'redirect_url' => '',
			'button_text'  => '',
		], $atts );
		return oc_get_template( 'shortcode-login-form.php', $atts );
	}

	public function vendor_dashboard( $atts = [] ) {
		if ( ! is_user_logged_in() ) {
			// Logged-out users are normally caught by the template_redirect hook
			// (redirect_logged_out_from_dashboard). This branch is a defensive
			// fallback for cases where the shortcode is embedded outside the
			// canonical /vendor-dashboard/ page — we bounce them to the login
			// page via meta-refresh + JS rather than render a bare notice.
			$login_url = add_query_arg(
				'redirect_to',
				rawurlencode( oc_page_url( 'vendor-dashboard' ) ),
				oc_page_url( 'vendor-login' )
			);
			return '<meta http-equiv="refresh" content="0;url=' . esc_attr( $login_url ) . '">'
				. '<script>window.location.replace(' . wp_json_encode( $login_url ) . ');</script>'
				. '<noscript><div class="oc-notice">' . esc_html__( 'Please log in to access your dashboard.', 'owambe-connect-core' )
				. ' <a class="oc-btn oc-btn-primary" href="' . esc_url( $login_url ) . '">' . esc_html__( 'Vendor Login', 'owambe-connect-core' ) . '</a></div></noscript>';
		}
		$atts = shortcode_atts( [
			'note' => '',
		], $atts );
		$atts['vendor_post'] = oc_get_current_vendor_post();
		return oc_get_template( 'shortcode-vendor-dashboard.php', $atts );
	}

	public function forgot_password( $atts = [] ) {
		// Logged-in users get bounced to their dashboard — they're already in.
		if ( is_user_logged_in() ) {
			return '<div class="oc-notice">' . esc_html__( 'You are already signed in.', 'owambe-connect-core' )
				. ' <a href="' . esc_url( oc_page_url( 'vendor-dashboard' ) ) . '">' . esc_html__( 'Go to your dashboard', 'owambe-connect-core' ) . '</a>.</div>';
		}
		$atts = shortcode_atts( [
			'heading'     => '',
			'subheading'  => '',
			'button_text' => '',
		], $atts );
		return oc_get_template( 'shortcode-forgot-password.php', $atts );
	}

	public function reset_password_form( $atts = [] ) {
		$atts = shortcode_atts( [
			'heading'     => '',
			'subheading'  => '',
			'button_text' => '',
		], $atts );
		return oc_get_template( 'shortcode-reset-password.php', $atts );
	}

	public function become_a_vendor( $atts = [] ) {
		$atts = shortcode_atts( [
			'eyebrow'         => '',
			'heading'         => '',
			'subheading'      => '',
			'button_text'     => '',
			'button_url'      => '',
			'secondary_text'  => '',
			'secondary_url'   => '',
			'bg_color'        => '',
			'show_features'   => 'yes',
			'show_steps'      => 'yes',
		], $atts );
		return oc_get_template( 'shortcode-become-a-vendor.php', $atts );
	}

	public function contact_form( $atts = [] ) {
		$atts = shortcode_atts( [
			'heading'         => '',
			'subheading'      => '',
			'recipient_email' => '',
			'button_text'     => '',
			'show_info'       => 'yes',
			'info_heading'    => '',
			'info_email'      => '',
			'info_phone'      => '',
			'info_whatsapp'   => '',
			'info_address'    => '',
			'info_hours'      => '',
			'info_response'   => '',
		], $atts );
		return oc_get_template( 'shortcode-contact-form.php', $atts );
	}

	public function how_it_works( $atts = [] ) {
		$atts = shortcode_atts( [
			'heading'    => '',
			'subheading' => '',
			'steps'      => '',
		], $atts );
		return oc_get_template( 'shortcode-how-it-works.php', $atts );
	}

	/**
	 * Website Safety Information — general safety guidance for clients/visitors.
	 * Content is controllable via the `safety_intro` option (oc_settings) and the
	 * `oc_safety_items` filter; the seeded /safety/ page renders this shortcode.
	 */
	public function safety_info( $atts = [] ) {
		$atts = shortcode_atts( [
			'heading'    => '',
			'subheading' => '',
		], $atts );
		return oc_get_template( 'shortcode-safety-info.php', $atts );
	}

	public function testimonials( $atts = [] ) {
		$atts = shortcode_atts( [
			'heading'    => '',
			'subheading' => '',
			'items'      => '',
		], $atts );
		return oc_get_template( 'shortcode-testimonials.php', $atts );
	}

	public function faq( $atts = [] ) {
		$atts = shortcode_atts( [
			'heading'    => '',
			'subheading' => '',
			'items'      => '',
		], $atts );
		return oc_get_template( 'shortcode-faq.php', $atts );
	}

	public function stats( $atts = [] ) {
		$atts = shortcode_atts( [
			'heading'    => '',
			'subheading' => '',
			'items'      => '',
		], $atts );
		return oc_get_template( 'shortcode-stats.php', $atts );
	}

	public function navbar( $atts = [] ) {
		$atts = shortcode_atts( [
			'logo_type'      => 'text',
			'logo_image'     => '',
			'logo_text_mark' => 'OWAMBE',
			'logo_text_sub'  => 'Connect',
			'logo_link'      => home_url( '/' ),
			'menu_id'        => '',
			'show_login'     => 'yes',
			'login_text'     => '',
			'show_cta'       => 'yes',
			'cta_text'       => '',
			'cta_url'        => '',
			'show_dashboard' => 'yes',
			'show_logout'    => 'yes',
			'sticky'         => 'yes',
			'show_border'    => 'yes',
		], $atts );
		// Cast yes/no strings to bool for the template.
		foreach ( [ 'show_login', 'show_cta', 'show_dashboard', 'show_logout', 'sticky', 'show_border' ] as $k ) {
			$atts[ $k ] = 'yes' === $atts[ $k ];
		}
		return oc_get_template( 'shortcode-navbar.php', $atts );
	}

	public function footer( $atts = [] ) {
		// Minimal shortcode passthrough — repeaters can't be serialised in attributes,
		// so the shortcode renders the template with defaults from the widget when used inline.
		return oc_get_template( 'shortcode-footer.php', [] );
	}

	/**
	 * Branded burgundy page header — single-line title in a slim band.
	 * Usage: [oc_breadcrumb] (uses page title) or [oc_breadcrumb title="About us"].
	 */
	public function breadcrumb( $atts = [] ) {
		$atts = shortcode_atts( [ 'title' => '' ], $atts, 'oc_breadcrumb' );
		// Read post_title directly from the DB so our own suppress_duplicate_page_title
		// filter doesn't wipe out the value we want to show here.
		$fallback = (string) get_post_field( 'post_title', get_queried_object_id() );
		$title    = $atts['title'] !== '' ? $atts['title'] : $fallback;
		if ( '' === trim( (string) $title ) ) return '';
		return '<section class="oc-breadcrumb"><div class="oc-container"><h1 class="oc-breadcrumb__title">' . esc_html( $title ) . '</h1></div></section>';
	}

	/**
	 * Prepend the breadcrumb band to a small set of content pages so admin
	 * doesn't have to remember to drop the shortcode on every legal / info
	 * page. Skips Elementor-built pages (they'd render the section twice)
	 * and pages that already include the shortcode.
	 */
	public function maybe_inject_breadcrumb( $content ) {
		if ( is_admin() || ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$id = get_queried_object_id();
		if ( ! $id ) return $content;
		$slug = (string) get_post_field( 'post_name', $id );
		$allowed = apply_filters( 'oc_breadcrumb_auto_pages', [ 'about', 'terms', 'privacy', 'contact' ] );
		if ( ! in_array( $slug, $allowed, true ) ) return $content;
		if ( has_shortcode( (string) $content, 'oc_breadcrumb' ) ) return $content;
		// Elementor takes over rendering for builder pages — skip injection.
		if ( get_post_meta( $id, '_elementor_edit_mode', true ) === 'builder' ) return $content;
		// Bypass our own the_title filter — we want the real page title here,
		// not the empty string the filter returns for auth/info pages.
		$title = (string) get_post_field( 'post_title', $id );
		if ( '' === trim( $title ) ) return $content;
		return '<section class="oc-breadcrumb"><div class="oc-container"><h1 class="oc-breadcrumb__title">' . esc_html( $title ) . '</h1></div></section>' . $content;
	}
}
