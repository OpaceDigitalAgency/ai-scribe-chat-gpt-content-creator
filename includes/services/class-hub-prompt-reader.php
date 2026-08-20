<?php
/**
 * Hub Prompt Reader for AI-Scribe Plugin
 *
 * A view over the Opace AI Hub's prompt library, plus the per-step "applied
 * prompt" mapping that lets a user drive an AI-Scribe wizard step from a
 * prompt they manage centrally in Opace AI Hub.
 *
 * Reading is the bulk of this class. The one write path is
 * write_prompt()/ensure_group(), used by the 2.6.2 migration to seed the
 * hub with the user's own prompts; both go through Opace AI Hub's public
 * save_prompt()/save_group() API and never touch its tables directly.
 *
 * AI-Scribe's own `ab_prompts_content` store is untouched by this class —
 * it remains the fallback, and nothing here ever deletes from either side.
 *
 * @package AI_Scribe
 * @subpackage Services
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Hub_Prompt_Reader
 *
 * Static helper: the hub is a global, and both the settings template and
 * the prompt manager need the same answers about it.
 */
class AI_Scribe_Hub_Prompt_Reader {

	/**
	 * Option holding the per-step applied-prompt map: step (1-11) => Opace AI Hub
	 * prompt id. A step with no entry uses AI-Scribe's own prompt.
	 */
	const APPLIED_OPTION = 'ai_scribe_hub_prompt_map';

	/**
	 * AJAX action for applying / reverting a step's Opace AI Hub prompt.
	 */
	const AJAX_APPLY = 'ai_scribe_apply_hub_prompt';

	/**
	 * Lowest and highest wizard step a hub prompt may be applied to.
	 */
	const MIN_STEP = 1;
	const MAX_STEP = 11;

	/**
	 * Per-request cache of the hub's prompt rows, keyed by id.
	 *
	 * @var array|null
	 */
	private static $prompt_index = null;

	/**
	 * Register the AJAX endpoint. Called from the plugin bootstrap
	 * (article_builder.php), matching AI_Scribe_Onboarding_Notice::register().
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_ajax_' . self::AJAX_APPLY, array( __CLASS__, 'handle_apply' ) );

		// The 2.6.2 -> Opace AI Hub prompt migration. It lives on this hook, and
		// not only inside AI_Scribe_Migration_Service::maybe_migrate(), so
		// that a site already flagged as migrated by an earlier 3.x build
		// still gets its prompts copied into the hub — and so that a site
		// where Opace AI Hub is activated after AI-Scribe is picked up the first
		// time an admin page loads with the hub present. The service keeps
		// its own completion flag, so this is a cheap option read once the
		// copy is done.
		if ( class_exists( 'AI_Scribe_Migration_Service' ) ) {
			// API keys first: the hub is the only send path, so a 2.6.2 (or
			// early-3.x) key still held only in AI-Scribe's options must land
			// in Opace AI Hub's key store even on sites already flagged as
			// migrated. One-shot, own flag, never overwrites a hub key.
			add_action( 'admin_init', array( 'AI_Scribe_Migration_Service', 'maybe_migrate_keys_to_hub' ), 19 );
			add_action( 'admin_init', array( 'AI_Scribe_Migration_Service', 'maybe_migrate_prompts_to_hub' ), 20 );
			// After the copy: move untouched hub copies of superseded stock
			// prompts to the current default wording (one-shot, own flag).
			add_action( 'admin_init', array( 'AI_Scribe_Migration_Service', 'maybe_refresh_hub_prompt_defaults' ), 21 );
		}
	}

	/**
	 * Is the Opace AI Hub plugin active? Same detection the Providers tab
	 * uses (templates/settings_template.php, AI_Scribe_Onboarding_Notice) —
	 * deliberately one mechanism, not two.
	 *
	 * @return bool
	 */
	public static function hub_active() {
		return class_exists( 'AI_Scribe_Onboarding_Notice' )
			&& AI_Scribe_Onboarding_Notice::hub_active();
	}

	/**
	 * Is the hub's prompt library readable? The hub exposes a public
	 * accessor (AI_Core_Prompt_Library::get_prompts()/get_groups()), so we
	 * never touch its tables directly. No accessor means no library.
	 *
	 * @return bool
	 */
	public static function library_available() {
		return self::hub_active() && class_exists( 'AI_Core_Prompt_Library' );
	}

	/**
	 * Can prompts be written into the hub? Requires the public save API
	 * added to Opace AI Hub alongside this integration, so an older Opace AI Hub
	 * degrades to read-only rather than erroring.
	 *
	 * @return bool
	 */
	public static function library_writable() {
		if ( ! self::library_available() ) {
			return false;
		}

		return method_exists( 'AI_Core_Prompt_Library', 'save_prompt' )
			&& method_exists( 'AI_Core_Prompt_Library', 'save_group' );
	}

