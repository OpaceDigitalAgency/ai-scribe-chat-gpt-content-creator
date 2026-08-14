<?php
/**
 * AI-Scribe uninstall cleanup.
 *
 * Runs when the plugin is DELETED from the Plugins screen. Nothing is
 * removed unless the user has opted in via the "Delete all plugin data on
 * uninstall" setting (option ai_scribe_delete_data_on_uninstall === 'yes'),
 * mirroring the 2.6.2 behaviour and satisfying the WP.org guideline that
 * plugins clean up after themselves without destroying data by surprise.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( 'yes' !== get_option( 'ai_scribe_delete_data_on_uninstall' ) ) {
	return; // The user did not opt in — leave everything in place.
}

global $wpdb;

// Custom tables: saved shortcodes (2.6.2 + v3) and v3 conversations.
foreach ( array( 'article_builder', 'ai_scribe_conversations' ) as $ai_scribe_table_suffix ) {
	$ai_scribe_table = $wpdb->prefix . $ai_scribe_table_suffix;
	$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $ai_scribe_table ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- schema teardown at uninstall; identifier escaped.
}

// Every option the plugin has ever written (v3 + legacy ab_* keys).
$ai_scribe_options = array(
	// Grouped settings.
	'ab_gpt_ai_engine_settings',
	'ab_gpt_content_settings',
	'ab_gpt_image_settings',
	'ab_prompts_content',
	'ai_scribe_general_settings',
	// API keys (legacy individual options).
	'ab_api_key',
	'ab_anthropic_api_key',
	// Legacy scalar mirrors.
	'ab_model',
	'ab_heading_tag',
	'ab_image_format',
	'ab_image_background',
	'ab_image_style',
	'ab_image_size',
	'ab_image_quality',
	'ab_image_prompts',
	'ab_enable_image_generation',
	'ab_check_Arr',
	'ab_language',
	'ab_writing_style',
	'ab_writing_tone',
	'ab_number_of_heading',
	'ab_custom_instructions',
	'ab_mode_instructions',
	'ab_system_messages',
	'ab_humanize_mode',
	'ab_temp',
	'ab_top_p',
	'ab_n',
	'ab_best_oi',
	'ab_freq_pent',
	// v3 state.
	'ai_scribe_languages',
	'ai_scribe_conversations_schema',
	'ai_scribe_prompt_text',
	'ai_scribe_current_prompt',
	'ai_scribe_step_prompts',
	'ai_scribe_language',
	'ai_scribe_writing_style',
	'ai_scribe_writing_tone',
	'ai_scribe_heading_tag',
	'ai_scribe_mock_request_log',
	'ai_scribe_delete_data_on_uninstall',
	// Migration and notice flags.
	'ai_scribe_v3_migrated',
	'ai_scribe_migrated_from_v2',
	'ai_scribe_hub_prompts_migrated',
	'ai_scribe_hub_prompts_refreshed',
	'ai_scribe_hub_keys_migrated',
	'ai_scribe_hub_prompt_map',
	'ai_scribe_model_remap_notice',
	'ai_scribe_retired_params_notice',
	'ai_scribe_onboarding_dismissed',
);

foreach ( $ai_scribe_options as $ai_scribe_option ) {
	delete_option( $ai_scribe_option );
}

// Live model-list transients (ai_scribe_models_<provider>_<hash>) and
// key-validation transients (ai_scribe_valid_<provider>_<hash>).
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_ai_scribe_models_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_ai_scribe_models_' ) . '%',
		$wpdb->esc_like( '_transient_ai_scribe_valid_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_ai_scribe_valid_' ) . '%'
	)
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- transient teardown at uninstall.

// Per-user UI preferences.
delete_metadata( 'user', 0, 'ai_scribe_ui_prefs', '', true );
