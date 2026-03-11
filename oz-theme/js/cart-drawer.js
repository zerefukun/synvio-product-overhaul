/**
 * Cart Drawer — WooCommerce AJAX integration
 *
 * Architecture: functional core + imperative shell.
 * - Pure functions compute state (totals, shipping progress, etc.)
 * - Renderers update specific DOM regions
 * - syncUI() composes all renderers from a single state read
 * - AJAX calls mutate WC cart, then refresh state
 *
 * GA4 dataLayer events tracked (oz_cart_ prefix):
 * - oz_cart_opened              (drawer opened, with trigger source)
 * - oz_cart_closed              (drawer closed, with trigger source)
 * - oz_cart_qty_increased       (+ button clicked)
 * - oz_cart_qty_decreased       (− button clicked)
 * - oz_cart_item_removed        (bin icon clicked at qty=1)
 * - oz_cart_qty_input           (qty typed directly)
 * - oz_cart_upsell_added        (upsell product added, regular or sized)
 * - oz_cart_upsell_size_selected (size pill clicked on sized upsell)
 * - oz_cart_checkout_clicked    (proceed to checkout)
 * - oz_cart_continue_shopping   (empty state shop button)
 * - oz_cart_free_shipping_reached (subtotal crosses free shipping threshold)
 *
 * @package OzTheme
 */
