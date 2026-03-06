# Beton Cire Webshop -- Project Scope
**Version 2.1 -- 6 maart 2026** (oz-variations foundation; v2.1 fixes: upsell hierarchy, m2 flag scope, room page count)

---

## 1. Project Goal

Rebuild the product buying experience for beton-cire-webshop.nl using our own
**oz-variations** plugin (already proven on epoxystone-gietvloer.nl) as the foundation.

This replaces YITH WAPO with our own system: a new product page template,
a cart drawer with upsells, and smarter cross-selling -- so customers buy the
right products with all needed materials in fewer clicks.

**Why oz-variations?**
- We built it ourselves -- no third-party dependency, no update breakage risk
- Already handles color merging, PU/primer pricing, calculator, cart management
- Proven in production on epoxystone with 12 languages
- Matches the productpagina-voorbeeld.html mockup capabilities exactly

**Stakeholders:** Patrick (owner), Dave (SEO), Fatih (development)

---

## 2. Confirmed Scope

### 2.1 Port oz-variations to BCW
- Fork or extend oz-variations for BCW's 9 product lines (epoxystone has 2)
- Per-line configuration: PU options, primer options, pricing, package sizes
- Color variant detection and base-product redirect (already built)
- Color swatches with URL-based navigation (already built)
- Replace YITH WAPO addon frontend + backend pricing entirely

### 2.2 Product Page Template
- Unified template for all product lines (currently each line has ad-hoc WAPO layout)
- Color swatches with direct product navigation (oz-variations _oz_variants meta)
- Add-on toggles (PU, Primer) adapted per product line (oz-variations config)
- Individual tool selection (not fixed sets)
- m2 calculator: **not wanted** (Patrick's decision). Current BCW calculator is dead code (remove during cleanup). Epoxystone's room-based calculator will NOT be ported to BCW.
- Live price breakdown
- Mobile bottom sheet for options
- Sticky bar with CTA on mobile

### 2.3 Cart Drawer
- Slides from right after adding to cart
- Shows cart items with quantity controls
- Free shipping progress bar (threshold: EUR 150)
- Incremental DOM updates (no full-page reload)
- Opens automatically on add-to-cart

### 2.4 Upsell / Cross-sell System
- **Product page level:** Upsell modal when adding a purchasable PDP (color PDP or single-product line) without tools
- **Cart drawer level:** "Vakmannen bestellen ook" section with smart suggestions
- **Priority model:** 1) project-completion rules (eligibility), 2) WooCommerce cross-sell IDs (product selection), 3) order history (tiebreaking), 4) per-line fallback defaults
- Suggestions adapt to cart contents (no duplicates)

---

## 3. Out of Scope

- Room page (ruimtepagina) redesign -- noted as future opportunity from Dave's analysis
- Category page layout changes
- Checkout page modifications
- Payment/shipping configuration
- Product data entry / new SKU creation
- SEO content writing
- Email marketing / abandoned cart flows

---

## 4. Current Catalog Rules

### 4.1 Product Lines

| Product Line | Colors + Base | Unit | Price | Has PU | Has Primer | Notes |
|---|---|---|---|---|---|---|
| Original | 50 + 1 base | 5m2 pakket | EUR 90 | Yes (1-3 lagen) | Yes (zuigend/niet-zuigend) | Colorfresh addon |
| All-In-One K&K | 40 + 1 base | 1m2 emmer | EUR 28 | Yes (1-3 lagen) | No | RAL/NCS custom color input |
| Easyline K&K | 40 + 1 base | 4m2 pakket | EUR 170 | Yes (1-3 lagen) | No | RAL/NCS custom color input |
| Metallic Velvet | 12 + 1 base | 4m2 pakket | EUR 120 | Yes (Geen/1/2/3 lagen) | Yes (Geen/Primer EUR 5,99) | |
| Microcement | 36 + 1 base | per stuk (1m2) | EUR 31 | Yes (1-3 lagen) | No | |
| Lavasteen | 20 + 1 base | 5m2 pakket | EUR 235 | Yes | No | |
| Betonlook Verf | 1 (no variants) | per stuk | EUR 29 | No | Yes | Internal color option, not URL variants |
| Stuco Paste | 1 (no variants) | per stuk | EUR 59.95 | No | Yes | No color variants |
| PU Color | 1 (no variants) | per stuk | EUR 95 | No | No | Standalone product |

