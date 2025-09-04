<?php
/**
 * Job Killer Widgets Class
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register and handle widgets
 */
class Job_Killer_Widgets {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('widgets_init', array($this, 'register_widgets'));
    }
    
    /**
     * Register widgets
     */
    public function register_widgets() {
        register_widget('Job_Killer_Recent_Jobs_Widget');
        register_widget('Job_Killer_Job_Search_Widget');
        register_widget('Job_Killer_Job_Stats_Widget');
        register_widget('Job_Killer_Job_Categories_Widget');
    }
}

/**
 * Recent Jobs Widget
 */
class Job_Killer_Recent_Jobs_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'job_killer_recent_jobs',
            __('Job Killer: Recent Jobs', 'job-killer'),
            array('description' => __('Display recent job listings', 'job-killer'))
        );
    }
    
    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Recent Jobs', 'job-killer');
        $number = !empty($instance['number']) ? absint($instance['number']) : 5;
        $show_date = isset($instance['show_date']) ? $instance['show_date'] : true;
        $show_company = isset($instance['show_company']) ? $instance['show_company'] : true;
        
        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }
        
        $jobs = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => $number,
            'meta_query' => array(
                array(
                    'key' => '_filled',
                    'value' => '1',
                    'compare' => '!='
                )
            )
        ));
        
        if ($jobs) {
            echo '<ul class="job-killer-recent-jobs-widget">';
            foreach ($jobs as $job) {
                echo '<li>';
                echo '<a href="' . get_permalink($job->ID) . '">' . get_the_title($job->ID) . '</a>';
                
                if ($show_company) {
                    $company = get_post_meta($job->ID, '_company_name', true);
                    if ($company) {
                        echo '<div class="job-company">' . esc_html($company) . '</div>';
                    }
                }
                
                if ($show_date) {
                    echo '<div class="job-date">' . get_the_date('', $job->ID) . '</div>';
                }
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . __('No recent jobs found.', 'job-killer') . '</p>';
        }
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Recent Jobs', 'job-killer');
        $number = !empty($instance['number']) ? absint($instance['number']) : 5;
        $show_date = isset($instance['show_date']) ? $instance['show_date'] : true;
        $show_company = isset($instance['show_company']) ? $instance['show_company'] : true;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title:', 'job-killer'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('number')); ?>"><?php _e('Number of jobs to show:', 'job-killer'); ?></label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('number')); ?>" name="<?php echo esc_attr($this->get_field_name('number')); ?>" type="number" step="1" min="1" value="<?php echo esc_attr($number); ?>" size="3">
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_date); ?> id="<?php echo esc_attr($this->get_field_id('show_date')); ?>" name="<?php echo esc_attr($this->get_field_name('show_date')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_date')); ?>"><?php _e('Show date', 'job-killer'); ?></label>
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_company); ?> id="<?php echo esc_attr($this->get_field_id('show_company')); ?>" name="<?php echo esc_attr($this->get_field_name('show_company')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_company')); ?>"><?php _e('Show company', 'job-killer'); ?></label>
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['number'] = (!empty($new_instance['number'])) ? absint($new_instance['number']) : 5;
        $instance['show_date'] = !empty($new_instance['show_date']);
        $instance['show_company'] = !empty($new_instance['show_company']);
        
        return $instance;
    }
}

/**
 * Job Search Widget
 */
class Job_Killer_Job_Search_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'job_killer_job_search',
            __('Job Killer: Job Search', 'job-killer'),
            array('description' => __('Display job search form', 'job-killer'))
        );
    }
    
    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Search Jobs', 'job-killer');
        $show_location = isset($instance['show_location']) ? $instance['show_location'] : true;
        $show_category = isset($instance['show_category']) ? $instance['show_category'] : true;
        
        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }
        
        echo '<form method="get" action="' . esc_url(get_post_type_archive_link('job_listing')) . '" class="job-killer-search-widget">';
        
        echo '<div class="search-field">';
        echo '<input type="text" name="search_keywords" placeholder="' . esc_attr__('Search jobs...', 'job-killer') . '" value="' . esc_attr(get_query_var('search_keywords')) . '">';
        echo '</div>';
        
        if ($show_location) {
            echo '<div class="location-field">';
            echo '<input type="text" name="search_location" placeholder="' . esc_attr__('Location', 'job-killer') . '" value="' . esc_attr(get_query_var('search_location')) . '">';
            echo '</div>';
        }
        
        if ($show_category) {
            echo '<div class="category-field">';
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
            echo '</div>';
        }
        
        echo '<div class="submit-field">';
        echo '<button type="submit">' . __('Search', 'job-killer') . '</button>';
        echo '</div>';
        
        echo '</form>';
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Search Jobs', 'job-killer');
        $show_location = isset($instance['show_location']) ? $instance['show_location'] : true;
        $show_category = isset($instance['show_category']) ? $instance['show_category'] : true;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title:', 'job-killer'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_location); ?> id="<?php echo esc_attr($this->get_field_id('show_location')); ?>" name="<?php echo esc_attr($this->get_field_name('show_location')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_location')); ?>"><?php _e('Show location field', 'job-killer'); ?></label>
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_category); ?> id="<?php echo esc_attr($this->get_field_id('show_category')); ?>" name="<?php echo esc_attr($this->get_field_name('show_category')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_category')); ?>"><?php _e('Show category field', 'job-killer'); ?></label>
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['show_location'] = !empty($new_instance['show_location']);
        $instance['show_category'] = !empty($new_instance['show_category']);
        
        return $instance;
    }
}

