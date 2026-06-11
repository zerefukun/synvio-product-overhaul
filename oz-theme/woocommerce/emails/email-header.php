<?php
/**
 * Email Header — branded header for WooCommerce transactional emails.
 *
 * @package OzTheme
 * @see     https://woocommerce.github.io/code-reference/files/woocommerce-templates-emails-email-header.html
 */

defined( 'ABSPATH' ) || exit;

$logo_url = '';
$logo_id  = get_theme_mod( 'site_logo' ) ?: get_theme_mod( 'custom_logo' );
if ( $logo_id ) {
	$logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#F5F4F0;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F5F4F0;">
<tr><td align="center" style="padding:40px 20px 0;">

<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">

	<!-- Logo bar -->
	<tr>
		<td align="center" style="padding:24px 40px;background-color:#135350;border-radius:12px 12px 0 0;">
			<?php if ( $logo_url ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="160" style="display:block;height:auto;" />
			<?php else : ?>
				<h1 style="margin:0;color:#FFFFFF;font-size:24px;font-weight:400;"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
			<?php endif; ?>
		</td>
	</tr>

	<!-- Email heading -->
	<tr>
		<td style="padding:32px 40px 0;background-color:#FFFFFF;">
			<h2 style="margin:0 0 16px;color:#1A1A1A;font-size:24px;font-weight:400;font-family:Georgia,serif;"><?php echo esc_html( $email_heading ); ?></h2>
		</td>
	</tr>

	<!-- Content start -->
	<tr>
		<td style="padding:0 40px 32px;background-color:#FFFFFF;color:#555555;font-size:15px;line-height:1.6;">
