<?php
defined( 'ABSPATH' ) || exit;

$heading    = ! empty( $heading )    ? $heading    : '';
$subheading = ! empty( $subheading ) ? $subheading : '';
$items_raw  = ! empty( $items )      ? $items      : '';
$items      = [];
if ( $items_raw ) {
	$decoded = json_decode( $items_raw, true );
	if ( is_array( $decoded ) ) { $items = $decoded; }
}
if ( empty( $items ) ) { return; }
?>
<section class="oc-section oc-stats">
	<div class="oc-container">
		<?php if ( $heading || $subheading ) : ?>
			<div class="oc-section__head">
				<?php if ( $heading ) : ?><h2 class="oc-section__title"><?php echo esc_html( $heading ); ?></h2><?php endif; ?>
				<?php if ( $subheading ) : ?><p class="oc-section__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="oc-stats__grid">
			<?php foreach ( $items as $stat ) : ?>
				<div class="oc-stat">
					<span class="oc-stat__value"><?php echo esc_html( $stat['value'] ?? '' ); ?></span>
					<span class="oc-stat__label"><?php echo esc_html( $stat['label'] ?? '' ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
