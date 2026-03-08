/**
 * AI SEO Assistant - License Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // License activation form handling
        $('#aiseo-license-form').on('submit', function(e) {
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
                url: aiseoLicense.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_activate_license',
                    license_key: licenseKey,
                    nonce: aiseoLicense.nonce
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
        $('#aiseo-deactivate-license').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to deactivate this license?')) {
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Deactivating...');
            
            $.ajax({
                url: aiseoLicense.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_deactivate_license',
                    nonce: aiseoLicense.nonce
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
        $('#aiseo-start-trial').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Starting trial...');
            
            $.ajax({
                url: aiseoLicense.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_start_trial',
                    nonce: aiseoLicense.nonce
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
        if ($('#aiseo-license-status').length) {
            setInterval(function() {
                checkLicenseStatus();
            }, 300000); // Every 5 minutes
        }

        function checkLicenseStatus() {
            $.ajax({
                url: aiseoLicense.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiseo_check_license_status',
                    nonce: aiseoLicense.nonce
                },
                success: function(response) {
                    if (response.success && response.data.status !== $('#aiseo-license-status').data('status')) {
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
        $('.aiseo-copy-key').on('click', function(e) {
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
    window.aiseoCopyLicense = function(key) {
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
    window.aiseoCopyAllLicenses = function() {
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
