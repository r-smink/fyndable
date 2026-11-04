/*
 * Home: store overview, setup card, billing banner and plan confirmation
 * after the Shopify plan page redirect. Uses /dashboard/overview (which also
 * carries the page context) instead of /dashboard/context.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    Fyn.customContext = true;

    function usageRow(title, u) {
        if (!u || u.limit === 0) return '';
        const text = u.limit === null ? t('usage_unlimited', { used: u.used }) : t('usage_month', { used: u.used, limit: u.limit });
        const full = u.limit !== null && u.used >= u.limit;
        return Fyn.row(title, text, Fyn.badge(full ? 'critical' : 'success', full ? t('limit_hit') : t('available')));
    }

    async function loadOverview() {
        const data = await Fyn.api('/dashboard/overview');
        if (data.error) {
            Fyn.html('home-stats', Fyn.errorBanner(data));
            Fyn.html('home-status', '');
            return;
        }

        await Fyn.applyContext({ billing: data.billing, plan: data.plan, onboarding: data.onboarding });

        const valid = !!data.license?.valid;
        const tier = data.plan?.tier || data.license?.tier || 'free';
        Fyn.html('home-stats', Fyn.stats([
            { label: t('stat_plan'), value: Fyn.tierLabel(tier), sub: valid ? t('active') : t('inactive') },
            { label: t('stat_products'), value: data.product_count ?? 0, sub: t('stat_products_sub') },
            { label: t('stat_keywords'), value: data.tracked_keywords ?? 0, sub: t('stat_keywords_sub') },
            { label: t('stat_top10'), value: data.top_10_keywords ?? 0, sub: t('stat_top10_sub'), tone: 'success' },
            { label: t('stat_llms'), value: data.llms_txt_enabled ? t('enabled') : t('disabled'), tone: data.llms_txt_enabled ? 'success' : 'critical' },
        ]));

        const on = ok => Fyn.badge(ok ? 'success' : 'neutral', ok ? t('enabled') : t('disabled'));
        const scopes = data.missing_scopes || [];
        Fyn.html('home-status',
            Fyn.row(t('row_license'), t('tier', { tier: Fyn.tierLabel(tier) }), Fyn.badge(valid ? 'success' : 'neutral', valid ? t('active') : t('inactive')))
            + usageRow(t('row_geo'), data.plan?.usage?.geo_scans)
            + usageRow(t('row_blog'), data.plan?.usage?.blog_articles)
            + Fyn.row(t('row_llms'), t('row_llms_sub'), on(data.llms_txt_enabled))
            + Fyn.row(t('row_llms_full'), t('row_llms_full_sub'), on(data.llms_txt_full_enabled))
            + Fyn.row(t('row_scopes'), scopes.length ? t('row_scopes_missing', { scopes: scopes.join(', ') }) : t('row_scopes_ok'),
                Fyn.badge(scopes.length ? 'critical' : 'success', scopes.length ? t('action_needed') : t('ok'))));

        Fyn.html('home-alerts', scopes.length
            ? `<s-banner tone="critical">${esc(t('missing_scopes', { scopes: scopes.join(', ') }))}
                <s-button slot="secondary-actions" href="${esc(data.reauth_url)}" target="_top">${esc(t('reauth'))}</s-button></s-banner>`
            : '');

        // Setup card until onboarding is done; not while a plan is required.
        Fyn.show('onb-card', !!data.onboarding && !data.onboarding.done && !['none', 'unknown'].includes(data.billing?.state));
    }

    // Back from the Shopify plan page: ?plan_handle=... is only a hint, the
    // server re-reads the subscription from the Partner API.
    async function confirmPlanFromRedirect() {
        const planHandle = new URLSearchParams(location.search).get('plan_handle');
        if (!planHandle) return;
        sessionStorage.removeItem('fyn-pricing-redirected');
        const data = await Fyn.post('/billing/confirm', { plan_handle: planHandle });
        if (!data.error && data.plan_confirmed) {
            Fyn.toast(t('plan_confirmed'));
            loadOverview();
        }
    }

    window.loadOverview = loadOverview;
    FynOnboarding.onDone = loadOverview;

    Fyn.page(async () => {
        await loadOverview();
        confirmPlanFromRedirect();
    });
})();
