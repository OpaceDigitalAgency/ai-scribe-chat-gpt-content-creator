<?php
/**
 * AI-Core Settings Class
 * 
 * Handles plugin settings management using WordPress Settings API
 * 
 * @package AI_Core
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI-Core Settings Class
 * 
 * Manages plugin settings and configuration
 */
class AI_Core_Settings {
    
    /**
     * Class instance
     * 
     * @var AI_Core_Settings
     */
    private static $instance = null;
    
    /**
     * Settings group name
     * 
     * @var string
     */
    private $settings_group = 'ai_core_settings_group';
    
    /**
     * Settings page slug
     * 
     * @var string
     */
    private $settings_page = 'ai-core-settings';
    
    /**
     * Option name
     * 
     * @var string
     */
    private $option_name = 'ai_core_settings';
    
    /**
     * Get class instance
     * 
     * @return AI_Core_Settings
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
        $this->init();
    }
    
    /**
     * Initialize settings
     *
     * @return void
     */
    private function init() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register plugin settings
     *
     * @return void
     */
    public function register_settings() {
        // Register settings
        register_setting(
            $this->settings_group,
            $this->option_name,
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => $this->get_default_settings(),
                'show_in_rest' => false
            )
        );

        // Add settings sections
        $this->add_settings_sections();

