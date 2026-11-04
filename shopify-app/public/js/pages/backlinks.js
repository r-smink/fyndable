/*
 * Backlinks: backlink summary or live backlinks for a domain (DataForSEO).
 */
(function () {
    'use strict';

    window.loadBacklinks = async function (live, button) {
        // An empty field falls back to the store domain (the placeholder).
        const target = Fyn.val('bl-target').trim() || Fyn.cfg.shop;
        Fyn.html('backlinks-result', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.api(`/insights/backlinks?target=${encodeURIComponent(target)}&live=${live ? 1 : 0}&limit=50`));
        if (data.error || data.success === false) {
            Fyn.html('backlinks-result', Fyn.errorBanner(data));
            return;
        }
        Fyn.html('backlinks-result', Fyn.json(data.data ?? data));
    };

    Fyn.page(async () => {});
})();
