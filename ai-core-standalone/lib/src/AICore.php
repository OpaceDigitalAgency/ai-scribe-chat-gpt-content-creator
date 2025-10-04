<?php
/**
 * AI-Core Library - Main Factory Class
 * 
 * Central factory for creating and managing AI providers
 * Provides a simple interface for AI-Scribe integration
 * 
 * @package AI_Core
 * @version 1.0.0
 */

namespace AICore;

use AICore\Providers\OpenAIProvider;
use AICore\Providers\AnthropicProvider;
use AICore\Providers\GeminiProvider;
use AICore\Providers\GrokProvider;
use AICore\Providers\OpenAIImageProvider;
use AICore\Registry\ModelRegistry;
use AICore\Response\ResponseNormalizer;

class AICore {
    
    /**
     * Library version
     */
    const VERSION = '1.0.0';
    
    /**
     * Provider instances cache
     * 
     * @var array
     */
    private static $providers = [];
    
    /**
     * Configuration settings
     * 
     * @var array
     */
    private static $config = [];
    
    /**
     * Initialize AI-Core with configuration
     * 
     * @param array $config Configuration array with API keys
     * @return void
     */
    public static function init(array $config): void {
        self::$config = $config;
        self::$providers = []; // Reset providers cache
    }
    
    /**
     * Get text provider for a specific model
     * 
     * @param string $model Model identifier
     * @return \AICore\Interfaces\ProviderInterface
     * @throws \Exception If model is not supported or provider not configured
     */
    public static function getTextProvider(string $model): \AICore\Interfaces\ProviderInterface {
        
        if (!ModelRegistry::modelExists($model)) {
            throw new \Exception("Unknown model: {$model}");
        }
        
        if (ModelRegistry::isImageModel($model)) {
            throw new \Exception("Model {$model} is for image generation, use getImageProvider() instead");
        }
        
        $provider_name = ModelRegistry::getProvider($model);
        
        if (!isset(self::$providers[$provider_name])) {
            self::$providers[$provider_name] = self::createTextProvider($provider_name);
        }
        
        return self::$providers[$provider_name];
    }
    
    /**
     * Get image provider
     * 
     * @param string $provider Provider name (default: 'openai')
     * @return \AICore\Interfaces\ImageProviderInterface
     * @throws \Exception If provider not supported or configured
     */
    public static function getImageProvider(string $provider = 'openai'): \AICore\Interfaces\ImageProviderInterface {
        
        $cache_key = "image_{$provider}";
        
        if (!isset(self::$providers[$cache_key])) {
            self::$providers[$cache_key] = self::createImageProvider($provider);
        }
        
        return self::$providers[$cache_key];
    }
    
    /**
     * Create text provider instance
     *
     * @param string $provider_name Provider name
     * @return \AICore\Interfaces\ProviderInterface
     * @throws \Exception If provider not supported or API key missing
     */
    private static function createTextProvider(string $provider_name): \AICore\Interfaces\ProviderInterface {

        switch ($provider_name) {
            case 'openai':
                $api_key = self::$config['openai_api_key'] ?? '';
                if (empty($api_key)) {
                    throw new \Exception('OpenAI API key not configured');
                }
                return new OpenAIProvider($api_key);

            case 'anthropic':
                $api_key = self::$config['anthropic_api_key'] ?? '';
                if (empty($api_key)) {
                    throw new \Exception('Anthropic API key not configured');
                }
                return new AnthropicProvider($api_key);

            case 'gemini':
                $api_key = self::$config['gemini_api_key'] ?? '';
                if (empty($api_key)) {
                    throw new \Exception('Gemini API key not configured');
                }
                return new GeminiProvider($api_key);

            case 'grok':
                $api_key = self::$config['grok_api_key'] ?? '';
                if (empty($api_key)) {
                    throw new \Exception('Grok API key not configured');
                }
                return new GrokProvider($api_key);

            default:
                throw new \Exception("Unsupported text provider: {$provider_name}");
        }
    }
    
