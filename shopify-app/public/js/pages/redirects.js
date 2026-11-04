/*
 * Redirects and 404s (1.2): 404 monitor, Shopify URL redirects, CSV
 * import/export and the automatic redirect settings.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    let nextCursor = null;
    let redirects = [];
    let notFound = [];
    let modal = { mode: 'create', id: null, notFoundId: null };
    let importTimer = null;

    const ERRORS = ['invalid_path', 'invalid_target', 'same_path'];

    function shortDate(iso) {
        return iso ? Fyn.fmtDate(iso) : '';
    }

    // ---------------------------------------------------------------- 404s
    async function loadNotFound() {
        const data = await Fyn.api('/redirects/not-found');
        if (data.error) {
            Fyn.html('nf-list', Fyn.errorBanner(data));
            return;
        }
        notFound = data.items;
        Fyn.$('nf-count').textContent = data.open ? t('open_count', { count: data.open }) : '';
        Fyn.html('nf-list', notFound.length
            ? Fyn.table(
                [t('col_path'), { label: t('col_hits'), format: 'numeric' }, t('col_last'), t('col_referrer'), t('col_action')],
                notFound.map((row, i) => [
                    esc(row.path),
                    esc(row.hits),
                    esc(shortDate(row.last_seen_at)),
                    esc(row.referrer || '-'),
                    `<s-stack direction="inline" gap="small-200">
                        <s-button variant="tertiary" onclick="openRedirectModal('fix', ${i})">${esc(t('fix'))}</s-button>
                        <s-button variant="tertiary" onclick="ignoreNotFound(this, ${i})">${esc(t('ignore'))}</s-button>
                    </s-stack>`,
                ]))
            : `<s-text color="subdued">${esc(t('no_not_found'))}</s-text>`);
    }

    window.ignoreNotFound = async function (button, index) {
        const row = notFound[index];
        const data = await Fyn.busy(button, () => Fyn.post(`/redirects/not-found/${row.id}/ignore`));
        if (data.error) {
            Fyn.toast(Fyn.errorText(data), true);
            return;
        }
        Fyn.toast(t('ignored'));
        loadNotFound();
    };

    // ----------------------------------------------------------- Redirects
    function renderRedirects() {
        Fyn.html('rd-list', redirects.length
            ? Fyn.table(
                [t('col_path'), t('col_target'), t('col_action')],
                redirects.map((r, i) => [
                    esc(r.path),
                    esc(r.target),
                    `<s-stack direction="inline" gap="small-200">
                        <s-button variant="tertiary" onclick="openRedirectModal('edit', ${i})">${esc(t('edit'))}</s-button>
                        <s-button variant="tertiary" tone="critical" onclick="deleteRedirect(this, ${i})">${esc(t('delete'))}</s-button>
                    </s-stack>`,
                ]))
            : `<s-text color="subdued">${esc(t('no_redirects'))}</s-text>`);
        Fyn.show('rd-more', !!nextCursor);
    }

    async function loadRedirects(reset) {
        const params = new URLSearchParams();
        const search = Fyn.val('rd-search').trim();
        if (search) params.set('search', search);
        if (!reset && nextCursor) params.set('after', nextCursor);
        if (reset) Fyn.html('rd-list', Fyn.loading());
        const data = await Fyn.api('/redirects?' + params.toString());
        if (data.error) {
            Fyn.html('rd-list', Fyn.errorBanner(data));
            return;
        }
        redirects = reset ? data.redirects : redirects.concat(data.redirects);
        nextCursor = data.next;
        renderRedirects();
    }

    window.loadRedirects = loadRedirects;

    window.deleteRedirect = async function (button, index) {
        const r = redirects[index];
        if (!(await Fyn.confirm(t('delete_confirm', { path: r.path })))) return;
        const data = await Fyn.busy(button, () => Fyn.api(`/redirects/${r.id.split('/').pop()}`, { method: 'DELETE' }));
        if (data.error) {
            Fyn.toast(Fyn.errorText(data), true);
            return;
        }
        Fyn.toast(t('redirect_deleted'));
        redirects.splice(index, 1);
        renderRedirects();
    };

    // --------------------------------------------------------------- Modal
    window.openRedirectModal = function (mode = 'create', index = null) {
        modal = { mode, id: null, notFoundId: null };
        let path = '';
        let target = '';
        if (mode === 'edit') {
            path = redirects[index].path;
            target = redirects[index].target;
            modal.id = redirects[index].id.split('/').pop();
        } else if (mode === 'fix') {
            path = notFound[index].path;
            modal.notFoundId = notFound[index].id;
        }
        Fyn.setVal('rd-path', path);
        Fyn.setVal('rd-target', target);
        Fyn.$('rd-path').disabled = mode === 'fix';
        Fyn.html('rd-modal-error', '');
        Fyn.$('rd-modal').showOverlay();
    };

    window.saveRedirect = async function (button) {
        const body = { path: Fyn.val('rd-path').trim(), target: Fyn.val('rd-target').trim() };
        const request = modal.mode === 'fix'
            ? () => Fyn.post(`/redirects/not-found/${modal.notFoundId}/fix`, { target: body.target })
            : modal.mode === 'edit'
                ? () => Fyn.post(`/redirects/${modal.id}`, body)
                : () => Fyn.post('/redirects', body);
        const data = await Fyn.busy(button, request);
        if (data.error) {
            Fyn.html('rd-modal-error', ERRORS.includes(data.error)
                ? Fyn.banner('critical', t(data.error))
                : Fyn.errorBanner(data));
            return;
        }
        Fyn.$('rd-modal').hideOverlay();
        Fyn.toast(t('redirect_saved'));
        loadRedirects(true);
        loadNotFound();
        loadSettings();
    };

    // ------------------------------------------------------- Import/export
    window.exportCsv = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.api('/redirects/export'));
        if (data.error) {
            Fyn.toast(Fyn.errorText(data), true);
            return;
        }
        const url = URL.createObjectURL(new Blob([data.csv], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `redirects-${Fyn.cfg.shop.replace('.myshopify.com', '')}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        Fyn.toast(t('exported', { count: data.count }));
    };

    window.importCsv = async function (button) {
        const csv = Fyn.val('csv-input');
        if (!csv.trim()) return;
        const data = await Fyn.busy(button, () => Fyn.post('/redirects/import', { csv }));
        if (data.error) {
            const known = { no_valid_rows: t('no_valid_rows'), too_many_rows: t('too_many_rows', { max: data.max }) };
            Fyn.html('csv-status', Fyn.banner('critical', known[data.error] || Fyn.errorText(data)));
            return;
        }
        Fyn.html('csv-status', Fyn.banner('info', t('import_started', { rows: data.rows, invalid: data.invalid })));
        pollImport();
    };

    function pollImport() {
        clearTimeout(importTimer);
        importTimer = setTimeout(async () => {
            const data = await Fyn.api('/redirects/import/status');
            const s = data.import;
            if (!s) return;
            if (s.status === 'queued' || s.status === 'running') {
                Fyn.html('csv-status', `<s-stack gap="small-200"><s-text>${esc(t('import_running', s))}</s-text>
                    <s-progress value="${Number(s.processed) || 0}" max="${Math.max(1, Number(s.total) || 1)}" accessibilityLabel="${esc(t('import_running', s))}"></s-progress></s-stack>`);
                pollImport();
            } else if (s.status === 'completed') {
                Fyn.html('csv-status', Fyn.banner(s.failed ? 'warning' : 'success', t('import_done', s)));
                Fyn.setVal('csv-input', '');
                loadRedirects(true);
            } else {
                Fyn.html('csv-status', Fyn.banner('critical', t('import_failed', s)));
            }
        }, 3000);
    }

    // ------------------------------------------------------------ Settings
    async function loadSettings() {
        const data = await Fyn.api('/redirects/settings');
        if (data.error) {
            Fyn.html('rd-events', Fyn.errorBanner(data));
            return;
        }
        const s = data.settings;
        Fyn.setChecked('set-handle', s.redirect_on_handle_change);
        Fyn.setChecked('set-delete', s.redirect_on_delete);
        Fyn.setVal('set-target', s.deleted_redirect_target);
        Fyn.setChecked('set-monitor', s.not_found_monitor);
        Fyn.html('rd-events', data.events.length
            ? `<s-stack gap="small-200">${data.events.map(e => Fyn.row(`${e.path} > ${e.target}`, `${t('source_' + e.source)} - ${shortDate(e.created_at)}`)).join('')}</s-stack>`
            : `<s-text color="subdued">${esc(t('no_events'))}</s-text>`);
    }

    window.saveRedirectSettings = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/redirects/settings', {
            redirect_on_handle_change: Fyn.checked('set-handle'),
            redirect_on_delete: Fyn.checked('set-delete'),
            deleted_redirect_target: Fyn.val('set-target') || 'collection',
            not_found_monitor: Fyn.checked('set-monitor'),
        }));
        if (data.error) {
            Fyn.toast(t('save_failed'), true);
            return;
        }
        Fyn.toast(t('settings_saved'));
    };

    Fyn.page(async () => {
        await Promise.all([loadNotFound(), loadRedirects(true), loadSettings()]);
        const status = await Fyn.api('/redirects/import/status');
        if (status.import && ['queued', 'running'].includes(status.import.status)) pollImport();
    });
})();
