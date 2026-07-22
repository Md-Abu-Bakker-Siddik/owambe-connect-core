<?php
/**
 * Admin-side client directory: a "Clients" submenu listing the marketplace's
 * client-role users (OC_CLIENT_ROLE — created when someone signs in with
 * Google on the client tab). Read-only: searchable, paginated, with each
 * client's saved-vendor and recent-contact counts and a link to the WP user
 * profile. Vendors live under their own CPT list; this is the customer side.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Clients {

	const PAGE_SLUG = 'oc-clients';
	const PER_PAGE  = 25;

	public function register() {
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
	}

	public function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Clients', 'owambe-connect-core' ),
			__( 'Clients', 'owambe-connect-core' ),
			'list_users',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'list_users' ) ) {
			wp_die( esc_html__( 'Unauthorised.', 'owambe-connect-core' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended — read-only list filters.
		$search = isset( $_GET['oc_s'] ) ? sanitize_text_field( wp_unslash( $_GET['oc_s'] ) ) : '';
		$paged  = isset( $_GET['cpage'] ) ? max( 1, absint( $_GET['cpage'] ) ) : 1;
		// phpcs:enable

		$args = [
			'role'    => OC_CLIENT_ROLE,
			'number'  => self::PER_PAGE,
			'paged'   => $paged,
			'orderby' => 'registered',
			'order'   => 'DESC',
		];
		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
		}

		$query = new WP_User_Query( $args );
		$users = (array) $query->get_results();
		$total = (int) $query->get_total();
		$pages = (int) ceil( $total / self::PER_PAGE );

		$base_url = admin_url( 'edit.php?post_type=' . OC_CPT . '&page=' . self::PAGE_SLUG );
		?>
		<div class="wrap oc-clients">
			<?php $this->page_styles(); ?>
			<h1>
				<span class="dashicons dashicons-groups" style="color:#6E0F2C;font-size:28px"></span>
				<?php esc_html_e( 'Clients', 'owambe-connect-core' ); ?>
				<span class="oc-clients-count"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
			</h1>
			<p class="oc-clients-sub"><?php esc_html_e( 'People who signed up to plan events and save vendors. Created automatically on Google sign-in.', 'owambe-connect-core' ); ?></p>

			<form method="get" class="oc-clients-search">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( OC_CPT ); ?>" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<input type="search" name="oc_s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name or email…', 'owambe-connect-core' ); ?>" />
				<button type="submit" class="button"><?php esc_html_e( 'Search', 'owambe-connect-core' ); ?></button>
				<?php if ( '' !== $search ) : ?>
					<a class="button" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Clear', 'owambe-connect-core' ); ?></a>
				<?php endif; ?>
			</form>

			<div class="oc-clients-section">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Client', 'owambe-connect-core' ); ?></th>
							<th><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?></th>
							<th><?php esc_html_e( 'Saved vendors', 'owambe-connect-core' ); ?></th>
							<th><?php esc_html_e( 'Contacted', 'owambe-connect-core' ); ?></th>
							<th><?php esc_html_e( 'Joined', 'owambe-connect-core' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $users ) ) : ?>
							<tr><td colspan="6"><?php echo '' !== $search ? esc_html__( 'No clients match your search.', 'owambe-connect-core' ) : esc_html__( 'No clients yet.', 'owambe-connect-core' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $users as $user ) :
								$saved    = class_exists( 'OC_Client' ) ? count( OC_Client::saved_vendors( $user->ID ) ) : 0;
								$contacts = get_user_meta( $user->ID, '_oc_recent_contacts', true );
								$contacts = is_array( $contacts ) ? count( $contacts ) : 0;
								$edit_url = admin_url( 'user-edit.php?user_id=' . $user->ID );
								?>
								<tr>
									<td><strong><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></strong></td>
									<td><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td>
									<td><?php echo esc_html( number_format_i18n( $saved ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $contacts ) ); ?></td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) ); ?></td>
									<td><a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'View', 'owambe-connect-core' ); ?></a></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<?php if ( $pages > 1 ) : ?>
					<p class="oc-clients-pager">
						<?php
						$page_args = '' !== $search ? [ 'oc_s' => $search ] : [];
						if ( $paged > 1 ) {
							printf( '<a class="button" href="%s">%s</a> ', esc_url( add_query_arg( array_merge( $page_args, [ 'cpage' => $paged - 1 ] ), $base_url ) ), esc_html__( '‹ Newer', 'owambe-connect-core' ) );
						}
						printf( '<span>%s</span>', esc_html( sprintf( __( 'Page %1$d of %2$d', 'owambe-connect-core' ), $paged, $pages ) ) );
						if ( $paged < $pages ) {
							printf( ' <a class="button" href="%s">%s</a>', esc_url( add_query_arg( array_merge( $page_args, [ 'cpage' => $paged + 1 ] ), $base_url ) ), esc_html__( 'Older ›', 'owambe-connect-core' ) );
						}
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/** Brand styles for the Clients screen — mirrors the Reviews/Analytics palette. */
	private function page_styles() {
		?>
		<style id="oc-clients-styles">
			.oc-clients h1 { display:flex; align-items:center; gap:10px; margin-bottom:6px; color:#1F1B1A; }
			.oc-clients .oc-clients-sub { margin:0 0 18px; color:#6B6361; }
			.oc-clients-count { font-family:Georgia, serif; font-size:14px; font-weight:700; color:#6E0F2C; background:#FAF0F2; border:1px solid #E9CBD3; border-radius:999px; padding:2px 12px; }
			.oc-clients-search { display:flex; gap:8px; margin:0 0 16px; }
			.oc-clients-search input[type="search"] { min-width:280px; padding:6px 10px; border:1px solid #ccd0d4; border-radius:4px; }
			.oc-clients-section { background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:18px 20px; }
			.oc-clients table.widefat { border-color:#E4DDD2; }
			.oc-clients table.widefat thead th { background:#FAF7F2; color:#1F1B1A; font-weight:600; }
			.oc-clients-pager { margin:12px 0 0; }
			.oc-clients-pager span { margin:0 8px; color:#6B6361; }
		</style>
		<?php
	}
}
