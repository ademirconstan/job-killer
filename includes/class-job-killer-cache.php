<?php
/**
 * Job Killer Cache Class
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle caching functionality
 */
class Job_Killer_Cache {
    
    /**
     * Cache group
     */
    const CACHE_GROUP = 'job_killer';
    
    /**
     * Default cache duration (1 hour)
     */
    const DEFAULT_DURATION = 3600;
    
    /**
     * Get cached data
     */
    public static function get($key, $default = null) {
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        
        if ($cached === false) {
            // Try transient as fallback
            $cached = get_transient(self::get_transient_key($key));
            
            if ($cached !== false) {
                // Store in object cache for this request
                wp_cache_set($key, $cached, self::CACHE_GROUP);
            }
        }
        
        return $cached !== false ? $cached : $default;
    }
    
    /**
     * Set cached data
     */
    public static function set($key, $data, $duration = null) {
        if ($duration === null) {
            $settings = get_option('job_killer_settings', array());
            $duration = $settings['cache_duration'] ?? self::DEFAULT_DURATION;
        }
        
        // Store in object cache
        wp_cache_set($key, $data, self::CACHE_GROUP, $duration);
        
        // Store in transient as fallback
        set_transient(self::get_transient_key($key), $data, $duration);
        
        return true;
    }
    
    /**
     * Delete cached data
     */
    public static function delete($key) {
        wp_cache_delete($key, self::CACHE_GROUP);
        delete_transient(self::get_transient_key($key));
        
        return true;
    }
    
    /**
     * Clear all cache
     */
    public static function clear_all() {
        // Clear object cache group (if supported)
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }
        
        // Clear transients
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . self::CACHE_GROUP . '_%'
        ));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . self::CACHE_GROUP . '_%'
        ));
        
        return true;
    }
    
    /**
     * Get transient key
     */
    private static function get_transient_key($key) {
        return self::CACHE_GROUP . '_' . $key;
    }
    
    /**
     * Cache RSS feed data
     */
    public static function cache_feed($url, $data, $duration = null) {
        $key = 'feed_' . md5($url);
        return self::set($key, $data, $duration);
    }
    
    /**
     * Get cached RSS feed data
     */
    public static function get_cached_feed($url) {
        $key = 'feed_' . md5($url);
        return self::get($key);
    }
    
    /**
     * Cache job search results
     */
    public static function cache_search($search_params, $results, $duration = 1800) {
        $key = 'search_' . md5(serialize($search_params));
        return self::set($key, $results, $duration);
    }
    
    /**
     * Get cached search results
     */
    public static function get_cached_search($search_params) {
        $key = 'search_' . md5(serialize($search_params));
        return self::get($key);
    }
    
    /**
     * Cache statistics
     */
    public static function cache_stats($stats, $duration = 900) {
        return self::set('stats', $stats, $duration);
    }
    
    /**
     * Get cached statistics
     */
    public static function get_cached_stats() {
        return self::get('stats');
    }
    
    /**
     * Cache chart data
     */
    public static function cache_chart_data($days, $data, $duration = 1800) {
        $key = 'chart_' . $days;
        return self::set($key, $data, $duration);
    }
    
    /**
     * Get cached chart data
     */
    public static function get_cached_chart_data($days) {
        $key = 'chart_' . $days;
        return self::get($key);
    }
    
    /**
     * Invalidate related caches
     */
    public static function invalidate_job_caches() {
        // Clear job-related caches
        self::delete('stats');
        
        // Clear search caches
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . self::CACHE_GROUP . '_search_%'
        ));
        
        // Clear chart caches
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . self::CACHE_GROUP . '_chart_%'
        ));
    }
    
    /**
     * Get cache statistics
     */
    public static function get_cache_stats() {
        global $wpdb;
        
        $transient_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . self::CACHE_GROUP . '_%'
        ));
        
        $cache_size = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . self::CACHE_GROUP . '_%'
        ));
        
        return array(
            'transient_count' => (int) $transient_count,
            'cache_size' => (int) $cache_size,
            'cache_size_formatted' => size_format($cache_size)
        );
    }
    
    /**
     * Warm up cache
     */
    public static function warm_up() {
        // Pre-cache frequently accessed data
        $helper = new Job_Killer_Helper();
        
        // Cache statistics
        $stats = $helper->get_import_stats();
        self::cache_stats($stats);
        
        // Cache chart data for common periods
        $chart_data_7 = $helper->get_chart_data(7);
        self::cache_chart_data(7, $chart_data_7);
        
        $chart_data_30 = $helper->get_chart_data(30);
        self::cache_chart_data(30, $chart_data_30);
        
        return true;
    }
    
    /**
     * Schedule cache cleanup
     */
    public static function schedule_cleanup() {
        if (!wp_next_scheduled('job_killer_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'job_killer_cache_cleanup');
        }
    }
    
    /**
     * Cleanup expired cache
     */
    public static function cleanup_expired() {
        global $wpdb;
        
        // Clean up expired transients
        $expired = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_value < %d",
            '_transient_timeout_' . self::CACHE_GROUP . '_%',
            time()
        ));
        
        foreach ($expired as $transient) {
            $key = str_replace('_transient_timeout_', '', $transient);
            delete_transient(str_replace('_transient_timeout_' . self::CACHE_GROUP . '_', '', $transient));
        }
        
        return count($expired);
    }
}

// Schedule cache cleanup
add_action('job_killer_cache_cleanup', array('Job_Killer_Cache', 'cleanup_expired'));