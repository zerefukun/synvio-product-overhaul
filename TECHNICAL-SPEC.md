# Beton Cire Webshop -- Technical Specification
**Version 2.2 -- 6 maart 2026** (v2.2: RAL/NCS color modes, set-first tool UX, event delegation, REFS consolidation, buildToolRow DRY)

---

## 1. Architecture

### 1.1 Foundation: oz-variations Plugin

The overhaul is based on **oz-variations** (v2.1.0), a plugin we built for epoxystone-gietvloer.nl.
It replaces YITH WAPO entirely. No third-party addon plugin dependency.

**Current epoxystone capabilities (proven, deployed):**
- Color variant detection from product slugs (regex-based via OZ_Product_Processor)
- Bidirectional variant linking via `_oz_variants` post meta
- Base product redirect to most-sold color variant (by total_sales)
- Color swatches on product pages (auto-display or `[oz_product_variants]` shortcode)
- PU layer pricing added to cart (stored as cart item data, priced via woocommerce_before_calculate_totals)
- Primer add-on handling in cart
- Color palette builder for kleurstalen products
- Room-based m2 calculator with multi-room/multi-color support (5m2 packages)
- 12-language translation system via `lang([])` helper
- Admin settings page under WooCommerce

**What needs to change for BCW:**
- Expand from 2 product types to 9 product lines
- Product-line-specific PU/primer options and pricing
- Different package sizes per line (5m2, 4m2, 1m2, per stuk)
- RAL/NCS custom color input (All-In-One + Easyline)
- Cart drawer (new component, replaces standard WooCommerce cart page interaction)
- Upsell system (new component)
- Translation: Dutch-only (simplify, remove 12-language overhead)
- Integration with Flatsome theme (epoxystone uses a different theme)

### 1.2 Principles

- **Functional Core / Imperative Shell (FCIS)** -- Pure functions for business logic, thin renderers for DOM
- **Single state object** -- One S = {} with syncUI() as sole composition point
- **Parameterized renderers** -- Every renderer takes explicit params, no global state reads
- **addEventListener only** -- Zero inline onclick strings
- **Incremental DOM** -- Reuse existing nodes by key, do not destroy/recreate
- **No jQuery in core logic** -- Vanilla JS for all cart drawer/product page code. jQuery used ONLY for Flatsome event interop (e.g. added_to_cart hook) since the live site already loads jQuery

### 1.3 Stack

- WooCommerce 10.4.3 on WordPress
- Flatsome theme (parent) + OzTheme (child)
- **oz-variations** (ported from epoxystone, replaces YITH WAPO)
- variation-price-display 1.4.0
- keuzehulp-editor-v8
- Yoast Premium for SEO/schema
- DB prefix: OTBgD_

### 1.4 Components to Port from Epoxystone

| Component | Port Strategy |
|---|---|
| OZ_Product_Processor | Port as-is. Add product-line detection for 9 BCW lines |
| OZ_Frontend_Display | Major rewrite. Match productpagina-voorbeeld.html mockup |
| OZ_Cart_Manager | Adapt for cart drawer. PU/primer pricing logic reusable as-is |
| OZ_Ajax_Handlers | Extend for cart drawer AJAX + upsell endpoints |
| OZ_Calculator | Do NOT port. Patrick does not want a calculator on BCW |
| OZ_Calculator_Display | Do NOT port (depends on OZ_Calculator) |
| OZ_Color_Mapping | Port as-is. Add BCW-specific colors |
| OZ_Admin | Extend with per-product-line config |
| OZ_Translation | Simplify to Dutch-only for BCW |
| Color Palette Interface | Port if BCW has kleurstalen products |

### 1.5 New Components (not in epoxystone)

| Component | Purpose |
|---|---|
| OZ_Cart_Drawer | Slide-from-right cart drawer with AJAX updates |
| OZ_Upsell_Engine | Cross-sell suggestions based on project-completion rules |
| OZ_Product_Line_Config | Per-line configuration (PU options, primer options, pricing, package size) |

---

## 2. Product Catalog Reference

### 2.1 Product Lines -- Complete Matrix

