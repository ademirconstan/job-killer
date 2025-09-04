<?php
/**
 * Admin Setup Wizard Template
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap job-killer-setup">
    <div class="job-killer-setup-wizard">
        <!-- Header -->
        <div class="job-killer-setup-header">
            <h1><?php _e('Welcome to Job Killer!', 'job-killer'); ?></h1>
            <p><?php _e('Let\'s get your job import system set up in just a few steps.', 'job-killer'); ?></p>
            
            <!-- Progress Steps -->
            <div class="job-killer-setup-progress">
                <?php foreach ($this->get_setup_steps() as $step_num => $step): ?>
                <div class="job-killer-setup-step <?php echo $step_num === 1 ? 'active' : ''; ?>" data-step="<?php echo $step_num; ?>">
                    <div class="job-killer-setup-step-number"><?php echo $step_num; ?></div>
                    <span><?php echo esc_html($step['title']); ?></span>
                </div>
                <?php if ($step_num < 4): ?>
                <div class="job-killer-setup-connector"></div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Content -->
        <div class="job-killer-setup-content">
            <form class="job-killer-setup-form">
                <!-- Step 1: Welcome -->
                <div class="job-killer-setup-step-1" style="display: block;">
                    <h2><?php _e('Welcome to Job Killer', 'job-killer'); ?></h2>
                    <p><?php _e('Job Killer will help you automatically import job listings from RSS feeds and APIs. This setup wizard will guide you through the initial configuration.', 'job-killer'); ?></p>
                    
                    <div class="job-killer-setup-features">
                        <div class="feature-item">
                            <span class="dashicons dashicons-rss"></span>
                            <h3><?php _e('RSS Feed Import', 'job-killer'); ?></h3>
                            <p><?php _e('Import jobs from multiple RSS feeds automatically', 'job-killer'); ?></p>
                        </div>
                        <div class="feature-item">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <h3><?php _e('Smart Deduplication', 'job-killer'); ?></h3>
                            <p><?php _e('Prevent duplicate job listings with intelligent filtering', 'job-killer'); ?></p>
                        </div>
                        <div class="feature-item">
                            <span class="dashicons dashicons-clock"></span>
                            <h3><?php _e('Automated Scheduling', 'job-killer'); ?></h3>
                            <p><?php _e('Set up automatic imports on your preferred schedule', 'job-killer'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Basic Settings -->
                <div class="job-killer-setup-step-2" style="display: none;">
                    <h2><?php _e('Basic Settings', 'job-killer'); ?></h2>
                    <p><?php _e('Configure the basic import settings for your job listings.', 'job-killer'); ?></p>
                    
                    <div class="job-killer-form-row">
                        <div class="job-killer-form-group">
                            <label for="setting_import_limit"><?php _e('Import Limit', 'job-killer'); ?></label>
                            <input type="number" id="setting_import_limit" name="setting_import_limit" value="50" min="1" max="500">
                            <p class="description"><?php _e('Maximum number of jobs to import per execution', 'job-killer'); ?></p>
                        </div>
                        
                        <div class="job-killer-form-group">
                            <label for="setting_cron_interval"><?php _e('Import Frequency', 'job-killer'); ?></label>
                            <select id="setting_cron_interval" name="setting_cron_interval">
                                <option value="hourly"><?php _e('Every Hour', 'job-killer'); ?></option>
                                <option value="twicedaily" selected><?php _e('Twice Daily', 'job-killer'); ?></option>
                                <option value="daily"><?php _e('Daily', 'job-killer'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="job-killer-form-row">
                        <div class="job-killer-form-group">
                            <label>
                                <input type="checkbox" name="setting_deduplication_enabled" value="1" checked>
                                <?php _e('Enable deduplication to prevent duplicate job listings', 'job-killer'); ?>
                            </label>
                        </div>
                        
                        <div class="job-killer-form-group">
                            <label>
                                <input type="checkbox" name="setting_email_notifications" value="1" checked>
                                <?php _e('Send email notifications for import results', 'job-killer'); ?>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Add RSS Feed -->
                <div class="job-killer-setup-step-3" style="display: none;">
                    <h2><?php _e('Add Your First RSS Feed', 'job-killer'); ?></h2>
                    <p><?php _e('Add an RSS feed to start importing job listings. You can add more feeds later.', 'job-killer'); ?></p>
                    
                    <div class="job-killer-form-group">
                        <label for="feed_name"><?php _e('Feed Name', 'job-killer'); ?></label>
                        <input type="text" id="feed_name" name="feed_name" placeholder="<?php _e('e.g., Indeed Brasil', 'job-killer'); ?>">
                    </div>
                    
                    <div class="job-killer-form-group">
                        <label for="feed_url"><?php _e('RSS Feed URL', 'job-killer'); ?></label>
                        <input type="url" id="feed_url" name="feed_url" placeholder="https://example.com/jobs.rss">
                    </div>
                    
                    <div class="job-killer-form-row">
                        <div class="job-killer-form-group">
                            <label for="feed_category"><?php _e('Default Category', 'job-killer'); ?></label>
                            <input type="text" id="feed_category" name="feed_category" placeholder="<?php _e('Technology', 'job-killer'); ?>">
                        </div>
                        
                        <div class="job-killer-form-group">
                            <label for="feed_region"><?php _e('Default Region', 'job-killer'); ?></label>
                            <input type="text" id="feed_region" name="feed_region" placeholder="<?php _e('São Paulo', 'job-killer'); ?>">
                        </div>
                    </div>
                    
                    <div class="job-killer-recommended-feeds">
                        <h3><?php _e('Recommended Feeds', 'job-killer'); ?></h3>
                        <p><?php _e('Click on any of these popular job boards to use their RSS feed:', 'job-killer'); ?></p>
                        
                        <?php foreach ($this->get_recommended_feeds() as $feed): ?>
                        <div class="recommended-feed-item" data-name="<?php echo esc_attr($feed['name']); ?>" data-url="<?php echo esc_attr($feed['url']); ?>">
                            <h4><?php echo esc_html($feed['name']); ?></h4>
                            <p><?php echo esc_html($feed['description']); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Step 4: Complete -->
                <div class="job-killer-setup-step-4" style="display: none;">
                    <h2><?php _e('Setup Complete!', 'job-killer'); ?></h2>
                    <p><?php _e('Congratulations! Job Killer is now configured and ready to import job listings.', 'job-killer'); ?></p>
                    
                    <div class="job-killer-setup-summary">
                        <h3><?php _e('What happens next?', 'job-killer'); ?></h3>
                        <ul>
                            <li><?php _e('Your RSS feeds will be checked automatically based on your schedule', 'job-killer'); ?></li>
                            <li><?php _e('New job listings will be imported and published', 'job-killer'); ?></li>
                            <li><?php _e('You\'ll receive email notifications about import results', 'job-killer'); ?></li>
                            <li><?php _e('You can manage everything from the Job Killer dashboard', 'job-killer'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="job-killer-setup-tips">
                        <h3><?php _e('Pro Tips', 'job-killer'); ?></h3>
                        <?php foreach ($this->get_setup_tips() as $tip): ?>
                        <div class="tip-item">
                            <span class="dashicons dashicons-<?php echo esc_attr($tip['icon']); ?>"></span>
                            <div>
                                <h4><?php echo esc_html($tip['title']); ?></h4>
                                <p><?php echo esc_html($tip['description']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Actions -->
        <div class="job-killer-setup-actions">
            <button type="button" class="job-killer-btn job-killer-btn-secondary job-killer-setup-prev" style="display: none;">
                <?php _e('Previous', 'job-killer'); ?>
            </button>
            
            <div class="job-killer-setup-actions-right">
                <button type="button" class="job-killer-btn job-killer-btn-secondary job-killer-setup-skip">
                    <?php _e('Skip Setup', 'job-killer'); ?>
                </button>
                
                <button type="button" class="job-killer-btn job-killer-btn-primary job-killer-setup-next">
                    <?php _e('Next', 'job-killer'); ?>
                </button>
                
                <button type="button" class="job-killer-btn job-killer-btn-primary job-killer-setup-complete" style="display: none;">
                    <?php _e('Complete Setup', 'job-killer'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.job-killer-setup {
    margin: 0;
    padding: 0;
}

.job-killer-setup-wizard {
    max-width: 800px;
    margin: 20px auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.job-killer-setup-header {
    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
    color: #fff;
    padding: 40px;
    text-align: center;
}

.job-killer-setup-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 600;
}

.job-killer-setup-header p {
    margin: 0 0 30px 0;
    font-size: 16px;
    opacity: 0.9;
}

.job-killer-setup-progress {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
}

.job-killer-setup-step {
    display: flex;
    align-items: center;
    gap: 10px;
    color: rgba(255,255,255,0.7);
    font-size: 14px;
    font-weight: 500;
}

.job-killer-setup-step.active {
    color: #fff;
}

.job-killer-setup-step-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.job-killer-setup-step.active .job-killer-setup-step-number {
    background: #fff;
    color: #2271b1;
}

.job-killer-setup-connector {
    width: 40px;
    height: 2px;
    background: rgba(255,255,255,0.3);
}

.job-killer-setup-content {
    padding: 40px;
    min-height: 400px;
}

.job-killer-setup-content h2 {
    margin: 0 0 10px 0;
    font-size: 24px;
    color: #1d2327;
}

.job-killer-setup-content > p {
    margin: 0 0 30px 0;
    font-size: 16px;
    color: #646970;
}

.job-killer-setup-features {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 30px;
    margin-top: 40px;
}

.feature-item {
    text-align: center;
}

.feature-item .dashicons {
    font-size: 48px;
    color: #2271b1;
    margin-bottom: 15px;
}

.feature-item h3 {
    margin: 0 0 10px 0;
    font-size: 18px;
    color: #1d2327;
}

.feature-item p {
    margin: 0;
    color: #646970;
    line-height: 1.5;
}

.job-killer-recommended-feeds {
    margin-top: 30px;
    padding-top: 30px;
    border-top: 1px solid #ddd;
}

.job-killer-recommended-feeds h3 {
    margin: 0 0 10px 0;
    color: #1d2327;
}

.recommended-feed-item {
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.recommended-feed-item:hover {
    border-color: #2271b1;
    background: #f6f7f7;
}

.recommended-feed-item h4 {
    margin: 0 0 5px 0;
    color: #2271b1;
}

.recommended-feed-item p {
    margin: 0;
    font-size: 13px;
    color: #646970;
}

.job-killer-setup-summary ul {
    list-style: none;
    padding: 0;
}

.job-killer-setup-summary li {
    padding: 8px 0;
    position: relative;
    padding-left: 25px;
}

.job-killer-setup-summary li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #00a32a;
    font-weight: bold;
}

.job-killer-setup-tips {
    margin-top: 30px;
    padding-top: 30px;
    border-top: 1px solid #ddd;
}

.tip-item {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.tip-item .dashicons {
    color: #2271b1;
    font-size: 20px;
    margin-top: 2px;
}

.tip-item h4 {
    margin: 0 0 5px 0;
    color: #1d2327;
}

.tip-item p {
    margin: 0;
    color: #646970;
    line-height: 1.5;
}

.job-killer-setup-actions {
    padding: 20px 40px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.job-killer-setup-actions-right {
    display: flex;
    gap: 10px;
}

@media (max-width: 768px) {
    .job-killer-setup-wizard {
        margin: 10px;
    }
    
    .job-killer-setup-header {
        padding: 30px 20px;
    }
    
    .job-killer-setup-content {
        padding: 30px 20px;
    }
    
    .job-killer-setup-progress {
        flex-direction: column;
        gap: 15px;
    }
    
    .job-killer-setup-connector {
        width: 2px;
        height: 20px;
    }
    
    .job-killer-setup-features {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .job-killer-setup-actions {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .job-killer-setup-actions-right {
        justify-content: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Recommended feeds click handler
    $('.recommended-feed-item').on('click', function() {
        var name = $(this).data('name');
        var url = $(this).data('url');
        
        $('#feed_name').val(name);
        $('#feed_url').val(url);
        
        $('.recommended-feed-item').removeClass('selected');
        $(this).addClass('selected');
    });
});
</script>