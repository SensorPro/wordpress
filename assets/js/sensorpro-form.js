/* SensorPro signup form: submit without reloading. Without JS the form posts to admin-post.php. */
( function () {
	'use strict';
	if ( ! window.fetch || ! window.FormData || ! window.SensorProForm ) {
		return;
	}
	var cfg = window.SensorProForm;

	function setMsg( form, text, kind ) {
		var el = form.querySelector( '.sensorpro-form__message' );
		if ( ! el ) {
			return;
		}
		el.textContent = text || '';
		el.className = 'sensorpro-form__message' + ( kind ? ' is-' + kind : '' );
	}

	function clientCheck( form ) {
		var email = form.querySelector( '[name="sp_email"]' );
		if ( email && ( ! email.value || ! email.checkValidity() ) ) {
			email.focus();
			return email.validationMessage || 'Please enter a valid email address.';
		}
		var req = form.querySelectorAll( 'input[required]:not([name="sp_email"])' );
		for ( var i = 0; i < req.length; i++ ) {
			if ( ! req[ i ].checkValidity() ) {
				req[ i ].focus();
				return req[ i ].validationMessage;
			}
		}
		return '';
	}

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! form.classList || ! form.classList.contains( 'sensorpro-form' ) ) {
			return;
		}
		e.preventDefault();
		var problem = clientCheck( form );
		if ( problem ) {
			setMsg( form, problem, 'error' );
			return;
		}
		var btn = form.querySelector( '.sensorpro-form__submit' );
		var label = btn ? btn.textContent : '';
		if ( btn ) {
			btn.disabled = true;
			btn.textContent = cfg.sending;
		}
		form.setAttribute( 'aria-busy', 'true' );
		setMsg( form, '', '' );

		var headers = { Accept: 'application/json' };
		if ( cfg.restNonce ) {
			headers[ 'X-WP-Nonce' ] = cfg.restNonce;
		}
		fetch( cfg.restUrl, { method: 'POST', body: new FormData( form ), credentials: 'same-origin', headers: headers } )
			.then( function ( r ) {
				return r.json().catch( function () {
					return { success: false, message: cfg.error };
				} );
			} )
			.then( function ( data ) {
				if ( data && data.success ) {
					if ( data.redirect ) {
						window.location.href = data.redirect;
						return;
					}
					form.reset();
					form.classList.add( 'is-done' );
					setMsg( form, data.message, 'success' );
				} else {
					setMsg( form, ( data && data.message ) || cfg.error, 'error' );
				}
			} )
			.catch( function () {
				setMsg( form, cfg.error, 'error' );
			} )
			.then( function () {
				form.removeAttribute( 'aria-busy' );
				if ( btn ) {
					btn.disabled = false;
					btn.textContent = label;
				}
			} );
	} );
} )();
