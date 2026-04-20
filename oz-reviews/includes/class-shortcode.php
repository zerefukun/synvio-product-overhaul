<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode — [oz_reviews] renders a grid of reviews from the CPT.
 *
 * Attributes:
 *   count  int     Number of reviews to show (default 12)
 *   source string  Filter by source: '' (any), 'google', 'import', 'shop_native'
 *   min_rating int Only show reviews at or above this rating (default 1)
 *   order  string  'date' (newest first) or 'rating' (best first)
 *
 * Also exposes [oz_reviews_summary] for the aggregate header.
 */
class Shortcode {

	public static function register() : void {
		add_shortcode( 'oz_reviews', array( __CLASS__, 'render_grid' ) );
		add_shortcode( 'oz_reviews_summary', array( __CLASS__, 'render_summary' ) );
	}

	public static function render_grid( $atts ) : string {
		$atts = shortcode_atts(
			array(
				'count'      => 12,
				'source'     => '',
				'min_rating' => 1,
				'order'      => 'date',
			),
			$atts,
			'oz_reviews'
		);

		$query_args = array(
			'post_type'      => CPT::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $atts['count'] ),
			'meta_key'       => '_oz_publish_time',
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
		);

		$meta_query = array();
		if ( ! empty( $atts['source'] ) ) {
			$meta_query[] = array( 'key' => '_oz_source', 'value' => sanitize_key( $atts['source'] ) );
		}
		$min_rating = (int) $atts['min_rating'];
		if ( $min_rating > 1 ) {
			$meta_query[] = array(
				'key'     => '_oz_rating',
				'value'   => $min_rating,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}
		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query;
		}

		if ( $atts['order'] === 'rating' ) {
			$query_args['meta_key'] = '_oz_rating';
			$query_args['orderby']  = array( 'meta_value_num' => 'DESC', 'date' => 'DESC' );
		}

		$posts = get_posts( $query_args );
		if ( empty( $posts ) ) {
			return '';
		}

		$dtos = array();
		foreach ( $posts as $p ) {
			$dtos[] = Review_DTO::from_post( $p );
		}

		return Renderer::grid( $dtos );
	}

	public static function render_summary( $atts ) : string {
		$agg = get_option( 'oz_reviews_google_aggregate' );
		if ( ! is_array( $agg ) || empty( $agg['rating_count'] ) ) {
			return '';
		}
		$rating_str  = number_format_i18n( (float) $agg['rating'], 1 );
		$count       = (int) $agg['rating_count'];
		$stars_round = (int) round( (float) $agg['rating'] );

		$stars = '';
		$path  = '<path fill="currentColor" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>';
		for ( $i = 1; $i <= 5; $i++ ) {
			$cls    = $i <= $stars_round ? 'oz-hp-star' : 'oz-hp-star oz-hp-star--empty';
			$stars .= '<svg class="' . $cls . '" viewBox="0 0 24 24" aria-hidden="true">' . $path . '</svg>';
		}

		return '<div class="oz-hp-reviews-summary">'
			. '<div class="oz-hp-reviews-rating">'
			. '<span class="oz-hp-reviews-big-num">' . esc_html( $rating_str ) . '</span>'
			. '<span class="oz-hp-reviews-rating-label">van de 5</span>'
			. '</div>'
			. '<div class="oz-hp-reviews-meta">'
			. '<div class="oz-hp-reviews-stars-row" role="img" aria-label="' . esc_attr( $rating_str ) . ' van de 5 sterren">' . $stars . '</div>'
			. '<div class="oz-hp-reviews-count">Gebaseerd op <strong>' . esc_html( $count ) . '+</strong> Google reviews</div>'
			. '</div>'
			. '</div>';
	}
}
