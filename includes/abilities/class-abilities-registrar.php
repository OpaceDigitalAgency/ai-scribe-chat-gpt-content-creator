<?php
/**
 * WordPress Abilities API registration for AI-Scribe (WP 6.9+/7.0)
 *
 * Registers AI-Scribe's generation surface as core Abilities so it is
 * discoverable by the Abilities API (and MCP clients built on it):
 * - ai-scribe/generate-article   (Express pipeline, one-shot article)
 * - ai-scribe/generate-titles    (wizard step 1)
 * - ai-scribe/humanize-content   (Humanise mode rewrite)
 * - ai-scribe/generate-seo-meta  (wizard step 9)
 *
 * Real core API surface used (discovered empirically on a running WP 7.0
 * — see REFACTOR.md §10 P4 STATUS):
 * - wp_register_ability( string $name, array $args ): ?WP_Ability
 *   (wp-includes/abilities-api.php, since 6.9.0) — MUST be called during
 *   the `wp_abilities_api_init` action or core raises _doing_it_wrong().
 * - wp_register_ability_category( string $slug, array $args )
 *   — MUST be called during `wp_abilities_api_categories_init`.
 * - $args: label, description, category (required string), input_schema /
 *   output_schema (JSON Schema arrays), execute_callback,
 *   permission_callback, meta.annotations/show_in_rest
 *   (shape mirrors core's own wp_register_core_abilities() in
 *   wp-includes/abilities.php).
 *
 * Fully existence-guarded: on WP < 6.9 the hooks simply never fire and
 * the guards prevent any fatal.
 *
 * @package AI_Scribe
 * @subpackage Abilities
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Abilities_Registrar
 */
class AI_Scribe_Abilities_Registrar {

	/**
	 * Ability category slug.
	 */
	const CATEGORY = 'ai-scribe';

	/** @var AI_Scribe_Service_Container|null */
	private $container;

	/**
	 * @param AI_Scribe_Service_Container|null $container Container to
	 *        resolve services from; falls back to the global container
	 *        at execute time (so tests can inject a stub).
	 */
	public function __construct( $container = null ) {
		$this->container = $container;
	}

