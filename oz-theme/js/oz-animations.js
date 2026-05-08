/**
 * OZ Animations — Scroll-reveal observer
 *
 * Loaded on all frontend pages. Watches three attribute types:
 *   [data-reveal]         — fade + slide up
 *   [data-reveal-stagger] — staggered children
 *   [data-reveal-img]     — clip-path image reveal
 *
 * Adds .oz-visible (theme standard) AND .is-visible (homepage legacy compat)
 * when elements enter the viewport (one-shot).
 * Respects prefers-reduced-motion.
 */
(function () {
  var SELECTORS = '[data-reveal],[data-reveal-stagger],[data-reveal-img]';
  var els = document.querySelectorAll(SELECTORS);
  if (!els.length) return;

  function reveal(el) {
    el.classList.add('oz-visible');
    el.classList.add('is-visible');
  }

  /* Reduced motion — show everything immediately */
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    for (var i = 0; i < els.length; i++) reveal(els[i]);
    return;
  }

  /* Fallback for browsers without IntersectionObserver */
  if (!('IntersectionObserver' in window)) {
    for (var j = 0; j < els.length; j++) reveal(els[j]);
    return;
  }

  /* Fire as soon as ANY pixel of the element crosses a virtual line
     that sits 12% of the viewport BELOW its real bottom edge.
     Positive bottom rootMargin extends the root downward, so the
     reveal fires *before* the section is actually scrolled to,
     and content lands settled by the time the user reaches it.
     Threshold 0 = first pixel of intersection is enough. */
  var observer = new IntersectionObserver(function (entries) {
    for (var k = 0; k < entries.length; k++) {
      if (entries[k].isIntersecting) {
        reveal(entries[k].target);
        observer.unobserve(entries[k].target);
      }
    }
  }, {
    threshold: 0,
    rootMargin: '0px 0px 12% 0px'
  });

  for (var m = 0; m < els.length; m++) observer.observe(els[m]);
})();
