/*
 * Onboarding wizard (0.2): store language, brand voice and the first
 * products. The trial tenant already exists (created on the first app load).
 * Markup: resources/views/dashboard/partials/onboarding.blade.php.
 *
 * Step 3 calls the regular product endpoints, so tier gating and the trial
 * cap apply as usual.
 */
(function () {
    'use strict';

    const { t, esc, $ } = Fyn;
    let state = null;

    function profileFromForm() {
        if (state.step === 1) {
            state.profile.content_language = Fyn.val('onb-lang');
            state.profile.target_country = Fyn.val('onb-country').trim().toUpperCase();
        }
        if (state.step === 2) {
            state.profile.brand_tone = Fyn.val('onb-tone');
            state.profile.brand_audience = Fyn.val('onb-audience').trim();
            state.profile.brand_description = Fyn.val('onb-brand').trim();
        }
    }

    function option(value, label, selected) {
        return `<s-option value="${esc(value)}"${selected ? ' selected' : ''}>${esc(label)}</s-option>`;
    }

    function render() {
        const d = state.data;
        const primary = $('onb-primary');
        let html = '';

        if (state.step === 1) {
            html = `<s-stack gap="base">
                <s-heading>${esc(t('onb_lang_title'))}</s-heading>
                <s-paragraph>${esc(t('onb_lang_intro'))}</s-paragraph>
                <s-select id="onb-lang" label="${esc(t('onb_language'))}" value="${esc(state.profile.content_language)}">
                    ${Object.entries(d.languages).map(([code, name]) => option(code, name, code === state.profile.content_language)).join('')}
                </s-select>
                <s-text-field id="onb-country" label="${esc(t('onb_country'))}" maxLength="2" value="${esc(state.profile.target_country)}"></s-text-field>
            </s-stack>`;
            primary.textContent = t('onb_next');
        } else if (state.step === 2) {
            html = `<s-stack gap="base">
                <s-heading>${esc(t('onb_voice_title'))}</s-heading>
                <s-paragraph>${esc(t('onb_voice_intro'))}</s-paragraph>
                <s-select id="onb-tone" label="${esc(t('onb_tone'))}" value="${esc(state.profile.brand_tone)}">
                    ${d.tones.map(tone => option(tone, t('tone_' + tone), tone === state.profile.brand_tone)).join('')}
                </s-select>
                <s-text-field id="onb-audience" label="${esc(t('onb_audience'))}" maxLength="255" placeholder="${esc(t('onb_audience_ph'))}" value="${esc(state.profile.brand_audience)}"></s-text-field>
                <s-text-area id="onb-brand" label="${esc(t('onb_brand'))}" rows="3" maxLength="1000" placeholder="${esc(t('onb_brand_ph'))}" value="${esc(state.profile.brand_description)}"></s-text-area>
            </s-stack>`;
            primary.textContent = t('onb_next');
        } else if (state.step === 3) {
            const products = d.products || [];
            const billing = Fyn.ctx && Fyn.ctx.billing;
            const trialNote = billing && billing.state === 'trial'
                ? `<s-banner tone="info">${esc(t('onb_trial_note', { max: d.trial.max, used: d.trial.used }))}</s-banner>` : '';
            const list = products.length
                ? products.map(p => `<s-stack direction="inline" gap="base" alignItems="center" data-id="${esc(p.id)}" class="onb-product">
                        <s-checkbox value="${esc(p.id)}" checked accessibilityLabel="${esc(p.title)}"></s-checkbox>
                        ${p.image ? `<img class="fyn-product-thumb" src="${esc(p.image)}" alt="">` : '<span class="fyn-product-thumb"></span>'}
                        <s-text>${esc(p.title)}</s-text>
                        <s-text color="subdued" class="onb-status"></s-text>
                    </s-stack>`).join('')
                : `<s-paragraph>${esc(t('onb_no_products'))}</s-paragraph>`;
            html = `<s-stack gap="base">
                <s-heading>${esc(t('onb_products_title'))}</s-heading>
                <s-paragraph>${esc(t('onb_products_intro'))}</s-paragraph>
                ${trialNote}${list}
            </s-stack>`;
            primary.textContent = products.length ? t('onb_optimize') : t('onb_finish');
        } else {
            html = `<s-stack gap="base">
                <s-heading>${esc(t('onb_done_title'))}</s-heading>
                <s-paragraph>${esc(t('onb_done_text', state.results))}</s-paragraph>
            </s-stack>`;
            primary.textContent = t('onb_finish');
        }

        $('onb-steplabel').textContent = state.step <= 3 ? t('onb_step', { n: state.step }) : '';
        Fyn.show('onb-back', state.step === 2 || state.step === 3);
        Fyn.show('onb-skip', state.step <= 3);
        primary.disabled = false;
        $('onb-body').innerHTML = html;
    }

    async function optimizeProducts() {
        const rows = [...document.querySelectorAll('#onb-body .onb-product')]
            .filter(row => row.querySelector('s-checkbox').checked);
        if (!rows.length) return;
        $('onb-primary').loading = true;
        let ok = 0;
        for (const row of rows) {
            const num = row.dataset.id.split('/').pop();
            const status = row.querySelector('.onb-status');
            status.textContent = t('onb_working');
            const meta = await Fyn.post(`/products/${num}/generate-meta`);
            if (meta.error) {
                status.textContent = t('onb_failed');
                // Trial used up or expired: Fyn.api already sent the merchant to the plan page.
                if (meta.error === 'trial_exhausted' || meta.error === 'trial_expired') break;
                continue;
            }
            const saved = await Fyn.post(`/products/${num}/save-meta`, { title: meta.title, description: meta.description });
            status.textContent = saved.success ? t('onb_ok') : t('onb_failed');
            if (saved.success) ok++;
        }
        $('onb-primary').loading = false;
        state.results = { ok, total: rows.length };
        await Fyn.post('/onboarding/complete');
        state.step = 4;
        render();
    }

    const FynOnboarding = {
        /** Called after finishing or skipping (home page reloads its overview). */
        onDone: null,

        /** Open from a click handler: the modal shows first, then loads. */
        async open() {
            const modal = $('onb-modal');
            $('onb-body').innerHTML = Fyn.loading();
            $('onb-steplabel').textContent = '';
            Fyn.show('onb-back', false);
            $('onb-primary').disabled = true;
            modal.showOverlay();

            const params = new URLSearchParams(location.search);
            const data = await Fyn.api('/onboarding' + (params.get('locale') ? `?locale=${encodeURIComponent(params.get('locale'))}` : ''));
            if (data.error) {
                $('onb-body').innerHTML = Fyn.errorBanner(data);
                return;
            }
            const p = data.profile || {};
            state = {
                step: 1,
                data,
                profile: {
                    content_language: p.content_language || data.suggestions?.content_language || Fyn.cfg.lang,
                    target_country: p.target_country || data.suggestions?.target_country || '',
                    brand_tone: p.brand_tone || 'friendly',
                    brand_audience: p.brand_audience || '',
                    brand_description: p.brand_description || '',
                },
                results: {},
            };
            render();
        },

        async primary() {
            if (!state) return;
            if (state.step === 1 || state.step === 2) {
                profileFromForm();
                if (state.step === 2) {
                    const saved = await Fyn.busy($('onb-primary'), () => Fyn.post('/onboarding/profile', state.profile));
                    if (saved.error) {
                        Fyn.toast(t('onb_save_failed'), true);
                        return;
                    }
                }
                state.step++;
                render();
            } else if (state.step === 3) {
                if ((state.data.products || []).length) {
                    await optimizeProducts();
                } else {
                    await FynOnboarding.finish();
                }
            } else {
                $('onb-modal').hideOverlay();
                if (FynOnboarding.onDone) FynOnboarding.onDone();
            }
        },

        back() {
            if (!state) return;
            profileFromForm();
            state.step = Math.max(1, state.step - 1);
            render();
        },

        async skip() {
            await Fyn.post('/onboarding/skip');
            $('onb-modal').hideOverlay();
            if (FynOnboarding.onDone) FynOnboarding.onDone();
        },

        async finish() {
            await Fyn.post('/onboarding/complete');
            $('onb-modal').hideOverlay();
            if (FynOnboarding.onDone) FynOnboarding.onDone();
        },
    };

    window.FynOnboarding = FynOnboarding;
})();