| Line | Cat ID | Unit | Price | Total in cat | Color PDPs | Base product | Current WAPO Blocks |
|---|---|---|---|---|---|---|---|
| Original | 290 | 5m2 pakket | EUR 90 | 51 | 50 | 11161 (in cat) | 4,6,11,26,31,42,45 |
| All-In-One K&K | 289 | 1m2 emmer | EUR 28 | 41 | 40 | 11165 (in cat) | 34,37 |
| Easyline K&K | 314 | 4m2 pakket | EUR 170 | 41 | 40 | 11160 (in cat) | 3,12 |
| Metallic Velvet | 18 | 4m2 pakket | EUR 120 | 13 | 12 | 11162 (in cat) | 5,29,30 |
| Microcement | 455,463 | per stuk (1m2) | EUR 31 | 36+1 | 36 (cat 455) | 22760 (cat 463) | 38*,43,44 |
| Lavasteen | 464 | 5m2 pakket | EUR 235 | 21 | 20 | 27736 (in cat) | 41,48 |
| Betonlook Verf | -- | per stuk | EUR 29 | 1 | 0 (no variants) | 11135 | 10 |
| Stuco Paste | 457 | per stuk | EUR 59.95 | 1 | 0 (no variants) | 22436 | 39 |
| PU Color | 456 | per stuk | EUR 95 | 1 | 0 (no variants) | 11004 | 33 |

*Block 38 has no targeting -- needs fix

**Product runtime model:**
- **Generic/base products** (e.g. 11161, 11165, 22760) are landing pages. oz-variations detects these via `is_base_product()` and redirects to the most-sold color variant. They are not directly purchasable PDPs.
- **Color products** are the actual configurable PDPs. Each has its own URL, price, and receives oz-variations option UI (PU, primer, etc.) based on its product line.
- oz-variations config sits behind this: it detects the product line from the color product's category and renders the correct options.

**m2 calculator flag (_enable_m2):** Only 2 specific generic/base products have this flag:
- 22760 (Microcement Performance) -- _enable_m2 = 1
- 11165 (All-In-One Kant & Klaar) -- _enable_m2 = 1

This flag does NOT apply to the color product families in these lines. The calculator
is triggered on the base product page before redirect, or must be explicitly shown
on color PDPs via oz-variations config. The "1m2 emmer" unit for All-In-One and
"per stuk (1m2)" unit for Microcement describe the package size, not the m2 flag.

### 2.2 Addon Matrix (data to extract from WAPO before deactivation)

| Line | PU Toplagen | Primer | Other |
|---|---|---|---|
| Original | 1-3 + geen (B42) | zuigend/niet-zuigend/geen (B31) | Colorfresh (B6), Toepassing (B26), Pakket (B45) |
| All-In-One K&K | 1-3 (B34,B37) | -- | Standaard/RAL/NCS color selector + text input |
| Easyline K&K | 1-3 (B3,B12) | -- | Pakket (B3,B12), Standaard/RAL/NCS color selector + text input |
| Metallic Velvet | Geen/1/2/3 lagen (B30) | Geen/Primer EUR 5.99 (B5) | Kleuren (B29) |
| Microcement | 1-3 (B44) | -- | RAL (B43), Extras: Flexibele spaan + Kwast (in WAPO, not just cross-sells) |
| Lavasteen | yes (B48) | -- | -- |
| Betonlook Verf | -- | yes (B10) | -- |
| Stuco Paste | -- | yes (B39) | -- |

### 2.2b Live Frontend Option Order (verified from product pages)

Each product line shows options in a specific order on the live site.
The new template must preserve this order:

| Line | Option Order (top to bottom) |
|---|---|
| Original | Kleurstalen > Pakket > Kleur > Toepassing > Primer > Colorfresh > PU toplagen |
| All-In-One K&K | Kleurstalen > Kleur > PU Toplagen (with recommendations: "2 lagen aanbevolen") |
| Easyline K&K | Kleurstalen > Kleur > Pakket > PU Toplagen |
| Metallic Velvet | Kleurstalen > Lagen PU > Kleur > Primer |
| Microcement | Kleurstalen > Kleur > PU Toplagen (with recommendations) |
| Lavasteen | Kleur > PU toplagen |
| Betonlook Verf | Kleurstalen > Kleur (internal option, not URL variant) > Primer |
| Stuco Paste | Primer |

Notes:
- "Kleurstalen aanvragen" link appears on most product lines (WAPO block 35)
- All-In-One and Microcement show PU recommendation text ("2 lagen aanbevolen", "Aanbevolen keuze!", etc.)
- Original has the most options (6 configurable sections)
- Stuco Paste is simplest (primer only, no color/PU)
- Lavasteen has no "kleurstalen aanvragen" link

### 2.3 PU Pricing (verified from live site)

