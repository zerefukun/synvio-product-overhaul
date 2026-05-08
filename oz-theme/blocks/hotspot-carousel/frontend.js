/**
 * oz/hotspot-carousel — frontend interactions.
 *
 * - Hotspot click → opens product card centered in the slide stage.
 *   Buttons + cards are siblings under .image-wrap; paired by
 *   data-hotspot-target ↔ data-hotspot-id.
 * - PAGE-BASED carousel: with N slides at K-per-view, there are
 *   ceil(N/K) pages. Dots = pages. Prev/next jumps a full page.
 *   No "stuck in the middle" half-step states.
 * - Outside click, ESC, page change → close any open card.
 *
 * No deps. ~2KB.
 */
( function () {
	function closeAllCards( root ) {
		root.querySelectorAll( '.oz-hotspot-card.is-open' ).forEach( function ( c ) {
			c.classList.remove( 'is-open' );
		} );
		root.querySelectorAll( '.oz-hotspot[aria-expanded="true"]' ).forEach( function ( b ) {
			b.setAttribute( 'aria-expanded', 'false' );
		} );
	}

	function initHotspotCards( root ) {
		root.querySelectorAll( '.oz-hotspot[data-hotspot-target]' ).forEach( function ( btn ) {
			const targetId = btn.getAttribute( 'data-hotspot-target' );
			const card = root.querySelector( '.oz-hotspot-card[data-hotspot-id="' + targetId + '"]' );
			if ( ! card ) return;

			btn.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				ev.stopPropagation();
				const isOpen = card.classList.contains( 'is-open' );
				closeAllCards( root );
				if ( ! isOpen ) {
					card.classList.add( 'is-open' );
					btn.setAttribute( 'aria-expanded', 'true' );
				}
			} );
		} );

		root.querySelectorAll( '.oz-hotspot-card__close' ).forEach( function ( closeBtn ) {
			closeBtn.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				ev.stopPropagation();
				closeAllCards( root );
			} );
		} );

		root.querySelectorAll( '.oz-hotspot-card' ).forEach( function ( card ) {
			card.addEventListener( 'click', function ( ev ) { ev.stopPropagation(); } );
		} );
	}

	function initCarousel( root ) {
		const track = root.querySelector( '.oz-hotspot-carousel__track' );
		if ( ! track ) return;

		const slides  = Array.from( track.querySelectorAll( '.oz-hotspot-carousel__slide' ) );
		const dots    = Array.from( root.querySelectorAll( '.oz-hotspot-carousel__dot' ) );
		const prevBtn = root.querySelector( '.oz-hotspot-carousel__nav--prev' );
		const nextBtn = root.querySelector( '.oz-hotspot-carousel__nav--next' );

		if ( slides.length < 2 ) {
			if ( prevBtn ) prevBtn.style.display = 'none';
			if ( nextBtn ) nextBtn.style.display = 'none';
			dots.forEach( function ( d ) { d.style.display = 'none'; } );
			return;
		}

		// ── Page model ───────────────────────────────────────────────────
		// pageCount = ceil(slides / slidesPerView). Each page is one full
		// "screenful" of slides. Dots represent pages.
		let slidesPerView = 1;
		let pageWidth     = 0;
		let pageCount     = 1;

		function recomputePages() {
			const trackW = track.clientWidth;
			const slideW = slides[ 0 ].getBoundingClientRect().width;
			const gap    = parseFloat( getComputedStyle( track ).columnGap ) || 16;
			slidesPerView = Math.max( 1, Math.round( ( trackW + gap ) / ( slideW + gap ) ) );
			pageWidth     = slidesPerView * ( slideW + gap );
			pageCount     = Math.max( 1, Math.ceil( slides.length / slidesPerView ) );
			dots.forEach( function ( d, i ) {
				d.style.display = i >= pageCount ? 'none' : '';
			} );
		}

		function getCurrentPage() {
			if ( pageWidth <= 0 ) return 0;
			return Math.min( pageCount - 1, Math.round( track.scrollLeft / pageWidth ) );
		}

		function goToPage( pageIdx ) {
			const clamped = Math.max( 0, Math.min( pageCount - 1, pageIdx ) );
			track.scrollTo( { left: clamped * pageWidth, behavior: 'smooth' } );
		}

		function syncDots( pageIdx ) {
			dots.forEach( function ( d, i ) {
				const isActive = i === pageIdx;
				d.classList.toggle( 'is-active', isActive );
				d.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );
			if ( prevBtn ) prevBtn.disabled = pageIdx <= 0;
			if ( nextBtn ) nextBtn.disabled = pageIdx >= pageCount - 1;
		}

		// ── Wire up controls ─────────────────────────────────────────────
		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function () { goToPage( getCurrentPage() - 1 ); } );
		}
		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () { goToPage( getCurrentPage() + 1 ); } );
		}
		dots.forEach( function ( dot, i ) {
			dot.addEventListener( 'click', function () { goToPage( i ); } );
		} );

		track.addEventListener( 'scroll', function () {
			syncDots( getCurrentPage() );
			closeAllCards( root );
		}, { passive: true } );

		window.addEventListener( 'resize', function () {
			recomputePages();
			syncDots( getCurrentPage() );
		}, { passive: true } );

		// ── Initial state ────────────────────────────────────────────────
		// Recompute multiple times: once now, again after window 'load' (when
		// images have loaded and slide widths are final), and once more after
		// a short delay as a belt-and-suspenders fallback for cases where
		// fonts / late-loading CSS shift the layout.
		function refresh() { recomputePages(); syncDots( getCurrentPage() ); }
		refresh();
		window.addEventListener( 'load', refresh, { once: true } );
		setTimeout( refresh, 250 );
		setTimeout( refresh, 1000 );
	}

	function init( root ) {
		initHotspotCards( root );
		initCarousel( root );

		document.addEventListener( 'click', function ( ev ) {
			if ( ! root.contains( ev.target ) ) closeAllCards( root );
		} );
		document.addEventListener( 'keydown', function ( ev ) {
			if ( ev.key === 'Escape' ) closeAllCards( root );
		} );
	}

	function boot() {
		document.querySelectorAll( '.oz-hotspot-carousel' ).forEach( init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
