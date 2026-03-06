<?php
// Extract WAPO table structure and raw data
define('ABSPATH', '/home/betoncire/public_html/');
require_once ABSPATH . 'wp-load.php';
global $wpdb;
$p = $wpdb->prefix;

echo "=== BLOCKS TABLE STRUCTURE ===\n";
$cols = $wpdb->get_results("DESCRIBE {$p}yith_wapo_blocks");
foreach ($cols as $c) {
    echo $c->Field . ' | ' . $c->Type . "\n";
}

echo "\n=== ADDONS TABLE STRUCTURE ===\n";
$cols2 = $wpdb->get_results("DESCRIBE {$p}yith_wapo_addons");
foreach ($cols2 as $c) {
    echo $c->Field . ' | ' . $c->Type . "\n";
}

echo "\n=== BLOCK COUNT: " . $wpdb->get_var("SELECT COUNT(*) FROM {$p}yith_wapo_blocks") . "\n";
echo "=== ADDON COUNT: " . $wpdb->get_var("SELECT COUNT(*) FROM {$p}yith_wapo_addons") . "\n";

echo "\n=== RAW BLOCKS ===\n";
$blocks = $wpdb->get_results("SELECT * FROM {$p}yith_wapo_blocks", ARRAY_A);
echo json_encode($blocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== RAW ADDONS (first 5) ===\n";
$addons = $wpdb->get_results("SELECT * FROM {$p}yith_wapo_addons LIMIT 5", ARRAY_A);
echo json_encode($addons, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
