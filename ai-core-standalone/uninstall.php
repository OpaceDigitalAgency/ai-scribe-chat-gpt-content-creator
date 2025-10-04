<?php
/**
 * AI-Core Uninstall Script
 * 
 * Fired when the plugin is uninstalled
 * 
 * @package AI_Core
 * @version 1.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('ai_core_settings');
delete_option('ai_core_stats');
delete_option('ai_core_version');

// Delete transients
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ai_core_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ai_core_%'");

// Clear any cached data
wp_cache_flush();

