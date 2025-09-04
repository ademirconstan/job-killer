<?php
/**
 * Job Killer Cron Class
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle cron jobs and scheduled tasks
 */
class Job_Killer_Cron {
    
    /**
     * Helper instance
     */
    private $helper;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->helper = new Job_Killer_Helper();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register cron hooks
        add_action('job_killer_import_jobs', array($this, 'run_import'));
        add_action('job_killer_cleanup_logs', array($this, 'cleanup_logs'));
        add_action('job_killer_cleanup_expired_jobs', array($this, 'cleanup_expired_jobs'));
        add_action('job_killer_send_daily_report', array($this, 'send_daily_report'));
        
        // Schedule additional cron jobs
        add_action('init', array($this, 'schedule_additional_jobs'));
        
        // Add custom cron intervals
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        
        // Handle cron execution monitoring
        add_action('job_killer_import_jobs', array($this, 'log_cron_start'), 5);
        add_action('job_killer_import_jobs', array($this, 'log_cron_end'), 95);
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
        
        $schedules['every_12_hours'] = array(
            'interval' => 43200,
            'display' => __('Every 12 Hours', 'job-killer')
        );
        
        return $schedules;
    }
    
    /**
     * Schedule additional cron jobs
     */
    public function schedule_additional_jobs() {
        // Daily log cleanup
        if (!wp_next_scheduled('job_killer_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'job_killer_cleanup_logs');
        }
        
        // Weekly expired jobs cleanup
        if (!wp_next_scheduled('job_killer_cleanup_expired_jobs')) {
            wp_schedule_event(time(), 'weekly', 'job_killer_cleanup_expired_jobs');
        }
        
        // Daily report (if enabled)
        $settings = get_option('job_killer_settings', array());
        if (!empty($settings['daily_reports']) && !wp_next_scheduled('job_killer_send_daily_report')) {
            wp_schedule_event(strtotime('tomorrow 8:00 AM'), 'daily', 'job_killer_send_daily_report');
        }
    }
    
    /**
     * Run import process
     */
    public function run_import() {
        $this->helper->log('info', 'cron', 'Starting scheduled import process');
        
        try {
            $importer = new Job_Killer_Importer();
            $importer->run_scheduled_import();
            
            $this->helper->log('success', 'cron', 'Scheduled import completed successfully');
            
        } catch (Exception $e) {
            $this->helper->log('error', 'cron', 
                'Scheduled import failed: ' . $e->getMessage(),
                array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString())
            );
            
            // Send error notification
            $this->send_error_notification($e);
        }
    }
    
    /**
     * Cleanup old logs
     */
    public function cleanup_logs() {
        $this->helper->log('info', 'cron', 'Starting log cleanup process');
        
        try {
            $this->helper->cleanup_old_logs();
            $this->helper->log('success', 'cron', 'Log cleanup completed successfully');
            
        } catch (Exception $e) {
            $this->helper->log('error', 'cron', 
                'Log cleanup failed: ' . $e->getMessage(),
                array('error' => $e->getMessage())
            );
        }
    }
    
    /**
     * Cleanup expired jobs
     */
    public function cleanup_expired_jobs() {
        $this->helper->log('info', 'cron', 'Starting expired jobs cleanup');
        
        try {
            global $wpdb;
            
            // Mark expired jobs as filled
            $expired_jobs = $wpdb->get_results("
                SELECT p.ID 
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'job_listing'
                AND p.post_status = 'publish'
                AND pm.meta_key = '_job_expires'
                AND pm.meta_value < CURDATE()
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm2 
                    WHERE pm2.post_id = p.ID 
                    AND pm2.meta_key = '_filled' 
                    AND pm2.meta_value = '1'
                )
            ");
            
            $expired_count = 0;
            foreach ($expired_jobs as $job) {
                update_post_meta($job->ID, '_filled', 1);
                $expired_count++;
            }
            
            $this->helper->log('success', 'cron', 
                sprintf('Marked %d expired jobs as filled', $expired_count),
                array('expired_count' => $expired_count)
            );
            
        } catch (Exception $e) {
            $this->helper->log('error', 'cron', 
                'Expired jobs cleanup failed: ' . $e->getMessage(),
                array('error' => $e->getMessage())
            );
        }
    }
    
    /**
     * Send daily report
     */
    public function send_daily_report() {
        $this->helper->log('info', 'cron', 'Generating daily report');
        
        try {
            $stats = $this->helper->get_import_stats();
            $settings = get_option('job_killer_settings', array());
            $email = $settings['notification_email'] ?? get_option('admin_email');
            
            if (empty($email)) {
                return;
            }
            
            $subject = sprintf(__('[%s] Job Killer Daily Report', 'job-killer'), get_bloginfo('name'));
            
            $message = $this->generate_daily_report_content($stats);
            
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            $sent = wp_mail($email, $subject, $message, $headers);
            
            if ($sent) {
                $this->helper->log('success', 'cron', 'Daily report sent successfully');
            } else {
                $this->helper->log('error', 'cron', 'Failed to send daily report');
            }
            
        } catch (Exception $e) {
            $this->helper->log('error', 'cron', 
                'Daily report generation failed: ' . $e->getMessage(),
                array('error' => $e->getMessage())
            );
        }
    }
    
    /**
     * Generate daily report content
     */
    private function generate_daily_report_content($stats) {
        ob_start();
        ?>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #2271b1; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
                .stat-card { background: #f9f9f9; padding: 15px; border-radius: 5px; text-align: center; }
                .stat-number { font-size: 24px; font-weight: bold; color: #2271b1; }
                .stat-label { font-size: 14px; color: #666; }
                .footer { background: #f9f9f9; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?php echo sprintf(__('Job Killer Daily Report - %s', 'job-killer'), get_bloginfo('name')); ?></h1>
                <p><?php echo date_i18n(get_option('date_format')); ?></p>
            </div>
            
            <div class="content">
                <h2><?php _e('Import Statistics', 'job-killer'); ?></h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['active_jobs']); ?></div>
                        <div class="stat-label"><?php _e('Active Jobs', 'job-killer'); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['today_imports']); ?></div>
                        <div class="stat-label"><?php _e('Jobs Imported Today', 'job-killer'); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['week_imports']); ?></div>
                        <div class="stat-label"><?php _e('Jobs This Week', 'job-killer'); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['month_imports']); ?></div>
                        <div class="stat-label"><?php _e('Jobs This Month', 'job-killer'); ?></div>
                    </div>
                </div>
                
                <h3><?php _e('Feed Performance', 'job-killer'); ?></h3>
                <?php if (!empty($stats['feed_stats'])): ?>
                <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                    <thead>
                        <tr style="background: #f9f9f9;">
                            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;"><?php _e('Feed', 'job-killer'); ?></th>
                            <th style="padding: 10px; text-align: right; border: 1px solid #ddd;"><?php _e('Jobs Imported', 'job-killer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['feed_stats'] as $feed_stat): ?>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($feed_stat->feed_id); ?></td>
                            <td style="padding: 10px; text-align: right; border: 1px solid #ddd;"><?php echo number_format($feed_stat->count); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><?php _e('No feed statistics available.', 'job-killer'); ?></p>
                <?php endif; ?>
                
                <h3><?php _e('Recent Activity', 'job-killer'); ?></h3>
                <?php
                $recent_logs = $this->helper->get_logs(array(
                    'limit' => 10,
                    'date_from' => date('Y-m-d', strtotime('-1 day'))
                ));
                
                if ($recent_logs):
                ?>
                <ul>
                    <?php foreach ($recent_logs as $log): ?>
                    <li>
                        <strong><?php echo esc_html(ucfirst($log->type)); ?>:</strong>
                        <?php echo esc_html($log->message); ?>
                        <small>(<?php echo $this->helper->time_ago($log->created_at); ?>)</small>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p><?php _e('No recent activity.', 'job-killer'); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="footer">
                <p><?php _e('This is an automated report from Job Killer plugin.', 'job-killer'); ?></p>
                <p><a href="<?php echo admin_url('admin.php?page=job-killer'); ?>"><?php _e('View Dashboard', 'job-killer'); ?></a></p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Send error notification
     */
    private function send_error_notification($exception) {
        $settings = get_option('job_killer_settings', array());
        
        if (empty($settings['email_notifications'])) {
            return;
        }
        
        $email = $settings['notification_email'] ?? get_option('admin_email');
        
        if (empty($email)) {
            return;
        }
        
        $subject = sprintf(__('[%s] Job Killer Import Error', 'job-killer'), get_bloginfo('name'));
        
        $message = sprintf(
            __("An error occurred during the scheduled job import:\n\nError: %s\n\nTime: %s\n\nPlease check the Job Killer logs for more details.\n\nDashboard: %s", 'job-killer'),
            $exception->getMessage(),
            current_time('Y-m-d H:i:s'),
            admin_url('admin.php?page=job-killer-logs')
        );
        
        wp_mail($email, $subject, $message);
    }
    
    /**
     * Log cron start
     */
    public function log_cron_start() {
        update_option('job_killer_cron_running', time());
    }
    
    /**
     * Log cron end
     */
    public function log_cron_end() {
        delete_option('job_killer_cron_running');
        update_option('job_killer_last_cron', time());
    }
    
    /**
     * Check if cron is running
     */
    public function is_cron_running() {
        $start_time = get_option('job_killer_cron_running');
        
        if (!$start_time) {
            return false;
        }
        
        // Consider cron stuck if running for more than 10 minutes
        return (time() - $start_time) < 600;
    }
    
    /**
     * Get cron status
     */
    public function get_cron_status() {
        $next_import = wp_next_scheduled('job_killer_import_jobs');
        $last_run = get_option('job_killer_last_cron');
        $is_running = $this->is_cron_running();
        
        return array(
            'next_import' => $next_import,
            'last_run' => $last_run,
            'is_running' => $is_running,
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'scheduled_events' => $this->get_scheduled_events()
        );
    }
    
    /**
     * Get scheduled events
     */
    private function get_scheduled_events() {
        $cron_events = _get_cron_array();
        $job_killer_events = array();
        
        foreach ($cron_events as $timestamp => $events) {
            foreach ($events as $hook => $event_data) {
                if (strpos($hook, 'job_killer_') === 0) {
                    $job_killer_events[] = array(
                        'hook' => $hook,
                        'timestamp' => $timestamp,
                        'next_run' => $timestamp,
                        'args' => $event_data
                    );
                }
            }
        }
        
        return $job_killer_events;
    }
    
    /**
     * Reschedule all cron jobs
     */
    public function reschedule_all() {
        // Clear existing schedules
        wp_clear_scheduled_hook('job_killer_import_jobs');
        wp_clear_scheduled_hook('job_killer_cleanup_logs');
        wp_clear_scheduled_hook('job_killer_cleanup_expired_jobs');
        wp_clear_scheduled_hook('job_killer_send_daily_report');
        
        // Get current settings
        $settings = get_option('job_killer_settings', array());
        $interval = $settings['cron_interval'] ?? 'twicedaily';
        
        // Reschedule main import
        wp_schedule_event(time(), $interval, 'job_killer_import_jobs');
        
        // Reschedule additional jobs
        $this->schedule_additional_jobs();
        
        $this->helper->log('info', 'cron', 'All cron jobs rescheduled successfully');
        
        return true;
    }
}