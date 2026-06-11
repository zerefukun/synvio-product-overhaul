/* page-ruimte-v2.js
   Drag-to-compare voor/na slider for ruimte/betonlook/betonstuc pages.

   Markup contract (built by tools/migrate_full.py build_S06_voorna):
     <div class="oz-rp2-vn-slider" data-vn-slider>
       <img class="oz-rp2-vn-after" .../>
       <div class="oz-rp2-vn-before-clip" data-vn-clip>
         <img class="oz-rp2-vn-before" .../>
       </div>
       <span class="oz-rp2-vn-label oz-rp2-vn-label-voor">Voor</span>
       <span class="oz-rp2-vn-label oz-rp2-vn-label-na">Na</span>
       <button class="oz-rp2-vn-handle" data-vn-handle> ... </button>
     </div>

   Behavior: pointer drag updates --vn-pos (0%-100%) on the slider element.
   The CSS (page-ruimte-v2.css) reads --vn-pos to set clip-path on the
   before image and the left position of the handle. */
(function () {
  "use strict";

  function init(slider) {
    if (slider.dataset.vnInited === "1") return;
    slider.dataset.vnInited = "1";
    var dragging = false;

    function setPos(clientX) {
      var rect = slider.getBoundingClientRect();
      var pct = ((clientX - rect.left) / rect.width) * 100;
      if (pct < 0) pct = 0;
      if (pct > 100) pct = 100;
      slider.style.setProperty("--vn-pos", pct + "%");
    }

    function onPointerDown(e) {
      dragging = true;
      slider.setPointerCapture && slider.setPointerCapture(e.pointerId);
      setPos(e.clientX);
      e.preventDefault();
    }
    function onPointerMove(e) {
      if (!dragging) return;
      setPos(e.clientX);
    }
    function onPointerUp() {
      dragging = false;
    }

    slider.addEventListener("pointerdown", onPointerDown);
    slider.addEventListener("pointermove", onPointerMove);
    slider.addEventListener("pointerup", onPointerUp);
    slider.addEventListener("pointercancel", onPointerUp);
    slider.addEventListener("pointerleave", onPointerUp);

    /* Keyboard support: arrow keys move 5% per press on the handle. */
    var handle = slider.querySelector("[data-vn-handle]");
    if (handle) {
      handle.addEventListener("keydown", function (e) {
        var current = parseFloat(getComputedStyle(slider).getPropertyValue("--vn-pos")) || 50;
        var next = current;
        if (e.key === "ArrowLeft")  next = Math.max(0, current - 5);
        if (e.key === "ArrowRight") next = Math.min(100, current + 5);
        if (e.key === "Home")       next = 0;
        if (e.key === "End")        next = 100;
        if (next !== current) {
          slider.style.setProperty("--vn-pos", next + "%");
          e.preventDefault();
        }
      });
    }
  }

  function boot() {
    var sliders = document.querySelectorAll("[data-vn-slider]");
    for (var i = 0; i < sliders.length; i++) init(sliders[i]);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();

/* ============================================================
   Bottom-nav (oz-rp2-bottomnav): scroll-spy + scroll-direction-hide.
   Show alleen bij scroll-naar-boven, hide bij scroll-naar-beneden,
   altijd hide in de bovenste ~220px (= ruimte voor de hero).
   CSS in page-ruimte-v2.css.
   ============================================================ */
(function () {
  "use strict";

  function init() {
    var nav = document.querySelector(".oz-rp2-bottomnav");
    if (!nav) return;
    var inner = nav.querySelector(".oz-rp2-bottomnav-inner");
    var pills = nav.querySelectorAll(".oz-rp2-bottomnav-pill");
    if (!pills.length) return;

    /* Targets: section-elements waarvan het id overeenkomt met een pill. */
    var targets = [];
    pills.forEach(function (p) {
      var id = p.getAttribute("data-target");
      var el = id ? document.getElementById(id) : null;
      if (el) targets.push({ id: id, el: el, pill: p });
    });
    if (!targets.length) return;

    /* Pill click → smooth scroll met header-offset. */
    pills.forEach(function (p) {
      p.addEventListener("click", function (e) {
        var id = p.getAttribute("data-target");
        var el = id ? document.getElementById(id) : null;
        if (!el) return;
        e.preventDefault();
        var top = el.getBoundingClientRect().top + window.scrollY - 80;
        window.scrollTo({ top: top, behavior: "smooth" });
        history.replaceState(null, "", "#" + id);
      });
    });

    function setActive(id) {
      pills.forEach(function (p) {
        p.classList.toggle("is-active", p.getAttribute("data-target") === id);
      });
      /* Active pill in beeld scrollen binnen de horizontale pill-rij. */
      var active = nav.querySelector(".oz-rp2-bottomnav-pill.is-active");
      if (active && inner) {
        var r = active.getBoundingClientRect();
        var n = inner.getBoundingClientRect();
        if (r.left < n.left + 20 || r.right > n.right - 20) {
          active.scrollIntoView({ inline: "center", block: "nearest", behavior: "smooth" });
        }
      }
    }

    var HIDE_BELOW = 220;     /* px-from-top waaronder de nav altijd verborgen blijft */
    var DELTA_MIN  = 4;       /* anti-jitter drempel voor scroll-richting */
    var lastY  = window.scrollY;
    var shown  = false;
    var queued = false;

    function update() {
      queued = false;
      var y = window.scrollY;
      var delta = y - lastY;

      if (y < HIDE_BELOW) {
        if (shown) { nav.classList.remove("is-shown"); shown = false; }
      } else if (delta > DELTA_MIN) {
        if (shown) { nav.classList.remove("is-shown"); shown = false; }
      } else if (delta < -DELTA_MIN) {
        if (!shown) { nav.classList.add("is-shown"); shown = true; }
      }
      lastY = y;

      /* Scroll-spy: laatste section waarvan de top boven probe-line ligt. */
      var probe = y + 100;
      var hit = null;
      for (var i = 0; i < targets.length; i++) {
        var top = targets[i].el.getBoundingClientRect().top + window.scrollY;
        if (top <= probe) hit = targets[i].id;
      }
      if (hit) setActive(hit);
    }

    function onScroll() {
      if (queued) return;
      queued = true;
      requestAnimationFrame(update);
    }

    window.addEventListener("scroll", onScroll, { passive: true });
    update();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
