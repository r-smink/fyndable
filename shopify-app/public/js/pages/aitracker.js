/*
 * AI optimization tracker: mentions overview, trends, recommendations and a
 * brand check across all AI providers.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    const PROVIDERS = { chatgpt: 'ChatGPT', claude: 'Claude', gemini: 'Gemini', perplexity: 'Perplexity' };
    const PLATFORMS = { chat_gpt: 'ChatGPT', google: 'Google AI Overview' };

    async function loadAiTrackerOverview() {
        Fyn.html('ait-stats', Fyn.loading());
        const data = await Fyn.api('/ai-tracker/overview');
        if (data.error) {
            Fyn.html('ait-stats', Fyn.errorBanner(data));
            return;
        }
        const m = data.metrics || {};
        const platforms = m.platforms || [];
        const top = platforms[0];
        let html = Fyn.stats([
            { label: t('stat_mentions'), value: Fyn.num(m.mentions), sub: t('stat_mentions_sub') },
            { label: t('stat_volume'), value: Fyn.num(m.ai_search_volume), sub: t('stat_volume_sub') },
            { label: t('stat_platform'), value: top ? (PLATFORMS[top.label] || top.label) : '-', sub: top ? t('mentions', { count: Fyn.num(top.mentions) }) : '' },
            { label: t('stat_target'), value: data.target || Fyn.cfg.shop },
        ]);
        if (m.mentions === null || m.mentions === undefined) {
            html += Fyn.banner('info', t('no_metrics'));
        }
        const domains = m.source_domains || [];
        if (domains.length) {
            html += `<s-stack gap="small-200"><s-text type="strong">${esc(t('source_domains'))}</s-text>
                ${domains.slice(0, 5).map(d => Fyn.row(d.label, t('mentions', { count: Fyn.num(d.mentions) }))).join('')}</s-stack>`;
        }
        Fyn.html('ait-stats', `<s-stack gap="base">${html}</s-stack>`);

        const pages = data.top_pages || [];
        Fyn.html('ait-top-pages', pages.length
            ? pages.map(p => `<s-stack direction="inline" gap="base" justifyContent="space-between" alignItems="center" paddingBlock="small-200">
                    <span class="fyn-wrap">${Fyn.link(p.label, p.label.replace(/^https?:\/\/(www\.)?/, ''))}</span>
                    <s-text color="subdued">${esc(t('mentions', { count: Fyn.num(p.mentions) }))}</s-text></s-stack>`).join('')
            : `<s-paragraph>${esc(t('no_pages'))}</s-paragraph>`);

        loadAiTrackerTimeseries();
        loadAiTrackerRecommendations();
    }

    async function loadAiTrackerTimeseries() {
        const data = await Fyn.api('/ai-tracker/timeseries');
        if (data.error || data.success === false) {
            Fyn.html('ait-timeseries', Fyn.errorBanner(data));
            return;
        }
        const points = data.points || [];
        if (!points.length) {
            Fyn.html('ait-timeseries', `<s-paragraph>${esc(t('no_trends'))}</s-paragraph>`);
            return;
        }
        const tone = n => (n > 0 ? 'success' : n < 0 ? 'critical' : 'neutral');
        Fyn.html('ait-timeseries', `<s-stack gap="base">
                <s-text color="subdued">${esc(t('trends_help'))}</s-text>
                ${Fyn.table(
                    [t('col_period'), { label: t('col_delta_mentions'), format: 'numeric' }, { label: t('col_delta_volume'), format: 'numeric' }],
                    points.slice().reverse().map(p => [
                        esc(Fyn.fmtDate(p.date)),
                        Fyn.badge(tone(p.mentions), Fyn.signed(p.mentions)),
                        esc(Fyn.signed(p.ai_search_volume)),
                    ])
                )}</s-stack>`);
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
        const entries = Object.entries(results);
        const mentioned = entries.filter(([, r]) => r && r.brand_mentioned === true).length;
        const checked = entries.filter(([, r]) => r && !r.error).length;
        Fyn.html('ait-check-result', `<s-stack gap="base">
                ${checked ? Fyn.banner(mentioned ? 'success' : 'warning', t('check_summary', { mentioned, total: checked })) : ''}
                ${entries.map(([provider, result]) => Fyn.answerCard(PROVIDERS[provider] || provider.charAt(0).toUpperCase() + provider.slice(1), result)).join('')}
            </s-stack>`);
    };

    Fyn.page(async () => {
        loadAiTrackerOverview();
    });
})();
