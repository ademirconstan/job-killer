<?php
/**
 * Admin Scheduling Template
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap job-killer-admin">
    <div class="job-killer-header">
        <h1><?php _e('Import Scheduling', 'job-killer'); ?></h1>
    </div>

    <!-- Current Schedule Status -->
    <div class="job-killer-form">
        <h2><?php _e('Current Schedule Status', 'job-killer'); ?></h2>
        
        <div class="job-killer-schedule-status">
            <div class="job-killer-form-row">
                <div class="job-killer-form-group">
                    <label><?php _e('Current Interval:', 'job-killer'); ?></label>
                    <strong>
                        <?php
                        $interval = $settings['cron_interval'] ?? 'twicedaily';
                        $intervals = array(
                            'every_30_minutes' => __('Every 30 Minutes', 'job-killer'),
                            'hourly' => __('Hourly', 'job-killer'),
                            'every_2_hours' => __('Every 2 Hours', 'job-killer'),
                            'every_6_hours' => __('Every 6 Hours', 'job-killer'),
                            'twicedaily' => __('Twice Daily', 'job-killer'),
                            'daily' => __('Daily', 'job-killer')
                        );
                        echo isset($intervals[$interval]) ? $intervals[$interval] : $interval;
                        ?>
                    </strong>
                </div>
                
                <div class="job-killer-form-group">
                    <label><?php _e('Next Scheduled Import:', 'job-killer'); ?></label>
                    <strong>
                        <?php if ($next_import): ?>
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_import); ?>
                        <?php else: ?>
                            <em><?php _e('No import scheduled', 'job-killer'); ?></em>
                        <?php endif; ?>
                    </strong>
                </div>
                
                <div class="job-killer-form-group">
                    <label><?php _e('Next Log Cleanup:', 'job-killer'); ?></label>
                    <strong>
                        <?php if ($next_cleanup): ?>
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_cleanup); ?>
                        <?php else: ?>
                            <em><?php _e('No cleanup scheduled', 'job-killer'); ?></em>
                        <?php endif; ?>
                    </strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Configuration -->
    <div class="job-killer-form">
        <h2><?php _e('Schedule Configuration', 'job-killer'); ?></h2>
        <p class="description"><?php _e('Configure how often the automatic import should run.', 'job-killer'); ?></p>
        
        <form method="post" action="options.php">
            <?php settings_fields('job_killer_settings'); ?>
            
            <div class="job-killer-form-group">
                <label for="job-killer-cron-interval"><?php _e('Import Interval', 'job-killer'); ?></label>
                <select id="job-killer-cron-interval" name="job_killer_settings[cron_interval]">
                    <option value="every_30_minutes" <?php selected($interval, 'every_30_minutes'); ?>>
                        <?php _e('Every 30 Minutes', 'job-killer'); ?>
                    </option>
                    <option value="hourly" <?php selected($interval, 'hourly'); ?>>
                        <?php _e('Hourly', 'job-killer'); ?>
                    </option>
                    <option value="every_2_hours" <?php selected($interval, 'every_2_hours'); ?>>
                        <?php _e('Every 2 Hours', 'job-killer'); ?>
                    </option>
                    <option value="every_6_hours" <?php selected($interval, 'every_6_hours'); ?>>
                        <?php _e('Every 6 Hours', 'job-killer'); ?>
                    </option>
                    <option value="twicedaily" <?php selected($interval, 'twicedaily'); ?>>
                        <?php _e('Twice Daily', 'job-killer'); ?>
                    </option>
                    <option value="daily" <?php selected($interval, 'daily'); ?>>
                        <?php _e('Daily', 'job-killer'); ?>
                    </option>
                </select>
                <p class="description"><?php _e('Choose how frequently you want to import jobs automatically.', 'job-killer'); ?></p>
            </div>
            
            <?php submit_button(__('Update Schedule', 'job-killer')); ?>
        </form>
    </div>

    <!-- Manual Actions -->
    <div class="job-killer-form">
        <h2><?php _e('Manual Actions', 'job-killer'); ?></h2>
        <p class="description"><?php _e('Perform manual import and maintenance tasks.', 'job-killer'); ?></p>
        
        <div class="job-killer-form-row">
            <div class="job-killer-form-group">
                <button type="button" class="job-killer-btn job-killer-btn-primary job-killer-run-import">
                    <?php _e('Run Import Now', 'job-killer'); ?>
                </button>
                <p class="description"><?php _e('Execute the import process immediately for all active feeds.', 'job-killer'); ?></p>
            </div>
            
            <div class="job-killer-form-group">
                <button type="button" class="job-killer-btn job-killer-btn-secondary" id="job-killer-reschedule">
                    <?php _e('Reschedule Cron', 'job-killer'); ?>
                </button>
                <p class="description"><?php _e('Reset the cron schedule if it appears to be stuck.', 'job-killer'); ?></p>
            </div>
            
            <div class="job-killer-form-group">
                <button type="button" class="job-killer-btn job-killer-btn-secondary" id="job-killer-cleanup-logs">
                    <?php _e('Cleanup Logs Now', 'job-killer'); ?>
                </button>
                <p class="description"><?php _e('Remove old log entries based on retention settings.', 'job-killer'); ?></p>
            </div>
        </div>
    </div>

    <!-- Cron History -->
    <div class="job-killer-form">
        <h2><?php _e('Recent Cron Executions', 'job-killer'); ?></h2>
        
        <?php if (!empty($cron_logs)): ?>
        <table class="job-killer-logs-table">
            <thead>
                <tr>
                    <th><?php _e('Type', 'job-killer'); ?></th>
                    <th><?php _e('Message', 'job-killer'); ?></th>
                    <th><?php _e('Data', 'job-killer'); ?></th>
                    <th><?php _e('Date', 'job-killer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cron_logs as $log): ?>
                <tr>
                    <td>
                        <span class="job-killer-log-type <?php echo esc_attr($log->type); ?>">
                            <?php echo esc_html(ucfirst($log->type)); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($log->message); ?></td>
                    <td>
                        <?php if (!empty($log->data)): ?>
                        <details>
                            <summary><?php _e('View Data', 'job-killer'); ?></summary>
                            <pre><?php echo esc_html($log->data); ?></pre>
                        </details>
                        <?php else: ?>
                        <em><?php _e('No data', 'job-killer'); ?></em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div title="<?php echo esc_attr($log->created_at); ?>">
                            <?php echo $helper->time_ago($log->created_at); ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><em><?php _e('No cron executions recorded yet.', 'job-killer'); ?></em></p>
        <?php endif; ?>
    </div>

    <!-- WordPress Cron Information -->
    <div class="job-killer-form">
        <h2><?php _e('WordPress Cron Information', 'job-killer'); ?></h2>
        <p class="description"><?php _e('Information about WordPress cron system and scheduled events.', 'job-killer'); ?></p>
        
        <div class="job-killer-cron-info">
            <?php
            $cron_events = _get_cron_array();
            $job_killer_events = array();
            
            foreach ($cron_events as $timestamp => $events) {
                foreach ($events as $hook => $event_data) {
                    if (strpos($hook, 'job_killer_') === 0) {
                        $job_killer_events[] = array(
                            'hook' => $hook,
                            'timestamp' => $timestamp,
                            'next_run' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp),
                            'args' => $event_data
                        );
                    }
                }
            }
            ?>
            
            <?php if (!empty($job_killer_events)): ?>
            <table class="job-killer-system-info-table">
                <thead>
                    <tr>
                        <th><?php _e('Hook', 'job-killer'); ?></th>
                        <th><?php _e('Next Run', 'job-killer'); ?></th>
                        <th><?php _e('Interval', 'job-killer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($job_killer_events as $event): ?>
                    <tr>
                        <td><code><?php echo esc_html($event['hook']); ?></code></td>
                        <td><?php echo esc_html($event['next_run']); ?></td>
                        <td>
                            <?php
                            $recurrence = '';
                            foreach ($event['args'] as $args) {
                                if (isset($args['schedule'])) {
                                    $recurrence = $args['schedule'];
                                    break;
                                }
                            }
                            echo esc_html($recurrence ?: __('Single event', 'job-killer'));
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><em><?php _e('No Job Killer cron events scheduled.', 'job-killer'); ?></em></p>
            <?php endif; ?>
            
            <div class="job-killer-cron-status">
                <h4><?php _e('Cron Status', 'job-killer'); ?></h4>
                <ul>
                    <li>
                        <strong><?php _e('WordPress Cron:', 'job-killer'); ?></strong>
                        <?php if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON): ?>
                            <span class="job-killer-status-warning"><?php _e('Disabled', 'job-killer'); ?></span>
                            <p class="description"><?php _e('WordPress cron is disabled. You need to set up a system cron job.', 'job-killer'); ?></p>
                        <?php else: ?>
                            <span class="job-killer-status-ok"><?php _e('Enabled', 'job-killer'); ?></span>
                        <?php endif; ?>
                    </li>
                    <li>
                        <strong><?php _e('Total Scheduled Events:', 'job-killer'); ?></strong>
                        <?php echo count($cron_events); ?>
                    </li>
                    <li>
                        <strong><?php _e('Job Killer Events:', 'job-killer'); ?></strong>
                        <?php echo count($job_killer_events); ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.job-killer-schedule-status {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.job-killer-cron-info {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 4px;
}

.job-killer-cron-status ul {
    list-style: none;
    padding: 0;
}

.job-killer-cron-status li {
    padding: 5px 0;
    border-bottom: 1px solid #ddd;
}

.job-killer-cron-status li:last-child {
    border-bottom: none;
}

.job-killer-status-ok {
    color: #00a32a;
    font-weight: 600;
}

.job-killer-status-warning {
    color: #dba617;
    font-weight: 600;
}

.job-killer-status-error {
    color: #d63638;
    font-weight: 600;
}

details summary {
    cursor: pointer;
    color: #2271b1;
    font-size: 12px;
}

details pre {
    background: #fff;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 11px;
    margin-top: 5px;
    max-height: 200px;
    overflow-y: auto;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Reschedule cron
    $('#job-killer-reschedule').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true).text('Rescheduling...');
        
        $.ajax({
            url: jobKillerAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'job_killer_reschedule_cron',
                nonce: jobKillerAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    jobKiller.showNotice('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    jobKiller.showNotice('error', response.data);
                }
            },
            error: function() {
                jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
            },
            complete: function() {
                $button.prop('disabled', false).text('Reschedule Cron');
            }
        });
    });
    
    // Cleanup logs
    $('#job-killer-cleanup-logs').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true).text('Cleaning up...');
        
        $.ajax({
            url: jobKillerAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'job_killer_cleanup_logs_now',
                nonce: jobKillerAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    jobKiller.showNotice('success', response.data.message);
                } else {
                    jobKiller.showNotice('error', response.data);
                }
            },
            error: function() {
                jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
            },
            complete: function() {
                $button.prop('disabled', false).text('Cleanup Logs Now');
            }
        });
    });
    
    // Auto-refresh next run times every minute
    setInterval(function() {
        location.reload();
    }, 60000);
});
</script>