| Line | Geen | 1 laag | 2 lagen | 3 lagen |
|---|---|---|---|---|
| Original | EUR 0 | EUR 40 | EUR 80 | EUR 120 |
| Metallic Velvet | EUR 0 | EUR 39.99 | EUR 79.99 | EUR 119.99 |
| Other lines | TBD -- extract from WAPO blocks before migration | | | |

### 2.4 Primer Pricing (verified from live site)

| Line | Options |
|---|---|
| Original | Geen / Zuigend EUR 12.50 / Niet-zuigend EUR 12.50 |
| Metallic Velvet | Geen / Primer EUR 5.99 |
| Betonlook Verf | Own primer options (TBD from WAPO block 10) |
| Stuco Paste | Own primer options (TBD from WAPO block 39) |
| Others | No primer |

### 2.5 Cross-sell Patterns

| Product Type | Cross-sells |
|---|---|
| Original colors | Flexibele spaan (11025), Gereedschapset Mengen (11163), PU roller (11175) |
| All-In-One K&K | Flexibele spaan (11025), Gereedschapset K&K (11177), PU roller (11175) |
| Easyline K&K | Flexibele spaan (11025), Gereedschapset K&K (11177), PU roller (11175) |
| Metallic Velvet | Flexibele spaan (11025) -- only 1/13 has cross-sells! |
| Microcement | Flexibele spaan (11025), Gereedschapset K&K (11177), PU roller (11175) |
| Lavasteen (new) | Flexibele spaan (11025), Gereedschapset Lavasteen (25550), PU roller (11175) |
| Lavasteen (old 4) | Flexibele spaan (11025), PU roller (11175) -- MISSING Gereedschapset |
| Betonlook Verf | Blokkwast (22997), Effect Kwast (22996), Kwast (11022), Vachtroller (11015) |
| Stuco Paste | Flexibele spaan (11025), Structuur roller (22994) |

### 2.6 Key Product IDs

**Generic/parent products:**

| ID | Name |
|---|---|
| 11161 | Beton Cire Original 5m2 |
| 11165 | All-In-One Kant & Klaar |
| 11160 | Easyline Kant & Klaar |
| 11162 | Metallic Stuc Velvet |
| 22760 | Microcement Performance |
| 27736 | Lavasteen gietvloer |
| 11135 | Betonlook Verf |
| 22436 | Stuco Paste |
| 11004 | PU Color |

**Commonly cross-sold tools:**

| ID | Name | Price |
|---|---|---|
| 11025 | Flexibele spaan | EUR 39.95 |
| 11175 | PU roller | EUR 2.50 |
| 11177 | Gereedschapset K&K | EUR 89.99 |
| 11163 | Gereedschapset Zelf Mengen | EUR 119.99 |
| 25550 | Gereedschapset Lavasteen | EUR 115.95 |
| 22997 | Blokkwast | EUR 6.99 |
| 22996 | Effect Kwast hout | EUR 16.99 |
| 22994 | Structuur roller 8cm | EUR 16.99 |
| 11015 | Vachtroller 25cm | EUR 8.95 |
| 11022 | Kwast | EUR 1.99 |

### 2.7 Room Pages

**Live published pages** (verified on site):

| Page Slug | Key Products |
|---|---|
| beton-cire-badkamer | Microcement, Original, Easyline, All-In-One, Lavasteen, tool sets |
| beton-cire-keuken | Microcement, Original, Easyline, All-In-One, tool sets |
| beton-cire-trappen | Microcement, Original, Easyline, All-In-One, tool sets |
| beton-cire-woonkamer | Microcement, Original, Easyline, All-In-One, tool sets |
| beton-cire-toilet | Microcement, Original, Easyline, All-In-One, tool sets |
| beton-cire-vloer | Microcement, Original, Easyline, All-In-One, Lavasteen, tool sets |
| beton-cire-wand | Microcement, Original, Easyline, All-In-One, Metallic Velvet, tool sets |

**Additional application/topic page** (not a room page):

| Page Slug | Notes |
|---|---|
| over-tegels | Application page, not room-specific |

NOT live: Meubel, Kantoor.

**pa_ruimtes taxonomy** (6 terms, 89 products each): Badkamer, Keuken, Meubel, Trap, Vloer, Wand.
Legacy duplicate taxonomy pa_ruimte (singular) exists but is unused.

Note: Room pages currently reuse mostly the same product set. Not strongly room-specific yet.

---

## 3. WAPO Migration Strategy

### 3.1 Approach: Replace, Not Integrate

YITH WAPO is being **replaced** by oz-variations. We are NOT keeping WAPO for backend pricing.
oz-variations already handles PU/primer pricing via cart item data + woocommerce_before_calculate_totals.

