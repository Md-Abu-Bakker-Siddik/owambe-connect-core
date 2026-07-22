<?php
defined( 'ABSPATH' ) || exit;

$heading    = ! empty( $heading )    ? $heading    : __( 'How it works', 'owambe-connect-core' );
$subheading = ! empty( $subheading ) ? $subheading : '';
$items_raw  = ! empty( $items )      ? $items      : '';
$items      = [];
if ( $items_raw ) {
	$decoded = json_decode( $items_raw, true );
	if ( is_array( $decoded ) ) { $items = $decoded; }
}
// Premium SVG icon set (monoline, currentColor) — replaces the emoji defaults.
// Step labels here mirror the section's three-stage flow on the home page.
$svg_icons = [
	'search'   => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
	'message'  => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.4 8.4 0 01-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.4 8.4 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>',
	'sparkle'  => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.4 5.6L20 10l-5.6 2.4L12 18l-2.4-5.6L4 10l5.6-2.4L12 2z"/><path d="M19 18l.6 1.6L21 20l-1.4.4L19 22l-.6-1.6L17 20l1.4-.4L19 18z"/></svg>',
];

if ( empty( $items ) ) {
	$items = [
		[ 'icon_svg' => $svg_icons['search'],  'title' => __( 'Browse vendors',       'owambe-connect-core' ), 'description' => __( 'Search by category, location or budget.',         'owambe-connect-core' ) ],
		[ 'icon_svg' => $svg_icons['message'], 'title' => __( 'Reach out directly',   'owambe-connect-core' ), 'description' => __( 'Message vendors on WhatsApp or Instagram.',      'owambe-connect-core' ) ],
		[ 'icon_svg' => $svg_icons['sparkle'], 'title' => __( 'Book with confidence', 'owambe-connect-core' ), 'description' => __( 'Lock in your vendor and celebrate.',             'owambe-connect-core' ) ],
	];
}
?>
<section class="oc-section oc-hiw oc-section--how-it-works">
	<div class="oc-container">
		<div class="oc-section__head">
			<h2 class="oc-section__title"><?php echo esc_html( $heading ); ?></h2>
			<?php if ( $subheading ) : ?><p class="oc-section__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
		</div>
		<ol class="oc-hiw__list oc-how-it-works__steps">
			<?php foreach ( $items as $i => $step ) :
				$svg = $step['icon_svg'] ?? '';
				$legacy_emoji = $step['icon'] ?? '';
			?>
				<li class="oc-hiw__item">
					<span class="oc-hiw__num"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
					<?php if ( $svg ) : ?>
						<span class="oc-hiw__icon" aria-hidden="true"><?php echo $svg; // phpcs:ignore — trusted inline SVG ?></span>
					<?php elseif ( $legacy_emoji ) : ?>
						<span class="oc-hiw__icon" aria-hidden="true"><?php echo esc_html( $legacy_emoji ); ?></span>
					<?php endif; ?>
					<h3 class="oc-hiw__title"><?php echo esc_html( $step['title'] ?? '' ); ?></h3>
					<p class="oc-hiw__desc"><?php echo esc_html( $step['description'] ?? '' ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
