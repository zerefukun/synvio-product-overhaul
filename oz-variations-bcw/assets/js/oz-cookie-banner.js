/**
 * OZ Cookie / Privacy Banner Delay
 *
 * Hides Cookiebot and other privacy banners for 30 seconds of inactivity.
 * Any user interaction (scroll, touch, click) resets the 30s timer.
 * After 30s of no interaction, banners become visible again.
 *
 * Requires the CSS class .oz-hide-banners to hide the banner elements.
 * Completely independent of the product page — loads on all pages.
 *
 * @package OZ_Variations_BCW
 * @since 1.1.0
 */

(function () {
  'use strict';

  document.body.classList.add('oz-hide-banners');

  var timer = setTimeout(function () {
    document.body.classList.remove('oz-hide-banners');
  }, 30000);

  // Reset timer on any user interaction — banners only show after 30s of inactivity
  var resetTimer = function () {
    clearTimeout(timer);
    timer = setTimeout(function () {
      document.body.classList.remove('oz-hide-banners');
    }, 30000);
  };

  document.addEventListener('scroll', resetTimer, { passive: true });
  document.addEventListener('touchstart', resetTimer, { passive: true });
  document.addEventListener('click', resetTimer);
})();
