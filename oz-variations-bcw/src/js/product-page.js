/**
 * OZ Variations BCW — Product Page Entry Point
 *
 * Handles:
 * - Option selection (PU, primer, colorfresh, toepassing, pakket)
 * - Real-time price calculation + breakdown
 * - RAL/NCS custom color mode with validation
 * - Gallery image switching with crossfade
 * - Quantity +/- controls
 * - AJAX add-to-cart (oz_bcw_add_to_cart)
 * - Mobile sticky bar (IntersectionObserver)
 * - Bottom sheet for mobile options
 * - Read more toggle
 * - Info tooltip toggles
 * - Upsell modal
 *
 * All DOM interaction uses event delegation on #oz-product-page.
 * No jQuery dependency.
 *
 * @package OZ_Variations_BCW
 * @since 2.0.0
 */

import { P, S, updateState, fmt, fmtDelta, findDefault, _originalP, calculatePrices, validateRal, validateNcs, hasAnyTool, clampToolQty, validateCartState, buildCartPayload } from './state.js';
import { DOM, cacheDom, show, hide } from './dom.js';
import { setToolSyncCallback, buildToolSectionV2, syncToolSectionV2, buildRuimteDropdown } from './tools.js';
import { initNavigation, navigateToVariant, swapMainImage, createThumb } from './navigation.js';
import { setupColorDrawer } from './color-drawer.js';
import { initFrequentlyBought } from './frequently-bought.js';
import * as analytics from './analytics.js';

