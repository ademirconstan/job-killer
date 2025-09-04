/**
 * Job Killer Frontend JavaScript
 */

(function($) {
    'use strict';

    // Global variables
    var jobKillerFrontend = {
        init: function() {
            this.bindEvents();
            this.initComponents();
        },

        bindEvents: function() {
            // Search functionality
            $(document).on('submit', '.job-killer-search-form form', this.handleSearch);
            $(document).on('click', '#job-search-submit', this.handleAjaxSearch);
            $(document).on('change', '.job-killer-filters select', this.handleFilterChange);
            $(document).on('change', '#job-filter-remote', this.handleFilterChange);
            
            // Load more functionality
            $(document).on('click', '.job-killer-load-more', this.loadMoreJobs);
            
            // Job item interactions
            $(document).on('click', '.job-item', this.handleJobClick);
            $(document).on('mouseenter', '.job-item', this.handleJobHover);
            
            // Responsive menu toggle
            $(document).on('click', '.job-killer-mobile-toggle', this.toggleMobileFilters);
        },

        initComponents: function() {
            // Initialize any third-party components
            this.initSelect2();
            this.initDatePickers();
            this.initTooltips();
            
            // Set up infinite scroll if enabled
            if ($('.job-killer-infinite-scroll').length) {
                this.initInfiniteScroll();
            }
            
            // Initialize search suggestions
            if ($('#job-search-keywords').length) {
                this.initSearchSuggestions();
            }
        },

        handleSearch: function(e) {
            // Let the form submit naturally for non-AJAX searches
            return true;
        },

        handleAjaxSearch: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $container = $button.closest('.job-killer-list-wrapper');
            var $results = $container.find('#job-killer-jobs');
            
            // Collect search parameters
            var searchData = {
                keywords: $('#job-search-keywords').val() || '',
                location: $('#job-search-location').val() || '',
                category: $('#job-filter-category').val() || '',
                type: $('#job-filter-type').val() || '',
                region: $('#job-filter-region').val() || '',
                remote: $('#job-filter-remote').is(':checked')
            };
            
            // Show loading state
            $button.prop('disabled', true).html('<span class="job-killer-loading"></span> Searching...');
            $results.addClass('job-killer-loading-overlay');
            
            $.ajax({
                url: jobKiller.ajaxUrl,
                type: 'POST',
                data: $.extend(searchData, {
                    action: 'job_killer_search',
                    nonce: jobKiller.nonce
                }),
                success: function(response) {
                    if (response.success) {
                        $results.html(response.data.html);
                        
                        // Update results count
                        jobKillerFrontend.updateResultsCount(response.data.found);
                        
                        // Scroll to results
                        $('html, body').animate({
                            scrollTop: $results.offset().top - 100
                        }, 500);
                    } else {
                        jobKillerFrontend.showNotice('error', 'Search failed. Please try again.');
                    }
                },
                error: function() {
                    jobKillerFrontend.showNotice('error', 'An error occurred. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).html('Search');
                    $results.removeClass('job-killer-loading-overlay');
                }
            });
        },

        handleFilterChange: function() {
            // Auto-trigger search when filters change
            if ($('#job-search-submit').length) {
                $('#job-search-submit').click();
            }
        },

        loadMoreJobs: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var page = parseInt($button.data('page')) || 2;
            var $container = $button.closest('.job-killer-list-wrapper');
            var $jobsList = $container.find('.jobs-list');
            
            // Collect current filters
            var filters = {
                keywords: $('#job-search-keywords').val() || '',
                location: $('#job-search-location').val() || '',
                category: $('#job-filter-category').val() || '',
                type: $('#job-filter-type').val() || '',
                region: $('#job-filter-region').val() || '',
                remote: $('#job-filter-remote').is(':checked')
            };
            
            $button.prop('disabled', true).html('<span class="job-killer-loading"></span> Loading...');
            
            $.ajax({
                url: jobKiller.ajaxUrl,
                type: 'POST',
                data: $.extend(filters, {
                    action: 'job_killer_load_more',
                    nonce: jobKiller.nonce,
                    page: page
                }),
                success: function(response) {
                    if (response.success) {
                        $jobsList.append(response.data.html);
                        
                        if (response.data.has_more) {
                            $button.data('page', page + 1);
                        } else {
                            $button.hide();
                        }
                        
                        // Animate new items
                        $jobsList.find('.job-item').slice(-10).each(function(index) {
                            $(this).css('opacity', '0').delay(index * 100).animate({
                                opacity: 1
                            }, 300);
                        });
                    } else {
                        jobKillerFrontend.showNotice('error', 'Failed to load more jobs.');
                    }
                },
                error: function() {
                    jobKillerFrontend.showNotice('error', 'An error occurred while loading more jobs.');
                },
                complete: function() {
                    $button.prop('disabled', false).html('Load More Jobs');
                }
            });
        },

        handleJobClick: function(e) {
            // Don't trigger if clicking on links or buttons
            if ($(e.target).is('a, button') || $(e.target).closest('a, button').length) {
                return;
            }
            
            var $jobItem = $(this);
            var jobUrl = $jobItem.find('.job-title a').attr('href');
            
            if (jobUrl) {
                // Track job view
                jobKillerFrontend.trackJobView($jobItem.data('job-id'));
                
                // Navigate to job
                window.location.href = jobUrl;
            }
        },

        handleJobHover: function() {
            var $jobItem = $(this);
            
            // Preload job details for faster navigation
            if (!$jobItem.data('preloaded')) {
                var jobUrl = $jobItem.find('.job-title a').attr('href');
                if (jobUrl) {
                    $('<link>').attr({
                        rel: 'prefetch',
                        href: jobUrl
                    }).appendTo('head');
                    
                    $jobItem.data('preloaded', true);
                }
            }
        },

        toggleMobileFilters: function(e) {
            e.preventDefault();
            
            var $toggle = $(this);
            var $filters = $('.job-killer-filters');
            
            $filters.slideToggle(300);
            $toggle.toggleClass('active');
            
            var text = $toggle.hasClass('active') ? 'Hide Filters' : 'Show Filters';
            $toggle.text(text);
        },

        initSelect2: function() {
            if ($.fn.select2) {
                $('.job-killer-select2').select2({
                    width: '100%',
                    placeholder: function() {
                        return $(this).data('placeholder');
                    }
                });
            }
        },

        initDatePickers: function() {
            if ($.fn.datepicker) {
                $('.job-killer-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true
                });
            }
        },

        initTooltips: function() {
            if ($.fn.tooltip) {
                $('[data-tooltip]').tooltip({
                    content: function() {
                        return $(this).data('tooltip');
                    }
                });
            }
        },

        initInfiniteScroll: function() {
            var loading = false;
            var page = 2;
            
            $(window).on('scroll', function() {
                if (loading) return;
                
                var $window = $(window);
                var $document = $(document);
                
                if ($window.scrollTop() + $window.height() >= $document.height() - 1000) {
                    loading = true;
                    
                    // Trigger load more
                    var $loadMoreBtn = $('.job-killer-load-more');
                    if ($loadMoreBtn.length && $loadMoreBtn.is(':visible')) {
                        $loadMoreBtn.click();
                        
                        // Reset loading flag after request completes
                        setTimeout(function() {
                            loading = false;
                        }, 2000);
                    } else {
                        loading = false;
                    }
                }
            });
        },

        initSearchSuggestions: function() {
            var $input = $('#job-search-keywords');
            var $suggestions = $('<div class="job-killer-search-suggestions"></div>');
            
            $input.after($suggestions);
            
            var searchTimeout;
            
            $input.on('input', function() {
                var query = $(this).val();
                
                clearTimeout(searchTimeout);
                
                if (query.length < 3) {
                    $suggestions.hide();
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: jobKiller.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'job_killer_search_suggestions',
                            nonce: jobKiller.nonce,
                            query: query
                        },
                        success: function(response) {
                            if (response.success && response.data.length > 0) {
                                var html = '';
                                response.data.forEach(function(suggestion) {
                                    html += '<div class="suggestion-item" data-value="' + suggestion + '">' + suggestion + '</div>';
                                });
                                
                                $suggestions.html(html).show();
                            } else {
                                $suggestions.hide();
                            }
                        }
                    });
                }, 300);
            });
            
            // Handle suggestion clicks
            $(document).on('click', '.suggestion-item', function() {
                var value = $(this).data('value');
                $input.val(value);
                $suggestions.hide();
                $('#job-search-submit').click();
            });
            
            // Hide suggestions when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.job-killer-search-suggestions, #job-search-keywords').length) {
                    $suggestions.hide();
                }
            });
        },

        updateResultsCount: function(count) {
            var $counter = $('.job-killer-results-count');
            if ($counter.length) {
                var text = count === 1 ? '1 job found' : count + ' jobs found';
                $counter.text(text);
            }
        },

        trackJobView: function(jobId) {
            if (!jobId) return;
            
            $.ajax({
                url: jobKiller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'job_killer_track_view',
                    nonce: jobKiller.nonce,
                    job_id: jobId
                }
            });
        },

        showNotice: function(type, message) {
            var $notice = $('<div class="job-killer-notice job-killer-notice-' + type + '">' + message + '</div>');
            
            // Find the best place to show the notice
            var $container = $('.job-killer-list-wrapper').first();
            if (!$container.length) {
                $container = $('body');
            }
            
            $container.prepend($notice);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Utility functions
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        throttle: function(func, limit) {
            var inThrottle;
            return function() {
                var args = arguments;
                var context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(function() {
                        inThrottle = false;
                    }, limit);
                }
            };
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        jobKillerFrontend.init();
    });

    // Make jobKillerFrontend available globally
    window.jobKillerFrontend = jobKillerFrontend;

})(jQuery);

