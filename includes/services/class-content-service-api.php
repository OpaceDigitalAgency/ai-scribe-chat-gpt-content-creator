<?php
/**
 * AI Content Service API Handler for AI-Scribe Plugin
 *
 * Handles API request preparation, execution, and response processing
 * for the Content Service. Split from main class due to 500 line limit.
 *
 * @package AI_Scribe
 * @subpackage Services
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Content_Service_API
 *
 * API handling extension for Content Service
 */
class AI_Scribe_Content_Service_API {

	/**
	 * Prepare API request based on model type
	 *
	 * @param bool $is_anthropic_model Whether using Anthropic
	 * @param string $model Model name
	 * @param array $messages Messages array
	 * @param array $model_params Model parameters
	 * @param string $apikey OpenAI API key
	 * @param string $anthropic_api_key Anthropic API key
	 * @param array $debug_messages Debug messages
	 * @return array API request configuration
	 */
	public function prepare_api_request( $is_anthropic_model, $model, $messages, $model_params, $apikey, $anthropic_api_key, &$debug_messages ) {
		if ( $is_anthropic_model ) {
			return $this->prepare_anthropic_request( $model, $messages, $model_params, $anthropic_api_key, $debug_messages );
		} else {
			return $this->prepare_openai_request( $model, $messages, $model_params, $apikey, $debug_messages );
		}
	}

	/**
	 * Prepare Anthropic API request
	 *
	 * @param string $model Model name
	 * @param array $messages Messages array
	 * @param array $model_params Model parameters
	 * @param string $anthropic_api_key API key
	 * @param array $debug_messages Debug messages
	 * @return array Request configuration
	 */
	private function prepare_anthropic_request( $model, $messages, $model_params, $anthropic_api_key, &$debug_messages ) {
		$url = 'https://api.anthropic.com/v1/messages';

		// Convert messages format for Anthropic
		$anthropic_messages = array();
		$system_message     = '';

		foreach ( $messages as $message ) {
			if ( $message['role'] === 'system' ) {
				$system_message = $message['content'];
			} else {
				$anthropic_messages[] = array(
					'role'    => $message['role'],
					'content' => array(
						array(
							'type' => 'text',
							'text' => $message['content'],
						),
					),
				);
			}
		}

		// Map display names to actual Anthropic API model names
		$anthropic_model_mapping = array(
			'claude-sonnet-4-20250514'   => 'claude-3-5-sonnet-20241022',
			'claude-opus-4-20250514'     => 'claude-3-opus-20240229',
			'claude-3-5-sonnet-20250514' => 'claude-3-5-sonnet-20241022',
		);

		$actual_anthropic_model = $anthropic_model_mapping[ $model ] ?? $model;
		$debug_messages[]       = "Anthropic model mapping: $model -> $actual_anthropic_model";

		$send_arr = array(
			'model'       => $actual_anthropic_model,
			'max_tokens'  => $model_params['max_tokens'],
			'temperature' => $model_params['temperature'],
			'messages'    => $anthropic_messages,
		);

		if ( ! empty( $system_message ) ) {
			$send_arr['system'] = $system_message;
		}

		$args = array(
			'timeout' => $model_params['timeout'],
			'headers' => array(
				'x-api-key'         => $anthropic_api_key,
				'Content-Type'      => 'application/json',
				'anthropic-version' => '2023-06-01',
			),
			'body'    => json_encode( $send_arr ),
		);

		$debug_messages[] = 'Using Anthropic API for model: ' . $model;
		$debug_messages[] = 'Anthropic request format: ' . json_encode( $send_arr );

		return array(
			'url'  => $url,
			'args' => $args,
		);
	}

	/**
	 * Prepare OpenAI API request
	 *
	 * @param string $model Model name
	 * @param array $messages Messages array
	 * @param array $model_params Model parameters
	 * @param string $apikey API key
	 * @param array $debug_messages Debug messages
	 * @return array Request configuration
	 */
	private function prepare_openai_request( $model, $messages, $model_params, $apikey, &$debug_messages ) {
		// Check model type FIRST, then build appropriate request

		// Special handling for o3 reasoning models
		if ( in_array( $model, array( 'o3', 'o3-mini' ) ) ) {
			$debug_messages[] = "Detected o3 model: $model - routing to Responses API";
			return $this->prepare_o3_request( $model, $messages, $model_params, $apikey, $debug_messages );
		}

		// Special handling for GPT-4.5
		if ( $model === 'gpt-4.5-preview' ) {
			$debug_messages[] = "Detected GPT-4.5 model: $model - using special handling";
			return $this->prepare_gpt45_request( $model, $messages, $model_params, $apikey, $debug_messages );
		}

		// Standard OpenAI models - build appropriate parameter array
		$endpoint = 'v1/chat/completions';
		$url      = 'https://api.openai.com/' . $endpoint;

		$send_arr = array(
			'model'             => $model,
			'messages'          => $messages,
			'temperature'       => $model_params['temperature'] * 1.5,
			'max_tokens'        => $model_params['max_tokens'],
			'top_p'             => $model_params['top_p'],
			'frequency_penalty' => $model_params['frequency_penalty'] / 2,
			'presence_penalty'  => $model_params['presence_penalty'] / 2,
			'stop'              => "\n\n\n",
			'n'                 => 1,
		);

		$args = array(
			'timeout'     => $model_params['timeout'],
			'redirection' => 10,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $apikey,
				'Content-Type'  => 'application/json',
			),
			'body'        => json_encode( $send_arr ),
			'cookies'     => array(),
		);

