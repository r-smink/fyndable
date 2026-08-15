(function ($) {
    'use strict';

    var FyndablePortal = window.FyndablePortal || {};
    var loaded = {};

    function t(key, replacements) {
        return window.FyndableI18n ? window.FyndableI18n.t(key, replacements) : key;
    }

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
        $('#' + panelId).html('<div class="fyndable-portal-loading">' + t('loading') + '</div>');
    }

    function showAlert(panelId, type, message) {
        $('#' + panelId).prepend('<div class="fyndable-portal-alert fyndable-portal-alert-' + type + '">' + message + '</div>');
        setTimeout(function () {
            $('#' + panelId + ' .fyndable-portal-alert').fadeOut(400, function () { $(this).remove(); });
        }, 5000);
    }

    // Extract a specific error message from a failed AJAX response.
    // Falls back to the generic i18n 'error_generic' string when no
    // parseable message is found.
    function failMessage(jqXHR) {
        var msg = '';
        if (jqXHR) {
            // jQuery may have already parsed the JSON body
            if (jqXHR.responseJSON) {
                msg = jqXHR.responseJSON.message || '';
            } else if (jqXHR.responseText) {
                try {
                    var parsed = JSON.parse(jqXHR.responseText);
                    msg = (parsed && parsed.message) ? parsed.message : '';
                } catch (e) { /* not JSON */ }
            }
        }
        return msg || t('error_generic');
    }

    function formatCurrency(amount, currency) {
        var symbols = { EUR: '€' };
        var symbol = symbols[(currency || 'EUR').toUpperCase()] || '€';
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

    function connectionBadge(connected, domain) {
        var cls = 'fyndable-portal-badge-' + (connected ? 'connected' : 'unconnected');
        var label = connected ? t('connected') : t('not_connected');
        var domainHtml = connected && domain ? ' <span class="fyndable-portal-domain">(' + domain + ')</span>' : '';
        return '<span class="fyndable-portal-badge ' + cls + '">' + label + '</span>' + domainHtml;
    }

    // --- Apply data-i18n attributes to static HTML elements ---
    function applyDataI18n() {
        $('[data-i18n]').each(function () {
            var key = $(this).data('i18n');
            var arg = $(this).data('i18n-arg');
            if (arg !== undefined) {
                $(this).text(t(key, { s: arg }));
            } else {
                $(this).text(t(key));
            }
        });
        $('[data-i18n-link]').each(function () {
            var key = $(this).data('i18n-link');
            $(this).text(t(key));
        });
    }

    // --- Language toggle ---
    function updateLangToggleActive() {
        var lang = window.FyndableI18n ? window.FyndableI18n.getLang() : 'en';
        $('.fyndable-portal-lang-toggle button').removeClass('active');
        $('.fyndable-portal-lang-toggle button[data-lang="' + lang + '"]').addClass('active');
    }

    $(document).on('click', '.fyndable-portal-lang-toggle button', function () {
        var lang = $(this).data('lang');
        if (!window.FyndableI18n) return;
        window.FyndableI18n.setLang(lang);
        // Persist to user meta via REST (only for logged-in users)
        if (FyndablePortal.nonce) {
            apiRequest('/portal/language', 'POST', { lang: lang }).fail(function () { /* ignore for non-logged-in */ });
        }
    });

    // Re-render on language change
    document.addEventListener('langchange', function () {
        applyDataI18n();
        updateLangToggleActive();
        // Reset loaded tabs so they re-render in the new language
        loaded = {};
        // Re-load the currently active tab
        var activeTab = $('.fyndable-portal-tab.active').data('tab');
        if (activeTab) {
            loadTab(activeTab);
        }
    });

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
                $(panel).html('<div class="fyndable-portal-error">' + (res.message || t('error_generic')) + '</div>');
                return;
            }

            var html = '<div class="fyndable-portal-card">';
            html += '<h3>' + (res.tier ? res.tier.charAt(0).toUpperCase() + res.tier.slice(1) : '') + ' ' + t('tab_license') + '</h3>';
            html += '<div class="fyndable-portal-license-key-row">';
            html += '<label>' + t('license_label') + '</label>';
            html += '<div class="fyndable-portal-license-key-box">';
            html += '<code class="fyndable-portal-license-key">' + res.license_key + '</code>';
            html += '<button class="button fyndable-portal-copy-btn" data-key="' + res.license_key + '">' + t('copy') + '</button>';
            html += '</div></div>';
            html += '<table class="fyndable-portal-info-table">';
            html += '<tr><td>' + t('connection_status') + '</td><td>' + connectionBadge(res.connected, res.domain_host) + '</td></tr>';
            html += '<tr><td>' + t('status') + '</td><td>' + statusBadge(res.status) + '</td></tr>';
            html += '<tr><td>' + t('tier') + '</td><td>' + (res.tier || '—') + '</td></tr>';
            html += '<tr><td>' + t('max_sites') + '</td><td>' + (res.max_sites || 1) + '</td></tr>';
            html += '<tr><td>' + t('rate_limit') + '</td><td>' + (res.rate_limit || 60) + ' ' + t('rate_limit_unit') + '</td></tr>';
            html += '<tr><td>' + t('api_calls_limit') + '</td><td>' + (res.api_calls_limit || 1000) + ' ' + t('api_calls_limit_unit') + '</td></tr>';
            html += '<tr><td>' + t('expires') + '</td><td>' + formatDate(res.expires_at) + '</td></tr>';
            html += '</table>';
            if (res.connected) {
                html += '<div class="fyndable-portal-license-actions">';
                html += '<button class="fyndable-portal-btn fyndable-portal-btn-danger" id="fyndable-portal-disconnect-btn">' + t('disconnect') + '</button>';
                html += '</div>';
            }
            html += '<p class="fyndable-portal-help">' + t('license_help') + '</p>';
            html += '</div>';

            $(panel).html(html);
            loaded.license = true;
        }).fail(function (jqXHR) {
            $(panel).html('<div class="fyndable-portal-error">' + failMessage(jqXHR) + '</div>');
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
            html += '<h3>' + t('subscription_details') + '</h3>';
            html += '<div class="fyndable-portal-detail-grid">';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">' + t('plan') + '</span><span class="fyndable-portal-detail-value">' + (s.tier || '—') + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">' + t('billing_period') + '</span><span class="fyndable-portal-detail-value">' + (s.interval === 'year' ? t('yearly_label') : t('monthly_label')) + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">' + t('amount') + '</span><span class="fyndable-portal-detail-value">' + formatCurrency(s.monthly_amount, s.currency) + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">' + t('status') + '</span>' + statusBadge(s.status) + '</div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">' + t('payment_status') + '</span><span class="fyndable-portal-detail-value">' + (s.payment_status || '—') + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">' + t('started') + '</span><span class="fyndable-portal-detail-value">' + formatDate(s.created_at) + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">' + t('renews_expires') + '</span><span class="fyndable-portal-detail-value">' + formatDate(s.expires_at) + '</span></div>';
            if (pd.next_payment_date) {
                html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">' + t('next_payment') + '</span><span class="fyndable-portal-detail-value">' + formatDate(pd.next_payment_date) + '</span></div>';
            }
            html += '</div>';
            html += '</div>';

            // Cancel button
            if (s.status === 'active' || s.payment_status === 'active') {
                html += '<div class="fyndable-portal-card">';
                html += '<h3>' + t('cancel_subscription') + '</h3>';
                html += '<p style="color:#6b7280;font-size:14px;margin-bottom:16px;">' + t('cancel_subscription_desc') + '</p>';
                html += '<button class="fyndable-portal-btn fyndable-portal-btn-danger" id="cancel-subscription-btn">' + t('cancel_subscription') + '</button>';
                html += '</div>';
            }

            html += '<div class="fyndable-portal-card" id="fyndable-portal-upgrade-card" style="display:none;"></div>';

            $(panel).html(html);
            loadUpgradeOptions();
        }).fail(function (jqXHR) {
            $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + failMessage(jqXHR) + '</div>');
        });
    }

    // --- Upgrade options ---
    function loadUpgradeOptions() {
        var $card = $('#fyndable-portal-upgrade-card');
        $card.html('<div class="fyndable-portal-loading">' + t('loading') + '</div>').show();

        apiRequest('/portal/tiers').done(function (res) {
            if (!res.success || !res.tiers || !res.tiers.length) {
                $card.hide();
                return;
            }

            var html = '<h3>' + t('upgrade_subscription') + '</h3>';
            html += '<p style="color:#6b7280;font-size:14px;margin-bottom:16px;">' + t('upgrade_subscription_desc') + '</p>';
            html += '<div class="fyndable-portal-form-group">';
            html += '<select id="fyndable-portal-upgrade-tier" class="fyndable-portal-form-select">';
            res.tiers.forEach(function (tier) {
                html += '<option value="' + tier.key + '">' + tier.label + ' — ' + formatCurrency(tier.amount, tier.currency) + ' / ' + (res.interval === 'year' ? t('yearly_label') : t('monthly_label')) + '</option>';
            });
            html += '</select>';
            html += '</div>';
            html += '<button class="fyndable-portal-btn fyndable-portal-btn-primary" id="fyndable-portal-upgrade-btn">' + t('upgrade_subscription') + '</button>';
            $card.html(html);
        }).fail(function () {
            $card.hide();
        });
    }

    // Upgrade subscription
    $(document).on('click', '#fyndable-portal-upgrade-btn', function () {
        var tier = $('#fyndable-portal-upgrade-tier').val();
        if (!tier) return;
        if (!confirm(t('confirm_upgrade', { s: tier }))) return;

        var btn = $(this);
        btn.prop('disabled', true).text(t('loading'));

        apiRequest('/portal/upgrade', 'POST', { tier: tier }).done(function (res) {
            if (res.success) {
                showAlert('panel-subscription', 'success', res.message);
                loaded.subscription = false;
                setTimeout(loadSubscription, 1500);
            } else {
                showAlert('panel-subscription', 'error', res.message || t('error_generic'));
                btn.prop('disabled', false).text(t('upgrade_subscription'));
            }
        }).fail(function (jqXHR) {
            showAlert('panel-subscription', 'error', failMessage(jqXHR));
            btn.prop('disabled', false).text(t('upgrade_subscription'));
        });
    });

    // Cancel subscription
    $(document).on('click', '#cancel-subscription-btn', function () {
        if (!confirm(t('confirm_cancel'))) {
            return;
        }
        var btn = $(this);
        btn.prop('disabled', true).text(t('loading'));

        apiRequest('/portal/cancel', 'POST').done(function (res) {
            if (res.success) {
                showAlert('panel-subscription', 'success', res.message);
                loaded.subscription = false;
                setTimeout(loadSubscription, 1500);
            } else {
                showAlert('panel-subscription', 'error', res.message || t('error_generic'));
                btn.prop('disabled', false).text(t('cancel_subscription'));
            }
        }).fail(function (jqXHR) {
            showAlert('panel-subscription', 'error', failMessage(jqXHR));
            btn.prop('disabled', false).text(t('cancel_subscription'));
        });
    });

    // Disconnect license from connected domain
    $(document).on('click', '#fyndable-portal-disconnect-btn', function () {
        if (!confirm(t('confirm_disconnect'))) {
            return;
        }
        var btn = $(this);
        btn.prop('disabled', true).text(t('loading'));

        apiRequest('/portal/license/disconnect', 'POST').done(function (res) {
            if (res.success) {
                showAlert('panel-license', 'success', t('disconnected_success'));
                loaded.license = false;
                loadLicense();
            } else {
                showAlert('panel-license', 'error', res.message || t('error_generic'));
                btn.prop('disabled', false).text(t('disconnect'));
            }
        }).fail(function (jqXHR) {
            showAlert('panel-license', 'error', failMessage(jqXHR));
            btn.prop('disabled', false).text(t('disconnect'));
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
            html += '<h3>' + t('usage_for') + ' ' + u.period + '</h3>';
            html += '<div class="fyndable-portal-detail-grid" style="margin-bottom:16px;">';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">' + t('api_calls') + '</span><span class="fyndable-portal-detail-value">' + u.api_calls + ' / ' + u.api_calls_limit + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">' + t('api_cost') + '</span><span class="fyndable-portal-detail-value">' + formatCurrency(u.api_cost, 'EUR') + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">' + t('serp_requests') + '</span><span class="fyndable-portal-detail-value">' + u.serp_requests + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">' + t('content_generated') + '</span><span class="fyndable-portal-detail-value">' + u.content_generated + '</span></div>';
            html += '<div class="fyndable-portal-detail-item"><span class="fyndable-portal-detail-label">' + t('keywords_tracked') + '</span><span class="fyndable-portal-detail-value">' + u.keywords_tracked + '</span></div>';
            html += '</div>';

            html += '<div>';
            html += '<div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;"><span>' + t('api_usage') + '</span><span>' + pct + '%</span></div>';
            html += '<div class="fyndable-portal-usage-bar"><div class="fyndable-portal-usage-fill ' + barClass + '" style="width:' + Math.min(pct, 100) + '%"></div></div>';
            html += '</div>';
            html += '</div>';

            $(panel).html(html);
        }).fail(function (jqXHR) {
            $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + failMessage(jqXHR) + '</div>');
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
            html += '<h3>' + t('download_plugin') + '</h3>';
            html += '<p style="color:#6b7280;font-size:14px;margin-bottom:16px;">' + t('latest_version') + ' <strong>v' + res.version + '</strong></p>';
            html += '<a href="' + res.download_url + '" class="fyndable-portal-btn fyndable-portal-btn-primary" download>' + t('download_plugin_version', { s: res.version }) + '</a>';
            html += '</div>';

            html += '<div class="fyndable-portal-card">';
            html += '<h3>' + t('your_license_key') + '</h3>';
            html += '<div class="fyndable-portal-license-box">';
            html += '<span class="fyndable-portal-license-key">' + res.license_key + '</span>';
            html += '<button class="fyndable-portal-copy-btn" data-key="' + res.license_key + '">' + t('copy') + '</button>';
            html += '</div>';
            html += '<p style="font-size:13px;color:#6b7280;">' + t('dashboard_url') + ' <code>' + res.dashboard_url + '</code></p>';
            html += '</div>';

            html += '<div class="fyndable-portal-card">';
            html += '<h3>' + t('installation_instructions') + '</h3>';
            html += '<ol style="font-size:14px;line-height:1.8;padding-left:20px;">';
            html += '<li>' + t('install_step_1') + '</li>';
            html += '<li>' + t('install_step_2') + '</li>';
            html += '<li>' + t('install_step_3') + '</li>';
            html += '<li>' + t('install_step_4') + '</li>';
            html += '<li>' + t('install_step_5') + '</li>';
            html += '</ol>';
            html += '</div>';

            $(panel).html(html);
        }).fail(function (jqXHR) {
            $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + failMessage(jqXHR) + '</div>');
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
                $(panel).html('<div class="fyndable-portal-card"><p style="color:#6b7280;text-align:center;padding:20px;">' + t('no_invoices') + '</p></div>');
                return;
            }

            var html = '<div class="fyndable-portal-card"><table class="fyndable-portal-table"><thead><tr>';
            html += '<th>' + t('invoice_number') + '</th><th>' + t('date') + '</th><th>' + t('description') + '</th><th>' + t('amount') + '</th><th>' + t('status') + '</th><th></th>';
            html += '</tr></thead><tbody>';

            res.invoices.forEach(function (inv) {
                html += '<tr>';
                html += '<td>' + inv.invoice_number + '</td>';
                html += '<td>' + formatDate(inv.created_at) + '</td>';
                html += '<td>' + (inv.description || t('subscription_default')) + '</td>';
                html += '<td>' + formatCurrency(inv.amount, inv.currency) + '</td>';
                html += '<td>' + statusBadge(inv.status) + '</td>';
                html += '<td class="fyndable-portal-invoice-actions">';
                html += '<button class="fyndable-portal-btn fyndable-portal-btn-secondary view-invoice-btn" data-id="' + inv.id + '">' + t('view') + '</button> ';
                html += '<button class="fyndable-portal-btn fyndable-portal-btn-secondary download-invoice-btn" data-id="' + inv.id + '">' + t('download_pdf') + '</button>';
                html += '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            $(panel).html(html);
        }).fail(function (jqXHR) {
            $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + failMessage(jqXHR) + '</div>');
        });
    }

    // View invoice modal
    $(document).on('click', '.view-invoice-btn', function () {
        var id = $(this).data('id');
        var modal = $('#invoice-modal');
        var body = $('#invoice-modal-body');

        body.html('<div class="fyndable-portal-loading">' + t('loading_invoice') + '</div>');
        modal.show();

        apiRequest('/portal/invoice/' + id).done(function (res) {
            if (res.success) {
                body.html(res.html);
            } else {
                body.html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + (res.message || t('error_generic')) + '</div>');
            }
        }).fail(function (jqXHR) {
            body.html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + failMessage(jqXHR) + '</div>');
        });
    });

    // Close modal on overlay click
    $(document).on('click', '.fyndable-portal-modal-overlay', function () {
        $('#invoice-modal').hide();
    });

    // Download invoice as PDF (browser print-to-PDF in a new window)
    $(document).on('click', '.download-invoice-btn', function () {
        var id = $(this).data('id');
        var $btn = $(this);
        $btn.prop('disabled', true);

        apiRequest('/portal/invoice/' + id + '/print').done(function (res) {
            if (res.success && res.html) {
                var w = window.open('', '_blank');
                if (!w) {
                    alert(t('allow_popups'));
                    return;
                }
                w.document.open();
                w.document.write(res.html);
                w.document.close();
            } else {
                alert((res && res.message) || t('error_invoice'));
            }
        }).fail(function () {
            alert(t('error_invoice'));
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // --- Account ---
    function loadAccount() {
        var panel = '#panel-account';
        showLoading('panel-account');

        apiRequest('/portal/account').done(function (res) {
            loaded.account = true;
            if (!res.success) {
                $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + res.message + '</div>');
                return;
            }

            var a = res.account;
            var html = '<div class="fyndable-portal-card">';
            html += '<h3>' + t('account_settings') + '</h3>';
            html += '<form id="account-form">';
            html += '<div class="fyndable-portal-form-group"><label>' + t('name') + '</label><input type="text" name="name" value="' + (a.name || '') + '" placeholder="' + t('name_placeholder') + '" /></div>';
            html += '<div class="fyndable-portal-form-group"><label>' + t('email_address') + '</label><input type="email" name="email" value="' + (a.email || '') + '" placeholder="' + t('email_placeholder') + '" /></div>';
            html += '<div class="fyndable-portal-form-group"><label>' + t('phone') + '</label><input type="tel" name="phone" value="' + (a.phone || '') + '" placeholder="' + t('phone_placeholder') + '" /></div>';
            html += '<div class="fyndable-portal-form-group"><label>' + t('address') + '</label><textarea name="address" rows="3" placeholder="' + t('address_placeholder') + '">' + (a.address || '') + '</textarea></div>';
            html += '<div class="fyndable-portal-form-group"><label>' + t('domain') + '</label><input type="text" name="domain" value="' + (a.domain || '') + '" placeholder="' + t('domain_placeholder') + '" /></div>';
            html += '<button type="submit" class="fyndable-portal-btn fyndable-portal-btn-primary">' + t('save_changes') + '</button>';
            html += '</form>';
            html += '</div>';

            html += '<div class="fyndable-portal-card">';
            html += '<h3>' + t('password') + '</h3>';
            html += '<p style="color:#6b7280;font-size:14px;margin-bottom:12px;">' + t('password_desc') + '</p>';
            html += '<a href="' + FyndablePortal.loginUrl.replace('wp-login.php', 'wp-login.php?action=lostpassword') + '" class="fyndable-portal-btn fyndable-portal-btn-secondary">' + t('reset_password') + '</a>';
            html += '</div>';

            $(panel).html(html);
        }).fail(function (jqXHR) {
            $(panel).html('<div class="fyndable-portal-alert fyndable-portal-alert-error">' + failMessage(jqXHR) + '</div>');
        });
    }

    // Save account form
    $(document).on('submit', '#account-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        var data = {
            name: $form.find('input[name="name"]').val(),
            email: $form.find('input[name="email"]').val(),
            phone: $form.find('input[name="phone"]').val(),
            address: $form.find('textarea[name="address"]').val(),
            domain: $form.find('input[name="domain"]').val(),
        };

        apiRequest('/portal/account', 'POST', data).done(function (res) {
            if (res.success) {
                showAlert('panel-account', 'success', res.message);
            } else {
                showAlert('panel-account', 'error', res.message || t('error_generic'));
            }
        }).fail(function (jqXHR) {
            showAlert('panel-account', 'error', failMessage(jqXHR));
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

    // --- Init ---
    $(document).ready(function () {
        applyDataI18n();
        updateLangToggleActive();
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
        alert(t('copied_clipboard'));
    };

})(jQuery);
