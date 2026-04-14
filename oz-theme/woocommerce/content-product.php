<?php
/**
 * Product card in shop/category grid.
 * Clean card: image, title, price. No rating stars or add-to-cart button
 * in the grid — customers click through to the PDP.
 *
 * @package OzTheme
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}
?>

<li <?php wc_product_class( 'oz-product-card', $product ); ?>>
	<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="oz-product-card__link">

		<div class="oz-product-card__image">
			<?php echo $product->get_image( 'woocommerce_thumbnail', [ 'class' => 'oz-product-card__img', 'loading' => 'lazy' ] ); ?>
			<?php if ( $product->is_on_sale() ) : ?>
				<span class="oz-product-card__badge">Sale</span>
			<?php endif; ?>
		</div>

		<div class="oz-product-card__body">
			<h2 class="oz-product-card__title"><?php echo esc_html( $product->get_name() ); ?></h2>
			<div class="oz-product-card__price"><?php echo $product->get_price_html(); ?></div>
		</div>

	</a>
</li>
