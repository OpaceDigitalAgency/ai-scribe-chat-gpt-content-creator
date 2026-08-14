<?php
/**
 * AI Content Service Workflow Handler for AI-Scribe Plugin
 *
 * Handles workflow integration, response building, and HTML generation
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
 * Class AI_Scribe_Content_Service_Workflow
 *
 * Workflow handling extension for Content Service
 */
class AI_Scribe_Content_Service_Workflow {

	/**
	 * Handle article generation fallback for API failures
	 *
	 * @param WP_Error $error API error
	 * @param array $debug_messages Debug messages
	 * @return void Sends JSON response and exits
	 */
	public function handle_article_generation_fallback( $error, $debug_messages ) {
		$debug_messages[] = '🚨 FALLBACK ACTIVATED: Providing emergency content due to API failure';
		$error_message    = $error->get_error_message();

		// Create a styled error message for the WYSIWYG
		$styled_error = '<div style="background: #fef7f7; border: 2px solid #d63638; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;">
            <h3 style="color: #d63638; margin: 0 0 10px 0; font-size: 18px;">⚠️ Article Generation Failed</h3>
            <p style="color: #333; margin: 0; font-size: 14px;">Connection timeout occurred. Please try again.</p>
        </div>';

		// P8 §14.4: the v2.6 popup <script> payload was removed — inline scripts
		// in AJAX HTML are inert under the v3 renderer and violate the
		// no-inline-script rule; the styled error block carries the message.
		$resultArr = array(
			'html'          => '<div class="title-idea after_generate_data"><div class="eval-screen">' . $styled_error . '</div></div>',
			'article'       => 'Article generation failed due to timeout. Please try again.',
			'type'          => 'error',
			'debug'         => $debug_messages,
			'fallback_used' => true,
			'error_message' => $error_message,
		);

		echo json_encode( $resultArr );
		exit();
	}

	/**
	 * Build final response with workflow integration
	 *
	 * @param object $processed_response Processed API response
	 * @param string $actionInput Action type
	 * @param array $messages Original messages
	 * @param array $model_params Model parameters
	 * @param array $debug_messages Debug messages
	 * @return array Final response array
	 */
	public function build_final_response( $processed_response, $actionInput, $messages, $model_params, &$debug_messages ) {
		// Extract combined content from response
		$combinedContent = '';
		if ( isset( $processed_response->choices ) ) {
			foreach ( $processed_response->choices as $choice ) {
				if ( isset( $choice->message->content ) ) {
					$combinedContent .= $choice->message->content;
				}
			}
		}

		// Build HTML structure
		$titleHtml = '<div class="title-idea after_generate_data">';

		if ( in_array( $actionInput, array( 'evaluate', 'article', 'review' ) ) ) {
			$titleHtml .= '<div class="ul1"><div class="eval-screen">';
		} else {
			$titleHtml .= '<ul class="ul1">';
		}

		// Handle early image generation for keyword step
		$parallel_image_js = $this->handle_early_image_generation( $actionInput, $debug_messages );

		// Process content based on action type
		$processed_content = $this->process_content_by_action( $combinedContent, $actionInput, $processed_response );

		// Build HTML content
		if ( in_array( $actionInput, array( 'evaluate', 'article', 'review' ) ) ) {
			$titleHtml .= $processed_content;
			$titleHtml .= '</div></div>';
		} else {
			$titleHtml .= $this->build_list_content( $processed_content, $actionInput );
			$titleHtml .= '</ul>';
		}

		$titleHtml .= '</div>';

		// Build debug information
		$debug_info = $this->build_debug_info( $messages, $model_params, $actionInput );

		// Prepare final result
		$resultArr = array(
			'html'       => $titleHtml,
			'article'    => $combinedContent,
			'type'       => 'success',
			'debug'      => $debug_messages,
			'debug_info' => $debug_info,
		);

		// Add parallel image JavaScript if available
		if ( $parallel_image_js ) {
			$resultArr['parallel_image_js'] = $parallel_image_js;
		}

		return $resultArr;
	}

