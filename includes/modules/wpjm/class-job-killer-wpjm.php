<?php
/**
 * Job Killer WP Job Manager Integration
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle WP Job Manager integration
 */
class Job_Killer_Wpjm {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check if WP Job Manager is active
        add_action('plugins_loaded', array($this, 'check_wpjm_compatibility'));
        
        // Integration hooks
        add_filter('job_killer_post_type_args', array($this, 'modify_post_type_args'));
        add_filter('job_killer_taxonomy_args', array($this, 'modify_taxonomy_args'));
        add_action('job_killer_after_job_import', array($this, 'sync_wpjm_data'), 10, 2);
        
        // Admin integration
        add_action('admin_notices', array($this, 'show_compatibility_notices'));
        add_filter('job_killer_settings_sections', array($this, 'add_wpjm_settings'));
    }
    
    /**
     * Check WP Job Manager compatibility
     */
    public function check_wpjm_compatibility() {
        if (!class_exists('WP_Job_Manager')) {
            return;
        }
        
        // Check version compatibility
        $wpjm_version = defined('JOB_MANAGER_VERSION') ? JOB_MANAGER_VERSION : '0.0.0';
        $min_version = '1.29.0';
        
        if (version_compare($wpjm_version, $min_version, '<')) {
            add_action('admin_notices', function() use ($wpjm_version, $min_version) {
                echo '<div class="notice notice-warning">';
                echo '<p>' . sprintf(
                    __('Job Killer requires WP Job Manager version %s or higher. You are running version %s.', 'job-killer'),
                    $min_version,
                    $wpjm_version
                ) . '</p>';
                echo '</div>';
            });
            return;
        }
        
        // Initialize integration
        $this->init_wpjm_integration();
    }
    
    /**
     * Initialize WP Job Manager integration
     */
    private function init_wpjm_integration() {
        // Use WP Job Manager's post type and taxonomies
        add_filter('job_killer_use_wpjm_structure', '__return_true');
        
        // Sync existing data
        add_action('init', array($this, 'maybe_sync_existing_data'));
        
        // Add WPJM-specific meta fields
        add_filter('job_killer_job_meta_fields', array($this, 'add_wpjm_meta_fields'));
        
        // Integration with WPJM templates
        add_filter('job_killer_use_wpjm_templates', '__return_true');
        
        // Hook into WPJM actions
        add_action('job_manager_job_submitted', array($this, 'handle_manual_job_submission'));
        add_action('job_manager_job_edited', array($this, 'handle_job_edit'));
    }
    
    /**
     * Modify post type arguments for WPJM compatibility
     */
    public function modify_post_type_args($args) {
        if (!class_exists('WP_Job_Manager')) {
            return $args;
        }
        
        // Don't register our own post type if WPJM is active
        return false;
    }
    
    /**
     * Modify taxonomy arguments for WPJM compatibility
     */
    public function modify_taxonomy_args($args) {
        if (!class_exists('WP_Job_Manager')) {
            return $args;
        }
        
        // Don't register our own taxonomies if WPJM is active
        return false;
    }
    
    /**
     * Sync data after job import
     */
    public function sync_wpjm_data($post_id, $job_data) {
        if (!class_exists('WP_Job_Manager')) {
            return;
        }
        
        // Add WPJM-specific meta fields
        $wpjm_meta = array(
            '_job_location' => $job_data['location'] ?? '',
            '_company_name' => $job_data['company'] ?? '',
            '_application' => $job_data['url'] ?? '',
            '_job_expires' => $this->calculate_expiry_date($job_data),
            '_filled' => 0,
            '_featured' => 0,
            '_job_salary' => $job_data['salary'] ?? '',
            '_remote_position' => $this->is_remote_job($job_data) ? 1 : 0,
            '_company_website' => $this->extract_company_website($job_data),
            '_company_tagline' => $this->extract_company_tagline($job_data),
            '_company_twitter' => $this->extract_company_twitter($job_data),
            '_company_video' => $this->extract_company_video($job_data)
        );
        
        foreach ($wpjm_meta as $key => $value) {
            if (!empty($value)) {
                update_post_meta($post_id, $key, $value);
            }
        }
        
        // Set job status
        update_post_meta($post_id, '_job_status', 'active');
        
        // Trigger WPJM hooks
        do_action('job_manager_job_submitted', $post_id);
    }
    
    /**
     * Maybe sync existing data
     */
    public function maybe_sync_existing_data() {
        if (get_option('job_killer_wpjm_synced')) {
            return;
        }
        
        // Schedule background sync
        wp_schedule_single_event(time() + 60, 'job_killer_sync_wpjm_data');
        add_action('job_killer_sync_wpjm_data', array($this, 'sync_existing_jobs'));
        
        update_option('job_killer_wpjm_synced', true);
    }
    
    /**
     * Sync existing jobs with WPJM structure
     */
    public function sync_existing_jobs() {
        $jobs = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_job_killer_imported',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        foreach ($jobs as $job) {
            // Ensure WPJM meta fields are present
            $required_fields = array(
                '_job_location',
                '_company_name',
                '_application',
                '_job_expires',
                '_filled',
                '_featured'
            );
            
            foreach ($required_fields as $field) {
                if (!get_post_meta($job->ID, $field, true)) {
                    // Set default values
                    switch ($field) {
                        case '_filled':
                        case '_featured':
                            update_post_meta($job->ID, $field, 0);
                            break;
                        case '_job_expires':
                            update_post_meta($job->ID, $field, date('Y-m-d', strtotime('+30 days')));
                            break;
                    }
                }
            }
            
            // Ensure job status
            if (!get_post_meta($job->ID, '_job_status', true)) {
                update_post_meta($job->ID, '_job_status', 'active');
            }
        }
    }
    
    /**
     * Add WPJM-specific meta fields
     */
    public function add_wpjm_meta_fields($fields) {
        $wpjm_fields = array(
            '_company_website',
            '_company_tagline',
            '_company_twitter',
            '_company_video',
            '_job_status'
        );
        
        return array_merge($fields, $wpjm_fields);
    }
    
    /**
     * Handle manual job submission
     */
    public function handle_manual_job_submission($job_id) {
        // Mark as manually submitted (not imported)
        update_post_meta($job_id, '_job_killer_manual', true);
        
        // Log the submission
        $helper = new Job_Killer_Helper();
        $helper->log('info', 'wpjm', 
            sprintf('Manual job submission: %s', get_the_title($job_id)),
            array('job_id' => $job_id)
        );
    }
    
    /**
     * Handle job edit
     */
    public function handle_job_edit($job_id) {
        // Check if this is an imported job
        if (get_post_meta($job_id, '_job_killer_imported', true)) {
            // Mark as manually edited
            update_post_meta($job_id, '_job_killer_edited', current_time('mysql'));
            
            // Log the edit
            $helper = new Job_Killer_Helper();
            $helper->log('info', 'wpjm', 
                sprintf('Imported job edited: %s', get_the_title($job_id)),
                array('job_id' => $job_id)
            );
        }
    }
    
    /**
     * Show compatibility notices
     */
    public function show_compatibility_notices() {
        if (!class_exists('WP_Job_Manager')) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'job-killer') === false) {
            return;
        }
        
        echo '<div class="notice notice-info">';
        echo '<p><strong>' . __('WP Job Manager Integration Active', 'job-killer') . '</strong></p>';
        echo '<p>' . __('Job Killer is now using WP Job Manager\'s post types and taxonomies for full compatibility.', 'job-killer') . '</p>';
        echo '</div>';
    }
    
    /**
     * Add WPJM settings section
     */
    public function add_wpjm_settings($sections) {
        if (!class_exists('WP_Job_Manager')) {
            return $sections;
        }
        
        $sections['wpjm'] = array(
            'title' => __('WP Job Manager Integration', 'job-killer'),
            'description' => __('Configure integration with WP Job Manager plugin.', 'job-killer'),
            'fields' => array(
                'wpjm_sync_categories' => array(
                    'title' => __('Sync Categories', 'job-killer'),
                    'type' => 'checkbox',
                    'description' => __('Automatically sync job categories with WP Job Manager.', 'job-killer'),
                    'default' => true
                ),
                'wpjm_sync_types' => array(
                    'title' => __('Sync Job Types', 'job-killer'),
                    'type' => 'checkbox',
                    'description' => __('Automatically sync job types with WP Job Manager.', 'job-killer'),
                    'default' => true
                ),
                'wpjm_preserve_manual' => array(
                    'title' => __('Preserve Manual Jobs', 'job-killer'),
                    'type' => 'checkbox',
                    'description' => __('Don\'t modify jobs that were manually submitted through WP Job Manager.', 'job-killer'),
                    'default' => true
                ),
                'wpjm_company_data' => array(
                    'title' => __('Extract Company Data', 'job-killer'),
                    'type' => 'checkbox',
                    'description' => __('Try to extract additional company information from job descriptions.', 'job-killer'),
                    'default' => false
                )
            )
        );
        
        return $sections;
    }
    
    /**
     * Calculate expiry date
     */
    private function calculate_expiry_date($job_data) {
        if (!empty($job_data['expires'])) {
            return date('Y-m-d', strtotime($job_data['expires']));
        }
        
        return date('Y-m-d', strtotime('+30 days'));
    }
    
    /**
     * Check if job is remote
     */
    private function is_remote_job($job_data) {
        $remote_keywords = array('remoto', 'remote', 'home office', 'trabalho remoto', 'teletrabalho');
        
        $search_text = strtolower(
            ($job_data['title'] ?? '') . ' ' . 
            ($job_data['description'] ?? '') . ' ' . 
            ($job_data['location'] ?? '')
        );
        
        foreach ($remote_keywords as $keyword) {
            if (strpos($search_text, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract company website
     */
    private function extract_company_website($job_data) {
        $description = $job_data['description'] ?? '';
        
        // Look for website URLs
        if (preg_match('/(?:website|site|www)[:\s]*([^\s]+\.[a-z]{2,})/i', $description, $matches)) {
            $url = $matches[1];
            if (!preg_match('/^https?:\/\//', $url)) {
                $url = 'http://' . $url;
            }
            return esc_url($url);
        }
        
        return '';
    }
    
    /**
     * Extract company tagline
     */
    private function extract_company_tagline($job_data) {
        $description = $job_data['description'] ?? '';
        
        // Look for tagline patterns
        if (preg_match('/(?:tagline|slogan|motto)[:\s]*([^\n\r]{10,100})/i', $description, $matches)) {
            return sanitize_text_field(trim($matches[1]));
        }
        
        return '';
    }
    
    /**
     * Extract company Twitter
     */
    private function extract_company_twitter($job_data) {
        $description = $job_data['description'] ?? '';
        
        // Look for Twitter handles
        if (preg_match('/@([a-zA-Z0-9_]{1,15})/', $description, $matches)) {
            return sanitize_text_field($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Extract company video
     */
    private function extract_company_video($job_data) {
        $description = $job_data['description'] ?? '';
        
        // Look for YouTube or Vimeo URLs
        if (preg_match('/(youtube\.com\/watch\?v=|youtu\.be\/|vimeo\.com\/)([^\s&]+)/i', $description, $matches)) {
            return esc_url($matches[0]);
        }
        
        return '';
    }
    
    /**
     * Get WPJM compatibility status
     */
    public function get_compatibility_status() {
        return array(
            'wpjm_active' => class_exists('WP_Job_Manager'),
            'wpjm_version' => defined('JOB_MANAGER_VERSION') ? JOB_MANAGER_VERSION : null,
            'compatible' => class_exists('WP_Job_Manager') && version_compare(JOB_MANAGER_VERSION, '1.29.0', '>='),
            'integration_active' => apply_filters('job_killer_use_wpjm_structure', false),
            'synced_jobs' => $this->get_synced_jobs_count()
        );
    }
    
    /**
     * Get count of synced jobs
     */
    private function get_synced_jobs_count() {
        global $wpdb;
        
        return $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_job_killer_imported'
        ");
    }
}

// Initialize if WP Job Manager integration is enabled
if (get_option('job_killer_wpjm_integration', true)) {
    new Job_Killer_Wpjm();
}