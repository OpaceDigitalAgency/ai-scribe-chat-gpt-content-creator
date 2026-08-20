<?php
/**
 * Opace AI Hub Adapter for AI-Scribe Plugin
 *
 * Thin interface to the Opace AI Hub plugin's public API (ai_core()). Every
 * text and image request is sent through the hub — never the shared library
 * directly — so provider configuration stays in one place and every request
 * is recorded in the hub's usage statistics. Response parsing is delegated
 * to Opace AI Hub's ResponseNormalizer instead of a hand-rolled format cascade.
 *
 * @package AI_Scribe
 * @subpackage Adapters
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_AI_Core_Adapter
 *
 * Provides a standardized interface to Opace AI Hub functionality
 * with error handling, logging, and response normalization.
 */
class AI_Scribe_AI_Core_Adapter {

	/**
	 * Supported text providers (Opace AI Hub config-key prefixes).
	 *
	 * @var string[]
	 */
	private $providers = array( 'openai', 'anthropic', 'gemini', 'grok' );

	/**
	 * Logger instance
	 *
	 * @var AI_Scribe_Logger
	 */
	private $logger;

	/**
	 * Configuration manager instance
	 *
	 * @var AI_Scribe_Config_Manager
	 */
	private $config;

	/**
	 * Whether the Opace AI Hub plugin (the only send path) is active.
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Constructor
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 * @param AI_Scribe_Config_Manager $config Configuration manager
	 */
	public function __construct( AI_Scribe_Logger $logger, AI_Scribe_Config_Manager $config ) {
		$this->logger = $logger;
		$this->config = $config;

		// Every request is sent through the Opace AI Hub plugin's public API,
		// which owns provider configuration (AICore::init from its own key
		// store) and records usage statistics. This adapter never configures
		// the shared library itself: a second AICore::init here would clobber
		// the hub's config, and a direct sendTextRequest would bypass its
		// stats pipeline.
		$this->initialized = function_exists( 'ai_core' );
		if ( ! $this->initialized ) {
			$this->logger->error( 'Opace AI Hub plugin not active; AI-Scribe cannot send requests' );
		}

		// Long-form generations (Express, body step) can exceed the WP HTTP
		// default/HttpClient 120s on slower models — seen live as "cURL
		// error 28" on Anthropic Express. Raise the timeout for provider
		// API hosts only; every other request keeps its own timeout.
		add_filter( 'http_request_args', array( __CLASS__, 'extend_provider_timeout' ), 10, 2 );
	}

	/**
	 * Filter: generous timeout for AI provider API requests only.
	 *
	 * @param array  $args Request args.
	 * @param string $url  Request URL.
	 * @return array
	 */
	public static function extend_provider_timeout( $args, $url ) {
		$host           = wp_parse_url( $url, PHP_URL_HOST );
		$provider_hosts = array(
			'api.openai.com',
			'api.anthropic.com',
			'generativelanguage.googleapis.com',
			'api.x.ai',
		);
		if ( in_array( $host, $provider_hosts, true ) ) {
			$timeout = isset( $args['timeout'] ) ? (int) $args['timeout'] : 0;
			if ( $timeout < 300 ) {
				$args['timeout'] = 300;
			}
		}
		return $args;
	}

