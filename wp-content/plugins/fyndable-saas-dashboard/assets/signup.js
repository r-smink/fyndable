(function () {
    var container = document.getElementById('fyndable-signup-app');
    if (!container) return;

    var restUrl = window.FyndableSignup?.restUrl || '/wp-json/ai-seo-saas/v1';
    var nonce = window.FyndableSignup?.nonce || '';
    var selectedTier = null;
    var selectedInterval = 'month';
    var plans = {};

    function init() {
        fetch(restUrl + '/signup/plans')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    renderPlans(data.plans);
                }
            })
            .catch(function () {
                container.innerHTML = '<div class="fyndable-signup-loading">Failed to load plans. Please refresh.</div>';
            });
    }

    function renderPlans(plansData) {
        plans = plansData;
        var html = '<div class="fyndable-signup-container">';
        html += '<div class="fyndable-signup-header"><h1>Choose Your Plan</h1><p>Start free, upgrade anytime. No credit card required.</p></div>';
        html += '<div class="fyndable-signup-billing"><label><input type="radio" name="fyndable-billing" value="month" ' + (selectedInterval === 'month' ? 'checked' : '') + '> Monthly</label> <label><input type="radio" name="fyndable-billing" value="year" ' + (selectedInterval === 'year' ? 'checked' : '') + '> Yearly</label></div>';
        html += '<div class="fyndable-signup-plans">';

        Object.keys(plans).forEach(function (key) {
            var plan = plans[key];
            var interval = plan.intervals[selectedInterval];
            html += '<div class="fyndable-signup-plan' + (plan.popular ? ' popular' : '') + '" data-tier="' + key + '">';
            if (plan.popular) html += '<span class="badge">Most Popular</span>';
            html += '<h3>' + escapeHtml(plan.name) + '</h3>';
            html += '<div class="price">' + escapeHtml(interval.price_display) + '<span class="period">' + escapeHtml(interval.period) + '</span></div>';
            if (selectedInterval === 'year' && interval.savings_label) {
                html += '<div class="savings">' + escapeHtml(interval.savings_label) + '</div>';
            }
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
        html += '<a class="fyndable-signup-back" id="fyndable-signup-back">← Back to plans</a>';
        html += '<h2>Create Your Account</h2>';
        html += '<p class="subtitle">You selected: <strong id="fyndable-selected-plan"></strong></p>';
        html += '<div id="fyndable-signup-error"></div>';
        html += '<div class="fyndable-signup-field"><label>Your Name</label><input type="text" id="fyndable-name" placeholder="John Doe"></div>';
        html += '<div class="fyndable-signup-field"><label>Email Address</label><input type="email" id="fyndable-email" placeholder="john@example.com"></div>';
        html += '<div class="fyndable-signup-field"><label>Website URL</label><input type="url" id="fyndable-siteurl" placeholder="https://yoursite.com"></div>';
        html += '<button class="fyndable-signup-submit" id="fyndable-signup-submit">Create Account →</button>';
        html += '</div></div>';

        // Success step (hidden initially)
        html += '<div class="fyndable-signup-step" id="fyndable-signup-success-step"></div>';

        html += '</div>';

        container.innerHTML = html;

        // Bind plan selection
        container.querySelectorAll('.fyndable-signup-plan button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectedTier = btn.dataset.tier;
                document.getElementById('fyndable-selected-plan').textContent = plans[selectedTier].name + ' — ' + plans[selectedTier].intervals[selectedInterval].price_display + plans[selectedTier].intervals[selectedInterval].period;
                showStep('form');
            });
        });

        // Billing interval toggle
        container.querySelectorAll('input[name="fyndable-billing"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (radio.checked) {
                    selectedInterval = radio.value;
                    renderPlans(plans);
                }
            });
        });

        document.getElementById('fyndable-signup-back').addEventListener('click', function () {
            showStep('plans');
        });

        document.getElementById('fyndable-signup-submit').addEventListener('click', submitSignup);
    }

    function showStep(step) {
        var plansEl = container.querySelector('.fyndable-signup-plans');
        var formEl = document.getElementById('fyndable-signup-form-step');
        var successEl = document.getElementById('fyndable-signup-success-step');

        plansEl.style.display = step === 'plans' ? 'grid' : 'none';
        formEl.classList.toggle('active', step === 'form');
        successEl.classList.toggle('active', step === 'success');
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function submitSignup() {
        var name = document.getElementById('fyndable-name').value.trim();
        var email = document.getElementById('fyndable-email').value.trim();
        var siteUrl = document.getElementById('fyndable-siteurl').value.trim();
        var errorEl = document.getElementById('fyndable-signup-error');
        var submitBtn = document.getElementById('fyndable-signup-submit');

        errorEl.innerHTML = '';

        if (!name || !email || !siteUrl) {
            errorEl.innerHTML = '<div class="fyndable-signup-error">Please fill in all fields.</div>';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating account...';

        fetch(restUrl + '/signup/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify({
                name: name,
                email: email,
                site_url: siteUrl,
                tier: selectedTier,
                interval: selectedInterval,
            }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    errorEl.innerHTML = '<div class="fyndable-signup-error">' + (data.message || 'Signup failed.') + '</div>';
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create Account →';
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
                errorEl.innerHTML = '<div class="fyndable-signup-error">Network error. Please try again.</div>';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Account →';
            });
    }

    function showSuccess(licenseKey) {
        var successEl = document.getElementById('fyndable-signup-success-step');
        successEl.innerHTML = '<div class="fyndable-signup-success">' +
            '<div class="icon">🎉</div>' +
            '<h2>Welcome to Fyndable!</h2>' +
            '<p class="instructions">Your account is ready. Copy your license key below and paste it into the Fyndable plugin on your WordPress site.</p>' +
            '<div class="license-key">' + licenseKey + '</div>' +
            '<p class="instructions"><strong>Next steps:</strong><br>1. Install the Fyndable plugin on your WordPress site<br>2. Go to Settings → Fyndable<br>3. Paste your license key and click Activate</p>' +
            '<button class="fyndable-signup-submit" onclick="navigator.clipboard.writeText(\'' + licenseKey + '\');this.textContent=\'✓ Copied!\';">Copy License Key</button>' +
            '</div>';
        showStep('success');
    }

    init();
})();
