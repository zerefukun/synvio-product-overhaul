/* OZ Forms — frontend controller. Single + multi-step forms, Turnstile, AJAX submit. */
( function () {
	'use strict';

	var CFG = window.OZ_FORMS_CFG || {};

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) { fn(); }
		else { document.addEventListener( 'DOMContentLoaded', fn ); }
	}

	function whenTurnstileReady( cb ) {
		if ( window.turnstile ) { cb(); return; }
		var poll = setInterval( function () {
			if ( window.turnstile ) { clearInterval( poll ); cb(); }
		}, 50 );
		setTimeout( function () { clearInterval( poll ); }, 10000 );
	}

	function renderTurnstile( form ) {
		if ( ! CFG.turnstileKey ) { return; }
		var mount = form.querySelector( '.oz-form__turnstile' );
		if ( ! mount || mount.dataset.rendered === '1' ) { return; }
		var action = mount.getAttribute( 'data-action' ) || form.getAttribute( 'data-action' ) || '';
		var widgetId = window.turnstile.render( mount, {
			sitekey: CFG.turnstileKey,
			action: action,
			theme: 'light',
			'error-callback': function () {
				setStatus( form, 'is-error', 'Spam-controle kon niet laden. Vernieuw de pagina.' );
			}
		} );
		mount.dataset.rendered = '1';
		mount.dataset.widgetId = widgetId;
	}

	function resetTurnstile( form ) {
		var mount = form.querySelector( '.oz-form__turnstile' );
		if ( mount && mount.dataset.widgetId && window.turnstile ) {
			window.turnstile.reset( mount.dataset.widgetId );
		}
	}

	function setStatus( form, cls, msg ) {
		var node = form.querySelector( '.oz-form__status' );
		if ( ! node ) { return; }
		node.classList.remove( 'is-success', 'is-error' );
		if ( cls ) { node.classList.add( cls ); }
		node.textContent = msg || '';
	}

	function clearFieldErrors( form ) {
		form.querySelectorAll( '.oz-form__field' ).forEach( function ( f ) {
			f.classList.remove( 'is-invalid' );
			var err = f.querySelector( '.oz-form__error' );
			if ( err ) { err.textContent = ''; }
		} );
	}

	function showFieldErrors( form, errors ) {
		Object.keys( errors ).forEach( function ( name ) {
			var input = form.querySelector( '[name="' + name + '"]' );
			if ( ! input ) { return; }
			var field = input.closest( '.oz-form__field' );
			if ( ! field ) { return; }
			field.classList.add( 'is-invalid' );
			var err = field.querySelector( '.oz-form__error' );
			if ( err ) { err.textContent = errors[ name ]; }
		} );
	}

	/* Validate one step's worth of inputs. Marks invalid fields and returns boolean. */
	function validateStep( stepEl ) {
		var ok = true;
		var fields = stepEl.querySelectorAll( '.oz-form__field' );
		fields.forEach( function ( f ) {
			f.classList.remove( 'is-invalid' );
			var err = f.querySelector( '.oz-form__error' );
			if ( err ) { err.textContent = ''; }
			var input = f.querySelector( 'input, select, textarea' );
			if ( ! input ) { return; }
			if ( ! input.checkValidity() ) {
				ok = false;
				f.classList.add( 'is-invalid' );
				if ( err ) { err.textContent = input.validationMessage || 'Controleer dit veld.'; }
			}
		} );
		// Radio groups in this step.
		var radioNames = {};
		stepEl.querySelectorAll( 'input[type=radio][required]' ).forEach( function ( r ) {
			radioNames[ r.name ] = true;
		} );
		Object.keys( radioNames ).forEach( function ( name ) {
			var any = stepEl.querySelector( 'input[name="' + name + '"]:checked' );
			if ( ! any ) {
				ok = false;
				var first = stepEl.querySelector( 'input[name="' + name + '"]' );
				var field = first ? first.closest( '.oz-form__field' ) : null;
				if ( field ) {
					field.classList.add( 'is-invalid' );
					var err = field.querySelector( '.oz-form__error' );
					if ( err ) { err.textContent = 'Maak een keuze.'; }
				}
			}
		} );
		return ok;
	}

	function setupSteps( form ) {
		var steps   = Array.prototype.slice.call( form.querySelectorAll( '.oz-form__step' ) );
		var progress= Array.prototype.slice.call( form.querySelectorAll( '.oz-form__progress-step' ) );
		var prevBtn = form.querySelector( '.oz-form__prev' );
		var nextBtn = form.querySelector( '.oz-form__next' );
		var submit  = form.querySelector( '.oz-form__submit' );
		var ts      = form.querySelector( '.oz-form__turnstile' );
		var current = 0;

		function show( idx ) {
			steps.forEach( function ( s, i ) { s.hidden = i !== idx; } );
			progress.forEach( function ( p, i ) {
				p.classList.toggle( 'is-active', i === idx );
				p.classList.toggle( 'is-done', i < idx );
			} );
			prevBtn.hidden = idx === 0;
			var isLast = idx === steps.length - 1;
			nextBtn.hidden = isLast;
			submit.hidden  = ! isLast;
			if ( ts ) {
				ts.hidden = ! isLast;
				if ( isLast ) {
					whenTurnstileReady( function () { renderTurnstile( form ); } );
				}
			}
			// Scroll to top of form on step change for context.
			form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			current = idx;
		}

		nextBtn.addEventListener( 'click', function () {
			if ( ! validateStep( steps[ current ] ) ) { return; }
			if ( current < steps.length - 1 ) { show( current + 1 ); }
		} );
		prevBtn.addEventListener( 'click', function () {
			if ( current > 0 ) { show( current - 1 ); }
		} );

		// Allow clicking a previous progress step to jump back (not forward).
		progress.forEach( function ( p, i ) {
			p.addEventListener( 'click', function () {
				if ( i < current ) { show( i ); }
			} );
		} );

		show( 0 );
	}

	function submit( form ) {
		clearFieldErrors( form );
		setStatus( form, '', '' );

		// Multi-step: validate current (last) step before submit.
		var isStepped = form.classList.contains( 'oz-form--steps' );
		if ( isStepped ) {
			var visible = form.querySelector( '.oz-form__step:not([hidden])' );
			if ( visible && ! validateStep( visible ) ) { return; }
		} else if ( ! form.checkValidity() ) {
			// Browser-default validation surfacing.
			form.reportValidity();
			return;
		}

		var btn = form.querySelector( '.oz-form__submit' );
		if ( btn ) { btn.disabled = true; }

		var fd = new FormData( form );
		fd.append( 'form_id', form.getAttribute( 'data-form-id' ) || '' );

		var payload = {};
		fd.forEach( function ( v, k ) { payload[ k ] = v; } );

		fetch( CFG.rest, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce || '' },
			body: JSON.stringify( payload )
		} )
			.then( function ( res ) { return res.json().then( function ( body ) { return { status: res.status, body: body }; } ); } )
			.then( function ( r ) {
				if ( r.status >= 200 && r.status < 300 && r.body.ok ) {
					setStatus( form, 'is-success', r.body.message || 'Verstuurd.' );
					form.reset();
					resetTurnstile( form );
					return;
				}
				if ( r.body && r.body.errors ) { showFieldErrors( form, r.body.errors ); }
				setStatus( form, 'is-error', ( r.body && r.body.message ) || 'Er ging iets mis. Probeer opnieuw.' );
				resetTurnstile( form );
			} )
			.catch( function () {
				setStatus( form, 'is-error', 'Verbinding mislukt. Controleer je internet en probeer opnieuw.' );
				resetTurnstile( form );
			} )
			.finally( function () {
				if ( btn ) { btn.disabled = false; }
			} );
	}

	ready( function () {
		var forms = document.querySelectorAll( 'form.oz-form' );
		if ( ! forms.length ) { return; }

		forms.forEach( function ( form ) {
			if ( form.classList.contains( 'oz-form--steps' ) ) {
				setupSteps( form );
			} else {
				whenTurnstileReady( function () { renderTurnstile( form ); } );
			}
			form.addEventListener( 'submit', function ( ev ) {
				ev.preventDefault();
				submit( form );
			} );
		} );
	} );
} )();
