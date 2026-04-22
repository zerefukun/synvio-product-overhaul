<?php
/**
 * Archive template — blog listing, category, tag, date archives.
 *
 * @package OzTheme
 */

get_header(); ?>

<div class="oz-archive oz-container">

	<header class="oz-archive__header">
		<?php the_archive_title( '<h1 class="oz-archive__title">', '</h1>' ); ?>
		<?php the_archive_description( '<div class="oz-archive__desc">', '</div>' ); ?>
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
						<div class="oz-eyebrow"><?php echo esc_html( get_the_date() ); ?></div>
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
