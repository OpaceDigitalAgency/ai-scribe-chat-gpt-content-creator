<?php
/*
 * Contributors: 		OPACE LTD
 * Plugin Name: 		AI Scribe: ChatGPT SEO Content Creator, Article Writer & SEO Assistant (GPT 3 & 4)
 * Description: 		AI Scribe: Free Plugin, ChatGPT, AI, SEO, Content Creator, Keyword Research, Title Suggestions, Editable Prompts, OpenAI, GPT, Auto Article Writer, SEO Analysis, Article Evaluation, GPT-3, GPT-4, 16K, 32K. Compatible with Yoast SEO, Rank Math, AIOSEO & SEOPress. Free Plugin - No hidden costs or paid add-ons.  
 * Plugin URI: 			https://www.opace.co.uk
 * Text Domain: 		ai-scribe-gpt-article-builder
 * Tags: 				AI, ChatGPT, SEO, Content Creator, Article Writer, Content Generator, Content Writer, Blog Writer, OpenAI, GPT-3, GPT-4, GPT-3.5, GPT-3.5-Turbo, ChatGPT-3, ChatGPT-4, 16K, 32K, Text Creator, Blog Creator, Blog Builder, Content Marketing, Free, GPT, OpenAI, Keyword Research
 * Author URI: 			https://www.opace.co.uk
 * Author: 				Opace Web Design
 * Requires at least: 	4.4 or higher
 * Tested up to: 		6.3.1
 * Version: 			1.2.2
 * License:          	GPL-3.0
 * License URI:      	http://www.gnu.org/licenses/gpl-3.0.txt
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The plugin requires using the external services of the company OpenAI to work, please make sure to read the Terms of Use <https://openai.com/policies/terms-of-use>
 * and the 	Privacy Policy <https://openai.com/policies/privacy-policy>.
 */

define( 'AI_SCRIBE_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_SCRIBE_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_SCRIBE_VER', '1.2.3' );

class AI_Scribe {
	public function __construct() {
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );



		add_action( 'admin_enqueue_scripts', function ( $page ) {
			if ( 'ai-scribe_page_ai_scribe_saved_shortcodes' == $page ) {
				wp_enqueue_style( 'ai-scribe-bootstrap', AI_SCRIBE_URL . 'assets/css/bootstrap5.2.css', [], AI_SCRIBE_VER );
				wp_enqueue_script( 'ai-scribe-show_template', AI_SCRIBE_URL . 'assets/js/show_template.js', [ 'jquery' ], AI_SCRIBE_VER, true );
				wp_add_inline_style( 'ai-scribe-bootstrap', '
					th, td {
						text-align: center !important;
					}
					h4 {
						margin-top: 3px;
						margin-bottom: 3px;
					}
				' );

				$script_data = [
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				];
				array_map( 'json_encode', $script_data );
				wp_localize_script( 'ai-scribe-show_template', 'ai_scribe', $script_data );
			}
			if ( 'ai-scribe_page_ai_scribe_generate_article' == $page ) {
				wp_enqueue_style( 'ai-scribe-quill', AI_SCRIBE_URL . 'assets/css/quill.css', [], AI_SCRIBE_VER );
				wp_enqueue_style( 'ai-scribe-font_awesome', AI_SCRIBE_URL . 'assets/css/font_awesome.css', [], AI_SCRIBE_VER );
				wp_enqueue_style( 'ai-scribe-create_template', AI_SCRIBE_URL . 'assets/css/article_builder.css', [
					'ai-scribe-quill',
					'ai-scribe-font_awesome'
				], AI_SCRIBE_VER );

				wp_add_inline_style( 'ai-scribe-create_template', '
					.article-main.overlay {
						position: relative;
					}
				
					.article-main.overlay::before {
						content: \'\';
						width: 100%;
						height: 100%;
						position: absolute;
						background-image: url(\'' . AI_SCRIBE_URL . 'assets/img/spinener.gif\');
						background-repeat: no-repeat;
						background-position: center center;
						left: 0;
						right: 0;
						opacity: 0.5;
						z-index: 99999;
					}

					@media screen and (max-width: 768px) {
							.overlay {
								display: block;
						}
					}

				' );

				wp_enqueue_script( 'ai-scribe-quill', AI_SCRIBE_URL . 'assets/js/quill.min.js', [ 'jquery' ], AI_SCRIBE_VER, true );
				wp_enqueue_script( 'ai-scribe-create_template', AI_SCRIBE_URL . 'assets/js/create_template.js', [
					'jquery',
					'ai-scribe-quill'
				], AI_SCRIBE_VER, true );
				$getarr                = get_option( 'ab_gpt3_content_settings' );
				$getcheckArray         = isset( $getarr['check_Arr'] ) ? $getarr['check_Arr'] : '';
				$aiengine              = get_option( 'ab_gpt3_ai_engine_settings' );
				$apikey                = isset( $aiengine['api_key'] ) ? $aiengine['api_key'] : '';
				$page                  = sanitize_text_field( $_GET['page'] );
				$current_page          = admin_url( "admin.php?page=" . $page );
				$current_page_settings = '&action=settings';
				$promptsData           = get_option( 'ab_prompts_content' );

				$script_data = [
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
					'apiKey'      => $apikey,
					'settingsUrl' => admin_url( "admin.php?page=ai_scribe_settings" ),
					'checkArr'    => $getcheckArray,
					'promptsData' => $promptsData,
					'aiEngine'    => $aiengine,
				];
				array_map( 'json_encode', $script_data );
				wp_localize_script( 'ai-scribe-create_template', 'ai_scribe', $script_data );
			}
			if ( 'ai-scribe_page_ai_scribe_settings' == $page ) {
				wp_enqueue_style( 'ai-scribe-settings', AI_SCRIBE_URL . 'assets/css/settings.css', [], AI_SCRIBE_VER );
				wp_enqueue_script( 'ai-scribe-settings', AI_SCRIBE_URL . 'assets/js/settings.js', [ 'jquery' ], AI_SCRIBE_VER, true );

				$script_data = [
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				];
				array_map( 'json_encode', $script_data );
				wp_localize_script( 'ai-scribe-settings', 'ai_scribe', $script_data );
			}

			if ( isset( $_GET['page'] ) && 'ai_scribe_help' === sanitize_text_field( $_GET['page'] ) ) {
				wp_enqueue_style( 'ai-scribe-create_template', AI_SCRIBE_URL . 'assets/css/article_builder.css', [], AI_SCRIBE_VER );
				wp_enqueue_style( 'ai-scribe-help-page', AI_SCRIBE_URL . 'assets/css/help.css', [ 'ai-scribe-create_template' ], AI_SCRIBE_VER );
				wp_add_inline_style( 'ai-scribe-help-page', '
					._9zm2 {
						background-image: url(\'' . AI_SCRIBE_URL . 'assets/2023/03/AI-Scribe-Logo-simplified.png\');
					}
				' );
			}

		} );
		add_action( 'admin_menu', [
			$this,
			'add_menu'
		] );
		add_action( 'wp_ajax_al_scribe_remove_short_code_content', [
			$this,
			'remove_short_code_content',
		] );
		add_action( 'wp_ajax_al_scribe_content_data', [
			$this,
			'content_data'
		] );
		add_action( 'wp_ajax_al_scribe_engine_request_data', [
			$this,
			'engine_request_data',
		] );
		add_action( 'wp_ajax_al_scribe_send_post_page', [
			$this,
			'send_post_page',
		] );
		add_action( 'wp_ajax_al_scribe_suggest_content', [
			$this,
			'suggest_content',
		] );
		add_action( 'wp_ajax_al_scribe_send_shortcode_page', [
			$this,
			'send_shortcode_page',
		] );
		add_action( 'wp_ajax_get_article', [
			$this,
			'get_article'
		] );
		add_action( 'admin_post_ai_scribe_delete_data_confirm', [
			$this,
			'ai_scribe_delete_data_confirm'
		] );
		add_action( 'admin_notices', [
			$this,
			'ai_scribe_uninstall_notice'
		] );
		add_action( 'plugins_loaded', [
			$this,
			'load_textdomain'
		] );

		add_shortcode( 'article_builder_generate_data', [
			$this,
			'send_shortcode_page_data',
		] );

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_settings_link' ] );

		// Initialize autogenerateValue and actionInput with default values
    		$this->autogenerateValue = '';
    		$this->actionInput = '';
	}

	/*
	* Function: get_article
	* Description: Initializes the GET request to get the article data.
	*/
	public function get_article() {
		$args     = array(
			'timeout'     => 300, // Increase the timeout value to 300 seconds (5 minutes)
			'redirection' => 10,
			'httpversion' => '1.1',
			'headers'     => array(
				'cache-control' => 'no-cache'
			)
		);
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			echo esc_html( "Something went wrong: $error_message" );
		} else {
			//request was correct
		}
	}

	/*
	* Function: activate
	* Description: Creates the custom database table and sets default options when the plugin is activated.
	*/
	public function activate() {
		global $wpdb, $table_prefix;
		$wp_article = $table_prefix . "article_builder";
		if ( $wpdb->get_var( "show tables like '$wp_article'" ) != $wp_article ) {
			$q = "CREATE TABLE `$wp_article` (
                    `id` int(20) NOT NULL AUTO_INCREMENT,
                      `title` text DEFAULT NULL,
                      `heading` text DEFAULT NULL,
                      `keyword` text DEFAULT NULL,
                      `intro` text DEFAULT NULL,
                      `tagline` text DEFAULT NULL,
                      `article` text DEFAULT NULL,
                      `conclusion` longtext DEFAULT NULL,
                      `qna` longtext DEFAULT NULL,
                      `metadata` longtext DEFAULT NULL,
                        PRIMARY KEY  (`id`)
                    )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
			$wpdb->query( $q );
		}
		$this->set_default_options();
		add_option( 'ai_scribe_delete_data_on_uninstall', 'no' );
	}

	/*
	* Function: load_textdomain
	* Description: Loads the plugin's textdomain for translations.
	*/

	private function set_default_options() {
		$contentsetting = [
			'language'          => 'English',
			'writing_style'     => 'Business',
			'writing_tone'      => 'Professional',
			'number_of_heading' => '5',
			'Heading_tag'       => 'H2',
			'check_Arr'         => [
				'addQNA'           => 'addQNA',
				'addinsertHyper'   => 'addinsertHyper',
				'addinsertToc'     => 'addinsertToc',
				'addfurtheReading' => 'addfurtheReading',
				'addsubMatter'     => 'addsubMatter',
				'addimgCont'       => 'addimgCont',
				'addkeywordBold'   => 'addkeywordBold',
			],
			'cs_list'           => '',
		];
		$enginesetting  = [
			'model'            => 'gpt-3.5-turbo-16k',
			'temp'             => 0.5,
			'top_p'            => 0.5,
			'freq_pent'        => 0.2,
			'Presence_penalty' => 0.2,
			'n'                => 1,
		];
		$promptssetting = [
			'instructions_prompts'      =>
				'Your name is AI-Scribe. Always write naturally and use the UK English spellings rather than US, e.g. words like "optimize" that contain a "z" near the end should be spelt as "optimise", "optimising", "optimised", etc. Respond using plain language. Do not provide any labels like "Section..." or "Sub-Section...". Do not provide any explanations, notes, other labelling or analysis. Follow my prompts carefully. Add variety and randomness to the length and structure of sentences and paragraphs. Avoid robotic ridged text structures. Ensure text is naturally flowing and creative. Use examples or anecdotes to illustrate the principles or concepts. Use genuine statistics or evidence to support claims. Use transitions or connectors to link the ideas or paragraphs more coherently. Use synonyms or paraphrasing to avoid repetition or redundancy. Always exclude these words: Testament, As A Professional, Previously Mentioned, Buckle Up, Dance, Delve, Digital Era, Dive In, Embark, Enable, Emphasise, Embracing, Enigma, Ensure, Essential, Even If, Even Though, Folks, Foster, Furthermore, Game Changer, Given That, Importantly, In Contrast, In Order To, World Of, Digital Era, In Today’s, Indeed, Indelible, Essential To, Imperative, Important To, Worth Noting, Journey, Labyrinth, Landscape, Look No Further, Moreover, Navigating, Nestled, Nonetheless, Notably, Other Hand, Overall, Pesky, Promptly, Realm, Remember That, Remnant, Revolutionize, Shed Light, Symphony, Dive Into, Tapestry, Testament, That Being Said, Crucial, Considerations, Exhaustive, Thus, Put It Simply, To Summarize, Unleashing, Ultimately, Underscore, Vibrant, Vital. ',
			'title_prompts'      =>
				'Provide 5 unique article titles for my blog based on "[Idea]". They need to be unique and catchy. Write them in the [Language] language using a [Style] writing style and a [Tone] writing tone.',
			'Keywords_prompts'   =>
				'For the title "[Title]", provide a list of 5 relevant keywords or phrases each on a new line. These need to be popular searches in Google and capable of driving traffic to the article. Capitsalise each word.',
			'outline_prompts'    =>
				'Write an article outline titled [Title]. Create [No. Headings] sections and no sub-sections for the body of my article. Don\'t include an introduction or conclusion. This needs to be a simple list of section headings. Do not add any commentary, notes or additional information such as section labels, "Section 1", "Section 2", etc. Please include the following SEO keywords following SEO keywords [Selected Keywords] where appropriate in the headings. Write the outline in the [Language] language using a [Style] writing style and a [Tone] writing tone.',
			'intro_prompts'      =>
				'Generate an introduction for my article as a single paragraph and within a single <span> tag. Do not include a separate heading. Base the introduction on the "[Title]" title and the [Selected Keywords]. Write the introduction in the [Language] language using a [Style] writing style and a [Tone] writing tone.',
			'tagline_prompts'    =>
				'Generate a tagline for my article. Base the tagline on the "[Title]" title and the [Selected Keywords]. Write the tagline in the [Language] language using a [Style] writing style and a [Tone] writing tone. Use persuasive power words.',
			'article_prompts'    =>
				'Write a HTML article to include an H1 tag for the "[Title]" main title. The following introduction should be at the top: "[Intro]". Add a tagline called "[The Tagline]" [above/below]. Then write the article and for each section, vary the word counts of each by at least 50%. This is my outline for you to write: [Heading]. Each section should provide a unique perspective on the topic and provide value over and above what\'s already available. Format each section heading as a [Heading Tag] tag. You must not include a conclusion heading or section. SEO optimise the article for the [Selected Keywords]. Don\'t include lists. Write the article in the [Language] language using a [Style] writing style and a [Tone] writing tone. Each section must be explored in detail and must include a minimum of 3 paragraphs. To achieve this, you must include all possible known features, benefits, arguments, analysis and whatever is needed to explore the topic to the best of your knowledge.',
			'conclusion_prompts' =>
				'Create a conclusion within a single html <span> tag and a maximum of one paragraph. Based this on the "[Title]" and optimise for the [Selected Keywords]. Write in the [Language] language using a [Style] writing style and a [Tone] writing tone. Include a call to action to express a sense of urgency. Within the paragraph, include a [Heading Tag] tag for the heading to contain the word "conclusion. Don\'t use <div> tags or <ul> tags.',
			'qa_prompts'         =>
				'Create [No. Headings] individual Questions and Answers, each in their own paragraph. Do not give each question a label, e.g. Question 1, Question2, etc. Based these on the "[Title]" title and the [Selected Keywords]. Write in the [Language] language using a [Style] writing style and a [Tone] writing tone. Within each paragraph, include a [Heading Tag] tag for the question and a P tag for the answer. Ensure they provide additional useful information to supplement the main "[Title]" article. Don\'t use lists or LI tags.',
			'meta_prompts'       =>
				'Create a single SEO friendly meta title and meta description. Based this on the "[Title]" article title and the [Selected Keywords]. Create the meta data in the [Language] language using a [Style] writing style and a [Tone] writing tone.  Follow SEO best practices and make the meta data catchy to attract clicks.',
			'review_prompts'     =>
				'Please revise the above article and HTML code so that it has [No. Headings] headings using the [Heading Tag] HTML tag. Revise the text in the [Language] language. Revise with a [Style]  style and a [Tone] writing tone.',
			'evaluate_prompts'   =>
				'Create a HTML table giving a strict/evaluation of each question below based on everything above. Give the HTML table 4 columns: [STATUS], [QUESTION], [EVALUATION], [RATIONALE]. For [EVALUATION], give a PASS, FAIL or IMPROVE response. Add a CSS class name to each row with the corresponding response value. For the [STATUS] column, don\'t add anything. For [RATIONALE], explain your reasoning. Order the rows according to  [EVALUATION]. All answers must be factual. Then giving examples like phrases or topics add these within curly brackets. Do not add the column labels within square brackets in your response. The questions are:
Is the length of the article over 500 words and an adequate length compared to similar articles?
Is the article optimised for certain keywords or phrases? What are these?
Is the article well-written and easy to read?
Does the article have any spelling or grammar issues?
Does the article provide an original, interesting and engaging perspective on the topic?',
		];
		update_option( 'ab_gpt3_content_settings', $contentsetting );
		update_option( 'ab_gpt3_ai_engine_settings', $enginesetting );
		update_option( 'ab_prompts_content', $promptssetting );

	}

	/*
	* Function: ai_scribe_uninstall_notice
	* Description: Displays a notice in the admin area asking the user if they want to delete all data when uninstalling the plugin.
	*/

	public function load_textdomain() {
		load_plugin_textdomain( 'article_builder', false, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/*
	* Function: ai_scribe_delete_data_confirm
	* Description: Updates the option to delete or not delete data on plugin uninstall based on user's choice.
	*/

	public function ai_scribe_uninstall_notice() {
		$screen = get_current_screen();
		if ( $screen->id == 'plugins' ) {
			$delete_data = get_option( 'ai_scribe_delete_data_on_uninstall' );
			if ( $delete_data === 'no' ) {
				?>
				<div class="notice notice-warning is-dismissible">
					<p>
						<strong>AI Scribe:</strong> Do you want to delete all data when uninstalling the plugin?
						<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=ai_scribe_delete_data_confirm&choice=yes' ) ); ?>">Yes</a>
						|
						<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=ai_scribe_delete_data_confirm&choice=no' ) ); ?>">No</a>
					</p>
				</div>
				<?php
			}
		}
	}

	/*
	* Function: uninstall
	* Description: Removes custom database tables and options created by the plugin when it's uninstalled, if the user has chosen to delete all data.
	*/

	public function ai_scribe_delete_data_confirm() {
		$choice = sanitize_text_field( $_GET['choice'] );
		if ( $choice ) {
			update_option( 'ai_scribe_delete_data_on_uninstall', $choice );
		}
		wp_redirect( admin_url( 'plugins.php' ) );
		exit;
	}

	/*
	* Function: deactivate
	* Description: Placeholder function for when the plugin is deactivated.
	*/

	public function uninstall() {
		$delete_data = get_option( 'ai_scribe_delete_data_on_uninstall' );
		if ( $delete_data === 'yes' ) {
			// Remove custom database tables
			global $wpdb;
			$table_name = $wpdb->prefix . "article_builder";
			$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

			// Remove options created by the plugin
			delete_option( 'ab_gpt3_ai_engine_settings' );
			delete_option( 'ab_gpt3_content_settings' );
		}
	}

	/*
	* Function: add_settings_link
	* Description: Adds the Settings and Help links to the plugin's action links on the plugins page.
	*/

	public function deactivate() {
	}

	/*
	* Function: set_default_options
	* Description: Sets the default options for the plugin's settings.
	*/
	public function add_settings_link( $links ) {
	    $settings_link = '<a href="' . admin_url( 'admin.php?page=ai_scribe_settings' ) . '">' . __( 'Settings', 'article_builder' ) . '</a>';
	    $help_link     = '<a href="' . admin_url( 'admin.php?page=ai_scribe_help' ) . '">' . __( 'Help', 'article_builder' ) . '</a>';
	    $review_link   = '<a href="' . esc_url( 'https://wordpress.org/support/plugin/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/reviews/#new-post' ) . 
	                    '" id="review-link" target="_blank">' . __( 'Leave a Review', 'article_builder' );
	                   // . ' <div class="rating"><span></span><span></span><span></span><span></span><span></span></div></a>';

	    array_unshift( $links, $settings_link, $help_link, $review_link );

	    return $links;
	}


	/*
	* Function: add_menu
	* Description: Adds the plugin's menu and submenu items to the WordPress admin area.
	*/

	public function add_menu() {
		$parent_slug = 'ai_scribe_help';
		$capability  = 'manage_options';
		$menu_title  = 'AI-Scribe';
		$menu_slug   = $parent_slug;
		$function    = [ $this, 'main_page' ];
		$icon_url    = '';
		$position    = 15;
		add_menu_page(
			$menu_title,
			$menu_title,
			$capability,
			$menu_slug,
			$function,
			$icon_url,
			$position
		);

		$generate_article_slug = 'ai_scribe_generate_article';
		add_submenu_page(
			$menu_slug,
			'Generate Article',
			'Generate Article',
			$capability,
			$generate_article_slug,
			[ $this, 'create_template' ]
		);

		$saved_shortcodes_slug = 'ai_scribe_saved_shortcodes';
		add_submenu_page(
			$menu_slug,
			'Saved Shortcodes',
			'Saved Shortcodes',
			$capability,
			$saved_shortcodes_slug,
			[ $this, 'all_templates' ]
		);

		$settings_slug = 'ai_scribe_settings';
		add_submenu_page(
			$menu_slug,
			'Settings',
			'Settings',
			$capability,
			$settings_slug,
			[ $this, 'settings_page' ]
		);

		$help_slug = $parent_slug . '_help';
		add_submenu_page(
			$menu_slug,
			'Help',
			'Help',
			$capability,
			$menu_slug,
			$function
		);
	}


	/*
	* Function: main_page
	* Description: Includes the main page template for the plugin.
	*/
	public function main_page() {
		include_once 'common/help.php';
	}

	/*
	* Function: create_template
	* Description: Includes the "Generate Article" or "Saved Shortcodes" template based on the current page.
	*/

	public function all_templates() {
		$page   = sanitize_text_field( $_GET["page"] ) ?? '';
		$action = sanitize_text_field( $_GET["action"] ) ?? '';
		if ( $page && $action == 'exit' ) {
			$this->create_template();
		} elseif ( $page && $action == 'settings' ) {
			include_once 'common/settings.php';
		} else {
			include_once 'templates/show_template.php';
		}
	}

	/*
	* Function: all_templates
	* Description: Includes the "Saved Shortcodes" or "Settings" template based on the current page and action.
	*/

	public function create_template() {
		$page = sanitize_text_field( $_GET["page"] ) ?? '';
		if ( $page == 'saved_shortcodes' ) {
			include_once 'templates/show_template.php';
		} else {
			include_once 'templates/create_template.php';
		}
	}

	/*
	* Function: settings_page
	* Description: Includes the "Settings" template for the plugin.
	*/

	public function settings_page() {
		include_once 'common/settings.php';
	}

	/*
	* Function: remove_short_code_content
	* Description: Removes a saved shortcode from the custom database table.
	*/
	public function remove_short_code_content() {
		global $wpdb;
		$id          = sanitize_key( $_POST['id'] );
		$table_name  = $wpdb->prefix . 'article_builder';
		$edit_record = $wpdb->query(
			" DELETE  FROM $table_name WHERE `id`='$id'"
		);
		header( 'Location: admin.php?page=at_templates' );
	}

	/*
	* Function: content_data
	* Description: Updates the content settings in the options table.
	*/
	public function content_data() {
		$lang           = sanitize_text_field( $_POST['language'] ?? '' );
		$wrtStyle       = sanitize_text_field( $_POST['writing_style'] ?? '' );
		$wrtStone       = sanitize_text_field( $_POST['writing_tone'] ?? '' );
		$numHeading     = sanitize_text_field( $_POST['number_of_heading'] ?? '' );
		$headTag        = sanitize_text_field( $_POST['Heading_tag'] ?? '' );
		$modHead        = sanitize_text_field( $_POST['modify_heading'] ?? '' );
		$checkboxArr    = array_map( 'sanitize_text_field', $_POST['checkArr'] ?? [] );
		$promptsContent = array_map( 'sanitize_textarea_field', $_POST['prompts_content'] ?? [] );
		$cslist         = sanitize_text_field( $_POST['cs_list'] ?? '' );
		$frmArr         = [
			'language'          => $lang,
			'writing_style'     => $wrtStyle,
			'writing_tone'      => $wrtStone,
			'number_of_heading' => $numHeading,
			'Heading_tag'       => $headTag,
			'modify_heading'    => $modHead,
			'check_Arr'         => $checkboxArr,
			'cs_list'           => $cslist,
		];
		update_option( 'ab_gpt3_content_settings', $frmArr );
		update_option( 'ab_prompts_content', $promptsContent );
		$resArr = [
			'status' => 'success',
			'msg'    => 'Your settings have been updated successfully',
		];
		echo json_encode( $resArr );
		exit();
	}

	/*
	* Function: engine_request_data
	* Description: Updates the AI engine settings in the options table.
	*/
	public function engine_request_data() {
		$model            = sanitize_text_field( $_POST['model'] ?? '' );
		$temp             = sanitize_text_field( $_POST['temp'] ?? '' );
		$top_p            = sanitize_text_field( $_POST['top_p'] ?? '' );
		$freq_pent        = sanitize_text_field( $_POST['freq_pent'] ?? '' );
		$Presence_penalty = sanitize_text_field( $_POST['Presence_penalty'] ?? '' );
		$api_key          = sanitize_text_field( $_POST['api_key'] ?? '' );
		$frmagArr         = [
			'model'            => $model,
			'temp'             => $temp,
			'max_tokens'       => 2500,
			'top_p'            => $top_p,
			//'best_oi' => $best_oi,
			'freq_pent'        => $freq_pent,
			'Presence_penalty' => $Presence_penalty,
			'api_key'          => $api_key,
		];
		update_option( 'ab_gpt3_ai_engine_settings', $frmagArr );
		$resArr = [
			'status' => 'success',
			'msg'    => 'Your settings have been updated successfully',
		];
		echo json_encode( $resArr );
		exit();
	}

	/*
	* Function: send_post_page
	* Description: Inserts a new post with the generated article content and updates its Yoast SEO meta title and description.
	*/
	public function send_post_page() {
		ob_start();

		$headingData    = array_map( 'sanitize_text_field', $_POST['headingData'] ?? [] );
		$headingStr     = implode( " ", $headingData );
		$keywordData    = array_map( 'sanitize_text_field', $_POST['keywordData'] ?? [] );
		$keywordStr     = implode( " ", $keywordData );
		$introData      = array_map( 'sanitize_text_field', $_POST['introData'] ?? [] );
		$introStr       = implode( " ", $introData );
		$taglineData    = array_map( 'sanitize_text_field', $_POST['taglineData'] ?? [] );
		$taglineStr     = implode( " ", $taglineData );
		$conclusionData = array_map( 'sanitize_text_field', $_POST['conclusionData'] ?? [] );
		$conclusionStr  = implode( " ", $conclusionData );
		$qnaData        = array_map( 'sanitize_text_field', $_POST['qnaData'] ?? [] );
		$qnaStr         = implode( " ", $qnaData );
		$metaData       = array_map( 'sanitize_text_field', $_POST['metaData'] ?? [] );
		$metaDataStr    = implode( " ", $metaData );

		$titleData = sanitize_title( $_POST['titleData'] ?? '' );

		$articleVal   = wp_kses_post( $_POST['articleVal'] ?? '' );
		$articleValue = preg_replace( "/<h1>.*<\/h1>/", " ", $articleVal );
		$articleValue = preg_replace( "/<br>|\n|<br( ?)\/>/", "", $articleValue );

		$pattern = "/<h1>.*<\/h1>/";
		preg_match( $pattern, $articleVal, $matches );
		$articleValue = preg_replace( "/<br>|\n|<br( ?)\/>/", "", $articleValue );

		$my_post    = [
			'post_type'    => 'post',
			'post_title'   => strip_tags( $matches[0] ),
			'post_content' => $articleValue,
			'post_status'  => 'draft',
		];
		$insertPost = wp_insert_post( $my_post );
	    /* 06.07.23 - inclusion of other popular SEO plugins */
	    if ( $insertPost > 0 ) {
	    	$keywordStr = implode(", ", $keywordData);
	        // Check which SEO plugin is active and update meta data accordingly
	        if ( defined( 'WPSEO_FILE' ) ) {
	            // Yoast SEO is active
	            update_post_meta( $insertPost, '_yoast_wpseo_title', $metaData[0] );
	            update_post_meta( $insertPost, '_yoast_wpseo_metadesc', $metaData[1] );
	            update_post_meta( $insertPost, '_yoast_wpseo_focuskw', $keywordStr );
	        } elseif ( defined( 'AIOSEOP_VERSION' ) ) {
	            // All in One SEO Pack is active
	            update_post_meta( $insertPost, '_aioseop_title', $metaData[0] );
	            update_post_meta( $insertPost, '_aioseop_description', $metaData[1] );
	            // All in One SEO Pack does not have a specific field for focus keyword
	        } elseif ( defined( 'RANK_MATH_FILE' ) ) {
	            // Rank Math is active
	            update_post_meta( $insertPost, 'rank_math_title', $metaData[0] );
	            update_post_meta( $insertPost, 'rank_math_description', $metaData[1] );
	            update_post_meta( $insertPost, 'rank_math_focus_keyword', $keywordStr );
	        } elseif ( defined( 'SEOPRESS_VERSION' ) ) {
	            // SEOPress is active
	            update_post_meta( $insertPost, '_seopress_titles_title', $metaData[0] );
	            update_post_meta( $insertPost, '_seopress_titles_desc', $metaData[1] );
	            update_post_meta( $insertPost, '_seopress_analysis_target_kw', $keywordStr );
	        }
	    }

		return ob_get_clean();
		exit();
	}

	/*
	* Function: send_shortcode_page
	* Description: Saves the generated article content as a shortcode in the custom database table.
	*/
	public function send_shortcode_page() {
		ob_start();

		$headingData    = array_map( 'sanitize_text_field', $_POST['headingData'] ?? [] );
		$headingStr     = implode( " ", $headingData );
		$keywordData    = array_map( 'sanitize_text_field', $_POST['keywordData'] ?? [] );
		$keywordStr     = implode( " ", $keywordData );
		$introData      = array_map( 'sanitize_text_field', $_POST['introData'] ?? [] );
		$introStr       = implode( " ", $introData );
		$taglineData    = array_map( 'sanitize_text_field', $_POST['taglineData'] ?? [] );
		$taglineStr     = implode( " ", $taglineData );
		$conclusionData = array_map( 'sanitize_text_field', $_POST['conclusionData'] ?? [] );
		$conclusionStr  = implode( " ", $conclusionData );
		$qnaData        = array_map( 'sanitize_text_field', $_POST['qnaData'] ?? [] );
		$qnaStr         = implode( " ", $qnaData );
		$metaData       = array_map( 'sanitize_text_field', $_POST['metaData'] ?? [] );
		$metaDataStr    = implode( " ", $metaData );

		$titleData = sanitize_title( $_POST['titleData'] ?? '' );

		$articleVal   = wp_kses_post( $_POST['articleVal'] ?? '' );
		$articleValue = preg_replace( "/<h1>.*<\/h1>/", " ", $articleVal );
		$articleValue = preg_replace( "/<br>|\n|<br( ?)\/>/", "", $articleValue );

		$pattern = "/<h1>.*<\/h1>/";
		preg_match( $pattern, $articleVal, $matches );
		global $wpdb, $table_prefix;
		$wp_article = $table_prefix . "article_builder";
		$wpdb->insert( $wp_article, [
			'title'      => strip_tags( $matches[0] ),
			'heading'    => $headingStr,
			'keyword'    => $keywordStr,
			'intro'      => $introStr,
			'tagline'    => $taglineStr,
			'article'    => $articleValue,
			'conclusion' => $conclusionStr,
			'qna'        => $qnaStr,
			'metadata'   => $metaData,

		] );

		return ob_get_clean();
		exit();
	}

	/*
	* Function: send_shortcode_page_data
	* This function retrieves the data associated with the given template ID and returns 
	* the combined content of the title, article, conclusion, and QnA.
	*/
	public function send_shortcode_page_data( $attr ) {
		$content = '';
		$tempId  = $attr['template_id'] ?? "";
		global $wpdb, $table_prefix;
		$wp_article = $table_prefix . "article_builder";
		$getData    = $wpdb->get_results( " SELECT * FROM $wp_article WHERE `id`='$tempId'" );
		foreach ( $getData as $key => $value ) {
			$content .= '<h1>' . $value->title . '</h1>';
			$content .= $value->article;
			$content .= $value->conclusion;
			$content .= $value->qna;
		}

		return $content;
	}

	/*
	* Function: suggest_content
	* This function sends a request to the OpenAI API with the given input and settings, 
	* processes the response, and generates the output in the desired format based on the actionInput value.
	*/
	public function suggest_content() {
		//$autogenerateValue = '';
		$autogenerateValue = wp_kses_post( $_POST['autogenerateValue'] ?? '' );
		$actionInput       = sanitize_text_field( $_POST['actionInput'] ?? '' );

		$autogenerateValue = str_replace( '"', "'", $autogenerateValue );

		$getarr = get_option( 'ab_gpt3_ai_engine_settings' );

		$apikey           = $getarr['api_key'] ?? '';
		$model            = $getarr['model'] ?? '';
		$temp             = $getarr['temp'] ?? '';
		$top_p            = $getarr['top_p'] ?? '';
		$freq_pent        = $getarr['freq_pent'] ?? '';
		$Presence_penalty = $getarr['Presence_penalty'] ?? '';
		$max_tokens       = '';

		if (strpos($model, '16K') === false || strpos($model, '32K') === false || strpos($model, 'gpt-4') === false) {
		if ( $actionInput == 'evaluate' ) {
			$max_tokens = '1500';
		} elseif ( $actionInput == 'article' || $actionInput == 'review' ) {
			$max_tokens = '2000';
		} elseif ( $actionInput == 'qna' || $actionInput == 'heading' ) {
			$max_tokens = '750';
		} else {
			$max_tokens = '250';
		}
		}

		$max_tokens = intval( $max_tokens );
		$temp       = floatval( $temp );
		$top_p      = floatval( $top_p );

		if ( $actionInput == 'evaluate' ) {
			$presence_penalty = 0;
			$freq_pent        = 0;
		} else {
			$presence_penalty = floatval( $Presence_penalty );
			$freq_pent        = floatval( $freq_pent );
		}

		$settings    = get_option( 'ab_gpt3_content_settings' );
		$actualStyle = $settings['writing_style'] ?? '';
		$actualTone  = $settings['writing_tone'] ?? '';
		
		$promptSettings    = get_option( 'ab_prompts_content' );
		$instructions  = $promptSettings['instructions_prompts'] ?? '';


		if ( strpos($model, 'gpt-3.5') !== false ) {
			
			$messages = [
				[
					"role"    => "system",
					"content" => 
						$instructions .
						" The current year is " . date( 'Y' ) .
						" Write in a " . $actualTone . " writing tone.",
				],
				[
					"role"    => "user",
					"content" => $autogenerateValue,
				],
			];
		} elseif ( strpos($model, 'gpt-4') !== false ) {
			/* Removed word count instruction for GPT-4 */
			$messages = [
				[
					"role"    => "system",
					"content" => 
						$instructions .
						" The year is " . date( 'Y' ) .
						" Write in a " .
						$actualStyle .
						" writing style and a " .
						$actualTone .
						" writing tone.",
				],
				[
					"role"    => "user",
					"content" => $autogenerateValue,
				],
			];
		}

		// Set up the request array
		$send_arr = [
			"model"             => $model,
			"temperature"       => $temp,
			"top_p"             => $top_p,
			"frequency_penalty" => $freq_pent,
			"presence_penalty"  => $presence_penalty,
			"n"                 => 1,
		];

		$endpoint = '';
		if ( $model == 'text-davinci-003' || $model == 'text-davinci' ) {
			$endpoint                      = 'v1/completions';
			$send_arr['model']             = $model;
			$send_arr['prompt']            = $autogenerateValue;
			$send_arr['max_tokens']        = $max_tokens;
			$send_arr['temperature']       = $send_arr['temperature'];
			$send_arr['top_p']             = $send_arr['top_p'];
			$send_arr['presence_penalty']  = $send_arr['presence_penalty'];
			$send_arr['frequency_penalty'] = $send_arr['frequency_penalty'];
		} elseif ( strpos($model, 'gpt-3.5') !== false ) {
			$endpoint                = 'v1/chat/completions';
			$send_arr['model']       = $model;
			$send_arr['messages']    = $messages;
			$send_arr['temperature'] = $send_arr['temperature'] * 2;
			$send_arr['top_p']       = $send_arr['top_p'] * 2;
			$send_arr['max_tokens'] = $max_tokens;
			$send_arr['presence_penalty']  = $send_arr['presence_penalty'] / 2;
			$send_arr['frequency_penalty'] = $send_arr['frequency_penalty'] / 2;
			$send_arr['stop']              = "\n\n\n"; // using a longer stop sequence
		} elseif ( strpos($model, 'gpt-4') !== false ) {
			$endpoint                = 'v1/chat/completions';
			$send_arr['model']       = $model;
			$send_arr['messages']    = $messages;
			$send_arr['temperature'] = $send_arr['temperature'] * 1.5;
			//$send_arr['max_tokens'] = $max_tokens;
			$send_arr['presence_penalty']  = $send_arr['presence_penalty'] / 2;
			$send_arr['frequency_penalty'] = $send_arr['frequency_penalty'] / 2;
			$send_arr['stop']              = "\n\n\n"; // using a longer stop sequence
		} else {
			$endpoint                      = 'v1/completions';
			$send_arr['model']             = $model;
			$send_arr['prompt']            = $autogenerateValue;
			$send_arr['max_tokens']        = $max_tokens;
			$send_arr['temperature']       = $send_arr['temperature'];
			$send_arr['top_p']             = $send_arr['top_p'];
			$send_arr['presence_penalty']  = $send_arr['presence_penalty'];
			$send_arr['frequency_penalty'] = $send_arr['frequency_penalty'];
		}

		$json_str = json_encode( $send_arr );

		// Set up the request using the OpenAI API
		// Refer to Terms of Use <https://openai.com/policies/terms-of-use> and the  Privacy Policy <https://openai.com/policies/privacy-policy>

		$url = 'https://api.openai.com/' . $endpoint;

		$args = array(
			'timeout'     => 500,
			'redirection' => 10,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $apikey,
				'Content-Type'  => 'application/json'
			),
			'body'        => $json_str,
			'cookies'     => array()
		);

		$response = wp_remote_post( $url, $args );

		$message_str = '';
		if ( $messages ) {
			foreach ( $messages as $message ) {
				$message_str .=
					'Role: ' .
					$message["role"] .
					'<br/>' .
					'Content: ' .
					$message["content"] .
					'<br/>';
			}
		}
		$debug = '';

		/*foreach ($messages as $message) {
		    if ($message["role"] === "system") {
		        echo $message["content"] . '<br>';
		    }
		}*/


		$debug .= 

			'<br/>style ' .
			$wrtStyle .
			'<br/>tone ' .
			$wrtStone .
			'<br/>actionInput: ' .
			$actionInput .
			'<br/>max_tokens: ' .
			$max_tokens .
			'<br/>Prompt: ' .
			$send_arr["prompt"] .
			'<br/>MESSAGE: ' .
			$message_str .
			'<br/> $model: ' .
			$send_arr["model"] .
			'<br/> $model 2: ' .
			$model .
			'<br/> $apikey: ' .
			$apikey .
			'<br/> $top_p: ' .
			$send_arr["top_p"] .
			'<br/> $freq_pent: ' .
			$send_arr["frequency_penalty"] .
			'<br/> $presence_penalty: ' .
			$send_arr["presence_penalty"] .
			'<br/> $max_tokens: ' .
			$send_arr["max_tokens"] .
			'<br/> $temp: ' .
			$send_arr["temperature"] .
			'<br/> $n: ' .
			$send_arr["n"] .
			'<br/>';

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			echo "Something went wrong: $error_message";
		} else {
			// Process the response and generate the output
			// Decode the JSON response into an associative array
			$resArr = json_decode( wp_remote_retrieve_body( $response ) );

			$isError = $resArr->error ?? '';
			if ( ! empty( $isError ) ) {
				$errorMSG          = $isError->message ?? '';
				$resultArr['html'] = $errorMSG;
				$resultArr['type'] = "error";
			} else {
				// Check if the response is an array and combine the content
				if ( isset( $resArr->choices ) ) {
					$combinedContent = '';
					foreach ( $resArr->choices as $choice ) {
						if ( isset( $choice->message->content ) ) {
							$combinedContent .= $choice->message->content;
						}
					}
				}

				$titleHtml = '<div class="title-idea after_generate_data">';

				if (
					$actionInput == 'evaluate' ||
					$actionInput == 'article' ||
					$actionInput == 'review'
				) {
					$titleHtml .= '<div class="ul1" ><div class="eval-screen">';
				} else {
					$titleHtml .= '<ul class="ul1" >';
				}

				if ( $resArr != "" ) {
					$choicesArr      = $resArr->choices ?? '';
					$combinedContent = '';

					if ( ! empty( $choicesArr ) ) {
						foreach ( $choicesArr as $reskey => $resvalue ) {
							// Access the corresponding element in the $resArr->choices array
							$choice = $resArr->choices[ $reskey ];

							// Initialize the combinedContent variable
							$combinedContent = '';

							// Check if the choice message content exists and append it to the combinedContent variable
							if ( isset( $choice->message->content ) ) {
								$combinedContent .= $choice->message->content;
							}

							// Check if the resvalue text exists and append it to the combinedContent variable
							$textRes         = $resvalue->text ?? '';
							$combinedContent .= $textRes;

							// Now, $combinedContent contains both the choice message content and the resvalue text

							if ( $actionInput == 'keyword' ) {
								if (
									strpos($model, 'gpt-3.5') !== false 
								) {
									$combinedContent = str_replace(
										",",
										"\n",
										$combinedContent
									);
								}
								$combinedContent = explode(
									"\n",
									$combinedContent
								);
							} elseif ( $actionInput == 'heading' ) {
								$combinedContent = str_replace(
									"\n\n",
									"\n",
									$combinedContent
								);
								$combinedContent = explode(
									"\n\n",
									$combinedContent
								);
							} elseif ( $actionInput == 'conclusion' ) {
								$combinedContent = explode(
									"\n\n",
									$combinedContent
								);
							} elseif ( $actionInput == 'qna' ) {

								$combinedContent = explode(
									"\n\n",
									$combinedContent
								);
							} elseif ( $actionInput == 'seo-meta-data' ) {
								$combinedContent = explode(
									"\n\n",
									$combinedContent
								);
							} elseif ( $actionInput == 'evaluate' ) {
								$combinedContent = explode(
									"\n\n",
									$combinedContent
								);
							} else {
								$combinedContent = explode(
									"\n",
									$combinedContent
								);
							}
							$checkboxAdded = false;

							foreach ( $combinedContent as $textValue ) {
								if (
									$actionInput == 'heading' ||
									$actionInput == 'keyword'
								) {
									$textValue = str_replace(
										"\n",
										'<br/>',
										$textValue
									);

								}
								if ( $actionInput == 'heading' ) {
									$textValue = ltrim( $textValue, "<br/>" );
								} elseif (
									$actionInput == 'seo-meta-data'
								) {
									$textValue = str_replace(
										"\n\n",
										"<br/>",
										$textValue
									);
									$textValue = ltrim( $textValue, "<br/>" );
									$textValue = trim(
										str_replace(
											'Meta Title:',
											'',
											$textValue
										)
									);
									$textValue = trim(
										str_replace(
											'Meta Description:',
											'',
											$textValue
										)
									);
								} elseif ( $actionInput == 'article' ) {
									$textValue = str_replace(
										"·",
										"",
										$textValue
									);
								} elseif ( $actionInput == 'evaluate' ) {
									$textValue = str_replace(
										"\n",
										"<br/>",
										$textValue
									);
								}
								if ( $textValue != "" ) {
									$textValue = str_replace(
										'"',
										'',
										$textValue
									);
									if (
										$actionInput == 'qna' ||
										$actionInput == 'conclusion'
									) {
										$titleHtml .=
											' 
                                                    <div  class= "generate-api-qna generate_' .
											$actionInput .
											' copycontent" >
                                                    <input type="button" class="copy_button" style="width:auto; height:25px; padding:3px; margin-right:10px;" value="Copy" />
                                                    <div class="get_' .
											$actionInput .
											'"> ' .
											$textValue .
											'</div>
                                                    <input class="checkbox get_checked" id="check_' .
											$actionInput .
											'" name="get_checked" type="checkbox" value= "' .
											$textValue .
											'" />
                                                    </div>';
									} elseif (
										$actionInput == 'evaluate' ||
										$actionInput == 'article' ||
										$actionInput == 'review'
									) {
										$textValue = preg_replace_callback(
											'/(<!--|\s*<!--)|\s+/',
											function ( $matches ) {
												if ( isset( $matches[1] ) && $matches[1] !== '' ) {
													return $matches[1];
												} else {
													return ' ';
												}
											},
											$textValue
										);
										$titleHtml .= html_entity_decode(
											$textValue
										);
									} else {
										$titleHtml .=
											' 
                                                    <div  class= "generate-api-article generate_' .
											$actionInput .
											' copycontent" >
                                                    <input type="button" class="copy_button" style="width:auto; height:25px; padding:3px; margin-right:10px;" value="Copy" />
                                                    <p>' .
											preg_replace(
											//'/^\d+[\.\)\s-]+/m',
												'/\d+\. /',
												'',
												$textValue
											) .
											'</p>
                                                    <input class="checkbox get_checked" id="check_' .
											$actionInput .
											'" name="get_checked" type="checkbox" value= "' .
											preg_replace(
											//'/^\d+[\.\)\s-]+/m',
												'/\d+\. /',
												'',
												$textValue
											) .
											'" />
                                            </div>';
									}
								}
							}
						}
					}
				}

				if (
					$actionInput == 'evaluate' ||
					$actionInput == 'article' ||
					$actionInput == 'review'
				) {
					$titleHtml .= '</div></div></div>';
				} else {
					$titleHtml .= '</ul></div>';
				}
				//$titleHtml = $debug . $titleHtml;
				$resultArr['html']    = $titleHtml;
				$articleStr           = implode( " ", $combinedContent );
				$resultArr['article'] = $articleStr;
				$resultArr['type']    = "success";
			}
		}

		/* Error handling */


		$http_status_code = wp_remote_retrieve_response_code($response);
		$errorMessage = "";
		$response_body = wp_remote_retrieve_body($response);

		if ($http_status_code == 504) {
		    // Handle gateway timeout error
		    $resultArr['type'] = "error";
		    $errorMessage = "Unfortunately OpenAI is very slow today which has caused a gateway timeout error. Please try again later.";
		    $resultArr['message'] = $errorMessage;
		} else if ($http_status_code != 200) {
		    // Handle other HTTP errors
		    $resultArr['type'] = "error";

		    // Attempt to parse the response body as JSON
		    $decoded_response = json_decode($response_body, true);

		    // Check if the response body includes debug statements
		    $debug_start = strpos($response_body, 'console.log("Response Body:');
		    if ($debug_start !== false) {
		        // Remove debug statements from the response body
		        $response_body = substr($response_body, 0, $debug_start);
		    }

		    // Attempt to parse the modified response body as JSON
		    $modified_response = json_decode($response_body, true);

		    if ($modified_response && isset($modified_response['error']['message'])) {
		        // Use the error message from the modified response
		        $errorMessage = "An error occurred while fetching data. ";

		        $error_message = $modified_response['error']['message'];
		        if (strpos($error_message, "The model:") !== false) {
		            $errorMessage .= "Your OpenAI account doesn't give you access to the selected model. Please try selecting a different model from the settings page.";
		        } else {
		            $errorMessage .= "Error: " . $error_message;
		        }
		    } else {
		        // Use a default error message
		        $errorMessage = "An error occurred while fetching data. Please try again later.";
		    }

		    $resultArr['message'] = $errorMessage;
		}



		// return $resultArr;
		echo json_encode( $resultArr );
		exit();
		
	}
}

// Initialize the class
$my_plugin = new AI_Scribe();