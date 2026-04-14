<?php
/**
 * Shop / product category page.
 * Replaces WooCommerce's default archive-product.php.
 * Uses our oz-card design and responsive grid.
 *
 * @package OzTheme
 */

defined( 'ABSPATH' ) || exit;

get_header(); ?>

<div class="oz-shop oz-container">

	<?php do_action( 'woocommerce_before_main_content' ); ?>

	<header class="oz-shop__header">
		<?php if ( apply_filters( 'woocommerce_show_page_title', true ) ) : ?>
			<h1 class="oz-shop__title"><?php woocommerce_page_title(); ?></h1>
		<?php endif; ?>

		<?php do_action( 'woocommerce_archive_description' ); ?>
	</header>

	<?php if ( woocommerce_product_loop() ) : ?>

		<div class="oz-shop__toolbar">
			<?php do_action( 'woocommerce_before_shop_loop' ); ?>
		</div>

		<?php woocommerce_product_loop_start(); ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<?php wc_get_template_part( 'content', 'product' ); ?>
			<?php endwhile; ?>
		<?php woocommerce_product_loop_end(); ?>

		<?php do_action( 'woocommerce_after_shop_loop' ); ?>

	<?php else : ?>

		<?php do_action( 'woocommerce_no_products_found' ); ?>

	<?php endif; ?>

	<?php do_action( 'woocommerce_after_main_content' ); ?>

</div>

<?php get_footer(); ?>
