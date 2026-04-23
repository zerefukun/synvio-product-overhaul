<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema — emits JSON-LD for reviews.
 *
 * On /reviews/ hub: Organization + aggregateRating + review list.
 * On product pages: WC emits its own Product schema; we augment with review rating.
 */
class Schema {

	public static function register() : void {
		add_action( 'wp_head', array( __CLASS__, 'maybe_emit_hub_schema' ), 20 );
	}

	public static function maybe_emit_hub_schema() : void {
		if ( ! is_page( 'reviews' ) ) {
			return;
		}

		$agg = Trustindex_Sync::get_aggregate();
		if ( empty( $agg['rating_count'] ) ) {
			return;
		}

		$posts = get_posts( array(
			'post_type'      => CPT::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'meta_key'       => '_oz_publish_time',
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
		) );

		$reviews = array();
		foreach ( $posts as $p ) {
			$dto = Review_DTO::from_post( $p );
			if ( empty( $dto['rating'] ) || empty( $dto['body'] ) ) {
				continue;
			}
			$reviews[] = array(
				'@type'         => 'Review',
				'reviewRating'  => array(
					'@type'       => 'Rating',
					'ratingValue' => (int) $dto['rating'],
					'bestRating'  => 5,
				),
				'author'        => array(
					'@type' => 'Person',
					'name'  => $dto['author_name'],
				),
				'reviewBody'    => wp_strip_all_tags( $dto['body'] ),
				'datePublished' => ! empty( $dto['date_iso'] ) ? gmdate( 'Y-m-d', strtotime( $dto['date_iso'] ) ) : null,
			);
		}

		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'Organization',
			'name'            => 'Beton Ciré Webshop',
			'url'             => home_url( '/' ),
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => round( (float) $agg['rating'], 1 ),
				'reviewCount' => (int) $agg['rating_count'],
				'bestRating'  => 5,
			),
			'review'          => $reviews,
		);

		// JSON_HEX_TAG prevents a review body containing </script> from ever
		// breaking out of the LD-JSON block and injecting arbitrary HTML.
		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . "</script>\n";
	}
}
