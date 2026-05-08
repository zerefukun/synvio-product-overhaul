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

		function setActive( index ) {
			dots.forEach( function ( d, i ) {
				const isActive = i === index;
				d.classList.toggle( 'is-active', isActive );
				d.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );
			if ( prevBtn ) prevBtn.disabled = index === 0;
			if ( nextBtn ) nextBtn.disabled = index === slides.length - 1;
		}

		const io = new IntersectionObserver( function ( entries ) {
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
		setActive( 0 );

		track.addEventListener( 'scroll', function () { closeAllCards( root ); }, { passive: true } );
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
