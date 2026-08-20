<?php
/**
 * Configuration Manager for AI-Scribe Plugin
 *
 * Centralized configuration management with environment abstraction,
 * secure handling of API keys, and WordPress options integration.
 *
 * @package AI_Scribe
 * @subpackage Infrastructure
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Config_Manager
 *
 * Manages all plugin configuration including API keys, settings,
 * and environment-specific values with secure storage and retrieval.
 */
class AI_Scribe_Config_Manager {

	/**
	 * Logger instance
	 *
	 * @var AI_Scribe_Logger
	 */
	private $logger;

	/**
	 * Configuration cache
	 *
	 * @var array
	 */
	private $config_cache = array();

	/**
	 * Default configuration values
	 *
	 * @var array
	 */
	private $defaults = array(
		'debug_mode'                 => false,
		'cache_enabled'              => true,
		'cache_duration'             => 3600,
		'max_completion_tokens'      => 4000,
		'temperature'                => 0.7,
		'timeout'                    => 30,
		'retry_attempts'             => 3,
		'rate_limit_enabled'         => true,
		'parallel_processing'        => true,
		'image_generation_enabled'   => true,
		'content_validation_enabled' => true,
	);

	/**
	 * Sensitive configuration keys that should be encrypted
	 *
	 * @var array
	 */
	private $sensitive_keys = array(
		'api_key',
		'anthropic_api_key',
		'openai_api_key',
		'claude_api_key',
		'gemini_api_key',
		'grok_api_key',
	);

	/**
	 * WordPress option names for different configuration groups
	 *
	 * @var array
	 */
	private $option_groups = array(
		'ai_engine' => 'ab_gpt_ai_engine_settings',
		'content'   => 'ab_gpt_content_settings',
		'prompts'   => 'ab_prompts_content',
		'image'     => 'ab_gpt_image_settings',
		'general'   => 'ai_scribe_general_settings',
	);

