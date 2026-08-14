<?php
/**
 * AI Scribe AJAX Handler Service
 *
 * Handles all AJAX requests for the AI Scribe plugin using OOP architecture.
 * Provides secure, nonce-verified endpoints for frontend interactions.
 *
 * @package    AI_Scribe
 * @subpackage Services
 * @since      3.0.0
 * @author     AI Scribe Team
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * AJAX Handler Service Class
 *
 * Manages all AJAX endpoints for the AI Scribe plugin with proper security,
 * validation, and error handling. Integrates with the plugin's service container.
 */
class AI_Scribe_Ajax_Handler_Service {

	/**
	 * Logger instance for debugging and monitoring
	 *
	 * @var mixed Logger instance or null
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param mixed $logger Optional logger instance for debugging
	 */
	public function __construct( $logger = null ) {
		$this->logger = $logger;
		$this->register_ajax_handlers();
	}

	/**
	 * Register all AJAX handlers
	 *
	 * Sets up WordPress AJAX hooks for both logged-in and non-logged-in users.
	 * All handlers require proper nonce verification for security.
	 *
	 * @return void
	 */
	private function register_ajax_handlers() {
		// P8 §14.2/§14.5: nothing left to register. The legacy
		// ai_scribe_generate_content and ai_scribe_get_prompts endpoints had
		// zero frontend consumers (the wizard drives generation through the
		// Conversation controller surface) and were unregistered to shrink
		// the authenticated attack surface. Handler methods remain below for
		// unit coverage only.
		if ( $this->logger ) {
			$this->logger->info( 'AJAX handler service initialised (legacy endpoints retired)' );
		}
	}

