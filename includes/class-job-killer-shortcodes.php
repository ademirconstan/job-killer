<?php
/**
 * Job Killer Shortcodes Class
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle shortcodes
 */
class Job_Killer_Shortcodes {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->register_shortcodes();
    }
    
    /**
     * Register shortcodes
     */
    private function register_shortcodes() {
        add_shortcode('job-killer-list', array($this, 'job_list_shortcode'));
        add_shortcode('job-killer-search', array($this, 'job_search_shortcode'));
        add_shortcode('job-killer-stats', array($this, 'job_stats_shortcode'));
        add_shortcode('job-killer-recent', array($this, 'recent_jobs_shortcode'));
        add_shortcode('job-killer-categories', array($this, 'job_categories_shortcode'));
        add_shortcode('job-killer-featured', array($this, 'featured_jobs_shortcode'));
    }
    
    /**
     * Job list shortcode
     */
    public function job_list_shortcode($atts) {
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
            'show_search' => 'true',
            'pagination_type' => 'numbers', // numbers, load_more, infinite
            'columns' => '1'
        ), $atts, 'job-killer-list');
        
        // Convert string booleans
        $show_filters = filter_var($atts['show_filters'], FILTER_VALIDATE_BOOLEAN);
        $show_pagination = filter_var($atts['show_pagination'], FILTER_VALIDATE_BOOLEAN);
        $show_search = filter_var($atts['show_search'], FILTER_VALIDATE_BOOLEAN);
        
        // Build query args
        $query_args = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['limit']),
            'orderby' => sanitize_text_field($atts['orderby']),
            'order' => strtoupper($atts['order']) === 'ASC' ? 'ASC' : 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_filled',
                    'value' => '1',
                    'compare' => '!='
                )
            )
        );
        
        // Add pagination
        if ($show_pagination) {
            $paged = get_query_var('paged') ? get_query_var('paged') : 1;
            $query_args['paged'] = $paged;
        }
        
        // Add taxonomy filters
        $tax_query = array();
        
        if (!empty($atts['category'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_listing_category',
                'field' => 'slug',
                'terms' => explode(',', $atts['category'])
            );
        }
        
        if (!empty($atts['type'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_listing_type',
                'field' => 'slug',
                'terms' => explode(',', $atts['type'])
            );
        }
        
        if (!empty($atts['region'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_listing_region',
                'field' => 'slug',
                'terms' => explode(',', $atts['region'])
            );
        }
        
        if (!empty($tax_query)) {
            $query_args['tax_query'] = $tax_query;
        }
        
        // Add meta filters
        if (!empty($atts['featured'])) {
            $query_args['meta_query'][] = array(
                'key' => '_featured',
                'value' => '1',
                'compare' => '='
            );
        }
        
        if (!empty($atts['remote'])) {
            $query_args['meta_query'][] = array(
                'key' => '_remote_position',
                'value' => '1',
                'compare' => '='
            );
        }
        
        // Execute query
        $jobs = new WP_Query($query_args);
        
        // Generate unique instance ID
        $instance_id = uniqid('jk_');
        
        // Start output buffering
        ob_start();
        
        // Include template
        include JOB_KILLER_PLUGIN_DIR . 'includes/templates/frontend/job-list.php';
        
        return ob_get_clean();
    }
    
    /**
     * Job search shortcode
     */
    public function job_search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => __('Search jobs...', 'job-killer'),
            'show_location' => 'true',
            'show_category' => 'true',
            'show_type' => 'false',
            'show_remote' => 'false',
            'button_text' => __('Search', 'job-killer'),
            'action_url' => '',
            'ajax' => 'false'
        ), $atts, 'job-killer-search');
        
        // Convert string booleans
        $show_location = filter_var($atts['show_location'], FILTER_VALIDATE_BOOLEAN);
        $show_category = filter_var($atts['show_category'], FILTER_VALIDATE_BOOLEAN);
        $show_type = filter_var($atts['show_type'], FILTER_VALIDATE_BOOLEAN);
        $show_remote = filter_var($atts['show_remote'], FILTER_VALIDATE_BOOLEAN);
        $ajax = filter_var($atts['ajax'], FILTER_VALIDATE_BOOLEAN);
        
        // Default action URL
        if (empty($atts['action_url'])) {
            $atts['action_url'] = get_post_type_archive_link('job_listing');
        }
        
        ob_start();
        ?>
        <div class="job-killer-search-form <?php echo $ajax ? 'ajax-search' : ''; ?>">
            <form method="get" action="<?php echo esc_url($atts['action_url']); ?>">
                <div class="search-fields">
                    <div class="search-field">
                        <input type="text" 
                               name="search_keywords" 
                               placeholder="<?php echo esc_attr($atts['placeholder']); ?>" 
                               value="<?php echo esc_attr(get_query_var('search_keywords')); ?>">
                    </div>
                    
                    <?php if ($show_location): ?>
                    <div class="location-field">
                        <input type="text" 
                               name="search_location" 
                               placeholder="<?php esc_attr_e('Location', 'job-killer'); ?>" 
                               value="<?php echo esc_attr(get_query_var('search_location')); ?>">
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($show_category): ?>
                    <div class="category-field">
                        <select name="search_category">
                            <option value=""><?php _e('All Categories', 'job-killer'); ?></option>
                            <?php
                            $categories = get_terms(array(
                                'taxonomy' => 'job_listing_category',
                                'hide_empty' => true
                            ));
                            foreach ($categories as $category) {
                                $selected = selected(get_query_var('search_category'), $category->slug, false);
                                echo '<option value="' . esc_attr($category->slug) . '"' . $selected . '>' . esc_html($category->name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($show_type): ?>
                    <div class="type-field">
                        <select name="search_type">
                            <option value=""><?php _e('All Types', 'job-killer'); ?></option>
                            <?php
                            $types = get_terms(array(
                                'taxonomy' => 'job_listing_type',
                                'hide_empty' => true
                            ));
                            foreach ($types as $type) {
                                $selected = selected(get_query_var('search_type'), $type->slug, false);
                                echo '<option value="' . esc_attr($type->slug) . '"' . $selected . '>' . esc_html($type->name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($show_remote): ?>
                    <div class="remote-field">
                        <label>
                            <input type="checkbox" 
                                   name="search_remote" 
                                   value="1" 
                                   <?php checked(get_query_var('search_remote'), '1'); ?>>
                            <?php _e('Remote Only', 'job-killer'); ?>
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="submit-field">
                    <button type="submit"><?php echo esc_html($atts['button_text']); ?></button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Job stats shortcode
     */
    public function job_stats_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_total' => 'true',
            'show_today' => 'true',
            'show_week' => 'true',
            'show_month' => 'false',
            'show_companies' => 'true',
            'show_remote' => 'false',
            'show_featured' => 'false',
            'layout' => 'grid', // grid, list, inline
            'columns' => '4'
        ), $atts, 'job-killer-stats');
        
        // Convert string booleans
        $show_total = filter_var($atts['show_total'], FILTER_VALIDATE_BOOLEAN);
        $show_today = filter_var($atts['show_today'], FILTER_VALIDATE_BOOLEAN);
        $show_week = filter_var($atts['show_week'], FILTER_VALIDATE_BOOLEAN);
        $show_month = filter_var($atts['show_month'], FILTER_VALIDATE_BOOLEAN);
        $show_companies = filter_var($atts['show_companies'], FILTER_VALIDATE_BOOLEAN);
        $show_remote = filter_var($atts['show_remote'], FILTER_VALIDATE_BOOLEAN);
        $show_featured = filter_var($atts['show_featured'], FILTER_VALIDATE_BOOLEAN);
        
        // Get cached stats
        $helper = new Job_Killer_Helper();
        $stats = Job_Killer_Cache::get_cached_stats();
        
        if (!$stats) {
            $stats = $helper->get_import_stats();
            Job_Killer_Cache::cache_stats($stats);
        }
        
        ob_start();
        ?>
        <div class="job-killer-stats layout-<?php echo esc_attr($atts['layout']); ?> columns-<?php echo esc_attr($atts['columns']); ?>">
            <?php if ($show_total): ?>
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($stats['active_jobs']); ?></span>
                <span class="stat-label"><?php _e('Active Jobs', 'job-killer'); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($show_today): ?>
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($stats['today_imports']); ?></span>
                <span class="stat-label"><?php _e('Jobs Today', 'job-killer'); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($show_week): ?>
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($stats['week_imports']); ?></span>
                <span class="stat-label"><?php _e('Jobs This Week', 'job-killer'); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($show_month): ?>
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($stats['month_imports']); ?></span>
                <span class="stat-label"><?php _e('Jobs This Month', 'job-killer'); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($show_companies): ?>
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($this->get_companies_count()); ?></span>
                <span class="stat-label"><?php _e('Companies', 'job-killer'); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($show_remote): ?>
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($this->get_remote_jobs_count()); ?></span>
                <span class="stat-label"><?php _e('Remote Jobs', 'job-killer'); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($show_featured): ?>
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($this->get_featured_jobs_count()); ?></span>
                <span class="stat-label"><?php _e('Featured Jobs', 'job-killer'); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Recent jobs shortcode
     */
    public function recent_jobs_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 5,
            'show_date' => 'true',
            'show_company' => 'true',
            'show_location' => 'true',
            'show_excerpt' => 'false',
            'show_featured' => 'true',
            'layout' => 'list', // list, grid
            'columns' => '1'
        ), $atts, 'job-killer-recent');
        
        // Convert string booleans
        $show_date = filter_var($atts['show_date'], FILTER_VALIDATE_BOOLEAN);
        $show_company = filter_var($atts['show_company'], FILTER_VALIDATE_BOOLEAN);
        $show_location = filter_var($atts['show_location'], FILTER_VALIDATE_BOOLEAN);
        $show_excerpt = filter_var($atts['show_excerpt'], FILTER_VALIDATE_BOOLEAN);
        $show_featured = filter_var($atts['show_featured'], FILTER_VALIDATE_BOOLEAN);
        
        $jobs = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['limit']),
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
        ?>
        <div class="job-killer-recent-jobs layout-<?php echo esc_attr($atts['layout']); ?> columns-<?php echo esc_attr($atts['columns']); ?>">
            <?php foreach ($jobs as $job): ?>
            <div class="recent-job-item">
                <h4>
                    <a href="<?php echo get_permalink($job->ID); ?>">
                        <?php echo get_the_title($job->ID); ?>
                        <?php if ($show_featured && get_post_meta($job->ID, '_featured', true)): ?>
                        <span class="featured-badge"><?php _e('Featured', 'job-killer'); ?></span>
                        <?php endif; ?>
                    </a>
                </h4>
                
                <?php if ($show_company): ?>
                <?php $company = get_post_meta($job->ID, '_company_name', true); ?>
                <?php if (!empty($company)): ?>
                <div class="job-company"><?php echo esc_html($company); ?></div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($show_location): ?>
                <?php $location = get_post_meta($job->ID, '_job_location', true); ?>
                <?php if (!empty($location)): ?>
                <div class="job-location"><?php echo esc_html($location); ?></div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($show_date): ?>
                <div class="job-date"><?php echo get_the_date('', $job->ID); ?></div>
                <?php endif; ?>
                
                <?php if ($show_excerpt): ?>
                <div class="job-excerpt"><?php echo wp_trim_words(get_the_content('', false, $job->ID), 20); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Job categories shortcode
     */
    public function job_categories_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_count' => 'true',
            'hide_empty' => 'true',
            'orderby' => 'name',
            'order' => 'ASC',
            'include' => '',
            'exclude' => '',
            'layout' => 'list', // list, grid, dropdown
            'columns' => '3'
        ), $atts, 'job-killer-categories');
        
        // Convert string booleans
        $show_count = filter_var($atts['show_count'], FILTER_VALIDATE_BOOLEAN);
        $hide_empty = filter_var($atts['hide_empty'], FILTER_VALIDATE_BOOLEAN);
        
        $args = array(
            'taxonomy' => 'job_listing_category',
            'hide_empty' => $hide_empty,
            'orderby' => $atts['orderby'],
            'order' => strtoupper($atts['order'])
        );
        
        if (!empty($atts['include'])) {
            $args['include'] = explode(',', $atts['include']);
        }
        
        if (!empty($atts['exclude'])) {
            $args['exclude'] = explode(',', $atts['exclude']);
        }
        
        $categories = get_terms($args);
        
        if (empty($categories) || is_wp_error($categories)) {
            return '<p>' . __('No categories found.', 'job-killer') . '</p>';
        }
        
        ob_start();
        
        if ($atts['layout'] === 'dropdown') {
            ?>
            <select class="job-killer-categories-dropdown" onchange="if(this.value) window.location.href=this.value">
                <option value=""><?php _e('Select Category', 'job-killer'); ?></option>
                <?php foreach ($categories as $category): ?>
                <option value="<?php echo esc_url(get_term_link($category)); ?>">
                    <?php echo esc_html($category->name); ?>
                    <?php if ($show_count): ?>
                    (<?php echo $category->count; ?>)
                    <?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php
        } else {
            ?>
            <div class="job-killer-categories layout-<?php echo esc_attr($atts['layout']); ?> columns-<?php echo esc_attr($atts['columns']); ?>">
                <?php foreach ($categories as $category): ?>
                <div class="category-item">
                    <a href="<?php echo esc_url(get_term_link($category)); ?>">
                        <?php echo esc_html($category->name); ?>
                        <?php if ($show_count): ?>
                        <span class="count">(<?php echo $category->count; ?>)</span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php
        }
        
        return ob_get_clean();
    }
    
    /**
     * Featured jobs shortcode
     */
    public function featured_jobs_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 5,
            'show_company' => 'true',
            'show_location' => 'true',
            'show_date' => 'true',
            'show_excerpt' => 'false',
            'layout' => 'list',
            'columns' => '1'
        ), $atts, 'job-killer-featured');
        
        // Convert string booleans
        $show_company = filter_var($atts['show_company'], FILTER_VALIDATE_BOOLEAN);
        $show_location = filter_var($atts['show_location'], FILTER_VALIDATE_BOOLEAN);
        $show_date = filter_var($atts['show_date'], FILTER_VALIDATE_BOOLEAN);
        $show_excerpt = filter_var($atts['show_excerpt'], FILTER_VALIDATE_BOOLEAN);
        
        $jobs = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['limit']),
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_filled',
                    'value' => '1',
                    'compare' => '!='
                ),
                array(
                    'key' => '_featured',
                    'value' => '1',
                    'compare' => '='
                )
            )
        ));
        
        if (empty($jobs)) {
            return '<p>' . __('No featured jobs found.', 'job-killer') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="job-killer-featured-jobs layout-<?php echo esc_attr($atts['layout']); ?> columns-<?php echo esc_attr($atts['columns']); ?>">
            <?php foreach ($jobs as $job): ?>
            <div class="featured-job-item">
                <h4>
                    <a href="<?php echo get_permalink($job->ID); ?>">
                        <?php echo get_the_title($job->ID); ?>
                        <span class="featured-badge"><?php _e('Featured', 'job-killer'); ?></span>
                    </a>
                </h4>
                
                <?php if ($show_company): ?>
                <?php $company = get_post_meta($job->ID, '_company_name', true); ?>
                <?php if (!empty($company)): ?>
                <div class="job-company"><?php echo esc_html($company); ?></div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($show_location): ?>
                <?php $location = get_post_meta($job->ID, '_job_location', true); ?>
                <?php if (!empty($location)): ?>
                <div class="job-location"><?php echo esc_html($location); ?></div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($show_date): ?>
                <div class="job-date"><?php echo get_the_date('', $job->ID); ?></div>
                <?php endif; ?>
                
                <?php if ($show_excerpt): ?>
                <div class="job-excerpt"><?php echo wp_trim_words(get_the_content('', false, $job->ID), 20); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
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
     * Get remote jobs count
     */
    private function get_remote_jobs_count() {
        global $wpdb;
        
        return $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_remote_position'
            AND pm.meta_value = '1'
        ");
    }
    
    /**
     * Get featured jobs count
     */
    private function get_featured_jobs_count() {
        global $wpdb;
        
        return $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_featured'
            AND pm.meta_value = '1'
        ");
    }
}

// Initialize shortcodes
new Job_Killer_Shortcodes();