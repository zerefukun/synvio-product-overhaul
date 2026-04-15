<?php
/**
 * Flatsome Shortcode Compatibility Layer
 *
 * Registers stub shortcode handlers so existing pages that were built
 * with Flatsome UX Builder continue to render properly.
 * Outputs semantic HTML with oz-fs-* classes styled in oz-blocks.css.
 *
 * @package OzTheme
 */

defined( 'ABSPATH' ) || exit;

/* Only register stubs if Flatsome is NOT the active parent theme.
 * When Flatsome is active, its own handlers take priority. */
if ( get_template() === 'flatsome' ) {
	return;
}

/* ── Layout: Row ── */
function oz_fs_row( $atts, $content = '' ) {
	$a = shortcode_atts( [
		'style'     => '',
		'width'     => '',
		'v_align'   => '',
		'label'     => '',
	], $atts );

	$classes = 'oz-fs-row';
	if ( $a['style'] === 'collapse' ) $classes .= ' oz-fs-row--collapse';
	if ( $a['width'] === 'full-width' ) $classes .= ' oz-fs-row--full';
	if ( $a['v_align'] === 'middle' ) $classes .= ' oz-fs-row--middle';

	return '<div class="' . esc_attr( $classes ) . '">' . do_shortcode( $content ) . '</div>';
}
add_shortcode( 'row', 'oz_fs_row' );
add_shortcode( 'row_inner', 'oz_fs_row' );
add_shortcode( 'row_inner_1', 'oz_fs_row' );

/* ── Layout: Column ── */
function oz_fs_col( $atts, $content = '' ) {
	$a = shortcode_atts( [
		'span'       => '12',
		'span__sm'   => '',
		'span__md'   => '',
		'bg_color'   => '',
		'padding'    => '',
		'padding__sm'=> '',
		'margin'     => '',
		'margin__sm' => '',
		'align'      => '',
		'animate'    => '',
		'border'     => '',
		'border_color' => '',
		'bg_radius'  => '',
		'divider'    => '',
		'visibility' => '',
		'color'      => '',
		'depth'      => '',
		'depth_hover'=> '',
		'force_first'=> '',
	], $atts );

	$style = '';
	$span  = intval( $a['span'] );
	/* Floor to avoid rounding overflow (6 * 16.67% = 100.02% wraps) */
	$width = floor( ( $span / 12 ) * 10000 ) / 100;

	$style .= "flex: 0 1 {$width}%; max-width: {$width}%;";

	if ( $a['bg_color'] ) $style .= 'background-color:' . esc_attr( $a['bg_color'] ) . ';';
	if ( $a['padding'] )  $style .= 'padding:' . esc_attr( $a['padding'] ) . ';';
	if ( $a['margin'] )   $style .= 'margin:' . esc_attr( $a['margin'] ) . ';';
	if ( $a['bg_radius'] ) $style .= 'border-radius:' . esc_attr( $a['bg_radius'] ) . 'px;';

	if ( $a['border'] && $a['border_color'] ) {
		$style .= 'border:' . esc_attr( $a['border'] ) . 'px solid ' . esc_attr( $a['border_color'] ) . ';';
	}

	$classes = 'oz-fs-col';
	if ( $a['align'] )  $classes .= ' oz-fs-col--' . esc_attr( $a['align'] );
	if ( $a['color'] === 'light' ) $classes .= ' oz-fs-col--light';
	if ( $a['visibility'] === 'hide-for-medium' ) $classes .= ' oz-fs-col--hide-mobile';
	if ( $a['visibility'] === 'show-for-medium' ) $classes .= ' oz-fs-col--show-mobile-only';

	$sm_style = '';
	if ( $a['span__sm'] ) {
		$sm_span  = intval( $a['span__sm'] );
		$sm_width = floor( ( $sm_span / 12 ) * 10000 ) / 100;
		$sm_style = "--oz-fs-col-sm: {$sm_width}%;";
	}
	if ( $a['padding__sm'] ) {
		$sm_style .= "--oz-fs-col-padding-sm: " . esc_attr( $a['padding__sm'] ) . ";";
	}

	$all_style = $style . $sm_style;

	return '<div class="' . esc_attr( $classes ) . '" style="' . esc_attr( $all_style ) . '">' . do_shortcode( $content ) . '</div>';
}
add_shortcode( 'col', 'oz_fs_col' );
add_shortcode( 'col_inner', 'oz_fs_col' );
add_shortcode( 'col_inner_1', 'oz_fs_col' );