	/**
	 * Handle content settings save
	 *
	 * @return void
	 */
	public function handle_save_content_settings() {
		// Verify nonce
		// Check if security parameter exists and verify nonce
		$security = isset( $_POST['security'] ) ? $_POST['security'] : '';
		if ( empty( $security ) || ! wp_verify_nonce( $security, 'ai_scribe_nonce' ) ) {
			ai_scribe_debug_log( '❌ AJAX Handler: Invalid security token. Security: ' . $security );
			wp_send_json_error( 'Invalid security token' );
			return;
		}

		try {
			$current_settings = get_option( 'ab_gpt_content_settings', array() );

			if ( isset( $_POST['language'] ) ) {
				$current_settings['language'] = sanitize_text_field( wp_unslash( $_POST['language'] ) );
			}

			if ( isset( $_POST['writing_style'] ) ) {
				$current_settings['writing_style'] = sanitize_text_field( wp_unslash( $_POST['writing_style'] ) );
			}

			if ( isset( $_POST['writing_tone'] ) ) {
				$current_settings['writing_tone'] = sanitize_text_field( wp_unslash( $_POST['writing_tone'] ) );
			}

			if ( isset( $_POST['heading_tag'] ) ) {
				$current_settings['Heading_tag'] = sanitize_text_field( wp_unslash( $_POST['heading_tag'] ) );
			}

			if ( isset( $_POST['number_of_heading'] ) ) {
				$current_settings['number_of_heading'] = sanitize_text_field( wp_unslash( $_POST['number_of_heading'] ) );
			}

			if ( isset( $_POST['modify_heading'] ) ) {
				$current_settings['modify_heading'] = sanitize_text_field( wp_unslash( $_POST['modify_heading'] ) );
			}

			// Save settings
			update_option( 'ab_gpt_content_settings', $current_settings );

			if ( $this->logger ) {
				$this->logger->info( 'Content settings saved', $current_settings );
			}

			wp_send_json_success(
				array(
					'message'  => 'Settings saved successfully',
					'settings' => $current_settings,
				)
			);

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error( 'Failed to save content settings', array( 'error' => $e->getMessage() ) );
			}

			wp_send_json_error( 'Failed to save content settings: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle prompt settings save
	 *
	 * @return void
	 */
	public function handle_save_prompt_settings() {
		// Verify nonce
		// Check if security parameter exists and verify nonce
		$security = isset( $_POST['security'] ) ? $_POST['security'] : '';
		if ( empty( $security ) || ! wp_verify_nonce( $security, 'ai_scribe_nonce' ) ) {
			ai_scribe_debug_log( '❌ AJAX Handler: Invalid security token for prompt settings. Security: ' . $security );
			wp_send_json_error( 'Invalid security token' );
			return;
		}

		try {
			$prompt_text = isset( $_POST['prompt_text'] ) ? wp_kses_post( wp_unslash( $_POST['prompt_text'] ) ) : '';
			if ( empty( $prompt_text ) ) {
				wp_send_json_error( 'Prompt text cannot be empty' );
				return;
			}

			// Save prompt
			update_option( 'ai_scribe_current_prompt', $prompt_text );

			if ( $this->logger ) {
				$this->logger->info(
					'Prompt settings saved',
					array(
						'prompt_length' => strlen( $prompt_text ),
					)
				);
			}

			wp_send_json_success(
				array(
					'message'       => 'Prompt saved successfully',
					'prompt_length' => strlen( $prompt_text ),
				)
			);

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error( 'Failed to save prompt settings', array( 'error' => $e->getMessage() ) );
			}

			wp_send_json_error( 'Failed to save prompt settings: ' . $e->getMessage() );
		}
	}

	/**
	 * Get current settings values
	 *
	 * @return array Current settings
	 */
	public function get_current_settings() {
		return array(
			'language'       => get_option( 'ai_scribe_language', 'English' ),
			'writing_style'  => get_option( 'ai_scribe_writing_style', 'Business' ),
			'writing_tone'   => get_option( 'ai_scribe_writing_tone', 'Professional' ),
			'heading_tag'    => get_option( 'ai_scribe_heading_tag', 'h2' ),
			'current_prompt' => get_option( 'ai_scribe_current_prompt', '' ),
		);
	}

	/**
	 * Validate settings data
	 *
	 * @param array $settings Settings to validate
	 * @return array Validation result
	 */
	private function validate_settings( $settings ) {
		$errors = array();

		if ( empty( $settings['language'] ) ) {
			$errors[] = 'Language cannot be empty';
		}

		if ( empty( $settings['writing_style'] ) ) {
			$errors[] = 'Writing style cannot be empty';
		}

		if ( empty( $settings['writing_tone'] ) ) {
			$errors[] = 'Writing tone cannot be empty';
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Handle content generation requests using V4 Content Generation Service
	 * 🚨 ARCHITECTURAL FIX: Use V4 Content Generation Service directly
	 *
	 * @return void
	 */
	public function handle_generate_content() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['security'], 'ai_scribe_nonce' ) ) {
			wp_send_json_error( 'Invalid security token' );
			return;
		}

		try {
			// Extract V4 request data
			$step             = intval( $_POST['step'] ?? 1 );
			$content_type     = sanitize_text_field( wp_unslash( $_POST['content_type'] ?? '' ) );
			$prompt           = wp_kses_post( wp_unslash( $_POST['prompt'] ?? '' ) );
			$options          = $_POST['options'] ?? array();
			$existing_results = $_POST['existing_results'] ?? array();

			if ( $this->logger ) {
				$this->logger->info(
					'V4 AJAX Handler: Processing content generation request',
					array(
						'step'                 => $step,
						'content_type'         => $content_type,
						'prompt_length'        => strlen( $prompt ),
						'has_existing_results' => ! empty( $existing_results ),
						'generate_more'        => isset( $options['generate_more'] ) ? $options['generate_more'] : false,
					)
				);
			}

			// Validate required parameters
			if ( ! $step || ! $content_type || ! $prompt ) {
				wp_send_json_error( 'Missing required parameters: step, content_type, or prompt' );
				return;
			}

			// Get V4 Content Generation Service
			$content_generation_service = $this->get_content_generation_service();
			if ( ! $content_generation_service ) {
				ai_scribe_debug_log( '🚨 AJAX Handler: Content generation service not available - check service registration' );
				wp_send_json_error( 'Content generation service not available' );
				return;
			}

			// Prepare generation data for V4 service
			$generation_data = array(
				'step'         => $step,
				'content_type' => $content_type,
				'prompt'       => $prompt,
				'options'      => $options,
				'context'      => array(
					'title'            => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
					'keywords'         => sanitize_text_field( wp_unslash( $_POST['keywords'] ?? '' ) ),
					'outline'          => wp_kses_post( wp_unslash( $_POST['outline'] ?? '' ) ),
					'introduction'     => wp_kses_post( wp_unslash( $_POST['introduction'] ?? '' ) ),
					'article_body'     => wp_kses_post( wp_unslash( $_POST['article_body'] ?? '' ) ),
					'existing_results' => $existing_results,
					'language'         => sanitize_text_field( $options['language'] ?? 'English' ),
					'style'            => sanitize_text_field( $options['style'] ?? 'professional' ),
					'tone'             => sanitize_text_field( $options['tone'] ?? 'informative' ),
				),
			);

			if ( $this->logger ) {
				$this->logger->info(
					'V4 AJAX Handler: Calling V4 generate_content method',
					array(
						'generation_data_keys' => array_keys( $generation_data ),
					)
				);
			}

			// Use V4 generate_content method
			$result = $content_generation_service->generate_content( $generation_data );

			// Send response in format expected by V4 frontend
			if ( $result['success'] ) {
				wp_send_json_success(
					array(
						'article'        => $result['data'],
						'debug_messages' => $result['debug_messages'] ?? array(),
						'usage'          => $result['usage'] ?? array(),
					)
				);
			} else {
				wp_send_json_error( $result['error'] ?? 'Content generation failed' );
			}
		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error(
					'Content generation failed',
					array(
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					)
				);
			}

			wp_send_json_error( 'Content generation failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle getting prompts data
	 *
	 * @return void
	 */
	public function handle_get_prompts() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['security'], 'ai_scribe_nonce' ) ) {
			wp_send_json_error( 'Invalid security token' );
			return;
		}

