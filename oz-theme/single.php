<?php
/**
 * Single post template — blog articles, kennisbank entries.
 *
 * @package OzTheme
 */

get_header(); ?>

<article <?php post_class( 'oz-article' ); ?>>

	<?php if ( has_post_thumbnail() ) : ?>
		<div class="oz-article__hero">
			<?php the_post_thumbnail( 'large', [ 'class' => 'oz-article__hero-img' ] ); ?>
		</div>
	<?php endif; ?>

	<div class="oz-article__inner oz-container">
		<header class="oz-article__header">
			<div class="oz-eyebrow"><?php echo esc_html( get_the_date() ); ?></div>
			<h1 class="oz-article__title"><?php the_title(); ?></h1>
		</header>

		<div class="oz-article__content">
			<?php the_content(); ?>
		</div>

		<nav class="oz-article__nav">
			<?php
			$prev = get_previous_post();
			$next = get_next_post();
			if ( $prev ) : ?>
				<a href="<?php echo esc_url( get_permalink( $prev ) ); ?>" class="oz-article__nav-link oz-article__nav-link--prev">
					&laquo; <?php echo esc_html( get_the_title( $prev ) ); ?>
				</a>
			<?php endif;
			if ( $next ) : ?>
				<a href="<?php echo esc_url( get_permalink( $next ) ); ?>" class="oz-article__nav-link oz-article__nav-link--next">
					<?php echo esc_html( get_the_title( $next ) ); ?> &raquo;
				</a>
			<?php endif; ?>
		</nav>
	</div>

</article>

<?php get_footer(); ?>
