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
                Fyn.html('geo-detail', geoReport(Object.assign({ url: data.url }, data.result || {})));
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

    const PLATFORMS = { chatgpt: 'ChatGPT', gemini: 'Gemini', perplexity: 'Perplexity', claude: 'Claude', google_ai_overviews: 'Google AI Overviews' };

    /** Label for a key, from the page strings when there is one. */
    function label(prefix, key) {
        const text = t(prefix + key);
        return text === prefix + key ? key.replace(/_/g, ' ').replace(/^./, c => c.toUpperCase()) : text;
    }

    /** Model output lists are strings, sometimes objects with title/description. */
    function textOf(item) {
        if (item && typeof item === 'object') return [item.title, item.description || item.text].filter(Boolean).join(': ');
        return String(item ?? '');
    }

    function list(title, items) {
        items = (items || []).map(textOf).filter(Boolean);
        if (!items.length) return '';
        return `<s-stack gap="small-200"><s-heading>${esc(title)}</s-heading>
            <s-unordered-list>${items.map(i => `<s-list-item>${esc(i)}</s-list-item>`).join('')}</s-unordered-list></s-stack>`;
    }

    /** Readable report for a completed scan (GeoScanner result). */
    function geoReport(r) {
        r = r || {};
        const parts = [];
        const head = [Fyn.scoreBadge(r.score), r.url ? `<s-text color="subdued">${Fyn.link(r.url, r.url)}</s-text>` : ''].join(' ');
        parts.push(`<s-stack gap="small-200">
                <s-stack direction="inline" gap="small-200" alignItems="center">${head}</s-stack>
                ${r.management_headline ? `<s-heading>${esc(r.management_headline)}</s-heading>` : ''}
                ${r.executive_summary ? Fyn.markdown(r.executive_summary) : ''}
            </s-stack>`);

        const pv = r.platform_visibility || {};
        const platformRows = Object.entries(pv).filter(([, p]) => p && typeof p === 'object').map(([key, p]) => {
            const tone = p.status === 'visible' ? 'success' : p.status === 'sometimes' ? 'warning' : p.status === 'not_mentioned' ? 'critical' : 'neutral';
            const sub = p.tests ? t('pv_tests', { mentions: p.mentions || 0, tests: p.tests }) : t('pv_unavailable');
            return Fyn.row(PLATFORMS[key] || label('pv_', key), sub, Fyn.badge(tone, label('pv_status_', p.status || 'unavailable')));
        });
        if (platformRows.length) {
            parts.push(`<s-stack gap="small-200"><s-heading>${esc(t('report_platforms'))}</s-heading>${platformRows.join('')}</s-stack>`);
        }

        const breakdown = Object.entries(r.breakdown || {}).filter(([, v]) => v !== null && v !== '' && !isNaN(Number(v)));
        if (breakdown.length) {
            parts.push(`<s-stack gap="small-200"><s-heading>${esc(t('report_breakdown'))}</s-heading>${Fyn.table(
                [t('report_aspect'), t('report_score')],
                breakdown.sort((a, b) => Number(a[1]) - Number(b[1])).map(([key, v]) => [esc(label('b_', key)), Fyn.scoreBadge(Number(v))])
            )}</s-stack>`);
        }

        const actions = (r.structured_actions || []).filter(a => a && typeof a === 'object' && a.title);
        if (actions.length) {
            const tone = p => (p === 'high' ? 'critical' : p === 'medium' ? 'warning' : 'neutral');
            parts.push(`<s-stack gap="small-200"><s-heading>${esc(t('report_actions'))}</s-heading>
                ${actions.map(a => Fyn.row(a.title, a.description || '', a.priority ? Fyn.badge(tone(a.priority), label('priority_', a.priority)) : '')).join('')}</s-stack>`);
        } else {
            parts.push(list(t('report_recommendations'), (r.priority_ranked_recommendations || []).length ? r.priority_ranked_recommendations : r.recommendations));
        }

        parts.push(list(t('report_strengths'), r.strengths));
        parts.push(list(t('report_weaknesses'), r.weaknesses));
        parts.push(list(t('report_findings'), r.findings));

        if (r.multi_model && (r.models_used || []).length) {
            parts.push(`<s-text color="subdued">${esc(t('report_models', { models: r.models_used.map(m => `${m.model} (${m.score})`).join(', ') }))}</s-text>`);
        }

        return `<s-stack gap="large">${parts.filter(Boolean).join('')}</s-stack>`;
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
