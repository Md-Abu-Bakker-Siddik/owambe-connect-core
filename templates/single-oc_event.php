<?php
/**
 * Plugin fallback single template for the oc_event CPT.
 *
 * WordPress' template hierarchy would otherwise fall back to the active theme's
 * index.php for a single event, which typically doesn't render the_content() —
 * so shortcodes placed in the event body (e.g. [oc_rsvp_form]) never run. This
 * template renders a centred hero (title + meta badges + description) and then
 * auto-appends the RSVP form, all inside the theme's own header/footer chrome.
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

			$oc_event_id = get_the_ID();

			// Optional meta from the Custom Fields box — support a couple of common
			// key spellings so clients aren't forced into one convention. Empty
			// values simply don't render a badge.
			$oc_meta_first = static function ( $keys ) use ( $oc_event_id ) {
				foreach ( (array) $keys as $k ) {
					$v = get_post_meta( $oc_event_id, $k, true );
					if ( '' !== trim( (string) $v ) ) {
						return trim( (string) $v );
					}
				}
				return '';
			};

			$oc_date     = $oc_meta_first( [ 'event_date', '_oc_event_date', 'oc_event_date' ] );
			$oc_time     = $oc_meta_first( [ 'event_time', '_oc_event_time', 'oc_event_time' ] );
			$oc_location = $oc_meta_first( [ 'event_location', '_oc_event_location', 'event_venue', 'oc_event_location' ] );
			$oc_datetime = trim( $oc_date . ( ( $oc_date && $oc_time ) ? ' · ' : '' ) . $oc_time );

			$oc_host_user = get_userdata( (int) get_post_field( 'post_author', $oc_event_id ) );
			$oc_host      = $oc_host_user ? $oc_host_user->display_name : '';

			// Inline icons (kept tiny; currentColor so they inherit badge colour).
			$oc_ico_cal = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
			$oc_ico_pin = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
			$oc_ico_usr = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

			// Build the badge list — only non-empty entries render.
			$oc_badges = [];
			if ( $oc_datetime ) {
				$oc_badges[] = [ 'icon' => $oc_ico_cal, 'text' => $oc_datetime ];
			}
			if ( $oc_location ) {
				$oc_badges[] = [ 'icon' => $oc_ico_pin, 'text' => $oc_location ];
			}
			if ( $oc_host ) {
				$oc_badges[] = [
					'icon' => $oc_ico_usr,
					/* translators: %s: host display name */
					'text' => sprintf( __( 'Hosted by %s', 'owambe-connect-core' ), $oc_host ),
				];
			}
			// Allow themes/plugins to add or replace badges: each item = [icon,text].
			$oc_badges = apply_filters( 'oc_event_meta_badges', $oc_badges, $oc_event_id );
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'oc-event' ); ?>>

				<header class="oc-event-hero">
					<span class="oc-event__eyebrow"><?php esc_html_e( 'You&#8217;re Invited', 'owambe-connect-core' ); ?></span>
					<h1 class="oc-event__title"><?php the_title(); ?></h1>

					<?php if ( ! empty( $oc_badges ) ) : ?>
						<ul class="oc-event__meta" role="list">
							<?php foreach ( $oc_badges as $oc_badge ) : ?>
								<li class="oc-event__badge">
									<span class="oc-event__badge-ico"><?php echo wp_kses( $oc_badge['icon'], [ 'svg' => [ 'viewbox' => [], 'width' => [], 'height' => [], 'fill' => [], 'stroke' => [], 'stroke-width' => [], 'stroke-linecap' => [], 'stroke-linejoin' => [], 'aria-hidden' => [] ], 'rect' => [ 'x' => [], 'y' => [], 'width' => [], 'height' => [], 'rx' => [] ], 'path' => [ 'd' => [] ], 'circle' => [ 'cx' => [], 'cy' => [], 'r' => [] ] ] ); ?></span>
									<span class="oc-event__badge-txt"><?php echo esc_html( $oc_badge['text'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<div class="oc-event__ornament" aria-hidden="true">
						<span class="oc-event__ornament-line"></span>
						<svg width="11" height="11" viewBox="0 0 10 10" fill="currentColor"><path d="M5 0 10 5 5 10 0 5Z"/></svg>
						<span class="oc-event__ornament-line"></span>
					</div>
				</header>

				<div class="oc-event__content entry-content">
					<?php the_content(); ?>
				</div>

				<?php
				/**
				 * After the event body — extension point for event sections such as
				 * the gift registry (OC_Registry renders here).
				 *
				 * @param int $event_id
				 */
				do_action( 'oc_event_after_content', $oc_event_id );
				?>

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
/* ─── Owambe Connect · single event ─────────────────────────────────────────
   Scoped under .oc-event-single so it can't leak into theme styles. */
.oc-event-single {
	--oc-ev-burgundy: #581825;
	--oc-ev-burgundy-soft: rgba(88, 24, 37, 0.08);
	--oc-ev-border: #ECE4E6;
	--oc-ev-ink-soft: #5B5157;
	--oc-ev-gold: #C9A961;
	--oc-ev-gold-deep: #A9823C;
	position: relative;
	background: #FFFDFB;
}
/* Soft warm glow behind the hero — gives the page depth instead of flat white. */
.oc-event-single::before {
	content: "";
	position: absolute;
	top: 0; left: 0; right: 0;
	height: 560px;
	background:
		radial-gradient(90% 100% at 50% -18%, rgba(201, 169, 97, 0.14), transparent 62%),
		radial-gradient(130% 90% at 50% -45%, rgba(88, 24, 37, 0.08), transparent 58%);
	pointer-events: none;
	z-index: 0;
}
.oc-event-single__inner {
	position: relative;
	z-index: 1;
	max-width: 768px;
	margin: 0 auto;
	padding: clamp(2.5rem, 5vw, 4rem) 1.25rem;
}

/* ── Centred hero ─────────────────────────────────────────────────────────── */
.oc-event-hero {
	text-align: center;
	margin: 0 0 clamp(2rem, 5vw, 3rem);
}
.oc-event__eyebrow {
	display: inline-block;
	margin: 0 0 0.95rem;
	font-size: 12px;
	font-weight: 700;
	letter-spacing: 0.28em;
	text-transform: uppercase;
	color: var(--oc-ev-gold-deep);
}
.oc-event__title {
	color: var(--oc-ev-burgundy);
	font-size: clamp(2.1rem, 5.5vw, 3.1rem);
	line-height: 1.06;
	font-weight: 800;
	letter-spacing: -0.02em;
	margin: 0 auto 1.35rem;
	max-width: 18ch;
	text-wrap: balance;
}

/* Shared section eyebrow + subtitle (used by the gift-registry sections too,
   so they're defined here where the CSS is always present). */
.oc-event__section-eyebrow {
	display: inline-block;
	margin: 0 0 0.55rem;
	font-size: 11.5px;
	font-weight: 700;
	letter-spacing: 0.26em;
	text-transform: uppercase;
	color: var(--oc-ev-gold-deep);
}
.oc-event__section-sub {
	margin: 0 auto 1.6rem;
	max-width: 44ch;
	color: #8A7F82;
	font-size: 14.5px;
	line-height: 1.6;
}
/* Shared registry heading — defined here (always present) so a section works
   whichever gift-registry part is enabled. */
.oc-event__registry-title {
	color: var(--oc-ev-burgundy);
	font-size: clamp(1.5rem, 3vw, 1.95rem);
	font-weight: 800;
	letter-spacing: -0.01em;
	margin: 0 0 0.5rem;
}

/* Meta badges */
.oc-event__meta {
	list-style: none;
	margin: 0 0 1.4rem;
	padding: 0;
	display: flex;
	flex-wrap: wrap;
	justify-content: center;
	gap: 10px;
}
.oc-event__badge {
	display: inline-flex;
	align-items: center;
	gap: 7px;
	padding: 7px 14px;
	background: var(--oc-ev-burgundy-soft);
	border: 1px solid var(--oc-ev-border);
	border-radius: 999px;
	color: var(--oc-ev-burgundy);
	font-size: 13.5px;
	font-weight: 600;
	line-height: 1.2;
	white-space: nowrap;
}
.oc-event__badge-ico { display: inline-flex; opacity: 0.9; }
.oc-event__badge-txt { white-space: normal; }

/* Ornamental gold divider under the hero — a line·diamond·line motif that reads
   like a printed invitation rather than a plain rule. */
.oc-event__ornament {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 14px;
	margin: 1.6rem auto 0;
	color: var(--oc-ev-gold);
}
.oc-event__ornament-line {
	display: block;
	width: 54px;
	height: 1px;
	background: linear-gradient(90deg, transparent, var(--oc-ev-gold-deep));
}
.oc-event__ornament-line:last-child {
	background: linear-gradient(90deg, var(--oc-ev-gold-deep), transparent);
}
.oc-event__ornament svg { flex: 0 0 auto; }

/* ── Description / body ───────────────────────────────────────────────────── */
.oc-event__content {
	max-width: 600px;
	margin: 0 auto;
	text-align: center;
	color: var(--oc-ev-ink-soft);
	font-size: clamp(1.02rem, 1.6vw, 1.1rem);
	line-height: 1.8;
}
.oc-event__content > * + * { margin-top: 1.1rem; }
.oc-event__content a { color: var(--oc-ev-burgundy); text-underline-offset: 2px; }
/* Media inside the body should never blow past the reading column. */
.oc-event__content img,
.oc-event__content iframe { max-width: 100%; height: auto; border-radius: 14px; }

/* ── Section rhythm ───────────────────────────────────────────────────────────
   Every major block after the description (gift registry, RSVP) shares the same
   generous top margin + a hairline divider, so the page reads as clean, evenly
   spaced bands rather than a stack with uneven gaps. The registry sections opt
   in via .oc-event__registry / .oc-event__registryb (styled in class-registry). */
.oc-event__rsvp,
.oc-event-single .oc-event__registry,
.oc-event-single .oc-event__registryb {
	margin-top: clamp(2.25rem, 5vw, 3.25rem) !important;
	padding-top: clamp(2.25rem, 5vw, 3.25rem);
	border-top: 1px solid var(--oc-ev-border);
}

@media (max-width: 600px) {
	.oc-event__badge { font-size: 13px; padding: 6px 12px; }
	.oc-event__content { text-align: left; } /* easier reading on narrow screens */
}
</style>
<?php
get_footer();
