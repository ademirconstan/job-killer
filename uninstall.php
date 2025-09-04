<?php
/**
 * Job Killer Uninstall
 *
 * @package Job_Killer
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user has permission to delete plugins
if (!current_user_can('delete_plugins')) {
    exit;
}

/**
 * Remove all plugin data
 */
function job_killer_uninstall() {
    global $wpdb;
    
    // Remove options
    $options_to_delete = array(
        'job_killer_settings',
        'job_killer_feeds',
        'job_killer_api_configs',
        'job_killer_activated',
        'job_killer_version',
        'job_killer_setup_completed',
        'job_killer_wpjm_synced',
        'job_killer_cron_running',
        'job_killer_last_cron'
    );
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // Remove transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_job_killer_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_job_killer_%'");
    
    // Remove custom tables
    $tables_to_drop = array(
        $wpdb->prefix . 'job_killer_logs',
        $wpdb->prefix . 'job_killer_imports'
    );
    
    foreach ($tables_to_drop as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
    
    // Clear scheduled cron events
    $cron_hooks = array(
        'job_killer_import_jobs',
        'job_killer_cleanup_logs',
        'job_killer_cleanup_expired_jobs',
        'job_killer_send_daily_report',
        'job_killer_cache_cleanup',
        'job_killer_api_import'
    );
    
    foreach ($cron_hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }
    
    // Remove job listings (if user chooses to)
    $remove_jobs = get_option('job_killer_remove_jobs_on_uninstall', false);
    
    if ($remove_jobs) {
        // Get all imported job listings
        $job_posts = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_job_killer_imported',
                    'compare' => 'EXISTS'
                )
            ),
            'fields' => 'ids'
        ));
        
        // Delete job posts and their meta
        foreach ($job_posts as $post_id) {
            wp_delete_post($post_id, true);
        }
        
        // Clean up orphaned meta
        $wpdb->query("
            DELETE pm FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.ID IS NULL
        ");
    }
    
    // Remove user meta related to Job Killer
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'job_killer_%'");
    
    // Remove any remaining plugin files from uploads
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/job-killer/';
    
    if (is_dir($plugin_upload_dir)) {
        job_killer_remove_directory($plugin_upload_dir);
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Clear any object cache
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}

/**
 * Recursively remove directory
 */
function job_killer_remove_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            job_killer_remove_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

// Run uninstall
job_killer_uninstall();