	/**
	 * Handle early image generation for keyword step
	 *
	 * @param string $actionInput Action type
	 * @param array $debug_messages Debug messages
	 * @return string|null JavaScript code for parallel image generation
	 */
	private function handle_early_image_generation( $actionInput, &$debug_messages ) {
		if ( $actionInput !== 'keyword' ) {
			return null;
		}

		$debug_messages[] = '🎨 Keyword step detected - user has selected title, starting early image generation';

		// Get the user-selected title from POST data
		$title            = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$debug_messages[] = '📝 User-selected title from POST data: ' . $title;

		// Clean up title if we have one
		if ( ! empty( $title ) ) {
			$title            = preg_replace( '/^\d+\.\s*["\']?/', '', $title );
			$title            = preg_replace( '/["\']$/', '', $title );
			$title            = trim( $title );
			$debug_messages[] = '🧹 Title cleaned: ' . $title;
		} else {
			$debug_messages[] = '⚠️ No title found in POST data, cannot generate image';
			return null;
		}

		// Check if image generation is enabled - CONSISTENT with workflow service
		$enable_image_generation = get_option( 'ab_enable_image_generation', 1 );

		// Debug logging to help troubleshoot setting issues
		ai_scribe_debug_log( '🔧 [IMAGE SETTING DEBUG - Content Service] WordPress option ab_enable_image_generation: ' . ( $enable_image_generation ? 'ENABLED' : 'DISABLED' ) );
		ai_scribe_debug_log( '🔧 [IMAGE SETTING DEBUG - Content Service] Title available: ' . ( ! empty( $title ) ? 'YES' : 'NO' ) );
		ai_scribe_debug_log( '🔧 [IMAGE SETTING DEBUG - Content Service] Action: ' . $actionInput );

		if ( ! $enable_image_generation || empty( $title ) ) {
			$debug_messages[] = 'ℹ️ Image generation disabled or no title available for ' . $actionInput;
			ai_scribe_debug_log( '🔧 [IMAGE SETTING DEBUG - Content Service] SKIPPING image generation - setting disabled or no title' );
			return null;
		}

		$debug_messages[] = '⚡ EARLY PARALLEL IMAGE: Attempting background generation after ' . $actionInput;

		// Create image prompt
		$image_style  = get_option( 'ab_image_style', 'Photorealistic' );
		$image_prompt = $title . ' - Create an image based on this title in the style of ' . $image_style . '. You must not include any text, characters, symbols, or writing. Highly detailed and stylised to match the title.';

		// Build JavaScript for parallel processing - DISABLED due to missing AJAX handler
		$javascript = "
            console.log('⚡ EARLY PARALLEL IMAGE: Parallel image generation disabled - missing AJAX handler');
            console.log('📝 Image prompt would have been: " . esc_js( $image_prompt ) . "');
            console.log('ℹ️ Manual image generation available after content completion');
        ";

		$debug_messages[] = '✅ Early parallel image generation JavaScript prepared';
		return $javascript;
	}

	/**
	 * Process content based on action type
	 *
	 * @param string $combinedContent Raw content
	 * @param string $actionInput Action type
	 * @param object $processed_response Full response object
	 * @return string|array Processed content
	 */
	private function process_content_by_action( $combinedContent, $actionInput, $processed_response ) {
		if ( ! isset( $processed_response->choices ) ) {
			return $combinedContent;
		}

		$choicesArr = $processed_response->choices;
		if ( empty( $choicesArr ) ) {
			return $combinedContent;
		}

		foreach ( $choicesArr as $reskey => $resvalue ) {
			$choice          = $processed_response->choices[ $reskey ];
			$combinedContent = '';

			// Get content from choice
			if ( isset( $choice->message->content ) ) {
				$combinedContent .= $choice->message->content;
			}

			// Get text from resvalue if available
			$textRes          = $resvalue->text ?? '';
			$combinedContent .= $textRes;

			// Process based on action type
			switch ( $actionInput ) {
				case 'keyword':
					$combinedContent = str_replace( ',', "\n", $combinedContent );
					$combinedContent = explode( "\n", $combinedContent );
					break;

				case 'heading':
					$combinedContent = str_replace( "\n\n", "\n", $combinedContent );
					$combinedContent = explode( "\n\n", $combinedContent );
					break;

				case 'conclusion':
				case 'qna':
				case 'seo-meta-data':
					$combinedContent = explode( "\n\n", $combinedContent );
					break;

				default:
					// Keep as string for other actions
					break;
			}
		}

		return $combinedContent;
	}

	/**
	 * Build list content for non-evaluation actions
	 *
	 * @param string|array $content Processed content
	 * @param string $actionInput Action type
	 * @return string HTML list content
	 */
	private function build_list_content( $content, $actionInput ) {
		if ( is_string( $content ) ) {
			// Convert string to array for list processing
			$content = explode( "\n", trim( $content ) );
		}

		if ( ! is_array( $content ) ) {
			return '<li>' . esc_html( $content ) . '</li>';
		}

		$html       = '';
		$item_count = 0;

		foreach ( $content as $item ) {
			$item = trim( $item );
			if ( empty( $item ) ) {
				continue;
			}

			++$item_count;

			// Remove numbering if present
			$item = preg_replace( '/^\d+\.\s*/', '', $item );
			$item = trim( $item, '"\'' );

			// Create clickable list item
			$html .= '<li class="title-item" data-action="' . esc_attr( $actionInput ) . '" data-index="' . $item_count . '">';
			$html .= '<span class="title-text">' . esc_html( $item ) . '</span>';
			$html .= '<button class="select-title-btn" data-title="' . esc_attr( $item ) . '">Select</button>';
			$html .= '</li>';
		}

		return $html;
	}

	/**
	 * Build debug information
	 *
	 * @param array $messages Original messages
	 * @param array $model_params Model parameters
	 * @param string $actionInput Action type
	 * @return string Debug HTML
	 */
	private function build_debug_info( $messages, $model_params, $actionInput ) {
		$message_str = '';
		if ( $messages ) {
			foreach ( $messages as $message ) {
				$message_str .= 'Role: ' . $message['role'] . '<br/>' .
								'Content: ' . $message['content'] . '<br/>';
			}
		}

		$debug = '<br/>actionInput: ' . $actionInput .
				'<br/>max_tokens: ' . $model_params['max_tokens'] .
				'<br/>MESSAGE: ' . $message_str .
				'<br/> $temp: ' . $model_params['temperature'] .
				'<br/> $top_p: ' . $model_params['top_p'] .
				'<br/> $freq_pent: ' . $model_params['frequency_penalty'] .
				'<br/> $presence_penalty: ' . $model_params['presence_penalty'] .
				'<br/>';

		return $debug;
	}
}
