<?php
/**
 * Plugin Name: FS WP Multilang Integration
 * Plugin URI: https://github.com/karakushan/fs-wpmultilang-integration
 * Description: Integration layer between the website and WP Multilang functionality for enhanced multilingual capabilities.
 * Version: 1.0.0
 * Author: Vitaliy Karakushan
 * Author URI: https://github.com/karakushan
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: fs-wpmultilang-integration
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FS_WPMULTILANG_INTEGRATION_VERSION', '1.0.0');
define('FS_WPMULTILANG_INTEGRATION_PATH', plugin_dir_path(__FILE__));
define('FS_WPMULTILANG_INTEGRATION_URL', plugin_dir_url(__FILE__));

// Load required class files directly
require_once FS_WPMULTILANG_INTEGRATION_PATH . 'includes/fs-wpml-hreflang-handler.php';
require_once FS_WPMULTILANG_INTEGRATION_PATH . 'includes/fs-wpml-language-switcher.php';
require_once FS_WPMULTILANG_INTEGRATION_PATH . 'includes/fs-wpml-url-translator.php';
require_once FS_WPMULTILANG_INTEGRATION_PATH . 'includes/class-fs-wpml-taxonomy-router.php';

/**
 * Main plugin class
 */
class FS_WPML_Integration {

    /**
     * Instance of the class
     *
     * @var FS_WPML_Integration
     */
    private static $instance = null;

    /**
     * Get instance of the class
     *
     * @return FS_WPML_Integration
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
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Load plugin textdomain
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Check if WP Multilang is active
        add_action('init', array($this, 'check_wp_multilang_dependency'));

        // Initialize integration functionality
        add_action('init', array($this, 'init_integration'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Check WP Multilang dependency using reliable methods
        $wpml_active = false;
        
        // Check if core WP Multilang functions exist
        if (function_exists('wpm_get_languages') && function_exists('wpm_get_user_language')) {
            $wpml_active = true;
        }
        // Check if the main wpm() function exists
        elseif (function_exists('wpm')) {
            $wpml_active = true;
        }
        
        if (!$wpml_active) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('This plugin requires WP Multilang to be installed and active.', 'fs-wpmultilang-integration'));
        }

        // Add activation logic here
        $this->create_default_options();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Add deactivation logic here
        flush_rewrite_rules();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'fs-wpmultilang-integration',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Check WP Multilang dependency
     */
    public function check_wp_multilang_dependency() {
        // Check multiple indicators that WP Multilang is active
        $is_active = false;
        
        // Method 1: Check if core WP Multilang functions exist
        if (function_exists('wpm_get_languages') && function_exists('wpm_get_user_language')) {
            $is_active = true;
        }
        // Method 2: Check if the main class exists (with proper namespace)
        elseif (class_exists('\\WPM\\Includes\\WP_Multilang')) {
            $is_active = true;
        }
        // Method 3: Check if the main wpm() function exists
        elseif (function_exists('wpm')) {
            $is_active = true;
        }
        
        if (!$is_active) {
            add_action('admin_notices', array($this, 'missing_wp_multilang_notice'));
            return false;
        }
        return true;
    }

    /**
     * Display notice if WP Multilang is not active
     */
    public function missing_wp_multilang_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('FS WP Multilang Integration requires WP Multilang plugin to be active.', 'fs-wpmultilang-integration'); ?></p>
        </div>
        <?php
    }

    /**
     * Initialize integration functionality
     */
    public function init_integration() {
        if (!$this->check_wp_multilang_dependency()) {
            return;
        }

        // Add your integration logic here
        $this->setup_language_switching();
        $this->setup_content_filtering();
        $this->setup_admin_features();
        $this->setup_hreflang_handler();
        $this->setup_url_translator();
        $this->setup_taxonomy_router();
        
        // Handle flush_rewrite_rules via URL parameter
        if (isset($_GET['flush_rewrite_rules']) && $_GET['flush_rewrite_rules'] === 'fs_wpmultilang') {
            if (current_user_can('manage_options')) {
                flush_rewrite_rules(true);
            }
        }
    }

    /**
     * Setup language switching functionality
     */
    private function setup_language_switching() {
        // Add language switching hooks here
    }

    /**
     * Setup content filtering for multilingual content
     */
    private function setup_content_filtering() {
        // Add content filtering hooks here
    }

    /**
     * Setup admin features
     */
    private function setup_admin_features() {
        // Add admin functionality here
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
        }
    }

    /**
     * Setup hreflang handler
     */
    private function setup_hreflang_handler() {
        // Initialize hreflang handler
        FS_WPML_Hreflang_Handler::get_instance();
    }

    /**
     * Setup URL translator
     */
    private function setup_url_translator() {
        // Initialize URL translator
        FS_WPML_URL_Translator::get_instance();
    }

    /**
     * Setup taxonomy router
     */
    private function setup_taxonomy_router() {
        // Initialize taxonomy router for multilingual taxonomy URLs
        FS_WPML_Taxonomy_Router::get_instance();
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('FS WP Multilang Integration', 'fs-wpmultilang-integration'),
            __('FS Multilang', 'fs-wpmultilang-integration'),
            'manage_options',
            'fs-wpmultilang-integration',
            array($this, 'admin_page')
        );
    }

    /**
     * Admin page callback
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('FS WP Multilang Integration Settings', 'fs-wpmultilang-integration'); ?></h1>
            <p><?php _e('Configure the integration settings for WP Multilang.', 'fs-wpmultilang-integration'); ?></p>
            <!-- Add your settings form here -->
        </div>
        <?php
    }

    /**
     * Create default options
     */
    private function create_default_options() {
        $default_options = array(
            'enable_custom_routing' => true,
            'sync_post_translations' => true,
            'show_language_switcher' => true,
        );

        add_option('fs_wpmultilang_integration_settings', $default_options);
    }

    /**
     * Get plugin option
     *
     * @param string $option Option name
     * @param mixed $default Default value
     * @return mixed Option value
     */
    public function get_option($option, $default = false) {
        $options = get_option('fs_wpmultilang_integration_settings', array());
        return isset($options[$option]) ? $options[$option] : $default;
    }

    /**
     * Update plugin option
     *
     * @param string $option Option name
     * @param mixed $value Option value
     * @return bool Success
     */
    public function update_option($option, $value) {
        $options = get_option('fs_wpmultilang_integration_settings', array());
        $options[$option] = $value;
        return update_option('fs_wpmultilang_integration_settings', $options);
    }
}

/**
 * Initialize the plugin
 */
function fs_wpmultilang_integration_init() {
    return FS_WPML_Integration::get_instance();
}

// Start the plugin
fs_wpmultilang_integration_init();