<?php
/**
 * Class for registering F-Shop post types and taxonomies with WP Multilang
 *
 * @package FS_WPML_Integration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FS_WPML_FShop_Registration
 *
 * Handles registration of F-Shop custom post types and taxonomies
 * for translation in WP Multilang.
 */
class FS_WPML_FShop_Registration {

    /**
     * Instance of the class
     *
     * @var FS_WPML_FShop_Registration
     */
    private static $instance = null;

    /**
     * F-Shop post types that need translation
     *
     * @var array
     */
    private static $post_types = array();

    /**
     * F-Shop taxonomies that need translation
     *
     * @var array
     */
    private static $taxonomies = array();

    /**
     * F-Shop post meta fields that need translation
     *
     * @var array
     */
    private static $post_fields = array();

    /**
     * F-Shop options that need translation
     *
     * @var array
     */
    private static $options = array();

    /**
     * Whether entities have been initialized
     *
     * @var bool
     */
    private static $initialized = false;

    /**
     * Initialize entities and register hooks
     * Called immediately when class is loaded
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        self::init_entities();
        self::register_hooks();
        self::ensure_post_types_enabled();
    }

    /**
     * Ensure F-Shop post types are enabled in WP Multilang settings
     */
    private static function ensure_post_types_enabled() {
        // Get current enabled post types from WP Multilang settings
        $enabled_post_types = get_option('wpm_custom_post_types', array());
        
        $needs_update = false;
        
        // Add our post types if not already enabled
        foreach (self::$post_types as $post_type => $fields) {
            if (!isset($enabled_post_types[$post_type])) {
                $enabled_post_types[$post_type] = $post_type;
                $needs_update = true;
            }
        }
        
        // Update option if we added new post types
        if ($needs_update) {
            update_option('wpm_custom_post_types', $enabled_post_types);
            // Clear cache to ensure changes take effect
            wp_cache_delete('config', 'wpm');
        }
    }

    /**
     * Initialize F-Shop entities that need translation support
     */
    private static function init_entities() {
        // Define post types that need translation
        self::$post_types = array(
            'product' => array(
                'post_title'   => array(),
                'post_excerpt' => array(),
                'post_content' => array(),
            ),
            'reviews' => array(
                'post_title'   => array(),
                'post_content' => array(),
            ),
            'orders' => array(
                'post_title'   => array(),
                'post_content' => array(),
            ),
            'fs-mail-template' => array(
                'post_title'   => array(),
                'post_content' => array(),
            ),
        );

        // Define taxonomies that need translation
        self::$taxonomies = array(
            'catalog' => array(
                'name'        => array(),
                'description' => array(),
            ),
            'product-attributes' => array(
                'name'        => array(),
                'description' => array(),
            ),
            'fs-brands' => array(
                'name'        => array(),
                'description' => array(),
            ),
            'fs-payment-methods' => array(
                'name'        => array(),
                'description' => array(),
            ),
            'fs-delivery-methods' => array(
                'name'        => array(),
                'description' => array(),
            ),
            'fs-order-status' => array(
                'name'        => array(),
                'description' => array(),
            ),
            'fs-taxes' => array(
                'name'        => array(),
                'description' => array(),
            ),
            'fs-discounts' => array(
                'name'        => array(),
                'description' => array(),
            ),
            'fs-currencies' => array(
                'name'        => array(),
                'description' => array(),
            ),
        );

        // Define post meta fields that need translation
        self::$post_fields = array(
            '_fs_seo_title'       => array(),
            '_fs_seo_description' => array(),
            '_fs_seo_keywords'    => array(),
        );

        // Define options that need translation
        self::$options = array(
            'fs_option' => array(
                'name_shop'     => array(),
                'description'   => array(),
                'address'       => array(),
                'phone'         => array(),
                'email'         => array(),
                'work_time'     => array(),
                'delivery_text' => array(),
                'payment_text'  => array(),
            ),
        );
    }

    /**
     * Register hooks for WP Multilang filters
     */
    private static function register_hooks() {
        // Use priority 20 to run AFTER WPM_Custom_Post_Types filter (default priority 10)
        // This ensures our post types are added even if they're not in admin settings
        add_filter('wpm_posts_config', array(__CLASS__, 'filter_post_types'), 20);
        add_filter('wpm_taxonomies_config', array(__CLASS__, 'filter_taxonomies'), 20);
        add_filter('wpm_post_fields_config', array(__CLASS__, 'filter_post_fields'), 20);
        add_filter('wpm_term_fields_config', array(__CLASS__, 'filter_term_fields'), 20);
        add_filter('wpm_options_config', array(__CLASS__, 'filter_options'), 20);
    }

