/*
 * Keyword explorer: related keywords, clustering, volume and trends.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    let lastKeywords = [];

    const splitKeywords = raw => raw.split(',').map(k => k.trim()).filter(Boolean);

    window.exploreKeywords = async function (button) {
        const seed = Fyn.val('kwe-seed').trim();
        if (!seed) {
            Fyn.toast(t('kwe_enter_seed'), true);
            return;
        }
        Fyn.show('kwe-results-card', true);
        Fyn.html('kwe-results', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post('/keywords/explore', {
            seed,
            country: Fyn.val('kwe-country'),
            language: Fyn.val('kwe-language'),
        }));
        if (data.error) {
            Fyn.html('kwe-results', Fyn.errorBanner(data));
            return;
        }
        const keywords = Array.isArray(data.keywords || data.data) ? (data.keywords || data.data) : [];
        lastKeywords = keywords.map(k => typeof k === 'string' ? k : (k.keyword || k.term || '')).filter(Boolean);
        if (!keywords.length) {
            Fyn.html('kwe-results', `<s-paragraph>${esc(t('kwe_none'))}</s-paragraph>`);
            return;
        }
        Fyn.html('kwe-results', Fyn.table(
            [t('kwe_col_keyword'), { label: t('kwe_col_volume'), format: 'numeric' }, { label: t('kwe_col_cpc'), format: 'numeric' }, { label: t('kwe_col_difficulty'), format: 'numeric' }],
            keywords.map(k => {
                const kw = typeof k === 'string' ? { keyword: k } : k;
                return [
                    esc(kw.keyword || kw.term || ''),
                    esc(kw.search_volume ?? kw.volume ?? '-'),
                    esc(kw.cpc ? '$' + Number(kw.cpc).toFixed(2) : '-'),
                    esc(kw.difficulty ?? kw.keyword_difficulty ?? '-'),
                ];
            })
        ));
    };

    window.clusterKeywords = async function (button) {
        if (lastKeywords.length < 2) {
            Fyn.toast(t('kwe_cluster_first'), true);
            return;
        }
        Fyn.html('kwe-results', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post('/keywords/cluster', { keywords: lastKeywords }));
        if (data.error || data.success === false) {
            Fyn.html('kwe-results', Fyn.errorBanner(data));
            return;
        }
        const clusters = (data.clusters || []).filter(c => Array.isArray(c) && c.length);
        const groups = clusters.filter(c => c.length > 1);
        const singles = clusters.filter(c => c.length === 1).map(c => c[0]);
        const badges = list => `<s-stack direction="inline" gap="small-200">${list.map(k => Fyn.badge('neutral', k)).join('')}</s-stack>`;
        Fyn.html('kwe-results', `<s-stack gap="base">
                <s-text color="subdued">${esc(t('kwe_cluster_summary', { keywords: Fyn.num(data.total_keywords), groups: Fyn.num(groups.length) }))}</s-text>
                ${groups.map((c, i) => `<s-box padding="base" border="base" borderRadius="base"><s-stack gap="small-200">
                    <s-text type="strong">${esc(t('kwe_cluster_title', { n: i + 1, main: c[0], count: c.length }))}</s-text>${badges(c)}</s-stack></s-box>`).join('')}
                ${singles.length ? `<s-stack gap="small-200"><s-text type="strong">${esc(t('kwe_cluster_single'))}</s-text>${badges(singles)}</s-stack>` : ''}
            </s-stack>`);
    };

    function trendsView(data) {
        const keywords = data.keywords || [];
        const points = data.points || [];
        if (!keywords.length || !points.length) return `<s-paragraph>${esc(t('no_results'))}</s-paragraph>`;
        const series = i => points.map(p => (p.values || [])[i]);
        let html = `<s-text color="subdued">${esc(t('kwe_trends_help'))}</s-text>`;
        html += Fyn.table(
            [t('col_keyword'), { label: t('kwe_trends_avg'), format: 'numeric' }, t('col_trend')],
            keywords.map((k, i) => [esc(k), esc(Fyn.num((data.averages || [])[i])), esc(Fyn.spark(series(i)) || '-')])
        );
        html += Fyn.table(
            [t('kwe_trends_period'), ...keywords.map(k => ({ label: k, format: 'numeric' }))],
            points.slice().reverse().map(p => [esc(Fyn.fmtDate(p.date)), ...keywords.map((k, i) => esc(Fyn.num((p.values || [])[i])))])
        );
        const queries = (title, list, rising) => list && list.length
            ? `<s-stack gap="small-200"><s-text type="strong">${esc(title)}</s-text><s-stack direction="inline" gap="small-200">${list.map(q => Fyn.badge(rising ? 'success' : 'neutral', rising && q.value ? `${q.query} +${q.value}%` : q.query)).join('')}</s-stack></s-stack>`
            : '';
        html += queries(t('kwe_trends_top'), data.top_queries, false) + queries(t('kwe_trends_rising'), data.rising_queries, true);
        return `<s-stack gap="base">${html}</s-stack>`;
    }

    async function lookup(button, inputId, resultId, path, render) {
        const keywords = splitKeywords(Fyn.val(inputId));
        if (!keywords.length) {
            Fyn.toast(t('kwe_enter_keywords'), true);
            return;
        }
        Fyn.html(resultId, Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post(path, { keywords }));
        Fyn.html(resultId, data.error || data.success === false ? Fyn.errorBanner(data) : render(data));
    }

    window.lookupVolume = button => lookup(button, 'kwe-volume-input', 'kwe-volume-result', '/keywords/volume', data => Fyn.keywordVolumeTable(data.keywords));
    window.lookupTrends = button => lookup(button, 'kwe-trends-input', 'kwe-trends-result', '/keywords/trends', trendsView);

    Fyn.page(async (ctx) => {
        // Default to the store's content language when the explorer offers it.
        const lang = ctx && ctx.onboarding && ctx.onboarding.language;
        if (lang && document.querySelector(`#kwe-language s-option[value="${CSS.escape(lang)}"]`)) {
            Fyn.setVal('kwe-language', lang);
        }
    });
})();
