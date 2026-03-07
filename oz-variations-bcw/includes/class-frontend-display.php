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

        // Override single-product template for BCW product lines
        // Priority 20 to run AFTER WC_Template_Loader::template_loader (priority 10)
        add_filter('template_include', [__CLASS__, 'override_product_template'], 20);

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

        // On template_redirect, global $product may be a string (slug), not a WC_Product.
        // Always resolve from post ID to get a real WC_Product object.
        $product = wc_get_product(get_the_ID());
        if (!$product instanceof WC_Product) {
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
     * Override single-product template for products in a BCW product line.
     * Non-BCW products keep the theme's default template.
     *
     * @param string $template  Current template path
     * @return string
     */
    public static function override_product_template($template) {
        if (!is_product()) {
            return $template;
        }

        $product = wc_get_product(get_the_ID());
        if (!$product instanceof WC_Product) {
            return $template;
        }

        // Only override for detected BCW product lines
        $line = OZ_Product_Line_Config::detect($product);
        if (!$line) {
            return $template;
        }

        $custom = OZ_BCW_PLUGIN_DIR . 'templates/single-product.php';
        if (file_exists($custom)) {
            return $custom;
        }

        return $template;
    }

    /**
     * Enqueue product page CSS and JS.
     * Only loads on single product pages.
     */
    public static function enqueue_assets() {
        if (!is_product()) {
            return;
        }

        // Google Fonts: DM Serif Display (headings) + Raleway (body)
        wp_enqueue_style(
            'oz-google-fonts',
            'https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Raleway:wght@400;500;600;700&display=swap',
            [],
            null // no version for external fonts
        );

        // Product page CSS
        wp_enqueue_style(
            'oz-product-page',
            OZ_BCW_PLUGIN_URL . 'assets/css/oz-product-page.css',
            ['oz-google-fonts'],
            OZ_BCW_VERSION . "." . time()
        );

        // Product page JS (vanilla, no jQuery dependency)
        wp_enqueue_script(
            'oz-product-page',
            OZ_BCW_PLUGIN_URL . 'assets/js/oz-product-page.js',
            [],
            OZ_BCW_VERSION . '.' . time(),
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

        // Same as redirect: global $product may not be a WC_Product yet
        $product = wc_get_product(get_the_ID());
        if (!$product instanceof WC_Product) {
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

            // Tool/gereedschap config — only for lines with has_tools
            'hasTools' => !empty($config['has_tools']),
            'toolConfig' => !empty($config['has_tools']) ? self::get_tool_config() : null,

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
        $current_id    = $product->get_id();
        $current_color = get_post_meta($current_id, '_oz_color', true);
        $variants      = OZ_Product_Processor::get_variant_display_data($current_id);

        if (empty($variants)) {
            return '';
        }

        // Build a unified list: current product + all variants, keyed by product ID
        $current_image = get_post_thumbnail_id($current_id)
            ? wp_get_attachment_image_url(get_post_thumbnail_id($current_id), 'thumbnail')
            : '';

        $all_swatches = [];
        $all_swatches[$current_id] = [
            'color' => $current_color,
            'url'   => get_permalink($current_id),
            'image' => $current_image,
        ];

        foreach ($variants as $vid => $v) {
            $all_swatches[$vid] = $v;
        }

        // Sort by product ID — ensures identical order on every color page
        ksort($all_swatches);

        $html = '<div class="oz-color-swatches">';

        foreach ($all_swatches as $pid => $s) {
            $is_current = ($pid === $current_id);
            $html .= sprintf(
                '<a href="%s" class="oz-color-swatch%s" data-color="%s" title="%s">'
                . '<img src="%s" alt="%s" width="46" height="46" loading="eager">'
                . '</a>',
                esc_url($s['url']),
                $is_current ? ' selected' : '',
                esc_attr($s['color']),
                esc_attr($s['color']),
                esc_url($s['image']),
                esc_attr($s['color'])
            );
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Get tool/gereedschap configuration for JS.
     * Contains the complete set, extras with sizes, and individual tools with sizes.
     * All prices and WooCommerce product IDs from real BCW catalog.
     *
     * @return array  Tool config array for wp_localize_script
     */
    public static function get_tool_config() {
        return array(
            'toolSet' => array(
                'id'       => 11177,
                'name'     => 'Gereedschapset Kant & Klaar',
                'price'    => 89.99,
                'contents' => array(
                    '1x Flexibele spaan',
                    '1x Kwast primer',
                    '1x Kwast PU',
                    '1x PU garde',
                    '3x PU roller',
                    '1x Tape',
                    '2x Verfbak',
                    '1x Vachtroller',
                ),
            ),
            'extras' => array(
                array(
                    'id' => 'pu-roller', 'name' => 'PU Roller', 'price' => 2.50,
                    'wcId' => 11175, 'note' => 'Verhardt na ~2 uur',
                    'sizes' => array(
                        array('label' => '10cm', 'price' => 2.50,  'wcId' => 11175),
                        array('label' => '18cm', 'price' => 9.95,  'wcId' => 17360),
                        array('label' => '25cm', 'price' => 12.95, 'wcId' => 17361),
                        array('label' => '50cm', 'price' => 17.50, 'wcId' => 19705),
                    ),
                ),
                array(
                    'id' => 'verfbak', 'name' => 'Verfbak', 'price' => 2.95,
                    'wcId' => 11164,
                    'sizes' => array(
                        array('label' => '10cm', 'price' => 2.95, 'wcId' => 11164, 'wapoAddon' => null),
                        array('label' => '18cm', 'price' => 4.95, 'wcId' => 11164, 'wapoAddon' => '43-1'),
                        array('label' => '32cm', 'price' => 5.95, 'wcId' => 11164, 'wapoAddon' => '43-2'),
                    ),
                ),
                array('id' => 'tape',           'name' => 'Tape',                   'price' => 5.99,  'wcId' => 11018),
                array('id' => 'vachtroller',    'name' => 'Vachtroller',             'price' => 8.95,  'wcId' => 11015),
                array('id' => 'troffel',        'name' => 'Troffel 180mm',           'price' => 16.95, 'wcId' => 11017),
                array('id' => 'hoek-inwendig',  'name' => 'Inwendige hoektroffel',   'price' => 15.95, 'wcId' => 11023),
                array('id' => 'hoek-uitwendig', 'name' => 'Uitwendige hoektroffel',  'price' => 15.95, 'wcId' => 11016),
            ),
            'tools' => array(
                array('id' => 'flexibele-spaan', 'name' => 'Flexibele spaan',  'price' => 39.95, 'wcId' => 11025),
                array(
                    'id' => 'pu-roller', 'name' => 'PU Roller', 'price' => 2.50,
                    'wcId' => 11175, 'note' => 'Verhardt na ~2 uur',
                    'sizes' => array(
                        array('label' => '10cm', 'price' => 2.50,  'wcId' => 11175),
                        array('label' => '18cm', 'price' => 9.95,  'wcId' => 17360),
                        array('label' => '25cm', 'price' => 12.95, 'wcId' => 17361),
                        array('label' => '50cm', 'price' => 17.50, 'wcId' => 19705),
                    ),
                ),
                array('id' => 'kwast',          'name' => 'Kwast',                   'price' => 1.99,  'wcId' => 11022),
                array('id' => 'pu-garde',       'name' => 'PU garde',                'price' => 8.99,  'wcId' => 11020),
                array('id' => 'tape',           'name' => 'Tape',                    'price' => 5.99,  'wcId' => 11018),
                array(
                    'id' => 'verfbak', 'name' => 'Verfbak', 'price' => 2.95,
                    'wcId' => 11164,
                    'sizes' => array(
                        array('label' => '10cm', 'price' => 2.95, 'wcId' => 11164, 'wapoAddon' => null),
                        array('label' => '18cm', 'price' => 4.95, 'wcId' => 11164, 'wapoAddon' => '43-1'),
                        array('label' => '32cm', 'price' => 5.95, 'wcId' => 11164, 'wapoAddon' => '43-2'),
                    ),
                ),
                array('id' => 'vachtroller',    'name' => 'Vachtroller',             'price' => 8.95,  'wcId' => 11015),
                array('id' => 'blokkwast',      'name' => 'Blokkwast',               'price' => 6.99,  'wcId' => 22997),
                array('id' => 'troffel',        'name' => 'Troffel 180mm',           'price' => 16.95, 'wcId' => 11017),
                array('id' => 'hoek-inwendig',  'name' => 'Inwendige hoektroffel',   'price' => 15.95, 'wcId' => 11023),
                array('id' => 'hoek-uitwendig', 'name' => 'Uitwendige hoektroffel',  'price' => 15.95, 'wcId' => 11016),
            ),
            'nudgeQtyThreshold' => 3,
        );
    }
}
