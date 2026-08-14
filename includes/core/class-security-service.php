<?php
/**
 * Security Service Class for AI-Scribe Plugin
 *
 * Handles security-related functionality including nonce management,
 * user authentication, and security validation.
 *
 * @package AI_Scribe
 * @subpackage Services
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Security_Service
 *
 * Provides security functionality for the AI-Scribe plugin including
 * nonce management, user authentication, and security validation.
 */
class AI_Scribe_Security_Service extends AI_Scribe_Base_Service {

	/**
	 * WordPress adapter instance
	 *
	 * @var AI_Scribe_WordPress_Adapter
	 */
	protected $wordpress_adapter;

	/**
	 * Constructor
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 * @param AI_Scribe_Config_Manager $config Configuration manager
	 * @param AI_Scribe_WordPress_Adapter $wordpress_adapter WordPress adapter
	 */
	public function __construct( $logger, $config, $wordpress_adapter ) {
		parent::__construct( $logger, $config, 'security_service' );
		$this->wordpress_adapter = $wordpress_adapter;
	}

	/**
	 * Initialize service
	 *
	 * @return void
	 */
	protected function initialize() {
		$this->log_debug( 'Security service initializing' );

		// Validate dependencies
		if ( ! $this->wordpress_adapter ) {
			$this->log_error( 'WordPress adapter not provided to Security Service' );
		}
	}

	/**
	 * Validate service dependencies and configuration
	 *
	 * @return array Service validation status
	 */
	public function validate_service() {
		$validation_result = array(
			'dependencies_resolved'        => isset( $this->wordpress_adapter ),
			'configuration_valid'          => true,
			'external_services_accessible' => true,
		);

		if ( ! $validation_result['dependencies_resolved'] ) {
			$this->log_error( 'Security service validation failed: WordPress adapter missing' );
		}

		return $validation_result;
	}

	/**
	 * Refresh AJAX nonce for security
	 *
	 * Handles AJAX request to refresh the security nonce for long-running sessions.
	 * Validates user authentication before generating new nonce.
	 *
	 * @return void Sends JSON response directly
	 */
	public function refresh_nonce() {
		$this->log_debug( 'Nonce refresh requested' );

		try {
			// Check if user is logged in
			if ( ! is_user_logged_in() ) {
				$this->log_warning( 'Nonce refresh attempted by unauthenticated user' );
				wp_send_json_error( array( 'msg' => 'Unauthorized request.' ) );
				return;
			}

			// Generate new nonce
			$new_nonce = wp_create_nonce( 'ai_scribe_nonce' );

			if ( ! $new_nonce ) {
				$this->log_error( 'Failed to generate new nonce' );
				wp_send_json_error( array( 'msg' => 'Failed to generate security token.' ) );
				return;
			}

			$this->log_debug( 'New nonce generated successfully' );
			wp_send_json_success( array( 'nonce' => $new_nonce ) );

		} catch ( Exception $e ) {
			$error_response = $this->handle_error(
				'Error refreshing nonce',
				$e,
				array( 'user_id' => get_current_user_id() )
			);
			wp_send_json_error( array( 'msg' => 'Security token refresh failed.' ) );
		}
	}

	/**
	 * Extend nonce lifetime for complex operations
	 *
	 * Returns extended nonce lifetime in seconds for operations that may
	 * take longer than the default WordPress nonce lifetime.
	 *
	 * @return int Extended nonce lifetime in seconds (24 hours)
	 */
	public function extend_nonce_life() {
		$extended_lifetime = 24 * HOUR_IN_SECONDS;

		$this->log_debug(
			'Nonce lifetime extended',
			array(
				'lifetime_hours'   => 24,
				'lifetime_seconds' => $extended_lifetime,
			)
		);

		return $extended_lifetime;
	}

	/**
	 * Verify nonce for AJAX requests
	 *
	 * @param string $nonce Nonce to verify
	 * @param string $action Nonce action (default: 'ai_scribe_nonce')
	 * @return bool True if nonce is valid, false otherwise
	 */
	public function verify_nonce( $nonce, $action = 'ai_scribe_nonce' ) {
		if ( empty( $nonce ) ) {
			$this->log_warning( 'Empty nonce provided for verification' );
			return false;
		}

		$is_valid = wp_verify_nonce( $nonce, $action );

		if ( ! $is_valid ) {
			$this->log_warning(
				'Nonce verification failed',
				array(
					'action'  => $action,
					'user_id' => get_current_user_id(),
				)
			);
		} else {
			$this->log_debug( 'Nonce verified successfully', array( 'action' => $action ) );
		}

		return (bool) $is_valid;
	}

	/**
	 * Check if current user has required capability
	 *
	 * @param string $capability Required capability (default: 'manage_options')
	 * @return bool True if user has capability, false otherwise
	 */
	public function check_user_capability( $capability = 'manage_options' ) {
		$has_capability = current_user_can( $capability );

		if ( ! $has_capability ) {
			$this->log_warning(
				'User capability check failed',
				array(
					'required_capability' => $capability,
					'user_id'             => get_current_user_id(),
				)
			);
		}

		return $has_capability;
	}

	/**
	 * Validate AJAX request security
	 *
	 * Comprehensive security validation for AJAX requests including
	 * user authentication, nonce verification, and capability checks.
	 *
	 * @param array $request_data Request data containing security nonce
	 * @param string $required_capability Required user capability
	 * @return array|true Returns error array if validation fails, true if success
	 */
	public function validate_ajax_request( $request_data, $required_capability = 'manage_options' ) {
		// Check user authentication
		if ( ! is_user_logged_in() ) {
			return $this->handle_error( 'Unauthorized AJAX request - user not logged in' );
		}

		// Check user capability
		if ( ! $this->check_user_capability( $required_capability ) ) {
			return $this->handle_error(
				'Insufficient user permissions for AJAX request',
				null,
				array(
					'required_capability' => $required_capability,
					'user_id'             => get_current_user_id(),
				)
			);
		}

		// Verify nonce
		$nonce = $request_data['security'] ?? '';
		if ( ! $this->verify_nonce( $nonce ) ) {
			return $this->handle_error( 'AJAX request security check failed - invalid nonce' );
		}

		$this->log_debug( 'AJAX request security validation passed' );
		return true;
	}

	/**
	 * Generate secure nonce for forms and AJAX requests
	 *
	 * @param string $action Nonce action (default: 'ai_scribe_nonce')
	 * @return string Generated nonce
	 */
	public function generate_nonce( $action = 'ai_scribe_nonce' ) {
		$nonce = wp_create_nonce( $action );

		$this->log_debug( 'Nonce generated', array( 'action' => $action ) );

		return $nonce;
	}

	/**
	 * Sanitize input data for security
	 *
	 * @param mixed $data Data to sanitize
	 * @param string $type Sanitization type
	 * @return mixed Sanitized data
	 */
	public function sanitize_data( $data, $type = 'text' ) {
		return $this->sanitize_input( $data, $type );
	}

	/**
	 * Get service health status
	 *
	 * @return array Health status information
	 */
	public function get_health_status() {
		$base_status = parent::get_health_status();

		$security_status = array(
			'nonce_generation'  => function_exists( 'wp_create_nonce' ),
			'user_capabilities' => function_exists( 'current_user_can' ),
			'wordpress_adapter' => isset( $this->wordpress_adapter ),
		);

		return array_merge(
			$base_status,
			array(
				'security_features'      => $security_status,
				'all_features_available' => ! in_array( false, $security_status, true ),
			)
		);
	}
}
