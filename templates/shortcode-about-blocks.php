<?php
/**
 * About Blocks template — story, mission, values, timeline, CTA.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$show_story    = ( $show_story    ?? 'yes' ) === 'yes';
$show_mission  = ( $show_mission  ?? 'yes' ) === 'yes';
$show_values   = ( $show_values   ?? 'yes' ) === 'yes';
$show_timeline = ( $show_timeline ?? 'no' )  === 'yes';
$show_cta      = ( $show_cta      ?? 'yes' ) === 'yes';
?>
<article class="oc-about">

	<?php if ( $show_story ) : ?>
		<section class="oc-about__section oc-about__story">
			<div class="oc-container oc-about__container">
				<div class="oc-about__story-grid<?php echo empty( $story_image ) ? ' oc-about__story-grid--no-image' : ''; ?>">
					<div class="oc-about__story-text">
						<?php if ( ! empty( $story_eyebrow ) ) : ?>
							<p class="oc-about__eyebrow"><?php echo esc_html( $story_eyebrow ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $story_heading ) ) : ?>
							<h2 class="oc-about__title"><?php echo esc_html( $story_heading ); ?></h2>
						<?php endif; ?>
						<?php if ( ! empty( $story_body ) ) : ?>
							<div class="oc-about__body"><?php echo wp_kses_post( wpautop( $story_body ) ); ?></div>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $story_image ) ) : ?>
						<div class="oc-about__story-image">
							<img src="<?php echo esc_url( $story_image ); ?>" alt="" loading="lazy" />
						</div>
					<?php endif; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $show_mission ) : ?>
		<section class="oc-about__section oc-about__mission">
			<div class="oc-container oc-about__container">
				<div class="oc-about__mv-grid">
					<div class="oc-about__mv-card">
						<span class="oc-about__mv-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
						</span>
						<h3 class="oc-about__mv-title"><?php echo esc_html( $mission_title ?? '' ); ?></h3>
						<p class="oc-about__mv-text"><?php echo esc_html( $mission_text ?? '' ); ?></p>
					</div>
					<div class="oc-about__mv-card oc-about__mv-card--alt">
						<span class="oc-about__mv-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
						</span>
						<h3 class="oc-about__mv-title"><?php echo esc_html( $vision_title ?? '' ); ?></h3>
						<p class="oc-about__mv-text"><?php echo esc_html( $vision_text ?? '' ); ?></p>
					</div>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $show_values && ! empty( $values_items ) ) : ?>
		<section class="oc-about__section oc-about__values">
			<div class="oc-container oc-about__container">
				<?php if ( ! empty( $values_heading ) ) : ?>
					<div class="oc-section__head">
						<h2 class="oc-section__title"><?php echo esc_html( $values_heading ); ?></h2>
					</div>
				<?php endif; ?>
				<div class="oc-about__values-grid">
					<?php foreach ( $values_items as $v ) : ?>
						<div class="oc-about__value">
							<?php if ( ! empty( $v['icon'] ) ) : ?>
								<span class="oc-about__value-icon" aria-hidden="true"><?php echo esc_html( $v['icon'] ); ?></span>
							<?php endif; ?>
							<h3 class="oc-about__value-title"><?php echo esc_html( $v['title'] ?? '' ); ?></h3>
							<p class="oc-about__value-desc"><?php echo esc_html( $v['description'] ?? '' ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $show_timeline && ! empty( $timeline_items ) ) : ?>
		<section class="oc-about__section oc-about__timeline-wrap">
			<div class="oc-container oc-about__container">
				<?php if ( ! empty( $timeline_heading ) ) : ?>
					<div class="oc-section__head">
						<h2 class="oc-section__title"><?php echo esc_html( $timeline_heading ); ?></h2>
					</div>
				<?php endif; ?>
				<ol class="oc-about__timeline">
					<?php foreach ( $timeline_items as $t ) : ?>
						<li class="oc-about__timeline-item">
							<span class="oc-about__timeline-dot" aria-hidden="true"></span>
							<span class="oc-about__timeline-date"><?php echo esc_html( $t['date'] ?? '' ); ?></span>
							<h3 class="oc-about__timeline-title"><?php echo esc_html( $t['title'] ?? '' ); ?></h3>
							<p class="oc-about__timeline-desc"><?php echo esc_html( $t['description'] ?? '' ); ?></p>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $show_cta ) : ?>
		<section class="oc-about__section oc-about__cta">
			<div class="oc-container oc-about__container">
				<div class="oc-about__cta-card">
					<?php if ( ! empty( $cta_heading ) ) : ?>
						<h2 class="oc-about__cta-heading"><?php echo esc_html( $cta_heading ); ?></h2>
					<?php endif; ?>
					<?php if ( ! empty( $cta_text ) ) : ?>
						<p class="oc-about__cta-text"><?php echo esc_html( $cta_text ); ?></p>
					<?php endif; ?>
					<div class="oc-about__cta-actions">
						<?php if ( ! empty( $cta_primary_text ) ) : ?>
							<a class="oc-btn oc-btn-primary oc-btn-lg" href="<?php echo esc_url( $cta_primary_url ?: oc_page_url( 'vendors' ) ); ?>"><?php echo esc_html( $cta_primary_text ); ?></a>
						<?php endif; ?>
						<?php if ( ! empty( $cta_secondary_text ) ) : ?>
							<a class="oc-btn oc-btn-ghost-light" href="<?php echo esc_url( $cta_secondary_url ?: oc_page_url( 'apply' ) ); ?>"><?php echo esc_html( $cta_secondary_text ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</section>
	<?php endif; ?>

</article>
