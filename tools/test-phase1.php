<?php
/**
 * Phase 1 Test Script for oz-variations-bcw
 *
 * Tests: plugin loading, line detection, pricing math,
 * validation, option payloads, variant linking, base redirect.
 *
 * Usage: ssh bcw "cd /home/betoncire/public_html && php test-phase1.php"
 */

// Bootstrap WordPress
require_once '/home/betoncire/public_html/wp-load.php';

$pass = 0;
$fail = 0;

function test($label, $condition, $detail = '') {
    global $pass, $fail;
    if ($condition) {
        echo "  PASS: $label\n";
        $pass++;
    } else {
        echo "  FAIL: $label" . ($detail ? " — $detail" : "") . "\n";
        $fail++;
    }
}

echo "=== OZ VARIATIONS BCW — PHASE 1 TESTS ===\n\n";


/* ─── 1. PLUGIN LOADED ──────────────────────────────────── */
echo "--- 1. Plugin Loading ---\n";

test('OZ_Product_Line_Config class exists', class_exists('OZ_Product_Line_Config'));
test('OZ_Cart_Manager class exists', class_exists('OZ_Cart_Manager'));
test('OZ_Frontend_Display class exists', class_exists('OZ_Frontend_Display'));
test('OZ_Ajax_Handlers class exists', class_exists('OZ_Ajax_Handlers'));
test('OZ_Product_Processor class exists', class_exists('OZ_Product_Processor'));
test('OZ_BCW_Admin class exists', class_exists('OZ_BCW_Admin'));
test('OZ_BCW_VERSION defined', defined('OZ_BCW_VERSION'));
echo "\n";


/* ─── 2. LINE DETECTION ─────────────────────────────────── */
echo "--- 2. Product Line Detection ---\n";

$detection_tests = [
    // [product_id, expected_line]
    [11161, 'original'],
    [11165, 'all-in-one'],
    [11160, 'easyline'],
    [22760, 'microcement'],
    [11162, 'metallic'],
    [27736, 'lavasteen'],
    [11135, 'betonlook-verf'],
];

foreach ($detection_tests as [$pid, $expected]) {
    $product = wc_get_product($pid);
    if (!$product) {
        test("Detect $pid => $expected", false, "product not found");
        continue;
    }
    $line = OZ_Product_Line_Config::detect($product);
    test(
        "Detect {$product->get_name()} ($pid) => $expected",
        $line === $expected,
        "got: " . ($line ?: 'false')
    );
}
echo "\n";


/* ─── 3. CONFIG STRUCTURE ────────────────────────────────── */
echo "--- 3. Config Structure ---\n";

$all_lines = OZ_Product_Line_Config::get_all_lines();
test('9 product lines defined', count($all_lines) === 9, 'got ' . count($all_lines));

$required_keys = ['cats', 'unit', 'unitM2', 'has_pu', 'has_primer', 'has_colorfresh',
                  'has_toepassing', 'has_pakket', 'ral_ncs', 'ral_ncs_only', 'option_order'];

foreach ($all_lines as $key) {
    $config = OZ_Product_Line_Config::get_config($key);
    $missing = [];
    foreach ($required_keys as $rk) {
        if (!array_key_exists($rk, $config)) $missing[] = $rk;
    }
    test("Config '$key' has all required keys", empty($missing), 'missing: ' . implode(', ', $missing));
}
echo "\n";


/* ─── 4. PU PRICING ─────────────────────────────────────── */
echo "--- 4. PU Pricing (direct lookup) ---\n";

$pu_tests = [
    // [line, layers, expected_price]
    ['original',    0, 0],
    ['original',    1, 40],
    ['original',    2, 80],
    ['original',    3, 120],
    ['all-in-one',  1, 8],
    ['all-in-one',  2, 16],
    ['all-in-one',  3, 24],
    ['all-in-one',  0, 0],
    ['easyline',    1, 0],      // 1 layer = free (included)
    ['easyline',    2, 40],
    ['easyline',    3, 80],
    ['microcement', 1, 8],
    ['metallic',    0, 0],
    ['metallic',    1, 39.99],
    ['metallic',    2, 79.99],
    ['metallic',    3, 119.99],
    ['lavasteen',   1, 40],
    ['lavasteen',   0, 0],
];

