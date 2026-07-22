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

// Suggestion list for the typeahead — 4 home countries + the 9 canonical
// England regions + every major UK city. The region labels come from
// oc_region_options() (the SAME strings saved into _oc_location), so typing a
// region and searching LIKE-matches the vendors who selected it. One combined
// list keeps the UX simple: the user types whatever feels natural.
$hero_suggestions = array_values( array_unique( array_merge(
	array_values( function_exists( 'oc_country_options' ) ? oc_country_options() : [ 'England', 'Scotland', 'Wales', 'Northern Ireland' ] ),
	function_exists( 'oc_region_options' ) ? oc_region_options() : [],
	function_exists( 'oc_city_options' ) ? oc_city_options() : []
) ) );

$eyebrow          = ! empty( $eyebrow )          ? $eyebrow          : __( 'Connecting Events. Celebrating Culture.', 'owambe-connect-core' );
$heading          = ! empty( $heading )          ? $heading          : __( 'Find the right event vendors for your celebration.', 'owambe-connect-core' );
$subheading       = ! empty( $subheading )       ? $subheading       : __( 'Discover trusted caterers, photographers, decorators, MUAs and more — all in one place, all serving the UK\'s vibrant minority communities.', 'owambe-connect-core' );
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
					<option value=""><?php esc_html_e( '◆  All categories', 'owambe-connect-core' ); ?></option>
					<?php foreach ( $categories as $term ) :
						$icon = function_exists( 'oc_get_category_icon' ) ? oc_get_category_icon( $term ) : [];
						$glyph = ! empty( $icon['emoji'] ) ? $icon['emoji'] : '◆';
					?>
						<option value="<?php echo esc_attr( $term->slug ); ?>"><?php
							echo esc_html( $glyph . '  ' . $term->name );
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
					placeholder="<?php esc_attr_e( '📍 City or region (e.g. London)', 'owambe-connect-core' ); ?>"
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
			$popular = array_slice( $categories, 0, 5 );
			foreach ( $popular as $term ) :
				$url = add_query_arg( 'cat', $term->slug, $directory_action );
				?>
				<a class="oc-pill" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $term->name ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
</section>
<script>
(function () {
	var box = document.querySelector('[data-oc-typeahead]');
	if (!box) return;
	var input = box.querySelector('.oc-typeahead__input');
	var list  = box.querySelector('.oc-typeahead__list');
	var clear = box.querySelector('[data-oc-typeahead-clear]');
	if (!input || !list) return;

	var suggestions = [];
	try { suggestions = JSON.parse(input.getAttribute('data-suggestions') || '[]'); } catch (e) {}
	var active = -1;
	var matches = [];

	function norm(s) { return (s || '').toString().toLowerCase().trim(); }

	function render(items) {
		list.innerHTML = '';
		matches = items;
		if (!items.length) { list.hidden = true; input.setAttribute('aria-expanded', 'false'); return; }
		var frag = document.createDocumentFragment();
		items.forEach(function (label, i) {
			var li = document.createElement('li');
			li.className = 'oc-typeahead__item';
			li.setAttribute('role', 'option');
			li.setAttribute('data-i', i);
			li.textContent = label;
			frag.appendChild(li);
		});
		list.appendChild(frag);
		list.hidden = false;
		input.setAttribute('aria-expanded', 'true');
		active = -1;
	}

	// A small curated set of "popular picks" shown the moment the user
	// focuses the input — gives one-tap access to the obvious choices
	// without forcing them to type a single letter.
	var defaults = [ 'London', 'Manchester', 'Birmingham', 'Edinburgh', 'Cardiff', 'Belfast' ]
		.filter(function (label) { return suggestions.indexOf(label) !== -1; });

	function filter() {
		var q = norm(input.value);
		clear.hidden = !input.value;
		if (!q) { render(defaults); return; }
		// Prefix matches first, then substring matches — feels faster.
		var prefix = [], sub = [];
		for (var i = 0; i < suggestions.length; i++) {
			var v = suggestions[i];
			var nv = norm(v);
			if (nv === q) continue;
			if (nv.indexOf(q) === 0) prefix.push(v);
			else if (nv.indexOf(q) > 0) sub.push(v);
		}
		render(prefix.concat(sub).slice(0, 10));
	}

	function pick(value) {
		input.value = value;
		render([]);
		clear.hidden = false;
		// Submit the form immediately so the user lands on /vendors/?location=...
		var form = input.closest('form');
		if (form) form.submit();
	}

	function highlight(idx) {
		var items = list.querySelectorAll('.oc-typeahead__item');
		items.forEach(function (el, i) {
			el.classList.toggle('is-active', i === idx);
		});
		active = idx;
	}

	input.addEventListener('input', filter);
	input.addEventListener('focus', function () { filter(); });
	input.addEventListener('click', function () { filter(); });
	input.addEventListener('keydown', function (e) {
		if (list.hidden || !matches.length) return;
		if (e.key === 'ArrowDown') { e.preventDefault(); highlight((active + 1) % matches.length); }
		else if (e.key === 'ArrowUp') { e.preventDefault(); highlight((active - 1 + matches.length) % matches.length); }
		else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); pick(matches[active]); }
		else if (e.key === 'Escape') { render([]); }
	});
	list.addEventListener('mousedown', function (e) {
		// mousedown rather than click so the input doesn't blur first.
		var el = e.target.closest('.oc-typeahead__item');
		if (!el) return;
		e.preventDefault();
		var i = parseInt(el.getAttribute('data-i'), 10);
		if (!isNaN(i) && matches[i]) pick(matches[i]);
	});
	document.addEventListener('click', function (e) {
		if (!box.contains(e.target)) render([]);
	});
	clear.addEventListener('click', function () {
		input.value = '';
		clear.hidden = true;
		render([]);
		input.focus();
	});
	// Initial: if there's a prefilled value (URL has ?location=...) show the clear button.
	if (input.value) clear.hidden = false;
})();
</script>
