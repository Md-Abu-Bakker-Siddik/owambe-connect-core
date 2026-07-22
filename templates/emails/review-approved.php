<?php
/**
 * Email: review approved (sent to the vendor).
 *
 * Fired when an admin approves a client review of the vendor's listing —
 * a "good news" moment, so it leads with the star row and the review text
 * itself, then nudges the vendor to see it live on their public profile.
 *
 * @package OwambeConnect
 * @var string $business_name
 * @var string $reviewer_name
 * @var int    $rating
 * @var string $review_text
 * @var string $profile_url
 * @var string $dashboard_url
 */
defined( 'ABSPATH' ) || exit;

$business_name = (string) ( $business_name ?? '' );
$reviewer_name = trim( (string) ( $reviewer_name ?? '' ) ) ?: __( 'A client', 'owambe-connect-core' );
$rating        = max( 0, min( 5, (int) ( $rating ?? 0 ) ) );
$review_text   = trim( (string) ( $review_text ?? '' ) );
$profile_url   = (string) ( $profile_url ?? '' );
$dashboard_url = (string) ( $dashboard_url ?? '' );
$stars_row     = str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );
$burgundy      = '#6E0F2C';
$gold          = '#C9A961';
$ink           = '#1F1B1A';
$stone         = '#6B6361';
$mist          = '#FAF7F2';
$border        = '#E4DDD2';
?>
<h1 style="font-family:Georgia,'Times New Roman',serif;font-size:24px;color:<?php echo $burgundy; ?>;margin:0 0 12px;font-weight:700;line-height:1.25;">
	<?php esc_html_e( 'You have a new review!', 'owambe-connect-core' ); ?> ⭐
</h1>

<p style="margin:0 0 16px;line-height:1.7;font-size:15px;color:<?php echo $ink; ?>;">
	<?php
	/* translators: 1: reviewer name (bold), 2: star rating (1-5), 3: business name (bold) */
	printf(
		esc_html__( '%1$s left a %2$s-star review on %3$s.', 'owambe-connect-core' ),
		'<strong>' . esc_html( $reviewer_name ) . '</strong>',
		esc_html( number_format_i18n( $rating ) ),
		'<strong>' . esc_html( $business_name ) . '</strong>'
	);
	?>
</p>

<!-- Star row -->
<p style="margin:0 0 18px;line-height:1;">
	<span style="color:<?php echo $gold; ?>;font-size:24px;letter-spacing:3px;"><?php echo esc_html( $stars_row ); ?></span>
</p>

<?php if ( $review_text ) : ?>
<!-- Review text callout -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 28px;border-collapse:collapse;">
	<tr>
		<td style="padding:16px 18px;background:<?php echo $mist; ?>;border:1px solid <?php echo $border; ?>;border-left:4px solid <?php echo $gold; ?>;border-radius:8px;">
			<span style="color:<?php echo $ink; ?>;font-size:15px;line-height:1.7;font-style:italic;">&ldquo;<?php echo nl2br( esc_html( $review_text ) ); ?>&rdquo;</span>
		</td>
	</tr>
</table>
<?php endif; ?>

<!-- Primary CTA -->
<p style="margin:0 0 20px;text-align:center;">
	<a href="<?php echo esc_url( $profile_url ); ?>" style="display:inline-block;background:<?php echo $burgundy; ?>;color:#FFFFFF;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:15px;letter-spacing:0.01em;">
		<?php esc_html_e( 'View it on your profile', 'owambe-connect-core' ); ?>
	</a>
</p>

<p style="margin:0;line-height:1.7;font-size:13.5px;color:<?php echo $stone; ?>;text-align:center;">
	<a href="<?php echo esc_url( $dashboard_url ); ?>" style="color:<?php echo $burgundy; ?>;text-decoration:underline;"><?php esc_html_e( 'See all your reviews in your dashboard', 'owambe-connect-core' ); ?></a>
</p>
