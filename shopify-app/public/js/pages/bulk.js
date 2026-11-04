/*
 * Bulk optimizer: start, follow and cancel background runs (BulkOptimizeJob).
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;

    window.startBulkOptimize = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/bulk/optimize', {
            action: Fyn.val('bulk-action'),
            limit: parseInt(Fyn.val('bulk-limit'), 10),
        }));
        if (data.success) {
            Fyn.html('bulk-result', Fyn.banner('success', t('started', { message: data.message || '', total: data.total })));
        } else {
            Fyn.html('bulk-result', Fyn.errorBanner(data));
        }
    };

    window.checkBulkProgress = async function (button) {
        const action = Fyn.val('bulk-action');
        const data = await Fyn.busy(button, () => Fyn.api(`/bulk/progress?action=${encodeURIComponent(action)}`));
        if (data.error && !data.status) {
            Fyn.html('bulk-result', Fyn.errorBanner(data));
            return;
        }
        const counts = { processed: data.processed, total: data.total, succeeded: data.succeeded, failed: data.failed };
        const bar = () => `<s-stack gap="small-200">
                <s-text>${esc(t('progress', counts))}</s-text>
                <s-progress value="${Number(data.processed) || 0}" max="${Math.max(Number(data.total) || 0, 1)}" accessibilityLabel="${esc(t('progress', counts))}"></s-progress>
            </s-stack>`;
        let html;
        if (data.status === 'running') {
            html = bar() + Fyn.banner('info', t('running', counts));
        } else if (data.status === 'completed') {
            html = bar() + Fyn.banner('success', t('completed', counts));
        } else if (data.status === 'cancelled') {
            html = Fyn.banner('warning', t('cancelled'));
        } else if (data.status === 'tier_too_low') {
            html = Fyn.errorBanner({ error: 'tier_too_low' });
        } else {
            html = Fyn.banner('info', data.message || t('no_job'));
        }
        Fyn.html('bulk-result', `<s-stack gap="base">${html}</s-stack>`);
    };

    window.cancelBulkOptimize = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/bulk/cancel', { action: Fyn.val('bulk-action') }));
        if (data.success) {
            Fyn.html('bulk-result', Fyn.banner('info', data.message || t('cancelled')));
        } else {
            Fyn.toast(Fyn.errorText(data), true);
        }
    };

    Fyn.page(async () => {
        // Deep link from the SEO audit "fix" buttons: ?action=meta
        const action = new URLSearchParams(location.search).get('action');
        if (['meta', 'titles', 'alt_text', 'descriptions'].includes(action)) Fyn.setVal('bulk-action', action);
    });
})();