	/**
	 * Constructor
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 */
	public function __construct( ?AI_Scribe_Logger $logger = null ) {
		$this->logger = $logger;

		// Initialize with error handling
		try {
			$this->initialize();
		} catch ( Exception $e ) {
			// Log error if logger is available, otherwise use error_log
			if ( $this->logger ) {
				$this->logger->error( 'Config Manager initialization failed: ' . $e->getMessage() );
			} else {
				ai_scribe_debug_log( 'AI-Scribe Config Manager initialization failed: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Initialize configuration manager
	 *
	 * @return void
	 */
	private function initialize() {
		// Load configuration from WordPress options
		$this->load_configuration();

		// Set up default values if not exists
		$this->ensure_defaults();

		// Only log if logger is available
		if ( $this->logger ) {
			$this->logger->debug( 'Configuration Manager initialized' );
		}
	}

	/**
	 * Load configuration from WordPress options
	 *
	 * @return void
	 */
	private function load_configuration() {
		foreach ( $this->option_groups as $group => $option_name ) {
			// Use WordPress function if available, otherwise use empty array as fallback
			if ( function_exists( 'get_option' ) ) {
				$this->config_cache[ $group ] = get_option( $option_name, array() );
			} else {
				// Fallback for early initialization when WordPress functions aren't loaded
				$this->config_cache[ $group ] = array();
			}
		}
	}

	/**
	 * Ensure default values are set
	 *
	 * CRITICAL FIX: Only set defaults for non-sensitive configuration
	 * API keys should NEVER have defaults - they must be explicitly set by user
	 *
	 * @return void
	 */
	private function ensure_defaults() {
		foreach ( $this->defaults as $key => $value ) {
			// CRITICAL: Never set defaults for API keys or sensitive data
			if ( in_array( $key, $this->sensitive_keys ) ) {
				continue;
			}

			// Only set defaults for non-sensitive configuration
			if ( ! $this->has( $key ) ) {
				$this->set( $key, $value );
			}
		}
	}

	/**
	 * Get configuration value
	 *
	 * @param string $key Configuration key (supports dot notation: group.key)
	 * @param mixed $default Default value if key not found
	 * @return mixed Configuration value
	 */
	public function get( $key, $default = null ) {
		// Handle dot notation (e.g., 'ai_engine.api_key')
		if ( strpos( $key, '.' ) !== false ) {
			list($group, $subkey) = explode( '.', $key, 2 );

			// First check individual options for backward compatibility
			// This is the primary method since AJAX handlers save to individual options
			if ( $group === 'ai_engine' ) {
				$individual_option_map = array(
					'api_key'                 => 'ab_api_key',
					'anthropic_api_key'       => 'ab_anthropic_api_key',
					'model'                   => 'ab_model',
					'temp'                    => 'ab_temp',
					'top_p'                   => 'ab_top_p',
					'best_oi'                 => 'ab_best_oi',
					'freq_pent'               => 'ab_freq_pent',
					'Presence_penalty'        => 'ab_Presence_penalty',
					'n'                       => 'ab_n',
					'enable_image_generation' => 'ab_enable_image_generation',
				);

				if ( isset( $individual_option_map[ $subkey ] ) ) {
					$option_name = $individual_option_map[ $subkey ];
					$value       = get_option( $option_name, null );

					// For API keys, if the individual option is empty/null,
					// return the default immediately - don't check grouped cache
					if ( in_array( $subkey, $this->sensitive_keys ) ) {
						if ( $value === null || $value === '' ) {
							return $default;
						}
						return $this->decrypt_value( $value );
					}

					// For non-sensitive values, return if not empty
					if ( $value !== null && $value !== '' ) {
						return $value;
					}
				}
			}

			// Fallback: Check grouped options cache
			if ( isset( $this->config_cache[ $group ][ $subkey ] ) ) {
				$value = $this->config_cache[ $group ][ $subkey ];

				// Decrypt sensitive values
				if ( in_array( $subkey, $this->sensitive_keys ) ) {
					return $this->decrypt_value( $value );
				}

				return $value;
			}

			return $default;
		}

		// Check in general settings first
		if ( isset( $this->config_cache['general'][ $key ] ) ) {
			return $this->config_cache['general'][ $key ];
		}

		// Check defaults
		if ( isset( $this->defaults[ $key ] ) ) {
			return $this->defaults[ $key ];
		}

		return $default;
	}

	/**
	 * Set configuration value
	 *
	 * @param string $key Configuration key (supports dot notation)
	 * @param mixed $value Configuration value
	 * @return bool Success status
	 */
	public function set( $key, $value ) {
		try {
			// Handle dot notation
			if ( strpos( $key, '.' ) !== false ) {
				list($group, $subkey) = explode( '.', $key, 2 );

				// Encrypt sensitive values
				if ( in_array( $subkey, $this->sensitive_keys ) ) {
					$value = $this->encrypt_value( $value );
				}

				// Update cache
				if ( ! isset( $this->config_cache[ $group ] ) ) {
					$this->config_cache[ $group ] = array();
				}
				$this->config_cache[ $group ][ $subkey ] = $value;

				// Save to WordPress options
				return update_option( $this->option_groups[ $group ], $this->config_cache[ $group ] );
			}

			// Set in general settings
			if ( ! isset( $this->config_cache['general'] ) ) {
				$this->config_cache['general'] = array();
			}
			$this->config_cache['general'][ $key ] = $value;

			return update_option( $this->option_groups['general'], $this->config_cache['general'] );

		} catch ( Exception $e ) {
			$this->logger->error( "Failed to set configuration: {$key}", array( 'exception' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Check if configuration key exists
	 *
	 * @param string $key Configuration key
	 * @return bool
	 */
	public function has( $key ) {
		return $this->get( $key ) !== null;
	}

	/**
	 * Alias for set() method for backward compatibility
	 *
	 * @param string $key Configuration key
	 * @param mixed $value Configuration value
	 * @return bool Success status
	 */
	public function set_config( $key, $value ) {
		return $this->set( $key, $value );
	}

	/**
	 * Alias for get() method for backward compatibility
	 *
	 * @param string $key Configuration key
	 * @param mixed $default Default value if key not found
	 * @return mixed Configuration value
	 */
	public function get_config( $key, $default = null ) {
		return $this->get( $key, $default );
	}

	/**
	 * Get all configuration for a group
	 *
	 * @param string $group Configuration group
	 * @return array Configuration array
	 */
	public function get_group( $group ) {
		if ( ! isset( $this->config_cache[ $group ] ) ) {
			return array();
		}

		$config = $this->config_cache[ $group ];

		// Decrypt sensitive values
		foreach ( $config as $key => $value ) {
			if ( in_array( $key, $this->sensitive_keys ) ) {
				$config[ $key ] = $this->decrypt_value( $value );
			}
		}

		return $config;
	}

	/**
	 * Set multiple configuration values for a group
	 *
	 * @param string $group Configuration group
	 * @param array $config Configuration array
	 * @return bool Success status
	 */
	public function set_group( $group, array $config ) {
		try {
			// Encrypt sensitive values
			foreach ( $config as $key => $value ) {
				if ( in_array( $key, $this->sensitive_keys ) ) {
					$config[ $key ] = $this->encrypt_value( $value );
				}
			}

			// Update cache
			$this->config_cache[ $group ] = array_merge(
				$this->config_cache[ $group ] ?? array(),
				$config
			);

			// Save to WordPress options
			if ( isset( $this->option_groups[ $group ] ) ) {
				return update_option( $this->option_groups[ $group ], $this->config_cache[ $group ] );
			}

			return false;

		} catch ( Exception $e ) {
			$this->logger->error( "Failed to set group configuration: {$group}", array( 'exception' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Synchronize individual options with grouped engine settings
	 * This ensures the ab_gpt_ai_engine_settings option is properly populated
	 *
	 * @return bool Success status
	 */
	public function sync_engine_settings() {
		try {
			// Map of individual option names to grouped setting keys
			$individual_option_map = array(
				'ab_api_key'                 => 'api_key',
				'ab_anthropic_api_key'       => 'anthropic_api_key',
				'ab_model'                   => 'model',
				'ab_temp'                    => 'temp',
				'ab_top_p'                   => 'top_p',
				'ab_best_oi'                 => 'best_oi',
				'ab_freq_pent'               => 'freq_pent',
				'ab_Presence_penalty'        => 'Presence_penalty',
				'ab_n'                       => 'n',
				'ab_enable_image_generation' => 'enable_image_generation',
			);

			$engine_settings = array();

			// Collect all individual options
			foreach ( $individual_option_map as $option_name => $setting_key ) {
				$value = get_option( $option_name, '' );
				if ( $value !== '' ) {
					$engine_settings[ $setting_key ] = $value;
				}
			}

			// Update grouped engine settings if we have any data
			if ( ! empty( $engine_settings ) ) {
				$this->config_cache['ai_engine'] = $engine_settings;
				update_option( $this->option_groups['ai_engine'], $engine_settings );

				// Special handling for image generation setting to ensure sync
				if ( isset( $engine_settings['enable_image_generation'] ) ) {
					update_option( 'ab_enable_image_generation', $engine_settings['enable_image_generation'] );
				}

				if ( $this->logger ) {
					$this->logger->debug( 'Engine settings synchronized', array( 'settings_count' => count( $engine_settings ) ) );
				}
				return true;
			}

			return false;

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error( 'Failed to sync engine settings', array( 'exception' => $e->getMessage() ) );
			}
			return false;
		}
	}

	/**
	 * Delete configuration key
	 *
	 * @param string $key Configuration key
	 * @return bool Success status
	 */
	public function delete( $key ) {
		try {
			if ( strpos( $key, '.' ) !== false ) {
				list($group, $subkey) = explode( '.', $key, 2 );

				if ( isset( $this->config_cache[ $group ][ $subkey ] ) ) {
					unset( $this->config_cache[ $group ][ $subkey ] );
					return update_option( $this->option_groups[ $group ], $this->config_cache[ $group ] );
				}
			} elseif ( isset( $this->config_cache['general'][ $key ] ) ) {
					unset( $this->config_cache['general'][ $key ] );
					return update_option( $this->option_groups['general'], $this->config_cache['general'] );
			}

			return true;

		} catch ( Exception $e ) {
			$this->logger->error( "Failed to delete configuration: {$key}", array( 'exception' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Get API key for specific provider.
	 *
	 * Opace AI Hub is a hard dependency and owns provider configuration, so its
	 * shared keys (option `ai_core_settings`) are the authoritative source.
	 * AI-Scribe has no key fields of its own any more.
	 *
	 * The AI-Scribe-side read that remains is a MIGRATION fallback, not a
	 * second configuration path: nothing can write it, and it exists only so
	 * that a 2.6.2 install whose keys the migration carried over and
	 * encrypted at rest (Migration_Service::migrate_engine_keys) keeps
	 * generating until those keys are re-entered in the hub.
	 *
	 * @param string $provider Provider name (openai, anthropic, etc.)
	 * @return string|null API key
	 */
	public function get_api_key( $provider = 'openai' ) {
		$key_map = array(
			'openai'    => 'ai_engine.api_key',
			'anthropic' => 'ai_engine.anthropic_api_key',
			'claude'    => 'ai_engine.anthropic_api_key',
			'gemini'    => 'ai_engine.gemini_api_key',
			'google'    => 'ai_engine.gemini_api_key',
			'grok'      => 'ai_engine.grok_api_key',
			'xai'       => 'ai_engine.grok_api_key',
		);

		if ( ! isset( $key_map[ $provider ] ) ) {
			return null;
		}

		$hub_key = $this->get_hub_api_key( $provider );
		if ( is_string( $hub_key ) && $hub_key !== '' ) {
			return $hub_key;
		}

		$key = $this->get( $key_map[ $provider ] );
		return ( is_string( $key ) && $key !== '' ) ? $key : null;
	}

	/**
	 * Read a provider key from the Opace AI Hub plugin's settings.
	 *
	 * @param string $provider openai|anthropic|claude|gemini|google|grok|xai
	 * @return string|null
	 */
	private function get_hub_api_key( $provider ) {
		if ( ! class_exists( 'AICore\\AICore' ) || ! function_exists( 'get_option' ) ) {
			return null;
		}
		$hub = get_option( 'ai_core_settings', array() );
		if ( ! is_array( $hub ) || empty( $hub ) ) {
			return null;
		}
		$hub_map = array(
			'openai'    => 'openai_api_key',
			'anthropic' => 'anthropic_api_key',
			'claude'    => 'anthropic_api_key',
			'gemini'    => 'gemini_api_key',
			'google'    => 'gemini_api_key',
			'grok'      => 'grok_api_key',
			'xai'       => 'grok_api_key',
		);
		if ( isset( $hub_map[ $provider ] ) && ! empty( $hub[ $hub_map[ $provider ] ] ) && is_string( $hub[ $hub_map[ $provider ] ] ) ) {
			return $hub[ $hub_map[ $provider ] ];
		}
		return null;
	}

	/**
	 * Validate API key format
	 *
	 * @param string $api_key API key to validate
	 * @param string $provider Provider name
	 * @return bool
	 */
	public function validate_api_key( $api_key, $provider = 'openai' ) {
		if ( empty( $api_key ) ) {
			return false;
		}

		switch ( $provider ) {
			case 'openai':
				return preg_match( '/^sk-[a-zA-Z0-9]{48,}$/', $api_key );
			case 'anthropic':
			case 'claude':
				return preg_match( '/^sk-ant-[a-zA-Z0-9\-_]{95,}$/', $api_key );
			default:
				return strlen( $api_key ) > 10; // Basic length check
		}
	}

	/**
	 * Get environment-specific configuration
	 *
	 * @return array Environment configuration
	 */
	public function get_environment_config() {
		return array(
			'is_debug'       => $this->get( 'debug_mode', false ),
			'is_production'  => ! WP_DEBUG,
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'plugin_version' => AI_SCRIBE_VER,
			'site_url'       => get_site_url(),
			'admin_url'      => admin_url(),
		);
	}

	/**
	 * Encrypt sensitive value using proper encryption
	 *
	 * @param string $value Value to encrypt
	 * @return string Encrypted value
	 */
	private function encrypt_value( $value ) {
		if ( empty( $value ) ) {
			return $value;
		}

		// Use WordPress salts to create a proper encryption key
		$key = $this->get_encryption_key();

		// Generate a random initialization vector
		$iv = openssl_random_pseudo_bytes( 16 );

		// Encrypt the value using AES-256-CBC
		$encrypted = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );

		if ( $encrypted === false ) {
			// Fallback to base64 for compatibility but log the issue
			ai_scribe_debug_log( 'AI-Scribe: OpenSSL encryption failed, falling back to base64' );
			return 'legacy:' . base64_encode( $value );
		}

		// Versioned format marker so decryption never has to guess:
		// prefix + base64(IV + ciphertext).
		return 'aisenc1:' . base64_encode( $iv . $encrypted );
	}

	/**
	 * Public wrapper so AJAX save paths can store keys encrypted at rest
	 * without re-implementing the crypto (settings controller, standalone
	 * mode). Returns the value in the same versioned format used by set().
	 *
	 * @param string $value Plaintext secret.
	 * @return string Encrypted storage value.
	 */
	public function encrypt_for_storage( $value ) {
		return $this->encrypt_value( $value );
	}

	/**
	 * Decrypt sensitive value
	 *
	 * @param string $encrypted_value Encrypted value
	 * @return string Decrypted value
	 */
	private function decrypt_value( $encrypted_value ) {
		if ( empty( $encrypted_value ) ) {
			return $encrypted_value;
		}

		// Versioned format (3.0.2+): 'aisenc1:' + base64(IV + ciphertext).
		if ( strpos( $encrypted_value, 'aisenc1:' ) === 0 ) {
			$decoded_data = base64_decode( substr( $encrypted_value, 8 ), true );
			if ( $decoded_data === false || strlen( $decoded_data ) <= 16 ) {
				return '';
			}
			$decrypted = openssl_decrypt(
				substr( $decoded_data, 16 ),
				'AES-256-CBC',
				$this->get_encryption_key(),
				0,
				substr( $decoded_data, 0, 16 )
			);
			// Fail closed: a marked ciphertext that will not decrypt (e.g.
			// salts changed) must never leak raw stored bytes to callers.
			return $decrypted !== false ? $decrypted : '';
		}

		// Handle legacy base64 encoded values for backward compatibility
		if ( strpos( $encrypted_value, 'legacy:' ) === 0 ) {
			$legacy_value = substr( $encrypted_value, 7 );
			$decoded      = base64_decode( $legacy_value, true );
			return $decoded !== false ? $decoded : $encrypted_value;
		}

		// Unprefixed values predate the versioned format: they are either the
		// old base64(IV + ciphertext) layout, an old plain-base64 value, or a
		// plaintext key saved before encryption covered this provider.
		$decoded_data = base64_decode( $encrypted_value, true );
		if ( $decoded_data === false ) {
			// Not base64 at all: stored plaintext (pre-hardening gemini/grok).
			return $encrypted_value;
		}

		// Check if this looks like the old encrypted format (IV + encrypted data)
		if ( strlen( $decoded_data ) < 16 ) {
			// Too short to be our format, likely legacy base64
			return $decoded_data;
		}

		// Extract IV and encrypted data
		$iv             = substr( $decoded_data, 0, 16 );
		$encrypted_data = substr( $decoded_data, 16 );

		// Get encryption key
		$key = $this->get_encryption_key();

		// Decrypt the value
		$decrypted = openssl_decrypt( $encrypted_data, 'AES-256-CBC', $key, 0, $iv );

		if ( $decrypted === false ) {
			// Not our ciphertext. Old plain-base64 values decode to printable
			// text; a plaintext key that merely happens to be base64-decodable
			// decodes to binary garbage - return the original in that case.
			if ( preg_match( '/^[\x20-\x7E]+$/', $decoded_data ) ) {
				return $decoded_data;
			}
			return $encrypted_value;
		}

		return $decrypted;
	}

	/**
	 * Get encryption key derived from WordPress salts
	 *
	 * @return string Encryption key
	 */
	private function get_encryption_key() {
		// Use WordPress salts to create a consistent encryption key
		$salt_data  = '';
		$salt_data .= defined( 'AUTH_SALT' ) ? AUTH_SALT : 'ai-scribe-auth';
		$salt_data .= defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : 'ai-scribe-secure';
		$salt_data .= defined( 'LOGGED_IN_SALT' ) ? LOGGED_IN_SALT : 'ai-scribe-logged';
		$salt_data .= defined( 'NONCE_SALT' ) ? NONCE_SALT : 'ai-scribe-nonce';

		// Create a 32-byte key using hash
		return hash( 'sha256', $salt_data . 'ai-scribe-encryption-key', true );
	}

	/**
	 * Reset configuration to defaults
	 *
	 * @param string|null $group Specific group to reset, or null for all
	 * @return bool Success status
	 */
	public function reset_to_defaults( $group = null ) {
		try {
			if ( $group && isset( $this->option_groups[ $group ] ) ) {
				delete_option( $this->option_groups[ $group ] );
				$this->config_cache[ $group ] = array();
			} else {
				// Reset all groups
				foreach ( $this->option_groups as $option_name ) {
					delete_option( $option_name );
				}
				$this->config_cache = array();
			}

			$this->ensure_defaults();
			return true;

		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to reset configuration', array( 'exception' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Export configuration (excluding sensitive data)
	 *
	 * @return array Configuration export
	 */
	public function export_config() {
		$export = array();

		foreach ( $this->config_cache as $group => $config ) {
			$export[ $group ] = array();
			foreach ( $config as $key => $value ) {
				if ( ! in_array( $key, $this->sensitive_keys ) ) {
					$export[ $group ][ $key ] = $value;
				}
			}
		}

		return $export;
	}
}
