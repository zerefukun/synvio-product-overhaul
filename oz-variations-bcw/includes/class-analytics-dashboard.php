<?php
/**
 * Analytics Dashboard — WP Admin page renderer
 *
 * Registers a submenu page under WooCommerce and renders the analytics UI.
 * Reads data from OZ_Analytics_Reporter (plain arrays), contains zero SQL.
 * Self-contained CSS via inline <style> block.
 *
 * @package OZ_Variations_BCW
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_Analytics_Dashboard {

    /**
     * Initialize admin menu hook.
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
    }

    /**
     * Register submenu page under WooCommerce.
     */
    public static function add_menu_page() {
        add_submenu_page(
            'woocommerce',                  // Parent slug
            'BCW Analytics',                // Page title
            'BCW Analytics',                // Menu title
            'manage_woocommerce',           // Capability
            'oz-bcw-analytics',             // Menu slug
            [__CLASS__, 'render']           // Callback
        );
    }

    /**
     * Render the full dashboard page.
     */
    public static function render() {
        // Date range from query string (default: 7 days)
        $range = isset($_GET['range']) ? absint($_GET['range']) : 7;
        if (!in_array($range, [1, 7, 30], true)) {
            $range = 7;
        }

        // Fetch all data from Reporter
        $summary  = OZ_Analytics_Reporter::summary($range);
        $product  = OZ_Analytics_Reporter::by_source('product', $range);
        $cart     = OZ_Analytics_Reporter::by_source('cart', $range);
        $funnel   = OZ_Analytics_Reporter::funnel($range);
        $colors   = OZ_Analytics_Reporter::top_values('oz_color_selected', 'oz_color', $range, 10);
        $upsells  = OZ_Analytics_Reporter::top_values('oz_cart_upsell_added', 'oz_upsell_name', $range, 10);

        // Build page URL for range links
        $base_url = admin_url('admin.php?page=oz-bcw-analytics');

        ?>
        <style>
            /* ── Dashboard layout ── */
            .oz-analytics { max-width: 1100px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .oz-analytics h1 { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }

            /* ── Range tabs ── */
            .oz-range-tabs { display: flex; gap: 4px; margin-left: auto; }
            .oz-range-tabs a {
                padding: 6px 14px; border-radius: 4px; text-decoration: none;
                font-size: 13px; font-weight: 500; color: #50575e; background: #f0f0f1;
                transition: background 0.15s;
            }
            .oz-range-tabs a:hover { background: #e0e0e1; }
            .oz-range-tabs a.active { background: #2271b1; color: #fff; }

            /* ── Summary cards ── */
            .oz-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
            .oz-card {
                background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;
                padding: 16px 20px; text-align: center;
            }
            .oz-card-value { font-size: 28px; font-weight: 700; color: #1d2327; line-height: 1.2; }
            .oz-card-label { font-size: 13px; color: #646970; margin-top: 4px; }

            /* ── Two-column grid ── */
            .oz-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
            .oz-panel {
                background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px;
            }
            .oz-panel h3 { margin: 0 0 12px; font-size: 14px; color: #1d2327; }

            /* ── Bar charts (pure CSS) ── */
            .oz-bar-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 13px; }
            .oz-bar-label { min-width: 180px; color: #50575e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .oz-bar-track { flex: 1; height: 18px; background: #f0f0f1; border-radius: 3px; overflow: hidden; }
            .oz-bar-fill { height: 100%; background: #2271b1; border-radius: 3px; min-width: 2px; transition: width 0.3s; }
            .oz-bar-count { min-width: 36px; text-align: right; font-weight: 600; color: #1d2327; font-variant-numeric: tabular-nums; }

            /* ── Funnel ── */
            .oz-funnel { margin-bottom: 24px; }
            .oz-funnel .oz-bar-fill { background: #135e96; }
            .oz-funnel .oz-bar-row:nth-child(2) .oz-bar-fill { background: #2271b1; }
            .oz-funnel .oz-bar-row:nth-child(3) .oz-bar-fill { background: #72aee6; }

            /* ── Top lists ── */
            .oz-top-item { display: flex; align-items: center; gap: 8px; padding: 5px 0; font-size: 13px; border-bottom: 1px solid #f0f0f1; }
            .oz-top-item:last-child { border-bottom: none; }
            .oz-top-rank { width: 20px; font-weight: 700; color: #2271b1; text-align: center; }
            .oz-top-name { flex: 1; color: #1d2327; }
            .oz-top-count { font-weight: 600; color: #50575e; font-variant-numeric: tabular-nums; }

            /* ── Empty state ── */
            .oz-empty { color: #646970; font-style: italic; font-size: 13px; padding: 12px 0; }

            /* ── Responsive ── */
            @media (max-width: 960px) {
                .oz-cards { grid-template-columns: repeat(2, 1fr); }
                .oz-columns { grid-template-columns: 1fr; }
            }
        </style>

        <div class="wrap oz-analytics">
            <h1>
                BCW Analytics
                <div class="oz-range-tabs">
                    <a href="<?php echo esc_url(add_query_arg('range', 1, $base_url)); ?>"
                       class="<?php echo $range === 1 ? 'active' : ''; ?>">Vandaag</a>
                    <a href="<?php echo esc_url(add_query_arg('range', 7, $base_url)); ?>"
                       class="<?php echo $range === 7 ? 'active' : ''; ?>">7 dagen</a>
                    <a href="<?php echo esc_url(add_query_arg('range', 30, $base_url)); ?>"
                       class="<?php echo $range === 30 ? 'active' : ''; ?>">30 dagen</a>
                </div>
            </h1>

            <?php
            // Summary cards
            self::render_cards($summary);
            ?>

            <!-- Event breakdown by source -->
            <div class="oz-columns">
                <div class="oz-panel">
                    <h3>Productpagina Events</h3>
                    <?php self::render_bar_chart($product); ?>
                </div>
                <div class="oz-panel">
                    <h3>Cart Drawer Events</h3>
                    <?php self::render_bar_chart($cart); ?>
                </div>
            </div>

            <!-- Conversion funnel -->
            <div class="oz-panel oz-funnel" style="margin-bottom: 24px;">
                <h3>Conversie Funnel</h3>
                <?php self::render_funnel($funnel); ?>
            </div>

            <!-- Top lists -->
            <div class="oz-columns">
                <div class="oz-panel">
                    <h3>Top Kleuren</h3>
                    <?php self::render_top_list($colors); ?>
                </div>
                <div class="oz-panel">
                    <h3>Top Upsells</h3>
                    <?php self::render_top_list($upsells); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render 4 summary cards.
     *
     * @param array $summary  From Reporter::summary()
     */
    private static function render_cards($summary) {
        $cards = [
            ['value' => number_format($summary['total']),        'label' => 'Events'],
            ['value' => number_format($summary['sessions']),     'label' => 'Sessies'],
            ['value' => number_format($summary['add_to_carts']), 'label' => 'Add to Cart'],
            ['value' => number_format($summary['checkouts']),    'label' => 'Checkout Clicks'],
        ];

        echo '<div class="oz-cards">';
        foreach ($cards as $card) {
            printf(
                '<div class="oz-card"><div class="oz-card-value">%s</div><div class="oz-card-label">%s</div></div>',
                esc_html($card['value']),
                esc_html($card['label'])
            );
        }
        echo '</div>';
    }

    /**
     * Render a horizontal bar chart from event counts.
     *
     * @param array $rows   [['event_name' => string, 'count' => int], ...]
     */
    private static function render_bar_chart($rows) {
        if (empty($rows)) {
            echo '<div class="oz-empty">Nog geen events in deze periode.</div>';
            return;
        }

        // Find max count for scaling bars
        $max = 1;
        foreach ($rows as $row) {
            if (intval($row['count']) > $max) $max = intval($row['count']);
        }

        foreach ($rows as $row) {
            $count = intval($row['count']);
            $pct   = round(($count / $max) * 100);
            printf(
                '<div class="oz-bar-row">'
                . '<span class="oz-bar-label">%s</span>'
                . '<div class="oz-bar-track"><div class="oz-bar-fill" style="width:%d%%"></div></div>'
                . '<span class="oz-bar-count">%s</span>'
                . '</div>',
                esc_html($row['event_name']),
                $pct,
                number_format($count)
            );
        }
    }

    /**
     * Render conversion funnel bars.
     *
     * @param array $funnel  From Reporter::funnel()
     */
    private static function render_funnel($funnel) {
        $steps = [
            ['label' => 'Kleur gekozen',  'count' => $funnel['color_selected']],
            ['label' => 'In winkelmand',   'count' => $funnel['add_to_cart']],
            ['label' => 'Naar afrekenen',  'count' => $funnel['checkout']],
        ];

        // Max is the first step (widest bar)
        $max = max($funnel['color_selected'], 1);

        foreach ($steps as $step) {
            $pct = round(($step['count'] / $max) * 100);
            printf(
                '<div class="oz-bar-row">'
                . '<span class="oz-bar-label">%s</span>'
                . '<div class="oz-bar-track"><div class="oz-bar-fill" style="width:%d%%"></div></div>'
                . '<span class="oz-bar-count">%s</span>'
                . '</div>',
                esc_html($step['label']),
                $pct,
                number_format($step['count'])
            );
        }
    }

    /**
     * Render a ranked top-N list.
     *
     * @param array $rows  [['value' => string, 'count' => int], ...]
     */
    private static function render_top_list($rows) {
        if (empty($rows)) {
            echo '<div class="oz-empty">Nog geen data in deze periode.</div>';
            return;
        }

        foreach ($rows as $i => $row) {
            printf(
                '<div class="oz-top-item">'
                . '<span class="oz-top-rank">%d.</span>'
                . '<span class="oz-top-name">%s</span>'
                . '<span class="oz-top-count">%s</span>'
                . '</div>',
                $i + 1,
                esc_html($row['value']),
                number_format(intval($row['count']))
            );
        }
    }
}
