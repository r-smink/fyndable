/*
 * Google Search Console (1.5): connect through the SaaS Google OAuth popup
 * (only the authorization code comes back; the app exchanges it on the
 * server), choose the property, and show totals, "almost page 1"
 * opportunities and top pages.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    let popupOrigin = null;

    const pct = v => `${(Number(v || 0) * 100).toFixed(1)}%`;
    const num = v => Number(v || 0).toLocaleString(Fyn.cfg.lang === 'nl' ? 'nl-NL' : 'en-US');

    function editLink(resource) {
        if (!resource) return '';
        return `<s-link href="${esc(Fyn.url('content', `type=${resource.type}&id=${encodeURIComponent(resource.id)}`))}">${esc(t('optimize'))}</s-link>`;
    }

    function pathLabel(row) {
        return `<s-link href="${esc(row.page)}" target="_blank">${esc(row.path)}</s-link>`;
    }

    async function loadStatus() {
        const data = await Fyn.api('/gsc');
        if (data.error) {
            Fyn.html('gsc-status', Fyn.errorBanner(data));
            return;
        }
        const connected = data.connected && data.status === 'connected';
        Fyn.show('gsc-connect-btn', !connected);
        Fyn.show('gsc-disconnect-btn', !!data.connected);
        Fyn.show('gsc-sync-btn', connected && !!data.site_url);

        let status;
        if (!data.connected) status = `<s-text color="subdued">${esc(t('not_connected'))}</s-text>`;
        else if (data.status === 'needs_reconnect') status = Fyn.banner('warning', t('needs_reconnect'));
        else if (!data.site_url) status = Fyn.banner('info', t('choose_site'));
        else status = Fyn.banner('success', t('connected_to', { site: data.site_url }));
        if (data.sync_error && data.status === 'connected') status += Fyn.banner('warning', t('sync_error', { error: data.sync_error }));
        Fyn.html('gsc-status', `<s-stack gap="small-200">${status}</s-stack>`);

        if (connected && !data.site_url) await loadSites();

        const hasData = connected && data.site_url && data.totals;
        ['gsc-stats-section', 'gsc-opportunities-section', 'gsc-pages-section'].forEach(id => Fyn.show(id, !!hasData));
        if (connected && data.site_url && !data.totals) {
            Fyn.html('gsc-status', `<s-stack gap="small-200">${status}<s-text color="subdued">${esc(t('no_data_yet'))}</s-text></s-stack>`);
        }
        if (!hasData) return;

        Fyn.$('gsc-period').textContent = `${t('period', { start: Fyn.fmtDate(data.period_start), end: Fyn.fmtDate(data.period_end) })} - ${t('synced', { date: Fyn.fmtDate(data.synced_at, true) })}`;
        Fyn.html('gsc-stats', Fyn.stats([
            { label: t('stat_clicks'), value: num(data.totals.clicks) },
            { label: t('stat_impressions'), value: num(data.totals.impressions) },
            { label: t('stat_ctr'), value: pct(data.totals.ctr) },
            { label: t('stat_position'), value: data.totals.position },
        ]));
        await Promise.all([loadOpportunities(), loadPages()]);
    }

    async function loadSites() {
        const data = await Fyn.api('/gsc/sites');
        if (data.error) {
            Fyn.html('gsc-status', Fyn.errorBanner(data));
            return;
        }
        if (!data.sites.length) {
            Fyn.html('gsc-status', Fyn.banner('warning', t('no_sites')));
            Fyn.show('gsc-site-box', false);
            return;
        }
        const selected = data.site_url || data.suggested || data.sites[0].site_url;
        Fyn.$('gsc-site').innerHTML = data.sites.map(s => `<s-option value="${esc(s.site_url)}"${s.site_url === selected ? ' selected' : ''}>${esc(s.site_url)}</s-option>`).join('');
        Fyn.setVal('gsc-site', selected);
        Fyn.show('gsc-site-box', true);
    }

    async function loadOpportunities() {
        const data = await Fyn.api('/gsc/opportunities');
        if (data.error) return Fyn.html('gsc-opportunities', Fyn.errorBanner(data));
        Fyn.html('gsc-opportunities', data.opportunities.length
            ? Fyn.table(
                [t('col_query'), t('col_page'), { label: t('col_position'), format: 'numeric' }, { label: t('col_impressions'), format: 'numeric' }, { label: t('col_clicks'), format: 'numeric' }, t('col_action')],
                data.opportunities.map(o => [esc(o.query), pathLabel(o), esc(o.position), esc(num(o.impressions)), esc(num(o.clicks)), editLink(o.resource)]))
            : `<s-text color="subdued">${esc(t('no_opportunities'))}</s-text>`);
    }

    async function loadPages() {
        const data = await Fyn.api('/gsc/pages');
        if (data.error) return Fyn.html('gsc-pages', Fyn.errorBanner(data));
        Fyn.html('gsc-pages', data.pages.length
            ? Fyn.table(
                [t('col_page'), { label: t('col_clicks'), format: 'numeric' }, { label: t('col_impressions'), format: 'numeric' }, { label: t('col_ctr'), format: 'numeric' }, { label: t('col_position'), format: 'numeric' }, t('col_action')],
                data.pages.map(p => [pathLabel(p), esc(num(p.clicks)), esc(num(p.impressions)), esc(pct(p.ctr)), esc(p.position), editLink(p.resource)]))
            : `<s-text color="subdued">${esc(t('no_pages'))}</s-text>`);
    }

    // The popup (SaaS domain) posts {type: 'fyndable_google_code', code}.
    window.addEventListener('message', async event => {
        if (!popupOrigin || event.origin !== popupOrigin || !event.data || event.data.type !== 'fyndable_google_code') return;
        popupOrigin = null;
        if (!event.data.success || !event.data.code) {
            Fyn.toast(t('connect_failed', { message: event.data.error || '' }), true);
            return;
        }
        const data = await Fyn.post('/gsc/connect', { code: event.data.code });
        if (data.error) {
            Fyn.toast(t('connect_failed', { message: data.message || Fyn.errorText(data) }), true);
            return;
        }
        Fyn.toast(t('connected'));
        await loadStatus();
    });

    window.connectGsc = async function (button) {
        // Open the window inside the click (pop-up blockers), then navigate it.
        const popup = window.open('', 'fyndable-google', 'width=520,height=680');
        if (!popup) {
            Fyn.toast(t('popup_blocked'), true);
            return;
        }
        const data = await Fyn.busy(button, () => Fyn.post('/gsc/session'));
        if (data.error || !data.url) {
            popup.close();
            Fyn.toast(t('connect_failed', { message: data.message || Fyn.errorText(data) }), true);
            return;
        }
        popupOrigin = new URL(data.url).origin;
        popup.location.href = data.url;
    };

    window.saveGscSite = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/gsc/site', { site_url: Fyn.val('gsc-site') }));
        if (data.error) {
            Fyn.toast(Fyn.errorText(data), true);
            return;
        }
        Fyn.show('gsc-site-box', false);
        Fyn.toast(t('site_saved'));
        await loadStatus();
    };

    window.syncGsc = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/gsc/sync'));
        Fyn.toast(data.error === 'sync_too_soon' ? t('sync_too_soon') : data.error ? Fyn.errorText(data) : t('sync_started'), !!data.error);
    };

    window.disconnectGsc = async function (button) {
        if (!(await Fyn.confirm(t('disconnect_confirm')))) return;
        const data = await Fyn.busy(button, () => Fyn.post('/gsc/disconnect'));
        if (data.error) {
            Fyn.toast(Fyn.errorText(data), true);
            return;
        }
        Fyn.toast(t('disconnected'));
        Fyn.show('gsc-site-box', false);
        await loadStatus();
    };

    Fyn.page(loadStatus);
})();
