<?php
/**
 * Gift registry — Part A (external registries).
 *
 * A client adds repeatable rows (a registry name + URL) on their event editor.
 * On the event page each becomes a "View Registry" button. When the URL's host
 * matches an APPROVED shop that has an affiliate param + code configured
 * (oc_registry_shops, managed in admin — Part-B/admin UI lands later), the
 * affiliate parameter is appended SERVER-SIDE, so a purchase can earn passive
 * income. Owambe never holds funds — the shop handles everything.
 *
 * Two gates: a global master toggle (registry_part_a_enabled) and a per-event
 * toggle (_oc_event_registry_a_enabled). The section shows on the event page
 * only when BOTH are on.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Registry {

	const META_ENABLED_A = '_oc_event_registry_a_enabled';
	const META_ROWS_A    = '_oc_event_registry_a';
	const MAX_ROWS       = 20;

	public function register() {
		// Persist registry data when the event editor saves (nonce already
		// verified by OC_Event_Editor::handle_save before this fires).
		add_action( 'oc_event_saved', [ $this, 'save_event' ] );

		// Editor UI — injected into the event editor via its sections hook.
		add_action( 'oc_event_editor_sections', [ $this, 'render_editor' ], 10 );

		// Front-end — rendered on the single event page between the body + RSVP.
		add_action( 'oc_event_after_content', [ $this, 'render_front' ], 10 );
	}

	// ─── Gates ──────────────────────────────────────────────────────────────

	/** Global master toggle (Settings). */
	public static function part_a_enabled() {
		return (bool) (int) oc_get_setting( 'registry_part_a_enabled', 0 );
	}

	/** Per-event toggle. */
	public static function event_enabled( $event_id ) {
		return 1 === (int) get_post_meta( (int) $event_id, self::META_ENABLED_A, true );
	}

	// ─── Approved shops + affiliate ─────────────────────────────────────────

	/**
	 * Approved shops list. Managed in admin via the oc_registry_shops option
	 * (that UI lands with the admin-retailers task); until then a filterable
	 * default seeds a few common UK stores with NO affiliate code (so nothing
	 * is appended until the admin adds their own codes).
	 *
	 * @return array<int,array{name:string,domain:string,aff_param:string,aff_code:string,visible:int}>
	 */
	public static function shops() {
		$shops = get_option( 'oc_registry_shops', [] );
		if ( ! is_array( $shops ) || empty( $shops ) ) {
			$shops = [
				[ 'name' => 'Amazon',      'domain' => 'amazon.co.uk',    'aff_param' => 'tag',      'aff_code' => '', 'visible' => 1 ],
				[ 'name' => 'John Lewis',  'domain' => 'johnlewis.com',   'aff_param' => '',         'aff_code' => '', 'visible' => 1 ],
				[ 'name' => 'Prezola',     'domain' => 'prezola.com',     'aff_param' => '',         'aff_code' => '', 'visible' => 1 ],
			];
		}
		return (array) apply_filters( 'oc_registry_shops', $shops );
	}

	/**
	 * Append the affiliate param SERVER-SIDE when a URL's host matches an
	 * approved, visible shop that has both a param and a code. Otherwise the URL
	 * is returned untouched. Always returns an escaped URL.
	 *
	 * @param string $url
	 * @return string
	 */
	public static function affiliate_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return esc_url_raw( $url );
		}
		$host = strtolower( preg_replace( '/^www\./', '', $host ) );

		foreach ( self::shops() as $shop ) {
			if ( empty( $shop['visible'] ) ) {
				continue;
			}
			$domain = strtolower( preg_replace( '/^www\./', '', (string) ( $shop['domain'] ?? '' ) ) );
			if ( '' === $domain ) {
				continue;
			}
			$is_match = ( $host === $domain ) || ( substr( $host, - ( strlen( $domain ) + 1 ) ) === '.' . $domain );
			if ( ! $is_match ) {
				continue;
			}
			$param = (string) ( $shop['aff_param'] ?? '' );
			$code  = (string) ( $shop['aff_code'] ?? '' );
			if ( '' !== $param && '' !== $code ) {
				$url = add_query_arg( [ $param => $code ], $url );
			}
			break;
		}
		return esc_url_raw( $url );
	}

	// ─── Per-event rows ─────────────────────────────────────────────────────

	/** @return array<int,array{label:string,url:string}> */
	public static function rows( $event_id ) {
		$rows = get_post_meta( (int) $event_id, self::META_ROWS_A, true );
		if ( ! is_array( $rows ) ) {
			return [];
		}
		$out = [];
		foreach ( $rows as $r ) {
			$url = isset( $r['url'] ) ? (string) $r['url'] : '';
			if ( '' === $url ) {
				continue;
			}
			$out[] = [
				'label' => isset( $r['label'] ) ? (string) $r['label'] : '',
				'url'   => $url,
			];
		}
		return $out;
	}

	/** Save the registry rows + toggle from the editor POST. */
	public function save_event( $event_id ) {
		// Only touch registry data when the editor actually rendered the section
		// (global toggle on) — otherwise leave existing data alone.
		if ( empty( $_POST['registry_a_present'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		update_post_meta( $event_id, self::META_ENABLED_A, empty( $_POST['registry_a_enabled'] ) ? 0 : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$rows = [];
		if ( isset( $_POST['registry_a'] ) && is_array( $_POST['registry_a'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			foreach ( wp_unslash( $_POST['registry_a'] ) as $r ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( ! is_array( $r ) ) {
					continue;
				}
				$url = esc_url_raw( trim( (string) ( $r['url'] ?? '' ) ) );
				if ( '' === $url ) {
					continue; // skip blank rows.
				}
				$rows[] = [
					'label' => sanitize_text_field( (string) ( $r['label'] ?? '' ) ),
					'url'   => $url,
				];
				if ( count( $rows ) >= self::MAX_ROWS ) {
					break;
				}
			}
		}
		update_post_meta( $event_id, self::META_ROWS_A, $rows );
	}

	// ─── Editor UI ──────────────────────────────────────────────────────────

	/** Render the registry section inside the event editor. */
	public function render_editor( $event ) {
		if ( ! self::part_a_enabled() ) {
			return; // hidden entirely while the global toggle is off.
		}
		$event_id = ( $event instanceof WP_Post ) ? (int) $event->ID : 0;
		$enabled  = $event_id ? self::event_enabled( $event_id ) : false;
		$rows     = $event_id ? self::rows( $event_id ) : [];
		if ( empty( $rows ) ) {
			$rows = [ [ 'label' => '', 'url' => '' ] ]; // one empty starter row.
		}
		?>
		<div class="oc-eved__section oc-reg">
			<h2 class="oc-eved__section-title"><?php esc_html_e( 'Gift registry', 'owambe-connect-core' ); ?></h2>
			<input type="hidden" name="registry_a_present" value="1" />
			<label class="oc-reg__toggle">
				<input type="checkbox" name="registry_a_enabled" value="1" <?php checked( $enabled ); ?> />
				<span><?php esc_html_e( 'Show gift registries on my event page', 'owambe-connect-core' ); ?></span>
			</label>
			<p class="oc-reg__hint"><?php esc_html_e( 'Add a registry you created on another shop (Amazon, John Lewis, Prezola…). Guests get a “View Registry” button.', 'owambe-connect-core' ); ?></p>

			<div class="oc-reg__rows" data-oc-reg-rows>
				<?php $i = 0; foreach ( $rows as $r ) : ?>
					<div class="oc-reg__row">
						<input type="text" name="registry_a[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $r['label'] ); ?>" placeholder="<?php esc_attr_e( 'Registry name (e.g. Our John Lewis List)', 'owambe-connect-core' ); ?>" />
						<input type="url" name="registry_a[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $r['url'] ); ?>" placeholder="https://…" />
						<button type="button" class="oc-reg__remove" data-oc-reg-remove aria-label="<?php esc_attr_e( 'Remove', 'owambe-connect-core' ); ?>">&times;</button>
					</div>
					<?php $i++; endforeach; ?>
			</div>
			<button type="button" class="oc-reg__add" data-oc-reg-add data-next="<?php echo (int) $i; ?>"><?php esc_html_e( '+ Add a registry', 'owambe-connect-core' ); ?></button>
		</div>
		<style>
			.oc-reg__toggle{display:flex;align-items:center;gap:9px;font-size:14px;color:#3A3330;font-weight:600;cursor:pointer;margin:0 0 6px;}
			.oc-reg__toggle input{width:16px;height:16px;accent-color:#6E0F2C;}
			.oc-reg__hint{margin:0 0 14px;color:#6B6361;font-size:13.5px;line-height:1.5;}
			.oc-reg__rows{display:flex;flex-direction:column;gap:10px;}
			.oc-reg__row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
			.oc-reg__row input{flex:1 1 200px;box-sizing:border-box;padding:11px 13px;font-size:14.5px;color:#1F1B1A;background:#FAF8F5;border:1px solid var(--oc-border,#E4DDD2);border-radius:10px;}
			.oc-reg__row input:focus{outline:none;background:#fff;border-color:#6E0F2C;box-shadow:0 0 0 3px rgba(110,15,44,.10);}
			.oc-reg__remove{flex:0 0 auto;width:34px;height:34px;border:0;border-radius:9px;background:#f3e9ea;color:#8a2b3a;font-size:18px;cursor:pointer;transition:background .15s;}
			.oc-reg__remove:hover{background:#efd9dd;}
			.oc-reg__add{align-self:flex-start;margin-top:12px;display:inline-flex;align-items:center;padding:9px 16px;border:1px dashed rgba(110,15,44,.4);border-radius:10px;background:rgba(110,15,44,.03);color:#6E0F2C;font-size:13.5px;font-weight:600;cursor:pointer;}
			.oc-reg__add:hover{background:rgba(110,15,44,.07);border-color:#6E0F2C;}
		</style>
		<script>
		( function () {
			var wrap = document.querySelector( '[data-oc-reg-rows]' );
			var add  = document.querySelector( '[data-oc-reg-add]' );
			if ( ! wrap || ! add ) { return; }
			// Delegated remove (keep at least one row).
			wrap.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest ? e.target.closest( '[data-oc-reg-remove]' ) : null;
				if ( ! btn ) { return; }
				var rows = wrap.querySelectorAll( '.oc-reg__row' );
				if ( rows.length <= 1 ) {
					btn.closest( '.oc-reg__row' ).querySelectorAll( 'input' ).forEach( function ( i ) { i.value = ''; } );
					return;
				}
				btn.closest( '.oc-reg__row' ).remove();
			} );
			add.addEventListener( 'click', function () {
				var n = parseInt( add.getAttribute( 'data-next' ), 10 ) || 0;
				add.setAttribute( 'data-next', n + 1 );
				var row = document.createElement( 'div' );
				row.className = 'oc-reg__row';
				row.innerHTML =
					'<input type="text" name="registry_a[' + n + '][label]" placeholder="<?php echo esc_js( __( 'Registry name (e.g. Our John Lewis List)', 'owambe-connect-core' ) ); ?>" />' +
					'<input type="url" name="registry_a[' + n + '][url]" placeholder="https://…" />' +
					'<button type="button" class="oc-reg__remove" data-oc-reg-remove aria-label="Remove">&times;</button>';
				wrap.appendChild( row );
			} );
		} )();
		</script>
		<?php
	}

	// ─── Front-end ──────────────────────────────────────────────────────────

	/** Render the "View Registry" buttons on the single event page. */
	public function render_front( $event_id ) {
		$event_id = (int) $event_id;
		if ( ! self::part_a_enabled() || ! self::event_enabled( $event_id ) ) {
			return;
		}
		$rows = self::rows( $event_id );
		if ( empty( $rows ) ) {
			return;
		}
		?>
		<section class="oc-event__registry" aria-label="<?php esc_attr_e( 'Gift registry', 'owambe-connect-core' ); ?>">
			<h2 class="oc-event__registry-title"><?php esc_html_e( 'Gift registry', 'owambe-connect-core' ); ?></h2>
			<div class="oc-event__registry-list">
				<?php foreach ( $rows as $r ) :
					$href  = self::affiliate_url( $r['url'] );
					$label = '' !== trim( $r['label'] ) ? $r['label'] : __( 'Gift registry', 'owambe-connect-core' );
					if ( '' === $href ) {
						continue;
					}
					?>
					<a class="oc-event__registry-item" href="<?php echo esc_url( $href ); ?>" target="_blank" rel="noopener nofollow sponsored">
						<span class="oc-event__registry-name"><?php echo esc_html( $label ); ?></span>
						<span class="oc-event__registry-cta"><?php esc_html_e( 'View Registry', 'owambe-connect-core' ); ?> &rarr;</span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
		<style>
			.oc-event__registry{max-width:650px;margin:clamp(1.75rem,4vw,2.5rem) auto 0;text-align:center;}
			.oc-event__registry-title{color:#581825;font-size:clamp(1.4rem,3vw,1.8rem);font-weight:800;margin:0 0 1rem;}
			.oc-event__registry-list{display:flex;flex-direction:column;gap:12px;}
			.oc-event__registry-item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;background:#fff;border:1px solid #ECE4E6;border-left:3px solid #581825;border-radius:12px;text-decoration:none;transition:box-shadow .15s,transform .05s;box-shadow:0 4px 14px rgba(88,24,37,.05);}
			.oc-event__registry-item:hover{box-shadow:0 8px 20px rgba(88,24,37,.10);}
			.oc-event__registry-name{font-weight:700;color:#581825;text-align:left;}
			.oc-event__registry-cta{flex:0 0 auto;font-weight:600;font-size:13.5px;color:#8a5a66;white-space:nowrap;}
		</style>
		<?php
	}
}
