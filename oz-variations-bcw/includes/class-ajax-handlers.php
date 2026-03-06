<?php
/**
 * AJAX Handlers for BCW
 *
 * Handles admin reprocessing and frontend add-to-cart AJAX.
 *
 * @package OZ_Variations_BCW
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_Ajax_Handlers {

    /**
     * Initialize AJAX hooks.
     */
    public static function init() {
        // Admin: reprocess all products (variant linking)
        add_action('wp_ajax_oz_bcw_reprocess', [__CLASS__, 'ajax_reprocess_products']);

        // Frontend: add to cart with addon data
        add_action('wp_ajax_oz_bcw_add_to_cart', [__CLASS__, 'ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_oz_bcw_add_to_cart', [__CLASS__, 'ajax_add_to_cart']);
    }

    /**
     * Reprocess all products to rebuild variant relationships.
     * Admin only — triggered from settings page.
     */
    public static function ajax_reprocess_products() {
        check_ajax_referer('oz_bcw_reprocess', '_wpnonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Geen toegang.');
        }

        $products = wc_get_products([
            'status' => 'publish',
            'limit'  => -1,
            'return' => 'ids',
        ]);

        $processed     = 0;
        $with_variants = 0;

        foreach ($products as $pid) {
            OZ_Product_Processor::process_product($pid);
            $processed++;

            $variants = get_post_meta($pid, '_oz_variants', true);
            if (!empty($variants) && is_array($variants)) {
                $with_variants++;
            }
        }

        wp_send_json_success([
            'message' => sprintf(
                '%d producten verwerkt, %d hebben kleurvarianten.',
                $processed,
                $with_variants
            ),
        ]);
    }

    /**
     * AJAX add-to-cart with addon data.
     * Called from the product page JS instead of standard WooCommerce add-to-cart.
     */
    public static function ajax_add_to_cart() {
        check_ajax_referer('oz_bcw_cart', 'nonce');

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $quantity   = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

        if (!$product_id || $quantity < 1) {
            wp_send_json_error('Ongeldig product of aantal.');
        }

        $product = wc_get_product($product_id);
        if (!$product || $product->get_status() !== 'publish') {
            wp_send_json_error('Product niet gevonden.');
        }

        // Quantity bounds
        $quantity = max(1, min(99, $quantity));

        // Validate addon data using shared pure function (single source of truth)
        $post_data = OZ_Cart_Manager::extract_post_data();
        $error = OZ_Cart_Manager::validate_addon_array($post_data);
        if ($error) {
            wp_send_json_error($error);
        }

        // The cart item data is captured by OZ_Cart_Manager::capture_addon_data
        // via the woocommerce_add_cart_item_data filter. We just need to add to cart.
        $cart_key = WC()->cart->add_to_cart($product_id, $quantity);

        if (!$cart_key) {
            wp_send_json_error('Kon product niet toevoegen aan winkelmand.');
        }

        // Return fresh cart data for the drawer
        wp_send_json_success([
            'cart_key'   => $cart_key,
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'subtotal'   => WC()->cart->get_cart_subtotal(),
            'cart_total'  => WC()->cart->get_total('edit'),
        ]);
    }
}
