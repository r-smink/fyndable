/*
 * Content optimizer: AI meta tags, descriptions, alt text, JSON-LD schema,
 * FAQ schema, internal links and AI product images for products,
 * collections, pages and blog articles.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    const EDITOR_SECTIONS = ['content-editor-head', 'sec-meta', 'sec-description', 'sec-schema', 'sec-faq', 'sec-links'];
    const PRODUCT_SECTIONS = ['alt-text-section', 'ai-image-section', 'push-all-section'];

    let currentType = 'product';
    let currentId = null;
    let linkSuggestions = [];
    let descriptionFormat = 'html';
    let currentGsc = null;

    function result(html) {
        Fyn.html('content-result', html);
    }

    function fail(data) {
        result(Fyn.errorBanner(data));
    }

    function ok(message) {
        result('');
        Fyn.toast(message);
    }

    function numericId() {
        return currentId ? currentId.split('/').pop() : null;
    }

    function requireItem() {
        if (currentId) return true;
        Fyn.toast(t('select_item_first'), true);
        return false;
    }

    function showEditor(visible) {
        EDITOR_SECTIONS.forEach(id => Fyn.show(id, visible));
        PRODUCT_SECTIONS.forEach(id => Fyn.show(id, visible && currentType === 'product'));
        Fyn.show('content-empty', !visible);
    }

    async function searchContent() {
        const search = Fyn.val('content-search');
        Fyn.html('content-list', Fyn.loading());
        const data = await Fyn.api(`/content/${currentType}?search=${encodeURIComponent(search)}&limit=50`);
        if (data.error) {
            Fyn.html('content-list', '');
            fail(data);
            return;
        }
        if (!data.items.length) {
            Fyn.html('content-list', `<s-text color="subdued">${esc(t('no_items'))}</s-text>`);
            return;
        }
        // One row per item: title + type on the left, status and score badges
        // on the right. Text wraps instead of widening the page.
        const status = s => (s === 'DRAFT' || s === 'ARCHIVED') ? Fyn.badge('neutral', t('status_' + s.toLowerCase())) : '';
        Fyn.html('content-list', `<s-stack gap="small-100">${data.items.map(i => `
            <s-clickable padding="small" borderRadius="base" data-id="${esc(i.id)}" onclick="selectContent(this.dataset.id)" accessibilityLabel="${esc(i.title)}">
                <s-stack direction="inline" gap="small-200" justifyContent="space-between" alignItems="center">
                    <s-stack gap="none">
                        <s-text type="strong">${esc(i.title)}</s-text>
                        ${i.subtitle ? `<s-text color="subdued">${esc(i.subtitle)}</s-text>` : ''}
                    </s-stack>
                    <s-stack direction="inline" gap="small-200">${status(i.status || '')}${Fyn.scoreBadge(i.score)}</s-stack>
                </s-stack>
            </s-clickable>`).join('')}
        </s-stack>`);
    }

    window.searchContent = searchContent;

    window.onContentTypeChange = function () {
        currentType = Fyn.val('content-type') || 'product';
        currentId = null;
        showEditor(false);
        result('');
        searchContent();
    };

    window.selectContent = async function (id) {
        currentId = id;
        result(Fyn.loading());
        const data = await Fyn.api(`/content/${currentType}/${numericId()}`);
        if (data.error) {
            fail(data);
            return;
        }
        const item = data.item;
        Fyn.$('content-selected').textContent = item.title + (item.url ? ' - ' + item.url : '');
        currentGsc = item.gsc || null;
        renderSeo(item.seo);
        Fyn.setVal('edit-meta-title', item.seo_title || '');
        Fyn.setVal('edit-meta-desc', item.seo_description || '');
        Fyn.setVal('edit-description', item.body || '');
        descriptionFormat = 'html';
        Fyn.setVal('edit-schema', item.schema ? JSON.stringify(item.schema, null, 2) : '');
        Fyn.setVal('edit-alt-text', '');
        const images = item.images || [];
        Fyn.html('edit-image', images.map(i =>
            `<s-option value="${esc(i.id)}" data-url="${esc(i.url)}">${esc(i.alt || i.url.split('/').pop())}</s-option>`).join(''));
        if (images.length) Fyn.setVal('edit-image', images[0].id);
        Fyn.html('link-suggestions', '');
        Fyn.html('ai-image-result', '');
        showEditor(true);
        result('');
    };

    // Live SEO score of the open item (SeoAuditor item checks, 1.1).
    function renderSeo(seo) {
        if (!seo) {
            Fyn.html('content-seo', '');
            return;
        }
        const issues = seo.issues.length
            ? `<s-unordered-list>${seo.issues.map(i => `<s-list-item>${esc(Fyn.issueLabel(i))}</s-list-item>`).join('')}</s-unordered-list>`
            : `<s-text color="subdued">${esc(t('no_issues'))}</s-text>`;
        const gsc = currentGsc
            ? `<s-text color="subdued">${esc(t('gsc_line', { clicks: currentGsc.clicks, impressions: currentGsc.impressions, position: currentGsc.position }))}</s-text>`
            : '';
        Fyn.html('content-seo', `<s-stack gap="small-200">
                <s-stack direction="inline" gap="small-200" alignItems="center"><s-text type="strong">${esc(t('seo_score'))}</s-text>${Fyn.scoreBadge(seo.score)}</s-stack>
                ${gsc}${issues}</s-stack>`);
    }

    function selectedImageUrl() {
        const id = Fyn.val('edit-image');
        const opt = [...Fyn.$('edit-image').querySelectorAll('s-option')].find(o => o.getAttribute('value') === id);
        return opt ? opt.dataset.url : '';
    }

    window.generateDescription = async function (button, type) {
        if (!requireItem()) return;
        const data = await Fyn.busy(button, () => Fyn.post(`/content/${currentType}/${numericId()}/generate-description`, {
            type, context: Fyn.val('product-context'),
        }));
        if (!data.success) return fail(data);
        Fyn.setVal('edit-description', data.description);
        descriptionFormat = 'text';
        ok(t('description_generated'));
    };

    window.saveDescription = async function (button) {
        if (!requireItem()) return;
        const value = Fyn.val('edit-description');
        if (!value.trim()) {
            Fyn.toast(t('nothing_to_save'), true);
            return;
        }
        const data = await Fyn.busy(button, () => Fyn.post(`/content/${currentType}/${numericId()}/save-description`, {
            description: value, format: descriptionFormat || 'text',
        }));
        data.success ? ok(t('description_saved')) : fail(data);
    };

    window.generateMeta = async function (button) {
        if (!requireItem()) return;
        const data = await Fyn.busy(button, () => Fyn.post(`/content/${currentType}/${numericId()}/generate-meta`, {
            context: Fyn.val('product-context'),
        }));
        if (!data.success) return fail(data);
        Fyn.setVal('edit-meta-title', data.title || '');
        Fyn.setVal('edit-meta-desc', data.description || '');
        ok(t('meta_generated'));
    };

    window.saveMeta = async function (button) {
        if (!requireItem()) return;
        const data = await Fyn.busy(button, () => Fyn.post(`/content/${currentType}/${numericId()}/save-meta`, {
            title: Fyn.val('edit-meta-title'),
            description: Fyn.val('edit-meta-desc'),
        }));
        data.success ? ok(t('meta_saved')) : fail(data);
    };

    window.generateAltText = async function (button) {
        if (!requireItem()) return;
        const imageUrl = selectedImageUrl();
        if (!imageUrl) {
            Fyn.toast(t('no_image'), true);
            return;
        }
        const data = await Fyn.busy(button, () => Fyn.post(`/products/${numericId()}/generate-alt-text`, { image_url: imageUrl }));
        if (!data.success) return fail(data);
        Fyn.setVal('edit-alt-text', data.alt_text);
        ok(t('alt_generated'));
    };

    window.saveAltText = async function (button) {
        if (!requireItem()) return;
        const imageId = Fyn.val('edit-image');
        const altText = Fyn.val('edit-alt-text');
        if (!imageId || !altText) {
            Fyn.toast(t('alt_missing'), true);
            return;
        }
        const data = await Fyn.busy(button, () => Fyn.post(`/products/${numericId()}/push-alt-text`, { image_id: imageId, alt_text: altText }));
        data.success ? ok(t('alt_saved')) : fail(data);
    };

    window.generateSchema = async function (button) {
        if (!requireItem()) return;
        const data = await Fyn.busy(button, () => Fyn.post(`/content/${currentType}/${numericId()}/generate-schema`));
        if (!data.success) return fail(data);
        Fyn.setVal('edit-schema', JSON.stringify(data.schema, null, 2));
        ok(t('schema_generated'));
    };

    window.saveSchema = async function (button) {
        if (!requireItem()) return;
        const raw = Fyn.val('edit-schema').trim();
        if (!raw) {
            Fyn.toast(t('schema_missing'), true);
            return;
        }
        let schema;
        try {
            schema = JSON.parse(raw);
        } catch (e) {
            result(Fyn.banner('critical', t('invalid_json', { message: e.message })));
            return;
        }
        const data = await Fyn.busy(button, () => Fyn.post(`/content/${currentType}/${numericId()}/save-schema`, { schema }));
        data.success ? ok(t('schema_saved')) : fail(data);
    };

    window.pushAll = async function (button) {
        if (!currentId || currentType !== 'product') {
            Fyn.toast(t('select_product_first'), true);
            return;
        }
        if (!Fyn.checked('product-overwrite')) {
            Fyn.toast(t('confirm_overwrite'), true);
            return;
        }
        let schema = null;
        const rawSchema = Fyn.val('edit-schema').trim();
        if (rawSchema) {
            try {
                schema = JSON.parse(rawSchema);
            } catch (e) {
                result(Fyn.banner('critical', t('invalid_json', { message: e.message })));
                return;
            }
        }
        const data = await Fyn.busy(button, () => Fyn.post(`/products/${numericId()}/push-all`, {
            description: Fyn.val('edit-description'),
            description_format: descriptionFormat || 'text',
            meta_title: Fyn.val('edit-meta-title'),
            meta_description: Fyn.val('edit-meta-desc'),
            image_id: Fyn.val('edit-image'),
            alt_text: Fyn.val('edit-alt-text'),
            schema,
            overwrite: true,
        }));
        if (!data.success) return fail(data);
        result(Fyn.banner('success', t('pushed', { saved: JSON.stringify(data.saved) })));
    };

    window.generateFaq = async function (button) {
        if (!requireItem()) return;
        const data = await Fyn.busy(button, () => Fyn.post(`/content/${currentType}/${numericId()}/generate-faq`, {
            count: parseInt(Fyn.val('faq-count'), 10) || 5,
            context: Fyn.val('product-context'),
        }));
        if (!data.success) return fail(data);
        Fyn.setVal('edit-faq', JSON.stringify(data.faq, null, 2));
        ok(t('faq_generated'));
    };

    window.saveFaq = async function (button) {
        if (!requireItem()) return;
        let pairs;
        try {
            pairs = JSON.parse(Fyn.val('edit-faq'));
        } catch (e) {
            Fyn.toast(t('faq_invalid'), true);
            return;
        }
        const schema = {
            '@context': 'https://schema.org/',
            '@type': 'FAQPage',
            'mainEntity': (Array.isArray(pairs) ? pairs : []).map(p => ({
                '@type': 'Question',
                'name': p.question,
                'acceptedAnswer': { '@type': 'Answer', 'text': p.answer },
            })),
        };
        const data = await Fyn.busy(button, () => Fyn.post(`/content/${currentType}/${numericId()}/save-faq`, { schema }));
        data.success ? ok(t('faq_saved')) : fail(data);
    };

    async function findLinkSuggestions(button) {
        if (!requireItem()) return;
        Fyn.html('link-suggestions', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post(`/content/${currentType}/${numericId()}/link-suggestions`));
        if (data.error) {
            Fyn.html('link-suggestions', '');
            fail(data);
            return;
        }
        linkSuggestions = data.suggestions || [];
        if (!linkSuggestions.length) {
            Fyn.html('link-suggestions', `<s-text color="subdued">${esc(t('no_links'))}</s-text>`);
            return;
        }
        Fyn.html('link-suggestions', linkSuggestions.map((s, i) => Fyn.row(
            `${s.anchor} -> ${s.url}`,
            s.target_type,
            `<s-button onclick="applyLink(this, ${i})">${esc(t('apply'))}</s-button>`
        )).join(''));
    }

    window.findLinkSuggestions = findLinkSuggestions;

    window.applyLink = async function (button, index) {
        const s = linkSuggestions[index];
        if (!s) return;
        const data = await Fyn.busy(button, () => Fyn.post(`/content/${currentType}/${numericId()}/apply-link`, { anchor: s.anchor, url: s.url }));
        if (!data.success) return fail(data);
        ok(t('link_applied'));
        findLinkSuggestions(null);
    };

    window.generateAiImage = async function (button) {
        if (!currentId || currentType !== 'product') {
            Fyn.toast(t('select_product_first'), true);
            return;
        }
        Fyn.html('ai-image-result', `<s-text color="subdued">${esc(t('image_working'))}</s-text>`);
        const data = await Fyn.busy(button, () => Fyn.post(`/content/product/${numericId()}/generate-image`, { prompt: Fyn.val('ai-image-prompt') }));
        if (!data.success) {
            Fyn.html('ai-image-result', '');
            return fail(data);
        }
        ok(t('image_done'));
        Fyn.html('ai-image-result', `<img src="${esc(data.image_url)}" alt="" style="max-width: 200px; border-radius: 8px;">`);
    };

    Fyn.page(async () => {
        // Deep link from the SEO audit: ?type=product&id=123
        const params = new URLSearchParams(location.search);
        const type = params.get('type');
        const id = params.get('id');
        if (['product', 'collection', 'page', 'article'].includes(type)) {
            Fyn.setVal('content-type', type);
            currentType = type;
        }
        await searchContent();
        if (id && /^\d+$/.test(id)) {
            const typeName = currentType.charAt(0).toUpperCase() + currentType.slice(1);
            await window.selectContent(`gid://shopify/${typeName}/${id}`);
        }
    });
})();
