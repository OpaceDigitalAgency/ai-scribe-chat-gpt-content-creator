<?php
/**
 * AI-Scribe v3 PHP test bootstrap (no WordPress required).
 *
 * Extends the scripts/smoke.php shim approach with a FUNCTIONAL options
 * store (get_option/update_option backed by an array) so migration and
 * prompt-library tests exercise real read/write behaviour. Boots the
 * full plugin container.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '1');

define('ABSPATH', __DIR__ . '/../../');
define('WP_DEBUG', false);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);

// ---------------------------------------------------------------------------
// Functional options store
// ---------------------------------------------------------------------------

$GLOBALS['__test_options']    = [];
$GLOBALS['__test_transients'] = [];
$GLOBALS['__test_last_insert_post'] = [];
$GLOBALS['__test_last_update_post'] = [];
$GLOBALS['__test_post_meta'] = [];
$GLOBALS['__test_terms'] = [];
$GLOBALS['__test_post_categories'] = [];
$GLOBALS['__test_post_tags'] = [];
$GLOBALS['__test_attachment_ids'] = [];
$GLOBALS['__test_attachment_metadata'] = [];
$GLOBALS['__test_capabilities'] = [];

function get_option($name, $default = false)
{
    return array_key_exists($name, $GLOBALS['__test_options']) ? $GLOBALS['__test_options'][$name] : $default;
}
function update_option($name, $value)
{
    $GLOBALS['__test_options'][$name] = $value;
    return true;
}
function delete_option($name)
{
    unset($GLOBALS['__test_options'][$name]);
    return true;
}
function test_reset_options()
{
    $GLOBALS['__test_options'] = [];
    $GLOBALS['__test_transients'] = [];
}

// ---------------------------------------------------------------------------
// Minimal WordPress shims (mirrors scripts/smoke.php)
// ---------------------------------------------------------------------------

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
function get_transient($name) { return array_key_exists($name, $GLOBALS['__test_transients']) ? $GLOBALS['__test_transients'][$name] : false; }
function set_transient($name, $value, $expiration = 0) { $GLOBALS['__test_transients'][$name] = $value; return true; }
function delete_transient($name) { unset($GLOBALS['__test_transients'][$name]); return true; }
function wp_cache_delete(...$args) { return true; }
function load_plugin_textdomain(...$args) { return true; }
function wp_upload_dir() { return ['basedir' => sys_get_temp_dir(), 'baseurl' => 'http://example.test/uploads']; }
function trailingslashit($s) { return rtrim($s, '/\\') . '/'; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_url($s) { return (string) $s; }
function sanitize_text_field($s) { return is_string($s) ? trim($s) : $s; }
function sanitize_textarea_field($s) { return is_string($s) ? trim($s) : $s; }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $s)); }
function sanitize_title($s) { return strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim((string) $s))); }
function wp_json_encode($data, $options = 0, $depth = 512) { return json_encode($data, $options, $depth); }
function __($text, $domain = 'default') { return $text; }
function _e($text, $domain = 'default') { echo $text; }
function is_admin() { return true; }
function current_user_can($capability, ...$args) { return array_key_exists($capability, $GLOBALS['__test_capabilities']) ? (bool) $GLOBALS['__test_capabilities'][$capability] : true; }
$GLOBALS['__test_current_user_id'] = 1;
function get_current_user_id() { return isset($GLOBALS['__test_current_user_id']) ? (int) $GLOBALS['__test_current_user_id'] : 0; }
function wp_create_nonce($action = -1) { return 'test-nonce'; }
function wp_verify_nonce(...$args) { return 1; }
function wp_unslash($value) { return $value; }
function admin_url($path = '') { return 'http://example.test/wp-admin/' . $path; }
function wp_send_json_success($data = null) { echo json_encode(['success' => true, 'data' => $data]); }
function wp_send_json_error($data = null) { echo json_encode(['success' => false, 'data' => $data]); }
function is_wp_error($thing) { return $thing instanceof WP_Error; }
function current_time($type = 'mysql', $gmt = 0) { return $type === 'timestamp' ? time() : gmdate('Y-m-d H:i:s'); }
function wp_kses($string, $allowed_html = [], $allowed_protocols = []) { return $string; }
function wp_kses_post($string) { return $string; }
function wp_parse_args($args, $defaults = []) { return array_merge((array) $defaults, (array) $args); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function add_shortcode(...$args) { return true; }
function get_bloginfo($show = '') { return 'Test Site'; }
function wp_enqueue_script(...$args) { return true; }
function wp_enqueue_style(...$args) { return true; }
function wp_register_script(...$args) { return true; }
function wp_register_style(...$args) { return true; }
function wp_localize_script(...$args) { return true; }
function wp_mkdir_p($dir) { return is_dir($dir) || mkdir($dir, 0777, true); }
function wp_insert_post($args) { static $id = 100; $GLOBALS['__test_last_insert_post'] = $args; return ++$id; }
function wp_update_post($args, $wp_error = false) { $GLOBALS['__test_last_update_post'] = $args; return isset($args['ID']) ? (int) $args['ID'] : 0; }
function get_post($id) { return null; }
function get_permalink($id) { return 'http://example.test/?p=' . $id; }
function update_post_meta($post_id, $key, $value) { $GLOBALS['__test_post_meta'][$post_id][$key] = $value; return true; }
function term_exists($term, $taxonomy = '', $parent = null) { return isset($GLOBALS['__test_terms'][$taxonomy][$term]) ? ['term_id' => $GLOBALS['__test_terms'][$taxonomy][$term]] : false; }
function wp_insert_term($term, $taxonomy, $args = []) { $id = count($GLOBALS['__test_terms'][$taxonomy] ?? []) + 201; $GLOBALS['__test_terms'][$taxonomy][$term] = $id; return ['term_id' => $id]; }
function wp_set_post_categories($post_id, $post_categories = [], $append = false) { $GLOBALS['__test_post_categories'][$post_id] = array_values($post_categories); return $GLOBALS['__test_post_categories'][$post_id]; }
function wp_set_post_tags($post_id, $tags = '', $append = false) { $GLOBALS['__test_post_tags'][$post_id] = array_values((array) $tags); return $GLOBALS['__test_post_tags'][$post_id]; }
function attachment_url_to_postid($url) { return isset($GLOBALS['__test_attachment_ids'][$url]) ? (int) $GLOBALS['__test_attachment_ids'][$url] : 0; }
function wp_get_attachment_metadata($attachment_id) { return isset($GLOBALS['__test_attachment_metadata'][$attachment_id]) ? $GLOBALS['__test_attachment_metadata'][$attachment_id] : false; }
function wp_get_attachment_url($attachment_id) { $url = array_search((int) $attachment_id, $GLOBALS['__test_attachment_ids'], true); return false === $url ? false : $url; }
function has_post_thumbnail($id) { return false; }
function wp_attachment_is_image($id) { return false; }
function set_post_thumbnail($post_id, $attachment_id) { return true; }
function check_ajax_referer(...$args) { return 1; }
function wp_strip_all_tags($string, $remove_breaks = false) { return trim(strip_tags((string) $string)); }
function wp_html_excerpt($str, $count, $more = null) { return mb_substr(wp_strip_all_tags($str), 0, $count); }

// ---------------------------------------------------------------------------
// WP 7.0 Abilities API shims (capture registrations for P4 tests).
// Real signatures verified against wp-includes/abilities-api.php on WP 7.0.
// ---------------------------------------------------------------------------

$GLOBALS['__test_abilities'] = [];
$GLOBALS['__test_ability_categories'] = [];

function wp_register_ability($name, array $args)
{
    $GLOBALS['__test_abilities'][$name] = $args;
    return (object) ['name' => $name];
}
function wp_register_ability_category($slug, array $args)
{
    $GLOBALS['__test_ability_categories'][$slug] = $args;
    return (object) ['slug' => $slug];
}

/**
 * Minimal container stub: fixed id => instance map (P4 ability tests).
 */
