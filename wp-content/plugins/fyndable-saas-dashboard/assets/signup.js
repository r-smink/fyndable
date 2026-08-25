(function () {
    var container = document.getElementById('fyndable-signup-app');
    if (!container) return;

    var restUrl = window.FyndableSignup?.restUrl || '/wp-json/ai-seo-saas/v1';
    var nonce = window.FyndableSignup?.nonce || '';
    var selectedTier = null;
    var selectedInterval = 'month';
    var selectedPaymentMethod = '';
    var paymentProvider = 'stripe';
    var availablePaymentMethods = {};
    var trialEnabled = true;
    var plans = {};
    var queryParams = getQueryParams();

    function getQueryParams() {
        var params = {};
        if (typeof window === 'undefined' || !window.location || !window.location.search) {
            return params;
        }
        var search = window.location.search;
        try {
            var qs = new URLSearchParams(search);
            var tier = qs.get('tier');
            var interval = qs.get('interval');
            if (tier) {
                params.tier = tier.toLowerCase();
            }
            if (interval) {
                interval = interval.toLowerCase();
                if (interval === 'month' || interval === 'year') {
                    params.interval = interval;
                }
            }
        } catch (e) {
            var pairs = search.substring(1).split('&');
            for (var i = 0; i < pairs.length; i++) {
                var pair = pairs[i].split('=');
                var key = decodeURIComponent(pair[0] || '').toLowerCase();
                var value = decodeURIComponent((pair[1] || '').replace(/\+/g, ' '));
                if (key === 'tier') {
                    params.tier = value.toLowerCase();
                }
                if (key === 'interval') {
                    var intervalValue = value.toLowerCase();
                    if (intervalValue === 'month' || intervalValue === 'year') {
                        params.interval = intervalValue;
                    }
                }
            }
        }
        return params;
    }

    function init() {
        if (queryParams.interval) {
            selectedInterval = queryParams.interval;
        }
        fetch(restUrl + '/signup/plans?_=' + Date.now())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    paymentProvider = data.provider || 'stripe';
                    trialEnabled = !!data.trial_enabled;
                    renderPlans(data.plans, data.provider, data.payment_methods, data.trial_enabled);
                    if (queryParams.tier && plans[queryParams.tier] && plans[queryParams.tier].self_serve !== false) {
                        selectedTier = queryParams.tier;
                        showStep('form');
                        updatePriceDisplays();
                    }
                }
            })
            .catch(function () {
                container.innerHTML = '<div class="fyndable-signup-loading">' + FyndableI18n.t('failed_to_load_plans') + '</div>';
            });
    }

    function renderLangToggle() {
        var lang = FyndableI18n.getLang();
        return '<div class="fyndable-signup-lang-toggle">'
            + '<button type="button" data-lang="en"' + (lang === 'en' ? ' class="active"' : '') + '>' + FyndableI18n.t('lang_en') + '</button>'
            + '<span class="fyndable-signup-lang-sep">|</span>'
            + '<button type="button" data-lang="nl"' + (lang === 'nl' ? ' class="active"' : '') + '>' + FyndableI18n.t('lang_nl') + '</button>'
            + '</div>';
    }

    function bindLangToggle() {
        container.querySelectorAll('.fyndable-signup-lang-toggle button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                FyndableI18n.setLang(btn.dataset.lang);
            });
        });
    }

    function renderPlans(plansData, provider, paymentMethods, trial_enabled) {
        plans = plansData;
        if (typeof provider !== 'undefined') {
            paymentProvider = provider;
        }
        if (typeof paymentMethods !== 'undefined') {
            availablePaymentMethods = paymentMethods;
        }
        if (typeof trial_enabled !== 'undefined') {
            trialEnabled = !!trial_enabled;
        }
        var paymentMethodOptions = '';
        Object.keys(availablePaymentMethods).forEach(function (key) {
            paymentMethodOptions += '<option value="' + escapeHtml(key) + '"' + (selectedPaymentMethod === key ? ' selected' : '') + '>' + escapeHtml(availablePaymentMethods[key]) + '</option>';
        });
        var html = '<div class="fyndable-signup-container">';
        html += '<div class="fyndable-signup-header">';
        html += renderLangToggle();
        html += '<h1>' + escapeHtml(FyndableI18n.t('choose_your_plan')) + '</h1>';
        if (trialEnabled) {
            html += '<p>' + escapeHtml(FyndableI18n.t('trial_subtitle')) + '</p>';
        }
        html += '</div>';
        html += '<div class="fyndable-signup-billing">';
        html += '<span class="fyndable-signup-billing-label">' + escapeHtml(FyndableI18n.t('monthly')) + '</span>';
        html += '<label class="fyndable-signup-toggle">';
        html += '<input type="checkbox" id="fyndable-billing-toggle" ' + (selectedInterval === 'year' ? 'checked' : '') + '>';
        html += '<span class="fyndable-signup-toggle-slider"></span>';
        html += '</label>';
        html += '<span class="fyndable-signup-billing-label">' + escapeHtml(FyndableI18n.t('yearly')) + ' <span class="fyndable-signup-savings">' + escapeHtml(FyndableI18n.t('months_free')) + '</span></span>';
        html += '</div>';
        html += '<div class="fyndable-signup-plans">';

        Object.keys(plans).forEach(function (key) {
            var plan = plans[key];
            var interval = plan.intervals[selectedInterval];
            html += '<div class="fyndable-signup-plan' + (plan.popular ? ' popular' : '') + '" data-tier="' + key + '">';
            if (plan.popular) html += '<span class="badge">' + escapeHtml(FyndableI18n.t('most_popular')) + '</span>';
            html += '<h3>' + escapeHtml(plan.name) + '</h3>';
            html += '<div class="price">' + escapeHtml(interval.price_display) + '<span class="period">' + escapeHtml(interval.period) + '</span></div>';
            html += '<ul>';
            plan.features.forEach(function (f) {
                html += '<li>' + escapeHtml(f) + '</li>';
            });
            html += '</ul>';
            html += '<button data-tier="' + key + '">' + escapeHtml(plan.cta) + '</button>';
            html += '</div>';
        });

        html += '</div>';

        // Form step (hidden initially)
        html += '<div class="fyndable-signup-step" id="fyndable-signup-form-step">';
        html += '<div class="fyndable-signup-form">';
        html += '<a class="fyndable-signup-back" id="fyndable-signup-back">' + escapeHtml(FyndableI18n.t('back_to_plans')) + '</a>';
        html += '<h2>' + escapeHtml(FyndableI18n.t('create_your_account')) + '</h2>';
        html += '<p class="subtitle">' + escapeHtml(FyndableI18n.t('you_selected')) + ' <strong id="fyndable-selected-plan"></strong></p>';
        html += '<div id="fyndable-signup-error"></div>';
        html += '<div class="fyndable-signup-field"><label>' + escapeHtml(FyndableI18n.t('your_name')) + '</label><input type="text" id="fyndable-name" placeholder="' + escapeHtml(FyndableI18n.t('name_placeholder')) + '"></div>';
        html += '<div class="fyndable-signup-field"><label>' + escapeHtml(FyndableI18n.t('email_address')) + '</label><input type="email" id="fyndable-email" placeholder="' + escapeHtml(FyndableI18n.t('email_placeholder')) + '"></div>';
        html += '<div class="fyndable-signup-field"><label>' + escapeHtml(FyndableI18n.t('street_address')) + '</label><input type="text" id="fyndable-street" placeholder="' + escapeHtml(FyndableI18n.t('street_placeholder')) + '"></div>';
        html += '<div class="fyndable-signup-field-row">';
        html += '<div class="fyndable-signup-field"><label>' + escapeHtml(FyndableI18n.t('postal_code')) + '</label><input type="text" id="fyndable-postalcode" placeholder="' + escapeHtml(FyndableI18n.t('postal_placeholder')) + '"></div>';
        html += '<div class="fyndable-signup-field"><label>' + escapeHtml(FyndableI18n.t('city')) + '</label><input type="text" id="fyndable-city" placeholder="' + escapeHtml(FyndableI18n.t('city_placeholder')) + '"></div>';
        html += '</div>';
        html += '<div class="fyndable-signup-field"><label>' + escapeHtml(FyndableI18n.t('country')) + '</label><select id="fyndable-country"><option value="NL">Netherlands</option><option value="BE">Belgium</option><option value="DE">Germany</option><option value="FR">France</option><option value="GB">United Kingdom</option><option value="US">United States</option><option value="ES">Spain</option><option value="PT">Portugal</option><option value="IT">Italy</option><option value="DK">Denmark</option><option value="SE">Sweden</option><option value="FI">Finland</option><option value="NO">Norway</option><option value="AT">Austria</option><option value="CH">Switzerland</option><option value="IE">Ireland</option><option value="PL">Poland</option><option value="LU">Luxembourg</option><option value="other">Other</option></select></div>';
        html += '<div class="fyndable-signup-field fyndable-signup-payment-method" id="fyndable-payment-method-field" style="display: ' + (paymentProvider === 'mollie' ? 'block' : 'none') + ';">'
            + '<label>' + escapeHtml(FyndableI18n.t('payment_method')) + '</label>'
            + '<select id="fyndable-payment-method">' + paymentMethodOptions + '</select>'
            + '</div>';
        html += '<button class="fyndable-signup-submit" id="fyndable-signup-submit">' + escapeHtml(FyndableI18n.t('create_account_btn')) + '</button>';
        html += '</div></div>';

        // Success step (hidden initially)
        html += '<div class="fyndable-signup-step" id="fyndable-signup-success-step"></div>';

        html += '</div>';

        container.innerHTML = html;

        if (selectedTier) {
            var selectedPlanEl = document.getElementById('fyndable-selected-plan');
            if (selectedPlanEl) {
                selectedPlanEl.textContent = plans[selectedTier].name + ' — ' + plans[selectedTier].intervals[selectedInterval].price_display + plans[selectedTier].intervals[selectedInterval].period;
            }
        }

        // Bind language toggle
        bindLangToggle();

        // Bind payment method selection
        var paymentMethodSelect = document.getElementById('fyndable-payment-method');
        if (paymentMethodSelect) {
            selectedPaymentMethod = paymentMethodSelect.value;
            paymentMethodSelect.addEventListener('change', function () {
                selectedPaymentMethod = paymentMethodSelect.value;
            });
        }

        // Bind plan selection
        container.querySelectorAll('.fyndable-signup-plan button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectedTier = btn.dataset.tier;
                document.getElementById('fyndable-selected-plan').textContent = plans[selectedTier].name + ' — ' + plans[selectedTier].intervals[selectedInterval].price_display + plans[selectedTier].intervals[selectedInterval].period;
                showStep('form');
            });
        });

        // Billing interval toggle
        var billingToggle = document.getElementById('fyndable-billing-toggle');
        if (billingToggle) {
            billingToggle.addEventListener('change', function () {
                selectedInterval = billingToggle.checked ? 'year' : 'month';
                updatePriceDisplays();
            });
        }

        document.getElementById('fyndable-signup-back').addEventListener('click', function () {
            showStep('plans');
        });

        document.getElementById('fyndable-signup-submit').addEventListener('click', submitSignup);
    }

    function showStep(step) {
        var plansEl = container.querySelector('.fyndable-signup-plans');
        var formEl = document.getElementById('fyndable-signup-form-step');
        var successEl = document.getElementById('fyndable-signup-success-step');
        var headerEl = container.querySelector('.fyndable-signup-header');

        plansEl.style.display = step === 'plans' ? 'grid' : 'none';
        formEl.classList.toggle('active', step === 'form');
        successEl.classList.toggle('active', step === 'success');
        if (headerEl) {
            var titleEl = headerEl.querySelector('h1');
            var subtitleEl = headerEl.querySelector('p');
            var showHeaderText = step === 'plans';
            if (titleEl) titleEl.style.display = showHeaderText ? '' : 'none';
            if (subtitleEl) subtitleEl.style.display = showHeaderText ? '' : 'none';
        }
    }

    function updatePriceDisplays() {
        Object.keys(plans).forEach(function (key) {
            var planEl = container.querySelector('.fyndable-signup-plan[data-tier="' + key + '"]');
            if (!planEl) return;
            var priceEl = planEl.querySelector('.price');
            if (priceEl) {
                var interval = plans[key].intervals[selectedInterval];
                priceEl.innerHTML = escapeHtml(interval.price_display) + '<span class="period">' + escapeHtml(interval.period) + '</span>';
            }
        });
        var selectedPlanEl = document.getElementById('fyndable-selected-plan');
        if (selectedPlanEl && selectedTier && plans[selectedTier]) {
            selectedPlanEl.textContent = plans[selectedTier].name + ' — ' + plans[selectedTier].intervals[selectedInterval].price_display + plans[selectedTier].intervals[selectedInterval].period;
        }
        var billingToggle = document.getElementById('fyndable-billing-toggle');
        if (billingToggle) {
            billingToggle.checked = selectedInterval === 'year';
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function submitSignup() {
        var name = document.getElementById('fyndable-name').value.trim();
        var email = document.getElementById('fyndable-email').value.trim();
        var street = document.getElementById('fyndable-street').value.trim();
        var postalCode = document.getElementById('fyndable-postalcode').value.trim();
        var city = document.getElementById('fyndable-city').value.trim();
        var country = document.getElementById('fyndable-country').value;
        var errorEl = document.getElementById('fyndable-signup-error');
        var submitBtn = document.getElementById('fyndable-signup-submit');

        errorEl.innerHTML = '';

        if (!name || !email || !street || !postalCode || !city) {
            errorEl.innerHTML = '<div class="fyndable-signup-error">' + escapeHtml(FyndableI18n.t('fill_all_fields')) + '</div>';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = FyndableI18n.t('creating_account');

        fetch(restUrl + '/signup/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify({
                name: name,
                email: email,
                street: street,
                postal_code: postalCode,
                city: city,
                country: country,
                tier: selectedTier,
                interval: selectedInterval,
                payment_method: paymentProvider === 'mollie' ? selectedPaymentMethod : '',
            }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    errorEl.innerHTML = '<div class="fyndable-signup-error">' + escapeHtml(data.message || FyndableI18n.t('signup_failed')) + '</div>';
                    submitBtn.disabled = false;
                    submitBtn.textContent = FyndableI18n.t('create_account_btn');
                    return;
                }

                if (data.requires_payment && data.checkout_url) {
                    // Redirect to payment provider
                    window.location.href = data.checkout_url;
                } else {
                    // Free tier — show success
                    showSuccess(data.license_key);
                }
            })
            .catch(function () {
                errorEl.innerHTML = '<div class="fyndable-signup-error">' + escapeHtml(FyndableI18n.t('network_error')) + '</div>';
                submitBtn.disabled = false;
                submitBtn.textContent = FyndableI18n.t('create_account_btn');
            });
    }

    function showSuccess(licenseKey) {
        var successEl = document.getElementById('fyndable-signup-success-step');
        successEl.innerHTML = '<div class="fyndable-signup-success">' +
            '<div class="icon">🎉</div>' +
            '<h2>' + escapeHtml(FyndableI18n.t('welcome_to_fyndable')) + '</h2>' +
            '<p class="instructions">' + escapeHtml(FyndableI18n.t('account_ready')) + '</p>' +
            '<div class="license-key">' + escapeHtml(licenseKey) + '</div>' +
            '<p class="instructions"><strong>' + escapeHtml(FyndableI18n.t('next_steps')) + '</strong><br>' + escapeHtml(FyndableI18n.t('next_step_1')) + '<br>' + escapeHtml(FyndableI18n.t('next_step_2')) + '<br>' + escapeHtml(FyndableI18n.t('next_step_3')) + '</p>' +
            '<button class="fyndable-signup-submit" id="fyndable-copy-license">' + escapeHtml(FyndableI18n.t('copy_license_key')) + '</button>' +
            '</div>';
        var copyBtn = document.getElementById('fyndable-copy-license');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                navigator.clipboard.writeText(licenseKey);
                copyBtn.textContent = FyndableI18n.t('copied');
            });
        }
        showStep('success');
    }

    // Re-render on language change
    document.addEventListener('langchange', function () {
        if (plans && Object.keys(plans).length > 0) {
            renderPlans(plans);
        }
    });

    init();
})();
