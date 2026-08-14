<?php
/**
 * WordPress core AI Client Adapter for AI-Scribe (WP 7.0+)
 *
 * Optional provider path: when WordPress 7.0's core AI Client is present
 * and a provider is configured (by any plugin registering into
 * WordPress\AiClient\AiClient::defaultRegistry()), generation can route
 * through core instead of AI-Scribe's direct provider HTTP.
 *
 * Real WP 7.0 API surface used here (discovered empirically against a
 * running WP 7.0 — see REFACTOR.md §10 P4 STATUS):
 * - wp_supports_ai(): bool                                   (wp-includes/ai-client.php)
 * - wp_ai_client_prompt($prompt): WP_AI_Client_Prompt_Builder (wp-includes/ai-client.php)
 * - WP_AI_Client_Prompt_Builder proxies snake_case calls to the bundled
 *   php-ai-client PromptBuilder (with_history, with_text,
 *   using_system_instruction, using_max_tokens, using_temperature,
 *   using_top_p, using_model_preference, as_json_response,
 *   generate_text_result) and returns WP_Error on failure.
 * - WordPress\AiClient\AiClient::defaultRegistry(): ProviderRegistry
 *   (getRegisteredProviderIds(), isProviderConfigured()).
 * - WordPress\AiClient\Messages\DTO\Message::fromArray(
 *       ['role' => 'user'|'model', 'parts' => [['type' => 'text', 'text' => ...]]]
 *   ) — MessageRoleEnum values are 'user' and 'model' (not 'assistant').
 *
 * Every entry point is existence-guarded: on WP < 7.0 (or with the AI
 * Client absent) the adapter reports unavailable and returns WP_Error
 * instead of fataling.
 *
 * @package AI_Scribe
 * @subpackage Adapters
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_WP_AI_Client_Adapter
 *
 * Drop-in alternative to AI_Scribe_AI_Core_Adapter::generate_text() —
 * same message-array input, same ['content','model','usage','raw_response']
 * output shape — routed through the WP 7.0 core AI Client.
 */
class AI_Scribe_WP_AI_Client_Adapter {

	/**
	 * Pseudo model id the settings UI stores when the user chooses the
	 * core AI client ("let WordPress pick the configured model").
	 */
	const MODEL_ID = 'wordpress-ai';

	/**
	 * Pseudo provider id for settings/routing decisions.
	 */
	const PROVIDER_ID = 'wordpress';

	/** @var AI_Scribe_Logger|null */
	private $logger;

	/** @var AI_Scribe_Config_Manager|null */
	private $config;

	/**
	 * Constructor deliberately touches NO core AI functions so the
	 * service container can resolve this adapter safely on WP < 7.0.
	 *
	 * @param AI_Scribe_Logger|null         $logger
	 * @param AI_Scribe_Config_Manager|null $config
	 */
	public function __construct( $logger = null, $config = null ) {
		$this->logger = $logger;
		$this->config = $config;
	}

	// ------------------------------------------------------------------
	// Availability
	// ------------------------------------------------------------------

	/**
	 * Whether the WP 7.0 core AI Client API exists and AI is enabled.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'wp_ai_client_prompt' )
			&& function_exists( 'wp_supports_ai' )
			&& class_exists( 'WordPress\\AiClient\\AiClient' )
			&& call_user_func( 'wp_supports_ai' ); // Indirect: symbol only exists on WP 7.0+, guarded above.
	}

	/**
	 * Whether at least one provider is registered AND configured in the
	 * core AI client's default registry. Core ships no concrete providers;
	 * plugins register them, so a bare WP 7.0 returns false here.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		if ( ! self::is_available() ) {
			return false;
		}

		try {
			$registry = WordPress\AiClient\AiClient::defaultRegistry();
			foreach ( $registry->getRegisteredProviderIds() as $provider_id ) {
				if ( $registry->isProviderConfigured( $provider_id ) ) {
					return true;
				}
			}
		} catch ( Exception $e ) {
			return false;
		}

		return false;
	}

	/**
	 * Provider ids registered with the core AI client, with configured state.
	 *
	 * @return array provider_id => bool configured
	 */
	public static function get_provider_status() {
		$status = array();
		if ( ! self::is_available() ) {
			return $status;
		}

		try {
			$registry = WordPress\AiClient\AiClient::defaultRegistry();
			foreach ( $registry->getRegisteredProviderIds() as $provider_id ) {
				$status[ $provider_id ] = (bool) $registry->isProviderConfigured( $provider_id );
			}
		} catch ( Exception $e ) {
			// Leave empty.
		}

		return $status;
	}

