<?php
/**
 * Schema Registry for AI-Scribe Plugin
 *
 * JSON schemas for the structured-output ("choice") steps and Express
 * mode, plus provider-specific request-option builders and a lightweight
 * validator. Structured outputs make the old parse_outline_options()
 * regex class of bugs impossible: the renderer consumes typed objects.
 *
 * Choice steps: 1 titles · 2 keywords · 3 outline · 5 taglines · 8 qna ·
 * 9 meta · 11 evaluation checks.
 * Long-form steps (4, 6, 7, 10) are free-form HTML and validated as
 * non-empty HTML instead.
 *
 * @package AI_Scribe
 * @subpackage Services
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Scribe_Schema_Registry {

	const CHOICE_STEPS   = array( 1, 2, 3, 5, 8, 9, 11 );
	const LONGFORM_STEPS = array( 4, 6, 7, 10 );

	/**
	 * Whether a step uses structured output.
	 *
	 * @param int $step
	 * @return bool
	 */
	public static function is_choice_step( $step ) {
		return in_array( (int) $step, self::CHOICE_STEPS, true );
	}

	/**
	 * JSON schema for a choice step.
	 *
	 * @param int $step
	 * @return array|null ['name' => string, 'schema' => array] or null.
	 */
	public static function get_step_schema( $step ) {
		$string_array = function ( $min = 1, $max = null ) {
			$schema = array(
				'type'     => 'array',
				'items'    => array(
					'type'      => 'string',
					'minLength' => 1,
				),
				'minItems' => $min,
			);
			if ( null !== $max ) {
				$schema['maxItems'] = $max;
			}
			return $schema;
		};

		switch ( (int) $step ) {
			case 1:
				return array(
					'name'   => 'article_titles',
					'schema' => self::object( array( 'titles' => $string_array() ) ),
				);
			case 2:
				return array(
					'name'   => 'article_keywords',
					'schema' => self::object(
						array(
							'keywords' => array(
								'type'     => 'array',
								'minItems' => 1,
								'items'    => self::object(
									array(
										'keyword'        => array(
											'type'      => 'string',
											'minLength' => 1,
										),
										'role'           => array(
											'type' => 'string',
											'enum' => array( 'primary', 'supporting', 'long-tail' ),
										),
										'demand_band'    => array(
											'type' => 'string',
											'enum' => array( 'low', 'medium', 'high', 'unknown' ),
										),
										'estimate_basis' => array(
											'type' => 'string',
											'enum' => array( 'ai_unverified' ),
										),
									)
								),
							),
						)
					),
				);
			case 3:
				return array(
					'name'   => 'article_outline',
					'schema' => self::object( array( 'outline' => $string_array() ) ),
				);
			case 5:
				return array(
					'name'   => 'article_taglines',
					'schema' => self::object( array( 'taglines' => $string_array( 1, 1 ) ) ),
				);
			case 8:
				return array(
					'name'   => 'article_qna',
					'schema' => self::object(
						array(
							'qna' => array(
								'type'     => 'array',
								'minItems' => 1,
								'items'    => self::object(
									array(
										'question' => array(
											'type'      => 'string',
											'minLength' => 1,
										),
										'answer'   => array(
											'type'      => 'string',
											'minLength' => 1,
										),
									)
								),
							),
						)
					),
				);
			case 9:
				return array(
					'name'   => 'article_meta',
					'schema' => self::object(
						array(
							'meta' => self::object(
								array(
									'title'       => array(
										'type'      => 'string',
										'minLength' => 1,
									),
									'description' => array(
										'type'      => 'string',
										'minLength' => 1,
									),
								)
							),
						)
					),
				);
			case 11:
				// Evaluate: structured per-check results, never a prose blob
				// (L-27). suggestion may be empty for a clear pass.
				return array(
					'name'   => 'article_evaluation',
					'schema' => self::object(
						array(
							'checks' => array(
								'type'     => 'array',
								'minItems' => 1,
								'items'    => self::object(
									array(
										'label'      => array(
											'type'      => 'string',
											'minLength' => 1,
										),
										'status'     => array(
											'type' => 'string',
											'enum' => array( 'pass', 'warn', 'fail', 'unknown' ),
										),
										'detail'     => array(
											'type'      => 'string',
											'minLength' => 1,
										),
										'suggestion' => array(
											'type' => 'string',
										),
									)
								),
							),
						)
					),
				);
			default:
				return null;
		}
	}

	/**
	 * JSON schema for Express mode (whole article in one call).
	 *
	 * @return array ['name' => string, 'schema' => array]
	 */
	public static function get_express_schema() {
		return array(
			'name'   => 'express_article',
			'schema' => self::object(
				array(
					'title'      => array(
						'type'      => 'string',
						'minLength' => 1,
					),
					'meta'       => self::object(
						array(
							'title'       => array(
								'type'      => 'string',
								'minLength' => 1,
							),
							'description' => array(
								'type'      => 'string',
								'minLength' => 1,
							),
						)
					),
					'tagline'    => array( 'type' => 'string' ),
					'outline'    => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
						'minItems' => 1,
					),
					'intro'      => array(
						'type'      => 'string',
						'minLength' => 1,
					),
					'body_html'  => array(
						'type'      => 'string',
						'minLength' => 1,
					),
					'conclusion' => array(
						'type'      => 'string',
						'minLength' => 1,
					),
					'qna'        => array(
						'type'  => 'array',
						'items' => self::object(
							array(
								'question' => array(
									'type'      => 'string',
									'minLength' => 1,
								),
								'answer'   => array(
									'type'      => 'string',
									'minLength' => 1,
								),
							)
						),
					),
				)
			),
		);
	}

	/**
	 * Provider request options that force this schema.
	 *
	 * OpenAI:  response_format json_schema (chat) / text.format (responses).
	 * Anthropic: tool-forcing (tools + tool_choice).
	 * Gemini:  generationConfig.responseSchema + responseMimeType.
	 * Grok:    OpenAI-shaped response_format.
	 *
	 * @param string $provider openai|anthropic|gemini|grok
	 * @param array  $schema_def ['name' => ..., 'schema' => ...]
	 * @return array Options to merge into the adapter request.
	 */
	public static function provider_options( $provider, array $schema_def ) {
		$name   = $schema_def['name'];
		$schema = $schema_def['schema'];

		switch ( $provider ) {
			case 'anthropic':
				return array(
					'tools'       => array(
						array(
							'name'         => $name,
							'description'  => 'Record the structured result. Always call this tool.',
							'input_schema' => $schema,
						),
					),
					'tool_choice' => array(
						'type' => 'tool',
						'name' => $name,
					),
				);

			case 'gemini':
				return array(
					'generationConfig' => array(
						'responseMimeType' => 'application/json',
						'responseSchema'   => self::gemini_schema( $schema ),
					),
				);

			case 'grok':
			case 'openai':
			default:
				// Chat Completions shape; the OpenAI provider translates to
				// text.format for Responses-API models.
				return array(
					'response_format' => array(
						'type'        => 'json_schema',
						'json_schema' => array(
							'name'   => $name,
							'strict' => true,
							'schema' => $schema,
						),
					),
				);
		}
	}

	/**
	 * Validate decoded data against a step schema (or Express schema when
	 * $step === 'express'). Long-form steps validate as non-empty HTML.
	 *
	 * @param int|string $step
	 * @param mixed      $data Decoded data (array) for choice steps; HTML string for long-form.
	 * @return true|array True on success, list of error strings on failure.
	 */
	public static function validate( $step, $data ) {
		if ( $step === 'express' ) {
			$schema_def = self::get_express_schema();
			return self::validate_schema( $schema_def['schema'], $data, '$' );
		}

		$step = (int) $step;
		if ( self::is_choice_step( $step ) ) {
			$schema_def = self::get_step_schema( $step );
			return self::validate_schema( $schema_def['schema'], $data, '$' );
		}

		// Long-form: non-empty markup/text.
		$html = is_string( $data ) ? trim( $data ) : '';
		if ( $html === '' ) {
			return array( 'Long-form response is empty.' );
		}
		return true;
	}

	/**
	 * Parse a raw model string into validated data for a step.
	 *
	 * @param int|string $step Step number or 'express'.
	 * @param string     $raw  Raw model output (JSON expected for choice steps).
	 * @return array ['ok' => bool, 'data' => mixed, 'errors' => array]
	 */

	/**
	 * Convert a Markdown response to HTML.
	 *
	 * Long-form steps ask for HTML, and OpenAI complies. Gemini frequently
	 * answers in Markdown regardless, and the "###" markers were stored
	 * verbatim: by the time the content reached the editor its newlines had
	 * been collapsed, so a published article was one unbroken wall of text
	 * with literal hashes in it and no headings at all — no structure for
	 * readers and none for search engines either.
	 *
	 * Converting here is the earliest point at which the line breaks still
	 * exist. Content that already contains block-level HTML is returned
	 * untouched, so a compliant provider is never reformatted.
	 *
	 * @param string $text Raw model output.
	 * @return string HTML.
	 */
	private static function markdown_to_html( $text ) {
		$text = (string) $text;
		if ( '' === trim( $text ) ) {
			return $text;
		}

		// Already structured HTML: leave it alone.
		if ( preg_match( '/<(h[1-6]|p|ul|ol|table|blockquote|div|section|figure)\b/i', $text ) ) {
			return $text;
		}

		// Plain text is left alone, but inline-only Markdown still needs parsing.
		$has_block_markdown  = preg_match( '/^\s{0,3}#{1,6}\s+\S/m', $text ) || preg_match( '/^\s{0,3}[-*+]\s+\S/m', $text );
		$has_inline_markdown = preg_match( '/\*\*(?=\S).+?(?<=\S)\*\*|(?<!\*)\*(?!\s).+?(?<!\s)\*(?!\*)|`[^`]+`|\[[^\]]+\]\(https?:\/\/[^\s)]+\)/s', $text );
		if ( ! $has_block_markdown && ! $has_inline_markdown ) {
			return $text;
		}

		$lines  = preg_split( '/\r\n|\r|\n/', $text );
		$out    = array();
		$para   = array();
		$list   = array();

		$flush_para = static function () use ( &$para, &$out ) {
			if ( ! empty( $para ) ) {
				$out[] = '<p>' . implode( ' ', $para ) . '</p>';
				$para  = array();
			}
		};
		$flush_list = static function () use ( &$list, &$out ) {
			if ( ! empty( $list ) ) {
				$out[] = '<ul>' . implode( '', array_map( static function ( $i ) {
					return '<li>' . $i . '</li>';
				}, $list ) ) . '</ul>';
				$list = array();
			}
		};

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			if ( '' === $trimmed ) {
				$flush_list();
				$flush_para();
				continue;
			}

			if ( preg_match( '/^#{1,6}\s+(.*)$/', $trimmed, $m ) ) {
				$flush_list();
				$flush_para();
				$level = strlen( $trimmed ) - strlen( ltrim( $trimmed, '#' ) );
				// Keep h1 for the article title: demote a Markdown h1 to h2.
				$level = max( 2, min( 6, $level ) );
				$out[] = '<h' . $level . '>' . self::inline_markdown( $m[1] ) . '</h' . $level . '>';
				continue;
			}

			if ( preg_match( '/^[-*+]\s+(.*)$/', $trimmed, $m ) ) {
				$flush_para();
				$list[] = self::inline_markdown( $m[1] );
				continue;
			}

			$flush_list();
			$para[] = self::inline_markdown( $trimmed );
		}
		$flush_list();
		$flush_para();

		return implode( "\n", $out );
	}

	/**
	 * Bold, italic and inline code within a single Markdown line.
	 *
	 * @param string $text Line content.
	 * @return string
	 */
	private static function inline_markdown( $text ) {
		$text = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text );
		$text = preg_replace( '/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/s', '<em>$1</em>', $text );
		$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );
		$text = preg_replace( '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2">$1</a>', $text );
		return $text;
	}

	public static function parse( $step, $raw ) {
		$is_structured = ( $step === 'express' ) || self::is_choice_step( (int) $step );

		if ( ! $is_structured ) {
			$html   = self::markdown_to_html( self::strip_code_fences( (string) $raw ) );
			$result = self::validate( $step, $html );
			return $result === true
				? array(
					'ok'     => true,
					'data'   => array( 'html' => $html ),
					'errors' => array(),
				)
				: array(
					'ok'     => false,
					'data'   => null,
					'errors' => $result,
				);
		}

		$json    = self::strip_code_fences( (string) $raw );
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			// Some providers wrap JSON in prose; try to find the outermost object.
			if ( preg_match( '/\{.*\}/s', $json, $m ) ) {
				$decoded = json_decode( $m[0], true );
			}
		}

		if ( 'express' === $step ) {
			$decoded = self::normalise_express_data( $decoded );
		}
		if ( ! is_array( $decoded ) ) {
			return array(
				'ok'     => false,
				'data'   => null,
				'errors' => array( 'Response was not valid JSON.' ),
			);
		}

		// Evaluation statuses: tolerate case and legacy wording ("PASS",
		// "Improve") rather than failing an otherwise sound response.
		if ( 11 === (int) $step && isset( $decoded['checks'] ) && is_array( $decoded['checks'] ) ) {
			$status_map = array(
				'passed'  => 'pass',
				'failed'  => 'fail',
				'improve' => 'warn',
				'warning' => 'warn',
				'check'   => 'unknown',
				'info'    => 'unknown',
				'unclear' => 'unknown',
			);
			foreach ( $decoded['checks'] as $i => $check ) {
				if ( is_array( $check ) && isset( $check['status'] ) && is_string( $check['status'] ) ) {
					$status = strtolower( trim( $check['status'] ) );
					$decoded['checks'][ $i ]['status'] = isset( $status_map[ $status ] ) ? $status_map[ $status ] : $status;
				}
			}
		}

		$decoded = self::normalise_choice_data( $step, $decoded );
		if ( 9 === (int) $step ) {
			$decoded = self::normalise_meta_data( $decoded );
			$title = isset( $decoded['meta']['title'] ) ? (string) $decoded['meta']['title'] : '';
			if ( ! preg_match( '/^.+\s\|\s.+$/u', $title ) ) {
				return array(
					'ok'     => false,
					'data'   => $decoded,
					'errors' => array( 'Meta title must contain two meaningful components separated by a spaced pipe ( | ).' ),
				);
			}
		}

		$result = self::validate( $step, $decoded );
		return $result === true
			? array(
				'ok'     => true,
				'data'   => $decoded,
				'errors' => array(),
			)
			: array(
				'ok'     => false,
				'data'   => $decoded,
				'errors' => $result,
			);
	}

	/**
	 * Keep the Express article's document structure deterministic.
	 *
	 * The structured response already has one authoritative title field. Any
	 * H1 inside the separately rendered introduction, body or conclusion is a
	 * second document title, and providers sometimes put the SEO meta title
	 * there. Remove those headings before persistence so Express, Refine and
	 * saved posts all receive the same one-H1 article structure.
	 *
	 * @param array $data Decoded Express response.
	 * @return array
	 */
	private static function normalise_express_data( array $data ) {
		if ( isset( $data['title'] ) && is_string( $data['title'] ) ) {
			$data['title'] = trim( wp_strip_all_tags( $data['title'] ) );
		}
		foreach ( array( 'intro', 'body_html', 'conclusion' ) as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_string( $data[ $key ] ) ) {
				continue;
			}
			$html         = wp_kses_post( self::markdown_to_html( $data[ $key ] ) );
			$data[ $key ] = trim( preg_replace( '/<h1\b[^>]*>[\s\S]*?<\/h1>/i', '', $html ) );
		}
		return $data;
	}

	/**
	 * Keep provider choice output consistent without inventing SEO evidence.
	 * Models frequently lowercase familiar acronyms, repeat taglines, or emit
	 * guessed keyword volumes. The UI must not present any of those as facts.
	 *
	 * @param int|string $step Step number.
	 * @param array      $data Decoded provider response.
	 * @return array
	 */
	private static function normalise_choice_data( $step, array $data ) {
		$step = (int) $step;
		$key  = array( 1 => 'titles', 2 => 'keywords', 5 => 'taglines' );
		if ( ! isset( $key[ $step ] ) || ! isset( $data[ $key[ $step ] ] ) || ! is_array( $data[ $key[ $step ] ] ) ) {
			return $data;
		}

		$seen   = array();
		$values = array();
		foreach ( $data[ $key[ $step ] ] as $index => $value ) {
			$keyword_meta = null;
			if ( 2 === $step ) {
				if ( is_string( $value ) ) {
					// Older releases stored string arrays and sometimes included
					// invented pipe-delimited metrics. Preserve only the phrase;
					// an old metric can never be promoted to a current estimate.
					$phrase = trim( explode( '|', $value, 2 )[0] );
					$role   = 0 === (int) $index ? 'primary' : 'supporting';
					$band   = 'unknown';
				} elseif ( is_array( $value ) ) {
					$phrase = isset( $value['keyword'] ) && is_string( $value['keyword'] ) ? trim( $value['keyword'] ) : '';
					$role   = isset( $value['role'] ) ? strtolower( trim( (string) $value['role'] ) ) : '';
					$role   = str_replace( array( '_', ' ' ), '-', $role );
					if ( ! in_array( $role, array( 'primary', 'supporting', 'long-tail' ), true ) ) {
						$role = 0 === (int) $index ? 'primary' : 'supporting';
					}
					// The ordered first suggestion is the default focus keyword.
					// Keep exactly one primary role without inventing new phrases.
					if ( 0 === (int) $index ) {
						$role = 'primary';
					} elseif ( 'primary' === $role ) {
						$role = 'supporting';
					}
					$band = isset( $value['demand_band'] ) ? strtolower( trim( (string) $value['demand_band'] ) ) : 'unknown';
					if ( ! in_array( $band, array( 'low', 'medium', 'high', 'unknown' ), true ) ) {
						$band = 'unknown';
					}
				} else {
					continue;
				}
				$value        = $phrase;
				$keyword_meta = array(
					'role'           => $role,
					'demand_band'    => $band,
					'estimate_basis' => 'ai_unverified',
				);
			} elseif ( is_string( $value ) ) {
				$value = trim( $value );
			} else {
				continue;
			}
			$value = self::normalise_known_acronyms( $value );
			if ( 1 === $step ) {
				$value = preg_replace( '/\bthis year\b/i', gmdate( 'Y' ), $value );
				$value = self::normalise_editorial_title( $value );
			}
			$identity = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
			if ( '' === $value || isset( $seen[ $identity ] ) ) {
				continue;
			}
			$seen[ $identity ] = true;
			$values[]          = 2 === $step
				? array(
					'keyword'        => $value,
					'role'           => $keyword_meta['role'],
					'demand_band'    => $keyword_meta['demand_band'],
					'estimate_basis' => $keyword_meta['estimate_basis'],
				)
				: $value;
		}
		if ( 5 === $step ) {
			$values = array_slice( $values, 0, 1 );
		}
		$data[ $key[ $step ] ] = $values;
		return $data;
	}

	/**
	 * Extract clean keyword phrases from either the current structured shape
	 * or a legacy array of strings. Selection storage and downstream article
	 * prompts deliberately remain phrase-only.
	 *
	 * @param mixed $values Keyword objects, strings, or a single string.
	 * @return array<int,string>
	 */
	public static function keyword_phrases( $values ) {
		if ( ! is_array( $values ) ) {
			$values = is_string( $values ) ? array( $values ) : array();
		}
		$phrases = array();
		$seen    = array();
		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				$value = isset( $value['keyword'] ) ? $value['keyword'] : '';
			}
			if ( ! is_string( $value ) ) {
				continue;
			}
			$value    = trim( $value );
			$identity = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
			if ( '' === $value || isset( $seen[ $identity ] ) ) {
				continue;
			}
			$seen[ $identity ] = true;
			$phrases[]         = $value;
		}
		return $phrases;
	}

	/**
	 * Normalise generated metadata without rewriting a user's later edits.
	 * Provider output is title-cased consistently, known names keep their
	 * canonical spelling, and common component separators become one spaced
	 * pipe. The wording itself is never invented here.
	 */
	private static function normalise_meta_data( array $data ) {
		if ( empty( $data['meta'] ) || ! is_array( $data['meta'] ) ) {
			return $data;
		}
		$title = isset( $data['meta']['title'] ) ? trim( (string) $data['meta']['title'] ) : '';
		$title = self::normalise_meta_separator( $title );
		$data['meta']['title'] = self::normalise_editorial_title( $title );
		if ( isset( $data['meta']['description'] ) ) {
			$data['meta']['description'] = self::normalise_known_acronyms( trim( (string) $data['meta']['description'] ) );
		}
		return $data;
	}

	/** Convert one provider-style title separator to the configured pipe. */
	private static function normalise_meta_separator( $title ) {
		$title = preg_replace( '/\s*\|\s*/u', ' | ', trim( (string) $title ) );
		if ( false === strpos( $title, ' | ' ) ) {
			$title = preg_replace( '/\s*(?::|\x{2013}|\x{2014}|\s-\s|\s\/\s)\s*/u', ' | ', $title, 1 );
		}
		return trim( preg_replace( '/\s+/u', ' ', $title ) );
	}

	/**
	 * Conservative title case: lowercase short joining words inside a
	 * component, capitalise content words, and preserve recognised acronyms,
	 * brands, numbers and already mixed-case names.
	 */
	private static function normalise_editorial_title( $text ) {
		$text  = self::normalise_known_acronyms( trim( preg_replace( '/\s+/u', ' ', (string) $text ) ) );
		$minor = array( 'a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'from', 'in', 'nor', 'of', 'on', 'or', 'per', 'the', 'to', 'via', 'vs', 'with' );
		$parts = preg_split( '/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		$words = array();
		foreach ( $parts as $index => $part ) {
			if ( preg_match( '/[\p{L}\p{N}]/u', $part ) ) {
				$words[] = $index;
			}
		}
		$last_word = empty( $words ) ? -1 : end( $words );
		$component_start = true;
		foreach ( $parts as $index => $part ) {
			if ( ! preg_match( '/^([^\p{L}\p{N}]*)(.*?)([^\p{L}\p{N}]*)$/u', $part, $match ) || '' === $match[2] ) {
				if ( false !== strpos( $part, ':' ) || false !== strpos( $part, '|' ) ) {
					$component_start = true;
				}
				continue;
			}
			$word  = $match[2];
			$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $word, 'UTF-8' ) : strtolower( $word );
			$is_mixed = preg_match( '/\p{Lu}/u', $word ) && preg_match( '/\p{Ll}/u', $word ) && $word !== ucfirst( $lower );
			$is_upper = preg_match( '/\p{Lu}/u', $word ) && ! preg_match( '/\p{Ll}/u', $word );
			if ( ! $is_mixed && ! $is_upper ) {
				if ( in_array( $lower, $minor, true ) && ! $component_start && $index !== $last_word ) {
					$word = $lower;
				} else {
					$word = preg_replace_callback(
						'/\p{L}/u',
						function ( $letter ) {
							return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $letter[0], 'UTF-8' ) : strtoupper( $letter[0] );
						},
						$lower,
						1
					);
				}
			}
			$parts[ $index ] = $match[1] . $word . $match[3];
			$component_start = ( false !== strpos( $match[3], ':' ) || false !== strpos( $match[3], '|' ) );
		}
		return implode( '', $parts );
	}

	private static function normalise_known_acronyms( $text ) {
		foreach ( array( 'SEO', 'AI', 'API', 'FAQ', 'HTML', 'URL', 'UI', 'UX', 'CTA', 'CEO', 'CRM', 'CMS', 'PPC', 'ROI', 'SERP', 'UK', 'US', 'B2B' ) as $acronym ) {
			$text = preg_replace( '/\b' . preg_quote( $acronym, '/' ) . '\b/i', $acronym, $text );
		}
		$brands = array(
			'wordpress'   => 'WordPress',
			'woocommerce' => 'WooCommerce',
			'chatgpt'     => 'ChatGPT',
			'openai'      => 'OpenAI',
			'youtube'     => 'YouTube',
			'linkedin'    => 'LinkedIn',
			'tiktok'      => 'TikTok',
			'google'      => 'Google',
			'semrush'     => 'Semrush',
			'shopify'     => 'Shopify',
		);
		foreach ( $brands as $source => $canonical ) {
			$text = preg_replace( '/\b' . preg_quote( $source, '/' ) . '\b/i', $canonical, $text );
		}
		return $text;
	}

	// ------------------------------------------------------------------
	// Internals
	// ------------------------------------------------------------------

	private static function object( array $properties ) {
		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => array_keys( $properties ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Gemini responseSchema uses uppercase types and no additionalProperties.
	 */
	private static function gemini_schema( array $schema ) {
		$converted = array();
		foreach ( $schema as $key => $value ) {
			if ( $key === 'additionalProperties' ) {
				continue;
			}
			if ( $key === 'type' && is_string( $value ) ) {
				$converted['type'] = strtoupper( $value );
				continue;
			}
			if ( is_array( $value ) ) {
				if ( $key === 'properties' ) {
					$converted['properties'] = array();
					foreach ( $value as $prop => $prop_schema ) {
						$converted['properties'][ $prop ] = self::gemini_schema( $prop_schema );
					}
					continue;
				}
				if ( $key === 'items' ) {
					$converted['items'] = self::gemini_schema( $value );
					continue;
				}
			}
			$converted[ $key ] = $value;
		}
		return $converted;
	}

	/**
	 * Minimal JSON-schema validator (type/object/required/array/items/
	 * minItems/string/minLength/enum) — enough for our own schemas.
	 *
	 * @return true|array
	 */
	private static function validate_schema( array $schema, $data, $path ) {
		$errors = array();
		$type   = isset( $schema['type'] ) ? $schema['type'] : null;

		if ( $type === 'object' ) {
			if ( ! is_array( $data ) || ( count( $data ) > 0 && array_keys( $data ) === range( 0, count( $data ) - 1 ) ) ) {
				return array( "{$path}: expected object" );
			}
			foreach ( (array) ( isset( $schema['required'] ) ? $schema['required'] : array() ) as $required ) {
				if ( ! array_key_exists( $required, $data ) ) {
					$errors[] = "{$path}.{$required}: missing required property";
				}
			}
			foreach ( (array) ( isset( $schema['properties'] ) ? $schema['properties'] : array() ) as $prop => $prop_schema ) {
				if ( array_key_exists( $prop, $data ) ) {
					$result = self::validate_schema( $prop_schema, $data[ $prop ], "{$path}.{$prop}" );
					if ( $result !== true ) {
						$errors = array_merge( $errors, $result );
					}
				}
			}
		} elseif ( $type === 'array' ) {
			if ( ! is_array( $data ) ) {
				return array( "{$path}: expected array" );
			}
			if ( isset( $schema['minItems'] ) && count( $data ) < $schema['minItems'] ) {
				$errors[] = "{$path}: expected at least {$schema['minItems']} item(s)";
			}
			if ( isset( $schema['maxItems'] ) && count( $data ) > $schema['maxItems'] ) {
				$errors[] = "{$path}: expected at most {$schema['maxItems']} item(s)";
			}
			if ( isset( $schema['items'] ) ) {
				foreach ( $data as $i => $item ) {
					$result = self::validate_schema( $schema['items'], $item, "{$path}[{$i}]" );
					if ( $result !== true ) {
						$errors = array_merge( $errors, $result );
					}
				}
			}
		} elseif ( $type === 'string' ) {
			if ( ! is_string( $data ) ) {
				return array( "{$path}: expected string" );
			}
			if ( isset( $schema['minLength'] ) && strlen( trim( $data ) ) < $schema['minLength'] ) {
				$errors[] = "{$path}: string shorter than {$schema['minLength']}";
			}
			if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) && ! in_array( $data, $schema['enum'], true ) ) {
				$errors[] = "{$path}: expected one of " . implode( '|', $schema['enum'] );
			}
		}

		return empty( $errors ) ? true : $errors;
	}

	/**
	 * Strip Markdown code fences some models wrap output in.
	 */
	private static function strip_code_fences( $text ) {
		$text = trim( $text );
		if ( preg_match( '/^```[a-zA-Z]*\s*(.*?)\s*```$/s', $text, $m ) ) {
			return trim( $m[1] );
		}
		return $text;
	}
}
