<?php
/**
 * Engine Service for AI-Scribe Plugin
 *
 * Handles AI engine operations and data processing using the existing AI-Core infrastructure,
 * Config Manager, and Prompt Manager for proper modular architecture.
 *
 * Migrated from engine_request_data() and related functions in article_builder_backup.php
 * preserving 100% of original functionality while using proper architecture.
 *
 * @package AI_Scribe
 * @subpackage Services
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Engine_Service
 *
 * Properly refactored to use existing infrastructure:
 * - AI-Core Adapter for all API calls
 * - Config Manager for all settings
 * - Prompt Manager for all prompts
 * - Security Service for nonce validation
 */
class AI_Scribe_Engine_Service extends AI_Scribe_Base_Service {

	/**
	 * AI-Core adapter instance
	 *
	 * @var AI_Scribe_AI_Core_Adapter
	 */
	private $ai_core_adapter;

	/**
	 * Config manager instance
	 *
	 * @var AI_Scribe_Config_Manager
	 */
	private $config_manager;

	/**
	 * Prompt manager instance
	 *
	 * @var AI_Scribe_Prompt_Manager
	 */
	private $prompt_manager;

	/**
	 * Security service instance
	 *
	 * @var AI_Scribe_Security_Service
	 */
	private $security_service;

	/**
	 * Constructor
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 * @param AI_Scribe_AI_Core_Adapter $ai_core_adapter AI-Core adapter
	 * @param AI_Scribe_Config_Manager $config_manager Config manager
	 * @param AI_Scribe_Prompt_Manager $prompt_manager Prompt manager
	 * @param AI_Scribe_Security_Service $security_service Security service
	 */
	public function __construct(
		AI_Scribe_Logger $logger,
		AI_Scribe_AI_Core_Adapter $ai_core_adapter,
		AI_Scribe_Config_Manager $config_manager,
		AI_Scribe_Prompt_Manager $prompt_manager,
		AI_Scribe_Security_Service $security_service
	) {
		parent::__construct( $logger );
		$this->ai_core_adapter  = $ai_core_adapter;
		$this->config_manager   = $config_manager;
		$this->prompt_manager   = $prompt_manager;
		$this->security_service = $security_service;
	}

	/**
	 * Initialize the service
	 *
	 * @return void
	 */
	protected function initialize() {
		// AJAX handlers now centralized in Plugin Initializer
		// No direct AJAX registration needed in service classes
	}

	/**
	 * Validate service configuration
	 *
	 * @return bool|array True if valid, error array if invalid
	 */
	public function validate_service() {
		if ( ! $this->ai_core_adapter ) {
			$this->log_error( 'Engine service validation failed: AI-Core adapter not available' );
			return 'AI-Core adapter not available';
		}

		if ( ! $this->config_manager ) {
			$this->log_error( 'Engine service validation failed: Config manager not available' );
			return 'Config manager not available';
		}

		return true;
	}

