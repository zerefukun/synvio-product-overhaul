/* OZ Variations BCW — Built by esbuild. Do not edit. Source: src/js/ */
(() => {
  // src/js/cookie-banner.js
  (function() {
    "use strict";
    document.body.classList.add("oz-hide-banners");
    var timer = setTimeout(function() {
      document.body.classList.remove("oz-hide-banners");
    }, 3e4);
    var resetTimer = function() {
      clearTimeout(timer);
      timer = setTimeout(function() {
        document.body.classList.remove("oz-hide-banners");
      }, 3e4);
    };
    document.addEventListener("scroll", resetTimer, { passive: true });
    document.addEventListener("touchstart", resetTimer, { passive: true });
    document.addEventListener("click", resetTimer);
  })();
})();
//# sourceMappingURL=oz-cookie-banner.js.map
