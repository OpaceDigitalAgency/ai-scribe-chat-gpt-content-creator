<?php
/**
 * Content Generation Service for V4 Frontend
 *
 * Handles AI content generation for the V4 frontend workflow system.
 * Provides clean API for the 11-step article generation process.
 *
 * @package AI_Scribe
 * @subpackage Services
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Content_Generation_Service
 *
 * V4 Frontend content generation service with proper workflow support
 */
class AI_Scribe_Content_Generation_Service extends AI_Scribe_Base_Service {

	/**
	 * Opace AI Hub adapter instance
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
	 * Constructor
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 * @param AI_Scribe_AI_Core_Adapter $ai_core_adapter Opace AI Hub adapter
	 * @param AI_Scribe_Config_Manager $config_manager Config manager
	 * @param AI_Scribe_Prompt_Manager $prompt_manager Prompt manager
	 */
	public function __construct(
		AI_Scribe_Logger $logger,
		AI_Scribe_AI_Core_Adapter $ai_core_adapter,
		AI_Scribe_Config_Manager $config_manager,
		AI_Scribe_Prompt_Manager $prompt_manager
	) {
		parent::__construct( $logger );
		$this->ai_core_adapter = $ai_core_adapter;
		$this->config_manager  = $config_manager;
		$this->prompt_manager  = $prompt_manager;
	}

	/**
	 * Initialize the service
	 *
	 * @return void
	 */
	protected function initialize() {
		// Service initialization if needed
	}

	/**
	 * Validate service configuration
	 *
	 * @return bool|array True if valid, error array if invalid
	 */
	public function validate_service() {
		if ( ! $this->ai_core_adapter ) {
			return array( 'error' => 'Opace AI Hub adapter not available' );
		}

		if ( ! $this->config_manager ) {
			return array( 'error' => 'Config manager not available' );
		}

		if ( ! $this->prompt_manager ) {
			return array( 'error' => 'Prompt manager not available' );
		}

		return true;
	}

