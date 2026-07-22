<?php
/**
 * Email: client welcome (sent to an event host after Google sign-in).
 *
 * Short and warm — the client just signed in with Google, so there is
 * nothing to verify or set up. The three step cards show them how to get
 * value immediately: browse, contact vendors directly, save favourites.
 *
 * @package OwambeConnect
 * @var string $first_name
 * @var string $dashboard_url
 * @var string $vendors_url
 */
defined( 'ABSPATH' ) || exit;

$first_name    = trim( (string) ( $first_name ?? '' ) ) ?: __( 'there', 'owambe-connect-core' );
$dashboard_url = (string) ( $dashboard_url ?? '' );
$vendors_url   = (string) ( $vendors_url ?? '' );
$burgundy      = '#6E0F2C';
$gold          = '#C9A961';
$ink           = '#1F1B1A';
$stone         = '#6B6361';
$mist          = '#FAF7F2';
$border        = '#E4DDD2';
?>
<h1 style="font-family:Georgia,'Times New Roman',serif;font-size:24px;color:<?php echo $burgundy; ?>;margin:0 0 12px;font-weight:700;line-height:1.25;">
	<?php
	/* translators: %s: client first name */
	printf( esc_html__( 'Hi %s, welcome to Owambe Connect', 'owambe-connect-core' ), esc_html( $first_name ) );
	?> 👋
</h1>

<p style="margin:0 0 22px;line-height:1.7;font-size:15px;color:<?php echo $ink; ?>;">
	<?php esc_html_e( 'You\'re all set. Owambe Connect helps you find trusted event vendors — caterers, DJs, photographers, decorators and more — and contact them directly. No middleman, no fees, no waiting.', 'owambe-connect-core' ); ?>
</p>

<!-- 3-step getting-started guide — each step in its own card-like row -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 28px;border-collapse:collapse;">

	<tr>
		<td style="padding:14px 16px;background:<?php echo $mist; ?>;border:1px solid <?php echo $border; ?>;border-radius:8px;">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
				<tr>
					<td width="40" valign="top" style="padding-right:14px;">
						<div style="width:32px;height:32px;background:<?php echo $burgundy; ?>;color:#fff;border-radius:50%;text-align:center;line-height:32px;font-weight:700;font-family:Georgia, serif;font-size:15px;">1</div>
					</td>
					<td valign="top">
						<strong style="color:<?php echo $ink; ?>;font-size:15px;display:block;margin-bottom:4px;"><?php esc_html_e( 'Browse vendors', 'owambe-connect-core' ); ?></strong>
						<span style="color:<?php echo $stone; ?>;font-size:13.5px;line-height:1.55;"><?php esc_html_e( 'Filter by category and location to find vendors that fit your event — every listing shows their services, photos, and reviews.', 'owambe-connect-core' ); ?></span>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<tr><td style="height:8px;line-height:8px;font-size:1px;">&nbsp;</td></tr>

	<tr>
		<td style="padding:14px 16px;background:<?php echo $mist; ?>;border:1px solid <?php echo $border; ?>;border-radius:8px;">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
				<tr>
					<td width="40" valign="top" style="padding-right:14px;">
						<div style="width:32px;height:32px;background:<?php echo $burgundy; ?>;color:#fff;border-radius:50%;text-align:center;line-height:32px;font-weight:700;font-family:Georgia, serif;font-size:15px;">2</div>
					</td>
					<td valign="top">
						<strong style="color:<?php echo $ink; ?>;font-size:15px;display:block;margin-bottom:4px;"><?php esc_html_e( 'Contact them directly', 'owambe-connect-core' ); ?></strong>
						<span style="color:<?php echo $stone; ?>;font-size:13.5px;line-height:1.55;"><?php esc_html_e( 'Message any vendor on WhatsApp straight from their profile — you deal with them directly, on your terms.', 'owambe-connect-core' ); ?></span>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<tr><td style="height:8px;line-height:8px;font-size:1px;">&nbsp;</td></tr>

	<tr>
		<td style="padding:14px 16px;background:<?php echo $mist; ?>;border:1px solid <?php echo $border; ?>;border-radius:8px;">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
				<tr>
					<td width="40" valign="top" style="padding-right:14px;">
						<div style="width:32px;height:32px;background:<?php echo $gold; ?>;color:<?php echo $ink; ?>;border-radius:50%;text-align:center;line-height:32px;font-weight:700;font-family:Georgia, serif;font-size:15px;">3</div>
					</td>
					<td valign="top">
						<strong style="color:<?php echo $ink; ?>;font-size:15px;display:block;margin-bottom:4px;"><?php esc_html_e( 'Save favourites & leave reviews', 'owambe-connect-core' ); ?></strong>
						<span style="color:<?php echo $stone; ?>;font-size:13.5px;line-height:1.55;"><?php esc_html_e( 'Tap the heart on any vendor to save them to your dashboard, and leave a review after your event to help other hosts.', 'owambe-connect-core' ); ?></span>
					</td>
				</tr>
			</table>
		</td>
	</tr>

</table>

<!-- Primary CTA -->
<p style="margin:0 0 20px;text-align:center;">
	<a href="<?php echo esc_url( $vendors_url ); ?>" style="display:inline-block;background:<?php echo $burgundy; ?>;color:#FFFFFF;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:15px;letter-spacing:0.01em;">
		<?php esc_html_e( 'Browse vendors', 'owambe-connect-core' ); ?>
	</a>
</p>

<p style="margin:0;line-height:1.7;font-size:13.5px;color:<?php echo $stone; ?>;text-align:center;">
	<a href="<?php echo esc_url( $dashboard_url ); ?>" style="color:<?php echo $burgundy; ?>;text-decoration:underline;"><?php esc_html_e( 'Go to your dashboard', 'owambe-connect-core' ); ?></a>
</p>
