# PushState Color Navigation — Implementation Spec

## Problem

When a customer clicks a color swatch on any product line page (Original, Microcement,
All-in-One, etc.), the browser does a **full page reload** to the variant URL. This causes:

- ~1-2s delay per color switch (server round-trip + full re-render)
- Loss of scroll position
- Loss of selected options (PU, primer, tools) unless persisted via localStorage
- Jarring visual flash between page loads
- Poor perceived performance compared to modern e-commerce

## Desired Behavior

Clicking a color swatch updates the URL via `history.pushState()` and swaps
variant-specific DOM elements via JavaScript — **no page reload**.

The URL bar, browser title, product image, price, and cart target all update
instantly. The back/forward buttons cycle through previously viewed colors.
Directly visiting a variant URL still works (server renders the correct product).

---

## Current Architecture

### Two swatch types

| Type | Attribute | href | Click behavior |
|------|-----------|------|----------------|
| **Normal** | (none) | `/variant-slug/` | Full page navigation (default `<a>`) |
| **Static** | `data-static="1"` | `#` | JS-only state update, no navigation |

Static swatches are used for "borrowed" colors (Betonlook Verf borrows from
All-in-One). These already work without navigation — pushState only applies to
**normal swatches**.

### Data already available in JS

`ozProduct.variants` (from `wp_localize_script`) contains per variant:
```
{ color: "Stone White 1000", url: "/beton-cire-original-stone-white-1000/", image: "thumb-url" }
```

### Key files

| File | What | Lines |
|------|------|-------|
| `class-frontend-display.php` | `render_color_swatches()` — generates `<a>` HTML | 278-349 |
| `class-product-processor.php` | `get_variant_display_data()` — loads variant URLs/images | 285-314 |
| `src/js/product-page.js` | Swatch click handler | 621-645 |
| `src/js/product-page.js` | `syncUI()` — updates labels/prices after state change | 295-363 |
| `src/js/state.js` | State object + `updateState()` | 46-126 |
| `templates/single-product.php` | Color section rendering | 340-368 |

### URL structure

```
Base:    /beton-cire-original/              (product ID 11161)
Variant: /beton-cire-original-stone-white-1000/  (product ID 11293)
```

Each variant is a **separate WooCommerce product** with its own permalink,
image, title, and price. This is good for SEO — each URL is a real page.

---

## SEO Strategy

### Goal: Zero SEO impact

Google already indexes each variant URL as a separate product page. We must
preserve this. PushState changes are invisible to crawlers that don't execute
JS, and for Googlebot (which does execute JS), the rendered DOM must match
what a server-rendered page would show.

### Canonical URLs

Each variant page should have a `<link rel="canonical">` pointing to itself:
```html
<!-- On /beton-cire-original-stone-white-1000/ -->
<link rel="canonical" href="https://beton-cire-webshop.nl/beton-cire-original-stone-white-1000/" />
```

When pushState changes the URL, update the canonical tag in the `<head>`:
```javascript
document.querySelector('link[rel="canonical"]').setAttribute('href', newUrl);
```

### Document title

Update `document.title` to match the variant:
```javascript
document.title = "Beton Ciré Original (Stone White 1000) - Beton Ciré Webshop";
```

The title format should match what WooCommerce/Yoast generates server-side.

### Structured data (JSON-LD)

WooCommerce outputs Product structured data. When pushState swaps the variant,
update the JSON-LD block:
- `name` → variant product name
- `url` → variant URL
- `image` → variant product image
- `sku` → variant SKU (if different)
- `offers.price` → variant price
- `offers.url` → variant URL

Implementation: find the existing `<script type="application/ld+json">` and
replace it with updated data. Variant structured data should be included in
the JS config passed from PHP.

### Open Graph / Social sharing

Update OG meta tags when pushState fires:
```javascript
document.querySelector('meta[property="og:url"]').setAttribute('content', newUrl);
document.querySelector('meta[property="og:title"]').setAttribute('content', newTitle);
document.querySelector('meta[property="og:image"]').setAttribute('content', newImage);
```

This ensures that if a user copies the URL after selecting a color, social
platforms fetch the correct preview. Note: most social crawlers don't execute
JS, so they'll hit the server-rendered page at that URL — which already has
the correct OG tags since it's a real WC product.

