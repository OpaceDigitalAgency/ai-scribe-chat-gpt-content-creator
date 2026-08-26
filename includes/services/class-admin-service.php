<?php
/**
 * Admin Service Class
 *
 * Handles WordPress admin functionality: script/style enqueuing per
 * assets/ENQUEUE_MANIFEST.md, admin pages and small utility AJAX handlers.
 *
 * Security: API keys are NEVER localised into the page. The settings screen
 * reads masked status via the ai_scribe_get_settings endpoint.
 *
 * @package AI_Scribe
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class AI_Scribe_Admin_Service {

	private $config;
	private $wordpress_adapter;

	public function __construct( $logger = null, $config = null, $wordpress_adapter = null ) {
		$this->config            = $config;
		$this->wordpress_adapter = $wordpress_adapter;
		$this->init();
	}

	public function init() {
		// Hook into WordPress admin
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ) );
		add_action( 'admin_head', array( $this, 'print_theme_boot' ) );

		// Register AJAX handlers
		// P8 §14.5: ai_scribe_get_article / ai_scribe_get_qna_setting had no
		// frontend consumers and were unregistered (methods kept for tests).
	}

	/**
	 * Whether the current admin page is the wizard (Generate Article) screen.
	 */
	private function is_wizard_page( $hook ) {
		return $hook === 'toplevel_page_ai-scribe'
			|| strpos( $hook, 'ai_scribe_generate_article' ) !== false;
	}

	/**
	 * Whether the current admin page is the settings screen.
	 */
	private function is_settings_page( $hook ) {
		return strpos( $hook, 'ai_scribe_settings' ) !== false;
	}

	public function admin_enqueue_styles( $hook ) {
		// The menu is visible throughout wp-admin, so its alignment rule must be too.
		$this->enqueue_style( 'ai-scribe-admin-menu', 'assets/css/admin-menu.css' );

		if ( strpos( $hook, 'ai-scribe' ) === false && strpos( $hook, 'ai_scribe' ) === false ) {
			return;
		}

		// Token system + admin polish on EVERY AI-Scribe admin page
		// (UAT §12.3/§12.5: shortcodes, help and diagnostics were unstyled).
		$this->enqueue_style( 'ai-scribe-main', 'assets/css/main.css' );
		$this->enqueue_style( 'ai-scribe-components', 'assets/css/components.css', array( 'ai-scribe-main' ) );
		$this->enqueue_style( 'ai-scribe-admin-pages', 'assets/css/admin-pages.css', array( 'ai-scribe-components' ) );
		$this->enqueue_style( 'ai-scribe-admin-responsive', 'assets/css/admin-responsive.css', array( 'ai-scribe-admin-pages' ) );
		$this->enqueue_style( 'ai-scribe-notification-centre', 'assets/css/notification-center.css', array( 'ai-scribe-admin-responsive' ) );
		$this->enqueue_style( 'ai-scribe-opace-footer', 'assets/css/opace-footer.css', array( 'ai-scribe-admin-responsive' ) );

		if ( $this->is_wizard_page( $hook ) ) {
			$this->enqueue_style( 'ai-scribe-animations', 'assets/css/animations.css', array( 'ai-scribe-components' ) );
			$this->enqueue_style( 'ai-scribe-quill-snow', 'assets/editor/quill.snow.css' );
			$this->enqueue_style( 'ai-scribe-fontawesome', 'assets/icons/fontawesome.css' );
		}
	}

	public function admin_enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'ai-scribe' ) === false && strpos( $hook, 'ai_scribe' ) === false ) {
			return;
		}

		// One outcome channel on every AI-Scribe screen, independent of the
		// wizard/settings bundles and outside every nested scroll container.
		$this->enqueue_script( 'ai-scribe-notification-centre', 'assets/js/services/NotificationCenter.js' );

		if ( $this->is_wizard_page( $hook ) ) {
			$this->enqueue_wizard_scripts();
			return;
		}

		if ( $this->is_settings_page( $hook ) ) {
			$this->enqueue_settings_scripts();
			return;
		}

		// Saved Shortcodes: nonce'd remove buttons (UAT §12.5).
		if ( strpos( $hook, 'ai_scribe_saved_shortcodes' ) !== false ) {
			$this->enqueue_script( 'ai-scribe-shortcodes-page', 'assets/js/shortcodes-page.js', array( 'ai-scribe-notification-centre' ) );
			wp_localize_script(
				'ai-scribe-shortcodes-page',
				'ai_scribe',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'ai_scribe_nonce' ),
				)
			);
		}
	}

	/**
	 * Apply the saved dark/light theme BEFORE first paint on EVERY AI-Scribe
	 * admin page (§13.7/§13.11: help and shortcodes never loaded main.js, so
	 * they ignored the stored theme; this also removes the dark-mode flash).
	 */
	public function print_theme_boot() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		if ( strpos( $id, 'ai-scribe' ) === false && strpos( $id, 'ai_scribe' ) === false ) {
			return;
		}
		$boot = "try{var t=window.localStorage.getItem('ai-scribe-theme');"
			. "if(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){t='dark';}"
			. "if(t){document.documentElement.setAttribute('data-ai-scribe-theme',t);}}catch(e){}";
		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( $boot, array( 'id' => 'ai-scribe-theme-boot' ) );
		} else {
			echo '<script id="ai-scribe-theme-boot">' . $boot . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static script, no dynamic input.
		}
	}

	/**
	 * Cache-busted style enqueue (AI_SCRIBE_VERSION per house rule).
	 */
	private function enqueue_style( $handle, $relative_path, $deps = array() ) {
		wp_enqueue_style( $handle, AI_SCRIBE_URL . $relative_path, $deps, AI_SCRIBE_VERSION );
	}

	/**
	 * Cache-busted footer script enqueue (AI_SCRIBE_VERSION per house rule).
	 */
	private function enqueue_script( $handle, $relative_path, $deps = array() ) {
		wp_enqueue_script( $handle, AI_SCRIBE_URL . $relative_path, $deps, AI_SCRIBE_VERSION, true );
	}

	/**
	 * Wizard (Generate Article) page — full v3 MVC stack per the manifest.
	 * DisplayManager and the legacy WorkflowView/Content/Navigation/Editor
	 * controllers are pruned: per-step views + WizardFlowController are
	 * canonical (REFACTOR.md §7).
	 */
	private function enqueue_wizard_scripts() {
		// Vendored editors/icons.
		$this->enqueue_script( 'ai-scribe-quill', 'assets/editor/quill.js' );
		$this->enqueue_script( 'ai-scribe-lucide', 'assets/icons/lucide.js' );

		// Core state.
		$this->enqueue_script( 'ai-scribe-app-state', 'assets/js/core/AppState.js' );

		// Network layer.
		$this->enqueue_script( 'ai-scribe-api-client', 'assets/js/services/ApiClient.js', array( 'ai-scribe-app-state' ) );

		// Utilities. The v4 model/util modules (WorkflowModel, CostModel,
		// ValidationModel, …) are DELETED (REFACTOR.md §13.1): they targeted
		// selectors that no longer exist and carried hardcoded model/pricing
		// fallbacks. CardRenderer is the only utility the step views consume.
		$this->enqueue_script( 'ai-scribe-util-cardrenderer', 'assets/js/utils/CardRenderer.js', array( 'ai-scribe-app-state' ) );

		// Per-step views over typed data.
		$this->enqueue_script( 'ai-scribe-base-step-view', 'assets/js/views/steps/BaseStepView.js', array( 'ai-scribe-util-cardrenderer' ) );
		$this->enqueue_script( 'ai-scribe-choice-step-view', 'assets/js/views/steps/ChoiceStepView.js', array( 'ai-scribe-base-step-view' ) );
		$this->enqueue_script( 'ai-scribe-streaming-step-view', 'assets/js/views/steps/StreamingStepView.js', array( 'ai-scribe-base-step-view' ) );

		$step_views = array(
			'ai-scribe-step-titles'     => 'TitlesStepView',
			'ai-scribe-step-keywords'   => 'KeywordsStepView',
			'ai-scribe-step-outline'    => 'OutlineStepView',
			'ai-scribe-step-intro'      => 'IntroStepView',
			'ai-scribe-step-tagline'    => 'TaglineStepView',
			'ai-scribe-step-body'       => 'BodyStepView',
			'ai-scribe-step-conclusion' => 'ConclusionStepView',
			'ai-scribe-step-qna'        => 'QnaStepView',
			'ai-scribe-step-seo-meta'   => 'SeoMetaStepView',
			'ai-scribe-step-review'     => 'ReviewStepView',
			'ai-scribe-step-evaluate'   => 'EvaluateStepView',
			'ai-scribe-express-view'    => 'ExpressView',
		);
		$view_deps  = array( 'ai-scribe-choice-step-view', 'ai-scribe-streaming-step-view' );
		foreach ( $step_views as $handle => $file ) {
			$this->enqueue_script( $handle, 'assets/js/views/steps/' . $file . '.js', $view_deps );
		}
		$this->enqueue_script( 'ai-scribe-step-registry', 'assets/js/views/steps/StepViewRegistry.js', array_keys( $step_views ) );

		// Flow controller + modal (the settings bundle is settings-page only).
		$this->enqueue_script( 'ai-scribe-wizard-flow', 'assets/js/controllers/WizardFlowController.js', array( 'ai-scribe-step-registry', 'ai-scribe-api-client' ) );
		$this->enqueue_script( 'ai-scribe-modal-controller', 'assets/js/controllers/ModalController.js', array( 'ai-scribe-app-state' ) );
		$this->enqueue_script( 'ai-scribe-modal-view', 'assets/js/views/ModalView.js', array( 'ai-scribe-modal-controller' ) );

		// Main application (last).
		$this->enqueue_script(
			'ai-scribe-main-app',
			'assets/js/main.js',
			array(
				'ai-scribe-notification-centre',
				'ai-scribe-quill',
				'ai-scribe-lucide',
				'ai-scribe-wizard-flow',
				'ai-scribe-modal-view',
			)
		);

		$this->localize_main_script_data();
	}

	/**
	 * Settings page — token CSS + settings controller/view only.
	 */
	private function enqueue_settings_scripts() {
		$this->enqueue_script( 'ai-scribe-app-state', 'assets/js/core/AppState.js' );
		$this->enqueue_script( 'ai-scribe-api-client', 'assets/js/services/ApiClient.js', array( 'ai-scribe-app-state' ) );
		$this->enqueue_script( 'ai-scribe-settings-controller', 'assets/js/controllers/SettingsController.js', array( 'ai-scribe-api-client' ) );
		$this->enqueue_script( 'ai-scribe-settings-view', 'assets/js/views/SettingsView.js', array( 'ai-scribe-settings-controller' ) );
		$this->enqueue_script( 'ai-scribe-main-app', 'assets/js/main.js', array( 'ai-scribe-settings-view', 'ai-scribe-notification-centre' ) );

		$this->localize_main_script_data();
	}

	/**
	 * Get article content with clean configuration approach
	 */
	public function get_article( $action_input = '' ) {
		wp_send_json_success( array() );
	}

	/**
	 * Get Q&A setting via AJAX
	 */
	public function get_qna_setting() {
		$qna_enabled = false;
		if ( $this->config ) {
			$content_settings = $this->config->get_group( 'content' );
			$check_arr        = isset( $content_settings['check_Arr'] ) ? $content_settings['check_Arr'] : array();
			$qna_enabled      = isset( $check_arr['addQNA'] ) && $check_arr['addQNA'] === 'addQNA';
		} else {
			// Fallback to direct option access
			$check_arr   = get_option( 'ab_check_Arr', array() );
			$qna_enabled = isset( $check_arr['addQNA'] ) && $check_arr['addQNA'] === 'addQNA';
		}

		wp_send_json_success( array( 'qna_enabled' => $qna_enabled ) );
	}

	/**
	 * Setup admin menu
	 */
	public function setup_admin_menu() {
		return array(
			'page_title' => 'AI-Scribe Settings',
			'menu_title' => 'AI-Scribe',
			'capability' => 'manage_options',
			'menu_slug'  => 'ai-scribe-settings',
			'callback'   => array( $this, 'render_admin_page' ),
		);
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'AI-Scribe Settings', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '</h1>';
		echo '<p>' . esc_html__( 'Admin interface for AI-Scribe plugin configuration.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Get current admin service status
	 */
	public function get_status() {
		return array(
			'service'                 => 'AdminService',
			'status'                  => 'active',
			'javascript_extraction'   => 'complete',
			'wordpress_org_compliant' => true,
		);
	}

	/**
	 * Validate service functionality
	 */
	public function validate_service() {
		// Check if WordPress admin functions are available
		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			return 'WordPress admin functions not available';
		}

		// Check if required constants are defined
		if ( ! defined( 'AI_SCRIBE_URL' ) || ! defined( 'AI_SCRIBE_VER' ) ) {
			return 'Required plugin constants not defined';
		}

		// Service is valid
		return true;
	}

	/**
	 * Q&A setting with caching (used for the localized checkArr flag).
	 */
	/**
	 * All seven check_Arr enhancement toggles as booleans for the UI
	 * (legacy stored format: check_Arr['key'] === 'key' when enabled).
	 * addQNA defaults to enabled when the setting has never been saved
	 * (matching getQNASetting()).
	 *
	 * @return array key => bool
	 */
	private function get_check_arr_flags() {
		$keys      = array( 'addkeywordBold', 'addinsertHyper', 'addinsertToc', 'addfurtheReading', 'addsubMatter', 'addimgCont' );
		$content   = get_option( 'ab_gpt_content_settings', array() );
		$check_arr = ( is_array( $content ) && isset( $content['check_Arr'] ) && is_array( $content['check_Arr'] ) )
			? $content['check_Arr'] : array();
		$flags     = array( 'addQNA' => (bool) $this->getQNASetting() );
		foreach ( $keys as $key ) {
			$flags[ $key ] = isset( $check_arr[ $key ] ) && $check_arr[ $key ] === $key;
		}
		return $flags;
	}

	/**
	 * Image-generation flags for the wizard (early/parallel image
	 * generation + the 2.6.2 style preset used in the image prompt).
	 * No key material — booleans and the style label only.
	 *
	 * @return array Safe global defaults available to article-local overrides.
	 */
	private function get_image_flags() {
		$images = get_option( 'ab_gpt_image_settings', array() );
		$images = is_array( $images ) ? $images : array();
		$style  = isset( $images['style'] ) && is_string( $images['style'] )
			? $images['style']
			: (string) get_option( 'ab_image_style', '' );

		// A site whose only provider cannot generate images (Anthropic, say)
		// has images switched off here rather than at the moment of use, so the
		// wizard never starts a request that has no chance of succeeding.
		$available = class_exists( 'AI_Scribe_Image_Service' )
			? AI_Scribe_Image_Service::images_available()
			: true;

		return array(
			'enabled'   => ! empty( $images['enabled'] ) && $available,
			'available' => $available,
			'reason'    => $available || ! class_exists( 'AI_Scribe_Image_Service' )
				? ''
				: AI_Scribe_Image_Service::image_unavailable_message(),
			'style'     => $style,
			'model'      => isset( $images['model'] ) ? (string) $images['model'] : '',
			'size'       => isset( $images['size'] ) ? (string) $images['size'] : '1024x1024',
			'quality'    => isset( $images['quality'] ) ? (string) $images['quality'] : 'medium',
			'format'     => isset( $images['format'] ) ? (string) $images['format'] : (string) get_option( 'ab_image_format', 'png' ),
			'background' => isset( $images['background'] ) ? (string) $images['background'] : (string) get_option( 'ab_image_background', 'auto' ),
		);
	}

	private function getQNASetting() {
		static $qna_setting_cache = null;

		if ( $qna_setting_cache !== null ) {
			return $qna_setting_cache;
		}

		$cache_key      = 'ai_scribe_qna_setting_v1';
		$cached_setting = wp_cache_get( $cache_key, 'ai_scribe' );

		if ( $cached_setting !== false ) {
			$qna_setting_cache = $cached_setting;
			return $qna_setting_cache;
		}

		$content_settings = get_option( 'ab_gpt_content_settings', array() );

		if ( isset( $content_settings['check_Arr'] ) && is_array( $content_settings['check_Arr'] ) ) {
			$qna_setting_cache = isset( $content_settings['check_Arr']['addQNA'] ) && $content_settings['check_Arr']['addQNA'] === 'addQNA';
		} else {
			// Default to enabled if setting doesn't exist
			$qna_setting_cache = true;
		}

		wp_cache_set( $cache_key, $qna_setting_cache, 'ai_scribe', 300 );

		return $qna_setting_cache;
	}

	/**
	 * Prompts data with caching (feeds the editable prompt library UI —
	 * the prompt library is a user-visible product feature, not a secret).
	 */
	private function getCachedPromptsData() {
		static $prompts_cache = null;

		if ( $prompts_cache !== null ) {
			return $prompts_cache;
		}

		$cache_key      = 'ai_scribe_prompts_data_v1';
		$cached_prompts = wp_cache_get( $cache_key, 'ai_scribe' );

		if ( $cached_prompts !== false ) {
			$prompts_cache = $cached_prompts;
			return $prompts_cache;
		}

		$prompts_cache = get_option( 'ab_prompts_content', null );
		wp_cache_set( $cache_key, $prompts_cache, 'ai_scribe', 300 );

		return $prompts_cache;
	}

	/**
	 * Localise the ai_scribe object on the main script.
	 *
	 * SECURITY: no API keys here — only boolean configured flags. The keys
	 * themselves never reach the browser (REFACTOR.md §5 / ENQUEUE_MANIFEST).
	 */
	private function localize_main_script_data() {
		$engine_settings = get_option( 'ab_gpt_ai_engine_settings', array() );

		// Hub-aware: ConfigManager::get_api_key falls back to the Opace AI Hub
		// hub's shared keys (ai_core_settings), so a hub-managed install
		// reports its providers as configured (raw option reads did not).
		$providers_configured = array();
		foreach ( array( 'openai', 'anthropic', 'gemini' ) as $provider ) {
			$key = $this->config ? $this->config->get_api_key( $provider ) : '';
			$providers_configured[ $provider ] = is_string( $key ) && '' !== $key;
		}
		if ( ! $this->config ) {
			$providers_configured = array(
				'openai'    => (bool) get_option( 'ab_api_key', '' ),
				'anthropic' => (bool) get_option( 'ab_anthropic_api_key', '' ),
				'gemini'    => ! empty( $engine_settings['gemini_api_key'] ),
			);
		}

		/*
		 * One resolver decides the effective model, so the wizard cannot display
		 * a different model from the one about to be billed. It previously showed
		 * "GPT-5 · OpenAI" on a Gemini-only site, and "No model selected yet"
		 * beside a populated picker.
		 */
		$model = AI_Scribe_Model_Resolver::resolve(
			isset( $engine_settings['model'] ) ? (string) $engine_settings['model'] : ''
		);

		$localized_data = array(
			'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
			'nonce'               => wp_create_nonce( 'ai_scribe_nonce' ),
			'settingsUrl'         => admin_url( 'admin.php?page=ai_scribe_settings' ),
			'debugMode'           => defined( 'AI_SCRIBE_DEBUG_MODE' ) && AI_SCRIBE_DEBUG_MODE,
			'version'             => AI_SCRIBE_VERSION,
			'model'               => $model,
			'providersConfigured' => $providers_configured,
			'hasAnyApiKey'        => in_array( true, $providers_configured, true ),
			'checkArr'            => $this->get_check_arr_flags(),
			'images'              => $this->get_image_flags(),
			// Steps with no assembled prompt (Review compiles from state);
			// the wizard's prompt box shows an "automatic" notice for these.
			'noPromptSteps'       => array( 10 ),
			'promptsData'         => $this->getCachedPromptsData(),
			'contentSettings'     => get_option( 'ab_gpt_content_settings', array() ),
			'i18n'                => $this->get_js_i18n(),
		);

		wp_localize_script( 'ai-scribe-main-app', 'ai_scribe', $localized_data );
	}

	/**
	 * Strings shared by the Wizard and Settings JavaScript bundles.
	 *
	 * @return array<string,string>
	 */
	private function get_js_i18n() {
		return array(
			'openGoogleTrends'          => __( 'Open in Google Trends', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'evaluationSummary'         => __( 'Evaluation summary', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'evaluationSummaryNote'     => __( 'Structural checks are measured from the final Review HTML. Editorial rows are clearly labelled AI review and should be confirmed by an editor.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'evaluationTableCaption'    => __( 'Article evaluation checks with evidence and suggested next actions', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'refreshingModels'          => __( 'Refreshing the model list from your providers…', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			/* translators: 1: Saved AI model name. 2: AI provider name. */
			'savedModelKeyInvalid'      => __( 'Saved model “%1$s” and its %2$s key were retained, but the key did not pass validation. Check it in Opace AI Hub before generating.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			/* translators: 1: Saved AI model name. 2: AI provider name. */
			'savedModelKeyUnchecked'    => __( 'Saved model “%1$s” and its %2$s key were retained, but the provider could not complete validation. Check it in Opace AI Hub before generating.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			/* translators: 1: Saved AI model name. 2: AI provider name. */
			'savedModelKeyMissing'      => __( 'Saved model “%1$s” was retained, but its %2$s key is missing. Add the key in Opace AI Hub before generating.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'savedKeyInvalidSuffix'     => __( 'saved; retained key did not pass validation', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'savedKeyUncheckedSuffix'   => __( 'saved; retained key could not be checked', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'savedKeyMissingSuffix'     => __( 'saved; provider key is missing', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'modelParameters'           => __( 'Model parameters', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'configured'                => __( 'Configured', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'optimisingMetadata'        => __( 'Asking the selected model to shorten the overlength metadata…', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'metadataSuggestionReady'   => __( 'Suggestion ready. Compare it with your original before applying.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'metadataApplied'           => __( 'Optimised metadata applied. Undo remains available until you edit or regenerate either field.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'metadataOriginalKept'      => __( 'Original metadata kept.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'metadataOriginalRestored'  => __( 'Original metadata restored.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'selectedKeywordCoverage'   => __( 'Selected keyword coverage', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'noKeywordToCheck'          => __( 'No selected keyword is available to check. Confirm relevance manually.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'articleTargetNotReached'   => __( 'Article generated — target not fully reached', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'runningAmendedPrompt'      => __( 'Running your amended prompt for this step…', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'amendedPromptUsed'         => __( 'Amended prompt used.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'amendedPromptFailed'       => __( 'The amended prompt could not be run. Try again.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'amendedPromptReady'        => __( 'Your amended prompt is ready to run.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'editPromptToEnable'        => __( 'Edit the prompt to enable this button.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'enterTopicForArticle'      => __( 'Enter a topic to generate the article.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'improvingArticleLength'    => __( 'Improving article length', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'reviewSectionPrompts'      => __( 'Optional: review or edit these prompts before generating the section set.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'imageProviderStillWorking' => __( 'Still working with your image provider. Larger images can take over a minute.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'caption'                   => __( 'Caption', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'captionPlaceholder'        => __( 'Edit the caption, or clear it to remove it', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'savePrompt'                => __( 'Save prompt', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'regenerateImage'           => __( 'Regenerate image', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'placeInArticle'            => __( 'Place in article', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'notSaved'                  => __( 'Not saved', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'notSavedDetail'            => __( 'Not saved. This article currently exists only in AI-Scribe.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'unsavedChanges'            => __( 'Unsaved changes', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'unsavedChangesDetail'      => __( 'Changes made since the last save exist only in AI-Scribe. Update a destination before finishing if you want to keep them.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'saved'                     => __( 'Saved', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'discardAndStartNew'        => __( 'Discard & Start New', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'nothingToPublish'          => __( 'There is nothing to publish yet — generate the article first.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'postSaveNotConfirmed'      => __( 'WordPress did not confirm a saved post. Nothing has been marked as saved.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'nothingToSave'             => __( 'There is nothing to save yet — generate the article first.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'shortcodeSaveNotConfirmed' => __( 'WordPress did not confirm a saved shortcode. Nothing has been marked as saved.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
		);
	}
}
