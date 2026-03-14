<?php
/**
 * Analytics Collector — Thin AJAX layer
 *
 * Registers the AJAX endpoint that receives beacon events from the browser.
 * Validates nonce, checks event allowlist, sanitizes input, then hands off
 * to OZ_Analytics_Store::insert(). No business logic here.
 *
 * Also manages the daily cleanup cron job.
 *
 * @package OZ_Variations_BCW
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_Analytics_Collector {

    /** Cron hook name */
    const CRON_HOOK = 'oz_analytics_daily_cleanup';

    /**
     * Allowlist of valid event names (24 total).
     * Any event not in this list is silently rejected.
     * Keeps the DB clean — only known events get stored.
     */
    private static $valid_events = [
        // Session tracking (1)
        'oz_session_start',
        // Product page events (13)
        'oz_color_selected',
        'oz_color_mode_changed',
        'oz_option_selected',
        'oz_tool_mode_changed',
        'oz_tool_toggled',
        'oz_qty_changed',
        'oz_add_to_cart',
        'oz_add_to_cart_error',
        'oz_upsell_shown',
        'oz_upsell_accepted',
        'oz_upsell_skipped',
        'oz_sheet_opened',
        'oz_gallery_image',
        // Cart drawer events (11)
        'oz_cart_opened',
        'oz_cart_closed',
        'oz_cart_qty_increased',
        'oz_cart_qty_decreased',
        'oz_cart_item_removed',
        'oz_cart_qty_input',
        'oz_cart_upsell_added',
        'oz_cart_upsell_size_selected',
        'oz_cart_upsell_option_selected',
        'oz_cart_checkout_clicked',
        'oz_cart_continue_shopping',
        'oz_cart_free_shipping_reached',
    ];

    /**
     * Initialize AJAX hooks and cron schedule.
     */
    public static function init() {
        // AJAX endpoint — both logged-in and guest users
        add_action('wp_ajax_oz_track_event', [__CLASS__, 'ajax_track_event']);
        add_action('wp_ajax_nopriv_oz_track_event', [__CLASS__, 'ajax_track_event']);

        // Heartbeat endpoint — both logged-in and guest users
        add_action('wp_ajax_oz_heartbeat', [__CLASS__, 'ajax_heartbeat']);
        add_action('wp_ajax_nopriv_oz_heartbeat', [__CLASS__, 'ajax_heartbeat']);

        // Active sessions count — admin only (for dashboard polling)
        add_action('wp_ajax_oz_active_sessions', [__CLASS__, 'ajax_active_sessions']);

        // Traffic source landing pages — admin only
        add_action('wp_ajax_oz_traffic_landings', [__CLASS__, 'ajax_traffic_landings']);

        // Daily cleanup cron
        add_action(self::CRON_HOOK, [__CLASS__, 'run_cleanup']);

        // Schedule cron if not already scheduled
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
    }

    /**
     * AJAX handler: receive and store a single analytics event.
     *
     * Expects POST with:
     *   nonce       — wp_nonce for 'oz_analytics'
     *   event_name  — must be in allowlist
     *   event_data  — JSON string of event parameters
     *   source      — 'product' or 'cart'
     */
    public static function ajax_track_event() {
        // Verify nonce
        if (!check_ajax_referer('oz_analytics', 'nonce', false)) {
            wp_send_json_error('Invalid nonce', 403);
            return;
        }

        // Sanitize simple string inputs
        $event_name = isset($_POST['event_name']) ? sanitize_text_field($_POST['event_name']) : '';
        $source     = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '';

        // Validate and sanitize JSON event data — decode then re-encode
        // (sanitize_text_field would corrupt JSON with HTML entities or special chars)
        $raw_data = isset($_POST['event_data']) ? wp_unslash($_POST['event_data']) : '{}';
        $decoded  = json_decode($raw_data, true);
        if (!is_array($decoded)) {
            wp_send_json_error('Invalid event data', 400);
            return;
        }
        $event_data = wp_json_encode($decoded);

        // Validate event name against allowlist
        if (!in_array($event_name, self::$valid_events, true)) {
            wp_send_json_error('Unknown event', 400);
            return;
        }

        // Validate source
        if (!in_array($source, ['product', 'cart', 'session'], true)) {
            wp_send_json_error('Invalid source', 400);
            return;
        }

        // Extract product_id from decoded event data if present
        $product_id = isset($decoded['oz_product_id']) ? absint($decoded['oz_product_id']) : 0;

        // Generate a session ID from cookies (anonymous, no PII)
        // Uses WC session cookie if available, falls back to a hash of IP + user agent
        $session_id = self::get_session_id();

        // Store the event (product_id 0 = no product, e.g. cart events)
        OZ_Analytics_Store::insert($event_name, $event_data, $source, $product_id, $session_id);

        wp_send_json_success();
    }

    /**
     * AJAX handler: receive heartbeat ping from browser.
     * Lightweight — just updates last_seen for this session.
     * Filters out bots and logged-in admins to match Clarity's session count.
     */
    public static function ajax_heartbeat() {
        if (!check_ajax_referer('oz_analytics', 'nonce', false)) {
            wp_send_json_error('Invalid nonce', 403);
            return;
        }

        // Skip admins — same exclusion as Clarity (don't pollute live session data)
        if (current_user_can('manage_options')) {
            wp_send_json_success();
            return;
        }

        // Skip bots — common crawlers that execute JS and send heartbeats
        if (self::is_bot()) {
            wp_send_json_success();
            return;
        }

        $session_id = self::get_session_id();
        $page_url   = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';

        OZ_Analytics_Store::update_heartbeat($session_id, $page_url);

        wp_send_json_success();
    }

    /**
     * Detect bots by user agent string.
     * These crawlers can execute JS and fire heartbeats,
     * inflating our active session count vs Clarity.
     *
     * @return bool  True if current request is from a known bot
     */
    private static function is_bot() {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        if ($ua === '') return true; // No user agent = bot

        // Common bot patterns — covers Google, Bing, SEO tools, uptime monitors
        $bot_patterns = [
            'googlebot', 'bingbot', 'yandexbot', 'baiduspider', 'duckduckbot',
            'slurp', 'facebookexternalhit', 'linkedinbot', 'twitterbot',
            'applebot', 'semrushbot', 'ahrefsbot', 'mj12bot', 'dotbot',
            'petalbot', 'bytespider', 'gptbot', 'claudebot', 'anthropic',
            'pingdom', 'uptimerobot', 'statuscake', 'site24x7',
            'headlesschrome', 'phantomjs', 'python-requests', 'curl/',
            'wget/', 'go-http-client', 'java/', 'crawler', 'spider', 'scraper',
        ];

        foreach ($bot_patterns as $pattern) {
            if (strpos($ua, $pattern) !== false) return true;
        }

        return false;
    }

    /**
     * AJAX handler: return live dashboard data.
     * Returns active session count, session details, and recent events.
     * Admin-only (registered on wp_ajax_ only, not nopriv).
     */
    public static function ajax_active_sessions() {
        check_ajax_referer('oz_active_sessions');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized', 403);
            return;
        }

        // 45s lookback — tighter window to match Clarity's session detection
        $sessions = OZ_Analytics_Store::get_active_sessions(45);

        // Optional session filter for viewing one session's journey
        $filter_session = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        $limit = $filter_session ? 50 : 20;
        $events = OZ_Analytics_Store::recent_events($limit, $filter_session);

        wp_send_json_success([
            'active'          => count($sessions),
            'sessions'        => $sessions,
            'events'          => $events,
            'filter_session'  => $filter_session,
        ]);
    }

    /**
     * AJAX handler: return landing pages for a specific traffic source.
     * Admin-only. Used when clicking a traffic source row in the dashboard.
     */
    public static function ajax_traffic_landings() {
        check_ajax_referer('oz_traffic_landings');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized', 403);
            return;
        }

        $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '';
        $medium = isset($_POST['medium']) ? sanitize_text_field($_POST['medium']) : '';
        $range  = isset($_POST['range'])  ? sanitize_text_field($_POST['range'])  : '7';

        if (!$source) {
            wp_send_json_error('Missing source', 400);
            return;
        }

        // Set the range for the reporter (needed for until_date)
        if ($range === 'yesterday') {
            OZ_Analytics_Reporter::set_range('yesterday');
        } else {
            OZ_Analytics_Reporter::set_range(absint($range));
        }

        $landings = OZ_Analytics_Reporter::landings_by_source(
            $source,
            $medium,
            $range === 'yesterday' ? 'yesterday' : absint($range),
            50
        );

        wp_send_json_success([
            'source'   => $source,
            'medium'   => $medium,
            'landings' => $landings,
        ]);
    }

    /**
     * Generate an anonymous session identifier.
     * Uses WC session cookie if available, otherwise hashes IP + user agent.
     * No PII is stored — just a fingerprint for session counting.
     *
     * @return string  Session ID (max 50 chars)
     */
    private static function get_session_id() {
        // Try WooCommerce session cookie first
        if (isset($_COOKIE['wp_woocommerce_session_' . COOKIEHASH])) {
            $wc_cookie = $_COOKIE['wp_woocommerce_session_' . COOKIEHASH];
            // WC session cookie format: "session_id||expiry||expiring||hash"
            $parts = explode('||', $wc_cookie);
            if (!empty($parts[0])) {
                return substr($parts[0], 0, 50);
            }
        }

        // Fallback: hash of IP + user agent (anonymous fingerprint)
        $ip    = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $ua    = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
        $today = date('Y-m-d'); // Include date so sessions reset daily

        return substr(md5($ip . $ua . $today), 0, 32);
    }

    /**
     * Cron callback: delete events older than 90 days.
     */
    public static function run_cleanup() {
        OZ_Analytics_Store::cleanup(90);
    }

    /**
     * Unschedule the cleanup cron.
     * Called on plugin deactivation.
     */
    public static function unschedule_cron() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }
}
