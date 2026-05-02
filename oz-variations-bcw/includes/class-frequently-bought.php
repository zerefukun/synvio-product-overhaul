<?php
/**
 * Frequently Bought Together — auto-derived from real WooCommerce orders.
 *
 * Picks tools/accessories that customers actually bought alongside the source
 * product, ranked by a time-decayed score so the carousel naturally re-orders
 * itself as buying patterns shift. Zero admin maintenance: no list to curate,
 * no rules to keep up to date.
 *
 * Whitelist (carousel candidates): products in the Gereedschap (19) or
 * Losse Materialen (17) categories. Main product lines (Beton Ciré, Microcement,
 * Lavasteen, Metallic Velvet) are intentionally excluded — those belong on
 * their own PDPs, not as upsell tiles.
 *
 * Scoring: SUM( 1 / max(1, days_ago / 30) ) per candidate. A co-purchase from
 * this month weighs ~1.0; from a year ago, ~0.083. Fresh trends bubble up
 * automatically without anyone touching the code.
 *
 * Caching: per-source-product transients with 24h TTL. Auto-invalidated when
 * a new order completes (woocommerce_order_status_completed hook).
 *
 * @package OZ_Variations_BCW
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_Frequently_Bought {

    /** Carousel candidate categories. Anything outside these is filtered out. */
    const CANDIDATE_CATEGORY_IDS = [19, 17]; // Gereedschap + Losse Materialen

    /** How many products to return at most. */
    const LIMIT = 10;

    /** Minimum unique candidates required before we render the carousel. */
    const MIN_CARDS = 4;

    /** Transient TTL — 24h keeps queries cheap, hook below invalidates on new order. */
    const CACHE_TTL = DAY_IN_SECONDS;

    /** Transient key prefix. */
    const CACHE_PREFIX = 'oz_fbt_v1_';

    /**
     * Init: register order-completion hook so cache stays in sync with reality.
     * Called once from the main plugin loader.
     */
    public static function init() {
        // When an order completes, invalidate cached lists for every product
        // in that order. Next pageview recomputes against fresh data.
        add_action('woocommerce_order_status_completed', [__CLASS__, 'on_order_completed']);
        add_action('woocommerce_order_status_processing', [__CLASS__, 'on_order_completed']);
    }

    /**
     * Get the ranked product IDs to show in the carousel for $product_id.
     * Returns an empty array if there's not enough signal — caller checks count
     * and skips rendering when below MIN_CARDS so we never show a half-empty row.
     *
     * @param int $product_id  The PDP product the carousel is being rendered on.
     * @return int[]           Product IDs, ordered by trending score (descending).
     */
    public static function get_for_product($product_id) {
        $product_id = absint($product_id);
        if (!$product_id) {
            return [];
        }

        $cache_key = self::CACHE_PREFIX . $product_id;
        $cached    = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $ids = self::compute($product_id);

        // Fallback: not enough product-specific signal → top trending tools globally.
        // Keeps the carousel useful for new products / niche items with no order history.
        if (count($ids) < self::MIN_CARDS) {
            $globally_top = self::compute_global_fallback();
            // Deduplicate while preserving product-specific order first
            $seen = array_flip($ids);
            foreach ($globally_top as $gid) {
                if (!isset($seen[$gid]) && $gid !== $product_id) {
                    $ids[] = $gid;
                    if (count($ids) >= self::LIMIT) break;
                }
            }
        }

        set_transient($cache_key, $ids, self::CACHE_TTL);
        return $ids;
    }

    /**
     * Run the time-decayed co-purchase query for a single source product.
     *
     * Decay formula: 1 / max(1, days_since_order / 30).
     *   - This month:         1.000
     *   - 2 months ago:       0.500
     *   - 6 months ago:       0.167
     *   - 1 year ago:         0.083
     * So a tool bought once last week outranks a tool bought 5 times two years ago.
     *
     * @param int $source_product_id
     * @return int[]
     */
    private static function compute($source_product_id) {
        global $wpdb;

        $candidate_cats = implode(',', array_map('absint', self::CANDIDATE_CATEGORY_IDS));
        $limit          = absint(self::LIMIT);
        $source_id      = absint($source_product_id);

        // Subquery selects orders that contain the source product (+ are in
        // a real revenue state). Outer query joins back to find every other
        // product in those orders, filters to whitelist categories, and scores.
        $sql = "
            SELECT candidate.product_id AS pid,
                   SUM( 1.0 / GREATEST(1, DATEDIFF(NOW(), o.post_date) / 30) ) AS score,
                   COUNT(DISTINCT o.ID) AS co_orders
            FROM {$wpdb->prefix}woocommerce_order_items src_oi
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta src_om
                ON src_om.order_item_id = src_oi.order_item_id
                AND src_om.meta_key = '_product_id'
                AND src_om.meta_value = %d
            INNER JOIN {$wpdb->posts} o
                ON o.ID = src_oi.order_id
                AND o.post_type = 'shop_order'
                AND o.post_status IN ('wc-completed','wc-processing')
            INNER JOIN {$wpdb->prefix}woocommerce_order_items cand_oi
                ON cand_oi.order_id = src_oi.order_id
                AND cand_oi.order_item_id != src_oi.order_item_id
            INNER JOIN (
                SELECT om.order_item_id, om.meta_value AS product_id
                FROM {$wpdb->prefix}woocommerce_order_itemmeta om
                WHERE om.meta_key = '_product_id'
            ) candidate ON candidate.order_item_id = cand_oi.order_item_id
            INNER JOIN {$wpdb->term_relationships} tr
                ON tr.object_id = candidate.product_id
                AND tr.term_taxonomy_id IN ({$candidate_cats})
            INNER JOIN {$wpdb->posts} cp
                ON cp.ID = candidate.product_id
                AND cp.post_status = 'publish'
            WHERE candidate.product_id != %d
            GROUP BY candidate.product_id
            HAVING co_orders >= 2
            ORDER BY score DESC
            LIMIT %d
        ";

        $results = $wpdb->get_col(
            $wpdb->prepare($sql, $source_id, $source_id, $limit)
        );

        return array_map('absint', $results ?: []);
    }

    /**
     * Top trending tools across the entire shop — used as fallback when a
     * specific product has weak co-purchase signal. Cached separately so we
     * compute once per day, not per product.
     *
     * @return int[]
     */
    private static function compute_global_fallback() {
        $cache_key = self::CACHE_PREFIX . 'global';
        $cached    = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $candidate_cats = implode(',', array_map('absint', self::CANDIDATE_CATEGORY_IDS));
        $limit          = absint(self::LIMIT);

        $sql = "
            SELECT candidate.product_id AS pid,
                   SUM( 1.0 / GREATEST(1, DATEDIFF(NOW(), o.post_date) / 30) ) AS score
            FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->posts} o
                ON o.ID = oi.order_id
                AND o.post_type = 'shop_order'
                AND o.post_status IN ('wc-completed','wc-processing')
            INNER JOIN (
                SELECT om.order_item_id, om.meta_value AS product_id
                FROM {$wpdb->prefix}woocommerce_order_itemmeta om
                WHERE om.meta_key = '_product_id'
            ) candidate ON candidate.order_item_id = oi.order_item_id
            INNER JOIN {$wpdb->term_relationships} tr
                ON tr.object_id = candidate.product_id
                AND tr.term_taxonomy_id IN ({$candidate_cats})
            INNER JOIN {$wpdb->posts} cp
                ON cp.ID = candidate.product_id
                AND cp.post_status = 'publish'
            GROUP BY candidate.product_id
            ORDER BY score DESC
            LIMIT %d
        ";

        $results = $wpdb->get_col($wpdb->prepare($sql, $limit));
        $ids     = array_map('absint', $results ?: []);

        set_transient($cache_key, $ids, self::CACHE_TTL);
        return $ids;
    }

    /**
     * Invalidate caches for every product in the just-completed order, plus
     * the global fallback. Cheap and surgical: only touches transients tied
     * to products that actually had a state change.
     *
     * @param int $order_id
     */
    public static function on_order_completed($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Invalidate global fallback so trending stays current.
        delete_transient(self::CACHE_PREFIX . 'global');

        // Invalidate per-source caches for every product in this order.
        // Both the products themselves AND any product whose carousel might
        // include them as candidates need to refresh — but we only know the
        // first set cheaply, so we clear those. Other PDPs will refresh on
        // their next 24h cache miss anyway.
        foreach ($order->get_items() as $item) {
            $pid = $item->get_product_id();
            if ($pid) {
                delete_transient(self::CACHE_PREFIX . $pid);
            }
        }
    }
}
