<?php
/**
 * Frontend Display for BCW
 *
 * Handles:
 * - Base product redirect to most-sold color variant
 * - Enqueueing product page CSS/JS
 * - Passing product config to JS via wp_localize_script
 *   (payload shape matches WAPO-PARITY-CONFIG.md §16 exactly)
 * - Color swatch rendering
 *
 * The actual product page layout is rendered by the template file
 * templates/product-page.php, loaded via a WooCommerce template override.
 *
 * @package OZ_Variations_BCW
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_Frontend_Display {

    /**
     * Initialize frontend hooks.
     */
    public static function init() {
        // Base product redirect (before template loads)
        add_action('template_redirect', [__CLASS__, 'redirect_base_products']);

        // Enqueue product page assets
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // Output product config as JS data (after enqueue, priority 20)
        add_action('wp_enqueue_scripts', [__CLASS__, 'localize_product_data'], 20);
    }

    /**
     * Redirect base products to their most popular color variant.
     * Base products are landing pages — not directly purchasable.
     */
    public static function redirect_base_products() {
        if (!is_product()) {
            return;
        }

        global $product;
        if (!$product) {
            $product = wc_get_product(get_the_ID());
        }
        if (!$product) {
            return;
        }

        if (!OZ_Product_Processor::is_base_product($product)) {
            return;
        }

        $variant_id = OZ_Product_Processor::find_most_popular_variant($product->get_id());
        if ($variant_id) {
            $url = get_permalink($variant_id);
            if ($url) {
                wp_safe_redirect($url, 301);
                exit;
            }
        }
    }

    /**
     * Enqueue product page CSS and JS.
     * Only loads on single product pages.
     */
    public static function enqueue_assets() {
        if (!is_product()) {
            return;
        }

        // Product page CSS
        wp_enqueue_style(
            'oz-product-page',
            OZ_BCW_PLUGIN_URL . 'assets/css/oz-product-page.css',
            [],
            OZ_BCW_VERSION
        );

        // Product page JS (vanilla, no jQuery dependency)
        wp_enqueue_script(
            'oz-product-page',
            OZ_BCW_PLUGIN_URL . 'assets/js/oz-product-page.js',
            [],
            OZ_BCW_VERSION,
            true // Load in footer
        );
    }

    /**
     * Pass product config data to JS via wp_localize_script.
     *
     * Payload shape matches WAPO-PARITY-CONFIG.md §16 exactly:
     * - puOptions: [{layers, label, price, default}] | false
     * - primerOptions: [{label, price, default}] | false
     * - colorfresh: [{label, price, default}] | false
     * - toepassing: ["Vloer", ...] | false
     * - pakket: [{label, price, default}] | false
     * - hasRalNcs: bool
     * - ralNcsOnly: bool
     */
    public static function localize_product_data() {
        if (!is_product()) {
            return;
        }

        global $product;
        if (!$product) {
            $product = wc_get_product(get_the_ID());
        }
        if (!$product) {
            return;
        }

        $line_info = OZ_Product_Line_Config::for_product($product);
        if (!$line_info['line']) {
            return;
        }

        $line_key = $line_info['line'];
        $config   = $line_info['config'];

        // Color variant data for swatches
        $current_color = get_post_meta($product->get_id(), '_oz_color', true);
        $variants      = OZ_Product_Processor::get_variant_display_data($product->get_id());

        // Current product image for sticky bar
        $image_id  = get_post_thumbnail_id($product->get_id());
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

        // Cross-sell product IDs (for upsell system)
        $cross_sell_ids = $product->get_cross_sell_ids();

        // Build JS data object — matches WAPO-PARITY-CONFIG.md §16
        $js_data = [
            // Product identity
            'productId'    => $product->get_id(),
            'productName'  => $product->get_name(),
            'basePrice'    => floatval($product->get_price()),
            'productLine'  => $line_key,

            // Unit info
            'unit'   => $config['unit'],
            'unitM2' => $config['unitM2'],

            // PU options: [{layers, label, price, default}] or false
            'puOptions' => OZ_Product_Line_Config::get_pu_options($line_key),

            // Primer options: [{label, price, default}] or false
            'primerOptions' => OZ_Product_Line_Config::get_primer_options($line_key),

            // Colorfresh: [{label, price, default}] or false (Original only)
            'colorfresh' => OZ_Product_Line_Config::get_colorfresh_options($line_key),

            // Toepassing: ["Vloer", "Wand", ...] or false (Original only)
            'toepassing' => OZ_Product_Line_Config::get_toepassing_options($line_key),

            // Pakket: [{label, price, default}] or false
            'pakket' => OZ_Product_Line_Config::get_pakket_options($line_key),

            // RAL/NCS color mode
            'hasRalNcs'  => (bool) $config['ral_ncs'],
            'ralNcsOnly' => (bool) $config['ral_ncs_only'],

            // Option display order
            'optionOrder' => $config['option_order'],

            // Color/variant data
            'currentColor' => $current_color ? $current_color : '',
            'variants'     => $variants,
            'productImage' => $image_url,

            // Cross-sells for upsell system
            'crossSells' => $cross_sell_ids,

            // WooCommerce cart endpoints
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'cartUrl'     => wc_get_cart_url(),
            'checkoutUrl' => wc_get_checkout_url(),
            'nonce'       => wp_create_nonce('oz_bcw_cart'),
        ];

        wp_localize_script('oz-product-page', 'ozProduct', $js_data);
    }

    /**
     * Render color swatches HTML for a product.
     * Used by the product page template.
     *
     * @param WC_Product $product
     * @return string  HTML
     */
    public static function render_color_swatches($product) {
        $current_color = get_post_meta($product->get_id(), '_oz_color', true);
        $variants      = OZ_Product_Processor::get_variant_display_data($product->get_id());

        if (empty($variants)) {
            return '';
        }

        $html = '<div class="oz-color-swatches">';

        // Current product swatch (highlighted)
        $current_image = get_post_thumbnail_id($product->get_id())
            ? wp_get_attachment_image_url(get_post_thumbnail_id($product->get_id()), 'thumbnail')
            : '';

        $html .= sprintf(
            '<a href="%s" class="oz-color-swatch selected" data-color="%s" title="%s">'
            . '<img src="%s" alt="%s">'
            . '</a>',
            esc_url(get_permalink($product->get_id())),
            esc_attr($current_color),
            esc_attr($current_color),
            esc_url($current_image),
            esc_attr($current_color)
        );

        // Variant swatches
        foreach ($variants as $vid => $v) {
            $html .= sprintf(
                '<a href="%s" class="oz-color-swatch" data-color="%s" title="%s">'
                . '<img src="%s" alt="%s">'
                . '</a>',
                esc_url($v['url']),
                esc_attr($v['color']),
                esc_attr($v['color']),
                esc_url($v['image']),
                esc_attr($v['color'])
            );
        }

        $html .= '</div>';
        return $html;
    }
}