    /**
     * Create image provider instance
     * 
     * @param string $provider_name Provider name
     * @return \AICore\Interfaces\ImageProviderInterface
     * @throws \Exception If provider not supported or API key missing
     */
    private static function createImageProvider(string $provider_name): \AICore\Interfaces\ImageProviderInterface {
        
        switch ($provider_name) {
            case 'openai':
                $api_key = self::$config['openai_api_key'] ?? '';
                if (empty($api_key)) {
                    throw new \Exception('OpenAI API key not configured for image generation');
                }
                return new OpenAIImageProvider($api_key);
                
            default:
                throw new \Exception("Unsupported image provider: {$provider_name}");
        }
    }
    
    /**
     * Send text generation request
     * Convenience method that automatically selects the right provider
     * 
     * @param string $model Model identifier
     * @param array $messages Messages array
     * @param array $options Request options
     * @return array Normalized response
     * @throws \Exception On errors
     */
    public static function sendTextRequest(string $model, array $messages, array $options = []): array {
        $provider = self::getTextProvider($model);
        $options['model'] = $model;
        return $provider->sendRequest($messages, $options);
    }
    
    /**
     * Generate image
     * Convenience method for image generation
     * 
     * @param string $prompt Image prompt
     * @param array $options Image options
     * @param string $provider Provider name (default: 'openai')
     * @return array Image response
     * @throws \Exception On errors
     */
    public static function generateImage(string $prompt, array $options = [], string $provider = 'openai'): array {
        $image_provider = self::getImageProvider($provider);
        return $image_provider->generateImage($prompt, $options);
    }
    
    /**
     * Check if model is supported
     * 
     * @param string $model Model identifier
     * @return bool True if model is supported
     */
    public static function isModelSupported(string $model): bool {
        return ModelRegistry::modelExists($model);
    }
    
    /**
     * Get all available models
     * 
     * @return array Array of model identifiers
     */
    public static function getAvailableModels(): array {
        return ModelRegistry::getAllModels();
    }
    
    /**
     * Get models by provider
     * 
     * @param string $provider Provider name
     * @return array Array of model identifiers
     */
    public static function getModelsByProvider(string $provider): array {
        return ModelRegistry::getModelsByProvider($provider);
    }
    
    /**
     * Check provider configuration status
     * 
     * @return array Status of all providers
     */
    public static function getProviderStatus(): array {
        $status = [];
        
        // Check OpenAI
        $openai_key = self::$config['openai_api_key'] ?? '';
        $status['openai'] = [
            'configured' => !empty($openai_key),
            'api_key' => !empty($openai_key) ? substr($openai_key, 0, 7) . '...' . substr($openai_key, -4) : 'Not set'
        ];
        
        // Check Anthropic
        $anthropic_key = self::$config['anthropic_api_key'] ?? '';
        $status['anthropic'] = [
            'configured' => !empty($anthropic_key),
            'api_key' => !empty($anthropic_key) ? substr($anthropic_key, 0, 7) . '...' . substr($anthropic_key, -4) : 'Not set'
        ];
        
        return $status;
    }
    
    /**
     * Get library version
     * 
     * @return string Version string
     */
    public static function getVersion(): string {
        return self::VERSION;
    }
    
    /**
     * Reset all providers (useful for testing)
     * 
     * @return void
     */
    public static function reset(): void {
        self::$providers = [];
        self::$config = [];
    }
    
    /**
     * Extract content from response
     * Convenience method for getting text content
     * 
     * @param array $response Normalized response
     * @return string Content text
     */
    public static function extractContent(array $response): string {
        return ResponseNormalizer::extractContent($response);
    }
    
    /**
     * Extract usage information from response
     * 
     * @param array $response Normalized response
     * @return array Usage statistics
     */
    public static function extractUsage(array $response): array {
        return ResponseNormalizer::extractUsage($response);
    }
    
    /**
     * Check if response has error
     * 
     * @param array $response Response to check
     * @return bool True if response contains error
     */
    public static function hasError(array $response): bool {
        return ResponseNormalizer::hasError($response);
    }
    
    /**
     * Extract error message from response
     * 
     * @param array $response Response with error
     * @return string Error message
     */
    public static function extractError(array $response): string {
        return ResponseNormalizer::extractError($response);
    }
}