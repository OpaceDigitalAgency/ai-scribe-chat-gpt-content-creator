<?php
/**
 * Plugin Initializer for AI-Scribe Plugin
 *
 * Main bootstrap class that initializes all services, registers hooks,
 * and coordinates the plugin startup process.
 *
 * @package AI_Scribe
 * @subpackage Infrastructure
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Plugin_Initializer
 *
 * Handles the complete plugin initialization process including
 * service registration, dependency resolution, and hook registration.
 */
class AI_Scribe_Plugin_Initializer {
	/*
	 * PHPCS cannot follow the controller and service guards used by this
	 * compatibility class. Only the six endpoints registered in
	 * register_plugin_hooks() are reachable; each verifies a nonce and the
	 * appropriate capability before its service sanitises request values.
	 * The remaining handlers are retained only for migration compatibility.
	 */
	// phpcs:disable WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated

	/**
	 * Capability for the authoring screens (wizard, help). Kept identical to
	 * AI_Scribe_Conversation_Ajax_Controller::CAPABILITY so the menu and the
	 * endpoints it drives agree.
	 */
	const AUTHOR_CAPABILITY = 'edit_posts';

	/**
	 * Capability for configuration and management screens (settings, saved
	 * shortcodes), which expose provider API keys and destructive actions.
	 */
	const ADMIN_CAPABILITY = 'manage_options';

	/**
	 * State of the one-time "Best Of retired" notice: unset (never
	 * evaluated), 'none' (nothing to say), 'pending' (show it) or
	 * 'dismissed'. §15.1 — a parameter that stops having an effect must be
	 * announced, never dropped silently.
	 */
	const RETIRED_PARAMS_OPTION = 'ai_scribe_retired_params_notice';

	/**
	 * Query argument and nonce action for dismissing that notice.
	 */
	const RETIRED_PARAMS_DISMISS_ARG = 'ai_scribe_dismiss_retired_params';

	/**
	 * Service container instance
	 *
	 * @var AI_Scribe_Service_Container
	 */
	private $container;

	/**
	 * Dependency resolver instance
	 *
	 * @var AI_Scribe_Dependency_Resolver
	 */
	private $dependency_resolver;

	/**
	 * Logger instance
	 *
	 * @var AI_Scribe_Logger
	 */
	private $logger;

	/**
	 * Initialization status
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private $plugin_version;

	/**
	 * Plugin file path
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Initialization error
	 *
	 * @var Exception|null
	 */
	private $initialization_error = null;

	/**
	 * Constructor
	 *
	 * @param string $plugin_file Main plugin file path
	 * @param string $plugin_version Plugin version
	 */
	public function __construct( $plugin_file, $plugin_version = AI_SCRIBE_VER ) {
		$this->plugin_file    = $plugin_file;
		$this->plugin_version = $plugin_version;
		$this->initialize();
	}

	/**
	 * Initialize the plugin
	 *
	 * @return void
	 */
	private function initialize() {
		try {
			// Initialize service container
			$this->container = AI_Scribe_Service_Container::getInstance();

			// Register all core services
			$this->register_core_services();

			// Register AJAX handlers
			$this->register_ajax_handlers();

			// Initialize core services
			$this->container->initializeCoreServices();

			// Get logger for further initialization
			$this->logger = $this->container->get( 'logger' );

			// Get dependency resolver
			$this->dependency_resolver = $this->container->get( 'dependency_resolver' );

			// Resolve all services
			$this->resolve_all_services();

			// Register WordPress hooks
			$this->register_wordpress_hooks();

			// Register plugin hooks
			$this->register_plugin_hooks();

			$this->initialized = true;
			$this->logger->info(
				'AI-Scribe Plugin initialized successfully',
				array(
					'version'        => $this->plugin_version,
					'services_count' => count( $this->container->getRegisteredServices() ),
				)
			);

		} catch ( Exception $e ) {
			// Store the error for display
			$this->initialization_error = $e;

			// Fallback error logging if logger not available
			if ( $this->logger ) {
				$this->logger->critical(
					'Plugin initialization failed',
					array(
						'exception' => $e->getMessage(),
						'file'      => $e->getFile(),
						'line'      => $e->getLine(),
						'trace'     => $e->getTraceAsString(),
					)
				);
			} else {
				// 🚨 CENTRALIZED DEBUG CONTROL - Only log if debug mode is enabled
				if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
					ai_scribe_debug_log( 'AI-Scribe Plugin initialization failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
				}
			}

			// Add admin notice for initialization failure
			add_action( 'admin_notices', array( $this, 'show_initialization_error' ) );
		}
	}

	/**
	 * Register all core services in the container
	 *
	 * @return void
	 */
	private function register_core_services() {
		// Register Logger (no dependencies)
		$this->container->register(
			'logger',
			function () {
				return new AI_Scribe_Logger();
			}
		);

		// Register Config Manager (depends on logger)
		$this->container->register(
			'config',
			function ( $logger ) {
				return new AI_Scribe_Config_Manager( $logger );
			},
			array( 'logger' )
		);

		// Register Error Handler (depends on logger)
		$this->container->register(
			'error_handler',
			function ( $logger ) {
				return new AI_Scribe_Error_Handler( $logger );
			},
			array( 'logger' )
		);

		// Register WordPress Adapter (depends on logger)
		$this->container->register(
			'wordpress_adapter',
			function ( $logger ) {
				return new AI_Scribe_WordPress_Adapter( $logger );
			},
			array( 'logger' )
		);

		// Register Opace AI Hub Adapter (depends on logger and config)
		$this->container->register(
			'ai_core_adapter',
			function ( $logger, $config ) {
				return new AI_Scribe_AI_Core_Adapter( $logger, $config );
			},
			array( 'logger', 'config' )
		);

		// v3 P4: WordPress core AI Client adapter (WP 7.0+; constructor is
		// guard-safe on older cores — see class-wp-ai-client-adapter.php)
		$this->container->register(
			'wp_ai_client_adapter',
			function ( $logger, $config ) {
				return new AI_Scribe_WP_AI_Client_Adapter( $logger, $config );
			},
			array( 'logger', 'config' )
		);

		// v3 P4: text_adapter — the adapter generation actually uses.
		// Routes through the WP core AI client when the user selected
		// "WordPress AI (core)" AND the core client is present; otherwise
		// the direct Opace AI Hub provider path.
		$this->container->register(
			'text_adapter',
			function ( $logger, $config ) {
				$model    = $config->get( 'ai_engine.model', '' );
				$model    = is_string( $model ) ? $model : '';
				$provider = $config->get( 'ai_engine.provider', '' );
				$provider = is_string( $provider ) ? $provider : '';
				if ( AI_Scribe_WP_AI_Client_Adapter::is_selected( $model, $provider )
				&& AI_Scribe_WP_AI_Client_Adapter::is_available() ) {
					return $this->container->get( 'wp_ai_client_adapter' );
				}
				return $this->container->get( 'ai_core_adapter' );
			},
			array( 'logger', 'config' )
		);

		// Register Prompt Manager (depends on logger and config)
		$this->container->register(
			'prompt_manager',
			function ( $logger, $config ) {
				return new AI_Scribe_Prompt_Manager( $logger, $config );
			},
			array( 'logger', 'config' )
		);

		// Register Dependency Resolver (depends on logger and container)
		$this->container->register(
			'dependency_resolver',
			function ( $logger ) {
				return new AI_Scribe_Dependency_Resolver( $logger, $this->container );
			},
			array( 'logger' )
		);

		// Register Security Service (depends on logger, config, and wordpress_adapter)
		$this->container->register(
			'security_service',
			function ( $logger, $config, $wordpress_adapter ) {
				return new AI_Scribe_Security_Service( $logger, $config, $wordpress_adapter );
			},
			array( 'logger', 'config', 'wordpress_adapter' )
		);

		// Register Service Factory (depends on logger and config)
		$this->container->register(
			'service_factory',
			function ( $logger, $config ) {
				return new AI_Scribe_Service_Factory( $logger, $config );
			},
			array( 'logger', 'config' )
		);

		// 🚨 ARCHITECTURAL FIX: Use V4 Content Generation Service instead of legacy V3 Content Service
		$this->container->register(
			'content_service',
			function ( $logger, $ai_core_adapter, $config, $prompt_manager, $security_service ) {
				return new AI_Scribe_Content_Generation_Service( $logger, $ai_core_adapter, $config, $prompt_manager, $security_service );
			},
			array( 'logger', 'ai_core_adapter', 'config', 'prompt_manager', 'security_service' )
		);

		// Register Image HTML Service (lightweight service with minimal dependencies)
		$this->container->register(
			'image_html_service',
			function ( $logger, $config ) {
				return new AI_Scribe_Image_HTML_Service( $logger, $config );
			},
			array( 'logger', 'config' )
		);

		// Register Image Service (depends on multiple services including ImageHTMLService)
		$this->container->register(
			'image_service',
			function ( $logger, $ai_core_adapter, $config, $prompt_manager, $security_service, $image_html_service ) {
				return new AI_Scribe_Image_Service( $logger, $ai_core_adapter, $config, $prompt_manager, $security_service, $image_html_service );
			},
			array( 'logger', 'ai_core_adapter', 'config', 'prompt_manager', 'security_service', 'image_html_service' )
		);

		// Register Engine Service (depends on multiple services)
		$this->container->register(
			'engine_service',
			function ( $logger, $ai_core_adapter, $config, $prompt_manager, $security_service ) {
				return new AI_Scribe_Engine_Service( $logger, $ai_core_adapter, $config, $prompt_manager, $security_service );
			},
			array( 'logger', 'ai_core_adapter', 'config', 'prompt_manager', 'security_service' )
		);

		// v3: Pricing Service DISCARDED (duplicates Opace AI Hub Pricing/Stats — see REFACTOR.md section 7).
		// Cost estimation will be wired to Opace AI Hub's Pricing classes in P2.
		// $this->container->register('pricing_service', function($logger, $config, $wordpress_adapter) {
		//     return new AI_Scribe_Pricing_Service($logger, $config, $wordpress_adapter);
		// }, ['logger', 'config', 'wordpress_adapter']);

		// Register Template Service (depends on logger, config, and wordpress_adapter)
		$this->container->register(
			'template_service',
			function ( $logger, $config, $wordpress_adapter ) {
				return new AI_Scribe_Template_Service( $logger, $config, $wordpress_adapter );
			},
			array( 'logger', 'config', 'wordpress_adapter' )
		);

		// Register Post Service (depends on logger, config, wordpress_adapter, and security_service)
		$this->container->register(
			'post_service',
			function ( $logger, $config, $wordpress_adapter, $security_service ) {
				return new AI_Scribe_Post_Service( $logger, $config, $wordpress_adapter, $security_service );
			},
			array( 'logger', 'config', 'wordpress_adapter', 'security_service' )
		);

		// Register Shortcode Service (depends on logger, config, wordpress_adapter, and security_service)
		$this->container->register(
			'shortcode_service',
			function ( $logger, $config, $wordpress_adapter, $security_service ) {
				return new AI_Scribe_Shortcode_Service( $logger, $config, $wordpress_adapter, $security_service );
			},
			array( 'logger', 'config', 'wordpress_adapter', 'security_service' )
		);

		// Register Utility Service (depends on logger and config)
		$this->container->register(
			'utility_service',
			function ( $logger, $config ) {
				return new AI_Scribe_Utility_Service( $logger, $config );
			},
			array( 'logger', 'config' )
		);

		// Register Admin Service (depends on logger, config, and wordpress_adapter)
		$this->container->register(
			'admin_service',
			function ( $logger, $config, $wordpress_adapter ) {
				return new AI_Scribe_Admin_Service( $logger, $config, $wordpress_adapter );
			},
			array( 'logger', 'config', 'wordpress_adapter' )
		);

		// v3 P2: Conversation-threaded generation engine (REFACTOR.md section 5)
		$this->container->register(
			'conversation_service',
			function ( $logger ) {
				return new AI_Scribe_Conversation_Service( $logger );
			},
			array( 'logger' )
		);

		$this->container->register(
			'cost_estimator',
			function ( $logger ) {
				return new AI_Scribe_Cost_Estimator( $logger );
			},
			array( 'logger' )
		);

		// v3 P4: generation now takes text_adapter (WP core AI client when
		// selected+available, direct Opace AI Hub provider path otherwise).
		$this->container->register(
			'generation_service',
			function ( $logger, $config, $text_adapter, $prompt_manager, $conversation_service, $cost_estimator ) {
				return new AI_Scribe_Generation_Service( $logger, $config, $text_adapter, $prompt_manager, $conversation_service, $cost_estimator );
			},
			array( 'logger', 'config', 'text_adapter', 'prompt_manager', 'conversation_service', 'cost_estimator' )
		);

		// v3 P4: Abilities API registrar (WP 6.9+ Abilities API / MCP
		// discovery; hooks are no-ops on older cores)
		$this->container->register(
			'abilities_registrar',
			function () {
				return new AI_Scribe_Abilities_Registrar( $this->container );
			}
		);

		// 🚨 ARCHITECTURAL CRISIS FIX: WorkflowService DISABLED - Redundant layer
		// WorkflowService was calling ContentService directly, creating unnecessary complexity
		// AJAX flow: PluginInitializer → ContentService (direct, no workflow layer needed)
		// WorkflowService moved to /disabled/ for potential future use

		// DISABLED: Workflow Service registration
		// $this->container->register('workflow_service', function($logger, $config, $content_service, $image_service, $security_service, $image_html_service) {
		//     return new AI_Scribe_Workflow_Service($logger, $config, $content_service, $image_service, $security_service, $image_html_service);
		// }, ['logger', 'config', 'content_service', 'image_service', 'security_service', 'image_html_service']);
	}

