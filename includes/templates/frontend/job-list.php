<?php
/**
 * Frontend Job List Template
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="job-killer-list-wrapper" id="job-killer-list-<?php echo esc_attr($instance_id ?? uniqid()); ?>">
    
    <?php if ($show_search || $show_filters): ?>
    <div class="job-killer-filters">
        <?php if ($show_search): ?>
        <div class="job-killer-search-form">
            <form method="get" action="<?php echo esc_url(get_post_type_archive_link('job_listing')); ?>">
                <div class="search-field">
                    <input type="text" 
                           id="job-search-keywords" 
                           name="search_keywords" 
                           placeholder="<?php esc_attr_e('Search jobs...', 'job-killer'); ?>" 
                           value="<?php echo esc_attr(get_query_var('search_keywords')); ?>">
                </div>
                
                <div class="location-field">
                    <input type="text" 
                           id="job-search-location" 
                           name="search_location" 
                           placeholder="<?php esc_attr_e('Location', 'job-killer'); ?>" 
                           value="<?php echo esc_attr(get_query_var('search_location')); ?>">
                </div>
                
                <div class="category-field">
                    <select name="search_category" id="job-filter-category">
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
                
                <div class="submit-field">
                    <button type="submit" id="job-search-submit"><?php _e('Search', 'job-killer'); ?></button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if ($show_filters): ?>
        <div class="job-killer-filters">
            <select id="job-filter-type">
                <option value=""><?php _e('All Types', 'job-killer'); ?></option>
                <?php
                $types = get_terms(array('taxonomy' => 'job_listing_type', 'hide_empty' => true));
                foreach ($types as $type) {
                    echo '<option value="' . esc_attr($type->slug) . '">' . esc_html($type->name) . '</option>';
                }
                ?>
            </select>
            
            <select id="job-filter-region">
                <option value=""><?php _e('All Regions', 'job-killer'); ?></option>
                <?php
                $regions = get_terms(array('taxonomy' => 'job_listing_region', 'hide_empty' => true));
                foreach ($regions as $region) {
                    echo '<option value="' . esc_attr($region->slug) . '">' . esc_html($region->name) . '</option>';
                }
                ?>
            </select>
            
            <label class="remote-filter">
                <input type="checkbox" id="job-filter-remote">
                <?php _e('Remote Only', 'job-killer'); ?>
            </label>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Results Count -->
    <div class="job-killer-results-info">
        <span class="job-killer-results-count">
            <?php
            if ($jobs->found_posts === 1) {
                _e('1 job found', 'job-killer');
            } else {
                printf(__('%d jobs found', 'job-killer'), $jobs->found_posts);
            }
            ?>
        </span>
        
        <div class="job-killer-sort">
            <select id="job-killer-sort">
                <option value="date-desc"><?php _e('Newest First', 'job-killer'); ?></option>
                <option value="date-asc"><?php _e('Oldest First', 'job-killer'); ?></option>
                <option value="title-asc"><?php _e('Title A-Z', 'job-killer'); ?></option>
                <option value="title-desc"><?php _e('Title Z-A', 'job-killer'); ?></option>
                <option value="company-asc"><?php _e('Company A-Z', 'job-killer'); ?></option>
            </select>
        </div>
    </div>
    
    <!-- Jobs List -->
    <div class="job-killer-jobs" id="job-killer-jobs">
        <?php if ($jobs->have_posts()): ?>
        <div class="jobs-list">
            <?php while ($jobs->have_posts()): $jobs->the_post(); ?>
            <?php include JOB_KILLER_PLUGIN_DIR . 'includes/templates/frontend/job-item.php'; ?>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="no-jobs-found">
            <div class="no-jobs-icon">
                <span class="dashicons dashicons-search"></span>
            </div>
            <h3><?php _e('No jobs found', 'job-killer'); ?></h3>
            <p><?php _e('Try adjusting your search criteria or check back later for new listings.', 'job-killer'); ?></p>
            
            <?php if (!empty(get_query_var('search_keywords')) || !empty(get_query_var('search_location'))): ?>
            <a href="<?php echo esc_url(get_post_type_archive_link('job_listing')); ?>" class="job-killer-btn job-killer-btn-secondary">
                <?php _e('View All Jobs', 'job-killer'); ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Load More / Pagination -->
    <?php if ($show_pagination && $jobs->max_num_pages > 1): ?>
    <div class="job-killer-pagination-wrapper">
        <?php if ($pagination_type === 'load_more'): ?>
        <button class="job-killer-load-more job-killer-btn job-killer-btn-primary" 
                data-page="2" 
                data-max-pages="<?php echo esc_attr($jobs->max_num_pages); ?>"
                <?php echo $jobs->max_num_pages <= 1 ? 'style="display:none;"' : ''; ?>>
            <?php _e('Load More Jobs', 'job-killer'); ?>
        </button>
        <?php else: ?>
        <div class="job-pagination">
            <?php
            $pagination_args = array(
                'total' => $jobs->max_num_pages,
                'current' => max(1, get_query_var('paged')),
                'prev_text' => __('&laquo; Previous', 'job-killer'),
                'next_text' => __('Next &raquo;', 'job-killer'),
                'type' => 'list'
            );
            echo paginate_links($pagination_args);
            ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
</div>

<?php wp_reset_postdata(); ?>