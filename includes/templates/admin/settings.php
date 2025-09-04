<?php
/**
 * Admin Settings Template
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap job-killer-admin">
    <div class="job-killer-header">
        <h1><?php _e('Job Killer Settings', 'job-killer'); ?></h1>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('job_killer_settings-options'); ?>
        
        <div class="job-killer-settings-container">
            <!-- Settings Navigation -->
            <div class="job-killer-settings-nav">
                <ul>
                    <?php foreach ($this->get_sections() as $section_id => $section): ?>
                    <li>
                        <a href="#job-killer-section-<?php echo esc_attr($section_id); ?>" class="job-killer-nav-tab">
                            <?php echo esc_html($section['title']); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Settings Content -->
            <div class="job-killer-settings-content">
                <?php foreach ($this->get_sections() as $section_id => $section): ?>
                <div id="job-killer-section-<?php echo esc_attr($section_id); ?>" class="job-killer-settings-section">
                    <div class="job-killer-form">
                        <h2><?php echo esc_html($section['title']); ?></h2>
                        <?php if (!empty($section['description'])): ?>
                        <p class="description"><?php echo esc_html($section['description']); ?></p>
                        <?php endif; ?>

                        <?php foreach ($section['fields'] as $field_id => $field): ?>
                        <div class="job-killer-form-group">
                            <?php $this->render_field(array('field_id' => $field_id, 'field' => $field)); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Settings Actions -->
                <div class="job-killer-settings-actions">
                    <div class="job-killer-form">
                        <div class="job-killer-form-row">
                            <div class="job-killer-form-group">
                                <?php submit_button(__('Save Settings', 'job-killer'), 'primary', 'submit', false); ?>
                            </div>
                            <div class="job-killer-form-group">
                                <button type="button" class="job-killer-btn job-killer-btn-secondary job-killer-reset-settings">
                                    <?php _e('Reset to Defaults', 'job-killer'); ?>
                                </button>
                            </div>
                            <div class="job-killer-form-group">
                                <button type="button" class="job-killer-btn job-killer-btn-secondary job-killer-export-settings">
                                    <?php _e('Export Settings', 'job-killer'); ?>
                                </button>
                            </div>
                            <div class="job-killer-form-group">
                                <button type="button" class="job-killer-btn job-killer-btn-secondary job-killer-import-settings">
                                    <?php _e('Import Settings', 'job-killer'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.job-killer-settings-container {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.job-killer-settings-nav {
    flex: 0 0 200px;
}

.job-killer-settings-nav ul {
    list-style: none;
    margin: 0;
    padding: 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.job-killer-settings-nav li {
    border-bottom: 1px solid #f0f0f1;
}

.job-killer-settings-nav li:last-child {
    border-bottom: none;
}

.job-killer-nav-tab {
    display: block;
    padding: 12px 16px;
    text-decoration: none;
    color: #2c3338;
    transition: background-color 0.2s ease;
}

.job-killer-nav-tab:hover,
.job-killer-nav-tab.active {
    background: #f6f7f7;
    color: #2271b1;
}

.job-killer-settings-content {
    flex: 1;
}

.job-killer-settings-section {
    display: none;
}

.job-killer-settings-section.active {
    display: block;
}

.job-killer-settings-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

@media (max-width: 768px) {
    .job-killer-settings-container {
        flex-direction: column;
    }
    
    .job-killer-settings-nav {
        flex: none;
    }
    
    .job-killer-settings-nav ul {
        display: flex;
        overflow-x: auto;
    }
    
    .job-killer-settings-nav li {
        flex: 0 0 auto;
        border-bottom: none;
        border-right: 1px solid #f0f0f1;
    }
    
    .job-killer-settings-nav li:last-child {
        border-right: none;
    }
    
    .job-killer-nav-tab {
        white-space: nowrap;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Settings navigation
    $('.job-killer-nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Update navigation
        $('.job-killer-nav-tab').removeClass('active');
        $(this).addClass('active');
        
        // Show target section
        $('.job-killer-settings-section').removeClass('active');
        $(target).addClass('active');
        
        // Update URL hash
        window.location.hash = target;
    });
    
    // Show first section by default or section from hash
    var hash = window.location.hash;
    if (hash && $(hash).length) {
        $('.job-killer-nav-tab[href="' + hash + '"]').click();
    } else {
        $('.job-killer-nav-tab:first').click();
    }
    
    // Handle hash changes
    $(window).on('hashchange', function() {
        var hash = window.location.hash;
        if (hash && $(hash).length) {
            $('.job-killer-nav-tab[href="' + hash + '"]').click();
        }
    });
});
</script>