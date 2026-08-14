<?php
/**
 * Settings AJAX Controller for AI-Scribe Plugin
 *
 * The v1.1 contract extensions (docs/API_CONTRACT.md §9–§12):
 * live model list, API key save (write-only), masked settings snapshot,
 * UI preference persistence. Every endpoint: nonce + capability check,
 * responses via wp_send_json_* only.
 *
 * SECURITY: API keys are write-only through this surface. ai_scribe_get_settings
 * returns masked previews (never the stored value); nothing here or in the
 * enqueue layer localises a key into the page.
 *
 * @package AI_Scribe
 * @subpackage Ajax
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Scribe_Settings_Ajax_Controller {

	const NONCE_ACTION = 'ai_scribe_nonce';

	/** Providers whose keys this surface manages. */
	const PROVIDERS = array( 'openai', 'anthropic', 'gemini', 'grok' );

	/** @var AI_Scribe_Logger|null */
	private $logger;

	/** @var AI_Scribe_Config_Manager */
	private $config;

	/** @var AI_Scribe_AI_Core_Adapter */
	private $ai_core_adapter;

	public function __construct( $logger, $config, $ai_core_adapter ) {
		$this->logger          = $logger;
		$this->config          = $config;
		$this->ai_core_adapter = $ai_core_adapter;
		$this->register();
	}

	private function register() {
		add_action( 'wp_ajax_ai_scribe_get_available_models', array( $this, 'handle_get_available_models' ) );
		add_action( 'wp_ajax_ai_scribe_save_api_keys', array( $this, 'handle_save_api_keys' ) );
		add_action( 'wp_ajax_ai_scribe_get_settings', array( $this, 'handle_get_settings' ) );
		add_action( 'wp_ajax_ai_scribe_save_ui_prefs', array( $this, 'handle_save_ui_prefs' ) );
	}

	/**
	 * Shared guard: nonce + capability. Sends the error itself and returns
	 * false when the request must stop.
	 *
	 * @param string $capability Required capability.
	 * @return bool
	 */
	private function guard( $capability = 'edit_posts' ) {
		$nonce = isset( $_REQUEST['security'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['security'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error(
				array(
					'code'      => 'invalid_nonce',
					'message'   => 'Security nonce is missing or invalid. Please refresh the page.',
					'retryable' => false,
				)
			);
			return false;
		}
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error(
				array(
					'code'      => 'insufficient_permissions',
					'message'   => 'You do not have permission to do this.',
					'retryable' => false,
				)
			);
			return false;
		}
		return true;
	}

	/** Live model-list transient lifetime in seconds (UAT §12.2: ~1h + manual refresh). */
	const MODELS_CACHE_TTL = 3600;

	/**
	 * Contract v1.1 §9 — ai_scribe_get_available_models.
	 *
	 * UAT §12.2 dynamic model UX: for every CONFIGURED provider the list is
	 * fetched LIVE from the provider's /models endpoint (AI-Core provider
	 * getAvailableModels(), which also registers new ids in ModelRegistry),
	 * transient-cached for an hour with a manual `refresh=1` bypass.
	 * Unconfigured providers fall back to the registry seed so a fresh
	 * install still has a picker. Each model carries its ModelRegistry
	 * parameter schema so the UI can render per-model settings panels.
	 * Plus the "WordPress AI (core)" provider choice when WP 7.0's core AI
	 * client is present (WpAiClientAdapter::provider_choice()).
	 */
	public function handle_get_available_models() {
		if ( ! $this->guard( 'edit_posts' ) ) {
			return;
		}

		$provider_filter = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$refresh         = ! empty( $_POST['refresh'] );
		$models          = array();
		$sources         = array();

		/*
		 * Providers you have configured come first. Iterating the fixed
		 * provider order put OpenAI's built-in seed list — models the site
		 * cannot even call — above the live Gemini models the user actually
		 * has, so the top of the picker was the least relevant thing in it.
		 */
		$configured   = array();
		$unconfigured = array();

		foreach ( self::PROVIDERS as $provider ) {
			if ( $provider_filter && $provider_filter !== $provider ) {
				continue;
			}
			$listed = $this->provider_models( $provider, $refresh, $sources );
			$described = array();
			foreach ( $listed as $model_id ) {
				$described[] = $this->describe_model( (string) $model_id, $provider );
			}
			$described = $this->sort_models( $described );

			$key = $this->config->get_api_key( $provider );
			if ( is_string( $key ) && '' !== $key ) {
				$configured = array_merge( $configured, $described );
			} else {
				$unconfigured = array_merge( $unconfigured, $described );
			}
		}

		$models = array_merge( $configured, $unconfigured );

		// WP 7.0 core AI client as an additional provider choice.
		if ( class_exists( 'AI_Scribe_WP_AI_Client_Adapter' ) && ( ! $provider_filter || 'WordPress' === $provider_filter ) ) {
			$choice = AI_Scribe_WP_AI_Client_Adapter::provider_choice();
			if ( is_array( $choice ) ) {
				$models[] = array(
					'id'                => $choice['id'],
					'provider'          => $choice['provider'],
					'label'             => $choice['label'],
					'context_window'    => null,
					'max_output_tokens' => null,
					'pricing'           => null,
					'capabilities'      => array( 'text' ),
					'configured'        => ! empty( $choice['configured'] ),
				);
			}
		}

		wp_send_json_success(
			array(
				'models'    => $models,
				'sources'   => $sources,
				// §13 addendum: the picker gates selectability on per-provider
				// VALIDATED status (grouped options, unconfigured greyed out).
				'providers' => $this->provider_status(),
			)
		);
	}

	/**
	 * Model ids for one provider: LIVE /models fetch for configured
	 * providers (transient-cached ~1h, refresh bypass), registry seed for
	 * unconfigured ones. The live fetch also registers previously-unknown
	 * ids into ModelRegistry so describe_model() has metadata.
	 *
	 * @param string $provider  openai|anthropic|gemini|grok
	 * @param bool   $refresh   Bypass the transient cache.
	 * @param array  $sources   By-ref map provider => live|live-cached|registry.
	 * @return array Model id list.
	 */
	private function provider_models( $provider, $refresh, array &$sources ) {
		$registry_seed = static function () use ( $provider ) {
			return class_exists( 'AICore\\Registry\\ModelRegistry' )
				? AICore\Registry\ModelRegistry::getModelsByProvider( $provider )
				: array();
		};

		$key = $this->config->get_api_key( $provider );
		$key = is_string( $key ) ? $key : '';
		if ( '' === $key ) {
			$sources[ $provider ] = 'registry';
			return $registry_seed();
		}

		$transient = AI_Scribe_Model_Resolver::cache_key( $provider, $key );
		if ( ! $refresh ) {
			$cached = get_transient( $transient );
			if ( is_array( $cached ) && ! empty( $cached ) ) {
				// Re-register cached ids so parameter schemas resolve.
				foreach ( $cached as $id ) {
					if ( class_exists( 'AICore\\Registry\\ModelRegistry' ) && ! AICore\Registry\ModelRegistry::modelExists( $id ) ) {
						AICore\Registry\ModelRegistry::registerModel( $id, array( 'provider' => $provider ) );
					}
				}
				$sources[ $provider ] = 'live-cached';
				return $cached;
			}
		}

		try {
			$instance = $this->make_provider( $provider, $key );
			$live     = $instance ? $instance->getAvailableModels() : array();
			if ( ! empty( $live ) ) {
				set_transient( $transient, $live, self::MODELS_CACHE_TTL );
				$sources[ $provider ] = 'live';
				return $live;
			}
		} catch ( Exception $e ) {
			if ( $this->logger ) {
				// Never log the key — provider + message only.
				$this->logger->warning(
					'Live model fetch failed',
					array(
						'provider' => $provider,
						'error'    => $e->getMessage(),
					)
				);
			}
		}

		/*
		 * A configured provider whose fetch failed is NOT the same as one with
		 * no key, but both reported 'registry' and the screen said "no
		 * providers configured yet" — so a site silently showing the built-in
		 * list looked identical to a correctly-working one, and a truncated
		 * list had no explanation.
		 */
		$sources[ $provider ] = 'registry-fallback';
		return $registry_seed();
	}

	/**
	 * Instantiate an AI-Core text provider for the live model listing.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      API key (never logged).
	 * @return object|null ProviderInterface instance.
	 */
	private function make_provider( $provider, $key ) {
		$classes = array(
			'openai'    => 'AICore\\Providers\\OpenAIProvider',
			'anthropic' => 'AICore\\Providers\\AnthropicProvider',
			'gemini'    => 'AICore\\Providers\\GeminiProvider',
			'grok'      => 'AICore\\Providers\\GrokProvider',
		);
		if ( ! isset( $classes[ $provider ] ) || ! class_exists( $classes[ $provider ] ) ) {
			return null;
		}
		$class = $classes[ $provider ];
		return new $class( $key );
	}

	/**
	 * Build the contract model descriptor from ModelRegistry metadata.
	 */
	/**
	 * Order a provider's models newest and most capable first.
	 *
	 * Nothing sorted this list before, so the picker showed whatever order the
	 * provider's endpoint or the registry happened to return. The registry's
	 * own priority is the primary signal; where it has none — which is the
	 * normal case for a model discovered live that the registry has never seen
	 * — fall back to the version number embedded in the id, so a genuinely new
	 * model still rises above an older one rather than sinking to the bottom.
	 *
	 * @param array $models Descriptors from describe_model().
	 * @return array
	 */
	private function sort_models( array $models ) {
		$ids = array();
		foreach ( $models as $descriptor ) {
			$ids[] = isset( $descriptor['id'] ) ? (string) $descriptor['id'] : '';
		}

		/*
		 * Ordering lives in the resolver so the picker and the default model
		 * can never disagree about which model is newest. Release-date suffixes
		 * are not versions, parameter counts are not versions, and a provider's
		 * side families do not outrank its main line.
		 */
		AI_Scribe_Model_Resolver::set_main_family( $ids );

		usort(
			$models,
			static function ( $a, $b ) {
				return AI_Scribe_Model_Resolver::compare_ids(
					isset( $a['id'] ) ? (string) $a['id'] : '',
					isset( $b['id'] ) ? (string) $b['id'] : ''
				);
			}
		);

		return $models;
	}

	private function describe_model( $model_id, $provider ) {
		// §13.3: live/self-registered models get family-inferred parameter
		// schemas (reasoning effort, extended thinking, honest output caps).
		if ( class_exists( 'AI_Scribe_Model_Schema_Inference' ) ) {
			AI_Scribe_Model_Schema_Inference::apply( $model_id );
		}

		$descriptor = array(
			'id'                => $model_id,
			'provider'          => $provider,
			'label'             => $model_id,
			'category'          => 'text',
			'context_window'    => null,
			'max_output_tokens' => null,
			'pricing'           => null,
			'capabilities'      => array( 'text' ),
			'parameters'        => new stdClass(),
		);

		// §13 addendum: image models must never appear in the TEXT picker.
		// Live-registered ids default to category "text" in the registry, so
		// detect the image families by id as a backstop.
		if ( preg_match( AI_Scribe_Model_Resolver::IS_IMAGE, $model_id ) ) {
			$descriptor['category']     = 'image';
			$descriptor['capabilities'] = array( 'image' );
		}

		if ( class_exists( 'AICore\\Registry\\ModelRegistry' ) ) {
			$config = AICore\Registry\ModelRegistry::getModelConfig( $model_id );
			if ( is_array( $config ) ) {
				if ( ! empty( $config['category'] ) && 'text' !== $config['category'] ) {
					$descriptor['category'] = (string) $config['category'];
				}
				if ( ! empty( $config['display_name'] ) ) {
					$descriptor['label'] = (string) $config['display_name'];
				}
				if ( ! empty( $config['context_window'] ) ) {
					$descriptor['context_window'] = (int) $config['context_window'];
				}
				if ( ! empty( $config['max_output_tokens'] ) ) {
					$descriptor['max_output_tokens'] = (int) $config['max_output_tokens'];
				}
				if ( isset( $config['pricing'] ) && is_array( $config['pricing'] ) ) {
					$descriptor['pricing'] = array(
						'input_per_1m'  => isset( $config['pricing']['input'] ) ? (float) $config['pricing']['input'] : null,
						'output_per_1m' => isset( $config['pricing']['output'] ) ? (float) $config['pricing']['output'] : null,
					);
				}
				if ( ! empty( $config['capabilities'] ) && is_array( $config['capabilities'] ) ) {
					$descriptor['capabilities'] = array_values( array_map( 'strval', $config['capabilities'] ) );
				}
				// UAT §12.2: per-model settings panel is generated from this
				// parameter schema (reasoning effort, thinking level,
				// temperature/top_p only where the model supports them).
				$parameters = AICore\Registry\ModelRegistry::getParameterSchema( $model_id );
				if ( ! empty( $parameters ) && is_array( $parameters ) ) {
					$descriptor['parameters'] = $parameters;
				}
			}
		}

		return $descriptor;
	}

	/**
	 * Contract v1.1 §10 — ai_scribe_save_api_keys.
	 *
	 * Payload: keys JSON {openai, anthropic, gemini, grok}. Empty string or
	 * absent = leave unchanged; a single dash "-" = clear the stored key.
	 * Keys are stored server-side only and never echoed back.
	 */
	public function handle_save_api_keys() {
		if ( ! $this->guard( 'manage_options' ) ) {
			return;
		}

		// §13.12: with the AI-Core hub active, provider keys are managed
		// centrally — this surface refuses key writes and points at the hub.
		if ( function_exists( 'ai_core' ) || class_exists( 'AI_Core' ) ) {
			wp_send_json_error(
				array(
					'code'      => 'managed_by_hub',
					'message'   => 'API keys are managed by the AI-Core plugin. Configure them under AI-Core → Settings.',
					'retryable' => false,
				)
			);
			return;
		}

		$raw  = isset( $_POST['keys'] ) ? wp_unslash( (string) $_POST['keys'] ) : '';
		$keys = json_decode( $raw, true );
		if ( ! is_array( $keys ) ) {
			wp_send_json_error(
				array(
					'code'      => 'invalid_params',
					'message'   => 'A keys object is required.',
					'retryable' => false,
				)
			);
			return;
		}

		$updated = array();
		$cleared = array();
		foreach ( self::PROVIDERS as $provider ) {
			if ( ! array_key_exists( $provider, $keys ) ) {
				continue;
			}
			$value = is_string( $keys[ $provider ] ) ? trim( $keys[ $provider ] ) : '';
			if ( '' === $value ) {
				continue; // Leave unchanged.
			}
			if ( '-' === $value ) {
				$this->store_key( $provider, '' );
				$cleared[] = $provider;
				continue;
			}
			$this->store_key( $provider, sanitize_text_field( $value ) );
			$updated[] = $provider;
		}

		wp_send_json_success(
			array(
				'updated'   => $updated,
				'cleared'   => $cleared,
				'providers' => $this->provider_status(),
			)
		);
	}

	/**
	 * Persist one provider key server-side.
	 *
	 * openai/anthropic keep their legacy individual options (plus the grouped
	 * mirror, matching 2.6.2 behaviour); gemini/grok live in the
	 * ab_gpt_ai_engine_settings group (ConfigManager::get_api_key reads both).
	 */
	private function store_key( $provider, $value ) {
		$engine = get_option( 'ab_gpt_ai_engine_settings', array() );
		if ( ! is_array( $engine ) ) {
			$engine = array();
		}

		// §14.3: keys are encrypted at rest (AES-256-CBC, wp-salts-derived
		// key, versioned format). Empty string (= clear) stays empty.
		$stored = ( '' === $value ) ? '' : $this->config->encrypt_for_storage( $value );

		switch ( $provider ) {
			case 'openai':
				update_option( 'ab_api_key', $stored );
				$engine['api_key'] = $stored;
				break;
			case 'anthropic':
				update_option( 'ab_anthropic_api_key', $stored );
				$engine['anthropic_api_key'] = $stored;
				break;
			case 'gemini':
				$engine['gemini_api_key'] = $stored;
				break;
			case 'grok':
				$engine['grok_api_key'] = $stored;
				break;
			default:
				return;
		}

		update_option( 'ab_gpt_ai_engine_settings', $engine );
		wp_cache_delete( 'ab_gpt_ai_engine_settings', 'options' );
	}

	/**
	 * Masked configured-state per provider — no key material.
	 *
	 * §13.5: `validated` is the result of a cheap LIVE test call (the
	 * provider's /models listing) cached ~1h per key — key PRESENCE alone
	 * (e.g. a stale dummy key) never reports a provider as working.
	 * Values: true (live call succeeded), false (live call failed),
	 * null (not configured / not checkable right now).
	 */
	private function provider_status() {
		$status = array();
		foreach ( self::PROVIDERS as $provider ) {
			$key                 = $this->config->get_api_key( $provider );
			$key                 = is_string( $key ) ? $key : '';
			$status[ $provider ] = array(
				'configured' => '' !== $key,
				'masked'     => $this->mask_key( $key ),
				'validated'  => '' === $key ? null : $this->validate_provider_key( $provider, $key ),
			);
		}
		return $status;
	}

	/**
	 * Cheap live key validation via the provider's /models endpoint,
	 * transient-cached per key hash so the settings screen stays fast.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      API key (never logged, never echoed).
	 * @return bool|null true = live call succeeded; false = provider
	 *                   rejected the call; null = could not check.
	 */
	/**
	 * Falsifiable proof that a key works: one tiny generation.
	 *
	 * A rejected key throws here, where the model-list call would have
	 * returned the bundled registry and looked like success.
	 *
	 * @param object $instance Provider instance built with the key under test.
	 * @return bool
	 */
	private function provider_call_succeeds( $instance ) {
		if ( ! method_exists( $instance, 'sendRequest' ) ) {
			// Nothing cheap to probe with — do not claim validity we cannot show.
			return false;
		}
		try {
			// 16 is the lowest value the OpenAI Responses API accepts; 1 is
			// rejected outright and would fail a perfectly good key.
			$response = $instance->sendRequest(
				array( array( 'role' => 'user', 'content' => 'ping' ) ),
				array( 'max_tokens' => 16 )
			);
			return is_array( $response ) && ! empty( $response );
		} catch ( Exception $e ) {
			return ! $this->is_auth_failure( $e->getMessage() );
		} catch ( Throwable $e ) {
			return ! $this->is_auth_failure( $e->getMessage() );
		}
	}

	/**
	 * Does this provider error mean the key was rejected?
	 *
	 * Only an authentication failure disproves a key. A complaint about a
	 * parameter, a model or a rate limit proves the opposite — the request
	 * reached the provider and was understood, which it could not have been
	 * without a valid key. Treating every error as "invalid key" would tell
	 * users their working key is broken.
	 *
	 * @param string $message Provider error message.
	 * @return bool
	 */
	private function is_auth_failure( $message ) {
		$message = strtolower( (string) $message );
		foreach ( array( '401', '403', 'invalid_api_key', 'invalid api key', 'incorrect api key', 'unauthorized', 'unauthenticated', 'authentication', 'permission_denied', 'api key not valid' ) as $needle ) {
			if ( false !== strpos( $message, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	private function validate_provider_key( $provider, $key ) {
		$transient = 'ai_scribe_valid_' . $provider . '_' . substr( md5( $key ), 0, 8 );
		$cached    = get_transient( $transient );
		if ( 'yes' === $cached ) {
			return true;
		}
		if ( 'no' === $cached ) {
			return false;
		}

		/*
		 * A non-empty model list is NOT proof of a working key. The provider's
		 * getAvailableModels() falls back to the bundled registry when the call
		 * fails, so a rejected key still returns a full list — a stale value in
		 * a legacy option made a site report "OpenAI Validated" with no OpenAI
		 * key configured at all, and unlocked every model behind it.
		 *
		 * Prefer the status-aware result where the loaded library provides it,
		 * and require the list to have come from the provider. Otherwise fall
		 * back to a request whose failure is observable, and treat anything
		 * that is not a demonstrable success as not validated.
		 */
		try {
			$instance = $this->make_provider( $provider, $key );
			if ( ! $instance ) {
				return null;
			}

			if ( method_exists( $instance, 'getAvailableModelsResult' ) ) {
				$result = $instance->getAvailableModelsResult();
				$valid  = is_array( $result )
					&& ! empty( $result['is_live'] )
					&& ! empty( $result['models'] );
			} elseif ( method_exists( $instance, 'getAvailableModels' ) ) {
				// No status available: make the call and let a rejection throw.
				// The registry fallback means a returned list proves nothing, so
				// only an exception-free call against a configured instance
				// counts, and an auth failure must surface as an exception.
				$models = $instance->getAvailableModels();
				$valid  = is_array( $models ) && ! empty( $models ) && $this->provider_call_succeeds( $instance );
			} else {
				return null;
			}
		} catch ( Exception $e ) {
			$valid = false;
		}

		set_transient( $transient, $valid ? 'yes' : 'no', self::MODELS_CACHE_TTL );
		return $valid;
	}

	/**
	 * Mask a key for display: first 3 + last 2 characters, never more.
	 */
	private function mask_key( $key ) {
		$length = strlen( $key );
		if ( 0 === $length ) {
			return '';
		}
		if ( $length <= 8 ) {
			return str_repeat( '•', 8 );
		}
		return substr( $key, 0, 3 ) . str_repeat( '•', 6 ) . substr( $key, -2 );
	}

	/**
	 * Contract v1.1 §11 — ai_scribe_get_settings.
	 *
	 * Full settings snapshot for the settings screen with MASKED keys.
	 */
	public function handle_get_settings() {
		if ( ! $this->guard( 'manage_options' ) ) {
			return;
		}

		$engine = get_option( 'ab_gpt_ai_engine_settings', array() );
		$engine = is_array( $engine ) ? $engine : array();
		// Never ship key material — masked status only.
		unset( $engine['api_key'], $engine['anthropic_api_key'], $engine['gemini_api_key'], $engine['grok_api_key'] );

		// U-01: text carried over from 2.6.2 (keywords-to-avoid, prompt
		// bodies) can hold compounded backslash escaping; normalise on read
		// so the settings screen shows what the user actually wrote. The
		// stored options are left untouched.
		$content = get_option( 'ab_gpt_content_settings', array() );
		$content = is_array( $content ) ? $content : array();
		foreach ( array( 'cs_list', 'avoid_keywords' ) as $avoid_key ) {
			if ( isset( $content[ $avoid_key ] ) && is_string( $content[ $avoid_key ] ) ) {
				$content[ $avoid_key ] = AI_Scribe_Prompt_Manager::normalise_stored_text( $content[ $avoid_key ] );
			}
		}
		$prompts = get_option( 'ab_prompts_content', array() );
		$prompts = is_array( $prompts ) ? $prompts : array();
		foreach ( $prompts as $prompt_key => $prompt_value ) {
			if ( is_string( $prompt_value ) ) {
				$prompts[ $prompt_key ] = AI_Scribe_Prompt_Manager::normalise_stored_text( $prompt_value );
			}
		}

		wp_send_json_success(
			array(
				'providers' => $this->provider_status(),
				'engine'    => $engine,
				'content'   => $content,
				'images'    => get_option( 'ab_gpt_image_settings', array() ),
				'prompts'   => $prompts,
				'ui_prefs'  => $this->current_ui_prefs(),
			)
		);
	}

	/**
	 * Contract v1.1 §12 — ai_scribe_save_ui_prefs.
	 *
	 * Payload: prefs JSON ({theme: "light"|"dark"|"auto"}), stored in user meta.
	 */
	public function handle_save_ui_prefs() {
		if ( ! $this->guard( 'edit_posts' ) ) {
			return;
		}

		$raw   = isset( $_POST['prefs'] ) ? wp_unslash( (string) $_POST['prefs'] ) : '';
		$prefs = json_decode( $raw, true );
		if ( ! is_array( $prefs ) ) {
			wp_send_json_error(
				array(
					'code'      => 'invalid_params',
					'message'   => 'A prefs object is required.',
					'retryable' => false,
				)
			);
			return;
		}

		$stored = $this->current_ui_prefs();
		if ( isset( $prefs['theme'] ) ) {
			$theme = sanitize_key( $prefs['theme'] );
			if ( in_array( $theme, array( 'light', 'dark', 'auto' ), true ) ) {
				$stored['theme'] = $theme;
			}
		}

		update_user_meta( get_current_user_id(), 'ai_scribe_ui_prefs', $stored );
		wp_send_json_success( array( 'prefs' => $stored ) );
	}

	private function current_ui_prefs() {
		$prefs = get_user_meta( get_current_user_id(), 'ai_scribe_ui_prefs', true );
		return is_array( $prefs ) ? $prefs : array();
	}
}
