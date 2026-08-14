<?php
/**
 * Error Handler for AI-Scribe Plugin
 *
 * Centralized error handling with logging integration,
 * graceful degradation, and user-friendly error messages.
 *
 * @package AI_Scribe
 * @subpackage Infrastructure
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Error_Handler
 *
 * Handles all error scenarios for the AI-Scribe plugin with
 * appropriate logging, user notifications, and recovery mechanisms.
 */
class AI_Scribe_Error_Handler {

	/**
	 * Logger instance
	 *
	 * @var AI_Scribe_Logger
	 */
	private $logger;

	/**
	 * Error types
	 */
	const TYPE_API_ERROR           = 'api_error';
	const TYPE_VALIDATION_ERROR    = 'validation_error';
	const TYPE_CONFIGURATION_ERROR = 'config_error';
	const TYPE_PERMISSION_ERROR    = 'permission_error';
	const TYPE_NETWORK_ERROR       = 'network_error';
	const TYPE_TIMEOUT_ERROR       = 'timeout_error';
	const TYPE_RATE_LIMIT_ERROR    = 'rate_limit_error';
	const TYPE_UNKNOWN_ERROR       = 'unknown_error';

	/**
	 * User-friendly error messages
	 *
	 * @var array
	 */
	private $user_messages = array(
		self::TYPE_API_ERROR           => 'There was an issue connecting to the AI service. Please check your API key and try again.',
		self::TYPE_VALIDATION_ERROR    => 'The provided information is invalid. Please check your input and try again.',
		self::TYPE_CONFIGURATION_ERROR => 'The plugin configuration is incomplete. Please check your settings.',
		self::TYPE_PERMISSION_ERROR    => 'You do not have permission to perform this action.',
		self::TYPE_NETWORK_ERROR       => 'Network connection failed. Please check your internet connection and try again.',
		self::TYPE_TIMEOUT_ERROR       => 'The request took too long to complete. Please try again with a shorter content length.',
		self::TYPE_RATE_LIMIT_ERROR    => 'Rate limit exceeded. Please wait a moment before trying again.',
		self::TYPE_UNKNOWN_ERROR       => 'An unexpected error occurred. Please try again or contact support if the issue persists.',
	);

	/**
	 * Constructor
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 */
	public function __construct( AI_Scribe_Logger $logger ) {
		$this->logger = $logger;
		$this->register_error_handlers();
	}

	/**
	 * Register WordPress error handlers
	 *
	 * @return void
	 */
	private function register_error_handlers() {
		// Register shutdown handler for fatal errors
		register_shutdown_function( array( $this, 'handle_fatal_error' ) );

		// Set custom error handler for non-fatal errors
		set_error_handler( array( $this, 'handle_php_error' ), E_ALL & ~E_NOTICE );
	}

	/**
	 * Handle API errors
	 *
	 * @param string $message Error message
	 * @param array $context Error context
	 * @param Exception|null $exception Original exception
	 * @return array Standardized error response
	 */
	public function handle_api_error( $message, array $context = array(), ?Exception $exception = null ) {
		return $this->handle_error( self::TYPE_API_ERROR, $message, $context, $exception );
	}

	/**
	 * Handle validation errors
	 *
	 * @param string $message Error message
	 * @param array $context Error context
	 * @return array Standardized error response
	 */
	public function handle_validation_error( $message, array $context = array() ) {
		return $this->handle_error( self::TYPE_VALIDATION_ERROR, $message, $context );
	}

	/**
	 * Handle configuration errors
	 *
	 * @param string $message Error message
	 * @param array $context Error context
	 * @return array Standardized error response
	 */
	public function handle_config_error( $message, array $context = array() ) {
		return $this->handle_error( self::TYPE_CONFIGURATION_ERROR, $message, $context );
	}

	/**
	 * Handle permission errors
	 *
	 * @param string $message Error message
	 * @param array $context Error context
	 * @return array Standardized error response
	 */
	public function handle_permission_error( $message, array $context = array() ) {
		return $this->handle_error( self::TYPE_PERMISSION_ERROR, $message, $context );
	}

	/**
	 * Handle network errors
	 *
	 * @param string $message Error message
	 * @param array $context Error context
	 * @param Exception|null $exception Original exception
	 * @return array Standardized error response
	 */
	public function handle_network_error( $message, array $context = array(), ?Exception $exception = null ) {
		return $this->handle_error( self::TYPE_NETWORK_ERROR, $message, $context, $exception );
	}

