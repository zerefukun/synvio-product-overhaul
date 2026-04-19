<?php
/**
 * Empty cart page.
 * Uses OzTheme design system — no WooCommerce default classes kept.
 *
 * @package OzTheme
 */

defined( 'ABSPATH' ) || exit;

$shop_url = wc_get_page_permalink( 'shop' );
if ( ! $shop_url ) {
	$shop_url = home_url( '/producten/' );
}
?>

<div class="oz-cp">
	<div class="oz-cp__header">
		<h1 class="oz-cp__title">Winkelwagen</h1>
	</div>

	<div class="oz-cp-empty">
		<span class="oz-cp-empty__icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
				<circle cx="9" cy="21" r="1"/>
				<circle cx="20" cy="21" r="1"/>
				<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
			</svg>
		</span>
		<h2 class="oz-cp-empty__title">Je winkelmand is leeg</h2>
		<p class="oz-cp-empty__text">Voeg producten toe om te beginnen met je beton ciré of betonlook project.</p>
		<a class="oz-btn oz-btn--cta" href="<?php echo esc_url( $shop_url ); ?>">Verder winkelen</a>
	</div>
</div>

<?php do_action( 'woocommerce_cart_is_empty' ); ?>
