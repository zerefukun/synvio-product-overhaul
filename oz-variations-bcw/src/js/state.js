/**
 * Shared State & Pure Functions
 *
 * Contains:
 * - P (ozProduct config from PHP)
 * - S (mutable application state)
 * - updateState() — single gate for all state mutations
 * - All pure helper functions (fmt, calculatePrices, validators)
 * - SVG constants
 *
 * No browser APIs — DOM cache lives in dom.js.
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

  // Selected color name for static/shared color swatches (e.g. Betonlook Verf)
  // Empty string = no color selected yet
  selectedColor: '',

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

  // Generic addon selections — keyed by group key, value is selected label
  // Populated from P.addonGroups defaults below
  addons: {},
} : null;

// Initialize extras state from tool config
if (P && P.hasTools && P.toolConfig && P.toolConfig.extras) {
  P.toolConfig.extras.forEach(function(e) { S.extras[e.id] = { on: false, qty: 0, size: 0 }; });
}
// Initialize individual tools state from tool config
if (P && P.hasTools && P.toolConfig && P.toolConfig.tools) {
  P.toolConfig.tools.forEach(function(t) { S.tools[t.id] = { on: false, qty: 0, size: 0 }; });
}

// Initialize generic addon group state from config defaults
if (P && P.addonGroups && P.addonGroups.length) {
  P.addonGroups.forEach(function(g) {
    S.addons[g.key] = findDefault(g.options, 'label');
  });
}


/* ═══ STATE MUTATION GATE ═══════════════════════════════════ */

/**
 * Single gate for all state mutations.
 * Centralizes writes to S — one place to log, guard, or intercept.
 *
 * @param {Object} patch  Key-value pairs to merge into S
 */
