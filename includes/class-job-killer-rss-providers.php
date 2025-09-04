<?php
/**
 * Job Killer RSS Providers Class
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle different RSS providers and their specific formats
 */
class Job_Killer_Rss_Providers {
    
    /**
     * Supported providers
     */
    private $providers = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_providers();
    }
    
    /**
     * Initialize supported providers
     */
    private function init_providers() {
        $this->providers = array(
            'indeed' => array(
                'name' => 'Indeed',
                'field_mapping' => array(
                    'title' => 'title',
                    'description' => 'description',
                    'company' => 'source',
                    'location' => 'location',
                    'url' => 'link',
                    'date' => 'pubDate',
                    'salary' => 'salary'
                ),
                'url_pattern' => '/indeed\.com/',
                'parser' => 'parse_indeed_feed'
            ),
            'catho' => array(
                'name' => 'Catho',
                'field_mapping' => array(
                    'title' => 'title',
                    'description' => 'description',
                    'company' => 'company',
                    'location' => 'location',
                    'url' => 'link',
                    'date' => 'pubDate',
                    'salary' => 'salary'
                ),
                'url_pattern' => '/catho\.com\.br/',
                'parser' => 'parse_catho_feed'
            ),
            'infojobs' => array(
                'name' => 'InfoJobs',
                'field_mapping' => array(
                    'title' => 'title',
                    'description' => 'description',
                    'company' => 'company',
                    'location' => 'city',
                    'url' => 'link',
                    'date' => 'pubDate',
                    'salary' => 'salaryDescription'
                ),
                'url_pattern' => '/infojobs\.com\.br/',
                'parser' => 'parse_infojobs_feed'
            ),
            'vagas' => array(
                'name' => 'Vagas.com',
                'field_mapping' => array(
                    'title' => 'title',
                    'description' => 'description',
                    'company' => 'company',
                    'location' => 'location',
                    'url' => 'link',
                    'date' => 'pubDate',
                    'salary' => 'salary'
                ),
                'url_pattern' => '/vagas\.com\.br/',
                'parser' => 'parse_vagas_feed'
            ),
            'linkedin' => array(
                'name' => 'LinkedIn',
                'field_mapping' => array(
                    'title' => 'title',
                    'description' => 'description',
                    'company' => 'company',
                    'location' => 'location',
                    'url' => 'link',
                    'date' => 'pubDate'
                ),
                'url_pattern' => '/linkedin\.com/',
                'parser' => 'parse_linkedin_feed'
            ),
            'generic' => array(
                'name' => 'Generic RSS',
                'field_mapping' => array(
                    'title' => 'title',
                    'description' => 'description',
                    'company' => 'company',
                    'location' => 'location',
                    'url' => 'link',
                    'date' => 'pubDate',
                    'salary' => 'salary'
                ),
                'url_pattern' => '/.*/',
                'parser' => 'parse_generic_feed'
            )
        );
    }
    
    /**
     * Detect provider from URL
     */
    public function detect_provider($url) {
        foreach ($this->providers as $provider_id => $provider) {
            if ($provider_id === 'generic') {
                continue; // Skip generic, use as fallback
            }
            
            if (preg_match($provider['url_pattern'], $url)) {
                return $provider_id;
            }
        }
        
        return 'generic';
    }
    
    /**
     * Get provider configuration
     */
    public function get_provider_config($provider_id) {
        return isset($this->providers[$provider_id]) ? $this->providers[$provider_id] : $this->providers['generic'];
    }
    
    /**
     * Get all providers
     */
    public function get_providers() {
        return $this->providers;
    }
    
    /**
     * Parse Indeed feed
     */
    public function parse_indeed_feed($xml, $feed_config) {
        $jobs = array();
        
        if (!isset($xml->channel->item)) {
            return $jobs;
        }
        
        foreach ($xml->channel->item as $item) {
            $job = array(
                'title' => (string) $item->title,
                'description' => (string) $item->description,
                'url' => (string) $item->link,
                'date' => (string) $item->pubDate,
                'location' => $this->extract_indeed_location($item),
                'company' => $this->extract_indeed_company($item),
                'salary' => $this->extract_indeed_salary($item)
            );
            
            if (!empty($job['title']) && !empty($job['description'])) {
                $jobs[] = $job;
            }
        }
        
        return $jobs;
    }
    
    /**
     * Extract Indeed location
     */
    private function extract_indeed_location($item) {
        // Indeed often includes location in the title or description
        $title = (string) $item->title;
        $description = (string) $item->description;
        
        // Look for location patterns
        if (preg_match('/em ([^-]+)/i', $title, $matches)) {
            return trim($matches[1]);
        }
        
        if (preg_match('/Location:\s*([^\n]+)/i', $description, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Extract Indeed company
     */
    private function extract_indeed_company($item) {
        $description = (string) $item->description;
        
        if (preg_match('/Company:\s*([^\n]+)/i', $description, $matches)) {
            return trim($matches[1]);
        }
        
        // Try to extract from title (format: "Job Title - Company")
        $title = (string) $item->title;
        if (preg_match('/^(.+?)\s*-\s*(.+?)(?:\s*em\s|$)/i', $title, $matches)) {
            return trim($matches[2]);
        }
        
        return '';
    }
    
    /**
     * Extract Indeed salary
     */
    private function extract_indeed_salary($item) {
        $description = (string) $item->description;
        
        if (preg_match('/Salary:\s*([^\n]+)/i', $description, $matches)) {
            return trim($matches[1]);
        }
        
        // Look for salary patterns in description
        if (preg_match('/R\$\s*[\d.,]+/i', $description, $matches)) {
            return trim($matches[0]);
        }
        
        return '';
    }
    
    /**
     * Parse Catho feed
     */
    public function parse_catho_feed($xml, $feed_config) {
        $jobs = array();
        
        if (!isset($xml->channel->item)) {
            return $jobs;
        }
        
        foreach ($xml->channel->item as $item) {
            $job = array(
                'title' => (string) $item->title,
                'description' => (string) $item->description,
                'url' => (string) $item->link,
                'date' => (string) $item->pubDate,
                'location' => $this->extract_catho_location($item),
                'company' => $this->extract_catho_company($item),
                'salary' => $this->extract_catho_salary($item)
            );
            
            if (!empty($job['title']) && !empty($job['description'])) {
                $jobs[] = $job;
            }
        }
        
        return $jobs;
    }
    
    /**
     * Extract Catho location
     */
    private function extract_catho_location($item) {
        // Check for custom fields
        $namespaces = $item->getNamespaces(true);
        
        if (isset($namespaces['catho'])) {
            $catho_elements = $item->children($namespaces['catho']);
            if (isset($catho_elements->location)) {
                return (string) $catho_elements->location;
            }
        }
        
        // Fallback to description parsing
        $description = (string) $item->description;
        if (preg_match('/Local:\s*([^\n]+)/i', $description, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Extract Catho company
     */
    private function extract_catho_company($item) {
        $namespaces = $item->getNamespaces(true);
        
        if (isset($namespaces['catho'])) {
            $catho_elements = $item->children($namespaces['catho']);
            if (isset($catho_elements->company)) {
                return (string) $catho_elements->company;
            }
        }
        
        $description = (string) $item->description;
        if (preg_match('/Empresa:\s*([^\n]+)/i', $description, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Extract Catho salary
     */
    private function extract_catho_salary($item) {
        $namespaces = $item->getNamespaces(true);
        
        if (isset($namespaces['catho'])) {
            $catho_elements = $item->children($namespaces['catho']);
            if (isset($catho_elements->salary)) {
                return (string) $catho_elements->salary;
            }
        }
        
        $description = (string) $item->description;
        if (preg_match('/Salário:\s*([^\n]+)/i', $description, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Parse InfoJobs feed
     */
    public function parse_infojobs_feed($xml, $feed_config) {
        $jobs = array();
        
        if (!isset($xml->channel->item)) {
            return $jobs;
        }
        
        foreach ($xml->channel->item as $item) {
            $job = array(
                'title' => (string) $item->title,
                'description' => (string) $item->description,
                'url' => (string) $item->link,
                'date' => (string) $item->pubDate,
                'location' => $this->extract_infojobs_location($item),
                'company' => $this->extract_infojobs_company($item),
                'salary' => $this->extract_infojobs_salary($item)
            );
            
            if (!empty($job['title']) && !empty($job['description'])) {
                $jobs[] = $job;
            }
        }
        
        return $jobs;
    }
    
    /**
     * Extract InfoJobs location
     */
    private function extract_infojobs_location($item) {
        $namespaces = $item->getNamespaces(true);
        
        if (isset($namespaces['infojobs'])) {
            $ij_elements = $item->children($namespaces['infojobs']);
            if (isset($ij_elements->city)) {
                return (string) $ij_elements->city;
            }
        }
        
        return '';
    }
    
    /**
     * Extract InfoJobs company
     */
    private function extract_infojobs_company($item) {
        $namespaces = $item->getNamespaces(true);
        
        if (isset($namespaces['infojobs'])) {
            $ij_elements = $item->children($namespaces['infojobs']);
            if (isset($ij_elements->company)) {
                return (string) $ij_elements->company;
            }
        }
        
        return '';
    }
    
    /**
     * Extract InfoJobs salary
     */
    private function extract_infojobs_salary($item) {
        $namespaces = $item->getNamespaces(true);
        
        if (isset($namespaces['infojobs'])) {
            $ij_elements = $item->children($namespaces['infojobs']);
            if (isset($ij_elements->salaryDescription)) {
                return (string) $ij_elements->salaryDescription;
            }
        }
        
        return '';
    }
    
    /**
     * Parse Vagas.com feed
     */
    public function parse_vagas_feed($xml, $feed_config) {
        $jobs = array();
        
        if (!isset($xml->channel->item)) {
            return $jobs;
        }
        
        foreach ($xml->channel->item as $item) {
            $job = array(
                'title' => (string) $item->title,
                'description' => (string) $item->description,
                'url' => (string) $item->link,
                'date' => (string) $item->pubDate,
                'location' => $this->extract_vagas_location($item),
                'company' => $this->extract_vagas_company($item),
                'salary' => $this->extract_vagas_salary($item)
            );
            
            if (!empty($job['title']) && !empty($job['description'])) {
                $jobs[] = $job;
            }
        }
        
        return $jobs;
    }
    
    /**
     * Extract Vagas.com location
     */
    private function extract_vagas_location($item) {
        $description = (string) $item->description;
        
        if (preg_match('/Localização:\s*([^\n]+)/i', $description, $matches)) {
            return trim($matches[1]);
        }
        
        if (preg_match('/Local:\s*([^\n]+)/i', $description, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Extract Vagas.com company
     */
    private function extract_vagas_company($item) {
        $description = (string) $item->description;
        
        if (preg_match('/Empresa:\s*([^\n]+)/i', $description, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Extract Vagas.com salary
     */
    private function extract_vagas_salary($item) {
        $description = (string) $item->description;
        
        if (preg_match('/Salário:\s*([^\n]+)/i', $description, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Parse LinkedIn feed
     */
    public function parse_linkedin_feed($xml, $feed_config) {
        $jobs = array();
        
        if (!isset($xml->channel->item)) {
            return $jobs;
        }
        
        foreach ($xml->channel->item as $item) {
            $job = array(
                'title' => (string) $item->title,
                'description' => (string) $item->description,
                'url' => (string) $item->link,
                'date' => (string) $item->pubDate,
                'location' => $this->extract_linkedin_location($item),
                'company' => $this->extract_linkedin_company($item)
            );
            
            if (!empty($job['title']) && !empty($job['description'])) {
                $jobs[] = $job;
            }
        }
        
        return $jobs;
    }
    
    /**
     * Extract LinkedIn location
     */
    private function extract_linkedin_location($item) {
        $title = (string) $item->title;
        
        // LinkedIn format: "Job Title at Company in Location"
        if (preg_match('/\sin\s(.+)$/i', $title, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Extract LinkedIn company
     */
    private function extract_linkedin_company($item) {
        $title = (string) $item->title;
        
        // LinkedIn format: "Job Title at Company"
        if (preg_match('/\sat\s(.+?)(?:\sin\s|$)/i', $title, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Parse generic RSS feed
     */
    public function parse_generic_feed($xml, $feed_config) {
        $jobs = array();
        
        if (!isset($xml->channel->item)) {
            return $jobs;
        }
        
        $mapping = $feed_config['field_mapping'] ?? $this->providers['generic']['field_mapping'];
        
        foreach ($xml->channel->item as $item) {
            $job = array();
            
            foreach ($mapping as $local_field => $rss_field) {
                $value = $this->get_xml_value($item, $rss_field);
                if (!empty($value)) {
                    $job[$local_field] = $value;
                }
            }
            
            if (!empty($job['title']) && !empty($job['description'])) {
                $jobs[] = $job;
            }
        }
        
        return $jobs;
    }
    
    /**
     * Get XML value with namespace support
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
     * Get provider-specific field mapping suggestions
     */
    public function get_field_mapping_suggestions($provider_id) {
        $provider = $this->get_provider_config($provider_id);
        
        $suggestions = array(
            'title' => array('title', 'job_title', 'position'),
            'description' => array('description', 'content:encoded', 'summary', 'job_description'),
            'company' => array('company', 'employer', 'organization', 'company_name'),
            'location' => array('location', 'city', 'address', 'job_location', 'workplace'),
            'url' => array('link', 'url', 'apply_url', 'job_url'),
            'date' => array('pubDate', 'published', 'date', 'created_date'),
            'salary' => array('salary', 'compensation', 'pay', 'wage', 'salaryDescription'),
            'type' => array('type', 'employment_type', 'job_type', 'category')
        );
        
        // Add provider-specific suggestions
        switch ($provider_id) {
            case 'indeed':
                $suggestions['company'][] = 'source';
                break;
            case 'infojobs':
                $suggestions['location'][] = 'city';
                $suggestions['salary'][] = 'salaryDescription';
                break;
            case 'catho':
                $suggestions['location'][] = 'catho:location';
                $suggestions['company'][] = 'catho:company';
                $suggestions['salary'][] = 'catho:salary';
                break;
        }
        
        return $suggestions;
    }
    
    /**
     * Validate feed format
     */
    public function validate_feed_format($xml_content) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_messages = array();
            foreach ($errors as $error) {
                $error_messages[] = trim($error->message);
            }
            return array(
                'valid' => false,
                'errors' => $error_messages
            );
        }
        
        // Check for required RSS structure
        if (!isset($xml->channel)) {
            return array(
                'valid' => false,
                'errors' => array(__('Invalid RSS format: missing channel element', 'job-killer'))
            );
        }
        
        if (!isset($xml->channel->item)) {
            return array(
                'valid' => false,
                'errors' => array(__('Invalid RSS format: no items found', 'job-killer'))
            );
        }
        
        return array(
            'valid' => true,
            'items_count' => count($xml->channel->item),
            'title' => (string) $xml->channel->title,
            'description' => (string) $xml->channel->description
        );
    }
}