<?php
/**
 * Tag Migration Tool — maps legacy vendor meta to the new tag taxonomies.
 *
 * Vendors → Tag Migration Tool. Two modes over the same batched AJAX loop
 * (200 vendors per request, so 500+ vendor sites never hit a timeout):
 *
 *  - DRY RUN: reads `_oc_cultural_specialties` + `_oc_vendor_tags` meta
 *    (legacy serialized arrays AND CSV strings), resolves every value
 *    against the `cultural_specialty` / `vendor_tag` taxonomies and reports
 *    totals + every unmapped value. Writes nothing.
 *  - EXECUTE: same resolution, then wp_set_object_terms(…, append) per
 *    vendor. Append-mode is idempotent (re-runs never duplicate) and the
 *    legacy meta is deliberately KEPT — instant rollback safety.
 *
 * Mapping rules: cultural meta stores slugs (african, …) → matched by term
 * slug, falling back to term name. Vendor-tag meta stores display labels
 * ("African Events") → matched against CHILD term names only (decoded,
 * case-insensitive), never group names — "Decor & Styling" (tag) must not
 * resolve to the "Decor & styling" group — with a slugified fallback.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Tag_Migration {

	const PAGE  = 'oc-tag-migration';
	const AJAX  = 'oc_tag_migration';
	const NONCE = 'oc_tag_migration';
	const BATCH = 200;

	public function register() {
		add_action( 'admin_menu', [ $this, 'menu' ], 30 );
		add_action( 'wp_ajax_' . self::AJAX, [ $this, 'ajax' ] );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Tag Migration Tool', 'owambe-connect-core' ),
			__( 'Tag Migration', 'owambe-connect-core' ),
			'manage_options',
			self::PAGE,
			[ $this, 'render' ]
		);
	}

	/* ── AJAX ─────────────────────────────────────────────────────────── */

	public function ajax() {
		check_ajax_referer( self::NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}

		$mode    = isset( $_POST['mode'] ) && 'execute' === $_POST['mode'] ? 'execute' : 'dry';
		$offset  = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;

		$ids = get_posts( [
			'post_type'      => OC_CPT,
			// Explicit status list: 'any' skips oc_pending/oc_rejected
			// (registered exclude_from_search) and would silently strand
			// unapproved vendors' tags.
			'post_status'    => self::statuses(),
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'offset'         => $offset,
			'numberposts'    => self::BATCH,
			'fields'         => 'ids',
			'no_found_rows'  => true,
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

	private static function total_vendors() {
		$counts = (array) wp_count_posts( OC_CPT );
		$counts = array_intersect_key( $counts, array_flip( self::statuses() ) );
		return array_sum( array_map( 'intval', $counts ) );
	}

	/** Every registered status except trash/auto-draft/inherit. */
	private static function statuses() {
		return array_values( array_diff(
			array_keys( get_post_stati() ),
			[ 'trash', 'auto-draft', 'inherit' ]
		) );
	}

	/* ── Core mapping ─────────────────────────────────────────────────── */

	/**
	 * Analyze (and optionally migrate) one batch of vendors.
	 *
	 * @param int[] $ids     Vendor post IDs.
	 * @param bool  $execute True = assign terms; false = dry run, no writes.
	 * @return array Batch stats (summed client-side across batches).
	 */
	public static function process( array $ids, $execute = false ) {
		$lookup = self::lookups();
		$stats  = [
			'scanned' => 0,
			'culture' => [ 'vendors' => 0, 'mapped' => 0, 'unmapped' => [] ],
			'tags'    => [ 'vendors' => 0, 'mapped' => 0, 'unmapped' => [] ],
			'assigned_culture' => 0,
			'assigned_tags'    => 0,
		];

		foreach ( $ids as $id ) {
			$stats['scanned']++;

			// ── Cultural specialties (slugs) ─────────────────────────
			$values = self::parse_values( get_post_meta( $id, '_oc_cultural_specialties', true ) );
			if ( $values ) {
				$stats['culture']['vendors']++;
				$term_ids = [];
				foreach ( $values as $v ) {
					$key = sanitize_title( $v );
					if ( isset( $lookup['culture_slug'][ $key ] ) ) {
						$term_ids[] = $lookup['culture_slug'][ $key ];
					} elseif ( isset( $lookup['culture_name'][ self::norm( $v ) ] ) ) {
						$term_ids[] = $lookup['culture_name'][ self::norm( $v ) ];
					} else {
						$stats['culture']['unmapped'][ $v ] = ( $stats['culture']['unmapped'][ $v ] ?? 0 ) + 1;
						continue;
					}
					$stats['culture']['mapped']++;
				}
				if ( $execute && $term_ids ) {
					$set = wp_set_object_terms( $id, array_values( array_unique( $term_ids ) ), OC_Vendor_Tags::TAX_CULTURE, true );
					if ( ! is_wp_error( $set ) ) {
						$stats['assigned_culture'] += count( $term_ids );
					}
				}
			}

			// ── Vendor tags (display labels) ─────────────────────────
			$values = self::parse_values( get_post_meta( $id, '_oc_vendor_tags', true ) );
			if ( $values ) {
				$stats['tags']['vendors']++;
				$term_ids = [];
				foreach ( $values as $v ) {
					if ( isset( $lookup['tag_name'][ self::norm( $v ) ] ) ) {
						$term_ids[] = $lookup['tag_name'][ self::norm( $v ) ];
					} elseif ( isset( $lookup['tag_slug'][ sanitize_title( $v ) ] ) ) {
						$term_ids[] = $lookup['tag_slug'][ sanitize_title( $v ) ];
					} else {
						$stats['tags']['unmapped'][ $v ] = ( $stats['tags']['unmapped'][ $v ] ?? 0 ) + 1;
						continue;
					}
					$stats['tags']['mapped']++;
				}
				if ( $execute && $term_ids ) {
					$set = wp_set_object_terms( $id, array_values( array_unique( $term_ids ) ), OC_Vendor_Tags::TAX_TAG, true );
					if ( ! is_wp_error( $set ) ) {
						$stats['assigned_tags'] += count( $term_ids );
					}
				}
			}
		}

		return $stats;
	}

	/**
	 * Legacy meta holds either a serialized array (live data) or a CSV
	 * string (early format). Return a clean flat list of strings.
	 */
	public static function parse_values( $raw ) {
		if ( is_array( $raw ) ) {
			$list = $raw;
		} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$list = explode( ',', $raw );
		} else {
			return [];
		}
		$out = [];
		foreach ( $list as $item ) {
			if ( is_scalar( $item ) ) {
				$item = trim( (string) $item );
				if ( '' !== $item ) {
					$out[] = $item;
				}
			}
		}
		return $out;
	}

	/** Case/space/entity-insensitive comparison key. */
	private static function norm( $value ) {
		return mb_strtolower( trim( wp_specialchars_decode( (string) $value, ENT_QUOTES ) ) );
	}

	/**
	 * Term lookup tables, built once per request.
	 * Vendor-tag name/slug tables contain CHILD terms only — labels must
	 * never resolve to a group ("Decor & Styling" tag vs group, see header).
	 */
	private static function lookups() {
		static $lookup = null;
		if ( null !== $lookup ) {
			return $lookup;
		}
		$lookup = [ 'culture_slug' => [], 'culture_name' => [], 'tag_name' => [], 'tag_slug' => [] ];

		$terms = get_terms( [ 'taxonomy' => OC_Vendor_Tags::TAX_CULTURE, 'hide_empty' => false ] );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$lookup['culture_slug'][ $t->slug ]            = (int) $t->term_id;
				$lookup['culture_name'][ self::norm( $t->name ) ] = (int) $t->term_id;
			}
		}

		$terms = get_terms( [ 'taxonomy' => OC_Vendor_Tags::TAX_TAG, 'hide_empty' => false ] );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( 0 === (int) $t->parent ) {
					continue; // groups are containers, never assignment targets.
				}
				$lookup['tag_name'][ self::norm( $t->name ) ] = (int) $t->term_id;
				$lookup['tag_slug'][ $t->slug ]               = (int) $t->term_id;
			}
		}

		return $lookup;
	}

	/* ── Page ─────────────────────────────────────────────────────────── */

	public function render() {
		$nonce = wp_create_nonce( self::NONCE );
		?>
		<div class="wrap oc-tagmig">
			<h1><?php esc_html_e( 'Tag Migration Tool', 'owambe-connect-core' ); ?></h1>
			<p class="oc-tagmig__intro">
				<?php esc_html_e( 'Maps each vendor\'s legacy cultural-specialty and vendor-tag meta onto the new taxonomies. Always run the dry-run first — it writes nothing and shows exactly what the migration will do. Execute appends terms and keeps the legacy meta untouched, so it is safe to re-run.', 'owambe-connect-core' ); ?>
			</p>

			<div class="oc-tagmig__actions">
				<button type="button" class="button button-secondary" id="oc-tagmig-dry"><?php esc_html_e( 'Run Dry-Run Analysis', 'owambe-connect-core' ); ?></button>
				<button type="button" class="button button-primary" id="oc-tagmig-exec"><?php esc_html_e( 'Execute Migration', 'owambe-connect-core' ); ?></button>
			</div>

			<div class="oc-tagmig__progress" id="oc-tagmig-progress" hidden>
				<div class="oc-tagmig__bar"><span id="oc-tagmig-bar-fill"></span></div>
				<p id="oc-tagmig-status"></p>
			</div>

			<div id="oc-tagmig-report"></div>
		</div>

		<style>
			.oc-tagmig__intro { max-width: 720px; }
			.oc-tagmig__actions { display: flex; gap: 10px; margin: 16px 0; }
			.oc-tagmig__bar { width: 420px; max-width: 100%; height: 12px; background: #dcdcde; border-radius: 6px; overflow: hidden; }
			.oc-tagmig__bar span { display: block; height: 100%; width: 0; background: #6E0F2C; border-radius: 6px; transition: width .2s ease; }
			.oc-tagmig table.widefat { max-width: 720px; margin-top: 16px; }
			.oc-tagmig .oc-tagmig__ok   { color: #2E7D52; font-weight: 600; }
			.oc-tagmig .oc-tagmig__warn { color: #B32D2E; font-weight: 600; }
			.oc-tagmig__unmapped { max-width: 720px; background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #B32D2E; padding: 10px 14px; margin-top: 12px; }
		</style>

		<script>
		(function () {
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
			var i18n    = {
				running:  <?php echo wp_json_encode( __( 'Processing vendors…', 'owambe-connect-core' ) ); ?>,
				dryDone:  <?php echo wp_json_encode( __( 'Dry run complete — nothing was written.', 'owambe-connect-core' ) ); ?>,
				execDone: <?php echo wp_json_encode( __( 'Migration complete. Legacy meta was kept.', 'owambe-connect-core' ) ); ?>,
				confirm:  <?php echo wp_json_encode( __( 'Execute the migration now? Terms are appended and legacy meta is kept. Run the dry-run first if you have not.', 'owambe-connect-core' ) ); ?>,
				error:    <?php echo wp_json_encode( __( 'Request failed — check the console and try again.', 'owambe-connect-core' ) ); ?>
			};

			var btnDry  = document.getElementById('oc-tagmig-dry');
			var btnExec = document.getElementById('oc-tagmig-exec');
			var wrap    = document.getElementById('oc-tagmig-progress');
			var fill    = document.getElementById('oc-tagmig-bar-fill');
			var status  = document.getElementById('oc-tagmig-status');
			var report  = document.getElementById('oc-tagmig-report');

			function sumInto(total, batch) {
				total.scanned += batch.scanned;
				total.assigned_culture += batch.assigned_culture;
				total.assigned_tags += batch.assigned_tags;
				['culture', 'tags'].forEach(function (k) {
					total[k].vendors += batch[k].vendors;
					total[k].mapped  += batch[k].mapped;
					Object.keys(batch[k].unmapped || {}).forEach(function (val) {
						total[k].unmapped[val] = (total[k].unmapped[val] || 0) + batch[k].unmapped[val];
					});
				});
			}

			function esc(s) {
				var d = document.createElement('span');
				d.textContent = String(s);
				return d.innerHTML;
			}

			function renderReport(mode, t) {
				var unCulture = Object.keys(t.culture.unmapped);
				var unTags    = Object.keys(t.tags.unmapped);
				var html = '<table class="widefat striped"><tbody>';
				html += '<tr><th>Total vendors processed</th><td>' + t.scanned + '</td></tr>';
				html += '<tr><th>Vendors with cultural specialties</th><td>' + t.culture.vendors + '</td></tr>';
				html += '<tr><th>Cultural values mapped</th><td class="oc-tagmig__ok">' + t.culture.mapped + '</td></tr>';
				html += '<tr><th>Vendors with vendor tags</th><td>' + t.tags.vendors + '</td></tr>';
				html += '<tr><th>Tag values mapped</th><td class="oc-tagmig__ok">' + t.tags.mapped + '</td></tr>';
				if (mode === 'execute') {
					html += '<tr><th>Culture terms assigned</th><td>' + t.assigned_culture + '</td></tr>';
					html += '<tr><th>Vendor-tag terms assigned</th><td>' + t.assigned_tags + '</td></tr>';
				}
				var unCount = unCulture.length + unTags.length;
				html += '<tr><th>Unmapped values</th><td class="' + (unCount ? 'oc-tagmig__warn' : 'oc-tagmig__ok') + '">' + unCount + '</td></tr>';
				html += '</tbody></table>';

				[[unCulture, t.culture.unmapped, 'Cultural specialties'], [unTags, t.tags.unmapped, 'Vendor tags']].forEach(function (row) {
					if (!row[0].length) { return; }
					html += '<div class="oc-tagmig__unmapped"><strong>' + esc(row[2]) + ' — unmapped:</strong><ul>';
					row[0].forEach(function (val) {
						html += '<li><code>' + esc(val) + '</code> — ' + row[1][val] + ' vendor(s)</li>';
					});
					html += '</ul></div>';
				});
				report.innerHTML = html;
			}

			function run(mode) {
				btnDry.disabled = btnExec.disabled = true;
				wrap.hidden = false;
				fill.style.width = '0%';
				status.textContent = i18n.running;
				report.innerHTML = '';

				var totals = {
					scanned: 0, assigned_culture: 0, assigned_tags: 0,
					culture: { vendors: 0, mapped: 0, unmapped: {} },
					tags:    { vendors: 0, mapped: 0, unmapped: {} }
				};

				(function step(offset) {
					var body = new URLSearchParams();
					body.set('action', <?php echo wp_json_encode( self::AJAX ); ?>);
					body.set('_ajax_nonce', nonce);
					body.set('mode', mode);
					body.set('offset', String(offset));

					fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
						.then(function (r) { return r.json(); })
						.then(function (res) {
							if (!res || !res.success) { throw new Error('ajax'); }
							var d = res.data;
							sumInto(totals, d.stats);
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
