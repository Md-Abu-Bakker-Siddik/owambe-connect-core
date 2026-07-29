<?php
/**
 * Vendors → Verification — the admin review queue for vendor verification.
 *
 * Vendors land here after uploading their ID/business document (and paying the
 * one-time fee when Settings → "Verification fee" is on; free submissions when
 * it's off). The submenu label carries a pending-count badge (same bubble WP
 * uses for comments). For each request the admin sees the vendor, the owner,
 * whether it was paid or free, and the uploaded document — then:
 *
 *  - APPROVE → sets the canonical `_oc_verified` flag, marks the request
 *    'approved', and emails the vendor a confirmation;
 *  - REJECT  → marks the request 'rejected' and emails a short note; the
 *    dashboard card invites the vendor to resubmit a clearer document.
 *
 * The user role is never changed — verification is a badge, not a capability.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Verification {

	const PAGE   = 'oc-verification';
	const ACTION = 'oc_verification_decide';

	public function register() {
		add_action( 'admin_menu', [ $this, 'menu' ], 15 );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
	}

	public function menu() {
		$count = class_exists( 'OC_Vendor_Verification' ) ? (int) OC_Vendor_Verification::pending_count() : 0;
		$label = __( 'Verification', 'owambe-connect-core' );
		if ( $count > 0 ) {
			// The same pending bubble WordPress uses for comments/plugins.
			$label .= sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $count );
		}
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Vendor Verification', 'owambe-connect-core' ),
			$label,
			'manage_options',
			self::PAGE,
			[ $this, 'render' ]
		);
	}

	// ─── Decision handler ────────────────────────────────────────────────────

	public function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}
		$vendor_id = isset( $_POST['vendor'] ) ? absint( $_POST['vendor'] ) : 0;
		check_admin_referer( self::ACTION . '_' . $vendor_id );

		$vendor   = $vendor_id ? get_post( $vendor_id ) : null;
		$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		if ( ! $vendor || OC_CPT !== $vendor->post_type || ! in_array( $decision, [ 'approve', 'reject' ], true ) ) {
			$this->back( 'verif_error' );
		}

		$owner = get_userdata( (int) $vendor->post_author );
		$to    = ( $owner && is_email( $owner->user_email ) ) ? $owner->user_email : '';
		$site  = get_bloginfo( 'name' );

		if ( 'approve' === $decision ) {
			update_post_meta( $vendor_id, '_oc_verified', 1 );
			update_post_meta( $vendor_id, OC_Vendor_Verification::META_REQUEST, 'approved' );
			if ( $owner ) {
				update_user_meta( $owner->ID, OC_Vendor_Verification::META_STATUS, 'approved' );
			}

			$sent = false;
			if ( '' !== $to && class_exists( 'OC_Mail' ) ) {
				/* translators: %s: site name */
				$subject = sprintf( __( 'You are now a Verified Vendor — %s', 'owambe-connect-core' ), $site );
				$body    = '<h2>' . esc_html__( 'Congratulations — you\'re verified! ✓', 'owambe-connect-core' ) . '</h2>';
				$body   .= '<p>' . sprintf(
					/* translators: 1: business name, 2: site name */
					esc_html__( 'We\'ve reviewed your documents and %1$s now carries the Verified Vendor badge on %2$s.', 'owambe-connect-core' ),
					'<strong>' . esc_html( $vendor->post_title ) . '</strong>',
					esc_html( $site )
				) . '</p>';
				$body   .= '<p>' . esc_html__( 'The ✓ badge now shows on your profile and in the vendor directory — it tells clients your business has been checked and can be trusted.', 'owambe-connect-core' ) . '</p>';
				$sent    = (bool) OC_Mail::send( $to, $subject, $body );
			}

			/**
			 * Fires when an admin approves a vendor's verification request.
			 *
			 * @param int  $vendor_id
			 * @param bool $sent Whether the confirmation email went out.
			 */
			do_action( 'oc_verification_approved', $vendor_id, $sent );

			if ( function_exists( 'oc_debug_log' ) ) {
				oc_debug_log( 'verification approved', [ 'vendor' => $vendor_id, 'to' => $to, 'mail_sent' => $sent ], true );
			}
			$this->back( 'verif_approved' );
		}

		// Reject — request closed; the dashboard card invites a resubmission.
		update_post_meta( $vendor_id, OC_Vendor_Verification::META_REQUEST, 'rejected' );
		if ( $owner ) {
			update_user_meta( $owner->ID, OC_Vendor_Verification::META_STATUS, 'rejected' );
		}
		if ( '' !== $to && class_exists( 'OC_Mail' ) ) {
			/* translators: %s: site name */
			$subject = sprintf( __( 'About your verification request — %s', 'owambe-connect-core' ), $site );
			$body    = '<p>' . sprintf(
				/* translators: %s: business name */
				esc_html__( 'We couldn\'t verify %s from the document provided. Please upload a clearer ID or business document from your dashboard and we\'ll take another look.', 'owambe-connect-core' ),
				'<strong>' . esc_html( $vendor->post_title ) . '</strong>'
			) . '</p>';
			OC_Mail::send( $to, $subject, $body );
		}

		/**
		 * Fires when an admin rejects a vendor's verification request.
		 *
		 * @param int $vendor_id
		 */
		do_action( 'oc_verification_rejected', $vendor_id );

		if ( function_exists( 'oc_debug_log' ) ) {
			oc_debug_log( 'verification rejected', [ 'vendor' => $vendor_id ], true );
		}
		$this->back( 'verif_rejected' );
	}

	private function back( $msg ) {
		$to = wp_get_referer() ?: admin_url( 'edit.php?post_type=' . OC_CPT . '&page=' . self::PAGE );
		wp_safe_redirect( add_query_arg( 'oc_admin_msg', $msg, $to ) );
		exit;
	}

	// ─── Page ────────────────────────────────────────────────────────────────

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$ids = OC_Vendor_Verification::pending_vendor_ids();
		?>
		<div class="wrap oc-verifq">
			<h1><?php esc_html_e( 'Vendor Verification', 'owambe-connect-core' ); ?></h1>
			<?php $this->notices(); ?>
			<p class="description" style="max-width:820px;">
				<?php esc_html_e( 'Requests below have a document on file and are waiting for review. Approving awards the ✓ Verified badge and emails the vendor; rejecting closes the request and invites them to resubmit a clearer document.', 'owambe-connect-core' ); ?>
			</p>

			<?php if ( empty( $ids ) ) : ?>
				<div class="oc-verifq__empty">
					<span class="dashicons dashicons-yes-alt"></span>
					<p><?php esc_html_e( 'All caught up — no verification requests waiting.', 'owambe-connect-core' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Vendor', 'owambe-connect-core' ); ?></th>
							<th><?php esc_html_e( 'Owner', 'owambe-connect-core' ); ?></th>
							<th style="width:12%;"><?php esc_html_e( 'Request', 'owambe-connect-core' ); ?></th>
							<th style="width:18%;"><?php esc_html_e( 'Document', 'owambe-connect-core' ); ?></th>
							<th style="width:14%;"><?php esc_html_e( 'Submitted', 'owambe-connect-core' ); ?></th>
							<th style="width:18%;"><?php esc_html_e( 'Decision', 'owambe-connect-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $ids as $vid ) :
							$vendor = get_post( $vid );
							if ( ! $vendor ) {
								continue;
							}
							$owner   = get_userdata( (int) $vendor->post_author );
							$request = (string) get_post_meta( $vid, OC_Vendor_Verification::META_REQUEST, true );
							$doc_id  = (int) get_post_meta( $vid, OC_Vendor_Verification::META_DOC, true );
							$doc_url = $doc_id ? wp_get_attachment_url( $doc_id ) : '';
							$doc     = $doc_id ? get_post( $doc_id ) : null;
							$when    = $doc ? date_i18n( get_option( 'date_format' ), strtotime( $doc->post_date ) ) : '—';
							?>
							<tr>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $vid ) ); ?>"><strong><?php echo esc_html( $vendor->post_title ); ?></strong></a>
								</td>
								<td>
									<?php if ( $owner ) : ?>
										<?php echo esc_html( $owner->display_name ); ?><br/>
										<a href="mailto:<?php echo esc_attr( $owner->user_email ); ?>"><?php echo esc_html( $owner->user_email ); ?></a>
									<?php else : ?>
										<span style="color:#999;">—</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( 'paid' === $request ) : ?>
										<span class="oc-verifq__chip oc-verifq__chip--paid"><?php echo esc_html( sprintf( /* translators: %s: fee, e.g. £15.00 */ __( 'Paid %s', 'owambe-connect-core' ), OC_Vendor_Verification::fee_display() ) ); ?></span>
									<?php else : ?>
										<span class="oc-verifq__chip"><?php esc_html_e( 'Free submission', 'owambe-connect-core' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $doc_url ) : ?>
										<a class="button button-small" href="<?php echo esc_url( $doc_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View document', 'owambe-connect-core' ); ?></a>
									<?php else : ?>
										<span style="color:#b32d2e;font-weight:600;"><?php esc_html_e( 'No document!', 'owambe-connect-core' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $when ); ?></td>
								<td>
									<div class="oc-verifq__actions">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Approve — award the Verified badge and email the vendor?', 'owambe-connect-core' ) ); ?>');">
											<?php wp_nonce_field( self::ACTION . '_' . $vid ); ?>
											<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
											<input type="hidden" name="vendor" value="<?php echo (int) $vid; ?>" />
											<input type="hidden" name="decision" value="approve" />
											<button type="submit" class="button button-primary"><?php esc_html_e( 'Approve', 'owambe-connect-core' ); ?></button>
										</form>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Reject this request? The vendor is emailed and can resubmit.', 'owambe-connect-core' ) ); ?>');">
											<?php wp_nonce_field( self::ACTION . '_' . $vid ); ?>
											<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
											<input type="hidden" name="vendor" value="<?php echo (int) $vid; ?>" />
											<input type="hidden" name="decision" value="reject" />
											<button type="submit" class="button oc-verifq__reject"><?php esc_html_e( 'Reject', 'owambe-connect-core' ); ?></button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<style>
			.oc-verifq__empty{background:#fff;border:1px solid #E4DDD2;border-radius:10px;padding:36px;text-align:center;color:#6B6361;max-width:520px;margin-top:14px;}
			.oc-verifq__empty .dashicons{font-size:34px;width:34px;height:34px;color:#2E7D52;}
			.oc-verifq__chip{display:inline-block;padding:2px 10px;border-radius:999px;font-size:11.5px;font-weight:600;background:#F0EBE4;color:#6B6361;}
			.oc-verifq__chip--paid{background:#E9F5EF;color:#1F4D3A;}
			.oc-verifq__actions{display:flex;gap:6px;}
			.oc-verifq__actions form{margin:0;}
			.oc-verifq__reject{color:#b32d2e;border-color:#b32d2e;}
		</style>
		<?php
	}

	private function notices() {
		if ( empty( $_GET['oc_admin_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$map = [
			'verif_approved' => [ 'success', __( 'Vendor verified — the badge is live and the confirmation email was sent.', 'owambe-connect-core' ) ],
			'verif_rejected' => [ 'warning', __( 'Request rejected — the vendor was notified and can resubmit.', 'owambe-connect-core' ) ],
			'verif_error'    => [ 'error', __( 'Could not process that decision — invalid request.', 'owambe-connect-core' ) ],
		];
		$key = sanitize_key( wp_unslash( $_GET['oc_admin_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $map[ $key ] ) ) {
			return;
		}
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $map[ $key ][0] ),
			esc_html( $map[ $key ][1] )
		);
	}
}