	/**
	 * The hub's prompt library instance, or null when unavailable.
	 *
	 * @return AI_Core_Prompt_Library|null
	 */
	private static function library() {
		if ( ! self::library_available() ) {
			return null;
		}
		try {
			return AI_Core_Prompt_Library::get_instance();
		} catch ( Exception $e ) {
			ai_scribe_debug_log( 'AI-Scribe hub prompt reader: could not load Opace AI Hub prompt library — ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Every prompt in the hub's library, normalised to the fields this
	 * plugin uses. Empty array when the hub is absent — never an error.
	 *
	 * @return array List of {id, title, content, group_id, group_name, type}.
	 */
	public static function get_prompts() {
		$library = self::library();
		if ( null === $library ) {
			return array();
		}

		$rows = $library->get_prompts();
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$prompts = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				continue;
			}
			$prompts[] = array(
				'id'         => (int) $row['id'],
				'title'      => isset( $row['title'] ) ? (string) $row['title'] : '',
				'content'    => self::normalise( isset( $row['content'] ) ? $row['content'] : '' ),
				'group_id'   => isset( $row['group_id'] ) ? (int) $row['group_id'] : 0,
				'group_name' => ! empty( $row['group_name'] ) ? (string) $row['group_name'] : '',
				'type'       => ! empty( $row['type'] ) ? (string) $row['type'] : 'text',
			);
		}

		return $prompts;
	}

	/**
	 * Undo the escaping that stored prompt text carries.
	 *
	 * Opace AI Hub saves prompt content through wp_kses_post() on slashed $_POST,
	 * so a prompt typed in its own library screen lands on disk with the same
	 * artefacts a 2.6.2 `ab_prompts_content` value has: backslash-escaped
	 * quotes and `&` encoded as `&amp;`. AI_Scribe_Prompt_Manager already owns
	 * the one implementation that peels those, and this delegates to it rather
	 * than repeating the logic — one normalisation, applied on read only, with
	 * the hub's stored row left exactly as Opace AI Hub wrote it.
	 *
	 * @param mixed $value Stored text.
	 * @return string
	 */
	private static function normalise( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		if ( class_exists( 'AI_Scribe_Prompt_Manager' ) ) {
			return AI_Scribe_Prompt_Manager::normalise_stored_text( $value );
		}

		return $value;
	}

	/**
	 * The hub's groups, as {id, name, description, count}. Empty array when
	 * the hub is absent.
	 *
	 * @return array
	 */
	public static function get_groups() {
		$library = self::library();
		if ( null === $library ) {
			return array();
		}

		$rows = $library->get_groups();
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$groups = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				continue;
			}
			$groups[] = array(
				'id'          => (int) $row['id'],
				'name'        => isset( $row['name'] ) ? (string) $row['name'] : '',
				'description' => isset( $row['description'] ) ? (string) $row['description'] : '',
				'count'       => isset( $row['count'] ) ? (int) $row['count'] : 0,
			);
		}

