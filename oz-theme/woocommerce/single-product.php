<?php
/**
 * Single product page — minimal wrapper.
 *
 * For configured product lines (original, microcement, etc.), the
 * oz-variations-bcw plugin intercepts via template_include at priority 20
 * and loads its own template. This file only renders for products NOT
 * handled by the plugin (generic WC products).
 *
 * @package OzTheme
 */

defined( 'ABSPATH' ) || exit;

get_header(); ?>

<div class="oz-product oz-container">

	<?php do_action( 'woocommerce_before_main_content' ); ?>

	<?php while ( have_posts() ) : the_post(); ?>
		<?php wc_get_template_part( 'content', 'single-product' ); ?>
	<?php endwhile; ?>

	<?php do_action( 'woocommerce_after_main_content' ); ?>

</div>

<?php get_footer(); ?>
