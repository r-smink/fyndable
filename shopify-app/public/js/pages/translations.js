/*
 * Translations (2.3): missing/outdated translations per language and
 * content type, AI translation per item (preview, edit, save) and a
 * background run for everything that is missing.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    let entries = [];
    let current = null;
    let pollTimer = null;
    window.trPage = 1;

    const field = key => t('field_' + key);

    async function loadLanguages() {
        const data = await Fyn.api('/translations');
        if (data.error) {
            Fyn.html('tr-notice', Fyn.errorBanner(data));
            return false;
        }
        if (!data.targets.length) {
            Fyn.html('tr-notice', Fyn.banner('info', t('no_languages')));
            ['tr-filters', 'tr-gaps-section', 'tr-run-section'].forEach(id => Fyn.show(id, false));
            return false;
        }
        Fyn.$('tr-locale').innerHTML = data.targets.map(l => `<s-option value="${esc(l.locale)}">${esc(l.name)}${l.published ? '' : ' (' + esc(t('unpublished')) + ')'}</s-option>`).join('');
        Fyn.setVal('tr-locale', data.targets[0].locale);
        return true;
    }

    async function loadGaps(page) {
        if (page < 1) return;
        Fyn.html('tr-gaps', Fyn.loading());
        const params = new URLSearchParams({ type: Fyn.val('tr-type') || 'product', locale: Fyn.val('tr-locale'), page: String(page) });
        const data = await Fyn.api('/translations/gaps?' + params.toString());
        if (data.error) {
            Fyn.html('tr-gaps', Fyn.errorBanner(data));
            return;
        }
        window.trPage = data.page;
        Fyn.$('tr-summary').textContent = data.total ? t('gaps_summary', { total: data.total, fields: data.fields }) : '';
        Fyn.html('tr-gaps', data.items.length
            ? Fyn.table([t('col_item'), t('col_missing'), t('col_outdated'), t('col_action')], data.items.map(item => [
                esc(item.title),
                esc(item.missing.map(field).join(', ') || '-'),
                esc(item.outdated.map(field).join(', ') || '-'),
                `<s-button variant="tertiary" data-id="${esc(item.resource_id)}" onclick="openTranslation(this.dataset.id)">${esc(t('translate'))}</s-button>`,
            ]))
            : Fyn.banner('success', t('no_gaps')));
        Fyn.show('tr-pager', data.pages > 1);
        Fyn.$('tr-prev').disabled = data.page <= 1;
        Fyn.$('tr-next').disabled = data.page >= data.pages;
        Fyn.$('tr-page').textContent = `${data.page} / ${data.pages}`;
    }

    window.loadGaps = loadGaps;

    window.openTranslation = async function (resourceId) {
        current = { resource_id: resourceId, type: Fyn.val('tr-type') || 'product', locale: Fyn.val('tr-locale') };
        entries = [];
        Fyn.html('tr-fields', '');
        Fyn.html('tr-modal-status', `<s-stack direction="inline" gap="small-200" alignItems="center"><s-spinner></s-spinner><s-text>${esc(t('translating'))}</s-text></s-stack>`);
        Fyn.$('tr-save').disabled = true;
        Fyn.$('tr-modal').showOverlay();

        const data = await Fyn.post('/translations/preview', current);
        if (data.error) {
            Fyn.html('tr-modal-status', Fyn.errorBanner(data));
            return;
        }
        entries = data.entries;
        if (!entries.length) {
            Fyn.html('tr-modal-status', Fyn.banner('info', t('nothing')));
            return;
        }
        Fyn.html('tr-modal-status', '');
        Fyn.html('tr-fields', `<s-stack gap="large">${entries.map((e, i) => `
            <s-stack gap="small-200">
                <s-text-area id="tr-entry-${i}" label="${esc(field(e.key))}" rows="${e.key === 'body_html' || e.key === 'summary_html' ? 8 : 2}"></s-text-area>
                <s-text color="subdued">${esc(t('source'))}: ${esc(e.source.replace(/<[^>]+>/g, ' ').slice(0, 300))}</s-text>
            </s-stack>`).join('')}</s-stack>`);
        entries.forEach((e, i) => Fyn.setVal('tr-entry-' + i, e.value));
        Fyn.$('tr-save').disabled = false;
    };

    window.saveTranslation = async function (button) {
        if (!current || !entries.length) return;
        const edited = entries.map((e, i) => ({ key: e.key, value: Fyn.val('tr-entry-' + i), digest: e.digest }));
        const data = await Fyn.busy(button, () => Fyn.post('/translations/save', { ...current, entries: edited }));
        if (data.error) {
            Fyn.html('tr-modal-status', Fyn.errorBanner(data));
            return;
        }
        Fyn.$('tr-modal').hideOverlay();
        Fyn.toast(t('saved'));
        loadGaps(window.trPage);
    };

    function renderProgress(data) {
        const active = ['queued', 'running'].includes(data.status);
        Fyn.show('tr-cancel', active);
        Fyn.$('tr-run').loading = active;
        if (data.status === 'idle') return Fyn.html('tr-progress', '');
        const counts = { processed: data.processed || 0, total: data.total || 0, succeeded: data.succeeded || 0, failed: data.failed || 0, fields: data.fields || 0 };
        let html = data.total ? `<s-stack gap="small-200"><s-text>${esc(t('progress', counts))}</s-text>
            <s-progress value="${counts.processed}" max="${Math.max(counts.total, 1)}" accessibilityLabel="${esc(t('progress', counts))}"></s-progress></s-stack>` : '';
        if (active) html += Fyn.banner('info', t('running', counts));
        else if (data.status === 'completed') html += Fyn.banner(counts.failed ? 'warning' : 'success', t('completed', counts));
        else if (data.status === 'cancelled') html += Fyn.banner('warning', t('cancelled'));
        else if (data.status === 'failed') html += Fyn.banner('critical', t('failed', { error: data.error || '' }));
        if ((data.errors || []).length) html += Fyn.table([t('col_item'), t('col_error')], data.errors.map(e => [esc(e.title), esc(e.error)]));
        Fyn.html('tr-progress', `<s-stack gap="base">${html}</s-stack>`);
    }

    async function poll() {
        clearTimeout(pollTimer);
        const data = await Fyn.api('/translations/progress');
        if (data.error && !data.status) return;
        renderProgress(data);
        if (['queued', 'running'].includes(data.status)) pollTimer = setTimeout(poll, 3000);
        else if (data.status === 'completed') loadGaps(1);
    }

    window.runTranslations = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/translations/run', {
            type: Fyn.val('tr-type') || 'product',
            locale: Fyn.val('tr-locale'),
            limit: parseInt(Fyn.val('tr-limit'), 10) || 50,
        }));
        if (data.error) {
            const known = { job_running: t('job_running'), no_gaps: t('no_gaps') };
            Fyn.toast(known[data.error] || Fyn.errorText(data), true);
            return;
        }
        Fyn.toast(t('run_started', { total: data.total }));
        poll();
    };

    window.cancelTranslations = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/translations/cancel'));
        if (!data.error) Fyn.toast(t('cancel_requested'));
        poll();
    };

    Fyn.page(async () => {
        if (await loadLanguages()) {
            await loadGaps(1);
            poll();
        }
    });
})();
