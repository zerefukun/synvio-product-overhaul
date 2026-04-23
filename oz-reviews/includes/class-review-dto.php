<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Review_DTO — canonical review shape, source-agnostic.
 *
 * Every source (wp_comments product reviews, oz_shop_review CPT, Google sync,
 * historical scrape import) is normalized into this array shape before
 * rendering. Adding a new source = implementing from_* into this shape.
 *
 * Shape:
 *   id            string    unique per source ("google:<sha1>", "product:<comment_id>", "shop:<post_id>", "trustindex:<reviewId>")
 *   source        string    'google' | 'product_native' | 'shop_native' | 'import' | 'trustindex'
 *   external_id   string    for dedup on google/import sources (sha1 of author+publish_time)
 *   rating        int       1..5
 *   author_name   string
 *   author_photo  string    URL or ''
 *   author_city   string
 *   date_iso      string    ISO 8601
 *   date_label    string    Dutch human-formatted
 *   title         string    optional (native reviews)
 *   body          string    plain text
 *   photos        string[]  URLs
 *   verified      bool
 *   product_id    int       0 if not product-specific
 *   staff_reply   string    optional
 *   source_label  string    UI label: "Google review" | "Geverifieerde aanschaf" | "Shop review"
 */
class Review_DTO {

	public const SOURCE_GOOGLE         = 'google';
	public const SOURCE_IMPORT         = 'import';
	public const SOURCE_TRUSTINDEX     = 'trustindex';
	public const SOURCE_PRODUCT_NATIVE = 'product_native';
	public const SOURCE_SHOP_NATIVE    = 'shop_native';

	/** Build canonical DTO from a raw associative array (already normalized fields). */
	public static function from_array( array $in ) : array {
		$source = sanitize_key( $in['source'] ?? 'import' );
		return array(
			'id'           => (string) ( $in['id'] ?? '' ),
			'source'       => $source,
			'external_id'  => (string) ( $in['external_id'] ?? '' ),
			'rating'       => max( 1, min( 5, (int) ( $in['rating'] ?? 5 ) ) ),
			// Strip invisible Unicode format/control chars (RTL overrides, zero-width,
			// bidi markers) — these render as invisible glyphs in the admin queue and
			// let attackers impersonate other reviewers or hide characters.
			'author_name'  => preg_replace( '/[\x{0000}-\x{001F}\x{007F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}]+/u', '', wp_strip_all_tags( (string) ( $in['author_name'] ?? 'Anoniem' ) ) ),
			'author_photo' => esc_url_raw( (string) ( $in['author_photo'] ?? '' ) ),
			'author_city'  => sanitize_text_field( (string) ( $in['author_city'] ?? '' ) ),
			'date_iso'     => (string) ( $in['date_iso'] ?? '' ),
			'date_label'   => self::format_dutch_date( (string) ( $in['date_iso'] ?? '' ), (string) ( $in['date_label'] ?? '' ) ),
			'title'        => wp_strip_all_tags( (string) ( $in['title'] ?? '' ) ),
			'body'         => wp_strip_all_tags( (string) ( $in['body'] ?? '' ) ),
			'photos'       => array_values( array_filter( array_map( 'esc_url_raw', (array) ( $in['photos'] ?? array() ) ) ) ),
			'verified'     => (bool) ( $in['verified'] ?? false ),
			'product_id'   => (int) ( $in['product_id'] ?? 0 ),
			'staff_reply'  => wp_strip_all_tags( (string) ( $in['staff_reply'] ?? '' ) ),
			'source_label' => self::source_label( $source, (bool) ( $in['verified'] ?? false ) ),
		);
	}

	/** Product review comment (wp_comments, comment_type=review) → DTO. */
	public static function from_comment( \WP_Comment $c ) : array {
		$rating   = (int) get_comment_meta( $c->comment_ID, 'rating', true );
		$verified = (bool) get_comment_meta( $c->comment_ID, 'verified', true );
		$title    = (string) get_comment_meta( $c->comment_ID, '_oz_title', true );
		$photos   = (array) get_comment_meta( $c->comment_ID, '_oz_photos', true );
		$city     = (string) get_comment_meta( $c->comment_ID, '_oz_city', true );

		return self::from_array( array(
			'id'          => 'product:' . $c->comment_ID,
			'source'      => 'product_native',
			'rating'      => $rating ?: 5,
			'author_name' => $c->comment_author,
			'author_city' => $city,
			'date_iso'    => mysql_to_rfc3339( $c->comment_date_gmt ),
			'title'       => $title,
			'body'        => $c->comment_content,
			'photos'      => array_values( array_filter( $photos ) ),
			'verified'    => $verified,
			'product_id'  => (int) $c->comment_post_ID,
		) );
	}

