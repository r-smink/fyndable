/*
 * SEO audit (1.1): store score, issue groups with fix links (bulk optimizer
 * for products when the plan includes it, otherwise the content optimizer)
 * and the worst items first.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    const TYPES = ['product', 'collection', 'page', 'article'];
    let meta = { issues: {}, bulk_fixes: {} };
    let pollTimer = null;
    window.auditPage = 1;

    function severityBadge(severity) {
        const tone = { critical: 'critical', warning: 'warning', info: 'info' }[severity] || 'neutral';
        return Fyn.badge(tone, t('severity_' + severity));
    }

    function contentUrl(item) {
        return Fyn.url('content', `type=${encodeURIComponent(item.type)}&id=${encodeURIComponent(item.resource_id.split('/').pop())}`);
    }

    function renderStatus(current, result) {
        let html = '';
        if (current && current.status === 'queued') {
            html = `<s-banner tone="info">${esc(t('queued'))}</s-banner><s-progress accessibilityLabel="${esc(t('queued'))}"></s-progress>`;
        } else if (current && current.status === 'running') {
            const label = t('running', { progress: current.progress });
            html = `<s-stack gap="small-200"><s-text>${esc(label)}</s-text><s-progress value="${Number(current.progress) || 0}" max="100" accessibilityLabel="${esc(label)}"></s-progress></s-stack>`;
        } else if (current && current.status === 'failed') {
            html = `<s-banner tone="critical">${esc(t('failed', { error: current.error || t('unknown_error') }))}</s-banner>`;
        }
        if (result) {
            html += `<s-text color="subdued">${esc(t('last_run', { date: Fyn.fmtDate(result.finished_at, true) }))}</s-text>`;
        } else if (!html) {
            html = `<s-text color="subdued">${esc(t('never_run'))}</s-text>`;
        }
        Fyn.html('audit-status', html);
        Fyn.$('audit-run').loading = !!current && ['queued', 'running'].includes(current.status);
    }

    function renderResult(result) {
        const has = !!result;
        ['audit-stats-section', 'audit-issues-section', 'audit-items-section'].forEach(id => Fyn.show(id, has));
        if (!has) return;

        Fyn.html('audit-stats', Fyn.stats([
            { label: t('seo_score'), value: result.score === null ? '-' : `${result.score}/100`, sub: t('score_sub'), tone: result.score >= 80 ? 'success' : result.score >= 50 ? 'warning' : 'critical' },
            { label: t('items_checked'), value: result.items_count },
            { label: t('critical'), value: result.severity.critical, tone: result.severity.critical ? 'critical' : 'success' },
            { label: t('warnings'), value: result.severity.warning, tone: result.severity.warning ? 'warning' : 'success' },
        ]));

        const codes = Object.keys(result.summary);
        const bulkAllowed = !!(Fyn.ctx && Fyn.ctx.plan && Fyn.ctx.plan.features && Fyn.ctx.plan.features.bulk_optimizer);
        Fyn.html('audit-issues', codes.length
            ? Fyn.table(
                [t('col_issue'), t('col_severity'), { label: t('col_items'), format: 'numeric' }, t('col_action')],
                codes.map(code => {
                    // Link with the content type that has the most affected items (bulk defaults to products).
                    const target = (result.bulk || {})[code];
                    const bulkQuery = target
                        ? 'type=' + encodeURIComponent(target.type) + '&action=' + encodeURIComponent(target.action)
                        : '';
                    const actions = `<s-stack direction="inline" gap="small-200">
                            <s-button variant="tertiary" onclick="filterIssue('${esc(code)}')">${esc(t('show_items'))}</s-button>
                            ${bulkQuery && bulkAllowed ? `<s-button variant="tertiary" href="${esc(Fyn.url('bulk', bulkQuery))}">${esc(t('fix_bulk'))}</s-button>` : ''}
                        </s-stack>`;
                    return [esc(Fyn.issueLabel(code)), severityBadge(meta.issues[code] || 'info'), esc(result.summary[code]), actions];
                }))
            : `<s-banner tone="success">${esc(t('no_issues'))}</s-banner>`);

        const typeSelect = Fyn.$('audit-filter-type');
        if (!typeSelect.dataset.ready) {
            typeSelect.innerHTML = `<s-option value="">${esc(t('all_types'))}</s-option>`
                + TYPES.map(type => `<s-option value="${type}">${esc(t('type_' + type))}</s-option>`).join('');
            typeSelect.dataset.ready = '1';
        }
        const issueSelect = Fyn.$('audit-filter-issue');
        const selected = Fyn.val('audit-filter-issue');
        issueSelect.innerHTML = `<s-option value="">${esc(t('all_issues'))}</s-option>`
            + codes.map(code => `<s-option value="${esc(code)}">${esc(Fyn.issueLabel(code))}</s-option>`).join('');
        issueSelect.value = codes.includes(selected) ? selected : '';
        loadItems(1);
    }

    async function loadItems(page) {
        if (page < 1) return;
        const params = new URLSearchParams({ page: String(page) });
        if (Fyn.val('audit-filter-type')) params.set('type', Fyn.val('audit-filter-type'));
        if (Fyn.val('audit-filter-issue')) params.set('issue', Fyn.val('audit-filter-issue'));
        Fyn.html('audit-items', Fyn.loading());
        const data = await Fyn.api('/audit/items?' + params.toString());
        if (data.error) {
            Fyn.html('audit-items', Fyn.errorBanner(data));
            return;
        }
        window.auditPage = data.page;
        Fyn.html('audit-items', data.items.length
            ? Fyn.table(
                [t('col_title'), t('col_type'), t('col_score'), t('col_issues'), t('col_action')],
                data.items.map(item => {
                    const issues = item.issues.slice(0, 3).map(i => esc(Fyn.issueLabel(i))).join(', ')
                        + (item.issues.length > 3 ? ' ' + esc(t('more_issues', { count: item.issues.length - 3 })) : '');
                    return [
                        esc(item.title),
                        esc(t('type_' + item.type)),
                        Fyn.scoreBadge(item.score),
                        issues || esc(t('no_issues')),
                        `<s-link href="${esc(contentUrl(item))}">${esc(t('edit'))}</s-link>`,
                    ];
                }))
            : `<s-text color="subdued">${esc(t('no_items'))}</s-text>`);
        Fyn.show('audit-pager', data.pages > 1);
        Fyn.$('audit-prev').disabled = data.page <= 1;
        Fyn.$('audit-next').disabled = data.page >= data.pages;
        Fyn.$('audit-page-label').textContent = t('page', { page: data.page, pages: data.pages, total: data.total });
    }

    async function loadLatest() {
        const data = await Fyn.api('/audit/latest');
        if (data.error) {
            Fyn.html('audit-status', Fyn.errorBanner(data));
            return null;
        }
        meta = { issues: data.issues || {}, bulk_fixes: data.bulk_fixes || {} };
        renderStatus(data.current, data.result);
        return data;
    }

    function poll() {
        clearTimeout(pollTimer);
        pollTimer = setTimeout(async () => {
            const data = await loadLatest();
            if (!data) return;
            if (data.current && ['queued', 'running'].includes(data.current.status)) {
                poll();
            } else {
                if (data.current && data.current.status === 'completed') Fyn.toast(t('done'));
                renderResult(data.result);
            }
        }, 4000);
    }

    window.loadItems = loadItems;

    window.filterIssue = function (code) {
        Fyn.setVal('audit-filter-issue', code);
        loadItems(1);
        Fyn.$('audit-items-section').scrollIntoView({ behavior: 'smooth' });
    };

    window.startAudit = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/audit/start'));
        if (data.error) {
            Fyn.toast(Fyn.errorText(data), true);
            return;
        }
        Fyn.toast(t(data.already_running ? 'already_running' : 'started'));
        renderStatus(data.audit, null);
        poll();
    };

    Fyn.page(async () => {
        const data = await loadLatest();
        if (!data) return;
        renderResult(data.result);
        if (data.current && ['queued', 'running'].includes(data.current.status)) poll();
    });
})();
