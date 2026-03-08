/* AI SEO Client Admin Scripts */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Copy license key to clipboard
        $('.ai-seo-client .copy-key').on('click', function(e) {
            e.preventDefault();
            const key = $(this).data('key');
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(key).then(function() {
                    showNotice('License key copied to clipboard!', 'success');
                });
            } else {
                // Fallback
                const textarea = $('<textarea>').val(key).appendTo('body').select();
                document.execCommand('copy');
                textarea.remove();
                showNotice('License key copied to clipboard!', 'success');
            }
        });

        // Show notice helper
        function showNotice(message, type) {
            const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.ai-seo-client h1').after(notice);
            
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }

        // Validate license key format
        $('#license_key').on('blur', function() {
            const key = $(this).val().trim();
            const pattern = /^AISEO-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}$/;
            
            if (key && !pattern.test(key)) {
                $(this).addClass('error');
                if (!$(this).next('.description').length) {
                    $(this).after('<p class="description error-message" style="color: #d63638;">Invalid license key format. Expected format: AISEO-XXXX-XXXX-XXXX-XXXX</p>');
                }
            } else {
                $(this).removeClass('error');
                $(this).next('.error-message').remove();
            }
        });
    });
})(jQuery);
