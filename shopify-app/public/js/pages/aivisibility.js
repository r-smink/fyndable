/*
 * AI visibility: live LLM answers, keyword data and AI mentions.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    const PROVIDERS = { chatgpt: 'ChatGPT', claude: 'Claude', gemini: 'Gemini', perplexity: 'Perplexity' };
    const PLATFORMS = { chat_gpt: 'ChatGPT', google: 'Google AI Overview' };

    function failed(data) {
        return data.error || data.success === false;
    }

    window.runLlmCheck = async function (button) {
        const prompt = Fyn.val('llm-prompt').trim();
        if (!prompt) {
            Fyn.toast(t('enter_question'), true);
            return;
        }
        const provider = Fyn.val('llm-provider');
        Fyn.html('llm-result', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post('/insights/llm-check', { prompt, provider }));
        Fyn.html('llm-result', failed(data) ? Fyn.errorBanner(data) : Fyn.answerCard(PROVIDERS[data.provider || provider] || provider, data));
    };

    window.loadLlmMentions = async function (button) {
        const target = Fyn.val('llm-mentions-target').trim() || Fyn.cfg.shop;
        Fyn.html('llm-mentions-result', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post('/insights/llm-mentions', { action: 'search_mentions', params: { target } }));
        if (failed(data)) {
            Fyn.html('llm-mentions-result', Fyn.errorBanner(data));
            return;
        }
        const items = data.items || [];
        if (!items.length) {
            Fyn.html('llm-mentions-result', `<s-paragraph>${esc(t('mentions_none', { target }))}</s-paragraph>`);
            return;
        }
        Fyn.html('llm-mentions-result', `<s-stack gap="base">
                <s-text color="subdued">${esc(t('mentions_found', { count: Fyn.num(data.total), target }))}</s-text>
                ${items.map(m => `<s-box padding="base" border="base" borderRadius="base"><s-stack gap="small-200">
                    <s-text type="strong">${esc(m.question)}</s-text>
                    <s-stack direction="inline" gap="small-200">
                        ${m.platform ? Fyn.badge('info', PLATFORMS[m.platform] || m.platform) : ''}
                        ${m.ai_search_volume !== null && m.ai_search_volume !== undefined ? Fyn.badge('neutral', t('mentions_volume', { volume: Fyn.num(m.ai_search_volume) })) : ''}
                    </s-stack>
                    ${m.answer ? Fyn.markdown(m.answer) : ''}
                    ${Fyn.sources(m.sources)}
                </s-stack></s-box>`).join('')}
            </s-stack>`);
    };

    window.loadKeywordData = async function (button) {
        const keywords = Fyn.val('kw-data-input').split(',').map(k => k.trim()).filter(Boolean);
        if (!keywords.length) {
            Fyn.toast(t('enter_keyword'), true);
            return;
        }
        Fyn.html('kw-data-result', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post('/insights/keyword-data', { keywords }));
        Fyn.html('kw-data-result', failed(data) ? Fyn.errorBanner(data) : Fyn.keywordVolumeTable(data.keywords));
    };

    Fyn.page(async () => {});
})();
