<?php
/**
 * Job Killer Helper Class
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper functions and utilities
 */
class JK_Helper {
    
    /**
     * Format time ago
     */
    public function time_ago($datetime) {
        if (empty($datetime)) {
            return __('Never', 'job-killer');
        }
        
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        if (!$timestamp) {
            return __('Invalid date', 'job-killer');
        }
        
        return human_time_diff($timestamp, current_time('timestamp')) . ' ' . __('ago', 'job-killer');
    }
    
    /**
     * Get import statistics
     */
    public function get_import_stats() {
        global $wpdb;
        
        // Use cache if available
        $cache_key = 'jk_import_stats';
        $stats = wp_cache_get($cache_key);
        
        if ($stats === false) {
            // Total jobs imported
            $total_imports = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'job_listing'
                AND p.post_status IN ('publish', 'draft')
                AND pm.meta_key = '_job_killer_imported'
            ");
            
            // Jobs imported today
            $today_imports = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'job_listing'
                AND p.post_status IN ('publish', 'draft')
                AND pm.meta_key = '_job_killer_imported'
                AND DATE(p.post_date) = CURDATE()
            ");
            
            // Jobs imported this week
            $week_imports = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'job_listing'
                AND p.post_status IN ('publish', 'draft')
                AND pm.meta_key = '_job_killer_imported'
                AND YEARWEEK(p.post_date, 1) = YEARWEEK(CURDATE(), 1)
            ");
            
            // Jobs imported this month
            $month_imports = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'job_listing'
                AND p.post_status IN ('publish', 'draft')
                AND pm.meta_key = '_job_killer_imported'
                AND YEAR(p.post_date) = YEAR(CURDATE())
                AND MONTH(p.post_date) = MONTH(CURDATE())
            ");
            
            // Active job listings
            $active_jobs = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->posts} p 
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_filled'
                WHERE p.post_type = 'job_listing' 
                AND p.post_status = 'publish'
                AND (pm.meta_value IS NULL OR pm.meta_value = '0')
            ");
            
            $stats = array(
                'total_imports' => (int) $total_imports,
                'today_imports' => (int) $today_imports,
                'week_imports' => (int) $week_imports,
                'month_imports' => (int) $month_imports,
                'active_jobs' => (int) $active_jobs,
                'feed_stats' => array()
            );
            
            // Cache for 5 minutes
            wp_cache_set($cache_key, $stats, '', 300);
        }
        
