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
        Fyn.html('kwe-results', data.error ? Fyn.errorBanner(data) : Fyn.json(data.clusters || data));
    };

    async function lookup(button, inputId, resultId, path) {
        const keywords = splitKeywords(Fyn.val(inputId));
        if (!keywords.length) {
            Fyn.toast(t('kwe_enter_keywords'), true);
            return;
        }
        Fyn.html(resultId, Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post(path, { keywords }));
        Fyn.html(resultId, data.error ? Fyn.errorBanner(data) : Fyn.json(data.data || data));
    }

    window.lookupVolume = button => lookup(button, 'kwe-volume-input', 'kwe-volume-result', '/keywords/volume');
    window.lookupTrends = button => lookup(button, 'kwe-trends-input', 'kwe-trends-result', '/keywords/trends');

    Fyn.page(async (ctx) => {
        // Default to the store's content language when the explorer offers it.
        const lang = ctx && ctx.onboarding && ctx.onboarding.language;
        if (lang && document.querySelector(`#kwe-language s-option[value="${CSS.escape(lang)}"]`)) {
            Fyn.setVal('kwe-language', lang);
        }
    });
})();
