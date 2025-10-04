<?php
/**
 * AI-Core Library - OpenAI Image Provider
 * 
 * Handles image generation using OpenAI DALL-E
 * Extracted from article_builder.php image generation logic
 * 
 * @package AI_Core
 * @version 1.0.0
 */

namespace AICore\Providers;

use AICore\Interfaces\ImageProviderInterface;
use AICore\Http\HttpClient;
use AICore\Registry\ModelRegistry;

class OpenAIImageProvider implements ImageProviderInterface {
    
    /**
     * OpenAI Image Generation API endpoint
     */
    const API_ENDPOINT = 'https://api.openai.com/v1/images/generations';
    
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
     * Generate an image from a text prompt
     * 
     * @param string $prompt Text description for image generation
     * @param array $options Image generation options (size, quality, style, etc.)
     * @return array Response containing image URL and metadata
     * @throws \Exception On API errors
     */
    public function generateImage(string $prompt, array $options = []): array {
        
        if (!$this->isConfigured()) {
            throw new \Exception('OpenAI Image provider not configured: missing API key');
        }
        
        if (empty(trim($prompt))) {
            throw new \Exception('Image prompt cannot be empty');
        }
        
        // Prepare request payload
        $payload = [
            'model' => $options['model'] ?? 'dall-e-3',
            'prompt' => trim($prompt),
            'n' => $options['n'] ?? 1,
            'size' => $options['size'] ?? '1024x1024',
            'quality' => $options['quality'] ?? 'standard',
            'response_format' => $options['response_format'] ?? 'url'
        ];
        
        // Add style if supported (DALL-E 3)
        if (isset($options['style']) && $payload['model'] === 'dall-e-3') {
            $payload['style'] = $options['style'];
        }
        
        // Prepare headers
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        ];
        
        // Send request
        try {
            $response = HttpClient::post(self::API_ENDPOINT, $payload, $headers, 180); // 3 minute timeout for image generation
            
            // Validate response
            if (!isset($response['data']) || empty($response['data'])) {
                throw new \Exception('Invalid response from OpenAI Image API: missing data');
            }
            
            // Extract image information
            $image_data = $response['data'][0];
            
            return [
                'url' => $image_data['url'] ?? null,
                'revised_prompt' => $image_data['revised_prompt'] ?? $prompt,
                'size' => $payload['size'],
                'quality' => $payload['quality'],
                'model' => $payload['model'],
                'created' => time(),
                'prompt' => $prompt
            ];
            
        } catch (\Exception $e) {
            throw new \Exception('OpenAI Image API request failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get provider name
     * 
     * @return string Provider identifier
     */
    public function getName(): string {
        return 'openai-dalle';
    }
    
    /**
     * Get supported image sizes
     * 
     * @return array Array of supported size strings
     */
    public function getSupportedSizes(): array {
        return [
            '1024x1024',  // Square (default)
            '1024x1792',  // Portrait
            '1792x1024',  // Landscape
            '512x512',    // Legacy DALL-E 2
            '256x256'     // Legacy DALL-E 2
        ];
    }
    
    /**
     * Get supported quality levels
     * 
     * @return array Array of supported quality levels
     */
    public function getSupportedQualities(): array {
        return [
            'standard',   // Default quality
            'hd'          // High definition (DALL-E 3 only)
        ];
    }
    
    /**
     * Get supported styles
     * 
     * @return array Array of supported style options
     */
    public function getSupportedStyles(): array {
        return [
            'vivid',      // Default - more dramatic, hyper-real
            'natural'     // More natural, less hyper-real
        ];
    }
    
    /**
     * Get supported models
     * 
     * @return array Array of supported model identifiers
     */
    public function getSupportedModels(): array {
        return [
            'dall-e-3',   // Latest model (default)
            'dall-e-2'    // Legacy model
        ];
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
     * Test image generation capability
     * 
     * @return bool True if image generation is working
     */
    public function testImageGeneration(): bool {
        try {
            $response = $this->generateImage('A simple test image', [
                'size' => '256x256',
                'quality' => 'standard',
                'model' => 'dall-e-2'
            ]);
            
            return !empty($response['url']);
            
        } catch (\Exception $e) {
            return false;
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
     * Validate image generation options
     * 
     * @param array $options Options to validate
     * @return array Validated options with defaults
     * @throws \Exception On invalid options
     */
    public function validateOptions(array $options): array {
        $validated = [];
        
        // Validate size
        $size = $options['size'] ?? '1024x1024';
        if (!in_array($size, $this->getSupportedSizes())) {
            throw new \Exception("Unsupported image size: {$size}");
        }
        $validated['size'] = $size;
        
        // Validate quality
        $quality = $options['quality'] ?? 'standard';
        if (!in_array($quality, $this->getSupportedQualities())) {
            throw new \Exception("Unsupported quality level: {$quality}");
        }
        $validated['quality'] = $quality;
        
        // Validate model
        $model = $options['model'] ?? 'dall-e-3';
        if (!in_array($model, $this->getSupportedModels())) {
            throw new \Exception("Unsupported model: {$model}");
        }
        $validated['model'] = $model;
        
        // Validate style (DALL-E 3 only)
        if (isset($options['style'])) {
            if ($model !== 'dall-e-3') {
                throw new \Exception("Style option only supported with dall-e-3 model");
            }
            if (!in_array($options['style'], $this->getSupportedStyles())) {
                throw new \Exception("Unsupported style: {$options['style']}");
            }
            $validated['style'] = $options['style'];
        }
        
        // Validate number of images
        $n = $options['n'] ?? 1;
        if ($n < 1 || $n > 10) {
            throw new \Exception("Number of images must be between 1 and 10");
        }
        $validated['n'] = $n;
        
        return $validated;
    }
    
    /**
     * Calculate estimated cost for image generation
     * 
     * @param array $options Image generation options
     * @return float Estimated cost in USD
     */
    public function estimateCost(array $options): float {
        $model = $options['model'] ?? 'dall-e-3';
        $size = $options['size'] ?? '1024x1024';
        $quality = $options['quality'] ?? 'standard';
        $n = $options['n'] ?? 1;
        
        // Basic pricing (simplified - real pricing may vary)
        $base_cost = 0.04; // $0.04 per image (approximate)
        
        // Adjust for quality
        if ($quality === 'hd') {
            $base_cost *= 2;
        }
        
        // Adjust for size
        if ($size === '1792x1024' || $size === '1024x1792') {
            $base_cost *= 1.5;
        }
        
        return $base_cost * $n;
    }
}