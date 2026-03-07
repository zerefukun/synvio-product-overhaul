<?php
/**
 * Plugin Name: OZ Variations BCW
 * Description: Product options, color variants, and addon pricing for Beton Ciré Webshop. Replaces YITH WAPO.
 * Version: 1.0.0
 * Author: OzIS
 * Text Domain: oz-variations-bcw
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 9.0
 * WC tested up to: 10.4
 * WooCommerce: true
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('OZ_BCW_VERSION', '1.1.0');
define('OZ_BCW_PLUGIN_FILE', __FILE__);
define('OZ_BCW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OZ_BCW_PLUGIN_URL', plugin_dir_url(__FILE__));

// Declare WooCommerce HPOS compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

/**
 * Main plugin class.
 *
 * Loads all classes in dependency order and initializes components.
 * Follows the same singleton + plugins_loaded pattern as oz-variations.
 */
class OZ_Variations_BCW {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * Plugin activation — check WooCommerce is present.
     */
    public function activate() {
        if (!$this->is_woocommerce_active()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('OZ Variations BCW requires WooCommerce to be active.');
        }
        update_option('oz_bcw_version', OZ_BCW_VERSION);
    }

    /**
     * Initialize after all plugins are loaded (WooCommerce is available).
     */
    public function init() {
        if (!$this->is_woocommerce_active()) {
            return;
        }

        $this->load_classes();
        $this->init_components();
    }

    /**
     * Load all class files in dependency order.
     * Config first (no dependencies), then processor (depends on config),
     * then cart/frontend/ajax/admin (depend on config + processor).
     */
    private function load_classes() {
        // Core config — no dependencies, everything else reads from this
        require_once OZ_BCW_PLUGIN_DIR . 'includes/class-product-line-config.php';

        // Product processor — depends on config for line detection
        require_once OZ_BCW_PLUGIN_DIR . 'classes/class-product-processor.php';

        // Cart manager — depends on config for pricing
        require_once OZ_BCW_PLUGIN_DIR . 'includes/class-cart-manager.php';

        // Frontend display — depends on config + processor
        require_once OZ_BCW_PLUGIN_DIR . 'includes/class-frontend-display.php';

        // AJAX handlers — depends on processor + cart
        require_once OZ_BCW_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

        // Admin settings — depends on processor for reprocess
        require_once OZ_BCW_PLUGIN_DIR . 'includes/class-admin.php';
    }

    /**
     * Initialize all plugin components.
     */
    private function init_components() {
        // AJAX handlers (admin + frontend)
        OZ_Ajax_Handlers::init();

        // Cart management (frontend + AJAX)
        OZ_Cart_Manager::init();

        // Frontend display (single product pages)
        OZ_Frontend_Display::init();

        // Process product on save (update color + variant links)
        add_action('woocommerce_update_product', ['OZ_Product_Processor', 'process_product']);

        // Admin interface (settings page with reprocess button)
        if (is_admin()) {
            OZ_BCW_Admin::init();
        }
    }

    /**
     * Check if WooCommerce is active.
     * Handles both regular and multisite installs.
     */
    private function is_woocommerce_active() {
        // Standard check
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            return true;
        }
        // Multisite check
        if (is_multisite()) {
            $plugins = get_site_option('active_sitewide_plugins', []);
            if (isset($plugins['woocommerce/woocommerce.php'])) {
                return true;
            }
        }
        return false;
    }
}

// Start the plugin
OZ_Variations_BCW::get_instance();
