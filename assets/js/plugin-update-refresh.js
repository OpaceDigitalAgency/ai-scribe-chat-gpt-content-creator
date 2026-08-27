( function ( $ ) {
	'use strict';

	if ( ! window.aiScribePluginUpdateRefresh ) {
		return;
	}

	$( document ).on( 'wp-plugin-update-success', function ( event, response ) {
		var expected = aiScribePluginUpdateRefresh.pluginFile;
		var updated = response && ( response.plugin || response.pluginFile );
		if ( updated !== expected ) {
			return;
		}

		var row = $( 'tr[data-plugin="' + expected.replace( /"/g, '\\"' ) + '"]' );
		var message = $( '<div class="notice notice-success inline ai-scribe-update-refresh"><p></p></div>' );
		message.find( 'p' ).text( aiScribePluginUpdateRefresh.message );
		row.find( '.plugin-update' ).first().append( message );
		window.setTimeout( function () {
			window.location.reload();
		}, 900 );
	} );
} )( jQuery );