### Sitemap

No changes needed. Each variant is already a separate WC product and appears
in the WooCommerce sitemap. PushState doesn't affect sitemap generation.

---

## UX Requirements

### 1. Instant color switching
- Swatch click → URL + image + price update within ~50ms
- No white flash, no layout shift
- Smooth image transition (crossfade or instant swap)

### 2. Browser history
- Each color selection pushes a history entry
- Back button → previous color (not previous page)
- Forward button → next color
- History entries should not accumulate excessively (e.g., rapidly clicking
  10 colors should ideally use `replaceState` for intermediate clicks and
  `pushState` for the final selection — debounce of ~300ms)

### 3. Deep linking
- Visiting `/beton-cire-original-stone-white-1000/` directly shows Stone White
  with the correct swatch pre-selected (already works — server renders it)
- Sharing a URL always lands on the correct variant

### 4. Option preservation
- PU, primer, tools, quantity selections persist across color switches
- Currently saved to localStorage on navigation — with pushState, state stays
  in memory (no need for localStorage roundtrip)
- Tool size selections (if any) should persist

### 5. Cart submission
- After pushState, the "In winkelmand" button must submit the correct
  **variant product ID**, not the base product ID
- The cart payload (`oz_color`, product_id) must update when color changes

### 6. Gallery image
- The main product image should swap to the variant's featured image
- If the variant has a gallery, swap the gallery too
- Swatch thumbnails already exist in `ozProduct.variants[].image`
- Full-size images need to be added to the variant data

### 7. Fallback
- If JavaScript fails or is blocked, normal `<a href>` navigation still works
- Progressive enhancement: the page works without JS, pushState enhances it

---

## Technical Architecture

### Phase 1: Extend variant data from PHP

`get_variant_display_data()` currently returns `color`, `url`, `image` (thumb).
Add:

```php
'product_id'  => $vid,
'full_image'  => wp_get_attachment_image_url($product->get_image_id(), 'large'),
'price'       => floatval($product->get_price()),
'title'       => $product->get_name(),
'slug'        => $product->get_slug(),
```

This gives JS everything it needs to swap DOM elements without a server call.

### Phase 2: Intercept swatch clicks

In the swatch click handler (`product-page.js` lines 621-645), change the
normal swatch branch from "let default navigation happen" to:

```javascript
// Normal swatch — pushState instead of navigation
e.preventDefault();

var colorName = swatch.getAttribute('data-color');
var variantUrl = swatch.getAttribute('href');
var variantData = findVariantByColor(colorName);

if (!variantData) {
    // Fallback: let browser navigate normally
    window.location.href = variantUrl;
    return;
}

// Push new URL to history
history.pushState(
    { color: colorName, productId: variantData.product_id },
    variantData.title,
    variantUrl
);

// Update DOM
applyVariant(variantData);
```

### Phase 3: DOM update function

```javascript
function applyVariant(variant) {
    // 1. Update product image
    swapProductImage(variant.full_image);

    // 2. Update document title
    document.title = variant.title + ' - Beton Ciré Webshop';

    // 3. Update canonical URL
    updateMeta('link[rel="canonical"]', 'href', variant.url);

    // 4. Update OG meta
    updateMeta('meta[property="og:url"]', 'content', variant.url);
    updateMeta('meta[property="og:title"]', 'content', variant.title);
    updateMeta('meta[property="og:image"]', 'content', variant.full_image);

    // 5. Update color label
    updateState({ selectedColor: variant.color });

    // 6. Update cart target product ID
    updateCartProductId(variant.product_id);

    // 7. Update swatch .selected class
    highlightSwatch(variant.color);

    // 8. Update structured data
    updateJsonLd(variant);

    // 9. Sync rest of UI (price, sticky bar, etc.)
    syncUI();
}
```

### Phase 4: Handle popstate (back/forward)

```javascript
window.addEventListener('popstate', function(e) {
    if (e.state && e.state.color) {
        var variant = findVariantByColor(e.state.color);
        if (variant) {
            applyVariant(variant);
        } else {
            // Unknown state — reload the page at current URL
            window.location.reload();
        }
    } else {
        // No state (e.g., landed on base product) — reload
        window.location.reload();
    }
});
```

