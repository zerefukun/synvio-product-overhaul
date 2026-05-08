/**
 * oz/hotspot-carousel — frontend interactions.
 *
 * Modeled on gulcanhome's pattern:
 * - Hotspot click → opens its product card in the CENTER of the slide stage.
 *   Buttons + cards are siblings under the image-wrap; JS pairs them by
 *   data-hotspot-target ↔ data-hotspot-id. Card centers via CSS.
 * - One card open at a time per carousel.
 * - Outside click, ESC, slide change, scroll → all close any open card.
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

		function scrollByOne( direction ) {
			const slideW = slides[ 0 ].getBoundingClientRect().width;
			const gap    = parseFloat( getComputedStyle( track ).columnGap ) || 16;
			const delta  = ( slideW + gap ) * ( direction === 'next' ? 1 : -1 );
			track.scrollBy( { left: delta, behavior: 'smooth' } );
		}
		if ( prevBtn ) prevBtn.addEventListener( 'click', function () { scrollByOne( 'prev' ); } );
		if ( nextBtn ) nextBtn.addEventListener( 'click', function () { scrollByOne( 'next' ); } );

		dots.forEach( function ( dot, i ) {
			dot.addEventListener( 'click', function () {
				const target = slides[ i ];
				if ( ! target ) return;
				const trackRect = track.getBoundingClientRect();
				const slideRect = target.getBoundingClientRect();
				track.scrollBy( { left: slideRect.left - trackRect.left, behavior: 'smooth' } );
			} );
		} );

		// Recompute which dots are reachable based on viewport / slide widths.
		// With 4 slides and 2 visible per view, only dots 1-3 represent unique
		// scroll positions; dot 4 would land at the same position as dot 3.
		function recomputeReachableDots() {
			const trackW = track.clientWidth;
			const slideW = slides[ 0 ].getBoundingClientRect().width;
			const gap    = parseFloat( getComputedStyle( track ).columnGap ) || 16;
			const slidesPerView = Math.max( 1, Math.round( ( trackW + gap ) / ( slideW + gap ) ) );
			const lastReachable = Math.max( 0, slides.length - slidesPerView );
			dots.forEach( function ( d, i ) {
				d.style.display = i > lastReachable ? 'none' : '';
			} );
			return lastReachable;
		}

		// Active slide = the LEFTMOST one whose right edge is past the track
		// viewport's left edge. Matches what the user perceives as "the slide
		// you're currently looking at" regardless of how many are partially
		// visible to the right.
		function getActiveSlideIdx() {
			const trackLeft = track.getBoundingClientRect().left;
			for ( let i = 0; i < slides.length; i++ ) {
				const r = slides[ i ].getBoundingClientRect();
				if ( r.right > trackLeft + 10 ) return i;
			}
			return 0;
		}

		let lastReachable = recomputeReachableDots();

		function setActive( index ) {
			// Clamp to reachable so the last visible page always shows the
			// correct dot active even if a partially visible slide beyond it
			// would otherwise be picked.
			if ( index > lastReachable ) index = lastReachable;
			dots.forEach( function ( d, i ) {
				const isActive = i === index;
				d.classList.toggle( 'is-active', isActive );
				d.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );
			if ( prevBtn ) prevBtn.disabled = index === 0;
			if ( nextBtn ) nextBtn.disabled = index >= lastReachable;
		}

		setActive( getActiveSlideIdx() );

		track.addEventListener( 'scroll', function () {
			setActive( getActiveSlideIdx() );
			closeAllCards( root );
		}, { passive: true } );

		window.addEventListener( 'resize', function () {
			lastReachable = recomputeReachableDots();
			setActive( getActiveSlideIdx() );
		}, { passive: true } );
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
