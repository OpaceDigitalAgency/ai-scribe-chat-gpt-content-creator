<?php
/**
 * AI-Core Prompt Library AJAX Handlers
 * 
 * Additional AJAX methods for the Prompt Library
 * 
 * @package AI_Core
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait for Prompt Library AJAX handlers
 */
trait AI_Core_Prompt_Library_AJAX {
    
    /**
     * AJAX: Save prompt
     *
     * @return void
     */
    public function ajax_save_prompt() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-core')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_core_prompts';
        
        $prompt_id = isset($_POST['prompt_id']) ? intval($_POST['prompt_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : null;
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'text';
        
        if (empty($title) || empty($content)) {
            wp_send_json_error(array('message' => __('Title and content are required', 'ai-core')));
        }
        
        $data = array(
            'title' => $title,
            'content' => $content,
            'group_id' => $group_id,
            'provider' => $provider,
            'type' => $type,
            'updated_at' => current_time('mysql'),
        );
        
        if ($prompt_id > 0) {
            // Update existing prompt
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $prompt_id),
                array('%s', '%s', '%d', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Create new prompt
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $table_name,
                $data,
                array('%s', '%s', '%d', '%s', '%s', '%s', '%s')
            );
            $prompt_id = $wpdb->insert_id;
        }
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to save prompt', 'ai-core')));
        }
        
        wp_send_json_success(array(
            'message' => __('Prompt saved successfully', 'ai-core'),
            'prompt_id' => $prompt_id,
        ));
    }
    
    /**
     * AJAX: Delete prompt
     *
     * @return void
     */
    public function ajax_delete_prompt() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-core')));
        }
        
        $prompt_id = isset($_POST['prompt_id']) ? intval($_POST['prompt_id']) : 0;
        
        if ($prompt_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid prompt ID', 'ai-core')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_core_prompts';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $prompt_id),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to delete prompt', 'ai-core')));
        }
        
        wp_send_json_success(array('message' => __('Prompt deleted successfully', 'ai-core')));
    }
    
    /**
     * AJAX: Get groups
     *
     * @return void
     */
    public function ajax_get_groups() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-core')));
        }
        
        $groups = $this->get_groups();
        
        wp_send_json_success(array('groups' => $groups));
    }
    
    /**
     * AJAX: Save group
     *
     * @return void
     */
    public function ajax_save_group() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-core')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_core_prompt_groups';
        
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        
        if (empty($name)) {
            wp_send_json_error(array('message' => __('Group name is required', 'ai-core')));
        }
        
        $data = array(
            'name' => $name,
            'description' => $description,
            'updated_at' => current_time('mysql'),
        );
        
        if ($group_id > 0) {
            // Update existing group
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $group_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Create new group
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $table_name,
                $data,
                array('%s', '%s', '%s', '%s')
            );
            $group_id = $wpdb->insert_id;
        }
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to save group', 'ai-core')));
        }
        
        wp_send_json_success(array(
            'message' => __('Group saved successfully', 'ai-core'),
            'group_id' => $group_id,
        ));
    }
    
    /**
     * AJAX: Delete group
     *
     * @return void
     */
    public function ajax_delete_group() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-core')));
        }
        
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        
        if ($group_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid group ID', 'ai-core')));
        }
        
        global $wpdb;
        
        // First, unassign all prompts from this group
        $prompts_table = $wpdb->prefix . 'ai_core_prompts';
        $wpdb->update(
            $prompts_table,
            array('group_id' => null),
            array('group_id' => $group_id),
            array('%d'),
            array('%d')
        );
        
        // Then delete the group
        $groups_table = $wpdb->prefix . 'ai_core_prompt_groups';
        $result = $wpdb->delete(
            $groups_table,
            array('id' => $group_id),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to delete group', 'ai-core')));
        }
        
        wp_send_json_success(array('message' => __('Group deleted successfully', 'ai-core')));
    }
    
    /**
     * AJAX: Move prompt to different group
     *
     * @return void
     */
    public function ajax_move_prompt() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-core')));
        }
        
        $prompt_id = isset($_POST['prompt_id']) ? intval($_POST['prompt_id']) : 0;
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : null;
        
        if ($prompt_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid prompt ID', 'ai-core')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_core_prompts';
        
        $result = $wpdb->update(
            $table_name,
            array('group_id' => $group_id),
            array('id' => $prompt_id),
            array('%d'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to move prompt', 'ai-core')));
        }
        
        wp_send_json_success(array('message' => __('Prompt moved successfully', 'ai-core')));
    }
    
    /**
     * AJAX: Run prompt
     *
     * @return void
     */
    public function ajax_run_prompt() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-core')));
        }
        
        $prompt_content = isset($_POST['prompt']) ? wp_kses_post($_POST['prompt']) : '';
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'text';
        
        if (empty($prompt_content)) {
            wp_send_json_error(array('message' => __('Prompt content is required', 'ai-core')));
        }
        
        // Get AI Core instance
        $ai_core = AI_Core::get_instance();
        
        try {
            if ($type === 'image') {
                // Image generation
                $result = $ai_core->generate_image($prompt_content, $provider);
            } else {
                // Text generation
                $result = $ai_core->generate_text($prompt_content, $provider);
            }
            
            wp_send_json_success(array(
                'result' => $result,
                'type' => $type,
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}