class AI_Scribe_Test_Stub_Container
{
    private $services;
    public function __construct(array $services) { $this->services = $services; }
    public function has($id) { return array_key_exists($id, $this->services); }
    public function get($id) { return $this->services[$id]; }
}

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

/*
 * The Opace AI Hub library is no longer bundled with this plugin — the hub owns
 * the single copy. The harness therefore loads it the same way a real site
 * does: from the hub plugin, wherever it is checked out.
 */
if ( ! class_exists( 'AICore\\AICore' ) ) {
	foreach ( array(
		__DIR__ . '/../../../AI CORE MODULAR/ai-core-standalone/lib/autoload.php',
		__DIR__ . '/../../../ai-core/lib/autoload.php',
		__DIR__ . '/../../../ai-core-standalone/lib/autoload.php',
	) as $ai_scribe_hub_autoload ) {
		if ( file_exists( $ai_scribe_hub_autoload ) ) {
			require_once $ai_scribe_hub_autoload;
			break;
		}
	}
}

if ( ! class_exists( 'AICore\\AICore' ) ) {
	fwrite( STDERR, "Opace AI Hub library not found. Check out the ai-core plugin beside this one.\n" );
	exit( 1 );
}

require __DIR__ . '/../../article_builder.php';

// ---------------------------------------------------------------------------
// Tiny test runner
// ---------------------------------------------------------------------------