		try {
			// 🔧 CRITICAL FIX: Return step-specific prompts like V3 backup
			$step_prompts   = get_option( 'ai_scribe_step_prompts', array() );
			$current_prompt = get_option( 'ai_scribe_current_prompt', '' );

			$prompts_data = array(
				'current_prompt' => $current_prompt,
				'step_prompts'   => $step_prompts,
				'step_1'         => isset( $step_prompts['step_1'] ) ? $step_prompts['step_1'] : $current_prompt,
				'step_2'         => isset( $step_prompts['step_2'] ) ? $step_prompts['step_2'] : '',
				'step_3'         => isset( $step_prompts['step_3'] ) ? $step_prompts['step_3'] : '',
				'step_4'         => isset( $step_prompts['step_4'] ) ? $step_prompts['step_4'] : '',
				'step_5'         => isset( $step_prompts['step_5'] ) ? $step_prompts['step_5'] : '',
				'step_6'         => isset( $step_prompts['step_6'] ) ? $step_prompts['step_6'] : '',
				'step_7'         => isset( $step_prompts['step_7'] ) ? $step_prompts['step_7'] : '',
				'step_8'         => isset( $step_prompts['step_8'] ) ? $step_prompts['step_8'] : '',
				'step_9'         => isset( $step_prompts['step_9'] ) ? $step_prompts['step_9'] : '',
				'step_10'        => isset( $step_prompts['step_10'] ) ? $step_prompts['step_10'] : '',
				'step_11'        => isset( $step_prompts['step_11'] ) ? $step_prompts['step_11'] : '',
			);

			if ( $this->logger ) {
				$this->logger->info( 'Prompts data requested' );
			}

			wp_send_json_success( $prompts_data );

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error( 'Failed to get prompts data', array( 'error' => $e->getMessage() ) );
			}

