<?php
/**
 * Post-update onboarding notice (REFACTOR.md §15.2).
 *
 * One dismissible admin notice on the first load after install or update:
 * what's new in 3.0, a link to the help page, and an optional Opace AI Hub
 * pitch. Dismissal is persisted per-site in
 * `ai_scribe_onboarding_dismissed` and the notice never re-shows once
 * dismissed.
 *
 * Also renders the one-time model-remap notice the migration records in
 * `ai_scribe_model_remap_notice` (§15.1: remap must be visible, never silent).
 * When Opace AI Hub is missing, this class owns a dedicated setup screen that
	 * installs the public Hub package after explicit consent, then waits for a
	 * second explicit administrator action before activation.
 *
 * @package AI_Scribe
 * @subpackage Core
 * @since 3.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Scribe_Onboarding_Notice {

	const DISMISSED_OPTION = 'ai_scribe_onboarding_dismissed';
	const REMAP_OPTION     = 'ai_scribe_model_remap_notice';
	const AJAX_ACTION      = 'ai_scribe_dismiss_notice';
	const NONCE_ACTION     = 'ai_scribe_dismiss_notice';
	const HUB_AJAX_ACTION  = 'ai_scribe_prepare_hub';
	const HUB_NONCE_ACTION = 'ai_scribe_prepare_hub';
	const HUB_SETUP_PAGE   = 'ai-scribe-setup';
	const HUB_SETUP_OPTION = 'ai_scribe_hub_setup_pending';
	const HUB_SETUP_VERSION_OPTION = 'ai_scribe_hub_setup_prompted_version';

	/** Text domain declared by the Opace AI Hub, used to find it on disk. */
	const HUB_TEXT_DOMAIN = 'opace-ai-prompt-library-api-hub';

	/** WordPress.org slug for the public Hub package. */
	const HUB_SLUG = 'opace-ai-prompt-library-api-hub';

	/** Canonical plugin file for the required hub. */
	const HUB_PLUGIN_FILE = 'opace-ai-prompt-library-api-hub/opace-ai-prompt-library-api-hub.php';

	/** Public listing shown before the administrator consents to installation. */
	const HUB_DIRECTORY_URL = 'https://wordpress.org/plugins/opace-ai-prompt-library-api-hub/';

	/**
	 * Wire the notice into wp-admin. Called once from the bootstrap.
	 */
	public static function register() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_notices', array( __CLASS__, 'render_notices' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'handle_dismiss' ) );
	}

	/**
	 * Whether the onboarding notice should render for the current request.
	 *
	 * @return bool
	 */
	public static function should_show_onboarding() {
		// The 3.0 welcome panel became stale and duplicated the dedicated Hub
		// setup in 3.2.32. Keep the method as a compatibility seam for older
		// callers, but retire the generic notice permanently.
		return false;
	}

	/**
	 * Whether the model-remap notice should render.
	 *
	 * @return bool
	 */
	public static function should_show_remap() {
		$remap = get_option( self::REMAP_OPTION );
		if ( empty( $remap ) || ! is_array( $remap ) || empty( $remap['from'] ) || empty( $remap['to'] ) ) {
			return false;
		}
		return function_exists( 'current_user_can' ) ? current_user_can( 'manage_options' ) : false;
	}

	/**
	 * Whether the Opace AI Hub is already loaded (active plugin).
	 *
	 * This is the canonical hub check for the whole plugin — the bootstrap's
	 * dependency guard, the Providers tab and the prompt reader all describe
	 * the same condition, so it lives in exactly one place.
	 *
	 * @return bool
	 */
	public static function hub_active() {
		if ( function_exists( 'ai_core' ) || class_exists( 'AI_Core' ) ) {
			return true;
		}

		// AI Scribe's directory can sort before the hub in active_plugins. In
		// that load order the hub is active but its API has not been defined yet.
		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( in_array( self::HUB_PLUGIN_FILE, $active_plugins, true ) ) {
			return true;
		}

		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
			$network_active = (array) get_site_option( 'active_sitewide_plugins', array() );
			return isset( $network_active[ self::HUB_PLUGIN_FILE ] );
		}

		return false;
	}

	/**
	 * Did the one-time migration find genuine 2.x data to carry over?
	 *
	 * Drives the onboarding copy (C-16-2): a fresh install must not claim
	 * settings "have carried over". Sites migrated before the flag existed
	 * fall back to a legacy-evidence check on an option 2.6.2 always wrote
	 * but v3 never creates by itself.
	 *
	 * @return bool
	 */
	private static function upgraded_from_v2() {
		$flag = get_option( AI_Scribe_Migration_Service::FROM_V2_OPTION );
		if ( 'yes' === $flag || 'no' === $flag ) {
			return 'yes' === $flag;
		}
		return false !== get_option( 'ab_check_Arr' ) || false !== get_option( 'ab_api_key' );
	}

	/**
	 * Register the minimal branded setup surface used while Hub is unavailable.
	 *
	 * @return void
	 */
	public static function register_hub_setup() {
		if ( ! is_admin() ) {
			return;
		}
		if ( AI_SCRIBE_VERSION !== get_option( self::HUB_SETUP_VERSION_OPTION ) ) {
			update_option( self::HUB_SETUP_OPTION, 'yes', false );
			update_option( self::HUB_SETUP_VERSION_OPTION, AI_SCRIBE_VERSION, false );
		}
		add_action( 'admin_menu', array( __CLASS__, 'register_hub_setup_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_to_hub_setup' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_hub_setup_assets' ) );
		add_action( 'wp_ajax_' . self::HUB_AJAX_ACTION, array( __CLASS__, 'handle_prepare_hub' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AI_SCRIBE_FILE ), array( __CLASS__, 'add_hub_setup_action_link' ) );
		add_action( 'after_plugin_row_' . plugin_basename( AI_SCRIBE_FILE ), array( __CLASS__, 'render_hub_setup_plugin_row' ), 10, 3 );
	}

	/**
	 * Add a direct setup action beneath AI-Scribe on the Plugins screen.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array
	 */
	public static function add_hub_setup_action_link( $links ) {
		if ( self::hub_active() ) {
			return $links;
		}

		$setup_link = sprintf(
			'<a class="ai-scribe-finish-setup" href="%1$s" aria-label="%2$s">%3$s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::HUB_SETUP_PAGE ) ),
			esc_attr__( 'Finish AI-Scribe setup', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			esc_html__( 'Finish setup', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' )
		);
		array_unshift( $links, $setup_link );

		return $links;
	}

	/**
	 * Render a native WordPress warning directly beneath AI-Scribe's plugin row.
	 *
	 * @param string $plugin_file Plugin file relative to the plugins directory.
	 * @param array  $plugin_data Plugin header data.
	 * @param string $status      Current plugin-list status view.
	 * @return void
	 */
	public static function render_hub_setup_plugin_row( $plugin_file, $plugin_data, $status ) {
		unset( $plugin_data, $status );

		if ( plugin_basename( AI_SCRIBE_FILE ) !== $plugin_file || self::hub_active() ) {
			return;
		}

		global $wp_list_table;
		$column_count = is_object( $wp_list_table ) && method_exists( $wp_list_table, 'get_column_count' ) ? $wp_list_table->get_column_count() : 4;
		$setup_url   = admin_url( 'admin.php?page=' . self::HUB_SETUP_PAGE );
		$message     = sprintf(
			'<strong>%1$s</strong> %2$s <a class="button button-small" href="%3$s">%4$s</a>',
			esc_html__( 'Opace AI Hub setup required.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			esc_html__( 'AI-Scribe needs its free companion plugin.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			esc_url( $setup_url ),
			esc_html__( 'Finish setup', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' )
		);
		$notice      = wp_get_admin_notice(
			$message,
			array(
				'type'               => 'warning',
				'additional_classes' => array( 'inline', 'notice-alt', 'ai-scribe-hub-row-notice' ),
			)
		);

		printf(
			'<tr class="plugin-update-tr active" id="ai-scribe-hub-setup-row"><td colspan="%1$d" class="plugin-update colspanchange">%2$s</td></tr>',
			(int) $column_count,
			wp_kses_post( $notice )
		);
	}

	/**
	 * Add the only AI-Scribe screen that is safe without Hub.
	 *
	 * @return void
	 */
	public static function register_hub_setup_menu() {
		add_menu_page(
			__( 'Complete AI-Scribe setup', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			__( 'AI Scribe', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'manage_options',
			self::HUB_SETUP_PAGE,
			array( __CLASS__, 'render_hub_setup_page' ),
			AI_SCRIBE_URL . 'assets/images/ai-scribe-menu-icon-20x20.png',
			30
		);
	}

	/**
	 * Continue an activation on the setup screen instead of the Plugins page.
	 *
	 * @return void
	 */
	public static function maybe_redirect_to_hub_setup() {
		if ( self::hub_active() ) {
			delete_option( self::HUB_SETUP_OPTION );
			return;
		}

		if ( 'yes' !== get_option( self::HUB_SETUP_OPTION ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing parameter; no state is changed from its value.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::HUB_SETUP_PAGE === $page ) {
			delete_option( self::HUB_SETUP_OPTION );
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::HUB_SETUP_PAGE ) );
		exit;
	}

	/**
	 * Locate an installed-but-inactive Opace AI Hub so the notice can offer a
	 * one-click activation. Not a second hub detector: hub_active() has
	 * already answered that question, this only resolves the plugin file.
	 *
	 * @return string Plugin file relative to the plugins directory, or ''.
	 */
	public static function installed_hub_file() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins() as $plugin_file => $plugin_data ) {
			if ( isset( $plugin_data['TextDomain'] ) && self::HUB_TEXT_DOMAIN === $plugin_data['TextDomain'] ) {
				return $plugin_file;
			}
			if ( 'opace-ai-prompt-library-api-hub.php' === basename( $plugin_file ) ) {
				return $plugin_file;
			}
		}
		return '';
	}

	/**
	 * Enqueue the dedicated Hub setup assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_hub_setup_assets( $hook ) {
		if ( 'toplevel_page_' . self::HUB_SETUP_PAGE !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ai-scribe-hub-setup',
			AI_SCRIBE_URL . 'assets/css/hub-setup.css',
			array(),
			AI_SCRIBE_VERSION
		);
		wp_enqueue_script(
			'ai-scribe-hub-setup',
			AI_SCRIBE_URL . 'assets/js/hub-setup.js',
			array(),
			AI_SCRIBE_VERSION,
			true
		);
		wp_localize_script(
			'ai-scribe-hub-setup',
			'aiScribeHubSetup',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::HUB_AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::HUB_NONCE_ACTION ),
				'strings' => array(
					'installing' => __( 'Installing Opace AI Hub…', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					'activating' => __( 'Activating Opace AI Hub…', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					'failed'     => __( 'Opace AI Hub could not be prepared. Please try again.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				),
			)
		);
	}

	/**
	 * Render the consent-led Hub setup screen.
	 *
	 * @return void
	 */
	public static function render_hub_setup_page() {
		$hub_file = self::installed_hub_file();
		$setup_action = '' !== $hub_file ? 'activate' : 'install';
		$can_prepare = 'activate' === $setup_action ? current_user_can( 'activate_plugins' ) : current_user_can( 'install_plugins' );
		?>
		<div class="wrap ai-scribe-hub-setup" data-testid="ai-scribe-hub-setup">
			<div class="ai-scribe-hub-setup__card">
				<img class="ai-scribe-hub-setup__logo" src="<?php echo esc_url( AI_SCRIBE_URL . 'assets/images/ai-scribe-logo.png' ); ?>" alt="<?php esc_attr_e( 'AI-Scribe', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
				<p class="ai-scribe-hub-setup__eyebrow"><?php esc_html_e( 'AI-Scribe setup', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
				<h1><?php esc_html_e( 'Connect AI-Scribe to Opace AI Hub', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h1>
				<p class="ai-scribe-hub-setup__lead"><?php esc_html_e( 'Opace AI Hub is the shared base layer for AI-Scribe and other Opace add-ons. It keeps provider connections, live model lists, reusable prompts and usage records in one place.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>

				<div class="ai-scribe-hub-setup__plugin">
					<div>
						<strong><?php esc_html_e( 'Opace AI Prompt Library & API Integration Hub', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></strong>
						<p><?php esc_html_e( 'Free companion plugin published by Opace Digital Agency on WordPress.org.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
					</div>
					<a href="<?php echo esc_url( self::HUB_DIRECTORY_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View plugin details', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></a>
				</div>

				<ul class="ai-scribe-hub-setup__benefits">
					<li><?php esc_html_e( 'Enter each provider key once for compatible Opace plugins.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></li>
					<li><?php esc_html_e( 'Keep your existing AI-Scribe prompts and settings during upgrade.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></li>
					<li><?php esc_html_e( 'Use WordPress Connectors optionally; separate provider plugins are not required for normal Hub use.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></li>
				</ul>

				<?php if ( $can_prepare ) : ?>
					<button type="button" class="button button-primary button-hero" id="ai-scribe-prepare-hub" data-action="<?php echo esc_attr( $setup_action ); ?>" data-testid="ai-scribe-prepare-hub">
						<?php echo 'activate' === $setup_action ? esc_html__( 'Activate Opace AI Hub and continue', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) : esc_html__( 'Install Opace AI Hub', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					</button>
				<?php else : ?>
					<p class="ai-scribe-hub-setup__permissions"><?php esc_html_e( 'A site administrator with permission to install and activate plugins must complete this setup.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
				<?php endif; ?>
				<p id="ai-scribe-hub-setup-status" class="ai-scribe-hub-setup__status" aria-live="polite"></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Install Hub from WordPress.org or activate an installed copy.
	 *
	 * @return void
	 */
	public static function handle_prepare_hub() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::HUB_NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to prepare Opace AI Hub.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ), 403 );
		}

		$setup_action = isset( $_POST['setup_action'] ) ? sanitize_key( wp_unslash( $_POST['setup_action'] ) ) : '';
		if ( ! in_array( $setup_action, array( 'install', 'activate' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose whether to install or activate Opace AI Hub.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ), 400 );
		}

		$hub_file = self::installed_hub_file();
		if ( 'install' === $setup_action ) {
			if ( ! current_user_can( 'install_plugins' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to install Opace AI Hub.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ), 403 );
			}
			if ( '' !== $hub_file ) {
				wp_send_json_success(
					array(
						'message'      => __( 'Opace AI Hub is installed. Activate it to finish setup.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'next_action'  => 'activate',
						'button_label' => __( 'Activate Opace AI Hub and continue', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					)
				);
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			$api = plugins_api( 'plugin_information', array(
				'slug'   => self::HUB_SLUG,
				'fields' => array( 'sections' => false ),
			) );
			if ( is_wp_error( $api ) || empty( $api->download_link ) ) {
				$message = is_wp_error( $api ) ? $api->get_error_message() : __( 'WordPress.org did not return the Opace AI Hub package.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' );
				wp_send_json_error( array( 'message' => $message ), 502 );
			}

			$skin = new WP_Ajax_Upgrader_Skin();
			$upgrader = new Plugin_Upgrader( $skin );
			$installed = $upgrader->install( $api->download_link );
			if ( is_wp_error( $installed ) || is_wp_error( $skin->result ) || $skin->get_errors()->has_errors() || true !== $installed ) {
				if ( is_wp_error( $installed ) ) {
					$message = $installed->get_error_message();
				} elseif ( is_wp_error( $skin->result ) ) {
					$message = $skin->result->get_error_message();
				} elseif ( $skin->get_errors()->has_errors() ) {
					$message = $skin->get_error_messages();
				} else {
					$message = __( 'WordPress could not install Opace AI Hub. Check filesystem access and try again.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' );
				}
				wp_send_json_error( array( 'message' => $message ), 500 );
			}

			wp_clean_plugins_cache( true );
			$hub_file = self::installed_hub_file();
			if ( '' === $hub_file ) {
				wp_send_json_error( array( 'message' => __( 'Opace AI Hub installed, but its plugin file could not be found.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ), 500 );
			}

			wp_send_json_success(
				array(
					'message'      => __( 'Opace AI Hub installed. Activate it to finish setup.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					'next_action'  => 'activate',
					'button_label' => __( 'Activate Opace AI Hub and continue', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				)
			);
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to activate Opace AI Hub.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ), 403 );
		}
		if ( '' === $hub_file ) {
			wp_send_json_error( array( 'message' => __( 'Install Opace AI Hub before activating it.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ), 409 );
		}

		$result = activate_plugin( $hub_file, '', is_multisite() && is_network_admin() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		delete_option( self::HUB_SETUP_OPTION );
		wp_send_json_success(
			array(
				'message'  => __( 'Opace AI Hub is ready. Opening AI-Scribe settings…', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'redirect' => admin_url( 'admin.php?page=ai_scribe_settings&hub_setup=complete' ),
			)
		);
	}

	/**
	 * Enqueue the notice stylesheet + dismissal script only when a notice
	 * will actually render (the notice can appear on any admin screen, so
	 * the main plugin bundle is not guaranteed to be present).
	 */
	public static function enqueue_assets() {
		if ( ! self::should_show_onboarding() && ! self::should_show_remap() ) {
			return;
		}
		wp_enqueue_style(
			'ai-scribe-onboarding-notice',
			AI_SCRIBE_URL . 'assets/css/onboarding-notice.css',
			array(),
			AI_SCRIBE_VERSION
		);
		wp_enqueue_script(
			'ai-scribe-onboarding-notice',
			AI_SCRIBE_URL . 'assets/js/onboarding-notice.js',
			array(),
			AI_SCRIBE_VERSION,
			true
		);
	}

	/**
	 * admin_notices callback: onboarding first, then the remap notice.
	 */
	public static function render_notices() {
		if ( self::should_show_remap() ) {
			self::render_remap_notice();
		}
		if ( self::should_show_onboarding() ) {
			self::render_onboarding_notice();
		}
	}

	/**
	 * The §15.2 what's-new notice.
	 */
	private static function render_onboarding_notice() {
		$help_url = admin_url( 'admin.php?page=ai_scribe_help' );
		$nonce    = wp_create_nonce( self::NONCE_ACTION );
		?>
		<div class="notice notice-info is-dismissible ai-scribe-notice ai-scribe-onboarding-notice"
			id="ai-scribe-onboarding-notice"
			data-ai-scribe-notice="onboarding"
			data-ai-scribe-nonce="<?php echo esc_attr( $nonce ); ?>"
			data-testid="ai-scribe-onboarding-notice">
			<div class="ai-scribe-notice__body">
				<p class="ai-scribe-notice__title">
					<strong><?php esc_html_e( 'Welcome to AI-Scribe 3.0', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></strong>
				</p>
				<p class="ai-scribe-notice__intro">
					<?php if ( self::upgraded_from_v2() ) : ?>
						<?php esc_html_e( 'Your settings, prompts, languages and saved shortcodes have carried over.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'You are ready to go: sensible defaults and a full prompt library are already set up for you.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					<?php endif; ?>
					<?php esc_html_e( 'Your provider API keys are managed centrally in Opace AI Hub.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
				</p>
				<?php
				/*
				 * The five headline changes used to be a bulleted list here. A
				 * welcome notice sits above every screen until it is dismissed,
				 * and at that length it took a third of the viewport and pushed
				 * the wizard below the fold. The detail lives on the Help screen,
				 * one click away behind "See the full guide".
				 */
				?>
				<p class="ai-scribe-notice__summary">
					<?php esc_html_e( 'New in 3.0: three API providers plus WordPress core AI, Express mode, whole-article context, live model lists and per-step costs.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
				</p>
				<p class="ai-scribe-notice__actions">
					<a class="button button-primary" href="<?php echo esc_url( $help_url ); ?>">
						<?php esc_html_e( 'See the full guide', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					</a>
					<button type="button" class="button-link ai-scribe-notice__dismiss-link" data-ai-scribe-dismiss="onboarding">
						<?php esc_html_e( 'Dismiss', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					</button>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * The §15.1 model-remap notice: a retired 2.6.2 model id was replaced
	 * during migration and the user must be able to see that it happened.
	 */
	private static function render_remap_notice() {
		$remap = get_option( self::REMAP_OPTION );
		$nonce = wp_create_nonce( self::NONCE_ACTION );
		$legacy_default = ( isset( $remap['reason'] ) && 'legacy_default' === $remap['reason'] )
			|| ( ! isset( $remap['reason'] ) && 'gpt-4o-mini' === $remap['from'] );
		?>
		<div class="notice notice-warning is-dismissible ai-scribe-notice ai-scribe-remap-notice"
			id="ai-scribe-remap-notice"
			data-ai-scribe-notice="model_remap"
			data-ai-scribe-nonce="<?php echo esc_attr( $nonce ); ?>"
			data-testid="ai-scribe-remap-notice">
			<p>
				<strong><?php esc_html_e( 'AI-Scribe model updated:', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></strong>
				<?php
				if ( $legacy_default ) {
					printf(
						/* translators: 1: old default model id, 2: Hub default model id */
						esc_html__( 'the untouched legacy default %1$s was updated to match Opace AI Hub, so %2$s is now selected. You can pick a different model under AI-Scribe → Settings.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'<code>' . esc_html( $remap['from'] ) . '</code>',
						'<code>' . esc_html( $remap['to'] ) . '</code>'
					);
				} else {
					printf(
						/* translators: 1: retired model id, 2: replacement model id */
						esc_html__( 'your previously selected model %1$s has been retired by its provider, so %2$s is now selected. You can pick a different model under AI-Scribe → Settings.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'<code>' . esc_html( $remap['from'] ) . '</code>',
						'<code>' . esc_html( $remap['to'] ) . '</code>'
					);
				}
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * AJAX: persist a dismissal. Nonce + capability gated.
	 */
	public static function handle_dismiss() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'code' => 'forbidden' ), 403 );
			return;
		}
		$notice = isset( $_POST['notice'] ) ? sanitize_key( wp_unslash( $_POST['notice'] ) ) : '';
		if ( 'onboarding' === $notice ) {
			update_option( self::DISMISSED_OPTION, AI_SCRIBE_VERSION, false );
			wp_send_json_success( array( 'dismissed' => 'onboarding' ) );
			return;
		}
		if ( 'model_remap' === $notice ) {
			delete_option( self::REMAP_OPTION );
			wp_send_json_success( array( 'dismissed' => 'model_remap' ) );
			return;
		}
		wp_send_json_error( array( 'code' => 'unknown_notice' ), 400 );
	}
}
