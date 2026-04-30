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
     * Cookie set by oz-forms.js when a kleurstalen-* form is submitted.
     * Value is the unix timestamp of submission. Same name is also used
     * as a user_meta key for logged-in customers (see capture_kleurstalen_redemption).
     */
    const KLEURSTALEN_COOKIE = 'oz_kleurstalen_redeemed_at';
    const KLEURSTALEN_USER_META = 'oz_kleurstalen_redeemed_at';

    /**
     * Default rules — mirrors the 4 active rules in easy-woocommerce-discounts-pro
     * as of 2026-04-20. Seeded on first init if no option exists.
     *
     * Sample-redeemed bonus (added 30/04/26): when a customer submitted a
     * kleurstalen form within `sample_bonus_window_days`, we add an extra
     * percentage on the eligible subtotal as a separate cart fee. Closes the
     * kleurstalen → buy loop so today's UX work earns its keep.
     */
    private static $default_rules = [
        'enabled' => true,
        'tiers'   => [
            ['threshold_m2' => 45, 'discount_pct' => 15],
            ['threshold_m2' => 60, 'discount_pct' => 20],
            ['threshold_m2' => 99, 'discount_pct' => 25],
        ],
        'sample_bonus' => [
            'enabled'     => false, // off by default; Patrick toggles in admin
            'pct'         => 5,
            'window_days' => 30,
        ],
        // Returning-customer bonus (added 30/04/26): when a customer's
        // billing email has at least `min_orders` previously completed orders,
        // we add an extra percentage as a cart fee. CLV play that complements
        // the kleurstalen first-order bonus.
        'returning_bonus' => [
            'enabled'    => false,
            'pct'        => 5,
            'min_orders' => 1, // 1 = "second order or later", 2 = "third order or later"
        ],
    ];

    public static function init() {
        self::seed_defaults_if_missing();

        add_action('woocommerce_cart_calculate_fees', [__CLASS__, 'apply_discount_fee'], 20);
        add_action('admin_menu', [__CLASS__, 'register_admin_page'], 20);
        add_action('admin_post_oz_bcw_save_staffelkorting', [__CLASS__, 'handle_save']);

        // Capture kleurstalen redemptions for logged-in users — server-side
        // mirror of the cookie set client-side by oz-forms.js. Logged-out
        // users rely on the cookie alone.
        add_action('oz_forms_submission_stored', [__CLASS__, 'capture_kleurstalen_redemption'], 10, 4);
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
        if ($tier) {
            $discount_amount = $eligible_subtotal * ($tier['discount_pct'] / 100);
            if ($discount_amount > 0) {
                $label = sprintf(
                    __('Staffelkorting %d%% (%sm²)', 'oz-variations-bcw'),
                    intval($tier['discount_pct']),
                    number_format($total_m2, 1, ',', '')
                );
                $cart->add_fee($label, -$discount_amount, true);
            }
        }

        // Sample-redeemed bonus: independent of the tier discount. Stacks on
        // top so a customer who requested kleurstalen AND ordered 60m² gets
        // both. Cache-safe: this fee only fires on the cart/checkout page,
        // both of which are uncached. See memory/reference_cache_safety_for_pricing.md.
        $bonus = isset($rules['sample_bonus']) && is_array($rules['sample_bonus'])
            ? $rules['sample_bonus']
            : self::$default_rules['sample_bonus'];

        if (!empty($bonus['enabled']) && self::has_recent_kleurstalen_redemption($bonus['window_days'] ?? 30)) {
            $bonus_pct = floatval($bonus['pct'] ?? 0);
            if ($bonus_pct > 0) {
                $bonus_amount = $eligible_subtotal * ($bonus_pct / 100);
                if ($bonus_amount > 0) {
                    $label = sprintf(
                        __('Kleurstalen-bonus %s%%', 'oz-variations-bcw'),
                        rtrim(rtrim(number_format($bonus_pct, 1, ',', ''), '0'), ',')
                    );
                    $cart->add_fee($label, -$bonus_amount, true);
                }
            }
        }

        // Returning-customer bonus: stacks on top of tier + sample bonus.
        // Looks up the customer's prior completed-order count via WC. For
        // logged-in users we use their user id; for guest checkout we use
        // billing_email if the user has typed it in already.
        $rb = isset($rules['returning_bonus']) && is_array($rules['returning_bonus'])
            ? $rules['returning_bonus']
            : self::$default_rules['returning_bonus'];

        if (!empty($rb['enabled'])) {
            $min_orders = max(1, intval($rb['min_orders'] ?? 1));
            $rb_pct = floatval($rb['pct'] ?? 0);
            if ($rb_pct > 0 && self::customer_has_min_prior_orders($min_orders)) {
                $rb_amount = $eligible_subtotal * ($rb_pct / 100);
                if ($rb_amount > 0) {
                    $label = sprintf(
                        __('Trouwe-klant-korting %s%%', 'oz-variations-bcw'),
                        rtrim(rtrim(number_format($rb_pct, 1, ',', ''), '0'), ',')
                    );
                    $cart->add_fee($label, -$rb_amount, true);
                }
            }
        }
    }

    /**
     * Returns true when the customer has at least $min_orders completed prior
     * orders. Uses user_id when logged in, falls back to billing_email at
     * checkout time for guest customers.
     *
     * Lookup is bounded ('paginate'=>false, 'limit'=>$min_orders+1) so we
     * don't load the full order history just to count past 1-2 orders.
     */
    private static function customer_has_min_prior_orders($min_orders) {
        if (!function_exists('wc_get_orders')) {
            return false;
        }
        $args = [
            'status' => ['completed', 'processing'],
            'limit'  => $min_orders + 1, // bounded — we only need the count up to the threshold
            'return' => 'ids',
        ];

        $user_id = get_current_user_id();
        if ($user_id > 0) {
            $args['customer_id'] = $user_id;
        } else {
            // Guest checkout: read billing_email from posted checkout fields.
            // Cart calc fires on /winkelwagen/ too where there's no email yet,
            // so this branch silently returns false until the user enters one.
            $email = '';
            if (isset($_POST['billing_email'])) {
                $email = sanitize_email(wp_unslash($_POST['billing_email']));
            } elseif (function_exists('WC') && WC()->customer) {
                $email = (string) WC()->customer->get_billing_email();
            }
            if (!is_email($email)) {
                return false;
            }
            $args['billing_email'] = $email;
        }

        $orders = wc_get_orders($args);
        return is_array($orders) && count($orders) >= $min_orders;
    }

    /**
     * Returns true when the current visitor submitted a kleurstalen form
     * within $window_days. Two signals:
     *   - cookie  oz_kleurstalen_redeemed_at  (set by oz-forms.js on success)
     *   - user_meta oz_kleurstalen_redeemed_at  (set server-side for logged-in users)
     *
     * Logged-in user_meta wins if both exist (more durable across devices).
     */
    private static function has_recent_kleurstalen_redemption($window_days) {
        $window_seconds = intval($window_days) * DAY_IN_SECONDS;
        if ($window_seconds <= 0) {
            return false;
        }
        $now = time();
        $cutoff = $now - $window_seconds;

        $ts = 0;

        if (is_user_logged_in()) {
            $meta = get_user_meta(get_current_user_id(), self::KLEURSTALEN_USER_META, true);
            if ($meta) {
                $ts = max($ts, intval($meta));
            }
        }

        if (isset($_COOKIE[self::KLEURSTALEN_COOKIE])) {
            $cookie_ts = intval($_COOKIE[self::KLEURSTALEN_COOKIE]);
            if ($cookie_ts > 0) {
                $ts = max($ts, $cookie_ts);
            }
        }

        return $ts > $cutoff && $ts <= $now;
    }

    /**
     * Hooked to oz_forms_submission_stored. When a kleurstalen-* form
     * submission is stored AND the user is logged in, write the timestamp
     * to user_meta so the bonus survives cookie loss / cross-device.
     *
     * @param string $form_id     Form schema id (e.g. 'kleurstalen-allinone')
     * @param int    $post_id     oz_submission post id
     * @param array  $data        Validated/sanitized fields
     * @param array  $attachments Uploaded file paths
     */
    public static function capture_kleurstalen_redemption($form_id, $post_id, $data, $attachments) {
        if (strpos((string) $form_id, 'kleurstalen-') !== 0) {
            return;
        }
        if (!is_user_logged_in()) {
            return;
        }
        update_user_meta(get_current_user_id(), self::KLEURSTALEN_USER_META, time());
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

                <h2><?php esc_html_e('Kleurstalen-bonus', 'oz-variations-bcw'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Extra korting voor klanten die een kleurstalen-formulier hebben ingestuurd. Past automatisch toe wanneer ze binnen het tijdvenster terugkomen om te bestellen. Stapelt op de Staffelkorting (cumulatief).', 'oz-variations-bcw'); ?>
                </p>
                <?php
                $bonus = isset($rules['sample_bonus']) && is_array($rules['sample_bonus'])
                    ? $rules['sample_bonus']
                    : ['enabled' => false, 'pct' => 5, 'window_days' => 30];
                ?>
                <table class="form-table" style="max-width:600px;">
                    <tr>
                        <th><label for="oz_sk_bonus_enabled"><?php esc_html_e('Actief', 'oz-variations-bcw'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="oz_sk_bonus_enabled" name="sample_bonus[enabled]" value="1" <?php checked(!empty($bonus['enabled'])); ?>>
                                <?php esc_html_e('Kleurstalen-bonus toepassen', 'oz-variations-bcw'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="oz_sk_bonus_pct"><?php esc_html_e('Bonus (%)', 'oz-variations-bcw'); ?></label></th>
                        <td>
                            <input type="number" id="oz_sk_bonus_pct" name="sample_bonus[pct]" value="<?php echo esc_attr($bonus['pct'] ?? 5); ?>" step="0.1" min="0" max="50" style="width:120px;">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="oz_sk_bonus_window"><?php esc_html_e('Tijdvenster (dagen)', 'oz-variations-bcw'); ?></label></th>
                        <td>
                            <input type="number" id="oz_sk_bonus_window" name="sample_bonus[window_days]" value="<?php echo esc_attr($bonus['window_days'] ?? 30); ?>" step="1" min="1" max="365" style="width:120px;">
                            <p class="description"><?php esc_html_e('Dagen na kleurstalen-aanvraag waarbinnen de bonus geldt.', 'oz-variations-bcw'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Trouwe-klant-korting', 'oz-variations-bcw'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Extra korting voor terugkerende klanten. Past automatisch toe als de klant al een vorige bestelling heeft afgerond. Stapelt op de Staffelkorting + Kleurstalen-bonus.', 'oz-variations-bcw'); ?>
                </p>
                <?php
                $rb = isset($rules['returning_bonus']) && is_array($rules['returning_bonus'])
                    ? $rules['returning_bonus']
                    : ['enabled' => false, 'pct' => 5, 'min_orders' => 1];
                ?>
                <table class="form-table" style="max-width:600px;">
                    <tr>
                        <th><label for="oz_sk_rb_enabled"><?php esc_html_e('Actief', 'oz-variations-bcw'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="oz_sk_rb_enabled" name="returning_bonus[enabled]" value="1" <?php checked(!empty($rb['enabled'])); ?>>
                                <?php esc_html_e('Trouwe-klant-korting toepassen', 'oz-variations-bcw'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="oz_sk_rb_pct"><?php esc_html_e('Korting (%)', 'oz-variations-bcw'); ?></label></th>
                        <td>
                            <input type="number" id="oz_sk_rb_pct" name="returning_bonus[pct]" value="<?php echo esc_attr($rb['pct'] ?? 5); ?>" step="0.1" min="0" max="50" style="width:120px;">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="oz_sk_rb_min"><?php esc_html_e('Vanaf welke order?', 'oz-variations-bcw'); ?></label></th>
                        <td>
                            <input type="number" id="oz_sk_rb_min" name="returning_bonus[min_orders]" value="<?php echo esc_attr($rb['min_orders'] ?? 1); ?>" step="1" min="1" max="10" style="width:120px;">
                            <p class="description"><?php esc_html_e('Aantal eerder afgeronde bestellingen dat nodig is. 1 = vanaf de tweede bestelling, 2 = vanaf de derde, etc.', 'oz-variations-bcw'); ?></p>
                        </td>
                    </tr>
                </table>

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

        // Sample-redeemed bonus persistence. Bounded ranges so a typo in the
        // admin UI can't accidentally give 999% off or a 9999-day window.
        $raw_bonus = isset($_POST['sample_bonus']) && is_array($_POST['sample_bonus']) ? $_POST['sample_bonus'] : [];
        $bonus = [
            'enabled'     => !empty($raw_bonus['enabled']),
            'pct'         => isset($raw_bonus['pct']) ? max(0.0, min(50.0, floatval($raw_bonus['pct']))) : 5.0,
            'window_days' => isset($raw_bonus['window_days']) ? max(1, min(365, intval($raw_bonus['window_days']))) : 30,
        ];

        // Returning-customer bonus. Same bounding pattern.
        $raw_rb = isset($_POST['returning_bonus']) && is_array($_POST['returning_bonus']) ? $_POST['returning_bonus'] : [];
        $returning_bonus = [
            'enabled'    => !empty($raw_rb['enabled']),
            'pct'        => isset($raw_rb['pct']) ? max(0.0, min(50.0, floatval($raw_rb['pct']))) : 5.0,
            'min_orders' => isset($raw_rb['min_orders']) ? max(1, min(10, intval($raw_rb['min_orders']))) : 1,
        ];

        update_option(self::OPTION_KEY, [
            'enabled'         => $enabled,
            'tiers'           => $tiers,
            'sample_bonus'    => $bonus,
            'returning_bonus' => $returning_bonus,
        ]);

        wp_safe_redirect(admin_url('admin.php?page=oz-bcw-staffelkorting&saved=1'));
        exit;
    }
}