/* ── Layout: Section ── */
function oz_fs_section( $atts, $content = '' ) {
	$a = shortcode_atts( [
		'bg_color'   => '',
		'bg'         => '',
		'bg_size'    => 'cover',
		'bg_pos'     => 'center',
		'bg_overlay' => '',
		'padding'    => '',
		'padding__sm'=> '',
		'margin'     => '',
		'height'     => '',
		'height__sm' => '',
		'dark'       => '',
		'visibility' => '',
		'label'      => '',
		'border'     => '',
		'border_margin' => '',
	], $atts );

	$style   = '';
	$classes = 'oz-fs-section';

	if ( $a['bg_color'] ) $style .= 'background-color:' . esc_attr( $a['bg_color'] ) . ';';
	if ( $a['padding'] )  $style .= 'padding:' . esc_attr( $a['padding'] ) . ';';
	if ( $a['margin'] )   $style .= 'margin:' . esc_attr( $a['margin'] ) . ';';
	if ( $a['height'] )   $style .= 'min-height:' . esc_attr( $a['height'] ) . ';';

	if ( $a['bg'] ) {
		$img_url = wp_get_attachment_image_url( intval( $a['bg'] ), 'large' );
		if ( $img_url ) {
			$style .= 'background-image:url(' . esc_url( $img_url ) . ');';
			$style .= 'background-size:' . esc_attr( $a['bg_size'] ) . ';';
			$style .= 'background-position:' . esc_attr( $a['bg_pos'] ) . ';';
		}
	}

	if ( $a['dark'] === 'true' ) $classes .= ' oz-fs-section--dark';
	if ( $a['visibility'] === 'hide-for-medium' ) $classes .= ' oz-fs-col--hide-mobile';

	return '<div class="' . esc_attr( $classes ) . '" style="' . esc_attr( $style ) . '">' . do_shortcode( $content ) . '</div>';
}
add_shortcode( 'section', 'oz_fs_section' );

/* ── Banner ── */
function oz_fs_ux_banner( $atts, $content = '' ) {
	$a = shortcode_atts( [
		'height'     => '300px',
		'height__sm' => '',
		'height__md' => '',
		'bg'         => '',
		'bg_color'   => '',
		'bg_overlay' => '',
		'bg_pos'     => 'center center',
		'bg_size'    => 'cover',
		'link'       => '',
		'hover'      => '',
		'border'     => '',
		'border_color' => '',
		'visibility' => '',
	], $atts );

	$style   = 'min-height:' . esc_attr( $a['height'] ) . ';';
	$classes = 'oz-fs-banner';

	if ( $a['bg_color'] ) $style .= 'background-color:' . esc_attr( $a['bg_color'] ) . ';';

	$img_html = '';
	if ( $a['bg'] ) {
		$img_url = wp_get_attachment_image_url( intval( $a['bg'] ), 'large' );
		if ( $img_url ) {
			$style .= 'background-image:url(' . esc_url( $img_url ) . ');';
			$style .= 'background-size:' . esc_attr( $a['bg_size'] ) . ';';
			$style .= 'background-position:' . esc_attr( $a['bg_pos'] ) . ';';
		}
	}

	if ( $a['bg_overlay'] ) {
		$style .= '--oz-fs-overlay:' . esc_attr( $a['bg_overlay'] ) . ';';
		$classes .= ' oz-fs-banner--overlay';
	}

	if ( $a['border'] && $a['border_color'] ) {
		$style .= 'border:' . esc_attr( $a['border'] ) . 'px solid ' . esc_attr( $a['border_color'] ) . ';';
	}

	if ( $a['hover'] ) $classes .= ' oz-fs-banner--hover-' . esc_attr( $a['hover'] );
	if ( $a['visibility'] === 'hide-for-medium' ) $classes .= ' oz-fs-col--hide-mobile';

	$inner = do_shortcode( $content );

	if ( $a['link'] ) {
		$label = wp_strip_all_tags( $inner );
		if ( ! $label ) {
			$slug  = basename( trim( parse_url( $a['link'], PHP_URL_PATH ), '/' ) );
			$label = ucfirst( str_replace( '-', ' ', $slug ) );
		}
		/* Link overlay is an empty sibling, NOT wrapping content.
		   Wrapping causes nested <a> when text_box contains [button],
		   triggering the adoption agency algorithm which duplicates elements. */
		return '<div class="' . esc_attr( $classes ) . '" style="' . esc_attr( $style ) . '"><a href="' . esc_url( $a['link'] ) . '" class="oz-fs-banner__link" aria-label="' . esc_attr( $label ) . '"></a>' . $inner . '</div>';
	}

	return '<div class="' . esc_attr( $classes ) . '" style="' . esc_attr( $style ) . '">' . $inner . '</div>';
}
add_shortcode( 'ux_banner', 'oz_fs_ux_banner' );

