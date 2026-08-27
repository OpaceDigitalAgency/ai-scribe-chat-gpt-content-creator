( function () {
	'use strict';

	var button = document.getElementById( 'ai-scribe-prepare-hub' );
	var status = document.getElementById( 'ai-scribe-hub-setup-status' );
	var installStep = document.querySelector( '[data-setup-step="install"]' );
	var activateStep = document.querySelector( '[data-setup-step="activate"]' );
	if ( ! button || ! status || ! window.aiScribeHubSetup ) {
		return;
	}

	button.addEventListener( 'click', function () {
		var requestedAction = button.getAttribute( 'data-action' );
		if ( 'install' !== requestedAction && 'update' !== requestedAction && 'activate' !== requestedAction ) {
			return;
		}
		button.disabled = true;
		button.setAttribute( 'aria-busy', 'true' );
		status.className = 'ai-scribe-hub-setup__status';
		status.textContent = 'install' === requestedAction
			? aiScribeHubSetup.strings.installing
			: ( 'update' === requestedAction ? aiScribeHubSetup.strings.updating : aiScribeHubSetup.strings.activating );

		var body = new URLSearchParams();
		body.set( 'action', aiScribeHubSetup.action );
		body.set( 'nonce', aiScribeHubSetup.nonce );
		body.set( 'setup_action', requestedAction );
		body.set( 'network_wide', aiScribeHubSetup.networkWide ? '1' : '0' );

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
					if ( 'activate' === response.data.next_action && installStep && activateStep ) {
						installStep.classList.remove( 'is-current' );
						installStep.classList.add( 'is-complete' );
						installStep.removeAttribute( 'aria-current' );
						installStep.querySelector( '.ai-scribe-hub-setup__step-number' ).textContent = '✓';
						activateStep.classList.remove( 'is-pending' );
						activateStep.classList.add( 'is-current' );
						activateStep.setAttribute( 'aria-current', 'step' );
					}
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
