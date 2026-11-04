/*
 * GEO scan: start a scan, poll its progress and list recent scans.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    let pollToken = 0;

    function statusLabel(status) {
        const key = 'status_' + status;
        const label = t(key);
        return label === key ? status : label;
    }

    function statusTone(status) {
        return status === 'completed' ? 'success' : status === 'failed' ? 'critical' : 'neutral';
    }

    window.startGeoScan = async function (button) {
        const keywords = Fyn.val('geo-keywords').trim();
        if (!keywords) {
            Fyn.toast(t('enter_keyword'), true);
            return;
        }
        Fyn.html('geo-result', Fyn.loading());
        const data = await Fyn.busy(button, () => Fyn.post('/geo-scan/start', { keywords }));
        if (data.error) {
            Fyn.html('geo-result', Fyn.errorBanner(data));
            return;
        }
        const scanId = data.scan_id || data.id;
        if (!scanId) {
            Fyn.html('geo-result', Fyn.banner('success', t('scan_queued')));
            return;
        }
        Fyn.html('geo-result', Fyn.banner('info', t('scan_started', { id: scanId })));
        pollGeoScan(scanId);
    };

    async function pollGeoScan(scanId) {
        const token = ++pollToken;
        let attempts = 0;
        const poll = async () => {
            if (token !== pollToken) return;
            if (attempts++ > 60) {
                Fyn.html('geo-result', Fyn.banner('critical', t('scan_timeout')));
                return;
            }
            const data = await Fyn.api(`/geo-scan/${encodeURIComponent(scanId)}/status`);
            if (token !== pollToken) return;
            if (data.error) {
                Fyn.html('geo-result', Fyn.errorBanner(data));
                return;
            }
            const status = data.status || 'unknown';
            const progress = Number(data.progress) || 0;
            if (status === 'completed') {
                const score = data.score ?? data.result?.score ?? '-';
                Fyn.html('geo-result', Fyn.banner('success', t('scan_complete', { score })));
                Fyn.html('geo-detail', Fyn.json(data.result || data));
                Fyn.show('geo-detail-card', true);
                loadGeoRecent();
                return;
            }
            if (status === 'failed') {
                Fyn.html('geo-result', Fyn.banner('critical', t('scan_failed', { error: data.error || t('unknown_error') })));
                return;
            }
            const label = t('scan_status', { status: statusLabel(status), progress }) + (data.progress_label ? ' - ' + data.progress_label : '');
            Fyn.html('geo-result', `<s-stack gap="small-200">
                    <s-text>${esc(label)}</s-text>
                    <s-progress value="${progress}" max="100" accessibilityLabel="${esc(label)}"></s-progress>
                </s-stack>`);
            setTimeout(poll, 5000);
        };
        poll();
    }

    async function loadGeoRecent() {
        const data = await Fyn.api('/geo-scan/recent?limit=10');
        if (data.error) {
            Fyn.html('geo-recent', Fyn.errorBanner(data));
            return;
        }
        const scans = data.scans || data.data || (Array.isArray(data) ? data : []);
        if (!scans.length) {
            Fyn.html('geo-recent', `<s-paragraph>${esc(t('no_scans'))}</s-paragraph>`);
            return;
        }
        Fyn.html('geo-recent', scans.map(s => {
            const title = s.keywords || s.url || t('scan_label', { id: s.id });
            const sub = statusLabel(s.status) + (s.score ? ' - ' + t('score', { score: s.score }) : '');
            const end = `<s-stack direction="inline" gap="small-200" alignItems="center">${Fyn.badge(statusTone(s.status), statusLabel(s.status))}
                <s-button variant="tertiary" onclick="viewGeoScan(${esc(JSON.stringify(String(s.id)))})">${esc(t('view'))}</s-button></s-stack>`;
            return Fyn.row(title, sub, end);
        }).join(''));
    }

    window.viewGeoScan = function (id) {
        Fyn.html('geo-result', Fyn.loading());
        pollGeoScan(id);
    };

    Fyn.page(async () => {
        loadGeoRecent();
    });
})();
