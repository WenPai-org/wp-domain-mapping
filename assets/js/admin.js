/**
 * WP Domain Mapping admin JavaScript
 */
jQuery(document).ready(function($) {
    // Tab switching
    $('.styles-tab').on('click', function() {
        $('.styles-tab').removeClass('active');
        $(this).addClass('active');
        var tab = $(this).data('tab');
        $('.styles-section').hide();
        $('.styles-section[data-section="' + tab + '"]').show();
    });

    // Domain form validation
    $('#edit-domain-form').on('submit', function(e) {
        var domain = $('#domain').val();
        var blogId = $('#blog_id').val();
        
        if (!domain) {
            e.preventDefault();
            alert(wpDomainMapping.messages.domainRequired);
            $('#domain').focus();
            return false;
        }
        
        if (!blogId) {
            e.preventDefault();
            alert(wpDomainMapping.messages.siteRequired);
            $('#blog_id').focus();
            return false;
        }
    });

    // Check all domains
    $('#select-all').on('change', function() {
        $('.domain-checkbox').prop('checked', this.checked);
    });

    // AJAX domain operations
    function showNotice(selector, message, type) {
        $(selector).removeClass('notice-success notice-error')
            .addClass('notice-' + type)
            .html('<p>' + message + '</p>')
            .show()
            .delay(3000)
            .fadeOut();
    }

    // Save domain
    $('#edit-domain-form').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serializeArray();
        formData.push({name: 'action', value: 'dm_handle_actions'});
        formData.push({name: 'action_type', value: 'save'});
        formData.push({name: 'nonce', value: wpDomainMapping.nonce});

        $('#edit-domain-status').text(wpDomainMapping.messages.saving).show();
        
        $.ajax({
            url: wpDomainMapping.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotice('#edit-domain-status', response.data, 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showNotice('#edit-domain-status', response.data || wpDomainMapping.messages.error, 'error');
                }
            },
            error: function() {
                showNotice('#edit-domain-status', wpDomainMapping.messages.error, 'error');
            }
        });
    });

    // Bulk actions
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
        if (action === '-1') return;

        if (confirm('Are you sure you want to delete the selected domains?')) {
            $('#domain-status').text(wpDomainMapping.messages.processing).show();
            
            $.ajax({
                url: wpDomainMapping.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dm_handle_actions',
                    action_type: 'delete',
                    domains: selectedDomains,
                    nonce: wpDomainMapping.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('#domain-status', response.data, 'success');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        showNotice('#domain-status', response.data || wpDomainMapping.messages.error, 'error');
                    }
                },
                error: function() {
                    showNotice('#domain-status', wpDomainMapping.messages.error, 'error');
                }
            });
        }
    });

    // Copy to clipboard functionality
    $('.copy-to-clipboard').on('click', function() {
        var text = $(this).data('text');
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(text).select();
        document.execCommand('copy');
        tempInput.remove();
        
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.text('Copied!');
        setTimeout(function() { $btn.text(originalText); }, 2000);
    });
});