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
class Job_Killer_WhatJobs_Provider {
    
    /**
     * Provider ID
     */
    const PROVIDER_ID = 'whatjobs';
    
    /**
     * API Base URL
     */
    const API_BASE_URL = 'https://api.whatjobs.com/api/v1/jobs.xml';
    
    /**
     * Helper instance
     */
    private $helper;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->helper = new Job_Killer_Helper();
    }
    
    /**
     * Get provider information
     */
    public function get_provider_info() {
        return array(
            'id' => self::PROVIDER_ID,
            'name' => 'WhatJobs',
            'description' => __('Import jobs from WhatJobs API with advanced filtering and mapping.', 'job-killer'),
            'requires_auth' => true,
            'auth_fields' => array(
                'publisher_id' => array(
                    'label' => __('Publisher ID', 'job-killer'),
                    'type' => 'text',
                    'required' => true,
                    'description' => __('Your WhatJobs Publisher ID (required for API access)', 'job-killer')
                )
            ),
            'parameters' => array(
                'keyword' => array(
                    'label' => __('Keywords', 'job-killer'),
                    'type' => 'text',
                    'description' => __('Job search keywords (optional)', 'job-killer')
                ),
                'location' => array(
                    'label' => __('Location', 'job-killer'),
                    'type' => 'text',
                    'description' => __('Job location (city, state, or country)', 'job-killer')
                ),
                'limit' => array(
                    'label' => __('Results Limit', 'job-killer'),
                    'type' => 'number',
                    'default' => 50,
                    'min' => 1,
                    'max' => 100,
                    'description' => __('Maximum number of jobs to import per request', 'job-killer')
                ),
                'page' => array(
                    'label' => __('Page', 'job-killer'),
                    'type' => 'number',
                    'default' => 1,
                    'min' => 1,
                    'description' => __('Page number for pagination', 'job-killer')
                )
            ),
            'field_mapping' => array(
                'title' => 'title',
                'company' => 'company',
                'location' => 'location',
                'description' => 'description',
                'url' => 'url',
                'job_type' => 'job_type',
                'salary' => 'salary',
                'logo' => 'logo',
                'age_days' => 'age_days',
                'date' => 'date'
            )
        );
    }
    
    /**
     * Build API URL
     */
    public function build_api_url($config) {
        $publisher_id = $config['auth']['publisher_id'] ?? '';
        
        if (empty($publisher_id)) {
            throw new Exception(__('Publisher ID is required for WhatJobs API', 'job-killer'));
        }
        
        // Build base parameters with required fields
        $params = $this->get_base_api_params($publisher_id);
        
        // Add optional parameters
        if (!empty($config['parameters']['keyword'])) {
            $params['keyword'] = sanitize_text_field($config['parameters']['keyword']);
        }
        
        if (!empty($config['parameters']['location'])) {
            $params['location'] = sanitize_text_field($config['parameters']['location']);
        }
        
        if (!empty($config['parameters']['limit'])) {
            $params['limit'] = min(100, max(1, intval($config['parameters']['limit'])));
        }
        
        if (!empty($config['parameters']['page'])) {
            $params['page'] = max(1, intval($config['parameters']['page']));
        }
        
        return add_query_arg($params, self::API_BASE_URL);
    }
    
    /**
     * Get base API parameters with required fields
     */
    private function get_base_api_params($publisher_id) {
        return array(
            'publisher'  => $publisher_id,
            'user_ip'    => urlencode($this->get_user_ip()),
            'user_agent' => urlencode($this->get_user_agent()),
            'snippet'    => 'full',
            'age_days'   => 0 // Only today's jobs
        );
    }
    
    /**
     * Get user IP address with fallback
     */
    private function get_user_ip() {
        // Try to get real IP from various headers (for proxy/CDN scenarios)
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header
            'HTTP_X_FORWARDED',          // Proxy header
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy header
            'HTTP_FORWARDED',            // Proxy header
            'REMOTE_ADDR'                // Standard server variable
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback for cron jobs or when no valid IP is found
        return '127.0.0.1';
    }
    
    /**
     * Get user agent with fallback
     */
    private function get_user_agent() {
        // Use server user agent if available
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            return $_SERVER['HTTP_USER_AGENT'];
        }
        
        // Fallback for cron jobs or missing user agent
        return 'JobKillerBot/1.0 (WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url') . ')';
    }
    
    /**
     * Test API connection
     */
    public function test_connection($config) {
        try {
            $url = $this->build_api_url($config);
            
            $this->helper->log('info', 'whatjobs', 
                'Testing WhatJobs API connection',
                array('url' => $url, 'config' => $config)
            );
            
            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'user-agent' => $this->get_user_agent()
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => $response->get_error_message()
                );
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code !== 200) {
                return array(
                    'success' => false,
                    'message' => sprintf(__('API returned status code %d', 'job-killer'), $status_code)
                );
            }
            
            $body = wp_remote_retrieve_body($response);
            $jobs = $this->parse_xml_response($body);
            
            return array(
                'success' => true,
                'message' => sprintf(__('Connection successful! Found %d jobs.', 'job-killer'), count($jobs)),
                'jobs_found' => count($jobs),
                'sample_jobs' => array_slice($jobs, 0, 3),
                'api_url' => $url // Include URL for debugging
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Import jobs from API
     */
    public function import_jobs($config) {
        $this->helper->log('info', 'whatjobs', 'Starting WhatJobs import', array('config' => $config));
        
        try {
            $url = $this->build_api_url($config);
            
            $response = wp_remote_get($url, array(
                'timeout' => 60,
                'user-agent' => $this->get_user_agent()
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('HTTP request failed: ' . $response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code !== 200) {
                throw new Exception('API returned status code: ' . $status_code);
            }
            
            $body = wp_remote_retrieve_body($response);
            $jobs = $this->parse_xml_response($body);
            
            // Filter and import jobs
            $imported_count = 0;
            foreach ($jobs as $job_data) {
                if ($this->should_import_job($job_data)) {
                    if ($this->import_single_job($job_data, $config)) {
                        $imported_count++;
                    }
                }
            }
            
            $this->helper->log('success', 'whatjobs', 
                sprintf('WhatJobs import completed. Imported %d jobs.', $imported_count),
                array('imported' => $imported_count, 'total_found' => count($jobs))
            );
            
            return $imported_count;
            
        } catch (Exception $e) {
            $this->helper->log('error', 'whatjobs', 
                'WhatJobs import failed: ' . $e->getMessage(),
                array('error' => $e->getMessage())
            );
            throw $e;
        }
    }
    
    /**
     * Parse XML response
     */
    private function parse_xml_response($xml_content) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_messages = array();
            foreach ($errors as $error) {
                $error_messages[] = trim($error->message);
            }
            throw new Exception('XML parsing failed: ' . implode(', ', $error_messages));
        }
        
        $jobs = array();
        
        if (isset($xml->job)) {
            foreach ($xml->job as $job_xml) {
                $job = $this->parse_job_xml($job_xml);
                if (!empty($job)) {
                    $jobs[] = $job;
                }
            }
        }
        
        return $jobs;
    }
    
    /**
     * Parse individual job XML
     */
    private function parse_job_xml($job_xml) {
        $job = array();
        
        // Basic fields
        $job['title'] = (string) $job_xml->title;
        $job['company'] = (string) $job_xml->company;
        $job['location'] = (string) $job_xml->location;
        $job['description'] = $this->clean_description((string) $job_xml->description);
        $job['url'] = (string) $job_xml->url;
        $job['job_type'] = (string) $job_xml->job_type;
        $job['salary'] = (string) $job_xml->salary;
        $job['logo'] = (string) $job_xml->logo;
        $job['age_days'] = intval($job_xml->age_days);
        $job['date'] = (string) $job_xml->date;
        
        // Additional fields
        $job['category'] = (string) $job_xml->category;
        $job['subcategory'] = (string) $job_xml->subcategory;
        $job['country'] = (string) $job_xml->country;
        $job['state'] = (string) $job_xml->state;
        $job['city'] = (string) $job_xml->city;
        $job['postal_code'] = (string) $job_xml->postal_code;
        
        // Employment details
        $job['employment_type'] = $this->normalize_employment_type((string) $job_xml->job_type);
        $job['remote_work'] = $this->detect_remote_work($job);
        
        return $job;
    }
    
    /**
     * Clean and format job description
     */
    private function clean_description($description) {
        if (empty($description)) {
            return '';
        }
        
        // Remove excessive whitespace
        $description = preg_replace('/\s+/', ' ', $description);
        
        // Convert line breaks to proper HTML
        $description = nl2br($description);
        
        // Clean up HTML but preserve structure
        $allowed_tags = array(
            'p' => array(),
            'br' => array(),
            'strong' => array(),
            'b' => array(),
            'em' => array(),
            'i' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array(),
            'div' => array('class' => array()),
            'span' => array('class' => array())
        );
        
        $description = wp_kses($description, $allowed_tags);
        
        // Fix common formatting issues
        $description = preg_replace('/<br\s*\/?>\s*<br\s*\/?>/i', '</p><p>', $description);
        $description = '<p>' . $description . '</p>';
        $description = preg_replace('/<p>\s*<\/p>/', '', $description);
        
        return trim($description);
    }
    
    /**
     * Normalize employment type
     */
    private function normalize_employment_type($job_type) {
        $type = strtolower(trim($job_type));
        
        $type_mapping = array(
            'full time' => 'FULL_TIME',
            'full-time' => 'FULL_TIME',
            'tempo integral' => 'FULL_TIME',
            'part time' => 'PART_TIME',
            'part-time' => 'PART_TIME',
            'meio período' => 'PART_TIME',
            'contract' => 'CONTRACTOR',
            'contractor' => 'CONTRACTOR',
            'freelance' => 'CONTRACTOR',
            'temporary' => 'TEMPORARY',
            'temporário' => 'TEMPORARY',
            'internship' => 'INTERN',
            'estágio' => 'INTERN'
        );
        
        return isset($type_mapping[$type]) ? $type_mapping[$type] : 'FULL_TIME';
    }
    
    /**
     * Detect remote work
     */
    private function detect_remote_work($job_data) {
        $remote_keywords = array(
            'remoto', 'remote', 'home office', 'trabalho remoto', 
            'teletrabalho', 'work from home', 'wfh'
        );
        
        $search_text = strtolower(
            $job_data['title'] . ' ' . 
            $job_data['description'] . ' ' . 
            $job_data['location']
        );
        
        foreach ($remote_keywords as $keyword) {
            if (strpos($search_text, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if job should be imported
     */
    private function should_import_job($job_data) {
        // Skip if no title
        if (empty($job_data['title'])) {
            return false;
        }
        
        // Skip if description is empty or too short
        $description = strip_tags($job_data['description']);
        $min_length = get_option('job_killer_settings')['description_min_length'] ?? 100;
        
        if (strlen($description) < $min_length) {
            return false;
        }
        
        // Skip if age_days is not 0 (we only want today's jobs)
        if ($job_data['age_days'] !== 0) {
            return false;
        }
        
        // Check for duplicates
        if ($this->is_duplicate_job($job_data)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if job is duplicate
     */
    private function is_duplicate_job($job_data) {
        $settings = get_option('job_killer_settings', array());
        
        if (empty($settings['deduplication_enabled'])) {
            return false;
        }
        
        global $wpdb;
        
        $title = sanitize_text_field($job_data['title']);
        $company = sanitize_text_field($job_data['company']);
        $location = sanitize_text_field($job_data['location']);
        
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_company_name'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_job_location'
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND p.post_title = %s
            AND (pm1.meta_value = %s OR %s = '')
            AND (pm2.meta_value = %s OR %s = '')
            LIMIT 1
        ", $title, $company, $company, $location, $location));
        
        return !empty($existing);
    }
    
    /**
     * Import single job
     */
    private function import_single_job($job_data, $config) {
        try {
            // Prepare post data
            $post_data = array(
                'post_title' => sanitize_text_field($job_data['title']),
                'post_content' => wp_kses_post($job_data['description']),
                'post_status' => 'publish',
                'post_type' => 'job_listing',
                'post_author' => 1,
                'meta_input' => $this->prepare_job_meta($job_data, $config)
            );
            
            // Insert post
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                throw new Exception('Failed to create job post: ' . $post_id->get_error_message());
            }
            
            // Set taxonomies
            $this->set_job_taxonomies($post_id, $job_data);
            
            // Handle company logo
            if (!empty($job_data['logo'])) {
                $this->handle_company_logo($post_id, $job_data['logo'], $job_data['company']);
            }
            
            // Trigger WP Job Manager hooks if available
            if (function_exists('job_manager_job_submitted')) {
                do_action('job_manager_job_submitted', $post_id);
            }
            
            do_action('job_killer_after_job_import', $post_id, $job_data, self::PROVIDER_ID);
            
            return true;
            
        } catch (Exception $e) {
            $this->helper->log('error', 'whatjobs', 
                'Failed to import job: ' . $e->getMessage(),
                array('job_data' => $job_data, 'error' => $e->getMessage())
            );
            return false;
        }
    }
    
    /**
     * Prepare job meta data
     */
    private function prepare_job_meta($job_data, $config) {
        $meta = array(
            // Core WP Job Manager fields
            '_job_location' => sanitize_text_field($job_data['location']),
            '_company_name' => sanitize_text_field($job_data['company']),
            '_application' => esc_url_raw($job_data['url']),
            '_job_expires' => $this->calculate_expiry_date($job_data),
            '_filled' => 0,
            '_featured' => 0,
            '_job_salary' => sanitize_text_field($job_data['salary']),
            '_remote_position' => $job_data['remote_work'] ? 1 : 0,
            
            // Job Killer specific
            '_job_killer_provider' => self::PROVIDER_ID,
            '_job_killer_imported' => current_time('mysql'),
            '_job_killer_source_url' => esc_url_raw($job_data['url']),
            '_job_killer_age_days' => intval($job_data['age_days']),
            
            // WhatJobs specific
            '_whatjobs_category' => sanitize_text_field($job_data['category']),
            '_whatjobs_subcategory' => sanitize_text_field($job_data['subcategory']),
            '_whatjobs_country' => sanitize_text_field($job_data['country']),
            '_whatjobs_state' => sanitize_text_field($job_data['state']),
            '_whatjobs_city' => sanitize_text_field($job_data['city']),
            '_whatjobs_postal_code' => sanitize_text_field($job_data['postal_code'])
        );
        
        // Add employment type for structured data
        $meta['_employment_type'] = $job_data['employment_type'];
        
        // Set job status for WP Job Manager
        $meta['_job_status'] = 'active';
        
        return $meta;
    }
    
    /**
     * Set job taxonomies
     */
    private function set_job_taxonomies($post_id, $job_data) {
        // Set job type
        if (!empty($job_data['job_type'])) {
            $job_type = $this->normalize_job_type($job_data['job_type']);
            $this->set_or_create_term($post_id, $job_type, 'job_listing_type');
        }
        
        // Set category based on WhatJobs category
        if (!empty($job_data['category'])) {
            $category = sanitize_text_field($job_data['category']);
            $this->set_or_create_term($post_id, $category, 'job_listing_category');
        }
        
        // Set region based on location
        if (!empty($job_data['state'])) {
            $region = sanitize_text_field($job_data['state']);
            $this->set_or_create_term($post_id, $region, 'job_listing_region');
        } elseif (!empty($job_data['city'])) {
            $region = sanitize_text_field($job_data['city']);
            $this->set_or_create_term($post_id, $region, 'job_listing_region');
        }
    }
    
    /**
     * Set or create taxonomy term
     */
    private function set_or_create_term($post_id, $term_name, $taxonomy) {
        if (empty($term_name)) {
            return;
        }
        
        $term = get_term_by('name', $term_name, $taxonomy);
        
        if (!$term) {
            $term_result = wp_insert_term($term_name, $taxonomy);
            if (!is_wp_error($term_result)) {
                $term = get_term($term_result['term_id'], $taxonomy);
            }
        }
        
        if ($term && !is_wp_error($term)) {
            wp_set_post_terms($post_id, array($term->term_id), $taxonomy);
        }
    }
    
    /**
     * Normalize job type for taxonomy
     */
    private function normalize_job_type($job_type) {
        $type = strtolower(trim($job_type));
        
        $type_mapping = array(
            'full time' => 'Tempo Integral',
            'full-time' => 'Tempo Integral',
            'part time' => 'Meio Período',
            'part-time' => 'Meio Período',
            'contract' => 'Contrato',
            'contractor' => 'Contrato',
            'freelance' => 'Freelance',
            'temporary' => 'Temporário',
            'internship' => 'Estágio',
            'intern' => 'Estágio'
        );
        
        return isset($type_mapping[$type]) ? $type_mapping[$type] : ucfirst($job_type);
    }
    
    /**
     * Calculate job expiry date
     */
    private function calculate_expiry_date($job_data) {
        // Default to 30 days from now
        return date('Y-m-d', strtotime('+30 days'));
    }
    
    /**
     * Handle company logo
     */
    private function handle_company_logo($post_id, $logo_url, $company_name) {
        if (empty($logo_url) || !filter_var($logo_url, FILTER_VALIDATE_URL)) {
            return;
        }
        
        // Try to download and attach the logo
        $attachment_id = $this->download_company_logo($logo_url, $post_id, $company_name);
        
        if ($attachment_id) {
            update_post_meta($post_id, '_company_logo', $attachment_id);
        } else {
            // Store URL as fallback
            update_post_meta($post_id, '_company_logo_url', esc_url($logo_url));
        }
    }
    
    /**
     * Download company logo
     */
    private function download_company_logo($url, $post_id, $company_name) {
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        
        // Download file
        $tmp = download_url($url);
        
        if (is_wp_error($tmp)) {
            return false;
        }
        
        // Get file extension
        $file_info = pathinfo($url);
        $extension = isset($file_info['extension']) ? $file_info['extension'] : 'jpg';
        
        // Prepare file array
        $file_array = array(
            'name' => sanitize_file_name($company_name . '-logo.' . $extension),
            'tmp_name' => $tmp
        );
        
        // Handle sideload
        $attachment_id = media_handle_sideload($file_array, $post_id, 'Logo da empresa ' . $company_name);
        
        // Clean up temp file
        @unlink($tmp);
        
        if (is_wp_error($attachment_id)) {
            return false;
        }
        
        return $attachment_id;
    }
    
    /**
     * Get provider statistics
     */
    public function get_provider_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total imported jobs
        $stats['total_imported'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_job_killer_provider'
            AND pm.meta_value = %s
        ", self::PROVIDER_ID));
        
        // Jobs imported today
        $stats['today_imported'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_job_killer_provider'
            AND pm.meta_value = %s
            AND DATE(p.post_date) = CURDATE()
        ", self::PROVIDER_ID));
        
        // Active jobs
        $stats['active_jobs'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_job_killer_provider'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_filled'
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm1.meta_value = %s
            AND (pm2.meta_value IS NULL OR pm2.meta_value = '0')
        ", self::PROVIDER_ID));
        
        return $stats;
    }
}