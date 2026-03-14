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

    /** Active sessions table (without prefix) */
    const SESSIONS_TABLE = 'oz_active_sessions';

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
     * Get the full prefixed sessions table name.
     *
     * @return string
     */
    public static function sessions_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::SESSIONS_TABLE;
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

    /* ══════════════════════════════════════════════════════════
     * ACTIVE SESSIONS (heartbeat tracking)
     * Tiny table: session_id (PK) + first_seen + last_seen + page_url.
     * JS pings every 30s, sessions expire after 45s of silence.
     * Sorted by first_seen (stable order — no jumping).
     * ══════════════════════════════════════════════════════════ */

    /**
     * Create the active sessions table.
     * Only holds currently-active rows — never grows beyond ~100 rows.
     * first_seen = when session first appeared, used for stable sort order.
     */
    public static function create_sessions_table() {
        global $wpdb;

        $table   = self::sessions_table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            session_id VARCHAR(50) NOT NULL,
            first_seen DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            page_url VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY  (session_id),
            KEY idx_last_seen (last_seen)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Update heartbeat for a session.
     * INSERT with first_seen on first visit, UPDATE only last_seen on subsequent pings.
     * first_seen stays fixed so the list order is stable.
     *
     * @param string $session_id  Browser session identifier
     * @param string $page_url    Current page URL
     */
    public static function update_heartbeat($session_id, $page_url = '') {
        global $wpdb;

        $table = self::sessions_table_name();
        $now   = current_time('mysql');

        // first_seen is set on INSERT, never updated. last_seen updates every heartbeat.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (session_id, first_seen, last_seen, page_url)
             VALUES (%s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE last_seen = %s, page_url = %s",
            $session_id, $now, $now, $page_url, $now, $page_url
        ));

        // Cleanup stale sessions older than 2 minutes (piggyback on heartbeat)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DELETE FROM {$table} WHERE last_seen < DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
    }

    /**
     * Count sessions active within the last $seconds seconds.
     *
     * @param int $seconds  Lookback window (default 45)
     * @return int  Number of active sessions
     */
    public static function count_active($seconds = 45) {
        global $wpdb;

        $table = self::sessions_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE last_seen >= DATE_SUB(NOW(), INTERVAL %d SECOND)",
            absint($seconds)
        ));
    }

    /**
     * Get all active sessions with their current page.
     * Sorted by first_seen DESC (newest sessions at top, stable order).
     *
     * @param int $seconds  Lookback window (default 45)
     * @return array  [['session_id' => '...', 'page_url' => '...', 'first_seen' => '...', 'last_seen' => '...'], ...]
     */
    public static function get_active_sessions($seconds = 45) {
        global $wpdb;

        $table = self::sessions_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results($wpdb->prepare(
            "SELECT session_id, page_url, first_seen, last_seen
             FROM {$table}
             WHERE last_seen >= DATE_SUB(NOW(), INTERVAL %d SECOND)
             ORDER BY first_seen DESC",
            absint($seconds)
        ), ARRAY_A);
    }

    /**
     * Get recent events for the live feed.
     * Optionally filter by session_id to see one session's full journey.
     *
     * @param int    $limit       Number of events to return (default 20)
     * @param string $session_id  Optional session filter (empty = all sessions)
     * @return array  Recent events with session_id, event_name, event_data, created_at
     */
    public static function recent_events($limit = 20, $session_id = '') {
        global $wpdb;

        $table = self::table_name();

        if ($session_id) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            return $wpdb->get_results($wpdb->prepare(
                "SELECT session_id, event_name, event_data, source, created_at
                 FROM {$table}
                 WHERE session_id = %s
                 ORDER BY id DESC
                 LIMIT %d",
                $session_id,
                absint($limit)
            ), ARRAY_A);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results($wpdb->prepare(
            "SELECT session_id, event_name, event_data, source, created_at
             FROM {$table}
             ORDER BY id DESC
             LIMIT %d",
            absint($limit)
        ), ARRAY_A);
    }
}
