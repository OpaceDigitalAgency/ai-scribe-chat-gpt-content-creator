<?php
/**
 * AI-Core Library - HTTP Client
 * 
 * Abstraction layer for HTTP communication with AI providers
 * Handles common functionality like headers, timeouts, and error handling
 * 
 * @package AI_Core
 * @version 1.0.0
 */

namespace AICore\Http;

class HttpClient {
    
    /**
     * Default timeout for requests (seconds)
     */
    const DEFAULT_TIMEOUT = 120;
    
    /**
     * Send POST request to API endpoint
     * 
     * @param string $url API endpoint URL
     * @param array $data Request payload
     * @param array $headers HTTP headers
     * @param int $timeout Request timeout in seconds
     * @return array Response data
     * @throws \Exception On HTTP errors or invalid responses
     */
    public static function post(string $url, array $data, array $headers = [], int $timeout = self::DEFAULT_TIMEOUT): array {
        
        // Prepare request arguments for wp_remote_post
        $args = [
            'method' => 'POST',
            'timeout' => $timeout,
            'headers' => array_merge([
                'Content-Type' => 'application/json',
                'User-Agent' => 'AI-Scribe/' . self::getVersion()
            ], $headers),
            'body' => json_encode($data),
            'sslverify' => true
        ];
        
        // Send request using WordPress HTTP API
        $response = wp_remote_post($url, $args);
        
        // Check for WordPress HTTP errors
        if (is_wp_error($response)) {
            throw new \Exception('HTTP Request failed: ' . $response->get_error_message());
        }
        
        // Get response code and body
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Check for HTTP error codes
        if ($response_code < 200 || $response_code >= 300) {
            $error_message = "HTTP {$response_code}";
            
            // Try to extract error message from response body
            $error_data = json_decode($response_body, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($error_data['error']['message'])) {
                $error_message .= ': ' . $error_data['error']['message'];
            } elseif (!empty($response_body)) {
                $error_message .= ': ' . substr($response_body, 0, 200);
            }
            
            throw new \Exception($error_message);
        }
        
        // Decode JSON response
        $decoded_response = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        return $decoded_response;
    }
    
    /**
     * Send GET request to API endpoint
     *
     * @param string $url API endpoint URL
     * @param array $params Query parameters
     * @param array $headers HTTP headers
     * @param int $timeout Request timeout in seconds
     * @return array Response data
     * @throws \Exception On HTTP errors or invalid responses
     */
    public static function get(string $url, array $params = [], array $headers = [], int $timeout = self::DEFAULT_TIMEOUT): array {
        
        // Prepare request arguments for wp_remote_get
        $args = [
            'timeout' => $timeout,
            'headers' => array_merge([
                'User-Agent' => 'AI-Scribe/' . self::getVersion()
            ], $headers),
            'sslverify' => true
        ];
        
        // Send request using WordPress HTTP API
        $response = wp_remote_get($url, $args);
        
        // Check for WordPress HTTP errors
        if (is_wp_error($response)) {
            throw new \Exception('HTTP Request failed: ' . $response->get_error_message());
        }
        
        // Get response code and body
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Check for HTTP error codes
        if ($response_code < 200 || $response_code >= 300) {
            throw new \Exception("HTTP {$response_code}: " . substr($response_body, 0, 200));
        }
        
        // Decode JSON response
        $decoded_response = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        return $decoded_response;
    }
    
    /**
     * Get AI-Core library version
     * 
     * @return string Version string
     */
    private static function getVersion(): string {
        return '1.0.0';
    }
    
    /**
     * Validate URL format
     * 
     * @param string $url URL to validate
     * @return bool True if URL is valid
     */
    public static function isValidUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Build query string from array
     * 
     * @param array $params Query parameters
     * @return string Query string
     */
    public static function buildQueryString(array $params): string {
        return http_build_query($params);
    }
}