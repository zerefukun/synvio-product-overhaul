<?php
/**
 * Custom walker for the shop sidebar nav menu.
 * Outputs our oz-cat-nav markup with toggle chevrons
 * and auto-opens the branch containing the current page.
 *
 * @package OzTheme
 */

defined( 'ABSPATH' ) || exit;

class Oz_Shop_Sidebar_Walker extends Walker_Nav_Menu {

    private $current_url = '';

    public function __construct( $current_url = '' ) {
        $this->current_url = $current_url;
    }

    /**
     * Open a sub-menu <ul>.
     */
    public function start_lvl( &$output, $depth = 0, $args = null ) {
        $output .= '<ul class="oz-cat-nav__sub">';
    }

    public function end_lvl( &$output, $depth = 0, $args = null ) {
        $output .= '</ul>';
    }

    /**
     * Open a menu item <li>.
     */
    public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        $has_children = ! empty( $args->walker->has_children );
        $item_url     = trailingslashit( wp_parse_url( $item->url, PHP_URL_PATH ) ?: '' );

        /* Active state: current page matches this item's URL */
        $is_active = ( $item_url === $this->current_url );

        /* Ancestor state: WP marks ancestors via current-menu-ancestor class */
        $is_ancestor = in_array( 'current-menu-ancestor', (array) $item->classes, true )
                    || in_array( 'current-menu-parent', (array) $item->classes, true );

        $classes = 'oz-cat-nav__item';
        if ( $is_active )                    $classes .= ' is-active';
        if ( $is_active || $is_ancestor )    $classes .= ' is-open';
        if ( $has_children )                 $classes .= ' has-children';

        $output .= '<li class="' . esc_attr( $classes ) . '">';

        if ( $has_children ) {
            $output .= '<div class="oz-cat-nav__row">';
        }

        $output .= '<a href="' . esc_url( $item->url ) . '" class="oz-cat-nav__link">';
        $output .= esc_html( $item->title );
        $output .= '</a>';

        if ( $has_children ) {
            $output .= '<button class="oz-cat-nav__toggle" type="button" aria-label="Subcategorieën tonen">';
            $output .= '<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3,4.5 6,7.5 9,4.5"/></svg>';
            $output .= '</button>';
            $output .= '</div>';
        }
    }

    public function end_el( &$output, $item, $depth = 0, $args = null ) {
        $output .= '</li>';
    }
}
