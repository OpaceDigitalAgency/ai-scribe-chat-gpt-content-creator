<?php
/**
 * AI-Scribe v3 smoke test (no WordPress required).
 *
 * Defines minimal WP shims and requires article_builder.php with AI-Core
 * deliberately absent. AI-Core is an external required plugin, so this smoke
 * run verifies the dependency guard and adapter fail-closed behaviour without
 * inventing a bundled library or making a provider request. Any fatal, warning
 * or uncaught exception fails the run.
 *
 * Usage: php scripts/smoke.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$failures = [];

set_error_handler(function ($severity, $message, $file, $line) use (&$failures) {
    // Deprecations in copied v4 code are logged but non-fatal for the smoke run
    if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
        return true;
    }
    $failures[] = "PHP error [{$severity}] {$message} in {$file}:{$line}";
    return true;
});

// ---------------------------------------------------------------------------
// Minimal WordPress shims
// ---------------------------------------------------------------------------

define('ABSPATH', __DIR__ . '/../');
define('WP_DEBUG', false);

function plugin_dir_path($file) { return rtrim(dirname($file), '/\\') . '/'; }
function plugin_dir_url($file) { return 'http://example.test/wp-content/plugins/' . basename(dirname($file)) . '/'; }
function plugin_basename($file) { return basename(dirname($file)) . '/' . basename($file); }
function add_action(...$args) { return true; }
function add_filter(...$args) { return true; }
function do_action(...$args) { return null; }
function apply_filters($tag, $value, ...$args) { return $value; }
function register_activation_hook(...$args) { return true; }
function register_deactivation_hook(...$args) { return true; }
function register_uninstall_hook(...$args) { return true; }
function get_option($name, $default = false) { return []; }
function update_option(...$args) { return true; }
function delete_option(...$args) { return true; }
function get_transient($name) { return false; }
function set_transient(...$args) { return true; }
function delete_transient(...$args) { return true; }
function load_plugin_textdomain(...$args) { return true; }
function wp_upload_dir() { return ['basedir' => sys_get_temp_dir(), 'baseurl' => 'http://example.test/uploads']; }
function trailingslashit($s) { return rtrim($s, '/\\') . '/'; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_url($s) { return (string) $s; }
function sanitize_text_field($s) { return is_string($s) ? trim($s) : $s; }
function wp_json_encode($data, $options = 0, $depth = 512) { return json_encode($data, $options, $depth); }
function __($text, $domain = 'default') { return $text; }
function _e($text, $domain = 'default') { echo $text; }
function is_admin() { return true; }
function current_user_can(...$args) { return true; }
function wp_create_nonce($action = -1) { return 'smoke-nonce'; }
function admin_url($path = '') { return 'http://example.test/wp-admin/' . $path; }
function wp_send_json_success($data = null) { echo json_encode(['success' => true, 'data' => $data]); }
function wp_send_json_error($data = null) { echo json_encode(['success' => false, 'data' => $data]); }
function is_wp_error($thing) { return $thing instanceof WP_Error; }
function current_time($type = 'mysql', $gmt = 0) { return $type === 'timestamp' ? time() : gmdate('Y-m-d H:i:s'); }
function wp_kses($string, $allowed_html = [], $allowed_protocols = []) { return $string; }
function wp_kses_post($string) { return $string; }
function wp_parse_args($args, $defaults = []) { return array_merge((array) $defaults, (array) $args); }
function add_shortcode(...$args) { return true; }
function wp_verify_nonce(...$args) { return 1; }
function wp_unslash($value) { return $value; }
function get_bloginfo($show = '') { return 'Smoke Test Site'; }
function wp_enqueue_script(...$args) { return true; }
function wp_enqueue_style(...$args) { return true; }
function wp_register_script(...$args) { return true; }
function wp_register_style(...$args) { return true; }
function wp_localize_script(...$args) { return true; }

class WP_Error
{
    private $code;
    private $message;
    public function __construct($code = '', $message = '', $data = '')
    {
        $this->code = $code;
        $this->message = $message;
    }
    public function get_error_message() { return $this->message; }
    public function get_error_code() { return $this->code; }
}

// ---------------------------------------------------------------------------
// Boot the plugin
// ---------------------------------------------------------------------------

$checks = [];
$plugin_file = __DIR__ . '/../article_builder.php';
$plugin_source = file_get_contents($plugin_file);
$header_version = null;
if (preg_match('/^\s*\* Version:\s*([^\r\n]+)/m', $plugin_source, $version_match)) {
    $header_version = trim($version_match[1]);
}

try {
    require $plugin_file;
    $checks['bootstrap_required'] = true;
} catch (Throwable $e) {
    $failures[] = 'Bootstrap threw: ' . $e->getMessage();
    $checks['bootstrap_required'] = false;
}

$checks['header_version_found'] = is_string($header_version) && $header_version !== '';
$checks['version_constant_agrees'] = defined('AI_SCRIBE_VERSION')
    && AI_SCRIBE_VERSION === $header_version;
$checks['version_alias_agrees'] = defined('AI_SCRIBE_VER')
    && defined('AI_SCRIBE_VERSION')
    && AI_SCRIBE_VER === AI_SCRIBE_VERSION;
$checks['external_ai_core_declared'] = preg_match('/^\s*\* Requires Plugins:\s*opace-ai-core-openai-claude-gemini\s*$/m', $plugin_source) === 1;
$checks['no_bundled_ai_core'] = !is_dir(__DIR__ . '/../ai-core');
$checks['hub_absent_for_smoke'] = !function_exists('ai_core')
    && !AI_Scribe_Onboarding_Notice::hub_active();

// Plugin container booted with all core services
try {
    $container = function_exists('ai_scribe_get_container') ? ai_scribe_get_container() : null;
    $checks['container_available'] = $container instanceof AI_Scribe_Service_Container;
    if ($container) {
        $adapter = $container->get('ai_core_adapter');
        $checks['adapter_resolves'] = $adapter instanceof AI_Scribe_AI_Core_Adapter;
        $health = $adapter->get_health_status();
        $checks['adapter_four_providers'] = isset(
            $health['providers_available']['openai'],
            $health['providers_available']['anthropic'],
            $health['providers_available']['gemini'],
            $health['providers_available']['grok']
        );
        $missing_hub = $adapter->generate_text('smoke-model', [['role' => 'user', 'content' => 'No request must be sent.']]);
        $checks['adapter_fails_closed'] = is_wp_error($missing_hub)
            && $missing_hub->get_error_code() === 'ai_core_hub_missing';
    }
} catch (Throwable $e) {
    $failures[] = 'Container/adapter check failed: ' . $e->getMessage();
    $checks['container_available'] = false;
}

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------

echo "AI-Scribe v3 smoke test\n";
echo str_repeat('-', 40) . "\n";
foreach ($checks as $name => $ok) {
    printf("%-28s %s\n", $name, $ok ? 'PASS' : 'FAIL');
    if (!$ok) {
        $failures[] = "Check failed: {$name}";
    }
}

if ($failures) {
    echo str_repeat('-', 40) . "\nFAILURES:\n";
    foreach (array_unique($failures) as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}

echo str_repeat('-', 40) . "\nAll smoke checks passed.\n";
exit(0);
