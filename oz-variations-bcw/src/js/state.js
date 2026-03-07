/**
 * Shared State & Pure Functions
 *
 * Contains:
 * - P (ozProduct config from PHP)
 * - S (mutable application state)
 * - All pure helper functions (fmt, calculatePrices, validators)
 * - DOM cache and show/hide helpers
 * - SVG constants
 *
 * Zero dependencies — imported by all other modules.
 *
 * @package OZ_Variations_BCW
 * @since 2.0.0
 */

// ozProduct is injected by wp_localize_script in class-frontend-display.php
export var P = window.ozProduct || null;

/**
 * Find the default option value from an options array.
 * Each option has a 'default' boolean flag.
 *
 * @param {Array|false} options  Options array from ozProduct
 * @param {string}      key     Property name to return (e.g. 'layers', 'label')
 * @return {*}  The default value, or first value, or null
 */
export function findDefault(options, key) {
  if (!options || !options.length) return null;
  for (var i = 0; i < options.length; i++) {
    // wp_localize_script sends 'default' as "" (false) or "1" (true)
    if (options[i]['default'] && options[i]['default'] !== '' && options[i]['default'] !== '0') {
      return key === 'layers' ? parseInt(options[i][key], 10) : options[i][key];
    }
  }
  // No default flag set — return first option
  return key === 'layers' ? parseInt(options[0][key], 10) : options[0][key];
}


/* ═══ APPLICATION STATE ═════════════════════════════════════ */

// Single source of truth for all UI state
// Safe when P is null (non-product pages) — entry point guards before use
export var S = P ? {
  qty: 1,

  // PU layers selection (int or null if no PU options)
  puLayers: findDefault(P.puOptions, 'layers'),

  // Primer label (string or null)
  primer: findDefault(P.primerOptions, 'label'),

  // Colorfresh label (string or null)
  colorfresh: findDefault(P.colorfresh, 'label'),

  // Toepassing label (string or null)
  toepassing: P.toepassing ? P.toepassing[0] : null,

  // Pakket label (string or null)
  pakket: findDefault(P.pakket, 'label'),

  // Color mode: 'swatch' or 'ral_ncs'
  colorMode: P.ralNcsOnly ? 'ral_ncs' : 'swatch',

  // Custom RAL/NCS code entered by user
  customColor: '',

  // Is the bottom sheet open?
  sheetOpen: false,

  // Tool mode: 'none' (default), 'set' (complete set), 'individual' (pick items)
  toolMode: 'none',

  // Extras on top of set — only used when toolMode === 'set'
  // Keyed by extra id: { on: bool, qty: int, size: int }
  extras: {},

  // Individual tool selections — only used when toolMode === 'individual'
  // Keyed by tool id: { on: bool, qty: int, size: int }
  tools: {},

  // Upsell modal state
  upsellOpen: false,
} : null;

// Initialize extras state from tool config
if (P && P.hasTools && P.toolConfig && P.toolConfig.extras) {
  P.toolConfig.extras.forEach(function(e) { S.extras[e.id] = { on: false, qty: 0, size: 0 }; });
}
// Initialize individual tools state from tool config
if (P && P.hasTools && P.toolConfig && P.toolConfig.tools) {
  P.toolConfig.tools.forEach(function(t) { S.tools[t.id] = { on: false, qty: 0, size: 0 }; });
}


/* ═══ PURE FUNCTIONS ════════════════════════════════════════ */

/**
 * Format a number as Euro currency string.
 * @param {number} n
 * @return {string}  e.g. "€12,50"
 */
