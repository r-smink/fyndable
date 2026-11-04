/*
 * Tools: IndexNow key status, key redirect setup and URL submission.
 */
(function () {
    'use strict';

    const { t } = Fyn;

    async function loadIndexNowStatus() {
        const data = await Fyn.api('/indexnow/status');
        if (data.error) {
            Fyn.html('indexnow-status', Fyn.errorBanner(data));
            return;
        }
        Fyn.html('indexnow-status',
            Fyn.row(t('key'), data.key, Fyn.badge('success', t('key_generated')))
            + Fyn.row(t('key_file'), data.key_location)
            + Fyn.row(t('proxy_target'), t('proxy_redirect', { path: data.proxy_path })));
    }

    window.setupIndexNow = async function (button) {
        Fyn.html('indexnow-result', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post('/indexnow/setup'));
        if (data.success) {
            Fyn.html('indexnow-result', Fyn.banner('success', t('redirect_done', {
                status: data.redirect?.status, path: data.redirect?.path, target: data.redirect?.target,
            })));
        } else {
            Fyn.html('indexnow-result', Fyn.errorBanner(data));
        }
    };

    window.submitIndexNow = async function (button) {
        const urls = Fyn.val('indexnow-urls').split('\n').map(u => u.trim()).filter(Boolean);
        if (!urls.length) {
            Fyn.toast(t('enter_url'), true);
            return;
        }
        Fyn.html('indexnow-result', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post('/indexnow/submit', { urls }));
        if (data.success) {
            Fyn.html('indexnow-result', Fyn.banner('success', t('submitted', { count: data.submitted })));
        } else {
            Fyn.html('indexnow-result', Fyn.errorBanner(data));
        }
    };

    Fyn.page(loadIndexNowStatus);
})();
