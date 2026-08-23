<?php
/**
 * Cost Estimator for AI-Scribe Plugin
 *
 * Pre-generation cost estimates per step and per article, and actual-cost
 * calculation from provider usage blocks. Prefers the standalone Opace AI Hub
 * plugin's Pricing class when active (shared pricing data across Opace
 * plugins); otherwise falls back to a bundled table.
 *
 * Prices are USD per 1M tokens. Filter `ai_scribe_pricing` lets sites
 * override without a plugin update; never hardcode model behaviour
 * elsewhere — unknown models fall back to provider-family defaults.
 *
 * @package AI_Scribe
 * @subpackage Services
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Scribe_Cost_Estimator {

	/**
	 * Cached-input price as a fraction of fresh input (Anthropic cache
	 * reads are ~10% of input price; OpenAI automatic caching ~50-90%
	 * discount — 0.1 is used for the "with caching" estimate as the
	 * conversation prefix dominates).
	 */
	const CACHED_INPUT_FRACTION = 0.1;

	/**
	 * Typical output-token budgets per wizard step (estimation only).
	 *
	 * @var array
	 */
	private static $step_output_budget = array(
		1  => 250,
		2  => 250,
		3  => 350,
		4  => 450,
		5  => 120,
		// Includes both bounded corrective rewrites when the first body misses
		// the helpfulness floor.
		6  => 9000,
		7  => 300,
		8  => 900,
		9  => 180,
		10 => 3500,
		11 => 900,
	);

	/**
	 * Bundled pricing table (USD per 1M tokens), keyed by model prefix.
	 * Reference points verified Aug 2026 (REFACTOR.md §4) plus the
	 * still-listed legacy models from Opace AI Hub's pricing data.
	 *
	 * @var array
	 */
	private static $pricing = array(
		// OpenAI GPT-5.6 family (GA 2026-07-09)
		'gpt-5.6-sol'           => array(
			'input'  => 5.00,
			'output' => 30.00,
		),
		'gpt-5.6-terra'         => array(
			'input'  => 2.50,
			'output' => 15.00,
		),
		'gpt-5.6-luna'          => array(
			'input'  => 1.00,
			'output' => 6.00,
		),
		'gpt-5-nano'            => array(
			'input'  => 0.05,
			'output' => 0.40,
		),
		'gpt-5-mini'            => array(
			'input'  => 0.25,
			'output' => 2.00,
		),
		'gpt-5'                 => array(
			'input'  => 1.25,
			'output' => 10.00,
		),
		'gpt-4.1-mini'          => array(
			'input'  => 0.40,
			'output' => 1.60,
		),
		'o4-mini'               => array(
			'input'  => 1.10,
			'output' => 4.40,
		),
		'gpt-4.1'               => array(
			'input'  => 2.00,
			'output' => 8.00,
		),
		'gpt-4o-mini'           => array(
			'input'  => 0.15,
			'output' => 0.60,
		),
		'gpt-4o'                => array(
			'input'  => 2.50,
			'output' => 10.00,
		),
		'o3'                    => array(
			'input'  => 20.00,
			'output' => 80.00,
		),
		// Anthropic (Aug 2026)
		'claude-fable-5'        => array(
			'input'  => 10.00,
			'output' => 50.00,
		),
		'claude-opus-5'         => array(
			'input'  => 5.00,
			'output' => 25.00,
		),
		'claude-sonnet-5'       => array(
			'input'  => 3.00,
			'output' => 15.00,
		),
		'claude-haiku-4-5'      => array(
			'input'  => 1.00,
			'output' => 5.00,
		),
		'claude-opus-4'         => array(
			'input'  => 15.00,
			'output' => 75.00,
		),
		'claude-sonnet-4-5'     => array(
			'input'  => 3.00,
			'output' => 15.00,
		),
		'claude-sonnet-4'       => array(
			'input'  => 3.00,
			'output' => 15.00,
		),
		'claude-3-7-sonnet'     => array(
			'input'  => 3.00,
			'output' => 15.00,
		),
		'claude-3-5-haiku'      => array(
			'input'  => 0.80,
			'output' => 4.00,
		),
		// Google
		'gemini-3.6-flash'      => array(
			'input'  => 0.30,
			'output' => 2.50,
		),
		'gemini-3'              => array(
			'input'  => 1.25,
			'output' => 10.00,
		),
		'gemini-2.5-pro'        => array(
			'input'  => 1.25,
			'output' => 10.00,
		),
		'gemini-2.5-flash-lite' => array(
			'input'  => 0.10,
			'output' => 0.40,
		),
		'gemini-2.5-flash'      => array(
			'input'  => 0.30,
			'output' => 2.50,
		),
		// Provider-family fallbacks
		'gpt-'                  => array(
			'input'  => 2.50,
			'output' => 10.00,
		),
		'claude-'               => array(
			'input'  => 3.00,
			'output' => 15.00,
		),
		'gemini-'               => array(
			'input'  => 0.30,
			'output' => 2.50,
		),
	);

	/**
	 * @var AI_Scribe_Logger|null
	 */
	private $logger;

	public function __construct( $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Pricing for a model (USD per 1M tokens).
	 *
	 * @param string $model
	 * @return array {input, output, cached_input}
	 */
	public function get_pricing( $model ) {
		$model   = (string) $model;
		$pricing = null;

		// Prefer the standalone Opace AI Hub plugin's pricing when active.
		if ( class_exists( 'AI_Core_Pricing' ) ) {
			try {
				$core = AI_Core_Pricing::get_instance();
				if ( method_exists( $core, 'get_model_pricing' ) ) {
					$found = $core->get_model_pricing( $model );
					if ( is_array( $found ) && isset( $found['input'], $found['output'] ) ) {
						$pricing = array(
							'input'  => (float) $found['input'],
							'output' => (float) $found['output'],
						);
					}
				}
			} catch ( Exception $e ) {
				// Fall through to the bundled table.
			}
		}

		if ( $pricing === null ) {
			$table = self::$pricing;
			if ( function_exists( 'apply_filters' ) ) {
				$table = apply_filters( 'ai_scribe_pricing', $table );
			}
			// Exact match first, then longest-prefix match.
			if ( isset( $table[ $model ] ) ) {
				$pricing = $table[ $model ];
			} else {
				$best     = null;
				$best_len = 0;
				foreach ( $table as $prefix => $price ) {
					if ( strpos( $model, $prefix ) === 0 && strlen( $prefix ) > $best_len ) {
						$best     = $price;
						$best_len = strlen( $prefix );
					}
				}
				$pricing = $best !== null ? $best : array(
					'input'  => 3.00,
					'output' => 15.00,
				);
			}
		}

		$pricing['cached_input'] = round( $pricing['input'] * self::CACHED_INPUT_FRACTION, 6 );
		return $pricing;
	}

	/**
	 * Actual cost from a provider usage block.
	 *
	 * @param string $model
	 * @param array  $usage {prompt_tokens, completion_tokens, cached_tokens?}
	 * @return float USD
	 */
	public function actual_cost( $model, array $usage ) {
		$pricing = $this->get_pricing( $model );
		$input   = isset( $usage['prompt_tokens'] ) ? (int) $usage['prompt_tokens'] : 0;
		$output  = isset( $usage['completion_tokens'] ) ? (int) $usage['completion_tokens'] : 0;
		$cached  = isset( $usage['cached_tokens'] ) ? (int) $usage['cached_tokens'] : 0;
		$fresh   = max( 0, $input - $cached );

		return $this->round_usd(
			( $fresh * $pricing['input']
				+ $cached * $pricing['cached_input']
				+ $output * $pricing['output'] ) / 1000000
		);
	}

	/**
	 * Estimate a single step.
	 *
	 * @param string $model
	 * @param int    $step
	 * @param int    $prompt_tokens Estimated input tokens (prompt + thread).
	 * @param bool   $cached        Whether the thread prefix is cache-warm.
	 * @return array {input_tokens, output_tokens, usd, usd_without_caching}
	 */
	public function estimate_step( $model, $step, $prompt_tokens, $cached = true ) {
		$pricing = $this->get_pricing( $model );
		$output  = isset( self::$step_output_budget[ (int) $step ] ) ? self::$step_output_budget[ (int) $step ] : 500;

		$fresh_usd = ( $prompt_tokens * $pricing['input'] + $output * $pricing['output'] ) / 1000000;
		// With caching, only ~the new step prompt is fresh input; the shared
		// prefix is billed at the cached rate.
		$fresh_portion  = min( $prompt_tokens, 400 );
		$cached_portion = max( 0, $prompt_tokens - $fresh_portion );
		$cached_usd     = ( $fresh_portion * $pricing['input']
			+ $cached_portion * $pricing['cached_input']
			+ $output * $pricing['output'] ) / 1000000;

		return array(
			'input_tokens'        => (int) $prompt_tokens,
			'output_tokens'       => (int) $output,
			'usd'                 => $this->round_usd( $cached ? $cached_usd : $fresh_usd ),
			'usd_without_caching' => $this->round_usd( $fresh_usd ),
		);
	}

	/**
	 * Whole-article estimate (wizard: 11 steps threaded; express: 1 call).
	 *
	 * @param string $model
	 * @param string $mode  wizard|express
	 * @param int    $base_prompt_tokens System + settings prompt size.
	 * @return array Contract §6 shape.
	 */
	public function estimate_article( $model, $mode = 'wizard', $base_prompt_tokens = 900 ) {
		$pricing = $this->get_pricing( $model );

		if ( $mode === 'express' ) {
			// Whole-article output plus both permitted corrective rewrites.
			$output = 13000;
			$usd    = ( $base_prompt_tokens * $pricing['input'] + $output * $pricing['output'] ) / 1000000;
			return array(
				'model'   => $model,
				'pricing' => $this->pricing_for_contract( $pricing ),
				'steps'   => array(
					'express' => array(
						'input_tokens'  => $base_prompt_tokens,
						'output_tokens' => $output,
						'usd'           => $this->round_usd( $usd ),
					),
				),
				'total'   => array(
					'input_tokens'        => $base_prompt_tokens,
					'output_tokens'       => $output,
					'usd'                 => $this->round_usd( $usd ),
					'usd_without_caching' => $this->round_usd( $usd ),
					'cache_savings_usd'   => 0.0,
				),
			);
		}

		$steps           = array();
		$running_context = $base_prompt_tokens;
		$totals          = array(
			'input'     => 0,
			'output'    => 0,
			'usd'       => 0.0,
			'usd_fresh' => 0.0,
		);

		foreach ( array_keys( self::$step_output_budget ) as $step ) {
			if ( $step === 10 ) {
				continue; // Review is client-side assembly, no API call by default.
			}
			$estimate                = $this->estimate_step( $model, $step, $running_context, true );
			$steps[ (string) $step ] = array(
				'input_tokens'  => $estimate['input_tokens'],
				'output_tokens' => $estimate['output_tokens'],
				'usd'           => $estimate['usd'],
			);
			$totals['input']        += $estimate['input_tokens'];
			$totals['output']       += $estimate['output_tokens'];
			$totals['usd']          += $estimate['usd'];
			$totals['usd_fresh']    += $estimate['usd_without_caching'];
			// Each step's output joins the thread context for the next.
			$running_context += $estimate['output_tokens'] + 150;
		}

		return array(
			'model'   => $model,
			'pricing' => $this->pricing_for_contract( $pricing ),
			'steps'   => $steps,
			'total'   => array(
				'input_tokens'        => $totals['input'],
				'output_tokens'       => $totals['output'],
				'usd'                 => $this->round_usd( $totals['usd'] ),
				'usd_without_caching' => $this->round_usd( $totals['usd_fresh'] ),
				'cache_savings_usd'   => $this->round_usd( $totals['usd_fresh'] - $totals['usd'] ),
			),
		);
	}

	/**
	 * Rough token estimate for a text blob (~4 chars/token).
	 *
	 * @param string $text
	 * @return int
	 */
	public function estimate_tokens( $text ) {
		return (int) ceil( strlen( (string) $text ) / 4 );
	}

	private function pricing_for_contract( array $pricing ) {
		return array(
			'input_per_mtok_usd'        => $pricing['input'],
			'output_per_mtok_usd'       => $pricing['output'],
			'cached_input_per_mtok_usd' => $pricing['cached_input'],
		);
	}

	private function round_usd( $usd ) {
		return round( (float) $usd, 6 );
	}
}
