<?php
/**
 * Email Styles — inline CSS for WooCommerce emails.
 * Email clients require inline styles. WooCommerce inlines these automatically
 * via Emogrifier when sending.
 *
 * @package OzTheme
 * @see     https://woocommerce.github.io/code-reference/files/woocommerce-templates-emails-email-styles.html
 */

defined( 'ABSPATH' ) || exit;
?>

body {
	background-color: #F5F4F0;
	font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
	font-size: 15px;
	line-height: 1.6;
	color: #555555;
	margin: 0;
	padding: 0;
}

h1, h2, h3 {
	font-family: Georgia, serif;
	color: #1A1A1A;
	font-weight: 400;
}

h2 {
	font-size: 22px;
	margin: 0 0 16px;
}

h3 {
	font-size: 18px;
	margin: 24px 0 8px;
}

a {
	color: #135350;
	text-decoration: underline;
}

table {
	width: 100%;
	border-collapse: collapse;
}

/* Order table */
.td {
	padding: 12px;
	border-bottom: 1px solid #E5E5E3;
	text-align: left;
	vertical-align: middle;
	font-size: 14px;
}

th.td {
	font-weight: 600;
	color: #1A1A1A;
	background-color: #F5F4F0;
}

/* Address blocks */
address {
	font-style: normal;
	font-size: 14px;
	line-height: 1.5;
	color: #555555;
	padding: 16px;
	background: #F5F4F0;
	border-radius: 8px;
	margin: 8px 0;
}

/* Buttons in emails */
.button, .wc-forward {
	display: inline-block;
	padding: 12px 28px;
	background-color: #135350;
	color: #FFFFFF !important;
	text-decoration: none;
	border-radius: 8px;
	font-size: 14px;
	font-weight: 600;
}

/* Order totals */
tfoot .td {
	border-top: 2px solid #E5E5E3;
	font-weight: 600;
}

/* Product images in order emails */
.im img {
	border-radius: 6px;
}

/* WC info boxes */
.wc-item-meta {
	list-style: none;
	padding: 0;
	margin: 4px 0 0;
	font-size: 13px;
	color: #888888;
}

.wc-item-meta li {
	margin-bottom: 2px;
}
