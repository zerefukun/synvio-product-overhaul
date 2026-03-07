/**
 * OZ Variations BCW — Product Page JS
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
 *
 * All DOM interaction uses event delegation on #oz-product-page.
 * No jQuery dependency.
 *
 * @package OZ_Variations_BCW
 * @since 1.1.0
 */

(function () {
  'use strict';

  // ozProduct is injected by wp_localize_script in class-frontend-display.php
  if (typeof ozProduct === 'undefined') {
    return;
  }

  var P = ozProduct; // shorthand alias


  /* ═══ STATE ═══════════════════════════════════════════════════ */

  // Application state — single source of truth
  var S = {
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
  };

  // Initialize extras state from tool config
  if (P.hasTools && P.toolConfig && P.toolConfig.extras) {
    P.toolConfig.extras.forEach(function(e) { S.extras[e.id] = { on: false, qty: 0, size: 0 }; });
  }
  // Initialize individual tools state from tool config
  if (P.hasTools && P.toolConfig && P.toolConfig.tools) {
    P.toolConfig.tools.forEach(function(t) { S.tools[t.id] = { on: false, qty: 0, size: 0 }; });
  }

  /**
   * Find the default option value from an options array.
   * Each option has a 'default' boolean flag.
   *
   * @param {Array|false} options  Options array from ozProduct
   * @param {string}      key     Property name to return (e.g. 'layers', 'label')
   * @return {*}  The default value, or first value, or null
   */
  function findDefault(options, key) {
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


  /* ═══ PURE FUNCTIONS ═════════════════════════════════════════ */

  /**
   * Format a number as Euro currency string.
   * @param {number} n
   * @return {string}  e.g. "€12,50"
   */
  function fmt(n) {
    // wp_localize_script sends all values as strings — ensure numeric
    n = parseFloat(n) || 0;
    return '€' + n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  /**
   * Calculate all prices based on current state.
   * Pure function — reads S and P, returns price object.
   *
   * @return {Object}  { base, puPrice, primerPrice, colorfreshPrice, unitTotal, total }
   */
  function calculatePrices() {
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
  function validateRal(code) {
    return /^\d{4}$/.test(code.trim());
  }

  /**
   * Validate an NCS color code (e.g. "S 1050-Y90R").
   * Loose match — allows common NCS formats.
   * @param {string} code
   * @return {boolean}
   */
  function validateNcs(code) {
    return /^S\s?\d{4}-[A-Z]\d{2}[A-Z]$/i.test(code.trim());
  }

  /* ═══ TOOL PURE FUNCTIONS ════════════════════════════════════ */

  /**
   * Get the active price for a tool/extra item — uses selected size if available.
   */
  function getItemPrice(configItem, stateItem) {
    if (configItem.sizes && stateItem) return parseFloat(configItem.sizes[stateItem.size || 0].price) || 0;
    return parseFloat(configItem.price) || 0;
  }

  /**
   * Check if any tools are selected.
   * Returns true for 'set' mode, checks individual selections for 'individual' mode.
   */
  function hasAnyTool(toolMode, tools) {
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
  function clampToolQty(current, delta) {
    return Math.max(1, Math.min(99, current + delta));
  }

  /* Checkmark SVG for tool custom checkbox */
  var CHECKMARK_SVG = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2.5 6l2.5 2.5 4.5-5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

  /* Info icon SVG for smart nudge */
  var NUDGE_ICON = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';




  /* ═══ DOM HELPERS ═════════════════════════════════════════════ */

  // Cache DOM references for frequently accessed elements
  var DOM = {};

  function cacheDom() {
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
  function show(el) { if (el) el.style.display = ''; }
  function hide(el) { if (el) el.style.display = 'none'; }


  /* ═══ RENDERERS ══════════════════════════════════════════════ */

  /**
   * Master render — updates all dynamic UI from state.
   * Called after every state change.
   */
  function syncUI() {
    var prices = calculatePrices();

    // Update price breakdown
    renderBreakdown(prices);

    // Update sticky bar price
    if (DOM.stickyPrice) DOM.stickyPrice.textContent = fmt(prices.total);

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
  }

  /**
   * Render the price breakdown panel.
   * Shows/hides addon price lines based on current selection.
   */
  function renderBreakdown(prices) {
    if (DOM.priceBase) DOM.priceBase.textContent = fmt(prices.base);

    // PU line — show if PU options exist and price > 0
    if (DOM.pricePuLine) {
      if (prices.puPrice > 0) {
        show(DOM.pricePuLine);
        DOM.pricePu.textContent = fmt(prices.puPrice);
      } else {
        hide(DOM.pricePuLine);
      }
    }

    // Primer line — show if price > 0
    if (DOM.pricePrimerLine) {
      if (prices.primerPrice > 0) {
        show(DOM.pricePrimerLine);
        DOM.pricePrimer.textContent = fmt(prices.primerPrice);
        DOM.pricePrimerLabel.textContent = 'Primer: ' + S.primer;
      } else {
        hide(DOM.pricePrimerLine);
      }
    }

    // Colorfresh line — show if price > 0
    if (DOM.priceColorfreshLine) {
      if (prices.colorfreshPrice > 0) {
        show(DOM.priceColorfreshLine);
        DOM.priceColorfresh.textContent = fmt(prices.colorfreshPrice);
      } else {
        hide(DOM.priceColorfreshLine);
      }
    }

    // Tools line — show if tool cost > 0
    if (DOM.priceToolsLine) {
      if (prices.toolsTotal > 0) {
        show(DOM.priceToolsLine);
        if (DOM.priceToolsLabel) DOM.priceToolsLabel.textContent = prices.toolsLabel;
        if (DOM.priceTools) DOM.priceTools.textContent = fmt(prices.toolsTotal);
      } else {
        hide(DOM.priceToolsLine);
      }
    }

    // Sheet tools line
    if (DOM.sheetPriceToolsLine) {
      if (prices.toolsTotal > 0) {
        show(DOM.sheetPriceToolsLine);
        if (DOM.sheetPriceToolsLabel) DOM.sheetPriceToolsLabel.textContent = prices.toolsLabel;
        if (DOM.sheetPriceTools) DOM.sheetPriceTools.textContent = fmt(prices.toolsTotal);
      } else {
        hide(DOM.sheetPriceToolsLine);
      }
    }

    // Quantity line — show if qty > 1
    if (DOM.priceQtyLine) {
      if (S.qty > 1) {
        show(DOM.priceQtyLine);
        DOM.priceQtyLabel.textContent = S.qty + '× ' + fmt(prices.unitTotal);
        DOM.priceQty.textContent = fmt(prices.total);
      } else {
        hide(DOM.priceQtyLine);
      }
    }

    // Total
    if (DOM.priceTotal) DOM.priceTotal.textContent = fmt(prices.total);
  }

  /**
   * Highlight the selected button in each option group.
   * Uses data- attributes to match state values.
   */
  function renderOptionHighlights() {
    // PU buttons
    var puBtns = document.querySelectorAll('[data-pu]');
    for (var i = 0; i < puBtns.length; i++) {
      var layers = parseInt(puBtns[i].getAttribute('data-pu'), 10);
      puBtns[i].classList.toggle('selected', layers === S.puLayers);
    }

    // Primer buttons
    var primerBtns = document.querySelectorAll('[data-primer]');
    for (var i = 0; i < primerBtns.length; i++) {
      primerBtns[i].classList.toggle('selected', primerBtns[i].getAttribute('data-primer') === S.primer);
    }

    // Colorfresh buttons
    var cfBtns = document.querySelectorAll('[data-colorfresh]');
    for (var i = 0; i < cfBtns.length; i++) {
      cfBtns[i].classList.toggle('selected', cfBtns[i].getAttribute('data-colorfresh') === S.colorfresh);
    }

    // Toepassing buttons
    var tpBtns = document.querySelectorAll('[data-toepassing]');
    for (var i = 0; i < tpBtns.length; i++) {
      tpBtns[i].classList.toggle('selected', tpBtns[i].getAttribute('data-toepassing') === S.toepassing);
    }

    // Pakket buttons
    var pkBtns = document.querySelectorAll('[data-pakket]');
    for (var i = 0; i < pkBtns.length; i++) {
      pkBtns[i].classList.toggle('selected', pkBtns[i].getAttribute('data-pakket') === S.pakket);
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

    // Color label in header
    var colorLabel = document.getElementById('selectedColorLabel');
    if (colorLabel) {
      if (S.colorMode === 'ral_ncs' && S.customColor) {
        colorLabel.textContent = S.customColor;
      } else if (P.currentColor) {
        colorLabel.textContent = P.currentColor;
      }
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

    // Show/hide swatches (hide when in ral_ncs mode)
    var swatches = document.querySelector('.oz-color-swatches');
    if (swatches) {
      swatches.style.display = S.colorMode === 'ral_ncs' ? 'none' : '';
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

    // Custom color input (always present if hasRalNcs)
    html += '<div class="oz-custom-color-wrap' + (P.ralNcsOnly ? ' visible' : '') + '">';
    html += '<input type="text" class="oz-custom-color-input" id="customColorInput" ';
    html += 'placeholder="Bijv. RAL 7016 of S 1050-Y90R">';
    html += '<div class="oz-custom-color-hint" id="customColorHint"></div>';
    html += '</div>';

    DOM.colorModeSlot.innerHTML = html;
  }


  

  /* ═══ TOOL DOM BUILDERS ══════════════════════════════════════ */

  /**
   * Build a single tool/extra row with checkbox, name, price, qty stepper, and optional size pills.
   *
   * @param {Object}   item       Config object: { id, name, price, note?, sizes? }
   * @param {string}   dataAttr   'extra' or 'tool' — sets data-extra or data-tool on the row
   * @param {Function} onToggle   Called when row is clicked: fn(id)
   * @param {Function} onQtyDec   Called when minus clicked: fn(id)
   * @param {Function} onQtyInc   Called when plus clicked: fn(id)
   * @param {Function} onQtyEdit  Called when qty input changes: fn(id, inputEl)
   * @param {Function} onSizeChange Called when size pill clicked: fn(id, sizeIndex)
   */
  function buildToolRow(item, dataAttr, onToggle, onQtyDec, onQtyInc, onQtyEdit, onSizeChange) {
    var row = document.createElement('div');
    row.className = 'oz-tool-item';
    row.dataset[dataAttr] = item.id;
    var noteHtml = item.note ? '<span style="font-size:11px;color:var(--oz-text-muted);margin-left:4px;">(' + item.note + ')</span>' : '';
    row.innerHTML =
      '<div class="oz-tool-check">' + CHECKMARK_SVG + '</div>' +
      '<span class="oz-tool-name">' + item.name + noteHtml + '</span>' +
      '<span class="oz-tool-price">' + fmt(item.price) + '</span>' +
      '<div class="oz-tool-qty">' +
        '<div class="oz-tool-qty-wrap">' +
          '<button class="oz-tool-qty-btn oz-tool-qty-dec">\u2212</button>' +
          '<input type="number" class="oz-tool-qty-input" value="1" min="1" max="99">' +
          '<button class="oz-tool-qty-btn oz-tool-qty-inc">+</button>' +
        '</div>' +
      '</div>';

    // Size selector — segmented pills below tool name, only for items with sizes
    if (item.sizes && item.sizes.length > 1) {
      var sizesDiv = document.createElement('div');
      sizesDiv.className = 'oz-tool-sizes';
      item.sizes.forEach(function(sz, idx) {
        var btn = document.createElement('button');
        btn.className = 'oz-tool-size-btn' + (idx === 0 ? ' selected' : '');
        btn.dataset.sizeIdx = idx;
        btn.textContent = sz.label + ' ' + fmt(sz.price);
        btn.addEventListener('click', function(ev) {
          ev.stopPropagation();
          if (onSizeChange) onSizeChange(item.id, idx);
        });
        sizesDiv.appendChild(btn);
      });
      row.appendChild(sizesDiv);
    }

    row.addEventListener('click', function() { onToggle(item.id); });
    row.querySelector('.oz-tool-qty-dec').addEventListener('click', function(ev) {
      ev.stopPropagation(); onQtyDec(item.id);
    });
    row.querySelector('.oz-tool-qty-inc').addEventListener('click', function(ev) {
      ev.stopPropagation(); onQtyInc(item.id);
    });
    var qtyInput = row.querySelector('.oz-tool-qty-input');
    qtyInput.addEventListener('click', function(ev) { ev.stopPropagation(); });
    qtyInput.addEventListener('change', function() { onQtyEdit(item.id, this); });

    return row;
  }

  /**
   * Build the entire tool section DOM — mode toggle, set contents, extras, individual list, nudge.
   * Called once at init. The section lives inside optionsWidget and moves with it.
   */
  function buildToolSectionV2(sectionId) {
    if (!P.hasTools || !P.toolConfig) return;
    var TC = P.toolConfig;
    var section = document.getElementById(sectionId);
    if (!section || section.children.length > 0) return;

    // Mode toggle: Geen / Kant & Klaar / Zelf samenstellen
    var mode = document.createElement('div');
    mode.className = 'oz-tool-mode';

    var btnNone = document.createElement('button');
    btnNone.className = 'oz-tool-mode-btn';
    btnNone.dataset.mode = 'none';
    btnNone.textContent = 'Geen';
    btnNone.addEventListener('click', function() { setToolMode('none'); });

    var btnSet = document.createElement('button');
    btnSet.className = 'oz-tool-mode-btn';
    btnSet.dataset.mode = 'set';
    var setName = TC.toolSet.name.replace('Gereedschapset ', '');
    btnSet.innerHTML = setName + ' <span class="oz-price-add">+' + fmt(TC.toolSet.price) + '</span>';
    btnSet.addEventListener('click', function() { setToolMode('set'); });

    var btnInd = document.createElement('button');
    btnInd.className = 'oz-tool-mode-btn';
    btnInd.dataset.mode = 'individual';
    btnInd.textContent = 'Zelf samenstellen';
    btnInd.addEventListener('click', function() { setToolMode('individual'); });

    mode.appendChild(btnNone);
    mode.appendChild(btnSet);
    mode.appendChild(btnInd);
    section.appendChild(mode);

    // Set contents — what is in the box
    var contents = document.createElement('div');
    contents.className = 'oz-set-contents';
    contents.innerHTML = '<strong>Bevat:</strong> ' + TC.toolSet.contents.join(', ');
    section.appendChild(contents);

    // Extras section — consumables to add on top of set
    var extrasWrap = document.createElement('div');
    extrasWrap.className = 'oz-tool-extras-wrap';

    var extrasLabel = document.createElement('div');
    extrasLabel.className = 'oz-extras-label';
    extrasLabel.textContent = 'Extra nodig?';
    extrasWrap.appendChild(extrasLabel);

    var extrasList = document.createElement('div');
    extrasList.className = 'oz-tool-list';
    TC.extras.forEach(function(e) {
      extrasList.appendChild(buildToolRow(
        e, 'extra', toggleExtra,
        function(id) { changeExtraQty(id, -1); },
        function(id) { changeExtraQty(id, 1); },
        onExtraQtyChange,
        changeExtraSize
      ));
    });
    extrasWrap.appendChild(extrasList);

    // Smart nudge — shown when qty >= threshold and no extra PU rollers
    var nudge = document.createElement('div');
    nudge.className = 'oz-smart-nudge';
    var nudgeM2 = (TC.nudgeQtyThreshold || 3) * (parseFloat(P.unitM2) || 5);
    nudge.innerHTML = NUDGE_ICON +
      '<span><strong>Groot project?</strong> PU rollers verharden na ~2 uur gebruik. Bij meer dan ' +
      nudgeM2 + 'm\u00b2 raden wij extra rollers aan.</span>';
    extrasWrap.appendChild(nudge);

    section.appendChild(extrasWrap);

    // Individual tool list — for "Zelf samenstellen" mode
    var indList = document.createElement('div');
    indList.className = 'oz-tool-list';
    indList.dataset.listType = 'individual';
    TC.tools.forEach(function(t) {
      indList.appendChild(buildToolRow(
        t, 'tool', toggleTool,
        function(id) { changeToolQty(id, -1); },
        function(id) { changeToolQty(id, 1); },
        onToolQtyChange,
        changeToolSize
      ));
    });
    section.appendChild(indList);
  }

  /**
   * Sync tool section state to DOM — handles set contents, extras, individual, nudge.
   * Called from syncUI() on every state change.
   */
  function syncToolSectionV2(sectionId, toolMode, tools, extras, qty) {
    if (!P.hasTools || !P.toolConfig) return;
    var TC = P.toolConfig;
    var section = document.getElementById(sectionId);
    if (!section) return;

    // Sync mode toggle buttons
    section.querySelectorAll('.oz-tool-mode-btn').forEach(function(btn) {
      btn.classList.toggle('selected', btn.dataset.mode === toolMode);
    });

    // Show/hide set contents
    var contentsEl = section.querySelector('.oz-set-contents');
    if (contentsEl) contentsEl.classList.toggle('visible', toolMode === 'set');

    // Show/hide extras section (only when set is active)
    var extrasWrap = section.querySelector('.oz-tool-extras-wrap');
    if (extrasWrap) extrasWrap.classList.toggle('visible', toolMode === 'set');

    // Sync extra item states
    TC.extras.forEach(function(e) {
      var row = section.querySelector('[data-extra="' + e.id + '"]');
      if (!row) return;
      var st = extras[e.id];
      var isOn = st && st.on;
      row.classList.toggle('selected', isOn);
      var qtyDiv = row.querySelector('.oz-tool-qty');
      var qtyInput = row.querySelector('.oz-tool-qty-input');
      if (qtyDiv) qtyDiv.classList.toggle('visible', isOn);
      if (qtyInput && isOn) qtyInput.value = st.qty;

      // Size selector sync
      var sizesDiv = row.querySelector('.oz-tool-sizes');
      if (sizesDiv) {
        sizesDiv.classList.toggle('visible', isOn);
        if (isOn) {
          sizesDiv.querySelectorAll('.oz-tool-size-btn').forEach(function(btn) {
            btn.classList.toggle('selected', parseInt(btn.dataset.sizeIdx) === (st.size || 0));
          });
        }
      }
      var priceSpan = row.querySelector('.oz-tool-price');
      if (priceSpan) priceSpan.textContent = fmt(getItemPrice(e, st));
    });

    // Smart nudge: show when qty >= threshold AND no extra PU rollers added
    var nudgeEl = section.querySelector('.oz-smart-nudge');
    if (nudgeEl) {
      var hasExtraRollers = extras['pu-roller'] && extras['pu-roller'].on;
      var threshold = TC.nudgeQtyThreshold || 3;
      nudgeEl.classList.toggle('visible', toolMode === 'set' && qty >= threshold && !hasExtraRollers);
    }

    // Show/hide individual list — only in 'individual' mode
    var indList = section.querySelector('[data-list-type="individual"]');
    if (indList) indList.classList.toggle('hidden', toolMode !== 'individual');

    // Sync individual item states
    TC.tools.forEach(function(t) {
      var row = section.querySelector('[data-tool="' + t.id + '"]');
      if (!row) return;
      var st = tools[t.id];
      var isOn = toolMode === 'individual' && st && st.on;
      row.classList.toggle('selected', isOn);
      var qtyDiv = row.querySelector('.oz-tool-qty');
      var qtyInput = row.querySelector('.oz-tool-qty-input');
      if (qtyDiv) qtyDiv.classList.toggle('visible', isOn);
      if (qtyInput && isOn) qtyInput.value = st.qty;

      // Size selector sync
      var sizesDiv = row.querySelector('.oz-tool-sizes');
      if (sizesDiv) {
        sizesDiv.classList.toggle('visible', isOn);
        if (isOn) {
          sizesDiv.querySelectorAll('.oz-tool-size-btn').forEach(function(btn) {
            btn.classList.toggle('selected', parseInt(btn.dataset.sizeIdx) === (st.size || 0));
          });
        }
      }
      var priceSpan = row.querySelector('.oz-tool-price');
      if (priceSpan) priceSpan.textContent = fmt(getItemPrice(t, st));
    });
  }


  /* ═══ TOOL EVENT HANDLERS ════════════════════════════════════ */

  /** Switch tool mode: 'none', 'set', or 'individual' */
  function setToolMode(mode) {
    S.toolMode = mode;
    syncUI();
  }

  /** Toggle an individual tool on/off — preserves size selection */
  function toggleTool(id) {
    if (S.toolMode !== 'individual') return;
    var prev = S.tools[id];
    var nowOn = !prev.on;
    S.tools[id] = { on: nowOn, qty: nowOn ? 1 : 0, size: prev.size };
    syncUI();
  }

  /** Change qty of an individual tool */
  function changeToolQty(id, delta) {
    var prev = S.tools[id];
    S.tools[id] = { on: prev.on, qty: clampToolQty(prev.qty, delta), size: prev.size };
    syncUI();
  }

  /** Manual qty input for a tool */
  function onToolQtyChange(id, inputEl) {
    var prev = S.tools[id];
    S.tools[id] = { on: prev.on, qty: clampToolQty(parseInt(inputEl.value) || 1, 0), size: prev.size };
    syncUI();
  }

  /** Change size of an individual tool */
  function changeToolSize(id, sizeIdx) {
    var prev = S.tools[id];
    S.tools[id] = { on: prev.on, qty: prev.qty, size: sizeIdx };
    syncUI();
  }

  /** Toggle an extra item on/off (on top of set) */
  function toggleExtra(id) {
    var prev = S.extras[id];
    var nowOn = !prev.on;
    S.extras[id] = { on: nowOn, qty: nowOn ? 1 : 0, size: prev.size };
    syncUI();
  }

  /** Change qty of an extra item */
  function changeExtraQty(id, delta) {
    var prev = S.extras[id];
    S.extras[id] = { on: prev.on, qty: clampToolQty(prev.qty, delta), size: prev.size };
    syncUI();
  }

  /** Manual qty input for an extra */
  function onExtraQtyChange(id, inputEl) {
    var prev = S.extras[id];
    S.extras[id] = { on: prev.on, qty: clampToolQty(parseInt(inputEl.value) || 1, 0), size: prev.size };
    syncUI();
  }

  /** Change size of an extra item */
  function changeExtraSize(id, sizeIdx) {
    var prev = S.extras[id];
    S.extras[id] = { on: prev.on, qty: prev.qty, size: sizeIdx };
    syncUI();
  }


  /* ═══ UPSELL MODAL HANDLERS ═════════════════════════════════ */

  /** Open upsell modal — called from addToCart when no tools selected */
  function openUpsell() {
    S.upsellOpen = true;
    document.body.style.overflow = 'hidden';
    renderUpsellModal();
  }

  /** Close upsell modal */
  function closeUpsell() {
    S.upsellOpen = false;
    document.body.style.overflow = '';
    renderUpsellModal();
  }

  /** Upsell: add the Kant & Klaar set and proceed to cart */
  function upsellAddSet() {
    S.toolMode = 'set';
    closeUpsell();
    syncUI();
    submitCart();
  }

  /** Upsell: skip — go to cart without tools */
  function upsellSkip() {
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

/* ═══ EVENT HANDLERS ═════════════════════════════════════════ */

  /**
   * Main event delegation handler.
   * All clicks on the product page are routed through here.
   */
  function handleClick(e) {
    var target = e.target;

    // Walk up to find the actionable element (button, swatch, etc.)
    var btn = target.closest('[data-pu], [data-primer], [data-colorfresh], [data-toepassing], [data-pakket]');
    var thumb = target.closest('.oz-gallery-thumb');
    var infoBtn = target.closest('.oz-info-btn');
    var qtyBtn = target.closest('[data-qty-delta]');
    var modeBtn = target.closest('.oz-color-mode-btn');

    // Option button clicks — update state + re-render
    if (btn) {
      e.preventDefault();
      if (btn.hasAttribute('data-pu')) {
        S.puLayers = parseInt(btn.getAttribute('data-pu'), 10);
      } else if (btn.hasAttribute('data-primer')) {
        S.primer = btn.getAttribute('data-primer');
      } else if (btn.hasAttribute('data-colorfresh')) {
        S.colorfresh = btn.getAttribute('data-colorfresh');
      } else if (btn.hasAttribute('data-toepassing')) {
        S.toepassing = btn.getAttribute('data-toepassing');
      } else if (btn.hasAttribute('data-pakket')) {
        S.pakket = btn.getAttribute('data-pakket');
      }
      syncUI();
      return;
    }

    // Gallery thumbnail click — switch main image with crossfade
    if (thumb) {
      e.preventDefault();
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
      S.colorMode = modeBtn.getAttribute('data-mode');
      syncUI();
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

    // Sticky bar button — opens bottom sheet on mobile
    if (target === DOM.stickyBtn || target.closest('#stickyBtn')) {
      e.preventDefault();
      openSheet();
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
   * @param {number} delta
   */
  function changeQty(delta) {
    var newQty = S.qty + delta;
    newQty = Math.max(1, Math.min(99, newQty));
    S.qty = newQty;
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
    S.qty = val;
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
   * Handle custom color input (RAL/NCS field).
   * Validates on every keystroke.
   */
  function handleCustomColorInput(e) {
    var input = e.target;
    var value = input.value.trim();
    var hint = document.getElementById('customColorHint');

    S.customColor = value;

    // Clear validation state if empty
    if (!value) {
      input.classList.remove('valid', 'invalid');
      if (hint) { hint.textContent = ''; hint.className = 'oz-custom-color-hint'; }
      syncUI();
      return;
    }

    // Check if it looks like RAL or NCS
    var isRal = validateRal(value);
    var isNcs = validateNcs(value);

    if (isRal || isNcs) {
      input.classList.remove('invalid');
      input.classList.add('valid');
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


  /* ═══ BOTTOM SHEET ═══════════════════════════════════════════ */

  /**
   * Open the bottom sheet on mobile.
   * Moves #optionsWidget into the sheet slot.
   */
  function openSheet() {
    if (!DOM.bottomSheet || !DOM.sheetOverlay || !DOM.optionsWidget) return;

    // Remember scroll position so we can restore it on close
    S.scrollY = window.scrollY;

    // Move options widget into sheet
    DOM.slotSheet.appendChild(DOM.optionsWidget);

    // Collapse the empty desktop home so the page doesn't show a gap
    if (DOM.desktopHome) DOM.desktopHome.style.minHeight = '0';

    S.sheetOpen = true;
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

    S.sheetOpen = false;
    DOM.sheetOverlay.classList.remove('open');
    DOM.bottomSheet.classList.remove('open');
    document.body.style.overflow = '';

    // Move options widget back to its desktop home
    if (DOM.desktopHome) DOM.desktopHome.appendChild(DOM.optionsWidget);

    // Restore scroll position
    if (S.scrollY !== undefined) window.scrollTo(0, S.scrollY);
  }


  /* ═══ AJAX ADD TO CART ═══════════════════════════════════════ */

  /**
   * Submit add-to-cart via AJAX.
   * Sends product + addon data to oz_bcw_add_to_cart action.
   */
  function addToCart() {
    // Validate RAL/NCS if in that mode
    if (S.colorMode === 'ral_ncs') {
      if (!S.customColor) {
        shakeButton();
        showCartError('Vul een RAL of NCS kleurcode in.');
        return;
      }
      if (!validateRal(S.customColor) && !validateNcs(S.customColor)) {
        shakeButton();
        showCartError('Ongeldige RAL of NCS kleurcode.');
        return;
      }
    }

    // Tool validation — individual mode must have at least 1 tool selected
    if (P.hasTools && S.toolMode === 'individual' && !hasAnyTool(S.toolMode, S.tools)) {
      var toolGroup = document.querySelector('[data-option="tools"]');
      if (toolGroup) toolGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
      shakeButton();
      showCartError('Kies minimaal 1 gereedschap of kies een andere optie.');
      return;
    }

    // Upsell: if no tools selected at all and product has tools, show upsell modal
    if (P.hasTools && S.toolMode === 'none') {
      openUpsell();
      return;
    }

    // Proceed to actually submit
    submitCart();
  }

  /**
   * Actually submit the cart — called after validation and upsell checks pass.
   */
  function submitCart() {
    // Build form data
    var data = new FormData();
    data.append('action', 'oz_bcw_add_to_cart');
    data.append('nonce', P.nonce);
    data.append('product_id', P.productId);
    data.append('quantity', S.qty);

    // Addon fields — same keys as OZ_Cart_Manager::extract_post_data()
    if (S.puLayers !== null)  data.append('oz_pu_layers', S.puLayers);
    if (S.primer)             data.append('oz_primer', S.primer);
    if (S.colorfresh)         data.append('oz_colorfresh', S.colorfresh);
    if (S.toepassing)         data.append('oz_toepassing', S.toepassing);
    if (S.pakket)             data.append('oz_pakket', S.pakket);

    // Color mode
    data.append('oz_color_mode', S.colorMode);
    if (S.colorMode === 'ral_ncs') {
      data.append('oz_custom_color', S.customColor);
    }

    // Tool data
    if (P.hasTools) {
      data.append('oz_tool_mode', S.toolMode);
      if (S.toolMode === 'set') {
        data.append('oz_tool_set_id', P.toolConfig.toolSet.id);
        // Extras on top of set
        P.toolConfig.extras.forEach(function(e) {
          var st = S.extras[e.id];
          if (st && st.on && st.qty > 0) {
            var sizeData = e.sizes ? e.sizes[st.size || 0] : e;
            data.append('oz_extras[' + e.id + '][qty]', st.qty);
            data.append('oz_extras[' + e.id + '][wcId]', sizeData.wcId);
            if (sizeData.wapoAddon) {
              data.append('oz_extras[' + e.id + '][wapoAddon]', sizeData.wapoAddon);
            }
          }
        });
      } else if (S.toolMode === 'individual') {
        P.toolConfig.tools.forEach(function(t) {
          var st = S.tools[t.id];
          if (st && st.on && st.qty > 0) {
            var sizeData = t.sizes ? t.sizes[st.size || 0] : t;
            data.append('oz_tools[' + t.id + '][qty]', st.qty);
            data.append('oz_tools[' + t.id + '][wcId]', sizeData.wcId);
            if (sizeData.wapoAddon) {
              data.append('oz_tools[' + t.id + '][wapoAddon]', sizeData.wapoAddon);
            }
          }
        });
      }
    }

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
          // Close sheet if open
          if (S.sheetOpen) closeSheet();

          // Show success feedback
          showCartSuccess(json.data);

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
    el.style.cssText = 'color:#E53E3E;font-size:13px;margin-top:8px;';
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


  /* ═══ MOBILE STICKY BAR ═════════════════════════════════════ */

  /**
   * Setup IntersectionObserver to show/hide sticky bar.
   * Shows when the add-to-cart button scrolls out of view.
   */
  function setupStickyBar() {
    if (!DOM.stickyBar || !DOM.addToCartBtn) return;

    // Only observe on mobile (< 900px)
    if (window.innerWidth >= 900) return;

    var observer = new IntersectionObserver(function (entries) {
      // Show sticky bar when add-to-cart button is NOT visible
      var isVisible = entries[0].isIntersecting;
      DOM.stickyBar.classList.toggle('visible', !isVisible);
    }, { threshold: 0 });

    observer.observe(DOM.addToCartBtn);
  }


  /* ═══ INITIALIZATION ═════════════════════════════════════════ */

  function init() {
    cacheDom();

    // Initial render — set highlights and prices from defaults
    // Build tool section DOM (if product has tools)
    buildToolSectionV2("toolSection");

    syncUI();

    // Event delegation: all clicks on the page
    document.addEventListener('click', handleClick);

    // Quantity input direct editing
    if (DOM.qtyInput) {
      DOM.qtyInput.addEventListener('change', handleQtyInput);
      DOM.qtyInput.addEventListener('input', handleQtyInput);
    }

    // Custom color input (delegated since it's built dynamically)
    document.addEventListener('input', function (e) {
      if (e.target.id === 'customColorInput') {
        handleCustomColorInput(e);
      }
    });

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

      // Intercept link clicks inside the sheet — close sheet before navigating
      // This ensures the widget moves back to desktop before the page unloads
      DOM.bottomSheet.addEventListener('click', function(e) {
        var link = e.target.closest('a[href]');
        if (link && S.sheetOpen) {
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

    // Re-check sticky bar on resize
    window.addEventListener('resize', function () {
      if (window.innerWidth >= 900 && DOM.stickyBar) {
        DOM.stickyBar.classList.remove('visible');
      }
    });

    // Hide "read more" button if content is short enough
    if (DOM.descContent && DOM.readMoreBtn) {
      if (DOM.descContent.scrollHeight <= 120) {
        DOM.readMoreBtn.style.display = 'none';
        DOM.descContent.classList.add('expanded');
      }
    }
  }

  // Run when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();


  // ═══ COOKIEBOT / PRIVACY BANNER — hide for 30s unless user interacts ═══
  (function() {
    document.body.classList.add('oz-hide-banners');
    var timer = setTimeout(function() {
      document.body.classList.remove('oz-hide-banners');
    }, 30000);

    // If user scrolls or touches, they're active — show banners sooner?
    // No — user said 30s of standing still. So we only remove on timeout.
    // But if they interact, we could reset. User said "unless if you stand still for 30seconds"
    // meaning: show banners only after 30s of inactivity. Reset timer on interaction.
    var resetTimer = function() {
      clearTimeout(timer);
      timer = setTimeout(function() {
        document.body.classList.remove('oz-hide-banners');
      }, 30000);
    };

    document.addEventListener('scroll', resetTimer, { passive: true });
    document.addEventListener('touchstart', resetTimer, { passive: true });
    document.addEventListener('click', resetTimer);
  })();
