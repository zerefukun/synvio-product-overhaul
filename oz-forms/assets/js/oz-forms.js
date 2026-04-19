/* OZ Forms — frontend controller. Renders Turnstile per form, AJAX submits to /wp-json/oz/v1/submit. */
( function () {
	'use strict';

	var CFG = window.OZ_FORMS_CFG || {};

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function whenTurnstileReady( cb ) {
		if ( window.turnstile ) {
			cb();
			return;
		}
		var poll = setInterval( function () {
			if ( window.turnstile ) {
				clearInterval( poll );
				cb();
			}
		}, 50 );
		// Safety: stop polling after 10s.
		setTimeout( function () {
			clearInterval( poll );
		}, 10000 );
	}

	function renderTurnstile( form ) {
		if ( ! CFG.turnstileKey ) {
			return;
		}
		var mount = form.querySelector( '.oz-form__turnstile' );
		if ( ! mount || mount.dataset.rendered === '1' ) {
			return;
		}
		var action = mount.getAttribute( 'data-action' ) || form.getAttribute( 'data-action' ) || '';
		var widgetId = window.turnstile.render( mount, {
			sitekey: CFG.turnstileKey,
			action: action,
			theme: 'light',
			'error-callback': function () {
				setStatus( form, 'is-error', 'Spam-controle kon niet laden. Vernieuw de pagina.' );
			},
		} );
		mount.dataset.rendered = '1';
		mount.dataset.widgetId = widgetId;
	}

	function setStatus( form, cls, msg ) {
		var node = form.querySelector( '.oz-form__status' );
		if ( ! node ) {
			return;
		}
		node.classList.remove( 'is-success', 'is-error' );
		if ( cls ) {
			node.classList.add( cls );
		}
		node.textContent = msg || '';
	}

	function clearFieldErrors( form ) {
		form.querySelectorAll( '.oz-form__field' ).forEach( function ( f ) {
			f.classList.remove( 'is-invalid' );
			var err = f.querySelector( '.oz-form__error' );
			if ( err ) {
				err.textContent = '';
			}
		} );
	}

	function showFieldErrors( form, errors ) {
		Object.keys( errors ).forEach( function ( name ) {
			var input = form.querySelector( '[name="' + name + '"]' );
			if ( ! input ) {
				return;
			}
			var field = input.closest( '.oz-form__field' );
			if ( ! field ) {
				return;
			}
			field.classList.add( 'is-invalid' );
			var err = field.querySelector( '.oz-form__error' );
			if ( err ) {
				err.textContent = errors[ name ];
			}
		} );
	}

	function submit( form ) {
		clearFieldErrors( form );
		setStatus( form, '', '' );

		var btn = form.querySelector( '.oz-form__submit' );
		if ( btn ) {
			btn.disabled = true;
		}

		var fd = new FormData( form );
		fd.append( 'form_id', form.getAttribute( 'data-form-id' ) || '' );

		var payload = {};
		fd.forEach( function ( v, k ) {
			payload[ k ] = v;
		} );

		fetch( CFG.rest, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': CFG.nonce || '',
			},
			body: JSON.stringify( payload ),
		} )
			.then( function ( res ) {
				return res.json().then( function ( body ) {
					return { status: res.status, body: body };
				} );
			} )
			.then( function ( r ) {
				if ( r.status >= 200 && r.status < 300 && r.body.ok ) {
					setStatus( form, 'is-success', r.body.message || 'Verstuurd.' );
					form.reset();
					resetTurnstile( form );
					return;
				}
				if ( r.body && r.body.errors ) {
					showFieldErrors( form, r.body.errors );
				}
				setStatus( form, 'is-error', ( r.body && r.body.message ) || 'Er ging iets mis. Probeer opnieuw.' );
				resetTurnstile( form );
			} )
			.catch( function () {
				setStatus( form, 'is-error', 'Verbinding mislukt. Controleer je internet en probeer opnieuw.' );
				resetTurnstile( form );
			} )
			.finally( function () {
				if ( btn ) {
					btn.disabled = false;
				}
			} );
	}

	function resetTurnstile( form ) {
		var mount = form.querySelector( '.oz-form__turnstile' );
		if ( mount && mount.dataset.widgetId && window.turnstile ) {
			window.turnstile.reset( mount.dataset.widgetId );
		}
	}

	ready( function () {
		var forms = document.querySelectorAll( 'form.oz-form' );
		if ( ! forms.length ) {
			return;
		}

		whenTurnstileReady( function () {
			forms.forEach( renderTurnstile );
		} );

		forms.forEach( function ( form ) {
			form.addEventListener( 'submit', function ( ev ) {
				ev.preventDefault();
				submit( form );
			} );
		} );
	} );
} )();
