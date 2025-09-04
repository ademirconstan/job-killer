<?php
/**
 * Job Killer Generic RSS Provider
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generic RSS Provider
 */
class JK_Provider_Generic_RSS {
    
    /**
     * Provider ID
     */
    const PROVIDER_ID = 'generic_rss';
    
    /**
     * Fetch jobs from RSS feed
     */
    public static function fetch($url, $args = array()) {
        jk_log('generic_rss_fetch_start', array(
            'url' => $url
        ));
        
        $response = wp_remote_get($url, array(
            'timeout' => 20,
            'headers' => array(
                'Accept' => 'application/rss+xml, application/xml, text/xml',
                'User-Agent' => 'JobKiller/1.0 (WordPress/' . get_bloginfo('version') . ')'
            )
        ));
        
        if (is_wp_error($response)) {
            jk_log('generic_rss_fetch_error', array(
                'error' => $response->get_error_message()
            ));
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $error = new WP_Error('http_error', sprintf('HTTP %d from RSS feed', $status_code));
            jk_log('generic_rss_fetch_error', array(
                'status_code' => $status_code
            ));
            return $error;
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            $error = new WP_Error('empty_response', 'Empty response from RSS feed');
            jk_log('generic_rss_fetch_error', array(
                'error' => 'Empty response body'
            ));
            return $error;
        }
        
        return self::parse_rss_response($body, $args);
    }
    
    /**
     * Parse RSS XML response
     */
    private static function parse_rss_response($xml_content, $args = array()) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_messages = array();
            foreach ($errors as $error) {
                $error_messages[] = trim($error->message);
            }
            
            jk_log('generic_rss_xml_parse_error', array(
                'errors' => $error_messages
            ));
            
            return new WP_Error('xml_parse_error', 'XML parsing failed: ' . implode(', ', $error_messages));
        }
        
        $jobs = array();
        
        // Standard RSS structure: <channel><item>...</item></channel>
        $items = $xml->channel->item ?? $xml->item ?? array();
        
        foreach ($items as $item) {
            $job = self::parse_rss_item($item);
            if (!empty($job)) {
                $jobs[] = $job;
            }
        }
        
        jk_log('generic_rss_parse_success', array(
            'total_jobs_found' => count($jobs)
        ));
        
        return $jobs;
    }
    
    /**
     * Parse individual RSS item
     */
    private static function parse_rss_item($item) {
        $job = array(
            'title' => (string) ($item->title ?? ''),
            'description' => (string) ($item->description ?? ''),
            'url' => (string) ($item->link ?? ''),
            'date' => (string) ($item->pubDate ?? ''),
            'company' => '',
            'location' => '',
            'salary' => ''
        );
        
        // Try to extract company and location from description or title
        $description = $job['description'];
        $title = $job['title'];
        
        // Extract company (common patterns)
        if (preg_match('/(?:company|empresa):\s*([^\n\r]+)/i', $description, $matches)) {
            $job['company'] = trim($matches[1]);
        } elseif (preg_match('/(.+?)\s*-\s*(.+?)(?:\s*em\s|$)/i', $title, $matches)) {
            $job['company'] = trim($matches[2]);
        }
        
        // Extract location (common patterns)
        if (preg_match('/(?:location|local|localização):\s*([^\n\r]+)/i', $description, $matches)) {
            $job['location'] = trim($matches[1]);
        } elseif (preg_match('/\sem\s(.+)$/i', $title, $matches)) {
            $job['location'] = trim($matches[1]);
        }
        
        // Extract salary
        if (preg_match('/(?:salary|salário):\s*([^\n\r]+)/i', $description, $matches)) {
            $job['salary'] = trim($matches[1]);
        } elseif (preg_match('/R\$\s*[\d.,]+/i', $description, $matches)) {
            $job['salary'] = trim($matches[0]);
        }
        
        // Format description
        $job['description'] = jk_format_description($job['description']);
        
        return $job;
    }
}