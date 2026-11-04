/*
 * AI optimization tracker: mentions overview, trends, recommendations and a
 * brand check across all AI providers.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    const PROVIDERS = { chatgpt: 'ChatGPT', claude: 'Claude', gemini: 'Gemini', perplexity: 'Perplexity' };

    async function loadAiTrackerOverview() {
        Fyn.html('ait-stats', Fyn.loading());
        const data = await Fyn.api('/ai-tracker/overview');
        if (data.error) {
            Fyn.html('ait-stats', Fyn.errorBanner(data));
            return;
        }
        const m = data.metrics || {};
        Fyn.html('ait-stats', Fyn.stats([
            { label: t('stat_mentions'), value: m.total_mentions ?? m.mentions_count ?? '-', sub: t('stat_mentions_sub') },
            { label: t('stat_domains'), value: (data.top_brands?.data || []).length || '-', sub: t('stat_domains_sub') },
            { label: t('stat_pages'), value: (data.top_pages?.data || []).length || '-', sub: t('stat_pages_sub') },
            { label: t('stat_target'), value: data.target || Fyn.cfg.shop },
        ]));

        const pages = data.top_pages?.data || [];
        Fyn.html('ait-top-pages', pages.length
            ? pages.slice(0, 10).map(p => Fyn.row(p.url || p.page || p.title || '-', t('mentions', { count: p.mentions || p.count || '-' }))).join('')
            : `<s-paragraph>${esc(t('no_pages'))}</s-paragraph>`);

        loadAiTrackerTimeseries();
        loadAiTrackerRecommendations();
    }

    async function loadAiTrackerTimeseries() {
        const data = await Fyn.api('/ai-tracker/timeseries');
        Fyn.html('ait-timeseries', data.error ? Fyn.errorBanner(data) : Fyn.json(data.delta || data));
    }

    async function loadAiTrackerRecommendations() {
        const data = await Fyn.api('/ai-tracker/recommendations');
        if (data.error) {
            Fyn.html('ait-recommendations', `<s-paragraph>${esc(t('no_recs_scan'))}</s-paragraph>`);
            return;
        }
        const recs = data.recommendations || [];
        if (!recs.length) {
            Fyn.html('ait-recommendations', `<s-paragraph>${esc(t('no_recs'))}</s-paragraph>`);
            return;
        }
        const tone = p => (p === 'high' ? 'critical' : p === 'medium' ? 'warning' : 'neutral');
        const label = p => {
            const key = 'priority_' + p;
            return t(key) === key ? String(p || '') : t(key);
        };
        Fyn.html('ait-recommendations', recs.map(r => Fyn.row(r.title, r.description, Fyn.badge(tone(r.priority), label(r.priority)))).join(''));
    }

    window.loadAiTrackerOverview = loadAiTrackerOverview;

    window.runAiTrackerCheck = async function (button) {
        const prompt = Fyn.val('ait-prompt').trim();
        if (!prompt) {
            Fyn.toast(t('enter_question'), true);
            return;
        }
        Fyn.html('ait-check-result', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post('/ai-tracker/check', { prompt }));
        if (data.error) {
            Fyn.html('ait-check-result', Fyn.errorBanner(data));
            return;
        }
        const results = data.results || {};
        Fyn.html('ait-check-result', `<s-stack gap="base">${Object.entries(results).map(([provider, result]) => {
            const name = PROVIDERS[provider] || provider.charAt(0).toUpperCase() + provider.slice(1);
            const body = result && result.error ? Fyn.banner('critical', result.error) : Fyn.json(result);
            return `<s-box padding="base" border="base" borderRadius="base">
                    <s-stack gap="small-200"><s-text type="strong">${esc(name)}</s-text>${body}</s-stack>
                </s-box>`;
        }).join('')}</s-stack>`);
    };

    Fyn.page(async () => {
        loadAiTrackerOverview();
    });
})();
