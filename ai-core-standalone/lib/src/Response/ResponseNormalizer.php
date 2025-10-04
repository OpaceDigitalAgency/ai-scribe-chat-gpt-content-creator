<?php
/**
 * AI-Core Library - Response Normalizer
 * 
 * Standardizes responses from different AI providers into a common format
 * Handles the Anthropic -> OpenAI format conversion from lines 3087-3126
 * 
 * @package AI_Core
 * @version 1.0.0
 */

namespace AICore\Response;

class ResponseNormalizer {
    
    /**
     * Normalize response from any provider to OpenAI format
     *
     * @param array $response Raw provider response
     * @param string $provider Provider name ('openai', 'anthropic', 'gemini', 'grok')
     * @return array Normalized response in OpenAI format
     */
    public static function normalize(array $response, string $provider): array {
        switch ($provider) {
            case 'anthropic':
                return self::normalizeAnthropicResponse($response);
            case 'openai':
                return self::normalizeOpenAIResponse($response);
            case 'openai-o3':
                return self::normalizeO3Response($response);
            case 'gemini':
                return self::normalizeGeminiResponse($response);
            case 'grok':
                // Grok uses OpenAI-compatible format
                return self::normalizeOpenAIResponse($response);
            default:
                throw new \InvalidArgumentException("Unsupported provider: {$provider}");
        }
    }
    
    /**
     * Convert Anthropic response to OpenAI format
     * Extracted from lines 3087-3126 in article_builder.php
     * 
     * @param array $response Anthropic API response
     * @return array OpenAI-formatted response
     */
    private static function normalizeAnthropicResponse(array $response): array {
        $content = "";
        $debug_messages = [];
        
        // Handle Anthropic content array format
        if (isset($response["content"]) && is_array($response["content"])) {
            foreach ($response["content"] as $content_block) {
                if (isset($content_block["type"])) {
                    if ($content_block["type"] === "text" && isset($content_block["text"])) {
                        $content .= $content_block["text"];
                    } elseif ($content_block["type"] === "thinking" && isset($content_block["thinking"])) {
                        // Log thinking content but don't include in response
                        $debug_messages[] = "Claude thinking content received";
                    }
                }
            }
        }
        
        // Create OpenAI-compatible response structure
        $normalized_response = [
            "choices" => [
                [
                    "message" => [
                        "content" => $content,
                        "role" => "assistant"
                    ],
                    "finish_reason" => self::mapFinishReason($response["stop_reason"] ?? "stop"),
                    "index" => 0
                ]
            ],
            "usage" => [
                "prompt_tokens" => $response["usage"]["input_tokens"] ?? 0,
                "completion_tokens" => $response["usage"]["output_tokens"] ?? 0,
                "total_tokens" => ($response["usage"]["input_tokens"] ?? 0) + ($response["usage"]["output_tokens"] ?? 0)
            ],
            "model" => $response["model"] ?? "claude-unknown",
            "object" => "chat.completion",
            "created" => time(),
            "id" => "chatcmpl-" . uniqid()
        ];
        
        // Add debug information if available
        if (!empty($debug_messages)) {
            $normalized_response["_debug"] = $debug_messages;
        }
        
        return $normalized_response;
    }
    
    /**
     * Ensure OpenAI response is in expected format
     * 
     * @param array $response OpenAI API response
     * @return array Validated OpenAI response
     */
    private static function normalizeOpenAIResponse(array $response): array {
        // OpenAI responses should already be in the correct format
        // Just validate required fields exist
        if (!isset($response["choices"]) || !is_array($response["choices"])) {
            throw new \InvalidArgumentException("Invalid OpenAI response: missing choices array");
        }
        
        if (empty($response["choices"])) {
            throw new \InvalidArgumentException("Invalid OpenAI response: empty choices array");
        }
        
        $first_choice = $response["choices"][0];
        if (!isset($first_choice["message"]["content"])) {
            throw new \InvalidArgumentException("Invalid OpenAI response: missing message content");
        }
        
        return $response;
    }
    
    /**
     * Convert O3 Responses API response to OpenAI Chat Completions format
     *
     * @param array $response O3 Responses API response
     * @return array OpenAI Chat Completions formatted response
     */
    private static function normalizeO3Response(array $response): array {
        // O3 Responses API returns a different structure than Chat Completions
        // Extract content from O3 response format
        $content = "";

        if (isset($response["output"]) && is_array($response["output"])) {
            foreach ($response["output"] as $output_block) {
                if (isset($output_block["content"]) && is_array($output_block["content"])) {
                    foreach ($output_block["content"] as $content_block) {
                        if (isset($content_block["text"])) {
                            $content .= $content_block["text"];
                        }
                    }
                }
            }
        }

        // If no content found in output, try alternative structure
        if (empty($content) && isset($response["choices"][0]["message"]["content"])) {
            $content = $response["choices"][0]["message"]["content"];
        }

        // Create OpenAI Chat Completions compatible response structure
        $normalized_response = [
            "choices" => [
                [
                    "message" => [
                        "content" => $content,
                        "role" => "assistant"
                    ],
                    "finish_reason" => "stop",
                    "index" => 0
                ]
            ],
            "usage" => [
                "prompt_tokens" => $response["usage"]["input_tokens"] ?? 0,
                "completion_tokens" => $response["usage"]["output_tokens"] ?? 0,
                "total_tokens" => ($response["usage"]["input_tokens"] ?? 0) + ($response["usage"]["output_tokens"] ?? 0)
            ],
            "model" => $response["model"] ?? "o3",
            "object" => "chat.completion",
            "created" => time(),
            "id" => "chatcmpl-" . uniqid()
        ];

        return $normalized_response;
    }

