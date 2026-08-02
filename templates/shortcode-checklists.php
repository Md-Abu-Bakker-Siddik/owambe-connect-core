<?php
/**
 * Planning resources / checklists page (P14).
 *
 * @package OwambeConnect
 * @var array  $resources
 * @var string $heading
 * @var string $subheading
 */
defined( 'ABSPATH' ) || exit;

$heading    = ! empty( $heading )    ? $heading    : __( 'Planning Resources', 'owambe-connect-core' );
$subheading = ! empty( $subheading ) ? $subheading : __( 'Free checklists, contract templates and guides to help you plan a memorable event — download, print, and tick things off.', 'owambe-connect-core' );

$type_icons = [
	// Loose keyword → icon mapping; anything unmatched gets the document icon.
	'checklist' => 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
	'contract'  => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8',
	'guide'     => 'M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2zM22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z',
];
$icon_for = function ( $type ) use ( $type_icons ) {
	$t = mb_strtolower( (string) $type );
	foreach ( $type_icons as $k => $path ) {
		if ( false !== strpos( $t, $k ) || ( 'contract' === $k && false !== strpos( $t, 'template' ) ) ) {
			return $path;
		}
	}
	return 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6';
};
?>
<section class="oc-section oc-checklists">
	<div class="oc-container">
		<header class="oc-checklists__head">
			<h1 class="oc-checklists__title"><?php echo esc_html( $heading ); ?></h1>
			<p class="oc-checklists__lead"><?php echo esc_html( $subheading ); ?></p>
		</header>

		<?php if ( $resources ) : ?>
			<div class="oc-checklists__grid">
				<?php foreach ( $resources as $r ) : ?>
					<article class="oc-checklists__card">
						<div class="oc-checklists__icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="<?php echo esc_attr( $icon_for( $r['type'] ) ); ?>"/></svg>
						</div>
						<?php if ( $r['type'] ) : ?>
							<span class="oc-checklists__chip"><?php echo esc_html( $r['type'] ); ?></span>
						<?php endif; ?>
						<h2 class="oc-checklists__name"><?php echo esc_html( $r['title'] ); ?></h2>
						<?php if ( $r['desc'] ) : ?>
							<p class="oc-checklists__desc"><?php echo esc_html( $r['desc'] ); ?></p>
						<?php endif; ?>
						<a class="oc-checklists__btn" href="<?php echo esc_url( $r['url'] ); ?>" target="_blank" rel="noopener" download>
							<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
							<?php esc_html_e( 'Download', 'owambe-connect-core' ); ?>
						</a>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="oc-checklists__empty">
				<h2><?php esc_html_e( 'Resources coming soon', 'owambe-connect-core' ); ?></h2>
				<p><?php esc_html_e( 'We are preparing free planning checklists, contract templates and guides — check back shortly.', 'owambe-connect-core' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>

<style>
	.oc-checklists { padding: 56px 0 72px; }
	.oc-checklists .oc-container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
	.oc-checklists__head { max-width: 720px; margin-bottom: 36px; }
	.oc-checklists__title { margin: 0 0 10px; font-family: var(--oc-font-serif, Georgia, serif); color: #6E0F2C; font-size: clamp(2rem, 4vw, 2.8rem); }
	.oc-checklists__lead { margin: 0; color: #6B6361; font-size: 1.05rem; line-height: 1.6; }
	.oc-checklists__grid { display: grid; grid-template-columns: 1fr; gap: 22px; }
	@media (min-width: 640px)  { .oc-checklists__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
	@media (min-width: 1024px) { .oc-checklists__grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
	.oc-checklists__card {
		position: relative; display: flex; flex-direction: column; align-items: flex-start;
		padding: 26px 24px 24px; background: #fff; border: 1px solid var(--oc-border, #E4DDD2);
		border-radius: 16px; transition: transform .18s ease, box-shadow .18s ease;
	}
	.oc-checklists__card:hover { transform: translateY(-3px); box-shadow: 0 14px 34px rgba(31, 27, 26, .10); }
	.oc-checklists__icon {
		display: flex; align-items: center; justify-content: center; width: 52px; height: 52px;
		margin-bottom: 14px; border-radius: 14px; color: #6E0F2C;
		background: linear-gradient(135deg, #FBF3F5, #F6E9ED); border: 1px solid rgba(110, 15, 44, .12);
	}
	.oc-checklists__chip {
		position: absolute; top: 22px; right: 20px; padding: 4px 10px; border-radius: 999px;
		background: #FAF6EC; border: 1px solid rgba(168, 137, 61, .35); color: #8a6d2f;
		font-size: 11.5px; font-weight: 700; letter-spacing: .03em;
	}
	.oc-checklists__name { margin: 0 0 8px; font-family: var(--oc-font-serif, Georgia, serif); color: #1F1B1A; font-size: 1.22rem; }
	.oc-checklists__desc { flex: 1; margin: 0 0 18px; color: #6B6361; font-size: .94rem; line-height: 1.6; }
	.oc-checklists__btn {
		display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px;
		border: 1.5px solid #6E0F2C; border-radius: 999px; background: #fff; color: #6E0F2C;
		font-weight: 700; font-size: .92rem; text-decoration: none;
		transition: background .15s ease, color .15s ease;
	}
	/* High-specificity hover: the theme's global a:hover colour rules would
	   otherwise keep the label burgundy on the burgundy fill. The SVG uses
	   stroke="currentColor", so it follows along. */
	section.oc-checklists a.oc-checklists__btn:hover,
	section.oc-checklists a.oc-checklists__btn:focus-visible {
		background: #6E0F2C;
		color: #fff;
	}
	.oc-checklists__empty {
		background: #fff; border: 2px dashed var(--oc-border, #E4DDD2); border-radius: 18px;
		padding: 56px 20px; text-align: center;
	}
	.oc-checklists__empty h2 { margin: 0 0 8px; font-family: var(--oc-font-serif, Georgia, serif); color: #6E0F2C; }
	.oc-checklists__empty p { margin: 0; color: #6B6361; }
</style>