(function () {
    'use strict';

    /* ============================================
       CONFIG — threshold from WC free shipping settings (0 = not configured)
       ============================================ */
    var FREE_SHIP_THRESHOLD = parseFloat(ozCartDrawer.freeShipThreshold) || 0;

    /* ============================================
       BEACON — fire-and-forget POST to server
       Separate from dataLayer push: these are two independent concerns.
       dataLayer → GA4/GTM pickup (client-side).
       beacon    → server-side storage for WP admin dashboard.
       ============================================ */
    var _lastBeacon = '';
    var _lastBeaconTime = 0;

    function beacon(eventName, payload) {
        if (typeof ozCartDrawer === 'undefined' || !ozCartDrawer.analyticsNonce) return;

        /* Deduplicate: skip if same event fired within 1.5 seconds */
        var key = eventName + '|' + (payload.oz_trigger || payload.oz_upsell_name || '');
        var now = Date.now();
        if (key === _lastBeacon && (now - _lastBeaconTime) < 1500) return;
        _lastBeacon = key;
        _lastBeaconTime = now;

        var fd = new FormData();
        fd.append('action', 'oz_track_event');
        fd.append('nonce', ozCartDrawer.analyticsNonce);
        fd.append('event_name', eventName);
        fd.append('event_data', JSON.stringify(payload));
        fd.append('source', 'cart');
        navigator.sendBeacon(ozCartDrawer.ajaxUrl, fd);
    }

    /* ============================================
       DATALAYER — GA4 event tracking for cart drawer
       Mirrors oz-variations-bcw analytics.js pattern.
       All events prefixed with "oz_cart_" for filtering.
       Now also beacons to server for internal analytics.
       ============================================ */
    function dlPush(eventName, params) {
        window.dataLayer = window.dataLayer || [];
        var payload = Object.assign({ event: eventName }, params || {});
        window.dataLayer.push(payload);  // GA4 concern
        beacon(eventName, payload);       // Server logging concern
    }

    /* ============================================
       DOM REFS — collected once on DOMContentLoaded
       ============================================ */
    var R = {};

    /* ============================================
       STATE — mirrors WC cart, updated via AJAX
       ============================================ */
    var S = {
        open: false,
        items: [],       /* { key, name, price, qty, image, meta, line_total } */
        upsells: [],     /* { id, name, price, image, permalink } */
        subtotal: 0,
        count: 0,
        loading: false,  /* true during AJAX calls */
        updatingKey: null, /* cart_item_key being updated */
        initialFetch: true /* true until first fetch completes */
    };

    /* Element that triggered the drawer open — for focus restore on close */
    var _triggerEl = null;

    /* ============================================
       PURE FUNCTIONS
       ============================================ */

    /* Format price as euro string: 12.50 -> "EUR12,50" */
    function fmt(n) {
        return '\u20ac' + parseFloat(n).toFixed(2).replace('.', ',');
    }

    /* Free shipping progress */
    function shippingProgress(subtotal) {
        if (subtotal >= FREE_SHIP_THRESHOLD) {
            return { pct: 100, remaining: 0, qualified: true };
        }
        return {
            pct: Math.round((subtotal / FREE_SHIP_THRESHOLD) * 100),
            remaining: FREE_SHIP_THRESHOLD - subtotal,
            qualified: false
        };
    }

    /* Count total items (sum of qty) */
    function totalCount(items) {
        var c = 0;
        for (var i = 0; i < items.length; i++) c += items[i].qty;
        return c;
    }

    /* Escape HTML — cached element to avoid creating one per call */
    var _escEl = document.createElement('div');
    function esc(str) {
        _escEl.textContent = str;
        return _escEl.innerHTML;
    }

    /* ============================================
       FOCUS TRAP — keeps Tab/Shift+Tab inside drawer
       ============================================ */
    var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function handleFocusTrap(e) {
        if (e.key !== 'Tab' || !S.open) return;

        var focusable = R.drawer.querySelectorAll(FOCUSABLE);
        if (focusable.length === 0) return;

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (e.shiftKey) {
            /* Shift+Tab: if on first element, jump to last */
            if (document.activeElement === first) {
                e.preventDefault();
                last.focus();
            }
        } else {
            /* Tab: if on last element, jump to first */
            if (document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }

    /* ============================================
       AJAX — talk to WooCommerce
       ============================================ */

    /**
     * Fetch full cart data from our custom endpoint.
     * Updates S.items, S.upsells, S.subtotal, S.count.
     */
    function fetchCart(callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ozCartDrawer.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success && resp.data) {
                        S.items = resp.data.items || [];
                        S.upsells = resp.data.upsells || [];
                        S.subtotal = parseFloat(resp.data.subtotal) || 0;
                        S.count = totalCount(S.items);
                    }
                } catch (e) {
                    console.error('Cart drawer: parse error', e);
                }
            }
            S.loading = false;
            S.updatingKey = null;
            S.initialFetch = false;
            if (callback) callback();
        };
        xhr.send('action=oz_cart_drawer_get&nonce=' + encodeURIComponent(ozCartDrawer.nonce));
    }

    /** Update item qty in WC cart */
    function updateQty(cartKey, qty, callback) {
        S.updatingKey = cartKey;
        syncUI();

        var xhr = new XMLHttpRequest();
        xhr.open('POST', ozCartDrawer.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            /* Refresh full cart after update */
            fetchCart(function () {
                syncUI();
                updateFlatsomeMiniCart();
                if (callback) callback();
            });
        };
        xhr.send(
            'action=oz_cart_drawer_update&nonce=' + encodeURIComponent(ozCartDrawer.nonce) +
            '&cart_key=' + encodeURIComponent(cartKey) +
            '&qty=' + qty
        );
    }

    /** Remove item from WC cart */
    function removeItem(cartKey, callback) {
        S.updatingKey = cartKey;
        syncUI();

        var xhr = new XMLHttpRequest();
        xhr.open('POST', ozCartDrawer.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            fetchCart(function () {
                syncUI();
                updateFlatsomeMiniCart();
                if (callback) callback();
            });
        };
        xhr.send(
            'action=oz_cart_drawer_remove&nonce=' + encodeURIComponent(ozCartDrawer.nonce) +
            '&cart_key=' + encodeURIComponent(cartKey)
        );
    }

    /** Add upsell product to WC cart.
     *  @param {number} productId - WC product ID
     *  @param {HTMLElement} btn - the add button (disabled during request)
     *  @param {Object} [meta] - optional cart item meta (e.g. {oz_line: 'stuco-paste', oz_primer: 'Ja'})
     */
    function addUpsell(productId, btn, meta) {
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.5';

        // Build POST body — include optional meta fields for option-type upsells
        var body = 'action=oz_cart_drawer_add&nonce=' + encodeURIComponent(ozCartDrawer.nonce) +
            '&product_id=' + productId +
            '&qty=1';
        if (meta) {
            for (var key in meta) {
                if (meta.hasOwnProperty(key)) {
                    body += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(meta[key]);
                }
            }
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', ozCartDrawer.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            fetchCart(function () {
                syncUI();
                updateFlatsomeMiniCart();
            });
        };
        xhr.send(body);
    }

    /** Trigger Flatsome mini-cart fragment refresh */
    function updateFlatsomeMiniCart() {
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).trigger('wc_fragment_refresh');
        }
    }

    /* ============================================
       RENDERERS — each updates one DOM region
       ============================================ */

    /** Render drawer open/close */
    function renderDrawerState(open) {
        R.overlay.classList.toggle('open', open);
        R.drawer.classList.toggle('open', open);
        document.body.classList.toggle('oz-drawer-open', open);
    }

    /** Render header badge count */
    function renderCount(count) {
        R.drawerCount.textContent = count;
        /* Also update Flatsome header cart count if it exists */
        var flatsomeBadge = document.querySelector('.cart-icon .cart-count');
        if (flatsomeBadge) {
            flatsomeBadge.textContent = count;
        }
    }

    /* Track free shipping only once per drawer session */
    var _shippingTracked = false;

    /** Render free shipping bar */
    function renderShipping(progress) {
        if (progress.qualified) {
            R.shippingText.textContent = 'Je bestelling wordt gratis verzonden!';
            R.shippingText.className = 'oz-shipping-text qualified';
            /* Fire once per drawer open when threshold is reached.
             * Skip when threshold is 0 (free shipping not configured) — otherwise
             * every cart open would falsely trigger this event. */
            if (!_shippingTracked && FREE_SHIP_THRESHOLD > 0) {
                _shippingTracked = true;
                dlPush('oz_cart_free_shipping_reached', {
                    oz_subtotal: S.subtotal,
                    oz_threshold: FREE_SHIP_THRESHOLD,
                });
            }
        } else {
            _shippingTracked = false; /* Reset when below threshold */
            R.shippingText.innerHTML = 'Nog <strong>' + fmt(progress.remaining) + '</strong> voor gratis verzending';
            R.shippingText.className = 'oz-shipping-text';
        }
        R.shippingFill.style.width = progress.pct + '%';
        R.shippingFill.classList.toggle('full', progress.qualified);
    }

    /** Render loading skeleton */
    function renderSkeleton(show) {
        if (!R.skeleton) return;
        R.skeleton.style.display = show ? '' : 'none';
    }

    /** Render cart items */
    function renderItems(items, updatingKey) {
        var container = R.cartItems;

        /* Build map of existing nodes by cart key */
        var existing = {};
        var nodes = container.querySelectorAll('.oz-cart-item');
        for (var i = 0; i < nodes.length; i++) {
            existing[nodes[i].dataset.key] = nodes[i];
        }

        /* Track needed keys */
        var needed = {};
        for (var j = 0; j < items.length; j++) {
            needed[items[j].key] = true;
        }

        /* Remove nodes no longer in cart */
        for (var key in existing) {
            if (!needed[key]) existing[key].remove();
        }

        /* Add or update items */
        for (var k = 0; k < items.length; k++) {
            var item = items[k];
            var el = existing[item.key];

            if (!el) {
                el = createItemNode(item);
                container.appendChild(el);
            } else {
                updateItemNode(el, item);
            }

            el.classList.toggle('updating', item.key === updatingKey);
        }
    }

    /** Create a cart item DOM node */
    function createItemNode(item) {
        var el = document.createElement('div');
        el.className = 'oz-cart-item';
        el.dataset.key = item.key;

        /* Image: use real product image or placeholder */
        var imgContent = item.image
            ? '<img src="' + esc(item.image) + '" alt="' + esc(item.name) + '">'
            : '';

        /* Bin SVG — shown on dec button when qty is 1 (1-1=0 means remove) */
        var binSvg = '<svg class="oz-bin-icon" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">' +
            '<path d="M2.545 4.675L3.465 9.72C3.545 10.17 3.94 10.5 4.4 10.5H7.595C8.055 10.5 8.45 10.175 8.53 9.72L9.45 4.675" stroke="currentColor" stroke-width="0.75" stroke-linecap="round" stroke-linejoin="round"/>' +
            '<path d="M1.515 3.09H10.515" stroke="currentColor" stroke-width="0.75" stroke-linecap="round" stroke-linejoin="round"/>' +
            '<path d="M3.61 3.09L4.345 1.75C4.43 1.6 4.59 1.505 4.76 1.505H7.24C7.415 1.505 7.575 1.6 7.655 1.75L8.39 3.09" stroke="currentColor" stroke-width="0.75" stroke-linecap="round" stroke-linejoin="round"/>' +
            '<path d="M7.005 6.5H4.995" stroke="currentColor" stroke-width="0.75" stroke-linecap="round" stroke-linejoin="round"/>' +
            '</svg>';

        /* Minus character — shown on dec button when qty > 1 */
        var minusSvg = '<span class="oz-minus-icon">\u2212</span>';

        /* Dec button shows bin or minus depending on qty */
        var decContent = item.qty <= 1 ? binSvg : minusSvg;

        el.innerHTML =
            '<div class="oz-cart-item-img">' + imgContent + '</div>' +
            '<div class="oz-cart-item-info">' +
                '<div class="oz-cart-item-name">' + esc(item.name) + '</div>' +
                (item.meta ? '<div class="oz-cart-item-meta">' + esc(item.meta) + '</div>' : '') +
                '<div class="oz-cart-item-row">' +
                    '<div class="oz-cart-qty">' +
                        '<button class="oz-cart-qty-btn dec' + (item.qty <= 1 ? ' bin' : '') + '" aria-label="' + (item.qty <= 1 ? 'Verwijderen' : 'Minder') + '">' + decContent + '</button>' +
                        '<input type="number" class="oz-cart-qty-input" value="' + item.qty + '" min="1" max="99">' +
                        '<button class="oz-cart-qty-btn inc" aria-label="Meer">\u002B</button>' +
                    '</div>' +
                    '<div class="oz-cart-item-price">' + fmt(item.line_total) + '</div>' +
                '</div>' +
            '</div>';

        /* Bind events */
        var cartKey = item.key;
        var qtyInput = el.querySelector('.oz-cart-qty-input');

        el.querySelector('.dec').addEventListener('click', function () {
            var current = findItem(cartKey);
            if (current && current.qty <= 1) {
                /* Removing item (qty goes to 0) */
                dlPush('oz_cart_item_removed', {
                    oz_item_name: item.name,
                    oz_item_price: item.line_total,
                    oz_item_qty: 1,
                });
                removeItem(cartKey);
            } else {
                dlPush('oz_cart_qty_decreased', {
                    oz_item_name: item.name,
                    oz_from_qty: current ? current.qty : 1,
                    oz_to_qty: (current ? current.qty : 1) - 1,
                });
                updateQty(cartKey, (current ? current.qty : 1) - 1);
            }
        });
        el.querySelector('.inc').addEventListener('click', function () {
            var current = findItem(cartKey);
            dlPush('oz_cart_qty_increased', {
                oz_item_name: item.name,
                oz_from_qty: current ? current.qty : 1,
                oz_to_qty: (current ? current.qty : 1) + 1,
            });
            updateQty(cartKey, (current ? current.qty : 1) + 1);
        });

        /* Direct input change — user types a qty */
        qtyInput.addEventListener('change', function () {
            var val = parseInt(qtyInput.value, 10);
            if (isNaN(val) || val < 1) val = 1;
            if (val > 99) val = 99;
            qtyInput.value = val;
            dlPush('oz_cart_qty_input', {
                oz_item_name: item.name,
                oz_to_qty: val,
            });
            updateQty(cartKey, val);
        });

        /* No separate remove button — dec button becomes bin at qty=1 */

        return el;
    }

    /** Update existing cart item node (qty, price, dec button icon) */
    function updateItemNode(el, item) {
        var qtyInput = el.querySelector('.oz-cart-qty-input');
        var priceDiv = el.querySelector('.oz-cart-item-price');
        var decBtn = el.querySelector('.dec');

        if (qtyInput) qtyInput.value = item.qty;
        if (priceDiv) priceDiv.textContent = fmt(item.line_total);

        /* Swap dec button between bin icon (qty=1) and minus (qty>1) */
        if (decBtn) {
            var isBin = item.qty <= 1;
            decBtn.classList.toggle('bin', isBin);
            decBtn.setAttribute('aria-label', isBin ? 'Verwijderen' : 'Minder');

            if (isBin && !decBtn.querySelector('.oz-bin-icon')) {
                decBtn.innerHTML = '<svg class="oz-bin-icon" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">' +
                    '<path d="M2.545 4.675L3.465 9.72C3.545 10.17 3.94 10.5 4.4 10.5H7.595C8.055 10.5 8.45 10.175 8.53 9.72L9.45 4.675" stroke="currentColor" stroke-width="0.75" stroke-linecap="round" stroke-linejoin="round"/>' +
                    '<path d="M1.515 3.09H10.515" stroke="currentColor" stroke-width="0.75" stroke-linecap="round" stroke-linejoin="round"/>' +
                    '<path d="M3.61 3.09L4.345 1.75C4.43 1.6 4.59 1.505 4.76 1.505H7.24C7.415 1.505 7.575 1.6 7.655 1.75L8.39 3.09" stroke="currentColor" stroke-width="0.75" stroke-linecap="round" stroke-linejoin="round"/>' +
                    '<path d="M7.005 6.5H4.995" stroke="currentColor" stroke-width="0.75" stroke-linecap="round" stroke-linejoin="round"/>' +
                    '</svg>';
            } else if (!isBin && !decBtn.querySelector('.oz-minus-icon')) {
                decBtn.innerHTML = '<span class="oz-minus-icon">\u2212</span>';
            }
        }
    }

    /** Find item in state by key */
    function findItem(cartKey) {
        for (var i = 0; i < S.items.length; i++) {
            if (S.items[i].key === cartKey) return S.items[i];
        }
        return null;
    }

    /** Build HTML for a regular (non-sized) upsell card */
    function buildRegularCard(u) {
        var imgHtml = u.image
            ? '<img src="' + esc(u.image) + '" alt="' + esc(u.name) + '">'
            : '';
        return '<div class="oz-drawer-upsell-card" data-product-id="' + u.id + '">' +
            '<div class="oz-drawer-upsell-img">' + imgHtml + '</div>' +
            '<div class="oz-drawer-upsell-info">' +
                '<div class="oz-drawer-upsell-name">' + esc(u.name) + '</div>' +
                '<div class="oz-drawer-upsell-price">' + fmt(u.price) + '</div>' +
            '</div>' +
            '<button class="oz-drawer-upsell-add" aria-label="Toevoegen">' +
                '<svg width="16" height="16" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true"><path d="M292.31-115.38q-25.31 0-42.66-17.35-17.34-17.35-17.34-42.65 0-25.31 17.34-42.66 17.35-17.34 42.66-17.34 25.31 0 42.65 17.34 17.35 17.35 17.35 42.66 0 25.3-17.35 42.65-17.34 17.35-42.65 17.35Zm375.38 0q-25.31 0-42.65-17.35-17.35-17.35-17.35-42.65 0-25.31 17.35-42.66 17.34-17.34 42.65-17.34t42.66 17.34q17.34 17.35 17.34 42.66 0 25.3-17.34 42.65-17.35 17.35-42.66 17.35ZM235.23-740 342-515.38h265.38q6.93 0 12.31-3.47 5.39-3.46 9.23-9.61l104.62-190q4.61-8.46.77-15-3.85-6.54-13.08-6.54h-486Zm-19.54-40h520.77q26.08 0 39.23 21.27 13.16 21.27 1.39 43.81l-114.31 208.3q-8.69 14.62-22.58 22.93-13.88 8.31-30.5 8.31H324l-48.62 89.23q-6.15 9.23-.38 20 5.77 10.77 17.31 10.77h435.38v40H292.31q-35 0-52.23-29.5-17.23-29.5-.85-59.27l60.15-107.23L152.31-820H80v-40h97.69l38 80ZM342-515.38h280-280Z"/></svg>' +
            '</button>' +
        '</div>';
    }

    /** Build HTML for a sized upsell card (PU Roller, Verfbak, etc.) */
    function buildSizedCard(u) {
        var imgHtml = u.image
            ? '<img src="' + esc(u.image) + '" alt="' + esc(u.name) + '">'
            : '';

        // Find first non-in-cart size as default selection
        var defaultIdx = 0;
        for (var k = 0; k < u.sizes.length; k++) {
            if (!u.sizes[k].in_cart) { defaultIdx = k; break; }
        }

        // Build size pills
        var pillsHtml = '<div class="oz-upsell-sizes">';
        for (var s = 0; s < u.sizes.length; s++) {
            var sz = u.sizes[s];
            var active = s === defaultIdx ? ' active' : '';
            var inCart = sz.in_cart ? ' in-cart' : '';
            pillsHtml += '<button class="oz-upsell-size-pill' + active + inCart + '"' +
                ' data-wc-id="' + sz.wcId + '"' +
                ' data-price="' + sz.price + '"' +
                ' data-idx="' + s + '">' +
                esc(sz.label) +
            '</button>';
        }
        pillsHtml += '</div>';

        return '<div class="oz-drawer-upsell-card oz-sized-upsell" data-product-id="' + u.sizes[defaultIdx].wcId + '">' +
            '<div class="oz-drawer-upsell-img">' + imgHtml + '</div>' +
            '<div class="oz-drawer-upsell-info">' +
                '<div class="oz-drawer-upsell-name">' + esc(u.name) + '</div>' +
                pillsHtml +
                '<div class="oz-drawer-upsell-price">' + fmt(u.sizes[defaultIdx].price) + '</div>' +
            '</div>' +
            '<button class="oz-drawer-upsell-add" aria-label="Toevoegen">' +
                '<svg width="16" height="16" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true"><path d="M292.31-115.38q-25.31 0-42.66-17.35-17.34-17.35-17.34-42.65 0-25.31 17.34-42.66 17.35-17.34 42.66-17.34 25.31 0 42.65 17.34 17.35 17.35 17.35 42.66 0 25.3-17.35 42.65-17.34 17.35-42.65 17.35Zm375.38 0q-25.31 0-42.65-17.35-17.35-17.35-17.35-42.65 0-25.31 17.35-42.66 17.34-17.34 42.65-17.34t42.66 17.34q17.34 17.35 17.34 42.66 0 25.3-17.34 42.65-17.35 17.35-42.66 17.35ZM235.23-740 342-515.38h265.38q6.93 0 12.31-3.47 5.39-3.46 9.23-9.61l104.62-190q4.61-8.46.77-15-3.85-6.54-13.08-6.54h-486Zm-19.54-40h520.77q26.08 0 39.23 21.27 13.16 21.27 1.39 43.81l-114.31 208.3q-8.69 14.62-22.58 22.93-13.88 8.31-30.5 8.31H324l-48.62 89.23q-6.15 9.23-.38 20 5.77 10.77 17.31 10.77h435.38v40H292.31q-35 0-52.23-29.5-17.23-29.5-.85-59.27l60.15-107.23L152.31-820H80v-40h97.69l38 80ZM342-515.38h280-280Z"/></svg>' +
            '</button>' +
        '</div>';
    }

    /** Build HTML for an option upsell card (Stuco Paste with primer Ja/Nee pills).
     *  Same product ID, but different cart item meta per option.
     *  Similar to sized cards but pills control meta instead of product ID. */
    function buildOptionCard(u) {
        var imgHtml = u.image
            ? '<img src="' + esc(u.image) + '" alt="' + esc(u.name) + '">'
            : '';

        // Build option pills — first option is default active
        var pillsHtml = '<div class="oz-upsell-options">';
        for (var o = 0; o < u.options.length; o++) {
            var opt = u.options[o];
            var active = o === 0 ? ' active' : '';
            // Store meta as JSON in data attribute for easy retrieval on add
            pillsHtml += '<button class="oz-upsell-option-pill' + active + '"' +
                ' data-price="' + opt.price + '"' +
                ' data-meta=\'' + JSON.stringify(opt.meta).replace(/'/g, '&#39;') + '\'' +
                ' data-idx="' + o + '">' +
                esc(opt.label) +
            '</button>';
        }
        pillsHtml += '</div>';

        return '<div class="oz-drawer-upsell-card oz-option-upsell" data-product-id="' + u.id + '">' +
            '<div class="oz-drawer-upsell-img">' + imgHtml + '</div>' +
            '<div class="oz-drawer-upsell-info">' +
                '<div class="oz-drawer-upsell-name">' + esc(u.name) + '</div>' +
                pillsHtml +
                '<div class="oz-drawer-upsell-price">' + fmt(u.options[0].price) + '</div>' +
            '</div>' +
            '<button class="oz-drawer-upsell-add" aria-label="Toevoegen">' +
                '<svg width="16" height="16" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true"><path d="M292.31-115.38q-25.31 0-42.66-17.35-17.34-17.35-17.34-42.65 0-25.31 17.34-42.66 17.35-17.34 42.66-17.34 25.31 0 42.65 17.34 17.35 17.35 17.35 42.66 0 25.3-17.35 42.65-17.34 17.35-42.65 17.35Zm375.38 0q-25.31 0-42.65-17.35-17.35-17.35-17.35-42.65 0-25.31 17.35-42.66 17.34-17.34 42.65-17.34t42.66 17.34q17.34 17.35 17.34 42.66 0 25.3-17.34 42.65-17.35 17.35-42.66 17.35ZM235.23-740 342-515.38h265.38q6.93 0 12.31-3.47 5.39-3.46 9.23-9.61l104.62-190q4.61-8.46.77-15-3.85-6.54-13.08-6.54h-486Zm-19.54-40h520.77q26.08 0 39.23 21.27 13.16 21.27 1.39 43.81l-114.31 208.3q-8.69 14.62-22.58 22.93-13.88 8.31-30.5 8.31H324l-48.62 89.23q-6.15 9.23-.38 20 5.77 10.77 17.31 10.77h435.38v40H292.31q-35 0-52.23-29.5-17.23-29.5-.85-59.27l60.15-107.23L152.31-820H80v-40h97.69l38 80ZM342-515.38h280-280Z"/></svg>' +
            '</button>' +
        '</div>';
    }

    /** Render upsell suggestions */
    function renderUpsells(upsells) {
        var show = upsells.length > 0 && S.items.length > 0;
        R.upsellSection.style.display = show ? '' : 'none';
        if (!show) return;

        var html = '';
        for (var i = 0; i < upsells.length; i++) {
            var u = upsells[i];
            if (u.type === 'sized') {
                html += buildSizedCard(u);
            } else if (u.type === 'option') {
                html += buildOptionCard(u);
            } else {
                html += buildRegularCard(u);
            }
        }
        R.upsellList.innerHTML = html;

        /* Bind size pill selection on sized cards */
        var sizedCards = R.upsellList.querySelectorAll('.oz-sized-upsell');
        for (var si = 0; si < sizedCards.length; si++) {
            (function (card) {
                var pills = card.querySelectorAll('.oz-upsell-size-pill');
                var priceEl = card.querySelector('.oz-drawer-upsell-price');
                for (var p = 0; p < pills.length; p++) {
                    pills[p].addEventListener('click', function () {
                        // Update active state
                        for (var q = 0; q < pills.length; q++) pills[q].classList.remove('active');
                        this.classList.add('active');
                        // Update card's product ID and displayed price
                        card.dataset.productId = this.dataset.wcId;
                        priceEl.textContent = fmt(parseFloat(this.dataset.price));
                        // Track size pill selection
                        var upsellName = card.querySelector('.oz-drawer-upsell-name');
                        dlPush('oz_cart_upsell_size_selected', {
                            oz_upsell_name: upsellName ? upsellName.textContent : '',
                            oz_upsell_size: this.textContent,
                            oz_upsell_price: parseFloat(this.dataset.price),
                        });
                    });
                }
            })(sizedCards[si]);
        }

        /* Bind option pill selection on option cards (e.g. Stuco Paste primer Ja/Nee) */
        var optionCards = R.upsellList.querySelectorAll('.oz-option-upsell');
        for (var oi = 0; oi < optionCards.length; oi++) {
            (function (card) {
                var pills = card.querySelectorAll('.oz-upsell-option-pill');
                var priceEl = card.querySelector('.oz-drawer-upsell-price');
                for (var p = 0; p < pills.length; p++) {
                    pills[p].addEventListener('click', function () {
                        // Update active state
                        for (var q = 0; q < pills.length; q++) pills[q].classList.remove('active');
                        this.classList.add('active');
                        // Update displayed price
                        priceEl.textContent = fmt(parseFloat(this.dataset.price));
                        // Track option pill selection
                        var upsellName = card.querySelector('.oz-drawer-upsell-name');
                        dlPush('oz_cart_upsell_option_selected', {
                            oz_upsell_name: upsellName ? upsellName.textContent : '',
                            oz_upsell_option: this.textContent,
                            oz_upsell_price: parseFloat(this.dataset.price),
                        });
                    });
                }
            })(optionCards[oi]);
        }

        /* Bind upsell add buttons (works for regular, sized, and option cards) */
        var cards = R.upsellList.querySelectorAll('.oz-drawer-upsell-card');
        for (var j = 0; j < cards.length; j++) {
            (function (card) {
                var btn = card.querySelector('.oz-drawer-upsell-add');
                var isSized = card.classList.contains('oz-sized-upsell');
                var isOption = card.classList.contains('oz-option-upsell');
                btn.addEventListener('click', function () {
                    var prodId = parseInt(card.dataset.productId, 10);
                    var upsellName = card.querySelector('.oz-drawer-upsell-name');
                    var upsellPrice = card.querySelector('.oz-drawer-upsell-price');

                    /* Track upsell added — includes size/option label */
                    var trackParams = {
                        oz_upsell_id: prodId,
                        oz_upsell_name: upsellName ? upsellName.textContent : '',
                        oz_upsell_price: upsellPrice ? upsellPrice.textContent : '',
                        oz_upsell_type: isSized ? 'sized' : isOption ? 'option' : 'regular',
                    };
                    if (isSized) {
                        var activePillForTrack = card.querySelector('.oz-upsell-size-pill.active');
                        trackParams.oz_upsell_size = activePillForTrack ? activePillForTrack.textContent : '';
                    }
                    if (isOption) {
                        var activeOptForTrack = card.querySelector('.oz-upsell-option-pill.active');
                        trackParams.oz_upsell_option = activeOptForTrack ? activeOptForTrack.textContent : '';
                    }
                    dlPush('oz_cart_upsell_added', trackParams);

                    /* Build meta from active option pill (if option card) */
                    var meta = null;
                    if (isOption) {
                        var activeOpt = card.querySelector('.oz-upsell-option-pill.active');
                        if (activeOpt && activeOpt.dataset.meta) {
                            try { meta = JSON.parse(activeOpt.dataset.meta); } catch (e) { /* ignore */ }
                        }
                    }

                    addUpsell(prodId, btn, meta);

                    if (isSized) {
                        /* Sized cards: brief checkmark, then reset — card stays for more sizes */
                        var plusSvg = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M7 2v10M2 7h10"/></svg>';
                        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7.5l3 3 5-6"/></svg>';
                        btn.classList.add('added');
                        /* Mark the active pill as in-cart */
                        var activePill = card.querySelector('.oz-upsell-size-pill.active');
                        if (activePill) activePill.classList.add('in-cart');
                        /* Reset button after 1.5s so customer can add another size */
                        setTimeout(function () {
                            btn.innerHTML = plusSvg;
                            btn.classList.remove('added');
                            btn.style.pointerEvents = '';
                            btn.style.opacity = '';
                        }, 1500);
                    } else {
                        /* Regular + option cards: show checkmark (card will be replaced on refresh) */
                        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7.5l3 3 5-6"/></svg>';
                        btn.classList.add('added');
                    }
                });
            })(cards[j]);
        }
    }

    /** Render empty vs filled state */
    function renderEmptyState(isEmpty) {
        R.cartEmpty.style.display = isEmpty ? '' : 'none';
        R.cartItems.style.display = isEmpty ? 'none' : '';
        R.drawerFooter.style.display = isEmpty ? 'none' : '';
        /* Hide shipping bar when cart is empty OR no free shipping threshold configured */
        R.shippingBar.style.display = (isEmpty || FREE_SHIP_THRESHOLD <= 0) ? 'none' : '';
        /* Explicitly hide upsells when cart is empty */
        R.upsellSection.style.display = isEmpty ? 'none' : R.upsellSection.style.display;
    }

    /** Render footer subtotal */
    function renderFooter(subtotal) {
        R.footerSubtotal.textContent = fmt(subtotal);
    }

    /* ============================================
       syncUI — composes all renderers
       ============================================ */
    function syncUI() {
        var items = S.items;
        var isEmpty = items.length === 0;
        var shipping = shippingProgress(S.subtotal);

        renderDrawerState(S.open);
        renderCount(S.count);

        /* Show skeleton during initial fetch, hide once loaded */
        var showSkeleton = S.open && S.initialFetch;
        renderSkeleton(showSkeleton);

        if (!S.initialFetch) {
            renderEmptyState(isEmpty);
            renderShipping(shipping);
            renderItems(items, S.updatingKey);
            renderUpsells(S.upsells);
            renderFooter(S.subtotal);
        }
    }

    /* ============================================
       DRAWER OPEN / CLOSE
       ============================================ */
    function openDrawer(trigger) {
        /* Don't open drawer on cart or checkout pages — let WC handle it */
        if (ozCartDrawer.isCartOrCheckout === '1') return;

        /* Store trigger element for focus restore on close */
        _triggerEl = document.activeElement;

        S.open = true;

        /* Track drawer open with trigger source */
        dlPush('oz_cart_opened', {
            oz_trigger: trigger || 'unknown',
            oz_item_count: S.count,
        });
        /* Always refresh cart when opening */
        S.loading = true;
        fetchCart(function () {
            syncUI();
            /* Move focus into drawer after content loads */
            if (R.drawerClose) R.drawerClose.focus();
        });
        syncUI();
    }

    function closeDrawer(trigger) {
        /* Track drawer close with trigger source */
        dlPush('oz_cart_closed', {
            oz_trigger: trigger || 'unknown',
            oz_item_count: S.count,
            oz_subtotal: S.subtotal,
        });

        S.open = false;
        syncUI();

        /* Restore focus to the element that opened the drawer */
        if (_triggerEl && typeof _triggerEl.focus === 'function') {
            _triggerEl.focus();
            _triggerEl = null;
        }
    }

    /* ============================================
       EVENT BINDING
       ============================================ */
    function bindEvents() {
        /* Close drawer: button, overlay, ESC */
        R.drawerClose.addEventListener('click', function () { closeDrawer('close_button'); });
        R.overlay.addEventListener('click', function () { closeDrawer('overlay'); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && S.open) closeDrawer('esc_key');
        });

        /* Focus trap — keep Tab cycling inside the drawer */
        document.addEventListener('keydown', handleFocusTrap);

        /* Empty state shop button */
        R.emptyShopBtn.addEventListener('click', function () {
            dlPush('oz_cart_continue_shopping', {});
            closeDrawer('continue_shopping');
        });

        /* Checkout button — track before navigating */
        R.checkoutBtn.addEventListener('click', function () {
            dlPush('oz_cart_checkout_clicked', {
                oz_item_count: S.count,
                oz_subtotal: S.subtotal,
            });
        });

        /* Flatsome header cart icon — open our drawer instead of navigating to cart page.
         * Flatsome uses .off-canvas-toggle + data-open="#cart-popup" on the <a>.
         * We must: (1) remove those attrs so Flatsome's JS ignores it,
         *          (2) bind our own click handler on the <a> elements,
         *          (3) use capture phase to beat any delegated handlers. */
        var cartAnchors = document.querySelectorAll('a.header-cart-link');
        for (var i = 0; i < cartAnchors.length; i++) {
            /* Strip Flatsome off-canvas attributes so its JS won't fire */
            cartAnchors[i].classList.remove('off-canvas-toggle');
            cartAnchors[i].removeAttribute('data-open');
            cartAnchors[i].removeAttribute('data-class');
            cartAnchors[i].removeAttribute('data-pos');

            /* Open our drawer on click (capture phase = fires first) */
            cartAnchors[i].addEventListener('click', function (e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                openDrawer('cart_icon');
            }, true);
        }

        /**
         * Hook into WooCommerce add-to-cart events.
         * WC fires 'added_to_cart' on jQuery body after AJAX add-to-cart.
         * Our plugin fires a custom 'oz-added-to-cart' event after its own add-to-cart.
         */
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).on('added_to_cart', function () {
                openDrawer('wc_add_to_cart');
            });
        }

        /* Custom event from our oz-variations-bcw plugin */
        document.addEventListener('oz-added-to-cart', function () {
            openDrawer('oz_add_to_cart');
        });
    }

    /* ============================================
       INIT
       ============================================ */
    function init() {
        /* Collect DOM refs */
        R.overlay = document.getElementById('ozDrawerOverlay');
        R.drawer = document.getElementById('ozDrawer');
        R.drawerClose = document.getElementById('ozDrawerClose');
        R.drawerCount = document.getElementById('ozDrawerCount');
        R.shippingBar = document.getElementById('ozShippingBar');
        R.shippingText = document.getElementById('ozShippingText');
        R.shippingFill = document.getElementById('ozShippingFill');
        R.cartItems = document.getElementById('ozCartItems');
        R.skeleton = document.getElementById('ozCartSkeleton');
        R.upsellSection = document.getElementById('ozUpsellSection');
        R.upsellList = document.getElementById('ozUpsellList');
        R.cartEmpty = document.getElementById('ozCartEmpty');
        R.drawerFooter = document.getElementById('ozDrawerFooter');
        R.footerSubtotal = document.getElementById('ozFooterSubtotal');
        R.emptyShopBtn = document.getElementById('ozEmptyShopBtn');
        R.checkoutBtn = document.getElementById('ozCheckoutBtn');

        /* Bail if template not present */
        if (!R.drawer) return;

        /* Bind all events */
        bindEvents();

        /* Initial cart fetch (for badge count) */
        fetchCart(function () {
            syncUI();
        });
    }

    /* Run on DOM ready */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /* ============================================
       HEARTBEAT — Ping server every 30s so the analytics
       dashboard can show live active session count.
       Stops automatically when the tab is closed/navigated away.
       ============================================ */
    function sendHeartbeat() {
        if (typeof ozCartDrawer === 'undefined' || !ozCartDrawer.analyticsNonce) return;
        var fd = new FormData();
        fd.append('action', 'oz_heartbeat');
        fd.append('nonce', ozCartDrawer.analyticsNonce);
        fd.append('page_url', window.location.pathname);
        navigator.sendBeacon(ozCartDrawer.ajaxUrl, fd);
    }

    /* Send first heartbeat immediately, then every 30 seconds */
    sendHeartbeat();
    setInterval(sendHeartbeat, 30000);

    /* ============================================
       SESSION START — Track traffic source once per browser session.
       Fires oz_session_start with classified referrer + UTM params.
       Uses sessionStorage to deduplicate (clears on tab close).
       ============================================ */
    (function trackSessionStart() {
        if (typeof ozCartDrawer === 'undefined' || !ozCartDrawer.analyticsNonce) return;

        /* Only fire once per browser session */
        try {
            if (sessionStorage.getItem('oz_session_tracked')) return;
            sessionStorage.setItem('oz_session_tracked', '1');
        } catch (e) { return; } // Private browsing may throw

        /* Parse UTM params from URL */
        var params = {};
        try {
            var qs = new URLSearchParams(window.location.search);
            ['utm_source', 'utm_medium', 'utm_campaign'].forEach(function (k) {
                if (qs.has(k)) params[k] = qs.get(k);
            });
        } catch (e) {}

        /* Classify the referrer into a traffic source channel */
        var ref = document.referrer || '';
        var source = 'direct';
        var medium = 'none';

        if (params.utm_source) {
            /* UTM params take priority — advertiser controls the label */
            source = params.utm_source;
            medium = params.utm_medium || 'unknown';
        } else if (ref) {
            try {
                var host = new URL(ref).hostname.toLowerCase();

                /* Skip self-referrals (same domain = internal navigation) */
                if (host === window.location.hostname) {
                    return; // Not a new visit, don't track
                }

                /* Search engines */
                if (/google\./i.test(host))       { source = 'google';    medium = 'organic'; }
                else if (/bing\./i.test(host))     { source = 'bing';      medium = 'organic'; }
                else if (/yahoo\./i.test(host))    { source = 'yahoo';     medium = 'organic'; }
                else if (/duckduckgo/i.test(host)) { source = 'duckduckgo'; medium = 'organic'; }
                else if (/ecosia/i.test(host))     { source = 'ecosia';    medium = 'organic'; }
                /* Social media */
                else if (/facebook\.|fb\./i.test(host))    { source = 'facebook';  medium = 'social'; }
                else if (/instagram/i.test(host))           { source = 'instagram'; medium = 'social'; }
                else if (/pinterest/i.test(host))           { source = 'pinterest'; medium = 'social'; }
                else if (/youtube/i.test(host))             { source = 'youtube';   medium = 'social'; }
                else if (/tiktok/i.test(host))              { source = 'tiktok';    medium = 'social'; }
                else if (/linkedin/i.test(host))            { source = 'linkedin';  medium = 'social'; }
                else if (/twitter\.|x\.com/i.test(host))    { source = 'twitter';   medium = 'social'; }
                /* Email providers (common webmail) */
                else if (/mail\.|outlook\.|gmail/i.test(host)) { source = host; medium = 'email'; }
                /* Everything else = referral from another website */
                else { source = host; medium = 'referral'; }
            } catch (e) {
                source = 'unknown';
                medium = 'referral';
            }
        }

        /* Fire the session start event directly (not via beacon() which hardcodes source='cart') */
        var fd = new FormData();
        fd.append('action', 'oz_track_event');
        fd.append('nonce', ozCartDrawer.analyticsNonce);
        fd.append('event_name', 'oz_session_start');
        fd.append('event_data', JSON.stringify({
            oz_traffic_source:   source,
            oz_traffic_medium:   medium,
            oz_landing_page:     window.location.pathname,
            oz_utm_campaign:     params.utm_campaign || '',
            oz_referrer:         ref ? ref.substring(0, 200) : '',
        }));
        fd.append('source', 'session');
        navigator.sendBeacon(ozCartDrawer.ajaxUrl, fd);
    })();

})();
