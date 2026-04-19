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
		add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
	}

	/**
	 * Shortcode fallback: [oz_form id="contact"]
	 * Lets us drop the form into Flatsome/legacy templates without touching the page wrapper.
	 */
	public static function register_shortcode() : void {
		add_shortcode( 'oz_form', function ( $atts ) {
			$atts = shortcode_atts( array( 'id' => '' ), $atts, 'oz_form' );
			$id   = sanitize_key( $atts['id'] );
			if ( $id === '' ) {
				return '';
			}
			return Form::render( $id );
		} );
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
