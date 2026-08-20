<?php
/*
 * Contributors:        OPACE LTD
 * Plugin Name:         Opace AI Scribe: SEO Content Creator & Humanizer for OpenAI, Anthropic & Gemini
 * Description:         AI SEO content creator and humaniser for OpenAI, Anthropic and Gemini models. 11-step wizard, editable prompts, Express mode. Works with Yoast, Rank Math, AIOSEO & SEOPress.
 * Plugin URI:          https://opace.agency/services/web-design/wordpress-development/
 * Text Domain:         ai-scribe-the-chatgpt-powered-seo-content-creation-wizard
 * Author URI:          https://opace.agency
 * Author:              Opace Digital Agency
 * Requires at least:   6.5
 * Tested up to:        7.1
 * Requires PHP:        7.4
 * Requires Plugins:    opace-ai-prompt-library-api-hub
 * Version:             3.2.25
 * License:             GPL-3.0
 * License URI:         http://www.gnu.org/licenses/gpl-3.0.txt
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The plugin uses the external AI services you configure (OpenAI, Anthropic,
 * Google Gemini). Please review each provider's terms and privacy policy.
 *
 * Opace AI Hub is required, not optional: from 3.0 the provider API keys, the model
 * lists and the usage statistics all live in the hub, so without it there is
 * nowhere to configure a model. `Requires Plugins` blocks activation on
 * WordPress 6.5+; the runtime guard below covers the hub being deactivated
 * afterwards.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'AI_SCRIBE_VERSION', '3.2.25' ); // Used for cache busting on every enqueue
if ( ! defined( 'AI_SCRIBE_VER' ) ) {
	define( 'AI_SCRIBE_VER', AI_SCRIBE_VERSION ); // Back-compat alias used by copied v4 services
}
define( 'AI_SCRIBE_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_SCRIBE_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_SCRIBE_FILE', __FILE__ );

// Debug flag (was debug-config.php in v4; keep off by default, overridable in wp-config.php)
if ( ! defined( 'AI_SCRIBE_DEBUG_MODE' ) ) {
	define( 'AI_SCRIBE_DEBUG_MODE', defined( 'WP_DEBUG' ) && WP_DEBUG );
}

/**
 * Debug-gated diagnostic logging. No-op unless AI_SCRIBE_DEBUG_MODE is true
 * (which defaults to WP_DEBUG). Keeps production sites free of log noise and
 * guarantees nothing sensitive is written on live installs.
 *
 * @param string $message Diagnostic message. Never pass secrets.
 * @return void
 */
function ai_scribe_debug_log( $message ) {
	if ( AI_SCRIBE_DEBUG_MODE ) {
		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-gated, off by default in production.
	}
}

// Minimum PHP version check
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>Opace AI Scribe:</strong> This plugin requires PHP 7.4 or higher. You are running PHP ' . esc_html( PHP_VERSION ) . '.';
			echo '</p></div>';
		}
	);
	return;
}

/*
 * The Opace AI Hub library is NOT bundled here.
 *
 * It used to be, as a fallback. Because the hub plugin's directory sorts
 * before this one, its copy always won and the bundled copy never ran — so
 * the two drifted apart unnoticed, and a provider fix applied to one of them
 * silently did nothing. That is exactly how Gemini shipped unable to generate
 * at all: the fix went into the copy that never loads.
 *
 * Opace AI Hub is a hard dependency (see the Requires Plugins header and the guard
 * below), so there is one copy of the library, in the hub, and no way for a
 * fix to land in the wrong one.
 */

/**
 * Autoloader for AI_Scribe_* classes.
 *
 * v3 layout splits includes/ into core/, services/, adapters/ and ajax/
 * (see REFACTOR.md section 6), so each subdirectory is searched in turn.
 */
spl_autoload_register(
	function ( $class_name ) {
		if ( strpos( $class_name, 'AI_Scribe_' ) !== 0 ) {
			return;
		}

		$class_file = 'class-' . str_replace( '_', '-', strtolower( substr( $class_name, 10 ) ) ) . '.php';

		foreach ( array( 'core', 'adapters', 'services', 'ajax', 'abilities' ) as $subdir ) {
			$file_path = AI_SCRIBE_DIR . 'includes/' . $subdir . '/' . $class_file;
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
				return;
			}
		}
	}
);

/**
 * Main plugin bootstrap (singleton). Delegates everything to the
 * ServiceContainer via AI_Scribe_Plugin_Initializer.
 */
final class AI_Scribe {

	/** @var AI_Scribe|null */
	private static $instance = null;

	/** @var AI_Scribe_Plugin_Initializer|null */
	private $initializer = null;

