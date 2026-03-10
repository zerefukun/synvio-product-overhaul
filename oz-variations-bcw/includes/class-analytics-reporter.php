<?php
/**
 * Analytics Reporter — Query and aggregation layer
 *
 * Two data sources:
 * 1. oz_analytics_events table — beacon events from product pages and cart drawer
 * 2. WooCommerce HPOS tables — order revenue, product sales, before/after comparison
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
     * WC order statuses that count as "paid" orders.
     * Processing = paid but not shipped. Completed = shipped.
     */
    const PAID_STATUSES = "('wc-completed', 'wc-processing')";

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
        22997, 22996, 22994,        // Betonlook/stuco accessories
    ];


    /* ══════════════════════════════════════════════════════════
     * SECTION 1: WooCommerce Order Data (HPOS tables)
     * ══════════════════════════════════════════════════════════ */

    /**
     * Order summary: revenue, order count, AOV for a given period.
     *
     * @param int $days  Number of days to look back
     * @return array  ['revenue' => float, 'orders' => int, 'aov' => float]
     */
    public static function order_summary($days) {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_orders';
        $since = self::since_date($days);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS orders, COALESCE(SUM(total_amount), 0) AS revenue
             FROM {$table}
             WHERE status IN ('wc-completed', 'wc-processing')
             AND date_created_gmt >= %s",
            $since
        ), ARRAY_A);

        $orders  = intval($row['orders']);
        $revenue = floatval($row['revenue']);

        return [
            'revenue' => $revenue,
            'orders'  => $orders,
            'aov'     => $orders > 0 ? round($revenue / $orders, 2) : 0,
        ];
    }

    /**
     * Before/after comparison: compares same-length periods around the launch date.
     * "After" = launch date to now. "Before" = same number of days before launch.
     *
     * @return array  ['before' => [...], 'after' => [...], 'days' => int, 'launch_date' => string]
     */
    public static function order_comparison() {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_orders';

        // Calculate period lengths
        $launch    = self::LAUNCH_DATE;
        $now       = current_time('Y-m-d');
        $after_days = max(1, (strtotime($now) - strtotime($launch)) / DAY_IN_SECONDS);
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
     *
     * @param string $from  Start date (Y-m-d)
     * @param string $to    End date (Y-m-d)
     * @return array  ['revenue' => float, 'orders' => int, 'aov' => float]
     */
    private static function period_stats($from, $to) {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_orders';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS orders, COALESCE(SUM(total_amount), 0) AS revenue
             FROM {$table}
             WHERE status IN ('wc-completed', 'wc-processing')
             AND date_created_gmt >= %s AND date_created_gmt < %s",
            $from, $to
        ), ARRAY_A);

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
        $orders_table = $wpdb->prefix . 'wc_orders';
        $items_table  = $wpdb->prefix . 'woocommerce_order_items';
        $meta_table   = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $since = self::since_date($days);

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
             JOIN {$orders_table} o ON oi.order_id = o.id
             WHERE o.status IN ('wc-completed', 'wc-processing')
             AND o.date_created_gmt >= %s
             AND oi.order_item_type = 'line_item'
             GROUP BY product_id
             ORDER BY revenue DESC
             LIMIT %d",
            $since,
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
     * @return array  [['line' => string, 'revenue' => float, 'orders' => int], ...]
     */
    public static function sales_by_line($days) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'wc_orders';
        $items_table  = $wpdb->prefix . 'woocommerce_order_items';
        $meta_table   = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $since = self::since_date($days);

        // Get all order items with product IDs and revenue
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT
                oim_pid.meta_value AS product_id,
                SUM(oim_total.meta_value + oim_tax.meta_value) AS revenue
             FROM {$items_table} oi
             JOIN {$meta_table} oim_pid ON oi.order_item_id = oim_pid.order_item_id AND oim_pid.meta_key = '_product_id'
             JOIN {$meta_table} oim_total ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
             JOIN {$meta_table} oim_tax ON oi.order_item_id = oim_tax.order_item_id AND oim_tax.meta_key = '_line_tax'
             JOIN {$orders_table} o ON oi.order_id = o.id
             WHERE o.status IN ('wc-completed', 'wc-processing')
             AND o.date_created_gmt >= %s
             AND oi.order_item_type = 'line_item'
             GROUP BY product_id",
            $since
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
     * @param string $from  Start date (Y-m-d)
     * @param string $to    End date (Y-m-d)
     * @return float
     */
    private static function avg_items_per_order_period($from, $to) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'wc_orders';
        $items_table  = $wpdb->prefix . 'woocommerce_order_items';

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(item_count) FROM (
                SELECT o.id, COUNT(oi.order_item_id) AS item_count
                FROM {$orders_table} o
                JOIN {$items_table} oi ON oi.order_id = o.id AND oi.order_item_type = 'line_item'
                WHERE o.status IN ('wc-completed', 'wc-processing')
                AND o.date_created_gmt >= %s AND o.date_created_gmt < %s
                GROUP BY o.id
            ) sub",
            $from, $to
        ));

        return round(floatval($result), 1);
    }

    /**
     * Upsell attach rate for a date range.
     * = % of orders that contain at least one upsell/accessory product.
     *
     * @param string $from  Start date (Y-m-d)
     * @param string $to    End date (Y-m-d)
     * @return float  Percentage (0-100)
     */
    private static function upsell_attach_rate_period($from, $to) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'wc_orders';
        $items_table  = $wpdb->prefix . 'woocommerce_order_items';
        $meta_table   = $wpdb->prefix . 'woocommerce_order_itemmeta';

        // Total paid orders in period
        $total_orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$orders_table}
             WHERE status IN ('wc-completed', 'wc-processing')
             AND date_created_gmt >= %s AND date_created_gmt < %s",
            $from, $to
        ));

        if (intval($total_orders) === 0) return 0;

        // Build IN clause for upsell product IDs
        $ids_placeholder = implode(',', array_map('intval', self::$upsell_product_ids));

        // Orders containing at least one upsell product
        $upsell_orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT o.id)
             FROM {$orders_table} o
             JOIN {$items_table} oi ON oi.order_id = o.id AND oi.order_item_type = 'line_item'
             JOIN {$meta_table} oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'
             WHERE o.status IN ('wc-completed', 'wc-processing')
             AND o.date_created_gmt >= %s AND o.date_created_gmt < %s
             AND oim.meta_value IN ({$ids_placeholder})",
            $from, $to
        ));

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
        $now   = current_time('Y-m-d H:i:s');
        return self::avg_items_per_order_period($since, $now);
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

        // Total events + unique sessions in one query
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS total, COUNT(DISTINCT session_id) AS sessions
             FROM {$table} WHERE created_at >= %s",
            $since
        ), ARRAY_A);

        // Add-to-cart count
        $atc = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE event_name = 'oz_add_to_cart' AND created_at >= %s",
            $since
        ));

        // Checkout clicks
        $checkout = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE event_name = 'oz_cart_checkout_clicked' AND created_at >= %s",
            $since
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
     *
     * @param string $source  'product' or 'cart'
     * @param int $days
     * @return array  [['event_name' => string, 'count' => int], ...]
     */
    public static function by_source($source, $days) {
        global $wpdb;
        $table = OZ_Analytics_Store::table_name();
        $since = self::since_date($days);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT event_name, COUNT(*) AS count
             FROM {$table}
             WHERE source = %s AND created_at >= %s
             GROUP BY event_name
             ORDER BY count DESC",
            $source,
            $since
        ), ARRAY_A);
    }

    /**
     * Top values for a specific JSON key within an event's event_data.
     * E.g. top colors: top_values('oz_color_selected', 'oz_color', 30, 10)
     *
     * Uses JSON_UNQUOTE + JSON_EXTRACT for MySQL 5.7+ / MariaDB 10.2+.
     *
     * @param string $event_name  Event to filter on
     * @param string $json_key    Key inside event_data JSON
     * @param int $days
     * @param int $limit          Max results (default 10)
     * @return array  [['value' => string, 'count' => int], ...]
     */
    public static function top_values($event_name, $json_key, $days, $limit = 10) {
        global $wpdb;
        $table = OZ_Analytics_Store::table_name();
        $since = self::since_date($days);

        // Build JSON path: $.oz_color → extracts "Sand 2" from {"oz_color":"Sand 2"}
        $json_path = '$.' . $json_key;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT JSON_UNQUOTE(JSON_EXTRACT(event_data, %s)) AS value, COUNT(*) AS count
             FROM {$table}
             WHERE event_name = %s AND created_at >= %s
             GROUP BY value
             HAVING value IS NOT NULL AND value != 'null'
             ORDER BY count DESC
             LIMIT %d",
            $json_path,
            $event_name,
            $since,
            $limit
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Conversion funnel: color_selected → add_to_cart → checkout_clicked.
     * Returns counts for each stage.
     *
     * @param int $days
     * @return array  ['color_selected' => int, 'add_to_cart' => int, 'checkout' => int]
     */
    public static function funnel($days) {
        global $wpdb;
        $table = OZ_Analytics_Store::table_name();
        $since = self::since_date($days);

        // Single query with conditional counts
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(CASE WHEN event_name = 'oz_color_selected' THEN 1 ELSE 0 END) AS color_selected,
                SUM(CASE WHEN event_name = 'oz_add_to_cart' THEN 1 ELSE 0 END) AS add_to_cart,
                SUM(CASE WHEN event_name = 'oz_cart_checkout_clicked' THEN 1 ELSE 0 END) AS checkout
             FROM {$table}
             WHERE created_at >= %s",
            $since
        ), ARRAY_A);

        return [
            'color_selected' => intval($row['color_selected']),
            'add_to_cart'    => intval($row['add_to_cart']),
            'checkout'       => intval($row['checkout']),
        ];
    }

    /**
     * Daily event trend for the given period.
     * @todo Add a sparkline/trend chart to the Dashboard in a future iteration.
     *
     * @param int $days
     * @return array  [['date' => 'YYYY-MM-DD', 'count' => int], ...]
     */
    public static function daily_trend($days) {
        global $wpdb;
        $table = OZ_Analytics_Store::table_name();
        $since = self::since_date($days);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) AS date, COUNT(*) AS count
             FROM {$table}
             WHERE created_at >= %s
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $since
        ), ARRAY_A);
    }


    /* ══════════════════════════════════════════════════════════
     * HELPERS
     * ══════════════════════════════════════════════════════════ */

    /**
     * Helper: calculate the "since" datetime string for $days ago.
     * Uses current_time() to match the timezone used in Store::insert().
     *
     * @param int $days
     * @return string  MySQL datetime string
     */
    private static function since_date($days) {
        return date('Y-m-d H:i:s', current_time('timestamp') - (absint($days) * DAY_IN_SECONDS));
    }
}
