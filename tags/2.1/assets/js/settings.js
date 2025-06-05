jQuery( document ).ready(
	function () {

		jQuery( window ).load(
			function () {
				jQuery( "#first" ).addClass( 'save-btn' );
				jQuery( '.second_form' ).show();
				jQuery( '.first_form' ).hide();
			}
		);

		jQuery( '#first' ).click(
			function ( e ) {
				e.preventDefault();

				jQuery( this ).addClass( 'save-btn' );
				jQuery( '#second' ).removeClass( 'save-btn' );
				jQuery( '.second_form' ).show();
				jQuery( '.first_form' ).hide();

			}
		);

		jQuery( '#second' ).click(
			function ( e ) {
				e.preventDefault();

				jQuery( this ).addClass( 'save-btn' );
				jQuery( '#first' ).removeClass( 'save-btn' );
				jQuery( '.first_form' ).show();
				jQuery( '.second_form' ).hide();
			}
		);

		jQuery( '#frmFirst' ).submit(
			function ( e ) {
				e.preventDefault();
				var first_form = jQuery( this ).serialize();
				var link = ai_scribe.ajaxUrl;
				jQuery.ajax(
					{
						type: "post",
						dataType: 'json',
						url: link,
						data: first_form,
						success: function ( response ) {
							alert( response.msg );
							location.reload();
						},
					}
				);

			}
		);

		jQuery( '#al_engine' ).submit(
			function ( e ) {
				e.preventDefault();
				var ai_engine_frm = jQuery( this ).serialize();
				var link = ai_scribe.ajaxUrl;
				jQuery.ajax(
					{
						type: "post",
						dataType: 'json',
						url: link,
						data: ai_engine_frm,
						success: function ( response ) {
							alert( response.msg );
							location.reload();
						},
					}
				);

			}
		);

	}
);
