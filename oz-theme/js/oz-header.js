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

  /* Sub-sub (grandchildren) accordion inside a drill-down panel */
  var grandchildToggles = document.querySelectorAll('.oz-menu-drawer__subcategory-toggle');
  grandchildToggles.forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var li = btn.closest('.has-grandchildren');
      if (!li) return;
      var expanded = li.classList.toggle('is-expanded');
      btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    });
  });

  /* ── Search drawer ── */
  function openSearch() {
    searchDrawer.classList.add('is-open');
    searchDrawer.setAttribute('aria-hidden', 'false');
    lockBody();
    renderRecent();
    warmPool();
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

  /* Levenshtein edit distance: minimum single-character edits (insert, delete,
     substitute) to transform `a` into `b`. Classic DP with a rolling row so
     memory stays O(min(a,b)). Used to rank product tokens against the query —
     e.g. "metalic" vs "metallic" = 1 edit, so it ranks just below an exact
     match. Short-circuits when both strings are equal. */
  function levenshtein(a, b) {
    if (a === b) return 0;
    if (!a.length) return b.length;
    if (!b.length) return a.length;
    var prev = new Array(b.length + 1);
    for (var j = 0; j <= b.length; j++) prev[j] = j;
    for (var i = 1; i <= a.length; i++) {
      var curr = [ i ];
      for (var k = 1; k <= b.length; k++) {
        var cost = a.charCodeAt(i - 1) === b.charCodeAt(k - 1) ? 0 : 1;
        curr[k] = Math.min(curr[k - 1] + 1, prev[k] + 1, prev[k - 1] + cost);
      }
      prev = curr;
    }
    return prev[b.length];
  }

  /* Max allowed edit distance scales with query length. Short queries ("fox")
     get 0 edits to avoid over-matching; medium ("metal") get 1; long
     ("metallic"+) get 2. Keeps fuzzy results relevant, not noisy. */
  function maxEdits(qLen) {
    if (qLen <= 3) return 0;
    if (qLen <= 6) return 1;
    return 2;
  }

  /* Split a normalized product name into word tokens. "metallic velvet 4m2"
     → ["metallic", "velvet", "4m2"]. Matching per-token lets a typo in a
     single word score well even when the full name is long. */
  function tokenize(nameNorm) {
    return nameNorm.split(/[^a-z0-9]+/).filter(Boolean);
  }

  /* Subsequence gap scorer — catches dropped-letter typos that Levenshtein
     at limit=1 misses. "metlic" is a subsequence of "metallic" (every letter
     in order), with 2 internal gaps (the 'a' and second 'l'). Returns the
     gap count (lower = better), or Infinity if not a valid subsequence or
     a guard fails.

     Guards (all must pass, else Infinity):
       - query length ≥ 5          (shorter words over-match: "red" → "reduced")
       - token.length ≤ 1.7 × query.length  (prevent "red" → "reduction")
       - first char must match     (anchors the match, cheap rejection)
       - gaps ≤ 2                  (2+ skipped letters = probably a different word)
     Trailing token chars after the query is consumed are NOT counted —
     natural suffixes ("-en", "-s") aren't typos. */
  function subsequenceGaps(query, token) {
    if (query.length < 5) return Infinity;
    if (token.length > query.length * 1.7) return Infinity;
    if (query.charCodeAt(0) !== token.charCodeAt(0)) return Infinity;

    var qi = 0, gaps = 0, lastIdx = -1;
    for (var j = 0; j < token.length; j++) {
      if (token.charCodeAt(j) === query.charCodeAt(qi)) {
        if (lastIdx >= 0) gaps += (j - lastIdx - 1);
        lastIdx = j;
        qi++;
        if (qi >= query.length) break;
      }
    }
    if (qi < query.length) return Infinity;
    if (gaps > 2) return Infinity;
    return gaps;
  }

  /* Score a product name against the query. Lower is better.
       0     — query is a substring of the full name OR a prefix of any token
               (e.g. "metal" inside "metallic" → strong match)
       0.5-n — subsequence hit on any token (e.g. "metlic" in "metallic"),
               tiered as 0.5 + gaps * 0.2 so these rank BETWEEN exact hits
               and 1-edit Levenshtein hits
       1-n   — min Levenshtein distance between query and any token, up to maxEdits
     Returns Infinity when nothing's close enough. Because callers rank by
     ascending score, substring/prefix hits always outrank fuzzy hits. */
  function scoreProduct(nameNorm, qNorm) {
    if (!qNorm) return Infinity;
    if (nameNorm.indexOf(qNorm) !== -1) return 0;

    var tokens   = tokenize(nameNorm);
    var limit    = maxEdits(qNorm.length);
    var bestLev  = Infinity;
    var bestSub  = Infinity;

    for (var i = 0; i < tokens.length; i++) {
      var t = tokens[i];
      if (t.indexOf(qNorm) === 0) return 0;

      /* Subsequence check — independent of Levenshtein window */
      var g = subsequenceGaps(qNorm, t);
      if (g < bestSub) bestSub = g;

      /* Levenshtein — skip tokens whose length is too different. A valid
         edit distance ≤ limit requires |len(a) - len(b)| ≤ limit. */
      if (Math.abs(t.length - qNorm.length) <= limit) {
        var d = levenshtein(t, qNorm);
        if (d < bestLev) bestLev = d;
      }
    }

    if (bestLev <= limit) return bestLev;
    if (bestSub !== Infinity) return 0.5 + bestSub * 0.2;
    return Infinity;
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

  /* Collapse scored hits into a final ranked list, with a noise guard:
     if there are already 3+ "hard" hits (score ≤ 0, exact substring/prefix),
     drop all fuzzy hits (> 0). Reserves typo tolerance for when real
     matches are thin. */
  var HARD_HIT_THRESHOLD = 3;
  function finalizeScored(scored) {
    var hardCount = 0;
    for (var i = 0; i < scored.length; i++) {
      if (scored[i].s <= 0) hardCount++;
    }
    scored.sort(function (a, b) { return a.s - b.s; });
    if (hardCount >= HARD_HIT_THRESHOLD) {
      scored = scored.filter(function (x) { return x.s <= 0; });
    }
    return scored.map(function (x) { return x.p; });
  }

  /* Filter + rank a product array by closeness to the query. Lower scoreProduct
     result = better match, so ascending sort puts exact/prefix hits first,
     then typo-tolerant hits ranked by edit distance. */
  function filterByQuery(products, query) {
    var qNorm = normalize(query);
    var scored = [];
    products.forEach(function (p) {
      var s = scoreProduct(normalize(p.name), qNorm);
      if (s !== Infinity) scored.push({ p: p, s: s });
    });
    return finalizeScored(scored);
  }

  /* Scan every product we've ever seen across any cached query, rank by
     closeness, return the top PER_PAGE. Same fuzzy scorer as filterByQuery
     — pool-match rendering stays consistent with API-match rendering. */
  function filterPoolByQuery(query) {
    var qNorm = normalize(query);
    var scored = [];
    productPool.forEach(function (p) {
      var s = scoreProduct(normalize(p.name), qNorm);
      if (s !== Infinity) scored.push({ p: p, s: s });
    });
    return finalizeScored(scored).slice(0, PER_PAGE);
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

  /* Pre-fetch a broad set of products once per session so the fuzzy scorer
     has real data to rank against on the very first keystroke. Without this,
     a fresh visitor typing "metalic" would get an empty API response (WC
     Store API uses substring LIKE, can't match typos) and no pool to fall
     back on. A single 100-item fetch costs one request but makes first-query
     typo tolerance work instantly. */
  var poolWarmed = false;
  function warmPool() {
    if (poolWarmed) return;
    poolWarmed = true;
    var url = siteUrl + '/wp-json/wc/store/v1/products?per_page=100&orderby=popularity';
    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (products) {
        if (products && products.length) addToPool(products);
      })
      .catch(function () {
        /* Let the next drawer-open retry on failure */
        poolWarmed = false;
      });
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
