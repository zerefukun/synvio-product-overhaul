<?php
/**
 * Admin Settings for BCW
 *
 * Adds a WooCommerce settings page under WooCommerce > BCW Opties with:
 * - Reprocess all products button (rebuilds color extraction + variant links)
 * - Plugin status overview (product counts per line)
 *
 * @package OZ_Variations_BCW
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_BCW_Admin {

    /**
     * Initialize admin hooks.
     */
    public static function init() {
        // Add admin menu page under WooCommerce
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);

        // Enqueue admin scripts on our settings page only
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);

        // Product editor metabox for USPs + Specs overrides
        add_action('add_meta_boxes', [__CLASS__, 'add_product_metabox']);
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_product_metabox']);
    }

    /**
     * Add submenu page under WooCommerce.
     */
    public static function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            'BCW Opties',
            'BCW Opties',
            'manage_woocommerce',
            'oz-bcw-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin JS only on our settings page.
     */
    public static function enqueue_admin_scripts($hook) {
        if ($hook !== 'woocommerce_page_oz-bcw-settings') {
            return;
        }

        wp_enqueue_script(
            'oz-bcw-admin',
            OZ_BCW_PLUGIN_URL . 'assets/js/oz-bcw-admin.js',
            [],
            OZ_BCW_VERSION,
            true
        );

        wp_localize_script('oz-bcw-admin', 'ozBcwAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('oz_bcw_reprocess'),
        ]);
    }

    /**
     * Render the settings page.
     */
    public static function render_settings_page() {
        // Get product counts per line for status overview
        $line_counts = self::get_line_counts();
        ?>
        <div class="wrap">
            <h1>OZ Variations BCW</h1>
            <p>Plugin versie: <?php echo esc_html(OZ_BCW_VERSION); ?></p>

            <hr>

            <!-- Reprocess Section -->
            <h2>Producten herverwerken</h2>
            <p>
                Herverwerk alle producten om kleuren te extraheren en variantlinks bij te werken.
                Dit is nodig na het importeren van nieuwe producten of het wijzigen van categorieën.
            </p>
            <button id="oz-bcw-reprocess" class="button button-primary">
                Alle producten herverwerken
            </button>
            <div id="oz-bcw-reprocess-result" style="margin-top:10px;"></div>

            <hr>

            <!-- Status Overview -->
            <h2>Productlijnen overzicht</h2>
            <table class="widefat fixed striped" style="max-width:600px;">
                <thead>
                    <tr>
                        <th>Productlijn</th>
                        <th>Aantal producten</th>
                        <th>Met kleurvarianten</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($line_counts as $line_key => $counts) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($line_key); ?></strong></td>
                            <td><?php echo intval($counts['total']); ?></td>
                            <td><?php echo intval($counts['with_variants']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($line_counts)) : ?>
                <p><em>Nog geen producten gevonden. Klik op "Alle producten herverwerken" om te starten.</em></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Register the BCW product metabox on the WC product editor.
     * Shows page mode selector, USPs, and Specs fields.
     */
    public static function add_product_metabox() {
        add_meta_box(
            'oz-bcw-product-meta',
            'OZ Productpagina',
            [__CLASS__, 'render_product_metabox'],
            'product',
            'normal',
            'default'
        );
    }

    /**
     * Render the metabox content.
     * Shows current product line defaults + editable override fields.
     *
     * @param WP_Post $post
     */
    public static function render_product_metabox($post) {
        wp_nonce_field('oz_bcw_product_meta', 'oz_bcw_meta_nonce');

        $product_id = $post->ID;

        // Get product line config defaults
        // Detect product line — use category IDs for matching
        $cat_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        $line_key = OZ_Product_Line_Config::detect_from_data($product_id, $cat_ids ?: []);
        $config = $line_key ? OZ_Product_Line_Config::get_config($line_key) : null;
        $default_usps = ($config && isset($config['usps'])) ? $config['usps'] : [];
        $default_specs = ($config && isset($config['specs'])) ? $config['specs'] : [];

        // Get per-product overrides (if any)
        $override_usps = get_post_meta($product_id, '_oz_usps', true);
        $override_specs = get_post_meta($product_id, '_oz_specs', true);

        // Current values: override wins, fallback to defaults
        $usps = !empty($override_usps) ? $override_usps : $default_usps;
        $specs = !empty($override_specs) ? $override_specs : $default_specs;

        ?>
        <style>
            .oz-meta-section { margin-bottom: 20px; }
            .oz-meta-section h4 { margin: 0 0 8px; }
            .oz-meta-section .description { color: #666; font-style: italic; margin-bottom: 8px; }
            .oz-meta-table { width: 100%; }
            .oz-meta-table input { width: 100%; }
            .oz-meta-table td { padding: 4px 8px 4px 0; vertical-align: top; }
            .oz-meta-table .oz-spec-key { width: 150px; }
            .oz-meta-default { color: #999; font-size: 12px; }
        </style>

        <?php
        // Page mode: auto-detected lines show as "configured_line", others can be assigned
        $current_mode = get_post_meta($product_id, '_oz_page_mode', true);
        $effective_mode = $line_key ? 'configured_line' : ($current_mode ?: '');
        ?>

        <!-- Page mode selector — determines which template this product uses -->
        <div class="oz-meta-section">
            <h4>Paginamodus</h4>
            <?php if ($line_key) : ?>
                <p>
                    <strong style="color:#135350;">&#10003; Productlijn: <?php echo esc_html($line_key); ?></strong>
                    — Automatisch herkend. Template met volledige opties actief.
                </p>
                <input type="hidden" name="oz_page_mode" value="">
            <?php else : ?>
                <p class="description">
                    Dit product is niet automatisch herkend als BCW productlijn.
                    Kies een modus om onze template te activeren.
                </p>
                <select name="oz_page_mode" style="width:300px;">
                    <option value="" <?php selected($current_mode, ''); ?>>
                        Niet actief (standaard thema)
                    </option>
                    <option value="generic_simple" <?php selected($current_mode, 'generic_simple'); ?>>
                        Generiek — eenvoudig (geen product-opties)
                    </option>
                    <option value="generic_addons" <?php selected($current_mode, 'generic_addons'); ?>>
                        Generiek — met add-ons (toekomstig)
                    </option>
                </select>
            <?php endif; ?>
        </div>

        <hr style="margin: 16px 0;">

        <p>
            <?php if ($line_key) : ?>
                Standaard waarden worden uit de productlijn geladen. Vul hieronder in om per product te overschrijven.
            <?php else : ?>
                Vul hieronder USPs en specificaties in voor dit product. Als deze leeg zijn worden de WooCommerce beschrijving en korte beschrijving gebruikt.
            <?php endif; ?>
        </p>

        <!-- USPs -->
        <div class="oz-meta-section">
            <h4>USPs (3 verkooppunten)</h4>
            <p class="description">Laat leeg om de standaard productlijn USPs te gebruiken. Vul alle 3 in om te overschrijven.</p>

            <?php for ($i = 0; $i < 3; $i++) : ?>
                <p>
                    <input type="text"
                           name="oz_usps[<?php echo $i; ?>]"
                           value="<?php echo esc_attr(isset($override_usps[$i]) ? $override_usps[$i] : ''); ?>"
                           placeholder="<?php echo esc_attr(isset($default_usps[$i]) ? $default_usps[$i] : 'USP ' . ($i + 1)); ?>"
                           style="width:100%;">
                </p>
            <?php endfor; ?>
        </div>

        <!-- Specs -->
        <div class="oz-meta-section">
            <h4>Specificaties (tabel)</h4>
            <p class="description">Laat leeg om de standaard productlijn specs te gebruiken. Key + waarde paren, max 10 rijen.</p>

            <table class="oz-meta-table" id="oz-specs-table">
                <thead>
                    <tr>
                        <th class="oz-spec-key">Kenmerk</th>
                        <th>Waarde</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Show existing overrides or empty rows
                    $rows = !empty($override_specs) ? $override_specs : [];
                    // Always show at least as many rows as defaults, + 2 empty
                    $min_rows = max(count($default_specs) + 2, count($rows) + 2);
                    $min_rows = min($min_rows, 10);

                    $spec_keys = array_keys($rows);
                    $default_keys = array_keys($default_specs);

                    for ($i = 0; $i < $min_rows; $i++) :
                        $key = isset($spec_keys[$i]) ? $spec_keys[$i] : '';
                        $val = $key ? ($rows[$key] ?? '') : '';
                        $placeholder_key = isset($default_keys[$i]) ? $default_keys[$i] : '';
                        $placeholder_val = $placeholder_key ? ($default_specs[$placeholder_key] ?? '') : '';
                    ?>
                    <tr>
                        <td class="oz-spec-key">
                            <input type="text"
                                   name="oz_spec_keys[]"
                                   value="<?php echo esc_attr($key); ?>"
                                   placeholder="<?php echo esc_attr($placeholder_key); ?>">
                        </td>
                        <td>
                            <input type="text"
                                   name="oz_spec_vals[]"
                                   value="<?php echo esc_attr($val); ?>"
                                   placeholder="<?php echo esc_attr($placeholder_val); ?>">
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <hr style="margin: 16px 0;">

        <!-- FAQ (Veelgestelde vragen) — repeating Q&A pairs -->
        <?php
        $oz_faq = get_post_meta($product_id, '_oz_faq', true);
        if (!is_array($oz_faq)) $oz_faq = [];
        ?>
        <div class="oz-meta-section">
            <h4>Veelgestelde vragen (FAQ)</h4>
            <p class="description">Voeg vraag/antwoord paren toe. Verschijnt als accordion onder Specificaties.</p>

            <div id="oz-faq-rows">
                <?php
                // Show existing FAQs + 1 empty row
                $faq_rows = !empty($oz_faq) ? $oz_faq : [];
                $faq_rows[] = ['q' => '', 'a' => '']; // always one empty row at the end
                foreach ($faq_rows as $fi => $faq_item) :
                ?>
                <div class="oz-faq-row" style="border:1px solid #ddd; padding:10px; margin-bottom:8px; border-radius:4px; background:#fafafa;">
                    <p style="margin:0 0 6px;">
                        <input type="text"
                               name="oz_faq_questions[]"
                               value="<?php echo esc_attr($faq_item['q']); ?>"
                               placeholder="Vraag"
                               style="width:100%;">
                    </p>
                    <p style="margin:0 0 6px;">
                        <textarea name="oz_faq_answers[]"
                                  placeholder="Antwoord"
                                  rows="2"
                                  style="width:100%;"><?php echo esc_textarea($faq_item['a']); ?></textarea>
                    </p>
                    <button type="button" class="button oz-faq-remove" style="color:#a00;">Verwijderen</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" id="oz-faq-add">+ Vraag toevoegen</button>
        </div>

        <!-- Inline JS for FAQ add/remove (lightweight, no external file needed) -->
        <script>
        (function(){
            document.getElementById('oz-faq-add').addEventListener('click', function() {
                var row = document.createElement('div');
                row.className = 'oz-faq-row';
                row.style.cssText = 'border:1px solid #ddd; padding:10px; margin-bottom:8px; border-radius:4px; background:#fafafa;';
                row.innerHTML = '<p style="margin:0 0 6px;"><input type="text" name="oz_faq_questions[]" value="" placeholder="Vraag" style="width:100%;"></p>'
                    + '<p style="margin:0 0 6px;"><textarea name="oz_faq_answers[]" placeholder="Antwoord" rows="2" style="width:100%;"></textarea></p>'
                    + '<button type="button" class="button oz-faq-remove" style="color:#a00;">Verwijderen</button>';
                document.getElementById('oz-faq-rows').appendChild(row);
            });
            document.getElementById('oz-faq-rows').addEventListener('click', function(e) {
                if (e.target.classList.contains('oz-faq-remove')) {
                    e.target.closest('.oz-faq-row').remove();
                }
            });
        })();
        </script>
        <?php
    }

    /**
     * Save the metabox data when product is saved.
     *
     * @param int $product_id
     */
    public static function save_product_metabox($product_id) {
        if (!isset($_POST['oz_bcw_meta_nonce']) ||
            !wp_verify_nonce($_POST['oz_bcw_meta_nonce'], 'oz_bcw_product_meta')) {
            return;
        }

        // Save page mode — only for non-line products
        if (isset($_POST['oz_page_mode'])) {
            $mode = sanitize_text_field($_POST['oz_page_mode']);
            if (in_array($mode, ['generic_simple', 'generic_addons'], true)) {
                update_post_meta($product_id, '_oz_page_mode', $mode);
            } else {
                delete_post_meta($product_id, '_oz_page_mode');
            }
        }

        // Save USPs — only if at least one is filled
        $usps = [];
        if (isset($_POST['oz_usps']) && is_array($_POST['oz_usps'])) {
            foreach ($_POST['oz_usps'] as $usp) {
                $usps[] = sanitize_text_field($usp);
            }
        }
        // Only save if at least one non-empty USP
        $has_usps = array_filter($usps, function($v) { return $v !== ''; });
        if (!empty($has_usps)) {
            update_post_meta($product_id, '_oz_usps', $usps);
        } else {
            delete_post_meta($product_id, '_oz_usps');
        }

        // Save Specs — key/value pairs, only if at least one pair is filled
        $specs = [];
        $keys = isset($_POST['oz_spec_keys']) ? $_POST['oz_spec_keys'] : [];
        $vals = isset($_POST['oz_spec_vals']) ? $_POST['oz_spec_vals'] : [];
        for ($i = 0; $i < count($keys); $i++) {
            $k = sanitize_text_field($keys[$i]);
            $v = sanitize_text_field($vals[$i]);
            if ($k !== '' && $v !== '') {
                $specs[$k] = $v;
            }
        }
        if (!empty($specs)) {
            update_post_meta($product_id, '_oz_specs', $specs);
        } else {
            delete_post_meta($product_id, '_oz_specs');
        }

        // Save FAQs — question/answer pairs
        $faqs = [];
        if (!empty($_POST['oz_faq_questions'])) {
            foreach ($_POST['oz_faq_questions'] as $i => $q) {
                $q = sanitize_text_field($q);
                $a = sanitize_textarea_field($_POST['oz_faq_answers'][$i] ?? '');
                if ($q && $a) {
                    $faqs[] = ['q' => $q, 'a' => $a];
                }
            }
        }
        if (!empty($faqs)) {
            update_post_meta($product_id, '_oz_faq', $faqs);
        } else {
            delete_post_meta($product_id, '_oz_faq');
        }
    }


    /**
     * Get product counts per product line.
     *
     * @return array  [line_key => ['total' => int, 'with_variants' => int]]
     */
    private static function get_line_counts() {
        $counts = [];

        foreach (OZ_Product_Line_Config::get_all_lines() as $line_key) {
            $config = OZ_Product_Line_Config::get_config($line_key);
            if (!$config) {
                continue;
            }

            $total = 0;
            $with_variants = 0;

            // Category-based lines
            if (!empty($config['cats'])) {
                $args = [
                    'post_type'      => 'product',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'tax_query'      => [[
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => $config['cats'],
                    ]],
                    'no_found_rows'  => true,
                ];

                $query = new WP_Query($args);
                $total = count($query->posts);

                foreach ($query->posts as $pid) {
                    $variants = get_post_meta($pid, '_oz_variants', true);
                    if (!empty($variants) && is_array($variants)) {
                        $with_variants++;
                    }
                }
            }

            // Extra products detected by ID (loose emmers, single-product lines)
            if (!empty($config['product_ids'])) {
                foreach ($config['product_ids'] as $pid) {
                    $product = wc_get_product($pid);
                    if ($product && $product->get_status() === 'publish') {
                        $total++;
                        $variants = get_post_meta($pid, '_oz_variants', true);
                        if (!empty($variants) && is_array($variants)) {
                            $with_variants++;
                        }
                    }
                }
            }

            $counts[$line_key] = [
                'total'         => $total,
                'with_variants' => $with_variants,
            ];
        }

        return $counts;
    }
}
