<?php
/**
 * Cart page with items — OzTheme design system.
 * Two-column: items list (left) + totals summary (right, sticky).
 *
 * @package OzTheme
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );
?>

<div class="oz-cp">
	<div class="oz-cp__header">
		<h1 class="oz-cp__title">Winkelwagen</h1>
		<?php
		$cart_count = WC()->cart->get_cart_contents_count();
		if ( $cart_count > 0 ) : ?>
			<div class="oz-cp__subtitle"><?php echo esc_html( $cart_count ); ?> <?php echo 1 === $cart_count ? 'artikel' : 'artikelen'; ?></div>
		<?php endif; ?>
	</div>

	<div class="oz-cp__grid">

		<div class="oz-cp__main">
			<form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
				<?php do_action( 'woocommerce_before_cart_table' ); ?>

				<div class="oz-cp-items">
					<?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
						$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
						$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

						if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
							continue;
						}

						$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
						$thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'thumbnail' ), $cart_item, $cart_item_key );
						?>
						<div class="oz-cp-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">

							<div class="oz-cp-item__image">
								<?php if ( $product_permalink ) : ?>
									<a href="<?php echo esc_url( $product_permalink ); ?>"><?php echo $thumbnail; // phpcs:ignore ?></a>
								<?php else : ?>
									<?php echo $thumbnail; // phpcs:ignore ?>
								<?php endif; ?>
							</div>

							<div class="oz-cp-item__body">
								<div class="oz-cp-item__name">
									<?php if ( $product_permalink ) : ?>
										<a href="<?php echo esc_url( $product_permalink ); ?>"><?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ); ?></a>
									<?php else : ?>
										<?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ); ?>
									<?php endif; ?>
								</div>

								<div class="oz-cp-item__meta">
									<?php
									do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );
									echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore
									?>
								</div>

								<div class="oz-cp-item__price">
									<?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); // phpcs:ignore ?>
								</div>
							</div>

							<div class="oz-cp-item__actions">
								<a href="<?php echo esc_url( wc_get_cart_remove_url( $cart_item_key ) ); ?>" class="oz-cp-item__remove" aria-label="<?php echo esc_attr( sprintf( __( 'Verwijder %s uit winkelwagen', 'oz-theme' ), wp_strip_all_tags( $_product->get_name() ) ) ); ?>" data-product_id="<?php echo esc_attr( $product_id ); ?>">&times;</a>

								<div class="oz-cp-qty">
									<?php
									if ( $_product->is_sold_individually() ) {
										$min_quantity = 1;
										$max_quantity = 1;
									} else {
										$min_quantity = 0;
										$max_quantity = $_product->get_max_purchase_quantity();
									}

									echo apply_filters( // phpcs:ignore
										'woocommerce_cart_item_quantity',
										woocommerce_quantity_input(
											[
												'input_name'   => "cart[{$cart_item_key}][qty]",
												'input_value'  => $cart_item['quantity'],
												'max_value'    => $max_quantity,
												'min_value'    => $min_quantity,
												'product_name' => $_product->get_name(),
											],
											$_product,
											false
										),
										$cart_item_key,
										$cart_item
									);
									?>
								</div>

								<div class="oz-cp-item__subtotal">
									<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="oz-cp-actions">
					<div class="oz-cp-coupon">
						<?php if ( wc_coupons_enabled() ) : ?>
							<input type="text" name="coupon_code" class="oz-input" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Kortingscode', 'oz-theme' ); ?>" />
							<button type="submit" class="oz-btn oz-btn--outline oz-btn--sm" name="apply_coupon" value="<?php esc_attr_e( 'Toepassen', 'oz-theme' ); ?>"><?php esc_html_e( 'Toepassen', 'oz-theme' ); ?></button>
						<?php endif; ?>
					</div>

					<button type="submit" class="oz-btn oz-btn--outline oz-btn--sm" name="update_cart" value="1"><?php esc_html_e( 'Winkelwagen bijwerken', 'oz-theme' ); ?></button>

					<?php do_action( 'woocommerce_cart_actions' ); ?>
					<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
				</div>

				<?php do_action( 'woocommerce_after_cart_table' ); ?>
			</form>

			<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>
		</div>

		<aside class="oz-cp__aside">
			<?php woocommerce_cart_totals(); ?>
		</aside>

	</div>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
