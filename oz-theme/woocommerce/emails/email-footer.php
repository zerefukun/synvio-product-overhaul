<?php
/**
 * Email Footer — branded footer for WooCommerce transactional emails.
 *
 * @package OzTheme
 * @see     https://woocommerce.github.io/code-reference/files/woocommerce-templates-emails-email-footer.html
 */

defined( 'ABSPATH' ) || exit;
?>
		</td>
	</tr>

	<!-- Footer -->
	<tr>
		<td style="padding:24px 40px;background-color:#F5F4F0;border-radius:0 0 12px 12px;text-align:center;font-size:13px;color:#888888;line-height:1.5;">
			<p style="margin:0 0 8px;">
				<strong style="color:#1A1A1A;"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong>
			</p>
			<p style="margin:0 0 8px;">
				Laan van 's-Gravenmade 42L, 2495 AJ Den Haag<br>
				<a href="mailto:info@beton-cire-webshop.nl" style="color:#135350;text-decoration:none;">info@beton-cire-webshop.nl</a>
				&middot;
				<a href="tel:0850270090" style="color:#135350;text-decoration:none;">085 - 027 00 90</a>
			</p>
			<p style="margin:0;font-size:12px;">
				KVK: 83646248 &middot; BTW: NL862945811 B01
			</p>
		</td>
	</tr>

</table>

</td></tr>

<!-- Unsubscribe / legal -->
<tr>
	<td align="center" style="padding:16px 20px 40px;font-size:12px;color:#999999;">
		<?php echo wp_kses_post( wpautop( wptexturize( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) ); ?>
	</td>
</tr>

</table>

</body>
</html>