**Migration steps:**
1. Extract all WAPO pricing data (PU prices, primer prices per product line)
2. Store pricing in oz-variations product-line config (wp_options or per-line arrays)
3. Build the product page UI to render options natively via oz-variations frontend
4. Test that add-to-cart produces identical cart items + pricing as current WAPO setup
5. Deactivate YITH WAPO plugin
6. Keep WAPO data in DB as backup (do not delete tables)

### 3.2 Complete WAPO Block Inventory (extraction checklist)

This list must match every block referenced in section 2.1. Use this as the
extraction checklist -- if a block is missing here, its data will be lost.

| Block ID | Name | Line | Target | Status |
|---|---|---|---|---|
| 3 | Extra PU toplaag | Easyline | cat 314 | Active |
| 4 | (addon) | Original | cat 290 | Active |
| 5 | Metallic Primer | Metallic Velvet | specific Metallic products (product-level) | Active |
| 6 | Colorfresh | Original | cat 290 | Active |
| 7 | (unknown) | -- | Deleted category 459 | ORPHANED |
| 10 | Primer | Betonlook Verf | specific product | Active |
| 11 | (addon) | Original | cat 290 | Active |
| 12 | (addon) | Easyline | cat 314 | Active |
| 26 | Toepassing | Original | cat 290 | Active |
| 29 | Metallic Kleuren | Metallic Velvet | cat 18 | Active |
| 30 | Metallic PU | Metallic Velvet | specific Metallic products incl. Oro 28196 | Active |
| 31 | Primer | Original | cat 290 | Active |
| 33 | (addon) | PU Color | cat 456 | Active |
| 34 | PU toplagen | All-In-One | cat 289 | Active |
| 35 | Kleurstalen Aanvragen | Multiple lines | most product lines (see 2.2b notes) | Active |
| 37 | (addon) | All-In-One | cat 289 | Active |
| 38 | Microcement Kleuren | Microcement | No targeting configured | BROKEN |
| 39 | Primer | Stuco Paste | cat 457 | Active |
| 41 | (addon) | Lavasteen | cat 464 | Active |
| 42 | PU toplagen | Original | cat 290 | Active |
| 43 | RAL | Microcement | cat 455,463 | Active |
| 44 | PU toplagen | Microcement | cat 455,463 | Active |
| 45 | Pakket | Original | cat 290 | Active |
| 46 | Extras | -- | Disabled | DISABLED |
| 47 | (unknown) | -- | Deleted category 459 | ORPHANED |
| 48 | PU toplagen | Lavasteen | cat 464 | Active |

Note: Block names marked (addon) need exact label verification during extraction.
Blocks 7, 46, 47 are non-functional and can be skipped during extraction.

### 3.3 WAPO Data to Extract Before Deactivation

For each active block, extract:
- Option labels (Dutch)
- Option prices (exact values)
- Product/category targeting rules
- Option type (radio, checkbox, select)

Store in structured format for oz-variations OZ_Product_Line_Config.

---

## 4. Color Navigation System

### 4.1 Current: oz-scripts.js (OzTheme child theme)

Each color = separate WooCommerce simple product with own URL.
oz-scripts.js handles color swatch clicks by navigating to the correct URL.

Key data structures in oz-scripts.js:
- **baseSlugs**: product line slug prefixes (beton-cire-original-*, microcement-*, etc.)
- **exactSlugMap**: exceptions where color name doesnt match slug pattern
- **slugOverrides**: color name to slug suffix mappings (e.g. ros -> rose)

### 4.2 Future: oz-variations handles this natively

oz-variations already has this capability via OZ_Product_Processor:
- `extract_color_from_product()` extracts color from slug using regex
- `_oz_variants` meta stores bidirectional variant links
- `find_most_popular_variant()` uses total_sales to find redirect target
- `is_base_product()` detects landing pages (no color in name)
- Frontend display renders clickable swatches that navigate to variant URLs

**Migration**: oz-scripts.js color navigation code becomes redundant once oz-variations
handles the same via `_oz_variants` meta. The baseSlugs/exactSlugMap/slugOverrides
data needs to be incorporated into OZ_Product_Processor for BCW-specific slug patterns.

---

## 5. Cart Drawer -- Technical Design (New Component)

### 5.1 State

