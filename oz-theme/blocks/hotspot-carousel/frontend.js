/**
 * Frontend JS for oz/hotspot-carousel.
 *
 * Wires up prev/next buttons + pagination dots to the native scroll-snap
 * track. Scroll position drives dot active state via IntersectionObserver
 * so swipe / drag / button click all stay in sync without manual state.
 *
 * No dependencies, ~1KB.
 */
( function () {
	// ─── Hotspot card (popover) ──────────────────────────────────────
	// Click hotspot button → toggle the sibling .oz-hotspot-card.
	// Click outside or ESC → close any open card.
	function initHotspotCards( root ) {
		const wraps = root.querySelectorAll( '.oz-hotspot-wrap' );
		wraps.forEach( function ( wrap ) {
			const btn  = wrap.querySelector( '.oz-hotspot' );
			const card = wrap.querySelector( '.oz-hotspot-card' );
			if ( ! btn || ! card ) return; // No card = no resolved product, fall through

			function positionCard() {
				const hsRect = btn.getBoundingClientRect();
				const cardW  = 280;
				const cardH  = card.offsetHeight || 100; // approx if not yet rendered
				const above  = hsRect.top > cardH + 30;
				const cx     = hsRect.left + hsRect.width / 2;
				// Clamp horizontal so the card stays in the viewport
				const halfW  = cardW / 2;
				const minCx  = halfW + 8;
				const maxCx  = window.innerWidth - halfW - 8;
				const clampedCx = Math.max( minCx, Math.min( maxCx, cx ) );
				card.style.left = clampedCx + 'px';
				if ( above ) {
					card.style.top    = ( hsRect.top - cardH - 14 ) + 'px';
					card.classList.remove( 'is-below' );
				} else {
					card.style.top    = ( hsRect.bottom + 14 ) + 'px';
					card.classList.add( 'is-below' );
				}
			}

			btn.addEventListener( 'click', function ( ev ) {
				ev.stopPropagation();
				const isOpen = card.classList.contains( 'is-open' );
				closeAll( root );
				if ( isOpen ) return;
				card.classList.add( 'is-open' );
				positionCard();
				// Re-position after the show animation reveals the real height
				requestAnimationFrame( positionCard );
				btn.setAttribute( 'aria-expanded', 'true' );
			} );

			const closeBtn = card.querySelector( '.oz-hotspot-card__close' );
			if ( closeBtn ) {
				closeBtn.addEventListener( 'click', function ( ev ) {
					ev.stopPropagation();
					card.classList.remove( 'is-open' );
					btn.setAttribute( 'aria-expanded', 'false' );
				} );
			}

			// Stop clicks inside the card from bubbling to the document handler
			card.addEventListener( 'click', function ( ev ) { ev.stopPropagation(); } );
		} );
	}
	function closeAll( root ) {
		root.querySelectorAll( '.oz-hotspot-card' ).forEach( function ( c ) { c.classList.remove( 'is-open' ); } );
		root.querySelectorAll( '.oz-hotspot' ).forEach( function ( b ) { b.setAttribute( 'aria-expanded', 'false' ); } );
	}

	function init( root ) {
		const viewport = root.querySelector( '.oz-hotspot-carousel__viewport' );
		const track    = root.querySelector( '.oz-hotspot-carousel__track' );
		if ( ! track ) return;

		initHotspotCards( root );

		// Close cards on outside click, ESC, scroll, or carousel scroll
		// (because position:fixed cards would otherwise stay glued to the
		// viewport while the hotspot they belong to scrolled away).
		document.addEventListener( 'click', function ( ev ) {
			if ( ! root.contains( ev.target ) ) closeAll( root );
		} );
		document.addEventListener( 'keydown', function ( ev ) {
			if ( ev.key === 'Escape' ) closeAll( root );
		} );
		track.addEventListener( 'scroll', function () { closeAll( root ); }, { passive: true } );
		window.addEventListener( 'scroll', function () { closeAll( root ); }, { passive: true } );
		window.addEventListener( 'resize', function () { closeAll( root ); }, { passive: true } );

		const slides   = Array.from( track.querySelectorAll( '.oz-hotspot-carousel__slide' ) );
		const dots     = Array.from( root.querySelectorAll( '.oz-hotspot-carousel__dot' ) );
		const prevBtn  = root.querySelector( '.oz-hotspot-carousel__nav--prev' );
		const nextBtn  = root.querySelector( '.oz-hotspot-carousel__nav--next' );
		if ( slides.length < 2 ) {
			// Single slide: hide all controls
			if ( prevBtn ) prevBtn.style.display = 'none';
			if ( nextBtn ) nextBtn.style.display = 'none';
			dots.forEach( function ( d ) { d.style.display = 'none'; } );
			return;
		}

		// ─── Scroll by one slide width when arrow clicked ────────────────
		function scrollByOne( direction ) {
			const slideW = slides[ 0 ].getBoundingClientRect().width;
			const gap    = parseFloat( getComputedStyle( track ).columnGap ) || 16;
			const delta  = ( slideW + gap ) * ( direction === 'next' ? 1 : -1 );
			track.scrollBy( { left: delta, behavior: 'smooth' } );
		}
		if ( prevBtn ) prevBtn.addEventListener( 'click', function () { scrollByOne( 'prev' ); } );
		if ( nextBtn ) nextBtn.addEventListener( 'click', function () { scrollByOne( 'next' ); } );

		// ─── Dots click → scroll to slide ────────────────────────────────
		dots.forEach( function ( dot, i ) {
			dot.addEventListener( 'click', function () {
				const target = slides[ i ];
				if ( ! target ) return;
				// scrollIntoView would also scroll the page; use track scrollLeft instead.
				const trackRect = track.getBoundingClientRect();
				const slideRect = target.getBoundingClientRect();
				track.scrollBy( { left: slideRect.left - trackRect.left, behavior: 'smooth' } );
			} );
		} );

		// ─── Active dot synced to scroll position via IntersectionObserver ─
		// rootMargin trims the edges so a slide only "activates" when more
		// than half visible. Avoids flicker between two adjacent slides.
		function setActive( index ) {
			dots.forEach( function ( d, i ) {
				const isActive = i === index;
				d.classList.toggle( 'is-active', isActive );
				d.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );
			// Disable arrows at edges
			if ( prevBtn ) prevBtn.disabled = index === 0;
			if ( nextBtn ) nextBtn.disabled = index === slides.length - 1;
		}

		const io = new IntersectionObserver( function ( entries ) {
			// Pick the most-intersecting entry as the "active" slide.
			let best = null;
			entries.forEach( function ( e ) {
				if ( ! best || e.intersectionRatio > best.intersectionRatio ) best = e;
			} );
			if ( best && best.intersectionRatio > 0.5 ) {
				const idx = slides.indexOf( best.target );
				if ( idx >= 0 ) setActive( idx );
			}
		}, { root: track, threshold: [ 0.5, 0.75, 1 ] } );

		slides.forEach( function ( s ) { io.observe( s ); } );

		// Initial state
		setActive( 0 );
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
