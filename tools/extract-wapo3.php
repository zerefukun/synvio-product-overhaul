<?php
/**
 * Extract complete WAPO option matrix — unserialized and structured.
 * Run: php extract-wapo3.php > /tmp/wapo-matrix.json
 */
define('ABSPATH', '/home/betoncire/public_html/');
require_once ABSPATH . 'wp-load.php';
global $wpdb;
$p = $wpdb->prefix;

// ── Category map for reference ──
$cats = $wpdb->get_results("
    SELECT t.term_id, t.name, t.slug
    FROM {$p}terms t
    INNER JOIN {$p}term_taxonomy tt ON t.term_id = tt.term_id
    WHERE tt.taxonomy = 'product_cat'
    ORDER BY t.name ASC
");
$cat_map = [];
foreach ($cats as $c) {
    $cat_map[$c->term_id] = $c->name . ' (' . $c->slug . ')';
}

// ── Get all blocks ──
$blocks_raw = $wpdb->get_results("SELECT * FROM {$p}yith_wapo_blocks ORDER BY priority ASC, id ASC", ARRAY_A);

// ── Get all addons ──
$addons_raw = $wpdb->get_results("SELECT * FROM {$p}yith_wapo_addons ORDER BY block_id ASC, priority ASC, id ASC", ARRAY_A);

// Index addons by block_id
$addons_by_block = [];
foreach ($addons_raw as $a) {
    $addons_by_block[$a['block_id']][] = $a;
}

$output = [];

foreach ($blocks_raw as $block) {
    $settings = maybe_unserialize($block['settings']);
    $block_name = isset($settings['name']) ? $settings['name'] : 'Block #' . $block['id'];
    $rules = isset($settings['rules']) ? $settings['rules'] : [];

    // Resolve targeting
    $targeting = [];
    if (isset($rules['show_in'])) {
        $targeting['show_in'] = $rules['show_in'];
    }
    if (!empty($rules['show_in_products'])) {
        // Resolve product names
        $pids = is_array($rules['show_in_products']) ? $rules['show_in_products'] : [$rules['show_in_products']];
        $product_names = [];
        foreach ($pids as $pid) {
            $prod = wc_get_product($pid);
            $product_names[$pid] = $prod ? $prod->get_name() : 'DELETED #' . $pid;
        }
        $targeting['products'] = $product_names;
    }
    if (!empty($rules['show_in_categories'])) {
        $cids = is_array($rules['show_in_categories']) ? $rules['show_in_categories'] : [$rules['show_in_categories']];
        $cat_names = [];
        foreach ($cids as $cid) {
            $cat_names[$cid] = isset($cat_map[$cid]) ? $cat_map[$cid] : 'Unknown #' . $cid;
        }
        $targeting['categories'] = $cat_names;
    }

    // Build addon list for this block
    $addon_list = [];
    $block_addons = isset($addons_by_block[$block['id']]) ? $addons_by_block[$block['id']] : [];

    foreach ($block_addons as $addon) {
        $a_settings = maybe_unserialize($addon['settings']);
        $a_options  = maybe_unserialize($addon['options']);

        $type  = isset($a_settings['type']) ? $a_settings['type'] : 'unknown';
        $title = isset($a_settings['title']) ? $a_settings['title'] : '';
        $desc  = isset($a_settings['description']) ? $a_settings['description'] : '';

        // Conditional logic
        $conditional = [];
        if (isset($a_settings['enable_rules']) && $a_settings['enable_rules'] === 'yes') {
            $conditional['display'] = isset($a_settings['conditional_logic_display']) ? $a_settings['conditional_logic_display'] : '';
            $conditional['display_if'] = isset($a_settings['conditional_logic_display_if']) ? $a_settings['conditional_logic_display_if'] : '';
            $conditional['rule_addon'] = isset($a_settings['conditional_rule_addon']) ? $a_settings['conditional_rule_addon'] : [];
            $conditional['rule_addon_is'] = isset($a_settings['conditional_rule_addon_is']) ? $a_settings['conditional_rule_addon_is'] : [];
        }

        // Extract clean option entries
        $clean_options = [];
        if (is_array($a_options) && isset($a_options['label'])) {
            $count = count($a_options['label']);
            for ($i = 0; $i < $count; $i++) {
                $opt = [
                    'label' => isset($a_options['label'][$i]) ? $a_options['label'][$i] : '',
                    'enabled' => isset($a_options['addon_enabled'][$i]) ? $a_options['addon_enabled'][$i] : 'yes',
                ];

                // Price
                if (isset($a_options['price_method'][$i])) {
                    $opt['price_method'] = $a_options['price_method'][$i];
                }
                if (isset($a_options['price'][$i]) && $a_options['price'][$i] !== '') {
                    $opt['price'] = $a_options['price'][$i];
                }
                if (isset($a_options['price_type'][$i])) {
                    $opt['price_type'] = $a_options['price_type'][$i];
                }
                if (isset($a_options['price_sale'][$i]) && $a_options['price_sale'][$i] !== '') {
                    $opt['price_sale'] = $a_options['price_sale'][$i];
                }

                // Default
                if (isset($a_options['default'][$i])) {
                    $opt['default'] = $a_options['default'][$i];
                }

                // Tooltip
                if (isset($a_options['tooltip'][$i]) && $a_options['tooltip'][$i] !== '') {
                    $opt['tooltip'] = $a_options['tooltip'][$i];
                }

                // Description (for option-level)
                if (isset($a_options['description'][$i]) && $a_options['description'][$i] !== '') {
                    $opt['description'] = $a_options['description'][$i];
                }

                // Color
                if (isset($a_options['color'][$i]) && $a_options['color'][$i] !== '') {
                    $opt['color'] = $a_options['color'][$i];
                }

                // Placeholder (text fields)
                if (isset($a_options['placeholder'][$i]) && $a_options['placeholder'][$i] !== '') {
                    $opt['placeholder'] = $a_options['placeholder'][$i];
                }

                // Required (text fields)
                if (isset($a_options['required'][$i])) {
                    $opt['required'] = $a_options['required'][$i];
                }

                $clean_options[] = $opt;
            }
        }

        $addon_entry = [
            'addon_id' => (int) $addon['id'],
            'type'     => $type,
            'title'    => $title,
            'priority' => $addon['priority'],
            'visible'  => (int) $addon['visibility'] === 1,
        ];

        if ($desc) {
            $addon_entry['description'] = $desc;
        }
        if (!empty($conditional)) {
            $addon_entry['conditional'] = $conditional;
        }
        if (!empty($clean_options)) {
            $addon_entry['options'] = $clean_options;
        }

        // Selection type
        if (isset($a_settings['selection_type'])) {
            $addon_entry['selection_type'] = $a_settings['selection_type'];
        }

        $addon_list[] = $addon_entry;
    }

    $block_entry = [
        'block_id'  => (int) $block['id'],
        'name'      => $block_name,
        'priority'  => $block['priority'],
        'visible'   => (int) $block['visibility'] === 1,
        'targeting' => $targeting,
        'addons'    => $addon_list,
    ];

    $output[] = $block_entry;
}

echo json_encode([
    'extracted_at' => date('Y-m-d H:i:s'),
    'total_blocks' => count($blocks_raw),
    'total_addons' => count($addons_raw),
    'category_map' => $cat_map,
    'blocks'       => $output,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
