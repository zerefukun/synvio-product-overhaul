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
			<div class="oz-cp-summary cart_totals <?php echo ( WC()->customer->has_calculated_shipping() ) ? 'calculated_shipping' : ''; ?>">
				<h2 class="oz-cp-summary__title"><?php esc_html_e( 'Overzicht', 'oz-theme' ); ?></h2>
				<?php do_action( 'woocommerce_before_cart_totals' ); ?>

				<table cellspacing="0" class="shop_table shop_table_responsive">
					<tr class="cart-subtotal">
						<th><?php esc_html_e( 'Subtotaal', 'oz-theme' ); ?></th>
						<td data-title="<?php esc_attr_e( 'Subtotaal', 'oz-theme' ); ?>"><?php wc_cart_totals_subtotal_html(); ?></td>
					</tr>

					<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
						<tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
							<th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
							<td data-title="<?php echo esc_attr( wc_cart_totals_coupon_label( $coupon, false ) ); ?>"><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
						</tr>
					<?php endforeach; ?>

					<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) :
						do_action( 'woocommerce_cart_totals_before_shipping' );
						wc_cart_totals_shipping_html();
						do_action( 'woocommerce_cart_totals_after_shipping' );
					elseif ( WC()->cart->needs_shipping() ) : ?>
						<tr class="shipping">
							<th><?php esc_html_e( 'Verzending', 'oz-theme' ); ?></th>
							<td data-title="<?php esc_attr_e( 'Verzending', 'oz-theme' ); ?>"><?php woocommerce_shipping_calculator(); ?></td>
						</tr>
					<?php endif; ?>

					<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
						<tr class="fee">
							<th><?php echo esc_html( $fee->name ); ?></th>
							<td data-title="<?php echo esc_attr( $fee->name ); ?>"><?php wc_cart_totals_fee_html( $fee ); ?></td>
						</tr>
					<?php endforeach; ?>

					<?php
					if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) {
						$taxable_address = WC()->customer->get_taxable_address();
						$estimated_text  = WC()->customer->is_customer_outside_base() && ! WC()->customer->has_calculated_shipping()
							? sprintf( ' <small>' . esc_html__( '(geschat voor %s)', 'oz-theme' ) . '</small>', WC()->countries->estimated_for_prefix( $taxable_address[0] ) . WC()->countries->countries[ $taxable_address[0] ] )
							: '';

						if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) {
							foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
								<tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
									<th><?php echo esc_html( $tax->label ) . $estimated_text; ?></th>
									<td data-title="<?php echo esc_attr( $tax->label ); ?>"><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
								</tr>
							<?php endforeach;
						} else { ?>
							<tr class="tax-total">
								<th><?php echo esc_html( WC()->countries->tax_or_vat() ) . $estimated_text; ?></th>
								<td data-title="<?php echo esc_attr( WC()->countries->tax_or_vat() ); ?>"><?php wc_cart_totals_taxes_total_html(); ?></td>
							</tr>
						<?php }
					}
					?>

					<?php do_action( 'woocommerce_cart_totals_before_order_total' ); ?>

					<tr class="order-total">
						<th><?php esc_html_e( 'Totaal', 'oz-theme' ); ?></th>
						<td data-title="<?php esc_attr_e( 'Totaal', 'oz-theme' ); ?>"><?php wc_cart_totals_order_total_html(); ?></td>
					</tr>

					<?php do_action( 'woocommerce_cart_totals_after_order_total' ); ?>
				</table>

				<div class="wc-proceed-to-checkout">
					<?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
				</div>

				<?php do_action( 'woocommerce_after_cart_totals' ); ?>
			</div>
		</aside>

	</div>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
