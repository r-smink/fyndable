/**
 * SSEO AI - License Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // License activation form handling
        $('#sseo-ai-license-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var licenseKey = $form.find('input[name="license_key"]').val().trim();
            
            if (!licenseKey) {
                showNotice('error', 'Please enter a license key');
                return;
            }
            
            $submitBtn.prop('disabled', true).text('Activating...');
            
            $.ajax({
                url: sseoAiLicense.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sseo_ai_activate_license',
                    license_key: licenseKey,
                    nonce: sseoAiLicense.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showNotice('error', response.data.message || 'Activation failed');
                        $submitBtn.prop('disabled', false).text('Activate License');
                    }
                },
                error: function() {
                    showNotice('error', 'Network error. Please try again.');
                    $submitBtn.prop('disabled', false).text('Activate License');
                }
            });
        });

        // License deactivation
        $('#sseo-ai-deactivate-license').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to deactivate this license?')) {
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Deactivating...');
            
            $.ajax({
                url: sseoAiLicense.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sseo_ai_deactivate_license',
                    nonce: sseoAiLicense.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showNotice('error', response.data.message || 'Deactivation failed');
                        $btn.prop('disabled', false).text('Deactivate License');
                    }
                },
                error: function() {
                    showNotice('error', 'Network error. Please try again.');
                    $btn.prop('disabled', false).text('Deactivate License');
                }
            });
        });

        // Start trial
        $('#sseo-ai-start-trial').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Starting trial...');
            
            $.ajax({
                url: sseoAiLicense.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sseo_ai_start_trial',
                    nonce: sseoAiLicense.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showNotice('error', response.data.message || 'Trial start failed');
                        $btn.prop('disabled', false).text('Start Free Trial');
                    }
                },
                error: function() {
                    showNotice('error', 'Network error. Please try again.');
                    $btn.prop('disabled', false).text('Start Free Trial');
                }
            });
        });

        // Check license status periodically
        if ($('#sseo-ai-license-status').length) {
            setInterval(function() {
                checkLicenseStatus();
            }, 300000); // Every 5 minutes
        }

        function checkLicenseStatus() {
            $.ajax({
                url: sseoAiLicense.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sseo_ai_check_license_status',
                    nonce: sseoAiLicense.nonce
                },
                success: function(response) {
                    if (response.success && response.data.status !== $('#sseo-ai-license-status').data('status')) {
                        window.location.reload();
                    }
                }
            });
        }

        function showNotice(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }

        // Copy license key to clipboard
        $('.sseo-ai-copy-key').on('click', function(e) {
            e.preventDefault();
            var key = $(this).data('key');
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(key).then(function() {
                    showNotice('success', 'License key copied to clipboard');
                });
            } else {
                // Fallback
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(key).select();
                document.execCommand('copy');
                $temp.remove();
                showNotice('success', 'License key copied to clipboard');
            }
        });
    });

    // SaaS License Admin Functions
    // Copy single license key
    window.sseoAiCopyLicense = function(key) {
        navigator.clipboard.writeText(key).then(function() {
            alert('License key copied to clipboard!');
        }).catch(function() {
            // Fallback
            var textarea = document.createElement('textarea');
            textarea.value = key;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert('License key copied to clipboard!');
        });
    };

    // Copy all generated licenses
    window.sseoAiCopyAllLicenses = function() {
        var keys = [];
        jQuery('.generated-licenses .license-key').each(function() {
            keys.push(jQuery(this).text().trim());
        });
        
        if (keys.length === 0) {
            alert('No license keys found to copy.');
            return;
        }
        
        var text = keys.join('\n');
        navigator.clipboard.writeText(text).then(function() {
            alert(keys.length + ' license key(s) copied to clipboard!');
        }).catch(function() {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert(keys.length + ' license key(s) copied to clipboard!');
        });
    };

})(jQuery);
