<?php
/**
 * AI-Core AJAX Class
 * 
 * Handles AJAX requests for admin interface
 * 
 * @package AI_Core
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI-Core AJAX Class
 * 
 * Manages AJAX handlers
 */
class AI_Core_AJAX {
    
    /**
     * Class instance
     * 
     * @var AI_Core_AJAX
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return AI_Core_AJAX
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
     * Initialize AJAX handlers
     *
     * @return void
     */
    private function init() {
        add_action('wp_ajax_ai_core_test_api_key', array($this, 'test_api_key'));
        add_action('wp_ajax_ai_core_get_models', array($this, 'get_models'));
        add_action('wp_ajax_ai_core_reset_stats', array($this, 'reset_stats'));
    }
    
    /**
     * Test API key
     *
     * @return void
     */
    public function test_api_key() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-core')));
        }
        
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($provider) || empty($api_key)) {
            wp_send_json_error(array('message' => __('Provider and API key are required', 'ai-core')));
        }
        
        $validator = AI_Core_Validator::get_instance();
        $result = $validator->validate_api_key($provider, $api_key);
        
        if ($result['valid']) {
            wp_send_json_success(array(
                'message' => __('API key is valid!', 'ai-core'),
                'provider' => $result['provider'] ?? $provider
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['error'] ?? __('API key validation failed', 'ai-core')
            ));
        }
    }
    
    /**
     * Get available models for a provider
     *
     * @return void
     */
    public function get_models() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-core')));
        }
        
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        
        if (empty($provider)) {
            wp_send_json_error(array('message' => __('Provider is required', 'ai-core')));
        }
        
        $validator = AI_Core_Validator::get_instance();
        $models = $validator->get_available_models($provider);
        
        wp_send_json_success(array(
            'models' => $models,
            'count' => count($models)
        ));
    }
    
    /**
     * Reset statistics
     *
     * @return void
     */
    public function reset_stats() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-core')));
        }
        
        $stats = AI_Core_Stats::get_instance();
        $result = $stats->reset_stats();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Statistics reset successfully', 'ai-core')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to reset statistics', 'ai-core')
            ));
        }
    }
}

// Initialize AJAX handlers
AI_Core_AJAX::get_instance();

