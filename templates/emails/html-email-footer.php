<?php
/**
 * Shared email footer — output after every transactional email body.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;
?>
		</td>
	</tr>

</table>

<?php
// Same domain-name guard as in the header — never render a bare ".com" in
// the footer line, because Gmail/Outlook will auto-linkify it in their
// own colour. Same filter hook so a site can override centrally.
$oc_site_name_footer  = (string) ( get_bloginfo( 'name' ) ?: 'Owambe Connect' );
$oc_brand_name_footer = preg_match( '/\.(com|co\.uk|org|net|io|app)$/i', trim( $oc_site_name_footer ) )
	? __( 'Owambe Connect', 'owambe-connect-core' )
	: $oc_site_name_footer;
$oc_brand_name_footer = apply_filters( 'oc_email_brand_name', $oc_brand_name_footer, $oc_site_name_footer );
?>
<p style="text-align:center;color:#9B9290;font-size:12px;margin:20px 0 0;line-height:1.7;">
	&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="oc-brand-burgundy" style="color:#6E0F2C !important;text-decoration:none !important;font-weight:600;">
		<span style="color:#6E0F2C !important;font-weight:600;"><?php echo esc_html( $oc_brand_name_footer ); ?></span>
	</a>
	&nbsp;&bull;&nbsp;
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="oc-brand-burgundy" style="color:#6E0F2C !important;text-decoration:none !important;">
		<span style="color:#6E0F2C !important;"><?php esc_html_e( 'Visit site', 'owambe-connect-core' ); ?></span>
	</a>
	&nbsp;&bull;&nbsp;
	<a href="<?php echo esc_url( oc_page_url( 'contact' ) ); ?>" class="oc-brand-burgundy" style="color:#6E0F2C !important;text-decoration:none !important;">
		<span style="color:#6E0F2C !important;"><?php esc_html_e( 'Contact us', 'owambe-connect-core' ); ?></span>
	</a>
</p>

</td></tr>
</table>

</body>
</html>
