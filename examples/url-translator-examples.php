<?php
/**
 * URL Translator Examples for FS WP Multilang Integration
 * 
 * Examples demonstrating how to work with URL translation functionality
 *
 * @package FS_WPML_Integration
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example: How the URL translator works internally
 * 
 * The FS_WPML_URL_Translator class automatically:
 * 1. Translates product URLs for Russian and Ukrainian versions
 * 2. Handles custom SEO slugs for products, posts, and categories
 * 3. Uses wpm_translate_url filter with priority 10 and 3 parameters
 */

/**
 * Example: Manual URL translation (if needed)
 */
function fs_example_manual_url_translation($new_url, $language, $url) {
    // Access the translator instance
    $translator = FS_WPML_URL_Translator::get_instance();
    
    // The translator automatically processes through the filter
    // but you can also call methods directly if needed
    
    return $new_url;
}

/**
 * Example: Adding custom URL processing after the main translator
 */
function fs_example_add_custom_url_processing() {
    // Add additional URL processing after the main translator
    add_filter('wpm_translate_url', 'fs_custom_url_processing', 15, 3);
}

function fs_custom_url_processing($new_url, $language, $url) {
    // Add your custom URL modifications here
    // This runs after the main URL translator
    
    // Example: Add custom handling for a specific post type
    if (strpos($url, '/custom-type/') !== false) {
        // Custom logic for custom post type URLs
    }
    
    return $new_url;
}

/**
 * Example: Debug URL translation
 */
function fs_debug_url_translation($new_url, $language, $url) {
    // Log URL translation data for debugging
    error_log('URL Translation Debug:');
    error_log('Original URL: ' . $url);
    error_log('Language: ' . $language);
    error_log('New URL: ' . $new_url);
    
    return $new_url;
}

// Uncomment to enable debugging
// add_filter('wpm_translate_url', 'fs_debug_url_translation', 5, 3);

/**
 * Key points about the URL translation implementation:
 * 
 * 1. The translator uses the 'wpm_translate_url' filter with priority 10 and 3 parameters
 * 2. It processes URLs for:
 *    - Products: /product/slug -> /ru/product/ru-slug (and reverse)
 *    - Posts: /post-slug -> /ru/ru-slug (and reverse)
 *    - Categories: /category-slug -> /ru/ru-category-slug (and reverse)
 * 3. It relies on custom meta fields:
 *    - Products: fs_seo_slug__ru_ru
 *    - Posts: slug_ru (multilang format)
 *    - Categories: _seo_slug (multilang format)
 * 4. The functionality was moved from theme functions.php to this plugin
 * 
 * The URL translator ensures proper URL routing for multilingual content
 * by translating slugs according to custom SEO requirements.
 */

/**
 * Supported language translations:
 * 
 * Russian (ru):
 * - Products: Looks up fs_seo_slug__ru_ru meta field
 * - Posts: Extracts ru slug from slug_ru multilang array
 * - Categories: Looks up _seo_slug multilang array
 * 
 * Ukrainian (uk/ua):
 * - Products: Uses base post_name (original slug)
 * - Posts: Uses original post_name
 * - Categories: Looks up _seo_slug__ru_ru meta field to find term
 */