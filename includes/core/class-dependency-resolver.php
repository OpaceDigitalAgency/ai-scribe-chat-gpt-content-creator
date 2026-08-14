<?php
/**
 * Dependency Resolver for AI-Scribe Plugin
 *
 * Manages dependency resolution and service initialization order
 * to ensure proper plugin bootstrap and service availability.
 *
 * @package AI_Scribe
 * @subpackage Infrastructure
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Dependency_Resolver
 *
 * Handles dependency resolution, service initialization order,
 * and ensures all required services are available when needed.
 */
class AI_Scribe_Dependency_Resolver {

	/**
	 * Logger instance
	 *
	 * @var AI_Scribe_Logger
	 */
	private $logger;

	/**
	 * Service container instance
	 *
	 * @var AI_Scribe_Service_Container
	 */
	private $container;

	/**
	 * Dependency graph
	 *
	 * @var array
	 */
	private $dependency_graph = array();

	/**
	 * Resolved services
	 *
	 * @var array
	 */
	private $resolved_services = array();

	/**
	 * Service initialization order
	 *
	 * @var array
	 */
	private $initialization_order = array();

	/**
	 * Constructor
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 * @param AI_Scribe_Service_Container $container Service container
	 */
	public function __construct( AI_Scribe_Logger $logger, AI_Scribe_Service_Container $container ) {
		$this->logger    = $logger;
		$this->container = $container;
		$this->initialize();
	}

	/**
	 * Initialize dependency resolver
	 *
	 * @return void
	 */
	private function initialize() {
		$this->build_dependency_graph();
		$this->calculate_initialization_order();
		$this->logger->debug( 'Dependency Resolver initialized' );
	}

	/**
	 * Build dependency graph from service container
	 *
	 * @return void
	 */
	private function build_dependency_graph() {
		$registered_services = $this->container->getRegisteredServices();

		foreach ( $registered_services as $service_id ) {
			$this->dependency_graph[ $service_id ] = $this->get_service_dependencies( $service_id );
		}

		$this->logger->debug( 'Dependency graph built', array( 'services' => count( $registered_services ) ) );
	}

	/**
	 * Get dependencies for a service
	 *
	 * @param string $service_id Service identifier
	 * @return array Service dependencies
	 */
	private function get_service_dependencies( $service_id ) {
		// Complete dependency mapping for all registered services
		$known_dependencies = array(
			// Core services
			'logger'              => array(),
			'config'              => array( 'logger' ),
			'error_handler'       => array( 'logger' ),
			'wordpress_adapter'   => array( 'logger' ),
			'ai_core_adapter'     => array( 'logger', 'config' ),
			'prompt_manager'      => array( 'logger', 'config' ),
			'dependency_resolver' => array( 'logger' ),

			// Business services
			'security_service'    => array( 'logger', 'config', 'wordpress_adapter' ),
			'service_factory'     => array( 'logger', 'config' ),
			'content_service'     => array( 'logger', 'ai_core_adapter', 'config', 'prompt_manager', 'security_service' ),
			'image_html_service'  => array( 'logger', 'config' ),
			'image_service'       => array( 'logger', 'ai_core_adapter', 'config', 'prompt_manager', 'security_service', 'image_html_service' ),
			'engine_service'      => array( 'logger', 'ai_core_adapter', 'config', 'prompt_manager', 'security_service' ),
			'pricing_service'     => array( 'logger', 'config', 'wordpress_adapter' ),
			'template_service'    => array( 'logger', 'config', 'wordpress_adapter' ),
			'post_service'        => array( 'logger', 'config', 'wordpress_adapter', 'security_service' ),
			'shortcode_service'   => array( 'logger', 'config', 'wordpress_adapter', 'security_service' ),
			'utility_service'     => array( 'logger', 'config' ),
			'admin_service'       => array( 'logger', 'config', 'wordpress_adapter' ),
			'workflow_service'    => array( 'logger', 'config', 'content_service', 'image_service', 'security_service', 'image_html_service' ),
		);

		return $known_dependencies[ $service_id ] ?? array();
	}

	/**
	 * Calculate service initialization order using topological sort
	 *
	 * @return void
	 */
	private function calculate_initialization_order() {
		$this->initialization_order = $this->topological_sort( $this->dependency_graph );

		$this->logger->debug(
			'Service initialization order calculated',
			array(
				'order' => $this->initialization_order,
			)
		);
	}

	/**
	 * Perform topological sort on dependency graph
	 *
	 * @param array $graph Dependency graph
	 * @return array Sorted service order
	 */
	private function topological_sort( array $graph ) {
		$sorted   = array();
		$visited  = array();
		$visiting = array();

		foreach ( array_keys( $graph ) as $service ) {
			if ( ! isset( $visited[ $service ] ) ) {
				$this->visit_service( $service, $graph, $visited, $visiting, $sorted );
			}
		}

		return array_reverse( $sorted );
	}

