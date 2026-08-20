<?php
/**
 * Service Container for AI-Scribe Plugin
 *
 * Implements dependency injection container for managing all services
 * and their dependencies following the Singleton pattern for global access.
 *
 * @package AI_Scribe
 * @subpackage Infrastructure
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Service_Container
 *
 * Dependency injection container for managing services and their dependencies.
 * Provides singleton pattern for global access and automatic dependency resolution.
 */
class AI_Scribe_Service_Container {

	/**
	 * Singleton instance
	 *
	 * @var AI_Scribe_Service_Container|null
	 */
	private static $instance = null;

	/**
	 * Registered service factories
	 *
	 * @var array
	 */
	private $factories = array();

	/**
	 * Singleton service instances
	 *
	 * @var array
	 */
	private $singletons = array();

	/**
	 * Service dependencies mapping
	 *
	 * @var array
	 */
	private $dependencies = array();

	/**
	 * Service configuration storage
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		// Initialize container
	}

	/**
	 * Get singleton instance
	 *
	 * @return AI_Scribe_Service_Container
	 */
	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register a service factory
	 *
	 * @param string $id Service identifier
	 * @param callable $factory Factory function that creates the service
	 * @param array $dependencies Array of dependency service IDs
	 * @param bool $singleton Whether to treat as singleton (default: true)
	 * @return void
	 */
	public function register( $id, callable $factory, array $dependencies = array(), $singleton = true ) {
		$serviceConfig = array(
			'factory'      => $factory,
			'singleton'    => $singleton,
			'dependencies' => $dependencies,
		);

		$this->factories[ $id ]    = $serviceConfig;
		$this->services[ $id ]     = $serviceConfig;
		$this->dependencies[ $id ] = $dependencies;
	}

	/**
	 * Get a service instance
	 *
	 * @param string $id Service identifier
	 * @return mixed Service instance
	 * @throws InvalidArgumentException If service not found
	 */
	public function get( $id ) {
		if ( ! $this->has( $id ) ) {
			throw new InvalidArgumentException( esc_html( "Service '{$id}' not found in container." ) );
		}

		$serviceConfig = $this->factories[ $id ];

		// Return singleton if already instantiated
		if ( $serviceConfig['singleton'] && isset( $this->singletons[ $id ] ) ) {
			return $this->singletons[ $id ];
		}

		// Resolve dependencies
		$dependencies = $this->resolveDependencies( $id, array() );

		// Create service instance
		$factory  = $serviceConfig['factory'];
		$instance = call_user_func_array( $factory, $dependencies );

		// Store singleton
		if ( $serviceConfig['singleton'] ) {
			$this->singletons[ $id ] = $instance;
		}

		return $instance;
	}

	/**
	 * Check if service is registered
	 *
	 * @param string $id Service identifier
	 * @return bool
	 */
	public function has( $id ) {
		return isset( $this->factories[ $id ] );
	}

	/**
	 * Resolve service dependencies
	 *
	 * @param string $id Service identifier
	 * @return array Resolved dependency instances
	 * @throws RuntimeException If circular dependency detected
	 */
	private function resolveDependencies( $id, array $resolving = array() ) {
		// Check for circular dependencies
		if ( in_array( $id, $resolving ) ) {
			throw new RuntimeException( esc_html( "Circular dependency detected for service '{$id}'." ) );
		}

		$resolving[]  = $id;
		$dependencies = array();

		foreach ( $this->dependencies[ $id ] as $dependencyId ) {
			if ( ! $this->has( $dependencyId ) ) {
				throw new InvalidArgumentException( esc_html( "Dependency '{$dependencyId}' not found for service '{$id}'." ) );
			}

			// Recursively resolve dependencies with the resolving chain
			$dependencies[] = $this->resolveServiceWithChain( $dependencyId, $resolving );
		}

		return $dependencies;
	}

	/**
	 * Resolve a service with circular dependency detection
	 *
	 * @param string $id Service identifier
	 * @param array $resolving Currently resolving services chain
	 * @return mixed Service instance
	 * @throws RuntimeException If circular dependency detected
	 */
	private function resolveServiceWithChain( $id, array $resolving = array() ) {
		// Check for circular dependencies
		if ( in_array( $id, $resolving ) ) {
			throw new RuntimeException( esc_html( "Circular dependency detected for service '{$id}'." ) );
		}

		// Check if service exists
		if ( ! $this->has( $id ) ) {
			throw new InvalidArgumentException( esc_html( "Service '{$id}' not found." ) );
		}

		$serviceConfig = $this->services[ $id ];

		// Return singleton if already created
		if ( $serviceConfig['singleton'] && isset( $this->singletons[ $id ] ) ) {
			return $this->singletons[ $id ];
		}

		// Resolve dependencies with updated resolving chain
		$dependencies = $this->resolveDependencies( $id, $resolving );

		// Create service instance
		$factory  = $serviceConfig['factory'];
		$instance = call_user_func_array( $factory, $dependencies );

		// Store singleton
		if ( $serviceConfig['singleton'] ) {
			$this->singletons[ $id ] = $instance;
		}

		return $instance;
	}

	/**
	 * Register core AI-Scribe services
	 *
	 * @return void
	 */
	public function registerCoreServices() {
		// Register Logger
		$this->register(
			'logger',
			function () {
				return new AI_Scribe_Logger();
			}
		);

		// Register Config Manager
		$this->register(
			'config',
			function ( $logger ) {
				return new AI_Scribe_Config_Manager( $logger );
			},
			array( 'logger' )
		);

		// Register Error Handler
		$this->register(
			'error_handler',
			function ( $logger ) {
				return new AI_Scribe_Error_Handler( $logger );
			},
			array( 'logger' )
		);

		// Register WordPress Adapter
		$this->register(
			'wordpress_adapter',
			function ( $logger ) {
				return new AI_Scribe_WordPress_Adapter( $logger );
			},
			array( 'logger' )
		);

		// Register Opace AI Hub Adapter
		$this->register(
			'ai_core_adapter',
			function ( $logger, $config ) {
				return new AI_Scribe_AI_Core_Adapter( $logger, $config );
			},
			array( 'logger', 'config' )
		);

		// Register Dependency Resolver
		$this->register(
			'dependency_resolver',
			function ( $logger ) {
				return new AI_Scribe_Dependency_Resolver( $logger, $this );
			},
			array( 'logger' )
		);
	}

	/**
	 * Initialize all core services
	 *
	 * @return void
	 */
	public function initializeCoreServices() {
		// Pre-load critical services
		$this->get( 'logger' );
		$this->get( 'config' );
		$this->get( 'error_handler' );
		$this->get( 'wordpress_adapter' );
		$this->get( 'ai_core_adapter' );
	}

	/**
	 * Get all registered service IDs
	 *
	 * @return array
	 */
	public function getRegisteredServices() {
		return array_keys( $this->factories );
	}

	/**
	 * Clear all singletons (useful for testing)
	 *
	 * @return void
	 */
	public function clearSingletons() {
		$this->singletons = array();
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization
	 */
	public function __wakeup() {
		throw new Exception( esc_html( 'Cannot unserialize singleton' ) );
	}
}
