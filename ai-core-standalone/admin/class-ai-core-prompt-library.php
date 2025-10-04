<?php
/**
 * AI-Core Prompt Library Class
 * 
 * Manages prompt library with groups, search, filter, import/export
 * 
 * @package AI_Core
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI-Core Prompt Library Class
 * 
 * Manages prompt catalogue with modern UX
 */
class AI_Core_Prompt_Library {
    
    /**
     * Class instance
     * 
     * @var AI_Core_Prompt_Library
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return AI_Core_Prompt_Library
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
     * Initialize
     *
     * @return void
     */
    private function init() {
        // AJAX handlers
        add_action('wp_ajax_ai_core_get_prompts', array($this, 'ajax_get_prompts'));
        add_action('wp_ajax_ai_core_save_prompt', array($this, 'ajax_save_prompt'));
        add_action('wp_ajax_ai_core_delete_prompt', array($this, 'ajax_delete_prompt'));
        add_action('wp_ajax_ai_core_get_groups', array($this, 'ajax_get_groups'));
        add_action('wp_ajax_ai_core_save_group', array($this, 'ajax_save_group'));
        add_action('wp_ajax_ai_core_delete_group', array($this, 'ajax_delete_group'));
        add_action('wp_ajax_ai_core_move_prompt', array($this, 'ajax_move_prompt'));
        add_action('wp_ajax_ai_core_run_prompt', array($this, 'ajax_run_prompt'));
        add_action('wp_ajax_ai_core_export_prompts', array($this, 'ajax_export_prompts'));
        add_action('wp_ajax_ai_core_import_prompts', array($this, 'ajax_import_prompts'));
    }
    
