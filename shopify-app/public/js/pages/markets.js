/*
 * Markets SEO check: hreflang consistency, locale coverage and translation
 * gaps for Shopify Markets.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;

    async function loadMarketsOverview() {
        const data = await Fyn.api('/markets/overview');
        if (data.error) {
            Fyn.html('markets-overview', Fyn.errorBanner(data));
            return;
        }
        const markets = data.markets || [];
        if (!markets.length) {
            Fyn.html('markets-overview', `<s-paragraph>${esc(t('mk_no_markets'))}</s-paragraph>`);
            return;
        }
        Fyn.html('markets-overview', markets.map(m => {
            const locales = (m.webPresences || []).flatMap(wp => [wp.defaultLocale, ...(wp.alternateLocales || [])]).filter(Boolean);
            const regions = (m.regions || []).map(r => r.name || r.code).join(', ');
            return Fyn.row(
                m.name + (m.primary ? ` (${t('mk_primary')})` : ''),
                t('mk_market_sub', { locales: locales.join(', ') || t('none'), regions: regions || t('none') }),
                Fyn.badge(m.enabled ? 'success' : 'neutral', m.enabled ? t('active') : t('disabled'))
            );
        }).join(''));
    }

    async function loadMarketsAudit() {
        Fyn.html('markets-stats', Fyn.loading());
        Fyn.html('markets-checks', Fyn.loading());

        const data = await Fyn.api('/markets/audit');
        if (data.error) {
            Fyn.html('markets-stats', Fyn.errorBanner(data));
            Fyn.html('markets-checks', '');
            Fyn.html('markets-recommendations', '');
            Fyn.html('markets-overview', '');
            return;
        }

        const score = data.score ?? 0;
        const locales = data.locales || [];
        Fyn.html('markets-stats', Fyn.stats([
            { label: t('mk_score'), value: score + '%', tone: score >= 80 ? 'success' : score >= 50 ? 'warning' : 'critical', sub: score >= 80 ? t('mk_good') : score >= 50 ? t('mk_needs_work') : t('mk_issues') },
            { label: t('mk_markets'), value: data.markets_count ?? 0, sub: t('mk_configured') },
            { label: t('mk_locales'), value: locales.length, sub: locales.join(', ') || t('none') },
            { label: t('mk_checks'), value: (data.checks || []).length, sub: t('mk_checks_sub') },
        ]));

        const checks = data.checks || [];
        Fyn.html('markets-checks', checks.length
            ? checks.map(c => Fyn.row(
                [c.name, c.market, c.locale].filter(Boolean).join(' · '),
                c.detail || '',
                Fyn.badge(c.passed ? 'success' : 'critical', c.passed ? t('mk_pass') : t('mk_fail'))
            )).join('')
            : `<s-paragraph>${esc(t('mk_no_checks'))}</s-paragraph>`);

        const recs = data.recommendations || [];
        Fyn.html('markets-recommendations', recs.length
            ? `<s-unordered-list>${recs.map(r => `<s-list-item>${esc(r)}</s-list-item>`).join('')}</s-unordered-list>`
            : `<s-paragraph>${esc(t('mk_no_issues'))}</s-paragraph>`);

        await loadMarketsOverview();
    }

    window.loadMarketsAudit = loadMarketsAudit;

    Fyn.page(loadMarketsAudit);
})();
