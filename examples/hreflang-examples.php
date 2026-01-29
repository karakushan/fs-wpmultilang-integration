<?php
/**
 * Hreflang Examples for FS WP Multilang Integration
 * 
 * Examples demonstrating how to work with hreflang functionality
 *
 * @package FS_WPML_Integration
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example: How the hreflang handler works internally
 * 
 * The FS_WPML_Hreflang_Handler class automatically:
 * 1. Fixes Russian hreflang from "ru-ru" to "ru-UA"
 * 2. Corrects URLs for translated slugs using custom_wpm_translate_current_url()
 * 3. Processes all language variants except x-default
 */

/**
 * Example: Manual hreflang modification (if needed)
 */
function fs_example_manual_hreflang_modification($hreflangs, $url) {
    // Access the handler instance
    $handler = FS_WPML_Hreflang_Handler::get_instance();
    
    // The handler automatically processes through the filter
    // but you can also call methods directly if needed
    
    return $hreflangs;
}

/**
 * Example: Adding custom hreflang processing
 */
function fs_example_add_custom_hreflang_processing() {
    // Add additional hreflang processing after the main handler
    add_filter('wpm_alternate_links', 'fs_custom_hreflang_processing', 15, 2);
}

function fs_custom_hreflang_processing($hreflangs, $url) {
    // Add your custom hreflang modifications here
    // This runs after the main hreflang handler
    
    foreach ($hreflangs as $code => $hreflang) {
        // Example: Add additional custom processing
        if ($code === 'en') {
            // Custom logic for English version
        }
    }
    
    return $hreflangs;
}

/**
 * Example: Debug hreflang output
 */
function fs_debug_hreflang_output($hreflangs, $url) {
    // Log hreflang data for debugging
    error_log('Hreflang data: ' . print_r($hreflangs, true));
    error_log('Current URL: ' . $url);
    
    return $hreflangs;
}

// Uncomment to enable debugging
// add_filter('wpm_alternate_links', 'fs_debug_hreflang_output', 5, 2);

/**
 * Key points about the hreflang implementation:
 * 
 * 1. The handler uses the 'wpm_alternate_links' filter with priority 10
 * 2. It processes all language codes except 'x-default'
 * 3. It relies on the custom_wpm_translate_current_url() function for URL correction
 * 4. It automatically fixes Russian hreflang codes from ru-ru to ru-UA
 * 5. The functionality was moved from theme functions.php to this plugin
 * 
 * The hreflang functionality ensures proper SEO implementation for multilingual sites
 * by providing correct language and regional targeting for search engines.
 */