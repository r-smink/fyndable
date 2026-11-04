/*
 * llms.txt: generator settings, regenerate, preview and root redirects.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    const kb = bytes => ((Number(bytes) || 0) / 1024).toFixed(1);

    async function loadLlmsTxtStatus() {
        const data = await Fyn.api('/llmstxt/status');
        if (data.error) {
            Fyn.html('llmstxt-status', Fyn.errorBanner(data));
            return;
        }
        Fyn.setChecked('llmstxt-enabled', data.enabled);
        Fyn.setChecked('llmstxt-full-enabled', data.full_enabled);
        Fyn.setChecked('llmstxt-products', data.include_products);
        Fyn.setChecked('llmstxt-collections', data.include_collections);
        Fyn.setChecked('llmstxt-pages', data.include_pages);
        Fyn.setChecked('llmstxt-blogs', data.include_blogs);
        Fyn.setVal('llmstxt-max-products', data.max_products);
        Fyn.setVal('llmstxt-max-chars', data.full_max_chars);
        Fyn.setVal('llmstxt-description', data.description || '');
        Fyn.setVal('llmstxt-custom', data.custom_sections || '');
        Fyn.html('llmstxt-status', Fyn.banner('info', t('sizes', { summary: kb(data.summary_size), full: kb(data.full_size) })));
    }

    window.saveLlmsTxtSettings = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/llmstxt/settings', {
            enabled: Fyn.checked('llmstxt-enabled'),
            full_enabled: Fyn.checked('llmstxt-full-enabled'),
            include_products: Fyn.checked('llmstxt-products'),
            include_collections: Fyn.checked('llmstxt-collections'),
            include_pages: Fyn.checked('llmstxt-pages'),
            include_blogs: Fyn.checked('llmstxt-blogs'),
            max_products: parseInt(Fyn.val('llmstxt-max-products'), 10),
            full_max_chars: parseInt(Fyn.val('llmstxt-max-chars'), 10),
            description: Fyn.val('llmstxt-description'),
            custom_sections: Fyn.val('llmstxt-custom'),
        }));
        if (data.success) {
            Fyn.toast(t('settings_saved'));
            loadLlmsTxtStatus();
        } else {
            Fyn.toast(Fyn.errorText(data), true);
        }
    };

    window.regenerateLlmsTxt = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/llmstxt/regenerate'));
        if (data.success) {
            Fyn.toast(t('regenerated', { summary: kb(data.summary_size), full: kb(data.full_size) }));
            loadLlmsTxtStatus();
        } else {
            Fyn.toast(Fyn.errorText(data), true);
        }
    };

    window.previewLlmsTxt = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.api('/llmstxt/preview'));
        if (data.content) {
            Fyn.$('llmstxt-preview').textContent = data.content;
        } else {
            Fyn.toast(data.error ? Fyn.errorText(data) : t('no_content'), true);
        }
    };

    window.setupLlmsTxtRedirects = async function (button) {
        Fyn.html('llmstxt-redirect-status', `<s-text color="subdued">${esc(t('creating_redirects'))}</s-text>`);
        const data = await Fyn.busy(button, () => Fyn.post('/llmstxt/setup-redirects'));
        if (data.error) {
            Fyn.html('llmstxt-redirect-status', Fyn.errorBanner(data));
            return;
        }
        const rows = (data.redirects || []).map(r => Fyn.row(r.path, '', Fyn.badge('info', r.status))).join('');
        Fyn.html('llmstxt-redirect-status', `<s-stack gap="small-200"><s-text type="strong">${esc(t('redirects_result'))}</s-text>${rows}</s-stack>`);
    };

    Fyn.page(loadLlmsTxtStatus);
})();
