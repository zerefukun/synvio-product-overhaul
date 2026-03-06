# WAPO Parity Config — Frozen Data Contract
# Extracted from live BCW database 2026-03-06
# Source: OTBgD_yith_wapo_blocks + OTBgD_yith_wapo_addons (37 blocks, 85 addons)
#
# This document is the SINGLE SOURCE OF TRUTH for what the oz-variations-bcw
# plugin must replicate. Every addon, price, targeting rule, and condition
# is documented here. The plugin code must match this contract exactly.

---

## 1. PRODUCT LINE DEFINITIONS

### 1.1 Original
- **Targeting:** Category 290 ("Beton Ciré Original Kleuren")
- **Base product:** 11161 ("Beton Cire Original 5 m2")
- **Unit:** 5m2 per product
- **unitM2:** 5
- **WAPO blocks:** 4, 11, 23, 26, 31, 6, 42, 45

### 1.2 All-In-One
- **Targeting:** Category 289 ("Beton Ciré All-In-One-Kleuren")
- **Base product (redirect):** 11165 ("Beton Ciré All-In-One Kant & Klaar") — landing page
- **Losse emmer:** 11191 ("Beton Cire All-In-One Emmer Kant & Klaar 1m2") — targeted by WAPO block 21
- **Unit:** 1m2 per product (Kant & Klaar)
- **unitM2:** 1
- **WAPO blocks:** 34, 37, 21

### 1.3 Easyline
- **Targeting:** Category 314 ("Beton Ciré Easyline Kleuren")
- **Base products:** 11001 (Fine 4m2), 11002 (Raw 4m2) — losse emmers
- **Unit:** 5m2 per pakket
- **unitM2:** 5
- **WAPO blocks:** 3, 12, 24

### 1.4 Microcement
- **Targeting:** Categories 455 ("Microcement kleuren"), 463 ("Microcement")
- **Unit:** 1m2 per product
- **unitM2:** 1
- **WAPO blocks:** 44 (active), 43 (disabled/obsolete), 38 (orphaned), 46 (disabled)

### 1.5 Metallic Velvet
- **Targeting:** Category 18 ("Metallic Velvet Kleuren")
- **Base product:** 11162 ("Metallic Stuc Velvet" — loose material)
- **Pakket products:** 11659, 11660, 11667, 11668, 11675, 11676, 11683, 11684, 11691, 11692, 11699, 28196
- **Unit:** 4m2 per pakket
- **unitM2:** 4
- **WAPO blocks:** 29, 5, 30

### 1.6 Lavasteen
- **Targeting:** Category 464 ("Lavasteen kleuren")
- **Specific product also targeted:** 27736
- **Unit:** TBD (check product data)
- **unitM2:** TBD
- **WAPO blocks:** 41, 48

### 1.7 Betonlook Verf
- **Targeting:** Product 11135 (single product, no category)
- **Unit:** single product
- **WAPO blocks:** 10

### 1.8 Stuco Paste
- **Targeting:** Category 457 ("Stuco Paste")
- **Unit:** single product
- **WAPO blocks:** 39

### 1.9 PU Color
- **Targeting:** Category 456 ("PU Color")
- **Unit:** single product
- **WAPO blocks:** 33

---

## 2. ADDON MATRIX PER PRODUCT LINE

### Legend
- ✅ = has this addon
- ❌ = does not have this addon
- Prices are in EUR, excl. VAT unless noted

| Addon              | Original | All-In-One | Easyline | Microcement | Metallic | Lavasteen | Betonlook Verf | Stuco Paste | PU Color |
|--------------------|----------|------------|----------|-------------|----------|-----------|----------------|-------------|----------|
| Color swatches     | ✅ 48    | ✅ 38      | ✅ 38    | ✅ 36       | ✅ 12    | ✅ 20     | ✅ 38          | ❌          | ✅ 38    |
| RAL/NCS toggle     | ❌       | ✅         | ✅       | ✅          | ❌       | ❌        | ✅             | ❌          | ✅*      |
| PU toplagen        | ✅       | ✅         | ✅       | ✅          | ✅       | ✅        | ❌             | ❌          | ❌       |
| Primer             | ✅       | ❌         | ❌       | ❌          | ✅       | ❌        | ✅             | ✅          | ❌       |
| Colorfresh         | ✅       | ❌         | ❌       | ❌          | ❌       | ❌        | ❌             | ❌          | ❌       |
| Toepassing         | ✅       | ❌         | ❌       | ❌          | ❌       | ❌        | ❌             | ❌          | ❌       |
| Pakket selector    | ✅       | ❌         | ✅       | ❌          | ❌       | ❌        | ❌             | ❌          | ❌       |

