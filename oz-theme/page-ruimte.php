<?php
/**
 * Template Name: Ruimte
 *
 * Full-width page template for ruimtes pages (badkamer, keuken, vloer, etc.).
 * Each top-level Gutenberg block becomes a full-width section with alternating
 * backgrounds and scroll-reveal animations via oz_render_block_sections().
 *
 * @package OzTheme
 */

get_header(); ?>

<div class="oz-ruimte">
<?php
while ( have_posts() ) : the_post();
	do_action( 'oz_ruimte_quicknav' );
	/* Template-side hero — alleen actief voor pages zonder eigen .oz-hp-hero
	   in hun Gutenberg-content. Renderer: oz_render_ruimte_template_hero(). */
	do_action( 'oz_ruimte_hero' );
	/* Template-side USP-marquee — alleen actief voor pages zonder eigen
	   oz-hp-trust block in hun content. Renderer: oz_render_ruimte_template_trust(). */
	do_action( 'oz_ruimte_trust' );
	oz_render_block_sections( get_the_content() );
endwhile;
oz_render_reviews_section( 'ruimte' );
?>
</div>

<?php get_footer(); ?>
