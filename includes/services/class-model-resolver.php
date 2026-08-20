<?php
/**
 * Which model a generation will actually use.
 *
 * This existed in three places at once — the generation service, the admin
 * localisation, and the settings screen — and they disagreed. The wizard
 * displayed "GPT-5 · OpenAI" on a site holding only a Gemini key while
 * generation correctly ran against Gemini, and earlier it showed
 * "No model selected yet" beside a populated picker. A displayed model that
 * is not the model about to be billed is worse than no display at all, so
 * there is now one answer and every caller asks for it here.
 *
 * @package AI_Scribe
 * @subpackage Services
 * @since 3.0.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Scribe_Model_Resolver {

	/**
	 * Ids that name another modality. A provider's model list carries image,
	 * speech, video, music, embedding and tooling models; none of them can
	 * write an article, so none may be chosen as a text default.
	 */
	const NOT_TEXT = '/(^|-)(image|imagen|tts|audio|speech|embedding|embed|veo|lyria|computer-use|live|rerank|guard|nano-banana)(-|$)/i';

	/** Still-image model families supported by Opace AI Hub's image providers. */
	const IS_IMAGE = '/(^|-)(image|imagen|dall-e|nano-banana)(-|$)/i';

	/** Image-adjacent families that cannot create a new still image. */
	const NOT_IMAGE = '/(^|-)(veo|lyria|edit|upscale|tts|audio)(-|$)/i';

	/**
	 * The model a generation will use, given whatever the caller has stored.
	 *
	 * @param string $stored_model Model saved by the plugin, may be empty or stale.
	 * @return string Model id. Empty only when no provider is configured at all.
	 */
	public static function resolve( $stored_model = '' ) {
		$stored_model = trim( (string) $stored_model );
		if ( '' !== $stored_model && self::is_usable( $stored_model ) ) {
			return $stored_model;
		}

		$hub = get_option( 'ai_core_settings', array() );
		if ( ! is_array( $hub ) ) {
			return '';
		}

		// A model the hub has been pointed at, when its provider still has a key.
		$provider = isset( $hub['default_provider'] ) ? (string) $hub['default_provider'] : '';
		$chosen   = isset( $hub['provider_models'][ $provider ] ) ? (string) $hub['provider_models'][ $provider ] : '';
		if ( '' !== $chosen && self::is_usable( $chosen ) ) {
			return $chosen;
		}
		if ( ! empty( $hub['provider_models'] ) && is_array( $hub['provider_models'] ) ) {
			foreach ( $hub['provider_models'] as $candidate ) {
				if ( is_string( $candidate ) && '' !== $candidate && self::is_usable( $candidate ) ) {
					return $candidate;
				}
			}
		}

		// Nothing chosen: the best model the account can actually serve.
		foreach ( self::providers() as $candidate_provider ) {
			if ( empty( $hub[ $candidate_provider . '_api_key' ] ) ) {
				continue;
			}
			$live = self::best_live_model( $candidate_provider );
			if ( '' !== $live ) {
				return $live;
			}
			if ( class_exists( 'AICore\\Registry\\ModelRegistry' ) ) {
				$preferred = AICore\Registry\ModelRegistry::getPreferredTextModel( $candidate_provider );
				if ( is_string( $preferred ) && '' !== $preferred ) {
					return $preferred;
				}
			}
		}

		return '';
	}

	/**
	 * Is this model's provider actually configured?
	 *
	 * @param string $model Model id.
	 * @return bool
	 */
	public static function is_usable( $model ) {
		$model = trim( (string) $model );
		if ( '' === $model ) {
			return false;
		}

		$provider = self::provider_of( $model );
		if ( '' === $provider ) {
			return false;
		}

		$hub = get_option( 'ai_core_settings', array() );
		return is_array( $hub ) && ! empty( $hub[ $provider . '_api_key' ] );
	}

	/**
	 * Provider that serves a model id.
	 *
	 * @param string $model Model id.
	 * @return string Provider id, or '' when unrecognised.
	 */
	public static function provider_of( $model ) {
		$model = (string) $model;

		if ( class_exists( 'AICore\\Registry\\ModelRegistry' ) ) {
			$provider = (string) AICore\Registry\ModelRegistry::getProvider( $model );
			if ( '' !== $provider ) {
				return $provider;
			}
		}

		// Not registered yet: infer from the id the same way the hub does.
		if ( preg_match( '/^claude-/i', $model ) ) {
			return 'anthropic';
		}
		if ( preg_match( '/^(gemini-|gemma-|imagen-|models\/gemini-|nano-banana)/i', $model ) ) {
			return 'gemini';
		}
		if ( preg_match( '/^grok-/i', $model ) ) {
			return 'grok';
		}
		if ( preg_match( '/^(gpt-|o[0-9]|chatgpt-|codex-|dall-e)/i', $model ) ) {
			return 'openai';
		}

		return '';
	}

	/**
	 * Best text model a provider account can actually serve, from its LIVE list.
	 *
	 * ModelRegistry::getPreferredModel() returns a value compiled into the
	 * bundled registry, so it can name a model the account does not have —
	 * which is how a Gemini-only site was pointed at gemini-3-pro-preview,
	 * a model Google no longer serves. The live list is whatever the settings
	 * screen already cached for this key, so this costs no extra request.
	 *
	 * @param string $provider Provider id.
	 * @return string Model id, or '' when no live list is available.
	 */
	public static function best_live_model( $provider ) {
		$models = self::live_models( $provider );
		if ( empty( $models ) ) {
			return '';
		}

		$usable = array();
		foreach ( $models as $id ) {
			$id = (string) $id;
			if ( '' !== $id && ! preg_match( self::NOT_TEXT, $id ) ) {
				$usable[] = $id;
			}
		}
		if ( empty( $usable ) ) {
			return '';
		}
		if ( class_exists( 'AICore\\Registry\\ModelRegistry' ) ) {
			$preferred = AICore\Registry\ModelRegistry::getPreferredTextModel( (string) $provider, $usable );
			if ( is_string( $preferred ) && '' !== $preferred ) {
				return $preferred;
			}
		}

		self::set_main_family( $usable );
		usort( $usable, array( __CLASS__, 'compare_ids' ) );

		return $usable[0];
	}

	/**
	 * Best image model a provider account can actually serve, from its LIVE list.
	 *
	 * @param string $provider Provider id.
	 * @return string Model id, or '' when the live list holds no image model.
	 */
	public static function best_live_image_model( $provider ) {
		$models = self::live_models( $provider );
		if ( empty( $models ) ) {
			return '';
		}

		$usable = array();
		foreach ( $models as $id ) {
			$id = (string) $id;
			// Image models, but not the video, music or editing-only variants.
			if ( '' !== $id
				&& preg_match( self::IS_IMAGE, $id )
				&& ! preg_match( self::NOT_IMAGE, $id ) ) {
				$usable[] = $id;
			}
		}
		if ( empty( $usable ) ) {
			return '';
		}
		if ( class_exists( 'AICore\\Registry\\ModelRegistry' ) ) {
			$preferred = AICore\Registry\ModelRegistry::getPreferredImageModel( (string) $provider, $usable );
			if ( is_string( $preferred ) && '' !== $preferred ) {
				return $preferred;
			}
		}

		self::set_main_family( $usable );
		usort( $usable, array( __CLASS__, 'compare_ids' ) );

		return $usable[0];
	}

	/**
	 * The live model list cached for a provider's current key.
	 *
	 * @param string $provider Provider id.
	 * @return array Model ids; empty when nothing has been fetched yet.
	 */
	public static function live_models( $provider ) {
		$hub = get_option( 'ai_core_settings', array() );
		$key = is_array( $hub ) && ! empty( $hub[ $provider . '_api_key' ] )
			? (string) $hub[ $provider . '_api_key' ]
			: '';
		if ( '' === $key ) {
			return array();
		}

		$models = get_transient( self::cache_key( $provider, $key ) );

		return is_array( $models ) ? $models : array();
	}

	/**
	 * Cache key shared by discovery, the resolver and image availability.
	 *
	 * Mock and real provider lists must never share a transient. Otherwise a
	 * development site that turns the mock off can retain a tiny canned list
	 * and incorrectly hide real image models for up to an hour.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      Provider key (only its hash enters the name).
	 * @return string
	 */
	public static function cache_key( $provider, $key ) {
		$mock_active = defined( 'AI_SCRIBE_MOCK' ) && AI_SCRIBE_MOCK
			&& defined( 'AI_SCRIBE_AUTOMATED_TEST' ) && AI_SCRIBE_AUTOMATED_TEST;
		$context = $mock_active ? 'mock' : 'live';

		return 'ai_scribe_models_v2_' . $context . '_' . sanitize_key( $provider ) . '_' . substr( md5( (string) $key ), 0, 8 );
	}

	/**
	 * Newest-first comparison for two model ids of the same provider.
	 *
	 * Sorting on the largest number in an id read release dates as versions
	 * ("deep-research-pro-preview-12-2025" ranked as version 12, above
	 * gemini-3.6) and parameter counts as versions (gemma-4-31b as 31). The
	 * main family is whichever leading token dominates the provider's own
	 * live list, so no family name is written into the plugin.
	 *
	 * @param string $a First id.
	 * @param string $b Second id.
	 * @return int
	 */
	public static function compare_ids( $a, $b ) {
		$fa = self::family_rank( $a );
		$fb = self::family_rank( $b );
		if ( $fa !== $fb ) {
			return $fb <=> $fa;
		}

		$va = self::version_of( $a );
		$vb = self::version_of( $b );
		if ( $va !== $vb ) {
			return $vb <=> $va;
		}

		// Shorter ids are the plain release; longer ones are variants.
		$la = strlen( (string) $a );
		$lb = strlen( (string) $b );
		if ( $la !== $lb ) {
			return $la <=> $lb;
		}

		return strcmp( (string) $a, (string) $b );
	}

	/**
	 * Numeric version carried by a model id, ignoring release dates.
	 *
	 * @param string $id Model id.
	 * @return float
	 */
	public static function version_of( $id ) {
		$stem = (string) $id;
		$stem = preg_replace( '/-\d{4}-\d{2}-\d{2}(?=-|$)/', '', $stem );   // -2025-08-07
		$stem = preg_replace( '/-\d{2}-\d{2}-\d{4}(?=-|$)/', '', $stem );   // -08-07-2025
		$stem = preg_replace( '/-\d{8}(?=-|$)/', '', $stem );               // -20250929
		$stem = preg_replace( '/-\d{2}-\d{4}(?=-|$)/', '', $stem );         // -12-2025
		$stem = preg_replace( '/-(19|20)\d{2}(?=-|$)/', '', $stem );        // -2026

		if ( ! preg_match( '/(?:^|-)(\d+)(?:[.-](\d+))?(?![0-9a-z])/', $stem, $m ) ) {
			return 0.0;
		}

		$version = (float) $m[1] + ( isset( $m[2] ) ? (float) ( '0.' . $m[2] ) : 0.0 );

		return $version >= 1000 ? 0.0 : $version;
	}

	/**
	 * Leading alphabetic token of a model id, e.g. "gemini" or "gemma".
	 *
	 * @param string $id Model id.
	 * @return string
	 */
	public static function family_of( $id ) {
		return preg_match( '/^([a-z]+)/i', (string) $id, $m ) ? strtolower( $m[1] ) : '';
	}

	/** @var string|null Family that dominates the list currently being sorted. */
	private static $main_family = null;

	/**
	 * Record which family dominates a list, so compare_ids() can rank the
	 * provider's main line above its side families.
	 *
	 * @param array $ids Model ids about to be sorted.
	 * @return void
	 */
	public static function set_main_family( array $ids ) {
		$counts = array();
		foreach ( $ids as $id ) {
			$family = self::family_of( $id );
			if ( '' !== $family ) {
				$counts[ $family ] = isset( $counts[ $family ] ) ? $counts[ $family ] + 1 : 1;
			}
		}
		arsort( $counts );
		self::$main_family = empty( $counts ) ? null : key( $counts );
	}

	/**
	 * 1 when an id belongs to the dominant family, 0 otherwise.
	 *
	 * @param string $id Model id.
	 * @return int
	 */
	private static function family_rank( $id ) {
		if ( null === self::$main_family ) {
			return 0;
		}

		return self::family_of( $id ) === self::$main_family ? 1 : 0;
	}

	/**
	 * Providers the hub supports, registry first so the list stays dynamic.
	 *
	 * @return array
	 */
	private static function providers() {
		if ( class_exists( 'AICore\\Registry\\ModelRegistry' ) ) {
			$providers = AICore\Registry\ModelRegistry::getSupportedProviders();
			if ( is_array( $providers ) && ! empty( $providers ) ) {
				return $providers;
			}
		}

		return array( 'openai', 'anthropic', 'gemini' );
	}
}
