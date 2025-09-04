<?php
/**
 * Job Killer Importer
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle job imports from various providers
 */
class JK_Importer {
    
    /**
     * Settings
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('job_killer_settings', array());
    }
    
    /**
     * Import from feed
     */
    public function import_from_feed($feed_id, $feed_config) {
        jk_log('import_start', array(
            'feed_id' => $feed_id,
            'feed_name' => $feed_config['name'],
            'provider' => $feed_config['provider']
        ));
        
        // Load providers registry
        if (!class_exists('JK_Providers_Registry')) {
            require_once JOB_KILLER_PLUGIN_DIR . 'includes/import/providers-registry.php';
        }
        
        $provider_id = $feed_config['provider'];
        $provider_info = JK_Providers_Registry::get_provider_info($provider_id);
        
        if (!$provider_info) {
            throw new Exception(__('Invalid provider', 'job-killer'));
        }
        
        // Load provider class
        $provider_class = $provider_info['class'];
        if (!class_exists($provider_class)) {
            $provider_file = JOB_KILLER_PLUGIN_DIR . 'includes/import/providers/class-jk-provider-' . str_replace('_', '-', strtolower(str_replace('JK_Provider_', '', $provider_class))) . '.php';
            if (file_exists($provider_file)) {
                require_once $provider_file;
            }
        }
        
        if (!class_exists($provider_class)) {
            throw new Exception(sprintf(__('Provider class %s not found', 'job-killer'), $provider_class));
        }
        
        // Fetch jobs based on provider
        if ($provider_id === 'whatjobs') {
            $publisher_id = $feed_config['auth']['publisher_id'] ?? '';
            if (empty($publisher_id)) {
                throw new Exception(__('Publisher ID is required for WhatJobs', 'job-killer'));
            }
            
            $url = $provider_class::build_url($publisher_id, $feed_config['args']);
            $jobs = $provider_class::fetch($url, $feed_config['args']);
        } else {
            $jobs = $provider_class::fetch($feed_config['url'], $feed_config['args']);
        }
        
        if (is_wp_error($jobs)) {
            throw new Exception($jobs->get_error_message());
        }
        
        if (empty($jobs)) {
            jk_log('import_no_jobs', array(
                'feed_id' => $feed_id,
                'provider' => $provider_id
            ));
            return 0;
        }
        
        // Import jobs
        $imported_count = 0;
        $import_limit = !empty($this->settings['import_limit']) ? $this->settings['import_limit'] : 50;
        $jobs_to_import = array_slice($jobs, 0, $import_limit);
        
        foreach ($jobs_to_import as $job_data) {
            try {
                if ($this->import_single_job($job_data, $feed_id, $feed_config)) {
                    $imported_count++;
                }
            } catch (Exception $e) {
                jk_log('import_job_error', array(
                    'feed_id' => $feed_id,
                    'job_title' => $job_data['title'] ?? 'Unknown',
                    'error' => $e->getMessage()
                ));
            }
        }
        
        jk_log('import_complete', array(
            'feed_id' => $feed_id,
            'provider' => $provider_id,
            'total_found' => count($jobs),
            'imported' => $imported_count
        ));
        
        return $imported_count;
    }
    
    /**
     * Import single job
     */
    private function import_single_job($job_data, $feed_id, $feed_config) {
        // Check for duplicates
        if (!empty($this->settings['deduplication_enabled'])) {
            if ($this->is_duplicate_job($job_data)) {
                return false; // Skip duplicate
            }
        }
        
        // Get default post status from settings
        $default_status = get_option('jk_default_post_status', 'draft');
        $post_status = in_array($default_status, array('publish', 'draft'), true) ? $default_status : 'draft';
        
        // Prepare post data
        $post_data = array(
            'post_title' => sanitize_text_field($job_data['title']),
            'post_content' => $job_data['description'],
            'post_status' => $post_status,
            'post_type' => 'job_listing',
            'post_author' => 1,
            'meta_input' => array(
                '_job_location' => sanitize_text_field($job_data['location'] ?? ''),
                '_company_name' => sanitize_text_field($job_data['company'] ?? ''),
                '_application' => esc_url_raw($job_data['url'] ?? ''),
                '_job_expires' => $this->calculate_expiry_date($job_data),
                '_filled' => 0,
                '_featured' => 0,
                '_job_salary' => sanitize_text_field($job_data['salary'] ?? ''),
                '_remote_position' => $this->is_remote_job($job_data) ? 1 : 0,
                '_job_killer_feed_id' => $feed_id,
                '_job_killer_provider' => $feed_config['provider'],
                '_job_killer_imported' => current_time('mysql')
            )
        );
        
        // Insert post
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            throw new Exception('Failed to create job post: ' . $post_id->get_error_message());
        }
        
