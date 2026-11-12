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
        // Unknown names stay as written, so {{title}} in template help survives.
        return String(text).replace(/\{(\w+)\}/g, (match, k) => (k in vars ? (vars[k] ?? '') : match));
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

        /**
         * Status of one of our theme app extension blocks/embeds via App
         * Bridge (Built for Shopify 4.2.3): 'active', 'available',
         * 'unavailable', or null when App Bridge cannot tell.
         */
        async embedStatus(blockHandle) {
            try {
                if (!window.shopify || !window.shopify.app || !window.shopify.app.extensions) return null;
                const extensions = await window.shopify.app.extensions();
                let found = null;
                const walk = list => (list || []).forEach(item => {
                    if (item && item.handle === blockHandle && item.status) {
                        if (found !== 'active') found = item.status;
                    }
                    if (item && item.activations) walk(item.activations);
                });
                extensions.filter(e => e.type === 'theme_app_extension').forEach(e => walk(e.activations));
                return found;
            } catch (e) {
                return null;
            }
        },

        /** Theme editor deep link that turns on one of our app embeds. */
        embedEditorUrl(blockHandle) {
            return `shopify://admin/themes/current/editor?context=apps&activateAppId=${encodeURIComponent(cfg.apiKey || '')}/${encodeURIComponent(blockHandle)}`;
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

        /** Formatted number ('-' when missing). */
        num(value) {
            if (value === null || value === undefined || value === '' || isNaN(Number(value))) return '-';
            return Number(value).toLocaleString(cfg.lang === 'nl' ? 'nl-NL' : 'en-US');
        },

        /** Signed number for changes: +12 / -3 / 0. */
        signed(value) {
            const n = Number(value) || 0;
            return (n > 0 ? '+' : '') + Fyn.num(n);
        },

        /** External link (http(s) only; anything else is shown as text). */
        link(url, label) {
            const text = esc(label || url || '');
            return /^https?:\/\//i.test(String(url || '')) ? `<s-link href="${esc(url)}" target="_blank">${text}</s-link>` : text;
        },

        /** Unicode sparkline for a short series of numbers. */
        spark(values) {
            const nums = (values || []).map(v => Number(v) || 0);
            if (nums.length < 2) return '';
            const bars = '▁▂▃▄▅▆▇█';
            const max = Math.max(...nums);
            const min = Math.min(...nums);
            return nums.map(v => bars[max === min ? 3 : Math.round(((v - min) / (max - min)) * 7)]).join('');
        },

        /**
         * Light Markdown (AI answers) to safe markup: paragraphs, headings,
         * bullet and numbered lists, **bold**, *italic*, `code` and
         * [links](https://...). Everything is escaped first.
         */
        markdown(text) {
            const inline = s => esc(s)
                .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<s-link href="$2" target="_blank">$1</s-link>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/(^|[\s(])\*([^*\s][^*]*)\*/g, '$1<em>$2</em>')
                .replace(/`([^`]+)`/g, '<code>$1</code>');
            const out = [];
            let para = [];
            let list = null;
            const flushPara = () => {
                if (para.length) out.push(`<s-paragraph>${para.map(inline).join('<br>')}</s-paragraph>`);
                para = [];
            };
            const flushList = () => {
                if (list) out.push(`<s-${list.tag}>${list.items.map(i => `<s-list-item>${inline(i)}</s-list-item>`).join('')}</s-${list.tag}>`);
                list = null;
            };
            String(text || '').replace(/\r/g, '').split('\n').forEach(raw => {
                const line = raw.trim();
                const bullet = line.match(/^[-*•]\s+(.*)$/);
                const numbered = line.match(/^\d+[.)]\s+(.*)$/);
                const heading = line.match(/^#{1,6}\s+(.*)$/);
                if (!line) {
                    flushPara();
                    flushList();
                } else if (bullet || numbered) {
                    flushPara();
                    const tag = bullet ? 'unordered-list' : 'ordered-list';
                    if (list && list.tag !== tag) flushList();
                    if (!list) list = { tag, items: [] };
                    list.items.push((bullet || numbered)[1]);
                } else if (heading) {
                    flushPara();
                    flushList();
                    out.push(`<s-text type="strong">${inline(heading[1].replace(/\*\*/g, ''))}</s-text>`);
                } else {
                    flushList();
                    para.push(line);
                }
            });
            flushPara();
            flushList();
            return `<s-stack gap="small-200">${out.join('')}</s-stack>`;
        },

        /** List of source links ({url, title}). */
        sources(list, heading) {
            if (!list || !list.length) return '';
            return `<s-stack gap="small-200"><s-text type="strong">${esc(heading || t('ai_sources'))}</s-text>
                <s-unordered-list>${list.map(s => `<s-list-item>${Fyn.link(s.url, s.title || s.url)}</s-list-item>`).join('')}</s-unordered-list></s-stack>`;
        },

        /**
         * One AI answer (insights llmAnswer shape): heading, brand-mentioned
         * badge, the answer text, sources and the model used.
         */
        answerCard(heading, r) {
            if (r && r.error) {
                return `<s-box padding="base" border="base" borderRadius="base"><s-stack gap="small-200"><s-text type="strong">${esc(heading)}</s-text>${Fyn.banner('critical', r.message || r.error)}</s-stack></s-box>`;
            }
            r = r || {};
            const badge = r.brand_mentioned === true ? Fyn.badge('success', t('ai_mentioned'))
                : r.brand_mentioned === false ? Fyn.badge('warning', t('ai_not_mentioned')) : '';
            const meta = [r.model ? t('ai_model', { model: r.model }) : '', r.web_search ? t('ai_web_search') : ''].filter(Boolean).join(' · ');
            return `<s-box padding="base" border="base" borderRadius="base"><s-stack gap="base">
                    <s-stack direction="inline" gap="base" justifyContent="space-between" alignItems="center"><s-heading>${esc(heading)}</s-heading>${badge}</s-stack>
                    ${r.answer ? Fyn.markdown(r.answer) : `<s-paragraph>${esc(t('ai_no_answer'))}</s-paragraph>`}
                    ${Fyn.sources(r.sources)}
                    ${meta ? `<s-text color="subdued">${esc(meta)}</s-text>` : ''}
                </s-stack></s-box>`;
        },

        /** Keyword volume table (insights keywordVolumes shape). */
        keywordVolumeTable(rows) {
            if (!rows || !rows.length) return `<s-paragraph>${esc(t('no_results'))}</s-paragraph>`;
            return Fyn.table(
                [t('col_keyword'), { label: t('col_ai_volume'), format: 'numeric' }, t('col_trend')],
                rows.map(r => [
                    esc(r.keyword),
                    esc(Fyn.num(r.ai_search_volume)),
                    `<span title="${esc((r.monthly || []).map(m => `${m.month}: ${m.volume}`).join(', '))}">${esc(Fyn.spark((r.monthly || []).map(m => m.volume)) || '-')}</span>`,
                ])
            );
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
            // No repeat(n, 1fr): Polaris splits responsive values on commas,
            // so a comma inside repeat() breaks the whole value (one column).
            const wide = Array(cols).fill('1fr').join(' ');
            return `<s-query-container><s-grid gridTemplateColumns="@container (inline-size > 640px) ${wide}, 1fr 1fr" gap="base">${boxes}</s-grid></s-query-container>`;
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
        /** Table cell content that wraps instead of widening the table (.fyn-wrap). */
        wrap(html) {
            return `<span class="fyn-wrap">${html}</span>`;
        },

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
            // sessionStorage only dedupes the redirect; it can throw when the
            // browser blocks storage in the admin iframe, so never rely on it.
            if (b.required) {
                let redirected = false;
                try {
                    redirected = !!sessionStorage.getItem('fyn-pricing-redirected');
                    sessionStorage.setItem('fyn-pricing-redirected', '1');
                } catch (e) { /* storage blocked: redirect anyway */ }
                if (!redirected) Fyn.openPricing(b.pricing_url);
            }
        },

        /** True when the current page's feature is not in the merchant's plan. */
        isLocked(plan) {
            return !!(cfg.feature && plan && plan.features && plan.features[cfg.feature] === false);
        },

        /** Mark sub navigation entries whose feature is outside the plan (BFS 4.3.7). */
        labelSubnav(plan) {
            if (!plan || !plan.features) return;
            document.querySelectorAll('#fyn-subnav [data-feature]').forEach(button => {
                const feature = button.dataset.feature;
                if (!feature || plan.features[feature] !== false || button.dataset.locked) return;
                button.dataset.locked = '1';
                button.textContent = `${button.textContent.trim()} (${Fyn.tierLabel(plan.required ? plan.required[feature] : '')})`;
            });
        },

        applyLock(plan) {
            const lock = $('fyn-lock');
            const pageEl = document.querySelector('s-page');
            if (!lock || !pageEl) return false;
            const locked = Fyn.isLocked(plan);
            [...pageEl.children].forEach(el => {
                if (el.id === 'fyn-banners' || el.id === 'fyn-lock' || el.id === 'fyn-subnav') return;
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
            Fyn.labelSubnav(ctx.plan);
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
