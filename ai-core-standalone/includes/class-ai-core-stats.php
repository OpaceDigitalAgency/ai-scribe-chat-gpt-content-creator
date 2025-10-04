<?php
/**
 * AI-Core Stats Class
 * 
 * Handles usage statistics tracking and display
 * 
 * @package AI_Core
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI-Core Stats Class
 * 
 * Manages usage statistics
 */
class AI_Core_Stats {
    
    /**
     * Class instance
     * 
     * @var AI_Core_Stats
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return AI_Core_Stats
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
     * Get all statistics
     * 
     * @return array Statistics data
     */
    public function get_stats() {
        return get_option('ai_core_stats', array());
    }
    
    /**
     * Get statistics for a specific model
     * 
     * @param string $model Model identifier
     * @return array Model statistics
     */
    public function get_model_stats($model) {
        $stats = $this->get_stats();
        return $stats[$model] ?? array(
            'requests' => 0,
            'tokens' => 0,
            'errors' => 0,
            'last_used' => null
        );
    }
    
    /**
     * Get total statistics across all models
     * 
     * @return array Total statistics
     */
    public function get_total_stats() {
        $stats = $this->get_stats();
        $total = array(
            'requests' => 0,
            'tokens' => 0,
            'errors' => 0,
            'models_used' => count($stats)
        );
        
        foreach ($stats as $model_stats) {
            $total['requests'] += $model_stats['requests'] ?? 0;
            $total['tokens'] += $model_stats['tokens'] ?? 0;
            $total['errors'] += $model_stats['errors'] ?? 0;
        }
        
        return $total;
    }
    
    /**
     * Reset all statistics
     * 
     * @return bool Success status
     */
    public function reset_stats() {
        return update_option('ai_core_stats', array());
    }
    
    /**
     * Format statistics for display
     * 
     * @return string HTML formatted statistics
     */
    public function format_stats_html() {
        $stats = $this->get_stats();
        $total = $this->get_total_stats();
        
        if (empty($stats)) {
            return '<p>' . esc_html__('No usage statistics available yet.', 'ai-core') . '</p>';
        }
        
        $html = '<div class="ai-core-stats-summary">';
        $html .= '<h3>' . esc_html__('Total Usage', 'ai-core') . '</h3>';
        $html .= '<div class="ai-core-stats-grid">';
        $html .= '<div class="stat-box"><span class="stat-label">' . esc_html__('Total Requests', 'ai-core') . '</span><span class="stat-value">' . number_format($total['requests']) . '</span></div>';
        $html .= '<div class="stat-box"><span class="stat-label">' . esc_html__('Total Tokens', 'ai-core') . '</span><span class="stat-value">' . number_format($total['tokens']) . '</span></div>';
        $html .= '<div class="stat-box"><span class="stat-label">' . esc_html__('Errors', 'ai-core') . '</span><span class="stat-value">' . number_format($total['errors']) . '</span></div>';
        $html .= '<div class="stat-box"><span class="stat-label">' . esc_html__('Models Used', 'ai-core') . '</span><span class="stat-value">' . number_format($total['models_used']) . '</span></div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="ai-core-stats-details">';
        $html .= '<h3>' . esc_html__('Usage by Model', 'ai-core') . '</h3>';
        $html .= '<table class="widefat">';
        $html .= '<thead><tr>';
        $html .= '<th>' . esc_html__('Model', 'ai-core') . '</th>';
        $html .= '<th>' . esc_html__('Requests', 'ai-core') . '</th>';
        $html .= '<th>' . esc_html__('Tokens', 'ai-core') . '</th>';
        $html .= '<th>' . esc_html__('Errors', 'ai-core') . '</th>';
        $html .= '<th>' . esc_html__('Last Used', 'ai-core') . '</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';
        
        foreach ($stats as $model => $model_stats) {
            $html .= '<tr>';
            $html .= '<td><strong>' . esc_html($model) . '</strong></td>';
            $html .= '<td>' . number_format($model_stats['requests'] ?? 0) . '</td>';
            $html .= '<td>' . number_format($model_stats['tokens'] ?? 0) . '</td>';
            $html .= '<td>' . number_format($model_stats['errors'] ?? 0) . '</td>';
            $html .= '<td>' . ($model_stats['last_used'] ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($model_stats['last_used']))) : '-') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }
}