			wp_send_json_error( 'Failed to get prompts data: ' . $e->getMessage() );
		}
	}

	/**
	 * Sanitize generation context data
	 *
	 * @param array $post_data Raw POST data
	 * @return array Sanitized context
	 */
	private function sanitize_generation_context( $post_data ) {
		$context        = array();
		$allowed_fields = array( 'topic', 'keywords', 'outline', 'previous_content', 'step_data' );

		foreach ( $allowed_fields as $field ) {
			if ( isset( $post_data[ $field ] ) ) {
				$context[ $field ] = wp_kses_post( $post_data[ $field ] );
			}
		}

		return $context;
	}

	/**
	 * Get content generation service from container
	 * 🚨 CRITICAL FIX: Use existing service container instead of manual instantiation
	 *
	 * @return mixed Content service instance or null
	 */
	private function get_content_service() {
		// Try to get from global container first (V4 way)
		if ( function_exists( 'ai_scribe_get_container' ) ) {
			try {
				$container = ai_scribe_get_container();
				return $container->get( 'content_service' );
			} catch ( Exception $e ) {
				ai_scribe_debug_log( 'AI Scribe V4: Failed to get content service from container: ' . $e->getMessage() );
			}
		}

		// 🚨 CRITICAL FIX: Fallback to plugin initializer if container function not available
		global $ai_scribe_plugin_initializer;
		if ( $ai_scribe_plugin_initializer && method_exists( $ai_scribe_plugin_initializer, 'get_container' ) ) {
			try {
				$container = $ai_scribe_plugin_initializer->get_container();
				return $container->get( 'content_service' );
			} catch ( Exception $e ) {
				ai_scribe_debug_log( 'AI Scribe: Failed to get content service from plugin initializer: ' . $e->getMessage() );
			}
		}

		ai_scribe_debug_log( 'AI Scribe: V4 content service not available - no container access method found' );
		return null;
	}

	/**
	 * Get V4 content generation service from container
	 *
	 * @return mixed Content generation service instance or null
	 */
	private function get_content_generation_service() {
		// Try to get from global container first (V4 way)
		if ( function_exists( 'ai_scribe_get_container' ) ) {
			try {
				$container = ai_scribe_get_container();
				// 🚨 CRITICAL FIX: Service is registered as 'content_service' not 'content_generation_service'
				return $container->get( 'content_service' );
			} catch ( Exception $e ) {
				ai_scribe_debug_log( 'AI Scribe V4: Failed to get content service from container: ' . $e->getMessage() );
			}
		}

		// 🚨 CRITICAL FIX: Fallback to plugin initializer if container function not available
		global $ai_scribe_plugin_initializer;
		if ( $ai_scribe_plugin_initializer && method_exists( $ai_scribe_plugin_initializer, 'get_container' ) ) {
			try {
				$container = $ai_scribe_plugin_initializer->get_container();
				// 🚨 CRITICAL FIX: Service is registered as 'content_service' not 'content_generation_service'
				return $container->get( 'content_service' );
			} catch ( Exception $e ) {
				ai_scribe_debug_log( 'AI Scribe: Failed to get content service from plugin initializer: ' . $e->getMessage() );
			}
		}

		ai_scribe_debug_log( 'AI Scribe: V4 content generation service not available - no container access method found' );
		return null;
	}

	/**
	 * Get prompt manager from container
	 *
	 * @return mixed Prompt manager instance or null
	 */
	private function get_prompt_manager() {
		// Try to get from global container
		if ( function_exists( 'ai_scribe_get_container' ) ) {
			$container = ai_scribe_get_container();
			return $container->get( 'prompt_manager' );
		}

		return null;
	}
}