foreach ($pu_tests as [$line, $layers, $expected]) {
    $price = OZ_Product_Line_Config::get_pu_price($line, $layers);
    test(
        "$line PU $layers layers => $expected",
        abs($price - $expected) < 0.01,
        "got: $price"
    );
}
echo "\n";


/* ─── 5. PRIMER PRICING ─────────────────────────────────── */
echo "--- 5. Primer Pricing ---\n";

$primer_tests = [
    ['original',       'Geen',                     0],
    ['original',       'Zuigende ondergrond',       12.50],
    ['original',       'Niet zuigende ondergrond',  12.50],
    ['metallic',       'Geen',                     0],
    ['metallic',       'Primer',                   5.99],
    ['betonlook-verf', 'Geen Primer',              0],
    ['betonlook-verf', 'Primer',                   6.00],
    ['stuco-paste',    'Nee',                      0],
    ['stuco-paste',    'Ja',                       16.00],
];

foreach ($primer_tests as [$line, $choice, $expected]) {
    $price = OZ_Product_Line_Config::get_primer_price($line, $choice);
    test(
        "$line primer '$choice' => $expected",
        abs($price - $expected) < 0.01,
        "got: $price"
    );
}
echo "\n";


/* ─── 6. COLORFRESH PRICING ──────────────────────────────── */
echo "--- 6. Colorfresh (Original only) ---\n";

$cf = OZ_Product_Line_Config::get_colorfresh_options('original');
test('Original has colorfresh options', $cf !== false && is_array($cf));
test('Colorfresh has 2 options', is_array($cf) && count($cf) === 2);

$cf_aio = OZ_Product_Line_Config::get_colorfresh_options('all-in-one');
test('All-In-One has no colorfresh', $cf_aio === false);

$cf_price = OZ_Product_Line_Config::get_colorfresh_price('original', 'Met Colorfresh');
test('Colorfresh price = 15.00', abs($cf_price - 15.00) < 0.01, "got: $cf_price");
echo "\n";


/* ─── 7. RESOLVE ADDON PRICE (combined) ─────────────────── */
echo "--- 7. resolve_addon_price() ---\n";

$resolve_tests = [
    // [line, cart_data, expected_total]
    ['original', ['oz_pu_layers' => 2, 'oz_primer' => 'Zuigende ondergrond', 'oz_colorfresh' => 'Met Colorfresh'], 80 + 12.50 + 15.00],
    ['original', ['oz_pu_layers' => 0, 'oz_primer' => 'Geen'], 0],
    ['metallic', ['oz_pu_layers' => 1, 'oz_primer' => 'Primer'], 39.99 + 5.99],
    ['easyline', ['oz_pu_layers' => 3], 80],
    ['all-in-one', ['oz_pu_layers' => 2], 16],
    ['betonlook-verf', ['oz_primer' => 'Primer'], 6.00],
    ['stuco-paste', ['oz_primer' => 'Ja'], 16.00],
    ['lavasteen', ['oz_pu_layers' => 1], 40],
];

foreach ($resolve_tests as [$line, $data, $expected]) {
    $result = OZ_Product_Line_Config::resolve_addon_price($line, $data);
    $desc = json_encode($data);
    test(
        "$line $desc => $expected",
        abs($result - $expected) < 0.01,
        "got: $result"
    );
}
echo "\n";


/* ─── 8. PAYLOAD SHAPE ──────────────────────────────────── */
echo "--- 8. Frontend Payload Shape ---\n";

// Test PU options payload
$pu_opts = OZ_Product_Line_Config::get_pu_options('original');
test('Original PU options is array', is_array($pu_opts));
test('Original PU has 4 options', is_array($pu_opts) && count($pu_opts) === 4);
if (is_array($pu_opts) && !empty($pu_opts)) {
    $first = $pu_opts[0];
    test('PU option has layers key', array_key_exists('layers', $first));
    test('PU option has label key', array_key_exists('label', $first));
    test('PU option has price key', array_key_exists('price', $first));
    test('PU option has default key', array_key_exists('default', $first));
}

// Lines without PU should return false
$pu_bv = OZ_Product_Line_Config::get_pu_options('betonlook-verf');
test('Betonlook Verf PU = false', $pu_bv === false);