// Guard: only run on pages with ozProduct data
if (!P) {
  // No-op — not a product page, bail out
} else {


/* ═══ FORM DATA CONVERSION ════════════════════════════════ */

/**
 * Convert a cart payload object to FormData.
 * Handles the nested _extras and _tools objects as bracketed keys.
 * Lives in the shell (not state.js) because FormData is a browser API.
 *
 * @param {Object} payload  From buildCartPayload()
 * @return {FormData}
 */
function payloadToFormData(payload) {
  var data = new FormData();

  Object.keys(payload).forEach(function(key) {
    // Skip internal nested objects — expanded separately below
    if (key === '_extras' || key === '_tools') return;
    data.append(key, payload[key]);
  });

  // Expand nested extras: oz_extras[id][field]
  if (payload._extras) {
    Object.keys(payload._extras).forEach(function(id) {
      var item = payload._extras[id];
      Object.keys(item).forEach(function(field) {
        data.append('oz_extras[' + id + '][' + field + ']', item[field]);
      });
    });
  }

  // Expand nested tools: oz_tools[id][field]
  if (payload._tools) {
    Object.keys(payload._tools).forEach(function(id) {
      var item = payload._tools[id];
      Object.keys(item).forEach(function(field) {
        data.append('oz_tools[' + id + '][' + field + ']', item[field]);
      });
    });
  }

  return data;
}


/* ═══ RENDERERS ══════════════════════════════════════════════ */

/**
 * Master render — updates all dynamic UI from state.
 * Called after every state change.
 */
/**
 * Format color for display. ZM mode shows only the 4-digit code.
 * K&K and other lines show the full color name.
 */
function displayColor(colorName) {
  if (!colorName) return '';
  // Strip to 4-digit code only when the *currently displayed* line is ZM.
  // toggleFormula keeps P.productLine in sync with the active mode, so this
  // works on both pages (K&K toggled to ZM, and ZM as direct landing).
  if (P.productLine && P.productLine.indexOf('-zm') !== -1) {
    var match = colorName.match(/\b(\d{4})\s*$/);
    if (match) return match[1];
  }
  return colorName;
}

function syncUI() {
  var prices = calculatePrices(P, S);

  // Persist current state to sessionStorage (debounced) so a
  // cart→checkout→bounce-back to PDP restores the user's options.
  // No-op if persistPdpState isn't defined yet (early init).
  if (typeof persistPdpState === 'function') persistPdpState();

  // Update price breakdown
  renderBreakdown(prices);

  // Update sticky bar — desktop price + mobile price + options summary
  if (DOM.stickyPrice) DOM.stickyPrice.textContent = fmt(prices.total);
  if (DOM.stickyPriceMobile) DOM.stickyPriceMobile.textContent = fmt(prices.total);
  if (DOM.displayBasePrice) DOM.displayBasePrice.textContent = fmt(prices.unitTotal);
  renderStickySummary();

  // Update sheet total
  if (DOM.sheetTotal) DOM.sheetTotal.textContent = fmt(prices.total);

  // Highlight selected option buttons
  renderOptionHighlights();

  // Update color mode UI
  renderColorMode();

  // Update selected labels in headers
  renderSelectedLabels();

  // Render tool section (if this product has tools)
  if (P.hasTools) {
    syncToolSectionV2("toolSection", S.toolMode, S.tools, S.extras, S.qty);
  }

  // Enable/disable cart button based on validation state.
  // Base products start with oz-disabled; remove it when a valid color is chosen (e.g. RAL/NCS).
  var error = validateCartState(P, S);
  if (DOM.addToCartBtn) {
    DOM.addToCartBtn.classList.toggle('oz-disabled', !!error);
  }

  // Update sticky buttons based on validation state
  var stickyLabel = 'In winkelmand';
  if (P.isBase && error) {
    stickyLabel = 'Kies kleur';
  } else if (P.toepassing && P.toepassing.length && !S.toepassing) {
    stickyLabel = 'Kies toepassing';
  }
  if (DOM.stickyBtn)  DOM.stickyBtn.textContent  = stickyLabel;
  if (DOM.stickyDBtn) DOM.stickyDBtn.textContent = stickyLabel;
}

/**
 * Render tool detail lines in a price summary.
 * When tools are selected, shows each item (set + extras, or individual picks)
 * as its own line with name, qty, and price.
 * Dynamically inserts/removes divs after the anchor element.
 *
 * @param {Object}  prices     From calculatePrices()
 * @param {Element} anchor     The priceToolsLine element to anchor after
 * @param {string}  lineClass  CSS class for each line ('oz-price-line' or 'oz-sheet-price-line')
 */
function renderToolDetails(prices, anchor, lineClass) {
  if (!anchor) return;

  // Remove any previously rendered detail lines
  var parent = anchor.parentNode;
  var existing = parent.querySelectorAll('.oz-tool-detail-line');
  for (var i = 0; i < existing.length; i++) {
    // Only remove lines that belong to this anchor's parent
    if (existing[i].parentNode === parent) existing[i].remove();
  }

  // If no tools selected, hide the anchor and bail
  if (prices.toolsTotal <= 0 || !prices.toolsDetails || prices.toolsDetails.length === 0) {
    hide(anchor);
    return;
  }

  // Single tool item — use the existing anchor line (no sub-lines needed)
  if (prices.toolsDetails.length === 1) {
    show(anchor);
    var d = prices.toolsDetails[0];
    var label = d.name + (d.qty > 1 ? ' \u00d7' + d.qty : '');
    anchor.querySelector('span:first-child').textContent = label;
    anchor.querySelector('span:last-child').textContent = fmt(d.total);
    return;
  }

  // Multiple items — hide the summary anchor, render each item as its own line
  hide(anchor);
  var insertBefore = anchor.nextSibling;
  for (var j = 0; j < prices.toolsDetails.length; j++) {
    var detail = prices.toolsDetails[j];
    var div = document.createElement('div');
    div.className = lineClass + ' oz-tool-detail-line';
    var nameSpan = document.createElement('span');
    nameSpan.textContent = detail.name + (detail.qty > 1 ? ' \u00d7' + detail.qty : '');
    var priceSpan = document.createElement('span');
    priceSpan.textContent = fmt(detail.total);
    div.appendChild(nameSpan);
    div.appendChild(priceSpan);
    parent.insertBefore(div, insertBefore);
  }
}

/**
 * Render the price breakdown panel.
 * Data-driven: each price line is described as { line, value, el, labelEl?, label? }.
 * One generic loop replaces 6 repetitions of show/hide + setText.
 */
function renderBreakdown(prices) {
  // Desktop base price — annotate with unit when qty > 1 and product is m²-based
  // Helps customer understand the breakdown lines are per-unit, not total
  var isM2 = (parseFloat(P.unitM2) || 0) > 0;
  var perUnit = (S.qty > 1 && isM2) ? ' (per ' + P.unit + ')' : '';
  if (DOM.priceBaseLabel) DOM.priceBaseLabel.textContent = P.productName + perUnit;
  if (DOM.priceBase) DOM.priceBase.textContent = fmt(prices.base);

  // Each line: row element, value to check, price element, optional label element + text
  var lines = [
    {
      line: DOM.pricePuLine,
      value: prices.puPrice,
      el: DOM.pricePu,
      labelEl: DOM.pricePuLabel,
      label: S.puLayers === 0 ? 'Geen PU' : 'PU Toplaag'
    },
    { line: DOM.pricePrimerLine,        value: prices.primerPrice,     el: DOM.pricePrimer,        labelEl: DOM.pricePrimerLabel,        label: 'Primer: ' + S.primer },
    { line: DOM.priceColorfreshLine,    value: prices.colorfreshPrice, el: DOM.priceColorfresh },
  ];

  // Generic render: show line if value > 0, update price + optional label
  for (var i = 0; i < lines.length; i++) {
    var item = lines[i];
    if (!item.line) continue;
    if (item.value !== 0) {
      show(item.line);
      if (item.el) item.el.textContent = fmtDelta(item.value);
      if (item.labelEl && item.label) item.labelEl.textContent = item.label;
    } else {
      hide(item.line);
    }
  }

  // Generic addon group price lines — show/hide each group's price line
  if (P.addonGroups && prices.addonPrices) {
    P.addonGroups.forEach(function(g) {
      var lineEl = document.getElementById('priceAddon_' + g.key + 'Line');
      var priceEl = document.getElementById('priceAddon_' + g.key);
      if (lineEl && priceEl) {
        if (prices.addonPrices[g.key] !== 0) {
          show(lineEl);
          priceEl.textContent = fmtDelta(prices.addonPrices[g.key]);
        } else {
          hide(lineEl);
        }
      }
    });
  }

  // Tool detail lines — render each tool/extra as a sub-line in the breakdown.
  // Uses the single priceToolsLine as an anchor point; detail divs are inserted after it.
  renderToolDetails(prices, DOM.priceToolsLine, 'oz-price-line');

  // Quantity / m² subtotal line — shows when qty > 1
  // m²-based: "20 m² (4×)" → subtotal   |   non-m²: "3 stuks" → subtotal
  // This line sits ABOVE tools in the DOM (template reordered), creating a natural
  // visual separator between per-m² costs and one-time costs (tools).
  var m2PerUnit = parseFloat(P.unitM2) || 0;
  var qtyLabel = isM2
    ? (S.qty * m2PerUnit) + ' m² (' + S.qty + '×)'
    : S.qty + ' stuks';
  var qtySubtotal = fmt(prices.unitTotal * S.qty);

  if (DOM.priceQtyLine) {
    if (S.qty > 1) {
      show(DOM.priceQtyLine);
      DOM.priceQtyLabel.textContent = qtyLabel;
      DOM.priceQty.textContent = qtySubtotal;
    } else {
      hide(DOM.priceQtyLine);
    }
  }
  // Total
  if (DOM.priceTotal) DOM.priceTotal.textContent = fmt(prices.total);
}

/**
 * Highlight the selected button in each option group.
 * Data-driven: each option type is described as { attr, value, parse? }.
 * One generic loop replaces 5 repetitions.
 */
function renderOptionHighlights() {
  var highlights = [
    { attr: 'data-pu',         value: S.puLayers,    parse: function(v) { return parseInt(v, 10); } },
    { attr: 'data-primer',     value: S.primer },
    { attr: 'data-colorfresh', value: S.colorfresh },
    { attr: 'data-toepassing', value: S.toepassing },
    { attr: 'data-pakket',     value: S.pakket },
  ];

  for (var h = 0; h < highlights.length; h++) {
    var spec = highlights[h];
    // Scope to buttons only. Variant C's <option> elements in the
    // ruimte dropdown carry the same data-primer / data-pu attrs but
    // shouldn't get the .selected button-state class.
    var btns = document.querySelectorAll('button[' + spec.attr + ']');
    for (var i = 0; i < btns.length; i++) {
      var val = spec.parse ? spec.parse(btns[i].getAttribute(spec.attr)) : btns[i].getAttribute(spec.attr);
      btns[i].classList.toggle('selected', val === spec.value);
    }
  }

  // Generic addon buttons (data-addon-key / data-addon-value). Click handler
  // sets .selected imperatively, but a state restore from sessionStorage
  // doesn't trigger that handler — sync the highlight here so restored
  // addon picks render correctly.
  if (S.addons) {
    var addonBtns = document.querySelectorAll('button[data-addon-key]');
    for (var a = 0; a < addonBtns.length; a++) {
      var key = addonBtns[a].getAttribute('data-addon-key');
      var val = addonBtns[a].getAttribute('data-addon-value');
      addonBtns[a].classList.toggle('selected', S.addons[key] === val);
    }
  }
}

/**
 * Update the "selected value" labels in option headers.
 */
function renderSelectedLabels() {
  // Toepassing selected label + required indicator
  var tpLabel = document.getElementById('selectedToepassingLabel');
  if (tpLabel) {
    tpLabel.textContent = S.toepassing || '';
  }
  var tpStar = document.getElementById('toepassingRequired');
  if (tpStar) {
    tpStar.style.display = S.toepassing ? 'none' : '';
  }

  // Color label in header — shows selected static color, RAL/NCS code, or product color
  var colorLabel = document.getElementById('selectedColorLabel');
  if (colorLabel) {
    if (S.colorMode === 'ral_ncs' && S.customColor) {
      colorLabel.textContent = S.customColor;
    } else if (S.selectedColor) {
      colorLabel.textContent = displayColor(S.selectedColor);
    } else if (P.currentColor) {
      colorLabel.textContent = displayColor(P.currentColor);
    }
  }

  // Big color label above product title (for shared-color products)
  if (DOM.colorLabel) {
    if (S.colorMode === 'ral_ncs' && S.customColor) {
      DOM.colorLabel.textContent = S.customColor;
      DOM.colorLabel.style.display = '';
    } else if (S.selectedColor) {
      DOM.colorLabel.textContent = displayColor(S.selectedColor);
      DOM.colorLabel.style.display = '';
    } else if (!P.currentColor && P.hasStaticColors) {
      // No color picked yet — hide the empty label
      DOM.colorLabel.style.display = 'none';
    }
  }
}

/**
 * Update the desktop sticky bar elements: color, options summary, qty.
 * Layout: [Title] [Color] [Options...] [Price Qty CTA]
 */
function renderStickySummary() {
  var sep = '<span class="oz-sep">&middot;</span>';

  // Update color text — static selected color, RAL/NCS, or product color
  if (DOM.stickyDColor) {
    if (S.colorMode === 'ral_ncs' && S.customColor) {
      DOM.stickyDColor.textContent = S.customColor;
    } else if (S.selectedColor) {
      DOM.stickyDColor.textContent = displayColor(S.selectedColor);
    } else {
      DOM.stickyDColor.textContent = displayColor(P.currentColor || '');
    }
  }

  // Mobile sticky color name — update when static color selected
  var stickyColorName = document.getElementById('stickyColorName');
  var stickyColorWrap = document.getElementById('stickyColorWrap');
  if (stickyColorName) {
    var mobileColor = '';
    if (S.colorMode === 'ral_ncs' && S.customColor) {
      mobileColor = S.customColor;
    } else if (S.selectedColor) {
      mobileColor = displayColor(S.selectedColor);
    } else {
      mobileColor = displayColor(P.currentColor || '');
    }
    stickyColorName.textContent = mobileColor;
    // Show/hide the wrap for shared-color products
    if (stickyColorWrap) {
      stickyColorWrap.style.display = mobileColor ? '' : 'none';
    }
  }

  // Build options summary (everything except color — that has its own element)
  if (DOM.stickyDOptions) {
    var parts = [];

    // Formula mode label (K&K / ZM) — first so client/admin can see mode
    // matches URL, diagnostic aid against pushState/cache desync
    if (P.modeToggle && P.modeToggle.labelSelf && P.modeToggle.labelTarget) {
      var modeLabel = S.formulaMode === 'target' ? P.modeToggle.labelTarget : P.modeToggle.labelSelf;
      parts.push(modeLabel);
    }

    // PU layers — always show, even "Geen PU"
    if (S.puLayers !== null && S.puLayers !== undefined) {
      if (S.puLayers === 0) {
        parts.push('Geen PU');
      } else {
        parts.push(S.puLayers + ' PU ' + (S.puLayers === 1 ? 'laag' : 'lagen'));
      }
    }

    // Primer — only show when primer is a customer option (not for ZM)
    if (S.primer && P.primerOptions) {
      parts.push('Primer: ' + S.primer);
    }

    // Colorfresh
    if (S.colorfresh && S.colorfresh !== 'Zonder Colorfresh') {
      parts.push(S.colorfresh);
    }

    // Toepassing
    if (S.toepassing) {
      parts.push(S.toepassing);
    }

    // Pakket
    if (S.pakket) {
      parts.push(S.pakket);
    }

    // Tool mode
    if (S.toolMode === 'set') {
      parts.push('Gereedschapset');
    } else if (S.toolMode === 'individual') {
      parts.push('Gereedschap');
    }

    DOM.stickyDOptions.innerHTML = parts.join(sep);
  }

  // Update qty display
  if (DOM.stickyDQty) {
    DOM.stickyDQty.textContent = S.qty + '×';
    DOM.stickyDQty.setAttribute('data-qty', S.qty);
  }
}

/**
 * Build and manage the RAL/NCS color mode UI.
 * Only rendered when hasRalNcs is true.
 * Inserts into #colorModeSlot.
 */
function renderColorMode() {
  if (!P.hasRalNcs || !DOM.colorModeSlot) return;

  // Build HTML once (only if no element children — ignores HTML comments)
  if (!DOM.colorModeSlot.querySelector('.oz-color-mode-btn, .oz-custom-color-wrap')) {
    buildColorModeUI();
  }

  // Update active state on mode buttons
  var modeBtns = DOM.colorModeSlot.querySelectorAll('.oz-color-mode-btn');
  for (var i = 0; i < modeBtns.length; i++) {
    modeBtns[i].classList.toggle('active', modeBtns[i].getAttribute('data-mode') === S.colorMode);
  }

  // Show/hide custom color input
  var customWrap = DOM.colorModeSlot.querySelector('.oz-custom-color-wrap');
  if (customWrap) {
    customWrap.classList.toggle('visible', S.colorMode === 'ral_ncs');
  }

  // Show/hide swatches with smooth animation (CSS class toggle, not display:none)
  var swatches = document.querySelector('.oz-color-swatches');
  if (swatches) {
    swatches.classList.toggle('hidden', S.colorMode === 'ral_ncs');
  }
}

/**
 * Build the color mode toggle buttons + custom input field.
 * Called once, inserts into #colorModeSlot.
 */
function buildColorModeUI() {
  var html = '';

  // Mode toggle buttons (only show if not ral_ncs_only — those have no swatch option)
  if (!P.ralNcsOnly) {
    html += '<div class="oz-color-mode-buttons">';
    html += '<button class="oz-color-mode-btn active" data-mode="swatch">Standaard kleuren</button>';
    html += '<button class="oz-color-mode-btn" data-mode="ral_ncs">RAL / NCS</button>';
    html += '</div>';
  }

  // Custom color input — accepts any color code, advises RAL/NCS
  html += '<div class="oz-custom-color-wrap' + (P.ralNcsOnly ? ' visible' : '') + '">';
  html += '<div class="oz-color-input-row">';
  html += '<input type="text" class="oz-custom-color-input" id="customColorInput" ';
  html += 'placeholder="Bijv. RAL 7016 of NCS S 2005-Y20R">';
  html += '</div>';
  html += '<div class="oz-custom-color-hint" id="customColorHint"></div>';
  html += '<div class="oz-custom-color-info">';
  html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';
  html += '<span>Wij mengen elke kleurcode op maat. RAL en NCS aanbevolen.</span>';
  html += '</div>';
  html += '</div>';

  DOM.colorModeSlot.innerHTML = html;
}


/* ═══ FORMULA TOGGLE (K&K <-> Zelf Mengen & Mixen) ═════════ */

// Store pre-toggle state for toggle-back restoration
// Updated every time we toggle TO ZM (captures current color variant)
var _preToggleUrl = P.modeToggle ? location.href : null;
var _preToggleProductId = P ? P.productId : null;
var _preToggleIsBase = P ? P.isBase : false;
var _preToggleBasePrice = P ? P.basePrice : 0;
var _preToggleProductName = P ? P.productName : '';
var _preToggleMainImgSrc = '';
var _preToggleGalleryHtml = '';

/**
 * Toggle between K&K and ZM formula modes.
 * Swaps product config, options, content, URL, and tool set.
 *
 * @param {string}  mode          'self' (current/original line) or 'target' (toggled line)
 * @param {boolean} fromPopstate  true when driven by browser back/forward
 *                                — skip internal pushState (URL already updated)
 */
function toggleFormula(mode, fromPopstate) {
  if (!P.modeToggle || S.formulaMode === mode) return;

  var prevMode = S.formulaMode;
  var MT = P.modeToggle;

  // Track the toggle
  var fromLabel = prevMode === 'self' ? MT.labelSelf : MT.labelTarget;
  var toLabel = mode === 'self' ? MT.labelSelf : MT.labelTarget;
  analytics.trackFormulaToggled(fromLabel, toLabel);

  updateState({ formulaMode: mode });

  if (mode === 'target') {
    // Save current state for toggle-back (captures current color variant)
    _preToggleUrl = location.href;
    _preToggleProductId = P.productId;
    _preToggleIsBase = P.isBase;
    _preToggleBasePrice = P.basePrice;
    _preToggleProductName = P.productName;
    _preToggleMainImgSrc = DOM.mainImg ? DOM.mainImg.src : '';
    var galleryEl = document.querySelector('.oz-gallery-thumbs');
    _preToggleGalleryHtml = galleryEl ? galleryEl.innerHTML : '';

    // Swap P properties to toggle target config
    P.productId     = MT.targetProductId;
    P.productName   = MT.targetProductName;
    P.basePrice     = MT.targetBasePrice;
    P.productLine   = MT.targetLine;
    P.unit          = MT.targetUnit;
    P.unitM2        = MT.targetUnitM2;
    P.puOptions     = MT.targetPuOptions;
    P.primerOptions = MT.targetPrimerOptions;
    P.toepassing    = MT.targetToepassing;
    P.optionOrder   = MT.targetOptionOrder;
    P.hasTools      = MT.targetHasTools;
    P.toolConfig    = MT.targetToolConfig;
    P.isBase        = false;  // ZM is always a single product

    // Preserve compatible options, default only what's new/different
    updateState({
      // PU layers: keep current selection (same 0-3 range on both sides)
      puLayers:   S.puLayers,
      // Primer: ZM has no customer-facing primer, clear it
      primer:     null,
      // Toepassing: no default — user must choose (Vloer or Overige)
      toepassing: null,
      // Color: preserve from current swatch
      selectedColor: S.selectedColor || P.currentColor || '',
      // Tools: if K&K set was selected, auto-switch to ZM set
      toolMode:   S.toolMode === 'set' ? 'set' : S.toolMode,
      // Qty: preserved automatically (not in this patch)
    });

    // Swap content sections from pre-loaded data
    swapContent(MT);

    // pushState to target URL (skip when driven by popstate — browser already there)
    if (!fromPopstate) {
      history.pushState(
        { productId: MT.targetProductId, formulaMode: 'target' },
        '', MT.targetUrl
      );
    }
  } else {
    // Restore K&K config values (PU options, primer options, tools, etc.)
    if (_originalP) {
      var keys = Object.keys(_originalP);
      for (var i = 0; i < keys.length; i++) {
        P[keys[i]] = _originalP[keys[i]];
      }
    }
    // Restore the product ID and isBase to the pre-toggle state
    // (user may have navigated to a color variant before toggling)
    P.productId = _preToggleProductId;
    P.isBase = _preToggleIsBase;
    P.basePrice = _preToggleBasePrice;
    P.productName = _preToggleProductName;

    // Preserve compatible options back to self-line
    // If self IS K&K: clear selectedColor (K&K uses URL-driven variant navigation).
    // If self IS ZM: keep selectedColor (ZM uses static swatch click, selection
    // must survive toggle-back or the color label vanishes).
    var selfIsZm = (P.productLine || '').indexOf('-zm') !== -1;
    updateState({
      // PU layers: keep current selection
      puLayers:   S.puLayers,
      // Primer: restore default (hidden on ZM, so only affects K&K)
      primer:     findDefault(P.primerOptions, 'label'),
      // Toepassing: only ZM has it; K&K clears
      toepassing: selfIsZm ? S.toepassing : null,
      // Color: preserve on ZM, clear on K&K
      selectedColor: selfIsZm ? S.selectedColor : '',
      // Tools: if ZM set was selected, auto-switch to K&K set
      toolMode:   S.toolMode === 'set' ? 'set' : S.toolMode,
    });

    // Restore original content
    restoreContent();

    // pushState back to pre-toggle URL (skip when driven by popstate)
    if (!fromPopstate) {
      history.pushState(
        { productId: P.productId, formulaMode: 'self' },
        '', _preToggleUrl
      );
    }
  }

  // isZM reflects the CURRENT line after toggle, not the raw mode — the page may
  // be the ZM product itself (user lands via search), in which case mode='target'
  // actually means K&K. P.productLine is the reliable signal.
  var isZM = (P.productLine || '').indexOf('-zm') !== -1;

  // Swap images: ZM shows old bucket photos, K&K shows new avif photos
  swapVariantImages(isZM);

  // Swap showcase sections — each line has its own (or none)
  var selfShowcase = document.querySelector('.oz-showcase[data-showcase-mode="self"]');
  var targetShowcase = document.querySelector('.oz-showcase[data-showcase-mode="target"]');
  if (selfShowcase) selfShowcase.style.display = isZM ? 'none' : '';
  if (targetShowcase) targetShowcase.style.display = isZM ? '' : 'none';

  // Hide/show primer section — ZM includes primer, no customer choice
  var primerGroup = document.querySelector('[data-option="primer"]');
  if (primerGroup) primerGroup.style.display = isZM ? 'none' : '';

  // "Incl. primer" subtitle
  var subtitle = document.getElementById('formulaSubtitle');
  if (subtitle) subtitle.style.display = isZM ? '' : 'none';

  // Rebuild toepassing section (appears in ZM, hidden in K&K)
  rebuildToggleOptions();

  // Rebuild PU buttons with correct prices
  rebuildPuOptions();

  // Rebuild tool section with correct tool set (clear + rebuild)
  if (P.hasTools) {
    updateState({ toolMode: 'none', extras: {}, tools: {} });
    // Re-init extras/tools state from new config
    if (P.toolConfig && P.toolConfig.extras) {
      P.toolConfig.extras.forEach(function(e) { S.extras[e.id] = { on: false, qty: 0, size: 0 }; });
    }
    if (P.toolConfig && P.toolConfig.tools) {
      P.toolConfig.tools.forEach(function(t) { S.tools[t.id] = { on: false, qty: 0, size: 0 }; });
    }
    buildToolSectionV2('toolSection', true);
  }

  // Update product title in DOM
  if (DOM.productTitle) {
    // Strip color suffix from product name for the title
    var titleText = P.productName || '';
    if (P.currentColor) {
      titleText = titleText.replace(/\s*\([^)]+\)\s*$/, '').replace(new RegExp('\\s+' + P.currentColor.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*$', 'i'), '');
    }
    DOM.productTitle.textContent = titleText;
  }

  // Update toggle button highlight
  var toggleBtns = document.querySelectorAll('.oz-formula-btn');
  for (var b = 0; b < toggleBtns.length; b++) {
    toggleBtns[b].classList.toggle('selected', toggleBtns[b].dataset.formula === mode);
  }

  // Update per-unit labels
  var perUnits = document.querySelectorAll('.oz-per-unit');
  for (var u = 0; u < perUnits.length; u++) {
    perUnits[u].textContent = 'per ' + P.unit;
  }
  var m2Notes = document.querySelectorAll('.oz-m2-note');
  for (var m = 0; m < m2Notes.length; m++) {
    m2Notes[m].textContent = 'per ' + P.unit;
  }

  syncUI();
}

/**
 * Swap variant images between K&K and ZM modes.
 * ZM mode uses old bucket photos (zmImage/zmFullImage), K&K uses default.
 * Also swaps swatch thumbnails and rebuilds gallery strip.
 */
function swapVariantImages(toZM) {
  var currentColor = S.selectedColor || P.currentColor || '';
  var MT = P.modeToggle || {};

  if (toZM) {
    // Swap main image to ZM version for current color; if no color is selected
    // (user is on the base product), fall back to the ZM product's own featured
    // image from modeToggle — variants[baseId] has K&K image only, which would
    // leave the main photo unchanged during a toggle from base K&K → ZM.
    var zmFull = findVariantField(currentColor, 'zmFullImage') || findVariantField(currentColor, 'fullImage');
    if (!currentColor && MT.zmBaseImage) {
      zmFull = MT.zmBaseImage;
    }
    if (zmFull) swapMainImage(zmFull);

    // Rebuild gallery: ZM color image + ZM product generic gallery
    rebuildGalleryForZM(currentColor);
  } else {
    // Restore K&K image for the color the user had selected in ZM mode
    var selectedColor = S.selectedColor || P.currentColor || '';
    var kkFull = findVariantField(selectedColor, 'fullImage');
    if (!selectedColor && MT.kkBaseImage) {
      kkFull = MT.kkBaseImage;
    }
    if (kkFull) {
      swapMainImage(kkFull);
      // Also navigate to the K&K variant so gallery + URL match the color
      var vKeys = Object.keys(P.variants);
      for (var vi = 0; vi < vKeys.length; vi++) {
        if (P.variants[vKeys[vi]].color === selectedColor) {
          var kkV = P.variants[vKeys[vi]];
          // Rebuild gallery from K&K variant data
          var container = document.querySelector('.oz-gallery-thumbs');
          if (container) {
            container.innerHTML = '';
            container.style.display = '';
            if (kkV.image && kkV.fullImage) {
              container.appendChild(createThumb(kkV.image, kkV.fullImage, 0, true));
            }
            var gallery = kkV.gallery || [];
            for (var gi = 0; gi < gallery.length; gi++) {
              container.appendChild(createThumb(gallery[gi].thumb, gallery[gi].full, gi + 1, false));
            }
            if (container.children.length <= 1) container.style.display = 'none';
          }
          break;
        }
      }
    } else if (_preToggleMainImgSrc && DOM.mainImg) {
      // Fallback: no color was selected, restore pre-toggle snapshot
      DOM.mainImg.src = _preToggleMainImgSrc;
      var galleryEl = document.querySelector('.oz-gallery-thumbs');
      if (galleryEl && _preToggleGalleryHtml) {
        galleryEl.innerHTML = _preToggleGalleryHtml;
        galleryEl.style.display = '';
      }
    }
  }

  // Swap swatch <img> tags and labels
  var swatches = document.querySelectorAll('.oz-color-swatch[data-color]');
  for (var i = 0; i < swatches.length; i++) {
    var colorName = swatches[i].getAttribute('data-color');
    var img = swatches[i].querySelector('.oz-swatch-img img');
    if (!colorName) continue;

    if (img) {
      var src = toZM
        ? (findVariantField(colorName, 'zmImage') || findVariantField(colorName, 'image'))
        : findVariantField(colorName, 'image');
      if (src) img.src = src;
    }

    // Swap label: ZM shows only number code, K&K shows full name
    var label = swatches[i].querySelector('.oz-swatch-name');
    if (label) {
      if (toZM) {
        var codeMatch = colorName.match(/\b(\d{4})\s*$/);
        label.textContent = codeMatch ? codeMatch[1] : colorName;
      } else {
        label.textContent = colorName;
      }
    }
  }
}

/** Find a field value from P.variants by matching color name. */
function findVariantField(colorName, fieldName) {
  if (!colorName || !P.variants) return '';
  var keys = Object.keys(P.variants);
  for (var i = 0; i < keys.length; i++) {
    if (P.variants[keys[i]].color === colorName) {
      return P.variants[keys[i]][fieldName] || '';
    }
  }
  return '';
}

/** Rebuild gallery strip for ZM mode: color bucket image + ZM product lifestyle gallery. */
function rebuildGalleryForZM(colorName) {
  var container = document.querySelector('.oz-gallery-thumbs');
  if (!container) return;
  container.innerHTML = '';
  container.style.display = '';

  // ZM bucket image for the selected color as first thumb
  var zmThumb = findVariantField(colorName, 'zmImage') || findVariantField(colorName, 'image');
  var zmFull = findVariantField(colorName, 'zmFullImage') || findVariantField(colorName, 'fullImage');
  if (zmThumb && zmFull) {
    container.appendChild(createThumb(zmThumb, zmFull, 0, true));
  }

  // ZM product generic gallery images — named by formula so it works whether
  // ZM is the self or target side of the toggle.
  var zmGallery = (P.modeToggle && (P.modeToggle.zmGallery || P.modeToggle.targetGallery)) || [];
  for (var i = 0; i < zmGallery.length; i++) {
    container.appendChild(createThumb(zmGallery[i].thumb, zmGallery[i].full, i + 1, false));
  }

  if (container.children.length <= 1) container.style.display = 'none';
}

/**
 * Swap USPs, specs, FAQ, and description from pre-loaded toggle target data.
 */
function swapContent(MT) {
  // Guarantee a fresh snapshot of the original sections right before we overwrite
  // them. Relying on requestIdleCallback alone was racy — on pages with slow
  // third-party scripts, idle fired with empty innerHTML, so restoreContent had
  // nothing to put back and the description stayed stuck on the target line.
  captureOriginalContent();

  // Each section: if target has data → fill + show; if empty → hide the whole
  // section so stale source-line content can never leak into the target view.
  function setSectionVisible(innerEl, sectionSelector, visible) {
    if (!innerEl) return;
    var section = sectionSelector ? innerEl.closest(sectionSelector) : innerEl;
    if (section) section.style.display = visible ? '' : 'none';
  }

  // USPs
  var uspList = document.querySelector('.oz-short-desc ul');
  var hasUsps = !!(MT.targetUsps && MT.targetUsps.length);
  if (uspList && hasUsps) {
    var uspHtml = '';
    for (var i = 0; i < MT.targetUsps.length; i++) {
      if (!MT.targetUsps[i]) continue;
      uspHtml += '<li><svg class="oz-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"></path></svg>' + MT.targetUsps[i] + '</li>';
    }
    uspList.innerHTML = uspHtml;
  }
  setSectionVisible(uspList, '.oz-short-desc', hasUsps);

  // Specs table
  var specsBody = document.querySelector('.oz-specs-table tbody');
  var specKeys = MT.targetSpecs ? Object.keys(MT.targetSpecs) : [];
  var hasSpecs = specKeys.length > 0;
  if (specsBody && hasSpecs) {
    var specHtml = '';
    for (var s = 0; s < specKeys.length; s++) {
      specHtml += '<tr><th>' + specKeys[s] + '</th><td>' + MT.targetSpecs[specKeys[s]] + '</td></tr>';
    }
    specsBody.innerHTML = specHtml;
  }
  setSectionVisible(specsBody, '.oz-specs-table', hasSpecs);

  // FAQ
  var faqList = document.querySelector('.oz-faq-list');
  var hasFaq = !!(MT.targetFaq && MT.targetFaq.length);
  if (faqList && hasFaq) {
    var faqHtml = '';
    for (var f = 0; f < MT.targetFaq.length; f++) {
      faqHtml += '<details class="oz-faq-item"><summary class="oz-faq-question">' + MT.targetFaq[f].q + '</summary><div class="oz-faq-answer">' + MT.targetFaq[f].a + '</div></details>';
    }
    faqList.innerHTML = faqHtml;
  }
  setSectionVisible(faqList, null, hasFaq);

  // Description
  if (DOM.descContent && MT.targetDescription) {
    DOM.descContent.innerHTML = MT.targetDescription;
    DOM.descContent.classList.remove('expanded');
    if (DOM.readMoreBtn) {
      if (DOM.descContent.scrollHeight <= 120) {
        DOM.readMoreBtn.style.display = 'none';
        DOM.descContent.classList.add('expanded');
      } else {
        DOM.readMoreBtn.style.display = '';
        DOM.readMoreBtn.textContent = 'Lees meer';
      }
    }
  }
}

// Store original content for restoration on toggle-back
var _originalContent = null;

/**
 * Snapshot original content sections on first toggle (lazy init).
 */
function captureOriginalContent() {
  if (_originalContent) return;
  _originalContent = {};
  var uspList = document.querySelector('.oz-short-desc ul');
  if (uspList) _originalContent.uspsHtml = uspList.innerHTML;
  var specsBody = document.querySelector('.oz-specs-table tbody');
  if (specsBody) _originalContent.specsHtml = specsBody.innerHTML;
  var faqList = document.querySelector('.oz-faq-list');
  if (faqList) _originalContent.faqHtml = faqList.innerHTML;
  if (DOM.descContent) _originalContent.descHtml = DOM.descContent.innerHTML;
}

/**
 * Restore original content sections from snapshot.
 */
function restoreContent() {
  if (!_originalContent) return;
  var uspList = document.querySelector('.oz-short-desc ul');
  if (uspList && _originalContent.uspsHtml) uspList.innerHTML = _originalContent.uspsHtml;
  var uspSection = uspList && uspList.closest('.oz-short-desc');
  if (uspSection) uspSection.style.display = '';
  var specsBody = document.querySelector('.oz-specs-table tbody');
  if (specsBody && _originalContent.specsHtml) specsBody.innerHTML = _originalContent.specsHtml;
  var specsWrap = specsBody && specsBody.closest('.oz-specs-table');
  if (specsWrap) specsWrap.style.display = '';
  var faqList = document.querySelector('.oz-faq-list');
  if (faqList && _originalContent.faqHtml) faqList.innerHTML = _originalContent.faqHtml;
  if (faqList) faqList.style.display = '';
  if (DOM.descContent && _originalContent.descHtml) {
    DOM.descContent.innerHTML = _originalContent.descHtml;
    DOM.descContent.classList.remove('expanded');
    if (DOM.readMoreBtn) {
      if (DOM.descContent.scrollHeight <= 120) {
        DOM.readMoreBtn.style.display = 'none';
        DOM.descContent.classList.add('expanded');
      } else {
        DOM.readMoreBtn.style.display = '';
        DOM.readMoreBtn.textContent = 'Lees meer';
      }
    }
  }
}

/**
 * Rebuild option groups that differ between K&K and ZM.
 * Primer is handled separately (hidden/shown, not rebuilt).
 */
function rebuildToggleOptions() {
  // Toepassing section — show/hide and rebuild using existing template classes
  var toeGroup = document.querySelector('[data-option="toepassing"]');
  if (P.toepassing && P.toepassing.length) {
    if (!toeGroup) {
      // Create toepassing section matching the template markup exactly
      var optionsWidget = DOM.optionsWidget;
      if (optionsWidget) {
        var toeSection = document.createElement('div');
        toeSection.className = 'oz-option-group';
        toeSection.setAttribute('data-option', 'toepassing');
        var toeHtml = '<div class="oz-option-header">Toepassing: <span class="oz-required-star" id="toepassingRequired" style="color:#e53e3e">*</span> <span class="oz-selected-value" id="selectedToepassingLabel"></span></div>';
        toeHtml += '<div class="oz-option-labels">';
        for (var t = 0; t < P.toepassing.length; t++) {
          var isSel = P.toepassing[t] === S.toepassing;
          toeHtml += '<button class="oz-option-label-btn' + (isSel ? ' selected' : '') + '" data-toepassing="' + P.toepassing[t] + '">' + P.toepassing[t] + '</button>';
        }
        toeHtml += '</div>';
        toeSection.innerHTML = toeHtml;
        // Insert before PU section (toepassing comes before PU in ZM option_order)
        var puSec = optionsWidget.querySelector('[data-option="pu"]');
        if (puSec) {
          optionsWidget.insertBefore(toeSection, puSec);
        } else {
          optionsWidget.appendChild(toeSection);
        }
      }
    } else {
      toeGroup.style.display = '';
    }
  } else if (toeGroup) {
    toeGroup.style.display = 'none';
  }
}

/**
 * Rebuild PU option buttons with prices from the active config.
 * K&K shows €8/16/24 per m², ZM shows €40/80/120 per 5m².
 */
function rebuildPuOptions() {
  var puGroup = document.querySelector('[data-option="pu"]');
  if (!puGroup || !P.puOptions) return;
  var btnsWrap = puGroup.querySelector('.oz-option-buttons');
  if (!btnsWrap) return;

  var html = '';
  for (var i = 0; i < P.puOptions.length; i++) {
    var opt = P.puOptions[i];
    var isSelected = opt.layers == S.puLayers;
    var priceTag = '';
    if (opt.price > 0) priceTag = ' +' + fmt(opt.price);
    else if (opt.price < 0) priceTag = ' ' + fmt(opt.price);
    html += '<button class="oz-option-btn' + (isSelected ? ' selected' : '') + '" data-pu="' + opt.layers + '">' + opt.label + (priceTag ? ' <span class="oz-price-tag">' + priceTag + '</span>' : '') + '</button>';
  }
  btnsWrap.innerHTML = html;
}

// Capture is now deferred to the first swapContent() call so we always
// snapshot the real PHP-rendered content (pre-toggle). Idle-callback timing
// was racy on slow pages and could cache an empty descContent, breaking restore.

// Export toggleFormula for navigation.js popstate handler
window._ozToggleFormula = P.modeToggle ? toggleFormula : null;


/* ═══ UPSELL MODAL HANDLERS ════════════════════════════════ */

/** Open upsell modal — called from addToCart when no tools selected */
function openUpsell() {
  updateState({ upsellOpen: true });
  document.body.style.overflow = 'hidden';
  renderUpsellModal();
}

/** Close upsell modal */
function closeUpsell() {
  updateState({ upsellOpen: false });
  document.body.style.overflow = '';
  renderUpsellModal();
}

/** Upsell: add the Kant & Klaar set and proceed to cart */
function upsellAddSet() {
  analytics.trackUpsellAccepted();
  updateState({ toolMode: 'set' });
  closeUpsell();
  syncUI();
  submitCart();
}

/** Upsell: skip — go to cart without tools */
function upsellSkip() {
  analytics.trackUpsellSkipped();
  closeUpsell();
  submitCart();
}

/** Render upsell modal — show/hide based on S.upsellOpen */
function renderUpsellModal() {
  if (!DOM.upsellOverlay) return;
  if (S.upsellOpen) {
    DOM.upsellOverlay.style.display = 'flex';
    requestAnimationFrame(function() { DOM.upsellOverlay.classList.add('open'); });
  } else {
    DOM.upsellOverlay.classList.remove('open');
    setTimeout(function() { DOM.upsellOverlay.style.display = 'none'; }, 250);
  }
}


/* ═══ EVENT HANDLERS ════════════════════════════════════════ */

/**
 * Main event delegation handler.
 * All clicks on the product page are routed through here.
 */
function handleClick(e) {
  var target = e.target;

  // Formula toggle button — K&K <-> Zelf Mengen & Mixen
  var formulaBtn = target.closest('[data-formula]');
  if (formulaBtn) {
    e.preventDefault();
    toggleFormula(formulaBtn.dataset.formula);
    return;
  }

  // Walk up to find the actionable element (button, swatch, etc.)
  var btn = target.closest('[data-pu], [data-primer], [data-colorfresh], [data-toepassing], [data-pakket]');
  var addonBtn = target.closest('[data-addon-key]');
  var thumb = target.closest('.oz-gallery-thumb');
  var infoBtn = target.closest('.oz-info-btn');
  var qtyBtn = target.closest('[data-qty-delta]');
  var modeBtn = target.closest('.oz-color-mode-btn');

  // Generic addon group button clicks — update addons state
  if (addonBtn && !btn) {
    e.preventDefault();
    var key = addonBtn.getAttribute('data-addon-key');
    var value = addonBtn.getAttribute('data-addon-value');
    if (key && value) {
      S.addons[key] = value;
      analytics.trackAddonSelected(key, value);
      // Highlight selected button within this group
      var group = addonBtn.closest('.oz-option-group');
      if (group) {
        var groupBtns = group.querySelectorAll('[data-addon-key]');
        for (var gi = 0; gi < groupBtns.length; gi++) {
          groupBtns[gi].classList.toggle('selected', groupBtns[gi].getAttribute('data-addon-value') === value);
        }
      }
      syncUI();
    }
    return;
  }

  // Option button clicks — update state + re-render
  if (btn) {
    e.preventDefault();
    if (btn.hasAttribute('data-pu')) {
      var puVal = parseInt(btn.getAttribute('data-pu'), 10);
      updateState({ puLayers: puVal });
      analytics.trackOptionSelected('pu', puVal);
    } else if (btn.hasAttribute('data-primer')) {
      var primerVal = btn.getAttribute('data-primer');
      updateState({ primer: primerVal });
      analytics.trackOptionSelected('primer', primerVal);
    } else if (btn.hasAttribute('data-colorfresh')) {
      var cfVal = btn.getAttribute('data-colorfresh');
      updateState({ colorfresh: cfVal });
      analytics.trackOptionSelected('colorfresh', cfVal);
    } else if (btn.hasAttribute('data-toepassing')) {
      var toeVal = btn.getAttribute('data-toepassing');
      updateState({ toepassing: toeVal });
      analytics.trackOptionSelected('toepassing', toeVal);
    } else if (btn.hasAttribute('data-pakket')) {
      var pakVal = btn.getAttribute('data-pakket');
      updateState({ pakket: pakVal });
      analytics.trackOptionSelected('pakket', pakVal);
    }
    syncUI();
    return;
  }

  // Gallery thumbnail click — switch main image with crossfade
  if (thumb) {
    e.preventDefault();
    analytics.trackGalleryImage(thumb.getAttribute('data-index') || 0);
    switchGalleryImage(thumb);
    return;
  }

  // Info tooltip toggle
  if (infoBtn) {
    e.preventDefault();
    toggleInfoTooltip(infoBtn);
    return;
  }

  // Quantity +/- buttons
  if (qtyBtn) {
    e.preventDefault();
    changeQty(parseInt(qtyBtn.getAttribute('data-qty-delta'), 10));
    return;
  }

  // Color mode buttons (RAL/NCS vs swatch)
  if (modeBtn) {
    e.preventDefault();
    var newMode = modeBtn.getAttribute('data-mode');
    updateState({ colorMode: newMode });
    analytics.trackColorModeChanged(newMode);
    syncUI();
    return;
  }

  // Color swatch click — static swatches set state, normal swatches navigate
  var swatch = target.closest('.oz-color-swatch');
  if (swatch) {
    // The "Bekijk alle" chip in the colors drawer wears .oz-color-swatch
    // for grid alignment but is not an actual swatch — it has its own
    // click handler. Bail before any navigation logic touches it.
    if (swatch.classList.contains('oz-color-more-chip')) return;
    var colorName = swatch.getAttribute('data-color') || '';
    analytics.trackColorSelected(colorName);

    // Static swatch (shared colors, e.g. ZM, Betonlook Verf) — no navigation
    if (swatch.hasAttribute('data-static')) {
      e.preventDefault();
      updateState({ selectedColor: colorName });
      // Update swatch highlight
      var allSwatches = swatch.parentNode.querySelectorAll('.oz-color-swatch');
      for (var si = 0; si < allSwatches.length; si++) {
        allSwatches[si].classList.toggle('selected', allSwatches[si] === swatch);
      }
      // Swap main image from variant data — on ZM line use zmFullImage,
      // fall back to fullImage for lines without ZM variant data
      var swatchIsZm = (P.productLine || '').indexOf('-zm') !== -1;
      var fullImg = swatchIsZm
        ? (findVariantField(colorName, 'zmFullImage') || findVariantField(colorName, 'fullImage'))
        : findVariantField(colorName, 'fullImage');
      if (fullImg) swapMainImage(fullImg);
      syncUI();
      return;
    }

    // Normal swatch — pushState navigation (no page reload)
    e.preventDefault();
    var pid = parseInt(swatch.getAttribute('data-product-id'), 10);
    if (pid && P.variants && P.variants[pid] && navigateToVariant(pid)) {
      // In ZM mode: navigateToVariant swaps visuals (image, title, gallery)
      // but overwrites P.basePrice/productId/etc with K&K variant data.
      // Restore all ZM config properties after the visual swap.
      if (S.formulaMode === 'target' && P.modeToggle) {
        var MT = P.modeToggle;
        P.productId     = MT.targetProductId;
        P.basePrice     = MT.targetBasePrice;
        P.productLine   = MT.targetLine;
        P.unit          = MT.targetUnit;
        P.unitM2        = MT.targetUnitM2;
        P.puOptions     = MT.targetPuOptions;
        P.primerOptions = MT.targetPrimerOptions;
        P.toepassing    = MT.targetToepassing;
        P.optionOrder   = MT.targetOptionOrder;
        P.hasTools      = MT.targetHasTools;
        P.toolConfig    = MT.targetToolConfig;
        P.isBase        = false;
        updateState({ selectedColor: colorName });
        // Keep URL on ZM product
        history.replaceState(
          { productId: MT.targetProductId, formulaMode: 'target' },
          '', MT.targetUrl
        );

        // navigateToVariant applied K&K images — swap to ZM images
        var navV = P.variants[pid];
        if (navV && navV.zmFullImage) swapMainImage(navV.zmFullImage);
        rebuildGalleryForZM(colorName);

        // Re-render with correct ZM prices
        syncUI();
      }
      // syncUI called by navigation.js callback (handles both click + popstate)
    } else {
      // Fallback: full navigation if variant data is missing
      saveToolState();
      window.location.href = swatch.href;
    }
    return;
  }

  // Tab switching
  var tabBtn = target.closest('.oz-tab');
  if (tabBtn) {
    e.preventDefault();
    switchTab(tabBtn.getAttribute('data-tab'));
    return;
  }

  // Sticky nav link tab activation
  var stickyLink = target.closest('.oz-sticky-d-link[data-tab]');
  if (stickyLink) {
    var tabId = stickyLink.getAttribute('data-tab');
    if (tabId) switchTab(tabId);
  }

  // Read more toggle
  if (target === DOM.readMoreBtn || target.closest('#readMoreBtn')) {
    e.preventDefault();
    toggleReadMore();
    return;
  }

  // Add to cart button(s)
  if (target === DOM.addToCartBtn || target.closest('#addToCartBtn') ||
      target === DOM.sheetCtaBtn || target.closest('#sheetCtaBtn')) {
    e.preventDefault();
    addToCart();
    return;
  }

  // Upsell modal buttons
  if (target === DOM.upsellAddBtn || target.closest('#upsellAddBtn')) {
    e.preventDefault();
    upsellAddSet();
    return;
  }
  if (target === DOM.upsellSkipBtn || target.closest('#upsellSkipBtn')) {
    e.preventDefault();
    upsellSkip();
    return;
  }

  // Upsell overlay click — close modal
  if (target === DOM.upsellOverlay) {
    closeUpsell();
    return;
  }

  // Mobile sticky button
  if (target === DOM.stickyBtn || target.closest('#stickyBtn')) {
    e.preventDefault();
    if (P.isBase && validateCartState(P, S)) {
      scrollToColors();
    } else if (needsToepassing()) {
      scrollToToepassing();
    } else {
      openSheet();
    }
    return;
  }

  // Desktop sticky button
  if (target === DOM.stickyDBtn || target.closest('#stickyDBtn')) {
    e.preventDefault();
    if (P.isBase && validateCartState(P, S)) {
      scrollToColors();
    } else if (needsToepassing()) {
      scrollToToepassing();
    } else {
      addToCart();
    }
    return;
  }

  // Desktop sticky nav links — smooth scroll to page sections
  var navLink = target.closest('.oz-sticky-d-link');
  if (navLink) {
    e.preventDefault();
    var sectionId = navLink.getAttribute('data-scroll');
    var section = sectionId ? document.getElementById(sectionId) : null;
    if (section) smoothScrollTo(section);
    return;
  }

  // Desktop sticky options — smooth scroll to options widget
  if (target === DOM.stickyDOptions || target.closest('#stickyDOptions')) {
    e.preventDefault();
    var optionsEl = DOM.optionsWidget || DOM.addToCartBtn;
    if (optionsEl) smoothScrollTo(optionsEl);
    return;
  }

  // Sheet overlay click — close sheet
  if (target === DOM.sheetOverlay) {
    closeSheet();
    return;
  }
}

/**
 * Switch gallery main image with crossfade effect.
 * @param {Element} thumb  The clicked thumbnail element
 */
function switchGalleryImage(thumb) {
  var newSrc = thumb.getAttribute('data-full-src');
  if (!newSrc || !DOM.mainImg) return;

  // Update selected state on thumbnails
  var allThumbs = document.querySelectorAll('.oz-gallery-thumb');
  for (var i = 0; i < allThumbs.length; i++) {
    allThumbs[i].classList.remove('selected');
  }
  thumb.classList.add('selected');

  // Some thumbnails (like the Lavasteen PU explainer infographic) are wider
  // than square and need object-fit: contain to stay readable. Carry the
  // marker via data-fit on the thumb so the swap logic can apply / clear
  // the .oz-gallery-fit-contain class on the main image.
  var wantsContain = thumb.getAttribute('data-fit') === 'contain';
  DOM.mainImg.classList.toggle('oz-gallery-fit-contain', wantsContain);

  // Crossfade: fade out, swap src, fade in.
  // crossorigin="anonymous" is needed by adaptBreadcrumbColor (canvas pixel
  // read) but breaks loading of cross-origin AVIF/WebP variants (e.g. when
  // LiteSpeed serves them without CORS headers). Strip the attribute for
  // the swap, then put it back after load — the canvas read happens on the
  // freshly-loaded image so the attribute is in place by then.
  DOM.mainImg.classList.add('oz-fade');
  setTimeout(function () {
    var hadCrossorigin = DOM.mainImg.hasAttribute('crossorigin');
    if (hadCrossorigin) DOM.mainImg.removeAttribute('crossorigin');

    function clearFade() {
      DOM.mainImg.classList.remove('oz-fade');
      // LSCWP lazy-load adds an "error" class on failure; clear it so it
      // doesn't stick to the image after a successful swap.
      DOM.mainImg.classList.remove('error');
      if (hadCrossorigin) DOM.mainImg.setAttribute('crossorigin', 'anonymous');
      var bc = document.querySelector('.oz-breadcrumb-overlay');
      if (bc) adaptBreadcrumbColor(DOM.mainImg, bc);
    }

    // Always reveal the image — even on load error — so the user never
    // sees a blank gallery. Browser shows broken-image fallback at worst.
    DOM.mainImg.onload  = clearFade;
    DOM.mainImg.onerror = clearFade;
    DOM.mainImg.src = newSrc;
  }, 200);
}

/**
 * Toggle info tooltip visibility.
 * @param {Element} btn  The info button that was clicked
 */
function toggleInfoTooltip(btn) {
  var targetId = btn.getAttribute('data-info-target');
  if (!targetId) return;
  var tooltip = document.getElementById(targetId);
  if (!tooltip) return;
  tooltip.classList.toggle('visible');
}

/**
 * Change quantity by a delta (+1 or -1). Clamps between 1–99.
 * Uses clampToolQty from state.js (same clamping logic, no duplication).
 * @param {number} delta
 */
function changeQty(delta) {
  var newQty = clampToolQty(S.qty, delta);
  updateState({ qty: newQty });
  analytics.trackQtyChanged(newQty);
  if (DOM.qtyInput) DOM.qtyInput.value = newQty;
  syncUI();
}

/**
 * Handle direct quantity input changes.
 */
function handleQtyInput() {
  var val = parseInt(DOM.qtyInput.value, 10);
  if (isNaN(val) || val < 1) val = 1;
  if (val > 99) val = 99;
  updateState({ qty: val });
  // Analytics tracking moved to 'change' event only — see event binding below
  DOM.qtyInput.value = val;
  syncUI();
}

/**
 * Switch active tab in the product info tabs.
 * @param {string} tabId  Tab key: 'info', 'specs', or 'compare'
 */
function switchTab(tabId) {
  var container = document.getElementById('ozTabs');
  if (!container) return;

  var tabs = container.querySelectorAll('.oz-tab');
  var panels = container.querySelectorAll('.oz-tab-panel');

  for (var i = 0; i < tabs.length; i++) {
    tabs[i].classList.toggle('active', tabs[i].getAttribute('data-tab') === tabId);
  }
  for (var j = 0; j < panels.length; j++) {
    panels[j].classList.toggle('active', panels[j].getAttribute('data-tab') === tabId);
  }
}

/**
 * Toggle "read more" on the product description.
 */
function toggleReadMore() {
  if (!DOM.descContent || !DOM.readMoreBtn) return;
  var expanded = DOM.descContent.classList.toggle('expanded');
  DOM.readMoreBtn.textContent = expanded ? 'Lees minder' : 'Lees meer';
}

/**
 * Auto-format a raw color input string into proper RAL or NCS notation.
 * - 4 digits (e.g. "4070") → "RAL 4070"
 * - NCS-like pattern (e.g. "1050y90r") → "NCS S 1050-Y90R"
 * - Already prefixed values are left as-is.
 *
 * @param {string} raw  User input (trimmed)
 * @return {string}  Formatted code, or original if no pattern matched
 */
function autoFormatColor(raw) {
  // Normalize: collapse whitespace, trim
  var s = raw.trim().replace(/\s+/g, ' ');

  // Strip any RAL/NCS/S prefix to get the core code
  var core = s.replace(/^(RAL|NCS)\s*/i, '').replace(/^S\s*/i, '').trim();

  // Pure 4-digit number → RAL code (e.g. "7010", "RAL 7010", "ral  7010")
  if (/^\d{4}$/.test(core)) return 'RAL ' + core;

  // NCS pattern: 4 digits + hue (single letter like B/N or letter-digits-letter like Y20R)
  var ncsMatch = core.match(/^(\d{4})-?([A-Za-z](?:\d{2}[A-Za-z])?)$/);
  if (ncsMatch) return 'NCS S ' + ncsMatch[1] + '-' + ncsMatch[2].toUpperCase();

  return raw;
}

/**
 * Handle custom color input (RAL/NCS field).
 * Auto-formats on blur and validates on every keystroke.
 */
function handleCustomColorInput(e) {
  var input = e.target;
  var value = input.value.trim();
  var hint = document.getElementById('customColorHint');

  // Auto-format on blur — applies RAL/NCS prefix formatting
  if (e.type === 'blur' && value) {
    var formatted = autoFormatColor(value);
    if (formatted !== value) {
      input.value = formatted;
      value = formatted;
    }
  }

  updateState({ customColor: value });

  // Clear validation state if empty
  if (!value) {
    input.classList.remove('valid', 'invalid');
    if (hint) { hint.textContent = ''; hint.className = 'oz-custom-color-hint'; }
    syncUI();
    return;
  }

  // Auto-format and recognize RAL/NCS (but accept any code)
  var checkValue = autoFormatColor(value);
  var isRal = validateRal(checkValue);
  var isNcs = validateNcs(checkValue);

  // Any non-empty input is valid — RAL/NCS get a recognition hint
  input.classList.remove('invalid');
  input.classList.add('valid');

  if (isRal || isNcs) {
    if (e.type === 'blur' || e.type === 'focusout') {
      analytics.trackCustomColor(checkValue, isRal ? 'ral' : 'ncs');
    }
    if (hint) {
      hint.textContent = isRal ? 'RAL kleurcode herkend' : 'NCS kleurcode herkend';
      hint.className = 'oz-custom-color-hint success';
    }
  } else {
    if (hint) {
      hint.textContent = '';
      hint.className = 'oz-custom-color-hint';
    }
  }

  syncUI();
}


/* ═══ PDP STATE PERSISTENCE ═══════════════════════════════════
 *
 * Cache-safe by construction: sessionStorage is per-tab, client-only.
 * Nothing renders server-side. The cached PDP HTML is identical for all
 * users; the JS reads/writes session state at runtime.
 *
 * Two scenarios this covers:
 *   1. Color switch via full-page nav (variant data missing) — same line
 *      shares state across colors so PU/primer/qty survive the swatch click.
 *   2. Cart drawer → checkout → bounce-back to PDP — the whole reason this
 *      exists. Marcel-style sessions where customers re-pick options after
 *      every return navigation.
 *
 * Storage key is scoped by baseProductId (or productId) so different
 * product lines don't cross-pollute. TTL of 30 min covers a reasonable
 * checkout-and-back cycle without crossing into a separate buying intent.
 *
 * Save: debounced 200 ms after every state mutation. Also fires on
 * `pagehide` for the case where debounce hasn't drained before unload.
 */

// Bump the v on schema changes so old saved state doesn't get applied with
// keys we no longer support
var PDP_STATE_KEY = 'oz_bcw_pdp_state_v2:' + (P.baseProductId || P.productId);
var PDP_STATE_TTL = 30 * 60 * 1000;  // 30 minutes

var _persistTimer = null;

/**
 * Save persistable PDP state to sessionStorage. Debounced — call as often
 * as you like, only the last call within 200 ms actually writes.
 */
function persistPdpState() {
  if (_persistTimer) clearTimeout(_persistTimer);
  _persistTimer = setTimeout(function () {
    _persistTimer = null;
    try {
      var data = {
        ts: Date.now(),
        toolMode: S.toolMode,
        extras: S.extras,
        tools: S.tools,
        puLayers: S.puLayers,
        primer: S.primer,
        colorfresh: S.colorfresh,
        toepassing: S.toepassing,
        pakket: S.pakket,
        qty: S.qty,
        addons: S.addons,
        selectedColor: S.selectedColor,
        colorMode: S.colorMode,
        customColor: S.customColor,
        formulaMode: S.formulaMode,
      };
      sessionStorage.setItem(PDP_STATE_KEY, JSON.stringify(data));
    } catch (e) {
      // Safari private mode / quota exceeded — silently ignore. Persisting
      // is nice-to-have, not critical to the cart flow.
    }
  }, 200);
}

/**
 * Validate that an option value still exists in the current product config.
 * If not (e.g. admin removed the option since the customer last visited),
 * skip restoring it so we don't push the user into an invalid state.
 */
function _isValidPuLayers(v) {
  if (!P.puOptions) return false;
  for (var i = 0; i < P.puOptions.length; i++) {
    if (P.puOptions[i].layers == v) return true;  // loose for "0" === 0
  }
  return false;
}
function _isValidOptionLabel(options, label) {
  if (!options || !label) return false;
  for (var i = 0; i < options.length; i++) {
    if (options[i].label === label) return true;
  }
  return false;
}
function _isValidAddon(groupKey, label) {
  if (!P.addonGroups) return false;
  for (var i = 0; i < P.addonGroups.length; i++) {
    if (P.addonGroups[i].key === groupKey) {
      return _isValidOptionLabel(P.addonGroups[i].options, label);
    }
  }
  return false;
}

/**
 * Restore PDP state from sessionStorage on page load. Validates each value
 * against the current product config so a since-removed option doesn't get
 * applied. Silent on any failure — a broken restore must never block the page.
 */
function restorePdpState() {
  try {
    var raw = sessionStorage.getItem(PDP_STATE_KEY);
    if (!raw) return;

    var data = JSON.parse(raw);

    // TTL — drop stale state. Crossing 30 min usually means a different
    // buying intent, not a checkout-and-back loop.
    if (!data.ts || Date.now() - data.ts > PDP_STATE_TTL) {
      sessionStorage.removeItem(PDP_STATE_KEY);
      return;
    }

    // Top-level options — validate against current product config
    if (data.puLayers !== undefined && data.puLayers !== null && _isValidPuLayers(data.puLayers)) {
      updateState({ puLayers: data.puLayers });
    }
    if (data.primer && _isValidOptionLabel(P.primerOptions, data.primer)) {
      updateState({ primer: data.primer });
    }
    if (data.colorfresh && _isValidOptionLabel(P.colorfresh, data.colorfresh)) {
      updateState({ colorfresh: data.colorfresh });
    }
    if (data.toepassing && _isValidOptionLabel(P.toepassing, data.toepassing)) {
      updateState({ toepassing: data.toepassing });
    }
    if (data.pakket && _isValidOptionLabel(P.pakket, data.pakket)) {
      updateState({ pakket: data.pakket });
    }
    if (data.qty && data.qty > 0 && data.qty <= 99) {
      updateState({ qty: data.qty });
      if (DOM.qtyInput) DOM.qtyInput.value = data.qty;
    }
    if (data.toolMode === 'set' || data.toolMode === 'individual' || data.toolMode === 'none') {
      updateState({ toolMode: data.toolMode });
    }

    // Generic addon groups — only apply still-valid selections
    if (data.addons && S.addons) {
      Object.keys(data.addons).forEach(function (key) {
        if (_isValidAddon(key, data.addons[key])) {
          S.addons[key] = data.addons[key];
        }
      });
    }

    // Color mode + selection — applies for static-color products and RAL/NCS
    if (data.colorMode === 'swatch' || data.colorMode === 'ral_ncs') {
      updateState({ colorMode: data.colorMode });
    }
    if (data.customColor && data.colorMode === 'ral_ncs') {
      updateState({ customColor: data.customColor });
    }
    if (data.selectedColor && P.hasStaticColors) {
      updateState({ selectedColor: data.selectedColor });
    }

    // Tool/extra state — only restore ids that still exist in current config
    if (data.extras) {
      Object.keys(data.extras).forEach(function (id) {
        if (S.extras[id]) S.extras[id] = data.extras[id];
      });
    }
    if (data.tools) {
      Object.keys(data.tools).forEach(function (id) {
        if (S.tools[id]) S.tools[id] = data.tools[id];
      });
    }
  } catch (e) {
    // Parse error / storage unavailable — never block the page on restore
    try { sessionStorage.removeItem(PDP_STATE_KEY); } catch (e2) {}
  }
}

// Back-compat shims for the old function names (still called from a couple
// of other code paths — keeping the old API points working avoids an
// unrelated refactor).
function saveToolState()   { persistPdpState(); }
function restoreToolState() { restorePdpState(); }


/* ═══ BOTTOM SHEET ══════════════════════════════════════════ */

// Local variable for scroll position — browser ephemera, not domain state
var _sheetScrollY = 0;

/**
 * Open the bottom sheet on mobile.
 * Moves #optionsWidget into the sheet slot.
 */
function openSheet() {
  if (!DOM.bottomSheet || !DOM.sheetOverlay || !DOM.optionsWidget) return;

  // Track sheet open for analytics
  analytics.trackSheetOpened();

  // Remember scroll position so we can restore it on close
  _sheetScrollY = window.scrollY;

  // Move options widget into sheet
  DOM.slotSheet.appendChild(DOM.optionsWidget);

  // Collapse the empty desktop home so the page doesn't show a gap
  if (DOM.desktopHome) DOM.desktopHome.style.minHeight = '0';

  updateState({ sheetOpen: true });
  DOM.sheetOverlay.classList.add('open');
  DOM.bottomSheet.classList.add('open');
  document.body.style.overflow = 'hidden'; // prevent body scroll
}

/**
 * Close the bottom sheet.
 * Moves #optionsWidget back to the desktop slot.
 */
function closeSheet() {
  if (!DOM.bottomSheet || !DOM.sheetOverlay || !DOM.optionsWidget) return;

  updateState({ sheetOpen: false });
  DOM.sheetOverlay.classList.remove('open');
  DOM.bottomSheet.classList.remove('open');
  document.body.style.overflow = '';

  // Move options widget back to its desktop home
  if (DOM.desktopHome) DOM.desktopHome.appendChild(DOM.optionsWidget);

  // Restore scroll position
  window.scrollTo(0, _sheetScrollY);
}


/**
 * Setup swipe-to-dismiss on the bottom sheet.
 * User can drag the sheet handle (or top area) downward to close.
 * Threshold: 80px of downward drag closes the sheet.
 */
function setupSheetSwipe() {
  if (!DOM.bottomSheet) return;

  var startY = 0;
  var currentY = 0;
  var isDragging = false;

  // Only allow drag from the handle area (top 60px of sheet)
  function isInHandleZone(e) {
    var touch = e.touches[0];
    var rect = DOM.bottomSheet.getBoundingClientRect();
    return (touch.clientY - rect.top) < 60;
  }

  DOM.bottomSheet.addEventListener('touchstart', function(e) {
    // Only start drag if touching the handle zone or sheet is scrolled to top
    if (isInHandleZone(e) || DOM.bottomSheet.scrollTop === 0) {
      startY = e.touches[0].clientY;
      currentY = startY;
      isDragging = true;
      // Disable transition during drag for smooth tracking
      DOM.bottomSheet.style.transition = 'none';
    }
  }, { passive: true });

  DOM.bottomSheet.addEventListener('touchmove', function(e) {
    if (!isDragging) return;
    currentY = e.touches[0].clientY;
    var deltaY = currentY - startY;

    // Only track downward drags (positive delta)
    if (deltaY > 0) {
      // Apply rubber-band transform — sheet follows finger
      DOM.bottomSheet.style.transform = 'translateY(' + deltaY + 'px)';
      // Prevent scroll while dragging down
      e.preventDefault();
    }
  }, { passive: false });

  DOM.bottomSheet.addEventListener('touchend', function() {
    if (!isDragging) return;
    isDragging = false;

    var deltaY = currentY - startY;
    // Restore transition for snap animation
    DOM.bottomSheet.style.transition = '';

    if (deltaY > 80) {
      // Threshold exceeded — close the sheet
      DOM.bottomSheet.style.transform = '';
      closeSheet();
    } else {
      // Snap back to open position
      DOM.bottomSheet.style.transform = 'translateY(0)';
    }
  }, { passive: true });
}


/* ═══ AJAX ADD TO CART ══════════════════════════════════════ */

/**
 * Submit add-to-cart via AJAX.
 * Validates state (pure), then delegates to submitCart for I/O.
 */
function addToCart() {
  // Base products need a color selection before ordering.
  // Exception: RAL/NCS mode with a valid custom color — that IS their color choice.
  if (P.isBase && !(S.colorMode === 'ral_ncs' && S.customColor)) {
    var colorGroup = document.querySelector('[data-option="color"]');
    if (colorGroup) {
      smoothScrollTo(colorGroup);
      colorGroup.classList.add('oz-highlight');
      setTimeout(function() { colorGroup.classList.remove('oz-highlight'); }, 1500);
    }
    shakeButton();
    showCartError('Kies eerst een kleur om te bestellen.');
    return;
  }

  // Variant C: the "Kies je ruimte" dropdown is the user's primer + PU choice.
  // Without a pick, the cart silently submits whatever defaults sit underneath
  // (often "Primer" + "2 lagen ADVIES"). Force an explicit choice so users
  // don't accidentally pay for protection they didn't ask for.
  var ruimteDropdown = document.querySelector('.oz-ruimte-dropdown');
  if (ruimteDropdown) {
    var ruimteSelect = ruimteDropdown.querySelector('.oz-ruimte-select');
    if (ruimteSelect && !ruimteSelect.value) {
      smoothScrollTo(ruimteDropdown);
      ruimteDropdown.classList.add('oz-highlight');
      setTimeout(function() { ruimteDropdown.classList.remove('oz-highlight'); }, 1500);
      analytics.trackAddToCartError('ruimte not selected');
      shakeButton();
      showCartError('Kies eerst een ruimte.');
      return;
    }
  }

  // Pure validation — returns error string or null
  var error = validateCartState(P, S);
  if (error) {
    // Scroll to the relevant section based on the error
    if (error.indexOf('toepassing') !== -1) {
      scrollToToepassing();
    } else if (error.indexOf('gereedschap') !== -1) {
      var toolGroup = document.querySelector('[data-option="tools"]');
      if (toolGroup) {
        smoothScrollTo(toolGroup);
        toolGroup.classList.add('oz-highlight');
        setTimeout(function() { toolGroup.classList.remove('oz-highlight'); }, 1500);
      }
    }
    analytics.trackAddToCartError(error);
    shakeButton();
    showCartError(error);
    return;
  }

  // Tool upsell modal disabled — customers who skip tools have made
  // a deliberate choice. The cart drawer upsells handle tool suggestions
  // after add-to-cart instead (less intrusive, higher conversion).

  // Proceed to actually submit
  submitCart();
}

/**
 * Fetch a fresh nonce from the server.
 * Used when page cache (LiteSpeed) served a stale nonce.
 */
function refreshNonce() {
  var data = new FormData();
  data.append('action', 'oz_bcw_refresh_nonce');
  return fetch(P.ajaxUrl, {
    method: 'POST',
    body: data,
    credentials: 'same-origin',
  })
    .then(function (res) { return res.json(); })
    .then(function (json) {
      if (json.success && json.data && json.data.nonce) {
        P.nonce = json.data.nonce;
        return json.data.nonce;
      }
      return null;
    });
}

/**
 * Actually submit the cart — thin I/O shell.
 * Pure payload building is in buildCartPayload(), I/O is here.
 * On stale nonce (page cache), fetches a fresh nonce and retries once.
 */
function submitCart(isRetry) {
  // Pure: build payload object from state
  var payload = buildCartPayload(P, S);

  // Convert to FormData (browser API — lives in this shell module)
  var data = payloadToFormData(payload);

  // Disable button + show loading state
  if (!isRetry) setCartLoading(true);

  fetch(P.ajaxUrl, {
    method: 'POST',
    body: data,
    credentials: 'same-origin',
  })
    .then(function (res) { return res.json(); })
    .then(function (json) {
      // Stale nonce from page cache — refresh and retry once
      if (!isRetry && json && json.success === false && json.data === 'nonce_expired') {
        refreshNonce().then(function (fresh) {
          if (fresh) {
            submitCart(true);
          } else {
            setCartLoading(false);
            showCartError('Sessie verlopen. Ververs de pagina.');
          }
        });
        return;
      }

      setCartLoading(false);

      if (json && json.success) {
        // Track successful add to cart
        analytics.trackAddToCart(calculatePrices(P, S));

        // Close sheet if open
        if (S.sheetOpen) closeSheet();

        // Show success feedback
        showCartSuccess(json.data);

        // Server replaced an older configuration of the same product.
        // Tell the user so they understand why the old line is gone — silent
        // dedup would feel like a bug ("where's my other config?").
        var replaced = (json.data && parseInt(json.data.replaced_count, 10)) || 0;
        if (replaced > 0) {
          showCartInfo(replaced === 1
            ? 'Vorige configuratie van dit product vervangen door je nieuwe keuze.'
            : 'Vorige configuraties van dit product vervangen door je nieuwe keuze.');
        }

        // Notify cart drawer to open (custom event for our theme)
        document.dispatchEvent(new CustomEvent('oz-added-to-cart'));

        // Update WC cart fragments if available
        if (typeof jQuery !== 'undefined') {
          jQuery(document.body).trigger('wc_fragment_refresh');
        }
      } else {
        var msg = (json && json.data) ? json.data : 'Er ging iets mis.';
        showCartError(msg);
      }
    })
    .catch(function () {
      setCartLoading(false);
      showCartError('Verbindingsfout. Probeer opnieuw.');
    });
}

/**
 * Toggle loading state on add-to-cart buttons.
 */
function setCartLoading(loading) {
  var btn = DOM.addToCartBtn;
  if (!btn) return;

  btn.disabled = loading;
  btn.textContent = loading ? 'Bezig...' : 'In winkelmand';

  // Also disable sheet CTA
  if (DOM.sheetCtaBtn) DOM.sheetCtaBtn.disabled = loading;
}

/**
 * Brief shake animation on the add-to-cart button for validation errors.
 */
function shakeButton() {
  var btn = DOM.addToCartBtn;
  if (!btn) return;
  btn.style.animation = 'none';
  // Force reflow
  btn.offsetHeight;
  btn.style.animation = 'oz-shake 0.4s ease';
  setTimeout(function () { btn.style.animation = ''; }, 500);
}

/**
 * Show error message below the add-to-cart area.
 * Auto-hides after 4 seconds.
 */
function showCartError(msg) {
  removeCartMsg();
  var el = document.createElement('div');
  el.className = 'oz-cart-msg oz-cart-error';
  el.textContent = msg;
  var cartRow = document.querySelector('.oz-cart-row');
  if (cartRow && cartRow.parentNode) {
    cartRow.parentNode.insertBefore(el, cartRow.nextSibling);
  }
  setTimeout(removeCartMsg, 4000);
}

/**
 * Show informational message below the add-to-cart area. Used to surface
 * silent server actions (like dedup-replacement) so the user understands
 * why the cart looks different than they expect. Auto-hides after 6 s
 * (longer than errors — this is non-blocking informational copy).
 */
function showCartInfo(msg) {
  removeCartMsg();
  var el = document.createElement('div');
  el.className = 'oz-cart-msg oz-cart-info';
  el.textContent = msg;
  var cartRow = document.querySelector('.oz-cart-row');
  if (cartRow && cartRow.parentNode) {
    cartRow.parentNode.insertBefore(el, cartRow.nextSibling);
  }
  setTimeout(removeCartMsg, 6000);
}

/**
 * Show success feedback after adding to cart.
 * @param {Object} data  Response from server (cart_count, subtotal, etc.)
 */
function showCartSuccess(data) {
  removeCartMsg();

  // Flash the button green briefly
  if (DOM.addToCartBtn) {
    DOM.addToCartBtn.style.background = '#38A169';
    DOM.addToCartBtn.textContent = 'Toegevoegd!';
    setTimeout(function () {
      DOM.addToCartBtn.style.background = '';
      DOM.addToCartBtn.textContent = 'In winkelmand';
    }, 1500);
  }

  // Update cart count in header if there's a .cart-count element
  if (data && data.cart_count) {
    var counters = document.querySelectorAll('.cart-count, .cart_count, .header-cart-count');
    for (var i = 0; i < counters.length; i++) {
      counters[i].textContent = data.cart_count;
    }
  }
}

/**
 * Remove any existing cart message (error or success).
 */
function removeCartMsg() {
  var existing = document.querySelector('.oz-cart-msg');
  if (existing) existing.remove();
}


/* ═══ SMOOTH SCROLL HELPER ═════════════════════════════════ */

/**
 * Smooth scroll to an element with a gentle overshoot.
 * Slow start, glides past target slightly, then eases back.
 * Offsets for sticky bar height + 20px padding.
 */
function smoothScrollTo(el) {
  // Account for any fixed/sticky header at the top (Flatsome's sticky header)
  var topOffset = 0;
  var stickyHeader = document.querySelector('.header-wrapper .stuck, #header.stuck, .header-main');
  if (stickyHeader) topOffset = stickyHeader.offsetHeight;
  var targetY = el.getBoundingClientRect().top + window.pageYOffset - topOffset - 48;
  var startY = window.pageYOffset;
  var diff = targetY - startY;
  var duration = 900;
  var start = null;

  // Gentle overshoot easing — soft decel with subtle bounce back
  function ease(t) {
    // Two-phase: ease out with tiny overshoot, then settle
    if (t < 0.82) {
      // Main phase — smooth cubic ease-out
      var p = t / 0.82;
      return 1.04 * (1 - Math.pow(1 - p, 3));
    }
    // Settle phase — ease back from 1.04 to 1.0
    var p2 = (t - 0.82) / 0.18;
    return 1.04 - 0.04 * (p2 * p2 * (3 - 2 * p2));
  }

  function step(timestamp) {
    if (!start) start = timestamp;
    var elapsed = timestamp - start;
    var progress = Math.min(elapsed / duration, 1);
    window.scrollTo(0, startY + diff * ease(progress));
    if (progress < 1) requestAnimationFrame(step);
  }

  requestAnimationFrame(step);
}


/**
 * Scroll to the color swatches section.
 * Used by sticky bar on base products — guides user to pick a color.
 */
function scrollToColors() {
  var colorSection = document.querySelector('[data-option="color"]');
  if (colorSection) {
    smoothScrollTo(colorSection);
    // Brief pulse effect to draw attention to the swatches
    colorSection.classList.add('oz-pulse');
    setTimeout(function() { colorSection.classList.remove('oz-pulse'); }, 1500);
  }
}


/**
 * Check if toepassing selection is required but not yet chosen.
 */
function needsToepassing() {
  return P.toepassing && P.toepassing.length && !S.toepassing;
}

/**
 * Scroll to the toepassing section and highlight it.
 * Same pattern as scrollToColors — pulse effect to draw attention.
 */
function scrollToToepassing() {
  var toeSection = document.querySelector('[data-option="toepassing"]');
  if (toeSection) {
    smoothScrollTo(toeSection);
    toeSection.classList.add('oz-highlight');
    setTimeout(function() { toeSection.classList.remove('oz-highlight'); }, 1500);
  }
}


/* ═══ MOBILE STICKY BAR ════════════════════════════════════ */

/**
 * Setup IntersectionObserver to show/hide sticky bar.
 * Shows when the add-to-cart button scrolls out of view.
 * On mobile: hides again when the options widget scrolls into view,
 * since the user is actively configuring and the sticky bar just wastes space.
 */
function setupStickyBar() {
  if (!DOM.stickyBar) return;

  // Observe the add-to-cart button — show sticky bar when it scrolls out of view
  var target = DOM.addToCartBtn || DOM.optionsWidget;
  if (!target) return;

  // Track both states to decide visibility
  var ctaOutOfView = false;
  var optionsInView = false;
  var isMobile = window.matchMedia('(max-width: 900px)');

  function updateStickyVisibility() {
    // Show sticky bar when CTA is out of view, BUT hide on mobile when options are visible
    var show = ctaOutOfView && !(isMobile.matches && optionsInView);
    DOM.stickyBar.classList.toggle('visible', show);

    // Add bottom padding to body so footer content isn't hidden behind the fixed bar
    if (show) {
      document.body.style.paddingBottom = DOM.stickyBar.offsetHeight + 'px';
    } else {
      document.body.style.paddingBottom = '';
    }
  }

  // Observer 1: add-to-cart button — triggers sticky bar when scrolled past
  var ctaObserver = new IntersectionObserver(function (entries) {
    ctaOutOfView = !entries[0].isIntersecting;
    updateStickyVisibility();
  }, { threshold: 0 });
  ctaObserver.observe(target);

  // Observer 2: options widget — hide sticky on mobile when user reaches options
  if (DOM.optionsWidget) {
    var optionsObserver = new IntersectionObserver(function (entries) {
      optionsInView = entries[0].isIntersecting;
      updateStickyVisibility();
    }, { threshold: 0 });
    optionsObserver.observe(DOM.optionsWidget);
  }
}


/* ═══ SCROLL-REVEAL ANIMATIONS ═════════════════════════════ */

/**
 * Observe .oz-reveal elements and add .oz-visible when they enter viewport.
 * Triggers once per element (unobserves after reveal). Uses a 15% threshold
 * so the animation starts just as the element becomes meaningfully visible.
 */
function setupScrollReveal() {
  var reveals = document.querySelectorAll('.oz-reveal');
  if (!reveals.length) return;

  // Respect reduced motion preference
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    for (var i = 0; i < reveals.length; i++) reveals[i].classList.add('oz-visible');
    return;
  }

  var observer = new IntersectionObserver(function (entries) {
    for (var i = 0; i < entries.length; i++) {
      if (entries[i].isIntersecting) {
        var el = entries[i].target;
        observer.unobserve(el);
        // Stagger based on block index for blocks visible at page load
        var idx = parseInt(el.getAttribute('data-reveal-index') || '0', 10);
        var delay = idx * 150;
        (function (target, d) {
          setTimeout(function () { target.classList.add('oz-visible'); }, d);
        })(el, delay);
      }
    }
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

  for (var j = 0; j < reveals.length; j++) observer.observe(reveals[j]);

  // Fade in orbs when the showcase wrapper enters the viewport
  var showcase = document.querySelector('.oz-showcase');
  if (showcase) {
    var orbObserver = new IntersectionObserver(function (entries) {
      if (entries[0].isIntersecting) {
        showcase.classList.add('oz-orbs-visible');
        orbObserver.disconnect();
      }
    }, { threshold: 0.05 });
    orbObserver.observe(showcase);
  }
}


/* ═══ INITIALIZATION ════════════════════════════════════════ */

/* ═══ BREADCRUMB CONTRAST DETECTION ══════════════════════ */

/**
 * Sample the top strip of an image and set breadcrumb text color
 * based on average luminance. Dark image = white text, light image = dark text.
 * Uses a tiny offscreen canvas to read pixel data.
 */
function adaptBreadcrumbColor(img, breadcrumb) {
  if (!img.naturalWidth) return; // image not yet decoded

  try {
    var canvas = document.createElement('canvas');
    var sampleHeight = 40; // matches breadcrumb overlay height
    // Scale down for speed — we only need rough luminance
    canvas.width = 80;
    canvas.height = Math.round(sampleHeight * (80 / img.naturalWidth)) || 10;
    var ctx = canvas.getContext('2d');
    // Draw only the top strip of the image
    ctx.drawImage(img, 0, 0, img.naturalWidth, sampleHeight, 0, 0, canvas.width, canvas.height);
    var data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;

    // Average luminance using perceptual weights
    var totalLum = 0;
    var pixels = data.length / 4;
    for (var i = 0; i < data.length; i += 4) {
      totalLum += 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
    }
    var avgLum = totalLum / pixels;

    // Dark image = white text + shadow for contrast.
    // Light image = dark text, no shadow needed.
    if (isDark) {
      breadcrumb.style.color = 'rgba(255,255,255,0.9)';
      breadcrumb.style.textShadow = '0 1px 3px rgba(0,0,0,0.5)';
    } else {
      breadcrumb.style.color = 'var(--oz-text-muted)';
      breadcrumb.style.textShadow = 'none';
    }
  } catch (e) {
    console.warn('[OZ] Breadcrumb contrast detection failed:', e.message);
  }
}
// Expose for navigation.js (separate module, can't import without circular dep)
window.adaptBreadcrumbColor = adaptBreadcrumbColor;

/* ═══ MOBILE USP TICKER ══════════════════════════════════ */

/**
 * Convert stacked USP chips into a compact auto-swiping horizontal ticker.
 * Saves ~50-80px of vertical space so color swatches stay in initial viewport.
 * Only runs on mobile (<=900px). Desktop keeps the stacked chip layout.
 */
function initUspTicker() {
  var uspContainer = document.querySelector('.oz-short-desc ul');
  if (!uspContainer || uspContainer.children.length < 2) return;

  // Wrap each <li> in a Swiper slide
  var items = uspContainer.querySelectorAll('li');
  var wrapper = document.createElement('div');
  wrapper.className = 'swiper-wrapper';

  for (var i = 0; i < items.length; i++) {
    var slide = document.createElement('div');
    slide.className = 'swiper-slide';
    slide.appendChild(items[i]);
    wrapper.appendChild(slide);
  }

  // Replace <ul> content with Swiper container
  uspContainer.innerHTML = '';
  uspContainer.classList.add('swiper', 'oz-usp-ticker');
  uspContainer.appendChild(wrapper);

  // Move elements above the gallery on mobile, hide breadcrumb.
  // Order: USP ticker → title → color label + price → gallery
  var shortDesc = uspContainer.closest('.oz-short-desc');
  var breadcrumb = document.querySelector('.oz-breadcrumb');
  var colorLabel = document.getElementById('colorLabel');
  var title = document.querySelector('.oz-product-title');
  var price = document.querySelector('.oz-product-base-price');
  var gallery = document.querySelector('.oz-product-gallery');

  if (gallery && gallery.parentNode) {
    // Wrap everything in a single container so the parent grid treats it as one item
    var mobileHeader = document.createElement('div');
    mobileHeader.className = 'oz-mobile-header';

    if (shortDesc) mobileHeader.appendChild(shortDesc);
    if (title) mobileHeader.appendChild(title);

    // Color label + price sit inline in a flex row
    var labelPriceRow = document.createElement('div');
    labelPriceRow.className = 'oz-mobile-label-price';
    if (colorLabel) labelPriceRow.appendChild(colorLabel);
    if (price) labelPriceRow.appendChild(price);
    mobileHeader.appendChild(labelPriceRow);

    gallery.parentNode.insertBefore(mobileHeader, gallery);
  }

  // Overlay breadcrumb on top of the gallery image
  if (breadcrumb && gallery) {
    breadcrumb.classList.add('oz-breadcrumb-overlay');
    gallery.insertBefore(breadcrumb, gallery.firstChild);

    // Set initial breadcrumb color based on image brightness
    var mainImg = document.getElementById('mainImg');
    if (mainImg) {
      if (mainImg.complete) {
        adaptBreadcrumbColor(mainImg, breadcrumb);
      } else {
        mainImg.addEventListener('load', function () {
          adaptBreadcrumbColor(mainImg, breadcrumb);
        }, { once: true });
      }
    }
  }

  // Load Swiper via shared loader and initialize auto-play carousel
  if (window.ozLoadSwiper) {
    window.ozLoadSwiper(function () {
      new Swiper('.oz-usp-ticker', {
        slidesPerView: 'auto',
        spaceBetween: 8,
        loop: true,
        autoplay: {
          delay: 3000,
          disableOnInteraction: false,
          pauseOnMouseEnter: true,
        },
        speed: 500,
      });
    });
  }
}

function init() {
  cacheDom();
  initNavigation(syncUI);

  // Mobile USP ticker — convert stacked chips to auto-swiping strip
  if (window.innerWidth <= 900) {
    initUspTicker();
  }

  // Register syncUI as the callback for tool state changes
  setToolSyncCallback(syncUI);

  // Build tool section DOM (if product has tools)
  buildToolSectionV2("toolSection");

  // Variant C: build a single "Kies je ruimte" dropdown that combines
  // primer + PU choice into one room-based pick. Only does anything
  // when html.oz-ab-tools-c is set; no-op for variants A and B.
  buildRuimteDropdown();

  // Restore PDP option state from sessionStorage. Covers two cases:
  // (1) full-page color-swatch nav within the same line, (2) cart→checkout
  // bounce-back where the user expects their PU/primer/qty/etc. preserved.
  restorePdpState();

  // Safety net: persist on pagehide too, in case the 200ms debounce hasn't
  // drained yet when the user navigates away. pagehide fires reliably on
  // every nav including bfcache; beforeunload is unreliable on mobile.
  window.addEventListener('pagehide', function () {
    // Bypass debounce — write synchronously
    if (_persistTimer) { clearTimeout(_persistTimer); _persistTimer = null; }
    try {
      sessionStorage.setItem(PDP_STATE_KEY, JSON.stringify({
        ts: Date.now(),
        toolMode: S.toolMode, extras: S.extras, tools: S.tools,
        puLayers: S.puLayers, primer: S.primer, colorfresh: S.colorfresh,
        toepassing: S.toepassing, pakket: S.pakket, qty: S.qty,
        addons: S.addons, selectedColor: S.selectedColor,
        colorMode: S.colorMode, customColor: S.customColor,
        formulaMode: S.formulaMode,
      }));
    } catch (e) {}
  });

  // Initial render — set highlights and prices from defaults
  syncUI();

  // Collapse swatch grid to 2 rows + add "Bekijk alle" chip / drawer.
  // Runs AFTER syncUI() so the RAL/NCS mode buttons exist (renderColorMode
  // builds them) and the drawer can include its RAL CTA. Variant C only.
  setupColorDrawer();

  // Frequently bought together carousel — no-op when the section isn't
  // rendered (caller checks for .oz-fbt presence). Server only renders it
  // when there's enough order signal, so most PDPs skip this branch entirely.
  initFrequentlyBought();

  // Event delegation: all clicks on the page
  document.addEventListener('click', handleClick);

  // Quantity input direct editing
  // 'input' fires on every keystroke/spinner tick — updates UI live
  // 'change' fires once on blur/commit — triggers analytics to avoid spam
  if (DOM.qtyInput) {
    DOM.qtyInput.addEventListener('input', handleQtyInput);
    DOM.qtyInput.addEventListener('change', function() {
      handleQtyInput();
      analytics.trackQtyChanged(S.qty);
    });
  }

  // Custom color input (delegated since it's built dynamically)
  // 'input' fires on every keystroke for live validation
  // 'blur' fires when user leaves the field — applies auto-formatting (RAL/NCS prefix)
  document.addEventListener('input', function (e) {
    if (e.target.id === 'customColorInput') {
      handleCustomColorInput(e);
    }
  });
  document.addEventListener('focusout', function (e) {
    if (e.target.id === 'customColorInput') {
      handleCustomColorInput(e);
    }
  });

  // Sheet swipe-to-dismiss (touch gesture on the handle area)
  setupSheetSwipe();

  // Sheet overlay close
  if (DOM.sheetOverlay) {
    DOM.sheetOverlay.addEventListener('click', closeSheet);

    // If user navigates away while sheet is open, close it
    window.addEventListener('beforeunload', function() {
      if (S.sheetOpen) closeSheet();
    });

    // Handle bfcache — browser back/forward restores stale DOM state.
    // Force reload if the page was restored from cache to reset all JS state.
    window.addEventListener('pageshow', function(e) {
      if (e.persisted) {
        if (S.sheetOpen) closeSheet();
        // bfcache restores old JS state with wrong product data — reload cleanly
        location.reload();
      }
    });

    // Intercept link clicks inside the sheet — close sheet before navigating.
    // This ensures the widget moves back to desktop before the page unloads.
    // Color swatches with data-product-id are handled by the main click
    // handler via pushState — let those bubble up normally.
    DOM.bottomSheet.addEventListener('click', function(e) {
      var link = e.target.closest('a[href]');
      if (link && S.sheetOpen) {
        // Color swatches use pushState — don't intercept, keep sheet open
        if (link.classList.contains('oz-color-swatch') && link.hasAttribute('data-product-id')) {
          return;
        }
        e.preventDefault();
        closeSheet();
        window.location.href = link.href;
      }
    });
  }

  // Escape key closes sheet
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      if (S.upsellOpen) { closeUpsell(); return; }
      if (S.sheetOpen) { closeSheet(); return; }
    }
  });

  // Scroll-reveal animations for showcase sections
  setupScrollReveal();

  // Mobile sticky bar
  setupStickyBar();

  // Re-check sticky bar on resize — observer handles show/hide automatically

  // Hide "read more" button if content is short enough
  if (DOM.descContent && DOM.readMoreBtn) {
    if (DOM.descContent.scrollHeight <= 120) {
      DOM.readMoreBtn.style.display = 'none';
      DOM.descContent.classList.add('expanded');
    }
  }
}

