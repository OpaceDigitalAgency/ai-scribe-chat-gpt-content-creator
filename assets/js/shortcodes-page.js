/**
 * Saved Shortcodes screen — remove buttons (UAT §12.5).
 * Calls the existing al_scribe_remove_short_code_content AJAX handler.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

( function () {
	'use strict';

	document.addEventListener( 'click', async function ( e ) {
		const button = e.target.closest( '#ai-scribe-shortcodes-root .delete[data-id]' );
		if ( ! button ) {
			return;
		}
		const localized = window.ai_scribe || {};
		button.disabled = true;
		try {
			const body = new FormData();
			body.append( 'action', 'al_scribe_remove_short_code_content' );
			body.append( 'security', localized.nonce || '' );
			body.append( 'shortcode_id', button.getAttribute( 'data-id' ) );
			const response = await fetch( localized.ajaxUrl || window.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			} );
			const payload = await response.json();
			if ( payload && payload.success ) {
				const row = button.closest( 'tr' );
				if ( row ) {
					row.remove();
				}
				if ( window.aiScribeNotifications ) {
					window.aiScribeNotifications.show( {
						title: 'Shortcode removed',
						message: 'The saved shortcode is no longer available for use.',
						type: 'success',
						key: 'shortcode-removed'
					} );
				}
			} else {
				button.disabled = false;
				const message = ( payload && payload.data && payload.data.message ) || 'Failed to remove the shortcode.';
				window.aiScribeNotifications.show( { title: 'Shortcode was not removed', message: message, type: 'error' } );
			}
		} catch ( error ) {
			button.disabled = false;
			window.aiScribeNotifications.show( {
				title: 'Shortcode was not removed',
				message: 'The request failed. Check your connection and try again.',
				type: 'error'
			} );
		}
	} );
}() );