// Easyline has 3 PU options (no "Geen")
$pu_easy = OZ_Product_Line_Config::get_pu_options('easyline');
test('Easyline PU has 3 options (no Geen)', is_array($pu_easy) && count($pu_easy) === 3);

// Metallic defaults
$pu_met = OZ_Product_Line_Config::get_pu_options('metallic');
if (is_array($pu_met)) {
    $defaults = array_filter($pu_met, function($o) { return $o['default']; });
    test('Metallic PU default = Geen PU', count($defaults) === 1 && reset($defaults)['layers'] === 0);
}

// Lavasteen defaults
$pu_lav = OZ_Product_Line_Config::get_pu_options('lavasteen');
if (is_array($pu_lav)) {
    $defaults = array_filter($pu_lav, function($o) { return $o['default']; });
    test('Lavasteen PU default = 1 toplaag', count($defaults) === 1 && reset($defaults)['layers'] === 1);
}

// Toepassing
$toep = OZ_Product_Line_Config::get_toepassing_options('original');
test('Original has toepassing', is_array($toep) && count($toep) === 6);
$toep_aio = OZ_Product_Line_Config::get_toepassing_options('all-in-one');
test('All-In-One has no toepassing', $toep_aio === false);

// Pakket
$pakket = OZ_Product_Line_Config::get_pakket_options('original');
test('Original has pakket', is_array($pakket) && count($pakket) >= 1);
$pakket_mc = OZ_Product_Line_Config::get_pakket_options('microcement');
test('Microcement has no pakket', $pakket_mc === false);

// RAL/NCS flags
$config_aio = OZ_Product_Line_Config::get_config('all-in-one');
test('All-In-One has RAL/NCS', $config_aio['ral_ncs'] === true);
test('All-In-One is NOT ral_ncs_only', $config_aio['ral_ncs_only'] === false);

$config_pu = OZ_Product_Line_Config::get_config('pu-color');
test('PU Color has RAL/NCS', $config_pu['ral_ncs'] === true);
test('PU Color IS ral_ncs_only', $config_pu['ral_ncs_only'] === true);

$config_orig = OZ_Product_Line_Config::get_config('original');
test('Original has NO RAL/NCS', $config_orig['ral_ncs'] === false);
echo "\n";


/* ─── 9. VALIDATION ──────────────────────────────────────── */
echo "--- 9. Validation ---\n";

// RAL/NCS required
$err = OZ_Cart_Manager::validate_addon_array(['oz_color_mode' => 'ral_ncs', 'oz_custom_color' => '']);
test('RAL/NCS empty => error', $err !== null, $err ?: 'no error');

$err = OZ_Cart_Manager::validate_addon_array(['oz_color_mode' => 'ral_ncs', 'oz_custom_color' => 'RAL 7016']);
test('RAL/NCS with code => pass', $err === null, $err ?: '');

$err = OZ_Cart_Manager::validate_addon_array(['oz_color_mode' => 'standard']);
test('Standard mode => pass', $err === null, $err ?: '');

$err = OZ_Cart_Manager::validate_addon_array([]);
test('Empty data => pass', $err === null, $err ?: '');
echo "\n";


/* ─── 10. OPTION ORDER ───────────────────────────────────── */
echo "--- 10. Option Order ---\n";

$order_tests = [
    ['original',       ['pakket', 'color', 'toepassing', 'primer', 'colorfresh', 'pu']],
    ['all-in-one',     ['color', 'pu']],
    ['easyline',       ['color', 'pakket', 'pu']],
    ['microcement',    ['color', 'pu']],
    ['metallic',       ['color', 'primer', 'pu']],
    ['betonlook-verf', ['color', 'primer']],
    ['stuco-paste',    ['primer']],
    ['pu-color',       ['color']],
];

foreach ($order_tests as [$line, $expected]) {
    $order = OZ_Product_Line_Config::get_option_order($line);
    test(
        "$line order = [" . implode(', ', $expected) . "]",
        $order === $expected,
        "got: [" . implode(', ', $order) . "]"
    );
}
echo "\n";


/* ─── SUMMARY ────────────────────────────────────────────── */
$total = $pass + $fail;
echo "=== RESULTS: $pass/$total passed";
if ($fail > 0) echo ", $fail FAILED";
echo " ===\n";
