( function () {
	'use strict';

	var button = document.getElementById( 'ai-scribe-prepare-hub' );
	var status = document.getElementById( 'ai-scribe-hub-setup-status' );
	if ( ! button || ! status || ! window.aiScribeHubSetup ) {
		return;
	}

	button.addEventListener( 'click', function () {
		var requestedAction = button.getAttribute( 'data-action' );
		if ( 'install' !== requestedAction && 'activate' !== requestedAction ) {
			return;
		}
		button.disabled = true;
		button.setAttribute( 'aria-busy', 'true' );
		status.className = 'ai-scribe-hub-setup__status';
		status.textContent = 'install' === requestedAction ? aiScribeHubSetup.strings.installing : aiScribeHubSetup.strings.activating;

		var body = new URLSearchParams();
		body.set( 'action', aiScribeHubSetup.action );
		body.set( 'nonce', aiScribeHubSetup.nonce );
		body.set( 'setup_action', requestedAction );

		window.fetch( aiScribeHubSetup.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( response ) {
				if ( ! response.success ) {
					throw new Error( response.data && response.data.message ? response.data.message : aiScribeHubSetup.strings.failed );
				}
				status.classList.add( 'is-success' );
				status.textContent = response.data.message;
				if ( response.data.next_action ) {
					button.setAttribute( 'data-action', response.data.next_action );
					button.textContent = response.data.button_label;
					button.disabled = false;
					button.removeAttribute( 'aria-busy' );
					button.focus();
					return;
				}
				window.location.assign( response.data.redirect );
			} )
			.catch( function ( error ) {
				button.disabled = false;
				button.removeAttribute( 'aria-busy' );
				status.classList.add( 'is-error' );
				status.textContent = error.message || aiScribeHubSetup.strings.failed;
			} );
	} );
} )();
