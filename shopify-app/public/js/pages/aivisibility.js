/*
 * AI visibility: live LLM answers, keyword data and AI mentions.
 */
(function () {
    'use strict';

    const { t } = Fyn;

    function failed(data) {
        return data.error || data.success === false;
    }

    window.runLlmCheck = async function (button) {
        const prompt = Fyn.val('llm-prompt').trim();
        if (!prompt) {
            Fyn.toast(t('enter_question'), true);
            return;
        }
        Fyn.html('llm-result', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post('/insights/llm-check', { prompt, provider: Fyn.val('llm-provider') }));
        Fyn.html('llm-result', failed(data) ? Fyn.errorBanner(data) : Fyn.json(data.data ?? data));
    };

    window.loadLlmMentions = async function (button) {
        const target = Fyn.val('llm-mentions-target').trim() || Fyn.cfg.shop;
        Fyn.html('llm-mentions-result', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post('/insights/llm-mentions', { action: 'search_mentions', params: { target } }));
        Fyn.html('llm-mentions-result', failed(data) ? Fyn.errorBanner(data) : Fyn.json(data.data ?? data));
    };

    window.loadKeywordData = async function (button) {
        const keywords = Fyn.val('kw-data-input').split(',').map(k => k.trim()).filter(Boolean);
        if (!keywords.length) {
            Fyn.toast(t('enter_keyword'), true);
            return;
        }
        Fyn.html('kw-data-result', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post('/insights/keyword-data', { keywords }));
        Fyn.html('kw-data-result', failed(data) ? Fyn.errorBanner(data) : Fyn.json(data.data ?? data));
    };

    Fyn.page(async () => {});
})();
