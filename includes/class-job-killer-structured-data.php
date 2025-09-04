<?php
/**
 * Job Killer Structured Data
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle structured data (JSON-LD) for job listings
 */
class Job_Killer_Structured_Data {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_head', array($this, 'add_job_structured_data'));
        add_action('wp_head', array($this, 'add_organization_structured_data'));
    }
    
    /**
     * Add job listing structured data
     */
    public function add_job_structured_data() {
        if (!is_singular('job_listing')) {
            return;
        }
        
        $settings = get_option('job_killer_settings', array());
        if (empty($settings['structured_data'])) {
            return;
        }
        
        global $post;
        
        $job_data = $this->get_job_structured_data($post->ID);
        
        if (!empty($job_data)) {
            echo '<script type="application/ld+json">' . wp_json_encode($job_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        }
    }
    
    /**
     * Get job structured data
     */
    private function get_job_structured_data($post_id) {
        $company = get_post_meta($post_id, '_company_name', true);
        $location = get_post_meta($post_id, '_job_location', true);
        $salary = get_post_meta($post_id, '_job_salary', true);
        $remote = get_post_meta($post_id, '_remote_position', true);
        $employment_type = get_post_meta($post_id, '_employment_type', true);
        $job_expires = get_post_meta($post_id, '_job_expires', true);
        $application_url = get_post_meta($post_id, '_application', true);
        
        $job_data = array(
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => get_the_title($post_id),
            'description' => wp_strip_all_tags(get_the_content('', false, $post_id)),
            'datePosted' => get_the_date('c', $post_id),
            'url' => get_permalink($post_id)
        );
        
        // Add employment type
        if (!empty($employment_type)) {
            $job_data['employmentType'] = $employment_type;
        } else {
            $job_data['employmentType'] = 'FULL_TIME'; // Default
        }
        
        // Add expiry date
        if (!empty($job_expires)) {
            $job_data['validThrough'] = date('c', strtotime($job_expires));
        } else {
            // Default to 30 days from post date
            $post_date = get_the_time('U', $post_id);
            $job_data['validThrough'] = date('c', $post_date + (30 * 24 * 60 * 60));
        }
        
        // Add hiring organization
        if (!empty($company)) {
            $job_data['hiringOrganization'] = array(
                '@type' => 'Organization',
                'name' => $company
            );
            
            // Add company logo if available
            $company_logo = get_post_meta($post_id, '_company_logo', true);
            $company_logo_url = get_post_meta($post_id, '_company_logo_url', true);
            
            if (!empty($company_logo) && is_numeric($company_logo)) {
                $logo_url = wp_get_attachment_image_url($company_logo, 'medium');
                if ($logo_url) {
                    $job_data['hiringOrganization']['logo'] = $logo_url;
                }
            } elseif (!empty($company_logo_url)) {
                $job_data['hiringOrganization']['logo'] = $company_logo_url;
            }
            
            // Add company website if available
            $company_website = get_post_meta($post_id, '_company_website', true);
            if (!empty($company_website)) {
                $job_data['hiringOrganization']['url'] = $company_website;
            }
        }
        
        // Add job location
        if (!empty($location)) {
            $job_data['jobLocation'] = array(
                '@type' => 'Place',
                'address' => array(
                    '@type' => 'PostalAddress',
                    'addressLocality' => $location
                )
            );
            
            // Try to extract more specific location data
            $city = get_post_meta($post_id, '_whatjobs_city', true);
            $state = get_post_meta($post_id, '_whatjobs_state', true);
            $country = get_post_meta($post_id, '_whatjobs_country', true);
            $postal_code = get_post_meta($post_id, '_whatjobs_postal_code', true);
            
            if (!empty($city)) {
                $job_data['jobLocation']['address']['addressLocality'] = $city;
            }
            
            if (!empty($state)) {
                $job_data['jobLocation']['address']['addressRegion'] = $state;
            }
            
            if (!empty($country)) {
                $job_data['jobLocation']['address']['addressCountry'] = $country;
            }
            
            if (!empty($postal_code)) {
                $job_data['jobLocation']['address']['postalCode'] = $postal_code;
            }
        }
        
        // Add remote work indicator
        if ($remote) {
            $job_data['jobLocationType'] = 'TELECOMMUTE';
        }
        
        // Add salary information
        if (!empty($salary)) {
            $salary_data = $this->parse_salary($salary);
            if (!empty($salary_data)) {
                $job_data['baseSalary'] = $salary_data;
            }
        }
        
        // Add application URL
        if (!empty($application_url)) {
            $job_data['directApply'] = true;
            $job_data['applicationContact'] = array(
                '@type' => 'ContactPoint',
                'url' => $application_url
            );
        }
        
        // Add job categories
        $categories = get_the_terms($post_id, 'job_listing_category');
        if ($categories && !is_wp_error($categories)) {
            $job_data['industry'] = array_map(function($cat) {
                return $cat->name;
            }, $categories);
        }
        
        // Add job types
        $types = get_the_terms($post_id, 'job_listing_type');
        if ($types && !is_wp_error($types)) {
            $job_data['employmentType'] = $this->map_job_type_to_schema($types[0]->name);
        }
        
        return $job_data;
    }
    
    /**
     * Parse salary information
     */
    private function parse_salary($salary_string) {
        // Remove common prefixes and clean up
        $salary = preg_replace('/^(R\$|USD|EUR|\$)\s*/i', '', trim($salary_string));
        
        // Try to extract numeric values
        if (preg_match('/(\d+(?:[.,]\d+)*)\s*(?:-|a|to)\s*(\d+(?:[.,]\d+)*)/i', $salary, $matches)) {
            // Range detected
            $min_salary = floatval(str_replace(',', '', $matches[1]));
            $max_salary = floatval(str_replace(',', '', $matches[2]));
            
            return array(
                '@type' => 'MonetaryAmount',
                'currency' => 'BRL',
                'value' => array(
                    '@type' => 'QuantitativeValue',
                    'minValue' => $min_salary,
                    'maxValue' => $max_salary,
                    'unitText' => $this->detect_salary_unit($salary_string)
                )
            );
        } elseif (preg_match('/(\d+(?:[.,]\d+)*)/i', $salary, $matches)) {
            // Single value
            $salary_value = floatval(str_replace(',', '', $matches[1]));
            
            return array(
                '@type' => 'MonetaryAmount',
                'currency' => 'BRL',
                'value' => array(
                    '@type' => 'QuantitativeValue',
                    'value' => $salary_value,
                    'unitText' => $this->detect_salary_unit($salary_string)
                )
            );
        }
        
        return null;
    }
    
    /**
     * Detect salary unit (monthly, yearly, etc.)
     */
    private function detect_salary_unit($salary_string) {
        $salary_lower = strtolower($salary_string);
        
        if (strpos($salary_lower, 'ano') !== false || strpos($salary_lower, 'year') !== false) {
            return 'YEAR';
        } elseif (strpos($salary_lower, 'hora') !== false || strpos($salary_lower, 'hour') !== false) {
            return 'HOUR';
        } elseif (strpos($salary_lower, 'dia') !== false || strpos($salary_lower, 'day') !== false) {
            return 'DAY';
        } else {
            return 'MONTH'; // Default to monthly
        }
    }
    
    /**
     * Map job type to schema.org employment type
     */
    private function map_job_type_to_schema($job_type) {
        $type_lower = strtolower($job_type);
        
        $type_mapping = array(
            'tempo integral' => 'FULL_TIME',
            'full time' => 'FULL_TIME',
            'meio período' => 'PART_TIME',
            'part time' => 'PART_TIME',
            'freelance' => 'CONTRACTOR',
            'contrato' => 'CONTRACTOR',
            'temporário' => 'TEMPORARY',
            'temporary' => 'TEMPORARY',
            'estágio' => 'INTERN',
            'internship' => 'INTERN'
        );
        
        return isset($type_mapping[$type_lower]) ? $type_mapping[$type_lower] : 'FULL_TIME';
    }
    
    /**
     * Add organization structured data
     */
    public function add_organization_structured_data() {
        if (!is_singular('job_listing')) {
            return;
        }
        
        $settings = get_option('job_killer_settings', array());
        if (empty($settings['structured_data'])) {
            return;
        }
        
        global $post;
        
        $company = get_post_meta($post->ID, '_company_name', true);
        $company_website = get_post_meta($post->ID, '_company_website', true);
        $company_logo = get_post_meta($post->ID, '_company_logo', true);
        $company_logo_url = get_post_meta($post->ID, '_company_logo_url', true);
        
        if (empty($company)) {
            return;
        }
        
        $organization_data = array(
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $company
        );
        
        if (!empty($company_website)) {
            $organization_data['url'] = $company_website;
        }
        
        if (!empty($company_logo) && is_numeric($company_logo)) {
            $logo_url = wp_get_attachment_image_url($company_logo, 'medium');
            if ($logo_url) {
                $organization_data['logo'] = $logo_url;
            }
        } elseif (!empty($company_logo_url)) {
            $organization_data['logo'] = $company_logo_url;
        }
        
        echo '<script type="application/ld+json">' . wp_json_encode($organization_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
}

// Initialize structured data
new Job_Killer_Structured_Data();