/**
 * Job Stats Widget
 */
class Job_Killer_Job_Stats_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'job_killer_job_stats',
            __('Job Killer: Job Statistics', 'job-killer'),
            array('description' => __('Display job statistics', 'job-killer'))
        );
    }
    
    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Job Statistics', 'job-killer');
        
        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }
        
        $helper = new Job_Killer_Helper();
        $stats = $helper->get_import_stats();
        
        echo '<div class="job-killer-stats-widget">';
        
        echo '<div class="stat-item">';
        echo '<span class="stat-number">' . number_format($stats['active_jobs']) . '</span>';
        echo '<span class="stat-label">' . __('Active Jobs', 'job-killer') . '</span>';
        echo '</div>';
        
        echo '<div class="stat-item">';
        echo '<span class="stat-number">' . number_format($stats['today_imports']) . '</span>';
        echo '<span class="stat-label">' . __('Jobs Today', 'job-killer') . '</span>';
        echo '</div>';
        
        echo '<div class="stat-item">';
        echo '<span class="stat-number">' . number_format($stats['week_imports']) . '</span>';
        echo '<span class="stat-label">' . __('This Week', 'job-killer') . '</span>';
        echo '</div>';
        
        echo '</div>';
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Job Statistics', 'job-killer');
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title:', 'job-killer'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        
        return $instance;
    }
}

/**
 * Job Categories Widget
 */
class Job_Killer_Job_Categories_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'job_killer_job_categories',
            __('Job Killer: Job Categories', 'job-killer'),
            array('description' => __('Display job categories', 'job-killer'))
        );
    }
    
    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Job Categories', 'job-killer');
        $show_count = isset($instance['show_count']) ? $instance['show_count'] : true;
        $hierarchical = isset($instance['hierarchical']) ? $instance['hierarchical'] : true;
        
        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }
        
        $categories = get_terms(array(
            'taxonomy' => 'job_listing_category',
            'hide_empty' => true,
            'hierarchical' => $hierarchical
        ));
        
        if ($categories && !is_wp_error($categories)) {
            echo '<ul class="job-killer-categories-widget">';
            foreach ($categories as $category) {
                echo '<li>';
                echo '<a href="' . esc_url(get_term_link($category)) . '">' . esc_html($category->name) . '</a>';
                
                if ($show_count) {
                    echo ' <span class="count">(' . $category->count . ')</span>';
                }
                
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . __('No categories found.', 'job-killer') . '</p>';
        }
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Job Categories', 'job-killer');
        $show_count = isset($instance['show_count']) ? $instance['show_count'] : true;
        $hierarchical = isset($instance['hierarchical']) ? $instance['hierarchical'] : true;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title:', 'job-killer'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_count); ?> id="<?php echo esc_attr($this->get_field_id('show_count')); ?>" name="<?php echo esc_attr($this->get_field_name('show_count')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_count')); ?>"><?php _e('Show job counts', 'job-killer'); ?></label>
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($hierarchical); ?> id="<?php echo esc_attr($this->get_field_id('hierarchical')); ?>" name="<?php echo esc_attr($this->get_field_name('hierarchical')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('hierarchical')); ?>"><?php _e('Show hierarchy', 'job-killer'); ?></label>
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['show_count'] = !empty($new_instance['show_count']);
        $instance['hierarchical'] = !empty($new_instance['hierarchical']);
        
        return $instance;
    }
}

// Initialize widgets
new Job_Killer_Widgets();