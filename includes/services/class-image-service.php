<?php
/**
 * Image Service for AI-Scribe Plugin
 *
 * Handles AI image generation using the existing Opace AI Hub infrastructure,
 * Config Manager, and Prompt Manager for proper modular architecture.
 *
 * Migrated from generate_gpt_image_1() function in article_builder_backup.php
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
 * Class AI_Scribe_Image_Service
 *
 * Properly refactored to use existing infrastructure:
 * - Opace AI Hub Adapter for all API calls
 * - Config Manager for all settings
 * - Prompt Manager for image prompts
 * - Security Service for nonce validation
 */
class AI_Scribe_Image_Service extends AI_Scribe_Base_Service {

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
	 * Security service instance
	 *
	 * @var AI_Scribe_Security_Service
	 */
	private $security_service;

	/**
	 * Image HTML service instance
	 *
	 * @var AI_Scribe_Image_HTML_Service
	 */
	private $image_html_service;

	/**
	 * Constructor
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 * @param AI_Scribe_AI_Core_Adapter $ai_core_adapter Opace AI Hub adapter
	 * @param AI_Scribe_Config_Manager $config_manager Config manager
	 * @param AI_Scribe_Prompt_Manager $prompt_manager Prompt manager
	 * @param AI_Scribe_Security_Service $security_service Security service
	 * @param AI_Scribe_Image_HTML_Service $image_html_service Image HTML service
	 */
	public function __construct(
		AI_Scribe_Logger $logger,
		AI_Scribe_AI_Core_Adapter $ai_core_adapter,
		AI_Scribe_Config_Manager $config_manager,
		AI_Scribe_Prompt_Manager $prompt_manager,
		AI_Scribe_Security_Service $security_service,
		AI_Scribe_Image_HTML_Service $image_html_service
	) {
		parent::__construct( $logger );
		$this->ai_core_adapter    = $ai_core_adapter;
		$this->config_manager     = $config_manager;
		$this->prompt_manager     = $prompt_manager;
		$this->security_service   = $security_service;
		$this->image_html_service = $image_html_service;
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
			$this->log_error( 'Image service validation failed: Opace AI Hub adapter not available' );
			return 'Opace AI Hub adapter not available';
		}

		if ( ! $this->config_manager ) {
			$this->log_error( 'Image service validation failed: Config manager not available' );
			return 'Config manager not available';
		}