```javascript
var S = {
    open: false,        // drawer visibility
    items: [],          // [{ key, productId, name, price, qty, image, cat }]
    updating: null,     // cartKey currently being AJAX-updated
    lineConfig: {},     // per-line project-completion rules (from server)
    crossSells: [],     // WooCommerce cross-sell IDs for cart products (from server)
    orderHistory: {}    // co-purchase frequency cache (from server)
};
```

### 5.2 Pure Functions

| Function | Signature | Returns |
|---|---|---|
| calculateSubtotal | (items) | number |
| countItems | (items) | number |
| freeShippingProgress | (subtotal, threshold) | { percent, remaining, reached } |
| resolveUpsells | (items, lineConfig, crossSells, orderHistory) | [{ id, name, price, cat }] |
| catalogById | (catalog, id) | product or undefined |
| findCartItem | (items, productId) | item or undefined |
| clamp | (val, min, max) | number |
| fmt | (n) | formatted price string |

### 5.3 Renderers (all take explicit params)

| Renderer | Params | Updates |
|---|---|---|
| renderBadge | (count) | header cart icon badge |
| renderShippingBar | (progress) | free shipping progress bar |
| renderCartItems | (items, updatingKey) | incremental DOM by cartKey |
| renderUpsells | (upsells) | suggestion cards |
| renderEmptyState | (isEmpty) | empty cart message |
| renderDrawerState | (open) | open/close + overlay + scroll lock |
| renderFooter | (subtotal) | subtotal + checkout button |

### 5.4 syncUI Composition

```javascript
function syncUI() {
    var sub = calculateSubtotal(S.items);
    var count = countItems(S.items);
    var progress = freeShippingProgress(sub, CONFIG.freeShipThreshold);
    var upsells = resolveUpsells(S.items, S.lineConfig, S.crossSells, S.orderHistory);

    renderBadge(count);
    renderShippingBar(progress);
    renderCartItems(S.items, S.updating);
    renderUpsells(upsells);
    renderEmptyState(S.items.length === 0);
    renderDrawerState(S.open);
    renderFooter(sub);
}
```

### 5.5 Upsell Resolution (explicit priority order)

The cart drawer resolveUpsells() function uses this hierarchy:

1. **Project-completion rules first** -- per-line family rules determine which product
   categories are eligible (e.g. Original in cart -> PU, Primer, tools are eligible).
   These rules live in OZ_Product_Line_Config, not hardcoded in JS.

2. **WooCommerce cross-sell IDs for ranking** -- among eligible categories, use the
   actual cross-sell IDs from the products in cart to pick specific products.
   This is the per-product data source (read from Woo, never hardcoded).

3. **Order history for tiebreaking** -- if multiple eligible products exist, rank by
   co-purchase frequency from cached order data.

4. **Fallback defaults** -- only if steps 1-3 produce zero results (e.g. product has
   no cross-sells set and no order history), fall back to safe defaults per line.

```javascript
var CONFIG = Object.freeze({
    freeShipThreshold: 150,
    maxSuggestions: 3
    // upsell rules come from ozProduct.lineConfig (server-side),
    // cross-sells from ozProduct.crossSells (per-product Woo data),
    // NOT hardcoded here
});
```

Important: BCW's cross-sell coverage is incomplete (Metallic Velvet: only 1/13 has
cross-sells). Phase 3 must fix missing cross-sell IDs before the upsell system
can rely on them fully. Until then, project-completion rules carry more weight.

### 5.6 WooCommerce Integration

**Plugin structure:**
```
oz-cart-drawer/
  oz-cart-drawer.php          // plugin bootstrap
  includes/
    class-cart-handler.php    // AJAX endpoints
  templates/
    cart-drawer.php           // drawer HTML
  assets/
    css/oz-cart-drawer.css
    js/oz-cart-drawer.js
```

**AJAX endpoints** (wp_ajax + wp_ajax_nopriv):

| Action | Method | Params | Returns |
|---|---|---|---|
| oz_cart_update_qty | POST | cart_key, quantity, nonce | cart_count, subtotal, total |
| oz_cart_remove_item | POST | cart_key, nonce | cart_count, cart_empty, subtotal, total |
| oz_cart_get_content | POST | nonce | html, count, subtotal |

**Flatsome hook:**
```javascript
// Listen for Flatsome add-to-cart success
jQuery(document.body).on("added_to_cart", function(e, fragments, hash, $button) {
    // Fetch fresh cart data via oz_cart_get_content
    // Open drawer: S.open = true; syncUI();
});
```

### 5.7 Integration with OZ_Cart_Manager

