<?php
/**
 * Single post template.
 *
 * Stucsoorten category posts (pillar landing pages like betonlook, betonstuc)
 * use the block-section renderer inside the oz-ruimte wrapper — same layout
 * primitives as page-ruimte.php.
 *
 * All other posts (kennisbank articles, blog) use the standard article layout.
 *
 * @package OzTheme
 */

get_header();

while ( have_posts() ) : the_post();

	$is_stucsoorten = has_category( 'stucsoorten' );

	if ( $is_stucsoorten ) : ?>
		<div class="oz-ruimte">
			<?php
			oz_render_block_sections( get_the_content() );
			oz_render_reviews_section( 'ruimte' );
			?>
		</div>
	<?php else : ?>
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
	<?php endif;

endwhile;

get_footer();
