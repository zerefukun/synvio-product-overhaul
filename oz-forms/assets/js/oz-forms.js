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

		function show( idx, opts ) {
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
			if ( ! opts || opts.scroll !== false ) {
				form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
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

		show( 0, { scroll: false } );
	}

	function submit( form ) {
		clearFieldErrors( form );
		setStatus( form, '', '' );

		var isStepped = form.classList.contains( 'oz-form--steps' );
		if ( isStepped ) {
			// Validate every step. Reveal the first invalid one to the user
			// instead of letting them submit an incomplete multi-step form.
			var allSteps = form.querySelectorAll( '.oz-form__step' );
			var firstInvalid = -1;
			for ( var i = 0; i < allSteps.length; i++ ) {
				var wasHidden = allSteps[ i ].hidden;
				allSteps[ i ].hidden = false; // checkValidity needs the field visible
				var stepOk = validateStep( allSteps[ i ] );
				allSteps[ i ].hidden = wasHidden;
				if ( ! stepOk && firstInvalid === -1 ) { firstInvalid = i; }
			}
			if ( firstInvalid !== -1 ) {
				var nextBtn = form.querySelector( '.oz-form__next' );
				if ( nextBtn ) { nextBtn.click(); } // no-op safety; below jumps directly
				// Jump to the first invalid step.
				var stepEls = Array.prototype.slice.call( allSteps );
				stepEls.forEach( function ( s, idx ) { s.hidden = idx !== firstInvalid; } );
				var progress = form.querySelectorAll( '.oz-form__progress-step' );
				progress.forEach( function ( p, idx ) {
					p.classList.toggle( 'is-active', idx === firstInvalid );
					p.classList.toggle( 'is-done', idx < firstInvalid );
				} );
				var prev = form.querySelector( '.oz-form__prev' );
				var next = form.querySelector( '.oz-form__next' );
				var sub  = form.querySelector( '.oz-form__submit' );
				if ( prev ) { prev.hidden = firstInvalid === 0; }
				var isLast = firstInvalid === stepEls.length - 1;
				if ( next ) { next.hidden = isLast; }
				if ( sub ) { sub.hidden = ! isLast; }
				validateStep( stepEls[ firstInvalid ] ); // re-mark errors on the now-visible step
				return;
			}
		} else if ( ! form.checkValidity() ) {
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

	/* Wire any .oz-kleur-grid on the page to the kleurstalen form's
	   kleur1..kleur4 selects. Click a swatch → fills the next empty slot
	   (and badges the swatch with the slot number). Click an already
	   selected swatch → clears that slot. Manual select changes sync back. */
	function setupKleurPicker( form ) {
		var grids   = document.querySelectorAll( '.oz-kleur-grid' );
		if ( ! grids.length ) { return; }
		var selects = [
			form.querySelector( '[name="kleur1"]' ),
			form.querySelector( '[name="kleur2"]' ),
			form.querySelector( '[name="kleur3"]' ),
			form.querySelector( '[name="kleur4"]' )
		];
		if ( selects.some( function ( s ) { return ! s; } ) ) { return; }

		var swatches = [];
		grids.forEach( function ( grid ) {
			grid.classList.add( 'oz-kleur-grid--pickable' );
			grid.querySelectorAll( '.oz-kleur-swatch' ).forEach( function ( fig ) {
				var strong = fig.querySelector( 'strong' );
				if ( ! strong ) { return; }
				var code = strong.textContent.trim();
				if ( ! code ) { return; }
				fig.dataset.kleurCode = code;
				fig.setAttribute( 'role', 'button' );
				fig.setAttribute( 'tabindex', '0' );
				fig.setAttribute( 'aria-pressed', 'false' );
				var badge = document.createElement( 'span' );
				badge.className = 'oz-kleur-swatch__badge';
				badge.setAttribute( 'aria-hidden', 'true' );
				fig.appendChild( badge );
				swatches.push( fig );
			} );
		} );

		function refresh() {
			var values = selects.map( function ( s ) { return s.value || ''; } );
			swatches.forEach( function ( fig ) {
				var code = fig.dataset.kleurCode;
				var slot = values.indexOf( code );
				var badge = fig.querySelector( '.oz-kleur-swatch__badge' );
				if ( slot >= 0 ) {
					fig.classList.add( 'is-selected' );
					fig.setAttribute( 'aria-pressed', 'true' );
					if ( badge ) { badge.textContent = String( slot + 1 ); }
				} else {
					fig.classList.remove( 'is-selected' );
					fig.setAttribute( 'aria-pressed', 'false' );
					if ( badge ) { badge.textContent = ''; }
				}
			} );
		}

		function pick( code ) {
			var values = selects.map( function ( s ) { return s.value || ''; } );
			var existing = values.indexOf( code );
			if ( existing >= 0 ) {
				selects[ existing ].value = '';
				selects[ existing ].dispatchEvent( new Event( 'change', { bubbles: true } ) );
				refresh();
				return;
			}
			var empty = values.indexOf( '' );
			if ( empty < 0 ) { return; } // all 4 filled
			var sel = selects[ empty ];
			var hasOption = !! sel.querySelector( 'option[value="' + code + '"]' );
			if ( ! hasOption ) { return; }
			sel.value = code;
			sel.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			refresh();
		}

		swatches.forEach( function ( fig ) {
			fig.addEventListener( 'click', function () { pick( fig.dataset.kleurCode ); } );
			fig.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					pick( fig.dataset.kleurCode );
				}
			} );
		} );

		selects.forEach( function ( s ) {
			s.addEventListener( 'change', refresh );
		} );

		refresh();
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
			setupKleurPicker( form );
			form.addEventListener( 'submit', function ( ev ) {
				ev.preventDefault();
				submit( form );
			} );
		} );
	} );
} )();
