/**
 * Shared Swiper v11 CDN loader — deduplicates across all consumers.
 * Both cart-drawer.js and product-page.js call window.ozLoadSwiper(callback)
 * to lazily load Swiper only once, regardless of which triggers first.
 *
 * Enqueued before any consumer via functions.php.
 */
(function () {
    var _loaded = false;
    var _failed = false;
    var _callbacks = [];

    window.ozLoadSwiper = function (callback) {
        if (_failed) return;
        if (_loaded) { callback(); return; }
        _callbacks.push(callback);
        if (_callbacks.length > 1) return; // already loading

        // CSS
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css';
        document.head.appendChild(link);

        // JS
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js';
        script.onload = function () {
            _loaded = true;
            for (var i = 0; i < _callbacks.length; i++) _callbacks[i]();
            _callbacks = [];
        };
        script.onerror = function () {
            _failed = true;
            _callbacks = [];
        };
        document.head.appendChild(script);
    };
})();
