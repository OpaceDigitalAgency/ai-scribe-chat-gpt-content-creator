<?php
/**
 * Front-end SEO meta output for AI-Scribe.
 *
 * When no supported SEO plugin is active, the meta title and description a
 * generation saved to `_ai_scribe_meta_title` / `_ai_scribe_meta_description`
 * would otherwise have no front-end effect at all (C-2-4 / L-26). This class
 * makes those values real on a vanilla site:
 *
 * - `pre_get_document_title` serves the stored meta title as the <title>.
 * - `wp_head` prints a <meta name="description"> tag.
 *
 * Both hooks stand down entirely when Yoast, AIOSEO (v4 or legacy), Rank Math
 * or SEOPress is active — those plugins own the head, and PostService has
 * already written their fields.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Scribe_Frontend_Meta {

	const META_TITLE_KEY       = '_ai_scribe_meta_title';
	const META_DESCRIPTION_KEY = '_ai_scribe_meta_description';

	/**
	 * Wire the front-end hooks. Called from the plugin bootstrap on every
	 * load (admin requests never reach the two callbacks).
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_document_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'output_meta_description' ), 1 );
	}

	/**
	 * Is a supported SEO plugin handling the head?
	 *
	 * @return bool
	 */
	public static function seo_plugin_active() {
		return defined( 'WPSEO_FILE' )        // Yoast SEO.
			|| defined( 'AIOSEO_VERSION' )    // All in One SEO v4.
			|| defined( 'AIOSEOP_VERSION' )   // All in One SEO Pack v3 (legacy).
			|| defined( 'RANK_MATH_FILE' )    // Rank Math.
			|| defined( 'SEOPRESS_VERSION' ); // SEOPress.
	}

	/**
	 * The stored AI-Scribe meta value for the current singular view, or ''.
	 *
	 * @param string $meta_key Post meta key.
	 * @return string
	 */
	private static function current_meta( $meta_key ) {
		if ( self::seo_plugin_active() || is_admin() || ! is_singular() ) {
			return '';
		}
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return '';
		}
		return trim( (string) get_post_meta( $post_id, $meta_key, true ) );
	}

	/**
	 * pre_get_document_title: serve the generated meta title.
	 *
	 * @param string $title Short-circuit value from earlier filters.
	 * @return string
	 */
	public static function filter_document_title( $title ) {
		if ( '' !== $title ) {
			return $title; // Another plugin got there first.
		}
		$meta_title = self::current_meta( self::META_TITLE_KEY );
		return '' !== $meta_title ? $meta_title : $title;
	}

	/**
	 * wp_head: print the generated meta description.
	 *
	 * @return void
	 */
	public static function output_meta_description() {
		$description = self::current_meta( self::META_DESCRIPTION_KEY );
		if ( '' === $description ) {
			return;
		}
		echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $description ) ) . '" />' . "\n";
	}
}