*PU Color: "Standaard Kleuren" is DISABLED in WAPO — only RAL/NCS input available.

---

## 3. PU TOPLAGEN PRICING (per product unit, not per m2)

### 3.1 Original (block 11/42)
| Option         | Label          | Price  | Default |
|----------------|----------------|--------|---------|
| 0 layers       | Geen toplaag   | €0     | no      |
| 1 layer        | 1 toplaag      | +€40   | no      |
| 2 layers       | 2 Toplagen     | +€80   | no      |
| 3 layers       | 3 Toplagen     | +€120  | no      |
- **Pattern:** Fixed prices: 0/40/80/120
- **Note:** These are per-unit (5m2 product), so effectively €8/m2 per layer

### 3.2 All-In-One (block 37)
| Option         | Label              | Price  | Default |
|----------------|--------------------|--------|---------|
| 1 layer        | 1 toplaag          | +€8    | no      |
| 2 layers       | 2 toplagen         | +€16   | no      |
| 3 layers       | 3 toplagen         | +€24   | no      |
| 0 layers       | Geen Beschermlaag  | €0     | no      |
- **Pattern:** Fixed prices: 8/16/24/0
- **Note:** per 1m2 unit, so also €8/m2 per layer

### 3.3 Easyline (block 3)
| Option         | Label          | Price  | Default |
|----------------|----------------|--------|---------|
| 1 layer        | 1 toplaag      | +€0    | no      |
| 2 layers       | 2 toplagen     | +€40   | no      |
| 3 layers       | 3 toplagen     | +€80   | no      |
- **Pattern:** Fixed prices: 0/40/80 (NO "Geen" option — minimum 1 layer)
- **Note:** 1st layer is FREE (included in price). Per 5m2 unit.

### 3.4 Microcement (block 44)
| Option         | Label              | Price  | Default |
|----------------|--------------------|--------|---------|
| 1 layer        | 1 toplaag          | +€8    | no      |
| 2 layers       | 2 toplagen         | +€16   | no      |
| 3 layers       | 3 toplagen         | +€24   | no      |
| 0 layers       | Geen Beschermlaag  | €0     | no      |
- **Pattern:** Same as All-In-One: 8/16/24/0

### 3.5 Metallic Velvet (block 30)
| Option         | Label          | Price    | Default |
|----------------|----------------|----------|---------|
| 0 layers       | Geen PU        | €0       | yes     |
| 1 layer        | 1 Laag PU      | +€39.99  | no      |
| 2 layers       | 2 Lagen PU     | +€79.99  | no      |
| 3 layers       | 3 Lagen PU     | +€119.99 | no      |
- **Pattern:** Fixed prices: 0/39.99/79.99/119.99

### 3.6 Lavasteen (block 48)
| Option         | Label          | Price  | Default |
|----------------|----------------|--------|---------|
| 1 layer        | 1 toplaag      | +€40   | yes     |
| 2 layers       | 2 toplagen     | +€80   | no      |
| 3 layers       | 3 toplagen     | +€120  | no      |
| 0 layers       | Geen toplaag   | €0     | no      |
- **Pattern:** Same as Original: 40/80/120/0 but default is 1 layer

---

## 4. PRIMER PRICING

### 4.1 Original (block 11 addon 31, also block 31 for product 11161)
| Option                     | Price   | Default |
|----------------------------|---------|---------|
| Geen                       | €0      | no      |
| Zuigende ondergrond        | +€12.50 | no      |
| Niet zuigende ondergrond   | +€12.50 | no      |

### 4.2 Metallic Velvet (block 5)
| Option  | Price  | Default |
|---------|--------|---------|
| Geen    | €0     | yes     |
| Primer  | +€5.99 | no      |

### 4.3 Betonlook Verf (block 10 addon 25)
| Option      | Price  | Default |
|-------------|--------|---------|
| Geen Primer | €0     | yes     |
| Primer      | +€6.00 | no      |

### 4.4 Stuco Paste (block 39)
| Option | Price   | Default |
|--------|---------|---------|
| Nee    | €0      | no      |
| Ja     | +€16.00 | no      |

---

