<?php
/**
 * WordPress Adapter for AI-Scribe Plugin
 *
 * Handles all WordPress-specific functionality including hooks,
 * options management, user capabilities, and WordPress API integration.
 *
 * @package AI_Scribe
 * @subpackage Infrastructure
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_WordPress_Adapter
 *
 * Abstracts WordPress functionality to provide a clean interface
 * for the plugin while maintaining WordPress best practices.
 */
class AI_Scribe_WordPress_Adapter {

	/**
	 * Logger instance
	 *
	 * @var AI_Scribe_Logger
	 */
	private $logger;

	/**
	 * Registered hooks
	 *
	 * @var array
	 */
	private $registered_hooks = array();

	/**
	 * AJAX actions
	 *
	 * @var array
	 */
	private $ajax_actions = array();

	/**
	 * Admin menu items
	 *
	 * @var array
	 */
	private $menu_items = array();

	/**
	 * Constructor
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 */
	public function __construct( AI_Scribe_Logger $logger ) {
		$this->logger = $logger;
		$this->initialize();
	}

	/**
	 * Initialize WordPress adapter
	 *
	 * @return void
	 */
	private function initialize() {
		$this->logger->debug( 'WordPress Adapter initialized' );
	}

	/**
	 * Register WordPress hook
	 *
	 * @param string $hook_name Hook name
	 * @param callable $callback Callback function
	 * @param int $priority Hook priority
	 * @param int $accepted_args Number of accepted arguments
	 * @return bool Success status
	 */
	public function add_hook( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		try {
			add_action( $hook_name, $callback, $priority, $accepted_args );

			$this->registered_hooks[] = array(
				'hook'     => $hook_name,
				'callback' => $callback,
				'priority' => $priority,
				'args'     => $accepted_args,
			);

			$this->logger->debug( "Registered hook: {$hook_name}" );
			return true;

		} catch ( Exception $e ) {
			$this->logger->error( "Failed to register hook: {$hook_name}", array( 'exception' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Register WordPress filter
	 *
	 * @param string $filter_name Filter name
	 * @param callable $callback Callback function
	 * @param int $priority Filter priority
	 * @param int $accepted_args Number of accepted arguments
	 * @return bool Success status
	 */
	public function add_filter( $filter_name, $callback, $priority = 10, $accepted_args = 1 ) {
		try {
			add_filter( $filter_name, $callback, $priority, $accepted_args );

			$this->registered_hooks[] = array(
				'type'     => 'filter',
				'hook'     => $filter_name,
				'callback' => $callback,
				'priority' => $priority,
				'args'     => $accepted_args,
			);

			$this->logger->debug( "Registered filter: {$filter_name}" );
			return true;

		} catch ( Exception $e ) {
			$this->logger->error( "Failed to register filter: {$filter_name}", array( 'exception' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Register AJAX action
	 *
	 * @param string $action AJAX action name
	 * @param callable $callback Callback function
	 * @param bool $public Whether to allow non-logged-in users
	 * @return bool Success status
	 */
	public function register_ajax_action( $action, $callback, $public = false ) {
		try {
			// Register for logged-in users
			add_action( "wp_ajax_{$action}", $callback );

			// Register for non-logged-in users if public
			if ( $public ) {
				add_action( "wp_ajax_nopriv_{$action}", $callback );
			}

			$this->ajax_actions[ $action ] = array(
				'callback' => $callback,
				'public'   => $public,
			);

			$this->logger->debug( "Registered AJAX action: {$action}" . ( $public ? ' (public)' : '' ) );
			return true;

		} catch ( Exception $e ) {
			$this->logger->error( "Failed to register AJAX action: {$action}", array( 'exception' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Create WordPress nonce
	 *
	 * @param string $action Nonce action
	 * @return string Nonce value
	 */
	public function create_nonce( $action = 'ai_scribe_nonce' ) {
		return wp_create_nonce( $action );
	}

	/**
	 * Verify WordPress nonce
	 *
	 * @param string $nonce Nonce value
	 * @param string $action Nonce action
	 * @return bool Verification result
	 */
	public function verify_nonce( $nonce, $action = 'ai_scribe_nonce' ) {
		return wp_verify_nonce( $nonce, $action ) !== false;
	}

	/**
	 * Check user capability
	 *
	 * @param string $capability Required capability
	 * @param int|null $user_id User ID (null for current user)
	 * @return bool Whether user has capability
	 */
	public function user_can( $capability, $user_id = null ) {
		if ( $user_id ) {
			return user_can( $user_id, $capability );
		}

		return current_user_can( $capability );
	}

	/**
	 * Get current user ID
	 *
	 * @return int User ID
	 */
	public function get_current_user_id() {
		return get_current_user_id();
	}

	/**
	 * Get WordPress option
	 *
	 * @param string $option_name Option name
	 * @param mixed $default Default value
	 * @return mixed Option value
	 */
	public function get_option( $option_name, $default = false ) {
		return get_option( $option_name, $default );
	}

	/**
	 * Update WordPress option
	 *
	 * @param string $option_name Option name
	 * @param mixed $value Option value
	 * @return bool Success status
	 */
	public function update_option( $option_name, $value ) {
		return update_option( $option_name, $value );
	}

	/**
	 * Delete WordPress option
	 *
	 * @param string $option_name Option name
	 * @return bool Success status
	 */
	public function delete_option( $option_name ) {
		return delete_option( $option_name );
	}

	/**
	 * Add admin menu page
	 *
	 * @param array $menu_config Menu configuration
	 * @return string|false Menu hook suffix or false on failure
	 */
	public function add_admin_menu( $menu_config ) {
		$defaults = array(
			'page_title' => 'AI Scribe',
			'menu_title' => 'AI Scribe',
			'capability' => 'manage_options',
			'menu_slug'  => 'ai-scribe',
			'callback'   => null,
			'icon_url'   => '',
			'position'   => null,
		);

		$config = array_merge( $defaults, $menu_config );

		try {
			$hook_suffix = add_menu_page(
				$config['page_title'],
				$config['menu_title'],
				$config['capability'],
				$config['menu_slug'],
				$config['callback'],
				$config['icon_url'],
				$config['position']
			);

			$this->menu_items[] = $config;
			$this->logger->debug( "Added admin menu: {$config['menu_slug']}" );

			return $hook_suffix;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to add admin menu',
				array(
					'config'    => $config,
					'exception' => $e->getMessage(),
				)
			);
			return false;
		}
	}

	/**
	 * Add admin submenu page
	 *
	 * @param array $submenu_config Submenu configuration
	 * @return string|false Menu hook suffix or false on failure
	 */
	public function add_admin_submenu( $submenu_config ) {
		$defaults = array(
			'parent_slug' => 'ai-scribe',
			'page_title'  => 'AI Scribe',
			'menu_title'  => 'AI Scribe',
			'capability'  => 'manage_options',
			'menu_slug'   => 'ai-scribe-sub',
			'callback'    => null,
		);

		$config = array_merge( $defaults, $submenu_config );

		try {
			$hook_suffix = add_submenu_page(
				$config['parent_slug'],
				$config['page_title'],
				$config['menu_title'],
				$config['capability'],
				$config['menu_slug'],
				$config['callback']
			);

			$this->menu_items[] = $config;
			$this->logger->debug( "Added admin submenu: {$config['menu_slug']}" );

			return $hook_suffix;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to add admin submenu',
				array(
					'config'    => $config,
					'exception' => $e->getMessage(),
				)
			);
			return false;
		}
	}

	/**
	 * Enqueue script
	 *
	 * @param string $handle Script handle
	 * @param string $src Script source URL
	 * @param array $deps Dependencies
	 * @param string|bool $ver Version
	 * @param bool $in_footer Whether to enqueue in footer
	 * @return bool Success status
	 */
	public function enqueue_script( $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {
		try {
			wp_enqueue_script( $handle, $src, $deps, $ver, $in_footer );
			$this->logger->debug( "Enqueued script: {$handle}" );
			return true;

		} catch ( Exception $e ) {
			$this->logger->error( "Failed to enqueue script: {$handle}", array( 'exception' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Enqueue style
	 *
	 * @param string $handle Style handle
	 * @param string $src Style source URL
	 * @param array $deps Dependencies
	 * @param string|bool $ver Version
	 * @param string $media Media type
	 * @return bool Success status
	 */
	public function enqueue_style( $handle, $src, $deps = array(), $ver = false, $media = 'all' ) {
		try {
			wp_enqueue_style( $handle, $src, $deps, $ver, $media );
			$this->logger->debug( "Enqueued style: {$handle}" );
			return true;

		} catch ( Exception $e ) {
			$this->logger->error( "Failed to enqueue style: {$handle}", array( 'exception' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Localize script
	 *
	 * @param string $handle Script handle
	 * @param string $object_name JavaScript object name
	 * @param array $data Data to localize
	 * @return bool Success status
	 */
	public function localize_script( $handle, $object_name, $data ) {
		try {
			wp_localize_script( $handle, $object_name, $data );
			$this->logger->debug( "Localized script: {$handle}" );
			return true;

		} catch ( Exception $e ) {
			$this->logger->error( "Failed to localize script: {$handle}", array( 'exception' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Make HTTP request
	 *
	 * @param string $url Request URL
	 * @param array $args Request arguments
	 * @return array|WP_Error Response or error
	 */
	public function http_request( $url, $args = array() ) {
		$defaults = array(
			'timeout' => 30,
			'headers' => array(),
			'method'  => 'GET',
		);

		$args = array_merge( $defaults, $args );

		$this->logger->debug(
			'Making HTTP request',
			array(
				'url'    => $url,
				'method' => $args['method'],
			)
		);

		return wp_remote_request( $url, $args );
	}

	/**
	 * Get admin URL
	 *
	 * @param string $path Admin path
	 * @param string $scheme URL scheme
	 * @return string Admin URL
	 */
	public function get_admin_url( $path = '', $scheme = 'admin' ) {
		return admin_url( $path, $scheme );
	}

	/**
	 * Get site URL
	 *
	 * @param string $path Site path
	 * @param string $scheme URL scheme
	 * @return string Site URL
	 */
	public function get_site_url( $path = '', $scheme = null ) {
		return site_url( $path, $scheme );
	}

	/**
	 * Get current time
	 *
	 * @param string $format Time format
	 * @param int|bool $gmt Whether to use GMT
	 * @return string Current time
	 */
	public function get_current_time( $format = 'mysql', $gmt = false ) {
		return current_time( $format, $gmt );
	}

	/**
	 * Send JSON response
	 *
	 * @param mixed $data Response data
	 * @param bool $success Success status
	 * @return void (exits)
	 */
	public function send_json_response( $data, $success = true ) {
		if ( $success ) {
			wp_send_json_success( $data );
		} else {
			wp_send_json_error( $data );
		}
	}

	/**
	 * Add plugin action links
	 *
	 * @param string $plugin_file Plugin file path
	 * @param array $links Action links
	 * @return bool Success status
	 */
	public function add_plugin_action_links( $plugin_file, $links ) {
		try {
			$filter_name = 'plugin_action_links_' . plugin_basename( $plugin_file );

			add_filter(
				$filter_name,
				function ( $existing_links ) use ( $links ) {
					return array_merge( $links, $existing_links );
				}
			);

			$this->logger->debug( "Added plugin action links for: {$plugin_file}" );
			return true;

		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to add plugin action links', array( 'exception' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Register shortcode
	 *
	 * @param string $tag Shortcode tag
	 * @param callable $callback Shortcode callback
	 * @return bool Success status
	 */
	public function register_shortcode( $tag, $callback ) {
		try {
			add_shortcode( $tag, $callback );
			$this->logger->debug( "Registered shortcode: {$tag}" );
			return true;

		} catch ( Exception $e ) {
			$this->logger->error( "Failed to register shortcode: {$tag}", array( 'exception' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Get WordPress version
	 *
	 * @return string WordPress version
	 */
	public function get_wp_version() {
		global $wp_version;
		return $wp_version;
	}

	/**
	 * Check if WordPress is in debug mode
	 *
	 * @return bool Debug status
	 */
	public function is_debug_mode() {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Get registered hooks summary
	 *
	 * @return array Hooks summary
	 */
	public function get_hooks_summary() {
		return array(
			'total_hooks'  => count( $this->registered_hooks ),
			'ajax_actions' => count( $this->ajax_actions ),
			'menu_items'   => count( $this->menu_items ),
			'hooks'        => $this->registered_hooks,
			'ajax'         => $this->ajax_actions,
			'menus'        => $this->menu_items,
		);
	}

	/**
	 * Clean up registered hooks (for testing)
	 *
	 * @return void
	 */
	public function cleanup_hooks() {
		foreach ( $this->registered_hooks as $hook_data ) {
			if ( isset( $hook_data['type'] ) && $hook_data['type'] === 'filter' ) {
				remove_filter( $hook_data['hook'], $hook_data['callback'], $hook_data['priority'] );
			} else {
				remove_action( $hook_data['hook'], $hook_data['callback'], $hook_data['priority'] );
			}
		}

		$this->registered_hooks = array();
		$this->ajax_actions     = array();
		$this->menu_items       = array();

		$this->logger->debug( 'WordPress hooks cleaned up' );
	}
}
