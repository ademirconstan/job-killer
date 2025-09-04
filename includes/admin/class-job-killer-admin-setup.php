<?php
/**
 * Job Killer Admin Setup Class
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle plugin setup and onboarding
 */
class Job_Killer_Admin_Setup {
    
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
        add_action('admin_init', array($this, 'check_setup_redirect'));
        add_action('wp_ajax_job_killer_complete_setup', array($this, 'complete_setup'));
        add_action('wp_ajax_job_killer_skip_setup', array($this, 'skip_setup'));
    }
    
    /**
     * Check if we should redirect to setup
     */
    public function check_setup_redirect() {
        // Only redirect on plugin activation
        if (!get_transient('job_killer_setup_redirect')) {
            return;
        }
        
        delete_transient('job_killer_setup_redirect');
        
        // Don't redirect if doing AJAX or if not admin
        if (wp_doing_ajax() || !current_user_can('manage_options')) {
            return;
        }
        
        // Don't redirect if setup already completed
        if (get_option('job_killer_setup_completed')) {
            return;
        }
        
        wp_redirect(admin_url('admin.php?page=job-killer-setup'));
        exit;
    }
    
    /**
     * Render setup wizard
     */
    public function render_setup_wizard() {
        $step = intval($_GET['step'] ?? 1);
        $step = max(1, min(4, $step)); // Limit steps 1-4
        
        include JOB_KILLER_PLUGIN_DIR . 'includes/templates/admin/setup-wizard.php';
    }
    
    /**
     * Get setup steps
     */
    public function get_setup_steps() {
        return array(
            1 => array(
                'title' => __('Welcome', 'job-killer'),
                'description' => __('Welcome to Job Killer! Let\'s get you set up.', 'job-killer')
            ),
            2 => array(
                'title' => __('Basic Settings', 'job-killer'),
                'description' => __('Configure basic import settings.', 'job-killer')
            ),
            3 => array(
                'title' => __('Add RSS Feed', 'job-killer'),
                'description' => __('Add your first RSS feed source.', 'job-killer')
            ),
            4 => array(
                'title' => __('Complete', 'job-killer'),
                'description' => __('Setup complete! You\'re ready to start importing jobs.', 'job-killer')
            )
        );
    }
    
    /**
     * Complete setup
     */
    public function complete_setup() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $setup_data = $_POST['setup_data'] ?? array();
        
        // Save basic settings
        if (!empty($setup_data['settings'])) {
            $current_settings = get_option('job_killer_settings', array());
            $new_settings = array_merge($current_settings, $setup_data['settings']);
            update_option('job_killer_settings', $new_settings);
        }
        
        // Save first feed
        if (!empty($setup_data['feed'])) {
            $helper = new Job_Killer_Helper();
            $feed_config = $helper->sanitize_feed_config($setup_data['feed']);
            $feed_config['id'] = 'setup_feed_' . time();
            
            $feeds = get_option('job_killer_feeds', array());
            $feeds[$feed_config['id']] = $feed_config;
            update_option('job_killer_feeds', $feeds);
        }
        
        // Mark setup as completed
        update_option('job_killer_setup_completed', true);
        
        // Log setup completion
        $helper = new Job_Killer_Helper();
        $helper->log('info', 'setup', 'Plugin setup completed successfully');
        
        wp_send_json_success(array(
            'message' => __('Setup completed successfully!', 'job-killer'),
            'redirect_url' => admin_url('admin.php?page=job-killer')
        ));
    }
    
    /**
     * Skip setup
     */
    public function skip_setup() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        // Mark setup as completed (skipped)
        update_option('job_killer_setup_completed', true);
        
        wp_send_json_success(array(
            'message' => __('Setup skipped', 'job-killer'),
            'redirect_url' => admin_url('admin.php?page=job-killer')
        ));
    }
    
    /**
     * Get recommended feeds
     */
    public function get_recommended_feeds() {
        return array(
            array(
                'name' => 'Indeed Brasil',
                'url' => 'https://br.indeed.com/rss?q=&l=',
                'description' => __('Popular job board with thousands of listings', 'job-killer'),
                'provider' => 'indeed'
            ),
            array(
                'name' => 'Catho',
                'url' => 'https://www.catho.com.br/rss/vagas',
                'description' => __('Leading Brazilian job portal', 'job-killer'),
                'provider' => 'catho'
            ),
            array(
                'name' => 'InfoJobs',
                'url' => 'https://www.infojobs.com.br/rss/ofertas',
                'description' => __('Professional job opportunities', 'job-killer'),
                'provider' => 'infojobs'
            ),
            array(
                'name' => 'Vagas.com',
                'url' => 'https://www.vagas.com.br/rss',
                'description' => __('Comprehensive job listings', 'job-killer'),
                'provider' => 'vagas'
            )
        );
    }
    
    /**
     * Get setup progress
     */
    public function get_setup_progress() {
        $progress = array(
            'completed' => get_option('job_killer_setup_completed', false),
            'has_settings' => !empty(get_option('job_killer_settings')),
            'has_feeds' => !empty(get_option('job_killer_feeds')),
            'has_imports' => $this->has_imported_jobs()
        );
        
        $progress['percentage'] = 0;
        if ($progress['has_settings']) $progress['percentage'] += 25;
        if ($progress['has_feeds']) $progress['percentage'] += 25;
        if ($progress['has_imports']) $progress['percentage'] += 25;
        if ($progress['completed']) $progress['percentage'] += 25;
        
        return $progress;
    }
    
    /**
     * Check if any jobs have been imported
     */
    private function has_imported_jobs() {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'job_listing' 
            AND post_status = 'publish'
        ");
        
        return $count > 0;
    }
    
    /**
     * Get setup checklist
     */
    public function get_setup_checklist() {
        $checklist = array(
            'settings' => array(
                'title' => __('Configure Settings', 'job-killer'),
                'description' => __('Set up basic import and performance settings', 'job-killer'),
                'completed' => !empty(get_option('job_killer_settings')),
                'url' => admin_url('admin.php?page=job-killer-settings')
            ),
            'feeds' => array(
                'title' => __('Add RSS Feeds', 'job-killer'),
                'description' => __('Add at least one RSS feed source', 'job-killer'),
                'completed' => !empty(get_option('job_killer_feeds')),
                'url' => admin_url('admin.php?page=job-killer-feeds')
            ),
            'test_import' => array(
                'title' => __('Test Import', 'job-killer'),
                'description' => __('Run a test import to verify everything works', 'job-killer'),
                'completed' => $this->has_imported_jobs(),
                'url' => admin_url('admin.php?page=job-killer-scheduling')
            ),
            'schedule' => array(
                'title' => __('Configure Scheduling', 'job-killer'),
                'description' => __('Set up automatic import scheduling', 'job-killer'),
                'completed' => wp_next_scheduled('job_killer_import_jobs') !== false,
                'url' => admin_url('admin.php?page=job-killer-scheduling')
            )
        );
        
        return $checklist;
    }
    
    /**
     * Reset setup
     */
    public function reset_setup() {
        delete_option('job_killer_setup_completed');
        set_transient('job_killer_setup_redirect', true, 30);
        
        return true;
    }
    
    /**
     * Get setup tips
     */
    public function get_setup_tips() {
        return array(
            array(
                'title' => __('Start Small', 'job-killer'),
                'description' => __('Begin with one or two RSS feeds to test the system before adding more.', 'job-killer'),
                'icon' => 'lightbulb'
            ),
            array(
                'title' => __('Monitor Performance', 'job-killer'),
                'description' => __('Keep an eye on import logs and adjust settings if needed.', 'job-killer'),
                'icon' => 'chart-bar'
            ),
            array(
                'title' => __('Regular Maintenance', 'job-killer'),
                'description' => __('Review and clean up old job listings periodically.', 'job-killer'),
                'icon' => 'admin-tools'
            ),
            array(
                'title' => __('Backup Settings', 'job-killer'),
                'description' => __('Export your settings and feed configurations as backup.', 'job-killer'),
                'icon' => 'backup'
            )
        );
    }
}