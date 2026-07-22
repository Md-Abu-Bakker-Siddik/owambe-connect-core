<?php
/**
 * Email: contact form message (sent to admin).
 *
 * @package OwambeConnect
 * @var string $name
 * @var string $email
 * @var string $message
 */
defined( 'ABSPATH' ) || exit;
?>
<h1 style="font-family:Georgia,'Times New Roman',serif;font-size:22px;color:#1F1B1A;margin:0 0 16px;font-weight:700;line-height:1.3;"><?php esc_html_e( 'New contact message', 'owambe-connect-core' ); ?></h1>

<div style="background:#FAF7F2;border-left:4px solid #C9A961;padding:14px 18px;margin:0 0 20px;border-radius:4px;">
	<p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#6B6361;font-weight:600;"><?php esc_html_e( 'From', 'owambe-connect-core' ); ?></p>
	<p style="margin:0;font-size:15px;color:#1F1B1A;"><?php echo esc_html( $name ); ?> &mdash; <a href="mailto:<?php echo esc_attr( $email ); ?>" style="color:#6E0F2C;text-decoration:none;"><?php echo esc_html( $email ); ?></a></p>
</div>

<p style="margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#6B6361;font-weight:600;"><?php esc_html_e( 'Message', 'owambe-connect-core' ); ?></p>
<p style="margin:0;color:#1F1B1A;line-height:1.7;font-size:15px;"><?php echo nl2br( esc_html( $message ) ); ?></p>
