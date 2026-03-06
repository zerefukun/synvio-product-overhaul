# BCW Product Overhaul — Implementation Plan
**Version 1.0 — 6 maart 2026**

Step-by-step build order. Each step is a deployable unit.
Start line: **Microcement** (simplest: PU only, no primer, no RAL/NCS).

---

## Prerequisites

- [ ] SSH access to betoncire server ✅
- [ ] oz-variations source at `/home/betoncire/epoxystone-gietvloer.nl/wp-content/plugins/oz-variations/`
- [ ] BCW site at `/home/betoncire/public_html/`
- [ ] OzTheme child theme at `/home/betoncire/public_html/wp-content/themes/OzTheme/`
- [ ] Mockup reference: `productpagina-voorbeeld.html` (repo: beton-cire-webshop)
- [ ] Staging environment or maintenance mode for testing

---

## Phase 1: oz-variations Port (Backend)

### Step 1.1 — Fork oz-variations for BCW
**Where:** `/home/betoncire/public_html/wp-content/plugins/oz-variations/`

1. Copy the oz-variations plugin from epoxystone to BCW:
   ```
   cp -r /home/betoncire/epoxystone-gietvloer.nl/wp-content/plugins/oz-variations/ \
         /home/betoncire/public_html/wp-content/plugins/oz-variations/
   ```
