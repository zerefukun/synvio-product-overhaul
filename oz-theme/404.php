<?php
/**
 * 404 template — page not found.
 *
 * @package OzTheme
 */

get_header(); ?>

<div class="oz-404 oz-container">
	<div class="oz-404__inner">
		<h1 class="oz-404__title">404</h1>
		<p class="oz-404__text">Deze pagina bestaat niet of is verplaatst.</p>
		<div class="oz-404__actions">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="oz-btn oz-btn--primary">Naar de homepage</a>
			<a href="<?php echo esc_url( home_url( '/product-categorie/' ) ); ?>" class="oz-btn oz-btn--outline">Bekijk producten</a>
		</div>
	</div>
</div>

<?php get_footer(); ?>
