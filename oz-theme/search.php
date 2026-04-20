<?php
/**
 * Search results template.
 *
 * @package OzTheme
 */

get_header(); ?>

<div class="oz-search-results oz-container">

	<header class="oz-search-results__header">
		<h1 class="oz-search-results__title">
			<?php printf( esc_html__( 'Zoekresultaten voor: %s', 'oz-theme' ), '<span>' . get_search_query() . '</span>' ); ?>
		</h1>
		<?php if ( function_exists( 'oz_render_search_suggestions' ) ) { oz_render_search_suggestions(); } ?>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="oz-post-grid">
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class( 'oz-card' ); ?>>
					<?php if ( has_post_thumbnail() ) : ?>
						<div class="oz-card__image">
							<a href="<?php the_permalink(); ?>">
								<?php the_post_thumbnail( 'medium_large' ); ?>
							</a>
						</div>
					<?php endif; ?>
					<div class="oz-card__body">
						<h2 class="oz-card__title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h2>
						<div class="oz-card__excerpt"><?php the_excerpt(); ?></div>
					</div>
				</article>
			<?php endwhile; ?>
		</div>

		<?php the_posts_pagination( [
			'mid_size'  => 2,
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
		] ); ?>
	<?php else : ?>
		<div class="oz-search-results__empty">
			<h2>Geen resultaten gevonden</h2>
			<p>Probeer een andere zoekterm of bekijk onze <a href="<?php echo esc_url( home_url( '/product-categorie/' ) ); ?>">productcategorieen</a>.</p>
		</div>
	<?php endif; ?>

</div>

<?php get_footer(); ?>
