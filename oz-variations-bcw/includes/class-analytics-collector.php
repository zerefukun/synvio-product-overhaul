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
        if (!in_array($source, ['product', 'cart'], true)) {
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