## 5. COLORFRESH (Original only)

### Block 6 (product 11161) / Block 11 (cat 290)
| Option             | Price  | Default |
|--------------------|--------|---------|
| Zonder Colorfresh  | €0     | yes     |
| Met Colorfresh     | +€15   | no      |

---

## 6. TOEPASSING (Original only)

### Block 11 (cat 290) / Block 26 (product 11161)
Options (all free, single selection):
- Vloer
- Wand
- Meubel
- Keuken
- Badkamer
- Trap

---

## 7. PAKKET SELECTOR

### 7.1 Original (block 45)
| Option | Label | Price | Required |
|--------|-------|-------|----------|
| 0      | 5m2   | free  | yes      |

### 7.2 Easyline (block 3 addon 15)
| Option | Label        | Price | Required |
|--------|--------------|-------|----------|
| 0      | 5m2 - 170,-  | free  | yes      |

---

## 8. COLOR PALETTES

### 8.1 Original Palette (48 colors, 1000-series)
1000 Stone white, 1001 France grey, 1002 France grey Mid, 1003 France grey Dark,
1004 Elephant skin, 1005 Earth stone, 1006 Tin grey, 1007 Smoke black,
1008 Jack black, 1010 Azul blue, 1011 Sky blue, 1014 Lavendel,
1015 Powder skin, 1016 Peachblossem Light, 1017 Peachblossem Dark, 1018 Nude,
1020 Ashes of rose, 1021 Taupe Dark, 1025 Almond, 1026 Camel,
1027 Army grey, 1028 Antique white, 1029 Havanna Yellow, 1030 Sunday Yellow,
1031 Sahara dust Yellow, 1032 Goldzand, 1033 Old romance, 1034 Deep earth Green,
1035 China clay, 1036 Nutmeg, 1037 Dusty rose, 1038 Soft taupe,
1040 Island stone Light, 1041 Island stone Deep, 1042 Island stone Dark,
1043 Old linnen, 1044 Dried clay, 1045 Pebble stone, 1046 Taupe light,
1048 Pepper, 1049 Silky grey, 1050 Silver clay, 1051 Shabby clay,
1052 Urban grey, 1053 Storm grey, 1054 Teal grey, 1060 Greyish,
1061 Pistache soft Green, 1062 Shadow green, 1063 Mud Green

### 8.2 Kant & Klaar Palette (38 colors) — shared by All-In-One, Easyline, Betonlook Verf, PU Color
Pure White, Pure, Pearl White, Grey, Smooth Grey, Simply Grey,
Pale, Pale Stone, Shades, Dark Shades, Silk, Bricks,
New York, Stonehenge, Base Grey, Stone Grey, Camouflage, Bellbird,
Ground cover, Hunter, Atmos, Dark night, Cloudy, Island stone,
Egypt, Sandy beach, Coconut grove, Ribbon, Dusty rose, Gloria,
Hippo, Sage, Olive, Bit of green, Basil, Olive vierge,
Octo, Canyon, Mermaid, Emerald bay

### 8.3 Microcement Palette (36 colors)
Cement 1, Cement 2, Cement 3, Cement 4, Cement 5,
Blue 2,
Sand 1, Sand 2, Sand 3, Sand 4, Sand 5, Sand 6,
Green 1, Green 2, Green 3, Green 4, Green 5, Green 6,
Nude 1, Nude 2, Nude 3, Nude 4, Nude 5, Nude 6,
Warm Grey 1, Warm Grey 2, Warm Grey 3, Warm Grey 4, Warm Grey 5, Warm Grey 6,
Lavender Grey 1, Lavender Grey 2, Lavender Grey 3, Lavender Grey 4, Lavender Grey 5, Lavender Grey 6

### 8.4 Metallic Palette (12 colors)
Black Pearl, Brilliant, Champagne, Copper, Duna, Griseo,
Oro, Pandora, Platinum, Rose, Royal Flush, Silver Lining

### 8.5 Lavasteen Palette (20 colors)
Cream Peony 23, Seakale 3, Anise 4, Fennel 14, Mushroom 15,
Seashell 20, Morning Dew 9, Hazel 10, Hillflower 16, Portobelo 17,
Wool 19, Mellisa 2, Ash 12, Shiitaki 7, Aquamarine 24,
Linnen 1, Sterling 25, Agave 11, Reindeer Moss 27, Graphite 22

---

## 9. RAL/NCS COLOR MODE