2. Do NOT activate yet — first adapt for BCW.
3. Remove calculator files (Patrick doesn't want calculator on BCW):
   - Delete `includes/calculator/class-oz-calculator.php`
   - Delete `includes/calculator/class-oz-calculator-display.php`
   - Delete `assets/css/oz-calculator.css`
   - Delete `assets/js/oz-calculator.js`
   - Remove calculator includes from `oz-variations.php`
4. Remove translation files (BCW is Dutch-only):
   - Delete `includes/translation/class-oz-email-translator.php`
   - Delete `includes/translation/class-oz-theme-translator.php`
   - Delete `includes/translation/class-oz-translation.php`
   - Delete `languages/` directory
   - Remove translation includes from `oz-variations.php`
5. Remove NCS database (only needed for epoxystone):
   - Delete `assets/js/ncs-database.js`

**Test:** Plugin folder exists, no PHP errors when activated.

### Step 1.2 — Add Product Line Config
**Where:** `oz-variations/includes/class-oz-product-line-config.php` (NEW file)

Create a config class that maps category IDs to product line settings:

```php
class OZ_Product_Line_Config {
    private static $lines = [
        'microcement'    => ['cats' => [455, 463], 'unit' => '1m²', 'unitM2' => 1,
                             'pu' => [0, 49, 98, 147], 'primer' => false, 'ral_ncs' => false],
        'original'       => ['cats' => [290],      'unit' => '5m²', 'unitM2' => 5,
                             'pu' => [0, 40, 80, 120], 'primer' => [0, 12.50, 12.50], 'ral_ncs' => false],
        'all-in-one'     => ['cats' => [289],      'unit' => '1m²', 'unitM2' => 1,
                             'pu' => [0, 'TBD', 'TBD', 'TBD'], 'primer' => false, 'ral_ncs' => true],
        'easyline'       => ['cats' => [314],      'unit' => '4m²', 'unitM2' => 4,
                             'pu' => [0, 'TBD', 'TBD', 'TBD'], 'primer' => false, 'ral_ncs' => true],
        'metallic'       => ['cats' => [18],       'unit' => '4m²', 'unitM2' => 4,
                             'pu' => [0, 39.99, 79.99, 119.99], 'primer' => [0, 5.99], 'ral_ncs' => false],
        'lavasteen'      => ['cats' => [464],      'unit' => '5m²', 'unitM2' => 5,
                             'pu' => [0, 'TBD', 'TBD', 'TBD'], 'primer' => false, 'ral_ncs' => false],
        'betonlook-verf' => ['cats' => [],         'unit' => 'stuk', 'unitM2' => 0,
                             'pu' => false, 'primer' => 'TBD', 'ral_ncs' => false, 'product_id' => 11135],
        'stuco-paste'    => ['cats' => [457],      'unit' => 'stuk', 'unitM2' => 0,
                             'pu' => false, 'primer' => 'TBD', 'ral_ncs' => false],
        'pu-color'       => ['cats' => [456],      'unit' => 'stuk', 'unitM2' => 0,
                             'pu' => false, 'primer' => false, 'ral_ncs' => false],
    ];

    public static function detect($product) { /* match by category */ }
    public static function get_config($product) { /* return line config */ }
}
```

**TBD prices:** Extract from WAPO blocks before deactivation (Step 1.3).

**Test:** `OZ_Product_Line_Config::detect($product)` returns correct line for Microcement product.

### Step 1.3 — Extract WAPO Pricing Data
**Where:** SSH to BCW, query WAPO tables directly

Before we can replace WAPO, we need the exact prices for every line's PU/primer options.

1. Query WAPO blocks for exact PU prices per line:
   ```sql
   SELECT b.id, b.name, o.label, o.price
   FROM OTBgD_yith_wapo_blocks b
   JOIN OTBgD_yith_wapo_addons a ON a.block_id = b.id
   JOIN OTBgD_yith_wapo_addons_options o ON o.addon_id = a.id
   WHERE b.id IN (3, 34, 42, 44, 48, 30)
   ORDER BY b.id, o.id;
   ```
2. Record all prices in a spreadsheet or directly into Step 1.2 config.
3. Replace 'TBD' values in OZ_Product_Line_Config with real prices.

**Test:** All PU/primer prices match what the live site currently shows.

### Step 1.4 — Adapt OZ_Product_Processor for BCW Slugs
**Where:** `oz-variations/classes/class-oz-product-processor.php`

The epoxystone version uses 2 product line slug patterns. BCW has 9.

1. Add BCW slug patterns to `extract_color_from_product()`:
   - `microcement-*` (cat 455)
   - `beton-cire-original-*` (cat 290)
   - `all-in-one-kant-klaar-*` (cat 289)
   - `easyline-kant-klaar-*` (cat 314)
   - `metallic-velvet-4m2-pakket-*` (cat 18)
   - `lavasteen-gietvloer-*` (cat 464)
2. Add base product IDs: 11161, 11165, 11160, 11162, 22760, 27736
3. Keep existing `_oz_variants` meta logic — it works the same way.

**Test:** Run variant processor on a Microcement product → correct color extracted, variants linked.

### Step 1.5 — Adapt OZ_Cart_Manager for BCW Pricing
**Where:** `oz-variations/includes/cart/class-oz-cart-manager.php`

1. Replace hardcoded epoxystone PU/primer prices with `OZ_Product_Line_Config::get_config()`
2. In `woocommerce_before_calculate_totals` hook, detect product line and apply correct pricing
3. Remove calculator-related cart logic (room packages etc.)

**Test:** Add Microcement to cart with PU 2 lagen → price = €31 + €98 = €129. Matches WAPO.

---

## Phase 2: Product Page Template (Frontend)

### Step 2.1 — Create WooCommerce Template Override
**Where:** `OzTheme/woocommerce/single-product.php` (NEW file)

1. Create the WooCommerce template override directory:
   ```
   mkdir -p /home/betoncire/public_html/wp-content/themes/OzTheme/woocommerce/
   ```
2. Copy Flatsome's single-product.php as starting point:
   ```
   cp /home/betoncire/public_html/wp-content/themes/flatsome/woocommerce/single-product.php \
      /home/betoncire/public_html/wp-content/themes/OzTheme/woocommerce/single-product.php
   ```
3. Modify to match `productpagina-voorbeeld.html` layout:
   - Gallery (left column)
   - Product info + description (left, below gallery)
   - Options widget (right column) — color, PU, primer, tools
   - Delivery timeline, qty + CTA, price breakdown
4. Start with Microcement only — use `OZ_Product_Line_Config::detect()` to conditionally
   load new template vs. default Flatsome template for other lines.

**Test:** Visit a Microcement product → new layout renders. Other products → old layout.

### Step 2.2 — Port Frontend CSS
**Where:** `OzTheme/css/oz-product-page.css` (NEW file)

1. Extract CSS from `productpagina-voorbeeld.html` (lines 1-~1900)
2. Remove mockup-only styles (Google Fonts CDN link → use `wp_enqueue_style`)
3. Namespace all classes with `oz-` prefix (already done in mockup)
4. Enqueue in functions.php only on single product pages:
   ```php
   if (is_product()) {
       wp_enqueue_style('oz-product-page', get_stylesheet_directory_uri() . '/css/oz-product-page.css');
   }
   ```

**Test:** Microcement product page looks like the mockup.

### Step 2.3 — Port Frontend JS
**Where:** `OzTheme/js/oz-product-page.js` (NEW file)

1. Extract JS from `productpagina-voorbeeld.html` (lines 2437-3487)
2. Replace hardcoded CONFIG with `ozProduct` from `wp_localize_script`:
   ```javascript
   var CONFIG = Object.freeze({
       basePrice: ozProduct.basePrice,
       puPrices: Object.freeze(ozProduct.puOptions),
       primerPrice: ozProduct.primerPrice || 0,
       // ... etc from server-side config
   });
   ```
3. Replace mock `goToCart()` with real WooCommerce AJAX add-to-cart:
   ```javascript
   function goToCart() {
       var payload = buildCartPayload(S.color, S.colorMode, S.customColorCode,
           S.pu, S.primer, S.toolMode, S.tools, S.extras, S.qty);
       // POST to WooCommerce add-to-cart endpoint
       var formData = new FormData();
       formData.append('product_id', ozProduct.productId);
       formData.append('quantity', payload.qty);
       formData.append('oz_pu', payload.pu);
       formData.append('oz_primer', payload.primer);
       // ... tools, extras as cart item data
       fetch(wc_add_to_cart_params.wc_ajax_url.replace('%%endpoint%%', 'add_to_cart'), {
           method: 'POST', body: formData
       }).then(function(r) { return r.json(); })
         .then(function(data) { /* open cart drawer or redirect */ });
   }
   ```
4. Replace mock `selectColor()` URL navigation with real product URL navigation:
   ```javascript
   function selectColor(name) {
       // In production: navigate to the variant product URL
       var variants = ozProduct.variants; // from _oz_variants meta
       var targetUrl = variants[name];
       if (targetUrl) window.location = targetUrl;
   }
   ```
5. Enqueue with `wp_localize_script` data:
   ```php
   wp_enqueue_script('oz-product-page', get_stylesheet_directory_uri() . '/js/oz-product-page.js',
       [], '1.0', true);
   wp_localize_script('oz-product-page', 'ozProduct', [
       'productId'    => $product->get_id(),
       'basePrice'    => $product->get_price(),
       'productLine'  => $line,
       'puOptions'    => $config['pu'],
       'primerPrice'  => $config['primer'] ? $config['primer'][1] : 0,
       'hasPrimer'    => (bool)$config['primer'],
       'hasRalNcs'    => (bool)$config['ral_ncs'],
       'unitM2'       => $config['unitM2'],
       'unit'         => $config['unit'],
       'variants'     => get_post_meta($product->get_id(), '_oz_variants', true) ?: [],
       'tools'        => $tools_data,
       'toolSet'      => $tool_set_data,
       'extras'       => $extras_data,
   ]);
   ```

**Test:** Microcement product page → select PU → price updates → click "In winkelmand" → product added to WooCommerce cart with correct PU addon pricing.

### Step 2.4 — Color Swatches from _oz_variants Meta
**Where:** Template + JS

1. In template, render color swatches from `_oz_variants` meta (same approach as epoxystone)
2. Each swatch links to the variant product URL
3. The currently active product is highlighted

**Test:** Click a different color swatch → navigates to that product's URL → page shows that color selected.

### Step 2.5 — Mobile Bottom Sheet
**Where:** Already in CSS/JS from mockup

The bottom sheet is already built in the mockup. Just verify it works in the WooCommerce context:
1. Sticky bar appears on mobile when options are out of view
2. Tapping "In winkelmand" on sticky bar opens bottom sheet
3. Options widget moves into sheet (single DOM)
4. CTA in sheet triggers addToCart()

**Test:** Mobile viewport → scroll down → sticky bar appears → tap → sheet opens → select options → add to cart works.

---

## Phase 2b: Roll Out to Other Lines

### Step 2.6 — Original (most complex)
1. Add Original-specific options: Colorfresh toggle, Toepassing dropdown, Pakket selector
2. Primer options: Zuigend / Niet-zuigend / Geen
3. PU prices: €0 / €40 / €80 / €120
4. Test all 6 option sections render correctly

### Step 2.7 — Metallic Velvet
1. Primer: Geen / Primer €5,99
2. PU: Geen / 1-3 lagen with Metallic-specific pricing
3. No RAL/NCS

### Step 2.8 — All-In-One + Easyline
1. Enable RAL/NCS color mode selector (hasRalNcs = true)
2. Custom color input with validation
3. PU prices from WAPO extraction

### Step 2.9 — Lavasteen
1. PU only, no primer
2. Different tool set: Gereedschapset Lavasteen (25550, €115.95)

### Step 2.10 — Single-Product Lines (Betonlook Verf, Stuco Paste, PU Color)
1. No color swatches (no variants)
2. Betonlook Verf: internal color option + primer
3. Stuco Paste: primer only
4. PU Color: standalone, no options

---

## Phase 3: Cart Drawer

### Step 3.1 — Cart Drawer Plugin Shell
**Where:** `/home/betoncire/public_html/wp-content/plugins/oz-cart-drawer/` (NEW plugin)

1. Create plugin structure:
   ```
   oz-cart-drawer/
     oz-cart-drawer.php
     includes/class-cart-handler.php
     assets/css/oz-cart-drawer.css
     assets/js/oz-cart-drawer.js
     templates/cart-drawer.php
   ```
2. Register AJAX endpoints: `oz_cart_update_qty`, `oz_cart_remove_item`, `oz_cart_get_content`
3. Inject drawer HTML via `wp_footer` hook
4. Port CSS/JS from `demo-site/cart-drawer.html` mockup

### Step 3.2 — Cart Drawer Integration
1. Hook into Flatsome's `added_to_cart` jQuery event → open drawer
2. AJAX qty update / remove without page reload
3. Free shipping progress bar (€150 threshold)
4. Display PU/primer addon details from OZ_Cart_Manager cart item data

### Step 3.3 — Cart Drawer Upsell Section
1. "Vakmannen bestellen ook" section in drawer
2. Use resolveUpsells() from TECHNICAL-SPEC section 5.5
3. No duplicates with items already in cart
4. One-click add from drawer

---

## Phase 4: Cleanup + Launch

### Step 4.1 — Deactivate YITH WAPO
1. Verify ALL product lines work correctly with oz-variations
2. Deactivate YITH WAPO plugin (do NOT delete — keep as fallback)
3. Monitor for any pricing discrepancies

### Step 4.2 — Fix Data Issues
1. Add missing cross-sells on Metallic Velvet (only 1/13 has them)
2. Add Gereedschapset Lavasteen cross-sell to 4 older Lavasteen products
3. Fix WAPO block 38 targeting (if still relevant)
4. Remove orphaned WAPO blocks 7, 46, 47

### Step 4.3 — Clean Up OzTheme
1. Remove dead m² calculator code from functions.php
2. Remove oz-scripts.js color navigation (replaced by oz-variations)
3. Remove legacy taxonomy pa_ruimte (singular)
4. Remove unused attributes: pa_standaard-kleuren, pa_extra-pu-toplaag, pa_m2

### Step 4.4 — Verify & Monitor
1. Test all 9 product lines on live site
2. Check pricing matches pre-migration values exactly
3. Monitor conversion metrics for 2 weeks
4. Keep WAPO tables in DB as backup (do not drop)

---

## Quick Reference: What Goes Where

| What | Where |
|---|---|
| Product line config | `oz-variations/includes/class-oz-product-line-config.php` |
| Variant detection + linking | `oz-variations/classes/class-oz-product-processor.php` |
| PU/primer cart pricing | `oz-variations/includes/cart/class-oz-cart-manager.php` |
| Product page template | `OzTheme/woocommerce/single-product.php` |
| Product page CSS | `OzTheme/css/oz-product-page.css` |
| Product page JS | `OzTheme/js/oz-product-page.js` |
| Cart drawer plugin | `wp-content/plugins/oz-cart-drawer/` |
| Design reference | `productpagina-voorbeeld.html` (repo: beton-cire-webshop) |

---

## Current Status

- [x] Mockup complete (productpagina-voorbeeld.html)
- [x] Cart drawer mockup complete (demo-site/cart-drawer.html)
- [x] PROJECT-SCOPE.md written
- [x] TECHNICAL-SPEC.md written
- [ ] **Step 1.1** — Fork oz-variations to BCW ← START HERE