	/**
	 * The "WordPress AI (core)" choice for the settings UI model list.
	 * Returns null when the core AI client is absent so the option never
	 * appears on WP < 7.0.
	 *
	 * @return array|null {id, label, available, configured}
	 */
	public static function provider_choice() {
		if ( ! self::is_available() ) {
			return null;
		}

		return array(
			'id'         => self::MODEL_ID,
			'provider'   => self::PROVIDER_ID,
			'label'      => __( 'WordPress AI (core)', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'available'  => true,
			'configured' => self::is_configured(),
		);
	}

	/**
	 * Whether a model/provider selection should route through this adapter.
	 *
	 * @param string $model    Selected model id.
	 * @param string $provider Optional selected provider id.
	 * @return bool
	 */
	public static function is_selected( $model, $provider = '' ) {
		return $model === self::MODEL_ID || $provider === self::PROVIDER_ID;
	}

	// ------------------------------------------------------------------
	// Generation
	// ------------------------------------------------------------------

	/**
	 * Generate text via the core AI client.
	 *
	 * Accepts the same message array as AI_Scribe_AI_Core_Adapter
	 * ([{role: system|user|assistant, content: string|blocks}]) and
	 * returns the same success shape or WP_Error.
	 *
	 * @param string $model      Model id; self::MODEL_ID (or '') lets the
	 *                           core client pick among configured models,
	 *                           anything else becomes a model preference.
	 * @param array  $messages   Messages array.
	 * @param array  $parameters Generation parameters (max_tokens,
	 *                           temperature, top_p, response_format).
	 * @return array|WP_Error
	 */
	public function generate_text( $model, array $messages, array $parameters = array() ) {
		if ( ! self::is_available() ) {
			return new WP_Error(
				'wp_ai_client_unavailable',
				'The WordPress core AI Client (WordPress 7.0+) is not available on this site.'
			);
		}

		try {
			$builder = call_user_func( 'wp_ai_client_prompt', null ); // Indirect: WP 7.0+ symbol, availability checked via is_available().

			// System messages -> system instruction (core has no system role).
			$system = $this->collect_system_text( $messages );
			if ( $system !== '' ) {
				$builder = $builder->using_system_instruction( $system );
			}

			// Thread history: all but the final user message; final user
			// message is the active prompt text.
			$thread = $this->normalise_thread( $messages );
			if ( empty( $thread ) ) {
				return new WP_Error( 'invalid_params', 'No user prompt supplied to the WordPress AI client.' );
			}

			$final = array_pop( $thread );

			if ( ! empty( $thread ) ) {
				$history = array();
				foreach ( $thread as $entry ) {
					$history[] = WordPress\AiClient\Messages\DTO\Message::fromArray(
						array(
							'role'  => $entry['role'],
							'parts' => array(
								array(
									'type' => 'text',
									'text' => $entry['text'],
								),
							),
						)
					);
				}
				$builder = $builder->with_history( ...$history );
			}

			$builder = $builder->with_text( $final['text'] );
			$builder = $this->apply_parameters( $builder, $model, $parameters );

			$result = $builder->generate_text_result();

			if ( is_wp_error( $result ) ) {
				if ( $this->logger ) {
					$this->logger->error(
						'WP AI Client generation failed',
						array(
							'code'    => $result->get_error_code(),
							'message' => $result->get_error_message(),
						)
					);
				}
				return $result;
			}

			$content = $result->toText();
			$usage   = $this->extract_usage( $result );

			if ( $this->logger ) {
				$this->logger->debug(
					'WP AI Client generation completed',
					array(
						'requested_model' => $model,
						'resolved_model'  => isset( $usage['resolved_model'] ) ? $usage['resolved_model'] : '',
						'content_length'  => strlen( $content ),
					)
				);
			}

			return array(
				'content'      => $content,
				'model'        => isset( $usage['resolved_model'] ) && $usage['resolved_model'] !== ''
					? $usage['resolved_model'] : $model,
				'usage'        => $usage,
				'raw_response' => $result->toArray(),
			);
		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error( 'WP AI Client generation threw', array( 'exception' => $e->getMessage() ) );
			}
			return new WP_Error( 'wp_ai_client_error', $e->getMessage() );
		}
	}

	/**
	 * Image generation is not routed through core in v3 — the ImageService
	 * keeps its direct AI-Core path. Returns WP_Error so callers fall back.
	 *
	 * @param string $prompt
	 * @param array  $options
	 * @return WP_Error
	 */
	public function generate_image( $prompt, array $options = array() ) {
		return new WP_Error(
			'wp_ai_client_unsupported',
			'Image generation is not routed through the WordPress core AI client; use the direct provider path.'
		);
	}

	// ------------------------------------------------------------------
	// Internals
	// ------------------------------------------------------------------