OZ_Cart_Manager from oz-variations already handles:
- PU layer pricing in cart items
- Primer add-on pricing
- Calculator package pricing
- Palette grouping

The cart drawer must read these cart item data fields to display correct prices.
No duplicate pricing logic -- drawer reads what OZ_Cart_Manager already calculated.

---

## 6. Product Page -- Technical Design

### 6.1 State

```javascript
var S = {
    color: "Cement 3",
    colorMode: "standard",   // "standard" | "ral" | "ncs"
    customColorCode: "",     // typed RAL/NCS code (e.g. "RAL 7016")
    pu: 0,                   // 0=geen, 1-3=lagen
    primer: 0,               // 0=geen, 1=yes
    toolMode: "none",        // "none" | "set" | "individual"
    tools: {},               // per-tool: { on: false, qty: 0 } — for "individual" mode
    extras: {},              // per-extra: { on: false, qty: 0 } — add-ons on top of "set" mode
    qty: 1,
    upsellOpen: false,
    upsellChoice: "none"
};
```

### 6.1b Color Modes (RAL/NCS — All-In-One + Easyline lines)

Three color modes above the swatch grid, using `oz-option-label-btn` styling:
- **Standaard kleur** (default): shows color swatches, clicking navigates to color product URL
- **RAL kleur**: hides swatches, shows text input with live validation (`/^RAL\s?\d{4}$/i`)
- **NCS kleur**: hides swatches, shows text input with live validation (`/^NCS\s+S\s+\d{4}-[A-Z]\d{2}[A-Z]$/i`)

Both custom modes show partial validation hints as the user types (format guide appears below input).
Info text: "Wij mengen uw kleur op maat. Levertijd +2 werkdagen."

Color mode widget is built once by `buildColorMode()` and stored in `REFS.colorMode`.
It lives inside `#optionsWidget` and moves with it between desktop and sheet.

### 6.1c Tool Modes (Set-First UX)

Three tool modes, using `oz-tool-mode-btn` styling:
- **Geen** (default): no tools
- **Kant & Klaar** (+€89,99): complete set (Gereedschapset K&K, WC ID 11177).
  Shows set contents + extras section (consumables like PU Roller, Verfbak, Tape).
  Smart nudge appears when qty >= threshold and no extra PU rollers added.
- **Zelf samenstellen**: individual tool checklist for professionals.
  Each tool has checkbox + qty controls.

Tool section is built once by `buildToolSectionV2()` using shared `buildToolRow()` function.
`buildToolRow(item, dataAttr, onToggle, onQtyDec, onQtyInc, onQtyEdit)` eliminates
duplication between extras list and individual tools list.

**Upsell modal**: When user clicks "In winkelmand" with toolMode="none", a modal offers
the Kant & Klaar set. "Zelf samenstellen" with 0 tools scrolls to the tool section instead.

### 6.2 oz-variations Frontend Strategy

oz-variations replaces WAPO frontend entirely. The product page template:

1. Detects product line via OZ_Product_Line_Config
2. Reads PU/primer options + pricing from config (not WAPO blocks)
3. Renders option UI matching productpagina-voorbeeld.html mockup
4. On add-to-cart, stores PU/primer as cart item data (same as epoxystone)
5. OZ_Cart_Manager prices the item via woocommerce_before_calculate_totals

```php
// Output product-line config via wp_localize_script
wp_localize_script("oz-product-page", "ozProduct", array(
    "basePrice"   => $product->get_price(),
    "productLine" => OZ_Product_Line_Config::detect($product),
    "puOptions"   => OZ_Product_Line_Config::get_pu_options($product),
    "primerOptions" => OZ_Product_Line_Config::get_primer_options($product),
    "crossSells"  => $cross_sell_data,
    "tools"       => $available_tools,
    "variants"    => get_post_meta($product->get_id(), '_oz_variants', true)
));
```

### 6.3 DOM Architecture — Single DOM, Event Delegation

**Single DOM pattern**: One `#optionsWidget` div contains all options (color mode, swatches,
PU, primer, tools, m2 advice). It physically moves between `#optionsSlotDesktop` (main page)
and `#optionsSlotSheet` (bottom sheet) via `openSheet()`/`closeSheet()`. Zero duplication.

**REFS object**: All DOM references collected once at init in a centralized object:

