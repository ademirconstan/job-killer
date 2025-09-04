/**
 * Job Killer Admin JavaScript
 */

(function($) {
    'use strict';

    // Global variables
    var jobKiller = {
        init: function() {
            this.bindEvents();
            this.initComponents();
        },

        bindEvents: function() {
            // Feed management
            $(document).on('click', '.job-killer-test-feed', this.testFeed);
            $(document).on('click', '.job-killer-save-feed', this.saveFeed);
            $(document).on('click', '.job-killer-delete-feed', this.deleteFeed);
            $(document).on('click', '.job-killer-toggle-feed', this.toggleFeed);
            $(document).on('click', '.job-killer-import-feed', this.importFeed);

            // Scheduling
            $(document).on('click', '.job-killer-run-import', this.runImport);
            $(document).on('change', '#job-killer-cron-interval', this.updateSchedule);

            // Logs
            $(document).on('click', '.job-killer-clear-logs', this.clearLogs);
            $(document).on('click', '.job-killer-export-logs', this.exportLogs);
            $(document).on('change', '.job-killer-log-filter', this.filterLogs);

            // Settings
            $(document).on('click', '.job-killer-reset-settings', this.resetSettings);
            $(document).on('click', '.job-killer-export-settings', this.exportSettings);
            $(document).on('click', '.job-killer-import-settings', this.importSettings);

            // Modals
            $(document).on('click', '[data-modal]', this.openModal);
            $(document).on('click', '.job-killer-modal-close', this.closeModal);
            $(document).on('click', '.job-killer-modal', function(e) {
                if (e.target === this) {
                    jobKiller.closeModal.call(this, e);
                }
            });

            // Setup wizard
            $(document).on('click', '.job-killer-setup-next', this.setupNext);
            $(document).on('click', '.job-killer-setup-prev', this.setupPrev);
            $(document).on('click', '.job-killer-setup-complete', this.setupComplete);
            $(document).on('click', '.job-killer-setup-skip', this.setupSkip);

            // Chart data refresh
            $(document).on('change', '#job-killer-chart-days', this.refreshChart);
        },

        initComponents: function() {
            // Initialize Select2
            if ($.fn.select2) {
                $('.job-killer-select2').select2({
                    width: '100%'
                });
            }

            // Initialize dashboard chart
            if (typeof Chart !== 'undefined' && $('#job-killer-chart').length) {
                this.initChart();
            }

            // Auto-refresh dashboard stats
            if ($('.job-killer-dashboard').length) {
                setInterval(this.refreshStats, 60000); // Refresh every minute
            }
        },

        testFeed: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $form = $button.closest('form');
            var url = $form.find('input[name="feed_url"]').val();

            if (!url) {
                jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                return;
            }

            $button.prop('disabled', true).html('<span class="job-killer-loading"></span> ' + jobKillerAdmin.strings.testing_feed);

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
                        jobKiller.showNotice('success', response.data.message);
                        
                        // Show sample jobs if available
                        if (response.data.sample_jobs && response.data.sample_jobs.length > 0) {
                            jobKiller.showSampleJobs(response.data.sample_jobs);
                        }
                    } else {
                        jobKiller.showNotice('error', response.data);
                    }
                },
                error: function() {
                    jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                },
                complete: function() {
                    $button.prop('disabled', false).html(jobKillerAdmin.strings.test_successful);
                }
            });
        },

        saveFeed: function(e) {
            e.preventDefault();
            
            var $form = $(this).closest('form');
            var formData = $form.serialize();

            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: formData + '&action=job_killer_save_feed&nonce=' + jobKillerAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        jobKiller.showNotice('success', response.data.message);
                        location.reload();
                    } else {
                        jobKiller.showNotice('error', response.data);
                    }
                },
                error: function() {
                    jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                }
            });
        },

        deleteFeed: function(e) {
            e.preventDefault();
            
            if (!confirm(jobKillerAdmin.strings.confirm_delete)) {
                return;
            }

            var feedId = $(this).data('feed-id');

            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_delete_feed',
                    nonce: jobKillerAdmin.nonce,
                    feed_id: feedId
                },
                success: function(response) {
                    if (response.success) {
                        jobKiller.showNotice('success', response.data.message);
                        $('[data-feed-id="' + feedId + '"]').closest('tr').fadeOut();
                    } else {
                        jobKiller.showNotice('error', response.data);
                    }
                },
                error: function() {
                    jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                }
            });
        },

        toggleFeed: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var feedId = $button.data('feed-id');

            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_toggle_feed',
                    nonce: jobKillerAdmin.nonce,
                    feed_id: feedId
                },
                success: function(response) {
                    if (response.success) {
                        jobKiller.showNotice('success', response.data.message);
                        
                        var $status = $button.closest('tr').find('.job-killer-feed-status');
                        if (response.data.active) {
                            $status.removeClass('inactive').addClass('active').text('Active');
                            $button.text('Deactivate');
                        } else {
                            $status.removeClass('active').addClass('inactive').text('Inactive');
                            $button.text('Activate');
                        }
                    } else {
                        jobKiller.showNotice('error', response.data);
                    }
                },
                error: function() {
                    jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                }
            });
        },

        importFeed: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var feedId = $button.data('feed-id');

            $button.prop('disabled', true).html('<span class="job-killer-loading"></span> ' + jobKillerAdmin.strings.importing);

            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_import_feed',
                    nonce: jobKillerAdmin.nonce,
                    feed_id: feedId
                },
                success: function(response) {
                    if (response.success) {
                        jobKiller.showNotice('success', response.data.message);
                        jobKiller.refreshStats();
                    } else {
                        jobKiller.showNotice('error', response.data);
                    }
                },
                error: function() {
                    jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                },
                complete: function() {
                    $button.prop('disabled', false).html('Import Now');
                }
            });
        },

        runImport: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            $button.prop('disabled', true).html('<span class="job-killer-loading"></span> ' + jobKillerAdmin.strings.importing);

            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_run_import',
                    nonce: jobKillerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        jobKiller.showNotice('success', response.data.message);
                        jobKiller.refreshStats();
                    } else {
                        jobKiller.showNotice('error', response.data);
                    }
                },
                error: function() {
                    jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                },
                complete: function() {
                    $button.prop('disabled', false).html('Run Import Now');
                }
            });
        },

        updateSchedule: function(e) {
            var interval = $(this).val();

            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_update_schedule',
                    nonce: jobKillerAdmin.nonce,
                    interval: interval
                },
                success: function(response) {
                    if (response.success) {
                        jobKiller.showNotice('success', response.data.message);
                        
                        // Update next run time
                        if (response.data.next_run) {
                            var nextRun = new Date(response.data.next_run * 1000);
                            $('#job-killer-next-run').text(nextRun.toLocaleString());
                        }
                    } else {
                        jobKiller.showNotice('error', response.data);
                    }
                },
                error: function() {
                    jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                }
            });
        },

        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear the logs?')) {
                return;
            }

            var type = $(this).data('type') || '';

            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_clear_logs',
                    nonce: jobKillerAdmin.nonce,
                    type: type
                },
                success: function(response) {
                    if (response.success) {
                        jobKiller.showNotice('success', response.data.message);
                        location.reload();
                    } else {
                        jobKiller.showNotice('error', response.data);
                    }
                },
                error: function() {
                    jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                }
            });
        },

        exportLogs: function(e) {
            e.preventDefault();
            
            var filters = {
                type: $('#log-filter-type').val() || '',
                source: $('#log-filter-source').val() || '',
                date_from: $('#log-filter-date-from').val() || '',
                date_to: $('#log-filter-date-to').val() || ''
            };

            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: $.extend(filters, {
                    action: 'job_killer_export_logs',
                    nonce: jobKillerAdmin.nonce
                }),
                success: function(response) {
                    if (response.success) {
                        jobKiller.showNotice('success', response.data.message);
                        
                        // Trigger download
                        var link = document.createElement('a');
                        link.href = response.data.download_url;
                        link.download = response.data.filename;
                        link.click();
                    } else {
                        jobKiller.showNotice('error', response.data);
                    }
                },
                error: function() {
                    jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                }
            });
        },

        filterLogs: function() {
            var filters = {
                type: $('#log-filter-type').val() || '',
                source: $('#log-filter-source').val() || '',
                date_from: $('#log-filter-date-from').val() || '',
                date_to: $('#log-filter-date-to').val() || ''
            };

            var url = new URL(window.location);
            Object.keys(filters).forEach(key => {
                if (filters[key]) {
                    url.searchParams.set(key, filters[key]);
                } else {
                    url.searchParams.delete(key);
                }
            });

            window.location.href = url.toString();
        },

        resetSettings: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to reset all settings to defaults?')) {
                return;
            }

            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_reset_settings',
                    nonce: jobKillerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        jobKiller.showNotice('success', response.data.message);
                        location.reload();
                    } else {
                        jobKiller.showNotice('error', response.data);
                    }
                },
                error: function() {
                    jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                }
            });
        },

        exportSettings: function(e) {
            e.preventDefault();

            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_export_settings',
                    nonce: jobKillerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Trigger download
                        var blob = new Blob([response.data.data], { type: 'application/json' });
                        var link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = response.data.filename;
                        link.click();
                        
                        jobKiller.showNotice('success', 'Settings exported successfully!');
                    } else {
                        jobKiller.showNotice('error', response.data);
                    }
                },
                error: function() {
                    jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                }
            });
        },

        importSettings: function(e) {
            e.preventDefault();
            
            var fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = '.json';
            
            fileInput.onchange = function(event) {
                var file = event.target.files[0];
                if (!file) return;
                
                var reader = new FileReader();
                reader.onload = function(e) {
                    $.ajax({
                        url: jobKillerAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'job_killer_import_settings',
                            nonce: jobKillerAdmin.nonce,
                            data: e.target.result
                        },
                        success: function(response) {
                            if (response.success) {
                                jobKiller.showNotice('success', response.data.message);
                                location.reload();
                            } else {
                                jobKiller.showNotice('error', response.data);
                            }
                        },
                        error: function() {
                            jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                        }
                    });
                };
                reader.readAsText(file);
            };
            
            fileInput.click();
        },

        openModal: function(e) {
            e.preventDefault();
            var modalId = $(this).data('modal');
            $('#' + modalId).fadeIn();
        },

        closeModal: function(e) {
            e.preventDefault();
            $(this).closest('.job-killer-modal').fadeOut();
        },

        showNotice: function(type, message) {
            var $notice = $('<div class="job-killer-notice ' + type + '">' + message + '</div>');
            $('.job-killer-admin').prepend($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        showSampleJobs: function(jobs) {
            var html = '<div class="job-killer-sample-jobs"><h4>Sample Jobs Found:</h4><ul>';
            jobs.forEach(function(job) {
                html += '<li><strong>' + job.title + '</strong>';
                if (job.company) html += ' - ' + job.company;
                if (job.location) html += ' (' + job.location + ')';
                html += '</li>';
            });
            html += '</ul></div>';
            
            $('.job-killer-test-results').html(html).show();
        },

        refreshStats: function() {
            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_get_stats',
                    nonce: jobKillerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update stat cards
                        Object.keys(response.data).forEach(function(key) {
                            $('.job-killer-stat-' + key + ' .job-killer-stat-number').text(response.data[key]);
                        });
                    }
                }
            });
        },

        initChart: function() {
            var ctx = document.getElementById('job-killer-chart').getContext('2d');
            
            // Get chart data via AJAX
            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_get_chart_data',
                    nonce: jobKillerAdmin.nonce,
                    days: 30
                },
                success: function(response) {
                    if (response.success) {
                        jobKiller.renderChart(ctx, response.data);
                    }
                }
            });
        },

        renderChart: function(ctx, data) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(function(item) {
                        return new Date(item.date).toLocaleDateString();
                    }),
                    datasets: [{
                        label: 'Jobs Imported',
                        data: data.map(function(item) {
                            return item.count;
                        }),
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },

        refreshChart: function() {
            var days = $(this).val();
            
            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_get_chart_data',
                    nonce: jobKillerAdmin.nonce,
                    days: days
                },
                success: function(response) {
                    if (response.success) {
                        // Destroy existing chart and create new one
                        var ctx = document.getElementById('job-killer-chart').getContext('2d');
                        Chart.getChart(ctx)?.destroy();
                        jobKiller.renderChart(ctx, response.data);
                    }
                }
            });
        },

        // Setup Wizard Functions
        setupNext: function(e) {
            e.preventDefault();
            var currentStep = parseInt($('.job-killer-setup-step.active').data('step'));
            var nextStep = currentStep + 1;
            
            if (nextStep <= 4) {
                jobKiller.showSetupStep(nextStep);
            }
        },

        setupPrev: function(e) {
            e.preventDefault();
            var currentStep = parseInt($('.job-killer-setup-step.active').data('step'));
            var prevStep = currentStep - 1;
            
            if (prevStep >= 1) {
                jobKiller.showSetupStep(prevStep);
            }
        },

        showSetupStep: function(step) {
            $('.job-killer-setup-step').removeClass('active');
            $('.job-killer-setup-step[data-step="' + step + '"]').addClass('active');
            
            $('.job-killer-setup-content > div').hide();
            $('.job-killer-setup-step-' + step).show();
            
            // Update navigation
            $('.job-killer-setup-prev').toggle(step > 1);
            $('.job-killer-setup-next').toggle(step < 4);
            $('.job-killer-setup-complete').toggle(step === 4);
        },

        setupComplete: function(e) {
            e.preventDefault();
            
            var setupData = {
                settings: {},
                feed: {}
            };
            
            // Collect form data
            $('.job-killer-setup-form input, .job-killer-setup-form select').each(function() {
                var name = $(this).attr('name');
                var value = $(this).val();
                
                if (name && value) {
                    if (name.startsWith('setting_')) {
                        setupData.settings[name.replace('setting_', '')] = value;
                    } else if (name.startsWith('feed_')) {
                        setupData.feed[name.replace('feed_', '')] = value;
                    }
                }
            });
            
            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_complete_setup',
                    nonce: jobKillerAdmin.nonce,
                    setup_data: setupData
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        jobKiller.showNotice('error', response.data);
                    }
                },
                error: function() {
                    jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                }
            });
        },

        setupSkip: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to skip the setup wizard?')) {
                return;
            }
            
            $.ajax({
                url: jobKillerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_skip_setup',
                    nonce: jobKillerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        jobKiller.showNotice('error', response.data);
                    }
                },
                error: function() {
                    jobKiller.showNotice('error', jobKillerAdmin.strings.error_occurred);
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        jobKiller.init();
    });

    // Make jobKiller available globally
    window.jobKiller = jobKiller;

})(jQuery);