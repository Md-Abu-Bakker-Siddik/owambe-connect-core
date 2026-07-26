<?php
/**
 * Vendors → Approved Retailers — editor for the oc_registry_shops option.
 *
 * This is the single source of truth behind BOTH gift-registry parts:
 *  - Part A (external registries): a URL only earns an affiliate append, and is
 *    shown, when its host matches an approved, visible shop here.
 *  - Part B (Owambe registry): a "gift" item's product link is accepted only if
 *    its host is on this list; the affiliate param/code is appended server-side.
 *
 * Each row: display name, domain (host only), affiliate query param + code, and
 * a visibility toggle. OC_Registry::shops() reads the saved option (and seeds a
 * filterable default when it's empty), so nothing here needs to know about the
 * matching logic — it just curates the list.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Retailers {

	const PAGE  = 'oc-registry-shops';
	const NONCE = 'oc_registry_shops_save';

	public function register() {
		add_action( 'admin_menu', [ $this, 'menu' ], 13 );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Approved Retailers', 'owambe-connect-core' ),
			__( 'Approved Retailers', 'owambe-connect-core' ),
			'manage_options',
			self::PAGE,
			[ $this, 'render' ]
		);
	}

	/**
	 * Normalise a pasted domain or full URL down to a bare host:
	 * "https://www.Amazon.co.uk/gp/…" → "amazon.co.uk".
	 */
	private static function clean_domain( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		$test = ( false === strpos( $raw, '://' ) ) ? 'http://' . $raw : $raw;
		$host = wp_parse_url( $test, PHP_URL_HOST );
		if ( ! $host ) {
			$host = $raw;
		}
		return strtolower( preg_replace( '/^www\./', '', $host ) );
	}

	/** Read + sanitise the submitted rows into the shop-list shape. */
	private static function collect_from_post() {
		$shops = [];
		if ( isset( $_POST['shops'] ) && is_array( $_POST['shops'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			foreach ( wp_unslash( $_POST['shops'] ) as $row ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( ! is_array( $row ) ) {
					continue;
				}
				$domain = self::clean_domain( $row['domain'] ?? '' );
				$name   = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
				if ( '' === $domain ) {
					continue; // a shop with no domain is meaningless.
				}
				$shops[] = [
					'name'      => '' !== $name ? $name : $domain,
					'domain'    => $domain,
					'aff_param' => sanitize_text_field( (string) ( $row['aff_param'] ?? '' ) ),
					'aff_code'  => sanitize_text_field( (string) ( $row['aff_code'] ?? '' ) ),
					'visible'   => empty( $row['visible'] ) ? 0 : 1,
				];
			}
		}
		return $shops;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$saved = false;
		if ( isset( $_POST['oc_registry_shops_submit'] ) ) {
			check_admin_referer( self::NONCE );
			update_option( 'oc_registry_shops', self::collect_from_post() );
			$saved = true;
		}

		// After a save, read straight back from the option so an all-empty save
		// shows exactly what's stored (rather than shops()' seeded default).
		if ( $saved ) {
			$shops = get_option( 'oc_registry_shops', [] );
			$shops = is_array( $shops ) ? $shops : [];
		} else {
			$shops = OC_Registry::shops(); // seeds the filterable default on first visit.
		}
		if ( empty( $shops ) ) {
			$shops = [ [ 'name' => '', 'domain' => '', 'aff_param' => '', 'aff_code' => '', 'visible' => 1 ] ];
		}
		?>
		<div class="wrap oc-retailers">
			<h1><?php esc_html_e( 'Approved Retailers', 'owambe-connect-core' ); ?></h1>
			<p class="description" style="max-width:820px;">
				<?php esc_html_e( 'Shops guests may link to in a gift registry. A registry link is only shown — and only earns an affiliate append — when its web address matches a visible shop below. This list powers both external registries (Part A) and the Owambe registry (Part B).', 'owambe-connect-core' ); ?>
			</p>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Approved retailers saved.', 'owambe-connect-core' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE ); ?>
				<table class="wp-list-table widefat fixed striped oc-retailers__table">
					<thead>
						<tr>
							<th style="width:20%;"><?php esc_html_e( 'Shop name', 'owambe-connect-core' ); ?></th>
							<th style="width:24%;"><?php esc_html_e( 'Domain', 'owambe-connect-core' ); ?></th>
							<th style="width:16%;"><?php esc_html_e( 'Affiliate param', 'owambe-connect-core' ); ?></th>
							<th style="width:22%;"><?php esc_html_e( 'Affiliate code', 'owambe-connect-core' ); ?></th>
							<th style="width:10%;"><?php esc_html_e( 'Visible', 'owambe-connect-core' ); ?></th>
							<th style="width:8%;"></th>
						</tr>
					</thead>
					<tbody data-oc-shops>
						<?php $i = 0; foreach ( $shops as $shop ) : ?>
							<tr class="oc-retailers__row">
								<td><input type="text" name="shops[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( (string) ( $shop['name'] ?? '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Amazon', 'owambe-connect-core' ); ?>" /></td>
								<td><input type="text" name="shops[<?php echo (int) $i; ?>][domain]" value="<?php echo esc_attr( (string) ( $shop['domain'] ?? '' ) ); ?>" class="regular-text" placeholder="amazon.co.uk" /></td>
								<td><input type="text" name="shops[<?php echo (int) $i; ?>][aff_param]" value="<?php echo esc_attr( (string) ( $shop['aff_param'] ?? '' ) ); ?>" placeholder="tag" /></td>
								<td><input type="text" name="shops[<?php echo (int) $i; ?>][aff_code]" value="<?php echo esc_attr( (string) ( $shop['aff_code'] ?? '' ) ); ?>" placeholder="owambe-21" /></td>
								<td style="text-align:center;"><input type="checkbox" name="shops[<?php echo (int) $i; ?>][visible]" value="1" <?php checked( ! empty( $shop['visible'] ) ); ?> /></td>
								<td><button type="button" class="button-link oc-retailers__remove" data-oc-shop-remove><?php esc_html_e( 'Remove', 'owambe-connect-core' ); ?></button></td>
							</tr>
							<?php $i++; endforeach; ?>
					</tbody>
				</table>

				<p><button type="button" class="button" data-oc-shop-add data-next="<?php echo (int) $i; ?>"><?php esc_html_e( '+ Add retailer', 'owambe-connect-core' ); ?></button></p>

				<p class="description" style="max-width:820px;">
					<?php esc_html_e( 'Affiliate param + code are optional. When both are set, that key/value is appended to matching links (e.g. ?tag=owambe-21). Leave them blank to approve a shop for linking without earning a commission. Domains match the host and any subdomain; “www.” is ignored.', 'owambe-connect-core' ); ?>
				</p>

				<p class="submit">
					<button type="submit" name="oc_registry_shops_submit" value="1" class="button button-primary"><?php esc_html_e( 'Save retailers', 'owambe-connect-core' ); ?></button>
				</p>
			</form>
		</div>

		<style>
			.oc-retailers__table input[type="text"]{width:100%;}
			.oc-retailers__remove{color:#b32d3a;}
			.oc-retailers__remove:hover{color:#8a2b3a;}
		</style>
		<script>
		( function () {
			var body = document.querySelector( '[data-oc-shops]' );
			var add  = document.querySelector( '[data-oc-shop-add]' );
			if ( ! body || ! add ) { return; }
			body.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest ? e.target.closest( '[data-oc-shop-remove]' ) : null;
				if ( ! btn ) { return; }
				var rows = body.querySelectorAll( '.oc-retailers__row' );
				if ( rows.length <= 1 ) {
					btn.closest( '.oc-retailers__row' ).querySelectorAll( 'input' ).forEach( function ( inp ) {
						if ( 'checkbox' === inp.type ) { inp.checked = false; } else { inp.value = ''; }
					} );
					return;
				}
				btn.closest( '.oc-retailers__row' ).remove();
			} );
			add.addEventListener( 'click', function () {
				var n = parseInt( add.getAttribute( 'data-next' ), 10 ) || 0;
				add.setAttribute( 'data-next', n + 1 );
				var tr = document.createElement( 'tr' );
				tr.className = 'oc-retailers__row';
				tr.innerHTML =
					'<td><input type="text" name="shops[' + n + '][name]" class="regular-text" placeholder="Amazon" /></td>' +
					'<td><input type="text" name="shops[' + n + '][domain]" class="regular-text" placeholder="amazon.co.uk" /></td>' +
					'<td><input type="text" name="shops[' + n + '][aff_param]" placeholder="tag" /></td>' +
					'<td><input type="text" name="shops[' + n + '][aff_code]" placeholder="owambe-21" /></td>' +
					'<td style="text-align:center;"><input type="checkbox" name="shops[' + n + '][visible]" value="1" checked /></td>' +
					'<td><button type="button" class="button-link oc-retailers__remove" data-oc-shop-remove><?php echo esc_js( __( 'Remove', 'owambe-connect-core' ) ); ?></button></td>';
				body.appendChild( tr );
			} );
		} )();
		</script>
		<?php
	}
}
