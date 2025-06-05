
const myObj = {};
jQuery( document ).ready( function () {
	console.log('Localized ai_scribe object:', ai_scribe);
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
		    console.error('ai_scribe is not defined or missing properties. Please check wp_localize_script in PHP.');
		    alert('Unable to process the request. Please refresh the page and try again.');
		    return;
		}

		var linkaction = ai_scribe.ajaxUrl;

		// Ensure nonce is available
        if (!ai_scribe.nonce) {
            console.error('Security nonce is missing.');
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


	var apikey = ai_scribe.apiKey;

	if ( apikey.length == 0 ) {
		alert( "Please add your API key within the settings page. If you don't have an OpenAI account yet, you can sign up for one here: https://beta.openai.com/signup" );
		window.location = ai_scribe.settingsUrl;
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
	        console.warn("TOC already created. Skipping generation.");
	        return;
	    }
	    tocCreated = true;
	    
	    const article = document.querySelector('.editorjs2 .ql-editor');
	    if (!article || !(article instanceof HTMLElement)) {
	        console.error("Article element not found. Please check the selector.");
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
	        console.error("First paragraph not found. Inserting TOC at the beginning.");
	        // As a fallback, insert the TOC at the beginning if no paragraph is found
	        finalreview.clipboard.dangerouslyPasteHTML(0, wrappedTOC);
	    }

	    console.log("TOC generated and inserted after the first paragraph.");
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
				aiengine.model.includes("gpt-4")
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
            console.error('Security nonce is missing.');
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
			jQuery( '.progress-container' ).css( 'display', 'block' );
			jQuery( ".article-main" ).addClass( "article_progress" );
			progress();
			resetProgressBar();
			jQuery('button').attr('disabled', true);
			},
			success: function ( response ) {
				if ( response.type == 'error' ) {
					// Display the error message in a popup or another UI element
					alert( response.message );
					return false;
				} else {
					//console.log(response.html);
					// Your existing success code
					if ( promptRegenerate == 'currentpage' || dataStep == 6 ) {
						var delta = alok.clipboard.convert( decodeHtmlEntities( response.html ) );
						alok.setContents( delta , 'silent' );
					} else if ( skipbtn && dataStep == 5 ) {
						var delta = alok.clipboard.convert( decodeHtmlEntities( response.html ) );
						alok.setContents( delta, 'silent' );
					} else if ( promptRegenerate == 'currentpage' || dataStep == 10 ) {
						var delta = finalreview.clipboard.convert( decodeHtmlEntities( response.html ) );
						finalreview.setContents( delta, 'silent' );
					} else if ( generateMore == 'generate_more' ) {
						jQuery( '.at_temp_sec.active_page .title_class' ).append( response.html );
					} else {
						jQuery( '.at_temp_sec.active_page .title_class' ).html( decodeHtmlEntities( response.html ) );
					}
					jQuery( ".prompts-options" ).show();
					// **Capture and store the attachment_id**
			        /*if (response.attachment_id) {
			            jQuery('#attachment_id').val(response.attachment_id);
			        }*/
				}
			},
			// New v1.2.4 error handling when issues such as timeout
			error: function (xhr, textStatus, errorThrown) {
			    console.log('AJAX Error:', textStatus, xhr.status, errorThrown);
			    var errorMsg = "An error occurred: ";
			    if (textStatus === "timeout") {
			        errorMsg += "The request timed out. Please check your internet connection and try again.";
			    } else if (xhr.status === 500) {
			        errorMsg += "There was a server error. Please try again later.";
			    } else if (xhr.status === 404) {
			        errorMsg += "The requested resource could not be found. Please contact support.";
			    } else {
			        errorMsg += "Please check your internet connection and try again.";
			    }
			    alert(errorMsg);
			},
			complete: function (data) {

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
            console.error('Security nonce is missing.');
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
				console.log( response );
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
            console.error('Security nonce is missing.');
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
				console.log( response );
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