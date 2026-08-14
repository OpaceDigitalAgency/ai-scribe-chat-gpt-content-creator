<?php
/**
 * Base Service Class for AI-Scribe Plugin
 *
 * Abstract base class providing common functionality for all services
 * including logging, configuration access, and error handling.
 *
 * @package AI_Scribe
 * @subpackage Infrastructure
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Abstract Class AI_Scribe_Base_Service
 *
 * Provides common functionality for all AI-Scribe services including
 * logging, configuration access, and standardized error handling.
 */
abstract class AI_Scribe_Base_Service {

	/**
	 * Logger instance
	 *
	 * @var AI_Scribe_Logger
	 */
	protected $logger;

	/**
	 * Configuration manager instance
	 *
	 * @var AI_Scribe_Config_Manager
	 */
	protected $config;

	/**
	 * Service name for logging and debugging
	 *
	 * @var string
	 */
	protected $service_name;

	/**
	 * Constructor
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 * @param AI_Scribe_Config_Manager $config Configuration manager
	 */
	public function __construct( AI_Scribe_Logger $logger, ?AI_Scribe_Config_Manager $config = null ) {
		$this->logger       = $logger;
		$this->config       = $config;
		$this->service_name = $this->getServiceName();

		$this->initialize();
		$this->log_debug( "Service initialized: {$this->service_name}" );
	}

	/**
	 * Get service name for logging
	 *
	 * @return string
	 */
	protected function getServiceName() {
		$class_name = get_class( $this );
		return str_replace( array( 'AI_Scribe_', '_' ), array( '', ' ' ), $class_name );
	}

	/**
	 * Initialize service - override in child classes
	 *
	 * @return void
	 */
	protected function initialize() {
		// Override in child classes for custom initialization
	}

	/**
	 * Log debug message
	 *
	 * @param string $message Debug message
	 * @param array $context Additional context data
	 * @return void
	 */
	protected function log_debug( $message, array $context = array() ) {
		if ( $this->logger ) {
			$this->logger->debug( "[{$this->service_name}] {$message}", $context );
		}
	}

	/**
	 * Log info message
	 *
	 * @param string $message Info message
	 * @param array $context Additional context data
	 * @return void
	 */
	protected function log_info( $message, array $context = array() ) {
		if ( $this->logger ) {
			$this->logger->info( "[{$this->service_name}] {$message}", $context );
		}
	}

	/**
	 * Log warning message
	 *
	 * @param string $message Warning message
	 * @param array $context Additional context data
	 * @return void
	 */
	protected function log_warning( $message, array $context = array() ) {
		if ( $this->logger ) {
			$this->logger->warning( "[{$this->service_name}] {$message}", $context );
		}
	}

	/**
	 * Log error message
	 *
	 * @param string $message Error message
	 * @param array $context Additional context data
	 * @return void
	 */
	protected function log_error( $message, array $context = array() ) {
		if ( $this->logger ) {
			$this->logger->error( "[{$this->service_name}] {$message}", $context );
		}
	}

	/**
	 * Get configuration value
	 *
	 * @param string $key Configuration key
	 * @param mixed $default Default value if key not found
	 * @return mixed Configuration value
	 */
	protected function get_config( $key, $default = null ) {
		if ( $this->config ) {
			return $this->config->get( $key, $default );
		}
		return $default;
	}

	/**
	 * Set configuration value
	 *
	 * @param string $key Configuration key
	 * @param mixed $value Configuration value
	 * @return bool Success status
	 */
	protected function set_config( $key, $value ) {
		if ( $this->config ) {
			return $this->config->set( $key, $value );
		}
		return false;
	}

	/**
	 * Handle service error with standardized logging and response
	 *
	 * @param string $message Error message
	 * @param Exception|null $exception Optional exception
	 * @param array $context Additional context data
	 * @return array Standardized error response
	 */
	protected function handle_error( $message, ?Exception $exception = null, array $context = array() ) {
		$error_data = array(
			'service' => $this->service_name,
			'message' => $message,
			'context' => $context,
		);

		if ( $exception ) {
			$error_data['exception'] = array(
				'message' => $exception->getMessage(),
				'file'    => $exception->getFile(),
				'line'    => $exception->getLine(),
				'trace'   => $exception->getTraceAsString(),
			);
		}

		$this->log_error( $message, $error_data );

		return array(
			'success'   => false,
			'error'     => $message,
			'service'   => $this->service_name,
			'timestamp' => current_time( 'mysql' ),
		);
	}

	/**
	 * Create standardized success response
	 *
	 * @param mixed $data Response data
	 * @param string $message Optional success message
	 * @return array Standardized success response
	 */
	protected function create_success_response( $data = null, $message = 'Operation completed successfully' ) {
		$response = array(
			'success'   => true,
			'message'   => $message,
			'service'   => $this->service_name,
			'timestamp' => current_time( 'mysql' ),
		);

		if ( $data !== null ) {
			$response['data'] = $data;
		}

		return $response;
	}

	/**
	 * Validate required parameters
	 *
	 * @param array $params Parameters to validate
	 * @param array $required Required parameter names
	 * @return array|true Returns error array if validation fails, true if success
	 */
	protected function validate_required_params( array $params, array $required ) {
		$missing = array();

		foreach ( $required as $param ) {
			if ( ! isset( $params[ $param ] ) || empty( $params[ $param ] ) ) {
				$missing[] = $param;
			}
		}

		if ( ! empty( $missing ) ) {
			return $this->handle_error(
				'Missing required parameters: ' . implode( ', ', $missing ),
				null,
				array(
					'missing_params'  => $missing,
					'provided_params' => array_keys( $params ),
				)
			);
		}

		return true;
	}

	/**
	 * Sanitize input data
	 *
	 * @param mixed $data Data to sanitize
	 * @param string $type Sanitization type (text, email, url, etc.)
	 * @return mixed Sanitized data
	 */
	protected function sanitize_input( $data, $type = 'text' ) {
		switch ( $type ) {
			case 'text':
				return sanitize_text_field( $data );
			case 'textarea':
				return sanitize_textarea_field( $data );
			case 'email':
				return sanitize_email( $data );
			case 'url':
				return esc_url_raw( $data );
			case 'key':
				return sanitize_key( $data );
			case 'html':
				return wp_kses_post( $data );
			case 'int':
				return intval( $data );
			case 'float':
				return floatval( $data );
			case 'bool':
				return (bool) $data;
			default:
				return sanitize_text_field( $data );
		}
	}

	/**
	 * Check if debug mode is enabled
	 *
	 * @return bool
	 */
	protected function is_debug_mode() {
		return $this->get_config( 'debug_mode', false ) && current_user_can( 'manage_options' );
	}

	/**
	 * Get service health status
	 *
	 * @return array Health status information
	 */
	public function get_health_status() {
		return array(
			'service'     => $this->service_name,
			'status'      => 'healthy',
			'initialized' => true,
			'timestamp'   => current_time( 'mysql' ),
		);
	}

	/**
	 * Abstract method for service-specific validation
	 * Must be implemented by child classes
	 *
	 * @return bool|array True if valid, error array if invalid
	 */
	abstract public function validate_service();
}
