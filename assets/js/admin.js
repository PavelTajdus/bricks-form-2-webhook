jQuery(document).ready(function($) {
    // Confirmation for delete actions
    $('.button-link-delete').on('click', function(e) {
        if (!confirm($(this).data('confirm') || 'Are you sure you want to delete this webhook?')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Form validation
    $('form').on('submit', function(e) {
        var isValid = true;
        var requiredFields = $(this).find('input[required]');
        
        requiredFields.each(function() {
            var $field = $(this);
            var value = $field.val().trim();
            
            if (!value) {
                isValid = false;
                $field.addClass('form-invalid').focus();
                
                // Remove invalid class when user starts typing
                $field.one('input', function() {
                    $(this).removeClass('form-invalid');
                });
            } else {
                $field.removeClass('form-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
            return false;
        }
        
        return true;
    });
    
    // Auto-focus first input in add/edit forms
    var $firstInput = $('.bf2w-add-webhook input[type="text"]:first');
    if ($firstInput.length && !$firstInput.val()) {
        $firstInput.focus();
    }
    
    // Enhanced URL validation
    $('input[type="url"]').on('blur', function() {
        var $input = $(this);
        var url = $input.val().trim();
        
        if (url && !isValidUrl(url)) {
            $input.addClass('form-invalid');
            showTooltip($input, 'Please enter a valid URL (e.g., https://webhook.site/...)');
        } else {
            $input.removeClass('form-invalid');
            hideTooltip($input);
        }
    });
    
    // Enhanced Form ID validation
    $('input[name="form_id"]').on('blur', function() {
        var $input = $(this);
        var formId = $input.val().trim();
        
        if (formId) {
            // Check for common mistakes
            if (formId.includes('bricks-element-')) {
                $input.addClass('form-invalid');
                showTooltip($input, 'Remove "bricks-element-" prefix. Use only the ID part.');
            } else if (formId.includes(' ') || formId.includes('#') || formId.includes('.')) {
                $input.addClass('form-invalid');
                showTooltip($input, 'Form ID should not contain spaces, # or . characters.');
            } else {
                $input.removeClass('form-invalid');
                hideTooltip($input);
            }
        }
    });
    
    // Utility functions
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
    
    function showTooltip($element, message) {
        hideTooltip($element); // Remove existing tooltip
        
        var $tooltip = $('<div class="bf2w-tooltip">' + message + '</div>');
        $tooltip.css({
            position: 'absolute',
            background: '#dc3232',
            color: '#fff',
            padding: '5px 10px',
            borderRadius: '3px',
            fontSize: '12px',
            zIndex: 9999,
            whiteSpace: 'nowrap',
            top: $element.offset().top + $element.outerHeight() + 5,
            left: $element.offset().left
        });
        
        $('body').append($tooltip);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $tooltip.fadeOut(function() {
                $tooltip.remove();
            });
        }, 5000);
    }
    
    function hideTooltip($element) {
        $('.bf2w-tooltip').remove();
    }
    
    // Clean up tooltips when clicking elsewhere
    $(document).on('click', function() {
        hideTooltip();
    });
    
    // Highlight newly added/edited rows
    if (window.location.search.includes('updated=1') || window.location.search.includes('added=1')) {
        $('.bf2w-existing-webhooks .wp-list-table tbody tr:first-child').addClass('bf2w-highlight');
    }
    
    // Copy to clipboard functionality for webhook URLs
    $('.bf2w-existing-webhooks').on('click', '.copy-webhook-url', function(e) {
        e.preventDefault();
        
        var url = $(this).data('url');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
                showNotice('Webhook URL copied to clipboard!', 'success');
            });
        } else {
            // Fallback for older browsers
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(url).select();
            document.execCommand('copy');
            $temp.remove();
            showNotice('Webhook URL copied to clipboard!', 'success');
        }
    });
    
    // Show notice messages
    function showNotice(message, type) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);
        
        setTimeout(function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        }, 3000);
    }
    
    // Debug info toggle
    $('.bf2w-debug-info h2').on('click', function() {
        $(this).next('table').slideToggle();
        $(this).toggleClass('collapsed');
    });
    
    // Add copy button for debug data
    if ($('.bf2w-debug-info pre').length) {
        $('.bf2w-debug-info pre').each(function() {
            var $pre = $(this);
            var $copyBtn = $('<button type="button" class="button button-small copy-debug-data" style="margin-top: 5px;">Copy Debug Data</button>');
            
            $copyBtn.on('click', function() {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText($pre.text()).then(function() {
                        showNotice('Debug data copied to clipboard!', 'success');
                    });
                }
            });
            
            $pre.after($copyBtn);
        });
    }
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + S to save form
        if ((e.ctrlKey || e.metaKey) && e.which === 83) {
            e.preventDefault();
            $('.bf2w-add-webhook form').submit();
        }
        
        // Escape key to cancel edit
        if (e.which === 27 && window.location.search.includes('edit=')) {
            window.location.href = window.location.pathname + '?page=bricks-form-2-webhook';
        }
    });
}); 