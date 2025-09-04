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
class Job_Killer_Helper {
    
    /**
     * Log message to database
     */
    public function log($type, $source, $message, $data = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'job_killer_logs';
        
        $wpdb->insert($table, array(
            'type' => sanitize_text_field($type),
            'source' => sanitize_text_field($source),
            'message' => sanitize_textarea_field($message),
            'data' => !empty($data) ? wp_json_encode($data) : null,
            'created_at' => current_time('mysql')
        ));
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
            $this->log('info', 'cleanup', sprintf('Cleaned up %d old log entries', $deleted));
        }
    }
    
    /**
     * Get import statistics
     */
    public function get_import_stats() {
        global $wpdb;
        
        // Total jobs imported (from both RSS and auto feeds)
        $total_imports = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_job_killer_imported'
        ");
        
        // Jobs imported today
        $today_imports = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_job_killer_imported'
            AND DATE(p.post_date) = CURDATE()
        ");
        
        // Jobs imported this week
        $week_imports = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_job_killer_imported'
            AND YEARWEEK(p.post_date, 1) = YEARWEEK(CURDATE(), 1)
        ");
        
        // Jobs imported this month
        $month_imports = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
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
        
        // Provider statistics
        $provider_stats = $wpdb->get_results("
            SELECT 
                COALESCE(pm1.meta_value, 'rss') as provider,
                COUNT(*) as count 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_job_killer_imported'
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_job_killer_provider'
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            GROUP BY provider
            ORDER BY count DESC
        ");
        
        return array(
            'total_imports' => (int) $total_imports,
            'today_imports' => (int) $today_imports,
            'week_imports' => (int) $week_imports,
            'month_imports' => (int) $month_imports,
            'active_jobs' => (int) $active_jobs,
            'provider_stats' => $provider_stats
        );
    }
    
    /**
     * Get chart data for dashboard
     */
    public function get_chart_data($days = 30) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(p.post_date) as date, COUNT(*) as count
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
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
        
        return $chart_data;
    }
    
    /**
     * Format file size
     */
    public function format_file_size($bytes) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Format time ago
     */
    public function time_ago($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) {
            return __('just now', 'job-killer');
        }
        
        $condition = array(
            12 * 30 * 24 * 60 * 60 => __('year', 'job-killer'),
            30 * 24 * 60 * 60 => __('month', 'job-killer'),
            24 * 60 * 60 => __('day', 'job-killer'),
            60 * 60 => __('hour', 'job-killer'),
            60 => __('minute', 'job-killer')
        );
        
        foreach ($condition as $secs => $str) {
            $d = $time / $secs;
            
            if ($d >= 1) {
                $t = round($d);
                return sprintf(
                    _n('%d %s ago', '%d %ss ago', $t, 'job-killer'),
                    $t,
                    $str
                );
            }
        }
        
        return __('just now', 'job-killer');
    }
    
    /**
     * Sanitize feed configuration
     */
    public function sanitize_feed_config($config) {
        return array(
            'id' => sanitize_key($config['id'] ?? ''),
            'name' => sanitize_text_field($config['name'] ?? ''),
            'url' => esc_url_raw($config['url'] ?? ''),
            'active' => !empty($config['active']),
            'default_category' => sanitize_text_field($config['default_category'] ?? ''),
            'default_region' => sanitize_text_field($config['default_region'] ?? ''),
            'field_mapping' => $this->sanitize_field_mapping($config['field_mapping'] ?? array()),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
    }
    
    /**
     * Sanitize field mapping
     */
    private function sanitize_field_mapping($mapping) {
        $sanitized = array();
        
        if (!is_array($mapping)) {
            return $sanitized;
        }
        
        $allowed_fields = array(
            'title', 'description', 'company', 'location', 
            'url', 'date', 'salary', 'type', 'expires'
        );
        
        foreach ($mapping as $local_field => $rss_field) {
            if (in_array($local_field, $allowed_fields)) {
                $sanitized[sanitize_key($local_field)] = sanitize_text_field($rss_field);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate feed URL
     */
    public function validate_feed_url($url) {
        if (empty($url)) {
            return new WP_Error('empty_url', __('Feed URL is required', 'job-killer'));
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Invalid URL format', 'job-killer'));
        }
        
        // Test feed accessibility
        $response = wp_remote_head($url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            return new WP_Error('unreachable_url', 
                sprintf(__('Feed URL is not accessible: %s', 'job-killer'), $response->get_error_message())
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error('http_error', 
                sprintf(__('Feed URL returned HTTP status %d', 'job-killer'), $status_code)
            );
        }
        
        return true;
    }
    
    /**
     * Export logs to CSV
     */
    public function export_logs_csv($args = array()) {
        $logs = $this->get_logs(array_merge($args, array('limit' => 10000)));
        
        if (empty($logs)) {
            return false;
        }
        
        $filename = 'job-killer-logs-' . date('Y-m-d-H-i-s') . '.csv';
        $filepath = wp_upload_dir()['path'] . '/' . $filename;
        
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
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => wp_upload_dir()['url'] . '/' . $filename
        );
    }
    
    /**
     * Get system info
     */
    public function get_system_info() {
        global $wpdb;
        
        return array(
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
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
                'simplexml' => extension_loaded('simplexml')
            )
        );
    }
}