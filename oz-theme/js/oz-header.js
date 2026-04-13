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

  if (searchTrigger) searchTrigger.addEventListener('click', openSearch);
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
    document.body.classList.add('oz-header-overlay-page');
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

  /* ── Predictive search ── */
  var debounceTimer = null;
  var siteUrl = (typeof ozHeaderData !== 'undefined' && ozHeaderData.siteUrl)
    ? ozHeaderData.siteUrl
    : window.location.origin;

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      var q = searchInput.value.trim();

      /* Show/hide clear button */
      if (searchClear) searchClear.style.display = q.length ? 'flex' : 'none';

      clearTimeout(debounceTimer);

      if (q.length < 2) {
        hideResults();
        return;
      }

      debounceTimer = setTimeout(function () { fetchResults(q); }, 300);
    });
  }

  if (searchClear) {
    searchClear.addEventListener('click', function () {
      searchInput.value = '';
      searchClear.style.display = 'none';
      hideResults();
      searchInput.focus();
    });
  }

  function hideResults() {
    if (resultsSection)   resultsSection.style.display = 'none';
    if (noResultsSection) noResultsSection.style.display = 'none';
    if (loadingSection)   loadingSection.style.display = 'none';
  }

  function fetchResults(query) {
    if (loadingSection) loadingSection.style.display = 'block';
    if (resultsSection) resultsSection.style.display = 'none';
    if (noResultsSection) noResultsSection.style.display = 'none';

    /* Use WC REST API v3 product search */
    var url = siteUrl + '/wp-json/wc/store/v1/products?search=' +
      encodeURIComponent(query) + '&per_page=6';

    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (products) {
        if (loadingSection) loadingSection.style.display = 'none';

        if (!products || !products.length) {
          if (noResultsSection) {
            noResultsSection.style.display = 'block';
            if (queryText) queryText.textContent = query;
          }
          return;
        }

        /* Render product cards */
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
        if (viewAllLink) {
          viewAllLink.href = siteUrl + '/?s=' + encodeURIComponent(query) + '&post_type=product';
        }

        /* Save to recent */
        saveRecent(query);
      })
      .catch(function () {
        if (loadingSection) loadingSection.style.display = 'none';
      });
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

})();
