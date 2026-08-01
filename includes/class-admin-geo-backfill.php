<?php
/**
 * Geo Backfill — writes _oc_lat/_oc_lng for existing vendors (P12).
 *
 * Vendors → Geo Backfill. Same batched-AJAX shape as the Tag Migration tool
 * (200 vendors per request). Dry run classifies every vendor WITHOUT
 * spending geocoding API calls: city-table hit / region-centroid hit /
 * would-need-geocoding / no location at all. Execute writes coords via
 * OC_Geo::write_coords() (which may geocode when maps_api_key is set) and
 * is idempotent — re-runs simply recompute the same values.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Geo_Backfill {

	const PAGE  = 'oc-geo-backfill';
	const AJAX  = 'oc_geo_backfill';
	const NONCE = 'oc_geo_backfill';
	const BATCH = 200;

	public function register() {
		add_action( 'admin_menu', [ $this, 'menu' ], 31 );
		add_action( 'wp_ajax_' . self::AJAX, [ $this, 'ajax' ] );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Geo Backfill', 'owambe-connect-core' ),
			__( 'Geo Backfill', 'owambe-connect-core' ),
			'manage_options',
			self::PAGE,
			[ $this, 'render' ]
		);
	}

	public function ajax() {
		check_ajax_referer( self::NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}

		$mode   = isset( $_POST['mode'] ) && 'execute' === $_POST['mode'] ? 'execute' : 'dry';
		$offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;

		$ids = get_posts( [
			'post_type'     => OC_CPT,
			'post_status'   => self::statuses(),
			'orderby'       => 'ID',
			'order'         => 'ASC',
			'offset'        => $offset,
			'numberposts'   => self::BATCH,
			'fields'        => 'ids',
			'no_found_rows' => true,
		] );

		$stats = self::process( $ids, 'execute' === $mode );

		wp_send_json_success( [
			'stats'       => $stats,
			'processed'   => count( $ids ),
			'next_offset' => $offset + count( $ids ),
			'done'        => count( $ids ) < self::BATCH,
			'total'       => self::total_vendors(),
		] );
	}

	/**
	 * @param int[] $ids     Vendor IDs.
	 * @param bool  $execute True = write coords (may geocode); false = classify only.
	 * @return array Batch stats.
	 */
	public static function process( array $ids, $execute = false ) {
		$city_table   = OC_Geo::city_coords();
		$region_table = OC_Geo::region_coords();
		$has_key      = '' !== trim( (string) oc_get_setting( 'maps_api_key', '' ) );

		$stats = [
			'scanned' => 0, 'city_hit' => 0, 'region_hit' => 0,
			'geocode_needed' => 0, 'no_location' => 0, 'written' => 0,
		];

		foreach ( $ids as $id ) {
			$stats['scanned']++;

			$areas   = array_values( array_filter( array_map( 'trim', (array) get_post_meta( $id, '_oc_location_areas', true ) ) ) );
			$regions = array_values( array_filter( array_map( 'trim', (array) get_post_meta( $id, '_oc_location_regions', true ) ) ) );
			$summary = trim( (string) get_post_meta( $id, '_oc_location', true ) );

			if ( $areas && isset( $city_table[ $areas[0] ] ) ) {
				$stats['city_hit']++;
			} elseif ( $regions && isset( $region_table[ $regions[0] ] ) ) {
				$stats['region_hit']++;
			} elseif ( '' !== $summary || $areas ) {
				$stats['geocode_needed']++;
			} else {
				$stats['no_location']++;
			}

			if ( $execute ) {
				OC_Geo::write_coords( $id );
				if ( get_post_meta( $id, OC_Geo::META_LAT, true ) !== '' ) {
					$stats['written']++;
				}
			}
		}

		$stats['geocoding_available'] = $has_key ? 1 : 0;
		return $stats;
	}

	private static function total_vendors() {
		$counts = (array) wp_count_posts( OC_CPT );
		$counts = array_intersect_key( $counts, array_flip( self::statuses() ) );
		return array_sum( array_map( 'intval', $counts ) );
	}

	private static function statuses() {
		return array_values( array_diff(
			array_keys( get_post_stati() ),
			[ 'trash', 'auto-draft', 'inherit' ]
		) );
	}

	public function render() {
		$nonce = wp_create_nonce( self::NONCE );
		?>
		<div class="wrap oc-geobf">
			<h1><?php esc_html_e( 'Geo Backfill', 'owambe-connect-core' ); ?></h1>
			<p class="oc-geobf__intro">
				<?php esc_html_e( 'Writes latitude/longitude for every existing vendor so radius search covers them. New and edited vendors get coordinates automatically on save — this tool is for the back catalogue. Dry-run first: it classifies every vendor without calling any geocoding API.', 'owambe-connect-core' ); ?>
			</p>

			<div class="oc-geobf__actions">
				<button type="button" class="button button-secondary" id="oc-geobf-dry"><?php esc_html_e( 'Run Dry-Run Analysis', 'owambe-connect-core' ); ?></button>
				<button type="button" class="button button-primary" id="oc-geobf-exec"><?php esc_html_e( 'Execute Backfill', 'owambe-connect-core' ); ?></button>
			</div>

			<div class="oc-geobf__progress" id="oc-geobf-progress" hidden>
				<div class="oc-geobf__bar"><span id="oc-geobf-bar-fill"></span></div>
				<p id="oc-geobf-status"></p>
			</div>

			<div id="oc-geobf-report"></div>
		</div>

		<style>
			.oc-geobf__intro { max-width: 720px; }
			.oc-geobf__actions { display: flex; gap: 10px; margin: 16px 0; }
			.oc-geobf__bar { width: 420px; max-width: 100%; height: 12px; background: #dcdcde; border-radius: 6px; overflow: hidden; }
			.oc-geobf__bar span { display: block; height: 100%; width: 0; background: #6E0F2C; border-radius: 6px; transition: width .2s ease; }
			.oc-geobf table.widefat { max-width: 720px; margin-top: 16px; }
			.oc-geobf .ok   { color: #2E7D52; font-weight: 600; }
			.oc-geobf .warn { color: #C77D0A; font-weight: 600; }
		</style>

		<script>
		(function () {
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
			var action  = <?php echo wp_json_encode( self::AJAX ); ?>;
			var i18n = {
				running:  <?php echo wp_json_encode( __( 'Processing vendors…', 'owambe-connect-core' ) ); ?>,
				dryDone:  <?php echo wp_json_encode( __( 'Dry run complete — nothing was written.', 'owambe-connect-core' ) ); ?>,
				execDone: <?php echo wp_json_encode( __( 'Backfill complete.', 'owambe-connect-core' ) ); ?>,
				confirm:  <?php echo wp_json_encode( __( 'Write coordinates for all vendors now? Safe to re-run at any time.', 'owambe-connect-core' ) ); ?>,
				error:    <?php echo wp_json_encode( __( 'Request failed — check the console and try again.', 'owambe-connect-core' ) ); ?>
			};

			var btnDry  = document.getElementById('oc-geobf-dry');
			var btnExec = document.getElementById('oc-geobf-exec');
			var wrap    = document.getElementById('oc-geobf-progress');
			var fill    = document.getElementById('oc-geobf-bar-fill');
			var status  = document.getElementById('oc-geobf-status');
			var report  = document.getElementById('oc-geobf-report');

			function renderReport(mode, t) {
				var html = '<table class="widefat striped"><tbody>';
				html += '<tr><th>Total vendors processed</th><td>' + t.scanned + '</td></tr>';
				html += '<tr><th>Matched via city table (free)</th><td class="ok">' + t.city_hit + '</td></tr>';
				html += '<tr><th>Matched via region centroid (free)</th><td class="ok">' + t.region_hit + '</td></tr>';
				html += '<tr><th>Would need geocoding API</th><td class="' + (t.geocode_needed ? 'warn' : 'ok') + '">' + t.geocode_needed + (t.geocoding_available ? ' (API key configured)' : ' (no API key — these stay uncovered)') + '</td></tr>';
				html += '<tr><th>No location set</th><td>' + t.no_location + '</td></tr>';
				if (mode === 'execute') {
					html += '<tr><th>Coordinates written</th><td class="ok">' + t.written + '</td></tr>';
				}
				html += '</tbody></table>';
				report.innerHTML = html;
			}

			function run(mode) {
				btnDry.disabled = btnExec.disabled = true;
				wrap.hidden = false;
				fill.style.width = '0%';
				status.textContent = i18n.running;
				report.innerHTML = '';

				var totals = { scanned: 0, city_hit: 0, region_hit: 0, geocode_needed: 0, no_location: 0, written: 0, geocoding_available: 0 };

				(function step(offset) {
					var body = new URLSearchParams();
					body.set('action', action);
					body.set('_ajax_nonce', nonce);
					body.set('mode', mode);
					body.set('offset', String(offset));

					fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
						.then(function (r) { return r.json(); })
						.then(function (res) {
							if (!res || !res.success) { throw new Error('ajax'); }
							var d = res.data;
							Object.keys(totals).forEach(function (k) {
								if (k === 'geocoding_available') { totals[k] = d.stats[k]; return; }
								totals[k] += d.stats[k] || 0;
							});
							var pct = d.total ? Math.min(100, Math.round(100 * Math.min(d.next_offset, d.total) / d.total)) : 100;
							fill.style.width = pct + '%';
							status.textContent = i18n.running + ' ' + Math.min(d.next_offset, d.total) + ' / ' + d.total;
							if (d.done) {
								fill.style.width = '100%';
								status.textContent = (mode === 'execute') ? i18n.execDone : i18n.dryDone;
								renderReport(mode, totals);
								btnDry.disabled = btnExec.disabled = false;
							} else {
								step(d.next_offset);
							}
						})
						.catch(function () {
							status.textContent = i18n.error;
							btnDry.disabled = btnExec.disabled = false;
						});
				})(0);
			}

			btnDry.addEventListener('click', function () { run('dry'); });
			btnExec.addEventListener('click', function () {
				if (window.confirm(i18n.confirm)) { run('execute'); }
			});
		})();
		</script>
		<?php
	}
}
