<?php
/**
 * Email: application received + onboarding guide.
 *
 * Sent to the vendor immediately after signup. Acts as both a confirmation
 * and a five-step checklist so the vendor knows exactly what to do next
 * to get their profile review-ready (the May-2026 feedback round called
 * out that vendors were confused about "what's next" after signup).
 *
 * @package OwambeConnect
 * @var WP_Post $post
 * @var WP_User $user
 * @var string  $site_name
 */
defined( 'ABSPATH' ) || exit;

$first_name    = $user->first_name ?: ( preg_split( '/\s+/', (string) $user->display_name )[0] ?: __( 'there', 'owambe-connect-core' ) );
$dashboard_url = oc_page_url( 'vendor-dashboard' );
$burgundy      = '#6E0F2C';
$gold          = '#C9A961';
$ink           = '#1F1B1A';
$stone         = '#6B6361';
$mist          = '#FAF7F2';
$border        = '#E4DDD2';
?>
<h1 style="font-family:Georgia,'Times New Roman',serif;font-size:24px;color:<?php echo $burgundy; ?>;margin:0 0 12px;font-weight:700;line-height:1.25;">
	<?php
	/* translators: %s: vendor first name */
	printf( esc_html__( 'Welcome to %s, %s', 'owambe-connect-core' ), esc_html( $site_name ), esc_html( $first_name ) );
	?> 👋
</h1>

<p style="margin:0 0 14px;line-height:1.7;font-size:15px;color:<?php echo $ink; ?>;">
	<?php
	/* translators: %s: business name */
	printf(
		esc_html__( 'Your application for %s has been received. Our team reviews new vendors within 2–3 working days — but the more complete your profile is, the faster (and easier) the review.', 'owambe-connect-core' ),
		'<strong>' . esc_html( $post->post_title ) . '</strong>'
	);
	?>
</p>

<p style="margin:0 0 22px;line-height:1.7;font-size:15px;color:<?php echo $ink; ?>;">
	<?php esc_html_e( 'Here are the 5 quick steps to get your listing review-ready:', 'owambe-connect-core' ); ?>
</p>

<!-- 5-step onboarding checklist — each step in its own card-like row -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 28px;border-collapse:collapse;">

	<tr>
		<td style="padding:14px 16px;background:<?php echo $mist; ?>;border:1px solid <?php echo $border; ?>;border-radius:8px;">
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
				<tr>
					<td width="40" valign="top" style="padding-right:14px;">
						<div style="width:32px;height:32px;background:<?php echo $burgundy; ?>;color:#fff;border-radius:50%;text-align:center;line-height:32px;font-weight:700;font-family:Georgia, serif;font-size:15px;">1</div>
					</td>
					<td valign="top">
						<strong style="color:<?php echo $ink; ?>;font-size:15px;display:block;margin-bottom:4px;"><?php esc_html_e( 'Verify your email', 'owambe-connect-core' ); ?></strong>
						<span style="color:<?php echo $stone; ?>;font-size:13.5px;line-height:1.55;"><?php esc_html_e( 'Check your inbox for a separate verification email. Click the link inside — your listing can\'t go live until you do.', 'owambe-connect-core' ); ?></span>
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
						<strong style="color:<?php echo $ink; ?>;font-size:15px;display:block;margin-bottom:4px;"><?php esc_html_e( 'Add your business basics', 'owambe-connect-core' ); ?></strong>
						<span style="color:<?php echo $stone; ?>;font-size:13.5px;line-height:1.55;"><?php esc_html_e( 'Country + cities you cover, cultural specialties, whether you\'re a registered business, an 80+ character "About" bio, and the services you offer.', 'owambe-connect-core' ); ?></span>
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
						<div style="width:32px;height:32px;background:<?php echo $burgundy; ?>;color:#fff;border-radius:50%;text-align:center;line-height:32px;font-weight:700;font-family:Georgia, serif;font-size:15px;">3</div>
					</td>
					<td valign="top">
						<strong style="color:<?php echo $ink; ?>;font-size:15px;display:block;margin-bottom:4px;"><?php esc_html_e( 'Tick the right vendor tags', 'owambe-connect-core' ); ?></strong>
						<span style="color:<?php echo $stone; ?>;font-size:13.5px;line-height:1.55;"><?php esc_html_e( 'In the tag picker, expand the groups that match what you do (Food & drinks, Music, Photo & video, etc.) and tick every applicable tag. These are how clients find you.', 'owambe-connect-core' ); ?></span>
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
						<div style="width:32px;height:32px;background:<?php echo $burgundy; ?>;color:#fff;border-radius:50%;text-align:center;line-height:32px;font-weight:700;font-family:Georgia, serif;font-size:15px;">4</div>
					</td>
					<td valign="top">
						<strong style="color:<?php echo $ink; ?>;font-size:15px;display:block;margin-bottom:4px;"><?php esc_html_e( 'Upload your photos', 'owambe-connect-core' ); ?></strong>
						<span style="color:<?php echo $stone; ?>;font-size:13.5px;line-height:1.55;"><?php esc_html_e( 'A square logo (400×400), a wide banner (1200×400), and at least 3 portfolio photos. Pick one of the gallery photos as your "display" — that\'s the one shown on directory cards.', 'owambe-connect-core' ); ?></span>
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
						<div style="width:32px;height:32px;background:<?php echo $gold; ?>;color:<?php echo $ink; ?>;border-radius:50%;text-align:center;line-height:32px;font-weight:700;font-family:Georgia, serif;font-size:15px;">5</div>
					</td>
					<td valign="top">
						<strong style="color:<?php echo $ink; ?>;font-size:15px;display:block;margin-bottom:4px;"><?php esc_html_e( 'Hit "Submit for review"', 'owambe-connect-core' ); ?></strong>
						<span style="color:<?php echo $stone; ?>;font-size:13.5px;line-height:1.55;"><?php esc_html_e( 'Once your profile shows 100%, the "Submit for review" button enables. Click it and we\'ll take it from there — you\'ll get another email when your listing goes live.', 'owambe-connect-core' ); ?></span>
					</td>
				</tr>
			</table>
		</td>
	</tr>

</table>

<!-- Primary CTA -->
<p style="margin:0 0 20px;text-align:center;">
	<a href="<?php echo esc_url( $dashboard_url ); ?>" style="display:inline-block;background:<?php echo $burgundy; ?>;color:#FFFFFF;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:15px;letter-spacing:0.01em;">
		<?php esc_html_e( 'Open my vendor dashboard', 'owambe-connect-core' ); ?>
	</a>
</p>

<p style="margin:0 0 6px;line-height:1.7;font-size:13.5px;color:<?php echo $stone; ?>;text-align:center;">
	<?php esc_html_e( 'Bookmark it — that\'s where you manage everything about your listing.', 'owambe-connect-core' ); ?>
</p>

<hr style="border:0;border-top:1px solid <?php echo $border; ?>;margin:28px 0 16px;">

<p style="margin:0;line-height:1.7;font-size:13px;color:<?php echo $stone; ?>;">
	<strong style="color:<?php echo $ink; ?>;"><?php esc_html_e( 'Stuck or have a question?', 'owambe-connect-core' ); ?></strong><br>
	<?php
	/* translators: %s: contact support link */
	printf(
		esc_html__( 'Open the Account tab of your dashboard and use the "Contact support" form — we reply within 24 hours. Or just reply to this email.', 'owambe-connect-core' )
	);
	?>
</p>
