/*
 * Rank tracker: tracked keywords, Google positions and manual rank checks.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;

    function posBadge(pos) {
        if (!pos) return Fyn.badge('neutral', '-');
        return Fyn.badge(pos <= 3 ? 'success' : pos <= 10 ? 'info' : 'warning', String(pos));
    }

    async function loadRankStats() {
        const data = await Fyn.api('/rank-tracker/stats');
        if (data.error) {
            Fyn.html('rank-stats', Fyn.errorBanner(data));
            return;
        }
        Fyn.html('rank-stats', Fyn.stats([
            { label: t('rt_total'), value: data.total_keywords ?? 0, sub: t('rt_total_sub') },
            { label: t('rt_top3'), value: data.top_3 ?? 0, sub: t('rt_top3_sub'), tone: 'success' },
            { label: t('rt_top10'), value: data.top_10 ?? 0, sub: t('rt_top10_sub'), tone: 'success' },
            { label: t('rt_top100'), value: data.top_100 ?? 0, sub: t('rt_top100_sub') },
            { label: t('rt_not_ranked'), value: data.not_ranked ?? 0, sub: t('rt_not_ranked_sub') },
        ]));
    }

    async function loadKeywords() {
        const data = await Fyn.api('/rank-tracker/keywords');
        if (data.error || !data.keywords) {
            Fyn.html('rank-keywords', data.error ? Fyn.errorBanner(data) : '');
            return;
        }
        if (!data.keywords.length) {
            Fyn.html('rank-keywords', `<s-paragraph>${esc(t('rt_empty'))}</s-paragraph>`);
            return;
        }
        Fyn.html('rank-keywords', Fyn.table(
            [t('rt_col_keyword'), t('rt_col_url'), { label: t('rt_col_last'), format: 'numeric' }, { label: t('rt_col_best'), format: 'numeric' }, { label: t('rt_col_gsc'), format: 'numeric' }, t('rt_col_checked'), ''],
            data.keywords.map(k => [
                esc(k.keyword),
                esc(k.url ? (k.url.length > 40 ? k.url.substring(0, 40) + '...' : k.url) : '-'),
                posBadge(k.last_position),
                posBadge(k.best_position),
                k.gsc ? `${esc(k.gsc.position)} <s-text color="subdued">(${esc(t('rt_gsc_clicks', { clicks: k.gsc.clicks }))})</s-text>` : '-',
                esc(k.last_checked_at ? Fyn.fmtDate(k.last_checked_at) : '-'),
                `<s-button variant="tertiary" tone="critical" accessibilityLabel="${esc(t('rt_delete'))}" onclick="deleteKeyword(${Number(k.id)}, this)">${esc(t('rt_delete'))}</s-button>`,
            ])
        ));
    }

    window.addKeyword = async function (button) {
        const keyword = Fyn.val('rt-keyword').trim();
        const url = Fyn.val('rt-url').trim();
        if (!keyword) {
            Fyn.toast(t('rt_enter_keyword'), true);
            return;
        }
        const data = await Fyn.busy(button, () => Fyn.post('/rank-tracker/keywords', { keyword, url }));
        if (data.success) {
            Fyn.setVal('rt-keyword', '');
            Fyn.setVal('rt-url', '');
            loadKeywords();
            loadRankStats();
        } else {
            Fyn.toast(Fyn.errorText(data), true);
        }
    };

    window.deleteKeyword = async function (id, button) {
        if (!(await Fyn.confirm(t('rt_delete_confirm')))) return;
        const data = await Fyn.busy(button, () => Fyn.api(`/rank-tracker/keywords/${id}`, { method: 'DELETE' }));
        if (data.success) {
            loadKeywords();
            loadRankStats();
        } else {
            Fyn.toast(Fyn.errorText(data), true);
        }
    };

    window.checkRankings = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/rank-tracker/check'));
        if (data.success) {
            Fyn.toast(t('rt_check_started'));
        } else {
            Fyn.toast(Fyn.errorText(data), true);
        }
    };

    Fyn.page(async () => {
        loadRankStats();
        loadKeywords();
    });
})();
