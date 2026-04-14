<?php
/**
 * Mega Menu Walker — renders desktop horizontal nav with dropdown panels.
 *
 * Top-level items become horizontal links. Items with children get a mega
 * dropdown panel that appears on hover / focus-within (CSS-driven).
 *
 * Usage in header.php:
 *   wp_nav_menu([
 *       'theme_location' => 'oz-primary',
 *       'walker'         => new Oz_Mega_Menu_Walker(),
 *       'depth'          => 2,
 *       ...
 *   ]);
 *
 * @package OzTheme
 */

class Oz_Mega_Menu_Walker extends Walker_Nav_Menu {

	/** Track whether the current top-level item has children. */
	private $current_item_has_children = false;

	/**
	 * Start a <li> element.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$classes = empty( $item->classes ) ? [] : (array) $item->classes;

		/* Depth 0: top-level nav item */
		if ( $depth === 0 ) {
			$this->current_item_has_children = in_array( 'menu-item-has-children', $classes, true );

			$li_classes = [ 'oz-nav__item' ];
			if ( $this->current_item_has_children ) {
				$li_classes[] = 'has-mega';
			}
			if ( in_array( 'cta', $classes, true ) ) {
				$li_classes[] = 'oz-nav__item--cta';
			}

			$output .= '<li class="' . esc_attr( implode( ' ', $li_classes ) ) . '">';
			$output .= '<a href="' . esc_url( $item->url ) . '" class="oz-nav__link">';
			$output .= esc_html( $item->title );

			if ( $this->current_item_has_children ) {
				$output .= '<svg class="oz-nav__chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>';
			}

			$output .= '</a>';

			/* Open mega panel container (children will be appended inside) */
			if ( $this->current_item_has_children ) {
				$output .= '<div class="oz-mega__panel"><div class="oz-mega__inner">';
			}
		}

		/* Depth 1: child inside mega panel */
		if ( $depth === 1 ) {
			$output .= '<a href="' . esc_url( $item->url ) . '" class="oz-mega__link">';
			$output .= esc_html( $item->title );
			$output .= '</a>';
		}
	}

	/**
	 * End a <li> element.
	 */
	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		if ( $depth === 0 ) {
			/* Close mega panel if this item had children */
			if ( $this->current_item_has_children ) {
				$output .= '</div></div>'; /* .oz-mega__inner + .oz-mega__panel */
			}
			$output .= '</li>';
		}
		/* Depth 1 items are self-closing <a> tags, no </li> needed */
	}

	/**
	 * Start a submenu <ul> — we suppress it because mega panels use flat links.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		/* No <ul> wrapper — children render directly inside .oz-mega__inner */
	}

	/**
	 * End a submenu </ul> — suppressed.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		/* No closing </ul> */
	}
}
