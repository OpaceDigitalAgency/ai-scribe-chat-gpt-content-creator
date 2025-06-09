<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>AI-Scribe Settings: ChatGPT SEO Content Creator</title>
	<link rel="stylesheet" href="<?php echo esc_url( AI_SCRIBE_URL . 'assets/css/article_builder.css' ); ?>">
	<style>
		body { margin: 0; padding: 0; background: #f1f1f1; }
		.settings-wrapper { background: white; min-height: 100vh; }

		/* Settings Section Styling */
		.settings-section {
			border: 2px solid #0073aa;
			padding: 20px;
			margin: 20px 0;
			background-color: #f0f8ff;
			border-radius: 8px;
		}

		.section-title {
			color: #0073aa;
			margin-top: 0;
			margin-bottom: 15px;
			font-size: 18px;
		}

		.section-content {
			background: white;
			padding: 15px;
			border-radius: 5px;
			border: 1px solid #ddd;
		}

		.smart-interface-notice {
			background-color: #fff3cd;
			padding: 10px 15px;
			border-left: 4px solid #ffc107;
			margin-bottom: 20px;
			border-radius: 4px;
		}

		/* Form Row Styling */
		.form-row {
			display: flex;
			align-items: center;
			margin-bottom: 15px;
			gap: 15px;
		}

		.form-label {
			min-width: 150px;
			font-weight: bold;
			margin: 0;
		}

		.form-select, .form-input {
			flex: 1;
			padding: 8px 12px;
			border: 1px solid #ddd;
			border-radius: 4px;
			font-size: 14px;
		}

		.form-select:focus, .form-input:focus {
			outline: none;
			border-color: #0073aa;
			box-shadow: 0 0 0 1px #0073aa;
		}

		/* API Keys Section */
		.api-keys-section {
			border: 2px solid #0073aa;
			padding: 20px;
			margin: 20px 0;
			background-color: #f0f8ff;
			border-radius: 8px;
		}

		.api-keys-section .gform {
			margin-bottom: 20px;
		}

		.api-keys-section .gform label {
			display: block;
			margin-bottom: 8px;
			font-weight: bold;
		}

		.api-keys-section .gform input[type="text"] {
			width: 100%;
			padding: 10px;
			border: 1px solid #ddd;
			border-radius: 4px;
			font-size: 14px;
		}

		.api-keys-section .gform small {
			display: block;
			margin-top: 5px;
			color: #666;
		}

		/* Image Generation Section */
		.image-generation-section {
			border: 2px solid #28a745;
			padding: 20px;
			margin: 20px 0;
			background-color: #f8fff8;
			border-radius: 8px;
		}

		/* Hide/show based on model selection */
		.model-dependent {
			transition: opacity 0.3s ease;
		}

		.model-dependent.hidden {
			opacity: 0.3;
			pointer-events: none;
		}
	</style>
</head>
<body id="body">
<div class="settings-wrapper">
<div class="form-step1">
	<!-- Consistent header across all pages -->
	<div class="ai-scribe-header">
		<div class="logo-container">
			<img class="opace-logo-compact"
			     src="<?php echo esc_url( AI_SCRIBE_URL . 'assets/2023/03/AI-Scribe-Logo-simplified-80x80.png' ) ?>"
			     alt="AI-Scribe Logo">
			<div class="brand-info">
				<h1 class="brand-name">AI-Scribe</h1>
				<span class="version-badge">v<?php echo AI_SCRIBE_VER; ?></span>
			</div>
		</div>
	</div>
	
	<!-- Navigation menu -->
	<div class="header-main">
		<div class="temp-progress-bar">
			<div class="step" onclick="document.location.href='./admin.php?page=ai_scribe_generate_article'">
				<p>Generate Article</p>
			</div>
			<div class="step active_step">
				<p>Settings</p>
			</div>
			<div class="step" onclick="document.location.href='./admin.php?page=ai_scribe_help'">
				<p>Help</p>
			</div>
			<div class="step" onclick="document.location.href='./admin.php?page=ai_scribe_saved_shortcodes'">
				<p>Saved Shortcodes</p>
			</div>
		</div>
	</div>

	<div class="form-text">
		<div class="button-div modern-tabs">
			<button class="al-engine tab-button" id="second">Content</button>
			<button class="al-engine tab-button" id="first">AI Engine</button>
		</div>
		<div class="tab-content">
		<form class="first_form" id="frmFirst" method="post">
			<?php 


			$getarr      = get_option( 'ab_gpt_content_settings' );
			$promptsContent    = get_option( 'ab_prompts_content' );

			// Get mode - 12.10.24
			$selected_mode 	   = isset( $getarr['mode'] ) ? $getarr['mode'] : 'standard';

			$instructionsPrompts      = isset( $promptsContent['instructions_prompts'] ) ? $promptsContent['instructions_prompts'] : '';
			$titlePrompts      = isset( $promptsContent['title_prompts'] ) ? $promptsContent['title_prompts'] : '';
			$KeywordsPrompts   = isset( $promptsContent['Keywords_prompts'] ) ? $promptsContent['Keywords_prompts'] : '';
			$outlinePrompts    = isset( $promptsContent['outline_prompts'] ) ? $promptsContent['outline_prompts'] : '';
			$introPrompts      = isset( $promptsContent['intro_prompts'] ) ? $promptsContent['intro_prompts'] : '';
			$taglinePrompts    = isset( $promptsContent['tagline_prompts'] ) ? $promptsContent['tagline_prompts'] : '';
			$articlePrompts    = isset( $promptsContent['article_prompts'] ) ? $promptsContent['article_prompts'] : '';
			$conclusionPrompts = isset( $promptsContent['conclusion_prompts'] ) ? $promptsContent['conclusion_prompts'] : '';
			$qaPrompts         = isset( $promptsContent['qa_prompts'] ) ? $promptsContent['qa_prompts'] : '';
			$metaPrompts       = isset( $promptsContent['meta_prompts'] ) ? $promptsContent['meta_prompts'] : '';
			$evaluatePrompts   = isset( $promptsContent['evaluate_prompts'] ) ? $promptsContent['evaluate_prompts'] : '';
			$reviewPrompts     = isset( $promptsContent['review_prompts'] ) ? $promptsContent['review_prompts'] : '';



			$lang              = isset( $getarr['language'] ) ? $getarr['language'] : '';
			$writing_style     = isset( $getarr['writing_style'] ) ? $getarr['writing_style'] : '';
			$writing_tone      = isset( $getarr['writing_tone'] ) ? $getarr['writing_tone'] : '';
			$number_of_heading = isset( $getarr['number_of_heading'] ) ? $getarr['number_of_heading'] : '';
			$Heading_tag       = isset( $getarr['Heading_tag'] ) ? $getarr['Heading_tag'] : '';
			$modify_heading    = isset( $getarr['modify_heading'] ) ? $getarr['modify_heading'] : '';
			$getcheckArray     = isset( $getarr['check_Arr'] ) ? $getarr['check_Arr'] : '';
			$cslist            = isset( $getarr['cs_list'] ) ? $getarr['cs_list'] : '';
			
			

			$langArr;

			if (!get_option('ai_scribe_languages')) {
            	$langArr = array(
					'English',
					'Bulgarian',
					'Czech',
					'Danish',
					'German',
					'Greek',
					'British',
					'Spanish',
					'Estonian',
					'Finnish',
					'French',
					'Hungarian',
					'Indonesian',
					'Italian',
					'Japanese',
					'Korean',
					'Lithuanian',
					'Latvian',
					'Norwegian (Bokmål)',
					'Dutch',
					'Polish',
					'Portuguese',
					'Portuguese (Brazilian)',
					'Romanian',
					'Russian',
					'Slovak',
					'Slovenian',
					'Swedish',
					'Turkish',
					'Ukrainian',
					'Chinese',
					'Vietnamese',
					'Arabic'
				);
				update_option('ai_scribe_languages', $langArr);
        	} else {
        		$langArr = get_option('ai_scribe_languages');
        	}


			$writingStyleArr = array(
				'Informal',
				'Creative',
				'Academic',
				'Business',
				'Creative',
				'Journalistic',
				'Scientific'
			);
			$writingToneArr  = array(
				'Casual',
				'Funny',
				'Excited',
				'Professional',
				'Witty',
				'Sarcastic',
				'Feminine',
				'Masculine',
				'Bold',
				'Dramatic',
				'Grumpy',
				'Secretive'
			);
			$subhedingArr    = array( 'H2', 'H3', 'H4', 'H5' );
			$checkArr        = array(
				'addQNA'           => 'Add Q&As',
				'addkeywordBold'   => 'Suggest keywords to make bold',
				'addinsertHyper'   => 'Suggest keywords to add hyperlinks',
				'addinsertToc'     => 'Insert TOC',
				'addfurtheReading' => 'Suggest related topics of interest',
				'addsubMatter'     => 'Suggest authorities on the subject matter for further reading',
				'addimgCont'       => 'Suggest ideas for suitable images and video content',
			);
			?>
			<input type="hidden" name="action" value="al_scribe_content_data">

			<h3>Language, Style &amp; Tone</h3>
			<div style="border: 1px dotted grey !important; padding: 20px; margin: 0 0 20px 0">
				Global settings to control the writing style.
			</div>
			<div class="gform">
				<label for="fname">Language:-</label>
				<select name="language">
					<option value="" disabled>Select Language</option>
					<?php foreach ( $langArr as $langkey => $langvalue ) {
						?>
						<option <?php selected( $langvalue, $lang ); ?>
							value="<?php echo esc_attr( $langvalue ); ?>"><?php echo esc_attr( $langvalue ); ?></option>
						<?php
					} ?>
				</select>
				<div class="gform">
				    <label for="custom_language">Add New Language:</label>
				    <input type="text" name="custom_language" placeholder="Enter new language" />
				    <!--<input type="submit" name="add_language" value="Add Language" />-->
				</div>
			</div>
			<div class="gform">
				<label for="fname">Writing Style:-</label>
				<select name="writing_style">
					<option value="" disabled>Select Writing Style</option>
					<?php foreach ( $writingStyleArr as $writingStyleArrkey => $writingStyleArrvalue ) {
						?>
						<option <?php echo selected( $writingStyleArrvalue, $writing_style ); ?>
							value="<?php echo esc_attr( $writingStyleArrvalue ); ?>"><?php echo esc_attr( $writingStyleArrvalue ); ?></option>
						<?php
					} ?>
				</select>
			</div>
			<div class="gform">
				<label for="fname">Writing Tone:-</label>
				<select name="writing_tone">
					<option value="" disabled>Select Writing Tone</option>
					<?php foreach ( $writingToneArr as $writingToneArrkey => $writingToneArrvalue ) {
						?>
						<option <?php echo selected( $writingToneArrvalue, $writing_tone ); ?>
							value="<?php echo esc_attr( $writingToneArrvalue ); ?>"><?php echo esc_attr( $writingToneArrvalue ); ?></option>
						<?php
					} ?>

				</select>
			</div>
			<h3 class="Language-border">Headings</h3>

			<div style="border: 1px dotted grey !important; padding: 20px; margin: 0 0 20px 0">
				Global settings to control the structure of your article.
			</div>

			<div class="gform">
				<label for="fname">Number Of Heading:-</label>

				<input type="number" name="number_of_heading" value="<?php echo esc_attr( $number_of_heading ); ?>">
			</div>
			<div class="gform">
				<label for="fname">Heading Tags:-</label>
				<select name="Heading_tag">
					<option value="" disabled>Select Sub Headings</option>
					<?php foreach ( $subhedingArr as $subhedingArrkey => $subhedingArrvalue ) {
						?>
						<option <?php echo selected( $subhedingArrvalue, $Heading_tag ); ?>
							value="<?php echo esc_attr( $subhedingArrvalue ); ?>"><?php echo esc_attr( $subhedingArrvalue ); ?></option>
						<?php
					} ?>
				</select>
			</div>
			<div class="prompts_opt_sec">
				<h3 class="Language-border" id="prompt-settings">Prompts Settings</h3>
				<div style="border: 1px dotted grey !important; padding: 20px; margin: 0 0 20px 0">
					<p>The following prompts can be modified according to your personal preferences and saved.</p>
					<p style="font-weight:bold">Prompt Parameters</p>
					<p>The below parameters can be inserted into any of the prompts below (in any order) but they must
						be used exactly:</p>
					<ol>
						<li>[Language]</li>
						<li>[Style]</li>
						<li>[Tone]</li>
						<li>[Title]</li>
						<li>[Selected Keywords]</li>
						<li>[The Tagline] - using [above/below] for the position</li>
						<li>[No. Headings]</li>
						<li>[Heading Tag]</li>
						<li>[Keywords to Avoid]</li>
					</ol>
				</div>

				<!-- Get mode - 12.10.24 -->
				<div class="gform">
					<P style="background-color: #facd9d; padding:15px;"><strong>New for v2.0: </strong> Give your content a boost with our <strong>"Humanize"</strong> and <strong>"Humanize with Personality"</strong> options. These go beyond standard writing style and tone to give your content a new lease of life. Given them a try and let us know what you think.</P>
				    <div class="mode-selection">
				        <label for="mode_select">Humaize Mode:-</label>
				            <!-- HTML Form: Ensure correct mode is shown as selected -->
				            <select id="mode_select" name="mode">
				                <option value="standard" <?php echo selected('standard', $selected_mode); ?>>Standard Mode</option>
				                <option value="humanize" <?php echo selected('humanize', $selected_mode); ?>>Humanize</option>
				                <option value="personality" <?php echo selected('personality', $selected_mode); ?>>Humanize with Personality</option>
				            </select>
				    </div>
				</div>


				<div class="gform">
				    <label>Custom Instructions:-</label>
				    <textarea name="prompts_content[instructions_prompts]">
				        <?php echo esc_textarea(stripslashes( $instructionsPrompts )); ?>
				    </textarea>
				</div>



				<div class="gform">
					<label>Title:-</label>
					<textarea
						name="prompts_content[title_prompts]"><?php echo esc_textarea(stripslashes( $titlePrompts )); ?></textarea>
				</div>
				<div class="gform">
					<label>Keywords:-</label>
					<textarea
						name="prompts_content[Keywords_prompts]"><?php echo esc_textarea(stripslashes( $KeywordsPrompts )); ?></textarea>
				</div>
				<div class="gform">
					<label>Outline:-</label>
					<textarea
						name="prompts_content[outline_prompts]"><?php echo esc_textarea(stripslashes( $outlinePrompts )); ?></textarea>
				</div>
				<div class="gform">
					<label>Intro:-</label>
					<textarea
						name="prompts_content[intro_prompts]"><?php echo esc_textarea(stripslashes( $introPrompts )); ?></textarea>
				</div>
				<div class="gform">
					<label>Tagline:-</label>
					<textarea
						name="prompts_content[tagline_prompts]"><?php echo esc_textarea(stripslashes( $taglinePrompts )); ?></textarea>
				</div>
				<div class="gform">
					<label>Main Body:-</label>
					<textarea
						name="prompts_content[article_prompts]"><?php echo esc_textarea(stripslashes( $articlePrompts )); ?></textarea>
				</div>
				<div class="gform">
					<label>Conclusion:-</label>
					<textarea
						name="prompts_content[conclusion_prompts]"><?php echo esc_textarea(stripslashes( $conclusionPrompts )); ?></textarea>
				</div>
				<div class="gform">
					<label>Q&A's:-</label>
					<textarea name="prompts_content[qa_prompts]"><?php echo esc_textarea(stripslashes( $qaPrompts )); ?></textarea>
				</div>
				<div class="gform">
					<label>Meta Data:-</label>
					<textarea
						name="prompts_content[meta_prompts]"><?php echo esc_textarea(stripslashes( $metaPrompts )); ?></textarea>
				</div>
				<div class="gform">
					<label>Article:-</label>
					<textarea
						name="prompts_content[review_prompts]"><?php echo esc_textarea(stripslashes( $reviewPrompts )); ?></textarea>
				</div>
				<div class="gform">
					<label>Evaluate:-</label>
					<textarea
						name="prompts_content[evaluate_prompts]"><?php echo esc_textarea(stripslashes( $evaluatePrompts )); ?></textarea>
				</div>
				<p class="helping_info"></p>
			</div>
			<h3 class="Additional-border">Enhancements</h3>
			<div style="border: 1px dotted grey !important; padding: 20px; margin: 0 0 20px 0">
				Global settings to control additional aspects of the article. Currently these settings are in BETA, as
				some may cause issues depending on the AI Engine/Model selected. Currently, only Q&amp;A and TOC will
				update the actual article. All others are suggestions only and will be shown at the Evaluate &amp;
				Enhance stage.
			</div>
			<div class="gform gform-checkbox">
				<label for="fname">Keywords to avoid (comma separated):-</label>
				<input type="text" name="cs_list" class="" value="<?php echo esc_attr( $cslist ); ?>"
				       placeholder="Please add here...">
			</div>
			<div class="gform gform-checkbox">
				<?php foreach ( $checkArr as $checkArrkey => $checkArrvalue ) {
					$checked = "";
					if ( ! empty( $getcheckArray ) && in_array( $checkArrkey, $getcheckArray ) ) {
						$checked = "checked";
					}
					?>
					<p>
						<label for="fname"><?php echo esc_attr($checkArrvalue); ?></label>
						<input type="checkbox"
						       name="checkArr[<?php echo esc_attr( $checkArrkey ); ?>]" <?php echo esc_attr( $checked ); ?>
						       value="<?php echo esc_attr( $checkArrkey ); ?>" class="checkbox-input">
					</p>
					<?php
				} ?>
			</div>
			<div class="savebar"><input type="submit" name="submit" class="save-btn" value="save" id="submit_first"></div>
		</form>
		</div> <!-- Close tab-content -->
	</div>
</div>
<div class="form-step1 second_form" style="display: none;">
	<div class="tab-content">
		<form id="al_engine">
			<?php $getarr     = get_option( 'ab_gpt_ai_engine_settings' );
			// Set default model to GPT-4o if none selected (GPT-4.5 has issues)
			$model            = isset( $getarr['model'] ) ? $getarr['model'] : 'gpt-4o';
			$temp             = isset( $getarr['temp'] ) ? $getarr['temp'] : '';
			$maxTokens        = isset( $getarr['max_tokens'] ) ? $getarr['max_tokens'] : '';
			$top_p            = isset( $getarr['top_p'] ) ? $getarr['top_p'] : '';
			$best_oi          = isset( $getarr['best_oi'] ) ? $getarr['best_oi'] : '';
			$freq_pent        = isset( $getarr['freq_pent'] ) ? $getarr['freq_pent'] : '';
			$Presence_penalty = isset( $getarr['Presence_penalty'] ) ? $getarr['Presence_penalty'] : '';
			$api_key          = isset( $getarr['api_key'] ) ? $getarr['api_key'] : '';
			$anthropic_api_key = isset( $getarr['anthropic_api_key'] ) ? $getarr['anthropic_api_key'] : '';

			$modelArr = array(
				// OpenAI Models
				'gpt-4o' => 'OpenAI GPT-4o (128K)',
				'gpt-4o-mini' => 'OpenAI GPT-4o-mini (128K)',
				'gpt-4.5-preview' => 'OpenAI GPT-4.5 Preview (128K)',
				'o3' => 'OpenAI o3 (200K)',
				// Anthropic Models
				'claude-sonnet-4-20250514' => 'Claude Sonnet 4 (200K)',
				'claude-opus-4-20250514' => 'Claude Opus 4 (200K)',
			);

			?>
			<div style="border: 1px dotted grey !important; padding: 20px; margin: 0 0 20px 0">
					<h3>🚀 AI Scribe v2.6 - Next-Generation AI Models</h3>
					<p>Global settings to control the behaviour of your chosen AI model. We now support both OpenAI and Anthropic models, giving you access to the most advanced AI capabilities available.</p>

					<div style="background-color: #e8f5e8; padding:15px; border-left: 4px solid #4caf50; margin: 15px 0;">
						<strong>🆕 New in v2.6:</strong> Support for GPT-4.5, OpenAI o3, Claude Sonnet 4 & Opus 4, plus GPT-4o image generation!
					</div>

					<div style="background-color: #f0f8ff; padding:15px; border-left: 4px solid #0073aa; margin: 15px 0;">
						<h4>📊 Model Comparison:</h4>
						<strong>OpenAI Models:</strong><br/>
						• <strong>GPT-4o:</strong> 128K tokens | $0.005/$0.02 per 1K tokens<br/>
						• <strong>GPT-4o-mini:</strong> 128K tokens | $0.0006/$0.0024 per 1K tokens<br/>
						• <strong>GPT-4.5 Preview:</strong> 128K tokens | $0.075/$0.15 per 1K tokens<br/>
						• <strong>o3:</strong> 200K tokens | $0.01/$0.04 per 1K tokens<br/><br/>

						<strong>Anthropic Models:</strong><br/>
						• <strong>Claude Sonnet 4:</strong> 200K tokens | $0.003/$0.015 per 1K tokens<br/>
						• <strong>Claude Opus 4:</strong> 200K tokens | $0.015/$0.075 per 1K tokens<br/><br/>

						<strong>🖼️ Image Generation:</strong> GPT-image-1 (GPT-4o) | $0.01-$0.17 per image + $0.005 per 1K tokens for prompts<br/>
						<small style="color: #666;">[Pricing as of January 2025]</small>
					</div>
			
			<!-- AI Engine & Model Settings Container -->
			<div class="settings-section">
				<h3 class="section-title">🤖 AI Engine & Model Settings</h3>
				<div class="section-content">
					<div class="smart-interface-notice">
						<strong>🎯 Smart Interface:</strong> Settings automatically adjust based on your selected model. Only relevant parameters will be shown.
					</div>
					
					<input type="hidden" name="action" value="al_scribe_engine_request_data">
					
					<div class="form-row">
						<label for="model-select" class="form-label">Model:</label>
						<select name="model" id="model-select" class="form-select">
							<?php foreach ( $modelArr as $modelKey => $modelLabel ) { ?>
								<option <?php echo selected( $modelKey, $model ); ?>
									value="<?php echo esc_attr( $modelKey ); ?>"><?php echo esc_html( $modelLabel ); ?></option>
							<?php } ?>
						</select>
					</div>
				</div>
			</div>
			
			<!-- Model Parameters Container -->
			<div class="settings-section">
				<h3 class="section-title">⚙️ Model Parameters</h3>
				<div class="section-content">
					<div class="form-row" id="temperature-input">
						<label for="temp" class="form-label">Temperature:</label>
						<input type="number" step="0.1" name="temp" min="0" max="1" value="<?php echo esc_attr( $temp ); ?>"
						       placeholder="0.7" class="form-input">
					</div>
					
					<div class="form-row" id="reasoning-effort-select" style="display: none;">
						<label for="reasoning_effort" class="form-label">Reasoning Effort:</label>
						<select name="reasoning_effort" id="reasoning_effort" class="form-select">
							<option value="low" <?php selected( $temp <= 0.3 ? 'low' : '', 'low' ); ?>>Low</option>
							<option value="medium" <?php selected( ($temp > 0.3 && $temp < 0.7) ? 'medium' : '', 'medium' ); ?>>Medium</option>
							<option value="high" <?php selected( $temp >= 0.7 ? 'high' : '', 'high' ); ?>>High</option>
						</select>
					</div>
					
					<div class="form-row">
						<label for="top_p" class="form-label">Top P:</label>
						<input type="number" name="top_p" step="0.1" min="0" max="1" value="<?php echo esc_attr( $top_p ); ?>"
						       placeholder="0.01" class="form-input">
					</div>
					
					<div class="form-row">
						<label for="best_oi" class="form-label">Best OI:</label>
						<input type="number" name="best_oi" step="0.1" min="0" max="20"
						       value="<?php echo esc_attr( $best_oi ); ?>"
						       placeholder="1" class="form-input">
					</div>
					
					<div class="form-row">
						<label for="freq_pent" class="form-label">Frequency Penalty:</label>
						<input type="number" name="freq_pent" step="0.1" min="0" max="2"
						       value="<?php echo esc_attr( $freq_pent ); ?>"
						       placeholder="0.01" class="form-input">
					</div>
					
					<div class="form-row">
						<label for="Presence_penalty" class="form-label">Presence Penalty:</label>
						<input type="number" name="Presence_penalty" step="0.1" min="0" max="2"
						       value="<?php echo esc_attr( $Presence_penalty ); ?>" placeholder="0.01" class="form-input">
					</div>
				</div>
			</div>
			<!-- Image Generation Section -->
			<div class="image-generation-section" style="border: 2px solid #28a745; padding: 20px; margin: 20px 0; background-color: #f8fff8;">
				<h3 style="color: #28a745; margin-top: 0;">🖼️ Image Generation Settings</h3>
				<p style="background-color: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0;">
					<strong>Enhanced Feature:</strong> Generate AI images using GPT-4o. Works with all text models (OpenAI + Anthropic).
				</p>
				
				<div id="image-generation-checkbox-container" style="margin: 15px 0;">
					<div style="display: flex; align-items: center; margin-bottom: 10px;">
						<input type="checkbox" name="enable_image_generation" id="enable_image_generation" value="1"
							<?php checked(1, get_option('ab_enable_image_generation', 1)); ?> style="margin-right: 10px;">
						<label for="enable_image_generation" style="margin: 0; font-weight: bold;">
							Enable AI Image Generation
						</label>
					</div>
					
					<small id="image-generation-description" style="color: #666; display: block; margin-left: 25px;">
						When enabled, articles can include AI-generated images using GPT-4o. Requires OpenAI API key.
					</small>
				</div>
				
				<!-- Image Generation Settings -->
				<div id="image-generation-settings" style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9;">
					<h4 style="margin-top: 0; color: #333;">Image Generation Settings</h4>
					
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
						<div>
							<label for="image_size" style="display: block; margin-bottom: 5px; font-weight: bold;">Size</label>
							<select name="image_size" id="image_size" style="width: 100%; padding: 5px;">
								<option value="auto" <?php selected(get_option('ab_image_size', 'auto'), 'auto'); ?>>Auto</option>
								<option value="square" <?php selected(get_option('ab_image_size', 'auto'), 'square'); ?>>Square (1024×1024)</option>
								<option value="portrait" <?php selected(get_option('ab_image_size', 'auto'), 'portrait'); ?>>Portrait (1024×1536)</option>
								<option value="landscape" <?php selected(get_option('ab_image_size', 'auto'), 'landscape'); ?>>Landscape (1536×1024)</option>
							</select>
						</div>
						
						<div>
							<label for="image_quality" style="display: block; margin-bottom: 5px; font-weight: bold;">Quality</label>
							<select name="image_quality" id="image_quality" style="width: 100%; padding: 5px;">
								<option value="high" <?php selected(get_option('ab_image_quality', 'high'), 'high'); ?>>High</option>
								<option value="medium" <?php selected(get_option('ab_image_quality', 'high'), 'medium'); ?>>Medium</option>
								<option value="low" <?php selected(get_option('ab_image_quality', 'high'), 'low'); ?>>Low</option>
							</select>
						</div>
					</div>
					
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
						<div>
							<label for="image_format" style="display: block; margin-bottom: 5px; font-weight: bold;">Output Format</label>
							<select name="image_format" id="image_format" style="width: 100%; padding: 5px;">
								<option value="png" <?php selected(get_option('ab_image_format', 'png'), 'png'); ?>>PNG</option>
								<option value="webp" <?php selected(get_option('ab_image_format', 'png'), 'webp'); ?>>WebP</option>
								<option value="jpeg" <?php selected(get_option('ab_image_format', 'png'), 'jpeg'); ?>>JPEG</option>
							</select>
						</div>
						
						<div>
							<label for="image_background" style="display: block; margin-bottom: 5px; font-weight: bold;">Background</label>
							<select name="image_background" id="image_background" style="width: 100%; padding: 5px;">
								<option value="auto" <?php selected(get_option('ab_image_background', 'auto'), 'auto'); ?>>Auto</option>
								<option value="transparent" <?php selected(get_option('ab_image_background', 'auto'), 'transparent'); ?>>Transparent</option>
								<option value="opaque" <?php selected(get_option('ab_image_background', 'auto'), 'opaque'); ?>>Opaque</option>
							</select>
						</div>
					</div>
					
					<div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
						<div>
							<label for="image_style" style="display: block; margin-bottom: 5px; font-weight: bold;">Style</label>
							<select name="image_style" id="image_style" style="width: 100%; padding: 5px;">
								<option value="Photorealistic" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Photorealistic'); ?>>Photorealistic</option>
								<option value="Cinematic lighting" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Cinematic lighting'); ?>>Cinematic lighting</option>
								<option value="Watercolour painting" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Watercolour painting'); ?>>Watercolour painting</option>
								<option value="Oil painting" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Oil painting'); ?>>Oil painting</option>
								<option value="Pencil sketch" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Pencil sketch'); ?>>Pencil sketch</option>
								<option value="Charcoal drawing" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Charcoal drawing'); ?>>Charcoal drawing</option>
								<option value="Line art" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Line art'); ?>>Line art</option>
								<option value="Vector illustration" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Vector illustration'); ?>>Vector illustration</option>
								<option value="Cartoon Illustration" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Cartoon Illustration'); ?>>Cartoon Illustration</option>
								<option value="Handdrawn Sketch" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Handdrawn Sketch'); ?>>Handdrawn Sketch</option>
								<option value="Pop art" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Pop art'); ?>>Pop art</option>
								<option value="Retro 80s" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Retro 80s'); ?>>Retro 80s</option>
								<option value="Cyberpunk" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Cyberpunk'); ?>>Cyberpunk</option>
								<option value="Fantasy art" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Fantasy art'); ?>>Fantasy art</option>
								<option value="Surrealist" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Surrealist'); ?>>Surrealist</option>
								<option value="Minimalist" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Minimalist'); ?>>Minimalist</option>
								<option value="3D render" <?php selected(get_option('ab_image_style', 'Photorealistic'), '3D render'); ?>>3D render</option>
								<option value="Monochrome" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Monochrome'); ?>>Monochrome</option>
								<option value="Impressionist" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Impressionist'); ?>>Impressionist</option>
								<option value="Low-poly" <?php selected(get_option('ab_image_style', 'Photorealistic'), 'Low-poly'); ?>>Low-poly</option>
							</select>
						</div>
					</div>
				</div>
			</div>

			<!-- API Keys Section -->
			<div class="api-keys-section" style="border: 2px solid #0073aa; padding: 20px; margin: 20px 0; background-color: #f0f8ff;">
				<h3 style="color: #0073aa; margin-top: 0;">🔑 API Keys Configuration</h3>
				<p style="background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 10px 0;">
					<strong>Important:</strong> You need an Anthropic API key for Claude models. You need an OpenAI API key for GPT models and image generation. If you plan to use Claude models with image generation, you'll need <strong>both</strong> API keys.
				</p>

				<div class="gform">
					<label for="openai_api_key"><strong>OpenAI API Key</strong> (Required for GPT models & image generation)</label>
					<input type="text" name="api_key" id="openai_api_key" value="<?php echo esc_attr( $api_key ); ?>" placeholder="sk-...">
					<small style="color: #666;">Used for: GPT-4o, GPT-4o-mini, GPT-4.5 Preview, o3, and all image generation</small>
				</div>

				<div class="gform">
					<label for="anthropic_api_key"><strong>Anthropic API Key</strong> (Required for Claude models)</label>
					<input type="text" name="anthropic_api_key" id="anthropic_api_key" value="<?php echo esc_attr( $anthropic_api_key ); ?>" placeholder="sk-ant-...">
					<small style="color: #666;">Used for: Claude Sonnet 4, Claude Opus 4</small>
				</div>
			</div>
			<div class="gform">
				<div style="border: 1px dotted grey !important; padding: 20px; margin: 0 0 20px 0">
					<h3>Where to find your API key?</h3>
					<p style="font-size: 14px;">If you don't have an OpenAI account yet, you can sign up for one here:<a
							href="https://beta.openai.com/signup">https://beta.openai.com/signup</a></p>
					<p style="font-size: 14px;">
						Log into your OpenAI account. Click your username in the top righthand corner and select “View
						API keys”, then on the next screen, create or copy an API key.</p>
					<h3>
						Slideshow showing how to access your OpenAI API key
					</h3>
					<blockquote class="instagram-media"
					            data-instgrm-permalink="https://www.instagram.com/p/CqaNQJqtaXT/?utm_source=ig_embed&amp;utm_campaign=loading"
					            data-instgrm-version="14"
					            style=" background:#FFF; border:0; border-radius:3px; box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15); margin: 1px; max-width:95%; min-width:95%; padding:0; width:99.375%; width:-webkit-calc(100% - 2px); width:calc(100% - 2px);">
						<div style="padding:16px;"><a
								href="https://www.instagram.com/p/CqaNQJqtaXT/?utm_source=ig_embed&amp;utm_campaign=loading"
								style=" background:#FFFFFF; line-height:0; padding:0 0; text-align:center; text-decoration:none; width:100%;"
								target="_blank">
								<div style=" display: flex; flex-direction: row; align-items: center;">
									<div
										style="background-color: #F4F4F4; border-radius: 50%; flex-grow: 0; height: 40px; margin-right: 14px; width: 40px;"></div>
									<div
										style="display: flex; flex-direction: column; flex-grow: 1; justify-content: center;">
										<div
											style=" background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; margin-bottom: 6px; width: 100px;"></div>
										<div
											style=" background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; width: 60px;"></div>
									</div>
								</div>
								<div style="padding: 19% 0;"></div>
								<div style="display:block; height:50px; margin:0 auto 12px; width:50px;">
									<svg width="50px" height="50px" viewBox="0 0 60 60" version="1.1"
									     xmlns="https://www.w3.org/2000/svg"
									     xmlns:xlink="https://www.w3.org/1999/xlink">
										<g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
											<g transform="translate(-511.000000, -20.000000)" fill="#000000">
												<g>
													<path
														d="M556.869,30.41 C554.814,30.41 553.148,32.076 553.148,34.131 C553.148,36.186 554.814,37.852 556.869,37.852 C558.924,37.852 560.59,36.186 560.59,34.131 C560.59,32.076 558.924,30.41 556.869,30.41 M541,60.657 C535.114,60.657 530.342,55.887 530.342,50 C530.342,44.114 535.114,39.342 541,39.342 C546.887,39.342 551.658,44.114 551.658,50 C551.658,55.887 546.887,60.657 541,60.657 M541,33.886 C532.1,33.886 524.886,41.1 524.886,50 C524.886,58.899 532.1,66.113 541,66.113 C549.9,66.113 557.115,58.899 557.115,50 C557.115,41.1 549.9,33.886 541,33.886 M565.378,62.101 C565.244,65.022 564.756,66.606 564.346,67.663 C563.803,69.06 563.154,70.057 562.106,71.106 C561.058,72.155 560.06,72.803 558.662,73.347 C557.607,73.757 556.021,74.244 553.102,74.378 C549.944,74.521 548.997,74.552 541,74.552 C533.003,74.552 532.056,74.521 528.898,74.378 C525.979,74.244 524.393,73.757 523.338,73.347 C521.94,72.803 520.942,72.155 519.894,71.106 C518.846,70.057 518.197,69.06 517.654,67.663 C517.244,66.606 516.755,65.022 516.623,62.101 C516.479,58.943 516.448,57.996 516.448,50 C516.448,42.003 516.479,41.056 516.623,37.899 C516.755,34.978 517.244,33.391 517.654,32.338 C518.197,30.938 518.846,29.942 519.894,28.894 C520.942,27.846 521.94,27.196 523.338,26.654 C524.393,26.244 525.979,25.756 528.898,25.623 C532.057,25.479 533.004,25.448 541,25.448 C548.997,25.448 549.943,25.479 553.102,25.623 C556.021,25.756 557.607,26.244 558.662,26.654 C560.06,27.196 561.058,27.846 562.106,28.894 C563.154,29.942 563.803,30.938 564.346,32.338 C564.756,33.391 565.244,34.978 565.378,37.899 C565.522,41.056 565.552,42.003 565.552,50 C565.552,57.996 565.522,58.943 565.378,62.101 M570.82,37.631 C570.674,34.438 570.167,32.258 569.425,30.349 C568.659,28.377 567.633,26.702 565.965,25.035 C564.297,23.368 562.623,22.342 560.652,21.575 C558.743,20.834 556.562,20.326 553.369,20.18 C550.169,20.033 549.148,20 541,20 C532.853,20 531.831,20.033 528.631,20.18 C525.438,20.326 523.257,20.834 521.349,21.575 C519.376,22.342 517.703,23.368 516.035,25.035 C514.368,26.702 513.342,28.377 512.574,30.349 C511.834,32.258 511.326,34.438 511.181,37.631 C511.035,40.831 511,41.851 511,50 C511,58.147 511.035,59.17 511.181,62.369 C511.326,65.562 511.834,67.743 512.574,69.651 C513.342,71.625 514.368,73.296 516.035,74.965 C517.703,76.634 519.376,77.658 521.349,78.425 C523.257,79.167 525.438,79.673 528.631,79.82 C531.831,79.965 532.853,80.001 541,80.001 C549.148,80.001 550.169,79.965 553.369,79.82 C556.562,79.673 558.743,79.167 560.652,78.425 C562.623,77.658 564.297,76.634 565.965,74.965 C567.633,73.296 568.659,71.625 569.425,69.651 C570.167,67.743 570.674,65.562 570.82,62.369 C570.966,59.17 571,58.147 571,50 C571,41.851 570.966,40.831 570.82,37.631"></path>
												</g>
											</g>
										</g>
									</svg>
								</div>
								<div style="padding-top: 8px;">
									<div
										style=" color:#3897f0; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:550; line-height:18px;">
										View this post on Instagram
									</div>
								</div>
								<div style="padding: 12.5% 0;"></div>
								<div
									style="display: flex; flex-direction: row; margin-bottom: 14px; align-items: center;">
									<div>
										<div
											style="background-color: #F4F4F4; border-radius: 50%; height: 12.5px; width: 12.5px; transform: translateX(0px) translateY(7px);"></div>
										<div
											style="background-color: #F4F4F4; height: 12.5px; transform: rotate(-45deg) translateX(3px) translateY(1px); width: 12.5px; flex-grow: 0; margin-right: 14px; margin-left: 2px;"></div>
										<div
											style="background-color: #F4F4F4; border-radius: 50%; height: 12.5px; width: 12.5px; transform: translateX(9px) translateY(-18px);"></div>
									</div>
									<div style="margin-left: 8px;">
										<div
											style=" background-color: #F4F4F4; border-radius: 50%; flex-grow: 0; height: 20px; width: 20px;"></div>
										<div
											style=" width: 0; height: 0; border-top: 2px solid transparent; border-left: 6px solid #f4f4f4; border-bottom: 2px solid transparent; transform: translateX(16px) translateY(-4px) rotate(30deg)"></div>
									</div>
									<div style="margin-left: auto;">
										<div
											style=" width: 0px; border-top: 8px solid #F4F4F4; border-right: 8px solid transparent; transform: translateY(16px);"></div>
										<div
											style=" background-color: #F4F4F4; flex-grow: 0; height: 12px; width: 16px; transform: translateY(-4px);"></div>
										<div
											style=" width: 0; height: 0; border-top: 8px solid #F4F4F4; border-left: 8px solid transparent; transform: translateY(-4px) translateX(8px);"></div>
									</div>
								</div>
								<div
									style="display: flex; flex-direction: column; flex-grow: 1; justify-content: center; margin-bottom: 24px;">
									<div
										style=" background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; margin-bottom: 6px; width: 224px;"></div>
									<div
										style=" background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; width: 144px;"></div>
								</div>
							</a>
							<p style=" color:#c9c8cd; font-family:Arial,sans-serif; font-size:14px; line-height:17px; margin-bottom:0; margin-top:8px; overflow:hidden; padding:8px 0 7px; text-align:center; text-overflow:ellipsis; white-space:nowrap;">
								<a href="https://www.instagram.com/p/CqaNQJqtaXT/?utm_source=ig_embed&amp;utm_campaign=loading"
								   style=" color:#c9c8cd; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:normal; line-height:17px; text-decoration:none;"
								   target="_blank">A post shared by Opace Digital Agency (@opacedigital)</a></p></div>
					</blockquote>
					<script async src="//www.instagram.com/embed.js"></script>
				</div>

			</div>
			<div class="savebar"><input type="submit" name="submit" class="save-btn" value="save"></div>
		</form>
	</div> <!-- Close tab-content -->
</div>
</body>
</html>
