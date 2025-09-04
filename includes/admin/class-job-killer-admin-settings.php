<?php
/**
 * Job Killer Admin Settings Class
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle admin settings
 */
class Job_Killer_Admin_Settings {
    
    /**
     * Settings sections
     */
    private $sections = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->init_sections();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Initialize settings sections
     */
    private function init_sections() {
        $this->sections = array(
            'import' => array(
                'title' => __('Import Settings', 'job-killer'),
                'description' => __('Configure how jobs are imported from RSS feeds.', 'job-killer'),
                'fields' => array(
                    'import_limit' => array(
                        'title' => __('Import Limit', 'job-killer'),
                        'type' => 'number',
                        'description' => __('Maximum number of jobs to import per execution.', 'job-killer'),
                        'default' => 50,
                        'min' => 1,
                        'max' => 500
                    ),
                    'age_filter' => array(
                        'title' => __('Age Filter (days)', 'job-killer'),
                        'type' => 'number',
                        'description' => __('Only import jobs newer than this many days. Set to 0 to import all jobs.', 'job-killer'),
                        'default' => 0,
                        'min' => 0,
                        'max' => 365
                    ),
                    'description_min_length' => array(
                        'title' => __('Minimum Description Length', 'job-killer'),
                        'type' => 'number',
                        'description' => __('Skip jobs with descriptions shorter than this many characters.', 'job-killer'),
                        'default' => 100,
                        'min' => 0,
                        'max' => 1000
                    ),
                    'deduplication_enabled' => array(
                        'title' => __('Enable Deduplication', 'job-killer'),
                        'type' => 'checkbox',
                        'description' => __('Prevent importing duplicate jobs based on title, company, and location.', 'job-killer'),
                        'default' => true
                    ),
                    'auto_taxonomies' => array(
                        'title' => __('Auto Create Taxonomies', 'job-killer'),
                        'type' => 'checkbox',
                        'description' => __('Automatically create regions and categories from job data.', 'job-killer'),
                        'default' => true
                    )
                )
            ),
            'performance' => array(
                'title' => __('Performance Settings', 'job-killer'),
                'description' => __('Configure performance and caching options.', 'job-killer'),
                'fields' => array(
                    'timeout' => array(
                        'title' => __('Request Timeout (seconds)', 'job-killer'),
                        'type' => 'number',
                        'description' => __('Maximum time to wait for RSS feed responses.', 'job-killer'),
                        'default' => 30,
                        'min' => 5,
                        'max' => 300
                    ),
                    'request_delay' => array(
                        'title' => __('Request Delay (seconds)', 'job-killer'),
                        'type' => 'number',
                        'description' => __('Delay between requests to avoid overwhelming servers.', 'job-killer'),
                        'default' => 1,
                        'min' => 0,
                        'max' => 60
                    ),
                    'cache_duration' => array(
                        'title' => __('Cache Duration (seconds)', 'job-killer'),
                        'type' => 'number',
                        'description' => __('How long to cache RSS feed data.', 'job-killer'),
                        'default' => 3600,
                        'min' => 300,
                        'max' => 86400
                    )
                )
            ),
            'scheduling' => array(
                'title' => __('Scheduling Settings', 'job-killer'),
                'description' => __('Configure automatic import scheduling.', 'job-killer'),
                'fields' => array(
                    'cron_interval' => array(
                        'title' => __('Import Interval', 'job-killer'),
                        'type' => 'select',
                        'description' => __('How often to automatically import jobs.', 'job-killer'),
                        'default' => 'twicedaily',
                        'options' => array(
                            'every_30_minutes' => __('Every 30 Minutes', 'job-killer'),
                            'hourly' => __('Hourly', 'job-killer'),
                            'every_2_hours' => __('Every 2 Hours', 'job-killer'),
                            'every_6_hours' => __('Every 6 Hours', 'job-killer'),
                            'twicedaily' => __('Twice Daily', 'job-killer'),
                            'daily' => __('Daily', 'job-killer')
                        )
                    )
                )
            ),
            'notifications' => array(
                'title' => __('Notification Settings', 'job-killer'),
                'description' => __('Configure email notifications and alerts.', 'job-killer'),
                'fields' => array(
                    'email_notifications' => array(
                        'title' => __('Enable Email Notifications', 'job-killer'),
                        'type' => 'checkbox',
                        'description' => __('Send email notifications for import results and errors.', 'job-killer'),
                        'default' => true
                    ),
                    'notification_email' => array(
                        'title' => __('Notification Email', 'job-killer'),
                        'type' => 'email',
                        'description' => __('Email address to receive notifications.', 'job-killer'),
                        'default' => get_option('admin_email')
                    )
                )
            ),
            'logs' => array(
                'title' => __('Log Settings', 'job-killer'),
                'description' => __('Configure logging and data retention.', 'job-killer'),
                'fields' => array(
                    'log_retention_days' => array(
                        'title' => __('Log Retention (days)', 'job-killer'),
                        'type' => 'number',
                        'description' => __('How long to keep log entries before automatic cleanup.', 'job-killer'),
                        'default' => 30,
                        'min' => 1,
                        'max' => 365
                    )
                )
            ),
            'seo' => array(
                'title' => __('SEO Settings', 'job-killer'),
                'description' => __('Configure SEO and structured data options.', 'job-killer'),
                'fields' => array(
                    'structured_data' => array(
                        'title' => __('Enable Structured Data', 'job-killer'),
                        'type' => 'checkbox',
                        'description' => __('Add JSON-LD structured data for better search engine visibility.', 'job-killer'),
                        'default' => true
                    )
                )
            )
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('job_killer_settings', 'job_killer_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
        
        foreach ($this->sections as $section_id => $section) {
            add_settings_section(
                'job_killer_' . $section_id,
                $section['title'],
                function() use ($section) {
                    if (!empty($section['description'])) {
                        echo '<p>' . esc_html($section['description']) . '</p>';
                    }
                },
                'job_killer_settings'
            );
            
            foreach ($section['fields'] as $field_id => $field) {
                add_settings_field(
                    $field_id,
                    $field['title'],
                    array($this, 'render_field'),
                    'job_killer_settings',
                    'job_killer_' . $section_id,
                    array(
                        'field_id' => $field_id,
                        'field' => $field
                    )
                );
            }
        }
    }
    
    /**
     * Render settings field
     */
    public function render_field($args) {
        $field_id = $args['field_id'];
        $field = $args['field'];
        $settings = get_option('job_killer_settings', array());
        $value = isset($settings[$field_id]) ? $settings[$field_id] : $field['default'];
        
        $name = "job_killer_settings[{$field_id}]";
        $id = "job_killer_settings_{$field_id}";
        
        switch ($field['type']) {
            case 'text':
                echo '<input type="text" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                break;
                
            case 'email':
                echo '<input type="email" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                break;
                
            case 'number':
                $min = isset($field['min']) ? ' min="' . esc_attr($field['min']) . '"' : '';
                $max = isset($field['max']) ? ' max="' . esc_attr($field['max']) . '"' : '';
                echo '<input type="number" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="small-text"' . $min . $max . ' />';
                break;
                
            case 'checkbox':
                echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1"' . checked(1, $value, false) . ' />';
                echo '<label for="' . esc_attr($id) . '">' . esc_html($field['description']) . '</label>';
                return; // Skip description below
                
            case 'select':
                echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '">';
                foreach ($field['options'] as $option_value => $option_label) {
                    echo '<option value="' . esc_attr($option_value) . '"' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
                }
                echo '</select>';
                break;
                
            case 'textarea':
                echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="5" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
                break;
        }
        
        if (!empty($field['description']) && $field['type'] !== 'checkbox') {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        foreach ($this->sections as $section) {
            foreach ($section['fields'] as $field_id => $field) {
                if (!isset($input[$field_id])) {
                    $sanitized[$field_id] = $field['default'];
                    continue;
                }
                
                $value = $input[$field_id];
                
                switch ($field['type']) {
                    case 'text':
                        $sanitized[$field_id] = sanitize_text_field($value);
                        break;
                        
                    case 'email':
                        $sanitized[$field_id] = sanitize_email($value);
                        break;
                        
                    case 'number':
                        $sanitized[$field_id] = intval($value);
                        if (isset($field['min']) && $sanitized[$field_id] < $field['min']) {
                            $sanitized[$field_id] = $field['min'];
                        }
                        if (isset($field['max']) && $sanitized[$field_id] > $field['max']) {
                            $sanitized[$field_id] = $field['max'];
                        }
                        break;
                        
                    case 'checkbox':
                        $sanitized[$field_id] = !empty($value);
                        break;
                        
                    case 'select':
                        if (isset($field['options'][$value])) {
                            $sanitized[$field_id] = $value;
                        } else {
                            $sanitized[$field_id] = $field['default'];
                        }
                        break;
                        
                    case 'textarea':
                        $sanitized[$field_id] = sanitize_textarea_field($value);
                        break;
                        
                    default:
                        $sanitized[$field_id] = sanitize_text_field($value);
                }
            }
        }
        
        // Update cron schedule if changed
        $current_settings = get_option('job_killer_settings', array());
        if (isset($sanitized['cron_interval']) && 
            (!isset($current_settings['cron_interval']) || $current_settings['cron_interval'] !== $sanitized['cron_interval'])) {
            
            // Clear existing schedule
            wp_clear_scheduled_hook('job_killer_import_jobs');
            
            // Schedule with new interval
            wp_schedule_event(time(), $sanitized['cron_interval'], 'job_killer_import_jobs');
        }
        
        return $sanitized;
    }
    
    /**
     * Render settings page
     */
    public function render_page() {
        // Handle form submission
        if (isset($_POST['submit'])) {
            check_admin_referer('job_killer_settings-options');
            
            $settings = $this->sanitize_settings($_POST['job_killer_settings']);
            update_option('job_killer_settings', $settings);
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'job-killer') . '</p></div>';
        }
        
        include JOB_KILLER_PLUGIN_DIR . 'includes/templates/admin/settings.php';
    }
    
    /**
     * Get settings sections
     */
    public function get_sections() {
        return $this->sections;
    }
    
    /**
     * Reset settings to defaults
     */
    public function reset_to_defaults() {
        $defaults = array();
        
        foreach ($this->sections as $section) {
            foreach ($section['fields'] as $field_id => $field) {
                $defaults[$field_id] = $field['default'];
            }
        }
        
        update_option('job_killer_settings', $defaults);
        
        return true;
    }
    
    /**
     * Export settings
     */
    public function export_settings() {
        $settings = get_option('job_killer_settings', array());
        $feeds = get_option('job_killer_feeds', array());
        
        $export_data = array(
            'settings' => $settings,
            'feeds' => $feeds,
            'version' => JOB_KILLER_VERSION,
            'exported_at' => current_time('mysql')
        );
        
        return wp_json_encode($export_data, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import settings
     */
    public function import_settings($json_data) {
        $data = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Invalid JSON data', 'job-killer'));
        }
        
        if (!isset($data['settings']) || !isset($data['feeds'])) {
            return new WP_Error('invalid_format', __('Invalid export format', 'job-killer'));
        }
        
        // Sanitize and update settings
        $settings = $this->sanitize_settings($data['settings']);
        update_option('job_killer_settings', $settings);
        
        // Sanitize and update feeds
        $helper = new Job_Killer_Helper();
        $feeds = array();
        
        foreach ($data['feeds'] as $feed_id => $feed_config) {
            $feeds[$feed_id] = $helper->sanitize_feed_config($feed_config);
        }
        
        update_option('job_killer_feeds', $feeds);
        
        return true;
    }
}