<?php
defined( 'ABSPATH' ) || exit;

$heading    = ! empty( $heading )    ? $heading    : __( 'Frequently asked questions', 'owambe-connect-core' );
$subheading = ! empty( $subheading ) ? $subheading : '';
$items_raw  = ! empty( $items )      ? $items      : '';
$items      = [];
if ( $items_raw ) {
	$decoded = json_decode( $items_raw, true );
	if ( is_array( $decoded ) ) { $items = $decoded; }
}
if ( empty( $items ) ) { return; }
$uid = 'faq-' . wp_generate_password( 6, false, false );
?>
<section class="oc-section oc-faq">
	<div class="oc-container oc-faq__container">
		<div class="oc-section__head">
			<h2 class="oc-section__title"><?php echo esc_html( $heading ); ?></h2>
			<?php if ( $subheading ) : ?><p class="oc-section__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
		</div>
		<div class="oc-faq__list" data-oc-faq>
			<?php foreach ( $items as $i => $f ) :
				$id = $uid . '-' . $i;
			?>
				<details class="oc-faq__item">
					<summary class="oc-faq__q" id="<?php echo esc_attr( $id ); ?>-q">
						<span><?php echo esc_html( $f['question'] ?? '' ); ?></span>
						<svg class="oc-faq__chevron" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
					</summary>
					<div class="oc-faq__a"><?php echo wp_kses_post( wpautop( $f['answer'] ?? '' ) ); ?></div>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>
