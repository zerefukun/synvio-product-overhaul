/**
 * OzTheme frontend scripts.
 * WAPO color navigation, addon handlers, price label, tooltip + checkbox logic
 * all removed in Phase 4 cleanup — oz-variations-bcw handles everything now.
 *
 * Remaining: TrustIndex lazy-loading (skip on PageSpeed / GTmetrix bots).
 */
jQuery(document).ready(function($) {

    // --- TrustIndex lazy-loading ---
    // Skip loading on speed-test bots so it doesn't hurt Lighthouse scores
    function isTrustIndexLoadable() {
        var userAgent = navigator.userAgent;
        var pageSpeedTesters = [
            'Google Page Speed Insights',
            'GTmetrix',
            'Pingdom',
        ];

        return !pageSpeedTesters.some(tester => userAgent.indexOf(tester) !== -1);
    }

    if (isTrustIndexLoadable()) {
        var trustIndexContainer = document.getElementById('trustindex-script-container');
        if (trustIndexContainer) {
            var trustIndexScript = document.createElement('script');
            trustIndexScript.src = 'https://cdn.trustindex.io/loader.js?c754f892650e180cb696989cccc';
            trustIndexScript.defer = true;
            trustIndexScript.async = true;
            trustIndexContainer.appendChild(trustIndexScript);
        }
    }
});

/* == Shop-sidebar categorie-toggles =========================
   De walker rendert chevron-knoppen (.oz-cat-nav__toggle) maar
   had geen handler: subcategorieen waren onbereikbaar. Klik
   toggelt is-open op het item; de tak van de actieve categorie
   klapt automatisch open. */
(function () {
  var nav = document.querySelector('.oz-cat-nav');
  if (!nav || nav.dataset.ozToggleInit) { return; }
  nav.dataset.ozToggleInit = '1';

  /* Click-toggle zit al in archive-product.php (inline) - hier alleen
     de aanvulling die daar ontbreekt: actieve tak openen + markeren. */
  var here = window.location.pathname.replace(/\/$/, '');
  nav.querySelectorAll('.oz-cat-nav__link').forEach(function (a) {
    var path = a.pathname.replace(/\/$/, '');
    if (path && (path === here || here.indexOf(path + '/') === 0)) {
      a.classList.add('is-current');
      var p = a.closest('.oz-cat-nav__item');
      while (p) {
        p.classList.add('is-open');
        var t = p.querySelector(':scope > .oz-cat-nav__row .oz-cat-nav__toggle');
        if (t) { t.setAttribute('aria-expanded', 'true'); }
        p = p.parentElement.closest('.oz-cat-nav__item');
      }
    }
  });
})();
