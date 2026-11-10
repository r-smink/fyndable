/*
 * Backlinks: backlink summary or live backlinks for a domain (DataForSEO).
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;

    function summary(data) {
        const s = data.summary || {};
        const rows = [
            [t('bl_main_domains'), Fyn.num(s.referring_main_domains)],
            [t('bl_nofollow_domains'), Fyn.num(s.referring_domains_nofollow)],
            [t('bl_ips'), Fyn.num(s.referring_ips)],
            [t('bl_first_seen'), s.first_seen ? Fyn.fmtDate(s.first_seen) : '-'],
        ];
        return `<s-stack gap="base">
                <s-text color="subdued">${esc(t('bl_for', { target: data.target }))}</s-text>
                ${Fyn.stats([
                    { label: t('bl_rank'), value: Fyn.num(s.rank), sub: t('bl_rank_sub') },
                    { label: t('bl_backlinks'), value: Fyn.num(s.backlinks) },
                    { label: t('bl_domains'), value: Fyn.num(s.referring_domains) },
                    { label: t('bl_broken'), value: Fyn.num(s.broken_backlinks), tone: s.broken_backlinks ? 'warning' : undefined },
                    { label: t('bl_spam'), value: s.spam_score === null || s.spam_score === undefined ? '-' : `${s.spam_score}/100`, tone: s.spam_score >= 50 ? 'critical' : undefined },
                ])}
                <s-stack>${rows.map(([label, value]) => Fyn.row(label, '', `<s-text>${esc(value)}</s-text>`)).join('')}</s-stack>
            </s-stack>`;
    }

    function live(data) {
        const items = data.items || [];
        if (!items.length) return `<s-paragraph>${esc(t('bl_none', { target: data.target }))}</s-paragraph>`;
        return `<s-stack gap="base">
                <s-text color="subdued">${esc(t('bl_showing', { shown: Fyn.num(items.length), total: Fyn.num(data.total), target: data.target }))}</s-text>
                ${Fyn.table(
                    [t('bl_col_source'), t('bl_col_anchor'), t('bl_col_type'), { label: t('bl_col_rank'), format: 'numeric' }, t('bl_col_first_seen')],
                    items.map(i => [
                        Fyn.wrap(`${Fyn.link(i.url_from, i.domain_from || i.url_from)}${i.is_broken ? ' ' + Fyn.badge('critical', t('bl_broken_link')) : ''}`),
                        Fyn.wrap(esc(i.anchor || t('bl_no_anchor'))),
                        Fyn.badge(i.dofollow ? 'success' : 'neutral', i.dofollow ? 'dofollow' : 'nofollow'),
                        esc(Fyn.num(i.domain_rank)),
                        esc(i.first_seen ? Fyn.fmtDate(i.first_seen) : '-'),
                    ])
                )}</s-stack>`;
    }

    window.loadBacklinks = async function (isLive, button) {
        // An empty field falls back to the store domain (the placeholder).
        const target = Fyn.val('bl-target').trim() || Fyn.cfg.shop;
        Fyn.html('backlinks-result', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.api(`/insights/backlinks?target=${encodeURIComponent(target)}&live=${isLive ? 1 : 0}&limit=50`));
        if (data.error || data.success === false) {
            Fyn.html('backlinks-result', Fyn.errorBanner(data));
            return;
        }
        Fyn.html('backlinks-result', data.live ? live(data) : summary(data));
    };

    Fyn.page(async () => {});
})();
