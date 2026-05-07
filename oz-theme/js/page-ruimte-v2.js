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
