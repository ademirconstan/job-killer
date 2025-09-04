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
}

/**
 * Get provider from URL helper function
 */
function jk_get_provider_from_url($url) {
    return JK_Providers_Registry::get_provider_from_url($url);
}

// Initialize registry
JK_Providers_Registry::init();