$GLOBALS['__tests'] = ['pass' => 0, 'fail' => 0, 'failures' => []];

function test_assert($condition, $label)
{
    if ($condition) {
        $GLOBALS['__tests']['pass']++;
        echo "  ok    {$label}\n";
    } else {
        $GLOBALS['__tests']['fail']++;
        $GLOBALS['__tests']['failures'][] = $label;
        echo "  FAIL  {$label}\n";
    }
}

function test_assert_contains($needle, $haystack, $label)
{
    test_assert(is_string($haystack) && strpos($haystack, $needle) !== false, $label . " [expects: {$needle}]");
}

function test_assert_not_contains($needle, $haystack, $label)
{
    test_assert(is_string($haystack) && strpos($haystack, $needle) === false, $label . " [must not contain: {$needle}]");
}

function test_section($name)
{
    echo "\n== {$name} ==\n";
}

function test_summary()
{
    $t = $GLOBALS['__tests'];
    echo "\n" . str_repeat('-', 50) . "\n";
    echo "Passed: {$t['pass']}  Failed: {$t['fail']}\n";
    if ($t['fail'] > 0) {
        foreach ($t['failures'] as $f) {
            echo "  - {$f}\n";
        }
        exit(1);
    }
    echo "All tests passed.\n";
    exit(0);
}

/**
 * Mock AI adapter: returns queued responses in order (FIFO), then the
 * last one repeatedly. Records every request for assertions.
 */
class AI_Scribe_Test_Mock_Adapter
{
    public $requests = [];
    private $queue = [];

    public function queue($content, array $usage = ['prompt_tokens' => 1000, 'completion_tokens' => 300, 'total_tokens' => 1300])
    {
        $this->queue[] = ['content' => $content, 'usage' => $usage];
        return $this;
    }

    public function generate_text($model, array $messages, array $options = [])
    {
        $this->requests[] = ['model' => $model, 'messages' => $messages, 'options' => $options];
        $item = array_shift($this->queue);
        if ($item === null) {
            return new WP_Error('no_mock', 'No mock response queued');
        }
        if ($item['content'] instanceof WP_Error) {
            return $item['content'];
        }
        return [
            'content' => $item['content'],
            'model' => $model,
            'usage' => $item['usage'],
            'raw_response' => [],
        ];
    }
}
