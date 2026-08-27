<?php
/**
 * Logger for AI-Scribe Plugin
 *
 * Provides comprehensive logging functionality with multiple levels,
 * debug mode awareness, and WordPress integration.
 *
 * @package AI_Scribe
 * @subpackage Infrastructure
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Logger
 *
 * Handles all logging operations for the AI-Scribe plugin with
 * configurable log levels and debug mode integration.
 */
class AI_Scribe_Logger {

	/**
	 * Log levels
	 */
	const LEVEL_DEBUG    = 'debug';
	const LEVEL_INFO     = 'info';
	const LEVEL_WARNING  = 'warning';
	const LEVEL_ERROR    = 'error';
	const LEVEL_CRITICAL = 'critical';

	/**
	 * Log level hierarchy for filtering
	 *
	 * @var array
	 */
	private $level_hierarchy = array(
		self::LEVEL_DEBUG    => 0,
		self::LEVEL_INFO     => 1,
		self::LEVEL_WARNING  => 2,
		self::LEVEL_ERROR    => 3,
		self::LEVEL_CRITICAL => 4,
	);

	/**
	 * Current minimum log level
	 *
	 * @var string
	 */
	private $min_log_level;

	/**
	 * Whether debug mode is enabled
	 *
	 * @var bool
	 */
	private $debug_enabled;

	/**
	 * Log file path
	 *
	 * @var string
	 */
	private $log_file_path;

	/**
	 * Whether the private log directory is available and writable.
	 *
	 * File logging is best effort. A fresh or locked-down WordPress uploads
	 * directory must never turn a successful admin request into a PHP warning.
	 *
	 * @var bool
	 */
	private $file_logging_enabled = false;

