(function () {
  'use strict';

  // Start marquee only after the browser is truly idle. Continuous animations
  // prevent Lighthouse from finding the "quiet window" it needs to record an
  // LCP, so we hold the animation paused until rIC fires (or 3s fallback).
  function startMarquee() {
    var tracks = document.querySelectorAll('.oz-hp-trust-track');
    for (var i = 0; i < tracks.length; i++) tracks[i].classList.add('is-running');
  }
  function scheduleMarquee() {
    if ('requestIdleCallback' in window) {
      window.requestIdleCallback(startMarquee, { timeout: 4000 });
    } else {
      setTimeout(startMarquee, 3000);
    }
  }
  if (document.readyState === 'complete') {
    scheduleMarquee();
  } else {
    window.addEventListener('load', scheduleMarquee);
  }

  var wraps = document.querySelectorAll('.oz-hp-kb-wrap');
  if (!wraps.length) return;

  wraps.forEach(function (wrap) {
    var carousel = wrap.querySelector('.oz-hp-kb-carousel');
    var prev = wrap.querySelector('.oz-hp-kb-nav--prev');
    var next = wrap.querySelector('.oz-hp-kb-nav--next');
    if (!carousel || !prev || !next) return;

    function step() {
      var card = carousel.querySelector('.oz-hp-kb-card');
      if (!card) return 300;
      var styles = window.getComputedStyle(carousel);
      var gap = parseFloat(styles.columnGap || styles.gap) || 20;
      return card.getBoundingClientRect().width + gap;
    }

    var maxScroll = 0;

    function recalcBounds() {
      maxScroll = carousel.scrollWidth - carousel.clientWidth - 1;
    }

    function updateState() {
      var pos = carousel.scrollLeft;
      prev.disabled = pos <= 0;
      next.disabled = pos >= maxScroll;
    }

    function scrollByDir(dir) {
      carousel.scrollBy({ left: step() * dir, behavior: 'smooth' });
    }

    prev.addEventListener('click', function () { scrollByDir(-1); });
    next.addEventListener('click', function () { scrollByDir(1); });
    carousel.addEventListener('scroll', updateState, { passive: true });
    window.addEventListener('resize', function () {
      recalcBounds();
      updateState();
    });
    recalcBounds();
    updateState();
  });
})();
