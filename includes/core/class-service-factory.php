<?php
/**
 * Service Factory for AI-Scribe Plugin
 *
 * Creates and manages service instances with proper dependency injection.
 * Ensures all services use the existing infrastructure components.
 *
 * @package AI_Scribe
 * @subpackage Infrastructure
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Service_Factory
 *
 * Factory pattern implementation for creating service instances
 * with proper dependency injection and configuration.
 */
class AI_Scribe_Service_Factory {

	/**
	 * Logger instance
	 *
	 * @var AI_Scribe_Logger
	 */
	private static $logger;

	/**
	 * Opace AI Hub adapter instance
	 *
	 * @var AI_Scribe_AI_Core_Adapter
	 */
	private static $ai_core_adapter;

	/**
	 * Config manager instance
	 *
	 * @var AI_Scribe_Config_Manager
	 */
	private static $config_manager;

	/**
	 * Prompt manager instance
	 *
	 * @var AI_Scribe_Prompt_Manager
	 */
	private static $prompt_manager;

	/**
	 * Security service instance
	 *
	 * @var AI_Scribe_Security_Service
	 */
	private static $security_service;

	/**
	 * Service instances cache
	 *
	 * @var array
	 */
	private static $service_instances = array();

	/**
	 * Initialize the factory with core dependencies
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 * @param AI_Scribe_AI_Core_Adapter $ai_core_adapter Opace AI Hub adapter
	 * @param AI_Scribe_Config_Manager $config_manager Config manager
	 * @param AI_Scribe_Prompt_Manager $prompt_manager Prompt manager
	 * @param AI_Scribe_Security_Service $security_service Security service
	 * @return void
	 */
	public static function initialize(
		AI_Scribe_Logger $logger,
		AI_Scribe_AI_Core_Adapter $ai_core_adapter,
		AI_Scribe_Config_Manager $config_manager,
		AI_Scribe_Prompt_Manager $prompt_manager,
		AI_Scribe_Security_Service $security_service
	) {
		self::$logger           = $logger;
		self::$ai_core_adapter  = $ai_core_adapter;
		self::$config_manager   = $config_manager;
		self::$prompt_manager   = $prompt_manager;
		self::$security_service = $security_service;
	}

	/**
	 * Create Content Service instance
	 * 🚨 ARCHITECTURAL FIX: Now uses V4 Content Generation Service
	 *
	 * @return AI_Scribe_Content_Generation_Service
	 * @throws Exception If dependencies not initialized
	 */
	public static function create_content_service() {
		if ( ! isset( self::$service_instances['content'] ) ) {
			self::validate_dependencies();

			self::$service_instances['content'] = new AI_Scribe_Content_Generation_Service(
				self::$logger,
				self::$ai_core_adapter,
				self::$config_manager,
				self::$prompt_manager,
				self::$security_service
			);

			self::$logger->info( 'V4 Content Generation Service created successfully' );
		}

		return self::$service_instances['content'];
	}

	/**
	 * Create Image Service instance
	 *
	 * @return AI_Scribe_Image_Service
	 * @throws Exception If dependencies not initialized
	 */
	public static function create_image_service() {
		if ( ! isset( self::$service_instances['image'] ) ) {
			self::validate_dependencies();

			self::$service_instances['image'] = new AI_Scribe_Image_Service(
				self::$logger,
				self::$ai_core_adapter,
				self::$config_manager,
				self::$prompt_manager,
				self::$security_service
			);

			self::$logger->info( 'Image Service created successfully' );
		}

		return self::$service_instances['image'];
	}

	/**
	 * Create Engine Service instance
	 *
	 * @return AI_Scribe_Engine_Service
	 * @throws Exception If dependencies not initialized
	 */
	public static function create_engine_service() {
		if ( ! isset( self::$service_instances['engine'] ) ) {
			self::validate_dependencies();

			self::$service_instances['engine'] = new AI_Scribe_Engine_Service(
				self::$logger,
				self::$ai_core_adapter,
				self::$config_manager,
				self::$prompt_manager,
				self::$security_service
			);

			self::$logger->info( 'Engine Service created successfully' );
		}

		return self::$service_instances['engine'];
	}

