/*
 * Fyndable embedded app - shared client code for every page.
 *
 * Loaded by resources/views/layouts/app.blade.php after App Bridge and the
 * Polaris web components. Page scripts (public/js/pages/{page}.js) register
 * themselves with Fyn.page(async (ctx) => { ... }); the callback runs once
 * the page context (billing, plan, onboarding) is loaded and the page is not
 * locked by the merchant's plan.
 *
 * App Bridge intercepts window.fetch and adds the session token
 * (Authorization: Bearer) to requests to the app's own domain.
 */
(function () {
    'use strict';

    const cfg = window.FYN || {};
    const I18N = cfg.i18n || {};
    let pageInit = null;
    let pageRan = false;

    /** Translate a key from lang/{locale}/app.php or the page file ('js' group). */
    function t(key, vars = {}) {
        const text = I18N[key] ?? key;
        return String(text).replace(/\{(\w+)\}/g, (_, k) => (vars[k] ?? ''));
    }

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    const $ = id => document.getElementById(id);

    const Fyn = {
        cfg,
        ctx: null,
        t,
        esc,
        $,

        // ------------------------------------------------------------------
        // Navigation
        // ------------------------------------------------------------------
        url(page, extra = '') {
            const base = page === 'home' ? '/dashboard' : `/dashboard/${page}`;
            const q = [cfg.navQuery, extra].filter(Boolean).join('&');
            return q ? `${base}?${q}` : base;
        },

        go(page) {
            window.location.href = Fyn.url(page);
        },

        openPricing(url) {
            if (url) window.open(url, '_top');
        },

        // ------------------------------------------------------------------
        // API
        // ------------------------------------------------------------------
        async api(path, options = {}) {
            const url = `/api${path}${path.includes('?') ? '&' : '?'}shop=${encodeURIComponent(cfg.shop)}`;
            try {
                const response = await fetch(url, {
                    ...options,
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(options.headers || {}) },
                });
                // In-app trial used up or expired: tell the merchant and send
                // them to the Shopify plan page.
                if (response.status === 402) {
                    const body = await response.json().catch(() => ({}));
                    if (body.error === 'trial_exhausted' || body.error === 'trial_expired') {
                        Fyn.toast(t(body.error, { max: body.trial_limit, days: body.trial_days }), true);
                        Fyn.openPricing(body.pricing_url);
                    }
                    return body;
                }
                const type = response.headers.get('content-type') || '';
                if (!type.includes('application/json')) {
                    return { error: 'server_error', message: `HTTP ${response.status}` };
                }
                return await response.json();
            } catch (e) {
                return { error: 'request_failed', message: e.message };
            }
        },

        post(path, body = {}) {
            return Fyn.api(path, { method: 'POST', body: JSON.stringify(body) });
        },

        // ------------------------------------------------------------------
        // Feedback (App Bridge toast + Polaris modal instead of alert/confirm)
        // ------------------------------------------------------------------
        toast(message, isError = false) {
            if (window.shopify && window.shopify.toast) {
                window.shopify.toast.show(String(message), { isError, duration: isError ? 6000 : 4000 });
            } else {
                console[isError ? 'error' : 'log'](message);
            }
        },

        /** Human readable message for an API error payload. */
        errorText(data) {
            if (!data) return t('unknown_error');
            if (data.error === 'tier_too_low') return t('tier_too_low');
            if (data.error === 'limit_reached') return t('limit_reached');
            if (data.error === 'missing_scopes') return t('missing_scopes', { scopes: (data.missing_scopes || []).join(', ') });
            return data.message || data.error || t('unknown_error');
        },

        /** Critical banner for an API error, with an upgrade or re-auth action. */
        errorBanner(data) {
            let action = '';
            if (data && (data.error === 'tier_too_low' || data.error === 'limit_reached' || data.error === 'license_inactive')) {
                action = ` <s-link href="${esc(Fyn.url('plan'))}">${esc(t('lock_button'))}</s-link>`;
            } else if (data && data.error === 'missing_scopes' && data.reauth_url) {
                action = ` <s-link href="${esc(data.reauth_url)}" target="_top">${esc(t('reauth'))}</s-link>`;
            }
            return `<s-banner tone="critical">${esc(Fyn.errorText(data))}${action}</s-banner>`;
        },

        banner(tone, text, heading = '') {
            return `<s-banner tone="${esc(tone)}"${heading ? ` heading="${esc(heading)}"` : ''}>${esc(text)}</s-banner>`;
        },

        /**
         * Promise-based confirm dialog. Call it directly from a click handler
         * (Polaris modals only open on user interaction).
         */
        confirm(text) {
            const modal = $('fyn-confirm');
            $('fyn-confirm-text').textContent = text;
            return new Promise(resolve => {
                const ok = $('fyn-confirm-ok');
                let done = false;
                const finish = value => {
                    if (done) return;
                    done = true;
                    ok.removeEventListener('click', onOk);
                    modal.removeEventListener('hide', onHide);
                    resolve(value);
                };
                const onOk = () => { finish(true); modal.hideOverlay(); };
                const onHide = () => finish(false);
                ok.addEventListener('click', onOk);
                modal.addEventListener('hide', onHide);
                modal.showOverlay();
            });
        },

        /** Run fn with a loading state on the clicked s-button. */
        async busy(button, fn) {
            if (button) button.loading = true;
            try {
                return await fn();
            } finally {
                if (button) button.loading = false;
            }
        },

        // ------------------------------------------------------------------
        // Form helpers (Polaris fields expose value/checked as properties)
        // ------------------------------------------------------------------
        val(id) {
            const el = $(id);
            return el ? String(el.value ?? '') : '';
        },
        setVal(id, value) {
            const el = $(id);
            if (el) el.value = value ?? '';
        },
        checked(id) {
            const el = $(id);
            return !!(el && el.checked);
        },
        setChecked(id, value) {
            const el = $(id);
            if (el) el.checked = !!value;
        },
        show(id, visible = true) {
            const el = typeof id === 'string' ? $(id) : id;
            if (el) el.hidden = !visible;
        },
        html(id, markup) {
            const el = $(id);
            if (el) el.innerHTML = markup;
        },

        // ------------------------------------------------------------------
        // Markup helpers (Polaris web components)
        // ------------------------------------------------------------------
        loading() {
            return '<s-stack direction="inline" gap="small-200" alignItems="center"><s-spinner size="base"></s-spinner></s-stack>';
        },

        badge(tone, text) {
            return `<s-badge tone="${esc(tone)}">${esc(text)}</s-badge>`;
        },

        /** SEO score badge (1.1): green from 80, orange from 50, red below. */
        scoreBadge(score) {
            if (score === null || score === undefined) return '';
            return Fyn.badge(score >= 80 ? 'success' : score >= 50 ? 'warning' : 'critical', `${score}/100`);
        },

        /** Label for a SeoAuditor issue code (lang/{locale}/app.php issue_*). */
        issueLabel(issue) {
            const code = typeof issue === 'string' ? issue : issue.code;
            let label = t('issue_' + code);
            if (issue && typeof issue === 'object' && typeof issue.detail === 'number') label += ` (${issue.detail})`;
            return label;
        },

        /** Raw JSON output (insights endpoints without a structured view yet). */
        json(data) {
            return `<pre class="fyn-code">${esc(JSON.stringify(data, null, 2))}</pre>`;
        },

        /** Grid of metric boxes: [{label, value, sub, tone}] */
        stats(items) {
            const cols = Math.min(items.length, 5);
            const boxes = items.map(i => `<s-box padding="base" background="base" borderRadius="base" border="base">
                    <s-stack gap="small-200">
                        <s-text color="subdued">${esc(i.label)}</s-text>
                        <s-heading><s-text${i.tone ? ` tone="${esc(i.tone)}"` : ''}>${esc(i.value)}</s-text></s-heading>
                        ${i.sub ? `<s-text color="subdued">${esc(i.sub)}</s-text>` : ''}
                    </s-stack></s-box>`).join('');
            return `<s-query-container><s-grid gridTemplateColumns="@container (inline-size > 640px) repeat(${cols}, 1fr), 1fr 1fr" gap="base">${boxes}</s-grid></s-query-container>`;
        },

        /** Status line: title + subdued text + optional badge/action (HTML). */
        row(title, sub, endHtml = '') {
            return `<s-stack direction="inline" gap="base" justifyContent="space-between" alignItems="center" paddingBlock="small-200">
                    <s-stack gap="small-500"><s-text type="strong">${esc(title)}</s-text>${sub !== '' && sub != null ? `<s-text color="subdued">${esc(sub)}</s-text>` : ''}</s-stack>
                    ${endHtml}</s-stack>`;
        },

        /**
         * s-table markup. headers: [label | {label, format}], rows: arrays of
         * cell HTML (escape text yourself with Fyn.esc).
         */
        table(headers, rows) {
            const head = headers.map((h, i) => {
                const label = typeof h === 'string' ? h : h.label;
                const format = typeof h === 'string' ? '' : (h.format || '');
                return `<s-table-header${i === 0 ? ' listSlot="primary"' : ''}${format ? ` format="${esc(format)}"` : ''}>${esc(label)}</s-table-header>`;
            }).join('');
            const body = rows.map(r => `<s-table-row>${r.map(c => `<s-table-cell>${c}</s-table-cell>`).join('')}</s-table-row>`).join('');
            return `<s-table><s-table-header-row>${head}</s-table-header-row><s-table-body>${body}</s-table-body></s-table>`;
        },

        fmtDate(iso, withTime = false) {
            if (!iso) return '';
            const d = new Date(iso);
            const locale = cfg.lang === 'nl' ? 'nl-NL' : 'en-US';
            return withTime ? d.toLocaleString(locale) : d.toLocaleDateString(locale);
        },

        tierLabel(tier) {
            return tier ? tier.charAt(0).toUpperCase() + tier.slice(1).replace('_', ' ') : '';
        },

        // ------------------------------------------------------------------
        // Billing banner (every page) and plan gating
        // ------------------------------------------------------------------
        renderBillingBanner(b) {
            const box = $('fyn-banners');
            if (!box || !b || !b.enabled) return;
            const action = `<s-button slot="secondary-actions" onclick="Fyn.openPricing(Fyn.ctx.billing.pricing_url)">${esc(t('choose_plan'))}</s-button>`;
            if (cfg.page === 'plan') {
                // The plan page shows the full plan card instead.
                box.innerHTML = '';
            } else if (b.state === 'trial') {
                const full = b.trial.used >= b.trial.max;
                const text = t(full ? 'trial_full' : 'trial_text', { used: b.trial.used, max: b.trial.max, date: Fyn.fmtDate(b.trial.ends_at) });
                box.innerHTML = `<s-banner tone="${full ? 'warning' : 'info'}" heading="${esc(t('trial_title'))}">${esc(text)}${action}</s-banner>`;
            } else if (b.state === 'none' || b.required) {
                box.innerHTML = `<s-banner tone="critical" heading="${esc(t('required_title'))}">${esc(t('required_text'))}${action}</s-banner>`;
            } else {
                box.innerHTML = '';
            }

            // No plan and no trial left: send the merchant to the Shopify
            // plan page once per session (App Store requirement for paid apps).
            if (b.required && !sessionStorage.getItem('fyn-pricing-redirected')) {
                sessionStorage.setItem('fyn-pricing-redirected', '1');
                Fyn.openPricing(b.pricing_url);
            }
        },

        /** True when the current page's feature is not in the merchant's plan. */
        isLocked(plan) {
            return !!(cfg.feature && plan && plan.features && plan.features[cfg.feature] === false);
        },

        applyLock(plan) {
            const lock = $('fyn-lock');
            const pageEl = document.querySelector('s-page');
            if (!lock || !pageEl) return false;
            const locked = Fyn.isLocked(plan);
            [...pageEl.children].forEach(el => {
                if (el.id === 'fyn-banners' || el.id === 'fyn-lock') return;
                if (locked) el.hidden = true;
            });
            if (!locked) {
                lock.hidden = true;
                return false;
            }
            const required = plan.required ? plan.required[cfg.feature] : '';
            const why = plan.tier === 'free' ? t('lock_free') : t('lock_upgrade', { tier: Fyn.tierLabel(plan.tier) });
            lock.innerHTML = `<s-stack gap="base">
                    <s-heading>${esc(t('lock_title', { tier: Fyn.tierLabel(required) }))}</s-heading>
                    <s-paragraph>${esc(why)}</s-paragraph>
                    <s-stack direction="inline"><s-button variant="primary" href="${esc(Fyn.url('plan'))}">${esc(t('lock_button'))}</s-button></s-stack>
                </s-stack>`;
            lock.hidden = false;
            return true;
        },

        /**
         * Apply a context payload (from /dashboard/context or the overview)
         * and run the page script unless the page is locked.
         */
        async applyContext(ctx) {
            Fyn.ctx = ctx;
            Fyn.renderBillingBanner(ctx.billing);
            if (Fyn.applyLock(ctx.plan)) return;
            if (pageInit && !pageRan) {
                pageRan = true;
                await pageInit(ctx);
            }
        },

        /** Register the page script. */
        page(fn) {
            pageInit = fn;
        },

        /** Pages that load their own context (home: overview) set this. */
        customContext: false,
    };

    window.Fyn = Fyn;
    window.t = t;

    async function boot() {
        // Polaris elements must be defined before pages read .value/.checked.
        await Promise.race([
            Promise.all(['s-page', 's-text-field', 's-button'].map(tag => customElements.whenDefined(tag))),
            new Promise(resolve => setTimeout(resolve, 3000)),
        ]);
        if (Fyn.customContext) {
            if (pageInit && !pageRan) {
                pageRan = true;
                await pageInit(null);
            }
            return;
        }
        const ctx = await Fyn.api('/dashboard/context');
        if (ctx.error) {
            Fyn.html('fyn-banners', Fyn.errorBanner(ctx));
            return;
        }
        await Fyn.applyContext(ctx);
    }

    // Deferred scripts run in order: this file, then the page script; both
    // before DOMContentLoaded.
    document.addEventListener('DOMContentLoaded', boot);
})();
