<?php
/**
 * Post Service Class for AI-Scribe Plugin
 *
 * Handles WordPress post creation functionality including post generation,
 * SEO metadata management, and integration with popular SEO plugins.
 *
 * @package AI_Scribe
 * @subpackage Services
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Post_Service
 *
 * Provides post creation functionality for the AI-Scribe plugin including
 * WordPress post generation, SEO metadata handling, and content processing.
 */
class AI_Scribe_Post_Service extends AI_Scribe_Base_Service {

	/**
	 * WordPress adapter instance
	 *
	 * @var AI_Scribe_WordPress_Adapter
	 */
	protected $wordpress_adapter;

	/**
	 * Security service instance
	 *
	 * @var AI_Scribe_Security_Service
	 */
	protected $security_service;

	/**
	 * Constructor
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 * @param AI_Scribe_Config_Manager $config Configuration manager
	 * @param AI_Scribe_WordPress_Adapter $wordpress_adapter WordPress adapter
	 * @param AI_Scribe_Security_Service $security_service Security service
	 */
	public function __construct( $logger, $config, $wordpress_adapter, $security_service = null ) {
		parent::__construct( $logger, $config, 'post_service' );
		$this->wordpress_adapter = $wordpress_adapter;
		$this->security_service  = $security_service;
	}

	/**
	 * Initialize service
	 *
	 * @return void
	 */
	protected function initialize() {
		$this->log_debug( 'Post service initializing' );

		// Validate dependencies
		if ( ! $this->wordpress_adapter ) {
			$this->log_error( 'WordPress adapter not provided to Post Service' );
		}
	}

	/**
	 * Validate service dependencies and configuration
	 *
	 * @return array Service validation status
	 */
	public function validate_service() {
		// Check WordPress adapter dependency
		if ( ! $this->wordpress_adapter ) {
			$this->log_error( 'Post service validation failed: WordPress adapter missing' );
			return 'WordPress adapter missing';
		}

		// Check configuration dependency
		if ( ! $this->config ) {
			$this->log_error( 'Post service validation failed: Configuration missing' );
			return 'Configuration missing';
		}

		// During early WordPress initialization, some functions may not be available yet
		// This is acceptable - the service will work once WordPress is fully loaded

		return true;
	}

	/**
	 * Send post page - Create WordPress post from content
	 *
	 * Handles AJAX request to create a WordPress post from generated content
	 * including SEO metadata and integration with popular SEO plugins.
	 *
	 * @return void Sends JSON response directly
	 */
	public function send_post_page() {
		$this->log_debug( 'Processing post creation request' );

		// Validate nonce for security
		if ( ! isset( $_POST['security'] ) || ! check_ajax_referer( 'ai_scribe_nonce', 'security', false ) ) {
			$this->log_warning( 'Post creation failed: Invalid nonce' );
			wp_send_json_error(
				array(
					'msg'           => 'Invalid request. Please refresh the page and try again.',
					'nonce_expired' => true,
				)
			);
			return;
		}

		ob_start();

		try {
			// Sanitize and process input data
			$post_data = $this->sanitize_post_data( $_POST );

			$this->log_debug(
				'Post data sanitized',
				array(
					'title_length'   => strlen( $post_data['post_title'] ),
					'content_length' => strlen( $post_data['post_content'] ),
				)
			);

			// Create WordPress post
			$post_id = $this->create_wordpress_post( $post_data );

			if ( $post_id > 0 ) {
				$this->log_info( 'WordPress post created successfully', array( 'post_id' => $post_id ) );

				// Handle SEO metadata
				$this->handle_seo_metadata( $post_id, $post_data );

				wp_send_json_success(
					array(
						'msg'      => 'Post created successfully',
						'post_id'  => $post_id,
						'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
					)
				);
			} else {
				$this->log_error( 'Failed to create WordPress post' );
				wp_send_json_error( array( 'msg' => 'Failed to create post' ) );
			}
		} catch ( Exception $e ) {
			$error_response = $this->handle_error( 'Post creation failed', $e );
			wp_send_json_error( array( 'msg' => $error_response['error'] ) );
		}

		return ob_get_clean();
	}

