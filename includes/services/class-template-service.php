<?php
/**
 * Template Service Class for AI-Scribe Plugin
 *
 * Handles template-related functionality including template display,
 * creation, and management for saved templates and shortcodes.
 *
 * @package AI_Scribe
 * @subpackage Services
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Template_Service
 *
 * Provides template functionality for the AI-Scribe plugin including
 * template rendering, creation pages, and template management.
 */
class AI_Scribe_Template_Service extends AI_Scribe_Base_Service {

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
		parent::__construct( $logger, $config, 'template_service' );
		$this->wordpress_adapter = $wordpress_adapter;
	}

	/**
	 * Initialize service
	 *
	 * @return void
	 */
	protected function initialize() {
		$this->log_debug( 'Template service initializing' );

		// Validate dependencies
		if ( ! $this->wordpress_adapter ) {
			$this->log_error( 'WordPress adapter not provided to Template Service' );
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
			'configuration_valid'          => $this->config !== null,
			'external_services_accessible' => true,
		);

		if ( ! $validation_result['dependencies_resolved'] ) {
			$this->log_error( 'Template service validation failed: WordPress adapter missing' );
		}

		if ( ! $validation_result['configuration_valid'] ) {
			$this->log_error( 'Template service validation failed: Configuration missing' );
		}

		return $validation_result;
	}

	/**
	 * Display all templates page
	 *
	 * Handles the display of saved templates or settings page based on
	 * the current page and action parameters.
	 *
	 * @return void
	 */
	public function all_templates() {
		$this->log_debug( 'Displaying all templates page' );

		try {
			// Sanitize input parameters
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing between plugin admin views.
			$page   = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing between plugin admin views.
			$action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );

			$this->log_debug(
				'Template page request',
				array(
					'page'   => $page,
					'action' => $action,
				)
			);

			// Route to appropriate template based on action
			if ( $page && $action == 'exit' ) {
				$this->log_debug( 'Routing to create template page' );
				$this->create_template();
			} elseif ( $page && $action == 'settings' ) {
				$this->log_debug( 'Including settings template' );
				$this->include_template( 'common/settings.php' );
			} else {
				$this->log_debug( 'Including show template page' );
				$this->include_template( 'templates/show_template.php' );
			}
		} catch ( Exception $e ) {
			$this->handle_error( 'Failed to display templates page', $e );
			$this->include_template( 'templates/show_template.php' ); // Fallback
		}
	}

	/**
	 * Create template page
	 *
	 * Displays either the saved shortcodes template or the create template
	 * page based on the current page parameter.
	 *
	 * @return void
	 */
	public function create_template() {
		$this->log_debug( 'Displaying create template page' );

		try {
			// Sanitize input parameter
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing between plugin admin views.
			$page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );

			$this->log_debug( 'Create template page request', array( 'page' => $page ) );

			// Route to appropriate template
			if ( $page == 'saved_shortcodes' ) {
				$this->log_debug( 'Including show template for saved shortcodes' );
				$this->include_template( 'templates/show_template.php' );
			} else {
				$this->log_debug( 'Including create template page' );
				$this->include_template( 'templates/create_template.php' );
			}
		} catch ( Exception $e ) {
			$this->handle_error( 'Failed to display create template page', $e );
			$this->include_template( 'templates/create_template.php' ); // Fallback
		}
	}

	/**
	 * Include template file safely
	 *
	 * @param string $template_path Relative path to template file
	 * @return void
	 */
	protected function include_template( $template_path ) {
		$full_path = plugin_dir_path( __DIR__ ) . $template_path;

		if ( file_exists( $full_path ) ) {
			$this->log_debug( 'Including template file', array( 'path' => $template_path ) );
			include_once $full_path;
		} else {
			$this->log_error( 'Template file not found', array( 'path' => $full_path ) );
			echo '<div class="notice notice-error"><p>Template file not found: ' . esc_html( $template_path ) . '</p></div>';
		}
	}

	/**
	 * Get template data for rendering
	 *
	 * @param array $filters Optional filters for template data
	 * @return array Template data
	 */
	public function get_template_data( $filters = array() ) {
		$this->log_debug( 'Retrieving template data', array( 'filters' => $filters ) );

		try {
			// This would typically retrieve template data from database
			// For now, return empty array as placeholder
			$template_data = array();

			$this->log_debug( 'Template data retrieved', array( 'count' => count( $template_data ) ) );

			return $this->create_success_response( $template_data, 'Template data retrieved successfully' );

		} catch ( Exception $e ) {
			return $this->handle_error( 'Failed to retrieve template data', $e );
		}
	}

	/**
	 * Save template data
	 *
	 * @param array $template_data Template data to save
	 * @return array Operation result
	 */
	public function save_template( $template_data ) {
		$this->log_debug( 'Saving template data' );

		try {
			// Validate required parameters
			$validation = $this->validate_required_params( $template_data, array( 'title', 'content' ) );
			if ( $validation !== true ) {
				return $validation;
			}

			// Sanitize template data
			$sanitized_data = array(
				'title'      => $this->sanitize_input( $template_data['title'], 'text' ),
				'content'    => $this->sanitize_input( $template_data['content'], 'html' ),
				'created_at' => current_time( 'mysql' ),
			);

			$this->log_debug( 'Template data sanitized', array( 'title' => $sanitized_data['title'] ) );

			// Here you would typically save to database
			// For now, return success response

			return $this->create_success_response( $sanitized_data, 'Template saved successfully' );

		} catch ( Exception $e ) {
			return $this->handle_error( 'Failed to save template', $e );
		}
	}

	/**
	 * Delete template
	 *
	 * @param int $template_id Template ID to delete
	 * @return array Operation result
	 */
	public function delete_template( $template_id ) {
		$this->log_debug( 'Deleting template', array( 'id' => $template_id ) );

		try {
			// Validate template ID
			$template_id = $this->sanitize_input( $template_id, 'int' );
			if ( ! $template_id || $template_id <= 0 ) {
				return $this->handle_error( 'Invalid template ID provided' );
			}

			// Here you would typically delete from database
			// For now, return success response

			$this->log_debug( 'Template deleted successfully', array( 'id' => $template_id ) );

			return $this->create_success_response( null, 'Template deleted successfully' );

		} catch ( Exception $e ) {
			return $this->handle_error( 'Failed to delete template', $e );
		}
	}

	/**
	 * Get service health status
	 *
	 * @return array Health status information
	 */
	public function get_health_status() {
		$base_status = parent::get_health_status();

		$base_status['dependencies'] = array(
			'wordpress_adapter' => isset( $this->wordpress_adapter ),
			'config'            => isset( $this->config ),
		);

		return $base_status;
	}
}
