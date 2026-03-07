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
       CONFIG
       ============================================ */
    var FREE_SHIP_THRESHOLD = 150;

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
        updatingKey: null /* cart_item_key being updated */
    };

    /* ============================================
       PURE FUNCTIONS
       ============================================ */

    /* Format price as euro string: 12.50 → "€12,50" */
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

    /* Escape HTML */
    function esc(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
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
        btn.textContent = 'Toevoegen...';
        btn.style.pointerEvents = 'none';

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

        el.innerHTML =
            '<div class="oz-cart-item-img">' + imgContent + '</div>' +
            '<div class="oz-cart-item-info">' +
                '<div class="oz-cart-item-name">' + esc(item.name) + '</div>' +
                (item.meta ? '<div class="oz-cart-item-meta">' + esc(item.meta) + '</div>' : '') +
                '<div class="oz-cart-item-row">' +
                    '<div class="oz-cart-qty">' +
                        '<button class="oz-cart-qty-btn dec" aria-label="Minder">\u2212</button>' +
                        '<span class="oz-cart-qty-val">' + item.qty + '</span>' +
                        '<button class="oz-cart-qty-btn inc" aria-label="Meer">+</button>' +
                    '</div>' +
                    '<div class="oz-cart-item-price">' + fmt(item.line_total) + '</div>' +
                '</div>' +
            '</div>' +
            '<button class="oz-cart-item-remove" aria-label="Verwijderen">' +
                '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M1 1l12 12M13 1L1 13"/></svg>' +
            '</button>';

        /* Bind events */
        var cartKey = item.key;
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
        el.querySelector('.oz-cart-item-remove').addEventListener('click', function () {
            removeItem(cartKey);
        });

        /* Show bin icon when qty is 1 */
        if (item.qty <= 1) {
            el.querySelector('.dec').classList.add('bin');
        }

        return el;
    }

    /** Update existing cart item node (qty + price) */
    function updateItemNode(el, item) {
        var qtySpan = el.querySelector('.oz-cart-qty-val');
        var priceDiv = el.querySelector('.oz-cart-item-price');
        var decBtn = el.querySelector('.dec');

        if (qtySpan) qtySpan.textContent = item.qty;
        if (priceDiv) priceDiv.textContent = fmt(item.line_total);
        if (decBtn) decBtn.classList.toggle('bin', item.qty <= 1);
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
                    '<button class="oz-drawer-upsell-add">Toevoegen</button>' +
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
                    btn.textContent = 'Toegevoegd';
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
        R.shippingBar.style.display = isEmpty ? 'none' : '';
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
        renderEmptyState(isEmpty);
        renderShipping(shipping);
        renderItems(items, S.updatingKey);
        renderUpsells(S.upsells);
        renderFooter(S.subtotal);
    }

    /* ============================================
       DRAWER OPEN / CLOSE
       ============================================ */
    function openDrawer() {
        S.open = true;
        /* Always refresh cart when opening */
        S.loading = true;
        fetchCart(function () {
            syncUI();
        });
        syncUI();
    }

    function closeDrawer() {
        S.open = false;
        syncUI();
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
