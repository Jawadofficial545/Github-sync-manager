jQuery(document).ready(function($) {
    
    // Auto-check updates on page load
    $('.check-update').each(function() {
        var slug = $(this).data('slug');
        checkUpdate(slug);
    });
    
    // Check update button click
    $(document).on('click', '.check-update', function(e) {
        e.preventDefault();
        var slug = $(this).data('slug');
        checkUpdate(slug);
    });
    
    // Install update button click
    $(document).on('click', '.install-update', function(e) {
        e.preventDefault();
        var slug = $(this).data('slug');
        
        if (!confirm('Are you sure you want to update this plugin? Make sure to backup first!')) {
            return;
        }
        
        installUpdate(slug);
    });
    
    // Remove repository
    $(document).on('click', '.remove-repo', function(e) {
        e.preventDefault();
        var slug = $(this).data('slug');
        
        if (!confirm('Are you sure you want to remove this repository?')) {
            return;
        }
        
        var url = window.location.href;
        url += (url.indexOf('?') > -1 ? '&' : '?') + 'action=remove&slug=' + slug;
        url += '&_wpnonce=' + '<?php echo wp_create_nonce("github_sync_remove_' + slug + '"); ?>';
        
        window.location.href = url;
    });
    
    function checkUpdate(slug) {
        var $row = $('tr[data-slug="' + slug + '"]');
        var $statusCell = $row.find('.sync-status');
        var $checkBtn = $row.find('.check-update');
        var $installBtn = $row.find('.install-update');
        
        // Show checking status
        $statusCell.html('<span class="status-badge status-checking"><span class="loading-spinner"></span> Checking...</span>');
        $checkBtn.prop('disabled', true);
        $installBtn.hide();
        
        $.ajax({
            url: githubSync.ajax_url,
            type: 'POST',
            data: {
                action: 'github_sync_check_update',
                slug: slug,
                nonce: githubSync.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    if (data.needs_update) {
                        $statusCell.html(
                            '<span class="status-badge status-update-available">' +
                            'Update Available: v' + data.latest_version +
                            '</span>'
                        );
                        $installBtn.show();
                    } else {
                        $statusCell.html(
                            '<span class="status-badge status-uptodate">' +
                            '✓ Up to date (v' + data.current_version + ')' +
                            '</span>'
                        );
                    }
                } else {
                    $statusCell.html(
                        '<span class="status-badge status-error">' +
                        '✗ Error: ' + (response.data || 'Unknown error') +
                        '</span>'
                    );
                }
            },
            error: function() {
                $statusCell.html(
                    '<span class="status-badge status-error">' +
                    '✗ Connection error' +
                    '</span>'
                );
            },
            complete: function() {
                $checkBtn.prop('disabled', false);
            }
        });
    }
    
    function installUpdate(slug) {
        var $row = $('tr[data-slug="' + slug + '"]');
        var $statusCell = $row.find('.sync-status');
        var $checkBtn = $row.find('.check-update');
        var $installBtn = $row.find('.install-update');
        
        // Show updating status
        $statusCell.html('<span class="status-badge status-updating"><span class="loading-spinner"></span> Updating...</span>');
        $checkBtn.prop('disabled', true);
        $installBtn.prop('disabled', true);
        
        $.ajax({
            url: githubSync.ajax_url,
            type: 'POST',
            data: {
                action: 'github_sync_install',
                slug: slug,
                nonce: githubSync.nonce
            },
            success: function(response) {
                if (response.success) {
                    $statusCell.html(
                        '<span class="status-badge status-uptodate">' +
                        '✓ Updated successfully!' +
                        '</span>'
                    );
                    $installBtn.hide();
                    
                    // Show success message
                    showNotice('Plugin updated successfully! Reloading page...', 'success');
                    
                    // Reload page after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $statusCell.html(
                        '<span class="status-badge status-error">' +
                        '✗ Update failed: ' + (response.data || 'Unknown error') +
                        '</span>'
                    );
                    showNotice('Update failed: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function() {
                $statusCell.html(
                    '<span class="status-badge status-error">' +
                    '✗ Update failed' +
                    '</span>'
                );
                showNotice('Update failed: Connection error', 'error');
            },
            complete: function() {
                $checkBtn.prop('disabled', false);
                $installBtn.prop('disabled', false);
            }
        });
    }
    
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Form validation
    $('#github-sync-form').on('submit', function(e) {
        var slug = $('#plugin_slug').val().trim();
        var repo = $('#github_repo').val().trim();
        
        if (slug === '' || repo === '') {
            alert('Please fill in all required fields');
            e.preventDefault();
            return false;
        }
        
        // Validate repo format
        if (!repo.match(/^[\w-]+\/[\w-]+$/)) {
            alert('Invalid repository format. Use: username/repository');
            e.preventDefault();
            return false;
        }
    });
});
