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
			'source'       => 'string',
			'verified'     => 'boolean',
			'photos'       => 'array',
			'project_type' => 'string',
			'color_used'   => 'string',
			'm2'           => 'number',
			'author_name'  => 'string',
			'author_city'  => 'string',
			'external_id'  => 'string',
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
}
