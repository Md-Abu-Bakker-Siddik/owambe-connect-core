<?php
/**
 * Vendors → Subscriptions — admin controls over a vendor's subscription state.
 *
 * Stripe stays the source of truth for PAID subscriptions (the webhook mirror
 * in OC_Vendor_Subscription); this page is the admin override lane on top of
 * that mirror:
 *   - assign a plan manually (comped accounts — sets the local mirror only,
 *     no Stripe object is created);
 *   - grant / extend / shorten a free period (the _oc_free_until clock);
 *   - cancel (status → canceled, history kept) or revoke (full local reset).
 *
 * It also exposes the status pill used by BOTH vendor list UIs — the native
 * edit.php column (OC_Admin) and the custom Vendors list (OC_Admin_Vendors_List)
 * — so subscription state is visible at a glance everywhere:
 *   free / active / expiring / expired / cancelled (+ end date).
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Subscriptions {

	const PAGE   = 'oc-subscription';
	const ACTION = 'oc_admin_sub_update';

	/** Days before the free-period end at which the pill flips to "expiring". */
	const EXPIRING_DAYS = 14;

	public function register() {
		add_action( 'admin_menu', [ $this, 'menu' ], 14 );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Subscriptions', 'owambe-connect-core' ),
			__( 'Subscriptions', 'owambe-connect-core' ),
			'manage_options',
			self::PAGE,
			[ $this, 'render' ]
		);
	}

	/** Manage-page URL for one vendor. */
	public static function manage_url( $vendor_id ) {
		return admin_url( 'edit.php?post_type=' . OC_CPT . '&page=' . self::PAGE . '&vendor=' . (int) $vendor_id );
	}

	// ─── State machine ────────────────────────────────────────────────────────

	/**
	 * Resolve a vendor's subscription meta into one display state.
	 *
	 * @param int $vendor_id
	 * @return array{key:string,label:string,end:int,color:string}
	 *         key ∈ none|free|active|expiring|expired|cancelled
	 */
	public static function state( $vendor_id ) {
		$sub    = OC_Vendor_Subscription::get_vendor_subscription( (int) $vendor_id );
		$status = (string) $sub['status'];
		$until  = (int) $sub['free_until'];
		$soon   = time() + self::EXPIRING_DAYS * DAY_IN_SECONDS;

		if ( 'canceled' === $status ) {
			return [ 'key' => 'cancelled', 'label' => __( 'Cancelled', 'owambe-connect-core' ), 'end' => 0, 'color' => '#787069' ];
		}
		if ( 'expired' === $status ) {
			// Set by the daily maintenance sweep when a free period lapses.
			return [ 'key' => 'expired', 'label' => __( 'Expired', 'owambe-connect-core' ), 'end' => $until, 'color' => '#B32D2E' ];
		}
		if ( in_array( $status, OC_Vendor_Subscription::ACTIVE_STATUSES, true ) ) {
			return [ 'key' => 'active', 'label' => __( 'Active', 'owambe-connect-core' ), 'end' => 0, 'color' => '#2E7D52' ];
		}
		if ( 'past_due' === $status ) {
			return [ 'key' => 'expiring', 'label' => __( 'Payment due', 'owambe-connect-core' ), 'end' => 0, 'color' => '#C77D0A' ];
		}
		if ( 'free' === $status ) {
			if ( $until > $soon ) {
				return [ 'key' => 'free', 'label' => __( 'Free', 'owambe-connect-core' ), 'end' => $until, 'color' => '#1D6FA5' ];
			}
			if ( $until > time() ) {
				return [ 'key' => 'expiring', 'label' => __( 'Expiring', 'owambe-connect-core' ), 'end' => $until, 'color' => '#C77D0A' ];
			}
			return [ 'key' => 'expired', 'label' => __( 'Expired', 'owambe-connect-core' ), 'end' => $until, 'color' => '#B32D2E' ];
		}
		return [ 'key' => 'none', 'label' => __( 'No plan', 'owambe-connect-core' ), 'end' => 0, 'color' => '#9A938C' ];
	}

	/**
	 * The status pill (self-contained inline styles so it renders identically
	 * in both list UIs). Links to the manage page when the viewer may manage.
	 *
	 * @param int  $vendor_id
	 * @param bool $link
	 * @return string
	 */
	public static function pill( $vendor_id, $link = true ) {
		$vendor_id = (int) $vendor_id;
		$s         = self::state( $vendor_id );
		$plan      = (string) get_post_meta( $vendor_id, OC_Vendor_Subscription::META_PLAN, true );
		$tier      = OC_Vendor_Subscription::get_tier( $plan );

		$text = $s['label'];
		if ( $tier && in_array( $s['key'], [ 'active', 'expiring' ], true ) ) {
			$text .= ' · ' . $tier['label'];
		}
		if ( $s['end'] > 0 && in_array( $s['key'], [ 'free', 'expiring', 'expired' ], true ) ) {
			$text .= ' · ' . date_i18n( (string) get_option( 'date_format' ), $s['end'] );
		}

		$style = 'display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:999px;'
			. 'font-size:11.5px;font-weight:600;line-height:1.7;white-space:nowrap;text-decoration:none;'
			. 'color:' . $s['color'] . ';background:' . $s['color'] . '1a;border:1px solid ' . $s['color'] . '4d;';
		$dot   = '<span style="width:7px;height:7px;border-radius:50%;background:' . esc_attr( $s['color'] ) . ';"></span>';
		$inner = $dot . esc_html( $text );

		if ( $link && current_user_can( 'manage_options' ) ) {
			return '<a class="oc-sub-pill" href="' . esc_url( self::manage_url( $vendor_id ) ) . '" style="' . esc_attr( $style ) . '" title="' . esc_attr__( 'Manage subscription', 'owambe-connect-core' ) . '">' . $inner . '</a>';
		}
		return '<span class="oc-sub-pill" style="' . esc_attr( $style ) . '">' . $inner . '</span>';
	}

	// ─── Admin-post handler ───────────────────────────────────────────────────

	public function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}
		$vendor_id = isset( $_POST['vendor'] ) ? absint( $_POST['vendor'] ) : 0;
		check_admin_referer( self::ACTION . '_' . $vendor_id );

		$vendor = $vendor_id ? get_post( $vendor_id ) : null;
		if ( ! $vendor || OC_CPT !== $vendor->post_type ) {
			$this->back( $vendor_id, 'sub_error' );
		}

		$op  = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$msg = 'sub_error';

		switch ( $op ) {
			// Manual plan assignment (comped account) — local mirror only.
			case 'assign':
				$tier = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : '';
				if ( OC_Vendor_Subscription::get_tier( $tier ) ) {
					update_post_meta( $vendor_id, OC_Vendor_Subscription::META_PLAN, $tier );
					update_post_meta( $vendor_id, OC_Vendor_Subscription::META_STATUS, 'active' );
					$msg = 'sub_assigned';
				}
				break;

			// Set the free-period end to an explicit date (end of that day).
			case 'free_set':
				$date = isset( $_POST['free_until'] ) ? sanitize_text_field( wp_unslash( $_POST['free_until'] ) ) : '';
				$ts   = $date ? strtotime( $date . ' 23:59:59' ) : 0;
				if ( $ts > 0 ) {
					update_post_meta( $vendor_id, OC_Vendor_Subscription::META_STATUS, 'free' );
					update_post_meta( $vendor_id, OC_Vendor_Subscription::META_FREE_UNTIL, $ts );
					$msg = 'sub_free';
				}
				break;

			// Nudge the free clock: ±N months. Extending starts from now when the
			// period already lapsed, so "+1 month" always yields a usable window.
			case 'free_adjust':
				$months = isset( $_POST['months'] ) ? (int) $_POST['months'] : 0;
				if ( 0 !== $months && $months >= -24 && $months <= 24 ) {
					$base = (int) get_post_meta( $vendor_id, OC_Vendor_Subscription::META_FREE_UNTIL, true );
					if ( $months > 0 ) {
						$base = max( $base, time() );
					}
					$ts = strtotime( ( $months > 0 ? '+' : '' ) . $months . ' months', $base ?: time() );
					update_post_meta( $vendor_id, OC_Vendor_Subscription::META_STATUS, 'free' );
					update_post_meta( $vendor_id, OC_Vendor_Subscription::META_FREE_UNTIL, $ts );
					$msg = 'sub_free';
				}
				break;

			// Cancel: access ends, history (plan/customer/dates) kept.
			case 'cancel':
				update_post_meta( $vendor_id, OC_Vendor_Subscription::META_STATUS, 'canceled' );
				$msg = 'sub_cancelled';
				break;

			// Revoke: wipe the local subscription state entirely (Stripe ids are
			// kept so a returning paid customer still maps to this vendor).
			case 'revoke':
				delete_post_meta( $vendor_id, OC_Vendor_Subscription::META_PLAN );
				delete_post_meta( $vendor_id, OC_Vendor_Subscription::META_STATUS );
				delete_post_meta( $vendor_id, OC_Vendor_Subscription::META_FREE_UNTIL );
				$msg = 'sub_revoked';
				break;
		}

		if ( 'sub_error' !== $msg ) {
			do_action( 'oc_vendor_subscription_updated', $vendor_id, OC_Vendor_Subscription::get_vendor_subscription( $vendor_id ) );
		}
		$this->back( $vendor_id, $msg );
	}

	private function back( $vendor_id, $msg ) {
		$to = wp_get_referer() ?: ( $vendor_id ? self::manage_url( $vendor_id ) : admin_url( 'edit.php?post_type=' . OC_CPT ) );
		wp_safe_redirect( add_query_arg( 'oc_admin_msg', $msg, $to ) );
		exit;
	}

	// ─── Page ─────────────────────────────────────────────────────────────────

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$vendor_id = isset( $_GET['vendor'] ) ? absint( $_GET['vendor'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$vendor    = $vendor_id ? get_post( $vendor_id ) : null;
		?>
		<div class="wrap oc-subs">
			<h1><?php esc_html_e( 'Vendor Subscription', 'owambe-connect-core' ); ?></h1>
			<?php $this->notices(); ?>

			<?php if ( ! $vendor || OC_CPT !== $vendor->post_type ) : ?>
				<p class="description" style="max-width:720px;">
					<?php esc_html_e( 'Open this page from a vendor\'s subscription pill (Vendors list, or the Subscription column on All Vendors) to manage their plan, free period, or cancellation.', 'owambe-connect-core' ); ?>
				</p>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . OC_CPT . '&page=oc-vendors-admin' ) ); ?>">&larr; <?php esc_html_e( 'Go to Vendors', 'owambe-connect-core' ); ?></a></p>
			<?php else :
				$sub    = OC_Vendor_Subscription::get_vendor_subscription( $vendor_id );
				$state  = self::state( $vendor_id );
				$tiers  = OC_Vendor_Subscription::tiers();
				$free_v = $sub['free_until'] > 0 ? gmdate( 'Y-m-d', (int) $sub['free_until'] ) : '';
				$nonce  = function () use ( $vendor_id ) {
					wp_nonce_field( self::ACTION . '_' . $vendor_id );
					echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
					echo '<input type="hidden" name="vendor" value="' . (int) $vendor_id . '" />';
				};
				?>
				<div class="oc-subs__head">
					<h2 class="oc-subs__vendor"><?php echo esc_html( $vendor->post_title ); ?></h2>
					<?php echo self::pill( $vendor_id, false ); // phpcs:ignore WordPress.Security.EscapeOutput -- pill escapes internally. ?>
					<a class="oc-subs__backlink" href="<?php echo esc_url( get_edit_post_link( $vendor_id ) ); ?>"><?php esc_html_e( 'Edit vendor', 'owambe-connect-core' ); ?></a>
				</div>

				<div class="oc-subs__grid">

					<div class="oc-subs__card">
						<h3><?php esc_html_e( 'Assign a plan', 'owambe-connect-core' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Manually comp this vendor onto a plan (no Stripe billing is created — a real Stripe subscription will overwrite this via the webhook).', 'owambe-connect-core' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php $nonce(); ?>
							<input type="hidden" name="op" value="assign" />
							<select name="tier" required>
								<option value=""><?php esc_html_e( '— Choose a plan —', 'owambe-connect-core' ); ?></option>
								<?php foreach ( $tiers as $slug => $t ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $sub['plan'], $slug ); ?>>
										<?php
										echo esc_html( $t['label'] );
										if ( ! empty( $t['price'] ) ) {
											echo esc_html( ' — ' . $t['price'] );
										}
										?>
									</option>
								<?php endforeach; ?>
							</select>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Assign plan', 'owambe-connect-core' ); ?></button>
						</form>
					</div>

					<div class="oc-subs__card">
						<h3><?php esc_html_e( 'Free period', 'owambe-connect-core' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Set the exact end date, or nudge the clock. Extending an expired period restarts it from today.', 'owambe-connect-core' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:10px;">
							<?php $nonce(); ?>
							<input type="hidden" name="op" value="free_set" />
							<input type="date" name="free_until" value="<?php echo esc_attr( $free_v ); ?>" required />
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Set end date', 'owambe-connect-core' ); ?></button>
						</form>
						<div class="oc-subs__quick">
							<?php foreach ( [ 1 => '+1', 3 => '+3', -1 => '−1' ] as $m => $lab ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php $nonce(); ?>
									<input type="hidden" name="op" value="free_adjust" />
									<input type="hidden" name="months" value="<?php echo (int) $m; ?>" />
									<button type="submit" class="button">
										<?php
										/* translators: %s: signed number of months, e.g. +1 */
										echo esc_html( sprintf( __( '%s month', 'owambe-connect-core' ), $lab ) );
										?>
									</button>
								</form>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="oc-subs__card oc-subs__card--danger">
						<h3><?php esc_html_e( 'Cancel / revoke', 'owambe-connect-core' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Cancel keeps the plan history but ends access. Revoke wipes the subscription state completely (Stripe customer link is kept).', 'owambe-connect-core' ); ?></p>
						<div class="oc-subs__quick">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Cancel this vendor\'s subscription?', 'owambe-connect-core' ) ); ?>');">
								<?php $nonce(); ?>
								<input type="hidden" name="op" value="cancel" />
								<button type="submit" class="button"><?php esc_html_e( 'Cancel subscription', 'owambe-connect-core' ); ?></button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Revoke ALL subscription state for this vendor? This cannot be undone.', 'owambe-connect-core' ) ); ?>');">
								<?php $nonce(); ?>
								<input type="hidden" name="op" value="revoke" />
								<button type="submit" class="button oc-subs__revoke"><?php esc_html_e( 'Revoke', 'owambe-connect-core' ); ?></button>
							</form>
						</div>
					</div>

				</div>
			<?php endif; ?>
		</div>
		<style>
			.oc-subs__head{display:flex;align-items:center;gap:14px;margin:8px 0 18px;flex-wrap:wrap;}
			.oc-subs__vendor{margin:0;font-size:20px;}
			.oc-subs__backlink{font-size:13px;}
			.oc-subs__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;max-width:1040px;}
			.oc-subs__card{background:#fff;border:1px solid #E4DDD2;border-radius:10px;padding:16px 18px;}
			.oc-subs__card h3{margin:0 0 6px;}
			.oc-subs__card form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
			.oc-subs__quick{display:flex;gap:8px;flex-wrap:wrap;}
			.oc-subs__card--danger{border-color:#E8C4C6;}
			.oc-subs__revoke{color:#b32d2e;border-color:#b32d2e;}
		</style>
		<?php
	}

	/** Success/notice feedback on the manage page itself. */
	private function notices() {
		if ( empty( $_GET['oc_admin_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$map = self::notice_map();
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

	/**
	 * The subscription notice entries — shared so both vendor-list notice maps
	 * (OC_Admin::admin_notices and OC_Admin_Vendors_List::render_notices) can
	 * merge the same keys instead of duplicating strings.
	 *
	 * @return array<string,array{0:string,1:string}> key => [kind, text]
	 */
	public static function notice_map() {
		return [
			'sub_assigned'  => [ 'success', __( 'Plan assigned to the vendor.', 'owambe-connect-core' ) ],
			'sub_free'      => [ 'success', __( 'Free period updated.', 'owambe-connect-core' ) ],
			'sub_cancelled' => [ 'warning', __( 'Subscription cancelled.', 'owambe-connect-core' ) ],
			'sub_revoked'   => [ 'warning', __( 'Subscription state revoked.', 'owambe-connect-core' ) ],
			'sub_error'     => [ 'warning', __( 'Could not update the subscription — invalid request.', 'owambe-connect-core' ) ],
		];
	}
}