    /**
     * Render prompt library page
     *
     * @return void
     */
    public function render_page() {
        $groups = $this->get_groups();
        $prompts = $this->get_prompts();
        
        ?>
        <div class="wrap ai-core-prompt-library">
            <h1><?php esc_html_e('Prompt Library', 'ai-core'); ?></h1>
            
            <div class="ai-core-library-header">
                <div class="ai-core-library-actions">
                    <button type="button" class="button button-primary" id="ai-core-new-prompt">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('New Prompt', 'ai-core'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="ai-core-new-group">
                        <span class="dashicons dashicons-category"></span>
                        <?php esc_html_e('New Group', 'ai-core'); ?>
                    </button>
                    <button type="button" class="button" id="ai-core-import-prompts">
                        <span class="dashicons dashicons-upload"></span>
                        <?php esc_html_e('Import', 'ai-core'); ?>
                    </button>
                    <button type="button" class="button" id="ai-core-export-prompts">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export', 'ai-core'); ?>
                    </button>
                </div>
                
                <div class="ai-core-library-search">
                    <input type="search" 
                           id="ai-core-prompt-search" 
                           class="regular-text" 
                           placeholder="<?php esc_attr_e('Search prompts...', 'ai-core'); ?>" />
                    <select id="ai-core-filter-group" class="regular-text">
                        <option value=""><?php esc_html_e('All Groups', 'ai-core'); ?></option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo esc_attr($group['id']); ?>">
                                <?php echo esc_html($group['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="ai-core-library-content">
                <!-- Sidebar with groups -->
                <div class="ai-core-library-sidebar">
                    <h3><?php esc_html_e('Groups', 'ai-core'); ?></h3>
                    <ul id="ai-core-groups-list" class="ai-core-groups-list">
                        <li class="ai-core-group-item active" data-group-id="">
                            <span class="group-name"><?php esc_html_e('All Prompts', 'ai-core'); ?></span>
                            <span class="group-count"><?php echo count($prompts); ?></span>
                        </li>
                        <?php foreach ($groups as $group): ?>
                            <li class="ai-core-group-item" data-group-id="<?php echo esc_attr($group['id']); ?>">
                                <span class="group-name"><?php echo esc_html($group['name']); ?></span>
                                <span class="group-count"><?php echo esc_html($group['count'] ?? 0); ?></span>
                                <div class="group-actions">
                                    <button type="button" class="button-link edit-group" title="<?php esc_attr_e('Edit', 'ai-core'); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <button type="button" class="button-link delete-group" title="<?php esc_attr_e('Delete', 'ai-core'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- Main content area with prompts -->
                <div class="ai-core-library-main">
                    <div id="ai-core-prompts-grid" class="ai-core-prompts-grid">
                        <?php if (empty($prompts)): ?>
                            <div class="ai-core-empty-state">
                                <span class="dashicons dashicons-admin-post"></span>
                                <h3><?php esc_html_e('No prompts yet', 'ai-core'); ?></h3>
                                <p><?php esc_html_e('Create your first prompt to get started.', 'ai-core'); ?></p>
                                <button type="button" class="button button-primary" id="ai-core-new-prompt-empty">
                                    <?php esc_html_e('Create Prompt', 'ai-core'); ?>
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($prompts as $prompt): ?>
                                <?php $this->render_prompt_card($prompt); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Prompt Editor Modal -->
        <div id="ai-core-prompt-modal" class="ai-core-modal" style="display: none;">
            <div class="ai-core-modal-content">
                <div class="ai-core-modal-header">
                    <h2 id="ai-core-modal-title"><?php esc_html_e('Edit Prompt', 'ai-core'); ?></h2>
                    <button type="button" class="ai-core-modal-close">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
                <div class="ai-core-modal-body">
                    <input type="hidden" id="prompt-id" value="" />
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="prompt-title"><?php esc_html_e('Title', 'ai-core'); ?></label></th>
                            <td><input type="text" id="prompt-title" class="large-text" /></td>
                        </tr>
                        <tr>
                            <th><label for="prompt-group"><?php esc_html_e('Group', 'ai-core'); ?></label></th>
                            <td>
                                <select id="prompt-group" class="regular-text">
                                    <option value=""><?php esc_html_e('Ungrouped', 'ai-core'); ?></option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo esc_attr($group['id']); ?>">
                                            <?php echo esc_html($group['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="prompt-content"><?php esc_html_e('Prompt', 'ai-core'); ?></label></th>
                            <td><textarea id="prompt-content" rows="8" class="large-text"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="prompt-provider"><?php esc_html_e('Provider', 'ai-core'); ?></label></th>
                            <td>
                                <select id="prompt-provider" class="regular-text">
                                    <option value=""><?php esc_html_e('Default', 'ai-core'); ?></option>
                                    <option value="openai">OpenAI</option>
                                    <option value="anthropic">Anthropic Claude</option>
                                    <option value="gemini">Google Gemini</option>
                                    <option value="grok">xAI Grok</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="prompt-type"><?php esc_html_e('Type', 'ai-core'); ?></label></th>
                            <td>
                                <select id="prompt-type" class="regular-text">
                                    <option value="text"><?php esc_html_e('Text Generation', 'ai-core'); ?></option>
                                    <option value="image"><?php esc_html_e('Image Generation', 'ai-core'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="ai-core-prompt-test">
                        <h3><?php esc_html_e('Test Prompt', 'ai-core'); ?></h3>
                        <button type="button" class="button" id="ai-core-test-prompt-modal">
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php esc_html_e('Run Prompt', 'ai-core'); ?>
                        </button>
                        <div id="ai-core-prompt-result" class="ai-core-prompt-result" style="display: none;"></div>
                    </div>
                </div>
                <div class="ai-core-modal-footer">
                    <button type="button" class="button button-primary" id="ai-core-save-prompt">
                        <?php esc_html_e('Save Prompt', 'ai-core'); ?>
                    </button>
                    <button type="button" class="button" id="ai-core-cancel-prompt">
                        <?php esc_html_e('Cancel', 'ai-core'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Group Editor Modal -->
        <div id="ai-core-group-modal" class="ai-core-modal" style="display: none;">
            <div class="ai-core-modal-content ai-core-modal-small">
                <div class="ai-core-modal-header">
                    <h2 id="ai-core-group-modal-title"><?php esc_html_e('Edit Group', 'ai-core'); ?></h2>
                    <button type="button" class="ai-core-modal-close">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
                <div class="ai-core-modal-body">
                    <input type="hidden" id="group-id" value="" />
                    <table class="form-table">
                        <tr>
                            <th><label for="group-name"><?php esc_html_e('Group Name', 'ai-core'); ?></label></th>
                            <td><input type="text" id="group-name" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th><label for="group-description"><?php esc_html_e('Description', 'ai-core'); ?></label></th>
                            <td><textarea id="group-description" rows="3" class="large-text"></textarea></td>
                        </tr>
                    </table>
                </div>
                <div class="ai-core-modal-footer">
                    <button type="button" class="button button-primary" id="ai-core-save-group">
                        <?php esc_html_e('Save Group', 'ai-core'); ?>
                    </button>
                    <button type="button" class="button" id="ai-core-cancel-group">
                        <?php esc_html_e('Cancel', 'ai-core'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Import Modal -->
        <div id="ai-core-import-modal" class="ai-core-modal" style="display: none;">
            <div class="ai-core-modal-content ai-core-modal-small">
                <div class="ai-core-modal-header">
                    <h2><?php esc_html_e('Import Prompts', 'ai-core'); ?></h2>
                    <button type="button" class="ai-core-modal-close">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
                <div class="ai-core-modal-body">
                    <p><?php esc_html_e('Upload a JSON file containing prompts and groups.', 'ai-core'); ?></p>
                    <input type="file" id="ai-core-import-file" accept=".json" />
                </div>
                <div class="ai-core-modal-footer">
                    <button type="button" class="button button-primary" id="ai-core-do-import">
                        <?php esc_html_e('Import', 'ai-core'); ?>
                    </button>
                    <button type="button" class="button" id="ai-core-cancel-import">
                        <?php esc_html_e('Cancel', 'ai-core'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render prompt card
     *
     * @param array $prompt Prompt data
     * @return void
     */
    private function render_prompt_card($prompt) {
        $prompt_id = esc_attr($prompt['id']);
        $title = esc_html($prompt['title']);
        $content = esc_html(wp_trim_words($prompt['content'], 20));
        $group_id = esc_attr($prompt['group_id'] ?? '');
        $type = esc_html($prompt['type'] ?? 'text');
        $provider = esc_html($prompt['provider'] ?? 'default');

        ?>
        <div class="ai-core-prompt-card" data-prompt-id="<?php echo $prompt_id; ?>" data-group-id="<?php echo $group_id; ?>">
            <div class="prompt-card-header">
                <h4><?php echo $title; ?></h4>
                <div class="prompt-card-actions">
                    <button type="button" class="button-link edit-prompt" title="<?php esc_attr_e('Edit', 'ai-core'); ?>">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="button-link delete-prompt" title="<?php esc_attr_e('Delete', 'ai-core'); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>
            <div class="prompt-card-body">
                <p><?php echo $content; ?></p>
            </div>
            <div class="prompt-card-footer">
                <span class="prompt-type">
                    <span class="dashicons dashicons-<?php echo $type === 'image' ? 'format-image' : 'text'; ?>"></span>
                    <?php echo ucfirst($type); ?>
                </span>
                <span class="prompt-provider"><?php echo ucfirst($provider); ?></span>
                <button type="button" class="button button-small run-prompt">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php esc_html_e('Run', 'ai-core'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Get all groups
     *
     * @return array
     */
    public function get_groups() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_core_prompt_groups';

        $groups = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY name ASC",
            ARRAY_A
        );

        // Add prompt count to each group
        foreach ($groups as &$group) {
            $group['count'] = $this->get_group_prompt_count($group['id']);
        }

        return $groups ?: array();
    }

    /**
     * Get prompt count for a group
     *
     * @param int $group_id Group ID
     * @return int
     */
    private function get_group_prompt_count($group_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_core_prompts';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE group_id = %d",
            $group_id
        ));
    }

    /**
     * Get all prompts
     *
     * @param array $args Query arguments
     * @return array
     */
    public function get_prompts($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_core_prompts';

        $defaults = array(
            'group_id' => null,
            'search' => '',
            'type' => '',
            'provider' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $prepare_args = array();

        if (!is_null($args['group_id'])) {
            $where[] = 'group_id = %d';
            $prepare_args[] = $args['group_id'];
        }

        if (!empty($args['search'])) {
            $where[] = '(title LIKE %s OR content LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $prepare_args[] = $search_term;
            $prepare_args[] = $search_term;
        }

        if (!empty($args['type'])) {
            $where[] = 'type = %s';
            $prepare_args[] = $args['type'];
        }

        if (!empty($args['provider'])) {
            $where[] = 'provider = %s';
            $prepare_args[] = $args['provider'];
        }

        $where_clause = implode(' AND ', $where);
        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC";

        if (!empty($prepare_args)) {
            $query = $wpdb->prepare($query, $prepare_args);
        }

        $prompts = $wpdb->get_results($query, ARRAY_A);

        return $prompts ?: array();
    }

    /**
     * AJAX: Get prompts
     *
     * @return void
     */
    public function ajax_get_prompts() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-core')));
        }

        $args = array(
            'group_id' => isset($_POST['group_id']) ? intval($_POST['group_id']) : null,
            'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
            'type' => isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '',
            'provider' => isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '',
        );

        $prompts = $this->get_prompts($args);

        wp_send_json_success(array('prompts' => $prompts));
    }
}
