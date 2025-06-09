
// Cache bust: 2025-01-09 14:30
const myObj = {};

// Cost Calculator Object
const CostCalculator = {
    // Dynamic pricing data - fetched from backend
    modelPricing: {},
    pricingLoaded: false,

    currentModel: 'gpt-4o',
    estimatedTokens: 8000, // Default estimate for full article
    actualCost: 0,

    init: function() {
        this.loadDynamicPricing();
        this.getCurrentModel();
    },

    loadDynamicPricing: function() {
        const self = this;
        jQuery.ajax({
            type: 'POST',
            url: ai_scribe.ajaxUrl,
            data: {
                action: 'ai_scribe_get_pricing',
                security: ai_scribe.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    if (window.aiScribeDebugMode) console.log('💰 Dynamic pricing loaded:', response.data);
                    self.processPricingData(response.data);
                    self.pricingLoaded = true;
                    self.updateEstimatedCost();
                } else {
                    if (window.aiScribeDebugMode) console.error('Failed to load pricing data:', response);
                    self.fallbackToPricing();
                }
            },
            error: function(xhr, status, error) {
                if (window.aiScribeDebugMode) console.error('Error loading pricing data:', error);
                self.fallbackToPricing();
            }
        });
    },

    processPricingData: function(data) {
        // Convert API pricing data to internal format
        this.modelPricing = {};

        // Process OpenAI models
        if (data.openai) {
            for (const [model, pricing] of Object.entries(data.openai)) {
                this.modelPricing[model] = {
                    input: pricing.input,  // Already per 1K tokens from PHP
                    output: pricing.output,
                    article_estimate: pricing.article_estimate,
                    unit: pricing.unit
                };
            }
        }

        // Process Anthropic models
        if (data.anthropic) {
            for (const [model, pricing] of Object.entries(data.anthropic)) {
                this.modelPricing[model] = {
                    input: pricing.input,  // Already per 1K tokens from PHP
                    output: pricing.output,
                    article_estimate: pricing.article_estimate,
                    unit: pricing.unit
                };
            }
        }

        if (window.aiScribeDebugMode) console.log('💰 Processed pricing data:', this.modelPricing);
    },

    fallbackToPricing: function() {
        // Fallback pricing if dynamic loading fails (accurate January 2025 pricing)
        if (window.aiScribeDebugMode) console.warn('💰 Using fallback pricing data');
        this.modelPricing = {
            // OpenAI Models (per 1K tokens) - updated pricing
            'gpt-4o': { input: 0.005, output: 0.02, article_estimate: 0.06 },
            'gpt-4o-mini': { input: 0.0006, output: 0.0024, article_estimate: 0.007 },
            'gpt-4.5-preview': { input: 0.075, output: 0.15, article_estimate: 0.45 },
            'o3': { input: 0.01, output: 0.04, article_estimate: 0.12 },
            'o3-mini': { input: 0.001, output: 0.004, article_estimate: 0.012 },
            // Anthropic Models (per 1K tokens) - updated pricing
            'claude-sonnet-4-20250514': { input: 0.003, output: 0.015, article_estimate: 0.043 },
            'claude-opus-4-20250514': { input: 0.015, output: 0.075, article_estimate: 0.214 }
        };
        this.pricingLoaded = true;
        if (window.aiScribeDebugMode) console.log('💰 Fallback pricing loaded:', this.modelPricing);
        this.updateEstimatedCost();
    },

    // Accurate token estimation based on content analysis
    estimateTokens: function(text, model) {
        if (!text || text.trim() === '') {
            return 0;
        }

        text = text.trim();
        const isAnthropic = model && model.toLowerCase().includes('claude');

        // Different models have different tokenization patterns
        const charsPerToken = isAnthropic ? 3.7 : 4.0;

        // Count characters
        const charCount = text.length;

        // Basic token estimation
        let estimatedTokens = Math.ceil(charCount / charsPerToken);

        // Adjust for complexity
        const wordCount = text.split(/\s+/).length;
        const punctuationCount = (text.match(/[^\w\s]/g) || []).length;
        const numberCount = (text.match(/\d+/g) || []).length;

        // Add overhead for complex content
        let complexityFactor = 1.0;
        if (punctuationCount > wordCount * 0.1) {
            complexityFactor += 0.1; // Heavy punctuation
        }
        if (numberCount > wordCount * 0.05) {
            complexityFactor += 0.05; // Numbers present
        }

        estimatedTokens = Math.ceil(estimatedTokens * complexityFactor);

        return Math.max(1, estimatedTokens);
    },

    // Get current input text for estimation
    getCurrentInputText: function() {
        // Try to get text from the active input field
        const activeInput = jQuery('.at_temp_sec.active_page .action_val_field');
        if (activeInput.length && activeInput.val()) {
            return activeInput.val();
        }

        // Fallback to first input field
        const firstInput = jQuery('#tab_input');
        if (firstInput.length && firstInput.val()) {
            return firstInput.val();
        }

        // Default sample text for estimation
        return "Write a comprehensive article about artificial intelligence and its impact on modern business operations.";
    },

    // Accurate token estimation based on content analysis
    estimateTokens: function(text, model) {
        if (!text || text.trim() === '') {
            return 0;
        }

        text = text.trim();
        const isAnthropic = model && model.toLowerCase().includes('claude');

        // Different models have different tokenization patterns
        const charsPerToken = isAnthropic ? 3.7 : 4.0;

        // Count characters
        const charCount = text.length;

        // Basic token estimation
        let estimatedTokens = Math.ceil(charCount / charsPerToken);

        // Adjust for complexity
        const wordCount = text.split(/\s+/).length;
        const punctuationCount = (text.match(/[^\w\s]/g) || []).length;
        const numberCount = (text.match(/\d+/g) || []).length;

        // Add overhead for complex content
        let complexityFactor = 1.0;
        if (punctuationCount > wordCount * 0.1) {
            complexityFactor += 0.1; // Heavy punctuation
        }
        if (numberCount > wordCount * 0.05) {
            complexityFactor += 0.05; // Numbers present
        }

        estimatedTokens = Math.ceil(estimatedTokens * complexityFactor);

        return Math.max(1, estimatedTokens);
    },

    // Estimate tokens for complete article generation
    estimateArticleTokens: function(inputText, model, action) {
        const baseInputTokens = this.estimateTokens(inputText, model);

        // Output multipliers based on action type
        const outputMultipliers = {
            'title': 0.2,
            'keyword': 0.3,
            'heading': 0.8,
            'intro': 1.5,
            'article': 8.0,
            'conclusion': 1.2,
            'qna': 2.0,
            'seo-meta-data': 0.5,
            'evaluate': 3.0,
            'review': 2.5
        };

        const outputMultiplier = outputMultipliers[action] || 2.0;
        const estimatedOutputTokens = Math.ceil(baseInputTokens * outputMultiplier);

        // Add system prompt overhead (approximately 200-500 tokens)
        const systemPromptTokens = 350;

        // Add conversation context overhead
        const contextOverhead = Math.ceil(baseInputTokens * 0.1);

        const totalInputTokens = baseInputTokens + systemPromptTokens + contextOverhead;
        const totalOutputTokens = estimatedOutputTokens;

        return {
            input_tokens: totalInputTokens,
            output_tokens: totalOutputTokens,
            total_tokens: totalInputTokens + totalOutputTokens
        };
    },
    
    getCurrentModel: function() {
        // Try to get current model from settings or form
        const modelSelect = jQuery('#model');
        if (modelSelect.length) {
            this.currentModel = modelSelect.val() || 'gpt-4o';
            if (window.aiScribeDebugMode) console.log('💰 Initial model from form:', this.currentModel);
            const self = this;
            modelSelect.on('change', function() {
                self.currentModel = jQuery(this).val();
                if (window.aiScribeDebugMode) console.log('💰 Model changed to:', self.currentModel);
                self.updateEstimatedCost();
            });
        } else {
            // Try to get from AI engine settings if no model select found
            if (typeof ai_scribe !== 'undefined' && ai_scribe.aiEngine && ai_scribe.aiEngine.model) {
                this.currentModel = ai_scribe.aiEngine.model;
                if (window.aiScribeDebugMode) console.log('💰 Model from ai_scribe settings:', this.currentModel);
            } else {
                if (window.aiScribeDebugMode) console.log('💰 No model found, using default gpt-4o');
                this.currentModel = 'gpt-4o';
            }
        }

        // Also listen for changes on settings page model selector
        jQuery(document).on('change', 'select[name="model"]', function() {
            const newModel = jQuery(this).val();
            if (window.aiScribeDebugMode) console.log('💰 Settings model changed to:', newModel);
            CostCalculator.currentModel = newModel;
            CostCalculator.updateEstimatedCost();
        });
    },

    updateEstimatedCost: function() {
        if (window.aiScribeDebugMode) console.log('💰 updateEstimatedCost called - pricingLoaded:', this.pricingLoaded, 'currentModel:', this.currentModel);

        // Check if cost display elements exist
        const estimatedElement = jQuery('#estimated-cost');
        const actualElement = jQuery('#actual-cost');
        if (window.aiScribeDebugMode) console.log('💰 Cost elements found - estimated:', estimatedElement.length, 'actual:', actualElement.length);

        if (!this.pricingLoaded) {
            if (window.aiScribeDebugMode) console.log('💰 Pricing not loaded yet, skipping cost update');
            return;
        }

        const pricing = this.modelPricing[this.currentModel];
        if (window.aiScribeDebugMode) console.log('💰 Pricing for model', this.currentModel, ':', pricing);
        if (window.aiScribeDebugMode) console.log('💰 Available models in pricing:', Object.keys(this.modelPricing));

        if (pricing) {
            // Use article estimate if available, otherwise calculate based on tokens
            let totalEstimated;

            if (pricing.article_estimate) {
                // Use the pre-calculated article estimate
                totalEstimated = pricing.article_estimate;
                if (window.aiScribeDebugMode) console.log('💰 Using article estimate:', totalEstimated);
            } else {
                // Get current input text and estimate tokens accurately
                const inputText = this.getCurrentInputText();
                const inputTokens = this.estimateTokens(inputText, this.currentModel);

                // Estimate output tokens (typically 6-8x input for full articles)
                const outputTokens = inputTokens * 7;

                // Add system prompt overhead (~350 tokens)
                const systemTokens = 350;
                const totalInputTokens = inputTokens + systemTokens;

                // Calculate cost
                const inputCost = (totalInputTokens / 1000) * pricing.input;
                const outputCost = (outputTokens / 1000) * pricing.output;
                totalEstimated = inputCost + outputCost;

                if (window.aiScribeDebugMode) console.log('💰 Detailed cost calculation:', {
                    model: this.currentModel,
                    inputText: inputText.substring(0, 50) + '...',
                    inputTokens: inputTokens,
                    outputTokens: outputTokens,
                    systemTokens: systemTokens,
                    totalInputTokens: totalInputTokens,
                    inputCost: inputCost,
                    outputCost: outputCost,
                    totalEstimated: totalEstimated,
                    pricing: pricing
                });
            }

            jQuery('#estimated-cost').text('$' + totalEstimated.toFixed(3));
        } else {
            if (window.aiScribeDebugMode) console.warn('💰 No pricing data for model:', this.currentModel);
            if (window.aiScribeDebugMode) console.warn('💰 Available pricing models:', Object.keys(this.modelPricing));
            jQuery('#estimated-cost').text('$0.000');
        }
    },

    addActualCost: function(inputTokens, outputTokens, model) {
        const usedModel = model || this.currentModel;
        const pricing = this.modelPricing[usedModel];

        if (pricing && inputTokens && outputTokens) {
            const stepCost = (inputTokens / 1000 * pricing.input) + (outputTokens / 1000 * pricing.output);
            this.actualCost += stepCost;

            if (window.aiScribeDebugMode) console.log('💰 Adding actual cost:', {
                model: usedModel,
                inputTokens: inputTokens,
                outputTokens: outputTokens,
                stepCost: stepCost,
                totalActualCost: this.actualCost
            });

            jQuery('#actual-cost').text('$' + this.actualCost.toFixed(3));
        } else {
            if (window.aiScribeDebugMode) console.warn('💰 Cannot calculate cost - missing data:', {
                pricing: !!pricing,
                inputTokens: inputTokens,
                outputTokens: outputTokens,
                model: usedModel
            });
        }
    },

    resetActualCost: function() {
        this.actualCost = 0;
        jQuery('#actual-cost').text('$0.000');
        if (window.aiScribeDebugMode) console.log('💰 Actual cost reset');
    }
};