	/**
	 * Handle timeout errors
	 *
	 * @param string $message Error message
	 * @param array $context Error context
	 * @return array Standardized error response
	 */
	public function handle_timeout_error( $message, array $context = array() ) {
		return $this->handle_error( self::TYPE_TIMEOUT_ERROR, $message, $context );
	}

	/**
	 * Handle rate limit errors
	 *
	 * @param string $message Error message
	 * @param array $context Error context
	 * @return array Standardized error response
	 */
	public function handle_rate_limit_error( $message, array $context = array() ) {
		return $this->handle_error( self::TYPE_RATE_LIMIT_ERROR, $message, $context );
	}

	/**
	 * Main error handling method
	 *
	 * @param string $type Error type
	 * @param string $message Technical error message
	 * @param array $context Error context
	 * @param Exception|null $exception Original exception
	 * @return array Standardized error response
	 */
	public function handle_error( $type, $message, array $context = array(), ?Exception $exception = null ) {
		// Log the technical error
		$log_context = array_merge(
			$context,
			array(
				'error_type'  => $type,
				'user_id'     => get_current_user_id(),
				'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
				'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
			)
		);

		if ( $exception ) {
			$log_context['exception'] = array(
				'message' => $exception->getMessage(),
				'file'    => $exception->getFile(),
				'line'    => $exception->getLine(),
				'trace'   => $exception->getTraceAsString(),
			);
		}

		$this->logger->error( "AI-Scribe Error [{$type}]: {$message}", $log_context );

		// Create user-friendly response
		$user_message = $this->get_user_friendly_message( $type );

		return array(
			'success'     => false,
			'error'       => $user_message,
			'error_type'  => $type,
			'error_code'  => $this->generate_error_code( $type ),
			'timestamp'   => current_time( 'mysql' ),
			'recoverable' => $this->is_recoverable_error( $type ),
			'retry_after' => $this->get_retry_delay( $type ),
		);
	}

	/**
	 * Get user-friendly error message
	 *
	 * @param string $type Error type
	 * @return string User-friendly message
	 */
	private function get_user_friendly_message( $type ) {
		return $this->user_messages[ $type ] ?? $this->user_messages[ self::TYPE_UNKNOWN_ERROR ];
	}

	/**
	 * Generate unique error code for tracking
	 *
	 * @param string $type Error type
	 * @return string Error code
	 */
	private function generate_error_code( $type ) {
		return strtoupper( substr( $type, 0, 3 ) ) . '-' . gmdate( 'Ymd' ) . '-' . substr( md5( microtime() ), 0, 6 );
	}

	/**
	 * Check if error is recoverable
	 *
	 * @param string $type Error type
	 * @return bool
	 */
	private function is_recoverable_error( $type ) {
		$recoverable_types = array(
			self::TYPE_NETWORK_ERROR,
			self::TYPE_TIMEOUT_ERROR,
			self::TYPE_RATE_LIMIT_ERROR,
		);

		return in_array( $type, $recoverable_types );
	}

	/**
	 * Get retry delay for recoverable errors
	 *
	 * @param string $type Error type
	 * @return int Delay in seconds
	 */
	private function get_retry_delay( $type ) {
		$delays = array(
			self::TYPE_NETWORK_ERROR    => 5,
			self::TYPE_TIMEOUT_ERROR    => 10,
			self::TYPE_RATE_LIMIT_ERROR => 60,
		);

		return $delays[ $type ] ?? 0;
	}

