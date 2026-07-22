<?php
/**
 * Custom "Add Vendor" admin form — replaces the default post editor with a
 * streamlined, single-page form aimed at admins adding vendors manually.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Add_Vendor {

	const PAGE   = 'oc-add-vendor';
	const ACTION = 'oc_admin_add_vendor';

	public function register() {
		add_action( 'admin_menu',                          [ $this, 'menu' ], 9 );
		add_action( 'admin_post_' . self::ACTION,          [ $this, 'handle' ] );
		add_action( 'admin_init',                          [ $this, 'redirect_default_add_new' ] );
		add_action( 'admin_init',                          [ $this, 'redirect_default_edit' ] );
		add_filter( 'submenu_file',                        [ $this, 'highlight_menu' ] );
		add_filter( 'post_row_actions',                    [ $this, 'filter_row_actions' ], 10, 2 );
		add_action( 'admin_print_footer_scripts-edit.php', [ $this, 'retarget_list_add_button' ] );
	}

	public function menu() {
		// Registered as a hidden page (null parent) — accessible by URL but not
		// duplicated in the sidebar; admins reach it via the "Add New Vendor"
		// button on the vendor list page.
		add_submenu_page(
			null,
			__( 'Add Vendor', 'owambe-connect-core' ),
			__( 'Add Vendor', 'owambe-connect-core' ),
			'edit_posts',
			self::PAGE,
			[ $this, 'render' ]
		);
	}

	/**
	 * Send admins clicking the auto-generated "Add New Vendor" link
	 * to our streamlined form instead of the post editor.
	 */
	public function redirect_default_add_new() {
		global $pagenow;
		if ( 'post-new.php' === $pagenow && isset( $_GET['post_type'] ) && OC_CPT === $_GET['post_type'] ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
			exit;
		}
	}

	/**
	 * Send any post.php?action=edit hit on a vendor to our custom edit form.
	 * Catches direct URLs and any link we missed in row actions.
	 */
	public function redirect_default_edit() {
		global $pagenow;
		if ( 'post.php' !== $pagenow ) return;
		if ( empty( $_GET['action'] ) || 'edit' !== $_GET['action'] ) return;
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( ! $post_id || OC_CPT !== get_post_type( $post_id ) ) return;
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&edit=' . $post_id ) );
		exit;
	}

	/**
	 * On the vendor list page (edit.php?post_type=oc_vendor) the native
	 * "Add New Vendor" button next to the page title points to post-new.php,
	 * which then redirects to our custom form. Retarget it so the click
	 * goes directly to the custom form — saves a hop, cleaner URL.
	 */
	public function retarget_list_add_button() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . OC_CPT !== $screen->id ) return;
		$target = esc_url( admin_url( 'admin.php?page=' . self::PAGE ) );
		?>
		<script>
		(function () {
			var url = <?php echo wp_json_encode( $target ); ?>;
			document.querySelectorAll('.wrap .page-title-action[href*="post-new.php"][href*="<?php echo esc_js( OC_CPT ); ?>"]').forEach(function (a) {
				a.setAttribute('href', url);
			});
		})();
		</script>
		<?php
	}

	/**
	 * Rewrite the "Edit" link in the vendor list row actions to point
	 * at our custom form instead of post.php?action=edit.
	 */
	public function filter_row_actions( $actions, $post ) {
		if ( ! $post || OC_CPT !== $post->post_type ) return $actions;
		if ( isset( $actions['edit'] ) ) {
			$url = admin_url( 'admin.php?page=' . self::PAGE . '&edit=' . $post->ID );
			$actions['edit'] = '<a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( sprintf( __( 'Edit "%s"', 'owambe-connect-core' ), get_the_title( $post ) ) ) . '">' . esc_html__( 'Edit', 'owambe-connect-core' ) . '</a>';
		}
		// "Quick Edit" and "Inline Edit" stay — they bypass the post editor anyway.
		return $actions;
	}

	public function highlight_menu( $submenu_file ) {
		$screen = get_current_screen();
		if ( $screen && self::PAGE === $screen->parent_base ) {
			return self::PAGE;
		}
		return $submenu_file;
	}

	public function render() {
		if ( ! current_user_can( 'edit_posts' ) ) return;
		$categories = OC_Queries::categories_with_counts();
		$prices     = oc_price_range_options();
		$languages  = oc_language_options();
		$err        = isset( $_GET['oc_admin_error'] )  ? wp_unslash( $_GET['oc_admin_error'] )  : '';
		$created    = isset( $_GET['oc_created'] ) ? (int) $_GET['oc_created'] : 0;
		$updated    = isset( $_GET['oc_updated'] ) ? (int) $_GET['oc_updated'] : 0;

		// Edit mode: load existing vendor.
		$edit_id      = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
		$is_edit      = false;
		$edit_post    = null;
		$post_cat_ids = [];
		$values = [
			'business_name'        => '',
			'location'             => '',
			'location_country'     => '',
			'location_areas'       => [],
			'location_regions'     => [],
			'cultural_specialties' => [],
			'nigerian_specialty'   => '',
			'registered_business'  => '',
			'vendor_tags'          => [],
			'bio'                  => '',
			'services'              => '',
			'price_range'          => '',
			'whatsapp'             => '',
			'whatsapp_local'       => '',
			'public_email'         => '',
			'instagram'            => '',
			'facebook'             => '',
			'website'              => '',
			'languages'            => [],
			'featured'             => 0,
			'verified'             => 0,
			'founding'             => 0,
			'email_verified'       => 1,
			'vendor_number'        => '',
			'status'               => OC_STATUS_APPROVED,
			'rejection_note'       => '',
		];

		if ( $edit_id ) {
			$edit_post = get_post( $edit_id );
			if ( $edit_post && OC_CPT === $edit_post->post_type ) {
				$is_edit = true;
				$values['rejection_note']       = (string) get_post_meta( $edit_id, '_oc_rejection_note', true );
				$values['business_name']        = get_post_meta( $edit_id, '_oc_business_name', true ) ?: $edit_post->post_title;
				$values['location']             = (string) get_post_meta( $edit_id, '_oc_location',             true );
				$values['location_country']     = (string) get_post_meta( $edit_id, '_oc_location_country',     true );
				$values['location_areas']       = (array)  get_post_meta( $edit_id, '_oc_location_areas',       true );
				$values['location_regions']     = (array)  get_post_meta( $edit_id, '_oc_location_regions',     true );
				$values['cultural_specialties'] = (array)  get_post_meta( $edit_id, '_oc_cultural_specialties', true );
				$values['nigerian_specialty']   = (string) get_post_meta( $edit_id, '_oc_nigerian_specialty',   true );
				$values['registered_business']  = (string) get_post_meta( $edit_id, '_oc_registered_business',  true );
				$values['vendor_tags']          = (array)  get_post_meta( $edit_id, '_oc_vendor_tags',          true );
				$values['bio']                  = (string) get_post_meta( $edit_id, '_oc_bio',                  true );
				$values['services']             = (string) get_post_meta( $edit_id, '_oc_services',             true );
				$values['price_range']          = (string) get_post_meta( $edit_id, '_oc_price_range',          true );
				$values['whatsapp']             = (string) get_post_meta( $edit_id, '_oc_whatsapp',             true );
				$values['whatsapp_local']       = function_exists( 'oc_uk_whatsapp_local' )
					? oc_uk_whatsapp_local( $values['whatsapp'] )
					: $values['whatsapp'];
				$values['public_email']         = (string) get_post_meta( $edit_id, '_oc_public_email',         true );
				$values['instagram']            = (string) get_post_meta( $edit_id, '_oc_instagram',            true );
				$values['facebook']             = (string) get_post_meta( $edit_id, '_oc_facebook',             true );
				$values['website']              = (string) get_post_meta( $edit_id, '_oc_website',              true );
				$langs                          = get_post_meta( $edit_id, '_oc_languages', true );
				$values['languages']            = is_array( $langs ) ? $langs : ( $langs ? array_map( 'trim', explode( ',', (string) $langs ) ) : [] );
				$values['featured']             = (int) get_post_meta( $edit_id, '_oc_featured',         true ) === 1 ? 1 : 0;
				$values['verified']             = (int) get_post_meta( $edit_id, '_oc_verified',         true ) === 1 ? 1 : 0;
				$values['founding']             = (int) get_post_meta( $edit_id, '_oc_founding_vendor',  true ) === 1 ? 1 : 0;
				$ev                             = get_post_meta( $edit_id, '_oc_email_verified', true );
				$values['email_verified']       = ( '' === $ev || null === $ev ) ? 1 : ( (int) $ev === 1 ? 1 : 0 );
				$values['vendor_number']        = (string) get_post_meta( $edit_id, '_oc_vendor_number', true );
				$values['status']               = $edit_post->post_status;
				$post_cat_ids                   = wp_get_object_terms( $edit_id, OC_TAX, [ 'fields' => 'ids' ] );
				if ( is_wp_error( $post_cat_ids ) ) $post_cat_ids = [];
			}
		}

		// Pull option lists once for the form below.
		$country_options  = function_exists( 'oc_country_options' )            ? oc_country_options()            : [];
		$cities_by_country = function_exists( 'oc_cities_by_country' ) ? oc_cities_by_country() : [];
		$region_options   = function_exists( 'oc_region_options' )            ? oc_region_options()            : [];
		$cultural_options = function_exists( 'oc_cultural_specialty_options' ) ? oc_cultural_specialty_options() : [];
		$tag_groups       = function_exists( 'oc_vendor_tag_options' )         ? oc_vendor_tag_options()         : [];
		// Normalise stored values for safe array-comparison later.
		$values['location_areas']       = array_values( array_filter( array_map( 'trim', (array) $values['location_areas'] ) ) );
		$values['location_regions']     = array_values( array_filter( array_map( 'trim', (array) $values['location_regions'] ) ) );
		$values['cultural_specialties'] = array_values( array_filter( array_map( 'trim', (array) $values['cultural_specialties'] ) ) );
		$values['vendor_tags']          = array_values( array_filter( array_map( 'trim', (array) $values['vendor_tags'] ) ) );

		$header_title = $is_edit ? __( 'Edit Vendor', 'owambe-connect-core' )      : __( 'Add Vendor', 'owambe-connect-core' );
		$header_desc  = $is_edit ? __( 'Update this vendor\'s details. Click "Save changes" when you\'re done.', 'owambe-connect-core' )
		                          : __( 'Quickly add a vendor to the marketplace. Only Business name and at least one Category are required.', 'owambe-connect-core' );
		$submit_label = $is_edit ? __( 'Save changes', 'owambe-connect-core' )    : __( 'Create Vendor', 'owambe-connect-core' );
		?>
		<div class="wrap oc-add-vendor">
			<h1 style="margin-bottom:6px;display:flex;align-items:center;gap:14px">
				<?php echo esc_html( $header_title ); ?>
				<?php if ( $is_edit ) : ?>
					<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>"><?php esc_html_e( 'Add new', 'owambe-connect-core' ); ?></a>
					<a class="page-title-action" href="<?php echo esc_url( get_permalink( $edit_post ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View profile', 'owambe-connect-core' ); ?></a>
				<?php endif; ?>
			</h1>
			<p style="margin:0 0 18px;color:#555"><?php echo esc_html( $header_desc ); ?></p>

			<?php if ( $err ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $err ); ?></p></div>
			<?php endif; ?>
			<?php if ( $updated && $is_edit ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Vendor updated.', 'owambe-connect-core' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $created ) :
				$post = get_post( $created );
				if ( $post ) : ?>
					<div class="notice notice-success">
						<p>
							<strong><?php echo esc_html( $post->post_title ); ?></strong>
							<?php esc_html_e( 'has been created.', 'owambe-connect-core' ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&edit=' . $post->ID ) ); ?>"><?php esc_html_e( 'Edit full details', 'owambe-connect-core' ); ?></a> ·
							<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View profile', 'owambe-connect-core' ); ?></a> ·
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>"><?php esc_html_e( 'Add another', 'owambe-connect-core' ); ?></a>
						</p>
					</div>
				<?php endif;
			endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="oc-av-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="vendor_id" value="<?php echo (int) $edit_id; ?>" />
				<?php endif; ?>
				<?php wp_nonce_field( self::ACTION, 'oc_av_nonce' ); ?>

				<div class="oc-av-grid">

					<!-- Main column -->
					<div class="oc-av-main">

						<div class="oc-av-card">
							<h2><?php esc_html_e( 'Business', 'owambe-connect-core' ); ?></h2>
							<p class="oc-av-row">
								<label for="av-name"><?php esc_html_e( 'Business name', 'owambe-connect-core' ); ?> <span style="color:#b32d2e">*</span></label>
								<input id="av-name" type="text" name="business_name" required maxlength="120" value="<?php echo esc_attr( $values['business_name'] ); ?>"/>
							</p>
							<div class="oc-av-row-2">
								<p>
									<label for="av-country"><?php esc_html_e( 'Country / region', 'owambe-connect-core' ); ?></label>
									<?php
									// England is the default for new vendors so the
									// city filter below already shows English cities.
									$effective_country = $values['location_country'] ?: 'england';
									?>
									<select id="av-country" name="location_country" data-oc-country-select>
										<?php foreach ( $country_options as $key => $label ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $effective_country, $key ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<small style="display:block;color:#6B6361;margin-top:4px;"><?php esc_html_e( 'England is selected by default. Picking another country filters the city list below.', 'owambe-connect-core' ); ?></small>
								</p>
								<p>
									<label for="av-loc-legacy"><?php esc_html_e( 'Location summary (free text, legacy)', 'owambe-connect-core' ); ?></label>
									<input id="av-loc-legacy" type="text" name="location" placeholder="<?php esc_attr_e( 'Auto-generated from areas below — override if you like.', 'owambe-connect-core' ); ?>" value="<?php echo esc_attr( $values['location'] ); ?>"/>
								</p>
							</div>
							<?php if ( $region_options ) : ?>
							<div class="oc-av-row" data-oc-regions-field>
								<label><?php esc_html_e( 'Regions covered (England)', 'owambe-connect-core' ); ?></label>
								<span style="display:block;color:#6B6361;font-size:12.5px;margin:2px 0 6px;"><?php esc_html_e( "For vendors whose town isn't in the city list — a region covers every town within it.", 'owambe-connect-core' ); ?></span>
								<div class="oc-av-checks">
									<?php foreach ( $region_options as $region ) : ?>
										<label class="oc-av-chk">
											<input type="checkbox" name="location_regions[]" value="<?php echo esc_attr( $region ); ?>" <?php checked( in_array( $region, (array) $values['location_regions'], true ) ); ?>/>
											<span><?php echo esc_html( $region ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
							<?php endif; ?>
							<p class="oc-av-row">
								<label><?php esc_html_e( 'Cities / areas covered', 'owambe-connect-core' ); ?></label>
								<span class="oc-av-chip-actions">
									<button type="button" class="button button-small" data-oc-areas-action="select"><?php esc_html_e( 'Select all cities', 'owambe-connect-core' ); ?></button>
									<button type="button" class="button button-small" data-oc-areas-action="clear"><?php esc_html_e( 'Clear', 'owambe-connect-core' ); ?></button>
									<label style="font-size:12px;color:#6B6361;margin-left:6px;"><input type="checkbox" data-oc-areas-showall/> <?php esc_html_e( 'Show all cities (ignore region filter)', 'owambe-connect-core' ); ?></label>
								</span>
								<div class="oc-av-checks" data-oc-areas-wrap>
									<?php foreach ( $cities_by_country as $country_slug => $country_cities ) : ?>
										<?php foreach ( $country_cities as $city ) :
											$chip_region = ( 'england' === $country_slug && function_exists( 'oc_region_for_city' ) ) ? oc_region_for_city( $city ) : ''; ?>
											<label class="oc-av-chk" data-oc-area-chip data-country="<?php echo esc_attr( $country_slug ); ?>" data-region="<?php echo esc_attr( $chip_region ); ?>">
												<input type="checkbox" name="location_areas[]" value="<?php echo esc_attr( $city ); ?>" <?php checked( in_array( $city, $values['location_areas'], true ) ); ?>/>
												<span><?php echo esc_html( $city ); ?></span>
											</label>
										<?php endforeach; ?>
									<?php endforeach; ?>
								</div>
								<p data-oc-areas-hint hidden style="color:#6B6361;font-size:12.5px;margin:6px 0 0;"><?php esc_html_e( 'Pick a region above to see its cities — or tick "Show all cities" for the full list.', 'owambe-connect-core' ); ?></p>
							</p>
							<script>
							(function () {
								var country = document.querySelector('[data-oc-country-select]');
								var wrap    = document.querySelector('[data-oc-areas-wrap]');
								if (!country || !wrap) return;
								var chips = wrap.querySelectorAll('[data-oc-area-chip]');
								var hint  = document.querySelector('[data-oc-areas-hint]');
								// Regions are England-only — show only when England is picked.
								var regionsField = document.querySelector('[data-oc-regions-field]');
								var showAll      = document.querySelector('[data-oc-areas-showall]');

								function toggleRegions(selected) {
									if (!regionsField) return;
									var show = selected === 'england';
									regionsField.style.display = show ? '' : 'none';
									if (!show) {
										regionsField.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = false; });
									}
								}
								function selectedRegions() {
									var set = {};
									if (regionsField) {
										regionsField.querySelectorAll('input[type="checkbox"]:checked').forEach(function (cb) { set[ cb.value ] = true; });
									}
									return set;
								}

								// Country + region (England) filters in one pass. Region narrows the
								// city list but never unchecks a city (checked cities stay visible);
								// only switching country unchecks the old country's cities.
								function applyFilters(opts) {
									opts = opts || {};
									var sel        = country.value;
									var regs       = selectedRegions();
									var regionKeys = Object.keys( regs );
									var showingAll = !!( showAll && showAll.checked );
									var isEngland  = sel === 'england';
									var anyVisible = false;
									chips.forEach(function (chip) {
										var cb      = chip.querySelector('input[type="checkbox"]');
										var checked = !!( cb && cb.checked );
										var ok = !sel || chip.getAttribute('data-country') === sel;
										if (!ok && checked && opts.clearHiddenCountry) { cb.checked = false; checked = false; }
										// England cities gated (regions-first): hidden until a region is
										// picked, "Show all cities" is ticked, or the city is already checked.
										if (ok && isEngland && ! showingAll) {
											ok = regionKeys.length > 0 ? ( !!regs[ chip.getAttribute('data-region') ] || checked ) : checked;
										}
										chip.style.display = ok ? '' : 'none';
										if (ok) anyVisible = true;
									});
									if (hint) hint.hidden = anyVisible;
								}

								country.addEventListener('change', function () { toggleRegions(country.value); applyFilters({ clearHiddenCountry: true }); });
								if (regionsField) regionsField.addEventListener('change', function () { applyFilters(); });
								if (showAll) showAll.addEventListener('change', function () { applyFilters(); });
								toggleRegions(country.value);
								applyFilters();

								// "Select all cities" = nationwide: tick EVERY city for the selected
								// country (not just visible). "Clear" unticks all + resets "Show all".
								document.querySelectorAll('[data-oc-areas-action]').forEach(function (btn) {
									btn.addEventListener('click', function () {
										var select = btn.getAttribute('data-oc-areas-action') === 'select';
										var sel    = country.value;
										chips.forEach(function (chip) {
											var cb = chip.querySelector('input[type="checkbox"]');
											if (!cb) return;
											if (select) {
												if (!sel || chip.getAttribute('data-country') === sel) cb.checked = true;
											} else {
												cb.checked = false;
											}
										});
										if (!select && showAll) showAll.checked = false;
										applyFilters();
									});
								});
							})();
							</script>
							<p class="oc-av-row">
								<label for="av-bio"><?php esc_html_e( 'Short bio / about', 'owambe-connect-core' ); ?></label>
								<textarea id="av-bio" name="bio" rows="3" maxlength="1500"><?php echo esc_textarea( $values['bio'] ); ?></textarea>
							</p>
							<p class="oc-av-row">
								<label for="av-services"><?php esc_html_e( 'Services offered', 'owambe-connect-core' ); ?></label>
								<textarea id="av-services" name="services" rows="3" maxlength="2000"><?php echo esc_textarea( $values['services'] ); ?></textarea>
							</p>
							<div class="oc-av-row-2">
								<p>
									<label for="av-price"><?php esc_html_e( 'Price range', 'owambe-connect-core' ); ?></label>
									<select id="av-price" name="price_range">
										<option value=""><?php esc_html_e( '— Select —', 'owambe-connect-core' ); ?></option>
										<?php foreach ( $prices as $k => $label ) : ?>
											<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $values['price_range'], $k ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</p>
								<p>
									<label><input type="checkbox" name="featured" value="1" <?php checked( $values['featured'], 1 ); ?>/> <?php esc_html_e( 'Mark as featured', 'owambe-connect-core' ); ?></label>
								</p>
							</div>
						</div>

						<div class="oc-av-card">
							<h2><?php esc_html_e( 'Cultural & registration', 'owambe-connect-core' ); ?></h2>
							<p class="oc-av-row">
								<label><?php esc_html_e( 'Cultural events / specialties', 'owambe-connect-core' ); ?></label>
								<div class="oc-av-checks">
									<?php foreach ( $cultural_options as $key => $label ) : ?>
										<label class="oc-av-chk">
											<input type="checkbox" name="cultural_specialties[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $values['cultural_specialties'], true ) ); ?>/>
											<span><?php echo esc_html( $label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</p>
							<div class="oc-av-row-2">
								<p>
									<label><?php esc_html_e( 'Officially registered business?', 'owambe-connect-core' ); ?></label>
									<span class="oc-av-radio-row">
										<label><input type="radio" name="registered_business" value="yes" <?php checked( $values['registered_business'], 'yes' ); ?>/> <?php esc_html_e( 'Yes', 'owambe-connect-core' ); ?></label>
										<label><input type="radio" name="registered_business" value="no"  <?php checked( $values['registered_business'], 'no'  ); ?>/> <?php esc_html_e( 'No', 'owambe-connect-core' ); ?></label>
										<label><input type="radio" name="registered_business" value=""    <?php checked( $values['registered_business'], '' ); ?>/> <?php esc_html_e( 'Unanswered', 'owambe-connect-core' ); ?></label>
									</span>
								</p>
								<p>
									<label><?php esc_html_e( 'Specialises in Nigerian events?', 'owambe-connect-core' ); ?></label>
									<span class="oc-av-radio-row">
										<label><input type="radio" name="nigerian_specialty" value="yes" <?php checked( $values['nigerian_specialty'], 'yes' ); ?>/> <?php esc_html_e( 'Yes', 'owambe-connect-core' ); ?></label>
										<label><input type="radio" name="nigerian_specialty" value="no"  <?php checked( $values['nigerian_specialty'], 'no'  ); ?>/> <?php esc_html_e( 'No', 'owambe-connect-core' ); ?></label>
										<label><input type="radio" name="nigerian_specialty" value=""    <?php checked( $values['nigerian_specialty'], '' ); ?>/> <?php esc_html_e( 'Unanswered', 'owambe-connect-core' ); ?></label>
									</span>
								</p>
							</div>
						</div>

						<div class="oc-av-card">
							<h2><?php esc_html_e( 'Vendor tags', 'owambe-connect-core' ); ?>
								<small style="font-weight:400;font-size:12px;color:#6B6361;text-transform:none;letter-spacing:0;border:0;padding:0;float:right;">
									<?php
									/* translators: %d count */
									printf( esc_html__( '%d selected', 'owambe-connect-core' ), count( $values['vendor_tags'] ) );
									?>
								</small>
							</h2>
							<p style="color:#6B6361;font-size:13px;margin:-4px 0 12px;"><?php esc_html_e( 'Tick everything that describes the vendor. Groups auto-expand when a tag is already selected.', 'owambe-connect-core' ); ?></p>
							<div class="oc-av-tag-groups">
								<?php foreach ( $tag_groups as $group_label => $tag_list ) :
									$group_selected = array_intersect( $tag_list, $values['vendor_tags'] );
									$is_open = ! empty( $group_selected );
								?>
									<details class="oc-av-tag-group" <?php if ( $is_open ) echo 'open'; ?>>
										<summary>
											<span class="oc-av-tag-group__title"><?php echo esc_html( $group_label ); ?></span>
											<span class="oc-av-tag-group__count"><?php echo (int) count( $group_selected ); ?> / <?php echo (int) count( $tag_list ); ?></span>
										</summary>
										<div class="oc-av-checks">
											<?php foreach ( $tag_list as $tag ) : ?>
												<label class="oc-av-chk">
													<input type="checkbox" name="vendor_tags[]" value="<?php echo esc_attr( $tag ); ?>" <?php checked( in_array( $tag, $values['vendor_tags'], true ) ); ?>/>
													<span><?php echo esc_html( $tag ); ?></span>
												</label>
											<?php endforeach; ?>
										</div>
									</details>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="oc-av-card">
							<h2><?php esc_html_e( 'Contact', 'owambe-connect-core' ); ?></h2>

							<p class="oc-av-row">
								<label for="av-wa-local"><?php esc_html_e( 'WhatsApp number', 'owambe-connect-core' ); ?></label>
								<span class="oc-av-prefix">
									<span class="oc-av-prefix__tag" aria-hidden="true">+44</span>
									<input id="av-wa-local" type="tel" name="whatsapp_local"
										value="<?php echo esc_attr( $values['whatsapp_local'] ); ?>"
										inputmode="numeric"
										pattern="[0-9]{10}"
										maxlength="11"
										placeholder="7424688636"/>
								</span>
								<small style="color:#6B6361;font-size:12px;"><?php esc_html_e( '10 digits after +44, no leading zero. Always saved in canonical +44XXXXXXXXXX format.', 'owambe-connect-core' ); ?></small>
							</p>

							<div class="oc-av-row-2">
								<p>
									<label for="av-pubmail"><?php esc_html_e( 'Public contact email', 'owambe-connect-core' ); ?></label>
									<input id="av-pubmail" type="email" name="public_email" value="<?php echo esc_attr( $values['public_email'] ); ?>" placeholder="hello@business.co.uk"/>
								</p>
								<p>
									<label for="av-ig"><?php esc_html_e( 'Instagram handle', 'owambe-connect-core' ); ?></label>
									<input id="av-ig" type="text" name="instagram" placeholder="@business" value="<?php echo esc_attr( $values['instagram'] ); ?>"/>
								</p>
								<p>
									<label for="av-fb"><?php esc_html_e( 'Facebook page', 'owambe-connect-core' ); ?></label>
									<input id="av-fb" type="text" name="facebook" value="<?php echo esc_attr( $values['facebook'] ); ?>"/>
								</p>
								<p>
									<label for="av-web"><?php esc_html_e( 'Website', 'owambe-connect-core' ); ?></label>
									<input id="av-web" type="url" name="website" placeholder="https://" value="<?php echo esc_attr( $values['website'] ); ?>"/>
								</p>
							</div>
						</div>

						<div class="oc-av-card">
							<h2><?php esc_html_e( 'Languages spoken', 'owambe-connect-core' ); ?></h2>
							<div class="oc-av-checks">
								<?php foreach ( $languages as $lang ) : ?>
									<label class="oc-av-chk">
										<input type="checkbox" name="languages[]" value="<?php echo esc_attr( $lang ); ?>" <?php checked( in_array( $lang, (array) $values['languages'], true ) ); ?>/>
										<span><?php echo esc_html( $lang ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="oc-av-card">
							<h2><?php esc_html_e( 'Images', 'owambe-connect-core' ); ?></h2>
							<?php if ( $is_edit ) :
								$logo_id   = (int) get_post_meta( $edit_id, '_oc_logo_id',   true );
								$banner_id = (int) get_post_meta( $edit_id, '_oc_banner_id', true );
								?>
								<div class="oc-av-row-2" style="margin-bottom:10px">
									<div>
										<strong style="font-size:12px;color:#6B6361;text-transform:uppercase;letter-spacing:.06em"><?php esc_html_e( 'Current logo', 'owambe-connect-core' ); ?></strong><br>
										<?php echo $logo_id ? wp_get_attachment_image( $logo_id, [ 80, 80 ], false, [ 'style' => 'border-radius:6px;border:1px solid #e4ddd2;margin-top:6px' ] ) : '<span style="color:#888">—</span>'; ?>
									</div>
									<div>
										<strong style="font-size:12px;color:#6B6361;text-transform:uppercase;letter-spacing:.06em"><?php esc_html_e( 'Current banner', 'owambe-connect-core' ); ?></strong><br>
										<?php echo $banner_id ? wp_get_attachment_image( $banner_id, [ 200, 80 ], false, [ 'style' => 'border-radius:6px;border:1px solid #e4ddd2;margin-top:6px;object-fit:cover' ] ) : '<span style="color:#888">—</span>'; ?>
									</div>
								</div>
								<p style="color:#666;font-size:13px;margin:0 0 10px"><?php esc_html_e( 'Upload a new file to replace, or leave empty to keep the current image.', 'owambe-connect-core' ); ?></p>
							<?php endif; ?>
							<div class="oc-av-row-2">
								<p>
									<label for="av-logo"><?php esc_html_e( 'Logo (≤2 MB, square)', 'owambe-connect-core' ); ?></label>
									<input id="av-logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp"/>
								</p>
								<p>
									<label for="av-banner"><?php esc_html_e( 'Banner (≤5 MB, wide)', 'owambe-connect-core' ); ?></label>
									<input id="av-banner" type="file" name="banner" accept="image/jpeg,image/png,image/webp"/>
								</p>
							</div>

							<?php
							$av_gallery_max = (int) oc_get_setting( 'gallery_max_images', 6 );
							$av_gallery_mb  = (int) oc_get_setting( 'gallery_max_mb', 3 );
							$av_gallery_ids = $is_edit ? (array) get_post_meta( $edit_id, '_oc_gallery_ids', true ) : [];
							$av_gallery_ids = array_values( array_filter( array_map( 'intval', $av_gallery_ids ) ) );
							if ( $av_gallery_max > 0 ) :
								$av_slots_left = max( 0, $av_gallery_max - count( $av_gallery_ids ) ); ?>
								<hr style="border:0;border-top:1px dashed #E4DDD2;margin:14px 0">
								<p style="margin:0 0 8px"><strong><?php
									printf( esc_html__( 'Portfolio gallery (%1$d / %2$d)', 'owambe-connect-core' ), count( $av_gallery_ids ), $av_gallery_max );
								?></strong></p>

								<?php if ( $av_gallery_ids ) : ?>
									<div class="oc-av-gallery__grid">
										<?php foreach ( $av_gallery_ids as $gid ) : ?>
											<label class="oc-av-gallery__item">
												<?php echo wp_get_attachment_image( $gid, 'thumbnail', false, [ 'class' => 'oc-av-gallery__img' ] ); ?>
												<span class="oc-av-gallery__rm">
													<input type="checkbox" name="gallery_remove[]" value="<?php echo (int) $gid; ?>"/>
													<?php esc_html_e( 'Remove', 'owambe-connect-core' ); ?>
												</span>
											</label>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>

								<?php if ( $av_slots_left > 0 ) : ?>
									<p>
										<input type="file" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple/>
									</p>
									<p style="color:#666;font-size:12px;margin:4px 0 0"><?php
										/* translators: 1: slots left, 2: per-image MB */
										printf( esc_html__( 'Up to %1$d more, each ≤ %2$d MB.', 'owambe-connect-core' ), $av_slots_left, $av_gallery_mb );
									?></p>
								<?php else : ?>
									<p style="color:#666;font-size:12px;margin:0"><?php esc_html_e( 'Gallery is full — tick "Remove" on existing items first.', 'owambe-connect-core' ); ?></p>
								<?php endif; ?>
							<?php endif; ?>
						</div>

					</div>

					<!-- Side column -->
					<div class="oc-av-side">

						<div class="oc-av-card">
							<h2><?php esc_html_e( 'Categories', 'owambe-connect-core' ); ?> <span style="color:#b32d2e">*</span></h2>
							<div class="oc-av-checks oc-av-checks--col">
								<?php foreach ( $categories as $term ) : ?>
									<label class="oc-av-chk">
										<input type="checkbox" name="categories[]" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( in_array( (int) $term->term_id, array_map( 'intval', (array) $post_cat_ids ), true ) ); ?>/>
										<span><?php echo esc_html( $term->name ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="oc-av-card">
							<h2><?php esc_html_e( 'Status', 'owambe-connect-core' ); ?></h2>
							<p>
								<label><input type="radio" name="status" value="<?php echo esc_attr( OC_STATUS_APPROVED ); ?>" data-oc-status="approved" <?php checked( $values['status'], OC_STATUS_APPROVED ); ?>/> <?php esc_html_e( 'Approved & published', 'owambe-connect-core' ); ?></label><br>
								<label><input type="radio" name="status" value="<?php echo esc_attr( OC_STATUS_PENDING ); ?>" data-oc-status="pending" <?php checked( $values['status'], OC_STATUS_PENDING ); ?>/> <?php esc_html_e( 'Pending review', 'owambe-connect-core' ); ?></label><br>
								<?php if ( $is_edit ) : ?>
									<label><input type="radio" name="status" value="<?php echo esc_attr( OC_STATUS_REJECTED ); ?>" data-oc-status="rejected" <?php checked( $values['status'], OC_STATUS_REJECTED ); ?>/> <?php esc_html_e( 'Rejected / needs changes', 'owambe-connect-core' ); ?></label>
								<?php endif; ?>
							</p>

							<?php if ( $is_edit ) : ?>
								<div id="oc-av-reject-block" style="<?php echo OC_STATUS_REJECTED === $values['status'] ? '' : 'display:none;'; ?> margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e2e2;">
									<label for="oc-av-rejection" style="display:block;font-weight:600;margin-bottom:6px;">
										<?php esc_html_e( 'Rejection reason', 'owambe-connect-core' ); ?>
										<span style="color:#a02e2a;">*</span>
									</label>
									<textarea id="oc-av-rejection" name="rejection_note" rows="4" class="widefat" minlength="10" placeholder="<?php esc_attr_e( 'Explain what the vendor needs to fix. Minimum 10 characters. This text is emailed to the vendor.', 'owambe-connect-core' ); ?>"><?php echo esc_textarea( $values['rejection_note'] ); ?></textarea>
									<p style="margin: 6px 0 0; font-size: 12px; color: #666;">
										<?php esc_html_e( 'Required when status is Rejected. Sent to the vendor by email along with the status change.', 'owambe-connect-core' ); ?>
									</p>
								</div>
								<script>
								(function () {
									var block  = document.getElementById('oc-av-reject-block');
									var radios = document.querySelectorAll('input[name="status"]');
									if (!block || !radios.length) return;
									function sync() {
										var sel = document.querySelector('input[name="status"]:checked');
										var isRej = sel && sel.value === '<?php echo esc_js( OC_STATUS_REJECTED ); ?>';
										block.style.display = isRej ? '' : 'none';
										var ta = document.getElementById('oc-av-rejection');
										if (ta) ta.required = !!isRej;
									}
									radios.forEach(function (r) { r.addEventListener('change', sync); });
									sync();
								})();
								</script>
							<?php endif; ?>
						</div>

						<div class="oc-av-card">
							<h2><?php esc_html_e( 'Admin flags', 'owambe-connect-core' ); ?></h2>
							<p style="margin:0 0 10px;">
								<label><input type="checkbox" name="verified" value="1" <?php checked( $values['verified'], 1 ); ?>/> <strong><?php esc_html_e( 'Verified vendor', 'owambe-connect-core' ); ?></strong></label>
								<br><small style="color:#6B6361;font-size:12px;"><?php esc_html_e( 'Shows the burgundy ✓ badge on the public profile.', 'owambe-connect-core' ); ?></small>
							</p>
							<p style="margin:0 0 10px;">
								<label><input type="checkbox" name="founding" value="1" <?php checked( $values['founding'], 1 ); ?>/> <strong><?php esc_html_e( 'Founding vendor', 'owambe-connect-core' ); ?></strong></label>
								<br><small style="color:#6B6361;font-size:12px;"><?php esc_html_e( 'Gold ★ badge on the public profile.', 'owambe-connect-core' ); ?></small>
							</p>
							<p style="margin:0 0 10px;">
								<label><input type="checkbox" name="email_verified" value="1" <?php checked( $values['email_verified'], 1 ); ?>/> <strong><?php esc_html_e( 'Email verified', 'owambe-connect-core' ); ?></strong></label>
								<br><small style="color:#6B6361;font-size:12px;"><?php esc_html_e( 'Untick to send this vendor through the verification flow on next dashboard visit.', 'owambe-connect-core' ); ?></small>
							</p>
							<?php if ( $is_edit && $values['vendor_number'] ) : ?>
								<hr style="border:0;border-top:1px dashed #E4DDD2;margin:14px 0;">
								<p style="margin:0;">
									<small style="color:#6B6361;font-size:11px;text-transform:uppercase;letter-spacing:.08em;"><?php esc_html_e( 'Vendor number', 'owambe-connect-core' ); ?></small><br>
									<code style="background:#FAF7F2;border:1px solid #E4DDD2;padding:4px 10px;border-radius:4px;font-size:14px;letter-spacing:.06em;color:#6E0F2C;"><?php echo esc_html( $values['vendor_number'] ); ?></code>
								</p>
							<?php endif; ?>
						</div>

						<?php if ( ! $is_edit ) : ?>
							<div class="oc-av-card">
								<h2><?php esc_html_e( 'Vendor account (optional)', 'owambe-connect-core' ); ?></h2>
								<p style="color:#666;font-size:13px;margin:0 0 10px"><?php esc_html_e( 'Create a login so the vendor can manage their own listing from the frontend dashboard. Otherwise leave blank.', 'owambe-connect-core' ); ?></p>
								<p>
									<label for="av-email"><?php esc_html_e( 'Vendor email (login)', 'owambe-connect-core' ); ?></label>
									<input id="av-email" type="email" name="vendor_email" class="widefat"/>
								</p>
								<p>
									<label for="av-pw"><?php esc_html_e( 'Temporary password', 'owambe-connect-core' ); ?></label>
									<input id="av-pw" type="text" name="vendor_password" class="widefat" placeholder="<?php esc_attr_e( 'min 8 chars, leave blank to auto-generate', 'owambe-connect-core' ); ?>"/>
								</p>
								<p>
									<label><input type="checkbox" name="email_credentials" value="1" checked/> <?php esc_html_e( 'Email credentials to vendor', 'owambe-connect-core' ); ?></label>
								</p>
							</div>
						<?php else :
							$author = get_user_by( 'id', (int) $edit_post->post_author );
							?>
							<div class="oc-av-card">
								<h2><?php esc_html_e( 'Vendor account', 'owambe-connect-core' ); ?></h2>
								<?php if ( $author ) : ?>
									<p style="margin:0 0 12px;font-size:13px;color:#555">
										<strong><?php echo esc_html( $author->display_name ); ?></strong>
									</p>
									<p class="oc-av-row" style="margin-bottom:6px">
										<label for="av-login-email"><?php esc_html_e( 'Login email', 'owambe-connect-core' ); ?></label>
										<span class="oc-av-locked" data-oc-email-lock>
											<input id="av-login-email" type="email" name="vendor_login_email"
												value="<?php echo esc_attr( $author->user_email ); ?>"
												data-original="<?php echo esc_attr( $author->user_email ); ?>"
												readonly />
											<button type="button" class="button button-small" data-oc-email-edit><?php esc_html_e( 'Edit', 'owambe-connect-core' ); ?></button>
										</span>
									</p>
									<p data-oc-email-warning hidden style="color:#a02e2a;font-size:12px;margin:0 0 6px">
										<?php esc_html_e( 'Changing the login email changes how this vendor signs in. Double-check it is spelled correctly.', 'owambe-connect-core' ); ?>
									</p>
									<small style="color:#6B6361;font-size:12px;display:block"><?php esc_html_e( 'Locked to prevent accidental edits. Click "Edit" to fix a vendor who mistyped their email at signup.', 'owambe-connect-core' ); ?></small>
									<script>
									(function () {
										var wrap = document.querySelector('[data-oc-email-lock]');
										if (!wrap) return;
										var input = wrap.querySelector('input');
										var btn   = wrap.querySelector('[data-oc-email-edit]');
										var warn  = document.querySelector('[data-oc-email-warning]');
										var EDIT   = <?php echo wp_json_encode( __( 'Edit', 'owambe-connect-core' ) ); ?>;
										var CANCEL = <?php echo wp_json_encode( __( 'Cancel', 'owambe-connect-core' ) ); ?>;
										btn.addEventListener('click', function () {
											if (input.hasAttribute('readonly')) {
												input.removeAttribute('readonly');
												input.focus();
												btn.textContent = CANCEL;
												if (warn) warn.hidden = false;
											} else {
												input.value = input.getAttribute('data-original');
												input.setAttribute('readonly', 'readonly');
												btn.textContent = EDIT;
												if (warn) warn.hidden = true;
											}
										});
									})();
									</script>
								<?php else : ?>
									<p style="margin:0;font-size:13px;color:#555"><em><?php esc_html_e( 'No linked user.', 'owambe-connect-core' ); ?></em></p>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<div class="oc-av-card oc-av-card--cta">
							<button type="submit" class="button button-primary button-hero" style="width:100%"><?php echo esc_html( $submit_label ); ?></button>
							<?php if ( $is_edit ) : ?>
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . OC_CPT ) ); ?>" class="button" style="width:100%;margin-top:8px;text-align:center"><?php esc_html_e( 'Back to vendors', 'owambe-connect-core' ); ?></a>
							<?php endif; ?>
						</div>

					</div>
				</div>
			</form>
		</div>
		<style>
			.oc-add-vendor h1 { color:#6E0F2C; }
			.oc-av-grid { display:grid; grid-template-columns:1fr; gap:18px; max-width:1200px; }
			@media (min-width: 960px) { .oc-av-grid { grid-template-columns:1fr 320px; } }
			.oc-av-card { background:#fff; border:1px solid #e4ddd2; border-radius:8px; padding:20px 22px; margin-bottom:14px; }
			.oc-av-card h2 { font-family:Georgia, serif; color:#6E0F2C; font-size:1.05rem; margin:0 0 12px; padding-bottom:8px; border-bottom:2px solid #C9A961; }
			.oc-av-card--cta { background:#FAF7F2; }
			.button-primary.button-hero { background:#6E0F2C; border-color:#6E0F2C; }
			.button-primary.button-hero:hover { background:#4A0A1E; border-color:#4A0A1E; }
			.oc-av-row { display:flex; flex-direction:column; gap:6px; margin:0 0 14px; }
			.oc-av-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
			@media (max-width: 700px) { .oc-av-row-2 { grid-template-columns:1fr; } }
			.oc-av-form input[type="text"], .oc-av-form input[type="email"], .oc-av-form input[type="tel"], .oc-av-form input[type="url"], .oc-av-form input[type="password"], .oc-av-form select, .oc-av-form textarea { width:100%; padding:9px 11px; border:1px solid #ccd0d4; border-radius:4px; font-size:14px; }
			.oc-av-form label { font-weight:600; font-size:13px; }
			.oc-av-checks { display:grid; grid-template-columns:1fr 1fr; gap:6px; }
			.oc-av-checks--col { grid-template-columns:1fr; }
			@media (min-width: 600px) { .oc-av-checks { grid-template-columns:repeat(3, 1fr); } .oc-av-checks--col { grid-template-columns:1fr; } }
			.oc-av-chk { display:flex; align-items:center; gap:6px; padding:6px 8px; border-radius:4px; cursor:pointer; font-weight:400; font-size:13px; border:1px solid transparent; }
			.oc-av-chk:hover { border-color:#C9A961; }
			.oc-av-chk input:checked + span { color:#6E0F2C; font-weight:600; }
			.oc-av-gallery__grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(110px, 1fr)); gap:8px; margin:0 0 10px; }
			.oc-av-gallery__item { display:flex; flex-direction:column; gap:4px; padding:6px; border:1px solid #E4DDD2; border-radius:6px; background:#FAF7F2; cursor:pointer; align-items:center; }
			.oc-av-gallery__img { width:100%; height:90px; object-fit:cover; border-radius:4px; display:block; }
			.oc-av-gallery__rm { font-size:11px; color:#6B6361; display:flex; gap:4px; align-items:center; }
			.oc-av-gallery__item:has(input:checked) { background:#FBECEF; border-color:#B0354F; }
			.oc-av-gallery__item:has(input:checked) .oc-av-gallery__img { opacity:.4; }

			/* +44 prefix input */
			.oc-av-prefix { display:flex; align-items:stretch; border:1px solid #ccd0d4; border-radius:4px; overflow:hidden; background:#fff; max-width:280px; }
			.oc-av-prefix:focus-within { border-color:#6E0F2C; box-shadow:0 0 0 2px rgba(110,15,44,.18); }
			.oc-av-prefix__tag { background:#FAF7F2; padding:0 12px; display:inline-flex; align-items:center; font-weight:600; border-right:1px solid #E4DDD2; }
			.oc-av-prefix input { border:0 !important; flex:1; min-width:0; padding:9px 11px; }
			.oc-av-prefix input:focus { box-shadow:none !important; outline:none; }

			/* Locked login-email field */
			.oc-av-locked { display:flex; gap:8px; align-items:center; }
			.oc-av-locked input { flex:1; min-width:0; }
			.oc-av-locked input[readonly] { background:#FAF7F2; color:#6B6361; cursor:not-allowed; }

			/* Yes / No / Unanswered row */
			.oc-av-radio-row { display:inline-flex; flex-wrap:wrap; gap:14px; padding-top:4px; }
			.oc-av-radio-row label { font-weight:400; font-size:13px; color:#1F1B1A; display:inline-flex; align-items:center; gap:4px; }

			/* Vendor tags — collapsible group list */
			.oc-av-tag-groups { display:grid; gap:8px; }
			@media (min-width:1100px) { .oc-av-tag-groups { grid-template-columns:1fr 1fr; gap:8px 12px; align-items:start; } }
			.oc-av-tag-group { border:1px solid #E4DDD2; border-radius:6px; background:#FAF7F2; }
			.oc-av-tag-group summary { cursor:pointer; padding:10px 14px; display:flex; align-items:center; justify-content:space-between; gap:10px; font-weight:600; color:#1F1B1A; list-style:none; }
			.oc-av-tag-group summary::-webkit-details-marker { display:none; }
			.oc-av-tag-group summary::after { content:"▾"; color:#A8893D; transition:transform .2s ease; }
			.oc-av-tag-group[open] summary::after { transform:rotate(180deg); }
			.oc-av-tag-group__title { flex:1; font-size:14px; }
			.oc-av-tag-group__count { background:#fff; border:1px solid #E4DDD2; color:#6B6361; padding:2px 9px; border-radius:999px; font-size:11px; font-weight:700; font-variant-numeric:tabular-nums; }
			.oc-av-tag-group:has(input:checked) { border-color:#6E0F2C; background:#fff; }
			.oc-av-tag-group:has(input:checked) .oc-av-tag-group__count { background:#6E0F2C; color:#fff; border-color:#6E0F2C; }
			.oc-av-tag-group .oc-av-checks { padding:6px 14px 14px; }
		</style>
		<?php
	}

	public function handle() {
		$ref = admin_url( 'admin.php?page=' . self::PAGE );

		if ( ! current_user_can( 'edit_posts' ) ) wp_die( -1 );
		if ( ! isset( $_POST['oc_av_nonce'] ) || ! wp_verify_nonce( $_POST['oc_av_nonce'], self::ACTION ) ) {
			$this->redirect( $ref, __( 'Security check failed.', 'owambe-connect-core' ) );
		}

		// Detect edit mode.
		$vendor_id = isset( $_POST['vendor_id'] ) ? (int) $_POST['vendor_id'] : 0;
		$is_edit   = false;
		if ( $vendor_id ) {
			$existing = get_post( $vendor_id );
			if ( ! $existing || OC_CPT !== $existing->post_type ) {
				$this->redirect( $ref, __( 'Vendor not found.', 'owambe-connect-core' ) );
			}
			if ( ! current_user_can( 'edit_post', $vendor_id ) ) wp_die( -1 );
			$is_edit = true;
			$ref     = admin_url( 'admin.php?page=' . self::PAGE . '&edit=' . $vendor_id );
		}

		$business_name = isset( $_POST['business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) ) : '';
		$category_ids  = isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ? array_map( 'absint', $_POST['categories'] ) : [];
		$status_in     = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : OC_STATUS_APPROVED;
		$allowed_statuses = $is_edit
			? [ OC_STATUS_APPROVED, OC_STATUS_PENDING, OC_STATUS_REJECTED ]
			: [ OC_STATUS_APPROVED, OC_STATUS_PENDING ];
		$status         = in_array( $status_in, $allowed_statuses, true ) ? $status_in : OC_STATUS_APPROVED;
		$rejection_note = isset( $_POST['rejection_note'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['rejection_note'] ) ) ) : '';

		if ( '' === $business_name ) {
			$this->redirect( $ref, __( 'Business name is required.', 'owambe-connect-core' ) );
		}
		if ( empty( $category_ids ) ) {
			$this->redirect( $ref, __( 'Please select at least one category.', 'owambe-connect-core' ) );
		}
		// Reject status MUST come with a meaningful explanation.
		if ( OC_STATUS_REJECTED === $status && strlen( $rejection_note ) < 10 ) {
			$this->redirect( $ref, __( 'Rejection reason is required (minimum 10 characters). It is sent to the vendor.', 'owambe-connect-core' ) );
		}

		// Edit mode: validate an optional login-email change up front so we never
		// half-save the post before bailing. The change itself is applied after the
		// post is saved (see below). Lets admins fix a mistyped signup email.
		$new_login_email = '';
		if ( $is_edit && isset( $_POST['vendor_login_email'] ) ) {
			$candidate   = sanitize_email( wp_unslash( $_POST['vendor_login_email'] ) );
			$email_owner = get_user_by( 'id', (int) $existing->post_author );
			if ( $email_owner && '' !== $candidate && 0 !== strcasecmp( $candidate, $email_owner->user_email ) ) {
				if ( ! is_email( $candidate ) ) {
					$this->redirect( $ref, __( 'The login email is invalid.', 'owambe-connect-core' ) );
				}
				$clash = get_user_by( 'email', $candidate );
				if ( $clash && (int) $clash->ID !== (int) $email_owner->ID ) {
					$this->redirect( $ref, __( 'That email is already used by another account.', 'owambe-connect-core' ) );
				}
				$new_login_email = $candidate;
			}
		}

		// Optionally create a vendor user account (only on create, not edit).
		$vendor_email    = ( ! $is_edit && isset( $_POST['vendor_email'] ) )    ? sanitize_email( wp_unslash( $_POST['vendor_email'] ) ) : '';
		$vendor_password = ( ! $is_edit && isset( $_POST['vendor_password'] ) ) ? (string) wp_unslash( $_POST['vendor_password'] )       : '';
		$email_creds     = ! $is_edit && ! empty( $_POST['email_credentials'] );
		$vendor_user_id  = 0;
		$plain_password  = '';

		if ( $vendor_email ) {
			if ( ! is_email( $vendor_email ) ) {
				$this->redirect( $ref, __( 'Vendor email is invalid.', 'owambe-connect-core' ) );
			}
			$existing = get_user_by( 'email', $vendor_email );
			if ( $existing ) {
				$vendor_user_id = $existing->ID;
				$user = new WP_User( $existing->ID );
				if ( ! in_array( OC_ROLE, (array) $user->roles, true ) ) {
					$user->add_role( OC_ROLE );
				}
			} else {
				if ( '' === $vendor_password ) {
					$vendor_password = wp_generate_password( 12, false );
				}
				if ( strlen( $vendor_password ) < 8 ) {
					$this->redirect( $ref, __( 'Password must be at least 8 characters.', 'owambe-connect-core' ) );
				}
				$plain_password = $vendor_password;
				$vendor_user_id = wp_insert_user( [
					'user_login'   => $vendor_email,
					'user_email'   => $vendor_email,
					'user_pass'    => $vendor_password,
					'display_name' => $business_name,
					'role'         => OC_ROLE,
				] );
				if ( is_wp_error( $vendor_user_id ) ) {
					$this->redirect( $ref, $vendor_user_id->get_error_message() );
				}
			}
		}

		// Create or update the vendor post.
		$post_args = [
			'post_type'    => OC_CPT,
			'post_status'  => $status,
			'post_title'   => $business_name,
			'post_content' => isset( $_POST['bio'] ) ? wp_kses_post( wp_unslash( $_POST['bio'] ) ) : '',
		];

		if ( $is_edit ) {
			// Write the rejection note BEFORE wp_update_post fires transition_post_status,
			// so the email handler can pick it up. Clear it on non-rejected statuses.
			if ( OC_STATUS_REJECTED === $status ) {
				update_post_meta( $vendor_id, '_oc_rejection_note', $rejection_note );
			} else {
				delete_post_meta( $vendor_id, '_oc_rejection_note' );
			}

			$post_args['ID'] = $vendor_id;
			$post_id         = wp_update_post( $post_args, true );
		} else {
			$post_args['post_author'] = $vendor_user_id ?: get_current_user_id();
			$post_id                  = wp_insert_post( $post_args, true );
		}

		if ( is_wp_error( $post_id ) ) {
			$this->redirect( $ref, $post_id->get_error_message() );
		}

		// ── New-field normalisation (mirrors class-dashboard.php) ──────────
		// WhatsApp: accept either the new whatsapp_local field (10 digits, no
		// prefix) or the legacy whatsapp field; always store as +44XXXXXXXXXX.
		$wa_raw = '';
		if ( isset( $_POST['whatsapp_local'] ) ) {
			$wa_raw = (string) wp_unslash( $_POST['whatsapp_local'] );
		} elseif ( isset( $_POST['whatsapp'] ) ) {
			$wa_raw = (string) wp_unslash( $_POST['whatsapp'] );
		}
		$wa_canon = function_exists( 'oc_normalize_uk_whatsapp' )
			? oc_normalize_uk_whatsapp( $wa_raw )
			: oc_sanitize_phone( $wa_raw );

		// Country: validate against known UK constituents.
		$country_options_v = function_exists( 'oc_country_options' ) ? oc_country_options() : [];
		$country_in        = isset( $_POST['location_country'] ) ? sanitize_key( wp_unslash( $_POST['location_country'] ) ) : '';
		$country           = isset( $country_options_v[ $country_in ] ) ? $country_in : '';

		// Areas: free-form CSV; we trim + dedupe.
		$areas = isset( $_POST['location_areas'] )
			? oc_sanitize_csv( wp_unslash( $_POST['location_areas'] ) )
			: [];

		// Regions: England-only; validate against the canonical list and drop
		// if the vendor isn't in England (mirrors the dashboard saver).
		$region_opts = function_exists( 'oc_region_options' ) ? oc_region_options() : [];
		$regions     = isset( $_POST['location_regions'] )
			? array_values( array_intersect( oc_sanitize_csv( wp_unslash( $_POST['location_regions'] ) ), $region_opts ) )
			: [];
		if ( 'england' !== $country ) {
			$regions = [];
		}

		// Compose a human-readable location summary that mirrors the dashboard's
		// logic EXACTLY: structured fields (areas + regions + country) win so the
		// searchable summary always reflects them; the legacy free-text field is
		// only a fallback when nothing structured is set. (Previously the legacy
		// field won — which, because edit-mode pre-fills it, silently dropped
		// regions from `_oc_location` and broke region search on admin edits.)
		$loc_legacy = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';
		if ( $country || $areas || $regions ) {
			$parts = [];
			if ( $areas )   $parts[] = implode( ', ', array_slice( $areas, 0, 10 ) );
			if ( $regions ) $parts[] = implode( ', ', $regions );
			if ( $country ) $parts[] = $country_options_v[ $country ];
			$loc_summary = implode( ' — ', $parts );
		} elseif ( '' !== $loc_legacy ) {
			$loc_summary = $loc_legacy;
		} else {
			$loc_summary = '';
		}

		// Cultural + tags + Y/N — same validation rules as the dashboard saver.
		$cultural_keys = function_exists( 'oc_cultural_specialty_options' ) ? array_keys( oc_cultural_specialty_options() ) : [];
		$cultural      = isset( $_POST['cultural_specialties'] )
			? array_values( array_intersect( oc_sanitize_csv( wp_unslash( $_POST['cultural_specialties'] ) ), $cultural_keys ) )
			: [];

		$allowed_tags = function_exists( 'oc_vendor_tag_options_flat' ) ? oc_vendor_tag_options_flat() : [];
		$tags         = isset( $_POST['vendor_tags'] )
			? array_values( array_intersect( oc_sanitize_csv( wp_unslash( $_POST['vendor_tags'] ) ), $allowed_tags ) )
			: [];

		$reg_biz_raw  = isset( $_POST['registered_business'] ) ? sanitize_key( wp_unslash( $_POST['registered_business'] ) ) : '';
		$reg_biz      = in_array( $reg_biz_raw, [ 'yes', 'no' ], true ) ? $reg_biz_raw : '';
		$nigerian_raw = isset( $_POST['nigerian_specialty'] )  ? sanitize_key( wp_unslash( $_POST['nigerian_specialty'] ) )  : '';
		$nigerian     = in_array( $nigerian_raw, [ 'yes', 'no' ], true ) ? $nigerian_raw : '';

		// Meta.
		$pairs = [
			'_oc_business_name'        => $business_name,
			'_oc_location'             => $loc_summary,
			'_oc_location_country'     => $country,
			'_oc_location_areas'       => $areas,
			'_oc_location_regions'     => $regions,
			'_oc_cultural_specialties' => $cultural,
			'_oc_nigerian_specialty'   => $nigerian,
			'_oc_registered_business'  => $reg_biz,
			'_oc_vendor_tags'          => $tags,
			'_oc_bio'                  => isset( $_POST['bio'] )         ? wp_kses_post( wp_unslash( $_POST['bio'] ) )                : '',
			'_oc_services'             => isset( $_POST['services'] )    ? wp_kses_post( wp_unslash( $_POST['services'] ) )           : '',
			'_oc_price_range'          => isset( $_POST['price_range'] ) ? sanitize_text_field( wp_unslash( $_POST['price_range'] ) ) : '',
			'_oc_whatsapp'             => $wa_canon,
			'_oc_public_email'         => isset( $_POST['public_email'] ) ? sanitize_email( wp_unslash( $_POST['public_email'] ) )    : '',
			'_oc_instagram'            => isset( $_POST['instagram'] )   ? oc_sanitize_handle( wp_unslash( $_POST['instagram'] ) )    : '',
			'_oc_facebook'             => isset( $_POST['facebook'] )    ? sanitize_text_field( wp_unslash( $_POST['facebook'] ) )    : '',
			'_oc_website'              => isset( $_POST['website'] )     ? esc_url_raw( wp_unslash( $_POST['website'] ) )             : '',
			'_oc_languages'            => isset( $_POST['languages'] )   ? oc_sanitize_csv( wp_unslash( $_POST['languages'] ) )       : [],
			'_oc_featured'             => ! empty( $_POST['featured'] )       ? 1 : 0,
			'_oc_verified'             => ! empty( $_POST['verified'] )       ? 1 : 0,
			'_oc_founding_vendor'      => ! empty( $_POST['founding'] )       ? 1 : 0,
			'_oc_email_verified'       => ! empty( $_POST['email_verified'] ) ? 1 : 0,
		];
		foreach ( $pairs as $k => $v ) {
			update_post_meta( $post_id, $k, $v );
		}

		wp_set_object_terms( $post_id, $category_ids, OC_TAX );

		// Image uploads.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( ! empty( $_FILES['logo']['name'] ) ) {
			$id = media_handle_upload( 'logo', $post_id );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $post_id, '_oc_logo_id', $id );
				set_post_thumbnail( $post_id, $id );
			}
		}
		if ( ! empty( $_FILES['banner']['name'] ) ) {
			$id = media_handle_upload( 'banner', $post_id );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $post_id, '_oc_banner_id', $id );
			}
		}

		// Gallery
		$gallery_max_count = max( 0, (int) oc_get_setting( 'gallery_max_images', 6 ) );
		$gallery_max_bytes = max( 1, (int) oc_get_setting( 'gallery_max_mb',     3 ) ) * 1024 * 1024;
		$existing_gallery  = (array) get_post_meta( $post_id, '_oc_gallery_ids', true );
		$existing_gallery  = array_values( array_filter( array_map( 'intval', $existing_gallery ) ) );
		if ( ! empty( $_POST['gallery_remove'] ) && is_array( $_POST['gallery_remove'] ) ) {
			$drop             = array_map( 'intval', $_POST['gallery_remove'] );
			$existing_gallery = array_values( array_diff( $existing_gallery, $drop ) );
		}
		if ( $gallery_max_count > 0 ) {
			$existing_gallery = oc_handle_gallery_upload( 'gallery', $post_id, $gallery_max_count, $gallery_max_bytes, $existing_gallery );
		}
		update_post_meta( $post_id, '_oc_gallery_ids', $existing_gallery );

		// Email credentials to the new vendor (create only).
		if ( ! $is_edit && $vendor_user_id && $email_creds && $plain_password ) {
			$this->mail_credentials( $vendor_user_id, $plain_password );
		}

		// If admin created it as approved straight away, fire approval hook.
		if ( ! $is_edit && OC_STATUS_APPROVED === $status ) {
			do_action( 'oc_after_vendor_approved', $post_id );
		}

		// Apply the validated login-email change (edit mode). user_login is left
		// as-is — WordPress accepts the email address at login regardless.
		if ( $is_edit && '' !== $new_login_email ) {
			$res = wp_update_user( [ 'ID' => (int) $existing->post_author, 'user_email' => $new_login_email ] );
			if ( is_wp_error( $res ) ) {
				$this->redirect( $ref, $res->get_error_message() );
			}
		}

		if ( $is_edit ) {
			wp_safe_redirect( add_query_arg( 'oc_updated', 1, admin_url( 'admin.php?page=' . self::PAGE . '&edit=' . $post_id ) ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'oc_created', $post_id, admin_url( 'admin.php?page=' . self::PAGE ) ) );
		exit;
	}

	private function mail_credentials( $user_id, $password ) {
		$user  = get_user_by( 'id', $user_id );
		if ( ! $user ) return;
		$login = oc_page_url( 'vendor-login' );
		$body  = '<div style="font-family:Inter,Arial,sans-serif;max-width:580px;margin:0 auto;padding:24px;background:#FAF7F2"><div style="background:#fff;border:1px solid #EFEAE2;border-radius:12px;padding:32px">'
			. '<h1 style="font-family:Georgia,serif;color:#6E0F2C">' . esc_html__( 'Welcome to Owambe Connect', 'owambe-connect-core' ) . '</h1>'
			. '<p>' . esc_html__( 'An account has been created for you. Use the credentials below to log in and manage your listing.', 'owambe-connect-core' ) . '</p>'
			. '<p><strong>' . esc_html__( 'Email:', 'owambe-connect-core' ) . '</strong> ' . esc_html( $user->user_email ) . '<br>'
			. '<strong>' . esc_html__( 'Password:', 'owambe-connect-core' ) . '</strong> <code>' . esc_html( $password ) . '</code></p>'
			. '<p style="margin:18px 0"><a href="' . esc_url( $login ) . '" style="background:#6E0F2C;color:#fff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:600">' . esc_html__( 'Log in', 'owambe-connect-core' ) . '</a></p>'
			. '<p style="color:#6B6361;font-size:13px">' . esc_html__( 'Please change your password after your first login.', 'owambe-connect-core' ) . '</p>'
			. '</div></div>';
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', OC_Mail::from_name(), OC_Mail::from_email() ),
		];
		wp_mail( $user->user_email, __( 'Your Owambe Connect login details', 'owambe-connect-core' ), $body, $headers );
	}

	private function redirect( $url, $message ) {
		wp_safe_redirect( add_query_arg( 'oc_admin_error', rawurlencode( $message ), $url ) );
		exit;
	}
}
