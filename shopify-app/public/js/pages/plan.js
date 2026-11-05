/*
 * Plan: Shopify App Pricing status. Plans are chosen and changed on
 * Shopify's plan page; there is no other way to license the app.
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
        } else if (b.state === 'unknown') {
            html = `<s-banner tone="warning" heading="${esc(t('unknown_title'))}">${esc(t('unknown_text'))}</s-banner>`;
        } else {
            html = `<s-banner tone="critical" heading="${esc(t('required_title'))}">${esc(t('required_text'))}${pricing(t('choose_plan'), 'secondary')}</s-banner>`;
        }
        Fyn.html('billing-card', html);
    }

    Fyn.page(async (ctx) => {
        // The context already refreshed the subscription (cached check).
        renderBilling(ctx.billing);
    });
})();
