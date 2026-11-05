/*
 * AI shopping (2.1): AI-ready score per product, AI shopping attributes
 * (preview + edit + save) and the Google Merchant Center / OpenAI feeds.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    let checks = {};
    let current = null;
    window.arPage = 1;

    const lines = id => Fyn.val(id).split(/\r?\n/).map(s => s.trim()).filter(Boolean);
    const issueLabel = i => {
        const code = typeof i === 'string' ? i : i.code;
        const detail = typeof i === 'object' && typeof i.detail === 'number' && code !== 'short_description' ? ` (${i.detail})` : '';
        return t('issue_' + code) + detail;
    };

    function bulkAllowed() {
        return !!(Fyn.ctx && Fyn.ctx.plan && Fyn.ctx.plan.features && Fyn.ctx.plan.features.bulk_optimizer);
    }

    function renderFeed(data) {
        const feed = data.feed;
        Fyn.setChecked('ar-enabled', feed.enabled);
        Fyn.show('ar-urls', !!feed.urls);
        if (feed.urls) {
            Fyn.setVal('ar-url-google', feed.urls.google);
            Fyn.setVal('ar-url-openai', feed.urls.openai);
        }
        let status;
        if (feed.status === 'building') status = Fyn.banner('info', t('feed_building'));
        else if (feed.status === 'failed') status = Fyn.banner('critical', t('feed_failed', { error: feed.error || '' }));
        else if (feed.built_at) status = `<s-text color="subdued">${esc(t('feed_ready', { products: feed.products, items: feed.items, date: Fyn.fmtDate(feed.built_at, true) }))}</s-text>`;
        else status = `<s-text color="subdued">${esc(t('feed_never'))}</s-text>`;
        Fyn.html('ar-feed-status', status);
    }

    function renderScore(data) {
        checks = data.checks;
        if (data.average_score === null || data.average_score === undefined) {
            Fyn.html('ar-stats', `<s-text color="subdued">${esc(t('no_data'))}</s-text>`);
            Fyn.html('ar-issues', '');
            return;
        }
        const score = data.average_score;
        Fyn.html('ar-stats', Fyn.stats([
            { label: t('score_sub', { products: data.feed.products }), value: `${score}/100`, tone: score >= 80 ? 'success' : score >= 50 ? 'warning' : 'critical' },
        ]));

        const codes = Object.keys(data.summary);
        Fyn.html('ar-issues', codes.length ? Fyn.table(
            [t('col_issues'), { label: t('col_count'), format: 'numeric' }, t('col_action')],
            codes.map(code => {
                let action = `<s-button variant="tertiary" onclick="filterProducts('${esc(code)}')">${esc(t('show_products'))}</s-button>`;
                if (code === 'missing_attributes' && bulkAllowed()) {
                    action += ` <s-button variant="tertiary" href="${esc(Fyn.url('bulk', 'type=product&action=ai_attributes'))}">${esc(t('fix_bulk'))}</s-button>`;
                }
                if (code === 'missing_meta' && bulkAllowed()) {
                    action += ` <s-button variant="tertiary" href="${esc(Fyn.url('bulk', 'type=product&action=meta'))}">${esc(t('fix_bulk'))}</s-button>`;
                }
                return [esc(issueLabel(code)), esc(data.summary[code]), `<s-stack direction="inline" gap="small-200">${action}</s-stack>`];
            })) : '');

        const filter = Fyn.$('ar-filter');
        const selected = Fyn.val('ar-filter');
        filter.innerHTML = `<s-option value="">${esc(t('all_issues'))}</s-option>` + Object.keys(checks).map(c => `<s-option value="${c}">${esc(issueLabel(c))}</s-option>`).join('');
        filter.value = selected;
    }

    async function load() {
        const data = await Fyn.api('/ai-ready');
        if (data.error) {
            Fyn.html('ar-stats', Fyn.errorBanner(data));
            return null;
        }
        renderFeed(data);
        renderScore(data);
        if (data.average_score !== null && data.average_score !== undefined) await loadProducts(window.arPage);
        return data;
    }

    async function loadProducts(page) {
        if (page < 1) return;
        const params = new URLSearchParams({ page: String(page) });
        if (Fyn.val('ar-filter')) params.set('issue', Fyn.val('ar-filter'));
        const data = await Fyn.api('/ai-ready/products?' + params.toString());
        if (data.error) {
            Fyn.html('ar-products', Fyn.errorBanner(data));
            return;
        }
        window.arPage = data.page;
        Fyn.html('ar-products', Fyn.table(
            [t('col_product'), t('col_score'), t('col_issues'), t('col_action')],
            data.products.map(p => {
                const issues = p.issues.slice(0, 3).map(i => esc(issueLabel(i))).join(', ') + (p.issues.length > 3 ? ' ' + esc(t('more', { count: p.issues.length - 3 })) : '');
                const needsAttributes = p.issues.some(i => i.code === 'missing_attributes');
                return [
                    esc(p.title),
                    Fyn.scoreBadge(p.score),
                    issues || '-',
                    `<s-stack direction="inline" gap="small-200">
                        ${needsAttributes ? `<s-button variant="tertiary" onclick="openAttributes('${esc(p.product_id)}', this)">${esc(t('fix_attributes'))}</s-button>` : ''}
                        <s-link href="${esc(Fyn.url('content', 'type=product&id=' + encodeURIComponent(p.product_id)))}">${esc(t('edit'))}</s-link>
                    </s-stack>`,
                ];
            })));
        Fyn.show('ar-pager', data.pages > 1);
        Fyn.$('ar-prev').disabled = data.page <= 1;
        Fyn.$('ar-next').disabled = data.page >= data.pages;
        Fyn.$('ar-page').textContent = `${data.page} / ${data.pages}`;
    }

    window.loadProducts = loadProducts;

    window.filterProducts = function (code) {
        Fyn.setVal('ar-filter', code);
        loadProducts(1);
    };

    function fillModal(a) {
        Fyn.setVal('ar-material', a.material || '');
        Fyn.setVal('ar-color', a.color || '');
        Fyn.setVal('ar-features', (a.features || []).join('\n'));
        Fyn.setVal('ar-use-cases', (a.use_cases || []).join('\n'));
        Fyn.setVal('ar-audience', a.target_audience || '');
        Fyn.setVal('ar-care', a.care_instructions || '');
        Fyn.setVal('ar-gender', a.gender || '');
        Fyn.setVal('ar-age', a.age_group || '');
    }

    window.openAttributes = async function (productId) {
        current = productId;
        fillModal({});
        Fyn.html('ar-modal-status', `<s-stack direction="inline" gap="small-200" alignItems="center"><s-spinner></s-spinner><s-text>${esc(t('generating'))}</s-text></s-stack>`);
        Fyn.$('ar-save').disabled = true;
        Fyn.$('ar-modal').showOverlay();
        const data = await Fyn.post(`/ai-ready/${encodeURIComponent(productId)}/attributes`);
        if (data.error) {
            Fyn.html('ar-modal-status', Fyn.errorBanner(data));
            return;
        }
        Fyn.html('ar-modal-status', '');
        fillModal(data.attributes);
        Fyn.$('ar-save').disabled = false;
    };

    window.saveAttributes = async function (button) {
        if (!current) return;
        const attributes = {
            material: Fyn.val('ar-material').trim(),
            color: Fyn.val('ar-color').trim(),
            features: lines('ar-features'),
            use_cases: lines('ar-use-cases'),
            target_audience: Fyn.val('ar-audience').trim(),
            care_instructions: Fyn.val('ar-care').trim(),
            gender: Fyn.val('ar-gender'),
            age_group: Fyn.val('ar-age'),
        };
        const data = await Fyn.busy(button, () => Fyn.post(`/ai-ready/${encodeURIComponent(current)}/attributes/save`, { attributes }));
        if (data.error) {
            Fyn.html('ar-modal-status', Fyn.errorBanner(data));
            return;
        }
        Fyn.$('ar-modal').hideOverlay();
        Fyn.toast(t('attributes_saved'));
    };

    window.toggleFeed = async function (el) {
        const data = await Fyn.post('/ai-ready/feed', { enabled: !!el.checked });
        if (data.error) {
            Fyn.toast(Fyn.errorText(data), true);
            return;
        }
        Fyn.toast(t(data.feed.enabled ? 'feed_on' : 'feed_off'));
        renderFeed(data);
    };

    window.rebuildFeed = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/ai-ready/rebuild'));
        Fyn.toast(data.error === 'too_soon' ? t('too_soon') : data.error ? Fyn.errorText(data) : t('rebuild_started'), !!data.error);
        if (!data.error) setTimeout(load, 8000);
    };

    window.rotateFeedToken = async function (button) {
        if (!(await Fyn.confirm(t('rotate_confirm')))) return;
        const data = await Fyn.busy(button, () => Fyn.post('/ai-ready/rotate-token'));
        if (data.error) {
            Fyn.toast(Fyn.errorText(data), true);
            return;
        }
        Fyn.toast(t('rotated'));
        renderFeed(data);
    };

    window.copyFeedUrl = async function (el) {
        try {
            await navigator.clipboard.writeText(el.value);
            Fyn.toast(t('copied'));
        } catch (e) {
            // Clipboard can be blocked in the admin iframe; the field stays selectable.
        }
    };

    Fyn.page(load);
})();
