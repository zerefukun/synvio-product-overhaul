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
    // Toepassing label — null by default, user must choose (no pre-selection)
    toepassing: null,
    // Pakket label (string or null)
    pakket: findDefault(P.pakket, "label"),
    // Color mode: 'swatch' or 'ral_ncs'
    colorMode: P.ralNcsOnly ? "ral_ncs" : "swatch",
    // Custom RAL/NCS code entered by user
    customColor: "",
    // Selected color name for static/shared color swatches (e.g. Betonlook Verf)
    // Empty string = no color selected yet
    selectedColor: "",
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
    // Formula mode: 'self' (current line) or 'target' (toggled line)
    // null when no mode_toggle exists on this product
    formulaMode: P.modeToggle ? "self" : null,
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
  var _originalP = P && P.modeToggle ? {
    productId: P.productId,
    productName: P.productName,
    basePrice: P.basePrice,
    productLine: P.productLine,
    unit: P.unit,
    unitM2: P.unitM2,
    isBase: P.isBase,
    puOptions: P.puOptions,
    primerOptions: P.primerOptions,
    toepassing: P.toepassing,
    optionOrder: P.optionOrder,
    toolConfig: P.toolConfig,
    hasTools: P.hasTools
  } : null;
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
  function fmtDelta(n) {
    n = parseFloat(n) || 0;
    return n < 0 ? "-" + fmt(Math.abs(n)) : fmt(n);
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
    var clean = code.trim().replace(/\s+/g, " ");
    return /^(RAL\s?)?\d{4}$/i.test(clean);
  }
  function validateNcs(code) {
    var clean = code.trim().replace(/\s+/g, "").toUpperCase();
    clean = clean.replace(/^NCS/, "").replace(/^S/, "");
    return /^\d{4}-?([A-Z](\d{2}[A-Z])?)$/.test(clean);
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
    if (config.isBase && state.colorMode !== "ral_ncs") {
      return "Kies eerst een kleur om te bestellen.";
    }
    if (state.colorMode === "ral_ncs") {
      if (!state.customColor) return "Vul een kleurcode in.";
    }
    if (config.hasStaticColors && state.colorMode === "swatch" && !state.selectedColor) {
      return "Kies een kleur.";
    }
    if (state.formulaMode === "target" && state.colorMode === "swatch" && !state.selectedColor && !config.currentColor) {
      return "Kies eerst een kleur.";
    }
    if (config.toepassing && config.toepassing.length && !state.toepassing) {
      return "Kies een toepassing (Vloer of Overige).";
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
    var isToggled = state.formulaMode === "target" && config.modeToggle;
    var productId = isToggled ? config.modeToggle.targetProductId : config.productId;
    var payload = {
      action: "oz_bcw_add_to_cart",
      nonce: config.nonce,
      product_id: productId,
      quantity: state.qty
    };
    if (isToggled) {
      payload.oz_line = config.modeToggle.targetLine;
      var color = state.selectedColor || config.currentColor || "";
      if (color) payload.oz_selected_color = color;
    }
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
    if (state.colorMode === "swatch" && state.selectedColor) {
      payload.oz_selected_color = state.selectedColor;
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
  var NUDGE_ICON = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L1 21h22L12 2zm0 4l7.53 13H4.47L12 6zm-1 5v4h2v-4h-2zm0 6v2h2v-2h-2z"/></svg>';

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
    DOM.displayBasePrice = document.getElementById("displayBasePrice");
    DOM.stickyDColor = document.getElementById("stickyDColor");
    DOM.stickyDOptions = document.getElementById("stickyDOptions");
    DOM.stickyDQty = document.getElementById("stickyDQty");
    DOM.stickyDBtn = document.getElementById("stickyDBtn");
    DOM.stickyPriceMobile = document.getElementById("stickyPriceMobile");
    DOM.stickyThumb = document.getElementById("stickyThumb");
    DOM.productTitle = document.querySelector(".oz-product-title");
    DOM.selectedColorLabel = document.getElementById("selectedColorLabel");
    DOM.stickyColorName = document.getElementById("stickyColorName");
    DOM.stickyColorWrap = document.getElementById("stickyColorWrap");
    DOM.stickyProductName = document.querySelector(".oz-sticky-product-name");
    DOM.stickyDTitle = document.querySelector(".oz-sticky-d-title");
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

  // src/js/analytics.js
  var _lastBeacon = "";
  var _lastBeaconTime = 0;
  function beacon(eventName, payload) {
    if (!P || !P.ajaxUrl || !P.analyticsNonce) return;
    var key = eventName + "|" + (payload.oz_color || payload.oz_option_value || payload.oz_tool_mode || "");
    var now = Date.now();
    if (key === _lastBeacon && now - _lastBeaconTime < 1500) return;
    _lastBeacon = key;
    _lastBeaconTime = now;
    var fd = new FormData();
    fd.append("action", "oz_track_event");
    fd.append("nonce", P.analyticsNonce);
    fd.append("event_name", eventName);
    fd.append("event_data", JSON.stringify(payload));
    fd.append("source", "product");
    navigator.sendBeacon(P.ajaxUrl, fd);
  }
  function push(eventName, params) {
    window.dataLayer = window.dataLayer || [];
    var payload = Object.assign({
      event: eventName,
      oz_product_id: P.productId,
      oz_product_name: P.productName,
      oz_product_line: P.productLine || "none"
    }, params || {});
    window.dataLayer.push(payload);
    beacon(eventName, payload);
  }
  function trackColorSelected(colorName) {
    push("oz_color_selected", {
      oz_color: colorName,
      oz_color_mode: "swatch"
    });
  }
  function trackCustomColor(code, mode) {
    push("oz_color_selected", {
      oz_color: code,
      oz_color_mode: mode
      // 'ral_ncs'
    });
  }
  function trackColorModeChanged(mode) {
    push("oz_color_mode_changed", {
      oz_color_mode: mode
    });
  }
  function trackOptionSelected(optionType, value) {
    push("oz_option_selected", {
      oz_option_type: optionType,
      // 'pu', 'primer', 'colorfresh', 'toepassing', 'pakket'
      oz_option_value: String(value)
    });
  }
  function trackToolModeChanged(mode) {
    push("oz_tool_mode_changed", {
      oz_tool_mode: mode
      // 'none', 'set', 'individual'
    });
  }
  function trackToolToggled(toolId, isOn) {
    push("oz_tool_toggled", {
      oz_tool_id: toolId,
      oz_tool_action: isOn ? "selected" : "deselected"
    });
  }
  function trackQtyChanged(qty) {
    push("oz_qty_changed", {
      oz_qty: qty
    });
  }
  function trackAddToCart(prices) {
    push("oz_add_to_cart", {
      oz_total_price: prices.total,
      oz_qty: S.qty,
      oz_pu_layers: S.puLayers,
      oz_primer: S.primer,
      oz_tool_mode: S.toolMode,
      oz_color: S.colorMode === "ral_ncs" ? S.customColor : P.currentColor
    });
  }
  function trackAddToCartError(errorMsg) {
    push("oz_add_to_cart_error", {
      oz_error: errorMsg
    });
  }
  function trackUpsellAccepted() {
    push("oz_upsell_accepted", {});
  }
  function trackUpsellSkipped() {
    push("oz_upsell_skipped", {});
  }
  function trackSheetOpened() {
    push("oz_sheet_opened", {});
  }
  function trackGalleryImage(imageIndex) {
    push("oz_gallery_image", {
      oz_image_index: imageIndex
    });
  }
  function trackFormulaToggled(fromMode, toMode) {
    push("oz_formula_toggled", {
      oz_from_mode: fromMode,
      oz_to_mode: toMode
    });
  }
  function trackAddonSelected(addonKey, addonValue) {
    push("oz_option_selected", {
      oz_option_type: "addon_" + addonKey,
      oz_option_value: addonValue
    });
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
  function buildToolSectionV2(sectionId, rebuild) {
    if (!P.hasTools || !P.toolConfig) return;
    var TC = P.toolConfig;
    var section = document.getElementById(sectionId);
    if (!section) return;
    if (rebuild) {
      section.innerHTML = "";
    }
    if (section.children.length > 0) return;
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
    section.appendChild(extrasWrap);
    var nudge = document.createElement("div");
    nudge.className = "oz-smart-nudge";
    nudge.innerHTML = NUDGE_ICON + "<span><strong>Groot project?</strong> PU rollers verharden na ~2 uur gebruik. Bij meer dan 15m\xB2 raden wij extra rollers aan.</span>";
    section.appendChild(nudge);
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
      var m2PerUnit = parseFloat(P.unitM2) || 0;
      var totalM2 = qty * m2PerUnit;
      var m2Threshold = 15;
      var showNudge = false;
      if (totalM2 >= m2Threshold) {
        if (toolMode === "set") {
          var hasExtraRollers = extras["pu-roller"] && extras["pu-roller"].on;
          showNudge = !hasExtraRollers;
        } else if (toolMode === "individual") {
          var hasIndividualRoller = tools["pu-roller"] && tools["pu-roller"].on;
          showNudge = hasIndividualRoller;
        }
      }
      nudgeEl.classList.toggle("visible", showNudge);
    }
    var indList = section.querySelector('[data-list-type="individual"]');
    if (indList) indList.classList.toggle("hidden", toolMode !== "individual");
    syncItemRows(section, TC.tools, tools, "tool", function(st) {
      return toolMode === "individual" && st && st.on;
    });
  }
  function setToolMode(mode) {
    updateState({ toolMode: mode });
    trackToolModeChanged(mode);
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
    trackToolToggled(id, nowOn);
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

  // src/js/navigation.js
  var _decodeEl = null;
  function decodeEntities(str) {
    if (!str || str.indexOf("&") === -1) return str;
    if (!_decodeEl) _decodeEl = document.createElement("textarea");
    _decodeEl.innerHTML = str;
    return _decodeEl.value;
  }
  var _pushTimer = null;
  var _hasPushed = false;
  var _imgTimer = null;
  var _initialProductId = null;
  var _initialIsBase = false;
  var _onAfterNavigate = null;
  function initNavigation(onAfterNavigate) {
    _initialProductId = parseInt(P.productId, 10) || P.productId;
    _initialIsBase = P.isBase;
    _onAfterNavigate = onAfterNavigate || null;
    history.replaceState({ productId: P.productId }, "", location.href);
    window.addEventListener("popstate", function(e) {
      if (!e.state) return;
      if (e.state.formulaMode && window._ozToggleFormula) {
        window._ozToggleFormula(e.state.formulaMode);
        return;
      }
      if (e.state.productId) {
        applyVariant(e.state.productId, true);
      }
    });
  }
  function navigateToVariant(productId) {
    return applyVariant(productId, false);
  }
  function applyVariant(productId, isPopstate) {
    productId = parseInt(productId, 10) || productId;
    var v = P.variants[productId];
    if (!v) return false;
    var isInitialProduct = productId === _initialProductId;
    P.productId = productId;
    P.currentColor = v.color;
    P.basePrice = parseFloat(v.price) || P.basePrice;
    P.productName = decodeEntities(v.title);
    P.isBase = isInitialProduct && _initialIsBase;
    swapMainImage(v.fullImage);
    rebuildGalleryThumbs(v);
    var strippedTitle = stripColor(decodeEntities(v.title), v.color);
    if (DOM.productTitle) DOM.productTitle.textContent = strippedTitle;
    swapDescription(v.description);
    toggleStickyLinkByTab("info", !!v.description);
    updateSaleDisplay(v);
    if (DOM.selectedColorLabel) {
      DOM.selectedColorLabel.textContent = v.color || "Kies eerst uw kleur";
    }
    if (DOM.colorLabel) {
      DOM.colorLabel.textContent = v.color;
      DOM.colorLabel.style.display = v.color ? "" : "none";
    }
    if (DOM.stickyDColor) DOM.stickyDColor.textContent = v.color;
    if (DOM.stickyColorName) {
      DOM.stickyColorName.textContent = v.color;
      if (DOM.stickyColorWrap) DOM.stickyColorWrap.style.display = v.color ? "" : "none";
    }
    if (v.image && DOM.stickyThumb) DOM.stickyThumb.src = v.image;
    if (DOM.stickyProductName) DOM.stickyProductName.textContent = strippedTitle;
    if (DOM.stickyDTitle) DOM.stickyDTitle.textContent = strippedTitle;
    var swatches = document.querySelectorAll(".oz-color-swatch");
    for (var i = 0; i < swatches.length; i++) {
      var spid = parseInt(swatches[i].getAttribute("data-product-id"), 10);
      swatches[i].classList.toggle("selected", spid === productId);
    }
    updateSeoMeta(v.url, decodeEntities(v.title));
    if (!isPopstate) {
      if (!_hasPushed) {
        history.pushState({ productId }, "", v.url);
        _hasPushed = true;
      } else {
        history.replaceState({ productId }, "", v.url);
      }
      clearTimeout(_pushTimer);
      _pushTimer = setTimeout(function() {
        _hasPushed = false;
      }, 300);
    }
    var editLink = document.querySelector("#wp-admin-bar-edit a");
    if (editLink) {
      editLink.href = editLink.href.replace(/post=\d+/, "post=" + productId);
    }
    if (_onAfterNavigate) _onAfterNavigate();
    return true;
  }
  function swapDescription(html) {
    if (!DOM.descContent) return;
    var panel = document.getElementById("tabInfo");
    var tab = document.querySelector('.oz-tab[data-tab="info"]');
    if (!html) {
      if (panel) panel.style.display = "none";
      if (tab) {
        var wasActive = tab.classList.contains("active");
        tab.style.display = "none";
        if (wasActive) {
          tab.classList.remove("active");
          if (panel) panel.classList.remove("active");
          var nextTab = document.querySelector('.oz-tab:not([style*="display: none"])');
          if (nextTab) {
            nextTab.classList.add("active");
            var nextPanel = document.querySelector('.oz-tab-panel[data-tab="' + nextTab.getAttribute("data-tab") + '"]');
            if (nextPanel) nextPanel.classList.add("active");
          }
        }
      }
      return;
    }
    if (panel) panel.style.display = "";
    if (tab) tab.style.display = "";
    DOM.descContent.innerHTML = html;
    DOM.descContent.classList.remove("expanded");
    if (DOM.readMoreBtn) {
      if (DOM.descContent.scrollHeight <= 120) {
        DOM.readMoreBtn.style.display = "none";
        DOM.descContent.classList.add("expanded");
      } else {
        DOM.readMoreBtn.style.display = "";
        DOM.readMoreBtn.textContent = "Lees meer";
      }
    }
  }
  function updateSaleDisplay(v) {
    var priceWrap = document.querySelector(".oz-product-base-price");
    if (!priceWrap) return;
    var del = priceWrap.querySelector("del");
    if (v.onSale && v.regularPrice && v.regularPrice !== v.price) {
      if (!del) {
        del = document.createElement("del");
        priceWrap.insertBefore(del, priceWrap.firstChild);
      }
      del.textContent = fmt(v.regularPrice);
      del.style.display = "";
    } else {
      if (del) del.style.display = "none";
    }
  }
  function toggleStickyLinkByTab(tabId, show2) {
    var link = document.querySelector('.oz-sticky-d-link[data-tab="' + tabId + '"]');
    if (link) link.style.display = show2 ? "" : "none";
  }
  function swapMainImage(fullImageUrl) {
    if (!fullImageUrl || !DOM.mainImg) return;
    clearTimeout(_imgTimer);
    DOM.mainImg.classList.add("oz-fade");
    _imgTimer = setTimeout(function() {
      DOM.mainImg.onload = function() {
        DOM.mainImg.classList.remove("oz-fade");
        var bc = document.querySelector(".oz-breadcrumb-overlay");
        if (bc && typeof window.adaptBreadcrumbColor === "function") {
          window.adaptBreadcrumbColor(DOM.mainImg, bc);
        }
      };
      DOM.mainImg.onerror = function() {
        DOM.mainImg.classList.remove("oz-fade");
      };
      DOM.mainImg.src = fullImageUrl;
    }, 200);
  }
  function createThumb(thumbSrc, fullSrc, index, selected) {
    var div = document.createElement("div");
    div.className = "oz-gallery-thumb" + (selected ? " selected" : "");
    div.setAttribute("data-full-src", fullSrc);
    div.setAttribute("data-index", index);
    var img = document.createElement("img");
    img.src = thumbSrc;
    img.alt = "";
    div.appendChild(img);
    return div;
  }
  function rebuildGalleryThumbs(v) {
    var container = document.querySelector(".oz-gallery-thumbs");
    if (!container) return;
    container.style.display = "";
    container.innerHTML = "";
    if (v.image && v.fullImage) {
      container.appendChild(createThumb(v.image, v.fullImage, 0, true));
    }
    var gallery = v.gallery || [];
    for (var i = 0; i < gallery.length; i++) {
      container.appendChild(createThumb(gallery[i].thumb, gallery[i].full, i + 1, false));
    }
    if (container.children.length <= 1) {
      container.style.display = "none";
    }
  }
  function updateSeoMeta(url, title) {
    document.title = title + " - " + (P.siteTitle || "Beton Cire Webshop");
    var canonical = document.querySelector('link[rel="canonical"]');
    if (canonical) canonical.setAttribute("href", url);
    var ogUrl = document.querySelector('meta[property="og:url"]');
    if (ogUrl) ogUrl.setAttribute("content", url);
    var ogTitle = document.querySelector('meta[property="og:title"]');
    if (ogTitle) ogTitle.setAttribute("content", title);
  }
  function stripColor(fullTitle, color) {
    if (!color) return fullTitle;
    var stripped = fullTitle.replace(/\s*\([^)]+\)\s*$/, "");
    if (stripped !== fullTitle) return stripped.trim();
    var escaped = color.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    var re = new RegExp("\\s+" + escaped + "\\s*$", "i");
    return fullTitle.replace(re, "").trim();
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
      if (DOM.stickyPriceMobile) DOM.stickyPriceMobile.textContent = fmt(prices.total);
      if (DOM.displayBasePrice) DOM.displayBasePrice.textContent = fmt(prices.unitTotal);
      renderStickySummary();
      if (DOM.sheetTotal) DOM.sheetTotal.textContent = fmt(prices.total);
      renderOptionHighlights();
      renderColorMode();
      renderSelectedLabels();
      if (P.hasTools) {
        syncToolSectionV2("toolSection", S.toolMode, S.tools, S.extras, S.qty);
      }
      var error = validateCartState(P, S);
      if (DOM.addToCartBtn) {
        DOM.addToCartBtn.classList.toggle("oz-disabled", !!error);
      }
      var stickyLabel = "In winkelmand";
      if (P.isBase && error) {
        stickyLabel = "Kies kleur";
      } else if (P.toepassing && P.toepassing.length && !S.toepassing) {
        stickyLabel = "Kies toepassing";
      }
      if (DOM.stickyBtn) DOM.stickyBtn.textContent = stickyLabel;
      if (DOM.stickyDBtn) DOM.stickyDBtn.textContent = stickyLabel;
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
      var perUnit = S.qty > 1 && isM2 ? " (per " + P.unit + ")" : "";
      if (DOM.priceBaseLabel) DOM.priceBaseLabel.textContent = P.productName + perUnit;
      if (DOM.priceBase) DOM.priceBase.textContent = fmt(prices.base);
      var lines = [
        {
          line: DOM.pricePuLine,
          value: prices.puPrice,
          el: DOM.pricePu,
          labelEl: DOM.pricePuLabel,
          label: S.puLayers === 0 ? "Geen PU" : "PU Toplaag"
        },
        { line: DOM.pricePrimerLine, value: prices.primerPrice, el: DOM.pricePrimer, labelEl: DOM.pricePrimerLabel, label: "Primer: " + S.primer },
        { line: DOM.priceColorfreshLine, value: prices.colorfreshPrice, el: DOM.priceColorfresh }
      ];
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
      if (P.addonGroups && prices.addonPrices) {
        P.addonGroups.forEach(function(g) {
          var lineEl = document.getElementById("priceAddon_" + g.key + "Line");
          var priceEl = document.getElementById("priceAddon_" + g.key);
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
      if (tpLabel) {
        tpLabel.textContent = S.toepassing || "";
      }
      var tpStar = document.getElementById("toepassingRequired");
      if (tpStar) {
        tpStar.style.display = S.toepassing ? "none" : "";
      }
      var colorLabel = document.getElementById("selectedColorLabel");
      if (colorLabel) {
        if (S.colorMode === "ral_ncs" && S.customColor) {
          colorLabel.textContent = S.customColor;
        } else if (S.selectedColor) {
          colorLabel.textContent = S.selectedColor;
        } else if (P.currentColor) {
          colorLabel.textContent = P.currentColor;
        }
      }
      if (DOM.colorLabel) {
        if (S.colorMode === "ral_ncs" && S.customColor) {
          DOM.colorLabel.textContent = S.customColor;
          DOM.colorLabel.style.display = "";
        } else if (S.selectedColor) {
          DOM.colorLabel.textContent = S.selectedColor;
          DOM.colorLabel.style.display = "";
        } else if (!P.currentColor && P.hasStaticColors) {
          DOM.colorLabel.style.display = "none";
        }
      }
    }, renderStickySummary = function() {
      var sep = '<span class="oz-sep">&middot;</span>';
      if (DOM.stickyDColor) {
        if (S.colorMode === "ral_ncs" && S.customColor) {
          DOM.stickyDColor.textContent = S.customColor;
        } else if (S.selectedColor) {
          DOM.stickyDColor.textContent = S.selectedColor;
        } else {
          DOM.stickyDColor.textContent = P.currentColor || "";
        }
      }
      var stickyColorName = document.getElementById("stickyColorName");
      var stickyColorWrap = document.getElementById("stickyColorWrap");
      if (stickyColorName) {
        var mobileColor = "";
        if (S.colorMode === "ral_ncs" && S.customColor) {
          mobileColor = S.customColor;
        } else if (S.selectedColor) {
          mobileColor = S.selectedColor;
        } else {
          mobileColor = P.currentColor || "";
        }
        stickyColorName.textContent = mobileColor;
        if (stickyColorWrap) {
          stickyColorWrap.style.display = mobileColor ? "" : "none";
        }
      }
      if (DOM.stickyDOptions) {
        var parts = [];
        if (S.puLayers !== null && S.puLayers !== void 0) {
          if (S.puLayers === 0) {
            parts.push("Geen PU");
          } else {
            parts.push(S.puLayers + " PU " + (S.puLayers === 1 ? "laag" : "lagen"));
          }
        }
        if (S.primer) {
          parts.push("Primer: " + S.primer);
        }
        if (S.colorfresh && S.colorfresh !== "Zonder Colorfresh") {
          parts.push(S.colorfresh);
        }
        if (S.toepassing) {
          parts.push(S.toepassing);
        }
        if (S.pakket) {
          parts.push(S.pakket);
        }
        if (S.toolMode === "set") {
          parts.push("Gereedschapset");
        } else if (S.toolMode === "individual") {
          parts.push("Gereedschap");
        }
        DOM.stickyDOptions.innerHTML = parts.join(sep);
      }
      if (DOM.stickyDQty) {
        DOM.stickyDQty.textContent = S.qty + "\xD7";
        DOM.stickyDQty.setAttribute("data-qty", S.qty);
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
      html += '<div class="oz-color-input-row">';
      html += '<input type="text" class="oz-custom-color-input" id="customColorInput" ';
      html += 'placeholder="Bijv. RAL 7016 of NCS S 2005-Y20R">';
      html += "</div>";
      html += '<div class="oz-custom-color-hint" id="customColorHint"></div>';
      html += '<div class="oz-custom-color-info">';
      html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';
      html += "<span>Wij mengen elke kleurcode op maat. RAL en NCS aanbevolen.</span>";
      html += "</div>";
      html += "</div>";
      DOM.colorModeSlot.innerHTML = html;
    }, toggleFormula = function(mode) {
      if (!P.modeToggle || S.formulaMode === mode) return;
      var prevMode = S.formulaMode;
      var MT = P.modeToggle;
      var fromLabel = prevMode === "self" ? MT.labelSelf : MT.labelTarget;
      var toLabel = mode === "self" ? MT.labelSelf : MT.labelTarget;
      trackFormulaToggled(fromLabel, toLabel);
      updateState({ formulaMode: mode });
      if (mode === "target") {
        _preToggleUrl = location.href;
        _preToggleProductId = P.productId;
        _preToggleIsBase = P.isBase;
        _preToggleBasePrice = P.basePrice;
        P.productId = MT.targetProductId;
        P.productName = MT.targetProductName;
        P.basePrice = MT.targetBasePrice;
        P.productLine = MT.targetLine;
        P.unit = MT.targetUnit;
        P.unitM2 = MT.targetUnitM2;
        P.puOptions = MT.targetPuOptions;
        P.primerOptions = MT.targetPrimerOptions;
        P.toepassing = MT.targetToepassing;
        P.optionOrder = MT.targetOptionOrder;
        P.hasTools = MT.targetHasTools;
        P.toolConfig = MT.targetToolConfig;
        P.isBase = false;
        updateState({
          // PU layers: keep current selection (same 0-3 range on both sides)
          puLayers: S.puLayers,
          // Primer: ZM has no customer-facing primer, clear it
          primer: null,
          // Toepassing: no default — user must choose (Vloer or Overige)
          toepassing: null,
          // Color: preserve from current swatch
          selectedColor: S.selectedColor || P.currentColor || "",
          // Tools: if K&K set was selected, auto-switch to ZM set
          toolMode: S.toolMode === "set" ? "set" : S.toolMode
          // Qty: preserved automatically (not in this patch)
        });
        swapContent(MT);
        history.pushState(
          { productId: MT.targetProductId, formulaMode: "target" },
          "",
          MT.targetUrl
        );
      } else {
        if (_originalP) {
          var keys = Object.keys(_originalP);
          for (var i = 0; i < keys.length; i++) {
            P[keys[i]] = _originalP[keys[i]];
          }
        }
        P.productId = _preToggleProductId;
        P.isBase = _preToggleIsBase;
        P.basePrice = _preToggleBasePrice;
        updateState({
          // PU layers: keep current selection
          puLayers: S.puLayers,
          // Primer: restore K&K default (was hidden in ZM)
          primer: findDefault(P.primerOptions, "label"),
          // Toepassing: K&K doesn't have it, clear
          toepassing: null,
          // Color: clear ZM static selection (K&K uses variant navigation)
          selectedColor: "",
          // Tools: if ZM set was selected, auto-switch to K&K set
          toolMode: S.toolMode === "set" ? "set" : S.toolMode
        });
        restoreContent();
        history.pushState(
          { productId: P.productId, formulaMode: "self" },
          "",
          _preToggleUrl
        );
      }
      var isZM = mode === "target";
      var primerGroup = document.querySelector('[data-option="primer"]');
      if (primerGroup) primerGroup.style.display = isZM ? "none" : "";
      var subtitle = document.getElementById("formulaSubtitle");
      if (subtitle) subtitle.style.display = isZM ? "" : "none";
      rebuildToggleOptions();
      rebuildPuOptions();
      if (P.hasTools) {
        updateState({ toolMode: "none", extras: {}, tools: {} });
        if (P.toolConfig && P.toolConfig.extras) {
          P.toolConfig.extras.forEach(function(e) {
            S.extras[e.id] = { on: false, qty: 0, size: 0 };
          });
        }
        if (P.toolConfig && P.toolConfig.tools) {
          P.toolConfig.tools.forEach(function(t) {
            S.tools[t.id] = { on: false, qty: 0, size: 0 };
          });
        }
        buildToolSectionV2("toolSection", true);
      }
      var toggleBtns = document.querySelectorAll(".oz-formula-btn");
      for (var b = 0; b < toggleBtns.length; b++) {
        toggleBtns[b].classList.toggle("selected", toggleBtns[b].dataset.formula === mode);
      }
      var perUnits = document.querySelectorAll(".oz-per-unit");
      for (var u = 0; u < perUnits.length; u++) {
        perUnits[u].textContent = "per " + P.unit;
      }
      var m2Notes = document.querySelectorAll(".oz-m2-note");
      for (var m = 0; m < m2Notes.length; m++) {
        m2Notes[m].textContent = "per " + P.unit;
      }
      syncUI();
    }, swapContent = function(MT) {
      var uspList = document.querySelector(".oz-short-desc ul");
      if (uspList && MT.targetUsps && MT.targetUsps.length) {
        var uspHtml = "";
        for (var i = 0; i < MT.targetUsps.length; i++) {
          if (!MT.targetUsps[i]) continue;
          uspHtml += '<li><svg class="oz-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"></path></svg>' + MT.targetUsps[i] + "</li>";
        }
        uspList.innerHTML = uspHtml;
      }
      var specsBody = document.querySelector(".oz-specs-table tbody");
      if (specsBody && MT.targetSpecs) {
        var specKeys = Object.keys(MT.targetSpecs);
        if (specKeys.length) {
          var specHtml = "";
          for (var s = 0; s < specKeys.length; s++) {
            specHtml += "<tr><th>" + specKeys[s] + "</th><td>" + MT.targetSpecs[specKeys[s]] + "</td></tr>";
          }
          specsBody.innerHTML = specHtml;
        }
      }
      var faqList = document.querySelector(".oz-faq-list");
      if (faqList && MT.targetFaq && MT.targetFaq.length) {
        var faqHtml = "";
        for (var f = 0; f < MT.targetFaq.length; f++) {
          faqHtml += '<details class="oz-faq-item"><summary class="oz-faq-question">' + MT.targetFaq[f].q + '</summary><div class="oz-faq-answer">' + MT.targetFaq[f].a + "</div></details>";
        }
        faqList.innerHTML = faqHtml;
      }
      if (DOM.descContent && MT.targetDescription) {
        DOM.descContent.innerHTML = MT.targetDescription;
        DOM.descContent.classList.remove("expanded");
        if (DOM.readMoreBtn) {
          if (DOM.descContent.scrollHeight <= 120) {
            DOM.readMoreBtn.style.display = "none";
            DOM.descContent.classList.add("expanded");
          } else {
            DOM.readMoreBtn.style.display = "";
            DOM.readMoreBtn.textContent = "Lees meer";
          }
        }
      }
    }, captureOriginalContent = function() {
      if (_originalContent) return;
      _originalContent = {};
      var uspList = document.querySelector(".oz-short-desc ul");
      if (uspList) _originalContent.uspsHtml = uspList.innerHTML;
      var specsBody = document.querySelector(".oz-specs-table tbody");
      if (specsBody) _originalContent.specsHtml = specsBody.innerHTML;
      var faqList = document.querySelector(".oz-faq-list");
      if (faqList) _originalContent.faqHtml = faqList.innerHTML;
      if (DOM.descContent) _originalContent.descHtml = DOM.descContent.innerHTML;
    }, restoreContent = function() {
      if (!_originalContent) return;
      var uspList = document.querySelector(".oz-short-desc ul");
      if (uspList && _originalContent.uspsHtml) uspList.innerHTML = _originalContent.uspsHtml;
      var specsBody = document.querySelector(".oz-specs-table tbody");
      if (specsBody && _originalContent.specsHtml) specsBody.innerHTML = _originalContent.specsHtml;
      var faqList = document.querySelector(".oz-faq-list");
      if (faqList && _originalContent.faqHtml) faqList.innerHTML = _originalContent.faqHtml;
      if (DOM.descContent && _originalContent.descHtml) {
        DOM.descContent.innerHTML = _originalContent.descHtml;
        DOM.descContent.classList.remove("expanded");
        if (DOM.readMoreBtn) {
          if (DOM.descContent.scrollHeight <= 120) {
            DOM.readMoreBtn.style.display = "none";
            DOM.descContent.classList.add("expanded");
          } else {
            DOM.readMoreBtn.style.display = "";
            DOM.readMoreBtn.textContent = "Lees meer";
          }
        }
      }
    }, rebuildToggleOptions = function() {
      var toeGroup = document.querySelector('[data-option="toepassing"]');
      if (P.toepassing && P.toepassing.length) {
        if (!toeGroup) {
          var optionsWidget = DOM.optionsWidget;
          if (optionsWidget) {
            var toeSection = document.createElement("div");
            toeSection.className = "oz-option-group";
            toeSection.setAttribute("data-option", "toepassing");
            var toeHtml = '<div class="oz-option-header">Toepassing: <span class="oz-required-star" id="toepassingRequired" style="color:#e53e3e">*</span> <span class="oz-selected-value" id="selectedToepassingLabel"></span></div>';
            toeHtml += '<div class="oz-option-labels">';
            for (var t = 0; t < P.toepassing.length; t++) {
              var isSel = P.toepassing[t] === S.toepassing;
              toeHtml += '<button class="oz-option-label-btn' + (isSel ? " selected" : "") + '" data-toepassing="' + P.toepassing[t] + '">' + P.toepassing[t] + "</button>";
            }
            toeHtml += "</div>";
            toeSection.innerHTML = toeHtml;
            var puSec = optionsWidget.querySelector('[data-option="pu"]');
            if (puSec) {
              optionsWidget.insertBefore(toeSection, puSec);
            } else {
              optionsWidget.appendChild(toeSection);
            }
          }
        } else {
          toeGroup.style.display = "";
        }
      } else if (toeGroup) {
        toeGroup.style.display = "none";
      }
    }, rebuildPuOptions = function() {
      var puGroup = document.querySelector('[data-option="pu"]');
      if (!puGroup || !P.puOptions) return;
      var btnsWrap = puGroup.querySelector(".oz-option-buttons");
      if (!btnsWrap) return;
      var html = "";
      for (var i = 0; i < P.puOptions.length; i++) {
        var opt = P.puOptions[i];
        var isSelected = opt.layers == S.puLayers;
        var priceTag = "";
        if (opt.price > 0) priceTag = " +" + fmt(opt.price);
        else if (opt.price < 0) priceTag = " " + fmt(opt.price);
        html += '<button class="oz-option-btn' + (isSelected ? " selected" : "") + '" data-pu="' + opt.layers + '">' + opt.label + (priceTag ? ' <span class="oz-price-tag">' + priceTag + "</span>" : "") + "</button>";
      }
      btnsWrap.innerHTML = html;
    }, openUpsell = function() {
      updateState({ upsellOpen: true });
      document.body.style.overflow = "hidden";
      renderUpsellModal();
    }, closeUpsell = function() {
      updateState({ upsellOpen: false });
      document.body.style.overflow = "";
      renderUpsellModal();
    }, upsellAddSet = function() {
      trackUpsellAccepted();
      updateState({ toolMode: "set" });
      closeUpsell();
      syncUI();
      submitCart();
    }, upsellSkip = function() {
      trackUpsellSkipped();
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
      var formulaBtn = target.closest("[data-formula]");
      if (formulaBtn) {
        e.preventDefault();
        toggleFormula(formulaBtn.dataset.formula);
        return;
      }
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
          trackAddonSelected(key, value);
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
          var puVal = parseInt(btn.getAttribute("data-pu"), 10);
          updateState({ puLayers: puVal });
          trackOptionSelected("pu", puVal);
        } else if (btn.hasAttribute("data-primer")) {
          var primerVal = btn.getAttribute("data-primer");
          updateState({ primer: primerVal });
          trackOptionSelected("primer", primerVal);
        } else if (btn.hasAttribute("data-colorfresh")) {
          var cfVal = btn.getAttribute("data-colorfresh");
          updateState({ colorfresh: cfVal });
          trackOptionSelected("colorfresh", cfVal);
        } else if (btn.hasAttribute("data-toepassing")) {
          var toeVal = btn.getAttribute("data-toepassing");
          updateState({ toepassing: toeVal });
          trackOptionSelected("toepassing", toeVal);
        } else if (btn.hasAttribute("data-pakket")) {
          var pakVal = btn.getAttribute("data-pakket");
          updateState({ pakket: pakVal });
          trackOptionSelected("pakket", pakVal);
        }
        syncUI();
        return;
      }
      if (thumb) {
        e.preventDefault();
        trackGalleryImage(thumb.getAttribute("data-index") || 0);
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
        var newMode = modeBtn.getAttribute("data-mode");
        updateState({ colorMode: newMode });
        trackColorModeChanged(newMode);
        syncUI();
        return;
      }
      var swatch = target.closest(".oz-color-swatch");
      if (swatch) {
        var colorName = swatch.getAttribute("data-color") || "";
        trackColorSelected(colorName);
        if (swatch.hasAttribute("data-static")) {
          e.preventDefault();
          updateState({ selectedColor: colorName });
          var allSwatches = swatch.parentNode.querySelectorAll(".oz-color-swatch");
          for (var si = 0; si < allSwatches.length; si++) {
            allSwatches[si].classList.toggle("selected", allSwatches[si] === swatch);
          }
          syncUI();
          return;
        }
        e.preventDefault();
        var pid = parseInt(swatch.getAttribute("data-product-id"), 10);
        if (pid && P.variants && P.variants[pid] && navigateToVariant(pid)) {
          if (S.formulaMode === "target" && P.modeToggle) {
            var MT = P.modeToggle;
            P.productId = MT.targetProductId;
            P.basePrice = MT.targetBasePrice;
            P.productLine = MT.targetLine;
            P.unit = MT.targetUnit;
            P.unitM2 = MT.targetUnitM2;
            P.puOptions = MT.targetPuOptions;
            P.primerOptions = MT.targetPrimerOptions;
            P.toepassing = MT.targetToepassing;
            P.optionOrder = MT.targetOptionOrder;
            P.hasTools = MT.targetHasTools;
            P.toolConfig = MT.targetToolConfig;
            P.isBase = false;
            updateState({ selectedColor: colorName });
            history.replaceState(
              { productId: MT.targetProductId, formulaMode: "target" },
              "",
              MT.targetUrl
            );
            syncUI();
          }
        } else {
          saveToolState();
          window.location.href = swatch.href;
        }
        return;
      }
      var tabBtn = target.closest(".oz-tab");
      if (tabBtn) {
        e.preventDefault();
        switchTab(tabBtn.getAttribute("data-tab"));
        return;
      }
      var stickyLink = target.closest(".oz-sticky-d-link[data-tab]");
      if (stickyLink) {
        var tabId = stickyLink.getAttribute("data-tab");
        if (tabId) switchTab(tabId);
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
        if (P.isBase && validateCartState(P, S)) {
          scrollToColors();
        } else if (needsToepassing()) {
          scrollToToepassing();
        } else {
          openSheet();
        }
        return;
      }
      if (target === DOM.stickyDBtn || target.closest("#stickyDBtn")) {
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
      var navLink = target.closest(".oz-sticky-d-link");
      if (navLink) {
        e.preventDefault();
        var sectionId = navLink.getAttribute("data-scroll");
        var section = sectionId ? document.getElementById(sectionId) : null;
        if (section) smoothScrollTo(section);
        return;
      }
      if (target === DOM.stickyDOptions || target.closest("#stickyDOptions")) {
        e.preventDefault();
        var optionsEl = DOM.optionsWidget || DOM.addToCartBtn;
        if (optionsEl) smoothScrollTo(optionsEl);
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
          var bc = document.querySelector(".oz-breadcrumb-overlay");
          if (bc) adaptBreadcrumbColor(DOM.mainImg, bc);
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
      trackQtyChanged(newQty);
      if (DOM.qtyInput) DOM.qtyInput.value = newQty;
      syncUI();
    }, handleQtyInput = function() {
      var val = parseInt(DOM.qtyInput.value, 10);
      if (isNaN(val) || val < 1) val = 1;
      if (val > 99) val = 99;
      updateState({ qty: val });
      DOM.qtyInput.value = val;
      syncUI();
    }, switchTab = function(tabId) {
      var container = document.getElementById("ozTabs");
      if (!container) return;
      var tabs = container.querySelectorAll(".oz-tab");
      var panels = container.querySelectorAll(".oz-tab-panel");
      for (var i = 0; i < tabs.length; i++) {
        tabs[i].classList.toggle("active", tabs[i].getAttribute("data-tab") === tabId);
      }
      for (var j = 0; j < panels.length; j++) {
        panels[j].classList.toggle("active", panels[j].getAttribute("data-tab") === tabId);
      }
    }, toggleReadMore = function() {
      if (!DOM.descContent || !DOM.readMoreBtn) return;
      var expanded = DOM.descContent.classList.toggle("expanded");
      DOM.readMoreBtn.textContent = expanded ? "Lees minder" : "Lees meer";
    }, autoFormatColor = function(raw) {
      var s = raw.trim().replace(/\s+/g, " ");
      var core = s.replace(/^(RAL|NCS)\s*/i, "").replace(/^S\s*/i, "").trim();
      if (/^\d{4}$/.test(core)) return "RAL " + core;
      var ncsMatch = core.match(/^(\d{4})-?([A-Za-z](?:\d{2}[A-Za-z])?)$/);
      if (ncsMatch) return "NCS S " + ncsMatch[1] + "-" + ncsMatch[2].toUpperCase();
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
      input.classList.remove("invalid");
      input.classList.add("valid");
      if (isRal || isNcs) {
        if (e.type === "blur" || e.type === "focusout") {
          trackCustomColor(checkValue, isRal ? "ral" : "ncs");
        }
        if (hint) {
          hint.textContent = isRal ? "RAL kleurcode herkend" : "NCS kleurcode herkend";
          hint.className = "oz-custom-color-hint success";
        }
      } else {
        if (hint) {
          hint.textContent = "";
          hint.className = "oz-custom-color-hint";
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
          sheetOpen: S.sheetOpen,
          // Preserve bottom sheet state across color switches
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
        if (data.sheetOpen) {
          setTimeout(function() {
            openSheet();
          }, 100);
        }
      } catch (e) {
      }
    }, openSheet = function() {
      if (!DOM.bottomSheet || !DOM.sheetOverlay || !DOM.optionsWidget) return;
      trackSheetOpened();
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
    }, setupSheetSwipe = function() {
      if (!DOM.bottomSheet) return;
      var startY = 0;
      var currentY = 0;
      var isDragging = false;
      function isInHandleZone(e) {
        var touch = e.touches[0];
        var rect = DOM.bottomSheet.getBoundingClientRect();
        return touch.clientY - rect.top < 60;
      }
      DOM.bottomSheet.addEventListener("touchstart", function(e) {
        if (isInHandleZone(e) || DOM.bottomSheet.scrollTop === 0) {
          startY = e.touches[0].clientY;
          currentY = startY;
          isDragging = true;
          DOM.bottomSheet.style.transition = "none";
        }
      }, { passive: true });
      DOM.bottomSheet.addEventListener("touchmove", function(e) {
        if (!isDragging) return;
        currentY = e.touches[0].clientY;
        var deltaY = currentY - startY;
        if (deltaY > 0) {
          DOM.bottomSheet.style.transform = "translateY(" + deltaY + "px)";
          e.preventDefault();
        }
      }, { passive: false });
      DOM.bottomSheet.addEventListener("touchend", function() {
        if (!isDragging) return;
        isDragging = false;
        var deltaY = currentY - startY;
        DOM.bottomSheet.style.transition = "";
        if (deltaY > 80) {
          DOM.bottomSheet.style.transform = "";
          closeSheet();
        } else {
          DOM.bottomSheet.style.transform = "translateY(0)";
        }
      }, { passive: true });
    }, addToCart = function() {
      if (P.isBase && !(S.colorMode === "ral_ncs" && S.customColor)) {
        var colorGroup = document.querySelector('[data-option="color"]');
        if (colorGroup) {
          colorGroup.scrollIntoView({ behavior: "smooth", block: "center" });
          colorGroup.classList.add("oz-highlight");
          setTimeout(function() {
            colorGroup.classList.remove("oz-highlight");
          }, 1500);
        }
        shakeButton();
        showCartError("Kies eerst een kleur om te bestellen.");
        return;
      }
      var error = validateCartState(P, S);
      if (error) {
        if (error.indexOf("toepassing") !== -1) {
          scrollToToepassing();
        } else if (error.indexOf("gereedschap") !== -1) {
          var toolGroup = document.querySelector('[data-option="tools"]');
          if (toolGroup) toolGroup.scrollIntoView({ behavior: "smooth", block: "center" });
        }
        trackAddToCartError(error);
        shakeButton();
        showCartError(error);
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
          trackAddToCart(calculatePrices(P, S));
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
    }, smoothScrollTo = function(el) {
      var barHeight = DOM.stickyBar ? DOM.stickyBar.offsetHeight : 0;
      var targetY = el.getBoundingClientRect().top + window.pageYOffset - barHeight - 20;
      var startY = window.pageYOffset;
      var diff = targetY - startY;
      var duration = 900;
      var start = null;
      function ease(t) {
        if (t < 0.82) {
          var p = t / 0.82;
          return 1.04 * (1 - Math.pow(1 - p, 3));
        }
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
    }, scrollToColors = function() {
      var colorSection = document.querySelector('[data-option="color"]');
      if (colorSection) {
        smoothScrollTo(colorSection);
        colorSection.classList.add("oz-pulse");
        setTimeout(function() {
          colorSection.classList.remove("oz-pulse");
        }, 1500);
      }
    }, needsToepassing = function() {
      return P.toepassing && P.toepassing.length && !S.toepassing;
    }, scrollToToepassing = function() {
      var toeSection = document.querySelector('[data-option="toepassing"]');
      if (toeSection) {
        toeSection.scrollIntoView({ behavior: "smooth", block: "center" });
        toeSection.classList.add("oz-highlight");
        setTimeout(function() {
          toeSection.classList.remove("oz-highlight");
        }, 1500);
      }
    }, setupStickyBar = function() {
      if (!DOM.stickyBar) return;
      var target = DOM.addToCartBtn || DOM.optionsWidget;
      if (!target) return;
      var ctaOutOfView = false;
      var optionsInView = false;
      var isMobile = window.matchMedia("(max-width: 900px)");
      function updateStickyVisibility() {
        var show2 = ctaOutOfView && !(isMobile.matches && optionsInView);
        DOM.stickyBar.classList.toggle("visible", show2);
      }
      var ctaObserver = new IntersectionObserver(function(entries) {
        ctaOutOfView = !entries[0].isIntersecting;
        updateStickyVisibility();
      }, { threshold: 0 });
      ctaObserver.observe(target);
      if (DOM.optionsWidget) {
        var optionsObserver = new IntersectionObserver(function(entries) {
          optionsInView = entries[0].isIntersecting;
          updateStickyVisibility();
        }, { threshold: 0 });
        optionsObserver.observe(DOM.optionsWidget);
      }
    }, setupScrollReveal = function() {
      var reveals = document.querySelectorAll(".oz-reveal");
      if (!reveals.length) return;
      if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        for (var i = 0; i < reveals.length; i++) reveals[i].classList.add("oz-visible");
        return;
      }
      var observer = new IntersectionObserver(function(entries) {
        for (var i2 = 0; i2 < entries.length; i2++) {
          if (entries[i2].isIntersecting) {
            var el = entries[i2].target;
            observer.unobserve(el);
            var idx = parseInt(el.getAttribute("data-reveal-index") || "0", 10);
            var delay = idx * 150;
            (function(target, d) {
              setTimeout(function() {
                target.classList.add("oz-visible");
              }, d);
            })(el, delay);
          }
        }
      }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
      for (var j = 0; j < reveals.length; j++) observer.observe(reveals[j]);
      var showcase = document.querySelector(".oz-showcase");
      if (showcase) {
        var orbObserver = new IntersectionObserver(function(entries) {
          if (entries[0].isIntersecting) {
            showcase.classList.add("oz-orbs-visible");
            orbObserver.disconnect();
          }
        }, { threshold: 0.05 });
        orbObserver.observe(showcase);
      }
    }, adaptBreadcrumbColor = function(img, breadcrumb) {
      if (!img.naturalWidth) return;
      try {
        var canvas = document.createElement("canvas");
        var sampleHeight = 40;
        canvas.width = 80;
        canvas.height = Math.round(sampleHeight * (80 / img.naturalWidth)) || 10;
        var ctx = canvas.getContext("2d");
        ctx.drawImage(img, 0, 0, img.naturalWidth, sampleHeight, 0, 0, canvas.width, canvas.height);
        var data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        var totalLum = 0;
        var pixels = data.length / 4;
        for (var i = 0; i < data.length; i += 4) {
          totalLum += 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
        }
        var avgLum = totalLum / pixels;
        if (isDark) {
          breadcrumb.style.color = "rgba(255,255,255,0.9)";
          breadcrumb.style.textShadow = "0 1px 3px rgba(0,0,0,0.5)";
        } else {
          breadcrumb.style.color = "var(--oz-text-muted)";
          breadcrumb.style.textShadow = "none";
        }
      } catch (e) {
        console.warn("[OZ] Breadcrumb contrast detection failed:", e.message);
      }
    }, initUspTicker = function() {
      var uspContainer = document.querySelector(".oz-short-desc ul");
      if (!uspContainer || uspContainer.children.length < 2) return;
      var items = uspContainer.querySelectorAll("li");
      var wrapper = document.createElement("div");
      wrapper.className = "swiper-wrapper";
      for (var i = 0; i < items.length; i++) {
        var slide = document.createElement("div");
        slide.className = "swiper-slide";
        slide.appendChild(items[i]);
        wrapper.appendChild(slide);
      }
      uspContainer.innerHTML = "";
      uspContainer.classList.add("swiper", "oz-usp-ticker");
      uspContainer.appendChild(wrapper);
      var shortDesc = uspContainer.closest(".oz-short-desc");
      var breadcrumb = document.querySelector(".oz-breadcrumb");
      var colorLabel = document.getElementById("colorLabel");
      var title = document.querySelector(".oz-product-title");
      var price = document.querySelector(".oz-product-base-price");
      var gallery = document.querySelector(".oz-product-gallery");
      if (gallery && gallery.parentNode) {
        var mobileHeader = document.createElement("div");
        mobileHeader.className = "oz-mobile-header";
        if (shortDesc) mobileHeader.appendChild(shortDesc);
        if (title) mobileHeader.appendChild(title);
        var labelPriceRow = document.createElement("div");
        labelPriceRow.className = "oz-mobile-label-price";
        if (colorLabel) labelPriceRow.appendChild(colorLabel);
        if (price) labelPriceRow.appendChild(price);
        mobileHeader.appendChild(labelPriceRow);
        gallery.parentNode.insertBefore(mobileHeader, gallery);
      }
      if (breadcrumb && gallery) {
        breadcrumb.classList.add("oz-breadcrumb-overlay");
        gallery.insertBefore(breadcrumb, gallery.firstChild);
        var mainImg = document.getElementById("mainImg");
        if (mainImg) {
          if (mainImg.complete) {
            adaptBreadcrumbColor(mainImg, breadcrumb);
          } else {
            mainImg.addEventListener("load", function() {
              adaptBreadcrumbColor(mainImg, breadcrumb);
            }, { once: true });
          }
        }
      }
      if (window.ozLoadSwiper) {
        window.ozLoadSwiper(function() {
          new Swiper(".oz-usp-ticker", {
            slidesPerView: "auto",
            spaceBetween: 8,
            loop: true,
            autoplay: {
              delay: 3e3,
              disableOnInteraction: false,
              pauseOnMouseEnter: true
            },
            speed: 500
          });
        });
      }
    }, init = function() {
      cacheDom();
      initNavigation(syncUI);
      if (window.innerWidth <= 900) {
        initUspTicker();
      }
      setToolSyncCallback(syncUI);
      buildToolSectionV2("toolSection");
      restoreToolState();
      syncUI();
      document.addEventListener("click", handleClick);
      if (DOM.qtyInput) {
        DOM.qtyInput.addEventListener("input", handleQtyInput);
        DOM.qtyInput.addEventListener("change", function() {
          handleQtyInput();
          trackQtyChanged(S.qty);
        });
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
      setupSheetSwipe();
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
            if (link.classList.contains("oz-color-swatch") && link.hasAttribute("data-product-id")) {
              return;
            }
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
      setupScrollReveal();
      setupStickyBar();
      if (DOM.descContent && DOM.readMoreBtn) {
        if (DOM.descContent.scrollHeight <= 120) {
          DOM.readMoreBtn.style.display = "none";
          DOM.descContent.classList.add("expanded");
        }
      }
    }, openGalleryLightbox = function() {
      var mainImg = document.getElementById("mainImg");
      if (!mainImg) return;
      mainImg.addEventListener("click", function() {
        lightbox.open(mainImg.src);
      });
    };
    _preToggleUrl = P.modeToggle ? location.href : null;
    _preToggleProductId = P ? P.productId : null;
    _preToggleIsBase = P ? P.isBase : false;
    _preToggleBasePrice = P ? P.basePrice : 0;
    _originalContent = null;
    if (P.modeToggle) {
      captureOnce = function() {
        captureOriginalContent();
      };
      if (window.requestIdleCallback) {
        requestIdleCallback(captureOnce);
      } else {
        setTimeout(captureOnce, 100);
      }
    }
    window._ozToggleFormula = P.modeToggle ? toggleFormula : null;
    TOOL_STATE_KEY = "oz_bcw_tool_state";
    _sheetScrollY = 0;
    window.adaptBreadcrumbColor = adaptBreadcrumbColor;
    lightbox = {
      overlay: null,
      img: null,
      images: [],
      // array of full-size URLs
      current: 0,
      // index of currently displayed image
      /** Build the lightbox DOM (once) and append to body */
      create: function() {
        if (this.overlay) return;
        var ov = document.createElement("div");
        ov.className = "oz-lightbox";
        ov.innerHTML = '<button class="oz-lb-close" aria-label="Sluiten">&times;</button><button class="oz-lb-prev" aria-label="Vorige">&#8249;</button><button class="oz-lb-next" aria-label="Volgende">&#8250;</button><div class="oz-lb-img-wrap"><img class="oz-lb-img" alt=""></div>';
        document.body.appendChild(ov);
        this.overlay = ov;
        this.img = ov.querySelector(".oz-lb-img");
        var self = this;
        ov.addEventListener("click", function(e) {
          if (e.target === ov || e.target.classList.contains("oz-lb-img-wrap")) {
            self.close();
          }
        });
        ov.querySelector(".oz-lb-close").addEventListener("click", function() {
          self.close();
        });
        ov.querySelector(".oz-lb-prev").addEventListener("click", function(e) {
          e.stopPropagation();
          self.prev();
        });
        ov.querySelector(".oz-lb-next").addEventListener("click", function(e) {
          e.stopPropagation();
          self.next();
        });
        document.addEventListener("keydown", function(e) {
          if (!self.overlay || !self.overlay.classList.contains("oz-lb-open")) return;
          if (e.key === "Escape") self.close();
          if (e.key === "ArrowLeft") self.prev();
          if (e.key === "ArrowRight") self.next();
        });
      },
      /** Collect all gallery image URLs from thumbnails.
       *  Thumbs are rebuilt per-variant on pushState navigation.
       *  Falls back to current main image if no thumbs are visible. */
      collectImages: function() {
        var thumbStrip = document.querySelector(".oz-gallery-thumbs");
        var thumbsVisible = thumbStrip && thumbStrip.style.display !== "none";
        this.images = [];
        if (thumbsVisible) {
          var thumbs = document.querySelectorAll(".oz-gallery-thumb");
          for (var i = 0; i < thumbs.length; i++) {
            var src = thumbs[i].getAttribute("data-full-src");
            if (src) this.images.push(src);
          }
        }
        if (!this.images.length && DOM.mainImg && DOM.mainImg.src) {
          this.images.push(DOM.mainImg.src);
        }
      },
      /** Open lightbox at given image URL */
      open: function(src) {
        this.create();
        this.collectImages();
        this.current = 0;
        for (var i = 0; i < this.images.length; i++) {
          if (this.images[i] === src) {
            this.current = i;
            break;
          }
        }
        this.show();
        this.overlay.classList.add("oz-lb-open");
        document.body.style.overflow = "hidden";
        var hasMultiple = this.images.length > 1;
        this.overlay.querySelector(".oz-lb-prev").style.display = hasMultiple ? "" : "none";
        this.overlay.querySelector(".oz-lb-next").style.display = hasMultiple ? "" : "none";
      },
      /** Display the current image */
      show: function() {
        if (this.images[this.current]) {
          this.img.src = this.images[this.current];
        }
      },
      prev: function() {
        this.current = (this.current - 1 + this.images.length) % this.images.length;
        this.show();
      },
      next: function() {
        this.current = (this.current + 1) % this.images.length;
        this.show();
      },
      close: function() {
        if (this.overlay) {
          this.overlay.classList.remove("oz-lb-open");
          document.body.style.overflow = "";
        }
      }
    };
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", function() {
        init();
        openGalleryLightbox();
      });
    } else {
      init();
      openGalleryLightbox();
    }
  }
  var _preToggleUrl;
  var _preToggleProductId;
  var _preToggleIsBase;
  var _preToggleBasePrice;
  var _originalContent;
  var captureOnce;
  var TOOL_STATE_KEY;
  var _sheetScrollY;
  var lightbox;
})();
//# sourceMappingURL=oz-product-page.js.map
