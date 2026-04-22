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

        // One-time FAQ seeder — seeds default line FAQs into product meta
        add_action('admin_init', [__CLASS__, 'seed_default_faqs']);
    }

    /**
     * One-time seeder: writes default FAQs from product line config into _oz_faq
     * post meta for every configured product that doesn't already have custom FAQs.
     * Sets an option flag so it only runs once.
     */
    public static function seed_default_faqs() {
        // Already seeded? Skip.
        if (get_option('oz_faq_seeded')) return;

        $line_keys = OZ_Product_Line_Config::get_all_lines();
        $count = 0;

        foreach ($line_keys as $line_key) {
            $config = OZ_Product_Line_Config::get_config($line_key);
            if (!$config) continue;

            // Skip lines without default FAQs
            if (empty($config['faq'])) continue;

            // Get category IDs for this line
            $cat_ids = isset($config['cats']) ? $config['cats'] : [];
            if (empty($cat_ids)) continue;

            // Find all products in this line's categories
            $product_ids = get_posts([
                'post_type'      => 'product',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'tax_query'      => [[
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $cat_ids,
                ]],
            ]);

            foreach ($product_ids as $pid) {
                // Only seed if product has no custom FAQ yet
                $existing = get_post_meta($pid, '_oz_faq', true);
                if (!empty($existing) && is_array($existing)) continue;

                update_post_meta($pid, '_oz_faq', $config['faq']);
                $count++;
            }
        }

        // Mark as done so this never runs again
        update_option('oz_faq_seeded', true);

        if ($count > 0) {
            error_log("[OZ-BCW] Seeded default FAQs for {$count} products.");
        }
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

        // For configured lines: USPs/specs/FAQ are shared via the base product.
        // Variants can override each section independently.
        $base_id = (!empty($config['base_id'])) ? $config['base_id'] : $product_id;
        $is_variant = ($base_id !== $product_id);

        // Legacy shared override still exists on older products; treat it as
        // "all sections overridden" until the product is saved with new flags.
        $legacy_override = $is_variant && get_post_meta($product_id, '_oz_override_shared', true) === 'yes';

        // Per-section override flags — each section can independently use variant data.
        $ovr_usps  = $is_variant && (
            get_post_meta($product_id, '_oz_override_usps', true) === 'yes' || $legacy_override
        );
        $ovr_specs = $is_variant && (
            get_post_meta($product_id, '_oz_override_specs', true) === 'yes' || $legacy_override
        );
        $ovr_faq   = $is_variant && (
            get_post_meta($product_id, '_oz_override_faq', true) === 'yes' || $legacy_override
        );

        // For variants: prefer variant's own data if it exists (kept even when
        // override is off), so re-checking the box shows the saved variant values.
        // Fall back to base product data, then line config defaults.
        if ($is_variant) {
            $variant_usps  = get_post_meta($product_id, '_oz_usps', true);
            $variant_specs = get_post_meta($product_id, '_oz_specs', true);
            $base_usps     = get_post_meta($base_id, '_oz_usps', true);
            $base_specs    = get_post_meta($base_id, '_oz_specs', true);

            $override_usps  = !empty($variant_usps) ? $variant_usps : $base_usps;
            $override_specs = !empty($variant_specs) ? $variant_specs : $base_specs;
        } else {
            $override_usps = get_post_meta($product_id, '_oz_usps', true);
            $override_specs = get_post_meta($product_id, '_oz_specs', true);
        }

        // Current values: override wins, fallback to line config defaults
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

        <?php if ($line_key && $is_variant && $base_id) : ?>
        <!-- Quick link to base product editor — saves time navigating -->
        <div style="margin: 12px 0; padding: 10px 14px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 3px;">
            <strong>Hoofdproduct:</strong>
            <?php echo esc_html(get_the_title($base_id)); ?>
            <a href="<?php echo esc_url(get_edit_post_link($base_id)); ?>"
               class="button button-small"
               style="margin-left: 8px; vertical-align: middle;">
                ✎ Bewerk hoofdproduct
            </a>
        </div>
        <?php endif; ?>

        <hr style="margin: 16px 0;">

        <p>
            <?php if ($line_key && $is_variant) : ?>
                Standaard worden USPs, specificaties en FAQ overgenomen van het hoofdproduct.
                Vink een sectie aan om alleen voor dit product af te wijken.
            <?php elseif ($line_key) : ?>
                Dit is het hoofdproduct. USPs, specificaties en FAQ worden hier beheerd voor alle kleuren.
            <?php else : ?>
                Vul hieronder USPs en specificaties in voor dit product. Als deze leeg zijn worden de WooCommerce beschrijving en korte beschrijving gebruikt.
            <?php endif; ?>
        </p>

        <!-- USPs -->
        <div class="oz-meta-section">
            <h4>USPs (3 verkooppunten)</h4>
            <?php if ($is_variant) : ?>
            <label style="display:block; margin-bottom:6px;">
                <input type="checkbox" name="oz_override_usps" value="yes" <?php checked($ovr_usps); ?>>
                <strong>Afwijkend voor dit product</strong>
            </label>
            <p class="description">Uitgevinkt = je bewerkt het hoofdproduct. Aangevinkt = alleen deze kleur.</p>
            <?php else : ?>
            <p class="description">Deze waarden gelden voor alle kleuren binnen deze productlijn.</p>
            <?php endif; ?>

            <?php for ($i = 0; $i < 3; $i++) : ?>
                <p>
                    <input type="text"
                           name="oz_usps[<?php echo $i; ?>]"
                           value="<?php echo esc_attr(isset($usps[$i]) ? $usps[$i] : ''); ?>"
                           placeholder="USP <?php echo $i + 1; ?>"
                           style="width:100%;">
                </p>
            <?php endfor; ?>
        </div>

        <!-- Specs -->
        <div class="oz-meta-section">
            <h4>Specificaties (tabel)</h4>
            <?php if ($is_variant) : ?>
            <label style="display:block; margin-bottom:6px;">
                <input type="checkbox" name="oz_override_specs" value="yes" <?php checked($ovr_specs); ?>>
                <strong>Afwijkend voor dit product</strong>
            </label>
            <p class="description">Uitgevinkt = je bewerkt het hoofdproduct. Aangevinkt = alleen deze kleur.</p>
            <?php else : ?>
            <p class="description">Deze waarden gelden voor alle kleuren binnen deze productlijn.</p>
            <?php endif; ?>

            <table class="oz-meta-table" id="oz-specs-table">
                <thead>
                    <tr>
                        <th class="oz-spec-key">Kenmerk</th>
                        <th>Waarde</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Pre-fill with effective values (override if exists, else defaults)
                    $rows = !empty($override_specs) ? $override_specs : $default_specs;
                    // Add 2 empty rows for new entries
                    $spec_keys = array_keys($rows);
                    $total_rows = min(count($spec_keys) + 2, 10);

                    for ($i = 0; $i < $total_rows; $i++) :
                        $key = isset($spec_keys[$i]) ? $spec_keys[$i] : '';
                        $val = $key ? ($rows[$key] ?? '') : '';
                    ?>
                    <tr>
                        <td class="oz-spec-key">
                            <input type="text"
                                   name="oz_spec_keys[]"
                                   value="<?php echo esc_attr($key); ?>"
                                   placeholder="Kenmerk">
                        </td>
                        <td>
                            <input type="text"
                                   name="oz_spec_vals[]"
                                   value="<?php echo esc_attr($val); ?>"
                                   placeholder="Waarde">
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <hr style="margin: 16px 0;">

        <!-- FAQ (Veelgestelde vragen) — repeating Q&A pairs -->
        <?php
        // Read FAQ — prefer variant's own data if it exists, else base product
        if ($is_variant) {
            $variant_faq = get_post_meta($product_id, '_oz_faq', true);
            $base_faq    = get_post_meta($base_id, '_oz_faq', true);
            $oz_faq = (!empty($variant_faq) && is_array($variant_faq)) ? $variant_faq
                    : (is_array($base_faq) ? $base_faq : []);
        } else {
            $oz_faq = get_post_meta($product_id, '_oz_faq', true);
            if (!is_array($oz_faq)) $oz_faq = [];
        }
        // Pre-fill with line defaults if no FAQ exists
        $default_faq = ($config && isset($config['faq'])) ? $config['faq'] : [];
        $effective_faq = !empty($oz_faq) ? $oz_faq : $default_faq;
        ?>
        <div class="oz-meta-section">
            <h4>Veelgestelde vragen (FAQ)</h4>
            <?php if ($is_variant) : ?>
            <label style="display:block; margin-bottom:6px;">
                <input type="checkbox" name="oz_override_faq" value="yes" <?php checked($ovr_faq); ?>>
                <strong>Afwijkend voor dit product</strong>
            </label>
            <p class="description">Uitgevinkt = je bewerkt het hoofdproduct. Aangevinkt = alleen deze kleur.</p>
            <?php else : ?>
            <p class="description">Deze waarden gelden voor alle kleuren binnen deze productlijn.</p>
            <?php endif; ?>

            <div id="oz-faq-rows">
                <?php
                // Pre-fill with effective FAQs + 1 empty row
                $faq_rows = !empty($effective_faq) ? $effective_faq : [];
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

        <!-- ═══ SHOWCASE SECTIES ═══ -->
        <div class="oz-meta-section">
            <h4>Showcase Secties</h4>
            <p class="description">Secties met afbeelding + tekst onder de productpagina. Stel in op het hoofdproduct — varianten erven deze automatisch.</p>
            <?php
            $showcase = get_post_meta($post->ID, '_oz_showcase_sections', true);
            if (!is_array($showcase) || empty($showcase)) {
                // Fall back to config defaults for this product line
                $line_info = OZ_Product_Line_Config::for_product(wc_get_product($post->ID));
                $lk = $line_info['line'] ?? '';
                $showcase = $lk ? OZ_Product_Line_Config::get_showcase_defaults($lk) : [];
            }
            ?>
            <div id="oz-showcase-sections">
                <?php foreach ($showcase as $i => $section) :
                    $thumb = !empty($section['image_id']) ? wp_get_attachment_image_url($section['image_id'], 'medium') : '';
                ?>
                <div class="oz-showcase-section-row" style="border:1px solid #ddd; padding:12px; margin-bottom:10px; border-radius:4px; background:#fafafa;">
                    <div style="display:flex; gap:12px;">
                        <div style="flex-shrink:0;">
                            <div class="oz-showcase-img-preview" style="width:150px; height:100px; border-radius:6px; border:1px solid #ccc; overflow:hidden; background:#eee; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                                <?php if ($thumb) : ?>
                                    <img src="<?php echo esc_url($thumb); ?>" style="width:100%; height:100%; object-fit:cover;">
                                <?php else : ?>
                                    <span style="color:#999; font-size:12px;">+ Afbeelding</span>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="oz_showcase_image_ids[]" value="<?php echo absint($section['image_id'] ?? 0); ?>">
                        </div>
                        <div style="flex:1; display:flex; flex-direction:column; gap:6px;">
                            <input type="text" name="oz_showcase_titles[]" value="<?php echo esc_attr($section['title'] ?? ''); ?>" placeholder="Titel" style="width:100%; font-weight:600;">
                            <input type="text" name="oz_showcase_subtitles[]" value="<?php echo esc_attr($section['subtitle'] ?? ''); ?>" placeholder="Subtitel (optioneel)" style="width:100%;">
                            <textarea name="oz_showcase_texts[]" placeholder="Tekst (optioneel)" rows="2" style="width:100%;"><?php echo esc_textarea($section['text'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <button type="button" class="button oz-showcase-section-remove" style="color:#a00; margin-top:8px;">Verwijderen</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" id="oz-showcase-add-section">+ Sectie toevoegen</button>
        </div>

        <script>
        (function(){
            var container = document.getElementById('oz-showcase-sections');

            // Add new section
            document.getElementById('oz-showcase-add-section').addEventListener('click', function() {
                var row = document.createElement('div');
                row.className = 'oz-showcase-section-row';
                row.style.cssText = 'border:1px solid #ddd; padding:12px; margin-bottom:10px; border-radius:4px; background:#fafafa;';
                row.innerHTML = '<div style="display:flex; gap:12px;">'
                    + '<div style="flex-shrink:0;">'
                    + '<div class="oz-showcase-img-preview" style="width:150px; height:100px; border-radius:6px; border:1px solid #ccc; overflow:hidden; background:#eee; cursor:pointer; display:flex; align-items:center; justify-content:center;"><span style="color:#999; font-size:12px;">+ Afbeelding</span></div>'
                    + '<input type="hidden" name="oz_showcase_image_ids[]" value="0">'
                    + '</div>'
                    + '<div style="flex:1; display:flex; flex-direction:column; gap:6px;">'
                    + '<input type="text" name="oz_showcase_titles[]" value="" placeholder="Titel" style="width:100%; font-weight:600;">'
                    + '<input type="text" name="oz_showcase_subtitles[]" value="" placeholder="Subtitel (optioneel)" style="width:100%;">'
                    + '<textarea name="oz_showcase_texts[]" placeholder="Tekst (optioneel)" rows="2" style="width:100%;"></textarea>'
                    + '</div></div>'
                    + '<button type="button" class="button oz-showcase-section-remove" style="color:#a00; margin-top:8px;">Verwijderen</button>';
                container.appendChild(row);
            });

            // Remove section
            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('oz-showcase-section-remove')) {
                    e.target.closest('.oz-showcase-section-row').remove();
                }
            });

            // Click image preview to open media library
            container.addEventListener('click', function(e) {
                var preview = e.target.closest('.oz-showcase-img-preview');
                if (!preview) return;
                var row = preview.closest('.oz-showcase-section-row');
                var hiddenInput = row.querySelector('input[name="oz_showcase_image_ids[]"]');

                var frame = wp.media({
                    title: 'Afbeelding kiezen',
                    multiple: false,
                    library: { type: 'image' },
                    button: { text: 'Selecteren' }
                });
                frame.on('select', function() {
                    var att = frame.state().get('selection').first().toJSON();
                    var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                    preview.innerHTML = '<img src="' + url + '" style="width:100%; height:100%; object-fit:cover;">';
                    hiddenInput.value = att.id;
                });
                frame.open();
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

        // SECURITY: nonce alone is not enough — a low-priv user with a stolen
        // nonce could save product meta. Verify the current user can actually
        // edit this product.
        if (!current_user_can('edit_product', $product_id)) {
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

        // For configured lines: USPs/specs/FAQ save to the base product by default.
        // Variants can save each section to their own meta when that section is overridden.
        $cat_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        $line_key = OZ_Product_Line_Config::detect_from_data($product_id, $cat_ids ?: []);
        $config = $line_key ? OZ_Product_Line_Config::get_config($line_key) : null;
        $base_id = (!empty($config['base_id'])) ? $config['base_id'] : $product_id;
        $is_variant = ($base_id !== $product_id);

        // Per-section override toggles — each section saves independently
        $ovr_usps  = isset($_POST['oz_override_usps'])  && $_POST['oz_override_usps']  === 'yes';
        $ovr_specs = isset($_POST['oz_override_specs']) && $_POST['oz_override_specs'] === 'yes';
        $ovr_faq   = isset($_POST['oz_override_faq'])   && $_POST['oz_override_faq']   === 'yes';

        if ($is_variant) {
            // Toggle override flags — data is kept in the database even when
            // override is off, so re-enabling brings back the variant values.
            $ovr_usps  ? update_post_meta($product_id, '_oz_override_usps', 'yes')
                       : delete_post_meta($product_id, '_oz_override_usps');
            $ovr_specs ? update_post_meta($product_id, '_oz_override_specs', 'yes')
                       : delete_post_meta($product_id, '_oz_override_specs');
            $ovr_faq   ? update_post_meta($product_id, '_oz_override_faq', 'yes')
                       : delete_post_meta($product_id, '_oz_override_faq');

            // Clean up old single-flag if it exists
            delete_post_meta($product_id, '_oz_override_shared');
        }

        // USPs — save to variant (if override) or base product.
        // When a variant turns OFF override, skip saving to avoid polluting the base product
        // with variant-specific form data that was still in the inputs.
        $usps_target = $ovr_usps ? $product_id : ($is_variant ? null : $product_id);
        if ($usps_target !== null) {
            $usps = [];
            if (isset($_POST['oz_usps']) && is_array($_POST['oz_usps'])) {
                foreach ($_POST['oz_usps'] as $usp) {
                    $usps[] = sanitize_text_field($usp);
                }
            }
            $has_usps = array_filter($usps, function($v) { return $v !== ''; });
            if (!empty($has_usps)) {
                update_post_meta($usps_target, '_oz_usps', $usps);
            } else {
                delete_post_meta($usps_target, '_oz_usps');
            }
        }

        // Specs — same logic: skip saving when variant turns off override
        $specs_target = $ovr_specs ? $product_id : ($is_variant ? null : $product_id);
        if ($specs_target !== null) {
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
                update_post_meta($specs_target, '_oz_specs', $specs);
            } else {
                delete_post_meta($specs_target, '_oz_specs');
            }
        }

        // FAQ — same logic: skip saving when variant turns off override
        $faq_target = $ovr_faq ? $product_id : ($is_variant ? null : $product_id);
        if ($faq_target !== null) {
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
                update_post_meta($faq_target, '_oz_faq', $faqs);
            } else {
                delete_post_meta($faq_target, '_oz_faq');
            }
        }

        // Showcase sections — each section has image + title + subtitle + text
        if (isset($_POST['oz_showcase_image_ids']) && is_array($_POST['oz_showcase_image_ids'])) {
            $sections = [];
            foreach ($_POST['oz_showcase_image_ids'] as $i => $img_id) {
                $title    = sanitize_text_field($_POST['oz_showcase_titles'][$i] ?? '');
                $subtitle = sanitize_text_field($_POST['oz_showcase_subtitles'][$i] ?? '');
                $text     = sanitize_textarea_field($_POST['oz_showcase_texts'][$i] ?? '');
                $img_id   = absint($img_id);

                // Keep section if it has at least a title or an image
                if ($title || $img_id) {
                    $sections[] = [
                        'image_id' => $img_id,
                        'title'    => $title,
                        'subtitle' => $subtitle,
                        'text'     => $text,
                    ];
                }
            }
            if (!empty($sections)) {
                update_post_meta($product_id, '_oz_showcase_sections', $sections);
            } else {
                delete_post_meta($product_id, '_oz_showcase_sections');
            }
        } else {
            delete_post_meta($product_id, '_oz_showcase_sections');
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
