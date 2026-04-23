<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode — [oz_reviews] renders a grid of reviews.
 *
 * Two data sources:
 *   source="live-trustindex"  Reads directly from OTBgD_trustindex_google_reviews
 *                             (Trustindex's cached Google reviews — live, not snapshotted).
 *                             Default: homepage + PDP + ruimte pages show
 *                             "nothing more, nothing less" than what Trustindex has right now.
 *
 *   source="cpt" / other      Reads from the oz_shop_review CPT (accumulated archive —
 *                             used by /reviews/ hub to show the full history).
 *                             Pass a specific source like "google" / "import" / "trustindex"
 *                             to filter CPT rows to that lineage.
 *
 * Attributes:
 *   count      int     Number of reviews to show (default 12)
 *   source     string  'live-trustindex' | 'cpt' | '' (any) | specific CPT source
 *   min_rating int     Only show reviews at or above this rating (default 1)
 *   order      string  'date' (newest first) or 'rating' (best first)
 *   layout     string  'grid' or 'carousel'
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
				'source'     => 'live-trustindex',
				'min_rating' => 1,
				'order'      => 'date',
				'layout'     => 'grid',
			),
			$atts,
			'oz_reviews'
		);

		$source = sanitize_key( (string) $atts['source'] );

		if ( $source === 'live-trustindex' ) {
			return self::render_live( $atts );
		}

		return self::render_from_cpt( $atts, $source );
	}

	private static function render_live( array $atts ) : string {
		$rows = Trustindex_Sync::fetch_rows();
		if ( empty( $rows ) ) {
			return '';
		}

		$min_rating = max( 1, (int) $atts['min_rating'] );
		$count      = max( 1, (int) $atts['count'] );
		$order      = (string) $atts['order'];
		$needs_full_scan = ( $order === 'rating' );

		$dtos = array();
		foreach ( $rows as $row ) {
			$dto = Review_DTO::from_trustindex_row( $row );
			if ( $min_rating > 1 && $dto['rating'] < $min_rating ) {
				continue;
			}
			$dtos[] = $dto;
			if ( ! $needs_full_scan && count( $dtos ) >= $count ) {
				break;
			}
		}

		if ( $needs_full_scan ) {
			usort( $dtos, function ( $a, $b ) {
				$cmp = $b['rating'] <=> $a['rating'];
				return $cmp !== 0 ? $cmp : strcmp( (string) $b['date_iso'], (string) $a['date_iso'] );
			} );
			$dtos = array_slice( $dtos, 0, $count );
		}

		if ( empty( $dtos ) ) {
			return '';
		}

		return $atts['layout'] === 'carousel'
			? Renderer::carousel( $dtos )
			: Renderer::grid( $dtos );
	}

	/**
	 * Archive renderer for the /reviews/ hub.
	 * $source_filter: '' | 'cpt' (any CPT row) | specific source like 'trustindex' | 'google' | 'import' | 'shop_native'.
	 */
	private static function render_from_cpt( array $atts, string $source_filter ) : string {
		$query_args = array(
			'post_type'      => CPT::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $atts['count'] ),
			'meta_key'       => '_oz_publish_time',
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
		);

		$meta_query = array();
		if ( $source_filter !== '' && $source_filter !== 'cpt' ) {
			$meta_query[] = array( 'key' => '_oz_source', 'value' => $source_filter );
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

		return $atts['layout'] === 'carousel'
			? Renderer::carousel( $dtos )
			: Renderer::grid( $dtos );
	}

	public static function render_summary( $atts ) : string {
		$agg = Trustindex_Sync::get_resolved_aggregate( 0.0, 0 );
		return Renderer::summary( (float) $agg['rating'], (int) $agg['rating_count'] );
	}
}
