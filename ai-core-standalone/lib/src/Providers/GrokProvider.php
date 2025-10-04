<?php
/**
 * AI-Core Library - xAI Grok Provider
 * 
 * Handles communication with xAI Grok API
 * 
 * @package AI_Core
 * @version 1.0.0
 */

namespace AICore\Providers;

use AICore\Interfaces\ProviderInterface;
use AICore\Http\HttpClient;
use AICore\Response\ResponseNormalizer;
use AICore\Registry\ModelRegistry;

class GrokProvider implements ProviderInterface {
    
    /**
     * Grok API endpoint
     */
    const API_ENDPOINT = 'https://api.x.ai/v1/chat/completions';
    
    /**
     * API key for authentication
     * 
     * @var string
     */
    private $api_key;
    
    /**
     * Constructor
     * 
     * @param string $api_key xAI Grok API key
     */
    public function __construct(string $api_key) {
        $this->api_key = $api_key;
    }
    
    /**
     * Send request to Grok API
     * 
     * @param array $messages Array of messages for the conversation
     * @param array $options Request options (model, temperature, max_tokens, etc.)
     * @return array Normalized response array
     * @throws \Exception On API errors
     */
    public function sendRequest(array $messages, array $options = []): array {
        
        if (!$this->isConfigured()) {
            throw new \Exception('Grok provider not configured: missing API key');
        }
        
        // Prepare request payload (Grok uses OpenAI-compatible format)
        $payload = [
            'model' => $options['model'] ?? 'grok-beta',
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4000,
            'top_p' => $options['top_p'] ?? 1.0,
            'frequency_penalty' => $options['frequency_penalty'] ?? 0,
            'presence_penalty' => $options['presence_penalty'] ?? 0
        ];
        
        // Add optional parameters if provided
        if (isset($options['stream'])) {
            $payload['stream'] = $options['stream'];
        }
        
        if (isset($options['stop'])) {
            $payload['stop'] = $options['stop'];
        }
        
        // Prepare headers
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        ];
        
        // Send request
        try {
            $response = HttpClient::post(self::API_ENDPOINT, $payload, $headers);
            
            // Grok uses OpenAI-compatible format, so normalize as OpenAI
            return ResponseNormalizer::normalize($response, 'openai');
            
        } catch (\Exception $e) {
            throw new \Exception('Grok API request failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if provider is configured
     * 
     * @return bool True if configured
     */
    public function isConfigured(): bool {
        return !empty($this->api_key);
    }
    
    /**
     * Get provider name
     * 
     * @return string Provider name
     */
    public function getName(): string {
        return 'grok';
    }
    
    /**
     * Validate API key
     * 
     * @return array Validation result with 'valid' boolean and optional 'error' message
     */
    public function validateApiKey(): array {
        if (!$this->isConfigured()) {
            return [
                'valid' => false,
                'error' => 'API key is empty'
            ];
        }
        
        try {
            // Send a minimal test request
            $test_messages = [
                ['role' => 'user', 'content' => 'Hello']
            ];
            
            $response = $this->sendRequest($test_messages, [
                'model' => 'grok-beta',
                'max_tokens' => 10
            ]);
            
            return [
                'valid' => true,
                'provider' => 'grok',
                'model' => 'grok-beta'
            ];
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get available models
     * 
     * @return array List of available models
     */
    public function getAvailableModels(): array {
        if (!$this->isConfigured()) {
            return [];
        }
        
        try {
            // Try to fetch models from API
            $endpoint = 'https://api.x.ai/v1/models';
            
            $headers = [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ];
            
            $response = HttpClient::get($endpoint, [], $headers);
            
            $models = [];
            if (isset($response['data']) && is_array($response['data'])) {
                foreach ($response['data'] as $model) {
                    if (isset($model['id'])) {
                        $models[] = [
                            'id' => $model['id'],
                            'name' => $model['id'],
                            'description' => $model['description'] ?? '',
                            'max_tokens' => $model['context_length'] ?? 131072,
                        ];
                    }
                }
            }
            
            return $models;
            
        } catch (\Exception $e) {
            // Return default models if API call fails
            return [
                [
                    'id' => 'grok-beta',
                    'name' => 'Grok Beta',
                    'description' => 'Latest Grok model with enhanced capabilities',
                    'max_tokens' => 131072,
                ],
                [
                    'id' => 'grok-vision-beta',
                    'name' => 'Grok Vision Beta',
                    'description' => 'Grok with vision capabilities',
                    'max_tokens' => 8192,
                ],
            ];
        }
    }
}

