/**
 * OZ Header — menu drawer, search drawer, sticky header, submenu drill-down.
 * Replaces Flatsome header interactions sitewide.
 */
(function () {
  'use strict';

  /* ── DOM refs ── */
  var header     = document.getElementById('oz-header');
  var menuDrawer = document.getElementById('oz-menu-drawer');
  var menuTrigger = document.getElementById('oz-menu-trigger');
  var menuClose  = document.getElementById('oz-menu-close');
  var menuOverlay = document.getElementById('oz-menu-overlay');

  var searchDrawer = document.getElementById('oz-search-drawer');
  var searchTrigger = document.getElementById('oz-search-trigger');
  var searchClose  = document.getElementById('oz-search-close');
  var searchOverlay = document.getElementById('oz-search-overlay');
  var searchInput  = document.getElementById('oz-search-input');
  var searchClear  = document.getElementById('oz-search-clear');

  var resultsSection  = document.getElementById('oz-search-results');
  var resultsGrid     = document.getElementById('oz-search-results-grid');
  var noResultsSection = document.getElementById('oz-search-no-results');
  var queryText       = document.getElementById('oz-search-query-text');
  var loadingSection  = document.getElementById('oz-search-loading');
  var recentSection   = document.getElementById('oz-search-recent');
  var recentList      = document.getElementById('oz-search-recent-list');
  var clearRecentBtn  = document.getElementById('oz-search-clear-recent');
  var viewAllLink     = document.getElementById('oz-search-view-all');

  /* ── Helpers ── */
  function lockBody() { document.body.classList.add('oz-drawer-open'); }
  function unlockBody() {
    if (!menuDrawer.classList.contains('is-open') &&
        !searchDrawer.classList.contains('is-open')) {
      document.body.classList.remove('oz-drawer-open');
    }
  }

  /* ── Menu drawer ── */
  function openMenu() {
    menuDrawer.classList.add('is-open');
    menuDrawer.setAttribute('aria-hidden', 'false');
    header.classList.add('menu-open');
    menuTrigger.setAttribute('aria-expanded', 'true');
    lockBody();
  }

  function closeMenu() {
    menuDrawer.classList.remove('is-open');
    menuDrawer.setAttribute('aria-hidden', 'true');
    header.classList.remove('menu-open');
    menuTrigger.setAttribute('aria-expanded', 'false');
    closeAllSubmenus();
    unlockBody();
  }

  if (menuTrigger) menuTrigger.addEventListener('click', function () {
    menuDrawer.classList.contains('is-open') ? closeMenu() : openMenu();
  });
  if (menuClose)   menuClose.addEventListener('click', closeMenu);
  if (menuOverlay) menuOverlay.addEventListener('click', closeMenu);

  /* ── Submenu drill-down ── */
  var submenuButtons = document.querySelectorAll('.oz-menu-has-children');
  var submenuPanels  = document.querySelectorAll('.oz-menu-drawer__subcategory');
  var backButtons    = document.querySelectorAll('[data-back-button]');

  function openSubmenu(id) {
    var panel = document.querySelector('[data-submenu-panel="' + id + '"]');
    if (panel) panel.classList.add('is-open');
  }

  function closeAllSubmenus() {
    submenuPanels.forEach(function (p) { p.classList.remove('is-open'); });
  }

  submenuButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      openSubmenu(btn.getAttribute('data-submenu'));
    });
  });

  backButtons.forEach(function (btn) {
    btn.addEventListener('click', closeAllSubmenus);
  });

  /* ── Search drawer ── */
  function openSearch() {
    searchDrawer.classList.add('is-open');
    searchDrawer.setAttribute('aria-hidden', 'false');
    lockBody();
    renderRecent();
    /* Focus input after transition */
    setTimeout(function () { searchInput && searchInput.focus(); }, 350);
  }

  function closeSearch() {
    searchDrawer.classList.remove('is-open');
    searchDrawer.setAttribute('aria-hidden', 'true');
    unlockBody();
  }

  /* Both desktop and mobile search triggers open the same drawer */
  var searchTriggerMobile = document.getElementById('oz-search-trigger-mobile');
  if (searchTrigger) searchTrigger.addEventListener('click', openSearch);
  if (searchTriggerMobile) searchTriggerMobile.addEventListener('click', openSearch);
  if (searchClose)   searchClose.addEventListener('click', closeSearch);
  if (searchOverlay) searchOverlay.addEventListener('click', closeSearch);

  /* ── Escape key closes drawers ── */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      if (searchDrawer && searchDrawer.classList.contains('is-open')) closeSearch();
      else if (menuDrawer && menuDrawer.classList.contains('is-open')) closeMenu();
    }
  });

  /* ── Sticky header: overlay → solid on scroll ── */
  if (header && header.classList.contains('oz-header--overlay')) {
    /* body class is now added server-side in header.php to prevent CLS.
       Keeping this as a safety fallback for any edge case. */
    if (!document.body.classList.contains('oz-header-overlay-page')) {
      document.body.classList.add('oz-header-overlay-page');
    }
    var threshold = 80;
    var ticking = false;

    window.addEventListener('scroll', function () {
      if (!ticking) {
        window.requestAnimationFrame(function () {
          if (window.scrollY > threshold) {
            header.classList.add('is-stuck');
          } else {
            header.classList.remove('is-stuck');
          }
          ticking = false;
        });
        ticking = true;
      }
    }, { passive: true });
  }

  /* ── Predictive search ──
     Uses: debounce (300ms) + AbortController (cancel stale requests)
           + in-memory LRU cache (skip API for repeated queries)
           + exhaustive-prefix filter (skip API entirely when safe)
           + cross-cache substring filter on all seen products (instant
             visual response — no spinner — while a refining fetch runs in
             the background to fill in any missing matches)
           + accent / case-insensitive normalization (semantic match:
             "cafe" → "Café", "BETON" → "beton")
           + active query guard (ignore out-of-order responses). */
  var debounceTimer = null;
  var activeAbort = null;       /* AbortController for in-flight request */
  var activeQuery = '';          /* latest query we care about */
  var searchCache = new Map();   /* query → products array */
  var productPool = new Map();   /* id → product (every product we've seen, deduped) */
  var CACHE_MAX = 30;            /* evict oldest after this many entries */
  var PER_PAGE = 6;              /* must match the per_page in the fetch URL */
  var siteUrl = (typeof ozHeaderData !== 'undefined' && ozHeaderData.siteUrl)
    ? ozHeaderData.siteUrl
    : window.location.origin;

  /* Normalize for semantic matching: lowercase + strip accents/diacritics.
     "Café" and "cafe" collapse to "cafe" so the filter doesn't miss hits
     just because of Dutch/French accented characters. */
  function normalize(s) {
    return (s || '')
      .toString()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  /* Subsequence match: every char of `needle` appears in `haystack` in order,
     not necessarily consecutive. Tolerates missing/extra letters in typos --
     "metalic" matches "metallicstucvelvet" because m-e-t-a-l-i-c all appear
     in order. Used as a FALLBACK after substring match fails, and gated at
     4+ chars so short queries like "asd" don't accidentally match long names. */
  function isSubsequence(needle, haystack) {
    if (!needle) return true;
    var i = 0;
    for (var j = 0; j < haystack.length && i < needle.length; j++) {
      if (haystack.charCodeAt(j) === needle.charCodeAt(i)) i++;
    }
    return i === needle.length;
  }

  /* Ranks substring hits before subsequence hits so exact matches stay first.
     Returns 2 for substring, 1 for subsequence (4+ chars only), 0 for miss. */
  function fuzzyScore(nameNorm, qNorm) {
    if (nameNorm.indexOf(qNorm) !== -1) return 2;
    if (qNorm.length >= 4 && isSubsequence(qNorm, nameNorm)) return 1;
    return 0;
  }

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      var q = searchInput.value.trim();

      if (searchClear) searchClear.style.display = q.length ? 'flex' : 'none';

      clearTimeout(debounceTimer);

      if (q.length < 2) {
        activeQuery = '';
        cancelInflight();
        hideResults();
        return;
      }

      activeQuery = q;

      /* Exact cache hit — show instantly, skip network entirely */
      if (searchCache.has(q)) {
        cancelInflight();
        renderResults(q, searchCache.get(q));
        return;
      }

      /* Exhaustive-prefix filter: if a shorter prefix returned < PER_PAGE
         results, its set is the complete result for the prefix — narrower
         queries are strict subsets, so we can filter client-side AND skip
         the API. E.g. "beton" returned 3 → "betonc" filters those 3. */
      var prefixHit = findExhaustivePrefix(q);
      if (prefixHit) {
        cancelInflight();
        var filtered = filterByQuery(prefixHit, q);
        cacheResult(q, filtered);
        renderResults(q, filtered);
        return;
      }

      /* Cross-cache substring match: scan every product we've ever seen
         and render any whose name contains the normalized query. Gives
         instant feedback (no spinner) while we still fetch in the
         background, because the pool may be incomplete. */
      var poolMatches = filterPoolByQuery(q);
      if (poolMatches.length) {
        renderResults(q, poolMatches);
      }

      /* Always fetch — the pool may be missing matching products we've
         never pulled. The stale guard inside fetchResults discards any
         response that doesn't match the current activeQuery. */
      debounceTimer = setTimeout(function () { fetchResults(q); }, 300);
    });
  }

  if (searchClear) {
    searchClear.addEventListener('click', function () {
      searchInput.value = '';
      searchClear.style.display = 'none';
      activeQuery = '';
      cancelInflight();
      hideResults();
      searchInput.focus();
    });
  }

  /* Walk backwards through prefixes of `query` looking for a cached result
     that returned fewer than PER_PAGE items (meaning the server gave us ALL
     matches). If found, we can filter client-side — zero network cost. */
  function findExhaustivePrefix(query) {
    for (var len = query.length - 1; len >= 2; len--) {
      var prefix = query.substring(0, len);
      if (searchCache.has(prefix)) {
        var cached = searchCache.get(prefix);
        if (cached.length < PER_PAGE) return cached;
        /* If prefix returned PER_PAGE results, the set may be truncated —
           the server might have more matches for the longer query. Stop. */
        return null;
      }
    }
    return null;
  }

  /* Filter a given product array using fuzzyScore (substring + subsequence
     fallback) and return substring matches before subsequence matches. */
  function filterByQuery(products, query) {
    var qNorm = normalize(query);
    var hits = [];
    products.forEach(function (p) {
      var score = fuzzyScore(normalize(p.name), qNorm);
      if (score > 0) hits.push({ p: p, score: score });
    });
    hits.sort(function (a, b) { return b.score - a.score; });
    return hits.map(function (x) { return x.p; });
  }

  /* Scan every product we've ever seen across any cached query and return
     the ones matching the normalized query (substring or subsequence fallback).
     Capped at PER_PAGE for layout stability — same number of cards as a real
     fetch would render. Substring hits ranked before subsequence hits. */
  function filterPoolByQuery(query) {
    var qNorm = normalize(query);
    var substringHits = [];
    var subsequenceHits = [];
    productPool.forEach(function (p) {
      var score = fuzzyScore(normalize(p.name), qNorm);
      if (score === 2) substringHits.push(p);
      else if (score === 1) subsequenceHits.push(p);
    });
    return substringHits.concat(subsequenceHits).slice(0, PER_PAGE);
  }

  /* Fold a product array into the dedup pool keyed by id. */
  function addToPool(products) {
    if (!products || !products.length) return;
    products.forEach(function (p) {
      if (p && p.id != null) productPool.set(p.id, p);
    });
  }

  function cacheResult(query, products) {
    if (searchCache.size >= CACHE_MAX) {
      searchCache.delete(searchCache.keys().next().value);
    }
    searchCache.set(query, products);
    addToPool(products);
  }

  function cancelInflight() {
    if (activeAbort) {
      activeAbort.abort();
      activeAbort = null;
    }
  }

  function hideResults() {
    if (resultsSection)   resultsSection.style.display = 'none';
    if (noResultsSection) noResultsSection.style.display = 'none';
    if (loadingSection)   loadingSection.style.display = 'none';
  }

  function fetchResults(query) {
    /* Cancel any previous in-flight request */
    cancelInflight();

    activeQuery = query;

    /* Check cache before hitting the network */
    if (searchCache.has(query)) {
      renderResults(query, searchCache.get(query));
      return;
    }

    /* Prefix superset: filter cached shorter-prefix results client-side */
    var prefixHit = findExhaustivePrefix(query);
    if (prefixHit) {
      var filtered = filterByQuery(prefixHit, query);
      cacheResult(query, filtered);
      renderResults(query, filtered);
      return;
    }

    /* If we already have something on-screen from a pool-match pre-render,
       don't flash a spinner over it — just let the fetch settle in the
       background. Only show the spinner when the grid is empty. */
    var showSpinner = !resultsSection || resultsSection.style.display === 'none';
    if (showSpinner) {
      if (loadingSection) loadingSection.style.display = 'block';
      if (resultsSection) resultsSection.style.display = 'none';
      if (noResultsSection) noResultsSection.style.display = 'none';
    }

    var controller = new AbortController();
    activeAbort = controller;

    var url = siteUrl + '/wp-json/wc/store/v1/products?search=' +
      encodeURIComponent(query) + '&per_page=' + PER_PAGE;

    fetch(url, { signal: controller.signal })
      .then(function (r) { return r.json(); })
      .then(function (products) {
        /* Stale guard: if user typed something newer, discard this response */
        if (query !== activeQuery) return;

        var list = products || [];
        cacheResult(query, list);

        /* If the server returned nothing, try to rescue the query:
           1. Pool fallback: fuzzy-match across every product we've cached
              so far (handles typos like "metalic" → "Metallic Stuc Velvet"
              if the pool already contains those products).
           2. Stem retry: the WC Store API uses a strict substring LIKE on
              post_title. A typo like "metalic" returns 0 because "metallic"
              contains no "metalic" substring. Retrying with the first 4 chars
              ("meta") matches the real product; we then fuzzy-filter the
              response client-side to keep only products matching the original
              typed query. One retry max — bounded network cost. */
        if (!list.length) {
          var fallback = filterPoolByQuery(query);
          if (fallback.length) {
            renderResults(query, fallback);
            return;
          }
          if (query.length >= 5) {
            fetchStemAndFilter(query);
            return;
          }
        }

        renderResults(query, list);
      })
      .catch(function (err) {
        /* AbortError is expected when we cancel — not an actual failure */
        if (err && err.name === 'AbortError') return;
        if (loadingSection) loadingSection.style.display = 'none';
      });
  }

  /* Retry-with-shorter-query fallback. Uses a 4-char stem of the original
     query so the WC Store API's strict substring search can find products
     the user actually meant (typos). The response is then fuzzy-filtered
     against the original query so we only show relevant matches.
     Runs at most once per query — fetchResults has already fired the
     original attempt and concluded it returned 0 results. */
  function fetchStemAndFilter(query) {
    var stem = query.slice(0, 4);
    var url  = siteUrl + '/wp-json/wc/store/v1/products?search=' +
      encodeURIComponent(stem) + '&per_page=' + PER_PAGE;

    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (products) {
        /* Stale guard */
        if (query !== activeQuery) return;
        var list = products || [];
        if (list.length) addToPool(list);
        var matched = filterByQuery(list, query);
        if (matched.length) {
          cacheResult(query, matched);
          renderResults(query, matched);
        } else {
          cacheResult(query, []);
          renderResults(query, []);
        }
      })
      .catch(function () {
        renderResults(query, []);
      });
  }

  function renderResults(query, products) {
    if (loadingSection) loadingSection.style.display = 'none';

    if (!products || !products.length) {
      if (noResultsSection) {
        noResultsSection.style.display = 'block';
        if (queryText) queryText.textContent = query;
      }
      if (resultsSection) resultsSection.style.display = 'none';
      return;
    }

    resultsGrid.innerHTML = '';
    products.forEach(function (p) {
      var img = (p.images && p.images[0]) ? p.images[0].src : '';
      var price = p.prices
        ? (p.prices.price
          ? '&euro;' + (parseInt(p.prices.price, 10) / 100).toFixed(2).replace('.', ',')
          : '')
        : '';

      var card = document.createElement('a');
      card.href = p.permalink || '#';
      card.className = 'oz-search-drawer__product';
      card.innerHTML =
        '<div class="oz-search-drawer__product-img">' +
          (img ? '<img src="' + img + '" alt="" loading="lazy">' : '') +
        '</div>' +
        '<div class="oz-search-drawer__product-title">' + (p.name || '') + '</div>' +
        '<div class="oz-search-drawer__product-price">' + price + '</div>';
      resultsGrid.appendChild(card);
    });

    if (resultsSection) resultsSection.style.display = 'block';
    if (noResultsSection) noResultsSection.style.display = 'none';
    if (viewAllLink) {
      viewAllLink.href = siteUrl + '/?s=' + encodeURIComponent(query) + '&post_type=product';
    }

    saveRecent(query);
  }

  /* ── Recent searches (localStorage) ── */
  var RECENT_KEY = 'oz_recent_searches';
  var MAX_RECENT = 5;

  function getRecent() {
    try {
      return JSON.parse(localStorage.getItem(RECENT_KEY)) || [];
    } catch (e) { return []; }
  }

  function saveRecent(q) {
    var list = getRecent().filter(function (s) { return s !== q; });
    list.unshift(q);
    if (list.length > MAX_RECENT) list = list.slice(0, MAX_RECENT);
    localStorage.setItem(RECENT_KEY, JSON.stringify(list));
  }

  function clearRecent() {
    localStorage.removeItem(RECENT_KEY);
    renderRecent();
  }

  function renderRecent() {
    var list = getRecent();
    if (!recentSection || !recentList) return;

    if (!list.length) {
      recentSection.style.display = 'none';
      return;
    }

    recentList.innerHTML = '';
    list.forEach(function (q) {
      var li = document.createElement('li');
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = q;
      btn.addEventListener('click', function () {
        searchInput.value = q;
        if (searchClear) searchClear.style.display = 'flex';
        fetchResults(q);
      });
      li.appendChild(btn);
      recentList.appendChild(li);
    });

    recentSection.style.display = 'block';
  }

  if (clearRecentBtn) clearRecentBtn.addEventListener('click', clearRecent);

  /* ── Form submit: save search and let the form navigate ── */
  var searchForm = document.querySelector('.oz-search-drawer__form');
  if (searchForm) {
    searchForm.addEventListener('submit', function () {
      var q = searchInput.value.trim();
      if (q.length >= 2) saveRecent(q);
    });
  }

  /* ── Mega menu: keyboard + touch support ──
     CSS :hover/:focus-within handles mouse. This adds:
     - Escape closes mega panel
     - Touch devices: first tap opens, second tap navigates */
  var megaItems = document.querySelectorAll('.oz-nav__item.has-mega');

  /* Escape closes mega panel (.is-mega-closed overrides :focus-within in CSS) */
  megaItems.forEach(function (item) {
    item.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        item.classList.add('is-mega-closed');
        var link = item.querySelector('.oz-nav__link');
        if (link) link.focus();
      }
    });

    /* Clear the closed state when mouse re-enters or focus leaves entirely */
    item.addEventListener('mouseenter', function () {
      item.classList.remove('is-mega-closed');
    });
    item.addEventListener('focusout', function (e) {
      if (!item.contains(e.relatedTarget)) {
        item.classList.remove('is-mega-closed');
      }
    });
  });

  /* Touch-friendly: toggle mega on first tap, navigate on second.
     Uses pointer:coarse to avoid catching hybrid laptops with touchscreens. */
  var isCoarsePointer = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
  if (isCoarsePointer) {
    megaItems.forEach(function (item) {
      var link = item.querySelector('.oz-nav__link');
      if (!link) return;
      link.addEventListener('click', function (e) {
        if (item.classList.contains('is-mega-open')) return; /* second tap navigates */
        e.preventDefault();
        megaItems.forEach(function (o) { o.classList.remove('is-mega-open'); });
        item.classList.add('is-mega-open');
      });
    });

    document.addEventListener('click', function (e) {
      if (!e.target.closest('.oz-nav__item.has-mega')) {
        megaItems.forEach(function (item) { item.classList.remove('is-mega-open'); });
      }
    });
  }

})();