```javascript
var REFS = {
    main:           breakdownRefs('price'),     // price breakdown lines
    sheet:          breakdownRefs('sheetPrice'), // sheet price breakdown
    upsell:         { overlay, modal, addBtn, skipBtn },
    sticky:         { bar, price, unit, colorName, btn },
    sheet2:         { overlay, panel, slot, ctaBtn, colorName },
    color:          { selectedValue, label },
    gallery:        { mainImg },
    qty:            { input, m2Note },
    addToCartBtn, readMoreBtn, optionsWidget, desktopSlot,
    colorMode: null  // set by buildColorMode()
};
```

**Event delegation**: Zero inline `onclick` handlers. All events bound via `addEventListener`
in a centralized `bindEvents()` IIFE at init. Uses data attributes for delegation:

| Data Attribute | Element | Handler |
|---|---|---|
| `data-thumb-src` | Gallery thumbnails | `switchImage()` |
| `data-color` | Color swatches | `selectColor()` |
| `data-pu` | PU option buttons | `selectPu()` |
| `data-primer` | Primer buttons | `selectPrimer()` |
| `data-info-target` | Info tooltip buttons | `toggleInfo()` |
| `data-qty-delta` | Qty +/- buttons | `changeQty()` |
| `data-mode` | Tool mode buttons | `setToolMode()` |
| `data-colormode` | Color mode buttons | `setColorMode()` |

**Extracted renderers** (keeping handlers thin):
- `renderColorValidationError()` — focus + mark invalid on custom color input
- `buildToolRow()` — shared tool row builder for extras and individual lists

---

## 7. Upsell System -- Technical Design (New Component)

### 7.1 Priority Model (matches section 5.5)

Four steps, in strict order:

1. **Project-completion rules** (eligibility filter)
   - Per-line family rules determine which product categories are eligible
   - E.g., Original in cart -> PU, Primer, tools are eligible categories
   - Rules defined per product line in OZ_Product_Line_Config

2. **WooCommerce cross-sell IDs** (product selection)
   - Among eligible categories, use cross-sell IDs from cart products to pick specifics
   - Per-product data, read from Woo, never hardcoded

3. **Order history** (tiebreaking)
   - If multiple eligible products remain, rank by co-purchase frequency
   - SQL: orders containing product X, what else was in the order?
   - Cache results in wp_options (recalculate weekly or on-demand)

4. **Fallback defaults** (last resort)
   - Only when steps 1-3 produce zero results (e.g. no cross-sells set, no order data)
   - Safe per-line defaults defined in OZ_Product_Line_Config

### 7.2 When to Show Upsells

- **Product page**: Tool suggestion modal when adding a purchasable PDP (color PDP or single-product line) without tools
- **Cart drawer**: "Vakmannen bestellen ook" section, adapts to cart contents
- **No duplicates**: Never suggest whats already in cart

---

## 8. Design System Tokens

```css
/* Colors */
--accent: #135350;           --accent-hover: #0E3E3C;
--accent-text: #FFFFFF;      --accent-light: #E8F0F0;
--cta: #E67C00;              --cta-hover: #D06E00;
--cta-text: #FFFFFF;
--text-primary: #1A1A1A;     --text-body: #555555;
--text-muted: #999999;
--border: #E5E5E3;           --border-light: #F0EFED;
--bg-page: #FFFFFF;          --bg-subtle: #FAFAF9;
--bg-warm: #F5F4F0;
--label-bg: #F7F7F5;         --label-border: #E0DFDD;

/* Typography */
--font-heading: "DM Serif Display", serif;
--font-body: "Raleway", sans-serif;

/* Shadows */
--shadow-sm: 0 1px 3px rgba(0,0,0,.06);
--shadow-md: 0 4px 12px rgba(0,0,0,.08);
--shadow-lg: 0 8px 30px rgba(0,0,0,.12);

/* Motion */
--ease-out: cubic-bezier(.22,1,.36,1);
--ease-smooth: cubic-bezier(.4,0,.2,1);

/* Sizing */
--radius-sm: 6px;   --radius-md: 10px;   --radius-lg: 14px;
--drawer-width: 430px;
```

**Button hierarchy:**
- Primary: bg=var(--cta), color=white, uppercase, 600 weight
- Secondary: border=var(--accent), color=var(--accent), transparent bg
- Tertiary: text-only, color=var(--accent), underline on hover

---

## 9. Migration Notes

### 9.1 What stays
- WooCommerce cross-sell IDs (data source for upsells)
- Product URLs (critical for SEO -- one URL per color)
- Room page taxonomy (pa_ruimtes)
- Flatsome theme (parent theme stays)
- OzTheme child theme (will get template override)