/* ── Text Box (inside banners) ── */
function oz_fs_text_box( $atts, $content = '' ) {
	$a = shortcode_atts( [
		'text_align'  => 'center',
		'width'       => '',
		'width__sm'   => '',
		'padding'     => '',
		'position_x'  => '50',
		'position_y'  => '50',
		'text_color'  => '',
		'bg'          => '',
		'radius'      => '',
		'depth'       => '',
		'animate'     => '',
	], $atts );

	$style = '';
	if ( $a['padding'] )    $style .= 'padding:' . esc_attr( $a['padding'] ) . ';';
	if ( $a['width'] )      $style .= 'max-width:' . esc_attr( $a['width'] ) . '%;';
	if ( $a['bg'] )         $style .= 'background:' . esc_attr( $a['bg'] ) . ';';
	if ( $a['radius'] )     $style .= 'border-radius:' . esc_attr( $a['radius'] ) . 'px;';

	$style .= 'position:absolute;';
	$style .= 'left:' . esc_attr( $a['position_x'] ) . '%;';
	$style .= 'top:' . esc_attr( $a['position_y'] ) . '%;';
	$style .= 'transform:translate(-' . esc_attr( $a['position_x'] ) . '%,-' . esc_attr( $a['position_y'] ) . '%);';

	$classes = 'oz-fs-text-box oz-fs-text-box--' . esc_attr( $a['text_align'] );

	/* "dark" is a Flatsome keyword, not a CSS color — map to a modifier class */
	if ( $a['text_color'] === 'dark' ) {
		$classes .= ' oz-fs-text-box--dark';
	} elseif ( $a['text_color'] ) {
		$style .= 'color:' . esc_attr( $a['text_color'] ) . ';';
	}

	if ( $a['depth'] ) $classes .= ' oz-fs-text-box--depth';

	return '<div class="' . esc_attr( $classes ) . '" style="' . esc_attr( $style ) . '">' . do_shortcode( $content ) . '</div>';
}
add_shortcode( 'text_box', 'oz_fs_text_box' );

/* ── Text Wrapper ── */
function oz_fs_ux_text( $atts, $content = '' ) {
	$a = shortcode_atts( [
		'font_size'    => '',
		'font_size__sm'=> '',
		'line_height'  => '',
		'text_align'   => '',
		'text_color'   => '',
	], $atts );

	$style = '';
	if ( $a['font_size'] && $a['font_size'] !== '1' )   $style .= 'font-size:' . esc_attr( $a['font_size'] ) . ';';
	if ( $a['line_height'] ) $style .= 'line-height:' . esc_attr( $a['line_height'] ) . ';';
	if ( $a['text_align'] )  $style .= 'text-align:' . esc_attr( $a['text_align'] ) . ';';
	if ( $a['text_color'] )  $style .= 'color:' . esc_attr( $a['text_color'] ) . ';';

	return '<div class="oz-fs-text" style="' . esc_attr( $style ) . '">' . do_shortcode( $content ) . '</div>';
}
add_shortcode( 'ux_text', 'oz_fs_ux_text' );

