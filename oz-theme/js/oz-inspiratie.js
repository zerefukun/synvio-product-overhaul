/**
 * Converts each .oz-inspiratie-section .wp-block-gallery into a Swiper
 * slideshow with fade effect, prev/next arrows, and clickable pagination.
 *
 * Progressive enhancement: the gallery grid remains the semantic source
 * of truth, so if Swiper fails to load the user still sees a valid
 * image grid.
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    function rewriteGalleryToSwiper(gallery) {
        var figures = Array.prototype.slice.call(
            gallery.querySelectorAll('figure.wp-block-image')
        );
        if (figures.length < 2) return null;

        var container = document.createElement('div');
        container.className = 'oz-slideshow swiper';

        var wrapper = document.createElement('div');
        wrapper.className = 'swiper-wrapper';

        figures.forEach(function (fig) {
            var slide = document.createElement('div');
            slide.className = 'swiper-slide oz-slideshow__slide';
            // Pull the <img> (or link+img) straight out — no clone, to keep
            // browsers from re-downloading.
            while (fig.firstChild) slide.appendChild(fig.firstChild);
            wrapper.appendChild(slide);
        });

        container.appendChild(wrapper);

        var prev = document.createElement('button');
        prev.type = 'button';
        prev.className = 'swiper-button-prev oz-slideshow__arrow';
        prev.setAttribute('aria-label', 'Vorige afbeelding');

        var next = document.createElement('button');
        next.type = 'button';
        next.className = 'swiper-button-next oz-slideshow__arrow';
        next.setAttribute('aria-label', 'Volgende afbeelding');

        var pagination = document.createElement('div');
        pagination.className = 'swiper-pagination oz-slideshow__dots';

        var counter = document.createElement('div');
        counter.className = 'oz-slideshow__counter';
        counter.setAttribute('aria-live', 'polite');
        counter.textContent = '1 / ' + figures.length;

        container.appendChild(prev);
        container.appendChild(next);
        container.appendChild(pagination);
        container.appendChild(counter);

        gallery.parentNode.replaceChild(container, gallery);
        return { container: container, counter: counter, total: figures.length };
    }

    function initSwiper(built) {
        if (!window.Swiper) return;
        var swiper = new window.Swiper(built.container, {
            effect: 'fade',
            fadeEffect: { crossFade: true },
            speed: 450,
            loop: true,
            grabCursor: true,
            keyboard: { enabled: true, onlyInViewport: true },
            a11y: true,
            navigation: {
                prevEl: built.container.querySelector('.swiper-button-prev'),
                nextEl: built.container.querySelector('.swiper-button-next'),
            },
            pagination: {
                el: built.container.querySelector('.swiper-pagination'),
                clickable: true,
                dynamicBullets: built.total > 10,
                dynamicMainBullets: 5,
            },
        });

        swiper.on('slideChange', function () {
            built.counter.textContent =
                (swiper.realIndex + 1) + ' / ' + built.total;
        });
    }

    ready(function () {
        var galleries = document.querySelectorAll(
            '.oz-inspiratie-section .wp-block-gallery'
        );
        if (!galleries.length) return;

        var built = [];
        galleries.forEach(function (g) {
            var b = rewriteGalleryToSwiper(g);
            if (b) built.push(b);
        });
        if (!built.length) return;

        if (typeof window.ozLoadSwiper !== 'function') return;
        window.ozLoadSwiper(function () {
            built.forEach(initSwiper);
        });
    });
})();
