<?php
/**
 * Hreflang Handler for FS WP Multilang Integration
 * 
 * Handles hreflang attribute modifications and URL corrections for multilingual sites
 *
 * @package FS_WPML_Integration
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FS_WPML_Hreflang_Handler {
    
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
        add_filter('wpm_alternate_links', array($this, 'fix_russian_hreflang'), 10, 2);
    }
    
    /**
     * Change hreflang attribute from "ru-ru" to "ru-UA" for Russian version
     * and fix incorrect URLs for translated slugs.
     *
     * @param array $hreflangs Array of hreflang link tags.
     * @param string $url Current page URL.
     * @return array Modified hreflang links.
     */
    public function fix_russian_hreflang($hreflangs, $url) {
        if (is_array($hreflangs)) {
            foreach ($hreflangs as $code => $hreflang) {
                // Fix URL for each language using our custom translation function
                if ($code !== 'x-default' && function_exists('custom_wpm_translate_current_url')) {
                    $correct_url = custom_wpm_translate_current_url($code);
                    
                    // Extract current URL from hreflang tag to replace it
                    if (preg_match('/href="([^"]+)"/', $hreflang, $matches)) {
                        $old_url = $matches[1];
                        $hreflangs[$code] = str_replace($old_url, $correct_url, $hreflangs[$code]);
                    }
                }
                
                // Replace hreflang="ru-ru" with hreflang="ru-UA"
                $hreflangs[$code] = str_replace('hreflang="ru-ru"', 'hreflang="ru-UA"', $hreflangs[$code]);
                $hreflangs[$code] = str_replace("hreflang='ru-ru'", "hreflang='ru-UA'", $hreflangs[$code]);
            }
        }
        
        return $hreflangs;
    }
}