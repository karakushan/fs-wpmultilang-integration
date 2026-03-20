<?php
/**
 * Data Converter for FS WP Multilang Integration
 * 
 * Converts meta fields with language suffixes to WP Multilang format.
 * 
 * @package FS_WPML_Integration
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FS_WPML_Data_Converter
{
    /**
     * Instance of the class
     */
    private static $instance = null;

    /**
     * Term meta fields to convert
     */
    private $term_meta_fields = [
        '_seo_slug',
        '_seo_title',
        '_seo_description',
        '_content'
    ];

    /**
     * Post meta fields to convert
     */
    private $post_meta_fields = [
        'fs_seo_slug'
    ];

    /**
     * Language suffix mapping (normalized to lowercase)
     * Maps database suffixes to WP Multilang language codes
     */
    private $lang_suffixes = [
        'ru_ru' => 'ru',
        'uk' => 'ua',
        'en_us' => 'en',
    ];

    /**
     * Get all possible case variants for a suffix
     * 
     * @param string $suffix Base suffix like 'ru_ru'
     * @return array Array of possible suffix variants (ru_ru, ru_RU, RU_RU, etc.)
     */
    private function get_suffix_case_variants($suffix)
    {
        $variants = [$suffix];
        
        $parts = explode('_', $suffix);
        if (count($parts) === 2) {
            // ru_ru -> ru_RU
            $variants[] = $parts[0] . '_' . strtoupper($parts[1]);
            // ru_ru -> RU_ru  
            $variants[] = strtoupper($parts[0]) . '_' . $parts[1];
            // ru_ru -> RU_RU
            $variants[] = strtoupper($parts[0]) . '_' . strtoupper($parts[1]);
        }
        
        return array_unique($variants);
    }

    /**
     * Get all suffix patterns for SQL query (including case variants)
     * 
     * @param string $field Base field name
     * @return array Array of full meta_key patterns
     */
    private function get_all_suffix_patterns($field)
    {
        $patterns = [];
        
        foreach ($this->lang_suffixes as $suffix => $lang_code) {
            $variants = $this->get_suffix_case_variants($suffix);
            foreach ($variants as $variant) {
                $patterns[] = $field . '__' . $variant;
            }
        }
        
        return $patterns;
    }

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
        // Initialize hooks if needed
    }

    /**
     * Convert term meta fields
     * 
     * @return array Result with statistics
     */
    public function convert_term_meta()
    {
        global $wpdb;

        $stats = [
            'processed' => 0,
            'converted' => 0,
            'errors' => 0,
            'details' => []
        ];

        foreach ($this->term_meta_fields as $field) {
            $result = $this->convert_meta_field($wpdb->termmeta, 'term_id', $field);
            $stats['processed'] += $result['processed'];
            $stats['converted'] += $result['converted'];
            $stats['errors'] += $result['errors'];
            $stats['details'][$field] = $result;
        }

        // Flush rewrite rules after term meta conversion
        $this->flush_rewrite_rules();

        return $stats;
    }

    /**
     * Convert post meta fields
     * 
     * @return array Result with statistics
     */
    public function convert_post_meta()
    {
        global $wpdb;

        $stats = [
            'processed' => 0,
            'converted' => 0,
            'errors' => 0,
            'details' => []
        ];

        foreach ($this->post_meta_fields as $field) {
            $result = $this->convert_meta_field($wpdb->postmeta, 'post_id', $field);
            $stats['processed'] += $result['processed'];
            $stats['converted'] += $result['converted'];
            $stats['errors'] += $result['errors'];
            $stats['details'][$field] = $result;
        }

        // Flush rewrite rules after post meta conversion
        $this->flush_rewrite_rules();

        return $stats;
    }

    /**
     * Convert all meta fields
     * 
     * @return array Result with statistics
     */
    public function convert_all()
    {
        $term_result = $this->convert_term_meta();
        $post_result = $this->convert_post_meta();

        return [
            'term_meta' => $term_result,
            'post_meta' => $post_result,
            'total_processed' => $term_result['processed'] + $post_result['processed'],
            'total_converted' => $term_result['converted'] + $post_result['converted'],
            'total_errors' => $term_result['errors'] + $post_result['errors']
        ];
    }

    /**
     * Flush rewrite rules
     */
    private function flush_rewrite_rules()
    {
        flush_rewrite_rules(true);
    }

    /**
     * Convert a specific meta field
     * 
     * @param string $table Table name (termmeta or postmeta)
     * @param string $id_column ID column name (term_id or post_id)
     * @param string $field Base field name
     * @return array Result with statistics
     */
    private function convert_meta_field($table, $id_column, $field)
    {
        global $wpdb;

        $stats = [
            'processed' => 0,
            'converted' => 0,
            'errors' => 0,
            'messages' => []
        ];

        // Get all suffix patterns including case variants
        $suffix_patterns = $this->get_all_suffix_patterns($field);

        $in_clause = implode(',', array_map(function($p) use ($wpdb) {
            return $wpdb->prepare('%s', $p);
        }, $suffix_patterns));

        // Find all objects with suffixed meta keys
        $sql = "SELECT DISTINCT {$id_column} FROM {$table} WHERE meta_key IN ({$in_clause})";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $object_ids = $wpdb->get_col($sql);

        if (empty($object_ids)) {
            $stats['messages'][] = "No records found for field: {$field}";
            return $stats;
        }

        foreach ($object_ids as $object_id) {
            $stats['processed']++;

            // Collect values for each language (check all case variants)
            $values = [];
            foreach ($this->lang_suffixes as $suffix => $lang_code) {
                $case_variants = $this->get_suffix_case_variants($suffix);
                
                foreach ($case_variants as $variant) {
                    $suffixed_key = $field . '__' . $variant;
                    
                    if ($table === $wpdb->termmeta) {
                        $value = get_term_meta($object_id, $suffixed_key, true);
                    } else {
                        $value = get_post_meta($object_id, $suffixed_key, true);
                    }

                    if (!empty($value)) {
                        $values[$lang_code] = $value;
                        break; // Found value, no need to check other variants
                    }
                }
            }

            // Skip if no values found
            if (empty($values)) {
                continue;
            }

            // Determine if this is a slug field (requires lowercase)
            $is_slug_field = (strpos($field, 'slug') !== false);

            // Build WP Multilang format string
            $ml_string = $this->build_ml_string($values, $is_slug_field);

            if (empty($ml_string)) {
                $stats['errors']++;
                $stats['messages'][] = "Failed to build ML string for {$field}, ID: {$object_id}";
                continue;
            }

            // Save the converted value
            if ($table === $wpdb->termmeta) {
                $saved = update_term_meta($object_id, $field, $ml_string);
            } else {
                $saved = update_post_meta($object_id, $field, $ml_string);
            }

            if (is_wp_error($saved) || false === $saved) {
                $stats['errors']++;
                $stats['messages'][] = "Failed to save {$field} for ID: {$object_id}";
                continue;
            }

            // Delete old suffixed meta fields (all case variants)
            foreach ($this->lang_suffixes as $suffix => $lang_code) {
                $case_variants = $this->get_suffix_case_variants($suffix);
                
                foreach ($case_variants as $variant) {
                    $suffixed_key = $field . '__' . $variant;
                    
                    if ($table === $wpdb->termmeta) {
                        delete_term_meta($object_id, $suffixed_key);
                    } else {
                        delete_post_meta($object_id, $suffixed_key);
                    }
                }
            }

            $stats['converted']++;
        }

        return $stats;
    }

    /**
     * Build WP Multilang format string
     * 
     * @param array $values Array of language code => value pairs
     * @param bool $lowercase Whether to convert to lowercase (for slugs)
     * @return string WP Multilang format string
     */
    private function build_ml_string($values, $lowercase = false)
    {
        if (empty($values) || !is_array($values)) {
            return '';
        }

        $string = '';

        // Get available languages from WP Multilang
        $languages = [];
        if (function_exists('wpm_get_lang_option')) {
            $languages = array_keys(wpm_get_lang_option());
        } else {
            // Fallback to default languages
            $languages = ['ru', 'ua'];
        }

        foreach ($languages as $lang) {
            if (isset($values[$lang]) && $values[$lang] !== '') {
                $value = trim($values[$lang]);
                // Convert to lowercase for slugs
                if ($lowercase) {
                    $value = strtolower($value);
                }
                $string .= '[:' . $lang . ']' . $value;
            }
        }

        if (!empty($string)) {
            $string .= '[:]';
        }

        return $string;
    }

    /**
     * Get preview of conversion (dry run)
     * 
     * @param string $type 'term' or 'post' or 'all'
     * @return array Preview data
     */
    public function get_conversion_preview($type = 'all')
    {
        $preview = [
            'term_meta' => [],
            'post_meta' => []
        ];

        if ($type === 'all' || $type === 'term') {
            $preview['term_meta'] = $this->get_meta_preview($this->term_meta_fields, 'term');
        }

        if ($type === 'all' || $type === 'post') {
            $preview['post_meta'] = $this->get_meta_preview($this->post_meta_fields, 'post');
        }

        return $preview;
    }

    /**
     * Get preview for specific meta fields
     * 
     * @param array $fields Fields to preview
     * @param string $type 'term' or 'post'
     * @return array Preview data
     */
    private function get_meta_preview($fields, $type)
    {
        global $wpdb;

        $preview = [];
        $table = $type === 'term' ? $wpdb->termmeta : $wpdb->postmeta;
        $id_column = $type === 'term' ? 'term_id' : 'post_id';

        foreach ($fields as $field) {
            $suffix_patterns = $this->get_all_suffix_patterns($field);

            $in_clause = implode(',', array_map(function($p) use ($wpdb) {
                return $wpdb->prepare('%s', $p);
            }, $suffix_patterns));

            $sql = "SELECT COUNT(DISTINCT {$id_column}) FROM {$table} WHERE meta_key IN ({$in_clause})";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $count = $wpdb->get_var($sql);

            $preview[$field] = [
                'count' => intval($count),
                'field' => $field
            ];
        }

        return $preview;
    }
}
