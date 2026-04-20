<?php
/**
 * Weight-per-m² for configured-line products.
 *
 * Adds a "Gewicht per m² (kg)" admin field on the product edit screen
 * and overrides cart-item weight at calculation time:
 *
 *   effective_weight_per_unit = kg_per_m² × line.unitM2
 *
 * WooCommerce multiplies by cart quantity, so the cart-level weight
 * always equals kg_per_m² × total m² for that line item.
 *
 * Falls back to WooCommerce's standard _weight for any product that
 * doesn't have _oz_weight_per_m2 set or isn't part of a configured line.
 *
 * @package OZ_Variations_BCW
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_Weight_Per_M2 {

    const META_KEY = '_oz_weight_per_m2';

    public static function init() {
        add_action('woocommerce_product_options_shipping', [__CLASS__, 'render_admin_field']);
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_admin_field']);

        add_action('woocommerce_before_calculate_totals', [__CLASS__, 'apply_cart_weights'], 20);
        add_action('woocommerce_cart_loaded_from_session', [__CLASS__, 'apply_cart_weights'], 20);
    }

    /**
     * Renders the kg/m² numeric input next to WooCommerce's standard Weight field
     * on the Shipping tab of the product edit screen.
     */
    public static function render_admin_field() {
        woocommerce_wp_text_input([
            'id'          => self::META_KEY,
            'label'       => __('Gewicht per m² (kg)', 'oz-variations-bcw'),
            'desc_tip'    => true,
            'description' => __(
                'Vul in voor producten die per m² worden besteld (Original, Easyline, All-In-One, PU Basis, etc.). ' .
                'Laat leeg voor platte producten (gereedschap, primers, kleurstalen) — die gebruiken het Gewicht-veld hierboven.',
                'oz-variations-bcw'
            ),
            'type'        => 'number',
            'custom_attributes' => [
                'step' => '0.01',
                'min'  => '0',
            ],
        ]);
    }

    public static function save_admin_field($post_id) {
        $value = isset($_POST[self::META_KEY]) ? wc_clean(wp_unslash($_POST[self::META_KEY])) : '';
        if ($value === '') {
            delete_post_meta($post_id, self::META_KEY);
            return;
        }
        update_post_meta($post_id, self::META_KEY, wc_format_decimal($value));
    }

    /**
     * Sets per-unit weight on configured-line cart items.
     *
     * Cloned product instance is mutated (same pattern as addon price override)
     * so the change only affects this cart item, not the base product.
     */
    public static function apply_cart_weights($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        if (!$cart || !method_exists($cart, 'get_cart')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item['oz_line'])) {
                continue;
            }

            $weight = self::compute_weight_per_unit($cart_item['product_id'], $cart_item['oz_line']);
            if ($weight === null) {
                continue;
            }

            $cart_item['data']->set_weight($weight);
        }
    }

    /**
     * Returns kg for a single cart unit of this line:
     *   kg_per_m² × line.unitM2
     * Or null when either value is missing — caller falls back to _weight.
     */
    private static function compute_weight_per_unit($product_id, $line_key) {
        $kg_per_m2 = floatval(get_post_meta($product_id, self::META_KEY, true));
        if ($kg_per_m2 <= 0) {
            return null;
        }

        $config = OZ_Product_Line_Config::get_config($line_key);
        if (!$config || empty($config['unitM2'])) {
            return null;
        }

        return $kg_per_m2 * floatval($config['unitM2']);
    }
}
