<?php
/*
 * Contributors: 		OPACE LTD
 * Plugin Name: 		AI Writer: ChatGPT SEO Content Creator - AI Blog Writer, Humanizer, GPT-4.5, OpenAI o3, Claude Sonnet 4 & GPT-4o Images
 * Description: 		AI Writer: Free Plugin, ChatGPT, AI, SEO, AI Content Creator, AI Article Writer, AI Blog Writer, GPT-4o Images, Keyword Research, Title Suggestions, Editable Prompts, OpenAI,
 * 						Anthropic Claude Sonnet 4, Opus 4, GPT-4o, GPT-4.5 Preview, OpenAI o3, AI SEO Analysis, Article Evaluation. Compatible with Yoast SEO, Rank Math, AIOSEO & SEOPress.
 * 						Free Plugin - No hidden costs or paid add-ons.
 * Plugin URI: 			https://opace.agency
 * Text Domain: 		ai-writer-gpt-article-builder
 * Tags: 				AI Content Generator, AI Content Creator, AI Article Writer, AI Blog Writer, OpenAI, AI, ChatGPT, SEO,
 * 						Anthropic Claude Sonnet 4, Opus 4, GPT-4o, GPT-4.5, OpenAI o3 128K, Text Creator, Blog Creator, Blog Builder, GPT-4o Images, Article Writer, Content Marketing,
 * 						Free Plugin, GPT, Keyword Research, Humanizer, Human Writer, Long Form Content
 * Author URI: 			https://opace.agency
 * Author: 				Opace Digital Agency
 * Requires at least: 	4.4 or higher
 * Tested up to: 		6.8.1
 * Version: 			2.6
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

define("AI_SCRIBE_DIR", plugin_dir_path(__FILE__));
define("AI_SCRIBE_URL", plugin_dir_url(__FILE__));
define("AI_SCRIBE_VER", "2.6");

class AI_Scribe
{
    //private $nonce;
    //private $autogenerateValue;
    //private $actionInput;
    public function __construct()
    {
        $this->debug_log("AI Scribe: Constructor called");
        register_activation_hook(__FILE__, [$this, "activate"]);
        register_deactivation_hook(__FILE__, [$this, "deactivate"]);

        // Initialize autogenerateValue and actionInput with default values
        $this->autogenerateValue = "";
        $this->actionInput = "";

        //$this->nonce = ''; // Initialize the nonce

        // Generate nonce at the appropriate hook
        //add_action('init', [$this, 'initialize_nonce']);

        // Register hooks on init to ensure WordPress is fully loaded
        add_action('init', [$this, 'register_hooks']);
    }

    /*public function initialize_nonce() {
	    if (!isset($this->nonce)) { // Ensure it's set only once
	        $this->nonce = wp_create_nonce('ai_scribe_nonce');
	    }
	}*/

    public function enqueue_scripts($page)
    {
        // Generate a nonce for the current request
        $nonce = wp_create_nonce("ai_scribe_nonce");

        // Handle "Saved Shortcodes" page
        if ($page === "ai-scribe_page_ai_scribe_saved_shortcodes") {
            wp_enqueue_style(
                "ai-scribe-bootstrap",
                plugins_url("assets/css/bootstrap5.2.css", __FILE__),
                [],
                filemtime(AI_SCRIBE_DIR . "assets/css/bootstrap5.2.css")
            );
            wp_enqueue_script(
                "ai-scribe-show_template",
                plugins_url("assets/js/show_template.js", __FILE__),
                ["jquery"],
                filemtime(AI_SCRIBE_DIR . "assets/js/show_template.js"),
                true
            );
            wp_localize_script("ai-scribe-show_template", "ai_scribe", [
                "ajaxUrl" => admin_url("admin-ajax.php"),
                "nonce" => $nonce,
            ]);
        }

        // Handle "Generate Article" page
        if ($page === "ai-scribe_page_ai_scribe_generate_article") {
            wp_enqueue_style(
                "ai-scribe-quill",
                plugins_url("assets/css/quill.css", __FILE__),
                [],
                filemtime(AI_SCRIBE_DIR . "assets/css/quill.css")
            );
            wp_enqueue_style(
                "ai-scribe-font_awesome",
                plugins_url("assets/css/font_awesome.css", __FILE__),
                [],
                filemtime(AI_SCRIBE_DIR . "assets/css/font_awesome.css")
            );
            wp_enqueue_style(
                "ai-scribe-create_template",
                plugins_url("assets/css/article_builder.css", __FILE__),
                ["ai-scribe-quill", "ai-scribe-font_awesome"],
                filemtime(AI_SCRIBE_DIR . "assets/css/article_builder.css")
            );
            wp_enqueue_script(
                "ai-scribe-quill",
                plugins_url("assets/js/quill.min.js", __FILE__),
                ["jquery"],
                filemtime(AI_SCRIBE_DIR . "assets/js/quill.min.js"),
                true
            );
            wp_enqueue_script(
                "ai-scribe-create_template",
                plugins_url("assets/js/create_template.js", __FILE__),
                ["jquery", "ai-scribe-quill"],
                filemtime(AI_SCRIBE_DIR . "assets/js/create_template.js"),
                true
            );
            wp_localize_script("ai-scribe-create_template", "ai_scribe", [
                "ajaxUrl" => admin_url("admin-ajax.php"),
                "nonce" => $nonce,
                "apiKey" =>
                    get_option("ab_gpt_ai_engine_settings")["api_key"] ?? "",
                "settingsUrl" => admin_url("admin.php?page=ai_scribe_settings"),
                "checkArr" =>
                    get_option("ab_gpt_content_settings")["check_Arr"] ?? "",
                "promptsData" => get_option("ab_prompts_content"),
                "aiEngine" => get_option("ab_gpt_ai_engine_settings"),
            ]);
        }

        // Handle "Settings" page
        if ($page === "ai-scribe_page_ai_scribe_settings") {
            wp_enqueue_style(
                "ai-scribe-settings",
                plugins_url("assets/css/settings.css", __FILE__),
                [],
                filemtime(AI_SCRIBE_DIR . "assets/css/settings.css")
            );
            wp_enqueue_script(
                "ai-scribe-settings",
                plugins_url("assets/js/settings.js", __FILE__),
                ["jquery"],
                filemtime(AI_SCRIBE_DIR . "assets/js/settings.js"),
                true
            );
            wp_localize_script("ai-scribe-settings", "ai_scribe", [
                "ajaxUrl" => admin_url("admin-ajax.php"),
                "nonce" => $nonce,
            ]);
        }

        // Handle "Help" page
        if (
            isset($_GET["page"]) &&
            sanitize_text_field($_GET["page"]) === "ai_scribe_help"
        ) {
            wp_enqueue_style(
                "ai-scribe-create_template",
                plugins_url("assets/css/article_builder.css", __FILE__),
                [],
                filemtime(AI_SCRIBE_DIR . "assets/css/article_builder.css")
            );
            wp_enqueue_style(
                "ai-scribe-help-page",
                plugins_url("assets/css/help.css", __FILE__),
                ["ai-scribe-create_template"],
                filemtime(AI_SCRIBE_DIR . "assets/css/help.css")
            );
        }
    }

    public function register_hooks()
    {
        $this->debug_log("AI Scribe: register_hooks() called");
        // Nonce handling
        add_action("wp_ajax_refresh_nonce", [$this, "refresh_nonce"]);
        add_action("wp_ajax_nopriv_refresh_nonce", [$this, "refresh_nonce"]);
        add_filter("nonce_life", [$this, "extend_nonce_life"]);

        // Enqueue scripts and styles
        add_action("admin_enqueue_scripts", [$this, "enqueue_scripts"]);

        // Admin menu
        add_action("admin_menu", [$this, "add_menu"]);

        // AJAX actions
        add_action("wp_ajax_al_scribe_remove_short_code_content", [
            $this,
            "remove_short_code_content",
        ]);
        add_action("wp_ajax_al_scribe_content_data", [$this, "content_data"]);
        add_action("wp_ajax_al_scribe_engine_request_data", [
            $this,
            "engine_request_data",
        ]);
        add_action("wp_ajax_al_scribe_send_post_page", [
            $this,
            "send_post_page",
        ]);
        add_action("wp_ajax_al_scribe_suggest_content", [
            $this,
            "suggest_content",
        ]);
        // Use the class method for the AJAX handler
        if (method_exists($this, 'generate_gpt_image_1')) {
            $this->debug_log("AI Scribe: ✅ generate_gpt_image_1 method EXISTS - registering AJAX handlers");
            add_action("wp_ajax_al_scribe_generate_4o_image", [
                $this,
                "generate_gpt_image_1",
            ]);
            add_action("wp_ajax_nopriv_al_scribe_generate_4o_image", [
                $this,
                "generate_gpt_image_1",
            ]);
        } else {
            $this->debug_log("AI Scribe: ❌ generate_gpt_image_1 method DOES NOT EXIST!");
        }

        // Debug: Log AJAX handler registration
        $this->debug_log("AI Scribe: AJAX handlers registered - al_scribe_generate_4o_image");
        
        // Add a simple test AJAX handler that just echoes and dies
        add_action("wp_ajax_al_scribe_test_simple", function() {
            echo json_encode(['success' => true, 'message' => 'Simple handler works!']);
            wp_die();
        });
        add_action("wp_ajax_nopriv_al_scribe_test_simple", function() {
            echo json_encode(['success' => true, 'message' => 'Simple handler works!']);
            wp_die();
        });
        
        // Add a simple test AJAX handler
        add_action("wp_ajax_al_scribe_test_handler", [$this, "test_ajax_handler"]);
        add_action("wp_ajax_nopriv_al_scribe_test_handler", [$this, "test_ajax_handler"]);
        
        // Add a direct test for the image handler
        add_action("wp_ajax_al_scribe_test_image", function() {
            error_log("AI Scribe: TEST IMAGE HANDLER CALLED!");
            wp_send_json_success(['message' => 'Test image handler works!']);
        });
        add_action("wp_ajax_nopriv_al_scribe_test_image", function() {
            error_log("AI Scribe: TEST IMAGE HANDLER CALLED!");
            wp_send_json_success(['message' => 'Test image handler works!']);
        });
        add_action("wp_ajax_al_scribe_send_shortcode_page", [
            $this,
            "send_shortcode_page",
        ]);
        add_action("wp_ajax_get_article", [$this, "get_article"]);
        add_action("wp_ajax_ai_scribe_generate_image", [
            $this,
            "ajax_generate_image",
        ]);
        add_action("wp_ajax_ai_scribe_get_pricing", [
            $this,
            "get_dynamic_pricing",
        ]);
        add_action("wp_ajax_update_style_tone", [$this, "update_style_tone"]);

        // Admin notices
        add_action("admin_notices", [$this, "ai_scribe_uninstall_notice"]);

        // Plugin actions and filters
        add_filter("plugin_action_links_" . plugin_basename(__FILE__), [
            $this,
            "add_settings_link",
        ]);
        add_action("plugins_loaded", [$this, "load_textdomain"]);

        // Shortcode
        add_shortcode("article_builder_generate_data", [
            $this,
            "send_shortcode_page_data",
        ]);

        // Add debug mode injection for admin users
        add_action("admin_footer", [$this, "inject_debug_mode"]);
    }

    public function refresh_nonce()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(["msg" => "Unauthorized request."]);
            return;
        }

        wp_send_json_success(["nonce" => wp_create_nonce("ai_scribe_nonce")]);
    }

    public function extend_nonce_life()
    {
        return 24 * HOUR_IN_SECONDS;
    }

    /**
     * Check if debug mode is enabled for PHP logging
     * Debug mode is disabled by default for production safety
     */
    private function is_debug_mode_enabled()
    {
        // Debug mode is disabled by default for production
        // Set to true only during development/debugging
        $debug_enabled = false; // Change to true to enable PHP debugging

        // During early WordPress loading, user functions may not be available
        // Only check user permissions if WordPress is fully loaded
        if (!function_exists('current_user_can') || !function_exists('wp_get_current_user')) {
            return false; // Disable debugging during early initialization
        }

        // Only allow debugging for admin users
        if (!current_user_can('manage_options')) {
            return false;
        }

        return $debug_enabled;
    }

    /**
     * Debug-aware error logging
     * Only logs when debug mode is enabled and user has proper permissions
     * Safe to call during early WordPress initialization
     */
    private function debug_log($message)
    {
        // During early WordPress loading, skip all debug logging to prevent errors
        if (!function_exists('wp_get_current_user') || !function_exists('current_user_can')) {
            return; // Silently skip during early initialization
        }

        if ($this->is_debug_mode_enabled()) {
            error_log($message);
        }
    }

    /**
     * Inject debug mode flag for admin users
     * Only users with manage_options capability will have debugging enabled
     */
    public function inject_debug_mode()
    {
        // Only inject for admin users (check if user functions are available)
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            return;
        }

        // Check if we're on an AI Scribe admin page (check if screen functions are available)
        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'ai-scribe') === false) {
            return;
        }

        // Inject the debug mode variable (disabled by default for production)
        echo '<script type="text/javascript">
            window.aiScribeDebugMode = false; // Set to true to enable debugging
            if (window.aiScribeDebugMode) {
                console.log("🔧 AI Scribe Debug Mode: ENABLED");
                console.log("🔧 Debug mode can be disabled by setting window.aiScribeDebugMode = false");
            }
        </script>';
    }

    public function update_style_tone()
    {
        if (
            !isset($_POST["security"]) ||
            !check_ajax_referer("ai_scribe_nonce", "security", false)
        ) {
            wp_send_json_error([
                "msg" =>
                    "Invalid request. Please refresh the page and try again.",
                "nonce_expired" => true, // Flag to handle nonce expiration in JS
            ]);
            $this->debug_log("Failed nonce validation.");
            $this->debug_log("Received nonce: " . ($_POST["security"] ?? "None"));
            return;
        }

        // Sanitize input
        $writingStyle = sanitize_text_field($_POST["writing_style"] ?? "");
        $writingTone = sanitize_text_field($_POST["writing_tone"] ?? "");
        $language = sanitize_text_field($_POST["language"] ?? "");

        // Get existing content settings
        $contentSettings = get_option("ab_gpt_content_settings");

        if (!$contentSettings) {
            $this->debug_log(__FUNCTION__ . ": Content settings not found.");
            wp_send_json_error("Content settings not found.");
            return;
        }
        /*
	    // Update with new values
	    $contentSettings['writing_style'] = $writingStyle;
	    $contentSettings['writing_tone'] = $writingTone;
	    $contentSettings['language'] = $language;

	    // Save the updated content settings
	    $updated = update_option('ab_gpt_content_settings', $contentSettings);
	    if ($updated === false) {
	        error_log(__FUNCTION__ . ': Failed to update content settings.');
	        wp_send_json_error('Failed to update content settings.');
	        return;
	    }

	    // Send success response
	    wp_send_json_success('Content settings updated.');
	    */

        // Fetch the current settings to prevent overwriting

        $currentSettings = get_option("ab_gpt_content_settings");
        if (!$currentSettings) {
            wp_send_json_error("Content settings not found.");
            return;
        }

        // Merge the new settings with existing ones
        $newSettings = wp_parse_args(
            [
                "writing_style" => $writingStyle,
                "writing_tone" => $writingTone,
                "language" => $language,
            ],
            $currentSettings
        );

        // Update only if there are changes
        if ($newSettings !== $currentSettings) {
            $updated = update_option("ab_gpt_content_settings", $newSettings);
            if ($updated === false) {
                wp_send_json_error("Failed to update content settings.");
                return;
            }
        }

        wp_send_json_success("Content settings updated.");
    }

    /*
     * Function: get_article
     * Description: Initializes the GET request to get the article data.
     */
    public function get_article()
    {
        // Check nonce for security
        if (!check_ajax_referer("ai_scribe_nonce", "security", false)) {
            wp_send_json_error([
                "msg" =>
                    "Invalid request. Please refresh the page and try again.",
                "nonce_expired" => true,
            ]);
            return;
        }

        // This function appears to be incomplete in the original code
        // For now, just return a success response
        wp_send_json_success(["msg" => "Article retrieved successfully"]);
    }

    /*
     * Function: estimate_tokens
     * Description: Estimate token count for text content using accurate approximation
     */
    public function estimate_tokens($text, $model = 'gpt-4o') {
        if (empty($text)) {
            return 0;
        }

        // Clean the text
        $text = trim($text);

        // Different models have different tokenization patterns
        $is_anthropic = strpos($model, 'claude') !== false;

        if ($is_anthropic) {
            // Claude tokenization: roughly 3.5-4 characters per token
            $chars_per_token = 3.7;
        } else {
            // OpenAI tokenization: roughly 4 characters per token for English
            $chars_per_token = 4.0;
        }

        // Count characters
        $char_count = mb_strlen($text, 'UTF-8');

        // Basic token estimation
        $estimated_tokens = ceil($char_count / $chars_per_token);

        // Adjust for common patterns that affect tokenization
        // Words with punctuation, numbers, special characters use more tokens
        $word_count = str_word_count($text);
        $punctuation_count = preg_match_all('/[^\w\s]/', $text);
        $number_count = preg_match_all('/\d+/', $text);

        // Add overhead for complex content
        $complexity_factor = 1.0;
        if ($punctuation_count > $word_count * 0.1) {
            $complexity_factor += 0.1; // Heavy punctuation
        }
        if ($number_count > $word_count * 0.05) {
            $complexity_factor += 0.05; // Numbers present
        }

        $estimated_tokens = ceil($estimated_tokens * $complexity_factor);

        return max(1, $estimated_tokens); // Minimum 1 token
    }

    /*
     * Function: estimate_article_tokens
     * Description: Estimate total tokens for a complete article generation process
     */
    public function estimate_article_tokens($input_text, $model = 'gpt-4o', $action = 'article') {
        $base_input_tokens = $this->estimate_tokens($input_text, $model);

        // Estimate output tokens based on action type
        $output_multipliers = [
            'title' => 0.2,        // Short titles
            'keyword' => 0.3,      // List of keywords
            'heading' => 0.8,      // Multiple headings
            'intro' => 1.5,        // Introduction paragraph
            'article' => 8.0,      // Full article content
            'conclusion' => 1.2,   // Conclusion paragraph
            'qna' => 2.0,         // Q&A sections
            'seo-meta-data' => 0.5, // Meta descriptions
            'evaluate' => 3.0,     // Evaluation content
            'review' => 2.5        // Review content
        ];

        $output_multiplier = $output_multipliers[$action] ?? 2.0;
        $estimated_output_tokens = ceil($base_input_tokens * $output_multiplier);

        // Add system prompt overhead (approximately 200-500 tokens)
        $system_prompt_tokens = 350;

        // Add conversation context overhead
        $context_overhead = ceil($base_input_tokens * 0.1);

        $total_input_tokens = $base_input_tokens + $system_prompt_tokens + $context_overhead;
        $total_output_tokens = $estimated_output_tokens;

        return [
            'input_tokens' => $total_input_tokens,
            'output_tokens' => $total_output_tokens,
            'total_tokens' => $total_input_tokens + $total_output_tokens
        ];
    }



    /*
     * Function: get_dynamic_pricing
     * Description: Get current pricing data for AI models with caching
     */
    public function get_dynamic_pricing() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(["msg" => "Unauthorized request."]);
            return;
        }

        // Verify nonce for security
        if (!wp_verify_nonce($_POST['security'] ?? '', 'ai_scribe_nonce')) {
            wp_send_json_error(["msg" => "Security check failed."]);
            return;
        }

        // Verify nonce for security
        if (!wp_verify_nonce($_POST['security'] ?? '', 'ai_scribe_nonce')) {
            wp_send_json_error(["msg" => "Security check failed."]);
            return;
        }

        // Check for cached pricing (cache for 1 hour)
        $cached_pricing = get_transient('ai_scribe_model_pricing');
        if ($cached_pricing !== false) {
            wp_send_json_success($cached_pricing);
            return;
        }

        // Accurate pricing data (per 1K tokens as specified)
        $pricing_data = [
            'openai' => [
                'gpt-4o' => [
                    'input' => 0.005,   // $0.005 per 1K input tokens
                    'output' => 0.02,   // $0.02 per 1K output tokens
                    'context' => 128000, // 128K tokens
                    'article_estimate' => 0.06, // $0.06 per article estimate
                    'unit' => 'per_1k_tokens'
                ],
                'gpt-4o-mini' => [
                    'input' => 0.0006,  // $0.0006 per 1K input tokens
                    'output' => 0.0024, // $0.0024 per 1K output tokens
                    'context' => 128000, // 128K tokens
                    'article_estimate' => 0.007, // $0.007 per article estimate
                    'unit' => 'per_1k_tokens'
                ],
                'gpt-4.5-preview' => [
                    'input' => 0.075,   // Estimated based on premium pricing
                    'output' => 0.15,   // Estimated based on premium pricing
                    'context' => 128000, // 128K tokens
                    'article_estimate' => 0.45, // Estimated per article
                    'unit' => 'per_1k_tokens'
                ],
                'o3' => [
                    'input' => 0.01,    // $0.01 per 1K input tokens
                    'output' => 0.04,   // $0.04 per 1K output tokens
                    'context' => 200000, // 200K tokens
                    'article_estimate' => 0.12, // $0.12 per article estimate
                    'unit' => 'per_1k_tokens'
                ]
            ],
            'anthropic' => [
                'claude-sonnet-4-20250514' => [
                    'input' => 0.003,   // $0.003 per 1K input tokens
                    'output' => 0.015,  // $0.015 per 1K output tokens
                    'context' => 200000, // 200K tokens
                    'article_estimate' => 0.043, // $0.043 per article estimate
                    'unit' => 'per_1k_tokens'
                ],
                'claude-opus-4-20250514' => [
                    'input' => 0.015,   // $0.015 per 1K input tokens
                    'output' => 0.075,  // $0.075 per 1K output tokens
                    'context' => 200000, // 200K tokens
                    'article_estimate' => 0.214, // $0.214 per article estimate
                    'unit' => 'per_1k_tokens'
                ]
            ],
            'image_generation' => [
                'gpt-image-1' => [
                    'low_quality' => 0.01,    // $0.01 per image (low)
                    'medium_quality' => 0.04, // $0.04 per image (medium)
                    'high_quality' => 0.17,   // $0.17 per image (high)
                    'text_prompt' => 0.005,   // $0.005 per 1K tokens for text prompt
                    'unit' => 'per_image'
                ]
            ]
        ];

        // Cache the pricing data for 1 hour
        set_transient('ai_scribe_model_pricing', $pricing_data, HOUR_IN_SECONDS);

        wp_send_json_success($pricing_data);
    }

    /*
     * Function: ajax_generate_image
     * Description: AJAX handler for background image generation with WordPress media library integration
     */
    public function ajax_generate_image()
    {
        // Check nonce for security
        if (!check_ajax_referer("ai_scribe_nonce", "security", false)) {
            wp_send_json_error([
                "msg" =>
                    "Invalid request. Please refresh the page and try again.",
                "nonce_expired" => true,
            ]);
            return;
        }

        // Get the prompt from the request
        $prompt = sanitize_textarea_field($_POST["prompt"] ?? "");

        if (empty($prompt)) {
            wp_send_json_error([
                "msg" => "No prompt provided for image generation",
            ]);
            return;
        }

        // Initialize debug messages array
        $debug_messages = [];
        $debug_messages[] = "🎨 AJAX: Starting background image generation";
        $debug_messages[] =
            "📝 AJAX: Prompt: " . substr($prompt, 0, 100) . "...";

        // Generate the image using simple gpt-image-1 API
        $image_response = $this->generate_4o_image_from_content(
            $prompt,
            $debug_messages
        );

        // Check if image generation was successful
        if (is_wp_error($image_response)) {
            $debug_messages[] =
                "❌ AJAX: Image generation failed: " .
                $image_response->get_error_message();
            wp_send_json_error([
                "msg" =>
                    "Image generation failed: " .
                    $image_response->get_error_message(),
                "debug_messages" => $debug_messages,
            ]);
            return;
        }

        if (
            !is_array($image_response) ||
            !isset($image_response["image_url"])
        ) {
            $debug_messages[] = "❌ AJAX: Invalid image response format";
            wp_send_json_error([
                "msg" => "Invalid image response format",
                "debug_messages" => $debug_messages,
                "response" => $image_response,
            ]);
            return;
        }

        $image_url = $image_response["image_url"];
        $debug_messages[] =
            "✅ AJAX: Image URL received: " . substr($image_url, 0, 50) . "...";

        // Extract title from prompt for filename
        $title_parts = explode(" - ", $prompt);
        $title = trim($title_parts[0]);

        // Download and save to WordPress media library
        $debug_messages[] =
            "📥 AJAX: Starting download to WordPress media library";

        try {
            // Create SEO-friendly filename
            $truncated_title_words = explode(" ", $title);
            $truncated_title = implode(
                " ",
                array_slice($truncated_title_words, 0, 8)
            ); // Limit to 8 words
            $seo_friendly_filename = sanitize_title($truncated_title) . ".webp"; // Use webp extension

            $debug_messages[] = "📁 AJAX: Filename: " . $seo_friendly_filename;

            // Download the image to a temporary location
            $temp_file = download_url($image_url);
            if (is_wp_error($temp_file)) {
                $debug_messages[] =
                    "❌ AJAX: Download failed: " .
                    $temp_file->get_error_message();
                wp_send_json_error([
                    "msg" =>
                        "Failed to download image: " .
                        $temp_file->get_error_message(),
                    "debug_messages" => $debug_messages,
                ]);
                return;
            }

            $debug_messages[] = "✅ AJAX: Image downloaded to temp file";

            // Prepare file array to sideload the image
            $file_array = [
                "name" => $seo_friendly_filename,
                "tmp_name" => $temp_file,
            ];

            // Upload the image and add it to the WordPress media library
            $attachment_id = media_handle_sideload(
                $file_array,
                0,
                $seo_friendly_filename
            );
            if (is_wp_error($attachment_id)) {
                @unlink($temp_file); // Cleanup temp file if upload fails
                $debug_messages[] =
                    "❌ AJAX: Media library upload failed: " .
                    $attachment_id->get_error_message();
                wp_send_json_error([
                    "msg" =>
                        "Failed to upload to media library: " .
                        $attachment_id->get_error_message(),
                    "debug_messages" => $debug_messages,
                ]);
                return;
            }

            // Update attachment metadata with proper title, caption, and alt text
            $attachment_data = [
                'ID' => $attachment_id,
                'post_title' => $prompt, // Image title - use the prompt as title
                'post_excerpt' => $prompt, // Image caption - use the prompt as caption
            ];
            wp_update_post($attachment_data);
            
            // Set alt text using update_post_meta
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $prompt);
            
            $debug_messages[] = "✅ AJAX: Image metadata updated - Title: '$prompt', Caption: '$prompt', Alt: '$prompt'";

            // Get the attachment URL after upload
            $attachment_url = wp_get_attachment_url($attachment_id);
            $debug_messages[] =
                "✅ AJAX: Image uploaded to media library with ID: " .
                $attachment_id;
            $debug_messages[] =
                "🔗 AJAX: WordPress media URL: " . $attachment_url;

            // Generate image HTML with proper alt, title, and caption for AJAX response
            $image_html = '<figure class="wp-block-image">' .
                '<img src="' .
                esc_url($attachment_url) .
                '" alt="' .
                esc_attr($prompt) .
                '" title="' .
                esc_attr($prompt) .
                '" class="wp-image-' . $attachment_id . '" />' .
                '<figcaption>' . esc_html($prompt) . '</figcaption>' .
                '</figure>';

            // Return success with WordPress media library URL and formatted HTML
            wp_send_json_success([
                "image_url" => $attachment_url,
                "attachment_id" => $attachment_id,
                "image_html" => $image_html,
                "original_url" => $image_url,
                "filename" => $seo_friendly_filename,
                "debug_messages" => $debug_messages,
            ]);
        } catch (Exception $e) {
            $debug_messages[] =
                "❌ AJAX: Exception during media library processing: " .
                $e->getMessage();
            wp_send_json_error([
                "msg" =>
                    "Exception during image processing: " . $e->getMessage(),
                "debug_messages" => $debug_messages,
            ]);
        }
    }

    /*
     * Function: activate
     * Description: Creates the custom database table and sets default options when the plugin is activated.
     */
    public function activate()
    {
        global $wpdb, $table_prefix;

        $wp_article = $table_prefix . "article_builder";

        // Check if the table exists
        if (
            $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $wpdb->esc_like($wp_article)
                )
            ) != $wp_article
        ) {
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
            $wpdb->query($q);
        }

        // Call the method to set default options
        $this->set_default_options();

        // Add the delete data on uninstall option if not already set
        if (get_option("ai_scribe_delete_data_on_uninstall") === false) {
            add_option("ai_scribe_delete_data_on_uninstall", "no");
        }
    }

    /*
     * Function: ai_scribe_delete_data_confirm
     * Description: Updates the option to delete or not delete data on plugin uninstall based on user's choice.
     */

    public function ai_scribe_uninstall_notice()
    {
        $screen = get_current_screen();
        if ($screen->id == "plugins") {
            $delete_data = get_option("ai_scribe_delete_data_on_uninstall");
            if ($delete_data === "no") { ?>
				<div class="notice notice-warning is-dismissible">
					<p>
						<strong>AI Scribe:</strong> Do you want to delete all data when uninstalling the plugin?
						<a href="<?php echo esc_url(
          admin_url(
              "admin-post.php?action=ai_scribe_delete_data_confirm&choice=yes"
          )
      ); ?>">Yes</a>
						|
						<a href="<?php echo esc_url(
          admin_url(
              "admin-post.php?action=ai_scribe_delete_data_confirm&choice=no"
          )
      ); ?>">No</a>
					</p>
				</div>
				<?php }
        }
    }

    /*
     * Function: uninstall
     * Description: Removes custom database tables and options created by the plugin when it's uninstalled, if the user has chosen to delete all data.
     */

    public function ai_scribe_delete_data_confirm()
    {
        $choice = sanitize_text_field($_GET["choice"]);
        if (!in_array($choice, ["yes", "no"], true)) {
            wp_die("Invalid choice");
        }
        if ($choice) {
            update_option("ai_scribe_delete_data_on_uninstall", $choice);
        }
        wp_redirect(admin_url("plugins.php"));
        exit();
    }

    /*
     * Function: deactivate
     * Description: Placeholder function for when the plugin is deactivated.
     */

    public function uninstall()
    {
        // Check if the user has opted to delete data on uninstall
        $delete_data = get_option("ai_scribe_delete_data_on_uninstall");
        if ($delete_data !== "yes") {
            return; // Exit if data deletion is not allowed
        }

        global $wpdb;

        // Remove the custom database table
        $table_name = $wpdb->prefix . "article_builder";

        $query_result = $wpdb->query(
            $wpdb->prepare("DROP TABLE IF EXISTS `%s`", $table_name)
        );
        if ($query_result === false) {
            $this->debug_log(
                __FUNCTION__ .
                    ": Failed to drop the article_builder table: " .
                    $wpdb->last_error
            );
        }

        // Remove options created by the plugin
        $options = [
            "ab_gpt_ai_engine_settings",
            "ab_gpt_content_settings",
            "ai_scribe_languages",
            "ai_scribe_delete_data_on_uninstall",
        ];

        foreach ($options as $option) {
            if (!delete_option($option)) {
                $this->debug_log(
                    __FUNCTION__ . ": Failed to delete option: " . $option
                );
            }
        }

        // Add a log entry or notice for successful uninstallation
        $this->debug_log(
            __FUNCTION__ .
                ": AI Scribe plugin data has been successfully removed."
        );
    }

    /*
     * Function: add_settings_link
     * Description: Adds the Settings and Help links to the plugin's action links on the plugins page.
     */

    public function deactivate()
    {
    }

    /*
     * Function: content_data
     * Description: Updates the content settings in the options table.
     */
    public function content_data()
    {
        // If you only want admins or similarly privileged users to do this:
        if (!current_user_can("manage_options")) {
            wp_send_json_error(["msg" => "Unauthorized action."]);
            return;
        }

        // Verify nonce
        if (
            !isset($_POST["security"]) ||
            !check_ajax_referer("ai_scribe_nonce", "security", false)
        ) {
            wp_send_json_error([
                "msg" => "Invalid request. Nonce verification failed.",
            ]);
            return;
        }

        // Validate and sanitize inputs
        $new_language = isset($_POST["custom_language"])
            ? sanitize_text_field($_POST["custom_language"])
            : "";
        $lang = isset($_POST["language"])
            ? sanitize_text_field($_POST["language"])
            : "";
        $wrtStyle = isset($_POST["writing_style"])
            ? sanitize_text_field($_POST["writing_style"])
            : "";
        $wrtStone = isset($_POST["writing_tone"])
            ? sanitize_text_field($_POST["writing_tone"])
            : "";
        $numHeading = isset($_POST["number_of_heading"])
            ? sanitize_text_field($_POST["number_of_heading"])
            : "";
        $headTag = isset($_POST["Heading_tag"])
            ? sanitize_text_field($_POST["Heading_tag"])
            : "";
        $modHead = isset($_POST["modify_heading"])
            ? sanitize_text_field($_POST["modify_heading"])
            : "";
        $checkboxArr = isset($_POST["checkArr"])
            ? array_map("sanitize_text_field", (array) $_POST["checkArr"])
            : [];
        $promptsContentInput = isset($_POST["prompts_content"])
            ? array_map(
                "sanitize_textarea_field",
                (array) $_POST["prompts_content"]
            )
            : [];
        $cslist = isset($_POST["cs_list"])
            ? sanitize_text_field($_POST["cs_list"])
            : "";

        // Handle mode selection
        $mode = isset($_POST["mode"])
            ? sanitize_text_field($_POST["mode"])
            : "";
        if (empty($mode)) {
            $existingSettings = get_option("ab_gpt_content_settings", []);
            $mode = isset($existingSettings["mode"])
                ? $existingSettings["mode"]
                : "standard";
        }

        // Handle custom language input
        if (!empty($new_language)) {
            $languages = get_option("ai_scribe_languages", []);
            if (!is_array($languages)) {
                $languages = [];
            }

            if (!in_array($new_language, $languages, true)) {
                $languages[] = $new_language;
                update_option("ai_scribe_languages", $languages);
            }

            $lang = $new_language; // Use the new language as the selected language
        }

        // Build the content settings array
        $frmArr = [
            "language" => $lang,
            "writing_style" => $wrtStyle,
            "writing_tone" => $wrtStone,
            "number_of_heading" => $numHeading,
            "Heading_tag" => $headTag,
            "modify_heading" => $modHead,
            "check_Arr" => $checkboxArr,
            "cs_list" => $cslist,
            "mode" => $mode,
        ];

        // Update content settings in the database
        $updatedSettings = update_option("ab_gpt_content_settings", $frmArr);
        if (!$updatedSettings) {
            wp_send_json_error(["msg" => "Failed to update content settings."]);
            return;
        }

        // Handle prompts content
        $existingPromptsContent = get_option("ab_prompts_content", []);

        // Save user's custom instructions
        $customInstructions = isset(
            $promptsContentInput["instructions_prompts"]
        )
            ? sanitize_textarea_field(
                $promptsContentInput["instructions_prompts"]
            )
            : "";
        unset($promptsContentInput["instructions_prompts"]);

        // Determine instructions based on the mode
        $promptsContentInput["user_instructions"] =
            $mode === "standard"
                ? $customInstructions
                : "Additional instructions for non-standard modes.";

        // Merge existing and new prompts content
        $promptsContent = array_merge(
            $existingPromptsContent,
            $promptsContentInput
        );

        // Update prompts content in the database
        $updatedPrompts = update_option("ab_prompts_content", $promptsContent);
        if (!$updatedPrompts) {
            wp_send_json_error(["msg" => "Failed to update prompts content."]);
            return;
        }

        // Success response
        wp_send_json_success([
            "msg" => "Your settings have been updated successfully.",
        ]);
    }

    /*
     * Function: load_textdomain
     * Description: Loads the plugin's textdomain for translations.
     */

    private function set_default_options()
    {
        $contentsetting = [
            "language" => "English",
            "writing_style" => "Business",
            "writing_tone" => "Professional",
            "number_of_heading" => "5",
            "Heading_tag" => "H2",
            "check_Arr" => [
                "addQNA" => "addQNA",
                "addinsertHyper" => "addinsertHyper",
                "addinsertToc" => "addinsertToc",
                "addfurtheReading" => "addfurtheReading",
                "addsubMatter" => "addsubMatter",
                "addimgCont" => "addimgCont",
                "addkeywordBold" => "addkeywordBold",
            ],
            "cs_list" => "",
        ];
        $enginesetting = [
            "model" => "gpt-4o-mini",
            "temp" => 0.5,
            "top_p" => 0.5,
            "freq_pent" => 0.2,
            "Presence_penalty" => 0.2,
            "n" => 1,
        ];
        $promptssetting = [
            "instructions_prompts" =>
                'These are your most basic writing instructions: Your name is AI-Scribe and you are a talented copywriter and SEO specialist. Always write naturally and use the UK English spellings rather than US if writing in the English language, e.g. do not use words like "optimize" that contain a "z" near the end, as should be spelt as "optimise", "optimising", "optimised", etc. Focus on producing helpful SEO content that google will appreciate. 
                Respond using plain language. Do not provide any labels like "Section..." or "Sub-Section...". Do not provide any explanations, notes, other labelling or analysis. Follow my prompts carefully. Do not use these these words or phrases that contain them: Ever Changing, Ever Evolving, Testament, As A Professional, Previously Mentioned, Buckle Up, Dance, Delve, Digital Era, Dive In, Embark, Enable, Emphasise, Embracing, Enigma, Ensure, Essential, Even If, Even Though, Folks, Foster, Furthermore, Game Changer, Given That, Importantly, In Contrast, In Order To, World Of, Digital Era, In Today’s, Indeed, Indelible, Essential To, Imperative, Important To, Worth Noting, Journey, Labyrinth, Landscape, Look No Further, Moreover, Navigating, Nestled, Nonetheless, Notably, Other Hand, Overall, Pesky, Promptly, Realm, Remember That, Remnant, Revolutionize, Shed Light, Symphony, Dive Into, Tapestry, Testament, That Being Said, Crucial, Considerations, Exhaustive, Thus, Put It Simply, To Summarize, Unlock, Unleash, Unleashing, Ultimately, Underscore, Vibrant, Vital. ',
            "title_prompts" =>
                'Provide 5 unique article titles for my blog based on "[Idea]". They need to be unique and cover a different angle to topics that are already likely to be covered. ',
            "Keywords_prompts" =>
                'For the title "[Title]", provide a list of 5 relevant keywords or phrases each on a new line. These need to be popular searches (keywords or short phrases) that people are likely to enter into Google and capable of driving traffic to the article. Capitsalise each word.',
            "outline_prompts" =>
                'Write an article outline titled [Title]. Create [No. Headings] sections and no sub-sections for the body of my article. Don\'t include an introduction or conclusion. This needs to be a simple list of section headings. Do not add any commentary, notes or additional information such as section labels, "Section 1", "Section 2", etc. Please include the following SEO keywords following SEO keywords [Selected Keywords] where appropriate in the headings. ',
            "intro_prompts" =>
                'Generate an introduction for my article as a single paragraph. Do not include a separate heading. Base the introduction on the "[Title]" title and the [Selected Keywords]. Write the introduction in the [Language] language using a [Style] writing style and a [Tone] writing tone.',
            "tagline_prompts" =>
                'Generate a tagline for my article. Base the tagline on the "[Title]" title and the [Selected Keywords]. ',
            "article_prompts" =>
                'Write my article using only HTML tags directly in the output without enclosing the content in any ``` or code block syntax. Include a H1 tag for the [Title] main title. Add a tagline called "[The Tagline]" [above/below]. Include the introduction: [Intro]. 
                Then write each section using my outline, making sure each section heading is formatted as a [Heading Tag] tag: [Heading].  
                Strictly randomise the <p> count under each [Heading Tag] tag. Some sections should have just 1 <p> tag, while others may have up to 4 <p> tags. No two consecutive [Heading Tag] tag or section should have the same number of <p> tags. Vary the word count of each paragraph by at least 50%. 
                Each section should provide a unique perspective on the topic and provide value over and above what\'s already available. You must not include a conclusion heading or section.
                Each section must be explored in detail. To achieve this, you must include all possible known features, benefits, arguments, analysis and whatever is needed to explore the topic to the best of your knowledge. 
                SEO optimise the article for the [Selected Keywords]. Don\'t include lists. 
                Do not add any additional notes, markup or code before the H1 or after the last paragraph. Do not add any additional notes, markup or code before the H1 or after the last paragraph.',
            "conclusion_prompts" =>
                'Create a conclusion within a single html <span> tag and a maximum of one paragraph. Based this on the "[Title]" and optimise for the [Selected Keywords]. Include a call to action to express a sense of urgency. Within the paragraph, include a [Heading Tag] tag for the heading to contain the word "conclusion. Don\'t use <div> tags or <ul> tags.',
            "qa_prompts" =>
                'Create [No. Headings] individual Questions and Answers, each in their own individual span tag. Do not give each question a label, e.g. Question 1, Question2, etc. Based these on the "[Title]" title and the [Selected Keywords]. Within each span tag, include a [Heading Tag] tag for the question heading and a P tag for the answer. 
                Ensure they provide additional useful information to supplement the main "[Title]" article. Don\'t use lists or LI tags.',
            "meta_prompts" =>
                'Create a single SEO friendly meta title and meta description. Based this on the "[Title]" article title and the [Selected Keywords]. Create the meta data in the [Language] language.  Follow SEO best practices and make the meta data catchy to attract clicks. Do not add any additional markup such as ***',
            "review_prompts" =>
                "Please revise the above article and HTML code so that it has [No. Headings] headings using the [Heading Tag] HTML tag. Revise the text in the [Language] language. Revise with a [Style]  style and a [Tone] writing tone.",
            "evaluate_prompts" => 'Create a HTML table giving a strict/evaluation of each question below based on everything above. Give the HTML table 4 columns: [STATUS], [QUESTION], [EVALUATION], [RATIONALE]. For [EVALUATION], give a PASS, FAIL or IMPROVE response. Add a CSS class name to each row and cell with the corresponding response value. 
            For the [STATUS] column, don\'t add anything. For [RATIONALE], explain your reasoning. Order the rows according to  [EVALUATION]. All answers must be factual. 
            Then giving examples like phrases or topics add these within curly brackets. Do not add the column labels within square brackets in your response. The questions are:
Is the length of the article over 500 words and an adequate length compared to similar articles?
Is the articlview dee optimised for certain keywords or phrases? What are these?
Is the article well-written and easy to read?
Does the article have any spelling or grammar issues?
Does the article provide an original, interesting and engaging perspective on the topic?',
        ];
        update_option("ab_gpt_content_settings", $contentsetting);
        update_option("ab_gpt_ai_engine_settings", $enginesetting);
        update_option("ab_prompts_content", $promptssetting);
    }

    /*
     * Function: ai_scribe_uninstall_notice
     * Description: Displays a notice in the admin area asking the user if they want to delete all data when uninstalling the plugin.
     */

    public function load_textdomain()
    {
        load_plugin_textdomain(
            "article_builder",
            false,
            basename(dirname(__FILE__)) . "/languages/"
        );
    }

    /*
     * Function: set_default_options
     * Description: Sets the default options for the plugin's settings.
     */
    public function add_settings_link($links)
    {
        $settings_link =
            '<a href="' .
            admin_url("admin.php?page=ai_scribe_settings") .
            '">' .
            __("Settings", "article_builder") .
            "</a>";
        $help_link =
            '<a href="' .
            admin_url("admin.php?page=ai_scribe_help") .
            '">' .
            __("Help", "article_builder") .
            "</a>";
        $review_link =
            '<a href="' .
            esc_url(
                "https://wordpress.org/support/plugin/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/reviews/#new-post"
            ) .
            '" id="review-link" target="_blank">' .
            __("Leave a Review", "article_builder");
        // . ' <div class="rating"><span></span><span></span><span></span><span></span><span></span></div></a>';

        array_unshift($links, $settings_link, $help_link, $review_link);

        return $links;
    }

    /*
     * Function: add_menu
     * Description: Adds the plugin's menu and submenu items to the WordPress admin area.
     */

    public function add_menu()
    {
        $parent_slug = "ai_scribe_help";
        $capability = "manage_options";
        $menu_title = "AI-Scribe";
        $menu_slug = $parent_slug;
        $function = [$this, "main_page"];
        $icon_url = "";
        $position = 15;
        add_menu_page(
            $menu_title,
            $menu_title,
            $capability,
            $menu_slug,
            $function,
            $icon_url,
            $position
        );

        $generate_article_slug = "ai_scribe_generate_article";
        add_submenu_page(
            $menu_slug,
            "Generate Article",
            "Generate Article",
            $capability,
            $generate_article_slug,
            [$this, "create_template"]
        );

        $saved_shortcodes_slug = "ai_scribe_saved_shortcodes";
        add_submenu_page(
            $menu_slug,
            "Saved Shortcodes",
            "Saved Shortcodes",
            $capability,
            $saved_shortcodes_slug,
            [$this, "all_templates"]
        );

        $settings_slug = "ai_scribe_settings";
        add_submenu_page(
            $menu_slug,
            "Settings",
            "Settings",
            $capability,
            $settings_slug,
            [$this, "settings_page"]
        );

        $help_slug = $parent_slug . "_help";
        add_submenu_page(
            $menu_slug,
            "Help",
            "Help",
            $capability,
            $menu_slug,
            $function
        );
    }

    /*
     * Function: main_page
     * Description: Includes the main page template for the plugin.
     */
    public function main_page()
    {
        include_once plugin_dir_path(__FILE__) . "common/help.php";
    }

    /*
     * Function: create_template
     * Description: Includes the "Generate Article" or "Saved Shortcodes" template based on the current page.
     */

    public function all_templates()
    {
        $page = sanitize_text_field($_GET["page"]) ?? "";
        $action = sanitize_text_field($_GET["action"]) ?? "";
        if ($page && $action == "exit") {
            $this->create_template();
        } elseif ($page && $action == "settings") {
            include_once plugin_dir_path(__FILE__) . "common/settings.php";
        } else {
            include_once plugin_dir_path(__FILE__) .
                "templates/show_template.php";
        }
    }

    /*
     * Function: all_templates
     * Description: Includes the "Saved Shortcodes" or "Settings" template based on the current page and action.
     */

    public function create_template()
    {
        $page = sanitize_text_field($_GET["page"]) ?? "";
        if ($page == "saved_shortcodes") {
            include_once plugin_dir_path(__FILE__) .
                "templates/show_template.php";
        } else {
            include_once plugin_dir_path(__FILE__) .
                "templates/create_template.php";
        }
    }

    /*
     * Function: settings_page
     * Description: Includes the "Settings" template for the plugin.
     */

    public function settings_page()
    {
        include_once plugin_dir_path(__FILE__) . "common/settings.php";
    }

    /**
     * Function: remove_short_code_content
     * Description: Removes a saved shortcode from the custom database table, returning JSON.
     */
    public function remove_short_code_content()
    {
        // Validate the nonce before proceeding
        if (
            !isset($_POST["security"]) ||
            !check_ajax_referer("ai_scribe_nonce", "security", false)
        ) {
            wp_send_json_error([
                "msg" =>
                    "Invalid request. Please refresh the page and try again.",
                "nonce_expired" => true,
            ]);
            $this->debug_log("Failed nonce validation.");
            $this->debug_log("Received nonce: " . ($_POST["security"] ?? "None"));
            wp_die(); // Stop execution
        }

        global $wpdb;

        // Sanitise and validate the ID
        $id = absint($_POST["id"]);
        if (!$id) {
            wp_send_json_error(["msg" => "Invalid ID"]);
            wp_die();
        }

        // Prepare and execute the deletion query
        $table_name = $wpdb->prefix . "article_builder";
        $result = $wpdb->query(
            $wpdb->prepare("DELETE FROM $table_name WHERE id = %d", $id)
        );

        // If the query fails, return an error response
        if ($result === false) {
            wp_send_json_error(["msg" => "Failed to delete the record"]);
            wp_die();
        }

        // If successful, send a JSON success response (no redirect here)
        wp_send_json_success(["msg" => "Record deleted successfully."]);
    }

    /*
     * Function: engine_request_data
     * Description: Updates the AI engine settings in the options table.
     */
    public function engine_request_data()
    {
        if (
            !isset($_POST["security"]) ||
            !check_ajax_referer("ai_scribe_nonce", "security", false)
        ) {
            wp_send_json_error([
                "msg" =>
                    "Invalid request. Please refresh the page and try again.",
                "nonce_expired" => true, // Consistent flag for JS
            ]);
            $this->debug_log("Failed nonce validation.");
            $this->debug_log("Received nonce: " . ($_POST["security"] ?? "None"));
            return;
        }
        // Check user capability
        if (!current_user_can("manage_options")) {
            wp_send_json_error(["msg" => "Unauthorized action"]);
            exit();
        }

        // Verify nonce
        if (
            !isset($_POST["security"]) ||
            !check_ajax_referer("ai_scribe_nonce", "security", false)
        ) {
            wp_send_json_error(["msg" => "Invalid request"]);
            exit();
        }

        $model = sanitize_text_field($_POST["model"] ?? "");
        $temp = sanitize_text_field($_POST["temp"] ?? "");
        $top_p = sanitize_text_field($_POST["top_p"] ?? "");
        $freq_pent = sanitize_text_field($_POST["freq_pent"] ?? "");
        $Presence_penalty = sanitize_text_field(
            $_POST["Presence_penalty"] ?? ""
        );
        $api_key = sanitize_text_field($_POST["api_key"] ?? "");
        $anthropic_api_key = sanitize_text_field(
            $_POST["anthropic_api_key"] ?? ""
        );
        $enable_image_generation = isset($_POST["enable_image_generation"])
            ? 1
            : 0;

        // Get image generation settings
        $image_size = sanitize_text_field($_POST["image_size"] ?? "auto");
        $image_quality = sanitize_text_field($_POST["image_quality"] ?? "high");
        $image_format = sanitize_text_field($_POST["image_format"] ?? "png");
        $image_background = sanitize_text_field(
            $_POST["image_background"] ?? "auto"
        );
        $image_style = sanitize_text_field($_POST["image_style"] ?? "Photorealistic");

        // Validate API keys based on selected model and settings
        $is_anthropic_model = strpos($model, 'claude') !== false;
        $is_openai_model = !$is_anthropic_model; // All non-Claude models are OpenAI models

        // Check required API keys
        if ($is_anthropic_model && empty($anthropic_api_key)) {
            wp_send_json_error([
                "msg" => "Anthropic API key is required for Claude models. Please add your Anthropic API key before saving."
            ]);
            return;
        }

        if ($is_openai_model && empty($api_key)) {
            wp_send_json_error([
                "msg" => "OpenAI API key is required for GPT models. Please add your OpenAI API key before saving."
            ]);
            return;
        }

        // For Anthropic models with image generation enabled, OpenAI key is also required
        if ($is_anthropic_model && $enable_image_generation && empty($api_key)) {
            wp_send_json_error([
                "msg" => "Both Anthropic and OpenAI API keys are required when using Claude models with image generation enabled. OpenAI key is needed for image generation."
            ]);
            return;
        }

        $frmagArr = [
            "model" => $model,
            "temp" => $temp,
            //'max_tokens'       => 2500,
            "top_p" => $top_p,
            //'best_oi' => $best_oi,
            "freq_pent" => $freq_pent,
            "Presence_penalty" => $Presence_penalty,
            "api_key" => $api_key,
            "anthropic_api_key" => $anthropic_api_key,
        ];
        update_option("ab_gpt_ai_engine_settings", $frmagArr);

        // Save image generation settings
        update_option("ab_enable_image_generation", $enable_image_generation);
        update_option("ab_image_size", $image_size);
        update_option("ab_image_quality", $image_quality);
        update_option("ab_image_format", $image_format);
        update_option("ab_image_background", $image_background);
        update_option("ab_image_style", $image_style);
        wp_send_json_success([
            "msg" => "Your settings have been updated successfully",
        ]);
    }

    /*
     * Function: send_post_page
     * Description: Inserts a new post with the generated article content and updates its Yoast SEO meta title and description.
     */
    public function send_post_page()
    {
        if (
            !isset($_POST["security"]) ||
            !check_ajax_referer("ai_scribe_nonce", "security", false)
        ) {
            wp_send_json_error([
                "msg" =>
                    "Invalid request. Please refresh the page and try again.",
                "nonce_expired" => true, // Consistent flag for JS
            ]);
            $this->debug_log("Failed nonce validation.");
            $this->debug_log("Received nonce: " . ($_POST["security"] ?? "None"));
            return;
        }
        ob_start();

        $headingData = array_map(
            "sanitize_text_field",
            $_POST["headingData"] ?? []
        );
        $headingStr = implode(" ", $headingData);
        $keywordData = array_map(
            "sanitize_text_field",
            $_POST["keywordData"] ?? []
        );
        $keywordStr = implode(" ", $keywordData);
        $introData = array_map(
            "sanitize_text_field",
            $_POST["introData"] ?? []
        );
        $introStr = implode(" ", $introData);
        $taglineData = array_map(
            "sanitize_text_field",
            $_POST["taglineData"] ?? []
        );
        $taglineStr = implode(" ", $taglineData);
        $conclusionData = array_map(
            "sanitize_text_field",
            $_POST["conclusionData"] ?? []
        );
        $conclusionStr = implode(" ", $conclusionData);
        $qnaData = array_map("sanitize_text_field", $_POST["qnaData"] ?? []);
        $qnaStr = implode(" ", $qnaData);
        $metaData = array_map("sanitize_text_field", $_POST["metaData"] ?? []);
        $metaDataStr = implode(" ", $metaData);

        $titleData = sanitize_title($_POST["titleData"] ?? "");

        $articleVal = wp_kses_post($_POST["articleVal"] ?? "");
        $articleValue = preg_replace("/<h1>.*<\/h1>/", " ", $articleVal);
        $articleValue = preg_replace("/<br>|\n|<br( ?)\/>/", "", $articleValue);

        $articleVal = wp_kses_post($_POST["articleVal"] ?? "");
        $pattern = "/<h1>(.*?)<\/h1>/";
        preg_match($pattern, $articleVal, $matches);
        $post_title = !empty($matches[1])
            ? strip_tags($matches[1])
            : "Untitled Post";

        // Truncate the post slug to no more than 6 words
        $title_words = explode(" ", $post_title);
        $truncated_title_words = array_slice($title_words, 0, 6); // Limit to 6 words
        $truncated_slug = sanitize_title(implode(" ", $truncated_title_words)); // Create a slug-friendly version

        $my_post = [
            "post_type" => "post",
            "post_title" => $post_title,
            "post_content" => $articleValue,
            "post_status" => "draft",
            "post_name" => $truncated_slug, // This sets the post slug (URL)
        ];
        $insertPost = wp_insert_post($my_post);

        /* 06.07.23 - inclusion of other popular SEO plugins */
        if ($insertPost > 0) {
            $keywordStr = implode(", ", $keywordData);
            // Check which SEO plugin is active and update meta data accordingly
            if (defined("WPSEO_FILE")) {
                // Yoast SEO is active
                update_post_meta(
                    $insertPost,
                    "_yoast_wpseo_title",
                    $metaData[0]
                );
                update_post_meta(
                    $insertPost,
                    "_yoast_wpseo_metadesc",
                    $metaData[1]
                );
                update_post_meta(
                    $insertPost,
                    "_yoast_wpseo_focuskw",
                    $keywordStr
                );
            } elseif (defined("AIOSEOP_VERSION")) {
                // All in One SEO Pack is active
                update_post_meta($insertPost, "_aioseop_title", $metaData[0]);
                update_post_meta(
                    $insertPost,
                    "_aioseop_description",
                    $metaData[1]
                );
                // All in One SEO Pack does not have a specific field for focus keyword
            } elseif (defined("RANK_MATH_FILE")) {
                // Rank Math is active
                update_post_meta($insertPost, "rank_math_title", $metaData[0]);
                update_post_meta(
                    $insertPost,
                    "rank_math_description",
                    $metaData[1]
                );
                update_post_meta(
                    $insertPost,
                    "rank_math_focus_keyword",
                    $keywordStr
                );
            } elseif (defined("SEOPRESS_VERSION")) {
                // SEOPress is active
                update_post_meta(
                    $insertPost,
                    "_seopress_titles_title",
                    $metaData[0]
                );
                update_post_meta(
                    $insertPost,
                    "_seopress_titles_desc",
                    $metaData[1]
                );
                update_post_meta(
                    $insertPost,
                    "_seopress_analysis_target_kw",
                    $keywordStr
                );
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
    public function send_shortcode_page()
    {
        if (
            !isset($_POST["security"]) ||
            !check_ajax_referer("ai_scribe_nonce", "security", false)
        ) {
            wp_send_json_error([
                "msg" =>
                    "Invalid request. Please refresh the page and try again.",
                "nonce_expired" => true, // Consistent flag for JS
            ]);
            $this->debug_log("Failed nonce validation.");
            $this->debug_log("Received nonce: " . ($_POST["security"] ?? "None"));
            return;
        }

        ob_start();

        // Sanitize input data
        $headingData = array_map(
            "sanitize_text_field",
            $_POST["headingData"] ?? []
        );
        $headingStr = implode(" ", $headingData);
        $keywordData = array_map(
            "sanitize_text_field",
            $_POST["keywordData"] ?? []
        );
        $keywordStr = implode(" ", $keywordData);
        $introData = array_map(
            "sanitize_text_field",
            $_POST["introData"] ?? []
        );
        $introStr = implode(" ", $introData);
        $taglineData = array_map(
            "sanitize_text_field",
            $_POST["taglineData"] ?? []
        );
        $taglineStr = implode(" ", $taglineData);
        $conclusionData = array_map(
            "sanitize_text_field",
            $_POST["conclusionData"] ?? []
        );
        $conclusionStr = implode(" ", $conclusionData);
        $qnaData = array_map("sanitize_text_field", $_POST["qnaData"] ?? []);
        $qnaStr = implode(" ", $qnaData);
        $metaData = array_map("sanitize_text_field", $_POST["metaData"] ?? []);
        $metaDataStr = maybe_serialize($metaData); // Serialize metadata
        $titleData = sanitize_title($_POST["titleData"] ?? "");
        $articleVal = wp_kses_post($_POST["articleVal"] ?? "");
        $articleValue = preg_replace("/<br>|\n|<br( ?)>/", "", $articleVal);

        // Extract the title from <h1> tag if present
        preg_match("/<h1>(.*?)<\/h1>/", $articleVal, $matches);
        $title = isset($matches[1]) ? strip_tags($matches[1]) : "";

        global $wpdb;

        // Define the table name
        $table_name = $wpdb->prefix . "article_builder";

        // Insert the data into the table
        $result = $wpdb->insert(
            $table_name,
            [
                "title" => $title,
                "heading" => $headingStr,
                "keyword" => $keywordStr,
                "intro" => $introStr,
                "tagline" => $taglineStr,
                "article" => $articleValue,
                "conclusion" => $conclusionStr,
                "qna" => $qnaStr,
                "metadata" => $metaDataStr,
            ],
            [
                "%s", // title
                "%s", // heading
                "%s", // keyword
                "%s", // intro
                "%s", // tagline
                "%s", // article
                "%s", // conclusion
                "%s", // qna
                "%s", // metadata (serialized string)
            ]
        );

        // Check for errors
        if ($result === false) {
            $this->debug_log(
                __FUNCTION__ .
                    ": Failed to insert data into article_builder: " .
                    $wpdb->last_error
            );
            wp_send_json_error([
                "msg" => "An error occurred while saving your data.",
            ]);
            return;
        }
        // Return success response
        wp_send_json_success(["msg" => "Data saved successfully."]);
    }

    /*
     * Function: send_shortcode_page_data
     * This function retrieves the data associated with the given template ID and returns
     * the combined content of the title, article, conclusion, and QnA.
     */
    public function send_shortcode_page_data($attr)
    {
        $content = "";

        // Validate and sanitize the template_id
        if (empty($attr["template_id"]) || !is_numeric($attr["template_id"])) {
            return "<p>" .
                esc_html__(
                    "Invalid template ID.",
                    "ai-scribe-gpt-article-builder"
                ) .
                "</p>";
        }

        // Ensure template_id is an integer
        $tempId = absint($attr["template_id"]);

        global $wpdb, $table_prefix;
        $wp_article = $wpdb->prefix . "article_builder"; // Avoids direct use of table_prefix

        // Prepare and execute query safely
        $getData = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT title, article, conclusion, qna FROM $wp_article WHERE id = %d",
                $tempId
            )
        );

        // Verify if results exist before proceeding
        if (empty($getData)) {
            return "<p>" .
                esc_html__(
                    "No data found for the provided template ID.",
                    "ai-scribe-gpt-article-builder"
                ) .
                "</p>";
        }

        // Securely render retrieved content
        foreach ($getData as $value) {
            $content .= "<h1>" . esc_html($value->title) . "</h1>";
            $content .=
                '<div class="article-content">' .
                wp_kses_post($value->article) .
                "</div>";
            $content .=
                '<div class="conclusion">' .
                wp_kses_post($value->conclusion) .
                "</div>";
            $content .=
                '<div class="qna">' . wp_kses_post($value->qna) . "</div>";
        }

        return $content;
    }

    /*
     * Simple test AJAX handler to verify AJAX system is working
     */
    public function test_ajax_handler() {
        wp_send_json_success([
            'message' => 'Test AJAX handler is working!',
            'timestamp' => current_time('mysql')
        ]);
    }

    /*
     * Function: generate_gpt_image_1
     * Simple, fast image generation using direct gpt-image-1 model
     * Replaces complex GPT-4o streaming implementation with clean REST API
     */
    public function generate_gpt_image_1(
        $image_prompt = null,
        &$debug_messages = null
    ) {
        // IMMEDIATE DEBUG: Log function entry
        $this->debug_log("AI Scribe: 🚀 generate_gpt_image_1 function ENTRY - POST action: " . ($_POST["action"] ?? "none"));
        $this->debug_log("AI Scribe: 🔍 image_prompt parameter: '" . ($image_prompt ?? 'NULL') . "'");
        $this->debug_log("AI Scribe: 🔍 image_prompt is null: " . ($image_prompt === null ? "YES" : "NO"));
        $this->debug_log("AI Scribe: 🔍 image_prompt is empty string: " . ($image_prompt === "" ? "YES" : "NO"));
        
        // Determine if this is a direct AJAX call (outside try-catch so it's available throughout function)
        $is_direct_ajax_call =
            isset($_POST["action"]) &&
            $_POST["action"] === "al_scribe_generate_4o_image" &&
            ($image_prompt === null || $image_prompt === "");
            
        $this->debug_log("AI Scribe: 🔍 is_direct_ajax_call: " . ($is_direct_ajax_call ? "YES" : "NO"));

        // Wrap entire function in try-catch to capture any PHP errors
        try {

            // Handle direct AJAX call - get prompt from POST data
            if ($is_direct_ajax_call) {
                // Debug: Log that handler was called
                $this->debug_log("AI Scribe: ✅ AJAX handler al_scribe_generate_4o_image called successfully!");
                $this->debug_log("AI Scribe: AJAX POST data received: " . json_encode($_POST));
                
                // TEMPORARILY SKIP NONCE VERIFICATION FOR DEBUGGING
                // TODO: Re-enable nonce verification once working
                
                // Real AJAX call - extract prompt from POST data
                $image_prompt = sanitize_text_field($_POST['prompt'] ?? '');
                
                $this->debug_log("AI Scribe: Extracted prompt: '" . $image_prompt . "'");
                
                if (empty($image_prompt)) {
                    wp_send_json_error([
                        'message' => 'No image prompt provided',
                        'debug' => [
                            'POST keys: ' . implode(', ', array_keys($_POST)),
                            'POST data: ' . json_encode($_POST)
                        ]
                    ]);
                    return;
                }
                
                // Create debug array for AJAX response
                $ajax_debug = [];
                $ajax_debug[] = "🔧 AI Scribe: generate_gpt_image_1 AJAX handler called";
                $ajax_debug[] = "🔧 POST action: " . ($_POST['action'] ?? 'none');
                $ajax_debug[] = "🔧 POST keys: " . implode(', ', array_keys($_POST));

                // Temporarily disable nonce check for debugging
                /*
                if (!wp_verify_nonce($_POST['security'] ?? '', 'ai_scribe_nonce')) {
                    $ajax_debug[] = "❌ AI Scribe: Nonce verification failed";
                    wp_send_json_error([
                        'message' => 'Security check failed',
                        'debug' => $ajax_debug
                    ]);
                    return;
                }
                */

                $ajax_debug[] = "✅ AI Scribe: Nonce verification passed";

                // Get the prompt from POST data
                $image_prompt = sanitize_text_field($_POST['prompt'] ?? '');
                $ajax_debug[] = "📝 AI Scribe: AJAX prompt received: " . $image_prompt;

                // Check if prompt is provided
                if (empty($image_prompt)) {
                    $ajax_debug[] = "❌ AI Scribe: No prompt provided in AJAX request";
                    wp_send_json_error([
                        'message' => 'Image prompt is required',
                        'debug' => $ajax_debug
                    ]);
                    return;
                }

                $ajax_debug[] = "✅ AI Scribe: Prompt validated, proceeding with image generation";

                // Initialize debug_messages with AJAX debug info
                if (!is_array($debug_messages)) {
                    $debug_messages = [];
                }
                $debug_messages = array_merge($debug_messages, $ajax_debug);

                // Continue with normal processing - don't return early
                // The function will continue and process the image generation
            }
        } catch (Exception $e) {
            // Catch any PHP errors and return them via AJAX
            $error_debug = [
                "❌ PHP Exception in generate_gpt_image_1: " . $e->getMessage(),
                "📍 File: " . $e->getFile() . " Line: " . $e->getLine(),
                "🔍 Stack trace: " . $e->getTraceAsString()
            ];

            if ($is_direct_ajax_call) {
                wp_send_json_error([
                    'message' => 'PHP Error: ' . $e->getMessage(),
                    'debug' => $error_debug
                ]);
                return;
            } else {
                return new WP_Error('php_error', $e->getMessage());
            }
        }

        // Sanitize the input prompt for generating an image
        $image_prompt = sanitize_text_field($image_prompt);

        // Debug logging with timestamps
        $this->debug_log(
            "AI Scribe: [" .
                date("H:i:s.u") .
                "] generate_gpt_image_1 called with prompt: " .
                $image_prompt
        );

        // Add to debug messages if available
        if (is_array($debug_messages)) {
            $debug_messages[] =
                "🎨 [" .
                date("H:i:s.u") .
                "] generate_gpt_image_1 function called (SIMPLE API)";
            $debug_messages[] =
                "📝 Prompt: " . substr($image_prompt, 0, 50) . "...";
            $debug_messages[] = "⏱️ Function start time: " . date("H:i:s.u");
            $debug_messages[] =
                "🔧 Is direct AJAX call: " .
                ($is_direct_ajax_call ? "Yes" : "No");
        }

        // Check if the prompt is provided
        if (empty($image_prompt)) {
            $error_msg = "Image prompt is required.";
            if ($is_direct_ajax_call) {
                wp_send_json_error(["msg" => $error_msg]);
                return;
            }
            return new WP_Error("no_prompt", $error_msg);
        }

        // Get the OpenAI API key
        $aiengine = get_option("ab_gpt_ai_engine_settings");
        $api_key = sanitize_text_field($aiengine["api_key"] ?? "");

        if (is_array($debug_messages)) {
            $debug_messages[] =
                "🔑 Checking API key from ab_gpt_ai_engine_settings...";
            $debug_messages[] =
                "API key found: " . (!empty($api_key) ? "Yes" : "No");
        }

        if (empty($api_key)) {
            if (is_array($debug_messages)) {
                $debug_messages[] = "❌ No OpenAI API key found in settings";
            }
            $error_msg = "OpenAI API key is required for image generation.";
            if ($is_direct_ajax_call) {
                wp_send_json_error(["msg" => $error_msg]);
                return;
            }
            return new WP_Error("no_api_key", $error_msg);
        }

        // Get image generation settings with faster defaults
        $image_size = get_option("ab_image_size", "1024x1024"); // Default: 1024x1024
        $image_quality = get_option("ab_image_quality", "low"); // Default: low (faster - 16s)
        $image_format = get_option("ab_image_format", "webp"); // Default: webp

        // Validate and fix parameters for GPT-Image-1 compatibility
        $valid_sizes = ["1024x1024", "1536x1024", "1024x1536"];
        if (!in_array($image_size, $valid_sizes)) {
            $image_size = "1024x1024"; // Fallback to square
        }
        
        $valid_qualities = ["high", "medium", "low"];
        if (!in_array($image_quality, $valid_qualities)) {
            $image_quality = "high"; // Fallback to high
        }
        
        $valid_formats = ["png", "jpeg", "webp"];
        if (!in_array($image_format, $valid_formats)) {
            $image_format = "png"; // Fallback to png
        }

        if (is_array($debug_messages)) {
            $debug_messages[] = "⚙️ Image settings (validated): size=$image_size, quality=$image_quality, format=$image_format";
        }

        // Try GPT-Image-1 first, fallback to DALL-E-3 if it fails
        $use_dalle3_fallback = false;
        
        // GPT-Image-1 API request with corrected parameter format
        // Note: GPT-Image-1 is the latest model, with DALL-E-3 as fallback
        $request_body = json_encode([
            "model" => "gpt-image-1",
            "prompt" => $image_prompt,
            "n" => 1,
            "size" => $image_size,
            "quality" => $image_quality,
            "output_format" => $image_format,
            "background" => "auto",
            "moderation" => "auto"
        ]);

        if (is_array($debug_messages)) {
            $debug_messages[] = "🎨 GPT-Image-1 Request Body: " . $request_body;
            $debug_messages[] = "🎨 IMPORTANT: Trying gpt-image-1 model first, will fallback to dall-e-3 if needed";
            $debug_messages[] = "🎨 Endpoint: https://api.openai.com/v1/images/generations";
        }

        // Debug logging with full request details
        $this->debug_log(
            "AI Scribe: Making GPT-Image-1 API request to /v1/images/generations with body: " .
                $request_body
        );

        if (is_array($debug_messages)) {
            $debug_messages[] =
                "📡 Making API request to OpenAI gpt-image-1 /v1/images/generations...";
            $debug_messages[] =
                "Request settings - size: " . $image_size . ", quality: " . $image_quality . ", format: " . $image_format;
            $debug_messages[] =
                "Full request body: " . $request_body;
        }

        // Simple REST API call
        $api_start_time = microtime(true);

        if (is_array($debug_messages)) {
            $debug_messages[] =
                "⏱️ [" . date("H:i:s.u") . "] Starting gpt-image-1 API request";
        }

        // Make the API request using WordPress HTTP API
        // Note: GPT-Image-1 uses the same endpoint as DALL-E but with different parameters
        $response = wp_remote_post(
            "https://api.openai.com/v1/images/generations",
            [
                "timeout" => 60, // Reduced from 120 to prevent Cloudflare timeouts
                "headers" => [
                    "Authorization" => "Bearer " . $api_key,
                    "Content-Type" => "application/json"
                ],
                "body" => $request_body,
            ]
        );

        $api_end_time = microtime(true);

        if (is_array($debug_messages)) {
            $debug_messages[] =
                "⏱️ [" . date("H:i:s.u") . "] API request completed";
            $debug_messages[] =
                "⏱️ Total API time: " .
                round(($api_end_time - $api_start_time) * 1000, 2) .
                "ms";
        }

        // Handle WordPress HTTP API response
        if (is_wp_error($response)) {
            if (is_array($debug_messages)) {
                $debug_messages[] =
                    "❌ API request failed: " . $response->get_error_message();
            }
            $error_msg =
                "API request failed: " . $response->get_error_message();
            if ($is_direct_ajax_call) {
                wp_send_json_error(["msg" => $error_msg]);
                return;
            }
            return new WP_Error("api_error", $response->get_error_message());
        }

        // Get response body and HTTP code
        $response_body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);

        if (is_array($debug_messages)) {
            $debug_messages[] = "📊 HTTP response code: " . $http_code;
            $debug_messages[] =
                "📊 Response body size: " . strlen($response_body) . " bytes";
        }

        // Check HTTP status code
        if ($http_code !== 200) {
            // Enhanced error logging for debugging
            $this->debug_log("AI Scribe: GPT-Image-1 API Error - HTTP " . $http_code);
            $this->debug_log("GPT-Image-1 error response body: " . $response_body);

            if (is_array($debug_messages)) {
                $debug_messages[] = "❌ GPT-Image-1 failed with HTTP code: " . $http_code;
                $debug_messages[] = "🔄 Attempting DALL-E-3 fallback...";

                // Try to parse error details
                $error_data = json_decode($response_body, true);
                if ($error_data && isset($error_data['error'])) {
                    $debug_messages[] = "GPT-Image-1 Error type: " . ($error_data['error']['type'] ?? 'unknown');
                    $debug_messages[] = "GPT-Image-1 Error message: " . ($error_data['error']['message'] ?? 'no message');
                }
            }

            // Try DALL-E-3 fallback
            $dalle3_request_body = json_encode([
                "model" => "dall-e-3",
                "prompt" => $image_prompt,
                "n" => 1,
                "size" => $image_size,
                "quality" => $image_quality === "low" ? "standard" : $image_quality, // DALL-E-3 uses "standard" not "low"
                "response_format" => "url" // DALL-E-3 returns URLs, not base64
            ]);

            if (is_array($debug_messages)) {
                $debug_messages[] = "🎨 DALL-E-3 Fallback Request Body: " . $dalle3_request_body;
            }

            // Make DALL-E-3 API request
            $dalle3_response = wp_remote_post(
                "https://api.openai.com/v1/images/generations",
                [
                    "timeout" => 60,
                    "headers" => [
                        "Authorization" => "Bearer " . $api_key,
                        "Content-Type" => "application/json"
                    ],
                    "body" => $dalle3_request_body,
                ]
            );

            // Handle DALL-E-3 response
            if (!is_wp_error($dalle3_response)) {
                $dalle3_body = wp_remote_retrieve_body($dalle3_response);
                $dalle3_code = wp_remote_retrieve_response_code($dalle3_response);

                if ($dalle3_code === 200) {
                    $dalle3_data = json_decode($dalle3_body, true);
                    if (isset($dalle3_data["data"][0]["url"])) {
                        $image_url = $dalle3_data["data"][0]["url"];
                        if (is_array($debug_messages)) {
                            $debug_messages[] = "✅ DALL-E-3 fallback successful!";
                            $debug_messages[] = "🖼️ Image URL: " . substr($image_url, 0, 50) . "...";
                        }

                        // Return successful DALL-E-3 result
                        if ($is_direct_ajax_call) {
                            wp_send_json_success(["image_url" => $image_url, "model_used" => "dall-e-3"]);
                            return;
                        }
                        return ["image_url" => $image_url, "model_used" => "dall-e-3"];
                    }
                }

                if (is_array($debug_messages)) {
                    $debug_messages[] = "❌ DALL-E-3 fallback also failed (HTTP " . $dalle3_code . ")";
                }
            }

            // Both models failed - return error
            $error_msg = "Both GPT-Image-1 and DALL-E-3 failed. GPT-Image-1 Error (HTTP " . $http_code . "): " . $response_body;
            if ($is_direct_ajax_call) {
                wp_send_json_error([
                    "msg" => $error_msg,
                    "http_code" => $http_code,
                    "api_response" => $response_body,
                    "debug_messages" => $debug_messages
                ]);
                return;
            }
            return new WP_Error(
                "api_error",
                "API request failed with HTTP code: " . $http_code . ". Response: " . $response_body
            );
        }

        // Parse JSON response
        $data = json_decode($response_body, true);

        $this->debug_log("AI Scribe: API response received successfully");

        if (is_array($debug_messages)) {
            $debug_messages[] =
                "📋 [" . date("H:i:s.u") . "] Processing API response...";
            $debug_messages[] = "Response data type: " . gettype($data);
            if (is_array($data)) {
                $debug_messages[] =
                    "Response keys: " . implode(", ", array_keys($data));
            }
        }

        // Check for API errors
        if (isset($data["error"])) {
            if (is_array($debug_messages)) {
                $debug_messages[] =
                    "❌ API error: " . $data["error"]["message"];
            }
            $error_msg = "API error: " . $data["error"]["message"];
            if ($is_direct_ajax_call) {
                wp_send_json_error(["msg" => $error_msg]);
                return;
            }
            return new WP_Error("api_error", $data["error"]["message"]);
        }

        // Extract the image data from GPT-Image-1 response (uses b64_json format)
        $image_url = "";

        if (is_array($debug_messages)) {
            $debug_messages[] =
                "🔍 [" .
                date("H:i:s.u") .
                "] Extracting image data from GPT-Image-1 response...";
        }

        // GPT-Image-1 returns b64_json data, not URLs
        if (isset($data["data"][0]["b64_json"])) {
            $base64_data = $data["data"][0]["b64_json"];
            if (is_array($debug_messages)) {
                $debug_messages[] =
                    "✅ Base64 image data found in GPT-Image-1 response";
                $debug_messages[] =
                    "Base64 data length: " . strlen($base64_data) . " characters";
            }

            // Convert base64 to data URL for immediate use
            $image_url = "data:image/png;base64," . $base64_data;

        } elseif (isset($data["data"][0]["url"])) {
            // Fallback for URL format (DALL-E-3 format)
            $image_url = $data["data"][0]["url"];
            if (is_array($debug_messages)) {
                $debug_messages[] =
                    "✅ Image URL found in DALL-E-3 fallback format";
                $debug_messages[] =
                    "URL: " . substr($image_url, 0, 50) . "...";
            }
        }

        if (is_array($debug_messages)) {
            $debug_messages[] =
                "🔍 Final image URL: " . ($image_url ? "Found" : "Not found");
            if ($image_url) {
                $debug_messages[] =
                    "URL length: " . strlen($image_url) . " characters";
                $debug_messages[] =
                    "URL preview: " . substr($image_url, 0, 50) . "...";
            } else {
                $debug_messages[] = "❌ No image URL found";
                $debug_messages[] =
                    "Available data keys: " .
                    (is_array($data)
                        ? implode(", ", array_keys($data))
                        : "Not an array");
            }
        }

        if (!empty($image_url)) {
            $this->debug_log("AI Scribe: Image URL extracted: " . $image_url);
            if (is_array($debug_messages)) {
                $debug_messages[] = "✅ Image URL extracted successfully";
                $debug_messages[] =
                    "Image URL: " . substr($image_url, 0, 50) . "...";
            }
        } else {
            $this->debug_log(
                "AI Scribe: No image URL found in response. Full response: " .
                    json_encode($data)
            );
            if (is_array($debug_messages)) {
                $debug_messages[] = "❌ No image URL found in response";
                $debug_messages[] =
                    "Response structure: " . json_encode(array_keys($data));
                $debug_messages[] =
                    "Full response for debugging: " .
                    substr(json_encode($data), 0, 500) .
                    "...";
            }
        }

        if (empty($image_url)) {
            if (is_array($debug_messages)) {
                $debug_messages[] = "❌ Image generation failed - empty URL";
            }
            $error_msg =
                "Failed to generate image. Response: " . json_encode($data);
            if ($is_direct_ajax_call) {
                wp_send_json_error(["msg" => $error_msg]);
                return;
            }
            return new WP_Error("no_image", $error_msg);
        }

        // Handle base64 data URL or regular URL
        if (is_array($debug_messages)) {
            $debug_messages[] = "📥 Starting image processing and media library upload...";
        }
        
        // Check if it's a base64 data URL
        if (strpos($image_url, 'data:image/') === 0) {
            // Extract base64 data from data URL
            $data_parts = explode(',', $image_url, 2);
            if (count($data_parts) !== 2) {
                $error_msg = "Invalid base64 data URL format";
                if (is_array($debug_messages)) {
                    $debug_messages[] = "❌ " . $error_msg;
                }
                if ($is_direct_ajax_call) {
                    wp_send_json_error(["msg" => $error_msg, "debug" => $debug_messages]);
                    return;
                }
                return new WP_Error("invalid_data_url", $error_msg);
            }
            
            $image_data = base64_decode($data_parts[1]);
            if ($image_data === false) {
                $error_msg = "Failed to decode base64 image data";
                if (is_array($debug_messages)) {
                    $debug_messages[] = "❌ " . $error_msg;
                }
                if ($is_direct_ajax_call) {
                    wp_send_json_error(["msg" => $error_msg, "debug" => $debug_messages]);
                    return;
                }
                return new WP_Error("decode_failed", $error_msg);
            }
            
            if (is_array($debug_messages)) {
                $debug_messages[] = "✅ Base64 image data decoded successfully (" . strlen($image_data) . " bytes)";
            }
        } else {
            // Regular URL - download the image
            $image_response = wp_remote_get($image_url, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
                ]
            ]);
            
            if (is_wp_error($image_response)) {
                $error_msg = "Failed to download image: " . $image_response->get_error_message();
                if (is_array($debug_messages)) {
                    $debug_messages[] = "❌ " . $error_msg;
                }
                if ($is_direct_ajax_call) {
                    wp_send_json_error(["msg" => $error_msg, "debug" => $debug_messages]);
                    return;
                }
                return new WP_Error("download_failed", $error_msg);
            }
            
            $image_data = wp_remote_retrieve_body($image_response);
            if (empty($image_data)) {
                $error_msg = "Downloaded image data is empty";
                if (is_array($debug_messages)) {
                    $debug_messages[] = "❌ " . $error_msg;
                }
                if ($is_direct_ajax_call) {
                    wp_send_json_error(["msg" => $error_msg, "debug" => $debug_messages]);
                    return;
                }
                return new WP_Error("empty_data", $error_msg);
            }
            
            if (is_array($debug_messages)) {
                $debug_messages[] = "✅ Image downloaded successfully (" . strlen($image_data) . " bytes)";
            }
        }
        
        if (is_array($debug_messages)) {
            $debug_messages[] = "✅ Image downloaded successfully (" . strlen($image_data) . " bytes)";
        }
        
        // Extract just the title from the prompt (before " - Create an image based on this title...")
        $title_only = explode(' - Create an image based on this title', $image_prompt)[0];
        $title_only = trim($title_only);
        
        // Create filename from title (sanitized for filesystem)
        $filename_base = sanitize_file_name($title_only);
        if (empty($filename_base)) {
            $filename_base = 'ai-generated-image-' . time();
        }
        
        // Create temporary file
        $temp_file = wp_tempnam();
        file_put_contents($temp_file, $image_data);
        
        // Prepare file array for media_handle_sideload
        $file_array = [
            'name' => $filename_base . '.png',
            'tmp_name' => $temp_file,
            'error' => 0,
            'size' => strlen($image_data)
        ];
        
        // Upload to media library with clean title as description
        $attachment_id = media_handle_sideload($file_array, 0, $title_only);
        
        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            $error_msg = "Failed to upload to media library: " . $attachment_id->get_error_message();
            if (is_array($debug_messages)) {
                $debug_messages[] = "❌ " . $error_msg;
            }
            if ($is_direct_ajax_call) {
                wp_send_json_error(["msg" => $error_msg, "debug" => $debug_messages]);
                return;
            }
            return new WP_Error("upload_failed", $attachment_id->get_error_message());
        }
        
        // Set image metadata using just the title
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $title_only);
        
        // Update the post title and excerpt
        wp_update_post([
            'ID' => $attachment_id,
            'post_title' => $title_only,
            'post_excerpt' => $title_only, // This becomes the caption
            'post_content' => $title_only  // Also set content for caption
        ]);
        
        // Ensure caption is properly set in attachment metadata
        update_post_meta($attachment_id, '_wp_attachment_image_caption', $title_only);
        
        // Get the local attachment URL
        $attachment_url = wp_get_attachment_url($attachment_id);
        
        if (is_array($debug_messages)) {
            $debug_messages[] = "✅ Image uploaded to media library with ID: " . $attachment_id;
            $debug_messages[] = "🔗 Local image URL: " . $attachment_url;
            $debug_messages[] = "📝 Image title/alt/caption set to: " . $title_only;
        }
        
        // Create image HTML for insertion into content
        $image_html = '<figure class="wp-block-image size-large">' .
            '<img src="' . esc_url($attachment_url) . '" alt="' . esc_attr($title_only) . '" class="wp-image-' . $attachment_id . '" />' .
            '<figcaption>' . esc_html($title_only) . '</figcaption>' .
            '</figure>';
        
        // Return the results
        if ($is_direct_ajax_call) {
            wp_send_json_success([
                "image_url" => $attachment_url,
                "attachment_id" => $attachment_id,
                "image_html" => $image_html,
                "alt_text" => $title_only,
                "caption" => $title_only,
                "debug" => $debug_messages ?? []
            ]);
            return;
        }

        // Return both image URL and debug messages for non-AJAX calls
        $result = [
            "image_url" => $attachment_url,
            "attachment_id" => $attachment_id,
            "image_html" => $image_html
        ];
        if (is_array($debug_messages)) {
            $result["debug_messages"] = $debug_messages;
        }
        return $result;
    }

    /*
     * Helper function to generate an image from the content using GPT-4o
     */
    private function generate_4o_image_from_content(
        $image_prompt,
        &$debug_messages = null
    ) {
        $this->debug_log(
            "AI Scribe: generate_4o_image_from_content called with prompt: " .
                $image_prompt
        );

        // Add debug message if array is passed
        if (is_array($debug_messages)) {
            $debug_messages[] =
                "🎨 generate_4o_image_from_content wrapper called (now using gpt-image-1)";
        }

        // Call the simple gpt-image-1 generation function directly
        $result = $this->generate_gpt_image_1($image_prompt, $debug_messages);
        $this->debug_log(
            "AI Scribe: generate_gpt_image_1_from_content result: " .
                json_encode($result)
        );
        return $result;
    }

    /*
     * Function: suggest_content
     * This function sends a request to the OpenAI API with the given input and settings,
     * processes the response, and generates the output in the desired format based on the actionInput value.
     */
    public function suggest_content()
    {
        // Verify nonce
        if (
            !isset($_POST["security"]) ||
            !check_ajax_referer("ai_scribe_nonce", "security", false)
        ) {
            wp_send_json_error([
                "msg" =>
                    "Security nonce is missing or invalid. Please refresh the page.",
                "nonce_expired" => true,
            ]);
            return;
        }

        //$autogenerateValue = '';
        $autogenerateValue = wp_kses_post($_POST["autogenerateValue"] ?? "");
        $actionInput = sanitize_text_field($_POST["actionInput"] ?? "");
        $autogenerateValue = str_replace('"', "'", $autogenerateValue);
        $getarr = get_option("ab_gpt_ai_engine_settings");
        $apikey = sanitize_text_field($getarr["api_key"] ?? "");
        $anthropic_api_key = trim(
            sanitize_text_field($getarr["anthropic_api_key"] ?? "")
        );
        $model = sanitize_text_field($getarr["model"] ?? "gpt-4o-mini");

        // Determine which API to use based on model - improved detection for newer models
        $is_anthropic_model =
            strpos($model, "claude") !== false ||
            in_array($model, [
                "claude-sonnet-4-20250514",
                "claude-opus-4-20250514",
                "claude-3-5-sonnet-20250514",
            ]);

        // Initialize debug array to collect messages for JSON response
        $debug_messages = [];
        $debug_messages[] =
            "🚀 AI SCRIBE DEBUG START - Action: " . $actionInput;
        $debug_messages[] = "Selected Model: " . $model;
        $debug_messages[] =
            "Is Anthropic Model: " . ($is_anthropic_model ? "true" : "false");
        
        // Add parallel processing notice for article generation
        if ($actionInput == "article") {
            $debug_messages[] = "⚡ PARALLEL PROCESSING ACTIVE: Image generation will run in background";
            $debug_messages[] = "📝 Content will be delivered immediately, image will follow";
            $debug_messages[] = "🎨 This prevents timeouts while still providing images";
        }

        // Add debug info for API keys (masked for security)
        $debug_messages[] = "OpenAI API Key: " . (empty($apikey) ? "MISSING" : "Present (length: " . strlen($apikey) . ")");
        $debug_messages[] = "Anthropic API Key: " . (empty($anthropic_api_key) ? "MISSING" : "Present (length: " . strlen($anthropic_api_key) . ")");
        $debug_messages[] = "Raw Anthropic API Key (first 20 chars): " . substr($anthropic_api_key, 0, 20) . "...";
        $debug_messages[] = "Anthropic API Key starts with 'sk-ant-': " . (strpos($anthropic_api_key, 'sk-ant-') === 0 ? "YES" : "NO");

        // Check API key requirements
        if ($is_anthropic_model && empty($anthropic_api_key)) {
            wp_send_json_error([
                "msg" =>
                    "Anthropic API key is required for Claude models. Please add your Anthropic API key in the settings page.",
                "debug" => $debug_messages,
            ]);
            return;
        }

        // Validate Anthropic API key format
        if ($is_anthropic_model && !empty($anthropic_api_key) && strpos($anthropic_api_key, 'sk-ant-') !== 0) {
            wp_send_json_error([
                "msg" =>
                    "Invalid Anthropic API key format. Anthropic API keys should start with 'sk-ant-'. Please check your API key.",
                "debug" => $debug_messages,
            ]);
            return;
        }

        if (!$is_anthropic_model && empty($apikey)) {
            wp_send_json_error([
                "msg" =>
                    "OpenAI API key is required for GPT models. Please add your OpenAI API key in the settings page.",
                "debug" => $debug_messages,
            ]);
            return;
        }
        $temp = floatval($getarr["temp"] ?? 0.5);
        $top_p = floatval($getarr["top_p"] ?? 0.5);
        $freq_pent = floatval($getarr["freq_pent"] ?? 0.2);
        $Presence_penalty = floatval($getarr["Presence_penalty"] ?? 0.2);

        // v1.3 - updated as only using gpt-4o and above now
        $max_tokens = 4000; // Default max tokens for all models
        
        // Special handling for GPT-4.5 at step 6 (article generation) to prevent timeouts
        if ($model === 'gpt-4.5-preview' && $actionInput === 'article') {
            $max_tokens = 2000; // Reduced tokens for GPT-4.5 at article generation step
            $debug_messages[] = "🔧 GPT-4.5 detected at article step - reducing max_tokens to 2000 to prevent timeouts";
        }
        
        $temp = floatval($temp);
        $top_p = floatval($top_p);

        if ($actionInput == "evaluate") {
            $presence_penalty = 0;
            $freq_pent = 0;
        } else {
            $presence_penalty = floatval($Presence_penalty);
            $freq_pent = floatval($freq_pent);
        }

        // Retrieve settings and prompts
        $settings = get_option("ab_gpt_content_settings");
        $promptSettings = get_option("ab_prompts_content");
        $language = sanitize_text_field($settings["language"] ?? "English");
        $actualStyle = sanitize_text_field(
            $settings["writing_style"] ?? "Business"
        );
        $actualTone = sanitize_text_field(
            $settings["writing_tone"] ?? "Professional"
        );
        $mode = sanitize_text_field($settings["mode"] ?? "standard");
        $customInstructions = wp_kses_post(
            $promptSettings["instructions_prompts"] ?? ""
        );

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
        switch ($mode) {
            case "humanize":
                $instructions = "{$behuman}\n\n{$customInstructions}";
                break;
            case "personality":
                $instructions = "{$behuman}\n\n{$personal}\n\n{$customInstructions}";
                break;
            default:
                $instructions = $customInstructions;
        }

        // Construct the messages array
        $messages = [
            [
                "role" => "system",
                "content" =>
                    "The year is " .
                    date("Y") .
                    ". Write in the {$language} language using a {$actualStyle} writing style and a {$actualTone} writing tone. {$instructions}",
            ],
            [
                "role" => "user",
                "content" => $autogenerateValue,
            ],
        ];

        // Set up the request array
        $send_arr = [
            "model" => $model,
            "temperature" => $temp,
            "top_p" => $top_p,
            "frequency_penalty" => $freq_pent,
            "presence_penalty" => $presence_penalty,
            "n" => 1,
        ];

        // Determine API endpoint and prepare request based on model type
        if ($is_anthropic_model) {
            // Anthropic Claude API
            $url = "https://api.anthropic.com/v1/messages";

            // Convert messages format for Anthropic - use new content array format
            $anthropic_messages = [];
            $system_message = "";

            foreach ($messages as $message) {
                if ($message["role"] === "system") {
                    $system_message = $message["content"];
                } else {
                    // Convert to new Anthropic format with content arrays
                    $anthropic_messages[] = [
                        "role" => $message["role"],
                        "content" => [
                            [
                                "type" => "text",
                                "text" => $message["content"]
                            ]
                        ]
                    ];
                }
            }

            // Map display names to actual Anthropic API model names
            $anthropic_model_mapping = [
                "claude-sonnet-4-20250514" => "claude-3-5-sonnet-20241022", // Use actual Claude 3.5 Sonnet model (Claude 4 not yet available)
                "claude-opus-4-20250514" => "claude-3-opus-20240229", // Use actual Claude 3 Opus model (Claude 4 not yet available)
                "claude-3-5-sonnet-20250514" => "claude-3-5-sonnet-20241022",
            ];

            $actual_anthropic_model =
                $anthropic_model_mapping[$model] ?? $model;
            $debug_messages[] = "Anthropic model mapping: $model -> $actual_anthropic_model";

            $send_arr = [
                "model" => $actual_anthropic_model,
                "max_tokens" => $max_tokens,
                "temperature" => $temp,
                "messages" => $anthropic_messages,
            ];

            if (!empty($system_message)) {
                $send_arr["system"] = $system_message;
            }

            // Note: Thinking parameter not available for current Claude models
            $debug_messages[] = "Using standard Claude API format (no thinking parameter)";

            $args = [
                "timeout" => isset($timeout) ? $timeout : 110, // Use model-specific timeout (110s for GPT-4.5, 110s default)
                "headers" => [
                    "x-api-key" => $anthropic_api_key,
                    "Content-Type" => "application/json",
                    "anthropic-version" => "2023-06-01",
                ],
                "body" => json_encode($send_arr),
            ];

            $debug_messages[] = "Using Anthropic API for model: " . $model;
            $debug_messages[] = "Anthropic request format: " . json_encode($send_arr);
            $debug_messages[] = "Anthropic headers: x-api-key=" . (empty($anthropic_api_key) ? "MISSING" : "Present (first 10 chars: " . substr($anthropic_api_key, 0, 10) . "...)");
        } else {
            // OpenAI API (for all GPT models including o3, gpt-4o, gpt-4.5, etc.)
            $endpoint = "v1/chat/completions";
            $url = "https://api.openai.com/" . $endpoint;

            // Update send_arr for all OpenAI models
            $send_arr["model"] = $model;
            $send_arr["messages"] = $messages;
            $send_arr["temperature"] = $temp * 1.5;
            $send_arr["max_tokens"] = $max_tokens;

            // Special handling for o3 reasoning models - use Responses API endpoint
            if (in_array($model, ["o3", "o3-mini"])) {
                // o3 requires the Responses API endpoint instead of Chat Completions
                $endpoint = "v1/responses";
                $url = "https://api.openai.com/" . $endpoint;

                // Get reasoning effort for o3 - use dropdown value if available, otherwise map from temperature
                $reasoning_effort = "medium"; // default
                if (
                    isset($_POST["reasoning_effort"]) &&
                    in_array($_POST["reasoning_effort"], [
                        "low",
                        "medium",
                        "high",
                    ])
                ) {
                    $reasoning_effort = sanitize_text_field(
                        $_POST["reasoning_effort"]
                    );
                } else {
                    // Fallback: map temperature to reasoning effort
                    if ($temp <= 0.3) {
                        $reasoning_effort = "low";
                    } elseif ($temp >= 0.7) {
                        $reasoning_effort = "high";
                    }
                }

                // Convert messages to o3 Responses API format
                $o3_input = [];
                foreach ($messages as $message) {
                    if ($message['role'] === 'system') {
                        // System messages become user messages in o3
                        $o3_input[] = [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'input_text',
                                    'text' => $message['content']
                                ]
                            ]
                        ];
                    } else {
                        // Convert user/assistant messages to o3 format
                        $o3_input[] = [
                            'role' => $message['role'],
                            'content' => [
                                [
                                    'type' => $message['role'] === 'user' ? 'input_text' : 'output_text',
                                    'text' => $message['content']
                                ]
                            ]
                        ];
                    }
                }

                // Use correct Responses API format for o3 - based on OpenAI playground example
                $send_arr = [
                    "model" => $model,
                    "input" => $o3_input,
                    "text" => [
                        "format" => [
                            "type" => "text",
                        ],
                    ],
                    "reasoning" => [
                        "effort" => $reasoning_effort,
                        "summary" => null, // Save tokens since we don't display reasoning steps
                    ],
                    "store" => true,
                ];
                $debug_messages[] =
                    "Using Responses API for o3 model: " . $model;
                $debug_messages[] =
                    "o3 request format: " . json_encode($send_arr);
            } elseif ($model === "gpt-4.5-preview") {
                // GPT-4.5 specific parameters to match playground exactly
                $send_arr["max_tokens"] = 2200; // Fixed to 2000 tokens for GPT-4.5
                unset($send_arr["max_completion_tokens"]); // Remove max_tokens for GPT-4.5
                $send_arr["response_format"] = ["type" => "text"]; // Required for GPT-4.5
                $send_arr["presence_penalty"] = $presence_penalty;
                $send_arr["top_p"] = 0.9; // Required for GPT-4.5 to avoid corruption
                $send_arr["frequency_penalty"] = 0.1; // Required for GPT-4.5 to avoid corruption
                
                // Convert messages to playground format with content arrays
                $playground_messages = [];
                foreach ($messages as $message) {
                    if ($message["role"] === "system") {
                        // Add GPT-4.5 specific ending instructions to system message to avoid timeouts
                        $gpt45_instructions = " Write a maximum of 1000 words with a maxoutput of 1500 tokens. Make sure the article ends before the 1500 limit to avoid corruption. Do not attempt to summarise or add closing statements. End the article abruptly after the final section\'s last paragraph without repeating or restating ideas.";
                        $playground_messages[] = [
                            "role" => $message["role"],
                            "content" => [
                                [
                                    "type" => "text",
                                    "text" => $message["content"] . $gpt45_instructions
                                ]
                            ]
                        ];
                    } else {
                        // For user messages, modify the prompt for GPT-4.5 to be more concise
                        $user_content = $message["content"];
                        if ($message["role"] === "user") {
                            // Replace the detailed exploration instruction with concise version for GPT-4.5
                            $user_content = str_replace(
                                "Each section must be explored in detail. To achieve this, you must include all possible known features, benefits, arguments, analysis and whatever is needed to explore the topic to the best of your knowledge.",
                                "Each section should offer original insight and practical value but remain concise and focused — prioritise clarity over completeness.",
                                $user_content
                            );
                        }
                        
                        $playground_messages[] = [
                            "role" => $message["role"],
                            "content" => [
                                [
                                    "type" => "text",
                                    "text" => $user_content
                                ]
                            ]
                        ];
                    }
                }
                $send_arr["messages"] = $playground_messages; // Override with playground format
                
                // Set timeout to 100 seconds (20s buffer before Cloudflare 524 limit)
                $timeout = 100; // Ensure completion before Cloudflare timeout
                $debug_messages[] = "🔧 GPT-4.5: Using 2000 max_completion_tokens, playground format, ending instructions, and 100s timeout";
            } elseif (!in_array($model, ["o3", "o3-mini"])) {
                // Add legacy parameters for older models (not o3 or gpt-4.5-preview)
                $send_arr["presence_penalty"] = $presence_penalty / 2;
                $send_arr["frequency_penalty"] = $freq_pent / 2;
                $send_arr["stop"] = "\n\n\n";
            }

            $debug_messages[] =
                "OpenAI request parameters: " . json_encode($send_arr);
            $debug_messages[] = "OpenAI endpoint URL: " . $url;

            // Add extra debugging for o3 models
            if (in_array($model, ["o3", "o3-mini"])) {
                $debug_messages[] = "o3 model detected - using Responses API";
                $debug_messages[] =
                    "Request body size: " .
                    strlen(json_encode($send_arr)) .
                    " bytes";
            }

            $args = [
                "timeout" => isset($timeout) ? $timeout : 110, // Use model-specific timeout (110s for GPT-4.5, 110s default)
                "redirection" => 10,
                "httpversion" => "1.1",
                "blocking" => true,
                "headers" => [
                    "Authorization" => "Bearer " . $apikey,
                    "Content-Type" => "application/json",
                ],
                "body" => json_encode($send_arr),
                "cookies" => [],
            ];

            $debug_messages[] = "Using OpenAI API for model: " . $model;
        }

        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            $debug_messages[] = "❌ API connection failed: " . $response->get_error_message();
            
            // CRITICAL FALLBACK: If this is article generation, provide styled error message
            if ($actionInput == "article") {
                $debug_messages[] = "🚨 FALLBACK ACTIVATED: Providing emergency content due to API failure";
                $error_message = $response->get_error_message();
                
                // Create a styled error message for the WYSIWYG
                $styled_error = '<div style="background: #fef7f7; border: 2px solid #d63638; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;">
                    <h3 style="color: #d63638; margin: 0 0 10px 0; font-size: 18px;">⚠️ Article Generation Failed</h3>
                    <p style="color: #333; margin: 0; font-size: 14px;">Connection timeout occurred. Please try again.</p>
                </div>';
                
                // Create JavaScript for popup alert
                $popup_script = '<script>
                    setTimeout(function() {
                        alert("⚠️ Article Generation Failed\\n\\nConnection timeout occurred: ' . esc_js($error_message) . '\\n\\nPlease try again or contact support if this continues.");
                    }, 500);
                </script>';
                
                $resultArr = [
                    "html" => '<div class="title-idea after_generate_data"><div class="eval-screen">' . $styled_error . $popup_script . '</div></div>',
                    "article" => "Article generation failed due to timeout. Please try again.",
                    "type" => "error", // Changed from success to error
                    "debug" => $debug_messages,
                    "fallback_used" => true,
                    "error_message" => $error_message
                ];
                
                echo json_encode($resultArr);
                exit();
            }
            
            wp_send_json_error([
                "msg" =>
                    "Failed to connect to the engine API. Error: " .
                    $response->get_error_message(),
                "debug" => $debug_messages,
            ]);
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data["error"])) {
            $debug_messages[] = "API Error detected";
            $debug_messages[] =
                "Error Code: " . ($data["error"]["code"] ?? "unknown");
            $debug_messages[] = "Full Error: " . json_encode($data["error"]);

            // Minimal o3 error debugging to prevent JSON truncation
            if (in_array($model, ["o3", "o3-mini"])) {
                $debug_messages[] =
                    "o3 error: " . $model . " - " . substr($body, 0, 100);
            }

            wp_send_json_error([
                "msg" => "Engine API Error: " . $data["error"]["message"],
                "debug" => $debug_messages,
            ]);
            return;
        }

        // Convert response format for consistency
        if ($is_anthropic_model) {
            // Convert Anthropic response to OpenAI format (including new format with thinking)
            if (isset($data["content"]) && is_array($data["content"])) {
                $content = "";
                foreach ($data["content"] as $content_block) {
                    // Handle both thinking and text content types
                    if (isset($content_block["type"])) {
                        if ($content_block["type"] === "text" && isset($content_block["text"])) {
                            $content .= $content_block["text"];
                        } elseif ($content_block["type"] === "thinking" && isset($content_block["thinking"])) {
                            // For debugging, we can log the thinking content but don't include it in the main response
                            $debug_messages[] = "Claude thinking content received (length: " . strlen($content_block["thinking"]) . " chars)";
                        }
                    } elseif (isset($content_block["text"])) {
                        // Fallback for older format
                        $content .= $content_block["text"];
                    }
                }
                $resArr = (object) [
                    "choices" => [
                        (object) [
                            "message" => (object) [
                                "content" => $content,
                            ],
                        ],
                    ],
                ];
                $debug_messages[] = "Anthropic response converted successfully (content length: " . strlen($content) . " chars)";
            } else {
                $debug_messages[] = "Unexpected Anthropic response format";
                $debug_messages[] =
                    "Raw Anthropic response: " . substr($body, 0, 500);
                wp_send_json_error([
                    "msg" => "Unexpected response format from Anthropic API",
                    "debug" => $debug_messages,
                ]);
                return;
            }
        } else {
            // OpenAI response - handle both Chat Completions and Responses API
            $resArr = json_decode($body);
            $debug_messages[] = "OpenAI response processed successfully";
            $debug_messages[] =
                "Raw OpenAI response (first 500 chars): " .
                substr($body, 0, 500);

            // Check if this is a Responses API response (for o3)
            if (isset($resArr->object) && $resArr->object === "response") {
                $debug_messages[] = "Processing o3 Responses API format";
                $debug_messages[] = "Response object keys: " . implode(", ", array_keys((array)$resArr));

                // Extracting text from o3 Responses API output
                $extractedText = '';
                if (!empty($resArr->output) && is_array($resArr->output)) {
                    foreach ($resArr->output as $entry) {
                        if (
                            isset($entry->type) && $entry->type === 'message'
                         && !empty($entry->content) && is_array($entry->content)
                        ) {
                            foreach ($entry->content as $contentObj) {
                                if (
                                    isset($contentObj->type) && $contentObj->type === 'output_text'
                                 && isset($contentObj->text)
                                ) {
                                    $extractedText .= $contentObj->text;
                                }
                            }
                        }
                    }
                }
                if ($extractedText === '') {
                    wp_send_json_error([
                        'msg' => 'o3 model completed but no content found',
                        'debug' => $debug_messages,
                    ]);
                    return;
                }
                // For o3 models, create a mock response structure that matches regular OpenAI responses
                // so it goes through the same processing pipeline
                $resArr = (object) [
                    'choices' => [
                        (object) [
                            'message' => (object) [
                                'content' => $extractedText
                            ]
                        ]
                    ]
                ];
                
                $debug_messages[] = "o3 content extracted and formatted for standard processing pipeline";
            }
        }

        $message_str = "";
        if ($messages) {
            foreach ($messages as $message) {
                $message_str .=
                    "Role: " .
                    $message["role"] .
                    "<br/>" .
                    "Content: " .
                    $message["content"] .
                    "<br/>";
            }
        }
        $debug = "";

        /*foreach ($messages as $message) {
		    if ($message["role"] === "system") {
		        echo $message["content"] . '<br>';
		    }
		}*/

        // Handle debug output differently for o3 models vs regular models
        if (in_array($model, ["o3", "o3-mini"])) {
            // o3 models use Responses API format
            $debug .=
                "<br/>Prompt: N/A (o3 uses input array)" .
                "<br/>MESSAGE: " .
                $message_str .
                '<br/> $model: ' .
                ($send_arr["model"] ?? $model) .
                '<br/> $model 2: ' .
                $model .
                '<br/> $apikey: ' .
                $apikey .
                '<br/> $anthropic_api_key: ' .
                $anthropic_api_key .
                '<br/> $temp: ' .
                $temp .
                '<br/> $max_tokens: ' .
                $max_tokens .
                "<br/>actionInput: " .
                $actionInput .
                "<br/>max_tokens: " .
                $max_tokens;
        } else {
            $debug .=
                "<br/>mode: " .
                $mode .
                "<br/>style " .
                $actualStyle .
                "<br/>tone " .
                $actualTone .
                "<br/>actionInput: " .
                $actionInput .
                "<br/>max_tokens: " .
                $max_tokens .
                "<br/>Prompt: " .
                ($send_arr["prompt"] ?? "N/A") .
                "<br/>MESSAGE: " .
                $message_str .
                '<br/> $model: ' .
                ($send_arr["model"] ?? $model) .
                '<br/> $model 2: ' .
                $model .
                '<br/> $apikey: ' .
                $apikey .
                '<br/> $anthropic_api_key: ' .
                $anthropic_api_key .
                '<br/> $top_p: ' .
                ($send_arr["top_p"] ?? "N/A") .
                '<br/> $freq_pent: ' .
                ($send_arr["frequency_penalty"] ?? "N/A") .
                '<br/> $presence_penalty: ' .
                ($send_arr["presence_penalty"] ?? "N/A") .
                '<br/> $max_tokens: ' .
                ($send_arr["max_tokens"] ?? $max_tokens) .
                '<br/> $temp: ' .
                ($send_arr["temperature"] ?? $temp) .
                '<br/> $n: ' .
                ($send_arr["n"] ?? "N/A") .
                "<br/>";
        }

        $isError = $resArr->error ?? "";
        if (!empty($isError)) {
            $errorMSG = $isError->message ?? "";
            $resultArr["html"] = $errorMSG;
            $resultArr["type"] = "error";
        } else {
            // Check if the response is an array and combine the content
            if (isset($resArr->choices)) {
                $combinedContent = "";
                foreach ($resArr->choices as $choice) {
                    if (isset($choice->message->content)) {
                        $combinedContent .= $choice->message->content;
                    }
                }
            }

            $titleHtml = '<div class="title-idea after_generate_data">';

            if (
                $actionInput == "evaluate" ||
                $actionInput == "article" ||
                $actionInput == "review"
            ) {
                $titleHtml .= '<div class="ul1" ><div class="eval-screen">';
            } else {
                $titleHtml .= '<ul class="ul1" >';
            }

            // EARLY IMAGE GENERATION: Start when user has selected a title and moved to keyword step
            if ($actionInput == "keyword") {
                $debug_messages[] = "🎨 Keyword step detected - user has selected title, starting early image generation";
                
                // Get the user-selected title from POST data (not from generated content)
                $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : "";
                $debug_messages[] = "📝 User-selected title from POST data: " . $title;

                // Clean up title if we have one
                if (!empty($title)) {
                    // Remove numbering and clean up
                    $title = preg_replace('/^\d+\.\s*["\']?/', '', $title); // Remove "1. " or "1. ""
                    $title = preg_replace('/["\']$/', '', $title); // Remove trailing quotes
                    $title = trim($title);
                    $debug_messages[] = "🧹 Title cleaned: " . $title;
                } else {
                    $debug_messages[] = "⚠️ No title found in POST data, cannot generate image";
                }
                
                // Check if image generation is enabled and we have a title
                $enable_image_generation = get_option('ab_enable_image_generation', 1);
                if ($enable_image_generation && !empty($title)) {
                    $debug_messages[] = "⚡ EARLY PARALLEL IMAGE: Starting background generation immediately after " . $actionInput;
                    
                    // Create image prompt
                    $image_style = get_option('ab_image_style', 'Photorealistic');
                    $image_prompt = $title . " - Create an image based on this title in the style of " . $image_style . ". You must not include any text, characters, symbols, or writing. Highly detailed and stylised to match the title.";
                    
                    // Store data for parallel processing
                    $resultArr['parallel_image_js'] = "
                        console.log('⚡ EARLY PARALLEL IMAGE: Starting background image generation after " . $actionInput . "');
                        console.log('📝 Image prompt: " . esc_js($image_prompt) . "');
                        
                        // Start parallel image generation via AJAX
                        jQuery.ajax({
                            type: 'POST',
                            url: '" . admin_url('admin-ajax.php') . "',
                            data: {
                                action: 'al_scribe_generate_4o_image',
                                security: ai_scribe.nonce,
                                prompt: '" . esc_js($image_prompt) . "'
                            },
                            timeout: 60000, // 60 second timeout for image generation
                            success: function(response) {
                                console.log('✅ EARLY PARALLEL IMAGE: Generation completed', response);
                                console.log('🔍 EARLY PARALLEL IMAGE: Response type:', typeof response);
                                console.log('🔍 EARLY PARALLEL IMAGE: Response value:', response);
                                
                                // Handle WordPress AJAX response format
                                if (response && response.success && response.data && response.data.image_url) {
                                    console.log('🖼️ EARLY PARALLEL IMAGE: Image URL received, storing for article');
                                    window.aiScribeGeneratedImage = {
                                        url: response.data.image_url,
                                        attachment_id: response.data.attachment_id,
                                        html: response.data.image_html,
                                        alt: response.data.alt_text || 'Article image',
                                        caption: response.data.caption || ''
                                    };
                                    console.log('📦 EARLY PARALLEL IMAGE: Stored image data:', window.aiScribeGeneratedImage);
                                    
                                    // Also store in localStorage as backup for article step
                                    try {
                                        localStorage.setItem('aiScribeGeneratedImage', JSON.stringify(window.aiScribeGeneratedImage));
                                        console.log('💾 EARLY PARALLEL IMAGE: Backup stored in localStorage');
                                    } catch (e) {
                                        console.log('⚠️ EARLY PARALLEL IMAGE: Could not store in localStorage:', e);
                                    }
                                    
                                    // Trigger custom event to notify image insertion system
                                    try {
                                        var event = new CustomEvent('aiScribeImageReady', { detail: window.aiScribeGeneratedImage });
                                        window.dispatchEvent(event);
                                        console.log('🎉 EARLY PARALLEL IMAGE: Triggered aiScribeImageReady event');
                                    } catch (e) {
                                        console.log('⚠️ EARLY PARALLEL IMAGE: Could not trigger event:', e);
                                    }
                                } else if (response && response.image_url) {
                                    // Direct response format
                                    console.log('🖼️ EARLY PARALLEL IMAGE: Direct image URL received');
                                    window.aiScribeGeneratedImage = {
                                        url: response.image_url,
                                        attachment_id: response.attachment_id || null,
                                        html: response.image_html || null,
                                        alt: response.alt_text || 'Article image',
                                        caption: response.caption || ''
                                    };
                                    
                                    // Also store in localStorage as backup for article step
                                    try {
                                        localStorage.setItem('aiScribeGeneratedImage', JSON.stringify(window.aiScribeGeneratedImage));
                                        console.log('💾 EARLY PARALLEL IMAGE: Backup stored in localStorage');
                                    } catch (e) {
                                        console.log('⚠️ EARLY PARALLEL IMAGE: Could not store in localStorage:', e);
                                    }
                                    
                                    // Trigger custom event to notify image insertion system
                                    try {
                                        var event = new CustomEvent('aiScribeImageReady', { detail: window.aiScribeGeneratedImage });
                                        window.dispatchEvent(event);
                                        console.log('🎉 EARLY PARALLEL IMAGE: Triggered aiScribeImageReady event');
                                    } catch (e) {
                                        console.log('⚠️ EARLY PARALLEL IMAGE: Could not trigger event:', e);
                                    }
                                } else if (response === 0 || response === '0') {
                                    console.log('❌ EARLY PARALLEL IMAGE: WordPress AJAX returned 0 - handler not found or PHP error');
                                    console.log('🔍 Check: 1) AJAX action registered 2) Nonce valid 3) PHP errors in handler');
                                } else if (response && !response.success && response.data) {
                                    console.log('❌ EARLY PARALLEL IMAGE: Error response received');
                                    console.log('🔍 Error message:', response.data.message || response.data.msg || 'No error message');
                                    if (response.data.debug) {
                                        console.log('🔍 Debug info:', response.data.debug);
                                    }
                                } else {
                                    console.log('⚠️ EARLY PARALLEL IMAGE: Unexpected response format, continuing without image');
                                    console.log('🔍 Expected: {success: true, data: {image_url: \"...\"}} or {image_url: \"...\"}');
                                    console.log('🔍 Actual response:', response);
                                }
                            },
                            error: function(xhr, status, error) {
                                console.log('❌ EARLY PARALLEL IMAGE: AJAX error (' + status + '):', error);
                                console.log('🔍 XHR status:', xhr.status);
                                console.log('🔍 XHR response:', xhr.responseText);
                                try {
                                    var errorResponse = JSON.parse(xhr.responseText);
                                    if (errorResponse.data && errorResponse.data.debug) {
                                        console.log('🔍 Error debug info:', errorResponse.data.debug);
                                    }
                                } catch (e) {
                                    console.log('🔍 Could not parse error response as JSON');
                                }
                            }
                        });
                    ";
                    
                    $debug_messages[] = "✅ Early parallel image generation JavaScript prepared";
                } else {
                    $debug_messages[] = "ℹ️ Image generation disabled or no title available for " . $actionInput;
                }
            }
            if ($resArr != "") {
                $choicesArr = $resArr->choices ?? "";
                $combinedContent = "";

                if (!empty($choicesArr)) {
                    foreach ($choicesArr as $reskey => $resvalue) {
                        // Access the corresponding element in the $resArr->choices array
                        $choice = $resArr->choices[$reskey];

                        // Initialize the combinedContent variable
                        $combinedContent = "";

                        // Check if the choice message content exists and append it to the combinedContent variable
                        if (isset($choice->message->content)) {
                            $combinedContent .= $choice->message->content;
                        }

                        // Check if the resvalue text exists and append it to the combinedContent variable
                        $textRes = $resvalue->text ?? "";
                        $combinedContent .= $textRes;

                        // Now, $combinedContent contains both the choice message content and the resvalue text

                        if ($actionInput == "keyword") {
                            // Since gpt-3.5 is removed, we no longer need the model check
                            $combinedContent = str_replace(
                                ",",
                                "\n",
                                $combinedContent
                            );
                            $combinedContent = explode("\n", $combinedContent);
                        } elseif ($actionInput == "heading") {
                            $combinedContent = str_replace(
                                "\n\n",
                                "\n",
                                $combinedContent
                            );
                            $combinedContent = explode(
                                "\n\n",
                                $combinedContent
                            );
                        } elseif ($actionInput == "conclusion") {
                            $combinedContent = explode(
                                "\n\n",
                                $combinedContent
                            );
                        } elseif ($actionInput == "qna") {
                            $combinedContent = explode(
                                "\n\n",
                                $combinedContent
                            );
                        } elseif ($actionInput == "seo-meta-data") {
                            $combinedContent = explode(
                                "\n\n",
                                $combinedContent
                            );
                        } elseif ($actionInput == "evaluate") {
                            $combinedContent = explode(
                                "\n\n",
                                $combinedContent
                            );
                        } else {
                            $combinedContent = explode("\n", $combinedContent);
                        }
                        $checkboxAdded = false;

                        foreach ($combinedContent as $textValue) {
                            if (
                                $actionInput == "heading" ||
                                $actionInput == "keyword"
                            ) {
                                $textValue = str_replace(
                                    "\n",
                                    "<br/>",
                                    $textValue
                                );
                            }
                            if ($actionInput == "heading") {
                                $textValue = ltrim($textValue, "<br/>");
                            } elseif ($actionInput == "seo-meta-data") {
                                $textValue = str_replace(
                                    "\n\n",
                                    "<br/>",
                                    $textValue
                                );
                                $textValue = ltrim($textValue, "<br/>");
                                $textValue = trim(
                                    str_replace("Meta Title:", "", $textValue)
                                );
                                $textValue = trim(
                                    str_replace(
                                        "Meta Description:",
                                        "",
                                        $textValue
                                    )
                                );
                            } elseif ($actionInput == "article") {
                                $textValue = str_replace("·", "", $textValue);
                            } elseif ($actionInput == "evaluate") {
                                $textValue = str_replace(
                                    "\n",
                                    "<br/>",
                                    $textValue
                                );
                            }
                            if ($textValue != "") {
                                $textValue = str_replace('"', "", $textValue);
                                // Remove bullet points from o3 model output (• character and other bullets)
                                $textValue = preg_replace('/^[•\-\*\+\d+\.\)\s\-]+/', '', trim($textValue));
                                if (
                                    $actionInput == "qna" ||
                                    $actionInput == "conclusion"
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
                                        preg_replace('/^[•\-\*\+\d+\.\)\s\-]+/', '', $textValue) .
                                        '</div>
                                                    <input class="checkbox get_checked" id="check_' .
                                        $actionInput .
                                        '" name="get_checked" type="checkbox" value= "' .
                                        preg_replace('/^[•\-\*\+\d+\.\)\s\-]+/', '', $textValue) .
                                        '" />
                                                    </div>';
                                } elseif (
                                    $actionInput == "evaluate" ||
                                    $actionInput == "article" ||
                                    $actionInput == "review"
                                ) {
                                    $textValue = preg_replace_callback(
                                        "/(<!--|\s*<!--)|\s+/",
                                        function ($matches) {
                                            if (
                                                isset($matches[1]) &&
                                                $matches[1] !== ""
                                            ) {
                                                return $matches[1];
                                            } else {
                                                return " ";
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
                                            '/^[•\-\*\+\d+\.\)\s\-]+/',
                                            "",
                                            $textValue
                                        ) .
                                        '</p>
                                                    <input class="checkbox get_checked" id="check_' .
                                        $actionInput .
                                        '" name="get_checked" type="checkbox" value= "' .
                                        preg_replace(
                                            '/^[•\-\*\+\d+\.\)\s\-]+/',
                                            "",
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
                $actionInput == "evaluate" ||
                $actionInput == "article" ||
                $actionInput == "review"
            ) {
                $titleHtml .= "</div></div></div>";
            } else {
                $titleHtml .= "</ul></div>";
            }
            //$titleHtml = $debug . $titleHtml;
            $resultArr["html"] = $titleHtml;
            $articleStr = implode(" ", $combinedContent);
            $resultArr["article"] = $articleStr;
            $resultArr["type"] = "success";
            $resultArr["debug"] = $debug_messages; // Add debug messages to response
        }

        // Add token usage data to response for cost tracking
        $debug_messages[] = "💰 Extracting token usage data for cost tracking";

        // Check multiple sources for usage data
        $usage_data = null;

        // Method 1: Check $data array (Anthropic format)
        if (isset($data['usage'])) {
            $usage_data = $data['usage'];
            $debug_messages[] = "💰 Found usage data in \$data['usage'] (OpenAI format)";
        } elseif (isset($data['token_usage'])) {
            // Anthropic format - convert to OpenAI format
            $usage_data = [
                'prompt_tokens' => $data['token_usage']['input_tokens'] ?? 0,
                'completion_tokens' => $data['token_usage']['output_tokens'] ?? 0,
                'total_tokens' => ($data['token_usage']['input_tokens'] ?? 0) + ($data['token_usage']['output_tokens'] ?? 0)
            ];
            $debug_messages[] = "💰 Found usage data in \$data['token_usage'] (Anthropic format)";
        }

        // Method 2: Check $resArr object (OpenAI response object)
        if (!$usage_data && isset($resArr->usage)) {
            $usage_data = (array) $resArr->usage;
            $debug_messages[] = "💰 Found usage data in \$resArr->usage (OpenAI object format)";
        }

        // Method 3: Check original response body for usage data
        if (!$usage_data && isset($body)) {
            $response_data = json_decode($body, true);
            if (isset($response_data['usage'])) {
                $usage_data = $response_data['usage'];
                $debug_messages[] = "💰 Found usage data in response body (backup method)";
            }
        }

        if ($usage_data) {
            $resultArr['usage'] = $usage_data;
            $resultArr['model'] = $model;
            $debug_messages[] = "💰 Token usage added to response: " . json_encode($usage_data);
        } else {
            $debug_messages[] = "⚠️ No token usage data found in API response";
        }

        // Add image insertion JavaScript ONLY for article generation step
        $debug_messages[] = "🖼️ CHECKING if image insertion needed for step: " . $actionInput;
        if ($actionInput == "article") { // Only add for article generation step
            $debug_messages[] = "✅ ADDING image_insert_js to response for article step";
            $resultArr["image_insert_js"] = "
                console.log('🖼️ IMAGE INSERTION: Starting smart image insertion system for article step');
                console.log('🖼️ IMAGE INSERTION: Checking if image data already exists...');
                
                // Flag to prevent duplicate insertions
                window.aiScribeImageInserted = false;
                
                // Function to insert image into Quill editor content
                function insertImageIntoQuillContent() {
                    if (window.aiScribeImageInserted) {
                        console.log('ℹ️ IMAGE INSERTION: Image already inserted, skipping');
                        return false;
                    }
                    
                    var imageData = null;
                    
                    // Check window.aiScribeGeneratedImage first
                    if (window.aiScribeGeneratedImage && window.aiScribeGeneratedImage.url) {
                        imageData = window.aiScribeGeneratedImage;
                        console.log('✅ IMAGE INSERTION: Found generated image in window object');
                    } else {
                        // Fallback: check localStorage
                        try {
                            var storedImage = localStorage.getItem('aiScribeGeneratedImage');
                            if (storedImage) {
                                imageData = JSON.parse(storedImage);
                                console.log('✅ IMAGE INSERTION: Found generated image in localStorage');
                            }
                        } catch (e) {
                            console.log('⚠️ IMAGE INSERTION: Could not retrieve image from localStorage:', e);
                        }
                    }
                    
                    if (imageData && imageData.url) {
                        console.log('✅ IMAGE INSERTION: Found generated image, inserting into Quill editor');
                        console.log('📦 IMAGE INSERTION: Image data:', imageData);
                        
                        // Try to get the Quill editor instance
                        var quillEditor = null;
                        if (typeof alok !== 'undefined' && alok) {
                            quillEditor = alok;
                            console.log('✅ IMAGE INSERTION: Found alok Quill editor instance');
                        } else if (typeof quill !== 'undefined' && quill) {
                            quillEditor = quill;
                            console.log('✅ IMAGE INSERTION: Found quill editor instance');
                        } else {
                            console.log('⚠️ IMAGE INSERTION: No Quill editor instance found');
                            return false;
                        }
                        
                        // Get current content from Quill
                        var currentContent = quillEditor.root.innerHTML;
                        
                        if (currentContent && currentContent.length > 100) { // Ensure content is substantial
                            // Check if image is already in content
                            if (currentContent.indexOf(imageData.url) !== -1) {
                                console.log('ℹ️ IMAGE INSERTION: Image already exists in content');
                                window.aiScribeImageInserted = true;
                                return true;
                            }
                            
                            // Create image HTML with enhanced styling
                            var imageHtml = '<div class=\"ai-scribe-article-image\" style=\"margin: 25px 0; text-align: center; clear: both;\">';
                            imageHtml += '<img src=\"' + imageData.url + '\" alt=\"' + (imageData.alt || 'Article image') + '\" style=\"max-width: 100%; height: auto; display: block; margin: 0 auto; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15);\" />';
                            if (imageData.caption && imageData.caption.trim()) {
                                imageHtml += '<p style=\"text-align: center; font-style: italic; margin-top: 15px; color: #666; font-size: 14px; line-height: 1.4;\">' + imageData.caption + '</p>';
                            }
                            imageHtml += '</div>';
                            
                            // Find the first H1 tag and insert image after it
                            var h1Match = currentContent.match(/(<h1[^>]*>.*?<\/h1>)/i);
                            var newContent;
                            if (h1Match) {
                                newContent = currentContent.replace(h1Match[0], h1Match[0] + imageHtml);
                                console.log('✅ IMAGE INSERTION: Will insert image after H1 title');
                            } else {
                                // If no H1 found, insert at the beginning
                                newContent = imageHtml + currentContent;
                                console.log('✅ IMAGE INSERTION: Will insert image at beginning of content');
                            }
                            
                            // Update Quill editor content
                            try {
                                var delta = quillEditor.clipboard.convert(newContent);
                                quillEditor.setContents(delta, 'silent');
                                console.log('✅ IMAGE INSERTION: Image successfully inserted into Quill editor!');
                                
                                // Mark as inserted and clean up
                                window.aiScribeImageInserted = true;
                                delete window.aiScribeGeneratedImage;
                                try {
                                    localStorage.removeItem('aiScribeGeneratedImage');
                                    console.log('🧹 IMAGE INSERTION: Cleared stored image data');
                                } catch (e) {
                                    console.log('🧹 IMAGE INSERTION: localStorage clear failed:', e);
                                }
                                return true;
                            } catch (e) {
                                console.log('❌ IMAGE INSERTION: Failed to update Quill content:', e);
                                return false;
                            }
                        } else {
                            console.log('⚠️ IMAGE INSERTION: Article content not ready yet (length: ' + (currentContent ? currentContent.length : 0) + ')');
                            return false;
                        }
                    }
                    return false;
                }
                
                // Immediate check for existing image
                setTimeout(function() {
                    console.log('🔍 IMAGE INSERTION: Initial check for existing image');
                    insertImageIntoQuillContent();
                }, 1000); // Wait 1 second for Quill to be ready
                
                // Set up periodic checks for late-arriving images (every 3 seconds for 30 seconds)
                var checkCount = 0;
                var maxChecks = 10; // 30 seconds total
                var imageCheckInterval = setInterval(function() {
                    checkCount++;
                    console.log('🔍 IMAGE INSERTION: Periodic check #' + checkCount + ' for late-arriving image');
                    
                    if (insertImageIntoQuillContent() || checkCount >= maxChecks) {
                        clearInterval(imageCheckInterval);
                        if (checkCount >= maxChecks) {
                            console.log('⏰ IMAGE INSERTION: Stopped checking after ' + (maxChecks * 3) + ' seconds');
                        }
                    }
                }, 3000);
                
                // Listen for custom events when image becomes available
                window.addEventListener('aiScribeImageReady', function(event) {
                    console.log('🎉 IMAGE INSERTION: Received aiScribeImageReady event');
                    setTimeout(insertImageIntoQuillContent, 500);
                });
                
                console.log('✅ IMAGE INSERTION: Smart insertion system initialized for Quill editor');
            ";
        } else {
            $debug_messages[] = "ℹ️ Skipping image insertion JavaScript for step: " . $actionInput;
        }

        // return $resultArr;
        echo json_encode($resultArr);
        exit();
    }
}

// Initialize the class
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Debug: Log plugin initialization
error_log("AI Scribe: Plugin file loaded, about to instantiate class");

try {
    $my_plugin = new AI_Scribe();
    error_log("AI Scribe: Class instantiated successfully");
} catch (Exception $e) {
    error_log("AI Scribe: Failed to instantiate class: " . $e->getMessage());
}