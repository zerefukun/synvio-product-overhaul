/**
 * Tool Section — DOM Builders & Event Handlers
 *
 * Handles the "Gereedschap" section:
 * - Mode toggle (Geen / Kant & Klaar / Zelf samenstellen)
 * - Set contents display
 * - Extra items on top of set
 * - Individual tool selection
 * - Smart nudge for PU rollers
 *
 * Uses a sync callback pattern to avoid circular imports.
 * Call setToolSyncCallback(syncUI) from the entry point.
 *
 * @package OZ_Variations_BCW
 * @since 2.0.0
 */

import { P, S, updateState, fmt, getItemPrice, clampToolQty, CHECKMARK_SVG, NUDGE_ICON } from './state.js';
import * as analytics from './analytics.js';


/* ═══ SYNC CALLBACK ═════════════════════════════════════════ */

// Callback to trigger full UI sync after tool state changes.
// Set by the entry point to avoid circular dependency with syncUI.
var _onSync = function() {};

/**
 * Register the syncUI callback. Called once from init().
 * @param {Function} fn  The syncUI function from the entry point
 */
export function setToolSyncCallback(fn) { _onSync = fn; }


/* ═══ TOOL DOM BUILDERS ═════════════════════════════════════ */

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
      var isOos = sz.inStock === false; // explicit false = out of stock
      btn.className = 'oz-tool-size-btn' + (idx === 0 && !isOos ? ' selected' : '') + (isOos ? ' oos' : '');
      btn.dataset.sizeIdx = idx;
      btn.textContent = sz.label + ' ' + fmt(sz.price) + (isOos ? ' (uitverkocht)' : '');
      if (isOos) {
        btn.disabled = true;
      } else {
        btn.addEventListener('click', function(ev) {
          ev.stopPropagation();
          if (onSizeChange) onSizeChange(item.id, idx);
        });
      }
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
export function buildToolSectionV2(sectionId, rebuild) {
  if (!P.hasTools || !P.toolConfig) return;
  var TC = P.toolConfig;
  var section = document.getElementById(sectionId);
  if (!section) return;
  // Clear and rebuild when called with rebuild=true (formula toggle)
  if (rebuild) { section.innerHTML = ''; }
  if (section.children.length > 0) return;

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

  section.appendChild(extrasWrap);

  // Smart nudge — lives outside extrasWrap so it can show in both set and individual mode
  // Fixed 15m² threshold — rollers harden in ~2 hours, big projects need extras
  var nudge = document.createElement('div');
  nudge.className = 'oz-smart-nudge';
  nudge.innerHTML = NUDGE_ICON +
    '<span><strong>Groot project?</strong> PU rollers verharden na ~2 uur gebruik. Bij meer dan ' +
    '15m\u00b2 raden wij extra rollers aan.</span>';
  section.appendChild(nudge);

  // Hint shown when in individual mode with 0 tools selected
  var hint = document.createElement('div');
  hint.className = 'oz-tool-hint';
  hint.textContent = 'Selecteer minimaal 1 gereedschap';
  section.appendChild(hint);

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

  // A/B/C variant C: build a <select> dropdown that mirrors the 3 mode
  // buttons. The buttons themselves stay in the DOM but are hidden by CSS
  // (html.oz-ab-tools-c .oz-tool-mode { display:none }), so we can call
  // their click() handlers when the dropdown changes — same setToolMode
  // actions fire, no new logic.
  if (document.documentElement.classList.contains('oz-ab-tools-c')) {
    buildToolModeDropdown(section);
  }
}

/**
 * Variant C: build a "Kies je ruimte" dropdown that combines the primer
 * and PU Toplaag sections into one room-based choice (Betonstunter
 * pattern). Hides the underlying primer + pu sections via CSS for
 * variant C and triggers the corresponding primer + PU buttons on
 * change. No new pricing logic — just a different UI control over the
 * same underlying state.
 *
 * Mapping (room → primer, pu_layers):
 *   Geen beschermlaag        → Geen primer, 0 PU
 *   Slaapkamer/Hal/Gang/Zolder/Muur (laag verkeer, droog) → Primer, 1 PU
 *   Keuken/Badkamer/Toilet/Vloer/Trap/Meubel (hoog verkeer of vocht) → Primer, 2 PU
 *
 * Idempotent — safe to call multiple times.
 */