/* ── Button ── */
function oz_fs_button( $atts, $content = '' ) {
	$a = shortcode_atts( [
		'text'         => '',
		'link'         => '#',
		'style'        => 'primary',
		'color'        => '',
		'size'         => '',
		'radius'       => '',
		'target'       => '_self',
		'padding'      => '',
		'icon'         => '',
		'icon_reveal'  => '',
		'letter_case'  => '',
		'expand'       => '',
	], $atts );

	$text = $a['text'] ?: wp_strip_all_tags( $content );
	$classes = 'oz-fs-button';

	if ( $a['style'] === 'link' || $a['style'] === 'underline' ) {
		$classes .= ' oz-fs-button--link';
	} elseif ( $a['style'] === 'outline' ) {
		$classes .= ' oz-fs-button--outline';
	} elseif ( $a['color'] === 'alert' || $a['color'] === 'success' ) {
		$classes .= ' oz-fs-button--cta';
	} else {
		$classes .= ' oz-fs-button--primary';
	}

	if ( $a['color'] === 'white' ) $classes .= ' oz-fs-button--white';
	if ( $a['color'] === 'secondary' ) $classes .= ' oz-fs-button--secondary';

	/* Arrow icon after text */
	$icon_html = '';
	if ( $a['icon'] === 'icon-angle-right' ) {
		$reveal = $a['icon_reveal'] ? ' oz-fs-button__icon--reveal' : '';
		$icon_html = ' <span class="oz-fs-button__icon' . $reveal . '" aria-hidden="true">&#8250;</span>';
	}

	$style = '';
	if ( $a['radius'] ) $style .= 'border-radius:' . esc_attr( $a['radius'] ) . 'px;';
	if ( $a['padding'] ) $style .= 'padding:' . esc_attr( $a['padding'] ) . ';';
	if ( $a['letter_case'] === 'lowercase' ) $style .= 'text-transform:none;';

	return '<a href="' . esc_url( $a['link'] ) . '" class="' . esc_attr( $classes ) . '" style="' . esc_attr( $style ) . '" target="' . esc_attr( $a['target'] ) . '">' . esc_html( $text ) . $icon_html . '</a>';
}
add_shortcode( 'button', 'oz_fs_button' );

/* ── Image ── */
function oz_fs_ux_image( $atts, $content = '' ) {
	$a = shortcode_atts( [
		'id'          => '',
		'image_size'  => 'large',
		'width'       => '',
		'link'        => '',
		'target'      => '_self',
		'animate'     => '',
		'margin'      => '',
		'lightbox'    => '',
		'depth'       => '',
		'border'      => '',
		'border_color'=> '',
	], $atts );

	if ( ! $a['id'] ) return '';

	$img = wp_get_attachment_image( intval( $a['id'] ), $a['image_size'], false, [
		'class'   => 'oz-fs-image__img',
		'loading' => 'lazy',
	] );
	if ( ! $img ) return '';

	$style = '';
	if ( $a['width'] )  $style .= 'max-width:' . esc_attr( $a['width'] ) . '%;';
	if ( $a['margin'] ) $style .= 'margin:' . esc_attr( $a['margin'] ) . ';';

	$out = '<div class="oz-fs-image" style="' . esc_attr( $style ) . '">';

	if ( $a['link'] ) {
		$out .= '<a href="' . esc_url( $a['link'] ) . '" target="' . esc_attr( $a['target'] ) . '">' . $img . '</a>';
	} else {
		$out .= $img;
	}

	$out .= '</div>';
	return $out;
}
add_shortcode( 'ux_image', 'oz_fs_ux_image' );

/* ── Gap (spacer) ── */
function oz_fs_gap( $atts ) {
	$a = shortcode_atts( [ 'height' => '30px' ], $atts );
	return '<div class="oz-fs-gap" style="height:' . esc_attr( $a['height'] ) . ';"></div>';
}
add_shortcode( 'gap', 'oz_fs_gap' );

/* ── Divider ── */
function oz_fs_divider( $atts ) {
	$a = shortcode_atts( [ 'margin' => '', 'color' => '', 'width' => '' ], $atts );
	$style = '';
	if ( $a['margin'] ) $style .= 'margin:' . esc_attr( $a['margin'] ) . ';';
	if ( $a['color'] )  $style .= 'border-color:' . esc_attr( $a['color'] ) . ';';
	if ( $a['width'] )  $style .= 'max-width:' . esc_attr( $a['width'] ) . '%;';
	return '<hr class="oz-fs-divider" style="' . esc_attr( $style ) . '">';
}
add_shortcode( 'divider', 'oz_fs_divider' );

/* ── Title ── */
function oz_fs_title( $atts, $content = '' ) {
	$a = shortcode_atts( [
		'style' => 'center',
		'size'  => '',
		'color' => '',
		'margin_top' => '',
	], $atts );

	$style = 'text-align:' . esc_attr( $a['style'] ) . ';';
	if ( $a['margin_top'] ) $style .= 'margin-top:' . esc_attr( $a['margin_top'] ) . ';';
	if ( $a['color'] )      $style .= 'color:' . esc_attr( $a['color'] ) . ';';

	return '<div class="oz-fs-title" style="' . esc_attr( $style ) . '">' . do_shortcode( $content ) . '</div>';
}
add_shortcode( 'title', 'oz_fs_title' );

