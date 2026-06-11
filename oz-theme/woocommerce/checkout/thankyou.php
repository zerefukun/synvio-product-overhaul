<?php
/**
 * Order confirmation / thank you page.
 *
 * @package OzTheme
 * @see     https://woocommerce.github.io/code-reference/files/woocommerce-templates-checkout-thankyou.html
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="oz-thankyou oz-container">

<?php
if ( $order ) :
	do_action( 'woocommerce_before_thankyou', $order->get_id() );

	if ( $order->has_status( 'failed' ) ) : ?>

		<div class="oz-thankyou__failed">
			<h2><?php esc_html_e( 'Helaas, je betaling is mislukt.', 'oz-theme' ); ?></h2>
			<p><?php esc_html_e( 'Probeer het opnieuw of kies een andere betaalmethode.', 'oz-theme' ); ?></p>
			<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="oz-btn oz-btn--primary">
				<?php esc_html_e( 'Opnieuw betalen', 'oz-theme' ); ?>
			</a>
		</div>

	<?php else : ?>

		<div class="oz-thankyou__success">
			<div class="oz-thankyou__icon">
				<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--oz-success, #38A169)" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
			</div>
			<h2><?php esc_html_e( 'Bedankt voor je bestelling!', 'oz-theme' ); ?></h2>
			<p class="oz-thankyou__order-number">
				<?php printf( esc_html__( 'Bestelnummer: %s', 'oz-theme' ), '<strong>' . $order->get_order_number() . '</strong>' ); ?>
			</p>
		</div>

		<div class="oz-thankyou__details">
			<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
			<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>
		</div>

	<?php endif; ?>

<?php else : ?>

	<div class="oz-thankyou__success">
		<h2><?php esc_html_e( 'Bedankt voor je bestelling!', 'oz-theme' ); ?></h2>
	</div>

<?php endif; ?>

</div>
