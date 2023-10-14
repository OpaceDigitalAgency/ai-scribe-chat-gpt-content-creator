<?php
// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
require_once plugin_dir_path(__FILE__) . 'article_builder.php';
$my_plugin = new AI_Scribe();
$my_plugin->uninstall();