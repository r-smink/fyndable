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

        // Validate license key format (non-blocking)
        $('#license_key').on('input', function() {
            const key = $(this).val().trim();
            const pattern = /^(FYN-SSAI|[A-Z0-9]{1,6}-AI)-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}$/i;
            
            // Remove previous error messages
            $(this).next('.error-message').remove();
            
            if (key && key.length > 5 && !pattern.test(key)) {
                $(this).addClass('error');
                if (!$(this).next('.error-message').length) {
                    $(this).after('<p class="description error-message" style="color: #d63638;">⚠️ Expected format: XXXX-XXXX-XXXX-XXXX-XXXX (case-insensitive)</p>');
                }
            } else {
                $(this).removeClass('error');
            }
        });
        
        // Clear error on focus
        $('#license_key').on('focus', function() {
            $(this).removeClass('error');
            $(this).next('.error-message').remove();
        });

        // Enhance plain checkboxes to checkout-style toggles
        enhanceCheckboxes();
    });

    /**
     * Turn checkboxes inside .form-field and cards into toggle switches.
     */
    function enhanceCheckboxes() {
        if (!document.querySelector) {
            return;
        }

        var selectors = [
            '.fyndable-ui .form-field input[type="checkbox"]',
            '.fyndable-ui .sseo-ai-settings-card input[type="checkbox"]',
            '.fyndable-ui .sseo-ai-dashboard-card input[type="checkbox"]'
        ].join(', ');

        document.querySelectorAll(selectors).forEach(function(input) {
            if (input.closest('.sseo-ai-toggle') || input.closest('table')) {
                return;
            }

            var label = input.closest('label');
            if (!label) {
                return;
            }

            label.classList.add('sseo-ai-toggle');

            var track = document.createElement('span');
            track.className = 'sseo-ai-toggle-track';
            track.setAttribute('aria-hidden', 'true');
            input.after(track);

            var textSpan = document.createElement('span');
            textSpan.className = 'sseo-ai-toggle-text';
            var labelChildren = Array.from(label.childNodes).filter(function(node) {
                return node !== input && node !== track;
            });
            labelChildren.forEach(function(node) {
                textSpan.appendChild(node);
            });
            if (textSpan.childNodes.length) {
                label.appendChild(textSpan);
            }
        });
    }
})(jQuery);