	/**
	 * Generate content for V4 frontend workflow
	 *
	 * @param array $generation_data Generation parameters
	 * @return array Generation result
	 */
	public function generate_content( $generation_data ) {
		try {
			$step         = $generation_data['step'] ?? 0;
			$content_type = $generation_data['content_type'] ?? '';
			$prompt       = $generation_data['prompt'] ?? '';
			$options      = $generation_data['options'] ?? array();
			$context      = $generation_data['context'] ?? array();

			if ( $this->logger ) {
				$this->logger->info(
					'V4 content generation started',
					array(
						'step'          => $step,
						'content_type'  => $content_type,
						'prompt_length' => strlen( $prompt ),
					)
				);
			}

			// Validate required parameters
			if ( ! $step || ! $content_type || ! $prompt ) {
				throw new Exception( esc_html( 'Missing required parameters: step, content_type, or prompt' ) );
			}

			// 🚨 CRITICAL FIX: Add prompt variation for "Generate More" requests with existing results
			$is_generate_more = isset( $options['generate_more'] ) && $options['generate_more'] === true;
			$existing_results = isset( $context['existing_results'] ) ? $context['existing_results'] : array();

			if ( $is_generate_more ) {
				$prompt = $this->add_generate_more_variation( $prompt, $content_type, $existing_results );
			}

			// Prepare generation request for Opace AI Hub
			$ai_request = array(
				'prompt'      => $prompt,
				'model'       => $this->get_selected_model(),
				'max_tokens'  => $this->get_max_tokens_for_content_type( $content_type ),
				'temperature' => $this->get_temperature_for_content_type( $content_type ) + ( $is_generate_more ? 0.3 : 0 ), // 🚨 CRITICAL FIX: Much higher temperature for more variation
				'context'     => $context,
				'options'     => $options,
			);

			// 🚨 CRITICAL FIX: Handle case where Opace AI Hub adapter is not available
			if ( ! $this->ai_core_adapter ) {
				if ( $this->logger ) {
					$this->logger->error( 'Opace AI Hub adapter not available for content generation' );
				}
				throw new Exception( esc_html( 'Opace AI Hub adapter not available' ) );
			}

			// Generate content using Opace AI Hub adapter
			// 🚨 CRITICAL FIX: Build system message with humanize mode, custom instructions, and current year
			$system_message = $this->build_system_message( $context );

			// Convert prompt to messages format expected by Opace AI Hub with system message
			$messages = array();

			// Add system message if available
			if ( ! empty( $system_message ) ) {
				$messages[] = array(
					'role'    => 'system',
					'content' => $system_message,
				);
			}

			// Add user prompt
			$messages[] = array(
				'role'    => 'user',
				'content' => $prompt,
			);

			$parameters = array(
				'max_tokens'  => $ai_request['max_tokens'],
				'temperature' => $ai_request['temperature'],
			);

			$model = $ai_request['model'];

			// 🚨 CRITICAL FIX: Handle case where model is null - let Opace AI Hub use its default
			if ( empty( $model ) ) {
				// Get available models and use the first suitable one
				$available_models = $this->ai_core_adapter->get_available_models();
				if ( ! empty( $available_models['openai'] ) ) {
					$model = $available_models['openai'][0]; // Use first available OpenAI model
				} elseif ( ! empty( $available_models['anthropic'] ) ) {
					$model = $available_models['anthropic'][0]; // Use first available Anthropic model
				} else {
					throw new Exception( esc_html( 'No AI models available' ) );
				}

				if ( $this->logger ) {
					$this->logger->info( 'Using default model from Opace AI Hub', array( 'model' => $model ) );
				}
			}

			$ai_response = $this->ai_core_adapter->generate_text( $model, $messages, $parameters );

			if ( is_wp_error( $ai_response ) ) {
				throw new Exception( esc_html( 'AI generation failed: ' . $ai_response->get_error_message() ) );
			}

			if ( ! $ai_response || ! isset( $ai_response['content'] ) ) {
				throw new Exception( esc_html( 'AI generation failed: No content returned' ) );
			}

			// Process response based on content type
			// Opace AI Hub adapter returns: ['content' => 'generated text', 'usage' => [...]]
			$processed_result = $this->process_generation_result( $content_type, $ai_response['content'], $options );

			// Calculate cost
			$cost = $this->calculate_generation_cost( $ai_response );

			if ( $this->logger ) {
				$this->logger->info(
					'V4 content generation completed',
					array(
						'step'         => $step,
						'content_type' => $content_type,
						'cost'         => $cost,
					)
				);
			}

			return array(
				'success'      => true,
				'data'         => $processed_result,
				'cost'         => $cost,
				'step'         => $step,
				'content_type' => $content_type,
				'timestamp'    => time(),
			);

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error(
					'V4 content generation failed',
					array(
						'error' => $e->getMessage(),
						'step'  => $generation_data['step'] ?? 'unknown',
					)
				);
			}

			return array(
				'success'      => false,
				'error'        => $e->getMessage(),
				'step'         => $generation_data['step'] ?? 0,
				'content_type' => $generation_data['content_type'] ?? '',
			);
		}
	}

	/**
	 * Process generation result based on content type
	 *
	 * @param string $content_type Type of content generated
	 * @param mixed $ai_data Raw AI response data
	 * @param array $options Generation options
	 * @return array Processed result
	 */
	private function process_generation_result( $content_type, $ai_data, $options ) {
		// 🚨 CRITICAL FIX: Don't default count - let prompt define quantity
		$count = $options['count'] ?? null;

		switch ( $content_type ) {
			case 'title':
				return $this->process_title_result( $ai_data, $count );

			case 'keywords':
				return $this->process_keywords_result( $ai_data, $count );

			case 'outline':
				return $this->process_outline_result( $ai_data, $count );

			case 'introduction':
				return $this->process_introduction_result( $ai_data, $count );

			case 'tagline':
				return $this->process_tagline_result( $ai_data, $count );

			case 'article_body':
				return $this->process_article_body_result( $ai_data );

			case 'conclusion':
				return $this->process_conclusion_result( $ai_data, $count );

			case 'qa_section':
				return $this->process_qa_result( $ai_data, $options );

			case 'meta_data':
				return $this->process_meta_data_result( $ai_data );

			default:
				return array( 'content' => $ai_data );
		}
	}

	/**
	 * Process title generation result
	 */
	private function process_title_result( $ai_data, $count ) {
		$titles = $this->parse_multiple_options( $ai_data, $count );

		$options = array();
		foreach ( $titles as $index => $title ) {
			$options[] = array(
				'id'       => $index + 1,
				'title'    => trim( $title ),
				'selected' => $index === 0,
			);
		}

		return array(
			'options'       => $options,
			'selectedTitle' => $options[0]['title'] ?? '',
		);
	}

	/**
	 * Process keywords generation result
	 * 🚨 CRITICAL FIX: Parse keywords into individual objects instead of single string
	 */
	private function process_keywords_result( $ai_data, $count ) {
		$keyword_sets = $this->parse_multiple_options( $ai_data, $count );

		$options = array();
		foreach ( $keyword_sets as $index => $keywords_text ) {
			// 🚨 CRITICAL FIX: Parse individual keywords from the text
			$individual_keywords = $this->parse_individual_keywords( $keywords_text );

			$options[] = array(
				'id'       => $index + 1,
				'keywords' => $individual_keywords, // Now an array of keyword objects
				'selected' => $index === 0,
			);
		}

		// 🚨 CRITICAL FIX: Return first set of individual keywords for selectedKeywords
		$selected_keywords = $options[0]['keywords'] ?? array();

		return array(
			'options'          => $options,
			'selectedKeywords' => $selected_keywords,
		);
	}

	/**
	 * Parse individual keywords from text into structured objects
	 * Handles format: "keyword | volume | difficulty"
	 */
	private function parse_individual_keywords( $keywords_text ) {
		$keywords = array();

		// Split by newlines to get individual keywords
		$lines = explode( "\n", trim( $keywords_text ) );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			// Parse format: "keyword | volume | difficulty"
			if ( strpos( $line, ' | ' ) !== false ) {
				$parts = explode( ' | ', $line );
				if ( count( $parts ) >= 3 ) {
					$keywords[] = array(
						'keyword'    => trim( $parts[0] ),
						'volume'     => trim( $parts[1] ),
						'difficulty' => trim( $parts[2] ),
					);
				} else {
					// Fallback for incomplete format
					$keywords[] = array(
						'keyword'    => $line,
						'volume'     => '',
						'difficulty' => 'medium',
					);
				}
			} else {
				// Fallback for simple keyword without stats
				$keywords[] = array(
					'keyword'    => $line,
					'volume'     => '',
					'difficulty' => 'medium',
				);
			}
		}

		return $keywords;
	}

	/**
	 * Add variation to prompts for "Generate More" requests
	 * This ensures different results instead of repeating the same content
	 * 🚨 CRITICAL FIX: Strengthened prompt modification to prevent duplicates
	 */
	private function add_generate_more_variation( $prompt, $content_type, $existing_results = array() ) {
		// 🚨 CRITICAL FIX: Much stronger variation instructions
		$variations = array(
			'keywords'     => array(
				'IMPORTANT: Generate COMPLETELY DIFFERENT SEO keywords that are ENTIRELY DISTINCT from any previously generated. Focus on ALTERNATIVE angles, DIFFERENT search intents, and RELATED but UNIQUE terms. DO NOT repeat or paraphrase existing keywords.',
				'CRITICAL: Create FRESH keyword variations with DIFFERENT search volumes and competition levels. Explore ALTERNATIVE aspects of the topic that have NOT been covered yet.',
				'ESSENTIAL: Provide UNIQUE keyword alternatives that target DIFFERENT user intents and search behaviors. Avoid ANY similarity to previous results.',
				'MANDATORY: Generate SUPPLEMENTARY keywords that explore DIFFERENT aspects and angles of the topic. Focus on ALTERNATIVE approaches and RELATED but DISTINCT terms.',
				'REQUIRED: Create ADDITIONAL keyword options with VARIED difficulty levels and DIFFERENT search volumes. Ensure NO overlap with existing results.',
			),
			'title'        => array(
				'IMPORTANT: Generate COMPLETELY DIFFERENT title variations with ALTERNATIVE approaches and angles. DO NOT repeat or paraphrase existing titles.',
				'CRITICAL: Create FRESH title options that explore DIFFERENT aspects of the topic. Focus on ALTERNATIVE benefits and UNIQUE selling points.',
				'ESSENTIAL: Provide UNIQUE titles that would appeal to DIFFERENT audiences and demographics. Avoid ANY similarity to previous results.',
				'MANDATORY: Generate ALTERNATIVE title variations with DIFFERENT tones, styles, and emotional appeals. Focus on DISTINCT approaches.',
				'REQUIRED: Create SUPPLEMENTARY title options that highlight DIFFERENT benefits and value propositions. Ensure NO overlap with existing titles.',
			),
			'outline'      => array(
				'IMPORTANT: Generate an ALTERNATIVE outline structure with COMPLETELY DIFFERENT section approaches and organization. DO NOT repeat existing structure.',
				'CRITICAL: Create a FRESH outline that explores DIFFERENT aspects and angles of the topic. Focus on ALTERNATIVE content flow and priorities.',
				'ESSENTIAL: Provide a DIFFERENT organizational structure with UNIQUE section priorities and focus areas. Avoid ANY similarity to previous outlines.',
				'MANDATORY: Generate an outline with ALTERNATIVE section approaches and DIFFERENT content hierarchy. Focus on DISTINCT organizational methods.',
				'REQUIRED: Create a SUPPLEMENTARY outline approach with DIFFERENT content flow and section priorities. Ensure NO overlap with existing structure.',
			),
			'introduction' => array(
				'IMPORTANT: Generate COMPLETELY DIFFERENT introduction variations with ALTERNATIVE opening approaches. DO NOT repeat existing introductions.',
				'CRITICAL: Create FRESH introduction options that use DIFFERENT hooks, angles, and engagement strategies. Focus on ALTERNATIVE approaches.',
				'ESSENTIAL: Provide UNIQUE introductions with DIFFERENT tones and opening strategies. Avoid ANY similarity to previous results.',
				'MANDATORY: Generate ALTERNATIVE introduction variations with DIFFERENT emotional appeals and engagement methods.',
				'REQUIRED: Create SUPPLEMENTARY introduction options with DIFFERENT hooks and opening approaches. Ensure NO overlap with existing content.',
			),
			'tagline'      => array(
				'IMPORTANT: Generate COMPLETELY DIFFERENT tagline variations with ALTERNATIVE messaging approaches. DO NOT repeat existing taglines.',
				'CRITICAL: Create FRESH tagline options that use DIFFERENT emotional appeals and value propositions. Focus on ALTERNATIVE angles.',
				'ESSENTIAL: Provide UNIQUE taglines with DIFFERENT tones and messaging strategies. Avoid ANY similarity to previous results.',
				'MANDATORY: Generate ALTERNATIVE tagline variations with DIFFERENT benefits and emotional hooks.',
				'REQUIRED: Create SUPPLEMENTARY tagline options with DIFFERENT messaging approaches. Ensure NO overlap with existing taglines.',
			),
		);

		$content_variations = $variations[ $content_type ] ?? $variations['keywords'];
		$random_variation   = $content_variations[ array_rand( $content_variations ) ];

		// 🚨 CRITICAL FIX: Much stronger existing results exclusion
		$existing_results_text = '';
		if ( ! empty( $existing_results ) && is_array( $existing_results ) ) {
			$existing_results_list = implode( "\n- ", $existing_results );
			$existing_results_text = "\n\n🚨 CRITICAL CONSTRAINT: You MUST NOT generate any content that is similar to, paraphrases, or repeats these existing results:\n\n- " . $existing_results_list . "\n\n🚨 MANDATORY REQUIREMENT: Generate COMPLETELY DIFFERENT content that has NO similarity, overlap, or resemblance to any of the above results. Focus on ALTERNATIVE angles, DIFFERENT approaches, and UNIQUE perspectives that are ENTIRELY DISTINCT from what has already been generated.\n\n";
		}

		// 🚨 CRITICAL FIX: Add temperature boost instruction for more variation
		$temperature_boost = "\n\n🎯 VARIATION INSTRUCTION: Use maximum creativity and variation in your response. Explore completely different angles, alternative approaches, and unique perspectives that haven't been covered yet.\n\n";

		// Add the variation instruction, existing results exclusion, and temperature boost to the beginning of the prompt
		$modified_prompt = $random_variation . $existing_results_text . $temperature_boost . $prompt;

		if ( $this->logger ) {
			$this->logger->info(
				'Added generate more variation for ' . $content_type,
				array(
					'variation'              => $random_variation,
					'existing_results_count' => count( $existing_results ),
				)
			);
		}

		return $modified_prompt;
	}

	/**
	 * Process outline generation result
	 * 🚨 CRITICAL FIX: Handle both numbered options and \n-separated sections
	 */
	private function process_outline_result( $ai_data, $count ) {
		$outlines = $this->parse_outline_options( $ai_data, $count );

		$options = array();
		foreach ( $outlines as $index => $outline ) {
			$options[] = array(
				'id'        => $index + 1,
				'structure' => trim( $outline ),
				'selected'  => $index === 0,
			);
		}

		return array(
			'options'         => $options,
			'selectedOutline' => array(
				'structure' => $options[0]['structure'] ?? '',
			),
		);
	}

	/**
	 * Process introduction generation result
	 */
	private function process_introduction_result( $ai_data, $count ) {
		$introductions = $this->parse_multiple_options( $ai_data, $count );

		$options = array();
		foreach ( $introductions as $index => $intro ) {
			$options[] = array(
				'id'       => $index + 1,
				'content'  => trim( $intro ),
				'selected' => $index === 0,
			);
		}

		return array(
			'options'              => $options,
			'selectedIntroduction' => array(
				'content' => $options[0]['content'] ?? '',
			),
		);
	}

	/**
	 * Process tagline generation result
	 */
	private function process_tagline_result( $ai_data, $count ) {
		$taglines = $this->parse_multiple_options( $ai_data, $count );

		$options = array();
		foreach ( $taglines as $index => $tagline ) {
			$options[] = array(
				'id'       => $index + 1,
				'content'  => trim( $tagline ),
				'selected' => $index === 0,
			);
		}

		return array(
			'options'         => $options,
			'selectedTagline' => array(
				'content' => $options[0]['content'] ?? '',
			),
		);
	}

	/**
	 * Process article body generation result
	 */
	private function process_article_body_result( $ai_data ) {
		return array(
			'articleBody' => trim( $ai_data ),
		);
	}

	/**
	 * Process conclusion generation result
	 * 🚨 CRITICAL FIX: Strip <h2>Conclusion</h2> tags from content
	 */
	private function process_conclusion_result( $ai_data, $count ) {
		$conclusions = $this->parse_multiple_options( $ai_data, $count );

		$options = array();
		foreach ( $conclusions as $index => $conclusion ) {
			// Strip unwanted HTML tags like <h2>Conclusion</h2>
			$cleanContent = $this->strip_conclusion_tags( trim( $conclusion ) );

			$options[] = array(
				'id'       => $index + 1,
				'content'  => $cleanContent,
				'selected' => $index === 0,
			);
		}

		return array(
			'options'            => $options,
			'selectedConclusion' => array(
				'content' => $options[0]['content'] ?? '',
			),
		);
	}

	/**
	 * Process Q&A generation result
	 * 🚨 CRITICAL FIX: Parse Q&A into structured options for multiple selection
	 */
	private function process_qa_result( $ai_data, $options ) {
		$qa_pairs = $this->parse_qa_pairs( $ai_data );

		$qa_options = array();
		foreach ( $qa_pairs as $index => $qa_pair ) {
			$qa_options[] = array(
				'id'       => $index + 1,
				'question' => $qa_pair['question'],
				'answer'   => $qa_pair['answer'],
				'selected' => false, // Allow multiple selections
			);
		}

		return array(
			'options'   => $qa_options,
			'qaSection' => trim( $ai_data ), // Keep original for backward compatibility
		);
	}

	/**
	 * Process meta data generation result
	 */
	private function process_meta_data_result( $ai_data ) {
		// Parse meta title and description from AI response
		$lines            = explode( "\n", trim( $ai_data ) );
		$meta_title       = '';
		$meta_description = '';

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( stripos( $line, 'title:' ) === 0 ) {
				$meta_title = trim( substr( $line, 6 ) );
			} elseif ( stripos( $line, 'description:' ) === 0 ) {
				$meta_description = trim( substr( $line, 12 ) );
			}
		}

		return array(
			'metaTitle'       => $meta_title,
			'metaDescription' => $meta_description,
		);
	}

	/**
	 * Parse multiple options from AI response
	 * 🚨 CRITICAL FIX: Let prompt define quantity - don't force artificial counts
	 */
	private function parse_multiple_options( $ai_data, $count = null ) {
		$options = array();

		// Try to split by numbered list first
		if ( preg_match_all( '/^\d+\.\s*(.+)$/m', $ai_data, $matches ) ) {
			$options = $matches[1];
		} else {
			// Fallback: split by double newlines or other separators
			$parts   = preg_split( '/\n\s*\n|\n---\n|\n\*\*\*\n/', trim( $ai_data ) );
			$options = array_filter(
				$parts,
				function ( $part ) {
					return ! empty( trim( $part ) );
				}
			);
		}

		// 🚨 CRITICAL FIX: Remove duplicates
		$unique_options = array_unique( array_map( 'trim', $options ) );

		// 🚨 CRITICAL FIX: If no count specified, return all unique options (let prompt define quantity)
		if ( $count === null ) {
			return array_values( $unique_options );
		}

		// If count is specified, return up to that count but don't duplicate
		return array_slice( array_values( $unique_options ), 0, $count );
	}

	/**
	 * Get selected AI model
	 * 🚨 FIXED: Use current V4 configuration system properly
	 */
	private function get_selected_model() {
		if ( $this->config_manager ) {
			// Use the correct V4 configuration path
			$current_model = $this->config_manager->get( 'ai_engine.model' );
			if ( ! empty( $current_model ) ) {
				return $current_model;
			}
		}

		// Fallback to direct WordPress option (no hardcoded defaults)
		$fallback_model = get_option( 'ab_model' );
		if ( ! empty( $fallback_model ) ) {
			return $fallback_model;
		}

		// Final fallback - let Opace AI Hub handle default model selection
		return null;
	}

	/**
	 * Get max tokens for content type
	 */
	private function get_max_tokens_for_content_type( $content_type ) {
		$token_limits = array(
			'title'        => 100,
			'keywords'     => 200,
			'outline'      => 500,
			'introduction' => 300,
			'tagline'      => 50,
			'article_body' => 2000,
			'conclusion'   => 300,
			'qa_section'   => 800,
			'meta_data'    => 200,
		);

		return $token_limits[ $content_type ] ?? 500;
	}

	/**
	 * Get temperature for content type
	 */
	private function get_temperature_for_content_type( $content_type ) {
		$temperatures = array(
			'title'        => 0.8,
			'keywords'     => 0.3,
			'outline'      => 0.7,
			'introduction' => 0.7,
			'tagline'      => 0.9,
			'article_body' => 0.7,
			'conclusion'   => 0.7,
			'qa_section'   => 0.6,
			'meta_data'    => 0.5,
		);

		return $temperatures[ $content_type ] ?? 0.7;
	}

	/**
	 * Calculate generation cost
	 */
	private function calculate_generation_cost( $ai_response ) {
		// Basic cost calculation - should be enhanced based on actual usage
		// Opace AI Hub adapter returns usage in different format
		$tokens_used = 1000; // Default fallback

		if ( isset( $ai_response['usage']['total_tokens'] ) ) {
			$tokens_used = $ai_response['usage']['total_tokens'];
		} elseif ( isset( $ai_response['usage'] ) ) {
			// Handle different usage format structures
			$usage       = $ai_response['usage'];
			$tokens_used = ( $usage['prompt_tokens'] ?? 0 ) + ( $usage['completion_tokens'] ?? 0 );
		}

		$cost_per_token = 0.00002; // Approximate cost
		return round( $tokens_used * $cost_per_token, 4 );
	}

	/**
	 * Backward compatibility method for V3 AJAX handler
	 * 🚨 ARCHITECTURAL FIX: Provides compatibility with existing AJAX calls
	 *
	 * @return void
	 */
	public function suggest_content() {
		// This compatibility method is called only after the registered AJAX
		// controller has verified ai_scribe_nonce and the user's capability.
		// phpcs:disable WordPress.Security.NonceVerification
		try {
			// Extract data from $_POST (V3 format)
			$action_input = sanitize_text_field( wp_unslash( $_POST['actionInput'] ?? '' ) );
			$prompt       = wp_kses_post( wp_unslash( $_POST['autogenerateValue'] ?? '' ) );
			$step         = intval( $_POST['step'] ?? 1 );

			if ( $this->logger ) {
				$this->logger->info(
					'V3 compatibility: suggest_content() called',
					array(
						'action_input'  => $action_input,
						'step'          => $step,
						'prompt_length' => strlen( $prompt ),
					)
				);
			}

			// Map V3 action inputs to V4 content types
			$content_type_map = array(
				'title'        => 'titles',
				'keyword'      => 'keywords',
				'outline'      => 'outline',
				'introduction' => 'introduction',
				'article'      => 'article_body',
				'conclusion'   => 'conclusion',
				'meta'         => 'meta_data',
			);

			$content_type = $content_type_map[ $action_input ] ?? $action_input;

			// Prepare V4 generation data
			$generation_data = array(
				'step'         => $step,
				'content_type' => $content_type,
				'prompt'       => $prompt,
				'options'      => array(
					'model'         => sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) ),
					'language'      => sanitize_text_field( wp_unslash( $_POST['language'] ?? '' ) ),
					'writing_style' => sanitize_text_field( wp_unslash( $_POST['writingStyle'] ?? '' ) ),
					'writing_tone'  => sanitize_text_field( wp_unslash( $_POST['writingTone'] ?? '' ) ),
				),
				'context'      => array(
					'title'               => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
					'keywords'            => sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) ),
					'idea'                => sanitize_text_field( wp_unslash( $_POST['idea'] ?? '' ) ),
					// 🚨 CRITICAL FIX: Add system message context
					'language'            => sanitize_text_field( wp_unslash( $_POST['language'] ?? 'English' ) ),
					'style'               => sanitize_text_field( wp_unslash( $_POST['writingStyle'] ?? 'professional' ) ),
					'tone'                => sanitize_text_field( wp_unslash( $_POST['writingTone'] ?? 'informative' ) ),
					'mode'                => $this->get_humanize_mode(),
					'custom_instructions' => $this->get_custom_instructions(),
				),
			);

			// Use V4 generate_content method
			$result = $this->generate_content( $generation_data );

			// Send V3-compatible response
			if ( $result['success'] ) {
				// The frontend expects 'article' field with the generated content
				$response_data = array(
					'article'        => $result['data']['content'] ?? $result['data'],
					'debug_messages' => $result['debug_messages'] ?? array(),
					'usage'          => $result['usage'] ?? array(),
				);

				if ( $this->logger ) {
					$this->logger->info(
						'V3 compatibility: Sending success response',
						array(
							'content_length' => strlen( $response_data['article'] ),
							'has_usage'      => ! empty( $response_data['usage'] ),
						)
					);
				}

				wp_send_json_success( $response_data );
			} else {
				if ( $this->logger ) {
					$this->logger->error(
						'V3 compatibility: Sending error response',
						array(
							'error' => $result['error'] ?? 'Content generation failed',
						)
					);
				}
				wp_send_json_error( $result['error'] ?? 'Content generation failed' );
			}
		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error(
					'V3 compatibility suggest_content failed',
					array(
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					)
				);
			}

			wp_send_json_error( 'Content generation failed: ' . $e->getMessage() );
		}
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Build system message with mode-specific instructions (preserving original logic)
	 * 🚨 CRITICAL FIX: Added system message building for humanize mode, custom instructions, and current year
	 *
	 * @param array $context Context data containing language, style, tone, mode, custom_instructions
	 * @return string Complete system message
	 */
	private function build_system_message( $context ) {
		// Get settings from context or use defaults
		$language            = $context['language'] ?? 'English';
		$style               = $context['style'] ?? 'professional';
		$tone                = $context['tone'] ?? 'informative';
		$mode                = $context['mode'] ?? 'standard';
		$custom_instructions = $context['custom_instructions'] ?? '';

		// 🚨 CRITICAL FIX: Always include current year
		$base_message = 'The year is ' . gmdate( 'Y' ) . ". Write in the {$language} language using a {$style} writing style and a {$tone} writing tone.";

		// Get mode-specific instructions (preserving original humanize/personality logic)
		$mode_instructions = $this->get_mode_instructions( $mode );

		// Combine all instructions. Hard rules sit after the persona and
		// before the user's own Custom Instructions, matching
		// AI_Scribe_Prompt_Manager::get_system_prompt().
		$full_instructions = $base_message;
		if ( ! empty( $mode_instructions ) ) {
			$full_instructions .= "\n\n" . $mode_instructions;
		}
		$full_instructions .= "\n\n" . AI_Scribe_Prompt_Manager::hard_rules();
		if ( ! empty( $custom_instructions ) ) {
			$full_instructions .= "\n\n" . $custom_instructions;
		}

		return $full_instructions;
	}

	/**
	 * Get mode-specific instructions using database-driven configuration
	 *
	 * @param string $mode Content mode
	 * @return string Mode instructions
	 */
	private function get_mode_instructions( $mode ) {
		try {
			switch ( $mode ) {
				case 'humanize':
					return $this->get_mode_instructions_from_database( 'humanize' );
				case 'personality':
					$humanize = $this->get_mode_instructions_from_database( 'humanize' );
					$personal = $this->get_mode_instructions_from_database( 'personal' );
					return $humanize . "\n\n" . $personal;
				default:
					return '';
			}
		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error( 'Failed to get mode instructions: ' . $e->getMessage() );
			}
			// Fallback to empty string to prevent breaking functionality
			return '';
		}
	}

	/**
	 * Get mode-specific instructions from database via Prompt Manager
	 *
	 * @param string $mode_key Mode key (humanize, personal)
	 * @return string Mode instructions from database
	 */
	private function get_mode_instructions_from_database( $mode_key ) {
		if ( ! $this->prompt_manager ) {
			if ( $this->logger ) {
				$this->logger->error( 'Prompt manager not available for mode instructions' );
			}
			return '';
		}

		try {
			// One source of truth for the mode text — the Prompt Manager
			// resolves complete-prompts.json first, then the seeded
			// ab_mode_instructions option, then the PHP constant. Reading the
			// option directly here is what let the two paths drift.
			$instructions = (string) $this->prompt_manager->get_mode_instructions( $mode_key );
			if ( trim( $instructions ) !== '' ) {
				return $instructions;
			}

			if ( $this->logger ) {
				$this->logger->warning( "Mode instructions not found for key: {$mode_key}" );
			}
			return '';

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error( 'Failed to get mode instructions from database: ' . $e->getMessage() );
			}
			return '';
		}
	}

	/**
	 * Get current humanize mode from settings
	 *
	 * @return string Humanize mode (standard, humanize, personality)
	 */
	private function get_humanize_mode() {
		// Get humanize mode from WordPress options
		$humanize_mode = get_option( 'ab_humanize_mode', 'standard' );
		return sanitize_text_field( $humanize_mode );
	}

	/**
	 * Get custom instructions from settings
	 *
	 * @return string Custom instructions
	 */
	private function get_custom_instructions() {
		// Get custom instructions from WordPress options
		$custom_instructions = get_option( 'ab_custom_instructions', '' );
		return wp_kses_post( $custom_instructions );
	}

	/**
	 * Parse outline options - handles both numbered lists and \n-separated sections
	 * 🚨 CRITICAL FIX: Unified outline parsing for consistent API responses
	 */
	private function parse_outline_options( $ai_data, $count ) {
		$options = array();
		$ai_data = trim( $ai_data );

		// 🚨 CRITICAL FIX: Always ensure outline returns ONE option with ALL sections
		// This prevents the inconsistent Format 1 vs Format 2 issue

		// First, try to detect if this is multiple numbered options (Format 1)
		if ( preg_match_all( '/^\d+\.\s*(.+)$/m', $ai_data, $matches ) ) {
			// Format 1: Multiple numbered options - combine them into one outline
			$sections = $matches[1];
			$sections = array_map( 'trim', $sections );
			$sections = array_filter(
				$sections,
				function ( $section ) {
					return ! empty( $section ) && strlen( $section ) > 3;
				}
			);

			// Combine all sections into one outline with newline separators
			$options[] = implode( "\n", $sections );

		} elseif ( strpos( $ai_data, "\n" ) !== false ) {
			// Format 2: Single outline with \n-separated sections
			$sections = explode( "\n", $ai_data );
			$sections = array_map( 'trim', $sections );
			$sections = array_filter(
				$sections,
				function ( $section ) {
					return ! empty( $section ) && strlen( $section ) > 3;
				}
			);

			if ( count( $sections ) > 1 ) {
				// Join sections back with newlines for frontend parsing
				$options[] = implode( "\n", $sections );
			} else {
				$options[] = $ai_data;
			}
		} else {
			// Fallback: Single line response - use as is
			$options[] = $ai_data;
		}

		// Ensure we always have at least one option
		if ( empty( $options ) ) {
			$options[] = $ai_data;
		}

		return $options;
	}

	/**
	 * Strip conclusion HTML tags like <h2>Conclusion</h2>
	 * 🚨 CRITICAL FIX: Clean conclusion content for display
	 */
	private function strip_conclusion_tags( $content ) {
		// Remove <h2>Conclusion</h2> and similar heading tags
		$content = preg_replace( '/<h[1-6][^>]*>.*?conclusion.*?<\/h[1-6]>/i', '', $content );

		// Remove <span> wrapper if it exists
		$content = preg_replace( '/^<span>(.*)<\/span>$/s', '$1', $content );

		// Clean up any extra whitespace
		return trim( $content );
	}

	/**
	 * Parse Q&A pairs from AI response
	 * 🚨 CRITICAL FIX: Structure Q&A for multiple selection support
	 */
	private function parse_qa_pairs( $ai_data ) {
		$qa_pairs = array();

		// Try to match Q&A patterns
		// Pattern 1: Question?Answer format
		if ( preg_match_all( '/([^?]+\?)\s*([^?]+?)(?=\s*[A-Z][^?]*\?|$)/s', $ai_data, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$question = trim( $match[1] );
				$answer   = trim( $match[2] );

				if ( ! empty( $question ) && ! empty( $answer ) ) {
					$qa_pairs[] = array(
						'question' => $question,
						'answer'   => $answer,
					);
				}
			}
		}

		// Fallback: Split by double newlines and try to parse
		if ( empty( $qa_pairs ) ) {
			$parts = preg_split( '/\n\s*\n/', trim( $ai_data ) );
			foreach ( $parts as $part ) {
				$part = trim( $part );
				if ( strpos( $part, '?' ) !== false ) {
					$question_end = strpos( $part, '?' ) + 1;
					$question     = trim( substr( $part, 0, $question_end ) );
					$answer       = trim( substr( $part, $question_end ) );

					if ( ! empty( $question ) && ! empty( $answer ) ) {
						$qa_pairs[] = array(
							'question' => $question,
							'answer'   => $answer,
						);
					}
				}
			}
		}

		return $qa_pairs;
	}
}
