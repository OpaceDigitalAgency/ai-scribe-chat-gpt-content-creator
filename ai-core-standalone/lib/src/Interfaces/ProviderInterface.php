<?php
/**
 * AI-Core Library - Provider Interface
 * 
 * Base interface for all AI providers (OpenAI, Anthropic, etc.)
 * 
 * @package AI_Core
 * @version 1.0.0
 */

namespace AICore\Interfaces;

interface ProviderInterface {
    
    /**
     * Send a request to the AI provider
     * 
     * @param array $messages Array of messages for the conversation
     * @param array $options Provider-specific options (model, temperature, etc.)
     * @return array Normalized response array
     * @throws \Exception On API errors
     */
    public function sendRequest(array $messages, array $options = []): array;
    
    /**
     * Get the provider name
     * 
     * @return string Provider identifier (e.g., 'openai', 'anthropic')
     */
    public function getName(): string;
    
    /**
     * Check if the provider supports a specific model
     * 
     * @param string $model Model identifier
     * @return bool True if model is supported
     */
    public function supportsModel(string $model): bool;
    
    /**
     * Get available models for this provider
     * 
     * @return array Array of supported model identifiers
     */
    public function getAvailableModels(): array;
    
    /**
     * Validate provider configuration
     * 
     * @return bool True if provider is properly configured
     */
    public function isConfigured(): bool;
}