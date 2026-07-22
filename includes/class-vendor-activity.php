<?php
/**
 * Vendor activity log.
 *
 * Records every meaningful event in a vendor's lifecycle:
 *   - registered, profile updated, submitted for review,
 *   - admin status changes (approved / rejected / un-approved),
 *   - admin manual edits via WP Quick Edit / post edit screen.
 *
 * Storage:
 *   - Per-vendor: post meta `_oc_activity_log` (last 50 events)
 *   - Site-wide: option `oc_recent_activity` (last 200 events) — for the
 *     admin overview at WP Admin → Vendors → Activity Log.
 *
 * Each entry: [ time, event, actor_id, actor_login, vendor_id, vendor_title, meta ]
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

class OC_Vendor_Activity {

	const META_KEY       = '_oc_activity_log';
	const OPTION_KEY     = 'oc_recent_activity';
	const PER_VENDOR_MAX = 50;
	const GLOBAL_MAX     = 200;
	const MENU_SLUG      = 'oc-activity-log';

	public function register() {
		// Hook into every event we care about.
		add_action( 'oc_after_vendor_registered',  [ $this, 'on_registered' ],     10, 1 );
		add_action( 'oc_after_vendor_updated',     [ $this, 'on_updated' ],        10, 1 );
		add_action( 'oc_vendor_status_changed',    [ $this, 'on_status_changed' ], 10, 5 );

		// Admin menu page + meta box on the vendor edit screen.
		add_action( 'admin_menu',        [ $this, 'admin_menu' ] );
		add_action( 'add_meta_boxes',    [ $this, 'add_meta_box' ] );
	}

	// ──────────────────────── Event handlers ────────────────────────

	public function on_registered( $post_id ) {
		$this->record( $post_id, 'registered', [ 'note' => __( 'New vendor signed up', 'owambe-connect-core' ) ] );
	}

	public function on_updated( $post_id ) {
		$this->record( $post_id, 'profile_updated', [ 'note' => __( 'Vendor saved profile changes', 'owambe-connect-core' ) ] );
	}

	public function on_status_changed( $post_id, $new_status, $old_status, $actor_id, $is_self ) {
		$label_map = [
			OC_STATUS_APPROVED => __( 'Approved & published', 'owambe-connect-core' ),
			OC_STATUS_PENDING  => __( 'Pending review', 'owambe-connect-core' ),
			OC_STATUS_REJECTED => __( 'Rejected (needs changes)', 'owambe-connect-core' ),
		];
		$meta = [
			'from'       => $label_map[ $old_status ] ?? $old_status,
			'to'         => $label_map[ $new_status ] ?? $new_status,
			'new_status' => $new_status, // raw — used by event_link() to decide where to link
		];
		if ( OC_STATUS_REJECTED === $new_status ) {
			$reason = (string) get_post_meta( $post_id, '_oc_rejection_note', true );
			if ( $reason ) $meta['reason'] = $reason;
		}
		$event = $is_self ? 'status_changed_by_vendor' : 'status_changed_by_admin';
		$this->record( $post_id, $event, $meta, $actor_id );
	}

	// ──────────────────────── Recorder ────────────────────────

	/**
	 * Public record API — can also be called from anywhere via:
	 *   ( new OC_Vendor_Activity() )->record( $vendor_id, 'event_key', $meta );
	 */
	public function record( $vendor_id, $event, $meta = [], $actor_id = null ) {
		$vendor_id = (int) $vendor_id;
		if ( ! $vendor_id ) return;
		$actor_id  = (int) ( $actor_id ?: get_current_user_id() );
		$actor     = $actor_id ? get_userdata( $actor_id ) : null;

		$entry = [
			'time'         => current_time( 'mysql' ),
			'event'        => sanitize_key( $event ),
			'actor_id'     => $actor_id,
			'actor_login'  => $actor ? $actor->user_login : __( 'system', 'owambe-connect-core' ),
			'vendor_id'    => $vendor_id,
			'vendor_title' => get_the_title( $vendor_id ),
			'meta'         => is_array( $meta ) ? $meta : [],
		];

		// Per-vendor log (oldest first capped at last N).
		$log = (array) get_post_meta( $vendor_id, self::META_KEY, true );
		$log[] = $entry;
		if ( count( $log ) > self::PER_VENDOR_MAX ) {
			$log = array_slice( $log, -self::PER_VENDOR_MAX );
		}
		update_post_meta( $vendor_id, self::META_KEY, $log );

		// Site-wide recent feed.
		$global = (array) get_option( self::OPTION_KEY, [] );
		$global[] = $entry;
		if ( count( $global ) > self::GLOBAL_MAX ) {
			$global = array_slice( $global, -self::GLOBAL_MAX );
		}
		update_option( self::OPTION_KEY, $global, false );
	}

	// ──────────────────────── Admin UI ────────────────────────

	public function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Vendor Activity Log', 'owambe-connect-core' ),
			__( 'Activity Log', 'owambe-connect-core' ),
			'edit_posts',
			self::MENU_SLUG,
			[ $this, 'render_admin_page' ]
		);
	}

	public function render_admin_page() {
		$entries = (array) get_option( self::OPTION_KEY, [] );
		$entries = array_reverse( $entries ); // newest first
		$total_all = count( $entries );

		$filter_event  = isset( $_GET['event'] )  ? sanitize_key( $_GET['event'] ) : '';
		$filter_vendor = isset( $_GET['vendor'] ) ? (int) $_GET['vendor'] : 0;
		$paged         = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$per_page      = 25;

		// Pre-compute KPI counts off the unfiltered set so they stay stable
		// when the admin narrows the view.
		$now_ts        = current_time( 'timestamp' );
		$today_start   = strtotime( 'today',           $now_ts );
		$week_start    = strtotime( '-7 days',         $now_ts );
		$kpi_total     = $total_all;
		$kpi_today     = 0;
		$kpi_week      = 0;
		$kpi_event_counts = [
			'registered' => 0, 'profile_updated' => 0,
			'status_changed_by_admin' => 0, 'status_changed_by_vendor' => 0,
		];
		foreach ( $entries as $e ) {
			$ts = strtotime( $e['time'] ?? '' );
			if ( $ts && $ts >= $today_start ) $kpi_today++;
			if ( $ts && $ts >= $week_start )  $kpi_week++;
			$ev = $e['event'] ?? '';
			if ( isset( $kpi_event_counts[ $ev ] ) ) $kpi_event_counts[ $ev ]++;
		}

		// Filter set + paginate.
		$filtered = array_values( array_filter( $entries, function ( $e ) use ( $filter_event, $filter_vendor ) {
			if ( $filter_event && ( $e['event'] ?? '' ) !== $filter_event ) return false;
			if ( $filter_vendor && (int) ( $e['vendor_id'] ?? 0 ) !== $filter_vendor ) return false;
			return true;
		} ) );
		$filtered_total = count( $filtered );
		$page_count     = max( 1, (int) ceil( $filtered_total / $per_page ) );
		$paged          = min( $paged, $page_count );
		$page_slice     = array_slice( $filtered, ( $paged - 1 ) * $per_page, $per_page );

		$base = admin_url( 'edit.php?post_type=' . OC_CPT . '&page=' . self::MENU_SLUG );
		$pill_links = [
			'all'                       => [ __( 'All',                      'owambe-connect-core' ), $kpi_total ],
			'registered'                => [ __( 'Registered',               'owambe-connect-core' ), $kpi_event_counts['registered'] ],
			'profile_updated'           => [ __( 'Profile updated',          'owambe-connect-core' ), $kpi_event_counts['profile_updated'] ],
			'status_changed_by_admin'   => [ __( 'Status changed (admin)',   'owambe-connect-core' ), $kpi_event_counts['status_changed_by_admin'] ],
			'status_changed_by_vendor'  => [ __( 'Status changed (vendor)',  'owambe-connect-core' ), $kpi_event_counts['status_changed_by_vendor'] ],
		];
		?>
		<div class="wrap oc-al">
			<header class="oc-al-head">
				<div>
					<h1><?php esc_html_e( 'Vendor Activity Log', 'owambe-connect-core' ); ?></h1>
					<p class="oc-al-sub"><?php
						/* translators: %d: total entries cap */
						printf( esc_html__( 'Latest %d events across the marketplace. Per-vendor history is also visible on each vendor\'s edit screen.', 'owambe-connect-core' ), self::GLOBAL_MAX );
					?></p>
				</div>
				<a class="oc-al-back" href="<?php echo esc_url( admin_url( 'admin.php?page=oc-vendors' ) ); ?>">
					<span class="dashicons dashicons-arrow-left-alt2"></span>
					<?php esc_html_e( 'Back to vendors', 'owambe-connect-core' ); ?>
				</a>
			</header>

			<!-- KPI strip -->
			<div class="oc-al-kpis">
				<?php $this->kpi_card( __( 'Total events',     'owambe-connect-core' ), $kpi_total, 'list-view',      '#1F1B1A' ); ?>
				<?php $this->kpi_card( __( 'Today',            'owambe-connect-core' ), $kpi_today, 'clock',          '#A8893D' ); ?>
				<?php $this->kpi_card( __( 'Last 7 days',      'owambe-connect-core' ), $kpi_week,  'calendar-alt',   '#6E0F2C' ); ?>
				<?php $this->kpi_card( __( 'Registrations',    'owambe-connect-core' ), $kpi_event_counts['registered'], 'admin-users', '#2271b1' ); ?>
				<?php $this->kpi_card( __( 'Status changes',   'owambe-connect-core' ), $kpi_event_counts['status_changed_by_admin'] + $kpi_event_counts['status_changed_by_vendor'], 'update', '#2E7D5B' ); ?>
			</div>

			<!-- Event pills -->
			<nav class="oc-al-pills">
				<?php foreach ( $pill_links as $key => $row ) :
					[ $label, $count ] = $row;
					$is_active = ( 'all' === $key && '' === $filter_event ) || $filter_event === $key;
					$url = 'all' === $key
						? $base . ( $filter_vendor ? '&vendor=' . $filter_vendor : '' )
						: add_query_arg( [ 'event' => $key, 'vendor' => $filter_vendor ?: false ], $base );
					?>
					<a class="oc-al-pill <?php echo $is_active ? 'is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
						<?php echo esc_html( $label ); ?>
						<span class="oc-al-pill__count"><?php echo (int) $count; ?></span>
					</a>
				<?php endforeach; ?>
			</nav>

			<!-- Filter bar -->
			<form class="oc-al-filters" method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( OC_CPT ); ?>" />
				<input type="hidden" name="page"      value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
				<input type="hidden" name="event"     value="<?php echo esc_attr( $filter_event ); ?>" />

				<div class="oc-al-filter oc-al-filter--vendor">
					<span class="dashicons dashicons-id"></span>
					<input type="number" name="vendor" value="<?php echo $filter_vendor ?: ''; ?>" min="0" placeholder="<?php esc_attr_e( 'Filter by vendor ID…', 'owambe-connect-core' ); ?>"/>
				</div>
				<button type="submit" class="oc-al-btn oc-al-btn--primary"><?php esc_html_e( 'Apply', 'owambe-connect-core' ); ?></button>
				<?php if ( $filter_event || $filter_vendor ) : ?>
					<a class="oc-al-btn" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Reset', 'owambe-connect-core' ); ?></a>
				<?php endif; ?>
				<div class="oc-al-meta">
					<?php
					/* translators: 1: total filtered, 2: page, 3: pages */
					printf( esc_html( _n( '%1$s event', '%1$s events', $filtered_total, 'owambe-connect-core' ) ), '<strong>' . number_format_i18n( $filtered_total ) . '</strong>' );
					echo '  ·  ';
					/* translators: 1: current page, 2: total pages */
					printf( esc_html__( 'Page %1$d of %2$d', 'owambe-connect-core' ), $paged, $page_count );
					?>
				</div>
			</form>

			<!-- Table -->
			<?php if ( empty( $page_slice ) ) : ?>
				<div class="oc-al-empty">
					<span class="dashicons dashicons-info-outline"></span>
					<h3><?php esc_html_e( 'No activity matches these filters.', 'owambe-connect-core' ); ?></h3>
					<p><?php esc_html_e( 'Try resetting or picking a different event.', 'owambe-connect-core' ); ?></p>
					<a class="oc-al-btn oc-al-btn--primary" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Reset filters', 'owambe-connect-core' ); ?></a>
				</div>
			<?php else : ?>
				<div class="oc-al-table">
					<div class="oc-al-row oc-al-row--head">
						<div class="oc-al-col oc-al-col--when"><?php esc_html_e( 'When', 'owambe-connect-core' ); ?></div>
						<div class="oc-al-col oc-al-col--event"><?php esc_html_e( 'Event', 'owambe-connect-core' ); ?></div>
						<div class="oc-al-col oc-al-col--vendor"><?php esc_html_e( 'Vendor', 'owambe-connect-core' ); ?></div>
						<div class="oc-al-col oc-al-col--actor"><?php esc_html_e( 'Actor', 'owambe-connect-core' ); ?></div>
						<div class="oc-al-col oc-al-col--details"><?php esc_html_e( 'Details', 'owambe-connect-core' ); ?></div>
					</div>

					<?php foreach ( $page_slice as $e ) :
						$vid                 = (int) ( $e['vendor_id'] ?? 0 );
						[ $link, $external ] = self::event_link( $e );
						$ev_key              = $e['event'] ?? '';
						$ev_color            = self::event_color( $ev_key );
						$ts                  = strtotime( $e['time'] ?? '' );
						$rel                 = $ts ? human_time_diff( $ts, $now_ts ) . ' ' . __( 'ago', 'owambe-connect-core' ) : '';
					?>
						<div class="oc-al-row">
							<div class="oc-al-col oc-al-col--when">
								<strong><?php echo esc_html( $rel ); ?></strong>
								<small><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $e['time'] ?? '' ) ); ?></small>
							</div>
							<div class="oc-al-col oc-al-col--event">
								<?php if ( $link ) : ?>
									<a class="oc-al-event-link" style="--c:<?php echo esc_attr( $ev_color ); ?>" href="<?php echo esc_url( $link ); ?>"<?php echo $external ? ' target="_blank" rel="noopener"' : ''; ?>>
										<span class="oc-al-event-dot" aria-hidden="true"></span>
										<?php echo esc_html( self::event_label( $ev_key ) ); ?>
										<?php if ( $external ) : ?><span class="oc-al-event-ext" aria-hidden="true">↗</span><?php endif; ?>
									</a>
								<?php else : ?>
									<span class="oc-al-event-link" style="--c:<?php echo esc_attr( $ev_color ); ?>">
										<span class="oc-al-event-dot" aria-hidden="true"></span>
										<?php echo esc_html( self::event_label( $ev_key ) ); ?>
									</span>
								<?php endif; ?>
							</div>
							<div class="oc-al-col oc-al-col--vendor">
								<?php if ( $link && $vid ) : ?>
									<a class="oc-al-vendor" href="<?php echo esc_url( $link ); ?>"<?php echo $external ? ' target="_blank" rel="noopener"' : ''; ?>>
										<?php echo esc_html( $e['vendor_title'] ?: __( '(no title)', 'owambe-connect-core' ) ); ?>
									</a>
								<?php else : ?>
									<span class="oc-al-vendor"><?php echo esc_html( $e['vendor_title'] ?? '—' ); ?></span>
								<?php endif; ?>
								<small>#<?php echo (int) $vid; ?></small>
							</div>
							<div class="oc-al-col oc-al-col--actor">
								<?php echo esc_html( $e['actor_login'] ?? '—' ); ?>
							</div>
							<div class="oc-al-col oc-al-col--details">
								<?php echo self::format_meta( $e['meta'] ?? [] ); // phpcs:ignore — pre-escaped ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<?php $this->render_pager( $paged, $page_count, $base, $filter_event, $filter_vendor ); ?>
			<?php endif; ?>
		</div>

		<?php $this->print_styles(); ?>
		<?php
	}

	/**
	 * KPI tile (mirrors the vendor-list style so admin pages feel like one
	 * design system).
	 */
	private function kpi_card( $label, $value, $icon, $color ) {
		?>
		<div class="oc-al-kpi" style="--c:<?php echo esc_attr( $color ); ?>">
			<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
			<div>
				<strong><?php echo esc_html( number_format_i18n( (int) $value ) ); ?></strong>
				<small><?php echo esc_html( $label ); ?></small>
			</div>
		</div>
		<?php
	}

	/**
	 * Pager — Prev / [num] / Next, identical UX to the vendor list page.
	 */
	private function render_pager( $paged, $pages, $base, $filter_event, $filter_vendor ) {
		if ( $pages <= 1 ) return;
		$args = [ 'event' => $filter_event, 'vendor' => $filter_vendor ?: false ];
		$prev = $paged > 1     ? add_query_arg( array_merge( $args, [ 'paged' => $paged - 1 ] ), $base ) : '';
		$next = $paged < $pages ? add_query_arg( array_merge( $args, [ 'paged' => $paged + 1 ] ), $base ) : '';
		?>
		<div class="oc-al-pager">
			<a class="oc-al-pager__btn <?php echo $prev ? '' : 'is-disabled'; ?>" href="<?php echo esc_url( $prev ?: '#' ); ?>">‹ <?php esc_html_e( 'Previous', 'owambe-connect-core' ); ?></a>
			<span class="oc-al-pager__pages">
				<?php
				$start = max( 1, $paged - 2 );
				$end   = min( $pages, $paged + 2 );
				if ( $start > 1 ) {
					printf( '<a class="oc-al-pager__num" href="%s">1</a>', esc_url( add_query_arg( array_merge( $args, [ 'paged' => 1 ] ), $base ) ) );
					if ( $start > 2 ) echo '<span class="oc-al-pager__gap">…</span>';
				}
				for ( $i = $start; $i <= $end; $i++ ) {
					if ( $i === $paged ) {
						printf( '<span class="oc-al-pager__num is-active">%d</span>', $i );
					} else {
						printf( '<a class="oc-al-pager__num" href="%s">%d</a>', esc_url( add_query_arg( array_merge( $args, [ 'paged' => $i ] ), $base ) ), $i );
					}
				}
				if ( $end < $pages ) {
					if ( $end < $pages - 1 ) echo '<span class="oc-al-pager__gap">…</span>';
					printf( '<a class="oc-al-pager__num" href="%s">%d</a>', esc_url( add_query_arg( array_merge( $args, [ 'paged' => $pages ] ), $base ) ), $pages );
				}
				?>
			</span>
			<a class="oc-al-pager__btn <?php echo $next ? '' : 'is-disabled'; ?>" href="<?php echo esc_url( $next ?: '#' ); ?>"><?php esc_html_e( 'Next', 'owambe-connect-core' ); ?> ›</a>
		</div>
		<?php
	}

	private function print_styles() {
		?>
		<style>
			.oc-al { max-width:1400px; }
			.oc-al-head { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; margin:8px 0 18px; }
			.oc-al-head h1 { font-family:Georgia, serif; color:#1F1B1A; margin:0; font-size:1.8rem; }
			.oc-al-sub { margin:4px 0 0; color:#6B6361; font-size:13px; max-width:780px; }
			.oc-al-back { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; background:#fff; border:1px solid #E4DDD2; color:#6B6361; border-radius:6px; text-decoration:none; font-weight:500; font-size:13px; }
			.oc-al-back:hover { border-color:#6E0F2C; color:#6E0F2C; }
			.oc-al-back .dashicons { font-size:14px; width:14px; height:14px; }

			/* KPI strip — same dimensions + visual rhythm as the vendor list */
			.oc-al-kpis { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin:0 0 18px; }
			@media (min-width:900px)  { .oc-al-kpis { grid-template-columns:repeat(5,1fr); } }
			.oc-al-kpi { background:#fff; border:1px solid #E4DDD2; border-left:3px solid var(--c); border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px; }
			.oc-al-kpi .dashicons { color:var(--c); opacity:.55; font-size:28px; width:28px; height:28px; }
			.oc-al-kpi strong { display:block; font-family:Georgia, serif; font-size:1.6rem; line-height:1; color:var(--c); }
			.oc-al-kpi small { display:block; font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#6B6361; font-weight:600; margin-top:4px; }

			/* Pills row */
			.oc-al-pills { display:flex; flex-wrap:wrap; gap:6px; padding:10px 0; margin:0 0 14px; border-bottom:1px solid #E4DDD2; }
			.oc-al-pill { display:inline-flex; align-items:center; gap:8px; padding:7px 14px; background:#FAF7F2; border:1px solid #E4DDD2; border-radius:999px; color:#6B6361; text-decoration:none; font-weight:500; font-size:13px; }
			.oc-al-pill:hover { border-color:#6E0F2C; color:#6E0F2C; }
			.oc-al-pill.is-active { background:#6E0F2C; border-color:#6E0F2C; color:#fff; }
			.oc-al-pill__count { background:rgba(0,0,0,.08); border-radius:999px; padding:1px 8px; font-size:11px; font-weight:600; }
			.oc-al-pill.is-active .oc-al-pill__count { background:#C9A961; color:#1F1B1A; }

			/* Filter bar */
			.oc-al-filters { background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:12px; margin:0 0 14px; display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
			.oc-al-filter--vendor { display:flex; align-items:center; gap:6px; padding:0; border:1px solid #ccd0d4; border-radius:4px; background:#fff; flex:0 0 240px; }
			.oc-al-filter--vendor .dashicons { color:#6B6361; padding-left:10px; }
			.oc-al-filter--vendor input { border:0; outline:0; padding:8px 12px 8px 6px; flex:1; font-size:13px; background:transparent; width:100%; }
			.oc-al-btn { display:inline-flex; align-items:center; padding:8px 16px; border:1px solid #E4DDD2; background:#fff; color:#6B6361; border-radius:4px; text-decoration:none; cursor:pointer; font-size:13px; font-weight:500; }
			.oc-al-btn:hover { border-color:#6E0F2C; color:#6E0F2C; }
			.oc-al-btn--primary { background:#6E0F2C; border-color:#6E0F2C; color:#fff; }
			.oc-al-btn--primary:hover { background:#4A0A1E; color:#fff; }
			.oc-al-meta { margin-left:auto; color:#6B6361; font-size:13px; }
			.oc-al-meta strong { color:#1F1B1A; }

			/* Table — five-col grid, same shape as the vendor list */
			.oc-al-table { background:#fff; border:1px solid #E4DDD2; border-radius:8px; overflow:hidden; }
			.oc-al-row {
				display:grid;
				grid-template-columns: 160px 220px minmax(0, 1.4fr) 130px minmax(0, 2fr);
				gap:14px;
				padding:12px 16px;
				align-items:flex-start;
				border-bottom:1px solid #EFEAE2;
			}
			.oc-al-row > .oc-al-col { min-width:0; }
			.oc-al-row:last-child { border-bottom:0; }
			.oc-al-row:hover { background:#FAF7F2; }
			.oc-al-row--head { background:#FAF7F2; font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#6B6361; font-weight:600; padding:10px 16px; }
			.oc-al-row--head:hover { background:#FAF7F2; }

			.oc-al-col--when strong { display:block; font-size:13px; color:#1F1B1A; }
			.oc-al-col--when small  { display:block; color:#6B6361; font-size:11.5px; }

			.oc-al-event-link { display:inline-flex; align-items:center; gap:8px; color:var(--c); font-weight:600; text-decoration:none; font-size:13px; }
			.oc-al-event-link:hover { text-decoration:underline; }
			.oc-al-event-dot { width:8px; height:8px; border-radius:50%; background:var(--c); display:inline-block; flex-shrink:0; }
			.oc-al-event-ext { color:var(--c); opacity:.55; font-size:12px; }

			.oc-al-vendor { color:#6E0F2C; text-decoration:none; font-weight:500; font-size:13px; }
			.oc-al-vendor:hover { text-decoration:underline; color:#4A0A1E; }
			.oc-al-col--vendor small { display:block; color:#999; font-size:11px; margin-top:2px; }
			.oc-al-col--actor { font-size:13px; color:#1F1B1A; }
			.oc-al-col--details { font-size:13px; color:#1F1B1A; }
			.oc-al-col--details div { margin:2px 0; }
			.oc-al-col--details strong { color:#6B6361; font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.04em; }

			.oc-al-empty { background:#fff; border:1px dashed #E4DDD2; border-radius:8px; padding:48px 24px; text-align:center; }
			.oc-al-empty .dashicons { font-size:36px; width:36px; height:36px; color:#C9A961; }
			.oc-al-empty h3 { font-family:Georgia, serif; color:#1F1B1A; margin:14px 0 4px; }
			.oc-al-empty p { color:#6B6361; margin:0 0 14px; }

			.oc-al-pager { display:flex; justify-content:center; gap:6px; padding:18px 0; align-items:center; }
			.oc-al-pager__btn { padding:7px 14px; border:1px solid #E4DDD2; background:#fff; border-radius:4px; text-decoration:none; color:#6B6361; font-size:13px; font-weight:500; }
			.oc-al-pager__btn:hover { border-color:#6E0F2C; color:#6E0F2C; }
			.oc-al-pager__btn.is-disabled { opacity:.4; pointer-events:none; }
			.oc-al-pager__pages { display:flex; gap:4px; margin:0 6px; }
			.oc-al-pager__num { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:34px; padding:0 8px; border:1px solid #E4DDD2; background:#fff; border-radius:4px; text-decoration:none; color:#6B6361; font-size:13px; }
			.oc-al-pager__num:hover { border-color:#6E0F2C; color:#6E0F2C; }
			.oc-al-pager__num.is-active { background:#6E0F2C; border-color:#6E0F2C; color:#fff; font-weight:600; }
			.oc-al-pager__gap { color:#999; padding:0 4px; }

			/* Narrow viewports — collapse to two stacked rows */
			@media (max-width:1100px) {
				.oc-al-row { grid-template-columns: 130px minmax(0, 1.4fr) minmax(0, 1.2fr); }
				.oc-al-col--actor, .oc-al-col--details { grid-column: 2 / -1; padding-top:2px; }
				.oc-al-col--details strong { display:inline; }
				.oc-al-row--head .oc-al-col--actor, .oc-al-row--head .oc-al-col--details { display:none; }
			}
		</style>
		<?php
	}

	// ──────────────────────── Per-vendor meta box ────────────────────────

	public function add_meta_box() {
		add_meta_box( 'oc_vendor_history', __( 'Activity history', 'owambe-connect-core' ), [ $this, 'render_meta_box' ], OC_CPT, 'normal', 'low' );
	}

	public function render_meta_box( $post ) {
		$log = (array) get_post_meta( $post->ID, self::META_KEY, true );
		$log = array_reverse( $log );
		?>
		<style>
			.oc-al-mbox { font-size: 13px; }
			.oc-al-mbox__empty { padding: 18px 0; color: #6B6361; text-align: center; font-style: italic; }
			.oc-al-mbox__list { display: flex; flex-direction: column; gap: 0; }
			.oc-al-mbox__row { display: grid; grid-template-columns: 140px 1fr; gap: 14px; padding: 12px 0; border-bottom: 1px solid #EFEAE2; }
			.oc-al-mbox__row:last-child { border-bottom: 0; }
			.oc-al-mbox__when strong { display: block; color: #1F1B1A; font-size: 12.5px; }
			.oc-al-mbox__when small  { display: block; color: #6B6361; font-size: 11px; }
			.oc-al-mbox__event { display: inline-flex; align-items: center; gap: 8px; color: var(--c); font-weight: 600; font-size: 13px; margin-bottom: 4px; }
			.oc-al-mbox__dot { width: 8px; height: 8px; border-radius: 50%; background: var(--c); display: inline-block; }
			.oc-al-mbox__actor { color: #6B6361; font-size: 11.5px; }
			.oc-al-mbox__meta { color: #1F1B1A; }
			.oc-al-mbox__meta strong { color: #6B6361; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
			.oc-al-mbox__meta div { margin: 2px 0; }
		</style>
		<div class="oc-al-mbox">
			<?php if ( empty( $log ) ) : ?>
				<p class="oc-al-mbox__empty"><?php esc_html_e( 'No activity yet for this vendor.', 'owambe-connect-core' ); ?></p>
			<?php else : ?>
				<div class="oc-al-mbox__list">
					<?php $now_ts = current_time( 'timestamp' );
					foreach ( $log as $e ) :
						$ev = $e['event'] ?? '';
						$ts = strtotime( $e['time'] ?? '' );
						?>
						<div class="oc-al-mbox__row">
							<div class="oc-al-mbox__when">
								<strong><?php echo esc_html( $ts ? human_time_diff( $ts, $now_ts ) . ' ' . __( 'ago', 'owambe-connect-core' ) : '—' ); ?></strong>
								<small><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $e['time'] ?? '' ) ); ?></small>
							</div>
							<div>
								<span class="oc-al-mbox__event" style="--c:<?php echo esc_attr( self::event_color( $ev ) ); ?>">
									<span class="oc-al-mbox__dot"></span>
									<?php echo esc_html( self::event_label( $ev ) ); ?>
								</span>
								<span class="oc-al-mbox__actor"><?php esc_html_e( 'by', 'owambe-connect-core' ); ?> <?php echo esc_html( $e['actor_login'] ?? '—' ); ?></span>
								<div class="oc-al-mbox__meta"><?php echo self::format_meta( $e['meta'] ?? [] ); // phpcs:ignore ?></div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// ──────────────────────── Routing ────────────────────────

	/**
	 * Decide where each activity row should link to when clicked.
	 *
	 * Approved-status events → PUBLIC vendor profile (so admin can verify the
	 * live result). Everything else → admin edit screen (so admin can act on it).
	 *
	 * @return array{0:string,1:bool}  [ url, is_external ]  is_external=true means
	 *                                  it opens the public site (used to render a ↗ icon).
	 */
	private static function event_link( $entry ) {
		$vid   = (int) ( $entry['vendor_id'] ?? 0 );
		$event = $entry['event'] ?? '';
		$meta  = $entry['meta']  ?? [];

		if ( ! $vid ) return [ '', false ];

		$goes_to_approved = ( 'status_changed_by_admin' === $event )
			&& ( OC_STATUS_APPROVED === ( $meta['new_status'] ?? '' ) );

		if ( $goes_to_approved ) {
			// Verify the link still resolves to a published vendor (the post may have been
			// re-rejected since). If not, fall through to the admin edit page.
			if ( OC_STATUS_APPROVED === get_post_status( $vid ) ) {
				$link = get_permalink( $vid );
				if ( $link ) return [ $link, true ];
			}
		}

		return [ admin_url( 'post.php?post=' . $vid . '&action=edit' ), false ];
	}

	// ──────────────────────── Display helpers ────────────────────────

	private static function event_labels() {
		return [
			'registered'                 => __( 'Registered', 'owambe-connect-core' ),
			'profile_updated'            => __( 'Profile updated', 'owambe-connect-core' ),
			'status_changed_by_admin'    => __( 'Status changed (admin)', 'owambe-connect-core' ),
			'status_changed_by_vendor'   => __( 'Status changed (vendor)', 'owambe-connect-core' ),
			'review_approved'            => __( 'Review approved', 'owambe-connect-core' ),
			'featured_expired'           => __( 'Featured expired', 'owambe-connect-core' ),
		];
	}

	private static function event_label( $key ) {
		$map = self::event_labels();
		return $map[ $key ] ?? $key;
	}

	private static function event_color( $key ) {
		$colors = [
			'registered'               => '#2271b1',
			'profile_updated'          => '#666',
			'status_changed_by_admin'  => '#A8893D',
			'status_changed_by_vendor' => '#2E7D5B',
			'review_approved'          => '#C9A961',
			'featured_expired'         => '#B0354F',
		];
		return $colors[ $key ] ?? '#444';
	}

	private static function format_meta( $meta ) {
		if ( empty( $meta ) || ! is_array( $meta ) ) return '<span style="color:#888;">—</span>';
		$out = '';
		foreach ( $meta as $k => $v ) {
			if ( 'note' === $k ) {
				$out .= esc_html( $v );
				continue;
			}
			if ( is_array( $v ) ) $v = wp_json_encode( $v );
			$out .= '<div><strong>' . esc_html( $k ) . ':</strong> ' . esc_html( (string) $v ) . '</div>';
		}
		return $out;
	}
}
