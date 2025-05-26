/**
 * WP Domain Mapping admin JavaScript
 */
jQuery(document).ready(function($) {

    // Helper function for showing notices
    function showNotice(selector, message, type) {
        $(selector)
            .removeClass('notice-success notice-error notice-warning notice-info')
            .addClass('notice-' + type)
            .html('<p>' + message + '</p>')
            .show()
            .delay(5000)
            .fadeOut();
    }

    // Domain form validation
    $('#edit-domain-form').on('submit', function(e) {
        var domain = $('#domain').val().trim();
        var blogId = $('#blog_id').val().trim();

        if (!domain) {
            e.preventDefault();
            showNotice('#edit-domain-status', wpDomainMapping.messages.domainRequired, 'error');
            $('#domain').focus();
            return false;
        }

        if (!blogId) {
            e.preventDefault();
            showNotice('#edit-domain-status', wpDomainMapping.messages.siteRequired, 'error');
            $('#blog_id').focus();
            return false;
        }

        // Enhanced domain format validation - more flexible validation
        var domainPattern = /^[a-zA-Z0-9][a-zA-Z0-9.-]*[a-zA-Z0-9]\.[a-zA-Z]{2,}$/;
        if (!domainPattern.test(domain)) {
            e.preventDefault();
            showNotice('#edit-domain-status', 'Please enter a valid domain format (e.g., example.com, www.example.com)', 'error');
            $('#domain').focus();
            return false;
        }
    });

    // Check all domains functionality
    $('#select-all').on('change', function() {
        $('.domain-checkbox').prop('checked', this.checked);
    });

    // Update select all when individual checkboxes change
    $('.domain-checkbox').on('change', function() {
        if (!this.checked) {
            $('#select-all').prop('checked', false);
        } else if ($('.domain-checkbox:checked').length === $('.domain-checkbox').length) {
            $('#select-all').prop('checked', true);
        }
    });

    // Handle domain edit/add form submission via AJAX
    $('#edit-domain-form').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serializeArray();
        formData.push({name: 'action', value: 'dm_handle_actions'});
        formData.push({name: 'action_type', value: 'save'});
        formData.push({name: 'nonce', value: wpDomainMapping.nonce});

        $('#edit-domain-status').html('<p>' + wpDomainMapping.messages.saving + '</p>').show();

        $.ajax({
            url: wpDomainMapping.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotice('#edit-domain-status', response.data, 'success');
                    setTimeout(function() {
                        // Redirect to domains page without edit parameter
                        var url = new URL(window.location);
                        url.searchParams.delete('edit_domain');
                        window.location.href = url.toString();
                    }, 1500);
                } else {
                    showNotice('#edit-domain-status', response.data || wpDomainMapping.messages.error, 'error');
                }
            },
            error: function() {
                showNotice('#edit-domain-status', wpDomainMapping.messages.error, 'error');
            }
        });
    });

    // Handle bulk domain actions
    $('#domain-list-form').on('submit', function(e) {
        e.preventDefault();

        var selectedDomains = [];
        $('.domain-checkbox:checked').each(function() {
            selectedDomains.push($(this).val());
        });

        if (selectedDomains.length === 0) {
            showNotice('#domain-status', wpDomainMapping.messages.noSelection, 'error');
            return;
        }

        var action = $('#bulk-action-selector-top').val();
        if (action === '-1') {
            showNotice('#domain-status', 'Please select an action.', 'error');
            return;
        }

        if (action === 'delete' && !confirm('Are you sure you want to delete the selected domains? This action cannot be undone.')) {
            return;
        }

        $('#domain-status').html('<p>' + wpDomainMapping.messages.processing + '</p>').show();

        $.ajax({
            url: wpDomainMapping.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dm_handle_actions',
                action_type: action,
                domains: selectedDomains,
                nonce: wpDomainMapping.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('#domain-status', response.data, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('#domain-status', response.data || wpDomainMapping.messages.error, 'error');
                }
            },
            error: function() {
                showNotice('#domain-status', wpDomainMapping.messages.error, 'error');
            }
        });
    });

    // Single domain delete functionality
    $('.domain-delete-button').on('click', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to delete this domain? This action cannot be undone.')) {
            return;
        }

        var domain = $(this).data('domain');
        var $row = $(this).closest('tr');

        $.ajax({
            url: wpDomainMapping.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dm_handle_actions',
                action_type: 'delete',
                domain: domain,
                nonce: wpDomainMapping.nonce
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(function() {
                        $(this).remove();
                    });
                    showNotice('#domain-status', response.data, 'success');
                } else {
                    showNotice('#domain-status', response.data || wpDomainMapping.messages.error, 'error');
                }
            },
            error: function() {
                showNotice('#domain-status', wpDomainMapping.messages.error, 'error');
            }
        });
    });

    // Tab switching functionality (for admin settings page)
    $('.domain-mapping-tab').on('click', function() {
        var tab = $(this).data('tab');

        // Update active tab
        $('.domain-mapping-tab').removeClass('active');
        $(this).addClass('active');

        // Show corresponding content
        $('.domain-mapping-section').hide();
        $('.domain-mapping-section[data-section="' + tab + '"]').show();

        // Update URL without reloading
        if (history.pushState) {
            var url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
        }
    });

    // Auto-fill server IP if detected
    if ($('#ipaddress').length && !$('#ipaddress').val()) {
        // This would be populated server-side, but we can enhance it client-side if needed
    }

    // Domain input formatting - remove protocols and trailing slashes
    $('#domain').on('blur', function() {
        var domain = $(this).val().trim();
        // Remove http:// or https://
        domain = domain.replace(/^https?:\/\//, '');
        // Remove trailing slash
        domain = domain.replace(/\/$/, '');
        // Convert to lowercase
        domain = domain.toLowerCase();
        $(this).val(domain);
    });

    // Copy to clipboard functionality for DNS instructions
    $('.copy-to-clipboard').on('click', function(e) {
        e.preventDefault();

        var text = $(this).data('text') || $(this).prev('code').text();

        // Create temporary input
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(text).select();

        try {
            document.execCommand('copy');
            var $btn = $(this);
            var originalText = $btn.text();
            $btn.text('Copied!').addClass('copied');

            setTimeout(function() {
                $btn.text(originalText).removeClass('copied');
            }, 2000);
        } catch (err) {
            console.log('Copy failed');
        }

        tempInput.remove();
    });

    // Form validation enhancement
    $('form').on('submit', function() {
        var $submitBtn = $(this).find('input[type="submit"], button[type="submit"]');
        var originalText = $submitBtn.val() || $submitBtn.text();

        // Disable submit button to prevent double submission
        $submitBtn.prop('disabled', true);

        // Re-enable after a delay
        setTimeout(function() {
            $submitBtn.prop('disabled', false);
        }, 3000);
    });

    // Enhanced table row highlighting
    $('.wp-list-table tbody tr').hover(
        function() {
            $(this).addClass('hover');
        },
        function() {
            $(this).removeClass('hover');
        }
    );

    // Auto-hide notices after delay
    $('.notice.is-dismissible').delay(8000).fadeOut();

    // Confirmation for destructive actions
    $('a[href*="action=delete"], .delete a').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });

    // Enhanced search functionality
    $('#domain-filter-form input[type="text"]').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            $(this).closest('form').submit();
        }
    });

    // Tooltips for status indicators
    if ($.fn.tooltip) {
        $('.dashicons[title]').tooltip();
    }

    // Progress indicator for long-running operations
    function showProgress(message) {
        var $progress = $('<div class="progress-indicator">' +
            '<p>' + message + '</p>' +
            '<div class="progress-bar">' +
                '<div class="progress-fill"></div>' +
            '</div>' +
        '</div>');

        $('body').append($progress);
        return $progress;
    }

    function hideProgress($progress) {
        if ($progress) {
            $progress.fadeOut(function() {
                $(this).remove();
            });
        }
    }

    // Enhanced error handling
    $(document).ajaxError(function(event, xhr, settings, thrownError) {
        if (xhr.status === 403) {
            showNotice('.wrap', 'Permission denied. Please refresh the page and try again.', 'error');
        } else if (xhr.status === 500) {
            showNotice('.wrap', 'Server error occurred. Please try again later.', 'error');
        }
    });

    // Initialize any existing tab from URL
    if (typeof URLSearchParams !== 'undefined') {
        var urlParams = new URLSearchParams(window.location.search);
        var currentTab = urlParams.get('tab');
        if (currentTab && $('.domain-mapping-tab[data-tab="' + currentTab + '"]').length) {
            $('.domain-mapping-tab[data-tab="' + currentTab + '"]').click();
        }
    }

    // Smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(e) {
        var target = $(this.getAttribute('href'));
        if (target.length) {
            e.preventDefault();
            $('html, body').stop().animate({
                scrollTop: target.offset().top - 100
            }, 500);
        }
    });

    // Auto-refresh functionality for health checks
    var autoRefreshInterval;

    function startAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }

        // Only auto-refresh on health tab
        if ($('.domain-mapping-section[data-section="health"]').is(':visible')) {
            autoRefreshInterval = setInterval(function() {
                // Check if any health checks are running
                if ($('.check-domain-health:disabled').length === 0) {
                    // Optionally auto-refresh health status every 5 minutes
                    // location.reload();
                }
            }, 300000); // 5 minutes
        }
    }

    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    }

    // Start auto-refresh when health tab is active
    $('.domain-mapping-tab[data-tab="health"]').on('click', startAutoRefresh);

    // Stop auto-refresh when leaving health tab
    $('.domain-mapping-tab:not([data-tab="health"])').on('click', stopAutoRefresh);

    // Initialize auto-refresh if health tab is already active
    if ($('.domain-mapping-tab[data-tab="health"]').hasClass('active')) {
        startAutoRefresh();
    }

    // Clean up on page unload
    $(window).on('beforeunload', function() {
        stopAutoRefresh();
    });

    // Domain tabs switching
    $('.domain-mapping-tab').on('click', function() {
        $('.domain-mapping-tab').removeClass('active');
        $(this).addClass('active');

        var tab = $(this).data('tab');
        $('.domain-mapping-section').hide();
        $('.domain-mapping-section[data-section="' + tab + '"]').show();
    });

    // Health check single domain
    $(document).on('click', '.check-domain-health', function() {
        var $button = $(this);
        var domain = $button.data('domain');
        var originalText = $button.text();

        $button.prop('disabled', true).text('Checking...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dm_check_domain_health',
                domain: domain,
                nonce: $('input[name="dm_manual_health_check_nonce"]').val() || wpDomainMapping.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Refresh page to show updated results
                    location.reload();
                } else {
                    alert(response.data || 'An error occurred during the health check.');
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert('An error occurred during the health check.');
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Import form handling
    $('#domain-mapping-import-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'dm_import_csv');

        // Show progress bar
        $('#import-progress').show();
        $('#import-results').hide();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            contentType: false,
            processData: false,
            success: function(response) {
                $('#import-progress').hide();
                $('#import-results').show();

                if (response.success) {
                    $('.import-summary').html(
                        '<div class="notice notice-success"><p>' +
                        response.data.message +
                        '</p></div>'
                    );

                    if (response.data.details && response.data.details.length > 0) {
                        var details = '<table class="widefat striped">' +
                                      '<thead><tr>' +
                                      '<th>Status</th>' +
                                      '<th>Details</th>' +
                                      '</tr></thead><tbody>';

                        $.each(response.data.details, function(i, item) {
                            var statusClass = '';
                            if (item.status === 'error') {
                                statusClass = 'style="background-color: #fef0f0;"';
                            } else if (item.status === 'warning') {
                                statusClass = 'style="background-color: #fff8e5;"';
                            } else if (item.status === 'success') {
                                statusClass = 'style="background-color: #f0f9eb;"';
                            }

                            details += '<tr ' + statusClass + '>' +
                                       '<td><strong>' + item.status.toUpperCase() + '</strong></td>' +
                                       '<td>' + item.message + '</td>' +
                                       '</tr>';
                        });

                        details += '</tbody></table>';
                        $('.import-details').html(details);
                    }
                } else {
                    $('.import-summary').html(
                        '<div class="notice notice-error"><p>' +
                        (response.data || 'Import failed.') +
                        '</p></div>'
                    );
                }
            },
            error: function() {
                $('#import-progress').hide();
                $('#import-results').show();
                $('.import-summary').html(
                    '<div class="notice notice-error"><p>' +
                    'An error occurred during import.' +
                    '</p></div>'
                );
            },
            xhr: function() {
                var xhr = new window.XMLHttpRequest();

                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = (evt.loaded / evt.total) * 100;
                        $('.progress-bar-inner').css('width', percentComplete + '%');
                        $('.progress-text').text(Math.round(percentComplete) + '%');
                    }
                }, false);

                return xhr;
            }
        });
    });

    // Enhanced domain validation
    $('#domain').on('input', function() {
        var domain = $(this).val();
        var $feedback = $('#domain-feedback');

        if (!$feedback.length) {
            $(this).after('<div id="domain-feedback" class="description"></div>');
            $feedback = $('#domain-feedback');
        }

        if (domain.length > 0) {
            // Check for common issues
            if (domain.indexOf('http') === 0) {
                $feedback.html('<span style="color: #dc3232;">Please remove http:// or https://</span>').show();
            } else if (domain.indexOf('/') !== -1) {
                $feedback.html('<span style="color: #dc3232;">Please remove any paths or slashes</span>').show();
            } else if (!/^[a-zA-Z0-9][a-zA-Z0-9.-]*[a-zA-Z0-9]\.[a-zA-Z]{2,}$/.test(domain)) {
                $feedback.html('<span style="color: #dc3232;">Please enter a valid domain format</span>').show();
            } else {
                $feedback.html('<span style="color: #46b450;">Domain format looks good</span>').show();
            }
        } else {
            $feedback.hide();
        }
    });

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + S to save forms
        if ((e.ctrlKey || e.metaKey) && e.which === 83) {
            var $activeForm = $('form:visible').first();
            if ($activeForm.length) {
                e.preventDefault();
                $activeForm.submit();
            }
        }

        // Escape key to close modals or cancel actions
        if (e.which === 27) {
            $('.notice.is-dismissible').fadeOut();
        }
    });

    // Real-time search for domain filter
    var searchTimeout;
    $('#domain-filter-form input[name="s"]').on('input', function() {
        clearTimeout(searchTimeout);
        var searchTerm = $(this).val();

        searchTimeout = setTimeout(function() {
            if (searchTerm.length > 2 || searchTerm.length === 0) {
                // Auto-submit form after delay
                $('#domain-filter-form').submit();
            }
        }, 1000);
    });

    // Initialize page based on current state
    function initializePage() {
        // Check if we're on the domains page and have messages to show
        if (window.location.href.indexOf('page=domains') > -1) {
            if (window.location.href.indexOf('updated=add') > -1) {
                showNotice('#domain-status', 'Domain added successfully.', 'success');
            } else if (window.location.href.indexOf('updated=del') > -1) {
                showNotice('#domain-status', 'Domain deleted successfully.', 'success');
            }
        }

        // Auto-focus first input field
        $('input[type="text"]:visible:first').focus();
    }

    // Initialize when document is ready
    initializePage();
});

