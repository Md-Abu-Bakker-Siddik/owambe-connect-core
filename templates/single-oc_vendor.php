<?php
/**
 * Single vendor template — full-bleed profile.
 *
 * Loaded via template_include for any /vendor/<slug>/ URL. We only call
 * get_header() and get_footer() so the site nav/footer stay consistent —
 * but the theme's default single-post chrome (post title, byline, post
 * navigation, comments) is intentionally skipped so our profile design
 * controls the entire page width.
 *
 * Themes can override this by adding `single-oc_vendor.php` at the theme
 * root — the OC_Shortcodes::use_single_template() filter prefers theme
 * overrides via locate_template().
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="oc-vendor-main" class="oc-vendor-main" role="main">
<?php
while ( have_posts() ) {
	the_post();
	$id = get_the_ID();
	if ( $id && OC_CPT === get_post_type( $id ) ) {
		echo oc_get_template( 'shortcode-vendor-profile.php', [ 'post_id' => $id ] );
	}
}
?>
</main>
<style>
	/* Make sure our profile gets the full viewport width regardless of how
	   the theme normally constrains <main>. */
	body.single-<?php echo esc_attr( OC_CPT ); ?> .oc-vendor-main { max-width: none; width: 100%; padding: 0; margin: 0; }
	body.single-<?php echo esc_attr( OC_CPT ); ?> .wp-site-blocks > main,
	body.single-<?php echo esc_attr( OC_CPT ); ?> .site-main { max-width: none !important; padding: 0 !important; }
</style>
<?php
get_footer();