		$debug_messages[] = 'Using OpenAI Chat Completions API for standard model: ' . $model;
		$debug_messages[] = 'Standard OpenAI request parameters: ' . json_encode( $send_arr );

		return array(
			'url'  => $url,
			'args' => $args,
		);
	}

	/**
	 * Prepare o3 model request (Responses API)
	 *
	 * @param string $model Model name
	 * @param array $messages Messages array
	 * @param array $model_params Model parameters
	 * @param string $apikey API key
	 * @param array $debug_messages Debug messages
	 * @return array Request configuration
	 */
	private function prepare_o3_request( $model, $messages, $model_params, $apikey, &$debug_messages ) {
		$endpoint = 'v1/responses';
		$url      = 'https://api.openai.com/' . $endpoint;

		// Get reasoning effort
		$reasoning_effort = 'medium';
		if ( isset( $_POST['reasoning_effort'] ) && in_array( $_POST['reasoning_effort'], array( 'low', 'medium', 'high' ) ) ) {
			$reasoning_effort = sanitize_text_field( wp_unslash( $_POST['reasoning_effort'] ) );
		} else {
			// Map temperature to reasoning effort
			$temp = $model_params['temperature'];
			if ( $temp <= 0.3 ) {
				$reasoning_effort = 'low';
			} elseif ( $temp >= 0.7 ) {
				$reasoning_effort = 'high';
			}
		}

		// Convert messages to o3 format
		$o3_input = array();
		foreach ( $messages as $message ) {
			if ( $message['role'] === 'system' ) {
				$o3_input[] = array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'input_text',
							'text' => $message['content'],
						),
					),
				);
			} else {
				$o3_input[] = array(
					'role'    => $message['role'],
					'content' => array(
						array(
							'type' => $message['role'] === 'user' ? 'input_text' : 'output_text',
							'text' => $message['content'],
						),
					),
				);
			}
		}

		$send_arr = array(
			'model'     => $model,
			'input'     => $o3_input,
			'text'      => array(
				'format' => array(
					'type' => 'text',
				),
			),
			'reasoning' => array(
				'effort'  => $reasoning_effort,
				'summary' => null,
			),
			'store'     => true,
		);

		$args = array(
			'timeout'     => $model_params['timeout'],
			'redirection' => 10,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $apikey,
				'Content-Type'  => 'application/json',
			),
			'body'        => json_encode( $send_arr ),
			'cookies'     => array(),
		);

		$debug_messages[] = 'Using Responses API for o3 model: ' . $model;
		$debug_messages[] = 'o3 request format: ' . json_encode( $send_arr );
		$debug_messages[] = 'Request body size: ' . strlen( json_encode( $send_arr ) ) . ' bytes';

		return array(
			'url'  => $url,
			'args' => $args,
		);
	}

	/**
	 * Prepare GPT-4.5 request with special handling
	 *
	 * @param string $model Model name
	 * @param array $messages Messages array
	 * @param array $model_params Model parameters
	 * @param string $apikey API key
	 * @param array $debug_messages Debug messages
	 * @return array Request configuration
	 */
	private function prepare_gpt45_request( $model, $messages, $model_params, $apikey, &$debug_messages ) {
		$url = 'https://api.openai.com/v1/chat/completions';

		// Convert messages to playground format
		$playground_messages = array();
		foreach ( $messages as $message ) {
			if ( $message['role'] === 'system' ) {
				// Add GPT-4.5 specific ending instructions
				$gpt45_instructions    = " Write a maximum of 1000 words with a maxoutput of 1500 tokens. Make sure the article ends before the 1500 limit to avoid corruption. Do not attempt to summarise or add closing statements. End the article abruptly after the final section's last paragraph without repeating or restating ideas.";
				$playground_messages[] = array(
					'role'    => $message['role'],
					'content' => array(
						array(
							'type' => 'text',
							'text' => $message['content'] . $gpt45_instructions,
						),
					),
				);
			} else {
				// Modify user content for conciseness
				$user_content = $message['content'];
				if ( $message['role'] === 'user' ) {
					$user_content = str_replace(
						'Each section must be explored in detail. To achieve this, you must include all possible known features, benefits, arguments, analysis and whatever is needed to explore the topic to the best of your knowledge.',
						'Each section should offer original insight and practical value but remain concise and focused — prioritise clarity over completeness.',
						$user_content
					);
				}

				$playground_messages[] = array(
					'role'    => $message['role'],
					'content' => array(
						array(
							'type' => 'text',
							'text' => $user_content,
						),
					),
				);
			}
		}

		$send_arr = array(
			'model'             => $model,
			'messages'          => $playground_messages,
			'max_tokens'        => 2200,
			'response_format'   => array( 'type' => 'text' ),
			'presence_penalty'  => $model_params['presence_penalty'],
			'top_p'             => 0.9,
			'frequency_penalty' => 0.1,
			'temperature'       => $model_params['temperature'] * 1.5,
		);

		$args = array(
			'timeout'     => 100, // Reduced timeout for GPT-4.5
			'redirection' => 10,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $apikey,
				'Content-Type'  => 'application/json',
			),
			'body'        => json_encode( $send_arr ),
			'cookies'     => array(),
		);

		$debug_messages[] = '🔧 GPT-4.5: Using 2200 max_tokens, playground format, ending instructions, and 100s timeout';

		return array(
			'url'  => $url,
			'args' => $args,
		);
	}

	/**
	 * Make API call
	 *
	 * @param string $url API URL
	 * @param array $args Request arguments
	 * @param array $debug_messages Debug messages
	 * @return array|WP_Error Response data or error
	 */
	public function make_api_call( $url, $args, &$debug_messages ) {
		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			$debug_messages[] = '❌ API connection failed: ' . $response->get_error_message();
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error'] ) ) {
			$debug_messages[] = 'API Error detected';
			$debug_messages[] = 'Error Code: ' . ( $data['error']['code'] ?? 'unknown' );
			$debug_messages[] = 'Full Error: ' . json_encode( $data['error'] );

			return new WP_Error( 'api_error', 'Engine API Error: ' . $data['error']['message'] );
		}

		return $data;
	}

	/**
	 * Process API response based on provider
	 *
	 * @param array $data Response data
	 * @param bool $is_anthropic_model Whether Anthropic model
	 * @param string $model Model name
	 * @param array $debug_messages Debug messages
	 * @return object|WP_Error Processed response or error
	 */
	public function process_api_response( $data, $is_anthropic_model, $model, &$debug_messages ) {
		if ( $is_anthropic_model ) {
			return $this->process_anthropic_response( $data, $debug_messages );
		} else {
			return $this->process_openai_response( $data, $model, $debug_messages );
		}
	}

	/**
	 * Process Anthropic response
	 *
	 * @param array $data Response data
	 * @param array $debug_messages Debug messages
	 * @return object|WP_Error Processed response
	 */
	private function process_anthropic_response( $data, &$debug_messages ) {
		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			$content = '';
			foreach ( $data['content'] as $content_block ) {
				if ( isset( $content_block['type'] ) ) {
					if ( $content_block['type'] === 'text' && isset( $content_block['text'] ) ) {
						$content .= $content_block['text'];
					} elseif ( $content_block['type'] === 'thinking' && isset( $content_block['thinking'] ) ) {
						$debug_messages[] = 'Claude thinking content received (length: ' . strlen( $content_block['thinking'] ) . ' chars)';
					}
				} elseif ( isset( $content_block['text'] ) ) {
					$content .= $content_block['text'];
				}
			}

			$resArr = (object) array(
				'choices' => array(
					(object) array(
						'message' => (object) array(
							'content' => $content,
						),
					),
				),
			);

			$debug_messages[] = 'Anthropic response converted successfully (content length: ' . strlen( $content ) . ' chars)';
			return $resArr;
		} else {
			$debug_messages[] = 'Unexpected Anthropic response format';
			return new WP_Error( 'invalid_response', 'Unexpected response format from Anthropic API' );
		}
	}

	/**
	 * Process OpenAI response
	 *
	 * @param array $data Response data
	 * @param string $model Model name
	 * @param array $debug_messages Debug messages
	 * @return object|WP_Error Processed response
	 */
	private function process_openai_response( $data, $model, &$debug_messages ) {
		$resArr           = json_decode( json_encode( $data ) );
		$debug_messages[] = 'OpenAI response processed successfully';

		// Check if this is a Responses API response (for o3)
		if ( isset( $resArr->object ) && $resArr->object === 'response' ) {
			$debug_messages[] = 'Processing o3 Responses API format';

			$extractedText = '';
			if ( ! empty( $resArr->output ) && is_array( $resArr->output ) ) {
				foreach ( $resArr->output as $entry ) {
					if ( isset( $entry->type ) && $entry->type === 'message' &&
						! empty( $entry->content ) && is_array( $entry->content ) ) {
						foreach ( $entry->content as $contentObj ) {
							if ( isset( $contentObj->type ) && $contentObj->type === 'output_text' &&
								isset( $contentObj->text ) ) {
								$extractedText .= $contentObj->text;
							}
						}
					}
				}
			}

			if ( $extractedText === '' ) {
				return new WP_Error( 'no_content', 'o3 model completed but no content found' );
			}

			// Create mock response structure for standard processing
			$resArr = (object) array(
				'choices' => array(
					(object) array(
						'message' => (object) array(
							'content' => $extractedText,
						),
					),
				),
			);

			$debug_messages[] = 'o3 content extracted and formatted for standard processing pipeline';
		}

		return $resArr;
	}
}