// Vanilla JavaScript for critical functionality (no jQuery dependency)
(function() {
    'use strict';
    
    // Add loading states to forms
    document.addEventListener('submit', function(e) {
        if (e.target.classList.contains('job-killer-search-form')) {
            var submitBtn = e.target.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="job-killer-loading"></span> Searching...';
            }
        }
    });
    
    // Add click tracking for job applications
    document.addEventListener('click', function(e) {
        if (e.target.matches('.job-application-link') || e.target.closest('.job-application-link')) {
            var link = e.target.matches('.job-application-link') ? e.target : e.target.closest('.job-application-link');
            var jobId = link.dataset.jobId;
            
            if (jobId && typeof jobKiller !== 'undefined') {
                // Track application click
                fetch(jobKiller.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'job_killer_track_application',
                        nonce: jobKiller.nonce,
                        job_id: jobId
                    })
                });
            }
        }
    });
    
    // Lazy load job images
    if ('IntersectionObserver' in window) {
        var imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            var lazyImages = document.querySelectorAll('img[data-src]');
            lazyImages.forEach(function(img) {
                imageObserver.observe(img);
            });
        });
    }
    
    // Add keyboard navigation for job items
    document.addEventListener('keydown', function(e) {
        if (e.target.classList.contains('job-item') && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            var link = e.target.querySelector('.job-title a');
            if (link) {
                link.click();
            }
        }
    });
    
    // Add focus management for accessibility
    document.addEventListener('DOMContentLoaded', function() {
        var jobItems = document.querySelectorAll('.job-item');
        jobItems.forEach(function(item) {
            if (!item.hasAttribute('tabindex')) {
                item.setAttribute('tabindex', '0');
            }
            item.setAttribute('role', 'button');
            item.setAttribute('aria-label', 'View job details');
        });
    });
    
})();