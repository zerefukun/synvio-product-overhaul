/**
 * Cart Drawer — WooCommerce AJAX integration
 *
 * Architecture: functional core + imperative shell.
 * - Pure functions compute state (totals, shipping progress, etc.)
 * - Renderers update specific DOM regions
 * - syncUI() composes all renderers from a single state read
 * - AJAX calls mutate WC cart, then refresh state
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

    /** Add upsell product to WC cart */
    function addUpsell(productId, btn) {
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.5';

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
        xhr.send(
            'action=oz_cart_drawer_add&nonce=' + encodeURIComponent(ozCartDrawer.nonce) +
            '&product_id=' + productId +
            '&qty=1'
        );
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

    /** Render free shipping bar */
    function renderShipping(progress) {
        if (progress.qualified) {
            R.shippingText.textContent = 'Je bestelling wordt gratis verzonden!';
            R.shippingText.className = 'oz-shipping-text qualified';
        } else {
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
                removeItem(cartKey);
            } else {
                updateQty(cartKey, (current ? current.qty : 1) - 1);
            }
        });
        el.querySelector('.inc').addEventListener('click', function () {
            var current = findItem(cartKey);
            updateQty(cartKey, (current ? current.qty : 1) + 1);
        });

        /* Direct input change — user types a qty */
        qtyInput.addEventListener('change', function () {
            var val = parseInt(qtyInput.value, 10);
            if (isNaN(val) || val < 1) val = 1;
            if (val > 99) val = 99;
            qtyInput.value = val;
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

    /** Render upsell suggestions */
    function renderUpsells(upsells) {
        var show = upsells.length > 0 && S.items.length > 0;
        R.upsellSection.style.display = show ? '' : 'none';
        if (!show) return;

        var html = '';
        for (var i = 0; i < upsells.length; i++) {
            var u = upsells[i];
            var imgHtml = u.image
                ? '<img src="' + esc(u.image) + '" alt="' + esc(u.name) + '">'
                : '';
            html +=
                '<div class="oz-drawer-upsell-card" data-product-id="' + u.id + '">' +
                    '<div class="oz-drawer-upsell-img">' + imgHtml + '</div>' +
                    '<div class="oz-drawer-upsell-info">' +
                        '<div class="oz-drawer-upsell-name">' + esc(u.name) + '</div>' +
                        '<div class="oz-drawer-upsell-price">' + fmt(u.price) + '</div>' +
                    '</div>' +
                    '<button class="oz-drawer-upsell-add" aria-label="Toevoegen">' +
                        '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M7 2v10M2 7h10"/></svg>' +
                    '</button>' +
                '</div>';
        }
        R.upsellList.innerHTML = html;

        /* Bind upsell add buttons */
        var cards = R.upsellList.querySelectorAll('.oz-drawer-upsell-card');
        for (var j = 0; j < cards.length; j++) {
            (function (card) {
                var prodId = parseInt(card.dataset.productId, 10);
                var btn = card.querySelector('.oz-drawer-upsell-add');
                btn.addEventListener('click', function () {
                    addUpsell(prodId, btn);
                    /* Show checkmark after adding */
                    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7.5l3 3 5-6"/></svg>';
                    btn.classList.add('added');
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
    function openDrawer() {
        /* Don't open drawer on cart or checkout pages — let WC handle it */
        if (ozCartDrawer.isCartOrCheckout === '1') return;

        /* Store trigger element for focus restore on close */
        _triggerEl = document.activeElement;

        S.open = true;
        /* Always refresh cart when opening */
        S.loading = true;
        fetchCart(function () {
            syncUI();
            /* Move focus into drawer after content loads */
            if (R.drawerClose) R.drawerClose.focus();
        });
        syncUI();
    }

    function closeDrawer() {
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
        R.drawerClose.addEventListener('click', closeDrawer);
        R.overlay.addEventListener('click', closeDrawer);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && S.open) closeDrawer();
        });

        /* Focus trap — keep Tab cycling inside the drawer */
        document.addEventListener('keydown', handleFocusTrap);

        /* Empty state shop button */
        R.emptyShopBtn.addEventListener('click', closeDrawer);

        /* Flatsome header cart icon — open drawer instead of going to cart page */
        var cartLinks = document.querySelectorAll('.header-cart-link, .cart-icon, a[href*="cart"]');
        for (var i = 0; i < cartLinks.length; i++) {
            /* Only intercept Flatsome cart icon links, not all cart links */
            if (cartLinks[i].closest('.header-cart') || cartLinks[i].classList.contains('header-cart-link')) {
                cartLinks[i].addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openDrawer();
                });
            }
        }

        /**
         * Hook into WooCommerce add-to-cart events.
         * WC fires 'added_to_cart' on jQuery body after AJAX add-to-cart.
         * Our plugin fires a custom 'oz-added-to-cart' event after its own add-to-cart.
         */
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).on('added_to_cart', function () {
                openDrawer();
            });
        }

        /* Custom event from our oz-variations-bcw plugin */
        document.addEventListener('oz-added-to-cart', function () {
            openDrawer();
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
})();
