/**
 * Analytics — GA4 dataLayer event tracking for product page interactions
 *
 * Pushes custom events to window.dataLayer for GA4 / GTM pickup.
 * All events are prefixed with "oz_" for easy filtering in GA4.
 * No external dependencies — only uses window.dataLayer.
 *
 * Events tracked:
 * - oz_color_selected       (swatch click or RAL/NCS input)
 * - oz_color_mode_changed   (swatch ↔ RAL/NCS toggle)
 * - oz_option_selected      (PU, primer, colorfresh, toepassing, pakket)
 * - oz_tool_mode_changed    (geen / kant & klaar / zelf samenstellen)
 * - oz_tool_toggled         (individual tool selected/deselected)
 * - oz_qty_changed          (quantity stepper)
 * - oz_add_to_cart          (successful cart submission)
 * - oz_add_to_cart_error    (validation failed)
 * - oz_upsell_shown         (tool upsell modal opened)
 * - oz_upsell_accepted      (user added tool set from upsell)
 * - oz_upsell_skipped       (user skipped upsell)
 * - oz_sheet_opened         (mobile bottom sheet)
 * - oz_gallery_image        (thumbnail clicked)
 *
 * @package OZ_Variations_BCW
 * @since 2.1.0
 */

import { P, S } from './state.js';


/* ═══ BEACON — fire-and-forget POST to server ═════════════ */
/* Separate from dataLayer push: these are two independent concerns.
 * dataLayer → GA4/GTM pickup (client-side).
 * beacon    → server-side storage for our WP admin dashboard.
 *
 * Deduplication: same event+key data within 1.5s is ignored.
 * Prevents double-clicks and rapid re-fires from polluting data. */

var _lastBeacon = '';
var _lastBeaconTime = 0;

function beacon(eventName, payload) {
  if (!P || !P.ajaxUrl || !P.analyticsNonce) return;

  // Deduplicate: skip if same event fired within 1.5 seconds
  var key = eventName + '|' + (payload.oz_color || payload.oz_option_value || payload.oz_tool_mode || '');
  var now = Date.now();
  if (key === _lastBeacon && (now - _lastBeaconTime) < 1500) return;
  _lastBeacon = key;
  _lastBeaconTime = now;

  var fd = new FormData();
  fd.append('action', 'oz_track_event');
  fd.append('nonce', P.analyticsNonce);
  fd.append('event_name', eventName);
  fd.append('event_data', JSON.stringify(payload));
  fd.append('source', 'product');
  navigator.sendBeacon(P.ajaxUrl, fd);
}


/* ═══ DATALAYER HELPER ═════════════════════════════════════ */

/**
 * Push an event to dataLayer AND beacon to server.
 * Safe — silently no-ops if dataLayer doesn't exist
 * (e.g. GTM not loaded, ad blocker, etc.)
 *
 * @param {string} eventName  GA4 event name (oz_ prefixed)
 * @param {Object} params     Event parameters
 */
function push(eventName, params) {
  window.dataLayer = window.dataLayer || [];
  var payload = Object.assign({
    event: eventName,
    oz_product_id: P.productId,
    oz_product_name: P.productName,
    oz_product_line: P.productLine || 'none',
  }, params || {});
  window.dataLayer.push(payload);  // GA4 concern
  beacon(eventName, payload);       // Server logging concern
}


/* ═══ EVENT FUNCTIONS ══════════════════════════════════════ */

/** Color swatch clicked — navigating to a different color variant */
export function trackColorSelected(colorName) {
  push('oz_color_selected', {
    oz_color: colorName,
    oz_color_mode: 'swatch',
  });
}

/** RAL/NCS custom color entered (only fires on valid codes) */
export function trackCustomColor(code, mode) {
  push('oz_color_selected', {
    oz_color: code,
    oz_color_mode: mode, // 'ral_ncs'
  });
}

/** Color mode toggled between swatch and RAL/NCS */
export function trackColorModeChanged(mode) {
  push('oz_color_mode_changed', {
    oz_color_mode: mode,
  });
}

/** Product option selected (PU layers, primer, colorfresh, etc.) */
export function trackOptionSelected(optionType, value) {
  push('oz_option_selected', {
    oz_option_type: optionType,  // 'pu', 'primer', 'colorfresh', 'toepassing', 'pakket'
    oz_option_value: String(value),
  });
}

/** Tool mode changed (geen / kant & klaar / zelf samenstellen) */
export function trackToolModeChanged(mode) {
  push('oz_tool_mode_changed', {
    oz_tool_mode: mode, // 'none', 'set', 'individual'
  });
}

/** Individual tool toggled on/off */
export function trackToolToggled(toolId, isOn) {
  push('oz_tool_toggled', {
    oz_tool_id: toolId,
    oz_tool_action: isOn ? 'selected' : 'deselected',
  });
}

/** Quantity changed */
export function trackQtyChanged(qty) {
  push('oz_qty_changed', {
    oz_qty: qty,
  });
}

/** Successful add to cart */
export function trackAddToCart(prices) {
  push('oz_add_to_cart', {
    oz_total_price: prices.total,
    oz_qty: S.qty,
    oz_pu_layers: S.puLayers,
    oz_primer: S.primer,
    oz_tool_mode: S.toolMode,
    oz_color: S.colorMode === 'ral_ncs' ? S.customColor : P.currentColor,
  });
}

/** Add to cart validation error */
export function trackAddToCartError(errorMsg) {
  push('oz_add_to_cart_error', {
    oz_error: errorMsg,
  });
}

/** Upsell modal shown */
export function trackUpsellShown() {
  push('oz_upsell_shown', {});
}

/** Upsell accepted — user added tool set */
export function trackUpsellAccepted() {
  push('oz_upsell_accepted', {});
}

/** Upsell skipped */
export function trackUpsellSkipped() {
  push('oz_upsell_skipped', {});
}

/** Mobile bottom sheet opened */
export function trackSheetOpened() {
  push('oz_sheet_opened', {});
}

/** Gallery thumbnail clicked */
export function trackGalleryImage(imageIndex) {
  push('oz_gallery_image', {
    oz_image_index: imageIndex,
  });
}

/** Generic addon group option selected */
export function trackAddonSelected(addonKey, addonValue) {
  push('oz_option_selected', {
    oz_option_type: 'addon_' + addonKey,
    oz_option_value: addonValue,
  });
}
