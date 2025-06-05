<?php
$getarr            = get_option( 'ab_gpt3_content_settings' );
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
$writingStyleArr   = array( 'Creative', 'Informal', 'Academic', 'Business', 'Creative', 'Journalistic', 'Scientific' );
$writingToneArr    = array(
	'Funny',
	'Casual',
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
$subhedingArr      = array( 'H2', 'H3', 'H4', 'H5' );
$checkArr          = array(
	'addkeywordBold' => 'Format Keywords in Bold:-',
	'addinsertToc'   => 'Insert TOC',
	'addinsertHyper' => 'Identify Suitable Text to Add Hyperlinks :- ',
);


?>
<div class="create_template_cont_sec">
	<center><img class="opace-logo"
	             src="<?php echo esc_url( AI_SCRIBE_URL . 'assets/2023/03/AI-Scribe-Logo-simplified.png' ) ?>"
	             alt="opace logo">
	<div class="plugin-version">Version: <?php echo AI_SCRIBE_VER; ?></div>
	</center>
	<!-- header-start -->
	<div class="article_header">

		<header>
			<div class="header-main">
				<div class="temp-progress-bar progress-menu-bar">
					<div data-step="1" class="step active_step">
						<p>Brainstorm<br/>Titles</p>
						<div class="bullet nav-step"><span>1</span></div>

					</div>
					<div data-step="2" id="keywordnav" class="step">
						<p>Identify<br/>Keywords</p>
						<div class="bullet nav-step"><span>2</span></div>
					</div>
					<div data-step="3" class="step">
						<p>Generate<br/>Outline</p>
						<div class="bullet nav-step"><span>3</span></div>
						<div class="check fas fa-check"></div>
					</div>
					<div data-step="4" class="step">
						<p>Create<br/>Intro</p>
						<div class="bullet nav-step"><span>4</span></div>
						<div class="check fas fa-check"></div>
					</div>
					<div data-step="5" class="step">
						<p>Add<br/>Tagline</p>
						<div class="bullet nav-step"><span>5</span></div>
						<div class="check fas fa-check"></div>
					</div>
					<div data-step="6" class="step article_nav">
						<p>Create<br/>Main Body</p>
						<div class="bullet nav-step"><span>6</span></div>
						<div class="check fas fa-check"></div>
					</div>
					<div data-step="7" class="step">
						<p>Build<br/>Conclusion</p>
						<div class="bullet nav-step"><span>7</span></div>
						<div class="check fas fa-check"></div>
					</div>
					<div data-step="8" class="step" id="qnaBar">
						<p>Add<br/>Q&amp;A's</p>
						<div class="bullet nav-step"><span>8</span></div>
						<div class="check fas fa-check"></div>
					</div>
					<div data-step="9" class="step">
						<p>Build<br/>Meta Data</p>
						<div class="bullet nav-step"><span id="metaPosition">9</span></div>
						<div class="check fas fa-check"></div>
					</div>
					<div data-step="10" class="step">
						<p>View<br/>&amp; Publish</p>
						<div class="bullet nav-step"><span>10</span></div>
						<div class="check fas fa-check"></div>
					</div>
					<div data-step="11" class="step">
						<p>Evaluate<br/>&amp; Enhance</p>
						<div class="bullet nav-step"><span>11</span></div>
						<div class="check fas fa-check"></div>
					</div>
				</div>
			</div>
		</header>
	</div>
	<!-- header-end -->

	<div class="article-main">

		<div class="progress-container" style="display:none;">
			<h2>AI is currently writing the article for you.</h2>
			<p class="pro-p">Please keep this page open until the process is completed. It can take up to 5 minutes for slower models.</p>

			<div id="Progress">
				<div id="progressBar"><b>7%</b></div>
			</div>
		</div>
		<div class="article-left">
			<!-- title-start -->
			<div class="main-common at_temp_sec at_temp_sec_1 active_step " id="step1">
				<h1 class="bratop-headingin">

					Brainstorm Article Title Ideas
				</h1>
				<div class="maincontent">
					<div class="title_div  genrate">
						<input class="keywords action_val_field " type="text" id="tab_input" name="tab_input"
						       placeholder=" Add your article title idea here...."/>
						<button data-action="title" class="btn tab_generate_btn">
							Generate
						</button>
					</div>
					<div>
						<label for=""><h2 class="label-last">Select a title for your article</h2></label>
						<div class="main-div3 title_class">
							<div class="title-idea">
								<ul class="ul1">
									Please wait, your results will show here...
								</ul>
							</div>
						</div>
						<div class="skip_cont_div">
							<button class="btn generate_more_btn" data-action="title" generate-more="generate_more">
								Generate more
							</button>
							<button class="btn next_step_btn" data-nextstep="2" auto-generate='cont_next_step'>
								Continue
							</button>
						</div>
					</div>
				</div>
			</div>
			<!-- title-end -->
			<!-- Keywords-start -->
			<div class="main-common at_temp_sec at_temp_sec_2" id="step2" style="display:none;">
				<h1 class="top-heading">
					Find Relevant Keywords Automatically
				</h1>
				<div class="closest-obj">
					<button class="btn next_step_btn" data-nextstep="1" id="keywordback" back-btn="back">BACK</button>
					<div class="maincontent">
						<div class="genrate_input_sec" style="display:none;">
							<input class="keywords action_val_field" type="text" id="tab_keyword" name="tab_keyword"
							       placeholder=" Add your keyword ideas here..."/>
							<button class="btn tab_generate_btn" data-action="keyword">Generate</button>
						</div>
						<label for=""><h2 class="label-last">Your previous selections</h2></label>
						<div class="title-idea checked-element" id="keycheckedval">
							<ul class="ul1">
								<h2 class="">Your checked value show here... </h2>
							</ul>
						</div>
						<div>
							<label for=""><h2 class="label-last">Select keywords for your article</h2></label>
							<div class="title_class ">
								<div class="title-idea">
									<ul class="ul1">
										Please wait, your results will show here...
									</ul>
								</div>
							</div>
							<div class="skip_cont_div">
								<button class="btn next_step_btn" data-nextstep="3" skip-btn="skip">Skip</button>
								<button class="btn generate_more_btn" data-action="keyword"
								        generate-more="generate_more">Generate More
								</button>
								<button class="btn next_step_btn" data-nextstep="3" auto-generate='cont_next_step'>
									Continue
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- Keywords-end -->

			<!-- Outline-start -->
			<div class="main-common at_temp_sec at_temp_sec_3" id="step3" style="display:none;">
				<h3 class="top-heading">
					SUGGEST AN ARTICLE OUTLINE AND SECTION HEADINGS
				</h3>
				<div class="parentmain-div2">
					<div class="closest-obj">
						<button class="btn next_step_btn" data-nextstep="2" back-btn="back">BACK</button>
						<div class="maincontent">
							<div class="genrate_input_sec" style="display:none;">
								<textarea class="inputtt action_val_field " placeholder="Add your outline idea here"
								          name="w3review"></textarea>
								<button class="btn tab_generate_btn" data-action="heading">Generate</button>
							</div>
							<label for=""><h2 class="label-last">Your previous selections</h2></label>
							<div class="title-idea checked-element">
								<ul class="ul1">
									<h2>Your checked value show here... </h2>
								</ul>
							</div>
							<label for=""><h2 class="label-last">Select below if you are happy with the article
									outline</h2></label>
							<div class="main-div3 title_class">
								<div class="title-idea">
									<ul class="ul1">
										Please wait, your results will show here...
									</ul>
								</div>
							</div>
							<div class="skip_cont_div">
								<button class="btn next_step_btn" data-nextstep="4" skip-btn="skip">Skip</button>
								<button class="btn generate_more_btn" data-action="heading"
								        generate-more="generate_more">Generate More
								</button>
								<button class="btn next_step_btn" data-nextstep="4" auto-generate='cont_next_step'>
									Continue
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- Outline-end -->
			<!-- Intro -strat -->
			<div class="main-common at_temp_sec at_temp_sec_4" id="step4" style="display:none;">
				<h3 class="top-heading">
					Generate Intro
				</h3>
				<div class="closest-obj">
					<div class="introo">
						<button class="btn next_step_btn" data-nextstep="3" back-btn="back">BACK</button>
						<div class="maincontent">
							<div class="genrate_input_sec" style="display:none;">
								<textarea id="w3review" name="w3review" class="action_val_field"
								          placeholder="Add your intro idea here" rows="5" cols="40"></textarea>
								<button class="btn tab_generate_btn" data-action="intro">Generate Intro</button>

							</div>
							<label for=""><h2 class="label-last">Your previous selections</h2></label>
							<div class="title-idea checked-element">
								<ul class="ul1">
									<h2>Your checked value show here... </h2>
								</ul>
							</div>

							<div>
								<label for=""><h2 class="label-last">Select below if you are happy with the
										introduction</h2></label>
								<div class="title_class">
									<div class="title-idea">
										<ul class="ul1">
											Please wait, your results will show here...
										</ul>
									</div>
								</div>
								<div class="skip_cont_div">
									<button class="btn next_step_btn" data-nextstep="5" skip-btn="skip">Skip</button>
									<button class="btn tab_regenerate_btn" regenerate="regenerateAttr">Regenerate
									</button>
									<button class="btn next_step_btn" data-nextstep="5" auto-generate='cont_next_step'>
										Continue
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- Intro-end -->

			<!-- Tagline-start -->
			<div class="main-common at_temp_sec at_temp_sec_5" id="step5" style="display:none;">
				<h3 class="top-heading">
					Generate Tagline: A short headline to go above or below the Introduction
				</h3>
				<div class="closest-obj">
					<div class="introo">
						<button class="btn next_step_btn" data-nextstep="4" back-btn="back">BACK</button>
						<div class="maincontent">
							<div class="genrate_input_sec" style="display:none;">
								<textarea id="w3review1" class="action_val_field"
								          placeholder="Add your tagline idea here" name="w3review" rows="5"
								          cols="40"></textarea>
								<button class="btn tab_generate_btn" data-action="tagline">Generate</button>
							</div>
							<label for=""><h2 class="label-last">Your previous selections</h2></label>
							<div class="title-idea checked-element">
								<ul class="ul1">
									<h2>Your checked value show here... </h2>
								</ul>
							</div>
							<div>
								<label for=""><h2 class="label-last">Select below if you would like to add a
										tagline</h2></label>
								<div class="title_class">
									<div class="title-idea">
										<ul class="ul1">
											Please wait, your results will show here...
										</ul>
									</div>
								</div>
							</div>
							<div>
								<ul class="ul2">
									<li> Above Intro <input class="checkbox above_below" name="above_below_tagline"
									                        value="above" type="radio"></li>
									<li> Below Intro <input class="checkbox above_below" name="above_below_tagline"
									                        value="below" type="radio" checked="checked"></li>
								</ul>
							</div>

							<div class="skip_cont_div">
								<button class="btn next_step_btn tagline_skip_btn" data-nextstep="6" skip-btn="skip">
									Skip
								</button>
								<button class="btn tab_regenerate_btn" regenerate="regenerateAttr">Regenerate</button>
								<button class="btn next_step_btn tagline_btn" data-nextstep="6"
								        auto-generate='cont_next_step'>Continue
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- Tagline-end -->

			<!-- Article-start -->
			<div class="main-common at_temp_sec at_temp_sec_6" id="step6" style="display:none;">
				<h3 class="top-heading">
					Generate Main Body
				</h3>
				<div class="closest-obj">
					<div>
						<button class="btn next_step_btn" data-nextstep="5" back-btn="back">BACK</button>
					</div>
					<label for=""><h2 class="label-last">Your previous selections</h2></label>
					<div class="title-idea checked-element">

					</div>
					<div class="maincontent">
						<style>
							.editorjs .ql-editor ul > li::before {
								content: '';
								width: 0px;
							}
						</style>
						<div class="genrate_input_sec" style="display:none;">
							<textarea id="w3review1" class="action_val_field" placeholder="Add your tagline idea here"
							          name="w3review" rows="5" cols="40"></textarea>
							<button class="btn tab_generate_btn" data-action="article">Generate</button>
						</div>
						<label for=""><h2 class="label-last">Here is the main body of your article</h2></label>
						<div class="editorjs">
							<div class="title-idea checked-element">
								<div class="title_class intro-test">
									<div class="ul1">
										Your result will show here...
									</div>
								</div>
							</div>
						</div>
						<div class="skip_cont_div" data-action="article">
							<button class="btn tab_regenerate_btn" regenerate="regenerateAttr">Regenerate</button>
							<button class="btn next_step_btn" data-nextstep="7" auto-generate='cont_next_step'>
								Continue
							</button>
						</div>

					</div>

				</div>
			</div>
			<!-- Article-end -->

			<!-- Conclusion-start -->
			<div class="main-common at_temp_sec at_temp_sec_7" id="step7" style="display:none;">
				<h3 class="top-heading">
					Conclusion
				</h3>
				<div class="closest-obj">
					<div class="maincontent">
						<div class="conclusion">
							<button class="btn next_step_btn" data-nextstep="6" back-btn="back">BACK</button>

							<button class="btn tab_generate_btn" data-action="conclusion" style="display:none;">Generate
								Conclusion
							</button>
						</div>
						<label for=""><h2 class="label-last">Your previous selections</h2></label>
						<div class="title-idea checked-element">
							<ul class="ul1">
								<h2>Your checked value show here... </h2>
							</ul>
						</div>
						<label for=""><h2 class="label-last">Select below if you would like to add a conclusion</h2>
						</label>
						<div class="title_class intro-test">
							<div class="title-idea">
								<ul class="ul1">
									Please wait, your results will show here...
								</ul>
							</div>
						</div>
						<div>

						</div>
						<div class="skip_cont_div">
							<button class="btn next_step_btn" data-nextstep="8" id="conclusionSkip" skip-btn="skip">
								Skip
							</button>
							<button class="btn tab_regenerate_btn" regenerate="regenerateAttr">Regenerate</button>
							<button class="btn next_step_btn" id="conclusionCont" data-nextstep="8"
							        auto-generate='cont_next_step'>Continue
							</button>
						</div>
					</div>
				</div>
			</div>
			<!-- Conclusion-end -->

			<!-- Question-start -->
			<div class="main-common at_temp_sec at_temp_sec_8" id="step8" style="display:none;">
				<h3 class="top-heading">
					Generate Related Question and Answer
				</h3>
				<div class="closest-obj">
					<div class="maincontent">
						<div>
							<button class="btn next_step_btn" data-nextstep="7" back-btn="back">BACK</button>
							<button class="btn tab_generate_btn" data-action="qna" style="display:none;">Generate Q&amp;As</button>
						</div>
						<label for=""><h2 class="label-last">Your previous selections</h2></label>
						<div class="title-idea checked-element">
							<ul class="ul1">
								<h2>Your checked value show here... </h2>
							</ul>
						</div>
						<label for=""><h2 class="label-last">Select the below Q&amp;A's if you would like to include
								them</h2></label>
						<div class="title_class intro-test">
							<div class="title-idea">
								<ul class="ul1">
									Please wait, your results will show here...
								</ul>
							</div>
						</div>
						<div>
							<ul class="ul2">
								<li> Above the conclusion <input class="checkbox above_below_conclusion"
								                                 name="above_below_conclusion" value="above"
								                                 type="radio"></li>
								<li> Below the conclusion <input class="checkbox above_below_conclusion"
								                                 name="above_below_conclusion" value="below"
								                                 type="radio" checked="checked"></li>
							</ul>
						</div>
						<div class="skip_cont_div">
							<button class="btn next_step_btn" data-nextstep="9" skip-btn="skip">Skip</button>
							<button class="btn tab_regenerate_btn" regenerate="regenerateAttr">Regenerate</button>
							<button class="btn next_step_btn" data-nextstep="9" auto-generate='cont_next_step'>
								Continue
							</button>
						</div>
					</div>
				</div>
			</div>
			<!-- Question-end -->

			<!-- Meta-Data-start -->
			<div class="main-common at_temp_sec at_temp_sec_9" id="step9" style="display:none;">
				<h3 class="top-heading">
					Generate SEO Meta Data
				</h3>
				<div class="closest-obj">
					<div class="maincontent">
						<div>
							<button class="btn next_step_btn" data-nextstep="8" id="metadataBack" back-btn="back">BACK
							</button>
							<button class="btn tab_generate_btn" data-action="seo-meta-data" style="display:none;">
								Generate Meta Data
							</button>
						</div>
						<label for=""><h2 class="label-last">Your previous selections</h2></label>
						<div class="title-idea checked-element">
							<ul class="ul1">
								<h2>Your checked value show here... </h2>
							</ul>
						</div>
						<label for=""><h2 class="label-last">Finally, select below if you would like to add Meta
								Data</h2></label>
						<div class="title_class intro-test">
							<div class="title-idea">
								<ul class="ul1">
									Please wait, your results will show here...
								</ul>
							</div>
						</div>
						<div class="skip_cont_div">
							<button class="btn next_step_btn review_skip_btn" data-nextstep="10" skip-btn="skip">Skip
							</button>
							<button class="btn  tab_regenerate_btn" regenerate="regenerateAttr">Regenerate</button>
							<button class="btn next_step_btn meta-data-btn" data-nextstep="10"
							        auto-generate='cont_next_step'>Continue
							</button>
						</div>
					</div>
				</div>
			</div>
			<!-- Meta-Data-end -->

			<!-- Review & Publish Article-start -->
			<div class="main-common at_temp_sec at_temp_sec_10" id="step10" style="display:none;">
				<h3 class="top-heading">
					REVIEW &amp; PUBLISH ARTICLE
				</h3>
				<div class="closest-obj">
					<div class="maincontent">
						<button class="btn next_step_btn" data-nextstep="9" back-btn="back">BACK</button>
						<button class="btn tab_generate_btn" data-action="review" style="display:none;">Generate Meta
							Data
						</button>
						<label for=""><h2 class="label-last">Your Article</h2></label>

						<style>
							.editorjs2 .ql-editor ul > li::before {
								content: '';
								width: 0px;
							}
						</style>

						<div class="editorjs2 ">
							<div class="title-idea checked-element">
								<div class="title_class intro-test">
									<div class="ul1">

										Your result will show here...
									</div>
								</div>
							</div>
						</div>
						<div class="skip_cont_div">
							<input type="hidden" id="attachment_id" name="attachment_id" value="" />
							<button class="btn next_step_btn" data-nextstep="11" auto-generate='cont_next_step'>Evaluate
								My article
							</button>
							<button class="btn save_post_tab">Save as post(Draft)</button>
							<button class="btn save_as_shortcode">Save as shortcode</button>
						</div>


					</div>
				</div>
			</div>
			<!-- Review & Public Article-end -->
			<div class="main-common at_temp_sec at_temp_sec_11" id="step11" style="display:none;">
				<h3 class="top-heading">
					Evaluate &amp; Enhance
				</h3>
				<div class="closest-obj">
					<div>
						<button class="btn next_step_btn" data-nextstep="10" back-btn="back"> BACK</button>
					</div>
					<div class="maincontent">
						<button class="btn tab_generate_btn" data-action="evaluate" style="display:none;">Generate Q&amp;As</button>

						<div class="title_class intro-test">

						</div>
						<div>
							<?php
							$current_page = admin_url( "admin.php?page=saved_shortcodes" );
							?>
							<a href="<?php echo esc_url( $current_page ); ?>">
								<button class="btn">Exit</button>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="article-right">
			<div class="option-prompt">
				<h1>OPTIONS &amp; PROMPTS</h1>
				<span style="padding:10px;">
				<P><strong>New for AI-Scribe v2.0: </strong> Give your content a boost with our <strong>"Humanize"</strong> and <strong>"Humanize with Personality"</strong> options within <a href="./admin.php?page=ai_scribe_settings" style="text-decoration: underline;">global settings</a>. These go beyond standard writing style and tone to give your content a new lease of life. Given them a try and let us know what you think.</P>
			</span>

			</div>
			<div class="main-common2">
				<div class="lang-additional-heading">
					<div class="container div-main">
						<h1>
							<div class="bullet"></div>
							<span class="plus-sign languages_style_tab" style="cursor: pointer;">+</span> Language
						</h1>
						<div class=" languages_style" style="display:none;">
							<P style="margin-left: 30px;">Any changes made in this section will be saved in your global settings.</P>
							<form class="form1" action="#">

								<div class="formgroup languages">
									<label for="lang"> Languages:- </label> <select name="languages" id="lang">
										<option value="" disabled>Select Language
										</option> <?php foreach ( $langArr as $langkey => $langvalue ) {
											?>
											<option <?php echo selected( $langvalue, $lang ); ?>
												value="<?php echo esc_attr( $langvalue ); ?>"><?php echo esc_attr( $langvalue ); ?>
											</option> <?php
										} ?>
									</select>

								</div>
								
								<div class="formgroup1">
									<label for="writingStyle"> Writing Style:- </label> <select name="writingStyle"
									                                                            id="writingStyle">
										<option value="" disabled>Select Writing Style
										</option> <?php foreach ( $writingStyleArr as $writingStyleArrkey => $writingStyleArrvalue ) {
											?>
											<option <?php echo selected($writingStyleArrvalue, $writing_style); ?>
												value="<?php echo esc_attr($writingStyleArrvalue); ?>"><?php echo esc_attr($writingStyleArrvalue); ?></option> <?php
										} ?>
									</select>

								</div>
								<div class="formgroup1">
									<label for="writingTone"> Writing Tone:- </label> <select name="writingTone"
									                                                          id="writingTone">
										<option value="" disabled>Select Writing Tone
										</option> <?php foreach ( $writingToneArr as $writingToneArrkey => $writingToneArrvalue ) {
											?>
											<option <?php echo selected($writingToneArrvalue, $writing_tone); ?>
												value="<?php echo esc_attr($writingToneArrvalue); ?>"><?php echo esc_attr($writingToneArrvalue); ?></option> <?php
										} ?>
									</select>

								</div>
								
							</form>
						</div>
					</div>
					<!--  -->
					<div class="container heading-div1">
						<h1>
							<div class="bullet "></div>
							<span class="plus-sign heading_tab" style="cursor: pointer;">+</span>
							Headings
						</h1>
						<div class=" hide_headings_tab" style="display:none;">
							<P style="margin-left: 30px;">Visit the <a href="./admin.php?page=ai_scribe_settings" style="text-decoration: underline;">global settings</a> to change these settings permanently.</P>
							<form class="form1 headingcont" action="#">
								<div class="formgroup1 heading_key_avoid no_heading">
									<label for="lang"> Number of Headings:- </label> <input class="heading-no"
									                                                        type="text"
									                                                        name="num_heading" id=""
									                                                        value="<?php echo esc_attr($number_of_heading); ?>"/>
								</div>
								<div class="formgroup1">
									<label for="lang"> Heading Tags:- </label> <select name="headingtag"
									                                                   id="heading-tag">
										<option value="" disabled>Select Heading Tags
										</option> <?php foreach ( $subhedingArr as $subhedingArrkey => $subhedingArrvalue ) {
											?>
											<option <?php echo selected($subhedingArrvalue, $Heading_tag); ?>
												value="<?php echo esc_attr($subhedingArrvalue); ?>"><?php echo esc_attr($subhedingArrvalue); ?></option> <?php
										} ?>
									</select>
								</div>
							</form>
						</div>
					</div>
					<!--  -->
					<div class="container" style="display:none;">
						<h1>
							<div class="bullet "></div>
							<span class="plus-sign additional_content_tab" style="cursor: pointer;">+</span> Additional
							Content
						</h1>
						<div class="hide_addition_content">
							<form class="form1" action="#">
								<div class="formgroup1 heading_key_avoid"><label for="lang"> Keywords to Avoid<br><span>(Comma Separated):-</span>
									</label> <input class="heading-no" type="text" name="keyword_avoid"
								                    id="keywordavoid" placeholder="Please add here..."
								                    value="<?php echo esc_attr($cslist); ?>"/>
								</div> <?php foreach ( $checkArr as $checkArrkey => $checkArrvalue ) {
									$checked = "";
									if ( ! empty( $getcheckArray ) && in_array( $checkArrkey, $getcheckArray ) ) {
										$checked = "checked";
									}
									?>
									<div class="formgroup1 checked-settings"><label
											for="lang"> <?php echo esc_attr($checkArrvalue); ?>   </label> <input
											class="chackbox2" type="checkbox" name="checkArr[]" <?php echo esc_attr($checked); ?>
											value="<?php echo esc_attr($checkArrkey); ?>"/></div> <?php
								} ?>
							</form>
						</div>
					</div>
					<!--  -->
				</div>
				<div class="prompts-sec">
					<div class="prompts-box">
						<br/>
						<div class="prompts">
							<span class="text-prompts">Prompts</span>
							<span class="button-right "><input type="button" class="show_prompt"
							                                   value="Hide"></input></span>
							<div class="prompts-options">
								<div class="options">
									<textarea id="prompt_text" value=""></textarea>
								</div>
							</div>
						</div>
						<button class="btn tab_regenerate_btn regen" prompt-regenerate='currentpage'>Update settings or
							prompt
						</button>
					</div>
				</div>
				<!--ss -->

				<!--  -->
			</div>
		</div>
	</div>
	<!-- Add your link block here just before the .create_template_cont_sec closes -->
<div style="margin-top:20px; padding:10px; text-align:center;">
    For paid enhancements or new feature requests, please view our 
    <a href="https://opace.agency/services/web-design/wordpress-development" target="_blank">WordPress services</a> and 
    <a href="https://opace.agency/get-in-touch" target="_blank">get in touch</a> with your requirements.
</div>

</div>