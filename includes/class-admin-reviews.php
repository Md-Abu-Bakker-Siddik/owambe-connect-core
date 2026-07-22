<?php
/**
 * Admin-side review moderation: "Reviews" submenu with pending badge,
 * approve/reject/restore admin-post handlers (per-ID nonces), paginated
 * tables (Pending / Approved / Rejected), and result notices.
 *
 * Status changes here (pending → publish, → trash, → pending) trigger
 * OC_Reviews' transition handler, which recomputes the vendor rating
 * aggregate and sends the vendor email — this class only moves posts
 * and renders UI.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Reviews {

	const PAGE_SLUG = 'oc-reviews';
	const PER_PAGE  = 25;

	public function register() {
		add_action( 'admin_menu',                   [ $this, 'admin_menu' ] );
		add_action( 'admin_post_oc_review_approve', [ $this, 'approve' ] );
		add_action( 'admin_post_oc_review_reject',  [ $this, 'reject' ] );
		add_action( 'admin_post_oc_review_restore', [ $this, 'restore' ] );
		add_action( 'admin_notices',                [ $this, 'notices' ] );

		// When a vendor is deleted, trash its reviews so orphan review rows
		// (and stale aggregate meta on a now-nonexistent post) never accumulate.
		add_action( 'before_delete_post', [ $this, 'cleanup_vendor_reviews' ], 10, 2 );
	}

	public function admin_menu() {
		// Same yellow "awaiting moderation" pill as the vendor pending badge
		// (class-admin.php) and the enquiries submenu (class-enquiry-log.php).
		$pending = OC_Reviews::pending_count();
		$badge   = $pending > 0
			? sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $pending )
			: '';
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Reviews', 'owambe-connect-core' ),
			__( 'Reviews', 'owambe-connect-core' ) . $badge,
			'edit_posts',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function approve() {
		$id = isset( $_GET['review'] ) ? absint( $_GET['review'] ) : 0;
		check_admin_referer( 'oc_review_approve_' . $id );
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( -1 );

		$review = get_post( $id );
		if ( ! $review || 'oc_review' !== $review->post_type ) wp_die( -1 );

		// Refuse to publish a review whose vendor no longer exists — that would
		// write rating aggregate meta onto a nonexistent post ID. Auto-trash the
		// orphan so it clears out of the queue.
		$vendor_id = (int) get_post_meta( $id, OC_Reviews::META_VENDOR, true );
		$vendor    = $vendor_id ? get_post( $vendor_id ) : null;
		if ( ! $vendor || OC_CPT !== $vendor->post_type ) {
			wp_trash_post( $id );
			$this->redirect_msg( 'review_vendor_missing' );
		}

		// Status change triggers transition_post_status → OC_Reviews recompute + vendor email.
		wp_update_post( [ 'ID' => $id, 'post_status' => 'publish' ] );
		$this->redirect_msg( 'review_approved' );
	}

	public function reject() {
		$id = isset( $_GET['review'] ) ? absint( $_GET['review'] ) : 0;
		check_admin_referer( 'oc_review_reject_' . $id );
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( -1 );

		$review = get_post( $id );
		if ( ! $review || 'oc_review' !== $review->post_type ) wp_die( -1 );

		// Trash (not delete) so a mistaken rejection is recoverable; also works
		// for removing an already-approved review, which un-counts it on recompute.
		wp_trash_post( $id );
		$this->redirect_msg( 'review_rejected' );
	}

	public function restore() {
		$id = isset( $_GET['review'] ) ? absint( $_GET['review'] ) : 0;
		check_admin_referer( 'oc_review_restore_' . $id );
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( -1 );

		$review = get_post( $id );
		if ( ! $review || 'oc_review' !== $review->post_type ) wp_die( -1 );

		// Restore to PENDING (not 'draft', which wp_untrash_post uses on WP 5.6+)
		// so it re-enters the moderation queue for a fresh decision.
		wp_update_post( [ 'ID' => $id, 'post_status' => 'pending' ] );
		$this->redirect_msg( 'review_restored' );
	}

	/** Trash a deleted vendor's reviews so no orphans/stale aggregates remain. */
	public function cleanup_vendor_reviews( $post_id, $post = null ) {
		$post = $post ?: get_post( $post_id );
		if ( ! $post || OC_CPT !== $post->post_type ) {
			return;
		}
		$reviews = get_posts( [
			'post_type'   => 'oc_review',
			'post_status' => [ 'pending', 'publish', 'trash' ],
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_key'    => OC_Reviews::META_VENDOR,
			'meta_value'  => (int) $post_id,
		] );
		foreach ( $reviews as $rid ) {
			wp_delete_post( (int) $rid, true );
		}
	}

	private function redirect_msg( $msg ) {
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . OC_CPT . '&page=' . self::PAGE_SLUG . '&oc_admin_msg=' . $msg ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( esc_html__( 'Unauthorised.', 'owambe-connect-core' ) );

		$paged = isset( $_GET['rpage'] ) ? max( 1, absint( $_GET['rpage'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// oldest-first for pending clears the backlog fairly; newest-first elsewhere.
		$sections = [
			[ 'label' => __( 'Pending approval', 'owambe-connect-core' ), 'status' => 'pending', 'order' => 'ASC',  'context' => 'pending'  ],
			[ 'label' => __( 'Approved',         'owambe-connect-core' ), 'status' => 'publish', 'order' => 'DESC', 'context' => 'approved' ],
			[ 'label' => __( 'Rejected',         'owambe-connect-core' ), 'status' => 'trash',   'order' => 'DESC', 'context' => 'rejected' ],
		];
		?>
		<div class="wrap oc-rev">
			<?php $this->page_styles(); ?>
			<h1>
				<span class="dashicons dashicons-star-filled" style="color:#6E0F2C;font-size:28px"></span>
				<?php esc_html_e( 'Reviews', 'owambe-connect-core' ); ?>
			</h1>
			<p class="oc-rev-sub"><?php esc_html_e( 'Moderate customer reviews before they appear on vendor profiles.', 'owambe-connect-core' ); ?></p>

			<?php foreach ( $sections as $section ) :
				$q = new WP_Query( [
					'post_type'      => 'oc_review',
					'post_status'    => $section['status'],
					'posts_per_page' => self::PER_PAGE,
					'paged'          => $paged,
					'orderby'        => 'date',
					'order'          => $section['order'],
				] );
				?>
				<div class="oc-rev-section">
					<h2>
						<?php echo esc_html( $section['label'] ); ?>
						<span class="oc-rev-count"><?php echo esc_html( number_format_i18n( (int) $q->found_posts ) ); ?></span>
					</h2>
					<?php
					$this->render_table( $q->posts, $section['context'] );
					$this->render_pager( $q, $paged );
					wp_reset_postdata();
					?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/** Brand styles for the Reviews screen — mirrors the Analytics palette. */
	private function page_styles() {
		?>
		<style id="oc-reviews-styles">
			.oc-rev h1 { display:flex; align-items:center; gap:10px; margin-bottom:6px; color:#1F1B1A; }
			.oc-rev .oc-rev-sub { margin:0 0 18px; color:#6B6361; }
			.oc-rev-section { background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:18px 20px; margin:0 0 16px; }
			.oc-rev-section > h2 { display:flex; align-items:center; gap:8px; font-family:Georgia, serif; color:#6E0F2C; margin:0 0 14px; padding-bottom:10px; border-bottom:2px solid #C9A961; font-size:1.05rem; }
			.oc-rev-count { font-family:inherit; font-size:12px; font-weight:600; color:#6E0F2C; background:#FAF0F2; border:1px solid #E9CBD3; border-radius:999px; padding:1px 9px; }
			.oc-rev table.widefat { border-color:#E4DDD2; }
			.oc-rev table.widefat thead th { background:#FAF7F2; color:#1F1B1A; font-weight:600; }
			.oc-rev .button-primary { background:#6E0F2C; border-color:#6E0F2C; box-shadow:none; text-shadow:none; }
			.oc-rev .button-primary:hover, .oc-rev .button-primary:focus { background:#57091f; border-color:#57091f; }
			.oc-rev-pager span { color:#6B6361; }
			/* Star rating: base greyed, gold fill layered on top (admin has no front-end CSS). */
			.oc-rev .oc-stars { position:relative; display:inline-block; font-size:15px; line-height:1; letter-spacing:2px; color:#D9CFC2; white-space:nowrap; }
			.oc-rev .oc-stars__fill { position:absolute; top:0; left:0; overflow:hidden; color:#C9A961; }
			.oc-rev .oc-stars__count { color:#6B6361; letter-spacing:normal; }
		</style>
		<?php
	}

	/**
	 * Render a moderation table for one status.
	 *
	 * @param WP_Post[] $reviews
	 * @param string    $context pending|approved|rejected — decides the actions column.
	 */
	private function render_table( $reviews, $context ) {
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Reviewer',  'owambe-connect-core' ); ?></th>
					<th><?php esc_html_e( 'Vendor',    'owambe-connect-core' ); ?></th>
					<th><?php esc_html_e( 'Rating',    'owambe-connect-core' ); ?></th>
					<th><?php esc_html_e( 'Review',    'owambe-connect-core' ); ?></th>
					<th><?php esc_html_e( 'Submitted', 'owambe-connect-core' ); ?></th>
					<th><?php esc_html_e( 'Actions',   'owambe-connect-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $reviews ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'Nothing here.', 'owambe-connect-core' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $reviews as $review ) : ?>
						<tr>
							<?php $this->render_common_cells( $review ); ?>
							<td><?php $this->render_actions( $review, $context ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_actions( $review, $context ) {
		$approve = wp_nonce_url( admin_url( 'admin-post.php?action=oc_review_approve&review=' . $review->ID ), 'oc_review_approve_' . $review->ID );
		$reject  = wp_nonce_url( admin_url( 'admin-post.php?action=oc_review_reject&review=' . $review->ID ), 'oc_review_reject_' . $review->ID );
		$restore = wp_nonce_url( admin_url( 'admin-post.php?action=oc_review_restore&review=' . $review->ID ), 'oc_review_restore_' . $review->ID );
		$confirm = esc_js( __( 'Reject and remove this review?', 'owambe-connect-core' ) );

		if ( 'pending' === $context ) {
			printf( '<a href="%s" class="button button-primary">%s</a> ', esc_url( $approve ), esc_html__( 'Approve', 'owambe-connect-core' ) );
			printf( '<a href="%s" style="color:#b32d2e;margin-left:8px;" onclick="return confirm(\'%s\');">%s</a>', esc_url( $reject ), $confirm, esc_html__( 'Reject', 'owambe-connect-core' ) );
		} elseif ( 'approved' === $context ) {
			printf( '<a href="%s" style="color:#b32d2e;" onclick="return confirm(\'%s\');">%s</a>', esc_url( $reject ), $confirm, esc_html__( 'Remove', 'owambe-connect-core' ) );
		} else { // rejected
			printf( '<a href="%s" class="button">%s</a>', esc_url( $restore ), esc_html__( 'Restore to pending', 'owambe-connect-core' ) );
		}
	}

	private function render_pager( $q, $paged ) {
		$total = (int) $q->max_num_pages;
		if ( $total <= 1 ) {
			return;
		}
		$base = admin_url( 'edit.php?post_type=' . OC_CPT . '&page=' . self::PAGE_SLUG );
		echo '<p class="oc-rev-pager" style="margin:8px 0 0;">';
		if ( $paged > 1 ) {
			printf( '<a class="button" href="%s">%s</a> ', esc_url( add_query_arg( 'rpage', $paged - 1, $base ) ), esc_html__( '‹ Newer', 'owambe-connect-core' ) );
		}
		printf( '<span style="margin:0 8px;color:#666;">%s</span>', esc_html( sprintf( __( 'Page %1$d of %2$d', 'owambe-connect-core' ), $paged, $total ) ) );
		if ( $paged < $total ) {
			printf( ' <a class="button" href="%s">%s</a>', esc_url( add_query_arg( 'rpage', $paged + 1, $base ) ), esc_html__( 'Older ›', 'owambe-connect-core' ) );
		}
		echo '</p>';
	}

	/** Reviewer / Vendor / Rating / Review / Submitted cells shared by all tables. */
	private function render_common_cells( $review ) {
		$author    = get_userdata( $review->post_author );
		$vendor_id = (int) get_post_meta( $review->ID, '_oc_review_vendor_id', true );
		$rating    = (int) get_post_meta( $review->ID, '_oc_review_rating', true );
		?>
		<td><?php echo esc_html( $author ? $author->display_name : __( 'Unknown', 'owambe-connect-core' ) ); ?></td>
		<td>
			<?php if ( $vendor_id && get_post( $vendor_id ) ) : ?>
				<a href="<?php echo esc_url( get_permalink( $vendor_id ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $vendor_id ) ); ?></a>
			<?php else : ?>
				<span style="color:#b32d2e;"><?php esc_html_e( '(vendor deleted)', 'owambe-connect-core' ); ?></span>
			<?php endif; ?>
		</td>
		<td><?php echo wp_kses_post( OC_Reviews::stars_html( $rating ) ); ?></td>
		<td><?php echo esc_html( wp_trim_words( $review->post_content, 30 ) ); ?></td>
		<td><?php echo esc_html( get_the_date( '', $review ) ); ?></td>
		<?php
	}

	public function notices() {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) return;
		if ( ! isset( $_GET['oc_admin_msg'] ) ) return;
		$map = [
			'review_approved'       => [ 'success', __( 'Review approved and published.', 'owambe-connect-core' ) ],
			'review_rejected'       => [ 'success', __( 'Review rejected.', 'owambe-connect-core' ) ],
			'review_restored'       => [ 'success', __( 'Review restored to pending.', 'owambe-connect-core' ) ],
			'review_vendor_missing' => [ 'warning', __( 'That review\'s vendor no longer exists — the review was removed instead of published.', 'owambe-connect-core' ) ],
		];
		$key = sanitize_key( $_GET['oc_admin_msg'] );
		if ( isset( $map[ $key ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $map[ $key ][0] ), esc_html( $map[ $key ][1] ) );
		}
	}
}
