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

import { P, S, updateState, fmt, fmtDelta, calculatePrices, validateRal, validateNcs, hasAnyTool, clampToolQty, validateCartState, buildCartPayload } from './state.js';
import { DOM, cacheDom, show, hide } from './dom.js';
import { setToolSyncCallback, buildToolSectionV2, syncToolSectionV2 } from './tools.js';
import { initNavigation, navigateToVariant } from './navigation.js';
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
function syncUI() {
  var prices = calculatePrices(P, S);

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

  // Update sticky buttons: base products show "Kies kleur" until a valid
  // RAL/NCS color is entered; variants always show "In winkelmand"
  if (P.isBase) {
    var ready = !error;
    if (DOM.stickyBtn)  DOM.stickyBtn.textContent  = ready ? 'In winkelmand' : 'Kies kleur';
    if (DOM.stickyDBtn) DOM.stickyDBtn.textContent = ready ? 'In winkelmand' : 'Kies kleur';
  } else {
    if (DOM.stickyBtn)  DOM.stickyBtn.textContent  = 'In winkelmand';
    if (DOM.stickyDBtn) DOM.stickyDBtn.textContent = 'In winkelmand';
  }
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
    var btns = document.querySelectorAll('[' + spec.attr + ']');
    for (var i = 0; i < btns.length; i++) {
      var val = spec.parse ? spec.parse(btns[i].getAttribute(spec.attr)) : btns[i].getAttribute(spec.attr);
      btns[i].classList.toggle('selected', val === spec.value);
    }
  }
}

/**
 * Update the "selected value" labels in option headers.
 */