	/** oz_shop_review CPT post → DTO. Covers scraped imports + Google sync + native shop reviews. */
	public static function from_post( \WP_Post $p ) : array {
		$meta = function ( $k ) use ( $p ) {
			return get_post_meta( $p->ID, '_oz_' . $k, true );
		};

		$source = (string) $meta( 'source' ) ?: 'shop_native';

		return self::from_array( array(
			'id'           => $source . ':' . ( $meta( 'external_id' ) ?: $p->ID ),
			'source'       => $source,
			'external_id'  => (string) $meta( 'external_id' ),
			'rating'       => (int) $meta( 'rating' ),
			'author_name'  => (string) $meta( 'author_name' ) ?: $p->post_title,
			'author_photo' => (string) $meta( 'author_photo' ),
			'author_city'  => (string) $meta( 'author_city' ),
			'date_iso'     => (string) $meta( 'publish_time' ) ?: mysql_to_rfc3339( $p->post_date_gmt ),
			'body'         => (string) $p->post_content,
			'photos'       => (array) $meta( 'photos' ),
			'verified'     => (bool) $meta( 'verified' ),
			'staff_reply'  => (string) $meta( 'staff_reply' ),
		) );
	}

	/**
	 * Trustindex DB row (OTBgD_trustindex_google_reviews) → DTO.
	 *
	 * Trustindex stores Google reviews it scraped into its own table. The row
	 * is a plain stdClass/array with columns: id, user, user_photo, text,
	 * rating, date (Y-m-d), reviewId (Google's stable review id), reply.
	 *
	 * We treat these as source='trustindex' (vs 'google' which was the old
	 * Places API direct path). The reviewId column is Google's canonical id
	 * and is our natural dedup key — no sha1 fingerprint needed.
	 */
	public static function from_trustindex_row( $row ) : array {
		$row = (array) $row;
		$review_id = (string) ( $row['reviewId'] ?? '' );
		$date      = (string) ( $row['date'] ?? '' );
		// Trustindex stores date as Y-m-d. Upgrade to ISO 8601 at local noon so
		// ordering + Dutch formatting both work without timezone surprises.
		$date_iso  = $date !== '' ? $date . 'T12:00:00+00:00' : '';

		return self::from_array( array(
			'id'           => self::SOURCE_TRUSTINDEX . ':' . $review_id,
			'source'       => self::SOURCE_TRUSTINDEX,
			'external_id'  => $review_id,
			'rating'       => (int) round( (float) ( $row['rating'] ?? 5 ) ),
			'author_name'  => (string) ( $row['user'] ?? 'Anoniem' ),
			'author_photo' => (string) ( $row['user_photo'] ?? '' ),
			'date_iso'     => $date_iso,
			'body'         => (string) ( $row['text'] ?? '' ),
			'staff_reply'  => (string) ( $row['reply'] ?? '' ),
			'verified'     => false,
		) );
	}

	private static function source_label( string $source, bool $verified ) : string {
		switch ( $source ) {
			case 'google':
			case 'import':
			case 'trustindex':
				return 'Google review';
			case 'product_native':
				return $verified ? 'Geverifieerde aanschaf' : 'Webshop review';
			case 'shop_native':
				return 'Webshop review';
		}
		return 'Review';
	}

	/**
	 * Best-effort Dutch formatting. If an explicit date_label is supplied we
	 * trust it; otherwise derive from ISO date.
	 */
	private static function format_dutch_date( string $iso, string $prefilled ) : string {
		if ( $prefilled !== '' ) {
			return $prefilled;
		}
		if ( $iso === '' ) {
			return '';
		}
		$ts = strtotime( $iso );
		if ( ! $ts ) {
			return '';
		}

		$days = array( 'Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag' );
		$mons = array( '', 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december' );
		return sprintf( '%s %d %s %d', $days[ (int) gmdate( 'w', $ts ) ], (int) gmdate( 'j', $ts ), $mons[ (int) gmdate( 'n', $ts ) ], (int) gmdate( 'Y', $ts ) );
	}

	/**
	 * Deterministic dedup key for external sources (Google, scrape import).
	 * Combines author + normalized publish time — same reviewer + same minute = same review.
	 */
	public static function external_id( string $author_name, string $date_iso ) : string {
		$ts = strtotime( $date_iso );
		$bucket = $ts ? gmdate( 'Y-m-d\TH:i', $ts ) : $date_iso; // minute resolution
		return sha1( mb_strtolower( trim( $author_name ) ) . '|' . $bucket );
	}
}