export function buildRuimteDropdown() {
  if (!document.documentElement.classList.contains('oz-ab-tools-c')) return;
  if (document.querySelector('.oz-ruimte-dropdown')) return;

  var primerSection = document.querySelector('.oz-option-group[data-option="primer"]');
  var puSection = document.querySelector('.oz-option-group[data-option="pu"]');
  if (!primerSection || !puSection) return;

  var rooms = [
    { label: 'Geen beschermlaag',                primer: 'Geen',   pu: '0' },
    { label: 'Muur (1 laag PU + primer) +€8',    primer: 'Primer', pu: '1' },
    { label: 'Hal / Gang (1 laag PU + primer) +€8',  primer: 'Primer', pu: '1' },
    { label: 'Zolder (1 laag PU + primer) +€8',  primer: 'Primer', pu: '1' },
    { label: 'Slaapkamer (1 laag PU + primer) +€8',  primer: 'Primer', pu: '1' },
    { label: 'Keuken (2 lagen PU + primer) +€16',    primer: 'Primer', pu: '2' },
    { label: 'Badkamer (2 lagen PU + primer) +€16',  primer: 'Primer', pu: '2' },
    { label: 'Toilet / WC (2 lagen PU + primer) +€16', primer: 'Primer', pu: '2' },
    { label: 'Vloer (2 lagen PU + primer) +€16',     primer: 'Primer', pu: '2' },
    { label: 'Meubel (2 lagen PU + primer) +€16',    primer: 'Primer', pu: '2' },
    { label: 'Trap (2 lagen PU + primer) +€16',      primer: 'Primer', pu: '2' },
  ];

  var wrap = document.createElement('div');
  wrap.className = 'oz-option-group oz-ruimte-dropdown';
  wrap.setAttribute('data-option', 'ruimte');
  wrap.innerHTML =
    '<div class="oz-option-header">Kies je ruimte ' +
    '<span class="oz-required-star" style="color:#e53e3e">*</span> ' +
    '<button class="oz-info-btn" type="button" data-info-target="ruimte-info">i</button></div>' +
    '<div class="oz-info-tooltip" id="ruimte-info">' +
    'Kies de ruimte waar je beton ciré aanbrengt. Op basis van slijtage en vocht ' +
    'selecteren we automatisch het juiste aantal PU-lagen + primer.' +
    '</div>';

  // No separate <label> — the .oz-option-header above + placeholder option
  // in the <select> are enough. Two stacked labels was visual noise.
  var select = document.createElement('select');
  select.className = 'oz-ruimte-select';
  // Empty placeholder so user must actively pick — preselected is risky
  // because we don't know which room they're targeting.
  var placeholder = document.createElement('option');
  placeholder.value = '';
  placeholder.textContent = 'Maak je keuze...';
  placeholder.disabled = true;
  placeholder.selected = true;
  select.appendChild(placeholder);

  rooms.forEach(function(r, i) {
    var opt = document.createElement('option');
    opt.value = String(i);
    opt.textContent = r.label;
    opt.dataset.primer = r.primer;
    opt.dataset.pu = r.pu;
    select.appendChild(opt);
  });

  select.addEventListener('change', function() {
    var idx = parseInt(select.value, 10);
    if (isNaN(idx)) return;
    var r = rooms[idx];
    if (!r) return;
    var primerBtn = primerSection.querySelector('[data-primer="' + r.primer + '"]');
    var puBtn = puSection.querySelector('[data-pu="' + r.pu + '"]');
    if (primerBtn) primerBtn.click();
    if (puBtn) puBtn.click();
  });
  wrap.appendChild(select);

  // Insert before primer section so the user sees the room dropdown first.
  primerSection.parentNode.insertBefore(wrap, primerSection);
}

/**
 * Build a <select> dropdown for the tool mode that mirrors the 3 inline
 * mode buttons. Inserts it before the existing .oz-tool-mode (which is
 * hidden by CSS for variant C). Changing the dropdown clicks the matching
 * button so all existing logic (price, validation, syncing) keeps working.
 */
