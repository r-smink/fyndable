/*
 * SERP competitor analysis and domain comparison.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;

    window.analyzeCompetitors = async function (button) {
        const keyword = Fyn.val('comp-keyword').trim();
        if (!keyword) {
            Fyn.toast(t('comp_enter_keyword'), true);
            return;
        }
        Fyn.show('comp-results-card', true);
        Fyn.html('comp-results', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post('/competitor/analyze', {
            keyword,
            target_url: Fyn.val('comp-target').trim() || undefined,
            country: Fyn.val('comp-country'),
            language: Fyn.val('comp-lang'),
        }));
        if (data.error) {
            Fyn.html('comp-results', Fyn.errorBanner(data));
            return;
        }
        const pos = data.target_position;
        let html = `<s-stack gap="base"><s-banner tone="${pos && pos <= 10 ? 'success' : 'info'}">${esc(t('comp_your_position', { position: pos || t('comp_not_found') }))}</s-banner>`;
        html += Fyn.table(
            ['#', t('comp_col_domain'), t('comp_col_title'), { label: t('comp_col_backlinks'), format: 'numeric' }, { label: t('comp_col_refdomains'), format: 'numeric' }, { label: 'DR', format: 'numeric' }],
            (data.competitors || []).map((c, i) => {
                const bl = c.backlinks || {};
                return [
                    esc(c.position || i + 1),
                    esc(c.domain),
                    esc((c.title || '').substring(0, 60)),
                    esc(bl.total ?? '-'),
                    esc(bl.referring_domains ?? '-'),
                    esc(bl.domain_rank ?? '-'),
                ];
            })
        );
        if (data.serp_features && Object.keys(data.serp_features).length) {
            html += `<s-stack direction="inline" gap="small-200" alignItems="center"><s-text type="strong">${esc(t('comp_serp_features'))}</s-text>`
                + Object.keys(data.serp_features).map(f => Fyn.badge('neutral', f)).join('') + '</s-stack>';
        }
        Fyn.html('comp-results', html + '</s-stack>');
    };

    window.compareDomains = async function (button) {
        const a = Fyn.val('comp-domain-a').trim();
        const b = Fyn.val('comp-domain-b').trim();
        if (!a || !b) {
            Fyn.toast(t('comp_enter_domains'), true);
            return;
        }
        Fyn.html('comp-compare-result', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post('/competitor/compare', { domain_a: a, domain_b: b }));
        if (data.error) {
            Fyn.html('comp-compare-result', Fyn.errorBanner(data));
            return;
        }
        const domains = data.domains || [];
        const metrics = [
            ['rank', t('comp_m_rank')],
            ['backlinks', t('comp_m_backlinks')],
            ['referring_domains', t('comp_m_domains')],
            ['referring_main_domains', t('comp_m_main_domains')],
            ['broken_backlinks', t('comp_m_broken')],
            ['spam_score', t('comp_m_spam')],
        ];
        // Highlight the better value per row (lower is better for broken links and spam).
        const lowerIsBetter = ['broken_backlinks', 'spam_score'];
        const cell = (key, d) => {
            if (d.error) return esc('-');
            const values = domains.filter(x => !x.error && x[key] !== null && x[key] !== undefined).map(x => Number(x[key]));
            const v = d[key];
            const best = values.length === 2 && values[0] !== values[1]
                && Number(v) === (lowerIsBetter.includes(key) ? Math.min(...values) : Math.max(...values));
            return best ? `<strong>${esc(Fyn.num(v))}</strong>` : esc(Fyn.num(v));
        };
        const errors = domains.filter(d => d.error).map(d => Fyn.banner('warning', t('comp_domain_error', { domain: d.domain, error: d.error }))).join('');
        Fyn.html('comp-compare-result', `<s-stack gap="base">${errors}${Fyn.table(
            [t('comp_m_metric'), ...domains.map(d => ({ label: d.domain, format: 'numeric' }))],
            metrics.map(([key, label]) => [esc(label), ...domains.map(d => cell(key, d))])
        )}</s-stack>`);
    };

    Fyn.page(async (ctx) => {
        // Default to the store's content language when it is offered here.
        const lang = ctx && ctx.onboarding && ctx.onboarding.language;
        if (lang && document.querySelector(`#comp-lang s-option[value="${CSS.escape(lang)}"]`)) {
            Fyn.setVal('comp-lang', lang);
        }
    });
})();
