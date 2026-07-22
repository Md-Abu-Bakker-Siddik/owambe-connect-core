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

		<?php if ( $show_filters ) :
			// Count how many filters are active so the mobile toggle button
			// can show a small badge ("Filters · 2") — gives the user a
			// visual cue that filters are constraining the list even when
			// the panel is collapsed.
			$active_filter_count = (int) ( '' !== $current_search )
				+ (int) ( '' !== $current_cat )
				+ (int) ( '' !== $current_loc )
				+ (int) ( '' !== $current_city );
			// Preserve backward compatibility: support old ?s= parameter format
			if ( '' === $current_search && isset( $_GET['s'] ) ) {
				$current_search = sanitize_text_field( wp_unslash( $_GET['s'] ) );
				$active_filter_count = (int) ( '' !== $current_search ) + (int) ( '' !== $current_cat ) + (int) ( '' !== $current_loc ) + (int) ( '' !== $current_city );
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
			});
		})();
		</script>
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
						] ),
					] );
					?>
				</nav>
			<?php endif; ?>

		<?php else :
			$has_filters = ( '' !== $current_cat ) || ( '' !== $current_search ) || ( '' !== $current_loc ) || ( '' !== $current_city );
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
