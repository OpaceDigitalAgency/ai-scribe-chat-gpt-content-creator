<?php
/**
 * AI-Core Library - Autoloader
 * 
 * Simple PSR-4 compatible autoloader for AI-Core library
 * Allows AI-Scribe to use AI-Core without Composer
 * 
 * @package AI_Core
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI-Core autoloader function
 * 
 * @param string $class_name Fully qualified class name
 * @return bool True if class was loaded
 */
function ai_core_autoloader($class_name) {
    
    // Check if this is an AICore class
    if (strpos($class_name, 'AICore\\') !== 0) {
        return false;
    }
    
    // Remove the AICore namespace prefix
    $class_name = substr($class_name, 7);
    
    // Convert namespace separators to directory separators
    $class_path = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
    
    // Build the full file path
    $file_path = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $class_path . '.php';
    
    // Check if file exists and include it
    if (file_exists($file_path)) {
        require_once $file_path;
        return true;
    }
    
    return false;
}

// Register the autoloader
spl_autoload_register('ai_core_autoloader');

// Define AI-Core constants
if (!defined('AI_CORE_VERSION')) {
    define('AI_CORE_VERSION', '1.0.0');
}

if (!defined('AI_CORE_PATH')) {
    define('AI_CORE_PATH', __DIR__);
}

// Initialize AI-Core if not already done
if (!class_exists('AICore\\AICore')) {
    // The autoloader will handle loading the class when it's first used
}