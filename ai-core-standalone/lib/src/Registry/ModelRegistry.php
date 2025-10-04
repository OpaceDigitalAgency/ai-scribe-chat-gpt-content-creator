<?php
/**
 * AI-Core Library - Model Registry
 * 
 * Centralized model management and provider detection
 * Replaces fragile string matching with structured model definitions
 * 
 * @package AI_Core
 * @version 1.0.0
 */

namespace AICore\Registry;

class ModelRegistry {
    
    /**
     * Model definitions with provider mappings
     * 
     * @var array
     */
    private static $models = [
        // OpenAI Models
        'gpt-4o' => [
            'provider' => 'openai',
            'type' => 'chat',
            'max_tokens' => 128000,
            'supports_images' => true,
            'supports_functions' => true
        ],
        'gpt-4o-mini' => [
            'provider' => 'openai',
            'type' => 'chat',
            'max_tokens' => 128000,
            'supports_images' => true,
            'supports_functions' => true
        ],
        'gpt-4.5' => [
            'provider' => 'openai',
            'type' => 'chat',
            'max_tokens' => 128000,
            'supports_images' => true,
            'supports_functions' => true
        ],
        'o3' => [
            'provider' => 'openai',
            'type' => 'chat',
            'max_tokens' => 128000,
            'supports_images' => false,
            'supports_functions' => true
        ],
        'o3-mini' => [
            'provider' => 'openai',
            'type' => 'chat',
            'max_tokens' => 128000,
            'supports_images' => false,
            'supports_functions' => true
        ],
        'gpt-image-1' => [
            'provider' => 'openai',
            'type' => 'image',
            'max_tokens' => null,
            'supports_images' => false,
            'supports_functions' => false
        ],
        
        // Anthropic Models
        'claude-sonnet-4-20250514' => [
            'provider' => 'anthropic',
            'type' => 'chat',
            'max_tokens' => 200000,
            'supports_images' => true,
            'supports_functions' => true
        ],
        'claude-opus-4-20250514' => [
            'provider' => 'anthropic',
            'type' => 'chat',
            'max_tokens' => 200000,
            'supports_images' => true,
            'supports_functions' => true
        ],

        // Google Gemini Models
        'gemini-2.0-flash-exp' => [
            'provider' => 'gemini',
            'type' => 'chat',
            'max_tokens' => 8192,
            'supports_images' => true,
            'supports_functions' => true
        ],
        'gemini-1.5-pro' => [
            'provider' => 'gemini',
            'type' => 'chat',
            'max_tokens' => 8192,
            'supports_images' => true,
            'supports_functions' => true
        ],
        'gemini-1.5-flash' => [
            'provider' => 'gemini',
            'type' => 'chat',
            'max_tokens' => 8192,
            'supports_images' => true,
            'supports_functions' => true
        ],

        // xAI Grok Models
        'grok-beta' => [
            'provider' => 'grok',
            'type' => 'chat',
            'max_tokens' => 131072,
            'supports_images' => false,
            'supports_functions' => true
        ],
        'grok-vision-beta' => [
            'provider' => 'grok',
            'type' => 'chat',
            'max_tokens' => 8192,
            'supports_images' => true,
            'supports_functions' => true
        ]
    ];
    
    /**
     * Get provider for a specific model
     * 
     * @param string $model Model identifier
     * @return string|null Provider name or null if model not found
     */
    public static function getProvider(string $model): ?string {
        return self::$models[$model]['provider'] ?? null;
    }
    
    /**
     * Check if model is from Anthropic
     * Replaces: $is_anthropic_model = (strpos($model, 'claude') !== false);
     * 
     * @param string $model Model identifier
     * @return bool True if model is from Anthropic
     */
    public static function isAnthropicModel(string $model): bool {
        return self::getProvider($model) === 'anthropic';
    }
    
    /**
     * Check if model is from OpenAI
     *
     * @param string $model Model identifier
     * @return bool True if model is from OpenAI
     */
    public static function isOpenAIModel(string $model): bool {
        return self::getProvider($model) === 'openai';
    }

    /**
     * Check if model is from Gemini
     *
     * @param string $model Model identifier
     * @return bool True if model is from Gemini
     */
    public static function isGeminiModel(string $model): bool {
        return self::getProvider($model) === 'gemini';
    }

    /**
     * Check if model is from Grok
     *
     * @param string $model Model identifier
     * @return bool True if model is from Grok
     */
    public static function isGrokModel(string $model): bool {
        return self::getProvider($model) === 'grok';
    }

    /**
     * Check if model supports image generation
     *
     * @param string $model Model identifier
     * @return bool True if model supports image generation
     */
    public static function isImageModel(string $model): bool {
        return (self::$models[$model]['type'] ?? '') === 'image';
    }
    
    /**
     * Get all models for a specific provider
     *
     * @param string $provider Provider name ('openai', 'anthropic', 'gemini', 'grok')
     * @return array Array of model identifiers
     */
    public static function getModelsByProvider(string $provider): array {
        $models = [];
        foreach (self::$models as $model => $config) {
            if ($config['provider'] === $provider) {
                $models[] = $model;
            }
        }
        return $models;
    }

    /**
     * Get all available providers
     *
     * @return array Array of provider names
     */
    public static function getAllProviders(): array {
        return ['openai', 'anthropic', 'gemini', 'grok'];
    }
    
    /**
     * Get model configuration
     * 
     * @param string $model Model identifier
     * @return array|null Model configuration or null if not found
     */
    public static function getModelConfig(string $model): ?array {
        return self::$models[$model] ?? null;
    }
    
    /**
     * Register a new model
     * 
     * @param string $model Model identifier
     * @param array $config Model configuration
     * @return void
     */
    public static function registerModel(string $model, array $config): void {
        self::$models[$model] = $config;
    }
    
    /**
     * Check if model exists in registry
     * 
     * @param string $model Model identifier
     * @return bool True if model is registered
     */
    public static function modelExists(string $model): bool {
        return isset(self::$models[$model]);
    }
    
    /**
     * Get all registered models
     * 
     * @return array All model identifiers
     */
    public static function getAllModels(): array {
        return array_keys(self::$models);
    }
}