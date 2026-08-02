<?php
/**
 * Planning Resources admin (P14) — curates the downloads shown by
 * [oc_checklists] and on the client dashboard.
 *
 * Vendors → Planning Resources. Repeatable rows stored in the
 * `oc_planning_resources` option: title, short description, type label and
 * the file URL — picked straight from the Media Library (client uploads
 * their PDFs there, then selects them here). Explicit curation only: the
 * page never auto-lists PDF attachments, because the Media Library also
 * holds private files (verification documents).
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Resources {

	const PAGE   = 'oc-resources';
	const NONCE  = 'oc_resources_save';
	const OPTION = 'oc_planning_resources';

	public function register() {
		add_action( 'admin_menu', [ $this, 'menu' ], 32 );
		add_action( 'admin_post_oc_resources_save', [ $this, 'save' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Planning Resources', 'owambe-connect-core' ),
			__( 'Planning Resources', 'owambe-connect-core' ),
			'manage_options',
			self::PAGE,
			[ $this, 'render' ]
		);
	}

	public function assets( $hook ) {
		if ( false !== strpos( (string) $hook, self::PAGE ) ) {
			wp_enqueue_media();
		}
	}

	/** Default type labels — free text in the row, these are placeholders. */
	public static function types() {
		return [
			__( 'Checklist', 'owambe-connect-core' ),
			__( 'Contract template', 'owambe-connect-core' ),
			__( 'Planning guide', 'owambe-connect-core' ),
		];
	}

	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		check_admin_referer( self::NONCE );

		$rows = [];
		$in   = isset( $_POST['res'] ) && is_array( $_POST['res'] ) ? wp_unslash( $_POST['res'] ) : [];
		foreach ( $in as $row ) {
			$url = esc_url_raw( trim( (string) ( $row['url'] ?? '' ) ) );
			if ( '' === $url ) {
				continue; // Row without a file is meaningless — dropped.
			}
			$title = sanitize_text_field( $row['title'] ?? '' );
			$rows[] = [
				'title'    => '' !== $title ? $title : wp_basename( $url ),
				'desc'     => sanitize_text_field( $row['desc'] ?? '' ),
				'type'     => sanitize_text_field( $row['type'] ?? '' ),
				'url'      => $url,
				'media_id' => absint( $row['media_id'] ?? 0 ),
			];
		}
		update_option( self::OPTION, $rows );

		wp_safe_redirect( add_query_arg(
			[ 'post_type' => OC_CPT, 'page' => self::PAGE, 'oc_admin_msg' => 'resources_saved' ],
			admin_url( 'edit.php' )
		) );
		exit;
	}

	public function render() {
		$rows = get_option( self::OPTION, [] );
		if ( ! is_array( $rows ) ) {
			$rows = [];
		}
		$saved = isset( $_GET['oc_admin_msg'] ) && 'resources_saved' === $_GET['oc_admin_msg'];
		?>
		<div class="wrap oc-res">
			<h1><?php esc_html_e( 'Planning Resources', 'owambe-connect-core' ); ?></h1>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Resources saved.', 'owambe-connect-core' ); ?></p></div>
			<?php endif; ?>
			<p class="oc-res__intro">
				<?php esc_html_e( 'These downloads appear on the Planning Resources page and the client dashboard. Upload PDFs to the Media Library, then add them here — nothing is listed automatically.', 'owambe-connect-core' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="oc_resources_save" />
				<?php wp_nonce_field( self::NONCE ); ?>

				<table class="widefat striped oc-res__table">
					<thead>
						<tr>
							<th style="width:18%"><?php esc_html_e( 'Title', 'owambe-connect-core' ); ?></th>
							<th style="width:28%"><?php esc_html_e( 'Short description', 'owambe-connect-core' ); ?></th>
							<th style="width:14%"><?php esc_html_e( 'Type label', 'owambe-connect-core' ); ?></th>
							<th><?php esc_html_e( 'File (from Media Library)', 'owambe-connect-core' ); ?></th>
							<th style="width:60px"></th>
						</tr>
					</thead>
					<tbody id="oc-res-rows">
						<?php
						if ( ! $rows ) {
							$rows = [ [ 'title' => '', 'desc' => '', 'type' => '', 'url' => '', 'media_id' => 0 ] ];
						}
						foreach ( $rows as $i => $r ) :
							?>
							<tr class="oc-res__row">
								<td><input type="text" name="res[<?php echo (int) $i; ?>][title]" value="<?php echo esc_attr( $r['title'] ?? '' ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Wedding planning checklist', 'owambe-connect-core' ); ?>" /></td>
								<td><input type="text" name="res[<?php echo (int) $i; ?>][desc]" value="<?php echo esc_attr( $r['desc'] ?? '' ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Month-by-month tasks from engagement to the big day.', 'owambe-connect-core' ); ?>" /></td>
								<td><input type="text" name="res[<?php echo (int) $i; ?>][type]" value="<?php echo esc_attr( $r['type'] ?? '' ); ?>" class="widefat" list="oc-res-types" placeholder="<?php esc_attr_e( 'Checklist', 'owambe-connect-core' ); ?>" /></td>
								<td>
									<div style="display:flex;gap:6px;">
										<input type="url" name="res[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $r['url'] ?? '' ); ?>" class="widefat oc-res__url" placeholder="https://…/checklist.pdf" />
										<input type="hidden" name="res[<?php echo (int) $i; ?>][media_id]" value="<?php echo (int) ( $r['media_id'] ?? 0 ); ?>" class="oc-res__media-id" />
										<button type="button" class="button oc-res__pick"><?php esc_html_e( 'Select file', 'owambe-connect-core' ); ?></button>
									</div>
								</td>
								<td><button type="button" class="button-link-delete oc-res__remove" aria-label="<?php esc_attr_e( 'Remove row', 'owambe-connect-core' ); ?>">&times;</button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<datalist id="oc-res-types">
					<?php foreach ( self::types() as $t ) : ?>
						<option value="<?php echo esc_attr( $t ); ?>"></option>
					<?php endforeach; ?>
				</datalist>

				<p style="margin-top:12px;display:flex;gap:10px;">
					<button type="button" class="button" id="oc-res-add"><?php esc_html_e( '+ Add resource', 'owambe-connect-core' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save resources', 'owambe-connect-core' ); ?></button>
				</p>
			</form>
		</div>

		<style>
			.oc-res__intro { max-width: 720px; }
			.oc-res__table input { min-height: 32px; }
			.oc-res__remove { color: #b32d2e; font-size: 18px; text-decoration: none; }
		</style>

		<script>
		(function () {
			var rows = document.getElementById('oc-res-rows');

			document.getElementById('oc-res-add').addEventListener('click', function () {
				var idx = rows.querySelectorAll('.oc-res__row').length;
				var tpl = rows.querySelector('.oc-res__row').cloneNode(true);
				tpl.querySelectorAll('input').forEach(function (input) {
					input.name = input.name.replace(/res\[\d+\]/, 'res[' + idx + ']');
					input.value = input.classList.contains('oc-res__media-id') ? '0' : '';
				});
				rows.appendChild(tpl);
			});

			rows.addEventListener('click', function (e) {
				if (e.target.closest('.oc-res__remove')) {
					var all = rows.querySelectorAll('.oc-res__row');
					var row = e.target.closest('.oc-res__row');
					if (all.length > 1) { row.remove(); }
					else { row.querySelectorAll('input').forEach(function (i) { i.value = i.classList.contains('oc-res__media-id') ? '0' : ''; }); }
					return;
				}
				var pick = e.target.closest('.oc-res__pick');
				if (pick && window.wp && wp.media) {
					var row = pick.closest('.oc-res__row');
					var frame = wp.media({
						title: '<?php echo esc_js( __( 'Select a resource file', 'owambe-connect-core' ) ); ?>',
						button: { text: '<?php echo esc_js( __( 'Use this file', 'owambe-connect-core' ) ); ?>' },
						library: { type: 'application' },
						multiple: false
					});
					frame.on('select', function () {
						var file = frame.state().get('selection').first().toJSON();
						row.querySelector('.oc-res__url').value = file.url;
						row.querySelector('.oc-res__media-id').value = file.id;
						var title = row.querySelector('input[name$="[title]"]');
						if (title && !title.value) { title.value = file.title || file.filename; }
					});
					frame.open();
				}
			});
		})();
		</script>
		<?php
	}
}
