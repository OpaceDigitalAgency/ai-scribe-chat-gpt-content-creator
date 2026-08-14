<?php
/**
 * Conversation Service for AI-Scribe Plugin
 *
 * Server-side article state: one conversation per article. Stores the
 * settings snapshot, the running message history, per-step responses
 * (raw + parsed), user selections and status. Every step response is
 * persisted BEFORE it is returned to the client, so a UI failure never
 * wastes tokens — the stored response can always be re-rendered.
 *
 * Storage: custom table {$wpdb->prefix}ai_scribe_conversations (JSON
 * columns, installed via dbDelta on activation). When $wpdb is not
 * available (unit tests / smoke harness) an in-memory store is used.
 *
 * @package AI_Scribe
 * @subpackage Services
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Scribe_Conversation_Service {

	const TABLE          = 'ai_scribe_conversations';
	const SCHEMA_VERSION = '1';

	/** Valid selection keys (contract §3). */
	const SELECTION_KEYS = array(
		'title',
		'keywords',
		'outline',
		'tagline',
		'introduction',
		'body',
		'conclusion',
		'qna',
		'meta',
		'final_article',
	);

	/**
	 * @var AI_Scribe_Logger|null
	 */
	private $logger;

	/**
	 * In-memory fallback store (tests / smoke harness without $wpdb).
	 *
	 * @var array<int,array>
	 */
	private static $memory_store = array();

	/**
	 * @var int Next in-memory id.
	 */
	private static $memory_next_id = 1;

	public function __construct( $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Create/upgrade the conversations table. Safe to call repeatedly.
	 *
	 * @return void
	 */
	public static function install_table() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return;
		}

		$table           = $wpdb->prefix . self::TABLE;
		$charset_collate = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';

		$sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            mode VARCHAR(20) NOT NULL DEFAULT 'wizard',
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            settings LONGTEXT NULL,
            messages LONGTEXT NULL,
            steps LONGTEXT NULL,
            selections LONGTEXT NULL,
            cost LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY status (status)
        ) {$charset_collate};";

		if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		if ( function_exists( 'dbDelta' ) ) {
			dbDelta( $sql );
			update_option( 'ai_scribe_conversations_schema', self::SCHEMA_VERSION );
		}
	}

	/**
	 * Create a new conversation.
	 *
	 * @param array  $settings Settings snapshot (idea, language, model, ...).
	 * @param string $mode     wizard|express
	 * @return int Conversation id.
	 */
	public function create( array $settings, $mode = 'wizard' ) {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user_id < 1 ) {
			return 0;
		}
		$now = current_time( 'mysql' );
		$row = array(
			'status'     => 'active',
			'mode'       => ( $mode === 'express' ) ? 'express' : 'wizard',
			'user_id'    => $user_id,
			'settings'   => $this->normalise_settings( $settings ),
			'messages'   => array(),
			'steps'      => array(),
			'selections' => array(),
			'cost'       => array(
				'running_total_usd' => 0.0,
				'by_step'           => array(),
			),
			'created_at' => $now,
			'updated_at' => $now,
		);

		return $this->insert_row( $row );
	}

	/**
	 * Fetch a conversation by id for the current user.
	 *
	 * Conversation ids are not capabilities. All reads and the mutations built
	 * on this method are owner-scoped so one author cannot inspect, generate,
	 * save or add cost to another author's article by changing a numeric id.
	 *
	 * @param int $id
	 * @return array|null Conversation array (decoded) or null.
	 */
	public function get( $id ) {
		$id      = (int) $id;
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$wpdb    = $this->wpdb();

		if ( $id < 1 || $user_id < 1 ) {
			return null;
		}

		if ( $wpdb === null ) {
			if ( ! isset( self::$memory_store[ $id ] ) || (int) self::$memory_store[ $id ]['user_id'] !== $user_id ) {
				return null;
			}
			return self::$memory_store[ $id ];
		}

		$table = $wpdb->prefix . self::TABLE;
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND user_id = %d', $table, $id, $user_id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}

		foreach ( array( 'settings', 'messages', 'steps', 'selections', 'cost' ) as $json_col ) {
			$decoded          = json_decode( isset( $row[ $json_col ] ) ? (string) $row[ $json_col ] : '', true );
			$row[ $json_col ] = is_array( $decoded ) ? $decoded : array();
		}
		$row['id'] = (int) $row['id'];

		return $row;
	}

	/**
	 * Update settings snapshot (partial merge).
	 *
	 * @param int   $id
	 * @param array $settings
	 * @return bool
	 */
	public function update_settings( $id, array $settings ) {
		$conversation = $this->get( $id );
		if ( ! $conversation ) {
			return false;
		}
		// Merge FIRST, normalise the result: normalising a partial map would
		// fill defaults for the missing keys and clobber the stored values.
		$conversation['settings'] = $this->normalise_settings( array_merge( $conversation['settings'], $settings ) );
		return $this->save( $conversation );
	}

	/**
	 * Append a message to the running thread.
	 *
	 * @param int    $id
	 * @param string $role    system|user|assistant
	 * @param string $content
	 * @param int    $step    Originating step (0 = none).
	 * @return bool
	 */
	public function append_message( $id, $role, $content, $step = 0 ) {
		$conversation = $this->get( $id );
		if ( ! $conversation ) {
			return false;
		}
		$conversation['messages'][] = array(
			'role'    => $role,
			'content' => (string) $content,
			'step'    => (int) $step,
		);
		return $this->save( $conversation );
	}

	/**
	 * Persist a step response. Called BEFORE anything is returned to the
	 * client so a UI failure never wastes the tokens.
	 *
	 * @param int    $id
	 * @param int    $step
	 * @param string $status   complete|failed
	 * @param array  $payload  {kind, raw, parsed, usage, prompt_used, error}
	 * @return bool
	 */
	public function record_step( $id, $step, $status, array $payload = array() ) {
		$conversation = $this->get( $id );
		if ( ! $conversation ) {
			return false;
		}

		$entry = array(
			'status'       => $status,
			'kind'         => isset( $payload['kind'] ) ? $payload['kind'] : 'longform',
			'raw'          => isset( $payload['raw'] ) ? $payload['raw'] : '',
			'parsed'       => isset( $payload['parsed'] ) ? $payload['parsed'] : null,
			'usage'        => isset( $payload['usage'] ) ? $payload['usage'] : array(),
			'prompt_used'  => isset( $payload['prompt_used'] ) ? $payload['prompt_used'] : '',
			'completed_at' => current_time( 'mysql' ),
		);
		// L-21: an explicit marker, so the UI can say "no prompt was sent for
		// this step" (Express mirrors, auto phases) instead of showing a
		// misleading placeholder where a prompt never existed.
		$entry['has_prompt'] = trim( (string) $entry['prompt_used'] ) !== '';
		if ( isset( $payload['error'] ) ) {
			$entry['error'] = $payload['error'];
		}

		// Regeneration keeps previous parsed options available.
		$key = (string) (int) $step;
		if ( ! empty( $payload['append_options'] )
			&& isset( $conversation['steps'][ $key ]['parsed'] )
			&& is_array( $conversation['steps'][ $key ]['parsed'] )
			&& is_array( $entry['parsed'] )
		) {
			foreach ( $entry['parsed'] as $pk => $pv ) {
				if ( is_array( $pv ) && isset( $conversation['steps'][ $key ]['parsed'][ $pk ] ) && is_array( $conversation['steps'][ $key ]['parsed'][ $pk ] ) ) {
					$entry['parsed'][ $pk ] = array_values(
						array_unique(
							array_merge(
								$conversation['steps'][ $key ]['parsed'][ $pk ],
								$pv
							),
							SORT_REGULAR
						)
					);
				}
			}
		}

		$conversation['steps'][ $key ] = $entry;
		return $this->save( $conversation );
	}

	/**
	 * Record actual cost for a step and update the running total.
	 *
	 * @param int   $id
	 * @param int   $step
	 * @param float $usd
	 * @return bool
	 */
	public function record_cost( $id, $step, $usd ) {
		$conversation = $this->get( $id );
		if ( ! $conversation ) {
			return false;
		}
		$key                                       = (string) (int) $step;
		$conversation['cost']['by_step'][ $key ]   =
			( isset( $conversation['cost']['by_step'][ $key ] ) ? (float) $conversation['cost']['by_step'][ $key ] : 0.0 ) + (float) $usd;
		$conversation['cost']['running_total_usd'] =
			( isset( $conversation['cost']['running_total_usd'] ) ? (float) $conversation['cost']['running_total_usd'] : 0.0 ) + (float) $usd;
		return $this->save( $conversation );
	}

	/**
	 * Save a user selection.
	 *
	 * @param int    $id
	 * @param string $key   One of SELECTION_KEYS.
	 * @param mixed  $value
	 * @return bool
	 */
	public function save_selection( $id, $key, $value ) {
		if ( ! in_array( $key, self::SELECTION_KEYS, true ) ) {
			return false;
		}
		$conversation = $this->get( $id );
		if ( ! $conversation ) {
			return false;
		}
		if ( 'keywords' === $key ) {
			$value = AI_Scribe_Schema_Registry::keyword_phrases( $value );
		}
		$conversation['selections'][ $key ] = $value;
		return $this->save( $conversation );
	}

	/**
	 * Set conversation status.
	 *
	 * @param int    $id
	 * @param string $status active|complete|abandoned
	 * @return bool
	 */
	public function set_status( $id, $status ) {
		$conversation = $this->get( $id );
		if ( ! $conversation ) {
			return false;
		}
		$conversation['status'] = in_array( $status, array( 'active', 'complete', 'abandoned' ), true ) ? $status : 'active';
		return $this->save( $conversation );
	}

	/**
	 * Public state object per API contract §4.
	 *
	 * @param int $id
	 * @return array|null
	 */
	public function get_state( $id ) {
		$conversation = $this->get( $id );
		if ( ! $conversation ) {
			return null;
		}
		return array(
			'conversation_id' => (int) $conversation['id'],
			'status'          => $conversation['status'],
			'mode'            => $conversation['mode'],
			'settings'        => $conversation['settings'],
			'selections'      => (object) $conversation['selections'],
			'steps'           => (object) $conversation['steps'],
			'cost'            => $conversation['cost'],
		);
	}

	/**
	 * Message history as provider-ready [{role, content}] pairs.
	 *
	 * @param int $id
	 * @return array
	 */
	public function get_messages( $id ) {
		$conversation = $this->get( $id );
		if ( ! $conversation ) {
			return array();
		}
		$out = array();
		foreach ( $conversation['messages'] as $message ) {
			$out[] = array(
				'role'    => $message['role'],
				'content' => $message['content'],
			);
		}
		return $out;
	}

	// ------------------------------------------------------------------
	// Internals
	// ------------------------------------------------------------------

	/**
	 * @return \wpdb|null
	 */
	private function wpdb() {
		global $wpdb;
		return ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_row' ) ) ? $wpdb : null;
	}

	private function normalise_settings( array $settings ) {
		$defaults                       = array(
			'idea'               => '',
			'language'           => 'English',
			'writing_style'      => 'Business',
			'writing_tone'       => 'Professional',
			'heading_tag'        => 'H2',
			'number_of_headings' => 5,
			'article_length_mode' => 'auto',
			'article_word_count'  => 1800,
			'qna_enabled'         => true,
			'quality_gate_enabled' => false,
			'tagline_position'   => 'below',
			'avoid_keywords'     => '',
			'model'              => '',
			'options'            => array(),
			// The WordPress post this conversation has been saved to (0 =
			// never saved). Lets a later Publish promote the existing draft
			// instead of creating a duplicate post.
			'post_id'            => 0,
			'post_status'        => '',
			'post_html'          => '',
			'post_edit_link'     => '',
		);
		$settings                       = array_merge( $defaults, array_intersect_key( $settings, $defaults ) );
		$settings['number_of_headings'] = max( 1, (int) $settings['number_of_headings'] );
		$settings['article_length_mode'] = in_array( sanitize_key( $settings['article_length_mode'] ), AI_Scribe_Article_Plan_Service::MODES, true ) ? sanitize_key( $settings['article_length_mode'] ) : 'auto';
		$settings['article_word_count']  = max( 400, min( 8000, (int) $settings['article_word_count'] ) );
		$settings['qna_enabled']         = (bool) $settings['qna_enabled'];
		$settings['quality_gate_enabled'] = (bool) $settings['quality_gate_enabled'];
		$settings['post_id']            = max( 0, (int) $settings['post_id'] );
		$settings['post_status']        = in_array( $settings['post_status'], array( 'draft', 'publish' ), true ) ? $settings['post_status'] : '';
		$settings['post_html']          = (string) $settings['post_html'];
		$settings['post_edit_link']     = (string) $settings['post_edit_link'];
		$settings['tagline_position']   = ( $settings['tagline_position'] === 'above' ) ? 'above' : 'below';
		if ( ! is_array( $settings['options'] ) ) {
			$settings['options'] = array();
		}
		return $settings;
	}

	private function insert_row( array $row ) {
		$wpdb = $this->wpdb();

		if ( $wpdb === null ) {
			$id                        = self::$memory_next_id++;
			$row['id']                 = $id;
			self::$memory_store[ $id ] = $row;
			return $id;
		}

		$table = $wpdb->prefix . self::TABLE;
		$wpdb->insert(
			$table,
			array(
				'status'     => $row['status'],
				'mode'       => $row['mode'],
				'user_id'    => $row['user_id'],
				'settings'   => wp_json_encode( $row['settings'] ),
				'messages'   => wp_json_encode( $row['messages'] ),
				'steps'      => wp_json_encode( $row['steps'] ),
				'selections' => wp_json_encode( $row['selections'] ),
				'cost'       => wp_json_encode( $row['cost'] ),
				'created_at' => $row['created_at'],
				'updated_at' => $row['updated_at'],
			)
		);

		return (int) $wpdb->insert_id;
	}

	private function save( array $conversation ) {
		$conversation['updated_at'] = current_time( 'mysql' );
		$wpdb                       = $this->wpdb();
		$user_id                    = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user_id < 1 || ! isset( $conversation['user_id'] ) || (int) $conversation['user_id'] !== $user_id ) {
			return false;
		}

		if ( $wpdb === null ) {
			if ( ! isset( self::$memory_store[ (int) $conversation['id'] ] )
				|| (int) self::$memory_store[ (int) $conversation['id'] ]['user_id'] !== $user_id ) {
				return false;
			}
			self::$memory_store[ (int) $conversation['id'] ] = $conversation;
			return true;
		}

		$table  = $wpdb->prefix . self::TABLE;
		$result = $wpdb->update(
			$table,
			array(
				'status'     => $conversation['status'],
				'mode'       => $conversation['mode'],
				'settings'   => wp_json_encode( $conversation['settings'] ),
				'messages'   => wp_json_encode( $conversation['messages'] ),
				'steps'      => wp_json_encode( $conversation['steps'] ),
				'selections' => wp_json_encode( $conversation['selections'] ),
				'cost'       => wp_json_encode( $conversation['cost'] ),
				'updated_at' => $conversation['updated_at'],
			),
			array(
				'id'      => (int) $conversation['id'],
				'user_id' => $user_id,
			)
		);

		return $result !== false;
	}
}
