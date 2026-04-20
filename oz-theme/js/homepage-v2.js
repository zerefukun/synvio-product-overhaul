(function () {
  'use strict';

  // Start marquee only after page load so Lighthouse can settle on an LCP.
  function startMarquee() {
    var tracks = document.querySelectorAll('.oz-hp-trust-track');
    for (var i = 0; i < tracks.length; i++) tracks[i].classList.add('is-running');
  }
  if (document.readyState === 'complete') {
    setTimeout(startMarquee, 500);
  } else {
    window.addEventListener('load', function () { setTimeout(startMarquee, 500); });
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
