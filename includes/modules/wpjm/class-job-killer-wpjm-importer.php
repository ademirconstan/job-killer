<?php
/**
 * Job Killer WP Job Manager Importer
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle WP Job Manager specific import logic
 */
class Job_Killer_Wpjm_Importer extends Job_Killer_Importer {
    
    /**
     * Import a single job with WPJM compatibility
     */
    public function import_job($job_data, $feed_id, $feed_config) {
        // Check for duplicates using WPJM logic
        if (!empty($this->settings['deduplication_enabled'])) {
            if ($this->wpjm_job_exists($job_data)) {
                return false; // Skip duplicate
            }
        }
        
        // Prepare WPJM-compatible post data
        $post_data = array(
            'post_title' => sanitize_text_field($job_data['title']),
            'post_content' => wp_kses_post($job_data['description']),
            'post_status' => 'publish',
            'post_type' => 'job_listing',
            'post_author' => 1,
            'meta_input' => $this->prepare_wpjm_meta($job_data, $feed_id)
        );
        
        // Insert post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            throw new Exception('Failed to create job post: ' . $post_id->get_error_message());
        }
        
        // Set WPJM taxonomies
        $this->set_wpjm_taxonomies($post_id, $job_data, $feed_config);
        
        // Handle company logo
        $this->handle_company_logo($post_id, $job_data);
        
        // Set job status
        update_post_meta($post_id, '_job_status', 'active');
        
        // Trigger WPJM hooks
        do_action('job_manager_job_submitted', $post_id);
        do_action('job_killer_after_job_import', $post_id, $job_data);
        
