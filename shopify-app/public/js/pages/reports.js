/*
 * Reports (1.7): weekly email settings, send a report now, and the list of
 * reports (each opens as a printable page; "Save as PDF" in the browser).
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    let refreshTimer = null;

    async function load() {
        const data = await Fyn.api('/reports');
        if (data.error) {
            Fyn.html('rep-list', Fyn.errorBanner(data));
            return null;
        }
        Fyn.setChecked('rep-enabled', data.settings.enabled);
        Fyn.setVal('rep-email', data.settings.email || '');
        Fyn.setVal('rep-language', data.settings.language);
        Fyn.html('rep-list', data.reports.length
            ? Fyn.table([t('col_period'), t('col_sent'), t('col_action')], data.reports.map(r => [
                esc(`${Fyn.fmtDate(r.period_start)} - ${Fyn.fmtDate(r.period_end)}`),
                esc(r.sent_at ? `${r.sent_to} (${Fyn.fmtDate(r.sent_at, true)})` : t('not_sent')),
                `<s-link href="${esc(r.url)}" target="_blank">${esc(t('open'))}</s-link>`,
            ]))
            : `<s-text color="subdued">${esc(t('empty'))}</s-text>`);
        return data;
    }

    window.saveReportSettings = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/reports/settings', {
            enabled: Fyn.checked('rep-enabled'),
            email: Fyn.val('rep-email').trim(),
            language: Fyn.val('rep-language') || 'en',
        }));
        if (data.error) {
            Fyn.toast(data.error === 'invalid_settings' ? t('invalid_email') : Fyn.errorText(data), true);
            return;
        }
        Fyn.toast(t('saved'));
    };

    window.sendReportNow = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/reports/send'));
        if (data.error) {
            const known = { no_email: t('no_email'), too_soon: t('too_soon') };
            Fyn.toast(known[data.error] || Fyn.errorText(data), true);
            return;
        }
        Fyn.toast(t('sending'));
        // The report is built in the background; refresh the list a few times.
        let tries = 0;
        clearInterval(refreshTimer);
        refreshTimer = setInterval(async () => {
            if (++tries > 6) clearInterval(refreshTimer);
            await load();
        }, 5000);
    };

    Fyn.page(load);
})();