jQuery( document ).ready( function () {
	if (window.aiScribeDebugMode) console.log('Localized ai_scribe object:', ai_scribe);

	// Check if cost calculator elements exist on page load
	if (window.aiScribeDebugMode) console.log('💰 Cost calculator elements on page load - estimated:', jQuery('#estimated-cost').length, 'actual:', jQuery('#actual-cost').length);

	// Initialize cost calculator
	CostCalculator.init();

	// Add listener for model changes in settings
	jQuery(document).on('change', 'select[name="model"]', function() {
		const newModel = jQuery(this).val();
		if (window.aiScribeDebugMode) console.log('💰 Model changed in settings to:', newModel);
		CostCalculator.currentModel = newModel;
		CostCalculator.updateEstimatedCost();
	});

	/* 06.07.23 - listen user hitting the enter button */
  	// Add event listener to input fields
  	jQuery('.keywords').on('keydown', function(event) {
    	// Check if the key pressed is the enter key
    	if (event.keyCode === 13) {
      		// Find the corresponding button and trigger the click event
      		jQuery(this).next('.tab_generate_btn').click();
      		event.preventDefault(); // Prevent the default form submission
    	}
  	}); 




	// Add event listeners to the writing style and tone fields to detect changes and trigger AJAX save to database
    jQuery('#lang, #writingStyle, #writingTone').on('change', function() {
        if (typeof ai_scribe === 'undefined' || !ai_scribe.nonce || !ai_scribe.ajaxUrl) {
		    if (window.aiScribeDebugMode) console.error('ai_scribe is not defined or missing properties. Please check wp_localize_script in PHP.');
		    alert('Unable to process the request. Please refresh the page and try again.');
		    return;
		}

		var linkaction = ai_scribe.ajaxUrl;

		// Ensure nonce is available
        if (!ai_scribe.nonce) {
            if (window.aiScribeDebugMode) console.error('Security nonce is missing.');
            alert('Security issue detected. Please refresh the page.');
            return;
        }


        // Capture the new values
        var language = jQuery('#lang').val();
        var writingStyle = jQuery('#writingStyle').val();
        var writingTone = jQuery('#writingTone').val();
        //alert(language);

        // AJAX request to save updated style and tone to the backend


		jQuery.ajax({
		    type: "POST",
		    url: linkaction,
		    data: {
		        action: "update_style_tone",
		        security: ai_scribe.nonce,
		        writing_style: writingStyle,
		        writing_tone: writingTone,
		        language: language
		    },
		    success: function(response) {
		        if (response.success) {
		            alert("Updated successfully");
		        } else {
		            alert(response.data.msg || "Unknown error occurred.");
		        }
		    },
		    error: function(xhr) {
		        try {
		            const response = JSON.parse(xhr.responseText);
		            if (response.nonce_expired) {
		                alert("Session expired. Refreshing...");
		                location.reload();
		            } else {
		                alert(response.msg || "Error saving style and tone.");
		            }
		        } catch (e) {
		            alert("An unexpected error occurred.");
		        }
		    }
		});


    });


	// Check API keys based on selected model
	var selectedModel = ai_scribe.aiEngine?.model || '';
	var openaiKey = ai_scribe.apiKey || '';
	var anthropicKey = ai_scribe.aiEngine?.anthropic_api_key || '';

	if (window.aiScribeDebugMode) console.log('AI Scribe JS Debug - Selected Model:', selectedModel);
	if (window.aiScribeDebugMode) console.log('AI Scribe JS Debug - OpenAI Key Length:', openaiKey.length);
	if (window.aiScribeDebugMode) console.log('AI Scribe JS Debug - Anthropic Key Length:', anthropicKey.length);

	// Define Anthropic models - updated to include newer models
	var anthropicModels = [
		'claude-3-5-sonnet-20241022',
		'claude-3-opus-20240229',
		'claude-3-sonnet-20240229',
		'claude-3-haiku-20240307',
		'claude-sonnet-4-20250514',
		'claude-opus-4-20250514',
		'claude-3-5-sonnet-20250514',
		// Add any model that contains 'claude' for future compatibility
	];

	// Enhanced model detection - check if model name contains 'claude'
	var isAnthropicModel = anthropicModels.includes(selectedModel) || selectedModel.toLowerCase().includes('claude');

	var needsOpenAI = !isAnthropicModel && selectedModel !== ''; // Only OpenAI models (not empty)
	var needsAnthropic = isAnthropicModel;

	if (window.aiScribeDebugMode) console.log('AI Scribe JS Debug - Is Anthropic Model:', isAnthropicModel);
	if (window.aiScribeDebugMode) console.log('AI Scribe JS Debug - Needs OpenAI:', needsOpenAI);
	if (window.aiScribeDebugMode) console.log('AI Scribe JS Debug - Needs Anthropic:', needsAnthropic);

	// Check required API keys
	var missingKeys = [];
	if (needsOpenAI && openaiKey.length === 0) {
		missingKeys.push('OpenAI API key');
		if (window.aiScribeDebugMode) console.log('AI Scribe JS Debug - Missing OpenAI key');
	}
	if (needsAnthropic && anthropicKey.length === 0) {
		missingKeys.push('Anthropic API key');
		if (window.aiScribeDebugMode) console.log('AI Scribe JS Debug - Missing Anthropic key');
	}

	if (window.aiScribeDebugMode) console.log('AI Scribe JS Debug - Missing Keys:', missingKeys);

	if (missingKeys.length > 0) {
		var message = "Please add your " + missingKeys.join(' and ') + " in the settings page depending on which provider and model you want to use.\n\n";
		if (needsOpenAI) {
			message += "• For OpenAI models (GPT-4o, GPT-4.5, GPT-4o-mini, o3):\n  https://beta.openai.com/signup\n\n";
		}
		if (needsAnthropic) {
			message += "• For Anthropic models (Sonnet 4 or Opus 4):\n  https://console.anthropic.com/login\n\n";
		}
		if (!needsOpenAI && !needsAnthropic) {
			message += "• For OpenAI models (GPT-4o, GPT-4.5, GPT-4o-mini, o3):\n  https://beta.openai.com/signup\n\n";
			message += "• For Anthropic models (Sonnet 4 or Opus 4):\n  https://console.anthropic.com/login";
		}
		if (window.aiScribeDebugMode) console.log('AI Scribe JS Debug - Showing popup with message:', message);
		alert(message);
		window.location = ai_scribe.settingsUrl;
	} else {
		if (window.aiScribeDebugMode) console.log('AI Scribe JS Debug - All required API keys are present');
	}

	if ( !ai_scribe.checkArr.addQNA ) {
		jQuery( '#conclusionCont' ).attr( 'data-nextstep', '9' );
		jQuery( '#conclusionSkip' ).attr( 'data-nextstep', '9' );
		jQuery( '#metadataBack' ).attr( 'data-nextstep', '7' );
	}

	jQuery( window ).load( function () {
		var dataSet = jQuery( '.active_step' ).attr( 'data-step' );
		var currentObj = jQuery( '.at_temp_sec_' + dataSet ).addClass( 'active_page' );
		allSiteInputs( currentObj );

	} );
	hideShowElement();
	var toolbarOptions = [
		['bold', 'italic', 'underline', 'strike'],
		['blockquote', 'code-block'],

		[{'header': 1}, {'header': 2}],
		[{'list': 'ordered'}, {'list': ' '}],
		[{'script': 'sub'}, {'script': 'super'}],
		[{'indent': '-1'}, {'indent': '+1'}],
		[{'direction': 'rtl'}],
		[{'size': ['small', false, 'large', 'huge']}],
		[{'header': [1, 2, 3, 4, 5, 6, false]}],

		[{'color': []}, {'background': []}],
		[{'font': []}],
		[{'align': []}],

		['clean']
	];


	var quill = new Quill('.editorjs', {
	    modules: {
	        toolbar: toolbarOptions
	    },
	    placeholder: 'Compose an epic...',
	    theme: 'snow'
	});
	window.alok = quill; // Assign the Quill instance to the global variable 'alok'

	// Use alok to manipulate content in .editorjs
	const delta = alok.clipboard.convert('<div class="ul1"></div>');
	alok.setContents(delta); // Set content correctly for .editorjs

	// Set up the second Quill instance for .editorjs2
	var quillReview = new Quill('.editorjs2', {
	    modules: {
	        toolbar: toolbarOptions
	    },
	    placeholder: 'Compose an epic...',
	    theme: 'snow'
	});
	window.finalreview = quillReview; // Assign the second Quill instance to 'finalreview'


	// Use finalreview to manipulate content in .editorjs2
	const deltaReview = finalreview.clipboard.convert('<div class="main-div3 title_class"></div>');
	finalreview.setContents(deltaReview); // Set content correctly for .editorjs2




	jQuery( '.generate_more_btn' ).attr( 'disabled', true );
	jQuery( '.tab_regenerate_btn' ).attr( 'disabled', true );

	jQuery( 'body' ).on( 'click', '.copy_button', function () {
		var thisVal = jQuery( this ).closest( '.copycontent' ).find( '.get_checked' ).val();
		var copyText = thisVal.replace( thisVal.match( /(\d+)/g ), '' ).replace( '.', '' ).trim();
		navigator.clipboard.writeText( copyText );
		jQuery( this ).val( 'Copied' );
		setTimeout( () => {
			jQuery( '.copy_button' ).val( 'Copy' );
		}, 1000 );
	} );

	jQuery( "body" ).on( 'click', '.generate_title :checkbox', function () {
		jQuery( '.generate_title input' ).removeAttr( 'checked' );
		jQuery( this ).prop( 'checked', true );
	} );
	jQuery( '#keywordback' ).click( function () {
		jQuery( 'input[name="get_checked"]:checked' ).prop( 'checked', false );

		var editor_content = quill.root.innerHTML = '';
	} );

	let originalContent;
	var toc = "";
	var checkPromptOpt = "";
	
	let tocCreated = false;

	function generateTOC() {
	    if (tocCreated) {
	        if (window.aiScribeDebugMode) console.warn("TOC already created. Skipping generation.");
	        return;
	    }
	    tocCreated = true;

	    const article = document.querySelector('.editorjs2 .ql-editor');
	    if (!article || !(article instanceof HTMLElement)) {
	        if (window.aiScribeDebugMode) console.error("Article element not found. Please check the selector.");
	        return;
	    }

	    // Capture the original content before modifying it
	    originalContent = finalreview.getContents(); // Get the current content as a Delta

	    let tocHTML = '<ul class="toc">';
	    let currentLevel = 2;

	    const headings = article.querySelectorAll("h2, h3, h4, h5");
	    headings.forEach((heading, index) => {
	        const level = parseInt(heading.tagName.slice(1));

	        while (currentLevel < level) {
	            tocHTML += '<ul class="toc">';
	            currentLevel++;
	        }

	        while (currentLevel > level) {
	            tocHTML += '</ul>';
	            currentLevel--;
	        }

	        tocHTML += `<li><a href="#heading-${index}">${heading.textContent}</a></li>`;
	        heading.id = `heading-${index}`;
	    });

	    // Close all opened lists
	    while (currentLevel > 2) {
	        tocHTML += '</ul>';
	        currentLevel--;
	    }
	    tocHTML += '</ul>';

	    // Ensure proper separation of the TOC with new paragraphs and line breaks
	    const wrappedTOC = `<p>&nbsp;</p><h2>Table of Contents</h2>${tocHTML}<p></p>`; // Adding paragraphs around the TOC

	    // Find the position after the first paragraph or text block
	    const firstParagraph = article.querySelector('p'); // Look for the first paragraph in the article
	    if (firstParagraph) {
	        const firstParagraphIndex = finalreview.getText(0, finalreview.getLength()).indexOf(firstParagraph.textContent) + firstParagraph.textContent.length;

	        // Insert the TOC after the first paragraph
	        finalreview.clipboard.dangerouslyPasteHTML(firstParagraphIndex + 1, wrappedTOC); // Insert TOC after the first paragraph
	    } else {
	        if (window.aiScribeDebugMode) console.error("First paragraph not found. Inserting TOC at the beginning.");
	        // As a fallback, insert the TOC at the beginning if no paragraph is found
	        finalreview.clipboard.dangerouslyPasteHTML(0, wrappedTOC);
	    }

	    if (window.aiScribeDebugMode) console.log("TOC generated and inserted after the first paragraph.");
	}




	function removeStartEnd( input ) {
		const wordsToRemove = ["start-output", "end-output"];

		// Remove quotes from the input string
		const cleanedInput = input.replace( /"/g, '' );

		// Split the input into words using commas and whitespace
		const words = cleanedInput.split( /,\s*/ );

		// Filter out the words to remove
		const filteredWords = words.filter( word => {
			const lowerCaseWord = word.toLowerCase().trim();
			return !wordsToRemove.includes( lowerCaseWord );
		} );
		// Rejoin the filtered words and add quotes back
		return filteredWords.map( word => `"${word}"` ).join( ", " );
	}

	function getInputValueByName(allinput, name) {
	    var result = allinput.filter(function(input) {
	        return input.name === name;
	    });
	    return result.length > 0 ? result[0].value : ''; // Return the value if found, otherwise an empty string
	}

	function allSiteInputs( currentObj ) {
		var getAllCheckElement = allCheckElements();
		var tab_val = currentObj
			.closest( ".maincontent" )
			.find( ".action_val_field" )
			.val();
		var titleVal = tab_val != null ? '"' + tab_val + '"' : " ";
		titleVal = titleVal.replace( /^([\d\W]\.\s*)/, '' );

		var qnaStr = getAllCheckElement.qna.join( "," ).replace( /,/g, "" ).split();
		var conclusionStr = getAllCheckElement.conclusion
		                                      .join( "," )
		                                      .replace( /,/g, "" )
		                                      .split();
		var keyVal = getAllCheckElement.keyword
		                               .join( '", "' )
		                               .replace( /[^\w\s,.]/gi, "" );

		var tagLineVal = '"' + getAllCheckElement.tagline.join( '", "' ) + '"';

		var headingSel = getAllCheckElement.heading.join( '", "' );

		var introSel = getAllCheckElement.intro.join( '", "' );
		var dataStep = "";

		var checkArr = jQuery( "input[name='checkArr[]']" )
			.map( function () {
				return jQuery( this ).val();
			} )
			.get();
		var allinput = jQuery( "form" ).serializeArray();
		var aboveBelowObj = jQuery( ".above_below:checked" ).val();
		var skipbtn = currentObj.attr( "skip-btn" );
		if ( skipbtn == "skip" ) {
			dataStep = currentObj.attr( "data-nextstep" );
		} else {
			dataStep = jQuery( ".temp-progress-bar .active_step" ).attr( "data-step" );
		}

		var promptsData = ai_scribe.promptsData;
		var aiengine = ai_scribe.aiEngine;
		var getcheckArray = ai_scribe.checkArr;
		var autogenerateObj = "";
		


		if ( dataStep == 1 ) {
			autogenerateObj = promptsData.title_prompts;
			jQuery( ".checked-settings input:checked" ).each( function () {
				checkPromptOpt = jQuery( this ).val();
				if ( checkPromptOpt == "addinsertToc" ) {
					toc = jQuery( this ).val();
				}
			} );

		} else if ( dataStep == 2 ) {
			if ( allinput[5].value.length == 0 ) {
				autogenerateObj = promptsData.Keywords_prompts;
			} else {
				autogenerateObj = promptsData.Keywords_prompts;
			}
		} else if ( dataStep == 3 ) {
			autogenerateObj = promptsData.outline_prompts;
			if (
				aiengine.model.includes("gpt-4") || aiengine.model.includes("o3")
			) {
				autogenerateObj = autogenerateObj.replaceAll(
					"and any relevant sub-sections",
					"and no sub-sections"
				);
			}
		} else if ( dataStep == 4 ) {
			autogenerateObj = promptsData.intro_prompts;
		} else if ( dataStep == 5 ) {
			autogenerateObj = promptsData.tagline_prompts;
		} else if ( dataStep == 6 ) {
			autogenerateObj = promptsData.article_prompts;

			if ( skipbtn == "skip" && dataStep == 6 ) {
				autogenerateObj = autogenerateObj.replaceAll(
					"Add a tagline called",
					""
				);
				autogenerateObj = autogenerateObj.replaceAll( "[above/below].", "" );
				autogenerateObj = autogenerateObj.replaceAll( "[The Tagline]", "" );
			}
			
		} else if ( dataStep == 7 ) {
			autogenerateObj = promptsData.conclusion_prompts;
		} else if ( dataStep == 8 ) {
			autogenerateObj = promptsData.qa_prompts;

		
		} else if ( dataStep == 9 ) {
			autogenerateObj = promptsData.meta_prompts;
		}	
		if ( dataStep == 10 ) {
			var articleHtml = jQuery( ".ql-editor" ).html();
			autogenerateObj = articleHtml + conclusionStr + qnaStr + "\n\n" + promptsData.review_prompts;
		} else if ( dataStep == 11 ) {

			if ( typeof originalContent != "undefined" ) {
				var articleHtml = originalContent;
				autogenerateObj = articleHtml + "\n\n" + promptsData.evaluate_prompts;
			} else {
				var articleHtml = jQuery( ".ql-editor" ).html();
				autogenerateObj = articleHtml + conclusionStr + qnaStr + "\n\n" + promptsData.evaluate_prompts;
			}

			if ( getcheckArray.addsubMatter ) {
				autogenerateObj = autogenerateObj + "\Have any authorities on the subject matter been included in the text? If not, list people who could be added.";
			}
			if ( getcheckArray.addimgCont ) {
				autogenerateObj = autogenerateObj + "\nHave any IMG tags been added within the HTML? If not, list the kinds of image and video content that would complement the article, Also, give examples of suitable royalty-free sites where to find them.";
			}
			if ( getcheckArray.addfurtheReading ) {
				autogenerateObj = autogenerateObj + "\nHas a section for further reading been included in the text? If not, list related topics that could be added.";
			}
			if ( getcheckArray.addinsertHyper ) {
				autogenerateObj = autogenerateObj + "\nHave any A tags been added within the HTML? If not, list relevant phrases within the article where hyperlinks could be added? Suggest potential domains for these hyperlinks.";
			}
			if ( getcheckArray.addkeywordBold ) {
				autogenerateObj = autogenerateObj + "\nHave any STRONG tags been added within the HTML? If not, list important phrases within the article where bold tags could be added";
			}
			if ( checkPromptOpt == "addkeywordBold" ) {
				autogenerateObj = autogenerateObj + "\nHave any STRONG tags been added within the HTML? If not, list important phrases within the article where bold tags could be added";
			}
		}


		var langVal = getInputValueByName(allinput, 'languages');
		var styleVal = getInputValueByName(allinput, 'writingStyle');
		var toneVal = getInputValueByName(allinput, 'writingTone');
		var headingTag = getInputValueByName(allinput, 'headingtag');
		var noHeading = getInputValueByName(allinput, 'num_heading');
		var avoidKeyword = getInputValueByName(allinput, 'keyword_avoid');


		autogenerateObj = autogenerateObj.replaceAll("[Language]", langVal);
	    autogenerateObj = autogenerateObj.replaceAll("[Style]", styleVal);
	    autogenerateObj = autogenerateObj.replaceAll("[Tone]", toneVal);
	    autogenerateObj = autogenerateObj.replaceAll("[Heading Tag]", headingTag);

		var ideaSelect = titleVal.replace( /['"]+/g, "" );
		ideaSelect = ideaSelect.trim();
		if ( typeof ideaSelect !== "undefined" && ideaSelect != "" ) {
			autogenerateObj = autogenerateObj.replaceAll( "[Idea]", ideaSelect );
		}
		var titleSel = getAllCheckElement.title;

		if ( typeof titleSel !== "undefined" && titleSel != "" ) {
			autogenerateObj = autogenerateObj.replaceAll( "[Title]", titleSel );
		}


		if ( avoidKeyword != "" ) {
			if ( dataStep != 11 && dataStep != 9 ) {
				avoidKeyword = avoidKeyword.split( "," );
				var keyReplace = avoidKeyword.join( '", "' );
				keyReplace = '"' + keyReplace + '"';
				autogenerateObj =
					autogenerateObj +
					" Exclude the following keywords " +
					keyReplace +
					" if they have been provided. ";

			}
		}

		if ( noHeading != "" ) {
			autogenerateObj = autogenerateObj.replaceAll(
				"[No. Headings]",
				noHeading
			);
		}

		if ( skipbtn == "skip" && dataStep == 3 ) {
			autogenerateObj = autogenerateObj.replaceAll(
				"and [Selected Keywords].",
				""
			);
		}

		if ( keyVal !== "" ) {
			var keywords = keyVal.split( "," );
			for ( var i = 0; i < keywords.length; i ++ ) {
				keywords[i] = '"' + keywords[i].trim() + '"';
			}
			var selKeyword = "following SEO keywords " + keywords.join( " and " );
			autogenerateObj = autogenerateObj.replaceAll(
				"[Selected Keywords]",
				selKeyword
			);
		} else {
			autogenerateObj = autogenerateObj.replaceAll(
				"Please include the following SEO keywords [Selected Keywords] where appropriate in the headings.",
				""
			);
			autogenerateObj = autogenerateObj.replaceAll(
				"and the [Selected Keywords]",
				""
			);
			autogenerateObj = autogenerateObj.replaceAll(
				"SEO optimise the content for the [Selected Keywords].",
				""
			);
			autogenerateObj = autogenerateObj.replaceAll(
				"and optimise for the [Selected Keywords]",
				""
			);
			autogenerateObj = autogenerateObj.replaceAll( "[Keywords Bold].", "" );
			autogenerateObj = autogenerateObj.replaceAll(
				"and the [Selected Keywords]",
				""
			);
		}

		if ( aboveBelowObj != "" ) {
			autogenerateObj = autogenerateObj.replaceAll(
				"[above/below]",
				aboveBelowObj
			);
		}

		if ( headingTag != "" ) {
			autogenerateObj = autogenerateObj.replaceAll(
				"[Heading Tag]",
				headingTag
			);
		}

		if ( headingSel != "" ) {
			autogenerateObj = (
				autogenerateObj.replaceAll( "[Heading]", headingSel )
			);
		}

		var introVal = getAllCheckElement.intro;
		if ( introVal != "" ) {
			autogenerateObj = autogenerateObj.replaceAll( "[Intro]", introVal );
		} else {
			autogenerateObj = autogenerateObj.replaceAll(
				"The following introduction should be at the top: ",
				""
			);
			autogenerateObj = autogenerateObj.replaceAll( "[Intro]", "" );
		}

		if ( tagLineVal != "" ) {
			var tagLS = "add the tagline " + tagLineVal;
			if ( aboveBelowObj != "" ) {
				tagLS += " " + aboveBelowObj;
			}
			tagLS += " the introduction in a new P tag formatted in bold";
			autogenerateObj = autogenerateObj.replaceAll( "[The Tagline]", tagLS );
		}

		autogenerateObj = autogenerateObj.replaceAll( "\\", "" );
		myObj[dataStep] = autogenerateObj;

		jQuery( "#prompt_text" ).val( autogenerateObj );
		jQuery( "#prompt_text" ).each( function () {
		} );

		return autogenerateObj;
	}

	jQuery( ".action_val_field" ).on( "input", function () {
		var currentObj = jQuery( this );
		allSiteInputs( currentObj );
	} );
	jQuery( ".action_val_field" ).on( "textarea", function () {
		var currentObj = jQuery( this );
		allSiteInputs( currentObj );
	} );
	jQuery( ".lang-additional-heading" ).on( "change", function () {
		jQuery( "#tab_input" ).trigger( "input" );
	} );
	jQuery( ".heading_key_avoid" ).on( "input", function () {
		jQuery( "#tab_input" ).trigger( "input" );
	} );

	jQuery( ".next_step_btn" ).click( function () {
		var getAllCheckElement = allCheckElements();
		var currentObj = jQuery( this );
		jQuery( "textarea#prompt_text" ).val();
		jQuery( ".action_val_field" ).val();
		var nextStep = jQuery( this ).attr( "data-nextstep" );
		var backbtn = jQuery( this ).attr( "back-btn" );
		var skipbtn = jQuery( this ).attr( "skip-btn" );
		var autogenerate = jQuery( this ).attr( "auto-generate" );
		var articleType = jQuery( this )
			.closest( ".maincontent" )
			.find( ".tab_generate_btn" )
			.attr( "data-action" );
		var generateObj = jQuery( this )
			.closest( ".maincontent" )
			.find( ".after_generate_data" );
		var checkboxCls = jQuery( this )
			.closest( ".maincontent" )
			.find( ".get_checked:checked" );
		var dataStep = jQuery( ".temp-progress-bar .active_step" ).attr( "data-step" );

		var articleVal = currentObj
			.closest( ".maincontent" )
			.find( ".action_val_field" )
			.val();
		var currentClass = jQuery(
			`#step${jQuery( ".active_step" ).attr( "data-step" )}`
		);
		if ( backbtn ) {
			jQuery( "textarea#prompt_text" ).val( myObj[nextStep] );
			dataS = jQuery( ".active_step" ).attr( "data-step" );
			if ( dataStep !== "11" ) {
				jQuery( ".prompts-sec" ).show();
			}
			rem = dataS - 1;
			var remCheck = jQuery( `[data-step='${rem}'] .rightCheck` ).replaceWith(
				"<i class='fa-solid fa-square-check fa-2xl'></i>"
			);
		} else if ( skipbtn == "skip" ) {
			allSiteInputs( currentObj );
			jQuery( ".at_temp_sec_" + nextStep ).addClass( "active-skip" );
			var closestObj = jQuery( this )
				.closest( ".maincontent" )
				.find( ".checked-element" );
			var getelemetnt = closestObj.html();
			var getAllCheckElement = allCheckElements();
			var editor_content = quill.root.innerHTML;
			var qnaStr = getAllCheckElement.qna.join( "," ).replace( /,/g, "" ).split();
			var conclusionStr = getAllCheckElement.conclusion
			                                      .join( "," )
			                                      .replace( /,/g, "" )
			                                      .split();
			var conclusionVal =
				conclusionStr.length > 0 ? conclusionStr + "<br/><br/>" : "";
			var qnaVal = qnaStr.length > 0 ? qnaStr + "<br/><br/>" : "";
			var checkedString = editor_content + conclusionVal + qnaVal;
			if ( dataStep !== "9" ) {
				autoGenerateElement( currentObj );
				//alert("skip not 9");
			}
			if ( dataStep == 9 ) {
				var delta = finalreview.clipboard.convert( checkedString );
				finalreview.setContents( delta, "silent" );

				if ( toc == "addinsertToc" ) {
					setTimeout( () => {
						generateTOC(); // Call the generateTOC function after a short delay
					}, 500 );
				} else {
				}


			} else {
				jQuery( ".active-skip" ).find( ".checked-element" ).html( getelemetnt );
			}
			jQuery( ".at_temp_sec" ).removeClass( "active-skip" );
		}
		if ( backbtn || skipbtn ) {
			jQuery( ".at_temp_sec" ).hide();
			var clsNext = ".at_temp_sec_" + nextStep;
			jQuery( ".temp-progress-bar .step" ).removeClass( "active_step" );
			jQuery( ".at_temp_sec" ).removeClass( "active_page" );
			jQuery( '.temp-progress-bar div[data-step="' + nextStep + '"]' ).addClass(
				"active_step"
			);
			jQuery( ".at_temp_sec_" + nextStep ).addClass( "active_page" );
			jQuery( clsNext ).show();
			hideShowElement();
			jQuery( "html, body" ).animate( {
				scrollTop: jQuery( ".create_template_cont_sec" ).position().top,
			} );
			return false;
		}
		if ( !backbtn ) {
			if ( generateObj.length != 0 || nextStep === "7" || nextStep === "11" ) {
				if (
					checkboxCls.length != 0 ||
					nextStep === "7" ||
					nextStep === "11"
				) {
					jQuery( ".progress-menu-bar .active_step .bullet" ).replaceWith(
						"<i class='fa-solid fa-square-check fa-2xl'></i>"
					);
					jQuery( ".at_temp_sec" ).hide();
					var clsNext = ".at_temp_sec_" + nextStep;
					jQuery( ".temp-progress-bar .step" ).removeClass( "active_step" );
					jQuery( ".at_temp_sec" ).removeClass( "active_page" );
					jQuery(
						'.temp-progress-bar div[data-step="' + nextStep + '"]'
					).addClass( "active_step" );
					jQuery( ".at_temp_sec_" + nextStep ).addClass( "active_page" );
					jQuery( clsNext ).show();
					hideShowElement();
					jQuery( "html, body" ).animate( {
						scrollTop: jQuery( ".create_template_cont_sec" ).position()
							.top,
					} );
					var currentObj = jQuery( this );
					if ( dataStep !== "9" ) {
						autoGenerateElement( currentObj );
					}

					checkedElement();

					var tab_val = currentObj
						.closest( ".maincontent" )
						.find( ".action_val_field" )
						.val();
					var dataStep = jQuery( ".temp-progress-bar .active_step" ).attr(
						"data-step"
					);
					allSiteInputs( currentObj );
					setTimeout( () => {
						var currentObj = jQuery( ".at_temp_sec_" + nextStep );
					}, 100 );
				} else {
					alert( "Please select a checkbox before continuing" );
					allSiteInputs( currentObj );
				}
			} else {
				alert( "Please select a " + articleType + " before continuing." );
				allSiteInputs( currentObj );
			}
		}

		if ( dataStep == "10" ) {
			if ( toc == "addinsertToc" ) {
				setTimeout( () => {
					generateTOC(); // Call the generateTOC function after a short delay
				}, 500 );
			} else {
			}

		}
	} );

	function decodeHtmlEntities( encodedString ) {
		const textArea = document.createElement( "textarea" );
		textArea.innerHTML = encodedString;
		return textArea.value;
	}

	function autoGenerateElement( currentObj ) {
        var getAllCheckElement = allCheckElements();
		var dataStep = jQuery( ".temp-progress-bar .active_step" ).attr( 'data-step' );
        
		var allinputs = jQuery( "#prompt_text" ).val();
        //var promptsData = ai_scribe.promptsData;
        //var instructions = promptsData.instructions_prompts;
        //allinputs = allinputs + ' ' + instructions; 
        
        
		var autogenerate = currentObj.attr( 'auto-generate' );
		var generateMore = currentObj.attr( 'generate-more' );
		var promptRegenerate = currentObj.attr( "prompt-regenerate" );
		var articleType = jQuery( '.at_temp_sec.active_page' ).find( '.tab_generate_btn' ).attr( "data-action" );
		var skipbtn = currentObj.attr( 'skip-btn' );
		var linkaction = ai_scribe.ajaxUrl;
		// Ensure nonce is available
        if (!ai_scribe.nonce) {
            if (window.aiScribeDebugMode) console.error('Security nonce is missing.');
            alert('Security issue detected. Please refresh the page.');
            return;
        }
		if ( autogenerate == 'cont_next_step' ) {
			allinputs = allSiteInputs( currentObj ); // + ' ' + instructions;
		}
		if ( skipbtn == 'skip' ) {
			articleType = jQuery( '.at_temp_sec.active-skip' ).find( '.tab_generate_btn' ).attr( "data-action" );
		}
		var lang_heading_contArr = jQuery( "form" ).serializeArray();
		var inputIdea = jQuery( '#tab_input' ).val();
		var getAllCheckElement = allCheckElements();
		var title = getAllCheckElement.title != null ? getAllCheckElement.title : '';

		var keyVal = getAllCheckElement.keyword.join( ',' );
		var tagline = getAllCheckElement.tagline.join( ',' );
		var aboveBelowObj = jQuery( ".above_below:checked" ).val();
		//alert(allinputs + ' ' + instructions);
		jQuery.ajax( {
			type: "post",
			url: linkaction,
			dataType: 'json',
			timeout: articleType === 'article' ? 180000 : 120000, // 3 minutes for articles, 2 minutes for others
			data: {
				action: 'al_scribe_suggest_content',
				security: ai_scribe.nonce,
				autogenerateValue: allinputs,
				actionInput: articleType,
				idea: inputIdea,
				title: title,
				keyword: keyVal,
				tagline: tagline,
				aboveBelow: aboveBelowObj,
				language: lang_heading_contArr[0].value,
				writingStyle: lang_heading_contArr[1].value,
				writingTone: lang_heading_contArr[2].value,
				noHeading: lang_heading_contArr[3].value,
				headingTag: lang_heading_contArr[4].value,
				keywordToAvoid: lang_heading_contArr[5].value,

			},
			beforeSend: function () {
			if (window.aiScribeDebugMode) console.log('🚀 AI Scribe Debug - AJAX request starting...');
			if (window.aiScribeDebugMode) console.log('🚀 AI Scribe Debug - URL:', linkaction);
			if (window.aiScribeDebugMode) console.log('🚀 AI Scribe Debug - Action:', 'al_scribe_suggest_content');
			if (window.aiScribeDebugMode) console.log('🚀 AI Scribe Debug - Nonce:', ai_scribe.nonce);

			// COMPREHENSIVE STEP DEBUGGING
			if (window.aiScribeDebugMode) console.group('📋 STEP-BY-STEP DIAGNOSIS');
			if (window.aiScribeDebugMode) console.log('🎯 Current Step:', dataStep);
			if (window.aiScribeDebugMode) console.log('📝 Article Type:', articleType);
			if (window.aiScribeDebugMode) console.log('🔄 Action Input (sent as):', articleType);
			if (window.aiScribeDebugMode) console.log('⚙️ Generate More:', typeof generateMore !== 'undefined' ? generateMore : 'undefined');
			if (window.aiScribeDebugMode) console.log('🔁 Prompt Regenerate:', typeof promptRegenerate !== 'undefined' ? promptRegenerate : 'undefined');
			if (window.aiScribeDebugMode) console.log('⏭️ Skip Button:', typeof skipbtn !== 'undefined' ? skipbtn : 'undefined');
			if (window.aiScribeDebugMode) console.log('📊 Data Object:', {
				step: dataStep,
				type: articleType,
				actionInputSent: articleType,
				autoGenerateValue: allinputs,
				idea: inputIdea,
				title: title
			});
			if (window.aiScribeDebugMode) console.groupEnd();

			// Reset cost calculator for new article generation (step 1 only)
			if (dataStep == 1) {
				CostCalculator.resetActualCost();
				if (window.aiScribeDebugMode) console.log('💰 Cost calculator reset for new article generation');
			}

			// Start timeout detection for articles
			if (articleType === 'article') {
				window.aiScribeStartTime = Date.now();
				if (window.aiScribeDebugMode) console.log('⏱️ TIMEOUT DETECTION: Started timer for article generation');
				if (window.aiScribeDebugMode) console.log('⏱️ TIMEOUT DETECTION: Start time:', new Date(window.aiScribeStartTime).toISOString());
				
				// Set warning at 85 seconds for articles
				window.aiScribeTimeoutWarning = setTimeout(function() {
					const elapsed = (Date.now() - window.aiScribeStartTime) / 1000;
					if (window.aiScribeDebugMode) console.warn('⚠️ TIMEOUT WARNING: Article generation approaching 85s limit');
					if (window.aiScribeDebugMode) console.warn('⚠️ TIMEOUT WARNING: Elapsed time:', elapsed + 's');
					if (window.aiScribeDebugMode) console.warn('🔄 If this continues, consider using faster models or smaller content');
				}, 85000);
			}
			
			// Log early image generation expectations
			if (articleType === 'keyword') {
				if (window.aiScribeDebugMode) console.log('🎨 EARLY IMAGE GENERATION: Keyword step detected - user has selected title, expecting parallel image generation to start');
			} else if (articleType === 'article') {
				if (window.aiScribeDebugMode) console.log('🎨 LATE IMAGE GENERATION: Article step detected - checking for image generation');
			}
			
			jQuery( '.progress-container' ).css( 'display', 'block' );
			jQuery( ".article-main" ).addClass( "article_progress" );
			progress();
			resetProgressBar();
			jQuery('button').attr('disabled', true);
			},
			success: function ( response ) {
				// COMPREHENSIVE SUCCESS HANDLER DEBUGGING
				if (window.aiScribeDebugMode) console.group('✅ SUCCESS HANDLER - COMPLETE DIAGNOSIS');
				if (window.aiScribeDebugMode) console.log('🚨 Raw response received:', response);
				if (window.aiScribeDebugMode) console.log('🚨 Response type:', typeof response);
				if (window.aiScribeDebugMode) console.log('🚨 Response keys:', response ? Object.keys(response) : 'null response');

				// Log timing information
				if (window.aiScribeStartTime) {
					const elapsed = (Date.now() - window.aiScribeStartTime) / 1000;
					if (window.aiScribeDebugMode) console.log('⏱️ TIMING: Request completed in', elapsed + 's');

					// Clear timeout warning
					if (window.aiScribeTimeoutWarning) {
						clearTimeout(window.aiScribeTimeoutWarning);
						if (window.aiScribeDebugMode) console.log('⏱️ TIMING: Timeout warning cleared');
					}
				}

				// Handle WordPress AJAX response structure
				var actualResponse = response;
				if (response.success && response.data) {
					if (window.aiScribeDebugMode) console.log('🔄 WordPress AJAX success response detected, using response.data');
					actualResponse = response.data;
				} else if (response.success === false && response.data) {
					if (window.aiScribeDebugMode) console.log('❌ WordPress AJAX error response detected');
					if (window.aiScribeDebugMode) console.log('❌ Error details:', response.data);

					// Handle detailed API error information
					if (response.data.api_response) {
						if (window.aiScribeDebugMode) console.log('🔍 API Response:', response.data.api_response);
						try {
							var apiError = JSON.parse(response.data.api_response);
							if (window.aiScribeDebugMode) console.log('🔍 Parsed API Error:', apiError);
						} catch (e) {
							if (window.aiScribeDebugMode) console.log('🔍 Raw API Error:', response.data.api_response);
						}
					}

					// Show detailed error message
					var errorMsg = response.data.msg || 'Unknown error occurred';
					alert('Content Generation Error: ' + errorMsg);
					if (window.aiScribeDebugMode) console.groupEnd();
					return false;
				}

				// Log actual response structure
				if (window.aiScribeDebugMode) console.log('📦 Actual Response Object:', actualResponse);
				if (window.aiScribeDebugMode) console.log('📦 Actual Response Keys:', actualResponse ? Object.keys(actualResponse) : 'null');
				if (window.aiScribeDebugMode) console.groupEnd();
				
				// Check for fallback content
				if (actualResponse.fallback_used) {
					if (window.aiScribeDebugMode) console.log('⚠️ AI Scribe - Fallback content was used due to API issues');
					if (window.aiScribeDebugMode) console.log('ℹ️ This means the primary AI service was unavailable, but content was still delivered');
				}

				// Output debug messages to console if present
				var debugMessages = actualResponse.debug || actualResponse.debug_messages;
				if (window.aiScribeDebugMode) console.log('🚨 AI Scribe - Debug messages found:', debugMessages);

				if (debugMessages && Array.isArray(debugMessages)) {
					if (window.aiScribeDebugMode) console.group('🔍 AI Scribe Debug Messages');
					debugMessages.forEach(function(debugMsg, index) {
						if (window.aiScribeDebugMode) console.log((index + 1) + '. ' + debugMsg);
					});
					if (window.aiScribeDebugMode) console.groupEnd();

					// Also log specific important messages
					if (window.aiScribeDebugMode) console.log('🎯 Selected Model:', debugMessages.find(msg => msg.includes('Selected Model:')) || 'Not found');
					if (window.aiScribeDebugMode) console.log('🤖 Is Anthropic Model:', debugMessages.find(msg => msg.includes('Is Anthropic Model:')) || 'Not found');
					if (window.aiScribeDebugMode) console.log('📡 API Response Code:', debugMessages.find(msg => msg.includes('Response HTTP code:')) || 'Not found');
					if (window.aiScribeDebugMode) console.log('📝 Content Length:', debugMessages.find(msg => msg.includes('Content extracted successfully')) || 'Not found');
				} else {
					if (window.aiScribeDebugMode) console.log('⚠️ AI Scribe - No debug messages in response');
				}

				// Track actual costs from API usage
				if (actualResponse.usage || actualResponse.token_usage) {
					const usage = actualResponse.usage || actualResponse.token_usage;
					const inputTokens = usage.prompt_tokens || usage.input_tokens || 0;
					const outputTokens = usage.completion_tokens || usage.output_tokens || 0;
					const model = actualResponse.model || CostCalculator.currentModel;

					if (window.aiScribeDebugMode) console.log('💰 Cost Tracking - Input tokens:', inputTokens, 'Output tokens:', outputTokens, 'Model:', model);
					CostCalculator.addActualCost(inputTokens, outputTokens, model);
				}

				if ( actualResponse.type == 'error' ) {
					// Display the error message in a popup or another UI element
					alert( actualResponse.message );
					return false;
				} else {
					//console.log(actualResponse.html);
					// Your existing success code
					if ( promptRegenerate == 'currentpage' || dataStep == 6 ) {
						var delta = alok.clipboard.convert( decodeHtmlEntities( actualResponse.html ) );
						alok.setContents( delta , 'silent' );
					} else if ( skipbtn && dataStep == 5 ) {
						var delta = alok.clipboard.convert( decodeHtmlEntities( actualResponse.html ) );
						alok.setContents( delta, 'silent' );
					} else if ( promptRegenerate == 'currentpage' || dataStep == 10 ) {
						var delta = finalreview.clipboard.convert( decodeHtmlEntities( actualResponse.html ) );
						finalreview.setContents( delta, 'silent' );
					} else if ( generateMore == 'generate_more' ) {
						jQuery( '.at_temp_sec.active_page .title_class' ).append( actualResponse.html );
					} else {
						// Enhanced response handling with better debugging
						if (window.aiScribeDebugMode) console.log('🔍 AI Scribe - Checking actualResponse.html:', actualResponse.html);
						if (window.aiScribeDebugMode) console.log('🔍 AI Scribe - actualResponse.html type:', typeof actualResponse.html);
						if (window.aiScribeDebugMode) console.log('🔍 AI Scribe - actualResponse.html length:', actualResponse.html ? actualResponse.html.length : 'null/undefined');

						// Check if actualResponse.html exists and has content
						if (actualResponse.html && actualResponse.html !== 'undefined' && actualResponse.html.trim() !== '') {
							if (window.aiScribeDebugMode) console.log('✅ AI Scribe - Valid content found, updating UI');
							jQuery( '.at_temp_sec.active_page .title_class' ).html( decodeHtmlEntities( actualResponse.html ) );
						} else {
							if (window.aiScribeDebugMode) console.log('❌ AI Scribe - No valid content found');
							if (window.aiScribeDebugMode) console.log('🔍 AI Scribe - Full actualResponse object:', actualResponse);
							
							// Show user-friendly error message
							var errorMsg = "⚠️ No content generated. ";
							if (actualResponse.debug && Array.isArray(actualResponse.debug)) {
								var apiError = actualResponse.debug.find(msg => msg.includes('Engine API Error:'));
								if (apiError) {
									errorMsg += "API Error: " + apiError.split('Engine API Error: ')[1];
								} else {
									errorMsg += "Please check the browser console for debug information.";
								}
							} else {
								errorMsg += "Please try again or contact support.";
							}
							
							jQuery( '.at_temp_sec.active_page .title_class' ).html('<div style="color: #d63638; padding: 15px; border: 1px solid #d63638; border-radius: 4px; background: #fef7f7;">' + errorMsg + '</div>');
							
							// Also show popup for immediate attention
							alert("Content Generation Failed\n\n" + errorMsg.replace(/⚠️|<[^>]*>/g, ''));
						}
					}

					// ⚡ EXECUTE PARALLEL IMAGE GENERATION JAVASCRIPT
					if (window.aiScribeDebugMode) console.group('🎨 IMAGE GENERATION DIAGNOSIS');
					if (window.aiScribeDebugMode) console.log('🔍 Checking for parallel_image_js in response...');
					if (window.aiScribeDebugMode) console.log('🔍 parallel_image_js exists:', !!response.parallel_image_js);
					if (window.aiScribeDebugMode) console.log('🔍 image_insert_js exists:', !!response.image_insert_js);
					if (window.aiScribeDebugMode) console.log('🔍 no_title_js exists:', !!response.no_title_js);
					if (window.aiScribeDebugMode) console.log('🔍 final_status_js exists:', !!response.final_status_js);

					// Handle parallel image generation JavaScript from response
					if (response.parallel_image_js) {
						if (window.aiScribeDebugMode) console.log('⚡ PARALLEL IMAGE: Found image generation JavaScript in response');
						if (window.aiScribeDebugMode) console.log('⚡ PARALLEL IMAGE: JavaScript length:', response.parallel_image_js.length);
						if (window.aiScribeDebugMode) console.log('⚡ PARALLEL IMAGE: JavaScript preview:', response.parallel_image_js.substring(0, 200) + '...');
						try {
							if (window.aiScribeDebugMode) console.log('⚡ PARALLEL IMAGE: Executing image generation JavaScript...');
							// Execute the JavaScript for parallel image generation
							eval(response.parallel_image_js);
							if (window.aiScribeDebugMode) console.log('✅ PARALLEL IMAGE: JavaScript executed successfully');
						} catch (error) {
							if (window.aiScribeDebugMode) console.error('❌ PARALLEL IMAGE: Error executing image generation JavaScript:', error);
							if (window.aiScribeDebugMode) console.error('❌ PARALLEL IMAGE: Error stack:', error.stack);
						}
					} else {
						if (window.aiScribeDebugMode) console.log('ℹ️ PARALLEL IMAGE: No parallel_image_js found in response');
						if (window.aiScribeDebugMode) console.log('ℹ️ PARALLEL IMAGE: This may be normal for non-title/non-article steps');
					}

					// Handle image insertion JavaScript from response (for article step)
					if (response.image_insert_js) {
						if (window.aiScribeDebugMode) console.log('🖼️ IMAGE INSERTION: Found image insertion JavaScript in response');
						if (window.aiScribeDebugMode) console.log('🖼️ IMAGE INSERTION: JavaScript length:', response.image_insert_js.length);
						try {
							if (window.aiScribeDebugMode) console.log('🖼️ IMAGE INSERTION: Executing image insertion JavaScript...');
							// Execute the JavaScript for image insertion
							eval(response.image_insert_js);
							if (window.aiScribeDebugMode) console.log('✅ IMAGE INSERTION: JavaScript executed successfully');
						} catch (error) {
							if (window.aiScribeDebugMode) console.error('❌ IMAGE INSERTION: Error executing image insertion JavaScript:', error);
							if (window.aiScribeDebugMode) console.error('❌ IMAGE INSERTION: Error stack:', error.stack);
						}
					} else {
						if (window.aiScribeDebugMode) console.log('ℹ️ IMAGE INSERTION: No image_insert_js found in response');
						if (window.aiScribeDebugMode) console.log('ℹ️ IMAGE INSERTION: This is normal for non-article steps');
					}

					// Handle no title JavaScript from response
					if (response.no_title_js) {
						if (window.aiScribeDebugMode) console.log('ℹ️ NO TITLE: Found no title JavaScript in response');
						try {
							eval(response.no_title_js);
							if (window.aiScribeDebugMode) console.log('✅ NO TITLE: JavaScript executed successfully');
						} catch (error) {
							if (window.aiScribeDebugMode) console.error('❌ NO TITLE: Error executing no title JavaScript:', error);
						}
					}

					// Handle final status JavaScript from response
					if (response.final_status_js) {
						if (window.aiScribeDebugMode) console.log('✅ FINAL STATUS: Found final status JavaScript in response');
						try {
							eval(response.final_status_js);
							if (window.aiScribeDebugMode) console.log('✅ FINAL STATUS: JavaScript executed successfully');
						} catch (error) {
							if (window.aiScribeDebugMode) console.error('❌ FINAL STATUS: Error executing final status JavaScript:', error);
						}
					}

					if (window.aiScribeDebugMode) console.groupEnd();

					jQuery( ".prompts-options" ).show();
					// **Capture and store the attachment_id**
			        /*if (response.attachment_id) {
			            jQuery('#attachment_id').val(response.attachment_id);
			        }*/
				}
			},
			// Enhanced error handling with meaningful messages
			error: function (xhr, textStatus, errorThrown) {
			    // COMPREHENSIVE ERROR HANDLER DEBUGGING
			    if (window.aiScribeDebugMode) console.group('❌ ERROR HANDLER - COMPLETE DIAGNOSIS');
			    if (window.aiScribeDebugMode) console.error('❌ AI Scribe AJAX Error Details:', {
			        textStatus: textStatus,
			        httpStatus: xhr.status,
			        errorThrown: errorThrown,
			        responseText: xhr.responseText,
			        readyState: xhr.readyState,
			        responseHeaders: xhr.getAllResponseHeaders()
			    });

			    // Log timing information
			    if (window.aiScribeStartTime) {
			        const elapsed = (Date.now() - window.aiScribeStartTime) / 1000;
			        if (window.aiScribeDebugMode) console.error('⏱️ ERROR TIMING: Request failed after', elapsed + 's');
			        if (window.aiScribeDebugMode) console.error('⏱️ ERROR TIMING: Start time:', new Date(window.aiScribeStartTime).toISOString());
			        if (window.aiScribeDebugMode) console.error('⏱️ ERROR TIMING: End time:', new Date().toISOString());
			    }

			    // Log current step context
			    if (window.aiScribeDebugMode) console.error('📋 ERROR CONTEXT:', {
			        step: dataStep,
			        articleType: articleType,
			        actionInputSent: articleType,
			        generateMore: typeof generateMore !== 'undefined' ? generateMore : 'undefined',
			        promptRegenerate: typeof promptRegenerate !== 'undefined' ? promptRegenerate : 'undefined',
			        skipbtn: typeof skipbtn !== 'undefined' ? skipbtn : 'undefined'
			    });
			    
			    var errorMsg = "Content Generation Failed: ";
			    var isArticleGeneration = articleType === 'article';
			    
			    if (textStatus === "timeout") {
			        if (isArticleGeneration) {
			            errorMsg += "The article generation process timed out, likely due to image generation taking too long. ";
			            errorMsg += "This is usually caused by Cloudflare's 100-second timeout limit. ";
			            errorMsg += "The content was generated but the response couldn't be delivered. ";
			            errorMsg += "Try again - the system will attempt to generate content without images if this continues.";
			        } else {
			            errorMsg += "The request timed out. This may be due to high server load or network issues. Please try again.";
			        }
			    } else if (xhr.status === 524) {
			        errorMsg += "Cloudflare timeout occurred (524 error). ";
			        if (isArticleGeneration) {
			            errorMsg += "This is likely due to image generation taking too long. ";
			            errorMsg += "The article content was probably generated successfully but couldn't be delivered due to the timeout. ";
			            errorMsg += "Try refreshing and generating again - the system will attempt faster processing.";
			        } else {
			            errorMsg += "The server took too long to respond. Please try again.";
			        }
			    } else if (xhr.status === 500) {
			        errorMsg += "There was a server error. Please try again later.";
			    } else if (xhr.status === 404) {
			        errorMsg += "The requested resource could not be found. Please contact support.";
			    } else if (textStatus === "parsererror") {
			        errorMsg += "Server response format error. Please check the console for details and contact support if the issue persists.";
			    } else {
			        errorMsg += "Please check your internet connection and try again.";
			    }
			    
			    // Show detailed error in console for debugging
			    if (window.aiScribeDebugMode) console.error('🚨 AI Scribe - Detailed Error Info:', {
			        textStatus: textStatus,
			        httpStatus: xhr.status,
			        errorThrown: errorThrown,
			        responseText: xhr.responseText,
			        isArticleGeneration: isArticleGeneration
			    });

			    if (window.aiScribeDebugMode) console.error('📢 ERROR MESSAGE TO USER:', errorMsg);
			    if (window.aiScribeDebugMode) console.groupEnd();
			    
			    alert(errorMsg);
			},
			complete: function (data) {
			    // Clear timeout detection
			    if (window.aiScribeTimeoutWarning) {
			        clearTimeout(window.aiScribeTimeoutWarning);
			        window.aiScribeTimeoutWarning = null;
			    }
			    
			    // Log completion time for articles
			    if (articleType === 'article' && window.aiScribeStartTime) {
			        const totalTime = (Date.now() - window.aiScribeStartTime) / 1000;
			        if (window.aiScribeDebugMode) console.log('⏱️ TIMEOUT DETECTION: Article completed in ' + totalTime.toFixed(1) + 's');
			        window.aiScribeStartTime = null;
			    }

			    jQuery('.progress-container').css('display', 'none');
			    jQuery(".article-main").removeClass("article_progress");

			    setTimeout(() => {
			        jQuery("button").removeAttr("disabled");
				resetProgressBar();
			    }, 2000);
			}
		} );
	}

	jQuery( '.tab_generate_btn' ).click( function () {
		var articleVal = jQuery( this ).closest( '.title_div' ).find( '.action_val_field' ).val();
		if ( articleVal == '' ) {
			alert( 'Please enter a value before clicking the continue button' );
			return;
		}
		var currentObj = jQuery( this );
		autoGenerateElement( currentObj );
		jQuery( '.generate_more_btn' ).removeAttr( 'disabled' );
		jQuery( '.tab_regenerate_btn' ).removeAttr( 'disabled' );

	} );
	jQuery( '.tab_regenerate_btn' ).click( function () {
		var currentObj = jQuery( `#step${jQuery( '.active_step' ).attr( "data-step" )}` );
		var dataStep = jQuery( ".temp-progress-bar .active_step" ).attr( 'data-step' );
		var articleVal = currentObj.find( '.action_val_field' ).val();
		if ( articleVal == '' && dataStep == 1 ) {
			alert( 'Please Enter Input' );
			return;
		}
		autoGenerateElement( currentObj );
		return false;
	} );
	jQuery( '.generate_more_btn' ).click( function () {
		var currentObj = jQuery( this );
		var dataStep = jQuery( ".temp-progress-bar .active_step" ).attr( 'data-step' );
		var articleVal = currentObj.closest( '.maincontent' ).find( '.action_val_field' ).val();
		if ( articleVal == '' && dataStep == 1 ) {
			alert( 'Please Enter Input' );
			return;
		}
		autoGenerateElement( currentObj );
		return false;
	} );

	function allCheckElements() {
		var titleCheckObj = jQuery( ".generate_title :checked" ).val();
		var titleCheckObj = titleCheckObj;
		var headingCheckObj = [];
		jQuery( '.generate_heading .get_checked:checked' ).each( function ( i ) {
			headingCheckObj[i] = jQuery( this ).val();
			headingCheckObj[i] = headingCheckObj[i]?.replace( headingCheckObj[i]?.match( /(\d+)./g ), '' ).trim();
			headingCheckObj[i] = headingCheckObj[i]?.split( /<\/?br\s*\/?>/ ).filter( Boolean ).map( substring => `"${substring.trim()}"` ).join( ', ' );
		} );
		var keywordCheckObj = [];
		jQuery( '.generate_keyword .get_checked:checked' ).each( function ( i ) {
			keywordCheckObj[i] = jQuery( this ).val();
			keywordCheckObj[i] = keywordCheckObj[i]?.replace( keywordCheckObj[i]?.match( /(\d+)./g ), '' ).trim();
		} );
		var introCheckObj = [];
		jQuery( '.generate_intro .get_checked:checked' ).each( function ( i ) {
			introCheckObj[i] = jQuery( this ).val();
			introCheckObj[i] = introCheckObj[i]?.replace( introCheckObj[i]?.match( /(\d+)./g ), '' ).trim();
		} );
		var taglineCheckObj = [];
		jQuery( '.generate_tagline .get_checked:checked' ).each( function ( i ) {
			taglineCheckObj[i] = jQuery( this ).val();
			taglineCheckObj[i] = taglineCheckObj[i]?.replace( taglineCheckObj[i]?.match( /(\d+)./g ), '' ).trim();
		} );
		var conclusionCheckObj = [];
		jQuery( '.generate_conclusion .get_checked:checked' ).each( function ( i ) {
			conclusionCheckObj[i] = jQuery( this ).val();
			conclusionCheckObj[i] = conclusionCheckObj[i]?.replace( conclusionCheckObj[i]?.match( /(\d+)./g ), '' ).trim();
		} );
		var qnaCheckObj = [];
		jQuery( '.generate_qna .get_checked:checked' ).each( function ( i ) {
			qnaCheckObj[i] = jQuery( this ).val();

			qnaCheckObj[i] = qnaCheckObj[i]?.replace( qnaCheckObj[i]?.match( /(\d+)./g ), '' ).trim();
		} );
		var metadataCheckObj = [];
		jQuery( '.generate_seo-meta-data .get_checked:checked' ).each( function ( i ) {
			metadataCheckObj[i] = jQuery( this ).val();
			metadataCheckObj[i] = metadataCheckObj[i]?.replace( metadataCheckObj[i]?.match( /(\d+)./g ), '' ).trim();
		} );

		var allcheckArray = {
			title: titleCheckObj,
			heading: headingCheckObj,
			keyword: keywordCheckObj,
			intro: introCheckObj,
			tagline: taglineCheckObj,
			conclusion: conclusionCheckObj,
			qna: qnaCheckObj,
			metadata: metadataCheckObj,
		};
		return allcheckArray;
	}

	jQuery( '.save_post_tab' ).click( function () {
		var getAllCheckElement = allCheckElements();
		var linkaction = ai_scribe.ajaxUrl;
		// Ensure nonce is available
        if (!ai_scribe.nonce) {
            if (window.aiScribeDebugMode) console.error('Security nonce is missing.');
            alert('Security issue detected. Please refresh the page.');
            return;
        }

		var checkObj = jQuery( ".checked_value" ).val();
		var editor_content = quillReview.root.innerHTML;
		// Call the progress() function to show the progress bar
    		progress();
		jQuery.ajax( {
			type: "post",
			url: linkaction,
			dataType: 'json',
			data: {
				action: 'al_scribe_send_post_page',
				security: ai_scribe.nonce, 
				titleData: getAllCheckElement.title,
				headingData: getAllCheckElement.heading,
				keywordData: getAllCheckElement.keyword,
				introData: getAllCheckElement.intro,
				taglineData: getAllCheckElement.tagline,
				articleVal: editor_content,
				conclusionData: getAllCheckElement.conclusion,
				qnaData: getAllCheckElement.qna,
				metaData: getAllCheckElement.metadata,
				//attachment_id: attachmentId,
				contentData: checkObj,
			},
			beforeSend: function () {
				jQuery( ".article-main" ).addClass( "overlay" );
			},
			success: function ( response ) {
				if (window.aiScribeDebugMode) console.log( response );
				// Display an alert popup indicating successful saving
            			alert("Post saved successfully!");
			},
			complete: function ( data ) {
				jQuery( ".article-main" ).removeClass( "overlay" );
			}

		} );

	} );

	jQuery( '.save_as_shortcode' ).click( function () {
		var linkaction = ai_scribe.ajaxUrl;
		// Ensure nonce is available
        if (!ai_scribe.nonce) {
            if (window.aiScribeDebugMode) console.error('Security nonce is missing.');
            alert('Security issue detected. Please refresh the page.');
            return;
        }

		var getAllCheckElement = allCheckElements();
		var editor_content = quillReview.root.innerHTML;
		// Call the progress() function to show the progress bar
    		progress();
		jQuery.ajax( {
			type: "post",
			dataType: 'json',
			url: linkaction,
			data: {
				action: 'al_scribe_send_shortcode_page',
				security: ai_scribe.nonce, 
				titleData: getAllCheckElement.title,
				headingData: getAllCheckElement.heading,
				keywordData: getAllCheckElement.keyword,
				introData: getAllCheckElement.intro,
				taglineData: getAllCheckElement.tagline,
				articleVal: editor_content,
				conclusionData: getAllCheckElement.conclusion,
				qnaData: getAllCheckElement.qna,
				metaData: getAllCheckElement.metadata,

			},
			beforeSend: function () {
				jQuery( ".article-main" ).addClass( "overlay" );
			},
			success: function ( response ) {
				if (window.aiScribeDebugMode) console.log( response );
				// Display an alert popup indicating successful saving
            			alert("Post saved successfully!");
			},
			complete: function ( data ) {
				jQuery( ".article-main" ).removeClass( "overlay" );
			}
		} );
	} );
	jQuery( ".languages_style_tab" ).click( function () {
		var currentObj = jQuery( this );
		jQuery( ".languages_style" ).toggle();
		currentObj.toggleClass( "expanded" );
		if ( currentObj.hasClass( "expanded" ) ) {
			currentObj.html( "-" );
		} else {
			currentObj.html( "+" );
		}
	} );

	jQuery( ".heading_tab" ).click( function () {
		var currentObj = jQuery( this );
		jQuery( ".hide_headings_tab" ).toggle();
		currentObj.toggleClass( "expanded" );
		if ( currentObj.hasClass( "expanded" ) ) {
			currentObj.html( "-" );
		} else {
			currentObj.html( "+" );
		}
	} );

	jQuery( ".additional_content_tab" ).click( function () {
		var currentObj = jQuery( this );
		jQuery( ".hide_addition_content" ).toggle();
		currentObj.toggleClass( "expanded" );
		if ( currentObj.hasClass( "expanded" ) ) {
			currentObj.html( "-" );
		} else {
			currentObj.html( "+" );
		}
	} );

	jQuery( ".show_prompt" ).click( function () {
		jQuery( ".prompts-options" ).toggle();
		jQuery( '.regen' ).toggle();
		jQuery( this ).val( jQuery( this ).val() == 'Show' ? 'Hide' : 'Show' );
	} );

	function checkedElement() {
		var getAllCheckElement = allCheckElements();
		var editor_content = quill.root.innerHTML;
		var content = jQuery( '.editorjs' ).innerHTML;

		var qnaStr = getAllCheckElement.qna.join( ',' ).replace( /,/g, '' ).split();
		var conclusionStr = getAllCheckElement.conclusion.join( ',' ).replace( /,/g, '' ).split();
		var dataStep = jQuery( `#step${jQuery( '.active_step' ).attr( "data-step" )}` );
		var dataStepBar = jQuery( ".temp-progress-bar .active_step" ).attr( 'data-step' );
		var keyVal = getAllCheckElement.keyword.join( '<br/>' );
		var titleVal = getAllCheckElement.title.length > 0 ? (
			"<b> Title </b>  :- " + getAllCheckElement.title + "<br/><br/>"
		) : '';
		var keywordVal = getAllCheckElement.keyword.length > 0 ? (
			"<b> Keyword </b> :- " + keyVal + "<br/><br/>"
		) : '';
		var headingVal = getAllCheckElement.heading.length > 0 ? (
			"<b> Heading </b> :- " + getAllCheckElement.heading + "<br/><br/>"
		) : '';
		var introVal = getAllCheckElement.intro.length > 0 ? (
			"<b> Intro </b> :- " + getAllCheckElement.intro + "<br/><br/>"
		) : '';
		var taglineVal = getAllCheckElement.tagline.length > 0 ? (
			"<b> Tagline </b> :- " + getAllCheckElement.tagline + "<br/><br/>"
		) : '';
		var conclusionVal = conclusionStr.length > 0 ? (
			conclusionStr + "<br/><br/>"
		) : '';
		var qnaVal = qnaStr.length > 0 ? (
			qnaStr + "<br/><br/>"
		) : '';
		var metadataVal = getAllCheckElement.metadata.length > 0 ? (
			"<b> Meta Data </b>:- " + getAllCheckElement.metadata
		) : '';
		var aboveBelowObj = jQuery( ".above_below:checked" ).val();
		var aboveBelowReviewObj = jQuery( ".above_below_conclusion:checked" ).val();
		var checkedString = titleVal + keywordVal + headingVal + introVal + taglineVal + editor_content + conclusionVal + qnaVal + metadataVal;
		if ( dataStepBar == 10 ) {

			checkedString = editor_content + conclusionVal + qnaVal;

			if ( aboveBelowReviewObj == 'above' || aboveBelowObj == 'above' ) {
				// Combine All Output on Final Article Screen
				checkedString = editor_content + qnaVal + conclusionVal;
			} else {
				// Combine All Output on Final Article Screen
				checkedString = editor_content + conclusionVal + qnaVal;
			}
		}
		var closestObj = dataStep.find( '.checked-element' );
		if ( dataStepBar == 10 ) {
			var delta = finalreview.clipboard.convert( checkedString );
			finalreview.setContents( delta, 'silent' );
		}

		// Create a new unordered list element
		var ulElement = document.createElement( 'ul' );

		// Append the list item to the unordered list element
		ulElement.innerHTML = '<li style="margin-bottom: 6px; margin-top: 6px; margin-left: 3px;">' + checkedString + '</li>';

		// Append the unordered list element to the closestObj
		var closestObj = dataStep.find( '.checked-element' );
		closestObj.html( ulElement );

		var allcheckelement = closestObj.html( '<ul><li style=" margin-bottom: 6px;  margin-top: 6px; margin-left: 3px;">' + checkedString + '</li></ul>' );
		return allcheckelement;
	}

	function hideShowElement() {
		var dataStep = jQuery( '.active_step' ).attr( 'data-step' );
		var clsNext = '.at_temp_sec_' + dataStep;
		var lang_heading_contArr = jQuery( '.lang-additional-heading' );
		if ( dataStep == 10 ) {
			jQuery( "input[name='checkArr[]']" ).removeClass( 'inactive_field' );
		} else {
			jQuery( "input[name='checkArr[]']" ).addClass( 'inactive_field' );
		}
		if ( dataStep == 1 || dataStep == 2 || dataStep == 5 || dataStep == 6 || dataStep == 9 ) {
			jQuery( "input[name='num_heading']" ).addClass( 'inactive_field' );
			jQuery( "#heading-tag" ).addClass( 'inactive_field' );
		} else {
			jQuery( "input[name='num_heading']" ).removeClass( 'inactive_field' );
			jQuery( "#heading-tag" ).removeClass( 'inactive_field' );
		}

		if ( dataStep == 1 || dataStep == 10 ) {
			jQuery( ".languages" ).removeClass( 'inactive_field' );
		} else {
			jQuery( ".languages" ).addClass( 'inactive_field' );
		}

		if ( dataStep == 4 || dataStep == 7 ) {
			jQuery( ".no_heading" ).addClass( 'inactive_field' );
		} else {
			jQuery( ".no_heading" ).removeClass( 'inactive_field' );
		}

		if ( dataStep == 6 || dataStep == 11 ) {
			jQuery( ".form1" ).addClass( 'inactive_field' );

		} else {
			jQuery( ".form1" ).removeClass( 'inactive_field' );
		}
	}

	var i = 0;

	function progress() {
	    if (i == 0) {
	        i = 1;
	        var elem = document.getElementById("progressBar");
	        var width = 10;
	        var id = setInterval(frame, 200);

	        function frame() {
	            if (width >= 100) {
	                clearInterval(id);
	                i = 0;
	                elem.innerHTML = "WAIT (STILL LOADING)...";
					elem.style.animation = "buzzing 0.2s linear infinite";
	                //elem.style.animation = "none";
	            } else {
	                width++;
	                elem.style.width = width + "%";
	                elem.innerHTML = width + "%";
	            }
	        }
	    }
	}


	function resetProgressBar() {
		var elem = document.getElementById( "progressBar" );
		elem.style.width = "0%";
		elem.innerHTML = "0%";
		elem.style.animation = "none";
	}
} );