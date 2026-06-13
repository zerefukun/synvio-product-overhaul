/**
 * Shop filter — auto-submit on change + mobile drawer behaviour.
 *
 * Auto-submit: every checkbox/swatch toggle, and a debounced submit on the
 * search and price inputs, navigates to the resulting URL. State lives in
 * URL params (the PHP side reads them) so back/forward + sharing work.
 *
 * Mobile drawer: when the .is-open class is on .oz-shop__sidebar (set by the
 * "Filter" button in the toolbar) we add a sticky bottom CTA "Bekijk N
 * producten" that closes the drawer. The button count is read from
 * .woocommerce-result-count.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-oz-shop-filter]');
    if (!form) {
        return;
    }

    /* ─────────────── Auto-submit on change ─────────────── */

    /* Combine prijs_min/max into a single ?prijs=min-max param before submit,
       so the URL stays clean. We strip empty fields too. */
    function buildUrl() {
        var data = new FormData(form);
        var url = new URL(form.action, window.location.origin);
        url.search = '';

        var lijn = data.getAll('lijn').filter(Boolean);
        if (lijn.length) {
            url.searchParams.set('lijn', lijn.join(','));
        }
        var kleur = data.getAll('kleur').filter(Boolean);
        if (kleur.length) {
            url.searchParams.set('kleur', kleur.join(','));
        }
        var pmin = (data.get('prijs_min') || '').toString().trim();
        var pmax = (data.get('prijs_max') || '').toString().trim();
        if (pmin && pmax) {
            url.searchParams.set('prijs', pmin + '-' + pmax);
        }
        var q = (data.get('q') || '').toString().trim();
        if (q) {
            url.searchParams.set('q', q);
        }
        return url.toString();
    }

    function navigate() {
        window.location.href = buildUrl();
    }

    /* Instant nav on checkbox/swatch change. */
    form.addEventListener('change', function (e) {
        var name = e.target && e.target.name;
        if (name === 'lijn[]' || name === 'kleur[]') {
            navigate();
        }
    });

    /* Debounced nav on search + price typing. */
    var debounceTimer = null;
    function debounceNavigate() {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(navigate, 600);
    }
    form.addEventListener('input', function (e) {
        var name = e.target && e.target.name;
        if (name === 'q' || name === 'prijs_min' || name === 'prijs_max') {
            debounceNavigate();
        }
    });
    /* Enter on search field → instant nav. */
    var search = form.querySelector('input[name="q"]');
    if (search) {
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (debounceTimer) clearTimeout(debounceTimer);
                navigate();
            }
        });
    }

    /* ─────────────── Mobile drawer ─────────────── */

    var sidebar = document.getElementById('shop-sidebar');
    var toggle = document.getElementById('filter-toggle');
    var close = form.querySelector('.oz-shop-filter__close');

    function openDrawer() {
        if (!sidebar) return;
        sidebar.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        renderBottomCTA();
    }
    function closeDrawer() {
        if (!sidebar) return;
        sidebar.classList.remove('is-open');
        document.body.style.overflow = '';
        removeBottomCTA();
    }
    if (toggle) {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            openDrawer();
        });
    }
    if (close) {
        close.addEventListener('click', function (e) {
            e.preventDefault();
            closeDrawer();
        });
    }
    /* Close on Escape. */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('is-open')) {
            closeDrawer();
        }
    });

    /* ─────────────── Sticky bottom CTA in drawer ───────────────
       Reads the product count from the WC result-count element so the CTA
       label tells the user how many products match before they close. */
    function getProductCount() {
        var rc = document.querySelector('.woocommerce-result-count');
        if (!rc) return null;
        var m = rc.textContent.match(/van\s+(\d+)|(\d+)\s+resultaat|(\d+)\s+resultaten|^(\d+)/i);
        if (!m) return null;
        return parseInt(m[1] || m[2] || m[3] || m[4], 10);
    }
    var bottomCTA = null;
    function renderBottomCTA() {
        if (bottomCTA || !sidebar) return;
        var count = getProductCount();
        var label = count != null
            ? ('Bekijk ' + count + ' product' + (count === 1 ? '' : 'en'))
            : 'Filters toepassen';
        bottomCTA = document.createElement('button');
        bottomCTA.type = 'button';
        bottomCTA.className = 'oz-shop-filter__cta';
        bottomCTA.textContent = label;
        bottomCTA.addEventListener('click', closeDrawer);
        sidebar.appendChild(bottomCTA);
    }
    function removeBottomCTA() {
        if (bottomCTA && bottomCTA.parentNode) {
            bottomCTA.parentNode.removeChild(bottomCTA);
        }
        bottomCTA = null;
    }
})();