	/**
	 * Concatenate system message text (string content or Anthropic-style
	 * text blocks) into one system instruction.
	 *
	 * @param array $messages
	 * @return string
	 */
	private function collect_system_text( array $messages ) {
		$parts = array();
		foreach ( $messages as $message ) {
			if ( ! isset( $message['role'] ) || $message['role'] !== 'system' ) {
				continue;
			}
			$text = $this->flatten_content( isset( $message['content'] ) ? $message['content'] : '' );
			if ( $text !== '' ) {
				$parts[] = $text;
			}
		}
		return implode( "\n\n", $parts );
	}

	/**
	 * Non-system messages as [{role: user|model, text}] — the core AI
	 * client's role enum is user|model (assistant maps to model), and
	 * cache_control block arrays are flattened to plain text (caching is
	 * the core client's concern on this path).
	 *
	 * @param array $messages
	 * @return array
	 */
	private function normalise_thread( array $messages ) {
		$thread = array();
		foreach ( $messages as $message ) {
			$role = isset( $message['role'] ) ? $message['role'] : 'user';
			if ( $role === 'system' ) {
				continue;
			}
			$text = $this->flatten_content( isset( $message['content'] ) ? $message['content'] : '' );
			if ( $text === '' ) {
				continue;
			}
			$thread[] = array(
				'role' => ( $role === 'assistant' ) ? 'model' : 'user',
				'text' => $text,
			);
		}
		return $thread;
	}

	/**
	 * @param string|array $content String or provider block array.
	 * @return string
	 */
	private function flatten_content( $content ) {
		if ( is_string( $content ) ) {
			return trim( $content );
		}
		if ( is_array( $content ) ) {
			$parts = array();
			foreach ( $content as $block ) {
				if ( is_string( $block ) ) {
					$parts[] = $block;
				} elseif ( is_array( $block ) && isset( $block['text'] ) ) {
					$parts[] = (string) $block['text'];
				}
			}
			return trim( implode( "\n", $parts ) );
		}
		return '';
	}

	/**
	 * Map AI-Scribe request parameters onto the prompt builder.
	 *
	 * @param WP_AI_Client_Prompt_Builder $builder
	 * @param string                      $model
	 * @param array                       $parameters
	 * @return WP_AI_Client_Prompt_Builder
	 */
	private function apply_parameters( $builder, $model, array $parameters ) {
		if ( ! empty( $parameters['max_tokens'] ) ) {
			$builder = $builder->using_max_tokens( (int) $parameters['max_tokens'] );
		}
		if ( isset( $parameters['temperature'] ) && $parameters['temperature'] !== '' ) {
			$builder = $builder->using_temperature( (float) $parameters['temperature'] );
		}
		if ( isset( $parameters['top_p'] ) && $parameters['top_p'] !== '' ) {
			$builder = $builder->using_top_p( (float) $parameters['top_p'] );
		}

		// Structured outputs: translate the OpenAI-shaped response_format
		// the SchemaRegistry emits into the client-neutral JSON response.
		$schema = $this->extract_json_schema( $parameters );
		if ( $schema !== null ) {
			$builder = $builder->as_json_response( $schema );
		}

		// A concrete model id becomes a preference; self::MODEL_ID (or '')
		// lets the core client pick among configured models.
		if ( $model !== '' && $model !== self::MODEL_ID ) {
			try {
				$builder = $builder->using_model_preference( $model );
			} catch ( Exception $e ) {
				// Unknown to the registry — let the client pick.
			}
		}

		return $builder;
	}

	/**
	 * @param array $parameters
	 * @return array|null JSON schema array or null.
	 */
	private function extract_json_schema( array $parameters ) {
		if ( isset( $parameters['response_format']['json_schema']['schema'] )
			&& is_array( $parameters['response_format']['json_schema']['schema'] ) ) {
			return $parameters['response_format']['json_schema']['schema'];
		}
		if ( isset( $parameters['generationConfig']['responseSchema'] )
			&& is_array( $parameters['generationConfig']['responseSchema'] ) ) {
			return $parameters['generationConfig']['responseSchema'];
		}
		return null;
	}

	/**
	 * Usage in AI-Scribe's normalised shape from a GenerativeAiResult.
	 *
	 * @param object $result WordPress\AiClient\Results\DTO\GenerativeAiResult
	 * @return array
	 */
	private function extract_usage( $result ) {
		$usage = array(
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
		);

		try {
			$tokens                     = $result->getTokenUsage();
			$usage['prompt_tokens']     = (int) $tokens->getPromptTokens();
			$usage['completion_tokens'] = (int) $tokens->getCompletionTokens();
			$usage['total_tokens']      = (int) $tokens->getTotalTokens();
		} catch ( Exception $e ) {
			// Keep zeroes.
		}

		try {
			$usage['resolved_model'] = (string) $result->getModelMetadata()->getId();
		} catch ( Exception $e ) {
			$usage['resolved_model'] = '';
		}

		return $usage;
	}
}
