jQuery(document).ready(function () {
	if (window.aiScribeDebugMode) console.log('Localized ai_scribe object:', ai_scribe);
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
		    if (window.aiScribeDebugMode) console.error('ai_scribe is not defined or missing properties. Please check wp_localize_script in PHP.');
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
                    alert(response.data.msg || response.msg || 'Settings saved successfully!');
                    location.reload();
                } else {
                    // Handle both WordPress format (response.data.msg) and custom format (response.msg)
                    var errorMsg = 'An unknown error occurred.';
                    if (response.data && response.data.msg) {
                        errorMsg = response.data.msg;
                    } else if (response.msg) {
                        errorMsg = response.msg;
                    } else if (response.data && typeof response.data === 'string') {
                        errorMsg = response.data;
                    }
                    alert('Error: ' + errorMsg);
                }
            },
            error: function (xhr, status, error) {
                if (window.aiScribeDebugMode) console.error('AJAX Error:', status, error);
                alert('Failed to save settings. Please try again later.');
            }
        });
    });

    // Submit handler for the AI engine form
    jQuery('#al_engine').submit(function (e) {
        e.preventDefault();

        // Check if the localized variable exists
        if (typeof ai_scribe === 'undefined') {
            if (window.aiScribeDebugMode) console.error('ai_scribe is not defined. Please check wp_localize_script in PHP.');
            alert('Unable to process the request. Please refresh the page and try again.');
            return;
        }

        // Validate API keys before submission
        const selectedModel = jQuery('select[name="model"]').val();
        const isAnthropic = selectedModel && selectedModel.startsWith('claude-');
        const isOpenAI = selectedModel && !isAnthropic;
        const imageGenerationEnabled = jQuery('input[name="enable_image_generation"]').is(':checked');

        const openaiKey = jQuery('input[name="api_key"]').val().trim();
        const anthropicKey = jQuery('input[name="anthropic_api_key"]').val().trim();

        // Check required API keys
        if (isAnthropic && !anthropicKey) {
            alert('Anthropic API key is required for Claude models. Please add your Anthropic API key before saving.');
            jQuery('#anthropic_api_key').focus();
            return;
        }

        if (isOpenAI && !openaiKey) {
            alert('OpenAI API key is required for GPT models. Please add your OpenAI API key before saving.');
            jQuery('#openai_api_key').focus();
            return;
        }

        // For Anthropic models with image generation enabled, OpenAI key is also required
        if (isAnthropic && imageGenerationEnabled && !openaiKey) {
            alert('Both Anthropic and OpenAI API keys are required when using Claude models with image generation enabled. OpenAI key is needed for image generation.');
            jQuery('#openai_api_key').focus();
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
                    // Create a more specific success message based on what was saved
                    var selectedModel = jQuery('#model').val() || 'Unknown';
                    var successMsg = response.data.msg || response.msg || 'Settings saved successfully!';

                    // Enhance the message with model information
                    if (successMsg.includes('Your settings have been updated successfully')) {
                        var provider = '';
                        if (selectedModel.includes('gpt') || selectedModel.includes('o3')) {
                            provider = 'OpenAI';
                        } else if (selectedModel.includes('claude')) {
                            provider = 'Anthropic';
                        }

                        if (provider) {
                            successMsg = provider + ' settings saved successfully! Model: ' + selectedModel;
                        }
                    }

                    alert(successMsg);
                    location.reload();
                } else {
                    // Handle both WordPress format (response.data.msg) and custom format (response.msg)
                    var errorMsg = 'An unknown error occurred.';
                    if (response.data && response.data.msg) {
                        errorMsg = response.data.msg;
                    } else if (response.msg) {
                        errorMsg = response.msg;
                    } else if (response.data && typeof response.data === 'string') {
                        errorMsg = response.data;
                    }
                    alert('Error: ' + errorMsg);
                }
            },
            error: function (xhr, status, error) {
                if (window.aiScribeDebugMode) console.error('AJAX Error:', status, error);
                alert('Failed to save settings. Please try again later.');
            }
        });
    });

    // Model-specific parameter visibility
    function toggleParameterVisibility() {
        const selectedModel = jQuery('#model-select').val();
        const isO3 = selectedModel && (selectedModel === 'o3' || selectedModel === 'o3-mini');
        const isOpenAI = selectedModel && (selectedModel.startsWith('gpt-') || isO3);
        const isAnthropic = selectedModel && selectedModel.startsWith('claude-');

        if (window.aiScribeDebugMode) console.log('Selected model:', selectedModel, 'isO3:', isO3, 'isOpenAI:', isOpenAI, 'isAnthropic:', isAnthropic);

        // Hide all parameters first using the new form-row structure
        const allParams = ['temp', 'top_p', 'best_oi', 'freq_pent', 'Presence_penalty'];
        allParams.forEach(function(param) {
            const paramDiv = jQuery('input[name="' + param + '"]').closest('.form-row');
            paramDiv.hide();
        });

        // Hide both temperature and reasoning effort inputs initially
        jQuery('#temperature-input').hide();
        jQuery('#reasoning-effort-select').hide();

        // Show model-specific parameters
        if (isO3) {
            // o3 models use reasoning effort dropdown instead of temperature
            if (window.aiScribeDebugMode) console.log('Showing reasoning effort dropdown for o3 model');
            jQuery('#reasoning-effort-select').show();
        } else if (isOpenAI) {
            // Standard OpenAI models support all parameters including temperature
            jQuery('#temperature-input').show();
            const openaiParams = ['top_p', 'best_oi', 'freq_pent', 'Presence_penalty'];
            openaiParams.forEach(function(param) {
                const paramDiv = jQuery('input[name="' + param + '"]').closest('.form-row');
                paramDiv.show();
            });
        } else if (isAnthropic) {
            // Anthropic models support temperature and top_p only
            jQuery('#temperature-input').show();
            const anthropicParams = ['top_p'];
            anthropicParams.forEach(function(param) {
                const paramDiv = jQuery('input[name="' + param + '"]').closest('.form-row');
                paramDiv.show();
            });
        }

        // Show/hide API key sections based on model and image generation
        const openaiKeySection = jQuery('#openai_api_key').closest('.gform');
        const anthropicKeySection = jQuery('#anthropic_api_key').closest('.gform');
        const imageGenerationEnabled = jQuery('#enable_image_generation').is(':checked');

        // Always show the appropriate key for the selected model
        if (isOpenAI) {
            openaiKeySection.show();
            anthropicKeySection.hide(); // Hide Anthropic key for OpenAI models
        } else if (isAnthropic) {
            anthropicKeySection.show();
            // Show OpenAI key if image generation is enabled
            if (imageGenerationEnabled) {
                openaiKeySection.show();
            } else {
                openaiKeySection.hide();
            }
        } else {
            // No model selected - show both
            openaiKeySection.show();
            anthropicKeySection.show();
        }

        // Handle image generation requirements - allow for all models but require OpenAI key
        updateImageGenerationAvailability();

        // Update API key section message based on model and image generation
        const apiMessage = jQuery('.api-keys-section p');
        if (isAnthropic) {
            if (imageGenerationEnabled) {
                apiMessage.html('<strong>Important:</strong> You\'ve selected a Claude model with image generation enabled. You need <strong>both</strong> an Anthropic API key (for text) and an OpenAI API key (for images).');
            } else {
                apiMessage.html('<strong>Important:</strong> You\'ve selected a Claude model. You need an Anthropic API key for text generation. OpenAI key not required since image generation is disabled.');
            }
        } else if (isOpenAI) {
            if (imageGenerationEnabled) {
                apiMessage.html('<strong>Important:</strong> You\'ve selected an OpenAI model with image generation enabled. You need an OpenAI API key for both text and image generation.');
            } else {
                apiMessage.html('<strong>Important:</strong> You\'ve selected an OpenAI model. You need an OpenAI API key for text generation.');
            }
        } else {
            apiMessage.html('<strong>Important:</strong> You need an Anthropic API key for Claude models and an OpenAI API key for GPT models and image generation. If you plan to use Claude models with image generation, you\'ll need <strong>both</strong> API keys.');
        }
    }

    // New function to handle image generation availability
    function updateImageGenerationAvailability() {
        const openaiKey = jQuery('#openai_api_key').val().trim();
        const imageCheckbox = jQuery('#enable_image_generation');
        const selectedModel = jQuery('#model-select').val();
        const isAnthropic = selectedModel && selectedModel.startsWith('claude-');
        const isOpenAI = selectedModel && (selectedModel.startsWith('gpt-') || selectedModel === 'o3' || selectedModel === 'o3-mini');

        // Always enable the checkbox - let users choose
        imageCheckbox.prop('disabled', false);

        // Update the description and show appropriate hints
        if (!openaiKey) {
            // No OpenAI key - show helpful messages
            if (isAnthropic) {
                showImageGenerationHint('claude');
            } else if (isOpenAI) {
                showImageGenerationHint('openai');
            } else {
                showImageGenerationHint('general');
            }
            
            // If checkbox is checked but no key, show warning
            if (imageCheckbox.is(':checked')) {
                jQuery('#image-generation-settings').show();
                showImageKeyWarning();
            } else {
                jQuery('#image-generation-settings').hide();
                hideImageKeyWarning();
            }
        } else {
            // OpenAI key present - hide hints and warnings
            hideImageGenerationHint();
            hideImageKeyWarning();
            
            // Show settings if checkbox is checked
            if (imageCheckbox.is(':checked')) {
                jQuery('#image-generation-settings').show();
            } else {
                jQuery('#image-generation-settings').hide();
            }
        }
    }

    // Show hint for users about image generation requirements
    function showImageGenerationHint(type) {
        let hint = jQuery('#image-generation-hint');
        let message = '';
        
        switch(type) {
            case 'claude':
                message = '<strong>💡 Want images with Claude?</strong> Add your OpenAI API key above to enable AI image generation alongside Claude text generation.';
                break;
            case 'openai':
                message = '<strong>⚠️ OpenAI API Key Required</strong> Add your OpenAI API key above to enable image generation.';
                break;
            default:
                message = '<strong>💡 Image Generation Available</strong> Add your OpenAI API key above to enable AI image generation with any text model.';
        }
        
        if (hint.length === 0) {
            hint = jQuery('<div id="image-generation-hint" style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 10px; margin: 10px 0; color: #856404;"></div>');
            jQuery('#image-generation-description').after(hint);
        }
        
        hint.html(message).show();
    }

    // Show warning when image generation is enabled but no OpenAI key
    function showImageKeyWarning() {
        let warning = jQuery('#image-key-warning');
        if (warning.length === 0) {
            warning = jQuery('<div id="image-key-warning" style="background: #f8d7da; border: 1px solid #dc3545; border-radius: 4px; padding: 10px; margin: 10px 0; color: #721c24;"><strong>⚠️ Warning:</strong> Image generation is enabled but no OpenAI API key is provided. Images will not be generated until you add an OpenAI API key above.</div>');
            jQuery('#image-generation-settings').before(warning);
        }
        warning.show();
    }

    // Hide the hint
    function hideImageGenerationHint() {
        jQuery('#image-generation-hint').hide();
    }

    // Hide the warning
    function hideImageKeyWarning() {
        jQuery('#image-key-warning').hide();
    }

    // Initialize parameter visibility on page load
    toggleParameterVisibility();
    
    // Initialize image generation settings visibility
    toggleImageGenerationSettings();

    // Update parameter visibility when model changes
    jQuery('#model-select').on('change', function() {
        toggleParameterVisibility();
        updateImageGenerationAvailability();
    });
    
    // Handle image generation toggle
    function toggleImageGenerationSettings() {
        const isEnabled = jQuery('#enable_image_generation').is(':checked');
        const openaiKey = jQuery('#openai_api_key').val().trim();
        
        // Show/hide image generation settings
        if (isEnabled) {
            jQuery('#image-generation-settings').show();
            // Show warning if no OpenAI key
            if (!openaiKey) {
                showImageKeyWarning();
            } else {
                hideImageKeyWarning();
            }
        } else {
            jQuery('#image-generation-settings').hide();
            hideImageKeyWarning();
        }
        
        // Update parameter visibility and API key requirements
        toggleParameterVisibility();
        validateApiKeys();
    }
    
    // Update parameter visibility when image generation toggle changes
    jQuery('#enable_image_generation').on('change', toggleImageGenerationSettings);
    
    // Add visual feedback for required API keys
    function validateApiKeys() {
        const selectedModel = jQuery('#model-select').val();
        const isO3 = selectedModel && (selectedModel === 'o3' || selectedModel === 'o3-mini');
        const isOpenAI = selectedModel && (selectedModel.startsWith('gpt-') || isO3);
        const isAnthropic = selectedModel && selectedModel.startsWith('claude-');
        const imageGenerationEnabled = jQuery('#enable_image_generation').is(':checked');
        
        const openaiKey = jQuery('#openai_api_key').val().trim();
        const anthropicKey = jQuery('#anthropic_api_key').val().trim();
        
        // Reset styles
        jQuery('#openai_api_key, #anthropic_api_key').removeClass('required-missing');
        
        // Check requirements and add visual indicators
        if (isOpenAI && !openaiKey) {
            jQuery('#openai_api_key').addClass('required-missing');
        }
        
        // Image generation validation is handled by updateImageGenerationAvailability()
        // Just check for required keys based on current selections
        if (isAnthropic && !anthropicKey) {
            jQuery('#anthropic_api_key').addClass('required-missing');
        }
        if (isAnthropic && imageGenerationEnabled && !openaiKey) {
            jQuery('#openai_api_key').addClass('required-missing');
        }
    }
    
    // Validate API keys on model change and key input
    jQuery('#model-select, #enable_image_generation').on('change', validateApiKeys);
    jQuery('#openai_api_key, #anthropic_api_key').on('input', function() {
        validateApiKeys();
        updateImageGenerationAvailability();
        toggleImageGenerationSettings(); // Update image settings visibility
    });
    
    // Initialize validation and parameter visibility
    validateApiKeys();
    toggleParameterVisibility();
});
