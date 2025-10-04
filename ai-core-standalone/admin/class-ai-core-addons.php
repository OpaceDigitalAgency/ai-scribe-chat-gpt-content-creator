<?php
/**
 * AI-Core Add-ons Class
 * 
 * Handles add-ons library and discovery
 * 
 * @package AI_Core
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI-Core Add-ons Class
 * 
 * Manages add-ons library
 */
class AI_Core_Addons {
    
    /**
     * Class instance
     * 
     * @var AI_Core_Addons
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return AI_Core_Addons
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Private constructor for singleton
    }
    
    /**
     * Get available add-ons
     * 
     * @return array List of add-ons
     */
    public function get_addons() {
        return array(
            array(
                'slug' => 'ai-scribe',
                'name' => 'AI-Scribe',
                'description' => 'Professional AI-powered content creation plugin. Generate SEO-optimised articles, blog posts, and content with GPT-4.5, OpenAI o3, Claude Sonnet 4, and more.',
                'author' => 'Opace Digital Agency',
                'version' => '6.5',
                'requires' => 'AI-Core 1.0+',
                'installed' => $this->is_plugin_installed('ai-scribe'),
                'active' => $this->is_plugin_active('ai-scribe'),
                'icon' => 'dashicons-edit',
                'url' => 'https://opace.agency/ai-scribe',
            ),
            array(
                'slug' => 'ai-imagen',
                'name' => 'AI-Imagen',
                'description' => 'AI-powered image generation plugin using OpenAI DALL-E and GPT-Image-1. Generate stunning, high-quality images directly in WordPress with automatic media library integration.',
                'author' => 'Opace Digital Agency',
                'version' => '1.2',
                'requires' => 'AI-Core 1.0+',
                'installed' => $this->is_plugin_installed('ai-imagen'),
                'active' => $this->is_plugin_active('ai-imagen'),
                'icon' => 'dashicons-format-image',
                'url' => 'https://opace.agency/ai-imagen',
            ),
        );
    }
    
    /**
     * Check if plugin is installed
     * 
     * @param string $slug Plugin slug
     * @return bool True if installed
     */
    private function is_plugin_installed($slug) {
        $plugins = get_plugins();
        
        foreach ($plugins as $plugin_file => $plugin_data) {
            if (strpos($plugin_file, $slug) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if plugin is active
     * 
     * @param string $slug Plugin slug
     * @return bool True if active
     */
    private function is_plugin_active($slug) {
        $plugins = get_plugins();
        
        foreach ($plugins as $plugin_file => $plugin_data) {
            if (strpos($plugin_file, $slug) !== false) {
                return is_plugin_active($plugin_file);
            }
        }
        
        return false;
    }
    
    /**
     * Render add-ons page
     *
     * @return void
     */
    public function render_addons_page() {
        $addons = $this->get_addons();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI-Core Add-ons', 'ai-core'); ?></h1>
            
            <p class="description">
                <?php esc_html_e('Extend AI-Core functionality with these powerful add-on plugins. All add-ons automatically use your configured API keys from AI-Core.', 'ai-core'); ?>
            </p>
            
            <div class="ai-core-addons-grid">
                <?php foreach ($addons as $addon): ?>
                    <div class="ai-core-addon-card <?php echo $addon['active'] ? 'active' : ''; ?>">
                        <div class="addon-icon">
                            <span class="dashicons <?php echo esc_attr($addon['icon']); ?>"></span>
                        </div>
                        <div class="addon-content">
                            <h3><?php echo esc_html($addon['name']); ?></h3>
                            <p class="addon-description"><?php echo esc_html($addon['description']); ?></p>
                            <div class="addon-meta">
                                <span class="addon-author"><?php echo esc_html__('By', 'ai-core') . ' ' . esc_html($addon['author']); ?></span>
                                <span class="addon-version"><?php echo esc_html__('Version', 'ai-core') . ' ' . esc_html($addon['version']); ?></span>
                            </div>
                            <div class="addon-requires">
                                <span class="dashicons dashicons-info"></span>
                                <?php echo esc_html__('Requires:', 'ai-core') . ' ' . esc_html($addon['requires']); ?>
                            </div>
                        </div>
                        <div class="addon-actions">
                            <?php if ($addon['active']): ?>
                                <span class="button button-disabled">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php esc_html_e('Active', 'ai-core'); ?>
                                </span>
                            <?php elseif ($addon['installed']): ?>
                                <span class="button button-secondary">
                                    <?php esc_html_e('Installed', 'ai-core'); ?>
                                </span>
                            <?php else: ?>
                                <a href="<?php echo esc_url($addon['url']); ?>" class="button button-primary" target="_blank">
                                    <?php esc_html_e('Learn More', 'ai-core'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="ai-core-addons-info">
                <h2><?php esc_html_e('Developing Add-ons', 'ai-core'); ?></h2>
                <p><?php esc_html_e('AI-Core provides a simple API for developers to create add-on plugins. Your add-ons can access all configured AI providers without requiring users to enter API keys again.', 'ai-core'); ?></p>
                
                <h3><?php esc_html_e('Example Usage', 'ai-core'); ?></h3>
                <pre><code>&lt;?php
// Check if AI-Core is available
if (function_exists('ai_core')) {
    $ai_core = ai_core();
    
    // Check if configured
    if ($ai_core->is_configured()) {
        // Send a text generation request
        $response = $ai_core->send_text_request(
            'gpt-4o',
            array(
                array('role' => 'user', 'content' => 'Hello, AI!')
            ),
            array('max_tokens' => 100)
        );
        
        if (!is_wp_error($response)) {
            echo $response['choices'][0]['message']['content'];
        }
    }
}
?&gt;</code></pre>
                
                <p>
                    <a href="https://opace.agency/ai-core/docs" class="button" target="_blank">
                        <?php esc_html_e('View Documentation', 'ai-core'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
}

