<?php
/**
 * Generic page template — renders Gutenberg blocks with the design system.
 *
 * @package OzTheme
 */

get_header(); ?>

<div class="oz-page oz-container">
	<?php while ( have_posts() ) : the_post(); ?>
		<div class="oz-page__content">
			<?php the_content(); ?>
		</div>
	<?php endwhile; ?>
</div>

<?php get_footer(); ?>
