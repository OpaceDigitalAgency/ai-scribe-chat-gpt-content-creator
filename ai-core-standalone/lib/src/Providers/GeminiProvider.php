<?php
/**
 * AI-Core Library - Google Gemini Provider
 * 
 * Handles communication with Google Gemini API
 * 
 * @package AI_Core
 * @version 1.0.0
 */

namespace AICore\Providers;

use AICore\Interfaces\ProviderInterface;
use AICore\Http\HttpClient;
use AICore\Response\ResponseNormalizer;
use AICore\Registry\ModelRegistry;

class GeminiProvider implements ProviderInterface {
    
    /**
     * Gemini API endpoint base
     */
    const API_ENDPOINT_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    /**
     * API key for authentication
     * 
     * @var string
     */
    private $api_key;
    
    /**
     * Constructor
     * 
     * @param string $api_key Google Gemini API key
     */
    public function __construct(string $api_key) {
        $this->api_key = $api_key;
    }
    
    /**
     * Send request to Gemini API
     * 
     * @param array $messages Array of messages for the conversation
     * @param array $options Request options (model, temperature, max_tokens, etc.)
     * @return array Normalized response array
     * @throws \Exception On API errors
     */
    public function sendRequest(array $messages, array $options = []): array {
        
        if (!$this->isConfigured()) {
            throw new \Exception('Gemini provider not configured: missing API key');
        }
        
        $model = $options['model'] ?? 'gemini-2.0-flash-exp';
        
        // Convert OpenAI message format to Gemini format
        $gemini_contents = $this->convertMessagesToGeminiFormat($messages);
        
        // Prepare request payload
        $payload = [
            'contents' => $gemini_contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'topP' => $options['top_p'] ?? 1.0,
                'maxOutputTokens' => $options['max_tokens'] ?? 4000,
            ]
        ];
        
        // Add system instruction if provided
        if (isset($options['system'])) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $options['system']]
                ]
            ];
        }
        
        // Add optional parameters if provided
        if (isset($options['stop_sequences'])) {
            $payload['generationConfig']['stopSequences'] = $options['stop_sequences'];
        }
        
        // Build endpoint URL with API key
        $endpoint = self::API_ENDPOINT_BASE . $model . ':generateContent?key=' . $this->api_key;
        
        // Prepare headers
        $headers = [
            'Content-Type' => 'application/json'
        ];
        
        // Send request
        try {
            $response = HttpClient::post($endpoint, $payload, $headers);
            
            // Normalize response to OpenAI format
            return ResponseNormalizer::normalize($response, 'gemini');
            
        } catch (\Exception $e) {
            throw new \Exception('Gemini API request failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Convert OpenAI message format to Gemini format
     * 
     * @param array $messages OpenAI format messages
     * @return array Gemini format contents
     */
    private function convertMessagesToGeminiFormat(array $messages): array {
        $contents = [];
        $system_message = null;
        
        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $content = $message['content'] ?? '';
            
            // Handle system messages separately
            if ($role === 'system') {
                $system_message = $content;
                continue;
            }
            
            // Map OpenAI roles to Gemini roles
            $gemini_role = $role === 'assistant' ? 'model' : 'user';
            
            $contents[] = [
                'role' => $gemini_role,
                'parts' => [
                    ['text' => $content]
                ]
            ];
        }
        
        return $contents;
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
        return 'gemini';
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
                'model' => 'gemini-2.0-flash-exp',
                'max_tokens' => 10
            ]);
            
            return [
                'valid' => true,
                'provider' => 'gemini',
                'model' => 'gemini-2.0-flash-exp'
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
            // Build endpoint URL for listing models
            $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $this->api_key;
            
            $headers = [
                'Content-Type' => 'application/json'
            ];
            
            $response = HttpClient::get($endpoint, [], $headers);
            
            $models = [];
            if (isset($response['models']) && is_array($response['models'])) {
                foreach ($response['models'] as $model) {
                    if (isset($model['name']) && strpos($model['name'], 'gemini') !== false) {
                        // Extract model ID from full name (e.g., "models/gemini-pro" -> "gemini-pro")
                        $model_id = str_replace('models/', '', $model['name']);
                        
                        $models[] = [
                            'id' => $model_id,
                            'name' => $model['displayName'] ?? $model_id,
                            'description' => $model['description'] ?? '',
                            'max_tokens' => $model['outputTokenLimit'] ?? 8192,
                        ];
                    }
                }
            }
            
            return $models;
            
        } catch (\Exception $e) {
            // Return default models if API call fails
            return [
                [
                    'id' => 'gemini-2.0-flash-exp',
                    'name' => 'Gemini 2.0 Flash (Experimental)',
                    'description' => 'Latest experimental Gemini model',
                    'max_tokens' => 8192,
                ],
                [
                    'id' => 'gemini-1.5-pro',
                    'name' => 'Gemini 1.5 Pro',
                    'description' => 'Advanced reasoning and long context',
                    'max_tokens' => 8192,
                ],
                [
                    'id' => 'gemini-1.5-flash',
                    'name' => 'Gemini 1.5 Flash',
                    'description' => 'Fast and efficient',
                    'max_tokens' => 8192,
                ],
            ];
        }
    }
}

