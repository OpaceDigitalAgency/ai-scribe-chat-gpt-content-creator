<?php
/**
 * AI-Core Uninstall Script
 *
 * Handles plugin uninstallation and cleanup
 * Respects the "persist_on_uninstall" setting
 *
 * @package AI_Core
 * @version 0.0.1
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Get plugin settings
$settings = get_option('ai_core_settings', array());

// Check if user wants to persist settings (default: true)
$persist_on_uninstall = isset($settings['persist_on_uninstall']) ? $settings['persist_on_uninstall'] : true;

// If persist is disabled, delete all plugin data
if (!$persist_on_uninstall) {
    // Delete plugin options
    delete_option('ai_core_settings');
    delete_option('ai_core_stats');
    delete_option('ai_core_version');
    delete_option('ai_core_cache');

    // Delete transients
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ai_core_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ai_core_%'");

    // Delete prompt library data if tables exist
    $prompts_table = $wpdb->prefix . 'ai_core_prompts';
    $groups_table = $wpdb->prefix . 'ai_core_prompt_groups';

    // Check if tables exist before dropping
    $prompts_exists = $wpdb->get_var("SHOW TABLES LIKE '{$prompts_table}'") === $prompts_table;
    $groups_exists = $wpdb->get_var("SHOW TABLES LIKE '{$groups_table}'") === $groups_table;

    if ($prompts_exists) {
        $wpdb->query("DROP TABLE IF EXISTS {$prompts_table}");
    }

    if ($groups_exists) {
        $wpdb->query("DROP TABLE IF EXISTS {$groups_table}");
    }

    // Clear any cached data
    wp_cache_flush();
}
// If persist is enabled (default), keep all settings and data
// This allows users to reinstall without losing their API keys and prompts

