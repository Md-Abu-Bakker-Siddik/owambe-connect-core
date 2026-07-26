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

	// Part B — the Owambe-hosted registry (items, funds + the client's own
	// payment details). Owambe never holds or processes any money.
	const META_ENABLED_B = '_oc_event_registry_b_enabled';
	const META_ITEMS_B   = '_oc_event_registry_b';
	const META_PAY       = '_oc_event_pay_methods';
	const META_PAY_REF   = '_oc_event_pay_reference';
	const META_WHATSAPP  = '_oc_event_pay_whatsapp';
	const META_ADDRESS   = '_oc_event_delivery_address';
	const MAX_ITEMS      = 40;
	const OG_TTL         = 86400; // DAY_IN_SECONDS — OpenGraph scrape cache.

	public function register() {
		// Persist registry data when the event editor saves (nonce already
		// verified by OC_Event_Editor::handle_save before this fires).
		add_action( 'oc_event_saved', [ $this, 'save_event' ] );

		// Editor UI — injected into the event editor via its sections hook.
		add_action( 'oc_event_editor_sections', [ $this, 'render_editor' ], 10 );

		// Front-end — rendered on the single event page between the body + RSVP.
		add_action( 'oc_event_after_content', [ $this, 'render_front' ], 10 );

		// ── Part B (Owambe registry) — save + editor + front, after Part A. ──
		add_action( 'oc_event_saved', [ $this, 'save_event_b' ], 11 );
		add_action( 'oc_event_editor_sections', [ $this, 'render_editor_b' ], 20 );
		add_action( 'oc_event_after_content', [ $this, 'render_front_b' ], 20 );

		// AJAX — pull OpenGraph details for an approved product URL (editor helper).
		add_action( 'wp_ajax_oc_registry_og', [ $this, 'ajax_og' ] );
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
		$shop = self::matched_shop( $url );
		if ( $shop ) {
			$param = (string) ( $shop['aff_param'] ?? '' );
			$code  = (string) ( $shop['aff_code'] ?? '' );
			if ( '' !== $param && '' !== $code ) {
				$url = add_query_arg( [ $param => $code ], $url );
			}
		}
		return esc_url_raw( $url );
	}

	/**
	 * Return the approved, visible shop whose domain matches this URL's host
	 * (exact host or a subdomain of it), or null. Central host-matching used by
	 * both affiliate_url() and is_approved_url().
	 *
	 * @param string $url
	 * @return array|null
	 */
	protected static function matched_shop( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( ! $host ) {
			return null;
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
			if ( $host === $domain || substr( $host, - ( strlen( $domain ) + 1 ) ) === '.' . $domain ) {
				return $shop;
			}
		}
		return null;
	}

	/** True when the URL's host is an approved, visible shop. */
	public static function is_approved_url( $url ) {
		return null !== self::matched_shop( $url );
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

	// ═══ PART B · Owambe-hosted registry ══════════════════════════════════════

	/** Global master toggle (Settings). */
	public static function part_b_enabled() {
		return (bool) (int) oc_get_setting( 'registry_part_b_enabled', 0 );
	}

	/** Per-event toggle. */
	public static function event_b_enabled( $event_id ) {
		return 1 === (int) get_post_meta( (int) $event_id, self::META_ENABLED_B, true );
	}

	/** The four registry target types (value => label). */
	public static function types() {
		return [
			'gift'           => __( 'Gift (from a shop)', 'owambe-connect-core' ),
			'experience'     => __( 'Experience fund', 'owambe-connect-core' ),
			'vendor_service' => __( 'Vendor service', 'owambe-connect-core' ),
			'general'        => __( 'General gift fund', 'owambe-connect-core' ),
		];
	}

	protected static function valid_type( $t ) {
		return array_key_exists( $t, self::types() ) ? $t : 'general';
	}

	// ─── OpenGraph auto-pull (cached) ─────────────────────────────────────────

	/**
	 * Scrape og:title / og:image / price from an APPROVED product URL, cached in
	 * a transient. Returns empty strings for anything not found or not approved.
	 *
	 * @param string $url
	 * @return array{title:string,image:string,price:string}
	 */
	public static function fetch_og( $url ) {
		$url   = trim( (string) $url );
		$empty = [ 'title' => '', 'image' => '', 'price' => '' ];
		if ( '' === $url || ! self::is_approved_url( $url ) ) {
			return $empty;
		}
		$key    = 'oc_og_' . md5( $url );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return array_merge( $empty, $cached );
		}
		$res  = wp_remote_get( $url, [
			'timeout'     => 8,
			'redirection' => 3,
			'user-agent'  => 'Mozilla/5.0 (compatible; OwambeConnect/1.0; +https://owambeconnect.com)',
			'headers'     => [ 'Accept' => 'text/html' ],
		] );
		$data = $empty;
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
			$html          = substr( (string) wp_remote_retrieve_body( $res ), 0, 200000 );
			$data['image'] = self::og_tag( $html, 'og:image' );
			$data['title'] = self::og_tag( $html, 'og:title' );
			if ( '' === $data['title'] && preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $m ) ) {
				$data['title'] = trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES ) );
			}
			$price = self::og_tag( $html, 'product:price:amount' );
			if ( '' === $price ) {
				$price = self::og_tag( $html, 'og:price:amount' );
			}
			$data['price'] = $price;
		}
		set_transient( $key, $data, self::OG_TTL );
		return $data;
	}

	/** Read a single <meta property|name="$prop" content="…"> value (either attr order). */
	protected static function og_tag( $html, $prop ) {
		$p = preg_quote( $prop, '/' );
		if ( preg_match( '/<meta[^>]+(?:property|name)=["\']' . $p . '["\'][^>]*content=["\'](.*?)["\']/is', $html, $m ) ) {
			return trim( html_entity_decode( $m[1], ENT_QUOTES ) );
		}
		if ( preg_match( '/<meta[^>]+content=["\'](.*?)["\'][^>]*(?:property|name)=["\']' . $p . '["\']/is', $html, $m ) ) {
			return trim( html_entity_decode( $m[1], ENT_QUOTES ) );
		}
		return '';
	}

	/** AJAX: return OG details for an approved product URL (event editor helper). */
	public function ajax_og() {
		if ( ! is_user_logged_in() || ! check_ajax_referer( 'oc_registry_og', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Session expired — reload the page and try again.', 'owambe-connect-core' ) ], 403 );
		}
		$url = isset( $_POST['url'] ) ? esc_url_raw( trim( (string) wp_unslash( $_POST['url'] ) ) ) : '';
		if ( '' === $url ) {
			wp_send_json_error( [ 'message' => __( 'Paste a product link first.', 'owambe-connect-core' ) ] );
		}
		if ( ! self::is_approved_url( $url ) ) {
			wp_send_json_error( [ 'message' => __( 'That shop is not on the approved list yet.', 'owambe-connect-core' ) ] );
		}
		wp_send_json_success( self::fetch_og( $url ) );
	}

	// ─── Readers ──────────────────────────────────────────────────────────────

	/** @return array<int,array<string,mixed>> */
	public static function items( $event_id ) {
		$items = get_post_meta( (int) $event_id, self::META_ITEMS_B, true );
		if ( ! is_array( $items ) ) {
			return [];
		}
		$out = [];
		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$title = isset( $it['title'] ) ? (string) $it['title'] : '';
			if ( '' === trim( $title ) ) {
				continue;
			}
			$out[] = [
				'type'   => self::valid_type( isset( $it['type'] ) ? (string) $it['type'] : 'general' ),
				'title'  => $title,
				'url'    => isset( $it['url'] ) ? (string) $it['url'] : '',
				'image'  => isset( $it['image'] ) ? (string) $it['image'] : '',
				'price'  => isset( $it['price'] ) ? (string) $it['price'] : '',
				'goal'   => isset( $it['goal'] ) ? (float) $it['goal'] : 0.0,
				'raised' => isset( $it['raised'] ) ? (float) $it['raised'] : 0.0,
				'note'   => isset( $it['note'] ) ? (string) $it['note'] : '',
			];
		}
		return $out;
	}

	/** @return array<int,array{label:string,value:string}> */
	public static function pay_methods( $event_id ) {
		$m = get_post_meta( (int) $event_id, self::META_PAY, true );
		if ( ! is_array( $m ) ) {
			return [];
		}
		$out = [];
		foreach ( $m as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$value = isset( $row['value'] ) ? (string) $row['value'] : '';
			if ( '' === trim( $value ) ) {
				continue;
			}
			$out[] = [
				'label' => isset( $row['label'] ) ? (string) $row['label'] : '',
				'value' => $value,
			];
		}
		return $out;
	}

	public static function pay_reference( $event_id ) {
		return (string) get_post_meta( (int) $event_id, self::META_PAY_REF, true );
	}

	public static function whatsapp( $event_id ) {
		return (string) get_post_meta( (int) $event_id, self::META_WHATSAPP, true );
	}

	public static function delivery_address( $event_id ) {
		return (string) get_post_meta( (int) $event_id, self::META_ADDRESS, true );
	}

	/** Digits-only helper — a display string like "£1,250.50" becomes 1250.50. */
	protected static function to_amount( $raw ) {
		return (float) preg_replace( '/[^0-9.]/', '', (string) $raw );
	}

	// ─── Save ─────────────────────────────────────────────────────────────────

	/** Persist Part B (items + payment details) from the editor POST. */
	public function save_event_b( $event_id ) {
		if ( empty( $_POST['registry_b_present'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$event_id = (int) $event_id;
		update_post_meta( $event_id, self::META_ENABLED_B, empty( $_POST['registry_b_enabled'] ) ? 0 : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Payment / contact — the client's OWN details. Owambe holds no funds.
		$methods = [];
		if ( isset( $_POST['registry_b_pay'] ) && is_array( $_POST['registry_b_pay'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			foreach ( wp_unslash( $_POST['registry_b_pay'] ) as $row ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( ! is_array( $row ) ) {
					continue;
				}
				$value = sanitize_text_field( (string) ( $row['value'] ?? '' ) );
				if ( '' === trim( $value ) ) {
					continue;
				}
				$methods[] = [
					'label' => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
					'value' => $value,
				];
				if ( count( $methods ) >= self::MAX_ROWS ) {
					break;
				}
			}
		}
		update_post_meta( $event_id, self::META_PAY, $methods );
		update_post_meta( $event_id, self::META_PAY_REF, sanitize_text_field( (string) wp_unslash( $_POST['registry_b_reference'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		// WhatsApp — keep digits only (drop +, spaces, brackets).
		$wa = preg_replace( '/[^0-9]/', '', (string) wp_unslash( $_POST['registry_b_whatsapp'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_post_meta( $event_id, self::META_WHATSAPP, $wa );
		update_post_meta( $event_id, self::META_ADDRESS, sanitize_textarea_field( (string) wp_unslash( $_POST['registry_b_address'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Items.
		$items = [];
		if ( isset( $_POST['registry_b_item'] ) && is_array( $_POST['registry_b_item'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			foreach ( wp_unslash( $_POST['registry_b_item'] ) as $it ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( ! is_array( $it ) ) {
					continue;
				}
				$type  = self::valid_type( sanitize_key( (string) ( $it['type'] ?? 'general' ) ) );
				$title = sanitize_text_field( (string) ( $it['title'] ?? '' ) );
				$url   = esc_url_raw( trim( (string) ( $it['url'] ?? '' ) ) );
				$image = esc_url_raw( trim( (string) ( $it['image'] ?? '' ) ) );
				$price = sanitize_text_field( (string) ( $it['price'] ?? '' ) );

				// Gifts point at a shop URL — reject anything not on the approved
				// list so the page never hosts arbitrary outbound links.
				if ( 'gift' === $type && '' !== $url && ! self::is_approved_url( $url ) ) {
					$url = '';
				}
				// Server-side OG auto-pull (cached) — fill any blank display field.
				if ( 'gift' === $type && '' !== $url && ( '' === $title || '' === $image || '' === $price ) ) {
					$og = self::fetch_og( $url );
					if ( '' === $title ) {
						$title = sanitize_text_field( $og['title'] );
					}
					if ( '' === $image ) {
						$image = esc_url_raw( $og['image'] );
					}
					if ( '' === $price ) {
						$price = sanitize_text_field( $og['price'] );
					}
				}
				if ( '' === trim( $title ) ) {
					continue; // an item needs at least a title.
				}
				$items[] = [
					'type'   => $type,
					'title'  => $title,
					'url'    => ( 'gift' === $type ) ? $url : '',
					'image'  => $image,
					'price'  => $price,
					'goal'   => self::to_amount( $it['goal'] ?? '' ),
					'raised' => self::to_amount( $it['raised'] ?? '' ),
					'note'   => sanitize_text_field( (string) ( $it['note'] ?? '' ) ),
				];
				if ( count( $items ) >= self::MAX_ITEMS ) {
					break;
				}
			}
		}
		update_post_meta( $event_id, self::META_ITEMS_B, $items );
	}

	// ─── Editor UI ────────────────────────────────────────────────────────────

	/** Render the Part B editor section inside the event editor. */
	public function render_editor_b( $event ) {
		if ( ! self::part_b_enabled() ) {
			return;
		}
		$event_id = ( $event instanceof WP_Post ) ? (int) $event->ID : 0;
		$enabled  = $event_id ? self::event_b_enabled( $event_id ) : false;
		$methods  = $event_id ? self::pay_methods( $event_id ) : [];
		$items    = $event_id ? self::items( $event_id ) : [];
		$ref      = $event_id ? self::pay_reference( $event_id ) : '';
		$wa       = $event_id ? self::whatsapp( $event_id ) : '';
		$address  = $event_id ? self::delivery_address( $event_id ) : '';
		if ( empty( $methods ) ) {
			$methods = [ [ 'label' => '', 'value' => '' ] ];
		}
		if ( empty( $items ) ) {
			$items = [ [ 'type' => 'gift', 'title' => '', 'url' => '', 'image' => '', 'price' => '', 'goal' => 0, 'raised' => 0, 'note' => '' ] ];
		}
		$types = self::types();
		?>
		<div class="oc-eved__section oc-regb">
			<h2 class="oc-eved__section-title"><?php esc_html_e( 'Owambe gift registry', 'owambe-connect-core' ); ?></h2>
			<input type="hidden" name="registry_b_present" value="1" />
			<label class="oc-reg__toggle">
				<input type="checkbox" name="registry_b_enabled" value="1" <?php checked( $enabled ); ?> />
				<span><?php esc_html_e( 'Host a gift registry on my event page', 'owambe-connect-core' ); ?></span>
			</label>
			<p class="oc-reg__hint"><?php esc_html_e( 'Add items guests can buy from an approved shop, or funds they can contribute to. All money goes straight to you — Owambe never holds or processes it.', 'owambe-connect-core' ); ?></p>

			<!-- ── How guests pay you ─────────────────────────────────────────── -->
			<div class="oc-regb__pay">
				<h3 class="oc-regb__sub"><?php esc_html_e( 'How guests send you money', 'owambe-connect-core' ); ?></h3>
				<div class="oc-regb__rows" data-oc-regb-pay>
					<?php $pi = 0; foreach ( $methods as $m ) : ?>
						<div class="oc-reg__row">
							<input type="text" name="registry_b_pay[<?php echo (int) $pi; ?>][label]" value="<?php echo esc_attr( $m['label'] ); ?>" placeholder="<?php esc_attr_e( 'Method (Bank transfer, PayPal, Monzo…)', 'owambe-connect-core' ); ?>" />
							<input type="text" name="registry_b_pay[<?php echo (int) $pi; ?>][value]" value="<?php echo esc_attr( $m['value'] ); ?>" placeholder="<?php esc_attr_e( 'Details (sort/acct, paypal.me link…)', 'owambe-connect-core' ); ?>" />
							<button type="button" class="oc-reg__remove" data-oc-regb-payremove aria-label="<?php esc_attr_e( 'Remove', 'owambe-connect-core' ); ?>">&times;</button>
						</div>
						<?php $pi++; endforeach; ?>
				</div>
				<button type="button" class="oc-reg__add" data-oc-regb-payadd data-next="<?php echo (int) $pi; ?>"><?php esc_html_e( '+ Add a payment method', 'owambe-connect-core' ); ?></button>

				<div class="oc-regb__grid2">
					<label class="oc-regb__field">
						<span><?php esc_html_e( 'Payment reference for guests (optional)', 'owambe-connect-core' ); ?></span>
						<input type="text" name="registry_b_reference" value="<?php echo esc_attr( $ref ); ?>" placeholder="<?php esc_attr_e( 'e.g. your names', 'owambe-connect-core' ); ?>" />
					</label>
					<label class="oc-regb__field">
						<span><?php esc_html_e( 'WhatsApp number (optional)', 'owambe-connect-core' ); ?></span>
						<input type="text" name="registry_b_whatsapp" value="<?php echo esc_attr( $wa ); ?>" placeholder="<?php esc_attr_e( 'e.g. 447123456789', 'owambe-connect-core' ); ?>" />
					</label>
				</div>
				<label class="oc-regb__field">
					<span><?php esc_html_e( 'Delivery address (only shown for posted gifts)', 'owambe-connect-core' ); ?></span>
					<textarea name="registry_b_address" rows="2" placeholder="<?php esc_attr_e( 'Where guests should ship physical gifts', 'owambe-connect-core' ); ?>"><?php echo esc_textarea( $address ); ?></textarea>
				</label>
			</div>

			<!-- ── Items ──────────────────────────────────────────────────────── -->
			<h3 class="oc-regb__sub"><?php esc_html_e( 'Registry items', 'owambe-connect-core' ); ?></h3>
			<div class="oc-regb__items" data-oc-regb-items>
				<?php $ii = 0; foreach ( $items as $it ) :
					$it_type = self::valid_type( (string) ( $it['type'] ?? 'general' ) );
					?>
					<div class="oc-regb__item" data-oc-regb-item data-type="<?php echo esc_attr( $it_type ); ?>">
						<div class="oc-regb__item-head">
							<select name="registry_b_item[<?php echo (int) $ii; ?>][type]" data-oc-regb-type>
								<?php foreach ( $types as $val => $lab ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $it_type, $val ); ?>><?php echo esc_html( $lab ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="oc-reg__remove" data-oc-regb-itemremove aria-label="<?php esc_attr_e( 'Remove item', 'owambe-connect-core' ); ?>">&times;</button>
						</div>
						<div class="oc-regb__gift" data-oc-regb-gift>
							<div class="oc-regb__urlrow">
								<input type="url" name="registry_b_item[<?php echo (int) $ii; ?>][url]" value="<?php echo esc_attr( (string) ( $it['url'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Product link from an approved shop', 'owambe-connect-core' ); ?>" data-oc-regb-url />
								<button type="button" class="oc-regb__fetch" data-oc-regb-fetch><?php esc_html_e( 'Fetch details', 'owambe-connect-core' ); ?></button>
							</div>
							<p class="oc-regb__fetchmsg" data-oc-regb-msg aria-live="polite"></p>
						</div>
						<input type="text" name="registry_b_item[<?php echo (int) $ii; ?>][title]" value="<?php echo esc_attr( (string) ( $it['title'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Item name', 'owambe-connect-core' ); ?>" data-oc-regb-title />
						<div class="oc-regb__grid2">
							<input type="url" name="registry_b_item[<?php echo (int) $ii; ?>][image]" value="<?php echo esc_attr( (string) ( $it['image'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Image URL (auto-filled)', 'owambe-connect-core' ); ?>" data-oc-regb-image />
							<input type="text" name="registry_b_item[<?php echo (int) $ii; ?>][price]" value="<?php echo esc_attr( (string) ( $it['price'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Price e.g. £120', 'owambe-connect-core' ); ?>" data-oc-regb-price />
						</div>
						<div class="oc-regb__grid2">
							<label class="oc-regb__mini"><span><?php esc_html_e( 'Goal (£)', 'owambe-connect-core' ); ?></span>
								<input type="number" step="0.01" min="0" name="registry_b_item[<?php echo (int) $ii; ?>][goal]" value="<?php echo esc_attr( (string) ( (float) ( $it['goal'] ?? 0 ) ?: '' ) ); ?>" placeholder="0" /></label>
							<label class="oc-regb__mini"><span><?php esc_html_e( 'Raised so far (£)', 'owambe-connect-core' ); ?></span>
								<input type="number" step="0.01" min="0" name="registry_b_item[<?php echo (int) $ii; ?>][raised]" value="<?php echo esc_attr( (string) ( (float) ( $it['raised'] ?? 0 ) ?: '' ) ); ?>" placeholder="0" /></label>
						</div>
						<input type="text" name="registry_b_item[<?php echo (int) $ii; ?>][note]" value="<?php echo esc_attr( (string) ( $it['note'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Short note (optional)', 'owambe-connect-core' ); ?>" />
					</div>
					<?php $ii++; endforeach; ?>
			</div>
			<button type="button" class="oc-reg__add" data-oc-regb-itemadd data-next="<?php echo (int) $ii; ?>"><?php esc_html_e( '+ Add an item', 'owambe-connect-core' ); ?></button>
		</div>
		<style>
			.oc-regb__sub{margin:22px 0 10px;font-size:13px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#6E0F2C;}
			.oc-regb__pay{margin-top:6px;padding:16px;background:#FAF6F2;border:1px solid var(--oc-border,#E4DDD2);border-radius:12px;}
			.oc-regb__pay .oc-regb__sub{margin-top:0;}
			.oc-regb__grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;}
			.oc-regb__field{display:flex;flex-direction:column;gap:5px;margin-top:12px;font-size:13px;font-weight:600;color:#5B534F;}
			.oc-regb__field input,.oc-regb__field textarea{box-sizing:border-box;padding:11px 13px;font-size:14.5px;font-weight:400;color:#1F1B1A;background:#fff;border:1px solid var(--oc-border,#E4DDD2);border-radius:10px;}
			.oc-regb__item{margin:0 0 14px;padding:14px;background:#fff;border:1px solid var(--oc-border,#E4DDD2);border-radius:12px;display:flex;flex-direction:column;gap:10px;}
			.oc-regb__item-head{display:flex;gap:10px;align-items:center;}
			.oc-regb__item-head select{flex:1 1 auto;padding:10px 40px 10px 12px;font-size:14px;color:#1F1B1A;border:1px solid var(--oc-border,#E4DDD2);border-radius:10px;cursor:pointer;-webkit-appearance:none;-moz-appearance:none;appearance:none;background-color:#FAF8F5;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1.5 6 6.5 11 1.5' fill='none' stroke='%236E0F2C' stroke-width='1.75' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;background-size:12px 8px;}
			.oc-regb__item-head select:focus{outline:none;border-color:#6E0F2C;box-shadow:0 0 0 3px rgba(110,15,44,.10);}
			.oc-regb__item input{box-sizing:border-box;padding:11px 13px;font-size:14.5px;color:#1F1B1A;background:#FAF8F5;border:1px solid var(--oc-border,#E4DDD2);border-radius:10px;width:100%;}
			.oc-regb__item input:focus,.oc-regb__field input:focus,.oc-regb__field textarea:focus{outline:none;background:#fff;border-color:#6E0F2C;box-shadow:0 0 0 3px rgba(110,15,44,.10);}
			.oc-regb__urlrow{display:flex;gap:8px;}
			.oc-regb__urlrow input{flex:1 1 auto;}
			.oc-regb__fetch{flex:0 0 auto;padding:0 14px;border:1px solid #6E0F2C;border-radius:10px;background:#fff;color:#6E0F2C;font-size:13px;font-weight:600;cursor:pointer;}
			.oc-regb__fetch:hover{background:rgba(110,15,44,.06);}
			.oc-regb__fetch[disabled]{opacity:.55;cursor:default;}
			.oc-regb__fetchmsg{margin:6px 0 0;font-size:12.5px;color:#6B6361;min-height:1px;}
			.oc-regb__mini{display:flex;flex-direction:column;gap:5px;font-size:12.5px;font-weight:600;color:#5B534F;}
			.oc-regb__grid2 .oc-regb__mini input{background:#FAF8F5;}
			/* Non-gift items hide the shop-URL row. */
			.oc-regb__item:not([data-type="gift"]) .oc-regb__gift{display:none;}
		</style>
		<script>
		( function () {
			var root = document.querySelector( '.oc-regb' );
			if ( ! root ) { return; }
			var ajaxUrl = '<?php echo esc_url_raw( admin_url( 'admin-ajax.php' ) ); ?>';
			var ogNonce = '<?php echo esc_js( wp_create_nonce( 'oc_registry_og' ) ); ?>';

			// ── Payment methods (add / remove) ──
			var payWrap = root.querySelector( '[data-oc-regb-pay]' );
			var payAdd  = root.querySelector( '[data-oc-regb-payadd]' );
			if ( payWrap && payAdd ) {
				payWrap.addEventListener( 'click', function ( e ) {
					var btn = e.target.closest ? e.target.closest( '[data-oc-regb-payremove]' ) : null;
					if ( ! btn ) { return; }
					var rows = payWrap.querySelectorAll( '.oc-reg__row' );
					if ( rows.length <= 1 ) { btn.closest( '.oc-reg__row' ).querySelectorAll( 'input' ).forEach( function ( i ) { i.value = ''; } ); return; }
					btn.closest( '.oc-reg__row' ).remove();
				} );
				payAdd.addEventListener( 'click', function () {
					var n = parseInt( payAdd.getAttribute( 'data-next' ), 10 ) || 0;
					payAdd.setAttribute( 'data-next', n + 1 );
					var row = document.createElement( 'div' );
					row.className = 'oc-reg__row';
					row.innerHTML =
						'<input type="text" name="registry_b_pay[' + n + '][label]" placeholder="<?php echo esc_js( __( 'Method (Bank transfer, PayPal, Monzo…)', 'owambe-connect-core' ) ); ?>" />' +
						'<input type="text" name="registry_b_pay[' + n + '][value]" placeholder="<?php echo esc_js( __( 'Details (sort/acct, paypal.me link…)', 'owambe-connect-core' ) ); ?>" />' +
						'<button type="button" class="oc-reg__remove" data-oc-regb-payremove aria-label="Remove">&times;</button>';
					payWrap.appendChild( row );
				} );
			}

			// ── Items (add / remove / type toggle / fetch) ──
			var itemWrap = root.querySelector( '[data-oc-regb-items]' );
			var itemAdd  = root.querySelector( '[data-oc-regb-itemadd]' );
			var types    = <?php echo wp_json_encode( $types ); ?>;

			function itemHtml( n ) {
				var opts = '';
				for ( var k in types ) { if ( types.hasOwnProperty( k ) ) { opts += '<option value="' + k + '">' + types[ k ] + '</option>'; } }
				return '<div class="oc-regb__item" data-oc-regb-item data-type="gift">' +
					'<div class="oc-regb__item-head"><select name="registry_b_item[' + n + '][type]" data-oc-regb-type>' + opts + '</select>' +
					'<button type="button" class="oc-reg__remove" data-oc-regb-itemremove aria-label="Remove">&times;</button></div>' +
					'<div class="oc-regb__gift" data-oc-regb-gift><div class="oc-regb__urlrow">' +
					'<input type="url" name="registry_b_item[' + n + '][url]" placeholder="<?php echo esc_js( __( 'Product link from an approved shop', 'owambe-connect-core' ) ); ?>" data-oc-regb-url />' +
					'<button type="button" class="oc-regb__fetch" data-oc-regb-fetch><?php echo esc_js( __( 'Fetch details', 'owambe-connect-core' ) ); ?></button></div>' +
					'<p class="oc-regb__fetchmsg" data-oc-regb-msg aria-live="polite"></p></div>' +
					'<input type="text" name="registry_b_item[' + n + '][title]" placeholder="<?php echo esc_js( __( 'Item name', 'owambe-connect-core' ) ); ?>" data-oc-regb-title />' +
					'<div class="oc-regb__grid2">' +
					'<input type="url" name="registry_b_item[' + n + '][image]" placeholder="<?php echo esc_js( __( 'Image URL (auto-filled)', 'owambe-connect-core' ) ); ?>" data-oc-regb-image />' +
					'<input type="text" name="registry_b_item[' + n + '][price]" placeholder="<?php echo esc_js( __( 'Price e.g. £120', 'owambe-connect-core' ) ); ?>" data-oc-regb-price /></div>' +
					'<div class="oc-regb__grid2">' +
					'<label class="oc-regb__mini"><span><?php echo esc_js( __( 'Goal (£)', 'owambe-connect-core' ) ); ?></span><input type="number" step="0.01" min="0" name="registry_b_item[' + n + '][goal]" placeholder="0" /></label>' +
					'<label class="oc-regb__mini"><span><?php echo esc_js( __( 'Raised so far (£)', 'owambe-connect-core' ) ); ?></span><input type="number" step="0.01" min="0" name="registry_b_item[' + n + '][raised]" placeholder="0" /></label></div>' +
					'<input type="text" name="registry_b_item[' + n + '][note]" placeholder="<?php echo esc_js( __( 'Short note (optional)', 'owambe-connect-core' ) ); ?>" /></div>';
			}

			if ( itemWrap && itemAdd ) {
				itemWrap.addEventListener( 'change', function ( e ) {
					var sel = e.target.closest ? e.target.closest( '[data-oc-regb-type]' ) : null;
					if ( ! sel ) { return; }
					var card = sel.closest( '[data-oc-regb-item]' );
					if ( card ) { card.setAttribute( 'data-type', sel.value ); }
				} );
				itemWrap.addEventListener( 'click', function ( e ) {
					var rm = e.target.closest ? e.target.closest( '[data-oc-regb-itemremove]' ) : null;
					if ( rm ) {
						var cards = itemWrap.querySelectorAll( '[data-oc-regb-item]' );
						if ( cards.length <= 1 ) { rm.closest( '[data-oc-regb-item]' ).querySelectorAll( 'input' ).forEach( function ( i ) { i.value = ''; } ); return; }
						rm.closest( '[data-oc-regb-item]' ).remove();
						return;
					}
					var fb = e.target.closest ? e.target.closest( '[data-oc-regb-fetch]' ) : null;
					if ( fb ) { fetchDetails( fb ); }
				} );
				itemAdd.addEventListener( 'click', function () {
					var n = parseInt( itemAdd.getAttribute( 'data-next' ), 10 ) || 0;
					itemAdd.setAttribute( 'data-next', n + 1 );
					var tmp = document.createElement( 'div' );
					tmp.innerHTML = itemHtml( n );
					itemWrap.appendChild( tmp.firstChild );
				} );
			}

			function fetchDetails( btn ) {
				var card = btn.closest( '[data-oc-regb-item]' );
				if ( ! card ) { return; }
				var url = ( card.querySelector( '[data-oc-regb-url]' ) || {} ).value || '';
				var msg = card.querySelector( '[data-oc-regb-msg]' );
				if ( ! url ) { if ( msg ) { msg.textContent = '<?php echo esc_js( __( 'Paste a product link first.', 'owambe-connect-core' ) ); ?>'; } return; }
				btn.disabled = true;
				if ( msg ) { msg.textContent = '<?php echo esc_js( __( 'Fetching…', 'owambe-connect-core' ) ); ?>'; }
				var body = new URLSearchParams();
				body.append( 'action', 'oc_registry_og' );
				body.append( 'nonce', ogNonce );
				body.append( 'url', url );
				fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						btn.disabled = false;
						if ( ! res || ! res.success ) { if ( msg ) { msg.textContent = ( res && res.data && res.data.message ) ? res.data.message : '<?php echo esc_js( __( 'Could not fetch details.', 'owambe-connect-core' ) ); ?>'; } return; }
						var d = res.data || {};
						var t = card.querySelector( '[data-oc-regb-title]' );
						var im = card.querySelector( '[data-oc-regb-image]' );
						var pr = card.querySelector( '[data-oc-regb-price]' );
						if ( t && ! t.value && d.title ) { t.value = d.title; }
						if ( im && ! im.value && d.image ) { im.value = d.image; }
						if ( pr && ! pr.value && d.price ) { pr.value = d.price; }
						if ( msg ) { msg.textContent = '<?php echo esc_js( __( 'Details loaded — edit anything you like.', 'owambe-connect-core' ) ); ?>'; }
					} )
					.catch( function () { btn.disabled = false; if ( msg ) { msg.textContent = '<?php echo esc_js( __( 'Could not fetch details.', 'owambe-connect-core' ) ); ?>'; } } );
			}
		} )();
		</script>
		<?php
	}

	// ─── Front-end ────────────────────────────────────────────────────────────

	/** Render the Part B registry (items + funds + contribute modal). */
	public function render_front_b( $event_id ) {
		$event_id = (int) $event_id;
		if ( ! self::part_b_enabled() || ! self::event_b_enabled( $event_id ) ) {
			return;
		}
		$items = self::items( $event_id );
		if ( empty( $items ) ) {
			return;
		}
		$methods = self::pay_methods( $event_id );
		$ref     = self::pay_reference( $event_id );
		$wa       = self::whatsapp( $event_id );
		$address  = self::delivery_address( $event_id );
		$types    = self::types();
		$cur      = static function ( $n ) {
			return '£' . number_format( (float) $n, ( fmod( (float) $n, 1 ) ? 2 : 0 ) );
		};
		?>
		<section class="oc-event__registryb" aria-label="<?php esc_attr_e( 'Owambe gift registry', 'owambe-connect-core' ); ?>">
			<h2 class="oc-event__registry-title"><?php esc_html_e( 'Gift registry', 'owambe-connect-core' ); ?></h2>
			<div class="oc-regb-grid">
				<?php foreach ( $items as $it ) :
					$is_gift = ( 'gift' === $it['type'] );
					$buy     = ( $is_gift && '' !== $it['url'] ) ? self::affiliate_url( $it['url'] ) : '';
					$goal    = (float) $it['goal'];
					$raised  = (float) $it['raised'];
					$pct     = ( $goal > 0 ) ? min( 100, round( $raised / $goal * 100 ) ) : 0;
					?>
					<article class="oc-regb-card" data-oc-regb-card data-item="<?php echo esc_attr( $it['title'] ); ?>">
						<?php if ( '' !== $it['image'] ) : ?>
							<div class="oc-regb-card__img"><img src="<?php echo esc_url( $it['image'] ); ?>" alt="" loading="lazy" /></div>
						<?php endif; ?>
						<div class="oc-regb-card__body">
							<span class="oc-regb-card__chip"><?php echo esc_html( $types[ $it['type'] ] ); ?></span>
							<h3 class="oc-regb-card__title"><?php echo esc_html( $it['title'] ); ?></h3>
							<?php if ( '' !== $it['price'] ) : ?>
								<div class="oc-regb-card__price"><?php echo esc_html( $it['price'] ); ?></div>
							<?php endif; ?>
							<?php if ( '' !== $it['note'] ) : ?>
								<p class="oc-regb-card__note"><?php echo esc_html( $it['note'] ); ?></p>
							<?php endif; ?>
							<?php if ( $goal > 0 ) : ?>
								<div class="oc-regb-card__prog">
									<div class="oc-regb-card__bar"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
									<div class="oc-regb-card__figs">
										<?php
										/* translators: 1: amount raised, 2: goal amount */
										echo esc_html( sprintf( __( '%1$s of %2$s', 'owambe-connect-core' ), $cur( $raised ), $cur( $goal ) ) );
										?>
									</div>
								</div>
							<?php endif; ?>
							<div class="oc-regb-card__actions">
								<?php if ( '' !== $buy ) : ?>
									<a class="oc-regb-btn oc-regb-btn--buy" href="<?php echo esc_url( $buy ); ?>" target="_blank" rel="noopener nofollow sponsored"><?php esc_html_e( 'Buy this gift', 'owambe-connect-core' ); ?> &rarr;</a>
								<?php endif; ?>
								<button type="button" class="oc-regb-btn oc-regb-btn--give" data-oc-regb-open data-item="<?php echo esc_attr( $it['title'] ); ?>"><?php echo '' !== $buy ? esc_html__( 'Contribute instead', 'owambe-connect-core' ) : esc_html__( 'Contribute', 'owambe-connect-core' ); ?></button>
							</div>
							<?php if ( $is_gift && '' !== $buy && '' !== trim( $address ) ) : ?>
								<details class="oc-regb-card__ship">
									<summary><?php esc_html_e( 'Buying & posting it? Delivery address', 'owambe-connect-core' ); ?></summary>
									<address><?php echo nl2br( esc_html( $address ) ); ?></address>
								</details>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

		<!-- Shared contribute modal — shows the client's OWN payment details. -->
		<div class="oc-regb-modal" data-oc-regb-modal hidden>
			<div class="oc-regb-modal__backdrop" data-oc-regb-close></div>
			<div class="oc-regb-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="oc-regb-modal-title">
				<button type="button" class="oc-regb-modal__x" data-oc-regb-close aria-label="<?php esc_attr_e( 'Close', 'owambe-connect-core' ); ?>">&times;</button>
				<h3 id="oc-regb-modal-title" class="oc-regb-modal__title"><?php esc_html_e( 'Contribute', 'owambe-connect-core' ); ?> <span data-oc-regb-modalitem></span></h3>
				<?php if ( ! empty( $methods ) ) : ?>
					<ul class="oc-regb-modal__methods">
						<?php foreach ( $methods as $m ) : ?>
							<li>
								<?php if ( '' !== trim( $m['label'] ) ) : ?><span class="oc-regb-modal__mlabel"><?php echo esc_html( $m['label'] ); ?></span><?php endif; ?>
								<span class="oc-regb-modal__mval"><?php echo esc_html( $m['value'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( '' !== trim( $ref ) ) : ?>
					<p class="oc-regb-modal__ref"><?php printf( /* translators: %s: reference */ esc_html__( 'Please use the reference: %s', 'owambe-connect-core' ), '<strong>' . esc_html( $ref ) . '</strong>' ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $wa ) : ?>
					<a class="oc-regb-btn oc-regb-btn--wa" href="https://wa.me/<?php echo esc_attr( $wa ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Message on WhatsApp', 'owambe-connect-core' ); ?></a>
				<?php endif; ?>
				<?php if ( empty( $methods ) && '' === $wa ) : ?>
					<p class="oc-regb-modal__ref"><?php esc_html_e( 'The host hasn’t added payment details yet — please reach out to them directly.', 'owambe-connect-core' ); ?></p>
				<?php endif; ?>
				<p class="oc-regb-modal__disc"><?php esc_html_e( 'Owambe doesn’t process this payment — it goes directly to your host.', 'owambe-connect-core' ); ?></p>
			</div>
		</div>
		<style>
			.oc-event__registryb{max-width:860px;margin:clamp(1.75rem,4vw,2.5rem) auto 0;text-align:center;}
			.oc-regb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:16px;text-align:left;}
			.oc-regb-card{display:flex;flex-direction:column;background:#fff;border:1px solid #ECE4E6;border-radius:14px;overflow:hidden;box-shadow:0 4px 14px rgba(88,24,37,.05);}
			.oc-regb-card__img{aspect-ratio:4/3;background:#F6EEF0;}
			.oc-regb-card__img img{width:100%;height:100%;object-fit:cover;display:block;}
			.oc-regb-card__body{display:flex;flex-direction:column;gap:8px;padding:14px 16px 16px;flex:1 1 auto;}
			.oc-regb-card__chip{align-self:flex-start;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#6E0F2C;background:rgba(110,15,44,.08);padding:3px 9px;border-radius:999px;}
			.oc-regb-card__title{margin:0;font-size:16px;font-weight:700;color:#581825;line-height:1.3;}
			.oc-regb-card__price{font-size:15px;font-weight:700;color:#3A3330;}
			.oc-regb-card__note{margin:0;font-size:13px;color:#6B6361;line-height:1.5;}
			.oc-regb-card__prog{margin-top:2px;}
			.oc-regb-card__bar{height:7px;border-radius:99px;background:#EEE3E6;overflow:hidden;}
			.oc-regb-card__bar span{display:block;height:100%;background:linear-gradient(90deg,#C9A961,#581825);}
			.oc-regb-card__figs{margin-top:5px;font-size:12.5px;font-weight:600;color:#6B6361;}
			.oc-regb-card__actions{display:flex;flex-direction:column;gap:8px;margin-top:auto;padding-top:6px;}
			.oc-regb-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:10px;font-size:14px;font-weight:600;text-decoration:none;cursor:pointer;border:1px solid transparent;text-align:center;}
			.oc-regb-btn--buy{background:#581825;color:#fff;}
			.oc-regb-btn--buy:hover{background:#6E0F2C;}
			.oc-regb-btn--give{background:#fff;color:#581825;border-color:#E0CDD2;}
			.oc-regb-btn--give:hover{background:#FBF4F6;border-color:#581825;}
			.oc-regb-btn--wa{background:#1FA855;color:#fff;margin-top:4px;}
			.oc-regb-btn--wa:hover{background:#178a45;}
			.oc-regb-card__ship{margin-top:2px;font-size:13px;color:#6B6361;}
			.oc-regb-card__ship summary{cursor:pointer;font-weight:600;color:#581825;}
			.oc-regb-card__ship address{margin-top:6px;font-style:normal;line-height:1.5;}
			.oc-regb-modal[hidden]{display:none;}
			.oc-regb-modal{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;padding:18px;}
			.oc-regb-modal__backdrop{position:absolute;inset:0;background:rgba(31,20,24,.55);}
			.oc-regb-modal__dialog{position:relative;width:100%;max-width:420px;background:#fff;border-radius:16px;padding:24px;box-shadow:0 24px 60px rgba(0,0,0,.3);text-align:left;max-height:88vh;overflow:auto;}
			.oc-regb-modal__x{position:absolute;top:12px;right:14px;border:0;background:none;font-size:24px;line-height:1;color:#8a5a66;cursor:pointer;}
			.oc-regb-modal__title{margin:0 28px 14px 0;font-size:18px;font-weight:800;color:#581825;}
			.oc-regb-modal__methods{list-style:none;margin:0 0 12px;padding:0;display:flex;flex-direction:column;gap:10px;}
			.oc-regb-modal__methods li{padding:12px 14px;background:#FAF6F2;border:1px solid #ECE4E6;border-radius:10px;}
			.oc-regb-modal__mlabel{display:block;font-size:11.5px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#8a5a66;margin-bottom:2px;}
			.oc-regb-modal__mval{display:block;font-size:15px;font-weight:600;color:#1F1B1A;word-break:break-word;}
			.oc-regb-modal__ref{margin:0 0 12px;font-size:13.5px;color:#3A3330;}
			.oc-regb-modal__disc{margin:14px 0 0;font-size:12px;color:#9a908c;line-height:1.5;}
		</style>
		<script>
		( function () {
			var modal = document.querySelector( '[data-oc-regb-modal]' );
			if ( ! modal ) { return; }
			var slot = modal.querySelector( '[data-oc-regb-modalitem]' );
			function open( title ) {
				if ( slot ) { slot.textContent = title ? '· ' + title : ''; }
				modal.hidden = false;
				document.body.style.overflow = 'hidden';
			}
			function close() { modal.hidden = true; document.body.style.overflow = ''; }
			document.addEventListener( 'click', function ( e ) {
				var op = e.target.closest ? e.target.closest( '[data-oc-regb-open]' ) : null;
				if ( op ) { open( op.getAttribute( 'data-item' ) || '' ); return; }
				if ( e.target.closest && e.target.closest( '[data-oc-regb-close]' ) ) { close(); }
			} );
			document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key && ! modal.hidden ) { close(); } } );
		} )();
		</script>
		<?php
	}
}