	/**
	 * Sanitize post data from request
	 *
	 * @param array $raw_data Raw POST data
	 * @return array Sanitized post data
	 */
	protected function sanitize_post_data( $raw_data ) {
		// Sanitize arrays of data
		$heading_data    = array_map( 'sanitize_text_field', $raw_data['headingData'] ?? array() );
		$keyword_data    = array_map( 'sanitize_text_field', $raw_data['keywordData'] ?? array() );
		$intro_data      = array_map( 'sanitize_text_field', $raw_data['introData'] ?? array() );
		$tagline_data    = array_map( 'sanitize_text_field', $raw_data['taglineData'] ?? array() );
		$conclusion_data = array_map( 'sanitize_text_field', $raw_data['conclusionData'] ?? array() );
		$qna_data        = array_map( 'sanitize_text_field', $raw_data['qnaData'] ?? array() );
		$meta_data       = array_map( 'sanitize_text_field', $raw_data['metaData'] ?? array() );

		// Process article content
		$article_val     = wp_kses_post( $raw_data['articleVal'] ?? '' );
		$article_content = preg_replace( '/<h1>.*<\/h1>/', ' ', $article_val );
		$article_content = preg_replace( "/<br>|\n|<br( ?)\/>/", '', $article_content );

		// Extract post title from H1 tag
		$pattern = '/<h1>(.*?)<\/h1>/';
		preg_match( $pattern, $article_val, $matches );
		$post_title = ! empty( $matches[1] ) ? wp_strip_all_tags( $matches[1] ) : 'Untitled Post';

		$post_slug = $this->build_post_slug( $post_title );

		return array(
			'post_title'     => $post_title,
			'post_content'   => $this->clean_article_html( $article_content ),
			'post_slug'      => $post_slug,
			'heading_str'    => implode( ' ', $heading_data ),
			'keyword_str'    => implode( ' ', $keyword_data ),
			'keyword_data'   => $keyword_data,
			'intro_str'      => implode( ' ', $intro_data ),
			'tagline_str'    => implode( ' ', $tagline_data ),
			'conclusion_str' => implode( ' ', $conclusion_data ),
			'qna_str'        => implode( ' ', $qna_data ),
			'meta_data'      => $meta_data,
			'title_data'     => sanitize_title( $raw_data['titleData'] ?? '' ),
		);
	}

	/**
	 * Create WordPress post
	 *
	 * @param array $post_data Sanitized post data
	 * @return int Post ID or 0 on failure
	 */
	protected function create_wordpress_post( $post_data ) {
		$post_args = array(
			'post_type'    => 'post',
			'post_title'   => $post_data['post_title'],
			'post_content' => $post_data['post_content'],
			'post_status'  => 'draft',
			'post_name'    => $post_data['post_slug'],
			'post_excerpt' => $this->build_post_excerpt( $post_data['post_content'] ),
		);

		$this->log_debug(
			'Creating WordPress post',
			array(
				'title' => $post_data['post_title'],
				'slug'  => $post_data['post_slug'],
			)
		);

		return wp_insert_post( $post_args );
	}

