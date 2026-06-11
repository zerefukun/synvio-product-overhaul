<?php
/**
 * Fallback template — used when no more specific template matches.
 *
 * @package OzTheme
 */

get_header(); ?>

<div class="oz-content oz-container">

<?php if ( have_posts() ) : ?>

	<?php if ( is_home() && ! is_front_page() ) : ?>
		<header class="oz-content__header">
			<h1 class="oz-content__title"><?php single_post_title(); ?></h1>
		</header>
	<?php endif; ?>

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
	<p><?php esc_html_e( 'Geen berichten gevonden.', 'oz-theme' ); ?></p>
<?php endif; ?>

</div>

<?php get_footer(); ?>
