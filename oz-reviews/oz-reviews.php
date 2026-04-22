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

require_once OZ_REVIEWS_DIR . 'includes/class-review-dto.php';
require_once OZ_REVIEWS_DIR . 'includes/class-renderer.php';
require_once OZ_REVIEWS_DIR . 'includes/class-cpt.php';
require_once OZ_REVIEWS_DIR . 'includes/class-meta.php';
require_once OZ_REVIEWS_DIR . 'includes/class-settings.php';
require_once OZ_REVIEWS_DIR . 'includes/class-submission.php';
require_once OZ_REVIEWS_DIR . 'includes/class-places-client.php';
require_once OZ_REVIEWS_DIR . 'includes/class-google-sync.php';
require_once OZ_REVIEWS_DIR . 'includes/class-shortcode.php';
require_once OZ_REVIEWS_DIR . 'includes/class-schema.php';
require_once OZ_REVIEWS_DIR . 'includes/class-product-tab.php';
require_once OZ_REVIEWS_DIR . 'includes/class-cli.php';

add_action( 'plugins_loaded', function () {
	OZ_Reviews\CPT::register();
	OZ_Reviews\Meta::register();
	OZ_Reviews\Settings::register();
	OZ_Reviews\Submission::register();
	OZ_Reviews\Google_Sync::register();
	OZ_Reviews\Shortcode::register();
	OZ_Reviews\Schema::register();
	OZ_Reviews\Product_Tab::register();
	if ( class_exists( 'OZ_Reviews\\CLI' ) && defined( 'WP_CLI' ) && WP_CLI ) {
		OZ_Reviews\CLI::register();
	}
} );

// Clean up daily cron on plugin deactivation so we don't leave orphan jobs.
register_deactivation_hook( __FILE__, array( 'OZ_Reviews\\Google_Sync', 'unschedule' ) );
