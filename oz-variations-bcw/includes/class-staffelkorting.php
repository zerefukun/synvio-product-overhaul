<?php
/**
 * Staffelkorting (tiered volume discount) for configured-line products.
 *
 * Replaces the 4 active cart-discount rules in easy-woocommerce-discounts-pro:
 *   >45m² → 15% | >60m² → 20% | >99m² → 25% (on category "Beton Ciré")
 *
 * Rules live in the option `oz_bcw_staffelkorting` so Patrick / Iboyle can
 * edit thresholds themselves. At cart time we sum m² across configured-line
 * items and apply the matching tier as a negative fee.
 *
 * Fee-based (not per-item price override) so:
 *   - the saving is visible as its own line ("Staffelkorting -€X")
 *   - line-item prices stay untouched, avoiding stacking bugs with addon pricing
 *   - WooCommerce tax + coupon interactions are standard
 *
 * @package OZ_Variations_BCW
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_Staffelkorting {

    const OPTION_KEY = 'oz_bcw_staffelkorting';

    /**
     * Default rules — mirrors the 4 active rules in easy-woocommerce-discounts-pro
     * as of 2026-04-20. Seeded on first init if no option exists.
     */
    private static $default_rules = [
        'enabled' => true,
        'tiers'   => [
            ['threshold_m2' => 45, 'discount_pct' => 15],
            ['threshold_m2' => 60, 'discount_pct' => 20],
            ['threshold_m2' => 99, 'discount_pct' => 25],
        ],
    ];

    public static function init() {
        self::seed_defaults_if_missing();

        add_action('woocommerce_cart_calculate_fees', [__CLASS__, 'apply_discount_fee'], 20);
        add_action('admin_menu', [__CLASS__, 'register_admin_page'], 20);
        add_action('admin_post_oz_bcw_save_staffelkorting', [__CLASS__, 'handle_save']);
    }

    private static function seed_defaults_if_missing() {
        if (get_option(self::OPTION_KEY) === false) {
            update_option(self::OPTION_KEY, self::$default_rules);
        }
    }

    public static function get_rules() {
        $rules = get_option(self::OPTION_KEY, self::$default_rules);
        if (!is_array($rules) || !isset($rules['tiers'])) {
            return self::$default_rules;
        }
        return $rules;
    }

    /**
     * Applies the tiered discount as a negative fee on the cart.
     *
     * Scope: only items with oz_line set (configured per-m² products).
     * Tools, kleurstalen, primers, and any non-configured product are
     * excluded from both the m² total and the discount base.
     */
    public static function apply_discount_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        if (!$cart || !method_exists($cart, 'get_cart')) {
            return;
        }

        $rules = self::get_rules();
        if (empty($rules['enabled']) || empty($rules['tiers'])) {
            return;
        }

        $total_m2 = 0.0;
        $eligible_subtotal = 0.0;

        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item['oz_line'])) {
                continue;
            }
            $m2_per_unit = self::resolve_unit_m2($cart_item['oz_line']);
            if ($m2_per_unit <= 0) {
                continue;
            }

            $qty = isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 0;
            $total_m2 += $m2_per_unit * $qty;

            $line_price = isset($cart_item['data']) ? floatval($cart_item['data']->get_price()) : 0;
            $eligible_subtotal += $line_price * $qty;
        }

        if ($total_m2 <= 0 || $eligible_subtotal <= 0) {
            return;
        }

        $tier = self::find_applicable_tier($rules['tiers'], $total_m2);
        if (!$tier) {
            return;
        }

        $discount_amount = $eligible_subtotal * ($tier['discount_pct'] / 100);
        if ($discount_amount <= 0) {
            return;
        }

        $label = sprintf(
            __('Staffelkorting %d%% (%sm²)', 'oz-variations-bcw'),
            intval($tier['discount_pct']),
            number_format($total_m2, 1, ',', '')
        );

        $cart->add_fee($label, -$discount_amount, true);
    }

    /**
     * Reads unitM2 from the line config, 0 if unresolvable.
     */
    private static function resolve_unit_m2($line_key) {
        $config = OZ_Product_Line_Config::get_config($line_key);
        if (!$config || empty($config['unitM2'])) {
            return 0;
        }
        return floatval($config['unitM2']);
    }

    /**
     * Picks the highest-percentage tier whose threshold has been reached.
     * Returns null if no tier qualifies.
     */
    private static function find_applicable_tier($tiers, $total_m2) {
        $best = null;
        foreach ($tiers as $tier) {
            if (!isset($tier['threshold_m2'], $tier['discount_pct'])) {
                continue;
            }
            if ($total_m2 < floatval($tier['threshold_m2'])) {
                continue;
            }
            if ($best === null || floatval($tier['discount_pct']) > floatval($best['discount_pct'])) {
                $best = $tier;
            }
        }
        return $best;
    }

    public static function register_admin_page() {
        add_submenu_page(
            'woocommerce',
            __('Staffelkorting', 'oz-variations-bcw'),
            __('BCW Staffelkorting', 'oz-variations-bcw'),
            'manage_woocommerce',
            'oz-bcw-staffelkorting',
            [__CLASS__, 'render_admin_page']
        );
    }

    public static function render_admin_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Onvoldoende rechten.', 'oz-variations-bcw'));
        }

        $rules = self::get_rules();
        $saved = isset($_GET['saved']) && $_GET['saved'] === '1';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Staffelkorting', 'oz-variations-bcw'); ?></h1>
            <p><?php esc_html_e('Volumekorting op beton ciré producten (alleen per-m² items). Telt m² op per bestelling en past het hoogste van toepassing zijnde tarief toe.', 'oz-variations-bcw'); ?></p>

            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Opgeslagen.', 'oz-variations-bcw'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="oz_bcw_save_staffelkorting">
                <?php wp_nonce_field('oz_bcw_save_staffelkorting'); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="oz_sk_enabled"><?php esc_html_e('Actief', 'oz-variations-bcw'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="oz_sk_enabled" name="enabled" value="1" <?php checked(!empty($rules['enabled'])); ?>>
                                <?php esc_html_e('Staffelkorting toepassen op cart totaal', 'oz-variations-bcw'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Staffeltarieven', 'oz-variations-bcw'); ?></h2>
                <p class="description"><?php esc_html_e('Eén regel per staffel. Drempel = minimum m² om dit tarief te activeren. Korting geldt op alle per-m² producten in de cart.', 'oz-variations-bcw'); ?></p>

                <table class="widefat" id="oz-staffel-table" style="max-width:600px;">
                    <thead>
                        <tr>
                            <th style="width:40%;"><?php esc_html_e('Drempel (m²)', 'oz-variations-bcw'); ?></th>
                            <th style="width:40%;"><?php esc_html_e('Korting (%)', 'oz-variations-bcw'); ?></th>
                            <th style="width:20%;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $tiers = isset($rules['tiers']) && is_array($rules['tiers']) ? $rules['tiers'] : [];
                        if (empty($tiers)) $tiers = [['threshold_m2' => '', 'discount_pct' => '']];
                        foreach ($tiers as $i => $tier) :
                            $threshold = isset($tier['threshold_m2']) ? $tier['threshold_m2'] : '';
                            $pct = isset($tier['discount_pct']) ? $tier['discount_pct'] : '';
                        ?>
                            <tr>
                                <td><input type="number" name="tiers[<?php echo (int)$i; ?>][threshold_m2]" value="<?php echo esc_attr($threshold); ?>" step="0.1" min="0" style="width:100%;"></td>
                                <td><input type="number" name="tiers[<?php echo (int)$i; ?>][discount_pct]" value="<?php echo esc_attr($pct); ?>" step="0.1" min="0" max="100" style="width:100%;"></td>
                                <td><button type="button" class="button oz-sk-remove"><?php esc_html_e('Verwijder', 'oz-variations-bcw'); ?></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="button" class="button" id="oz-sk-add"><?php esc_html_e('+ Staffel toevoegen', 'oz-variations-bcw'); ?></button></p>

                <?php submit_button(__('Opslaan', 'oz-variations-bcw')); ?>
            </form>
        </div>

        <script>
        (function(){
            var table = document.querySelector('#oz-staffel-table tbody');
            var addBtn = document.getElementById('oz-sk-add');
            function reindex() {
                table.querySelectorAll('tr').forEach(function(row, idx){
                    row.querySelectorAll('input').forEach(function(input){
                        input.name = input.name.replace(/tiers\[\d+\]/, 'tiers[' + idx + ']');
                    });
                });
            }
            addBtn.addEventListener('click', function(){
                var row = document.createElement('tr');
                row.innerHTML = '<td><input type="number" name="tiers[x][threshold_m2]" step="0.1" min="0" style="width:100%;"></td>' +
                    '<td><input type="number" name="tiers[x][discount_pct]" step="0.1" min="0" max="100" style="width:100%;"></td>' +
                    '<td><button type="button" class="button oz-sk-remove">Verwijder</button></td>';
                table.appendChild(row);
                reindex();
            });
            table.addEventListener('click', function(e){
                if (e.target && e.target.classList.contains('oz-sk-remove')) {
                    e.target.closest('tr').remove();
                    reindex();
                }
            });
        })();
        </script>
        <?php
    }

    public static function handle_save() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Onvoldoende rechten.', 'oz-variations-bcw'));
        }
        check_admin_referer('oz_bcw_save_staffelkorting');

        $enabled = !empty($_POST['enabled']);
        $raw_tiers = isset($_POST['tiers']) && is_array($_POST['tiers']) ? $_POST['tiers'] : [];

        $tiers = [];
        foreach ($raw_tiers as $row) {
            $threshold = isset($row['threshold_m2']) ? floatval($row['threshold_m2']) : 0;
            $pct = isset($row['discount_pct']) ? floatval($row['discount_pct']) : 0;
            if ($threshold <= 0 || $pct <= 0) {
                continue;
            }
            $tiers[] = [
                'threshold_m2' => $threshold,
                'discount_pct' => $pct,
            ];
        }

        usort($tiers, function($a, $b) {
            return $a['threshold_m2'] <=> $b['threshold_m2'];
        });

        update_option(self::OPTION_KEY, [
            'enabled' => $enabled,
            'tiers'   => $tiers,
        ]);

        wp_safe_redirect(admin_url('admin.php?page=oz-bcw-staffelkorting&saved=1'));
        exit;
    }
}