### Phase 5: Initial state

On page load, push the current variant into history state so back button
works correctly from the first color switch:

```javascript
// On DOMContentLoaded, replace current history entry with state
if (P.currentColor && P.productId) {
    history.replaceState(
        { color: P.currentColor, productId: P.productId },
        document.title,
        window.location.href
    );
}
```

---

## Edge Cases

### 1. Base product page (no color selected)
- `/beton-cire-original/` is the base product, no color selected
- Swatches are links to variants
- First click → `pushState` to variant URL
- Back button → return to base product URL (reload, since base has no variant state)

### 2. RAL/NCS custom colors
- Some lines (Microcement, All-in-One) support RAL/NCS custom color entry
- These don't have a separate product URL — color is entered manually
- PushState should NOT fire for RAL/NCS selections
- If user switches from a standard color to RAL/NCS: `replaceState` back to
  base product URL (or keep variant URL — debatable)

### 3. Different prices per variant
- Currently all Original variants are €28 — but other lines may differ
- The price display must update from `variant.price`
- The price breakdown (base + PU + primer) should recalculate

### 4. Image gallery (future)
- Currently single product image per variant
- If galleries are added later, `applyVariant` should swap the full gallery
- For now, just swap the main image

### 5. WooCommerce cart widget
- After pushState, if the user opens the cart drawer, the "continue shopping"
  link should point to the current (pushState'd) URL
- WC breadcrumbs in the header should update too (if visible)

### 6. Analytics
- `trackColorSelected` analytics event already fires on swatch click
- Ensure it still fires after switching to pushState (before `e.preventDefault()`)
- Consider tracking pushState navigations as virtual page views in GA4

### 7. LiteSpeed Cache
- The site uses LiteSpeed Cache — ensure cached pages include all variant
  data in the localized JS config
- PushState requests never hit the server, so cache isn't a concern for
  the JS-driven navigation itself

### 8. Debounce rapid clicks
- If user clicks 5 colors in quick succession, don't push 5 history entries
- Use `replaceState` for clicks within 300ms of the previous, `pushState`
  only for the final (debounced) selection

---

## Applies To

| Product Line | Swatch Type | PushState? |
|---|---|---|
| Original | Normal (separate products) | Yes |
| All-in-One | Normal | Yes |
| Microcement | Normal | Yes |
| EasyLine | Normal | Yes |
| Metallic | Normal | Yes |
| Lavasteen | Normal | Yes |
| Betonlook Verf | Static (borrowed) | No (already JS-only) |
| Stuco Paste | No colors | N/A |
| PU Color | Static (borrowed) | No (already JS-only) |

---

## Implementation Order

1. **Extend PHP variant data** — add product_id, full_image, price, title
2. **Intercept clicks + pushState** — prevent navigation, push URL
3. **DOM swap function** — image, title, meta, canonical, OG, JSON-LD
4. **Cart product ID update** — ensure correct variant goes to cart
5. **popstate handler** — back/forward support
6. **Initial state** — replaceState on page load
7. **Debounce** — replaceState for rapid clicks
8. **Analytics** — virtual page views
9. **Testing** — all product lines, back/forward, deep links, cart, SEO audit

## Testing Checklist

- [ ] Click color → URL updates without reload
- [ ] Click color → product image swaps to correct variant
- [ ] Click color → price updates if different
- [ ] Click color → document.title updates
- [ ] Click color → canonical tag updates
- [ ] Back button → returns to previous color, URL + image correct
- [ ] Forward button → goes to next color
- [ ] Direct URL visit → server renders correct variant (SSR unchanged)
- [ ] Share variant URL → correct page loads for recipient
- [ ] Add to cart → correct variant product ID submitted
- [ ] PU/primer/tools selections persist across color switches
- [ ] RAL/NCS input does NOT trigger pushState
- [ ] Static swatches (Betonlook Verf) unaffected
- [ ] Rapid clicking → only final color in history
- [ ] JS disabled → normal `<a>` navigation still works
- [ ] Google Search Console → no new crawl errors after deploy
- [ ] Mobile → swipe back gesture works correctly
- [ ] Structured data validator → correct after pushState
