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

  /* Each kb-carousel init reads scrollWidth/clientWidth/scrollLeft,
     which forces a synchronous layout. PSI flagged ~112ms of forced
     reflow here. The state we need for nav buttons (disabled flags)
     is purely cosmetic on first paint, so we can defer the whole
     init until the browser is idle. Listeners get attached at the
     same time, but on a fresh page the user won't be clicking the
     prev/next buttons within the first idle window anyway. */
  function initKbWraps() {
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
  }

  if ('requestIdleCallback' in window) {
    window.requestIdleCallback(initKbWraps, { timeout: 2000 });
  } else {
    window.requestAnimationFrame(initKbWraps);
  }
})();

/* ============================================================
   Voor/na sectie (S06): twee slider-varianten op de homepage.

   1) Drag-compare voor/na:
        <div class="oz-vn-drag" data-vn-drag>
          <img class="oz-vn-drag-base">          ← "na" foto (achtergrond)
          <div class="oz-vn-drag-overlay">       ← "voor" foto (overlay)
            <img>
          </div>
          <div class="oz-vn-drag-handle">        ← visuele handle (pointer-events none)
            <div class="oz-vn-drag-handle-knob">‹ ›</div>
          </div>
        </div>
      → pointer drag past overlay-width en handle-left aan.

   2) Stappen-stepper:
        <div class="oz-vn-stages" data-vn-stages>
          <img class="oz-vn-stage is-active">    ← stap 1 (zichtbaar)
          <img class="oz-vn-stage">              ← stap 2..N
          <div class="oz-vn-stages-dots">
            <span class="is-active"></span><span></span>...
          </div>
        </div>
      → auto-rotate elke 4s, dots klikbaar, pauze bij hover.

   Beide markup-patronen staan in front-page.php (S06 sectie). Deze JS
   is bewust gescheiden van de page-ruimte-v2.js variant (.oz-rp2-vn-slider)
   omdat de class-namen en DOM-structuur verschillen.
   ============================================================ */
(function () {
  'use strict';

  /* ---- Drag-compare voor/na ---- */
  function initDrag(slider) {
    if (slider.dataset.vnInited === '1') return;
    slider.dataset.vnInited = '1';

    var overlay = slider.querySelector('.oz-vn-drag-overlay');
    var handle  = slider.querySelector('.oz-vn-drag-handle');
    if (!overlay || !handle) return;

    var dragging = false;

    /* Positie via --vn-pos custom property. CSS clipt de full-size overlay
       met clip-path en zet de handle op left: var(--vn-pos) — beide foto's
       renderen daardoor altijd identiek (geen width-based misalignment). */
    function setPos(clientX) {
      var rect = slider.getBoundingClientRect();
      var pct = ((clientX - rect.left) / rect.width) * 100;
      if (pct < 0)   pct = 0;
      if (pct > 100) pct = 100;
      slider.style.setProperty('--vn-pos', pct + '%');
    }

    slider.addEventListener('pointerdown', function (e) {
      dragging = true;
      if (slider.setPointerCapture) {
        try { slider.setPointerCapture(e.pointerId); } catch (err) {}
      }
      setPos(e.clientX);
      e.preventDefault();
    });
    slider.addEventListener('pointermove', function (e) {
      if (!dragging) return;
      setPos(e.clientX);
    });
    function stopDrag() { dragging = false; }
    slider.addEventListener('pointerup',     stopDrag);
    slider.addEventListener('pointercancel', stopDrag);
    slider.addEventListener('pointerleave',  stopDrag);

    /* Keyboard: ‹/› arrows op de slider zelf voor a11y. */
    slider.setAttribute('tabindex', '0');
    slider.addEventListener('keydown', function (e) {
      var current = parseFloat(slider.style.getPropertyValue('--vn-pos')) || 50;
      var next = current;
      if (e.key === 'ArrowLeft')  next = Math.max(0, current - 5);
      if (e.key === 'ArrowRight') next = Math.min(100, current + 5);
      if (e.key === 'Home')       next = 0;
      if (e.key === 'End')        next = 100;
      if (next !== current) {
        slider.style.setProperty('--vn-pos', next + '%');
        e.preventDefault();
      }
    });
  }

  /* ---- Stappen-stepper ---- */
  function initStages(stages) {
    if (stages.dataset.vnInited === '1') return;
    stages.dataset.vnInited = '1';

    var imgs = stages.querySelectorAll('.oz-vn-stage');
    var dots = stages.querySelectorAll('.oz-vn-stages-dots span');
    if (!imgs.length || !dots.length || imgs.length !== dots.length) return;

    var idx = 0;
    var ROTATE_MS = 4000;
    var timer = null;

    function show(i) {
      idx = ((i % imgs.length) + imgs.length) % imgs.length;
      for (var j = 0; j < imgs.length; j++) {
        imgs[j].classList.toggle('is-active', j === idx);
        dots[j].classList.toggle('is-active', j === idx);
      }
    }

    function startTimer() {
      stopTimer();
      timer = setInterval(function () { show(idx + 1); }, ROTATE_MS);
    }
    function stopTimer() {
      if (timer) { clearInterval(timer); timer = null; }
    }

    for (var k = 0; k < dots.length; k++) {
      (function (j) {
        dots[j].style.cursor = 'pointer';
        dots[j].setAttribute('role', 'button');
        dots[j].setAttribute('tabindex', '0');
        dots[j].addEventListener('click', function () { show(j); startTimer(); });
        dots[j].addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); show(j); startTimer(); }
        });
      })(k);
    }

    stages.addEventListener('mouseenter', stopTimer);
    stages.addEventListener('mouseleave', startTimer);

    /* Respect prefers-reduced-motion — geen auto-rotate dan. */
    var rm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
    if (!rm || !rm.matches) {
      startTimer();
    }
  }

  function bootVn() {
    var dragSliders   = document.querySelectorAll('[data-vn-drag]');
    var stageSliders  = document.querySelectorAll('[data-vn-stages]');
    for (var i = 0; i < dragSliders.length;  i++) initDrag(dragSliders[i]);
    for (var j = 0; j < stageSliders.length; j++) initStages(stageSliders[j]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootVn);
  } else {
    bootVn();
  }
})();
