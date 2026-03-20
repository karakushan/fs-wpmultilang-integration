<?php
/**
 * Admin Interface for Data Converter
 * 
 * Provides admin page and AJAX handlers for conversion.
 * 
 * @package FS_WPML_Integration
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FS_WPML_Converter_Admin
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
        // AJAX handlers
        add_action('wp_ajax_fs_wpml_convert_term_meta', [$this, 'ajax_convert_term_meta']);
        add_action('wp_ajax_fs_wpml_convert_post_meta', [$this, 'ajax_convert_post_meta']);
        add_action('wp_ajax_fs_wpml_convert_all', [$this, 'ajax_convert_all']);
        add_action('wp_ajax_fs_wpml_get_preview', [$this, 'ajax_get_preview']);
    }

    /**
     * Render admin page content
     */
    public function render_page()
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('FS WP Multilang - Data Converter', 'fs-wpmultilang-integration'); ?></h1>
            
            <!-- Warning about backup -->
            <div class="notice notice-warning" style="margin: 20px 0;">
                <p style="font-size: 14px; font-weight: bold;">
                    <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                    <?php echo esc_html__('IMPORTANT: Create a database backup before running conversion!', 'fs-wpmultilang-integration'); ?>
                </p>
                <p>
                    <?php echo esc_html__('The conversion process will modify meta fields in the database. Old fields with language suffixes will be deleted after conversion.', 'fs-wpmultilang-integration'); ?>
                </p>
            </div>

            <!-- Preview section -->
            <div class="fs-wpml-preview-section" style="margin: 20px 0;">
                <h2><?php echo esc_html__('Conversion Preview', 'fs-wpmultilang-integration'); ?></h2>
                <button type="button" class="button" id="fs-wpml-refresh-preview">
                    <?php echo esc_html__('Refresh Preview', 'fs-wpmultilang-integration'); ?>
                </button>
                <div id="fs-wpml-preview-content" style="margin-top: 15px;">
                    <p><em><?php echo esc_html__('Click "Refresh Preview" to see how many records will be converted.', 'fs-wpmultilang-integration'); ?></em></p>
                </div>
            </div>

            <!-- Conversion buttons -->
            <div class="fs-wpml-conversion-section" style="margin: 20px 0;">
                <h2><?php echo esc_html__('Run Conversion', 'fs-wpmultilang-integration'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Term Meta (Categories)', 'fs-wpmultilang-integration'); ?></th>
                        <td>
                            <button type="button" class="button button-primary fs-wpml-convert-btn" data-action="term_meta">
                                <?php echo esc_html__('Convert Term Meta', 'fs-wpmultilang-integration'); ?>
                            </button>
                            <p class="description">
                                <?php echo esc_html__('Converts: _seo_slug, _seo_title, _seo_description, _content', 'fs-wpmultilang-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Post Meta (Products)', 'fs-wpmultilang-integration'); ?></th>
                        <td>
                            <button type="button" class="button button-primary fs-wpml-convert-btn" data-action="post_meta">
                                <?php echo esc_html__('Convert Post Meta', 'fs-wpmultilang-integration'); ?>
                            </button>
                            <p class="description">
                                <?php echo esc_html__('Converts: fs_seo_slug', 'fs-wpmultilang-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Convert All', 'fs-wpmultilang-integration'); ?></th>
                        <td>
                            <button type="button" class="button fs-wpml-convert-btn" data-action="all" style="background: #46b450; border-color: #46b450; color: #fff;">
                                <?php echo esc_html__('Convert All Data', 'fs-wpmultilang-integration'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Results section -->
            <div id="fs-wpml-results" style="margin: 20px 0; display: none;">
                <h2><?php echo esc_html__('Conversion Results', 'fs-wpmultilang-integration'); ?></h2>
                <div id="fs-wpml-results-content"></div>
            </div>

            <!-- Loading overlay -->
            <div id="fs-wpml-loading" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.7); z-index: 100000;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                    <span class="spinner is-active" style="float: none; margin: 0;"></span>
                    <p><?php echo esc_html__('Converting...', 'fs-wpmultilang-integration'); ?></p>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Refresh preview
            $('#fs-wpml-refresh-preview').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fs_wpml_get_preview',
                        nonce: '<?php echo esc_js(wp_create_nonce('fs_wpml_converter')); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<table class="widefat striped"><thead><tr><th>' + 
                                '<?php echo esc_html__('Field', 'fs-wpmultilang-integration'); ?>' + '</th><th>' +
                                '<?php echo esc_html__('Records to Convert', 'fs-wpmultilang-integration'); ?>' + '</th></tr></thead><tbody>';
                            
                            // Term meta
                            if (response.data.term_meta) {
                                html += '<tr><td colspan="2" style="background: #f7f7f7; font-weight: bold;">' +
                                    '<?php echo esc_html__('Term Meta (Categories)', 'fs-wpmultilang-integration'); ?>' + '</td></tr>';
                                $.each(response.data.term_meta, function(field, info) {
                                    html += '<tr><td>' + field + '</td><td>' + info.count + '</td></tr>';
                                });
                            }
                            
                            // Post meta
                            if (response.data.post_meta) {
                                html += '<tr><td colspan="2" style="background: #f7f7f7; font-weight: bold;">' +
                                    '<?php echo esc_html__('Post Meta (Products)', 'fs-wpmultilang-integration'); ?>' + '</td></tr>';
                                $.each(response.data.post_meta, function(field, info) {
                                    html += '<tr><td>' + field + '</td><td>' + info.count + '</td></tr>';
                                });
                            }
                            
                            html += '</tbody></table>';
                            $('#fs-wpml-preview-content').html(html);
                        } else {
                            $('#fs-wpml-preview-content').html('<p style="color: red;">' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        $('#fs-wpml-preview-content').html('<p style="color: red;">AJAX error</p>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Convert buttons
            $('.fs-wpml-convert-btn').on('click', function() {
                var action = $(this).data('action');
                var actionMap = {
                    'term_meta': 'fs_wpml_convert_term_meta',
                    'post_meta': 'fs_wpml_convert_post_meta',
                    'all': 'fs_wpml_convert_all'
                };
                
                if (!confirm('<?php echo esc_html__('Did you create a database backup? Continue with conversion?', 'fs-wpmultilang-integration'); ?>')) {
                    return;
                }
                
                $('#fs-wpml-loading').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: actionMap[action],
                        nonce: '<?php echo esc_js(wp_create_nonce('fs_wpml_converter')); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div class="notice notice-success"><p>' +
                                '<?php echo esc_html__('Conversion completed successfully!', 'fs-wpmultilang-integration'); ?>' + '</p></div>';
                            
                            html += '<table class="widefat striped"><tbody>';
                            html += '<tr><td><strong><?php echo esc_html__('Processed', 'fs-wpmultilang-integration'); ?></strong></td><td>' + response.data.total_processed + '</td></tr>';
                            html += '<tr><td><strong><?php echo esc_html__('Converted', 'fs-wpmultilang-integration'); ?></strong></td><td>' + response.data.total_converted + '</td></tr>';
                            html += '<tr><td><strong><?php echo esc_html__('Errors', 'fs-wpmultilang-integration'); ?></strong></td><td>' + response.data.total_errors + '</td></tr>';
                            html += '</tbody></table>';
                            
                            $('#fs-wpml-results-content').html(html);
                            $('#fs-wpml-results').show();
                            
                            // Refresh preview
                            $('#fs-wpml-refresh-preview').trigger('click');
                        } else {
                            $('#fs-wpml-results-content').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                            $('#fs-wpml-results').show();
                        }
                    },
                    error: function() {
                        $('#fs-wpml-results-content').html('<div class="notice notice-error"><p>AJAX error</p></div>');
                        $('#fs-wpml-results').show();
                    },
                    complete: function() {
                        $('#fs-wpml-loading').hide();
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Get preview
     */
    public function ajax_get_preview()
    {
        check_ajax_referer('fs_wpml_converter', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $converter = FS_WPML_Data_Converter::get_instance();
        $preview = $converter->get_conversion_preview('all');

        wp_send_json_success($preview);
    }

    /**
     * AJAX: Convert term meta
     */
    public function ajax_convert_term_meta()
    {
        check_ajax_referer('fs_wpml_converter', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $converter = FS_WPML_Data_Converter::get_instance();
        $result = $converter->convert_term_meta();

        wp_send_json_success([
            'total_processed' => $result['processed'],
            'total_converted' => $result['converted'],
            'total_errors' => $result['errors'],
            'details' => $result
        ]);
    }

    /**
     * AJAX: Convert post meta
     */
    public function ajax_convert_post_meta()
    {
        check_ajax_referer('fs_wpml_converter', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $converter = FS_WPML_Data_Converter::get_instance();
        $result = $converter->convert_post_meta();

        wp_send_json_success([
            'total_processed' => $result['processed'],
            'total_converted' => $result['converted'],
            'total_errors' => $result['errors'],
            'details' => $result
        ]);
    }

    /**
     * AJAX: Convert all
     */
    public function ajax_convert_all()
    {
        check_ajax_referer('fs_wpml_converter', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $converter = FS_WPML_Data_Converter::get_instance();
        $result = $converter->convert_all();

        wp_send_json_success([
            'total_processed' => $result['total_processed'],
            'total_converted' => $result['total_converted'],
            'total_errors' => $result['total_errors'],
            'details' => $result
        ]);
    }
}
