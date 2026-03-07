<?php
/**
 * Product Line Configuration for Beton Ciré Webshop
 *
 * Central config for all 9 BCW product lines. This is the exact runtime
 * representation of reference/WAPO-PARITY-CONFIG.md.
 *
 * All prices are fixed per-unit amounts (not formulas). Every option
 * carries a 'default' flag matching the live WAPO state. The public API
 * returns payload-ready arrays that can be passed directly to JS.
 *
 * Source: YITH WAPO blocks extracted 2026-03-06. Frozen in WAPO-PARITY-CONFIG.md.
 *
 * @package OZ_Variations_BCW
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_Product_Line_Config {

    /**
     * PU price tables — fixed per-unit prices, keyed by layer count.
     * Matches WAPO-PARITY-CONFIG.md §3 exactly.
     */
    private static $pu_prices = [
        'original'    => [0 => 0, 1 => 40,    2 => 80,    3 => 120],
        'all-in-one'  => [0 => 0, 1 => 8,     2 => 16,    3 => 24],
        'easyline'    => [1 => 0, 2 => 40,    3 => 80],  // no 0-layer option
        'microcement' => [0 => 0, 1 => 8,     2 => 16,    3 => 24],
        'metallic'    => [0 => 0, 1 => 39.99, 2 => 79.99, 3 => 119.99],
        'lavasteen'   => [0 => 0, 1 => 40,    2 => 80,    3 => 120],
    ];

    /**
     * PU option labels + defaults per line.
     * Each entry: [layers, label, default]
     * Order matches WAPO display order.
     */
    private static $pu_options = [
        'original' => [
            [0, 'Geen toplaag',  false],
            [1, '1 toplaag',     false],
            [2, '2 Toplagen',    false],
            [3, '3 Toplagen',    false],
        ],
        'all-in-one' => [
            [1, '1 toplaag',          false],
            [2, '2 toplagen',         false],
            [3, '3 toplagen',         false],
            [0, 'Geen Beschermlaag',  false],
        ],
        'easyline' => [
            // No "Geen" — minimum 1 layer (free, included in price)
            [1, '1 toplaag',   false],
            [2, '2 toplagen',  false],
            [3, '3 toplagen',  false],
        ],
        'microcement' => [
            [1, '1 toplaag',          false],
            [2, '2 toplagen',         false],
            [3, '3 toplagen',         false],
            [0, 'Geen Beschermlaag',  false],
        ],
        'metallic' => [
            [0, 'Geen PU',     true],  // default = yes in WAPO
            [1, '1 Laag PU',   false],
            [2, '2 Lagen PU',  false],
            [3, '3 Lagen PU',  false],
        ],
        'lavasteen' => [
            [1, '1 toplaag',    true],  // default = yes in WAPO
            [2, '2 toplagen',   false],
            [3, '3 toplagen',   false],
            [0, 'Geen toplaag', false],
        ],
    ];

    /**
     * Primer price tables per line.
     * Each entry: [label, price, default]
     * Matches WAPO-PARITY-CONFIG.md §4.
     */
    private static $primer_options = [
        'original' => [
            ['Geen',                     0,     false],
            ['Zuigende ondergrond',      12.50, false],
            ['Niet zuigende ondergrond', 12.50, false],
        ],
        'metallic' => [
            ['Geen',   0,    true],   // default = yes in WAPO
            ['Primer', 5.99, false],
        ],
        'betonlook-verf' => [
            ['Geen Primer', 0,    true],  // default = yes in WAPO
            ['Primer',      6.00, false],
        ],
        'stuco-paste' => [
            ['Nee', 0,     false],
            ['Ja',  16.00, false],
        ],
    ];

    /**
     * Product line definitions.
     *
     * Structure per line:
     *   cats          => category IDs for detection
     *   product_ids   => extra product IDs outside line categories (loose emmers, single-product lines)
     *   base_id       => base product ID for redirect (null = no variants)
     *   unit          => display label for package size
     *   unitM2        => m² per unit (0 = not m²-based)
     *   has_pu        => bool — PU toplagen available
     *   has_primer    => bool — primer addon available
     *   has_colorfresh => bool — colorfresh addon available (Original only)
     *   has_toepassing => bool — toepassing selector available (Original only)
     *   has_pakket    => bool — pakket selector available
     *   ral_ncs       => bool — RAL/NCS color mode toggle available
     *   ral_ncs_only  => bool — standard colors disabled, only RAL/NCS (PU Color)
     *   option_order  => display order of option sections on product page
     */
    private static $lines = [

        // ─── ORIGINAL ────────────────────────────────────────────────
        // 48 colors (1000-series), PU 0/40/80/120, primer, colorfresh, toepassing
        'original' => [
            'cats'           => [290],
            'base_id'        => 11161,
            'unit'           => '5m² pakket',
            'unitM2'         => 5,
            'has_pu'         => true,
            'has_primer'     => true,
            'has_colorfresh' => true,
            'has_toepassing' => true,
            'has_pakket'     => true,
            'ral_ncs'        => false,  // Original has NO RAL/NCS
            'ral_ncs_only'   => false,
            'has_tools'      => true,
            'option_order'   => ['pakket', 'color', 'toepassing', 'primer', 'colorfresh', 'pu', 'tools'],
        ],

        // ─── ALL-IN-ONE ──────────────────────────────────────────────
        // 38 colors (K&K palette), PU 8/16/24/0, RAL/NCS
        'all-in-one' => [
            'cats'           => [289],
            'product_ids'    => [11191],  // loose emmer (cat 17 "Losse Materialen")
            'base_id'        => 11165,
            'unit'           => '1m² emmer',
            'unitM2'         => 1,
            'has_pu'         => true,
            'has_primer'     => false,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => true,
            'ral_ncs_only'   => false,
            'has_tools'      => true,
            'option_order'   => ['color', 'pu', 'tools'],
        ],

        // ─── EASYLINE ────────────────────────────────────────────────
        // 38 colors (K&K palette), PU 0/40/80 (no "Geen"), RAL/NCS, pakket
        'easyline' => [
            'cats'           => [314],
            'product_ids'    => [11001, 11002],  // loose emmers (cat 17 "Losse Materialen")
            'base_id'        => 11160,
            'unit'           => '5m² pakket',  // corrected from 4m²
            'unitM2'         => 5,
            'has_pu'         => true,
            'has_primer'     => false,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => true,
            'ral_ncs'        => true,
            'ral_ncs_only'   => false,
            'has_tools'      => true,
            'option_order'   => ['color', 'pakket', 'pu', 'tools'],
        ],

        // ─── MICROCEMENT ─────────────────────────────────────────────
        // 36 colors (own palette), PU 8/16/24/0, RAL/NCS
        'microcement' => [
            'cats'           => [455, 463],
            'base_id'        => 22760,
            'unit'           => 'stuk (1m²)',
            'unitM2'         => 1,
            'has_pu'         => true,
            'has_primer'     => false,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => true,
            'ral_ncs_only'   => false,
            'has_tools'      => true,
            'option_order'   => ['color', 'pu', 'tools'],
        ],

        // ─── METALLIC VELVET ─────────────────────────────────────────
        // 12 colors (own palette), PU 0/39.99/79.99/119.99, primer 5.99
        'metallic' => [
            'cats'           => [18],
            'base_id'        => 11162,
            'unit'           => '4m² pakket',
            'unitM2'         => 4,
            'has_pu'         => true,
            'has_primer'     => true,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => false,
            'ral_ncs_only'   => false,
            'has_tools'      => true,
            'option_order'   => ['color', 'primer', 'pu', 'tools'],  // corrected from WAPO block order
        ],

        // ─── LAVASTEEN ───────────────────────────────────────────────
        // 20 colors (own palette), PU 40/80/120/0, default = 1 layer
        'lavasteen' => [
            'cats'           => [464],
            'base_id'        => 27736,
            'unit'           => '5m² pakket',
            'unitM2'         => 5,
            'has_pu'         => true,
            'has_primer'     => false,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => false,
            'ral_ncs_only'   => false,
            'has_tools'      => true,
            'option_order'   => ['color', 'pu', 'tools'],
        ],

        // ─── BETONLOOK VERF ─────────────────────────────────────────
        // 38 colors (K&K palette), primer 6.00, RAL/NCS
        'betonlook-verf' => [
            'cats'           => [],
            'product_ids'    => [11135],  // single product, detect by ID
            'base_id'        => null,
            'unit'           => 'stuk',
            'unitM2'         => 0,
            'has_pu'         => false,
            'has_primer'     => true,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => true,
            'ral_ncs_only'   => false,
            'option_order'   => ['color', 'primer'],
        ],

        // ─── STUCO PASTE ─────────────────────────────────────────────
        // No colors, primer 16.00
        'stuco-paste' => [
            'cats'           => [457],
            'base_id'        => null,
            'unit'           => 'stuk',
            'unitM2'         => 0,
            'has_pu'         => false,
            'has_primer'     => true,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => false,
            'ral_ncs_only'   => false,
            'option_order'   => ['primer'],
        ],

        // ─── PU COLOR ────────────────────────────────────────────────
        // RAL/NCS ONLY — standard colors disabled in WAPO
        'pu-color' => [
            'cats'           => [456],
            'base_id'        => null,
            'unit'           => 'stuk',
            'unitM2'         => 0,
            'has_pu'         => false,
            'has_primer'     => false,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => true,
            'ral_ncs_only'   => true,   // ONLY RAL/NCS, standard swatches disabled
            'option_order'   => ['color'],
        ],
    ];

    /**
     * Toepassing options (Original only). All free, no pricing.
     */
    private static $toepassing_options = [
        'Vloer', 'Wand', 'Meubel', 'Keuken', 'Badkamer', 'Trap',
    ];

    /**
     * Colorfresh options (Original only).
     * [label, price, default]
     */
    private static $colorfresh_options = [
        ['Zonder Colorfresh', 0,     true],   // default = yes in WAPO
        ['Met Colorfresh',    15.00, false],
    ];

    /**
     * Pakket options per line. Only Original and Easyline have this.
     * [label, price, default]
     */
    private static $pakket_options = [
        'original' => [
            ['5m2', 0, true],
        ],
        'easyline' => [
            ['5m2 - 170,-', 0, true],
        ],
    ];


    /* ══════════════════════════════════════════════════════════════════
     * DETECTION
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Detect product line from a WooCommerce product.
     * Shell wrapper — fetches category IDs then delegates to pure detect_from_data().
     *
     * @param WC_Product $product
     * @return string|false  Line key or false
     */
    public static function detect($product) {
        $product_id   = $product->get_id();
        $category_ids = self::get_category_ids($product_id);
        return self::detect_from_data($product_id, $category_ids);
    }

    /**
     * Pure detection: match product to a line using pre-fetched data.
     * No I/O — testable with plain arrays.
     *
     * @param int   $product_id    WooCommerce product ID
     * @param array $category_ids  Product's category term IDs
     * @return string|false  Line key or false
     */
    public static function detect_from_data($product_id, array $category_ids) {
        foreach (self::$lines as $key => $line) {
            // Category match (main detection path)
            if (!empty($line['cats']) && array_intersect($category_ids, $line['cats'])) {
                return $key;
            }
            // product_ids array — loose emmers, single-product lines
            if (!empty($line['product_ids']) && in_array($product_id, $line['product_ids'], false)) {
                return $key;
            }
        }
        return false;
    }

    /**
     * Get full config for a line key.
     *
     * @param string $line_key
     * @return array|false
     */
    public static function get_config($line_key) {
        return isset(self::$lines[$line_key]) ? self::$lines[$line_key] : false;
    }

    /**
     * Detect + get config in one call.
     *
     * @param WC_Product $product
     * @return array  ['line' => key, 'config' => array] or ['line' => false, 'config' => false]
     */
    public static function for_product($product) {
        $line = self::detect($product);
        return [
            'line'   => $line,
            'config' => $line ? self::get_config($line) : false,
        ];
    }


    /* ══════════════════════════════════════════════════════════════════
     * PU — PAYLOAD-READY OPTIONS + PRICE LOOKUP
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Get PU options array for frontend / wp_localize_script.
     * Returns array of {layers, label, price, default} or false.
     *
     * @param string $line_key
     * @return array|false
     */
    public static function get_pu_options($line_key) {
        if (!isset(self::$pu_options[$line_key])) {
            return false;
        }

        $prices  = self::$pu_prices[$line_key];
        $options = [];

        foreach (self::$pu_options[$line_key] as list($layers, $label, $default)) {
            $options[] = [
                'layers'  => $layers,
                'label'   => $label,
                'price'   => isset($prices[$layers]) ? $prices[$layers] : 0,
                'default' => $default,
            ];
        }
        return $options;
    }

    /**
     * Look up the PU price for a given layer count.
     * Direct table lookup — no formula.
     *
     * @param string $line_key
     * @param int    $layers  0–3
     * @return float
     */
    public static function get_pu_price($line_key, $layers) {
        $layers = max(0, min(3, intval($layers)));
        if (!isset(self::$pu_prices[$line_key])) {
            return 0;
        }
        return isset(self::$pu_prices[$line_key][$layers])
            ? floatval(self::$pu_prices[$line_key][$layers])
            : 0;
    }


    /* ══════════════════════════════════════════════════════════════════
     * PRIMER — PAYLOAD-READY OPTIONS + PRICE LOOKUP
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Get primer options for frontend.
     * Returns array of {label, price, default} or false.
     *
     * @param string $line_key
     * @return array|false
     */
    public static function get_primer_options($line_key) {
        if (!isset(self::$primer_options[$line_key])) {
            return false;
        }

        $options = [];
        foreach (self::$primer_options[$line_key] as list($label, $price, $default)) {
            $options[] = [
                'label'   => $label,
                'price'   => $price,
                'default' => $default,
            ];
        }
        return $options;
    }

    /**
     * Look up primer price for a given selection.
     *
     * @param string $line_key
     * @param string $primer_choice  The selected option label
     * @return float
     */
    public static function get_primer_price($line_key, $primer_choice) {
        if (!isset(self::$primer_options[$line_key])) {
            return 0;
        }
        foreach (self::$primer_options[$line_key] as list($label, $price, $default)) {
            if ($label === $primer_choice) {
                return floatval($price);
            }
        }
        return 0;
    }


    /* ══════════════════════════════════════════════════════════════════
     * COLORFRESH — ORIGINAL ONLY
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Get colorfresh options for frontend.
     * Returns array of {label, price, default} or false.
     *
     * @param string $line_key
     * @return array|false
     */
    public static function get_colorfresh_options($line_key) {
        $config = self::get_config($line_key);
        if (!$config || !$config['has_colorfresh']) {
            return false;
        }

        $options = [];
        foreach (self::$colorfresh_options as list($label, $price, $default)) {
            $options[] = [
                'label'   => $label,
                'price'   => $price,
                'default' => $default,
            ];
        }
        return $options;
    }

    /**
     * Get colorfresh price for a given selection.
     *
     * @param string $line_key
     * @param string $choice
     * @return float
     */
    public static function get_colorfresh_price($line_key, $choice) {
        $config = self::get_config($line_key);
        if (!$config || !$config['has_colorfresh']) {
            return 0;
        }
        foreach (self::$colorfresh_options as list($label, $price, $default)) {
            if ($label === $choice) {
                return floatval($price);
            }
        }
        return 0;
    }


    /* ══════════════════════════════════════════════════════════════════
     * TOEPASSING — ORIGINAL ONLY
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Get toepassing options for frontend.
     * Returns array of strings or false.
     *
     * @param string $line_key
     * @return array|false
     */
    public static function get_toepassing_options($line_key) {
        $config = self::get_config($line_key);
        if (!$config || !$config['has_toepassing']) {
            return false;
        }
        return self::$toepassing_options;
    }


    /* ══════════════════════════════════════════════════════════════════
     * PAKKET
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Get pakket options for frontend.
     * Returns array of {label, price, default} or false.
     *
     * @param string $line_key
     * @return array|false
     */
    public static function get_pakket_options($line_key) {
        if (!isset(self::$pakket_options[$line_key])) {
            return false;
        }

        $options = [];
        foreach (self::$pakket_options[$line_key] as list($label, $price, $default)) {
            $options[] = [
                'label'   => $label,
                'price'   => $price,
                'default' => $default,
            ];
        }
        return $options;
    }


    /* ══════════════════════════════════════════════════════════════════
     * CONVENIENCE
     * ══════════════════════════════════════════════════════════════════ */

    /** @return int|null */
    public static function get_base_product_id($line_key) {
        $config = self::get_config($line_key);
        return $config ? $config['base_id'] : null;
    }

    /** @return array */
    public static function get_option_order($line_key) {
        $config = self::get_config($line_key);
        return $config ? $config['option_order'] : [];
    }

    /** @return array  All line keys */
    public static function get_all_lines() {
        return array_keys(self::$lines);
    }


    /* ══════════════════════════════════════════════════════════════════
     * DEFAULTS — server-side fallback for omitted POST fields
     *
     * Returns the default addon values for a product line, derived from
     * the 'default' flags in PU/primer/colorfresh option tables.
     * Used by OZ_Cart_Manager::capture_addon_data() when POST omits keys.
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Get default addon values for a product line.
     * Reads the 'default' flag from each option table.
     *
     * @param string $line_key
     * @return array  Keys: oz_pu_layers, oz_primer, oz_colorfresh (only set if line has them)
     */
    public static function get_defaults($line_key) {
        $defaults = [];

        // PU default layers
        if (isset(self::$pu_options[$line_key])) {
            foreach (self::$pu_options[$line_key] as list($layers, $label, $is_default)) {
                if ($is_default) {
                    $defaults['oz_pu_layers'] = $layers;
                    break;
                }
            }
        }

        // Primer default
        if (isset(self::$primer_options[$line_key])) {
            foreach (self::$primer_options[$line_key] as list($label, $price, $is_default)) {
                if ($is_default) {
                    $defaults['oz_primer'] = $label;
                    break;
                }
            }
        }

        // Colorfresh default (Original only)
        $config = self::get_config($line_key);
        if ($config && $config['has_colorfresh']) {
            foreach (self::$colorfresh_options as list($label, $price, $is_default)) {
                if ($is_default) {
                    $defaults['oz_colorfresh'] = $label;
                    break;
                }
            }
        }

        // ral_ncs_only lines force ral_ncs color mode
        if ($config && $config['ral_ncs_only']) {
            $defaults['oz_color_mode'] = 'ral_ncs';
        }

        return $defaults;
    }


    /* ══════════════════════════════════════════════════════════════════
     * FULL ADDON PRICE RESOLVER
     *
     * Single function that takes a line key + cart item data array
     * and returns the total addon surcharge (per unit).
     * Used by cart manager for woocommerce_before_calculate_totals.
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Resolve total addon price surcharge for a cart item.
     *
     * @param string $line_key
     * @param array  $item_data  Cart item data keys (oz_pu_layers, oz_primer, etc.)
     * @return float  Per-unit surcharge
     */
    public static function resolve_addon_price($line_key, $item_data) {
        $total = 0;

        // PU layers
        if (isset($item_data['oz_pu_layers']) && $item_data['oz_pu_layers'] > 0) {
            $total += self::get_pu_price($line_key, $item_data['oz_pu_layers']);
        }

        // Primer
        if (!empty($item_data['oz_primer'])) {
            $total += self::get_primer_price($line_key, $item_data['oz_primer']);
        }

        // Colorfresh (Original only)
        if (!empty($item_data['oz_colorfresh'])) {
            $total += self::get_colorfresh_price($line_key, $item_data['oz_colorfresh']);
        }

        // Toepassing and pakket are free — no price impact

        return $total;
    }


    /* ══════════════════════════════════════════════════════════════════
     * INTERNAL HELPERS
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Get WooCommerce category IDs for a product.
     *
     * @param int $product_id
     * @return array
     */
    private static function get_category_ids($product_id) {
        $terms = get_the_terms($product_id, 'product_cat');
        if (empty($terms) || is_wp_error($terms)) {
            return [];
        }
        return wp_list_pluck($terms, 'term_id');
    }


    /* ══════════════════════════════════════════════════════════════════
     * TOOL / GEREEDSCHAP CATALOG
     *
     * Each tool is defined once in $tool_catalog. The extras and
     * individual lists reference items by ID — no duplication.
     * All prices and WooCommerce product IDs from real BCW catalog.
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Master tool catalog — each tool defined exactly once.
     * Keyed by tool slug. Contains name, base price, WC product ID,
     * optional note, and optional size variants.
     */
    private static $tool_catalog = [
        'flexibele-spaan' => [
            'name' => 'Flexibele spaan', 'price' => 39.95, 'wcId' => 11025,
        ],
        'pu-roller' => [
            'name' => 'PU Roller', 'price' => 2.50, 'wcId' => 11175,
            'note' => 'Verhardt na ~2 uur',
            'sizes' => [
                ['label' => '10cm', 'price' => 2.50,  'wcId' => 11175],
                ['label' => '18cm', 'price' => 9.95,  'wcId' => 17360],
                ['label' => '25cm', 'price' => 12.95, 'wcId' => 17361],
                ['label' => '50cm', 'price' => 17.50, 'wcId' => 19705],
            ],
        ],
        'kwast' => [
            'name' => 'Kwast', 'price' => 1.99, 'wcId' => 11022,
        ],
        'pu-garde' => [
            'name' => 'PU garde', 'price' => 8.99, 'wcId' => 11020,
        ],
        'tape' => [
            'name' => 'Tape', 'price' => 5.99, 'wcId' => 11018,
        ],
        'verfbak' => [
            'name' => 'Verfbak', 'price' => 2.95, 'wcId' => 11164,
            'sizes' => [
                ['label' => '10cm', 'price' => 2.95, 'wcId' => 11164, 'wapoAddon' => null],
                ['label' => '18cm', 'price' => 4.95, 'wcId' => 11164, 'wapoAddon' => '43-1'],
                ['label' => '32cm', 'price' => 5.95, 'wcId' => 11164, 'wapoAddon' => '43-2'],
            ],
        ],
        'vachtroller' => [
            'name' => 'Vachtroller', 'price' => 8.95, 'wcId' => 11015,
        ],
        'blokkwast' => [
            'name' => 'Blokkwast', 'price' => 6.99, 'wcId' => 22997,
        ],
        'troffel' => [
            'name' => 'Troffel 180mm', 'price' => 16.95, 'wcId' => 11017,
        ],
        'hoek-inwendig' => [
            'name' => 'Inwendige hoektroffel', 'price' => 15.95, 'wcId' => 11023,
        ],
        'hoek-uitwendig' => [
            'name' => 'Uitwendige hoektroffel', 'price' => 15.95, 'wcId' => 11016,
        ],
    ];

    /**
     * Gereedschapset Kant & Klaar — the complete set product.
     */
    private static $tool_set = [
        'id'       => 11177,
        'name'     => 'Gereedschapset Kant & Klaar',
        'price'    => 89.99,
        'contents' => [
            '1x Flexibele spaan',
            '1x Kwast primer',
            '1x Kwast PU',
            '1x PU garde',
            '3x PU roller',
            '1x Tape',
            '2x Verfbak',
            '1x Vachtroller',
        ],
    ];

    /** Tool IDs available as extras on top of the set */
    private static $set_extra_ids = [
        'pu-roller', 'verfbak', 'tape', 'vachtroller',
        'troffel', 'hoek-inwendig', 'hoek-uitwendig',
    ];

    /** Tool IDs available in "Zelf samenstellen" (individual) mode */
    private static $individual_tool_ids = [
        'flexibele-spaan', 'pu-roller', 'kwast', 'pu-garde', 'tape',
        'verfbak', 'vachtroller', 'blokkwast', 'troffel',
        'hoek-inwendig', 'hoek-uitwendig',
    ];

    /**
     * Get tool/gereedschap configuration for JS.
     * Composes from single-source catalog — no data duplication.
     *
     * @return array  Tool config array for wp_localize_script
     */
    /**
     * Check WooCommerce stock status for a tool item wcId.
     * Returns true if the product exists and is in stock, false otherwise.
     */
    private static function check_tool_stock($wc_id) {
        if (!function_exists('wc_get_product') || !$wc_id) return true;
        $product = wc_get_product($wc_id);
        return $product ? $product->is_in_stock() : false;
    }

    /**
     * Add stock status to a tool/extra item and its sizes.
     * Adds 'inStock' boolean to the item and each size entry.
     */
    private static function enrich_with_stock($item) {
        $item['inStock'] = self::check_tool_stock($item['wcId'] ?? null);
        if (!empty($item['sizes'])) {
            foreach ($item['sizes'] as &$size) {
                $size['inStock'] = self::check_tool_stock($size['wcId'] ?? null);
            }
            unset($size);
        }
        return $item;
    }

    public static function get_tool_config() {
        // Build extras and tools arrays from catalog references
        // Each item is enriched with stock status from WooCommerce
        $extras = [];
        foreach (self::$set_extra_ids as $id) {
            $extras[] = self::enrich_with_stock(
                array_merge(['id' => $id], self::$tool_catalog[$id])
            );
        }

        $tools = [];
        foreach (self::$individual_tool_ids as $id) {
            $tools[] = self::enrich_with_stock(
                array_merge(['id' => $id], self::$tool_catalog[$id])
            );
        }

        return [
            'toolSet'            => self::$tool_set,
            'extras'             => $extras,
            'tools'              => $tools,
            'nudgeQtyThreshold'  => 3,
        ];
    }
}