	/**
	 * Visit service in topological sort
	 *
	 * @param string $service Service ID
	 * @param array $graph Dependency graph
	 * @param array &$visited Visited services
	 * @param array &$visiting Currently visiting services
	 * @param array &$sorted Sorted result
	 * @return void
	 * @throws RuntimeException If circular dependency detected
	 */
	private function visit_service( $service, array $graph, array &$visited, array &$visiting, array &$sorted ) {
		if ( isset( $visiting[ $service ] ) ) {
			throw new RuntimeException( esc_html( "Circular dependency detected involving service: {$service}" ) );
		}

		if ( isset( $visited[ $service ] ) ) {
			return;
		}

		$visiting[ $service ] = true;

		foreach ( $graph[ $service ] as $dependency ) {
			if ( isset( $graph[ $dependency ] ) ) {
				$this->visit_service( $dependency, $graph, $visited, $visiting, $sorted );
			}
		}

		unset( $visiting[ $service ] );
		$visited[ $service ] = true;
		$sorted[]            = $service;
	}

	/**
	 * Resolve all services in correct order
	 *
	 * @return array Resolution results
	 */
	public function resolve_all_services() {
		$results = array();

		foreach ( $this->initialization_order as $service_id ) {
			$result                 = $this->resolve_service( $service_id );
			$results[ $service_id ] = $result;

			if ( ! $result['success'] ) {
				$this->logger->error( "Failed to resolve service: {$service_id}", $result );
				break;
			}
		}

		return $results;
	}

	/**
	 * Resolve individual service
	 *
	 * @param string $service_id Service identifier
	 * @return array Resolution result
	 */
	public function resolve_service( $service_id ) {
		try {
			$start_time = microtime( true );

			// Check if already resolved
			if ( isset( $this->resolved_services[ $service_id ] ) ) {
				return array(
					'success'         => true,
					'service_id'      => $service_id,
					'cached'          => true,
					'resolution_time' => 0,
				);
			}

			// Resolve dependencies first
			$dependencies = $this->dependency_graph[ $service_id ] ?? array();
			foreach ( $dependencies as $dependency_id ) {
				if ( ! isset( $this->resolved_services[ $dependency_id ] ) ) {
					$dep_result = $this->resolve_service( $dependency_id );
					if ( ! $dep_result['success'] ) {
						return array(
							'success'          => false,
							'service_id'       => $service_id,
							'error'            => "Failed to resolve dependency: {$dependency_id}",
							'dependency_error' => $dep_result,
						);
					}
				}
			}

			// Get service from container
			$service = $this->container->get( $service_id );

			// Validate service if it has validation method
			if ( method_exists( $service, 'validate_service' ) ) {
				$validation_result = $service->validate_service();

				// Handle different validation return types
				$is_valid = $this->is_validation_successful( $validation_result );

				if ( ! $is_valid ) {
					$error_message = $this->extract_validation_error( $validation_result );
					return array(
						'success'          => false,
						'service_id'       => $service_id,
						'error'            => 'Service validation failed: ' . $error_message,
						'validation_error' => $validation_result,
					);
				}
			}

			$this->resolved_services[ $service_id ] = $service;
			$resolution_time                        = microtime( true ) - $start_time;

			$this->logger->debug(
				"Service resolved: {$service_id}",
				array(
					'resolution_time' => $resolution_time,
					'dependencies'    => $dependencies,
				)
			);

			return array(
				'success'               => true,
				'service_id'            => $service_id,
				'cached'                => false,
				'resolution_time'       => $resolution_time,
				'dependencies_resolved' => count( $dependencies ),
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				"Service resolution failed: {$service_id}",
				array(
					'exception' => $e->getMessage(),
					'trace'     => $e->getTraceAsString(),
				)
			);

			return array(
				'success'        => false,
				'service_id'     => $service_id,
				'error'          => $e->getMessage(),
				'exception_type' => get_class( $e ),
			);
		}
	}

	/**
	 * Check if validation result indicates success
	 *
	 * @param mixed $validation_result Result from validate_service()
	 * @return bool True if validation successful
	 */
	private function is_validation_successful( $validation_result ) {
		// Handle different return types:
		// - true: Success
		// - false: Failure
		// - string: Error message (failure)
		// - array with 'success' => false: Error response (failure)
		// - array with validation data but no 'success' key: Check individual fields

		if ( $validation_result === true ) {
			return true;
		}

		if ( $validation_result === false || is_string( $validation_result ) ) {
			return false;
		}

		if ( is_array( $validation_result ) ) {
			// If it has a 'success' key, use that
			if ( isset( $validation_result['success'] ) ) {
				return $validation_result['success'] === true;
			}

			// For validation arrays without 'success' key, check if all validations passed
			// This handles cases like post_service which returns validation status arrays
			foreach ( $validation_result as $key => $value ) {
				if ( is_bool( $value ) && ! $value ) {
					return false;
				}
			}
			return true;
		}

		// Default to false for unknown types
		return false;
	}