	/**
	 * Maximum log file size in bytes (5MB)
	 *
	 * @var int
	 */
	private $max_log_size = 5242880;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->initialize();
	}

	/**
	 * Initialize logger
	 *
	 * @return void
	 */
	private function initialize() {
		$this->debug_enabled = $this->is_debug_mode_enabled();
		$this->min_log_level = $this->debug_enabled ? self::LEVEL_DEBUG : self::LEVEL_WARNING;
		$this->log_file_path = $this->get_log_file_path();

		// Ensure log directory exists
		$this->file_logging_enabled = $this->ensure_log_directory();
	}

	/**
	 * Check if debug mode is enabled
	 *
	 * @return bool
	 */
	private function is_debug_mode_enabled() {
		// Check if WordPress functions are available
		if ( ! function_exists( 'current_user_can' ) || ! function_exists( 'wp_get_current_user' ) ) {
			return false;
		}

		// Only allow debugging for admin users
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Check debug setting (can be enhanced to read from config)
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Get log file path
	 *
	 * @return string
	 */
	private function get_log_file_path() {
		// Use WordPress function if available, otherwise fallback to temp directory
		if ( function_exists( 'wp_upload_dir' ) ) {
			$upload_dir = wp_upload_dir();
			$log_dir    = $upload_dir['basedir'] . '/ai-scribe-logs';
		} else {
			// Fallback for early initialization when WordPress functions aren't loaded
			$log_dir = sys_get_temp_dir() . '/ai-scribe-logs';
		}
		return $log_dir . '/ai-scribe-' . gmdate( 'Y-m-d' ) . '.log';
	}

	/**
	 * Ensure log directory exists
	 *
	 * @return bool Whether the directory is ready for file logging.
	 */
	private function ensure_log_directory() {
		$log_dir = dirname( $this->log_file_path );

		if ( ! is_dir( $log_dir ) ) {
			// The plugin only ever runs inside WordPress, so wp_mkdir_p() is
			// always available; if creation fails, logging stays disabled.
			if ( ! wp_mkdir_p( $log_dir ) ) {
				return false;
			}
		}

		if ( ! is_dir( $log_dir ) || ( function_exists( 'wp_is_writable' ) && ! wp_is_writable( $log_dir ) ) ) {
			return false;
		}

		// Create .htaccess to protect log files. This remains best effort
		// because logging must never emit another filesystem warning.
		$htaccess_file = $log_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A logger must degrade without emitting a new warning.
			@file_put_contents( $htaccess_file, "Deny from all\n", LOCK_EX );
		}

		return true;
	}

	/**
	 * Log debug message
	 *
	 * @param string $message Log message
	 * @param array $context Additional context data
	 * @return void
	 */
	public function debug( $message, array $context = array() ) {
		$this->log( self::LEVEL_DEBUG, $message, $context );
	}

	/**
	 * Log info message
	 *
	 * @param string $message Log message
	 * @param array $context Additional context data
	 * @return void
	 */
	public function info( $message, array $context = array() ) {
		$this->log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log warning message
	 *
	 * @param string $message Log message
	 * @param array $context Additional context data
	 * @return void
	 */
	public function warning( $message, array $context = array() ) {
		$this->log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log error message
	 *
	 * @param string $message Log message
	 * @param array $context Additional context data
	 * @return void
	 */
	public function error( $message, array $context = array() ) {
		$this->log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Log critical message
	 *
	 * @param string $message Log message
	 * @param array $context Additional context data
	 * @return void
	 */
	public function critical( $message, array $context = array() ) {
		$this->log( self::LEVEL_CRITICAL, $message, $context );
	}

	/**
	 * Main logging method
	 *
	 * @param string $level Log level
	 * @param string $message Log message
	 * @param array $context Additional context data
	 * @return void
	 */
	public function log( $level, $message, array $context = array() ) {
		// Check if we should log this level
		if ( ! $this->should_log( $level ) ) {
			return;
		}

		// Format the log entry
		$log_entry = $this->format_log_entry( $level, $message, $context );

		// Write to WordPress error log if debug enabled
		if ( $this->debug_enabled ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Explicitly gated by the plugin's debug setting.
			error_log( $log_entry );
		}

		// Write to custom log file for warnings and above
		if ( $this->level_hierarchy[ $level ] >= $this->level_hierarchy[ self::LEVEL_WARNING ] ) {
			$this->write_to_file( $log_entry );
		}
	}

	/**
	 * Check if we should log this level
	 *
	 * @param string $level Log level
	 * @return bool
	 */
	private function should_log( $level ) {
		if ( ! isset( $this->level_hierarchy[ $level ] ) ) {
			return false;
		}

		return $this->level_hierarchy[ $level ] >= $this->level_hierarchy[ $this->min_log_level ];
	}

	/**
	 * Format log entry
	 *
	 * @param string $level Log level
	 * @param string $message Log message
	 * @param array $context Additional context data
	 * @return string Formatted log entry
	 */
	private function format_log_entry( $level, $message, array $context = array() ) {
		$timestamp   = current_time( 'Y-m-d H:i:s' );
		$level_upper = strtoupper( $level );

		$log_entry = "[{$timestamp}] AI-SCRIBE.{$level_upper}: {$message}";

		// Add context if provided
		if ( ! empty( $context ) ) {
			$context_json = json_encode( $context, JSON_UNESCAPED_SLASHES );
			$log_entry   .= " Context: {$context_json}";
		}

		return $log_entry;
	}

	/**
	 * Write log entry to file
	 *
	 * @param string $log_entry Formatted log entry
	 * @return void
	 */
	private function write_to_file( $log_entry ) {
		if ( ! $this->file_logging_enabled ) {
			return;
		}

		try {
			// Check file size and rotate if necessary
			$this->rotate_log_if_needed();

			// The directory can change between readiness and this write. Disable
			// file logging rather than leaking PHP's warning into the admin UI.
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A logger must degrade without emitting a new warning.
			if ( false === @file_put_contents( $this->log_file_path, $log_entry . PHP_EOL, FILE_APPEND | LOCK_EX ) ) {
				$this->file_logging_enabled = false;
			}

		} catch ( Exception $e ) {
			$this->file_logging_enabled = false;
			// Fallback to WordPress error log if file writing fails
			if ( $this->debug_enabled ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Explicitly gated by the plugin's debug setting.
				error_log( 'AI-Scribe Logger: Failed to write to log file - ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Rotate log file if it exceeds maximum size
	 *
	 * @return void
	 */
	private function rotate_log_if_needed() {
		if ( ! file_exists( $this->log_file_path ) ) {
			return;
		}

		if ( filesize( $this->log_file_path ) > $this->max_log_size ) {
			$backup_path = $this->log_file_path . '.backup';

			// Remove old backup if exists
			if ( file_exists( $backup_path ) ) {
				wp_delete_file( $backup_path );
			}

			// Move current log to backup via the WP filesystem abstraction.
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			if ( $wp_filesystem ) {
				$wp_filesystem->move( $this->log_file_path, $backup_path, true );
			}
		}
	}

	/**
	 * Get recent log entries
	 *
	 * @param int $lines Number of lines to retrieve
	 * @param string $level Minimum log level to include
	 * @return array Log entries
	 */
	public function get_recent_logs( $lines = 100, $level = self::LEVEL_WARNING ) {
		if ( ! file_exists( $this->log_file_path ) ) {
			return array();
		}

		try {
			$log_content = file_get_contents( $this->log_file_path );
			$log_lines   = explode( PHP_EOL, $log_content );

			// Filter by level if specified
			if ( $level !== self::LEVEL_DEBUG ) {
				$min_level_value = $this->level_hierarchy[ $level ];
				$log_lines       = array_filter(
					$log_lines,
					function ( $line ) use ( $min_level_value ) {
						foreach ( $this->level_hierarchy as $log_level => $value ) {
							if ( $value >= $min_level_value && strpos( $line, strtoupper( $log_level ) ) !== false ) {
								return true;
							}
						}
						return false;
					}
				);
			}

			// Get last N lines
			$recent_logs = array_slice( array_reverse( $log_lines ), 0, $lines );
			return array_reverse( $recent_logs );

		} catch ( Exception $e ) {
			return array( 'Error reading log file: ' . $e->getMessage() );
		}
	}

	/**
	 * Clear log files
	 *
	 * @return bool Success status
	 */
	public function clear_logs() {
		try {
			$log_dir   = dirname( $this->log_file_path );
			$log_files = glob( $log_dir . '/ai-scribe-*.log*' );

			foreach ( $log_files as $file ) {
				wp_delete_file( $file );
			}

			$this->info( 'Log files cleared' );
			return true;

		} catch ( Exception $e ) {
			$this->error( 'Failed to clear log files', array( 'exception' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Get log statistics
	 *
	 * @return array Log statistics
	 */
	public function get_log_stats() {
		$stats = array(
			'debug_enabled'     => $this->debug_enabled,
			'min_log_level'     => $this->min_log_level,
			'log_file_path'     => $this->log_file_path,
			'log_file_exists'   => file_exists( $this->log_file_path ),
			'log_file_size'     => 0,
			'log_file_readable' => false,
		);

		if ( $stats['log_file_exists'] ) {
			$stats['log_file_size']     = filesize( $this->log_file_path );
			$stats['log_file_readable'] = is_readable( $this->log_file_path );
		}

		return $stats;
	}

	/**
	 * Set minimum log level
	 *
	 * @param string $level Minimum log level
	 * @return void
	 */
	public function set_min_log_level( $level ) {
		if ( isset( $this->level_hierarchy[ $level ] ) ) {
			$this->min_log_level = $level;
		}
	}

	/**
	 * Enable or disable debug mode
	 *
	 * @param bool $enabled Debug mode status
	 * @return void
	 */
	public function set_debug_enabled( $enabled ) {
		$this->debug_enabled = (bool) $enabled;
		$this->min_log_level = $this->debug_enabled ? self::LEVEL_DEBUG : self::LEVEL_WARNING;
	}
}