        // Add settings fields
        $this->add_settings_fields();
    }
    
    /**
     * Add settings sections
     * 
     * @return void
     */
    private function add_settings_sections() {
        // API Keys Section
        add_settings_section(
            'ai_core_api_keys_section',
            __('API Keys Configuration', 'ai-core'),
            array($this, 'api_keys_section_callback'),
            $this->settings_page
        );
        
        // General Settings Section
        add_settings_section(
            'ai_core_general_section',
            __('General Settings', 'ai-core'),
            array($this, 'general_section_callback'),
            $this->settings_page
        );
    }
    
    /**
     * Add settings fields
     *
     * @return void
     */
    private function add_settings_fields() {
        // OpenAI API Key
        add_settings_field(
            'openai_api_key',
            __('OpenAI API Key', 'ai-core'),
            array($this, 'api_key_field_callback'),
            $this->settings_page,
            'ai_core_api_keys_section',
            array('provider' => 'openai', 'label' => 'OpenAI')
        );
        
        // Anthropic API Key
        add_settings_field(
            'anthropic_api_key',
            __('Anthropic API Key', 'ai-core'),
            array($this, 'api_key_field_callback'),
            $this->settings_page,
            'ai_core_api_keys_section',
            array('provider' => 'anthropic', 'label' => 'Anthropic Claude')
        );
        
        // Gemini API Key
        add_settings_field(
            'gemini_api_key',
            __('Google Gemini API Key', 'ai-core'),
            array($this, 'api_key_field_callback'),
            $this->settings_page,
            'ai_core_api_keys_section',
            array('provider' => 'gemini', 'label' => 'Google Gemini')
        );
        
        // Grok API Key
        add_settings_field(
            'grok_api_key',
            __('xAI Grok API Key', 'ai-core'),
            array($this, 'api_key_field_callback'),
            $this->settings_page,
            'ai_core_api_keys_section',
            array('provider' => 'grok', 'label' => 'xAI Grok')
        );
        
        // Default Provider
        add_settings_field(
            'default_provider',
            __('Default Provider', 'ai-core'),
            array($this, 'default_provider_field_callback'),
            $this->settings_page,
            'ai_core_general_section'
        );
        
        // Enable Stats
        add_settings_field(
            'enable_stats',
            __('Enable Usage Statistics', 'ai-core'),
            array($this, 'checkbox_field_callback'),
            $this->settings_page,
            'ai_core_general_section',
            array('field' => 'enable_stats', 'label' => 'Track API usage statistics')
        );
        
        // Enable Caching
        add_settings_field(
            'enable_caching',
            __('Enable Model Caching', 'ai-core'),
            array($this, 'checkbox_field_callback'),
            $this->settings_page,
            'ai_core_general_section',
            array('field' => 'enable_caching', 'label' => 'Cache available models list')
        );

        // Persist Settings on Uninstall
        add_settings_field(
            'persist_on_uninstall',
            __('Persist Settings on Uninstall', 'ai-core'),
            array($this, 'checkbox_field_callback'),
            $this->settings_page,
            'ai_core_general_section',
            array('field' => 'persist_on_uninstall', 'label' => 'Keep API keys and settings when plugin is deleted (recommended)')
        );
    }
    
    /**
     * API keys section callback
     *
     * @return void
     */
    public function api_keys_section_callback() {
        echo '<p>' . esc_html__('Configure your AI provider API keys. At least one API key is required for the plugin to function.', 'ai-core') . '</p>';
        echo '<p>' . esc_html__('Your API keys are stored securely in the WordPress database.', 'ai-core') . '</p>';
    }
    
    /**
     * General section callback
     *
     * @return void
     */
    public function general_section_callback() {
        echo '<p>' . esc_html__('Configure general plugin settings.', 'ai-core') . '</p>';
    }
    
    /**
     * API key field callback
     *
     * @param array $args Field arguments
     * @return void
     */
    public function api_key_field_callback($args) {
        $settings = get_option($this->option_name, $this->get_default_settings());
        $provider = $args['provider'];
        $field_name = $provider . '_api_key';
        $value = $settings[$field_name] ?? '';
        $masked_value = !empty($value) ? str_repeat('*', 20) . substr($value, -4) : '';
        
        echo '<div class="ai-core-api-key-field">';
        echo '<input type="password" ';
        echo 'id="' . esc_attr($field_name) . '" ';
        echo 'name="' . esc_attr($this->option_name) . '[' . esc_attr($field_name) . ']" ';
        echo 'value="' . esc_attr($value) . '" ';
        echo 'class="regular-text ai-core-api-key-input" ';
        echo 'placeholder="' . esc_attr__('Enter your API key', 'ai-core') . '" />';
        
        echo '<button type="button" class="button ai-core-test-key" data-provider="' . esc_attr($provider) . '">';
        echo esc_html__('Test Key', 'ai-core');
        echo '</button>';
        
        echo '<span class="ai-core-key-status" id="' . esc_attr($provider) . '-status"></span>';
        echo '</div>';
        
        echo '<p class="description">';
        printf(
            esc_html__('Get your %s API key from their website.', 'ai-core'),
            esc_html($args['label'])
        );
        echo '</p>';
    }
    
    /**
     * Default provider field callback
     *
     * @return void
     */
    public function default_provider_field_callback() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        $value = $settings['default_provider'] ?? 'openai';
        
        $providers = array(
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic Claude',
            'gemini' => 'Google Gemini',
            'grok' => 'xAI Grok'
        );
        
        echo '<select id="default_provider" name="' . esc_attr($this->option_name) . '[default_provider]">';
        foreach ($providers as $provider_key => $provider_label) {
            echo '<option value="' . esc_attr($provider_key) . '" ' . selected($value, $provider_key, false) . '>';
            echo esc_html($provider_label);
            echo '</option>';
        }
        echo '</select>';
        
        echo '<p class="description">' . esc_html__('Default AI provider for add-on plugins.', 'ai-core') . '</p>';
    }
    
    /**
     * Checkbox field callback
     *
     * @param array $args Field arguments
     * @return void
     */
    public function checkbox_field_callback($args) {
        $settings = get_option($this->option_name, $this->get_default_settings());
        $field = $args['field'];
        $value = $settings[$field] ?? false;
        
        echo '<label>';
        echo '<input type="checkbox" ';
        echo 'id="' . esc_attr($field) . '" ';
        echo 'name="' . esc_attr($this->option_name) . '[' . esc_attr($field) . ']" ';
        echo 'value="1" ';
        checked($value, true);
        echo '/> ';
        echo esc_html($args['label']);
        echo '</label>';
    }
    
    /**
     * Get default settings
     *
     * @return array Default settings
     */
    private function get_default_settings() {
        return array(
            'openai_api_key' => '',
            'anthropic_api_key' => '',
            'gemini_api_key' => '',
            'grok_api_key' => '',
            'default_provider' => 'openai',
            'enable_stats' => true,
            'enable_caching' => true,
            'cache_duration' => 3600,
            'persist_on_uninstall' => true,
        );
    }
    
    /**
     * Sanitize settings
     *
     * @param array $input Raw input values
     * @return array Sanitized values
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize API keys
        $api_keys = array('openai_api_key', 'anthropic_api_key', 'gemini_api_key', 'grok_api_key');
        foreach ($api_keys as $key) {
            $sanitized[$key] = isset($input[$key]) ? sanitize_text_field($input[$key]) : '';
        }
        
        // Sanitize default provider
        $sanitized['default_provider'] = isset($input['default_provider']) ? sanitize_text_field($input['default_provider']) : 'openai';
        
        // Sanitize checkboxes
        $sanitized['enable_stats'] = isset($input['enable_stats']) && $input['enable_stats'] == '1';
        $sanitized['enable_caching'] = isset($input['enable_caching']) && $input['enable_caching'] == '1';
        $sanitized['persist_on_uninstall'] = isset($input['persist_on_uninstall']) && $input['persist_on_uninstall'] == '1';

        // Sanitize cache duration
        $sanitized['cache_duration'] = isset($input['cache_duration']) ? absint($input['cache_duration']) : 3600;

        return $sanitized;
    }
    
    /**
     * Get setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get_setting($key, $default = null) {
        $settings = get_option($this->option_name, $this->get_default_settings());
        return $settings[$key] ?? $default;
    }
}

