<?php
/**
 * Admin Feeds Template
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap job-killer-admin">
    <div class="job-killer-header">
        <h1><?php _e('RSS Feeds Management', 'job-killer'); ?></h1>
        <button class="job-killer-btn job-killer-btn-primary" data-modal="job-killer-add-feed-modal">
            <?php _e('Add New Feed', 'job-killer'); ?>
        </button>
    </div>

    <!-- Feeds List -->
    <div class="job-killer-feeds-container">
        <?php if (!empty($feeds)): ?>
        <table class="job-killer-feeds-table">
            <thead>
                <tr>
                    <th><?php _e('Name', 'job-killer'); ?></th>
                    <th><?php _e('URL', 'job-killer'); ?></th>
                    <th><?php _e('Provider', 'job-killer'); ?></th>
                    <th><?php _e('Status', 'job-killer'); ?></th>
                    <th><?php _e('Last Import', 'job-killer'); ?></th>
                    <th><?php _e('Jobs Imported', 'job-killer'); ?></th>
                    <th><?php _e('Actions', 'job-killer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feeds as $feed_id => $feed): ?>
                <tr data-feed-id="<?php echo esc_attr($feed_id); ?>">
                    <td>
                        <strong><?php echo esc_html($feed['name']); ?></strong>
                        <?php if (!empty($feed['default_category'])): ?>
                        <br><small><?php echo esc_html($feed['default_category']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url($feed['url']); ?>" target="_blank" title="<?php echo esc_attr($feed['url']); ?>">
                            <?php echo esc_html(wp_trim_words($feed['url'], 6, '...')); ?>
                        </a>
                    </td>
                    <td>
                        <?php
                        $provider_id = $rss_providers->detect_provider($feed['url']);
                        $provider_config = $rss_providers->get_provider_config($provider_id);
                        echo esc_html($provider_config['name']);
                        ?>
                    </td>
                    <td>
                        <span class="job-killer-feed-status <?php echo !empty($feed['active']) ? 'active' : 'inactive'; ?>">
                            <?php echo !empty($feed['active']) ? __('Active', 'job-killer') : __('Inactive', 'job-killer'); ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $last_import = get_option('job_killer_last_import_' . $feed_id);
                        echo $last_import ? $helper->time_ago($last_import) : __('Never', 'job-killer');
                        ?>
                    </td>
                    <td>
                        <?php
                        global $wpdb;
                        $imports_table = $wpdb->prefix . 'job_killer_imports';
                        $count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $imports_table WHERE feed_id = %s",
                            $feed_id
                        ));
                        echo number_format($count);
                        ?>
                    </td>
                    <td>
                        <div class="job-killer-feed-actions">
                            <button class="job-killer-btn job-killer-btn-secondary job-killer-test-feed" 
                                    data-feed-id="<?php echo esc_attr($feed_id); ?>"
                                    data-feed-url="<?php echo esc_attr($feed['url']); ?>">
                                <?php _e('Test', 'job-killer'); ?>
                            </button>
                            
                            <button class="job-killer-btn job-killer-btn-success job-killer-import-feed" 
                                    data-feed-id="<?php echo esc_attr($feed_id); ?>">
                                <?php _e('Import', 'job-killer'); ?>
                            </button>
                            
                            <button class="job-killer-btn job-killer-btn-secondary job-killer-toggle-feed" 
                                    data-feed-id="<?php echo esc_attr($feed_id); ?>">
                                <?php echo !empty($feed['active']) ? __('Deactivate', 'job-killer') : __('Activate', 'job-killer'); ?>
                            </button>
                            
                            <button class="job-killer-btn job-killer-btn-secondary" 
                                    data-modal="job-killer-edit-feed-modal"
                                    data-feed-id="<?php echo esc_attr($feed_id); ?>">
                                <?php _e('Edit', 'job-killer'); ?>
                            </button>
                            
                            <button class="job-killer-btn job-killer-btn-danger job-killer-delete-feed" 
                                    data-feed-id="<?php echo esc_attr($feed_id); ?>">
                                <?php _e('Delete', 'job-killer'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="job-killer-form">
            <h3><?php _e('No RSS Feeds Configured', 'job-killer'); ?></h3>
            <p><?php _e('Get started by adding your first RSS feed source.', 'job-killer'); ?></p>
            <button class="job-killer-btn job-killer-btn-primary" data-modal="job-killer-add-feed-modal">
                <?php _e('Add Your First Feed', 'job-killer'); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Test Results -->
    <div class="job-killer-test-results" style="display: none; margin-top: 20px;"></div>
</div>

<!-- Add Feed Modal -->
<div id="job-killer-add-feed-modal" class="job-killer-modal">
    <div class="job-killer-modal-content">
        <div class="job-killer-modal-header">
            <h2><?php _e('Add New RSS Feed', 'job-killer'); ?></h2>
            <button class="job-killer-modal-close">&times;</button>
        </div>
        <div class="job-killer-modal-body">
            <form id="job-killer-add-feed-form">
                <div class="job-killer-form-group">
                    <label for="feed_name"><?php _e('Feed Name', 'job-killer'); ?></label>
                    <input type="text" id="feed_name" name="feed[name]" required>
                    <p class="description"><?php _e('A descriptive name for this feed', 'job-killer'); ?></p>
                </div>

                <div class="job-killer-form-group">
                    <label for="feed_url"><?php _e('RSS Feed URL', 'job-killer'); ?></label>
                    <input type="url" id="feed_url" name="feed[url]" required>
                    <p class="description"><?php _e('The complete URL to the RSS feed', 'job-killer'); ?></p>
                </div>

                <div class="job-killer-form-row">
                    <div class="job-killer-form-group">
                        <label for="feed_category"><?php _e('Default Category', 'job-killer'); ?></label>
                        <select id="feed_category" name="feed[default_category]">
                            <option value=""><?php _e('Select Category', 'job-killer'); ?></option>
                            <?php
                            $categories = get_terms(array(
                                'taxonomy' => 'job_listing_category',
                                'hide_empty' => false
                            ));
                            foreach ($categories as $category) {
                                echo '<option value="' . esc_attr($category->name) . '">' . esc_html($category->name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="job-killer-form-group">
                        <label for="feed_region"><?php _e('Default Region', 'job-killer'); ?></label>
                        <select id="feed_region" name="feed[default_region]">
                            <option value=""><?php _e('Select Region', 'job-killer'); ?></option>
                            <?php
                            $regions = get_terms(array(
                                'taxonomy' => 'job_listing_region',
                                'hide_empty' => false
                            ));
                            foreach ($regions as $region) {
                                echo '<option value="' . esc_attr($region->name) . '">' . esc_html($region->name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="job-killer-form-group">
                    <label>
                        <input type="checkbox" name="feed[active]" value="1" checked>
                        <?php _e('Activate this feed immediately', 'job-killer'); ?>
                    </label>
                </div>

                <!-- Field Mapping Section -->
                <div class="job-killer-field-mapping" style="display: none;">
                    <h4><?php _e('Field Mapping', 'job-killer'); ?></h4>
                    <p class="description"><?php _e('Map RSS fields to job listing fields', 'job-killer'); ?></p>
                    
                    <div class="job-killer-form-row">
                        <div class="job-killer-form-group">
                            <label><?php _e('Title Field', 'job-killer'); ?></label>
                            <input type="text" name="feed[field_mapping][title]" value="title">
                        </div>
                        <div class="job-killer-form-group">
                            <label><?php _e('Description Field', 'job-killer'); ?></label>
                            <input type="text" name="feed[field_mapping][description]" value="description">
                        </div>
                    </div>

                    <div class="job-killer-form-row">
                        <div class="job-killer-form-group">
                            <label><?php _e('Company Field', 'job-killer'); ?></label>
                            <input type="text" name="feed[field_mapping][company]" value="company">
                        </div>
                        <div class="job-killer-form-group">
                            <label><?php _e('Location Field', 'job-killer'); ?></label>
                            <input type="text" name="feed[field_mapping][location]" value="location">
                        </div>
                    </div>

                    <div class="job-killer-form-row">
                        <div class="job-killer-form-group">
                            <label><?php _e('URL Field', 'job-killer'); ?></label>
                            <input type="text" name="feed[field_mapping][url]" value="link">
                        </div>
                        <div class="job-killer-form-group">
                            <label><?php _e('Date Field', 'job-killer'); ?></label>
                            <input type="text" name="feed[field_mapping][date]" value="pubDate">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="job-killer-modal-footer">
            <button class="job-killer-btn job-killer-btn-secondary job-killer-test-feed">
                <?php _e('Test Feed', 'job-killer'); ?>
            </button>
            <button class="job-killer-btn job-killer-btn-primary job-killer-save-feed">
                <?php _e('Save Feed', 'job-killer'); ?>
            </button>
            <button class="job-killer-btn job-killer-btn-secondary job-killer-modal-close">
                <?php _e('Cancel', 'job-killer'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Edit Feed Modal -->
<div id="job-killer-edit-feed-modal" class="job-killer-modal">
    <div class="job-killer-modal-content">
        <div class="job-killer-modal-header">
            <h2><?php _e('Edit RSS Feed', 'job-killer'); ?></h2>
            <button class="job-killer-modal-close">&times;</button>
        </div>
        <div class="job-killer-modal-body">
            <form id="job-killer-edit-feed-form">
                <input type="hidden" name="feed[id]" id="edit_feed_id">
                <!-- Same form fields as add modal -->
                <div class="job-killer-form-group">
                    <label for="edit_feed_name"><?php _e('Feed Name', 'job-killer'); ?></label>
                    <input type="text" id="edit_feed_name" name="feed[name]" required>
                </div>

                <div class="job-killer-form-group">
                    <label for="edit_feed_url"><?php _e('RSS Feed URL', 'job-killer'); ?></label>
                    <input type="url" id="edit_feed_url" name="feed[url]" required>
                </div>

                <div class="job-killer-form-row">
                    <div class="job-killer-form-group">
                        <label for="edit_feed_category"><?php _e('Default Category', 'job-killer'); ?></label>
                        <select id="edit_feed_category" name="feed[default_category]">
                            <option value=""><?php _e('Select Category', 'job-killer'); ?></option>
                            <?php
                            foreach ($categories as $category) {
                                echo '<option value="' . esc_attr($category->name) . '">' . esc_html($category->name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="job-killer-form-group">
                        <label for="edit_feed_region"><?php _e('Default Region', 'job-killer'); ?></label>
                        <select id="edit_feed_region" name="feed[default_region]">
                            <option value=""><?php _e('Select Region', 'job-killer'); ?></option>
                            <?php
                            foreach ($regions as $region) {
                                echo '<option value="' . esc_attr($region->name) . '">' . esc_html($region->name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="job-killer-form-group">
                    <label>
                        <input type="checkbox" id="edit_feed_active" name="feed[active]" value="1">
                        <?php _e('Feed is active', 'job-killer'); ?>
                    </label>
                </div>
            </form>
        </div>
        <div class="job-killer-modal-footer">
            <button class="job-killer-btn job-killer-btn-primary job-killer-save-feed">
                <?php _e('Update Feed', 'job-killer'); ?>
            </button>
            <button class="job-killer-btn job-killer-btn-secondary job-killer-modal-close">
                <?php _e('Cancel', 'job-killer'); ?>
            </button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Test feed functionality
    $('.job-killer-test-feed').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var feedUrl = $button.data('feed-url') || $('#feed_url').val() || $('#edit_feed_url').val();
        
        if (!feedUrl) {
            alert('Please enter a feed URL first.');
            return;
        }
        
        $button.prop('disabled', true).html('<span class="job-killer-loading"></span> Testing...');
        
        $.ajax({
            url: jobKillerAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'job_killer_test_feed',
                nonce: jobKillerAdmin.nonce,
                url: feedUrl
            },
            success: function(response) {
                if (response.success) {
                    $('.job-killer-test-results').html(
                        '<div class="job-killer-notice success">' +
                        '<h4>Test Successful!</h4>' +
                        '<p>' + response.data.message + '</p>' +
                        '<p><strong>Provider:</strong> ' + response.data.provider_name + '</p>' +
                        '</div>'
                    ).show();
                    
                    // Show field mapping section
                    $('.job-killer-field-mapping').show();
                } else {
                    $('.job-killer-test-results').html(
                        '<div class="job-killer-notice error">' +
                        '<h4>Test Failed</h4>' +
                        '<p>' + response.data + '</p>' +
                        '</div>'
                    ).show();
                }
            },
            error: function() {
                $('.job-killer-test-results').html(
                    '<div class="job-killer-notice error">' +
                    '<h4>Test Failed</h4>' +
                    '<p>An error occurred while testing the feed.</p>' +
                    '</div>'
                ).show();
            },
            complete: function() {
                $button.prop('disabled', false).html('Test Feed');
            }
        });
    });
    
    // Edit feed modal
    $('[data-modal="job-killer-edit-feed-modal"]').on('click', function() {
        var feedId = $(this).data('feed-id');
        var feedData = <?php echo wp_json_encode($feeds); ?>;
        var feed = feedData[feedId];
        
        if (feed) {
            $('#edit_feed_id').val(feedId);
            $('#edit_feed_name').val(feed.name);
            $('#edit_feed_url').val(feed.url);
            $('#edit_feed_category').val(feed.default_category || '');
            $('#edit_feed_region').val(feed.default_region || '');
            $('#edit_feed_active').prop('checked', feed.active);
        }
    });
});
</script>