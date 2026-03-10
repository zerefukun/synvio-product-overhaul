<?php
/**
 * Analytics Reporter — Query and aggregation layer
 *
 * Reads from OZ_Analytics_Store's table and returns plain associative arrays.
 * No HTML, no formatting, no HTTP — just SQL queries and array results.
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
     * High-level summary for the dashboard cards.
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
