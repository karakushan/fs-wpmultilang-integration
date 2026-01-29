<?php
/**
 * Uninstall script for FS WP Multilang Integration
 * 
 * This file is called when the plugin is uninstalled/unactivated
 */

// Exit if not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('fs_wpmultilang_integration_settings');

// Clear any transients
delete_transient('fs_wpmultilang_integration_cache');