/* ═══════════════════════════════════════════════════════════════
 * LIGHTBOX — fullscreen image viewer with prev/next navigation
 * ═══════════════════════════════════════════════════════════════ */

var lightbox = {
  overlay: null,
  img: null,
  images: [],   // array of full-size URLs
  current: 0,   // index of currently displayed image

  /** Build the lightbox DOM (once) and append to body */
  create: function () {
    if (this.overlay) return;

    var ov = document.createElement('div');
    ov.className = 'oz-lightbox';
    ov.innerHTML =
      '<button class="oz-lb-close" aria-label="Sluiten">&times;</button>' +
      '<button class="oz-lb-prev" aria-label="Vorige">&#8249;</button>' +
      '<button class="oz-lb-next" aria-label="Volgende">&#8250;</button>' +
      '<div class="oz-lb-img-wrap"><img class="oz-lb-img" alt=""></div>';

    document.body.appendChild(ov);
    this.overlay = ov;
    this.img = ov.querySelector('.oz-lb-img');

    var self = this;

    // Close on overlay click (but not on image or buttons)
    ov.addEventListener('click', function (e) {
      if (e.target === ov || e.target.classList.contains('oz-lb-img-wrap')) {
        self.close();
      }
    });
    ov.querySelector('.oz-lb-close').addEventListener('click', function () { self.close(); });
    ov.querySelector('.oz-lb-prev').addEventListener('click', function (e) { e.stopPropagation(); self.prev(); });
    ov.querySelector('.oz-lb-next').addEventListener('click', function (e) { e.stopPropagation(); self.next(); });

    // Keyboard navigation
    document.addEventListener('keydown', function (e) {
      if (!self.overlay || !self.overlay.classList.contains('oz-lb-open')) return;
      if (e.key === 'Escape') self.close();
      if (e.key === 'ArrowLeft') self.prev();
      if (e.key === 'ArrowRight') self.next();
    });
  },

  /** Collect all gallery image URLs from thumbnails.
   *  Thumbs are rebuilt per-variant on pushState navigation.
   *  Falls back to current main image if no thumbs are visible. */
  collectImages: function () {
    var thumbStrip = document.querySelector('.oz-gallery-thumbs');
    var thumbsVisible = thumbStrip && thumbStrip.style.display !== 'none';

    this.images = [];

    if (thumbsVisible) {
      var thumbs = document.querySelectorAll('.oz-gallery-thumb');
      for (var i = 0; i < thumbs.length; i++) {
        var src = thumbs[i].getAttribute('data-full-src');
        if (src) this.images.push(src);
      }
    }

    // After pushState: no visible thumbs, use current main image
    if (!this.images.length && DOM.mainImg && DOM.mainImg.src) {
      this.images.push(DOM.mainImg.src);
    }
  },

  /** Open lightbox at given image URL */
  open: function (src) {
    this.create();
    this.collectImages();

    // Find index of clicked image
    this.current = 0;
    for (var i = 0; i < this.images.length; i++) {
      if (this.images[i] === src) { this.current = i; break; }
    }

    this.show();
    this.overlay.classList.add('oz-lb-open');
    document.body.style.overflow = 'hidden';

    // Hide arrows if only one image
    var hasMultiple = this.images.length > 1;
    this.overlay.querySelector('.oz-lb-prev').style.display = hasMultiple ? '' : 'none';
    this.overlay.querySelector('.oz-lb-next').style.display = hasMultiple ? '' : 'none';
  },

  /** Display the current image */
  show: function () {
    if (this.images[this.current]) {
      this.img.src = this.images[this.current];
    }
  },

  prev: function () {
    this.current = (this.current - 1 + this.images.length) % this.images.length;
    this.show();
  },

  next: function () {
    this.current = (this.current + 1) % this.images.length;
    this.show();
  },

  close: function () {
    if (this.overlay) {
      this.overlay.classList.remove('oz-lb-open');
      document.body.style.overflow = '';
    }
  }
};

/** Click handler for main gallery image — opens lightbox */
function openGalleryLightbox() {
  var mainImg = document.getElementById('mainImg');
  if (!mainImg) return;

  mainImg.addEventListener('click', function () {
    lightbox.open(mainImg.src);
  });
}


// Run when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function () { init(); openGalleryLightbox(); });
} else {
  init();
  openGalleryLightbox();
}


} // end ozProduct guard
