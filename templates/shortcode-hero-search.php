<?php
/**
 * Hero search shortcode template.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$categories       = OC_Queries::categories_with_counts();
$directory_action = oc_page_url( 'vendors' );
$current_location = isset( $_GET['location'] ) ? sanitize_text_field( wp_unslash( $_GET['location'] ) ) : '';

// Suggestion list for the typeahead â€” 4 home countries + the 9 canonical
// England regions + every major UK city. The region labels come from
// oc_region_options() (the SAME strings saved into _oc_location), so typing a
// region and searching LIKE-matches the vendors who selected it. One combined
// list keeps the UX simple: the user types whatever feels natural.
$hero_suggestions = function_exists( 'oc_location_suggestions' ) ? oc_location_suggestions() : array_values( array_unique( array_merge(
	array_values( function_exists( 'oc_country_options' ) ? oc_country_options() : [ 'England', 'Scotland', 'Wales', 'Northern Ireland' ] ),
	function_exists( 'oc_region_options' ) ? oc_region_options() : [],
	function_exists( 'oc_city_options' ) ? oc_city_options() : []
) ) );

$eyebrow          = ! empty( $eyebrow )          ? $eyebrow          : __( 'Connecting Events. Celebrating Culture.', 'owambe-connect-core' );
$heading          = ! empty( $heading )          ? $heading          : __( 'Find the right event vendors for your celebration.', 'owambe-connect-core' );
$subheading       = ! empty( $subheading )       ? $subheading       : __( 'Discover trusted caterers, photographers, decorators, MUAs and more â€” all in one place, all serving the UK\'s vibrant minority communities.', 'owambe-connect-core' );
$search_btn_label = ! empty( $search_btn_label ) ? $search_btn_label : __( 'Search Vendors', 'owambe-connect-core' );
$popular_label    = ! empty( $popular_label )    ? $popular_label    : __( 'Popular:', 'owambe-connect-core' );
$button_text      = isset( $button_text )        ? $button_text      : '';
$button_url       = isset( $button_url )         ? $button_url       : '';
$show_search      = ! isset( $show_search )      || 'yes' === $show_search;
$show_popular     = ! isset( $show_popular )     || 'yes' === $show_popular;
$bg_image_url     = ! empty( $bg_image_url )     ? esc_url( $bg_image_url ) : '';
?>
<section class="oc-hero<?php echo $bg_image_url ? ' oc-hero--has-bg' : ''; ?>">
	<?php if ( $bg_image_url ) : ?>
	<div class="oc-hero__bg" aria-hidden="true">
		<img src="<?php echo $bg_image_url; ?>" alt=""/>
	</div>
	<?php endif; ?>
	<div class="oc-hero__inner">
		<?php if ( $eyebrow ) : ?><p class="oc-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
		<h1 class="oc-hero__title"><?php echo esc_html( $heading ); ?></h1>
		<?php if ( $subheading ) : ?><p class="oc-hero__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>

		<?php if ( $button_text ) : ?>
			<div class="oc-hero__cta">
				<a class="oc-btn oc-btn-primary oc-btn-lg" href="<?php echo esc_url( $button_url ?: oc_page_url( 'vendors' ) ); ?>"><?php echo esc_html( $button_text ); ?></a>
			</div>
		<?php endif; ?>

		<?php if ( $show_search ) : ?>
		<form class="oc-hero__form" method="get" action="<?php echo esc_url( $directory_action ); ?>" role="search">
			<div class="oc-hero__field">
				<label class="oc-sr-only" for="oc-hero-cat"><?php esc_html_e( 'Category', 'owambe-connect-core' ); ?></label>
				<select id="oc-hero-cat" name="cat" class="oc-hero-search__cat">
					<option value=""><?php esc_html_e( 'â—†  All categories', 'owambe-connect-core' ); ?></option>
					<?php foreach ( $categories as $term ) :
						$icon = function_exists( 'oc_get_category_icon' ) ? oc_get_category_icon( $term ) : [];
						$glyph = ! empty( $icon['emoji'] ) ? $icon['emoji'] : 'â—†';
					?>
						<option value="<?php echo esc_attr( $term->slug ); ?>"><?php
							echo esc_html( $glyph . '  ' . str_repeat( 'â€” ', (int) ( $term->depth ?? 0 ) ) . $term->name );
						?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="oc-hero__field oc-typeahead" data-oc-typeahead>
				<label class="oc-sr-only" for="oc-hero-loc"><?php esc_html_e( 'City, region, or area', 'owambe-connect-core' ); ?></label>
				<input
					id="oc-hero-loc"
					name="location"
					type="text"
					class="oc-hero-search__city oc-typeahead__input"
					autocomplete="off"
					spellcheck="false"
					placeholder="<?php esc_attr_e( 'ðŸ“ City or region (e.g. London)', 'owambe-connect-core' ); ?>"
					value="<?php echo esc_attr( $current_location ); ?>"
					data-suggestions="<?php echo esc_attr( wp_json_encode( $hero_suggestions ) ); ?>"
					aria-autocomplete="list"
					aria-expanded="false"
					aria-controls="oc-hero-suggestions"
				/>
				<button type="button" class="oc-typeahead__clear" data-oc-typeahead-clear aria-label="<?php esc_attr_e( 'Clear', 'owambe-connect-core' ); ?>" hidden>&times;</button>
				<ul id="oc-hero-suggestions" class="oc-typeahead__list" role="listbox" hidden></ul>
			</div>
			<button type="submit" class="oc-btn oc-btn-primary oc-hero__submit"><?php echo esc_html( $search_btn_label ); ?></button>
		</form>
		<?php endif; ?>

		<?php if ( $show_popular && ! empty( $categories ) ) : ?>
		<div class="oc-hero__quick">
			<span class="oc-hero__quick-label"><?php echo esc_html( $popular_label ); ?></span>
			<?php
			// Popular pills stay top-level â€” subcategories belong in the select.
			$popular = array_slice( array_values( array_filter( $categories, function ( $t ) {
				return 0 === (int) ( $t->depth ?? 0 );
			} ) ), 0, 5 );
			foreach ( $popular as $term ) :
				$url = add_query_arg( 'cat', $term->slug, $directory_action );
				?>
				<a class="oc-pill" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $term->name ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
</section>
