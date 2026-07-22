<?php
/**
 * Vendor reviews — CPT, public submission handler, and rating aggregates.
 *
 * Signed-in clients leave a star + text review on a vendor profile. Reviews
 * land as 'pending' and only render once an admin approves them ('publish';
 * rejected = 'trash'). Every status transition funnels through on_transition()
 * so aggregates, the vendor email, and the activity log stay in sync no
 * matter which path caused the change (moderation screen, quick edit, code).
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Reviews {

	const CPT             = 'oc_review';
	const ACTION_SUBMIT   = 'oc_submit_review';
	const META_VENDOR     = '_oc_review_vendor_id';
	const META_RATING     = '_oc_review_rating';
	const META_AVG        = '_oc_rating_avg';
	const META_COUNT      = '_oc_rating_count';

	public function register() {
		add_action( 'init', [ __CLASS__, 'register_post_type' ] );

		add_action( 'admin_post_' . self::ACTION_SUBMIT,        [ $this, 'submit' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION_SUBMIT, [ $this, 'submit_nopriv' ] );

		// Single source of truth for review status transitions: recomputes the
		// vendor aggregate, emails the reviewed vendor, logs activity.
		add_action( 'transition_post_status', [ __CLASS__, 'on_transition' ], 10, 3 );

		// HARD deletes bypass transition_post_status (wp_delete_post with
		// $force, or emptying the trash), which would leave the vendor's
		// cached _oc_rating_avg/_oc_rating_count stale. Capture the vendor id
		// before the meta disappears, recompute after the row is gone.
		add_action( 'before_delete_post', [ __CLASS__, 'capture_vendor_before_delete' ], 10, 2 );
		add_action( 'deleted_post',       [ __CLASS__, 'recompute_after_delete' ], 10, 2 );
	}

	/** @var array<int,int> review_id => vendor_id captured pre-delete. */
	private static $pending_delete_vendors = [];

	public static function capture_vendor_before_delete( $post_id, $post = null ) {
		$post = $post ?: get_post( $post_id );
		if ( $post && self::CPT === $post->post_type ) {
			self::$pending_delete_vendors[ (int) $post_id ] = (int) get_post_meta( $post_id, self::META_VENDOR, true );
		}
	}

	public static function recompute_after_delete( $post_id, $post = null ) {
		$post_id = (int) $post_id;
		if ( isset( self::$pending_delete_vendors[ $post_id ] ) ) {
			$vendor_id = self::$pending_delete_vendors[ $post_id ];
			unset( self::$pending_delete_vendors[ $post_id ] );
			if ( $vendor_id ) {
				self::recompute( $vendor_id );
			}
		}
	}

	/**
	 * Hidden CPT — no admin UI (moderation lives in OC_Admin_Reviews), never
	 * publicly queryable; reviews only ever render through the profile partials.
	 */
	public static function register_post_type() {
		register_post_type( self::CPT, [
			'labels' => [
				'name'          => __( 'Reviews', 'owambe-connect-core' ),
				'singular_name' => __( 'Review',  'owambe-connect-core' ),
			],
			'public'              => false,
			'show_ui'             => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'rewrite'             => false,
			'supports'            => [ 'title', 'editor', 'author' ],
			'capability_type'     => 'post',
		] );
	}

	// ──────────────────────── Submission ────────────────────────

	/**
	 * Logged-out hit on the submit action — bounce to the client login page.
	 * If we know which vendor they came from, round-trip them back to the
	 * reviews section after signing in.
	 */
	public function submit_nopriv() {
		$vendor_id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;
		$args      = [ 'oc_error' => rawurlencode( __( 'Sign in first to leave a review.', 'owambe-connect-core' ) ) ];
		if ( $vendor_id && get_permalink( $vendor_id ) ) {
			$args['redirect_to'] = rawurlencode( get_permalink( $vendor_id ) . '#reviews' );
		}
		wp_safe_redirect( add_query_arg( $args, oc_page_url( 'client-login' ) ) );
		exit;
	}

	/**
	 * admin_post handler for the profile review form.
	 *
	 * Validation order: nonce → honeypot → auth → role → reCAPTCHA →
	 * rate-limit → vendor → rating → text → duplicate. Any failure redirects
	 * back to the vendor's #reviews section with ?oc_error= and the typed
	 * values preserved as query args so nothing the user wrote is lost.
	 */
	public function submit() {
		$vendor_id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;

		if ( ! isset( $_POST['oc_review_nonce'] ) || ! wp_verify_nonce( $_POST['oc_review_nonce'], self::ACTION_SUBMIT ) ) {
			$this->redirect_back( $vendor_id, __( 'Security check failed. Please try again.', 'owambe-connect-core' ) );
		}

		// Honeypot — silently succeed (don't tell bots they're caught).
		if ( ! empty( $_POST['oc_hp'] ) ) {
			$this->redirect_back( $vendor_id, '', __( 'Thanks — your review has been submitted and is awaiting approval.', 'owambe-connect-core' ) );
		}

		if ( ! is_user_logged_in() ) {
			$this->submit_nopriv();
		}

		// Clients only (admins may test-drive the form too).
		$user = wp_get_current_user();
		if ( ! in_array( OC_CLIENT_ROLE, (array) $user->roles, true ) && ! current_user_can( 'manage_options' ) ) {
			$this->redirect_back( $vendor_id, __( 'Reviews are for client accounts.', 'owambe-connect-core' ) );
		}

		// reCAPTCHA v3.
		$rc_token = isset( $_POST['oc_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['oc_recaptcha_token'] ) ) : '';
		if ( ! oc_verify_recaptcha( $rc_token ) ) {
			$this->redirect_back( $vendor_id, __( 'Spam check failed. Please try again.', 'owambe-connect-core' ) );
		}

		// 1-per-60s rate limit so double-clicks and rapid-fire bots don't pile up.
		$rate_key = 'oc_review_rl_' . get_current_user_id();
		if ( get_transient( $rate_key ) ) {
			$this->redirect_back( $vendor_id, __( 'You submitted a review moments ago — please wait a minute before trying again.', 'owambe-connect-core' ) );
		}

		// Approved-vendor targets only.
		$vendor = $vendor_id ? get_post( $vendor_id ) : null;
		if ( ! $vendor || OC_CPT !== $vendor->post_type || OC_STATUS_APPROVED !== $vendor->post_status ) {
			$this->redirect_back( 0, __( 'That vendor cannot be reviewed.', 'owambe-connect-core' ) );
		}

		$rating = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
		if ( $rating < 1 || $rating > 5 ) {
			$this->redirect_back( $vendor_id, __( 'Please pick a star rating.', 'owambe-connect-core' ) );
		}

		$text   = isset( $_POST['review_text'] ) ? wp_strip_all_tags( sanitize_textarea_field( wp_unslash( $_POST['review_text'] ) ) ) : '';
		$length = mb_strlen( $text );
		if ( $length < 20 || $length > 2000 ) {
			$this->redirect_back( $vendor_id, __( 'Please write between 20 and 2000 characters.', 'owambe-connect-core' ) );
		}

		// Atomic per-user+vendor claim to close the check-then-insert race:
		// two truly-parallel submits would each pass user_has_reviewed() (before
		// either row exists) and both insert. The options table's UNIQUE
		// option_name makes add_option() a compare-and-set — only one racer can
		// create the claim; the loser is rejected as a duplicate. The claim is
		// released on every exit path below so a genuine later retry still works
		// (durable one-per-vendor is enforced by user_has_reviewed()).
		$claim_key = 'oc_revclaim_' . $user->ID . '_' . $vendor_id;
		$now       = time();
		if ( ! add_option( $claim_key, $now, '', 'no' ) ) {
			$held = (int) get_option( $claim_key );
			if ( $now - $held < 30 ) {
				$this->redirect_back( $vendor_id, __( 'You submitted a review moments ago — please wait a minute before trying again.', 'owambe-connect-core' ) );
			}
			// Stale claim (a prior attempt died before releasing) — take it over.
			update_option( $claim_key, $now );
		}

		// One review per client per vendor (pending counts too).
		if ( self::user_has_reviewed( $user->ID, $vendor_id ) ) {
			delete_option( $claim_key );
			$this->redirect_back( $vendor_id, __( 'You have already reviewed this vendor.', 'owambe-connect-core' ) );
		}

		set_transient( $rate_key, 1, MINUTE_IN_SECONDS );

		$review_id = wp_insert_post( wp_slash( [
			'post_type'    => self::CPT,
			'post_status'  => 'pending',
			'post_author'  => $user->ID,
			/* translators: 1: vendor business name, 2: reviewer display name */
			'post_title'   => sprintf( __( 'Review: %1$s by %2$s', 'owambe-connect-core' ), get_the_title( $vendor ), $user->display_name ),
			'post_content' => $text,
		] ), true );

		if ( is_wp_error( $review_id ) || ! $review_id ) {
			delete_option( $claim_key );
			$this->redirect_back( $vendor_id, __( 'Something went wrong saving your review — please try again.', 'owambe-connect-core' ) );
		}

		update_post_meta( $review_id, self::META_VENDOR, $vendor_id );
		update_post_meta( $review_id, self::META_RATING, $rating );

		delete_option( $claim_key );
		$this->redirect_back( $vendor_id, '', __( 'Thanks — your review has been submitted and is awaiting approval.', 'owambe-connect-core' ) );
	}

	/**
	 * Redirect back to the vendor's #reviews section (referer fallback) with
	 * oc_error/oc_notice. On error the typed rating + text ride along as
	 * query args so the form can re-fill them — never wipe user input.
	 */
	private function redirect_back( $vendor_id, $error = '', $notice = '' ) {
		$base = $vendor_id ? get_permalink( $vendor_id ) : '';
		if ( ! $base ) {
			$base = wp_get_referer() ?: home_url( '/' );
		}
		// Drop any stale round-trip args + fragment before appending fresh ones.
		$base = remove_query_arg( [ 'oc_error', 'oc_notice', 'oc_rating', 'oc_review_text' ], $base );
		$hash = strpos( $base, '#' );
		if ( false !== $hash ) {
			$base = substr( $base, 0, $hash );
		}

		$args = [];
		if ( $error )  $args['oc_error']  = rawurlencode( $error );
		if ( $notice ) $args['oc_notice'] = rawurlencode( $notice );

		if ( $error ) {
			$rating = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
			if ( $rating >= 1 && $rating <= 5 ) {
				$args['oc_rating'] = $rating;
			}
			$text = isset( $_POST['review_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['review_text'] ) ) : '';
			if ( '' !== $text ) {
				$args['oc_review_text'] = rawurlencode( $text );
			}
		}

		wp_safe_redirect( ( $args ? add_query_arg( $args, $base ) : $base ) . '#reviews' );
		exit;
	}

	// ──────────────────────── Transitions ────────────────────────

	/**
	 * Central handler for any oc_review status change (approve, reject,
	 * un-approve, restore). Keeps the vendor's aggregate rating meta correct
	 * whenever a review enters or leaves 'publish', emails the vendor on
	 * first approval, and logs to the vendor activity feed.
	 */
	public static function on_transition( $new_status, $old_status, $post ) {
		if ( ! $post instanceof WP_Post || self::CPT !== $post->post_type ) return;
		if ( $new_status === $old_status ) return;

		$vendor_id = (int) get_post_meta( $post->ID, self::META_VENDOR, true );

		do_action( 'oc_review_status_changed', $post->ID, $new_status, $old_status );

		// Entering or leaving 'publish' changes the published set → recompute.
		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			self::recompute( $vendor_id );
		}

		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			if ( class_exists( 'OC_Mail' ) && method_exists( 'OC_Mail', 'review_approved' ) ) {
				OC_Mail::review_approved( $post->ID );
			}
			if ( $vendor_id && class_exists( 'OC_Vendor_Activity' ) ) {
				try {
					( new OC_Vendor_Activity() )->record( $vendor_id, 'review_approved', [
						/* translators: 1: review post ID, 2: reviewer display name */
						'note' => sprintf( __( 'Review #%1$d by %2$s approved', 'owambe-connect-core' ), $post->ID, get_the_author_meta( 'display_name', $post->post_author ) ),
					] );
				} catch ( \Throwable $e ) {
					// Activity logging must never break the approval itself.
				}
			}
		}
	}

	// ──────────────────────── Aggregates ────────────────────────

	/**
	 * Recompute a vendor's `_oc_rating_avg` (1dp float) + `_oc_rating_count`
	 * from its published reviews. Deletes both metas when no reviews remain
	 * so profiles/cards/schema can simply check for the meta's presence.
	 */
	public static function recompute( $vendor_id ) {
		global $wpdb;

		$vendor_id = absint( $vendor_id );
		if ( ! $vendor_id ) return;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT AVG( CAST( mr.meta_value AS DECIMAL(3,2) ) ) AS avg_rating, COUNT(*) AS total
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} mv ON mv.post_id = p.ID AND mv.meta_key = %s
			 INNER JOIN {$wpdb->postmeta} mr ON mr.post_id = p.ID AND mr.meta_key = %s
			 WHERE p.post_type = %s AND p.post_status = 'publish' AND mv.meta_value = %d",
			self::META_VENDOR,
			self::META_RATING,
			self::CPT,
			$vendor_id
		) );

		$count = $row ? (int) $row->total : 0;
		if ( $count > 0 ) {
			update_post_meta( $vendor_id, self::META_AVG,   round( (float) $row->avg_rating, 1 ) );
			update_post_meta( $vendor_id, self::META_COUNT, $count );
		} else {
			delete_post_meta( $vendor_id, self::META_AVG );
			delete_post_meta( $vendor_id, self::META_COUNT );
		}
	}

	// ──────────────────────── Queries ────────────────────────

	/**
	 * Published reviews for a vendor, newest first.
	 *
	 * @return WP_Post[]
	 */
	public static function for_vendor( $vendor_id, $limit = 20 ) {
		return get_posts( [
			'post_type'   => self::CPT,
			'post_status' => 'publish',
			'numberposts' => (int) $limit,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_key'    => self::META_VENDOR,
			'meta_value'  => absint( $vendor_id ),
		] );
	}

	/** Published-review count from the vendor's aggregate meta (0 when none). */
	public static function count_for_vendor( $vendor_id ) {
		return (int) get_post_meta( absint( $vendor_id ), self::META_COUNT, true );
	}

	/** Site-wide pending review count (moderation menu badge). */
	public static function pending_count() {
		$counts = wp_count_posts( self::CPT );
		return isset( $counts->pending ) ? (int) $counts->pending : 0;
	}

	/**
	 * True when the user already has a review (pending OR published) for this
	 * vendor — enforces one review per client per vendor.
	 */
	public static function user_has_reviewed( $user_id, $vendor_id ) {
		$found = get_posts( [
			'post_type'   => self::CPT,
			'post_status' => [ 'publish', 'pending' ],
			'author'      => (int) $user_id,
			'meta_key'    => self::META_VENDOR,
			'meta_value'  => absint( $vendor_id ),
			'numberposts' => 1,
			'fields'      => 'ids',
		] );
		return ! empty( $found );
	}

	// ──────────────────────── Presentation ────────────────────────

	/**
	 * Star rating markup: a dimmed ★★★★★ base with a gold overlay clipped to
	 * {rating/5*100}% width (theme styles .oc-stars / .oc-stars__fill /
	 * .oc-stars__count). Optionally appends the review count.
	 *
	 * @param float    $rating 0–5 (fractions render as partial stars).
	 * @param int|null $count  Review count to append, or null to omit.
	 * @return string
	 */
	public static function stars_html( $rating, $count = null ) {
		$rating = max( 0, min( 5, (float) $rating ) );
		$pct    = round( $rating / 5 * 100, 2 );
		/* translators: %s: star rating, e.g. 4.5 */
		$label  = sprintf( __( '%s out of 5 stars', 'owambe-connect-core' ), number_format_i18n( $rating, 1 ) );

		$html  = '<span class="oc-stars" role="img" aria-label="' . esc_attr( $label ) . '">';
		$html .= '<span aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span>';
		$html .= '<span class="oc-stars__fill" style="width:' . esc_attr( $pct ) . '%;" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span>';
		if ( null !== $count ) {
			$html .= ' <span class="oc-stars__count">(' . esc_html( number_format_i18n( (int) $count ) ) . ')</span>';
		}
		$html .= '</span>';

		return $html;
	}
}
