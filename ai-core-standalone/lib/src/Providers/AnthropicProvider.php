<?php
/**
 * AI-Core Library - Anthropic Provider
 * 
 * Handles communication with Anthropic API
 * Extracted from article_builder.php Anthropic integration logic
 * 
 * @package AI_Core
 * @version 1.0.0
 */

namespace AICore\Providers;

use AICore\Interfaces\ProviderInterface;
use AICore\Http\HttpClient;
use AICore\Response\ResponseNormalizer;
use AICore\Registry\ModelRegistry;

class AnthropicProvider implements ProviderInterface {
    
    /**
     * Anthropic API endpoint
     */
    const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    
    /**
     * API version
     */
    const API_VERSION = '2023-06-01';
    
    /**
     * API key for authentication
     * 
     * @var string
     */
    private $api_key;
    
    /**
     * Constructor
     * 
     * @param string $api_key Anthropic API key
     */
    public function __construct(string $api_key) {
        $this->api_key = $api_key;
    }
    
    /**
     * Send request to Anthropic API
     * 
     * @param array $messages Array of messages for the conversation
     * @param array $options Request options (model, temperature, max_tokens, etc.)
     * @return array Normalized response array
     * @throws \Exception On API errors
     */
    public function sendRequest(array $messages, array $options = []): array {
        
        if (!$this->isConfigured()) {
            throw new \Exception('Anthropic provider not configured: missing API key');
        }
        
        // Convert OpenAI message format to Anthropic format
        $anthropic_messages = $this->convertMessagesToAnthropicFormat($messages);
        
        // Prepare request payload
        $payload = [
            'model' => $options['model'] ?? 'claude-sonnet-4-20250514',
            'max_tokens' => $options['max_tokens'] ?? 4000,
            'messages' => $anthropic_messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'top_p' => $options['top_p'] ?? 1.0
        ];
        
        // Add system message if provided
        if (isset($options['system'])) {
            $payload['system'] = $options['system'];
        }
        
        // Add optional parameters if provided
        if (isset($options['stop_sequences'])) {
            $payload['stop_sequences'] = $options['stop_sequences'];
        }
        
        // Prepare headers
        $headers = [
            'x-api-key' => $this->api_key,
            'anthropic-version' => self::API_VERSION,
            'Content-Type' => 'application/json'
        ];
        
        // Send request
        try {
            $response = HttpClient::post(self::API_ENDPOINT, $payload, $headers);
            
            // Normalize response to OpenAI format
            return ResponseNormalizer::normalize($response, 'anthropic');
            
        } catch (\Exception $e) {
            throw new \Exception('Anthropic API request failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Convert OpenAI message format to Anthropic format
     * 
     * @param array $messages OpenAI format messages
     * @return array Anthropic format messages
     */
    private function convertMessagesToAnthropicFormat(array $messages): array {
        $anthropic_messages = [];
        
        foreach ($messages as $message) {
            // Skip system messages (handled separately in Anthropic)
            if (($message['role'] ?? '') === 'system') {
                continue;
            }
            
            $anthropic_message = [
                'role' => $message['role'] ?? 'user',
                'content' => $message['content'] ?? ''
            ];
            
            $anthropic_messages[] = $anthropic_message;
        }
        
        return $anthropic_messages;
    }
    
    /**
     * Extract system message from OpenAI messages
     * 
     * @param array $messages OpenAI format messages
     * @return string|null System message content
     */
    private function extractSystemMessage(array $messages): ?string {
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                return $message['content'] ?? null;
            }
        }
        
        return null;
    }
    
    /**
     * Get provider name
     * 
     * @return string Provider identifier
     */
    public function getName(): string {
        return 'anthropic';
    }
    
    /**
     * Check if provider supports a specific model
     * 
     * @param string $model Model identifier
     * @return bool True if model is supported
     */
    public function supportsModel(string $model): bool {
        return ModelRegistry::isAnthropicModel($model);
    }
    
    /**
     * Get available models for this provider
     * 
     * @return array Array of supported model identifiers
     */
    public function getAvailableModels(): array {
        return ModelRegistry::getModelsByProvider('anthropic');
    }
    
    /**
     * Validate provider configuration
     * 
     * @return bool True if provider is properly configured
     */
    public function isConfigured(): bool {
        return !empty($this->api_key) && strlen($this->api_key) > 10;
    }
    
    /**
     * Test API connection
     *
     * @return bool True if API is accessible
     */
    public function testConnection(): bool {
        try {
            $test_messages = [
                ['role' => 'user', 'content' => 'Hello']
            ];

            $response = $this->sendRequest($test_messages, [
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 10
            ]);

            return !empty(ResponseNormalizer::extractContent($response));

        } catch (\Exception $e) {
            return false;
        }
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
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 10
            ]);

            return [
                'valid' => true,
                'provider' => 'anthropic',
                'model' => 'claude-sonnet-4-20250514'
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get API key (masked for security)
     * 
     * @return string Masked API key
     */
    public function getMaskedApiKey(): string {
        if (empty($this->api_key)) {
            return 'Not configured';
        }
        
        return substr($this->api_key, 0, 7) . '...' . substr($this->api_key, -4);
    }
    
    /**
     * Update API key
     * 
     * @param string $api_key New API key
     * @return void
     */
    public function setApiKey(string $api_key): void {
        $this->api_key = $api_key;
    }
    
    /**
     * Get default model for this provider
     * 
     * @return string Default model identifier
     */
    public function getDefaultModel(): string {
        return 'claude-sonnet-4-20250514';
    }
    
    /**
     * Calculate estimated cost for request
     * 
     * @param array $messages Messages array
     * @param string $model Model identifier
     * @return float Estimated cost in USD
     */
    public function estimateCost(array $messages, string $model): float {
        // Rough token estimation (4 chars = 1 token)
        $total_chars = 0;
        foreach ($messages as $message) {
            $total_chars += strlen($message['content'] ?? '');
        }
        
        $estimated_tokens = ceil($total_chars / 4);
        
        // Basic pricing (simplified - real pricing varies by model)
        $cost_per_1k_tokens = 0.015; // $0.015 per 1K tokens (approximate)
        
        return ($estimated_tokens / 1000) * $cost_per_1k_tokens;
    }
}