export function updateState(patch) {
  var keys = Object.keys(patch);
  for (var i = 0; i < keys.length; i++) {
    S[keys[i]] = patch[keys[i]];
  }
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
 * Format a price delta. Negative values render as discounts.
 *
 * @param {number} n
 * @return {string}
 */
export function fmtDelta(n) {
  n = parseFloat(n) || 0;
  return n < 0 ? '-' + fmt(Math.abs(n)) : fmt(n);
}

/**
 * Get the active price for a tool/extra item — uses selected size if available.
 */
export function getItemPrice(configItem, stateItem) {
  if (configItem.sizes && stateItem) return parseFloat(configItem.sizes[stateItem.size || 0].price) || 0;
  return parseFloat(configItem.price) || 0;
}

/**
 * Calculate all prices based on product config and current state.
 * Truly pure — all data passed as arguments, testable with plain objects.
 *
 * @param {Object} config  Product config (ozProduct)
 * @param {Object} state   Current UI state
 * @return {Object}  { base, puPrice, primerPrice, colorfreshPrice, toolsTotal, toolsLabel, unitTotal, total }
 */
export function calculatePrices(config, state) {
  // wp_localize_script sends all values as strings — parse to float
  var base = parseFloat(config.basePrice) || 0;

  // PU price — look up from puOptions by matching layers
  // Use == (loose) because wp_localize_script sends layers as string "1" etc.
  var puPrice = 0;
  if (config.puOptions && state.puLayers !== null) {
    for (var i = 0; i < config.puOptions.length; i++) {
      if (config.puOptions[i].layers == state.puLayers) {
        puPrice = parseFloat(config.puOptions[i].price) || 0;
        break;
      }
    }
  }

  // Primer price — look up by label
  var primerPrice = 0;
  if (config.primerOptions && state.primer) {
    for (var i = 0; i < config.primerOptions.length; i++) {
      if (config.primerOptions[i].label === state.primer) {
        primerPrice = parseFloat(config.primerOptions[i].price) || 0;
        break;
      }
    }
  }

  // Colorfresh price — look up by label
  var colorfreshPrice = 0;
  if (config.colorfresh && state.colorfresh) {
    for (var i = 0; i < config.colorfresh.length; i++) {
      if (config.colorfresh[i].label === state.colorfresh) {
        colorfreshPrice = parseFloat(config.colorfresh[i].price) || 0;
        break;
      }
    }
  }

  // Tool costs — calculated separately (not per-unit)
  // Tool costs — calculated separately (not per-m²)
  // toolsDetails: array of { name, qty, total } for each selected item
  var toolsTotal = 0;
  var toolsLabel = '';
  var toolsDetails = [];
  if (config.hasTools && config.toolConfig) {
    var TC = config.toolConfig;
    if (state.toolMode === 'set') {
      toolsTotal = parseFloat(TC.toolSet.price) || 0;
      toolsLabel = TC.toolSet.name;
      toolsDetails.push({ name: TC.toolSet.name, qty: 1, total: toolsTotal });
      // Add extras on top of set
      TC.extras.forEach(function(e) {
        var st = state.extras[e.id];
        if (st && st.on && st.qty > 0) {
          var lineTotal = getItemPrice(e, st) * st.qty;
          toolsTotal += lineTotal;
          // Build name with size if applicable
          var sizeName = (e.sizes && e.sizes[st.size || 0]) ? e.sizes[st.size || 0].label : '';
          var itemName = e.name + (sizeName ? ' ' + sizeName : '');
          toolsDetails.push({ name: itemName, qty: st.qty, total: lineTotal });
        }
      });
      // Summary label: just "Gereedschap" — details are listed separately
      if (toolsDetails.length > 1) {
        toolsLabel = 'Gereedschap';
      }
    } else if (state.toolMode === 'individual') {
      TC.tools.forEach(function(t) {
        var st = state.tools[t.id];
        if (st && st.on && st.qty > 0) {
          var lineTotal = getItemPrice(t, st) * st.qty;
          toolsTotal += lineTotal;
          var sizeName = (t.sizes && t.sizes[st.size || 0]) ? t.sizes[st.size || 0].label : '';
          var itemName = t.name + (sizeName ? ' ' + sizeName : '');
          toolsDetails.push({ name: itemName, qty: st.qty, total: lineTotal });
        }
      });
      if (toolsDetails.length === 1) {
        toolsLabel = toolsDetails[0].name + (toolsDetails[0].qty > 1 ? ' \u00d7' + toolsDetails[0].qty : '');
      } else if (toolsDetails.length > 1) {
        toolsLabel = 'Gereedschap';
      }
    }
  }

  // Generic addon group prices — keyed by group key for price breakdown display
  var addonPrices = {};
  var addonTotal = 0;
  if (config.addonGroups && config.addonGroups.length && state.addons) {
    config.addonGroups.forEach(function(g) {
      var selected = state.addons[g.key];
      var price = 0;
      if (selected) {
        for (var i = 0; i < g.options.length; i++) {
          if (g.options[i].label === selected) {
            price = parseFloat(g.options[i].price) || 0;
            break;
          }
        }
      }
      addonPrices[g.key] = price;
      addonTotal += price;
    });
  }

  // Unit total (per-unit price including all addons)
  var unitTotal = base + puPrice + primerPrice + colorfreshPrice + addonTotal;

  // Grand total (unit * quantity) + tools (not per-unit)
  var total = (unitTotal * state.qty) + toolsTotal;

  return {
    base: base,
    puPrice: puPrice,
    primerPrice: primerPrice,
    colorfreshPrice: colorfreshPrice,
    addonPrices: addonPrices,
    toolsTotal: toolsTotal,
    toolsLabel: toolsLabel,
    toolsDetails: toolsDetails,
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
  // Accepts "4070" or "RAL 4070"
  return /^(RAL\s?)?\d{4}$/i.test(code.trim());
}

/**
 * Validate an NCS color code (e.g. "S 1050-Y90R" or "NCS S 1050-Y90R").
 * Loose match — allows common NCS formats with or without NCS prefix.
 * @param {string} code
 * @return {boolean}
 */
export function validateNcs(code) {
  return /^(NCS\s+)?S\s?\d{4}-[A-Z]\d{2}[A-Z]$/i.test(code.trim());
}

/**
 * Check if any tools are selected.
 * Pure — toolConfig passed explicitly. Returns true for 'set' mode,
 * checks individual selections for 'individual' mode.
 *
 * @param {string}      toolMode    'none', 'set', or 'individual'
 * @param {Object}      tools       Tool state map { id: { on, qty, size } }
 * @param {Object|null} toolConfig  Tool config from product (P.toolConfig)
 * @return {boolean}
 */
export function hasAnyTool(toolMode, tools, toolConfig) {
  if (!toolConfig) return false;
  if (toolMode === 'set') return true;
  if (toolMode === 'individual') {
    return toolConfig.tools.some(function(t) { return tools[t.id] && tools[t.id].on; });
  }
  return false;
}

/**
 * Clamp a quantity value to valid range [1, 99].
 */
export function clampToolQty(current, delta) {
  return Math.max(1, Math.min(99, current + delta));
}

/**
 * Validate cart state before submission.
 * Pure — returns error message string or null if valid.
 *
 * @param {Object} config  Product config (ozProduct)
 * @param {Object} state   Current UI state
 * @return {string|null}  Error message, or null if valid
 */
export function validateCartState(config, state) {
  // RAL/NCS validation
  if (state.colorMode === 'ral_ncs') {
    if (!state.customColor) return 'Vul een RAL of NCS kleurcode in.';
    if (!validateRal(state.customColor) && !validateNcs(state.customColor)) {
      return 'Ongeldige RAL of NCS kleurcode.';
    }
  }
  // Static/shared color validation — must pick a color before adding to cart
  if (config.hasStaticColors && state.colorMode === 'swatch' && !state.selectedColor) {
    return 'Kies een kleur.';
  }
  // Tool validation — individual mode must have at least 1 tool selected
  if (config.hasTools && state.toolMode === 'individual' && !hasAnyTool(state.toolMode, state.tools, config.toolConfig)) {
    return 'Kies minimaal 1 gereedschap of kies een andere optie.';
  }

  // Stock validation — check selected tool/extra sizes are in stock
  if (config.hasTools && config.toolConfig) {
    var TC = config.toolConfig;
    if (state.toolMode === 'set') {
      for (var i = 0; i < TC.extras.length; i++) {
        var ext = TC.extras[i];
        var est = state.extras[ext.id];
        if (est && est.on && ext.sizes && ext.sizes[est.size || 0] && ext.sizes[est.size || 0].inStock === false) {
          return ext.name + ' in deze maat is uitverkocht. Kies een andere maat.';
        }
      }
    } else if (state.toolMode === 'individual') {
      for (var i = 0; i < TC.tools.length; i++) {
        var tool = TC.tools[i];
        var tst = state.tools[tool.id];
        if (tst && tst.on && tool.sizes && tool.sizes[tst.size || 0] && tool.sizes[tst.size || 0].inStock === false) {
          return tool.name + ' in deze maat is uitverkocht. Kies een andere maat.';
        }
      }
    }
  }

  return null;
}

/**
 * Build cart payload as a plain object from product config and state.
 * Pure — no I/O, no DOM, no FormData. Testable with plain objects.
 *
 * @param {Object} config  Product config (ozProduct)
 * @param {Object} state   Current UI state
 * @return {Object}  Key-value pairs for the cart form data
 */
export function buildCartPayload(config, state) {
  var payload = {
    action: 'oz_bcw_add_to_cart',
    nonce: config.nonce,
    product_id: config.productId,
    quantity: state.qty,
  };

  // Addon fields — same keys as OZ_Cart_Manager::extract_post_data()
  if (state.puLayers !== null)  payload.oz_pu_layers = state.puLayers;
  if (state.primer)             payload.oz_primer = state.primer;
  if (state.colorfresh)         payload.oz_colorfresh = state.colorfresh;
  if (state.toepassing)         payload.oz_toepassing = state.toepassing;
  if (state.pakket)             payload.oz_pakket = state.pakket;

  // Generic addon selections — oz_addon_{key} = selected label
  if (config.addonGroups && config.addonGroups.length && state.addons) {
    config.addonGroups.forEach(function(g) {
      if (state.addons[g.key]) {
        payload['oz_addon_' + g.key] = state.addons[g.key];
      }
    });
  }

  // Color mode
  payload.oz_color_mode = state.colorMode;
  if (state.colorMode === 'ral_ncs') {
    payload.oz_custom_color = state.customColor;
  }
  // Static/shared color swatch selection (single-product lines like Betonlook Verf)
  if (state.colorMode === 'swatch' && state.selectedColor) {
    payload.oz_selected_color = state.selectedColor;
  }

  // Tool data — nested keys need special handling when converting to FormData
  if (config.hasTools) {
    payload.oz_tool_mode = state.toolMode;
    if (state.toolMode === 'set') {
      payload.oz_tool_set_id = config.toolConfig.toolSet.id;
      // Extras on top of set — stored as nested object for FormData expansion
      var extras = {};
      config.toolConfig.extras.forEach(function(e) {
        var st = state.extras[e.id];
        if (st && st.on && st.qty > 0) {
          var sizeData = e.sizes ? e.sizes[st.size || 0] : e;
          extras[e.id] = { qty: st.qty, wcId: sizeData.wcId, price: sizeData.price };
          // Include size label so cart can display which size was chosen
          if (e.sizes) extras[e.id].sizeLabel = sizeData.label;
          if (sizeData.wapoAddon) extras[e.id].wapoAddon = sizeData.wapoAddon;
        }
      });
      payload._extras = extras;
    } else if (state.toolMode === 'individual') {
      var tools = {};
      config.toolConfig.tools.forEach(function(t) {
        var st = state.tools[t.id];
        if (st && st.on && st.qty > 0) {
          var sizeData = t.sizes ? t.sizes[st.size || 0] : t;
          tools[t.id] = { qty: st.qty, wcId: sizeData.wcId, price: sizeData.price };
          if (t.sizes) tools[t.id].sizeLabel = sizeData.label;
          if (sizeData.wapoAddon) tools[t.id].wapoAddon = sizeData.wapoAddon;
        }
      });
      payload._tools = tools;
    }
  }

  return payload;
}

/* Checkmark SVG for tool custom checkbox */
export var CHECKMARK_SVG = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2.5 6l2.5 2.5 4.5-5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

/* Warning icon SVG for smart nudge — filled triangle for visibility on amber background */
export var NUDGE_ICON = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L1 21h22L12 2zm0 4l7.53 13H4.47L12 6zm-1 5v4h2v-4h-2zm0 6v2h2v-2h-2z"/></svg>';
