<?php
/**
 * AI-Core Validator Class
 * 
 * Handles API key validation and testing
 * 
 * @package AI_Core
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI-Core Validator Class
 * 
 * Validates API keys and tests connections
 */
class AI_Core_Validator {
    
    /**
     * Class instance
     * 
     * @var AI_Core_Validator
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return AI_Core_Validator
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
     * Validate API key for a provider
     * 
     * @param string $provider Provider name
     * @param string $api_key API key to validate
     * @return array Validation result
     */
    public function validate_api_key($provider, $api_key) {
        if (empty($api_key)) {
            return array(
                'valid' => false,
                'error' => __('API key is empty', 'ai-core')
            );
        }
        
        try {
            // Initialize AI-Core with the API key
            $config = array($provider . '_api_key' => $api_key);
            \AICore\AICore::init($config);
            
            // Get the provider instance
            $provider_instance = $this->get_provider_instance($provider, $api_key);
            
            if (!$provider_instance) {
                return array(
                    'valid' => false,
                    'error' => __('Provider not supported', 'ai-core')
                );
            }
            
            // Validate using provider's method
            if (method_exists($provider_instance, 'validateApiKey')) {
                return $provider_instance->validateApiKey();
            }
            
            // Fallback: try a simple request
            return $this->test_with_request($provider_instance);
            
        } catch (Exception $e) {
            return array(
                'valid' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get provider instance
     * 
     * @param string $provider Provider name
     * @param string $api_key API key
     * @return object|null Provider instance
     */
    private function get_provider_instance($provider, $api_key) {
        switch ($provider) {
            case 'openai':
                return new \AICore\Providers\OpenAIProvider($api_key);
            case 'anthropic':
                return new \AICore\Providers\AnthropicProvider($api_key);
            case 'gemini':
                return new \AICore\Providers\GeminiProvider($api_key);
            case 'grok':
                return new \AICore\Providers\GrokProvider($api_key);
            default:
                return null;
        }
    }
    
    /**
     * Test provider with a simple request
     * 
     * @param object $provider Provider instance
     * @return array Test result
     */
    private function test_with_request($provider) {
        try {
            $messages = array(
                array('role' => 'user', 'content' => 'Hello')
            );
            
            $response = $provider->sendRequest($messages, array('max_tokens' => 10));
            
            return array(
                'valid' => true,
                'provider' => $provider->getName()
            );
            
        } catch (Exception $e) {
            return array(
                'valid' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get available models for a provider
     * 
     * @param string $provider Provider name
     * @return array List of models
     */
    public function get_available_models($provider) {
        $settings = get_option('ai_core_settings', array());
        $api_key = $settings[$provider . '_api_key'] ?? '';
        
        if (empty($api_key)) {
            return array();
        }
        
        try {
            $provider_instance = $this->get_provider_instance($provider, $api_key);
            
            if (!$provider_instance || !method_exists($provider_instance, 'getAvailableModels')) {
                // Fallback to registry
                return \AICore\Registry\ModelRegistry::getModelsByProvider($provider);
            }
            
            // Check cache first
            $cache_key = 'ai_core_models_' . $provider;
            $cached = get_transient($cache_key);
            
            if ($cached !== false && $settings['enable_caching']) {
                return $cached;
            }
            
            // Fetch from API
            $models = $provider_instance->getAvailableModels();
            
            // Cache for 1 hour
            if ($settings['enable_caching']) {
                set_transient($cache_key, $models, HOUR_IN_SECONDS);
            }
            
            return $models;
            
        } catch (Exception $e) {
            // Fallback to registry
            return \AICore\Registry\ModelRegistry::getModelsByProvider($provider);
        }
    }
}

