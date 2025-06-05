jQuery(document).ready(function () {
	console.log('Localized ai_scribe object:', ai_scribe);
    // Ensure the window load event shows the correct form
    jQuery(window).on('load', function () {
        jQuery("#first").addClass('save-btn');
        jQuery('.second_form').show();
        jQuery('.first_form').hide();
    });

    // Handle click on the first tab
    jQuery('#first').click(function (e) {
        e.preventDefault();

        jQuery(this).addClass('save-btn');
        jQuery('#second').removeClass('save-btn');
        jQuery('.second_form').show();
        jQuery('.first_form').hide();
    });

    // Handle click on the second tab
    jQuery('#second').click(function (e) {
        e.preventDefault();

        jQuery(this).addClass('save-btn');
        jQuery('#first').removeClass('save-btn');
        jQuery('.first_form').show();
        jQuery('.second_form').hide();
    });

    // Submit handler for the first form
    jQuery('#frmFirst').submit(function (e) {
        e.preventDefault();

        // Check if the localized variable exists
        
        if (typeof ai_scribe === 'undefined' || !ai_scribe.nonce || !ai_scribe.ajaxUrl) {
		    console.error('ai_scribe is not defined or missing properties. Please check wp_localize_script in PHP.');
		    alert('Unable to process the request. Please refresh the page and try again.');
		    return;
		}


        // Serialize the form data and include the nonce
        var first_form = jQuery(this).serialize() + '&security=' + ai_scribe.nonce;

        // Perform the AJAX request
        jQuery.ajax({
            type: "POST",
            dataType: "json",
            url: ai_scribe.ajaxUrl,
            data: first_form,
            success: function (response) {
                if (response.success) {
                    alert(response.msg);
                    location.reload();
                } else {
                    alert('Error: ' + (response.msg || 'An unknown error occurred.'));
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('Failed to save settings. Please try again later.');
            }
        });
    });

    // Submit handler for the AI engine form
    jQuery('#al_engine').submit(function (e) {
        e.preventDefault();

        // Check if the localized variable exists
        if (typeof ai_scribe === 'undefined') {
            console.error('ai_scribe is not defined. Please check wp_localize_script in PHP.');
            alert('Unable to process the request. Please refresh the page and try again.');
            return;
        }

        // Serialize the form data and include the nonce
        var ai_engine_frm = jQuery(this).serialize() + '&security=' + ai_scribe.nonce;

        // Perform the AJAX request
        jQuery.ajax({
            type: "POST",
            dataType: "json",
            url: ai_scribe.ajaxUrl,
            data: ai_engine_frm,
            success: function (response) {
                if (response.success) {
                    alert(response.msg);
                    location.reload();
                } else {
                    alert('Error: ' + (response.msg || 'An unknown error occurred.'));
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('Failed to save settings. Please try again later.');
            }
        });
    });
});