	/**
	 * Resolve all services using dependency resolver
	 *
	 * @return void
	 */
	private function resolve_all_services() {
		$resolution_results = $this->dependency_resolver->resolve_all_services();

		foreach ( $resolution_results as $service_id => $result ) {
			if ( ! $result['success'] ) {
				throw new RuntimeException( esc_html( "Failed to resolve service: {$service_id} - " . $result['error'] ) );
			}
		}
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	private function register_wordpress_hooks() {
		$wordpress_adapter = $this->container->get( 'wordpress_adapter' );

		// Plugin lifecycle hooks
		register_activation_hook( $this->plugin_file, array( $this, 'activate_plugin' ) );
		register_deactivation_hook( $this->plugin_file, array( $this, 'deactivate_plugin' ) );

		// WordPress initialization hooks
		$wordpress_adapter->add_hook( 'init', array( $this, 'on_wordpress_init' ) );
		$wordpress_adapter->add_hook( 'admin_init', array( $this, 'on_admin_init' ) );
		$wordpress_adapter->add_hook( 'admin_menu', array( $this, 'register_admin_menu' ) );
		$wordpress_adapter->add_hook( 'admin_notices', array( $this, 'render_retired_params_notice' ) );
		// Asset enqueuing: AdminService registers its own admin_enqueue_scripts
		// hooks in its constructor — no duplicate delegation here.

		// Plugin action links
		$wordpress_adapter->add_hook( 'plugin_action_links_' . plugin_basename( $this->plugin_file ), array( $this, 'add_plugin_action_links' ) );

		// Shortcode registration
		$wordpress_adapter->register_shortcode( 'article_builder_generate_data', array( $this, 'handle_shortcode' ) );

		// v3 P4: Abilities API registration (WP 6.9+ wp_abilities_api_init /
		// wp_abilities_api_categories_init; the hooks never fire on older cores)
		$this->container->get( 'abilities_registrar' )->register_hooks();
	}

	/**
	 * Register plugin-specific hooks
	 *
	 * @return void
	 */
	private function register_plugin_hooks() {
		$wordpress_adapter = $this->container->get( 'wordpress_adapter' );

		// P8 §14.2/§14.5 attack-surface reduction: only endpoints the current
		// UI actually calls are registered. The v2.6-era endpoints (legacy
		// engine-settings saves that accepted plaintext keys, phantom-data
		// cleanup, nonce refresh, article retrieval, prompt-template CRUD,
		// etc.) had ZERO frontend consumers after the P7 zombie purge and
		// were unregistered here; their handler methods remain for unit
		// coverage but are no longer reachable over admin-ajax.

		// Settings save handlers still used by the settings screen
		$wordpress_adapter->register_ajax_action( 'ai_scribe_save_content_settings', array( $this, 'handle_save_content_settings' ) );
		$wordpress_adapter->register_ajax_action( 'ai_scribe_save_prompt_settings', array( $this, 'handle_save_prompt_settings' ) );

		// Template and shortcode management (shortcodes screen)
		$wordpress_adapter->register_ajax_action( 'al_scribe_remove_short_code_content', array( $this, 'handle_shortcode_removal' ) );
		$wordpress_adapter->register_ajax_action( 'al_scribe_send_shortcode_page', array( $this, 'handle_shortcode_page' ) );

		// Image generation (wizard image step)
		$wordpress_adapter->register_ajax_action( 'ai_scribe_generate_image', array( $this, 'handle_image_generation' ) );

		// Bulk "one image per section" (wizard image step, L-17/L-18):
		// sections are iterated server-side with a distinct prompt per
		// heading; pass `index` per request for real progress reporting.
		$wordpress_adapter->register_ajax_action( 'ai_scribe_generate_section_images', array( $this, 'handle_section_image_generation' ) );
	}

	/**
	 * WordPress init hook handler
	 *
	 * @return void
	 */
	public function on_wordpress_init() {
		// Translations load automatically from wp.org language packs (WP 4.6+);
		// no manual load_plugin_textdomain() needed now domain = slug.
		$this->logger->debug( 'WordPress init completed' );
	}

	/**
	 * Admin init hook handler
	 *
	 * @return void
	 */
	public function on_admin_init() {
		$this->handle_retired_params_dismissal();
		$this->maybe_flag_retired_params();

		// Perform admin-specific initialization
		$this->logger->debug( 'Admin init completed' );
	}

	/**
	 * Decide once whether this site has anything to be told about Best Of.
	 *
	 * `n` (Best Of) was a 2.6.2 generation parameter that v3 retires: it is
	 * still preserved in storage but no longer reaches any provider, and it
	 * has no control on the settings screen. A site that left it at the
	 * default of 1 lost nothing and is told nothing; a site that tuned it is
	 * shown the notice below exactly once.
	 *
	 * @return void
	 */
	private function maybe_flag_retired_params() {
		if ( false !== get_option( self::RETIRED_PARAMS_OPTION, false ) ) {
			return;
		}

		$engine = get_option( 'ab_gpt_ai_engine_settings', array() );
		$engine = is_array( $engine ) ? $engine : array();

		$values = array(
			isset( $engine['n'] ) ? $engine['n'] : null,
			get_option( 'ab_n', null ),
		);

		$tuned = false;
		foreach ( $values as $value ) {
			if ( null !== $value && '' !== $value && (int) $value > 1 ) {
				$tuned = true;
				break;
			}
		}

		update_option( self::RETIRED_PARAMS_OPTION, $tuned ? 'pending' : 'none', false );
	}

	/**
	 * Persist a dismissal of the Best Of notice. Nonce and capability gated,
	 * exactly as the settings writes are.
	 *
	 * @return void
	 */
	private function handle_retired_params_dismissal() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is verified on the next line.
		if ( empty( $_GET[ self::RETIRED_PARAMS_DISMISS_ARG ] ) ) {
			return;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::RETIRED_PARAMS_DISMISS_ARG ) || ! current_user_can( self::ADMIN_CAPABILITY ) ) {
			return;
		}
		update_option( self::RETIRED_PARAMS_OPTION, 'dismissed', false );
	}

	/**
	 * The §15.1 retirement notice for Best Of.
	 *
	 * @return void
	 */
	public function render_retired_params_notice() {
		if ( 'pending' !== get_option( self::RETIRED_PARAMS_OPTION ) || ! current_user_can( self::ADMIN_CAPABILITY ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			add_query_arg( self::RETIRED_PARAMS_DISMISS_ARG, '1' ),
			self::RETIRED_PARAMS_DISMISS_ARG
		);
		?>
		<div class="notice notice-warning ai-scribe-notice ai-scribe-retired-params-notice"
			id="ai-scribe-retired-params-notice"
			data-testid="ai-scribe-retired-params-notice">
			<p>
				<strong><?php esc_html_e( 'AI-Scribe: Best Of has been retired.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></strong>
				<?php esc_html_e( 'The Best Of setting (n) asked the provider for several completions and kept one. Current models do not support it, so it is no longer sent and the control has gone from the settings screen. Your saved value is untouched and nothing else about your generations changes. Frequency Penalty and Presence Penalty are still available, under Model parameters, for the models that accept them.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( $dismiss_url ); ?>" data-testid="ai-scribe-retired-params-dismiss">
					<?php esc_html_e( 'Got it', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Register admin menu
	 *
	 * Two capabilities, split by what the screen actually does (D-07):
	 *
	 * - Authoring screens use `edit_posts`, matching the capability the
	 *   generation endpoints already enforce (Conversation_Ajax_Controller::
	 *   CAPABILITY and the abilities registrar permission callback). Writing an
	 *   article is an author's job, and until now an Editor could drive every
	 *   generation endpoint while having no screen to drive it from.
	 * - Configuration and management screens stay on `manage_options`. Settings
	 *   holds provider API keys, and the shortcodes screen's only action is a
	 *   deletion that is itself `manage_options`-gated.
	 *
	 * No endpoint capability is relaxed here; the menu is being brought up to
	 * the endpoints, not the other way round.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		$wordpress_adapter = $this->container->get( 'wordpress_adapter' );

		// Main menu page
		$wordpress_adapter->add_admin_menu(
			array(
				'page_title' => 'AI Scribe - Content Generator',
				'menu_title' => 'AI Scribe',
				'capability' => self::AUTHOR_CAPABILITY,
				'menu_slug'  => 'ai-scribe',
				'callback'   => array( $this, 'render_main_page' ),
				'icon_url'   => AI_SCRIBE_URL . 'assets/images/ai-scribe-menu-icon-20x20.png',
				'position'   => 30,
			)
		);

		// Submenu pages
		$wordpress_adapter->add_admin_submenu(
			array(
				'parent_slug' => 'ai-scribe',
				'page_title'  => 'Generate Article',
				'menu_title'  => 'Generate Article',
				'capability'  => self::AUTHOR_CAPABILITY,
				'menu_slug'   => 'ai_scribe_generate_article',
				'callback'    => array( $this, 'render_generate_page' ),
			)
		);

		$wordpress_adapter->add_admin_submenu(
			array(
				'parent_slug' => 'ai-scribe',
				'page_title'  => 'Saved Shortcodes',
				'menu_title'  => 'Saved Shortcodes',
				'capability'  => self::ADMIN_CAPABILITY,
				'menu_slug'   => 'ai_scribe_saved_shortcodes',
				'callback'    => array( $this, 'render_shortcodes_page' ),
			)
		);

		$wordpress_adapter->add_admin_submenu(
			array(
				'parent_slug' => 'ai-scribe',
				'page_title'  => 'Settings',
				'menu_title'  => 'Settings',
				'capability'  => self::ADMIN_CAPABILITY,
				'menu_slug'   => 'ai_scribe_settings',
				'callback'    => array( $this, 'render_settings_page' ),
			)
		);

		$wordpress_adapter->add_admin_submenu(
			array(
				'parent_slug' => 'ai-scribe',
				'page_title'  => 'Help',
				'menu_title'  => 'Help',
				'capability'  => self::AUTHOR_CAPABILITY,
				'menu_slug'   => 'ai_scribe_help',
				'callback'    => array( $this, 'render_help_page' ),
			)
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook_suffix Current admin page hook suffix
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		// Only enqueue on AI Scribe pages
		if ( strpos( $hook_suffix, 'ai-scribe' ) === false ) {
			return;
		}

		// Delegate to admin service for proper script and style enqueuing
		try {
			$admin_service = $this->container->get( 'admin_service' );
			// 🚨 CRITICAL FIX: Use the correct method names that actually exist
			$admin_service->admin_enqueue_styles( $hook_suffix );
			$admin_service->admin_enqueue_scripts( $hook_suffix );
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to enqueue admin assets',
				array(
					'hook_suffix' => $hook_suffix,
					'exception'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Plugin activation handler
	 *
	 * @return void
	 */
	public function activate_plugin() {
		try {
			// Activation also runs after a delete/reinstall. Fill only missing
			// keys: replacing either group here discards choices the uninstall
			// path deliberately retained.
			$this->seed_activation_defaults();

			// Install correct prompts with [Title] instead of [Idea]
			$this->install_default_prompts();

			// Create database table
			$utility_service = $this->container->get( 'utility_service' );
			$utility_service->activate();

			// Do not run the legacy phantom-key cleanup here. Opace AI Hub owns API
			// keys in v3, so an empty pair of legacy key options is normal. The
			// old routine interpreted that as a fresh install and erased the
			// saved model plus all per-model parameters on every reinstall.

			$this->logger->info( 'Plugin activated successfully' );

		} catch ( Exception $e ) {
			$this->logger->error( 'Plugin activation failed', array( 'exception' => $e->getMessage() ) );
		}
	}

	/** Fill activation defaults without replacing retained user settings. */
	private function seed_activation_defaults() {
		$this->seed_option_defaults(
			'ab_gpt_content_settings',
			array(
				'language'          => 'English',
				'writing_style'     => 'Business',
				'writing_tone'      => 'Professional',
				'number_of_heading' => '5',
				'Heading_tag'       => 'H2',
			)
		);

		$this->seed_option_defaults(
			'ab_gpt_ai_engine_settings',
			array(
				'model'            => '',
				'temp'             => 0.5,
				'top_p'            => 0.5,
				'freq_pent'        => 0.2,
				'Presence_penalty' => 0.2,
				'n'                => 1,
			)
		);
	}

	/**
	 * Install default prompts with correct [Title] placeholders
	 *
	 * @return void
	 */
	private function install_default_prompts() {
		// Load prompts from JSON file during activation
		$json_file = AI_SCRIBE_DIR . 'includes/prompts/complete-prompts.json';

		if ( ! file_exists( $json_file ) ) {
			$this->logger->error( 'Default prompts JSON file not found: ' . $json_file );
			return;
		}

		$json_content = file_get_contents( $json_file );
		if ( $json_content === false ) {
			$this->logger->error( 'Failed to read default prompts JSON file: ' . $json_file );
			return;
		}

		$json_data = json_decode( $json_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->logger->error( 'Failed to parse default prompts JSON: ' . json_last_error_msg() );
			return;
		}

		// Install ALL sections from JSON file
		$this->install_content_prompts( $json_data );
		$this->install_default_settings( $json_data );
		$this->install_system_messages( $json_data );
		$this->install_image_prompts( $json_data );
		$this->install_mode_instructions( $json_data );

		$this->logger->info( 'Complete JSON configuration installed during activation' );
	}

	/**
	 * Map JSON prompt keys to database keys
	 */
	private function map_json_key_to_db_key( $json_key ) {
		$mapping = array(
			'instructions' => 'instructions_prompts',
			'title'        => 'title_prompts',
			'keywords'     => 'Keywords_prompts',
			'outline'      => 'outline_prompts',
			'introduction' => 'intro_prompts',
			'tagline'      => 'tagline_prompts',
			'article'      => 'article_prompts',
			'conclusion'   => 'conclusion_prompts',
			'qa'           => 'qa_prompts',
			'meta'         => 'meta_prompts',
			'revision'     => 'review_prompts',  // JSON has 'revision' but form expects 'review_prompts'
			'evaluate'     => 'evaluate_prompts',
		);

		return isset( $mapping[ $json_key ] ) ? $mapping[ $json_key ] : $json_key . '_prompts';
	}

	/**
	 * Plugin deactivation handler
	 *
	 * @return void
	 */
	public function deactivate_plugin() {
		$this->logger->info( 'Plugin deactivated' );
	}

	/**
	 * Show initialization error notice
/**
	 * Install content prompts from JSON
	 */
	private function install_content_prompts( $json_data ) {
		if ( ! isset( $json_data['content_prompts'] ) ) {
			return;
		}

		$default_prompts = array();
		foreach ( $json_data['content_prompts'] as $key => $prompt_data ) {
			if ( isset( $prompt_data['template'] ) ) {
				// Map JSON keys to database keys
				$db_key = $this->map_json_key_to_db_key( $key );

				// Special handling for instructions - store template cleanly, excluded words separately
				if ( $key === 'instructions' && isset( $prompt_data['words_to_exclude'] ) && is_array( $prompt_data['words_to_exclude'] ) ) {
					// Store clean template without excluded words
					$default_prompts[ $db_key ] = $prompt_data['template'];

					// Store excluded words separately
					$excluded_words                    = implode( ', ', $prompt_data['words_to_exclude'] );
					$default_prompts['excluded_words'] = $excluded_words;

					// Also store the excluded words separately for the settings UI
					$default_prompts['excluded_words'] = implode( ', ', $prompt_data['words_to_exclude'] );
				} else {
					$default_prompts[ $db_key ] = $prompt_data['template'];
				}
			}
		}

		if ( ! empty( $default_prompts ) ) {
			$this->seed_option_defaults( 'ab_prompts_content', $default_prompts );
			wp_cache_delete( 'ab_prompts_content', 'options' );
			$this->logger->info( 'Content prompts installed: ' . count( $default_prompts ) . ' prompts' );
		}
	}

	/**
	 * Write default keys into an option without touching anything already
	 * stored.
	 *
	 * Activation runs on every reactivation and on some update paths, so a
	 * plain update_option() here threw away the user's edited prompts and
	 * their chosen writing mode. Defaults now only fill gaps: a fresh
	 * install gets the whole set, an existing site keeps every choice it has
	 * already made.
	 *
	 * @param string $option   Option name.
	 * @param array  $defaults Default key => value pairs.
	 * @return void
	 */
	private function seed_option_defaults( $option, array $defaults ) {
		$stored = get_option( $option, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$merged = $stored;
		foreach ( $defaults as $key => $value ) {
			if ( ! array_key_exists( $key, $stored ) ) {
				$merged[ $key ] = $value;
			}
		}

		if ( $merged !== $stored ) {
			update_option( $option, $merged );
		}
	}

	/**
	 * Install default settings from JSON
	 */
	private function install_default_settings( $json_data ) {
		if ( ! isset( $json_data['default_settings'] ) ) {
			return;
		}

		$settings = $json_data['default_settings'];

		// Install content settings
		if ( isset( $settings['content'] ) ) {
			$this->seed_option_defaults( 'ab_gpt_content_settings', $settings['content'] );
			$this->logger->info( 'Default content settings installed' );
		}

		// Install engine settings
		if ( isset( $settings['engine'] ) ) {
			$this->seed_option_defaults( 'ab_gpt_ai_engine_settings', $settings['engine'] );
			$this->logger->info( 'Default engine settings installed' );
		}

		// Install image settings
		if ( isset( $settings['image'] ) ) {
			$this->seed_option_defaults( 'ab_gpt_image_settings', $settings['image'] );
			$this->logger->info( 'Default image settings installed' );
		}
	}

	/**
	 * Install system messages from JSON
	 */
	private function install_system_messages( $json_data ) {
		if ( ! isset( $json_data['system_messages'] ) ) {
			return;
		}

		update_option( 'ab_system_messages', $json_data['system_messages'] );
		$this->logger->info( 'System messages installed' );
	}

	/**
	 * Install image prompts from JSON
	 */
	private function install_image_prompts( $json_data ) {
		if ( ! isset( $json_data['image_prompts'] ) ) {
			return;
		}

		update_option( 'ab_image_prompts', $json_data['image_prompts'] );
		$this->logger->info( 'Image prompts installed' );
	}

	/**
	 * Install mode-specific instructions from JSON
	 */
	private function install_mode_instructions( $json_data ) {
		if ( ! isset( $json_data['mode_specific_instructions'] ) ) {
			return;
		}

		update_option( 'ab_mode_instructions', $json_data['mode_specific_instructions'] );
		$this->logger->info( 'Mode-specific instructions installed' );
	}

	/**
	 * Show initialization error
	 *
	 * @return void
	 */
	public function show_initialization_error() {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>' . esc_html__( 'AI Scribe:', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '</strong> ' . esc_html__( 'Plugin initialization failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '<br>';

		if ( $this->initialization_error ) {
			echo '<strong>' . esc_html__( 'Error:', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '</strong> ' . esc_html( $this->initialization_error->getMessage() ) . '<br>';
			echo '<strong>' . esc_html__( 'File:', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '</strong> ' . esc_html( $this->initialization_error->getFile() ) . '<br>';
			echo '<strong>' . esc_html__( 'Line:', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '</strong> ' . esc_html( $this->initialization_error->getLine() ) . '<br>';

			// Show first few lines of stack trace for debugging
			$trace_lines = explode( "\n", $this->initialization_error->getTraceAsString() );
			if ( count( $trace_lines ) > 0 ) {
				echo '<strong>' . esc_html__( 'Stack trace (first 3 lines):', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '</strong><br>';
				for ( $i = 0; $i < min( 3, count( $trace_lines ) ); $i++ ) {
					echo '<code>' . esc_html( $trace_lines[ $i ] ) . '</code><br>';
				}
			}
		} else {
			esc_html_e( 'Please check the error logs for details.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' );
		}

		echo '</p></div>';
	}

	/**
	 * Render main admin page
	 *
	 * @return void
	 */
	public function render_main_page() {
		include AI_SCRIBE_DIR . 'templates/create_template.php';
		AI_Scribe_Opace_Footer::render();
	}

	/**
	 * Render generate article page
	 *
	 * @return void
	 */
	public function render_generate_page() {
		include AI_SCRIBE_DIR . 'templates/create_template.php';
		AI_Scribe_Opace_Footer::render();
	}

	/**
	 * Render saved shortcodes page
	 *
	 * @return void
	 */
	public function render_shortcodes_page() {
		include AI_SCRIBE_DIR . 'templates/show_template.php';
		AI_Scribe_Opace_Footer::render();
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render_settings_page() {
		include AI_SCRIBE_DIR . 'templates/settings_template.php';
		AI_Scribe_Opace_Footer::render();
	}

	/**
	 * Render help page
	 *
	 * @return void
	 */
	public function render_help_page() {
		include AI_SCRIBE_DIR . 'templates/help_template.php';
		AI_Scribe_Opace_Footer::render();
	}

	/**
	 * Handle shortcode rendering
	 *
	 * @param array $atts Shortcode attributes
	 * @return string Shortcode output
	 */
	public function handle_shortcode( $atts ) {
		// Frontend [article_builder_generate_data template_id="N"] rendering —
		// delegates to ShortcodeService (P5.6: this was a stub returning '',
		// which silently blanked every embedded shortcode).
		try {
			$shortcode_service = $this->container->get( 'shortcode_service' );
			return $shortcode_service->send_shortcode_page_data( is_array( $atts ) ? $atts : array() );
		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error( 'Shortcode rendering failed', array( 'exception' => $e->getMessage() ) );
			}
			return '';
		}
	}

	/**
	 * AJAX handler for content generation
	 * Delegates to Engine Service
	 */
	public function handle_content_generation() {
		try {
			$engine_service = $this->container->get( 'engine_service' );
			$engine_service->handle_engine_request();
		} catch ( Exception $e ) {
			$this->logger->error( 'Content generation failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => __( 'Content generation failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
		}
	}

	/**
	 * AJAX handler for content suggestion
	 * Delegates to Content Service
	 */
	public function handle_content_suggestion() {
		// 🚨 CRITICAL DEBUG: Add comprehensive error tracking to identify 500 error cause
		ai_scribe_debug_log( '🚀 AI-SCRIBE CRITICAL DEBUG: handle_content_suggestion() method called - starting investigation' );

		try {
			// Step 1: Test basic PHP execution
			ai_scribe_debug_log( '🔧 STEP 1: Basic PHP execution - OK' );

			// Step 2: Test container availability
			if ( ! isset( $this->container ) ) {
				ai_scribe_debug_log( '❌ STEP 2: Container not available - FATAL' );
				throw new Exception( esc_html( 'Service container not available' ) );
			}
			ai_scribe_debug_log( '🔧 STEP 2: Container available - OK' );

			// 🔧 CRITICAL FIX: Use Content Service directly instead of Workflow Service
			// The Workflow Service creates different UI structure (li elements with Select buttons)
			// But the working backup uses Content Service which creates checkbox structure
			try {
				$content_service = $this->container->get( 'content_service' );
				ai_scribe_debug_log( '🔧 STEP 3: Content service retrieved - OK' );
			} catch ( Exception $e ) {
				ai_scribe_debug_log( '❌ STEP 3: Content service resolution failed - ' . $e->getMessage() );
				throw new Exception( esc_html( 'Failed to resolve content service: ' . $e->getMessage() ) );
			}

			// Step 4: Test content service object
			if ( ! is_object( $content_service ) ) {
				ai_scribe_debug_log( '❌ STEP 4: Content service is not an object - FATAL' );
				throw new Exception( esc_html( 'Content service is not a valid object' ) );
			}
			ai_scribe_debug_log( '🔧 STEP 4: Content service is valid object - OK' );

			// Step 5: Test method existence
			if ( ! method_exists( $content_service, 'suggest_content' ) ) {
				ai_scribe_debug_log( '❌ STEP 5: suggest_content method does not exist - FATAL' );
				throw new Exception( esc_html( 'suggest_content method not found on content service' ) );
			}
			ai_scribe_debug_log( '🔧 STEP 5: suggest_content method exists - OK' );

			// Step 6: Test nonce before calling content service
			$nonce = $_POST['security'] ?? '';
			if ( empty( $nonce ) ) {
				ai_scribe_debug_log( '❌ STEP 6: No nonce provided - FATAL' );
				wp_send_json_error( array( 'message' => __( 'A security nonce is required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}
			ai_scribe_debug_log( '🔧 STEP 6: Nonce provided - OK' );

			// Step 7: Convert V4 frontend format to V3 backend format before calling content service
			ai_scribe_debug_log( '🔧 STEP 7: Converting V4 frontend parameters to V3 backend format' );

			// Get V4 parameters
			$content_type = sanitize_text_field( wp_unslash( $_POST['content_type'] ?? 'titles' ) );
			$prompt       = wp_kses_post( wp_unslash( $_POST['prompt'] ?? '' ) );
			$topic        = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );

			// Map content types to action inputs (V3 format)
			$action_input_map = array(
				'titles'       => 'title',
				'keywords'     => 'keyword',
				'outline'      => 'outline',
				'introduction' => 'introduction',
				'tagline'      => 'tagline',
				'article_body' => 'article',
				'conclusion'   => 'conclusion',
				'qa_section'   => 'evaluate',
				'meta_data'    => 'seo-meta-data',  // 🚨 CRITICAL FIX: Use 'seo-meta-data' for proper V3 backend processing
			);

			$action_input = isset( $action_input_map[ $content_type ] ) ? $action_input_map[ $content_type ] : 'title';

			// Set up $_POST data in V3 format that suggest_content() expects
			$_POST['actionInput']       = $action_input;
			$_POST['autogenerateValue'] = $prompt; // Use the processed prompt from V4
			$_POST['idea']              = $topic;
			$_POST['title']             = isset( $_POST['title'] ) ? $_POST['title'] : '';
			$_POST['keyword']           = isset( $_POST['keywords'] ) ? $_POST['keywords'] : '';

			ai_scribe_debug_log( '🔧 STEP 7: Parameter conversion complete - actionInput: ' . $action_input . ', prompt length: ' . strlen( $prompt ) );
			ai_scribe_debug_log( '🔧 STEP 7: About to call content_service->suggest_content()' );
			$content_service->suggest_content();
			ai_scribe_debug_log( '🔧 STEP 7: content_service->suggest_content() completed successfully' );

		} catch ( Exception $e ) {
			// Comprehensive error logging
			ai_scribe_debug_log( '❌ AI-SCRIBE CRITICAL ERROR: Exception in handle_content_suggestion' );
			ai_scribe_debug_log( '❌ Exception Message: ' . $e->getMessage() );
			ai_scribe_debug_log( '❌ Exception File: ' . $e->getFile() );
			ai_scribe_debug_log( '❌ Exception Line: ' . $e->getLine() );
			ai_scribe_debug_log( '❌ Exception Trace: ' . $e->getTraceAsString() );

			// Log additional context
			ai_scribe_debug_log( '❌ POST field names: ' . implode( ', ', array_map( 'sanitize_key', array_keys( $_POST ) ) ) );
			ai_scribe_debug_log( '❌ Container Status: ' . ( isset( $this->container ) ? 'Available' : 'Not Available' ) );

			// Send proper error response
			wp_send_json_error(
				array(
					'message'       => sprintf(
						/* translators: %s: provider or generation error message. */
						__( 'Content generation failed: %s', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						$e->getMessage()
					),
					'error_details' => array(
						'file' => basename( $e->getFile() ),
						'line' => $e->getLine(),
						'step' => 'Service resolution or workflow execution',
					),
				)
			);
		}
	}

	/**
	 * AJAX handler for image generation
	 * Delegates to Image Service
	 */
	public function handle_image_generation() {
		// 🚨 CENTRALIZED DEBUG CONTROL - Only log if debug mode is enabled
		if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
			ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] === AJAX HANDLER CALLED - handle_image_generation() ===' );

			// Add comprehensive request logging
			$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
			$request_uri    = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$header_names   = function_exists( 'getallheaders' ) ? array_keys( (array) getallheaders() ) : array();
			ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Request method: ' . $request_method );
			ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Request URI: ' . $request_uri );
			ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] POST field names: ' . implode( ', ', array_map( 'sanitize_key', array_keys( $_POST ) ) ) );
			ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Header names: ' . implode( ', ', array_map( 'sanitize_key', $header_names ) ) );
		}

		// Verify nonce first
		if ( ! check_ajax_referer( 'ai_scribe_nonce', 'security', false ) ) {
			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] ❌ Nonce verification failed' );
			}
			wp_send_json_error(
				array(
					'msg'           => __( 'Security check failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					'console_debug' => '[AI_SCRIBE_IMAGE_DEBUG] ❌ Nonce verification failed',
				)
			);
			return;
		}

		// P8 §14.2: capability check (wizard image generation).
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
			return;
		}

		if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
			ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] ✅ Nonce verification passed' );
		}

		try {
			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] Getting image_service from container' );
			}
			$image_service = $this->container->get( 'image_service' );

			if ( ! $image_service ) {
				if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
					ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] ❌ Image service not found in container' );
				}
				wp_send_json_error(
					array(
						'msg'           => __( 'The image service is not available.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'console_debug' => '[AI_SCRIBE_IMAGE_DEBUG] ❌ Image service not found in container',
					)
				);
				return;
			}

			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] ✅ Image service found, calling generate_image()' );
			}

			// Set longer execution time for image generation

			$image_service->generate_image();

			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] ✅ Image service generate_image() completed' );
			}
		} catch ( Exception $e ) {
			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] ❌ Exception in handler: ' . $e->getMessage() );
				ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] ❌ Exception trace: ' . $e->getTraceAsString() );
			}

			if ( $this->logger ) {
				$this->logger->error( 'Image generation failed', array( 'exception' => $e->getMessage() ) );
			}
			wp_send_json_error(
				array(
					'msg'           => sprintf(
						/* translators: %s: image generation error message. */
						__( 'Image generation failed: %s', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						$e->getMessage()
					),
					'console_debug' => '[AI_SCRIBE_IMAGE_DEBUG] ❌ Exception: ' . $e->getMessage(),
					'error_trace'   => $e->getTraceAsString(),
				)
			);
		} catch ( Throwable $e ) {
			// Everything an Exception handler cannot see: Error, TypeError and
			// any other engine Throwable raised inside the image pipeline.
			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] ❌ PHP Error in handler: ' . $e->getMessage() );
				ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] ❌ PHP Error trace: ' . $e->getTraceAsString() );
			}

			wp_send_json_error(
				array(
					'msg'           => sprintf(
						/* translators: %s: PHP error message. */
						__( 'PHP error during image generation: %s', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						$e->getMessage()
					),
					'console_debug' => '[AI_SCRIBE_IMAGE_DEBUG] ❌ PHP Error: ' . $e->getMessage(),
					'error_trace'   => $e->getTraceAsString(),
				)
			);
		}

		if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
			ai_scribe_debug_log( '[AI_SCRIBE_IMAGE_DEBUG] === AJAX HANDLER COMPLETED ===' );
		}
	}

	/**
	 * AJAX handler for bulk per-section image generation.
	 * Delegates to Image Service (which performs its own nonce and
	 * capability checks, matching generate_image()).
	 */
	public function handle_section_image_generation() {
		try {
			$image_service = $this->container->get( 'image_service' );
			if ( ! $image_service ) {
				wp_send_json_error( array( 'msg' => __( 'The image service is not available.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}
			$image_service->handle_generate_section_images();
		} catch ( Throwable $e ) {
			if ( $this->logger ) {
				$this->logger->error( 'Section image generation failed', array( 'exception' => $e->getMessage() ) );
			}
			wp_send_json_error(
				array(
					'msg' => sprintf(
						/* translators: %s: section image generation error message. */
						__( 'Section image generation failed: %s', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * AJAX handler for checking recent images in media library
	 * Used by image polling system to find generated images
	 */
	public function handle_check_recent_images() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_scribe_nonce', 'security', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
			return;
		}

		try {
			$article_title = sanitize_text_field( wp_unslash( $_POST['article_title'] ?? '' ) );
			$per_page      = intval( $_POST['per_page'] ?? 10 );

			if ( empty( $article_title ) ) {
				wp_send_json_error( array( 'message' => __( 'An article title is required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			// Get recent images from media library
			$args = array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => $per_page,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'date_query'     => array(
					array(
						'after' => '5 minutes ago',
					),
				),
			);

			$images     = get_posts( $args );
			$image_data = array();

			foreach ( $images as $image ) {
				$image_data[] = array(
					'id'       => $image->ID,
					'title'    => $image->post_title,
					'url'      => wp_get_attachment_url( $image->ID ),
					'alt_text' => get_post_meta( $image->ID, '_wp_attachment_image_alt', true ),
					'caption'  => $image->post_excerpt,
					'date'     => $image->post_date,
				);
			}

			// 🚨 CENTRALIZED DEBUG CONTROL - Only log if debug mode is enabled
			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( 'OPACE IMAGE CHECK: Found ' . count( $image_data ) . ' recent images for title: ' . $article_title );
			}

			wp_send_json_success( $image_data );

		} catch ( Exception $e ) {
			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( 'OPACE IMAGE CHECK: Error - ' . $e->getMessage() );
			}
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: media-library lookup error message. */
						__( 'Failed to check recent images: %s', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * AJAX handler for updating image metadata (alt text and caption)
	 * Called when image is found in media library to update alt text and caption
	 */
	public function handle_update_image_metadata() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_scribe_nonce', 'security', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
			return;
		}

		try {
			$attachment_id = intval( $_POST['attachment_id'] ?? 0 );
			$alt_text      = sanitize_text_field( wp_unslash( $_POST['alt_text'] ?? '' ) );
			$caption       = sanitize_text_field( wp_unslash( $_POST['caption'] ?? '' ) );

			if ( empty( $attachment_id ) ) {
				wp_send_json_error( array( 'message' => __( 'An attachment ID is required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			if ( empty( $alt_text ) ) {
				wp_send_json_error( array( 'message' => __( 'Alt text is required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			// Update alt text
			$alt_updated = update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

			// Update caption (post_excerpt)
			$caption_updated = wp_update_post(
				array(
					'ID'           => $attachment_id,
					'post_excerpt' => $caption,
				)
			);

			if ( $alt_updated !== false && $caption_updated !== 0 ) {
				if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
					ai_scribe_debug_log( 'OPACE IMAGE CHECK: image ALT text and caption added in media library now' );
				}
				wp_send_json_success(
					array(
						'message'       => __( 'Image metadata updated successfully.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'alt_text'      => $alt_text,
						'caption'       => $caption,
						'attachment_id' => $attachment_id,
					)
				);
			} else {
				wp_send_json_error( array( 'message' => __( 'Failed to update image metadata.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
			}
		} catch ( Exception $e ) {
			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( 'OPACE IMAGE CHECK: Error updating metadata - ' . $e->getMessage() );
			}
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: image metadata update error message. */
						__( 'Failed to update image metadata: %s', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * AJAX handler for content evaluation (Q&A generation)
	 * Delegates to Content Service with evaluate action
	 */
	public function handle_content_evaluation() {
		try {
			$content_service = $this->container->get( 'content_service' );

			// Set the action to 'evaluate' for Q&A generation
			$_POST['action_input'] = 'evaluate';

			$content_service->suggest_content();
		} catch ( Exception $e ) {
			$this->logger->error( 'Content evaluation failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => __( 'Content evaluation failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
		}
	}

	/**
	 * AJAX handler for style/tone updates
	 * Uses Config Manager
	 */
	public function handle_style_tone_update() {
		try {
			$config = $this->container->get( 'config' );

			// Verify nonce - check both 'security' and 'nonce' parameters
			$nonce = sanitize_text_field( wp_unslash( $_POST['security'] ?? $_POST['nonce'] ?? '' ) );
			if ( ! wp_verify_nonce( $nonce, 'ai_scribe_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid security nonce.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			$style    = sanitize_text_field( $_POST['writing_style'] ?? $_POST['style'] ?? '' );
			$tone     = sanitize_text_field( $_POST['writing_tone'] ?? $_POST['tone'] ?? '' );
			$language = sanitize_text_field( wp_unslash( $_POST['language'] ?? '' ) );

			// 🔧 DEBUG: Log received values for console
			$debug_info = array(
				'received_language' => $language,
				'received_style'    => $style,
				'received_tone'     => $tone,
			);

			if ( $style ) {
				$config->set( 'content.writing_style', $style );
				$debug_info['style_saved'] = true;
			}
			if ( $tone ) {
				$config->set( 'content.writing_tone', $tone );
				$debug_info['tone_saved'] = true;
			}
			if ( $language ) {
				$config->set( 'content.language', $language );
				$debug_info['language_saved'] = true;
			}

			wp_send_json_success(
				array(
					'message' => __( 'Settings updated successfully.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					'debug'   => $debug_info,
				)
			);
		} catch ( Exception $e ) {
			$this->logger->error( 'Style/tone update failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => __( 'Update failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
		}
	}

	/**
	 * AJAX handler for nonce refresh
	 */
	public function handle_nonce_refresh() {
		wp_send_json_success(
			array(
				'nonce'   => wp_create_nonce( 'ai_scribe_nonce' ),
				'message' => __( 'Security nonce refreshed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			)
		);
	}

	/**
	 * AJAX handler for shortcode removal
	 */
	public function handle_shortcode_removal() {
		try {
			// Verify nonce - check both 'security' and 'nonce' parameters for compatibility
			$nonce = sanitize_text_field( wp_unslash( $_POST['security'] ?? $_POST['nonce'] ?? '' ) );
			if ( ! wp_verify_nonce( $nonce, 'ai_scribe_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid security nonce.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}


		// P8 §14.2: capability check (shortcodes admin screen).
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
			return;
		}

			$shortcode_id = absint( wp_unslash( $_POST['shortcode_id'] ?? ( $_POST['id'] ?? 0 ) ) );
			if ( $shortcode_id ) {
				// Delegate to ShortcodeService so the {prefix}article_builder
				// ROW is deleted (the old option delete removed nothing).
				$_POST['id']       = $shortcode_id;
				$shortcode_service = $this->container->get( 'shortcode_service' );
				$shortcode_service->remove_short_code_content();
			} else {
				wp_send_json_error( array( 'message' => __( 'Invalid shortcode ID.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
			}
		} catch ( Exception $e ) {
			$this->logger->error( 'Shortcode removal failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => __( 'Shortcode removal failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
		}
	}

	/**
	 * AJAX handler for shortcode page
	 * Delegates to the proper Shortcode Service
	 */
	public function handle_shortcode_page() {
		try {
			// Delegate to shortcode service for actual implementation
			$shortcode_service = $this->container->get( 'shortcode_service' );
			$shortcode_service->send_shortcode_page();
		} catch ( Exception $e ) {
			$this->logger->error( 'Shortcode save failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => __( 'Shortcode save failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
		}
	}

	/**
	 * AJAX handler for article retrieval
	 */
	public function handle_article_retrieval() {
		try {
			// Delegate to admin service for actual implementation
			$admin_service = $this->container->get( 'admin_service' );
			$admin_service->get_article();
		} catch ( Exception $e ) {
			$this->logger->error( 'Article retrieval failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => __( 'Article retrieval failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
		}
	}

	/**
	 * AJAX handler for API key validation
	 */
	public function handle_api_key_validation() {
		try {
			// Verify nonce - check both 'security' and 'nonce' parameters for compatibility
			$nonce = $_POST['security'] ?? $_POST['nonce'] ?? '';
			if ( ! wp_verify_nonce( $nonce, 'ai_scribe_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid security nonce.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );

			if ( empty( $api_key ) ) {
				wp_send_json_error( array( 'message' => __( 'An API key is required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			// Use Opace AI Hub Adapter to validate the API key
			$ai_core_adapter   = $this->container->get( 'ai_core_adapter' );
			$validation_result = $ai_core_adapter->validate_api_key( $api_key );

			if ( $validation_result['valid'] ) {
				// Save the API key if valid
				$config = $this->container->get( 'config' );
				$config->set( 'engine.api_key', $api_key );

				wp_send_json_success(
					array(
						'message'  => __( 'The API key is valid and has been saved.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'provider' => $validation_result['provider'] ?? 'unknown',
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message' => $validation_result['error'] ?? 'Invalid API key',
					)
				);
			}
		} catch ( Exception $e ) {
			$this->logger->error( 'API key validation failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => __( 'API key validation failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
		}
	}

	/**
	 * AJAX handler for prompts requests
	 */
	public function handle_prompts_request() {
		try {
			// Verify nonce for security
			$nonce = $_POST['security'] ?? '';
			if ( ! wp_verify_nonce( $nonce, 'ai_scribe_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			// P8 §14.2: wizard consumers need edit_posts (login alone is not enough).
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_send_json_error( array( 'message' => __( 'Unauthorised request.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			// Get prompts from database
			$prompts_data = array();

			// Get all prompt options from WordPress
			$prompt_keys = array(
				'ab_gpt_title_prompt',
				'ab_gpt_keyword_prompt',
				'ab_gpt_outline_prompt',
				'ab_gpt_intro_prompt',
				'ab_gpt_tagline_prompt',
				'ab_gpt_body_prompt',
				'ab_gpt_conclusion_prompt',
				'ab_gpt_qa_prompt',
				'ab_gpt_meta_prompt',
			);

			foreach ( $prompt_keys as $key ) {
				$value = get_option( $key, '' );
				if ( ! empty( $value ) ) {
					$prompts_data[ $key ] = $value;
				}
			}

			wp_send_json_success( $prompts_data );

		} catch ( Exception $e ) {
			$this->logger->error( 'Prompts request failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => __( 'Failed to load prompts.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
		}
	}

	/**
	 * Get initialization status
	 *
	 * @return bool Initialization status
	 */
	public function is_initialized() {
		return $this->initialized;
	}

	/**
	 * Get service container
	 *
	 * @return AI_Scribe_Service_Container
	 */
	public function get_container() {
		return $this->container;
	}

	/**
	 * Get plugin health status
	 *
	 * @return array Health status
	 */
	public function get_health_status() {
		if ( ! $this->initialized ) {
			return array( 'status' => 'not_initialized' );
		}

		return array(
			'status'      => 'healthy',
			'initialized' => $this->initialized,
			'version'     => $this->plugin_version,
			'services'    => $this->dependency_resolver->get_resolution_stats(),
			'ai_core'     => $this->container->get( 'ai_core_adapter' )->get_health_status(),
		);
	}

	/**
	 * Add plugin action links
	 *
	 * @param array $links Existing plugin action links
	 * @return array Modified plugin action links
	 */
	public function add_plugin_action_links( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=ai_scribe_settings' ) . '">' . __( 'Settings', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '</a>';
		$help_link     = '<a href="' . admin_url( 'admin.php?page=ai_scribe_help' ) . '">' . __( 'Help', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '</a>';
		$review_link   = '<a href="' . esc_url( 'https://wordpress.org/support/plugin/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/reviews/#new-post' ) . '" id="review-link" target="_blank">' . __( 'Leave a Review', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '</a>';

		array_unshift( $links, $settings_link, $help_link, $review_link );

		return $links;
	}

	/**
	 * AJAX handler for general settings save
	 */
	public function handle_settings_save() {
		try {
			// Verify nonce - check both 'security' and 'nonce' parameters for compatibility
			$nonce = $_POST['security'] ?? $_POST['nonce'] ?? '';
			if ( ! wp_verify_nonce( $nonce, 'ai_scribe_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid security nonce.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			$config = $this->container->get( 'config' );

			// Handle prompt templates
			if ( isset( $_POST['prompts_content'] ) && is_array( $_POST['prompts_content'] ) ) {
				$current_prompts = get_option( 'ab_prompts_content', array() );

				// Extract excluded words for processing
				$excluded_words = isset( $_POST['prompts_content']['excluded_words'] ) ? sanitize_textarea_field( stripslashes( $_POST['prompts_content']['excluded_words'] ) ) : '';

				foreach ( $_POST['prompts_content'] as $key => $value ) {
					$sanitized_value = wp_kses_post( stripslashes( $value ) );

					// Store instructions cleanly without combining with excluded words
					// The combination will happen dynamically during content generation
					$current_prompts[ $key ] = $sanitized_value;
				}
				update_option( 'ab_prompts_content', $current_prompts );
				// 🔧 CRITICAL FIX: Clear WordPress object cache to prevent 15-second delays
				if ( function_exists( 'wp_cache_delete' ) ) {
					wp_cache_delete( 'ab_prompts_content', 'options' );
					wp_cache_delete( 'ai_scribe_qna_setting_v1', 'ai_scribe' ); // Clear Q&A setting cache
				}
				if ( function_exists( 'wp_cache_flush' ) ) {
					wp_cache_flush();
				}
				$this->logger->info( 'Prompt templates saved', array( 'keys' => array_keys( $_POST['prompts_content'] ) ) );
			}

			// Handle content settings (both new and old format for compatibility)
			if ( isset( $_POST['content_settings'] ) ) {
				$content_settings = $_POST['content_settings'];

				// Sanitize and save content settings
				$sanitized_settings = array(
					'language'          => sanitize_text_field( $content_settings['language'] ?? 'English' ),
					'writing_style'     => sanitize_text_field( $content_settings['writing_style'] ?? 'Business' ),
					'writing_tone'      => sanitize_text_field( $content_settings['writing_tone'] ?? 'Professional' ),
					'number_of_heading' => intval( $content_settings['number_of_heading'] ?? 5 ),
					'heading_tag'       => sanitize_text_field( $content_settings['heading_tag'] ?? 'H2' ),
					'add_qna'           => isset( $content_settings['add_qna'] ) ? (bool) $content_settings['add_qna'] : true,
				);

				$config->set_group( 'content', $sanitized_settings );
			}

			// Get existing settings to merge with new values (single source of truth)
			$current_content_settings = get_option( 'ab_gpt_content_settings', array() );
			$settings_updated         = false;

			// Handle flat form format settings (current settings.php form structure)
			if ( isset( $_POST['language'] ) || isset( $_POST['writing_style'] ) || isset( $_POST['writing_tone'] ) ||
				isset( $_POST['number_of_heading'] ) || isset( $_POST['Heading_tag'] ) || isset( $_POST['mode'] ) ) {

				// Update only the fields that are present in the form
				if ( isset( $_POST['language'] ) ) {
					$current_content_settings['language'] = sanitize_text_field( wp_unslash( $_POST['language'] ) );
				}
				if ( isset( $_POST['writing_style'] ) ) {
					$current_content_settings['writing_style'] = sanitize_text_field( wp_unslash( $_POST['writing_style'] ) );
				}
				if ( isset( $_POST['writing_tone'] ) ) {
					$current_content_settings['writing_tone'] = sanitize_text_field( wp_unslash( $_POST['writing_tone'] ) );
				}
				if ( isset( $_POST['number_of_heading'] ) ) {
					$current_content_settings['number_of_heading'] = sanitize_text_field( wp_unslash( $_POST['number_of_heading'] ) );
				}
				if ( isset( $_POST['Heading_tag'] ) ) {
					$current_content_settings['Heading_tag'] = sanitize_text_field( wp_unslash( $_POST['Heading_tag'] ) );
				}
				if ( isset( $_POST['mode'] ) ) {
					$current_content_settings['mode'] = sanitize_text_field( wp_unslash( $_POST['mode'] ) );
				}

				$settings_updated = true;
				$this->logger->info(
					'Content settings updated',
					array(
						'fields' => array_keys(
							array_filter(
								array(
									'language'          => isset( $_POST['language'] ),
									'writing_style'     => isset( $_POST['writing_style'] ),
									'writing_tone'      => isset( $_POST['writing_tone'] ),
									'number_of_heading' => isset( $_POST['number_of_heading'] ),
									'Heading_tag'       => isset( $_POST['Heading_tag'] ),
									'mode'              => isset( $_POST['mode'] ),
								)
							)
						),
					)
				);
			}

			// Handle checkbox array (checkArr) - save to check_Arr format expected by form
			// Clear existing checkboxes first (unchecked boxes don't appear in POST)
			$current_content_settings['check_Arr'] = array();

			if ( isset( $_POST['checkArr'] ) && is_array( $_POST['checkArr'] ) ) {
				foreach ( $_POST['checkArr'] as $key => $value ) {
					$current_content_settings['check_Arr'][ $key ] = $value;
				}
				$this->logger->info( 'Checkbox settings updated', array( 'checkboxes' => array_keys( $_POST['checkArr'] ) ) );
				$settings_updated = true;
			} else {
				$this->logger->info( 'No checkboxes selected - cleared all checkbox settings' );
				$settings_updated = true;
			}

			// Save all content settings once
			if ( $settings_updated ) {
				update_option( 'ab_gpt_content_settings', $current_content_settings );

				// Also save as individual WordPress options for backward compatibility
				if ( isset( $_POST['language'] ) ) {
					update_option( 'ab_language', $current_content_settings['language'] );
				}
				if ( isset( $_POST['writing_style'] ) ) {
					update_option( 'ab_writing_style', $current_content_settings['writing_style'] );
				}
				if ( isset( $_POST['writing_tone'] ) ) {
					update_option( 'ab_writing_tone', $current_content_settings['writing_tone'] );
				}
				if ( isset( $_POST['number_of_heading'] ) ) {
					update_option( 'ab_number_of_heading', $current_content_settings['number_of_heading'] );
				}
				if ( isset( $_POST['Heading_tag'] ) ) {
					update_option( 'ab_heading_tag', $current_content_settings['Heading_tag'] );
				}
			}

			// Handle image generation checkbox
			if ( isset( $_POST['enable_image_generation'] ) ) {
				update_option( 'ab_enable_image_generation', (bool) $_POST['enable_image_generation'] );
				$this->logger->info( 'Image generation setting saved', array( 'enabled' => (bool) $_POST['enable_image_generation'] ) );
			}

			wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );

		} catch ( Exception $e ) {
			$this->logger->error( 'Settings save failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: settings save error message. */
						__( 'Settings save failed: %s', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * AJAX handler for engine settings save
	 */
	public function handle_engine_settings_save() {
		try {
			// Verify nonce - check both 'security' and 'nonce' parameters for compatibility
			$nonce = $_POST['security'] ?? $_POST['nonce'] ?? '';
			if ( ! wp_verify_nonce( $nonce, 'ai_scribe_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid security nonce.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			$config = $this->container->get( 'config' );

			// Handle engine settings
			if ( isset( $_POST['engine_settings'] ) ) {
				$engine_settings = $_POST['engine_settings'];

				// Sanitize and save engine settings
				$sanitized_settings = array(
					'api_key'          => sanitize_text_field( $engine_settings['api_key'] ?? '' ),
					'model'            => sanitize_text_field( $engine_settings['model'] ?? '' ),
					'temp'             => floatval( $engine_settings['temp'] ?? 0.5 ),
					'top_p'            => floatval( $engine_settings['top_p'] ?? 0.5 ),
					'freq_pent'        => floatval( $engine_settings['freq_pent'] ?? 0.2 ),
					'presence_penalty' => floatval( $engine_settings['presence_penalty'] ?? 0.2 ),
					'n'                => intval( $engine_settings['n'] ?? 1 ),
				);

				$config->set_group( 'engine', $sanitized_settings );
			}

			wp_send_json_success( array( 'message' => __( 'Engine settings saved successfully.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );

		} catch ( Exception $e ) {
			$this->logger->error( 'Engine settings save failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => __( 'Engine settings save failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
		}
	}

	/**
	 * Handle engine request data (form uses al_scribe_engine_request_data action)
	 * Processes flat form data structure instead of nested structure
	 *
	 * @return void
	 */
	public function handle_engine_request_data() {
		try {
			// 🚨 CENTRALIZED DEBUG CONTROL - Only log if debug mode is enabled
			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( 'ANTHROPIC API: handle_engine_request_data called' );
				ai_scribe_debug_log( 'ANTHROPIC API: POST data keys: ' . implode( ', ', array_keys( $_POST ) ) );
				ai_scribe_debug_log( 'ANTHROPIC API: anthropic_api_key in POST: ' . ( isset( $_POST['anthropic_api_key'] ) ? 'YES' : 'NO' ) );
				if ( isset( $_POST['anthropic_api_key'] ) ) {
					ai_scribe_debug_log( 'ANTHROPIC API: anthropic_api_key length: ' . strlen( $_POST['anthropic_api_key'] ) );
				}
			}

			// Verify nonce - form uses 'security' field name
			if ( ! wp_verify_nonce( $_POST['security'] ?? '', 'ai_scribe_nonce' ) ) {
				if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
					ai_scribe_debug_log( 'ANTHROPIC API: Nonce verification failed' );
				}
				wp_send_json_error( array( 'message' => __( 'Invalid security nonce.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( 'ANTHROPIC API: Nonce verification passed' );
			}

			$config = $this->container->get( 'config' );

			// Process flat form data structure
			$sanitized_settings = array(
				'api_key'                 => sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ),
				'anthropic_api_key'       => sanitize_text_field( wp_unslash( $_POST['anthropic_api_key'] ?? '' ) ),
				'model'                   => sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) ),
				'temp'                    => floatval( $_POST['temp'] ?? 0.5 ),
				'top_p'                   => floatval( $_POST['top_p'] ?? 0.5 ),
				'best_oi'                 => floatval( $_POST['best_oi'] ?? 1.0 ),
				'freq_pent'               => floatval( $_POST['freq_pent'] ?? 0.2 ),
				'Presence_penalty'        => floatval( $_POST['Presence_penalty'] ?? 0.2 ),
				'n'                       => intval( $_POST['n'] ?? 1 ),
				'enable_image_generation' => intval( $_POST['enable_image_generation'] ?? 0 ),
			);

			// 🚨 CENTRALIZED DEBUG CONTROL - Only log if debug mode is enabled
			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( 'ANTHROPIC API: Sanitized settings:' );
				ai_scribe_debug_log( 'ANTHROPIC API: - api_key length: ' . strlen( $sanitized_settings['api_key'] ) );
				ai_scribe_debug_log( 'ANTHROPIC API: - anthropic_api_key length: ' . strlen( $sanitized_settings['anthropic_api_key'] ) );
				ai_scribe_debug_log( 'ANTHROPIC API: - model: ' . $sanitized_settings['model'] );
				ai_scribe_debug_log( 'ANTHROPIC API: - enable_image_generation: ' . $sanitized_settings['enable_image_generation'] );
			}

			// Process image generation settings
			$image_settings = array(
				'image_size'       => sanitize_text_field( wp_unslash( $_POST['image_size'] ?? 'auto' ) ),
				'image_quality'    => sanitize_text_field( wp_unslash( $_POST['image_quality'] ?? 'high' ) ),
				'image_format'     => sanitize_text_field( wp_unslash( $_POST['image_format'] ?? 'png' ) ),
				'image_background' => sanitize_text_field( wp_unslash( $_POST['image_background'] ?? 'auto' ) ),
				'image_style'      => sanitize_text_field( wp_unslash( $_POST['image_style'] ?? 'Photorealistic' ) ),
			);

			// Validate API keys based on selected model and settings (restored from v2.6)
			// TEMPORARILY DISABLED FOR DEBUGGING - TODO: Re-enable after testing
			/*
			$is_anthropic_model = strpos($sanitized_settings['model'], 'claude') !== false;
			$is_openai_model = !$is_anthropic_model; // All non-Claude models are OpenAI models

			// Check required API keys
			if ($is_anthropic_model && empty($sanitized_settings['anthropic_api_key'])) {
				wp_send_json_error([
					"msg" => "Anthropic API key is required for Claude models. Please add your Anthropic API key before saving."
				]);
				return;
			}

			if ($is_openai_model && empty($sanitized_settings['api_key'])) {
				wp_send_json_error([
					"msg" => "OpenAI API key is required for GPT models. Please add your OpenAI API key before saving."
				]);
				return;
			}

			// For Anthropic models with image generation enabled, OpenAI key is also required
			if ($is_anthropic_model && $sanitized_settings['enable_image_generation'] && empty($sanitized_settings['api_key'])) {
				wp_send_json_error([
					"msg" => "Both Anthropic and OpenAI API keys are required when using Claude models with image generation enabled. OpenAI key is needed for image generation."
				]);
				return;
			}
			*/

			// Save engine settings
			$config->set_group( 'engine', $sanitized_settings );

			// Also save individual options for backward compatibility
			update_option( 'ab_api_key', $sanitized_settings['api_key'] );
			update_option( 'ab_anthropic_api_key', $sanitized_settings['anthropic_api_key'] );
			update_option( 'ab_model', $sanitized_settings['model'] );
			update_option( 'ab_temp', $sanitized_settings['temp'] );
			update_option( 'ab_top_p', $sanitized_settings['top_p'] );
			update_option( 'ab_best_oi', $sanitized_settings['best_oi'] );
			update_option( 'ab_freq_pent', $sanitized_settings['freq_pent'] );
			update_option( 'ab_Presence_penalty', $sanitized_settings['Presence_penalty'] );
			update_option( 'ab_n', $sanitized_settings['n'] );
			update_option( 'ab_enable_image_generation', $sanitized_settings['enable_image_generation'] );

			// Save image generation settings (including enable_image_generation - this was missing in v2.6)
			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( 'ANTHROPIC API: Saving image settings:' );
				ai_scribe_debug_log( 'ANTHROPIC API: - enable_image_generation: ' . $sanitized_settings['enable_image_generation'] );
				ai_scribe_debug_log( 'ANTHROPIC API: - image_size: ' . $image_settings['image_size'] );
				ai_scribe_debug_log( 'ANTHROPIC API: - image_quality: ' . $image_settings['image_quality'] );
			}

			update_option( 'ab_enable_image_generation', $sanitized_settings['enable_image_generation'] ); // Fixed: was missing in v2.6
			update_option( 'ab_image_size', $image_settings['image_size'] );
			update_option( 'ab_image_quality', $image_settings['image_quality'] );
			update_option( 'ab_image_format', $image_settings['image_format'] );
			update_option( 'ab_image_background', $image_settings['image_background'] );
			update_option( 'ab_image_style', $image_settings['image_style'] );

			// Save grouped engine settings (matching v2.6 structure exactly for compatibility)
			$grouped_settings = array(
				'model'             => $sanitized_settings['model'],
				'temp'              => $sanitized_settings['temp'],
				'top_p'             => $sanitized_settings['top_p'],
				'freq_pent'         => $sanitized_settings['freq_pent'],
				'Presence_penalty'  => $sanitized_settings['Presence_penalty'],
				'api_key'           => $sanitized_settings['api_key'],
				'anthropic_api_key' => $sanitized_settings['anthropic_api_key'],
			);
			update_option( 'ab_gpt_ai_engine_settings', $grouped_settings );

			// Ensure grouped engine settings are synchronized
			$config->sync_engine_settings();

			// 🚨 CENTRALIZED DEBUG CONTROL - Only log if debug mode is enabled
			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( 'ANTHROPIC API: All settings saved successfully' );
				ai_scribe_debug_log( 'ANTHROPIC API: Sending success response' );
			}

			$this->logger->info(
				'Engine settings saved successfully',
				array(
					'engine_settings' => array_keys( $sanitized_settings ),
					'image_settings'  => array_keys( $image_settings ),
				)
			);

			wp_send_json_success(
				array(
					'msg'     => __( 'Your settings have been updated successfully.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), // Match v2.6 response format
					'message' => __( 'Settings saved successfully.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				)
			);

		} catch ( Exception $e ) {
			if ( AI_Scribe_Utility_Service::is_global_debug_enabled() ) {
				ai_scribe_debug_log( 'ANTHROPIC API: Exception occurred: ' . $e->getMessage() );
				ai_scribe_debug_log( 'ANTHROPIC API: Exception file: ' . $e->getFile() . ' line: ' . $e->getLine() );
			}
			$this->logger->error( 'Engine request data save failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error(
				array(
					'msg'     => sprintf(
						/* translators: %s: engine settings save error message. */
						__( 'Engine settings save failed: %s', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						$e->getMessage()
					), // Match v2.6 response format
					'message' => __( 'Failed to save settings.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				)
			);
		}
	}

	/**
	 * AJAX handler for phantom data cleanup
	 *
	 * Removes phantom/default values from ab_gpt_ai_engine_settings
	 * that are causing API key validation inconsistency.
	 *
	 * @return void
	 */
	public function handle_phantom_data_cleanup() {
		try {
			// Verify nonce - check both 'security' and 'nonce' parameters for compatibility
			$nonce = $_POST['security'] ?? $_POST['nonce'] ?? '';
			if ( ! wp_verify_nonce( $nonce, 'ai_scribe_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid security nonce.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			// Perform the cleanup using shared method
			$cleanup_result = $this->perform_phantom_data_cleanup();

			if ( $cleanup_result['needs_cleanup'] ) {
				wp_send_json_success(
					array(
						'message'             => __( 'Phantom data cleanup completed successfully.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'actions_taken'       => $cleanup_result['cleanup_actions'],
						'before_state'        => $cleanup_result['before_state'],
						'after_grouped_count' => $cleanup_result['after_grouped_count'],
					)
				);
			} else {
				wp_send_json_success(
					array(
						'message'      => __( 'No cleanup is needed; the data is already consistent.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'before_state' => $cleanup_result['before_state'],
					)
				);
			}
		} catch ( Exception $e ) {
			$this->logger->error( 'Phantom data cleanup failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: data cleanup error message. */
						__( 'Cleanup failed: %s', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * Perform phantom data cleanup (shared logic)
	 *
	 * This method contains the core cleanup logic that can be called
	 * from both the AJAX handler and plugin activation.
	 *
	 * @return array Cleanup results
	 */
	private function perform_phantom_data_cleanup() {
		// Check current state
		$ab_api_key                = get_option( 'ab_api_key', '' );
		$ab_anthropic_api_key      = get_option( 'ab_anthropic_api_key', '' );
		$ab_gpt_ai_engine_settings = get_option( 'ab_gpt_ai_engine_settings', array() );

		$before_state = array(
			'individual_openai'      => empty( $ab_api_key ) ? 'EMPTY' : 'Present (length: ' . strlen( $ab_api_key ) . ')',
			'individual_anthropic'   => empty( $ab_anthropic_api_key ) ? 'EMPTY' : 'Present (length: ' . strlen( $ab_anthropic_api_key ) . ')',
			'grouped_settings_count' => count( $ab_gpt_ai_engine_settings ),
			'grouped_has_openai'     => isset( $ab_gpt_ai_engine_settings['api_key'] ) && ! empty( $ab_gpt_ai_engine_settings['api_key'] ),
			'grouped_has_anthropic'  => isset( $ab_gpt_ai_engine_settings['anthropic_api_key'] ) && ! empty( $ab_gpt_ai_engine_settings['anthropic_api_key'] ),
		);

		// Cleanup logic
		$needs_cleanup    = false;
		$cleaned_settings = $ab_gpt_ai_engine_settings;
		$cleanup_actions  = array();

		// Step 1: If individual API key options are empty, remove them from grouped settings
		if ( empty( $ab_api_key ) && isset( $cleaned_settings['api_key'] ) && ! empty( $cleaned_settings['api_key'] ) ) {
			unset( $cleaned_settings['api_key'] );
			$needs_cleanup     = true;
			$cleanup_actions[] = 'Removed phantom OpenAI API key from grouped settings';
		}

		if ( empty( $ab_anthropic_api_key ) && isset( $cleaned_settings['anthropic_api_key'] ) && ! empty( $cleaned_settings['anthropic_api_key'] ) ) {
			unset( $cleaned_settings['anthropic_api_key'] );
			$needs_cleanup     = true;
			$cleanup_actions[] = 'Removed phantom Anthropic API key from grouped settings';
		}

		// Step 2: For fresh install, if both individual options are empty, clear the entire grouped settings
		if ( empty( $ab_api_key ) && empty( $ab_anthropic_api_key ) && ! empty( $ab_gpt_ai_engine_settings ) ) {
			$cleaned_settings  = array();
			$needs_cleanup     = true;
			$cleanup_actions[] = 'Fresh install detected - cleared all grouped settings to prevent phantom data';
		}

		if ( $needs_cleanup ) {
			// Update the option
			update_option( 'ab_gpt_ai_engine_settings', $cleaned_settings );

			// Clear any WordPress object cache
			if ( function_exists( 'wp_cache_delete' ) ) {
				wp_cache_delete( 'ab_gpt_ai_engine_settings', 'options' );
			}

			// Log the cleanup
			$this->logger->info(
				'Phantom data cleanup completed',
				array(
					'before_state'        => $before_state,
					'cleanup_actions'     => $cleanup_actions,
					'after_grouped_count' => count( $cleaned_settings ),
				)
			);
		}

		return array(
			'needs_cleanup'       => $needs_cleanup,
			'before_state'        => $before_state,
			'cleanup_actions'     => $cleanup_actions,
			'after_grouped_count' => count( $cleaned_settings ),
		);
	}

	/**
	 * AJAX handler for getting prompt templates
	 * Delegates to Prompt Manager
	 */
	public function handle_get_prompt_template() {
		try {
			$prompt_manager = $this->container->get( 'prompt_manager' );
			$prompt_manager->handle_get_prompt_template();
		} catch ( Exception $e ) {
			$this->logger->error( 'Get prompt template failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => __( 'Failed to get the prompt template.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
		}
	}

	/**
	 * AJAX handler for saving prompt templates
	 * Delegates to Prompt Manager
	 */
	public function handle_save_prompt_template() {
		try {
			$prompt_manager = $this->container->get( 'prompt_manager' );
			$prompt_manager->handle_save_prompt_template();
		} catch ( Exception $e ) {
			$this->logger->error( 'Save prompt template failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => __( 'Failed to save the prompt template.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
		}
	}

	/**
	 * AJAX handler for getting template data
	 * Delegates to Template Service
	 */
	public function handle_get_template_data() {
		try {
			// Verify nonce for security
			$nonce = $_POST['security'] ?? '';
			if ( ! wp_verify_nonce( $nonce, 'ai_scribe_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			// Check if user is logged in
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( array( 'message' => __( 'Unauthorised request.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			$template_service = $this->container->get( 'template_service' );
			$result           = $template_service->get_template_data();

			if ( $result['success'] ) {
				wp_send_json_success( $result['data'] );
			} else {
				wp_send_json_error( array( 'message' => $result['message'] ) );
			}
		} catch ( Exception $e ) {
			$this->logger->error( 'Get template data failed', array( 'exception' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => __( 'Failed to get template data.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
		}
	}
	/**
	 * Register AJAX handlers for settings functionality
	 * 🚨 CRITICAL FIX: AJAX Handler Service disabled to prevent conflicts
	 * All AJAX actions are now registered directly in register_plugin_hooks()
	 *
	 * @return void
	 */
	private function register_ajax_handlers() {
		try {
			$logger = $this->container->get( 'logger' );

			// v3 P2: conversation/generation endpoint surface (docs/API_CONTRACT.md)
			new AI_Scribe_Conversation_Ajax_Controller(
				$logger,
				$this->container->get( 'conversation_service' ),
				$this->container->get( 'generation_service' ),
				$this->container->get( 'cost_estimator' ),
				$this->container->get( 'prompt_manager' ),
				$this->container->get( 'post_service' )
			);

			// v3 P5: settings endpoint surface (contract v1.1 extensions —
			// model list, write-only API keys, masked settings, UI prefs)
			new AI_Scribe_Settings_Ajax_Controller(
				$logger,
				$this->container->get( 'config' ),
				$this->container->get( 'ai_core_adapter' )
			);

			if ( $this->logger ) {
				$this->logger->info( 'AJAX Handler Service instantiated and registered' );
			}
		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error( 'Failed to instantiate AJAX Handler Service: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * AJAX handler for saving content settings (V4 frontend)
	 * Handles language, writing style, writing tone, etc.
	 */
	public function handle_save_content_settings() {
		// Verify nonce
		$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ai_scribe_nonce' ) ) {
			wp_send_json_error( __( 'Invalid security token.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
			return;
		}


		// P8 §14.2: capability check (settings write).
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
			return;
		}

		try {
			$current_settings = get_option( 'ab_gpt_content_settings', array() );

			// v3 settings screen sends one JSON `settings` object
			// (SettingsController.save → ApiClient.saveContentSettings).
			if ( isset( $_POST['settings'] ) ) {
				$incoming = json_decode( wp_unslash( (string) $_POST['settings'] ), true );
				if ( is_array( $incoming ) ) {
					foreach ( array( 'language', 'writing_style', 'writing_tone', 'avoid_keywords' ) as $field ) {
						if ( isset( $incoming[ $field ] ) && is_string( $incoming[ $field ] ) ) {
							$current_settings[ $field ] = sanitize_text_field( $incoming[ $field ] );
						}
					}

					// Heading controls (2.6.2 keys kept for back-compat readers).
					if ( isset( $incoming['heading_tag'] ) && is_string( $incoming['heading_tag'] ) ) {
						$heading_tag = strtoupper( sanitize_text_field( $incoming['heading_tag'] ) );
						if ( in_array( $heading_tag, array( 'H2', 'H3', 'H4', 'H5' ), true ) ) {
							$current_settings['heading_tag'] = $heading_tag;
							$current_settings['Heading_tag'] = $heading_tag;
						}
					}
					if ( isset( $incoming['number_of_headings'] ) && is_numeric( $incoming['number_of_headings'] ) ) {
						$number                                 = max( 1, min( 20, (int) $incoming['number_of_headings'] ) );
						$current_settings['number_of_headings'] = $number;
						$current_settings['number_of_heading']  = (string) $number;
					}
					if ( isset( $incoming['article_length_mode'] ) && is_string( $incoming['article_length_mode'] ) ) {
						$length_mode = sanitize_key( $incoming['article_length_mode'] );
						$current_settings['article_length_mode'] = in_array( $length_mode, array( 'auto', 'concise', 'standard', 'in_depth', 'custom' ), true ) ? $length_mode : 'auto';
					}
					if ( isset( $incoming['article_word_count'] ) && is_numeric( $incoming['article_word_count'] ) ) {
						$current_settings['article_word_count'] = max( 400, min( 8000, (int) $incoming['article_word_count'] ) );
					}

					// Humanizer writing mode (standard | humanize | personality).
					if ( isset( $incoming['mode'] ) && is_string( $incoming['mode'] ) ) {
						$mode                     = sanitize_key( $incoming['mode'] );
						$current_settings['mode'] = in_array( $mode, array( 'humanize', 'personality' ), true ) ? $mode : 'standard';
					}

					// Spelling variant (british | american). Anything else
					// falls back to British, which is the plugin's default.
					if ( isset( $incoming['spelling'] ) && is_string( $incoming['spelling'] ) ) {
						$spelling                     = sanitize_key( $incoming['spelling'] );
						$current_settings['spelling'] = ( 'american' === $spelling ) ? 'american' : 'british';
					}

					// check_Arr enhancement toggles — legacy shape key => key.
					if ( isset( $incoming['check_arr'] ) && is_array( $incoming['check_arr'] ) ) {
						$allowed_checks = array( 'addQNA', 'addkeywordBold', 'addinsertHyper', 'addinsertToc', 'addfurtheReading', 'addsubMatter', 'addimgCont' );
						$check_arr      = array();
						foreach ( $allowed_checks as $check_key ) {
							if ( isset( $incoming['check_arr'][ $check_key ] ) ) {
								$check_arr[ $check_key ] = $check_key;
							}
						}
						$current_settings['check_Arr'] = $check_arr;
						wp_cache_delete( 'ai_scribe_qna_setting_v1', 'ai_scribe' );
					}

					// Add New Language (2.6.2 changelog 1.2.5): append to the
					// ai_scribe_languages list.
					if ( ! empty( $incoming['custom_language'] ) && is_string( $incoming['custom_language'] ) ) {
						$custom_language = sanitize_text_field( $incoming['custom_language'] );
						$languages       = get_option( 'ai_scribe_languages', array() );
						$languages       = is_array( $languages ) ? $languages : array();
						if ( $custom_language !== '' && ! in_array( $custom_language, $languages, true ) ) {
							$languages[] = $custom_language;
							update_option( 'ai_scribe_languages', $languages );
						}
					}

					// Delete-data-on-uninstall opt-in (WP.org compliance).
					if ( array_key_exists( 'delete_data_on_uninstall', $incoming ) ) {
						update_option( 'ai_scribe_delete_data_on_uninstall', ! empty( $incoming['delete_data_on_uninstall'] ) ? 'yes' : 'no' );
					}

					// Engine settings: model + honest 0–2 / 0–1 clamp only.
					$engine = get_option( 'ab_gpt_ai_engine_settings', array() );
					$engine = is_array( $engine ) ? $engine : array();
					if ( ! empty( $incoming['model'] ) && is_string( $incoming['model'] ) ) {
						$engine['model'] = sanitize_text_field( $incoming['model'] );
						update_option( 'ab_model', $engine['model'] );
					}
					if ( isset( $incoming['temperature'] ) && is_numeric( $incoming['temperature'] ) ) {
						$engine['temperature'] = max( 0.0, min( 2.0, (float) $incoming['temperature'] ) );
						// GenerationService reads the 2.6.2 key name
						// (`ai_engine.temp`); without this mirror the value
						// typed here never reaches the wire.
						$engine['temp'] = $engine['temperature'];
					}
					if ( isset( $incoming['top_p'] ) && is_numeric( $incoming['top_p'] ) ) {
						$engine['top_p'] = max( 0.0, min( 1.0, (float) $incoming['top_p'] ) );
					}
					// UAT §12.2: per-model parameters (reasoning effort,
					// thinking level, …) from the schema-generated panel.
					if ( isset( $incoming['model_params'] ) && is_array( $incoming['model_params'] ) ) {
						$params = array();
						foreach ( $incoming['model_params'] as $param_key => $param_value ) {
							if ( ! is_string( $param_key ) ) {
								continue;
							}
							if ( is_numeric( $param_value ) ) {
								$params[ sanitize_key( $param_key ) ] = (float) $param_value;
							} elseif ( is_string( $param_value ) ) {
								$params[ sanitize_key( $param_key ) ] = sanitize_text_field( $param_value );
							}
						}
						$engine['model_params'] = $params;
					}
					update_option( 'ab_gpt_ai_engine_settings', $engine );

					// Image options.
					if ( isset( $incoming['images'] ) && is_array( $incoming['images'] ) ) {
						$images            = get_option( 'ab_gpt_image_settings', array() );
						$images            = is_array( $images ) ? $images : array();
						$images['enabled'] = ! empty( $incoming['images']['enabled'] );
						if ( isset( $incoming['images']['model'] ) && is_string( $incoming['images']['model'] ) ) {
							$images['model'] = sanitize_text_field( $incoming['images']['model'] );
						}
						if ( isset( $incoming['images']['size'] ) && is_string( $incoming['images']['size'] ) ) {
							$images['size'] = sanitize_text_field( $incoming['images']['size'] );
						}
						// Full 2.6.2 image option set: quality / format /
						// background / style (ImageService reads these).
						if ( isset( $incoming['images']['quality'] ) && in_array( $incoming['images']['quality'], array( 'high', 'medium', 'low' ), true ) ) {
							$images['quality'] = $incoming['images']['quality'];
						}
						if ( isset( $incoming['images']['format'] ) && in_array( $incoming['images']['format'], array( 'png', 'webp', 'jpeg' ), true ) ) {
							$images['format'] = $incoming['images']['format'];
							// ImageService reads the standalone legacy option.
							update_option( 'ab_image_format', $images['format'] );
						}
						if ( isset( $incoming['images']['background'] ) && in_array( $incoming['images']['background'], array( 'auto', 'transparent', 'opaque' ), true ) ) {
							$images['background'] = $incoming['images']['background'];
							update_option( 'ab_image_background', $images['background'] );
						}
						if ( isset( $incoming['images']['style'] ) && is_string( $incoming['images']['style'] ) ) {
							$images['style'] = sanitize_text_field( $incoming['images']['style'] );
							// Legacy mirror used by the image-prompt formula.
							update_option( 'ab_image_style', $images['style'] );
						}
						update_option( 'ab_gpt_image_settings', $images );
					}
				}
			}

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
					'message'  => __( 'Settings saved successfully.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					'settings' => $current_settings,
				)
			);

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error( 'Failed to save content settings', array( 'error' => $e->getMessage() ) );
			}

			wp_send_json_error(
				sprintf(
					/* translators: %s: content settings save error message. */
					__( 'Failed to save content settings: %s', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Sanitise one prompt body for storage.
	 *
	 * Prompt bodies are plain text carrying a little inline HTML (the article
	 * prompts ask the model for <p> and <span>), so wp_kses_post() is still
	 * the right tag filter and is applied unchanged. What is undone is its
	 * entity normalisation: wp_kses_post() rewrites a bare `&` to `&amp;`,
	 * which is not markup safety — it is a text transform — and it put
	 * `&amp;` into the stored option, the settings box and the outbound
	 * prompt on every save. Restoring the ampersand cannot reintroduce a tag
	 * or an attribute, so nothing kses removed comes back.
	 *
	 * @param string $value Raw prompt body.
	 * @return string Sanitised prompt body.
	 */
	private static function sanitize_prompt_body( $value ) {
		return str_replace( '&amp;', '&', wp_kses_post( $value ) );
	}

	/**
	 * AJAX handler for saving prompt settings (V4 frontend)
	 * Handles prompt text content
	 */
	public function handle_save_prompt_settings() {
		// Verify nonce
		$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ai_scribe_nonce' ) ) {
			wp_send_json_error( __( 'Invalid security token.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
			return;
		}


		// P8 §14.2: capability check (settings write).
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
			return;
		}

		try {
			// v3 settings screen: one JSON `prompts` object keyed exactly as
			// ab_prompts_content (including capital-K Keywords_prompts).
			if ( isset( $_POST['prompts'] ) ) {
				$incoming = json_decode( wp_unslash( (string) $_POST['prompts'] ), true );
				if ( ! is_array( $incoming ) ) {
					wp_send_json_error( __( 'A prompts object is required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
					return;
				}

				$current_prompts = get_option( 'ab_prompts_content', array() );
				$current_prompts = is_array( $current_prompts ) ? $current_prompts : array();
				foreach ( $incoming as $key => $value ) {
					if ( ! is_string( $key ) || ! is_string( $value ) ) {
						continue;
					}
					// Keys preserved verbatim (Keywords_prompts quirk included).
					$current_prompts[ $key ] = self::sanitize_prompt_body( $value );
				}
				update_option( 'ab_prompts_content', $current_prompts );
				wp_cache_delete( 'ab_prompts_content', 'options' );
				wp_cache_delete( 'ai_scribe_prompts_data_v1', 'ai_scribe' );

				if ( $this->logger ) {
					$this->logger->info( 'Prompt library saved', array( 'keys' => array_keys( $incoming ) ) );
				}

				wp_send_json_success( array( 'message' => __( 'Prompts saved successfully.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ) );
				return;
			}

			if ( ! isset( $_POST['prompt_text'] ) ) {
				wp_send_json_error( __( 'Prompt text is required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
				return;
			}

			$prompt_text = self::sanitize_prompt_body( wp_unslash( $_POST['prompt_text'] ) );

			// Save to appropriate option
			update_option( 'ai_scribe_prompt_text', $prompt_text );

			if ( $this->logger ) {
				$this->logger->info( 'Prompt settings saved' );
			}

			wp_send_json_success(
				array(
					'message' => __( 'Prompt saved successfully.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				)
			);

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error( 'Failed to save prompt settings', array( 'error' => $e->getMessage() ) );
			}

			wp_send_json_error(
				sprintf(
					/* translators: %s: prompt settings save error message. */
					__( 'Failed to save prompt settings: %s', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					$e->getMessage()
				)
			);
		}
	}
}
