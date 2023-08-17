<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>AI-Scribe Settings: ChatGPT SEO Content Creator</title>
</head>
<body id="body">
<div class="form-step1">
	<h2>AI-Scribe Settings - ChatGPT Powered SEO Article Generator</h2>

	<div class="form-text">
		<div class="button-div">
			<button class="al-engine" id="second">Content</button>
			<button class="al-engine" id="first">Al Engine</button>
		</div>
		<img
			src="<?php echo esc_url( AI_SCRIBE_URL . 'assets/2023/03/AI-Scribe-Logo-simplified.png' ) ?>"
			class="opace-logo" style="width:100px; margin-top:30px"/> <br/><br/>
		<button class="btn tab_regenerate_btn regen" onclick="document.location.href='./admin.php?page=ai_scribe_help'">READ ME
			INFORMATION
		</button>
		<form class="first_form" id="frmFirst" method="post">
			<?php $getarr      = get_option( 'ab_gpt3_content_settings' );
			$promptsContent    = get_option( 'ab_prompts_content' );
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
				'Chinese'

			);

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
			<input type="submit" name="submit" class="save-btn" value="save" id="submit_first">
		</form>
	</div>
</div>
<div class="form-step1 second_form" style="display: none;">
	<div class="form-text">
		<form id="al_engine">
			<?php $getarr     = get_option( 'ab_gpt3_ai_engine_settings' );
			$model            = isset( $getarr['model'] ) ? $getarr['model'] : '';
			$temp             = isset( $getarr['temp'] ) ? $getarr['temp'] : '';
			$maxTokens        = isset( $getarr['max_tokens'] ) ? $getarr['max_tokens'] : '';
			$top_p            = isset( $getarr['top_p'] ) ? $getarr['top_p'] : '';
			$best_oi          = isset( $getarr['best_oi'] ) ? $getarr['best_oi'] : '';
			$freq_pent        = isset( $getarr['freq_pent'] ) ? $getarr['freq_pent'] : '';
			$Presence_penalty = isset( $getarr['Presence_penalty'] ) ? $getarr['Presence_penalty'] : '';
			$api_key          = isset( $getarr['api_key'] ) ? $getarr['api_key'] : '';

			$modelArr = array(
				/*'text-curie-001',
				'text-davinci-002',*/
				'text-davinci-003',
				"gpt-3.5-turbo",
				"gpt-4",
				"gpt-3.5-turbo-16k",
				"gpt-4-32k",
			);

			?>
			<div class="gform">
				<h3>AI Engine &amp; Model Settings</h3>
				<div style="border: 1px dotted grey !important; padding: 20px; margin: 0 0 20px 0">
					Global settings to control the behaviour of your chosen model. Using standard model names like gpt-4 or gpt-3.5-turbo will default to the very latest version of each model.
					<ul>
						<li><strong>Temperature:</strong> Helps&nbsp;the model to make creative or focused answers.
							Higher temperature = more creative, lower temperature = more focused.
						</li>
						<li><strong>Top P:</strong> Picks the best ideas. Lower Top P = only the best ideas, higher Top
							P = more variety of ideas.
						</li>
						<li><strong>Best OI:</strong> A technique that encourages the model to choose the best possible
							word to complete a sentence, based on what it has learned.
						</li>
						<li><strong>Frequency Penalty:</strong> A setting that stops&nbsp;the model from repeating words
							or phrases too much, making its answers more varied and interesting.
						</li>
						<li><strong>Presence Penalty:</strong> A setting that helps&nbsp;the model not say things it
							already said, making its answers less repetitive and more useful.
						</li>
						<li><strong>API Key:</strong> A special code that lets the AI-Scribe plugin talk to&nbsp;OpenAI&nbsp;and
							get answers from it. It's like a secret password to use GPT's smartness. Read more below.
						</li>
					</ul>
				</div>
				<input type="hidden" name="action" value="al_scribe_engine_request_data">
				<label for="fname">Models: <br/>davinci &amp; gpt-3.5-turbo (4K max tokens)<br/>gpt-3.5-turbo-16k (16K max tokens)<br/>gpt-4-32k (32K max tokens)</label>
				<select name="model">
					<?php foreach ( $modelArr as $modelArrkey => $modelArrvalue ) {
						$selected = "";
						if ( $modelArrvalue == $model ) {
							$selected = "selected";
						}
						?>
						<option <?php echo selected( $modelArrvalue, $model ); ?>
							value="<?php echo esc_attr( $modelArrvalue ); ?>"><?php echo esc_attr( $modelArrvalue ); ?></option>
						<?php
					} ?>
				</select>
			</div>
			<div class="gform">
				<label for="fname">Tempeature</label>
				<input type="number" step="0.1" name="temp" min="0" max="1" value="<?php echo esc_attr( $temp ); ?>"
				       placeholder="0.7">
			</div>
			<div class="gform">
				<label for="fname">Top P</label>
				<input type="number" name="top_p" step="0.1" min="0" max="1" value="<?php echo esc_attr( $top_p ); ?>"
				       placeholder="0.01">
			</div>
			<div class="gform">
				<label for="fname">Best OI</label>
				<input type="number" name="best_oi" step="0.1" min="0" max="20"
				       value="<?php echo esc_attr( $best_oi ); ?>"
				       placeholder="1">
			</div>
			<div class="gform">
				<label for="fname">Frequency Penalty</label>
				<input type="number" name="freq_pent" step="0.1" min="0" max="2"
				       value="<?php echo esc_attr( $freq_pent ); ?>"
				       placeholder="0.01">
			</div>
			<div class="gform">
				<label for="fname">Presence Penalty</label>
				<input type="number" name="Presence_penalty" step="0.1" min="0" max="2"
				       value="<?php echo esc_attr( $Presence_penalty ); ?>" placeholder="0.01">
			</div>
			<div class="gform">
				<label for="fname">Api Key</label>
				<input type="text" name="api_key" value="<?php echo esc_attr( $api_key ); ?>">
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
			<input type="submit" name="submit" class="save-btn" value="save">
		</form>
	</div>
</div>
</body
</html>
