<?php
/**
 * Job Killer API Class
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle external API integrations
 */
class Job_Killer_Api {
    
    /**
     * API providers
     */
    private $providers = array();
    
    /**
     * Helper instance
     */
    private $helper;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->helper = new Job_Killer_Helper();
        $this->init_providers();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_job_killer_test_api', array($this, 'test_api_connection'));
        add_action('job_killer_api_import', array($this, 'run_api_import'));
    }
    
    /**
     * Initialize API providers
     */
    private function init_providers() {
        $this->providers = array(
            'indeed' => array(
                'name' => 'Indeed API',
                'base_url' => 'https://api.indeed.com/ads/apisearch',
                'auth_type' => 'api_key',
                'rate_limit' => 100, // requests per hour
                'fields' => array(
                    'publisher' => 'required',
                    'q' => 'optional',
                    'l' => 'optional',
                    'sort' => 'optional',
                    'radius' => 'optional',
                    'st' => 'optional',
                    'jt' => 'optional',
                    'start' => 'optional',
                    'limit' => 'optional',
                    'fromage' => 'optional',
                    'filter' => 'optional',
                    'latlong' => 'optional',
                    'country' => 'optional',
                    'chnl' => 'optional',
                    'userip' => 'optional',
                    'useragent' => 'optional',
                    'v' => 'optional'
                )
            ),
            'adzuna' => array(
                'name' => 'Adzuna API',
                'base_url' => 'https://api.adzuna.com/v1/api/jobs',
                'auth_type' => 'app_credentials',
                'rate_limit' => 1000, // requests per hour
                'fields' => array(
                    'app_id' => 'required',
                    'app_key' => 'required',
                    'results_per_page' => 'optional',
                    'what' => 'optional',
                    'where' => 'optional',
                    'distance' => 'optional',
                    'salary_min' => 'optional',
                    'salary_max' => 'optional',
                    'sort_by' => 'optional'
                )
            ),
            'remoteok' => array(
                'name' => 'RemoteOK API',
                'base_url' => 'https://remoteok.io/api',
                'auth_type' => 'none',
                'rate_limit' => 60, // requests per hour
                'fields' => array()
            ),
            'github_jobs' => array(
                'name' => 'GitHub Jobs API',
                'base_url' => 'https://jobs.github.com/positions.json',
                'auth_type' => 'none',
                'rate_limit' => 60, // requests per hour
                'fields' => array(
                    'description' => 'optional',
                    'location' => 'optional',
                    'lat' => 'optional',
                    'long' => 'optional',
                    'full_time' => 'optional'
                )
            )
        );
    }
    
    /**
     * Get available providers
     */
    public function get_providers() {
        return $this->providers;
    }
    
    /**
     * Get provider configuration
     */
    public function get_provider($provider_id) {
        return isset($this->providers[$provider_id]) ? $this->providers[$provider_id] : null;
    }
    
    /**
     * Test API connection
     */
    public function test_api_connection() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $provider_id = sanitize_text_field($_POST['provider'] ?? '');
        $credentials = $_POST['credentials'] ?? array();
        
        if (empty($provider_id) || !isset($this->providers[$provider_id])) {
            wp_send_json_error(__('Invalid API provider', 'job-killer'));
        }
        
        try {
            $result = $this->test_provider_connection($provider_id, $credentials);
            
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
     * Test provider connection
     */
    private function test_provider_connection($provider_id, $credentials) {
        $provider = $this->providers[$provider_id];
        
        switch ($provider_id) {
            case 'indeed':
                return $this->test_indeed_api($credentials);
                
            case 'adzuna':
                return $this->test_adzuna_api($credentials);
                
            case 'remoteok':
                return $this->test_remoteok_api();
                
            case 'github_jobs':
                return $this->test_github_jobs_api();
                
            default:
                throw new Exception(__('Unsupported API provider', 'job-killer'));
        }
    }
    
    /**
     * Test Indeed API
     */
    private function test_indeed_api($credentials) {
        if (empty($credentials['publisher'])) {
            return array(
                'success' => false,
                'message' => __('Publisher ID is required for Indeed API', 'job-killer')
            );
        }
        
        $url = add_query_arg(array(
            'publisher' => $credentials['publisher'],
            'q' => 'developer',
            'l' => 'São Paulo',
            'limit' => 1,
            'format' => 'json',
            'v' => '2'
        ), $this->providers['indeed']['base_url']);
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Job Killer WordPress Plugin/' . JOB_KILLER_VERSION
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            return array(
                'success' => false,
                'message' => sprintf(__('API returned status code %d', 'job-killer'), $status_code)
            );
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => __('Invalid JSON response from API', 'job-killer')
            );
        }
        
        if (isset($data['error'])) {
            return array(
                'success' => false,
                'message' => $data['error']
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Connection successful!', 'job-killer'),
            'data' => array(
                'total_results' => $data['totalResults'] ?? 0,
                'sample_jobs' => array_slice($data['results'] ?? array(), 0, 3)
            )
        );
    }
    
    /**
     * Test Adzuna API
     */
    private function test_adzuna_api($credentials) {
        if (empty($credentials['app_id']) || empty($credentials['app_key'])) {
            return array(
                'success' => false,
                'message' => __('App ID and App Key are required for Adzuna API', 'job-killer')
            );
        }
        
        $url = sprintf(
            '%s/br/search/1?app_id=%s&app_key=%s&results_per_page=1&what=developer',
            $this->providers['adzuna']['base_url'],
            urlencode($credentials['app_id']),
            urlencode($credentials['app_key'])
        );
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Job Killer WordPress Plugin/' . JOB_KILLER_VERSION
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            return array(
                'success' => false,
                'message' => sprintf(__('API returned status code %d', 'job-killer'), $status_code)
            );
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => __('Invalid JSON response from API', 'job-killer')
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Connection successful!', 'job-killer'),
            'data' => array(
                'total_results' => $data['count'] ?? 0,
                'sample_jobs' => array_slice($data['results'] ?? array(), 0, 3)
            )
        );
    }
    
    /**
     * Test RemoteOK API
     */
    private function test_remoteok_api() {
        $url = $this->providers['remoteok']['base_url'];
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Job Killer WordPress Plugin/' . JOB_KILLER_VERSION
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            return array(
                'success' => false,
                'message' => sprintf(__('API returned status code %d', 'job-killer'), $status_code)
            );
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => __('Invalid JSON response from API', 'job-killer')
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Connection successful!', 'job-killer'),
            'data' => array(
                'total_results' => count($data),
                'sample_jobs' => array_slice($data, 0, 3)
            )
        );
    }
    
    /**
     * Test GitHub Jobs API
     */
    private function test_github_jobs_api() {
        $url = add_query_arg(array(
            'description' => 'developer',
            'location' => 'brazil'
        ), $this->providers['github_jobs']['base_url']);
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Job Killer WordPress Plugin/' . JOB_KILLER_VERSION
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            return array(
                'success' => false,
                'message' => sprintf(__('API returned status code %d', 'job-killer'), $status_code)
            );
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => __('Invalid JSON response from API', 'job-killer')
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Connection successful!', 'job-killer'),
            'data' => array(
                'total_results' => count($data),
                'sample_jobs' => array_slice($data, 0, 3)
            )
        );
    }
    
    /**
     * Import jobs from API
     */
    public function import_from_api($provider_id, $config) {
        $provider = $this->get_provider($provider_id);
        
        if (!$provider) {
            throw new Exception(__('Invalid API provider', 'job-killer'));
        }
        
        // Check rate limits
        if (!$this->check_rate_limit($provider_id)) {
            throw new Exception(__('API rate limit exceeded', 'job-killer'));
        }
        
        switch ($provider_id) {
            case 'indeed':
                return $this->import_from_indeed($config);
                
            case 'adzuna':
                return $this->import_from_adzuna($config);
                
            case 'remoteok':
                return $this->import_from_remoteok($config);
                
            case 'github_jobs':
                return $this->import_from_github_jobs($config);
                
            default:
                throw new Exception(__('Unsupported API provider', 'job-killer'));
        }
    }
    
    /**
     * Check rate limit
     */
    private function check_rate_limit($provider_id) {
        $provider = $this->providers[$provider_id];
        $rate_limit = $provider['rate_limit'];
        
        $cache_key = 'api_requests_' . $provider_id;
        $requests = Job_Killer_Cache::get($cache_key, array());
        
        // Clean old requests (older than 1 hour)
        $current_time = time();
        $requests = array_filter($requests, function($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < 3600;
        });
        
        if (count($requests) >= $rate_limit) {
            return false;
        }
        
        // Add current request
        $requests[] = $current_time;
        Job_Killer_Cache::set($cache_key, $requests, 3600);
        
        return true;
    }
    
    /**
     * Import from Indeed API
     */
    private function import_from_indeed($config) {
        // Implementation for Indeed API import
        // This would be similar to RSS import but using API endpoints
        
        $jobs = array();
        // ... API implementation
        
        return $jobs;
    }
    
    /**
     * Import from Adzuna API
     */
    private function import_from_adzuna($config) {
        // Implementation for Adzuna API import
        
        $jobs = array();
        // ... API implementation
        
        return $jobs;
    }
    
    /**
     * Import from RemoteOK API
     */
    private function import_from_remoteok($config) {
        // Implementation for RemoteOK API import
        
        $jobs = array();
        // ... API implementation
        
        return $jobs;
    }
    
    /**
     * Import from GitHub Jobs API
     */
    private function import_from_github_jobs($config) {
        // Implementation for GitHub Jobs API import
        
        $jobs = array();
        // ... API implementation
        
        return $jobs;
    }
    
    /**
     * Run API import (cron job)
     */
    public function run_api_import() {
        $this->helper->log('info', 'api', 'Starting API import process');
        
        $api_configs = get_option('job_killer_api_configs', array());
        
        foreach ($api_configs as $config_id => $config) {
            if (empty($config['active'])) {
                continue;
            }
            
            try {
                $jobs = $this->import_from_api($config['provider'], $config);
                
                $this->helper->log('success', 'api', 
                    sprintf('Imported %d jobs from %s API', count($jobs), $config['provider']),
                    array('config_id' => $config_id, 'jobs_count' => count($jobs))
                );
                
            } catch (Exception $e) {
                $this->helper->log('error', 'api', 
                    sprintf('Failed to import from %s API: %s', $config['provider'], $e->getMessage()),
                    array('config_id' => $config_id, 'error' => $e->getMessage())
                );
            }
        }
    }
    
    /**
     * Get API statistics
     */
    public function get_api_stats() {
        $stats = array();
        
        foreach ($this->providers as $provider_id => $provider) {
            $cache_key = 'api_requests_' . $provider_id;
            $requests = Job_Killer_Cache::get($cache_key, array());
            
            $stats[$provider_id] = array(
                'name' => $provider['name'],
                'requests_last_hour' => count($requests),
                'rate_limit' => $provider['rate_limit'],
                'remaining' => max(0, $provider['rate_limit'] - count($requests))
            );
        }
        
        return $stats;
    }
}