<?php
/**
 * Job Killer Providers Registry
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Central registry for job import providers
 */
class JK_Providers_Registry {
    
    /**
     * Registered providers
     */
    private static $providers = array();
    
    /**
     * Initialize registry
     */
    public static function init() {
        self::register_default_providers();
    }
    
    /**
     * Register default providers
     */
    private static function register_default_providers() {
        // WhatJobs provider
        self::$providers['whatjobs'] = array(
            'name' => 'WhatJobs',
            'class' => 'JK_Provider_WhatJobs',
            'patterns' => array(
                '/api\.whatjobs\.com/i',
                '/whatjobs\.com.*\/api/i'
            ),
            'type' => 'api'
        );
        
        // Generic RSS provider
        self::$providers['generic_rss'] = array(
            'name' => 'Generic RSS',
            'class' => 'JK_Provider_Generic_RSS',
            'patterns' => array('/.*rss.*/i', '/.*/'),
            'type' => 'rss'
        );
    }
    
    /**
     * Get provider from URL
     */
    public static function get_provider_from_url($url) {
        if (empty($url)) {
            return 'generic_rss';
        }
        
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);
        $full_url = $host . $path;
        
        // Check each provider's patterns
        foreach (self::$providers as $provider_id => $provider) {
            if ($provider_id === 'generic_rss') {
                continue; // Skip generic, use as fallback
            }
            
            foreach ($provider['patterns'] as $pattern) {
                if (preg_match($pattern, $full_url)) {
                    jk_log('provider_detection', array(
                        'url' => $url,
                        'detected_provider' => $provider_id,
                        'pattern_matched' => $pattern
                    ));
                    return $provider_id;
                }
            }
        }
        
        jk_log('provider_detection', array(
            'url' => $url,
            'detected_provider' => 'generic_rss',
            'reason' => 'no_pattern_matched'
        ));
        
        return 'generic_rss';
    }
    
    /**
     * Get provider info
     */
    public static function get_provider_info($provider_id) {
        return isset(self::$providers[$provider_id]) ? self::$providers[$provider_id] : null;
    }
    
    /**
     * Get all providers
     */
    public static function get_all_providers() {
        return self::$providers;
    }
    
    /**
     * Register custom provider
     */
    public static function register_provider($provider_id, $config) {
        self::$providers[$provider_id] = $config;
    }
    
    /**
     * Get provider instance
     */
    public static function get_provider_instance($provider_id) {
        $provider_info = self::get_provider_info($provider_id);
        
        if (!$provider_info || !class_exists($provider_info['class'])) {
            return null;
        }
        
        return new $provider_info['class']();
    }
    
    /**
     * Test provider connection
     */
    public static function test_provider($provider_id, $config) {
        $provider_info = self::get_provider_info($provider_id);
        
        if (!$provider_info) {
            return new WP_Error('invalid_provider', __('Invalid provider', 'job-killer'));
        }
        
        if (!class_exists($provider_info['class'])) {
            return new WP_Error('provider_class_missing', sprintf(__('Provider class %s not found', 'job-killer'), $provider_info['class']));
        }
        
        $provider_class = $provider_info['class'];
        
        try {
            if ($provider_id === 'whatjobs') {
                $publisher_id = $config['auth']['publisher_id'] ?? '';
                if (empty($publisher_id)) {
                    return new WP_Error('missing_publisher_id', __('Publisher ID is required', 'job-killer'));
                }
                
                $test_args = array_merge($config['parameters'] ?? array(), array('only_today' => true));
                $url = $provider_class::build_url($publisher_id, $test_args);
                $result = $provider_class::fetch($url, $test_args);
                
                if (is_wp_error($result)) {
                    return $result;
                }
                
                return array(
                    'success' => true,
                    'message' => sprintf(__('Connection successful! Found %d jobs.', 'job-killer'), count($result)),
                    'jobs_found' => count($result),
                    'sample_jobs' => array_slice($result, 0, 3),
                    'api_url' => self::mask_sensitive_url($url)
                );
            }
            
            // For other providers, implement similar logic
            return new WP_Error('provider_not_implemented', __('Provider test not implemented', 'job-killer'));
            
        } catch (Exception $e) {
            return new WP_Error('provider_test_failed', $e->getMessage());
        }
    }
    
    /**
     * Mask sensitive information in URL for logging
     */
    private static function mask_sensitive_url($url) {
        $parsed = parse_url($url);
        if (!isset($parsed['query'])) {
            return $url;
        }
        
        parse_str($parsed['query'], $params);
        
        // Mask sensitive parameters
        if (isset($params['user_ip'])) {
            $parts = explode('.', $params['user_ip']);
            if (count($parts) === 4) {
                $params['user_ip'] = $parts[0] . '.' . $parts[1] . '.xxx.xxx';
            }
        }
        
        if (isset($params['user_agent'])) {
            $params['user_agent'] = substr($params['user_agent'], 0, 20) . '...';
        }
        
        $masked_query = http_build_query($params);
        return $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'] . '?' . $masked_query;
    }
}

/**
 * Get provider from URL helper function
 */
function jk_get_provider_from_url($url) {
    return JK_Providers_Registry::get_provider_from_url($url);
}

// Initialize registry
JK_Providers_Registry::init();