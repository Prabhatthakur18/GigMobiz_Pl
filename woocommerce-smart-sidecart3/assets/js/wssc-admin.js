jQuery(document).ready(function($) {
    // Auto-hide toast notifications
    $('.wssc-admin-toast').each(function() {
        const toast = $(this);
        
        // Auto-hide after 4 seconds
        setTimeout(function() {
            toast.fadeOut(400, function() {
                toast.remove();
            });
        }, 4000);
        
        // Allow manual close on click
        toast.on('click', function() {
            toast.fadeOut(300, function() {
                toast.remove();
            });
        });
    });

    // File input enhancement
    $('.file-input').on('change', function() {
        const fileName = $(this)[0].files[0]?.name;
        const fileText = $(this).siblings('.file-label').find('.file-text');
        
        if (fileName) {
            fileText.text(fileName);
            $(this).siblings('.file-label').css({
                'background': '#e7f3ff',
                'border-color': '#0073aa',
                'color': '#0073aa'
            });
        } else {
            fileText.text('Choose CSV File');
            $(this).siblings('.file-label').css({
                'background': '#f6f7f7',
                'border-color': '#c3c4c7',
                'color': 'inherit'
            });
        }
    });

    // Form submission loading state
    $('.wssc-upload-form').on('submit', function() {
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        submitBtn.prop('disabled', true)
               .html('<span class="upload-icon">⏳</span> Uploading...');
        
        // Re-enable if form doesn't submit for some reason
        setTimeout(function() {
            submitBtn.prop('disabled', false).html(originalText);
        }, 10000);
    });

    // Edit Status Button
    $(document).on('click', '.edit-status-btn', function() {
        var requestId = $(this).data('id');
        var currentStatus = $(this).closest('tr').find('.status-badge').text().toLowerCase().trim();
        
        $('#edit-request-id').val(requestId);
        $('#status-select').val(currentStatus);
        $('#status-edit-modal').show();
    });

    // Cancel Edit
    $(document).on('click', '.cancel-edit', function() {
        $('#status-edit-modal').hide();
    });

    // Submit Status Update
    $(document).on('submit', '#status-edit-form', function(e) {
        e.preventDefault();
        
        var requestId = $('#edit-request-id').val();
        var newStatus = $('#status-select').val();
        
        $.ajax({
            url: wsscAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'wssc_update_request_status',
                id: requestId,
                status: newStatus,
                _ajax_nonce: wsscAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the status badge in the table
                    var row = $('tr[data-id="' + requestId + '"]');
                    var statusBadge = row.find('.status-badge');
                    statusBadge.removeClass('status-pending status-done')
                              .addClass('status-' + newStatus)
                              .text(newStatus.charAt(0).toUpperCase() + newStatus.slice(1));
                    
                    $('#status-edit-modal').hide();
                    showAdminToast('✅ Status updated successfully!', 'success');
                } else {
                    showAdminToast('❌ Failed to update status', 'error');
                }
            },
            error: function() {
                showAdminToast('❌ Error updating status', 'error');
            }
        });
    });

    // Delete Request
    $(document).on('click', '.delete-request-btn', function() {
        if (!confirm('Are you sure you want to delete this request?')) {
            return;
        }
        
        var requestId = $(this).data('id');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: wsscAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'wssc_delete_request',
                id: requestId,
                _ajax_nonce: wsscAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        row.remove();
                        
                        // Check if table is empty and show no requests message
                        if ($('.wssc-requests-table tbody tr').length === 0) {
                            $('.wssc-requests-table').after('<div class="no-requests"><p>No bulk requests found.</p></div>');
                            $('.wssc-requests-table').hide();
                        }
                    });
                    showAdminToast('✅ Request deleted successfully!', 'success');
                } else {
                   showAdminToast('❌ Failed to delete request', 'error');
                }
            },
            error: function() {
                showAdminToast('❌ Error deleting request', 'error');
            }
        });
    });

    // Admin Toast Function
    function showAdminToast(message, type) {
        var toast = $('<div class="wssc-admin-toast ' + type + '">' + message + '</div>');
        $('body').append(toast);
        
        setTimeout(function() {
            toast.fadeOut(400, function() {
                toast.remove();
            });
        }, 4000);
        
        toast.on('click', function() {
            toast.fadeOut(300, function() {
                toast.remove();
            });
        });
    }

    // Close modal when clicking outside
    $(document).on('click', '.wssc-modal', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Handle image deletion
    $(document).on('click', '.delete-image-btn', function() {
        if (!confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
            return;
        }
        
        var button = $(this);
        var imageId = button.data('id');
        var row = button.closest('tr');
        
        $.ajax({
            url: wsscAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'wssc_delete_image',
                id: imageId,
                _ajax_nonce: wsscAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        row.remove();
                        
                        // Check if table is empty and show no images message
                        if ($('.wssc-image-table tbody tr').length === 0) {
                            $('.wssc-image-table').after('<div class="no-requests"><p>No uploaded images found.</p></div>');
                            $('.wssc-image-table').hide();
                        }
                    });
                    showAdminToast('✅ Image deleted successfully!', 'success');
                } else {
                    showAdminToast('❌ ' + response.data, 'error');
                }
            },
            error: function() {
                showAdminToast('❌ Error deleting image', 'error');
            }
        });
    });
});