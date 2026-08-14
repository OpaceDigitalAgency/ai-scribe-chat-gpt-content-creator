<?php
/**
 * Shortcode Service Class for AI-Scribe Plugin
 *
 * Handles shortcode-related functionality including shortcode creation,
 * data management, content storage, and shortcode rendering.
 *
 * @package AI_Scribe
 * @subpackage Services
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Shortcode_Service
 *
 * Provides shortcode functionality for the AI-Scribe plugin including
 * shortcode creation, data storage, content management, and rendering.
 */
class AI_Scribe_Shortcode_Service extends AI_Scribe_Base_Service {

	/**
	 * WordPress adapter instance
	 *
	 * @var AI_Scribe_WordPress_Adapter
	 */
	protected $wordpress_adapter;

	/**
	 * Security service instance
	 *
	 * @var AI_Scribe_Security_Service
	 */
	protected $security_service;

	/**
	 * Database table name for shortcode storage
	 *
	 * @var string
	 */
	protected $table_name;

	/**
	 * Constructor
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 * @param AI_Scribe_Config_Manager $config Configuration manager
	 * @param AI_Scribe_WordPress_Adapter $wordpress_adapter WordPress adapter
	 * @param AI_Scribe_Security_Service $security_service Security service
	 */
	public function __construct( $logger, $config, $wordpress_adapter, $security_service = null ) {
		parent::__construct( $logger, $config, 'shortcode_service' );
		$this->wordpress_adapter = $wordpress_adapter;
		$this->security_service  = $security_service;

		global $wpdb;
		$this->table_name = $wpdb->prefix . 'article_builder';
	}

	/**
	 * Initialize service
	 *
	 * @return void
	 */
	protected function initialize() {
		$this->log_debug( 'Shortcode service initializing' );

		// Validate dependencies
		if ( ! $this->wordpress_adapter ) {
			$this->log_error( 'WordPress adapter not provided to Shortcode Service' );
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
			'external_services_accessible' => $this->check_database_table(),
		);

		if ( ! $validation_result['dependencies_resolved'] ) {
			$this->log_error( 'Shortcode service validation failed: WordPress adapter missing' );
			return 'WordPress adapter missing';
		}

		if ( ! $validation_result['configuration_valid'] ) {
			$this->log_error( 'Shortcode service validation failed: Configuration missing' );
			return 'Configuration missing';
		}

		if ( ! $validation_result['external_services_accessible'] ) {
			$this->log_error( 'Shortcode service validation failed: Database table not accessible' );
			return 'Database table not accessible';
		}

		// All validations passed
		return true;
	}

	/**
	 * Check if database table exists and is accessible
	 *
	 * @return bool Table accessibility status
	 */
	protected function check_database_table() {
		global $wpdb;

		// During early initialization, $wpdb might not be available yet
		if ( ! $wpdb ) {
			$this->log_debug( 'Database check skipped - WordPress not fully loaded yet' );
			return true; // Allow service to initialize, table check will happen later
		}

		try {
			// Fix SQL injection vulnerability by using $wpdb->prepare() for dynamic queries
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name ) ) === $this->table_name;

