<?php
/**
 * Block-section renderer — wraps each top-level Gutenberg block in an
 * <section class="oz-section"> with alternating backgrounds and data-reveal.
 *
 * Shared by page-ruimte.php and single.php (stucsoorten category posts),
 * so both use the same layout primitives and design tokens.
 *
 * Special handling:
 *   - core/cover            → full-width hero (no container wrapper)
 *   - core/columns          → contained section with stagger reveal
 *   - core/group (alignfull)→ section without container (Group is the container)
 *   - core/group anchor "stappen-plan" → timeline wrapper consuming the group
 *     plus all consecutive core/columns blocks after it.
 *
 * @package OzTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a post's content as a sequence of wrapped block-sections.
 *
 * @param string $content Raw post_content (Gutenberg block markup).
 */
function oz_render_block_sections( $content ) {
	$blocks     = parse_blocks( $content );
	$section_i  = 0;
	$hero_used  = false;

	/* Pre-scan: which block indices belong to the stappen-plan timeline. */
	$timeline_indices = array();
	foreach ( $blocks as $i => $block ) {
		if ( empty( $block['blockName'] ) ) continue;

		if ( $block['blockName'] === 'core/group'
			&& ! empty( $block['attrs']['anchor'] )
			&& $block['attrs']['anchor'] === 'stappen-plan' ) {
			$timeline_indices[] = $i;
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
		if ( empty( $block['blockName'] ) ) continue;
		if ( in_array( $i, $timeline_indices, true ) && $i !== $timeline_indices[0] ) continue;

		$name     = $block['blockName'];
		$rendered = render_block( $block );
		$bg       = ( $section_i % 2 === 1 ) ? ' oz-section--warm' : '';

		if ( in_array( $i, $timeline_indices, true ) ) {
			echo '<section class="oz-section' . $bg . '" data-reveal>';
			echo '<div class="oz-container">';
			echo '<div class="oz-timeline">';
			echo $rendered;
			foreach ( $timeline_indices as $ti ) {
				if ( $ti === $i ) continue;
				if ( empty( $blocks[ $ti ]['blockName'] ) ) continue;
				echo render_block( $blocks[ $ti ] );
			}
			echo '</div>';
			echo '</div>';
			echo '</section>';

		} elseif ( $name === 'core/cover' ) {
			if ( ! $hero_used ) {
				echo '<section class="oz-ruimte__hero" data-reveal>';
				echo $rendered;
				echo '</section>';
				$hero_used = true;
			} else {
				echo '<section class="oz-section oz-section--cover' . $bg . '" data-reveal>';
				echo $rendered;
				echo '</section>';
			}

		} elseif ( $name === 'core/columns' ) {
			$is_full = ! empty( $block['attrs']['align'] ) && $block['attrs']['align'] === 'full';
			echo '<section class="oz-section' . $bg . '" data-reveal-stagger>';
			if ( ! $is_full ) echo '<div class="oz-container">';
			echo $rendered;
			if ( ! $is_full ) echo '</div>';
			echo '</section>';

		} elseif ( $name === 'core/group' && ! empty( $block['attrs']['align'] ) && $block['attrs']['align'] === 'full' ) {
			echo '<section class="oz-section' . $bg . '" data-reveal>';
			echo $rendered;
			echo '</section>';

		} else {
			echo '<section class="oz-section' . $bg . '" data-reveal>';
			echo '<div class="oz-container">';
			echo $rendered;
			echo '</div>';
			echo '</section>';
		}

		$section_i++;
	}
}
