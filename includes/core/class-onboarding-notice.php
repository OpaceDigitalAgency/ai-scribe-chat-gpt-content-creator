<?php
/**
 * Post-update onboarding notice (REFACTOR.md §15.2).
 *
 * One dismissible admin notice on the first load after install or update:
 * what's new in 3.0, a link to the help page, and an optional Opace AI Hub
 * pitch. The hub install button follows WordPress dependency availability and
 * remains filterable through `ai_scribe_hub_install_cta`. Dismissal
 * is persisted per-site in `ai_scribe_onboarding_dismissed` and the notice
 * never re-shows once dismissed.
 *
 * Also renders the one-time model-remap notice the migration records in
 * `ai_scribe_model_remap_notice` (§15.1: remap must be visible, never silent),
 * and the "Opace AI Hub required" notice the bootstrap falls back to when the hub
 * is missing — Opace AI Hub became a hard dependency in 3.0.
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

	/** Text domain declared by the Opace AI Hub, used to find it on disk. */
	const HUB_TEXT_DOMAIN = 'opace-ai-prompt-library-api-hub';

	/** wordpress.org slug, and what the Requires Plugins header resolves against. */
	const HUB_SLUG = 'opace-ai-prompt-library-api-hub';

	/** Canonical plugin file for the required hub. */
	const HUB_PLUGIN_FILE = 'opace-ai-prompt-library-api-hub/opace-ai-prompt-library-api-hub.php';

	/** Where a user without the hub can get it. */
	const HUB_HOME_URL = 'https://opace.agency/services/web-design/wordpress-development/';

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
		if ( get_option( self::DISMISSED_OPTION ) ) {
			return false;
		}
		return function_exists( 'current_user_can' ) ? current_user_can( 'manage_options' ) : false;
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
	 * Wire the "Opace AI Hub required" notice. The bootstrap calls this in place of
	 * register() when the hub is missing: in that state AI-Scribe registers no
	 * admin pages and no AJAX endpoints, so this notice is its entire admin
	 * surface and must say plainly what to do next.
	 *
	 * @return void
	 */
	public static function register_hub_required() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_notices', array( __CLASS__, 'render_hub_required_notice' ) );
	}

	/**
	 * Locate an installed-but-inactive Opace AI Hub so the notice can offer a
	 * one-click activation. Not a second hub detector: hub_active() has
	 * already answered that question, this only resolves the plugin file.
	 *
	 * @return string Plugin file relative to the plugins directory, or ''.
	 */
	private static function installed_hub_file() {
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
	 * The dependency notice. Deliberately not dismissible: nothing in the
	 * plugin works until it is resolved.
	 *
	 * @return void
	 */
	public static function render_hub_required_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$hub_file = self::installed_hub_file();
		?>
		<div class="notice notice-error ai-scribe-hub-required-notice" data-testid="ai-scribe-hub-required-notice">
			<p>
				<strong><?php esc_html_e( 'Opace AI Scribe needs the Opace AI Hub plugin.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'Your provider API keys, the model lists and the usage statistics all live in Opace AI Hub, so AI-Scribe cannot generate anything without it. Its screens stay hidden until Opace AI Hub is active.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
			</p>
			<p>
				<?php if ( '' !== $hub_file ) : ?>
					<a class="button button-primary"
						href="<?php echo esc_url( self::hub_activate_url( $hub_file ) ); ?>"
						data-testid="ai-scribe-hub-activate-cta">
						<?php esc_html_e( 'Activate Opace AI Hub', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					</a>
				<?php elseif ( self::hub_install_cta_enabled() && current_user_can( 'install_plugins' ) ) : ?>
					<a class="button button-primary"
						href="<?php echo esc_url( self::hub_install_url() ); ?>"
						data-testid="ai-scribe-hub-install-cta">
						<?php esc_html_e( 'Install Opace AI Hub now', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( self_admin_url( 'plugin-install.php?tab=upload' ) ); ?>">
						<?php esc_html_e( 'Upload a copy instead', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					</a>
				<?php else : ?>
					<a class="button button-primary"
						href="<?php echo esc_url( self::HUB_HOME_URL ); ?>"
						target="_blank" rel="noopener noreferrer"
						data-testid="ai-scribe-hub-get-cta">
						<?php esc_html_e( 'Ask about Opace AI Hub', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( self_admin_url( 'plugin-install.php?tab=upload' ) ); ?>">
						<?php esc_html_e( 'Upload and install it', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					</a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Nonced activation URL for the hub. Capability is checked by the caller
	 * and again by plugins.php itself.
	 *
	 * @param string $hub_file Plugin file relative to the plugins directory.
	 * @return string
	 */
	private static function hub_activate_url( $hub_file ) {
		return wp_nonce_url(
			self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $hub_file ) ),
			'activate-plugin_' . $hub_file
		);
	}

	/**
	 * Whether to render the WordPress.org hub install button.
	 *
	 * WordPress resolves the declared dependency through its own plugin API.
	 * The button therefore appears automatically once the permanent Opace AI Hub
	 * slug has a public listing, without shipping a second AI Scribe update.
	 *
	 * @return bool
	 */
	public static function hub_install_cta_enabled() {
		if ( self::hub_active() ) {
			return false;
		}

		$available = false;
		if ( class_exists( 'WP_Plugin_Dependencies' ) ) {
			WP_Plugin_Dependencies::initialize();
			$available = ! empty( WP_Plugin_Dependencies::get_dependency_data( self::HUB_SLUG ) );
		}

		return (bool) apply_filters( 'ai_scribe_hub_install_cta', $available );
	}

	/**
	 * Nonced one-click install URL for the hub from wordpress.org.
	 *
	 * @return string
	 */
	private static function hub_install_url() {
		return wp_nonce_url(
			self_admin_url( 'update.php?action=install-plugin&plugin=' . self::HUB_SLUG ),
			'install-plugin_' . self::HUB_SLUG
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
					<?php if ( self::hub_active() ) : ?>
						<?php esc_html_e( 'Your provider API keys are managed centrally in Opace AI Hub.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					<?php elseif ( self::hub_install_cta_enabled() ) : ?>
						<?php esc_html_e( 'The free Opace AI Hub stores your provider keys once and shares them across all Opace AI plugins.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					<?php endif; ?>
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
					<?php esc_html_e( 'New in 3.0: four providers, Express mode, whole-article context, live model lists and per-step costs.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
				</p>
				<p class="ai-scribe-notice__actions">
					<a class="button button-primary" href="<?php echo esc_url( $help_url ); ?>">
						<?php esc_html_e( 'See the full guide', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					</a>
					<?php if ( self::hub_install_cta_enabled() && current_user_can( 'install_plugins' ) ) : ?>
						<a class="button" href="<?php echo esc_url( self::hub_install_url() ); ?>"
							data-testid="ai-scribe-hub-install-cta">
							<?php esc_html_e( 'Install Opace AI Hub', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
						</a>
					<?php endif; ?>
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
