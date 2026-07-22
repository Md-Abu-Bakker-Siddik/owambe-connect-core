<?php
/**
 * Owambe Connect — Analytics dashboard.
 * KPIs, time-series, category/location/price breakdowns, recent activity.
 * Filters: date range (preset or custom) + category.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Analytics {

	const PAGE = 'oc-analytics';

	/** @var array{from:string,to:string,range:string,cat:string} */
	private $filters = [];

	public function register() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Marketplace Analytics', 'owambe-connect-core' ),
			__( 'Analytics', 'owambe-connect-core' ),
			'edit_posts',
			self::PAGE,
			[ $this, 'render' ],
			7
		);
	}

	private function parse_filters() {
		$range = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : '30d';
		$cat   = isset( $_GET['cat'] )   ? sanitize_title( $_GET['cat'] ) : '';

		$now = current_time( 'timestamp' );
		switch ( $range ) {
			case '7d':    $from_ts = strtotime( '-7 days',   $now ); $to_ts = $now; break;
			case '90d':   $from_ts = strtotime( '-90 days',  $now ); $to_ts = $now; break;
			case '12m':   $from_ts = strtotime( '-12 months',$now ); $to_ts = $now; break;
			case 'all':   $from_ts = strtotime( '-10 years', $now ); $to_ts = $now; break;
			case 'custom':
				$from_ts = isset( $_GET['from'] ) ? strtotime( sanitize_text_field( $_GET['from'] ) ) : strtotime( '-30 days', $now );
				$to_ts   = isset( $_GET['to'] )   ? strtotime( sanitize_text_field( $_GET['to'] ) . ' 23:59:59' ) : $now;
				if ( ! $from_ts ) $from_ts = strtotime( '-30 days', $now );
				if ( ! $to_ts )   $to_ts   = $now;
				break;
			case '30d':
			default:
				$range   = '30d';
				$from_ts = strtotime( '-30 days', $now );
				$to_ts   = $now;
		}
		$this->filters = [
			'from'  => date( 'Y-m-d 00:00:00', $from_ts ),
			'to'    => date( 'Y-m-d 23:59:59', $to_ts ),
			'range' => $range,
			'cat'   => $cat,
		];
	}

	public function render() {
		if ( ! current_user_can( 'edit_posts' ) ) return;
		$this->parse_filters();

		// Per-vendor drill-down: &vendor=<post ID> switches the whole page to a
		// single vendor's analytics (views/clicks over the same date filters).
		$vendor_id = isset( $_GET['vendor'] ) ? absint( $_GET['vendor'] ) : 0;
		if ( $vendor_id ) {
			$vendor_post = get_post( $vendor_id );
			if ( $vendor_post && OC_CPT === $vendor_post->post_type ) {
				$this->render_vendor( $vendor_post );
				return;
			}
		}

		$kpis        = $this->kpis();
		$timeseries  = $this->applications_timeseries();
		$categories  = $this->vendors_by_category();
		$locations   = $this->top_locations();
		$prices      = $this->price_distribution();
		$recent      = $this->recent_vendors( 8 );
		$cats_filter = OC_Queries::categories_with_counts();

		$ts_has_data = false;
		foreach ( $timeseries as $row ) {
			if ( $row['pending'] || $row['approved'] || $row['rejected'] ) {
				$ts_has_data = true;
				break;
			}
		}

		$base_url = admin_url( 'edit.php?post_type=' . OC_CPT . '&page=' . self::PAGE );

		// Drill-down metric for the tracking KPI cards. Clicking "Profile views"
		// or "Contact clicks" reloads with ?metric= and jumps to the per-vendor
		// breakdown table below, which re-ranks itself by the chosen metric.
		$active_metric = isset( $_GET['metric'] ) && in_array( $_GET['metric'], [ 'views', 'clicks' ], true ) ? sanitize_key( $_GET['metric'] ) : '';
		$sort_metric   = $active_metric ?: 'views';

		// Carry the current date/category filters onto the drill links so the
		// breakdown honours the same window the cards were counted over.
		$kpi_filter_args = [ 'range' => $this->filters['range'], 'cat' => $this->filters['cat'] ];
		if ( 'custom' === $this->filters['range'] ) {
			$kpi_filter_args['from'] = substr( $this->filters['from'], 0, 10 );
			$kpi_filter_args['to']   = substr( $this->filters['to'], 0, 10 );
		}
		$views_drill_url  = add_query_arg( array_merge( $kpi_filter_args, [ 'metric' => 'views' ] ),  $base_url ) . '#oc-an-vendor-breakdown';
		$clicks_drill_url = add_query_arg( array_merge( $kpi_filter_args, [ 'metric' => 'clicks' ] ), $base_url ) . '#oc-an-vendor-breakdown';
		?>
		<div class="wrap oc-an">
			<h1 style="margin-bottom:6px;display:flex;align-items:center;gap:10px">
				<span class="dashicons dashicons-chart-line" style="color:#6E0F2C;font-size:28px"></span>
				<?php esc_html_e( 'Marketplace Analytics', 'owambe-connect-core' ); ?>
			</h1>
			<p style="margin:0 0 18px;color:#555">
				<?php
				printf(
					/* translators: 1: from date, 2: to date */
					esc_html__( 'Showing data from %1$s to %2$s', 'owambe-connect-core' ),
					'<strong>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $this->filters['from'] ) ) ) . '</strong>',
					'<strong>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $this->filters['to'] ) ) ) . '</strong>'
				);
				?>
			</p>

			<!-- ============== Filter bar ============== -->
			<form method="get" class="oc-an-filters">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( OC_CPT ); ?>" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />

				<div class="oc-an-presets">
					<?php
					$presets = [
						'7d'  => __( 'Last 7 days',   'owambe-connect-core' ),
						'30d' => __( 'Last 30 days',  'owambe-connect-core' ),
						'90d' => __( 'Last 90 days',  'owambe-connect-core' ),
						'12m' => __( 'Last 12 months','owambe-connect-core' ),
						'all' => __( 'All time',      'owambe-connect-core' ),
					];
					foreach ( $presets as $key => $label ) :
						$url = add_query_arg( [ 'range' => $key, 'cat' => $this->filters['cat'] ], $base_url );
						?>
						<a class="oc-an-preset <?php echo $this->filters['range'] === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</div>

				<div class="oc-an-controls">
					<label><?php esc_html_e( 'From', 'owambe-connect-core' ); ?>
						<input type="date" name="from" value="<?php echo esc_attr( substr( $this->filters['from'], 0, 10 ) ); ?>" />
					</label>
					<label><?php esc_html_e( 'To', 'owambe-connect-core' ); ?>
						<input type="date" name="to" value="<?php echo esc_attr( substr( $this->filters['to'], 0, 10 ) ); ?>" />
					</label>
					<input type="hidden" name="range" value="custom" />

					<label><?php esc_html_e( 'Category', 'owambe-connect-core' ); ?>
						<select name="cat">
							<option value=""><?php esc_html_e( 'All categories', 'owambe-connect-core' ); ?></option>
							<?php foreach ( $cats_filter as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $this->filters['cat'], $term->slug ); ?>>
									<?php echo esc_html( $term->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>

					<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'owambe-connect-core' ); ?></button>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Reset', 'owambe-connect-core' ); ?></a>

					<label style="margin-left:auto"><?php esc_html_e( 'Vendor analytics', 'owambe-connect-core' ); ?>
						<input type="text" id="oc-vendor-search" list="oc-vendor-list"
							placeholder="<?php esc_attr_e( 'Pick a vendor…', 'owambe-connect-core' ); ?>"
							autocomplete="off" />
						<input type="hidden" name="vendor" id="oc-vendor-input" value="" />
						<datalist id="oc-vendor-list">
							<?php foreach ( $this->vendor_choices() as $vc ) : ?>
								<option data-id="<?php echo (int) $vc->ID; ?>" value="<?php echo esc_attr( $vc->post_title ); ?>"></option>
							<?php endforeach; ?>
						</datalist>
					</label>
					<script>
					( function () {
						var search = document.getElementById( 'oc-vendor-search' ),
							hidden = document.getElementById( 'oc-vendor-input' ),
							list   = document.getElementById( 'oc-vendor-list' );
						if ( ! search || ! hidden || ! list ) { return; }
						search.addEventListener( 'input', function () {
							var val  = search.value.trim(),
								opts = list.options,
								i;
							for ( i = 0; i < opts.length; i++ ) {
								if ( opts[ i ].value === val ) {
									hidden.value = opts[ i ].getAttribute( 'data-id' );
									search.form.submit();
									return;
								}
							}
							hidden.value = '';
						} );
					} )();
					</script>
				</div>
			</form>

			<!-- ============== KPI cards ============== -->
			<div class="oc-an-kpis">
				<?php $this->kpi_card( __( 'Live vendors',          'owambe-connect-core' ), $kpis['live'],            'chart-bar',     '#2E7D5B' ); ?>
				<?php $this->kpi_card( __( 'Pending review',        'owambe-connect-core' ), $kpis['pending'],         'clock',         '#B8860B' ); ?>
				<?php $this->kpi_card( __( 'Featured',              'owambe-connect-core' ), $kpis['featured'],        'star-filled',   '#A8893D' ); ?>
				<?php $this->kpi_card( __( 'Approval rate',         'owambe-connect-core' ), $kpis['approval_rate_label'], 'yes-alt',  '#6E0F2C' ); ?>
				<?php $this->kpi_card( __( 'Applications in period','owambe-connect-core' ), $kpis['applications'],    'edit-page',     '#6E0F2C' ); ?>
				<?php $this->kpi_card( __( 'Approved in period',    'owambe-connect-core' ), $kpis['approved_period'], 'thumbs-up',     '#2E7D5B' ); ?>
				<?php
				// Phase 2 — profile views + contact clicks from the oc_vendor_stats
				// table (the first persistent metric store; everything above is
				// computed live from wp_posts). Uses the filter's REAL [from,to]
				// window so custom/past ranges report correctly (not "ending today").
				$trk_from   = substr( (string) $this->filters['from'], 0, 10 );
				$trk_to     = substr( (string) $this->filters['to'], 0, 10 );
				$trk_totals = class_exists( 'OC_Tracking' ) ? OC_Tracking::totals_range( $trk_from, $trk_to ) : [ 'views' => 0, 'clicks' => 0 ];
				?>
				<?php $this->kpi_card( __( 'Profile views (period)',  'owambe-connect-core' ), number_format_i18n( (int) $trk_totals['views'] ),  'visibility', '#2E7D5B', $views_drill_url,  'views' === $active_metric ); ?>
				<?php $this->kpi_card( __( 'Contact clicks (period)', 'owambe-connect-core' ), number_format_i18n( (int) $trk_totals['clicks'] ), 'phone',      '#A8893D', $clicks_drill_url, 'clicks' === $active_metric ); ?>
			</div>

			<!-- ============== Charts row 1 ============== -->
			<div class="oc-an-row">
				<div class="oc-an-card oc-an-card--wide">
					<h3><?php esc_html_e( 'Applications over time', 'owambe-connect-core' ); ?></h3>
					<?php if ( $ts_has_data ) : ?>
						<div class="oc-an-chart oc-an-chart--tall">
							<canvas id="oc-chart-timeseries"></canvas>
						</div>
					<?php else : ?>
						<p class="oc-an-empty"><?php esc_html_e( 'No applications in this date range.', 'owambe-connect-core' ); ?></p>
					<?php endif; ?>
				</div>
				<div class="oc-an-card">
					<h3><?php esc_html_e( 'Vendors by category', 'owambe-connect-core' ); ?></h3>
					<?php if ( ! empty( $categories ) ) : ?>
						<div class="oc-an-chart">
							<canvas id="oc-chart-categories"></canvas>
						</div>
					<?php else : ?>
						<p class="oc-an-empty"><?php esc_html_e( 'No approved vendors yet.', 'owambe-connect-core' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- ============== Phase 2: views & clicks ============== -->
			<?php
			$trk_series  = class_exists( 'OC_Tracking' ) ? OC_Tracking::timeseries_range( $trk_from, $trk_to ) : [];
			// Over-fetch, then rank by whichever metric the KPI drill-down selected
			// (views by default, clicks when the "Contact clicks" card was clicked).
			$top_vendors = class_exists( 'OC_Tracking' ) ? OC_Tracking::top_vendors_range( $trk_from, $trk_to, 50 ) : [];
			usort( $top_vendors, static function ( $a, $b ) use ( $sort_metric ) {
				$other = 'views' === $sort_metric ? 'clicks' : 'views';
				return $a[ $sort_metric ] === $b[ $sort_metric ]
					? $b[ $other ] <=> $a[ $other ]
					: $b[ $sort_metric ] <=> $a[ $sort_metric ];
			} );
			$top_vendors = array_slice( $top_vendors, 0, 8 );
			?>
			<div class="oc-an-row">
				<div class="oc-an-card oc-an-card--wide">
					<h3><?php esc_html_e( 'Profile views & contact clicks', 'owambe-connect-core' ); ?></h3>
					<?php if ( (int) $trk_totals['views'] + (int) $trk_totals['clicks'] > 0 ) : ?>
						<div class="oc-an-chart oc-an-chart--tall">
							<canvas id="oc-chart-tracking"></canvas>
						</div>
					<?php else : ?>
						<p class="oc-an-empty"><?php esc_html_e( 'No views or clicks recorded in this period yet.', 'owambe-connect-core' ); ?></p>
					<?php endif; ?>
				</div>

				<?php
				$is_clicks_rank = 'clicks' === $sort_metric;
				$rank_title     = $is_clicks_rank ? __( 'Most-contacted vendors', 'owambe-connect-core' ) : __( 'Most-viewed vendors', 'owambe-connect-core' );
				$rank_col       = $is_clicks_rank ? __( 'Clicks', 'owambe-connect-core' ) : __( 'Views', 'owambe-connect-core' );
				?>
				<div class="oc-an-card" id="oc-an-vendor-breakdown">
					<h3 style="display:flex;align-items:center;gap:8px;justify-content:space-between">
						<span><?php echo esc_html( $rank_title ); ?></span>
						<?php if ( ! empty( $top_vendors ) ) : ?>
							<span style="font-size:11px;font-weight:600;color:#6B6361;text-transform:uppercase;letter-spacing:.06em"><?php echo esc_html( $rank_col ); ?></span>
						<?php endif; ?>
					</h3>
					<?php if ( ! empty( $top_vendors ) ) : ?>
						<table class="oc-an-bars oc-an-top">
							<?php
							$tv_max = max( array_column( $top_vendors, $sort_metric ) ) ?: 1;
							foreach ( $top_vendors as $tv ) :
								$primary = (int) $tv[ $sort_metric ];
								$pct     = round( ( $primary / $tv_max ) * 100 );
								$vurl    = add_query_arg( 'vendor', $tv['vendor_id'], $base_url );
								$hint    = $is_clicks_rank
									/* translators: %s: number of profile views */
									? sprintf( _n( '%s profile view', '%s profile views', $tv['views'], 'owambe-connect-core' ), number_format_i18n( $tv['views'] ) )
									/* translators: %s: number of contact clicks */
									: sprintf( _n( '%s contact click', '%s contact clicks', $tv['clicks'], 'owambe-connect-core' ), number_format_i18n( $tv['clicks'] ) );
								?>
								<tr>
									<td class="oc-an-bars__label">
										<a href="<?php echo esc_url( $vurl ); ?>" title="<?php echo esc_attr( $hint ); ?>"><?php echo esc_html( $tv['title'] ); ?></a>
									</td>
									<td class="oc-an-bars__bar"><span style="width:<?php echo esc_attr( $pct ); ?>%"></span></td>
									<td class="oc-an-bars__val"><?php echo esc_html( number_format_i18n( $primary ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</table>
					<?php else : ?>
						<p class="oc-an-empty"><?php echo esc_html( $is_clicks_rank ? __( 'No vendor contact clicks recorded in this period yet.', 'owambe-connect-core' ) : __( 'No vendor views recorded in this period yet.', 'owambe-connect-core' ) ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- ============== Phase 2: clicks by contact method ============== -->
			<?php
			$channel_labels = class_exists( 'OC_Tracking' ) ? OC_Tracking::click_channels() : [];
			$channel_totals = class_exists( 'OC_Tracking' ) ? OC_Tracking::channel_totals_range( $trk_from, $trk_to ) : [];
			$channel_colors = [
				'click_whatsapp'  => '#2E7D5B',
				'click_email'     => '#6E0F2C',
				'click_instagram' => '#B0354F',
				'click_facebook'  => '#1877F2',
				'click_website'   => '#A8893D',
			];
			$channel_sum = array_sum( $channel_totals );
			?>
			<div class="oc-an-card">
				<h3><?php esc_html_e( 'Clicks by contact method', 'owambe-connect-core' ); ?></h3>
				<?php if ( $channel_sum > 0 ) : ?>
					<table class="oc-an-bars">
						<?php
						$cmax = max( $channel_totals ) ?: 1;
						foreach ( $channel_labels as $metric => $label ) :
							$val   = (int) ( $channel_totals[ $metric ] ?? 0 );
							$pct   = round( ( $val / $cmax ) * 100 );
							$color = $channel_colors[ $metric ] ?? '#6E0F2C';
							?>
							<tr>
								<td class="oc-an-bars__label"><?php echo esc_html( $label ); ?></td>
								<td class="oc-an-bars__bar"><span style="width:<?php echo esc_attr( $pct ); ?>%;background:<?php echo esc_attr( $color ); ?>"></span></td>
								<td class="oc-an-bars__val"><?php echo esc_html( number_format_i18n( $val ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php else : ?>
					<p class="oc-an-empty"><?php esc_html_e( 'No contact clicks recorded in this period yet.', 'owambe-connect-core' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- ============== Search & discovery ============== -->
			<h2 style="font-family:Georgia, serif;color:#1F1B1A;font-size:1.2rem;margin:26px 0 4px"><?php esc_html_e( 'Search & discovery', 'owambe-connect-core' ); ?></h2>
			<p style="margin:0 0 14px;color:#6B6361;font-size:12px"><?php esc_html_e( 'All-time totals from the vendor directory — not affected by the date filter above.', 'owambe-connect-core' ); ?></p>
			<div class="oc-an-row">
				<?php
				$this->search_bars(
					__( 'Top searched keywords', 'owambe-connect-core' ),
					OC_Queries::top_keywords( 12 ),
					'term',
					__( 'No keyword searches recorded yet.', 'owambe-connect-core' )
				);
				$this->search_bars(
					__( 'Empty searches (0 results)', 'owambe-connect-core' ),
					OC_Queries::top_empty_searches( 12 ),
					'term',
					__( 'No zero-result searches recorded yet — every search found a vendor.', 'owambe-connect-core' )
				);
				?>
			</div>
			<div class="oc-an-row">
				<?php
				$this->search_bars(
					__( 'Most searched categories', 'owambe-connect-core' ),
					OC_Queries::top_searched_categories( 12 ),
					'name',
					__( 'No category filters used yet.', 'owambe-connect-core' )
				);
				$this->search_bars(
					__( 'Most clicked categories', 'owambe-connect-core' ),
					OC_Queries::top_clicked_categories( 12 ),
					'name',
					__( 'No category browses recorded yet.', 'owambe-connect-core' )
				);
				?>
			</div>

			<!-- ============== Charts row 2 ============== -->
			<div class="oc-an-row">
				<div class="oc-an-card">
					<h3><?php esc_html_e( 'Top locations', 'owambe-connect-core' ); ?></h3>
					<?php if ( ! empty( $locations ) ) : ?>
						<table class="oc-an-bars">
							<?php
							$max = max( array_map( 'intval', wp_list_pluck( $locations, 'cnt' ) ) ) ?: 1;
							foreach ( $locations as $loc ) :
								$pct = round( ( (int) $loc->cnt / $max ) * 100 );
								?>
								<tr>
									<td class="oc-an-bars__label"><?php echo esc_html( $loc->location ); ?></td>
									<td class="oc-an-bars__bar">
										<span style="width:<?php echo esc_attr( $pct ); ?>%"></span>
									</td>
									<td class="oc-an-bars__val"><?php echo esc_html( $loc->cnt ); ?></td>
								</tr>
							<?php endforeach; ?>
						</table>
					<?php else : ?>
						<p class="oc-an-empty"><?php esc_html_e( 'No locations recorded yet.', 'owambe-connect-core' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="oc-an-card">
					<h3><?php esc_html_e( 'Price distribution', 'owambe-connect-core' ); ?></h3>
					<?php if ( ! empty( $prices ) ) : ?>
						<div class="oc-an-chart">
							<canvas id="oc-chart-prices"></canvas>
						</div>
					<?php else : ?>
						<p class="oc-an-empty"><?php esc_html_e( 'No price ranges set yet.', 'owambe-connect-core' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- ============== Recent activity ============== -->
			<div class="oc-an-card">
				<h3 style="display:flex;align-items:center;gap:8px;justify-content:space-between">
					<span><?php esc_html_e( 'Recent applications', 'owambe-connect-core' ); ?></span>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . OC_CPT ) ); ?>" class="button button-small"><?php esc_html_e( 'View all', 'owambe-connect-core' ); ?> →</a>
				</h3>
				<?php if ( $recent ) : ?>
					<table class="widefat striped oc-an-recent">
						<thead><tr>
							<th><?php esc_html_e( 'Vendor',   'owambe-connect-core' ); ?></th>
							<th><?php esc_html_e( 'Status',   'owambe-connect-core' ); ?></th>
							<th><?php esc_html_e( 'Category', 'owambe-connect-core' ); ?></th>
							<th><?php esc_html_e( 'Location', 'owambe-connect-core' ); ?></th>
							<th><?php esc_html_e( 'Submitted','owambe-connect-core' ); ?></th>
							<th></th>
						</tr></thead>
						<tbody>
							<?php foreach ( $recent as $p ) :
								$cats     = wp_get_post_terms( $p->ID, OC_TAX, [ 'fields' => 'names' ] );
								$location = get_post_meta( $p->ID, '_oc_location', true );
								$colors   = [ OC_STATUS_PENDING => '#B8860B', OC_STATUS_APPROVED => '#2E7D5B', OC_STATUS_REJECTED => '#B0354F' ];
								$color    = $colors[ $p->post_status ] ?? '#555';
								?>
								<tr>
									<td><strong><?php echo esc_html( $p->post_title ); ?></strong></td>
									<td><span style="color:<?php echo esc_attr( $color ); ?>;font-weight:600"><?php echo esc_html( oc_status_label( $p->post_status ) ); ?></span></td>
									<td><?php echo esc_html( implode( ', ', (array) $cats ) ?: '—' ); ?></td>
									<td><?php echo esc_html( $location ?: '—' ); ?></td>
									<td><?php echo esc_html( human_time_diff( strtotime( $p->post_date ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'owambe-connect-core' ) ); ?></td>
									<td><a class="button button-small" href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . $p->ID ) ); ?>"><?php esc_html_e( 'Open', 'owambe-connect-core' ); ?></a></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="oc-an-empty"><?php esc_html_e( 'No vendor applications in this date range.', 'owambe-connect-core' ); ?></p>
				<?php endif; ?>
			</div>

		</div>

		<?php
		// Chart.js — lazy-loaded only on this page.
		?>
		<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
		<script>
		(function () {
			var TS_LABELS    = <?php echo wp_json_encode( array_keys( $timeseries ) ); ?>;
			var TS_PENDING   = <?php echo wp_json_encode( array_values( wp_list_pluck( $timeseries, 'pending' ) ) ); ?>;
			var TS_APPROVED  = <?php echo wp_json_encode( array_values( wp_list_pluck( $timeseries, 'approved' ) ) ); ?>;
			var TS_REJECTED  = <?php echo wp_json_encode( array_values( wp_list_pluck( $timeseries, 'rejected' ) ) ); ?>;
			var CAT_LABELS   = <?php echo wp_json_encode( wp_list_pluck( $categories, 'name' ) ); ?>;
			var CAT_VALUES   = <?php echo wp_json_encode( array_map( 'intval', wp_list_pluck( $categories, 'cnt' ) ) ); ?>;
			var PRICE_LABELS = <?php echo wp_json_encode( array_keys( $prices ) ); ?>;
			var PRICE_VALUES = <?php echo wp_json_encode( array_values( $prices ) ); ?>;
			var TRK_LABELS   = <?php echo wp_json_encode( array_keys( $trk_series ) ); ?>;
			var TRK_VIEWS    = <?php echo wp_json_encode( array_map( 'intval', wp_list_pluck( $trk_series, 'views' ) ) ); ?>;
			var TRK_CLICKS   = <?php echo wp_json_encode( array_map( 'intval', wp_list_pluck( $trk_series, 'clicks' ) ) ); ?>;

			var BURG = '#6E0F2C', GOLD = '#C9A961', GREEN = '#2E7D5B', AMBER = '#B8860B', RED = '#B0354F';
			var PALETTE = ['#6E0F2C','#C9A961','#A8893D','#8B1538','#4A0A1E','#2E7D5B','#B8860B','#B0354F','#6B6361','#1F1B1A'];

			function ready(fn){ if(window.Chart){fn();} else {document.addEventListener('DOMContentLoaded',function(){ var t=setInterval(function(){if(window.Chart){clearInterval(t);fn();}},80); });}}

			ready(function () {
				Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
				Chart.defaults.color = '#1F1B1A';
				Chart.defaults.plugins.legend.position = 'bottom';

				// 1. Time series
				var tsEl = document.getElementById('oc-chart-timeseries');
				if (tsEl) {
					new Chart(tsEl, {
						type: 'line',
						data: {
							labels: TS_LABELS,
							datasets: [
								{ label: 'Approved',  data: TS_APPROVED, borderColor: GREEN, backgroundColor: GREEN+'22', tension: 0.3, fill: true },
								{ label: 'Pending',   data: TS_PENDING,  borderColor: AMBER, backgroundColor: AMBER+'22', tension: 0.3, fill: true },
								{ label: 'Rejected',  data: TS_REJECTED, borderColor: RED,   backgroundColor: RED+'18',   tension: 0.3, fill: true }
							]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							scales: {
								x: { grid: { display: false } },
								y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
							}
						}
					});
				}

				// 2. Categories donut
				var catEl = document.getElementById('oc-chart-categories');
				if (catEl && CAT_LABELS.length) {
					new Chart(catEl, {
						type: 'doughnut',
						data: {
							labels: CAT_LABELS,
							datasets: [{ data: CAT_VALUES, backgroundColor: PALETTE, borderWidth: 2, borderColor: '#fff' }]
						},
						options: { responsive: true, maintainAspectRatio: false, cutout: '60%' }
					});
				}

				// Phase 2 — views & clicks line chart.
				var trkEl = document.getElementById('oc-chart-tracking');
				if (trkEl && TRK_LABELS.length) {
					new Chart(trkEl, {
						type: 'line',
						data: {
							labels: TRK_LABELS,
							datasets: [
								{ label: 'Profile views',  data: TRK_VIEWS,  borderColor: BURG, backgroundColor: BURG+'18', tension: 0.3, fill: true },
								{ label: 'Contact clicks', data: TRK_CLICKS, borderColor: GOLD, backgroundColor: GOLD+'22', tension: 0.3, fill: true }
							]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							scales: {
								x: { grid: { display: false } },
								y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
							}
						}
					});
				}

				// 3. Price bar
				var prEl = document.getElementById('oc-chart-prices');
				if (prEl && PRICE_LABELS.length) {
					new Chart(prEl, {
						type: 'bar',
						data: {
							labels: PRICE_LABELS,
							datasets: [{ label: 'Vendors', data: PRICE_VALUES, backgroundColor: BURG, borderRadius: 6 }]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							plugins: { legend: { display: false } },
							scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } }
						}
					});
				}
			});
		})();
		</script>

		<style>
			.oc-an h1 { color:#1F1B1A; }
			.oc-an-filters { background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:14px 16px; margin:16px 0 22px; }
			.oc-an-presets { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:12px; padding-bottom:12px; border-bottom:1px solid #EFEAE2; }
			.oc-an-preset { padding:6px 14px; background:#FAF7F2; border:1px solid #E4DDD2; border-radius:999px; color:#6B6361; text-decoration:none; font-weight:500; font-size:13px; }
			.oc-an-preset:hover { border-color:#6E0F2C; color:#6E0F2C; }
			.oc-an-preset.is-active { background:#6E0F2C; color:#fff; border-color:#6E0F2C; }
			.oc-an-controls { display:flex; flex-wrap:wrap; gap:10px; align-items:end; }
			.oc-an-controls label { display:flex; flex-direction:column; gap:3px; font-size:12px; color:#6B6361; font-weight:500; text-transform:uppercase; letter-spacing:.06em; }
			.oc-an-controls input[type="date"], .oc-an-controls select { padding:6px 10px; border:1px solid #ccd0d4; border-radius:4px; font-size:13px; }

			.oc-an-kpis { display:grid; grid-template-columns:repeat(2, 1fr); gap:12px; margin-bottom:22px; }
			@media (min-width: 900px)  { .oc-an-kpis { grid-template-columns:repeat(3, 1fr); } }
			@media (min-width: 1300px) { .oc-an-kpis { grid-template-columns:repeat(6, 1fr); } }
			.oc-an-kpi { background:#fff; border:1px solid #E4DDD2; border-top:3px solid var(--oc-kpi,#6E0F2C); border-radius:10px; padding:15px 16px; display:flex; flex-direction:column; gap:6px; min-height:104px; position:relative; overflow:hidden; box-shadow:0 1px 2px rgba(31,27,26,.06); }
			.oc-an-kpi__badge { width:36px; height:36px; border-radius:9px; display:inline-flex; align-items:center; justify-content:center; margin-bottom:2px; color:var(--oc-kpi,#6E0F2C); background:#F1E9DE; background:color-mix(in srgb, var(--oc-kpi) 16%, #fff); }
			.oc-an-kpi__badge .dashicons { font-size:20px; width:20px; height:20px; line-height:20px; }
			.oc-an-kpi__label { font-size:11px; text-transform:uppercase; letter-spacing:.07em; color:#6B6361; font-weight:600; }
			.oc-an-kpi__val   { font-family:Georgia, serif; font-size:2.15rem; line-height:1.05; color:#1F1B1A; font-weight:700; }
			.oc-an-kpi__drill { font-size:11px; font-weight:600; color:var(--oc-kpi,#6E0F2C); letter-spacing:.02em; margin-top:auto; opacity:0; transition:opacity .15s; }
			a.oc-an-kpi { text-decoration:none; color:inherit; cursor:pointer; transition:box-shadow .15s, transform .15s, border-color .15s; }
			.oc-an-kpi--link:hover, .oc-an-kpi--link:focus-visible { box-shadow:0 4px 14px rgba(31,27,26,.12); transform:translateY(-2px); border-color:#C9A961; }
			.oc-an-kpi--link:focus-visible { outline:2px solid #6E0F2C; outline-offset:2px; }
			.oc-an-kpi--link:hover .oc-an-kpi__drill, .oc-an-kpi--link:focus-visible .oc-an-kpi__drill, .oc-an-kpi--link.is-active .oc-an-kpi__drill { opacity:1; }
			.oc-an-kpi--link.is-active { border-color:#6E0F2C; box-shadow:0 0 0 2px rgba(110,15,44,.22); }

			.oc-an-row { display:grid; grid-template-columns:1fr; gap:12px; margin-bottom:14px; }
			@media (min-width: 1100px) { .oc-an-row { grid-template-columns:1fr 1fr; } .oc-an-row:has(.oc-an-card--wide) { grid-template-columns:2fr 1fr; } }
			.oc-an-card { background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:18px 20px; }
			.oc-an-card h3 { font-family:Georgia, serif; color:#6E0F2C; margin:0 0 14px; padding-bottom:10px; border-bottom:2px solid #C9A961; font-size:1rem; }
			.oc-an-empty { color:#6B6361; padding:24px 0; text-align:center; }
			.oc-an-chart { position:relative; width:100%; height:240px; }
			.oc-an-chart--tall { height:280px; }
			.oc-an-chart canvas { max-width:100%; }

			.oc-an-bars { width:100%; border-collapse:collapse; }
			.oc-an-bars td { padding:6px 0; font-size:13px; vertical-align:middle; }
			.oc-an-bars__label { width:35%; color:#1F1B1A; padding-right:10px !important; }
			.oc-an-bars__bar { width:55%; padding-right:10px !important; }
			.oc-an-bars__bar span { display:block; height:8px; background:linear-gradient(90deg, #6E0F2C, #C9A961); border-radius:999px; min-width:6px; }
			.oc-an-bars__val { width:10%; text-align:right; font-weight:600; color:#6E0F2C; font-family:Georgia, serif; font-size:1rem; }
			.oc-an-top .oc-an-bars__label a { color:#1F1B1A; text-decoration:none; font-weight:600; }
			.oc-an-top .oc-an-bars__label a:hover { color:#6E0F2C; text-decoration:underline; }

			.oc-an-recent th { background:#FAF7F2; }
		</style>
		<?php
	}

	/**
	 * Render one KPI card.
	 *
	 * @param string $href   Optional drill-down URL. When set the card renders
	 *                       as a clickable <a> that filters the per-vendor
	 *                       breakdown below instead of a static <div>.
	 * @param bool   $active Whether this card's drill-down is the one currently shown.
	 */
	private function kpi_card( $label, $value, $dashicon, $color, $href = '', $active = false ) {
		$open = $href
			? '<a class="oc-an-kpi oc-an-kpi--link' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( $href ) . '" style="--oc-kpi:' . esc_attr( $color ) . '">'
			: '<div class="oc-an-kpi" style="--oc-kpi:' . esc_attr( $color ) . '">';
		echo $open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- values escaped above.
		?>
			<span class="oc-an-kpi__badge">
				<span class="dashicons dashicons-<?php echo esc_attr( $dashicon ); ?>"></span>
			</span>
			<span class="oc-an-kpi__label"><?php echo esc_html( $label ); ?></span>
			<span class="oc-an-kpi__val"><?php echo esc_html( $value ); ?></span>
			<?php if ( $href ) : ?>
				<span class="oc-an-kpi__drill"><?php esc_html_e( 'View vendors', 'owambe-connect-core' ); ?> &rarr;</span>
			<?php endif; ?>
		<?php
		echo $href ? '</a>' : '</div>';
	}

	/**
	 * Render one ranked bar table (a search/category leaderboard) as an analytics
	 * card. Rows are [ $label_key => string, 'count' => int ], pre-sorted desc.
	 *
	 * @param string $title     Card heading.
	 * @param array  $rows      Display rows from OC_Queries::top_* helpers.
	 * @param string $label_key Row key holding the label ('term' or 'name').
	 * @param string $empty_msg Shown when there are no rows yet.
	 */
	private function search_bars( $title, array $rows, $label_key, $empty_msg ) {
		?>
		<div class="oc-an-card">
			<h3><?php echo esc_html( $title ); ?></h3>
			<?php if ( ! empty( $rows ) ) : ?>
				<table class="oc-an-bars">
					<?php
					$max = max( array_map( static function ( $r ) { return (int) $r['count']; }, $rows ) ) ?: 1;
					foreach ( $rows as $r ) :
						$count = (int) $r['count'];
						$pct   = round( ( $count / $max ) * 100 );
						?>
						<tr>
							<td class="oc-an-bars__label"><?php echo esc_html( (string) ( $r[ $label_key ] ?? '' ) ); ?></td>
							<td class="oc-an-bars__bar"><span style="width:<?php echo esc_attr( $pct ); ?>%"></span></td>
							<td class="oc-an-bars__val"><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php else : ?>
				<p class="oc-an-empty"><?php echo esc_html( $empty_msg ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Vendors offered in the per-vendor drill-down picker (all non-trashed). */
	private function vendor_choices() {
		return get_posts( [
			'post_type'      => OC_CPT,
			'post_status'    => [ OC_STATUS_APPROVED, OC_STATUS_PENDING, OC_STATUS_REJECTED ],
			'posts_per_page' => 500,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
	}

	/**
	 * Per-vendor analytics view — same date filters as the marketplace page,
	 * scoped to one vendor: views/clicks KPIs, per-channel breakdown and a
	 * daily time-series chart. Reached via &vendor=<ID> (picker or the
	 * chart-bar action on the vendors list).
	 */
	private function render_vendor( $vendor_post ) {
		$vendor_id = (int) $vendor_post->ID;
		$base_url  = admin_url( 'edit.php?post_type=' . OC_CPT . '&page=' . self::PAGE );
		$self_url  = add_query_arg( 'vendor', $vendor_id, $base_url );

		$trk_from = substr( (string) $this->filters['from'], 0, 10 );
		$trk_to   = substr( (string) $this->filters['to'], 0, 10 );

		$has_tracking = class_exists( 'OC_Tracking' );
		$counts = $has_tracking ? OC_Tracking::counts_range( $vendor_id, $trk_from, $trk_to ) : [];
		$series = $has_tracking ? OC_Tracking::timeseries_range( $trk_from, $trk_to, $vendor_id ) : [];

		$views        = (int) ( $counts['view'] ?? 0 );
		$channels     = [
			'click_whatsapp'  => [ __( 'WhatsApp',  'owambe-connect-core' ), '#2E7D5B' ],
			'click_email'     => [ __( 'Email',     'owambe-connect-core' ), '#6E0F2C' ],
			'click_instagram' => [ __( 'Instagram', 'owambe-connect-core' ), '#B0354F' ],
			'click_facebook'  => [ __( 'Facebook',  'owambe-connect-core' ), '#1877F2' ],
			'click_website'   => [ __( 'Website',   'owambe-connect-core' ), '#A8893D' ],
		];
		$total_clicks = 0;
		foreach ( $channels as $metric => $_ ) {
			$total_clicks += (int) ( $counts[ $metric ] ?? 0 );
		}
		$ctr = $views > 0 ? round( ( $total_clicks / $views ) * 100, 1 ) . '%' : '—';

		$status_colors = [ OC_STATUS_PENDING => '#B8860B', OC_STATUS_APPROVED => '#2E7D5B', OC_STATUS_REJECTED => '#B0354F' ];
		$status_color  = $status_colors[ $vendor_post->post_status ] ?? '#555';
		$view_url      = OC_STATUS_APPROVED === $vendor_post->post_status ? get_permalink( $vendor_id ) : '';
		?>
		<div class="wrap oc-an">
			<p style="margin:14px 0 4px">
				<a href="<?php echo esc_url( $base_url ); ?>">&larr; <?php esc_html_e( 'Back to marketplace analytics', 'owambe-connect-core' ); ?></a>
			</p>
			<h1 style="margin-bottom:6px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
				<span class="dashicons dashicons-chart-bar" style="color:#6E0F2C;font-size:28px"></span>
				<?php echo esc_html( $vendor_post->post_title ); ?>
				<span style="font-size:13px;font-weight:600;color:<?php echo esc_attr( $status_color ); ?>"><?php echo esc_html( oc_status_label( $vendor_post->post_status ) ); ?></span>
				<span style="margin-left:auto;display:flex;gap:6px">
					<a class="button button-small" href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . $vendor_id ) ); ?>"><?php esc_html_e( 'Edit vendor', 'owambe-connect-core' ); ?></a>
					<?php if ( $view_url ) : ?>
						<a class="button button-small" href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View profile', 'owambe-connect-core' ); ?> ↗</a>
					<?php endif; ?>
				</span>
			</h1>
			<p style="margin:0 0 18px;color:#555">
				<?php
				printf(
					/* translators: 1: from date, 2: to date */
					esc_html__( 'Showing data from %1$s to %2$s', 'owambe-connect-core' ),
					'<strong>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $this->filters['from'] ) ) ) . '</strong>',
					'<strong>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $this->filters['to'] ) ) ) . '</strong>'
				);
				?>
			</p>

			<!-- Filter bar (dates only — vendor is pinned) -->
			<form method="get" class="oc-an-filters">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( OC_CPT ); ?>" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<input type="hidden" name="vendor" value="<?php echo (int) $vendor_id; ?>" />

				<div class="oc-an-presets">
					<?php
					$presets = [
						'7d'  => __( 'Last 7 days',   'owambe-connect-core' ),
						'30d' => __( 'Last 30 days',  'owambe-connect-core' ),
						'90d' => __( 'Last 90 days',  'owambe-connect-core' ),
						'12m' => __( 'Last 12 months','owambe-connect-core' ),
						'all' => __( 'All time',      'owambe-connect-core' ),
					];
					foreach ( $presets as $key => $label ) :
						$url = add_query_arg( 'range', $key, $self_url );
						?>
						<a class="oc-an-preset <?php echo $this->filters['range'] === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</div>

				<div class="oc-an-controls">
					<label><?php esc_html_e( 'From', 'owambe-connect-core' ); ?>
						<input type="date" name="from" value="<?php echo esc_attr( substr( $this->filters['from'], 0, 10 ) ); ?>" />
					</label>
					<label><?php esc_html_e( 'To', 'owambe-connect-core' ); ?>
						<input type="date" name="to" value="<?php echo esc_attr( substr( $this->filters['to'], 0, 10 ) ); ?>" />
					</label>
					<input type="hidden" name="range" value="custom" />
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'owambe-connect-core' ); ?></button>
					<a href="<?php echo esc_url( $self_url ); ?>" class="button"><?php esc_html_e( 'Reset', 'owambe-connect-core' ); ?></a>
				</div>
			</form>

			<!-- KPI cards -->
			<div class="oc-an-kpis">
				<?php $this->kpi_card( __( 'Profile views',      'owambe-connect-core' ), number_format_i18n( $views ),        'visibility', '#2E7D5B' ); ?>
				<?php $this->kpi_card( __( 'Contact clicks',     'owambe-connect-core' ), number_format_i18n( $total_clicks ), 'phone',      '#6E0F2C' ); ?>
				<?php $this->kpi_card( __( 'Click-through rate', 'owambe-connect-core' ), $ctr,                                'chart-line', '#A8893D' ); ?>
				<?php $this->kpi_card( __( 'WhatsApp taps',      'owambe-connect-core' ), number_format_i18n( (int) ( $counts['click_whatsapp'] ?? 0 ) ),  'format-chat', '#2E7D5B' ); ?>
				<?php $this->kpi_card( __( 'Email clicks',       'owambe-connect-core' ), number_format_i18n( (int) ( $counts['click_email'] ?? 0 ) ),     'email',       '#6E0F2C' ); ?>
				<?php $this->kpi_card( __( 'Instagram clicks',   'owambe-connect-core' ), number_format_i18n( (int) ( $counts['click_instagram'] ?? 0 ) ), 'instagram',   '#B0354F' ); ?>
			</div>

			<div class="oc-an-row">
				<div class="oc-an-card oc-an-card--wide">
					<h3><?php esc_html_e( 'Views & clicks over time', 'owambe-connect-core' ); ?></h3>
					<?php if ( $views + $total_clicks > 0 ) : ?>
						<div class="oc-an-chart oc-an-chart--tall">
							<canvas id="oc-chart-vendor"></canvas>
						</div>
					<?php else : ?>
						<p class="oc-an-empty"><?php esc_html_e( 'No views or clicks recorded for this vendor in this period.', 'owambe-connect-core' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="oc-an-card">
					<h3><?php esc_html_e( 'Clicks by channel', 'owambe-connect-core' ); ?></h3>
					<?php if ( $total_clicks > 0 ) : ?>
						<table class="oc-an-bars">
							<?php
							$max = 1;
							foreach ( $channels as $metric => $_ ) {
								$max = max( $max, (int) ( $counts[ $metric ] ?? 0 ) );
							}
							foreach ( $channels as $metric => [ $label, $color ] ) :
								$val = (int) ( $counts[ $metric ] ?? 0 );
								$pct = round( ( $val / $max ) * 100 );
								?>
								<tr>
									<td class="oc-an-bars__label"><?php echo esc_html( $label ); ?></td>
									<td class="oc-an-bars__bar">
										<span style="width:<?php echo esc_attr( $pct ); ?>%;background:<?php echo esc_attr( $color ); ?>"></span>
									</td>
									<td class="oc-an-bars__val"><?php echo esc_html( number_format_i18n( $val ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</table>
					<?php else : ?>
						<p class="oc-an-empty"><?php esc_html_e( 'No contact clicks in this period yet.', 'owambe-connect-core' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
		<script>
		(function () {
			var LABELS = <?php echo wp_json_encode( array_keys( $series ) ); ?>;
			var VIEWS  = <?php echo wp_json_encode( array_map( 'intval', wp_list_pluck( $series, 'views' ) ) ); ?>;
			var CLICKS = <?php echo wp_json_encode( array_map( 'intval', wp_list_pluck( $series, 'clicks' ) ) ); ?>;
			var BURG = '#6E0F2C', GOLD = '#C9A961';

			function ready(fn){ if(window.Chart){fn();} else {document.addEventListener('DOMContentLoaded',function(){ var t=setInterval(function(){if(window.Chart){clearInterval(t);fn();}},80); });}}

			ready(function () {
				Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
				Chart.defaults.color = '#1F1B1A';
				Chart.defaults.plugins.legend.position = 'bottom';

				var el = document.getElementById('oc-chart-vendor');
				if (el && LABELS.length) {
					new Chart(el, {
						type: 'line',
						data: {
							labels: LABELS,
							datasets: [
								{ label: '<?php echo esc_js( __( 'Profile views', 'owambe-connect-core' ) ); ?>',  data: VIEWS,  borderColor: BURG, backgroundColor: BURG+'18', tension: 0.3, fill: true },
								{ label: '<?php echo esc_js( __( 'Contact clicks', 'owambe-connect-core' ) ); ?>', data: CLICKS, borderColor: GOLD, backgroundColor: GOLD+'22', tension: 0.3, fill: true }
							]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							scales: {
								x: { grid: { display: false } },
								y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
							}
						}
					});
				}
			});
		})();
		</script>

		<style>
			.oc-an h1 { color:#1F1B1A; }
			.oc-an-filters { background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:14px 16px; margin:16px 0 22px; }
			.oc-an-presets { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:12px; padding-bottom:12px; border-bottom:1px solid #EFEAE2; }
			.oc-an-preset { padding:6px 14px; background:#FAF7F2; border:1px solid #E4DDD2; border-radius:999px; color:#6B6361; text-decoration:none; font-weight:500; font-size:13px; }
			.oc-an-preset:hover { border-color:#6E0F2C; color:#6E0F2C; }
			.oc-an-preset.is-active { background:#6E0F2C; color:#fff; border-color:#6E0F2C; }
			.oc-an-controls { display:flex; flex-wrap:wrap; gap:10px; align-items:end; }
			.oc-an-controls label { display:flex; flex-direction:column; gap:3px; font-size:12px; color:#6B6361; font-weight:500; text-transform:uppercase; letter-spacing:.06em; }
			.oc-an-controls input[type="date"], .oc-an-controls select { padding:6px 10px; border:1px solid #ccd0d4; border-radius:4px; font-size:13px; }

			.oc-an-kpis { display:grid; grid-template-columns:repeat(2, 1fr); gap:12px; margin-bottom:22px; }
			@media (min-width: 900px)  { .oc-an-kpis { grid-template-columns:repeat(3, 1fr); } }
			@media (min-width: 1300px) { .oc-an-kpis { grid-template-columns:repeat(6, 1fr); } }
			.oc-an-kpi { background:#fff; border:1px solid #E4DDD2; border-top:3px solid var(--oc-kpi,#6E0F2C); border-radius:10px; padding:15px 16px; display:flex; flex-direction:column; gap:6px; min-height:104px; position:relative; overflow:hidden; box-shadow:0 1px 2px rgba(31,27,26,.06); }
			.oc-an-kpi__badge { width:36px; height:36px; border-radius:9px; display:inline-flex; align-items:center; justify-content:center; margin-bottom:2px; color:var(--oc-kpi,#6E0F2C); background:#F1E9DE; background:color-mix(in srgb, var(--oc-kpi) 16%, #fff); }
			.oc-an-kpi__badge .dashicons { font-size:20px; width:20px; height:20px; line-height:20px; }
			.oc-an-kpi__label { font-size:11px; text-transform:uppercase; letter-spacing:.07em; color:#6B6361; font-weight:600; }
			.oc-an-kpi__val   { font-family:Georgia, serif; font-size:2.15rem; line-height:1.05; color:#1F1B1A; font-weight:700; }

			.oc-an-row { display:grid; grid-template-columns:1fr; gap:12px; margin-bottom:14px; }
			@media (min-width: 1100px) { .oc-an-row { grid-template-columns:2fr 1fr; } }
			.oc-an-card { background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:18px 20px; }
			.oc-an-card h3 { font-family:Georgia, serif; color:#6E0F2C; margin:0 0 14px; padding-bottom:10px; border-bottom:2px solid #C9A961; font-size:1rem; }
			.oc-an-empty { color:#6B6361; padding:24px 0; text-align:center; }
			.oc-an-chart { position:relative; width:100%; height:240px; }
			.oc-an-chart--tall { height:280px; }
			.oc-an-chart canvas { max-width:100%; }

			.oc-an-bars { width:100%; border-collapse:collapse; }
			.oc-an-bars td { padding:6px 0; font-size:13px; vertical-align:middle; }
			.oc-an-bars__label { width:35%; color:#1F1B1A; padding-right:10px !important; }
			.oc-an-bars__bar { width:55%; padding-right:10px !important; }
			.oc-an-bars__bar span { display:block; height:8px; background:linear-gradient(90deg, #6E0F2C, #C9A961); border-radius:999px; min-width:6px; }
			.oc-an-bars__val { width:10%; text-align:right; font-weight:600; color:#6E0F2C; font-family:Georgia, serif; font-size:1rem; }
		</style>
		<?php
	}

	// =================================================================
	//  Queries
	// =================================================================

	private function category_join_clause() {
		global $wpdb;
		if ( ! $this->filters['cat'] ) return [ '', [] ];
		$term = get_term_by( 'slug', $this->filters['cat'], OC_TAX );
		if ( ! $term ) return [ '', [] ];
		return [
			" INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			  INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.term_id = %d ",
			[ $term->term_id ],
		];
	}

	private function kpis() {
		global $wpdb;
		[ $cat_join, $cat_args ] = $this->category_join_clause();

		// Whole-marketplace counts (not date-filtered).
		$sql = "SELECT p.post_status as st, COUNT(*) as cnt
		        FROM {$wpdb->posts} p $cat_join
		        WHERE p.post_type = %s
		          AND p.post_status IN (%s, %s, %s)
		        GROUP BY p.post_status";
		$args   = array_merge( $cat_args, [ OC_CPT, OC_STATUS_PENDING, OC_STATUS_APPROVED, OC_STATUS_REJECTED ] );
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) );
		$counts = [ OC_STATUS_PENDING => 0, OC_STATUS_APPROVED => 0, OC_STATUS_REJECTED => 0 ];
		foreach ( (array) $rows as $r ) $counts[ $r->st ] = (int) $r->cnt;

		$total_decided  = $counts[ OC_STATUS_APPROVED ] + $counts[ OC_STATUS_REJECTED ];
		$approval_rate  = $total_decided ? round( ( $counts[ OC_STATUS_APPROVED ] / $total_decided ) * 100 ) : 0;
		$approval_label = $total_decided ? $approval_rate . '%' : '—';

		// Featured count.
		$featured = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_oc_featured' AND pm.meta_value = '1'
			 WHERE p.post_type = %s AND p.post_status = %s",
			OC_CPT, OC_STATUS_APPROVED
		) );

		// In-period: applications submitted, vendors approved (using post_modified for approval timing).
		$applications_period = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p $cat_join
			 WHERE p.post_type = %s
			   AND p.post_status IN (%s, %s, %s)
			   AND p.post_date BETWEEN %s AND %s",
			...array_merge( $cat_args, [ OC_CPT, OC_STATUS_PENDING, OC_STATUS_APPROVED, OC_STATUS_REJECTED, $this->filters['from'], $this->filters['to'] ] )
		) );
		$approved_period = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p $cat_join
			 WHERE p.post_type = %s
			   AND p.post_status = %s
			   AND p.post_modified BETWEEN %s AND %s",
			...array_merge( $cat_args, [ OC_CPT, OC_STATUS_APPROVED, $this->filters['from'], $this->filters['to'] ] )
		) );

		return [
			'live'                 => $counts[ OC_STATUS_APPROVED ],
			'pending'              => $counts[ OC_STATUS_PENDING ],
			'rejected'             => $counts[ OC_STATUS_REJECTED ],
			'featured'             => $featured,
			'approval_rate_label'  => $approval_label,
			'applications'         => $applications_period,
			'approved_period'      => $approved_period,
		];
	}

	/**
	 * Daily counts of new vendor posts grouped by status.
	 * Returns array keyed by 'M j' label, value = [pending, approved, rejected].
	 */
	private function applications_timeseries() {
		global $wpdb;
		[ $cat_join, $cat_args ] = $this->category_join_clause();

		$sql = "SELECT DATE(p.post_date) as day, p.post_status as st, COUNT(*) as cnt
		        FROM {$wpdb->posts} p $cat_join
		        WHERE p.post_type = %s
		          AND p.post_status IN (%s, %s, %s)
		          AND p.post_date BETWEEN %s AND %s
		        GROUP BY DATE(p.post_date), p.post_status";
		$args = array_merge( $cat_args, [ OC_CPT, OC_STATUS_PENDING, OC_STATUS_APPROVED, OC_STATUS_REJECTED, $this->filters['from'], $this->filters['to'] ] );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) );

		$start  = strtotime( $this->filters['from'] );
		$end    = strtotime( $this->filters['to'] );
		$days   = max( 1, (int) ( ( $end - $start ) / 86400 ) );
		$bucket = $days > 90 ? 'month' : ( $days > 31 ? 'week' : 'day' );

		// Bucket helper.
		$key_for = static function ( $date_str ) use ( $bucket ) {
			$ts = strtotime( $date_str );
			if ( 'month' === $bucket )  return date( 'M Y', $ts );
			if ( 'week' === $bucket )   return date( 'M j', strtotime( 'monday this week', $ts ) );
			return date( 'M j', $ts );
		};

		// Pre-fill labels with zeros so the chart shows continuous timeline.
		$series = [];
		$cursor = $start;
		while ( $cursor <= $end ) {
			$series[ $key_for( date( 'Y-m-d', $cursor ) ) ] = [ 'pending' => 0, 'approved' => 0, 'rejected' => 0 ];
			$cursor += 'month' === $bucket ? 86400 * 28 : ( 'week' === $bucket ? 86400 * 7 : 86400 );
		}

		foreach ( (array) $rows as $r ) {
			$key = $key_for( $r->day );
			if ( ! isset( $series[ $key ] ) ) {
				$series[ $key ] = [ 'pending' => 0, 'approved' => 0, 'rejected' => 0 ];
			}
			if ( OC_STATUS_PENDING === $r->st )  $series[ $key ]['pending']  += (int) $r->cnt;
			if ( OC_STATUS_APPROVED === $r->st ) $series[ $key ]['approved'] += (int) $r->cnt;
			if ( OC_STATUS_REJECTED === $r->st ) $series[ $key ]['rejected'] += (int) $r->cnt;
		}

		return $series;
	}

	private function vendors_by_category() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.name, t.slug, COUNT(DISTINCT p.ID) as cnt
			 FROM {$wpdb->terms} t
			 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			 INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			 INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
			 WHERE tt.taxonomy = %s
			   AND p.post_type = %s
			   AND p.post_status = %s
			 GROUP BY t.term_id
			 HAVING cnt > 0
			 ORDER BY cnt DESC",
			OC_TAX, OC_CPT, OC_STATUS_APPROVED
		) );
		return is_array( $rows ) ? $rows : [];
	}

	private function top_locations( $limit = 10 ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.meta_value as location, COUNT(*) as cnt
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			 WHERE pm.meta_key = '_oc_location'
			   AND pm.meta_value <> ''
			   AND p.post_type = %s
			   AND p.post_status = %s
			 GROUP BY pm.meta_value
			 ORDER BY cnt DESC
			 LIMIT %d",
			OC_CPT, OC_STATUS_APPROVED, (int) $limit
		) );
		return is_array( $rows ) ? $rows : [];
	}

	private function price_distribution() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.meta_value as price, COUNT(*) as cnt
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			 WHERE pm.meta_key = '_oc_price_range'
			   AND pm.meta_value <> ''
			   AND p.post_type = %s
			   AND p.post_status = %s
			 GROUP BY pm.meta_value",
			OC_CPT, OC_STATUS_APPROVED
		) );

		$labels = oc_price_range_options();
		$out    = [];
		foreach ( (array) $rows as $r ) {
			$key       = isset( $labels[ $r->price ] ) ? $labels[ $r->price ] : ucfirst( $r->price );
			$out[ $key ] = (int) $r->cnt;
		}
		return $out;
	}

	private function recent_vendors( $limit = 10 ) {
		return get_posts( [
			'post_type'   => OC_CPT,
			'post_status' => [ OC_STATUS_PENDING, OC_STATUS_APPROVED, OC_STATUS_REJECTED ],
			'numberposts' => (int) $limit,
			'orderby'     => 'date',
			'order'       => 'DESC',
		] );
	}
}
