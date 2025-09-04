<?php
/**
 * Job Killer Frontend Class
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle frontend functionality and shortcodes
 */
class Job_Killer_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->register_shortcodes();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_job_killer_search', array($this, 'ajax_search_jobs'));
        add_action('wp_ajax_nopriv_job_killer_search', array($this, 'ajax_search_jobs'));
        add_action('wp_ajax_job_killer_load_more', array($this, 'ajax_load_more_jobs'));
        add_action('wp_ajax_nopriv_job_killer_load_more', array($this, 'ajax_load_more_jobs'));
    }
    
    /**
     * Register shortcodes
     */
    private function register_shortcodes() {
        add_shortcode('job-killer-list', array($this, 'shortcode_job_list'));
        add_shortcode('job-killer-search', array($this, 'shortcode_job_search'));
        add_shortcode('job-killer-stats', array($this, 'shortcode_job_stats'));
        add_shortcode('job-killer-recent', array($this, 'shortcode_recent_jobs'));
    }
    
    /**
     * Job list shortcode
     */
    public function shortcode_job_list($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'category' => '',
            'type' => '',
            'region' => '',
            'featured' => '',
            'remote' => '',
            'orderby' => 'date',
            'order' => 'DESC',
            'show_filters' => 'true',
            'show_pagination' => 'true',
            'show_search' => 'true'
        ), $atts);
        
        ob_start();
        
        // Get jobs
        $jobs = $this->get_jobs($atts);
        
        echo '<div class="job-killer-list-wrapper">';
        
        // Search and filters
        if ($atts['show_search'] === 'true' || $atts['show_filters'] === 'true') {
            echo '<div class="job-killer-filters">';
            
            if ($atts['show_search'] === 'true') {
                $this->render_search_form();
            }
            
            if ($atts['show_filters'] === 'true') {
                $this->render_filters();
            }
            
            echo '</div>';
        }
        
        // Jobs list
        echo '<div class="job-killer-jobs" id="job-killer-jobs">';
        $this->render_jobs_list($jobs);
        echo '</div>';
        
        // Pagination
        if ($atts['show_pagination'] === 'true') {
            $this->render_pagination($jobs);
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Job search shortcode
     */
    public function shortcode_job_search($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => __('Search jobs...', 'job-killer'),
            'show_location' => 'true',
            'show_category' => 'true',
            'button_text' => __('Search', 'job-killer')
        ), $atts);
        
        ob_start();
        
        echo '<div class="job-killer-search-form">';
        echo '<form method="get" action="' . get_post_type_archive_link('job_listing') . '">';
        
        // Search input
        echo '<div class="search-field">';
        echo '<input type="text" name="search_keywords" placeholder="' . esc_attr($atts['placeholder']) . '" value="' . esc_attr(get_query_var('search_keywords')) . '">';
        echo '</div>';
        
        // Location filter
        if ($atts['show_location'] === 'true') {
            echo '<div class="location-field">';
            echo '<input type="text" name="search_location" placeholder="' . __('Location', 'job-killer') . '" value="' . esc_attr(get_query_var('search_location')) . '">';
            echo '</div>';
        }
        
        // Category filter
        if ($atts['show_category'] === 'true') {
            echo '<div class="category-field">';
            $this->render_category_dropdown();
            echo '</div>';
        }
        
        // Submit button
        echo '<div class="submit-field">';
        echo '<button type="submit">' . esc_html($atts['button_text']) . '</button>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Job stats shortcode
     */
    public function shortcode_job_stats($atts) {
        $atts = shortcode_atts(array(
            'show_total' => 'true',
            'show_today' => 'true',
            'show_week' => 'true',
            'show_companies' => 'true'
        ), $atts);
        
        $helper = new Job_Killer_Helper();
        $stats = $helper->get_import_stats();
        
        ob_start();
        
        echo '<div class="job-killer-stats">';
        
        if ($atts['show_total'] === 'true') {
            echo '<div class="stat-item">';
            echo '<span class="stat-number">' . number_format($stats['active_jobs']) . '</span>';
            echo '<span class="stat-label">' . __('Active Jobs', 'job-killer') . '</span>';
            echo '</div>';
        }
        
        if ($atts['show_today'] === 'true') {
            echo '<div class="stat-item">';
            echo '<span class="stat-number">' . number_format($stats['today_imports']) . '</span>';
            echo '<span class="stat-label">' . __('Jobs Today', 'job-killer') . '</span>';
            echo '</div>';
        }
        
        if ($atts['show_week'] === 'true') {
            echo '<div class="stat-item">';
            echo '<span class="stat-number">' . number_format($stats['week_imports']) . '</span>';
            echo '<span class="stat-label">' . __('Jobs This Week', 'job-killer') . '</span>';
            echo '</div>';
        }
        
        if ($atts['show_companies'] === 'true') {
            $companies_count = $this->get_companies_count();
            echo '<div class="stat-item">';
            echo '<span class="stat-number">' . number_format($companies_count) . '</span>';
            echo '<span class="stat-label">' . __('Companies', 'job-killer') . '</span>';
            echo '</div>';
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Recent jobs shortcode
     */
    public function shortcode_recent_jobs($atts) {
        $atts = shortcode_atts(array(
            'limit' => 5,
            'show_date' => 'true',
            'show_company' => 'true',
            'show_location' => 'true',
            'show_excerpt' => 'false'
        ), $atts);
        
        $jobs = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => $atts['limit'],
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_filled',
                    'value' => '1',
                    'compare' => '!='
                )
            )
        ));
        
        if (empty($jobs)) {
            return '<p>' . __('No recent jobs found.', 'job-killer') . '</p>';
        }
        
        ob_start();
        
        echo '<div class="job-killer-recent-jobs">';
        
        foreach ($jobs as $job) {
            echo '<div class="recent-job-item">';
            
            echo '<h4><a href="' . get_permalink($job->ID) . '">' . get_the_title($job->ID) . '</a></h4>';
            
            if ($atts['show_company'] === 'true') {
                $company = get_post_meta($job->ID, '_company_name', true);
                if (!empty($company)) {
                    echo '<div class="job-company">' . esc_html($company) . '</div>';
                }
            }
            
            if ($atts['show_location'] === 'true') {
                $location = get_post_meta($job->ID, '_job_location', true);
                if (!empty($location)) {
                    echo '<div class="job-location">' . esc_html($location) . '</div>';
                }
            }
            
            if ($atts['show_date'] === 'true') {
                echo '<div class="job-date">' . get_the_date('', $job->ID) . '</div>';
            }
            
            if ($atts['show_excerpt'] === 'true') {
                echo '<div class="job-excerpt">' . wp_trim_words(get_the_content('', false, $job->ID), 20) . '</div>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Get jobs based on parameters
     */
    private function get_jobs($args) {
        $query_args = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => $args['limit'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
            'meta_query' => array(
                array(
                    'key' => '_filled',
                    'value' => '1',
                    'compare' => '!='
                )
            )
        );
        
        // Add taxonomy filters
        $tax_query = array();
        
        if (!empty($args['category'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_listing_category',
                'field' => 'slug',
                'terms' => $args['category']
            );
        }
        
        if (!empty($args['type'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_listing_type',
                'field' => 'slug',
                'terms' => $args['type']
            );
        }
        
        if (!empty($args['region'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_listing_region',
                'field' => 'slug',
                'terms' => $args['region']
            );
        }
        
        if (!empty($tax_query)) {
            $query_args['tax_query'] = $tax_query;
        }
        
        // Add meta filters
        if (!empty($args['featured'])) {
            $query_args['meta_query'][] = array(
                'key' => '_featured',
                'value' => '1',
                'compare' => '='
            );
        }
        
        if (!empty($args['remote'])) {
            $query_args['meta_query'][] = array(
                'key' => '_remote_position',
                'value' => '1',
                'compare' => '='
            );
        }
        
        return new WP_Query($query_args);
    }
    
    /**
     * Render jobs list
     */
    private function render_jobs_list($jobs) {
        if (!$jobs->have_posts()) {
            echo '<p class="no-jobs-found">' . __('No jobs found.', 'job-killer') . '</p>';
            return;
        }
        
        echo '<div class="jobs-list">';
        
        while ($jobs->have_posts()) {
            $jobs->the_post();
            $this->render_job_item();
        }
        
        echo '</div>';
        
        wp_reset_postdata();
    }
    
    /**
     * Render single job item
     */
    private function render_job_item() {
        $job_id = get_the_ID();
        $company = get_post_meta($job_id, '_company_name', true);
        $location = get_post_meta($job_id, '_job_location', true);
        $salary = get_post_meta($job_id, '_job_salary', true);
        $remote = get_post_meta($job_id, '_remote_position', true);
        $featured = get_post_meta($job_id, '_featured', true);
        
        $classes = array('job-item');
        if ($featured) {
            $classes[] = 'featured';
        }
        if ($remote) {
            $classes[] = 'remote';
        }
        
        echo '<div class="' . implode(' ', $classes) . '">';
        
        echo '<div class="job-header">';
        echo '<h3 class="job-title"><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>';
        
        if ($featured) {
            echo '<span class="featured-badge">' . __('Featured', 'job-killer') . '</span>';
        }
        
        if ($remote) {
            echo '<span class="remote-badge">' . __('Remote', 'job-killer') . '</span>';
        }
        
        echo '</div>';
        
        echo '<div class="job-meta">';
        
        if (!empty($company)) {
            echo '<span class="job-company">' . esc_html($company) . '</span>';
        }
        
        if (!empty($location)) {
            echo '<span class="job-location">' . esc_html($location) . '</span>';
        }
        
        if (!empty($salary)) {
            echo '<span class="job-salary">' . esc_html($salary) . '</span>';
        }
        
        echo '<span class="job-date">' . get_the_date() . '</span>';
        
        echo '</div>';
        
        echo '<div class="job-excerpt">';
        echo wp_trim_words(get_the_excerpt(), 30);
        echo '</div>';
        
        // Job categories and types
        $categories = get_the_terms($job_id, 'job_listing_category');
        $types = get_the_terms($job_id, 'job_listing_type');
        
        if ($categories || $types) {
            echo '<div class="job-terms">';
            
            if ($categories) {
                echo '<div class="job-categories">';
                foreach ($categories as $category) {
                    echo '<span class="job-category">' . esc_html($category->name) . '</span>';
                }
                echo '</div>';
            }
            
            if ($types) {
                echo '<div class="job-types">';
                foreach ($types as $type) {
                    echo '<span class="job-type">' . esc_html($type->name) . '</span>';
                }
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render search form
     */
    private function render_search_form() {
        echo '<div class="job-search-form">';
        echo '<input type="text" id="job-search-keywords" placeholder="' . __('Search jobs...', 'job-killer') . '">';
        echo '<input type="text" id="job-search-location" placeholder="' . __('Location', 'job-killer') . '">';
        echo '<button type="button" id="job-search-submit">' . __('Search', 'job-killer') . '</button>';
        echo '</div>';
    }
    
    /**
     * Render filters
     */
    private function render_filters() {
        echo '<div class="job-filters">';
        
        // Category filter
        echo '<select id="job-filter-category">';
        echo '<option value="">' . __('All Categories', 'job-killer') . '</option>';
        $categories = get_terms(array('taxonomy' => 'job_listing_category', 'hide_empty' => true));
        foreach ($categories as $category) {
            echo '<option value="' . esc_attr($category->slug) . '">' . esc_html($category->name) . '</option>';
        }
        echo '</select>';
        
        // Type filter
        echo '<select id="job-filter-type">';
        echo '<option value="">' . __('All Types', 'job-killer') . '</option>';
        $types = get_terms(array('taxonomy' => 'job_listing_type', 'hide_empty' => true));
        foreach ($types as $type) {
            echo '<option value="' . esc_attr($type->slug) . '">' . esc_html($type->name) . '</option>';
        }
        echo '</select>';
        
        // Region filter
        echo '<select id="job-filter-region">';
        echo '<option value="">' . __('All Regions', 'job-killer') . '</option>';
        $regions = get_terms(array('taxonomy' => 'job_listing_region', 'hide_empty' => true));
        foreach ($regions as $region) {
            echo '<option value="' . esc_attr($region->slug) . '">' . esc_html($region->name) . '</option>';
        }
        echo '</select>';
        
        // Remote filter
        echo '<label><input type="checkbox" id="job-filter-remote"> ' . __('Remote Only', 'job-killer') . '</label>';
        
        echo '</div>';
    }
    
    /**
     * Render category dropdown
     */
    private function render_category_dropdown() {
        echo '<select name="search_category">';
        echo '<option value="">' . __('All Categories', 'job-killer') . '</option>';
        
        $categories = get_terms(array(
            'taxonomy' => 'job_listing_category',
            'hide_empty' => true
        ));
        
        foreach ($categories as $category) {
            $selected = selected(get_query_var('search_category'), $category->slug, false);
            echo '<option value="' . esc_attr($category->slug) . '"' . $selected . '>' . esc_html($category->name) . '</option>';
        }
        
        echo '</select>';
    }
    
    /**
     * Render pagination
     */
    private function render_pagination($jobs) {
        if ($jobs->max_num_pages <= 1) {
            return;
        }
        
        echo '<div class="job-pagination">';
        
        $pagination_args = array(
            'total' => $jobs->max_num_pages,
            'current' => max(1, get_query_var('paged')),
            'prev_text' => __('&laquo; Previous', 'job-killer'),
            'next_text' => __('Next &raquo;', 'job-killer')
        );
        
        echo paginate_links($pagination_args);
        
        echo '</div>';
    }
    
    /**
     * Get companies count
     */
    private function get_companies_count() {
        global $wpdb;
        
        return $wpdb->get_var("
            SELECT COUNT(DISTINCT meta_value) 
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_company_name' 
            AND pm.meta_value != ''
            AND p.post_type = 'job_listing'
            AND p.post_status = 'publish'
        ");
    }
    
    /**
     * AJAX search jobs
     */
    public function ajax_search_jobs() {
        check_ajax_referer('job_killer_nonce', 'nonce');
        
        $keywords = sanitize_text_field($_POST['keywords'] ?? '');
        $location = sanitize_text_field($_POST['location'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? '');
        $region = sanitize_text_field($_POST['region'] ?? '');
        $remote = !empty($_POST['remote']);
        
        $query_args = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'meta_query' => array(
                array(
                    'key' => '_filled',
                    'value' => '1',
                    'compare' => '!='
                )
            )
        );
        
        // Keywords search
        if (!empty($keywords)) {
            $query_args['s'] = $keywords;
        }
        
        // Location search
        if (!empty($location)) {
            $query_args['meta_query'][] = array(
                'key' => '_job_location',
                'value' => $location,
                'compare' => 'LIKE'
            );
        }
        
        // Taxonomy filters
        $tax_query = array();
        
        if (!empty($category)) {
            $tax_query[] = array(
                'taxonomy' => 'job_listing_category',
                'field' => 'slug',
                'terms' => $category
            );
        }
        
        if (!empty($type)) {
            $tax_query[] = array(
                'taxonomy' => 'job_listing_type',
                'field' => 'slug',
                'terms' => $type
            );
        }
        
        if (!empty($region)) {
            $tax_query[] = array(
                'taxonomy' => 'job_listing_region',
                'field' => 'slug',
                'terms' => $region
            );
        }
        
        if (!empty($tax_query)) {
            $query_args['tax_query'] = $tax_query;
        }
        
        // Remote filter
        if ($remote) {
            $query_args['meta_query'][] = array(
                'key' => '_remote_position',
                'value' => '1',
                'compare' => '='
            );
        }
        
        $jobs = new WP_Query($query_args);
        
        ob_start();
        $this->render_jobs_list($jobs);
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'found' => $jobs->found_posts
        ));
    }
    
    /**
     * AJAX load more jobs
     */
    public function ajax_load_more_jobs() {
        check_ajax_referer('job_killer_nonce', 'nonce');
        
        $page = intval($_POST['page'] ?? 1);
        $filters = $_POST['filters'] ?? array();
        
        // Build query based on filters
        $query_args = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'paged' => $page,
            'meta_query' => array(
                array(
                    'key' => '_filled',
                    'value' => '1',
                    'compare' => '!='
                )
            )
        );
        
        // Apply filters (similar to search function)
        // ... filter logic here ...
        
        $jobs = new WP_Query($query_args);
        
        ob_start();
        
        while ($jobs->have_posts()) {
            $jobs->the_post();
            $this->render_job_item();
        }
        
        $html = ob_get_clean();
        wp_reset_postdata();
        
        wp_send_json_success(array(
            'html' => $html,
            'has_more' => $page < $jobs->max_num_pages
        ));
    }
}