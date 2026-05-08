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
	function init( root ) {
		const viewport = root.querySelector( '.oz-hotspot-carousel__viewport' );
		const track    = root.querySelector( '.oz-hotspot-carousel__track' );
		if ( ! track ) return;

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
