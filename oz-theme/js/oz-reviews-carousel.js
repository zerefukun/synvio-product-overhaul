(function () {
    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    function initOne(wrap) {
        var swiperEl = wrap.querySelector('.oz-hp-reviews-swiper');
        var prev = wrap.querySelector('.oz-hp-reviews-nav--prev');
        var next = wrap.querySelector('.oz-hp-reviews-nav--next');
        if (!swiperEl) return;

        new Swiper(swiperEl, {
            slidesPerView: 1.1,
            spaceBetween: 16,
            grabCursor: true,
            navigation: { prevEl: prev, nextEl: next },
            breakpoints: {
                640:  { slidesPerView: 2,   spaceBetween: 20 },
                1024: { slidesPerView: 3,   spaceBetween: 24 },
            },
        });
    }

    ready(function () {
        var wraps = document.querySelectorAll('.oz-hp-reviews-carousel');
        if (!wraps.length) return;
        if (typeof window.ozLoadSwiper !== 'function') return;
        window.ozLoadSwiper(function () {
            wraps.forEach(initOne);
        });
    });
})();
