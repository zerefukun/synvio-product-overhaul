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

        // Exclude mode-toggle products (K&K ↔ ZM) from LiteSpeed page cache.
        // LS was serving ZM HTML under the K&K URL (and vice-versa) — likely
        // cache-key bleed from a poisoned entry. Excluding these URLs stops
        // recurrence until we can diagnose the LS config root cause.
        add_action('template_redirect', [__CLASS__, 'disable_cache_for_toggle_products']);
    }

    /**
     * Disable full-page caching for products whose line has a formula toggle
     * (currently: Original K&K + Original ZM). These pages share client-side
     * state via pushState and have been observed bleeding cached HTML
     * between the two URLs. Cache exclusion is surgical — only lines with
     * `mode_toggle` config are affected; the rest of the shop keeps caching.
     */
    public static function disable_cache_for_toggle_products() {
        if (!is_product()) {
            return;
        }

        $product = wc_get_product(get_the_ID());
        if (!$product instanceof WC_Product) {
            return;
        }

        $line_key = OZ_Product_Line_Config::detect($product);
        if (!$line_key) {
            return;
        }

        $mode_toggle = OZ_Product_Line_Config::get_mode_toggle_config($line_key);
        if (!$mode_toggle) {
            return;
        }

        // LiteSpeed Cache plugin — official API action
        do_action('litespeed_control_set_nocache', 'bcw-formula-toggle');

        // LiteSpeed web server header — works even if LSCWP plugin is absent
        if (!headers_sent()) {
            header('X-LiteSpeed-Cache-Control: no-cache');
            header('Cache-Control: no-cache, max-age=0, no-store, must-revalidate', true);
        }

        // WordPress standard no-cache headers (covers other page caches)
        nocache_headers();
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

        // Product page JS (vanilla, no jQuery dependency).
        // Depends on oz-swiper-loader so the FBT carousel + USP ticker can
        // call window.ozLoadSwiper without racing the loader script.
        $js_path = OZ_BCW_PLUGIN_DIR . 'assets/js/oz-product-page.js';
        wp_enqueue_script(
            'oz-product-page',
            OZ_BCW_PLUGIN_URL . 'assets/js/oz-product-page.js',
            ['oz-swiper-loader'],
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
            'isBase'       => OZ_Product_Processor::is_base_product($product),

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
            'variants'     => $line_key
                ? ($page_mode === 'configured_line' && !empty($config['share_colors_from'])
                    ? self::get_shared_color_variants($config['share_colors_from'])
                    : ($page_mode === 'configured_line' && OZ_Product_Processor::is_base_product($product)
                        ? self::get_base_product_variants($product)
                        : OZ_Product_Processor::get_variant_display_data($product->get_id())))
                : [],
            'productImage' => $image_url,
            'siteTitle'    => get_bloginfo('name'),

            // WooCommerce cart endpoints (always needed)
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'cartUrl'     => wc_get_cart_url(),
            'checkoutUrl' => wc_get_checkout_url(),
            'nonce'          => wp_create_nonce('oz_bcw_cart'),
            'analyticsNonce' => wp_create_nonce('oz_analytics'),
        ];

        // Configured line mode — full addon options
        if ($page_mode === 'configured_line' && $line_key) {
            $js_data += [
                'puOptions'     => OZ_Product_Line_Config::get_pu_options($line_key),
                'primerOptions' => OZ_Product_Line_Config::get_primer_options($line_key),
                'colorfresh'    => OZ_Product_Line_Config::get_colorfresh_options($line_key),
                'toepassing'    => OZ_Product_Line_Config::get_toepassing_options($line_key),
                'pakket'        => OZ_Product_Line_Config::get_pakket_options($line_key),
                'ruimteOptions' => OZ_Product_Line_Config::get_ruimte_options($line_key),
                'hasRalNcs'     => (bool) $config['ral_ncs'],
                'ralNcsOnly'    => (bool) $config['ral_ncs_only'],
                'baseProductId' => OZ_Product_Line_Config::get_base_product_id($line_key),
                'hasStaticColors' => !empty($config['share_colors_from']),
                'optionOrder'   => $config['option_order'],
                'crossSells'    => $product->get_cross_sell_ids(),
                'hasTools'      => !empty($config['has_tools']),
                'toolConfig'    => !empty($config['has_tools']) ? OZ_Product_Line_Config::get_tool_config($line_key) : null,
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

        // Add current product to variants map so pushState back-navigation works.
        // Without this, popstate to the initial page would have no variant data.
        if ($line_key && !empty($js_data['variants'])) {
            $current_pid      = $product->get_id();
            $current_image_id = get_post_thumbnail_id($current_pid);

            // Current product gallery — needed for popstate back-navigation rebuild
            $current_gallery = [];
            foreach ($product->get_gallery_image_ids() as $gid) {
                $g_thumb = wp_get_attachment_image_url($gid, 'thumbnail');
                $g_large = wp_get_attachment_image_url($gid, 'large');
                if ($g_thumb && $g_large) {
                    $current_gallery[] = ['thumb' => $g_thumb, 'full' => $g_large];
                }
            }

            $js_data['variants'][$current_pid] = [
                'color'        => get_post_meta($current_pid, '_oz_color', true) ?: '',
                'url'          => get_permalink($current_pid),
                'image'        => $current_image_id ? wp_get_attachment_image_url($current_image_id, 'thumbnail') : '',
                'fullImage'    => $current_image_id ? wp_get_attachment_image_url($current_image_id, 'large') : '',
                'gallery'      => $current_gallery,
                'price'        => floatval($product->get_price()),
                'regularPrice' => floatval($product->get_regular_price()),
                'onSale'       => $product->is_on_sale(),
                'title'        => $product->get_name(),
                'description'  => apply_filters('the_content', $product->get_description()),
            ];
        }

        // Mode toggle — pre-load target product data for instant client-side swap
        if ($line_key) {
            $mode_toggle = OZ_Product_Line_Config::get_mode_toggle_config($line_key);
            if ($mode_toggle) {
                $target_line   = $mode_toggle['target_line'];
                $target_config = OZ_Product_Line_Config::get_config($target_line);
                $target_pid    = $mode_toggle['target_product_id'];
                $target_product = wc_get_product($target_pid);

                if ($target_config && $target_product) {
                    // Target product's USPs/specs/FAQ — from meta first, then config fallback
                    $target_usps = get_post_meta($target_pid, '_oz_usps', true);
                    if (empty($target_usps) || !is_array($target_usps)) {
                        $target_usps = !empty($target_config['usps']) ? $target_config['usps'] : [];
                    }
                    $target_specs = get_post_meta($target_pid, '_oz_specs', true);
                    if (empty($target_specs) || !is_array($target_specs)) {
                        $target_specs = !empty($target_config['specs']) ? $target_config['specs'] : [];
                    }
                    $target_faq = get_post_meta($target_pid, '_oz_faq', true);
                    if (empty($target_faq) || !is_array($target_faq)) {
                        $target_faq = !empty($target_config['faq']) ? $target_config['faq'] : [];
                    }

                    // Target product gallery — used for gallery strip in target mode
                    $target_gallery = [];
                    foreach ($target_product->get_gallery_image_ids() as $gid) {
                        $g_thumb = wp_get_attachment_image_url($gid, 'thumbnail');
                        $g_large = wp_get_attachment_image_url($gid, 'large');
                        if ($g_thumb && $g_large) {
                            $target_gallery[] = ['thumb' => $g_thumb, 'full' => $g_large];
                        }
                    }

                    // Named-by-formula galleries so JS can pick the right one regardless
                    // of direction (K&K→ZM or ZM→K&K). The "target" gallery is whichever
                    // side we're toggling to; zm/kk are absolute references.
                    $is_self_zm = (strpos($line_key, '-zm') !== false);
                    $zm_gallery = $is_self_zm ? [] : $target_gallery;
                    $kk_gallery = $is_self_zm ? $target_gallery : [];
                    if ($is_self_zm) {
                        // current product IS ZM — capture its own gallery as zmGallery
                        foreach ($product->get_gallery_image_ids() as $gid) {
                            $g_thumb = wp_get_attachment_image_url($gid, 'thumbnail');
                            $g_large = wp_get_attachment_image_url($gid, 'large');
                            if ($g_thumb && $g_large) {
                                $zm_gallery[] = ['thumb' => $g_thumb, 'full' => $g_large];
                            }
                        }
                    } else {
                        // current product IS K&K — capture its own gallery as kkGallery
                        foreach ($product->get_gallery_image_ids() as $gid) {
                            $g_thumb = wp_get_attachment_image_url($gid, 'thumbnail');
                            $g_large = wp_get_attachment_image_url($gid, 'large');
                            if ($g_thumb && $g_large) {
                                $kk_gallery[] = ['thumb' => $g_thumb, 'full' => $g_large];
                            }
                        }
                    }

                    // Base featured images named by formula — JS falls back to these
                    // when no color variant is selected (user on the base product and
                    // toggles: main image needs to swap to the other side's base photo).
                    $self_base_img_id  = get_post_thumbnail_id($product->get_id());
                    $target_base_img_id = get_post_thumbnail_id($target_pid);
                    $self_base_image   = $self_base_img_id   ? wp_get_attachment_image_url($self_base_img_id,   'large') : '';
                    $target_base_image = $target_base_img_id ? wp_get_attachment_image_url($target_base_img_id, 'large') : '';
                    $zm_base_image = $is_self_zm ? $self_base_image : $target_base_image;
                    $kk_base_image = $is_self_zm ? $target_base_image : $self_base_image;

                    $js_data['modeToggle'] = [
                        'labelSelf'           => $mode_toggle['label_self'],
                        'labelTarget'         => $mode_toggle['label_target'],
                        'targetProductId'     => $target_pid,
                        'targetLine'          => $target_line,
                        'targetBasePrice'     => floatval($target_product->get_price()),
                        'targetUnit'          => $target_config['unit'],
                        'targetUnitM2'        => $target_config['unitM2'],
                        'targetUrl'           => get_permalink($target_pid),
                        'targetProductName'   => $target_product->get_name(),
                        'targetPuOptions'     => OZ_Product_Line_Config::get_pu_options($target_line),
                        'targetPrimerOptions' => OZ_Product_Line_Config::get_primer_options($target_line),
                        'targetToepassing'    => OZ_Product_Line_Config::get_toepassing_options($target_line),
                        'targetOptionOrder'   => $target_config['option_order'],
                        'targetHasTools'      => !empty($target_config['has_tools']),
                        'targetToolConfig'    => !empty($target_config['has_tools'])
                            ? OZ_Product_Line_Config::get_tool_config($target_line)
                            : null,
                        'targetUsps'          => $target_usps,
                        'targetSpecs'         => $target_specs,
                        'targetFaq'           => $target_faq,
                        'targetDescription'   => apply_filters('the_content', $target_product->get_description()),
                        'targetGallery'       => $target_gallery,
                        'zmGallery'           => $zm_gallery,
                        'kkGallery'           => $kk_gallery,
                        'zmBaseImage'         => $zm_base_image,
                        'kkBaseImage'         => $kk_base_image,
                    ];
                }
            }
        }

        wp_localize_script('oz-product-page', 'ozProduct', $js_data);
    }

    /**
     * Extract the trailing number code from a color name for swatch labels.
     * "Elephant Skin 1004" → "1004", "Sand 1" → "Sand 1" (no 4-digit code)
     * Falls back to full name if no code found.
     */
    private static function extract_color_code($color_name) {
        if (preg_match('/\b(\d{4})\s*$/', $color_name, $m)) {
            return $m[1];
        }
        return $color_name;
    }

    /**
     * Sort swatches by fixed color-group order.
     * Groups: Cement (1) > Blue (2) > Nude (3) > Sand (4) > Green (5)
     * Unrecognised names go last, sorted by name length then alphabetically.
     * Within each group, natural sort (Cement 1, Cement 2, Cement 10).
     */
    private static function sort_swatches($a, $b) {
        // Original line has 4-digit codes (1000+) — sort numerically.
        // Other lines have names like "Sand 1, Sand 2" — natural sort by name.
        $na = self::swatch_number($a['color']);
        $nb = self::swatch_number($b['color']);

        $a_has_code = ($na >= 1000 && $na < PHP_INT_MAX);
        $b_has_code = ($nb >= 1000 && $nb < PHP_INT_MAX);

        if ($a_has_code && $b_has_code) {
            return $na - $nb;
        }

        return strnatcasecmp($a['color'], $b['color']);
    }

    /**
     * Extract numeric code from a color name (e.g. "Stone White 1000" → 1000).
     * Falls back to PHP_INT_MAX so colors without numbers sort last.
     */
    private static function swatch_number($color) {
        if (preg_match('/(\d+)/', $color, $m)) {
            return (int) $m[1];
        }
        return PHP_INT_MAX;
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
        $is_base       = OZ_Product_Processor::is_base_product($product);

        // Check for shared colors (e.g. Betonlook Verf borrows All-in-One's palette)
        $line_info = OZ_Product_Line_Config::for_product($product);
        $config    = $line_info['config'];
        $shared    = !empty($config['share_colors_from']);

        if ($shared) {
            // Fetch color variants from the source line's categories
            return self::render_shared_color_swatches($config['share_colors_from']);
        }

        // For base products, fetch all variants from the line's categories.
        // For color variants, use the stored _oz_variants meta (bidirectional links).
        if ($is_base) {
            $variants = self::get_base_product_variants($product);
        } else {
            $variants = OZ_Product_Processor::get_variant_display_data($current_id);
        }

        if (empty($variants)) {
            return '';
        }

        // Build swatch list. Base products have no "current" swatch —
        // all swatches are links to color variants (none selected).
        $all_swatches = [];

        if (!$is_base && $current_color) {
            // Add current product to list (only for variant pages)
            $current_image = get_post_thumbnail_id($current_id)
                ? wp_get_attachment_image_url(get_post_thumbnail_id($current_id), 'thumbnail')
                : '';
            $all_swatches[$current_id] = [
                'color' => $current_color,
                'url'   => get_permalink($current_id),
                'image' => $current_image,
            ];
        }

        foreach ($variants as $vid => $v) {
            $all_swatches[$vid] = $v;
        }

        // Sort by fixed group order: Cement > Blue > Nude > Sand > Green > rest by length
        uasort($all_swatches, [__CLASS__, 'sort_swatches']);

        $html = '<div class="oz-color-swatches">';

        foreach ($all_swatches as $pid => $s) {
            $is_current = ($pid === $current_id);
            // K&K swatches show full color name
            $swatch_label = $s['color'];
            $html .= sprintf(
                '<a href="%s" class="oz-color-swatch%s" data-color="%s" data-product-id="%d">'
                . '<span class="oz-swatch-img"><img src="%s" alt="%s" width="46" height="46" loading="eager"></span>'
                . '<span class="oz-swatch-name">%s</span>'
                . '</a>',
                esc_url($s['url']),
                $is_current ? ' selected' : '',
                esc_attr($s['color']),
                $pid,
                esc_url($s['image']),
                esc_attr($s['color']),
                esc_html($swatch_label)
            );
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render static (non-navigating) color swatches borrowed from another line.
     * Clicking these sets the color in JS state instead of navigating to a new product.
     *
     * @param string $source_line_key  Line key to borrow colors from (e.g. 'all-in-one')
     * @return string  HTML
     */
    private static function render_shared_color_swatches($source_line_key) {
        $source_config = OZ_Product_Line_Config::get_config($source_line_key);
        if (!$source_config || empty($source_config['cats'])) {
            return '';
        }

        // Query all color variants from the source line's categories
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'tax_query'      => [[
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $source_config['cats'],
            ]],
            'meta_query'     => [[
                'key'     => '_oz_color',
                'compare' => 'EXISTS',
            ]],
        ];

        $ids = get_posts($args);
        if (empty($ids)) {
            return '';
        }

        // Collect color data — deduplicate by color name (source line may have
        // multiple sizes per color, we only need one swatch per color)
        $seen_colors = [];
        $swatches    = [];

        foreach ($ids as $vid) {
            $color = get_post_meta($vid, '_oz_color', true);
            if (empty($color) || isset($seen_colors[$color])) {
                continue;
            }
            $seen_colors[$color] = true;

            // Use ZM image if available, fall back to K&K featured image
            $zm_image_id = get_post_meta($vid, '_oz_zm_image_id', true);
            $image_id    = $zm_image_id ?: get_post_thumbnail_id($vid);
            $image_url   = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
            if (empty($image_url)) {
                continue; // Skip colors without a thumbnail — avoids broken img src=""
            }

            $swatches[$vid] = [
                'color' => $color,
                'image' => $image_url,
            ];
        }

        // Sort by fixed group order: Cement > Blue > Nude > Sand > Green > rest by length
        uasort($swatches, [__CLASS__, 'sort_swatches']);

        // Render as static swatches — data-static="1" tells JS not to navigate
        $html = '<div class="oz-color-swatches">';

        foreach ($swatches as $pid => $s) {
            $swatch_label = self::extract_color_code($s['color']);
            $html .= sprintf(
                '<a href="#" class="oz-color-swatch" data-color="%s" data-static="1">'
                . '<span class="oz-swatch-img"><img src="%s" alt="%s" width="46" height="46" loading="eager"></span>'
                . '<span class="oz-swatch-name">%s</span>'
                . '</a>',
                esc_attr($s['color']),
                esc_url($s['image']),
                esc_attr($s['color']),
                esc_html($swatch_label)
            );
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Get all color variants for a base product by querying the line's categories.
     * Returns the same format as OZ_Product_Processor::get_variant_display_data().
     *
     * @param WC_Product $product  The base product
     * @return array  [product_id => ['color' => ..., 'url' => ..., 'image' => ...]]
     */
    private static function get_base_product_variants($product) {
        $line_info = OZ_Product_Line_Config::for_product($product);
        if (!$line_info['config'] || empty($line_info['config']['cats'])) {
            return [];
        }

        // Query all published products in this line's categories that have _oz_color
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'post__not_in'   => [$product->get_id()],
            'fields'         => 'ids',
            'tax_query'      => [[
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $line_info['config']['cats'],
            ]],
            'meta_query'     => [[
                'key'     => '_oz_color',
                'compare' => 'EXISTS',
            ]],
        ];

        $ids = get_posts($args);
        $variants = [];

        foreach ($ids as $vid) {
            $color = get_post_meta($vid, '_oz_color', true);
            if (empty($color)) {
                continue;
            }

            $variant   = wc_get_product($vid);
            $image_id  = get_post_thumbnail_id($vid);
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

            // Gallery images for pushState thumbnail strip rebuild
            $gallery = [];
            if ($variant) {
                foreach ($variant->get_gallery_image_ids() as $gid) {
                    $g_thumb = wp_get_attachment_image_url($gid, 'thumbnail');
                    $g_large = wp_get_attachment_image_url($gid, 'large');
                    if ($g_thumb && $g_large) {
                        $gallery[] = ['thumb' => $g_thumb, 'full' => $g_large];
                    }
                }
            }

            // ZM image for K&K→ZM toggle (old bucket photo without K&K branding)
            $zm_image_id = get_post_meta($vid, '_oz_zm_image_id', true);

            $variants[$vid] = [
                'color'        => $color,
                'url'          => get_permalink($vid),
                'image'        => $image_url,
                'fullImage'    => $image_id ? wp_get_attachment_image_url($image_id, 'large') : '',
                'zmImage'      => $zm_image_id ? wp_get_attachment_image_url($zm_image_id, 'thumbnail') : '',
                'zmFullImage'  => $zm_image_id ? wp_get_attachment_image_url($zm_image_id, 'large') : '',
                'gallery'      => $gallery,
                'price'        => $variant ? floatval($variant->get_price()) : 0,
                'regularPrice' => $variant ? floatval($variant->get_regular_price()) : 0,
                'onSale'       => $variant ? $variant->is_on_sale() : false,
                'title'        => $variant ? $variant->get_name() : '',
                'description'  => $variant ? apply_filters('the_content', $variant->get_description()) : '',
            ];
        }

        return $variants;
    }

    /**
     * Get variant display data from a shared/source line.
     * Used by products with share_colors_from (e.g. ZM borrows Original's palette).
     * Returns variant data so JS can swap images on static swatch click.
     *
     * @param string $source_line_key  Line key to borrow colors from
     * @return array  [product_id => variant data]
     */
    private static function get_shared_color_variants($source_line_key) {
        $source_config = OZ_Product_Line_Config::get_config($source_line_key);
        if (!$source_config || empty($source_config['cats'])) {
            return [];
        }

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'tax_query'      => [[
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $source_config['cats'],
            ]],
            'meta_query'     => [[
                'key'     => '_oz_color',
                'compare' => 'EXISTS',
            ]],
        ];

        $ids = get_posts($args);
        $variants = [];
        $seen_colors = [];

        foreach ($ids as $vid) {
            $color = get_post_meta($vid, '_oz_color', true);
            if (empty($color) || isset($seen_colors[$color])) {
                continue;
            }
            $seen_colors[$color] = true;

            // Populate BOTH K&K images and ZM images so toggle can swap either
            // direction (user may land on ZM page from search and toggle to K&K).
            $kk_image_id = get_post_thumbnail_id($vid);
            $zm_image_id = get_post_meta($vid, '_oz_zm_image_id', true);

            $variant = wc_get_product($vid);
            $gallery = [];
            if ($variant) {
                foreach ($variant->get_gallery_image_ids() as $gid) {
                    $g_thumb = wp_get_attachment_image_url($gid, 'thumbnail');
                    $g_large = wp_get_attachment_image_url($gid, 'large');
                    if ($g_thumb && $g_large) {
                        $gallery[] = ['thumb' => $g_thumb, 'full' => $g_large];
                    }
                }
            }

            $variants[$vid] = [
                'color'        => $color,
                'url'          => get_permalink($vid),
                'image'        => $kk_image_id ? wp_get_attachment_image_url($kk_image_id, 'thumbnail') : '',
                'fullImage'    => $kk_image_id ? wp_get_attachment_image_url($kk_image_id, 'large') : '',
                'zmImage'      => $zm_image_id ? wp_get_attachment_image_url($zm_image_id, 'thumbnail') : '',
                'zmFullImage'  => $zm_image_id ? wp_get_attachment_image_url($zm_image_id, 'large') : '',
                'gallery'      => $gallery,
                'price'        => $variant ? floatval($variant->get_price()) : 0,
                'regularPrice' => $variant ? floatval($variant->get_regular_price()) : 0,
                'onSale'       => $variant ? $variant->is_on_sale() : false,
                'title'        => $variant ? $variant->get_name() : '',
                'description'  => $variant ? apply_filters('the_content', $variant->get_description()) : '',
            ];
        }

        return $variants;
    }

}
