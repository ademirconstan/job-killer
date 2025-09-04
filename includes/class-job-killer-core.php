<?php
/**
 * Job Killer Core Class
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core plugin functionality
 */
class Job_Killer_Core {
    
    /**
     * Plugin components
     */
    public $admin;
    public $importer;
    public $frontend;
    public $helper;
    public $rss_providers;
    public $providers_manager;
    public $structured_data;
    public $cron;
    public $api;
    
    /**
     * Initialization flag
     */
    private $initialized = false;
    
    /**
     * Initialize core
     */
    public function init() {
        // Prevent double initialization
        if ($this->initialized) {
            return;
        }
        
        $this->init_hooks();
        $this->load_components();
        
        $this->initialized = true;
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'register_post_types'), 20);
        add_action('init', array($this, 'register_taxonomies'), 25);
        add_action('init', array($this, 'maybe_flush_rewrite_rules'), 30);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('job_killer_import_jobs', array($this, 'run_import'));
        add_action('job_killer_cleanup_logs', array($this, 'cleanup_logs'));
        
        // Add custom cron intervals
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        
        // Add structured data
        add_action('wp_head', array($this, 'add_structured_data'));
    }
    
    /**
     * Load plugin components
     */
    private function load_components() {
        // Load components with error checking
        if (class_exists('Job_Killer_Helper')) {
            $this->helper = new Job_Killer_Helper();
        }
        
        if (class_exists('Job_Killer_Importer')) {
            $this->importer = new Job_Killer_Importer();
        }
        
        if (class_exists('Job_Killer_Frontend')) {
            $this->frontend = new Job_Killer_Frontend();
        }
        
        if (class_exists('Job_Killer_Rss_Providers')) {
            $this->rss_providers = new Job_Killer_Rss_Providers();
        }
        
        if (class_exists('Job_Killer_Providers_Manager')) {
            $this->providers_manager = new Job_Killer_Providers_Manager();
        }
        
        if (class_exists('Job_Killer_Structured_Data')) {
            $this->structured_data = new Job_Killer_Structured_Data();
        }
        
        if (class_exists('Job_Killer_Cron')) {
            $this->cron = new Job_Killer_Cron();
        }
        
        if (class_exists('Job_Killer_Api')) {
            $this->api = new Job_Killer_Api();
        }
        
        if (is_admin()) {
            if (class_exists('Job_Killer_Admin')) {
                $this->admin = new Job_Killer_Admin();
            }
        }
    }
    
    /**
     * Maybe flush rewrite rules
     */
    public function maybe_flush_rewrite_rules() {
        if (get_transient('job_killer_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_transient('job_killer_flush_rewrite_rules');
        }
    }
    
    /**
     * Register job listing post type
     */
    public function register_post_types() {
        // Don't register if WP Job Manager is handling this
        if (apply_filters('job_killer_use_wpjm_structure', false)) {
            return;
        }
        
        $labels = array(
            'name' => __('Job Listings', 'job-killer'),
            'singular_name' => __('Job Listing', 'job-killer'),
            'menu_name' => __('Jobs', 'job-killer'),
            'add_new' => __('Add New', 'job-killer'),
            'add_new_item' => __('Add New Job Listing', 'job-killer'),
            'edit_item' => __('Edit Job Listing', 'job-killer'),
            'new_item' => __('New Job Listing', 'job-killer'),
            'view_item' => __('View Job Listing', 'job-killer'),
            'search_items' => __('Search Job Listings', 'job-killer'),
            'not_found' => __('No job listings found', 'job-killer'),
            'not_found_in_trash' => __('No job listings found in trash', 'job-killer')
        );
        
        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'jobs'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-businessman',
            'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields'),
            'show_in_rest' => true
        );
        
        // Allow filtering of post type args
        $args = apply_filters('job_killer_post_type_args', $args);
        
        // Don't register if filtered to false
        if ($args === false) {
            return;
        }
        
        register_post_type('job_listing', $args);
    }
    
    /**
     * Register taxonomies
     */
    public function register_taxonomies() {
        // Don't register if WP Job Manager is handling this
        if (apply_filters('job_killer_use_wpjm_structure', false)) {
            return;
        }
        
        // Job categories
        $category_labels = array(
            'name' => __('Job Categories', 'job-killer'),
            'singular_name' => __('Job Category', 'job-killer'),
            'search_items' => __('Search Job Categories', 'job-killer'),
            'all_items' => __('All Job Categories', 'job-killer'),
            'parent_item' => __('Parent Job Category', 'job-killer'),
            'parent_item_colon' => __('Parent Job Category:', 'job-killer'),
            'edit_item' => __('Edit Job Category', 'job-killer'),
            'update_item' => __('Update Job Category', 'job-killer'),
            'add_new_item' => __('Add New Job Category', 'job-killer'),
            'new_item_name' => __('New Job Category Name', 'job-killer'),
            'menu_name' => __('Categories', 'job-killer')
        );
        
        $category_args = array(
            'hierarchical' => true,
            'labels' => $category_labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'job-category'),
            'show_in_rest' => true
        );
        
        $category_args = apply_filters('job_killer_taxonomy_args', $category_args, 'job_listing_category');
        if ($category_args !== false) {
            register_taxonomy('job_listing_category', 'job_listing', $category_args);
        }
        
        // Job types
        $type_labels = array(
            'name' => __('Job Types', 'job-killer'),
            'singular_name' => __('Job Type', 'job-killer'),
            'search_items' => __('Search Job Types', 'job-killer'),
            'all_items' => __('All Job Types', 'job-killer'),
            'edit_item' => __('Edit Job Type', 'job-killer'),
            'update_item' => __('Update Job Type', 'job-killer'),
            'add_new_item' => __('Add New Job Type', 'job-killer'),
            'new_item_name' => __('New Job Type Name', 'job-killer'),
            'menu_name' => __('Types', 'job-killer')
        );
        
        $type_args = array(
            'hierarchical' => false,
            'labels' => $type_labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'job-type'),
            'show_in_rest' => true
        );
        
        $type_args = apply_filters('job_killer_taxonomy_args', $type_args, 'job_listing_type');
        if ($type_args !== false) {
            register_taxonomy('job_listing_type', 'job_listing', $type_args);
        }
        
        // Job regions
        $region_labels = array(
            'name' => __('Job Regions', 'job-killer'),
            'singular_name' => __('Job Region', 'job-killer'),
            'search_items' => __('Search Job Regions', 'job-killer'),
            'all_items' => __('All Job Regions', 'job-killer'),
            'edit_item' => __('Edit Job Region', 'job-killer'),
            'update_item' => __('Update Job Region', 'job-killer'),
            'add_new_item' => __('Add New Job Region', 'job-killer'),
            'new_item_name' => __('New Job Region Name', 'job-killer'),
            'menu_name' => __('Regions', 'job-killer')
        );
        
        $region_args = array(
            'hierarchical' => true,
            'labels' => $region_labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'job-region'),
            'show_in_rest' => true
        );
        
        $region_args = apply_filters('job_killer_taxonomy_args', $region_args, 'job_listing_region');
        if ($region_args !== false) {
            register_taxonomy('job_listing_region', 'job_listing', $region_args);
        }
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_style(
            'job-killer-frontend',
            JOB_KILLER_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            JOB_KILLER_VERSION
        );
        
        wp_enqueue_script(
            'job-killer-frontend',
            JOB_KILLER_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            JOB_KILLER_VERSION,
            true
        );
        
        wp_localize_script('job-killer-frontend', 'jobKiller', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('job_killer_nonce')
        ));
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals($schedules) {
        $schedules['every_30_minutes'] = array(
            'interval' => 1800,
            'display' => __('Every 30 Minutes', 'job-killer')
        );
        
        $schedules['every_2_hours'] = array(
            'interval' => 7200,
            'display' => __('Every 2 Hours', 'job-killer')
        );
        
        $schedules['every_6_hours'] = array(
            'interval' => 21600,
            'display' => __('Every 6 Hours', 'job-killer')
        );
        
        return $schedules;
    }
    
    /**
     * Run import process
     */
    public function run_import() {
        if ($this->importer && method_exists($this->importer, 'run_scheduled_import')) {
            $this->importer->run_scheduled_import();
        }
        
        // Also run auto feeds import
        if ($this->providers_manager && method_exists($this->providers_manager, 'run_auto_imports')) {
            $this->providers_manager->run_auto_imports();
        }
    }
    
    /**
     * Cleanup old logs
     */
    public function cleanup_logs() {
        if ($this->helper && method_exists($this->helper, 'cleanup_old_logs')) {
            $this->helper->cleanup_old_logs();
        }
    }
    
    /**
     * Add structured data for job listings
     */
    public function add_structured_data() {
        if (!is_singular('job_listing')) {
            return;
        }
        
        $settings = get_option('job_killer_settings', array());
        if (empty($settings['structured_data'])) {
            return;
        }
        
        global $post;
        
        $job_data = array(
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => get_the_title($post->ID),
            'description' => wp_strip_all_tags(get_the_content()),
            'datePosted' => get_the_date('c', $post->ID),
            'validThrough' => $this->get_job_expiry_date($post->ID),
            'employmentType' => $this->get_employment_type($post->ID),
            'hiringOrganization' => array(
                '@type' => 'Organization',
                'name' => get_post_meta($post->ID, '_company_name', true)
            ),
            'jobLocation' => array(
                '@type' => 'Place',
                'address' => array(
                    '@type' => 'PostalAddress',
                    'addressLocality' => get_post_meta($post->ID, '_job_location', true)
                )
            )
        );
        
        // Add salary if available
        $salary = get_post_meta($post->ID, '_job_salary', true);
        if (!empty($salary)) {
            $job_data['baseSalary'] = array(
                '@type' => 'MonetaryAmount',
                'currency' => 'BRL',
                'value' => array(
                    '@type' => 'QuantitativeValue',
                    'value' => $salary
                )
            );
        }
        
        // Add remote work indicator
        $remote = get_post_meta($post->ID, '_remote_position', true);
        if ($remote) {
            $job_data['jobLocationType'] = 'TELECOMMUTE';
        }
        
        echo '<script type="application/ld+json">' . wp_json_encode($job_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
    
    /**
     * Get job expiry date
     */
    private function get_job_expiry_date($post_id) {
        $expires = get_post_meta($post_id, '_job_expires', true);
        if (!empty($expires)) {
            return date('c', strtotime($expires));
        }
        
        // Default to 30 days from post date
        $post_date = get_the_date('U', $post_id);
        return date('c', $post_date + (30 * 24 * 60 * 60));
    }
    
    /**
     * Get employment type
     */
    private function get_employment_type($post_id) {
        $types = wp_get_post_terms($post_id, 'job_listing_type', array('fields' => 'names'));
        
        if (!empty($types)) {
            $type = strtolower($types[0]);
            
            $type_mapping = array(
                'tempo integral' => 'FULL_TIME',
                'full time' => 'FULL_TIME',
                'meio período' => 'PART_TIME',
                'part time' => 'PART_TIME',
                'freelance' => 'CONTRACTOR',
                'contrato' => 'CONTRACTOR',
                'temporário' => 'TEMPORARY',
                'temporary' => 'TEMPORARY',
                'estágio' => 'INTERN',
                'internship' => 'INTERN'
            );
            
            return isset($type_mapping[$type]) ? $type_mapping[$type] : 'FULL_TIME';
        }
        
        return 'FULL_TIME';
    }
}