**Pattern (used by All-In-One, Easyline, Microcement, Betonlook Verf, PU Color):**
1. Label addon with 2 options: "Standaard Kleuren" (default) | "RAL of NCS Kleuren"
2. Color swatch addon: conditional — show only when "Standaard" is selected
3. Text input addon: conditional — show only when "RAL/NCS" is selected
   - Placeholder: "Typ nr van RAL of NCS"
   - Required: yes

**Exceptions:**
- Original: NO RAL/NCS — only standard colors
- Metallic: NO RAL/NCS — only standard colors
- Lavasteen: NO RAL/NCS — only standard colors
- PU Color: "Standaard Kleuren" is DISABLED — ONLY RAL/NCS input available

---

## 10. TOOL/ACCESSORY PRODUCTS (not part of main product lines)

### 10.1 Rollers (product 11175)
- Roller 10cm: free (default)
- Roller 18cm: +€7.45
- Roller 25cm: +€10.45

### 10.2 Gereedschapsset (products 11163, 11177)
- Roller: 10cm (free), 18cm (+€10), 25cm (+€14.99)
- Troffels: Nee (free), Ja (+€45)

### 10.3 Verfbak (product 11164)
- 10cm: free
- 18cm: +€2
- 32cm: +€3

### 10.4 Croco Roller (product 11176)
- 18cm: free (default)
- 25cm: +€4.99

### 10.5 PU Basis (product 11174)
- Original: free
- Kant & Klaar: +€10

### 10.6 Epoxystone (cat 459 — shared BCW/Epoxystone shop)
- 20 color swatches (same as Lavasteen palette)
- PU: 1 (+40, default), 2 (+80), 3 (+120), Geen (free)

### 10.7 Kleuren Pakket (cat 458)
- Soort: Beton Cire | EpoxyStone | Microcement (all free)

---

## 11. CROSS-LINE DISPLAY BLOCKS (no pricing impact)

- **Block 27 "Bubbles"**: targets ALL products, no addons (display only)
- **Block 35 "Kleurstalen Aanvragen"**: targets cats 330, 289, 290, 314, 18 — HTML text block (color sample request)
- **Block 18 "Losse Producten"**: targets 19 products + cat 17 — HTML text block

---

## 12. DISABLED / OBSOLETE BLOCKS

| Block | Name            | Status   | Reason                                      |
|-------|-----------------|----------|---------------------------------------------|
| 43    | Microcement RAL | Disabled | Superseded by block 44 "Microcement Nieuw"  |
| 46    | Extra's         | Disabled | Microcement extras (Flexibele spaan, Kwast)  |
| 38    | Microcement Kleuren | Orphaned | No targeting — targets "products" with empty list |
| 34    | AIO K&K Kleuren | Likely obsolete | Same cat 289 as block 37, addons hidden |

---

## 13. OPTION ORDER PER PRODUCT LINE

Based on WAPO block priorities and addon ordering:

### Original
1. Pakket selector (block 45, priority 1)
2. Color swatches (block 4/11, priority 2)
3. Toepassing (block 11/26, priority 2)
4. Primer (block 31, priority 4)
5. Colorfresh (block 6, priority 5)
6. PU toplagen (block 42, priority 6)

### All-In-One
1. Color mode toggle (addon 95)
2. Color swatches / RAL input (conditional)
3. PU toplagen (addon 96)

### Easyline
1. Color mode toggle (addon 14)
2. Color swatches / RAL input (conditional)
3. Pakket selector (addon 15)
4. PU toplagen (addon 11)

### Microcement
1. Color mode toggle (addon 109)
2. Color swatches / RAL input (conditional)
3. PU toplagen (addon 110)

### Metallic Velvet
1. Color swatches (block 29)
2. Primer (block 5)
3. PU layers (block 30)

### Lavasteen
1. Color swatches (block 41)
2. PU toplagen (block 48)

### Betonlook Verf
1. Color mode toggle (addon 28)
2. Color swatches / RAL input (conditional)
3. Primer (addon 25)

### Stuco Paste
1. Primer (addon 98)

### PU Color
1. Color mode toggle (addon 85) — Standaard DISABLED, only RAL/NCS
2. RAL/NCS input (addon 84)

---

## 14. CART ITEM DATA CONTRACT

Every addon selection must be stored as cart item data and saved to order meta.

