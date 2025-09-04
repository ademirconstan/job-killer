<?php
/**
 * Job Killer Admin Class
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle admin functionality
 */
class Job_Killer_Admin {
    
    /**
     * Settings instance
     */
    public $settings;
    
    /**
     * AJAX instance
     */
    public $ajax;
    
    /**
     * Auto feeds instance
     */
    public $auto_feeds;
    
    /**
     * Setup instance
     */
    public $setup;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        
        // Load components after admin_init to ensure all dependencies are available
        add_action('admin_init', array($this, 'load_components'), 5);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'admin_init'));
        add_filter('plugin_action_links_' . JOB_KILLER_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }
    
    /**
     * Load admin components
     */
    public function load_components() {
        // Only load if classes exist
        if (class_exists('Job_Killer_Admin_Settings')) {
            $this->settings = new Job_Killer_Admin_Settings();
        }
        
        if (class_exists('Job_Killer_Admin_Ajax')) {
            $this->ajax = new Job_Killer_Admin_Ajax();
        }
        
        if (class_exists('Job_Killer_Admin_Auto_Feeds')) {
            $this->auto_feeds = new Job_Killer_Admin_Auto_Feeds();
        }
        
        if (class_exists('Job_Killer_Admin_Setup')) {
            $this->setup = new Job_Killer_Admin_Setup();
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Job Killer', 'job-killer'),
            __('Job Killer', 'job-killer'),
            'manage_options',
            'job-killer',
            array($this, 'dashboard_page'),
            'dashicons-businessman',
            25
        );
        
        // Dashboard
        add_submenu_page(
            'job-killer',
            __('Dashboard', 'job-killer'),
            __('Dashboard', 'job-killer'),
            'manage_options',
            'job-killer',
            array($this, 'dashboard_page')
        );
        
        // Settings
        add_submenu_page(
            'job-killer',
            __('Settings', 'job-killer'),
            __('Settings', 'job-killer'),
            'manage_options',
            'job-killer-settings',
            array($this, 'settings_page')
        );
        
        // RSS Feeds
        add_submenu_page(
            'job-killer',
            __('RSS Feeds', 'job-killer'),
            __('RSS Feeds', 'job-killer'),
            'manage_options',
            'job-killer-feeds',
            array($this, 'feeds_page')
        );
        
        // Auto Feeds
        add_submenu_page(
            'job-killer',
            __('Feeds Automáticos', 'job-killer'),
            __('Feeds Automáticos', 'job-killer'),
            'manage_options',
            'job-killer-auto-feeds',
            array($this, 'auto_feeds_page')
        );
        
        // API Testing
        add_submenu_page(
            'job-killer',
            __('API Testing', 'job-killer'),
            __('API Testing', 'job-killer'),
            'manage_options',
            'job-killer-api-test',
            array($this, 'api_test_page')
        );
        
        // Scheduling
        add_submenu_page(
            'job-killer',
            __('Scheduling', 'job-killer'),
            __('Scheduling', 'job-killer'),
            'manage_options',
            'job-killer-scheduling',
            array($this, 'scheduling_page')
        );
        
        // Logs
        add_submenu_page(
            'job-killer',
            __('Logs', 'job-killer'),
            __('Logs', 'job-killer'),
            'manage_options',
            'job-killer-logs',
            array($this, 'logs_page')
        );
        
        // Setup wizard (only if not completed)
        if (!get_option('job_killer_setup_completed')) {
            add_submenu_page(
                null, // Hidden from menu
                __('Setup Wizard', 'job-killer'),
                __('Setup Wizard', 'job-killer'),
                'manage_options',
                'job-killer-setup',
                array($this, 'setup_page')
            );
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'job-killer') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'job-killer-admin-legacy',
            JOB_KILLER_PLUGIN_URL . 'includes/admin/assets/css/styles.css',
            array(),
            JOB_KILLER_VERSION
        );
        
        // New clean admin CSS
        wp_enqueue_style(
            'job-killer-admin',
            JOB_KILLER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            JOB_KILLER_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'job-killer-admin',
            JOB_KILLER_PLUGIN_URL . 'includes/admin/assets/js/scripts.js',
            array('jquery', 'wp-util'),
            JOB_KILLER_VERSION,
            true
        );
        
        // Chart.js for dashboard
        if ($hook === 'toplevel_page_job-killer') {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                array(),
                '3.9.1',
                true
            );
        }
        
        // Select2 for dropdowns
        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            array('jquery'),
            '4.1.0',
            true
        );
        
        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            array(),
            '4.1.0'
        );
        
        // Localize script
        wp_localize_script('job-killer-admin', 'jobKillerAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('job_killer_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'job-killer'),
                'testing_feed' => __('Testing feed...', 'job-killer'),
                'test_successful' => __('Test successful!', 'job-killer'),
                'test_failed' => __('Test failed!', 'job-killer'),
                'importing' => __('Importing...', 'job-killer'),
                'import_complete' => __('Import complete!', 'job-killer'),
                'error_occurred' => __('An error occurred. Please try again.', 'job-killer')
            )
        ));
    }
    
    /**
     * Admin init
     */
    public function admin_init() {
        // Check if plugin was just activated
        if (get_option('job_killer_activated')) {
            delete_option('job_killer_activated');
            
            // Only redirect if not doing AJAX and user has permissions
            if (!wp_doing_ajax() && current_user_can('manage_options')) {
                wp_redirect(admin_url('admin.php?page=job-killer&welcome=1'));
                exit;
            }
        }
    }
    
    /**
     * Add action links
     */
    public function add_action_links($links) {
        $action_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=job-killer-settings') . '">' . __('Settings', 'job-killer') . '</a>',
            'feeds' => '<a href="' . admin_url('admin.php?page=job-killer-feeds') . '">' . __('RSS Feeds', 'job-killer') . '</a>'
        );
        
        return array_merge($action_links, $links);
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        // Initialize helper if not available
        if (!$this->helper && class_exists('Job_Killer_Helper')) {
            $this->helper = new Job_Killer_Helper();
        }
        
        $stats = array();
        $chart_data = array();
        $recent_logs = array();
        
        if ($this->helper) {
            $stats = $this->helper->get_import_stats();
            $chart_data = $this->helper->get_chart_data(30);
            $recent_logs = $this->helper->get_logs(array('limit' => 10));
        }
        
        // Get next scheduled import
        $next_import = wp_next_scheduled('job_killer_import_jobs');
        
        // Get feeds status
        $feeds = get_option('job_killer_feeds', array());
        $active_feeds = array_filter($feeds, function($feed) {
            return !empty($feed['active']);
        });
        
        include JOB_KILLER_PLUGIN_DIR . 'includes/templates/admin/dashboard.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if ($this->settings && method_exists($this->settings, 'render_page')) {
            $this->settings->render_page();
        } else {
            echo '<div class="notice notice-error"><p>' . __('Settings component not available.', 'job-killer') . '</p></div>';
        }
    }
    
    /**
     * Feeds page
     */
    public function feeds_page() {
        $feeds = get_option('job_killer_feeds', array());
        
        $rss_providers = null;
        $providers = array();
        
        if (class_exists('Job_Killer_Rss_Providers')) {
            $rss_providers = new Job_Killer_Rss_Providers();
            $providers = $rss_providers->get_providers();
        }
        
        include JOB_KILLER_PLUGIN_DIR . 'includes/templates/admin/feeds.php';
    }
    
    /**
     * Auto feeds page
     */
    public function auto_feeds_page() {
        if ($this->auto_feeds && method_exists($this->auto_feeds, 'render_page')) {
            $this->auto_feeds->render_page();
        } else {
            echo '<div class="notice notice-error"><p>' . __('Auto feeds component not available.', 'job-killer') . '</p></div>';
        }
    }
    
    /**
     * API test page
     */
    public function api_test_page() {
        include JOB_KILLER_PLUGIN_DIR . 'includes/templates/admin/api-test.php';
    }
    
    /**
     * Setup page
     */
    public function setup_page() {
        if ($this->setup && method_exists($this->setup, 'render_setup_wizard')) {
            $this->setup->render_setup_wizard();
        } else {
            echo '<div class="notice notice-error"><p>' . __('Setup component not available.', 'job-killer') . '</p></div>';
        }
    }
    
    /**
     * Scheduling page
     */
    public function scheduling_page() {
        $settings = get_option('job_killer_settings', array());
        $next_import = wp_next_scheduled('job_killer_import_jobs');
        $next_cleanup = wp_next_scheduled('job_killer_cleanup_logs');
        
        // Get cron history
        $cron_logs = array();
        
        if (!$this->helper && class_exists('Job_Killer_Helper')) {
            $this->helper = new Job_Killer_Helper();
        }
        
        if ($this->helper) {
            $cron_logs = $this->helper->get_logs(array(
                'source' => 'cron',
                'limit' => 20
            ));
        }
        
        include JOB_KILLER_PLUGIN_DIR . 'includes/templates/admin/scheduling.php';
    }
    
    /**
     * Logs page
     */
    public function logs_page() {
        if (!$this->helper && class_exists('Job_Killer_Helper')) {
            $this->helper = new Job_Killer_Helper();
        }
        
        if (!$this->helper) {
            echo '<div class="notice notice-error"><p>' . __('Helper component not available.', 'job-killer') . '</p></div>';
            return;
        }
        
        // Handle filters
        $filters = array(
            'type' => sanitize_text_field($_GET['type'] ?? ''),
            'source' => sanitize_text_field($_GET['source'] ?? ''),
            'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_GET['date_to'] ?? ''),
            'limit' => 50,
            'offset' => (max(1, intval($_GET['paged'] ?? 1)) - 1) * 50
        );
        
        $logs = $this->helper->get_logs($filters);
        $total_logs = $this->helper->get_log_count($filters);
        $total_pages = ceil($total_logs / 50);
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        
        // Get log types and sources for filters
        global $wpdb;
        $log_table = $wpdb->prefix . 'job_killer_logs';
        
        $log_types = $wpdb->get_col("SELECT DISTINCT type FROM $log_table ORDER BY type");
        $log_sources = $wpdb->get_col("SELECT DISTINCT source FROM $log_table ORDER BY source");
        
        include JOB_KILLER_PLUGIN_DIR . 'includes/templates/admin/logs.php';
    }
}