function buildToolModeDropdown(section) {
  if (!P.toolConfig || !P.toolConfig.toolSet) return;
  if (section.querySelector('.oz-tool-mode-dropdown')) return; // idempotent
  var modeBtns = section.querySelectorAll('.oz-tool-mode-btn');
  if (!modeBtns.length) return;

  var setName = P.toolConfig.toolSet.name.replace('Gereedschapset ', '');
  var setPrice = fmt(P.toolConfig.toolSet.price);

  var wrap = document.createElement('div');
  wrap.className = 'oz-tool-mode-dropdown';
  // The Gereedschap section already has its own .oz-option-header, no need
  // for a second label above the select.

  var select = document.createElement('select');
  select.className = 'oz-tool-mode-select';
  var options = [
    { mode: 'none',       label: 'Geen gereedschap' },
    { mode: 'set',        label: setName + ' (+' + setPrice + ')' },
    { mode: 'individual', label: 'Zelf samenstellen' }
  ];
  options.forEach(function(o) {
    var opt = document.createElement('option');
    opt.value = o.mode;
    opt.textContent = o.label;
    select.appendChild(opt);
  });
  select.addEventListener('change', function() {
    var btn = section.querySelector('.oz-tool-mode-btn[data-mode="' + select.value + '"]');
    if (btn) btn.click();
  });
  wrap.appendChild(select);

  var modeContainer = section.querySelector('.oz-tool-mode');
  if (modeContainer && modeContainer.parentNode) {
    modeContainer.parentNode.insertBefore(wrap, modeContainer);
  } else {
    section.insertBefore(wrap, section.firstChild);
  }
}

/**
 * Sync a list of tool/extra item rows to match current state.
 * Generic — works for both extras and individual tools.
 *
 * @param {Element}  section   Parent section element
 * @param {Array}    items     Config items (TC.extras or TC.tools)
 * @param {Object}   stateMap  State map (S.extras or S.tools)
 * @param {string}   attrName  Data attribute name: 'extra' or 'tool'
 * @param {Function} isOnFn    Returns boolean: fn(stateItem) — whether item is active
 */
function syncItemRows(section, items, stateMap, attrName, isOnFn) {
  items.forEach(function(item) {
    var row = section.querySelector('[data-' + attrName + '="' + item.id + '"]');
    if (!row) return;
    var st = stateMap[item.id];
    var isOn = isOnFn(st);
    row.classList.toggle('selected', isOn);

    var qtyDiv = row.querySelector('.oz-tool-qty');
    var qtyInput = row.querySelector('.oz-tool-qty-input');
    if (qtyDiv) qtyDiv.classList.toggle('visible', isOn);
    if (qtyInput && isOn) qtyInput.value = st.qty;

    // Size selector sync — skip OOS buttons when syncing selection
    var sizesDiv = row.querySelector('.oz-tool-sizes');
    if (sizesDiv) {
      sizesDiv.classList.toggle('visible', isOn);
      if (isOn) {
        sizesDiv.querySelectorAll('.oz-tool-size-btn').forEach(function(btn) {
          var idx = parseInt(btn.dataset.sizeIdx);
          btn.classList.toggle('selected', idx === (st.size || 0) && !btn.classList.contains('oos'));
        });
      }
    }

    var priceSpan = row.querySelector('.oz-tool-price');
    if (priceSpan) priceSpan.textContent = fmt(getItemPrice(item, st));
  });
}

/**
 * Sync tool section state to DOM — handles set contents, extras, individual, nudge.
 * Called from syncUI() on every state change.
 */
export function syncToolSectionV2(sectionId, toolMode, tools, extras, qty) {
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

  // Sync extra item rows — active when toggled on
  syncItemRows(section, TC.extras, extras, 'extra', function(st) {
    return st && st.on;
  });

  // Smart nudge: show when total m² >= threshold AND user has a PU roller but may need more.
  // Works in both set mode (roller in extras) and individual mode (roller in tools).
  // Non-m² products (unitM2=0) never show nudge.
  var nudgeEl = section.querySelector('.oz-smart-nudge');
  if (nudgeEl) {
    var m2PerUnit = parseFloat(P.unitM2) || 0;
    var totalM2 = qty * m2PerUnit;
    var m2Threshold = 15; // fixed 15m² — rollers harden in ~2 hours
    var showNudge = false;
    if (totalM2 >= m2Threshold) {
      if (toolMode === 'set') {
        // In set mode: show nudge if user hasn't added extra rollers on top of the set
        var hasExtraRollers = extras['pu-roller'] && extras['pu-roller'].on;
        showNudge = !hasExtraRollers;
      } else if (toolMode === 'individual') {
        // In individual mode: show nudge if user selected a PU roller (remind them they may need more)
        var hasIndividualRoller = tools['pu-roller'] && tools['pu-roller'].on;
        showNudge = hasIndividualRoller;
      }
    }
    // Variant C: extras wrap is hidden via CSS when set mode is active.
    // Without the extras UI, the nudge has no actionable outcome. It
    // would just sit there permanently. Suppress it instead.
    if (toolMode === 'set' && document.documentElement.classList.contains('oz-ab-tools-c')) {
      showNudge = false;
    }
    nudgeEl.classList.toggle('visible', showNudge);
  }

  // Show/hide individual list — only in 'individual' mode
  var indList = section.querySelector('[data-list-type="individual"]');
  if (indList) indList.classList.toggle('hidden', toolMode !== 'individual');

  // Hint: visible when in individual mode with 0 tools selected
  var hintEl = section.querySelector('.oz-tool-hint');
  if (hintEl) {
    var anyToolOn = false;
    if (toolMode === 'individual') {
      for (var tk in tools) {
        if (tools[tk] && tools[tk].on) { anyToolOn = true; break; }
      }
    }
    hintEl.classList.toggle('visible', toolMode === 'individual' && !anyToolOn);
  }

  // Sync individual tool rows — active when in individual mode AND toggled on
  syncItemRows(section, TC.tools, tools, 'tool', function(st) {
    return toolMode === 'individual' && st && st.on;
  });
}