	/**
	 * Handle SEO metadata for popular SEO plugins
	 *
	 * @param int $post_id WordPress post ID
	 * @param array $post_data Post data including metadata
	 * @return array Report of where the meta was stored: {stored: bool,
	 *               plugin: yoast|aioseo|rankmath|seopress|none,
	 *               meta_keys: string[], message: string}
	 */
	protected function handle_seo_metadata( $post_id, $post_data ) {
		if ( empty( $post_data['meta_data'] ) || count( $post_data['meta_data'] ) < 2 ) {
			$this->log_warning( 'Insufficient SEO metadata provided' );
			return array(
				'stored'    => false,
				'plugin'    => 'none',
				'meta_keys' => array(),
				'message'   => __( 'No meta title or description was available to save.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			);
		}

		$seo_title       = $post_data['meta_data'][0];
		$seo_description = $post_data['meta_data'][1];
		$keywords        = implode( ', ', $post_data['keyword_data'] );

		// Always persist under plugin-own keys too, so the generated meta
		// survives when no SEO plugin is active (and can be adopted later).
		// L-26: with no SEO plugin these standard post-meta keys are the
		// canonical home — nothing is silently dropped.
		update_post_meta( $post_id, '_ai_scribe_meta_title', $seo_title );
		update_post_meta( $post_id, '_ai_scribe_meta_description', $seo_description );
		$meta_keys = array( '_ai_scribe_meta_title', '_ai_scribe_meta_description' );
		if ( $keywords !== '' ) {
			update_post_meta( $post_id, '_ai_scribe_focus_keywords', $keywords );
			$meta_keys[] = '_ai_scribe_focus_keywords';
		}

		$this->log_debug(
			'Processing SEO metadata',
			array(
				'post_id'        => $post_id,
				'seo_title'      => $seo_title,
				'keywords_count' => count( $post_data['keyword_data'] ),
			)
		);

		// Yoast SEO
		if ( defined( 'WPSEO_FILE' ) ) {
			$this->log_debug( 'Setting Yoast SEO metadata' );
			update_post_meta( $post_id, '_yoast_wpseo_title', $seo_title );
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $seo_description );
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', $keywords );
			$plugin      = 'yoast';
			$meta_keys[] = '_yoast_wpseo_title';
			$meta_keys[] = '_yoast_wpseo_metadesc';
		}
		// All in One SEO v4 (current; AIOSEO_VERSION) — meta lives in the
		// wp_aioseo_posts table, written through the plugin's own model so
		// its cache and column handling stay correct. The `_aioseop_*` post
		// meta only feeds the retired v3 plugin (AIOSEOP_VERSION), so it is
		// written solely when that legacy plugin is genuinely the active one.
		elseif ( defined( 'AIOSEO_VERSION' ) ) {
			$this->log_debug( 'Setting All in One SEO (v4) metadata' );
			$written = $this->write_aioseo_v4_meta( $post_id, $seo_title, $seo_description, $keywords );
			$plugin  = 'aioseo';
			if ( $written ) {
				$meta_keys[] = 'aioseo_posts.title';
				$meta_keys[] = 'aioseo_posts.description';
			}
		}
		// All in One SEO Pack v3 (legacy plugin still active).
		elseif ( defined( 'AIOSEOP_VERSION' ) ) {
			$this->log_debug( 'Setting All in One SEO (legacy v3) metadata' );
			update_post_meta( $post_id, '_aioseop_title', $seo_title );
			update_post_meta( $post_id, '_aioseop_description', $seo_description );
			$plugin      = 'aioseo';
			$meta_keys[] = '_aioseop_title';
			$meta_keys[] = '_aioseop_description';
		}
		// Rank Math
		elseif ( defined( 'RANK_MATH_FILE' ) ) {
			$this->log_debug( 'Setting Rank Math SEO metadata' );
			update_post_meta( $post_id, 'rank_math_title', $seo_title );
			update_post_meta( $post_id, 'rank_math_description', $seo_description );
			update_post_meta( $post_id, 'rank_math_focus_keyword', $keywords );
			$plugin      = 'rankmath';
			$meta_keys[] = 'rank_math_title';
			$meta_keys[] = 'rank_math_description';
		}
		// SEOPress
		elseif ( defined( 'SEOPRESS_VERSION' ) ) {
			$this->log_debug( 'Setting SEOPress metadata' );
			update_post_meta( $post_id, '_seopress_titles_title', $seo_title );
			update_post_meta( $post_id, '_seopress_titles_desc', $seo_description );
			update_post_meta( $post_id, '_seopress_analysis_target_kw', $keywords );
			$plugin      = 'seopress';
			$meta_keys[] = '_seopress_titles_title';
			$meta_keys[] = '_seopress_titles_desc';
		} else {
			$this->log_debug( 'No supported SEO plugin detected; meta kept in AI-Scribe post meta' );
			$plugin = 'none';
		}

		return array(
			'stored'    => true,
			'plugin'    => $plugin,
			'meta_keys' => $meta_keys,
			'message'   => ( 'none' === $plugin )
				? __( 'No SEO plugin is active, so AI-Scribe saved the meta title and description to this post\'s own meta and will output them on the front end (title tag and meta description). Installing Yoast, Rank Math, AIOSEO or SEOPress and re-saving will populate that plugin\'s fields instead.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' )
				: __( 'Meta title and description saved to your SEO plugin\'s fields.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
		);
	}

	/**
	 * Write meta into All in One SEO v4's own storage (wp_aioseo_posts).
	 *
	 * Goes through AIOSEO's Post model (Post::savePost) so its table row,
	 * caches and traditional post meta mirrors are all handled by the plugin
	 * itself. Defensive: any missing class or internal AIOSEO failure is
	 * caught and reported as not-written rather than breaking the save.
	 *
	 * @param int    $post_id         WordPress post ID.
	 * @param string $seo_title       Meta title.
	 * @param string $seo_description Meta description.
	 * @param string $keywords        Comma-separated focus keywords ('' for none).
	 * @return bool Whether the AIOSEO row was written.
	 */
	protected function write_aioseo_v4_meta( $post_id, $seo_title, $seo_description, $keywords ) {
		if ( ! class_exists( '\AIOSEO\Plugin\Common\Models\Post' )
			|| ! is_callable( array( '\AIOSEO\Plugin\Common\Models\Post', 'savePost' ) )
			|| ! function_exists( 'aioseo' ) ) {
			$this->log_warning( 'AIOSEO v4 detected but its Post model is unavailable' );
			return false;
		}

		$data = array(
			'title'       => $seo_title,
			'description' => $seo_description,
		);
		if ( $keywords !== '' ) {
			$data['keyphrases'] = array(
				'focus'      => array(
					'keyphrase' => $keywords,
					'score'     => 0,
					'analysis'  => array(),
				),
				'additional' => array(),
			);
		}

		try {
			\AIOSEO\Plugin\Common\Models\Post::savePost( (int) $post_id, $data );
			return true;
		} catch ( \Throwable $e ) {
			$this->log_error( 'AIOSEO v4 meta write failed', array( 'error' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * v3: create a post from conversation selections (contract §7).
	 *
	 * Assembles the final article from stored selections (or an explicit
	 * edited HTML), inserts the post and writes SEO plugin meta
	 * (Yoast / AIOSEO / RankMath / SEOPress) from selections.meta.
	 *
	 * @param array $selections Conversation selections (title, body, meta, ...).
	 * @param array $args {post_status, post_type, content_html}
	 * @return array|WP_Error {post_id, edit_link, permalink}
	 */
	public function create_from_conversation( array $selections, array $args = array() ) {
		$title   = isset( $selections['title'] ) ? trim( (string) $selections['title'] ) : '';
		$content = isset( $args['content_html'] ) && trim( (string) $args['content_html'] ) !== ''
			? (string) $args['content_html']
			: $this->assemble_article_html( $selections );

		if ( $title === '' ) {
			// Fall back to the H1 in the body.
			if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $content, $m ) ) {
				$title = trim( wp_strip_all_tags( $m[1] ) );
			}
		}
		if ( $title === '' || trim( wp_strip_all_tags( $content ) ) === '' ) {
			return new WP_Error( 'invalid_params', 'A title and article content are required to save a post.' );
		}
		$content = $this->normalise_article_semantics( $content );

		$missing_headings = $this->missing_outline_headings( $content, isset( $selections['outline'] ) ? $selections['outline'] : array() );
		if ( ! empty( $missing_headings ) ) {
			return new WP_Error(
				'outline_incomplete',
				'This article was not saved because these selected outline sections are missing: ' . implode( '; ', $missing_headings ) . '. Return to Article Body and regenerate or add the missing sections.'
			);
		}

		$article_settings = isset( $args['article_settings'] ) && is_array( $args['article_settings'] ) ? $args['article_settings'] : array();
		if ( ! empty( $article_settings['quality_gate_enabled'] ) ) {
			// Saving must protect the user's selected structure, but a preferred
			// word range is editorial guidance rather than a validity rule. A
			// complete reviewed draft is never discarded solely for being short.
			$outline = AI_Scribe_Article_Plan_Service::assess_selected_outline_order( $content, isset( $selections['outline'] ) ? $selections['outline'] : array(), 0 );
			if ( ! $outline['pass'] ) {
				return new WP_Error(
					'article_quality_incomplete',
					'This article was not saved because the reviewed headings no longer exactly match the selected outline text and order. Return to Review or Article Body and correct the missing or unexpected section.'
				);
			}
		}

		// Body H1 duplicates the post title — strip it, like 2.6.2 did.
		$content = preg_replace( '/<h1[^>]*>.*?<\/h1>/is', '', $content, 1 );

		// Any H1 still left is the model titling a section (or its own
		// output) with the wrong tag; the post title is the only H1, so the
		// stragglers are demoted to section headings.
		$content = preg_replace( '/<h1([^>]*)>(.*?)<\/h1>/is', '<h2$1>$2</h2>', $content );

		$featured_id = isset( $args['featured_attachment_id'] ) ? (int) $args['featured_attachment_id'] : 0;
		if ( $featured_id <= 0 && preg_match( '/wp-image-(\d+)/', $content, $img_match ) ) {
			$featured_id = (int) $img_match[1];
		}
		if ( $featured_id > 0 ) {
			$content = $this->remove_featured_image_from_content( $content, $featured_id );
		}
		$content = $this->clean_article_html( $content );
		$content = '<div class="ai-scribe-article-content">' . $content . '</div>';

		$status = isset( $args['post_status'] ) && in_array( $args['post_status'], array( 'draft', 'publish', 'pending' ), true )
			? $args['post_status'] : 'draft';
		$type   = isset( $args['post_type'] ) && in_array( $args['post_type'], array( 'post', 'page' ), true )
			? $args['post_type'] : 'post';

		// Saving the same conversation twice must UPDATE the post it already
		// created, not mint a duplicate — Publish after Save as Draft promotes
		// the existing draft. The caller passes the tracked id via
		// args.existing_post_id; it is reused only while it still points at a
		// live post of the same type (a trashed or deleted draft starts fresh).
		$existing_id = isset( $args['existing_post_id'] ) ? (int) $args['existing_post_id'] : 0;
		if ( $existing_id > 0 ) {
			$existing = get_post( $existing_id );
			if ( ! $existing || 'trash' === $existing->post_status || $existing->post_type !== $type ) {
				$existing_id = 0;
			}
		}

		$post_args = array(
			'post_type'    => $type,
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => $status,
			'post_excerpt' => $this->build_post_excerpt( $content ),
			'post_author'  => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
		);

		if ( $existing_id > 0 ) {
			$post_args['ID'] = $existing_id;
			$post_id         = wp_update_post( $post_args, true );
		} else {
			$post_args['post_name'] = $this->build_post_slug( $title );
			$post_id = wp_insert_post( $post_args );
		}

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return is_wp_error( $post_id ) ? $post_id : new WP_Error( 'save_failed', 'Failed to create the post.' );
		}
		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, '_ai_scribe_generated', '1' );
		}

		$category_name = isset( $args['category_name'] ) ? sanitize_text_field( (string) $args['category_name'] ) : '';
		$tag_names     = isset( $args['tag_names'] ) ? array_values( array_filter( array_map( 'trim', explode( ',', (string) $args['tag_names'] ) ) ) ) : array();
		$assigned_category = '';
		$assigned_tags     = array();
		if ( 'post' === $type && '' !== $category_name && function_exists( 'term_exists' ) && function_exists( 'wp_set_post_categories' ) ) {
			$term = term_exists( $category_name, 'category' );
			if ( ! $term && function_exists( 'wp_insert_term' ) && current_user_can( 'manage_categories' ) ) {
				$term = wp_insert_term( $category_name, 'category' );
			}
			if ( ! is_wp_error( $term ) ) {
				$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				if ( $term_id > 0 ) {
					$set_categories = wp_set_post_categories( $post_id, array( $term_id ), false );
					if ( is_array( $set_categories ) ) {
						$assigned_category = $category_name;
					}
				}
			}
		}
		if ( 'post' === $type && ! empty( $tag_names ) && function_exists( 'wp_set_post_tags' ) ) {
			$requested_tags = array_slice( $tag_names, 0, 12 );
			$tags_to_set    = $requested_tags;
			$assigned_names = $requested_tags;
			if ( ! current_user_can( 'manage_categories' ) ) {
				$tags_to_set    = array();
				$assigned_names = array();
				if ( function_exists( 'term_exists' ) ) {
					foreach ( $requested_tags as $tag_name ) {
						$tag_term = term_exists( $tag_name, 'post_tag' );
						if ( $tag_term && ! is_wp_error( $tag_term ) ) {
							$tags_to_set[]    = is_array( $tag_term ) ? (int) $tag_term['term_id'] : (int) $tag_term;
							$assigned_names[] = $tag_name;
						}
					}
				}
			}
			if ( ! empty( $tags_to_set ) ) {
				$set_tags = wp_set_post_tags( $post_id, $tags_to_set, false );
				if ( is_array( $set_tags ) ) {
					$assigned_tags = $assigned_names;
				}
			}
		}

		$meta     = isset( $selections['meta'] ) && is_array( $selections['meta'] ) ? $selections['meta'] : array();
		$keywords = isset( $selections['keywords'] ) && is_array( $selections['keywords'] ) ? $selections['keywords'] : array();
		// selections.meta holds the user's EDITED values when they changed
		// them in the SEO step (contract §3 save_selection key "meta"), so
		// edited meta is what lands here (L-23).
		$seo = $this->handle_seo_metadata(
			$post_id,
			array(
				'meta_data'    => array(
					isset( $meta['title'] ) ? (string) $meta['title'] : $title,
					isset( $meta['description'] ) ? (string) $meta['description'] : '',
				),
				'keyword_data' => array_map( 'strval', $keywords ),
			)
		);

		// Featured image: an explicit attachment wins; otherwise the first
		// image already placed in the article body. Never overwrites a
		// thumbnail that is somehow already set.
		$featured_set = false;
		if ( $featured_id > 0 && ! has_post_thumbnail( $post_id ) && wp_attachment_is_image( $featured_id ) ) {
			$featured_set = (bool) set_post_thumbnail( $post_id, $featured_id );
		}

		return array(
			'post_id'        => (int) $post_id,
			'updated'        => $existing_id > 0,
			'edit_link'      => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			'permalink'      => function_exists( 'get_permalink' ) ? get_permalink( $post_id ) : '',
			'seo'            => $seo,
			'featured_image' => array(
				'set'           => $featured_set,
				'attachment_id' => $featured_set ? $featured_id : 0,
			),
			'publishing'     => array(
				'category' => $assigned_category,
				'tags'     => $assigned_tags,
				'author_id' => isset( $post_args['post_author'] ) ? (int) $post_args['post_author'] : 0,
			),
		);
	}

	/**
	 * Strip editor-only empty blocks and inline sizing artefacts before save.
	 *
	 * @param string $html Reviewed article HTML.
	 * @return string Clean article HTML.
	 */
	protected function clean_article_html( $html ) {
		$html = (string) $html;
		$empty_block = '#<(p|div)\b[^>]*>(?:\s|&nbsp;|&\#160;|<br\s*/?>)*</\1>#iu';
		do {
			$before = $html;
			$html   = preg_replace( $empty_block, '', $html );
		} while ( $html !== $before );
		$html = preg_replace_callback(
			'/<(p|div)\b([^>]*)>/i',
			static function ( $match ) {
				$attrs = preg_replace_callback(
					'/\sstyle=("|\')(.*?)\1/i',
					static function ( $style_match ) {
						$rules = preg_replace( '/(?:^|;)\s*(?:height|min-height)\s*:[^;]*/i', '', $style_match[2] );
						$rules = trim( preg_replace( '/;;+/', ';', $rules ), " ;\t\n\r\0\x0B" );
						return '' === $rules ? '' : ' style="' . esc_attr( $rules ) . '"';
					},
					$match[2]
				);
				return '<' . strtolower( $match[1] ) . $attrs . '>';
			},
			$html
		);
		$html = preg_replace_callback(
			'/<img\b([^>]*)>/i',
			static function ( $match ) {
				$attrs = $match[1];
				$attrs = preg_replace( '/\s(?:loading|decoding|fetchpriority)=("|\').*?\1/i', '', $attrs );
				$attrs = preg_replace( '/\s(?:width|height)=("|\').*?\1/i', '', $attrs );
				$attrs = preg_replace( '/\s(?:role|tabindex|aria-label|title|draggable)=("|\').*?\1/i', '', $attrs );
				$size  = array();
				$attachment_id = 0;
				if ( preg_match( '/\bwp-image-(\d+)\b/i', $attrs, $id_match ) ) {
					$attachment_id = (int) $id_match[1];
				} elseif ( preg_match( '/\ssrc=("|\')(.*?)\1/i', $attrs, $src_match ) && function_exists( 'attachment_url_to_postid' ) ) {
					$attachment_id = (int) attachment_url_to_postid( html_entity_decode( $src_match[2], ENT_QUOTES ) );
				}
				$required_classes = 'ai-scribe-article-image';
				if ( $attachment_id > 0 && ! preg_match( '/\bwp-image-\d+\b/i', $attrs ) ) {
					$required_classes .= ' wp-image-' . $attachment_id;
				}
				$attrs = preg_replace( '/\sclass=("|\')(.*?)\1/i', ' class="$2 ' . $required_classes . '"', $attrs, 1, $class_count );
				if ( empty( $class_count ) ) {
					$attrs .= ' class="' . $required_classes . '"';
				}
				if ( $attachment_id > 0 && function_exists( 'wp_get_attachment_metadata' ) ) {
					$metadata = wp_get_attachment_metadata( $attachment_id );
					if ( is_array( $metadata ) && ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
						$size = array( (int) $metadata['width'], (int) $metadata['height'] );
					}
				}
				$attrs .= ' loading="lazy" decoding="async"';
				if ( ! empty( $size ) ) {
					$attrs .= ' width="' . $size[0] . '" height="' . $size[1] . '"';
				}
				return '<img' . $attrs . '>';
			},
			$html
		);
		$html = preg_replace(
			'#<p\b[^>]*>\s*(<img\b[^>]*>)\s*</p>\s*<p\b[^>]*class=("|\')[^"\']*ai-scribe-image-caption[^"\']*\2[^>]*>(.*?)</p>#is',
			'<figure class="wp-block-image size-large ai-scribe-article-figure">$1<figcaption>$3</figcaption></figure>',
			$html
		);
		$html = preg_replace(
			'#<p\b[^>]*>\s*(<img\b[^>]*>)\s*</p>#is',
			'<figure class="wp-block-image size-large ai-scribe-article-figure">$1</figure>',
			$html
		);
		return trim( $html );
	}

	/**
	 * Demote provider prose accidentally wrapped as a heading or as a wholly
	 * bold paragraph. This mirrors the editor boundary so direct save requests
	 * cannot reintroduce the malformed typography.
	 *
	 * @param string $html Reviewed article HTML.
	 * @return string Semantically normalised article HTML.
	 */
	protected function normalise_article_semantics( $html ) {
		$is_prose = static function ( $inner, $word_limit, $character_limit ) {
			$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( (string) $inner, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) );
			if ( '' === $text ) {
				return false;
			}
			$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
			return count( $words ) > $word_limit || $length > $character_limit;
		};

		$html = preg_replace_callback(
			'/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is',
			static function ( $match ) use ( $is_prose ) {
				return $is_prose( $match[2], 20, 160 ) ? '<p>' . $match[2] . '</p>' : $match[0];
			},
			(string) $html
		);
		$html = preg_replace_callback(
			'#<p\b([^>]*)>\s*<(strong|b)\b[^>]*>(.*?)</\2>\s*</p>#is',
			static function ( $match ) use ( $is_prose ) {
				return $is_prose( $match[3], 24, 180 ) ? '<p' . $match[1] . '>' . $match[3] . '</p>' : $match[0];
			},
			$html
		);
		return $html;
	}

