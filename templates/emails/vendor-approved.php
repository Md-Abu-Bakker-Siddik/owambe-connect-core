<?php
/**
 * Email: vendor listing approved (sent to vendor).
 *
 * @package OwambeConnect
 * @var WP_Post $post
 * @var WP_User $user
 * @var string  $profile_url
 */
defined( 'ABSPATH' ) || exit;
?>
<h1 style="font-family:Georgia,'Times New Roman',serif;font-size:22px;color:#1F1B1A;margin:0 0 16px;font-weight:700;line-height:1.3;"><?php esc_html_e( '🎉 Your listing is live!', 'owambe-connect-core' ); ?></h1>

<p style="margin:0 0 12px;line-height:1.7;"><?php
	/* translators: %s: business name (bold) */
	printf(
		esc_html__( 'Great news — %s has been approved and is now live on Owambe Connect.', 'owambe-connect-core' ),
		'<strong>' . esc_html( $post->post_title ) . '</strong>'
	);
?></p>

<p style="margin:0 0 28px;line-height:1.7;"><?php esc_html_e( 'Customers can now find you, view your services, and reach out via WhatsApp or Instagram.', 'owambe-connect-core' ); ?></p>

<p style="margin:0 0 28px;">
	<a href="<?php echo esc_url( $profile_url ); ?>" style="display:inline-block;background:#6E0F2C;color:#FFFFFF;text-decoration:none;padding:13px 26px;border-radius:8px;font-weight:600;font-size:15px;letter-spacing:0.01em;"><?php esc_html_e( 'View your live profile', 'owambe-connect-core' ); ?></a>
</p>

<p style="margin:0;color:#6B6361;font-size:14px;line-height:1.65;border-top:1px solid #EFEAE2;padding-top:20px;"><?php esc_html_e( '💡 Tip: share your profile link with your audience on Instagram, WhatsApp, and Facebook to drive your first enquiries.', 'owambe-connect-core' ); ?></p>
