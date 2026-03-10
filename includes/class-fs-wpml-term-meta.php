<?php
/**
 * Term Meta Handler for FS WP Multilang Integration
 * 
 * Handles multilingual term meta fields for SEO and content.
 * Integrates with F-Shop and Yoast SEO.
 *
 * @package FS_WPML_Integration
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FS_WPML_Term_Meta_Handler
{
    /**
     * Instance of the class
     */
    private static $instance = null;

    /**
     * Meta keys that should be translated
     */
    private $translatable_keys = [
        '_seo_title',
        '_seo_description',
        '_seo_content',
        '_content',
        '_seo_canonical',
        '_seo_slug',
    ];

    /**
     * Flag to prevent recursion
     */
    private $is_filtering = false;

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
        // Yoast SEO integration - use higher priority to override F-Shop
        add_filter('wpseo_title', [$this, 'yoast_seo_title'], 15, 1);
        add_filter('wpseo_metadesc', [$this, 'yoast_seo_description'], 15, 1);
        
        // Filter for fs_get_term_meta function result
        add_filter('fs_meta_description', [$this, 'filter_fs_meta_description'], 10, 1);
        add_filter('fs_meta_title', [$this, 'filter_fs_meta_title'], 10, 1);
        
        // Filter post meta for products (Yoast SEO fields)
        add_filter('get_post_metadata', [$this, 'filter_post_meta'], 10, 4);
        
        // Add Yoast SEO fields to WPMultilang config
        add_filter('wpm_post_fields_config', [$this, 'add_yoast_fields_config']);
    }

    /**
     * Add Yoast SEO fields to WPMultilang translation config
     *
     * @param array $fields Existing fields config.
     * @return array Modified fields config.
     */
    public function add_yoast_fields_config($fields)
    {
        $yoast_fields = [
            '_yoast_wpseo_title' => [],
            '_yoast_wpseo_metadesc' => [],
            '_yoast_wpseo_opengraph-title' => [],
            '_yoast_wpseo_opengraph-description' => [],
            '_yoast_wpseo_twitter-title' => [],
            '_yoast_wpseo_twitter-description' => [],
        ];
        
        return array_merge($fields, $yoast_fields);
    }

    /**
     * Check if value is in WPMultilang format
     *
     * @param string $value Value to check.
     * @return bool True if multilang format.
     */
    private function is_multilang_format($value)
    {
        if (!is_string($value)) {
            return false;
        }
        return (strpos($value, '[:ru]') !== false || strpos($value, '[:ua]') !== false);
    }

    /**
     * Translate a multilang value to specific language
     *
     * @param string $value Value in multilang format.
     * @param string $lang  Target language.
     * @return string Translated value.
     */
    private function translate_value($value, $lang = null)
    {
        if (!$this->is_multilang_format($value)) {
            return $value;
        }
        
        if (function_exists('wpm_translate_string')) {
            return wpm_translate_string($value, $lang);
        }
        
        // Fallback: parse manually
        return $this->parse_multilang_value($value, $lang);
    }

    /**
     * Parse multilang value manually
     *
     * @param string $value Value in multilang format.
     * @param string $lang  Target language.
     * @return string Parsed value.
     */
    private function parse_multilang_value($value, $lang = null)
    {
        if (!$lang) {
            $lang = function_exists('wpm_get_language') ? wpm_get_language() : 'ru';
        }
        
        // Pattern: [:lang]text[:] or [:lang]text[:][:lang2]text2[:]
        $pattern = '/\[:' . preg_quote($lang, '/') . '\](.*?)\[:/s';
        
        if (preg_match($pattern, $value, $matches)) {
            return $matches[1];
        }
        
        // Try to get default language (ru)
        if ($lang !== 'ru') {
            $pattern_ru = '/\[:ru\](.*?)\[:/s';
            if (preg_match($pattern_ru, $value, $matches)) {
                return $matches[1];
            }
        }
        
        return $value;
    }

    /**
     * Filter Yoast SEO title
     *
     * @param string $title Title.
     * @return string Filtered title.
     */
    public function yoast_seo_title($title)
    {
        // Prevent recursion
        if ($this->is_filtering) {
            return $title;
        }

        // Handle product pages
        if (function_exists('is_singular') && is_singular('product')) {
            $post_id = get_the_ID();
            $this->is_filtering = true;
            $meta_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
            $this->is_filtering = false;
            
            if (!empty($meta_title) && $this->is_multilang_format($meta_title)) {
                return esc_attr($this->translate_value($meta_title));
            }
        }
        
        // Handle product category pages
        if (!function_exists('fs_is_product_category') || !fs_is_product_category()) {
            return $title;
        }
        
        $term_id = get_queried_object_id();
        
        $this->is_filtering = true;
        $meta_title = get_term_meta($term_id, '_seo_title', true);
        $this->is_filtering = false;
        
        if (!empty($meta_title) && $this->is_multilang_format($meta_title)) {
            return esc_attr($this->translate_value($meta_title));
        }
        
        return $title;
    }

    /**
     * Filter Yoast SEO description
     *
     * @param string $description Description.
     * @return string Filtered description.
     */
    public function yoast_seo_description($description)
    {
        // Prevent recursion
        if ($this->is_filtering) {
            return $description;
        }

        // Handle product pages
        if (function_exists('is_singular') && is_singular('product')) {
            $post_id = get_the_ID();
            $this->is_filtering = true;
            $meta_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
            $this->is_filtering = false;
            
            if (!empty($meta_desc) && $this->is_multilang_format($meta_desc)) {
                return $this->translate_value($meta_desc);
            }
        }
        
        // Handle product category pages
        if (!function_exists('fs_is_product_category') || !fs_is_product_category()) {
            return $description;
        }
        
        $term_id = get_queried_object_id();
        
        $this->is_filtering = true;
        $meta_description = get_term_meta($term_id, '_seo_description', true);
        $this->is_filtering = false;
        
        if (!empty($meta_description) && $this->is_multilang_format($meta_description)) {
            return $this->translate_value($meta_description);
        }
        
        return $description;
    }

    /**
     * Filter FS meta description
     *
     * @param string $description Description.
     * @return string Filtered description.
     */
    public function filter_fs_meta_description($description)
    {
        if ($this->is_multilang_format($description)) {
            return $this->translate_value($description);
        }
        
        return $description;
    }

    /**
     * Filter FS meta title
     *
     * @param string $title Title.
     * @return string Filtered title.
     */
    public function filter_fs_meta_title($title)
    {
        if ($this->is_multilang_format($title)) {
            return $this->translate_value($title);
        }
        
        return $title;
    }

    /**
     * Filter post meta for Yoast SEO fields on products
     *
     * @param mixed  $value     The meta value.
     * @param int    $post_id   Post ID.
     * @param string $meta_key  Meta key.
     * @param bool   $single    Whether to return a single value.
     * @return mixed Filtered meta value.
     */
    public function filter_post_meta($value, $post_id, $meta_key, $single)
    {
        // Only filter Yoast SEO fields for products
        if (!in_array($meta_key, ['_yoast_wpseo_title', '_yoast_wpseo_metadesc'])) {
            return $value;
        }
        
        // Prevent recursion
        if ($this->is_filtering) {
            return $value;
        }
        
        $this->is_filtering = true;
        $raw_value = get_post_meta($post_id, $meta_key, $single);
        $this->is_filtering = false;
        
        if (!empty($raw_value) && $this->is_multilang_format($raw_value)) {
            return $this->translate_value($raw_value);
        }
        
        return $value;
    }
}
