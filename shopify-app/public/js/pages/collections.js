/*
 * Collection pages (2.5): keyword ideas with volume, AI intro + FAQ per
 * collection, edit and save; the theme app blocks show them on the store.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    let current = null;

    const lines = id => Fyn.val(id).split(/\r?\n/).map(s => s.trim()).filter(Boolean);

    function faqToText(faq) {
        return (faq || []).map(i => `${i.question}\n${i.answer}`).join('\n\n');
    }

    function textToFaq(text) {
        return text.split(/\n\s*\n/).map(block => {
            const [question, ...answer] = block.split('\n').map(s => s.trim()).filter(Boolean);
            return { question: question || '', answer: answer.join(' ') };
        }).filter(i => i.question && i.answer);
    }

    async function loadList() {
        const data = await Fyn.api('/collection-pages');
        if (data.error) {
            Fyn.html('cl-list', Fyn.errorBanner(data));
            return;
        }
        Fyn.html('cl-list', data.collections.length
            ? Fyn.table([t('col_collection'), { label: t('col_products'), format: 'numeric' }, t('col_status'), t('col_action')], data.collections.map(c => [
                esc(c.title),
                esc(c.products),
                Fyn.badge(c.has_landing ? 'success' : 'neutral', t(c.has_landing ? 'has_content' : 'no_content')),
                `<s-button variant="tertiary" data-id="${esc(c.id)}" data-title="${esc(c.title)}" onclick="openLanding(this.dataset.id, this.dataset.title)">${esc(t(c.has_landing ? 'edit' : 'create'))}</s-button>`,
            ]))
            : `<s-text color="subdued">${esc(t('empty'))}</s-text>`);
    }

    async function blockStatus() {
        const [intro, faq] = await Promise.all([Fyn.embedStatus('fyndable-collection-intro'), Fyn.embedStatus('fyndable-collection-faq')]);
        if (intro === null && faq === null) return;
        const active = intro === 'active' || faq === 'active';
        Fyn.html('cl-blocks', Fyn.banner(active ? 'success' : 'warning', t(active ? 'blocks_active' : 'blocks_missing')));
    }

    window.openLanding = async function (id, title) {
        current = id;
        Fyn.$('cl-modal').setAttribute('heading', title);
        ['cl-keywords', 'cl-intro', 'cl-faq'].forEach(f => Fyn.setVal(f, ''));
        Fyn.html('cl-suggestions', '');
        Fyn.html('cl-status', Fyn.loading());
        Fyn.$('cl-modal').showOverlay();
        const data = await Fyn.api(`/collection-pages/${encodeURIComponent(id)}`);
        if (data.error) {
            Fyn.html('cl-status', Fyn.errorBanner(data));
            return;
        }
        Fyn.html('cl-status', '');
        if (data.landing) {
            Fyn.setVal('cl-keywords', (data.landing.keywords || []).join('\n'));
            Fyn.setVal('cl-intro', data.landing.intro_html || '');
            Fyn.setVal('cl-faq', faqToText(data.landing.faq));
        }
    };

    window.suggestKeywords = async function (button) {
        Fyn.html('cl-suggestions', `<s-text color="subdued">${esc(t('suggesting'))}</s-text>`);
        const data = await Fyn.busy(button, () => Fyn.post(`/collection-pages/${encodeURIComponent(current)}/keywords`));
        if (data.error) {
            Fyn.html('cl-suggestions', Fyn.errorBanner(data));
            return;
        }
        Fyn.html('cl-suggestions', `<s-stack gap="small-200">${data.keywords.map(k => `
            <s-checkbox class="cl-kw" value="${esc(k.keyword)}" label="${esc(k.keyword)}" details="${esc(k.volume === null ? t('no_volume') : t('volume', { volume: k.volume }))}" onchange="syncKeywords()"></s-checkbox>`).join('')}</s-stack>`);
    };

    window.syncKeywords = function () {
        const chosen = [...document.querySelectorAll('#cl-suggestions .cl-kw')].filter(el => el.checked).map(el => el.getAttribute('value'));
        const existing = lines('cl-keywords').filter(k => !chosen.includes(k));
        Fyn.setVal('cl-keywords', [...existing, ...chosen].slice(0, 5).join('\n'));
    };

    window.writeLanding = async function (button) {
        Fyn.html('cl-status', `<s-text color="subdued">${esc(t('writing'))}</s-text>`);
        const data = await Fyn.busy(button, () => Fyn.post(`/collection-pages/${encodeURIComponent(current)}/generate`, { keywords: lines('cl-keywords') }));
        if (data.error) {
            Fyn.html('cl-status', Fyn.errorBanner(data));
            return;
        }
        Fyn.html('cl-status', '');
        Fyn.setVal('cl-intro', data.landing.intro_html);
        Fyn.setVal('cl-faq', faqToText(data.landing.faq));
    };

    window.saveLanding = async function (button) {
        const body = { intro_html: Fyn.val('cl-intro'), faq: textToFaq(Fyn.val('cl-faq')), keywords: lines('cl-keywords') };
        const data = await Fyn.busy(button, () => Fyn.post(`/collection-pages/${encodeURIComponent(current)}/save`, body));
        if (data.error) {
            Fyn.html('cl-status', data.error === 'nothing_to_save' ? Fyn.banner('warning', t('nothing_to_save')) : Fyn.errorBanner(data));
            return;
        }
        Fyn.$('cl-modal').hideOverlay();
        Fyn.toast(t('saved'));
        loadList();
    };

    Fyn.page(async () => {
        await Promise.all([loadList(), blockStatus()]);
    });
})();
