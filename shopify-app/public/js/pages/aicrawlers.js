/*
 * AI crawlers (2.2): which AI bots may read the store, from the live
 * robots.txt. Read only: the app does not change the theme (App Store
 * requirement 5.1.1).
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    let bots = {};
    let live = null;

    function render() {
        Fyn.html('ac-bots', Fyn.table(
            [t('col_bot'), t('col_company'), t('col_purpose'), t('col_live')],
            Object.entries(bots).map(([bot, meta]) => {
                const state = live ? live[bot] : null;
                return [
                    `<s-text type="strong">${esc(bot)}</s-text>`,
                    esc(meta.company),
                    `<s-stack gap="small-500"><s-text>${esc(t('purpose_' + meta.purpose))}</s-text><s-text color="subdued">${esc(t('purpose_' + meta.purpose + '_help'))}</s-text></s-stack>`,
                    !state ? '-' : Fyn.badge(state === 'block' ? (meta.purpose === 'training' ? 'info' : 'warning') : 'success', t(state === 'block' ? 'live_blocked' : 'live_allowed')),
                ];
            })));
    }

    async function check(button) {
        const data = await Fyn.busy(button, () => Fyn.post('/ai-crawlers/check'));
        if (data.error) {
            Fyn.html('ac-status', Fyn.errorBanner(data));
            return;
        }
        if (data.live.error) {
            live = null;
            Fyn.html('ac-status', Fyn.banner('info', t('live_error', { error: data.live.error })));
        } else {
            live = data.live.bots;
            const blockedSearch = Object.entries(live).filter(([bot, state]) => state === 'block' && bots[bot] && bots[bot].purpose !== 'training').map(([bot]) => bot);
            Fyn.html('ac-status', blockedSearch.length
                ? Fyn.banner('warning', t('search_blocked', { bots: blockedSearch.join(', ') }))
                : Fyn.banner('success', t('search_allowed')));
        }
        render();
    }

    window.checkCrawlers = check;

    Fyn.page(async () => {
        const data = await Fyn.api('/ai-crawlers');
        if (data.error) {
            Fyn.html('ac-bots', Fyn.errorBanner(data));
            return;
        }
        bots = data.bots;
        render();
        await check(null);
    });
})();
