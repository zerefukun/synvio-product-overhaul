<?php
/**
 * Product Processor for BCW
 *
 * Handles color extraction from product slugs, variant relationship
 * management via _oz_variants meta, and base product detection/redirect.
 *
 * Pattern taken from oz-variations (epoxystone), adapted for BCW's
 * 9 product lines with different slug patterns.
 *
 * @package OZ_Variations_BCW
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_Product_Processor {

    /**
     * BCW slug prefixes per product line.
     * Used to extract the color portion from product slugs.
     * Example: "microcement-cement-3" → prefix "microcement" → color "Cement 3"
     *
     * Multi-word prefixes listed first (longest match wins).
     */
    private static $slug_prefixes = [
        // Multi-word prefixes first
        'metallic-velvet-4m2-pakket',
        'metallic-stuc-velvet',
        'lavasteen-gietvloeren',
        'lavasteen-gietvloer',
        'beton-cire-all-in-one-kant-klaar',
        'beton-cire-easyline-kant-klaar',
        'beton-cire-original',
        // Single-word prefixes
        'microcement',
    ];

    /**
     * Process a product: extract color + update variant links.
     * Called on product save or via bulk reprocess.
     *
     * @param int $product_id
     */
    public static function process_product($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        // Only process lines that have variants
        $line_info = OZ_Product_Line_Config::for_product($product);
        if (!$line_info['line']) {
            return;
        }

        // Single-product lines don't have color variants
        $config = $line_info['config'];
        if ($config['base_id'] === null) {
            return;
        }

        // Base products are not color variants — skip color extraction + variant linking
        if (self::is_base_product($product)) {
            delete_post_meta($product_id, '_oz_color');
            delete_post_meta($product_id, '_oz_variants');
            return;
        }

        // Extract color from slug
        $color = self::extract_color($product->get_slug());

        if (!empty($color)) {
            update_post_meta($product_id, '_oz_color', sanitize_text_field($color));
        } else {
            delete_post_meta($product_id, '_oz_color');
        }

        // Update bidirectional variant links
        self::update_variants($product_id, $config['cats']);
    }

    /**
     * Extract color name from product slug.
     *
     * Tries each known prefix. If slug starts with a prefix followed by a dash,
     * the remainder is the color. Example: "microcement-cement-3" → "Cement 3"
     *
     * @param string $slug
     * @return string  Color name (title-cased) or empty string
     */
    public static function extract_color($slug) {
        $slug = strtolower($slug);

        foreach (self::$slug_prefixes as $prefix) {
            if (strpos($slug, $prefix . '-') === 0) {
                $color_part = substr($slug, strlen($prefix) + 1);
                return self::format_color($color_part);
            }
        }

        return '';
    }

    /**
     * Format raw color slug into display name.
     * "cement-3" → "Cement 3", "warm-grey-1" → "Warm Grey 1"
     *
     * @param string $raw
     * @return string
     */
    private static function format_color($raw) {
        return ucwords(trim(str_replace('-', ' ', $raw)));
    }

    /**
     * Check if a product is a base/generic product (landing page, not purchasable).
     * Base products have their slug exactly matching a prefix with no color suffix.
     *
     * @param WC_Product $product
     * @return bool
     */
    public static function is_base_product($product) {
        $product_id = $product->get_id();

        // Check if this product's ID matches a known base_id
        foreach (OZ_Product_Line_Config::get_all_lines() as $line_key) {
            $base_id = OZ_Product_Line_Config::get_base_product_id($line_key);
            if ($base_id && $base_id == $product_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the most popular variant for a base product (by total_sales).
     * Used for redirect: base product → most-sold color variant.
     *
     * @param int $product_id  The base product ID
     * @return int|false  Most popular variant ID or false
     */
    public static function find_most_popular_variant($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }

        $line_info = OZ_Product_Line_Config::for_product($product);
        if (!$line_info['config'] || empty($line_info['config']['cats'])) {
            return false;
        }

        // Find color variants in the same category that have _oz_color set.
        // This is simpler and more reliable than slug prefix matching, because
        // base product slugs don't always match the variant slug prefix
        // (e.g. base "metallic-stuc" vs variants "metallic-velvet-4m2-pakket-*").
        // Order by total_sales so the most popular variant is returned first.
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'post__not_in'   => [$product_id],
            'fields'         => 'ids',
            'tax_query'      => [[
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $line_info['config']['cats'],
            ]],
            'meta_query'     => [[
                'key'     => '_oz_color',
                'value'   => '',
                'compare' => '!=',
            ]],
            'meta_key'       => 'total_sales',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ];

        $query = new WP_Query($args);
        if (!empty($query->posts)) {
            return $query->posts[0];
        }

        // Fallback: without total_sales ordering (new/imported products)
        unset($args['meta_key']);
        $args['orderby'] = 'date';

        $query = new WP_Query($args);
        if (!empty($query->posts)) {
            return $query->posts[0];
        }

        return false;
    }

    /**
     * Update bidirectional variant links for a product.
     * Finds all products in the same categories and creates _oz_variants meta.
     *
     * @param int   $product_id
     * @param array $category_ids
     */
    private static function update_variants($product_id, $category_ids) {
        if (empty($category_ids)) {
            delete_post_meta($product_id, '_oz_variants');
            return;
        }

        $product_color = get_post_meta($product_id, '_oz_color', true);

        // Find all sibling products in the same category
        $args = [
            'post_type'              => 'product',
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'post__not_in'           => [$product_id],
            'fields'                 => 'ids',
            'tax_query'              => [[
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $category_ids,
            ]],
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        $query       = new WP_Query($args);
        $variant_ids = $query->posts;

        // Filter: exclude products without a real color (base products, tools, etc.)
        $filtered = [];
        foreach ($variant_ids as $vid) {
            $v_color = get_post_meta($vid, '_oz_color', true);
            // Must have a color and it must differ from ours
            if (!empty($v_color) && $v_color !== $product_color) {
                $filtered[] = $vid;
            }
        }

        // Get old variant list before update (for stale backlink pruning)
        $old_variants = get_post_meta($product_id, '_oz_variants', true);
        if (!is_array($old_variants)) {
            $old_variants = [];
        }

        // Save new variant IDs for this product
        update_post_meta($product_id, '_oz_variants', $filtered);

        // Prune stale backlinks: siblings we used to link to but no longer do
        $stale_ids = array_diff($old_variants, $filtered);
        foreach ($stale_ids as $stale_id) {
            $their_list = get_post_meta($stale_id, '_oz_variants', true);
            if (is_array($their_list)) {
                $their_list = array_values(array_diff($their_list, [$product_id]));
                update_post_meta($stale_id, '_oz_variants', $their_list);
            }
        }

        // Bidirectional: add this product to each current variant's list
        foreach ($filtered as $vid) {
            $existing = get_post_meta($vid, '_oz_variants', true);
            if (!is_array($existing)) {
                $existing = [];
            }
            if (!in_array($product_id, $existing)) {
                $existing[] = $product_id;
                update_post_meta($vid, '_oz_variants', array_unique($existing));
            }
        }
    }

    /**
     * Get variant data for frontend display (color swatches).
     * Returns array of [ product_id => [ 'color' => ..., 'url' => ..., 'image' => ... ] ]
     *
     * @param int $product_id
     * @return array
     */
    public static function get_variant_display_data($product_id) {
        $variant_ids = get_post_meta($product_id, '_oz_variants', true);
        if (empty($variant_ids) || !is_array($variant_ids)) {
            return [];
        }

        $variants = [];
        foreach ($variant_ids as $vid) {
            $variant = wc_get_product($vid);
            if (!$variant || $variant->get_status() !== 'publish') {
                continue;
            }

            $color = get_post_meta($vid, '_oz_color', true);
            if (empty($color)) {
                continue;
            }

            $image_id  = get_post_thumbnail_id($vid);
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

            // Gallery images for pushState thumbnail strip rebuild
            $gallery = [];
            foreach ($variant->get_gallery_image_ids() as $gid) {
                $g_thumb = wp_get_attachment_image_url($gid, 'thumbnail');
                $g_large = wp_get_attachment_image_url($gid, 'large');
                if ($g_thumb && $g_large) {
                    $gallery[] = ['thumb' => $g_thumb, 'full' => $g_large];
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
                'price'        => floatval($variant->get_price()),
                'regularPrice' => floatval($variant->get_regular_price()),
                'onSale'       => $variant->is_on_sale(),
                'title'        => $variant->get_name(),
                'description'  => apply_filters('the_content', $variant->get_description()),
            ];
        }

        return $variants;
    }
}
