<?php
/**
 * Conversation AJAX Controller for AI-Scribe Plugin
 *
 * The v3 endpoint surface for the new UI (docs/API_CONTRACT.md is the
 * binding contract). Every endpoint: nonce + capability check, responses
 * via wp_send_json_* only — the SSE stream endpoint is the single
 * documented exception (text/event-stream).
 *
 * @package AI_Scribe
 * @subpackage Ajax
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Scribe_Conversation_Ajax_Controller {

	const NONCE_ACTION = 'ai_scribe_nonce';
	const CAPABILITY   = 'edit_posts';

	/** @var AI_Scribe_Logger|null */
	private $logger;

	/** @var AI_Scribe_Conversation_Service */
	private $conversations;

	/** @var AI_Scribe_Generation_Service */
	private $generation;

	/** @var AI_Scribe_Cost_Estimator */
	private $estimator;

	/** @var AI_Scribe_Prompt_Manager */
	private $prompts;

	/** @var AI_Scribe_Post_Service */
	private $posts;

	public function __construct( $logger, $conversations, $generation, $estimator, $prompts, $posts ) {
		$this->logger        = $logger;
		$this->conversations = $conversations;
		$this->generation    = $generation;
		$this->estimator     = $estimator;
		$this->prompts       = $prompts;
		$this->posts         = $posts;
		$this->register();
	}

	private function register() {
		$actions = array(
			'ai_scribe_start_conversation' => 'handle_start_conversation',
			'ai_scribe_run_step'           => 'handle_run_step',
			'ai_scribe_run_express'        => 'handle_run_express',
			'ai_scribe_improve_article_length' => 'handle_improve_article_length',
			'ai_scribe_optimise_meta'       => 'handle_optimise_meta',
			'ai_scribe_get_state'          => 'handle_get_state',
			'ai_scribe_save_selection'     => 'handle_save_selection',
			'ai_scribe_estimate_cost'      => 'handle_estimate_cost',
			'ai_scribe_save_post'          => 'handle_save_post',
			'ai_scribe_stream_step'        => 'handle_stream_step',
		);
		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
			// No nopriv registration: generation requires an authenticated
			// user with edit_posts.
		}
	}

	/**
	 * Nonce + capability check, as an error payload rather than a response, so
	 * both the JSON endpoints and the SSE endpoint report the same reason. A
	 * capability failure must never be dressed up as a nonce failure.
	 *
	 * @return array|null Error payload, or null when the request may proceed.
	 */
	private function guard_failure() {
		$nonce = isset( $_REQUEST['security'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['security'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return array(
				'code'      => 'invalid_nonce',
				'message'   => 'Security nonce is missing or invalid. Please refresh the page.',
				'retryable' => false,
			);
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return array(
				'code'      => 'insufficient_permissions',
				'message'   => 'You do not have permission to generate content.',
				'retryable' => false,
			);
		}
		return null;
	}

	/**
	 * Shared guard: nonce + capability. Sends the JSON error itself and returns
	 * false when the request must stop.
	 *
	 * @return bool
	 */
	private function guard() {
		$failure = $this->guard_failure();
		if ( null === $failure ) {
			return true;
		}
		wp_send_json_error( $failure );
		return false;
	}

	/**
	 * Refuse an id that is absent or belongs to another user. The response is
	 * deliberately identical in both cases so conversation ids cannot be used
	 * to enumerate another author's work.
	 *
	 * @param int $conversation_id Conversation id supplied by the client.
	 * @return array|null Owned conversation, or null after sending an error.
	 */
	private function owned_conversation( $conversation_id ) {
		$conversation = $conversation_id ? $this->conversations->get( $conversation_id ) : null;
		if ( $conversation ) {
			return $conversation;
		}
		wp_send_json_error(
			array(
				'code'      => 'conversation_not_found',
				'message'   => 'Conversation not found.',
				'retryable' => false,
			)
		);
		return null;
	}

	/**
	 * Conversation defaults from the GLOBAL settings screens (2.6.2 parity:
	 * language/style/tone/heading/keywords-to-avoid saved in Settings apply
	 * to every article; request fields override per-conversation).
	 *
	 * @return array Partial settings map.
	 */
	private function settings_defaults_from_options() {
		$content = get_option( 'ab_gpt_content_settings', array() );
		$content = is_array( $content ) ? $content : array();
		$engine  = get_option( 'ab_gpt_ai_engine_settings', array() );
		$engine  = is_array( $engine ) ? $engine : array();

		$defaults = array();
		foreach ( array( 'language', 'writing_style', 'writing_tone' ) as $field ) {
			if ( ! empty( $content[ $field ] ) && is_string( $content[ $field ] ) ) {
				$defaults[ $field ] = $content[ $field ];
			}
		}
		// Legacy capitalisation/singular quirks preserved (2.6.2 option keys).
		$heading_tag = $content['heading_tag'] ?? $content['Heading_tag'] ?? '';
		if ( $heading_tag !== '' ) {
			$defaults['heading_tag'] = (string) $heading_tag;
		}
		$no_headings = $content['number_of_headings'] ?? $content['number_of_heading'] ?? '';
		if ( $no_headings !== '' ) {
			$defaults['number_of_headings'] = (int) $no_headings;
		}
		$length_mode = isset( $content['article_length_mode'] ) ? sanitize_key( $content['article_length_mode'] ) : 'auto';
		$defaults['article_length_mode'] = in_array( $length_mode, AI_Scribe_Article_Plan_Service::MODES, true ) ? $length_mode : 'auto';
		$defaults['article_word_count']  = isset( $content['article_word_count'] ) ? max( 400, min( 8000, (int) $content['article_word_count'] ) ) : 1800;
		$checks = isset( $content['check_Arr'] ) && is_array( $content['check_Arr'] ) ? $content['check_Arr'] : array();
		$defaults['qna_enabled'] = isset( $checks['addQNA'] ) && 'addQNA' === $checks['addQNA'];
		$defaults['quality_gate_enabled'] = true;
		$avoid = $content['avoid_keywords'] ?? $content['cs_list'] ?? '';
		if ( is_string( $avoid ) && $avoid !== '' ) {
			// U-01: a cs_list carried over from 2.6.2 arrives with compounded
			// backslash escaping; normalise on read so O\'Brien never reaches
			// a prompt (the stored option is left as-is).
			$defaults['avoid_keywords'] = AI_Scribe_Prompt_Manager::normalise_stored_text( $avoid );
		}
		if ( ! empty( $engine['model'] ) && is_string( $engine['model'] ) ) {
			$defaults['model'] = $engine['model'];
		}
		return $defaults;
	}

	// This helper is invoked only after the public endpoint has called guard().
	// phpcs:disable WordPress.Security.NonceVerification
	private function read_settings_from_request() {
		$settings      = array();
		$string_fields = array(
			'idea',
			'language',
			'writing_style',
			'writing_tone',
			'heading_tag',
			'tagline_position',
			'avoid_keywords',
			'model',
			'article_length_mode',
		);
		foreach ( $string_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$settings[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			}
		}
		if ( isset( $_POST['number_of_headings'] ) ) {
			$settings['number_of_headings'] = (int) $_POST['number_of_headings'];
		}
		if ( isset( $_POST['article_word_count'] ) ) {
			$settings['article_word_count'] = max( 400, min( 8000, (int) $_POST['article_word_count'] ) );
		}
		if ( isset( $_POST['options'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded here and every value is sanitised immediately below.
			$options = json_decode( wp_unslash( (string) $_POST['options'] ), true );
			if ( is_array( $options ) ) {
				$settings['options'] = array_map( 'sanitize_text_field', $options );
			}
		}
		return $settings;
	}
	// phpcs:enable WordPress.Security.NonceVerification

	private function send_generation_result( array $result ) {
		if ( ! empty( $result['success'] ) ) {
			unset( $result['success'] );
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['error'] );
		}
	}

	// ------------------------------------------------------------------
	// Endpoints
	// ------------------------------------------------------------------
	// PHPCS cannot follow guard() into each endpoint. Every JSON endpoint
	// calls guard() before reading request data; SSE calls guard_failure().
	// phpcs:disable WordPress.Security.NonceVerification

	/** Contract §1. */
	public function handle_start_conversation() {
		if ( ! $this->guard() ) {
			return;
		}
		$settings        = array_merge( $this->settings_defaults_from_options(), $this->read_settings_from_request() );
		$conversation_id = $this->conversations->create( $settings, 'wizard' );
		wp_send_json_success(
			array(
				'conversation_id' => (int) $conversation_id,
				'state'           => $this->conversations->get_state( $conversation_id ),
			)
		);
	}

	/** Contract §2. */
	public function handle_run_step() {
		if ( ! $this->guard() ) {
			return;
		}
		$conversation_id = isset( $_POST['conversation_id'] ) ? (int) $_POST['conversation_id'] : 0;
		$step            = isset( $_POST['step'] ) ? (int) $_POST['step'] : 0;
		if ( ! $conversation_id || $step < 1 || $step > 11 ) {
			wp_send_json_error(
				array(
					'code'      => 'invalid_params',
					'message'   => 'conversation_id and step (1-11) are required.',
					'retryable' => false,
				)
			);
			return;
		}
		if ( ! $this->owned_conversation( $conversation_id ) ) {
			return;
		}

		$args = array(
			'regenerate' => ! empty( $_POST['regenerate'] ),
		);
		if ( ! empty( $_POST['prompt_override'] ) ) {
			// Per-run prompt override: sanitised server-side; placeholders
			// are still resolved by the assembler.
			$args['prompt_override'] = sanitize_textarea_field( wp_unslash( $_POST['prompt_override'] ) );
		}
		if ( ! empty( $_POST['model'] ) ) {
			$args['model'] = sanitize_text_field( wp_unslash( $_POST['model'] ) );
		}
		if ( ! empty( $_POST['skip_tagline'] ) ) {
			$args['skip_tagline'] = true;
		}
		if ( ! empty( $_POST['skip_keywords'] ) ) {
			$args['skip_keywords'] = true;
		}
		if ( 11 === $step && isset( $_POST['content_html'] ) ) {
			$args['content_html'] = wp_kses_post( wp_unslash( $_POST['content_html'] ) );
		}

		$this->send_generation_result( $this->generation->run_step( $conversation_id, $step, $args ) );
	}

	/** Contract §5. */
	public function handle_run_express() {
		if ( ! $this->guard() ) {
			return;
		}
		$conversation_id = isset( $_POST['conversation_id'] ) ? (int) $_POST['conversation_id'] : 0;
		if ( ! $conversation_id ) {
			$settings = array_merge( $this->settings_defaults_from_options(), $this->read_settings_from_request() );
			if ( empty( $settings['idea'] ) ) {
				wp_send_json_error(
					array(
						'code'      => 'invalid_params',
						'message'   => 'An article idea is required.',
						'retryable' => false,
					)
				);
				return;
			}
			$conversation_id = $this->conversations->create( $settings, 'express' );
		} elseif ( ! $this->owned_conversation( $conversation_id ) ) {
			return;
		}
		$this->send_generation_result( $this->generation->run_express( $conversation_id ) );
	}

	/** User-triggered, one-call expansion of the current persisted article. */
	public function handle_improve_article_length() {
		if ( ! $this->guard() ) {
			return;
		}
		$conversation_id = isset( $_POST['conversation_id'] ) ? (int) $_POST['conversation_id'] : 0;
		if ( ! $conversation_id ) {
			wp_send_json_error( array( 'code' => 'invalid_params', 'message' => 'conversation_id is required.', 'retryable' => false ) );
			return;
		}
		if ( ! $this->owned_conversation( $conversation_id ) ) {
			return;
		}
		$current_html = isset( $_POST['current_html'] ) ? wp_kses_post( wp_unslash( (string) $_POST['current_html'] ) ) : '';
		$body_only    = ! empty( $_POST['body_only'] );
		$this->send_generation_result( $this->generation->improve_article_length( $conversation_id, $current_html, $body_only ) );
	}

	/** Optional, user-triggered metadata shortening. Never runs automatically. */
	public function handle_optimise_meta() {
		if ( ! $this->guard() ) {
			return;
		}
		$conversation_id = isset( $_POST['conversation_id'] ) ? (int) $_POST['conversation_id'] : 0;
		$title           = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$description     = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		if ( ! $conversation_id || '' === $title || '' === $description ) {
			wp_send_json_error( array( 'code' => 'invalid_params', 'message' => 'Conversation, title and description are required.', 'retryable' => false ) );
			return;
		}
		if ( ! $this->owned_conversation( $conversation_id ) ) {
			return;
		}
		$this->send_generation_result( $this->generation->optimise_meta( $conversation_id, $title, $description ) );
	}

	/** Contract §4. */
	public function handle_get_state() {
		if ( ! $this->guard() ) {
			return;
		}
		$conversation_id = isset( $_REQUEST['conversation_id'] ) ? (int) $_REQUEST['conversation_id'] : 0;
		if ( ! $this->owned_conversation( $conversation_id ) ) {
			return;
		}
		$state = $this->conversations->get_state( $conversation_id );
		wp_send_json_success( $state );
	}

	/** Contract §3. */
	public function handle_save_selection() {
		if ( ! $this->guard() ) {
			return;
		}
		$conversation_id = isset( $_POST['conversation_id'] ) ? (int) $_POST['conversation_id'] : 0;
		$key             = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The value is sanitised by sanitize_deep() or wp_kses_post() before storage.
		$raw_value       = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';

		if ( $conversation_id && ! $this->owned_conversation( $conversation_id ) ) {
			return;
		}

		// Tagline placement is a conversation SETTING, not a selection —
		// the step-5 above/below radios persist through this same endpoint.
		if ( 'tagline_position' === $key && $conversation_id ) {
			$position = ( 'above' === $raw_value ) ? 'above' : 'below';
			if ( ! $this->conversations->update_settings( $conversation_id, array( 'tagline_position' => $position ) ) ) {
				wp_send_json_error(
					array(
						'code'      => 'conversation_not_found',
						'message'   => 'Conversation not found.',
						'retryable' => false,
					)
				);
				return;
			}
			wp_send_json_success(
				array(
					'conversation_id' => $conversation_id,
					'state'           => $this->conversations->get_state( $conversation_id ),
				)
			);
			return;
		}

		if ( ! $conversation_id || ! in_array( $key, AI_Scribe_Conversation_Service::SELECTION_KEYS, true ) ) {
			wp_send_json_error(
				array(
					'code'      => 'invalid_params',
					'message'   => 'conversation_id and a valid selection key are required.',
					'retryable' => false,
				)
			);
			return;
		}

		// Array/object keys accept JSON; everything is sanitised as post-safe HTML.
		$decoded = json_decode( (string) $raw_value, true );
		if ( in_array( $key, array( 'keywords', 'outline', 'qna', 'meta' ), true ) && is_array( $decoded ) ) {
			$value = $this->sanitize_deep( $decoded );
		} else {
			$value = wp_kses_post( (string) $raw_value );
		}

		if ( ! $this->conversations->save_selection( $conversation_id, $key, $value ) ) {
			wp_send_json_error(
				array(
					'code'      => 'conversation_not_found',
					'message'   => 'Conversation not found.',
					'retryable' => false,
				)
			);
			return;
		}
		wp_send_json_success(
			array(
				'conversation_id' => $conversation_id,
				'state'           => $this->conversations->get_state( $conversation_id ),
			)
		);
	}

	/** Contract §6. */
	public function handle_estimate_cost() {
		if ( ! $this->guard() ) {
			return;
		}
		$conversation_id = isset( $_POST['conversation_id'] ) ? (int) $_POST['conversation_id'] : 0;
		$step            = isset( $_POST['step'] ) ? (int) $_POST['step'] : 0;
		$mode            = ( isset( $_POST['mode'] ) && $_POST['mode'] === 'express' ) ? 'express' : 'wizard';
		$model           = ! empty( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';

		$conversation = null;
		if ( $conversation_id ) {
			$conversation = $this->owned_conversation( $conversation_id );
			if ( ! $conversation ) {
				return;
			}
		}
		if ( $model === '' && $conversation && ! empty( $conversation['settings']['model'] ) ) {
			$model = $conversation['settings']['model'];
		}
		if ( $model === '' ) {
			$model = (string) ai_scribe_get_container()->get( 'config' )->get( 'ai_engine.model', 'gpt-4o-mini' );
		}

		// Base prompt size: system prompt + current thread if we have one.
		$base_tokens = $this->estimator->estimate_tokens( $this->prompts->get_system_prompt() ) + 200;
		if ( $conversation ) {
			foreach ( $conversation['messages'] as $message ) {
				$base_tokens += $this->estimator->estimate_tokens( is_string( $message['content'] ) ? $message['content'] : wp_json_encode( $message['content'] ) );
			}
		}

		if ( $step >= 1 && $step <= 11 ) {
			$estimate       = $this->estimator->estimate_step( $model, $step, $base_tokens, true );
			$prompt_preview = '';
			if ( $conversation ) {
				$prompt_preview = $this->prompts->assemble_step_prompt(
					$step,
					$conversation['settings'],
					$conversation['selections']
				);
			}
			wp_send_json_success(
				array(
					'model'          => $model,
					'pricing'        => $this->estimator->estimate_article( $model, 'wizard', $base_tokens )['pricing'],
					'steps'          => array(
						(string) $step => array(
							'input_tokens'  => $estimate['input_tokens'],
							'output_tokens' => $estimate['output_tokens'],
							'usd'           => $estimate['usd'],
						),
					),
					'total'          => array(
						'input_tokens'        => $estimate['input_tokens'],
						'output_tokens'       => $estimate['output_tokens'],
						'usd'                 => $estimate['usd'],
						'usd_without_caching' => $estimate['usd_without_caching'],
						'cache_savings_usd'   => round( $estimate['usd_without_caching'] - $estimate['usd'], 6 ),
					),
					'prompt_preview' => $prompt_preview,
				)
			);
			return;
		}

		wp_send_json_success( $this->estimator->estimate_article( $model, $mode, $base_tokens ) );
	}

	/** Contract §7. */
	public function handle_save_post() {
		if ( ! $this->guard() ) {
			return;
		}
		$conversation_id = isset( $_POST['conversation_id'] ) ? (int) $_POST['conversation_id'] : 0;
		$conversation    = $this->owned_conversation( $conversation_id );
		if ( ! $conversation ) {
			return;
		}

		$args = array(
			'post_status' => isset( $_POST['post_status'] ) ? sanitize_key( wp_unslash( $_POST['post_status'] ) ) : 'draft',
			'post_type'   => isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'post',
			'article_settings' => $conversation['settings'],
		);
		if ( ! empty( $_POST['content_html'] ) ) {
			$args['content_html'] = wp_kses_post( wp_unslash( $_POST['content_html'] ) );
		}
		$args['category_name'] = isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
		$args['tag_names']     = isset( $_POST['tag_names'] ) ? sanitize_text_field( wp_unslash( $_POST['tag_names'] ) ) : '';
		// Optional explicit featured image; otherwise PostService promotes the
		// first image found in the article body.
		if ( ! empty( $_POST['featured_attachment_id'] ) ) {
			$args['featured_attachment_id'] = (int) $_POST['featured_attachment_id'];
		}
		// A conversation already saved once carries the created post's id, so
		// a second save (e.g. Publish after Save as Draft) updates that post
		// in place instead of duplicating it.
		if ( ! empty( $conversation['settings']['post_id'] ) ) {
			$args['existing_post_id'] = (int) $conversation['settings']['post_id'];
		}

		$result = $this->posts->create_from_conversation( $conversation['selections'], $args );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'code'      => 'save_failed',
					'message'   => $result->get_error_message(),
					'retryable' => false,
				)
			);
			return;
		}

		// Remember which post this conversation produced so later saves reuse it.
		if ( ! empty( $result['post_id'] ) ) {
			$this->conversations->update_settings(
				$conversation_id,
				array(
					'post_id'        => (int) $result['post_id'],
					'post_status'    => $args['post_status'],
					'post_html'      => isset( $args['content_html'] ) ? $args['content_html'] : '',
					'post_edit_link' => isset( $result['edit_link'] ) ? (string) $result['edit_link'] : '',
				)
			);
		}
		$this->conversations->set_status( $conversation_id, 'complete' );
		wp_send_json_success( $result );
	}

	/** Contract §8 — SSE. The single non-JSON endpoint. */
	public function handle_stream_step() {
		$failure = $this->guard_failure();
		if ( null !== $failure ) {
			// SSE clients get a terminal error frame carrying the real reason,
			// not a blanket nonce failure.
			$this->sse_headers();
			$this->sse_emit( 'error', $failure );
			exit;
		}

		$conversation_id = isset( $_REQUEST['conversation_id'] ) ? (int) $_REQUEST['conversation_id'] : 0;
		$step            = isset( $_REQUEST['step'] ) ? (int) $_REQUEST['step'] : 0;
		if ( ! $conversation_id || ! $this->conversations->get( $conversation_id ) ) {
			$this->sse_headers();
			$this->sse_emit(
				'error',
				array(
					'code'      => 'conversation_not_found',
					'message'   => 'Conversation not found.',
					'retryable' => false,
				)
			);
			exit;
		}
		$args            = array();
		if ( ! empty( $_REQUEST['prompt_override'] ) ) {
			$args['prompt_override'] = sanitize_textarea_field( wp_unslash( $_REQUEST['prompt_override'] ) );
		}
		if ( ! empty( $_REQUEST['model'] ) ) {
			$args['model'] = sanitize_text_field( wp_unslash( $_REQUEST['model'] ) );
		}
		if ( 11 === $step && isset( $_REQUEST['content_html'] ) ) {
			$args['content_html'] = wp_kses_post( wp_unslash( $_REQUEST['content_html'] ) );
		}

		$this->sse_headers();
		$controller = $this;
		$this->generation->stream_step(
			$conversation_id,
			$step,
			$args,
			function ( $event, $data ) use ( $controller ) {
				$controller->sse_emit( $event, $data );
			}
		);
		exit;
	}
	// phpcs:enable WordPress.Security.NonceVerification

	// ------------------------------------------------------------------
	// SSE helpers
	// ------------------------------------------------------------------

	public function sse_headers() {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/event-stream; charset=utf-8' );
			header( 'Cache-Control: no-cache' );
			header( 'X-Accel-Buffering: no' );
		}
		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
	}

	public function sse_emit( $event, array $data ) {
		echo 'event: ' . esc_html( $event ) . "\n";
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";
		if ( function_exists( 'flush' ) ) {
			flush();
		}
	}

	private function sanitize_deep( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ is_string( $k ) ? sanitize_key( $k ) : $k ] = $this->sanitize_deep( $v );
			}
			return $out;
		}
		return is_string( $value ) ? wp_kses_post( $value ) : $value;
	}
}
