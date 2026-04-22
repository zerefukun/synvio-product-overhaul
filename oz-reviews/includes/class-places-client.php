<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Places_Client — thin wrapper around Google Places API (New) GetPlace endpoint.
 *
 * Docs: https://developers.google.com/maps/documentation/places/web-service/reference/rest/v1/places/get
 *
 * The New API returns up to 5 reviews in the `reviews[]` field. We pair this
 * with historical scrape imports to get full coverage (see CLI::import_google).
 *
 * Pricing note: one GetPlace call/day (Pro SKU) is well inside the free tier.
 */
class Places_Client {

	private const ENDPOINT = 'https://places.googleapis.com/v1/places/';

	/**
	 * Fetch place details including reviews.
	 *
	 * @return array{ok: bool, data?: array, error?: string, http?: int}
	 */
	public static function get_place( string $place_id, string $api_key, string $language = 'nl' ) : array {
		if ( $place_id === '' || $api_key === '' ) {
			return array( 'ok' => false, 'error' => 'Missing place_id or api_key' );
		}

		$url = self::ENDPOINT . rawurlencode( $place_id );

		$res = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'X-Goog-Api-Key'    => $api_key,
					'X-Goog-FieldMask'  => 'id,displayName,rating,userRatingCount,reviews',
					'Accept-Language'   => $language,
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'error' => $res->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		$data = json_decode( $body, true );

		if ( $code !== 200 || ! is_array( $data ) ) {
			$msg = is_array( $data ) && isset( $data['error']['message'] ) ? $data['error']['message'] : ( 'HTTP ' . $code );
			return array( 'ok' => false, 'error' => $msg, 'http' => $code );
		}

		return array( 'ok' => true, 'data' => $data, 'http' => $code );
	}

	/**
	 * Convert a Places API review object into our canonical DTO.
	 * Shape docs: https://developers.google.com/maps/documentation/places/web-service/reference/rest/v1/places#Review
	 */
	public static function review_to_dto( array $r ) : array {
		$author      = (string) ( $r['authorAttribution']['displayName'] ?? '' );
		$author_uri  = (string) ( $r['authorAttribution']['photoUri'] ?? '' );
		$publish     = (string) ( $r['publishTime'] ?? '' );
		$rating      = (int) ( $r['rating'] ?? 0 );
		$text        = (string) ( $r['text']['text'] ?? $r['originalText']['text'] ?? '' );

		return Review_DTO::from_array( array(
			'source'       => 'google',
			'external_id'  => Review_DTO::external_id( $author, $publish ),
			'rating'       => $rating,
			'author_name'  => $author,
			'author_photo' => $author_uri,
			'date_iso'     => $publish,
			'body'         => $text,
			'verified'     => false, // Google reviews aren't "verified buyers" in our sense
		) );
	}
}