        // Set taxonomies
        $this->set_job_taxonomies($post_id, $job_data, $feed_config);
        
        // Handle company logo for WhatJobs
        if ($feed_config['provider'] === 'whatjobs' && !empty($job_data['logo'])) {
            update_post_meta($post_id, '_company_logo_url', esc_url($job_data['logo']));
        }
        
        jk_log('job_imported', array(
            'post_id' => $post_id,
            'feed_id' => $feed_id,
            'job_title' => $job_data['title'],
            'post_status' => $post_status
        ));
        
        return true;
    }
    
    /**
     * Check if job is duplicate
     */
    private function is_duplicate_job($job_data) {
        global $wpdb;
        
        $title = sanitize_text_field($job_data['title']);
        $company = sanitize_text_field($job_data['company'] ?? '');
        $location = sanitize_text_field($job_data['location'] ?? '');
        
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_company_name'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_job_location'
            WHERE p.post_type = 'job_listing'
            AND p.post_status IN ('publish', 'draft')
            AND p.post_title = %s
            AND (pm1.meta_value = %s OR %s = '')
            AND (pm2.meta_value = %s OR %s = '')
            LIMIT 1
        ", $title, $company, $company, $location, $location));
        
        return !empty($existing);
    }
    
    /**
     * Calculate job expiry date
     */
    private function calculate_expiry_date($job_data) {
        // If expiry date is provided
        if (!empty($job_data['expires'])) {
            return date('Y-m-d', strtotime($job_data['expires']));
        }
        
        // Default to 30 days from now
        return date('Y-m-d', strtotime('+30 days'));
    }
    
    /**
     * Check if job is remote
     */
    private function is_remote_job($job_data) {
        $remote_keywords = array('remoto', 'remote', 'home office', 'trabalho remoto', 'teletrabalho');
        
        $search_text = strtolower(
            ($job_data['title'] ?? '') . ' ' . 
            ($job_data['description'] ?? '') . ' ' . 
            ($job_data['location'] ?? '')
        );
        
        foreach ($remote_keywords as $keyword) {
            if (strpos($search_text, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Set job taxonomies
     */
    private function set_job_taxonomies($post_id, $job_data, $feed_config) {
        // Set category
        if (!empty($feed_config['args']['default_category'])) {
            $this->set_or_create_term($post_id, $feed_config['args']['default_category'], 'job_listing_category');
        }
        
        // Set job type
        if (!empty($job_data['job_type'])) {
            $job_type = $this->normalize_job_type($job_data['job_type']);
            $this->set_or_create_term($post_id, $job_type, 'job_listing_type');
        }
        
        // Set region
        if (!empty($feed_config['args']['default_region'])) {
            $this->set_or_create_term($post_id, $feed_config['args']['default_region'], 'job_listing_region');
        } elseif (!empty($job_data['state'])) {
            $this->set_or_create_term($post_id, $job_data['state'], 'job_listing_region');
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
     * Normalize job type
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
     * Run scheduled import for all active feeds
     */
    public function run_scheduled_import() {
        jk_log('scheduled_import_start', array());
        
        // Load feeds store
        if (!class_exists('JK_Feeds_Store')) {
            require_once JOB_KILLER_PLUGIN_DIR . 'includes/import/class-jk-feeds-store.php';
        }
        
        $active_feeds = JK_Feeds_Store::get_active_feeds();
        $total_imported = 0;
        
        foreach ($active_feeds as $feed) {
            try {
                $imported = $this->import_from_feed($feed['id'], $feed);
                $total_imported += $imported;
                
                // Add delay between feeds
                if (!empty($this->settings['request_delay'])) {
                    sleep($this->settings['request_delay']);
                }
                
            } catch (Exception $e) {
                jk_log('scheduled_import_feed_error', array(
                    'feed_id' => $feed['id'],
                    'feed_name' => $feed['name'],
                    'error' => $e->getMessage()
                ));
            }
        }
        
        jk_log('scheduled_import_complete', array(
            'total_imported' => $total_imported,
            'feeds_processed' => count($active_feeds)
        ));
        
        return $total_imported;
    }
}