<?php
/**
 * Analytics Store — Pure data access layer
 *
 * Handles database table creation, event insertion, and cleanup.
 * No HTTP, no nonces, no rendering — just SQL.
 * Other classes call Store::insert() and Store::cleanup() without
 * knowing anything about the storage mechanism.
 *
 * @package OZ_Variations_BCW
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_Analytics_Store {

    /** Table name (without prefix) */
    const TABLE = 'oz_analytics_events';

    /**
     * Get the full prefixed table name.
     *
     * @return string
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Create the analytics events table.
     * Safe to call multiple times — dbDelta handles "already exists" gracefully.
     */
    public static function create_table() {
        global $wpdb;

        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        // dbDelta requires exact formatting: two spaces after PRIMARY KEY,
        // column definitions on separate lines, KEY on its own line.
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_name VARCHAR(50) NOT NULL,
            event_data TEXT NOT NULL,
            source VARCHAR(10) NOT NULL,
            product_id INT UNSIGNED NOT NULL DEFAULT 0,
            session_id VARCHAR(50) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_event_created (event_name, created_at),
            KEY idx_source (source),
            KEY idx_created (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Insert a single analytics event.
     *
     * @param string $event_name  Event identifier (e.g. 'oz_color_selected')
     * @param string $event_data  JSON-encoded event parameters
     * @param string $source      'product' or 'cart'
     * @param int $product_id  WC product ID (0 = no product, e.g. cart events)
     * @param string $session_id  Browser session identifier
     * @return int|false  Insert ID on success, false on failure
     */
    public static function insert($event_name, $event_data, $source, $product_id, $session_id) {
        global $wpdb;

        $result = $wpdb->insert(
            self::table_name(),
            [
                'event_name' => $event_name,
                'event_data' => $event_data,
                'source'     => $source,
                'product_id' => absint($product_id),
                'session_id' => $session_id,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Delete events older than $days days.
     * Called by daily cron to keep the table lean.
     *
     * @param int $days  Number of days to retain (default 90)
     * @return int  Number of rows deleted
     */
    public static function cleanup($days = 90) {
        global $wpdb;

        $table    = self::table_name();
        $days_int = absint($days);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days_int
            )
        );
    }
}
