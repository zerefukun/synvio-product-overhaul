<?php
/**
 * Template Name: Ruimte
 *
 * Full-width page template for ruimtes pages (badkamer, keuken, vloer, etc.).
 * Each top-level Gutenberg block becomes a full-width section with alternating
 * backgrounds and scroll-reveal animations.
 *
 * Content editing: SEO team uses Gutenberg block editor.
 *   - Cover block  → hero section (full-width, no container)
 *   - Group block   → regular section (contained, alternating bg)
 *   - Columns block → contained section with side-by-side layout
 *   - Other blocks  → contained section
 *
 * @package OzTheme
 */

get_header(); ?>

<div class="oz-ruimte">
<?php
while ( have_posts() ) : the_post();

    $blocks    = parse_blocks( get_the_content() );
    $section_i = 0;

    foreach ( $blocks as $block ) {
        /* Skip null blocks (whitespace / empty paragraphs between blocks) */
        if ( empty( $block['blockName'] ) ) continue;

        $name     = $block['blockName'];
        $rendered = render_block( $block );
        $bg       = ( $section_i % 2 === 1 ) ? ' oz-section--warm' : '';

        /* Cover blocks: full-width hero, no container wrapper */
        if ( $name === 'core/cover' ) {
            echo '<section class="oz-ruimte__hero" data-reveal>';
            echo $rendered;
            echo '</section>';

        /* Columns blocks: auto-stagger the child columns */
        } elseif ( $name === 'core/columns' ) {
            echo '<section class="oz-section' . $bg . '" data-reveal-stagger>';
            echo '<div class="oz-container">';
            echo $rendered;
            echo '</div>';
            echo '</section>';

        /* Group blocks with alignfull: section without container (the Group is the container) */
        } elseif ( $name === 'core/group' && ! empty( $block['attrs']['align'] ) && $block['attrs']['align'] === 'full' ) {
            echo '<section class="oz-section' . $bg . '" data-reveal>';
            echo $rendered;
            echo '</section>';

        /* Everything else: contained section */
        } else {
            echo '<section class="oz-section' . $bg . '" data-reveal>';
            echo '<div class="oz-container">';
            echo $rendered;
            echo '</div>';
            echo '</section>';
        }

        $section_i++;
    }

endwhile;
?>
</div>

<?php get_footer(); ?>
