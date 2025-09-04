<?php
/**
 * Single Job Template
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
$company_website = get_post_meta($job_id, '_company_website', true);
$company_logo = get_post_meta($job_id, '_company_logo', true);
?>

<article class="job-killer-single-job">
    <header class="job-header">
        <div class="job-header-content">
            <div class="job-title-section">
                <h1 class="job-title"><?php the_title(); ?></h1>
                
                <div class="job-badges">
                    <?php if ($featured): ?>
                    <span class="featured-badge"><?php _e('Featured', 'job-killer'); ?></span>
                    <?php endif; ?>
                    
                    <?php if ($remote): ?>
                    <span class="remote-badge"><?php _e('Remote', 'job-killer'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="job-actions">
                <?php if (!empty($application_url)): ?>
                <a href="<?php echo esc_url($application_url); ?>" 
                   class="job-application-link job-killer-btn job-killer-btn-primary job-killer-btn-large" 
                   target="_blank" 
                   rel="noopener"
                   data-job-id="<?php echo esc_attr($job_id); ?>">
                    <?php _e('Apply for this Job', 'job-killer'); ?>
                    <span class="external-link-icon">↗</span>
                </a>
                <?php endif; ?>
                
                <button class="job-save-btn job-killer-btn job-killer-btn-secondary" data-job-id="<?php echo esc_attr($job_id); ?>">
                    <span class="dashicons dashicons-heart"></span>
                    <?php _e('Save Job', 'job-killer'); ?>
                </button>
                
                <button class="job-share-btn job-killer-btn job-killer-btn-secondary" data-job-id="<?php echo esc_attr($job_id); ?>">
                    <span class="dashicons dashicons-share"></span>
                    <?php _e('Share', 'job-killer'); ?>
                </button>
            </div>
        </div>
    </header>
    
    <div class="job-content">
        <div class="job-main">
            <div class="job-description">
                <h2><?php _e('Job Description', 'job-killer'); ?></h2>
                <div class="job-description-content">
                    <?php the_content(); ?>
                </div>
            </div>
            
            <?php
            // Job requirements (if available in content)
            $content = get_the_content();
            if (preg_match('/(?:requirements?|qualifications?|skills?)[:\s]*(.+?)(?:\n\n|\n(?=[A-Z]))/is', $content, $matches)):
            ?>
            <div class="job-requirements">
                <h3><?php _e('Requirements', 'job-killer'); ?></h3>
                <div class="requirements-content">
                    <?php echo wp_kses_post($matches[1]); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php
            // Job benefits (if available)
            $benefits = get_post_meta($job_id, '_job_benefits', true);
            if (!empty($benefits)):
            ?>
            <div class="job-benefits">
                <h3><?php _e('Benefits', 'job-killer'); ?></h3>
                <div class="benefits-content">
                    <?php echo wp_kses_post($benefits); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <aside class="job-sidebar">
            <div class="job-info-card">
                <h3><?php _e('Job Information', 'job-killer'); ?></h3>
                
                <div class="job-info-list">
                    <?php if (!empty($company)): ?>
                    <div class="job-info-item">
                        <span class="info-label">
                            <span class="dashicons dashicons-building"></span>
                            <?php _e('Company', 'job-killer'); ?>
                        </span>
                        <span class="info-value">
                            <?php if (!empty($company_website)): ?>
                            <a href="<?php echo esc_url($company_website); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html($company); ?>
                            </a>
                            <?php else: ?>
                            <?php echo esc_html($company); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($location)): ?>
                    <div class="job-info-item">
                        <span class="info-label">
                            <span class="dashicons dashicons-location"></span>
                            <?php _e('Location', 'job-killer'); ?>
                        </span>
                        <span class="info-value"><?php echo esc_html($location); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($salary)): ?>
                    <div class="job-info-item">
                        <span class="info-label">
                            <span class="dashicons dashicons-money-alt"></span>
                            <?php _e('Salary', 'job-killer'); ?>
                        </span>
                        <span class="info-value"><?php echo esc_html($salary); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="job-info-item">
                        <span class="info-label">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php _e('Posted', 'job-killer'); ?>
                        </span>
                        <span class="info-value">
                            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                                <?php echo human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ' . __('ago', 'job-killer'); ?>
                            </time>
                        </span>
                    </div>
                    
                    <?php if (!empty($job_expires)): ?>
                    <div class="job-info-item">
                        <span class="info-label">
                            <span class="dashicons dashicons-clock"></span>
                            <?php _e('Expires', 'job-killer'); ?>
                        </span>
                        <span class="info-value">
                            <time datetime="<?php echo esc_attr(date('c', strtotime($job_expires))); ?>">
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($job_expires))); ?>
                            </time>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($remote): ?>
                    <div class="job-info-item">
                        <span class="info-label">
                            <span class="dashicons dashicons-laptop"></span>
                            <?php _e('Work Type', 'job-killer'); ?>
                        </span>
                        <span class="info-value"><?php _e('Remote Work Available', 'job-killer'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php
            // Job categories and types
            $categories = get_the_terms($job_id, 'job_listing_category');
            $types = get_the_terms($job_id, 'job_listing_type');
            if ($categories || $types):
            ?>
            <div class="job-terms-card">
                <h3><?php _e('Job Categories & Types', 'job-killer'); ?></h3>
                
                <?php if ($categories && !is_wp_error($categories)): ?>
                <div class="job-categories">
                    <span class="terms-label"><?php _e('Categories:', 'job-killer'); ?></span>
                    <div class="terms-list">
                        <?php foreach ($categories as $category): ?>
                        <a href="<?php echo esc_url(get_term_link($category)); ?>" class="job-category">
                            <?php echo esc_html($category->name); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($types && !is_wp_error($types)): ?>
                <div class="job-types">
                    <span class="terms-label"><?php _e('Types:', 'job-killer'); ?></span>
                    <div class="terms-list">
                        <?php foreach ($types as $type): ?>
                        <a href="<?php echo esc_url(get_term_link($type)); ?>" class="job-type">
                            <?php echo esc_html($type->name); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($company_logo)): ?>
            <div class="company-logo-card">
                <h3><?php _e('Company', 'job-killer'); ?></h3>
                <div class="company-logo">
                    <?php if (is_numeric($company_logo)): ?>
                    <?php echo wp_get_attachment_image($company_logo, 'medium', false, array('alt' => esc_attr($company))); ?>
                    <?php else: ?>
                    <img src="<?php echo esc_url($company_logo); ?>" alt="<?php echo esc_attr($company); ?>">
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </div>
    
    <!-- Related Jobs -->
    <?php
    $related_jobs = new WP_Query(array(
        'post_type' => 'job_listing',
        'post_status' => 'publish',
        'posts_per_page' => 3,
        'post__not_in' => array($job_id),
        'meta_query' => array(
            array(
                'key' => '_filled',
                'value' => '1',
                'compare' => '!='
            )
        ),
        'tax_query' => array(
            'relation' => 'OR',
            array(
                'taxonomy' => 'job_listing_category',
                'field' => 'term_id',
                'terms' => wp_get_post_terms($job_id, 'job_listing_category', array('fields' => 'ids'))
            ),
            array(
                'taxonomy' => 'job_listing_type',
                'field' => 'term_id',
                'terms' => wp_get_post_terms($job_id, 'job_listing_type', array('fields' => 'ids'))
            )
        )
    ));
    
    if ($related_jobs->have_posts()):
    ?>
    <section class="related-jobs">
        <h2><?php _e('Related Jobs', 'job-killer'); ?></h2>
        <div class="related-jobs-list">
            <?php while ($related_jobs->have_posts()): $related_jobs->the_post(); ?>
            <?php include JOB_KILLER_PLUGIN_DIR . 'includes/templates/frontend/job-item.php'; ?>
            <?php endwhile; ?>
        </div>
    </section>
    <?php endif; wp_reset_postdata(); ?>
</article>

<!-- Share Modal -->
<div id="job-share-modal" class="job-killer-modal" style="display: none;">
    <div class="job-killer-modal-content">
        <div class="job-killer-modal-header">
            <h3><?php _e('Share this Job', 'job-killer'); ?></h3>
            <button class="job-killer-modal-close">&times;</button>
        </div>
        <div class="job-killer-modal-body">
            <div class="share-options">
                <a href="#" class="share-option" data-share="facebook">
                    <span class="share-icon">📘</span>
                    <?php _e('Share on Facebook', 'job-killer'); ?>
                </a>
                <a href="#" class="share-option" data-share="twitter">
                    <span class="share-icon">🐦</span>
                    <?php _e('Share on Twitter', 'job-killer'); ?>
                </a>
                <a href="#" class="share-option" data-share="linkedin">
                    <span class="share-icon">💼</span>
                    <?php _e('Share on LinkedIn', 'job-killer'); ?>
                </a>
                <a href="#" class="share-option" data-share="whatsapp">
                    <span class="share-icon">💬</span>
                    <?php _e('Share on WhatsApp', 'job-killer'); ?>
                </a>
                <a href="#" class="share-option" data-share="email">
                    <span class="share-icon">📧</span>
                    <?php _e('Share via Email', 'job-killer'); ?>
                </a>
            </div>
            
            <div class="share-url">
                <label for="share-url-input"><?php _e('Or copy link:', 'job-killer'); ?></label>
                <div class="url-copy-container">
                    <input type="text" id="share-url-input" value="<?php echo esc_url(get_permalink()); ?>" readonly>
                    <button class="copy-url-btn job-killer-btn job-killer-btn-secondary"><?php _e('Copy', 'job-killer'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>