	/**
	 * Handle engine request
	 *
	 * Migrated from engine_request_data() function preserving ALL functionality:
	 * - Request validation and processing
	 * - Model-specific handling
	 * - Response formatting
	 * - Error handling
	 *
	 * @return void
	 */
	public function handle_engine_request() {
		// Verify nonce using Security Service
		if ( ! $this->security_service->verify_nonce( $_POST['security'] ?? '', 'ai_scribe_nonce' ) ) {
			wp_send_json_error(
				array(
					'msg'           => 'Security nonce is missing or invalid. Please refresh the page.',
					'nonce_expired' => true,
				)
			);
			return;
		}

		// Sanitize and validate input data
		$request_data = $this->sanitize_request_data( $_POST );

		if ( is_wp_error( $request_data ) ) {
			wp_send_json_error(
				array(
					'msg' => $request_data->get_error_message(),
				)
			);
			return;
		}

		// Process the engine request
		$result = $this->process_engine_request( $request_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'msg'   => $result->get_error_message(),
					'debug' => $result->get_error_data(),
				)
			);
			return;
		}

		wp_send_json_success( $result );
	}

	/**
	 * Sanitize request data
	 *
	 * @param array $post_data POST data
	 * @return array|WP_Error Sanitized data or error
	 */
	private function sanitize_request_data( $post_data ) {
		$sanitized = array();

		// Required fields
		$required_fields = array( 'prompt', 'action_type' );
		foreach ( $required_fields as $field ) {
			if ( empty( $post_data[ $field ] ) ) {
				return new WP_Error( 'missing_field', "Required field '{$field}' is missing" );
			}
			$sanitized[ $field ] = sanitize_text_field( $post_data[ $field ] );
		}

		// Optional fields
		$optional_fields = array(
			'model'             => 'gpt-4o-mini',
			'temperature'       => 0.7,
			'max_tokens'        => 4000,
			'top_p'             => 1.0,
			'frequency_penalty' => 0.0,
			'presence_penalty'  => 0.0,
			'language'          => 'English',
			'style'             => 'Professional',
			'tone'              => 'Neutral',
			'existing_results'  => array(), // 🚨 CRITICAL FIX: Support existing results for Generate More
			'generate_more'     => false, // 🚨 CRITICAL FIX: Support Generate More flag
		);

		foreach ( $optional_fields as $field => $default ) {
			if ( isset( $post_data[ $field ] ) ) {
				if ( in_array( $field, array( 'temperature', 'max_tokens', 'top_p', 'frequency_penalty', 'presence_penalty' ) ) ) {
					$sanitized[ $field ] = floatval( $post_data[ $field ] );
				} elseif ( $field === 'existing_results' ) {
					// 🚨 CRITICAL FIX: Handle existing_results array
					$sanitized[ $field ] = is_array( $post_data[ $field ] ) ? array_map( 'sanitize_text_field', $post_data[ $field ] ) : array();
				} elseif ( $field === 'generate_more' ) {
					// 🚨 CRITICAL FIX: Handle generate_more boolean
					$sanitized[ $field ] = (bool) $post_data[ $field ];
				} else {
					$sanitized[ $field ] = sanitize_text_field( $post_data[ $field ] );
				}
			} else {
				$sanitized[ $field ] = $default;
			}
		}

		// Sanitize prompt content
		$sanitized['prompt'] = wp_kses_post( $post_data['prompt'] );

		return $sanitized;
	}

	/**
	 * Process engine request
	 *
	 * @param array $request_data Sanitized request data
	 * @return array|WP_Error Processing result
	 */
	private function process_engine_request( $request_data ) {
		$debug_messages   = array();
		$debug_messages[] = '🔧 ENGINE SERVICE: Processing request';
		$debug_messages[] = 'Action Type: ' . $request_data['action_type'];
		$debug_messages[] = 'Model: ' . $request_data['model'];

		// Get model configuration using Config Manager
		$model       = $this->config_manager->get( 'ai_engine.model', $request_data['model'] );
		$temperature = $this->config_manager->get( 'ai_engine.temp', $request_data['temperature'] );
		$max_tokens  = $this->config_manager->get( 'ai_engine.max_tokens', $request_data['max_tokens'] );

		// Validate API keys
		$api_validation     = $this->ai_core_adapter->validate_api_keys();
		$is_anthropic_model = $this->is_anthropic_model( $model );

		if ( $is_anthropic_model && ! $api_validation['anthropic']['configured'] ) {
			return new WP_Error( 'missing_api_key', 'Anthropic API key required for Claude models' );
		}

		if ( ! $is_anthropic_model && ! $api_validation['openai']['configured'] ) {
			return new WP_Error( 'missing_api_key', 'OpenAI API key required for GPT models' );
		}

		// Build system message based on action type
		$system_message = $this->build_system_message_for_action(
			$request_data['action_type'],
			$request_data['language'],
			$request_data['style'],
			$request_data['tone']
		);

		// 🚨 CRITICAL FIX: Modify prompt for Generate More requests
		$user_prompt = $request_data['prompt'];
		if ( $request_data['generate_more'] && ! empty( $request_data['existing_results'] ) ) {
			$existing_results_text = "\n\nPrevious results to avoid duplicating:\n- " . implode( "\n- ", $request_data['existing_results'] ) . "\n\nGenerate completely different content that does not repeat any of the above.";
			$user_prompt           = 'Generate additional and different content that is distinct from any previously generated. Focus on alternative angles and related terms.' . $existing_results_text . "\n\n" . $user_prompt;
		}

		// Prepare messages
		$messages = array(
			array(
				'role'    => 'system',
				'content' => $system_message,
			),
			array(
				'role'    => 'user',
				'content' => $user_prompt,
			),
		);

		// Set generation parameters
		$parameters = array(
			'temperature'       => floatval( $temperature ),
			'top_p'             => floatval( $request_data['top_p'] ),
			'frequency_penalty' => floatval( $request_data['frequency_penalty'] ),
			'presence_penalty'  => floatval( $request_data['presence_penalty'] ),
			'max_tokens'        => intval( $max_tokens ),
		);

		// Handle o3 model specific parameters
		if ( strpos( $model, 'o3' ) !== false ) {
			// Remove temperature and other standard parameters for o3 models
			unset( $parameters['temperature'] );
			unset( $parameters['top_p'] );
			unset( $parameters['frequency_penalty'] );
			unset( $parameters['presence_penalty'] );

			// Use reasoning_effort from settings (should be 'low', 'medium', or 'high')
			if ( isset( $request_data['reasoning_effort'] ) ) {
				$parameters['reasoning_effort'] = $request_data['reasoning_effort'];
			} else {
				// Default to medium if not specified
				$parameters['reasoning_effort'] = 'medium';
			}

			$debug_messages[] = 'o3 model detected - using reasoning_effort: ' . $parameters['reasoning_effort'];
		}

		$debug_messages[] = 'Parameters: ' . json_encode( $parameters );

		// Generate content using AI-Core Adapter
		$response = $this->ai_core_adapter->generate_text( $model, $messages, $parameters );

		if ( is_wp_error( $response ) ) {
			$debug_messages[] = '❌ Generation failed: ' . $response->get_error_message();
			return new WP_Error( 'generation_failed', $response->get_error_message(), $debug_messages );
		}

		$debug_messages[] = '✅ Generation successful';

		// Format response based on action type
		return $this->format_response( $response, $request_data['action_type'], $debug_messages );
	}

	/**
	 * Check if model is Anthropic
	 *
	 * @param string $model Model name
	 * @return bool
	 */
	private function is_anthropic_model( $model ) {
		return strpos( $model, 'claude' ) !== false ||
				in_array(
					$model,
					array(
						'claude-sonnet-4-20250514',
						'claude-opus-4-20250514',
						'claude-3-5-sonnet-20250514',
					)
				);
	}

	/**
	 * Build system message for specific action
	 *
	 * @param string $action_type Action type
	 * @param string $language Target language
	 * @param string $style Writing style
	 * @param string $tone Writing tone
	 * @return string System message
	 */
	private function build_system_message_for_action( $action_type, $language, $style, $tone ) {
		// Year first (2.6.2 parity) and the hard rules last, exactly as the
		// wizard and Express paths assemble them.
		$base_message = 'The year is ' . gmdate( 'Y' ) . ". You are an expert AI assistant. Write in {$language} using a {$style} style and {$tone} tone.";
		$hard_rules   = "\n\n" . AI_Scribe_Prompt_Manager::hard_rules();

		switch ( $action_type ) {
			case 'content_generation':
				return $base_message . ' Focus on creating high-quality, engaging content that provides value to readers.' . $hard_rules;

			case 'content_analysis':
				return $base_message . ' Analyze the provided content thoroughly and provide detailed insights.' . $hard_rules;

			case 'content_optimization':
				return $base_message . ' Optimize the content for better readability, SEO, and user engagement.' . $hard_rules;

			case 'keyword_research':
				return $base_message . ' Provide comprehensive keyword research and SEO recommendations.' . $hard_rules;

			case 'title_generation':
				return $base_message . ' Create compelling, SEO-friendly titles that attract readers.' . $hard_rules;

			case 'meta_description':
				return $base_message . ' Write concise, compelling meta descriptions that improve click-through rates.' . $hard_rules;

			default:
				return $base_message . ' Provide helpful and accurate assistance.' . $hard_rules;
		}
	}

	/**
	 * Format response based on action type
	 *
	 * @param array $response AI response
	 * @param string $action_type Action type
	 * @param array $debug_messages Debug messages
	 * @return array Formatted response
	 */
	private function format_response( $response, $action_type, $debug_messages ) {
		$content = $response['content'] ?? '';

		$formatted_response = array(
			'content'     => $content,
			'action_type' => $action_type,
			'timestamp'   => current_time( 'mysql' ),
			'debug'       => $debug_messages,
		);

		// Add action-specific formatting
		switch ( $action_type ) {
			case 'title_generation':
				$formatted_response['titles'] = $this->extract_titles( $content );
				break;

			case 'keyword_research':
				$formatted_response['keywords'] = $this->extract_keywords( $content );
				break;

			case 'content_analysis':
				$formatted_response['analysis'] = $this->parse_analysis( $content );
				break;

			case 'meta_description':
				$formatted_response['meta_descriptions'] = $this->extract_meta_descriptions( $content );
				break;
		}

		return $formatted_response;
	}

	/**
	 * Extract titles from content
	 *
	 * @param string $content Generated content
	 * @return array Extracted titles
	 */
	private function extract_titles( $content ) {
		$titles = array();
		$lines  = explode( "\n", $content );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! empty( $line ) ) {
				// Remove numbering and quotes
				$title = preg_replace( '/^\d+\.\s*["\']?/', '', $line );
				$title = preg_replace( '/["\']$/', '', $title );
				$title = trim( $title );

				if ( ! empty( $title ) ) {
					$titles[] = $title;
				}
			}
		}

		return $titles;
	}

	/**
	 * Extract keywords from content
	 *
	 * @param string $content Generated content
	 * @return array Extracted keywords
	 */
	private function extract_keywords( $content ) {
		$keywords = array();

		// Simple keyword extraction - look for comma-separated values
		$lines = explode( "\n", $content );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! empty( $line ) && strpos( $line, ',' ) !== false ) {
				$line_keywords = array_map( 'trim', explode( ',', $line ) );
				$keywords      = array_merge( $keywords, $line_keywords );
			}
		}

		return array_filter( $keywords );
	}

	/**
	 * Parse analysis content
	 *
	 * @param string $content Generated content
	 * @return array Parsed analysis
	 */
	private function parse_analysis( $content ) {
		return array(
			'summary'         => $this->extract_summary( $content ),
			'recommendations' => $this->extract_recommendations( $content ),
			'full_analysis'   => $content,
		);
	}

	/**
	 * Extract summary from analysis
	 *
	 * @param string $content Analysis content
	 * @return string Summary
	 */
	private function extract_summary( $content ) {
		// Extract first paragraph as summary
		$paragraphs = explode( "\n\n", $content );
		return trim( $paragraphs[0] ?? '' );
	}

	/**
	 * Extract recommendations from analysis
	 *
	 * @param string $content Analysis content
	 * @return array Recommendations
	 */
	private function extract_recommendations( $content ) {
		$recommendations = array();

		// Look for bullet points or numbered lists
		$lines = explode( "\n", $content );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( preg_match( '/^[\-\*\d+\.]\s*(.+)/', $line, $matches ) ) {
				$recommendations[] = trim( $matches[1] );
			}
		}

		return $recommendations;
	}

	/**
	 * Extract meta descriptions from content
	 *
	 * @param string $content Generated content
	 * @return array Meta descriptions
	 */
	private function extract_meta_descriptions( $content ) {
		$descriptions = array();
		$lines        = explode( "\n", $content );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! empty( $line ) ) {
				// Remove numbering and quotes
				$description = preg_replace( '/^\d+\.\s*["\']?/', '', $line );
				$description = preg_replace( '/["\']$/', '', $description );
				$description = trim( $description );

				// Validate meta description length (150-160 characters)
				if ( ! empty( $description ) && strlen( $description ) <= 160 ) {
					$descriptions[] = $description;
				}
			}
		}

		return $descriptions;
	}

	/**
	 * Get engine health status
	 *
	 * @return array Health status
	 */
	public function get_health_status() {
		return array(
			'ai_core_status' => $this->ai_core_adapter->get_health_status(),
			'config_status'  => $this->config_manager->get_environment_config(),
			'service_status' => $this->validate_service(),
			'timestamp'      => current_time( 'mysql' ),
		);
	}

	/**
	 * Test engine functionality
	 *
	 * @return array Test results
	 */
	public function test_engine() {
		$test_request = array(
			'prompt'            => 'Generate a test response to verify the engine is working correctly.',
			'action_type'       => 'content_generation',
			'model'             => 'gpt-4o-mini',
			'temperature'       => 0.7,
			'max_tokens'        => 100,
			'top_p'             => 1.0,
			'frequency_penalty' => 0.0,
			'presence_penalty'  => 0.0,
			'language'          => 'English',
			'style'             => 'Professional',
			'tone'              => 'Neutral',
		);

		$result = $this->process_engine_request( $test_request );

		return array(
			'test_successful' => ! is_wp_error( $result ),
			'result'          => $result,
			'timestamp'       => current_time( 'mysql' ),
		);
	}
}
