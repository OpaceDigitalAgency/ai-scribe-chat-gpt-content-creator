<?php
/**
 * Base Controller Class for AI-Scribe Plugin
 *
 * Abstract base class for all controllers handling HTTP requests,
 * AJAX endpoints, and WordPress admin interactions.
 *
 * @package AI_Scribe
 * @subpackage Infrastructure
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Abstract Class AI_Scribe_Base_Controller
 *
 * Provides common functionality for all AI-Scribe controllers including
 * request handling, nonce verification, and response formatting.
 */
abstract class AI_Scribe_Base_Controller {

	/**
	 * Logger instance
	 *
	 * @var AI_Scribe_Logger
	 */
	protected $logger;

	/**
	 * Service container instance
	 *
	 * @var AI_Scribe_Service_Container
	 */
	protected $container;

	/**
	 * Controller name for logging and debugging
	 *
	 * @var string
	 */
	protected $controller_name;

	/**
	 * Registered AJAX actions
	 *
	 * @var array
	 */
	protected $ajax_actions = array();

	/**
	 * Constructor
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 * @param AI_Scribe_Service_Container $container Service container
	 */
	public function __construct( AI_Scribe_Logger $logger, AI_Scribe_Service_Container $container ) {
		$this->logger          = $logger;
		$this->container       = $container;
		$this->controller_name = $this->getControllerName();

		$this->initialize();
		$this->register_hooks();

		$this->log_debug( "Controller initialized: {$this->controller_name}" );
	}

	/**
	 * Get controller name for logging
	 *
	 * @return string
	 */
	protected function getControllerName() {
		$class_name = get_class( $this );
		return str_replace( array( 'AI_Scribe_', '_Controller', '_' ), array( '', '', ' ' ), $class_name );
	}

	/**
	 * Initialize controller - override in child classes
	 *
	 * @return void
	 */
	protected function initialize() {
		// Override in child classes for custom initialization
	}

	/**
	 * Register WordPress hooks - override in child classes
	 *
	 * @return void
	 */
	protected function register_hooks() {
		// Register AJAX actions
		foreach ( $this->ajax_actions as $action => $config ) {
			$this->register_ajax_action( $action, $config );
		}
	}

	/**
	 * Register AJAX action
	 *
	 * @param string $action AJAX action name
	 * @param array $config Action configuration
	 * @return void
	 */
	protected function register_ajax_action( $action, array $config = array() ) {
		$method         = $config['method'] ?? $action;
		$public         = $config['public'] ?? false;
		$nonce_required = $config['nonce_required'] ?? true;

		// Register for logged-in users
		add_action( "wp_ajax_{$action}", array( $this, $method ) );

		// Register for non-logged-in users if public
		if ( $public ) {
			add_action( "wp_ajax_nopriv_{$action}", array( $this, $method ) );
		}

		$this->log_debug( "Registered AJAX action: {$action} -> {$method}" );
	}

	/**
	 * Verify AJAX nonce
	 *
	 * @param string $nonce_action Nonce action name
	 * @param string $nonce_field Nonce field name in $_POST
	 * @return bool|array True if valid, error array if invalid
	 */
	protected function verify_nonce( $nonce_action = 'ai_scribe_nonce', $nonce_field = 'security' ) {
		if ( ! isset( $_POST[ $nonce_field ] ) ) {
			return $this->create_error_response(
				'Security nonce is missing. Please refresh the page.',
				array( 'nonce_expired' => true )
			);
		}

		if ( ! check_ajax_referer( $nonce_action, $nonce_field, false ) ) {
			return $this->create_error_response(
				'Invalid request. Please refresh the page and try again.',
				array( 'nonce_expired' => true )
			);
		}

		return true;
	}

	/**
	 * Get and sanitize POST parameter
	 *
	 * @param string $key Parameter key
	 * @param string $type Sanitization type
	 * @param mixed $default Default value
	 * @return mixed Sanitized parameter value
	 */
	protected function get_post_param( $key, $type = 'text', $default = null ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}

		return $this->sanitize_input( $_POST[ $key ], $type );
	}

	/**
	 * Get and sanitize GET parameter
	 *
	 * @param string $key Parameter key
	 * @param string $type Sanitization type
	 * @param mixed $default Default value
	 * @return mixed Sanitized parameter value
	 */
	protected function get_get_param( $key, $type = 'text', $default = null ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			return $default;
		}

		return $this->sanitize_input( $_GET[ $key ], $type );
	}

	/**
	 * Sanitize input data
	 *
	 * @param mixed $data Data to sanitize
	 * @param string $type Sanitization type
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
			case 'array':
				return is_array( $data ) ? array_map( 'sanitize_text_field', $data ) : array();
			default:
				return sanitize_text_field( $data );
		}
	}

	/**
	 * Send JSON success response
	 *
	 * @param mixed $data Response data
	 * @param string $message Success message
	 * @return void
	 */
	protected function send_success_response( $data = null, $message = 'Operation completed successfully' ) {
		$response = array(
			'success'    => true,
			'message'    => $message,
			'controller' => $this->controller_name,
			'timestamp'  => current_time( 'mysql' ),
		);

		if ( $data !== null ) {
			$response['data'] = $data;
		}

		wp_send_json_success( $response );
	}

	/**
	 * Send JSON error response
	 *
	 * @param string $message Error message
	 * @param array $data Additional error data
	 * @return void
	 */
	protected function send_error_response( $message, array $data = array() ) {
		$response = array_merge(
			array(
				'success'    => false,
				'message'    => $message,
				'controller' => $this->controller_name,
				'timestamp'  => current_time( 'mysql' ),
			),
			$data
		);

		$this->log_error( "Error response: {$message}", $response );
		wp_send_json_error( $response );
	}

	/**
	 * Create error response array (without sending)
	 *
	 * @param string $message Error message
	 * @param array $data Additional error data
	 * @return array Error response array
	 */
	protected function create_error_response( $message, array $data = array() ) {
		return array_merge(
			array(
				'success'    => false,
				'message'    => $message,
				'controller' => $this->controller_name,
				'timestamp'  => current_time( 'mysql' ),
			),
			$data
		);
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
			$this->logger->debug( "[{$this->controller_name}] {$message}", $context );
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
			$this->logger->info( "[{$this->controller_name}] {$message}", $context );
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
			$this->logger->warning( "[{$this->controller_name}] {$message}", $context );
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
			$this->logger->error( "[{$this->controller_name}] {$message}", $context );
		}
	}

	/**
	 * Check if user has required capability
	 *
	 * @param string $capability Required capability
	 * @return bool|array True if authorized, error array if not
	 */
	protected function check_user_capability( $capability = 'manage_options' ) {
		if ( ! current_user_can( $capability ) ) {
			return $this->create_error_response(
				'Insufficient permissions to perform this action.',
				array( 'required_capability' => $capability )
			);
		}

		return true;
	}

	/**
	 * Get service from container
	 *
	 * @param string $service_id Service identifier
	 * @return mixed Service instance
	 */
	protected function get_service( $service_id ) {
		try {
			return $this->container->get( $service_id );
		} catch ( Exception $e ) {
			$this->log_error( "Failed to get service: {$service_id}", array( 'exception' => $e->getMessage() ) );
			return null;
		}
	}

	/**
	 * Abstract method for defining AJAX actions
	 * Must be implemented by child classes
	 *
	 * @return array AJAX actions configuration
	 */
	abstract protected function get_ajax_actions();
}
