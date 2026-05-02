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

    /** How many product IDs to fetch from the SQL ranking. Slightly inflated
     *  vs. card count because grouping consolidates multiple IDs into one card. */
    const FETCH_LIMIT = 18;

    /** Hard cap on cards rendered after grouping. */
    const MAX_CARDS = 10;

    /** Minimum cards required before we render the carousel. */
    const MIN_CARDS = 4;

    /** Transient TTL — 24h keeps queries cheap, hook below invalidates on new order. */
    const CACHE_TTL = DAY_IN_SECONDS;

    /** Transient key prefix. Bumped to v2 when the cache shape changes. */
    const CACHE_PREFIX = 'oz_fbt_v2_';

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
            $seen = array_flip($ids);
            foreach ($globally_top as $gid) {
                if (!isset($seen[$gid]) && $gid !== $product_id) {
                    $ids[] = $gid;
                    if (count($ids) >= self::FETCH_LIMIT) break;
                }
            }
        }

        // Group size-variant siblings (e.g. "Verfbak 18cm" + "Verfbak 32cm" →
        // one card with size pills) so the carousel doesn't show three near-
        // identical products in a row.
        $cards = self::group_into_cards($ids);

        // Cap card count after grouping. Order is preserved from the trending rank.
        $cards = array_slice($cards, 0, self::MAX_CARDS);

        set_transient($cache_key, $cards, self::CACHE_TTL);
        return $cards;
    }

    /**
     * Strip a trailing size suffix from a product name to find its grouping key.
     * Examples (input → output):
     *   "Verfbak 18cm"                       → "Verfbak"
     *   "Verfbak 32 cm"                      → "Verfbak"
     *   "Pu roller 50 cm"                    → "Pu roller"
     *   "PU roller 2 componenten 18 cm"      → "PU roller 2 componenten"
     *   "Frans mes 25 cm"                    → "Frans mes"
     *   "Beton Ciré 5m2"                     → "Beton Ciré"
     *   "Stuco Paste"                        → "Stuco Paste"  (unchanged)
     *
     * Lower-cased + collapsed whitespace so cosmetic case/spacing differences
     * across product names don't split a logical group.
     */
    private static function group_key_from_name($name) {
        $name = (string) $name;
        // Trim recognised size suffixes (cm, mm, ml, l, gr, kg, m2, m²).
        $stripped = preg_replace(
            '/\s+\d+\s*(cm|mm|ml|l|gr|kg|m2|m²)\b.*$/iu',
            '',
            $name
        );
        return strtolower(trim(preg_replace('/\s+/', ' ', $stripped)));
    }

    /**
     * Detect a short human-readable size label from a product name.
     *   "Verfbak 18cm"   → "18cm"
     *   "Verfbak 32 cm"  → "32cm"
     *   "Pu roller 50 cm" → "50cm"
     *   "Verfbak"        → "" (no label)
     */
    public static function size_label_from_name($name) {
        if (preg_match('/(\d+)\s*(cm|mm|ml|l|gr|kg|m2|m²)\b/iu', (string) $name, $m)) {
            return $m[1] . strtolower($m[2]);
        }
        return '';
    }

    /**
     * Walk the ranked product IDs and consolidate size-variant siblings into
     * "group" cards. Single products fall through as their own cards. Order
     * follows the ranking — a group inherits the position of its first member.
     *
     * Returns an array of cards:
     *   ['type' => 'single', 'product_id' => int]
     *   ['type' => 'group',  'base_name' => string, 'variant_ids' => int[]]
     *
     * Stored in cache as plain arrays (no WC_Product objects), so cache stays
     * cheap to serialize and survives object cache flushes.
     */
    private static function group_into_cards($ids) {
        // First pass: bucket by group key so we know which keys have ≥2 members.
        $by_key   = [];      // key => [pid, pid, ...]
        $key_seen = [];      // key => first index in $ids (preserves order)

        foreach ($ids as $i => $pid) {
            $p = wc_get_product($pid);
            if (!$p) continue;
            $key = self::group_key_from_name($p->get_name());
            if (!isset($key_seen[$key])) $key_seen[$key] = $i;
            $by_key[$key][] = (int) $pid;
        }

        // Second pass: emit cards in the original order, group only when ≥2
        // members exist for that key. A single-member key always becomes a
        // 'single' card so we don't add overlay UI for products that have no
        // siblings to choose from.
        $emitted = [];
        $cards   = [];
        foreach ($ids as $pid) {
            $p = wc_get_product($pid);
            if (!$p) continue;
            $key = self::group_key_from_name($p->get_name());
            if (isset($emitted[$key])) continue;
            $emitted[$key] = true;

            $members = $by_key[$key];
            if (count($members) >= 2) {
                $cards[] = [
                    'type'        => 'group',
                    'base_name'   => self::pretty_base_name_for_key($key, $members),
                    'variant_ids' => $members,
                ];
            } else {
                $cards[] = [
                    'type'       => 'single',
                    'product_id' => (int) $members[0],
                ];
            }
        }
        return $cards;
    }

    /**
     * Pick a clean display name for a group. Uses the SHORTEST member name
     * stripped of its size suffix — that's usually the cleanest base label
     * (e.g. "Verfbak" instead of "Verfbak Standaard 18cm Roze Variant").
     */
    private static function pretty_base_name_for_key($key, $member_ids) {
        $best = '';
        foreach ($member_ids as $mid) {
            $p = wc_get_product($mid);
            if (!$p) continue;
            $candidate = preg_replace(
                '/\s+\d+\s*(cm|mm|ml|l|gr|kg|m2|m²)\b.*$/iu',
                '',
                $p->get_name()
            );
            $candidate = trim(preg_replace('/\s+/', ' ', $candidate));
            if ($best === '' || strlen($candidate) < strlen($best)) {
                $best = $candidate;
            }
        }
        return $best;
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
        $limit          = absint(self::FETCH_LIMIT);
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
        $limit          = absint(self::FETCH_LIMIT);

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
