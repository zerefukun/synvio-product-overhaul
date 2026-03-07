/* OZ Variations BCW — Built by esbuild. Do not edit. Source: src/js/ */
(() => {
  // src/js/state.js
  var P = window.ozProduct || null;
  function findDefault(options, key) {
    if (!options || !options.length) return null;
    for (var i = 0; i < options.length; i++) {
      if (options[i]["default"] && options[i]["default"] !== "" && options[i]["default"] !== "0") {
        return key === "layers" ? parseInt(options[i][key], 10) : options[i][key];
      }
    }
    return key === "layers" ? parseInt(options[0][key], 10) : options[0][key];
  }
  var S = P ? {
    qty: 1,
    // PU layers selection (int or null if no PU options)
    puLayers: findDefault(P.puOptions, "layers"),
    // Primer label (string or null)
    primer: findDefault(P.primerOptions, "label"),
    // Colorfresh label (string or null)
    colorfresh: findDefault(P.colorfresh, "label"),
    // Toepassing label (string or null)
    toepassing: P.toepassing ? P.toepassing[0] : null,
    // Pakket label (string or null)
    pakket: findDefault(P.pakket, "label"),
    // Color mode: 'swatch' or 'ral_ncs'
    colorMode: P.ralNcsOnly ? "ral_ncs" : "swatch",
    // Custom RAL/NCS code entered by user
    customColor: "",
    // Is the bottom sheet open?
    sheetOpen: false,
    // Tool mode: 'none' (default), 'set' (complete set), 'individual' (pick items)
    toolMode: "none",
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
    addons: {}
  } : null;
  if (P && P.hasTools && P.toolConfig && P.toolConfig.extras) {
    P.toolConfig.extras.forEach(function(e) {
      S.extras[e.id] = { on: false, qty: 0, size: 0 };
    });
  }
  if (P && P.hasTools && P.toolConfig && P.toolConfig.tools) {
    P.toolConfig.tools.forEach(function(t) {
      S.tools[t.id] = { on: false, qty: 0, size: 0 };
    });
  }
  if (P && P.addonGroups && P.addonGroups.length) {
    P.addonGroups.forEach(function(g) {
      S.addons[g.key] = findDefault(g.options, "label");
    });
  }
  function updateState(patch) {
    var keys = Object.keys(patch);
    for (var i = 0; i < keys.length; i++) {
      S[keys[i]] = patch[keys[i]];
    }
  }
  function fmt(n) {
    n = parseFloat(n) || 0;
    return "\u20AC" + n.toFixed(2).replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }
  function getItemPrice(configItem, stateItem) {
    if (configItem.sizes && stateItem) return parseFloat(configItem.sizes[stateItem.size || 0].price) || 0;
    return parseFloat(configItem.price) || 0;
  }
  function calculatePrices(config, state) {
    var base = parseFloat(config.basePrice) || 0;
    var puPrice = 0;
    if (config.puOptions && state.puLayers !== null) {
      for (var i = 0; i < config.puOptions.length; i++) {
        if (config.puOptions[i].layers == state.puLayers) {
          puPrice = parseFloat(config.puOptions[i].price) || 0;
          break;
        }
      }
    }
    var primerPrice = 0;
    if (config.primerOptions && state.primer) {
      for (var i = 0; i < config.primerOptions.length; i++) {
        if (config.primerOptions[i].label === state.primer) {
          primerPrice = parseFloat(config.primerOptions[i].price) || 0;
          break;
        }
      }
    }
    var colorfreshPrice = 0;
    if (config.colorfresh && state.colorfresh) {
      for (var i = 0; i < config.colorfresh.length; i++) {
        if (config.colorfresh[i].label === state.colorfresh) {
          colorfreshPrice = parseFloat(config.colorfresh[i].price) || 0;
          break;
        }
      }
    }
    var toolsTotal = 0;
    var toolsLabel = "";
    var toolsDetails = [];
    if (config.hasTools && config.toolConfig) {
      var TC = config.toolConfig;
      if (state.toolMode === "set") {
        toolsTotal = parseFloat(TC.toolSet.price) || 0;
        toolsLabel = TC.toolSet.name;
        toolsDetails.push({ name: TC.toolSet.name, qty: 1, total: toolsTotal });
        TC.extras.forEach(function(e) {
          var st = state.extras[e.id];
          if (st && st.on && st.qty > 0) {
            var lineTotal = getItemPrice(e, st) * st.qty;
            toolsTotal += lineTotal;
            var sizeName = e.sizes && e.sizes[st.size || 0] ? e.sizes[st.size || 0].label : "";
            var itemName = e.name + (sizeName ? " " + sizeName : "");
            toolsDetails.push({ name: itemName, qty: st.qty, total: lineTotal });
          }
        });
        if (toolsDetails.length > 1) {
          toolsLabel = "Gereedschap";
        }
      } else if (state.toolMode === "individual") {
        TC.tools.forEach(function(t) {
          var st = state.tools[t.id];
          if (st && st.on && st.qty > 0) {
            var lineTotal = getItemPrice(t, st) * st.qty;
            toolsTotal += lineTotal;
            var sizeName = t.sizes && t.sizes[st.size || 0] ? t.sizes[st.size || 0].label : "";
            var itemName = t.name + (sizeName ? " " + sizeName : "");
            toolsDetails.push({ name: itemName, qty: st.qty, total: lineTotal });
          }
        });
        if (toolsDetails.length === 1) {
          toolsLabel = toolsDetails[0].name + (toolsDetails[0].qty > 1 ? " \xD7" + toolsDetails[0].qty : "");
        } else if (toolsDetails.length > 1) {
          toolsLabel = "Gereedschap";
        }
      }
    }
    var addonPrices = {};
    var addonTotal = 0;
    if (config.addonGroups && config.addonGroups.length && state.addons) {
      config.addonGroups.forEach(function(g) {
        var selected = state.addons[g.key];
        var price = 0;
        if (selected) {
          for (var i2 = 0; i2 < g.options.length; i2++) {
            if (g.options[i2].label === selected) {
              price = parseFloat(g.options[i2].price) || 0;
              break;
            }
          }
        }
        addonPrices[g.key] = price;
        addonTotal += price;
      });
    }
    var unitTotal = base + puPrice + primerPrice + colorfreshPrice + addonTotal;
    var total = unitTotal * state.qty + toolsTotal;
    return {
      base,
      puPrice,
      primerPrice,
      colorfreshPrice,
      addonPrices,
      toolsTotal,
      toolsLabel,
      toolsDetails,
      unitTotal,
      total
    };
  }
  function validateRal(code) {
    return /^(RAL\s?)?\d{4}$/i.test(code.trim());
  }
  function validateNcs(code) {
    return /^(NCS\s+)?S\s?\d{4}-[A-Z]\d{2}[A-Z]$/i.test(code.trim());
  }
  function hasAnyTool(toolMode, tools, toolConfig) {
    if (!toolConfig) return false;
    if (toolMode === "set") return true;
    if (toolMode === "individual") {
      return toolConfig.tools.some(function(t) {
        return tools[t.id] && tools[t.id].on;
      });
    }
    return false;
  }
  function clampToolQty(current, delta) {
    return Math.max(1, Math.min(99, current + delta));
  }
  function validateCartState(config, state) {
    if (state.colorMode === "ral_ncs") {
      if (!state.customColor) return "Vul een RAL of NCS kleurcode in.";
      if (!validateRal(state.customColor) && !validateNcs(state.customColor)) {
        return "Ongeldige RAL of NCS kleurcode.";
      }
    }
    if (config.hasTools && state.toolMode === "individual" && !hasAnyTool(state.toolMode, state.tools, config.toolConfig)) {
      return "Kies minimaal 1 gereedschap of kies een andere optie.";
    }
    if (config.hasTools && config.toolConfig) {
      var TC = config.toolConfig;
      if (state.toolMode === "set") {
        for (var i = 0; i < TC.extras.length; i++) {
          var ext = TC.extras[i];
          var est = state.extras[ext.id];
          if (est && est.on && ext.sizes && ext.sizes[est.size || 0] && ext.sizes[est.size || 0].inStock === false) {
            return ext.name + " in deze maat is uitverkocht. Kies een andere maat.";
          }
        }
      } else if (state.toolMode === "individual") {
        for (var i = 0; i < TC.tools.length; i++) {
          var tool = TC.tools[i];
          var tst = state.tools[tool.id];
          if (tst && tst.on && tool.sizes && tool.sizes[tst.size || 0] && tool.sizes[tst.size || 0].inStock === false) {
            return tool.name + " in deze maat is uitverkocht. Kies een andere maat.";
          }
        }
      }
    }
    return null;
  }
  function buildCartPayload(config, state) {
    var payload = {
      action: "oz_bcw_add_to_cart",
      nonce: config.nonce,
      product_id: config.productId,
      quantity: state.qty
    };
    if (state.puLayers !== null) payload.oz_pu_layers = state.puLayers;
    if (state.primer) payload.oz_primer = state.primer;
    if (state.colorfresh) payload.oz_colorfresh = state.colorfresh;
    if (state.toepassing) payload.oz_toepassing = state.toepassing;
    if (state.pakket) payload.oz_pakket = state.pakket;
    if (config.addonGroups && config.addonGroups.length && state.addons) {
      config.addonGroups.forEach(function(g) {
        if (state.addons[g.key]) {
          payload["oz_addon_" + g.key] = state.addons[g.key];
        }
      });
    }
    payload.oz_color_mode = state.colorMode;
    if (state.colorMode === "ral_ncs") {
      payload.oz_custom_color = state.customColor;
    }
    if (config.hasTools) {
      payload.oz_tool_mode = state.toolMode;
      if (state.toolMode === "set") {
        payload.oz_tool_set_id = config.toolConfig.toolSet.id;
        var extras = {};
        config.toolConfig.extras.forEach(function(e) {
          var st = state.extras[e.id];
          if (st && st.on && st.qty > 0) {
            var sizeData = e.sizes ? e.sizes[st.size || 0] : e;
            extras[e.id] = { qty: st.qty, wcId: sizeData.wcId, price: sizeData.price };
            if (e.sizes) extras[e.id].sizeLabel = sizeData.label;
            if (sizeData.wapoAddon) extras[e.id].wapoAddon = sizeData.wapoAddon;
          }
        });
        payload._extras = extras;
      } else if (state.toolMode === "individual") {
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
  var CHECKMARK_SVG = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2.5 6l2.5 2.5 4.5-5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  var NUDGE_ICON = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';

  // src/js/dom.js
  var DOM = {};
  function cacheDom() {
    DOM.page = document.getElementById("oz-product-page");
    DOM.mainImg = document.getElementById("mainImg");
    DOM.qtyInput = document.getElementById("qtyInput");
    DOM.addToCartBtn = document.getElementById("addToCartBtn");
    DOM.descContent = document.getElementById("descContent");
    DOM.readMoreBtn = document.getElementById("readMoreBtn");
    DOM.stickyBar = document.getElementById("stickyBar");
    DOM.stickyBtn = document.getElementById("stickyBtn");
    DOM.stickyPrice = document.getElementById("stickyPrice");
    DOM.sheetOverlay = document.getElementById("sheetOverlay");
    DOM.bottomSheet = document.getElementById("bottomSheet");
    DOM.sheetCtaBtn = document.getElementById("sheetCtaBtn");
    DOM.optionsWidget = document.getElementById("optionsWidget");
    DOM.slotDesktop = document.getElementById("optionsSlotDesktop");
    DOM.desktopHome = document.getElementById("optionsDesktopHome");
    DOM.slotSheet = document.getElementById("optionsSlotSheet");
    DOM.colorModeSlot = document.getElementById("colorModeSlot");
    DOM.colorLabel = document.getElementById("colorLabel");
    DOM.priceBaseLabel = document.getElementById("priceBaseLabel");
    DOM.priceBase = document.getElementById("priceBase");
    DOM.pricePuLine = document.getElementById("pricePuLine");
    DOM.pricePu = document.getElementById("pricePu");
    DOM.pricePuLabel = document.getElementById("pricePuLabel");
    DOM.pricePrimerLine = document.getElementById("pricePrimerLine");
    DOM.pricePrimer = document.getElementById("pricePrimer");
    DOM.pricePrimerLabel = document.getElementById("pricePrimerLabel");
    DOM.priceColorfreshLine = document.getElementById("priceColorfreshLine");
    DOM.priceColorfresh = document.getElementById("priceColorfresh");
    DOM.priceToolsLine = document.getElementById("priceToolsLine");
    DOM.priceToolsLabel = document.getElementById("priceToolsLabel");
    DOM.priceTools = document.getElementById("priceTools");
    DOM.upsellOverlay = document.getElementById("upsellOverlay");
    DOM.upsellAddBtn = document.getElementById("upsellAddBtn");
    DOM.upsellSkipBtn = document.getElementById("upsellSkipBtn");
    DOM.priceQtyLine = document.getElementById("priceQtyLine");
    DOM.priceQtyLabel = document.getElementById("priceQtyLabel");
    DOM.priceQty = document.getElementById("priceQty");
    DOM.priceTotal = document.getElementById("priceTotal");
  }
  function show(el) {
    if (el) el.style.display = "";
  }
  function hide(el) {
    if (el) el.style.display = "none";
  }

  // src/js/tools.js
  var _onSync = function() {
  };
  function setToolSyncCallback(fn) {
    _onSync = fn;
  }
  function buildToolRow(item, dataAttr, onToggle, onQtyDec, onQtyInc, onQtyEdit, onSizeChange) {
    var row = document.createElement("div");
    row.className = "oz-tool-item";
    row.dataset[dataAttr] = item.id;
    var noteHtml = item.note ? '<span style="font-size:11px;color:var(--oz-text-muted);margin-left:4px;">(' + item.note + ")</span>" : "";
    row.innerHTML = '<div class="oz-tool-check">' + CHECKMARK_SVG + '</div><span class="oz-tool-name">' + item.name + noteHtml + '</span><span class="oz-tool-price">' + fmt(item.price) + '</span><div class="oz-tool-qty"><div class="oz-tool-qty-wrap"><button class="oz-tool-qty-btn oz-tool-qty-dec">\u2212</button><input type="number" class="oz-tool-qty-input" value="1" min="1" max="99"><button class="oz-tool-qty-btn oz-tool-qty-inc">+</button></div></div>';
    if (item.sizes && item.sizes.length > 1) {
      var sizesDiv = document.createElement("div");
      sizesDiv.className = "oz-tool-sizes";
      item.sizes.forEach(function(sz, idx) {
        var btn = document.createElement("button");
        var isOos = sz.inStock === false;
        btn.className = "oz-tool-size-btn" + (idx === 0 && !isOos ? " selected" : "") + (isOos ? " oos" : "");
        btn.dataset.sizeIdx = idx;
        btn.textContent = sz.label + " " + fmt(sz.price) + (isOos ? " (uitverkocht)" : "");
        if (isOos) {
          btn.disabled = true;
        } else {
          btn.addEventListener("click", function(ev) {
            ev.stopPropagation();
            if (onSizeChange) onSizeChange(item.id, idx);
          });
        }
        sizesDiv.appendChild(btn);
      });
      row.appendChild(sizesDiv);
    }
    row.addEventListener("click", function() {
      onToggle(item.id);
    });
    row.querySelector(".oz-tool-qty-dec").addEventListener("click", function(ev) {
      ev.stopPropagation();
      onQtyDec(item.id);
    });
    row.querySelector(".oz-tool-qty-inc").addEventListener("click", function(ev) {
      ev.stopPropagation();
      onQtyInc(item.id);
    });
    var qtyInput = row.querySelector(".oz-tool-qty-input");
    qtyInput.addEventListener("click", function(ev) {
      ev.stopPropagation();
    });
    qtyInput.addEventListener("change", function() {
      onQtyEdit(item.id, this);
    });
    return row;
  }
  function buildToolSectionV2(sectionId) {
    if (!P.hasTools || !P.toolConfig) return;
    var TC = P.toolConfig;
    var section = document.getElementById(sectionId);
    if (!section || section.children.length > 0) return;
    var mode = document.createElement("div");
    mode.className = "oz-tool-mode";
    var btnNone = document.createElement("button");
    btnNone.className = "oz-tool-mode-btn";
    btnNone.dataset.mode = "none";
    btnNone.textContent = "Geen";
    btnNone.addEventListener("click", function() {
      setToolMode("none");
    });
    var btnSet = document.createElement("button");
    btnSet.className = "oz-tool-mode-btn";
    btnSet.dataset.mode = "set";
    var setName = TC.toolSet.name.replace("Gereedschapset ", "");
    btnSet.innerHTML = setName + ' <span class="oz-price-add">+' + fmt(TC.toolSet.price) + "</span>";
    btnSet.addEventListener("click", function() {
      setToolMode("set");
    });
    var btnInd = document.createElement("button");
    btnInd.className = "oz-tool-mode-btn";
    btnInd.dataset.mode = "individual";
    btnInd.textContent = "Zelf samenstellen";
    btnInd.addEventListener("click", function() {
      setToolMode("individual");
    });
    mode.appendChild(btnNone);
    mode.appendChild(btnSet);
    mode.appendChild(btnInd);
    section.appendChild(mode);
    var contents = document.createElement("div");
    contents.className = "oz-set-contents";
    contents.innerHTML = "<strong>Bevat:</strong> " + TC.toolSet.contents.join(", ");
    section.appendChild(contents);
    var extrasWrap = document.createElement("div");
    extrasWrap.className = "oz-tool-extras-wrap";
    var extrasLabel = document.createElement("div");
    extrasLabel.className = "oz-extras-label";
    extrasLabel.textContent = "Extra nodig?";
    extrasWrap.appendChild(extrasLabel);
    var extrasList = document.createElement("div");
    extrasList.className = "oz-tool-list";
    TC.extras.forEach(function(e) {
      extrasList.appendChild(buildToolRow(
        e,
        "extra",
        toggleExtra,
        function(id) {
          changeExtraQty(id, -1);
        },
        function(id) {
          changeExtraQty(id, 1);
        },
        onExtraQtyChange,
        changeExtraSize
      ));
    });
    extrasWrap.appendChild(extrasList);
    var nudge = document.createElement("div");
    nudge.className = "oz-smart-nudge";
    var nudgeM2 = (TC.nudgeQtyThreshold || 3) * (parseFloat(P.unitM2) || 5);
    nudge.innerHTML = NUDGE_ICON + "<span><strong>Groot project?</strong> PU rollers verharden na ~2 uur gebruik. Bij meer dan " + nudgeM2 + "m\xB2 raden wij extra rollers aan.</span>";
    extrasWrap.appendChild(nudge);
    section.appendChild(extrasWrap);
    var indList = document.createElement("div");
    indList.className = "oz-tool-list";
    indList.dataset.listType = "individual";
    TC.tools.forEach(function(t) {
      indList.appendChild(buildToolRow(
        t,
        "tool",
        toggleTool,
        function(id) {
          changeToolQty(id, -1);
        },
        function(id) {
          changeToolQty(id, 1);
        },
        onToolQtyChange,
        changeToolSize
      ));
    });
    section.appendChild(indList);
  }
  function syncItemRows(section, items, stateMap, attrName, isOnFn) {
    items.forEach(function(item) {
      var row = section.querySelector("[data-" + attrName + '="' + item.id + '"]');
      if (!row) return;
      var st = stateMap[item.id];
      var isOn = isOnFn(st);
      row.classList.toggle("selected", isOn);
      var qtyDiv = row.querySelector(".oz-tool-qty");
      var qtyInput = row.querySelector(".oz-tool-qty-input");
      if (qtyDiv) qtyDiv.classList.toggle("visible", isOn);
      if (qtyInput && isOn) qtyInput.value = st.qty;
      var sizesDiv = row.querySelector(".oz-tool-sizes");
      if (sizesDiv) {
        sizesDiv.classList.toggle("visible", isOn);
        if (isOn) {
          sizesDiv.querySelectorAll(".oz-tool-size-btn").forEach(function(btn) {
            var idx = parseInt(btn.dataset.sizeIdx);
            btn.classList.toggle("selected", idx === (st.size || 0) && !btn.classList.contains("oos"));
          });
        }
      }
      var priceSpan = row.querySelector(".oz-tool-price");
      if (priceSpan) priceSpan.textContent = fmt(getItemPrice(item, st));
    });
  }
  function syncToolSectionV2(sectionId, toolMode, tools, extras, qty) {
    if (!P.hasTools || !P.toolConfig) return;
    var TC = P.toolConfig;
    var section = document.getElementById(sectionId);
    if (!section) return;
    section.querySelectorAll(".oz-tool-mode-btn").forEach(function(btn) {
      btn.classList.toggle("selected", btn.dataset.mode === toolMode);
    });
    var contentsEl = section.querySelector(".oz-set-contents");
    if (contentsEl) contentsEl.classList.toggle("visible", toolMode === "set");
    var extrasWrap = section.querySelector(".oz-tool-extras-wrap");
    if (extrasWrap) extrasWrap.classList.toggle("visible", toolMode === "set");
    syncItemRows(section, TC.extras, extras, "extra", function(st) {
      return st && st.on;
    });
    var nudgeEl = section.querySelector(".oz-smart-nudge");
    if (nudgeEl) {
      var hasExtraRollers = extras["pu-roller"] && extras["pu-roller"].on;
      var m2PerUnit = parseFloat(P.unitM2) || 0;
      var totalM2 = qty * m2PerUnit;
      var m2Threshold = (TC.nudgeQtyThreshold || 3) * (m2PerUnit || 5);
      nudgeEl.classList.toggle("visible", toolMode === "set" && totalM2 >= m2Threshold && !hasExtraRollers);
    }
    var indList = section.querySelector('[data-list-type="individual"]');
    if (indList) indList.classList.toggle("hidden", toolMode !== "individual");
    syncItemRows(section, TC.tools, tools, "tool", function(st) {
      return toolMode === "individual" && st && st.on;
    });
  }
  function setToolMode(mode) {
    updateState({ toolMode: mode });
    _onSync();
  }
  function firstInStockSize(configItem) {
    if (!configItem.sizes) return 0;
    for (var i = 0; i < configItem.sizes.length; i++) {
      if (configItem.sizes[i].inStock !== false) return i;
    }
    return 0;
  }
  function toggleTool(id) {
    if (S.toolMode !== "individual") return;
    var prev = S.tools[id];
    var nowOn = !prev.on;
    var size = prev.size;
    if (nowOn) {
      var config = P.toolConfig.tools.find(function(t) {
        return t.id === id;
      });
      if (config && config.sizes && config.sizes[size] && config.sizes[size].inStock === false) {
        size = firstInStockSize(config);
      }
    }
    S.tools[id] = { on: nowOn, qty: nowOn ? 1 : 0, size };
    _onSync();
  }
  function changeToolQty(id, delta) {
    var prev = S.tools[id];
    S.tools[id] = { on: prev.on, qty: clampToolQty(prev.qty, delta), size: prev.size };
    _onSync();
  }
  function onToolQtyChange(id, inputEl) {
    var prev = S.tools[id];
    S.tools[id] = { on: prev.on, qty: clampToolQty(parseInt(inputEl.value) || 1, 0), size: prev.size };
    _onSync();
  }
  function changeToolSize(id, sizeIdx) {
    var prev = S.tools[id];
    S.tools[id] = { on: prev.on, qty: prev.qty, size: sizeIdx };
    _onSync();
  }
  function toggleExtra(id) {
    var prev = S.extras[id];
    var nowOn = !prev.on;
    var size = prev.size;
    if (nowOn) {
      var config = P.toolConfig.extras.find(function(e) {
        return e.id === id;
      });
      if (config && config.sizes && config.sizes[size] && config.sizes[size].inStock === false) {
        size = firstInStockSize(config);
      }
    }
    S.extras[id] = { on: nowOn, qty: nowOn ? 1 : 0, size };
    _onSync();
  }
  function changeExtraQty(id, delta) {
    var prev = S.extras[id];
    S.extras[id] = { on: prev.on, qty: clampToolQty(prev.qty, delta), size: prev.size };
    _onSync();
  }
  function onExtraQtyChange(id, inputEl) {
    var prev = S.extras[id];
    S.extras[id] = { on: prev.on, qty: clampToolQty(parseInt(inputEl.value) || 1, 0), size: prev.size };
    _onSync();
  }
  function changeExtraSize(id, sizeIdx) {
    var prev = S.extras[id];
    S.extras[id] = { on: prev.on, qty: prev.qty, size: sizeIdx };
    _onSync();
  }

  // src/js/product-page.js
  if (!P) {
  } else {
    let payloadToFormData = function(payload) {
      var data = new FormData();
      Object.keys(payload).forEach(function(key) {
        if (key === "_extras" || key === "_tools") return;
        data.append(key, payload[key]);
      });
      if (payload._extras) {
        Object.keys(payload._extras).forEach(function(id) {
          var item = payload._extras[id];
          Object.keys(item).forEach(function(field) {
            data.append("oz_extras[" + id + "][" + field + "]", item[field]);
          });
        });
      }
      if (payload._tools) {
        Object.keys(payload._tools).forEach(function(id) {
          var item = payload._tools[id];
          Object.keys(item).forEach(function(field) {
            data.append("oz_tools[" + id + "][" + field + "]", item[field]);
          });
        });
      }
      return data;
    }, syncUI = function() {
      var prices = calculatePrices(P, S);
      renderBreakdown(prices);
      if (DOM.stickyPrice) DOM.stickyPrice.textContent = fmt(prices.total);
      if (DOM.sheetTotal) DOM.sheetTotal.textContent = fmt(prices.total);
      renderOptionHighlights();
      renderColorMode();
      renderSelectedLabels();
      if (P.hasTools) {
        syncToolSectionV2("toolSection", S.toolMode, S.tools, S.extras, S.qty);
      }
    }, renderToolDetails = function(prices, anchor, lineClass) {
      if (!anchor) return;
      var parent = anchor.parentNode;
      var existing = parent.querySelectorAll(".oz-tool-detail-line");
      for (var i = 0; i < existing.length; i++) {
        if (existing[i].parentNode === parent) existing[i].remove();
      }
      if (prices.toolsTotal <= 0 || !prices.toolsDetails || prices.toolsDetails.length === 0) {
        hide(anchor);
        return;
      }
      if (prices.toolsDetails.length === 1) {
        show(anchor);
        var d = prices.toolsDetails[0];
        var label = d.name + (d.qty > 1 ? " \xD7" + d.qty : "");
        anchor.querySelector("span:first-child").textContent = label;
        anchor.querySelector("span:last-child").textContent = fmt(d.total);
        return;
      }
      hide(anchor);
      var insertBefore = anchor.nextSibling;
      for (var j = 0; j < prices.toolsDetails.length; j++) {
        var detail = prices.toolsDetails[j];
        var div = document.createElement("div");
        div.className = lineClass + " oz-tool-detail-line";
        var nameSpan = document.createElement("span");
        nameSpan.textContent = detail.name + (detail.qty > 1 ? " \xD7" + detail.qty : "");
        var priceSpan = document.createElement("span");
        priceSpan.textContent = fmt(detail.total);
        div.appendChild(nameSpan);
        div.appendChild(priceSpan);
        parent.insertBefore(div, insertBefore);
      }
    }, renderBreakdown = function(prices) {
      var isM2 = (parseFloat(P.unitM2) || 0) > 0;
      var perUnit = S.qty > 1 && isM2 ? " (per m\xB2)" : "";
      if (DOM.priceBaseLabel) DOM.priceBaseLabel.textContent = P.productName + perUnit;
      if (DOM.priceBase) DOM.priceBase.textContent = fmt(prices.base);
      var lines = [
        { line: DOM.pricePuLine, value: prices.puPrice, el: DOM.pricePu },
        { line: DOM.pricePrimerLine, value: prices.primerPrice, el: DOM.pricePrimer, labelEl: DOM.pricePrimerLabel, label: "Primer: " + S.primer },
        { line: DOM.priceColorfreshLine, value: prices.colorfreshPrice, el: DOM.priceColorfresh }
      ];
      for (var i = 0; i < lines.length; i++) {
        var item = lines[i];
        if (!item.line) continue;
        if (item.value > 0) {
          show(item.line);
          if (item.el) item.el.textContent = fmt(item.value);
          if (item.labelEl && item.label) item.labelEl.textContent = item.label;
        } else {
          hide(item.line);
        }
      }
      if (P.addonGroups && prices.addonPrices) {
        P.addonGroups.forEach(function(g) {
          var lineEl = document.getElementById("priceAddon_" + g.key + "Line");
          var priceEl = document.getElementById("priceAddon_" + g.key);
          if (lineEl && priceEl) {
            if (prices.addonPrices[g.key] > 0) {
              show(lineEl);
              priceEl.textContent = fmt(prices.addonPrices[g.key]);
            } else {
              hide(lineEl);
            }
          }
        });
      }
      renderToolDetails(prices, DOM.priceToolsLine, "oz-price-line");
      var m2PerUnit = parseFloat(P.unitM2) || 0;
      var qtyLabel = isM2 ? S.qty * m2PerUnit + " m\xB2 (" + S.qty + "\xD7)" : S.qty + " stuks";
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
      if (DOM.priceTotal) DOM.priceTotal.textContent = fmt(prices.total);
    }, renderOptionHighlights = function() {
      var highlights = [
        { attr: "data-pu", value: S.puLayers, parse: function(v) {
          return parseInt(v, 10);
        } },
        { attr: "data-primer", value: S.primer },
        { attr: "data-colorfresh", value: S.colorfresh },
        { attr: "data-toepassing", value: S.toepassing },
        { attr: "data-pakket", value: S.pakket }
      ];
      for (var h = 0; h < highlights.length; h++) {
        var spec = highlights[h];
        var btns = document.querySelectorAll("[" + spec.attr + "]");
        for (var i = 0; i < btns.length; i++) {
          var val = spec.parse ? spec.parse(btns[i].getAttribute(spec.attr)) : btns[i].getAttribute(spec.attr);
          btns[i].classList.toggle("selected", val === spec.value);
        }
      }
    }, renderSelectedLabels = function() {
      var tpLabel = document.getElementById("selectedToepassingLabel");
      if (tpLabel && S.toepassing) {
        tpLabel.textContent = S.toepassing;
      }
      var colorLabel = document.getElementById("selectedColorLabel");
      if (colorLabel) {
        if (S.colorMode === "ral_ncs" && S.customColor) {
          colorLabel.textContent = S.customColor;
        } else if (P.currentColor) {
          colorLabel.textContent = P.currentColor;
        }
      }
    }, renderColorMode = function() {
      if (!P.hasRalNcs || !DOM.colorModeSlot) return;
      if (!DOM.colorModeSlot.querySelector(".oz-color-mode-btn, .oz-custom-color-wrap")) {
        buildColorModeUI();
      }
      var modeBtns = DOM.colorModeSlot.querySelectorAll(".oz-color-mode-btn");
      for (var i = 0; i < modeBtns.length; i++) {
        modeBtns[i].classList.toggle("active", modeBtns[i].getAttribute("data-mode") === S.colorMode);
      }
      var customWrap = DOM.colorModeSlot.querySelector(".oz-custom-color-wrap");
      if (customWrap) {
        customWrap.classList.toggle("visible", S.colorMode === "ral_ncs");
      }
      var swatches = document.querySelector(".oz-color-swatches");
      if (swatches) {
        swatches.classList.toggle("hidden", S.colorMode === "ral_ncs");
      }
    }, buildColorModeUI = function() {
      var html = "";
      if (!P.ralNcsOnly) {
        html += '<div class="oz-color-mode-buttons">';
        html += '<button class="oz-color-mode-btn active" data-mode="swatch">Standaard kleuren</button>';
        html += '<button class="oz-color-mode-btn" data-mode="ral_ncs">RAL / NCS</button>';
        html += "</div>";
      }
      html += '<div class="oz-custom-color-wrap' + (P.ralNcsOnly ? " visible" : "") + '">';
      html += '<input type="text" class="oz-custom-color-input" id="customColorInput" ';
      html += 'placeholder="Bijv. RAL 7016 of S 1050-Y90R">';
      html += '<div class="oz-custom-color-hint" id="customColorHint"></div>';
      html += "</div>";
      DOM.colorModeSlot.innerHTML = html;
    }, openUpsell = function() {
      updateState({ upsellOpen: true });
      document.body.style.overflow = "hidden";
      renderUpsellModal();
    }, closeUpsell = function() {
      updateState({ upsellOpen: false });
      document.body.style.overflow = "";
      renderUpsellModal();
    }, upsellAddSet = function() {
      updateState({ toolMode: "set" });
      closeUpsell();
      syncUI();
      submitCart();
    }, upsellSkip = function() {
      closeUpsell();
      submitCart();
    }, renderUpsellModal = function() {
      if (!DOM.upsellOverlay) return;
      if (S.upsellOpen) {
        DOM.upsellOverlay.style.display = "flex";
        requestAnimationFrame(function() {
          DOM.upsellOverlay.classList.add("open");
        });
      } else {
        DOM.upsellOverlay.classList.remove("open");
        setTimeout(function() {
          DOM.upsellOverlay.style.display = "none";
        }, 250);
      }
    }, handleClick = function(e) {
      var target = e.target;
      var btn = target.closest("[data-pu], [data-primer], [data-colorfresh], [data-toepassing], [data-pakket]");
      var addonBtn = target.closest("[data-addon-key]");
      var thumb = target.closest(".oz-gallery-thumb");
      var infoBtn = target.closest(".oz-info-btn");
      var qtyBtn = target.closest("[data-qty-delta]");
      var modeBtn = target.closest(".oz-color-mode-btn");
      if (addonBtn && !btn) {
        e.preventDefault();
        var key = addonBtn.getAttribute("data-addon-key");
        var value = addonBtn.getAttribute("data-addon-value");
        if (key && value) {
          S.addons[key] = value;
          var group = addonBtn.closest(".oz-option-group");
          if (group) {
            var groupBtns = group.querySelectorAll("[data-addon-key]");
            for (var gi = 0; gi < groupBtns.length; gi++) {
              groupBtns[gi].classList.toggle("selected", groupBtns[gi].getAttribute("data-addon-value") === value);
            }
          }
          syncUI();
        }
        return;
      }
      if (btn) {
        e.preventDefault();
        if (btn.hasAttribute("data-pu")) {
          updateState({ puLayers: parseInt(btn.getAttribute("data-pu"), 10) });
        } else if (btn.hasAttribute("data-primer")) {
          updateState({ primer: btn.getAttribute("data-primer") });
        } else if (btn.hasAttribute("data-colorfresh")) {
          updateState({ colorfresh: btn.getAttribute("data-colorfresh") });
        } else if (btn.hasAttribute("data-toepassing")) {
          updateState({ toepassing: btn.getAttribute("data-toepassing") });
        } else if (btn.hasAttribute("data-pakket")) {
          updateState({ pakket: btn.getAttribute("data-pakket") });
        }
        syncUI();
        return;
      }
      if (thumb) {
        e.preventDefault();
        switchGalleryImage(thumb);
        return;
      }
      if (infoBtn) {
        e.preventDefault();
        toggleInfoTooltip(infoBtn);
        return;
      }
      if (qtyBtn) {
        e.preventDefault();
        changeQty(parseInt(qtyBtn.getAttribute("data-qty-delta"), 10));
        return;
      }
      if (modeBtn) {
        e.preventDefault();
        updateState({ colorMode: modeBtn.getAttribute("data-mode") });
        syncUI();
        return;
      }
      var swatch = target.closest(".oz-color-swatch");
      if (swatch) {
        saveToolState();
        return;
      }
      if (target === DOM.readMoreBtn || target.closest("#readMoreBtn")) {
        e.preventDefault();
        toggleReadMore();
        return;
      }
      if (target === DOM.addToCartBtn || target.closest("#addToCartBtn") || target === DOM.sheetCtaBtn || target.closest("#sheetCtaBtn")) {
        e.preventDefault();
        addToCart();
        return;
      }
      if (target === DOM.upsellAddBtn || target.closest("#upsellAddBtn")) {
        e.preventDefault();
        upsellAddSet();
        return;
      }
      if (target === DOM.upsellSkipBtn || target.closest("#upsellSkipBtn")) {
        e.preventDefault();
        upsellSkip();
        return;
      }
      if (target === DOM.upsellOverlay) {
        closeUpsell();
        return;
      }
      if (target === DOM.stickyBtn || target.closest("#stickyBtn")) {
        e.preventDefault();
        if (window.innerWidth >= 900) {
          var cartBtn = DOM.addToCartBtn;
          if (cartBtn) cartBtn.scrollIntoView({ behavior: "smooth", block: "center" });
        } else {
          openSheet();
        }
        return;
      }
      if (target === DOM.sheetOverlay) {
        closeSheet();
        return;
      }
    }, switchGalleryImage = function(thumb) {
      var newSrc = thumb.getAttribute("data-full-src");
      if (!newSrc || !DOM.mainImg) return;
      var allThumbs = document.querySelectorAll(".oz-gallery-thumb");
      for (var i = 0; i < allThumbs.length; i++) {
        allThumbs[i].classList.remove("selected");
      }
      thumb.classList.add("selected");
      DOM.mainImg.classList.add("oz-fade");
      setTimeout(function() {
        DOM.mainImg.src = newSrc;
        DOM.mainImg.onload = function() {
          DOM.mainImg.classList.remove("oz-fade");
        };
      }, 200);
    }, toggleInfoTooltip = function(btn) {
      var targetId = btn.getAttribute("data-info-target");
      if (!targetId) return;
      var tooltip = document.getElementById(targetId);
      if (!tooltip) return;
      tooltip.classList.toggle("visible");
    }, changeQty = function(delta) {
      var newQty = clampToolQty(S.qty, delta);
      updateState({ qty: newQty });
      if (DOM.qtyInput) DOM.qtyInput.value = newQty;
      syncUI();
    }, handleQtyInput = function() {
      var val = parseInt(DOM.qtyInput.value, 10);
      if (isNaN(val) || val < 1) val = 1;
      if (val > 99) val = 99;
      updateState({ qty: val });
      DOM.qtyInput.value = val;
      syncUI();
    }, toggleReadMore = function() {
      if (!DOM.descContent || !DOM.readMoreBtn) return;
      var expanded = DOM.descContent.classList.toggle("expanded");
      DOM.readMoreBtn.textContent = expanded ? "Lees minder" : "Lees meer";
    }, autoFormatColor = function(raw) {
      if (/^RAL\s/i.test(raw)) return "RAL " + raw.replace(/^RAL\s*/i, "").trim();
      if (/^(NCS\s*)?S\s/i.test(raw)) return raw.toUpperCase().replace(/^NCS\s*/, "NCS ");
      if (/^\d{4}$/.test(raw)) return "RAL " + raw;
      var ncsMatch = raw.match(/^(\d{4})-?([A-Za-z]\d{2}[A-Za-z])$/);
      if (ncsMatch) return "NCS S " + ncsMatch[1] + "-" + ncsMatch[2].toUpperCase();
      var ncsWithS = raw.match(/^[Ss]\s?(\d{4})-?([A-Za-z]\d{2}[A-Za-z])$/);
      if (ncsWithS) return "NCS S " + ncsWithS[1] + "-" + ncsWithS[2].toUpperCase();
      return raw;
    }, handleCustomColorInput = function(e) {
      var input = e.target;
      var value = input.value.trim();
      var hint = document.getElementById("customColorHint");
      if (e.type === "blur" && value) {
        var formatted = autoFormatColor(value);
        if (formatted !== value) {
          input.value = formatted;
          value = formatted;
        }
      }
      updateState({ customColor: value });
      if (!value) {
        input.classList.remove("valid", "invalid");
        if (hint) {
          hint.textContent = "";
          hint.className = "oz-custom-color-hint";
        }
        syncUI();
        return;
      }
      var checkValue = autoFormatColor(value);
      var isRal = validateRal(checkValue);
      var isNcs = validateNcs(checkValue);
      if (isRal || isNcs) {
        input.classList.remove("invalid");
        input.classList.add("valid");
        if (hint) {
          hint.textContent = isRal ? "RAL kleurcode herkend" : "NCS kleurcode herkend";
          hint.className = "oz-custom-color-hint success";
        }
      } else {
        input.classList.remove("valid");
        input.classList.add("invalid");
        if (hint) {
          hint.textContent = "Voer een geldige RAL (4 cijfers) of NCS code in";
          hint.className = "oz-custom-color-hint error";
        }
      }
      syncUI();
    }, saveToolState = function() {
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
          timestamp: Date.now()
        };
        sessionStorage.setItem(TOOL_STATE_KEY, JSON.stringify(data));
      } catch (e) {
      }
    }, restoreToolState = function() {
      try {
        var raw = sessionStorage.getItem(TOOL_STATE_KEY);
        if (!raw) return;
        sessionStorage.removeItem(TOOL_STATE_KEY);
        var data = JSON.parse(raw);
        if (Date.now() - data.timestamp > 6e4) return;
        if (data.toolMode) updateState({ toolMode: data.toolMode });
        if (data.qty > 1) updateState({ qty: data.qty });
        if (data.puLayers !== void 0 && data.puLayers !== null) updateState({ puLayers: data.puLayers });
        if (data.primer) updateState({ primer: data.primer });
        if (data.colorfresh) updateState({ colorfresh: data.colorfresh });
        if (data.toepassing) updateState({ toepassing: data.toepassing });
        if (data.pakket) updateState({ pakket: data.pakket });
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
        if (DOM.qtyInput && data.qty > 1) DOM.qtyInput.value = data.qty;
      } catch (e) {
      }
    }, openSheet = function() {
      if (!DOM.bottomSheet || !DOM.sheetOverlay || !DOM.optionsWidget) return;
      _sheetScrollY = window.scrollY;
      DOM.slotSheet.appendChild(DOM.optionsWidget);
      if (DOM.desktopHome) DOM.desktopHome.style.minHeight = "0";
      updateState({ sheetOpen: true });
      DOM.sheetOverlay.classList.add("open");
      DOM.bottomSheet.classList.add("open");
      document.body.style.overflow = "hidden";
    }, closeSheet = function() {
      if (!DOM.bottomSheet || !DOM.sheetOverlay || !DOM.optionsWidget) return;
      updateState({ sheetOpen: false });
      DOM.sheetOverlay.classList.remove("open");
      DOM.bottomSheet.classList.remove("open");
      document.body.style.overflow = "";
      if (DOM.desktopHome) DOM.desktopHome.appendChild(DOM.optionsWidget);
      window.scrollTo(0, _sheetScrollY);
    }, addToCart = function() {
      var error = validateCartState(P, S);
      if (error) {
        if (error.indexOf("gereedschap") !== -1) {
          var toolGroup = document.querySelector('[data-option="tools"]');
          if (toolGroup) toolGroup.scrollIntoView({ behavior: "smooth", block: "center" });
        }
        shakeButton();
        showCartError(error);
        return;
      }
      if (P.hasTools && S.toolMode === "none") {
        openUpsell();
        return;
      }
      submitCart();
    }, submitCart = function() {
      var payload = buildCartPayload(P, S);
      var data = payloadToFormData(payload);
      setCartLoading(true);
      fetch(P.ajaxUrl, {
        method: "POST",
        body: data,
        credentials: "same-origin"
      }).then(function(res) {
        return res.json();
      }).then(function(json) {
        setCartLoading(false);
        if (json.success) {
          if (S.sheetOpen) closeSheet();
          showCartSuccess(json.data);
          document.dispatchEvent(new CustomEvent("oz-added-to-cart"));
          if (typeof jQuery !== "undefined") {
            jQuery(document.body).trigger("wc_fragment_refresh");
          }
        } else {
          showCartError(json.data || "Er ging iets mis.");
        }
      }).catch(function() {
        setCartLoading(false);
        showCartError("Verbindingsfout. Probeer opnieuw.");
      });
    }, setCartLoading = function(loading) {
      var btn = DOM.addToCartBtn;
      if (!btn) return;
      btn.disabled = loading;
      btn.textContent = loading ? "Bezig..." : "In winkelmand";
      if (DOM.sheetCtaBtn) DOM.sheetCtaBtn.disabled = loading;
    }, shakeButton = function() {
      var btn = DOM.addToCartBtn;
      if (!btn) return;
      btn.style.animation = "none";
      btn.offsetHeight;
      btn.style.animation = "oz-shake 0.4s ease";
      setTimeout(function() {
        btn.style.animation = "";
      }, 500);
    }, showCartError = function(msg) {
      removeCartMsg();
      var el = document.createElement("div");
      el.className = "oz-cart-msg oz-cart-error";
      el.textContent = msg;
      var cartRow = document.querySelector(".oz-cart-row");
      if (cartRow && cartRow.parentNode) {
        cartRow.parentNode.insertBefore(el, cartRow.nextSibling);
      }
      setTimeout(removeCartMsg, 4e3);
    }, showCartSuccess = function(data) {
      removeCartMsg();
      if (DOM.addToCartBtn) {
        DOM.addToCartBtn.style.background = "#38A169";
        DOM.addToCartBtn.textContent = "Toegevoegd!";
        setTimeout(function() {
          DOM.addToCartBtn.style.background = "";
          DOM.addToCartBtn.textContent = "In winkelmand";
        }, 1500);
      }
      if (data && data.cart_count) {
        var counters = document.querySelectorAll(".cart-count, .cart_count, .header-cart-count");
        for (var i = 0; i < counters.length; i++) {
          counters[i].textContent = data.cart_count;
        }
      }
    }, removeCartMsg = function() {
      var existing = document.querySelector(".oz-cart-msg");
      if (existing) existing.remove();
    }, setupStickyBar = function() {
      if (!DOM.stickyBar) return;
      var target = DOM.addToCartBtn || DOM.optionsWidget;
      if (!target) return;
      var observer = new IntersectionObserver(function(entries) {
        var isVisible = entries[0].isIntersecting;
        DOM.stickyBar.classList.toggle("visible", !isVisible);
      }, { threshold: 0 });
      observer.observe(target);
    }, init = function() {
      cacheDom();
      setToolSyncCallback(syncUI);
      buildToolSectionV2("toolSection");
      restoreToolState();
      syncUI();
      document.addEventListener("click", handleClick);
      if (DOM.qtyInput) {
        DOM.qtyInput.addEventListener("change", handleQtyInput);
        DOM.qtyInput.addEventListener("input", handleQtyInput);
      }
      document.addEventListener("input", function(e) {
        if (e.target.id === "customColorInput") {
          handleCustomColorInput(e);
        }
      });
      document.addEventListener("focusout", function(e) {
        if (e.target.id === "customColorInput") {
          handleCustomColorInput(e);
        }
      });
      if (DOM.sheetOverlay) {
        DOM.sheetOverlay.addEventListener("click", closeSheet);
        window.addEventListener("beforeunload", function() {
          if (S.sheetOpen) closeSheet();
        });
        window.addEventListener("pageshow", function(e) {
          if (e.persisted && S.sheetOpen) closeSheet();
        });
        DOM.bottomSheet.addEventListener("click", function(e) {
          var link = e.target.closest("a[href]");
          if (link && S.sheetOpen) {
            e.preventDefault();
            closeSheet();
            window.location.href = link.href;
          }
        });
      }
      document.addEventListener("keydown", function(e) {
        if (e.key === "Escape") {
          if (S.upsellOpen) {
            closeUpsell();
            return;
          }
          if (S.sheetOpen) {
            closeSheet();
            return;
          }
        }
      });
      setupStickyBar();
      if (DOM.descContent && DOM.readMoreBtn) {
        if (DOM.descContent.scrollHeight <= 120) {
          DOM.readMoreBtn.style.display = "none";
          DOM.descContent.classList.add("expanded");
        }
      }
    };
    TOOL_STATE_KEY = "oz_bcw_tool_state";
    _sheetScrollY = 0;
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", init);
    } else {
      init();
    }
  }
  var TOOL_STATE_KEY;
  var _sheetScrollY;
})();
//# sourceMappingURL=oz-product-page.js.map
