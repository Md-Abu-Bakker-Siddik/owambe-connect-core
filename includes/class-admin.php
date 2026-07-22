<?php
/**
 * Admin-side approval UI: row actions, bulk actions, columns,
 * meta box, and email triggers on status change.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin {

	public function register() {
		add_filter( 'post_row_actions',                          [ $this, 'row_actions' ], 10, 2 );
		add_action( 'admin_post_oc_approve_vendor',              [ $this, 'approve_vendor' ] );
		add_action( 'admin_post_oc_toggle_verified',             [ $this, 'toggle_verified' ] );
		add_action( 'admin_post_oc_reject_vendor',               [ $this, 'reject_vendor' ] );
		add_action( 'admin_post_oc_toggle_featured',             [ $this, 'toggle_featured' ] );
		add_action( 'add_meta_boxes_' . OC_CPT,                  [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post_' . OC_CPT,                       [ $this, 'save_meta_box' ], 10, 2 );
		add_filter( 'manage_' . OC_CPT . '_posts_columns',       [ $this, 'columns' ] );
		add_action( 'manage_' . OC_CPT . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_filter( 'bulk_actions-edit-' . OC_CPT,               [ $this, 'bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-' . OC_CPT,        [ $this, 'handle_bulk' ], 10, 3 );
		add_action( 'admin_notices',                             [ $this, 'admin_notices' ] );
		add_action( 'admin_menu',                                [ $this, 'add_pending_badge' ] );
		add_action( 'admin_enqueue_scripts',                     [ $this, 'enqueue_admin_assets' ] );
		add_filter( 'display_post_states',                       [ $this, 'post_states' ], 10, 2 );
		add_action( 'wp_dashboard_setup',                        [ $this, 'register_dashboard_widget' ] );
		add_action( 'admin_head',                                [ $this, 'submenu_icons_css' ] );
		add_action( 'admin_menu',                                [ $this, 'cleanup_submenu' ], 999 );
	}

	/**
	 * Remove the auto-generated "Add New Vendor" submenu — we replace it with
	 * our streamlined "Add Vendor" page (and redirect post-new.php anyway).
	 */
	public function cleanup_submenu() {
		remove_submenu_page( 'edit.php?post_type=' . OC_CPT, 'post-new.php?post_type=' . OC_CPT );
	}

	/**
	 * Inject icon glyphs (WordPress Dashicons font) before each Vendors
	 * submenu link. Keeps the menu consistent without external assets — every
	 * OC submenu item gets a paired dashicon so admin sidebar feels uniform.
	 */
	public function submenu_icons_css() {
		$screen = get_current_screen();
		if ( ! $screen ) return;
		?>
		<style id="oc-submenu-icons">
			#adminmenu .wp-submenu li a[href$="post_type=<?php echo esc_attr( OC_CPT ); ?>"]::before,
			#adminmenu .wp-submenu li a[href*="page=oc-add-vendor"]::before,
			#adminmenu .wp-submenu li a[href*="page=oc-import-vendors"]::before,
			#adminmenu .wp-submenu li a[href*="page=oc-vendor-emails"]::before,
			#adminmenu .wp-submenu li a[href*="page=oc-analytics"]::before,
			#adminmenu .wp-submenu li a[href*="page=oc-reviews"]::before,
			#adminmenu .wp-submenu li a[href*="page=oc-clients"]::before,
			#adminmenu .wp-submenu li a[href*="page=oc-activity-log"]::before,
			#adminmenu .wp-submenu li a[href*="taxonomy=<?php echo esc_attr( OC_TAX ); ?>"]::before,
			#adminmenu .wp-submenu li a[href*="page=oc-settings"]::before,
			#adminmenu .wp-submenu li a[href*="page=oc-security-health"]::before,
			#adminmenu .wp-submenu li a[href*="page=oc-import-demo"]::before,
			#adminmenu .wp-submenu li a[href*="page=oc-developer-guide"]::before {
				font-family: dashicons;
				font-weight: 400;
				speak: never;
				margin-right: 6px;
				vertical-align: middle;
				font-size: 14px;
				line-height: 1;
				width: 16px;
				display: inline-block;
				opacity: 0.85;
			}
			#adminmenu .wp-submenu li a[href$="post_type=<?php echo esc_attr( OC_CPT ); ?>"]::before  { content: "\f163"; } /* list */
			#adminmenu .wp-submenu li a[href*="page=oc-add-vendor"]::before                            { content: "\f132"; } /* plus-alt */
			#adminmenu .wp-submenu li a[href*="page=oc-import-vendors"]::before                        { content: "\f317"; } /* upload / database-import */
			#adminmenu .wp-submenu li a[href*="page=oc-vendor-emails"]::before                         { content: "\f465"; } /* email-alt */
			#adminmenu .wp-submenu li a[href*="page=oc-analytics"]::before                             { content: "\f239"; } /* chart-area */
			#adminmenu .wp-submenu li a[href*="page=oc-reviews"]::before                               { content: "\f155"; } /* star-filled */
			#adminmenu .wp-submenu li a[href*="page=oc-clients"]::before                               { content: "\f307"; } /* groups */
			#adminmenu .wp-submenu li a[href*="page=oc-activity-log"]::before                          { content: "\f469"; } /* calendar-alt */
			#adminmenu .wp-submenu li a[href*="taxonomy=<?php echo esc_attr( OC_TAX ); ?>"]::before    { content: "\f318"; } /* tag */
			#adminmenu .wp-submenu li a[href*="page=oc-settings"]::before                              { content: "\f108"; } /* admin-generic gear */
			#adminmenu .wp-submenu li a[href*="page=oc-security-health"]::before                       { content: "\f332"; } /* shield */
			#adminmenu .wp-submenu li a[href*="page=oc-import-demo"]::before                           { content: "\f105"; } /* admin-page */
			#adminmenu .wp-submenu li a[href*="page=oc-developer-guide"]::before                       { content: "\f223"; } /* editor-spellcheck */
		</style>
		<?php
	}

	public function add_pending_badge() {
		global $menu;
		$pending = OC_Queries::pending_count();
		if ( ! $pending ) return;
		foreach ( $menu as $i => $item ) {
			if ( isset( $item[2] ) && 'edit.php?post_type=' . OC_CPT === $item[2] ) {
				$menu[ $i ][0] .= sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $pending );
				break;
			}
		}
	}

	public function row_actions( $actions, $post ) {
		if ( OC_CPT !== $post->post_type ) return $actions;
		$status = $post->post_status;

		if ( in_array( $status, [ OC_STATUS_PENDING, OC_STATUS_REJECTED ], true ) ) {
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=oc_approve_vendor&post=' . $post->ID ),
				'oc_approve_' . $post->ID
			);
			$actions['oc_approve'] = '<a href="' . esc_url( $url ) . '" class="oc-approve" style="color:#1e7e3c;font-weight:600">' . esc_html__( 'Approve', 'owambe-connect-core' ) . '</a>';
		}
		if ( OC_STATUS_PENDING === $status || OC_STATUS_APPROVED === $status ) {
			$actions['oc_reject'] = '<a href="#" class="oc-reject" data-post-id="' . esc_attr( $post->ID ) . '" style="color:#b32d2e">' . esc_html__( 'Reject…', 'owambe-connect-core' ) . '</a>';
		}
		if ( OC_STATUS_APPROVED === $status ) {
			$is_featured = (int) get_post_meta( $post->ID, '_oc_featured', true ) === 1;
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=oc_toggle_featured&post=' . $post->ID ),
				'oc_toggle_featured_' . $post->ID
			);
			$actions['oc_featured'] = '<a href="' . esc_url( $url ) . '" style="color:' . ( $is_featured ? '#A8893D' : '#555' ) . ';font-weight:600">'
				. ( $is_featured ? esc_html__( '★ Unfeature', 'owambe-connect-core' ) : esc_html__( '☆ Feature', 'owambe-connect-core' ) )
				. '</a>';
		}
		return $actions;
	}

	public function toggle_featured() {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		check_admin_referer( 'oc_toggle_featured_' . $post_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) wp_die( -1 );

		$current = (int) get_post_meta( $post_id, '_oc_featured', true );
		update_post_meta( $post_id, '_oc_featured', $current ? 0 : 1 );

		wp_safe_redirect( add_query_arg( 'oc_admin_msg', $current ? 'unfeatured' : 'featured', wp_get_referer() ?: admin_url( 'edit.php?post_type=' . OC_CPT ) ) );
		exit;
	}

	public function toggle_verified() {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		check_admin_referer( 'oc_toggle_verified_' . $post_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) wp_die( -1 );

		$current = (int) get_post_meta( $post_id, '_oc_verified', true );
		update_post_meta( $post_id, '_oc_verified', $current ? 0 : 1 );

		wp_safe_redirect( add_query_arg( 'oc_admin_msg', $current ? 'unverified' : 'verified', wp_get_referer() ?: admin_url( 'admin.php?page=oc-vendors' ) ) );
		exit;
	}

	public function approve_vendor() {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		check_admin_referer( 'oc_approve_' . $post_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) wp_die( -1 );

		// Clear the old rejection note before transitioning so the email reads cleanly.
		delete_post_meta( $post_id, '_oc_rejection_note' );

		// Status change triggers transition_post_status → vendor email + activity log.
		wp_update_post( [ 'ID' => $post_id, 'post_status' => OC_STATUS_APPROVED ] );

		wp_safe_redirect( add_query_arg( 'oc_admin_msg', 'approved', wp_get_referer() ?: admin_url( 'edit.php?post_type=' . OC_CPT ) ) );
		exit;
	}

	public function reject_vendor() {
		$post_id = isset( $_POST['post'] ) ? (int) $_POST['post'] : 0;
		check_admin_referer( 'oc_reject_' . $post_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) wp_die( -1 );

		$reason = isset( $_POST['oc_rejection_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['oc_rejection_note'] ) ) : '';
		$reason = trim( $reason );

		// REQUIRED: a rejection without a reason is useless to the vendor. Bounce back with an error.
		if ( strlen( $reason ) < 10 ) {
			wp_safe_redirect( add_query_arg( 'oc_admin_msg', 'reject_needs_reason', wp_get_referer() ?: admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ) );
			exit;
		}

		// Save the note BEFORE the status change so the transition handler can read it for the email.
		update_post_meta( $post_id, '_oc_rejection_note', $reason );
		wp_update_post( [ 'ID' => $post_id, 'post_status' => OC_STATUS_REJECTED ] );

		wp_safe_redirect( add_query_arg( 'oc_admin_msg', 'rejected', wp_get_referer() ?: admin_url( 'edit.php?post_type=' . OC_CPT ) ) );
		exit;
	}

	public function bulk_actions( $actions ) {
		// Bulk approve is fine — no per-vendor message needed.
		// Bulk reject removed deliberately: rejections must always carry a reason. Use single-vendor reject.
		$actions['oc_bulk_approve'] = __( 'Approve', 'owambe-connect-core' );
		return $actions;
	}

	public function handle_bulk( $redirect, $action, $ids ) {
		if ( 'oc_bulk_approve' === $action ) {
			foreach ( $ids as $id ) {
				delete_post_meta( $id, '_oc_rejection_note' );
				wp_update_post( [ 'ID' => $id, 'post_status' => OC_STATUS_APPROVED ] );
				// Email + activity log fire via transition_post_status.
			}
			$redirect = add_query_arg( 'oc_admin_msg', 'bulk_approved', $redirect );
		}
		return $redirect;
	}

	public function admin_notices() {
		if ( ! isset( $_GET['oc_admin_msg'] ) ) return;
		$map = [
			'approved'            => [ 'success', __( 'Vendor approved and notified by email.', 'owambe-connect-core' ) ],
			'rejected'            => [ 'warning', __( 'Vendor marked as needing changes and notified.', 'owambe-connect-core' ) ],
			'reject_needs_reason' => [ 'error',   __( 'Please provide a rejection reason (minimum 10 characters) — it\'s sent to the vendor.', 'owambe-connect-core' ) ],
			'bulk_approved'       => [ 'success', __( 'Selected vendors approved.', 'owambe-connect-core' ) ],
			'featured'            => [ 'success', __( 'Vendor marked as featured.', 'owambe-connect-core' ) ],
			'unfeatured'          => [ 'success', __( 'Vendor removed from featured.', 'owambe-connect-core' ) ],
		];
		$key = sanitize_key( $_GET['oc_admin_msg'] );
		if ( isset( $map[ $key ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $map[ $key ][0] ), esc_html( $map[ $key ][1] ) );
		}
	}

	public function columns( $cols ) {
		$new = [];
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['oc_logo']     = __( 'Logo',     'owambe-connect-core' );
				$new['oc_location'] = __( 'Location', 'owambe-connect-core' );
				$new['oc_status']   = __( 'Status',   'owambe-connect-core' );
			}
		}
		return $new;
	}

	public function render_column( $col, $post_id ) {
		if ( 'oc_logo' === $col ) {
			$id = (int) get_post_meta( $post_id, '_oc_logo_id', true );
			echo $id ? wp_get_attachment_image( $id, [ 48, 48 ] ) : '<span style="color:#999">—</span>';
		} elseif ( 'oc_location' === $col ) {
			echo esc_html( get_post_meta( $post_id, '_oc_location', true ) ?: '—' );
		} elseif ( 'oc_status' === $col ) {
			$status = get_post_status( $post_id );
			$colors = [ OC_STATUS_PENDING => '#b8860b', OC_STATUS_APPROVED => '#1e7e3c', OC_STATUS_REJECTED => '#b32d2e' ];
			$color  = $colors[ $status ] ?? '#555';
			printf( '<strong style="color:%s">%s</strong>', esc_attr( $color ), esc_html( oc_status_label( $status ) ) );
		}
	}

	public function post_states( $states, $post ) {
		if ( OC_CPT !== $post->post_type ) return $states;
		if ( OC_STATUS_PENDING === $post->post_status )  $states['oc'] = __( 'Pending Review', 'owambe-connect-core' );
		if ( OC_STATUS_REJECTED === $post->post_status ) $states['oc'] = __( 'Needs Changes', 'owambe-connect-core' );
		return $states;
	}

	public function add_meta_boxes() {
		add_meta_box( 'oc_profile', __( 'Vendor Profile', 'owambe-connect-core' ), [ $this, 'render_profile_box' ], OC_CPT, 'normal', 'high' );
		add_meta_box( 'oc_actions', __( 'Approval Actions', 'owambe-connect-core' ), [ $this, 'render_actions_box' ], OC_CPT, 'side', 'high' );
	}

	public function render_actions_box( $post ) {
		$status = $post->post_status;
		echo '<p><strong>' . esc_html__( 'Current status:', 'owambe-connect-core' ) . '</strong> ' . esc_html( oc_status_label( $status ) ) . '</p>';

		if ( in_array( $status, [ OC_STATUS_PENDING, OC_STATUS_REJECTED ], true ) ) {
			$approve = wp_nonce_url( admin_url( 'admin-post.php?action=oc_approve_vendor&post=' . $post->ID ), 'oc_approve_' . $post->ID );
			echo '<p><a class="button button-primary" href="' . esc_url( $approve ) . '">' . esc_html__( 'Approve & Publish', 'owambe-connect-core' ) . '</a></p>';
		}

		if ( OC_STATUS_PENDING === $status || OC_STATUS_APPROVED === $status ) {
			$nonce = wp_create_nonce( 'oc_reject_' . $post->ID );
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="oc_reject_vendor"/>';
			echo '<input type="hidden" name="post" value="' . esc_attr( $post->ID ) . '"/>';
			echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '"/>';
			echo '<p><label for="oc_rejection_note"><strong>' . esc_html__( 'Reason (required, sent to vendor):', 'owambe-connect-core' ) . '</strong></label>';
			echo '<textarea id="oc_rejection_note" name="oc_rejection_note" rows="3" minlength="10" required style="width:100%" placeholder="' . esc_attr__( 'Explain what the vendor needs to fix. Minimum 10 characters.', 'owambe-connect-core' ) . '"></textarea></p>';
			echo '<p><button type="submit" class="button">' . esc_html__( 'Reject with reason', 'owambe-connect-core' ) . '</button></p>';
			echo '</form>';
		}
	}

	public function render_profile_box( $post ) {
		wp_nonce_field( 'oc_save_profile', 'oc_profile_nonce' );
		echo '<table class="form-table"><tbody>';
		foreach ( oc_vendor_fields() as $key => $field ) {
			if ( 'image' === $field['type'] || '_oc_rejection_note' === $key ) continue;
			$value = get_post_meta( $post->ID, $key, true );
			echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';

			if ( 'textarea' === $field['type'] ) {
				echo '<textarea name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" rows="4" class="large-text">' . esc_textarea( $value ) . '</textarea>';
			} elseif ( 'select' === $field['type'] ) {
				echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '">';
				echo '<option value="">' . esc_html__( '— Select —', 'owambe-connect-core' ) . '</option>';
				foreach ( oc_price_range_options() as $k => $label ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $value, $k, false ), esc_html( $label ) );
				}
				echo '</select>';
			} elseif ( 'multi' === $field['type'] ) {
				$vals = is_array( $value ) ? $value : [];
				echo '<input type="text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( implode( ', ', $vals ) ) . '" class="regular-text"/>';
				echo '<p class="description">' . esc_html__( 'Comma-separated.', 'owambe-connect-core' ) . '</p>';
			} elseif ( 'bool' === $field['type'] ) {
				echo '<label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( $value, 1, false ) . '/> ' . esc_html__( 'Yes', 'owambe-connect-core' ) . '</label>';
			} else {
				echo '<input type="' . esc_attr( $field['type'] ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text"/>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['oc_profile_nonce'] ) || ! wp_verify_nonce( $_POST['oc_profile_nonce'], 'oc_save_profile' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		foreach ( oc_vendor_fields() as $key => $field ) {
			if ( 'image' === $field['type'] || '_oc_rejection_note' === $key ) continue;
			if ( ! isset( $_POST[ $key ] ) ) {
				if ( 'bool' === $field['type'] ) update_post_meta( $post_id, $key, 0 );
				continue;
			}
			$value     = wp_unslash( $_POST[ $key ] );
			$sanitizer = $field['sanitize'];
			$value     = is_callable( $sanitizer ) ? call_user_func( $sanitizer, $value ) : sanitize_text_field( (string) $value );
			update_post_meta( $post_id, $key, $value );
		}
	}

	public function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || OC_CPT !== $screen->post_type ) return;
		wp_enqueue_style( 'oc-admin', OC_PLUGIN_URL . 'assets/css/oc-admin.css', [], OC_VERSION );
	}

	public function register_dashboard_widget() {
		if ( ! current_user_can( 'edit_posts' ) ) return;
		wp_add_dashboard_widget( 'oc_dashboard_widget', __( 'Owambe Connect — Marketplace Overview', 'owambe-connect-core' ), [ $this, 'render_dashboard_widget' ] );
	}

	public function render_dashboard_widget() {
		$counts    = wp_count_posts( OC_CPT );
		$pending   = isset( $counts->{OC_STATUS_PENDING} )  ? (int) $counts->{OC_STATUS_PENDING}  : 0;
		$approved  = isset( $counts->{OC_STATUS_APPROVED} ) ? (int) $counts->{OC_STATUS_APPROVED} : 0;
		$rejected  = isset( $counts->{OC_STATUS_REJECTED} ) ? (int) $counts->{OC_STATUS_REJECTED} : 0;
		?>
		<style>
			.oc-dw { margin:-12px; }
			.oc-dw-stats { display:grid; grid-template-columns:repeat(3, 1fr); gap:1px; background:#E4DDD2; border-bottom:1px solid #E4DDD2; }
			.oc-dw-stat { background:#fff; padding:18px; text-align:center; }
			.oc-dw-stat strong { display:block; font-size:1.8rem; font-family:Georgia, serif; line-height:1; margin-bottom:4px; }
			.oc-dw-stat.is-pending strong  { color:#B8860B; }
			.oc-dw-stat.is-approved strong { color:#2E7D5B; }
			.oc-dw-stat.is-rejected strong { color:#B0354F; }
			.oc-dw-stat span { font-size:11px; text-transform:uppercase; letter-spacing:0.1em; color:#6B6361; }
			.oc-dw-actions { padding:14px 18px; display:flex; flex-wrap:wrap; gap:8px; }
			.oc-dw-actions .button { background:#fff; }
			.oc-dw-actions .button-primary { background:#6E0F2C; border-color:#6E0F2C; color:#fff; }
		</style>
		<div class="oc-dw">
			<div class="oc-dw-stats">
				<div class="oc-dw-stat is-pending">
					<strong><?php echo esc_html( $pending ); ?></strong>
					<span><?php esc_html_e( 'Pending', 'owambe-connect-core' ); ?></span>
				</div>
				<div class="oc-dw-stat is-approved">
					<strong><?php echo esc_html( $approved ); ?></strong>
					<span><?php esc_html_e( 'Live', 'owambe-connect-core' ); ?></span>
				</div>
				<div class="oc-dw-stat is-rejected">
					<strong><?php echo esc_html( $rejected ); ?></strong>
					<span><?php esc_html_e( 'Needs changes', 'owambe-connect-core' ); ?></span>
				</div>
			</div>
			<div class="oc-dw-actions">
				<?php if ( $pending ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_status=' . OC_STATUS_PENDING . '&post_type=' . OC_CPT ) ); ?>">
						<?php
						/* translators: %d: pending count */
						printf( esc_html__( 'Review %d pending', 'owambe-connect-core' ), $pending );
						?>
					</a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=oc-add-vendor' ) ); ?>"><?php esc_html_e( 'Add vendor', 'owambe-connect-core' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . OC_CPT . '&page=oc-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'owambe-connect-core' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . OC_CPT . '&page=oc-developer-guide' ) ); ?>"><?php esc_html_e( 'Developer Guide', 'owambe-connect-core' ); ?></a>
			</div>
		</div>
		<?php
	}
}
