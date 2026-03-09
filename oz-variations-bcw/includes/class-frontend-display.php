<?php
/**
 * Frontend Display for BCW
 *
 * Handles:
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
        // NOTE: Base product redirect + sitemap exclusion removed (2026-03-09).
        // Base products should load normally — no 301 to variants.
        // They should also appear in the Yoast sitemap for SEO traffic.

        // Override single-product template for BCW product lines
        // Priority 20 to run AFTER WC_Template_Loader::template_loader (priority 10)
        add_filter('template_include', [__CLASS__, 'override_product_template'], 20);

        // Enqueue product page assets
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // Output product config as JS data (after enqueue, priority 20)
        add_action('wp_enqueue_scripts', [__CLASS__, 'localize_product_data'], 20);
    }

    // redirect_base_products() and exclude_base_products_from_sitemap() removed.
    // Base products now load their own page instead of 301-ing to a variant.
    // They also appear in the Yoast XML sitemap again.

    /**
     * Override single-product template for products with a page mode.
     * Products without a mode (no BCW line, no manual assignment) keep
     * the theme's default template.
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

        // Check page mode — covers both auto-detected lines and manual assignments
        $mode = OZ_Product_Line_Config::get_page_mode($product);
        if (!$mode) {
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
     * Only loads on products with a page mode (BCW line or manual assignment).
     */
    public static function enqueue_assets() {
        if (!is_product()) {
            return;
        }

        // Only enqueue for products using our template
        $product = wc_get_product(get_the_ID());
        if (!$product || !OZ_Product_Line_Config::get_page_mode($product)) {
            return;
        }

        // Google Fonts: DM Serif Display (headings) + Raleway (body)
        wp_enqueue_style(
            'oz-google-fonts',
            'https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Raleway:wght@400;500;600;700&display=swap',
            [],
            null // no version for external fonts
        );

        // Product page CSS — version based on file modification time for cache busting
        $css_path = OZ_BCW_PLUGIN_DIR . 'assets/css/oz-product-page.css';
        wp_enqueue_style(
            'oz-product-page',
            OZ_BCW_PLUGIN_URL . 'assets/css/oz-product-page.css',
            ['oz-google-fonts'],
            OZ_BCW_VERSION . '.' . filemtime($css_path)
        );

        // Product page JS (vanilla, no jQuery dependency)
        $js_path = OZ_BCW_PLUGIN_DIR . 'assets/js/oz-product-page.js';
        wp_enqueue_script(
            'oz-product-page',
            OZ_BCW_PLUGIN_URL . 'assets/js/oz-product-page.js',
            [],
            OZ_BCW_VERSION . '.' . filemtime($js_path),
            true // Load in footer
        );

        // Cookie/privacy banner delay — separate concern, no dependencies
        $banner_path = OZ_BCW_PLUGIN_DIR . 'assets/js/oz-cookie-banner.js';
        wp_enqueue_script(
            'oz-cookie-banner',
            OZ_BCW_PLUGIN_URL . 'assets/js/oz-cookie-banner.js',
            [],
            OZ_BCW_VERSION . '.' . filemtime($banner_path),
            true
        );
    }

    /**
     * Pass product config data to JS via wp_localize_script.
     *
     * For configured_line mode, payload matches WAPO-PARITY-CONFIG.md §16:
     * - puOptions, primerOptions, colorfresh, toepassing, pakket, etc.
     *
     * For generic modes, a minimal payload is sent (no addon options).
     * The JS handles both cases gracefully.
     */
    public static function localize_product_data() {
        if (!is_product()) {
            return;
        }

        $product = wc_get_product(get_the_ID());
        if (!$product instanceof WC_Product) {
            return;
        }

        // Determine page mode — bail if product doesn't use our template
        $page_mode = OZ_Product_Line_Config::get_page_mode($product);
        if (!$page_mode) {
            return;
        }

        // Resolve line config (null for generic modes)
        $line_info = OZ_Product_Line_Config::for_product($product);
        $line_key  = $line_info['line'];
        $config    = $line_info['config'] ?: OZ_Product_Line_Config::get_generic_config();

        // Common data for all modes
        $image_id  = get_post_thumbnail_id($product->get_id());
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

        $js_data = [
            // Page mode — JS can branch on this
            'pageMode'     => $page_mode,

            // Product identity
            'productId'    => $product->get_id(),
            'productName'  => $product->get_name(),
            'basePrice'    => floatval($product->get_price()),
            'productLine'  => $line_key ?: null,

            // Unit info
            'unit'   => $config['unit'],
            'unitM2' => $config['unitM2'],

            // Color/variant data
            'currentColor' => get_post_meta($product->get_id(), '_oz_color', true) ?: '',
            'variants'     => $line_key ? OZ_Product_Processor::get_variant_display_data($product->get_id()) : [],
            'productImage' => $image_url,

            // WooCommerce cart endpoints (always needed)
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'cartUrl'     => wc_get_cart_url(),
            'checkoutUrl' => wc_get_checkout_url(),
            'nonce'       => wp_create_nonce('oz_bcw_cart'),
        ];

        // Configured line mode — full addon options
        if ($page_mode === 'configured_line' && $line_key) {
            $js_data += [
                'puOptions'     => OZ_Product_Line_Config::get_pu_options($line_key),
                'primerOptions' => OZ_Product_Line_Config::get_primer_options($line_key),
                'colorfresh'    => OZ_Product_Line_Config::get_colorfresh_options($line_key),
                'toepassing'    => OZ_Product_Line_Config::get_toepassing_options($line_key),
                'pakket'        => OZ_Product_Line_Config::get_pakket_options($line_key),
                'hasRalNcs'     => (bool) $config['ral_ncs'],
                'ralNcsOnly'    => (bool) $config['ral_ncs_only'],
                'optionOrder'   => $config['option_order'],
                'crossSells'    => $product->get_cross_sell_ids(),
                'hasTools'      => !empty($config['has_tools']),
                'toolConfig'    => !empty($config['has_tools']) ? OZ_Product_Line_Config::get_tool_config() : null,
            ];
        } else {
            // Generic modes — no configured-line addon options
            $js_data += [
                'puOptions'     => false,
                'primerOptions' => false,
                'colorfresh'    => false,
                'toepassing'    => false,
                'pakket'        => false,
                'hasRalNcs'     => false,
                'ralNcsOnly'    => false,
                'optionOrder'   => [],
                'crossSells'    => [],
                'hasTools'      => false,
                'toolConfig'    => null,
            ];

            // Generic addon groups — per-product option groups (replaces YITH WAPO)
            if ($page_mode === 'generic_addons') {
                $js_data['addonGroups'] = OZ_Product_Line_Config::get_addon_groups_for_js($product->get_id());
            } else {
                $js_data['addonGroups'] = false;
            }
        }

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

}