| Cart Key          | Type    | Values                                           | Lines that use it |
|-------------------|---------|--------------------------------------------------|-------------------|
| oz_line           | string  | original, all-in-one, easyline, microcement, metallic, lavasteen, betonlook-verf, stuco-paste, pu-color | ALL |
| oz_pu_layers      | int     | 0, 1, 2, 3                                      | original, all-in-one, easyline, microcement, metallic, lavasteen |
| oz_primer         | string  | "", "Zuigende ondergrond", "Niet zuigende ondergrond", "Primer" | original, metallic, betonlook-verf, stuco-paste |
| oz_colorfresh     | string  | "", "Met Colorfresh"                              | original |
| oz_toepassing     | string  | "", "Vloer", "Wand", "Meubel", "Keuken", "Badkamer", "Trap" | original |
| oz_color_mode     | string  | "standard", "ral_ncs"                             | all-in-one, easyline, microcement, betonlook-verf, pu-color |
| oz_custom_color   | string  | RAL/NCS code (freetext)                           | all-in-one, easyline, microcement, betonlook-verf, pu-color |
| oz_pakket         | string  | "5m2" (currently single option)                   | original, easyline |

---

## 15. PRICING RESOLVER CONTRACT

```
function resolve_addon_price(line_key, cart_item_data) → float:

  addon_total = 0

  // PU
  if oz_pu_layers > 0:
    addon_total += PU_PRICE_TABLE[line_key][oz_pu_layers]

  // Primer
  if oz_primer != "":
    addon_total += PRIMER_PRICE_TABLE[line_key][oz_primer]

  // Colorfresh (Original only)
  if oz_colorfresh == "Met Colorfresh":
    addon_total += 15.00

  return addon_total
```

### PU_PRICE_TABLE (per product unit)
```
{
  "original":     { 1: 40,    2: 80,    3: 120    },
  "all-in-one":   { 1: 8,     2: 16,    3: 24     },
  "easyline":     { 1: 0,     2: 40,    3: 80     },
  "microcement":  { 1: 8,     2: 16,    3: 24     },
  "metallic":     { 1: 39.99, 2: 79.99, 3: 119.99 },
  "lavasteen":    { 1: 40,    2: 80,    3: 120    }
}
```

### PRIMER_PRICE_TABLE
```
{
  "original":       { "Zuigende ondergrond": 12.50, "Niet zuigende ondergrond": 12.50 },
  "metallic":       { "Primer": 5.99 },
  "betonlook-verf": { "Primer": 6.00 },
  "stuco-paste":    { "Ja": 16.00 }
}
```

---

## 16. FRONTEND PAYLOAD SHAPE (wp_localize_script)

```javascript
window.ozProduct = {
  // Product identity
  productId:    int,
  productName:  string,
  basePrice:    float,
  productLine:  string,   // "original", "all-in-one", etc.

  // Unit info
  unit:    string,  // "5m2", "1m2", "4m2", "stuk"
  unitM2:  float,   // 5, 1, 4, 1

  // PU options: array of {layers, label, price, default} or false
  puOptions: [
    { layers: 0, label: "Geen toplaag",  price: 0,  default: false },
    { layers: 1, label: "1 toplaag",     price: 40, default: false },
    ...
  ] | false,

  // Primer options: array of {label, price, default} or false
  primerOptions: [
    { label: "Geen",                   price: 0,     default: true },
    { label: "Zuigende ondergrond",    price: 12.50, default: false },
    ...
  ] | false,

  // Colorfresh: {options: [{label, price, default}]} or false
  colorfresh: [
    { label: "Zonder Colorfresh", price: 0,  default: true },
    { label: "Met Colorfresh",    price: 15, default: false },
  ] | false,

  // Toepassing: array of strings or false
  toepassing: ["Vloer", "Wand", "Meubel", "Keuken", "Badkamer", "Trap"] | false,

  // RAL/NCS available?
  hasRalNcs: bool,
  ralNcsOnly: bool,  // true for PU Color (standard disabled)

  // Color palette for swatches (from variant data, not WAPO)
  currentColor: string,
  variants: { [id]: { color, url, image } },

  // Pakket options or false
  pakket: [{ label: "5m2", price: 0 }] | false,

  // Option display order
  optionOrder: ["pakket", "color", "toepassing", "primer", "colorfresh", "pu"],

  // Cart/AJAX
  ajaxUrl:     string,
  cartUrl:     string,
  checkoutUrl: string,
  nonce:       string,
};
```