		return true;
	}

	/**
	 * Providers that can generate images and have a key on this site.
	 *
	 * Ordered best first by Opace AI Hub, which ranks them by the priority of their
	 * best image model. Empty means this site cannot generate images at all —
	 * an Anthropic-only site, for instance, because Anthropic publishes no
	 * image generation API.
	 *
	 * @return array List of provider ids
	 */
	public static function available_image_providers() {
		if ( ! class_exists( 'AICore\\Registry\\ModelRegistry' ) ) {
			return array();
		}

		$hub = get_option( 'ai_core_settings', array() );
		$hub = is_array( $hub ) ? $hub : array();

		$available = array();

		foreach ( AICore\Registry\ModelRegistry::getImageProviders() as $provider ) {
			if ( empty( $hub[ $provider . '_api_key' ] ) ) {
				continue;
			}

			/*
			 * The registry says which providers CAN generate images; the account
			 * decides whether this one actually may. Google grants image models
			 * per account, so a Gemini key on its own is not proof: offering the
			 * controls on that basis produced a request that always failed. When
			 * a live list has been fetched, it is the authority; before then the
			 * registry's answer stands so a fresh install is not crippled.
			 */
			$live = AI_Scribe_Model_Resolver::live_models( $provider );
			if ( ! empty( $live ) && '' === AI_Scribe_Model_Resolver::best_live_image_model( $provider ) ) {
				continue;
			}

			$available[] = $provider;
		}

		return $available;
	}

	/**
	 * Can this site generate images at all?
	 *
	 * @return bool
	 */
	public static function images_available() {
		return ! empty( self::available_image_providers() );
	}

	/**
	 * Why image generation is unavailable, in words a site owner can act on.
	 *
	 * @return string
	 */
	public static function image_unavailable_message() {
		return __( 'Image generation is unavailable because none of your configured providers can generate images. OpenAI and Google Gemini both can; Anthropic does not offer image generation at all. Add an OpenAI or Google Gemini API key on the Opace AI Hub settings page to turn images back on. Everything else in the wizard works without them.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' );
	}

	/**
	 * Human-readable provider name.
	 *
	 * @param string $provider Provider id.
	 * @return string
	 */
	public static function provider_label( $provider ) {
		$labels = array(
			'openai'    => 'OpenAI',
			'anthropic' => 'Anthropic',
			'gemini'    => 'Google Gemini',
		);

		return isset( $labels[ $provider ] ) ? $labels[ $provider ] : ucfirst( (string) $provider );
	}

	/**
	 * The image model this site should use when nothing has been chosen.
	 *
	 * Taken from the hub's per-provider image default where one was recorded
	 * when the key was validated, otherwise from the registry's own ranking.
	 *
	 * @return string Model id, empty when no provider can generate images
	 */
	private function default_image_model() {
		$providers = self::available_image_providers();

		if ( empty( $providers ) ) {
			return '';
		}

		$provider = $providers[0];

		$hub = get_option( 'ai_core_settings', array() );
		if ( is_array( $hub )
			&& ! empty( $hub['provider_image_models'][ $provider ] )
			&& AI_Scribe_Model_Resolver::is_usable( (string) $hub['provider_image_models'][ $provider ] ) ) {
			return (string) $hub['provider_image_models'][ $provider ];
		}

		// The account's own list before the bundled registry's ranking: the
		// registry can name an image model this account was never granted.
		$live = AI_Scribe_Model_Resolver::best_live_image_model( $provider );
		if ( '' !== $live ) {
			return $live;
		}

		$preferred = AICore\Registry\ModelRegistry::getPreferredImageModel( $provider );

		return $preferred ? (string) $preferred : '';
	}

	/**
	 * Which provider serves a given image model.
	 *
	 * @param string $model Image model id.
	 * @return string Provider id, empty when nothing can serve it
	 */
	private function image_provider_for_model( $model ) {
		if ( '' === (string) $model || ! class_exists( 'AICore\\AICore' ) ) {
			return '';
		}

		$provider = AICore\AICore::resolveImageProvider( (string) $model );

		if ( $provider ) {
			return (string) $provider;
		}

		// AICore resolves against the keys it was initialised with; fall back
		// to what this site has stored so a mis-timed init cannot silently
		// disable images.
		$available = self::available_image_providers();

		return $available ? $available[0] : '';
	}

	/**
	 * Main image generation handler
	 *
	 * Migrated from generate_gpt_image_1() function preserving ALL functionality:
	 * - Nonce verification
	 * - Image prompt processing
	 * - WordPress media library integration
	 * - Error handling with fallbacks
	 * - Debug logging
	 *
	 * @return void
	 */
	public function generate_image() {
		// Add error handling to catch any fatal errors
		try {
			// 🚨 CENTRALIZED DEBUG CONTROL - Only log if debug mode is enabled
			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] === IMAGE GENERATION AJAX HANDLER STARTED ===' );
			}

			// Verify nonce using Security Service
			if ( ! $this->security_service->verify_nonce( $_POST['security'] ?? '', 'ai_scribe_nonce' ) ) {
				if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
					ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Security check failed - invalid nonce' );
				}
				wp_send_json_error(
					array(
						'msg'           => 'Security nonce is missing or invalid. Please refresh the page.',
						'nonce_expired' => true,
						'console_debug' => '[AI_SCRIBE_IMAGE_DEBUG] Security check failed - invalid nonce',
					)
				);
				return;
			}

			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Image generation started - nonce verified' );
			}

			// Sanitize input data
			$prompt = sanitize_text_field( wp_unslash( $_POST['prompt'] ?? '' ) );

			// L-16: optional user guidance for this one image. Appended to the
			// formula prompt when both exist; used alone when only it exists.
			$user_prompt = sanitize_text_field( wp_unslash( $_POST['user_prompt'] ?? '' ) );
			if ( $user_prompt !== '' ) {
				$prompt = ( $prompt === '' ) ? $user_prompt : trim( $prompt . '. ' . $user_prompt );
			}

			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Prompt received: ' . $prompt );
			}

			if ( empty( $prompt ) ) {
				if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
					ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Empty prompt - aborting' );
				}
				wp_send_json_error(
					array(
						'msg'           => 'Image prompt is required.',
						'console_debug' => '[AI_SCRIBE_IMAGE_DEBUG] Empty prompt - aborting',
					)
				);
				return;
			}

			// Get image configuration using Config Manager. An empty setting is
			// resolved against whatever the site can actually reach — the old
			// 'gpt-image-1' literal made an OpenAI key a hidden prerequisite on
			// a site that had only ever configured Gemini.
			$overrides   = $this->request_image_overrides();
			$image_model = (string) ( $overrides['model'] ?? $this->config_manager->get( 'image.model', '' ) );
			$image_size  = (string) ( $overrides['size'] ?? $this->config_manager->get( 'image.size', '1024x1024' ) );

			/*
			 * A saved image model is only usable while its provider still has
			 * a key. A site that once had OpenAI, or that inherited the old
			 * 'gpt-image-1' default, kept sending image requests to OpenAI
			 * after switching to Gemini and every one of them failed with
			 * "OpenAI API key not configured for image generation".
			 */
			if ( '' !== $image_model && ! AI_Scribe_Model_Resolver::is_usable( $image_model ) ) {
				$image_model = '';
			}

			if ( '' === $image_model ) {
				$image_model = (string) $this->default_image_model();
			}

			$image_provider = $this->image_provider_for_model( $image_model );

			// Initialize debug messages
			$debug_messages   = array();
			$debug_messages[] = '🎨 AI SCRIBE IMAGE DEBUG START';
			$debug_messages[] = 'Image Model: ' . ( $image_model ? $image_model : '(none)' );
			$debug_messages[] = 'Image Provider: ' . ( $image_provider ? $image_provider : '(none)' );
			$debug_messages[] = 'Image Size: ' . $image_size;
			$debug_messages[] = 'Prompt: ' . $prompt;

			// The provider that owns the chosen image model is the one whose key
			// matters. Reporting "OpenAI key required" on a Gemini-only site was
			// both wrong and unactionable.
			if ( ! $image_provider ) {
				wp_send_json_error(
					array(
						'msg'   => self::image_unavailable_message(),
						'debug' => $debug_messages,
					)
				);
				return;
			}

			$api_validation = $this->ai_core_adapter->validate_api_keys();
			if ( empty( $api_validation[ $image_provider ]['configured'] ) ) {
				wp_send_json_error(
					array(
						'msg'   => sprintf(
							/* translators: 1: provider name, 2: image model id. */
							__( 'The %1$s API key is required to generate images with %2$s. Add it on the Opace AI Hub settings page, or choose an image model from a provider you have configured.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
							self::provider_label( $image_provider ),
							$image_model
						),
						'debug' => $debug_messages,
					)
				);
				return;
			}

			$debug_messages[] = self::provider_label( $image_provider ) . ' API key: present (length: ' . $api_validation[ $image_provider ]['key_length'] . ')';

			// Get image format setting
			$image_format  = (string) ( $overrides['format'] ?? get_option( 'ab_image_format', 'png' ) );
			$valid_formats = array( 'png', 'jpeg', 'webp' );
			if ( ! in_array( $image_format, $valid_formats, true ) ) {
				$image_format = 'png'; // Fallback to png if invalid
			}

			// Prepare image generation options
			$image_options = array(
				'model'    => $image_model,
				'provider' => $image_provider,
				'size'     => $image_size,
				'format'   => $image_format, // CRITICAL FIX: Pass format to Opace AI Hub
				'n'        => 1,
			);

			// quality, style and background are OpenAI images-endpoint controls.
			// Gemini's :generateContent takes none of them and 400s on unknown
			// keys, so they are only ever attached for the provider that has
			// them.
			if ( 'openai' === $image_provider ) {
				// gpt-image takes low|medium|high, DALL-E takes standard|hd.
				$is_gpt_image     = 0 === strpos( $image_model, 'gpt-image' );
				$image_quality    = $overrides['quality'] ?? $this->config_manager->get( 'image.quality', $is_gpt_image ? 'medium' : 'standard' );
				$debug_messages[] = 'Image Quality: ' . $image_quality;

				$image_options['quality'] = $image_quality;

				// Style only applies to DALL-E 3.
				if ( 'dall-e-3' === $image_model ) {
					$image_style = $overrides['style'] ?? $this->config_manager->get( 'image.style', 'vivid' );
					if ( ! in_array( $image_style, array( 'vivid', 'natural' ), true ) ) {
						$image_style = 'vivid';
					}
					$image_options['style'] = $image_style;
				}

				// Background (gpt-image models: auto | transparent | opaque).
				$image_background = $overrides['background'] ?? $this->config_manager->get( 'image.background', get_option( 'ab_image_background', 'auto' ) );
				if ( $is_gpt_image && in_array( $image_background, array( 'transparent', 'opaque' ), true ) ) {
					$image_options['background'] = $image_background;
				}
			}

			$debug_messages[] = 'Image generation options: ' . json_encode( $image_options );

			// Generate image using Opace AI Hub Adapter
			ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] About to call Opace AI Hub adapter for image generation' );
			ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Image options: ' . json_encode( $image_options ) );

			$response = $this->ai_core_adapter->generate_image( $prompt, $image_options );

			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Opace AI Hub adapter returned: ' . print_r( $response, true ) );
			}

			if ( is_wp_error( $response ) ) {
				if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
					ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Opace AI Hub returned WP_Error: ' . $response->get_error_message() );
				}
				$this->handle_image_error( $response, $debug_messages );
				return;
			}

			// Process successful response
			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Processing successful response' );
			}
			$this->process_image_response( $response, $prompt, $debug_messages );

		} catch ( Exception $e ) {
			ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] FATAL ERROR in image generation: ' . $e->getMessage() );
			ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Stack trace: ' . $e->getTraceAsString() );

			wp_send_json_error(
				array(
					'msg'           => 'A fatal error occurred during image generation: ' . $e->getMessage(),
					'console_debug' => '[AI_SCRIBE_IMAGE_DEBUG] FATAL ERROR: ' . $e->getMessage(),
					'error_type'    => 'fatal_error',
				)
			);
		} catch ( Error $e ) {
			ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] PHP ERROR in image generation: ' . $e->getMessage() );
			ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Stack trace: ' . $e->getTraceAsString() );

			wp_send_json_error(
				array(
					'msg'           => 'A PHP error occurred during image generation: ' . $e->getMessage(),
					'console_debug' => '[AI_SCRIBE_IMAGE_DEBUG] PHP ERROR: ' . $e->getMessage(),
					'error_type'    => 'php_error',
				)
			);
		}
	}

	/**
	 * Generate one image and store it in the media library, returning data
	 * instead of emitting JSON. The reusable core the section-image endpoint
	 * iterates over; single-image AJAX keeps its original handler.
	 *
	 * @param string $prompt Full image prompt.
	 * @return array|WP_Error {attachment_id, local_url, image_html, alt_text, caption}
	 */
	public function generate_stored_image( $prompt, array $overrides = array() ) {
		$prompt = trim( (string) $prompt );
		if ( '' === $prompt ) {
			return new WP_Error( 'empty_prompt', __( 'An image prompt is required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		$image_model = (string) ( $overrides['model'] ?? $this->config_manager->get( 'image.model', '' ) );
		if ( '' !== $image_model && ! AI_Scribe_Model_Resolver::is_usable( $image_model ) ) {
			$image_model = '';
		}
		if ( '' === $image_model ) {
			$image_model = (string) $this->default_image_model();
		}

		$image_provider = $this->image_provider_for_model( $image_model );
		if ( ! $image_provider ) {
			return new WP_Error( 'no_image_provider', self::image_unavailable_message() );
		}

		$image_format  = (string) ( $overrides['format'] ?? get_option( 'ab_image_format', 'png' ) );
		$valid_formats = array( 'png', 'jpeg', 'webp' );
		if ( ! in_array( $image_format, $valid_formats, true ) ) {
			$image_format = 'png';
		}

		$image_options = array(
			'model'    => $image_model,
			'provider' => $image_provider,
			'size'     => $overrides['size'] ?? $this->config_manager->get( 'image.size', '1024x1024' ),
			'format'   => $image_format,
			'n'        => 1,
		);

		if ( 'openai' === $image_provider ) {
			$is_gpt_image             = 0 === strpos( $image_model, 'gpt-image' );
			$image_options['quality'] = $overrides['quality'] ?? $this->config_manager->get( 'image.quality', $is_gpt_image ? 'medium' : 'standard' );
			if ( 'dall-e-3' === $image_model ) {
				$image_style = $overrides['style'] ?? $this->config_manager->get( 'image.style', 'vivid' );
				$image_options['style'] = in_array( $image_style, array( 'vivid', 'natural' ), true ) ? $image_style : 'vivid';
			}
			if ( isset( $overrides['background'] ) && $is_gpt_image && in_array( $overrides['background'], array( 'transparent', 'opaque' ), true ) ) {
				$image_options['background'] = $overrides['background'];
			}
		}

		$response = $this->ai_core_adapter->generate_image( $prompt, $image_options );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$image_url = '';
		if ( ! empty( $response['url'] ) ) {
			$image_url = $response['url'];
		} elseif ( ! empty( $response['base64_data'] ) ) {
			$image_url = 'data:image/png;base64,' . $response['base64_data'];
		}
		if ( '' === $image_url ) {
			return new WP_Error( 'no_image_data', __( 'The image provider returned no image data.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		$debug_messages = array();
		return $this->save_image_to_media_library( $image_url, $prompt, $debug_messages, $response );
	}

	/**
	 * AJAX: bulk-add "one image per section" — one distinct image per H2
	 * section, generated server-side from the section headings (L-17/L-18).
	 *
	 * Request (POST):
	 *   security      ai_scribe_nonce
	 *   sections      JSON array of section heading strings (required)
	 *   article_title Optional article title for context
	 *   user_prompt   Optional style guidance appended to every image
	 *   index         Optional 0-based section index; when present, only that
	 *                 section's image is generated, so the UI can call once
	 *                 per section and render real progress ("3 of 5").
	 *
	 * Response (success):
	 *   Single mode: {index, total, section, attachment_id, url, image_html, alt_text}
	 *   Batch mode:  {total, generated, images: [per-section results], errors: [{index, section, message}]}
	 *
	 * @return void
	 */
	public function handle_generate_section_images() {
		if ( ! $this->security_service->verify_nonce( $_POST['security'] ?? '', 'ai_scribe_nonce' ) ) {
			wp_send_json_error(
				array(
					'msg'           => 'Security nonce is missing or invalid. Please refresh the page.',
					'nonce_expired' => true,
				)
			);
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'msg' => 'You do not have permission to do this.' ) );
			return;
		}

		$sections_raw = isset( $_POST['sections'] ) ? wp_unslash( (string) $_POST['sections'] ) : '';
		$decoded      = json_decode( $sections_raw, true );
		$sections     = array();
		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $section ) {
				$section = sanitize_text_field( (string) $section );
				if ( '' !== $section ) {
					$sections[] = $section;
				}
			}
		}
		if ( empty( $sections ) ) {
			wp_send_json_error( array( 'msg' => __( 'A list of section headings is required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
			return;
		}

		$article_title = sanitize_text_field( wp_unslash( $_POST['article_title'] ?? '' ) );
		$user_prompt   = sanitize_text_field( wp_unslash( $_POST['user_prompt'] ?? '' ) );
		$prompt_exact  = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ?? '' ) );
		$overrides     = $this->request_image_overrides();
		$total         = count( $sections );

		// Single-section mode: real progress, one billing unit per request.
		if ( isset( $_POST['index'] ) && '' !== $_POST['index'] ) {
			$index = (int) $_POST['index'];
			if ( $index < 0 || $index >= $total ) {
				wp_send_json_error( array( 'msg' => __( 'Section index out of range.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}
			$prompt = '' !== $prompt_exact ? $prompt_exact : $this->section_image_prompt( $sections[ $index ], $article_title, $user_prompt, $overrides['style'] ?? '' );
			$result = $this->generate_stored_image( $prompt, $overrides );
			if ( is_wp_error( $result ) ) {
				$retryable = self::is_retryable_image_error( $result );
				wp_send_json_error(
					array(
						'msg'       => $result->get_error_message(),
						'code'      => $result->get_error_code(),
						'retryable' => $retryable,
						'index'     => $index,
						'total'     => $total,
						'section'   => $sections[ $index ],
					),
					$retryable ? 503 : 400
				);
				return;
			}
			wp_send_json_success(
				array(
					'index'         => $index,
					'total'         => $total,
					'section'       => $sections[ $index ],
					'attachment_id' => $result['attachment_id'],
					'url'           => $result['local_url'],
					'image_html'    => $result['image_html'],
					'alt_text'      => $result['alt_text'],
					'caption'       => $result['caption'],
					'width'         => $result['width'],
					'height'        => $result['height'],
					'prompt_used'   => $prompt,
					'image_options' => $overrides,
				)
			);
			return;
		}

		// Batch mode: iterate every section server-side.
		if ( function_exists( 'set_time_limit' ) ) {
			// One provider call per section; give the batch room to finish.
			@set_time_limit( 120 * $total ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$images = array();
		$errors = array();
		foreach ( $sections as $index => $section ) {
			$prompt = $this->section_image_prompt( $section, $article_title, $user_prompt, $overrides['style'] ?? '' );
			$result = $this->generate_stored_image( $prompt, $overrides );
			if ( is_wp_error( $result ) ) {
				$errors[] = array(
					'index'   => $index,
					'section' => $section,
					'message' => $result->get_error_message(),
				);
				continue;
			}
			$images[] = array(
				'index'         => $index,
				'section'       => $section,
				'attachment_id' => $result['attachment_id'],
				'url'           => $result['local_url'],
				'image_html'    => $result['image_html'],
				'alt_text'      => $result['alt_text'],
				'caption'       => $result['caption'],
				'width'         => $result['width'],
				'height'        => $result['height'],
				'prompt_used'   => $prompt,
				'image_options' => $overrides,
			);
			if ( $index < $total - 1 ) {
				sleep( 1 ); // Respect provider rate limits between calls.
			}
		}

		wp_send_json_success(
			array(
				'total'     => $total,
				'generated' => count( $images ),
				'images'    => $images,
				'errors'    => $errors,
			)
		);
	}

	/**
	 * A distinct per-section image prompt derived from the section heading.
	 *
	 * @param string $section       Section (H2) heading.
	 * @param string $article_title Article title for context, may be ''.
	 * @param string $user_prompt   Optional user style guidance.
	 * @return string
	 */
	private function section_image_prompt( $section, $article_title, $user_prompt = '', $style_override = '' ) {
		$prompt = $section . ' - Create an image that visually illustrates the topic "' . $section . '"';
		if ( '' !== $article_title ) {
			$prompt .= ' for an article titled "' . $article_title . '"';
		}
		$prompt .= '. The image must relate specifically to this section, not the article in general. Do not include any words or text in the image.';

		$style = '' !== $style_override ? $style_override : get_option( 'ab_image_style', '' );
		if ( is_string( $style ) && '' !== $style && ! in_array( $style, array( 'vivid', 'natural' ), true ) ) {
			// A descriptive style saved in settings (not the DALL-E API enum).
			$prompt .= ' Style: ' . sanitize_text_field( $style ) . '.';
		}
		if ( '' !== $user_prompt ) {
			$prompt .= ' ' . $user_prompt;
		}

		return $prompt;
	}

	/**
	 * Sanitised article-local image options supplied by the wizard. These are
	 * request-scoped only: global WordPress options are never written here.
	 *
	 * @return array<string,string>
	 */
	private function request_image_overrides() {
		$raw = isset( $_POST['image_options'] ) ? json_decode( wp_unslash( (string) $_POST['image_options'] ), true ) : array();
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$allowed = array(
			'model'      => null,
			'size'       => array( 'auto', '1024x1024', '1024x1536', '1536x1024' ),
			'quality'    => array( 'low', 'medium', 'high', 'standard', 'hd' ),
			'format'     => array( 'png', 'jpeg', 'webp' ),
			'background' => array( 'auto', 'transparent', 'opaque' ),
			'style'      => null,
		);
		$clean = array();
		foreach ( $allowed as $key => $values ) {
			if ( ! isset( $raw[ $key ] ) || ! is_string( $raw[ $key ] ) || '' === trim( $raw[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( $raw[ $key ] );
			if ( null === $values || in_array( $value, $values, true ) ) {
				$clean[ $key ] = $value;
			}
		}
		return $clean;
	}

	/**
	 * Handle image generation errors
	 *
	 * @param WP_Error $error Error object
	 * @param array $debug_messages Debug messages
	 * @return void
	 */
	private function handle_image_error( $error, $debug_messages ) {
		$debug_messages[] = '❌ Image generation failed: ' . $error->get_error_message();
		$retryable        = self::is_retryable_image_error( $error );

		wp_send_json_error(
			array(
				'msg'       => 'Failed to generate image: ' . $error->get_error_message(),
				'code'      => $error->get_error_code(),
				'retryable' => $retryable,
				'debug'     => $debug_messages,
			),
			$retryable ? 503 : 400
		);
	}

	/**
	 * Whether a provider failure is safe to repeat without user changes.
	 * Opace AI Hub currently normalises provider exceptions to WP_Error and some
	 * providers expose the HTTP status only in the message, so both structured
	 * data and a narrow transient-message allowlist are inspected.
	 *
	 * @param WP_Error $error Provider error.
	 * @return bool
	 */
	private static function is_retryable_image_error( $error ) {
		$data   = method_exists( $error, 'get_error_data' ) ? $error->get_error_data() : null;
		$status = is_array( $data ) ? (int) ( $data['status'] ?? $data['status_code'] ?? 0 ) : (int) $data;
		if ( 429 === $status || $status >= 500 ) {
			return true;
		}
		$haystack = strtolower( $error->get_error_code() . ' ' . $error->get_error_message() );
		foreach ( array( '503', '429', 'temporarily unavailable', 'service unavailable', 'overloaded', 'resource exhausted', 'rate limit', 'timed out', 'timeout', 'curl error' ) as $needle ) {
			if ( false !== strpos( $haystack, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Process successful image response and save to WordPress media library
	 *
	 * @param array $response AI response
	 * @param string $prompt Original prompt
	 * @param array $debug_messages Debug messages
	 * @return void
	 */
	private function process_image_response( $response, $prompt, $debug_messages ) {
		// Handle different response formats from different models
		$image_url = '';

		if ( ! empty( $response['url'] ) ) {
			// DALL-E models return direct URL
			$image_url        = $response['url'];
			$debug_messages[] = '📥 Received HTTP URL from DALL-E model';
		} elseif ( ! empty( $response['base64_data'] ) ) {
			// gpt-image-1 returns base64 data, create data URL
			$image_url        = 'data:image/png;base64,' . $response['base64_data'];
			$debug_messages[] = '📥 Received base64 data from gpt-image-1, created data URL';
		}

		if ( empty( $image_url ) ) {
			wp_send_json_error(
				array(
					'msg'   => 'No image URL or base64 data received from AI service',
					'debug' => $debug_messages,
				)
			);
			return;
		}

		$debug_messages[] = '✅ Image generated successfully: ' . $image_url;

		// Download and save image to WordPress media library
		$attachment_result = $this->save_image_to_media_library( $image_url, $prompt, $debug_messages, $response );

		if ( is_wp_error( $attachment_result ) ) {
			wp_send_json_error(
				array(
					'msg'   => 'Failed to save image to media library: ' . $attachment_result->get_error_message(),
					'debug' => $debug_messages,
				)
			);
			return;
		}

		// Prepare success response - CRITICAL: Use WordPress AJAX format
		$response_data = array(
			// JavaScript expects these fields at the top level of data
			'url'           => $attachment_result['local_url'], // JavaScript looks for .url
			'image_url'     => $attachment_result['local_url'], // Backward compatibility
			'attachment_id' => $attachment_result['attachment_id'],
			'local_url'     => $attachment_result['local_url'],
			'image_html'    => $attachment_result['image_html'],
			'alt_text'      => $attachment_result['alt_text'],
			'caption'       => $attachment_result['caption'],
			'width'         => $attachment_result['width'],
			'height'        => $attachment_result['height'],
			'prompt_used'   => $prompt,
			'image_options' => $this->request_image_overrides(),
			'timestamp'     => time() * 1000, // JavaScript timestamp for cache management
			'console_debug' => implode( "\n", $debug_messages ), // Debug messages for console
		);

		// P8: the v2.6 trigger_js inline-script payload was removed - scripts in
		// AJAX JSON are inert in the v3 UI (innerHTML never executes them) and
		// no client reads the field (SS14.4 no-inline-script rule).

		// Add comprehensive response logging
		ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] 🎯 SENDING SUCCESS RESPONSE WITH JS TRIGGER' );
		ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Response data keys: ' . implode( ', ', array_keys( $response_data ) ) );
		ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Image URL: ' . $response_data['url'] );
		ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Attachment ID: ' . $response_data['attachment_id'] );

		// Ensure output buffer is clean before sending JSON
		if ( ob_get_level() ) {
			ob_clean();
		}

		// Send the success response
		wp_send_json_success( $response_data );

		// This should never be reached, but log if it is
		ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] ⚠️ WARNING: Code executed after wp_send_json_success()' );
	}

	/**
	 * Save image to WordPress media library
	 *
	 * @param string $image_url Remote image URL
	 * @param string $prompt Original prompt for filename
	 * @param array &$debug_messages Debug messages array
	 * @param array  $provider_response Normalised provider response, when available.
	 * @return array|WP_Error Attachment data or error
	 */
	private function save_image_to_media_library( $image_url, $prompt, &$debug_messages, $provider_response = array() ) {
		// Include WordPress media functions
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Media metadata describes the visible subject, never the generation
		// instruction. Section-image prompts deliberately start with the subject;
		// free-form prompts are reduced to a short, non-imperative label.
		$caption    = $this->automatic_caption( $provider_response, $prompt );
		$title_only = '' !== $caption ? $caption : $this->visual_description_from_prompt( $prompt );
		if ( '' === $title_only ) {
			$title_only = __( 'AI generated image', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' );
		}

		$debug_messages[] = '🧹 Clean title extracted: ' . $title_only;

		// Handle different image URL types
		if ( strpos( $image_url, 'data:image/' ) === 0 ) {
			// Handle base64 data URL (from gpt-image-1)
			$debug_messages[] = '🔧 Processing base64 data URL from gpt-image-1';

			// Extract base64 data from data URL
			$data_parts = explode( ',', $image_url, 2 );
			if ( count( $data_parts ) !== 2 ) {
				$debug_messages[] = '❌ Invalid data URL format';
				return new WP_Error( 'invalid_data_url', 'Invalid data URL format' );
			}

			$base64_data = $data_parts[1];
			$image_data  = base64_decode( $base64_data );

			if ( $image_data === false ) {
				$debug_messages[] = '❌ Failed to decode base64 data';
				return new WP_Error( 'base64_decode_failed', 'Failed to decode base64 data' );
			}

			// Create temporary file
			$temp_file = wp_tempnam( 'ai-image-' );
			if ( ! $temp_file ) {
				$debug_messages[] = '❌ Failed to create temporary file';
				return new WP_Error( 'temp_file_failed', 'Failed to create temporary file' );
			}

			// Write decoded image data to temp file
			$bytes_written = file_put_contents( $temp_file, $image_data );
			if ( $bytes_written === false ) {
				$debug_messages[] = '❌ Failed to write image data to temp file';
				wp_delete_file( $temp_file );
				return new WP_Error( 'write_temp_file_failed', 'Failed to write image data to temp file' );
			}

			$debug_messages[] = '✅ Base64 image data written to temp file: ' . $temp_file;

		} else {
			// Handle regular HTTP URL (from DALL-E models)
			$debug_messages[] = '📥 Downloading image from HTTP URL: ' . $image_url;

			$temp_file = download_url( $image_url );
			if ( is_wp_error( $temp_file ) ) {
				$debug_messages[] = '❌ Failed to download image: ' . $temp_file->get_error_message();
				return $temp_file;
			}

			$debug_messages[] = '✅ Image downloaded to temp file: ' . $temp_file;
		}

		// Generate clean filename using title only (not full prompt)
		$clean_filename = sanitize_file_name( $title_only );
		$clean_filename = substr( $clean_filename, 0, 50 );
		$clean_filename = trim( $clean_filename, '-' );
		if ( empty( $clean_filename ) ) {
			$clean_filename = 'ai-generated-image';
		}

		// Extension follows the bytes actually received. $response is not in
		// scope here — reading it raised an undefined-variable notice on every
		// single image saved, and the ?? simply swallowed the result.
		$actual_format = 'png';
		if ( preg_match( '#^data:image/([a-z0-9.+-]+)#i', $image_url, $ai_scribe_mime ) ) {
			$actual_format = strtolower( $ai_scribe_mime[1] );
		} elseif ( preg_match( '#\.(png|jpe?g|webp|gif)(?:[?#]|$)#i', $image_url, $ai_scribe_ext ) ) {
			$actual_format = strtolower( $ai_scribe_ext[1] );
		}
		if ( ! in_array( $actual_format, array( 'png', 'jpeg', 'jpg', 'webp', 'gif' ), true ) ) {
			$actual_format = 'png';
		}
		$file_extension = ( 'jpeg' === $actual_format ) ? 'jpg' : $actual_format;

		$debug_messages[] = '🎨 Using API-returned image format: ' . $actual_format . ' (extension: .' . $file_extension . ')';

		$clean_filename .= '-' . time() . '.' . $file_extension;

		// Prepare file array for media_handle_sideload
		$file_array = array(
			'name'     => $clean_filename,
			'tmp_name' => $temp_file,
		);

		$debug_messages[] = '📁 Saving to media library as: ' . $clean_filename;

		// Upload to media library with clean title as description
		$attachment_id = media_handle_sideload( $file_array, 0, $title_only );

		// Clean up temp file
		if ( file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}

		if ( is_wp_error( $attachment_id ) ) {
			$debug_messages[] = '❌ Failed to save to media library: ' . $attachment_id->get_error_message();
			return $attachment_id;
		}

		// CRITICAL: Set image metadata using just the title (matching v2.6)
		$debug_messages[] = '🏷️ Setting alt text to: ' . $title_only;

		// A visible caption must describe the generated subject. Planner labels
		// such as "article introduction" are deliberately stored as blank; Image
		// Studio still lets the author add, edit or remove the caption.
		$post_update_result = wp_update_post(
			array(
				'ID'           => $attachment_id,
				'post_title'   => $title_only,
				'post_excerpt' => $caption,
				'post_content' => '',
			),
			true
		); // Return WP_Error on failure

		if ( is_wp_error( $post_update_result ) ) {
			$debug_messages[] = '⚠️ Post update failed: ' . $post_update_result->get_error_message();
		} else {
			$debug_messages[] = '✅ Post metadata updated successfully';
		}

		// Method 2: Set alt text using WordPress standard meta field
		$alt_update       = update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title_only );
		$debug_messages[] = '🏷️ Alt text update result: ' . ( $alt_update ? 'SUCCESS' : 'FAILED' );

		// Get the local attachment URL
		$local_url = wp_get_attachment_url( $attachment_id );

		// CRITICAL: Add "saved to media library" confirmation logging
		$debug_messages[] = '✅ Image saved to media library with ID: ' . $attachment_id;
		$debug_messages[] = '🔗 Local image URL: ' . $local_url;
		$debug_messages[] = '🏷️ Alt text set to: ' . $title_only;
		$debug_messages[] = '📝 Caption created from the sanitised image subject and remains editable';

		// OPACE SPECIFIC LOGGING - Enhanced logging for user feedback
		ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_SUCCESS] ✅ Image saved to media library: ' . $clean_filename );
		ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_SUCCESS] 📁 Media Library ID: ' . $attachment_id );
		ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_SUCCESS] 🔗 URL: ' . $local_url );
		ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_SUCCESS] 🏷️ Title: ' . $title_only );
		ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_SUCCESS] ⏰ Timestamp: ' . gmdate( 'Y-m-d H:i:s' ) );

		// OPACE CONSOLE LOGS - Add to debug messages for console output
		$debug_messages[] = 'OPACE IMAGE CHECK: image stored in media library now';
		$debug_messages[] = 'OPACE IMAGE CHECK: image ALT text and editable visible caption added';
		$debug_messages[] = 'OPACE IMAGE CHECK: image name/title matched to article title';

		// Use centralized ImageHTMLService for consistent image HTML generation
		$metadata   = wp_get_attachment_metadata( $attachment_id );
		$image_data = array(
			'url'           => $local_url,
			'alt_text'      => $title_only,
			'caption'       => $caption,
			'attachment_id' => $attachment_id,
			'width'         => is_array( $metadata ) ? (int) ( $metadata['width'] ?? 0 ) : 0,
			'height'        => is_array( $metadata ) ? (int) ( $metadata['height'] ?? 0 ) : 0,
		);

		$image_html = $this->image_html_service->generateImageHTML( $image_data, AI_Scribe_Image_HTML_Service::FORMAT_WORDPRESS_BLOCK );

		return array(
			'attachment_id' => $attachment_id,
			'local_url'     => $local_url,
			'image_html'    => $image_html,
			'alt_text'      => $title_only,
			'caption'       => $caption,
			'width'         => $image_data['width'],
			'height'        => $image_data['height'],
		);
	}

	/**
	 * Convert an image-generation prompt into concise visual metadata.
	 *
	 * @param string $prompt Provider prompt.
	 * @return string Human-readable image subject.
	 */
	private function visual_description_from_prompt( $prompt ) {
		$plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $prompt ) ) );
		if ( '' === $plain ) {
			return '';
		}

		$subject = '';
		if ( preg_match( '/for the section\s+["“]([^"”]+)["”]/iu', $plain, $section_match )
			&& ! $this->is_generic_image_label( $section_match[1] ) ) {
			$subject = trim( $section_match[1] );
		} elseif ( preg_match( '/(?:(?:article|blog post)\s+(?:titled|called)|in\s+the\s+article)\s+["“]([^"”]+)["”]/iu', $plain, $title_match ) ) {
			$subject = trim( $title_match[1] );
		}
		if ( '' === $subject && false !== stripos( $plain, ' - create an image' ) ) {
			$subject = trim( preg_split( '/\s+-\s+create an image/i', $plain, 2 )[0] );
		}
		if ( '' === $subject ) {
			$sentences = preg_split( '/(?<=[.!?])\s+/u', $plain );
			$subject   = trim( (string) ( $sentences[0] ?? '' ) );
		}

		$subject = preg_replace( '/^(?:please\s+)?(?:create|generate|make|produce|design|illustrate|show)\s+(?:an?\s+)?(?:image|illustration|visual|graphic|picture)?\s*(?:of|showing|depicting|for)?\s*/i', '', $subject );
		$subject = preg_replace( '/^(?:an?\s+)?editorial\s+(?:image|illustration|visual)\s+(?:of|showing|depicting|for)\s+/i', '', $subject );
		$subject = trim( $subject, " \t\n\r\0\x0B:;,.-\"'" );

		if ( $this->is_generic_image_label( $subject )
			|| preg_match( '/\b(?:do not include|must not include|no text|visual style|highly detailed|image prompt)\b/i', $subject ) ) {
			return '';
		}

		return $subject;
	}

	/**
	 * Select a caption-quality description without exposing prompt boilerplate.
	 *
	 * @param array  $provider_response Normalised image response.
	 * @param string $prompt            Prompt sent to the provider.
	 * @return string Caption, or an empty string when nothing descriptive exists.
	 */
	private function automatic_caption( $provider_response, $prompt ) {
		$candidates = array();
		if ( is_array( $provider_response ) ) {
			foreach ( array( 'caption', 'description', 'revised_prompt' ) as $key ) {
				if ( isset( $provider_response[ $key ] ) && is_string( $provider_response[ $key ] ) ) {
					$candidates[] = $provider_response[ $key ];
				}
			}
			if ( isset( $provider_response['data'][0] ) && is_array( $provider_response['data'][0] ) ) {
				foreach ( array( 'caption', 'description', 'revised_prompt' ) as $key ) {
					if ( isset( $provider_response['data'][0][ $key ] ) && is_string( $provider_response['data'][0][ $key ] ) ) {
						$candidates[] = $provider_response['data'][0][ $key ];
					}
				}
			}
		}
		$candidates[] = $prompt;

		foreach ( $candidates as $candidate ) {
			$caption = $this->visual_description_from_prompt( $candidate );
			if ( '' !== $caption ) {
				return $caption;
			}
		}
		return '';
	}

	/** Return true for workflow labels that do not describe visible content. */
	private function is_generic_image_label( $value ) {
		$label = strtolower( trim( preg_replace( '/[^\p{L}\p{N}]+/u', ' ', (string) $value ) ) );
		return '' === $label || in_array(
			$label,
			array(
				'article introduction',
				'introduction',
				'article intro',
				'featured image',
				'feature image',
				'article image',
				'article illustration',
				'section image',
				'section illustration',
				'blog image',
			),
			true
		);
	}

	/**
	 * Generate filename from prompt with format
	 *
	 * @param string $prompt Image prompt
	 * @param string $format Image format (png, jpeg, webp)
	 * @return string Sanitized filename
	 */
	private function generate_filename( $prompt, $format = 'png' ) {
		// Clean prompt for filename
		$filename = sanitize_file_name( $prompt );
		$filename = substr( $filename, 0, 50 ); // Limit length
		$filename = trim( $filename, '-' );

		if ( empty( $filename ) ) {
			$filename = 'ai-generated-image';
		}

		// Add timestamp to ensure uniqueness
		$filename .= '-' . time();

		// Convert jpeg to jpg for file extension
		$file_extension = ( $format === 'jpeg' ) ? 'jpg' : $format;

		return $filename . '.' . $file_extension;
	}

	/**
	 * AJAX handler to find recent images by title (fallback for timeout scenarios)
	 */
	public function find_recent_image() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['security'], 'ai_scribe_nonce' ) ) {
			wp_send_json_error( array( 'msg' => 'Security check failed' ) );
			return;
		}

		$title = sanitize_text_field( wp_unslash( $_POST['title'] ) );
		if ( empty( $title ) ) {
			wp_send_json_error( array( 'msg' => 'No title provided' ) );
			return;
		}

		// Search for recent images with matching title
		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'posts_per_page' => 5,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'date_query'     => array(
				array(
					'after' => '5 minutes ago',
				),
			),
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_wp_attachment_image_alt',
					'value'   => $title,
					'compare' => 'LIKE',
				),
			),
		);

		$images = get_posts( $args );

		if ( empty( $images ) ) {
			// Fallback: search by post title
			$args['meta_query'] = array();
			$args['s']          = $title;
			$images             = get_posts( $args );
		}

		if ( ! empty( $images ) ) {
			$image         = $images[0]; // Get most recent
			$attachment_id = $image->ID;
			$image_url     = wp_get_attachment_url( $attachment_id );
			$alt_text      = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: $title;
			$caption       = isset( $image->post_excerpt ) ? trim( (string) $image->post_excerpt ) : '';

			// Use centralized ImageHTMLService for consistent image HTML generation
			$image_data = array(
				'url'           => $image_url,
				'alt_text'      => $alt_text,
				'caption'       => $caption,
				'attachment_id' => $attachment_id,
			);

			$response_data              = $this->image_html_service->generateImageResponse( $image_data, AI_Scribe_Image_HTML_Service::FORMAT_WORDPRESS_BLOCK );
			$response_data['image_url'] = $image_url; // Maintain backward compatibility

			wp_send_json_success( $response_data );
		} else {
			wp_send_json_error( array( 'msg' => 'No recent images found matching title' ) );
		}
	}

	/**
	 * Generate alt text from prompt
	 *
	 * @param string $prompt Image prompt
	 * @return string Alt text
	 */
	private function generate_alt_text( $prompt ) {
		// Clean and shorten prompt for alt text
		$alt_text = wp_strip_all_tags( $prompt );
		$alt_text = substr( $alt_text, 0, 100 );

		if ( empty( $alt_text ) ) {
			$alt_text = 'AI generated image';
		}

		return $alt_text;
	}

	/**
	 * Generate image using prompt template
	 *
	 * @param string $category Prompt category
	 * @param string $type Prompt type
	 * @param array $parameters Template parameters
	 * @return array|WP_Error Generation result
	 */
	public function generate_with_template( $category, $type, $parameters = array() ) {
		// Build prompt using Prompt Manager
		$prompt_data = $this->prompt_manager->build_prompt( $category, $type, $parameters );

		if ( is_array( $prompt_data ) && isset( $prompt_data['error'] ) ) {
			return new WP_Error( 'prompt_error', $prompt_data['message'] );
		}

		$prompt = $prompt_data['prompt'] ?? '';

		if ( empty( $prompt ) ) {
			return new WP_Error( 'empty_prompt', 'Generated prompt is empty' );
		}

		// Get image format setting
		$image_format  = get_option( 'ab_image_format', 'png' );
		$valid_formats = array( 'png', 'jpeg', 'webp' );
		if ( ! in_array( $image_format, $valid_formats, true ) ) {
			$image_format = 'png'; // Fallback to png if invalid
		}

		$image_model = (string) $this->config_manager->get( 'image.model', '' );
		if ( '' === $image_model ) {
			$image_model = (string) $this->default_image_model();
		}

		$image_provider = $this->image_provider_for_model( $image_model );

		if ( ! $image_provider ) {
			return new WP_Error( 'no_image_provider', self::image_unavailable_message() );
		}

		$image_options = array(
			'model'    => $image_model,
			'provider' => $image_provider,
			'size'     => $this->config_manager->get( 'image.size', '1024x1024' ),
			'format'   => $image_format, // CRITICAL FIX: Pass format to Opace AI Hub
			'n'        => 1,
		);

		// quality and style belong to OpenAI's images endpoint only.
		if ( 'openai' === $image_provider ) {
			$image_options['quality'] = $this->config_manager->get( 'image.quality', 'standard' );

			if ( 'dall-e-3' === $image_model ) {
				$api_style = $this->config_manager->get( 'image.style', 'vivid' );
				if ( ! in_array( $api_style, array( 'vivid', 'natural' ), true ) ) {
					$api_style = 'vivid';
				}
				$image_options['style'] = $api_style;
			}
		}

		// Generate image using Opace AI Hub Adapter
		return $this->ai_core_adapter->generate_image( $prompt, $image_options );
	}

	/**
	 * Batch generate images
	 *
	 * @param array $prompts Array of prompts
	 * @param array $options Generation options
	 * @return array Results array
	 */
	public function batch_generate( $prompts, $options = array() ) {
		$results = array();

		foreach ( $prompts as $index => $prompt ) {
			$this->log_info( "Generating image {$index} of " . count( $prompts ) );

			$result    = $this->ai_core_adapter->generate_image( $prompt, $options );
			$results[] = array(
				'prompt' => $prompt,
				'result' => $result,
				'index'  => $index,
			);

			// Add delay between requests to respect rate limits
			if ( $index < count( $prompts ) - 1 ) {
				sleep( 1 );
			}
		}

		return $results;
	}

	/**
	 * Get valid OpenAI API style parameter
	 * Ensures only 'vivid' or 'natural' are used for API calls
	 *
	 * @return string Valid OpenAI style parameter
	 */
	private function get_valid_api_style() {
		$style = $this->config_manager->get( 'image.style', 'vivid' );

		// Ensure only valid OpenAI styles are used
		if ( ! in_array( $style, array( 'vivid', 'natural' ) ) ) {
			return 'vivid'; // Default to vivid if invalid
		}

		return $style;
	}
}
