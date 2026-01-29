<?php
/**
 * Example usage file for FS WP Multilang Integration
 * 
 * This file demonstrates how to use the integration plugin
 *
 * @package FS_WPML_Integration
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example: Get current language
 */
function fs_example_get_current_language() {
    $switcher = FS_WPML_Language_Switcher::get_instance();
    return $switcher->get_current_language();
}

/**
 * Example: Switch language
 */
function fs_example_switch_language($language_code) {
    $switcher = FS_WPML_Language_Switcher::get_instance();
    $switcher->switch_language($language_code);
}

/**
 * Example: Get active languages
 */
function fs_example_get_active_languages() {
    if (function_exists('wpm_get_languages')) {
        return wpm_get_languages();
    }
    return array();
}

/**
 * Example: Filter content by language
 */
function fs_example_filter_content_by_language($content, $language_code = '') {
    if (empty($language_code)) {
        $language_code = fs_example_get_current_language();
    }
    
    // Add your content filtering logic here
    return $content;
}