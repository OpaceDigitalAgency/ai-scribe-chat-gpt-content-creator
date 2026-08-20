<?php
/**
 * Migration Service for AI-Scribe Plugin
 *
 * One-time, non-destructive migration from 2.6.2. Option-name
 * compatibility is preserved (`ab_prompts_content` including the
 * capital-K `Keywords_prompts` quirk, `ab_gpt_content_settings`,
 * `ab_gpt_ai_engine_settings`): existing user values are NEVER
 * overwritten; only missing keys are filled from the canonical defaults.
 * Runtime code additionally uses fallback reads (PromptManager's
 * get_prompt_library merges over defaults on every read), so migration
 * failure can not lose behaviour.
 *
 * @package AI_Scribe
 * @subpackage Core
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Scribe_Migration_Service {

	const MIGRATED_OPTION   = 'ai_scribe_v3_migrated';
	const MIGRATION_VERSION = '3.0.11';

	/**
	 * Whether the one-time migration found genuine 2.x data ('yes'/'no').
	 * Drives the onboarding notice copy: upgrade wording vs fresh-install
	 * wording (C-16-2).
	 */
	const FROM_V2_OPTION = 'ai_scribe_migrated_from_v2';
	const REMAP_NOTICE_OPTION = 'ai_scribe_model_remap_notice';

	/**
	 * Completion flag for the separate prompts -> Opace AI Hub copy. Deliberately
	 * its own option rather than a bump of MIGRATION_VERSION: the hub copy
	 * can only run when Opace AI Hub is present, so it has to be able to complete
	 * on a site whose option migration finished long ago.
	 */
	const HUB_MIGRATED_OPTION   = 'ai_scribe_hub_prompts_migrated';
	const HUB_MIGRATION_VERSION = '1';

	/**
	 * One-shot flag for maybe_refresh_hub_prompt_defaults(): moves untouched
	 * hub copies of superseded stock prompts to the current default wording.
	 */
	const HUB_REFRESH_OPTION  = 'ai_scribe_hub_prompts_refreshed';
	const HUB_REFRESH_VERSION = '2';

	/**
	 * Completion flag for the API-key -> Opace AI Hub copy. Its own option for the
	 * same reason as HUB_MIGRATED_OPTION: the hand-off can only run with the
	 * hub active, so it must be able to complete on a site whose option
	 * migration finished long ago (including 3.0.x sites that upgraded while
	 * the adapter still had a direct-send fallback for AI-Scribe-held keys).
	 */
	const HUB_KEYS_OPTION  = 'ai_scribe_hub_keys_migrated';
	const HUB_KEYS_VERSION = '1';

	/**
	 * The Opace AI Hub group migrated prompts are filed under, so a user opening
	 * Opace AI Hub's Prompt Library can see at a glance where they came from.
	 */
	const HUB_GROUP_NAME = 'AI-Scribe';

	/**
	 * ab_prompts_content key => wizard step, for the keys that drive a step.
	 * Mirrors AI_Scribe_Prompt_Manager's own step map; kept here so the
	 * migration does not need a prompt manager instance (it runs from
	 * admin_init as well as from maybe_migrate()).
	 *
	 * @var array
	 */
	private static $hub_step_keys = array(
		'title_prompts'      => 1,
		'Keywords_prompts'   => 2,
		'outline_prompts'    => 3,
		'intro_prompts'      => 4,
		'tagline_prompts'    => 5,
		'article_prompts'    => 6,
		'conclusion_prompts' => 7,
		'qa_prompts'         => 8,
		'meta_prompts'       => 9,
		'review_prompts'     => 10,
		'evaluate_prompts'   => 11,
	);

	/**
	 * Human titles for the migrated prompts. The title is also the identity
	 * used for the per-prompt existence check, so these strings are stable
	 * and deliberately not translated.
	 *
	 * @var array
	 */
	private static $hub_prompt_titles = array(
		'instructions_prompts' => 'AI-Scribe: Base instructions',
		'title_prompts'        => 'AI-Scribe: Article titles (step 1)',
		'Keywords_prompts'     => 'AI-Scribe: Keywords (step 2)',
		'outline_prompts'      => 'AI-Scribe: Outline (step 3)',
		'intro_prompts'        => 'AI-Scribe: Introduction (step 4)',
		'tagline_prompts'      => 'AI-Scribe: Tagline (step 5)',
		'article_prompts'      => 'AI-Scribe: Article body (step 6)',
		'conclusion_prompts'   => 'AI-Scribe: Conclusion (step 7)',
		'qa_prompts'           => 'AI-Scribe: Questions and answers (step 8)',
		'meta_prompts'         => 'AI-Scribe: Meta data (step 9)',
		'review_prompts'       => 'AI-Scribe: Revision (step 10)',
		'evaluate_prompts'     => 'AI-Scribe: Evaluation (step 11)',
	);

	/**
	 * Run the migration once. Safe to call on every load.
	 *
	 * @param AI_Scribe_Prompt_Manager|null $prompt_manager Source of canonical defaults.
	 * @param AI_Scribe_Config_Manager|null $config_manager Encrypts legacy plaintext keys.
	 * @return bool True when a migration ran (or had already run).
	 */
	public static function maybe_migrate( $prompt_manager = null, $config_manager = null ) {
		if ( get_option( self::MIGRATED_OPTION ) === self::MIGRATION_VERSION ) {
			return true;
		}

		// Record whether this is genuinely an upgrade from 2.x BEFORE anything
		// below writes the very options the check looks for. A fresh install
		// has none of these yet (seeding happens in migrate_prompts); a 2.6.2
		// site always carries at least one. The onboarding notice branches its
		// copy on this flag (C-16-2: "your settings have carried over" is only
		// true when something existed to carry over).
		if ( false === get_option( self::FROM_V2_OPTION ) ) {
			$has_v2_data = false !== get_option( 'ab_prompts_content' )
				|| false !== get_option( 'ab_gpt_content_settings' )
				|| false !== get_option( 'ab_gpt_ai_engine_settings' )
				|| false !== get_option( 'ab_api_key' )
				|| false !== get_option( 'ab_check_Arr' );
			update_option( self::FROM_V2_OPTION, $has_v2_data ? 'yes' : 'no', false );
		}

		self::migrate_prompts( $prompt_manager );
		self::migrate_content_settings();
		self::migrate_engine_settings();
		self::migrate_engine_keys( $config_manager );

		update_option( self::MIGRATED_OPTION, self::MIGRATION_VERSION );

		// Prompts belong in Opace AI Hub now, so copy them across as part of the
		// same upgrade. Runs after migrate_prompts() so any gap-filled key is
		// included, and is a no-op without the hub.
		self::maybe_migrate_prompts_to_hub();

		// API keys belong in Opace AI Hub too: the hub is the only send path, so a
		// key left solely in AI-Scribe's options would never be used. Runs
		// after migrate_engine_keys() so 2.6.2 plaintext has been normalised.
		self::maybe_migrate_keys_to_hub( $config_manager );

		return true;
	}

	/**
	 * Copy AI-Scribe's own API keys into Opace AI Hub's key store.
	 *
	 * From v3 every provider request goes through the hub's public API
	 * (ai_core()->send_text_request() / generate_image()), which reads keys
	 * from its own `ai_core_settings` option — a key that only exists in
	 * AI-Scribe's options (the 2.6.2 / interim-3.x locations) would otherwise
	 * leave the hub reporting "not configured" and every generation failing.
	 *
	 * Safety, mirroring maybe_migrate_prompts_to_hub():
	 *
	 * - NON-DESTRUCTIVE. AI-Scribe's stored keys are read, never deleted;
	 *   they remain as the at-rest backup.
	 * - NEVER OVERWRITES THE USER. A provider that already has a key in the
	 *   hub keeps it; only empty hub slots are filled.
	 * - HUB-GATED AND IDEMPOTENT. Without the hub nothing happens (no flag,
	 *   no error); with it, a completion flag short-circuits, and the flag is
	 *   only set once every carried key is verified present in the hub.
	 * - VALUES ARE NEVER LOGGED. Only provider names appear in diagnostics.
	 *
	 * At-rest encryption is the hub's own: update_option('ai_core_settings')
	 * runs through AI_Core_Settings' sanitize/encrypt filters, exactly as the
	 * hub's settings screen does.
	 *
	 * @param AI_Scribe_Config_Manager|null $config_manager Optional injected
	 *        manager (tests); a fresh instance is built otherwise.
	 * @return bool True when the hand-off is complete (now or previously).
	 */
	public static function maybe_migrate_keys_to_hub( $config_manager = null ) {
		if ( get_option( self::HUB_KEYS_OPTION ) === self::HUB_KEYS_VERSION ) {
			return true;
		}

		if ( ! function_exists( 'ai_core' ) ) {
			// No hub, no hand-off, no error. Re-checked on the next admin
			// page load with Opace AI Hub active (see Hub_Prompt_Reader::register).
			return false;
		}

		if ( ! class_exists( 'AI_Scribe_Config_Manager' ) ) {
			return false;
		}
		$config = ( $config_manager instanceof AI_Scribe_Config_Manager )
			? $config_manager
			: new AI_Scribe_Config_Manager();

		// AI-Scribe-side storage (decrypted by ConfigManager on read).
		$scribe_keys = array(
			'openai'    => 'ai_engine.api_key',
			'anthropic' => 'ai_engine.anthropic_api_key',
			'gemini'    => 'ai_engine.gemini_api_key',
			'grok'      => 'ai_engine.grok_api_key',
		);

		// Read through the hub's option filter, so stored ciphertext arrives
		// decrypted and the merged write below re-encrypts every key.
		$hub = get_option( 'ai_core_settings', array() );
		if ( ! is_array( $hub ) ) {
			$hub = array();
		}

		$carried = array();
		foreach ( $scribe_keys as $provider => $config_key ) {
			$hub_field = $provider . '_api_key';
			if ( ! empty( $hub[ $hub_field ] ) ) {
				continue; // Hub already has this provider — the user's value wins.
			}
			$plain = $config->get( $config_key, '' );
			if ( ! is_string( $plain ) || trim( $plain ) === '' ) {
				continue;
			}
			$hub[ $hub_field ] = trim( $plain );
			$carried[]         = $provider;
		}

		if ( ! empty( $carried ) ) {
			update_option( 'ai_core_settings', $hub );

			// The flag is set from what is actually in the hub, not from what
			// this run believes it wrote.
			$stored = get_option( 'ai_core_settings', array() );
			foreach ( $carried as $provider ) {
				if ( ! is_array( $stored ) || empty( $stored[ $provider . '_api_key' ] ) ) {
					self::log( 'hub key hand-off: ' . $provider . ' key did not persist; leaving the flag unset so the next run retries.' );
					return false;
				}
			}
			self::log( 'hub key hand-off: complete — carried key(s) for ' . implode( ', ', $carried ) . ' into Opace AI Hub.' );
		}

		update_option( self::HUB_KEYS_OPTION, self::HUB_KEYS_VERSION, false );

		return true;
	}

	/**
	 * Move hub copies of superseded stock prompts to the current wording.
	 *
	 * The 3.0.x hub migration copied the then-current stock defaults into
	 * Opace AI Hub's library and pointed the wizard steps at them, so an improved
	 * default in get_default_prompts() would otherwise never reach a site
	 * that migrated earlier: the hub copy wins the precedence chain. A hub
	 * prompt whose content still reads exactly as a superseded stock default
	 * was never edited by the user and is safe to move forward; anything the
	 * user touched differs and is left alone.
	 *
	 * One-shot (own flag), hub-gated, and hooked from
	 * AI_Scribe_Hub_Prompt_Reader::register() so it also runs on sites whose
	 * option migration finished before Opace AI Hub was activated.
	 *
	 * @return bool True when the refresh is complete (now or previously).
	 */
	public static function maybe_refresh_hub_prompt_defaults() {
		if ( get_option( self::HUB_REFRESH_OPTION ) === self::HUB_REFRESH_VERSION ) {
			return true;
		}

		if ( ! class_exists( 'AI_Scribe_Hub_Prompt_Reader' ) || ! AI_Scribe_Hub_Prompt_Reader::library_writable() ) {
			return false;
		}

		if ( ! function_exists( 'ai_scribe_get_container' ) ) {
			return false;
		}
		try {
			$prompt_manager = ai_scribe_get_container()->get( 'prompt_manager' );
		} catch ( Exception $e ) {
			return false;
		}
		if ( ! ( $prompt_manager instanceof AI_Scribe_Prompt_Manager ) ) {
			return false;
		}

		$defaults = $prompt_manager->get_default_prompts();
		$group_id = 0;
		foreach ( AI_Scribe_Hub_Prompt_Reader::get_groups() as $group ) {
			if ( $group['name'] === self::HUB_GROUP_NAME ) {
				$group_id = (int) $group['id'];
				break;
			}
		}
		if ( $group_id <= 0 ) {
			// Nothing was migrated into the hub, so there is nothing to refresh.
			update_option( self::HUB_REFRESH_OPTION, self::HUB_REFRESH_VERSION, false );
			return true;
		}

		$all_done = true;
		foreach ( self::superseded_defaults() as $key => $old_texts ) {
			if ( empty( self::$hub_prompt_titles[ $key ] ) || empty( $defaults[ $key ] ) ) {
				continue;
			}
			$prompt_id = AI_Scribe_Hub_Prompt_Reader::find_prompt_id_by_title( self::$hub_prompt_titles[ $key ], $group_id );
			if ( $prompt_id <= 0 ) {
				continue;
			}
			$prompt = AI_Scribe_Hub_Prompt_Reader::find_prompt( $prompt_id );
			if ( null === $prompt ) {
				continue;
			}
			$matches_old = false;
			foreach ( $old_texts as $old_text ) {
				if ( trim( $prompt['content'] ) === trim( AI_Scribe_Prompt_Manager::normalise_stored_text( $old_text ) ) ) {
					$matches_old = true;
					break;
				}
			}
			if ( ! $matches_old ) {
				continue; // User-edited: leave it exactly as it is.
			}
			$updated = AI_Scribe_Hub_Prompt_Reader::update_prompt_content( $prompt_id, $defaults[ $key ] );
			if ( is_wp_error( $updated ) ) {
				self::log( 'hub prompt refresh: "' . self::$hub_prompt_titles[ $key ] . '" could not be updated — ' . $updated->get_error_message() );
				$all_done = false;
			}
		}

		if ( $all_done ) {
			update_option( self::HUB_REFRESH_OPTION, self::HUB_REFRESH_VERSION, false );
		}

		return $all_done;
	}

	/**
	 * Copy the user's `ab_prompts_content` prompts into Opace AI Hub's library.
	 *
	 * Opace AI Hub is a mandatory dependency from v3 and is where a user manages
	 * prompts, so an upgraded 2.6.2 site should find its own wording waiting
	 * there rather than a set of factory defaults.
	 *
	 * SAFETY — every one of these is deliberate:
	 *
	 * - NON-DESTRUCTIVE. `ab_prompts_content` is read and never written,
	 *   never deleted. It stays as the backup and as level 3 of the read
	 *   chain, so a bad run on a customer site loses nothing and can simply
	 *   be run again.
	 * - IDEMPOTENT, TWICE OVER. A completion flag short-circuits a finished
	 *   migration, and every individual prompt is additionally matched by
	 *   title within the AI-Scribe group before being written. A run that
	 *   died halfway therefore resumes instead of duplicating.
	 * - HUB-GATED. No Opace AI Hub, or an Opace AI Hub without the public save API, and
	 *   nothing happens at all: no write, no flag, no error, no notice.
	 * - NEVER DESTRUCTIVE ON FAILURE. A prompt that fails to save is logged
	 *   through the debug-gated logger and the run carries on with the next
	 *   one. Nothing is rolled back, and the completion flag is only set once
	 *   every prompt is accounted for — so the next run finishes the job.
	 * - NEVER OVERWRITES THE USER. An existing hub prompt of the same title
	 *   is adopted, not rewritten, and a wizard step the user has already
	 *   pointed at some other Opace AI Hub prompt keeps that choice.
	 *
	 * Stored text is normalised through
	 * AI_Scribe_Prompt_Manager::normalise_stored_text() — the single existing
	 * implementation — so 2.6.2's compounding addslashes escaping is peeled
	 * exactly once on the way in.
	 *
	 * @return bool True when the copy is complete (now or previously).
	 */
	public static function maybe_migrate_prompts_to_hub() {
		if ( get_option( self::HUB_MIGRATED_OPTION ) === self::HUB_MIGRATION_VERSION ) {
			return true;
		}

		if ( ! class_exists( 'AI_Scribe_Hub_Prompt_Reader' ) || ! AI_Scribe_Hub_Prompt_Reader::library_writable() ) {
			// No hub, no migration, no error. It will be picked up the next
			// time an admin page loads with Opace AI Hub active.
			return false;
		}

		$saved = get_option( 'ab_prompts_content', array() );
		if ( ! is_array( $saved ) || empty( $saved ) ) {
			// Nothing of the user's to carry across; treat as done so this
			// does not re-check on every admin page load forever.
			update_option( self::HUB_MIGRATED_OPTION, self::HUB_MIGRATION_VERSION, false );
			return true;
		}

		$group_id = AI_Scribe_Hub_Prompt_Reader::ensure_group(
			self::HUB_GROUP_NAME,
			'Prompts carried over from AI-Scribe. Edit them here and AI-Scribe will use them.'
		);
		if ( is_wp_error( $group_id ) ) {
			self::log( 'hub prompt migration: could not create the "' . self::HUB_GROUP_NAME . '" group — ' . $group_id->get_error_message() );
			return false;
		}

		$step_prompt_ids = array();
		$expected        = 0;
		$carried         = 0;

		foreach ( self::$hub_prompt_titles as $key => $title ) {
			// `user_instructions` is intentionally absent from this map: it
			// is the user's Custom Instructions, not a step prompt, and
			// AI_Scribe_Prompt_Manager::get_user_instructions() reads it
			// straight from ab_prompts_content on every run.
			if ( ! isset( $saved[ $key ] ) || ! is_string( $saved[ $key ] ) || trim( $saved[ $key ] ) === '' ) {
				continue;
			}

			$expected++;

			$content = class_exists( 'AI_Scribe_Prompt_Manager' )
				? AI_Scribe_Prompt_Manager::normalise_stored_text( $saved[ $key ] )
				: $saved[ $key ];

			if ( trim( $content ) === '' ) {
				$expected--;
				continue;
			}

			// Per-prompt existence check: the second half of the idempotency
			// guarantee, and what makes a half-finished run resumable.
			$prompt_id = AI_Scribe_Hub_Prompt_Reader::find_prompt_id_by_title( $title, $group_id );

			if ( $prompt_id <= 0 ) {
				$prompt_id = AI_Scribe_Hub_Prompt_Reader::write_prompt( $title, $content, $group_id );

				if ( is_wp_error( $prompt_id ) ) {
					// Log and carry on — one bad prompt must not cost the
					// user the other eleven.
					self::log( 'hub prompt migration: "' . $title . '" could not be saved — ' . $prompt_id->get_error_message() );
					continue;
				}

				$carried++;
			}

			if ( isset( self::$hub_step_keys[ $key ] ) ) {
				$step_prompt_ids[ self::$hub_step_keys[ $key ] ] = (int) $prompt_id;
			}
		}

		self::apply_migrated_steps( $step_prompt_ids );

		// The flag is set from what is actually in the hub, not from what this
		// run believes it did: a failed write leaves the count short and the
		// flag unset, so the next run picks up exactly the missing prompts.
		$stored = count( self::migrated_titles_present( $group_id ) );
		if ( $stored < $expected ) {
			self::log( 'hub prompt migration: ' . $stored . ' of ' . $expected . ' prompts stored; leaving the flag unset so the next run resumes.' );
			return false;
		}

		update_option( self::HUB_MIGRATED_OPTION, self::HUB_MIGRATION_VERSION, false );
		self::log( 'hub prompt migration: complete — ' . $stored . ' prompt(s) in the "' . self::HUB_GROUP_NAME . '" group, ' . $carried . ' written this run.' );

		return true;
	}

	/**
	 * Which of the migrated prompt titles currently exist in the hub group.
	 *
	 * @param int $group_id Opace AI Hub group id.
	 * @return array Titles present.
	 */
	private static function migrated_titles_present( $group_id ) {
		$present = array();

		foreach ( self::$hub_prompt_titles as $title ) {
			if ( AI_Scribe_Hub_Prompt_Reader::find_prompt_id_by_title( $title, $group_id ) > 0 ) {
				$present[] = $title;
			}
		}

		return $present;
	}

	/**
	 * Point each wizard step at its migrated Opace AI Hub prompt, so the hub copy
	 * is what AI-Scribe actually sends and `ab_prompts_content` becomes the
	 * fallback rather than the live value.
	 *
	 * A step the user has already mapped to a prompt that still exists in
	 * Opace AI Hub is left exactly as it is — this fills gaps, it does not impose.
	 * An entry pointing at a prompt that has since been deleted is repaired,
	 * which is what makes a crash-and-resume leave a consistent map rather
	 * than eleven steps quietly falling back.
	 *
	 * @param array $step_prompt_ids step => Opace AI Hub prompt id.
	 * @return void
	 */
	private static function apply_migrated_steps( $step_prompt_ids ) {
		if ( empty( $step_prompt_ids ) ) {
			return;
		}

		$existing = AI_Scribe_Hub_Prompt_Reader::get_applied_map();

		foreach ( $step_prompt_ids as $step => $prompt_id ) {
			if ( isset( $existing[ $step ] ) && null !== AI_Scribe_Hub_Prompt_Reader::find_prompt( $existing[ $step ] ) ) {
				continue;
			}

			$applied = AI_Scribe_Hub_Prompt_Reader::apply( $step, $prompt_id );
			if ( is_wp_error( $applied ) ) {
				self::log( 'hub prompt migration: step ' . $step . ' could not be pointed at prompt ' . $prompt_id . ' — ' . $applied->get_error_message() );
			}
		}
	}

	/**
	 * Debug-gated diagnostic logging, via the plugin's existing logger.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private static function log( $message ) {
		if ( function_exists( 'ai_scribe_debug_log' ) ) {
			ai_scribe_debug_log( 'AI-Scribe migration: ' . $message );
		}
	}

	/**
	 * ab_prompts_content: keep every user-edited prompt verbatim; fill
	 * only missing/blank keys from the canonical defaults.
	 */
	private static function migrate_prompts( $prompt_manager ) {
		if ( ! ( $prompt_manager instanceof AI_Scribe_Prompt_Manager ) ) {
			return;
		}
		$defaults = $prompt_manager->get_default_prompts();
		$saved    = get_option( 'ab_prompts_content', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$merged = $saved;
		foreach ( $defaults as $key => $value ) {
			if ( ! isset( $merged[ $key ] ) || ! is_string( $merged[ $key ] ) || trim( $merged[ $key ] ) === '' ) {
				$merged[ $key ] = $value;
			}
		}

		// A stored prompt that still reads exactly as a superseded stock
		// default was never edited by the user, so it is safe to move to the
		// improved wording. Anything the user has touched differs from the
		// old text and is left alone.
		foreach ( self::superseded_defaults() as $key => $old_texts ) {
			if ( ! isset( $merged[ $key ], $defaults[ $key ] ) || ! is_string( $merged[ $key ] ) ) {
				continue;
			}
			foreach ( $old_texts as $old_text ) {
				if ( trim( $merged[ $key ] ) === trim( $old_text ) ) {
					$merged[ $key ] = $defaults[ $key ];
					break;
				}
			}
		}

		if ( $merged !== $saved ) {
			update_option( 'ab_prompts_content', $merged );
		}
	}

	/**
	 * Earlier stock defaults, verbatim, keyed by prompt option. Used only to
	 * recognise an untouched stock prompt so it can follow the default
	 * forward; a user-edited prompt never matches and is never rewritten.
	 *
	 * @return array option-key => array of previous default texts.
	 */
	private static function superseded_defaults() {
		return array(
			'article_prompts'  => array(
				// Shipped up to 3.0.9: substituting the composed tagline
				// instruction into "Add a tagline called \"[The Tagline]\""
				// nested one instruction inside another (C-12-1).
				'Write my article using only HTML tags directly in the output without enclosing the content in any ``` or code block syntax. Include a H1 tag for the [Title] main title. Add a tagline called "[The Tagline]" [above/below]. Include the introduction: [Intro].
                Then write each section using my outline, making sure each section heading is formatted as a [Heading Tag] tag: [Heading].
                Strictly randomise the <p> count under each [Heading Tag] tag. Some sections should have just 1 <p> tag, while others may have up to 4 <p> tags. No two consecutive [Heading Tag] tag or section should have the same number of <p> tags. Vary the word count of each paragraph by at least 50%.
                Each section should provide a unique perspective on the topic and provide value over and above what\'s already available. You must not include a conclusion heading or section.
                Each section must be explored in detail. To achieve this, you must include all possible known features, benefits, arguments, analysis and whatever is needed to explore the topic to the best of your knowledge.
                SEO optimise the article for the [Selected Keywords]. Don\'t include lists.
                Do not add any additional notes, markup or code before the H1 or after the last paragraph. Do not add any additional notes, markup or code before the H1 or after the last paragraph.',
			),
			'meta_prompts'     => array(
				'Create a single SEO friendly meta title and meta description. Based this on the "[Title]" article title and the [Selected Keywords]. Create the meta data in the [Language] language.  Follow SEO best practices and make the meta data catchy to attract clicks. Do not add any additional markup such as ***',
			),
			'evaluate_prompts' => array(
				'Create a HTML table giving a strict/evaluation of each question below based on everything above. Give the HTML table 4 columns: [STATUS], [QUESTION], [EVALUATION], [RATIONALE]. For [EVALUATION], give a PASS, FAIL or IMPROVE response. Add a CSS class name to each row and cell with the corresponding response value.
            For the [STATUS] column, don\'t add anything. For [RATIONALE], explain your reasoning. Order the rows according to  [EVALUATION]. All answers must be factual.
            Then giving examples like phrases or topics add these within curly brackets. Do not add the column labels within square brackets in your response. The questions are:
Is the length of the article over 500 words and an adequate length compared to similar articles?
Is the articlview dee optimised for certain keywords or phrases? What are these?
Is the article well-written and easy to read?
Does the article have any spelling or grammar issues?
Does the article provide an original, interesting and engaging perspective on the topic?',
				// The complete-prompts.json seed shipped up to 3.0.9: asked
				// for a prose HTML table and literal curly-bracket examples,
				// which surfaced as "{560 words}" in the evaluate report.
				'Create a clean HTML table evaluating the article above. Use 4 columns: STATUS (leave empty), QUESTION, EVALUATION, RATIONALE. For EVALUATION, respond with PASS, FAIL, or IMPROVE. Add CSS class names to rows based on evaluation (pass, fail, improve). Order rows by evaluation result. Include specific examples in curly brackets {like this}. Evaluate these questions: 1) Is the article over 500 words and adequate length? 2) Is the article well-optimized for specific keywords/phrases? Which ones? 3) Is the article well-written and readable? 4) Are there spelling or grammar issues? 5) Does it provide an original, engaging perspective? Generate only the HTML table without markdown formatting or code blocks.',
			),
		);
	}

	/**
	 * ab_gpt_content_settings: fill missing keys with 2.6.2 defaults.
	 */
	private static function migrate_content_settings() {
		$defaults = array(
			'language'          => 'English',
			'writing_style'     => 'Business',
			'writing_tone'      => 'Professional',
			'number_of_heading' => '5',
			'Heading_tag'       => 'H2',
			'cs_list'           => '',
		);
		$saved    = get_option( 'ab_gpt_content_settings', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$merged = array_merge( $defaults, $saved );
		if ( $merged !== $saved ) {
			update_option( 'ab_gpt_content_settings', $merged );
		}
	}

	/**
	 * ab_gpt_ai_engine_settings: fill sampling defaults; retire models
	 * that no longer exist upstream (the 2.6.2 silent Claude remap and
	 * stale ids are gone. An untouched legacy gpt-4o-mini seed follows the
	 * Hub default, while an explicitly saved model remains untouched.
	 */
	private static function migrate_engine_settings() {
		$defaults = array(
			'model'            => '',
			'temp'             => 0.5,
			'top_p'            => 0.5,
			'freq_pent'        => 0.2,
			'Presence_penalty' => 0.2,
			'n'                => 1,
		);
		$saved    = get_option( 'ab_gpt_ai_engine_settings', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$merged = array_merge( $defaults, $saved );

		$retired_models = array(
			'gpt-4.5-preview'           => 'gpt-4o-mini',
			'gpt-4.5'                   => 'gpt-4o-mini',
			'gpt-3.5-turbo'             => 'gpt-4o-mini',
			// 2.6.2 listed this id but silently served claude-3-5-sonnet-20241022
			// (the §3.4 remap); the id itself never existed upstream.
			'claude-3-5-sonnet-20250514' => 'claude-sonnet-4-5',
		);
		$remap_from = '';
		if ( isset( $merged['model'], $retired_models[ $merged['model'] ] ) ) {
			$remap_from      = $merged['model'];
			$merged['model'] = $retired_models[ $merged['model'] ];
		}

		// Versions before 3.2.27 wrote gpt-4o-mini into the grouped option
		// even on a fresh install. The individual ab_model option is created
		// only when a person saves the model picker, so its absence lets us
		// distinguish that seed from an intentional GPT-4o Mini choice.
		if ( 'gpt-4o-mini' === $merged['model'] && false === get_option( 'ab_model', false ) ) {
			$hub              = get_option( 'ai_core_settings', array() );
			$provider         = is_array( $hub ) && ! empty( $hub['default_provider'] ) ? (string) $hub['default_provider'] : '';
			$hub_model        = is_array( $hub ) && isset( $hub['provider_models'][ $provider ] ) ? (string) $hub['provider_models'][ $provider ] : '';
			if ( '' !== $hub_model ) {
				$remap_from      = $remap_from ?: $merged['model'];
				$merged['model'] = $hub_model;
			}
		}

		if ( '' !== $remap_from && $remap_from !== $merged['model'] ) {
			$merged['model_pre_v3'] = $remap_from;
			// §15.1: the remap must be visible, never silent. Rendered as a
			// dismissible admin notice by AI_Scribe_Onboarding_Notice.
			update_option(
				self::REMAP_NOTICE_OPTION,
				array(
					'from' => $remap_from,
					'to'   => $merged['model'],
				),
				false
			);
		}

		if ( $merged !== $saved ) {
			update_option( 'ab_gpt_ai_engine_settings', $merged );
		}
	}

	/**
	 * Encrypt legacy plaintext API keys at rest (§14.3 meets §15.1).
	 *
	 * 2.6.2 stored `api_key` / `anthropic_api_key` as plaintext inside
	 * `ab_gpt_ai_engine_settings`; interim 3.x builds also used the
	 * individual `ab_api_key` / `ab_anthropic_api_key` options in various
	 * pre-`aisenc1:` formats. Every stored key is normalised to the
	 * versioned ciphertext so nothing sensitive remains readable in the
	 * options table. Values already carrying the marker are left alone,
	 * so re-running is a no-op.
	 *
	 * @param AI_Scribe_Config_Manager|null $config_manager Optional injected
	 *        manager (tests); a fresh instance is built otherwise so its
	 *        cache reflects the options as they exist mid-migration.
	 */
	private static function migrate_engine_keys( $config_manager = null ) {
		if ( ! class_exists( 'AI_Scribe_Config_Manager' ) ) {
			return;
		}
		$config = ( $config_manager instanceof AI_Scribe_Config_Manager )
			? $config_manager
			: new AI_Scribe_Config_Manager();

		$sensitive = array( 'api_key', 'anthropic_api_key', 'openai_api_key', 'claude_api_key', 'gemini_api_key', 'grok_api_key' );

		// 1. Keys inside the grouped engine option (the 2.6.2 location).
		$saved = get_option( 'ab_gpt_ai_engine_settings', array() );
		if ( is_array( $saved ) ) {
			$decrypted = $config->get_group( 'ai_engine' );
			$changed   = false;
			foreach ( $sensitive as $key ) {
				if ( ! isset( $saved[ $key ] ) || ! is_string( $saved[ $key ] ) || $saved[ $key ] === '' ) {
					continue;
				}
				if ( strpos( $saved[ $key ], 'aisenc1:' ) === 0 ) {
					continue;
				}
				$plain = isset( $decrypted[ $key ] ) && is_string( $decrypted[ $key ] ) ? $decrypted[ $key ] : '';
				if ( $plain === '' ) {
					continue;
				}
				$saved[ $key ] = $config->encrypt_for_storage( $plain );
				$changed       = true;
			}
			if ( $changed ) {
				update_option( 'ab_gpt_ai_engine_settings', $saved );
			}
		}

		// 2. Individual key options (interim 3.x AJAX save location).
		$individual = array(
			'ab_api_key'           => 'ai_engine.api_key',
			'ab_anthropic_api_key' => 'ai_engine.anthropic_api_key',
		);
		foreach ( $individual as $option_name => $config_key ) {
			$raw = get_option( $option_name, '' );
			if ( ! is_string( $raw ) || $raw === '' || strpos( $raw, 'aisenc1:' ) === 0 ) {
				continue;
			}
			$plain = $config->get( $config_key, '' );
			if ( ! is_string( $plain ) || $plain === '' ) {
				continue;
			}
			update_option( $option_name, $config->encrypt_for_storage( $plain ) );
		}

		// 3. Mirror group keys into the individual options the runtime reads
		// FIRST (ConfigManager::get short-circuits `ai_engine.api_key` /
		// `anthropic_api_key` to ab_api_key / ab_anthropic_api_key and never
		// falls back to the group when the individual option is absent).
		// A pure 2.6.2 site only has the group option, so without this
		// mirror its keys would be invisible post-upgrade (found by the
		// §15.1 upgrade simulation). store_key() keeps both in sync for
		// every later save, so this stays consistent going forward.
		$mirror = array(
			'api_key'           => 'ab_api_key',
			'anthropic_api_key' => 'ab_anthropic_api_key',
		);
		$saved  = get_option( 'ab_gpt_ai_engine_settings', array() );
		if ( is_array( $saved ) ) {
			foreach ( $mirror as $group_key => $option_name ) {
				$existing = get_option( $option_name, '' );
				if ( is_string( $existing ) && $existing !== '' ) {
					continue; // Individual option present — precedence keeps it.
				}
				if ( isset( $saved[ $group_key ] ) && is_string( $saved[ $group_key ] ) && $saved[ $group_key ] !== '' ) {
					update_option( $option_name, $saved[ $group_key ] );
				}
			}
		}
	}
}
