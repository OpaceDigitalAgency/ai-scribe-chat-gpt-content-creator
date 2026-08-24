<?php
/**
 * Prompt Manager for AI-Scribe Plugin
 *
 * Manages prompt templates from JSON files, handles parameter substitution,
 * and provides centralized prompt management with caching and validation.
 *
 * @package AI_Scribe
 * @subpackage Infrastructure
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Prompt_Manager
 *
 * Handles loading and processing of prompt templates from JSON files,
 * parameter substitution, and model-specific prompt modifications.
 */
class AI_Scribe_Prompt_Manager extends AI_Scribe_Base_Service {

	/**
	 * Previously shipped, unedited built-in prompts. An exact match can use
	 * the corrected default on upgrade; genuinely edited prompts never match.
	 */
	const RETIRED_DEFAULT_PROMPT_HASHES = array(
		'title_prompts'    => 'c5dbbff17185ccb153156a837128564a7222af159520c5776a90ef382045e4ca',
		'Keywords_prompts' => 'ec73d071e50ce1dde5b59aca5c1b56ed6ab5b0312a338fe5f0ecfa376f813a8b',
		'tagline_prompts'  => '7a81e71fddc25212bc5c7bcc2de5c6954592aed1863acb2684290588d6910e0e',
		'article_prompts'  => '81ebf5fa771c0cb0bfd56c6e2ad708d31a46474054858a97523aa154d90e749e',
	);

	/**
	 * Loaded prompt templates cache
	 *
	 * @var array
	 */
	private $prompt_cache = array();

	/**
	 * Prompt file paths
	 *
	 * @var array
	 */
	private $prompt_files = array(
		'default' => 'prompts/complete-prompts.json',
		'custom'  => 'prompts/custom-prompts.json',
	);

	/**
	 * Parameter pattern for replacement
	 *
	 * @var string
	 */
	private $parameter_pattern = '/\[([^\]]+)\]/';

	/**
	 * Initialize the prompt manager
	 *
	 * @return void
	 */
	protected function initialize() {
		$this->load_prompt_templates();

		// AJAX handlers now centralized in Plugin Initializer
		// No direct AJAX registration needed in service classes
	}

	/**
	 * Validate service configuration
	 *
	 * @return bool|array True if valid, error array if invalid
	 */
	public function validate_service() {
		$default_file = AI_SCRIBE_DIR . 'includes/' . $this->prompt_files['default'];

		if ( ! file_exists( $default_file ) ) {
			return $this->handle_error( 'Default prompt file not found: ' . $default_file );
		}

		if ( ! is_readable( $default_file ) ) {
			return $this->handle_error( 'Default prompt file not readable: ' . $default_file );
		}

		return true;
	}

	/**
	 * Load prompt templates from JSON files
	 *
	 * @return void
	 */
	private function load_prompt_templates() {
		$this->log_debug( '[LOAD DEBUG] Starting to load prompt templates' );
		$this->log_debug( '[LOAD DEBUG] Prompt file groups: ' . implode( ', ', array_keys( $this->prompt_files ) ) );

		foreach ( $this->prompt_files as $type => $file_path ) {
			$full_path = AI_SCRIBE_DIR . 'includes/' . $file_path;
			$this->log_debug( "[LOAD DEBUG] Processing {$type} from: {$full_path}" );

			if ( file_exists( $full_path ) && is_readable( $full_path ) ) {
				$content = file_get_contents( $full_path );
				$this->log_debug( "[LOAD DEBUG] File content length for {$type}: " . strlen( $content ) );

				$prompts = json_decode( $content, true );

				if ( json_last_error() === JSON_ERROR_NONE ) {
					$this->prompt_cache[ $type ] = $prompts;
					$this->log_debug( "[LOAD DEBUG] Successfully loaded {$type} prompts from: {$file_path}" );

					// Log structure for debugging
					if ( isset( $prompts['content_prompts'] ) ) {
						$this->log_debug( "[LOAD DEBUG] {$type} content_prompts types: " . implode( ', ', array_keys( $prompts['content_prompts'] ) ) );
					}
				} else {
					$this->log_error( "[LOAD DEBUG] Failed to parse JSON in {$file_path}: " . json_last_error_msg() );
				}
			} elseif ( $type === 'default' ) {
					$this->log_error( "[LOAD DEBUG] Required default prompt file not found: {$file_path}" );
			} else {
				$this->log_debug( "[LOAD DEBUG] Optional {$type} prompt file not found: {$file_path}" );
			}
		}

		$this->log_debug( '[LOAD DEBUG] Final prompt cache keys: ' . implode( ', ', array_keys( $this->prompt_cache ) ) );
	}

	/**
	 * Get prompt template by category and type
	 *
	 * @param string $category Prompt category (content_prompts, image_prompts, etc.)
	 * @param string $type Prompt type (outline, introduction, etc.)
	 * @param string $source Source to check (default, custom, or auto)
	 * @return array|null Prompt template or null if not found
	 */
	public function get_prompt_template( $category, $type, $source = 'auto' ) {
		if ( $source === 'auto' ) {
			// Check custom first, then default
			$template = $this->get_prompt_from_source( 'custom', $category, $type );
			if ( $template === null ) {
				$template = $this->get_prompt_from_source( 'default', $category, $type );
			}
			return $template;
		}

		return $this->get_prompt_from_source( $source, $category, $type );
	}

	/**
	 * Get prompt from specific source - ENHANCED WITH DIAGNOSTICS
	 *
	 * @param string $source Source name
	 * @param string $category Prompt category
	 * @param string $type Prompt type
	 * @return array|null
	 */
	private function get_prompt_from_source( $source, $category, $type ) {
		$this->log_debug( "[SOURCE DEBUG] get_prompt_from_source called: {$source}.{$category}.{$type}" );

		// Check if source exists
		if ( ! isset( $this->prompt_cache[ $source ] ) ) {
			$this->log_debug( "[SOURCE DEBUG] Source '{$source}' not found in cache" );
			return null;
		}

		// Check if category exists in source
		if ( ! isset( $this->prompt_cache[ $source ][ $category ] ) ) {
			$this->log_debug( "[SOURCE DEBUG] Category '{$category}' not found in source '{$source}'" );
			$this->log_debug( "[SOURCE DEBUG] Available categories in '{$source}': " . implode( ', ', array_keys( $this->prompt_cache[ $source ] ) ) );
			return null;
		}

		// Check if type exists in category
		if ( ! isset( $this->prompt_cache[ $source ][ $category ][ $type ] ) ) {
			$this->log_debug( "[SOURCE DEBUG] Type '{$type}' not found in '{$source}.{$category}'" );
			$this->log_debug( "[SOURCE DEBUG] Available types in '{$source}.{$category}': " . implode( ', ', array_keys( $this->prompt_cache[ $source ][ $category ] ) ) );
			return null;
		}

		$template = $this->prompt_cache[ $source ][ $category ][ $type ];
		$this->log_debug( "[SOURCE DEBUG] Found template in '{$source}.{$category}.{$type}' with keys: " . implode( ', ', array_keys( $template ) ) );

		return $template;
	}

