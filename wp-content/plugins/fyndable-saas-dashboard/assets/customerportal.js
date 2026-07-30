(function ($) {
    'use strict';

    var FyndablePortal = window.FyndablePortal || {};
    var loaded = {};

    function apiRequest(endpoint, method, data) {
        var opts = {
            url: FyndablePortal.restUrl + endpoint,
            method: method || 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', FyndablePortal.nonce);
            },
        };
        if (data) {
            opts.data = data;
            opts.contentType = 'application/json';
            opts.dataType = 'json';
            opts.data = JSON.stringify(data);
        }
        return $.ajax(opts);
    }

    function showLoading(panelId) {
        $('#' + panelId).html('<div class="fyndable-portal-loading">' + FyndablePortal.i18n.loading + '</div>');
    }

    function showAlert(panelId, type, message) {
        $('#' + panelId).prepend('<div class="fyndable-portal-alert fyndable-portal-alert-' + type + '">' + message + '</div>');
        setTimeout(function () {
            $('#' + panelId + ' .fyndable-portal-alert').fadeOut(400, function () { $(this).remove(); });
        }, 5000);
    }

    function formatCurrency(amount, currency) {
        var symbols = { EUR: '€', USD: '$', GBP: '£' };
        var symbol = symbols[(currency || 'EUR').toUpperCase()] || (currency + ' ');
        return symbol + parseFloat(amount).toFixed(2);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        var d = new Date(dateStr);
        return d.toLocaleDateString();
    }

    function statusBadge(status) {
        var cls = 'fyndable-portal-badge-' + (status || 'pending');
        return '<span class="fyndable-portal-badge ' + cls + '">' + (status || 'unknown') + '</span>';
    }

    // --- Tab switching ---
    $(document).on('click', '.fyndable-portal-tab', function () {
        var tab = $(this).data('tab');
        $('.fyndable-portal-tab').removeClass('active');
        $(this).addClass('active');
        $('.fyndable-portal-panel').removeClass('active');
        $('#panel-' + tab).addClass('active');

        if (!loaded[tab]) {
            loadTab(tab);
        }
    });

    function loadTab(tab) {
        switch (tab) {
            case 'subscription': loadSubscription(); break;
            case 'license': loadLicense(); break;
            case 'usage': loadUsage(); break;
            case 'download': loadDownload(); break;
            case 'invoices': loadInvoices(); break;
            case 'account': loadAccount(); break;
        }
    }

    // --- License ---
    function loadLicense() {
        var panel = '#panel-license';
        showLoading('panel-license');

        apiRequest('/portal/license').done(function (res) {
            if (!res.success) {
                $(panel).html('<div class="fyndable-portal-error">' + (res.message || FyndablePortal.i18n.error) + '</div>');
                return;
            }

            var html = '<div class="fyndable-portal-card">';
            html += '<h3>' + (res.tier ? res.tier.charAt(0).toUpperCase() + res.tier.slice(1) : '') + ' License</h3>';
            html += '<div class="fyndable-portal-license-key-row">';
            html += '<label>License Key</label>';
            html += '<div class="fyndable-portal-license-key-box">';
            html += '<code class="fyndable-portal-license-key">' + res.license_key + '</code>';
            html += '<button class="button fyndable-portal-copy-btn" data-key="' + res.license_key + '">' + (FyndablePortal.i18n.copied || 'Copy') + '</button>';
            html += '</div></div>';
            html += '<table class="fyndable-portal-info-table">';
            html += '<tr><td>Status</td><td>' + statusBadge(res.status) + '</td></tr>';
            html += '<tr><td>Tier</td><td>' + (res.tier || '—') + '</td></tr>';
            html += '<tr><td>Max Sites</td><td>' + (res.max_sites || 1) + '</td></tr>';
            html += '<tr><td>Rate Limit</td><td>' + (res.rate_limit || 60) + ' /hour</td></tr>';
            html += '<tr><td>API Calls Limit</td><td>' + (res.api_calls_limit || 1000) + ' /month</td></tr>';
            html += '<tr><td>Expires</td><td>' + formatDate(res.expires_at) + '</td></tr>';
            html += '</table>';
            html += '<p class="fyndable-portal-help">Use this license key to activate the Fyndable plugin on your WordPress site. Go to Settings → Fyndable, paste the key, and click Activate.</p>';
            html += '</div>';

            $(panel).html(html);
            loaded.license = true;
        }).fail(function () {
            $(panel).html('<div class="fyndable-portal-error">' + FyndablePortal.i18n.error + '</div>');
        });
    }

    // --- Subscription ---
    function loadSubscription() {
        var panel = '#panel-subscription';
        showLoading('panel-subscription');

        apiRequest('/portal/subscription').done(function (res) {
            loaded.subscription = true;
            if (!res.success) {
                $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + res.message + '</div>');
                return;
            }

            var s = res.subscription;
            var pd = res.provider_details || {};
            var html = '';

            html += '<div class="fyndable-portal-card">';
            html += '<h3>Subscription Details</h3>';
            html += '<div class="fyndable-portal-detail-grid">';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">Plan</span><span class="fyndable-portal-detail-value">' + (s.tier || '—') + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">Billing Period</span><span class="fyndable-portal-detail-value">' + (s.interval === 'year' ? 'Yearly' : 'Monthly') + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">Amount</span><span class="fyndable-portal-detail-value">' + formatCurrency(s.monthly_amount, s.currency) + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">Status</span>' + statusBadge(s.status) + '</div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">Payment Status</span><span class="fyndable-portal-detail-value">' + (s.payment_status || '—') + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">Started</span><span class="fyndable-portal-detail-value">' + formatDate(s.created_at) + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">Renews / Expires</span><span class="fyndable-portal-detail-value">' + formatDate(s.expires_at) + '</span></div>';
            if (pd.next_payment_date) {
                html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">Next Payment</span><span class="fyndable-portal-detail-value">' + formatDate(pd.next_payment_date) + '</span></div>';
            }
            html += '</div>';
            html += '</div>';

            // Cancel button
            if (s.status === 'active' || s.payment_status === 'active') {
                html += '<div class="fyndable-portal-card">';
                html += '<h3>Cancel Subscription</h3>';
                html += '<p style="color:#6b7280;font-size:14px;margin-bottom:16px;">Cancelling will stop future payments. You retain access until the end of your current billing period.</p>';
                html += '<button class="fyndable-portal-btn fyndable-portal-btn-danger" id="cancel-subscription-btn">Cancel Subscription</button>';
                html += '</div>';
            }

            $(panel).html(html);
        }).fail(function () {
            $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + FyndablePortal.i18n.error + '</div>');
        });
    }

    // Cancel subscription
    $(document).on('click', '#cancel-subscription-btn', function () {
        if (!confirm(FyndablePortal.i18n.confirmCancel)) {
            return;
        }
        var btn = $(this);
        btn.prop('disabled', true).text(FyndablePortal.i18n.loading);

        apiRequest('/portal/cancel', 'POST').done(function (res) {
            if (res.success) {
                showAlert('panel-subscription', 'success', res.message);
                loaded.subscription = false;
                setTimeout(loadSubscription, 1500);
            } else {
                showAlert('panel-subscription', 'error', res.message || FyndablePortal.i18n.error);
                btn.prop('disabled', false).text('Cancel Subscription');
            }
        }).fail(function () {
            showAlert('panel-subscription', 'error', FyndablePortal.i18n.error);
            btn.prop('disabled', false).text('Cancel Subscription');
        });
    });

    // --- Usage ---
    function loadUsage() {
        var panel = '#panel-usage';
        showLoading('panel-usage');

        apiRequest('/portal/usage').done(function (res) {
            loaded.usage = true;
            if (!res.success) {
                $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + res.message + '</div>');
                return;
            }

            var u = res.usage;
            var pct = u.api_calls_pct || 0;
            var barClass = pct > 90 ? 'critical' : (pct > 75 ? 'warning' : '');

            var html = '<div class="fyndable-portal-card">';
            html += '<h3>Usage for ' + u.period + '</h3>';
            html += '<div class="fyndable-portal-detail-grid" style="margin-bottom:16px;">';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">API Calls</span><span class="fyndable-portal-detail-value">' + u.api_calls + ' / ' + u.api_calls_limit + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">API Cost</span><span class="fyndable-portal-detail-value">' + formatCurrency(u.api_cost, 'EUR') + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">SERP Requests</span><span class="fyndable-portal-detail-value">' + u.serp_requests + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">Content Generated</span><span class="fyndable-portal-detail-value">' + u.content_generated + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">Keywords Tracked</span><span class="fyndable-portal-detail-value">' + u.keywords_tracked + '</span></div>';
            html += '</div>';

            html += '<div>';
            html += '<div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;"><span>API Usage</span><span>' + pct + '%</span></div>';
            html += '<div class="fyndable-portal-usage-bar"><div class="fyndable-portal-usage-fill ' + barClass + '" style="width:' + Math.min(pct, 100) + '%"></div></div>';
            html += '</div>';
            html += '</div>';

            $(panel).html(html);
        }).fail(function () {
            $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + FyndablePortal.i18n.error + '</div>');
        });
    }

    // --- Download ---
    function loadDownload() {
        var panel = '#panel-download';
        showLoading('panel-download');

        apiRequest('/portal/download').done(function (res) {
            loaded.download = true;
            if (!res.success) {
                $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + res.message + '</div>');
                return;
            }

            var html = '<div class="fyndable-portal-card">';
            html += '<h3>Download Plugin</h3>';
            html += '<p style="color:#6b7280;font-size:14px;margin-bottom:16px;">Latest version: <strong>v' + res.version + '</strong></p>';
            html += '<a href="' + res.download_url + '" class="fyndable-portal-btn fyndable-portal-btn-primary" download>Download Plugin (v' + res.version + ')</a>';
            html += '</div>';

            html += '<div class="fyndable-portal-card">';
            html += '<h3>Your License Key</h3>';
            html += '<div class="fyndable-portal-license-box">';
            html += '<span class="fyndable-portal-license-key">' + res.license_key + '</span>';
            html += '<button class="fyndable-portal-copy-btn" onclick="copyToClipboard(\'' + res.license_key + '\')">Copy</button>';
            html += '</div>';
            html += '<p style="font-size:13px;color:#6b7280;">Dashboard URL: <code>' + res.dashboard_url + '</code></p>';
            html += '</div>';

            html += '<div class="fyndable-portal-card">';
            html += '<h3>Installation Instructions</h3>';
            html += '<ol style="font-size:14px;line-height:1.8;padding-left:20px;">';
            html += '<li>Download the zip file above.</li>';
            html += '<li>In your WordPress admin, go to <strong>Plugins &gt; Add New &gt; Upload Plugin</strong>.</li>';
            html += '<li>Upload the zip file and click <strong>Install Now</strong>, then <strong>Activate</strong>.</li>';
            html += '<li>Go to the plugin settings and enter your license key and dashboard URL.</li>';
            html += '<li>Save settings — the plugin will validate your license automatically.</li>';
            html += '</ol>';
            html += '</div>';

            $(panel).html(html);
        }).fail(function () {
            $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + FyndablePortal.i18n.error + '</div>');
        });
    }

    // --- Invoices ---
    function loadInvoices() {
        var panel = '#panel-invoices';
        showLoading('panel-invoices');

        apiRequest('/portal/invoices').done(function (res) {
            loaded.invoices = true;
            if (!res.success) {
                $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + res.message + '</div>');
                return;
            }

            if (!res.invoices || res.invoices.length === 0) {
                $(panel).html('<div class="fyndable-portal-card"><p style="color:#6b7280;text-align:center;padding:20px;">No invoices yet.</p></div>');
                return;
            }

            var html = '<div class="fyndable-portal-card"><table class="fyndable-portal-table"><thead><tr>';
            html += '<th>Invoice #</th><th>Date</th><th>Description</th><th>Amount</th><th>Status</th><th></th>';
            html += '</tr></thead><tbody>';

            res.invoices.forEach(function (inv) {
                html += '<tr>';
                html += '<td>' + inv.invoice_number + '</td>';
                html += '<td>' + formatDate(inv.created_at) + '</td>';
                html += '<td>' + (inv.description || 'Subscription') + '</td>';
                html += '<td>' + formatCurrency(inv.amount, inv.currency) + '</td>';
                html += '<td>' + statusBadge(inv.status) + '</td>';
                html += '<td><button class="fyndable-portal-btn fyndable-portal-btn-secondary view-invoice-btn" data-id="' + inv.id + '">View</button></td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            $(panel).html(html);
        }).fail(function () {
            $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + FyndablePortal.i18n.error + '</div>');
        });
    }

    // View invoice modal
    $(document).on('click', '.view-invoice-btn', function () {
        var id = $(this).data('id');
        var modal = $('#invoice-modal');
        var body = $('#invoice-modal-body');

        body.html('<div class="fyndable-portal-loading">Loading invoice...</div>');
        modal.show();

        apiRequest('/portal/invoice/' + id).done(function (res) {
            if (res.success) {
                body.html(res.html);
            } else {
                body.html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + (res.message || 'Error') + '</div>');
            }
        }).fail(function () {
            body.html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + FyndablePortal.i18n.error + '</div>');
        });
    });

    // Close modal on overlay click
    $(document).on('click', '.fyndable-portal-modal-overlay', function () {
        $('#invoice-modal').hide();
    });

    // --- Account ---
    function loadAccount() {
        var panel = '#panel-account';
        showLoading('panel-account');

        apiRequest('/portal/subscription').done(function (res) {
            loaded.account = true;
            if (!res.success) {
                $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + res.message + '</div>');
                return;
            }

            var s = res.subscription;
            var html = '<div class="fyndable-portal-card">';
            html += '<h3>Account Settings</h3>';
            html += '<form id="account-form">';
            html += '<div class="fyndable-portal-form-group"><label>Name</label><input type="text" name="name" value="' + (s.tier ? '' : '') + '" placeholder="Your name" /></div>';
            html += '<div class="fyndable-portal-form-group"><label>Domain</label><input type="text" name="domain" value="' + (s.domain || '') + '" placeholder="https://yoursite.com" /></div>';
            html += '<button type="submit" class="fyndable-portal-btn fyndable-portal-btn-primary">Save Changes</button>';
            html += '</form>';
            html += '</div>';

            html += '<div class="fyndable-portal-card">';
            html += '<h3>Password</h3>';
            html += '<p style="color:#6b7280;font-size:14px;margin-bottom:12px;">To change your password, use the password reset link.</p>';
            html += '<a href="' + FyndablePortal.loginUrl.replace('wp-login.php', 'wp-login.php?action=lostpassword') + '" class="fyndable-portal-btn fyndable-portal-btn-secondary">Reset Password</a>';
            html += '</div>';

            $(panel).html(html);
        }).fail(function () {
            $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + FyndablePortal.i18n.error + '</div>');
        });
    }

    // Save account form
    $(document).on('submit', '#account-form', function (e) {
        e.preventDefault();
        var data = {
            name: $(this).find('input[name="name"]').val(),
            domain: $(this).find('input[name="domain"]').val(),
        };

        apiRequest('/portal/account', 'POST', data).done(function (res) {
            if (res.success) {
                showAlert('panel-account', 'success', res.message);
            } else {
                showAlert('panel-account', 'error', res.message || FyndablePortal.i18n.error);
            }
        }).fail(function () {
            showAlert('panel-account', 'error', FyndablePortal.i18n.error);
        });
    });

    // Copy license key to clipboard
    $(document).on('click', '.fyndable-portal-copy-btn', function (e) {
        e.preventDefault();
        var key = $(this).data('key');
        if (window.copyToClipboard) {
            window.copyToClipboard(key);
        }
    });

    // --- Init: load first tab ---
    $(document).ready(function () {
        loadSubscription();
    });

    // Copy to clipboard helper
    window.copyToClipboard = function (text) {
        var el = document.createElement('textarea');
        el.value = text;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        alert(FyndablePortal.i18n.copied);
    };

})(jQuery);
