<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderer — one card markup for every source.
 *
 * Intentionally mirrors the existing .oz-hp-review CSS in
 * oz-theme/css/oz-reviews.css so native, Google, and imported
 * reviews are visually indistinguishable.
 */
class Renderer {

	/** Render one review card from a DTO. */
	public static function card( array $dto ) : string {
		$stars_html = self::stars( (int) $dto['rating'] );

		$avatar = '';
		if ( ! empty( $dto['author_photo'] ) ) {
			$avatar = '<span class="oz-hp-review-avatar oz-hp-review-avatar--img"><img src="' . esc_url( $dto['author_photo'] ) . '" alt="" loading="lazy" width="40" height="40"></span>';
		} else {
			$initial = mb_strtoupper( mb_substr( (string) $dto['author_name'], 0, 1 ) );
			$avatar  = '<span class="oz-hp-review-avatar" aria-hidden="true">' . esc_html( $initial ) . '</span>';
		}

		$title_html = '';
		if ( ! empty( $dto['title'] ) ) {
			$title_html = '<h3 class="oz-hp-review-title">' . esc_html( $dto['title'] ) . '</h3>';
		}

		$photos_html = '';
		if ( ! empty( $dto['photos'] ) ) {
			$photos_html .= '<ul class="oz-hp-review-photos">';
			foreach ( $dto['photos'] as $url ) {
				$photos_html .= '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $url ) . '" alt="" loading="lazy"></a></li>';
			}
			$photos_html .= '</ul>';
		}

		$reply_html = '';
		if ( ! empty( $dto['staff_reply'] ) ) {
			$reply_html = '<div class="oz-hp-review-reply"><strong>Beton Ciré Webshop:</strong> ' . esc_html( $dto['staff_reply'] ) . '</div>';
		}

		$source_cls = 'oz-hp-review--' . esc_attr( $dto['source'] );

		return '<article class="oz-hp-review ' . $source_cls . '" data-source="' . esc_attr( $dto['source'] ) . '">'
			. '<header class="oz-hp-review-head">'
			. '<div class="oz-hp-review-stars" role="img" aria-label="' . esc_attr( $dto['rating'] ) . ' van de 5 sterren">' . $stars_html . '</div>'
			. '<span class="oz-hp-review-date">' . esc_html( $dto['date_label'] ) . '</span>'
			. '</header>'
			. $title_html
			. '<p class="oz-hp-review-body">' . esc_html( $dto['body'] ) . '</p>'
			. $photos_html
			. $reply_html
			. '<footer class="oz-hp-review-author">'
			. $avatar
			. '<span class="oz-hp-review-author-info">'
			. '<span class="oz-hp-review-author-name">' . esc_html( $dto['author_name'] ) . '</span>'
			. '<span class="oz-hp-review-verified">' . esc_html( $dto['source_label'] ) . '</span>'
			. '</span>'
			. '</footer>'
			. '</article>';
	}

	/** Render a grid of cards (used by homepage section and /reviews/ hub). */
	public static function grid( array $dtos, string $class = 'oz-hp-reviews-grid' ) : string {
		if ( empty( $dtos ) ) {
			return '';
		}
		$out = '<div class="' . esc_attr( $class ) . '">';
		foreach ( $dtos as $dto ) {
			$out .= self::card( $dto );
		}
		$out .= '</div>';
		return $out;
	}

	/** Render a Swiper-powered carousel. JS init lives in oz-theme/js/oz-reviews-carousel.js. */
	public static function carousel( array $dtos ) : string {
		if ( empty( $dtos ) ) {
			return '';
		}
		$slides = '';
		foreach ( $dtos as $dto ) {
			$slides .= '<div class="swiper-slide">' . self::card( $dto ) . '</div>';
		}
		$arrow_left  = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>';
		$arrow_right = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8.59 16.59L10 18l6-6-6-6-1.41 1.41L13.17 12z"/></svg>';

		return '<div class="oz-hp-reviews-carousel">'
			. '<div class="swiper oz-hp-reviews-swiper">'
			. '<div class="swiper-wrapper">' . $slides . '</div>'
			. '</div>'
			. '<button class="oz-hp-reviews-nav oz-hp-reviews-nav--prev" type="button" aria-label="Vorige reviews">' . $arrow_left . '</button>'
			. '<button class="oz-hp-reviews-nav oz-hp-reviews-nav--next" type="button" aria-label="Volgende reviews">' . $arrow_right . '</button>'
			. '</div>';
	}

	private static function stars( int $n ) : string {
		$n = max( 0, min( 5, $n ) );
		$path = '<path fill="currentColor" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>';
		$out  = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$cls  = $i <= $n ? 'oz-hp-star' : 'oz-hp-star oz-hp-star--empty';
			$out .= '<svg class="' . $cls . '" viewBox="0 0 24 24" aria-hidden="true">' . $path . '</svg>';
		}
		return $out;
	}
}

/**
 * Public helper — shortcut for templates that don't want to deal with namespaces.
 */
function render_review_card( array $dto ) : string {
	return Renderer::card( $dto );
}