// Domain Mapping Dashboard Widget JavaScript
jQuery(document).ready(function($) {

    // Health status refresh button
    $(document).on('click', '.dm-refresh-health', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $healthContent = $button.closest('.dm-health-status').find('.dm-health-content');
        var blogId = $button.data('blog-id');

        $button.prop('disabled', true);
        $healthContent.addClass('dm-loading');

        // Rotate icon
        $button.find('.dashicons').addClass('dashicons-update-spin');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dm_quick_health_check',
                blog_id: blogId,
                nonce: wpDomainMapping.nonce
            },
            success: function(response) {
                if (response.success) {
                    $healthContent.html(response.data.html);

                    // Show success message
                    var $notice = $('<div class="notice notice-success is-dismissible"><p>' +
                                  response.data.message + '</p></div>');
                    $('#dm_domain_status_widget .inside').prepend($notice);

                    setTimeout(function() {
                        $notice.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                }
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.find('.dashicons').removeClass('dashicons-update-spin');
                $healthContent.removeClass('dm-loading');
            }
        });
    });

    // Check all domain health status
    $(document).on('click', '.dm-check-all-health', function(e) {
        e.preventDefault();

        var $button = $(this);
        var blogId = $button.data('blog-id');
        var originalText = $button.html();

        $button.prop('disabled', true).html(
            '<span class="dashicons dashicons-update dashicons-update-spin"></span> ' +
            wpDomainMapping.messages.processing
        );

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dm_quick_health_check',
                blog_id: blogId,
                nonce: wpDomainMapping.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Refresh health status area
                    if ($('#dm-widget-health-status').length) {
                        $('#dm-widget-health-status .dm-health-content').html(response.data.html);
                    }

                    // Show success message
                    var $notice = $('<div class="notice notice-success is-dismissible"><p>' +
                                  response.data.message + '</p></div>');
                    $('#dm_domain_status_widget .inside').prepend($notice);

                    setTimeout(function() {
                        $notice.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                } else {
                    alert(response.data || wpDomainMapping.messages.error);
                }
            },
            error: function() {
                alert(wpDomainMapping.messages.error);
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });

    // Auto-refresh (optional)
    if ($('#dm_domain_status_widget').length && $('#dm_domain_status_widget').is(':visible')) {
        // Auto-refresh every 10 minutes
        var autoRefreshInterval = setInterval(function() {
            if ($('#dm_domain_status_widget').is(':visible')) {
                $('.dm-refresh-health').first().trigger('click');
            }
        }, 600000); // 10 minutes

        // Clear timer on page unload
        $(window).on('beforeunload', function() {
            clearInterval(autoRefreshInterval);
        });
    }

    // Widget configuration save
    $(document).on('submit', '#dm_domain_status_widget form', function(e) {
        // Configuration will auto-save, here we can add additional processing
        var showHealth = $('#dm_widget_show_health').is(':checked');

        // If unchecked health status, hide related areas
        if (!showHealth) {
            $('#dm-widget-health-status').fadeOut();
        } else {
            $('#dm-widget-health-status').fadeIn();
        }
    });

    // Add external icons for domain links
    $('.dm-domains-list a, .dm-domain-primary a').each(function() {
        if (!$(this).find('.dashicons-external').length) {
            $(this).append(' <span class="dashicons dashicons-external" style="font-size: 14px; vertical-align: middle;"></span>');
        }
    });

    // Tooltips
    if ($.fn.tooltip) {
        $('#dm_domain_status_widget [title]').tooltip({
            position: {
                my: 'center bottom-5',
                at: 'center top',
                using: function(position, feedback) {
                    $(this).css(position);
                    $('<div>')
                        .addClass('dm-tooltip-arrow')
                        .addClass(feedback.vertical)
                        .addClass(feedback.horizontal)
                        .appendTo(this);
                }
            }
        });
    }
});