	/**
	 * Extract error message from validation result
	 *
	 * @param mixed $validation_result Result from validate_service()
	 * @return string Error message
	 */
	private function extract_validation_error( $validation_result ) {
		if ( is_string( $validation_result ) ) {
			return $validation_result;
		}

		if ( is_array( $validation_result ) ) {
			// Check for error message in array
			if ( isset( $validation_result['error'] ) ) {
				return $validation_result['error'];
			}

			if ( isset( $validation_result['message'] ) ) {
				return $validation_result['message'];
			}

			// For validation status arrays, build error message from failed validations
			$errors = array();
			foreach ( $validation_result as $key => $value ) {
				if ( is_bool( $value ) && ! $value ) {
					$errors[] = str_replace( '_', ' ', $key );
				}
			}

			if ( ! empty( $errors ) ) {
				return 'Failed validations: ' . implode( ', ', $errors );
			}

			return 'Validation failed with unknown error structure';
		}

		return 'Unknown validation error';
	}

	/**
	 * Check if service is resolved
	 *
	 * @param string $service_id Service identifier
	 * @return bool Resolution status
	 */
	public function is_service_resolved( $service_id ) {
		return isset( $this->resolved_services[ $service_id ] );
	}

	/**
	 * Get resolved service
	 *
	 * @param string $service_id Service identifier
	 * @return mixed|null Service instance or null
	 */
	public function get_resolved_service( $service_id ) {
		return $this->resolved_services[ $service_id ] ?? null;
	}

	/**
	 * Validate dependency graph for circular dependencies
	 *
	 * @return array Validation result
	 */
	public function validate_dependency_graph() {
		try {
			$this->topological_sort( $this->dependency_graph );

			return array(
				'valid'          => true,
				'message'        => 'No circular dependencies detected',
				'services_count' => count( $this->dependency_graph ),
			);

		} catch ( RuntimeException $e ) {
			return array(
				'valid'          => false,
				'message'        => $e->getMessage(),
				'services_count' => count( $this->dependency_graph ),
			);
		}
	}

	/**
	 * Get dependency graph visualization
	 *
	 * @return array Dependency graph data
	 */
	public function get_dependency_graph() {
		return array(
			'graph'                => $this->dependency_graph,
			'initialization_order' => $this->initialization_order,
			'resolved_services'    => array_keys( $this->resolved_services ),
			'total_services'       => count( $this->dependency_graph ),
		);
	}

	/**
	 * Get resolution statistics
	 *
	 * @return array Resolution statistics
	 */
	public function get_resolution_stats() {
		$total_services = count( $this->dependency_graph );
		$resolved_count = count( $this->resolved_services );

		return array(
			'total_services'         => $total_services,
			'resolved_services'      => $resolved_count,
			'pending_services'       => $total_services - $resolved_count,
			'resolution_percentage'  => $total_services > 0 ? ( $resolved_count / $total_services ) * 100 : 0,
			'initialization_order'   => $this->initialization_order,
			'dependency_graph_valid' => $this->validate_dependency_graph()['valid'],
		);
	}

	/**
	 * Reset resolution state (for testing)
	 *
	 * @return void
	 */
	public function reset_resolution_state() {
		$this->resolved_services = array();
		$this->logger->debug( 'Dependency resolution state reset' );
	}

	/**
	 * Add service dependency
	 *
	 * @param string $service_id Service identifier
	 * @param array $dependencies Service dependencies
	 * @return bool Success status
	 */
	public function add_service_dependency( $service_id, array $dependencies ) {
		try {
			$this->dependency_graph[ $service_id ] = $dependencies;
			$this->calculate_initialization_order();

			$this->logger->debug(
				"Added service dependency: {$service_id}",
				array(
					'dependencies' => $dependencies,
				)
			);

			return true;

		} catch ( Exception $e ) {
			$this->logger->error(
				"Failed to add service dependency: {$service_id}",
				array(
					'exception' => $e->getMessage(),
				)
			);

			return false;
		}
	}

	/**
	 * Remove service from dependency graph
	 *
	 * @param string $service_id Service identifier
	 * @return bool Success status
	 */
	public function remove_service( $service_id ) {
		if ( isset( $this->dependency_graph[ $service_id ] ) ) {
			unset( $this->dependency_graph[ $service_id ] );
			unset( $this->resolved_services[ $service_id ] );

			// Remove from other services' dependencies
			foreach ( $this->dependency_graph as &$dependencies ) {
				$dependencies = array_filter(
					$dependencies,
					function ( $dep ) use ( $service_id ) {
						return $dep !== $service_id;
					}
				);
			}

			$this->calculate_initialization_order();

			$this->logger->debug( "Removed service: {$service_id}" );
			return true;
		}

		return false;
	}
}
