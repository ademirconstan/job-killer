<?php
/**
 * Admin Dashboard Template
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap job-killer-admin">
    <div class="job-killer-header">
        <h1><?php _e('Job Killer Dashboard', 'job-killer'); ?></h1>
        
        <?php if (isset($_GET['welcome']) && $_GET['welcome'] == '1'): ?>
        <div class="job-killer-notice success">
            <p><?php _e('Welcome to Job Killer! Your plugin has been activated successfully.', 'job-killer'); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Statistics Cards -->
    <div class="job-killer-stats-grid">
        <div class="job-killer-stat-card">
            <span class="job-killer-stat-number"><?php echo number_format($stats['active_jobs']); ?></span>
            <span class="job-killer-stat-label"><?php _e('Active Jobs', 'job-killer'); ?></span>
        </div>
        
        <div class="job-killer-stat-card">
            <span class="job-killer-stat-number"><?php echo number_format($stats['today_imports']); ?></span>
            <span class="job-killer-stat-label"><?php _e('Imported Today', 'job-killer'); ?></span>
        </div>
        
        <div class="job-killer-stat-card">
            <span class="job-killer-stat-number"><?php echo number_format($stats['week_imports']); ?></span>
            <span class="job-killer-stat-label"><?php _e('This Week', 'job-killer'); ?></span>
        </div>
        
        <div class="job-killer-stat-card">
            <span class="job-killer-stat-number"><?php echo number_format($stats['month_imports']); ?></span>
            <span class="job-killer-stat-label"><?php _e('This Month', 'job-killer'); ?></span>
        </div>
    </div>

    <div class="job-killer-dashboard">
        <!-- Main Content -->
        <div class="job-killer-main-content">
            <!-- Import Chart -->
            <div class="job-killer-chart-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3><?php _e('Import Activity', 'job-killer'); ?></h3>
                    <select id="job-killer-chart-days">
                        <option value="7"><?php _e('Last 7 days', 'job-killer'); ?></option>
                        <option value="30" selected><?php _e('Last 30 days', 'job-killer'); ?></option>
                        <option value="90"><?php _e('Last 90 days', 'job-killer'); ?></option>
                    </select>
                </div>
                <div style="position: relative; height: 300px;">
                    <canvas id="job-killer-chart"></canvas>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="job-killer-form">
                <h3><?php _e('Quick Actions', 'job-killer'); ?></h3>
                <div class="job-killer-form-row">
                    <div class="job-killer-form-group">
                        <button type="button" class="job-killer-btn job-killer-btn-primary job-killer-run-import">
                            <?php _e('Run Import Now', 'job-killer'); ?>
                        </button>
                    </div>
                    <div class="job-killer-form-group">
                        <a href="<?php echo admin_url('admin.php?page=job-killer-feeds'); ?>" class="job-killer-btn job-killer-btn-secondary">
                            <?php _e('Manage Feeds', 'job-killer'); ?>
                        </a>
                    </div>
                    <div class="job-killer-form-group">
                        <a href="<?php echo admin_url('admin.php?page=job-killer-settings'); ?>" class="job-killer-btn job-killer-btn-secondary">
                            <?php _e('Settings', 'job-killer'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Feed Status -->
            <div class="job-killer-form">
                <h3><?php _e('Feed Status', 'job-killer'); ?></h3>
                <?php if (!empty($active_feeds)): ?>
                <table class="job-killer-feeds-table">
                    <thead>
                        <tr>
                            <th><?php _e('Feed Name', 'job-killer'); ?></th>
                            <th><?php _e('Status', 'job-killer'); ?></th>
                            <th><?php _e('Last Import', 'job-killer'); ?></th>
                            <th><?php _e('Jobs Imported', 'job-killer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_feeds as $feed_id => $feed): ?>
                        <tr>
                            <td><?php echo esc_html($feed['name']); ?></td>
                            <td>
                                <span class="job-killer-feed-status <?php echo $feed['active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $feed['active'] ? __('Active', 'job-killer') : __('Inactive', 'job-killer'); ?>
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
                                $feed_stats = array_filter($stats['feed_stats'], function($stat) use ($feed_id) {
                                    return $stat->feed_id === $feed_id;
                                });
                                echo !empty($feed_stats) ? number_format($feed_stats[0]->count) : '0';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><?php _e('No active feeds configured.', 'job-killer'); ?> 
                   <a href="<?php echo admin_url('admin.php?page=job-killer-feeds'); ?>"><?php _e('Add your first feed', 'job-killer'); ?></a>
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="job-killer-sidebar">
            <!-- Next Scheduled Import -->
            <div class="job-killer-form">
                <h3><?php _e('Next Import', 'job-killer'); ?></h3>
                <p>
                    <?php if ($next_import): ?>
                        <strong id="job-killer-next-run"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_import); ?></strong>
                    <?php else: ?>
                        <em><?php _e('No import scheduled', 'job-killer'); ?></em>
                    <?php endif; ?>
                </p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=job-killer-scheduling'); ?>" class="job-killer-btn job-killer-btn-secondary">
                        <?php _e('Configure Schedule', 'job-killer'); ?>
                    </a>
                </p>
            </div>

            <!-- Recent Activity -->
            <div class="job-killer-form">
                <h3><?php _e('Recent Activity', 'job-killer'); ?></h3>
                <?php if (!empty($recent_logs)): ?>
                <div class="job-killer-recent-logs">
                    <?php foreach ($recent_logs as $log): ?>
                    <div class="job-killer-log-item">
                        <span class="job-killer-log-type <?php echo esc_attr($log->type); ?>"><?php echo esc_html($log->type); ?></span>
                        <div class="job-killer-log-message"><?php echo esc_html($log->message); ?></div>
                        <div class="job-killer-log-time"><?php echo $helper->time_ago($log->created_at); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=job-killer-logs'); ?>" class="job-killer-btn job-killer-btn-secondary">
                        <?php _e('View All Logs', 'job-killer'); ?>
                    </a>
                </p>
                <?php else: ?>
                <p><em><?php _e('No recent activity', 'job-killer'); ?></em></p>
                <?php endif; ?>
            </div>

            <!-- System Status -->
            <div class="job-killer-form">
                <h3><?php _e('System Status', 'job-killer'); ?></h3>
                <div class="job-killer-system-status">
                    <div class="status-item">
                        <span class="status-label"><?php _e('WordPress Version:', 'job-killer'); ?></span>
                        <span class="status-value"><?php echo get_bloginfo('version'); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label"><?php _e('PHP Version:', 'job-killer'); ?></span>
                        <span class="status-value"><?php echo PHP_VERSION; ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label"><?php _e('Plugin Version:', 'job-killer'); ?></span>
                        <span class="status-value"><?php echo JOB_KILLER_VERSION; ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label"><?php _e('Active Feeds:', 'job-killer'); ?></span>
                        <span class="status-value"><?php echo count($active_feeds); ?></span>
                    </div>
                </div>
            </div>

            <!-- Help & Support -->
            <div class="job-killer-form">
                <h3><?php _e('Help & Support', 'job-killer'); ?></h3>
                <ul>
                    <li><a href="<?php echo admin_url('admin.php?page=job-killer-api-test'); ?>"><?php _e('Test API Connections', 'job-killer'); ?></a></li>
                    <li><a href="<?php echo admin_url('admin.php?page=job-killer-logs'); ?>"><?php _e('View System Logs', 'job-killer'); ?></a></li>
                    <li><a href="#" data-modal="job-killer-system-info-modal"><?php _e('System Information', 'job-killer'); ?></a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- System Info Modal -->
<div id="job-killer-system-info-modal" class="job-killer-modal">
    <div class="job-killer-modal-content">
        <div class="job-killer-modal-header">
            <h2><?php _e('System Information', 'job-killer'); ?></h2>
            <button class="job-killer-modal-close">&times;</button>
        </div>
        <div class="job-killer-modal-body">
            <div id="job-killer-system-info-content">
                <p><?php _e('Loading system information...', 'job-killer'); ?></p>
            </div>
        </div>
        <div class="job-killer-modal-footer">
            <button class="job-killer-btn job-killer-btn-secondary job-killer-modal-close"><?php _e('Close', 'job-killer'); ?></button>
        </div>
    </div>
</div>

<style>
.job-killer-recent-logs {
    max-height: 300px;
    overflow-y: auto;
}

.job-killer-log-item {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
}

.job-killer-log-item:last-child {
    border-bottom: none;
}

.job-killer-log-time {
    font-size: 11px;
    color: #646970;
    margin-top: 2px;
}

.job-killer-system-status .status-item {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid #f0f0f1;
}

.job-killer-system-status .status-item:last-child {
    border-bottom: none;
}

.status-label {
    font-weight: 500;
}

.status-value {
    color: #646970;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Load system info when modal is opened
    $(document).on('click', '[data-modal="job-killer-system-info-modal"]', function() {
        $.ajax({
            url: jobKillerAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'job_killer_system_info',
                nonce: jobKillerAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var html = '<table class="job-killer-system-info-table">';
                    Object.keys(response.data).forEach(function(key) {
                        var value = response.data[key];
                        if (typeof value === 'object') {
                            value = JSON.stringify(value, null, 2);
                        }
                        html += '<tr><td><strong>' + key.replace(/_/g, ' ').toUpperCase() + ':</strong></td><td>' + value + '</td></tr>';
                    });
                    html += '</table>';
                    $('#job-killer-system-info-content').html(html);
                }
            }
        });
    });
});
</script>