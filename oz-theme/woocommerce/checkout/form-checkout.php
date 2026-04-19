<?php
/**
 * Checkout form.
 * Two-column layout: billing/shipping left, order summary right.
 *
 * @package OzTheme
 * @see     https://woocommerce.github.io/code-reference/files/woocommerce-templates-checkout-form-checkout.html
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}
?>

<div class="oz-checkout-page">

	<div class="oz-checkout-page__header">
		<h1 class="oz-checkout-page__title"><?php esc_html_e( 'Afrekenen', 'oz-theme' ); ?></h1>
	</div>

<form name="checkout" method="post" class="checkout woocommerce-checkout oz-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

	<div class="oz-checkout__columns">

		<div class="oz-checkout__main">
			<?php if ( $checkout->get_checkout_fields() ) : ?>

				<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

				<div id="customer_details">
					<div class="oz-checkout__section">
						<?php do_action( 'woocommerce_checkout_billing' ); ?>
					</div>

					<div class="oz-checkout__section">
						<?php do_action( 'woocommerce_checkout_shipping' ); ?>
					</div>
				</div>

				<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

			<?php endif; ?>
		</div>

		<div class="oz-checkout__sidebar">
			<div class="oz-checkout__order-review">
				<h3 id="order_review_heading"><?php esc_html_e( 'Jouw bestelling', 'oz-theme' ); ?></h3>

				<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

				<div id="order_review" class="woocommerce-checkout-review-order">
					<?php do_action( 'woocommerce_checkout_order_review' ); ?>
				</div>

				<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
			</div>
		</div>

	</div>

</form>

</div>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
