<?php
/**
 * Directory shortcode template.
 *
 * @package OwambeConnect
 * @var WP_Query $query
 */
defined( 'ABSPATH' ) || exit;

$categories     = OC_Queries::categories_with_counts();
$current_cat    = isset( $_GET['cat'] )         ? sanitize_title( $_GET['cat'] )                    : '';
$current_search = isset( $_GET['vendor_name'] ) ? sanitize_text_field( wp_unslash( $_GET['vendor_name'] ) ) : '';
$current_loc    = isset( $_GET['location'] )    ? sanitize_text_field( wp_unslash( $_GET['location'] ) ) : '';
$current_city   = isset( $_GET['city'] )        ? sanitize_text_field( wp_unslash( $_GET['city'] ) )     : '';
$current_cultural = isset( $_GET['cultural'] )  ? sanitize_key( wp_unslash( $_GET['cultural'] ) )        : '';
$current_nigerian = ! empty( $_GET['nigerian'] ) ? '1'                                                   : '';
$current_radius   = isset( $_GET['radius'] )    ? max( 0, (float) $_GET['radius'] )                      : 0;
$current_nlat     = isset( $_GET['near_lat'] )  ? sanitize_text_field( wp_unslash( $_GET['near_lat'] ) ) : '';
$current_nlng     = isset( $_GET['near_lng'] )  ? sanitize_text_field( wp_unslash( $_GET['near_lng'] ) ) : '';
$country_labels = function_exists( 'oc_country_options' ) ? oc_country_options() : [];
$region_labels  = function_exists( 'oc_region_options' )  ? oc_region_options()  : [];
$action_url     = oc_page_url( 'vendors' );

