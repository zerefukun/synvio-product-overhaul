/**
 * Shop filter — AJAX-driven faceted search.
 *
 * State lives in URL params (?lijn=, ?kleur=, ?prijs=, ?q=). The PHP side
 * reads them via $_GET in inc/shop-filter.php so back/forward + sharing +
 * server-rendered SEO all work. JS just keeps the URL and the in-page DOM
 * in sync.
 *
 * Flow on a filter change:
 *   1. Build the next URL from the form state.
 *   2. fetch() that URL (full HTML response — cheap, ~80kb gzipped).
 *   3. Parse and swap only the chips strip, the product grid, the result
 *      count, and the pagination. The sidebar, header and chrome stay put
 *      so the user never sees a flash.
 *   4. history.pushState() so the URL bar updates without reload.
 *
 * Mobile drawer: on `.is-open` we DON'T fetch on every tap — that would
 * close the drawer mid-flow. We accumulate picks visually (.is-checked +
 * sticky bottom CTA label) and commit when the user taps "Filters
 * toepassen".
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-oz-shop-filter]');
    if (!form) return;

    var sidebar = document.getElementById('shop-sidebar');
    var toggleBtn = document.getElementById('filter-toggle');
    var closeBtn = form.querySelector('.oz-shop-filter__close');
    var mainCol = document.querySelector('.oz-shop__main');

    /* ─────────────── URL builder ─────────────── */

    function buildUrl() {
        var data = new FormData(form);
        var url = new URL(form.action, window.location.origin);
        url.search = '';
        var lijn = data.getAll('lijn[]').filter(Boolean);
        if (lijn.length)  url.searchParams.set('lijn', lijn.join(','));
        var kleur = data.getAll('kleur[]').filter(Boolean);
        if (kleur.length) url.searchParams.set('kleur', kleur.join(','));
        var pmin = (data.get('prijs_min') || '').toString().trim();
        var pmax = (data.get('prijs_max') || '').toString().trim();
        if (pmin && pmax) url.searchParams.set('prijs', pmin + '-' + pmax);
        var q = (data.get('q') || '').toString().trim();
        if (q) url.searchParams.set('q', q);
        /* Preserve orderby across filter changes (user may have re-sorted). */
        var sort = new URLSearchParams(window.location.search).get('orderby');
        if (sort && sort !== 'menu_order') url.searchParams.set('orderby', sort);
        return url.toString();
    }

    /* ─────────────── Visual feedback ─────────────── */

    function syncCheckedClass(input) {
        if (!input) return;
        var row = input.closest('.oz-shop-filter__row, .oz-shop-filter__swatch');
        if (row) row.classList.toggle('is-checked', !!input.checked);
    }

    /* Force every form input + visual-state to match the given URL. Called
       after every AJAX swap so the sidebar truthfully reflects the active
       filters — otherwise chip-clears leave stale checkboxes ticked and the
       next change handler rebuilds a URL with the just-removed filter
       still present. */
    function syncFormToUrl(targetUrl) {
        var params;
        try {
            params = new URL(targetUrl, window.location.origin).searchParams;
        } catch (e) {
            return;
        }
        var lijnActive = (params.get('lijn') || '').split(',').filter(Boolean);
        var kleurActive = (params.get('kleur') || '').split(',').filter(Boolean);

        form.querySelectorAll('input[name="lijn[]"]').forEach(function (cb) {
            cb.checked = lijnActive.indexOf(cb.value) !== -1;
            syncCheckedClass(cb);
        });
        form.querySelectorAll('input[name="kleur[]"]').forEach(function (cb) {
            cb.checked = kleurActive.indexOf(cb.value) !== -1;
            syncCheckedClass(cb);
        });

        var prijsRaw = params.get('prijs') || '';
        var m = prijsRaw.match(/^(\d+)-(\d+)$/);
        var pmin = form.querySelector('input[name="prijs_min"]');
        var pmax = form.querySelector('input[name="prijs_max"]');
        if (pmin) pmin.value = m ? m[1] : '';
        if (pmax) pmax.value = m ? m[2] : '';

        var qInput = form.querySelector('input[name="q"]');
        if (qInput && document.activeElement !== qInput) {
            qInput.value = params.get('q') || '';
        }
    }

    function setLoadingState(loading) {
        if (mainCol) mainCol.classList.toggle('is-loading', loading);
    }

    /* ─────────────── AJAX apply ─────────────── */

    var inFlight = null;

    /* Swap a node in the live DOM with the matching node from a parsed
       response document. Handles all four states: present-on-both (replace),
       missing-from-live-but-present-in-response (insert before anchor),
       present-on-live-but-missing-in-response (remove), and missing-on-both
       (no-op). */
    function swapMatching(selector, sourceDoc, anchorSelector) {
        var live = document.querySelector(selector);
        var fresh = sourceDoc.querySelector(selector);
        if (live && fresh) {
            live.replaceWith(fresh);
            return fresh;
        }
        if (!live && fresh) {
            var anchor = document.querySelector(anchorSelector);
            if (anchor) anchor.parentNode.insertBefore(fresh, anchor);
            return fresh;
        }
        if (live && !fresh) {
            live.remove();
        }
        return null;
    }

    function applyAjax(targetUrl) {
        targetUrl = targetUrl || buildUrl();
        if (targetUrl === window.location.href) return;

        /* Abort any prior fetch so rapid checkbox toggles don't race. */
        if (inFlight && inFlight.abort) inFlight.abort();

        var controller = ('AbortController' in window) ? new AbortController() : null;
        inFlight = controller;

        setLoadingState(true);

        fetch(targetUrl, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller ? controller.signal : undefined,
        })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');

                /* Strategy: swap the whole "results" block (everything inside
                   .oz-shop__main BELOW the .oz-shop__header) in one go. That's
                   safer than picking individual selectors because it survives
                   layout changes (chips appear/disappear, grid ↔ no-results
                   notice, pagination toggles) without us having to enumerate
                   every container state. */
                var liveMain = document.querySelector('.oz-shop__main');
                var freshMain = doc.querySelector('.oz-shop__main');
                if (liveMain && freshMain) {
                    /* Preserve the page header (eyebrow + h1 + description)
                       since it never changes between filter states. */
                    var liveHeader = liveMain.querySelector('.oz-shop__header');
                    var freshHeader = freshMain.querySelector('.oz-shop__header');
                    /* Replace everything AFTER the header. We walk the live
                       main's children after the header, removing them, then
                       append the fresh equivalents. */
                    var marker = liveHeader || liveMain.firstElementChild;
                    var next = marker ? marker.nextSibling : liveMain.firstChild;
                    while (next) {
                        var toRemove = next;
                        next = next.nextSibling;
                        liveMain.removeChild(toRemove);
                    }
                    /* Append everything after the fresh header. */
                    var sourceMarker = freshHeader || freshMain.firstElementChild;
                    var src = sourceMarker ? sourceMarker.nextSibling : freshMain.firstChild;
                    while (src) {
                        var toAppend = src;
                        src = src.nextSibling;
                        liveMain.appendChild(toAppend);
                    }
                }

                /* Push the new URL to the history stack (no reload). */
                history.pushState({ ozShopFilter: 1 }, '', targetUrl);

                /* Sync the sidebar form's checkbox / input state to the URL
                   we just landed on. This is the fix for "old filters keep
                   coming back": without it, removing a chip leaves the matching
                   checkbox in the sidebar still ticked, so the very next
                   change-handler call rebuilds a URL that includes it again. */
                syncFormToUrl(targetUrl);

                /* Refresh the mobile drawer CTA label with the new count. */
                refreshBottomCTALabel();

                /* Notify any other code that depends on the loop having
                   re-rendered (e.g. Yoast schema, analytics view_item_list). */
                document.dispatchEvent(new CustomEvent('oz-shop-filter:applied', {
                    detail: { url: targetUrl },
                }));
            })
            .catch(function (err) {
                /* AbortError = expected (newer request superseded us). Other
                   errors fall back to full-page nav so the user still ends
                   up with the right results. */
                if (err && err.name === 'AbortError') return;
                window.location.href = targetUrl;
            })
            .finally(function () {
                if (inFlight === controller) inFlight = null;
                setLoadingState(false);
            });
    }

    /* ─────────────── Change handlers ─────────────── */

    form.addEventListener('change', function (e) {
        var name = e.target && e.target.name;
        if (name !== 'lijn[]' && name !== 'kleur[]') return;
        syncCheckedClass(e.target);
        if (sidebar && sidebar.classList.contains('is-open')) {
            /* Defer: user is mid-flow in the mobile drawer. CTA tap commits. */
            updateBottomCTA();
            return;
        }
        applyAjax();
    });

    /* Belt-and-braces: form is method=get action=/producten/. Any submit
       (Enter key on a checkbox, programmatic form.submit(), &lt;noscript&gt;
       button on a JS-stripped session, etc.) would otherwise do a full GET
       reload. Intercept and route through applyAjax instead. */
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (debounceTimer) clearTimeout(debounceTimer);
        applyAjax();
    });

    var debounceTimer = null;
    function debounceApply() {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            if (sidebar && sidebar.classList.contains('is-open')) {
                updateBottomCTA();
                return;
            }
            applyAjax();
        }, 500);
    }
    form.addEventListener('input', function (e) {
        var name = e.target && e.target.name;
        if (name === 'q' || name === 'prijs_min' || name === 'prijs_max') {
            debounceApply();
        }
    });
    var search = form.querySelector('input[name="q"]');
    if (search) {
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (debounceTimer) clearTimeout(debounceTimer);
                applyAjax();
            }
        });
    }

    /* ─────────────── Chips + reset = same-page links → also AJAX ─────────────── */

    /* Intercept clicks on chips and the reset link so they swap in place
       instead of triggering a full reload. */
    document.addEventListener('click', function (e) {
        var chip = e.target.closest && e.target.closest('.oz-shop-chip, .oz-shop-filter__reset');
        if (!chip || !chip.href) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
        e.preventDefault();
        applyAjax(chip.href);
    });

    /* ─────────────── Back/forward = re-fetch ─────────────── */

    window.addEventListener('popstate', function () {
        /* applyAjax bails when targetUrl === window.location.href, but on
           popstate the URL has ALREADY changed to the new one, so we need
           a special-case re-fetch here. */
        var target = window.location.href;
        if (inFlight && inFlight.abort) inFlight.abort();
        var controller = ('AbortController' in window) ? new AbortController() : null;
        inFlight = controller;
        setLoadingState(true);
        fetch(target, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller ? controller.signal : undefined,
        })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var liveMain = document.querySelector('.oz-shop__main');
                var freshMain = doc.querySelector('.oz-shop__main');
                if (liveMain && freshMain) {
                    var liveHeader = liveMain.querySelector('.oz-shop__header');
                    var marker = liveHeader || liveMain.firstElementChild;
                    var next = marker ? marker.nextSibling : liveMain.firstChild;
                    while (next) { var rm = next; next = next.nextSibling; liveMain.removeChild(rm); }
                    var freshHeader = freshMain.querySelector('.oz-shop__header');
                    var sourceMarker = freshHeader || freshMain.firstElementChild;
                    var src = sourceMarker ? sourceMarker.nextSibling : freshMain.firstChild;
                    while (src) { var ap = src; src = src.nextSibling; liveMain.appendChild(ap); }
                }
                syncFormToUrl(target);
            })
            .catch(function () { window.location.reload(); })
            .finally(function () {
                if (inFlight === controller) inFlight = null;
                setLoadingState(false);
            });
    });

    /* ─────────────── Mobile drawer ─────────────── */

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
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            openDrawer();
        });
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            closeDrawer();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('is-open')) {
            closeDrawer();
        }
    });

    /* ─────────────── Sticky bottom CTA in drawer ─────────────── */

    function getProductCount() {
        var rc = document.querySelector('.woocommerce-result-count');
        if (!rc) return null;
        var m = rc.textContent.match(/van\s+(\d+)|(\d+)\s+resultaat|(\d+)\s+resultaten|^(\d+)/i);
        if (!m) return null;
        return parseInt(m[1] || m[2] || m[3] || m[4], 10);
    }
    var bottomCTA = null;
    function ctaLabelForCount() {
        var count = getProductCount();
        return count != null
            ? ('Bekijk ' + count + ' product' + (count === 1 ? '' : 'en'))
            : 'Filters toepassen';
    }
    function renderBottomCTA() {
        if (bottomCTA || !sidebar) return;
        bottomCTA = document.createElement('button');
        bottomCTA.type = 'button';
        bottomCTA.className = 'oz-shop-filter__cta';
        bottomCTA.textContent = ctaLabelForCount();
        bottomCTA.addEventListener('click', function () {
            var target = buildUrl();
            if (target === window.location.href) {
                closeDrawer();
                return;
            }
            /* Commit picks via AJAX, then close the drawer so the user
               immediately sees the new results. */
            applyAjax(target);
            closeDrawer();
        });
        sidebar.appendChild(bottomCTA);
    }
    function updateBottomCTA() {
        if (!bottomCTA) renderBottomCTA();
        if (bottomCTA) bottomCTA.textContent = 'Filters toepassen';
    }
    function refreshBottomCTALabel() {
        if (bottomCTA) bottomCTA.textContent = ctaLabelForCount();
    }
    function removeBottomCTA() {
        if (bottomCTA && bottomCTA.parentNode) {
            bottomCTA.parentNode.removeChild(bottomCTA);
        }
        bottomCTA = null;
    }
})();