/* ── Accordion ── */
function oz_fs_accordion( $atts, $content = '' ) {
	$a = shortcode_atts( [ 'title' => '', 'open' => '' ], $atts );

	/* Accordion wraps individual [accordion] items which each have a title */
	if ( $a['title'] ) {
		$open  = ( $a['open'] === 'true' ) ? ' is-open' : '';
		$out   = '<div class="oz-fs-accordion__item' . $open . '">';
		$out  .= '<div class="oz-fs-accordion__title">' . esc_html( $a['title'] ) . '</div>';
		$out  .= '<div class="oz-fs-accordion__content">' . do_shortcode( $content ) . '</div>';
		$out  .= '</div>';
		return $out;
	}

	return '<div class="oz-fs-accordion">' . do_shortcode( $content ) . '</div>';
}
add_shortcode( 'accordion', 'oz_fs_accordion' );

/* ── Scroll To (anchor) ── */
function oz_fs_scroll_to( $atts ) {
	$a = shortcode_atts( [ 'id' => '', 'link' => '', 'title' => '' ], $atts );
	$anchor = $a['id'] ?: sanitize_title( $a['title'] );
	return '<div id="' . esc_attr( $anchor ) . '"></div>';
}
add_shortcode( 'scroll_to', 'oz_fs_scroll_to' );

/* ── Image Box ── */
function oz_fs_ux_image_box( $atts, $content = '' ) {
	$a = shortcode_atts( [
		'image' => '',
		'title' => '',
		'link'  => '',
	], $atts );

	$out = '<div class="oz-fs-image-box">';

	if ( $a['image'] ) {
		$img = wp_get_attachment_image( intval( $a['image'] ), 'medium', false, [ 'class' => 'oz-fs-image-box__img', 'loading' => 'lazy' ] );
		if ( $a['link'] ) {
			$out .= '<a href="' . esc_url( $a['link'] ) . '">' . $img . '</a>';
		} else {
			$out .= $img;
		}
	}

	if ( $a['title'] ) {
		$out .= '<div class="oz-fs-image-box__title">' . esc_html( $a['title'] ) . '</div>';
	}

	if ( $content ) {
		$out .= '<div class="oz-fs-image-box__text">' . do_shortcode( $content ) . '</div>';
	}

	$out .= '</div>';
	return $out;
}
add_shortcode( 'ux_image_box', 'oz_fs_ux_image_box' );

/* ── Stack (vertical stack of items) ── */
function oz_fs_ux_stack( $atts, $content = '' ) {
	$a = shortcode_atts( [ 'gap' => '10', 'align' => '' ], $atts );
	$style = 'display:flex;flex-direction:column;gap:' . esc_attr( $a['gap'] ) . 'px;';
	if ( $a['align'] ) $style .= 'align-items:' . esc_attr( $a['align'] ) . ';';
	return '<div class="oz-fs-stack" style="' . esc_attr( $style ) . '">' . do_shortcode( $content ) . '</div>';
}
add_shortcode( 'ux_stack', 'oz_fs_ux_stack' );

/* ── HTML passthrough ── */
function oz_fs_ux_html( $atts, $content = '' ) {
	return '<div class="oz-fs-html">' . do_shortcode( $content ) . '</div>';
}
add_shortcode( 'ux_html', 'oz_fs_ux_html' );

/* ── Video ── */
function oz_fs_ux_video( $atts ) {
	$a = shortcode_atts( [ 'url' => '' ], $atts );
	if ( ! $a['url'] ) return '';
	return '<div class="oz-fs-video">' . wp_oembed_get( $a['url'] ) . '</div>';
}
add_shortcode( 'ux_video', 'oz_fs_ux_video' );

/* ── Products ── */
function oz_fs_ux_products( $atts ) {
	$a = shortcode_atts( [
		'products' => '8',
		'columns'  => '4',
		'cat'      => '',
		'ids'      => '',
		'orderby'  => 'date',
		'order'    => 'DESC',
	], $atts );

	$wc_atts = 'limit="' . intval( $a['products'] ) . '" columns="' . intval( $a['columns'] ) . '"';
	if ( $a['cat'] ) $wc_atts .= ' category="' . esc_attr( $a['cat'] ) . '"';
	if ( $a['ids'] ) $wc_atts .= ' ids="' . esc_attr( $a['ids'] ) . '"';
	$wc_atts .= ' orderby="' . esc_attr( $a['orderby'] ) . '" order="' . esc_attr( $a['order'] ) . '"';

	return do_shortcode( '[products ' . $wc_atts . ']' );
}
add_shortcode( 'ux_products', 'oz_fs_ux_products' );

