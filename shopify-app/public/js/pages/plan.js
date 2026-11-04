/*
 * Plan: Shopify App Pricing status and the agency/enterprise license form.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;

    function renderBilling(b) {
        if (!b || !b.enabled) {
            Fyn.html('billing-card', '');
            return;
        }
        const pricing = (label, variant) => `<s-button slot="secondary-actions" variant="${variant}" onclick="Fyn.openPricing(Fyn.ctx.billing.pricing_url)">${esc(label)}</s-button>`;
        let html;
        if (b.state === 'active') {
            const lines = [`${t('plan_label')}: ${Fyn.tierLabel(b.plan || b.tier || '')}`];
            if (b.trial_ends_at) lines.push(`${t('trial_until')}: ${Fyn.fmtDate(b.trial_ends_at)}`);
            if (b.cycle_ends_at) lines.push(`${t(b.cancel_at_cycle_end ? 'cancels_on' : 'renews_on')}: ${Fyn.fmtDate(b.cycle_ends_at)}`);
            html = `<s-banner tone="success" heading="${esc(t('plan_active'))}">${lines.map(esc).join('<br>')}${pricing(t('change_plan'), 'secondary')}</s-banner>`;
        } else if (b.state === 'trial') {
            const full = b.trial.used >= b.trial.max;
            const text = t(full ? 'trial_full' : 'trial_text', { used: b.trial.used, max: b.trial.max, date: Fyn.fmtDate(b.trial.ends_at) });
            html = `<s-banner tone="${full ? 'warning' : 'info'}" heading="${esc(t('trial_title'))}">${esc(text)}${pricing(t('choose_plan'), 'secondary')}</s-banner>`;
        } else if (b.state === 'manual') {
            html = `<s-banner tone="success" heading="${esc(t('manual_title'))}">${esc(t('manual_text'))}</s-banner>`;
        } else if (b.state === 'unknown') {
            html = `<s-banner tone="warning" heading="${esc(t('unknown_title'))}">${esc(t('unknown_text'))}</s-banner>`;
        } else {
            html = `<s-banner tone="critical" heading="${esc(t('required_title'))}">${esc(t('required_text'))}${pricing(t('choose_plan'), 'secondary')}</s-banner>`;
        }
        Fyn.html('billing-card', html);
    }

    async function loadLicenseStatus() {
        const data = await Fyn.api('/license/status');
        if (data.error) return;
        // App Pricing licenses are managed in Shopify (plan card above).
        Fyn.show('agency-license', data.source !== 'app_pricing');
        if (data.source === 'app_pricing') return;
        Fyn.html('license-status', data.valid
            ? Fyn.row(t('license_active'), t('tier', { tier: Fyn.tierLabel(data.tier) }), Fyn.badge('success', t('active')))
                + `<s-text color="subdued">${esc(data.license_key || '')}</s-text>`
            : Fyn.row(t('license_none'), t('license_enter'), Fyn.badge('neutral', t('inactive'))));
        Fyn.show('license-form', !data.has_license);
        Fyn.show('license-active', !!data.has_license);
    }

    window.activateLicense = async function (button) {
        const key = Fyn.val('license-key').trim();
        if (!key) {
            Fyn.toast(t('license_enter'), true);
            return;
        }
        const data = await Fyn.busy(button, () => Fyn.post('/license/activate', { license_key: key }));
        if (data.success) {
            Fyn.toast(t('license_activated', { tier: Fyn.tierLabel(data.tier) }));
            loadLicenseStatus();
        } else {
            Fyn.toast(t('license_failed', { message: Fyn.errorText(data) }), true);
        }
    };

    window.deactivateLicense = async function (button) {
        if (!(await Fyn.confirm(t('license_deactivate_confirm')))) return;
        const data = await Fyn.busy(button, () => Fyn.post('/license/deactivate'));
        if (data.success) {
            Fyn.toast(t('license_deactivated'));
            loadLicenseStatus();
        } else {
            Fyn.toast(Fyn.errorText(data), true);
        }
    };

    Fyn.page(async (ctx) => {
        // The context already refreshed the subscription (cached check).
        renderBilling(ctx.billing);
        loadLicenseStatus();
    });
})();
