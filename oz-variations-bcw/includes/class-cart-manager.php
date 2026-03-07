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
     * All oz_ cart item data keys we manage (configured_line mode).
     * Used for session restore, meta persistence, and display filtering.
     */
    private static $meta_keys = [
        'oz_line', 'oz_pu_layers', 'oz_primer', 'oz_colorfresh',
        'oz_toepassing', 'oz_color_mode', 'oz_custom_color',
        'oz_selected_color', 'oz_pakket',
    ];

    /**
     * Prefix for generic addon cart keys.
     * Full key format: oz_addon_{group_key}
     */
    private static $addon_prefix = 'oz_addon_';

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

        // Detect line for line-specific validation (ral_ncs_only, etc.)
        $product = wc_get_product($product_id);
        $line_key = $product ? OZ_Product_Line_Config::detect($product) : null;

        $post_data = self::extract_post_data();
        $error = self::validate_addon_array($post_data, $line_key ?: null);
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

        // Apply configured defaults for any addon keys omitted from POST.
        // E.g. Lavasteen defaults to 1 PU layer (€40) even if not posted.
        if ($line) {
            $defaults = OZ_Product_Line_Config::get_defaults($line);
            foreach ($defaults as $key => $value) {
                if (!isset($cart_item_data[$key]) || $cart_item_data[$key] === '') {
                    $cart_item_data[$key] = $value;
                }
            }
        }

        // Generic addon groups — extract oz_addon_{key} fields from POST
        $addon_data = self::extract_generic_addon_data($product_id);
        foreach ($addon_data as $key => $value) {
            $cart_item_data[$key] = $value;
        }

        // Mark as generic_addons if product has addon groups (for pricing)
        if (!$line && OZ_Product_Line_Config::has_addon_groups($product_id)) {
            $cart_item_data['oz_page_mode'] = 'generic_addons';
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
     * @param array       $data      Sanitized addon data from extract_post_data()
     * @param string|null $line_key  Product line key (optional, enables line-specific checks)
     * @return string|null  Error message or null
     */
    public static function validate_addon_array($data, $line_key = null) {
        $color_mode = isset($data['oz_color_mode']) ? $data['oz_color_mode'] : '';

        // ral_ncs_only lines (PU Color) must use ral_ncs mode
        if ($line_key) {
            $config = OZ_Product_Line_Config::get_config($line_key);
            if ($config && $config['ral_ncs_only'] && $color_mode !== 'ral_ncs') {
                return 'Dit product vereist een RAL of NCS kleurcode.';
            }
        }

        // RAL/NCS mode requires a custom color code
        if ($color_mode === 'ral_ncs') {
            $custom_color = isset($data['oz_custom_color']) ? trim($data['oz_custom_color']) : '';
            if ($custom_color === '') {
                return 'Vul een RAL of NCS kleurcode in.';
            }
        }

        return null;
    }

    /**
     * Extract generic addon selections from $_POST.
     * Reads oz_addon_{key} fields for addon groups defined on this product.
     *
     * @param int $product_id
     * @return array  Sanitized ['oz_addon_{key}' => 'selected label', ...]
     */
    public static function extract_generic_addon_data($product_id) {
        $groups = OZ_Product_Line_Config::get_addon_groups($product_id);
        if (!$groups) {
            return [];
        }

        $data = [];
        foreach ($groups as $group) {
            $post_key = self::$addon_prefix . $group['key'];
            if (isset($_POST[$post_key]) && $_POST[$post_key] !== '') {
                $data[$post_key] = sanitize_text_field(wp_unslash($_POST[$post_key]));
            }
        }
        return $data;
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
        // Configured-line keys
        foreach (self::$meta_keys as $mk) {
            if (isset($values[$mk])) {
                $cart_item[$mk] = $values[$mk];
            }
        }
        // Generic addon keys (oz_addon_*), page mode, and tool price/size overrides
        foreach ($values as $vk => $vv) {
            if (strpos($vk, self::$addon_prefix) === 0
                || $vk === 'oz_page_mode'
                || $vk === 'oz_tool_price'
                || $vk === 'oz_tool_size'
                || $vk === 'oz_wapo_addon') {
                $cart_item[$vk] = $vv;
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
            $product = $cart_item['data'];
            $addon_total = 0;

            // Configured-line products — PU + primer + colorfresh
            if (isset($cart_item['oz_line'])) {
                $addon_total = OZ_Product_Line_Config::resolve_addon_price(
                    $cart_item['oz_line'],
                    $cart_item
                );
            }
            // Generic addon products — oz_addon_* keys
            elseif (isset($cart_item['oz_page_mode']) && $cart_item['oz_page_mode'] === 'generic_addons') {
                $addon_total = OZ_Product_Line_Config::resolve_generic_addon_price(
                    $cart_item['product_id'],
                    $cart_item
                );
            }
            // Tool products with size-specific price override (e.g. Verfbak 18cm)
            elseif (isset($cart_item['oz_tool_price'])) {
                $product->set_price(floatval($cart_item['oz_tool_price']));
                continue; // Price is absolute, not additive — skip base price logic
            }
            else {
                continue; // Not our product
            }

            // Use sale price only when the sale is currently active
            // (not scheduled/expired). We read from DB-backed methods
            // (not get_price()) to avoid stacking addon surcharges on
            // repeated calculate_totals calls.
            $base_price = ($product->is_on_sale())
                ? floatval($product->get_sale_price())
                : floatval($product->get_regular_price());

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
        // Tool products with size override — show size label below name
        if (isset($cart_item['oz_tool_size']) && !empty($cart_item['oz_tool_size'])) {
            $name .= '<div class="oz-cart-addons" style="font-size:12px;color:#666;margin-top:4px;">';
            $name .= esc_html('Maat: ' . $cart_item['oz_tool_size']);
            $name .= '</div>';
            return $name;
        }

        // Handle both configured-line and generic addon products
        if (!isset($cart_item['oz_line']) && !isset($cart_item['oz_page_mode'])) {
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
        // Configured-line keys
        foreach (self::$meta_keys as $cart_key) {
            if (isset($values[$cart_key])) {
                $item->add_meta_data('_' . $cart_key, $values[$cart_key]);
            }
        }
        // Generic addon keys (oz_addon_*), page mode, and tool size overrides
        foreach ($values as $vk => $vv) {
            if (strpos($vk, self::$addon_prefix) === 0
                || $vk === 'oz_page_mode'
                || $vk === 'oz_tool_price'
                || $vk === 'oz_tool_size') {
                $item->add_meta_data('_' . $vk, $vv);
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
        // Restore generic addon keys from order meta
        $all_meta = $item->get_meta_data();
        foreach ($all_meta as $meta) {
            $mk = $meta->key;
            if (strpos($mk, '_' . self::$addon_prefix) === 0) {
                // Remove leading underscore to get cart key format
                $data[substr($mk, 1)] = $meta->value;
            }
            if ($mk === '_oz_page_mode') {
                $data['oz_page_mode'] = $meta->value;
            }
        }

        if (empty($data) || (!isset($data['oz_line']) && !isset($data['oz_page_mode']))) {
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
        $prefix = self::$addon_prefix;
        return array_filter($item_data, function ($d) use ($prefix) {
            if (!isset($d['key'])) return true;
            // Hide configured-line meta keys
            if (in_array($d['key'], self::$meta_keys, true)) return false;
            // Hide generic addon keys (oz_addon_*)
            if (strpos($d['key'], $prefix) === 0) return false;
            // Hide page mode and tool override keys
            if (in_array($d['key'], ['oz_page_mode', 'oz_tool_price', 'oz_tool_size', 'oz_wapo_addon'], true)) return false;
            return true;
        });
    }


    /* ══════════════════════════════════════════════════════════════════
     * TOOL PRODUCTS — extraction + cart addition
     *
     * Tools are standalone WC products (not price addons). Each needs
     * its own add_to_cart() call. These methods keep the AJAX handler
     * thin and maintain a single $_POST extraction boundary.
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Extract and sanitize tool-related POST data.
     * Single point of $_POST access for tool fields.
     *
     * @return array  ['mode' => string, 'set_id' => int, 'extras' => array, 'tools' => array]
     */
    public static function extract_tool_post_data() {
        $mode   = isset($_POST['oz_tool_mode']) ? sanitize_text_field(wp_unslash($_POST['oz_tool_mode'])) : '';
        $set_id = isset($_POST['oz_tool_set_id']) ? intval($_POST['oz_tool_set_id']) : 0;

        $extras = self::sanitize_tool_items_array(isset($_POST['oz_extras']) ? $_POST['oz_extras'] : []);
        $tools  = self::sanitize_tool_items_array(isset($_POST['oz_tools']) ? $_POST['oz_tools'] : []);

        return [
            'mode'   => $mode,
            'set_id' => $set_id,
            'extras' => $extras,
            'tools'  => $tools,
        ];
    }

    /**
     * Parse tool data into a flat list of cart-ready items.
     * Pure function — no I/O, no $_POST, no WC calls.
     *
     * @param array $tool_data  From extract_tool_post_data()
     * @return array  List of ['product_id' => int, 'qty' => int, 'cart_data' => array]
     */
    public static function parse_tool_items(array $tool_data) {
        $items = [];

        if ($tool_data['mode'] === 'set') {
            // The Kant & Klaar set itself — no custom data so identical adds merge
            if ($tool_data['set_id'] > 0) {
                $items[] = ['product_id' => $tool_data['set_id'], 'qty' => 1, 'cart_data' => []];
            }

            // Extras on top of set
            foreach ($tool_data['extras'] as $extra_id => $extra) {
                if ($extra['wcId'] > 0 && $extra['qty'] > 0) {
                    // Only add cart_data for size variants — keeps tools mergeable
                    // Tools with same wcId + same size will share the same cart hash
                    $cart_data = [];
                    if (!empty($extra['sizeLabel'])) {
                        $cart_data['oz_tool_size'] = $extra['sizeLabel'];
                        // Price override only needed for non-default sizes
                        if ($extra['price'] > 0) {
                            $cart_data['oz_tool_price'] = $extra['price'];
                        }
                    }
                    $items[] = ['product_id' => $extra['wcId'], 'qty' => $extra['qty'], 'cart_data' => $cart_data];
                }
            }
        } elseif ($tool_data['mode'] === 'individual') {
            // Each selected individual tool
            foreach ($tool_data['tools'] as $tool_id => $tool) {
                if ($tool['wcId'] > 0 && $tool['qty'] > 0) {
                    // Only add cart_data for size variants — keeps tools mergeable
                    $cart_data = [];
                    if (!empty($tool['sizeLabel'])) {
                        $cart_data['oz_tool_size'] = $tool['sizeLabel'];
                        if ($tool['price'] > 0) {
                            $cart_data['oz_tool_price'] = $tool['price'];
                        }
                    }
                    $items[] = ['product_id' => $tool['wcId'], 'qty' => $tool['qty'], 'cart_data' => $cart_data];
                }
            }
        }

        return $items;
    }

    /**
     * Add parsed tool items to the WC cart.
     * Imperative shell — validates product existence and calls add_to_cart().
     *
     * @param array $tool_items  From parse_tool_items()
     */
    public static function add_tool_products_to_cart(array $tool_items) {
        foreach ($tool_items as $item) {
            if (wc_get_product($item['product_id'])) {
                WC()->cart->add_to_cart($item['product_id'], $item['qty'], 0, [], $item['cart_data']);
            }
        }
    }

    /**
     * Sanitize a nested tool items array from $_POST.
     * Handles oz_extras[id][qty], oz_extras[id][wcId], oz_extras[id][wapoAddon].
     *
     * @param mixed $raw  Raw $_POST array (may not be array)
     * @return array  Sanitized ['id' => ['qty' => int, 'wcId' => int, 'wapoAddon' => string]]
     */
    private static function sanitize_tool_items_array($raw) {
        if (!is_array($raw)) {
            return [];
        }

        $sanitized = [];
        foreach ($raw as $id => $data) {
            if (!is_array($data)) {
                continue;
            }
            $sanitized[sanitize_text_field($id)] = [
                'qty'       => isset($data['qty']) ? max(1, intval($data['qty'])) : 1,
                'wcId'      => isset($data['wcId']) ? intval($data['wcId']) : 0,
                'wapoAddon' => isset($data['wapoAddon']) ? sanitize_text_field($data['wapoAddon']) : '',
                'price'     => isset($data['price']) ? floatval($data['price']) : 0,
                'sizeLabel' => isset($data['sizeLabel']) ? sanitize_text_field($data['sizeLabel']) : '',
            ];
        }
        return $sanitized;
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
    /**
     * Public wrapper for build_addon_details — used by cart drawer AJAX.
     *
     * @param array $data  Cart item data
     * @return array  Human-readable addon detail strings
     */
    public static function get_addon_details($data) {
        return self::build_addon_details($data);
    }

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

        // Primer — skip "Geen" variants (no value to display)
        if (!empty($data['oz_primer'])) {
            $primer = $data['oz_primer'];
            if (stripos($primer, 'geen') === false) {
                $details[] = 'Primer: ' . $primer;
            }
        }

        // Colorfresh — skip "Zonder" variants
        if (!empty($data['oz_colorfresh'])) {
            $cf = $data['oz_colorfresh'];
            if (stripos($cf, 'zonder') === false) {
                $details[] = $cf;
            }
        }

        // Toepassing
        if (!empty($data['oz_toepassing'])) {
            $details[] = 'Toepassing: ' . $data['oz_toepassing'];
        }

        // Generic addon groups — show each selected addon with its group label
        // Reads oz_addon_{key} from cart data and resolves the group label from config
        $product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;
        if ($product_id) {
            $groups = OZ_Product_Line_Config::get_addon_groups($product_id);
            if ($groups) {
                foreach ($groups as $group) {
                    $cart_key = self::$addon_prefix . $group['key'];
                    if (!empty($data[$cart_key])) {
                        $details[] = $group['label'] . ': ' . $data[$cart_key];
                    }
                }
            }
        }

        return $details;
    }
}