	/**
	 * Handle fatal PHP errors
	 *
	 * @return void
	 */
	public function handle_fatal_error() {
		$error = error_get_last();

		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ) ) ) {
			$this->logger->critical(
				'Fatal PHP Error in AI-Scribe',
				array(
					'message' => $error['message'],
					'file'    => $error['file'],
					'line'    => $error['line'],
					'type'    => $error['type'],
				)
			);
		}
	}

	/**
	 * Handle PHP errors
	 *
	 * @param int $errno Error number
	 * @param string $errstr Error message
	 * @param string $errfile Error file
	 * @param int $errline Error line
	 * @return bool
	 */
	public function handle_php_error( $errno, $errstr, $errfile, $errline ) {
		// Only handle AI-Scribe related errors
		if ( strpos( $errfile, 'ai-scribe' ) === false ) {
			return false;
		}

		$error_types = array(
			E_ERROR             => 'ERROR',
			E_WARNING           => 'WARNING',
			E_PARSE             => 'PARSE',
			E_NOTICE            => 'NOTICE',
			E_CORE_ERROR        => 'CORE_ERROR',
			E_CORE_WARNING      => 'CORE_WARNING',
			E_COMPILE_ERROR     => 'COMPILE_ERROR',
			E_COMPILE_WARNING   => 'COMPILE_WARNING',
			E_USER_ERROR        => 'USER_ERROR',
			E_USER_WARNING      => 'USER_WARNING',
			E_USER_NOTICE       => 'USER_NOTICE',
			2048                => 'STRICT', // E_STRICT constant deprecated in PHP 8.4
			E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
			E_DEPRECATED        => 'DEPRECATED',
			E_USER_DEPRECATED   => 'USER_DEPRECATED',
		);

		$error_type = $error_types[ $errno ] ?? 'UNKNOWN';

		$this->logger->warning(
			"PHP {$error_type}: {$errstr}",
			array(
				'file'  => $errfile,
				'line'  => $errline,
				'errno' => $errno,
			)
		);

		// Don't prevent default error handling
		return false;
	}

	/**
	 * Validate API response and handle errors
	 *
	 * @param mixed $response API response
	 * @param string $context Context description
	 * @return array|true Validation result
	 */
	public function validate_api_response( $response, $context = 'API call' ) {
		if ( is_wp_error( $response ) ) {
			return $this->handle_network_error(
				"WordPress HTTP error in {$context}: " . $response->get_error_message(),
				array( 'wp_error_code' => $response->get_error_code() )
			);
		}

		if ( ! is_array( $response ) || ! isset( $response['response'] ) ) {
			return $this->handle_api_error(
				"Invalid response format in {$context}",
				array( 'response_type' => gettype( $response ) )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			$error_body = wp_remote_retrieve_body( $response );

			// Determine error type based on status code
			if ( $status_code === 401 || $status_code === 403 ) {
				return $this->handle_permission_error(
					"Authentication failed in {$context}",
					array(
						'status_code'   => $status_code,
						'response_body' => $error_body,
					)
				);
			} elseif ( $status_code === 429 ) {
				return $this->handle_rate_limit_error(
					"Rate limit exceeded in {$context}",
					array(
						'status_code'   => $status_code,
						'response_body' => $error_body,
					)
				);
			} elseif ( $status_code >= 500 ) {
				return $this->handle_api_error(
					"Server error in {$context}",
					array(
						'status_code'   => $status_code,
						'response_body' => $error_body,
					)
				);
			} else {
				return $this->handle_api_error(
					"HTTP error {$status_code} in {$context}",
					array(
						'status_code'   => $status_code,
						'response_body' => $error_body,
					)
				);
			}
		}

		return true;
	}

	/**
	 * Create error response for AJAX requests
	 *
	 * @param string $type Error type
	 * @param string $message Technical message
	 * @param array $context Error context
	 * @return void (sends JSON and exits)
	 */
	public function send_ajax_error( $type, $message, array $context = array() ) {
		$error_response = $this->handle_error( $type, $message, $context );
		wp_send_json_error( $error_response );
	}

	/**
	 * Get error statistics for debugging
	 *
	 * @return array Error statistics
	 */
	public function get_error_stats() {
		// This could be enhanced to track error counts, types, etc.
		return array(
			'error_handler_active'           => true,
			'fatal_error_handler_registered' => true,
			'php_error_handler_registered'   => true,
			'supported_error_types'          => array_keys( $this->user_messages ),
		);
	}

	/**
	 * Test error handling system
	 *
	 * @return array Test results
	 */
	public function test_error_handling() {
		$tests = array();

		// Test each error type
		foreach ( array_keys( $this->user_messages ) as $type ) {
			$result         = $this->handle_error( $type, "Test error for type: {$type}" );
			$tests[ $type ] = array(
				'success'       => isset( $result['error_type'] ) && $result['error_type'] === $type,
				'user_friendly' => ! empty( $result['error'] ),
				'error_code'    => $result['error_code'] ?? null,
			);
		}

		return $tests;
	}
}
