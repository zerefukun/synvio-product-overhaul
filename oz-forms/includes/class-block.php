<?php
namespace OZ_Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block — registers the oz/form Gutenberg block.
 *
 * Editor: simple dropdown with available form IDs.
 * Frontend: render_callback delegates to Form::render().
 */
class Block {

	public static function register() : void {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	public static function register_block() : void {
		register_block_type( OZ_FORMS_DIR . 'block' );

		add_action( 'enqueue_block_editor_assets', function () {
			$schemas = array_map(
				function ( $id ) {
					$schema = Schema_Registry::get( $id );
					return array(
						'id'    => $id,
						'title' => $schema['title'] ?? $id,
					);
				},
				Schema_Registry::ids()
			);
			wp_add_inline_script(
				'oz-form-editor-script',
				'window.OZ_FORMS_SCHEMAS = ' . wp_json_encode( array_values( $schemas ) ) . ';',
				'before'
			);
		} );
	}
}
