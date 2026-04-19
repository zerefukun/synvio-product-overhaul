<?php
namespace OZ_Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema_Registry — loads form schemas from /forms/*.php into a static map.
 *
 * Each schema file returns an associative array describing a single form:
 *   - id            : slug used in the block + REST payload (e.g. "contact")
 *   - title         : human label (admin + emails)
 *   - fields        : ordered field map (name => spec)
 *   - notify_to     : recipient(s) for the site notification email
 *   - reply_subject : auto-reply subject sent to the submitter
 *   - reply_body    : auto-reply body (string or callable taking the data)
 *   - subject       : site notification subject (string or callable)
 */
class Schema_Registry {

	/** @var array<string, array> */
	private static $schemas = array();

	public static function load_all() : void {
		$dir = OZ_FORMS_DIR . 'forms';
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( glob( $dir . '/*.php' ) as $file ) {
			$schema = include $file;
			if ( is_array( $schema ) && ! empty( $schema['id'] ) ) {
				self::$schemas[ $schema['id'] ] = $schema;
			}
		}
	}

	public static function get( string $id ) : ?array {
		return self::$schemas[ $id ] ?? null;
	}

	/** @return array<int, string> */
	public static function ids() : array {
		return array_keys( self::$schemas );
	}

	/** @return array<string, array> */
	public static function all() : array {
		return self::$schemas;
	}
}