			// If table doesn't exist, try to create it
			if ( ! $table_exists ) {
				$this->log_debug( 'Database table missing, attempting to create it' );
				$this->create_database_table();

				// Check again after creation attempt
				$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name ) ) === $this->table_name;
			}

			$this->log_debug( 'Database table check', array( 'exists' => $table_exists ) );
			return $table_exists;
		} catch ( Exception $e ) {
			$this->log_error( 'Database table check failed', array( 'error' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Create the database table if it doesn't exist
	 */
	private function create_database_table() {
		global $wpdb;

		try {
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE {$this->table_name} (
                id int(20) NOT NULL AUTO_INCREMENT,
                title text DEFAULT NULL,
                heading text DEFAULT NULL,
                keyword text DEFAULT NULL,
                intro text DEFAULT NULL,
                tagline text DEFAULT NULL,
                article text DEFAULT NULL,
                conclusion longtext DEFAULT NULL,
                qna longtext DEFAULT NULL,
                metadata longtext DEFAULT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->table_name ) ) ) === $this->table_name;

			if ( $result === false ) {
				$this->log_error(
					'Failed to create database table',
					array(
						'table' => $this->table_name,
						'error' => $wpdb->last_error,
					)
				);
			} else {
				$this->log_debug( 'Database table created successfully', array( 'table' => $this->table_name ) );
			}
		} catch ( Exception $e ) {
			$this->log_error( 'Exception during table creation', array( 'error' => $e->getMessage() ) );
		}
	}

	/**
	 * Send shortcode page - Save generated content as shortcode
	 *
	 * Handles AJAX request to save generated article content as a shortcode
	 * in the custom database table for later retrieval and display.
	 *
	 * @return void Sends JSON response directly
	 */
	public function send_shortcode_page() {
		$this->log_debug( 'Processing shortcode creation request' );

		// Validate nonce for security
		if ( ! isset( $_POST['security'] ) || ! check_ajax_referer( 'ai_scribe_nonce', 'security', false ) ) {
			$this->log_warning( 'Shortcode creation failed: Invalid nonce' );
			wp_send_json_error(
				array(
					'msg'           => 'Invalid request. Please refresh the page and try again.',
					'nonce_expired' => true,
				)
			);
			return;
		}

		// P8 §14.2: capability check (wizard save-as-shortcode).
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'msg' => 'You do not have permission to do this.' ) );
			return;
		}

		ob_start();

		try {
			// Sanitize and process input data
			$shortcode_data = $this->sanitize_shortcode_data( $_POST );

			$this->log_debug(
				'Shortcode data sanitized',
				array(
					'title'          => $shortcode_data['title'],
					'content_length' => strlen( $shortcode_data['article'] ),
				)
			);

			// Insert data into database
			$result = $this->insert_shortcode_data( $shortcode_data );

			if ( $result !== false ) {
				$this->log_info( 'Shortcode data saved successfully', array( 'insert_id' => $result ) );
				wp_send_json_success(
					array(
						'msg'          => 'Data saved successfully.',
						'shortcode_id' => $result,
					)
				);
			} else {
				$this->log_error( 'Failed to save shortcode data' );
				wp_send_json_error(
					array(
						'msg' => 'An error occurred while saving your data.',
					)
				);
			}
		} catch ( Exception $e ) {
			$error_response = $this->handle_error( 'Shortcode creation failed', $e );
			wp_send_json_error( array( 'msg' => $error_response['error'] ) );
		}

		return ob_get_clean();
	}

	/**
	 * Sanitize shortcode data from request
	 *
	 * @param array $raw_data Raw POST data
	 * @return array Sanitized shortcode data
	 */
	protected function sanitize_shortcode_data( $raw_data ) {
		// Sanitize arrays of data
		$heading_data    = array_map( 'sanitize_text_field', $raw_data['headingData'] ?? array() );
		$keyword_data    = array_map( 'sanitize_text_field', $raw_data['keywordData'] ?? array() );
		$intro_data      = array_map( 'sanitize_text_field', $raw_data['introData'] ?? array() );
		$tagline_data    = array_map( 'sanitize_text_field', $raw_data['taglineData'] ?? array() );
		$conclusion_data = array_map( 'sanitize_text_field', $raw_data['conclusionData'] ?? array() );
		$qna_data        = array_map( 'sanitize_text_field', $raw_data['qnaData'] ?? array() );
		$meta_data       = array_map( 'sanitize_text_field', $raw_data['metaData'] ?? array() );

		// Process article content
		$article_val     = wp_kses_post( $raw_data['articleVal'] ?? '' );
		$article_content = preg_replace( "/<br>|\n|<br( ?)>/", '', $article_val );

		// Extract title from H1 tag if present
		preg_match( '/<h1>(.*?)<\/h1>/', $article_val, $matches );
		$title = isset( $matches[1] ) ? wp_strip_all_tags( $matches[1] ) : '';

		return array(
			'title'      => $title,
			'heading'    => implode( ' ', $heading_data ),
			'keyword'    => implode( ' ', $keyword_data ),
			'intro'      => implode( ' ', $intro_data ),
			'tagline'    => implode( ' ', $tagline_data ),
			'article'    => $article_content,
			'conclusion' => implode( ' ', $conclusion_data ),
			'qna'        => implode( ' ', $qna_data ),
			'metadata'   => maybe_serialize( $meta_data ),
			'title_data' => sanitize_title( $raw_data['titleData'] ?? '' ),
		);
	}

	/**
	 * Insert shortcode data into database
	 *
	 * @param array $shortcode_data Sanitized shortcode data
	 * @return int|false Insert ID on success, false on failure
	 */
	protected function insert_shortcode_data( $shortcode_data ) {
		global $wpdb;

		$this->log_debug( 'Inserting shortcode data into database' );

		$result = $wpdb->insert(
			$this->table_name,
			array(
				'title'      => $shortcode_data['title'],
				'heading'    => $shortcode_data['heading'],
				'keyword'    => $shortcode_data['keyword'],
				'intro'      => $shortcode_data['intro'],
				'tagline'    => $shortcode_data['tagline'],
				'article'    => $shortcode_data['article'],
				'conclusion' => $shortcode_data['conclusion'],
				'qna'        => $shortcode_data['qna'],
				'metadata'   => $shortcode_data['metadata'],
			),
			array(
				'%s', // title
				'%s', // heading
				'%s', // keyword
				'%s', // intro
				'%s', // tagline
				'%s', // article
				'%s', // conclusion
				'%s', // qna
				'%s', // metadata (serialized string)
			)
		);

		if ( $result === false ) {
			$this->log_error(
				'Database insert failed',
				array(
					'error' => $wpdb->last_error,
					'query' => $wpdb->last_query,
				)
			);
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Send shortcode page data - Retrieve and render shortcode content
	 *
	 * Retrieves the data associated with the given template ID and returns
	 * the combined content of the title, article, conclusion, and QnA.
	 *
	 * @param array $attr Shortcode attributes containing template_id
	 * @return string Rendered shortcode content
	 */
	public function send_shortcode_page_data( $attr ) {
		$this->log_debug( 'Retrieving shortcode data', array( 'attributes' => $attr ) );

		// Validate and sanitize the template_id
		if ( empty( $attr['template_id'] ) || ! is_numeric( $attr['template_id'] ) ) {
			$this->log_warning( 'Invalid template ID provided', array( 'template_id' => $attr['template_id'] ?? 'null' ) );
			return '<p>' . esc_html__( 'Invalid template ID.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '</p>';
		}

		// Ensure template_id is an integer
		$template_id = absint( $attr['template_id'] );

		try {
			$content = $this->get_shortcode_content( $template_id );

			if ( $content === false ) {
				return '<p>' . esc_html__( 'No data found for the provided template ID.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '</p>';
			}

			return $content;

		} catch ( Exception $e ) {
			$this->handle_error( 'Failed to retrieve shortcode data', $e, array( 'template_id' => $template_id ) );
			return '<p>' . esc_html__( 'Error retrieving shortcode content.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '</p>';
		}
	}

	/**
	 * Get shortcode content from database
	 *
	 * @param int $template_id Template ID
	 * @return string|false Rendered content or false on failure
	 */
	protected function get_shortcode_content( $template_id ) {
		global $wpdb;

		$this->log_debug( 'Querying shortcode content', array( 'template_id' => $template_id ) );

		// Prepare and execute query safely
		$data = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT title, article, conclusion, qna FROM %i WHERE id = %d',
				$this->table_name,
				$template_id
			)
		);

		// Verify if results exist
		if ( empty( $data ) ) {
			$this->log_warning( 'No shortcode data found', array( 'template_id' => $template_id ) );
			return false;
		}

		// Build content from retrieved data
		$content = '';
		foreach ( $data as $value ) {
			$article = wp_kses_post( $value->article );
			$title   = trim( (string) $value->title );

			// The shortcode renders inside a post or page whose theme already
			// prints the post title as the page's H1, so any H1 of our own
			// creates a duplicate (C-3-1). Demote the article's H1s to H2 —
			// content nested inside them (the featured image) survives the
			// tag swap — and emit the stored title as an H2 only when the
			// article carried no heading at all.
			$has_article_h1 = (bool) preg_match( '/<h1[^>]*>/i', $article );
			$article        = preg_replace( '/<h1([^>]*)>(.*?)<\/h1>/is', '<h2$1>$2</h2>', $article );
			if ( ! $has_article_h1 && $title !== '' ) {
				$content .= '<h2>' . esc_html( $title ) . '</h2>';
			}

			$demote = static function ( $html ) {
				return preg_replace( '/<h1([^>]*)>(.*?)<\/h1>/is', '<h2$1>$2</h2>', (string) $html );
			};

			$content .= '<div class="article-content">' . $article . '</div>';
			$content .= '<div class="conclusion">' . $demote( wp_kses_post( $value->conclusion ) ) . '</div>';
			$content .= '<div class="qna">' . $demote( wp_kses_post( $value->qna ) ) . '</div>';
		}

		$this->log_debug(
			'Shortcode content rendered successfully',
			array(
				'template_id'    => $template_id,
				'content_length' => strlen( $content ),
			)
		);

		return $content;
	}

	/**
	 * Remove shortcode content - Delete shortcode from database
	 *
	 * Handles AJAX request to delete a shortcode record from the database.
	 * Validates nonce and user permissions before deletion.
	 *
	 * @return void Sends JSON response directly
	 */
	public function remove_short_code_content() {
		$this->log_debug( 'Processing shortcode deletion request' );

		// Validate nonce for security
		if ( ! isset( $_POST['security'] ) || ! check_ajax_referer( 'ai_scribe_nonce', 'security', false ) ) {
			$this->log_warning( 'Shortcode deletion failed: Invalid nonce' );
			wp_send_json_error(
				array(
					'msg'           => 'Invalid request. Please refresh the page and try again.',
					'nonce_expired' => true,
				)
			);
			wp_die();
		}

		// P8 §14.2: capability check (shortcodes admin screen).
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'msg' => 'You do not have permission to do this.' ) );
			wp_die();
		}

		try {
			// Sanitize and validate the ID
			$id = absint( $_POST['id'] );
			if ( ! $id ) {
				$this->log_warning( 'Invalid ID provided for deletion', array( 'id' => $_POST['id'] ?? 'null' ) );
				wp_send_json_error( array( 'msg' => 'Invalid ID' ) );
				wp_die();
			}

			// Delete the record
			$result = $this->delete_shortcode_record( $id );

			if ( $result !== false ) {
				$this->log_info( 'Shortcode record deleted successfully', array( 'id' => $id ) );
				wp_send_json_success( array( 'msg' => 'Record deleted successfully.' ) );
			} else {
				$this->log_error( 'Failed to delete shortcode record', array( 'id' => $id ) );
				wp_send_json_error( array( 'msg' => 'Failed to delete the record' ) );
			}
		} catch ( Exception $e ) {
			$error_response = $this->handle_error( 'Shortcode deletion failed', $e );
			wp_send_json_error( array( 'msg' => $error_response['error'] ) );
		}

		wp_die();
	}

	/**
	 * Delete shortcode record from database
	 *
	 * @param int $id Record ID to delete
	 * @return int|false Number of rows affected or false on failure
	 */
	protected function delete_shortcode_record( $id ) {
		global $wpdb;

		$this->log_debug( 'Deleting shortcode record', array( 'id' => $id ) );

		$result = $wpdb->query(
			$wpdb->prepare( 'DELETE FROM %i WHERE id = %d', $this->table_name, $id )
		);

		if ( $result === false ) {
			$this->log_error(
				'Database delete failed',
				array(
					'error' => $wpdb->last_error,
					'id'    => $id,
				)
			);
		}

		return $result;
	}

	/**
	 * Get all shortcode records
	 *
	 * @param array $filters Optional filters for records
	 * @return array|false Array of records or false on failure
	 */
	public function get_all_shortcodes( $filters = array() ) {
		global $wpdb;

		$this->log_debug( 'Retrieving all shortcode records', array( 'filters' => $filters ) );

		try {
			// Literal query text in each branch so every path is fully prepared.
			if ( ! empty( $filters['title'] ) ) {
				$results = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE title LIKE %s ORDER BY id DESC',
						$this->table_name,
						'%' . $wpdb->esc_like( $filters['title'] ) . '%'
					)
				);
			} else {
				$results = $wpdb->get_results(
					$wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC', $this->table_name )
				);
			}

			$this->log_debug( 'Shortcode records retrieved', array( 'count' => count( $results ) ) );

			return $results;

		} catch ( Exception $e ) {
			$this->handle_error( 'Failed to retrieve shortcode records', $e );
			return false;
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
			'security_service'  => isset( $this->security_service ),
			'config'            => isset( $this->config ),
		);

		$base_status['database'] = array(
			'table_name'   => $this->table_name,
			'table_exists' => $this->check_database_table(),
		);

		return $base_status;
	}
}
