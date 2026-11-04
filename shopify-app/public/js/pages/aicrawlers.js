/*
 * AI crawlers (2.2): allow or block AI bots, generate the robots.txt.liquid
 * code for the theme and check the theme file and the live robots.txt.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    let bots = {};
    let live = null;

    function choices() {
        const result = {};
        Object.keys(bots).forEach(bot => { result[bot] = Fyn.val('bot-' + bot) || 'allow'; });
        return result;
    }

    function render(settings) {
        Fyn.html('ac-bots', Fyn.table(
            [t('col_bot'), t('col_company'), t('col_purpose'), t('col_choice'), t('col_live')],
            Object.entries(bots).map(([bot, meta]) => {
                const liveState = live ? live[bot] : null;
                const liveBadge = !liveState ? '-'
                    : liveState !== settings[bot] ? Fyn.badge('warning', t('live_mismatch'))
                        : Fyn.badge(liveState === 'block' ? 'critical' : 'success', t(liveState === 'block' ? 'live_blocked' : 'live_allowed'));
                return [
                    `<s-text type="strong">${esc(bot)}</s-text>`,
                    esc(meta.company),
                    `<s-stack gap="small-500"><s-text>${esc(t('purpose_' + meta.purpose))}</s-text><s-text color="subdued">${esc(t('purpose_' + meta.purpose + '_help'))}</s-text></s-stack>`,
                    `<s-select id="bot-${esc(bot)}" label="${esc(bot)}" labelAccessibilityVisibility="exclusive">
                        <s-option value="allow"${settings[bot] === 'allow' ? ' selected' : ''}>${esc(t('allow'))}</s-option>
                        <s-option value="block"${settings[bot] === 'block' ? ' selected' : ''}>${esc(t('block'))}</s-option>
                    </s-select>`,
                    liveBadge,
                ];
            })));
    }

    function apply(data) {
        bots = data.bots;
        render(data.settings);
        Fyn.setVal('ac-template', data.template);
        Fyn.setVal('ac-block', data.block);
    }

    window.presetCrawlers = function (preset) {
        const settings = {};
        Object.entries(bots).forEach(([bot, meta]) => {
            settings[bot] = preset === 'training' && meta.purpose === 'training' ? 'block' : 'allow';
        });
        render(settings);
    };

    window.saveCrawlers = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/ai-crawlers', { bots: choices() }));
        if (data.error) {
            Fyn.toast(Fyn.errorText(data), true);
            return;
        }
        apply(data);
        Fyn.toast(t('saved'));
    };

    window.checkCrawlers = async function (button) {
        const data = await Fyn.busy(button, () => Fyn.post('/ai-crawlers/check'));
        if (data.error) {
            Fyn.html('ac-theme', Fyn.errorBanner(data));
            return;
        }
        const settings = choices();
        const anyBlocked = Object.values(settings).includes('block');
        let html = '';
        const theme = data.theme || {};
        if (theme.error) html += Fyn.errorBanner(theme);
        else if (!anyBlocked && !theme.has_block) html += Fyn.banner('info', t('nothing_blocked'));
        else if (!theme.exists) html += Fyn.banner('warning', t('theme_missing', { theme: theme.theme || '' }));
        else if (!theme.has_block) html += Fyn.banner('warning', t('theme_no_block'));
        else if (!theme.in_sync) html += Fyn.banner('warning', t('theme_outdated'));
        else html += Fyn.banner('success', t('theme_in_sync'));

        if (data.live && data.live.error) {
            live = null;
            html += Fyn.banner('info', t('live_error', { error: data.live.error }));
        } else if (data.live) {
            live = data.live.bots;
        }
        Fyn.html('ac-theme', `<s-stack gap="small-200">${html}</s-stack>`);
        render(settings);
        Fyn.toast(t('checked'));
    };

    window.copyCrawlerCode = async function (id) {
        try {
            await navigator.clipboard.writeText(Fyn.val(id));
            Fyn.toast(t('copied'));
        } catch (e) {
            Fyn.toast(t('copy_failed'), true);
        }
    };

    Fyn.page(async () => {
        const data = await Fyn.api('/ai-crawlers');
        if (data.error) {
            Fyn.html('ac-bots', Fyn.errorBanner(data));
            return;
        }
        apply(data);
    });
})();
