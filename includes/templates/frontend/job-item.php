<?php
/**
 * Frontend Job Item Template
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

$job_id = get_the_ID();
$company = get_post_meta($job_id, '_company_name', true);
$location = get_post_meta($job_id, '_job_location', true);
$salary = get_post_meta($job_id, '_job_salary', true);
$remote = get_post_meta($job_id, '_remote_position', true);
$featured = get_post_meta($job_id, '_featured', true);
$application_url = get_post_meta($job_id, '_application', true);
$job_expires = get_post_meta($job_id, '_job_expires', true);

$classes = array('job-item');
if ($featured) {
    $classes[] = 'featured';
}
if ($remote) {
    $classes[] = 'remote';
}

// Check if job is expiring soon (within 7 days)
$expires_soon = false;
if (!empty($job_expires)) {
    $expires_timestamp = strtotime($job_expires);
    $seven_days = 7 * 24 * 60 * 60;
    if ($expires_timestamp - time() <= $seven_days && $expires_timestamp > time()) {
        $expires_soon = true;
        $classes[] = 'expires-soon';
    }
}
?>

<article class="<?php echo implode(' ', $classes); ?>" data-job-id="<?php echo esc_attr($job_id); ?>">
    <div class="job-header">
        <div class="job-title-section">
            <h3 class="job-title">
                <a href="<?php the_permalink(); ?>" title="<?php echo esc_attr(get_the_title()); ?>">
                    <?php the_title(); ?>
                </a>
            </h3>
            
            <div class="job-badges">
                <?php if ($featured): ?>
                <span class="featured-badge" title="<?php esc_attr_e('Featured Job', 'job-killer'); ?>">
                    <?php _e('Featured', 'job-killer'); ?>
                </span>
                <?php endif; ?>
                
                <?php if ($remote): ?>
                <span class="remote-badge" title="<?php esc_attr_e('Remote Work Available', 'job-killer'); ?>">
                    <?php _e('Remote', 'job-killer'); ?>
                </span>
                <?php endif; ?>
                
                <?php if ($expires_soon): ?>
                <span class="expires-badge" title="<?php esc_attr_e('Expires Soon', 'job-killer'); ?>">
                    <?php _e('Expires Soon', 'job-killer'); ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="job-actions">
            <?php if (!empty($application_url)): ?>
            <a href="<?php echo esc_url($application_url); ?>" 
               class="job-application-link job-killer-btn job-killer-btn-primary" 
               target="_blank" 
               rel="noopener"
               data-job-id="<?php echo esc_attr($job_id); ?>">
                <?php _e('Apply Now', 'job-killer'); ?>
                <span class="external-link-icon">↗</span>
            </a>
            <?php endif; ?>
            
            <button class="job-save-btn" data-job-id="<?php echo esc_attr($job_id); ?>" title="<?php esc_attr_e('Save Job', 'job-killer'); ?>">
                <span class="dashicons dashicons-heart"></span>
            </button>
        </div>
    </div>
    
    <div class="job-meta">
        <?php if (!empty($company)): ?>
        <span class="job-company">
            <span class="meta-icon dashicons dashicons-building"></span>
            <?php echo esc_html($company); ?>
        </span>
        <?php endif; ?>
        
        <?php if (!empty($location)): ?>
        <span class="job-location">
            <span class="meta-icon dashicons dashicons-location"></span>
            <?php echo esc_html($location); ?>
        </span>
        <?php endif; ?>
        
        <?php if (!empty($salary)): ?>
        <span class="job-salary">
            <span class="meta-icon dashicons dashicons-money-alt"></span>
            <?php echo esc_html($salary); ?>
        </span>
        <?php endif; ?>
        
        <span class="job-date">
            <span class="meta-icon dashicons dashicons-calendar-alt"></span>
            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>" title="<?php echo esc_attr(get_the_date()); ?>">
                <?php echo human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ' . __('ago', 'job-killer'); ?>
            </time>
        </span>
    </div>
    
    <div class="job-excerpt">
        <?php 
        $excerpt = get_the_excerpt();
        if (empty($excerpt)) {
            $excerpt = wp_trim_words(get_the_content(), 30, '...');
        }
        echo wp_kses_post($excerpt);
        ?>
    </div>
    
    <div class="job-terms">
        <?php
        // Job categories
        $categories = get_the_terms($job_id, 'job_listing_category');
        if ($categories && !is_wp_error($categories)):
        ?>
        <div class="job-categories">
            <span class="terms-label"><?php _e('Categories:', 'job-killer'); ?></span>
            <?php foreach ($categories as $category): ?>
            <a href="<?php echo esc_url(get_term_link($category)); ?>" 
               class="job-category" 
               title="<?php echo esc_attr(sprintf(__('View all jobs in %s', 'job-killer'), $category->name)); ?>">
                <?php echo esc_html($category->name); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php
        // Job types
        $types = get_the_terms($job_id, 'job_listing_type');
        if ($types && !is_wp_error($types)):
        ?>
        <div class="job-types">
            <span class="terms-label"><?php _e('Type:', 'job-killer'); ?></span>
            <?php foreach ($types as $type): ?>
            <a href="<?php echo esc_url(get_term_link($type)); ?>" 
               class="job-type"
               title="<?php echo esc_attr(sprintf(__('View all %s jobs', 'job-killer'), $type->name)); ?>">
                <?php echo esc_html($type->name); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($job_expires)): ?>
    <div class="job-expires">
        <span class="expires-label"><?php _e('Expires:', 'job-killer'); ?></span>
        <time datetime="<?php echo esc_attr(date('c', strtotime($job_expires))); ?>">
            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($job_expires))); ?>
        </time>
    </div>
    <?php endif; ?>
</article>