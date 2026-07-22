<?php
/**
 * Shared email header — output before every transactional email body.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;
?>
<?php
// Brand text shown in the email header bar. The WP site name is set to the
// literal domain "owambeconnect.com" — Gmail/Outlook auto-linkify any text
// that matches a domain pattern and apply their own blue/pink link colour,
// which beat every inline-anchor colour override we threw at it.
//
// Fix in two layers:
//   1. If the site name looks like a bare domain, override the displayed
//      brand text to "Owambe Connect" (configurable via filter). This kills
//      the linkify trigger at the source.
//   2. Wrap the visible label in a <span> with an inline colour INSIDE the
//      anchor. Even if a mail client forces an outer-anchor colour, an
//      inner-span colour wins because span isn't an anchor.
$oc_site_name  = (string) ( get_bloginfo( 'name' ) ?: 'Owambe Connect' );
$oc_brand_name = preg_match( '/\.(com|co\.uk|org|net|io|app)$/i', trim( $oc_site_name ) )
	? __( 'Owambe Connect', 'owambe-connect-core' )
	: $oc_site_name;
$oc_brand_name = apply_filters( 'oc_email_brand_name', $oc_brand_name, $oc_site_name );
$oc_brand_link = esc_url( home_url( '/' ) );
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light only">
<meta name="supported-color-schemes" content="light only">
<title><?php echo esc_html( $oc_brand_name ); ?></title>
<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
<style type="text/css">
	/* Force Gmail / iOS Mail to keep our brand link colours instead of
	   applying their default link blue/purple. */
	a.oc-brand-gold,  a.oc-brand-gold:link,  a.oc-brand-gold:visited,  a.oc-brand-gold:hover  { color:#C9A961 !important; text-decoration:none !important; }
	a.oc-brand-burgundy, a.oc-brand-burgundy:link, a.oc-brand-burgundy:visited, a.oc-brand-burgundy:hover { color:#6E0F2C !important; text-decoration:none !important; }
	/* Gmail's auto-detection of phone/date/address strings — opt out. */
	u + .body .oc-no-linkify, .oc-no-linkify { color:inherit !important; text-decoration:none !important; pointer-events:none !important; }
</style>
</head>
<body class="body" style="margin:0;padding:0;background:#FAF7F2;font-family:'Inter',Arial,Helvetica,sans-serif;color:#1F1B1A;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#FAF7F2">
<tr><td style="padding:32px 16px;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;margin:0 auto;">

	<!-- Brand header bar -->
	<tr>
		<td style="background:#6E0F2C;border-radius:14px 14px 0 0;padding:22px 32px;">
			<p style="margin:0;font-family:Georgia,'Times New Roman',serif;font-size:22px;font-weight:700;letter-spacing:0.04em;line-height:1.2;">
				<a href="<?php echo $oc_brand_link; ?>" class="oc-brand-gold" style="color:#C9A961 !important;text-decoration:none !important;font-family:Georgia,'Times New Roman',serif;font-weight:700;">
					<span style="color:#C9A961 !important;font-family:Georgia,'Times New Roman',serif;font-weight:700;"><?php echo esc_html( $oc_brand_name ); ?></span>
				</a>
			</p>
			<p style="margin:6px 0 0;font-family:'Inter',Arial,sans-serif;color:rgba(255,255,255,0.70);font-size:11px;letter-spacing:0.24em;text-transform:uppercase;"><?php esc_html_e( 'Connecting Events. Celebrating Culture.', 'owambe-connect-core' ); ?></p>
		</td>
	</tr>

	<!-- White body card — opened here, closed in html-email-footer.php -->
	<tr>
		<td style="background:#FFFFFF;border:1px solid #E4DDD2;border-top:0;border-radius:0 0 14px 14px;padding:32px 32px 28px;">
