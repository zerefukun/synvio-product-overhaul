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
 * Special: group block with anchor "stappen-plan" triggers timeline mode.
 * The group + all consecutive columns blocks after it are wrapped in a
 * single .oz-timeline container, creating the vertical step-by-step timeline.
 *
 * @package OzTheme
 */

get_header(); ?>

<div class="oz-ruimte">
<?php
while ( have_posts() ) : the_post();

    $blocks    = parse_blocks( get_the_content() );
    $section_i = 0;

    /* Pre-scan: find which block indices belong to the stappen-plan timeline.
       The stappen-plan group block + all consecutive columns blocks after it
       are rendered together inside a single .oz-timeline wrapper. */
    $timeline_indices = [];
    foreach ( $blocks as $i => $block ) {
        if ( empty( $block['blockName'] ) ) continue;

        if ( $block['blockName'] === 'core/group'
             && ! empty( $block['attrs']['anchor'] )
             && $block['attrs']['anchor'] === 'stappen-plan' ) {
            $timeline_indices[] = $i;
            /* Collect consecutive columns blocks (skip whitespace null blocks) */
            for ( $j = $i + 1; $j < count( $blocks ); $j++ ) {
                if ( empty( $blocks[ $j ]['blockName'] ) ) {
                    $timeline_indices[] = $j;
                    continue;
                }
                if ( $blocks[ $j ]['blockName'] === 'core/columns' ) {
                    $timeline_indices[] = $j;
                } else {
                    break;
                }
            }
            break;
        }
    }

    foreach ( $blocks as $i => $block ) {
        /* Skip null blocks (whitespace / empty paragraphs between blocks) */
        if ( empty( $block['blockName'] ) ) continue;

        /* Skip blocks already consumed by the timeline grouping */
        if ( in_array( $i, $timeline_indices, true ) && $i !== $timeline_indices[0] ) continue;

        $name     = $block['blockName'];
        $rendered = render_block( $block );
        $bg       = ( $section_i % 2 === 1 ) ? ' oz-section--warm' : '';

        /* ── Timeline: stappen-plan group + following columns ── */
        if ( in_array( $i, $timeline_indices, true ) ) {
            echo '<section class="oz-section' . $bg . '" data-reveal>';
            echo '<div class="oz-container">';
            echo '<div class="oz-timeline">';
            echo $rendered; /* The group block (renders its inner Stap 1) */
            /* Render the collected columns blocks (Steps 2, 3, 4, 5...) */
            foreach ( $timeline_indices as $ti ) {
                if ( $ti === $i ) continue; /* skip the group itself */
                if ( empty( $blocks[ $ti ]['blockName'] ) ) continue;
                echo render_block( $blocks[ $ti ] );
            }
            echo '</div>';
            echo '</div>';
            echo '</section>';

        /* Cover blocks: full-width hero, no container wrapper */
        } elseif ( $name === 'core/cover' ) {
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
