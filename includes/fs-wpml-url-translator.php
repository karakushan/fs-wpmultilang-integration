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
        $parts = $this->get_path_parts($path);

        if (empty($parts)) {
            return $new_url;
        }

        if ($parts[0] === 'product' && !empty($parts[1])) {
            $post = $this->get_product_by_slug($parts[1]);

            if ($post) {
                $localized_post_url = $this->get_post_url_by_language($post->ID, $language, $post->post_type);

                if ($localized_post_url) {
                    return $this->append_remaining_path_segments($localized_post_url, array_slice($parts, 2));
                }
            }

            return $new_url;
        }

        $term = $this->get_catalog_term_by_slug($parts[0]);

        if ($term) {
            $localized_term_url = $this->get_term_url_by_language($term->term_id, $language);

            if ($localized_term_url) {
                return $this->append_remaining_path_segments($localized_term_url, array_slice($parts, 1));
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
        $default_language = function_exists('wpm_get_default_language')
            ? strtolower((string) wpm_get_default_language())
            : 'ua';
        $language = strtolower((string) $language);

        if ($post_type !== 'product') {
            return null;
        }

        if ($language === $default_language || $language === '') {
            $post = get_post($post_id);

            if ($post instanceof WP_Post && $post->post_type === 'product') {
                return trailingslashit(implode('/', [site_url(), 'product', $post->post_name]));
            }

            return null;
        }

        // Получаем сырое значение напрямую из базы, минуя фильтры WPM
        global $wpdb;
        $slug_string = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'fs_seo_slug'",
            $post_id
        ));
        
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
        $default_language = function_exists('wpm_get_default_language')
            ? strtolower((string) wpm_get_default_language())
            : 'ua';
        $language = strtolower((string) $language);

        if ($language === $default_language || $language === '') {
            $term = get_term($term_id, 'catalog');

            if ($term instanceof WP_Term) {
                return trailingslashit(implode('/', [site_url(), $term->slug]));
            }

            return null;
        }

        // Получаем сырое значение напрямую из базы, минуя фильтры WPM
        global $wpdb;
        $slug_string = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = '_seo_slug'",
            $term_id
        ));
        
        $slug_array = $slug_string ? wpm_string_to_ml_array($slug_string) : [];

        if (!empty($slug_array[$language])) {
            $current_language_slug = $slug_array[$language];
            $url_components = [site_url(), $language, $current_language_slug];
            $full_url = implode('/', $url_components);

            return $full_url . '/';
        }

        return null;
    }

    /**
     * Get normalized URL path parts without a language prefix.
     *
     * @param string|null $path URL path.
     * @return array
     */
    private function get_path_parts($path)
    {
        $parts = array_values(array_filter(explode('/', trim((string) $path, '/')), 'strlen'));

        if (empty($parts) || !function_exists('wpm_get_languages')) {
            return $parts;
        }

        $available_languages = array_map('strtolower', array_keys(wpm_get_languages()));

        if (in_array(strtolower($parts[0]), $available_languages, true)) {
            array_shift($parts);
        }

        return array_values($parts);
    }

    /**
     * Find product by original or translated slug.
     *
     * @param string $slug Product slug from current URL.
     * @return object|null
     */
    private function get_product_by_slug($slug)
    {
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
            return $post;
        }

        if (!function_exists('wpm_string_to_ml_array')) {
            return null;
        }

        $meta_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.post_id, pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = 'fs_seo_slug'
            AND p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm.meta_value LIKE %s",
            '%' . $wpdb->esc_like($slug) . '%'
        ));

        foreach ($meta_rows as $meta_row) {
            $slug_array = wpm_string_to_ml_array($meta_row->meta_value);

            if (in_array($slug, $slug_array, true)) {
                return (object) [
                    'ID' => (int) $meta_row->post_id,
                    'post_type' => 'product',
                ];
            }
        }

        return null;
    }

    /**
     * Find catalog term by original or translated slug.
     *
     * @param string $slug Term slug from current URL.
     * @return object|null
     */
    private function get_catalog_term_by_slug($slug)
    {
        global $wpdb;

        $term = $wpdb->get_row($wpdb->prepare(
            "SELECT t.term_id
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
            WHERE t.slug = %s
            AND tt.taxonomy = 'catalog'
            LIMIT 1",
            $slug
        ));

        if ($term) {
            return $term;
        }

        if (!function_exists('wpm_string_to_ml_array')) {
            return null;
        }

        $meta_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT term_id, meta_value
            FROM {$wpdb->termmeta}
            WHERE meta_key = '_seo_slug'
            AND meta_value LIKE %s",
            '%' . $wpdb->esc_like($slug) . '%'
        ));

        foreach ($meta_rows as $meta_row) {
            $slug_array = wpm_string_to_ml_array($meta_row->meta_value);

            if (in_array($slug, $slug_array, true)) {
                return (object) [
                    'term_id' => (int) $meta_row->term_id,
                ];
            }
        }

        return null;
    }

    /**
     * Append remaining path segments, for example pagination.
     *
     * @param string $url Base translated URL.
     * @param array $segments Remaining path segments.
     * @return string
     */
    private function append_remaining_path_segments($url, array $segments)
    {
        if (empty($segments)) {
            return $url;
        }

        return trailingslashit(untrailingslashit($url) . '/' . implode('/', $segments));
    }
}
