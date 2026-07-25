<?php
/**
 * Plugin fallback single template for the oc_event CPT.
 *
 * WordPress' template hierarchy would otherwise fall back to the active theme's
 * index.php for a single event, which typically doesn't render the_content() —
 * so shortcodes placed in the event body (e.g. [oc_rsvp_form]) never run. This
 * template renders the_content() through the standard filters (which includes
 * do_shortcode) inside the theme's own header/footer chrome.
 *
 * A theme can override this by shipping its own single-oc_event.php.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="oc-event-main" class="oc-event-single" role="main">
	<div class="oc-container oc-event-single__inner">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'oc-event' ); ?>>
				<header class="oc-event__header">
					<h1 class="oc-event__title"><?php the_title(); ?></h1>
				</header>

				<div class="oc-event__content entry-content">
					<?php the_content(); ?>
				</div>

				<?php
				// Auto-render the RSVP form at the bottom of every event page, so
				// clients don't have to paste the shortcode into the editor. Guard
				// against a double form if they added [oc_rsvp_form] to the content.
				if ( false === strpos( (string) get_the_content(), '[oc_rsvp_form' ) ) {
					echo '<div class="oc-event__rsvp">' . do_shortcode( '[oc_rsvp_form]' ) . '</div>';
				}
				?>
			</article>
			<?php
		endwhile;
		?>
	</div>
</main>

<style>
.oc-event-single__inner { max-width: 860px; margin: 0 auto; padding: 2.5rem 1rem; }
.oc-event__title { margin: 0 0 1.25rem; }
.oc-event__content { line-height: 1.7; }
.oc-event__content > * + * { margin-top: 1rem; }
</style>
<?php
get_footer();
