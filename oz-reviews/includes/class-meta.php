<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extra meta on WooCommerce product reviews (wp_comments with comment_type=review).
 * Registers fields for photos, topics, verified-order link, project context.
 *
 * Why comment meta and not a parallel table: WooCommerce already handles rating,
 * threading, approval workflow, and admin UI for comments. Meta just extends it.
 */
class Meta {

	public static function register() : void {
		add_action( 'init', array( __CLASS__, 'register_comment_meta' ) );
	}

	public static function register_comment_meta() : void {
		$fields = array(
			'photos'           => 'array',
			'topics'           => 'array',
			'verified_order'   => 'integer',
			'project_type'     => 'string',
			'color_used'       => 'string',
			'm2'               => 'number',
			'helpful_count'    => 'integer',
		);

		foreach ( $fields as $key => $type ) {
			register_meta(
				'comment',
				'_oz_' . $key,
				array(
					'single'       => true,
					'type'         => $type === 'array' ? 'array' : $type,
					'show_in_rest' => $type === 'array'
						? array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) )
						: true,
					'auth_callback' => function ( $allowed, $meta_key, $object_id ) {
						return current_user_can( 'moderate_comments' );
					},
				)
			);
		}
	}
}
