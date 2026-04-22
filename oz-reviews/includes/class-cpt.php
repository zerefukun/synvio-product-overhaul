<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * oz_shop_review CPT — shop-wide reviews and Google-imported reviews.
 * Product-specific reviews stay in wp_comments (WooCommerce native) and
 * gain extra fields via Meta::register().
 */
class CPT {

	public const CPT = 'oz_shop_review';

	public static function register() : void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	public static function register_post_type() : void {
		register_post_type(
			self::CPT,
			array(
				'label'           => 'Shop reviews',
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => true,
				'menu_icon'       => 'dashicons-star-filled',
				'menu_position'   => 57,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'supports'        => array( 'title', 'editor', 'custom-fields' ),
			)
		);

		self::register_meta();
	}

	private static function register_meta() : void {
		$fields = array(
			'rating'       => 'integer',
			'source'       => 'string',   // 'google' | 'import' | 'shop_native'
			'external_id'  => 'string',   // sha1(author + publish_time) for google/import dedup
			'verified'     => 'boolean',
			'photos'       => 'array',
			'project_type' => 'string',
			'color_used'   => 'string',
			'm2'           => 'number',
			'author_name'  => 'string',
			'author_city'  => 'string',
			'author_photo' => 'string',
			'publish_time' => 'string',   // ISO 8601
			'staff_reply'  => 'string',
		);

		foreach ( $fields as $key => $type ) {
			register_post_meta(
				self::CPT,
				'_oz_' . $key,
				array(
					'single'       => true,
					'type'         => $type === 'array' ? 'array' : $type,
					'show_in_rest' => $type === 'array'
						? array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) )
						: true,
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Find an existing CPT post by external_id (for dedup during Google sync / import).
	 * Returns post ID or 0.
	 */
	public static function find_by_external_id( string $external_id ) : int {
		if ( $external_id === '' ) {
			return 0;
		}
		$q = new \WP_Query(
			array(
				'post_type'      => self::CPT,
				'post_status'    => array( 'publish', 'pending', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array( 'key' => '_oz_external_id', 'value' => $external_id ),
				),
			)
		);
		return $q->posts ? (int) $q->posts[0] : 0;
	}

	/**
	 * Upsert a review from external source (Google, scrape import).
	 * Dedup by external_id. Returns post ID (0 on failure).
	 *
	 * @param array $dto Canonical DTO (see Review_DTO).
	 * @param string $post_status 'publish' for imported/public reviews, 'pending' for anything needing approval.
	 */
	public static function upsert_external( array $dto, string $post_status = 'publish' ) : int {
		if ( empty( $dto['external_id'] ) ) {
			return 0;
		}
		$existing = self::find_by_external_id( (string) $dto['external_id'] );

		$postarr = array(
			'post_type'    => self::CPT,
			'post_status'  => $post_status,
			'post_title'   => (string) $dto['author_name'],
			'post_content' => (string) $dto['body'],
		);
		if ( ! empty( $dto['date_iso'] ) ) {
			$ts = strtotime( (string) $dto['date_iso'] );
			if ( $ts ) {
				$postarr['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $ts );
				$postarr['post_date']     = get_date_from_gmt( $postarr['post_date_gmt'] );
			}
		}

		if ( $existing ) {
			$postarr['ID'] = $existing;
			$id = wp_update_post( $postarr, true );
		} else {
			$id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}

		$meta_map = array(
			'rating'       => (int) $dto['rating'],
			'source'       => (string) $dto['source'],
			'external_id'  => (string) $dto['external_id'],
			'author_name'  => (string) $dto['author_name'],
			'author_photo' => (string) $dto['author_photo'],
			'author_city'  => (string) $dto['author_city'],
			'publish_time' => (string) $dto['date_iso'],
			'verified'     => ! empty( $dto['verified'] ) ? 1 : 0,
		);
		if ( ! empty( $dto['photos'] ) ) {
			$meta_map['photos'] = array_values( $dto['photos'] );
		}
		if ( ! empty( $dto['staff_reply'] ) ) {
			$meta_map['staff_reply'] = (string) $dto['staff_reply'];
		}
		foreach ( $meta_map as $k => $v ) {
			update_post_meta( $id, '_oz_' . $k, $v );
		}

		return (int) $id;
	}
}