	/**
	 * Generate text content using Opace AI Hub
	 *
	 * @param string $model Model name
	 * @param array $messages Messages array
	 * @param array $parameters Generation parameters
	 * @return array|WP_Error Response or error
	 */
	public function generate_text( $model, array $messages, array $parameters = array() ) {
		try {
			if ( ! $this->initialized ) {
				return new WP_Error( 'ai_core_hub_missing', 'The Opace AI Hub plugin is not active. AI-Scribe sends every request through Opace AI Hub — please activate it.' );
			}

			$this->logger->debug(
				"Generating text with model: {$model}",
				array(
					'message_count' => count( $messages ),
					'parameters'    => $parameters,
				)
			);

			$options          = $this->prepare_text_parameters( $model, $messages, $parameters );
			$ai_core_response = $this->send_hub_text_request( $model, $messages, $options );

			if ( is_wp_error( $ai_core_response ) ) {
				$this->logger->error(
					'Opace AI Hub returned an error',
					array(
						'model' => $model,
						'error' => $ai_core_response->get_error_message(),
					)
				);
				return $ai_core_response;
			}

			// Provider-specific format handling lives in Opace AI Hub's ResponseNormalizer;
			// the old 4-branch extraction cascade is gone.
			if ( AICore\AICore::hasError( $ai_core_response ) ) {
				$error_message = AICore\AICore::extractError( $ai_core_response );
				$this->logger->error(
					'Opace AI Hub returned an error response',
					array(
						'model' => $model,
						'error' => $error_message,
					)
				);
				return new WP_Error( 'ai_generation_failed', $error_message );
			}

			$content = AICore\AICore::extractContent( $ai_core_response );
			$usage   = AICore\AICore::extractUsage( $ai_core_response );

			$response = array(
				'content'      => $content,
				'model'        => $model,
				'usage'        => $usage,
				'raw_response' => $ai_core_response, // Keep original for debugging
			);

			$this->logger->debug(
				'Text generation completed',
				array(
					'model'          => $model,
					'content_length' => strlen( $content ),
					'content_empty'  => empty( $content ),
					'usage'          => $usage,
				)
			);

			return $response;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Text generation failed',
				array(
					'model'     => $model,
					'exception' => $e->getMessage(),
				)
			);

			return new WP_Error( 'ai_generation_failed', $e->getMessage() );
		}
	}

	/**
	 * Send a text request through the Opace AI Hub plugin.
	 *
	 * The hub's public API (AI_Core_API::send_text_request) is the ONLY
	 * request path: it is the pipeline that records usage statistics
	 * (requests, tokens, model, provider, cost) on the Opace AI Hub Dashboard.
	 * There is deliberately no direct AICore\AICore::sendTextRequest()
	 * fallback — that would bypass the stats pipeline silently. A 2.6.2
	 * upgrade's keys are pushed into the hub's key store by
	 * AI_Scribe_Migration_Service::maybe_migrate_keys_to_hub(), so an
	 * unconfigured hub means the user genuinely has no key anywhere.
	 *
	 * @param string $model    Model name
	 * @param array  $messages Messages array
	 * @param array  $options  Prepared request options
	 * @return array|WP_Error Raw Opace AI Hub response or error
	 */
	private function send_hub_text_request( $model, array $messages, array $options ) {
		$hub_error = $this->require_configured_hub();
		if ( is_wp_error( $hub_error ) ) {
			return $hub_error;
		}

		return ai_core()->send_text_request(
			$model,
			$messages,
			$options,
			array( 'tool' => 'ai_scribe' )
		);
	}

	/**
	 * Assert the Opace AI Hub plugin is active and has at least one API key.
	 *
	 * @return true|WP_Error True when requests can be sent
	 */
	private function require_configured_hub() {
		if ( ! function_exists( 'ai_core' ) ) {
			return new WP_Error(
				'ai_core_hub_missing',
				'The Opace AI Hub plugin is not active. AI-Scribe sends every request through Opace AI Hub — please activate it.'
			);
		}

		if ( ! ai_core()->is_configured() ) {
			return new WP_Error(
				'ai_core_not_configured',
				'No AI provider is configured. Add an API key under Opace AI Hub → Settings, then try again.'
			);
		}

		return true;
	}

	/**
	 * Generate image using Opace AI Hub
	 *
	 * @param string $prompt Image prompt
	 * @param array $options Image generation options
	 * @return array|WP_Error Response or error
	 */
	public function generate_image( $prompt, array $options = array() ) {
		try {
			if ( ! $this->initialized ) {
				return new WP_Error( 'ai_core_hub_missing', 'The Opace AI Hub plugin is not active. AI-Scribe sends every request through Opace AI Hub — please activate it.' );
			}

			$model    = $options['model'] ?? 'gpt-image-1';
			$provider = $options['provider'] ?? 'openai';

			$this->logger->debug(
				"Generating image with model: {$model}",
				array(
					'prompt_length' => strlen( $prompt ),
					'options'       => $options,
				)
			);

			$image_params = $this->prepare_image_parameters( $prompt, $options );

			// Route via the hub plugin — the only send path — so the request
			// is recorded in Opace AI Hub's usage statistics (see
			// send_hub_text_request for why no direct fallback exists).
			$hub_error = $this->require_configured_hub();
			if ( is_wp_error( $hub_error ) ) {
				return $hub_error;
			}
			$response = ai_core()->generate_image( $prompt, $image_params, $provider, array( 'tool' => 'ai_scribe' ) );

			if ( is_wp_error( $response ) ) {
				$this->logger->error(
					'Opace AI Hub returned an image error',
					array(
						'model' => $model,
						'error' => $response->get_error_message(),
					)
				);
				return $response;
			}

			// Normalise the provider's raw {data: [...]} shape to the flat
			// {url}/{base64_data} contract ImageService consumes (the raw
			// pass-through previously broke the whole image pipeline).
			if ( empty( $response['url'] ) && empty( $response['base64_data'] ) && ! empty( $response['data'][0] ) && is_array( $response['data'][0] ) ) {
				$first = $response['data'][0];
				if ( ! empty( $first['url'] ) ) {
					$response['url'] = (string) $first['url'];
				} elseif ( ! empty( $first['b64_json'] ) ) {
					$response['base64_data'] = (string) $first['b64_json'];
				}
			}

			$this->logger->debug(
				'Image generation completed',
				array(
					'model'     => $model,
					'image_url' => $response['url'] ?? ( isset( $response['base64_data'] ) ? 'base64' : 'none' ),
				)
			);

			return $response;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Image generation failed',
				array(
					'model'     => $model ?? 'unknown',
					'exception' => $e->getMessage(),
				)
			);

			return new WP_Error( 'image_generation_failed', $e->getMessage() );
		}
	}

	/**
	 * Check if model is Anthropic
	 *
	 * @param string $model Model name
	 * @return bool
	 */
	private function is_anthropic_model( $model ) {
		return AICore\Registry\ModelRegistry::isAnthropicModel( $model );
	}

	/**
	 * Check if model is OpenAI
	 *
	 * @param string $model Model name
	 * @return bool
	 */
	private function is_openai_model( $model ) {
		return AICore\Registry\ModelRegistry::isOpenAIModel( $model );
	}

	/**
	 * Prepare text generation parameters
	 *
	 * @param string $model Model name
	 * @param array $messages Messages array
	 * @param array $parameters Additional parameters
	 * @return array Prepared parameters
	 */
	private function prepare_text_parameters( $model, array $messages, array $parameters = array() ) {
		$defaults = array(
			'model'       => $model,
			'max_tokens'  => $this->config->get( 'max_tokens', 4000 ),
			'temperature' => $this->config->get( 'temperature', 0.7 ),
		);

		$merged_params = array_merge( $defaults, $parameters );

		// OpenAI reasoning models (o-series) accept reasoning_effort, not sampling params
		if ( preg_match( '/^o[0-9]/i', $model ) ) {
			unset(
				$merged_params['temperature'],
				$merged_params['top_p'],
				$merged_params['frequency_penalty'],
				$merged_params['presence_penalty']
			);
			if ( ! isset( $merged_params['reasoning_effort'] ) ) {
				$merged_params['reasoning_effort'] = 'medium';
			}
		}

		return $merged_params;
	}

	/**
	 * Prepare image generation parameters
	 *
	 * @param string $prompt Image prompt
	 * @param array $options Image options
	 * @return array Prepared parameters
	 */
	private function prepare_image_parameters( $prompt, array $options = array() ) {
		$defaults = array(
			'prompt' => $prompt,
			'model'  => 'gpt-image-1',
			'size'   => '1024x1024',
			'n'      => 1,
		);

		return array_merge( $defaults, $options );
	}

	/**
	 * Validate API keys for all configured providers
	 *
	 * @return array Validation results keyed by provider
	 */
	public function validate_api_keys() {
		$results = array();

		foreach ( $this->providers as $provider ) {
			$key                  = $this->config->get_api_key( $provider );
			$key                  = is_string( $key ) ? $key : '';
			$results[ $provider ] = array(
				'configured'   => $key !== '',
				'valid_format' => $this->config->validate_api_key( $key, $provider ),
				'key_length'   => strlen( $key ),
			);
		}

		return $results;
	}

	/**
	 * Get available models
	 *
	 * @return array Available models by provider
	 */
	public function get_available_models() {
		$models = array();
		foreach ( $this->providers as $provider ) {
			$models[ $provider ] = AICore\Registry\ModelRegistry::getModelsByProvider( $provider );
		}
		$models['all'] = AICore\Registry\ModelRegistry::getAllModels();

		return $models;
	}

	/**
	 * Estimate token count for text
	 *
	 * @param string $text Text to estimate
	 * @param string $model Model name for estimation
	 * @return int Estimated token count
	 */
	public function estimate_tokens( $text, $model = 'gpt-4o' ) {
		if ( empty( $text ) ) {
			return 0;
		}

		$text = trim( $text );

		if ( $this->is_anthropic_model( $model ) ) {
			// Anthropic models: roughly 3.5 characters per token
			return (int) ceil( strlen( $text ) / 3.5 );
		}

		// Other providers: roughly 4 characters per token
		return (int) ceil( strlen( $text ) / 4 );
	}

	/**
	 * Get Opace AI Hub health status
	 *
	 * @return array Health status
	 */
	public function get_health_status() {
		$status = array(
			'ai_core_loaded'      => class_exists( 'AICore\AICore' ),
			'initialized'         => $this->initialized,
			'providers_available' => array(
				'openai'       => class_exists( 'AICore\Providers\OpenAIProvider' ),
				'anthropic'    => class_exists( 'AICore\Providers\AnthropicProvider' ),
				'gemini'       => class_exists( 'AICore\Providers\GeminiProvider' ),
				'grok'         => class_exists( 'AICore\Providers\GrokProvider' ),
				'openai_image' => class_exists( 'AICore\Providers\OpenAIImageProvider' ),
				'gemini_image' => class_exists( 'AICore\Providers\GeminiImageProvider' ),
			),
			'api_keys_configured' => array(),
			'provider_status'     => array(),
		);

		foreach ( $this->validate_api_keys() as $provider => $validation ) {
			$status['api_keys_configured'][ $provider ] = $validation['configured'];
		}

		if ( $this->initialized ) {
			try {
				$status['provider_status'] = AICore\AICore::getProviderStatus();
			} catch ( Exception $e ) {
				$status['provider_status'] = array( 'error' => $e->getMessage() );
			}
		}

		return $status;
	}

	/**
	 * Test Opace AI Hub functionality
	 *
	 * Uses a cheap probe model per configured provider purely as a
	 * connectivity check (never used for content generation).
	 *
	 * @return array Test results
	 */
	public function test_functionality() {
		$tests = array();

		$test_messages = array(
			array(
				'role'    => 'user',
				'content' => 'Say "Hello, Opace AI Hub test successful!"',
			),
		);

		$probe_models = array(
			'openai'    => 'gpt-4o-mini',
			'anthropic' => 'claude-haiku-4-5',
			'gemini'    => 'gemini-2.5-flash',
			'grok'      => 'grok-4-fast',
		);

		foreach ( $probe_models as $provider => $probe_model ) {
			$key = $this->config->get_api_key( $provider );
			if ( empty( $key ) ) {
				continue;
			}

			$result                      = $this->generate_text( $probe_model, $test_messages, array( 'max_tokens' => 50 ) );
			$tests[ "{$provider}_text" ] = array(
				'success'  => ! is_wp_error( $result ),
				'response' => is_wp_error( $result ) ? $result->get_error_message() : 'OK',
			);
		}

		return $tests;
	}

	/**
	 * Get Opace AI Hub version information
	 *
	 * @return array Version information
	 */
	public function get_version_info() {
		$version_info = array(
			'ai_core_version' => AICore\AICore::getVersion(),
			'adapter_version' => '3.0.0',
		);

		// The library version comes from the hub itself; this plugin no longer
		// ships a copy to read a version file out of.
		return $version_info;
	}
}
