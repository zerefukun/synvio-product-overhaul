/* OZ Forms — frontend controller. Single + multi-step forms, Turnstile, AJAX submit. */
( function () {
	'use strict';

	var CFG = window.OZ_FORMS_CFG || {};

	/* ═══ ANALYTICS ════════════════════════════════════════════
	 * dataLayer pushes only — submissions are stored as oz_submission
	 * CPT, so the server-side log already exists. No beacon needed.
	 * Safe no-op when dataLayer doesn't exist (ad blockers, no GTM). */
	function track( form, eventName, params ) {
		try {
			window.dataLayer = window.dataLayer || [];
			var base = {
				event:        eventName,
				oz_form_id:   form.getAttribute( 'data-form-id' ) || '',
				oz_form_type: form.classList.contains( 'oz-form--steps' ) ? 'multi_step' : 'single',
			};
			var step = form.querySelector( '.oz-form__step:not([hidden])' );
			if ( step ) { base.oz_form_step = parseInt( step.getAttribute( 'data-step' ), 10 ) + 1; }
			window.dataLayer.push( Object.assign( base, params || {} ) );
		} catch ( e ) { /* never let analytics break the form */ }
	}

	/* Fire oz_form_start exactly once per form, on first real interaction. */
	function armStartTracking( form ) {
		var fired = false;
		function fire() {
			if ( fired ) { return; }
			fired = true;
			track( form, 'oz_form_start', {} );
		}
		form.addEventListener( 'focusin', fire, { once: false } );
		form.addEventListener( 'change', fire, { once: false } );
	}

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
			if ( ! validateStep( steps[ current ] ) ) {
				track( form, 'oz_form_validation_error', {
					oz_form_step: current + 1,
					oz_error_source: 'next_button',
				} );
				return;
			}
			if ( current < steps.length - 1 ) {
				var from = current + 1;
				show( current + 1 );
				track( form, 'oz_form_step_advanced', { oz_form_step_from: from, oz_form_step_to: current + 1 } );
			}
		} );
		prevBtn.addEventListener( 'click', function () {
			if ( current > 0 ) {
				var from = current + 1;
				show( current - 1 );
				track( form, 'oz_form_step_back', { oz_form_step_from: from, oz_form_step_to: current + 1 } );
			}
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
				track( form, 'oz_form_validation_error', {
					oz_form_step: firstInvalid + 1,
					oz_error_source: 'submit',
				} );
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
			track( form, 'oz_form_validation_error', { oz_error_source: 'submit_native' } );
			form.reportValidity();
			return;
		}

		var btn = form.querySelector( '.oz-form__submit' );
		if ( btn ) { btn.disabled = true; }

		var fd = new FormData( form );
		fd.append( 'form_id', form.getAttribute( 'data-form-id' ) || '' );

		track( form, 'oz_form_submit_attempt', {} );

		// Post as multipart FormData so that file inputs and multi-value
		// fields (e.g. producten[]) reach WP without serialization loss.
		// WP's REST layer merges $_POST and $_FILES into $req, matching our
		// PHP handler's expectations.
		fetch( CFG.rest, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': CFG.nonce || '' },
			body: fd
		} )
			.then( function ( res ) { return res.json().then( function ( body ) { return { status: res.status, body: body }; } ); } )
			.then( function ( r ) {
				if ( r.status >= 200 && r.status < 300 && r.body.ok ) {
					setStatus( form, 'is-success', r.body.message || 'Verstuurd.' );
					track( form, 'oz_form_submit_success', {} );
					// Standard GA4 conversion event so Google Ads / GA4 lead
					// goals fire without GTM having to translate our custom
					// event. Mirrors the PDP add_to_cart pattern.
					try {
						window.dataLayer = window.dataLayer || [];
						window.dataLayer.push( {
							event: 'generate_lead',
							form_id: form.getAttribute( 'data-form-id' ) || '',
							currency: 'EUR',
							value: 0,
						} );
					} catch ( e ) {}
					form.reset();
					resetTurnstile( form );
					return;
				}
				if ( r.body && r.body.errors ) { showFieldErrors( form, r.body.errors ); }
				setStatus( form, 'is-error', ( r.body && r.body.message ) || 'Er ging iets mis. Probeer opnieuw.' );
				track( form, 'oz_form_submit_error', {
					oz_status: r.status,
					oz_reason: ( r.body && r.body.reason ) || ( r.body && r.body.message ) || 'unknown',
				} );
				resetTurnstile( form );
			} )
			.catch( function () {
				setStatus( form, 'is-error', 'Verbinding mislukt. Controleer je internet en probeer opnieuw.' );
				track( form, 'oz_form_submit_error', { oz_status: 0, oz_reason: 'network' } );
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
				track( form, 'oz_form_swatch_unpicked', {
					oz_swatch_code: code,
					oz_swatch_slot: existing + 1,
				} );
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
			track( form, 'oz_form_swatch_picked', {
				oz_swatch_code: code,
				oz_swatch_slot: empty + 1,
			} );
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

	/* Autocomplete multi-select: replaces the native <select multiple> with
	   a search box + chip list. Markup convention:
	     .oz-form__multiselect[data-name][data-options]
	       > select.oz-form__multiselect-native (hidden, mirrored state)
	   All state lives on the native <select>, so server-side code always
	   reads from the standard form submission (name="foo[]"). */
	function setupMultiselects( form ) {
		var containers = form.querySelectorAll( '.oz-form__multiselect' );
		containers.forEach( function ( box ) {
			var name   = box.getAttribute( 'data-name' ) || '';
			var ph     = box.getAttribute( 'data-placeholder' ) || 'Typ om te zoeken…';
			var native = box.querySelector( '.oz-form__multiselect-native' );
			if ( ! native ) { return; }

			var options;
			try { options = JSON.parse( box.getAttribute( 'data-options' ) || '{}' ); }
			catch ( e ) { options = {}; }

			native.setAttribute( 'hidden', 'hidden' );
			native.setAttribute( 'aria-hidden', 'true' );
			native.tabIndex = -1;

			var ui = document.createElement( 'div' );
			ui.className = 'oz-form__ms-ui';
			ui.innerHTML =
				'<div class="oz-form__ms-chips" role="list"></div>' +
				'<input type="text" class="oz-form__ms-search" placeholder="' + ph.replace( /"/g, '&quot;' ) + '" autocomplete="off" aria-autocomplete="list">' +
				'<ul class="oz-form__ms-suggest" role="listbox" hidden></ul>';
			box.appendChild( ui );

			var chipsEl   = ui.querySelector( '.oz-form__ms-chips' );
			var searchEl  = ui.querySelector( '.oz-form__ms-search' );
			var suggestEl = ui.querySelector( '.oz-form__ms-suggest' );

			function selectedValues() {
				return Array.prototype.slice.call( native.options )
					.filter( function ( o ) { return o.selected; } )
					.map( function ( o ) { return o.value; } );
			}

			function setSelected( value, on ) {
				var opt = Array.prototype.slice.call( native.options )
					.filter( function ( o ) { return o.value === value; } )[ 0 ];
				if ( ! opt ) { return; }
				opt.selected = !! on;
				native.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				render();
			}

			function render() {
				var selected = selectedValues();
				chipsEl.innerHTML = '';
				selected.forEach( function ( v ) {
					var chip = document.createElement( 'span' );
					chip.className = 'oz-form__ms-chip';
					chip.setAttribute( 'role', 'listitem' );
					chip.innerHTML = '<span>' + ( options[ v ] || v ) + '</span>' +
						'<button type="button" class="oz-form__ms-chip-x" aria-label="Verwijder ' + ( options[ v ] || v ) + '">&times;</button>';
					chip.querySelector( '.oz-form__ms-chip-x' ).addEventListener( 'click', function () {
						setSelected( v, false );
						searchEl.focus();
					} );
					chipsEl.appendChild( chip );
				} );
				box.classList.toggle( 'has-selection', selected.length > 0 );
			}

			function filterSuggestions( q ) {
				var selected = selectedValues();
				q = ( q || '' ).toLowerCase().trim();
				var matches = Object.keys( options ).filter( function ( v ) {
					if ( selected.indexOf( v ) !== -1 ) { return false; }
					if ( ! q ) { return true; }
					return ( options[ v ] || '' ).toLowerCase().indexOf( q ) !== -1
						|| v.toLowerCase().indexOf( q ) !== -1;
				} );
				suggestEl.innerHTML = '';
				if ( ! matches.length ) {
					suggestEl.hidden = true;
					return;
				}
				matches.forEach( function ( v, i ) {
					var li = document.createElement( 'li' );
					li.className = 'oz-form__ms-suggest-item';
					li.setAttribute( 'role', 'option' );
					li.setAttribute( 'data-value', v );
					if ( i === 0 ) { li.classList.add( 'is-active' ); }
					li.textContent = options[ v ] || v;
					li.addEventListener( 'mousedown', function ( ev ) {
						ev.preventDefault(); // keep focus on search
						setSelected( v, true );
						searchEl.value = '';
						filterSuggestions( '' );
					} );
					suggestEl.appendChild( li );
				} );
				suggestEl.hidden = false;
			}

			searchEl.addEventListener( 'focus', function () { filterSuggestions( searchEl.value ); } );
			searchEl.addEventListener( 'input', function () { filterSuggestions( searchEl.value ); } );
			searchEl.addEventListener( 'blur',  function () {
				setTimeout( function () { suggestEl.hidden = true; }, 120 );
			} );
			searchEl.addEventListener( 'keydown', function ( ev ) {
				var active = suggestEl.querySelector( '.is-active' );
				if ( ev.key === 'ArrowDown' ) {
					ev.preventDefault();
					if ( suggestEl.hidden ) { filterSuggestions( searchEl.value ); return; }
					var next = active && active.nextElementSibling;
					if ( active ) { active.classList.remove( 'is-active' ); }
					( next || suggestEl.firstElementChild ).classList.add( 'is-active' );
				} else if ( ev.key === 'ArrowUp' ) {
					ev.preventDefault();
					var prev = active && active.previousElementSibling;
					if ( active ) { active.classList.remove( 'is-active' ); }
					( prev || suggestEl.lastElementChild ).classList.add( 'is-active' );
				} else if ( ev.key === 'Enter' ) {
					if ( ! suggestEl.hidden && active ) {
						ev.preventDefault();
						setSelected( active.getAttribute( 'data-value' ), true );
						searchEl.value = '';
						filterSuggestions( '' );
					}
				} else if ( ev.key === 'Backspace' && searchEl.value === '' ) {
					var sel = selectedValues();
					if ( sel.length ) {
						ev.preventDefault();
						setSelected( sel[ sel.length - 1 ], false );
					}
				} else if ( ev.key === 'Escape' ) {
					suggestEl.hidden = true;
				}
			} );

			box.addEventListener( 'click', function ( ev ) {
				if ( ev.target === box || ev.target === chipsEl ) {
					searchEl.focus();
				}
			} );

			render();
		} );
	}

	/* Single-select autocomplete combobox. Upgrades a hidden native <select>
	   into a searchable input + suggestion list. All state lives on the
	   native <select>, so the form submits name="field"=value as if it were
	   a normal dropdown — validators treat it identically to `select`.

	   Markup convention (rendered server-side):
	     .oz-form__autocomplete[data-name][data-options]
	       > select.oz-form__autocomplete-native (hidden, holds state)

	   Normalization (lowercase + diacritic strip) makes "Ciré" match a
	   "cire" query, so Dutch/French accents don't break filtering. */
	function setupAutocompletes( form ) {
		var containers = form.querySelectorAll( '.oz-form__autocomplete' );
		containers.forEach( function ( box ) {
			var ph     = box.getAttribute( 'data-placeholder' ) || 'Typ om te zoeken…';
			var native = box.querySelector( '.oz-form__autocomplete-native' );
			if ( ! native ) { return; }

			var options;
			try { options = JSON.parse( box.getAttribute( 'data-options' ) || '{}' ); }
			catch ( e ) { options = {}; }

			native.setAttribute( 'hidden', 'hidden' );
			native.setAttribute( 'aria-hidden', 'true' );
			native.tabIndex = -1;

			var ui = document.createElement( 'div' );
			ui.className = 'oz-form__ac-ui';
			ui.innerHTML =
				'<input type="text" class="oz-form__ac-search" placeholder="' + ph.replace( /"/g, '&quot;' ) + '" autocomplete="off" aria-autocomplete="list" role="combobox" aria-expanded="false">' +
				'<button type="button" class="oz-form__ac-clear" aria-label="Wissen" hidden>&times;</button>' +
				'<ul class="oz-form__ac-suggest" role="listbox" hidden></ul>';
			box.appendChild( ui );

			var searchEl  = ui.querySelector( '.oz-form__ac-search' );
			var clearEl   = ui.querySelector( '.oz-form__ac-clear' );
			var suggestEl = ui.querySelector( '.oz-form__ac-suggest' );

			function normalize( s ) {
				return ( s || '' ).toString().toLowerCase().normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' );
			}

			function selectedValue() {
				return native.value || '';
			}

			function setSelected( value ) {
				native.value = value;
				native.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				render();
			}

			function render() {
				var v = selectedValue();
				if ( v && options[ v ] ) {
					searchEl.value = options[ v ];
					box.classList.add( 'has-selection' );
					clearEl.hidden = false;
				} else {
					box.classList.remove( 'has-selection' );
					clearEl.hidden = true;
				}
			}

			function filterSuggestions( q ) {
				var qNorm = normalize( q || '' ).trim();
				var current = selectedValue();
				// If the query exactly equals the selected label, treat as
				// empty filter — the user is just re-opening the menu.
				var currentLabel = current && options[ current ] ? options[ current ] : '';
				if ( qNorm && currentLabel && normalize( currentLabel ) === qNorm ) {
					qNorm = '';
				}
				var matches = Object.keys( options ).filter( function ( v ) {
					if ( ! qNorm ) { return true; }
					return normalize( options[ v ] || '' ).indexOf( qNorm ) !== -1
						|| normalize( v ).indexOf( qNorm ) !== -1;
				} );
				suggestEl.innerHTML = '';
				if ( ! matches.length ) {
					suggestEl.hidden = true;
					searchEl.setAttribute( 'aria-expanded', 'false' );
					return;
				}
				matches.forEach( function ( v, i ) {
					var li = document.createElement( 'li' );
					li.className = 'oz-form__ac-suggest-item';
					li.setAttribute( 'role', 'option' );
					li.setAttribute( 'data-value', v );
					if ( v === current ) { li.classList.add( 'is-selected' ); }
					if ( i === 0 ) { li.classList.add( 'is-active' ); }
					li.textContent = options[ v ] || v;
					li.addEventListener( 'mousedown', function ( ev ) {
						ev.preventDefault();
						setSelected( v );
						suggestEl.hidden = true;
						searchEl.setAttribute( 'aria-expanded', 'false' );
						searchEl.blur();
					} );
					suggestEl.appendChild( li );
				} );
				suggestEl.hidden = false;
				searchEl.setAttribute( 'aria-expanded', 'true' );
			}

			searchEl.addEventListener( 'focus', function () {
				// Select all on focus so the user can start typing fresh without
				// having to manually clear the old label.
				if ( selectedValue() ) { searchEl.select(); }
				filterSuggestions( '' );
			} );
			searchEl.addEventListener( 'input', function () {
				// Typing invalidates the current selection until a suggestion
				// is committed — matches classic combobox behaviour.
				if ( selectedValue() ) { setSelected( '' ); }
				filterSuggestions( searchEl.value );
			} );
			searchEl.addEventListener( 'blur',  function () {
				setTimeout( function () {
					suggestEl.hidden = true;
					searchEl.setAttribute( 'aria-expanded', 'false' );
					// If user typed but didn't pick, restore display to last committed selection.
					render();
				}, 120 );
			} );
			searchEl.addEventListener( 'keydown', function ( ev ) {
				var active = suggestEl.querySelector( '.is-active' );
				if ( ev.key === 'ArrowDown' ) {
					ev.preventDefault();
					if ( suggestEl.hidden ) { filterSuggestions( searchEl.value ); return; }
					var next = active && active.nextElementSibling;
					if ( active ) { active.classList.remove( 'is-active' ); }
					( next || suggestEl.firstElementChild ).classList.add( 'is-active' );
				} else if ( ev.key === 'ArrowUp' ) {
					ev.preventDefault();
					var prev = active && active.previousElementSibling;
					if ( active ) { active.classList.remove( 'is-active' ); }
					( prev || suggestEl.lastElementChild ).classList.add( 'is-active' );
				} else if ( ev.key === 'Enter' ) {
					if ( ! suggestEl.hidden && active ) {
						ev.preventDefault();
						setSelected( active.getAttribute( 'data-value' ) );
						suggestEl.hidden = true;
						searchEl.setAttribute( 'aria-expanded', 'false' );
						searchEl.blur();
					}
				} else if ( ev.key === 'Escape' ) {
					suggestEl.hidden = true;
					searchEl.setAttribute( 'aria-expanded', 'false' );
					render();
				}
			} );

			clearEl.addEventListener( 'click', function () {
				setSelected( '' );
				searchEl.value = '';
				searchEl.focus();
				filterSuggestions( '' );
			} );

			box.addEventListener( 'click', function ( ev ) {
				if ( ev.target === box ) { searchEl.focus(); }
			} );

			render();
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
			setupMultiselects( form );
			setupAutocompletes( form );
			setupKleurPicker( form );
			armStartTracking( form );
			form.addEventListener( 'submit', function ( ev ) {
				ev.preventDefault();
				submit( form );
			} );
		} );
	} );
} )();
