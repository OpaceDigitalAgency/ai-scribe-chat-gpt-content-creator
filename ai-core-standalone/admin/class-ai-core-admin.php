<?php
/**
 * AI-Core Admin Class
 * 
 * Handles admin interface and menu pages
 * 
 * @package AI_Core
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI-Core Admin Class
 * 
 * Manages admin pages and interface
 */
class AI_Core_Admin {
    
    /**
     * Class instance
     * 
     * @var AI_Core_Admin
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return AI_Core_Admin
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
     * Initialize admin
     *
     * @return void
     */
    private function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Add admin menu
     *
     * @return void
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('AI-Core', 'ai-core'),
            __('AI-Core', 'ai-core'),
            'manage_options',
            'ai-core',
            array($this, 'render_dashboard_page'),
            'dashicons-admin-generic',
            30
        );
        
        // Dashboard submenu (same as main)
        add_submenu_page(
            'ai-core',
            __('Dashboard', 'ai-core'),
            __('Dashboard', 'ai-core'),
            'manage_options',
            'ai-core',
            array($this, 'render_dashboard_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'ai-core',
            __('Settings', 'ai-core'),
            __('Settings', 'ai-core'),
            'manage_options',
            'ai-core-settings',
            array($this, 'render_settings_page')
        );
        
        // Statistics submenu
        add_submenu_page(
            'ai-core',
            __('Statistics', 'ai-core'),
            __('Statistics', 'ai-core'),
            'manage_options',
            'ai-core-stats',
            array($this, 'render_stats_page')
        );
        
        // Add-ons submenu
        add_submenu_page(
            'ai-core',
            __('Add-ons', 'ai-core'),
            __('Add-ons', 'ai-core'),
            'manage_options',
            'ai-core-addons',
            array($this, 'render_addons_page')
        );
    }
    
    /**
     * Render dashboard page
     *
     * @return void
     */
    public function render_dashboard_page() {
        $api = AI_Core_API::get_instance();
        $configured = $api->is_configured();
        $providers = $api->get_configured_providers();
        $stats = AI_Core_Stats::get_instance()->get_total_stats();
        
        ?>
        <div class="wrap ai-core-dashboard">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="ai-core-welcome-panel">
                <h2><?php esc_html_e('Welcome to AI-Core', 'ai-core'); ?></h2>
                <p><?php esc_html_e('Universal AI Integration Hub for WordPress', 'ai-core'); ?></p>
                
                <?php if (!$configured): ?>
                    <div class="notice notice-warning inline">
                        <p>
                            <strong><?php esc_html_e('Getting Started:', 'ai-core'); ?></strong>
                            <?php esc_html_e('Please configure at least one API key in the Settings page to start using AI-Core.', 'ai-core'); ?>
                        </p>
                        <p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ai-core-settings')); ?>" class="button button-primary">
                                <?php esc_html_e('Configure API Keys', 'ai-core'); ?>
                            </a>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-success inline">
                        <p>
                            <strong><?php esc_html_e('Status:', 'ai-core'); ?></strong>
                            <?php
                            printf(
                                esc_html(_n('%d provider configured', '%d providers configured', count($providers), 'ai-core')),
                                count($providers)
                            );
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($configured): ?>
                <div class="ai-core-stats-overview">
                    <h2><?php esc_html_e('Quick Stats', 'ai-core'); ?></h2>
                    <div class="ai-core-stats-grid">
                        <div class="stat-box">
                            <span class="stat-label"><?php esc_html_e('Total Requests', 'ai-core'); ?></span>
                            <span class="stat-value"><?php echo number_format($stats['requests']); ?></span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-label"><?php esc_html_e('Total Tokens', 'ai-core'); ?></span>
                            <span class="stat-value"><?php echo number_format($stats['tokens']); ?></span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-label"><?php esc_html_e('Configured Providers', 'ai-core'); ?></span>
                            <span class="stat-value"><?php echo count($providers); ?></span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-label"><?php esc_html_e('Models Used', 'ai-core'); ?></span>
                            <span class="stat-value"><?php echo number_format($stats['models_used']); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="ai-core-providers-status">
                    <h2><?php esc_html_e('Configured Providers', 'ai-core'); ?></h2>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Provider', 'ai-core'); ?></th>
                                <th><?php esc_html_e('Status', 'ai-core'); ?></th>
                                <th><?php esc_html_e('Available Models', 'ai-core'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($providers as $provider): 
                                $models = $api->get_available_models($provider);
                                $provider_names = array(
                                    'openai' => 'OpenAI',
                                    'anthropic' => 'Anthropic Claude',
                                    'gemini' => 'Google Gemini',
                                    'grok' => 'xAI Grok'
                                );
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($provider_names[$provider] ?? $provider); ?></strong></td>
                                    <td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <?php esc_html_e('Configured', 'ai-core'); ?></td>
                                    <td><?php echo count($models); ?> <?php esc_html_e('models', 'ai-core'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="ai-core-quick-links">
                <h2><?php esc_html_e('Quick Links', 'ai-core'); ?></h2>
                <div class="ai-core-links-grid">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ai-core-settings')); ?>" class="ai-core-link-box">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <h3><?php esc_html_e('Settings', 'ai-core'); ?></h3>
                        <p><?php esc_html_e('Configure API keys and preferences', 'ai-core'); ?></p>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ai-core-stats')); ?>" class="ai-core-link-box">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <h3><?php esc_html_e('Statistics', 'ai-core'); ?></h3>
                        <p><?php esc_html_e('View detailed usage statistics', 'ai-core'); ?></p>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ai-core-addons')); ?>" class="ai-core-link-box">
                        <span class="dashicons dashicons-admin-plugins"></span>
                        <h3><?php esc_html_e('Add-ons', 'ai-core'); ?></h3>
                        <p><?php esc_html_e('Discover plugins that extend AI-Core', 'ai-core'); ?></p>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('ai_core_settings_group');
                do_settings_sections('ai-core-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render statistics page
     *
     * @return void
     */
    public function render_stats_page() {
        $stats = AI_Core_Stats::get_instance();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="ai-core-stats-page">
                <?php echo $stats->format_stats_html(); ?>
                
                <p>
                    <button type="button" class="button" id="ai-core-reset-stats">
                        <?php esc_html_e('Reset Statistics', 'ai-core'); ?>
                    </button>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render add-ons page
     *
     * @return void
     */
    public function render_addons_page() {
        $addons = AI_Core_Addons::get_instance();
        $addons->render_addons_page();
    }
}