/* ═══ TOOL EVENT HANDLERS ═══════════════════════════════════ */

/** Switch tool mode: 'none', 'set', or 'individual' */
function setToolMode(mode) {
  updateState({ toolMode: mode });
  analytics.trackToolModeChanged(mode);
  _onSync();
}

/**
 * Find the first in-stock size index for a tool/extra item.
 * Falls back to 0 if no sizes or all sizes lack stock data.
 */
function firstInStockSize(configItem) {
  if (!configItem.sizes) return 0;
  for (var i = 0; i < configItem.sizes.length; i++) {
    if (configItem.sizes[i].inStock !== false) return i;
  }
  return 0; // all OOS — fallback to first
}

/** Toggle an individual tool on/off — auto-selects first in-stock size */
function toggleTool(id) {
  if (S.toolMode !== 'individual') return;
  var prev = S.tools[id];
  var nowOn = !prev.on;
  // When turning on, select first in-stock size if current is OOS
  var size = prev.size;
  if (nowOn) {
    var config = P.toolConfig.tools.find(function(t) { return t.id === id; });
    if (config && config.sizes && config.sizes[size] && config.sizes[size].inStock === false) {
      size = firstInStockSize(config);
    }
  }
  S.tools[id] = { on: nowOn, qty: nowOn ? 1 : 0, size: size };
  analytics.trackToolToggled(id, nowOn);
  _onSync();
}

/** Change qty of an individual tool */
function changeToolQty(id, delta) {
  var prev = S.tools[id];
  S.tools[id] = { on: prev.on, qty: clampToolQty(prev.qty, delta), size: prev.size };
  _onSync();
}

/** Manual qty input for a tool */
function onToolQtyChange(id, inputEl) {
  var prev = S.tools[id];
  S.tools[id] = { on: prev.on, qty: clampToolQty(parseInt(inputEl.value) || 1, 0), size: prev.size };
  _onSync();
}

/** Change size of an individual tool */
function changeToolSize(id, sizeIdx) {
  var prev = S.tools[id];
  S.tools[id] = { on: prev.on, qty: prev.qty, size: sizeIdx };
  _onSync();
}

/** Toggle an extra item on/off (on top of set) — auto-selects first in-stock size */
function toggleExtra(id) {
  var prev = S.extras[id];
  var nowOn = !prev.on;
  var size = prev.size;
  if (nowOn) {
    var config = P.toolConfig.extras.find(function(e) { return e.id === id; });
    if (config && config.sizes && config.sizes[size] && config.sizes[size].inStock === false) {
      size = firstInStockSize(config);
    }
  }
  S.extras[id] = { on: nowOn, qty: nowOn ? 1 : 0, size: size };
  _onSync();
}

/** Change qty of an extra item */
function changeExtraQty(id, delta) {
  var prev = S.extras[id];
  S.extras[id] = { on: prev.on, qty: clampToolQty(prev.qty, delta), size: prev.size };
  _onSync();
}

/** Manual qty input for an extra */
function onExtraQtyChange(id, inputEl) {
  var prev = S.extras[id];
  S.extras[id] = { on: prev.on, qty: clampToolQty(parseInt(inputEl.value) || 1, 0), size: prev.size };
  _onSync();
}

/** Change size of an extra item */
function changeExtraSize(id, sizeIdx) {
  var prev = S.extras[id];
  S.extras[id] = { on: prev.on, qty: prev.qty, size: sizeIdx };
  _onSync();
}
