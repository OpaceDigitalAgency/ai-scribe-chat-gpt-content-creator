/**
 * AI-Core Admin JavaScript
 * 
 * @package AI_Core
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * AI-Core Admin Object
     */
    const AICoreAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Test API key buttons
            $(document).on('click', '.ai-core-test-key', this.testApiKey.bind(this));
            
            // Reset stats button
            $(document).on('click', '#ai-core-reset-stats', this.resetStats.bind(this));
        },
        
        /**
         * Test API key
         */
        testApiKey: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const provider = $button.data('provider');
            const $input = $('#' + provider + '_api_key');
            const $status = $('#' + provider + '-status');
            const apiKey = $input.val();
            
            if (!apiKey) {
                this.showStatus($status, 'error', aiCoreAdmin.strings.error + ': API key is empty');
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).text(aiCoreAdmin.strings.testing);
            $status.html('<span class="ai-core-spinner"></span>');
            
            // Send AJAX request
            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_test_api_key',
                    nonce: aiCoreAdmin.nonce,
                    provider: provider,
                    api_key: apiKey
                },
                success: (response) => {
                    if (response.success) {
                        this.showStatus($status, 'success', aiCoreAdmin.strings.success + ': ' + response.data.message);
                    } else {
                        this.showStatus($status, 'error', aiCoreAdmin.strings.error + ': ' + response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    this.showStatus($status, 'error', aiCoreAdmin.strings.error + ': ' + error);
                },
                complete: () => {
                    $button.prop('disabled', false).text('Test Key');
                }
            });
        },
        
        /**
         * Reset statistics
         */
        resetStats: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to reset all statistics? This action cannot be undone.')) {
                return;
            }
            
            const $button = $(e.currentTarget);
            
            // Show loading state
            $button.prop('disabled', true).text('Resetting...');
            
            // Send AJAX request
            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_reset_stats',
                    nonce: aiCoreAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    alert('Error: ' + error);
                },
                complete: () => {
                    $button.prop('disabled', false).text('Reset Statistics');
                }
            });
        },
        
        /**
         * Show status message
         */
        showStatus: function($element, type, message) {
            const icon = type === 'success' ? 'yes-alt' : 'dismiss';
            const className = type === 'success' ? 'success' : 'error';
            
            $element.html(
                '<span class="' + className + '">' +
                '<span class="dashicons dashicons-' + icon + '"></span> ' +
                message +
                '</span>'
            );
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                $element.fadeOut(() => {
                    $element.html('').show();
                });
            }, 5000);
        }
    };
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        AICoreAdmin.init();
    });
    
})(jQuery);

