<?php
/**
 * Model parameter-schema inference for live-fetched model families.
 *
 * REFACTOR.md §13.3: models registered from a provider's live /models list
 * get only the generic provider default schema (Max Tokens 2048, temperature)
 * — no reasoning-effort control for o-series/GPT-5.x, no extended-thinking
 * toggle for Claude 4.x, an Anthropic wire-key that is plain wrong
 * (max_output_tokens), and an output default far too small for the body and
 * Express steps. The Opace AI Hub is frozen, so the inference lives here in
 * the AI-Scribe layer: family rules produce a corrected parameter schema and
 * re-register it into ModelRegistry, which both the settings UI panel and the
 * provider request builders read.
 *
 * Curated seed entries keep their hand-written parameters where present —
 * inference only fills gaps and fixes the known-wrong bits.
 *
 * The sampling knobs live here too. No provider default schema declares
 * top_p, frequency_penalty or presence_penalty, and the Opace AI Hub providers
 * build their request payload strictly from the schema — so an undeclared
 * parameter is dropped on the floor no matter what the settings screen
 * saved. Declaring them per family is what makes the controls appear and
 * makes the saved values reach the model.
 *
 * @package AI_Scribe
 * @since   3.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Scribe_Model_Schema_Inference {

	/**
	 * Per-request memo of model ids already processed.
	 *
	 * @var array<string,bool>
	 */
	private static $applied = array();

	/**
	 * Infer and register a corrected parameter schema for one model.
	 *
	 * Safe to call repeatedly; no-ops when ModelRegistry is unavailable or
	 * the model is unknown to it.
	 *
	 * @param string $model Model id.
	 * @return void
	 */
	public static function apply( $model ) {
		$model = trim( (string) $model );
		if ( '' === $model || isset( self::$applied[ $model ] ) || ! class_exists( 'AICore\\Registry\\ModelRegistry' ) ) {
			return;
		}
		self::$applied[ $model ] = true;

		try {
			$config = AICore\Registry\ModelRegistry::getModelConfig( $model );
			if ( ! is_array( $config ) || empty( $config['provider'] ) ) {
				return;
			}
			if ( isset( $config['category'] ) && in_array( $config['category'], array( 'image', 'embedding', 'audio' ), true ) ) {
				return;
			}

			$schema   = isset( $config['parameters'] ) && is_array( $config['parameters'] ) ? $config['parameters'] : array();
			$inferred = self::infer( $model, (string) $config['provider'], $schema );

			if ( $inferred['parameters'] !== $schema || ( isset( $inferred['endpoint'] ) && $inferred['endpoint'] !== ( $config['endpoint'] ?? '' ) ) ) {
				$update = array(
					'provider'   => $config['provider'],
					'parameters' => $inferred['parameters'],
				);
				if ( isset( $inferred['endpoint'] ) ) {
					$update['endpoint'] = $inferred['endpoint'];
				}
				AICore\Registry\ModelRegistry::registerModel( $model, $update );
			}
		} catch ( Exception $e ) {
			// Inference must never break generation — leave the registry as-is.
			unset( $e );
		}
	}

	/**
	 * Family rules. Returns ['parameters' => schema, 'endpoint' => ?string].
	 *
	 * @param string $model    Model id.
	 * @param string $provider Provider id.
	 * @param array  $schema   Current registry parameter schema.
	 * @return array
	 */
	private static function infer( $model, $provider, array $schema ) {
		$out      = $schema;
		$endpoint = null;
		$id       = strtolower( $model );

		if ( 'openai' === $provider ) {
			$is_reasoning = (bool) preg_match( '/^(o[0-9]|gpt-5)/', $id );
			if ( ! $is_reasoning ) {
				// Sampling family (GPT-4.1, GPT-4o, …): Top P and the two
				// penalties are real chat-completions parameters. Declaring
				// them is what makes them reach the wire at all — the Opace AI Hub
				// providers build their payload from the schema and silently
				// drop any option the schema does not name.
				self::add_top_p( $out, 'top_p' );
				self::add_penalties( $out, 'frequency_penalty', 'presence_penalty' );
			} else {
				// Reasoning family: no sampling params on the wire.
				unset( $out['temperature'], $out['top_p'] );
				// o1-mini never accepted reasoning effort; everything newer does.
				if ( ! isset( $out['reasoning_effort'] ) && 0 !== strpos( $id, 'o1' ) ) {
					$out['reasoning_effort'] = self::select_parameter(
						array(
							'low'    => 'Low',
							'medium' => 'Medium',
							'high'   => 'High',
						),
						'medium',
						'reasoning.effort',
						'Reasoning Effort',
						'Higher effort raises quality, cost and latency.'
					);
				}
				$out['max_tokens'] = self::number_parameter(
					1,
					self::pick_max( $schema, 0 === strpos( $id, 'gpt-5' ) ? 128000 : 100000 ),
					self::pick_default( $schema, 16384 ),
					'max_output_tokens',
					'Max Output Tokens',
					'Upper bound on generated tokens. Long-form steps (body, Express) request the model maximum automatically.'
				);
				$endpoint = 'responses';
			}
		} elseif ( 'anthropic' === $provider ) {
			// The 5 generation rejects any non-default temperature/top_p/top_k
			// with a 400 on every request, and raises the output cap to 128k.
			// Earlier families keep sampling and the 64k cap.
			$ai_scribe_no_sampling = (bool) preg_match(
				'/claude-(fable-5|mythos-5|mythos-preview|opus-5|opus-4-8|opus-4-7|sonnet-5)/',
				$id
			);
			$ai_scribe_output_cap  = $ai_scribe_no_sampling ? 128000 : 64000;

			// Anthropic wire name is max_tokens — the generic provider default
			// schema says max_output_tokens (rejected) and advertises the
			// context window (200k) rather than the output cap.
			$out['max_tokens'] = self::number_parameter(
				1,
				min( self::pick_max( $schema, $ai_scribe_output_cap ), $ai_scribe_output_cap ),
				self::pick_default( $schema, 8192 ),
				'max_tokens',
				'Max Output Tokens',
				'Upper bound on generated tokens. Long-form steps request the model maximum automatically.'
			);
			if ( $ai_scribe_no_sampling ) {
				// Declaring these would put them on the wire and 400 the request.
				unset( $out['temperature'], $out['top_p'] );
			} else {
				if ( isset( $out['temperature'] ) && is_array( $out['temperature'] ) ) {
					$out['temperature']['max'] = 1;
				}
				// Anthropic accepts top_p on these families; it has no frequency
				// or presence penalty and rejects unknown body fields outright,
				// so the penalties are deliberately not declared here.
				self::add_top_p( $out, 'top_p' );
			}
			// Extended thinking: Claude 3.7 Sonnet and every Claude 4.x model.
			if ( preg_match( '/claude-(3-7|sonnet-4|opus-4|haiku-4|4)/', $id ) && ! isset( $out['extended_thinking'] ) ) {
				$out['extended_thinking'] = self::select_parameter(
					array(
						''        => 'Off',
						'enabled' => 'On',
					),
					'',
					'thinking.type',
					'Extended Thinking',
					'Lets the model reason before answering. Temperature is fixed at 1 while enabled.'
				);
				$out['thinking_budget']   = self::number_parameter(
					1024,
					32000,
					null,
					'thinking.budget_tokens',
					'Thinking Budget (tokens)',
					'Only used when Extended Thinking is on. Must stay below Max Output Tokens.'
				);
			}
		} elseif ( 'gemini' === $provider ) {
			$out['max_tokens'] = self::number_parameter(
				1,
				self::pick_max( $schema, 65536 ),
				self::pick_default( $schema, 8192 ),
				'generationConfig.maxOutputTokens',
				'Max Output Tokens'
			);
			// Google's 3.x guidance is explicit: "temperature, top_p, and top_k
			// are no longer recommended for all Gemini 3.x models. Remove these
			// parameters from all requests." 2.x keeps them.
			$ai_scribe_gemini_3 = (bool) preg_match( '/^gemini-([3-9]|\d\d)/', $id );
			if ( $ai_scribe_gemini_3 ) {
				unset( $out['temperature'], $out['top_p'] );
			} else {
				self::add_top_p( $out, 'generationConfig.topP' );
				// frequencyPenalty / presencePenalty arrived with Gemini 2.x; the
				// 1.5 generationConfig rejects them.
				if ( preg_match( '/^gemini-2/', $id ) ) {
					self::add_penalties( $out, 'generationConfig.frequencyPenalty', 'generationConfig.presencePenalty' );
				}
			}
			if ( $ai_scribe_gemini_3 && ! isset( $out['thinking_level'] ) ) {
				$out['thinking_level'] = self::select_parameter(
					array(
						'minimal' => 'Minimal',
						'low'     => 'Low',
						'medium'  => 'Medium',
						'high'    => 'High',
					),
					'medium',
					// Live-verified wire shape: the API rejects a top-level
					// generationConfig.thinkingLevel ("Cannot find field");
					// the knob lives inside thinkingConfig.
					'generationConfig.thinkingConfig.thinkingLevel',
					'Thinking Level',
					'Gemini 3.x reasoning depth.'
				);
			}
		}

		$result = array( 'parameters' => $out );
		if ( null !== $endpoint ) {
			$result['endpoint'] = $endpoint;
		}
		return $result;
	}

	/**
	 * Keep a curated max when it is a real output cap; otherwise the family cap.
	 *
	 * @param array $schema   Existing schema.
	 * @param int   $family_max Family cap.
	 * @return int
	 */
	private static function pick_max( array $schema, $family_max ) {
		$existing = isset( $schema['max_tokens']['max'] ) ? (int) $schema['max_tokens']['max'] : 0;
		// The generic defaults (8192/2048/200000) are placeholders, not caps.
		if ( $existing > 0 && $existing !== 8192 && $existing !== 200000 && $existing >= $family_max ) {
			return $existing;
		}
		return max( $existing, $family_max );
	}

	/**
	 * Raise a too-small default output budget; §13.3 — 2048 starves the
	 * body/Express steps.
	 *
	 * @param array $schema  Existing schema.
	 * @param int   $minimum Sensible floor.
	 * @return int
	 */
	private static function pick_default( array $schema, $minimum ) {
		$existing = isset( $schema['max_tokens']['default'] ) ? (int) $schema['max_tokens']['default'] : 0;
		return max( $existing, $minimum );
	}

	/**
	 * Number parameter descriptor in the ModelRegistry shape.
	 *
	 * @param int|float   $min         Minimum.
	 * @param int|float   $max         Maximum.
	 * @param int|null    $default_val Default (null = none).
	 * @param string      $request_key Wire key (dot-nested supported).
	 * @param string      $label       UI label.
	 * @param string      $help        UI help text.
	 * @param int|float   $step        Control step; a step below 1 also tells
	 *                                 the Opace AI Hub providers to send a float.
	 * @return array
	 */
	private static function number_parameter( $min, $max, $default_val, $request_key, $label, $help = '', $step = 1 ) {
		return array(
			'type'        => 'number',
			'label'       => $label,
			'min'         => $min,
			'max'         => $max,
			'step'        => $step,
			'default'     => $default_val,
			'request_key' => $request_key,
			'help'        => $help,
		);
	}

	/**
	 * Declare Top P for a sampling model, mirroring how max_tokens is
	 * emitted. No default: an undeclared value must stay off the wire, so
	 * only a Top P the user actually set is ever sent.
	 *
	 * @param array  $out         Schema being built, by reference.
	 * @param string $request_key Wire key (dot-nested supported).
	 * @return void
	 */
	private static function add_top_p( array &$out, $request_key ) {
		if ( isset( $out['top_p'] ) ) {
			return; // Curated seed entry wins.
		}
		$out['top_p'] = self::number_parameter(
			0,
			1,
			null,
			$request_key,
			'Top P',
			'Nucleus sampling. Lower values narrow the model to the most likely wording. Usually tuned instead of temperature, not alongside it.',
			0.05
		);
	}

	/**
	 * Declare the frequency and presence penalties for a model family that
	 * accepts them. Both are off unless the user sets a value.
	 *
	 * @param array  $out                   Schema being built, by reference.
	 * @param string $frequency_request_key Wire key for the frequency penalty.
	 * @param string $presence_request_key  Wire key for the presence penalty.
	 * @return void
	 */
	private static function add_penalties( array &$out, $frequency_request_key, $presence_request_key ) {
		if ( ! isset( $out['frequency_penalty'] ) ) {
			$out['frequency_penalty'] = self::number_parameter(
				-2,
				2,
				null,
				$frequency_request_key,
				'Frequency Penalty',
				'Positive values discourage repeating the same wording. Leave blank for the provider default.',
				0.1
			);
		}
		if ( ! isset( $out['presence_penalty'] ) ) {
			$out['presence_penalty'] = self::number_parameter(
				-2,
				2,
				null,
				$presence_request_key,
				'Presence Penalty',
				'Positive values push the model towards introducing new topics. Leave blank for the provider default.',
				0.1
			);
		}
	}

	/**
	 * Select parameter descriptor (options as value => label map).
	 *
	 * @param array  $options     value => label.
	 * @param string $default_val Default value.
	 * @param string $request_key Wire key.
	 * @param string $label       UI label.
	 * @param string $help        UI help text.
	 * @return array
	 */
	private static function select_parameter( array $options, $default_val, $request_key, $label, $help = '' ) {
		return array(
			'type'        => 'select',
			'label'       => $label,
			'options'     => $options,
			'default'     => $default_val,
			'request_key' => $request_key,
			'help'        => $help,
		);
	}
}
