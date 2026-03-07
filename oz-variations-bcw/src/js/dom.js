/**
 * DOM Cache & Helpers
 *
 * Extracted from state.js to keep that module pure (no browser APIs).
 * Contains:
 * - DOM element cache (populated once by cacheDom)
 * - show/hide helpers
 *
 * @package OZ_Variations_BCW
 * @since 2.0.0
 */


/* ═══ DOM CACHE ════════════════════════════════════════════ */

// Cache DOM references for frequently accessed elements.
// Populated once by cacheDom() during init().
export var DOM = {};

/**
 * Populate the DOM cache. Called once from init().
 * All getElementById calls happen here — no scattered lookups.
 */
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
  DOM.sheetCtaBtn    = document.getElementById('sheetCtaBtn');
  DOM.optionsWidget  = document.getElementById('optionsWidget');
  DOM.slotDesktop    = document.getElementById('optionsSlotDesktop');
  DOM.desktopHome    = document.getElementById('optionsDesktopHome');
  DOM.slotSheet      = document.getElementById('optionsSlotSheet');
  DOM.colorModeSlot  = document.getElementById('colorModeSlot');
  DOM.colorLabel     = document.getElementById('colorLabel');

  // Price breakdown elements
  DOM.priceBaseLabel     = document.getElementById('priceBaseLabel');
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
  DOM.upsellOverlay      = document.getElementById('upsellOverlay');
  DOM.upsellAddBtn       = document.getElementById('upsellAddBtn');
  DOM.upsellSkipBtn      = document.getElementById('upsellSkipBtn');
  DOM.priceQtyLine       = document.getElementById('priceQtyLine');
  DOM.priceQtyLabel      = document.getElementById('priceQtyLabel');
  DOM.priceQty           = document.getElementById('priceQty');
  DOM.priceTotal         = document.getElementById('priceTotal');
}


/* ═══ DISPLAY HELPERS ══════════════════════════════════════ */

/** Show an element (reset display). Null-safe. */
export function show(el) { if (el) el.style.display = ''; }

/** Hide an element (display:none). Null-safe. */
export function hide(el) { if (el) el.style.display = 'none'; }