	/**
	 * Attach the registration hooks. Safe on any WP version: on cores
	 * without the Abilities API the actions never fire.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register the ai-scribe ability category
	 * (runs on wp_abilities_api_categories_init).
	 *
	 * @return void
	 */
	public function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		// Indirect call: symbol only exists on WP 7.0+ and is function_exists-guarded above.
		call_user_func(
			'wp_register_ability_category',
			self::CATEGORY,
			array(
				'label'       => __( 'AI Scribe', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'description' => __( 'SEO article generation abilities provided by the Opace AI Scribe plugin.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			)
		);
	}

	/**
	 * Register the four abilities (runs on wp_abilities_api_init).
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$permission = static function () {
			return current_user_can( 'edit_posts' );
		};
		$meta       = array(
			'annotations'  => array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			),
			'show_in_rest' => true,
		);

		call_user_func(
			'wp_register_ability',
			'ai-scribe/generate-article',
			array(
				'label'               => __( 'Generate Article', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'description'         => __( 'Generates a complete SEO-optimised article (title, meta, tagline, outline, introduction, body, conclusion and Q&A) from an idea in a single call, using the AI Scribe Express pipeline and the site\'s editable prompt library.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						$this->common_input_properties(),
						array(
							'idea'               => array(
								'type'        => 'string',
								'description' => __( 'The article idea or topic to write about.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
							),
							'heading_tag'        => array(
								'type'        => 'string',
								'enum'        => array( 'H2', 'H3', 'H4', 'H5', 'H6' ),
								'description' => __( 'Heading tag for body sections. Default H2.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
							),
							'number_of_headings' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'maximum'     => 15,
								'description' => __( 'Number of body sections. Default 5.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
							),
							'avoid_keywords'     => array(
								'type'        => 'string',
								'description' => __( 'Comma-separated keywords to exclude.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
							),
						)
					),
					'required'             => array( 'idea' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'conversation_id' => array(
							'type'        => 'integer',
							'description' => __( 'Server-side conversation id; the wizard can refine the article afterwards.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						),
						'article'         => array(
							'type'       => 'object',
							'properties' => array(
								'title'      => array( 'type' => 'string' ),
								'meta'       => array(
									'type'       => 'object',
									'properties' => array(
										'title'       => array( 'type' => 'string' ),
										'description' => array( 'type' => 'string' ),
									),
								),
								'tagline'    => array( 'type' => 'string' ),
								'outline'    => array(
									'type'  => 'array',
									'items' => array( 'type' => 'string' ),
								),
								'intro'      => array( 'type' => 'string' ),
								'body_html'  => array( 'type' => 'string' ),
								'conclusion' => array( 'type' => 'string' ),
								'qna'        => array(
									'type'  => 'array',
									'items' => array(
										'type'       => 'object',
										'properties' => array(
											'question' => array( 'type' => 'string' ),
											'answer'   => array( 'type' => 'string' ),
										),
									),
								),
							),
							'required'   => array( 'title', 'body_html' ),
						),
					),
					'required'   => array( 'conversation_id', 'article' ),
				),
				'execute_callback'    => array( $this, 'execute_generate_article' ),
				'permission_callback' => $permission,
				'meta'                => $meta,
			)
		);

		call_user_func(
			'wp_register_ability',
			'ai-scribe/generate-titles',
			array(
				'label'               => __( 'Generate Article Titles', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'description'         => __( 'Suggests SEO article titles for an idea using the AI Scribe title prompt (wizard step 1).', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						$this->common_input_properties(),
						array(
							'idea' => array(
								'type'        => 'string',
								'description' => __( 'The article idea or topic to suggest titles for.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
							),
						)
					),
					'required'             => array( 'idea' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'conversation_id' => array( 'type' => 'integer' ),
						'titles'          => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'required'   => array( 'titles' ),
				),
				'execute_callback'    => array( $this, 'execute_generate_titles' ),
				'permission_callback' => $permission,
				'meta'                => $meta,
			)
		);

		call_user_func(
			'wp_register_ability',
			'ai-scribe/humanize-content',
			array(
				'label'               => __( 'Humanise Content', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'description'         => __( 'Rewrites content so it reads as naturally human, using AI Scribe\'s Humanise mode instructions.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						$this->common_input_properties(),
						array(
							'content' => array(
								'type'        => 'string',
								'description' => __( 'The HTML or plain-text content to humanise.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
							),
						)
					),
					'required'             => array( 'content' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'content' => array(
							'type'        => 'string',
							'description' => __( 'The humanised content.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						),
					),
					'required'   => array( 'content' ),
				),
				'execute_callback'    => array( $this, 'execute_humanize_content' ),
				'permission_callback' => $permission,
				'meta'                => $meta,
			)
		);

		call_user_func(
			'wp_register_ability',
			'ai-scribe/generate-seo-meta',
			array(
				'label'               => __( 'Generate SEO Meta', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'description'         => __( 'Generates an SEO meta title and description for article content, with the full content in context (wizard step 9).', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						$this->common_input_properties(),
						array(
							'title'    => array(
								'type'        => 'string',
								'description' => __( 'The article title.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
							),
							'content'  => array(
								'type'        => 'string',
								'description' => __( 'The article body (HTML or plain text) the meta should describe.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
							),
							'keywords' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'Optional focus keywords.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
							),
						)
					),
					'required'             => array( 'content' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'conversation_id' => array( 'type' => 'integer' ),
						'meta'            => array(
							'type'       => 'object',
							'properties' => array(
								'title'       => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
							),
							'required'   => array( 'title', 'description' ),
						),
					),
					'required'   => array( 'meta' ),
				),
				'execute_callback'    => array( $this, 'execute_generate_seo_meta' ),
				'permission_callback' => $permission,
				'meta'                => $meta,
			)
		);
	}

	// ------------------------------------------------------------------
	// Execute callbacks (public so the PHP test suite can drive them
	// directly with a stub container)
	// ------------------------------------------------------------------

	/**
	 * ai-scribe/generate-article — Express pipeline.
	 *
	 * @param array $input
	 * @return array|WP_Error
	 */
	public function execute_generate_article( $input ) {
		$input = is_array( $input ) ? $input : array();
		$idea  = isset( $input['idea'] ) ? trim( (string) $input['idea'] ) : '';
		if ( $idea === '' ) {
			return new WP_Error( 'invalid_params', __( 'An article idea is required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		$services = $this->services( array( 'conversation_service', 'generation_service' ) );
		if ( is_wp_error( $services ) ) {
			return $services;
		}

		$settings        = $this->settings_from_input( $input );
		$conversation_id = $services['conversation_service']->create( $settings, 'express' );
		$result          = $services['generation_service']->run_express( $conversation_id );

		if ( empty( $result['success'] ) ) {
			return $this->result_error( $result );
		}

		return array(
			'conversation_id' => (int) $conversation_id,
			'article'         => $result['article'],
		);
	}

	/**
	 * ai-scribe/generate-titles — wizard step 1.
	 *
	 * @param array $input
	 * @return array|WP_Error
	 */
	public function execute_generate_titles( $input ) {
		$input = is_array( $input ) ? $input : array();
		$idea  = isset( $input['idea'] ) ? trim( (string) $input['idea'] ) : '';
		if ( $idea === '' ) {
			return new WP_Error( 'invalid_params', __( 'An article idea is required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		$services = $this->services( array( 'conversation_service', 'generation_service' ) );
		if ( is_wp_error( $services ) ) {
			return $services;
		}

		$settings        = $this->settings_from_input( $input );
		$conversation_id = $services['conversation_service']->create( $settings, 'wizard' );
		$result          = $services['generation_service']->run_step( $conversation_id, 1 );

		if ( empty( $result['success'] ) ) {
			return $this->result_error( $result );
		}

		return array(
			'conversation_id' => (int) $conversation_id,
			'titles'          => isset( $result['parsed']['titles'] ) ? $result['parsed']['titles'] : array(),
		);
	}

	/**
	 * ai-scribe/humanize-content — Humanise mode rewrite.
	 *
	 * @param array $input
	 * @return array|WP_Error
	 */
	public function execute_humanize_content( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$content = isset( $input['content'] ) ? trim( (string) $input['content'] ) : '';
		if ( $content === '' ) {
			return new WP_Error( 'invalid_params', __( 'Content to humanise is required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		$services = $this->services( array( 'text_adapter', 'prompt_manager', 'config' ) );
		if ( is_wp_error( $services ) ) {
			return $services;
		}

		// Same shape as every other entry point: year first, persona, then
		// the hard rules last so spelling and punctuation are enforced here
		// too (P-3.2).
		$instructions = 'The year is ' . gmdate( 'Y' ) . ".\n\n"
			. $this->humanize_instructions( $services['prompt_manager'] ) . "\n\n"
			. AI_Scribe_Prompt_Manager::hard_rules();
		$model        = ! empty( $input['model'] ) ? (string) $input['model']
			: (string) $services['config']->get( 'ai_engine.model', 'gpt-4o-mini' );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $instructions,
			),
			array(
				'role'    => 'user',
				'content' => "Rewrite the following content according to your human-like writing instructions. Preserve the meaning, structure and any HTML markup. Return only the rewritten content.\n\n" . $content,
			),
		);

		$result = $services['text_adapter']->generate_text( $model, $messages, array( 'max_tokens' => 16000 ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$rewritten = isset( $result['content'] ) ? trim( (string) $result['content'] ) : '';
		if ( $rewritten === '' ) {
			return new WP_Error( 'empty_response', __( 'The model returned no content.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		return array( 'content' => $rewritten );
	}

	/**
	 * ai-scribe/generate-seo-meta — wizard step 9 with the content
	 * threaded into the conversation (never written blind).
	 *
	 * @param array $input
	 * @return array|WP_Error
	 */
	public function execute_generate_seo_meta( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$content = isset( $input['content'] ) ? trim( (string) $input['content'] ) : '';
		if ( $content === '' ) {
			return new WP_Error( 'invalid_params', __( 'Article content is required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		$services = $this->services( array( 'conversation_service', 'generation_service' ) );
		if ( is_wp_error( $services ) ) {
			return $services;
		}

		$title    = isset( $input['title'] ) ? trim( (string) $input['title'] ) : '';
		$settings = $this->settings_from_input( $input );
		if ( empty( $settings['idea'] ) ) {
			$settings['idea'] = ( $title !== '' ) ? $title : wp_html_excerpt( wp_strip_all_tags( $content ), 100 );
		}

		$conversations   = $services['conversation_service'];
		$conversation_id = $conversations->create( $settings, 'wizard' );

		if ( $title !== '' ) {
			$conversations->save_selection( $conversation_id, 'title', $title );
		}
		if ( ! empty( $input['keywords'] ) && is_array( $input['keywords'] ) ) {
			$conversations->save_selection( $conversation_id, 'keywords', array_map( 'strval', $input['keywords'] ) );
		}
		$conversations->save_selection( $conversation_id, 'body', $content );

		// Thread the body as step-6 history so the meta prompt sees the
		// full article (the 2.6.2 blind-writing flaw stays fixed here).
		$conversations->append_message( $conversation_id, 'user', 'Write the article body.', 6 );
		$conversations->append_message( $conversation_id, 'assistant', $content, 6 );

		$result = $services['generation_service']->run_step( $conversation_id, 9 );

		if ( empty( $result['success'] ) ) {
			return $this->result_error( $result );
		}

		return array(
			'conversation_id' => (int) $conversation_id,
			'meta'            => isset( $result['parsed']['meta'] ) ? $result['parsed']['meta'] : array(
				'title'       => '',
				'description' => '',
			),
		);
	}

	// ------------------------------------------------------------------
	// Internals
	// ------------------------------------------------------------------

	/**
	 * Input properties shared by every ability.
	 *
	 * @return array
	 */
	private function common_input_properties() {
		return array(
			'language'      => array(
				'type'        => 'string',
				'description' => __( 'Output language. Defaults to the saved AI Scribe setting.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			),
			'writing_style' => array(
				'type'        => 'string',
				'description' => __( 'Writing style, e.g. Business. Defaults to the saved setting.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			),
			'writing_tone'  => array(
				'type'        => 'string',
				'description' => __( 'Writing tone, e.g. Professional. Defaults to the saved setting.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			),
			'model'         => array(
				'type'        => 'string',
				'description' => __( 'Model id override. Defaults to the saved AI engine setting; "wordpress-ai" routes via the WordPress core AI client.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			),
		);
	}

	/**
	 * Conversation settings from ability input (missing values fall back
	 * to saved options inside ConversationService/PromptManager).
	 *
	 * @param array $input
	 * @return array
	 */
	private function settings_from_input( $input ) {
		$settings = array();
		foreach ( array( 'idea', 'language', 'writing_style', 'writing_tone', 'heading_tag', 'avoid_keywords', 'model' ) as $key ) {
			if ( isset( $input[ $key ] ) && $input[ $key ] !== '' ) {
				$settings[ $key ] = (string) $input[ $key ];
			}
		}
		if ( isset( $input['number_of_headings'] ) ) {
			$settings['number_of_headings'] = (int) $input['number_of_headings'];
		}
		return $settings;
	}

	/**
	 * The Humanise mode instructions from the prompt library, with a
	 * plain-English fallback when the template is missing.
	 *
	 * @param AI_Scribe_Prompt_Manager $prompt_manager
	 * @return string
	 */
	private function humanize_instructions( $prompt_manager ) {
		// Same resolution order as generation: JSON, then the seeded option,
		// then the PHP constant. The abilities API and the wizard must never
		// send different persona text.
		if ( is_object( $prompt_manager ) && method_exists( $prompt_manager, 'get_mode_instructions' ) ) {
			$instructions = (string) $prompt_manager->get_mode_instructions( 'humanize' );
			if ( '' !== trim( $instructions ) ) {
				return $instructions;
			}
		}
		return 'Rewrite the content so it reads as naturally human: vary sentence length, '
			. 'use plain concrete wording, avoid formulaic AI phrasing, and keep the original meaning intact.';
	}

	/**
	 * Resolve required services from the injected or global container.
	 *
	 * @param string[] $ids
	 * @return array|WP_Error id => service
	 */
	private function services( array $ids ) {
		$container = $this->container;
		if ( ! $container && function_exists( 'ai_scribe_get_container' ) ) {
			$container = ai_scribe_get_container();
		}
		if ( ! $container ) {
			return new WP_Error( 'not_configured', __( 'AI Scribe services are not available.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		$services = array();
		foreach ( $ids as $id ) {
			$resolved_id = ( $id === 'text_adapter' && ! $container->has( 'text_adapter' ) ) ? 'ai_core_adapter' : $id;
			if ( ! $container->has( $resolved_id ) ) {
				/* translators: %s: internal service identifier. */
				return new WP_Error( 'not_configured', sprintf( __( 'AI Scribe service "%s" is not available.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), $id ) );
			}
			$services[ $id ] = $container->get( $resolved_id );
		}
		return $services;
	}

	/**
	 * @param array $result Generation service error payload.
	 * @return WP_Error
	 */
	private function result_error( array $result ) {
		$code    = isset( $result['error']['code'] ) ? $result['error']['code'] : 'provider_error';
		$message = isset( $result['error']['message'] ) ? $result['error']['message'] : __( 'Generation failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' );
		return new WP_Error(
			$code,
			$message,
			array(
				'retryable' => ! empty( $result['error']['retryable'] ),
			)
		);
	}
}
