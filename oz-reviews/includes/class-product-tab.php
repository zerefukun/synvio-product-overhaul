<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product_Tab — replaces WooCommerce's native review tab output with our
 * Renderer, so product reviews match homepage + /reviews/ styling.
 *
 * We do NOT remove WC's review meta (ratings still flow into wp_comments as
 * comment_type=review). We only replace the *display* on the product page.
 */
class Product_Tab {

	public static function register() : void {
		add_filter( 'woocommerce_product_tabs', array( __CLASS__, 'replace_reviews_tab' ), 98 );
	}

	public static function replace_reviews_tab( array $tabs ) : array {
		if ( ! isset( $tabs['reviews'] ) ) {
			return $tabs;
		}
		$tabs['reviews']['callback'] = array( __CLASS__, 'render_reviews_tab' );
		return $tabs;
	}

	public static function render_reviews_tab() : void {
		global $product;
		if ( ! $product ) {
			return;
		}
		$pid = (int) $product->get_id();

		$count = (int) $product->get_review_count();
		$avg   = (float) $product->get_average_rating();

		echo '<div class="oz-hp-reviews oz-hp-reviews--pdp">';

		if ( $count > 0 ) {
			$rating_str  = number_format_i18n( $avg, 1 );
			$stars_round = (int) round( $avg );
			$path        = '<path fill="currentColor" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>';
			$stars       = '';
			for ( $i = 1; $i <= 5; $i++ ) {
				$cls    = $i <= $stars_round ? 'oz-hp-star' : 'oz-hp-star oz-hp-star--empty';
				$stars .= '<svg class="' . $cls . '" viewBox="0 0 24 24" aria-hidden="true">' . $path . '</svg>';
			}
			echo '<div class="oz-hp-reviews-summary">'
				. '<div class="oz-hp-reviews-rating"><span class="oz-hp-reviews-big-num">' . esc_html( $rating_str ) . '</span>'
				. '<span class="oz-hp-reviews-rating-label">van de 5</span></div>'
				. '<div class="oz-hp-reviews-meta">'
				. '<div class="oz-hp-reviews-stars-row">' . $stars . '</div>'
				. '<div class="oz-hp-reviews-count">Gebaseerd op <strong>' . esc_html( $count ) . '</strong> productreviews</div>'
				. '</div></div>';
		}

		$comments = get_comments( array(
			'post_id' => $pid,
			'status'  => 'approve',
			'type'    => 'review',
			'number'  => 24,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		) );

		if ( empty( $comments ) ) {
			echo '<p class="oz-hp-reviews-empty">Er zijn nog geen reviews voor dit product. <a href="#respond">Schrijf de eerste review</a>.</p>';
		} else {
			$dtos = array();
			foreach ( $comments as $c ) {
				$dtos[] = Review_DTO::from_comment( $c );
			}
			echo Renderer::grid( $dtos );
		}

		if ( comments_open( $pid ) ) {
			echo '<div class="oz-hp-reviews-form">';
			comment_form(
				array(
					'title_reply' => esc_html__( 'Schrijf een review', 'oz-reviews' ),
					'label_submit' => esc_html__( 'Review plaatsen', 'oz-reviews' ),
				)
			);
			echo '</div>';
		}

		echo '</div>';
	}
}
