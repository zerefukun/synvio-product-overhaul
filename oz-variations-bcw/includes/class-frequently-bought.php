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

    /** Multiplier applied to the current-product (color-specific) score on
     *  top of the line baseline. Keeps line trends stable while letting an
     *  item with strong colour-specific affinity still bubble up. */
    const COLOR_BOOST = 1.5;

    /** Transient TTL — 24h keeps queries cheap, hook below invalidates on new order. */
    const CACHE_TTL = DAY_IN_SECONDS;

    /** Transient key prefix. Bump on every change to the cached payload shape. */
    const CACHE_PREFIX = 'oz_fbt_v4_';

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

        // Smart blend:
        //   1. LINE BASELINE — aggregate co-purchases across every product in
        //      the same product line as $product_id. Catches the trend at the
        //      whole-line level so a freshly viewed colour gets the line's
        //      collective signal even with thin per-color order history.
        //   2. COLOR-SPECIFIC BOOST — the current product's own co-purchase
        //      counts get added on top with a weight, so an item that's
        //      meaningfully more popular with THIS exact colour still bubbles
        //      up (e.g. Colorfresh that pairs disproportionately with darker
        //      colours pops higher when one of those colours is selected).
        $line_ids   = self::resolve_line_product_ids($product_id);
        $line_score = $line_ids ? self::compute_scored($line_ids, $product_id) : [];
        $self_score = self::compute_scored([$product_id], $product_id);

        $blended = $line_score;
        foreach ($self_score as $pid => $score) {
            $blended[$pid] = ($blended[$pid] ?? 0) + (self::COLOR_BOOST * $score);
        }
        arsort($blended, SORT_NUMERIC);
        $ids = array_slice(array_keys($blended), 0, self::FETCH_LIMIT);

        // Fallback: not enough specific signal → top trending tools globally.
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

        // Cap card count after grouping. Order is preserved from the blended rank.
        $cards = array_slice($cards, 0, self::MAX_CARDS);

        set_transient($cache_key, $cards, self::CACHE_TTL);
        return $cards;
    }

    /**
     * Walk the ranked product IDs and consolidate size-variant siblings into
     * "group" cards based on the central oz_bcw_get_sized_families() map. Any
     * trending ID that belongs to a sized family (whether base or sub-size)
     * collapses into one group card with ALL the family's sizes — so a card
     * shows the full set of pills even when only one variant happened to rank.
     *
     * Single products fall through as their own cards. Order follows the
     * ranking — a group inherits the position of its first ranked member.
     *
     * Returns an array of cards:
     *   ['type' => 'single', 'product_id' => int]
     *   ['type' => 'group',  'base_id' => int, 'name' => string,
     *                         'sizes' => [['label'=>'10cm','wcId'=>11175,'price'=>2.50], …]]
     *
     * Stored in cache as plain arrays (no WC_Product objects), so cache stays
     * cheap to serialize and survives object cache flushes.
     */
    private static function group_into_cards($ids) {
        $families = function_exists('oz_bcw_get_sized_families')
            ? oz_bcw_get_sized_families()
            : [];

        // Reverse map: any size wcId → its family base ID.
        $member_to_base = [];
        foreach ($families as $base_id => $family) {
            foreach ($family['sizes'] as $sz) {
                $member_to_base[(int) $sz['wcId']] = (int) $base_id;
            }
        }

        $emitted_bases = [];
        $cards         = [];
        foreach ($ids as $pid) {
            $pid = (int) $pid;
            // Does this product belong to a known sized family?
            if (isset($member_to_base[$pid])) {
                $base = $member_to_base[$pid];
                if (isset($emitted_bases[$base])) continue;  // already shown earlier in the rank
                $emitted_bases[$base] = true;
                $cards[] = [
                    'type'    => 'group',
                    'base_id' => $base,
                    'name'    => $families[$base]['name'],
                    'sizes'   => $families[$base]['sizes'],
                ];
                continue;
            }
            // No family → render as its own card.
            $cards[] = [
                'type'       => 'single',
                'product_id' => $pid,
            ];
        }
        return $cards;
    }

    /**
     * Resolve the full list of product IDs in $product_id's product line.
     * Falls back to [$product_id] when no line is detected (e.g. PDPs that
     * aren't part of a configured Beton-Ciré line). The whole-line list lets
     * the trending query aggregate across every colour at once.
     *
     * @param int $product_id
     * @return int[]
     */
    private static function resolve_line_product_ids($product_id) {
        if (!class_exists('OZ_Product_Line_Config')) return [$product_id];
        $product = wc_get_product($product_id);
        if (!$product) return [$product_id];

        $line_key = OZ_Product_Line_Config::detect($product);
        if (!$line_key) return [$product_id];

        $config = OZ_Product_Line_Config::get_config($line_key);
        if (empty($config['cats'])) return [$product_id];

        // Pull all visible products in the line's categories. Cached per
        // line key for 24h so we don't re-run the term query on every PDP load.
        $cache_key = self::CACHE_PREFIX . 'line_pids_' . $line_key;
        $cached    = get_transient($cache_key);
        if (is_array($cached) && !empty($cached)) return $cached;

        $ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [[
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => array_map('absint', $config['cats']),
            ]],
        ]);
        $ids = array_map('absint', $ids ?: [$product_id]);
        set_transient($cache_key, $ids, self::CACHE_TTL);
        return $ids;
    }

    /**
     * Time-decayed co-purchase scoring.
     *
     * Decay formula: 1 / max(1, days_since_order / 30).
     *   - This month:         1.000
     *   - 2 months ago:       0.500
     *   - 6 months ago:       0.167
     *   - 1 year ago:         0.083
     * So a tool bought once last week outranks a tool bought 5 times two years ago.
     *
     * @param int[] $source_ids   Product IDs whose orders we treat as "the source".
     *                            Pass the whole line for line-baseline ranking, or
     *                            [$single_id] for colour-specific signal.
     * @param int   $exclude_pid  Product id to remove from results (don't recommend
     *                            the product the user is already viewing).
     * @return array<int, float>  pid => score, sorted desc by score.
     */
    private static function compute_scored(array $source_ids, $exclude_pid) {
        global $wpdb;
        if (empty($source_ids)) return [];

        $candidate_cats = implode(',', array_map('absint', self::CANDIDATE_CATEGORY_IDS));
        $source_csv     = implode(',', array_map('absint', $source_ids));
        $exclude_pid    = absint($exclude_pid);

        // Pulling source orders by IN (...) lets us aggregate the whole line
        // in a single query. The HAVING co_orders >= 2 guard stays — keeps
        // single-occurrence noise out of the ranking.
        $sql = "
            SELECT candidate.product_id AS pid,
                   SUM( 1.0 / GREATEST(1, DATEDIFF(NOW(), o.post_date) / 30) ) AS score,
                   COUNT(DISTINCT o.ID) AS co_orders
            FROM {$wpdb->prefix}woocommerce_order_items src_oi
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta src_om
                ON src_om.order_item_id = src_oi.order_item_id
                AND src_om.meta_key = '_product_id'
                AND src_om.meta_value IN ({$source_csv})
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
              AND candidate.product_id NOT IN ({$source_csv})
            GROUP BY candidate.product_id
            HAVING co_orders >= 2
            ORDER BY score DESC
            LIMIT %d
        ";

        $rows = $wpdb->get_results(
            $wpdb->prepare($sql, $exclude_pid, absint(self::FETCH_LIMIT) * 2),
            ARRAY_A
        );
        if (!$rows) return [];

        $scored = [];
        foreach ($rows as $r) {
            $scored[(int) $r['pid']] = (float) $r['score'];
        }
        return $scored;
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
