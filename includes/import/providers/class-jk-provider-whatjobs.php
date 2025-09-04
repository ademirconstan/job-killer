<?php
/**
 * Job Killer WhatJobs Provider
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WhatJobs API Provider
 */
class JK_Provider_WhatJobs {
    
    /**
     * Provider ID
     */
    const PROVIDER_ID = 'whatjobs';
    
    /**
     * API Base URL
     */
    const API_BASE_URL = 'https://api.whatjobs.com/api/v1/jobs.xml';
    
    /**
     * Build API URL with proper parameters
     */
    public static function build_url($publisher_id, $args = array()) {
        if (empty($publisher_id)) {
            throw new Exception(__('Publisher ID is required for WhatJobs API', 'job-killer'));
        }
        
        // Get user IP and user agent with fallbacks for cron
        $user_ip = self::get_user_ip();
        $user_agent = self::get_user_agent();
        
        // Base required parameters
        $params = array(
            'publisher'  => $publisher_id,
            'user_ip'    => $user_ip,
            'user_agent' => $user_agent,
            'snippet'    => 'full',
            'age_days'   => 0 // Only today's jobs
        );
        
        // Add optional parameters
        if (!empty($args['keyword'])) {
            $params['keyword'] = sanitize_text_field($args['keyword']);
        }
        
        if (!empty($args['location'])) {
            $params['location'] = sanitize_text_field($args['location']);
        }
        
        if (!empty($args['limit'])) {
            $params['limit'] = min(100, max(1, intval($args['limit'])));
        }
        
        if (!empty($args['page'])) {
            $params['page'] = max(1, intval($args['page']));
        }
        
        $url = add_query_arg($params, self::API_BASE_URL);
        
        jk_log('whatjobs_url_build', array(
            'publisher_id' => $publisher_id,
            'args' => $args,
            'final_url' => self::mask_sensitive_url($url),
            'user_ip_masked' => self::mask_ip($user_ip)
        ));
        
        return $url;
    }
    
    /**
     * Fetch jobs from WhatJobs API
     */
    public static function fetch($url, $args = array()) {
        jk_log('whatjobs_fetch_start', array(
            'url_masked' => self::mask_sensitive_url($url)
        ));
        
        $response = wp_remote_get($url, array(
            'timeout' => 20,
            'headers' => array(
                'Accept' => 'application/xml',
                'User-Agent' => self::get_user_agent()
            )
        ));
        
        if (is_wp_error($response)) {
            jk_log('whatjobs_fetch_error', array(
                'error' => $response->get_error_message()
            ));
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $error = new WP_Error('http_error', sprintf('HTTP %d from WhatJobs API', $status_code));
            jk_log('whatjobs_fetch_error', array(
                'status_code' => $status_code,
                'error' => $error->get_error_message()
            ));
            return $error;
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            $error = new WP_Error('empty_response', 'Empty response from WhatJobs API');
            jk_log('whatjobs_fetch_error', array(
                'error' => 'Empty response body'
            ));
            return $error;
        }
        
        jk_log('whatjobs_fetch_success', array(
            'response_length' => strlen($body),
            'response_preview' => substr($body, 0, 500)
        ));
        
        return self::parse_xml_response($body, $args);
    }
    
    /**
     * Parse XML response from WhatJobs
     */
    private static function parse_xml_response($xml_content, $args = array()) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_messages = array();
            foreach ($errors as $error) {
                $error_messages[] = trim($error->message);
            }
            
            jk_log('whatjobs_xml_parse_error', array(
                'errors' => $error_messages,
                'xml_preview' => substr($xml_content, 0, 500)
            ));
            
            return new WP_Error('xml_parse_error', 'XML parsing failed: ' . implode(', ', $error_messages));
        }
        
        $jobs = array();
        
        // WhatJobs structure: <data><job>...</job></data>
        if (isset($xml->job)) {
            foreach ($xml->job as $job_xml) {
                $job = self::parse_job_xml($job_xml);
                if (!empty($job)) {
                    // Apply filters
                    if (self::should_include_job($job, $args)) {
                        $jobs[] = $job;
                    }
                }
            }
        }
        
        jk_log('whatjobs_parse_success', array(
            'total_jobs_found' => count($jobs),
            'xml_structure' => isset($xml->job) ? 'valid' : 'invalid'
        ));
        
        return $jobs;
    }
    
    /**
     * Parse individual job XML
     */
    private static function parse_job_xml($job_xml) {
        $job = array(
            'title' => (string) ($job_xml->title ?? ''),
            'company' => (string) ($job_xml->company ?? ''),
            'location' => (string) ($job_xml->location ?? ''),
            'snippet' => (string) ($job_xml->snippet ?? ''),
            'url' => (string) ($job_xml->url ?? ''),
            'job_type' => (string) ($job_xml->job_type ?? ''),
            'salary' => (string) ($job_xml->salary ?? ''),
            'postcode' => (string) ($job_xml->postcode ?? ''),
            'logo' => (string) ($job_xml->logo ?? ''),
            'age' => (string) ($job_xml->age ?? ''),
            'age_days' => (int) ($job_xml->age_days ?? 999),
            'site' => (string) ($job_xml->site ?? ''),
            'category' => (string) ($job_xml->category ?? ''),
            'subcategory' => (string) ($job_xml->subcategory ?? ''),
            'country' => (string) ($job_xml->country ?? ''),
            'state' => (string) ($job_xml->state ?? ''),
            'city' => (string) ($job_xml->city ?? '')
        );
        
        // Clean and format description
        $job['description'] = jk_format_description($job['snippet']);
        
        return $job;
    }
    
    /**
     * Check if job should be included
     */
    private static function should_include_job($job, $args = array()) {
        // Skip if no title
        if (empty($job['title'])) {
            return false;
        }
        
        // Skip if description too short
        $min_length = get_option('job_killer_settings')['description_min_length'] ?? 100;
        if (strlen(strip_tags($job['description'])) < $min_length) {
            return false;
        }
        
        // Filter by age if only_today is set
        if (!empty($args['only_today']) && $job['age_days'] !== 0) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get user IP with fallbacks
     */
    private static function get_user_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback for cron jobs
        return '127.0.0.1';
    }
    
    /**
     * Get user agent with fallback
     */
    private static function get_user_agent() {
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            return $_SERVER['HTTP_USER_AGENT'];
        }
        
        // Fallback for cron jobs
        return 'JobKillerCron/1.0 (WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url') . ')';
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
            $params['user_ip'] = self::mask_ip($params['user_ip']);
        }
        
        if (isset($params['user_agent'])) {
            $params['user_agent'] = substr($params['user_agent'], 0, 20) . '...';
        }
        
        $masked_query = http_build_query($params);
        return $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'] . '?' . $masked_query;
    }
    
    /**
     * Mask IP address for logging
     */
    private static function mask_ip($ip) {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.xxx.xxx';
        }
        return 'xxx.xxx.xxx.xxx';
    }
}