    /**
     * Filter: Register F-Shop post types for WP Multilang translation
     *
     * @param array $config Existing post types config
     * @return array Modified post types config
     */
    public static function filter_post_types($config) {
        if (!self::is_fshop_active()) {
            return $config;
        }

        // Always add F-Shop post types to config
        // This ensures they're available for translation regardless of admin settings
        foreach (self::$post_types as $post_type => $fields) {
            // Merge with existing config if present, otherwise add
            if (isset($config[$post_type])) {
                $config[$post_type] = array_merge($config[$post_type], $fields);
            } else {
                $config[$post_type] = $fields;
            }
        }

        return $config;
    }

    /**
     * Filter: Register F-Shop taxonomies for WP Multilang translation
     *
     * @param array $config Existing taxonomies config
     * @return array Modified taxonomies config
     */
    public static function filter_taxonomies($config) {
        if (!self::is_fshop_active()) {
            return $config;
        }

        // Always add F-Shop taxonomies to config
        foreach (self::$taxonomies as $taxonomy => $fields) {
            if (isset($config[$taxonomy])) {
                $config[$taxonomy] = array_merge($config[$taxonomy], $fields);
            } else {
                $config[$taxonomy] = $fields;
            }
        }

        return $config;
    }

    /**
     * Filter: Register F-Shop post meta fields for translation
     *
     * @param array $config Existing post meta config
     * @return array Modified post meta config
     */
    public static function filter_post_fields($config) {
        if (!self::is_fshop_active()) {
            return $config;
        }

        foreach (self::$post_fields as $field => $field_config) {
            if (!isset($config[$field])) {
                $config[$field] = $field_config;
            }
        }

        return $config;
    }

    /**
     * Filter: Register F-Shop term meta fields for translation
     *
     * @param array $config Existing term meta config
     * @return array Modified term meta config
     */
    public static function filter_term_fields($config) {
        if (!self::is_fshop_active()) {
            return $config;
        }

        // SEO fields that need translation for taxonomy terms
        $seo_fields = array(
            '_seo_title'       => array(),
            '_seo_description' => array(),
            '_seo_slug'        => array(),
            'fs_seo_title'     => array(),
            'fs_seo_description' => array(),
            'fs_seo_slug'      => array(),
            '_content'         => array(),
        );

        foreach ($seo_fields as $field => $field_config) {
            if (!isset($config[$field])) {
                $config[$field] = $field_config;
            }
        }

        return $config;
    }

    /**
     * Filter: Register F-Shop options for translation
     *
     * @param array $config Existing options config
     * @return array Modified options config
     */
    public static function filter_options($config) {
        if (!self::is_fshop_active()) {
            return $config;
        }

        foreach (self::$options as $option => $fields) {
            if (!isset($config[$option])) {
                $config[$option] = $fields;
            }
        }

        return $config;
    }

    /**
     * Check if F-Shop plugin is active
     *
     * @return bool
     */
    private static function is_fshop_active() {
        // Check if FS_Config class exists (main F-Shop class)
        if (class_exists('FS\FS_Config')) {
            return true;
        }

        // Check if F-Shop functions exist
        if (function_exists('fs_get_product')) {
            return true;
        }

        // Check if F-Shop plugin file exists and is active
        $active_plugins = get_option('active_plugins', array());
        if (in_array('f-shop/f-shop.php', $active_plugins)) {
            return true;
        }

        return false;
    }

    /**
     * Get instance of the class (for backwards compatibility)
     *
     * @return FS_WPML_FShop_Registration
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get all registered F-Shop post types
     *
     * @return array
     */
    public static function get_post_types() {
        return self::$post_types;
    }

    /**
     * Get all registered F-Shop taxonomies
     *
     * @return array
     */
    public static function get_taxonomies() {
        return self::$taxonomies;
    }

    /**
     * Add a custom post type to the registration
     *
     * @param string $post_type Post type slug
     * @param array  $fields    Fields configuration
     */
    public static function add_post_type($post_type, $fields = array()) {
        if (!isset(self::$post_types[$post_type])) {
            self::$post_types[$post_type] = $fields;
        }
    }

    /**
     * Add a custom taxonomy to the registration
     *
     * @param string $taxonomy Taxonomy slug
     * @param array  $fields   Fields configuration
     */
    public static function add_taxonomy($taxonomy, $fields = array()) {
        if (!isset(self::$taxonomies[$taxonomy])) {
            self::$taxonomies[$taxonomy] = $fields;
        }
    }
}

// Initialize immediately when file is loaded
FS_WPML_FShop_Registration::init();
