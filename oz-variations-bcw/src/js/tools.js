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
export function buildToolSectionV2(sectionId) {
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

  // Smart nudge: show when total m² >= 15 AND no extra PU rollers added.
  // Uses unitM2 to calculate actual coverage. Non-m² products (unitM2=0) never show nudge.
  var nudgeEl = section.querySelector('.oz-smart-nudge');
  if (nudgeEl) {
    var hasExtraRollers = extras['pu-roller'] && extras['pu-roller'].on;
    var m2PerUnit = parseFloat(P.unitM2) || 0;
    var totalM2 = qty * m2PerUnit;
    var m2Threshold = (TC.nudgeQtyThreshold || 3) * (m2PerUnit || 5); // default 15m²
    nudgeEl.classList.toggle('visible', toolMode === 'set' && totalM2 >= m2Threshold && !hasExtraRollers);
  }

  // Show/hide individual list — only in 'individual' mode
  var indList = section.querySelector('[data-list-type="individual"]');
  if (indList) indList.classList.toggle('hidden', toolMode !== 'individual');

  // Sync individual tool rows — active when in individual mode AND toggled on
  syncItemRows(section, TC.tools, tools, 'tool', function(st) {
    return toolMode === 'individual' && st && st.on;
  });
}


/* ═══ TOOL EVENT HANDLERS ═══════════════════════════════════ */

/** Switch tool mode: 'none', 'set', or 'individual' */
function setToolMode(mode) {
  updateState({ toolMode: mode });
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
