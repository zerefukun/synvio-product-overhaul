<?php
/**
 * Extract complete YITH WAPO option matrix from the BCW database.
 * Run via: php /path/to/extract-wapo.php
 *
 * Outputs a structured JSON with all blocks, their targeting rules,
 * options, labels, prices, and conditions.
 */

// Load WordPress
define('ABSPATH', '/home/betoncire/public_html/');
require_once ABSPATH . 'wp-load.php';

global $wpdb;
$prefix = $wpdb->prefix; // OTBgD_

// ── 1. Get all WAPO blocks (groups) ──
$blocks = $wpdb->get_results("
    SELECT id, name, priority, visibility, settings
    FROM {$prefix}yith_wapo_blocks
    ORDER BY priority ASC, id ASC
");

$output = [];

foreach ($blocks as $block) {
    $block_data = [
        'block_id'   => (int) $block->id,
        'name'       => $block->name,
        'priority'   => (int) $block->priority,
        'visibility' => $block->visibility,
        'settings'   => json_decode($block->settings, true),
        'addons'     => [],
    ];

    // ── 2. Get all addons in this block ──
    $addons = $wpdb->get_results($wpdb->prepare("
        SELECT id, block_id, title, type, options, settings, priority
        FROM {$prefix}yith_wapo_addons
        WHERE block_id = %d
        ORDER BY priority ASC, id ASC
    ", $block->id));

    foreach ($addons as $addon) {
        $options_raw  = maybe_unserialize($addon->options);
        $settings_raw = maybe_unserialize($addon->settings);

        // If options is still a string, try json_decode
        if (is_string($options_raw)) {
            $options_raw = json_decode($options_raw, true);
        }
        if (is_string($settings_raw)) {
            $settings_raw = json_decode($settings_raw, true);
        }

        $addon_data = [
            'addon_id'  => (int) $addon->id,
            'block_id'  => (int) $addon->block_id,
            'title'     => $addon->title,
            'type'      => $addon->type,
            'priority'  => (int) $addon->priority,
            'options'   => $options_raw,
            'settings'  => $settings_raw,
        ];

        $block_data['addons'][] = $addon_data;
    }

    // ── 3. Get targeting rules for this block ──
    // WAPO stores targeting in the block settings or a separate table
    // Check block settings for product/category targeting
    $block_settings = json_decode($block->settings, true);
    if ($block_settings) {
        $block_data['targeting'] = [];

        // Common WAPO targeting keys
        $targeting_keys = [
            'show_in', 'show_in_products', 'show_in_categories',
            'exclude_products', 'exclude_categories',
            'product_list', 'category_list',
        ];

        foreach ($targeting_keys as $tk) {
            if (isset($block_settings[$tk]) && !empty($block_settings[$tk])) {
                $block_data['targeting'][$tk] = $block_settings[$tk];
            }
        }
    }

    $output[] = $block_data;
}

// ── 4. Also get the raw block_rules table if it exists ──
$rules_table = $prefix . 'yith_wapo_block_rules';
$rules_exist = $wpdb->get_var("SHOW TABLES LIKE '{$rules_table}'");
if ($rules_exist) {
    $rules = $wpdb->get_results("SELECT * FROM {$rules_table}");
    $output_rules = [];
    foreach ($rules as $r) {
        $output_rules[] = (array) $r;
    }
} else {
    $output_rules = 'table_not_found';
}

// ── 5. Get list of product categories for reference ──
$cats = $wpdb->get_results("
    SELECT t.term_id, t.name, t.slug
    FROM {$prefix}terms t
    INNER JOIN {$prefix}term_taxonomy tt ON t.term_id = tt.term_id
    WHERE tt.taxonomy = 'product_cat'
    ORDER BY t.name ASC
");
$cat_map = [];
foreach ($cats as $c) {
    $cat_map[$c->term_id] = [
        'name' => $c->name,
        'slug' => $c->slug,
    ];
}

// ── 6. Get all WAPO-related tables for completeness ──
$wapo_tables = $wpdb->get_col("SHOW TABLES LIKE '{$prefix}yith_wapo%'");

// ── Output ──
$final = [
    'extracted_at'  => date('Y-m-d H:i:s'),
    'db_prefix'     => $prefix,
    'wapo_tables'   => $wapo_tables,
    'category_map'  => $cat_map,
    'block_rules'   => $output_rules,
    'blocks'        => $output,
];

// Pretty-print JSON
echo json_encode($final, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
