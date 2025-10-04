<?php
/**
 * AI-Core Library - Image Provider Interface
 * 
 * Interface for AI image generation providers
 * 
 * @package AI_Core
 * @version 1.0.0
 */

namespace AICore\Interfaces;

interface ImageProviderInterface {
    
    /**
     * Generate an image from a text prompt
     * 
     * @param string $prompt Text description for image generation
     * @param array $options Image generation options (size, quality, style, etc.)
     * @return array Response containing image URL and metadata
     * @throws \Exception On API errors
     */
    public function generateImage(string $prompt, array $options = []): array;
    
    /**
     * Get the provider name
     * 
     * @return string Provider identifier (e.g., 'openai-dalle', 'midjourney')
     */
    public function getName(): string;
    
    /**
     * Get supported image sizes
     * 
     * @return array Array of supported size strings (e.g., ['1024x1024', '512x512'])
     */
    public function getSupportedSizes(): array;
    
    /**
     * Get supported quality levels
     * 
     * @return array Array of supported quality levels (e.g., ['standard', 'hd'])
     */
    public function getSupportedQualities(): array;
    
    /**
     * Validate provider configuration
     * 
     * @return bool True if provider is properly configured
     */
    public function isConfigured(): bool;
}