	private function __construct() {
		try {
			$this->initializer = new AI_Scribe_Plugin_Initializer( AI_SCRIBE_FILE, AI_SCRIBE_VERSION );

			// Global reference used by the AJAX handler service as a container fallback
			global $ai_scribe_plugin_initializer;
			$ai_scribe_plugin_initializer = $this->initializer;

			add_action( 'init', array( $this, 'load_textdomain' ) );
		} catch ( Exception $e ) {
			add_action(
				'admin_notices',
				function () use ( $e ) {
					echo '<div class="notice notice-error"><p>';
					echo '<strong>Opace AI Scribe:</strong> Plugin initialisation failed: ' . esc_html( $e->getMessage() );
					echo '</p></div>';
				}
			);
			ai_scribe_debug_log( 'AI Scribe: initialisation failed: ' . $e->getMessage() );
		}
	}

	public static function getInstance(): AI_Scribe {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function load_textdomain(): void {
		// Intentionally empty: translations load automatically from wp.org
		// language packs since WP 4.6 (text domain matches the plugin slug).
	}

	public function get_initializer() {
		return $this->initializer;
	}

	public function get_version(): string {
		return AI_SCRIBE_VERSION;
	}

	public function is_initialized(): bool {
		return $this->initializer && $this->initializer->is_initialized();
	}
}

/**
 * Access the plugin instance.
 */
function ai_scribe(): AI_Scribe {
	return AI_Scribe::getInstance();
}

/**
 * Access the global service container (used by services as a fallback).
 *
 * @return AI_Scribe_Service_Container|null
 */
function ai_scribe_get_container() {
	$plugin = ai_scribe();
	if ( $plugin->get_initializer() ) {
		return $plugin->get_initializer()->get_container();
	}
	return null;
}

/**
 * Hard dependency guard: no Opace AI Hub, no AI-Scribe.
 *
 * Detection is deliberately the single mechanism used everywhere else
 * (AI_Scribe_Onboarding_Notice::hub_active()). When the hub is missing or
 * deactivated we register one explanatory notice and stop: no admin menu,
 * no wizard, no AJAX endpoints, nothing half-rendered. The schema and
 * migration checks below are safe to skip because they re-run on the first
 * admin request after the hub comes back.
 */
// Front-end meta output for posts generated with no SEO plugin active
// (C-2-4/L-26). Registered before the hub guard on purpose: already-saved
// posts must keep their title/description even while Opace AI Hub is deactivated.
AI_Scribe_Frontend_Meta::register();

// Generated posts carry a narrowly scoped reading-layout stylesheet. Theme
// typography remains in charge; this only prevents edge-touching prose and
// oversized images when a theme supplies no useful content defaults.
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! is_singular() || '1' !== (string) get_post_meta( get_queried_object_id(), '_ai_scribe_generated', true ) ) {
			return;
		}
		wp_enqueue_style( 'ai-scribe-article', AI_SCRIBE_URL . 'assets/css/frontend-article.css', array(), AI_SCRIBE_VERSION );
	}
);

if ( ! AI_Scribe_Onboarding_Notice::hub_active() ) {
	AI_Scribe_Onboarding_Notice::register_hub_required();
	return;
}

// Activation: conversations table + one-time 2.6.2 option migration
register_activation_hook(
	__FILE__,
	function () {
		AI_Scribe_Conversation_Service::install_table();
		$container      = ai_scribe_get_container();
		$prompt_manager = ( $container && $container->has( 'prompt_manager' ) ) ? $container->get( 'prompt_manager' ) : null;
		$config_manager = ( $container && $container->has( 'config' ) ) ? $container->get( 'config' ) : null;
		AI_Scribe_Migration_Service::maybe_migrate( $prompt_manager, $config_manager );
	}
);

// Upgrades without re-activation (updated in place): keep schema + options
// current. Hooked on `init` rather than `admin_init` deliberately: admin_init
// fires AFTER admin_menu, so on the first admin request following an update
// (or an activation that could not run its hook) the menu and services would
// otherwise build against a stale schema for that one request (C-14-1).
add_action(
	'init',
	function () {
		if ( ! is_admin() ) {
			return;
		}
		if ( get_option( 'ai_scribe_conversations_schema' ) !== AI_Scribe_Conversation_Service::SCHEMA_VERSION ) {
			AI_Scribe_Conversation_Service::install_table();
		}
		if ( get_option( AI_Scribe_Migration_Service::MIGRATED_OPTION ) !== AI_Scribe_Migration_Service::MIGRATION_VERSION ) {
			$container      = ai_scribe_get_container();
			$prompt_manager = ( $container && $container->has( 'prompt_manager' ) ) ? $container->get( 'prompt_manager' ) : null;
			$config_manager = ( $container && $container->has( 'config' ) ) ? $container->get( 'config' ) : null;
			AI_Scribe_Migration_Service::maybe_migrate( $prompt_manager, $config_manager );
		}
	}
);

// Post-update onboarding + model-remap notices (REFACTOR.md §15.1/§15.2)
AI_Scribe_Onboarding_Notice::register();

// Prompt Library tab: applying an Opace AI Hub prompt to a wizard step.
AI_Scribe_Hub_Prompt_Reader::register();

// Boot
ai_scribe();
