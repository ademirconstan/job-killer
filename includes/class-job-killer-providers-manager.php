<?php
/**
 * Job Killer Providers Manager
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manage all import providers
 */
class Job_Killer_Providers_Manager {
    
    /**
     * Registered providers
     */
    private $providers = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_providers();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_job_killer_test_provider', array($this, 'ajax_test_provider'));
        add_action('wp_ajax_job_killer_save_auto_feed', array($this, 'ajax_save_auto_feed'));
        add_action('wp_ajax_job_killer_get_provider_params', array($this, 'ajax_get_provider_params'));
    }
    
    /**
     * Load available providers
     */
    private function load_providers() {
        // Load WhatJobs provider
        require_once JOB_KILLER_PLUGIN_DIR . 'includes/import/class-job-killer-whatjobs-provider.php';
        $this->register_provider(new Job_Killer_WhatJobs_Provider());
        
        // Hook for additional providers
        do_action('job_killer_register_providers', $this);
    }
    
    /**
     * Register a provider
     */
    public function register_provider($provider) {
        $info = $provider->get_provider_info();
        $this->providers[$info['id']] = array(
            'instance' => $provider,
            'info' => $info
        );
    }
    
    /**
     * Get all providers
     */
    public function get_providers() {
        $providers = array();
        
        foreach ($this->providers as $id => $provider) {
            $providers[$id] = $provider['info'];
        }
        
        return $providers;
    }
    
    /**
     * Get provider instance
     */
    public function get_provider($provider_id) {
        return isset($this->providers[$provider_id]) ? $this->providers[$provider_id]['instance'] : null;
    }
    
    /**
     * Get provider info
     */
    public function get_provider_info($provider_id) {
        return isset($this->providers[$provider_id]) ? $this->providers[$provider_id]['info'] : null;
    }
    
    /**
     * Test provider connection (AJAX)
     */
    public function ajax_test_provider() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $provider_id = sanitize_text_field($_POST['provider_id'] ?? '');
        $config = $_POST['config'] ?? array();
        
        $provider = $this->get_provider($provider_id);
        
        if (!$provider) {
            wp_send_json_error(__('Invalid provider', 'job-killer'));
        }
        
        try {
            $result = $provider->test_connection($config);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Save automatic feed configuration (AJAX)
     */
    public function ajax_save_auto_feed() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $feed_data = $_POST['feed'] ?? array();
        
        if (empty($feed_data['name']) || empty($feed_data['provider_id'])) {
            wp_send_json_error(__('Feed name and provider are required', 'job-killer'));
        }
        
        $provider = $this->get_provider($feed_data['provider_id']);
        
        if (!$provider) {
            wp_send_json_error(__('Invalid provider', 'job-killer'));
        }
        
        // Sanitize feed configuration
        $feed_config = array(
            'id' => sanitize_key($feed_data['id'] ?? uniqid('feed_')),
            'name' => sanitize_text_field($feed_data['name']),
            'provider_id' => sanitize_text_field($feed_data['provider_id']),
            'active' => !empty($feed_data['active']),
            'auth' => $this->sanitize_auth_data($feed_data['auth'] ?? array()),
            'parameters' => $this->sanitize_parameters($feed_data['parameters'] ?? array()),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // Test configuration before saving
        try {
            $test_result = $provider->test_connection($feed_config);
            
            if (!$test_result['success']) {
                wp_send_json_error(__('Configuration test failed: ', 'job-killer') . $test_result['message']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(__('Configuration test failed: ', 'job-killer') . $e->getMessage());
        }
        
        // Save feed configuration
        $auto_feeds = get_option('job_killer_auto_feeds', array());
        $auto_feeds[$feed_config['id']] = $feed_config;
        update_option('job_killer_auto_feeds', $auto_feeds);
        
        $helper = new Job_Killer_Helper();
        $helper->log('info', 'admin', 
            sprintf('Auto feed "%s" saved successfully', $feed_config['name']),
            array('feed_id' => $feed_config['id'], 'provider' => $feed_config['provider_id'])
        );
        
        wp_send_json_success(array(
            'message' => __('Feed saved successfully!', 'job-killer'),
            'feed' => $feed_config
        ));
    }
    
    /**
     * Get provider parameters (AJAX)
     */
    public function ajax_get_provider_params() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        $provider_id = sanitize_text_field($_POST['provider_id'] ?? '');
        $provider_info = $this->get_provider_info($provider_id);
        
        if (!$provider_info) {
            wp_send_json_error(__('Invalid provider', 'job-killer'));
        }
        
        wp_send_json_success($provider_info);
    }
    
    /**
     * Sanitize auth data
     */
    private function sanitize_auth_data($auth_data) {
        $sanitized = array();
        
        foreach ($auth_data as $key => $value) {
            $sanitized[sanitize_key($key)] = sanitize_text_field($value);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize parameters
     */
    private function sanitize_parameters($parameters) {
        $sanitized = array();
        
        foreach ($parameters as $key => $value) {
            $key = sanitize_key($key);
            
            if (is_numeric($value)) {
                $sanitized[$key] = intval($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Run imports for all active auto feeds
     */
    public function run_auto_imports() {
        $auto_feeds = get_option('job_killer_auto_feeds', array());
        $total_imported = 0;
        
        foreach ($auto_feeds as $feed_id => $feed_config) {
            if (empty($feed_config['active'])) {
                continue;
            }
            
            $provider = $this->get_provider($feed_config['provider_id']);
            
            if (!$provider) {
                continue;
            }
            
            try {
                $imported = $provider->import_jobs($feed_config);
                $total_imported += $imported;
                
                // Update last import time
                $auto_feeds[$feed_id]['last_import'] = current_time('mysql');
                update_option('job_killer_auto_feeds', $auto_feeds);
                
            } catch (Exception $e) {
                $helper = new Job_Killer_Helper();
                $helper->log('error', 'auto_import', 
                    sprintf('Auto feed import failed for %s: %s', $feed_config['name'], $e->getMessage()),
                    array('feed_id' => $feed_id, 'provider' => $feed_config['provider_id'])
                );
            }
        }
        
        return $total_imported;
    }
    
    /**
     * Get auto feeds statistics
     */
    public function get_auto_feeds_stats() {
        $auto_feeds = get_option('job_killer_auto_feeds', array());
        $stats = array(
            'total_feeds' => count($auto_feeds),
            'active_feeds' => 0,
            'provider_breakdown' => array()
        );
        
        foreach ($auto_feeds as $feed) {
            if (!empty($feed['active'])) {
                $stats['active_feeds']++;
            }
            
            $provider_id = $feed['provider_id'];
            if (!isset($stats['provider_breakdown'][$provider_id])) {
                $stats['provider_breakdown'][$provider_id] = 0;
            }
            $stats['provider_breakdown'][$provider_id]++;
        }
        
        return $stats;
    }
}