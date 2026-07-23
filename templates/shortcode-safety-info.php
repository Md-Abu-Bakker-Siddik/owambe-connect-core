<?php
/**
 * Website Safety Information — general safety guidance for clients & visitors.
 *
 * A framework/placeholder: the copy is intentionally generic and controllable
 * three ways so a site can tailor it without forking this template:
 *
 *   1. Shortcode attributes — [oc_safety_info heading="…" subheading="…"]
 *   2. Plugin option (oc_settings → `safety_intro`) — an admin-editable notice
 *      rendered above the tips. Configure it in Owambe Connect → Settings.
 *   3. The `oc_safety_items` filter — replace or extend the default tip cards.
 *      Each item is [ 'icon' => dashicon-slug, 'title' => '…', 'text' => '…' ].
 *
 * Dropped onto the seeded /safety/ page (see OC_Activator::seed_pages()) or
 * placed anywhere with the [oc_safety_info] shortcode.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$heading    = ! empty( $heading )    ? $heading    : __( 'Website Safety Information', 'owambe-connect-core' );
$subheading = ! empty( $subheading ) ? $subheading : __( 'A few simple steps to help you plan safely and book with confidence.', 'owambe-connect-core' );

// Admin-editable intro/notice (oc_settings → safety_intro). Basic HTML allowed.
$intro = function_exists( 'oc_get_setting' ) ? (string) oc_get_setting( 'safety_intro', '' ) : '';

// Where to report a problem — reuse the contact page.
$report_url = function_exists( 'oc_page_url' ) ? oc_page_url( 'contact' ) : home_url( '/contact/' );

/**
 * Default safety tips. Filter to override or extend without editing this file.
 *
 * @param array[] $items Each: [ 'icon' => string, 'title' => string, 'text' => string ].
 */
$items = apply_filters( 'oc_safety_items', [
	[
		'icon'  => 'search',
		'title' => __( 'Check before you book', 'owambe-connect-core' ),
		'text'  => __( 'Read the vendor’s profile, reviews and photos. Ask for references or recent examples of their work before you commit.', 'owambe-connect-core' ),
	],
	[
		'icon'  => 'media-text',
		'title' => __( 'Get it in writing', 'owambe-connect-core' ),
		'text'  => __( 'Agree the price, date, deliverables and cancellation terms in writing before paying anything. A clear quote or contract protects you both.', 'owambe-connect-core' ),
	],
	[
		'icon'  => 'money-alt',
		'title' => __( 'Be careful with payments', 'owambe-connect-core' ),
		'text'  => __( 'Avoid large upfront deposits, cash-only demands or requests to pay a personal account. Owambe Connect never asks you to pay us to contact a vendor.', 'owambe-connect-core' ),
	],
	[
		'icon'  => 'lock',
		'title' => __( 'Protect your information', 'owambe-connect-core' ),
		'text'  => __( 'Share only the details a vendor needs for your event. Never send passwords, and be wary of anyone pressuring you to move off the platform immediately.', 'owambe-connect-core' ),
	],
	[
		'icon'  => 'flag',
		'title' => __( 'Report anything suspicious', 'owambe-connect-core' ),
		'text'  => __( 'If a listing or message feels off, let us know. Reporting helps us keep the marketplace safe for everyone.', 'owambe-connect-core' ),
	],
	[
		'icon'  => 'heart',
		'title' => __( 'Trust your instincts', 'owambe-connect-core' ),
		'text'  => __( 'If a deal looks too good to be true, take your time. A trustworthy vendor will be happy to answer your questions.', 'owambe-connect-core' ),
	],
] );
?>
<section class="oc-section oc-safety">
	<div class="oc-container">
		<header class="oc-safety__head">
			<h1 class="oc-safety__title"><?php echo esc_html( $heading ); ?></h1>
			<?php if ( $subheading ) : ?><p class="oc-safety__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
		</header>

		<?php if ( '' !== trim( $intro ) ) : ?>
			<div class="oc-safety__intro"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div>
		<?php endif; ?>

		<?php if ( ! empty( $items ) ) : ?>
			<div class="oc-safety__grid">
				<?php foreach ( $items as $item ) : ?>
					<?php if ( empty( $item['title'] ) ) { continue; } ?>
					<div class="oc-safety__card">
						<?php if ( ! empty( $item['icon'] ) ) : ?>
							<span class="oc-safety__icon dashicons dashicons-<?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
						<?php endif; ?>
						<h2 class="oc-safety__card-title"><?php echo esc_html( $item['title'] ); ?></h2>
						<?php if ( ! empty( $item['text'] ) ) : ?>
							<p class="oc-safety__card-text"><?php echo esc_html( $item['text'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="oc-safety__foot">
			<p><?php esc_html_e( 'Seen something that doesn’t look right?', 'owambe-connect-core' ); ?>
				<a class="oc-safety__report" href="<?php echo esc_url( $report_url ); ?>"><?php esc_html_e( 'Report it to our team', 'owambe-connect-core' ); ?> →</a>
			</p>
		</div>
	</div>
</section>

<style>
/* Self-contained placeholder styling — safe to move into the theme CSS later. */
.oc-safety__head { text-align: center; max-width: 640px; margin: 0 auto 26px; }
.oc-safety__title { font-family: Georgia, serif; color: var(--oc-burgundy, #6E0F2C); font-size: clamp(1.6rem, 4vw, 2.2rem); margin: 0 0 8px; }
.oc-safety__lead { color: var(--oc-stone, #6B6361); font-size: 1.05rem; margin: 0; }
.oc-safety__intro { max-width: 760px; margin: 0 auto 26px; padding: 16px 20px; background: var(--oc-cream, #FAF7F2); border: 1px solid var(--oc-border, #E4DDD2); border-left: 4px solid var(--oc-gold, #C9A961); border-radius: 10px; color: #1F1B1A; }
.oc-safety__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
.oc-safety__card { background: #fff; border: 1px solid var(--oc-border, #E4DDD2); border-radius: 14px; padding: 22px 22px 20px; transition: border-color .15s ease, box-shadow .15s ease; }
.oc-safety__card:hover { border-color: var(--oc-gold, #C9A961); box-shadow: 0 6px 18px rgba(46, 16, 24, .07); }
.oc-safety__icon { color: var(--oc-gold, #C9A961); font-size: 26px; width: 26px; height: 26px; }
.oc-safety__card-title { font-size: 1.05rem; color: #1F1B1A; margin: 10px 0 6px; }
.oc-safety__card-text { color: var(--oc-stone, #6B6361); font-size: .95rem; line-height: 1.55; margin: 0; }
.oc-safety__foot { text-align: center; margin: 28px 0 0; color: var(--oc-stone, #6B6361); }
.oc-safety__report { color: var(--oc-burgundy, #6E0F2C); font-weight: 600; text-decoration: none; }
.oc-safety__report:hover { text-decoration: underline; }
</style>
