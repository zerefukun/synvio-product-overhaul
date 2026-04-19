<?php
/**
 * Template Name: Sitemap
 *
 * Design-system-scoped sitemap page. Lists all published pages, posts,
 * and categories in a two-column border-grid reusing .oz-kb-list.
 *
 * @package OzTheme
 */

get_header(); ?>

<div class="oz-ruimte oz-sitemap">

	<!-- Hero -->
	<section class="wp-block-cover alignfull oz-sitemap__hero" style="min-height:42vh">
		<span aria-hidden="true" class="wp-block-cover__background has-background-dim-60 has-background-dim"></span>
		<div class="wp-block-cover__inner-container">
			<p class="has-text-align-center oz-eyebrow has-white-color has-text-color">Overzicht</p>
			<h1 class="wp-block-heading has-text-align-center has-white-color has-text-color">Sitemap</h1>
			<p class="has-text-align-center has-white-color has-text-color">Alle pagina's, artikelen en categorieën op één plek. Snel navigeren naar het onderwerp dat je zoekt.</p>
		</div>
	</section>

	<!-- Pagina's -->
	<section class="wp-block-group alignfull oz-section">
		<div class="oz-container">
			<h2 class="wp-block-heading has-text-align-center">Pagina's</h2>
			<?php
			$wppages = new WP_Query( array(
				'post_type'      => 'page',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			) );
			if ( $wppages->have_posts() ) : ?>
				<ul class="oz-kb-list">
					<?php while ( $wppages->have_posts() ) : $wppages->the_post(); ?>
						<li><a href="<?php the_permalink(); ?>" rel="dofollow"><strong><?php the_title(); ?></strong></a></li>
					<?php endwhile; wp_reset_postdata(); ?>
				</ul>
			<?php endif; ?>
		</div>
	</section>

	<!-- Berichten -->
	<section class="wp-block-group alignfull oz-section">
		<div class="oz-container">
			<h2 class="wp-block-heading has-text-align-center">Berichten</h2>
			<?php
			$wpposts = new WP_Query( array(
				'post_type'      => 'post',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			) );
			if ( $wpposts->have_posts() ) : ?>
				<ul class="oz-kb-list">
					<?php while ( $wpposts->have_posts() ) : $wpposts->the_post(); ?>
						<li><a href="<?php the_permalink(); ?>" rel="dofollow"><strong><?php the_title(); ?></strong></a></li>
					<?php endwhile; wp_reset_postdata(); ?>
				</ul>
			<?php endif; ?>
		</div>
	</section>

	<!-- Categorieën -->
	<section class="wp-block-group alignfull oz-section">
		<div class="oz-container">
			<h2 class="wp-block-heading has-text-align-center">Categorieën</h2>
			<?php
			$categories = get_categories( array( 'orderby' => 'name', 'order' => 'ASC' ) );
			if ( ! empty( $categories ) ) : ?>
				<ul class="oz-kb-list">
					<?php foreach ( $categories as $category ) : ?>
						<li>
							<a href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>" rel="dofollow">
								<strong><?php echo esc_html( $category->name ); ?></strong>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</section>

	<!-- XML Sitemap CTA -->
	<section class="wp-block-group alignfull oz-section oz-sitemap__xml">
		<div class="oz-container">
			<div class="oz-sitemap__xml-card">
				<p class="oz-eyebrow">Voor zoekmachines</p>
				<h2 class="wp-block-heading">XML Sitemap</h2>
				<p>De gestructureerde versie voor Google en andere crawlers.</p>
				<div class="wp-block-buttons">
					<div class="wp-block-button is-style-outline">
						<a class="wp-block-button__link wp-element-button" href="/sitemap_index.xml">Open XML sitemap</a>
					</div>
				</div>
			</div>
		</div>
	</section>

</div>

<?php get_footer(); ?>
