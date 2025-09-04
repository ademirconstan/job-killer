<?php
/**
 * Admin Logs Template
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap job-killer-admin">
    <div class="job-killer-header">
        <h1><?php _e('System Logs', 'job-killer'); ?></h1>
        <div class="job-killer-header-actions">
            <button class="job-killer-btn job-killer-btn-secondary job-killer-export-logs">
                <?php _e('Export Logs', 'job-killer'); ?>
            </button>
            <button class="job-killer-btn job-killer-btn-danger job-killer-clear-logs">
                <?php _e('Clear All Logs', 'job-killer'); ?>
            </button>
        </div>
    </div>

    <!-- Log Filters -->
    <div class="job-killer-logs-filters">
        <div class="job-killer-form-group">
            <label for="log-filter-type"><?php _e('Type', 'job-killer'); ?></label>
            <select id="log-filter-type" class="job-killer-log-filter">
                <option value=""><?php _e('All Types', 'job-killer'); ?></option>
                <?php foreach ($log_types as $type): ?>
                <option value="<?php echo esc_attr($type); ?>" <?php selected($filters['type'], $type); ?>>
                    <?php echo esc_html(ucfirst($type)); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="job-killer-form-group">
            <label for="log-filter-source"><?php _e('Source', 'job-killer'); ?></label>
            <select id="log-filter-source" class="job-killer-log-filter">
                <option value=""><?php _e('All Sources', 'job-killer'); ?></option>
                <?php foreach ($log_sources as $source): ?>
                <option value="<?php echo esc_attr($source); ?>" <?php selected($filters['source'], $source); ?>>
                    <?php echo esc_html(ucfirst($source)); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="job-killer-form-group">
            <label for="log-filter-date-from"><?php _e('From Date', 'job-killer'); ?></label>
            <input type="date" id="log-filter-date-from" class="job-killer-log-filter" value="<?php echo esc_attr($filters['date_from']); ?>">
        </div>

        <div class="job-killer-form-group">
            <label for="log-filter-date-to"><?php _e('To Date', 'job-killer'); ?></label>
            <input type="date" id="log-filter-date-to" class="job-killer-log-filter" value="<?php echo esc_attr($filters['date_to']); ?>">
        </div>

        <div class="job-killer-form-group">
            <button type="button" class="job-killer-btn job-killer-btn-primary" onclick="jobKiller.filterLogs()">
                <?php _e('Filter', 'job-killer'); ?>
            </button>
        </div>
    </div>

    <!-- Logs Summary -->
    <div class="job-killer-logs-summary">
        <div class="job-killer-form">
            <div class="job-killer-form-row">
                <div class="job-killer-form-group">
                    <strong><?php _e('Total Logs:', 'job-killer'); ?></strong>
                    <?php echo number_format($total_logs); ?>
                </div>
                <div class="job-killer-form-group">
                    <strong><?php _e('Showing:', 'job-killer'); ?></strong>
                    <?php echo number_format(count($logs)); ?>
                </div>
                <div class="job-killer-form-group">
                    <strong><?php _e('Page:', 'job-killer'); ?></strong>
                    <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Logs Table -->
    <?php if (!empty($logs)): ?>
    <table class="job-killer-logs-table">
        <thead>
            <tr>
                <th><?php _e('Type', 'job-killer'); ?></th>
                <th><?php _e('Source', 'job-killer'); ?></th>
                <th><?php _e('Message', 'job-killer'); ?></th>
                <th><?php _e('Data', 'job-killer'); ?></th>
                <th><?php _e('Date', 'job-killer'); ?></th>
                <th><?php _e('Actions', 'job-killer'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td>
                    <span class="job-killer-log-type <?php echo esc_attr($log->type); ?>">
                        <?php echo esc_html(ucfirst($log->type)); ?>
                    </span>
                </td>
                <td><?php echo esc_html(ucfirst($log->source)); ?></td>
                <td>
                    <div class="job-killer-log-message" title="<?php echo esc_attr($log->message); ?>">
                        <?php echo esc_html($log->message); ?>
                    </div>
                </td>
                <td>
                    <?php if (!empty($log->data)): ?>
                    <div class="job-killer-log-data" title="<?php echo esc_attr($log->data); ?>">
                        <?php echo esc_html(wp_trim_words($log->data, 10)); ?>
                    </div>
                    <?php else: ?>
                    <em><?php _e('No data', 'job-killer'); ?></em>
                    <?php endif; ?>
                </td>
                <td>
                    <div title="<?php echo esc_attr($log->created_at); ?>">
                        <?php echo $helper->time_ago($log->created_at); ?>
                    </div>
                </td>
                <td>
                    <button class="job-killer-btn job-killer-btn-secondary" 
                            data-modal="job-killer-log-detail-modal"
                            data-log-id="<?php echo esc_attr($log->id); ?>">
                        <?php _e('View', 'job-killer'); ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="job-killer-pagination">
        <?php
        $pagination_args = array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo; Previous', 'job-killer'),
            'next_text' => __('Next &raquo;', 'job-killer'),
            'total' => $total_pages,
            'current' => $current_page
        );
        
        echo paginate_links($pagination_args);
        ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="job-killer-form">
        <h3><?php _e('No Logs Found', 'job-killer'); ?></h3>
        <p><?php _e('No log entries match your current filters.', 'job-killer'); ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Log Detail Modal -->
<div id="job-killer-log-detail-modal" class="job-killer-modal">
    <div class="job-killer-modal-content">
        <div class="job-killer-modal-header">
            <h2><?php _e('Log Entry Details', 'job-killer'); ?></h2>
            <button class="job-killer-modal-close">&times;</button>
        </div>
        <div class="job-killer-modal-body">
            <div id="job-killer-log-detail-content">
                <p><?php _e('Loading log details...', 'job-killer'); ?></p>
            </div>
        </div>
        <div class="job-killer-modal-footer">
            <button class="job-killer-btn job-killer-btn-secondary job-killer-modal-close">
                <?php _e('Close', 'job-killer'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.job-killer-header-actions {
    display: flex;
    gap: 10px;
}

.job-killer-logs-summary {
    margin-bottom: 20px;
}

.job-killer-log-message,
.job-killer-log-data {
    cursor: help;
}

.job-killer-log-detail {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.job-killer-log-detail h4 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #1d2327;
}

.job-killer-log-detail pre {
    background: #fff;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow-x: auto;
    font-size: 12px;
    line-height: 1.4;
}

@media (max-width: 768px) {
    .job-killer-header-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .job-killer-logs-filters {
        flex-direction: column;
    }
    
    .job-killer-logs-table {
        font-size: 11px;
    }
    
    .job-killer-log-message,
    .job-killer-log-data {
        max-width: 100px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Log detail modal
    $('[data-modal="job-killer-log-detail-modal"]').on('click', function() {
        var logId = $(this).data('log-id');
        var logData = <?php echo wp_json_encode($logs); ?>;
        var log = null;
        
        // Find the log entry
        for (var i = 0; i < logData.length; i++) {
            if (logData[i].id == logId) {
                log = logData[i];
                break;
            }
        }
        
        if (log) {
            var html = '<div class="job-killer-log-detail">';
            html += '<h4>Type: <span class="job-killer-log-type ' + log.type + '">' + log.type.toUpperCase() + '</span></h4>';
            html += '<p><strong>Source:</strong> ' + log.source + '</p>';
            html += '<p><strong>Date:</strong> ' + log.created_at + '</p>';
            html += '<p><strong>Message:</strong></p>';
            html += '<pre>' + log.message + '</pre>';
            
            if (log.data) {
                html += '<p><strong>Additional Data:</strong></p>';
                try {
                    var parsedData = JSON.parse(log.data);
                    html += '<pre>' + JSON.stringify(parsedData, null, 2) + '</pre>';
                } catch (e) {
                    html += '<pre>' + log.data + '</pre>';
                }
            }
            
            html += '</div>';
            
            $('#job-killer-log-detail-content').html(html);
        }
    });
    
    // Auto-refresh logs every 30 seconds
    setInterval(function() {
        if ($('.job-killer-logs-table').length && !$('.job-killer-modal:visible').length) {
            location.reload();
        }
    }, 30000);
});
</script>