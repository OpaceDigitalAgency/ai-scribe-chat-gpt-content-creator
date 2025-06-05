<?php
/*
 * Contributors: 		OPACE LTD
 * Plugin Name: 		AI Scribe - SEO AI Writer, Content Generator, Humanizer, Blog Writer, SEO Optimizer, DALLE-3, AI WordPress Plugin ChatGPT (GPT-4o 128K) 
 * Description: 		AI Scribe: Free Plugin, ChatGPT, AI, SEO, Content Creator & Writer (Blog Writer), Keyword Research, Title Suggestions, Editable Prompts, OpenAI, GPT, Auto Article Writer, SEO Analysis, Article Evaluation, DALLE-3 Image, GPT-4o (128K), and GPT-4o-mini (128K). Compatible with Yoast SEO, Rank Math, AIOSEO & SEOPress. DALLE-3 Image. Free Plugin - No hidden costs or paid add-ons.  
 * Plugin URI: 			https://www.opace.co.uk
 * Text Domain: 		ai-scribe-gpt-article-builder
 * Tags: 				AI, ChatGPT, SEO, Content Generator, AI Writer,Blog Writer, Content Creator, Content Writer, Blog Writer, OpenAI, GPT-4o, GPT-4o-mini, 128K, Text Creator, Blog Creator, Blog Builder, Article Writer, Content Marketing, Free, GPT, OpenAI, Keyword Research, Humanizer, Human Writer, Long Form Content
 * Author URI: 			https://opace.agency
 * Author: 				Opace Digital Agency
 * Requires at least: 	4.4 or higher
 * Tested up to: 		6.7.1
 * Version: 			2.5
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
define( 'AI_SCRIBE_VER', '2.5' );


class AI_Scribe {
    //private $nonce; 
    //private $autogenerateValue;
	//private $actionInput;
    public function __construct() {
        
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

		// Initialize autogenerateValue and actionInput with default values
    	$this->autogenerateValue = '';
    	$this->actionInput = '';

        //$this->nonce = ''; // Initialize the nonce

	    // Generate nonce at the appropriate hook
	    //add_action('init', [$this, 'initialize_nonce']);

    	// Register hooks
    	$this->register_hooks();

	}

	/*public function initialize_nonce() {
	    if (!isset($this->nonce)) { // Ensure it's set only once
	        $this->nonce = wp_create_nonce('ai_scribe_nonce');
	    }
	}*/

	public function enqueue_scripts($page) {
	    // Generate a nonce for the current request
	    $nonce = wp_create_nonce('ai_scribe_nonce');

	    // Handle "Saved Shortcodes" page
	    if ($page === 'ai-scribe_page_ai_scribe_saved_shortcodes') {
	        wp_enqueue_style('ai-scribe-bootstrap', plugins_url('assets/css/bootstrap5.2.css', __FILE__), [], AI_SCRIBE_VER);
	        wp_enqueue_script('ai-scribe-show_template', plugins_url('assets/js/show_template.js', __FILE__), ['jquery'], AI_SCRIBE_VER, true);
	        wp_localize_script('ai-scribe-show_template', 'ai_scribe', [
	            'ajaxUrl' => admin_url('admin-ajax.php'),
	            'nonce' => $nonce,
	        ]);
	    }

	    // Handle "Generate Article" page
	    if ($page === 'ai-scribe_page_ai_scribe_generate_article') {
	        wp_enqueue_style('ai-scribe-quill', plugins_url('assets/css/quill.css', __FILE__), [], AI_SCRIBE_VER);
	        wp_enqueue_style('ai-scribe-font_awesome', plugins_url('assets/css/font_awesome.css', __FILE__), [], AI_SCRIBE_VER);
	        wp_enqueue_style('ai-scribe-create_template', plugins_url('assets/css/article_builder.css', __FILE__), ['ai-scribe-quill', 'ai-scribe-font_awesome'], filemtime(AI_SCRIBE_DIR . 'assets/css/article_builder.css'));
	        wp_enqueue_script('ai-scribe-quill', plugins_url('assets/js/quill.min.js', __FILE__), ['jquery'], AI_SCRIBE_VER, true);
	        wp_enqueue_script('ai-scribe-create_template', plugins_url('assets/js/create_template.js', __FILE__), ['jquery', 'ai-scribe-quill'], filemtime(AI_SCRIBE_DIR . 'assets/js/create_template.js'), true);
	        wp_localize_script('ai-scribe-create_template', 'ai_scribe', [
	            'ajaxUrl' => admin_url('admin-ajax.php'),
	            'nonce' => $nonce,
	            'apiKey' => get_option('ab_gpt3_ai_engine_settings')['api_key'] ?? '',
	            'settingsUrl' => admin_url('admin.php?page=ai_scribe_settings'),
	            'checkArr' => get_option('ab_gpt3_content_settings')['check_Arr'] ?? '',
	            'promptsData' => get_option('ab_prompts_content'),
	            'aiEngine' => get_option('ab_gpt3_ai_engine_settings'),
	        ]);
	    }

	    // Handle "Settings" page
	    if ($page === 'ai-scribe_page_ai_scribe_settings') {
	        wp_enqueue_style('ai-scribe-settings', plugins_url('assets/css/settings.css', __FILE__), [], AI_SCRIBE_VER);
	        wp_enqueue_script('ai-scribe-settings', plugins_url('assets/js/settings.js', __FILE__), ['jquery'], AI_SCRIBE_VER, true);
	        wp_localize_script('ai-scribe-settings', 'ai_scribe', [
	            'ajaxUrl' => admin_url('admin-ajax.php'),
	            'nonce' => $nonce,
	        ]);
	    }

	    // Handle "Help" page
	    if (isset($_GET['page']) && sanitize_text_field($_GET['page']) === 'ai_scribe_help') {
	        wp_enqueue_style('ai-scribe-create_template', plugins_url('assets/css/article_builder.css', __FILE__), [], AI_SCRIBE_VER);
	        wp_enqueue_style('ai-scribe-help-page', plugins_url('assets/css/help.css', __FILE__), ['ai-scribe-create_template'], AI_SCRIBE_VER);
	    }
	}



	private function register_hooks() {
	    // Nonce handling
	    add_action('wp_ajax_refresh_nonce', [$this, 'refresh_nonce']);
	    add_action('wp_ajax_nopriv_refresh_nonce', [$this, 'refresh_nonce']);
	    add_filter('nonce_life', [$this, 'extend_nonce_life']);

	    // Enqueue scripts and styles
	    add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

	    // Admin menu
	    add_action('admin_menu', [$this, 'add_menu']);

	    // AJAX actions
	    add_action('wp_ajax_al_scribe_remove_short_code_content', [$this, 'remove_short_code_content']);
	    add_action('wp_ajax_al_scribe_content_data', [$this, 'content_data']);
	    add_action('wp_ajax_al_scribe_engine_request_data', [$this, 'engine_request_data']);
	    add_action('wp_ajax_al_scribe_send_post_page', [$this, 'send_post_page']);
	    add_action('wp_ajax_al_scribe_suggest_content', [$this, 'suggest_content']);
	    add_action('wp_ajax_al_scribe_send_shortcode_page', [$this, 'send_shortcode_page']);
	    add_action('wp_ajax_get_article', [$this, 'get_article']);
	    add_action('wp_ajax_generate_dalle_image', [$this, 'generate_dalle_image']);
	    add_action('wp_ajax_update_style_tone', [$this, 'update_style_tone']);

	    // Admin notices
	    add_action('admin_notices', [$this, 'ai_scribe_uninstall_notice']);

	    // Plugin actions and filters
	    add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
	    add_action('plugins_loaded', [$this, 'load_textdomain']);

	    // Shortcode
	    add_shortcode('article_builder_generate_data', [$this, 'send_shortcode_page_data']);
	}



	public function refresh_nonce() {
	    if (!is_user_logged_in()) {
	        wp_send_json_error(['msg' => 'Unauthorized request.']);
	        return;
	    }

	    wp_send_json_success(['nonce' => wp_create_nonce('ai_scribe_nonce')]);
	}

	public function extend_nonce_life() {
	    return 24 * HOUR_IN_SECONDS;
	}



	function update_style_tone() {
		 if (!isset($_POST['security']) || !check_ajax_referer('ai_scribe_nonce', 'security', false)) {
		    wp_send_json_error([
		        'msg' => 'Invalid request. Please refresh the page and try again.',
		        'nonce_expired' => true, // Flag to handle nonce expiration in JS
		    ]);
		    error_log('Failed nonce validation.');
   	 		error_log('Received nonce: ' . ($_POST['security'] ?? 'None'));
		    return;
		}

	    // Sanitize input
	    $writingStyle = sanitize_text_field($_POST['writing_style'] ?? '');
	    $writingTone = sanitize_text_field($_POST['writing_tone'] ?? '');
	    $language = sanitize_text_field($_POST['language'] ?? '');

	    // Get existing content settings
	    $contentSettings = get_option('ab_gpt3_content_settings');
	    
	    if (!$contentSettings) {
	        error_log(__FUNCTION__ . ': Content settings not found.');
	        wp_send_json_error('Content settings not found.');
	        return;
	    }
	    /*
	    // Update with new values
	    $contentSettings['writing_style'] = $writingStyle;
	    $contentSettings['writing_tone'] = $writingTone;
	    $contentSettings['language'] = $language;

	    // Save the updated content settings
	    $updated = update_option('ab_gpt3_content_settings', $contentSettings);
	    if ($updated === false) {
	        error_log(__FUNCTION__ . ': Failed to update content settings.');
	        wp_send_json_error('Failed to update content settings.');
	        return;
	    }

	    // Send success response
	    wp_send_json_success('Content settings updated.');
	    */

	    // Fetch the current settings to prevent overwriting

		$currentSettings = get_option('ab_gpt3_content_settings');
		if (!$currentSettings) {
		    wp_send_json_error('Content settings not found.');
		    return;
		}

		// Merge the new settings with existing ones
		$newSettings = wp_parse_args([
		    'writing_style' => $writingStyle,
		    'writing_tone' => $writingTone,
		    'language' => $language,
		], $currentSettings);

		// Update only if there are changes
		if ($newSettings !== $currentSettings) {
		    $updated = update_option('ab_gpt3_content_settings', $newSettings);
		    if ($updated === false) {
		        wp_send_json_error('Failed to update content settings.');
		        return;
		    }
		}

		wp_send_json_success('Content settings updated.');

	}




	/*
	* Function: get_article
	* Description: Initializes the GET request to get the article data.
	*/
	public function get_article() {
;
		// Check nonce for security
	    if (!check_ajax_referer('ai_scribe_nonce', 'security', false)) {
	        wp_send_json_error([
	            'msg' => 'Invalid request. Please refresh the page and try again.',
	            'nonce_expired' => true,
	        ]);
	        return;
	    }
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

	    // Check if the table exists
	    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $wp_article ) ) ) != $wp_article ) {
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
	            PRIMARY KEY (`id`)
	        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
	        $wpdb->query( $q );
	    }

	    // Call the method to set default options
	    $this->set_default_options();

	    // Add the delete data on uninstall option if not already set
	    if ( get_option( 'ai_scribe_delete_data_on_uninstall' ) === false ) {
	        add_option( 'ai_scribe_delete_data_on_uninstall', 'no' );
	    }
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
		if ( ! in_array( $choice, [ 'yes', 'no' ], true ) ) {
		    wp_die( 'Invalid choice' );
		}
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
	    // Check if the user has opted to delete data on uninstall
	    $delete_data = get_option('ai_scribe_delete_data_on_uninstall');
	    if ($delete_data !== 'yes') {
	        return; // Exit if data deletion is not allowed
	    }

	    global $wpdb;

	    // Remove the custom database table
	    $table_name = $wpdb->prefix . "article_builder";

	    $query_result = $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS `%s`", $table_name));
	    if ($query_result === false) {
	        error_log(__FUNCTION__ . ': Failed to drop the article_builder table: ' . $wpdb->last_error);
	    }

	    // Remove options created by the plugin
	    $options = [
	        'ab_gpt3_ai_engine_settings',
	        'ab_gpt3_content_settings',
	        'ai_scribe_languages',
	        'ai_scribe_delete_data_on_uninstall'
	    ];

	    foreach ($options as $option) {
	        if (!delete_option($option)) {
	            error_log(__FUNCTION__ . ': Failed to delete option: ' . $option);
	        }
	    }

	    // Add a log entry or notice for successful uninstallation
	    error_log(__FUNCTION__ . ': AI Scribe plugin data has been successfully removed.');
	}


	/*
	* Function: add_settings_link
	* Description: Adds the Settings and Help links to the plugin's action links on the plugins page.
	*/

	public function deactivate() {
	}

	/*
	* Function: content_data
	* Description: Updates the content settings in the options table.
	*/
	public function content_data() {

		// If you only want admins or similarly privileged users to do this:
	    if ( ! current_user_can('manage_options') ) {
	        wp_send_json_error([ 'msg' => 'Unauthorized action.' ]);
	        return;
	    }

	    // Verify nonce
	    if (!isset($_POST['security']) || !check_ajax_referer('ai_scribe_nonce', 'security', false)) {
	        wp_send_json_error( [ 'msg' => 'Invalid request. Nonce verification failed.' ] );
	        return;
	    }

	    // Validate and sanitize inputs
	    $new_language = isset( $_POST['custom_language'] ) ? sanitize_text_field( $_POST['custom_language'] ) : '';
	    $lang = isset( $_POST['language'] ) ? sanitize_text_field( $_POST['language'] ) : '';
	    $wrtStyle = isset( $_POST['writing_style'] ) ? sanitize_text_field( $_POST['writing_style'] ) : '';
	    $wrtStone = isset( $_POST['writing_tone'] ) ? sanitize_text_field( $_POST['writing_tone'] ) : '';
	    $numHeading = isset( $_POST['number_of_heading'] ) ? sanitize_text_field( $_POST['number_of_heading'] ) : '';
	    $headTag = isset( $_POST['Heading_tag'] ) ? sanitize_text_field( $_POST['Heading_tag'] ) : '';
	    $modHead = isset( $_POST['modify_heading'] ) ? sanitize_text_field( $_POST['modify_heading'] ) : '';
	    $checkboxArr = isset( $_POST['checkArr'] ) ? array_map( 'sanitize_text_field', (array) $_POST['checkArr'] ) : [];
	    $promptsContentInput = isset( $_POST['prompts_content'] ) ? array_map( 'sanitize_textarea_field', (array) $_POST['prompts_content'] ) : [];
	    $cslist = isset( $_POST['cs_list'] ) ? sanitize_text_field( $_POST['cs_list'] ) : '';

	    // Handle mode selection
	    $mode = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : '';
	    if ( empty( $mode ) ) {
	        $existingSettings = get_option( 'ab_gpt3_content_settings', [] );
	        $mode = isset( $existingSettings['mode'] ) ? $existingSettings['mode'] : 'standard';
	    }

	    // Handle custom language input
	    if ( ! empty( $new_language ) ) {
	        $languages = get_option( 'ai_scribe_languages', [] );
	        if ( ! is_array( $languages ) ) {
	            $languages = [];
	        }

	        if ( ! in_array( $new_language, $languages, true ) ) {
	            $languages[] = $new_language;
	            update_option( 'ai_scribe_languages', $languages );
	        }

	        $lang = $new_language; // Use the new language as the selected language
	    }

	    // Build the content settings array
	    $frmArr = [
	        'language'          => $lang,
	        'writing_style'     => $wrtStyle,
	        'writing_tone'      => $wrtStone,
	        'number_of_heading' => $numHeading,
	        'Heading_tag'       => $headTag,
	        'modify_heading'    => $modHead,
	        'check_Arr'         => $checkboxArr,
	        'cs_list'           => $cslist,
	        'mode'              => $mode,
	    ];

	    // Update content settings in the database
	    $updatedSettings = update_option( 'ab_gpt3_content_settings', $frmArr );
	    if ( ! $updatedSettings ) {
	        wp_send_json_error( [ 'msg' => 'Failed to update content settings.' ] );
	        return;
	    }

	    // Handle prompts content
	    $existingPromptsContent = get_option( 'ab_prompts_content', [] );

	    // Save user's custom instructions
	    $customInstructions = isset( $promptsContentInput['instructions_prompts'] ) ? sanitize_textarea_field( $promptsContentInput['instructions_prompts'] ) : '';
	    unset( $promptsContentInput['instructions_prompts'] );

	    // Determine instructions based on the mode
	    $promptsContentInput['user_instructions'] = ( $mode === 'standard' ) ? $customInstructions : 'Additional instructions for non-standard modes.';

	    // Merge existing and new prompts content
	    $promptsContent = array_merge( $existingPromptsContent, $promptsContentInput );

	    // Update prompts content in the database
	    $updatedPrompts = update_option( 'ab_prompts_content', $promptsContent );
	    if ( ! $updatedPrompts ) {
	        wp_send_json_error( [ 'msg' => 'Failed to update prompts content.' ] );
	        return;
	    }

	    // Success response
	    wp_send_json_success( [ 'msg' => 'Your settings have been updated successfully.' ] );
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
			'model'            => 'gpt-4o-mini',
			'temp'             => 0.5,
			'top_p'            => 0.5,
			'freq_pent'        => 0.2,
			'Presence_penalty' => 0.2,
			'n'                => 1,
		];
		$promptssetting = [
			'instructions_prompts'      =>
				'These are your most basic writing instructions: Your name is AI-Scribe and you are a talented copywriter and SEO specialist. Always write naturally and use the UK English spellings rather than US if writing in the English language, e.g. do not use words like "optimize" that contain a "z" near the end, as should be spelt as "optimise", "optimising", "optimised", etc. Focus on producing helpful SEO content that google will appreciate. Respond using plain language. Do not provide any labels like "Section..." or "Sub-Section...". Do not provide any explanations, notes, other labelling or analysis. Follow my prompts carefully. Do not use these these words or phrases that contain them: Ever Changing, Ever Evolving, Testament, As A Professional, Previously Mentioned, Buckle Up, Dance, Delve, Digital Era, Dive In, Embark, Enable, Emphasise, Embracing, Enigma, Ensure, Essential, Even If, Even Though, Folks, Foster, Furthermore, Game Changer, Given That, Importantly, In Contrast, In Order To, World Of, Digital Era, In Today’s, Indeed, Indelible, Essential To, Imperative, Important To, Worth Noting, Journey, Labyrinth, Landscape, Look No Further, Moreover, Navigating, Nestled, Nonetheless, Notably, Other Hand, Overall, Pesky, Promptly, Realm, Remember That, Remnant, Revolutionize, Shed Light, Symphony, Dive Into, Tapestry, Testament, That Being Said, Crucial, Considerations, Exhaustive, Thus, Put It Simply, To Summarize, Unlock, Unleash, Unleashing, Ultimately, Underscore, Vibrant, Vital. ',
			'title_prompts'      =>
				'Provide 5 unique article titles for my blog based on "[Idea]". They need to be unique and cover a different angle to topics that are already likely to be covered. ',
			'Keywords_prompts'   =>
				'For the title "[Title]", provide a list of 5 relevant keywords or phrases each on a new line. These need to be popular searches (keywords or short phrases) that people are likely to enter into Google and capable of driving traffic to the article. Capitsalise each word.',
			'outline_prompts'    =>
				'Write an article outline titled [Title]. Create [No. Headings] sections and no sub-sections for the body of my article. Don\'t include an introduction or conclusion. This needs to be a simple list of section headings. Do not add any commentary, notes or additional information such as section labels, "Section 1", "Section 2", etc. Please include the following SEO keywords following SEO keywords [Selected Keywords] where appropriate in the headings. ',
			'intro_prompts'      =>
				'Generate an introduction for my article as a single paragraph. Do not include a separate heading. Base the introduction on the "[Title]" title and the [Selected Keywords]. Write the introduction in the [Language] language using a [Style] writing style and a [Tone] writing tone.',
			'tagline_prompts'    =>
				'Generate a tagline for my article. Base the tagline on the "[Title]" title and the [Selected Keywords]. ',
			'article_prompts'    =>
				'Write my article using only HTML tags directly in the output without enclosing the content in any ``` or code block syntax. Include a H1 tag for the [Title] main title. Add a tagline called "[The Tagline]" [above/below]. Include the introduction: [Intro]. Then write each section using my outline, making sure each section heading is formatted as a [Heading Tag] tag: [Heading].  Strictly randomise the <p> count under each [Heading Tag] tag. Some sections should have just 1 <p> tag, while others may have up to 4 <p> tags. No two consecutive [Heading Tag] tag or section should have the same number of <p> tags. Vary the word count of each paragraph by at least 50%. Each section should provide a unique perspective on the topic and provide value over and above what\'s already available. You must not include a conclusion heading or section. SEO optimise the article for the [Selected Keywords]. Don\'t include lists. Each section must be explored in detail. To achieve this, you must include all possible known features, benefits, arguments, analysis and whatever is needed to explore the topic to the best of your knowledge. Do not add any additional notes, markup or code before the H1 or after the last paragraph. Do not add any additional notes, markup or code before the H1 or after the last paragraph.',
			'conclusion_prompts' =>
				'Create a conclusion within a single html <span> tag and a maximum of one paragraph. Based this on the "[Title]" and optimise for the [Selected Keywords]. Include a call to action to express a sense of urgency. Within the paragraph, include a [Heading Tag] tag for the heading to contain the word "conclusion. Don\'t use <div> tags or <ul> tags.',
			'qa_prompts'         =>
				'Create [No. Headings] individual Questions and Answers, each in their own individual span tag. Do not give each question a label, e.g. Question 1, Question2, etc. Based these on the "[Title]" title and the [Selected Keywords]. Within each span tag, include a [Heading Tag] tag for the question heading and a P tag for the answer. Ensure they provide additional useful information to supplement the main "[Title]" article. Don\'t use lists or LI tags.',
			'meta_prompts'       =>
				'Create a single SEO friendly meta title and meta description. Based this on the "[Title]" article title and the [Selected Keywords]. Create the meta data in the [Language] language.  Follow SEO best practices and make the meta data catchy to attract clicks. Do not add any additional markup such as ***',
			'review_prompts'     =>
				'Please revise the above article and HTML code so that it has [No. Headings] headings using the [Heading Tag] HTML tag. Revise the text in the [Language] language. Revise with a [Style]  style and a [Tone] writing tone.',
			'evaluate_prompts'   =>
				'Create a HTML table giving a strict/evaluation of each question below based on everything above. Give the HTML table 4 columns: [STATUS], [QUESTION], [EVALUATION], [RATIONALE]. For [EVALUATION], give a PASS, FAIL or IMPROVE response. Add a CSS class name to each row and cell with the corresponding response value. For the [STATUS] column, don\'t add anything. For [RATIONALE], explain your reasoning. Order the rows according to  [EVALUATION]. All answers must be factual. Then giving examples like phrases or topics add these within curly brackets. Do not add the column labels within square brackets in your response. The questions are:
Is the length of the article over 500 words and an adequate length compared to similar articles?
Is the articlview dee optimised for certain keywords or phrases? What are these?
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
		include_once plugin_dir_path(__FILE__) . 'common/help.php';
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
			include_once plugin_dir_path(__FILE__) . 'common/settings.php';
		} else {
			include_once plugin_dir_path(__FILE__) . 'templates/show_template.php';
		}
	}

	/*
	* Function: all_templates
	* Description: Includes the "Saved Shortcodes" or "Settings" template based on the current page and action.
	*/

	public function create_template() {
		$page = sanitize_text_field( $_GET["page"] ) ?? '';
		if ( $page == 'saved_shortcodes' ) {
			include_once plugin_dir_path(__FILE__) . 'templates/show_template.php';
		} else {
			include_once plugin_dir_path(__FILE__) . 'templates/create_template.php';
		}
	}

	/*
	* Function: settings_page
	* Description: Includes the "Settings" template for the plugin.
	*/

	public function settings_page() {
		include_once plugin_dir_path(__FILE__) . 'common/settings.php';
	}

	/**
	 * Function: remove_short_code_content
	 * Description: Removes a saved shortcode from the custom database table, returning JSON.
	 */
	public function remove_short_code_content() {
	    // Validate the nonce before proceeding
	    if (!isset($_POST['security']) || !check_ajax_referer('ai_scribe_nonce', 'security', false)) {
	        wp_send_json_error([
	            'msg' => 'Invalid request. Please refresh the page and try again.',
	            'nonce_expired' => true,
	        ]);
	        error_log('Failed nonce validation.');
	        error_log('Received nonce: ' . ($_POST['security'] ?? 'None'));
	        wp_die(); // Stop execution
	    }

	    global $wpdb;

	    // Sanitise and validate the ID
	    $id = absint($_POST['id']);
	    if (!$id) {
	        wp_send_json_error(['msg' => 'Invalid ID']);
	        wp_die();
	    }

	    // Prepare and execute the deletion query
	    $table_name = $wpdb->prefix . 'article_builder';
	    $result = $wpdb->query(
	        $wpdb->prepare(
	            "DELETE FROM $table_name WHERE id = %d",
	            $id
	        )
	    );

	    // If the query fails, return an error response
	    if ($result === false) {
	        wp_send_json_error(['msg' => 'Failed to delete the record']);
	        wp_die();
	    }

	    // If successful, send a JSON success response (no redirect here)
	    wp_send_json_success(['msg' => 'Record deleted successfully.']);
	}

		
	/*
	* Function: engine_request_data
	* Description: Updates the AI engine settings in the options table.
	*/
	public function engine_request_data() {
	
	if (!isset($_POST['security']) || !check_ajax_referer('ai_scribe_nonce', 'security', false)) {
	    wp_send_json_error([
	        'msg' => 'Invalid request. Please refresh the page and try again.',
	        'nonce_expired' => true, // Consistent flag for JS
	    ]);
	    error_log('Failed nonce validation.');
   	 	error_log('Received nonce: ' . ($_POST['security'] ?? 'None'));
	    return;
	}
    // Check user capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'msg' => 'Unauthorized action' ] );
        exit;
    }

    // Verify nonce
    if (!isset($_POST['security']) || !check_ajax_referer('ai_scribe_nonce', 'security', false)) {
        wp_send_json_error( [ 'msg' => 'Invalid request' ] );
        exit;
    }

    $model            = sanitize_text_field( $_POST['model'] ?? '' );
		$temp             = sanitize_text_field( $_POST['temp'] ?? '' );
		$top_p            = sanitize_text_field( $_POST['top_p'] ?? '' );
		$freq_pent        = sanitize_text_field( $_POST['freq_pent'] ?? '' );
		$Presence_penalty = sanitize_text_field( $_POST['Presence_penalty'] ?? '' );
		$api_key          = sanitize_text_field( $_POST['api_key'] ?? '' );
		$frmagArr         = [
			'model'            => $model,
			'temp'             => $temp,
			//'max_tokens'       => 2500,
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
		if (!isset($_POST['security']) || !check_ajax_referer('ai_scribe_nonce', 'security', false)) {
		    wp_send_json_error([
		        'msg' => 'Invalid request. Please refresh the page and try again.',
		        'nonce_expired' => true, // Consistent flag for JS
		    ]);
		    error_log('Failed nonce validation.');
   	 		error_log('Received nonce: ' . ($_POST['security'] ?? 'None'));
		    return;
		}
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

		


		$articleVal   = wp_kses_post( $_POST['articleVal'] ?? '' );
		$pattern      = "/<h1>(.*?)<\/h1>/";
		preg_match( $pattern, $articleVal, $matches );
		$post_title   = !empty($matches[1]) ? strip_tags($matches[1]) : 'Untitled Post';

		// Truncate the post slug to no more than 6 words
		$title_words   = explode(' ', $post_title);
		$truncated_title_words = array_slice($title_words, 0, 6); // Limit to 6 words
		$truncated_slug = sanitize_title( implode(' ', $truncated_title_words) ); // Create a slug-friendly version

		$my_post    = [
			'post_type'    => 'post',
			'post_title'   => $post_title,
			'post_content' => $articleValue,
			'post_status'  => 'draft',
			'post_name'    => $truncated_slug // This sets the post slug (URL)
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

	    // **Handle Featured Image**
        /*if ( isset($_POST['attachment_id']) && !empty($_POST['attachment_id']) ) {
            $attachment_id = intval($_POST['attachment_id']);
            // Check if the attachment exists
            if ( get_post_status( $attachment_id ) ) {
                set_post_thumbnail( $insertPost, $attachment_id );
            } else {
                // Handle invalid attachment ID
                error_log("Attachment ID {$attachment_id} does not exist.");
            }
        }*/

		return ob_get_clean();
		exit();
	}

	/*
	* Function: send_shortcode_page
	* Description: Saves the generated article content as a shortcode in the custom database table.
	*/
	public function send_shortcode_page() {
	    if (!isset($_POST['security']) || !check_ajax_referer('ai_scribe_nonce', 'security', false)) {
		    wp_send_json_error([
		        'msg' => 'Invalid request. Please refresh the page and try again.',
		        'nonce_expired' => true, // Consistent flag for JS
		    ]);
		    error_log('Failed nonce validation.');
   	 		error_log('Received nonce: ' . ($_POST['security'] ?? 'None'));
		    return;
		}

	    ob_start();

	    // Sanitize input data
	    $headingData    = array_map('sanitize_text_field', $_POST['headingData'] ?? []);
	    $headingStr     = implode(" ", $headingData);
	    $keywordData    = array_map('sanitize_text_field', $_POST['keywordData'] ?? []);
	    $keywordStr     = implode(" ", $keywordData);
	    $introData      = array_map('sanitize_text_field', $_POST['introData'] ?? []);
	    $introStr       = implode(" ", $introData);
	    $taglineData    = array_map('sanitize_text_field', $_POST['taglineData'] ?? []);
	    $taglineStr     = implode(" ", $taglineData);
	    $conclusionData = array_map('sanitize_text_field', $_POST['conclusionData'] ?? []);
	    $conclusionStr  = implode(" ", $conclusionData);
	    $qnaData        = array_map('sanitize_text_field', $_POST['qnaData'] ?? []);
	    $qnaStr         = implode(" ", $qnaData);
	    $metaData       = array_map('sanitize_text_field', $_POST['metaData'] ?? []);
	    $metaDataStr    = maybe_serialize($metaData); // Serialize metadata
	    $titleData      = sanitize_title($_POST['titleData'] ?? '');
	    $articleVal     = wp_kses_post($_POST['articleVal'] ?? '');
	    $articleValue   = preg_replace("/<br>|\n|<br( ?)>/", "", $articleVal);

	    // Extract the title from <h1> tag if present
	    preg_match("/<h1>(.*?)<\/h1>/", $articleVal, $matches);
	    $title = isset($matches[1]) ? strip_tags($matches[1]) : '';

	    global $wpdb;

	    // Define the table name
	    $table_name = $wpdb->prefix . "article_builder";

	    // Insert the data into the table
	    $result = $wpdb->insert(
	        $table_name,
	        [
	            'title'      => $title,
	            'heading'    => $headingStr,
	            'keyword'    => $keywordStr,
	            'intro'      => $introStr,
	            'tagline'    => $taglineStr,
	            'article'    => $articleValue,
	            'conclusion' => $conclusionStr,
	            'qna'        => $qnaStr,
	            'metadata'   => $metaDataStr,
	        ],
	        [
	            '%s', // title
	            '%s', // heading
	            '%s', // keyword
	            '%s', // intro
	            '%s', // tagline
	            '%s', // article
	            '%s', // conclusion
	            '%s', // qna
	            '%s', // metadata (serialized string)
	        ]
	    );

	    // Check for errors   
		if ($result === false) {
		    error_log(__FUNCTION__ . ': Failed to insert data into article_builder: ' . $wpdb->last_error);
		    wp_send_json_error(['msg' => 'An error occurred while saving your data.']);
		    return;
		}
	    // Return success response
	    wp_send_json_success(['msg' => 'Data saved successfully.']);
	}


	/*
	* Function: send_shortcode_page_data
	* This function retrieves the data associated with the given template ID and returns 
	* the combined content of the title, article, conclusion, and QnA.
	*/
	public function send_shortcode_page_data( $attr ) {
	    $content = '';

	    // Validate and sanitize the template_id
	    if ( empty( $attr['template_id'] ) || ! is_numeric( $attr['template_id'] ) ) {
	        return '<p>' . esc_html__( 'Invalid template ID.', 'ai-scribe-gpt-article-builder' ) . '</p>';
	    }

	    // Ensure template_id is an integer
	    $tempId = absint( $attr['template_id'] );

	    global $wpdb, $table_prefix;
	    $wp_article = $wpdb->prefix . 'article_builder'; // Avoids direct use of table_prefix

	    // Prepare and execute query safely
	    $getData = $wpdb->get_results(
	        $wpdb->prepare( "SELECT title, article, conclusion, qna FROM $wp_article WHERE id = %d", $tempId )
	    );

	    // Verify if results exist before proceeding
	    if ( empty( $getData ) ) {
	        return '<p>' . esc_html__( 'No data found for the provided template ID.', 'ai-scribe-gpt-article-builder' ) . '</p>';
	    }

	    // Securely render retrieved content
	    foreach ( $getData as $value ) {
	        $content .= '<h1>' . esc_html( $value->title ) . '</h1>';
	        $content .= '<div class="article-content">' . wp_kses_post( $value->article ) . '</div>';
	        $content .= '<div class="conclusion">' . wp_kses_post( $value->conclusion ) . '</div>';
	        $content .= '<div class="qna">' . wp_kses_post( $value->qna ) . '</div>';
	    }

	    return $content;
	}





	/*
    * Function: generate_dalle_image
    * This function creates DALL-E 2 images.
    */
    public function generate_dalle_image($image_prompt) {
        // Sanitize the input prompt for generating an image
        $image_prompt = sanitize_text_field($image_prompt);
        
        // Check if the prompt is provided
        if (empty($image_prompt)) {
            return new WP_Error('no_prompt', 'Image prompt is required.');
        }

        // Get the OpenAI API key
        $aiengine = get_option('ab_gpt3_ai_engine_settings');
        $api_key = sanitize_text_field($aiengine['api_key'] ?? '');

        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'API key is missing.');
        }

        // Prepare the request payload for DALL·E 2
        $request_body = json_encode([
            'model' => 'dall-e-3',
            'prompt' => $image_prompt,
            'n' => 1, // Number of images
            'size' => '1024x1024' // Image size (can be adjusted)
        ]);

        // Make the API request to DALL·E 2 endpoint
        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'timeout' => 300,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => $request_body,
        ]);

        // Handle the response
        if (is_wp_error($response)) {
            return $response; // Return the error directly
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            return new WP_Error('api_error', $data['error']['message']);
        }

        // Extract the image URL
        $image_url = $data['data'][0]['url'] ?? '';

        if (empty($image_url)) {
            return new WP_Error('no_image', 'Failed to generate image.');
        }

        // Return the image URL
        return ['image_url' => $image_url];
    }


    /*
    * Helper function to generate an image from the content using DALL-E 2
    */
    private function generate_dalle_image_from_content($image_prompt) {
        // Call the DALL·E image generation function directly
        return $this->generate_dalle_image($image_prompt);
    }


	/*
	* Function: suggest_content
	* This function sends a request to the OpenAI API with the given input and settings, 
	* processes the response, and generates the output in the desired format based on the actionInput value.
	*/
	public function suggest_content() {
		// Verify nonce
	    if (!isset($_POST['security']) || !check_ajax_referer('ai_scribe_nonce', 'security', false)) {
	        wp_send_json_error([
	            'msg' => 'Security nonce is missing or invalid. Please refresh the page.',
	            'nonce_expired' => true,
	        ]);
	        return;
	    }
		
		//$autogenerateValue = '';
		$autogenerateValue = wp_kses_post( $_POST['autogenerateValue'] ?? '' );
		$actionInput       = sanitize_text_field( $_POST['actionInput'] ?? '' );
		$autogenerateValue = str_replace( '"', "'", $autogenerateValue );
		$getarr 		  = get_option( 'ab_gpt3_ai_engine_settings' );
		$apikey           = sanitize_text_field( $getarr['api_key'] ?? '' );
	    $model            = sanitize_text_field( $getarr['model'] ?? 'gpt-4o-mini' );
	    $temp             = floatval( $getarr['temp'] ?? 0.5 );
	    $top_p            = floatval( $getarr['top_p'] ?? 0.5 );
	    $freq_pent        = floatval( $getarr['freq_pent'] ?? 0.2 );
	    $Presence_penalty = floatval( $getarr['Presence_penalty'] ?? 0.2 );
		
		// v1.3 - updated as only using gpt-4o and above now
		//$max_tokens       = '16384';

		//$max_tokens = intval( $max_tokens );
		$temp       = floatval( $temp );
		$top_p      = floatval( $top_p );

		if ( $actionInput == 'evaluate' ) {
			$presence_penalty = 0;
			$freq_pent        = 0;
		} else {
			$presence_penalty = floatval( $Presence_penalty );
			$freq_pent        = floatval( $freq_pent );
		}

		// Retrieve settings and prompts
	    $settings        = get_option( 'ab_gpt3_content_settings' );
	    $promptSettings  = get_option( 'ab_prompts_content' );
	    $language        = sanitize_text_field( $settings['language'] ?? 'English' );
	    $actualStyle     = sanitize_text_field( $settings['writing_style'] ?? 'Business' );
	    $actualTone      = sanitize_text_field( $settings['writing_tone'] ?? 'Professional' );
	    $mode            = sanitize_text_field( $settings['mode'] ?? 'standard' );
	    $customInstructions = wp_kses_post( $promptSettings['instructions_prompts'] ?? '' );


	    // Define mode-specific instructions
	    $behuman = <<<EOT
	These are your 'Humaizer' writing instructions: You are not a standard GPT or ChatGPT agent so what you write and how you write (e.g. style, format, etc.) needs to be very different to how you would normally write when a user messages you on ChatGPT. This is your key differentiator! Your job is to write content so that it’s 100% natural content with all of the same errors, flaws, inconsistencies, and randomness that a human writer would produce without the aid of a computer or tools to assist with writing.
	Address the Reader Directly: Use "you," avoid "one," and use "I" or "we" when representing an organisation.
	Always vary the number of paragraphs you include in each section.
	Embrace Imperfections: Include natural mistakes and common grammatical errors to reflect human writing.
	Use Natural Language: Employ everyday, straightforward language and use jargon but only where widely understood.
	Vary Vocabulary: Use rich yet simple words, avoiding overly complex or repetitive terms.
	Mix Sentence and Paragraph Lengths: Combine longer, detailed sentences with shorter ones and vary paragraph lengths.
	Maintain Conversational Flow: Ensure smooth transitions, allow occasional tangents, and circle back to key points.
	Incorporate Humour and Humanity: Add humour, admit confusion or errors, and include personal anecdotes or cultural references when relevant.
	Use Simple but Professional Style: Keep the language clear and professional without being excessive.
	Apply Natural Punctuation: Vary punctuation usage inconsistently, sometimes adding extra spaces or using different punctuation marks naturally.
	Ensure Authenticity: Maintain a natural language flow, avoid improbable word combinations, and reflect human-like writing patterns.
	Structure Content for Readability: Break down complex ideas with bullet points, numbered lists, and bold text for easy scanning.
	Encourage Engagement: Pose questions, invite comments, and suggest social shares to boost interaction and signal valuable content.
	Avoid Repetition: Use diverse words and phrases, ensuring varied language throughout.
	Create Inspirational Moments: Introduce ideas and inspiration where applicable to engage readers.
	Allow for Randomness and Inconsistency: Mimic human variability in writing style and sentence construction.
	Include Relatable Examples: Use personal stories and hypothetical scenarios to illustrate points conversationally.
	Vary Spacing Patterns: Occasionally add extra spaces between words or sentences to reflect human typing habits.
	Reinforce Key Points: Evolve ideas and circle back to emphasize important concepts naturally.
	EOT;

	    $personal = <<<EOT
	These are your additional quirky, "hold no punches" style writing instructions for adding extra flair and personality:
	Distinctive Human Style: Write uniquely with raw, unfiltered human quirks, natural errors, inconsistencies, and occasional tangents to reflect authentic thought processes.
	Sarcastic and Witty Tone: Use a sarcastic, witty, confident, and unapologetically blunt tone to highlight absurdities, convey dark humour, and show passion for helping readers.
	It's ok to be eccentric. Include tongue-in-cheek remarks.
	Natural Flow and Cadence: Maintain a natural writing cadence with topic jumps, random pauses, and subtle contradictions to mimic wandering thoughts.
	Engaging and Structured Content: Organise content into punchy, direct sections or tips with logical flow and practical takeaways wrapped in sarcastic commentary.
	Catchy and Provocative Headlines: Create humorous or pun-heavy titles that grab attention and invite deeper, playful exploration of topics.
	Humour and Cultural References: Incorporate cynical humour, pop culture, news, and societal references with irony and a rebellious edge.
	Critical and Practical Advice: Deliver practical advice with scepticism towards trends and buzzwords, debunking myths and critically analysing popular topics.
	Memorable and Thought-Provoking Style: Transform simple topics into witty, sarcastic, and memorable narratives that encourage critical thinking and reader engagement.
	EOT;


		// Append mode-specific instructions
	    switch ( $mode ) {
	        case 'humanize':
	            $instructions = "{$behuman}\n\n{$customInstructions}";
	            break;
	        case 'personality':
	            $instructions = "{$behuman}\n\n{$personal}\n\n{$customInstructions}";
	            break;
	        default:
	            $instructions = $customInstructions;
	    }


	    // Construct the messages array
	    $messages = [
	        [
	            "role"    => "system",
	            "content" => "The year is " . date( 'Y' ) . ". Write in the {$language} language using a {$actualStyle} writing style and a {$actualTone} writing tone. {$instructions}",
	        ],
	        [
	            "role"    => "user",
	            "content" => $autogenerateValue,
	        ],
	    ];


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

		if ( strpos($model, 'gpt-4') !== false ) {
			$endpoint                = 'v1/chat/completions';
			$send_arr['model']       = $model;
			$send_arr['messages']    = $messages;
			$send_arr['temperature'] = $send_arr['temperature'] * 1.5;
			//$send_arr['max_tokens'] = $max_tokens;
			$send_arr['presence_penalty']  = $send_arr['presence_penalty'] / 2;
			$send_arr['frequency_penalty'] = $send_arr['frequency_penalty'] / 2;
			$send_arr['stop']              = "\n\n\n"; // using a longer stop sequence
		}

		$json_str = json_encode( $send_arr );

		// Set up the request using the OpenAI API
		// Refer to Terms of Use <https://openai.com/policies/terms-of-use> and the  Privacy Policy <https://openai.com/policies/privacy-policy>

		// Define the API endpoint
	    // Ensure that 'gpt-4o-mini' uses the same endpoint as OpenAI
	    $endpoint = 'v1/chat/completions';
	    $url      = 'https://api.openai.com/' . $endpoint;

		$args = array(
			'timeout'     => 800,
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
		if ( is_wp_error( $response ) ) {
		    wp_send_json_error( [ 'msg' => 'Failed to connect to the engine API. Error: ' . $response->get_error_message() ] );
		    return;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error'] ) ) {
		    wp_send_json_error( [ 'msg' => 'Engine API Error: ' . $data['error']['message'] ] );
		    return;
		}




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

			'<br/>mode: ' . $mode .
			'<br/>style ' .
			$actualStyle .
			'<br/>tone ' .
			$actualTone .
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


				if ($actionInput == 'article') {

				    // Extract the title from the generated content (assuming the title is in an <h1> tag)
				    $title = '';
				    preg_match('/<h1>(.*?)<\/h1>/', $combinedContent, $matches);
				    if (!empty($matches)) {
				        $title = strip_tags($matches[1]); // Use the title as the prompt
				    }

				    // Now generate a relevant image using DALL·E 2 based on the extracted title
				    $image_prompt = !empty($title) 
				        ? $title . ' - Create an image based on this title. You must not include any text, characters, symbols, or writing. Highly detailed, realistic and stylised to match the title.' 
				        : 'Create a default watermark type image'; // Fallback if title is empty
				    $image_response = $this->generate_dalle_image_from_content($image_prompt);

				    if (is_wp_error($image_response)) {
				        $resultArr['html'] = $image_response->get_error_message();
				        $resultArr['type'] = 'error';
				        echo json_encode($resultArr);
				        exit();
				    }

				    // Check if we got an image URL back
				    $image_url = $image_response['image_url'] ?? '';
				    if (empty($image_url)) {
				        $resultArr['html'] = 'Failed to generate image.';
				        $resultArr['type'] = 'error';
				        echo json_encode($resultArr);
				        exit();
				    }

				    // Truncate the title for the media upload filename
				    $truncated_title_words = explode(' ', $title);
				    $truncated_title = implode(' ', array_slice($truncated_title_words, 0, 8)); // Limit to 8 words
				    $seo_friendly_filename = sanitize_title($truncated_title) . '.png'; // Create SEO-friendly filename

				    // Download the image to a temporary location
				    $temp_file = download_url($image_url);
				    if (is_wp_error($temp_file)) {
				        $resultArr['html'] = $temp_file->get_error_message();
				        $resultArr['type'] = 'error';
				        echo json_encode($resultArr);
				        exit();
				    }

				    // Prepare file array to sideload the image
				    $file_array = array(
				        'name'     => $seo_friendly_filename, // Use the SEO-friendly filename
				        'tmp_name' => $temp_file
				    );

				    // Upload the image and add it to the WordPress media library
				    $attachment_id = media_handle_sideload($file_array, 0, $seo_friendly_filename); // '0' for unattached
				    if (is_wp_error($attachment_id)) {
				        @unlink($temp_file); // Cleanup temp file if upload fails
				        $resultArr['html'] = $attachment_id->get_error_message();
				        $resultArr['type'] = 'error';
				        echo json_encode($resultArr);
				        exit();
				    }

				    // Get the attachment URL after upload
				    $attachment_url = wp_get_attachment_url($attachment_id);

				    // Generate image HTML
				    $image_html = '<img src="' . esc_url($attachment_url) . '" alt="' . esc_attr($title) . '" title="' . esc_attr($title) . '" />';

				    // Insert the image after the closing </h1> tag in $titleHtml
				    $titleHtml =  $image_html . "<p>&nbsp</p>" . $titleHtml;

				    // Add the attachment ID to the AJAX response
					//$resultArr['attachment_id'] = $attachment_id;

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
								// Since gpt-3.5 is removed, we no longer need the model check
							    $combinedContent = str_replace(
							        ",",
							        "\n",
							        $combinedContent
							    );
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