		return $groups;
	}

	/**
	 * The hub's prompts arranged for display: group label => prompt list,
	 * groups in name order, ungrouped prompts last.
	 *
	 * @return array
	 */
	public static function get_prompts_by_group() {
		$prompts = self::get_prompts();
		if ( empty( $prompts ) ) {
			return array();
		}

		$ungrouped_label = __( 'Ungrouped', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' );

		// Seed with every group the hub knows about, so an empty group is
		// still visible and the ordering follows the hub's own ordering.
		$grouped = array();
		foreach ( self::get_groups() as $group ) {
			if ( '' !== $group['name'] ) {
				$grouped[ $group['name'] ] = array();
			}
		}

		foreach ( $prompts as $prompt ) {
			$label = '' !== $prompt['group_name'] ? $prompt['group_name'] : $ungrouped_label;
			if ( ! isset( $grouped[ $label ] ) ) {
				$grouped[ $label ] = array();
			}
			$grouped[ $label ][] = $prompt;
		}

		// Drop groups the hub reports but which hold nothing — an empty
		// heading is noise on a settings screen.
		foreach ( array_keys( $grouped ) as $label ) {
			if ( empty( $grouped[ $label ] ) ) {
				unset( $grouped[ $label ] );
			}
		}

		// Ungrouped always sorts last.
		if ( isset( $grouped[ $ungrouped_label ] ) ) {
			$ungrouped = $grouped[ $ungrouped_label ];
			unset( $grouped[ $ungrouped_label ] );
			$grouped[ $ungrouped_label ] = $ungrouped;
		}

		return $grouped;
	}

	/**
	 * The saved step => Opace AI Hub prompt id map, filtered to real steps.
	 *
	 * @return array int step => int prompt id
	 */
	public static function get_applied_map() {
		$saved = get_option( self::APPLIED_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			return array();
		}

		$map = array();
		foreach ( $saved as $step => $prompt_id ) {
			$step      = (int) $step;
			$prompt_id = (int) $prompt_id;
			if ( $step >= self::MIN_STEP && $step <= self::MAX_STEP && $prompt_id > 0 ) {
				$map[ $step ] = $prompt_id;
			}
		}

		return $map;
	}

	/**
	 * The Opace AI Hub prompt applied to a step, or null.
	 *
	 * Returns null — so the caller falls back to AI-Scribe's own prompt —
	 * whenever the hub is inactive, the prompt has been deleted in Opace AI Hub,
	 * or its content is empty. Deactivating Opace AI Hub must never break a run.
	 *
	 * @param int $step Wizard step 1-11.
	 * @return string|null Prompt content, or null to fall back.
	 */
	public static function get_applied_prompt( $step ) {
		$step = (int) $step;
		$map  = self::get_applied_map();
		if ( ! isset( $map[ $step ] ) ) {
			return null;
		}

		$prompt = self::find_prompt( $map[ $step ] );
		if ( null === $prompt || '' === trim( $prompt['content'] ) ) {
			return null;
		}

		return $prompt['content'];
	}

	/**
	 * One prompt by id, from the per-request index.
	 *
	 * @param int $prompt_id Opace AI Hub prompt id.
	 * @return array|null
	 */
	public static function find_prompt( $prompt_id ) {
		$prompt_id = (int) $prompt_id;
		if ( $prompt_id <= 0 ) {
			return null;
		}

		if ( null === self::$prompt_index ) {
			self::$prompt_index = array();
			foreach ( self::get_prompts() as $prompt ) {
				self::$prompt_index[ $prompt['id'] ] = $prompt;
			}
		}

		return isset( self::$prompt_index[ $prompt_id ] ) ? self::$prompt_index[ $prompt_id ] : null;
	}

	/**
	 * Apply an Opace AI Hub prompt to a step, or clear the step back to
	 * AI-Scribe's own prompt when $prompt_id is 0.
	 *
	 * @param int $step      Wizard step 1-11.
	 * @param int $prompt_id Opace AI Hub prompt id, or 0 to revert.
	 * @return bool|WP_Error True on success.
	 */
	public static function apply( $step, $prompt_id ) {
		$step      = (int) $step;
		$prompt_id = (int) $prompt_id;

		if ( $step < self::MIN_STEP || $step > self::MAX_STEP ) {
			return new WP_Error( 'invalid_step', __( 'That is not a wizard step.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		$map = self::get_applied_map();

		if ( $prompt_id <= 0 ) {
			unset( $map[ $step ] );
		} else {
			if ( ! self::library_available() ) {
				return new WP_Error( 'hub_inactive', __( 'Opace AI Hub is not active, so its prompts cannot be applied.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
			}
			if ( null === self::find_prompt( $prompt_id ) ) {
				return new WP_Error( 'prompt_not_found', __( 'That prompt no longer exists in Opace AI Hub.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
			}
			$map[ $step ] = $prompt_id;
		}

		ksort( $map );
		update_option( self::APPLIED_OPTION, $map, false );

		return true;
	}

	/**
	 * The hub group with this exact name, creating it when absent.
	 *
	 * Goes through Opace AI Hub's public save_group() API — the same code path its
	 * own "New Group" button uses — so nothing here knows about its tables.
	 *
	 * @param string $name        Group name.
	 * @param string $description Description, applied only on creation.
	 * @return int|WP_Error Group id.
	 */
	public static function ensure_group( $name, $description = '' ) {
		if ( ! self::library_writable() ) {
			return new WP_Error( 'hub_not_writable', __( 'Opace AI Hub does not expose a prompt save API.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		$library = self::library();
		if ( null === $library ) {
			return new WP_Error( 'hub_unavailable', __( 'Opace AI Hub prompt library unavailable.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		foreach ( self::get_groups() as $group ) {
			if ( $group['name'] === $name ) {
				return (int) $group['id'];
			}
		}

		$result = $library->save_group(
			array(
				'name'        => $name,
				'description' => $description,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::flush_cache();

		return (int) $result;
	}

	/**
	 * The id of a prompt in a group with this exact title, or 0.
	 *
	 * The per-prompt existence check that keeps the migration idempotent:
	 * a prompt already carried across is recognised and left alone, so a
	 * half-finished run resumes rather than duplicating.
	 *
	 * @param string $title    Prompt title.
	 * @param int    $group_id Group id to look in.
	 * @return int Prompt id, 0 when absent.
	 */
	public static function find_prompt_id_by_title( $title, $group_id ) {
		$group_id = (int) $group_id;

		foreach ( self::get_prompts() as $prompt ) {
			if ( $prompt['title'] === $title && $prompt['group_id'] === $group_id ) {
				return $prompt['id'];
			}
		}

		return 0;
	}

	/**
	 * Create a prompt in the hub.
	 *
	 * Thin wrapper over Opace AI Hub's public save_prompt(). Deliberately create-
	 * only: nothing in AI-Scribe overwrites a prompt the user maintains in
	 * Opace AI Hub, so the caller decides what to do when one already exists.
	 *
	 * @param string $title    Prompt title.
	 * @param string $content  Prompt text.
	 * @param int    $group_id Group id, 0 for ungrouped.
	 * @return int|WP_Error New prompt id.
	 */
	public static function write_prompt( $title, $content, $group_id = 0 ) {
		if ( ! self::library_writable() ) {
			return new WP_Error( 'hub_not_writable', __( 'Opace AI Hub does not expose a prompt save API.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		$library = self::library();
		if ( null === $library ) {
			return new WP_Error( 'hub_unavailable', __( 'Opace AI Hub prompt library unavailable.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		$result = $library->save_prompt(
			array(
				'title'    => $title,
				'content'  => $content,
				'group_id' => (int) $group_id,
				'type'     => 'text',
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::flush_cache();

		return (int) $result;
	}

	/**
	 * Update the content of an existing hub prompt, keeping its title, group
	 * and type exactly as they are.
	 *
	 * The one sanctioned overwrite path, used only by the migration's
	 * superseded-default refresh — which verifies the stored content still
	 * reads verbatim as an old stock default before calling this, so a
	 * user-edited prompt is never rewritten.
	 *
	 * @param int    $prompt_id Opace AI Hub prompt id.
	 * @param string $content   New prompt text.
	 * @return true|WP_Error
	 */
	public static function update_prompt_content( $prompt_id, $content ) {
		if ( ! self::library_writable() ) {
			return new WP_Error( 'hub_not_writable', __( 'Opace AI Hub does not expose a prompt save API.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		$prompt = self::find_prompt( $prompt_id );
		if ( null === $prompt ) {
			return new WP_Error( 'prompt_not_found', __( 'That prompt no longer exists in Opace AI Hub.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		$library = self::library();
		if ( null === $library ) {
			return new WP_Error( 'hub_unavailable', __( 'Opace AI Hub prompt library unavailable.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) );
		}

		$result = $library->save_prompt(
			array(
				'id'       => (int) $prompt_id,
				'title'    => $prompt['title'],
				'content'  => (string) $content,
				'group_id' => (int) $prompt['group_id'],
				'type'     => $prompt['type'],
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::flush_cache();

		return true;
	}

	/**
	 * Drop the per-request prompt index after a write, so a later read in the
	 * same request sees what was just stored.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$prompt_index = null;
	}

	/**
	 * AJAX: apply or revert a step's Opace AI Hub prompt.
	 *
	 * Applying a prompt changes what a wizard step sends, not a site
	 * setting, so `edit_posts` is the capability — the same bar as running
	 * the wizard itself. The nonce is AI-Scribe's standard admin nonce.
	 *
	 * @return void
	 */
	public static function handle_apply() {
		if ( ! check_ajax_referer( 'ai_scribe_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message'       => __( 'Security check failed. Please reload the page.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					'code'          => 'invalid_nonce',
					'nonce_expired' => true,
				)
			);
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to change prompts.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					'code'    => 'forbidden',
				)
			);
		}

		$step      = isset( $_POST['step'] ) ? (int) $_POST['step'] : 0;
		$prompt_id = isset( $_POST['prompt_id'] ) ? (int) $_POST['prompt_id'] : 0;

		$result = self::apply( $step, $prompt_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				)
			);
		}

		$prompt = $prompt_id > 0 ? self::find_prompt( $prompt_id ) : null;

		wp_send_json_success(
			array(
				'step'      => $step,
				'prompt_id' => $prompt_id,
				'title'     => $prompt ? $prompt['title'] : '',
				'message'   => $prompt_id > 0
					/* translators: %s: Opace AI Hub prompt title. */
					? sprintf( __( 'Now using the Opace AI Hub prompt "%s" for this step.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), $prompt ? $prompt['title'] : '' )
					: __( 'Reverted to the AI-Scribe prompt for this step.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			)
		);
	}
}
