<?php
/**
 * My Account page — dashboard with navigation.
 *
 * @package OzTheme
 * @see     https://woocommerce.github.io/code-reference/files/woocommerce-templates-myaccount-my-account.html
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="oz-account oz-container">

	<div class="oz-account__columns">

		<nav class="oz-account__nav">
			<?php do_action( 'woocommerce_account_navigation' ); ?>
		</nav>

		<div class="oz-account__content">
			<?php do_action( 'woocommerce_account_content' ); ?>
		</div>

	</div>

</div>