	/** @param string $content Article HTML. @param int $attachment_id Featured attachment. */
	protected function remove_featured_image_from_content( $content, $attachment_id ) {
		$url     = function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $attachment_id ) : '';
		$pattern = '#<(figure|p|div)\b[^>]*>\s*<img\b(?=[^>]*(?:wp-image-' . (int) $attachment_id;
		if ( '' !== $url ) {
			$pattern .= '|' . preg_quote( $url, '#' );
		}
		// Quill stores an image caption in the following paragraph. Removing a
		// promoted featured image must consume that paired caption too, otherwise
		// the post begins with orphaned descriptive text for an absent image.
		$pattern .= '))[^>]*>\s*(?:<figcaption\b[^>]*>.*?</figcaption>)?\s*</\1>'
			. '\s*(?:<p\b[^>]*class=("|\')[^"\']*ai-scribe-image-caption[^"\']*\2[^>]*>.*?</p>)?#is';
		$content = preg_replace( $pattern, '', (string) $content );
		$content = preg_replace( '#<img\b(?=[^>]*(?:wp-image-' . (int) $attachment_id . ( '' !== $url ? '|' . preg_quote( $url, '#' ) : '' ) . '))[^>]*>#is', '', $content );
		return $content;
	}

	/** @param string $title Post title. */
	protected function build_post_slug( $title ) {
		$words  = preg_split( '/\s+/u', trim( wp_strip_all_tags( (string) $title ) ) );
		$filler = array( 'a', 'an', 'and', 'are', 'as', 'at', 'but', 'by', 'for', 'from', 'in', 'is', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'with', 'your' );
		$useful = array_values( array_filter( $words, static function ( $word ) use ( $filler ) {
			return '' !== $word && ! in_array( strtolower( trim( $word, "'\".,:;!?()[]{}" ) ), $filler, true );
		} ) );
		$chosen = array_slice( ! empty( $useful ) ? $useful : $words, 0, 8 );
		while ( count( $chosen ) > 1 && in_array( strtolower( end( $chosen ) ), $filler, true ) ) {
			array_pop( $chosen );
		}
		return sanitize_title( implode( ' ', $chosen ) );
	}

	/** @param string $content Clean article HTML. */
	protected function build_post_excerpt( $content ) {
		$content = preg_replace( '#<h[2-6]\b[^>]*>\s*Table of Contents\s*</h[2-6]>\s*<ul\b[^>]*class=("|\')[^"\']*\btoc\b[^"\']*\1[^>]*>.*?</ul>#is', '', (string) $content );
		$text    = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $content ) ) );
		if ( '' === $text ) {
			return '';
		}
		if ( function_exists( 'wp_trim_words' ) ) {
			return wp_trim_words( $text, 32, '…' );
		}
		$words = preg_split( '/\s+/u', $text );
		return implode( ' ', array_slice( $words, 0, 32 ) ) . ( count( $words ) > 32 ? '…' : '' );
	}

	/**
	 * Exact selected headings absent from the final reviewed HTML.
	 * Duplicate selections count once; entities, case and whitespace are the
	 * only normalisations, so a renamed section cannot masquerade as coverage.
	 *
	 * @param string $content Final reviewed HTML.
	 * @param mixed  $outline Stored outline selection.
	 * @return array Missing headings in selection order.
	 */
	protected function missing_outline_headings( $content, $outline ) {
		if ( ! is_array( $outline ) || empty( $outline ) ) {
			return array();
		}
		preg_match_all( '/<h[2-6]\b[^>]*>(.*?)<\/h[2-6]>/is', (string) $content, $matches );
		$actual = array();
		foreach ( isset( $matches[1] ) ? $matches[1] : array() as $heading ) {
			$actual[ $this->normalise_heading_identity( $heading ) ] = true;
		}
		$missing = array();
		$seen    = array();
		foreach ( $outline as $heading ) {
			$label    = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( (string) $heading, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) );
			$identity = $this->normalise_heading_identity( $label );
			if ( '' !== $identity && ! isset( $seen[ $identity ] ) && ! isset( $actual[ $identity ] ) ) {
				$missing[] = $label;
			}
			$seen[ $identity ] = true;
		}
		return $missing;
	}

	/** @param string $heading Heading text or HTML. */
	private function normalise_heading_identity( $heading ) {
		$plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( (string) $heading, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $plain, 'UTF-8' ) : strtolower( $plain );
	}

	/**
	 * Assemble the full article HTML from conversation selections.
	 *
	 * @param array $selections
	 * @return string
	 */
	protected function assemble_article_html( array $selections ) {
		$parts = array();
		if ( ! empty( $selections['body'] ) ) {
			$parts[] = (string) $selections['body'];
		}
		if ( ! empty( $selections['conclusion'] ) ) {
			$parts[] = (string) $selections['conclusion'];
		}
		if ( ! empty( $selections['qna'] ) && is_array( $selections['qna'] ) ) {
			$tag = 'h2';
			foreach ( $selections['qna'] as $pair ) {
				if ( is_array( $pair ) && isset( $pair['question'], $pair['answer'] ) ) {
					$parts[] = "<{$tag}>" . esc_html( $pair['question'] ) . "</{$tag}>\n<p>" . esc_html( $pair['answer'] ) . '</p>';
				}
			}
		}
		return implode( "\n", $parts );
	}

	/**
	 * Set featured image for post
	 *
	 * @param int $post_id WordPress post ID
	 * @param int $attachment_id Attachment ID for featured image
	 * @return bool Success status
	 */
	public function set_featured_image( $post_id, $attachment_id ) {
		$this->log_debug(
			'Setting featured image',
			array(
				'post_id'       => $post_id,
				'attachment_id' => $attachment_id,
			)
		);

		try {
			// Validate attachment exists
			if ( ! get_post_status( $attachment_id ) ) {
				$this->log_error( 'Attachment does not exist', array( 'attachment_id' => $attachment_id ) );
				return false;
			}

			$result = set_post_thumbnail( $post_id, $attachment_id );

			if ( $result ) {
				$this->log_info( 'Featured image set successfully' );
			} else {
				$this->log_error( 'Failed to set featured image' );
			}

			return $result;

		} catch ( Exception $e ) {
			$this->handle_error( 'Failed to set featured image', $e );
			return false;
		}
	}

	/**
	 * Get service health status
	 *
	 * @return array Health status information
	 */
	public function get_health_status() {
		$base_status = parent::get_health_status();

		$base_status['dependencies'] = array(
			'wordpress_adapter' => isset( $this->wordpress_adapter ),
			'security_service'  => isset( $this->security_service ),
			'config'            => isset( $this->config ),
		);

		$base_status['seo_plugins'] = array(
			'yoast'    => defined( 'WPSEO_FILE' ),
			'aioseop'  => defined( 'AIOSEO_VERSION' ) || defined( 'AIOSEOP_VERSION' ),
			'rankmath' => defined( 'RANK_MATH_FILE' ),
			'seopress' => defined( 'SEOPRESS_VERSION' ),
		);

		return $base_status;
	}
}
