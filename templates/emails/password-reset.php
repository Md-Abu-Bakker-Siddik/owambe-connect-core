<?php
/**
 * Branded password-reset email body. Wrapped automatically by the shared
 * html-email-header.php / html-email-footer.php so brand colours and the
 * burgundy header bar match every other transactional email we send.
 *
 * @var WP_User $user
 * @var string  $reset_url
 * @var string  $site_name
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$display = $user->display_name ?: $user->user_login;
?>
<h2 style="font-family:Georgia,'Times New Roman',serif;color:#6E0F2C;font-size:20px;margin:0 0 14px;">
	<?php esc_html_e( 'Reset your password', 'owambe-connect-core' ); ?>
</h2>
<p style="color:#3D3735;font-size:14px;line-height:1.55;margin:0 0 14px;">
	<?php printf(
		/* translators: %s: user's display name */
		esc_html__( 'Hi %s,', 'owambe-connect-core' ),
		esc_html( $display )
	); ?>
</p>
<p style="color:#3D3735;font-size:14px;line-height:1.55;margin:0 0 22px;">
	<?php esc_html_e( 'We received a request to reset the password on your account. Click the button below to set a new one — the link works for 24 hours.', 'owambe-connect-core' ); ?>
</p>

<p style="margin:0 0 22px;">
	<a href="<?php echo esc_url( $reset_url ); ?>" style="display:inline-block;background:#6E0F2C;color:#fff !important;text-decoration:none !important;font-family:'Inter',Arial,sans-serif;font-size:15px;font-weight:600;padding:14px 26px;border-radius:8px;">
		<?php esc_html_e( 'Set a new password', 'owambe-connect-core' ); ?>
	</a>
</p>

<p style="color:#6B6361;font-size:13px;line-height:1.55;margin:0 0 14px;">
	<?php esc_html_e( 'Or copy and paste this link into your browser:', 'owambe-connect-core' ); ?>
</p>
<p style="word-break:break-all;font-family:'Inter',Arial,sans-serif;font-size:12px;line-height:1.5;background:#FAF7F2;border:1px solid #EFEAE2;border-radius:6px;padding:10px 12px;color:#3D3735;margin:0 0 22px;">
	<?php echo esc_html( $reset_url ); ?>
</p>

<p style="color:#6B6361;font-size:13px;line-height:1.55;margin:0;border-top:1px solid #EFEAE2;padding-top:14px;">
	<?php esc_html_e( 'If you didn\'t request this, no action is needed — your password stays the same and this link will simply expire.', 'owambe-connect-core' ); ?>
</p>