    /**
     * Convert Gemini response to OpenAI format
     *
     * @param array $response Gemini API response
     * @return array OpenAI-formatted response
     */
    private static function normalizeGeminiResponse(array $response): array {
        $content = "";

        // Extract content from Gemini response format
        if (isset($response["candidates"]) && is_array($response["candidates"])) {
            foreach ($response["candidates"] as $candidate) {
                if (isset($candidate["content"]["parts"]) && is_array($candidate["content"]["parts"])) {
                    foreach ($candidate["content"]["parts"] as $part) {
                        if (isset($part["text"])) {
                            $content .= $part["text"];
                        }
                    }
                }
            }
        }

        // Extract usage metadata
        $prompt_tokens = 0;
        $completion_tokens = 0;

        if (isset($response["usageMetadata"])) {
            $prompt_tokens = $response["usageMetadata"]["promptTokenCount"] ?? 0;
            $completion_tokens = $response["usageMetadata"]["candidatesTokenCount"] ?? 0;
        }

        // Create OpenAI-compatible response structure
        $normalized_response = [
            "choices" => [
                [
                    "message" => [
                        "content" => $content,
                        "role" => "assistant"
                    ],
                    "finish_reason" => self::mapGeminiFinishReason($response["candidates"][0]["finishReason"] ?? "STOP"),
                    "index" => 0
                ]
            ],
            "usage" => [
                "prompt_tokens" => $prompt_tokens,
                "completion_tokens" => $completion_tokens,
                "total_tokens" => $prompt_tokens + $completion_tokens
            ],
            "model" => $response["modelVersion"] ?? "gemini-unknown",
            "object" => "chat.completion",
            "created" => time(),
            "id" => "chatcmpl-" . uniqid()
        ];

        return $normalized_response;
    }
    
    /**
     * Map Anthropic stop reasons to OpenAI finish reasons
     *
     * @param string $anthropic_reason Anthropic stop reason
     * @return string OpenAI finish reason
     */
    private static function mapFinishReason(string $anthropic_reason): string {
        $mapping = [
            "end_turn" => "stop",
            "max_tokens" => "length",
            "stop_sequence" => "stop",
            "tool_use" => "function_call"
        ];

        return $mapping[$anthropic_reason] ?? "stop";
    }

    /**
     * Map Gemini finish reasons to OpenAI finish reasons
     *
     * @param string $gemini_reason Gemini finish reason
     * @return string OpenAI finish reason
     */
    private static function mapGeminiFinishReason(string $gemini_reason): string {
        $mapping = [
            "STOP" => "stop",
            "MAX_TOKENS" => "length",
            "SAFETY" => "content_filter",
            "RECITATION" => "content_filter",
            "OTHER" => "stop"
        ];

        return $mapping[$gemini_reason] ?? "stop";
    }
    
    /**
     * Extract content from normalized response
     * 
     * @param array $normalized_response Normalized response
     * @return string Content text
     */
    public static function extractContent(array $normalized_response): string {
        return $normalized_response["choices"][0]["message"]["content"] ?? "";
    }
    
    /**
     * Extract usage information from normalized response
     * 
     * @param array $normalized_response Normalized response
     * @return array Usage statistics
     */
    public static function extractUsage(array $normalized_response): array {
        return $normalized_response["usage"] ?? [
            "prompt_tokens" => 0,
            "completion_tokens" => 0,
            "total_tokens" => 0
        ];
    }
    
    /**
     * Check if response indicates an error
     * 
     * @param array $response Raw or normalized response
     * @return bool True if response contains an error
     */
    public static function hasError(array $response): bool {
        return isset($response["error"]) || 
               (isset($response["choices"]) && empty($response["choices"])) ||
               (isset($response["choices"][0]["message"]["content"]) && 
                empty(trim($response["choices"][0]["message"]["content"])));
    }
    
    /**
     * Extract error message from response
     * 
     * @param array $response Response with error
     * @return string Error message
     */
    public static function extractError(array $response): string {
        if (isset($response["error"]["message"])) {
            return $response["error"]["message"];
        }
        
        if (isset($response["error"]) && is_string($response["error"])) {
            return $response["error"];
        }
        
        return "Unknown error occurred";
    }
}