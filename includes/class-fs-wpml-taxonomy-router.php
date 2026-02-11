<?php

/**
 * Taxonomy Router for FS WP Multilang Integration
 * 
 * Handles custom rewrite rules and request parsing for multilingual taxonomy URLs.
 * Specifically handles Russian-language slugs for catalog taxonomy.
 *
 * @package FS_WPML_Integration
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FS_WPML_Taxonomy_Router
{

    /**
     * Instance of the class
     */
    private static $instance = null;

    /**
     * Cache for term slug lookups
     */
    private $term_cache = [];

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
        // Add rewrite rules for translated taxonomy slugs
        add_action('generate_rewrite_rules', array($this, 'add_taxonomy_rewrite_rules'), 20);
        
        // Handle requests for translated taxonomy URLs
        add_action('parse_request', array($this, 'parse_translated_taxonomy_request'), 10, 1);
        
        // Flush rewrite rules when terms are updated
        add_action('edited_term', array($this, 'schedule_rewrite_flush'), 10, 3);
        add_action('created_term', array($this, 'schedule_rewrite_flush'), 10, 3);
        add_action('delete_term', array($this, 'schedule_rewrite_flush'), 10, 3);
    }

    /**
     * Add rewrite rules for translated taxonomy slugs
     * 
     * Creates rewrite rules for URLs like /ru/muzhskie-zonty-vinnica/
     * to properly route to the catalog taxonomy.
     *
     * @param WP_Rewrite $wp_rewrite WordPress rewrite object.
     * @return array Modified rewrite rules.
     */
    public function add_taxonomy_rewrite_rules($wp_rewrite)
    {
        if (!function_exists('wpm_get_languages') || !function_exists('wpm_get_default_language')) {
            return $wp_rewrite->rules;
        }

        $languages = wpm_get_languages();
        $default_lang = wpm_get_default_language();
        $rules = [];

        foreach ($languages as $lang_code => $lang_data) {
            // Skip default language - it's handled by standard WordPress rules
            if ($lang_code === $default_lang) {
                continue;
            }

            $lang_prefix = isset($lang_data['slug']) ? $lang_data['slug'] : $lang_code;
            
            // Get all terms from catalog taxonomy
            $terms = get_terms([
                'taxonomy' => 'catalog',
                'hide_empty' => false,
            ]);

            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                // Get the translated slug for this language
                $translated_slug = $this->get_translated_slug($term->term_id, $lang_code);
                
                if (empty($translated_slug) || $translated_slug === $term->slug) {
                    continue;
                }

                // Add rewrite rule for translated slug
                // Pattern: ru/muzhskie-zonty-vinnica/
                $rules[$lang_prefix . '/' . $translated_slug . '/?$'] = 'index.php?catalog=' . $term->slug . '&lang=' . $lang_code;
                
                // Add pagination rule
                $rules[$lang_prefix . '/' . $translated_slug . '/page/([0-9]{1,})/?$'] = 'index.php?catalog=' . $term->slug . '&paged=$matches[1]&lang=' . $lang_code;
            }
        }

        // Add our rules before existing rules
        $wp_rewrite->rules = $rules + $wp_rewrite->rules;

        return $wp_rewrite->rules;
    }

    /**
     * Parse requests for translated taxonomy URLs
     * 
     * Handles cases where rewrite rules might not have matched,
     * and tries to resolve the term by looking up the translated slug.
     *
     * @param WP $wp Current WordPress environment instance.
     * @return void
     */
    public function parse_translated_taxonomy_request($wp)
    {
        // Only process if WP Multilang is active
        if (!function_exists('wpm_get_languages') || !function_exists('wpm_get_default_language')) {
            return;
        }

        $request_uri = trim($_SERVER['REQUEST_URI'], '/');
        $parts = explode('/', $request_uri);

        // Need at least 2 parts: language prefix and slug
        if (count($parts) < 2) {
            return;
        }

        $languages = wpm_get_languages();
        $default_lang = wpm_get_default_language();
        $lang_prefix = $parts[0];
        $requested_slug = $parts[1];

        // Find language code by prefix
        $lang_code = null;
        foreach ($languages as $code => $data) {
            if (isset($data['slug']) && $data['slug'] === $lang_prefix) {
                $lang_code = $code;
                break;
            }
            if ($code === $lang_prefix) {
                $lang_code = $code;
                break;
            }
        }

        // Skip if default language or language not found
        if (empty($lang_code) || $lang_code === $default_lang) {
            return;
        }

        // Check if this is already a valid catalog request
        if (isset($wp->query_vars['catalog'])) {
            return;
        }

        // Try to find a term with this translated slug
        $term = $this->get_term_by_translated_slug($requested_slug, $lang_code);

        if ($term && !is_wp_error($term)) {
            // Set the query vars for the taxonomy archive
            $wp->query_vars['catalog'] = $term->slug;
            $wp->query_vars['lang'] = $lang_code;
            
            // Handle pagination
            if (isset($parts[2]) && $parts[2] === 'page' && isset($parts[3]) && is_numeric($parts[3])) {
                $wp->query_vars['paged'] = intval($parts[3]);
            }
        }
    }

    /**
     * Get translated slug for a term
     *
     * @param int $term_id Term ID.
     * @param string $lang_code Language code.
     * @return string|null Translated slug or null if not found.
     */
    private function get_translated_slug($term_id, $lang_code)
    {
        $cache_key = $term_id . '_' . $lang_code;
        
        if (isset($this->term_cache[$cache_key])) {
            return $this->term_cache[$cache_key];
        }

        $slug_string = get_term_meta($term_id, '_seo_slug', true);
        
        if (empty($slug_string) || !function_exists('wpm_string_to_ml_array')) {
            $this->term_cache[$cache_key] = null;
            return null;
        }

        $slug_array = wpm_string_to_ml_array($slug_string);
        $translated_slug = isset($slug_array[$lang_code]) ? $slug_array[$lang_code] : null;
        
        $this->term_cache[$cache_key] = $translated_slug;
        
        return $translated_slug;
    }

    /**
     * Get term by translated slug
     *
     * @param string $slug Translated slug to search for.
     * @param string $lang_code Language code.
     * @return WP_Term|null Term object or null if not found.
     */
    private function get_term_by_translated_slug($slug, $lang_code)
    {
        // Get all catalog terms
        $terms = get_terms([
            'taxonomy' => 'catalog',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return null;
        }

        foreach ($terms as $term) {
            $translated_slug = $this->get_translated_slug($term->term_id, $lang_code);
            
            if ($translated_slug === $slug) {
                return $term;
            }
        }

        return null;
    }

    /**
     * Schedule rewrite rules flush
     *
     * @param int $term_id Term ID.
     * @param int $tt_id Term taxonomy ID.
     * @param string $taxonomy Taxonomy slug.
     * @return void
     */
    public function schedule_rewrite_flush($term_id, $tt_id, $taxonomy)
    {
        if ($taxonomy === 'catalog') {
            // Clear our cache
            $this->term_cache = [];
            
            // Schedule rewrite flush
            add_action('shutdown', 'flush_rewrite_rules');
        }
    }
}
