<?php
/**
 * Plugin Name: AI-Scribe Mock AI Provider
 * Description: Intercepts outbound HTTP to AI provider APIs and returns schema-valid canned responses. Zero API spend for E2E tests.
 * Version: 1.0.0
 * Author: Opace Digital Agency
 *
 * Activation: both AI_SCRIBE_MOCK and AI_SCRIBE_AUTOMATED_TEST must be true.
 * The second, test-only guard prevents an owner/development install from ever
 * serving fixtures even if an old AI_SCRIBE_MOCK constant is left behind.
 *
 * Failure modes (pick one):
 *   - Request param:  &ai_scribe_mock_mode=http_500   (any admin-ajax/REST request carrying the param)
 *   - Option:         wp option update ai_scribe_mock_mode rate_limit_429
 *   - Constant:       define( 'AI_SCRIBE_MOCK_MODE', 'slow' );
 * Modes: ok (default) | empty_choices | http_500 | rate_limit_429 | slow | malformed_json
 * Reset: wp option delete ai_scribe_mock_mode
 *
 * LIVE MODE — real providers, real spend — is separate from the modes above
 * and is deliberately NOT selectable by request parameter, constant or
 * option, because none of those is capability-checked. It is scoped to a
 * single browser via a cookie set by an administrator:
 *
 *     /wp-admin/admin.php?page=ai-scribe&ai_scribe_live=1   # on  (this browser)
 *     /wp-admin/admin.php?page=ai-scribe&ai_scribe_live=0   # off
 *
 * Cookie-less callers — the Playwright suite, wp-cli, cron — therefore stay
 * mocked no matter what, and cannot be pushed live by a stray URL or option.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'AI_SCRIBE_MOCK' ) || ! AI_SCRIBE_MOCK
	|| ! defined( 'AI_SCRIBE_AUTOMATED_TEST' ) || ! AI_SCRIBE_AUTOMATED_TEST ) {
	return;
}

final class AI_Scribe_Mock_Provider {

	const MOCK_HOSTS = array(
		'api.openai.com',
		'api.anthropic.com',
		'generativelanguage.googleapis.com',
		'api.x.ai',
	);

	/**
	 * Canned-response modes. Every one of these is free.
	 *
	 * 'live' is deliberately NOT a member. MODES is the whitelist consulted
	 * for $_REQUEST, the AI_SCRIBE_MOCK_MODE constant and the site-wide
	 * option — none of which is capability-checked — so listing 'live' here
	 * turned ?ai_scribe_mock_mode=live into an unauthenticated switch that
	 * spends real money on the configured keys. Live mode is reachable only
	 * through the capability-checked cookie below.
	 */
	const MODES = array( 'ok', 'empty_choices', 'http_500', 'rate_limit_429', 'slow', 'malformed_json' );

	/** Cookie that puts a single browser into live mode. */
	const LIVE_COOKIE = 'ai_scribe_live';

	public static function init() {
		add_filter( 'pre_http_request', array( __CLASS__, 'intercept' ), 5, 3 );
		add_action( 'admin_init', array( __CLASS__, 'handle_live_toggle' ) );
		add_action( 'admin_notices', array( __CLASS__, 'live_notice' ) );
	}

	/**
	 * Toggle live mode for THIS BROWSER via ?ai_scribe_live=1|0.
	 *
	 * Scoped to a cookie rather than the site-wide option so a human can
	 * exercise real generations while automated suites keep running against
	 * the same database: Playwright opens fresh contexts and wp-cli sends no
	 * cookies, so neither can inherit it.
	 */
	public static function handle_live_toggle() {
		if ( ! isset( $_GET['ai_scribe_live'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$on = '1' === sanitize_key( wp_unslash( $_GET['ai_scribe_live'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		setcookie( self::LIVE_COOKIE, $on ? '1' : '', $on ? time() + DAY_IN_SECONDS : time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN );
		$_COOKIE[ self::LIVE_COOKIE ] = $on ? '1' : '';

		wp_safe_redirect( remove_query_arg( 'ai_scribe_live' ) );
		exit;
	}

	/** Live mode spends real money, so it must never be silent. */
	public static function live_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! self::live_cookie_set() ) {
			printf(
				'<div class="notice notice-info"><p><strong>AI-Scribe test mode is active.</strong> Generated text and images are fixed mock fixtures, not responses from the model named in the interface. No provider charges apply. <a href="%s">Use live API for this browser</a>.</p></div>',
				esc_url( add_query_arg( 'ai_scribe_live', '1' ) )
			);
			return;
		}
		printf(
			'<div class="notice notice-warning"><p><strong>AI-Scribe: live API mode is on for this browser.</strong> Generations call your real providers and bill your account. <a href="%s">Switch back to mock data</a>.</p></div>',
			esc_url( add_query_arg( 'ai_scribe_live', '0' ) )
		);
	}

	private static function live_cookie_set() {
		return ! empty( $_COOKIE[ self::LIVE_COOKIE ] );
	}

	/**
	 * @param false|array|WP_Error $preempt Whether to preempt the request.
	 * @param array                $args    HTTP request arguments.
	 * @param string               $url     The request URL.
	 * @return false|array|WP_Error
	 */
	public static function intercept( $preempt, $args, $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! in_array( $host, self::MOCK_HOSTS, true ) ) {
			return $preempt;
		}

		$mode = self::mode();

		// Stand down and let the real provider answer. Returning $preempt
		// unchanged (false) leaves the HTTP stack to proceed normally.
		if ( 'live' === $mode ) {
			return $preempt;
		}

		if ( 'slow' === $mode ) {
			sleep( 5 );
			$mode = 'ok';
		}

		if ( 'http_500' === $mode ) {
			return self::response( 500, wp_json_encode( array(
				'error' => array(
					'message' => 'Mock internal server error (ai-scribe-mock-provider).',
					'type'    => 'server_error',
					'code'    => 'internal_error',
				),
			) ) );
		}

		if ( 'rate_limit_429' === $mode ) {
			return self::response( 429, wp_json_encode( array(
				'error' => array(
					'message' => 'Mock rate limit exceeded. Retry after 20 seconds.',
					'type'    => 'rate_limit_error',
					'code'    => 'rate_limit_exceeded',
				),
			) ), array( 'retry-after' => '20' ) );
		}

		if ( 'malformed_json' === $mode ) {
			return self::response( 200, '{"choices":[{"message":{"content":"truncated…' );
		}

		$path   = (string) wp_parse_url( $url, PHP_URL_PATH );
		$method = isset( $args['method'] ) ? strtoupper( $args['method'] ) : 'GET';
		$empty  = ( 'empty_choices' === $mode );

		switch ( $host ) {
			case 'api.openai.com':
				return self::openai( $path, $method, $args, $empty );
			case 'api.x.ai':
				return self::openai( $path, $method, $args, $empty, 'grok-4.3' );
			case 'api.anthropic.com':
				return self::anthropic( $path, $method, $args, $empty );
			case 'generativelanguage.googleapis.com':
				return self::gemini( $path, $method, $args, $empty );
		}

		return $preempt;
	}

	private static function mode() {
		// Highest precedence of all: the per-browser live cookie. It outranks
		// the site-wide option deliberately — one person can test real output
		// while automated runs against the same database stay mocked.
		if ( self::live_cookie_set() ) {
			return 'live';
		}

		// Explicit request parameter (lets Playwright flip modes per request).
		if ( isset( $_REQUEST['ai_scribe_mock_mode'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$m = sanitize_key( wp_unslash( $_REQUEST['ai_scribe_mock_mode'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( in_array( $m, self::MODES, true ) ) {
				return $m;
			}
		}
		if ( defined( 'AI_SCRIBE_MOCK_MODE' ) && in_array( AI_SCRIBE_MOCK_MODE, self::MODES, true ) ) {
			return AI_SCRIBE_MOCK_MODE;
		}
		$opt = get_option( 'ai_scribe_mock_mode', 'ok' );
		return in_array( $opt, self::MODES, true ) ? $opt : 'ok';
	}

	/* ---------------------------------------------------------------- OpenAI / xAI (OpenAI-compatible) */

	private static function openai( $path, $method, $args, $empty, $default_model = 'gpt-5-mini' ) {
		// Image generation (gpt-image-1 / GPT Image 2 / DALL-E): return a
		// tiny valid PNG as b64_json so the media-library save path works.
		if ( false !== strpos( $path, '/images/' ) ) {
			return self::response( 200, wp_json_encode( array(
				'created' => time(),
				'data'    => $empty ? array() : array(
					array( 'b64_json' => self::tiny_png_base64() ),
				),
				'usage'   => array(
					'input_tokens'  => 24,
					'output_tokens' => 1024,
					'total_tokens'  => 1048,
				),
			) ) );
		}

		if ( 'GET' === $method && false !== strpos( $path, '/models' ) ) {
			// Real live-family ids (P6 live smoke; §13 addendum: the mock must
			// mirror the REAL /models surface — no speculative gpt-5.6 names).
			$ids = 'grok-4.3' === $default_model
				? array( 'grok-4.20', 'grok-4.3' )
				: array( 'gpt-5', 'gpt-5-mini', 'gpt-5-nano', 'o4-mini', 'gpt-4.1-mini', 'gpt-image-1' );
			$data = array();
			foreach ( $ids as $id ) {
				$data[] = array(
					'id'       => $id,
					'object'   => 'model',
					'created'  => 1751932800,
					'owned_by' => 'grok-4.3' === $default_model ? 'xai' : 'openai',
				);
			}
			return self::response( 200, wp_json_encode( array( 'object' => 'list', 'data' => $data ) ) );
		}

		$body    = self::request_body( $args );
		$model   = isset( $body['model'] ) ? $body['model'] : $default_model;
		$content = self::step_content( $body );

		// Responses API (OpenAI primary surface).
		if ( false !== strpos( $path, '/responses' ) ) {
			$payload = array(
				'id'     => 'resp_' . self::uid(),
				'object' => 'response',
				'status' => 'completed',
				'model'  => $model,
				'output' => $empty ? array() : array(
					array(
						'type'    => 'message',
						'id'      => 'msg_' . self::uid(),
						'role'    => 'assistant',
						'content' => array(
							array( 'type' => 'output_text', 'text' => $content ),
						),
					),
				),
				'usage'  => array(
					'input_tokens'  => 812,
					'output_tokens' => 407,
					'total_tokens'  => 1219,
				),
			);
			return self::response( 200, wp_json_encode( $payload ) );
		}

		// Chat Completions (OpenAI legacy surface + xAI).
		$payload = array(
			'id'      => 'chatcmpl-' . self::uid(),
			'object'  => 'chat.completion',
			'created' => time(),
			'model'   => $model,
			'choices' => $empty ? array() : array(
				array(
					'index'         => 0,
					'message'       => array(
						'role'    => 'assistant',
						'content' => $content,
					),
					'finish_reason' => 'stop',
				),
			),
			'usage'   => array(
				'prompt_tokens'     => 812,
				'completion_tokens' => 407,
				'total_tokens'      => 1219,
			),
		);
		return self::response( 200, wp_json_encode( $payload ) );
	}

	/* ---------------------------------------------------------------- Anthropic */

	private static function anthropic( $path, $method, $args, $empty ) {
		if ( 'GET' === $method && false !== strpos( $path, '/models' ) ) {
			$models = array();
			foreach ( array(
				array( 'claude-sonnet-4-5-20250929', 'Claude Sonnet 4.5' ),
				array( 'claude-opus-4-1-20250805', 'Claude Opus 4.1' ),
				array( 'claude-3-7-sonnet-20250219', 'Claude Sonnet 3.7' ),
				array( 'claude-3-5-haiku-20241022', 'Claude Haiku 3.5' ),
			) as $m ) {
				$models[] = array(
					'id'           => $m[0],
					'type'         => 'model',
					'display_name' => $m[1],
					'created_at'   => '2026-06-30T00:00:00Z',
				);
			}
			return self::response( 200, wp_json_encode( array(
				'data'     => $models,
				'has_more' => false,
				'first_id' => $models[0]['id'],
				'last_id'  => $models[ count( $models ) - 1 ]['id'],
			) ) );
		}

		$body    = self::request_body( $args );
		$model   = isset( $body['model'] ) ? $body['model'] : 'claude-sonnet-4-5-20250929';
		$content = self::step_content( $body );

		$payload = array(
			'id'            => 'msg_' . self::uid(),
			'type'          => 'message',
			'role'          => 'assistant',
			'model'         => $model,
			'content'       => $empty ? array() : array(
				array( 'type' => 'text', 'text' => $content ),
			),
			'stop_reason'   => $empty ? null : 'end_turn',
			'stop_sequence' => null,
			'usage'         => array(
				'input_tokens'                => 812,
				'output_tokens'               => 407,
				'cache_creation_input_tokens' => 0,
				'cache_read_input_tokens'     => 512,
			),
		);
		return self::response( 200, wp_json_encode( $payload ) );
	}

	/* ---------------------------------------------------------------- Google Gemini */

	private static function gemini( $path, $method, $args, $empty ) {
		if ( 'GET' === $method && preg_match( '#/models/?$#', $path ) ) {
			$models = array();
			foreach ( array( 'gemini-3.6-flash', 'gemini-3.5-pro' ) as $id ) {
				$models[] = array(
					'name'                       => 'models/' . $id,
					'displayName'                => ucwords( str_replace( '-', ' ', $id ) ),
					'supportedGenerationMethods' => array( 'generateContent', 'streamGenerateContent' ),
					'inputTokenLimit'            => 1048576,
					'outputTokenLimit'           => 65536,
				);
			}
			return self::response( 200, wp_json_encode( array( 'models' => $models ) ) );
		}

		$body = self::request_body( $args );
		// Gemini image generation uses a single prompt-only generateContent
		// request with no systemInstruction. Return the inlineData shape the
		// real GeminiImageProvider consumes so Image Studio E2E stays zero-cost.
		$is_image_request = 'POST' === $method
			&& false !== strpos( $path, ':generateContent' )
			&& empty( $body['systemInstruction'] )
			&& isset( $body['contents'] )
			&& 1 === count( (array) $body['contents'] )
			&& empty( $body['generationConfig']['responseSchema'] );
		if ( $is_image_request ) {
			return self::response(
				200,
				wp_json_encode(
					array(
						'candidates' => $empty ? array() : array(
							array(
								'content' => array(
									'parts' => array(
										array(
											'inlineData' => array(
												'mimeType' => 'image/png',
												'data'     => self::tiny_png_base64(),
											),
										),
									),
								),
							),
						),
					)
				)
			);
		}
		$content = self::step_content( $body );

		$payload = array(
			'candidates'    => $empty ? array() : array(
				array(
					'content'      => array(
						'parts' => array( array( 'text' => $content ) ),
						'role'  => 'model',
					),
					'finishReason' => 'STOP',
					'index'        => 0,
				),
			),
			'usageMetadata' => array(
				'promptTokenCount'     => 812,
				'candidatesTokenCount' => 407,
				'totalTokenCount'      => 1219,
			),
			'modelVersion'  => 'gemini-3.6-flash',
		);
		return self::response( 200, wp_json_encode( $payload ) );
	}

	/* ---------------------------------------------------------------- Content fixtures */

	/**
	 * Extract the text of the LAST user message from any provider shape.
	 * Conversation-threaded generation resends the whole thread, so step
	 * detection must look at the newest instruction only — the full thread
	 * contains every earlier step's keywords.
	 *
	 * @param array $body Decoded request body.
	 * @return string
	 */
	private static function last_user_text( $body ) {
		$texts = array();

		// OpenAI Chat Completions / Anthropic Messages: messages[] with role.
		if ( isset( $body['messages'] ) && is_array( $body['messages'] ) ) {
			foreach ( $body['messages'] as $message ) {
				if ( ! is_array( $message ) || ( isset( $message['role'] ) && 'user' !== $message['role'] ) ) {
					continue;
				}
				$texts[] = self::content_text( isset( $message['content'] ) ? $message['content'] : '' );
			}
		}

		// OpenAI Responses API: input as string or messages array.
		if ( isset( $body['input'] ) ) {
			if ( is_string( $body['input'] ) ) {
				$texts[] = $body['input'];
			} elseif ( is_array( $body['input'] ) ) {
				foreach ( $body['input'] as $message ) {
					if ( is_array( $message ) && ( ! isset( $message['role'] ) || 'user' === $message['role'] ) ) {
						$texts[] = self::content_text( isset( $message['content'] ) ? $message['content'] : '' );
					}
				}
			}
		}

		// Gemini: contents[] with role user and parts[].text.
		if ( isset( $body['contents'] ) && is_array( $body['contents'] ) ) {
			foreach ( $body['contents'] as $content ) {
				if ( ! is_array( $content ) || ( isset( $content['role'] ) && 'user' !== $content['role'] ) ) {
					continue;
				}
				$texts[] = self::content_text( isset( $content['parts'] ) ? $content['parts'] : '' );
			}
		}

		$texts = array_values( array_filter( $texts, 'strlen' ) );
		return $texts ? (string) end( $texts ) : '';
	}

	/**
	 * Flatten a message content value (string, or array of typed parts).
	 *
	 * @param mixed $content Message content.
	 * @return string
	 */
	private static function content_text( $content ) {
		if ( is_string( $content ) ) {
			return $content;
		}
		if ( ! is_array( $content ) ) {
			return '';
		}
		$out = array();
		foreach ( $content as $part ) {
			if ( is_string( $part ) ) {
				$out[] = $part;
			} elseif ( is_array( $part ) && isset( $part['text'] ) && is_string( $part['text'] ) ) {
				$out[] = $part['text'];
			}
		}
		return implode( "\n", $out );
	}

	/**
	 * Every message text in the request, concatenated (for the request log —
	 * regression tests assert e.g. that the conclusion request carries the
	 * article body in its thread).
	 *
	 * @param array $body Decoded request body.
	 * @return string
	 */
	private static function all_text( $body ) {
		$out = array();
		if ( isset( $body['system'] ) ) {
			$out[] = self::content_text( $body['system'] );
		}
		foreach ( array( 'messages', 'input', 'contents' ) as $key ) {
			if ( ! isset( $body[ $key ] ) ) {
				continue;
			}
			if ( is_string( $body[ $key ] ) ) {
				$out[] = $body[ $key ];
				continue;
			}
			if ( is_array( $body[ $key ] ) ) {
				foreach ( $body[ $key ] as $message ) {
					if ( is_array( $message ) ) {
						$out[] = self::content_text( isset( $message['content'] ) ? $message['content'] : ( isset( $message['parts'] ) ? $message['parts'] : '' ) );
					}
				}
			}
		}
		return implode( "\n", array_filter( $out, 'strlen' ) );
	}

	/**
	 * Append this request to the mock request log (option-backed, last 12).
	 * E2E regression tests read it via WP-CLI:
	 *   wp option get ai_scribe_mock_request_log --format=json
	 *
	 * @param array  $body      Decoded request body.
	 * @param string $last_user Last user message text.
	 */
	private static function log_request( $body, $last_user ) {
		$log = get_option( 'ai_scribe_mock_request_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = array(
			'time'      => time(),
			'model'     => isset( $body['model'] ) ? (string) $body['model'] : '',
			'last_user' => substr( $last_user, 0, 20000 ),
			'all_text'  => substr( self::all_text( $body ), 0, 40000 ),
		);
		update_option( 'ai_scribe_mock_request_log', array_slice( $log, -12 ), false );
	}

	/**
	 * Inspect the outbound prompt and return plausible article-step content.
	 * Detection is keyword-based on the LAST user message so it works with
	 * conversation-threaded requests regardless of provider or template.
	 */
	/**
	 * Topic detected from the outbound prompt for this request (UAT clarity:
	 * fixtures echo the requested topic instead of always talking coffee).
	 *
	 * @var string
	 */
	private static $topic = '';

	/** Whether this request follows an earlier title response in the thread. */
	private static $title_variant = 0;

	/**
	 * Detect the user's article idea/topic in the outbound prompt. The v3
	 * prompts quote it: titles — `based on "[Idea]"`; Express — `based on
	 * the idea: "[Idea]"`. Falls back to the coffee fixture topic.
	 *
	 * @param string $text Prompt text (last user message + thread).
	 * @return string
	 */
	private static function detect_topic( $text ) {
		if ( preg_match( '/based on (?:the idea: )?["\x{201c}]([^"\x{201d}]{3,160})["\x{201d}]/u', $text, $m ) ) {
			return trim( $m[1] );
		}
		return '';
	}

	/** Detected topic, else the given fallback. */
	private static function topic_or( $fallback ) {
		return '' !== self::$topic ? self::$topic : $fallback;
	}

	private static function step_content( $body ) {
		$last_user = self::last_user_text( $body );
		self::log_request( $body, $last_user );
		$all_text    = self::all_text( $body );
		self::$topic = self::detect_topic( '' !== $last_user ? $last_user : $all_text );
		self::$title_variant = preg_match( '/"titles"\s*:/i', $all_text ) ? 1 : 0;
		if ( '' === self::$topic ) {
			self::$topic = self::detect_topic( $all_text );
		}
		$prompt = strtolower( '' !== $last_user ? $last_user : wp_json_encode( $body ) );

		$has = static function ( ...$needles ) use ( $prompt ) {
			foreach ( $needles as $n ) {
				if ( false !== strpos( $prompt, $n ) ) {
					return true;
				}
			}
			return false;
		};

		// Structured-output request: synthesise a valid instance from the
		// JSON schema actually sent (OpenAI response_format / Responses
		// text.format, Anthropic tool input_schema, Gemini responseSchema),
		// so choice steps parse against the real SchemaRegistry shapes.
		$schema = self::extract_schema( $body );
		if ( null !== $schema ) {
			return wp_json_encode( self::schema_instance( $schema ) );
		}

		if ( $has( 'json_schema', 'responseschema', 'tool_choice' ) || $has( 'return json', 'as json' ) ) {
			// Structured request without a recoverable schema: generic options.
			return wp_json_encode( array(
				'options' => array(
					'How to Brew Better Coffee at Home: A Practical Guide',
					'The Home Barista Handbook: Better Coffee Without the Cafe Price',
					'Better Coffee at Home: Beans, Grind and Water Explained',
				),
			) );
		}

		// Branch order matters: the real 2.6.2 prompt wording cross-references
		// other steps (the tagline prompt mentions keywords, the keywords
		// prompt mentions the title, the article prompt mentions headings),
		// so the most specific instructions are checked first.

		if ( $has( 'evaluat', 'assess', 'score the article' ) ) {
			return '<table class="evaluation-table"><thead><tr><th>STATUS</th><th>QUESTION</th><th>EVALUATION</th><th>RATIONALE</th></tr></thead><tbody><tr><td></td><td>Does the article target the primary keyword?</td><td>PASS</td><td>The keyword appears in the title, introduction and two subheadings.</td></tr><tr><td></td><td>Is the structure logical?</td><td>PASS</td><td>Sections follow the brewing workflow in order.</td></tr><tr><td></td><td>Are internal links present?</td><td>IMPROVE</td><td>Add one internal link per section.</td></tr></tbody></table>';
		}

		if ( $has( 'meta description', 'seo meta', 'meta title' ) ) {
			return "Meta Title: How to Brew Better Coffee at Home | Practical Guide\nMeta Description: Learn how to brew better coffee at home with fresh beans, the right grind and proper ratios. A practical, equipment-light guide for everyday brewers.";
		}

		// Article body FIRST among longforms: the 2.6.2 article prompt
		// ("Write my article using only HTML tags…") also mentions Q&A,
		// introduction, tagline, outline and keywords it builds from. It must
		// be classified before those narrower steps or the test fixture can
		// return an FAQ in the Article Body panel.
		if ( $has( 'write my article', 'write the article', 'article body', 'full article' ) ) {
			return self::body_html();
		}

		if ( $has( 'questions and answers', 'question', 'q&a', 'faq' ) ) {
			return "<h3>How fresh should coffee beans be?</h3><p>Use beans within four weeks of the roast date, and rest very fresh roasts for five days before brewing.</p><h3>What grind size should I use for pour over?</h3><p>Medium-fine, similar to table salt. If the brew tastes sour, grind finer; if bitter, grind coarser.</p><h3>Is filtered water really necessary?</h3><p>If your tap water tastes fine, it will brew fine. Very hard water benefits from a simple jug filter.</p>";
		}

		if ( $has( 'conclusion', 'concluding', 'summarise the article', 'summarize the article' ) ) {
			return '<p>Better coffee at home comes down to a short list: buy beans roasted within the past month, grind just before brewing, weigh your dose, and keep the water between 92 and 96°C. Fix those four and the method you choose matters far less than the internet suggests.</p><p>Start with the ratio in section six, adjust one variable at a time, and keep notes for a week. Your tenth cup will embarrass your first.</p>';
		}

		if ( $has( 'introduction', 'intro paragraph', 'opening paragraph' ) ) {
			return '<p>Most home coffee is bad for boring reasons: stale beans, the wrong grind, water straight off the boil. None of these takes skill to fix. This guide walks through each variable in the order it matters, so by the end you can make a cup that beats the chain on the corner, for about 40p.</p><p>You do not need a £600 machine. You need fresh beans, a scale, and ten minutes of attention.</p>';
		}

		if ( $has( 'tagline', 'catchy phrase', 'slogan' ) ) {
			return 'Great coffee is not a talent. It is a checklist.';
		}

		if ( $has( 'article outline', 'outline', 'table of contents' ) ) {
			return "1. Why Your Home Coffee Disappoints (and Why It Needn't)\n2. Choosing Beans: Freshness Beats Origin\n3. Grind Size: The Variable Most People Ignore\n4. Water Quality and Temperature\n5. Brewing Methods Compared: Pour Over, French Press, AeroPress\n6. Dialling In Your Ratio\n7. Common Mistakes and Quick Fixes";
		}

		if ( $has( 'keyword' ) ) {
			return "1. home coffee brewing\n2. brew better coffee\n3. coffee grind size\n4. pour over technique\n5. coffee to water ratio\n6. speciality coffee beans\n7. french press brewing\n8. water temperature coffee";
		}

		if ( $has( 'title' ) && $has( 'suggest', 'generate', 'provide', 'ideas', 'options' ) ) {
			$mock_title = self::title_case( self::topic_or( 'how to brew better coffee at home' ) );
			return "1. {$mock_title}: A Practical Guide\n2. The Complete Handbook: {$mock_title}\n3. {$mock_title} Explained Simply\n4. Common Mistakes in {$mock_title} (and How to Fix Them)\n5. From Beginner to Confident: {$mock_title}";
		}

		// Default: body copy for a section or full article.
		return self::body_html();
	}

	/**
	 * Full article body fixture (five substantive h2 sections).
	 */
	private static function body_html() {
		$topic    = esc_html( strtolower( self::topic_or( 'how to brew better coffee at home' ) ) );
		$headings = self::outline_headings();
		$focuses  = array(
			'current position and the outcome the reader needs',
			'constraints, priorities and a workable plan',
			'available approaches and the trade-offs between them',
			'careful implementation, checks and common failure points',
			'review, maintenance and the most useful next action',
		);
		$html = '';
		foreach ( $headings as $index => $heading ) {
			$focus = $focuses[ $index ];
			$html .= '<h2>' . esc_html( $heading ) . '</h2>';
			$html .= '<p>Start this part of ' . $topic . ' by defining ' . $focus . '. Write down what is already known, what is uncertain and what would count as a useful result. This prevents a familiar mistake: choosing an attractive answer before the real requirement has been made clear. Keep the first decision small enough to inspect and reverse.</p>';
			$html .= '<p>Turn that decision into a practical sequence. Identify the input, the person responsible, the check they will perform and the point at which work should pause. Compare options against the same criteria instead of changing the criteria to favour a preferred choice. Record assumptions plainly, especially where current information cannot verify a precise claim.</p>';
			$html .= '<p>Test the approach on a representative example before applying it more widely. Look for friction, hidden dependencies and cases where a technically correct result would still be unhelpful to the reader. If the result fails, change one variable at a time. That makes the cause easier to identify and avoids replacing one unclear problem with several new ones.</p>';
			$html .= '<p>Finish with evidence the reader can check: the intended outcome, the action completed, the result observed and the next review point. A useful section should leave someone able to make a decision or carry out a task, not merely recognise the terminology. Where circumstances vary, state the trade-off and explain when each option is the sensible choice.</p>';
		}
		return $html;
	}

	/** Five deterministic headings shared by outline and body fixtures. */
	private static function outline_headings() {
		if ( false !== stripos( self::topic_or( '' ), 'coffee' ) ) {
			return array(
				'Choosing Beans: Freshness Beats Origin',
				'Grind Size: The Variable Most People Ignore',
				'Water Quality and Temperature',
				'Brewing Methods and Practical Trade-offs',
				'Dialling In, Recording and Improving Results',
			);
		}
		$subject = self::title_case( self::topic_or( 'the topic' ) );
		return array(
			'Understanding ' . $subject,
			'Planning Priorities and Constraints',
			'Comparing the Available Approaches',
			'Implementation and Quality Checks',
			'Reviewing Results and Next Steps',
		);
	}

	/**
	 * Pull the JSON schema out of an outbound structured-output request,
	 * whichever provider shape carried it.
	 *
	 * @param array $body Decoded request body.
	 * @return array|null
	 */
	private static function extract_schema( $body ) {
		// OpenAI Chat Completions: response_format.json_schema.schema
		if ( isset( $body['response_format']['json_schema']['schema'] ) && is_array( $body['response_format']['json_schema']['schema'] ) ) {
			return $body['response_format']['json_schema']['schema'];
		}
		// OpenAI Responses API: text.format.schema
		if ( isset( $body['text']['format']['schema'] ) && is_array( $body['text']['format']['schema'] ) ) {
			return $body['text']['format']['schema'];
		}
		// Anthropic tool-forcing: tools[0].input_schema
		if ( isset( $body['tools'][0]['input_schema'] ) && is_array( $body['tools'][0]['input_schema'] ) ) {
			return $body['tools'][0]['input_schema'];
		}
		// Gemini: generationConfig.responseSchema
		if ( isset( $body['generationConfig']['responseSchema'] ) && is_array( $body['generationConfig']['responseSchema'] ) ) {
			return $body['generationConfig']['responseSchema'];
		}
		return null;
	}

	/**
	 * Build a plausible instance of a JSON schema (objects, arrays,
	 * strings by property name, numbers).
	 *
	 * @param array  $schema JSON schema fragment.
	 * @param string $key    Property name for context-aware strings.
	 * @return mixed
	 */
	private static function schema_instance( $schema, $key = '', $index = 0 ) {
		if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) && $schema['enum'] ) {
			return $schema['enum'][ max( 0, $index - 1 ) % count( $schema['enum'] ) ];
		}
		// Gemini's responseSchema uses UPPERCASE type names (OBJECT/ARRAY/
		// STRING); OpenAI and Anthropic use lowercase. Normalise so both
		// shapes synthesise correctly.
		$type = isset( $schema['type'] ) ? strtolower( (string) $schema['type'] ) : ( isset( $schema['properties'] ) ? 'object' : 'string' );

		if ( 'object' === $type ) {
			$out   = array();
			$props = isset( $schema['properties'] ) && is_array( $schema['properties'] ) ? $schema['properties'] : array();
			foreach ( $props as $name => $prop_schema ) {
				$out[ $name ] = self::schema_instance( is_array( $prop_schema ) ? $prop_schema : array(), $name, $index );
			}
			return $out;
		}

		if ( 'array' === $type ) {
			$items = isset( $schema['items'] ) && is_array( $schema['items'] ) ? $schema['items'] : array( 'type' => 'string' );
			$count = in_array( $key, array( 'titles', 'keywords', 'taglines', 'outline', 'qna' ), true ) ? 5 : 3;
			$out   = array();
			for ( $i = 1; $i <= $count; $i++ ) {
				// §13.4: the index rides down so array members VARY — the old
				// fixture repeated the identical Q&A three times.
				$item = self::schema_instance( $items, rtrim( $key, 's' ), $i );
				if ( is_string( $item ) && 0 === strpos( $item, 'Mock value' ) ) {
					$item .= " (option {$i})";
				}
				$out[] = $item;
			}
			return $out;
		}

		if ( 'integer' === $type || 'number' === $type ) {
			return 1;
		}
		if ( 'boolean' === $type ) {
			return true;
		}

		$topic_title = self::title_case( self::topic_or( 'how to brew better coffee at home' ) );
		$pick        = static function ( array $list ) use ( $index ) {
			return $list[ max( 0, $index - 1 ) % count( $list ) ];
		};

		// Strings, keyed by property name where it matters. The detected
		// topic is echoed so UAT runs read naturally.
		switch ( $key ) {
			case 'title':
				$variants = self::$title_variant
					? array(
						$topic_title . ': A Decision Checklist',
						'Planning ' . $topic_title . ' Step by Step',
						$topic_title . ': Options and Trade-offs',
						'How to Assess ' . $topic_title . ' with Confidence',
						$topic_title . ': Practical Next Steps',
					)
					: array(
						$topic_title . ': A Practical Guide',
						'The Complete Guide to ' . $topic_title,
						$topic_title . ' Explained Simply',
						'Common Mistakes in ' . $topic_title . ' (and How to Fix Them)',
						$topic_title . ': From First Steps to Confident Results',
					);
				return $pick( $variants );
			case 'description':
				return 'Learn about ' . strtolower( self::topic_or( 'brewing better coffee at home' ) ) . ' with clear, practical steps. An equipment-light guide for everyday readers.';
			case 'tagline':
				return $pick( array(
					'Great results are not a talent. They are a checklist.',
					'Small changes, dramatically better results.',
					'Master the basics; the rest follows.',
				) );
			case 'keyword':
				return $pick( array(
					strtolower( self::topic_or( 'home coffee brewing' ) ),
					'beginner guide',
					'practical tips',
					'common mistakes',
					'step by step',
				) );
			case 'outline':
			case 'heading':
				return $pick( self::outline_headings() );
			case 'question':
				return $pick( array(
					'How do I get started with ' . strtolower( self::topic_or( 'better coffee at home' ) ) . '?',
					'What mistakes should beginners avoid?',
					'How long before I see results?',
					'What should I record for a useful review?',
					'When should I pause and seek better evidence?',
				) );
			case 'answer':
				return $pick( array(
					'Start by recording the current position, the outcome you need and the constraints you cannot change. Choose one small, reversible action and define the check that will show whether it helped. This creates useful evidence before more time or money is committed.',
					'A common mistake is choosing an option before agreeing the criteria it must satisfy. Compare each option against the same needs, costs, risks and practical limits. Keep uncertain claims labelled as assumptions until current evidence can confirm them.',
					'Timing depends on the scope and the feedback available. Set an early review point based on a representative example rather than waiting for the whole task to finish. If the result is weak, change one variable and repeat the same check.',
					'Keep a short decision record containing the chosen action, its owner, the expected result and the review date. This makes handovers clearer and stops later changes from being judged against a different, undocumented goal.',
					'Pause when a required input is missing, a result cannot be checked or the next action would be difficult to reverse. State what evidence is needed, who can provide it and what safe work can continue while that gap is resolved.',
				) );
			case 'intro':
				return '<p>Approaching ' . esc_html( strtolower( self::topic_or( 'brewing coffee at home' ) ) ) . ' becomes easier when the decision is broken into visible parts. This guide starts with the outcome and constraints, compares practical options using consistent criteria, and then turns the preferred approach into a checked sequence of actions. It also explains where assumptions can mislead, when to pause for better evidence and how to review a result without changing several variables at once. The aim is to leave the reader with a usable method, not a list of unexplained terms. Each section builds on the previous one while answering a separate question, so readers can follow the complete process or return to the stage that matches their current problem.</p>';
			case 'body_html':
				return self::body_html();
			case 'conclusion':
				return '<p>A sound approach begins with a clear outcome, applies the same criteria to every option and tests a reversible action before wider use. Keep the evidence, assumptions and next review point together so another person can understand the decision. If a check fails, resist the urge to change everything at once. Adjust one factor, observe the result and record what changed. That discipline makes progress easier to explain and prevents confident language from hiding an unverified claim. The useful next step is to write down the current constraint and the smallest action that can test it, then arrange the first review before committing more time or money.</p>';
			default:
				return 'Mock value for ' . ( '' !== $key ? $key : 'string' );
		}
	}

	/**
	 * Title-case a phrase, keeping small connective words lowercase (so the
	 * idea "how to brew better coffee at home" round-trips as the exact
	 * title the E2E suite asserts: "How to Brew Better Coffee at Home").
	 *
	 * @param string $phrase Topic phrase.
	 * @return string
	 */
	private static function title_case( $phrase ) {
		$small = array( 'a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'in', 'of', 'on', 'or', 'the', 'to', 'with' );
		$words = preg_split( '/\s+/', trim( (string) $phrase ) );
		$out   = array();
		foreach ( $words as $i => $word ) {
			$lower = strtolower( $word );
			$out[] = ( 0 !== $i && in_array( $lower, $small, true ) ) ? $lower : ucfirst( $lower );
		}
		return implode( ' ', $out );
	}

	/* ---------------------------------------------------------------- Helpers */

	private static function request_body( $args ) {
		if ( empty( $args['body'] ) ) {
			return array();
		}
		if ( is_array( $args['body'] ) ) {
			return $args['body'];
		}
		$decoded = json_decode( $args['body'], true );
		return is_array( $decoded ) ? $decoded : array( 'raw' => (string) $args['body'] );
	}

	private static function response( $code, $body, $extra_headers = array() ) {
		return array(
			'headers'  => array_merge(
				array(
					'content-type'       => 'application/json',
					'x-ai-scribe-mock'   => '1',
					'x-request-id'       => 'mock_' . self::uid(),
				),
				$extra_headers
			),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	private static function uid() {
		return substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 12 );
	}

	/**
	 * A 16x16 opaque blue PNG (valid, media-library ingestible).
	 */
	private static function tiny_png_base64() {
		return 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGUlEQVR4nGPQztn8nxLMMGrAqAGjBgwXAwAwHUkf8/GuugAAAABJRU5ErkJggg==';
	}
}

AI_Scribe_Mock_Provider::init();
