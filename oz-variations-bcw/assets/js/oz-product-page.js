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
    upsellOpen: false
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
  function fmt(n) {
    n = parseFloat(n) || 0;
    return "\u20AC" + n.toFixed(2).replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }
  function getItemPrice(configItem, stateItem) {
    if (configItem.sizes && stateItem) return parseFloat(configItem.sizes[stateItem.size || 0].price) || 0;
    return parseFloat(configItem.price) || 0;
  }
  function calculatePrices() {
    var base = parseFloat(P.basePrice) || 0;
    var puPrice = 0;
    if (P.puOptions && S.puLayers !== null) {
      for (var i = 0; i < P.puOptions.length; i++) {
        if (P.puOptions[i].layers == S.puLayers) {
          puPrice = parseFloat(P.puOptions[i].price) || 0;
          break;
        }
      }
    }
    var primerPrice = 0;
    if (P.primerOptions && S.primer) {
      for (var i = 0; i < P.primerOptions.length; i++) {
        if (P.primerOptions[i].label === S.primer) {
          primerPrice = parseFloat(P.primerOptions[i].price) || 0;
          break;
        }
      }
    }
    var colorfreshPrice = 0;
    if (P.colorfresh && S.colorfresh) {
      for (var i = 0; i < P.colorfresh.length; i++) {
        if (P.colorfresh[i].label === S.colorfresh) {
          colorfreshPrice = parseFloat(P.colorfresh[i].price) || 0;
          break;
        }
      }
    }
    var toolsTotal = 0;
    var toolsLabel = "";
    if (P.hasTools && P.toolConfig) {
      var TC = P.toolConfig;
      if (S.toolMode === "set") {
        toolsTotal = parseFloat(TC.toolSet.price) || 0;
        toolsLabel = TC.toolSet.name;
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
          toolsLabel += " + " + extrasCount + " extra";
        }
      } else if (S.toolMode === "individual") {
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
          toolsLabel = toolLines[0].name + (toolLines[0].qty > 1 ? " \xD7" + toolLines[0].qty : "");
        } else if (toolLines.length > 1) {
          var totalItems = toolLines.reduce(function(sum, l) {
            return sum + l.qty;
          }, 0);
          toolsLabel = "Gereedschap (" + totalItems + (totalItems === 1 ? " item" : " items") + ")";
        }
      }
    }
    var unitTotal = base + puPrice + primerPrice + colorfreshPrice;
    var total = unitTotal * S.qty + toolsTotal;
    return {
      base,
      puPrice,
      primerPrice,
      colorfreshPrice,
      toolsTotal,
      toolsLabel,
      unitTotal,
      total
    };
  }
  function validateRal(code) {
    return /^\d{4}$/.test(code.trim());
  }
  function validateNcs(code) {
    return /^S\s?\d{4}-[A-Z]\d{2}[A-Z]$/i.test(code.trim());
  }
  function hasAnyTool(toolMode, tools) {
    if (!P.hasTools || !P.toolConfig) return false;
    if (toolMode === "set") return true;
    if (toolMode === "individual") {
      return P.toolConfig.tools.some(function(t) {
        return tools[t.id] && tools[t.id].on;
      });
    }
    return false;
  }
  function clampToolQty(current, delta) {
    return Math.max(1, Math.min(99, current + delta));
  }
  var CHECKMARK_SVG = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2.5 6l2.5 2.5 4.5-5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  var NUDGE_ICON = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
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
    DOM.sheetTotal = document.getElementById("sheetTotal");
    DOM.sheetCtaBtn = document.getElementById("sheetCtaBtn");
    DOM.optionsWidget = document.getElementById("optionsWidget");
    DOM.slotDesktop = document.getElementById("optionsSlotDesktop");
    DOM.desktopHome = document.getElementById("optionsDesktopHome");
    DOM.slotSheet = document.getElementById("optionsSlotSheet");
    DOM.colorModeSlot = document.getElementById("colorModeSlot");
    DOM.colorLabel = document.getElementById("colorLabel");
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
    DOM.sheetPriceToolsLine = document.getElementById("sheetPriceToolsLine");
    DOM.sheetPriceToolsLabel = document.getElementById("sheetPriceToolsLabel");
    DOM.sheetPriceTools = document.getElementById("sheetPriceTools");
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
        btn.className = "oz-tool-size-btn" + (idx === 0 ? " selected" : "");
        btn.dataset.sizeIdx = idx;
        btn.textContent = sz.label + " " + fmt(sz.price);
        btn.addEventListener("click", function(ev) {
          ev.stopPropagation();
          if (onSizeChange) onSizeChange(item.id, idx);
        });
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
    TC.extras.forEach(function(e) {
      var row = section.querySelector('[data-extra="' + e.id + '"]');
      if (!row) return;
      var st = extras[e.id];
      var isOn = st && st.on;
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
            btn.classList.toggle("selected", parseInt(btn.dataset.sizeIdx) === (st.size || 0));
          });
        }
      }
      var priceSpan = row.querySelector(".oz-tool-price");
      if (priceSpan) priceSpan.textContent = fmt(getItemPrice(e, st));
    });
    var nudgeEl = section.querySelector(".oz-smart-nudge");
    if (nudgeEl) {
      var hasExtraRollers = extras["pu-roller"] && extras["pu-roller"].on;
      var threshold = TC.nudgeQtyThreshold || 3;
      nudgeEl.classList.toggle("visible", toolMode === "set" && qty >= threshold && !hasExtraRollers);
    }
    var indList = section.querySelector('[data-list-type="individual"]');
    if (indList) indList.classList.toggle("hidden", toolMode !== "individual");
    TC.tools.forEach(function(t) {
      var row = section.querySelector('[data-tool="' + t.id + '"]');
      if (!row) return;
      var st = tools[t.id];
      var isOn = toolMode === "individual" && st && st.on;
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
            btn.classList.toggle("selected", parseInt(btn.dataset.sizeIdx) === (st.size || 0));
          });
        }
      }
      var priceSpan = row.querySelector(".oz-tool-price");
      if (priceSpan) priceSpan.textContent = fmt(getItemPrice(t, st));
    });
  }
  function setToolMode(mode) {
    S.toolMode = mode;
    _onSync();
  }
  function toggleTool(id) {
    if (S.toolMode !== "individual") return;
    var prev = S.tools[id];
    var nowOn = !prev.on;
    S.tools[id] = { on: nowOn, qty: nowOn ? 1 : 0, size: prev.size };
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
    S.extras[id] = { on: nowOn, qty: nowOn ? 1 : 0, size: prev.size };
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
    let syncUI = function() {
      var prices = calculatePrices();
      renderBreakdown(prices);
      if (DOM.stickyPrice) DOM.stickyPrice.textContent = fmt(prices.total);
      if (DOM.sheetTotal) DOM.sheetTotal.textContent = fmt(prices.total);
      renderOptionHighlights();
      renderColorMode();
      renderSelectedLabels();
      if (P.hasTools) {
        syncToolSectionV2("toolSection", S.toolMode, S.tools, S.extras, S.qty);
      }
    }, renderBreakdown = function(prices) {
      if (DOM.priceBase) DOM.priceBase.textContent = fmt(prices.base);
      if (DOM.pricePuLine) {
        if (prices.puPrice > 0) {
          show(DOM.pricePuLine);
          DOM.pricePu.textContent = fmt(prices.puPrice);
        } else {
          hide(DOM.pricePuLine);
        }
      }
      if (DOM.pricePrimerLine) {
        if (prices.primerPrice > 0) {
          show(DOM.pricePrimerLine);
          DOM.pricePrimer.textContent = fmt(prices.primerPrice);
          DOM.pricePrimerLabel.textContent = "Primer: " + S.primer;
        } else {
          hide(DOM.pricePrimerLine);
        }
      }
      if (DOM.priceColorfreshLine) {
        if (prices.colorfreshPrice > 0) {
          show(DOM.priceColorfreshLine);
          DOM.priceColorfresh.textContent = fmt(prices.colorfreshPrice);
        } else {
          hide(DOM.priceColorfreshLine);
        }
      }
      if (DOM.priceToolsLine) {
        if (prices.toolsTotal > 0) {
          show(DOM.priceToolsLine);
          if (DOM.priceToolsLabel) DOM.priceToolsLabel.textContent = prices.toolsLabel;
          if (DOM.priceTools) DOM.priceTools.textContent = fmt(prices.toolsTotal);
        } else {
          hide(DOM.priceToolsLine);
        }
      }
      if (DOM.sheetPriceToolsLine) {
        if (prices.toolsTotal > 0) {
          show(DOM.sheetPriceToolsLine);
          if (DOM.sheetPriceToolsLabel) DOM.sheetPriceToolsLabel.textContent = prices.toolsLabel;
          if (DOM.sheetPriceTools) DOM.sheetPriceTools.textContent = fmt(prices.toolsTotal);
        } else {
          hide(DOM.sheetPriceToolsLine);
        }
      }
      if (DOM.priceQtyLine) {
        if (S.qty > 1) {
          show(DOM.priceQtyLine);
          DOM.priceQtyLabel.textContent = S.qty + "\xD7 " + fmt(prices.unitTotal);
          DOM.priceQty.textContent = fmt(prices.total);
        } else {
          hide(DOM.priceQtyLine);
        }
      }
      if (DOM.priceTotal) DOM.priceTotal.textContent = fmt(prices.total);
    }, renderOptionHighlights = function() {
      var puBtns = document.querySelectorAll("[data-pu]");
      for (var i = 0; i < puBtns.length; i++) {
        var layers = parseInt(puBtns[i].getAttribute("data-pu"), 10);
        puBtns[i].classList.toggle("selected", layers === S.puLayers);
      }
      var primerBtns = document.querySelectorAll("[data-primer]");
      for (var i = 0; i < primerBtns.length; i++) {
        primerBtns[i].classList.toggle("selected", primerBtns[i].getAttribute("data-primer") === S.primer);
      }
      var cfBtns = document.querySelectorAll("[data-colorfresh]");
      for (var i = 0; i < cfBtns.length; i++) {
        cfBtns[i].classList.toggle("selected", cfBtns[i].getAttribute("data-colorfresh") === S.colorfresh);
      }
      var tpBtns = document.querySelectorAll("[data-toepassing]");
      for (var i = 0; i < tpBtns.length; i++) {
        tpBtns[i].classList.toggle("selected", tpBtns[i].getAttribute("data-toepassing") === S.toepassing);
      }
      var pkBtns = document.querySelectorAll("[data-pakket]");
      for (var i = 0; i < pkBtns.length; i++) {
        pkBtns[i].classList.toggle("selected", pkBtns[i].getAttribute("data-pakket") === S.pakket);
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
        swatches.style.display = S.colorMode === "ral_ncs" ? "none" : "";
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
      S.upsellOpen = true;
      document.body.style.overflow = "hidden";
      renderUpsellModal();
    }, closeUpsell = function() {
      S.upsellOpen = false;
      document.body.style.overflow = "";
      renderUpsellModal();
    }, upsellAddSet = function() {
      S.toolMode = "set";
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
      var thumb = target.closest(".oz-gallery-thumb");
      var infoBtn = target.closest(".oz-info-btn");
      var qtyBtn = target.closest("[data-qty-delta]");
      var modeBtn = target.closest(".oz-color-mode-btn");
      if (btn) {
        e.preventDefault();
        if (btn.hasAttribute("data-pu")) {
          S.puLayers = parseInt(btn.getAttribute("data-pu"), 10);
        } else if (btn.hasAttribute("data-primer")) {
          S.primer = btn.getAttribute("data-primer");
        } else if (btn.hasAttribute("data-colorfresh")) {
          S.colorfresh = btn.getAttribute("data-colorfresh");
        } else if (btn.hasAttribute("data-toepassing")) {
          S.toepassing = btn.getAttribute("data-toepassing");
        } else if (btn.hasAttribute("data-pakket")) {
          S.pakket = btn.getAttribute("data-pakket");
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
        S.colorMode = modeBtn.getAttribute("data-mode");
        syncUI();
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
        openSheet();
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
      var newQty = S.qty + delta;
      newQty = Math.max(1, Math.min(99, newQty));
      S.qty = newQty;
      if (DOM.qtyInput) DOM.qtyInput.value = newQty;
      syncUI();
    }, handleQtyInput = function() {
      var val = parseInt(DOM.qtyInput.value, 10);
      if (isNaN(val) || val < 1) val = 1;
      if (val > 99) val = 99;
      S.qty = val;
      DOM.qtyInput.value = val;
      syncUI();
    }, toggleReadMore = function() {
      if (!DOM.descContent || !DOM.readMoreBtn) return;
      var expanded = DOM.descContent.classList.toggle("expanded");
      DOM.readMoreBtn.textContent = expanded ? "Lees minder" : "Lees meer";
    }, handleCustomColorInput = function(e) {
      var input = e.target;
      var value = input.value.trim();
      var hint = document.getElementById("customColorHint");
      S.customColor = value;
      if (!value) {
        input.classList.remove("valid", "invalid");
        if (hint) {
          hint.textContent = "";
          hint.className = "oz-custom-color-hint";
        }
        syncUI();
        return;
      }
      var isRal = validateRal(value);
      var isNcs = validateNcs(value);
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
    }, openSheet = function() {
      if (!DOM.bottomSheet || !DOM.sheetOverlay || !DOM.optionsWidget) return;
      S.scrollY = window.scrollY;
      DOM.slotSheet.appendChild(DOM.optionsWidget);
      if (DOM.desktopHome) DOM.desktopHome.style.minHeight = "0";
      S.sheetOpen = true;
      DOM.sheetOverlay.classList.add("open");
      DOM.bottomSheet.classList.add("open");
      document.body.style.overflow = "hidden";
    }, closeSheet = function() {
      if (!DOM.bottomSheet || !DOM.sheetOverlay || !DOM.optionsWidget) return;
      S.sheetOpen = false;
      DOM.sheetOverlay.classList.remove("open");
      DOM.bottomSheet.classList.remove("open");
      document.body.style.overflow = "";
      if (DOM.desktopHome) DOM.desktopHome.appendChild(DOM.optionsWidget);
      if (S.scrollY !== void 0) window.scrollTo(0, S.scrollY);
    }, addToCart = function() {
      if (S.colorMode === "ral_ncs") {
        if (!S.customColor) {
          shakeButton();
          showCartError("Vul een RAL of NCS kleurcode in.");
          return;
        }
        if (!validateRal(S.customColor) && !validateNcs(S.customColor)) {
          shakeButton();
          showCartError("Ongeldige RAL of NCS kleurcode.");
          return;
        }
      }
      if (P.hasTools && S.toolMode === "individual" && !hasAnyTool(S.toolMode, S.tools)) {
        var toolGroup = document.querySelector('[data-option="tools"]');
        if (toolGroup) toolGroup.scrollIntoView({ behavior: "smooth", block: "center" });
        shakeButton();
        showCartError("Kies minimaal 1 gereedschap of kies een andere optie.");
        return;
      }
      if (P.hasTools && S.toolMode === "none") {
        openUpsell();
        return;
      }
      submitCart();
    }, submitCart = function() {
      var data = new FormData();
      data.append("action", "oz_bcw_add_to_cart");
      data.append("nonce", P.nonce);
      data.append("product_id", P.productId);
      data.append("quantity", S.qty);
      if (S.puLayers !== null) data.append("oz_pu_layers", S.puLayers);
      if (S.primer) data.append("oz_primer", S.primer);
      if (S.colorfresh) data.append("oz_colorfresh", S.colorfresh);
      if (S.toepassing) data.append("oz_toepassing", S.toepassing);
      if (S.pakket) data.append("oz_pakket", S.pakket);
      data.append("oz_color_mode", S.colorMode);
      if (S.colorMode === "ral_ncs") {
        data.append("oz_custom_color", S.customColor);
      }
      if (P.hasTools) {
        data.append("oz_tool_mode", S.toolMode);
        if (S.toolMode === "set") {
          data.append("oz_tool_set_id", P.toolConfig.toolSet.id);
          P.toolConfig.extras.forEach(function(e) {
            var st = S.extras[e.id];
            if (st && st.on && st.qty > 0) {
              var sizeData = e.sizes ? e.sizes[st.size || 0] : e;
              data.append("oz_extras[" + e.id + "][qty]", st.qty);
              data.append("oz_extras[" + e.id + "][wcId]", sizeData.wcId);
              if (sizeData.wapoAddon) {
                data.append("oz_extras[" + e.id + "][wapoAddon]", sizeData.wapoAddon);
              }
            }
          });
        } else if (S.toolMode === "individual") {
          P.toolConfig.tools.forEach(function(t) {
            var st = S.tools[t.id];
            if (st && st.on && st.qty > 0) {
              var sizeData = t.sizes ? t.sizes[st.size || 0] : t;
              data.append("oz_tools[" + t.id + "][qty]", st.qty);
              data.append("oz_tools[" + t.id + "][wcId]", sizeData.wcId);
              if (sizeData.wapoAddon) {
                data.append("oz_tools[" + t.id + "][wapoAddon]", sizeData.wapoAddon);
              }
            }
          });
        }
      }
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
      el.style.cssText = "color:#E53E3E;font-size:13px;margin-top:8px;";
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
      if (!DOM.stickyBar || !DOM.addToCartBtn) return;
      if (window.innerWidth >= 900) return;
      var observer = new IntersectionObserver(function(entries) {
        var isVisible = entries[0].isIntersecting;
        DOM.stickyBar.classList.toggle("visible", !isVisible);
      }, { threshold: 0 });
      observer.observe(DOM.addToCartBtn);
    }, init = function() {
      cacheDom();
      setToolSyncCallback(syncUI);
      buildToolSectionV2("toolSection");
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
      window.addEventListener("resize", function() {
        if (window.innerWidth >= 900 && DOM.stickyBar) {
          DOM.stickyBar.classList.remove("visible");
        }
      });
      if (DOM.descContent && DOM.readMoreBtn) {
        if (DOM.descContent.scrollHeight <= 120) {
          DOM.readMoreBtn.style.display = "none";
          DOM.descContent.classList.add("expanded");
        }
      }
    };
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", init);
    } else {
      init();
    }
  }
})();
//# sourceMappingURL=oz-product-page.js.map
