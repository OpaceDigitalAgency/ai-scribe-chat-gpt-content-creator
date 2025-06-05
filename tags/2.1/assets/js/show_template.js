(
	function ( $ ) {
		$( document ).ready(
			function () {
				$( ".delete" ).click(
					function ( e ) {
						e.preventDefault();

						var id    = jQuery( this ).attr( 'data-id' );
						var imgId = jQuery( "#loader-img" ).attr( 'img-id' );
						jQuery( imgId ).show();
						var link = ai_scribe.ajaxUrl;
						var x    = confirm( "Are you sure you want to delete?" );
						if ( x ) {
							jQuery.ajax(
								{
									type: "post",
									url: link,
									data: {
										action: 'al_scribe_remove_short_code_content',
										id: id
									},
									success: function ( response ) {
										jQuery( imgId ).hide();
										location.reload();
									},
								}
							);
						}
					}
				);
			}
		);
	}
)( jQuery )
