<?php
/**
 * Admin API Test Template
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap job-killer-admin">
    <div class="job-killer-header">
        <h1><?php _e('API Testing & Debugging', 'job-killer'); ?></h1>
    </div>

    <!-- RSS Feed Testing -->
    <div class="job-killer-form">
        <h2><?php _e('RSS Feed Testing', 'job-killer'); ?></h2>
        <p class="description"><?php _e('Test RSS feeds to verify they are working correctly and preview the data that will be imported.', 'job-killer'); ?></p>
        
        <form id="job-killer-rss-test-form">
            <div class="job-killer-form-row">
                <div class="job-killer-form-group" style="flex: 2;">
                    <label for="rss_test_url"><?php _e('RSS Feed URL', 'job-killer'); ?></label>
                    <input type="url" id="rss_test_url" name="rss_url" placeholder="https://example.com/jobs.rss" required>
                </div>
                <div class="job-killer-form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="job-killer-btn job-killer-btn-primary">
                        <?php _e('Test RSS Feed', 'job-killer'); ?>
                    </button>
                </div>
            </div>
        </form>

        <div id="rss-test-results" class="job-killer-test-results" style="display: none; margin-top: 20px;"></div>
    </div>

    <!-- Connection Testing -->
    <div class="job-killer-form">
        <h2><?php _e('Connection Testing', 'job-killer'); ?></h2>
        <p class="description"><?php _e('Test various connection aspects to diagnose potential issues.', 'job-killer'); ?></p>
        
        <div class="job-killer-form-row">
            <div class="job-killer-form-group">
                <button type="button" class="job-killer-btn job-killer-btn-secondary" id="test-curl">
                    <?php _e('Test cURL', 'job-killer'); ?>
                </button>
            </div>
            <div class="job-killer-form-group">
                <button type="button" class="job-killer-btn job-killer-btn-secondary" id="test-xml-parsing">
                    <?php _e('Test XML Parsing', 'job-killer'); ?>
                </button>
            </div>
            <div class="job-killer-form-group">
                <button type="button" class="job-killer-btn job-killer-btn-secondary" id="test-database">
                    <?php _e('Test Database', 'job-killer'); ?>
                </button>
            </div>
            <div class="job-killer-form-group">
                <button type="button" class="job-killer-btn job-killer-btn-secondary" id="test-cron">
                    <?php _e('Test Cron', 'job-killer'); ?>
                </button>
            </div>
        </div>

        <div id="connection-test-results" class="job-killer-test-results" style="display: none; margin-top: 20px;"></div>
    </div>

    <!-- System Information -->
    <div class="job-killer-form">
        <h2><?php _e('System Information', 'job-killer'); ?></h2>
        <p class="description"><?php _e('View detailed system information for troubleshooting.', 'job-killer'); ?></p>
        
        <div class="job-killer-system-info">
            <?php
            $helper = new Job_Killer_Helper();
            $system_info = $helper->get_system_info();
            ?>
            
            <table class="job-killer-system-info-table">
                <tbody>
                    <tr>
                        <td><strong><?php _e('WordPress Version', 'job-killer'); ?></strong></td>
                        <td><?php echo esc_html($system_info['wordpress_version']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('PHP Version', 'job-killer'); ?></strong></td>
                        <td><?php echo esc_html($system_info['php_version']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Plugin Version', 'job-killer'); ?></strong></td>
                        <td><?php echo esc_html($system_info['plugin_version']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('MySQL Version', 'job-killer'); ?></strong></td>
                        <td><?php echo esc_html($system_info['mysql_version']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Server Software', 'job-killer'); ?></strong></td>
                        <td><?php echo esc_html($system_info['server_software']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Max Execution Time', 'job-killer'); ?></strong></td>
                        <td><?php echo esc_html($system_info['max_execution_time']); ?> seconds</td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Memory Limit', 'job-killer'); ?></strong></td>
                        <td><?php echo esc_html($system_info['memory_limit']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Upload Max Filesize', 'job-killer'); ?></strong></td>
                        <td><?php echo esc_html($system_info['upload_max_filesize']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('cURL Version', 'job-killer'); ?></strong></td>
                        <td><?php echo esc_html($system_info['curl_version']); ?></td>
                    </tr>
                </tbody>
            </table>

            <h4><?php _e('PHP Extensions', 'job-killer'); ?></h4>
            <table class="job-killer-system-info-table">
                <tbody>
                    <?php foreach ($system_info['extensions'] as $extension => $loaded): ?>
                    <tr>
                        <td><strong><?php echo esc_html($extension); ?></strong></td>
                        <td>
                            <span class="job-killer-status-<?php echo $loaded ? 'ok' : 'error'; ?>">
                                <?php echo $loaded ? __('Loaded', 'job-killer') : __('Not Loaded', 'job-killer'); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Debug Tools -->
    <div class="job-killer-form">
        <h2><?php _e('Debug Tools', 'job-killer'); ?></h2>
        <p class="description"><?php _e('Advanced debugging tools for developers and troubleshooting.', 'job-killer'); ?></p>
        
        <div class="job-killer-form-row">
            <div class="job-killer-form-group">
                <button type="button" class="job-killer-btn job-killer-btn-secondary" id="clear-cache">
                    <?php _e('Clear All Cache', 'job-killer'); ?>
                </button>
            </div>
            <div class="job-killer-form-group">
                <button type="button" class="job-killer-btn job-killer-btn-secondary" id="reset-cron">
                    <?php _e('Reset Cron Jobs', 'job-killer'); ?>
                </button>
            </div>
            <div class="job-killer-form-group">
                <button type="button" class="job-killer-btn job-killer-btn-secondary" id="test-import">
                    <?php _e('Test Import Process', 'job-killer'); ?>
                </button>
            </div>
            <div class="job-killer-form-group">
                <button type="button" class="job-killer-btn job-killer-btn-secondary" id="export-debug-info">
                    <?php _e('Export Debug Info', 'job-killer'); ?>
                </button>
            </div>
        </div>

        <div id="debug-results" class="job-killer-test-results" style="display: none; margin-top: 20px;"></div>
    </div>

    <!-- Live Log Viewer -->
    <div class="job-killer-form">
        <h2><?php _e('Live Log Viewer', 'job-killer'); ?></h2>
        <p class="description"><?php _e('View real-time log entries as they are created.', 'job-killer'); ?></p>
        
        <div class="job-killer-form-row">
            <div class="job-killer-form-group">
                <button type="button" class="job-killer-btn job-killer-btn-primary" id="start-live-logs">
                    <?php _e('Start Live View', 'job-killer'); ?>
                </button>
            </div>
            <div class="job-killer-form-group">
                <button type="button" class="job-killer-btn job-killer-btn-secondary" id="stop-live-logs" disabled>
                    <?php _e('Stop Live View', 'job-killer'); ?>
                </button>
            </div>
            <div class="job-killer-form-group">
                <button type="button" class="job-killer-btn job-killer-btn-secondary" id="clear-live-logs">
                    <?php _e('Clear Display', 'job-killer'); ?>
                </button>
            </div>
        </div>

        <div id="live-logs-container" style="display: none; margin-top: 20px;">
            <div id="live-logs" class="job-killer-live-logs"></div>
        </div>
    </div>
</div>

<style>
.job-killer-system-info-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.job-killer-system-info-table td {
    padding: 8px 12px;
    border-bottom: 1px solid #f0f0f1;
}

.job-killer-system-info-table td:first-child {
    width: 200px;
    background: #f9f9f9;
}

.job-killer-status-ok {
    color: #00a32a;
    font-weight: 600;
}

.job-killer-status-error {
    color: #d63638;
    font-weight: 600;
}

.job-killer-test-results {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
}

.job-killer-test-results h4 {
    margin-top: 0;
    color: #1d2327;
}

.job-killer-test-results pre {
    background: #fff;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow-x: auto;
    font-size: 12px;
    line-height: 1.4;
}

.job-killer-live-logs {
    background: #1e1e1e;
    color: #f0f0f0;
    padding: 15px;
    border-radius: 4px;
    height: 400px;
    overflow-y: auto;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.4;
}

.job-killer-live-logs .log-entry {
    margin-bottom: 5px;
    padding: 2px 0;
}

.job-killer-live-logs .log-entry.error {
    color: #ff6b6b;
}

.job-killer-live-logs .log-entry.success {
    color: #51cf66;
}

.job-killer-live-logs .log-entry.warning {
    color: #ffd43b;
}

.job-killer-live-logs .log-entry.info {
    color: #74c0fc;
}

.job-killer-live-logs .log-timestamp {
    color: #868e96;
    margin-right: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    var liveLogsInterval;
    var lastLogId = 0;
    
    // RSS Feed Testing
    $('#job-killer-rss-test-form').on('submit', function(e) {
        e.preventDefault();
        
        var url = $('#rss_test_url').val();
        if (!url) return;
        
        $('#rss-test-results').html('<p>Testing RSS feed...</p>').show();
        
        $.ajax({
            url: jobKillerAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'job_killer_test_feed',
                nonce: jobKillerAdmin.nonce,
                url: url
            },
            success: function(response) {
                if (response.success) {
                    var html = '<div class="job-killer-notice success">';
                    html += '<h4>RSS Feed Test Successful!</h4>';
                    html += '<p>' + response.data.message + '</p>';
                    html += '<p><strong>Provider:</strong> ' + response.data.provider_name + '</p>';
                    
                    if (response.data.api_url) {
                        html += '<p><strong>API URL:</strong> <code>' + response.data.api_url + '</code></p>';
                    }
                    
                    if (response.data.sample_jobs && response.data.sample_jobs.length > 0) {
                        html += '<h4>Sample Jobs:</h4><ul>';
                        response.data.sample_jobs.forEach(function(job) {
                            html += '<li><strong>' + job.title + '</strong>';
                            if (job.company) html += ' - ' + job.company;
                            if (job.location) html += ' (' + job.location + ')';
                            html += '</li>';
                        });
                        html += '</ul>';
                    }
                    
                    html += '</div>';
                    $('#rss-test-results').html(html);
                } else {
                    $('#rss-test-results').html(
                        '<div class="job-killer-notice error">' +
                        '<h4>RSS Feed Test Failed</h4>' +
                        '<p>' + response.data + '</p>' +
                        '</div>'
                    );
                }
            },
            error: function() {
                $('#rss-test-results').html(
                    '<div class="job-killer-notice error">' +
                    '<h4>RSS Feed Test Failed</h4>' +
                    '<p>An error occurred while testing the feed.</p>' +
                    '</div>'
                );
            }
        });
    });
    
    // Connection Tests
    $('#test-curl').on('click', function() {
        testConnection('curl', 'Testing cURL functionality...');
    });
    
    $('#test-xml-parsing').on('click', function() {
        testConnection('xml', 'Testing XML parsing capabilities...');
    });
    
    $('#test-database').on('click', function() {
        testConnection('database', 'Testing database connection...');
    });
    
    $('#test-cron').on('click', function() {
        testConnection('cron', 'Testing cron job functionality...');
    });
    
    function testConnection(type, message) {
        $('#connection-test-results').html('<p>' + message + '</p>').show();
        
        $.ajax({
            url: jobKillerAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'job_killer_test_connection',
                nonce: jobKillerAdmin.nonce,
                test_type: type
            },
            success: function(response) {
                if (response.success) {
                    $('#connection-test-results').html(
                        '<div class="job-killer-notice success">' +
                        '<h4>Test Successful</h4>' +
                        '<pre>' + JSON.stringify(response.data, null, 2) + '</pre>' +
                        '</div>'
                    );
                } else {
                    $('#connection-test-results').html(
                        '<div class="job-killer-notice error">' +
                        '<h4>Test Failed</h4>' +
                        '<p>' + response.data + '</p>' +
                        '</div>'
                    );
                }
            },
            error: function() {
                $('#connection-test-results').html(
                    '<div class="job-killer-notice error">' +
                    '<h4>Test Failed</h4>' +
                    '<p>An error occurred during the test.</p>' +
                    '</div>'
                );
            }
        });
    }
    
    // Debug Tools
    $('#clear-cache').on('click', function() {
        debugAction('clear_cache', 'Clearing all cache...');
    });
    
    $('#reset-cron').on('click', function() {
        debugAction('reset_cron', 'Resetting cron jobs...');
    });
    
    $('#test-import').on('click', function() {
        debugAction('test_import', 'Testing import process...');
    });
    
    $('#export-debug-info').on('click', function() {
        debugAction('export_debug', 'Exporting debug information...');
    });
    
    function debugAction(action, message) {
        $('#debug-results').html('<p>' + message + '</p>').show();
        
        $.ajax({
            url: jobKillerAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'job_killer_debug_action',
                nonce: jobKillerAdmin.nonce,
                debug_action: action
            },
            success: function(response) {
                if (response.success) {
                    var html = '<div class="job-killer-notice success">';
                    html += '<h4>Action Completed</h4>';
                    
                    if (typeof response.data === 'string') {
                        html += '<p>' + response.data + '</p>';
                    } else {
                        html += '<pre>' + JSON.stringify(response.data, null, 2) + '</pre>';
                    }
                    
                    html += '</div>';
                    $('#debug-results').html(html);
                } else {
                    $('#debug-results').html(
                        '<div class="job-killer-notice error">' +
                        '<h4>Action Failed</h4>' +
                        '<p>' + response.data + '</p>' +
                        '</div>'
                    );
                }
            },
            error: function() {
                $('#debug-results').html(
                    '<div class="job-killer-notice error">' +
                    '<h4>Action Failed</h4>' +
                    '<p>An error occurred during the action.</p>' +
                    '</div>'
                );
            }
        });
    }
    
    // Live Log Viewer
    $('#start-live-logs').on('click', function() {
        $('#live-logs-container').show();
        $('#start-live-logs').prop('disabled', true);
        $('#stop-live-logs').prop('disabled', false);
        
        // Start polling for new logs
        liveLogsInterval = setInterval(function() {
            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_get_live_logs',
                    nonce: jobKillerAdmin.nonce,
                    last_id: lastLogId
                },
                success: function(response) {
                    if (response.success && response.data.logs.length > 0) {
                        response.data.logs.forEach(function(log) {
                            var timestamp = new Date(log.created_at).toLocaleTimeString();
                            var logHtml = '<div class="log-entry ' + log.type + '">';
                            logHtml += '<span class="log-timestamp">[' + timestamp + ']</span>';
                            logHtml += '<strong>' + log.type.toUpperCase() + '</strong> ';
                            logHtml += log.source + ': ' + log.message;
                            logHtml += '</div>';
                            
                            $('#live-logs').append(logHtml);
                            lastLogId = Math.max(lastLogId, parseInt(log.id));
                        });
                        
                        // Auto-scroll to bottom
                        $('#live-logs').scrollTop($('#live-logs')[0].scrollHeight);
                    }
                }
            });
        }, 2000); // Poll every 2 seconds
    });
    
    $('#stop-live-logs').on('click', function() {
        clearInterval(liveLogsInterval);
        $('#start-live-logs').prop('disabled', false);
        $('#stop-live-logs').prop('disabled', true);
    });
    
    $('#clear-live-logs').on('click', function() {
        $('#live-logs').empty();
        lastLogId = 0;
    });
});
</script>