<?php
/**
 * Owambe Connect — fully branded vendor list page.
 *
 * Replaces the default WP post list at edit.php?post_type=oc_vendor with a
 * custom-styled admin page that lives at admin.php?page=oc-vendors. All
 * styling, KPIs, filters, bulk actions and pagination come from this class.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Vendors_List {

	const PAGE        = 'oc-vendors';
	const BULK_ACTION = 'oc_vendors_bulk';
	const PER_PAGE    = 20;

	public function register() {
		add_action( 'admin_menu',                          [ $this, 'menu' ], 8 );
		add_action( 'admin_init',                          [ $this, 'redirect_default_list' ] );
		add_action( 'admin_post_' . self::BULK_ACTION,     [ $this, 'handle_bulk' ] );
		add_filter( 'parent_file',                         [ $this, 'highlight_parent' ] );
		add_filter( 'submenu_file',                        [ $this, 'highlight_submenu' ] );
	}

	/**
	 * Hidden submenu page (null parent). It's reachable by URL and via the
	 * sidebar "Vendors" → "Vendors" link, which we redirect to here.
	 */
	public function menu() {
		add_submenu_page(
			null,
			__( 'Vendors', 'owambe-connect-core' ),
			__( 'Vendors', 'owambe-connect-core' ),
			'edit_posts',
			self::PAGE,
			[ $this, 'render' ]
		);
	}

	/**
	 * When admins click the auto-generated "Vendors" link (which points to
	 * edit.php?post_type=oc_vendor) send them to our custom page instead.
	 * Skip on POST and on requests with action params (trash/restore/etc.).
	 */
	public function redirect_default_list() {
		global $pagenow;
		if ( 'edit.php' !== $pagenow ) return;
		if ( empty( $_GET['post_type'] ) || OC_CPT !== $_GET['post_type'] ) return;
		// `?page=oc-analytics` etc. live under edit.php?post_type=... — never redirect those.
		if ( ! empty( $_GET['page'] ) ) return;
		if ( ! empty( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) return;
		if ( ! empty( $_GET['action'] )  && '-1' !== $_GET['action'] )  return;
		if ( ! empty( $_GET['action2'] ) && '-1' !== $_GET['action2'] ) return;

		$args = [];
		if ( ! empty( $_GET['post_status'] ) ) $args['status'] = sanitize_key( $_GET['post_status'] );
		if ( ! empty( $_GET['s'] ) )           $args['s']      = sanitize_text_field( wp_unslash( $_GET['s'] ) );

		wp_safe_redirect( add_query_arg( array_merge( [ 'page' => self::PAGE ], $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Keep the "Vendors" item highlighted in the sidebar while on our page. */
	public function highlight_parent( $parent_file ) {
		$screen = get_current_screen();
		if ( $screen && false !== strpos( (string) $screen->id, self::PAGE ) ) {
			return 'edit.php?post_type=' . OC_CPT;
		}
		return $parent_file;
	}

	public function highlight_submenu( $submenu_file ) {
		$screen = get_current_screen();
		if ( $screen && false !== strpos( (string) $screen->id, self::PAGE ) ) {
			return 'edit.php?post_type=' . OC_CPT;
		}
		return $submenu_file;
	}

	// =========================================================================
	//  Render
	// =========================================================================

	public function render() {
		if ( ! current_user_can( 'edit_posts' ) ) return;

		$filters = $this->parse_filters();
		$query   = $this->run_query( $filters );
		$kpis    = $this->kpis();
		$cats    = OC_Queries::categories_with_counts();
		$base    = admin_url( 'admin.php?page=' . self::PAGE );
		$add_url = admin_url( 'admin.php?page=oc-add-vendor' );

		// Status pill links.
		$status_links = [
			'all'              => [ __( 'All',           'owambe-connect-core' ), $kpis['total']    ],
			OC_STATUS_APPROVED => [ __( 'Approved',      'owambe-connect-core' ), $kpis['live']     ],
			OC_STATUS_PENDING  => [ __( 'Pending',       'owambe-connect-core' ), $kpis['pending']  ],
			OC_STATUS_REJECTED => [ __( 'Needs Changes', 'owambe-connect-core' ), $kpis['rejected'] ],
			'featured'         => [ __( '★ Featured',    'owambe-connect-core' ), $kpis['featured'] ],
			'trash'            => [ __( 'Trash',         'owambe-connect-core' ), $kpis['trash']    ],
		];
		?>
		<div class="wrap oc-vl">
			<header class="oc-vl-head">
				<div>
					<h1><?php esc_html_e( 'Vendors', 'owambe-connect-core' ); ?></h1>
					<p class="oc-vl-sub"><?php esc_html_e( 'Manage every vendor on your marketplace — approve, reject, feature, edit.', 'owambe-connect-core' ); ?></p>
				</div>
				<a class="oc-vl-add" href="<?php echo esc_url( $add_url ); ?>">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add Vendor', 'owambe-connect-core' ); ?>
				</a>
			</header>

			<?php $this->render_notices(); ?>

			<!-- KPI strip -->
			<div class="oc-vl-kpis">
				<?php $this->kpi_card( __( 'Total',         'owambe-connect-core' ), $kpis['total'],    'groups',         '#1F1B1A' ); ?>
				<?php $this->kpi_card( __( 'Approved',      'owambe-connect-core' ), $kpis['live'],     'yes-alt',        '#2E7D5B' ); ?>
				<?php $this->kpi_card( __( 'Pending',       'owambe-connect-core' ), $kpis['pending'],  'clock',          '#B8860B' ); ?>
				<?php $this->kpi_card( __( 'Needs changes', 'owambe-connect-core' ), $kpis['rejected'], 'warning',        '#B0354F' ); ?>
				<?php $this->kpi_card( __( 'Featured',      'owambe-connect-core' ), $kpis['featured'], 'star-filled',    '#A8893D' ); ?>
			</div>

			<!-- Status pills -->
			<nav class="oc-vl-pills">
				<?php foreach ( $status_links as $key => $row ) :
					[ $label, $count ] = $row;
					$is_active = ( $filters['status'] === $key );
					$url = add_query_arg( [ 'status' => $key, 'cat' => $filters['cat'], 'location' => $filters['location'], 's' => $filters['s'] ], $base );
					?>
					<a class="oc-vl-pill <?php echo $is_active ? 'is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
						<?php echo esc_html( $label ); ?>
						<span class="oc-vl-pill__count"><?php echo (int) $count; ?></span>
					</a>
				<?php endforeach; ?>
			</nav>

			<!-- Filter bar -->
			<form class="oc-vl-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page"   value="<?php echo esc_attr( self::PAGE ); ?>"/>
				<input type="hidden" name="status" value="<?php echo esc_attr( $filters['status'] ); ?>"/>

				<div class="oc-vl-filter oc-vl-filter--search">
					<span class="dashicons dashicons-search"></span>
					<input type="search" name="s" value="<?php echo esc_attr( $filters['s'] ); ?>" placeholder="<?php esc_attr_e( 'Search vendors by name…', 'owambe-connect-core' ); ?>"/>
				</div>

				<select name="cat" class="oc-vl-filter">
					<option value=""><?php esc_html_e( 'All categories', 'owambe-connect-core' ); ?></option>
					<?php foreach ( $cats as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $filters['cat'], $term->slug ); ?>>
							<?php echo esc_html( $term->name ); ?> (<?php echo (int) $term->count; ?>)
						</option>
					<?php endforeach; ?>
				</select>

				<input type="text" name="location" class="oc-vl-filter" value="<?php echo esc_attr( $filters['location'] ); ?>" placeholder="<?php esc_attr_e( 'Location…', 'owambe-connect-core' ); ?>"/>

				<button type="submit" class="oc-vl-btn oc-vl-btn--primary"><?php esc_html_e( 'Filter', 'owambe-connect-core' ); ?></button>
				<?php if ( $filters['s'] || $filters['cat'] || $filters['location'] ) : ?>
					<a class="oc-vl-btn" href="<?php echo esc_url( add_query_arg( 'status', $filters['status'], $base ) ); ?>"><?php esc_html_e( 'Reset', 'owambe-connect-core' ); ?></a>
				<?php endif; ?>
			</form>

			<!-- Bulk + result bar -->
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="oc-vl-bulk-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::BULK_ACTION ); ?>"/>
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $_SERVER['REQUEST_URI'] ?? $base ); ?>"/>
				<?php wp_nonce_field( self::BULK_ACTION, 'oc_vl_nonce' ); ?>

				<div class="oc-vl-bar">
					<div class="oc-vl-bulk">
						<select name="bulk_action">
							<option value=""><?php esc_html_e( 'Bulk actions', 'owambe-connect-core' ); ?></option>
							<option value="approve"><?php esc_html_e( 'Approve', 'owambe-connect-core' ); ?></option>
							<option value="reject"><?php esc_html_e( 'Reject', 'owambe-connect-core' ); ?></option>
							<option value="feature"><?php esc_html_e( 'Mark as featured', 'owambe-connect-core' ); ?></option>
							<option value="unfeature"><?php esc_html_e( 'Remove featured', 'owambe-connect-core' ); ?></option>
							<option value="verify"><?php esc_html_e( 'Mark as verified', 'owambe-connect-core' ); ?></option>
							<option value="unverify"><?php esc_html_e( 'Remove verified', 'owambe-connect-core' ); ?></option>
							<option value="founding"><?php esc_html_e( 'Mark as founding vendor', 'owambe-connect-core' ); ?></option>
							<option value="unfounding"><?php esc_html_e( 'Remove founding vendor', 'owambe-connect-core' ); ?></option>
							<option value="trash"><?php esc_html_e( 'Move to trash', 'owambe-connect-core' ); ?></option>
							<option value="delete" style="color:#B0354F;"><?php esc_html_e( '✕ Delete permanently (and remove user)', 'owambe-connect-core' ); ?></option>
							<?php if ( 'trash' === $filters['status'] ) : ?>
								<option value="restore"><?php esc_html_e( 'Restore', 'owambe-connect-core' ); ?></option>
							<?php endif; ?>
						</select>
						<button type="submit" class="oc-vl-btn"><?php esc_html_e( 'Apply', 'owambe-connect-core' ); ?></button>
					</div>
					<div class="oc-vl-meta">
						<?php
						$total = (int) $query->found_posts;
						$paged = max( 1, (int) $filters['paged'] );
						$pages = max( 1, (int) $query->max_num_pages );
						/* translators: 1: total vendors */
						printf( esc_html( _n( '%s vendor', '%s vendors', $total, 'owambe-connect-core' ) ), '<strong>' . number_format_i18n( $total ) . '</strong>' );
						echo '  ·  ';
						/* translators: 1: current page, 2: total pages */
						printf( esc_html__( 'Page %1$d of %2$d', 'owambe-connect-core' ), $paged, $pages );
						?>
					</div>
				</div>

				<!-- Vendor list -->
				<?php if ( ! $query->have_posts() ) : ?>
					<div class="oc-vl-empty">
						<span class="dashicons dashicons-info-outline"></span>
						<h3><?php esc_html_e( 'No vendors match your filters.', 'owambe-connect-core' ); ?></h3>
						<p><?php esc_html_e( 'Try clearing the search or pick a different status.', 'owambe-connect-core' ); ?></p>
						<a class="oc-vl-btn oc-vl-btn--primary" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Clear filters', 'owambe-connect-core' ); ?></a>
					</div>
				<?php else : ?>
					<div class="oc-vl-table">
						<div class="oc-vl-row oc-vl-row--head">
							<div class="oc-vl-col oc-vl-col--check"><input type="checkbox" id="oc-vl-check-all"/></div>
							<div class="oc-vl-col oc-vl-col--vendor"><?php esc_html_e( 'Vendor', 'owambe-connect-core' ); ?></div>
							<div class="oc-vl-col oc-vl-col--meta"><?php esc_html_e( 'Location', 'owambe-connect-core' ); ?></div>
							<div class="oc-vl-col oc-vl-col--status"><?php esc_html_e( 'Status', 'owambe-connect-core' ); ?></div>
							<div class="oc-vl-col oc-vl-col--cats"><?php esc_html_e( 'Categories', 'owambe-connect-core' ); ?></div>
							<div class="oc-vl-col oc-vl-col--date"><?php esc_html_e( 'Submitted', 'owambe-connect-core' ); ?></div>
							<div class="oc-vl-col oc-vl-col--actions"></div>
						</div>
						<?php while ( $query->have_posts() ) : $query->the_post(); $this->render_row( get_post() ); endwhile; wp_reset_postdata(); ?>
					</div>

					<?php $this->render_pagination( $query, $filters, $base ); ?>
				<?php endif; ?>
			</form>
		</div>

		<script>
		(function () {
			var checkAll = document.getElementById('oc-vl-check-all');
			if (checkAll) {
				checkAll.addEventListener('change', function () {
					document.querySelectorAll('.oc-vl-row__check').forEach(function (cb) { cb.checked = checkAll.checked; });
				});
			}
			document.querySelectorAll('[data-oc-confirm]').forEach(function (a) {
				a.addEventListener('click', function (e) {
					if (!confirm(a.getAttribute('data-oc-confirm'))) e.preventDefault();
				});
			});
			document.querySelectorAll('[data-oc-reject]').forEach(function (a) {
				a.addEventListener('click', function (e) {
					e.preventDefault();
					var reason = prompt(<?php echo wp_json_encode( __( 'Reason (will be emailed to the vendor). Leave blank for no reason.', 'owambe-connect-core' ) ); ?>, '');
					if (reason === null) return;
					var form = document.createElement('form');
					form.method = 'POST';
					form.action = a.getAttribute('href');
					form.innerHTML =
						'<input type="hidden" name="action" value="oc_reject_vendor"/>' +
						'<input type="hidden" name="post" value="' + a.getAttribute('data-oc-reject') + '"/>' +
						'<input type="hidden" name="_wpnonce" value="' + a.getAttribute('data-oc-nonce') + '"/>' +
						'<input type="hidden" name="oc_rejection_note" value="' + reason.replace(/"/g, '&quot;') + '"/>';
					document.body.appendChild(form);
					form.submit();
				});
			});
			var bulkForm = document.getElementById('oc-vl-bulk-form');
			if (bulkForm) {
				bulkForm.addEventListener('submit', function (e) {
					var sel = bulkForm.querySelector('select[name="bulk_action"]');
					if (!sel || !sel.value) { e.preventDefault(); alert(<?php echo wp_json_encode( __( 'Pick an action first.', 'owambe-connect-core' ) ); ?>); return; }
					var picked = bulkForm.querySelectorAll('.oc-vl-row__check:checked').length;
					if (!picked) { e.preventDefault(); alert(<?php echo wp_json_encode( __( 'Select at least one vendor.', 'owambe-connect-core' ) ); ?>); return; }
					if (sel.value === 'delete') {
						var phrase = <?php echo wp_json_encode( __( 'DELETE', 'owambe-connect-core' ) ); ?>;
						var typed = prompt(
							picked + <?php echo wp_json_encode( ' ' . __( 'vendor(s) will be permanently deleted along with their user accounts. This cannot be undone.\n\nType DELETE to confirm:', 'owambe-connect-core' ) ); ?>,
							''
						);
						if (typed !== phrase) {
							e.preventDefault();
							if (typed !== null) {
								alert(<?php echo wp_json_encode( __( 'Cancelled — the confirmation phrase did not match.', 'owambe-connect-core' ) ); ?>);
							}
						}
					}
				});
			}
		})();
		</script>

		<?php $this->print_styles(); ?>
		<?php
	}

	private function render_row( $post ) {
		$status        = $post->post_status;
		$logo_id       = (int) get_post_meta( $post->ID, '_oc_logo_id', true );
		$location      = (string) get_post_meta( $post->ID, '_oc_location', true );
		$is_featured   = (int) get_post_meta( $post->ID, '_oc_featured', true ) === 1;
		$is_verified   = (int) get_post_meta( $post->ID, '_oc_verified', true ) === 1;
		$is_founding   = (int) get_post_meta( $post->ID, '_oc_founding_vendor', true ) === 1;
		$bio           = (string) get_post_meta( $post->ID, '_oc_bio', true );
		$vendor_number = (string) get_post_meta( $post->ID, '_oc_vendor_number', true );
		$cats          = wp_get_object_terms( $post->ID, OC_TAX );
		if ( is_wp_error( $cats ) ) $cats = [];

		$status_color = [ OC_STATUS_PENDING => '#B8860B', OC_STATUS_APPROVED => '#2E7D5B', OC_STATUS_REJECTED => '#B0354F', 'trash' => '#6B6361' ][ $status ] ?? '#555';

		$edit_url     = admin_url( 'admin.php?page=oc-add-vendor&edit=' . $post->ID );
		$view_url     = get_permalink( $post );
		$approve_url  = wp_nonce_url( admin_url( 'admin-post.php?action=oc_approve_vendor&post=' . $post->ID ),  'oc_approve_' . $post->ID );
		$feature_url  = wp_nonce_url( admin_url( 'admin-post.php?action=oc_toggle_featured&post=' . $post->ID ), 'oc_toggle_featured_' . $post->ID );
		$verify_url   = wp_nonce_url( admin_url( 'admin-post.php?action=oc_toggle_verified&post=' . $post->ID ), 'oc_toggle_verified_' . $post->ID );
		$trash_url    = get_delete_post_link( $post->ID );
		$reject_nonce = wp_create_nonce( 'oc_reject_' . $post->ID );
		?>
		<div class="oc-vl-row">
			<div class="oc-vl-col oc-vl-col--check">
				<input type="checkbox" class="oc-vl-row__check" name="ids[]" value="<?php echo (int) $post->ID; ?>"/>
			</div>
			<div class="oc-vl-col oc-vl-col--vendor">
				<div class="oc-vl-vendor">
					<div class="oc-vl-logo">
						<?php if ( $logo_id ) :
							echo wp_get_attachment_image( $logo_id, [ 56, 56 ], false, [ 'class' => 'oc-vl-logo__img' ] );
						else : ?>
							<span class="oc-vl-logo__fallback"><?php echo esc_html( mb_substr( $post->post_title, 0, 1 ) ); ?></span>
						<?php endif; ?>
						<?php if ( $is_featured ) : ?><span class="oc-vl-logo__star" title="<?php esc_attr_e( 'Featured', 'owambe-connect-core' ); ?>">★</span><?php endif; ?>
					</div>
					<div class="oc-vl-vendor__info">
						<a class="oc-vl-vendor__name" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $post->post_title ); ?></a>
						<div class="oc-vl-vendor__pills">
							<?php if ( $vendor_number ) : ?>
								<span class="oc-vl-vendor__num" title="<?php esc_attr_e( 'Vendor registration number', 'owambe-connect-core' ); ?>"><?php echo esc_html( $vendor_number ); ?></span>
							<?php endif; ?>
							<?php if ( $is_verified ) : ?>
								<span class="oc-vl-vendor__verified" title="<?php esc_attr_e( 'Verified by Owambe Connect', 'owambe-connect-core' ); ?>">✓ <?php esc_html_e( 'Verified', 'owambe-connect-core' ); ?></span>
							<?php endif; ?>
							<?php if ( $is_founding ) : ?>
								<span class="oc-vl-vendor__founding" title="<?php esc_attr_e( 'Founding vendor', 'owambe-connect-core' ); ?>"><?php esc_html_e( 'Founding', 'owambe-connect-core' ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( $bio ) : ?>
							<p class="oc-vl-vendor__bio"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $bio ), 14 ) ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="oc-vl-col oc-vl-col--meta">
				<?php if ( $location ) : ?>
					<span class="oc-vl-loc"><span class="dashicons dashicons-location"></span><?php echo esc_html( $location ); ?></span>
				<?php else : ?>
					<span class="oc-vl-muted">—</span>
				<?php endif; ?>
			</div>
			<div class="oc-vl-col oc-vl-col--status">
				<span class="oc-vl-badge" style="--c:<?php echo esc_attr( $status_color ); ?>">
					<?php echo esc_html( oc_status_label( $status ) ); ?>
				</span>
				<?php
				$cmp_pct   = (int) get_post_meta( $post->ID, '_oc_completion_pct', true );
				if ( ! $cmp_pct ) {
					$cmp_data = oc_profile_completion( $post->ID );
					$cmp_pct  = (int) $cmp_data['percent'];
					$cmp_color = $cmp_data['tier_color'];
					$cmp_tier  = $cmp_data['tier_label'];
				} else {
					[ , $cmp_tier, $cmp_color ] = oc_completion_tier( $cmp_pct );
				}
				?>
				<div class="oc-vl-cmp" title="<?php
					/* translators: 1: tier label, 2: percent */
					echo esc_attr( sprintf( __( '%1$s — %2$d%% complete', 'owambe-connect-core' ), $cmp_tier, $cmp_pct ) );
				?>">
					<div class="oc-vl-cmp__bar"><span style="width:<?php echo (int) $cmp_pct; ?>%; background:<?php echo esc_attr( $cmp_color ); ?>"></span></div>
					<small style="color:<?php echo esc_attr( $cmp_color ); ?>"><?php echo (int) $cmp_pct; ?>%</small>
				</div>
			</div>
			<div class="oc-vl-col oc-vl-col--cats">
				<?php if ( $cats ) : foreach ( $cats as $c ) : ?>
					<span class="oc-vl-tag"><?php echo esc_html( $c->name ); ?></span>
				<?php endforeach; else : ?>
					<span class="oc-vl-muted">—</span>
				<?php endif; ?>
			</div>
			<div class="oc-vl-col oc-vl-col--date">
				<?php
				$date  = strtotime( $post->post_date );
				$diff  = human_time_diff( $date, current_time( 'timestamp' ) );
				printf( '<span title="%s">%s</span>', esc_attr( date_i18n( get_option( 'date_format' ) . ' H:i', $date ) ), esc_html( $diff . ' ' . __( 'ago', 'owambe-connect-core' ) ) );
				?>
			</div>
			<div class="oc-vl-col oc-vl-col--actions">
				<a class="oc-vl-iconbtn" href="<?php echo esc_url( $edit_url ); ?>" title="<?php esc_attr_e( 'Edit', 'owambe-connect-core' ); ?>"><span class="dashicons dashicons-edit"></span></a>
				<?php if ( in_array( $status, [ OC_STATUS_PENDING, OC_STATUS_REJECTED ], true ) ) : ?>
					<a class="oc-vl-iconbtn oc-vl-iconbtn--ok" href="<?php echo esc_url( $approve_url ); ?>" data-oc-confirm="<?php esc_attr_e( 'Approve this vendor and email them?', 'owambe-connect-core' ); ?>" title="<?php esc_attr_e( 'Approve', 'owambe-connect-core' ); ?>"><span class="dashicons dashicons-yes-alt"></span></a>
				<?php endif; ?>
				<?php if ( in_array( $status, [ OC_STATUS_PENDING, OC_STATUS_APPROVED ], true ) ) : ?>
					<a class="oc-vl-iconbtn oc-vl-iconbtn--bad" href="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-oc-reject="<?php echo (int) $post->ID; ?>" data-oc-nonce="<?php echo esc_attr( $reject_nonce ); ?>" title="<?php esc_attr_e( 'Reject…', 'owambe-connect-core' ); ?>"><span class="dashicons dashicons-dismiss"></span></a>
				<?php endif; ?>
				<?php if ( OC_STATUS_APPROVED === $status ) : ?>
					<a class="oc-vl-iconbtn <?php echo $is_featured ? 'oc-vl-iconbtn--gold' : ''; ?>" href="<?php echo esc_url( $feature_url ); ?>" title="<?php echo $is_featured ? esc_attr__( 'Remove featured', 'owambe-connect-core' ) : esc_attr__( 'Feature', 'owambe-connect-core' ); ?>"><span class="dashicons dashicons-star-<?php echo $is_featured ? 'filled' : 'empty'; ?>"></span></a>
				<?php endif; ?>
				<a class="oc-vl-iconbtn <?php echo $is_verified ? 'oc-vl-iconbtn--ok' : ''; ?>" href="<?php echo esc_url( $verify_url ); ?>" title="<?php echo $is_verified ? esc_attr__( 'Remove verified badge', 'owambe-connect-core' ) : esc_attr__( 'Mark as verified', 'owambe-connect-core' ); ?>"><span class="dashicons dashicons-<?php echo $is_verified ? 'shield' : 'shield-alt'; ?>"></span></a>
				<a class="oc-vl-iconbtn" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . OC_CPT . '&page=oc-analytics&vendor=' . (int) $post->ID ) ); ?>" title="<?php esc_attr_e( 'View analytics', 'owambe-connect-core' ); ?>"><span class="dashicons dashicons-chart-bar"></span></a>
				<?php if ( $view_url && OC_STATUS_APPROVED === $status ) : ?>
					<a class="oc-vl-iconbtn" href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'View profile', 'owambe-connect-core' ); ?>"><span class="dashicons dashicons-external"></span></a>
				<?php endif; ?>
				<a class="oc-vl-iconbtn oc-vl-iconbtn--bad" href="<?php echo esc_url( $trash_url ); ?>" data-oc-confirm="<?php esc_attr_e( 'Move this vendor to trash?', 'owambe-connect-core' ); ?>" title="<?php esc_attr_e( 'Trash', 'owambe-connect-core' ); ?>"><span class="dashicons dashicons-trash"></span></a>
			</div>
		</div>
		<?php
	}

	private function render_pagination( $query, $filters, $base ) {
		$pages = (int) $query->max_num_pages;
		if ( $pages <= 1 ) return;
		$paged = max( 1, (int) $filters['paged'] );

		$args = [ 'status' => $filters['status'], 'cat' => $filters['cat'], 'location' => $filters['location'], 's' => $filters['s'] ];
		?>
		<div class="oc-vl-pager">
			<?php
			$prev = $paged > 1 ? add_query_arg( array_merge( $args, [ 'paged' => $paged - 1 ] ), $base ) : '';
			$next = $paged < $pages ? add_query_arg( array_merge( $args, [ 'paged' => $paged + 1 ] ), $base ) : '';
			?>
			<a class="oc-vl-pager__btn <?php echo $prev ? '' : 'is-disabled'; ?>" href="<?php echo esc_url( $prev ?: '#' ); ?>">‹ <?php esc_html_e( 'Previous', 'owambe-connect-core' ); ?></a>
			<span class="oc-vl-pager__pages">
				<?php
				$start = max( 1, $paged - 2 );
				$end   = min( $pages, $paged + 2 );
				if ( $start > 1 ) {
					printf( '<a class="oc-vl-pager__num" href="%s">1</a>', esc_url( add_query_arg( array_merge( $args, [ 'paged' => 1 ] ), $base ) ) );
					if ( $start > 2 ) echo '<span class="oc-vl-pager__gap">…</span>';
				}
				for ( $i = $start; $i <= $end; $i++ ) {
					if ( $i === $paged ) {
						printf( '<span class="oc-vl-pager__num is-active">%d</span>', $i );
					} else {
						printf( '<a class="oc-vl-pager__num" href="%s">%d</a>', esc_url( add_query_arg( array_merge( $args, [ 'paged' => $i ] ), $base ) ), $i );
					}
				}
				if ( $end < $pages ) {
					if ( $end < $pages - 1 ) echo '<span class="oc-vl-pager__gap">…</span>';
					printf( '<a class="oc-vl-pager__num" href="%s">%d</a>', esc_url( add_query_arg( array_merge( $args, [ 'paged' => $pages ] ), $base ) ), $pages );
				}
				?>
			</span>
			<a class="oc-vl-pager__btn <?php echo $next ? '' : 'is-disabled'; ?>" href="<?php echo esc_url( $next ?: '#' ); ?>"><?php esc_html_e( 'Next', 'owambe-connect-core' ); ?> ›</a>
		</div>
		<?php
	}

	private function render_notices() {
		if ( empty( $_GET['oc_admin_msg'] ) ) return;
		$map = [
			'approved'      => [ 'success', __( 'Vendor approved and notified by email.', 'owambe-connect-core' ) ],
			'rejected'      => [ 'warning', __( 'Vendor marked as needing changes and notified.', 'owambe-connect-core' ) ],
			'bulk_approved' => [ 'success', __( 'Selected vendors approved.', 'owambe-connect-core' ) ],
			'bulk_rejected' => [ 'warning', __( 'Selected vendors rejected.', 'owambe-connect-core' ) ],
			'bulk_featured' => [ 'success', __( 'Selected vendors marked as featured.', 'owambe-connect-core' ) ],
			'bulk_unfeat'   => [ 'success', __( 'Featured flag cleared on selected vendors.', 'owambe-connect-core' ) ],
			'bulk_verified' => [ 'success', __( 'Verified badge added to selected vendors.', 'owambe-connect-core' ) ],
			'bulk_unverified' => [ 'success', __( 'Verified badge removed from selected vendors.', 'owambe-connect-core' ) ],
			'bulk_founding' => [ 'success', __( 'Founding vendor badge added.', 'owambe-connect-core' ) ],
			'bulk_unfounding' => [ 'success', __( 'Founding vendor badge removed.', 'owambe-connect-core' ) ],
			'verified'      => [ 'success', __( 'Vendor marked as verified.', 'owambe-connect-core' ) ],
			'unverified'    => [ 'success', __( 'Verified badge removed.', 'owambe-connect-core' ) ],
			'bulk_trashed'  => [ 'success', __( 'Selected vendors moved to trash.', 'owambe-connect-core' ) ],
			'bulk_restored' => [ 'success', __( 'Selected vendors restored.', 'owambe-connect-core' ) ],
			'bulk_deleted'  => [ 'success', __( 'Selected vendors permanently deleted.', 'owambe-connect-core' ) ],
			'featured'      => [ 'success', __( 'Vendor marked as featured.', 'owambe-connect-core' ) ],
			'unfeatured'    => [ 'success', __( 'Vendor removed from featured.', 'owambe-connect-core' ) ],
		];
		$key = sanitize_key( $_GET['oc_admin_msg'] );
		if ( ! isset( $map[ $key ] ) ) return;
		[ $kind, $text ] = $map[ $key ];
		printf(
			'<div class="oc-vl-notice oc-vl-notice--%s">%s</div>',
			esc_attr( $kind ),
			esc_html( $text )
		);
	}

	private function kpi_card( $label, $value, $icon, $color ) {
		?>
		<div class="oc-vl-kpi" style="--c:<?php echo esc_attr( $color ); ?>">
			<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
			<div>
				<strong><?php echo esc_html( number_format_i18n( (int) $value ) ); ?></strong>
				<small><?php echo esc_html( $label ); ?></small>
			</div>
		</div>
		<?php
	}

	// =========================================================================
	//  Filters / queries
	// =========================================================================

	private function parse_filters() {
		$status   = isset( $_GET['status'] )   ? sanitize_key( $_GET['status'] ) : 'all';
		$allowed  = [ 'all', OC_STATUS_APPROVED, OC_STATUS_PENDING, OC_STATUS_REJECTED, 'featured', 'trash' ];
		if ( ! in_array( $status, $allowed, true ) ) $status = 'all';

		return [
			'status'   => $status,
			'cat'      => isset( $_GET['cat'] )      ? sanitize_title( $_GET['cat'] ) : '',
			'location' => isset( $_GET['location'] ) ? sanitize_text_field( wp_unslash( $_GET['location'] ) ) : '',
			's'        => isset( $_GET['s'] )        ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'paged'    => isset( $_GET['paged'] )    ? max( 1, (int) $_GET['paged'] ) : 1,
		];
	}

	private function run_query( $f ) {
		$args = [
			'post_type'      => OC_CPT,
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $f['paged'],
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( 'all' === $f['status'] ) {
			$args['post_status'] = [ OC_STATUS_APPROVED, OC_STATUS_PENDING, OC_STATUS_REJECTED ];
		} elseif ( 'featured' === $f['status'] ) {
			$args['post_status'] = OC_STATUS_APPROVED;
			$args['meta_query']  = [ [ 'key' => '_oc_featured', 'value' => '1' ] ];
		} elseif ( 'trash' === $f['status'] ) {
			$args['post_status'] = 'trash';
		} else {
			$args['post_status'] = $f['status'];
		}

		if ( $f['s'] )   $args['s'] = $f['s'];
		if ( $f['cat'] ) {
			$args['tax_query'] = [ [ 'taxonomy' => OC_TAX, 'field' => 'slug', 'terms' => $f['cat'] ] ];
		}
		if ( $f['location'] ) {
			$args['meta_query']   = isset( $args['meta_query'] ) ? $args['meta_query'] : [];
			$args['meta_query'][] = [ 'key' => '_oc_location', 'value' => $f['location'], 'compare' => 'LIKE' ];
		}

		return new WP_Query( $args );
	}

	private function kpis() {
		$counts = wp_count_posts( OC_CPT );
		global $wpdb;
		$featured = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_oc_featured' AND pm.meta_value = '1'
			 WHERE p.post_type = %s AND p.post_status = %s",
			OC_CPT, OC_STATUS_APPROVED
		) );

		$pending  = isset( $counts->{OC_STATUS_PENDING} )  ? (int) $counts->{OC_STATUS_PENDING}  : 0;
		$live     = isset( $counts->{OC_STATUS_APPROVED} ) ? (int) $counts->{OC_STATUS_APPROVED} : 0;
		$rejected = isset( $counts->{OC_STATUS_REJECTED} ) ? (int) $counts->{OC_STATUS_REJECTED} : 0;
		$trash    = isset( $counts->trash )                ? (int) $counts->trash                : 0;

		return [
			'total'    => $live + $pending + $rejected,
			'live'     => $live,
			'pending'  => $pending,
			'rejected' => $rejected,
			'featured' => $featured,
			'trash'    => $trash,
		];
	}

	// =========================================================================
	//  Bulk action handler
	// =========================================================================

	public function handle_bulk() {
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( -1 );
		check_admin_referer( self::BULK_ACTION, 'oc_vl_nonce' );

		$action = isset( $_POST['bulk_action'] ) ? sanitize_key( $_POST['bulk_action'] ) : '';
		$ids    = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', $_POST['ids'] ) : [];
		$back   = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : admin_url( 'admin.php?page=' . self::PAGE );

		if ( ! $action || ! $ids ) { wp_safe_redirect( $back ); exit; }

		$msg = '';
		switch ( $action ) {
			case 'approve':
				foreach ( $ids as $id ) {
					if ( ! current_user_can( 'edit_post', $id ) ) continue;
					wp_update_post( [ 'ID' => $id, 'post_status' => OC_STATUS_APPROVED ] );
					delete_post_meta( $id, '_oc_rejection_note' );
					if ( class_exists( 'OC_Mail' ) ) OC_Mail::vendor_approved( $id );
					do_action( 'oc_after_vendor_approved', $id );
				}
				$msg = 'bulk_approved'; break;

			case 'reject':
				foreach ( $ids as $id ) {
					if ( ! current_user_can( 'edit_post', $id ) ) continue;
					wp_update_post( [ 'ID' => $id, 'post_status' => OC_STATUS_REJECTED ] );
					update_post_meta( $id, '_oc_rejection_note', '' );
					if ( class_exists( 'OC_Mail' ) ) OC_Mail::vendor_rejected( $id, '' );
					do_action( 'oc_after_vendor_rejected', $id, '' );
				}
				$msg = 'bulk_rejected'; break;

			case 'feature':
				foreach ( $ids as $id ) {
					if ( ! current_user_can( 'edit_post', $id ) ) continue;
					update_post_meta( $id, '_oc_featured', 1 );
				}
				$msg = 'bulk_featured'; break;

			case 'unfeature':
				foreach ( $ids as $id ) {
					if ( ! current_user_can( 'edit_post', $id ) ) continue;
					update_post_meta( $id, '_oc_featured', 0 );
				}
				$msg = 'bulk_unfeat'; break;

			case 'verify':
				foreach ( $ids as $id ) {
					if ( ! current_user_can( 'edit_post', $id ) ) continue;
					update_post_meta( $id, '_oc_verified', 1 );
				}
				$msg = 'bulk_verified'; break;

			case 'unverify':
				foreach ( $ids as $id ) {
					if ( ! current_user_can( 'edit_post', $id ) ) continue;
					update_post_meta( $id, '_oc_verified', 0 );
				}
				$msg = 'bulk_unverified'; break;

			case 'founding':
				foreach ( $ids as $id ) {
					if ( ! current_user_can( 'edit_post', $id ) ) continue;
					update_post_meta( $id, '_oc_founding_vendor', 1 );
				}
				$msg = 'bulk_founding'; break;

			case 'unfounding':
				foreach ( $ids as $id ) {
					if ( ! current_user_can( 'edit_post', $id ) ) continue;
					update_post_meta( $id, '_oc_founding_vendor', 0 );
				}
				$msg = 'bulk_unfounding'; break;

			case 'trash':
				foreach ( $ids as $id ) {
					if ( current_user_can( 'delete_post', $id ) ) wp_trash_post( $id );
				}
				$msg = 'bulk_trashed'; break;

			case 'restore':
				foreach ( $ids as $id ) {
					if ( current_user_can( 'edit_post', $id ) ) wp_untrash_post( $id );
				}
				$msg = 'bulk_restored'; break;

			case 'delete':
				foreach ( $ids as $id ) {
					if ( ! current_user_can( 'delete_post', $id ) ) continue;
					self::delete_vendor_completely( $id );
				}
				$msg = 'bulk_deleted'; break;
		}

		wp_safe_redirect( add_query_arg( 'oc_admin_msg', $msg, $back ) );
		exit;
	}

	/**
	 * Permanently delete a vendor — both the CPT post AND the underlying
	 * WordPress user, provided the user is a pure vendor (no other content,
	 * not an admin). Used by the bulk-delete action and the import-batch
	 * cleanup button. Safe to call repeatedly.
	 *
	 * @param int $post_id Vendor CPT post ID.
	 * @return bool true if the post was deleted (user removal is best-effort).
	 */
	public static function delete_vendor_completely( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post || OC_CPT !== $post->post_type ) {
			return false;
		}
		$user_id = (int) $post->post_author;

		// Delete the post (force = true, skip trash).
		$deleted = wp_delete_post( $post_id, true );
		if ( ! $deleted ) {
			return false;
		}

		// Remove the user only if it's safe to do so:
		//  - the account exists,
		//  - it's not an administrator / editor,
		//  - it has the OC vendor role,
		//  - and it has no other posts of any type left.
		if ( ! $user_id ) {
			return true;
		}
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return true;
		}
		if ( user_can( $user, 'manage_options' ) || user_can( $user, 'edit_others_posts' ) ) {
			return true; // admin / editor — never delete
		}
		if ( ! in_array( OC_ROLE, (array) $user->roles, true ) ) {
			return true; // not one of "our" vendors — leave it alone
		}
		// Verify they have no remaining content owned by them.
		$remaining = count_user_posts( $user_id, 'any', true );
		if ( $remaining > 0 ) {
			return true; // still owns something — keep the user account
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );
		return true;
	}

	// =========================================================================
	//  Styles (inline so the page is fully self-contained)
	// =========================================================================

	private function print_styles() {
		?>
		<style>
			/* Page chrome */
			.oc-vl { max-width:1400px; }
			.oc-vl-head { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; margin:8px 0 18px; }
			.oc-vl-head h1 { font-family:Georgia, serif; color:#1F1B1A; margin:0; font-size:1.8rem; }
			.oc-vl-sub { margin:4px 0 0; color:#6B6361; font-size:13px; }
			.oc-vl-add { display:inline-flex; align-items:center; gap:6px; background:#6E0F2C; color:#fff; padding:10px 18px; border-radius:6px; text-decoration:none; font-weight:600; }
			.oc-vl-add:hover, .oc-vl-add:focus { background:#4A0A1E; color:#fff; box-shadow:0 0 0 3px #C9A961; }
			.oc-vl-add .dashicons { font-size:16px; width:16px; height:16px; }

			/* Notices */
			.oc-vl-notice { padding:12px 16px; border-radius:6px; margin:0 0 16px; border-left:4px solid; font-size:13px; }
			.oc-vl-notice--success { background:#E9F5EF; border-color:#2E7D5B; color:#1F4D3A; }
			.oc-vl-notice--warning { background:#FAF1DE; border-color:#B8860B; color:#4A3700; }

			/* KPIs */
			.oc-vl-kpis { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin:0 0 18px; }
			@media (min-width:900px)  { .oc-vl-kpis { grid-template-columns:repeat(5,1fr); } }
			.oc-vl-kpi { background:#fff; border:1px solid #E4DDD2; border-left:3px solid var(--c); border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px; }
			.oc-vl-kpi .dashicons { color:var(--c); opacity:.5; font-size:28px; width:28px; height:28px; }
			.oc-vl-kpi strong { display:block; font-family:Georgia, serif; font-size:1.6rem; line-height:1; color:var(--c); }
			.oc-vl-kpi small { display:block; font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#6B6361; font-weight:600; margin-top:4px; }

			/* Status pills */
			.oc-vl-pills { display:flex; flex-wrap:wrap; gap:6px; padding:10px 0; margin:0 0 14px; border-bottom:1px solid #E4DDD2; }
			.oc-vl-pill { display:inline-flex; align-items:center; gap:8px; padding:7px 14px; background:#FAF7F2; border:1px solid #E4DDD2; border-radius:999px; color:#6B6361; text-decoration:none; font-weight:500; font-size:13px; }
			.oc-vl-pill:hover { border-color:#6E0F2C; color:#6E0F2C; }
			.oc-vl-pill.is-active { background:#6E0F2C; border-color:#6E0F2C; color:#fff; }
			.oc-vl-pill__count { background:rgba(0,0,0,.08); border-radius:999px; padding:1px 8px; font-size:11px; font-weight:600; }
			.oc-vl-pill.is-active .oc-vl-pill__count { background:#C9A961; color:#1F1B1A; }

			/* Filter bar */
			.oc-vl-filters { background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:12px; margin:0 0 14px; display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
			.oc-vl-filter { padding:8px 12px; border:1px solid #ccd0d4; border-radius:4px; font-size:13px; background:#fff; min-width:160px; }
			.oc-vl-filter--search { display:flex; align-items:center; gap:6px; padding:0; border:1px solid #ccd0d4; flex:1; min-width:240px; }
			.oc-vl-filter--search .dashicons { color:#6B6361; padding-left:10px; }
			.oc-vl-filter--search input { border:0; outline:0; padding:8px 12px 8px 6px; flex:1; font-size:13px; background:transparent; }

			.oc-vl-btn { display:inline-flex; align-items:center; padding:8px 16px; border:1px solid #E4DDD2; background:#fff; color:#6B6361; border-radius:4px; text-decoration:none; cursor:pointer; font-size:13px; font-weight:500; }
			.oc-vl-btn:hover { border-color:#6E0F2C; color:#6E0F2C; }
			.oc-vl-btn--primary { background:#6E0F2C; border-color:#6E0F2C; color:#fff; }
			.oc-vl-btn--primary:hover { background:#4A0A1E; color:#fff; }

			/* Bulk + meta bar */
			.oc-vl-bar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; padding:8px 4px; }
			.oc-vl-bulk { display:flex; gap:6px; }
			.oc-vl-bulk select { padding:7px 10px; border:1px solid #ccd0d4; border-radius:4px; font-size:13px; }
			.oc-vl-meta { color:#6B6361; font-size:13px; }
			.oc-vl-meta strong { color:#1F1B1A; }

			/* Table */
			.oc-vl-table { background:#fff; border:1px solid #E4DDD2; border-radius:8px; overflow:hidden; }
			.oc-vl-row {
				display:grid;
				/* check | vendor (flex) | location | status | categories (flex) | date | actions */
				grid-template-columns: 32px minmax(0, 2.6fr) 150px 130px minmax(0, 1.6fr) 110px 200px;
				gap:14px;
				padding:14px 16px;
				align-items:center;
				border-bottom:1px solid #EFEAE2;
			}
			.oc-vl-row > .oc-vl-col { min-width:0; } /* prevent flex/grid blow-out */
			.oc-vl-row:last-child { border-bottom:0; }
			.oc-vl-row:hover { background:#FAF7F2; }
			.oc-vl-row--head { background:#FAF7F2; font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#6B6361; font-weight:600; padding:10px 16px; }
			.oc-vl-row--head:hover { background:#FAF7F2; }
			.oc-vl-col--check input { margin:0; }
			.oc-vl-col--cats { display:flex; flex-wrap:wrap; align-content:center; gap:4px; }

			/* Vendor cell */
			.oc-vl-vendor { display:flex; align-items:center; gap:12px; min-width:0; }
			.oc-vl-logo { position:relative; width:48px; height:48px; flex:0 0 48px; border-radius:8px; overflow:hidden; background:#F4EFE6; border:1px solid #E4DDD2; display:flex; align-items:center; justify-content:center; }
			.oc-vl-logo__img { width:100%; height:100%; object-fit:cover; }
			.oc-vl-logo__fallback { font-family:Georgia, serif; font-size:1.4rem; color:#6E0F2C; font-weight:600; }
			.oc-vl-logo__star { position:absolute; top:-4px; right:-4px; background:#C9A961; color:#1F1B1A; width:18px; height:18px; border-radius:50%; font-size:11px; line-height:18px; text-align:center; box-shadow:0 1px 2px rgba(0,0,0,.18); }
			.oc-vl-vendor__info { min-width:0; flex:1; }
			.oc-vl-vendor__name { display:block; color:#6E0F2C; text-decoration:none; font-weight:600; font-size:14px; line-height:1.3; }
			.oc-vl-vendor__name:hover { color:#4A0A1E; text-decoration:underline; }
			.oc-vl-vendor__bio { margin:3px 0 0; font-size:12px; color:#6B6361; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

			.oc-vl-loc { display:inline-flex; align-items:center; gap:4px; color:#1F1B1A; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
			.oc-vl-loc .dashicons { color:#A8893D; font-size:14px; width:14px; height:14px; flex:0 0 14px; }
			.oc-vl-muted { color:#999; }

			.oc-vl-badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:600; color:var(--c); background:color-mix(in srgb, var(--c) 12%, white); border:1px solid color-mix(in srgb, var(--c) 25%, white); white-space:nowrap; }

			/* Completion mini-bar in status cell */
			.oc-vl-cmp { display:flex; align-items:center; gap:6px; margin-top:6px; }
			.oc-vl-cmp__bar { flex:1; min-width:60px; max-width:100px; height:5px; background:#EFEAE2; border-radius:999px; overflow:hidden; }
			.oc-vl-cmp__bar span { display:block; height:100%; border-radius:999px; transition:width .4s ease; }
			.oc-vl-cmp small { font-size:10px; font-weight:700; }

			.oc-vl-tag { display:inline-block; background:#FAF7F2; border:1px solid #E4DDD2; color:#6E0F2C; padding:3px 9px; border-radius:4px; font-size:11px; font-weight:500; white-space:nowrap; }

			.oc-vl-col--date { color:#6B6361; font-size:12px; white-space:nowrap; }
			.oc-vl-col--actions { display:flex; gap:4px; justify-content:flex-end; }

			.oc-vl-iconbtn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:6px; background:#FAF7F2; border:1px solid #E4DDD2; color:#6B6361; text-decoration:none; transition:all .15s; }
			.oc-vl-iconbtn:hover { background:#6E0F2C; border-color:#6E0F2C; color:#fff; }
			.oc-vl-iconbtn .dashicons { font-size:16px; width:16px; height:16px; line-height:1; }
			.oc-vl-iconbtn--ok:hover { background:#2E7D5B; border-color:#2E7D5B; }
			.oc-vl-iconbtn--bad:hover { background:#B0354F; border-color:#B0354F; }
			.oc-vl-iconbtn--gold { background:#FAF1DE; border-color:#C9A961; color:#A8893D; }

			/* Empty state */
			.oc-vl-empty { background:#fff; border:1px dashed #E4DDD2; border-radius:8px; padding:48px 24px; text-align:center; }
			.oc-vl-empty .dashicons { font-size:36px; width:36px; height:36px; color:#C9A961; }
			.oc-vl-empty h3 { font-family:Georgia, serif; color:#1F1B1A; margin:14px 0 4px; }
			.oc-vl-empty p { color:#6B6361; margin:0 0 14px; }

			/* Pagination */
			.oc-vl-pager { display:flex; justify-content:center; gap:6px; padding:18px 0; align-items:center; }
			.oc-vl-pager__btn { padding:7px 14px; border:1px solid #E4DDD2; background:#fff; border-radius:4px; text-decoration:none; color:#6B6361; font-size:13px; font-weight:500; }
			.oc-vl-pager__btn:hover { border-color:#6E0F2C; color:#6E0F2C; }
			.oc-vl-pager__btn.is-disabled { opacity:.4; pointer-events:none; }
			.oc-vl-pager__pages { display:flex; gap:4px; margin:0 6px; }
			.oc-vl-pager__num { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:34px; padding:0 8px; border:1px solid #E4DDD2; background:#fff; border-radius:4px; text-decoration:none; color:#6B6361; font-size:13px; }
			.oc-vl-pager__num:hover { border-color:#6E0F2C; color:#6E0F2C; }
			.oc-vl-pager__num.is-active { background:#6E0F2C; border-color:#6E0F2C; color:#fff; font-weight:600; }
			.oc-vl-pager__gap { color:#999; padding:0 4px; }

			/* Medium screens — drop categories column, it can wrap below */
			@media (max-width:1280px) {
				.oc-vl-row { grid-template-columns: 32px minmax(0, 2.4fr) 130px 130px 100px 180px; }
				.oc-vl-col--cats     { grid-column: 2 / 7; padding:6px 0 0; }
				.oc-vl-row--head .oc-vl-col--cats { display:none; }
			}
			/* Narrow screens — stack actions to a new row */
			@media (max-width:980px) {
				.oc-vl-row { grid-template-columns: 32px minmax(0, 1.8fr) 1fr 130px; }
				.oc-vl-col--date    { grid-column: 2 / -1; padding-top:4px; }
				.oc-vl-col--actions { grid-column: 1 / -1; justify-content:flex-start; padding-top:8px; border-top:1px dashed #EFEAE2; margin-top:6px; }
				.oc-vl-row--head .oc-vl-col--date { display:none; }
			}
		</style>
		<?php
	}
}
