<?php
/**
 * Email: vendor listing needs changes (sent to vendor).
 *
 * @package OwambeConnect
 * @var WP_Post $post
 * @var WP_User $user
 * @var string  $reason
 * @var string  $dashboard_url
 */
defined( 'ABSPATH' ) || exit;
?>
<h1 style="font-family:Georgia,'Times New Roman',serif;font-size:22px;color:#1F1B1A;margin:0 0 16px;font-weight:700;line-height:1.3;"><?php esc_html_e( 'A few changes needed', 'owambe-connect-core' ); ?></h1>

<p style="margin:0 0 12px;line-height:1.7;"><?php
	/* translators: %s: business name (bold) */
	printf(
		esc_html__( 'Thanks for applying with %s. Before we can publish your listing, we need a few updates.', 'owambe-connect-core' ),
		'<strong>' . esc_html( $post->post_title ) . '</strong>'
	);
?></p>

<?php if ( $reason ) : ?>
<div style="background:#FAF7F2;border-left:4px solid #C9A961;padding:14px 18px;margin:18px 0;border-radius:4px;">
	<p style="margin:0 0 6px;font-weight:700;"><?php esc_html_e( 'What we noticed:', 'owambe-connect-core' ); ?></p>
	<p style="margin:0;line-height:1.7;"><?php echo nl2br( esc_html( $reason ) ); ?></p>
</div>
<?php endif; ?>

<p style="margin:0 0 28px;line-height:1.7;"><?php esc_html_e( 'Update your listing in your dashboard and we\'ll re-review it promptly.', 'owambe-connect-core' ); ?></p>

<p style="margin:0;">
	<a href="<?php echo esc_url( $dashboard_url ); ?>" style="display:inline-block;background:#6E0F2C;color:#FFFFFF;text-decoration:none;padding:13px 26px;border-radius:8px;font-weight:600;font-size:15px;letter-spacing:0.01em;"><?php esc_html_e( 'Update my listing', 'owambe-connect-core' ); ?></a>
</p>
