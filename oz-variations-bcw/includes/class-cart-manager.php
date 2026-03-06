<?php
/**
 * Cart Manager for BCW
 *
 * Captures addon selections into cart item data, prices them via
 * woocommerce_before_calculate_totals, displays them in cart/order,
 * and persists them to order meta.
 *
 * Cart item data keys (frozen contract from WAPO-PARITY-CONFIG.md §14):
 *   oz_line           => string  product line key
 *   oz_pu_layers      => int     0–3
 *   oz_primer         => string  selected primer label (or empty)
 *   oz_colorfresh     => string  "Met Colorfresh" or empty (Original only)
 *   oz_toepassing     => string  "Vloer", "Wand", etc. or empty (Original only)
 *   oz_color_mode     => string  "standard" | "ral_ncs"
 *   oz_custom_color   => string  RAL/NCS code when color_mode = "ral_ncs"
 *   oz_selected_color => string  chosen swatch name (single-product color lines)
 *   oz_pakket         => string  pakket label (Original, Easyline) or empty
 *
 * @package OZ_Variations_BCW
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_Cart_Manager {

    /**
     * All oz_ cart item data keys we manage.
     * Used for session restore, meta persistence, and display filtering.
     */
    private static $meta_keys = [
        'oz_line', 'oz_pu_layers', 'oz_primer', 'oz_colorfresh',
        'oz_toepassing', 'oz_color_mode', 'oz_custom_color',
        'oz_selected_color', 'oz_pakket',
    ];

    /**
     * Initialize cart hooks.
     */
    public static function init() {
        // Validate addon data before adding to cart (covers all add-to-cart paths)
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_addon_data'], 10, 2);

        // Capture addon selections when adding to cart
        add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'capture_addon_data'], 10, 2);

        // Restore addon data from session
        add_filter('woocommerce_get_cart_item_from_session', [__CLASS__, 'restore_from_session'], 10, 3);

        // Price calculation: add addon surcharges to product price
        add_action('woocommerce_before_calculate_totals', [__CLASS__, 'calculate_addon_prices'], 10, 1);

        // Also recalculate when cart loads from session (page refresh)
        add_action('woocommerce_cart_loaded_from_session', [__CLASS__, 'calculate_addon_prices'], 5, 1);

        // Display addon details in cart item name
        add_filter('woocommerce_cart_item_name', [__CLASS__, 'display_addons_in_cart'], 10, 3);
        add_filter('woocommerce_widget_cart_item_name', [__CLASS__, 'display_addons_in_cart'], 10, 3);

        // Save addon data to order items
        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'save_addons_to_order'], 10, 4);

        // Display addons in order confirmation / admin
        add_filter('woocommerce_order_item_name', [__CLASS__, 'display_addons_in_order'], 10, 2);

        // Hide raw meta keys from cart display
        add_filter('woocommerce_get_item_data', [__CLASS__, 'hide_raw_meta'], 10, 2);
    }

    /**
     * Validate addon data before adding to cart.
     * Runs on ALL add-to-cart paths (AJAX and standard form).
     *
     * @param bool $passed
     * @param int  $product_id
     * @return bool
     */
    public static function validate_addon_data($passed, $product_id) {
        if (!$passed) {
            return false;
        }

        $post_data = self::extract_post_data();
        $error = self::validate_addon_array($post_data);
        if ($error) {
            wc_add_notice($error, 'error');
            return false;
        }

        return $passed;
    }

    /**
     * Capture addon selections from POST data when adding to cart.
     *
     * @param array $cart_item_data
     * @param int   $product_id
     * @return array
     */
    public static function capture_addon_data($cart_item_data, $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return $cart_item_data;
        }

        // Detect product line
        $line = OZ_Product_Line_Config::detect($product);
        if ($line) {
            $cart_item_data['oz_line'] = $line;
        }

        // Extract and merge sanitized POST data into cart item
        $post_data = self::extract_post_data();
        foreach ($post_data as $key => $value) {
            if ($value !== '' && $value !== null) {
                $cart_item_data[$key] = $value;
            }
        }

        return $cart_item_data;
    }

    /**
     * Extract and sanitize all oz_ addon fields from $_POST.
     * Single point of $_POST access — imperative shell boundary.
     *
     * @return array  Sanitized addon data (keys may be empty string if not posted)
     */
    public static function extract_post_data() {
        $text = function ($key) {
            return isset($_POST[$key]) && $_POST[$key] !== ''
                ? sanitize_text_field(wp_unslash($_POST[$key]))
                : '';
        };

        $data = [
            'oz_primer'         => $text('oz_primer'),
            'oz_colorfresh'     => $text('oz_colorfresh'),
            'oz_toepassing'     => $text('oz_toepassing'),
            'oz_custom_color'   => $text('oz_custom_color'),
            'oz_selected_color' => $text('oz_selected_color'),
            'oz_pakket'         => $text('oz_pakket'),
        ];

        // PU layers (0-3, clamped)
        if (isset($_POST['oz_pu_layers'])) {
            $data['oz_pu_layers'] = max(0, min(3, intval($_POST['oz_pu_layers'])));
        }

        // Color mode (whitelist)
        $mode = $text('oz_color_mode');
        if (in_array($mode, ['standard', 'ral_ncs'], true)) {
            $data['oz_color_mode'] = $mode;
        }

        return $data;
    }

    /**
     * Validate addon data array. Pure function — no side effects.
     * Returns error message string on failure, null on success.
     *
     * @param array $data  Sanitized addon data from extract_post_data()
     * @return string|null  Error message or null
     */
    public static function validate_addon_array($data) {
        // RAL/NCS mode requires a custom color code
        $color_mode = isset($data['oz_color_mode']) ? $data['oz_color_mode'] : '';
        if ($color_mode === 'ral_ncs') {
            $custom_color = isset($data['oz_custom_color']) ? trim($data['oz_custom_color']) : '';
            if ($custom_color === '') {
                return 'Vul een RAL of NCS kleurcode in.';
            }
        }

        return null;
    }

    /**
     * Restore addon data from session after page load.
     *
     * @param array  $cart_item
     * @param array  $values     Session values
     * @param string $key        Cart item key
     * @return array
     */
    public static function restore_from_session($cart_item, $values, $key) {
        foreach (self::$meta_keys as $mk) {
            if (isset($values[$mk])) {
                $cart_item[$mk] = $values[$mk];
            }
        }
        return $cart_item;
    }

    /**
     * Calculate addon prices and update cart item prices.
     * Uses OZ_Product_Line_Config::resolve_addon_price() for the actual math.
     *
     * @param WC_Cart $cart
     */
    public static function calculate_addon_prices($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        if (!$cart || !method_exists($cart, 'get_cart')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_key => $cart_item) {
            if (!isset($cart_item['oz_line'])) {
                continue;
            }

            $product = $cart_item['data'];

            // Use sale price only when the sale is currently active
            // (not scheduled/expired). We read from DB-backed methods
            // (not get_price()) to avoid stacking addon surcharges on
            // repeated calculate_totals calls.
            $base_price = ($product->is_on_sale())
                ? floatval($product->get_sale_price())
                : floatval($product->get_regular_price());

            // Single call resolves PU + primer + colorfresh
            $addon_total = OZ_Product_Line_Config::resolve_addon_price(
                $cart_item['oz_line'],
                $cart_item
            );

            // Set modified price (base + addons per unit, WooCommerce multiplies by qty)
            $product->set_price($base_price + $addon_total);
        }
    }

    /**
     * Display addon details below product name in cart.
     *
     * @param string $name
     * @param array  $cart_item
     * @param string $cart_item_key
     * @return string
     */
    public static function display_addons_in_cart($name, $cart_item, $cart_item_key) {
        if (!isset($cart_item['oz_line'])) {
            return $name;
        }

        $details = self::build_addon_details($cart_item);

        if (!empty($details)) {
            $name .= '<div class="oz-cart-addons" style="font-size:12px;color:#666;margin-top:4px;">';
            $name .= implode('<br>', array_map('esc_html', $details));
            $name .= '</div>';
        }

        return $name;
    }

    /**
     * Save addon data to WooCommerce order line items.
     *
     * @param WC_Order_Item_Product $item
     * @param string                $cart_item_key
     * @param array                 $values
     * @param WC_Order              $order
     */
    public static function save_addons_to_order($item, $cart_item_key, $values, $order) {
        foreach (self::$meta_keys as $cart_key) {
            if (isset($values[$cart_key])) {
                // Prefix with underscore for hidden order meta
                $item->add_meta_data('_' . $cart_key, $values[$cart_key]);
            }
        }
    }

    /**
     * Display addon details in order confirmation and admin.
     *
     * @param string        $name
     * @param WC_Order_Item $item
     * @return string
     */
    public static function display_addons_in_order($name, $item) {
        // Rebuild cart-like array from order meta
        $data = [];
        foreach (self::$meta_keys as $key) {
            $val = $item->get_meta('_' . $key);
            if ($val !== '' && $val !== null) {
                $data[$key] = $val;
            }
        }

        if (empty($data) || !isset($data['oz_line'])) {
            return $name;
        }

        $details = self::build_addon_details($data);

        if (!empty($details)) {
            $name .= '<div class="oz-order-addons" style="font-size:12px;color:#666;margin-top:4px;">';
            $name .= implode('<br>', array_map('esc_html', $details));
            $name .= '</div>';
        }

        return $name;
    }

    /**
     * Hide raw oz_ meta keys from the default cart item data display.
     *
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public static function hide_raw_meta($item_data, $cart_item) {
        return array_filter($item_data, function ($d) {
            return !isset($d['key']) || !in_array($d['key'], self::$meta_keys, true);
        });
    }


    /* ══════════════════════════════════════════════════════════════════
     * INTERNAL: Build human-readable addon details array
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Build array of human-readable addon detail strings.
     * Used by both cart and order display.
     *
     * @param array $data  Cart item data or reconstructed order meta
     * @return array
     */
    private static function build_addon_details($data) {
        $details = [];

        // Custom color (RAL/NCS)
        if (isset($data['oz_color_mode']) && $data['oz_color_mode'] === 'ral_ncs') {
            $code = isset($data['oz_custom_color']) ? $data['oz_custom_color'] : '';
            $details[] = 'RAL/NCS kleur: ' . $code;
        }

        // Standard swatch color (single-product lines like Betonlook Verf)
        if (!empty($data['oz_selected_color']) &&
            (!isset($data['oz_color_mode']) || $data['oz_color_mode'] === 'standard')) {
            $details[] = 'Kleur: ' . $data['oz_selected_color'];
        }

        // Pakket
        if (!empty($data['oz_pakket'])) {
            $details[] = 'Pakket: ' . $data['oz_pakket'];
        }

        // PU layers
        if (isset($data['oz_pu_layers']) && intval($data['oz_pu_layers']) > 0) {
            $layers = intval($data['oz_pu_layers']);
            $label  = $layers === 1 ? '1 toplaag PU' : $layers . ' toplagen PU';
            $details[] = $label;
        }

        // Primer
        if (!empty($data['oz_primer'])) {
            $details[] = 'Primer: ' . $data['oz_primer'];
        }

        // Colorfresh
        if (!empty($data['oz_colorfresh'])) {
            $details[] = $data['oz_colorfresh'];
        }

        // Toepassing
        if (!empty($data['oz_toepassing'])) {
            $details[] = 'Toepassing: ' . $data['oz_toepassing'];
        }

        return $details;
    }
}