export function fmt(n) {
  // wp_localize_script sends all values as strings — ensure numeric
  n = parseFloat(n) || 0;
  return '€' + n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/**
 * Get the active price for a tool/extra item — uses selected size if available.
 */
export function getItemPrice(configItem, stateItem) {
  if (configItem.sizes && stateItem) return parseFloat(configItem.sizes[stateItem.size || 0].price) || 0;
  return parseFloat(configItem.price) || 0;
}

/**
 * Calculate all prices based on current state.
 * Pure function — reads S and P, returns price object.
 *
 * @return {Object}  { base, puPrice, primerPrice, colorfreshPrice, toolsTotal, toolsLabel, unitTotal, total }
 */
export function calculatePrices() {
  // wp_localize_script sends all values as strings — parse to float
  var base = parseFloat(P.basePrice) || 0;

  // PU price — look up from puOptions by matching layers
  // Use == (loose) because wp_localize_script sends layers as string "1" etc.
  var puPrice = 0;
  if (P.puOptions && S.puLayers !== null) {
    for (var i = 0; i < P.puOptions.length; i++) {
      if (P.puOptions[i].layers == S.puLayers) {
        puPrice = parseFloat(P.puOptions[i].price) || 0;
        break;
      }
    }
  }

  // Primer price — look up by label
  var primerPrice = 0;
  if (P.primerOptions && S.primer) {
    for (var i = 0; i < P.primerOptions.length; i++) {
      if (P.primerOptions[i].label === S.primer) {
        primerPrice = parseFloat(P.primerOptions[i].price) || 0;
        break;
      }
    }
  }

  // Colorfresh price — look up by label
  var colorfreshPrice = 0;
  if (P.colorfresh && S.colorfresh) {
    for (var i = 0; i < P.colorfresh.length; i++) {
      if (P.colorfresh[i].label === S.colorfresh) {
        colorfreshPrice = parseFloat(P.colorfresh[i].price) || 0;
        break;
      }
    }
  }

  // Tool costs — calculated separately (not per-unit)
  var toolsTotal = 0;
  var toolsLabel = '';
  if (P.hasTools && P.toolConfig) {
    var TC = P.toolConfig;
    if (S.toolMode === 'set') {
      toolsTotal = parseFloat(TC.toolSet.price) || 0;
      toolsLabel = TC.toolSet.name;
      // Add extras on top of set
      var extrasTotal = 0;
      var extrasCount = 0;
      TC.extras.forEach(function(e) {
        var st = S.extras[e.id];
        if (st && st.on && st.qty > 0) {
          extrasTotal += getItemPrice(e, st) * st.qty;
          extrasCount += st.qty;
        }
      });
      if (extrasTotal > 0) {
        toolsTotal += extrasTotal;
        toolsLabel += ' + ' + extrasCount + ' extra';
      }
    } else if (S.toolMode === 'individual') {
      var toolLines = [];
      TC.tools.forEach(function(t) {
        var st = S.tools[t.id];
        if (st && st.on && st.qty > 0) {
          var lineTotal = getItemPrice(t, st) * st.qty;
          toolsTotal += lineTotal;
          toolLines.push({ name: t.name, qty: st.qty, total: lineTotal });
        }
      });
      if (toolLines.length === 1) {
        toolsLabel = toolLines[0].name + (toolLines[0].qty > 1 ? ' \u00d7' + toolLines[0].qty : '');
      } else if (toolLines.length > 1) {
        var totalItems = toolLines.reduce(function(sum, l) { return sum + l.qty; }, 0);
        toolsLabel = 'Gereedschap (' + totalItems + (totalItems === 1 ? ' item' : ' items') + ')';
      }
    }
  }

  // Unit total (per-unit price including all addons)
  var unitTotal = base + puPrice + primerPrice + colorfreshPrice;

  // Grand total (unit * quantity) + tools (not per-unit)
  var total = (unitTotal * S.qty) + toolsTotal;

  return {
    base: base,
    puPrice: puPrice,
    primerPrice: primerPrice,
    colorfreshPrice: colorfreshPrice,
    toolsTotal: toolsTotal,
    toolsLabel: toolsLabel,
    unitTotal: unitTotal,
    total: total,
  };
}

/**
 * Validate a RAL color code (4 digits).
 * @param {string} code
 * @return {boolean}
 */
export function validateRal(code) {
  return /^\d{4}$/.test(code.trim());
}

/**
 * Validate an NCS color code (e.g. "S 1050-Y90R").
 * Loose match — allows common NCS formats.
 * @param {string} code
 * @return {boolean}
 */
export function validateNcs(code) {
  return /^S\s?\d{4}-[A-Z]\d{2}[A-Z]$/i.test(code.trim());
}

/**
 * Check if any tools are selected.
 * Returns true for 'set' mode, checks individual selections for 'individual' mode.
 */
export function hasAnyTool(toolMode, tools) {
  if (!P.hasTools || !P.toolConfig) return false;
  if (toolMode === 'set') return true;
  if (toolMode === 'individual') {
    return P.toolConfig.tools.some(function(t) { return tools[t.id] && tools[t.id].on; });
  }
  return false;
}

/**
 * Clamp a quantity value to valid range [1, 99].
 */
export function clampToolQty(current, delta) {
  return Math.max(1, Math.min(99, current + delta));
}

/* Checkmark SVG for tool custom checkbox */
export var CHECKMARK_SVG = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2.5 6l2.5 2.5 4.5-5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

/* Info icon SVG for smart nudge */
export var NUDGE_ICON = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';


/* ═══ DOM HELPERS ═══════════════════════════════════════════ */

// Cache DOM references for frequently accessed elements
export var DOM = {};

export function cacheDom() {
  DOM.page           = document.getElementById('oz-product-page');
  DOM.mainImg        = document.getElementById('mainImg');
  DOM.qtyInput       = document.getElementById('qtyInput');
  DOM.addToCartBtn   = document.getElementById('addToCartBtn');
  DOM.descContent    = document.getElementById('descContent');
  DOM.readMoreBtn    = document.getElementById('readMoreBtn');
  DOM.stickyBar      = document.getElementById('stickyBar');
  DOM.stickyBtn      = document.getElementById('stickyBtn');
  DOM.stickyPrice    = document.getElementById('stickyPrice');
  DOM.sheetOverlay   = document.getElementById('sheetOverlay');
  DOM.bottomSheet    = document.getElementById('bottomSheet');
  DOM.sheetTotal     = document.getElementById('sheetTotal');
  DOM.sheetCtaBtn    = document.getElementById('sheetCtaBtn');
  DOM.optionsWidget  = document.getElementById('optionsWidget');
  DOM.slotDesktop    = document.getElementById('optionsSlotDesktop');
  DOM.desktopHome    = document.getElementById('optionsDesktopHome');
  DOM.slotSheet      = document.getElementById('optionsSlotSheet');
  DOM.colorModeSlot  = document.getElementById('colorModeSlot');
  DOM.colorLabel     = document.getElementById('colorLabel');

  // Price breakdown elements
  DOM.priceBase          = document.getElementById('priceBase');
  DOM.pricePuLine        = document.getElementById('pricePuLine');
  DOM.pricePu            = document.getElementById('pricePu');
  DOM.pricePuLabel       = document.getElementById('pricePuLabel');
  DOM.pricePrimerLine    = document.getElementById('pricePrimerLine');
  DOM.pricePrimer        = document.getElementById('pricePrimer');
  DOM.pricePrimerLabel   = document.getElementById('pricePrimerLabel');
  DOM.priceColorfreshLine = document.getElementById('priceColorfreshLine');
  DOM.priceColorfresh    = document.getElementById('priceColorfresh');
  DOM.priceToolsLine     = document.getElementById('priceToolsLine');
  DOM.priceToolsLabel    = document.getElementById('priceToolsLabel');
  DOM.priceTools         = document.getElementById('priceTools');
  DOM.sheetPriceToolsLine  = document.getElementById('sheetPriceToolsLine');
  DOM.sheetPriceToolsLabel = document.getElementById('sheetPriceToolsLabel');
  DOM.sheetPriceTools      = document.getElementById('sheetPriceTools');
  DOM.upsellOverlay      = document.getElementById('upsellOverlay');
  DOM.upsellAddBtn       = document.getElementById('upsellAddBtn');
  DOM.upsellSkipBtn      = document.getElementById('upsellSkipBtn');
  DOM.priceQtyLine       = document.getElementById('priceQtyLine');
  DOM.priceQtyLabel      = document.getElementById('priceQtyLabel');
  DOM.priceQty           = document.getElementById('priceQty');
  DOM.priceTotal         = document.getElementById('priceTotal');
}

/**
 * Set display style for an element. Null-safe.
 */
export function show(el) { if (el) el.style.display = ''; }
export function hide(el) { if (el) el.style.display = 'none'; }