### 4.2 Cross-sell Pattern

Most color PDPs cross-sell to:
- Flexibele spaan (EUR 39.95)
- PU roller (EUR 2.50)
- Relevant Gereedschapset (K&K EUR 89.99 / Mengen EUR 119.99 / Lavasteen EUR 115.95)

Betonlook Verf cross-sells to: Blokkwast, Effect Kwast, Kwast, Vachtroller.

### 4.3 Room Pages

7 live published room pages (verified on site):
- beton-cire-badkamer
- beton-cire-keuken
- beton-cire-trappen
- beton-cire-woonkamer
- beton-cire-toilet
- beton-cire-vloer
- beton-cire-wand

1 additional application/topic page (not a room page):
- over-tegels

NOT live as published pages: Meubel, Kantoor.

Note: pa_ruimtes taxonomy has 6 terms (Badkamer, Keuken, Meubel, Trap, Vloer, Wand)
with 89 products each, but actual live pages differ from taxonomy terms.

Current room pages mostly reuse the same core product set (microcement,
original, easyline, all-in-one, tool sets). Not strongly room-specific yet.
Lavasteen appears mainly on badkamer/vloer. Metallic Velvet on wand.

### 4.4 Known Issues in Current Setup

- WAPO blocks 7 and 47 target deleted category 459 (orphaned)
- Orphaned addon entries in YITH tables: block IDs 2, 8, 15, 17, 20, 22, 25 no longer exist in blocks table
- WAPO block 38 (Microcement Kleuren) has no targeting configured
- WAPO block 46 (Extra's) is disabled
- Only 1 product has a SKU (Ardex R1 = AR1C)
- 3 older Lavasteen products (Graphite, Reindeer Moss, Sterling) + Agave missing Lavasteen Gereedschapset cross-sell
- Legacy duplicate taxonomy pa_ruimte (singular) exists alongside active pa_ruimtes (plural)
- Several unused product attributes: pa_standaard-kleuren, pa_extra-pu-toplaag, pa_m2

---

## 5. Business Rules That Must Not Break

1. **Two product classes: base products and color PDPs (color-based lines only).** Lines with multiple colors (Original, All-In-One, Easyline, Metallic Velvet, Microcement, Lavasteen) each have one generic/base product (e.g. "Beton Cire Original 5m2", ID 11161) that acts as a landing page. oz-variations detects these and redirects to the most-sold color variant. Color products are the actual purchasable PDPs -- each has its own URL, price, and receives the option UI. Single-product lines (Betonlook Verf, Stuco Paste, PU Color) have no variants and no redirect -- they are directly purchasable.

2. **Color = separate product (color-family lines only).** For lines with variant families (Original, All-In-One, Easyline, Metallic, Microcement, Lavasteen), each color is its own WooCommerce product with its own URL. Switching color = navigating to a different product URL. This is critical for SEO. Single-product lines (Betonlook Verf, Stuco Paste, PU Color) may still have internal color/option selectors (e.g. Betonlook Verf has Kleur + Primer on its page), but these are WAPO-style options on one product, not separate product URLs.

3. **PU options are product-line specific.** Original offers 1-3 lagen + "geen". Metallic offers Geen/1/2/3 lagen PU. These will be configured in oz-variations per-line config (replacing WAPO blocks).

4. **Primer options vary.** Original: zuigend/niet-zuigend/geen. Metallic: color primer/geen. Betonlook Verf/Stuco Paste: their own primer options. Most other lines: no primer.

5. **Upsell priority: rules first, cross-sells second.** Project-completion rules (per product line) determine eligible categories. WooCommerce cross-sell IDs (per product) pick specific products within eligible categories. Order history ranks ties. Fallback defaults only when cross-sells are missing. See TECHNICAL-SPEC section 5.5 for full hierarchy.

6. **PU/primer pricing via oz-variations cart item data.** Same mechanism as epoxystone: addons stored as cart item data, priced via woocommerce_before_calculate_totals hook. Prices must match current WAPO pricing exactly.

7. **EUR 150 free shipping threshold.** Used in header messaging and cart drawer progress bar.

8. **All products are simple type.** No variable products exist. The template must not assume variable product logic.

9. **URL-based color navigation.** oz-variations handles this natively: _oz_variants meta stores bidirectional variant links, frontend renders clickable swatches. Replaces oz-scripts.js baseSlugs/exactSlugMap/slugOverrides approach.

---

## 6. Implementation Phases

### Phase 1: oz-variations Port + Cart Drawer
Port oz-variations to BCW and build the cart drawer.
- Fork oz-variations for BCW (add per-line config for 9 product lines)
- Extract WAPO pricing data into oz-variations config
- Build cart drawer (standalone plugin or oz-variations module)
- Vanilla JS core, jQuery only for Flatsome event interop (added_to_cart hook)
- AJAX endpoints for qty update / remove
- Upsell section using WooCommerce cross-sell data
- Start with Microcement (simplest: PU only, no primer)
- Test on staging before production

### Phase 2: Product Page Template
Build the unified product page as a Flatsome template override.
- oz-variations renders option UI (not WAPO)
- Color swatches navigate via _oz_variants meta
- Start with Microcement, then Original (most complex), then remaining lines
- Mobile bottom sheet for options

### Phase 3: Upsell, Cleanup + Roll Out
- Implement upsell engine (project-completion rules + order history ranking)
- Deactivate YITH WAPO
- Fix orphaned WAPO blocks
- Add missing cross-sell relationships
- Monitor conversion metrics
- Room page improvements (future, per Dave's recommendations)

---

## 7. Acceptance Criteria

### Product Page
- [ ] Color swatches navigate to correct product URLs (via _oz_variants meta)
- [ ] Base products redirect to most-sold color variant
- [ ] PU/Primer toggles match current WAPO options per product line
- [ ] Price updates live when changing options
- [ ] "In winkelmand" adds correct product + selected addons to WooCommerce cart
- [ ] Mobile bottom sheet works for all option types
- [ ] Pricing matches current WAPO pricing exactly (no regressions)

### Cart Drawer
- [ ] Opens after successful add-to-cart
- [ ] Shows correct items, quantities, prices (including PU/primer addons)
- [ ] Qty +/- works without page reload
- [ ] Remove item works, shows empty state when cart is empty
- [ ] Upsell suggestions appear based on cart contents
- [ ] Free shipping bar shows correct progress
- [ ] Checkout button goes to WooCommerce checkout
- [ ] Works on mobile (full-width, touch-friendly)

### Integration
- [ ] Cart badge in header updates on all pages
- [ ] Product page + cart drawer use same design system
- [ ] No conflicts with existing Flatsome theme functionality
- [ ] Works for both logged-in and guest users
- [ ] YITH WAPO can be deactivated without breaking any functionality

---

## 8. Open Questions

1. **oz-variations: fork or multi-site?** Separate BCW copy of the plugin, or make the plugin site-aware so one codebase serves both epoxystone and BCW?

2. **Gereedschapset vs individual tools?** The current cross-sells point to Gereedschapsets (EUR 89-119). Our mockup shows individual tools. Which approach does Patrick prefer for the live site?

3. **Colorfresh addon** -- Only applies to Original line. Keep as separate toggle or fold into the options UI?

4. **RAL/NCS custom colors** -- All-In-One and Easyline currently have text input fields for custom RAL/NCS color codes. How should this work in the new template?

5. **Orphaned WAPO blocks** -- Can we safely delete blocks 7, 47 (target deleted category) and block 46 (disabled)?

6. **Room page improvements** -- Dave recommends product blocks on room pages. Is this Phase 3 or a separate project?

7. **Cart drawer: separate plugin or oz-variations module?** -- Affects deployment and update strategy.
