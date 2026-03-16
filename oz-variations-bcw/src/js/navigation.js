/**
 * PushState Color Navigation
 *
 * Swaps URL, image, title, and labels when clicking color swatches —
 * without a full page reload. Each variant URL remains a real
 * server-rendered page (SEO unchanged). Progressive enhancement:
 * if JS fails, <a> links still navigate normally.
 *
 * Applies to: Original, Microcement, All-in-One, EasyLine, Metallic, Lavasteen.
 * Does NOT affect: static swatches (Betonlook Verf, PU Color).
 *
 * @package OZ_Variations_BCW
 * @since 2.1.0
 */

import { P } from './state.js';
import { DOM } from './dom.js';

// Debounce timer for pushState — only the final click in a rapid series
// creates a browser history entry
var _pushTimer = null;

// Whether a pushState has been made in the current debounce cycle.
// Tracks if we should push (first click) or replace (rapid follow-up).
var _hasPushed = false;

// Timer for the image crossfade — cancelled on rapid clicks
var _imgTimer = null;

// Remember the initial product so we can restore isBase on popstate
var _initialProductId = null;
var _initialIsBase = false;

// Post-navigation callback (syncUI from product-page.js)
var _onAfterNavigate = null;


/* ═══ PUBLIC API ══════════════════════════════════════════ */

/**
 * Initialize pushState navigation. Call once from init() after cacheDom().
 * Tags the current page with history state and listens for back/forward.
 *
 * @param {Function} onAfterNavigate  Called after every variant swap (syncUI)
 */
export function initNavigation(onAfterNavigate) {
  _initialProductId = parseInt(P.productId, 10) || P.productId;
  _initialIsBase = P.isBase;
  _onAfterNavigate = onAfterNavigate || null;

  // Tag the initial history entry so popstate can restore it
  history.replaceState({ productId: P.productId }, '', location.href);

  window.addEventListener('popstate', function(e) {
    if (e.state && e.state.productId) {
      applyVariant(e.state.productId, true);
    }
  });
}

/**
 * Navigate to a variant via pushState. Called from swatch click handler.
 * Returns true if successful, false if variant data is missing (caller
 * should fall back to full page navigation).
 *
 * @param {number} productId  Target variant product ID
 * @return {boolean}
 */
export function navigateToVariant(productId) {
  return applyVariant(productId, false);
}


/* ═══ CORE SWAP LOGIC ════════════════════════════════════ */

/**
 * Apply a variant's data to the current page.
 * Updates: P state, image, title, labels, swatches, SEO meta, URL.
 *
 * @param {number}  productId  Target variant
 * @param {boolean} isPopstate True when triggered by back/forward button
 * @return {boolean} True if applied, false if variant data missing
 */
function applyVariant(productId, isPopstate) {
  // Normalize to integer — popstate for the initial entry carries a string
  // (wp_localize_script serializes all values as strings), while click path
  // passes integers from parseInt. Without this, === comparisons fail.
  productId = parseInt(productId, 10) || productId;

  var v = P.variants[productId];
  if (!v) return false;

  var isInitialProduct = (productId === _initialProductId);

  // 1. Update product state — affects cart submission & price calc
  P.productId = productId;
  P.currentColor = v.color;
  P.basePrice = parseFloat(v.price) || P.basePrice;
  P.productName = v.title;
  P.isBase = (isInitialProduct && _initialIsBase);

  // 2. Swap main product image with crossfade
  swapMainImage(v.fullImage);

  // 3. Gallery thumbnails — hide after swap (they belong to the original
  // server-rendered product), restore when navigating back to initial
  toggleGalleryThumbs(isInitialProduct);

  // 4. Update product title (strip color suffix from full WC name)
  var strippedTitle = stripColor(v.title, v.color);
  if (DOM.productTitle) DOM.productTitle.textContent = strippedTitle;

  // 5. Swap product description and reset read-more state
  swapDescription(v.description);

  // 6. Update all color label elements across the page
  if (DOM.selectedColorLabel) DOM.selectedColorLabel.textContent = v.color;
  if (DOM.colorLabel) {
    DOM.colorLabel.textContent = v.color;
    // Show/hide — hidden on base products, visible on color variants
    DOM.colorLabel.style.display = v.color ? '' : 'none';
  }
  if (DOM.stickyDColor)       DOM.stickyDColor.textContent = v.color;
  if (DOM.stickyColorName) {
    DOM.stickyColorName.textContent = v.color;
    // Show/hide the sticky color wrapper (hidden on base, visible on variants)
    if (DOM.stickyColorWrap) DOM.stickyColorWrap.style.display = v.color ? '' : 'none';
  }

  // 7. Update sticky bar — thumbnail, product name (mobile + desktop)
  if (v.image && DOM.stickyThumb) DOM.stickyThumb.src = v.image;
  if (DOM.stickyProductName) DOM.stickyProductName.textContent = strippedTitle;
  if (DOM.stickyDTitle) DOM.stickyDTitle.textContent = strippedTitle;

  // 8. Update swatch highlight — toggle 'selected' class
  var swatches = document.querySelectorAll('.oz-color-swatch');
  for (var i = 0; i < swatches.length; i++) {
    var spid = parseInt(swatches[i].getAttribute('data-product-id'), 10);
    swatches[i].classList.toggle('selected', spid === productId);
  }

  // 9. SEO meta tags
  updateSeoMeta(v.url, v.title);

  // 10. URL update — pushState for first click, replaceState for rapid follow-ups
  if (!isPopstate) {
    if (!_hasPushed) {
      // First click: create a real history entry immediately
      history.pushState({ productId: productId }, '', v.url);
      _hasPushed = true;
    } else {
      // Rapid follow-up: update the last entry instead of creating a new one
      history.replaceState({ productId: productId }, '', v.url);
    }

    // Reset the debounce flag after 300ms of inactivity
    clearTimeout(_pushTimer);
    _pushTimer = setTimeout(function() {
      _hasPushed = false;
    }, 300);
  }

  // 11. Notify product-page.js to recalculate prices, cart state, etc.
  if (_onAfterNavigate) _onAfterNavigate();

  return true;
}


