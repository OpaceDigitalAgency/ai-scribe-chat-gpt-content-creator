<?php
/**
 * AI-Core Library - OpenAI Provider
 * 
 * Handles communication with OpenAI API
 * Extracted from article_builder.php OpenAI integration logic
 * 
 * @package AI_Core
 * @version 1.0.0
 */

namespace AICore\Providers;

use AICore\Interfaces\ProviderInterface;
use AICore\Http\HttpClient;
use AICore\Response\ResponseNormalizer;
use AICore\Registry\ModelRegistry;

class OpenAIProvider implements ProviderInterface {
    
    /**
     * OpenAI API endpoints
     */
    const CHAT_COMPLETIONS_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    const RESPONSES_ENDPOINT = 'https://api.openai.com/v1/responses';
    
    /**
     * API key for authentication
     * 
     * @var string
     */
    private $api_key;
    
    /**
     * Constructor
     * 
     * @param string $api_key OpenAI API key
     */
    public function __construct(string $api_key) {
        $this->api_key = $api_key;
    }
    
    /**
     * Send request to OpenAI API
     * 
     * @param array $messages Array of messages for the conversation
     * @param array $options Request options (model, temperature, max_tokens, etc.)
     * @return array Normalized response array
     * @throws \Exception On API errors
     */
    public function sendRequest(array $messages, array $options = []): array {
        
        if (!$this->isConfigured()) {
            throw new \Exception('OpenAI provider not configured: missing API key');
        }
        
        $model = $options['model'] ?? 'gpt-4o';
        
        // Check if this is an O3 model that requires Responses API
        if ($this->isO3Model($model)) {
            return $this->sendO3Request($messages, $options);
        }
        
        // Standard Chat Completions API for non-O3 models
        return $this->sendChatCompletionsRequest($messages, $options);
    }
    
    /**
     * Send request using Chat Completions API (standard models)
     *
     * @param array $messages Array of messages for the conversation
     * @param array $options Request options
     * @return array Normalized response array
     * @throws \Exception On API errors
     */
    private function sendChatCompletionsRequest(array $messages, array $options = []): array {
        // Prepare request payload
        $payload = [
            'model' => $options['model'] ?? 'gpt-4o',
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
        
        if (isset($options['functions'])) {
            $payload['functions'] = $options['functions'];
        }
        
        // Prepare headers
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        ];
        
        // Send request
        try {
            $response = HttpClient::post(self::CHAT_COMPLETIONS_ENDPOINT, $payload, $headers);
            
            // Normalize response (OpenAI responses are already in correct format)
            return ResponseNormalizer::normalize($response, 'openai');
            
        } catch (\Exception $e) {
            throw new \Exception('OpenAI API request failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Send request using Responses API (O3 models)
     *
     * @param array $messages Array of messages for the conversation
     * @param array $options Request options
     * @return array Normalized response array
     * @throws \Exception On API errors
     */
    private function sendO3Request(array $messages, array $options = []): array {
        $model = $options['model'] ?? 'o3';
        
        // Use reasoning_effort directly from options (set by Engine Service)
        $reasoning_effort = $options['reasoning_effort'] ?? 'medium';
        
        // Validate reasoning_effort value
        if (!in_array($reasoning_effort, ['low', 'medium', 'high'])) {
            $reasoning_effort = 'medium';
        }
        
        // Convert messages to O3 Responses API format
        $o3_input = [];
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                // System messages become user messages in O3
                $o3_input[] = [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $message['content']
                        ]
                    ]
                ];
            } else {
                // Convert user/assistant messages to O3 format
                $o3_input[] = [
                    'role' => $message['role'],
                    'content' => [
                        [
                            'type' => $message['role'] === 'user' ? 'input_text' : 'output_text',
                            'text' => $message['content']
                        ]
                    ]
                ];
            }
        }
        
        // Prepare O3 Responses API payload
        $payload = [
            'model' => $model,
            'input' => $o3_input,
            'text' => [
                'format' => [
                    'type' => 'text'
                ]
            ],
            'reasoning' => [
                'effort' => $reasoning_effort,
                'summary' => null // Save tokens since we don't display reasoning steps
            ],
            'store' => true
        ];
        
        // Prepare headers
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        ];
        
        // Send request
        try {
            $response = HttpClient::post(self::RESPONSES_ENDPOINT, $payload, $headers);
            
            // Normalize O3 response to standard format
            return ResponseNormalizer::normalize($response, 'openai-o3');
            
        } catch (\Exception $e) {
            throw new \Exception('OpenAI O3 API request failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if model is an O3 model
     *
     * @param string $model Model identifier
     * @return bool True if model is O3
     */
    protected function isO3Model(string $model): bool {
        return strpos($model, 'o3') !== false;
    }
    
    /**
     * Get provider name
     * 
     * @return string Provider identifier
     */
    public function getName(): string {
        return 'openai';
    }
    
    /**
     * Check if provider supports a specific model
     * 
     * @param string $model Model identifier
     * @return bool True if model is supported
     */
    public function supportsModel(string $model): bool {
        return ModelRegistry::isOpenAIModel($model) && !ModelRegistry::isImageModel($model);
    }
    
    /**
     * Get available models for this provider
     * 
     * @return array Array of supported model identifiers
     */
    public function getAvailableModels(): array {
        $all_models = ModelRegistry::getModelsByProvider('openai');
        
        // Filter out image models (handled by OpenAIImageProvider)
        return array_filter($all_models, function($model) {
            return !ModelRegistry::isImageModel($model);
        });
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
                'model' => 'gpt-4o-mini',
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
                'model' => 'gpt-4o-mini',
                'max_tokens' => 10
            ]);

            return [
                'valid' => true,
                'provider' => 'openai',
                'model' => 'gpt-4o-mini'
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
        return 'gpt-4o';
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
        $cost_per_1k_tokens = 0.03; // $0.03 per 1K tokens (approximate)
        
        return ($estimated_tokens / 1000) * $cost_per_1k_tokens;
    }
}