	/**
	 * Create all services at once
	 *
	 * @return array Array of service instances
	 * @throws Exception If dependencies not initialized
	 */
	public static function create_all_services() {
		return array(
			'content' => self::create_content_service(),
			'image'   => self::create_image_service(),
			'engine'  => self::create_engine_service(),
		);
	}

	/**
	 * Get service instance by name
	 *
	 * @param string $service_name Service name (content, image, engine)
	 * @return object|null Service instance or null if not found
	 */
	public static function get_service( $service_name ) {
		switch ( $service_name ) {
			case 'content':
				return self::create_content_service();
			case 'image':
				return self::create_image_service();
			case 'engine':
				return self::create_engine_service();
			default:
				return null;
		}
	}

	/**
	 * Validate that all dependencies are initialized
	 *
	 * @throws Exception If any dependency is missing
	 */
	private static function validate_dependencies() {
		$dependencies = array(
			'logger'           => self::$logger,
			'ai_core_adapter'  => self::$ai_core_adapter,
			'config_manager'   => self::$config_manager,
			'prompt_manager'   => self::$prompt_manager,
			'security_service' => self::$security_service,
		);

		foreach ( $dependencies as $name => $dependency ) {
			if ( ! $dependency ) {
				throw new Exception( esc_html( "Service Factory dependency '{$name}' not initialized" ) );
			}
		}
	}

	/**
	 * Check if factory is properly initialized
	 *
	 * @return bool True if initialized
	 */
	public static function is_initialized() {
		try {
			self::validate_dependencies();
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Get factory status
	 *
	 * @return array Factory status information
	 */
	public static function get_status() {
		$status = array(
			'initialized'      => self::is_initialized(),
			'dependencies'     => array(
				'logger'           => ! is_null( self::$logger ),
				'ai_core_adapter'  => ! is_null( self::$ai_core_adapter ),
				'config_manager'   => ! is_null( self::$config_manager ),
				'prompt_manager'   => ! is_null( self::$prompt_manager ),
				'security_service' => ! is_null( self::$security_service ),
			),
			'services_created' => array_keys( self::$service_instances ),
			'timestamp'        => current_time( 'mysql' ),
		);

		return $status;
	}

	/**
	 * Reset factory state (useful for testing)
	 *
	 * @return void
	 */
	public static function reset() {
		self::$logger            = null;
		self::$ai_core_adapter   = null;
		self::$config_manager    = null;
		self::$prompt_manager    = null;
		self::$security_service  = null;
		self::$service_instances = array();
	}

	/**
	 * Validate all services
	 *
	 * @return array Validation results
	 */
	public static function validate_all_services() {
		$results = array();

		try {
			$content_service    = self::create_content_service();
			$results['content'] = $content_service->validate_service();
		} catch ( Exception $e ) {
			$results['content'] = array( 'error' => $e->getMessage() );
		}

		try {
			$image_service    = self::create_image_service();
			$results['image'] = $image_service->validate_service();
		} catch ( Exception $e ) {
			$results['image'] = array( 'error' => $e->getMessage() );
		}

		try {
			$engine_service    = self::create_engine_service();
			$results['engine'] = $engine_service->validate_service();
		} catch ( Exception $e ) {
			$results['engine'] = array( 'error' => $e->getMessage() );
		}

		return $results;
	}

	/**
	 * Get health status of all services
	 *
	 * @return array Health status
	 */
	public static function get_health_status() {
		$health = array(
			'factory_status'     => self::get_status(),
			'service_validation' => self::validate_all_services(),
			'timestamp'          => current_time( 'mysql' ),
		);

		// Add individual service health if available
		if ( isset( self::$service_instances['engine'] ) ) {
			$health['engine_health'] = self::$service_instances['engine']->get_health_status();
		}

		return $health;
	}
}
