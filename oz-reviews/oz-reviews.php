<?php
/**
 * Plugin Name: OZ Reviews
 * Description: Custom review system for Beton Ciré Webshop. Native product reviews (extends WooCommerce wp_comments), shop-wide reviews (oz_shop_review CPT), email automation via Action Scheduler, Google Business Profile sync, moderation UI, schema.org markup. Replaces Trustindex.
 * Version: 0.1.0
 * Author: OzIS
 * Text Domain: oz-reviews
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OZ_REVIEWS_VERSION', '0.1.0' );
define( 'OZ_REVIEWS_FILE', __FILE__ );
define( 'OZ_REVIEWS_DIR', plugin_dir_path( __FILE__ ) );
define( 'OZ_REVIEWS_URL', plugin_dir_url( __FILE__ ) );

require_once OZ_REVIEWS_DIR . 'includes/class-cpt.php';
require_once OZ_REVIEWS_DIR . 'includes/class-meta.php';
require_once OZ_REVIEWS_DIR . 'includes/class-settings.php';
require_once OZ_REVIEWS_DIR . 'includes/class-submission.php';

add_action( 'plugins_loaded', function () {
	OZ_Reviews\CPT::register();
	OZ_Reviews\Meta::register();
	OZ_Reviews\Settings::register();
	OZ_Reviews\Submission::register();
} );
