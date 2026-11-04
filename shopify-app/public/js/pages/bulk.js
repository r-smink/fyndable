/*
 * Bulk optimizer (1.3): products, collections, pages and articles; AI or
 * template mode (no AI cost) for meta titles and descriptions; background
 * runs (BulkOptimizeJob) with live progress.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    const ACTIONS = {
        product: ['meta_title', 'meta', 'titles', 'alt_text', 'descriptions', 'ai_attributes'],
        collection: ['meta_title', 'meta', 'descriptions'],
        page: ['meta_title', 'meta'],
        article: ['meta_title', 'meta'],
    };
    const TEMPLATE_ACTIONS = ['meta_title', 'meta'];
    const DEFAULT_TEMPLATES = {
        meta_title: '{{title}} | {{shop}}',
        meta: '{{title}} - {{excerpt}}',
    };
    let pollTimer = null;

    function mode() {
        const list = Fyn.$('bulk-mode');
        const values = list && list.values ? list.values : [];
        return TEMPLATE_ACTIONS.includes(Fyn.val('bulk-action')) && values[0] === 'template' ? 'template' : 'ai';
    }

    function fillActions(selected) {
        const type = Fyn.val('bulk-type') || 'product';
        const actions = ACTIONS[type];
        const action = actions.includes(selected) ? selected : actions[0];
        Fyn.$('bulk-action').innerHTML = actions.map(a => `<s-option value="${a}"${a === action ? ' selected' : ''}>${esc(t('action_' + a))}</s-option>`).join('');
        Fyn.setVal('bulk-action', action);
        window.onBulkActionChange();
    }

    window.onBulkTypeChange = function () {
        fillActions(Fyn.val('bulk-action'));
    };

    window.onBulkActionChange = function () {
        const action = Fyn.val('bulk-action');
        const templatable = TEMPLATE_ACTIONS.includes(action);
        Fyn.show('bulk-mode-box', templatable);
        Fyn.$('bulk-only-needed').disabled = action === 'titles';
        if (templatable && !Fyn.val('bulk-template')) Fyn.setVal('bulk-template', DEFAULT_TEMPLATES[action]);
        if (templatable) {
            const current = Fyn.val('bulk-template');
            if (Object.values(DEFAULT_TEMPLATES).includes(current)) Fyn.setVal('bulk-template', DEFAULT_TEMPLATES[action]);
        }
        window.onBulkModeChange();
    };

    window.onBulkModeChange = function () {
        Fyn.show('bulk-template-box', mode() === 'template');
        Fyn.html('bulk-preview', '');
    };

    function body() {
        return {
            type: Fyn.val('bulk-type') || 'product',
            action: Fyn.val('bulk-action'),
            mode: mode(),
            template: mode() === 'template' ? Fyn.val('bulk-template') : '',
            only_needed: Fyn.checked('bulk-only-needed'),
            limit: parseInt(Fyn.val('bulk-limit'), 10) || 100,
        };
    }

    function errorMessage(data) {
        const known = ['no_items', 'job_running', 'invalid_template', 'fetch_failed'];
        return known.includes(data.error) ? Fyn.banner('critical', t(data.error)) : Fyn.errorBanner(data);
    }

    window.previewBulkTemplate = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/bulk/preview', body()));
        if (data.error) {
            Fyn.html('bulk-preview', errorMessage(data));
            return;
        }
        Fyn.html('bulk-preview', data.items.length
            ? Fyn.table([t('col_item'), t('col_current'), t('col_new')], data.items.map(i => [
                esc(i.title), esc(i.current || '-'), `${esc(i.new)} <s-text color="subdued">(${i.new.length})</s-text>`,
            ]))
            : `<s-text color="subdued">${esc(t('no_items'))}</s-text>`);
    };

    function render(data) {
        const active = ['queued', 'running'].includes(data.status);
        Fyn.show('bulk-cancel-box', active);
        Fyn.$('bulk-start').loading = active;
        if (data.status === 'idle') return;

        const counts = { processed: data.processed || 0, total: data.total || 0, succeeded: data.succeeded || 0, failed: data.failed || 0 };
        const what = data.action ? `${t('type_' + data.type)} - ${t('action_' + data.action)}${data.mode === 'template' ? ' (' + t('mode_template_short') + ')' : ''}` : '';
        let html = what ? `<s-text type="strong">${esc(what)}</s-text>` : '';
        if (data.total) {
            html += `<s-stack gap="small-200"><s-text>${esc(t('progress', counts))}</s-text>
                <s-progress value="${counts.processed}" max="${Math.max(counts.total, 1)}" accessibilityLabel="${esc(t('progress', counts))}"></s-progress></s-stack>`;
        }
        if (data.status === 'queued') html += Fyn.banner('info', t('queued'));
        else if (data.status === 'running') html += Fyn.banner('info', t('running', counts));
        else if (data.status === 'completed') html += Fyn.banner(counts.failed ? 'warning' : 'success', t('completed', counts));
        else if (data.status === 'cancelled') html += Fyn.banner('warning', t('cancelled'));
        else if (data.status === 'failed') html += data.error === 'tier_too_low' ? Fyn.errorBanner({ error: 'tier_too_low' }) : Fyn.banner('critical', t('failed', { error: data.error || '' }));

        if ((data.errors || []).length) {
            html += Fyn.table([t('col_item'), t('col_error')], data.errors.map(e => [esc(e.title), esc(e.error)]));
        }
        Fyn.html('bulk-result', `<s-stack gap="base">${html}</s-stack>`);
    }

    async function poll() {
        clearTimeout(pollTimer);
        const data = await Fyn.api('/bulk/progress');
        if (data.error && !data.status) return;
        render(data);
        if (['queued', 'running'].includes(data.status)) pollTimer = setTimeout(poll, 3000);
    }

    window.startBulkOptimize = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/bulk/optimize', body()));
        if (data.error) {
            Fyn.html('bulk-result', errorMessage(data));
            return;
        }
        Fyn.toast(t('started', { total: data.total }));
        poll();
    };

    window.cancelBulkOptimize = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/bulk/cancel'));
        if (data.error) {
            Fyn.toast(Fyn.errorText(data), true);
            return;
        }
        Fyn.toast(t('cancel_requested'));
        poll();
    };

    Fyn.page(async () => {
        // Deep link from the SEO audit "fix" buttons: ?type=product&action=meta
        const params = new URLSearchParams(location.search);
        const type = params.get('type');
        if (ACTIONS[type]) Fyn.setVal('bulk-type', type);
        fillActions(params.get('action') || 'meta');
        await poll();
    });
})();