$heading       = ! empty( $heading )       ? $heading       : __( 'Vendor Directory', 'owambe-connect-core' );
$subheading    = ! empty( $subheading )    ? $subheading    : '';
$show_filters  = ! isset( $show_filters )  || 'yes' === $show_filters;
?>
<section class="oc-section oc-directory">
	<div class="oc-container">
		<header class="oc-directory__head">
			<h1 class="oc-directory__title"><?php echo esc_html( $heading ); ?></h1>
			<?php if ( $subheading ) : ?><p class="oc-directory__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
			<?php
			$count = (int) $query->found_posts;
			/* translators: %d: number of vendors found */
			$label = sprintf( _n( '%d vendor', '%d vendors', $count, 'owambe-connect-core' ), $count );
			?>
			<p class="oc-directory__count"><?php echo esc_html( $label ); ?></p>
		</header>
		<?php if ( $current_cat ) :
			$featured_category = OC_Queries::featured( (int) oc_get_setting( 'featured_count', 6 ), 'category', $current_cat );
			if ( $featured_category->have_posts() ) :
				$term = get_term_by( 'slug', $current_cat, OC_TAX );
		?>
			<section class="oc-category-featured" aria-label="<?php esc_attr_e( 'Featured vendors in this category', 'owambe-connect-core' ); ?>">
				<h2><?php printf( esc_html__( 'Featured %s vendors', 'owambe-connect-core' ), esc_html( $term ? $term->name : $current_cat ) ); ?></h2>
				<div class="oc-carousel" data-oc-carousel><button class="oc-carousel__arrow oc-carousel__arrow--prev" type="button" data-oc-carousel-prev aria-label="<?php esc_attr_e( 'Previous vendors', 'owambe-connect-core' ); ?>">&#8249;</button><div class="oc-carousel__track">
				<?php while ( $featured_category->have_posts() ) : $featured_category->the_post(); echo oc_get_template( 'partials/vendor-card.php', [ 'post_id' => get_the_ID() ] ); endwhile; wp_reset_postdata(); ?>
				</div><button class="oc-carousel__arrow oc-carousel__arrow--next" type="button" data-oc-carousel-next aria-label="<?php esc_attr_e( 'Next vendors', 'owambe-connect-core' ); ?>">&#8250;</button></div>
			</section>
		<?php endif; endif; ?>

		<?php if ( $show_filters ) :
			// Count how many filters are active so the mobile toggle button
			// can show a small badge ("Filters · 2") — gives the user a
			// visual cue that filters are constraining the list even when
			// the panel is collapsed.
			$active_filter_count = (int) ( '' !== $current_search )
				+ (int) ( '' !== $current_cat )
				+ (int) ( '' !== $current_loc )
				+ (int) ( '' !== $current_city )
				+ (int) ( $current_radius > 0 );
			// Preserve backward compatibility: support old ?s= parameter format
			if ( '' === $current_search && isset( $_GET['s'] ) ) {
				$current_search = sanitize_text_field( wp_unslash( $_GET['s'] ) );
				$active_filter_count = (int) ( '' !== $current_search ) + (int) ( '' !== $current_cat ) + (int) ( '' !== $current_loc ) + (int) ( '' !== $current_city ) + (int) ( $current_radius > 0 );
			}
		?>
		<form class="oc-filters<?php echo $active_filter_count > 0 ? ' is-open' : ''; ?>" method="get" action="<?php echo esc_url( user_trailingslashit( $action_url ) ); ?>" role="search">
			<button type="button" class="oc-filters__toggle" data-oc-filters-toggle aria-expanded="<?php echo $active_filter_count > 0 ? 'true' : 'false'; ?>" aria-controls="oc-filters-row">
				<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="6" x2="20" y2="6"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="10" y1="18" x2="14" y2="18"/></svg>
				<span><?php esc_html_e( 'Filters', 'owambe-connect-core' ); ?></span>
				<?php if ( $active_filter_count > 0 ) : ?>
					<span class="oc-filters__badge"><?php echo (int) $active_filter_count; ?></span>
				<?php endif; ?>
				<svg class="oc-filters__chev" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
			</button>
			<div class="oc-filters__row" id="oc-filters-row">
				<div class="oc-filters__field">
					<label for="oc-f-search"><?php esc_html_e( 'Search', 'owambe-connect-core' ); ?></label>
					<input id="oc-f-search" type="search" name="vendor_name" value="<?php echo esc_attr( $current_search ); ?>" placeholder="<?php esc_attr_e( 'e.g. Photography, Catering, Red Artistry', 'owambe-connect-core' ); ?>" data-oc-filter-input />
				</div>
				<div class="oc-filters__field">
					<label for="oc-f-cat"><?php esc_html_e( 'Category', 'owambe-connect-core' ); ?></label>
					<select id="oc-f-cat" name="cat" data-oc-filter-input>
						<option value=""><?php esc_html_e( 'All categories', 'owambe-connect-core' ); ?></option>
						<?php foreach ( $categories as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>"<?php selected( $current_cat, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="oc-filters__field">
					<label for="oc-f-region"><?php esc_html_e( 'Region', 'owambe-connect-core' ); ?></label>
					<select id="oc-f-region" name="location" data-oc-filter-input>
						<option value=""><?php esc_html_e( 'All UK', 'owambe-connect-core' ); ?></option>
						<?php if ( $country_labels ) : ?>
							<optgroup label="<?php esc_attr_e( 'UK countries', 'owambe-connect-core' ); ?>">
								<?php foreach ( $country_labels as $label ) : ?>
									<option value="<?php echo esc_attr( $label ); ?>"<?php selected( $current_loc, $label ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endif; ?>
						<?php if ( $region_labels ) : ?>
							<optgroup label="<?php esc_attr_e( 'England regions', 'owambe-connect-core' ); ?>">
								<?php foreach ( $region_labels as $label ) : ?>
									<option value="<?php echo esc_attr( $label ); ?>"<?php selected( $current_loc, $label ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endif; ?>
					</select>
				</div>
				<div class="oc-filters__field">
					<label for="oc-f-city"><?php esc_html_e( 'City / area', 'owambe-connect-core' ); ?></label>
					<input id="oc-f-city" type="text" name="city" value="<?php echo esc_attr( $current_city ); ?>" placeholder="<?php esc_attr_e( 'e.g. London', 'owambe-connect-core' ); ?>" data-oc-filter-input />
				</div>
				<div class="oc-filters__field oc-filters__field--distance">
					<label for="oc-f-radius"><?php esc_html_e( 'Distance', 'owambe-connect-core' ); ?></label>
					<div class="oc-distance">
						<select id="oc-f-radius" name="radius" data-oc-filter-input>
							<option value=""><?php esc_html_e( 'Any distance', 'owambe-connect-core' ); ?></option>
							<?php foreach ( [ 5, 10, 25, 50 ] as $miles ) : ?>
								<option value="<?php echo (int) $miles; ?>"<?php selected( (int) $current_radius, $miles ); ?>>
									<?php echo esc_html( sprintf( __( 'Within %d miles', 'owambe-connect-core' ), $miles ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="oc-nearme" data-oc-nearme title="<?php esc_attr_e( 'Use my current location', 'owambe-connect-core' ); ?>">
							<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
							<span><?php esc_html_e( 'Near me', 'owambe-connect-core' ); ?></span>
						</button>
					</div>
					<input type="hidden" name="near_lat" value="<?php echo esc_attr( $current_nlat ); ?>" data-oc-filter-input />
					<input type="hidden" name="near_lng" value="<?php echo esc_attr( $current_nlng ); ?>" data-oc-filter-input />
				</div>
				<div class="oc-filters__actions">
					<button type="submit" class="oc-btn oc-btn-primary"><?php esc_html_e( 'Filter', 'owambe-connect-core' ); ?></button>
					<a class="oc-btn oc-btn-ghost" href="<?php echo esc_url( $action_url ); ?>"><?php esc_html_e( 'Reset', 'owambe-connect-core' ); ?></a>
				</div>
			</div>
		</form>
		<script>
		(function () {
			document.querySelectorAll('.oc-filters').forEach(function (form) {
				var toggle = form.querySelector('[data-oc-filters-toggle]');
				var row    = form.querySelector('.oc-filters__row');
				if (!toggle || !row) return;
				function setOpen(open) {
					form.classList.toggle('is-open', !!open);
					toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				}
				toggle.addEventListener('click', function (e) {
					e.preventDefault();
					e.stopPropagation();
					setOpen(!form.classList.contains('is-open'));
				});

				form.addEventListener('submit', function (e) {
					var inputs = form.querySelectorAll('[data-oc-filter-input]');
					inputs.forEach(function (input) {
						if (!input.value || input.value === '') {
							input.disabled = true;
						}
					});
				});

				// Typing a city overrides any earlier "Near me" fix: clear the
				// stale GPS coords so the server centres on the typed city.
				var cityInput = form.querySelector('input[name="city"]');
				if (cityInput) {
					cityInput.addEventListener('input', function () {
						var la = form.querySelector('input[name="near_lat"]');
						var lo = form.querySelector('input[name="near_lng"]');
						if (la) { la.value = ''; }
						if (lo) { lo.value = ''; }
					});
				}

				// "Near me" — browser geolocation fills the hidden centre
				// coords; a default radius kicks in if none is picked yet.
				var nearBtn = form.querySelector('[data-oc-nearme]');
				if (nearBtn) {
					nearBtn.addEventListener('click', function () {
						if (!navigator.geolocation) {
							window.alert('<?php echo esc_js( __( 'Your browser does not support location.', 'owambe-connect-core' ) ); ?>');
							return;
						}
						nearBtn.classList.add('is-busy');
						navigator.geolocation.getCurrentPosition(function (pos) {
							form.querySelector('input[name="near_lat"]').value = pos.coords.latitude.toFixed(6);
							form.querySelector('input[name="near_lng"]').value = pos.coords.longitude.toFixed(6);
							var radius = form.querySelector('select[name="radius"]');
							if (radius && !radius.value) { radius.value = '25'; }
							form.requestSubmit ? form.requestSubmit() : form.submit();
						}, function () {
							nearBtn.classList.remove('is-busy');
							window.alert('<?php echo esc_js( __( 'Location permission was denied — pick a city and a distance instead.', 'owambe-connect-core' ) ); ?>');
						}, { timeout: 8000, maximumAge: 300000 });
					});
				}
			});
		})();
		</script>
		<style>
			.oc-filters__field--distance .oc-distance { display: flex; gap: 8px; align-items: stretch; }
			.oc-filters__field--distance select { flex: 1 1 auto; min-width: 0; }
			.oc-nearme {
				display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
				padding: 0 14px; border: 1px solid var(--oc-border, #E4DDD2); border-radius: 10px;
				background: #fff; color: #6E0F2C; font: inherit; font-size: 14.5px; font-weight: 600; cursor: pointer;
				transition: background .15s ease, border-color .15s ease, box-shadow .15s ease;
			}
			.oc-nearme:hover { background: #FBF7F4; border-color: #6E0F2C; }
			.oc-nearme:focus-visible { outline: none; border-color: #6E0F2C; box-shadow: 0 0 0 3px rgba(110, 15, 44, .10); }
			.oc-nearme.is-busy { opacity: .6; pointer-events: none; }
		</style>
		<?php endif; ?>

		<?php if ( $query->have_posts() ) : ?>
			<div class="oc-grid oc-grid--vendors">
				<?php while ( $query->have_posts() ) : $query->the_post(); ?>
					<?php echo oc_get_template( 'partials/vendor-card.php', [ 'post_id' => get_the_ID() ] ); ?>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>

			<?php if ( $query->max_num_pages > 1 ) : ?>
				<nav class="oc-pagination" aria-label="<?php esc_attr_e( 'Vendor pagination', 'owambe-connect-core' ); ?>">
					<?php
					$current = max( 1, (int) $query->query_vars['paged'] );
					$base    = add_query_arg( 'paged', '%#%', $action_url );
					echo paginate_links( [
						'base'      => $base,
						'format'    => '',
						'current'   => $current,
						'total'     => (int) $query->max_num_pages,
						'prev_text' => esc_html__( '← Previous', 'owambe-connect-core' ),
						'next_text' => esc_html__( 'Next →',     'owambe-connect-core' ),
						'add_args'  => array_filter( [
							'cat'         => $current_cat,
							'vendor_name' => $current_search,
							'location'    => $current_loc,
							'city'        => $current_city,
							'cultural'    => $current_cultural,
							'nigerian'    => $current_nigerian,
							'radius'      => $current_radius > 0 ? (int) $current_radius : '',
							'near_lat'    => $current_nlat,
							'near_lng'    => $current_nlng,
						] ),
					] );
					?>
				</nav>
			<?php endif; ?>

		<?php else :
			$has_filters = ( '' !== $current_cat ) || ( '' !== $current_search ) || ( '' !== $current_loc ) || ( '' !== $current_city ) || ( '' !== $current_cultural ) || ( '' !== $current_nigerian );
		?>
			<div class="oc-empty">
				<?php if ( $has_filters ) : ?>
					<h3><?php esc_html_e( 'No vendors match your filters yet.', 'owambe-connect-core' ); ?></h3>
					<p><?php esc_html_e( 'Try clearing the filters, or browse a different category.', 'owambe-connect-core' ); ?></p>
					<a class="oc-btn oc-btn-primary" href="<?php echo esc_url( $action_url ); ?>"><?php esc_html_e( 'Show all vendors', 'owambe-connect-core' ); ?></a>
				<?php else : ?>
					<h3><?php esc_html_e( 'No vendors are listed just yet.', 'owambe-connect-core' ); ?></h3>
					<p><?php esc_html_e( 'We\'re building our directory of trusted UK event vendors. Be one of the first to join.', 'owambe-connect-core' ); ?></p>
					<a class="oc-btn oc-btn-primary" href="<?php echo esc_url( oc_page_url( 'apply' ) ); ?>"><?php esc_html_e( 'List your business', 'owambe-connect-core' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
