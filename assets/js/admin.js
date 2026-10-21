( function () {
	'use strict';
	var btn = document.getElementById( 'sp-test' );
	var out = document.getElementById( 'sp-test-result' );
	if ( ! btn || ! window.SensorProAdmin ) {
		return;
	}
	btn.addEventListener( 'click', function () {
		var body = new FormData();
		body.append( 'action', 'sensorpro_test_connection' );
		body.append( 'nonce', SensorProAdmin.nonce );
		btn.disabled = true;
		out.className = '';
		out.textContent = SensorProAdmin.testing;
		fetch( SensorProAdmin.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( d ) {
				out.className = d && d.success ? 'sp-ok' : 'sp-err';
				out.textContent = ( d && d.data && d.data.message ) || SensorProAdmin.failed;
			} )
			.catch( function () {
				out.className = 'sp-err';
				out.textContent = SensorProAdmin.failed;
			} )
			.then( function () { btn.disabled = false; } );
	} );
} )();
