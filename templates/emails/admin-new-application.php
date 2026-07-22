<?php
/**
 * Email: new vendor application (sent to admin).
 *
 * @package OwambeConnect
 * @var WP_Post $post
 * @var string  $edit_url
 */
defined( 'ABSPATH' ) || exit;
?>
<h1 style="font-family:Georgia,'Times New Roman',serif;font-size:22px;color:#1F1B1A;margin:0 0 16px;font-weight:700;line-height:1.3;"><?php esc_html_e( 'New vendor application', 'owambe-connect-core' ); ?></h1>

<p style="margin:0 0 12px;line-height:1.7;"><?php
	/* translators: %s: business name (bold) */
	printf(
		esc_html__( '%s has submitted a vendor application and is waiting for your review.', 'owambe-connect-core' ),
		'<strong>' . esc_html( $post->post_title ) . '</strong>'
	);
?></p>

<div style="background:#FAF7F2;border-left:4px solid #C9A961;padding:14px 18px;margin:18px 0;border-radius:4px;">
	<p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#6B6361;font-weight:600;"><?php esc_html_e( 'Submitted listing', 'owambe-connect-core' ); ?></p>
	<p style="margin:0;font-family:Georgia,serif;font-size:16px;color:#1F1B1A;font-weight:700;"><?php echo esc_html( $post->post_title ); ?></p>
</div>

<p style="margin:0 0 28px;line-height:1.7;"><?php esc_html_e( 'Review the application in the admin panel and approve or reject it. The vendor will be notified by email automatically.', 'owambe-connect-core' ); ?></p>

<p style="margin:0;">
	<a href="<?php echo esc_url( $edit_url ); ?>" style="display:inline-block;background:#6E0F2C;color:#FFFFFF;text-decoration:none;padding:13px 26px;border-radius:8px;font-weight:600;font-size:15px;letter-spacing:0.01em;"><?php esc_html_e( 'Review application', 'owambe-connect-core' ); ?></a>
</p>