/* ═══ HELPERS ════════════════════════════════════════════ */

/**
 * Swap the product description HTML and reset read-more state.
 * If description is empty, hides the entire section.
 */
function swapDescription(html) {
  if (!DOM.descContent) return;

  var section = document.getElementById('sectionInfo');

  if (!html) {
    // No description for this variant — hide the section
    if (section) section.style.display = 'none';
    return;
  }

  // Show section (may have been hidden by a previous variant with no description)
  if (section) section.style.display = '';

  // Replace content
  DOM.descContent.innerHTML = html;

  // Reset read-more: collapse and re-evaluate whether button is needed
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

/**
 * Swap main product image with crossfade. Cancels any in-flight swap
 * from a previous click. Includes onerror fallback.
 */
function swapMainImage(fullImageUrl) {
  if (!fullImageUrl || !DOM.mainImg) return;

  // Cancel any in-flight swap from rapid clicking
  clearTimeout(_imgTimer);

  DOM.mainImg.classList.add('oz-fade');
  _imgTimer = setTimeout(function() {
    DOM.mainImg.onload = function() {
      DOM.mainImg.classList.remove('oz-fade');
    };
    DOM.mainImg.onerror = function() {
      // Remove fade even if image fails — don't leave it invisible
      DOM.mainImg.classList.remove('oz-fade');
    };
    DOM.mainImg.src = fullImageUrl;
  }, 200);
}

/**
 * Show or hide the gallery thumbnail strip.
 * Thumbnails belong to the server-rendered product. They are valid
 * for the initial product but stale after a client-side color swap.
 *
 * @param {boolean} show  True to show (initial product), false to hide
 */
function toggleGalleryThumbs(show) {
  var thumbs = document.querySelector('.oz-gallery-thumbs');
  if (thumbs) thumbs.style.display = show ? '' : 'none';
}

/**
 * Update document.title, canonical, and OpenGraph meta tags.
 */
function updateSeoMeta(url, title) {
  document.title = title + ' - ' + (P.siteTitle || 'Beton Cire Webshop');

  var canonical = document.querySelector('link[rel="canonical"]');
  if (canonical) canonical.setAttribute('href', url);

  var ogUrl = document.querySelector('meta[property="og:url"]');
  if (ogUrl) ogUrl.setAttribute('content', url);

  var ogTitle = document.querySelector('meta[property="og:title"]');
  if (ogTitle) ogTitle.setAttribute('content', title);
}

/**
 * Strip color portion from WC product title for display.
 * "Beton Cire Original (Stone White 1000)" -> "Beton Cire Original"
 * "Microcement Cement 3" with color "Cement 3" -> "Microcement"
 */
function stripColor(fullTitle, color) {
  if (!color) return fullTitle;

  // Pattern 1: color in parentheses at end — "(Stone White 1000)"
  var stripped = fullTitle.replace(/\s*\([^)]+\)\s*$/, '');
  if (stripped !== fullTitle) return stripped.trim();

  // Pattern 2: color as suffix — "Microcement Cement 3" -> "Microcement"
  var escaped = color.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  var re = new RegExp('\\s+' + escaped + '\\s*$', 'i');
  return fullTitle.replace(re, '').trim();
}
