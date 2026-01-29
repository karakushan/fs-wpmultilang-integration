<?php
/**
 * URL Translator for FS WP Multilang Integration
 * 
 * Handles custom URL translation for products, posts, and categories
 * with support for custom SEO slugs in multiple languages
 *
 * @package FS_WPML_Integration
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FS_WPML_URL_Translator {
    
    /**
     * Instance of the class
     */
    private static $instance = null;
    
    /**
     * Get instance
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_filter('wpm_translate_url', array($this, 'translate_url'), 10, 3);
    }
    
    /**
     * Main URL translation function
     * 
     * Translates URLs based on custom SEO slugs for products, posts, and categories
     *
     * @param string $new_url The new URL to return
     * @param string $language Target language code
     * @param string $url Original URL
     * @return string Translated URL
     */
    public function translate_url($new_url, $language, $url) {
        $path = parse_url($url, PHP_URL_PATH);
        $path = trim($path, '/');
        $parts = explode('/', $path);

        if ($language == 'ru') {
            // Check if this is a product URL
            if (count($parts) >= 2 && $parts[0] === 'product') {
                $slug = end($parts);

                // Check if product exists
                global $wpdb;
                $post = $wpdb->get_row($wpdb->prepare(
                    "SELECT ID, post_type FROM {$wpdb->posts} 
                    WHERE post_name = %s 
                    AND post_type = 'product' 
                    AND post_status = 'publish'
                    LIMIT 1",
                    $slug
                ));

                if ($post) {
                    // Get Russian slug
                    $ru_slug = get_post_meta($post->ID, 'fs_seo_slug__ru_ru', true);
                    if ($ru_slug) {
                        return site_url() . '/ru/product/' . $ru_slug . '/';
                    }

                    // If no Russian slug, use original
                    return site_url() . '/ru/product/' . $slug . '/';
                }
            }

            // Handle posts with custom Russian slug
            if (count($parts) >= 1) {
                $slug = end($parts);
                
                // Try to find post by default slug first
                global $wpdb;
                $post = $wpdb->get_row($wpdb->prepare(
                    "SELECT ID, post_type, post_name FROM {$wpdb->posts} 
                    WHERE post_name = %s 
                    AND post_type = 'post' 
                    AND post_status = 'publish'
                    LIMIT 1",
                    $slug
                ));

                if ($post) {
                    // Get custom Russian slug from ACF field
                    $custom_slug_meta = get_post_meta($post->ID, 'slug_ru', true);
                    
                    if ($custom_slug_meta && function_exists('wpm_string_to_ml_array')) {
                        // Extract Russian slug from multilang format
                        $ml_array = wpm_string_to_ml_array($custom_slug_meta);
                        $ru_slug = isset($ml_array['ru']) ? $ml_array['ru'] : '';
                        
                        if ($ru_slug) {
                            return site_url() . '/ru/' . $ru_slug . '/';
                        }
                    }
                    
                    // If no custom slug, use default slug with language prefix
                    return site_url() . '/ru/' . $slug . '/';
                }
            }

            // Check if this is a category URL
            $term = get_term_by('slug', end($parts), 'catalog');

            // Get clean slug value through $wpdb
            global $wpdb;
            $slug_string = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->termmeta} 
                WHERE term_id = %d AND meta_key = '_seo_slug' 
                LIMIT 1",
                $term->term_id
            ));

            $slug_array = $slug_string ? wpm_string_to_ml_array($slug_string) : [];

            if ($term && !empty($slug_array['ru'])) {
                $ru_slug = $slug_array['ru'];
                $full_ru_url = site_url() . '/ru/' . $ru_slug . '/';
                
                return $full_ru_url;
            }
        } elseif ($language == 'uk' || $language == 'ua') {
            // Check if this is a product URL for Ukrainian version
            if (count($parts) >= 3 && $parts[0] === 'ru' && $parts[1] === 'product') {
                $slug = end($parts);

                // Check if product exists
                global $wpdb;
                $post = $wpdb->get_row($wpdb->prepare(
                    "SELECT ID, post_type FROM {$wpdb->posts} 
                    WHERE post_type = 'product' 
                    AND post_status = 'publish'
                    AND (ID IN (
                        SELECT post_id FROM {$wpdb->postmeta} 
                        WHERE meta_key = 'fs_seo_slug__ru_ru' AND meta_value = %s
                    ) OR post_name = %s)
                    LIMIT 1",
                    $slug, $slug
                ));

                if ($post) {
                    // Get Ukrainian slug (base post_name)
                    $uk_slug = $wpdb->get_var($wpdb->prepare(
                        "SELECT post_name FROM {$wpdb->posts} WHERE ID = %d",
                        $post->ID
                    ));

                    if ($uk_slug) {
                        return site_url() . '/product/' . $uk_slug . '/';
                    }
                }
            }

            // Check if this is a category URL for Ukrainian version
            if (count($parts) >= 2 && $parts[0] === 'ru') {
                $ru_slug = end($parts);

                // Find term by Russian slug through meta field
                global $wpdb;
                $term_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT term_id FROM {$wpdb->termmeta} 
                    WHERE meta_key = '_seo_slug__ru_ru' AND meta_value = %s 
                    LIMIT 1",
                    $ru_slug
                ));

                if ($term_id) {
                    $term = get_term($term_id, 'catalog');
                    if ($term && !is_wp_error($term)) {
                        return site_url() . '/' . $term->slug . '/';
                    }
                }
            }

            // Handle posts with custom Russian slug for Ukrainian version
            if (count($parts) >= 2 && $parts[0] === 'ru') {
                $slug = end($parts);
                
                // Try to find post by custom Russian slug
                global $wpdb;
                $post_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = 'slug_ru' 
                    AND meta_value LIKE %s 
                    LIMIT 1",
                    '%[:ru]' . $wpdb->esc_like($slug) . '[:%'
                ));

                if ($post_id) {
                    // Get the actual post slug (post_name) for Ukrainian version
                    $post_name = $wpdb->get_var($wpdb->prepare(
                        "SELECT post_name FROM {$wpdb->posts} 
                        WHERE ID = %d AND post_type = 'post' AND post_status = 'publish'",
                        $post_id
                    ));

                    if ($post_name) {
                        // Return URL with post_name (default language, no prefix)
                        return site_url() . '/' . $post_name . '/';
                    }
                }
            }
        }

        return $new_url;
    }
}