        return true;
    }
    
    /**
     * Check if job exists using WPJM logic
     */
    private function wpjm_job_exists($job_data) {
        global $wpdb;
        
        $title = sanitize_text_field($job_data['title']);
        $company = sanitize_text_field($job_data['company'] ?? '');
        $location = sanitize_text_field($job_data['location'] ?? '');
        
        // Check for existing job with same title, company, and location
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
     * Prepare WPJM-compatible meta data
     */
    private function prepare_wpjm_meta($job_data, $feed_id) {
        $meta = array(
            // Core WPJM fields
            '_job_location' => sanitize_text_field($job_data['location'] ?? ''),
            '_company_name' => sanitize_text_field($job_data['company'] ?? ''),
            '_application' => esc_url_raw($job_data['url'] ?? ''),
            '_job_expires' => $this->calculate_expiry_date($job_data),
            '_filled' => 0,
            '_featured' => 0,
            '_job_salary' => sanitize_text_field($job_data['salary'] ?? ''),
            '_remote_position' => $this->is_remote_job($job_data) ? 1 : 0,
            
            // Extended WPJM fields
            '_company_website' => $this->extract_company_website($job_data),
            '_company_tagline' => $this->extract_company_tagline($job_data),
            '_company_twitter' => $this->extract_company_twitter($job_data),
            '_company_video' => $this->extract_company_video($job_data),
            
            // Job Killer specific
            '_job_killer_feed_id' => $feed_id,
            '_job_killer_imported' => current_time('mysql'),
            '_job_killer_source_url' => esc_url_raw($job_data['url'] ?? ''),
            '_job_killer_hash' => $this->generate_job_hash($job_data)
        );
        
        // Add salary range if available
        if (!empty($job_data['salary_min']) || !empty($job_data['salary_max'])) {
            $meta['_job_salary_min'] = intval($job_data['salary_min'] ?? 0);
            $meta['_job_salary_max'] = intval($job_data['salary_max'] ?? 0);
            $meta['_job_salary_currency'] = sanitize_text_field($job_data['salary_currency'] ?? 'BRL');
            $meta['_job_salary_unit'] = sanitize_text_field($job_data['salary_unit'] ?? 'MONTH');
        }
        
        // Add job benefits if available
        if (!empty($job_data['benefits'])) {
            $meta['_job_benefits'] = wp_kses_post($job_data['benefits']);
        }
        
        // Add application deadline
        if (!empty($job_data['application_deadline'])) {
            $meta['_application_deadline'] = date('Y-m-d', strtotime($job_data['application_deadline']));
        }
        
        return $meta;
    }
    
    /**
     * Set WPJM taxonomies
     */
    private function set_wpjm_taxonomies($post_id, $job_data, $feed_config) {
        // Set job category
        if (!empty($feed_config['default_category'])) {
            $category_term = get_term_by('name', $feed_config['default_category'], 'job_listing_category');
            if (!$category_term) {
                $category_term = wp_insert_term($feed_config['default_category'], 'job_listing_category');
                if (!is_wp_error($category_term)) {
                    $category_term = get_term($category_term['term_id']);
                }
            }
            
            if (!is_wp_error($category_term)) {
                wp_set_post_terms($post_id, array($category_term->term_id), 'job_listing_category');
            }
        }
        
        // Set job type
        if (!empty($job_data['type'])) {
            $job_type = $this->normalize_job_type($job_data['type']);
            $type_term = get_term_by('name', $job_type, 'job_listing_type');
            
            if (!$type_term) {
                $type_term = wp_insert_term($job_type, 'job_listing_type');
                if (!is_wp_error($type_term)) {
                    $type_term = get_term($type_term['term_id']);
                }
            }
            
            if (!is_wp_error($type_term)) {
                wp_set_post_terms($post_id, array($type_term->term_id), 'job_listing_type');
            }
        }
        
        // Set job region (if taxonomy exists)
        if (taxonomy_exists('job_listing_region') && !empty($job_data['location'])) {
            $region = $this->extract_region($job_data['location']);
            if (!empty($region)) {
                $region_term = get_term_by('name', $region, 'job_listing_region');
                
                if (!$region_term) {
                    $region_term = wp_insert_term($region, 'job_listing_region');
                    if (!is_wp_error($region_term)) {
                        $region_term = get_term($region_term['term_id']);
                    }
                }
                
                if (!is_wp_error($region_term)) {
                    wp_set_post_terms($post_id, array($region_term->term_id), 'job_listing_region');
                }
            }
        }
    }
    
    /**
     * Handle company logo
     */
    private function handle_company_logo($post_id, $job_data) {
        if (empty($job_data['company_logo'])) {
            return;
        }
        
        $logo_url = esc_url_raw($job_data['company_logo']);
        
        // Try to download and attach the logo
        $attachment_id = $this->download_company_logo($logo_url, $post_id);
        
        if ($attachment_id) {
            update_post_meta($post_id, '_company_logo', $attachment_id);
        } else {
            // Store URL as fallback
            update_post_meta($post_id, '_company_logo_url', $logo_url);
        }
    }
    
    /**
     * Download company logo
     */
    private function download_company_logo($url, $post_id) {
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
        
        // Prepare file array
        $file_array = array(
            'name' => basename($url),
            'tmp_name' => $tmp
        );
        
        // Handle sideload
        $attachment_id = media_handle_sideload($file_array, $post_id, 'Company Logo');
        
        // Clean up temp file
        @unlink($tmp);
        
        if (is_wp_error($attachment_id)) {
            return false;
        }
        
        return $attachment_id;
    }
    
    /**
     * Extract company website from job data
     */
    private function extract_company_website($job_data) {
        $description = $job_data['description'] ?? '';
        
        // Look for website patterns
        $patterns = array(
            '/(?:website|site|www)[:\s]*([^\s]+\.[a-z]{2,})/i',
            '/https?:\/\/([^\s]+\.[a-z]{2,})/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description, $matches)) {
                $url = $matches[1];
                if (!preg_match('/^https?:\/\//', $url)) {
                    $url = 'http://' . $url;
                }
                
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    return esc_url($url);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Extract company tagline
     */
    private function extract_company_tagline($job_data) {
        $description = $job_data['description'] ?? '';
        
        // Look for tagline patterns
        $patterns = array(
            '/(?:tagline|slogan|motto)[:\s]*([^\n\r]{10,100})/i',
            '/(?:sobre|about)[:\s]*([^\n\r]{20,150})/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description, $matches)) {
                return sanitize_text_field(trim($matches[1]));
            }
        }
        
        return '';
    }
    
    /**
     * Extract company Twitter handle
     */
    private function extract_company_twitter($job_data) {
        $description = $job_data['description'] ?? '';
        
        // Look for Twitter handles
        if (preg_match('/@([a-zA-Z0-9_]{1,15})/', $description, $matches)) {
            return sanitize_text_field($matches[1]);
        }
        
        // Look for Twitter URLs
        if (preg_match('/twitter\.com\/([a-zA-Z0-9_]{1,15})/', $description, $matches)) {
            return sanitize_text_field($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Extract company video
     */
    private function extract_company_video($job_data) {
        $description = $job_data['description'] ?? '';
        
        // Look for video URLs
        $patterns = array(
            '/(youtube\.com\/watch\?v=|youtu\.be\/)([^\s&]+)/i',
            '/(vimeo\.com\/)([^\s&]+)/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description, $matches)) {
                return esc_url($matches[0]);
            }
        }
        
        return '';
    }
    
    /**
     * Get WPJM-specific import statistics
     */
    public function get_wpjm_import_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total imported jobs
        $stats['total_imported'] = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_job_killer_imported'
        ");
        
        // Active jobs
        $stats['active_jobs'] = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_job_killer_imported'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_filled'
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND (pm2.meta_value IS NULL OR pm2.meta_value = '0')
        ");
        
        // Remote jobs
        $stats['remote_jobs'] = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_job_killer_imported'
            JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_remote_position'
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm2.meta_value = '1'
        ");
        
        // Featured jobs
        $stats['featured_jobs'] = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_job_killer_imported'
            JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_featured'
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm2.meta_value = '1'
        ");
        
        // Jobs with salary info
        $stats['jobs_with_salary'] = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_job_killer_imported'
            JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_job_salary'
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm2.meta_value != ''
        ");
        
        return $stats;
    }
    
    /**
     * Cleanup expired jobs (WPJM compatible)
     */
    public function cleanup_expired_jobs() {
        global $wpdb;
        
        $expired_jobs = $wpdb->get_results("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_job_killer_imported'
            JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_job_expires'
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm2.meta_value < CURDATE()
        ");
        
        $expired_count = 0;
        
        foreach ($expired_jobs as $job) {
            // Mark as filled instead of deleting
            update_post_meta($job->ID, '_filled', 1);
            update_post_meta($job->ID, '_job_status', 'expired');
            
            $expired_count++;
        }
        
        if ($expired_count > 0) {
            $helper = new Job_Killer_Helper();
            $helper->log('info', 'cleanup', 
                sprintf('Marked %d expired jobs as filled', $expired_count),
                array('expired_count' => $expired_count)
            );
        }
        
        return $expired_count;
    }
}