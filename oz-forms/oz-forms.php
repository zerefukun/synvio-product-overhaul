<?php
/**
 * Plugin Name: OZ Forms
 * Description: Custom form engine for Beton Ciré Webshop. Schema-driven forms, Turnstile spam protection, submissions stored as CPT, transactional emails via wp_mail. Replaces wpforms + contact-form-7.
 * Version: 0.1.0
 * Author: OzIS
 * Text Domain: oz-forms
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OZ_FORMS_VERSION', '0.1.0' );
define( 'OZ_FORMS_FILE', __FILE__ );
define( 'OZ_FORMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'OZ_FORMS_URL', plugin_dir_url( __FILE__ ) );

require_once OZ_FORMS_DIR . 'includes/class-schema-registry.php';
require_once OZ_FORMS_DIR . 'includes/class-turnstile.php';
require_once OZ_FORMS_DIR . 'includes/class-submission-cpt.php';
require_once OZ_FORMS_DIR . 'includes/class-mailer.php';
require_once OZ_FORMS_DIR . 'includes/class-form.php';
require_once OZ_FORMS_DIR . 'includes/class-rest.php';
require_once OZ_FORMS_DIR . 'includes/class-block.php';

add_action( 'plugins_loaded', function () {
	OZ_Forms\Schema_Registry::load_all();
	OZ_Forms\Submission_CPT::register();
	OZ_Forms\REST::register();
	OZ_Forms\Block::register();
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! OZ_Forms\Form::page_has_form() ) {
		return;
	}

	wp_enqueue_style(
		'oz-forms',
		OZ_FORMS_URL . 'assets/css/oz-forms.css',
		array(),
		filemtime( OZ_FORMS_DIR . 'assets/css/oz-forms.css' )
	);

	// Cloudflare Turnstile — explicit rendering, defer load.
	wp_enqueue_script(
		'cf-turnstile',
		'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
		array(),
		null,
		array( 'strategy' => 'defer', 'in_footer' => true )
	);

	wp_enqueue_script(
		'oz-forms',
		OZ_FORMS_URL . 'assets/js/oz-forms.js',
		array( 'cf-turnstile' ),
		filemtime( OZ_FORMS_DIR . 'assets/js/oz-forms.js' ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);

	wp_localize_script(
		'oz-forms',
		'OZ_FORMS_CFG',
		array(
			'rest'         => esc_url_raw( rest_url( 'oz/v1/submit' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'turnstileKey' => defined( 'OZ_TURNSTILE_SITE_KEY' ) ? OZ_TURNSTILE_SITE_KEY : '',
		)
	);
} );
