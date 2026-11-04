/*
 * Structured data (1.4): app embed status (App Bridge app.extensions,
 * Built for Shopify 4.2.3), schema switches, Organization and LocalBusiness
 * details, and the duplicate schema check of the theme.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    const FLAGS = ['organization', 'website', 'breadcrumbs', 'product', 'article', 'local_business'];
    let state = null;

    const lines = id => Fyn.val(id).split(/\r?\n/).map(s => s.trim()).filter(Boolean);

    async function renderEmbed() {
        const status = await Fyn.embedStatus('fyndable-seo-schema');
        const editor = `<s-button slot="secondary-actions" href="${esc(Fyn.embedEditorUrl('fyndable-seo-schema'))}" target="_top">${esc(t('embed_open'))}</s-button>`;
        Fyn.html('schema-embed', status === 'active'
            ? `<s-banner tone="success">${esc(t('embed_active'))}</s-banner>`
            : status === null
                ? `<s-banner tone="info">${esc(t('embed_unknown'))}${editor}</s-banner>`
                : `<s-banner tone="warning">${esc(t('embed_inactive'))}${editor}</s-banner>`);
    }

    function renderStatus(data) {
        if (data.publish_error) {
            Fyn.html('schema-status', Fyn.banner('critical', t('publish_failed', { message: data.publish_error })));
        } else if (data.published_at) {
            Fyn.html('schema-status', `<s-text color="subdued">${esc(t('last_published', { date: Fyn.fmtDate(data.published_at, true) }))}</s-text>`);
        } else {
            Fyn.html('schema-status', '');
        }
    }

    function renderThemeCheck(check, checkedAt) {
        if (!check) {
            Fyn.html('theme-check', '');
            return;
        }
        const enabled = FLAGS.filter(f => Fyn.checked('flag-' + f));
        const duplicates = (check.duplicates || []).filter(f => enabled.includes(f));
        let html = check.findings.length
            ? Fyn.banner(duplicates.length ? 'warning' : 'info', t('theme_found', { theme: check.theme, files: check.checked_files }))
            : Fyn.banner('success', t('theme_none', { theme: check.theme, files: check.checked_files }));
        if (duplicates.length) {
            html += `<s-paragraph>${esc(t('theme_duplicate', { types: duplicates.map(f => t('flag_name_' + f)).join(', ') }))}</s-paragraph>`;
        }
        if (check.findings.length) {
            html += Fyn.table([t('col_file'), t('col_types')], check.findings.map(f => [
                esc(f.file),
                f.types.map(type => esc(type)).join(', '),
            ]));
        }
        if (checkedAt) html += `<s-text color="subdued">${esc(t('theme_checked', { date: Fyn.fmtDate(checkedAt, true) }))}</s-text>`;
        Fyn.html('theme-check', `<s-stack gap="base">${html}</s-stack>`);
    }

    function fill(data) {
        const s = data.settings;
        FLAGS.forEach(f => Fyn.setChecked('flag-' + f, s.flags[f]));
        Fyn.setVal('org-name', s.organization.name);
        Fyn.setVal('org-logo', s.organization.logo);
        Fyn.setVal('org-description', s.organization.description);
        Fyn.setVal('org-email', s.organization.email);
        Fyn.setVal('org-phone', s.organization.phone);
        Fyn.setVal('org-same-as', (s.organization.same_as || []).join('\n'));
        const lb = s.local_business;
        Fyn.setVal('lb-type', lb.type || 'Store');
        Fyn.setVal('lb-street', lb.street);
        Fyn.setVal('lb-postal', lb.postal_code);
        Fyn.setVal('lb-city', lb.city);
        Fyn.setVal('lb-region', lb.region);
        Fyn.setVal('lb-country', lb.country);
        Fyn.setVal('lb-phone', lb.phone);
        Fyn.setVal('lb-lat', lb.latitude ?? '');
        Fyn.setVal('lb-lng', lb.longitude ?? '');
        Fyn.setVal('lb-hours', (lb.opening_hours || []).join('\n'));
        Fyn.setVal('lb-price', lb.price_range);

        // Built for Shopify 4.3.7: plan-gated options are disabled and labelled.
        const localAllowed = !!data.allowed.local_business;
        Fyn.$('flag-local_business').disabled = !localAllowed;
        Fyn.show('local-section', localAllowed);
        const required = Fyn.ctx?.plan?.required?.local_business_schema;
        Fyn.html('local-locked', localAllowed ? '' : `<s-banner tone="info">${esc(t('locked_local', { tier: Fyn.tierLabel(required || 'professional') }))}
            <s-button slot="secondary-actions" href="${esc(Fyn.url('plan'))}">${esc(t('lock_button'))}</s-button></s-banner>`);
    }

    async function load() {
        const data = await Fyn.api('/schema');
        if (data.error) {
            Fyn.html('schema-status', Fyn.errorBanner(data));
            return;
        }
        state = data;
        fill(data);
        renderStatus(data);
        renderThemeCheck(data.theme_check, data.theme_checked_at);
    }

    window.saveSchema = async function (button) {
        const num = id => {
            const v = Fyn.val(id).trim().replace(',', '.');
            return v === '' ? null : v;
        };
        const flags = {};
        FLAGS.forEach(f => { flags[f] = Fyn.checked('flag-' + f); });
        const body = {
            flags,
            organization: {
                name: Fyn.val('org-name').trim(),
                logo: Fyn.val('org-logo').trim(),
                description: Fyn.val('org-description').trim(),
                email: Fyn.val('org-email').trim(),
                phone: Fyn.val('org-phone').trim(),
                same_as: lines('org-same-as'),
            },
            local_business: {
                type: Fyn.val('lb-type') || 'Store',
                street: Fyn.val('lb-street').trim(),
                postal_code: Fyn.val('lb-postal').trim(),
                city: Fyn.val('lb-city').trim(),
                region: Fyn.val('lb-region').trim(),
                country: Fyn.val('lb-country').trim().toUpperCase(),
                phone: Fyn.val('lb-phone').trim(),
                latitude: num('lb-lat'),
                longitude: num('lb-lng'),
                opening_hours: lines('lb-hours'),
                price_range: Fyn.val('lb-price').trim(),
            },
        };
        const data = await Fyn.busy(button, () => Fyn.post('/schema', body));
        if (data.error === 'invalid_settings') {
            Fyn.toast(t('invalid_settings', { fields: (data.fields || []).join(', ') }), true);
            return;
        }
        if (data.error) {
            Fyn.html('schema-status', Fyn.banner('critical', t('publish_failed', { message: Fyn.errorText(data) })));
            return;
        }
        Fyn.toast(t('published'));
        load();
    };

    window.runThemeCheck = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/schema/theme-check'));
        if (data.error) {
            Fyn.html('theme-check', Fyn.errorBanner(data));
            return;
        }
        renderThemeCheck(data.theme_check, new Date().toISOString());
    };

    // Re-evaluate duplicates when switches change.
    document.addEventListener('change', e => {
        if (state && e.target && String(e.target.id || '').startsWith('flag-')) {
            renderThemeCheck(state.theme_check, state.theme_checked_at);
        }
    });

    Fyn.page(async () => {
        await Promise.all([load(), renderEmbed()]);
    });
})();
