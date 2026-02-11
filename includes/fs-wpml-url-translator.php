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

class FS_WPML_URL_Translator
{

    /**
     * Instance of the class
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
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
    public function translate_url($new_url, $language, $url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $path = trim($path, '/');
        $parts = explode('/', $path);

        if ($language != 'ua') {
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
                    $localized_post_url = $this->get_post_url_by_language($post->ID, $language, $post->post_type);
                    return $localized_post_url ?: $new_url;
                }
            } else {
                // Check if this is a product category URL
                $term = get_term_by('slug', end($parts), 'catalog');
                if ($term && !is_wp_error($term)) {
                    $localized_term_url = $this->get_term_url_by_language($term->term_id, $language);
                    return $localized_term_url ?: $new_url;
                }
            }
        } else {
            // устанавливаем урл для товаров языка по умолчанию
            if (count($parts) === 3 && $parts[1] === 'product') {
                $slug = end($parts);
                
                $object = get_queried_object();

                
                if ($object instanceof WP_Post) {
                    $url_components = [
                        site_url(),
                        'product',
                        $object->post_name
                    ];
                    $url = implode('/', $url_components);
                    return $url . '/';
                }
            }

            // устанавливаем урл для терминов языка по умолчанию
            if (count($parts) === 2 && $parts[1] !== 'product') {
                $slug = end($parts);
                
                $object = get_queried_object();

                if ($object instanceof WP_Term) {
                    $url_components = [
                        site_url(),
                        $object->slug
                    ];
                    $url = implode('/', $url_components);
                    return $url . '/';
                }
            }
        }

        return $new_url;
    }

    /**
     * Get post URL by language
     * 
     * Retrieves the translated URL for a post in the specified language.
     * Uses the custom SEO slug meta field to construct the URL with the appropriate language prefix.
     *
     * @param int    $post_id    The post ID to get the URL for
     * @param string $language   The target language code (e.g., 'ru', 'uk')
     * @param string $post_type  The post type (default: 'product')
     * @return string|null       The translated URL with trailing slash, or null if no slug found
     */
    public function get_post_url_by_language($post_id, $language, $post_type = 'product')
    {
        $slug_string = get_post_meta($post_id, 'fs_seo_slug', true);
        $slug_array = $slug_string ? wpm_string_to_ml_array($slug_string) : [];
        $current_language_slug = isset($slug_array[$language]) ? $slug_array[$language] : '';
        if ($current_language_slug) {
            $url_components = [site_url(), $language, 'product', $current_language_slug];
            $full_url = implode('/', $url_components);
            return $full_url  . '/';
        }

        return null;
    }

    /**
     * Get term URL by language
     * 
     * Retrieves the translated URL for a term in the specified language.
     * Uses the custom SEO slug meta field to construct the URL with the appropriate language prefix.
     *
     * @param int    $term_id   The term ID to get the URL for
     * @param string $language  The target language code (e.g., 'ru', 'uk')
     * @return string|null      The translated URL with trailing slash, or null if no slug found
     */
    public function get_term_url_by_language($term_id, $language)
    {

        $slug_string = get_term_meta($term_id, '_seo_slug', true);
        $slug_array = $slug_string ? wpm_string_to_ml_array($slug_string) : [];

        if (!empty($slug_array[$language])) {
            $current_language_slug = $slug_array[$language];
            $url_components = [site_url(), $language, $current_language_slug];
            $full_url = implode('/', $url_components);

            return $full_url . '/';
        }

        return null;
    }
}
