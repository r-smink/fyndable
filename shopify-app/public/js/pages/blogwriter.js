/*
 * Blog writer: generate an article with AI, review it and publish it or save
 * it as a draft. The language defaults to the store language from Settings.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;

    async function loadBlogs() {
        const data = await Fyn.api('/blogs');
        if (data.error) {
            Fyn.html('bw-blog', `<s-option value="">${esc(t('blogs_error'))}</s-option>`);
            return;
        }
        const blogs = data.blogs || [];
        Fyn.html('bw-blog', blogs.length
            ? blogs.map(b => `<s-option value="${esc(b.id.split('/').pop())}">${esc(b.title)}</s-option>`).join('')
            : `<s-option value="">${esc(t('no_blogs'))}</s-option>`);
        if (blogs.length) Fyn.setVal('bw-blog', blogs[0].id.split('/').pop());
    }

    window.generateArticle = async function (button) {
        const topic = Fyn.val('bw-topic').trim();
        if (!topic) {
            Fyn.toast(t('topic_required'), true);
            return;
        }
        Fyn.html('bw-result', `<s-banner tone="info">${esc(t('writing'))}</s-banner>`);
        const data = await Fyn.busy(button, () => Fyn.post('/articles/generate', {
            topic,
            keywords: Fyn.val('bw-keywords'),
            word_count: parseInt(Fyn.val('bw-words'), 10) || 800,
            language: Fyn.val('bw-lang'),
        }));
        if (!data.success) {
            Fyn.html('bw-result', Fyn.errorBanner(data));
            return;
        }
        Fyn.setVal('bw-title', data.title);
        Fyn.setVal('bw-body', data.body_html);
        Fyn.show('bw-draft', true);
        Fyn.show('bw-empty', false);
        Fyn.html('bw-result', '');
        Fyn.toast(t('draft_ready'));
    };

    window.createArticle = async function (button, publish) {
        const blogId = Fyn.val('bw-blog');
        const title = Fyn.val('bw-title').trim();
        const body = Fyn.val('bw-body');
        if (!title || !body) {
            Fyn.toast(t('title_body_required'), true);
            return;
        }
        const data = await Fyn.busy(button, () => Fyn.post(`/blogs/${blogId}/articles`, {
            title,
            body_html: body,
            publish,
            author: Fyn.val('bw-author').trim(),
            tags: Fyn.val('bw-tags').split(',').map(s => s.trim()).filter(Boolean),
        }));
        if (data.success) {
            Fyn.html('bw-result', '');
            Fyn.toast(publish ? t('published') : t('draft_saved'));
        } else {
            Fyn.html('bw-result', Fyn.errorBanner(data));
        }
    };

    Fyn.page(async (ctx) => {
        // Store language from Settings / onboarding preselects the language.
        const lang = ctx && ctx.onboarding && ctx.onboarding.language;
        if (lang && Fyn.$('bw-lang').querySelector(`s-option[value="${lang}"]`)) {
            Fyn.setVal('bw-lang', lang);
        }
        await loadBlogs();
    });
})();
