/*
 * Settings: content language, main country and brand voice. The same store
 * profile as the onboarding wizard; BrandVoice::apply() adds it to every AI
 * prompt.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;

    function options(id, entries, selected) {
        const el = Fyn.$(id);
        el.innerHTML = entries.map(([value, label]) =>
            `<s-option value="${esc(value)}"${value === selected ? ' selected' : ''}>${esc(label)}</s-option>`).join('');
        el.value = selected;
    }

    async function loadSettings() {
        const data = await Fyn.api('/settings/profile');
        if (data.error) {
            Fyn.html('fyn-banners', Fyn.errorBanner(data));
            return;
        }
        const p = data.profile || {};
        const language = p.content_language || data.suggestions?.content_language || Fyn.cfg.lang;
        options('set-language', Object.entries(data.languages), language);
        options('set-tone', [['', '-'], ...data.tones.map(tone => [tone, t('tone_' + tone)])], p.brand_tone || '');
        Fyn.setVal('set-country', p.target_country || data.suggestions?.target_country || '');
        Fyn.setVal('set-audience', p.brand_audience || '');
        Fyn.setVal('set-brand', p.brand_description || '');
    }

    window.saveSettings = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/settings/profile', {
            content_language: Fyn.val('set-language'),
            target_country: Fyn.val('set-country').trim().toUpperCase(),
            brand_tone: Fyn.val('set-tone') || null,
            brand_audience: Fyn.val('set-audience').trim(),
            brand_description: Fyn.val('set-brand').trim(),
        }));
        if (data.error) {
            Fyn.toast(t('save_failed'), true);
            return;
        }
        Fyn.toast(t('settings_saved'));
    };

    FynOnboarding.onDone = loadSettings;
    Fyn.page(loadSettings);
})();