	/**
	 * Handle AJAX request to get prompt template
	 *
	 * @return void
	 */
	public function handle_get_prompt_template() {
		// Verify nonce
		$nonce       = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
		$nonce_valid = wp_verify_nonce( $nonce, 'ai_scribe_nonce' );

		$this->log_debug( '[PROMPT DEBUG] Nonce validation: ' . ( $nonce_valid ? 'VALID' : 'INVALID' ) );
		$this->log_debug( '[PROMPT DEBUG] Provided nonce: ' . $nonce );

		if ( ! $nonce_valid ) {
			$this->log_error( '[PROMPT DEBUG] Nonce verification failed' );
			wp_send_json_error(
				array(
					'msg'           => __( 'The security nonce is missing or invalid. Please refresh the page.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					'nonce_expired' => true,
					'debug_info'    => array(
						'provided_nonce'  => $nonce,
						'expected_action' => 'ai_scribe_nonce',
					),
				)
			);
			return;
		}

		// Enhanced debugging for prompt template requests, after verification.
		$this->log_debug( '[PROMPT DEBUG] handle_get_prompt_template called' );
		$this->log_debug( '[PROMPT DEBUG] POST field names: ' . implode( ', ', array_map( 'sanitize_key', array_keys( $_POST ) ) ) );

		// Sanitize input
		$category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
		$type     = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
		$step     = intval( $_POST['step'] ?? 0 );

		$this->log_debug( "[PROMPT DEBUG] Request parameters - Category: {$category}, Type: {$type}, Step: {$step}" );

		// Handle step mapping for content_prompts category
		if ( $category === 'content_prompts' && $step > 0 ) {
			$step_mapping = array(
				1  => 'title',
				2  => 'keywords',
				3  => 'outline',
				4  => 'introduction',
				5  => 'tagline',
				6  => 'article_full',
				7  => 'conclusion',
				8  => 'qa',
				9  => 'meta',
				10 => 'revision',
				11 => 'evaluation',
			);

			if ( isset( $step_mapping[ $step ] ) ) {
				$mapped_type = $step_mapping[ $step ];
				$this->log_debug( "[PROMPT DEBUG] Step mapping applied - Step {$step} → Type: {$mapped_type}" );
				$type = $mapped_type;
			} else {
				$this->log_warning( "[PROMPT DEBUG] Invalid step number: {$step}" );
			}
		}

		if ( empty( $category ) || empty( $type ) ) {
			$this->log_error( '[PROMPT DEBUG] Missing required parameters' );
			wp_send_json_error(
				array(
					'msg'        => __( 'A category and type are required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					'debug_info' => array(
						'category' => $category,
						'type'     => $type,
					),
				)
			);
			return;
		}

		// Ensure prompt cache is loaded
		if ( empty( $this->prompt_cache ) ) {
			$this->log_warning( '[PROMPT DEBUG] Prompt cache is empty, reloading templates' );
			$this->load_prompt_templates();
		}

		// Get prompt template
		$this->log_debug( "[PROMPT DEBUG] Attempting to get template: {$category}.{$type}" );
		$template = $this->get_prompt_template( $category, $type );

		$this->log_debug( '[PROMPT DEBUG] Template result: ' . ( $template ? 'FOUND' : 'NOT FOUND' ) );
		if ( $template ) {
			$this->log_debug( '[PROMPT DEBUG] Template keys: ' . implode( ', ', array_keys( $template ) ) );
		}

		if ( $template === null ) {
			$this->log_error( "[PROMPT DEBUG] Template not found: {$category}.{$type}" );
			$this->log_debug( '[PROMPT DEBUG] Available prompt cache: ' . implode( ', ', array_keys( $this->prompt_cache ) ) );

			wp_send_json_error(
				array(
					'msg'        => sprintf(
						/* translators: 1: prompt category, 2: prompt type. */
						__( 'Prompt template not found: %1$s.%2$s', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						$category,
						$type
					),
					'debug_info' => array(
						'category'             => $category,
						'type'                 => $type,
						'available_cache_keys' => array_keys( $this->prompt_cache ),
					),
				)
			);
			return;
		}

		// Return template data
		$response_data = array(
			'prompt'        => $template['template'],
			'parameters'    => $template['parameters'] ?? array(),
			'output_format' => $template['output_format'] ?? 'text',
			'max_tokens'    => $template['max_tokens'] ?? null,
		);

		$this->log_debug( '[PROMPT DEBUG] Sending successful response with prompt length: ' . strlen( $template['template'] ) );
		wp_send_json_success( $response_data );
	}

	/**
	 * Handle AJAX request to save prompt template
	 *
	 * @return void
	 */
	public function handle_save_prompt_template() {
		// Verify nonce
		$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ai_scribe_nonce' ) ) {
			wp_send_json_error(
				array(
					'msg'           => __( 'The security nonce is missing or invalid. Please refresh the page.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					'nonce_expired' => true,
				)
			);
			return;
		}

		// Sanitize input - accept both 'template' and 'prompt' for backward compatibility
		$category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
		$type     = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
		$template = sanitize_textarea_field( wp_unslash( $_POST['template'] ?? $_POST['prompt'] ?? '' ) );

		if ( empty( $category ) || empty( $type ) || empty( $template ) ) {
			wp_send_json_error(
				array(
					'msg' => __( 'A category, type and template or prompt are required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				)
			);
			return;
		}

		// Save to custom prompts (user modifications)
		$result = $this->save_custom_prompt(
			$category,
			$type,
			array(
				'template'   => $template,
				'parameters' => $this->extract_parameters( $template ),
			)
		);

		if ( $result === true ) {
			wp_send_json_success(
				array(
					'msg'      => __( 'Prompt template saved successfully.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					'category' => $category,
					'type'     => $type,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'msg' => sprintf(
						/* translators: %s: prompt-template save error message. */
						__( 'Failed to save the prompt template: %s', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						$result
					),
				)
			);
		}
	}

	/**
	 * Save custom prompt template
	 *
	 * @param string $category Prompt category
	 * @param string $type Prompt type
	 * @param array $template Template data
	 * @return bool Success status
	 */
	public function save_custom_prompt( $category, $type, array $template ) {
		try {
			// Initialize custom prompts if not exists
			if ( ! isset( $this->prompt_cache['custom'] ) ) {
				$this->prompt_cache['custom'] = array();
			}

			if ( ! isset( $this->prompt_cache['custom'][ $category ] ) ) {
				$this->prompt_cache['custom'][ $category ] = array();
			}

			// Add metadata
			$template['created_at'] = current_time( 'mysql' );
			$template['created_by'] = get_current_user_id();

			$this->prompt_cache['custom'][ $category ][ $type ] = $template;

			// Save to file
			$custom_file = AI_SCRIBE_DIR . 'includes/' . $this->prompt_files['custom'];

			// Ensure directory exists
			$custom_dir = dirname( $custom_file );
			if ( ! file_exists( $custom_dir ) ) {
				wp_mkdir_p( $custom_dir );
			}

			$result = file_put_contents( $custom_file, json_encode( $this->prompt_cache['custom'], JSON_PRETTY_PRINT ) );

			if ( $result !== false ) {
				$this->log_info( "Saved custom prompt: {$category}.{$type}" );
				return true;
			}

			return false;

		} catch ( Exception $e ) {
			return $this->handle_error( 'Failed to save custom prompt', $e );
		}
	}

	/**
	 * Extract parameters from template
	 *
	 * @param string $template Template content
	 * @return array Array of parameter names
	 */
	private function extract_parameters( $template ) {
		preg_match_all( $this->parameter_pattern, $template, $matches );
		return array_unique( $matches[1] ?? array() );
	}

	/**
	 * Process template variables in prompt content
	 *
	 * Replaces template variables like [Title], [Language], [Style], [Tone],
	 * [Selected Keywords], [Heading Tag], etc. with actual values from the request context.
	 *
	 * @param string $template Template content with variables
	 * @param array $context Context data for variable replacement
	 * @return string Processed template with variables replaced
	 */
	public function process_template_variables( $template, $context = array() ) {
		if ( empty( $template ) || ! is_string( $template ) ) {
			$this->log_debug( '[TEMPLATE VARS] Invalid template provided' );
			return $template;
		}

		$this->log_debug( '[TEMPLATE VARS] Processing template variables' );
		$this->log_debug( '[TEMPLATE VARS] Original template: ' . substr( $template, 0, 200 ) . '...' );
		$this->log_debug( '[TEMPLATE VARS] Context fields: ' . implode( ', ', array_keys( $context ) ) );

		$processed_template = $template;

		try {
			// Get current settings from Config Manager if not provided in context
			$language      = $context['language'] ?? $this->get_setting_value( 'language', 'English' );
			$writing_style = $context['writing_style'] ?? $this->get_setting_value( 'writing_style', 'Business' );
			$writing_tone  = $context['writing_tone'] ?? $this->get_setting_value( 'writing_tone', 'Professional' );

			// Get user input and selected content from context
			$title             = $context['title'] ?? $context['selected_title'] ?? $context['user_input'] ?? '';
			$selected_keywords = $context['selected_keywords'] ?? $context['keywords'] ?? '';
			$heading_tag       = $context['heading_tag'] ?? $context['Heading_tag'] ?? 'H2';
			$user_input        = $context['user_input'] ?? '';

			// 🚨 CRITICAL FIX: Add missing variables for V4 compatibility
			$tagline      = $context['tagline'] ?? $context['selected_tagline'] ?? '';
			$outline      = $context['outline'] ?? $context['selected_outline'] ?? '';
			$introduction = $context['introduction'] ?? $context['selected_introduction'] ?? $context['intro'] ?? '';
			$no_headings  = $context['no_headings'] ?? $context['number_of_heading'] ?? '5';
			$above_below  = $context['above_below'] ?? $context['tagline_position'] ?? 'below';

			$this->log_debug(
				'[TEMPLATE VARS] Context lengths: ' . wp_json_encode(
					array(
						'title'             => strlen( (string) $title ),
						'selected_keywords' => strlen( is_array( $selected_keywords ) ? implode( ',', $selected_keywords ) : (string) $selected_keywords ),
						'tagline'           => strlen( (string) $tagline ),
						'outline'           => strlen( is_array( $outline ) ? wp_json_encode( $outline ) : (string) $outline ),
						'introduction'      => strlen( (string) $introduction ),
					)
				)
			);

			// Replace core template variables
			$replacements = array(
				'[Language]'          => $language,
				'[Style]'             => $writing_style,
				'[Tone]'              => $writing_tone,
				'[Title]'             => $title,
				'[Topic]'             => $user_input ?: $title,
				'[Input]'             => $user_input,
				'[Selected Keywords]' => $selected_keywords,
				'[Heading Tag]'       => $heading_tag,

				// 🚨 CRITICAL FIX: Add missing V4 variables
				'[The Tagline]'       => $tagline,
				'[Tagline]'           => $tagline,
				'[Outline]'           => $outline,
				'[Heading]'           => $outline, // Legacy compatibility
				'[Introduction]'      => $introduction,
				'[Intro]'             => $introduction,
				'[No. Headings]'      => $no_headings,
				'[NoHeadings]'        => $no_headings,
				'[above/below]'       => $above_below,

				// Additional common variables
				'[Keywords]'          => $selected_keywords,
				'[HeadingTag]'        => $heading_tag,
				'[SelectedKeywords]'  => $selected_keywords,
			);

			// Apply replacements
			foreach ( $replacements as $variable => $value ) {
				if ( strpos( $processed_template, $variable ) !== false ) {
					$processed_template = str_replace( $variable, $value, $processed_template );
					$this->log_debug( "[TEMPLATE VARS] Replaced {$variable} with: {$value}" );
				}
			}

			// Check for any remaining unreplaced variables
			preg_match_all( $this->parameter_pattern, $processed_template, $matches );
			$remaining_vars = $matches[0] ?? array();

			if ( ! empty( $remaining_vars ) ) {
				$this->log_debug( '[TEMPLATE VARS] Remaining unreplaced variables: ' . implode( ', ', $remaining_vars ) );

				// Replace remaining variables with safe defaults
				foreach ( $remaining_vars as $var ) {
					$var_name           = trim( $var, '[]' );
					$default_value      = $this->get_default_variable_value( $var_name );
					$processed_template = str_replace( $var, $default_value, $processed_template );
					$this->log_debug( "[TEMPLATE VARS] Replaced remaining {$var} with default: {$default_value}" );
				}
			}

			$this->log_debug( '[TEMPLATE VARS] Final processed template: ' . substr( $processed_template, 0, 200 ) . '...' );

		} catch ( Exception $e ) {
			$this->log_error( '[TEMPLATE VARS] Error processing template variables: ' . $e->getMessage() );
			return $template; // Return original template on error
		}

		return $processed_template;
	}

	/**
	 * Get setting value with fallback
	 *
	 * @param string $setting Setting name
	 * @param string $default Default value
	 * @return string Setting value
	 */
	private function get_setting_value( $setting, $default ) {
		// Try to get from WordPress options (content settings)
		$content_settings = get_option( 'ab_gpt_content_settings', array() );

		$setting_map = array(
			'language'      => 'language',
			'writing_style' => 'writing_style',
			'writing_tone'  => 'writing_tone',
		);

		if ( isset( $setting_map[ $setting ] ) && isset( $content_settings[ $setting_map[ $setting ] ] ) ) {
			return $content_settings[ $setting_map[ $setting ] ];
		}

		return $default;
	}

	/**
	 * Get default value for unknown variables
	 *
	 * @param string $variable_name Variable name without brackets
	 * @return string Default value
	 */
	private function get_default_variable_value( $variable_name ) {
		$defaults = array(
			'Language'          => 'English',
			'Style'             => 'Business',
			'Tone'              => 'Professional',
			'Title'             => 'your topic',
			'Topic'             => 'your topic',
			'Input'             => 'your input',
			'Keywords'          => 'relevant keywords',
			'Selected Keywords' => 'relevant keywords',
			'SelectedKeywords'  => 'relevant keywords',
			'Heading Tag'       => 'H2',
			'HeadingTag'        => 'H2',
		);

		return $defaults[ $variable_name ] ?? "[$variable_name]";
	}

	// ------------------------------------------------------------------
	// v3: server-side prompt assembly (ported from 2.6.2 client-side
	// allSiteInputs(), create_template.js:697-962). All [Placeholder]
	// resolution now happens here, from ConversationService state.
	// ------------------------------------------------------------------

	/**
	 * Step number → ab_prompts_content option key (2.6.2 names, incl.
	 * the capital-K Keywords_prompts quirk — preserved for compatibility).
	 *
	 * @var array
	 */
	private static $step_option_keys = array(
		1  => 'title_prompts',
		2  => 'Keywords_prompts',
		3  => 'outline_prompts',
		4  => 'intro_prompts',
		5  => 'tagline_prompts',
		6  => 'article_prompts',
		7  => 'conclusion_prompts',
		8  => 'qa_prompts',
		9  => 'meta_prompts',
		10 => 'review_prompts',
		11 => 'evaluate_prompts',
	);

	/**
	 * Map from 2.6.2 option keys to complete-prompts.json content_prompts
	 * types, used only to gap-fill missing defaults.
	 *
	 * @var array
	 */
	private static $json_gap_fill_map = array(
		'instructions_prompts' => 'instructions',
		'title_prompts'        => 'title',
		'Keywords_prompts'     => 'keywords',
		'outline_prompts'      => 'outline',
		'intro_prompts'        => 'introduction',
		'tagline_prompts'      => 'tagline',
		'article_prompts'      => 'article',
		'conclusion_prompts'   => 'conclusion',
		'qa_prompts'           => 'qa',
		'meta_prompts'         => 'meta',
		'review_prompts'       => 'revision',
		'evaluate_prompts'     => 'evaluation',
	);

	/**
	 * Canonical default prompt library. David's 2.6.2 ab_prompts_content
	 * defaults are the canonical wording (verbatim from
	 * snailsvn/trunk/article_builder.php set_default_options()); any key
	 * missing there is gap-filled from complete-prompts.json.
	 *
	 * @return array option-key => prompt text
	 */
	public function get_default_prompts() {
		$defaults = array(
			'instructions_prompts' =>
				'These are your most basic writing instructions: Your name is AI-Scribe and you are a talented copywriter and SEO specialist. Focus on producing helpful SEO content that Google will appreciate.
                Respond using plain language. Do not provide any labels like "Section..." or "Sub-Section...". Do not provide any explanations, notes, other labelling or analysis. Follow my prompts carefully. ',
			'excluded_words'       => self::DEFAULT_EXCLUDED_WORDS,
			'title_prompts'        =>
				'Provide 5 concise, genuinely different article titles for my blog based on "[Idea]". Preserve standard acronym capitalisation. If the topic is time-sensitive, use the explicit current year where useful rather than saying "this year". Cover different search intents or angles. Return titles only.',
			'Keywords_prompts'     =>
				'For the title "[Title]", provide 5 relevant keyword phrases people may use to search for this subject. Use natural search-case wording and preserve standard acronyms. Return structured keyword objects with a role and qualitative demand band as specified by the response schema. The demand band is a rough AI estimate only, never a numeric or measured search volume.',
			'outline_prompts'      =>
				'Write an article outline titled [Title]. Create [No. Headings] sections and no sub-sections for the body of my article. Don\'t include an introduction or conclusion. This needs to be a simple list of section headings. Do not add any commentary, notes or additional information such as section labels, "Section 1", "Section 2", etc. Please include the following SEO keywords following SEO keywords [Selected Keywords] where appropriate in the headings. ',
			'intro_prompts'        =>
				'Generate a focused introduction for my article without a separate heading. Establish the reader, problem and useful outcome, without summarising every section or inventing evidence. Base it on the "[Title]" title and the [Selected Keywords]. Write in [Language] using a [Style] writing style and a [Tone] writing tone.',
			'tagline_prompts'      =>
				'Generate exactly one short tagline for my article. Base it on the "[Title]" title and the [Selected Keywords]. Return only the tagline.',
			'article_prompts'      =>
				'Write only the body sections using clean HTML, without markdown code fences. Do not repeat the H1 title, tagline, introduction, conclusion or Q&A because AI-Scribe compiles them separately.
	                Write every selected heading exactly once as a [Heading Tag]: [Heading]. Vary paragraph lengths naturally and use short lists, steps or a checklist only when they make the advice easier to follow.
	                Each section must answer a distinct reader need and add concrete explanations, actions, examples, trade-offs and pitfalls where relevant. SEO optimise naturally for the [Selected Keywords] without repeating awkward exact phrases.
	                Do not add notes, commentary or code before the first section heading or after the last paragraph.',
			'conclusion_prompts'   =>
				'Create a concise conclusion with a [Heading Tag] heading containing the word "Conclusion" followed by one or two useful paragraphs. Base it on "[Title]" and the [Selected Keywords]. Summarise the practical next action without repeating the introduction, every heading or every keyword. Do not invent urgency, evidence or guarantees.',
			'qa_prompts'           =>
				'Create [No. Headings] useful Questions and Answers based on "[Title]" and the [Selected Keywords]. Return the structured Q&A fields requested. Each answer must add practical information, a nuance, exception or next step not already repeated from the body. Do not invent research, sources, statistics or experience.',
				'meta_prompts'         =>
					'Create one accurate SEO meta title and meta description for the "[Title]" article using its full content and the [Selected Keywords]. Match the article\'s search intent and write in [Language]. Treat the first selected keyword as primary and include it naturally near the start of the title and in the description. Use sensible title case while preserving the correct capitalisation of acronyms, brands and proper nouns. Structure the title as [Primary keyword] | [specific article benefit or angle], using the spaced pipe " | " as the only component separator. Use secondary keywords only where they read naturally; never force every phrase into either field. Treat 50-60 characters for the title and 120-160 characters for the description as display guidance, not guaranteed search-engine limits. Make the title specific and the description explain the useful value a reader will get. Do not keyword-stuff or invent a brand name, statistics, credentials, offers, urgency or unsupported claims. Do not claim uniqueness because other pages have not been checked. Return only the requested fields with no markup or commentary.',
			'review_prompts'       =>
				'Please revise the above article and HTML code so that it has [No. Headings] headings using the [Heading Tag] HTML tag. Revise the text in the [Language] language. Revise with a [Style]  style and a [Tone] writing tone.',
			'evaluate_prompts'     => 'Give a strict evaluation of the article above against each question below. Answer every question as one check with a short label, a status of pass, warn or fail, a factual explanation of your reasoning, and a concrete suggestion for how to improve it (an empty suggestion is acceptable for a clear pass). Where examples such as phrases or topics help, include them in the detail or suggestion text. All answers must be factual. The questions are:
Is the length of the article over 500 words and an adequate length compared to similar articles?
Is the article optimised for certain keywords or phrases? What are these?
Is the article well-written and easy to read?
Does the article have any spelling or grammar issues?
Does the article provide an original, interesting and engaging perspective on the topic?',
			'user_instructions'    =>
				'Write for the reader first, not for a search engine. Use the spelling and grammar conventions of the selected language consistently throughout the article. Do not use em dashes; use commas, colons or full stops instead. Avoid filler phrases, cliches and generic marketing language. Prefer plain, direct sentences and vary their length. Support claims with specifics rather than superlatives. Keep headings descriptive and concise. Replace or extend these instructions with your own brand voice, style rules and terminology.',
		);

		// Gap-fill any missing key from complete-prompts.json.
		foreach ( self::$json_gap_fill_map as $option_key => $json_type ) {
			if ( ! empty( $defaults[ $option_key ] ) ) {
				continue;
			}
			$template = $this->get_prompt_template( 'content_prompts', $json_type, 'default' );
			if ( is_array( $template ) && ! empty( $template['template'] ) ) {
				$defaults[ $option_key ] = $template['template'];
			}
		}

		return $defaults;
	}

	/**
	 * The effective prompt library: saved ab_prompts_content merged over
	 * the canonical defaults (fallback reads — a 2.6.2 install keeps its
	 * edited prompts, missing keys come from defaults).
	 *
	 * Saved values are run through normalise_stored_text() so a prompt
	 * carried over from 2.6.2 does not send `Don\'t` or `&amp;` to the
	 * model. The stored option is not rewritten — the normalisation is on
	 * read only, so nothing is lost if the assumption is ever wrong.
	 *
	 * @return array option-key => prompt text
	 */
	public function get_prompt_library() {
		$defaults = $this->get_default_prompts();
		$saved    = function_exists( 'get_option' ) ? get_option( 'ab_prompts_content', array() ) : array();
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$library = $defaults;
		foreach ( $saved as $key => $value ) {
			if ( is_string( $value ) && trim( $value ) !== '' ) {
				$normalised         = self::normalise_stored_text( $value );
				$is_retired_default = isset( self::RETIRED_DEFAULT_PROMPT_HASHES[ $key ] )
					&& hash_equals( self::RETIRED_DEFAULT_PROMPT_HASHES[ $key ], hash( 'sha256', $normalised ) );
				if ( ! $is_retired_default ) {
					$library[ $key ] = $normalised;
				}
			}
		}
		return $library;
	}

	/**
	 * The unresolved prompt text for a wizard step, before any placeholder
	 * substitution.
	 *
	 * PRECEDENCE — highest wins, and this is the only place it is decided:
	 *
	 *   1. Per-run override — what the user typed into the wizard's prompt
	 *      box for this one generation. Never persisted.
	 *   2. Applied Opace AI Hub prompt — a prompt from the hub's library that the
	 *      user pinned to this step on the Prompt Library tab
	 *      (AI_Scribe_Hub_Prompt_Reader). Resolves to null, and so falls
	 *      through, whenever the hub is inactive or the prompt was deleted
	 *      there — deactivating Opace AI Hub must never break a run.
	 *   3. Saved AI-Scribe prompt — the user's own `ab_prompts_content` value.
	 *   4. AI-Scribe built-in default — get_default_prompts().
	 *
	 * Levels 3 and 4 are already merged by get_prompt_library(), so this
	 * method only has to interleave levels 1 and 2 around it.
	 *
	 * The system message is a separate axis and is NOT part of this chain:
	 * get_system_prompt() always sends [Humanizer] + [Personality] +
	 * instructions_prompts + user_instructions alongside whichever step
	 * prompt wins here, so a user's Custom Instructions apply to every run
	 * regardless of which level supplied the step prompt.
	 *
	 * @param int         $step     1-11
	 * @param string|null $override Per-run prompt override, if any.
	 * @return string Prompt text, '' when the step has none.
	 */
	private function resolve_step_prompt( $step, $override = null ) {
		// 1. Per-run override.
		if ( is_string( $override ) && trim( $override ) !== '' ) {
			return $override;
		}

		// 2. Applied Opace AI Hub prompt.
		if ( class_exists( 'AI_Scribe_Hub_Prompt_Reader' ) ) {
			$applied = AI_Scribe_Hub_Prompt_Reader::get_applied_prompt( $step );
			if ( is_string( $applied ) && trim( $applied ) !== '' ) {
				return $applied;
			}
		}

		// 3/4. Saved AI-Scribe prompt over the built-in default.
		$library = $this->get_prompt_library();
		$key     = isset( self::$step_option_keys[ $step ] ) ? self::$step_option_keys[ $step ] : null;

		return ( $key !== null && isset( $library[ $key ] ) ) ? $library[ $key ] : '';
	}

	/**
	 * Undo the backslash escaping 2.6.2 baked into stored option text.
	 *
	 * 2.6.2 saved `ab_prompts_content` straight from the magic-slashed
	 * $_POST without wp_unslash(), so every save added another layer:
	 * a value typed as O'Brien's "house" is on disk as O\'Brien\'s \"house\"
	 * after one save, O\\\'Brien… after two, and so on. v3's save path
	 * unslashes properly, so nothing new compounds — but a migrated install
	 * still carries however many layers 2.6.2 left behind, and those must
	 * not reach the model.
	 *
	 * Only escapes of the characters addslashes() touches are peeled, and
	 * only while the string still looks escaped, so an instruction that
	 * legitimately contains a lone backslash is left alone.
	 *
	 * The second artefact is the ampersand: the v3 save path runs prompts
	 * through wp_kses_post(), which encodes a bare & as &amp;. That encoding
	 * is stable rather than compounding, so one decode on read is enough to
	 * keep both the settings box and the outbound prompt showing what the
	 * user actually wrote.
	 *
	 * @param mixed $value Stored option text.
	 * @return string Normalised text.
	 */
	public static function normalise_stored_text( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		// Bounded: five layers is far beyond anything a real install has,
		// and a bound means a pathological value can never spin here.
		for ( $pass = 0; $pass < 5; $pass++ ) {
			if ( ! preg_match( '/\\\\[\'"\\\\]/', $value ) ) {
				break;
			}
			$stripped = stripslashes( $value );
			if ( $stripped === $value ) {
				break;
			}
			$value = $stripped;
		}

		// Only the ampersand: prompts legitimately contain < and > (the
		// article prompts ask for <p> and <span> tags), so a general entity
		// decode would be the wrong tool here.
		return str_replace( '&amp;', '&', $value );
	}

	/**
	 * The user's own Custom Instructions.
	 *
	 * 2.6.2's settings screen wrote the "Custom Instructions" box to
	 * `ab_prompts_content['user_instructions']` (article_builder.php:1118)
	 * while leaving `instructions_prompts` holding the built-in base text.
	 * Nothing in 2.6.2 ever read the key back, so the user's brand voice,
	 * spelling rules and banned phrases were silently dropped — v3 honours
	 * them, in the slot 2.6.2's save handler intended: last, after the base
	 * instructions, exactly where its $customInstructions variable sat in
	 * the assembled system message (article_builder.php:2750-2762).
	 *
	 * @return string Normalised custom instructions, '' when unset.
	 */
	public function get_user_instructions() {
		$saved = function_exists( 'get_option' ) ? get_option( 'ab_prompts_content', array() ) : array();
		if ( ! is_array( $saved ) || ! isset( $saved['user_instructions'] ) ) {
			return '';
		}

		return trim( self::normalise_stored_text( $saved['user_instructions'] ) );
	}

	/**
	 * The "Humanizer" writing-mode instructions.
	 *
	 * FALLBACK ONLY. The canonical wording lives in
	 * includes/prompts/complete-prompts.json under
	 * mode_specific_instructions.humanize — see get_mode_instructions() for
	 * the resolution order. This constant is the last resort for the case
	 * where the JSON file is missing or unreadable.
	 *
	 * Derived from the 2.6.2 $behuman block (trunk article_builder.php:2705)
	 * with the clauses that manufactured literal defects removed: they asked
	 * for grammatical errors, inconsistent punctuation and stray spaces,
	 * which fought the hard rules and produced output nobody wanted to
	 * publish. Personal anecdotes, humour and natural variation stay.
	 */
	const HUMANIZE_INSTRUCTIONS = <<<'EOT'
These are your 'Humanizer' writing instructions: You are not a standard GPT or ChatGPT agent so what you write and how you write (e.g. style, format, etc.) needs to be very different to how you would normally write when a user messages you on ChatGPT. This is your key differentiator! Your job is to write content that reads as though a person wrote it in one sitting, with all of the same rhythm, unpredictability and personality a human writer brings, while still being correctly spelled and punctuated.
Address the Reader Directly: Use "you," avoid "one," and use "I" or "we" when representing an organisation.
Always vary the number of paragraphs you include in each section.
Embrace Natural Variation: Let the pace and shape of the writing wander the way a person's does, without ever breaking spelling, grammar or punctuation rules.
Use Natural Language: Employ everyday, straightforward language and use jargon but only where widely understood.
Vary Vocabulary: Use rich yet simple words, avoiding overly complex or repetitive terms.
Mix Sentence and Paragraph Lengths: Combine longer, detailed sentences with shorter ones and vary paragraph lengths.
Maintain Conversational Flow: Ensure smooth transitions, allow occasional tangents, and circle back to key points.
Incorporate Humour and Humanity: Add humour, admit uncertainty where it is honest, and include personal anecdotes or cultural references when relevant.
Use Simple but Professional Style: Keep the language clear and professional without being excessive.
Apply Natural Punctuation: Vary sentence construction so punctuation differs from one sentence to the next, always used correctly.
Ensure Authenticity: Maintain a natural language flow, avoid improbable word combinations, and reflect human-like writing patterns.
Structure Content for Readability: Break down complex ideas with bullet points, numbered lists, and bold text for easy scanning.
Encourage Engagement: Pose questions, invite comments, and suggest social shares to boost interaction and signal valuable content.
Avoid Repetition: Use diverse words and phrases, ensuring varied language throughout.
Create Inspirational Moments: Introduce ideas and inspiration where applicable to engage readers.
Allow for Randomness: Mimic human variability in writing style and sentence construction.
Include Relatable Examples: Use personal stories and hypothetical scenarios to illustrate points conversationally.
Vary Sentence Openings: Start sentences differently so no two in a row follow the same shape.
Reinforce Key Points: Evolve ideas and circle back to emphasise important concepts naturally.
EOT;

	/**
	 * The "with Personality" add-on instructions.
	 *
	 * FALLBACK ONLY, exactly as HUMANIZE_INSTRUCTIONS above: the canonical
	 * wording is mode_specific_instructions.personality in
	 * complete-prompts.json. Derived from the 2.6.2 $personal block (trunk
	 * article_builder.php:2733); only the clause asking for "natural errors"
	 * is gone, because it contradicts the hard rules. The sarcastic, blunt,
	 * eccentric character is the whole point of the mode and is untouched.
	 */
	const PERSONALITY_INSTRUCTIONS = <<<'EOT'
These are your additional quirky, "hold no punches" style writing instructions for adding extra flair and personality:
Distinctive Human Style: Write uniquely with raw, unfiltered human quirks, strong opinions, uneven pacing, and occasional tangents to reflect authentic thought processes.
Sarcastic and Witty Tone: Use a sarcastic, witty, confident, and unapologetically blunt tone to highlight absurdities, convey dark humour, and show passion for helping readers.
It's ok to be eccentric. Include tongue-in-cheek remarks.
Natural Flow and Cadence: Maintain a natural writing cadence with topic jumps, deliberate pauses, and subtle contradictions to mimic wandering thoughts.
Engaging and Structured Content: Organise content into punchy, direct sections or tips with logical flow and practical takeaways wrapped in sarcastic commentary.
Catchy and Provocative Headlines: Create humorous or pun-heavy titles that grab attention and invite deeper, playful exploration of topics.
Humour and Cultural References: Incorporate cynical humour, pop culture, news, and societal references with irony and a rebellious edge.
Critical and Practical Advice: Deliver practical advice with scepticism towards trends and buzzwords, debunking myths and critically analysing popular topics.
Memorable and Thought-Provoking Style: Transform simple topics into witty, sarcastic, and memorable narratives that encourage critical thinking and reader engagement.
EOT;

	/**
	 * The built-in banned word list, used when nothing has been saved to
	 * `ab_prompts_content['excluded_words']`. Mirrors
	 * complete-prompts.json content_prompts.instructions.words_to_exclude.
	 */
	const DEFAULT_EXCLUDED_WORDS = 'Ever Changing, Ever Evolving, Testament, As A Professional, Previously Mentioned, Buckle Up, Dance, Delve, Digital Era, Dive In, Embark, Enable, Emphasise, Embracing, Enigma, Ensure, Essential, Even If, Even Though, Folks, Foster, Furthermore, Game Changer, Given That, Importantly, In Contrast, In Order To, World Of, In Today\'s, Indeed, Indelible, Essential To, Imperative, Important To, Worth Noting, Journey, Labyrinth, Landscape, Look No Further, Moreover, Navigating, Nestled, Nonetheless, Notably, Other Hand, Overall, Pesky, Promptly, Realm, Remember That, Remnant, Revolutionise, Shed Light, Symphony, Dive Into, Tapestry, That Being Said, Crucial, Considerations, Exhaustive, Thus, Put It Simply, To Summarise, Unlock, Unleash, Unleashing, Ultimately, Underscore, Vibrant, Vital';

	/**
	 * The configured writing mode: standard | humanize | personality
	 * (2.6.2 `ab_gpt_content_settings['mode']`, the plugin's marketed
	 * Humanizer differentiator).
	 *
	 * The fallback stays `standard` on purpose: a fresh install gets
	 * `humanize` because activation SEEDS it (complete-prompts.json
	 * default_settings.content.mode), not because the read path invents it.
	 * An existing site whose stored option predates the key therefore keeps
	 * the behaviour it already had.
	 *
	 * @return string
	 */
	public function get_writing_mode() {
		$content = function_exists( 'get_option' ) ? get_option( 'ab_gpt_content_settings', array() ) : array();
		$mode    = ( is_array( $content ) && isset( $content['mode'] ) ) ? (string) $content['mode'] : 'standard';
		return in_array( $mode, array( 'humanize', 'personality' ), true ) ? $mode : 'standard';
	}

	/**
	 * The configured spelling variant: british | american.
	 *
	 * British is the default everywhere — on a fresh install, and on any
	 * existing install where the key has never been written. Nothing in the
	 * plugin may quietly select American spelling.
	 *
	 * @return string 'british' or 'american'
	 */
	public function get_spelling_variant() {
		return self::spelling_variant();
	}

	/**
	 * Static twin of get_spelling_variant(), so generation entry points that
	 * do not hold a Prompt Manager instance still read the same setting.
	 *
	 * @return string
	 */
	public static function spelling_variant() {
		$content  = function_exists( 'get_option' ) ? get_option( 'ab_gpt_content_settings', array() ) : array();
		$spelling = ( is_array( $content ) && isset( $content['spelling'] ) ) ? strtolower( (string) $content['spelling'] ) : '';
		return ( 'american' === $spelling ) ? 'american' : 'british';
	}

	/**
	 * The spelling instruction sent to the model, in wording that leaves no
	 * room for interpretation.
	 *
	 * @return string
	 */
	public static function spelling_instruction() {
		if ( 'american' === self::spelling_variant() ) {
			return 'Use American English spelling and idiom throughout (organize, optimize, color, license as both noun and verb). Never use British spellings.';
		}
		return 'Use British English spelling and idiom throughout (organise, optimise, colour, licence as a noun). Never use American spellings.';
	}

	/**
	 * The user's banned word list, as a comma separated string.
	 *
	 * @return string '' when the list has been cleared.
	 */
	public function get_excluded_words() {
		$library = $this->get_prompt_library();
		$words   = isset( $library['excluded_words'] ) ? trim( (string) $library['excluded_words'] ) : '';
		return $words;
	}

	/**
	 * The canonical hard-rules block.
	 *
	 * One instance, appended after the mode persona and after the global
	 * instructions, so it applies to Standard exactly as it does to the
	 * Humanizer modes. Only the user's own Custom Instructions come after
	 * it: the user always gets the last word.
	 *
	 * @param string $excluded_words Comma separated banned words, '' to omit
	 *                               the rule entirely.
	 * @return string
	 */
	public static function hard_rules( $excluded_words = null ) {
		if ( null === $excluded_words ) {
			$library        = function_exists( 'get_option' ) ? get_option( 'ab_prompts_content', array() ) : array();
			$excluded_words = ( is_array( $library ) && isset( $library['excluded_words'] ) )
				? trim( self::normalise_stored_text( $library['excluded_words'] ) )
				: self::DEFAULT_EXCLUDED_WORDS;
		}

		$rules   = array();
		$rules[] = 'These rules are absolute and override every style instruction above.';
		$rules[] = self::spelling_instruction();
		$rules[] = 'Never use em dashes; use commas, colons or full stops instead.';
		$rules[] = 'Weave keyword phrases into sentences naturally and in sentence case, matching the capitalisation of the surrounding prose. Never drop a keyword phrase into the middle of a sentence in Title Case.';
		$rules[] = 'Sentence case never lowercases an acronym, initialism, proper noun or brand name. Always capitalise them as they are normally written, wherever they appear, including inside a keyword phrase: SEO, CTA, CEO, CRM, API, B2B, ROI, UK, US, AI, WordPress, Google, Yoast. "local SEO tips", never "local seo tips".';

		$excluded_words = trim( (string) $excluded_words );
		if ( '' !== $excluded_words ) {
			$rules[] = 'Never use any of these words or phrases, and never use a phrase that contains one: ' . rtrim( $excluded_words, '. ' ) . '.';
		}

		return "These are your hard rules:\n" . implode( "\n", $rules );
	}

	/**
	 * Instance wrapper around hard_rules(), using this manager's resolved
	 * prompt library for the banned word list.
	 *
	 * @return string
	 */
	public function get_hard_rules() {
		return self::hard_rules( $this->get_excluded_words() );
	}

	/**
	 * The writing-mode persona text for a mode key.
	 *
	 * SINGLE SOURCE OF TRUTH, in this order:
	 *   1. complete-prompts.json mode_specific_instructions.<key>
	 *   2. the `ab_mode_instructions` option (the seeded mirror of 1, and
	 *      what pre-3.2 installs still hold)
	 *   3. the PHP constant
	 *
	 * The JSON file leads because nothing in the plugin lets a user edit the
	 * mode text: the file is what ships, so a stale option seeded by an
	 * earlier version must not outrank it. The option is kept as a
	 * compatibility read for anything that still consults it directly, and
	 * activation re-seeds it from the same JSON.
	 *
	 * `personal` is accepted as an alias of `personality` — the JSON used to
	 * name the key that way while code and stored settings said
	 * `personality`.
	 *
	 * @param string $mode_key humanize | personality
	 * @return string
	 */
	public function get_mode_instructions( $mode_key ) {
		$mode_key = ( 'personal' === $mode_key ) ? 'personality' : (string) $mode_key;
		$aliases  = ( 'personality' === $mode_key ) ? array( 'personality', 'personal' ) : array( $mode_key );

		foreach ( $aliases as $key ) {
			$template = $this->get_prompt_template( 'mode_specific_instructions', $key );
			if ( is_array( $template ) && ! empty( $template['template'] ) ) {
				return (string) $template['template'];
			}
		}

		$stored = function_exists( 'get_option' ) ? get_option( 'ab_mode_instructions', array() ) : array();
		if ( is_array( $stored ) ) {
			foreach ( $aliases as $key ) {
				if ( ! empty( $stored[ $key ]['template'] ) ) {
					return (string) $stored[ $key ]['template'];
				}
			}
		}

		return ( 'personality' === $mode_key ) ? self::PERSONALITY_INSTRUCTIONS : self::HUMANIZE_INSTRUCTIONS;
	}

	/**
	 * The system prompt (instructions) for the conversation thread.
	 *
	 * Composition, top to bottom:
	 *   [Year + language/style/tone] — 2.6.2 opened every system message with
	 *                           "The year is YYYY." (article_builder.php:2690)
	 *                           so the model does not write as though it were
	 *                           still in its training year.
	 *   [Humanizer block]   — writing mode humanize / personality
	 *   [Personality block] — writing mode personality only
	 *   [Global instructions] — ab_prompts_content['instructions_prompts']
	 *   [Hard rules]        — spelling, no em dashes, banned words, keyword
	 *                           casing. One canonical block, after the
	 *                           persona, so every mode including Standard
	 *                           gets it.
	 *   [Custom Instructions] — ab_prompts_content['user_instructions'],
	 *                           the user's own brand voice, last so it has
	 *                           the final word over everything above it.
	 *
	 * @param array|null $settings Optional conversation settings snapshot.
	 *                             Falls back to the saved content settings.
	 * @return string
	 */
	public function get_system_prompt( $settings = null ) {
		$library      = $this->get_prompt_library();
		$instructions = isset( $library['instructions_prompts'] ) ? $library['instructions_prompts'] : '';
		$instructions = self::strip_excluded_words_clause( $instructions );

		$settings = is_array( $settings ) ? $settings : array();
		$language = isset( $settings['language'] ) && '' !== $settings['language']
			? (string) $settings['language'] : $this->get_setting_value( 'language', 'English' );
		$style    = isset( $settings['writing_style'] ) && '' !== $settings['writing_style']
			? (string) $settings['writing_style'] : $this->get_setting_value( 'writing_style', 'Business' );
		$tone     = isset( $settings['writing_tone'] ) && '' !== $settings['writing_tone']
			? (string) $settings['writing_tone'] : $this->get_setting_value( 'writing_tone', 'Professional' );

		$parts = array(
			'The year is ' . gmdate( 'Y' ) . ". Write in the {$language} language using a {$style} writing style and a {$tone} writing tone.",
		);

		switch ( $this->get_writing_mode() ) {
			case 'humanize':
				$parts[] = $this->get_mode_instructions( 'humanize' );
				break;
			case 'personality':
				$parts[] = $this->get_mode_instructions( 'humanize' );
				$parts[] = $this->get_mode_instructions( 'personality' );
				break;
		}

		if ( trim( $instructions ) !== '' ) {
			$parts[] = trim( $instructions );
		}

		$parts[] = $this->get_hard_rules();

		$custom = $this->get_user_instructions();
		if ( '' !== $custom ) {
			$parts[] = $custom;
		}

		return trim( implode( "\n\n", array_filter( array_map( 'trim', $parts ), 'strlen' ) ) );
	}

	/**
	 * Remove any banned-word clause carried by the global instructions.
	 *
	 * The hard-rules block owns that instruction now, and it must appear
	 * exactly once. Pre-3.2 installs have `instructions_prompts` seeded from
	 * complete-prompts.json, whose template embedded the list behind a
	 * `{excluded_words}` placeholder; a 2.6.2 upgrade instead carries the
	 * list inline behind "words or phrases that contain them:". Both forms
	 * are stripped here, on read only, so nothing is lost on disk.
	 *
	 * @param string $instructions
	 * @return string
	 */
	public static function strip_excluded_words_clause( $instructions ) {
		$instructions = (string) $instructions;

		if ( false !== strpos( $instructions, '{excluded_words}' ) ) {
			// The whole shipped clause, lead-in and consequence sentences
			// included, so nothing dangles once the list is removed.
			$instructions = preg_replace(
				'/CRITICAL REQUIREMENT:.*?alternative expressions instead\./s',
				'',
				$instructions
			);
			// Any other host wording: drop just the sentence holding the token.
			$instructions = preg_replace( '/[^.]*\{excluded_words\}[^.]*\.\s*/', '', $instructions );
			$instructions = str_replace( '{excluded_words}', '', $instructions );
		}

		// 2.6.2's inline list, up to the end of that sentence.
		$instructions = preg_replace(
			'/Do not use these\s+these words or phrases that contain them:.*?(?:\.\s|\z)/s',
			'',
			$instructions
		);

		return trim( preg_replace( '/[ \t]{2,}/', ' ', $instructions ) );
	}

	/**
	 * Assemble the final prompt for a wizard step, resolving every
	 * placeholder server-side from conversation state.
	 *
	 * Faithful port of 2.6.2 allSiteInputs() semantics:
	 * [Idea] [Title] [Selected Keywords] [Heading] [Intro] [The Tagline]
	 * [Language] [Style] [Tone] [Heading Tag] [No. Headings] [above/below],
	 * exclude-keywords append (not steps 9/11), empty-keyword sentence
	 * removal, skip-tagline and skip-keywords handling.
	 *
	 * @param int         $step       1-11
	 * @param array       $settings   Conversation settings snapshot.
	 * @param array       $selections Conversation selections.
	 * @param string|null $override   Optional user-edited prompt for this run
	 *                                (per-run prompt override feature).
	 * @param array       $flags      {skip_tagline: bool, skip_keywords: bool}
	 * @return string Assembled prompt.
	 */
	public function assemble_step_prompt( $step, array $settings, array $selections, $override = null, array $flags = array() ) {
		$step   = (int) $step;
		$prompt = $this->resolve_step_prompt( $step, $override );

		if ( $prompt === '' ) {
			return '';
		}

		$skip_tagline  = ! empty( $flags['skip_tagline'] )
			|| ( array_key_exists( 'tagline', $selections ) && $this->is_blank( $selections['tagline'] ) );
		$skip_keywords = ! empty( $flags['skip_keywords'] );

		// Step 6 with tagline skipped: remove the whole tagline sentence
		// (works for both the seeded template's wording and the legacy
		// 2.6.2 "Add a tagline called …" form), then any stragglers.
		if ( $step === 6 && $skip_tagline ) {
			$prompt = preg_replace( '/[^.]*\[The Tagline\][^.]*\.\s*/', '', $prompt );
			$prompt = str_replace( 'Add a tagline called', '', $prompt );
			$prompt = str_replace( '[above/below].', '', $prompt );
			$prompt = str_replace( '[The Tagline]', '', $prompt );
		}

		// Simple scalar placeholders.
		$language    = isset( $settings['language'] ) ? (string) $settings['language'] : 'English';
		$style       = isset( $settings['writing_style'] ) ? (string) $settings['writing_style'] : 'Business';
		$tone        = isset( $settings['writing_tone'] ) ? (string) $settings['writing_tone'] : 'Professional';
		$heading_tag = ! empty( $settings['heading_tag'] ) ? (string) $settings['heading_tag'] : 'H2';
		$no_headings = isset( $settings['number_of_headings'] ) ? (string) (int) $settings['number_of_headings'] : '5';
		$above_below = ( isset( $settings['tagline_position'] ) && $settings['tagline_position'] === 'above' ) ? 'above' : 'below';

		$prompt = str_replace( '[Language]', $language, $prompt );
		$prompt = str_replace( '[Style]', $style, $prompt );
		$prompt = str_replace( '[Tone]', $tone, $prompt );

		// [Idea] — the raw topic, leading list numbering stripped (2.6.2 quirk).
		$idea = isset( $settings['idea'] ) ? trim( (string) $settings['idea'] ) : '';
		$idea = preg_replace( '/^([\d\W]\.\s*)/', '', $idea );
		$idea = str_replace( array( '"', "'" ), '', $idea );
		if ( $idea !== '' ) {
			$prompt = str_replace( '[Idea]', $idea, $prompt );
		}

		// [Title] — the selected title.
		$title = isset( $selections['title'] ) ? trim( (string) $selections['title'] ) : '';
		if ( $title !== '' ) {
			$prompt = str_replace( '[Title]', $title, $prompt );
		}

		if ( $no_headings !== '' ) {
			$prompt = str_replace( '[No. Headings]', $no_headings, $prompt );
		}

		// Step 3 with keywords skipped: 2.6.2 removed the trailing clause.
		if ( $step === 3 && $skip_keywords ) {
			$prompt = str_replace( 'and [Selected Keywords].', '', $prompt );
		}

		// [Selected Keywords] — quoted list with the 2.6.2 join, or the
		// sentence-removal fallbacks when no keywords were selected.
		$keywords = $this->selection_as_array( isset( $selections['keywords'] ) ? $selections['keywords'] : null );
		if ( $skip_keywords ) {
			$keywords = array();
		}
		if ( ! empty( $keywords ) ) {
			$quoted = array();
			foreach ( $keywords as $keyword ) {
				$keyword = trim( preg_replace( '/[^\w\s,.]/', '', (string) $keyword ) );
				if ( $keyword !== '' ) {
					$quoted[] = '"' . $keyword . '"';
				}
			}
			$sel_keyword = 'following SEO keywords ' . implode( ' and ', $quoted );
			$prompt      = str_replace( '[Selected Keywords]', $sel_keyword, $prompt );
		} else {
			// The shipped 2.6.2 outline default duplicates the lead-in
			// ("…following SEO keywords following SEO keywords…") — remove
			// that variant first, then the plain sentence.
			$prompt = str_replace( 'Please include the following SEO keywords following SEO keywords [Selected Keywords] where appropriate in the headings.', '', $prompt );
			$prompt = str_replace( 'Please include the following SEO keywords [Selected Keywords] where appropriate in the headings.', '', $prompt );
			$prompt = str_replace( 'and the [Selected Keywords]', '', $prompt );
			$prompt = str_replace( 'SEO optimise the content for the [Selected Keywords].', '', $prompt );
			$prompt = str_replace( 'SEO optimise the article for the [Selected Keywords].', '', $prompt );
			$prompt = str_replace( 'and optimise for the [Selected Keywords]', '', $prompt );
			$prompt = str_replace( '[Keywords Bold].', '', $prompt );
			// Any remaining bare occurrences resolve to nothing rather than leaking the token.
			$prompt = str_replace( 'the [Selected Keywords]', '', $prompt );
			$prompt = str_replace( '[Selected Keywords]', '', $prompt );
		}

		// [above/below]
		$prompt = str_replace( '[above/below]', $above_below, $prompt );

		// [Heading Tag]
		if ( $heading_tag !== '' ) {
			$prompt = str_replace( '[Heading Tag]', $heading_tag, $prompt );
		}

		// [Heading] — selected outline headings. A numbered, line-separated
		// contract prevents a model from treating a quoted comma string as a
		// loose set of suggestions (and silently dropping Generate More items).
		$outline = $this->selection_as_array( isset( $selections['outline'] ) ? $selections['outline'] : null );
		if ( ! empty( $outline ) ) {
			$unique_outline = array();
			$seen_outline   = array();
			foreach ( $outline as $heading ) {
				$heading  = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( (string) $heading, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) );
				$identity = function_exists( 'mb_strtolower' ) ? mb_strtolower( $heading, 'UTF-8' ) : strtolower( $heading );
				if ( '' === $heading || isset( $seen_outline[ $identity ] ) ) {
					continue;
				}
				$seen_outline[ $identity ] = true;
				$unique_outline[]          = $heading;
			}
			$numbered    = array();
			foreach ( $unique_outline as $index => $heading ) {
				$numbered[] = ( $index + 1 ) . '. ' . $heading;
			}
			$heading_sel = implode( "\n", $numbered );
			$prompt      = str_replace( '[Heading]', $heading_sel, $prompt );
			$prompt      = str_replace( '[Outline]', $heading_sel, $prompt );
			if ( 6 === $step ) {
				$prompt .= "\n\nOUTLINE COVERAGE CONTRACT: Write exactly one body section for each of the " . count( $unique_outline ) . " numbered headings above, in that order. Copy every heading exactly into its own <{$heading_tag}> tag. Do not omit, rename, merge, repeat or add headings.";
			}
		}

		// [Intro]
		$intro = isset( $selections['introduction'] ) ? trim( (string) $selections['introduction'] ) : '';
		if ( $intro !== '' ) {
			$prompt = str_replace( '[Intro]', $intro, $prompt );
		} else {
			$prompt = str_replace( 'The following introduction should be at the top: ', '', $prompt );
			$prompt = str_replace( 'Include the introduction: [Intro].', '', $prompt );
			$prompt = str_replace( '[Intro]', '', $prompt );
		}

		// [The Tagline] — the raw selected tagline. The templates already
		// carry their own placement/formatting wording around the quoted
		// placeholder, so substituting a composed instruction here nested one
		// instruction inside another (C-12-1: `Add the tagline "add the
		// tagline "…" below the introduction …" in a <p> tag …`).
		$tagline = isset( $selections['tagline'] ) ? trim( (string) $selections['tagline'] ) : '';
		if ( $tagline !== '' && ! $skip_tagline ) {
			$prompt = str_replace( '[The Tagline]', $tagline, $prompt );
		}

		// Exclude-keywords append — never on steps 9 (meta) and 11 (evaluate).
		$avoid = isset( $settings['avoid_keywords'] ) ? trim( (string) $settings['avoid_keywords'] ) : '';
		if ( $avoid !== '' && $step !== 9 && $step !== 11 ) {
			$avoid_list = array_filter( array_map( 'trim', explode( ',', $avoid ) ) );
			if ( ! empty( $avoid_list ) ) {
				$prompt .= ' Exclude the following keywords "' . implode( '", "', $avoid_list )
					. '" if they have been provided. ';
			}
		}

		// check_Arr enhancement questions on the Evaluate step (2.6.2 parity:
		// trunk create_template.js appended these to the evaluate prompt).
		if ( $step === 11 ) {
			$prompt .= $this->evaluation_enhancement_clauses();
		}

		// Stable generation policies apply to upgraded, Opace AI Hub and per-run
		// prompts without overwriting the user's saved wording.
		if ( 1 === $step ) {
			$year   = gmdate( 'Y' );
			$today  = gmdate( 'j F Y' );
			$policy = "TITLE ACCURACY POLICY: Today's date is {$today} and the current year is {$year}. "
				. 'Preserve standard acronym and brand capitalisation, including SEO, AI, API, URL, HTML and WordPress. '
				. 'Every suggestion must match the supplied topic and use a genuinely different search intent or angle. '
				. 'If the topic says "this year", "current", "latest" or otherwise depends on freshness, use the explicit current year in at least two suggestions and make the remaining suggestions clearly evergreen. '
				. 'Never leave the vague phrase "this year" in a suggested title. Return five concise titles only.';
			$prompt .= "\n\n" . apply_filters( 'ai_scribe_title_accuracy_policy', $policy, $settings, $selections );
		}

		if ( 2 === $step ) {
			$policy = 'KEYWORD DEMAND POLICY: Ignore any earlier instruction requesting numeric search volume, competition, difficulty, rankings or a pipe-delimited metric format. Return five relevant keyword objects in natural search-case wording. '
				. 'For each object, set keyword to the phrase; role to primary, supporting or long-tail; demand_band to low, medium, high or unknown; and estimate_basis to ai_unverified. Assign exactly one primary keyword. '
				. 'The demand band is only a rough AI estimate based on general language patterns, search intent and topic breadth. It is unverified, is not live keyword-tool data, and must never contain or imply a numeric search volume. Use unknown when there is not a reasonable basis for a qualitative estimate.';
			$prompt .= "\n\n" . apply_filters( 'ai_scribe_keyword_evidence_policy', $policy, $settings, $selections );
		}

		if ( 5 === $step ) {
			$policy = 'TAGLINE ACCURACY POLICY: Return exactly one short tagline. It must be specific to the selected article title and keywords, not a generic slogan. Do not return alternatives, duplicates, labels, quotation marks or commentary.';
			$prompt .= "\n\n" . apply_filters( 'ai_scribe_tagline_accuracy_policy', $policy, $settings, $selections );
		}

		if ( 6 === $step ) {
			$policy = 'ARTICLE BODY BOUNDARY: Return only the body sections from the selected outline. Do not repeat the article H1, introduction, tagline, conclusion or Q&A; AI-Scribe compiles those separately in Review. Keep every section relevant to the selected title and keywords.';
			$plan    = AI_Scribe_Article_Plan_Service::build( $settings, $selections );
			$prompt .= "\n\n" . apply_filters( 'ai_scribe_article_body_policy', $policy, $settings, $selections );
			$prompt .= "\n\n" . AI_Scribe_Article_Plan_Service::prompt_contract( $plan, true );
		}

		if ( 3 === $step ) {
			$plan    = AI_Scribe_Article_Plan_Service::build( $settings, $selections );
			$prompt .= "\n\nARTICLE PLAN CONTEXT: The complete article is planned for {$plan['min_words']}-{$plan['max_words']} useful words across exactly {$plan['outline_count']} main sections. Return only the outline headings; do not attempt to write the article here.";
		}

		$stage_names = array( 4 => 'introduction', 7 => 'conclusion', 8 => 'qna' );
		if ( isset( $stage_names[ $step ] ) ) {
			$plan    = AI_Scribe_Article_Plan_Service::build( $settings, $selections );
			$prompt .= "\n\n" . AI_Scribe_Article_Plan_Service::stage_contract( $plan, $stage_names[ $step ] );
		}

		// Stable, factual rules belong outside the editable prompt library so
		// upgraded/customised prompts receive them without being overwritten.
		if ( $step === 9 ) {
			$policy = 'SEO META ACCURACY POLICY: Accurately represent the final article and its search intent. '
				. 'Treat the first selected keyword as primary. Include its exact phrase naturally near the start of the meta title and exactly in the meta description while preserving the correct capitalisation of acronyms, brands and proper nouns. '
				. 'Write the meta title in sensible title case. For every selected secondary keyword, make a best-effort attempt to cover it in BOTH fields: prefer the exact phrase, then intelligently combine its terms, then use a meaningful partial when space or natural wording prevents stronger coverage. Self-check each secondary against each field before returning. Never keyword-stuff, repeat awkward phrases or sacrifice accuracy and readability. '
				. 'Structure the title as [Primary keyword] | [specific article benefit or angle]. Use the spaced pipe " | " as the only separator between title components; do not use a colon, hyphen, dash or slash as a component separator. '
				. 'Treat 50-60 title characters and 120-160 description characters as display guidance, not guaranteed search-engine limits. '
				. 'Rewrite surrounding copy rather than removing or distorting the primary keyword merely to meet those guides. '
				. 'Do not invent a brand name, statistics, credentials, offers, urgency or claims unsupported by the article. '
				. 'Do not claim uniqueness or guaranteed search-result display because those cannot be verified from this article alone.';
			$prompt .= "\n\n" . apply_filters( 'ai_scribe_seo_meta_policy', $policy, $settings, $selections );
		}

		// 2.6.2 stripped stray backslashes last.
		$prompt = str_replace( '\\', '', $prompt );

		return $prompt;
	}

	/**
	 * The enabled check_Arr enhancement toggles (global content settings).
	 * Legacy format: check_Arr['addQNA'] === 'addQNA' when enabled.
	 *
	 * @return array key => bool for all seven 2.6.2 toggles.
	 */
	public function get_enhancement_toggles() {
		$keys      = array( 'addQNA', 'addkeywordBold', 'addinsertHyper', 'addinsertToc', 'addfurtheReading', 'addsubMatter', 'addimgCont' );
		$content   = function_exists( 'get_option' ) ? get_option( 'ab_gpt_content_settings', array() ) : array();
		$check_arr = ( is_array( $content ) && isset( $content['check_Arr'] ) && is_array( $content['check_Arr'] ) )
			? $content['check_Arr'] : array();
		$toggles   = array();
		foreach ( $keys as $key ) {
			$toggles[ $key ] = isset( $check_arr[ $key ] ) && $check_arr[ $key ] === $key;
		}
		return $toggles;
	}

	/**
	 * Evaluate-step questions for the enabled enhancement toggles —
	 * verbatim 2.6.2 wording (trunk create_template.js:806-822).
	 *
	 * @return string Clauses to append to the step-11 prompt.
	 */
	private function evaluation_enhancement_clauses() {
		$toggles = $this->get_enhancement_toggles();
		$clauses = array(
			'addsubMatter'     => "\nHave any authorities on the subject matter been included in the text? If not, list people who could be added.",
			'addimgCont'       => "\nHave any IMG tags been added within the HTML? If not, list the kinds of image and video content that would complement the article, Also, give examples of suitable royalty-free sites where to find them.",
			'addfurtheReading' => "\nHas a section for further reading been included in the text? If not, list related topics that could be added.",
			'addinsertHyper'   => "\nHave any A tags been added within the HTML? If not, list relevant phrases within the article where hyperlinks could be added? Suggest potential domains for these hyperlinks.",
			'addkeywordBold'   => "\nHave any STRONG tags been added within the HTML? If not, list important phrases within the article where bold tags could be added",
		);
		$out     = '';
		foreach ( $clauses as $key => $clause ) {
			if ( ! empty( $toggles[ $key ] ) ) {
				$out .= $clause;
			}
		}
		return $out;
	}

	/**
	 * Normalise a selection value into an array of strings.
	 *
	 * @param mixed $value string (possibly JSON or comma-separated) or array
	 * @return array
	 */
	private function selection_as_array( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $item ) {
				if ( is_array( $item ) ) {
					$item = isset( $item['keyword'] ) ? $item['keyword'] : '';
				}
				if ( is_scalar( $item ) ) {
					$item = trim( (string) $item );
					if ( '' !== $item ) {
						$out[] = $item;
					}
				}
			}
			return array_values( $out );
		}
		if ( ! is_string( $value ) || trim( $value ) === '' ) {
			return array();
		}
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			return $this->selection_as_array( $decoded );
		}
		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' ) );
	}

	/**
	 * @param mixed $value
	 * @return bool True when the value is empty/blank.
	 */
	private function is_blank( $value ) {
		if ( is_array( $value ) ) {
			return count( $value ) === 0;
		}
		return trim( (string) $value ) === '';
	}

	/**
	 * Reload prompt templates from files
	 *
	 * @return bool Success status
	 */
	public function reload_templates() {
		$this->prompt_cache = array();
		$this->load_prompt_templates();

		$this->log_info( 'Prompt templates reloaded' );
		return ! empty( $this->prompt_cache );
	}

	/**
	 * Get all loaded prompts from cache
	 *
	 * @return array All loaded prompts
	 */
	public function get_all_prompts() {
		$this->log_debug( '[GET_ALL_PROMPTS] Returning all cached prompts' );
		$this->log_debug( '[GET_ALL_PROMPTS] Cache keys: ' . implode( ', ', array_keys( $this->prompt_cache ) ) );

		// Return the default prompts (which should contain all the content_prompts)
		if ( isset( $this->prompt_cache['default'] ) ) {
			return $this->prompt_cache['default'];
		}

		// Fallback to entire cache if default not found
		return $this->prompt_cache;
	}
}
