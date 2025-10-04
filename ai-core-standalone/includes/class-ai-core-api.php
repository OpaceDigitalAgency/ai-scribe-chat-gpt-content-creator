<?php
/**
 * AI-Core API Class
 * 
 * Provides public API for add-on plugins to access AI-Core functionality
 * 
 * @package AI_Core
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI-Core API Class
 * 
 * Public API for add-on plugins
 */
class AI_Core_API {
    
    /**
     * Class instance
     * 
     * @var AI_Core_API
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return AI_Core_API
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
        // Private constructor for singleton
    }
    
    /**
     * Check if AI-Core is configured
     * 
     * @return bool True if at least one API key is configured
     */
    public function is_configured() {
        $settings = get_option('ai_core_settings', array());
        
        return !empty($settings['openai_api_key']) ||
               !empty($settings['anthropic_api_key']) ||
               !empty($settings['gemini_api_key']) ||
               !empty($settings['grok_api_key']);
    }
    
    /**
     * Get configured providers
     * 
     * @return array List of configured provider names
     */
    public function get_configured_providers() {
        $settings = get_option('ai_core_settings', array());
        $providers = array();
        
        if (!empty($settings['openai_api_key'])) {
            $providers[] = 'openai';
        }
        if (!empty($settings['anthropic_api_key'])) {
            $providers[] = 'anthropic';
        }
        if (!empty($settings['gemini_api_key'])) {
            $providers[] = 'gemini';
        }
        if (!empty($settings['grok_api_key'])) {
            $providers[] = 'grok';
        }
        
        return $providers;
    }
    
    /**
     * Get API key for a provider
     * 
     * @param string $provider Provider name
     * @return string|null API key or null if not configured
     */
    public function get_api_key($provider) {
        $settings = get_option('ai_core_settings', array());
        $key_name = $provider . '_api_key';
        
        return $settings[$key_name] ?? null;
    }
    
    /**
     * Get default provider
     * 
     * @return string Default provider name
     */
    public function get_default_provider() {
        $settings = get_option('ai_core_settings', array());
        return $settings['default_provider'] ?? 'openai';
    }
    
    /**
     * Get available models for a provider
     * 
     * @param string $provider Provider name
     * @return array List of available models
     */
    public function get_available_models($provider) {
        if (!class_exists('AICore\\Registry\\ModelRegistry')) {
            return array();
        }
        
        return \AICore\Registry\ModelRegistry::getModelsByProvider($provider);
    }
    
    /**
     * Send text generation request
     * 
     * @param string $model Model identifier
     * @param array $messages Messages array
     * @param array $options Request options
     * @return array|WP_Error Response or error
     */
    public function send_text_request($model, $messages, $options = array()) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('AI-Core is not configured. Please add at least one API key.', 'ai-core'));
        }
        
        try {
            if (!class_exists('AICore\\AICore')) {
                return new WP_Error('library_missing', __('AI-Core library not found.', 'ai-core'));
            }
            
            $response = \AICore\AICore::sendTextRequest($model, $messages, $options);
            
            // Track usage if enabled
            $this->track_usage($model, $response);
            
            return $response;
            
        } catch (Exception $e) {
            return new WP_Error('request_failed', $e->getMessage());
        }
    }
    
    /**
     * Generate image
     * 
     * @param string $prompt Image prompt
     * @param array $options Image options
     * @param string $provider Provider name
     * @return array|WP_Error Response or error
     */
    public function generate_image($prompt, $options = array(), $provider = 'openai') {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('AI-Core is not configured. Please add at least one API key.', 'ai-core'));
        }
        
        try {
            if (!class_exists('AICore\\AICore')) {
                return new WP_Error('library_missing', __('AI-Core library not found.', 'ai-core'));
            }
            
            $response = \AICore\AICore::generateImage($prompt, $options, $provider);
            
            // Track usage if enabled
            $this->track_usage('image-' . $provider, $response);
            
            return $response;
            
        } catch (Exception $e) {
            return new WP_Error('request_failed', $e->getMessage());
        }
    }
    
    /**
     * Track API usage
     * 
     * @param string $model Model used
     * @param array $response API response
     * @return void
     */
    private function track_usage($model, $response) {
        $settings = get_option('ai_core_settings', array());
        
        if (empty($settings['enable_stats'])) {
            return;
        }
        
        $stats = get_option('ai_core_stats', array());
        
        if (!isset($stats[$model])) {
            $stats[$model] = array(
                'requests' => 0,
                'tokens' => 0,
                'errors' => 0,
                'last_used' => null
            );
        }
        
        $stats[$model]['requests']++;
        $stats[$model]['last_used'] = current_time('mysql');
        
        if (isset($response['usage']['total_tokens'])) {
            $stats[$model]['tokens'] += $response['usage']['total_tokens'];
        }
        
        if (isset($response['error'])) {
            $stats[$model]['errors']++;
        }
        
        update_option('ai_core_stats', $stats);
    }
    
    /**
     * Get usage statistics
     * 
     * @return array Usage statistics
     */
    public function get_stats() {
        return get_option('ai_core_stats', array());
    }
    
    /**
     * Reset usage statistics
     * 
     * @return bool Success status
     */
    public function reset_stats() {
        return update_option('ai_core_stats', array());
    }
}

/**
 * Get AI-Core API instance
 * 
 * @return AI_Core_API
 */
function ai_core() {
    return AI_Core_API::get_instance();
}

