<?php
/**
 * Plugin Name: AI-Core - Universal AI Integration Hub
 * Plugin URI: https://opace.agency/ai-core
 * Description: Centralised AI integration hub for WordPress. Manage API keys for OpenAI, Anthropic Claude, Google Gemini, and xAI Grok in one place. Powers AI-Scribe, AI-Imagen, and other AI plugins with shared configuration and seamless integration.
 * Version: 1.0.0
 * Author: Opace Digital Agency
 * Author URI: https://opace.agency
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: ai-core
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8.1
 * Requires PHP: 7.4
 * Network: false
 * Tags: ai, openai, claude, gemini, grok, api, integration, artificial intelligence
 *
 * @package AI_Core
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AI_CORE_VERSION', '1.0.0');
define('AI_CORE_PLUGIN_FILE', __FILE__);
define('AI_CORE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_CORE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_CORE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Minimum PHP version check
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>AI-Core:</strong> This plugin requires PHP 7.4 or higher. You are running PHP ' . esc_html(PHP_VERSION);
        echo '</p></div>';
    });
    return;
}

/**
 * Main AI-Core Plugin Class
 * 
 * Handles plugin initialization, activation, and deactivation
 * Provides centralized AI provider management for WordPress
 */
class AI_Core_Plugin {
    
    /**
     * Plugin instance
     * 
     * @var AI_Core_Plugin
     */
    private static $instance = null;
    
    /**
     * Get plugin instance (Singleton pattern)
     * 
     * @return AI_Core_Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the plugin
     * 
     * @return void
     */
    private function init() {
        // Load AI-Core library
        $this->load_ai_core_library();
        
        // Load plugin files
        $this->load_includes();
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Load AI-Core library
     *
     * @return void
     */
    private function load_ai_core_library() {
        $ai_core_autoload = AI_CORE_PLUGIN_DIR . 'lib/autoload.php';

        if (file_exists($ai_core_autoload)) {
            require_once $ai_core_autoload;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>AI-Core:</strong> Core library not found. Please reinstall the plugin.';
                echo '</p></div>';
            });
        }
    }
    
    /**
     * Load plugin includes
     * 
     * @return void
     */
    private function load_includes() {
        // Core functionality
        require_once AI_CORE_PLUGIN_DIR . 'includes/class-ai-core-settings.php';
        require_once AI_CORE_PLUGIN_DIR . 'includes/class-ai-core-api.php';
        require_once AI_CORE_PLUGIN_DIR . 'includes/class-ai-core-validator.php';
        require_once AI_CORE_PLUGIN_DIR . 'includes/class-ai-core-stats.php';
        
        // Admin functionality
        if (is_admin()) {
            require_once AI_CORE_PLUGIN_DIR . 'admin/class-ai-core-admin.php';
            require_once AI_CORE_PLUGIN_DIR . 'admin/class-ai-core-ajax.php';
            require_once AI_CORE_PLUGIN_DIR . 'admin/class-ai-core-addons.php';
        }
    }
    
    /**
     * Initialize WordPress hooks
     * 
     * @return void
     */
    private function init_hooks() {
        // Activation, deactivation, and uninstall hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Plugin loaded hook
        add_action('plugins_loaded', array($this, 'plugins_loaded'));
        
        // Admin init
        if (is_admin()) {
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        }
        
        // Add settings link on plugins page
        add_filter('plugin_action_links_' . AI_CORE_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }
    
    /**
     * Plugin activation
     * 
     * @return void
     */
    public function activate() {
        // Set default options
        $default_settings = array(
            'openai_api_key' => '',
            'anthropic_api_key' => '',
            'gemini_api_key' => '',
            'grok_api_key' => '',
            'default_provider' => 'openai',
            'enable_stats' => true,
            'enable_caching' => true,
            'cache_duration' => 3600,
        );
        
        add_option('ai_core_settings', $default_settings);
        add_option('ai_core_stats', array());
        add_option('ai_core_version', AI_CORE_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     * 
     * @return void
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugins loaded hook
     * 
     * @return void
     */
    public function plugins_loaded() {
        // Load text domain for translations
        load_plugin_textdomain('ai-core', false, dirname(AI_CORE_PLUGIN_BASENAME) . '/languages');
        
        // Initialize AI-Core library with saved settings
        $this->initialize_ai_core();
    }
    
    /**
     * Initialize AI-Core library with saved settings
     * 
     * @return void
     */
    private function initialize_ai_core() {
        $settings = get_option('ai_core_settings', array());
        
        // Initialize AI-Core with all configured API keys
        if (class_exists('AICore\\AICore')) {
            $config = array();
            
            if (!empty($settings['openai_api_key'])) {
                $config['openai_api_key'] = $settings['openai_api_key'];
            }
            
            if (!empty($settings['anthropic_api_key'])) {
                $config['anthropic_api_key'] = $settings['anthropic_api_key'];
            }
            
            if (!empty($settings['gemini_api_key'])) {
                $config['gemini_api_key'] = $settings['gemini_api_key'];
            }
            
            if (!empty($settings['grok_api_key'])) {
                $config['grok_api_key'] = $settings['grok_api_key'];
            }
            
            // Initialize AI-Core
            \AICore\AICore::init($config);
        }
    }
    
    /**
     * Admin init hook
     * 
     * @return void
     */
    public function admin_init() {
        // Initialize settings
        AI_Core_Settings::get_instance();
    }
    
    /**
     * Admin menu hook
     * 
     * @return void
     */
    public function admin_menu() {
        // Initialize admin
        AI_Core_Admin::get_instance();
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook Current admin page hook
     * @return void
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on AI-Core admin pages
        if (strpos($hook, 'ai-core') === false) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'ai-core-admin',
            AI_CORE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AI_CORE_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'ai-core-admin',
            AI_CORE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            AI_CORE_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('ai-core-admin', 'aiCoreAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_core_admin'),
            'strings' => array(
                'testing' => __('Testing...', 'ai-core'),
                'success' => __('Success!', 'ai-core'),
                'error' => __('Error', 'ai-core'),
                'validating' => __('Validating...', 'ai-core'),
            ),
        ));
    }
    
    /**
     * Add action links to plugins page
     * 
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=ai-core-settings') . '">' . __('Settings', 'ai-core') . '</a>';
        $addons_link = '<a href="' . admin_url('admin.php?page=ai-core-addons') . '">' . __('Add-ons', 'ai-core') . '</a>';
        
        array_unshift($links, $settings_link, $addons_link);
        
        return $links;
    }
}

// Initialize the plugin
AI_Core_Plugin::get_instance();

