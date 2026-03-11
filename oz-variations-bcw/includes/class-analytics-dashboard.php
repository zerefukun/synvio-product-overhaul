<?php
/**
 * Analytics Dashboard — WP Admin page renderer
 *
 * Three sections:
 * 1. Shop Performance — WC order data (revenue, orders, AOV, top products)
 * 2. Overhaul Impact  — before/after comparison proving ROI of our changes
 * 3. Behavior Analytics — beacon event data (funnels, top colors, upsells)
 *
 * Reads data from OZ_Analytics_Reporter (plain arrays), contains zero SQL.
 * Self-contained CSS via inline <style> block.
 *
 * @package OZ_Variations_BCW
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_Analytics_Dashboard {

    /**
     * Initialize admin menu hook.
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
    }

    /**
     * Register submenu page under WooCommerce.
     */
    public static function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            'BCW Analytics',
            'BCW Analytics',
            'manage_woocommerce',
            'oz-bcw-analytics',
            [__CLASS__, 'render']
        );
    }

    /**
     * Render the full dashboard page.
     */
    public static function render() {
        // Date range from query string (default: 7 days)
        // Support both numeric ranges and special string ranges like 'yesterday'
        $raw_range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : '7';
        if ($raw_range === 'yesterday') {
            $range = 'yesterday';
        } else {
            $range = absint($raw_range);
            if (!in_array($range, [1, 7, 30, 90], true)) {
                $range = 7;
            }
        }

        // Tell reporter which range we're using (for until_date ceiling)
        OZ_Analytics_Reporter::set_range($range);

        // ── Section 1: Shop Performance (WC orders) ──
        $orders       = OZ_Analytics_Reporter::order_summary($range);
        $items_avg    = OZ_Analytics_Reporter::avg_items_per_order($range);
        $top_products = OZ_Analytics_Reporter::top_products($range, 10);
        $by_line      = OZ_Analytics_Reporter::sales_by_line($range);

        // ── Section 2: Overhaul Impact (before/after) ──
        $comparison = OZ_Analytics_Reporter::order_comparison();

        // ── Section 3: Behavior Analytics (beacon events) ──
        $beacon_start = OZ_Analytics_Reporter::earliest_event_date();
        $summary  = OZ_Analytics_Reporter::summary($range);
        $product  = OZ_Analytics_Reporter::by_source('product', $range);
        $cart     = OZ_Analytics_Reporter::by_source('cart', $range);
        $funnel   = OZ_Analytics_Reporter::funnel($range);
        $colors   = OZ_Analytics_Reporter::top_values('oz_color_selected', 'oz_color', $range, 10);
        $upsells  = OZ_Analytics_Reporter::top_values('oz_cart_upsell_added', 'oz_upsell_name', $range, 10);
        $traffic  = OZ_Analytics_Reporter::traffic_sources($range);
        $landings = OZ_Analytics_Reporter::top_landing_pages($range, 10);

        $base_url = admin_url('admin.php?page=oz-bcw-analytics');

        self::render_styles();
        ?>

        <div class="wrap oz-analytics">
            <h1>
                BCW Analytics
                <span class="oz-live-counter" id="ozLiveCounter" title="Actieve sessies (laatste 60 seconden)">
                    <span class="oz-live-dot"></span>
                    <span id="ozLiveCount">-</span> live
                </span>
                <div class="oz-range-tabs">
                    <?php
                    $ranges = [1 => 'Vandaag', 'yesterday' => 'Gisteren', 7 => '7 dagen', 30 => '30 dagen', 90 => '90 dagen'];
                    foreach ($ranges as $val => $label) {
                        printf(
                            '<a href="%s" class="%s">%s</a>',
                            esc_url(add_query_arg('range', $val, $base_url)),
                            $range === $val ? 'active' : '',
                            esc_html($label)
                        );
                    }
                    ?>
                </div>
            </h1>

            <!-- ═══ LIVE PANEL ═══ -->
            <div class="oz-live-panel" id="ozLivePanel">
                <div class="oz-live-sections">
                    <div class="oz-live-sessions">
                        <h3>Actieve Sessies</h3>
                        <div id="ozLiveSessions" class="oz-live-list">
                            <div class="oz-empty">Laden...</div>
                        </div>
                    </div>
                    <div class="oz-live-feed">
                        <h3>Live Event Feed</h3>
                        <div id="ozLiveFeed" class="oz-live-list">
                            <div class="oz-empty">Laden...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ SECTION 1: Shop Performance ═══ -->
            <div class="oz-section-title">Shop Performance</div>

            <div class="oz-cards">
                <?php
                self::render_card('&euro;' . number_format($orders['revenue'], 2, ',', '.'), 'Omzet');
                self::render_card(number_format($orders['orders']), 'Orders');
                self::render_card('&euro;' . number_format($orders['aov'], 2, ',', '.'), 'Gem. Orderwaarde');
                self::render_card($items_avg, 'Items / Order');
                ?>
            </div>

            <div class="oz-columns">
                <div class="oz-panel">
                    <h3>Omzet per Productlijn</h3>
                    <?php self::render_revenue_bars($by_line); ?>
                </div>
                <div class="oz-panel">
                    <h3>Top Producten (omzet)</h3>
                    <?php self::render_product_list($top_products); ?>
                </div>
            </div>

            <!-- ═══ SECTION 2: Overhaul Impact ═══ -->
            <div class="oz-section-title">
                Overhaul Impact
                <span class="oz-section-subtitle">
                    Vergelijking: <?php echo intval($comparison['days']); ?> dagen voor vs. na lancering (<?php echo esc_html($comparison['launch_date']); ?>)
                </span>
            </div>

            <?php
            // Show warning when comparison period is too short to be reliable
            if ($comparison['days'] < 14) {
                printf(
                    '<div class="oz-notice oz-notice-warning">Nog te vroeg voor betrouwbare vergelijking — pas %d van minimaal 14 dagen data. Cijfers kunnen sterk schommelen door individuele orders.</div>',
                    intval($comparison['days'])
                );
            }
            self::render_comparison($comparison);
            ?>

            <!-- ═══ SECTION 3: Behavior Analytics ═══ -->
            <div class="oz-section-title">
                Gedrag Analytics
                <span class="oz-section-subtitle">
                    <?php if ($beacon_start): ?>
                        Tracking sinds <?php echo esc_html($beacon_start); ?> — datumfilter werkt alleen over beschikbare data
                    <?php else: ?>
                        Beacon events — data groeit naarmate bezoekers interacteren
                    <?php endif; ?>
                </span>
            </div>

            <div class="oz-cards">
                <?php
                self::render_card(number_format($summary['total']), 'Events');
                self::render_card(number_format($summary['sessions']), 'Sessies');
                self::render_card(number_format($summary['add_to_carts']), 'Add to Cart');
                self::render_card(number_format($summary['checkouts']), 'Checkout Clicks');
                ?>
            </div>

            <!-- ═══ Traffic Sources ═══ -->
            <div class="oz-columns">
                <div class="oz-panel">
                    <h3>Verkeersbronnen</h3>
                    <?php self::render_traffic_sources($traffic); ?>
                </div>
                <div class="oz-panel">
                    <h3>Top Landingspagina's</h3>
                    <?php self::render_top_list($landings); ?>
                </div>
            </div>

            <div class="oz-columns">
                <div class="oz-panel">
                    <h3>Productpagina Events</h3>
                    <?php self::render_bar_chart($product); ?>
                </div>
                <div class="oz-panel">
                    <h3>Cart Drawer Events</h3>
                    <?php self::render_bar_chart($cart); ?>
                </div>
            </div>

            <!-- Conversion funnel -->
            <div class="oz-panel oz-funnel" style="margin-bottom: 24px;">
                <h3>Conversie Funnel</h3>
                <?php self::render_funnel($funnel); ?>
            </div>

            <!-- Top lists -->
            <div class="oz-columns">
                <div class="oz-panel">
                    <h3>Top Kleuren</h3>
                    <?php self::render_top_list($colors); ?>
                </div>
                <div class="oz-panel">
                    <h3>Top Upsells</h3>
                    <?php self::render_top_list($upsells); ?>
                </div>
            </div>
        </div>

        <!-- Live session counter + feed: polls every 10 seconds -->
        <script>
        (function() {
            var countEl = document.getElementById('ozLiveCount');
            var dotEl = document.querySelector('.oz-live-dot');
            var sessionsEl = document.getElementById('ozLiveSessions');
            var feedEl = document.getElementById('ozLiveFeed');
            var feedTitle = feedEl ? feedEl.previousElementSibling : null;
            if (!countEl) return;

            /* Currently selected session (empty = show all) */
            var selectedSession = '';

            /* Human-readable event labels */
            var labels = {
                oz_session_start: 'Sessie gestart',
                oz_color_selected: 'Kleur gekozen',
                oz_color_mode_changed: 'Kleurmodus',
                oz_option_selected: 'Optie gekozen',
                oz_tool_mode_changed: 'Gereedschap modus',
                oz_tool_toggled: 'Gereedschap aan/uit',
                oz_qty_changed: 'Aantal gewijzigd',
                oz_add_to_cart: 'In winkelmand',
                oz_add_to_cart_error: 'Cart fout',
                oz_gallery_image: 'Galerij bekeken',
                oz_cart_opened: 'Cart geopend',
                oz_cart_closed: 'Cart gesloten',
                oz_cart_checkout_clicked: 'Naar afrekenen',
                oz_cart_upsell_added: 'Upsell toegevoegd',
                oz_cart_upsell_size_selected: 'Upsell maat',
                oz_cart_upsell_option_selected: 'Upsell optie',
                oz_cart_continue_shopping: 'Verder winkelen'
            };

            /* Extract a useful detail from event_data JSON */
            function getDetail(name, data) {
                try {
                    var d = JSON.parse(data);
                    /* oz_color_selected has both oz_color + oz_color_mode;
                       oz_color_mode_changed has only oz_color_mode.
                       Check oz_color first — if present, show color name. Otherwise show mode. */
                    /* Session start: show traffic source + medium */
                    if (d.oz_traffic_source) return d.oz_traffic_source + ' (' + (d.oz_traffic_medium || '') + ')';
                    if (d.oz_color) return d.oz_color;
                    if (d.oz_color_mode) return d.oz_color_mode === 'ral_ncs' ? 'RAL / NCS' : d.oz_color_mode;
                    /* oz_option_selected sends oz_option_type (pu/primer/etc) + oz_option_value */
                    if (d.oz_option_type) return d.oz_option_type + ': ' + (d.oz_option_value || '');
                    if (d.oz_upsell_name) return d.oz_upsell_name;
                    if (d.oz_trigger) return d.oz_trigger;
                    if (d.oz_tool_mode) return d.oz_tool_mode;
                    if (d.oz_tool_id) return d.oz_tool_id + ' ' + (d.oz_tool_action || '');
                    if (d.oz_qty) return 'qty: ' + d.oz_qty;
                    if (d.oz_error) return d.oz_error;
                    if (d.oz_image_index !== undefined) return 'foto ' + d.oz_image_index;
                } catch(e) {}
                return '';
            }

            /* Format time as HH:MM:SS */
            function fmtTime(dateStr) {
                var d = new Date(dateStr.replace(' ', 'T'));
                var h = ('0' + d.getHours()).slice(-2);
                var m = ('0' + d.getMinutes()).slice(-2);
                var s = ('0' + d.getSeconds()).slice(-2);
                return h + ':' + m + ':' + s;
            }

            /* Shorten session ID for display */
            function shortId(id) { return id ? id.substring(0, 8) : ''; }

            /* Render active sessions — clickable to filter event feed */
            function renderSessions(sessions) {
                if (!sessions || !sessions.length) {
                    sessionsEl.innerHTML = '<div class="oz-empty">Geen actieve sessies</div>';
                    return;
                }
                var html = '';
                for (var i = 0; i < sessions.length; i++) {
                    var s = sessions[i];
                    var active = (s.session_id === selectedSession) ? ' selected' : '';
                    html += '<div class="oz-live-session' + active + '" data-sid="' + s.session_id + '">'
                        + '<span class="oz-live-session-dot"></span>'
                        + '<span class="oz-live-session-id">' + shortId(s.session_id) + '</span>'
                        + '<span class="oz-live-session-url">' + (s.page_url || '/') + '</span>'
                        + '<span class="oz-live-session-time">' + fmtTime(s.last_seen) + '</span>'
                        + '</div>';
                }
                sessionsEl.innerHTML = html;
            }

            /* Click handler for sessions — toggle filter */
            sessionsEl.addEventListener('click', function(e) {
                var row = e.target.closest('.oz-live-session');
                if (!row) return;
                var sid = row.getAttribute('data-sid');
                if (selectedSession === sid) {
                    /* Deselect — show all events again */
                    selectedSession = '';
                } else {
                    selectedSession = sid;
                }
                /* Immediately poll with new filter */
                poll();
            });

            /* Render event feed — shows session's journey when filtered */
            function renderFeed(events, filterSession) {
                /* Update feed title */
                if (feedTitle) {
                    if (filterSession) {
                        feedTitle.innerHTML = 'Sessie ' + shortId(filterSession) + ' Journey <span class="oz-feed-clear" id="ozFeedClear">&times; Toon alles</span>';
                    } else {
                        feedTitle.textContent = 'Live Event Feed';
                    }
                }
                if (!events || !events.length) {
                    feedEl.innerHTML = '<div class="oz-empty">' + (filterSession ? 'Geen events voor deze sessie' : 'Nog geen events') + '</div>';
                    return;
                }
                var html = '';
                for (var i = 0; i < events.length; i++) {
                    var ev = events[i];
                    var detail = getDetail(ev.event_name, ev.event_data);
                    var cls = ev.source === 'cart' ? 'cart' : 'product';
                    html += '<div class="oz-live-event">'
                        + '<span class="oz-live-event-time">' + fmtTime(ev.created_at) + '</span>'
                        + '<span class="oz-live-event-name ' + cls + '">' + (labels[ev.event_name] || ev.event_name) + '</span>'
                        + (detail ? '<span class="oz-live-event-detail">' + detail + '</span>' : '')
                        + '</div>';
                }
                feedEl.innerHTML = html;
            }

            /* Clear filter button in feed title */
            document.addEventListener('click', function(e) {
                if (e.target.id === 'ozFeedClear') {
                    selectedSession = '';
                    poll();
                }
            });

            function poll() {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo esc_js(admin_url('admin-ajax.php')); ?>');
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.success) {
                            /* Update counter */
                            countEl.textContent = resp.data.active;
                            if (resp.data.active > 0) {
                                dotEl.classList.add('active');
                            } else {
                                dotEl.classList.remove('active');
                            }
                            /* Update live panels */
                            renderSessions(resp.data.sessions);
                            renderFeed(resp.data.events, resp.data.filter_session);
                        }
                    } catch (e) {}
                };
                var params = 'action=oz_active_sessions&_ajax_nonce=<?php echo wp_create_nonce('oz_active_sessions'); ?>';
                if (selectedSession) params += '&session_id=' + encodeURIComponent(selectedSession);
                xhr.send(params);
            }

            /* Poll immediately, then every 10 seconds */
            poll();
            setInterval(poll, 10000);
        })();
        </script>
        <?php
    }

    /* ══════════════════════════════════════════════════════════
     * RENDER HELPERS
     * ══════════════════════════════════════════════════════════ */

    /**
     * Render a single summary card.
     */
    private static function render_card($value, $label) {
        printf(
            '<div class="oz-card"><div class="oz-card-value">%s</div><div class="oz-card-label">%s</div></div>',
            $value, // Already escaped or formatted before calling
            esc_html($label)
        );
    }

    /**
     * Render the before/after comparison table.
     */
    private static function render_comparison($comparison) {
        $b = $comparison['before'];
        $a = $comparison['after'];

        $metrics = [
            ['label' => 'Omzet / dag',       'before' => '&euro;' . number_format($b['revenue_day'], 0, ',', '.'), 'after' => '&euro;' . number_format($a['revenue_day'], 0, ',', '.'), 'before_raw' => $b['revenue_day'], 'after_raw' => $a['revenue_day']],
            ['label' => 'Orders / dag',       'before' => $b['orders_day'],  'after' => $a['orders_day'],  'before_raw' => $b['orders_day'],  'after_raw' => $a['orders_day']],
            ['label' => 'Gem. orderwaarde',   'before' => '&euro;' . number_format($b['aov'], 2, ',', '.'), 'after' => '&euro;' . number_format($a['aov'], 2, ',', '.'), 'before_raw' => $b['aov'], 'after_raw' => $a['aov']],
            ['label' => 'Items per order',    'before' => $b['items_per_order'], 'after' => $a['items_per_order'], 'before_raw' => $b['items_per_order'], 'after_raw' => $a['items_per_order']],
            ['label' => 'Upsell attach rate', 'before' => $b['upsell_rate'] . '%', 'after' => $a['upsell_rate'] . '%', 'before_raw' => $b['upsell_rate'], 'after_raw' => $a['upsell_rate']],
        ];

        echo '<div class="oz-panel oz-comparison">';
        echo '<table class="oz-comp-table">';
        echo '<thead><tr><th>Metric</th><th>Voor</th><th>Na</th><th>Verschil</th></tr></thead>';
        echo '<tbody>';

        foreach ($metrics as $m) {
            // Calculate percentage change
            $change_pct = '';
            $change_class = '';
            if ($m['before_raw'] > 0) {
                $pct = round((($m['after_raw'] - $m['before_raw']) / $m['before_raw']) * 100, 1);
                $change_class = $pct >= 0 ? 'positive' : 'negative';
                $arrow = $pct >= 0 ? '&#9650;' : '&#9660;';
                $change_pct = sprintf('<span class="oz-change %s">%s%s%% %s</span>', $change_class, $pct >= 0 ? '+' : '', $pct, $arrow);
            } elseif ($m['after_raw'] > 0) {
                $change_pct = '<span class="oz-change positive">nieuw</span>';
            } else {
                $change_pct = '<span class="oz-change">—</span>';
            }

            printf(
                '<tr><td class="oz-comp-label">%s</td><td class="oz-comp-val">%s</td><td class="oz-comp-val">%s</td><td class="oz-comp-change">%s</td></tr>',
                esc_html($m['label']),
                $m['before'],
                $m['after'],
                $change_pct
            );
        }

        echo '</tbody></table>';

        // Total revenue comparison
        printf(
            '<div class="oz-comp-totals">Totale omzet voor: <strong>&euro;%s</strong> &mdash; Totale omzet na: <strong>&euro;%s</strong> (%s dagen vergeleken)</div>',
            number_format($b['revenue'], 2, ',', '.'),
            number_format($a['revenue'], 2, ',', '.'),
            intval($comparison['days'])
        );

        echo '</div>';
    }

    /**
     * Render revenue bars for product lines.
     */
    private static function render_revenue_bars($lines) {
        if (empty($lines)) {
            echo '<div class="oz-empty">Nog geen orderdata in deze periode.</div>';
            return;
        }

        $max = 1;
        foreach ($lines as $row) {
            if ($row['revenue'] > $max) $max = $row['revenue'];
        }

        foreach ($lines as $row) {
            $pct = round(($row['revenue'] / $max) * 100);
            printf(
                '<div class="oz-bar-row">'
                . '<span class="oz-bar-label">%s</span>'
                . '<div class="oz-bar-track"><div class="oz-bar-fill" style="width:%d%%"></div></div>'
                . '<span class="oz-bar-count">&euro;%s</span>'
                . '</div>',
                esc_html($row['line']),
                $pct,
                number_format($row['revenue'], 0, ',', '.')
            );
        }
    }

    /**
     * Render top products list with revenue.
     */
    private static function render_product_list($products) {
        if (empty($products)) {
            echo '<div class="oz-empty">Nog geen orderdata in deze periode.</div>';
            return;
        }

        foreach ($products as $i => $row) {
            printf(
                '<div class="oz-top-item">'
                . '<span class="oz-top-rank">%d.</span>'
                . '<span class="oz-top-name">%s <span class="oz-top-qty">(%s&times;)</span></span>'
                . '<span class="oz-top-count">&euro;%s</span>'
                . '</div>',
                $i + 1,
                esc_html($row['name']),
                number_format($row['qty']),
                number_format($row['revenue'], 0, ',', '.')
            );
        }
    }

    /**
     * Render a horizontal bar chart from event counts.
     */
    private static function render_bar_chart($rows) {
        if (empty($rows)) {
            echo '<div class="oz-empty">Nog geen events in deze periode.</div>';
            return;
        }

        $max = 1;
        foreach ($rows as $row) {
            if (intval($row['count']) > $max) $max = intval($row['count']);
        }

        foreach ($rows as $row) {
            $count = intval($row['count']);
            $pct   = round(($count / $max) * 100);
            printf(
                '<div class="oz-bar-row">'
                . '<span class="oz-bar-label">%s</span>'
                . '<div class="oz-bar-track"><div class="oz-bar-fill" style="width:%d%%"></div></div>'
                . '<span class="oz-bar-count">%s</span>'
                . '</div>',
                esc_html($row['event_name']),
                $pct,
                number_format($count)
            );
        }
    }

    /**
     * Render traffic sources with colored medium badges.
     * Cleans up raw UTM values into readable labels.
     */
    private static function render_traffic_sources($rows) {
        if (empty($rows)) {
            echo '<div class="oz-empty">Nog geen sessie-data. Tracking begint na deploy.</div>';
            return;
        }

        /* Color map for medium categories */
        $medium_colors = [
            'organic'  => '#00a32a',  // Green — search engines
            'direct'   => '#2271b1',  // Blue — typed URL
            'social'   => '#e65100',  // Orange — social media
            'referral' => '#7b1fa2',  // Purple — other websites
            'email'    => '#c62828',  // Red — email clicks
            'cpc'      => '#d4a017',  // Gold — paid ads
            'paid'     => '#d4a017',  // Gold — paid ads (alt)
            'none'     => '#2271b1',  // Blue — direct (no medium)
            'unknown'  => '#646970',  // Grey
        ];

        /* Friendly source names */
        $source_labels = [
            'google'    => 'Google',
            'bing'      => 'Bing',
            'yahoo'     => 'Yahoo',
            'duckduckgo' => 'DuckDuckGo',
            'ecosia'    => 'Ecosia',
            'facebook'  => 'Facebook',
            'instagram' => 'Instagram',
            'pinterest' => 'Pinterest',
            'youtube'   => 'YouTube',
            'tiktok'    => 'TikTok',
            'linkedin'  => 'LinkedIn',
            'twitter'   => 'Twitter / X',
            'direct'    => 'Direct',
        ];

        /* Classify raw medium into a clean category + label */
        $medium_categories = [
            'organic'              => ['cat' => 'organic',  'label' => 'Organisch'],
            'cpc'                  => ['cat' => 'cpc',      'label' => 'Betaald'],
            'paid'                 => ['cat' => 'cpc',      'label' => 'Betaald'],
            'social'               => ['cat' => 'social',   'label' => 'Social'],
            'referral'             => ['cat' => 'referral', 'label' => 'Verwijzing'],
            'email'                => ['cat' => 'email',    'label' => 'E-mail'],
            'direct'               => ['cat' => 'direct',   'label' => 'Direct'],
            'none'                 => ['cat' => 'direct',   'label' => 'Direct'],
            'unknown'              => ['cat' => 'unknown',  'label' => 'Onbekend'],
        ];

        $max = 1;
        foreach ($rows as $row) {
            if (intval($row['count']) > $max) $max = intval($row['count']);
        }

        foreach ($rows as $row) {
            $count  = intval($row['count']);
            $pct    = round(($count / $max) * 100);
            $raw_source = strtolower(trim($row['source']));
            $raw_medium = strtolower(trim($row['medium']));

            /* Clean source name — special case: Meta ads use source=Facebook
               but medium may specify the actual platform (Instagram_Stories_cpc) */
            if ($raw_source === 'facebook' && strpos($raw_medium, 'instagram') !== false) {
                $source_label = 'Instagram';
            } elseif (isset($source_labels[$raw_source])) {
                $source_label = $source_labels[$raw_source];
            } else {
                $source_label = ucfirst($raw_source);
            }

            /* Classify medium — check if raw medium contains known keywords */
            $medium_info = null;
            if (isset($medium_categories[$raw_medium])) {
                $medium_info = $medium_categories[$raw_medium];
            } elseif (strpos($raw_medium, 'cpc') !== false || strpos($raw_medium, 'paid') !== false || strpos($raw_medium, 'ppc') !== false) {
                $medium_info = ['cat' => 'cpc', 'label' => 'Betaald'];
            } elseif (strpos($raw_medium, 'social') !== false || strpos($raw_medium, 'stories') !== false || strpos($raw_medium, 'feed') !== false || strpos($raw_medium, 'reel') !== false) {
                $medium_info = ['cat' => 'social', 'label' => 'Social'];
            } elseif (strpos($raw_medium, 'email') !== false || strpos($raw_medium, 'newsletter') !== false) {
                $medium_info = ['cat' => 'email', 'label' => 'E-mail'];
            } else {
                $medium_info = ['cat' => 'unknown', 'label' => ucfirst(str_replace('_', ' ', $raw_medium))];
            }

            $color = isset($medium_colors[$medium_info['cat']]) ? $medium_colors[$medium_info['cat']] : $medium_colors['unknown'];

            printf(
                '<div class="oz-bar-row">'
                . '<span class="oz-bar-label">%s <span class="oz-traffic-badge" style="background:%s">%s</span></span>'
                . '<div class="oz-bar-track"><div class="oz-bar-fill" style="width:%d%%;background:%s"></div></div>'
                . '<span class="oz-bar-count">%s</span>'
                . '</div>',
                esc_html($source_label),
                esc_attr($color),
                esc_html($medium_info['label']),
                $pct,
                esc_attr($color),
                number_format($count)
            );
        }
    }

    /**
     * Render conversion funnel bars.
     */
    private static function render_funnel($funnel) {
        $steps = [
            ['label' => 'Kleur gekozen',  'count' => $funnel['color_selected']],
            ['label' => 'In winkelmand',   'count' => $funnel['add_to_cart']],
            ['label' => 'Naar afrekenen',  'count' => $funnel['checkout']],
        ];

        $max = max($funnel['color_selected'], 1);

        foreach ($steps as $step) {
            $pct = round(($step['count'] / $max) * 100);
            printf(
                '<div class="oz-bar-row">'
                . '<span class="oz-bar-label">%s</span>'
                . '<div class="oz-bar-track"><div class="oz-bar-fill" style="width:%d%%"></div></div>'
                . '<span class="oz-bar-count">%s</span>'
                . '</div>',
                esc_html($step['label']),
                $pct,
                number_format($step['count'])
            );
        }
    }

    /**
     * Render a ranked top-N list.
     * If values look like URL paths, cleans them up for readability.
     */
    private static function render_top_list($rows) {
        if (empty($rows)) {
            echo '<div class="oz-empty">Nog geen data in deze periode.</div>';
            return;
        }

        foreach ($rows as $i => $row) {
            $display = $row['value'];

            /* If it looks like a URL path, clean it up */
            if (strpos($display, '/') === 0) {
                $display = self::format_landing_page($display);
            }

            printf(
                '<div class="oz-top-item">'
                . '<span class="oz-top-rank">%d.</span>'
                . '<span class="oz-top-name">%s</span>'
                . '<span class="oz-top-count">%s</span>'
                . '</div>',
                $i + 1,
                esc_html($display),
                number_format(intval($row['count']))
            );
        }
    }

    /**
     * Turn a raw URL path into a readable page label.
     * /producten/beton-cire-original-bestellen/ → "Beton Cire Original Bestellen"
     * / → "Homepage"
     */
    private static function format_landing_page($path) {
        $path = trim($path, '/');

        if ($path === '') return 'Homepage';

        /* Strip common prefixes to get the meaningful part */
        $prefixes = ['producten/', 'product/', 'bestel/', 'product-categorie/'];
        $prefix_label = '';
        foreach ($prefixes as $p) {
            if (strpos($path, $p) === 0) {
                $path = substr($path, strlen($p));
                /* Show a small prefix hint */
                $prefix_labels = [
                    'producten/' => '',
                    'product/'   => '',
                    'bestel/'    => '',
                    'product-categorie/' => 'Cat: ',
                ];
                $prefix_label = $prefix_labels[$p];
                break;
            }
        }

        /* Remove trailing slug numbers/IDs and file extensions */
        $path = trim($path, '/');

        /* Convert slug to readable: dashes → spaces, capitalize */
        $readable = str_replace('-', ' ', $path);
        $readable = ucwords($readable);

        /* Truncate if too long */
        if (mb_strlen($readable) > 50) {
            $readable = mb_substr($readable, 0, 47) . '...';
        }

        return $prefix_label . $readable;
    }

    /**
     * Output all dashboard CSS.
     */
    private static function render_styles() {
        ?>
        <style>
            /* ── Dashboard layout ── */
            .oz-analytics { max-width: 1100px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .oz-analytics h1 { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }

            /* ── Live counter ── */
            .oz-live-counter {
                display: inline-flex; align-items: center; gap: 6px;
                font-size: 13px; font-weight: 500; color: #646970;
                background: #f6f7f7; border: 1px solid #dcdcde;
                padding: 4px 12px; border-radius: 16px;
            }
            .oz-live-dot {
                width: 8px; height: 8px; border-radius: 50%;
                background: #dcdcde; display: inline-block;
                transition: background 0.3s;
            }
            .oz-live-dot.active {
                background: #00a32a;
                animation: oz-pulse 1.5s ease infinite;
            }
            @keyframes oz-pulse {
                0%, 100% { box-shadow: 0 0 0 0 rgba(0, 163, 42, 0.4); }
                50% { box-shadow: 0 0 0 4px rgba(0, 163, 42, 0); }
            }

            /* ── Live panel ── */
            .oz-live-panel {
                background: #fff; border: 1px solid #dcdcde; border-radius: 8px;
                padding: 16px; margin-bottom: 24px;
            }
            .oz-live-sections { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
            .oz-live-sections h3 { margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #1d2327; }
            .oz-live-list { max-height: 300px; overflow-y: auto; font-size: 12px; }
            .oz-live-session {
                display: flex; align-items: center; gap: 8px;
                padding: 6px 8px; border-bottom: 1px solid #f0f0f1;
            }
            .oz-live-session:last-child { border-bottom: none; }
            .oz-live-session { cursor: pointer; border-radius: 4px; transition: background 0.15s; }
            .oz-live-session:hover { background: #f6f7f7; }
            .oz-live-session.selected { background: #e8f0fe; }
            .oz-live-session-dot { width: 6px; height: 6px; border-radius: 50%; background: #00a32a; flex-shrink: 0; }
            .oz-feed-clear {
                font-size: 11px; font-weight: 400; color: #2271b1; cursor: pointer;
                margin-left: 8px;
            }
            .oz-feed-clear:hover { text-decoration: underline; }
            .oz-live-session-url { color: #2271b1; word-break: break-all; }
            .oz-live-session-time { margin-left: auto; color: #999; white-space: nowrap; font-size: 11px; }
            .oz-live-session-id { color: #999; font-family: monospace; font-size: 10px; }
            .oz-live-event {
                display: flex; align-items: baseline; gap: 8px;
                padding: 5px 8px; border-bottom: 1px solid #f0f0f1;
                animation: oz-fade-in 0.3s ease;
            }
            .oz-live-event:last-child { border-bottom: none; }
            .oz-live-event-time { color: #999; font-size: 11px; white-space: nowrap; }
            .oz-live-event-name {
                font-weight: 600; color: #1d2327;
                background: #f0f0f1; padding: 1px 6px; border-radius: 3px; font-size: 11px;
            }
            .oz-live-event-name.product { background: #e8f0fe; color: #174ea6; }
            .oz-live-event-name.cart { background: #fce8e6; color: #c5221f; }
            .oz-live-event-detail { color: #646970; font-size: 11px; }
            @keyframes oz-fade-in { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }

            /* ── Traffic source badges ── */
            .oz-traffic-badge {
                display: inline-block; font-size: 9px; font-weight: 600; color: #fff;
                padding: 1px 5px; border-radius: 3px; vertical-align: middle;
                margin-left: 4px; text-transform: uppercase; letter-spacing: 0.3px;
            }

            /* ── Section titles ── */
            .oz-section-title {
                font-size: 16px; font-weight: 700; color: #1d2327;
                margin: 32px 0 16px; padding-bottom: 8px;
                border-bottom: 2px solid #2271b1; display: flex; align-items: baseline; gap: 12px;
            }
            .oz-section-title:first-of-type { margin-top: 8px; }
            .oz-section-subtitle { font-size: 12px; font-weight: 400; color: #646970; }

            /* ── Range tabs ── */
            .oz-range-tabs { display: flex; gap: 4px; margin-left: auto; }
            .oz-range-tabs a {
                padding: 6px 14px; border-radius: 4px; text-decoration: none;
                font-size: 13px; font-weight: 500; color: #50575e; background: #f0f0f1;
                transition: background 0.15s;
            }
            .oz-range-tabs a:hover { background: #e0e0e1; }
            .oz-range-tabs a.active { background: #2271b1; color: #fff; }

            /* ── Summary cards ── */
            .oz-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
            .oz-card {
                background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;
                padding: 16px 20px; text-align: center;
            }
            .oz-card-value { font-size: 28px; font-weight: 700; color: #1d2327; line-height: 1.2; }
            .oz-card-label { font-size: 13px; color: #646970; margin-top: 4px; }

            /* ── Two-column grid ── */
            .oz-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
            .oz-panel {
                background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px;
            }
            .oz-panel h3 { margin: 0 0 12px; font-size: 14px; color: #1d2327; }

            /* ── Bar charts (pure CSS) ── */
            .oz-bar-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 13px; }
            .oz-bar-label { min-width: 180px; color: #50575e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .oz-bar-track { flex: 1; height: 18px; background: #f0f0f1; border-radius: 3px; overflow: hidden; }
            .oz-bar-fill { height: 100%; background: #2271b1; border-radius: 3px; min-width: 2px; transition: width 0.3s; }
            .oz-bar-count { min-width: 50px; text-align: right; font-weight: 600; color: #1d2327; font-variant-numeric: tabular-nums; }

            /* ── Funnel ── */
            .oz-funnel { margin-bottom: 24px; }
            .oz-funnel .oz-bar-fill { background: #135e96; }
            .oz-funnel .oz-bar-row:nth-child(2) .oz-bar-fill { background: #2271b1; }
            .oz-funnel .oz-bar-row:nth-child(3) .oz-bar-fill { background: #72aee6; }

            /* ── Top lists ── */
            .oz-top-item { display: flex; align-items: center; gap: 8px; padding: 5px 0; font-size: 13px; border-bottom: 1px solid #f0f0f1; }
            .oz-top-item:last-child { border-bottom: none; }
            .oz-top-rank { width: 20px; font-weight: 700; color: #2271b1; text-align: center; }
            .oz-top-name { flex: 1; color: #1d2327; }
            .oz-top-qty { color: #646970; font-weight: 400; }
            .oz-top-count { font-weight: 600; color: #50575e; font-variant-numeric: tabular-nums; }

            /* ── Comparison table ── */
            .oz-comparison { margin-bottom: 24px; padding: 20px; }
            .oz-comp-table { width: 100%; border-collapse: collapse; font-size: 14px; }
            .oz-comp-table th {
                text-align: left; padding: 8px 12px; font-weight: 600; color: #50575e;
                border-bottom: 2px solid #c3c4c7; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;
            }
            .oz-comp-table td { padding: 10px 12px; border-bottom: 1px solid #f0f0f1; }
            .oz-comp-label { font-weight: 500; color: #1d2327; }
            .oz-comp-val { color: #50575e; font-variant-numeric: tabular-nums; }
            .oz-comp-change { text-align: right; }

            .oz-change { font-weight: 700; font-size: 13px; padding: 2px 8px; border-radius: 3px; }
            .oz-change.positive { color: #00a32a; background: #edfaef; }
            .oz-change.negative { color: #d63638; background: #fcf0f1; }

            .oz-comp-totals {
                margin-top: 16px; padding-top: 12px; border-top: 1px solid #c3c4c7;
                font-size: 13px; color: #50575e;
            }

            /* ── Warning notice ── */
            .oz-notice { padding: 10px 14px; border-radius: 4px; font-size: 13px; margin-bottom: 16px; border-left: 4px solid; }
            .oz-notice-warning { background: #fcf9e8; border-color: #dba617; color: #6e4e00; }

            /* ── Empty state ── */
            .oz-empty { color: #646970; font-style: italic; font-size: 13px; padding: 12px 0; }

            /* ── Responsive ── */
            @media (max-width: 960px) {
                .oz-cards { grid-template-columns: repeat(2, 1fr); }
                .oz-columns { grid-template-columns: 1fr; }
            }
        </style>
        <?php
    }
}