### 9.1b Current architecture (no template overrides)
- OzTheme has NO custom WooCommerce single-product template override
- Current product page behavior is layered through oz-scripts.js, theme CSS, and YITH WAPO
- oz-scripts.js handles URL-based color navigation (maps YITH color choices to product URLs)
- This is our first custom product template -- there is nothing to "replace", only to add

### 9.2 What changes
- **YITH WAPO deactivated** -- replaced by oz-variations for all addon logic
- Product page frontend (oz-variations renders options, not WAPO)
- Cart interaction (page reload replaced by drawer with AJAX)
- Cross-sell display (currently only on cart page, added to drawer)
- oz-scripts.js color navigation (replaced by oz-variations _oz_variants meta)

### 9.3 Cleanup opportunities
- WAPO blocks 7, 47 (target deleted category 459)
- Orphaned addon entries: block IDs 2, 8, 15, 17, 20, 22, 25 no longer exist in blocks table -- do NOT migrate blindly from raw YITH tables
- WAPO block 38 (no targeting configured)
- WAPO block 46 (disabled)
- Legacy taxonomy pa_ruimte (singular)
- Unused attributes: pa_standaard-kleuren, pa_extra-pu-toplaag, pa_m2
- Missing cross-sells on Metallic Velvet (only 1/13 has cross-sells)
- Missing Gereedschapset Lavasteen cross-sell on 4 older Lavasteen products
- Old m2 calculator in OzTheme functions.php (dead code, remove after oz-variations deployed)

---

## 10. Implementation Phases

### Phase 1: oz-variations Port + Cart Drawer
1. Fork oz-variations for BCW (or make it multi-site aware)
2. Add OZ_Product_Line_Config for all 9 lines
3. Extract WAPO pricing data into config
4. Build cart drawer (oz-cart-drawer plugin or oz-variations module)
5. Test on staging: add-to-cart with PU/primer produces correct pricing
6. Start with Microcement (simplest: PU only, no primer, no calculator needed)

### Phase 2: Product Page Template
1. Build Flatsome template override matching productpagina-voorbeeld.html
2. oz-variations frontend display renders options inline (no WAPO)
3. Color swatches navigate to variant URLs via _oz_variants meta
4. Mobile bottom sheet for options
5. Start with Microcement, then Original, then remaining lines

### Phase 3: Upsell + Polish
1. Implement OZ_Upsell_Engine (project-completion rules)
2. Generate order history co-purchase data
3. Cart drawer upsell section
4. Product page upsell modal
5. Deactivate YITH WAPO
6. Fix orphaned WAPO blocks (cleanup)

---

## 11. File Locations

### Remote (source of truth)
- oz-variations (reference): /home/betoncire/epoxystone-gietvloer.nl/wp-content/plugins/oz-variations/
- BCW site: /home/betoncire/public_html/ (beton-cire-webshop.nl)
- OzTheme child theme: check /home/betoncire/public_html/wp-content/themes/ for active child

### Local (backups)
- Mockup: C:\Users\zeref\OneDrive\OzIS\betoncire\productpagina-voorbeeld.html
- Cart drawer mockup: C:\Users\zeref\OneDrive\OzIS\betoncire\demo-site\cart-drawer.html
- Project docs: C:\Users\zeref\OneDrive\OzIS\betoncire\oz-product-overhaul\ (repo: synvio-product-overhaul)

### Live demos
- https://demo.compliantcookies.com/ (product page)
- https://demo.compliantcookies.com/cart-drawer.html (cart drawer)

---

## 12. Open Questions

1. **oz-variations: fork or multi-site?** -- Separate BCW copy, or make plugin site-aware?
2. ~~**Gereedschapset vs individual tools?**~~ -- **RESOLVED**: Set-first UX. Three modes: Geen / Kant & Klaar (set) / Zelf samenstellen (individual). Sets are the default recommendation, individual is for pros. Extras (consumable add-ons) available on top of sets. See section 6.1c.
3. **Colorfresh addon** -- Original line only. Keep as separate toggle or fold into options UI?
4. ~~**RAL/NCS custom colors**~~ -- **RESOLVED**: Color mode selector above swatch grid. Three modes: Standaard kleur / RAL kleur / NCS kleur. Live validation with partial hints. See section 6.1b.
5. **Missing cross-sell IDs** -- 3 older Lavasteen products + Agave missing Lavasteen Gereedschapset
6. **Orphaned WAPO blocks** -- Safe to delete blocks 7, 47 (orphaned) and 46 (disabled)?
7. **Cart drawer: separate plugin or oz-variations module?** -- Affects deployment and updates
