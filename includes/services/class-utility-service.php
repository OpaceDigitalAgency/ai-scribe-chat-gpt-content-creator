<?php
/**
 * AI Scribe Utility Service
 *
 * Handles utility functions including debugging, activation/deactivation,
 * nonce management, and AJAX testing functionality.
 *
 * @package AI_Scribe
 * @subpackage Services
 * @since 2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once AI_SCRIBE_DIR . 'includes/core/class-base-service.php'; // v3 layout: base classes live in includes/core/

/**
 * Class AI_Scribe_Utility_Service
 *
 * Provides utility functions for the AI Scribe plugin including:
 * - Debug logging with security checks
 * - Plugin activation and deactivation
 * - Nonce management and refresh
 * - AJAX testing handlers
 * - Token estimation utilities
 */
class AI_Scribe_Utility_Service extends AI_Scribe_Base_Service {

	/**
	 * Initialize the utility service
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 * @param AI_Scribe_Config_Manager $config Configuration manager
	 */
	public function __construct( $logger = null, $config = null ) {
		// Create minimal logger if none provided
		if ( ! $logger && class_exists( 'AI_Scribe_Logger' ) ) {
			$logger = new AI_Scribe_Logger();
		}

		// Create minimal config if none provided
		if ( ! $config && class_exists( 'AI_Scribe_Config_Manager' ) && $logger ) {
			$config = new AI_Scribe_Config_Manager( $logger );
		}

		if ( $logger && $config ) {
			parent::__construct( $logger, $config );
		}

		$this->debug_log( 'AI Scribe Utility Service: Initialized' );
	}

	/**
	 * Validate the utility service configuration
	 *
	 * @return bool True if service is properly configured
	 */
	public function validate_service() {
		// During early initialization, WordPress functions may not be available yet
		// This is acceptable - the service will work once WordPress is fully loaded

		// Check if WordPress functions are available (optional during early init)
		if ( function_exists( 'wp_create_nonce' ) && function_exists( 'current_user_can' ) ) {
			// WordPress is fully loaded - check database access
			global $wpdb;
			if ( ! $wpdb ) {
				return 'Database not accessible';
			}
		}
		// If WordPress functions aren't available yet, that's OK during early initialization

		return true;
	}

	/**
	 * Check if debug mode is enabled for PHP logging
	 * Debug mode is disabled by default for production safety
	 *
	 * @return bool True if debug mode is enabled and user has permissions
	 */
	private function is_debug_mode_enabled() {
		// 🚨 CENTRALIZED DEBUG CONTROL - WordPress.org Production Ready
		// Set to false for production release to WordPress.org
		$debug_enabled = defined( 'AI_SCRIBE_DEBUG_MODE' ) ? AI_SCRIBE_DEBUG_MODE : false;

		// Allow override via WordPress constant for development
		if ( defined( 'AI_SCRIBE_DEBUG_MODE' ) ) {
			$debug_enabled = AI_SCRIBE_DEBUG_MODE;
		}

		// During early WordPress loading, user functions may not be available
		// Only check user permissions if WordPress is fully loaded
		if ( ! function_exists( 'current_user_can' ) || ! function_exists( 'wp_get_current_user' ) ) {
			return false; // Disable debugging during early initialization
		}

		// Only allow debugging for admin users
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return $debug_enabled;
	}

	/**
	 * Get the global debug mode status for the entire plugin
	 * This is the single source of truth for all debug settings
	 *
	 * @return bool True if debug mode is enabled globally
	 */
	public static function is_global_debug_enabled() {
		// 🚨 MASTER DEBUG CONTROL - Single source of truth
		// Set to false for WordPress.org production release
		$global_debug = defined( 'AI_SCRIBE_DEBUG_MODE' ) ? AI_SCRIBE_DEBUG_MODE : false;

		// Debug mode is now controlled by the constant check above

		// Additional safety: Only enable for admin users
		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return $global_debug;
	}

	/**
	 * Debug-aware error logging
	 * Only logs when debug mode is enabled and user has proper permissions
	 * Safe to call during early WordPress initialization
	 *
	 * @param string $message The message to log
	 */
	public function debug_log( $message ) {
		// During early WordPress loading, skip all debug logging to prevent errors
		if ( ! function_exists( 'wp_get_current_user' ) || ! function_exists( 'current_user_can' ) ) {
			return; // Silently skip during early initialization
		}

		if ( $this->is_debug_mode_enabled() ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Explicitly debug-gated diagnostic output.
			error_log( $message );
		}
	}

	/**
	 * Plugin activation handler
	 * Creates the custom database table and sets default options when the plugin is activated
	 */
	public function activate() {
		global $wpdb, $table_prefix;

		$wp_article = $table_prefix . 'article_builder';

		// Check if the table exists
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off activation schema check for the plugin-owned table.
		if (
			$wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$wpdb->esc_like( $wp_article )
				)
			) != $wp_article
		) {
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$charset_collate = $wpdb->get_charset_collate();

			$q = "CREATE TABLE $wp_article (
                id int(20) NOT NULL AUTO_INCREMENT,
                title text DEFAULT NULL,
                heading text DEFAULT NULL,
                keyword text DEFAULT NULL,
                intro text DEFAULT NULL,
                tagline text DEFAULT NULL,
                article text DEFAULT NULL,
                conclusion longtext DEFAULT NULL,
                qna longtext DEFAULT NULL,
                metadata longtext DEFAULT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $q );
		}

		// Call the method to set default options
		$this->set_default_options();

		// Add the delete data on uninstall option if not already set
		if ( get_option( 'ai_scribe_delete_data_on_uninstall' ) === false ) {
			add_option( 'ai_scribe_delete_data_on_uninstall', 'no' );
		}
	}

	/**
	 * Plugin deactivation handler
	 * Placeholder function for when the plugin is deactivated
	 */
	public function deactivate() {
		// Currently no deactivation logic needed
		$this->debug_log( 'AI Scribe: Plugin deactivated' );
	}

	/**
	 * Set default plugin options during activation
	 */
	private function set_default_options() {
		$contentsetting = array(
			'language'          => 'English',
			'writing_style'     => 'Business',
			'writing_tone'      => 'Professional',
			'number_of_heading' => '5',
			'Heading_tag'       => 'H2',
			'check_Arr'         => array(
				'addQNA'         => 'addQNA',
				'addinsertHyper' => 'addinsertHyper',
				'addinsertToc'   => 'addinsertToc',
				'addkeywordBold' => 'addkeywordBold',
			),
		);

		$enginesetting = array(
			'model'            => 'gpt-4o-mini',
			'temp'             => 0.5,
			'top_p'            => 0.5,
			'freq_pent'        => 0.2,
			'Presence_penalty' => 0.2,
			'n'                => 1,
		);

		// Update options if they don't exist
		if ( ! get_option( 'ab_gpt_content_settings' ) ) {
			update_option( 'ab_gpt_content_settings', $contentsetting );
		}

		if ( ! get_option( 'ab_gpt_ai_engine_settings' ) ) {
			update_option( 'ab_gpt_ai_engine_settings', $enginesetting );
		}
	}

	/**
	 * Initialize nonce for security
	 *
	 * @return string Generated nonce
	 */
	public function initialize_nonce() {
		return wp_create_nonce( 'ai_scribe_nonce' );
	}

	/**
	 * Simple test AJAX handler to verify AJAX system is working
	 */
	public function test_ajax_handler() {
		wp_send_json_success(
			array(
				'message'   => 'Test AJAX handler is working!',
				'timestamp' => current_time( 'mysql' ),
			)
		);
	}

}
