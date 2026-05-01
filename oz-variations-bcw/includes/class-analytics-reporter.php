<?php
/**
 * Analytics Reporter — Query and aggregation layer
 *
 * Two data sources:
 * 1. oz_analytics_events table — beacon events from product pages and cart drawer
 * 2. WooCommerce order tables — order revenue, product sales, before/after comparison
 *    Supports both HPOS (wc_orders) and legacy (posts + postmeta) storage.
 *
 * Returns plain associative arrays. No HTML, no formatting, no HTTP.
 * The Dashboard class consumes these arrays for rendering.
 *
 * @package OZ_Variations_BCW
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_Analytics_Reporter {

    /**
     * Launch date of the product page overhaul + cart drawer.
     * Used for before/after comparison to prove ROI.
     * First git push: 2026-03-06
     */
    const LAUNCH_DATE = '2026-03-06';

    /**
     * Current active range — set by dashboard before calling methods.
     * Used by until_date() to know if we need a ceiling (e.g. "yesterday").
     */
    private static $active_range = 7;

    /**
     * Product IDs considered "upsell/accessory" products.
     * These are tools, rollers, verfbakken — items cross-sold via our cart drawer.
     * Used to calculate upsell attach rate.
     */
    private static $upsell_product_ids = [
        11177, 25550,               // Gereedschapsets (K&K, Lavasteen)
        11025,                       // Spaan
        11175, 17360, 17361, 19705, // PU Roller (10cm, 18cm, 25cm, 50cm)
        11018,                       // Kwast
        11020,                       // Garde
        11164, 28234, 28235,        // Verfbak (10cm, 18cm, 32cm)
        11022,                       // Tape
        11015,                       // Vachtroller
        11017,                       // Schuurpapier
        11023, 11016,               // Other tools
        10998,                       // RAL Kleurenwaaier
        22436,                       // Stuco Paste (cart drawer upsell with primer options)
        22997, 22996, 22994,        // Betonlook/stuco accessories
    ];

    /** Source name aliases — normalize short forms to canonical names */
    private static $source_aliases = [
        'ig' => 'instagram', 'fb' => 'facebook', 'yt' => 'youtube', 'x' => 'twitter',
    ];

    /**
     * Normalize a raw source + medium pair into canonical form.
     * Single source of truth — used by both traffic_sources() and landings_by_source().
     *
     * @param string $raw_source  Raw traffic source
     * @param string $raw_medium  Raw traffic medium
     * @return array  ['source' => string, 'medium' => string]
     */
    private static function normalize_source($raw_source, $raw_medium) {
        $source = strtolower($raw_source ?: 'unknown');

        // Apply aliases (ig → instagram, fb → facebook, etc.)
        if (isset(self::$source_aliases[$source])) {
            $source = self::$source_aliases[$source];
        }

        // Meta ads: source=facebook but medium mentions instagram → source=instagram
        if ($source === 'facebook' && stripos($raw_medium, 'instagram') !== false) {
            $source = 'instagram';
        }

        return ['source' => $source, 'medium' => self::classify_medium($raw_medium)];
    }

    /**
     * Classify a raw medium string into a clean channel category.
     *
     * @param string $raw  Raw medium value from UTM or referrer detection
     * @return string  Normalized channel name
     */
    private static function classify_medium($raw) {
        $m = strtolower($raw ?: '');
        if ($m === 'organic') return 'organic';
        if ($m === 'none' || $m === 'direct' || $m === '') return 'none';
        if (strpos($m, 'cpc') !== false || strpos($m, 'paid') !== false || strpos($m, 'ppc') !== false) return 'cpc';
        if (strpos($m, 'stories') !== false || strpos($m, 'feed') !== false || strpos($m, 'reel') !== false) return 'cpc';
        if ($m === 'social' || $m === 'referral') return $m;
        if (strpos($m, 'email') !== false || strpos($m, 'newsletter') !== false) return 'email';
        if ($m === 'affiliate') return 'affiliate';
        if ($m === 'display' || strpos($m, 'banner') !== false) return 'display';
        if ($m === 'video' || strpos($m, 'youtube') !== false) return 'video';
        return $m;
    }

    /**
     * Detect whether HPOS (custom order tables) is active.
     * If the wc_orders table has rows, use HPOS. Otherwise use legacy posts.
     *
     * @return bool  True if HPOS is active and has data
     */
    private static function is_hpos_active() {
        // Cache the result for the request
        static $result = null;
        if ($result !== null) return $result;

        // Check if WC OrderUtil says HPOS is enabled
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            $result = true;
            return true;
        }

        $result = false;
        return false;
    }


    /* ══════════════════════════════════════════════════════════
     * SECTION 1: WooCommerce Order Data
     * Supports both HPOS (wc_orders) and legacy (posts + postmeta)
     * ══════════════════════════════════════════════════════════ */

    /**
     * Order summary: revenue, order count, AOV for a given period.
     *
     * @param int $days  Number of days to look back
     * @return array  ['revenue' => float, 'orders' => int, 'aov' => float]
     */
    public static function order_summary($days) {
        $since = self::since_date($days);
        return self::period_stats($since, self::until_date($days));
    }

    /**
     * Before/after comparison: compares same-length periods around the launch date.
     * "After" = launch date to now. "Before" = same number of days before launch.
     *
     * @return array  ['before' => [...], 'after' => [...], 'days' => int, 'launch_date' => string]
     */
    public static function order_comparison() {
        // Calculate period lengths
        $launch    = self::LAUNCH_DATE;
        $now       = current_time('Y-m-d');
        $after_days = max(1, floor((strtotime($now) - strtotime($launch)) / DAY_IN_SECONDS));
        $before_start = date('Y-m-d', strtotime($launch) - ($after_days * DAY_IN_SECONDS));

        // Query both periods
        $before = self::period_stats($before_start, $launch);
        $after  = self::period_stats($launch, $now);

        // Items per order
        $before['items_per_order'] = self::avg_items_per_order_period($before_start, $launch);
        $after['items_per_order']  = self::avg_items_per_order_period($launch, $now);

        // Upsell attach rate
        $before['upsell_rate'] = self::upsell_attach_rate_period($before_start, $launch);
        $after['upsell_rate']  = self::upsell_attach_rate_period($launch, $now);

        // Normalize to per-day for fair comparison
        $before['revenue_day'] = $after_days > 0 ? round($before['revenue'] / $after_days, 2) : 0;
        $after['revenue_day']  = $after_days > 0 ? round($after['revenue'] / $after_days, 2) : 0;
        $before['orders_day']  = $after_days > 0 ? round($before['orders'] / $after_days, 1) : 0;
        $after['orders_day']   = $after_days > 0 ? round($after['orders'] / $after_days, 1) : 0;

        return [
            'before'      => $before,
            'after'       => $after,
            'days'        => intval($after_days),
            'launch_date' => $launch,
        ];
    }

    /**
     * Revenue + order count for a specific date range.
     * Auto-detects HPOS vs legacy storage.
     *
     * @param string $from  Start date (Y-m-d or Y-m-d H:i:s)
     * @param string $to    End date (Y-m-d or Y-m-d H:i:s)
     * @return array  ['revenue' => float, 'orders' => int, 'aov' => float]
     */
    private static function period_stats($from, $to) {
        global $wpdb;

        if (self::is_hpos_active()) {
            // HPOS: query wc_orders + operational_data for shipping amounts
            // Subtract shipping to match WC Analytics "Totale verkoop"
            // Convert WP-timezone dates to GMT for HPOS (stores date_created_gmt only)
            $table = $wpdb->prefix . 'wc_orders';
            $op_table = $wpdb->prefix . 'wc_order_operational_data';
            $from_gmt = get_gmt_from_date($from);
            $to_gmt   = get_gmt_from_date($to);
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(*) AS orders,
                    COALESCE(SUM(o.total_amount - COALESCE(od.shipping_total_amount, 0) - COALESCE(od.shipping_tax_amount, 0)), 0) AS revenue
                 FROM {$table} o
                 LEFT JOIN {$op_table} od ON od.order_id = o.id
                 WHERE o.status IN ('wc-completed', 'wc-processing')
                 AND o.date_created_gmt >= %s AND o.date_created_gmt < %s",
                $from_gmt, $to_gmt
            ), ARRAY_A);
        } else {
            // Legacy: query posts + postmeta
            // Subtract shipping + shipping tax to match WC Analytics "Totale verkoop"
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(*) AS orders,
                    COALESCE(SUM(
                        pm.meta_value
                        - COALESCE(pm_ship.meta_value, 0)
                        - COALESCE(pm_stax.meta_value, 0)
                    ), 0) AS revenue
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
                 LEFT JOIN {$wpdb->postmeta} pm_ship ON p.ID = pm_ship.post_id AND pm_ship.meta_key = '_order_shipping'
                 LEFT JOIN {$wpdb->postmeta} pm_stax ON p.ID = pm_stax.post_id AND pm_stax.meta_key = '_order_shipping_tax'
                 WHERE p.post_type = 'shop_order'
                 AND p.post_status IN ('wc-completed', 'wc-processing')
                 AND p.post_date >= %s AND p.post_date < %s",
                $from, $to
            ), ARRAY_A);
        }

        $orders  = intval($row['orders']);
        $revenue = floatval($row['revenue']);

        return [
            'revenue' => $revenue,
            'orders'  => $orders,
            'aov'     => $orders > 0 ? round($revenue / $orders, 2) : 0,
        ];
    }

    /**
     * Top products by revenue for the given period.
     *
     * @param int $days
     * @param int $limit
     * @return array  [['product_id' => int, 'name' => string, 'revenue' => float, 'qty' => int], ...]
     */
    public static function top_products($days, $limit = 10) {
        global $wpdb;
        $items_table = $wpdb->prefix . 'woocommerce_order_items';
        $meta_table  = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $since = self::since_date($days);
        $until = self::until_date($days);

        // Order items table is the same for HPOS and legacy.
        // We just need to join with the correct orders source for date filtering.
        if (self::is_hpos_active()) {
            // HPOS stores date_created_gmt — convert WP-timezone dates to GMT
            $since = get_gmt_from_date($since);
            $until = get_gmt_from_date($until);
            $orders_join = "JOIN {$wpdb->prefix}wc_orders o ON oi.order_id = o.id
                            AND o.status IN ('wc-completed', 'wc-processing')
                            AND o.date_created_gmt >= %s AND o.date_created_gmt < %s";
        } else {
            $orders_join = "JOIN {$wpdb->posts} o ON oi.order_id = o.ID
                            AND o.post_type = 'shop_order'
                            AND o.post_status IN ('wc-completed', 'wc-processing')
                            AND o.post_date >= %s AND o.post_date < %s";
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                oim_pid.meta_value AS product_id,
                SUM(oim_total.meta_value + oim_tax.meta_value) AS revenue,
                SUM(oim_qty.meta_value) AS qty
             FROM {$items_table} oi
             JOIN {$meta_table} oim_pid ON oi.order_item_id = oim_pid.order_item_id AND oim_pid.meta_key = '_product_id'
             JOIN {$meta_table} oim_total ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
             JOIN {$meta_table} oim_tax ON oi.order_item_id = oim_tax.order_item_id AND oim_tax.meta_key = '_line_tax'
             JOIN {$meta_table} oim_qty ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
             {$orders_join}
             WHERE oi.order_item_type = 'line_item'
             GROUP BY product_id
             ORDER BY revenue DESC
             LIMIT %d",
            $since,
            $until,
            $limit
        ), ARRAY_A);

        // Resolve product names
        foreach ($results as &$row) {
            $product = wc_get_product(intval($row['product_id']));
            $row['name']    = $product ? $product->get_name() : '(verwijderd #' . $row['product_id'] . ')';
            $row['revenue'] = floatval($row['revenue']);
            $row['qty']     = intval($row['qty']);
        }

        return $results ?: [];
    }

    /**
     * Revenue grouped by product line.
     * Maps products to lines via their WC category assignments.
     *
     * @param int $days
     * @return array  [['line' => string, 'revenue' => float], ...]
     */
    public static function sales_by_line($days) {
        global $wpdb;
        $items_table = $wpdb->prefix . 'woocommerce_order_items';
        $meta_table  = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $since = self::since_date($days);
        $until = self::until_date($days);

        if (self::is_hpos_active()) {
            $since = get_gmt_from_date($since);
            $until = get_gmt_from_date($until);
            $orders_join = "JOIN {$wpdb->prefix}wc_orders o ON oi.order_id = o.id
                            AND o.status IN ('wc-completed', 'wc-processing')
                            AND o.date_created_gmt >= %s AND o.date_created_gmt < %s";
        } else {
            $orders_join = "JOIN {$wpdb->posts} o ON oi.order_id = o.ID
                            AND o.post_type = 'shop_order'
                            AND o.post_status IN ('wc-completed', 'wc-processing')
                            AND o.post_date >= %s AND o.post_date < %s";
        }

        // Get all order items with product IDs and revenue
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT
                oim_pid.meta_value AS product_id,
                SUM(oim_total.meta_value + oim_tax.meta_value) AS revenue
             FROM {$items_table} oi
             JOIN {$meta_table} oim_pid ON oi.order_item_id = oim_pid.order_item_id AND oim_pid.meta_key = '_product_id'
             JOIN {$meta_table} oim_total ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
             JOIN {$meta_table} oim_tax ON oi.order_item_id = oim_tax.order_item_id AND oim_tax.meta_key = '_line_tax'
             {$orders_join}
             WHERE oi.order_item_type = 'line_item'
             GROUP BY product_id",
            $since,
            $until
        ), ARRAY_A);

        // Map each product to its line using OZ_Product_Line_Config
        $line_totals = [];
        $can_detect = class_exists('OZ_Product_Line_Config');

        foreach ($items as $item) {
            $pid = intval($item['product_id']);
            $revenue = floatval($item['revenue']);

            $line = 'Overig';
            if ($can_detect) {
                $product = wc_get_product($pid);
                if ($product) {
                    $detected = OZ_Product_Line_Config::detect($product);
                    if ($detected) {
                        $line = ucfirst(str_replace('-', ' ', $detected));
                    }
                }
            }

            if (!isset($line_totals[$line])) {
                $line_totals[$line] = 0;
            }
            $line_totals[$line] += $revenue;
        }

        // Sort by revenue descending
        arsort($line_totals);

        $result = [];
        foreach ($line_totals as $line => $revenue) {
            $result[] = ['line' => $line, 'revenue' => round($revenue, 2)];
        }

        return $result;
    }

    /**
     * Average items per order for a date range.
     *
     * @param string $from  Start date
     * @param string $to    End date
     * @return float
     */
    private static function avg_items_per_order_period($from, $to) {
        global $wpdb;
        $items_table = $wpdb->prefix . 'woocommerce_order_items';

        if (self::is_hpos_active()) {
            $orders_table = $wpdb->prefix . 'wc_orders';
            $from_gmt = get_gmt_from_date($from);
            $to_gmt   = get_gmt_from_date($to);
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(item_count) FROM (
                    SELECT o.id, COUNT(oi.order_item_id) AS item_count
                    FROM {$orders_table} o
                    JOIN {$items_table} oi ON oi.order_id = o.id AND oi.order_item_type = 'line_item'
                    WHERE o.status IN ('wc-completed', 'wc-processing')
                    AND o.date_created_gmt >= %s AND o.date_created_gmt < %s
                    GROUP BY o.id
                ) sub",
                $from_gmt, $to_gmt
            ));
        } else {
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(item_count) FROM (
                    SELECT p.ID, COUNT(oi.order_item_id) AS item_count
                    FROM {$wpdb->posts} p
                    JOIN {$items_table} oi ON oi.order_id = p.ID AND oi.order_item_type = 'line_item'
                    WHERE p.post_type = 'shop_order'
                    AND p.post_status IN ('wc-completed', 'wc-processing')
                    AND p.post_date >= %s AND p.post_date < %s
                    GROUP BY p.ID
                ) sub",
                $from, $to
            ));
        }

        return round(floatval($result), 1);
    }

    /**
     * Upsell attach rate for a date range.
     * = % of orders that contain at least one upsell/accessory product.
     *
     * @param string $from  Start date
     * @param string $to    End date
     * @return float  Percentage (0-100)
     */
    private static function upsell_attach_rate_period($from, $to) {
        global $wpdb;
        $items_table = $wpdb->prefix . 'woocommerce_order_items';
        $meta_table  = $wpdb->prefix . 'woocommerce_order_itemmeta';

        // Build IN clause for upsell product IDs
        $ids_placeholder = implode(',', array_map('intval', self::$upsell_product_ids));

        if (self::is_hpos_active()) {
            $orders_table = $wpdb->prefix . 'wc_orders';
            $from_gmt = get_gmt_from_date($from);
            $to_gmt   = get_gmt_from_date($to);

            $total_orders = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$orders_table}
                 WHERE status IN ('wc-completed', 'wc-processing')
                 AND date_created_gmt >= %s AND date_created_gmt < %s",
                $from_gmt, $to_gmt
            ));

            if (intval($total_orders) === 0) return 0;

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $upsell_orders = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT o.id)
                 FROM {$orders_table} o
                 JOIN {$items_table} oi ON oi.order_id = o.id AND oi.order_item_type = 'line_item'
                 JOIN {$meta_table} oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'
                 WHERE o.status IN ('wc-completed', 'wc-processing')
                 AND o.date_created_gmt >= %s AND o.date_created_gmt < %s
                 AND oim.meta_value IN ({$ids_placeholder})",
                $from_gmt, $to_gmt
            ));
        } else {
            $total_orders = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = 'shop_order'
                 AND post_status IN ('wc-completed', 'wc-processing')
                 AND post_date >= %s AND post_date < %s",
                $from, $to
            ));

            if (intval($total_orders) === 0) return 0;

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $upsell_orders = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                 FROM {$wpdb->posts} p
                 JOIN {$items_table} oi ON oi.order_id = p.ID AND oi.order_item_type = 'line_item'
                 JOIN {$meta_table} oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'
                 WHERE p.post_type = 'shop_order'
                 AND p.post_status IN ('wc-completed', 'wc-processing')
                 AND p.post_date >= %s AND p.post_date < %s
                 AND oim.meta_value IN ({$ids_placeholder})",
                $from, $to
            ));
        }

        return round((intval($upsell_orders) / intval($total_orders)) * 100, 1);
    }

    /**
     * Average items per order for the last $days.
     *
     * @param int $days
     * @return float
     */
    public static function avg_items_per_order($days) {
        $since = self::since_date($days);
        $until = self::until_date($days);
        return self::avg_items_per_order_period($since, $until);
    }


    /**
     * Top tools sold via our product page tools section (Kant & Klaar / Zelf Samenstellen).
     * Only counts order items that have _oz_tool_size or _oz_tool_price meta,
     * which means they were added through our tools UI — not bought loose or via cart upsells.
     *
     * @param int $days   Date range
     * @param int $limit  Max rows
     * @return array  [['value' => string, 'count' => int], ...]
     */
    public static function top_tool_sales($days, $limit = 10) {
        global $wpdb;
        $items_table = $wpdb->prefix . 'woocommerce_order_items';
        $meta_table  = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $since = self::since_date($days);
        $until = self::until_date($days);

        if (self::is_hpos_active()) {
            $since = get_gmt_from_date($since);
            $until = get_gmt_from_date($until);
            $orders_join = "JOIN {$wpdb->prefix}wc_orders o ON oi.order_id = o.id
                            AND o.status IN ('wc-completed', 'wc-processing')
                            AND o.date_created_gmt >= %s AND o.date_created_gmt < %s";
        } else {
            $orders_join = "JOIN {$wpdb->posts} o ON oi.order_id = o.ID
                            AND o.post_type = 'shop_order'
                            AND o.post_status IN ('wc-completed', 'wc-processing')
                            AND o.post_date >= %s AND o.post_date < %s";
        }

        // Match tools added via our product page:
        // 1. Items with _oz_tool_size or _oz_tool_price meta (individual tools with size selection)
        // 2. Gereedschapset products (11177 K&K, 25550 Lavasteen) — added with empty cart_data
        // Uses EXISTS subquery instead of LEFT JOIN to avoid double-counting
        // when an item has both _oz_tool_size AND _oz_tool_price meta rows
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                oim_pid.meta_value AS product_id,
                SUM(oim_qty.meta_value) AS qty
             FROM {$items_table} oi
             JOIN {$meta_table} oim_pid ON oi.order_item_id = oim_pid.order_item_id AND oim_pid.meta_key = '_product_id'
             JOIN {$meta_table} oim_qty ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
             {$orders_join}
             WHERE oi.order_item_type = 'line_item'
             AND (
                 EXISTS (
                     SELECT 1 FROM {$meta_table} oim_tool
                     WHERE oim_tool.order_item_id = oi.order_item_id
                     AND oim_tool.meta_key IN ('_oz_tool_size', '_oz_tool_price')
                 )
                 OR oim_pid.meta_value IN (11177, 25550)
             )
             GROUP BY product_id
             ORDER BY qty DESC
             LIMIT %d",
            $since,
            $until,
            $limit
        ), ARRAY_A);

        // Resolve product names, format for render_top_list()
        $output = [];
        foreach ($results as $row) {
            $product = wc_get_product(intval($row['product_id']));
            $output[] = [
                'value' => $product ? $product->get_name() : '#' . $row['product_id'],
                'count' => intval($row['qty']),
            ];
        }

        return $output;
    }

    /* ══════════════════════════════════════════════════════════
     * SECTION 2: Beacon Event Data (oz_analytics_events table)
     * ══════════════════════════════════════════════════════════ */

    /**
     * High-level summary for the behavior analytics cards.
     *
     * @param int $days  Number of days to look back
     * @return array  ['total' => int, 'sessions' => int, 'add_to_carts' => int, 'checkouts' => int]
     */
    public static function summary($days) {
        global $wpdb;
        $table = OZ_Analytics_Store::table_name();
        $since = self::since_date($days);
        $until = self::until_date($days);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS total, COUNT(DISTINCT session_id) AS sessions
             FROM {$table} WHERE created_at >= %s AND created_at < %s",
            $since, $until
        ), ARRAY_A);

        $atc = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE event_name = 'oz_add_to_cart' AND created_at >= %s AND created_at < %s",
            $since, $until
        ));

        $checkout = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE event_name = 'oz_cart_checkout_clicked' AND created_at >= %s AND created_at < %s",
            $since, $until
        ));

        return [
            'total'        => intval($row['total']),
            'sessions'     => intval($row['sessions']),
            'add_to_carts' => intval($atc),
            'checkouts'    => intval($checkout),
        ];
    }

    /**
     * Event counts grouped by event_name, filtered by source.
     */
    public static function by_source($source, $days) {
        global $wpdb;
        $table = OZ_Analytics_Store::table_name();
        $since = self::since_date($days);
        $until = self::until_date($days);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT event_name, COUNT(*) AS count
             FROM {$table}
             WHERE source = %s AND created_at >= %s AND created_at < %s
             GROUP BY event_name
             ORDER BY count DESC",
            $source,
            $since,
            $until
        ), ARRAY_A);
    }

    /**
     * Top values for a specific JSON key within an event's event_data.
     */
    public static function top_values($event_name, $json_key, $days, $limit = 10) {
        global $wpdb;
        $table = OZ_Analytics_Store::table_name();
        $since = self::since_date($days);
        $until = self::until_date($days);

        // MariaDB has bugs with JSON_EXTRACT + GROUP BY (silently drops rows).
        // Fetch raw event_data and aggregate in PHP instead.
        // $event_name can be a string or array of event names to combine.
        if (is_array($event_name)) {
            $placeholders = implode(',', array_fill(0, count($event_name), '%s'));
            $params = array_merge($event_name, [$since, $until]);
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT event_data
                 FROM {$table}
                 WHERE event_name IN ({$placeholders}) AND created_at >= %s AND created_at < %s",
                ...$params
            ), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT event_data
                 FROM {$table}
                 WHERE event_name = %s AND created_at >= %s AND created_at < %s",
                $event_name,
                $since,
                $until
            ), ARRAY_A);
        }

        // Aggregate values in PHP
        $counts = [];
        foreach ($rows as $row) {
            $data = json_decode($row['event_data'], true);
            if (!$data || !isset($data[$json_key])) continue;
            $val = $data[$json_key];
            if ($val === null || $val === '') continue;
            $val = (string) $val;
            if (!isset($counts[$val])) {
                $counts[$val] = 0;
            }
            $counts[$val]++;
        }

        // Sort by count descending, format as expected output
        arsort($counts);
        $results = [];
        foreach (array_slice($counts, 0, $limit, true) as $value => $count) {
            $results[] = ['value' => $value, 'count' => $count];
        }
        return $results;
    }

    /**
     * Conversion funnel: color_selected → add_to_cart → checkout_clicked.
     */
    public static function funnel($days) {
        global $wpdb;
        $table = OZ_Analytics_Store::table_name();
        $since = self::since_date($days);
        $until = self::until_date($days);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(CASE WHEN event_name = 'oz_color_selected' THEN 1 ELSE 0 END) AS color_selected,
                SUM(CASE WHEN event_name = 'oz_add_to_cart' THEN 1 ELSE 0 END) AS add_to_cart,
                SUM(CASE WHEN event_name = 'oz_cart_checkout_clicked' THEN 1 ELSE 0 END) AS checkout
             FROM {$table}
             WHERE created_at >= %s AND created_at < %s",
            $since, $until
        ), ARRAY_A);

        return [
            'color_selected' => intval($row['color_selected']),
            'add_to_cart'    => intval($row['add_to_cart']),
            'checkout'       => intval($row['checkout']),
        ];
    }

    /**
     * A/B test results for the Gereedschap section visibility test.
     *
     * Cookie `oz_ab_tools` (A or B) is set client-side by an inline script
     * in the theme. Every analytics event written from a PDP or session-start
     * carries `oz_ab_tools_variant` in its event_data JSON.
     *
     * Returns a per-variant breakdown of sessions, color picks, ATC, and
     * checkout clicks so we can see whether hiding the tools section
     * (variant B) helps or hurts conversion.
     *
     * Variants without recorded data simply show 0s — that's normal for
     * the early days of the test before traffic accumulates.
     */
    public static function ab_test_tools_results($days) {
        global $wpdb;
        $table = OZ_Analytics_Store::table_name();
        $since = self::since_date($days);
        $until = self::until_date($days);

        // JSON_EXTRACT returns "A", "B" or "C" with quotes; JSON_UNQUOTE strips them.
        // Both functions are available on MariaDB 10.2+ which BCW runs.
        // 3-variant extension (01/05/26): C = dropdown variant.
        $variants = ['A', 'B', 'C'];
        $out = [];
        foreach ($variants as $v) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT
                    COUNT(DISTINCT CASE WHEN event_name = 'oz_session_start' THEN session_id END) AS sessions,
                    COUNT(DISTINCT CASE WHEN event_name = 'oz_color_selected' THEN session_id END) AS color_pickers,
                    COUNT(DISTINCT CASE WHEN event_name = 'oz_add_to_cart' THEN session_id END) AS atc_sessions,
                    SUM(CASE WHEN event_name = 'oz_add_to_cart' THEN 1 ELSE 0 END) AS atc_total,
                    COUNT(DISTINCT CASE WHEN event_name = 'oz_cart_checkout_clicked' THEN session_id END) AS checkout_sessions
                 FROM {$table}
                 WHERE created_at >= %s AND created_at < %s
                   AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.oz_ab_tools_variant')) = %s",
                $since, $until, $v
            ), ARRAY_A);

            $sessions     = intval($row['sessions']);
            $color_pickers = intval($row['color_pickers']);
            $atc_sessions = intval($row['atc_sessions']);
            $atc_total    = intval($row['atc_total']);
            $checkout     = intval($row['checkout_sessions']);

            $out[$v] = [
                'variant'         => $v,
                'sessions'        => $sessions,
                'color_pickers'   => $color_pickers,
                'atc_sessions'    => $atc_sessions,
                'atc_total'       => $atc_total,
                'checkout'        => $checkout,
                // Conversion: % of sessions that ATC'd. ATC sessions / sessions × 100.
                'conv_atc_pct'    => $sessions > 0 ? round(($atc_sessions / $sessions) * 100, 2) : 0,
                // % of sessions that picked a color (engagement).
                'engage_pct'      => $sessions > 0 ? round(($color_pickers / $sessions) * 100, 2) : 0,
                // % of sessions that clicked checkout.
                'conv_checkout_pct' => $sessions > 0 ? round(($checkout / $sessions) * 100, 2) : 0,
            ];
        }

        // Lift % per variant vs A (control) on the headline metric (ATC %).
        // Positive = that variant converts better than control.
        $base_pct = $out['A']['conv_atc_pct'];
        $lifts = ['A' => 0, 'B' => 0, 'C' => 0];
        if ($base_pct > 0) {
            $lifts['B'] = round((($out['B']['conv_atc_pct'] - $base_pct) / $base_pct) * 100, 1);
            $lifts['C'] = round((($out['C']['conv_atc_pct'] - $base_pct) / $base_pct) * 100, 1);
        }

        return [
            'variants' => $out,
            'lifts'    => $lifts, // B and C lift vs A on ATC %
            // Backward-compat: existing UI reads `lift_pct` (B vs A only).
            'lift_pct' => $lifts['B'],
            // Total sessions across all variants — sanity check the split.
            'total_sessions' => $out['A']['sessions'] + $out['B']['sessions'] + $out['C']['sessions'],
        ];
    }

    /**
     * Formula toggle stats: toggle clicks, ZM add-to-carts, and conversion rate.
     * Used by the dashboard ZM monitoring section.
     */
    public static function formula_toggle_stats($days) {
        global $wpdb;
        $table = OZ_Analytics_Store::table_name();
        $since = self::since_date($days);
        $until = self::until_date($days);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(CASE WHEN event_name = 'oz_formula_toggled' THEN 1 ELSE 0 END) AS toggle_clicks,
                SUM(CASE WHEN event_name = 'oz_add_to_cart' AND product_id = 11152 THEN 1 ELSE 0 END) AS zm_add_to_cart
             FROM {$table}
             WHERE created_at >= %s AND created_at < %s",
            $since, $until
        ), ARRAY_A);

        $toggles = intval($row['toggle_clicks']);
        $zm_atc  = intval($row['zm_add_to_cart']);

        return [
            'toggle_clicks'   => $toggles,
            'zm_add_to_cart'  => $zm_atc,
            'zm_conversion'   => $toggles > 0 ? round(($zm_atc / $toggles) * 100, 1) : 0,
        ];
    }

    /**
     * Traffic sources: where sessions originated (from oz_session_start events).
     * Groups by oz_traffic_source extracted from event_data JSON.
     *
     * @param int $days  Number of days to look back
     * @param int $limit Max rows to return
     * @return array  [['source' => string, 'medium' => string, 'count' => int], ...]
     */
    public static function traffic_sources($days, $limit = 15) {
        global $wpdb;
        $table = OZ_Analytics_Store::table_name();
        $since = self::since_date($days);
        $until = self::until_date($days);

        // MariaDB has bugs with JSON_EXTRACT + GROUP BY (silently drops rows).
        // Fetch raw event_data and aggregate in PHP instead.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT event_data
             FROM {$table}
             WHERE event_name = 'oz_session_start'
             AND created_at >= %s AND created_at < %s",
            $since,
            $until
        ), ARRAY_A);

        // Aggregate source+medium counts in PHP using shared normalization
        $counts = [];
        foreach ($rows as $row) {
            $data = json_decode($row['event_data'], true);
            if (!$data) continue;

            $normalized = self::normalize_source(
                isset($data['oz_traffic_source']) ? $data['oz_traffic_source'] : 'unknown',
                isset($data['oz_traffic_medium']) ? $data['oz_traffic_medium'] : 'unknown'
            );
            $source  = $normalized['source'];
            $channel = $normalized['medium'];

            $key = $source . '|' . $channel;
            if (!isset($counts[$key])) {
                $counts[$key] = ['source' => $source, 'medium' => $channel, 'count' => 0];
            }
            $counts[$key]['count']++;
        }

        // Sort by count descending, limit results
        usort($counts, function ($a, $b) { return $b['count'] - $a['count']; });
        return array_slice($counts, 0, $limit);
    }

    /**
     * Top landing pages from oz_session_start events.
     *
     * @param int $days
     * @param int $limit
     * @return array  [['value' => string, 'count' => int], ...]
     */
    public static function top_landing_pages($days, $limit = 10) {
        return self::top_values('oz_session_start', 'oz_landing_page', $days, $limit);
    }

    /**
     * Landing pages filtered by a specific traffic source+medium.
     * Returns individual visits with timestamps (not aggregated).
     *
     * @param string $source  Traffic source (e.g. 'google')
     * @param string $medium  Traffic medium (e.g. 'organic')
     * @param int    $days    Date range
     * @param int    $limit   Max rows
     * @return array  [['landing_page' => string, 'created_at' => string, 'campaign' => string], ...]
     */
    public static function landings_by_source($source, $medium, $days, $limit = 50) {
        global $wpdb;
        $table = OZ_Analytics_Store::table_name();
        $since = self::since_date($days);
        $until = self::until_date($days);

        // Fetch all session_start events in range, filter in PHP (MariaDB JSON bug)
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT event_data, created_at
             FROM {$table}
             WHERE event_name = 'oz_session_start'
             AND created_at >= %s AND created_at < %s
             ORDER BY created_at DESC",
            $since,
            $until
        ), ARRAY_A);

        // Same shared normalization as traffic_sources()
        $results = [];
        foreach ($rows as $row) {
            $data = json_decode($row['event_data'], true);
            if (!$data) continue;

            $normalized = self::normalize_source(
                isset($data['oz_traffic_source']) ? $data['oz_traffic_source'] : '',
                isset($data['oz_traffic_medium']) ? $data['oz_traffic_medium'] : ''
            );

            // Match against normalized source + channel
            if ($normalized['source'] !== strtolower($source)) continue;
            if ($normalized['medium'] !== strtolower($medium)) continue;

            $results[] = [
                'landing_page' => isset($data['oz_landing_page']) ? $data['oz_landing_page'] : '/',
                'created_at'   => $row['created_at'],
                'campaign'     => isset($data['oz_utm_campaign']) ? $data['oz_utm_campaign'] : '',
            ];

            if (count($results) >= $limit) break;
        }

        return $results;
    }

    /**
     * Daily event trend for the given period.
     * @todo Add a sparkline/trend chart to the Dashboard in a future iteration.
     */
    public static function daily_trend($days) {
        global $wpdb;
        $table = OZ_Analytics_Store::table_name();
        $since = self::since_date($days);
        $until = self::until_date($days);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) AS date, COUNT(*) AS count
             FROM {$table}
             WHERE created_at >= %s AND created_at < %s
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $since, $until
        ), ARRAY_A);
    }


    /**
     * Get the date of the earliest beacon event stored.
     * Used to show when tracking started on the dashboard.
     *
     * @return string|null  Date string (Y-m-d) or null if no events
     */
    public static function earliest_event_date() {
        global $wpdb;
        $table = OZ_Analytics_Store::table_name();
        $date = $wpdb->get_var("SELECT MIN(DATE(created_at)) FROM {$table}");
        return $date ?: null;
    }


    /* ══════════════════════════════════════════════════════════
     * HELPERS
     * ══════════════════════════════════════════════════════════ */

    /**
     * Helper: calculate the "since" datetime string for $days ago.
     * Uses calendar dates (midnight) not rolling hours.
     * "Vandaag" (1 day) = midnight today. "7 dagen" = midnight 7 days ago.
     * This matches WC Analytics' date behavior.
     */
    private static function since_date($days) {
        // Special case: 'yesterday' returns yesterday midnight
        if ($days === 'yesterday') {
            return date('Y-m-d 00:00:00', current_time('timestamp') - DAY_IN_SECONDS);
        }
        // Start of today in WP timezone, then subtract ($days - 1) full days
        // For "Vandaag" ($days=1): returns midnight today
        // For "7 dagen" ($days=7): returns midnight 6 days ago (= 7 calendar days incl. today)
        $days_back = max(0, absint($days) - 1);
        return date('Y-m-d 00:00:00', current_time('timestamp') - ($days_back * DAY_IN_SECONDS));
    }

    /**
     * Set the active range before calling reporter methods.
     * Call this once from the dashboard so all methods know the ceiling.
     */
    public static function set_range($range) {
        self::$active_range = $range;
    }

    /**
     * Helper: "until" date for the active range.
     * Most ranges go to "now". Yesterday ends at midnight today.
     */
    private static function until_date($days = null) {
        $r = $days !== null ? $days : self::$active_range;
        if ($r === 'yesterday') {
            return date('Y-m-d 00:00:00', current_time('timestamp'));
        }
        return current_time('Y-m-d H:i:s');
    }

    /**
     * Convert WP-timezone date pair to GMT for HPOS queries.
     * HPOS stores date_created_gmt (UTC), but since_date/until_date return WP timezone.
     * Only needed when is_hpos_active() is true.
     *
     * @param string $since  WP-timezone start date
     * @param string $until  WP-timezone end date
     * @return array  [$since_gmt, $until_gmt]
     */
    private static function to_gmt($since, $until) {
        return [get_gmt_from_date($since), get_gmt_from_date($until)];
    }
}
