<?php
/**
 * Demo content importer — one-click admin tool.
 *
 * Creates marketplace pages with OC widgets, primary menu, vendor categories,
 * AND sample vendor profiles with mixed statuses (published / pending / rejected).
 *
 * Lives at Appearance → Import Demo. User-triggered only — never auto-runs.
 *
 * Re-import behaviour:
 * - **Pages**: existing pages with the same slug get their Elementor content
 *   REPLACED with the demo content. No duplicate pages are created. WARNING:
 *   this overwrites custom edits on those pages.
 * - **Sample vendors**: existing vendor posts with the same slug get their
 *   content + meta REPLACED. No duplicate vendors created.
 * - **Menu items / categories**: skipped if they exist (no duplicate menu items).
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

class OC_Page_Seeder {

	const OPTION    = 'oc_demo_imported_at';
	const MENU_SLUG = 'oc-import-demo';
	const ACTION    = 'oc_import_demo';

	public function register() {
		add_action( 'admin_menu',                 [ $this, 'admin_menu' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_import' ] );
	}

	public function admin_menu() {
		add_theme_page(
			__( 'Import Demo', 'owambe-connect-core' ),
			__( 'Import Demo', 'owambe-connect-core' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_admin_page' ]
		);
	}

	public function render_admin_page() {
		$imported_at = get_option( self::OPTION );
		$notice      = isset( $_GET['oc_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['oc_notice'] ) ) : '';
		$error       = isset( $_GET['oc_error'] )  ? sanitize_text_field( wp_unslash( $_GET['oc_error'] ) )  : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Owambe Connect — Import Demo', 'owambe-connect-core' ); ?></h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<div class="card" style="max-width: 780px; padding: 24px 28px;">

				<h2 style="margin-top: 0;"><?php esc_html_e( 'One-click marketplace setup', 'owambe-connect-core' ); ?></h2>
				<p><?php esc_html_e( 'Place OC widgets into your existing marketplace pages, configure the primary navigation, add starter vendor categories, and seed sample vendor profiles so the directory looks alive.', 'owambe-connect-core' ); ?></p>

				<h3 style="margin-top: 22px;"><?php esc_html_e( 'What this will do', 'owambe-connect-core' ); ?></h3>
				<ul style="list-style: disc; padding-left: 22px;">
					<li><strong><?php esc_html_e( '9 marketplace pages', 'owambe-connect-core' ); ?></strong> — populates Home, Find Vendors, Become a Vendor, Vendor Login, Apply, Vendor Dashboard, Vendor Profile, About, Contact with their default OC widgets. <strong><?php esc_html_e( 'Existing pages are NOT duplicated', 'owambe-connect-core' ); ?></strong> — their content is replaced with the demo widgets. Pages that don\'t yet exist are created.</li>
					<li><strong><?php esc_html_e( 'Primary menu', 'owambe-connect-core' ); ?></strong> — Find Vendors · Become a Vendor · About · Contact (only added if menu is empty)</li>
					<li><strong><?php esc_html_e( '10 vendor categories', 'owambe-connect-core' ); ?></strong> — Catering, Photography, Videography, Decor &amp; Styling, DJ &amp; Live Music, Venues, Makeup &amp; Hair, Cakes &amp; Desserts, Event Planners, Attire &amp; Aso Ebi (skipped if they already exist)</li>
					<li><strong><?php esc_html_e( '10 sample vendor profiles', 'owambe-connect-core' ); ?></strong> — mixed statuses (8 approved &amp; live, 1 pending review, 1 rejected). Existing demo vendors with the same slug are <strong>updated</strong>, not duplicated.</li>
					<li><?php esc_html_e( 'Sets the static front page to the Home page and forces all marketplace pages to Elementor Full Width.', 'owambe-connect-core' ); ?></li>
				</ul>

				<p style="margin-top: 18px; padding: 12px 14px; background: #fcf0f1; border-left: 4px solid #a02e2a;">
					<strong><?php esc_html_e( 'Warning — re-import replaces page content.', 'owambe-connect-core' ); ?></strong>
					<?php esc_html_e( 'If you have edited any of the marketplace pages (Home, About, Contact, etc.) in Elementor, re-importing will OVERWRITE those edits with the latest demo widgets. Re-import is the intended way to refresh demo content; use it deliberately.', 'owambe-connect-core' ); ?>
				</p>

				<?php if ( $imported_at ) : ?>
					<p style="margin-top: 14px; padding: 10px 14px; background: #f0f6fc; border-left: 4px solid #2271b1;">
						<strong><?php esc_html_e( 'Last imported:', 'owambe-connect-core' ); ?></strong>
						<?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' \a\t ' . get_option( 'time_format' ), $imported_at ) ); ?>
					</p>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;"
				      onsubmit="return confirm('<?php echo esc_js( __( 'Run the demo importer? Existing marketplace pages will be overwritten with the latest demo widgets. Continue?', 'owambe-connect-core' ) ); ?>');">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
					<?php wp_nonce_field( self::ACTION, 'oc_import_nonce' ); ?>
					<button type="submit" class="button button-primary button-hero">
						<?php echo esc_html( $imported_at ? __( 'Re-import demo content', 'owambe-connect-core' ) : __( 'Import demo content', 'owambe-connect-core' ) ); ?>
					</button>
				</form>

			</div>
		</div>
		<?php
	}

	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'owambe-connect-core' ) );
		}
		check_admin_referer( self::ACTION, 'oc_import_nonce' );

		$redirect_base = add_query_arg( 'page', self::MENU_SLUG, admin_url( 'themes.php' ) );

		try {
			$results = $this->run_full_import();
			update_option( self::OPTION, current_time( 'mysql' ) );

			$msg = sprintf(
				/* translators: pages created, pages overwritten, categories, vendors */
				__( 'Demo imported. Pages created: %1$d · overwritten: %2$d · categories added: %3$d · sample vendors: %4$d.', 'owambe-connect-core' ),
				(int) $results['pages_created'],
				(int) $results['pages_overwritten'],
				(int) $results['categories'],
				(int) $results['vendors']
			);
			$redirect = add_query_arg( 'oc_notice', rawurlencode( $msg ), $redirect_base );
		} catch ( \Throwable $e ) {
			$redirect = add_query_arg( 'oc_error', rawurlencode( $e->getMessage() ), $redirect_base );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	private function run_full_import() {
		$pages_created     = 0;
		$pages_overwritten = 0;

		foreach ( $this->page_specs() as $slug => $spec ) {
			$elementor_data = $this->build_elementor_data_for_page( $spec['widgets'] );
			$existing       = get_page_by_path( $slug );

			if ( $existing ) {
				// Page exists — REPLACE its content with the demo widgets.
				$this->set_page_elementor( $existing->ID, $elementor_data );
				$pages_overwritten++;
				continue;
			}

			// Page doesn't exist — create it with the demo content.
			$page_id = wp_insert_post( [
				'post_title'   => $spec['title'],
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
			], true );

			if ( is_wp_error( $page_id ) || ! $page_id ) {
				continue;
			}

			$this->set_page_elementor( $page_id, $elementor_data );
			$pages_created++;
		}

		// Set static front page.
		$home = get_page_by_path( 'home' );
		if ( $home ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home->ID );
		}

		// Primary menu — only adds items if menu is empty (no duplicate links on re-run).
		$this->ensure_primary_menu();

		// Vendor categories — skip if already exist.
		$categories = $this->ensure_vendor_categories();

		// Sample vendor profiles — update if slug exists (no duplicates).
		$vendors = $this->ensure_sample_vendors();

		// Clear Elementor cache so new templates render immediately.
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		return compact( 'pages_created', 'pages_overwritten', 'categories', 'vendors' );
	}

	private function page_specs() {
		return [
			'home' => [
				'title'   => __( 'Home', 'owambe-connect-core' ),
				'widgets' => [
					'oc_hero_search',
					'oc_category_grid',
					'oc_featured_vendors',
					'oc_stats',
					'oc_how_it_works',
					'oc_testimonials',
					'oc_become_a_vendor_cta',
				],
			],
			'vendors' => [
				'title'   => __( 'Find Vendors', 'owambe-connect-core' ),
				'widgets' => [ 'oc_directory' ],
			],
			'become-a-vendor' => [
				'title'   => __( 'Become a Vendor', 'owambe-connect-core' ),
				'widgets' => [ 'oc_become_a_vendor_cta' ],
			],
			'vendor-login' => [
				'title'   => __( 'Vendor Login', 'owambe-connect-core' ),
				'widgets' => [ 'oc_login_form' ],
			],
			'apply' => [
				'title'   => __( 'Apply', 'owambe-connect-core' ),
				'widgets' => [ 'oc_register_form' ],
			],
			'vendor-dashboard' => [
				'title'   => __( 'Vendor Dashboard', 'owambe-connect-core' ),
				'widgets' => [ 'oc_vendor_dashboard' ],
			],
			'vendor-profile' => [
				'title'   => __( 'Vendor Profile', 'owambe-connect-core' ),
				'widgets' => [ 'oc_vendor_profile' ],
			],
			'about' => [
				'title'   => __( 'About', 'owambe-connect-core' ),
				'widgets' => [ 'oc_about_blocks' ],
			],
			'contact' => [
				'title'   => __( 'Contact', 'owambe-connect-core' ),
				'widgets' => [ 'oc_contact_form', 'oc_faq' ],
			],
		];
	}

	private function build_elementor_data_for_page( array $widget_types ) {
		$sections = [];
		foreach ( $widget_types as $widget_type ) {
			$sections[] = $this->build_widget_section( $widget_type );
		}
		return $sections;
	}

	private function build_widget_section( $widget_type ) {
		$rand_seed = $widget_type . wp_generate_password( 8, false, false );
		return [
			'id'       => substr( md5( 'sect_' . $rand_seed ), 0, 7 ),
			'elType'   => 'section',
			'settings' => [
				'layout'        => 'full_width',
				'gap'           => 'no',
				'content_width' => [ 'unit' => 'px', 'size' => 1200 ],
				'padding'       => [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
			],
			'elements' => [
				[
					'id'       => substr( md5( 'col_' . $rand_seed ), 0, 7 ),
					'elType'   => 'column',
					'settings' => [ '_column_size' => 100, '_inline_size' => null ],
					'elements' => [
						[
							'id'         => substr( md5( 'wid_' . $rand_seed ), 0, 7 ),
							'elType'     => 'widget',
							'widgetType' => $widget_type,
							'settings'   => new stdClass(),
						],
					],
					'isInner'  => false,
				],
			],
			'isInner'  => false,
		];
	}

	private function set_page_elementor( $page_id, $elementor_data ) {
		update_post_meta( $page_id, '_elementor_data',          wp_slash( wp_json_encode( $elementor_data ) ) );
		update_post_meta( $page_id, '_elementor_edit_mode',     'builder' );
		update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $page_id, '_elementor_version',       defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
		update_post_meta( $page_id, '_wp_page_template',        'elementor/tpl-full-width.php' );
	}

	private function ensure_primary_menu() {
		$menu_name = 'Primary Menu';
		$menu      = wp_get_nav_menu_object( $menu_name );

		if ( ! $menu ) {
			$menu_id = wp_create_nav_menu( $menu_name );
			if ( is_wp_error( $menu_id ) ) {
				return;
			}
		} else {
			$menu_id = (int) $menu->term_id;
			$items   = wp_get_nav_menu_items( $menu_id );
			if ( $items && count( $items ) > 0 ) {
				$this->set_theme_location( $menu_id );
				return;
			}
		}

		$nav_items = [
			[ 'slug' => 'vendors',         'label' => __( 'Find Vendors',    'owambe-connect-core' ) ],
			[ 'slug' => 'become-a-vendor', 'label' => __( 'Become a Vendor', 'owambe-connect-core' ) ],
			[ 'slug' => 'about',           'label' => __( 'About',           'owambe-connect-core' ) ],
			[ 'slug' => 'contact',         'label' => __( 'Contact',         'owambe-connect-core' ) ],
		];

		foreach ( $nav_items as $nav_item ) {
			$page = get_page_by_path( $nav_item['slug'] );
			if ( ! $page ) {
				continue;
			}
			wp_update_nav_menu_item( $menu_id, 0, [
				'menu-item-title'     => $nav_item['label'],
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page->ID,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			] );
		}

		$this->set_theme_location( $menu_id );
	}

	private function set_theme_location( $menu_id ) {
		$locations            = get_theme_mod( 'nav_menu_locations', [] );
		$locations['primary'] = (int) $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	private function ensure_vendor_categories() {
		$tax = $this->resolve_vendor_taxonomy();
		if ( ! $tax ) {
			return 0;
		}

		$categories = [
			[ 'name' => __( 'Catering',         'owambe-connect-core' ), 'slug' => 'catering' ],
			[ 'name' => __( 'Photography',      'owambe-connect-core' ), 'slug' => 'photography' ],
			[ 'name' => __( 'Videography',      'owambe-connect-core' ), 'slug' => 'videography' ],
			[ 'name' => __( 'Decor & Styling',  'owambe-connect-core' ), 'slug' => 'decor' ],
			[ 'name' => __( 'DJ & Live Music',  'owambe-connect-core' ), 'slug' => 'dj-music' ],
			[ 'name' => __( 'Venues',           'owambe-connect-core' ), 'slug' => 'venues' ],
			[ 'name' => __( 'Makeup & Hair',    'owambe-connect-core' ), 'slug' => 'mua' ],
			[ 'name' => __( 'Cakes & Desserts', 'owambe-connect-core' ), 'slug' => 'cakes' ],
			[ 'name' => __( 'Event Planners',   'owambe-connect-core' ), 'slug' => 'planners' ],
			[ 'name' => __( 'Attire & Aso Ebi', 'owambe-connect-core' ), 'slug' => 'attire' ],
		];

		$count = 0;
		foreach ( $categories as $cat ) {
			if ( term_exists( $cat['slug'], $tax ) ) {
				continue;
			}
			$result = wp_insert_term( $cat['name'], $tax, [ 'slug' => $cat['slug'] ] );
			if ( ! is_wp_error( $result ) ) {
				$count++;
			}
		}
		return $count;
	}

	private function resolve_vendor_taxonomy() {
		foreach ( [ 'oc_vendor_cat', 'oc_vendor_category', 'oc_category' ] as $candidate ) {
			if ( taxonomy_exists( $candidate ) ) {
				return $candidate;
			}
		}
		if ( defined( 'OC_TAX' ) && taxonomy_exists( OC_TAX ) ) {
			return OC_TAX;
		}
		return null;
	}

	/**
	 * Seed 10 sample vendor posts owned by the current admin.
	 * Mix of statuses so admin / dashboard / directory flows are all demo-able.
	 */
	private function ensure_sample_vendors() {
		$cpt        = defined( 'OC_CPT' ) ? OC_CPT : 'oc_vendor';
		$tax        = $this->resolve_vendor_taxonomy();
		$author_id  = (int) get_current_user_id();
		$status_pub = defined( 'OC_STATUS_APPROVED' ) ? OC_STATUS_APPROVED : 'publish';
		$status_pen = defined( 'OC_STATUS_PENDING' )  ? OC_STATUS_PENDING  : 'oc_pending';
		$status_rej = defined( 'OC_STATUS_REJECTED' ) ? OC_STATUS_REJECTED : 'oc_rejected';

		$samples = [
			[
				'slug'      => 'lolas-aso-ebi',
				'title'     => "Lola's Aso Ebi",
				'status'    => $status_pub,
				'location'  => 'London, UK',
				'category'  => 'attire',
				'bio'       => 'Traditional Yoruba aso-oke gele specialist. 25+ years experience styling brides for weddings, engagements and naming ceremonies across the UK.',
				'services'  => 'Aso-oke gele styling · custom aso ebi · bridal accessories',
				'price'     => '££',
				'whatsapp'  => '447700900001',
				'instagram' => 'lolasasoebi',
				'featured'  => 1,
			],
			[
				'slug'      => 'royal-bites-catering',
				'title'     => 'Royal Bites Catering',
				'status'    => $status_pub,
				'location'  => 'Manchester, UK',
				'category'  => 'catering',
				'bio'       => 'Nigerian, Ghanaian and Caribbean event catering. From jollof rice and suya to full sit-down buffets for 200+ guests.',
				'services'  => 'Buffet catering · canapé service · live cooking stations',
				'price'     => '£££',
				'whatsapp'  => '447700900002',
				'instagram' => 'royalbitescatering',
				'featured'  => 1,
			],
			[
				'slug'      => 'frame-and-flair-photography',
				'title'     => 'Frame & Flair Photography',
				'status'    => $status_pub,
				'location'  => 'Birmingham, UK',
				'category'  => 'photography',
				'bio'       => 'Wedding and event photographers with a documentary-meets-editorial style. Specialists in Nigerian, Pakistani and Indian celebrations.',
				'services'  => 'Wedding · engagement · pre-wedding · event coverage',
				'price'     => '£££',
				'whatsapp'  => '447700900003',
				'instagram' => 'frameandflair',
				'featured'  => 1,
			],
			[
				'slug'      => 'lagos-vibes-dj',
				'title'     => 'Lagos Vibes DJ',
				'status'    => $status_pub,
				'location'  => 'London, UK',
				'category'  => 'dj-music',
				'bio'       => 'Afrobeats, Amapiano, dancehall and old-school highlife. Reading the room is the job — we play what makes people dance, not just what we like.',
				'services'  => 'DJ set · MC · sound system rental · uplighting',
				'price'     => '££',
				'whatsapp'  => '447700900004',
				'instagram' => 'lagosvibesdj',
				'featured'  => 0,
			],
			[
				'slug'      => 'bloom-and-petals-decor',
				'title'     => 'Bloom & Petals Decor',
				'status'    => $status_pub,
				'location'  => 'Leeds, UK',
				'category'  => 'decor',
				'bio'       => 'Bold florals and structural decor for weddings and milestone events. Famous for our archways and centrepiece installations.',
				'services'  => 'Wedding decor · venue styling · floral installations · linen hire',
				'price'     => '£££',
				'whatsapp'  => '447700900005',
				'instagram' => 'bloomandpetals',
				'featured'  => 1,
			],
			[
				'slug'      => 'mua-by-aisha',
				'title'     => 'MUA by Aisha',
				'status'    => $status_pub,
				'location'  => 'London, UK',
				'category'  => 'mua',
				'bio'       => 'South Asian and African bridal makeup. Calm, kind, and brilliant with mature skin and complex skin tones.',
				'services'  => 'Bridal makeup · hair styling · trials · party makeup',
				'price'     => '££',
				'whatsapp'  => '447700900006',
				'instagram' => 'muabyaisha',
				'featured'  => 0,
			],
			[
				'slug'      => 'sweet-crumb-cakes',
				'title'     => 'Sweet Crumb Cakes',
				'status'    => $status_pub,
				'location'  => 'Glasgow, UK',
				'category'  => 'cakes',
				'bio'       => 'Multi-tier celebration cakes with sugar craft detail. Cultural design specialists — we research your tradition properly.',
				'services'  => 'Tiered wedding cakes · cupcake towers · dessert tables',
				'price'     => '££',
				'whatsapp'  => '447700900007',
				'instagram' => 'sweetcrumbcakes',
				'featured'  => 0,
			],
			[
				'slug'      => 'cinematic-stories',
				'title'     => 'Cinematic Stories',
				'status'    => $status_pub,
				'location'  => 'London, UK',
				'category'  => 'videography',
				'bio'       => 'Cinematic wedding films with the pacing and grade of a short feature. Drone-licensed; two-shooter coverage standard.',
				'services'  => 'Wedding films · highlight reels · drone · same-day edits',
				'price'     => '£££',
				'whatsapp'  => '447700900008',
				'instagram' => 'cinematic.stories',
				'featured'  => 0,
			],
			[
				'slug'      => 'eve-plans-events',
				'title'     => 'Eve Plans Events',
				'status'    => $status_pen,
				'location'  => 'London, UK',
				'category'  => 'planners',
				'bio'       => 'Full-service planner for cross-cultural weddings. We sweat the small stuff so you don\'t — vendor coordination, logistics, day-of management.',
				'services'  => 'Full planning · partial planning · day-of coordination',
				'price'     => '£££',
				'whatsapp'  => '447700900009',
				'instagram' => 'eveplansevents',
				'featured'  => 0,
			],
			[
				'slug'      => 'heritage-hall-venue',
				'title'     => 'Heritage Hall Venue',
				'status'    => $status_rej,
				'location'  => 'Birmingham, UK',
				'category'  => 'venues',
				'bio'       => 'Grade-II listed event hall for ceremonies and receptions. Capacity 220 seated, 350 standing.',
				'services'  => 'Venue hire · in-house catering · ceremony space',
				'price'     => '££££',
				'whatsapp'  => '447700900010',
				'instagram' => 'heritagehall',
				'featured'  => 0,
				'rejection' => 'Pricing details and capacity proof required before we can publish — please reply with rate card and floor plan.',
			],
		];

		$processed = 0;
		foreach ( $samples as $v ) {
			$existing = get_page_by_path( $v['slug'], OBJECT, $cpt );

			$post_data = [
				'post_type'    => $cpt,
				'post_title'   => $v['title'],
				'post_name'    => $v['slug'],
				'post_status'  => $v['status'],
				'post_author'  => $author_id,
				'post_content' => $v['bio'],
			];

			if ( $existing ) {
				// Update existing vendor in place — no duplicate.
				$post_data['ID'] = $existing->ID;
				$post_id         = wp_update_post( $post_data, true );
			} else {
				$post_id = wp_insert_post( $post_data, true );
			}

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, '_oc_business_name', $v['title'] );
			update_post_meta( $post_id, '_oc_location',      $v['location'] );
			update_post_meta( $post_id, '_oc_bio',           $v['bio'] );
			update_post_meta( $post_id, '_oc_services',      $v['services'] );
			update_post_meta( $post_id, '_oc_price_range',   $v['price'] );
			update_post_meta( $post_id, '_oc_whatsapp',      $v['whatsapp'] );
			update_post_meta( $post_id, '_oc_instagram',     $v['instagram'] );
			update_post_meta( $post_id, '_oc_featured',      ! empty( $v['featured'] ) ? 1 : 0 );

			if ( ! empty( $v['rejection'] ) ) {
				update_post_meta( $post_id, '_oc_rejection_note', $v['rejection'] );
			} else {
				delete_post_meta( $post_id, '_oc_rejection_note' );
			}

			if ( $tax && ! empty( $v['category'] ) ) {
				wp_set_object_terms( $post_id, [ $v['category'] ], $tax, false );
			}

			$processed++;
		}

		return $processed;
	}
}