/* ── Page Header ── */
function oz_fs_page_header( $atts, $content = '' ) {
	$a = shortcode_atts( [
		'height'   => '300px',
		'bg'       => '',
		'bg_color' => '',
		'bg_pos'   => 'center',
	], $atts );

	$style = 'min-height:' . esc_attr( $a['height'] ) . ';display:flex;align-items:center;justify-content:center;';
	if ( $a['bg_color'] ) $style .= 'background-color:' . esc_attr( $a['bg_color'] ) . ';';
	if ( $a['bg'] ) {
		$img_url = wp_get_attachment_image_url( intval( $a['bg'] ), 'large' );
		if ( $img_url ) {
			$style .= 'background-image:url(' . esc_url( $img_url ) . ');background-size:cover;background-position:' . esc_attr( $a['bg_pos'] ) . ';';
		}
	}

	return '<div class="oz-fs-page-header" style="' . esc_attr( $style ) . '">' . do_shortcode( $content ) . '</div>';
}
add_shortcode( 'page_header', 'oz_fs_page_header' );

/* ── Blog Posts ── */
function oz_fs_blog_posts( $atts ) {
	$a = shortcode_atts( [
		'posts' => '4',
		'cat'   => '',
	], $atts );

	$args = [
		'post_type'      => 'post',
		'posts_per_page' => intval( $a['posts'] ),
		'post_status'    => 'publish',
	];
	if ( $a['cat'] ) {
		$args['category_name'] = $a['cat'];
	}

	$posts = get_posts( $args );
	if ( empty( $posts ) ) return '';

	$out = '<div class="oz-post-grid">';
	foreach ( $posts as $post ) {
		$out .= '<article class="oz-card">';
		$thumb = get_the_post_thumbnail( $post, 'medium_large' );
		if ( $thumb ) {
			$out .= '<div class="oz-card__image"><a href="' . esc_url( get_permalink( $post ) ) . '">' . $thumb . '</a></div>';
		}
		$out .= '<div class="oz-card__body">';
		$out .= '<h3 class="oz-card__title"><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3>';
		$out .= '<div class="oz-card__excerpt">' . wp_trim_words( $post->post_content, 20, '...' ) . '</div>';
		$out .= '</div></article>';
	}
	$out .= '</div>';
	wp_reset_postdata();

	return $out;
}
add_shortcode( 'blog_posts', 'oz_fs_blog_posts' );

/* ── Gallery ── */
function oz_fs_ux_gallery( $atts ) {
	$a = shortcode_atts( [ 'ids' => '', 'columns' => '4' ], $atts );
	if ( ! $a['ids'] ) return '';

	return do_shortcode( '[gallery ids="' . esc_attr( $a['ids'] ) . '" columns="' . intval( $a['columns'] ) . '" link="none"]' );
}
add_shortcode( 'ux_gallery', 'oz_fs_ux_gallery' );

/* ── Banner Grid ── */
function oz_fs_ux_banner_grid( $atts, $content = '' ) {
	return '<div class="oz-fs-row">' . do_shortcode( $content ) . '</div>';
}
add_shortcode( 'ux_banner_grid', 'oz_fs_ux_banner_grid' );

/* ── Col Grid (used rarely) ── */
function oz_fs_col_grid( $atts, $content = '' ) {
	return '<div class="oz-fs-col" style="flex:1 1 0%;">' . do_shortcode( $content ) . '</div>';
}
add_shortcode( 'col_grid', 'oz_fs_col_grid' );

/* All static Flatsome-stub CSS now lives in oz-blocks.css.
   Removed oz_fs_responsive_css() to eliminate duplicate/conflicting
   inline rules (overlay ::before, mobile columns, alignment helpers). */

/* ── Accordion JS ── */
function oz_fs_accordion_js() {
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('.oz-fs-accordion__title').forEach(function(el) {
			el.addEventListener('click', function() {
				this.parentElement.classList.toggle('is-open');
			});
		});
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'oz_fs_accordion_js', 99 );