function renderSelectedLabels() {
  // Toepassing selected label
  var tpLabel = document.getElementById('selectedToepassingLabel');
  if (tpLabel && S.toepassing) {
    tpLabel.textContent = S.toepassing;
  }

  // Color label in header — shows selected static color, RAL/NCS code, or product color
  var colorLabel = document.getElementById('selectedColorLabel');
  if (colorLabel) {
    if (S.colorMode === 'ral_ncs' && S.customColor) {
      colorLabel.textContent = S.customColor;
    } else if (S.selectedColor) {
      colorLabel.textContent = S.selectedColor;
    } else if (P.currentColor) {
      colorLabel.textContent = P.currentColor;
    }
  }

  // Big color label above product title (for shared-color products)
  if (DOM.colorLabel) {
    if (S.colorMode === 'ral_ncs' && S.customColor) {
      DOM.colorLabel.textContent = S.customColor;
      DOM.colorLabel.style.display = '';
    } else if (S.selectedColor) {
      DOM.colorLabel.textContent = S.selectedColor;
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
      DOM.stickyDColor.textContent = S.selectedColor;
    } else {
      DOM.stickyDColor.textContent = P.currentColor || '';
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
      mobileColor = S.selectedColor;
    } else {
      mobileColor = P.currentColor || '';
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

    // PU layers — always show, even "Geen PU"
    if (S.puLayers !== null && S.puLayers !== undefined) {
      if (S.puLayers === 0) {
        parts.push('Geen PU');
      } else {
        parts.push(S.puLayers + ' PU ' + (S.puLayers === 1 ? 'laag' : 'lagen'));
      }
    }

    // Primer — always show (including "Geen")
    if (S.primer) {
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

  // Custom color input with NCS prefix and info box (matches reference design)
  html += '<div class="oz-custom-color-wrap' + (P.ralNcsOnly ? ' visible' : '') + '">';
  html += '<div class="oz-color-input-row">';
  html += '<span class="oz-color-prefix" id="colorInputPrefix">NCS S</span>';
  html += '<input type="text" class="oz-custom-color-input" id="customColorInput" ';
  html += 'placeholder="Bijv. RAL 7016">';
  html += '</div>';
  html += '<div class="oz-custom-color-hint" id="customColorHint">Formaat: RAL 7016</div>';
  html += '<div class="oz-custom-color-info">';
  html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';
  html += '<span>Wij mengen uw kleur op maat.</span>';
  html += '</div>';
  html += '</div>';

  DOM.colorModeSlot.innerHTML = html;
}


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
    var colorName = swatch.getAttribute('data-color') || '';
    analytics.trackColorSelected(colorName);

    // Static swatch (shared colors, e.g. Betonlook Verf) — no navigation
    if (swatch.hasAttribute('data-static')) {
      e.preventDefault();
      // Update state with selected color
      updateState({ selectedColor: colorName });
      // Update swatch highlight — remove from siblings, add to clicked
      var allSwatches = swatch.parentNode.querySelectorAll('.oz-color-swatch');
      for (var si = 0; si < allSwatches.length; si++) {
        allSwatches[si].classList.toggle('selected', allSwatches[si] === swatch);
      }
      syncUI();
      return;
    }

    // Normal swatch — pushState navigation (no page reload)
    e.preventDefault();
    var pid = parseInt(swatch.getAttribute('data-product-id'), 10);
    if (pid && P.variants && P.variants[pid] && navigateToVariant(pid)) {
      // syncUI called by navigation.js callback (handles both click + popstate)
    } else {
      // Fallback: full navigation if variant data is missing
      saveToolState();
      window.location.href = swatch.href;
    }
    return;
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

  // Mobile sticky button — base products without valid color scroll to colors, otherwise open sheet
  if (target === DOM.stickyBtn || target.closest('#stickyBtn')) {
    e.preventDefault();
    if (P.isBase && validateCartState(P, S)) {
      // No valid color yet — guide user to pick one
      scrollToColors();
    } else {
      openSheet();
    }
    return;
  }

  // Desktop sticky button — base products without valid color scroll to colors, otherwise add to cart
  if (target === DOM.stickyDBtn || target.closest('#stickyDBtn')) {
    e.preventDefault();
    if (P.isBase && validateCartState(P, S)) {
      // No valid color yet — guide user to pick one
      scrollToColors();
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

  // Crossfade: fade out, swap src, fade in
  DOM.mainImg.classList.add('oz-fade');
  setTimeout(function () {
    DOM.mainImg.src = newSrc;
    DOM.mainImg.onload = function () {
      DOM.mainImg.classList.remove('oz-fade');
      // Re-check breadcrumb contrast for the new image
      var bc = document.querySelector('.oz-breadcrumb-overlay');
      if (bc) adaptBreadcrumbColor(DOM.mainImg, bc);
    };
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

  // NCS pattern: 4 digits + optional dash + letter + 2 digits + letter
  // Handles: "2005-Y20R", "2005Y20R", "2005-y20r", and all prefixed variants
  var ncsMatch = core.match(/^(\d{4})-?([A-Za-z]\d{2}[A-Za-z])$/);
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

  // Check if it looks like RAL or NCS (also check the auto-formatted version)
  var checkValue = autoFormatColor(value);
  var isRal = validateRal(checkValue);
  var isNcs = validateNcs(checkValue);

  if (isRal || isNcs) {
    input.classList.remove('invalid');
    input.classList.add('valid');
    // Track valid custom color entry (only on blur to avoid spamming on keystrokes)
    if (e.type === 'blur' || e.type === 'focusout') {
      analytics.trackCustomColor(checkValue, isRal ? 'ral' : 'ncs');
    }
    if (hint) {
      hint.textContent = isRal ? 'RAL kleurcode herkend' : 'NCS kleurcode herkend';
      hint.className = 'oz-custom-color-hint success';
    }
  } else {
    input.classList.remove('valid');
    input.classList.add('invalid');
    if (hint) {
      hint.textContent = 'Voer een geldige RAL (4 cijfers) of NCS code in';
      hint.className = 'oz-custom-color-hint error';
    }
  }

  syncUI();
}


/* ═══ TOOL STATE PERSISTENCE (across color switches) ═══════ */

var TOOL_STATE_KEY = 'oz_bcw_tool_state';

/**
 * Save tool-related state to sessionStorage before navigating to a new color.
 * Only saves tool mode, extras, and tools — not color/qty/product-specific state.
 */
function saveToolState() {
  try {
    var data = {
      toolMode: S.toolMode,
      extras: S.extras,
      tools: S.tools,
      puLayers: S.puLayers,
      primer: S.primer,
      colorfresh: S.colorfresh,
      toepassing: S.toepassing,
      pakket: S.pakket,
      qty: S.qty,
      sheetOpen: S.sheetOpen,  // Preserve bottom sheet state across color switches
      timestamp: Date.now(),
    };
    sessionStorage.setItem(TOOL_STATE_KEY, JSON.stringify(data));
  } catch (e) {
    // sessionStorage not available — silently ignore
  }
}

/**
 * Restore tool state from sessionStorage on page load.
 * Only restores if saved within the last 60 seconds (i.e., from a color switch, not stale).
 */
function restoreToolState() {
  try {
    var raw = sessionStorage.getItem(TOOL_STATE_KEY);
    if (!raw) return;

    // Clear it immediately so it doesn't persist across unrelated visits
    sessionStorage.removeItem(TOOL_STATE_KEY);

    var data = JSON.parse(raw);

    // Only restore if saved less than 60 seconds ago (color switch)
    if (Date.now() - data.timestamp > 60000) return;

    // Restore tool selections
    if (data.toolMode) updateState({ toolMode: data.toolMode });
    if (data.qty > 1) updateState({ qty: data.qty });

    // Restore option selections (puLayers, primer, etc.)
    if (data.puLayers !== undefined && data.puLayers !== null) updateState({ puLayers: data.puLayers });
    if (data.primer) updateState({ primer: data.primer });
    if (data.colorfresh) updateState({ colorfresh: data.colorfresh });
    if (data.toepassing) updateState({ toepassing: data.toepassing });
    if (data.pakket) updateState({ pakket: data.pakket });

    // Restore nested tool/extra state — merge carefully
    if (data.extras) {
      Object.keys(data.extras).forEach(function(id) {
        if (S.extras[id]) S.extras[id] = data.extras[id];
      });
    }
    if (data.tools) {
      Object.keys(data.tools).forEach(function(id) {
        if (S.tools[id]) S.tools[id] = data.tools[id];
      });
    }

    // Update qty input to match restored state
    if (DOM.qtyInput && data.qty > 1) DOM.qtyInput.value = data.qty;

    // Re-open bottom sheet if it was open during color switch
    if (data.sheetOpen) {
      // Small delay to let the DOM settle after init
      setTimeout(function() { openSheet(); }, 100);
    }
  } catch (e) {
    // Parse error or sessionStorage unavailable — silently ignore
  }
}


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
      colorGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
      // Briefly highlight the color section to draw attention
      colorGroup.classList.add('oz-highlight');
      setTimeout(function() { colorGroup.classList.remove('oz-highlight'); }, 1500);
    }
    shakeButton();
    showCartError('Kies eerst een kleur om te bestellen.');
    return;
  }

  // Pure validation — returns error string or null
  var error = validateCartState(P, S);
  if (error) {
    // Scroll to tool section if it's a tool error
    if (error.indexOf('gereedschap') !== -1) {
      var toolGroup = document.querySelector('[data-option="tools"]');
      if (toolGroup) toolGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
 * Actually submit the cart — thin I/O shell.
 * Pure payload building is in buildCartPayload(), I/O is here.
 */
function submitCart() {
  // Pure: build payload object from state
  var payload = buildCartPayload(P, S);

  // Convert to FormData (browser API — lives in this shell module)
  var data = payloadToFormData(payload);

  // Disable button + show loading state
  setCartLoading(true);

  fetch(P.ajaxUrl, {
    method: 'POST',
    body: data,
    credentials: 'same-origin',
  })
    .then(function (res) { return res.json(); })
    .then(function (json) {
      setCartLoading(false);

      if (json.success) {
        // Track successful add to cart
        analytics.trackAddToCart(calculatePrices(P, S));

        // Close sheet if open
        if (S.sheetOpen) closeSheet();

        // Show success feedback
        showCartSuccess(json.data);

        // Notify cart drawer to open (custom event for our theme)
        document.dispatchEvent(new CustomEvent('oz-added-to-cart'));

        // Update WC cart fragments if available
        if (typeof jQuery !== 'undefined') {
          jQuery(document.body).trigger('wc_fragment_refresh');
        }
      } else {
        showCartError(json.data || 'Er ging iets mis.');
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
  var barHeight = DOM.stickyBar ? DOM.stickyBar.offsetHeight : 0;
  var targetY = el.getBoundingClientRect().top + window.pageYOffset - barHeight - 20;
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

    // Always white text — dark shadow strength adapts to image brightness.
    // Darker images need less shadow, lighter images need more.
    var shadowOpacity = isDark ? 0.3 : 0.6;
    breadcrumb.style.color = 'rgba(255,255,255,0.9)';
    breadcrumb.style.textShadow = '0 1px 3px rgba(0,0,0,' + shadowOpacity + '), 0 0 8px rgba(0,0,0,' + (shadowOpacity * 0.5) + ')';
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

  // Restore tool state from sessionStorage (e.g., after color switch)
  restoreToolState();

  // Initial render — set highlights and prices from defaults
  syncUI();

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

    // Handle bfcache — browser back/forward restores DOM with sheet open
    window.addEventListener('pageshow', function(e) {
      if (e.persisted && S.sheetOpen) closeSheet();
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
   *  After pushState navigation, gallery thumbs are hidden (stale),
   *  so fall back to the current main image as the only lightbox image. */
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