        return $stats;
    }
    
    /**
     * Get chart data for dashboard
     */
    public function get_chart_data($days = 30) {
        global $wpdb;
        
        $cache_key = 'jk_chart_data_' . $days;
        $chart_data = wp_cache_get($cache_key);
        
        if ($chart_data === false) {
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT DATE(p.post_date) as date, COUNT(*) as count
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'job_listing'
                AND p.post_status IN ('publish', 'draft')
                AND pm.meta_key = '_job_killer_imported'
                AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
                GROUP BY DATE(p.post_date)
                ORDER BY date ASC
            ", $days));
            
            $chart_data = array();
            $start_date = date('Y-m-d', strtotime("-{$days} days"));
            
            // Fill in missing dates with zero counts
            for ($i = 0; $i < $days; $i++) {
                $date = date('Y-m-d', strtotime($start_date . " +{$i} days"));
                $count = 0;
                
                foreach ($results as $result) {
                    if ($result->date === $date) {
                        $count = (int) $result->count;
                        break;
                    }
                }
                
                $chart_data[] = array(
                    'date' => $date,
                    'count' => $count
                );
            }
            
            // Cache for 30 minutes
            wp_cache_set($cache_key, $chart_data, '', 1800);
        }
        
        return $chart_data;
    }
    
    /**
     * Get logs with filters
     */
    public function get_logs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'type' => '',
            'source' => '',
            'limit' => 100,
            'offset' => 0,
            'date_from' => '',
            'date_to' => '',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $wpdb->prefix . 'job_killer_logs';
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($args['type'])) {
            $where_conditions[] = 'type = %s';
            $where_values[] = $args['type'];
        }
        
        if (!empty($args['source'])) {
            $where_conditions[] = 'source = %s';
            $where_values[] = $args['source'];
        }
        
        if (!empty($args['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $order = in_array(strtoupper($args['order']), array('ASC', 'DESC')) ? $args['order'] : 'DESC';
        
        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY created_at $order LIMIT %d OFFSET %d";
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get log count
     */
    public function get_log_count($args = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'job_killer_logs';
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($args['type'])) {
            $where_conditions[] = 'type = %s';
            $where_values[] = $args['type'];
        }
        
        if (!empty($args['source'])) {
            $where_conditions[] = 'source = %s';
            $where_values[] = $args['source'];
        }
        
        if (!empty($args['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $query = "SELECT COUNT(*) FROM $table WHERE $where_clause";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_var($query);
    }
    
    /**
     * Cleanup old logs
     */
    public function cleanup_old_logs() {
        $settings = get_option('job_killer_settings', array());
        $retention_days = !empty($settings['log_retention_days']) ? $settings['log_retention_days'] : 30;
        
        global $wpdb;
        $table = $wpdb->prefix . 'job_killer_logs';
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < %s",
            $cutoff_date
        ));
        
        if ($deleted > 0) {
            jk_log('log_cleanup', array('deleted_count' => $deleted));
        }
        
        return $deleted;
    }
    
    /**
     * Get system information
     */
    public function get_system_info() {
        global $wpdb;
        
        return array(
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'plugin_version' => JOB_KILLER_VERSION,
            'mysql_version' => $wpdb->db_version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'curl_version' => function_exists('curl_version') ? curl_version()['version'] : 'Not available',
            'extensions' => array(
                'curl' => extension_loaded('curl'),
                'json' => extension_loaded('json'),
                'libxml' => extension_loaded('libxml'),
                'simplexml' => extension_loaded('simplexml'),
                'dom' => extension_loaded('dom')
            )
        );
    }
    
    /**
     * Export logs to CSV
     */
    public function export_logs_csv($filters = array()) {
        $logs = $this->get_logs(array_merge($filters, array('limit' => 10000)));
        
        if (empty($logs)) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $filename = 'job-killer-logs-' . date('Y-m-d-H-i-s') . '.csv';
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        $file = fopen($filepath, 'w');
        
        // CSV headers
        fputcsv($file, array('ID', 'Type', 'Source', 'Message', 'Data', 'Created At'));
        
        // CSV data
        foreach ($logs as $log) {
            fputcsv($file, array(
                $log->id,
                $log->type,
                $log->source,
                $log->message,
                $log->data,
                $log->created_at
            ));
        }
        
        fclose($file);
        
        return array(
            'url' => $upload_dir['url'] . '/' . $filename,
            'filename' => $filename
        );
    }
}

/**
 * Central logging function
 */
function jk_log($context, $data = array()) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    $log_message = sprintf(
        'JK :: %s :: %s',
        $context,
        wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
    
    error_log($log_message);
    
    // Also save to database if table exists
    global $wpdb;
    $table = $wpdb->prefix . 'job_killer_logs';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
        $wpdb->insert($table, array(
            'type' => 'info',
            'source' => 'system',
            'message' => $context,
            'data' => wp_json_encode($data),
            'created_at' => current_time('mysql')
        ));
    }
}

/**
 * Format job description
 */
function jk_format_description($raw) {
    if (empty($raw)) {
        return '';
    }
    
    // Allowed HTML tags for job descriptions
    $allowed_tags = array(
        'p' => array(),
        'br' => array(),
        'ul' => array(),
        'ol' => array(),
        'li' => array(),
        'strong' => array(),
        'b' => array(),
        'em' => array(),
        'i' => array(),
        'a' => array(
            'href' => array(),
            'title' => array(),
            'rel' => array(),
            'target' => array()
        ),
        'h3' => array(),
        'h4' => array(),
        'h5' => array(),
        'h6' => array(),
        'div' => array('class' => array()),
        'span' => array('class' => array())
    );
    
    // Clean HTML but preserve structure
    $description = wp_kses($raw, $allowed_tags);
    
    // Convert line breaks to paragraphs if not already formatted
    if (strpos($description, '<p>') === false) {
        $description = wpautop($description);
    }
    
    // Remove empty paragraphs
    $description = preg_replace('/<p>\s*<\/p>/', '', $description);
    
    // Ensure proper paragraph structure
    $description = trim($description);
    
    return $description;
}