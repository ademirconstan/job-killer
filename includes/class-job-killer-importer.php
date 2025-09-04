<?php
/**
 * Job Killer Importer Class
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle job imports from RSS feeds and APIs
 */
class Job_Killer_Importer {
    
    /**
     * Settings
     */
    private $settings;
    
    /**
     * Helper instance
     */
    private $helper;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('job_killer_settings', array());
        
        if (class_exists('Job_Killer_Helper')) {
            $this->helper = new Job_Killer_Helper();
        }
    }
    
    /**
     * Run scheduled import
     */
    public function run_scheduled_import() {
        if (!$this->helper) {
            error_log('Job Killer: Helper not available for scheduled import');
            return;
        }
        
        $this->helper->log('info', 'cron', 'Starting scheduled import');
        
        // Import from traditional RSS feeds
        $feeds = get_option('job_killer_feeds', array());
        $total_imported = 0;
        
        foreach ($feeds as $feed_id => $feed_config) {
            if (empty($feed_config['active'])) {
                continue;
            }
            
            try {
                $imported = $this->import_from_feed($feed_id, $feed_config);
                $total_imported += $imported;
                
                $this->helper->log('success', 'import', 
                    sprintf('Imported %d jobs from feed %s', $imported, $feed_config['name']),
                    array('feed_id' => $feed_id, 'imported' => $imported)
                );
                
            } catch (Exception $e) {
                $this->helper->log('error', 'import', 
                    sprintf('Failed to import from feed %s: %s', $feed_config['name'], $e->getMessage()),
                    array('feed_id' => $feed_id, 'error' => $e->getMessage())
                );
            }
            
            // Add delay between feeds
            if (!empty($this->settings['request_delay'])) {
                sleep($this->settings['request_delay']);
            }
        }
        
        // Import from auto feeds (new providers system)
        try {
            if (class_exists('Job_Killer_Providers_Manager')) {
                $providers_manager = new Job_Killer_Providers_Manager();
                $auto_imported = $providers_manager->run_auto_imports();
                $total_imported += $auto_imported;
            
                if ($auto_imported > 0) {
                    $this->helper->log('success', 'import', 
                        sprintf('Imported %d jobs from auto feeds', $auto_imported),
                        array('auto_imported' => $auto_imported)
                    );
                }
            }
            
        } catch (Exception $e) {
            $this->helper->log('error', 'import', 
                'Auto feeds import failed: ' . $e->getMessage(),
                array('error' => $e->getMessage())
            );
        }
        
        $this->helper->log('info', 'cron', 
            sprintf('Scheduled import completed. Total imported: %d', $total_imported),
            array('total_imported' => $total_imported)
        );
        
        // Send notification if enabled
        if (!empty($this->settings['email_notifications']) && $total_imported > 0) {
            $this->send_import_notification($total_imported);
        }
    }
    
    /**
     * Import jobs from a specific feed
     */
    public function import_from_feed($feed_id, $feed_config) {
        if (!$this->helper) {
            throw new Exception('Helper not available');
        }
        
        $this->helper->log('info', 'import', 
            sprintf('Starting import from feed: %s', $feed_config['name']),
            array('feed_id' => $feed_id)
        );
        
        // Get feed data
        $feed_data = $this->fetch_feed_data($feed_config['url']);
        
        if (empty($feed_data)) {
            throw new Exception('Failed to fetch feed data');
        }
        
        // Parse feed
        $jobs = $this->parse_feed($feed_data, $feed_config);
        
        if (empty($jobs)) {
            $this->helper->log('warning', 'import', 'No jobs found in feed', array('feed_id' => $feed_id));
            return 0;
        }
        
        // Apply filters
        $jobs = $this->apply_filters($jobs);
        
        // Limit jobs per import
        $import_limit = !empty($this->settings['import_limit']) ? $this->settings['import_limit'] : 50;
        $jobs = array_slice($jobs, 0, $import_limit);
        
        // Import jobs
        $imported_count = 0;
        foreach ($jobs as $job_data) {
            try {
                if ($this->import_job($job_data, $feed_id, $feed_config)) {
                    $imported_count++;
                }
            } catch (Exception $e) {
                $this->helper->log('error', 'import', 
                    sprintf('Failed to import job: %s', $e->getMessage()),
                    array('job_data' => $job_data, 'error' => $e->getMessage())
                );
            }
        }
        
        return $imported_count;
    }
    
    /**
     * Fetch feed data
     */
    private function fetch_feed_data($url) {
        $cache_key = 'job_killer_feed_' . md5($url);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $timeout = !empty($this->settings['timeout']) ? $this->settings['timeout'] : 30;
        
        $response = wp_remote_get($url, array(
            'timeout' => $timeout,
            'user-agent' => 'Job Killer WordPress Plugin/' . JOB_KILLER_VERSION
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('HTTP request failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new Exception('HTTP request failed with status: ' . $status_code);
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Cache the data
        $cache_duration = !empty($this->settings['cache_duration']) ? $this->settings['cache_duration'] : 3600;
        set_transient($cache_key, $body, $cache_duration);
        
        return $body;
    }
    
    /**
     * Parse RSS feed
     */
    private function parse_feed($feed_data, $feed_config) {
        $jobs = array();
        
        // Suppress XML errors
        libxml_use_internal_errors(true);
        
        $xml = simplexml_load_string($feed_data);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_messages = array();
            foreach ($errors as $error) {
                $error_messages[] = $error->message;
            }
            throw new Exception('XML parsing failed: ' . implode(', ', $error_messages));
        }
        
        // Get field mapping
        $mapping = !empty($feed_config['field_mapping']) ? $feed_config['field_mapping'] : $this->get_default_mapping();
        
        // Parse items
        $items = $xml->channel->item ?? $xml->item ?? array();
        
        foreach ($items as $item) {
            $job = array();
            
            // Map fields
            foreach ($mapping as $local_field => $rss_field) {
                $value = $this->get_xml_value($item, $rss_field);
                if (!empty($value)) {
                    $job[$local_field] = $value;
                }
            }
            
            // Add feed metadata
            $job['feed_id'] = $feed_config['id'] ?? '';
            $job['feed_category'] = $feed_config['default_category'] ?? '';
            $job['feed_region'] = $feed_config['default_region'] ?? '';
            
            if (!empty($job['title']) && !empty($job['description'])) {
                $jobs[] = $job;
            }
        }
        
        return $jobs;
    }
    
    /**
     * Get XML value with fallbacks
     */
    private function get_xml_value($item, $field) {
        // Handle nested fields (e.g., 'content:encoded')
        if (strpos($field, ':') !== false) {
            $parts = explode(':', $field);
            $namespace = $parts[0];
            $element = $parts[1];
            
            $namespaces = $item->getNamespaces(true);
            if (isset($namespaces[$namespace])) {
                $ns_elements = $item->children($namespaces[$namespace]);
                if (isset($ns_elements->$element)) {
                    return (string) $ns_elements->$element;
                }
            }
        }
        
        // Direct field access
        if (isset($item->$field)) {
            return (string) $item->$field;
        }
        
        return '';
    }
    
    /**
     * Get default field mapping
     */
    private function get_default_mapping() {
        return array(
            'title' => 'title',
            'description' => 'description',
            'company' => 'company',
            'location' => 'location',
            'url' => 'link',
            'date' => 'pubDate',
            'salary' => 'salary',
            'type' => 'type'
        );
    }
    
    /**
     * Apply filters to jobs
     */
    private function apply_filters($jobs) {
        $filtered_jobs = array();
        
        foreach ($jobs as $job) {
            // Age filter
            if (!empty($this->settings['age_filter'])) {
                $job_date = strtotime($job['date'] ?? '');
                $max_age = time() - ($this->settings['age_filter'] * 24 * 60 * 60);
                
                if ($job_date < $max_age) {
                    continue;
                }
            }
            
            // Description length filter
            if (!empty($this->settings['description_min_length'])) {
                $description_length = strlen(strip_tags($job['description'] ?? ''));
                if ($description_length < $this->settings['description_min_length']) {
                    continue;
                }
            }
            
            $filtered_jobs[] = $job;
        }
        
        return $filtered_jobs;
    }
    
    /**
     * Import a single job
     */
    private function import_job($job_data, $feed_id, $feed_config) {
        // Check for duplicates
        if (!empty($this->settings['deduplication_enabled'])) {
            $job_hash = $this->generate_job_hash($job_data);
            
            if ($this->job_exists($job_hash)) {
                return false; // Skip duplicate
            }
        }
        
        // Prepare post data
        $post_data = array(
            'post_title' => sanitize_text_field($job_data['title']),
            'post_content' => wp_kses_post($job_data['description']),
            'post_status' => 'publish',
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
                '_job_killer_imported' => current_time('mysql')
            )
        );
        
        // Insert post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            throw new Exception('Failed to create post: ' . $post_id->get_error_message());
        }
        
        // Set taxonomies
        $this->set_job_taxonomies($post_id, $job_data, $feed_config);
        
        // Record import
        if (!empty($this->settings['deduplication_enabled'])) {
            $this->record_import($feed_id, $job_hash, $post_id);
        }
        
        return true;
    }
    
    /**
     * Generate job hash for deduplication
     */
    private function generate_job_hash($job_data) {
        $hash_string = strtolower(trim($job_data['title'] ?? '')) . '|' . 
                      strtolower(trim($job_data['company'] ?? '')) . '|' . 
                      strtolower(trim($job_data['location'] ?? ''));
        
        return hash('sha256', $hash_string);
    }
    
    /**
     * Check if job already exists
     */
    private function job_exists($job_hash) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'job_killer_imports';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE job_hash = %s",
            $job_hash
        ));
        
        return $exists > 0;
    }
    
    /**
     * Record import in database
     */
    private function record_import($feed_id, $job_hash, $post_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'job_killer_imports';
        $wpdb->insert($table, array(
            'feed_id' => $feed_id,
            'job_hash' => $job_hash,
            'post_id' => $post_id,
            'imported_at' => current_time('mysql')
        ));
    }
    
    /**
     * Calculate job expiry date
     */
    private function calculate_expiry_date($job_data) {
        // If expiry date is provided in feed
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
        
        $search_text = strtolower($job_data['title'] . ' ' . $job_data['description'] . ' ' . $job_data['location']);
        
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
        if (!empty($feed_config['default_category'])) {
            wp_set_post_terms($post_id, array($feed_config['default_category']), 'job_listing_category');
        }
        
        // Set job type
        if (!empty($job_data['type'])) {
            $job_type = $this->normalize_job_type($job_data['type']);
            wp_set_post_terms($post_id, array($job_type), 'job_listing_type');
        }
        
        // Set region based on location
        if (!empty($this->settings['auto_taxonomies']) && !empty($job_data['location'])) {
            $region = $this->extract_region($job_data['location']);
            if (!empty($region)) {
                wp_set_post_terms($post_id, array($region), 'job_listing_region');
            }
        }
    }
    
    /**
     * Normalize job type
     */
    private function normalize_job_type($type) {
        $type = strtolower(trim($type));
        
        $type_mapping = array(
            'full time' => 'Tempo Integral',
            'full-time' => 'Tempo Integral',
            'tempo integral' => 'Tempo Integral',
            'part time' => 'Meio Período',
            'part-time' => 'Meio Período',
            'meio período' => 'Meio Período',
            'freelance' => 'Freelance',
            'contract' => 'Contrato',
            'contrato' => 'Contrato',
            'temporary' => 'Temporário',
            'temporário' => 'Temporário',
            'internship' => 'Estágio',
            'estágio' => 'Estágio'
        );
        
        return isset($type_mapping[$type]) ? $type_mapping[$type] : 'Tempo Integral';
    }
    
    /**
     * Extract region from location
     */
    private function extract_region($location) {
        // Brazilian states mapping
        $states = array(
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
            'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
            'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
            'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
            'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
        );
        
        $location = strtoupper($location);
        
        // Check for state abbreviations
        foreach ($states as $abbr => $name) {
            if (strpos($location, $abbr) !== false) {
                return $name;
            }
        }
        
        // Check for full state names
        foreach ($states as $name) {
            if (strpos($location, strtoupper($name)) !== false) {
                return $name;
            }
        }
        
        // Extract city (first part before comma or dash)
        $parts = preg_split('/[,\-]/', $location);
        if (!empty($parts[0])) {
            return trim($parts[0]);
        }
        
        return '';
    }
    
    /**
     * Send import notification email
     */
    private function send_import_notification($total_imported) {
        $email = $this->settings['notification_email'] ?? get_option('admin_email');
        
        if (empty($email)) {
            return;
        }
        
        $subject = sprintf(__('[%s] Job Import Completed', 'job-killer'), get_bloginfo('name'));
        
        $message = sprintf(
            __("Hello,\n\nThe scheduled job import has completed successfully.\n\nTotal jobs imported: %d\n\nTime: %s\n\nBest regards,\nJob Killer Plugin", 'job-killer'),
            $total_imported,
            current_time('Y-m-d H:i:s')
        );
        
        wp_mail($email, $subject, $message);
    }
    
    /**
     * Test feed import
     */
    public function test_feed_import($feed_config) {
        try {
            $feed_data = $this->fetch_feed_data($feed_config['url']);
            $jobs = $this->parse_feed($feed_data, $feed_config);
            $jobs = $this->apply_filters($jobs);
            
            return array(
                'success' => true,
                'jobs_found' => count($jobs),
                'sample_jobs' => array_slice($jobs, 0, 3)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
}