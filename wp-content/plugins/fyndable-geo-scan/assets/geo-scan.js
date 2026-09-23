(function () {
    'use strict';

    var form = document.getElementById('fyndable-geo-scan-form');
    if (!form) {
        return;
    }

    var strings = (window.fyndableGeoScan && window.fyndableGeoScan.strings) || {};
    var restUrl = (window.fyndableGeoScan && window.fyndableGeoScan.restUrl) || '/wp-json/fyndable/v1';

    var submitBtn = document.getElementById('fgs-submit');
    var progress = document.getElementById('fgs-progress');
    var fill = progress.querySelector('.fgs-progress-fill');
    var pct = progress.querySelector('.fgs-progress-pct');
    var label = progress.querySelector('.fgs-progress-label');
    var errorBox = document.getElementById('fgs-error');
    var resultBox = document.getElementById('fgs-result');

    var pollTimer = null;
    var tickTimer = null;
    var pollStarted = 0;
    var shown = 0;    // smoothed percentage currently rendered
    var reported = 0; // last percentage reported by the server
    var MAX_POLL_MS = 10 * 60 * 1000; // 10 minutes — slow LLM responses take a while
    var CREEP_AHEAD = 12;             // how far beyond the server value the bar may drift
    var CREEP_CAP = 96;               // never show 100 until the scan reports completed
    var STORAGE_KEY = 'fgs_pending_scan';

    // The scan row lives on the portal for 90 days — remember the scan id so a
    // result can still be shown after a timeout or a page refresh.
    function savePending(scanId, scanUrl) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ id: scanId, url: scanUrl, t: Date.now() }));
        } catch (e) { /* private mode etc. — resume just won't work */ }
    }

    function loadPending() {
        try {
            var p = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
            if (!p || !p.id || (Date.now() - p.t) > 24 * 60 * 60 * 1000) {
                return null;
            }
            return p;
        } catch (e) {
            return null;
        }
    }

    function clearPending() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {}
    }

    function esc(s) {
        var div = document.createElement('div');
        div.textContent = s == null ? '' : String(s);
        return div.innerHTML;
    }

    function render() {
        var p = Math.max(0, Math.min(100, Math.round(shown)));
        fill.style.width = p + '%';
        pct.textContent = p + '%';
    }

    function setProgress(p, l) {
        if (p >= 0) {
            reported = Math.max(reported, Math.min(100, p));
            if (p > shown) {
                shown = p;
            }
            render();
        }
        if (l) {
            label.textContent = l;
        }
    }

    // Ease the bar forward between server updates so it never appears frozen:
    // it drifts at most CREEP_AHEAD points past the last reported value and
    // stays below CREEP_CAP until the scan actually completes.
    function tick() {
        var target = Math.min(reported + CREEP_AHEAD, CREEP_CAP);
        if (shown < target) {
            shown += Math.max(0.1, (target - shown) * 0.04);
            render();
        }
    }

    function startTicker() {
        if (!tickTimer) {
            tickTimer = setInterval(tick, 400);
        }
    }

    function stopTicker() {
        if (tickTimer) {
            clearInterval(tickTimer);
            tickTimer = null;
        }
    }

    function showError(msg) {
        errorBox.textContent = msg || strings.error;
        errorBox.hidden = false;
    }

    function resetUI() {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
        stopTicker();
        submitBtn.disabled = false;
        progress.hidden = true;
    }

    function fail(msg) {
        showError(msg);
        resetUI();
    }

    function scoreColor(v) {
        if (v >= 80) { return '#10b981'; }
        if (v >= 40) { return '#f59e0b'; }
        return '#ef4444';
    }

    function scoreVerdict(v) {
        if (v >= 80) { return strings.verdictStrong || 'Sterk'; }
        if (v >= 40) { return strings.verdictNeedsWork || 'Kan beter'; }
        return strings.verdictWeak || 'Zwak';
    }

    function scoreRing(score) {
        // r=60 → circumference ≈ 377
        var dash = Math.round(Math.max(0, Math.min(100, score)) / 100 * 377);
        return '<svg class="fgs-ring" viewBox="0 0 140 140" width="140" height="140">' +
            '<circle cx="70" cy="70" r="60" stroke="rgba(255,255,255,.1)" stroke-width="8" fill="none"></circle>' +
            '<circle cx="70" cy="70" r="60" stroke="url(#fgsRingGrad)" stroke-width="8" fill="none" stroke-linecap="round" transform="rotate(-90 70 70)" stroke-dasharray="' + dash + ' 999"></circle>' +
            '<defs><linearGradient id="fgsRingGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#8F39AC"></stop><stop offset="100%" stop-color="#379FD3"></stop></linearGradient></defs>' +
            '<text x="70" y="66" text-anchor="middle" class="fgs-ring-score">' + esc(score) + '</text>' +
            '<text x="70" y="86" text-anchor="middle" class="fgs-ring-max">/ 100</text>' +
        '</svg>';
    }

    function subscoreTiles(sub) {
        sub = sub || {};
        var tiles = [
            { key: 'google',        label: strings.tileGoogle || 'Google' },
            { key: 'ai_visibility', label: strings.tileAi || 'AI Visibility' },
            { key: 'eeat',          label: strings.tileEeat || 'E-E-A-T' },
            { key: 'readability',   label: strings.tileReadability || 'Readability' }
        ];
        var html = '';
        tiles.forEach(function (t) {
            var v = Math.max(0, Math.min(100, parseInt(sub[t.key], 10) || 0));
            html += '<div class="fgs-tile">' +
                '<div class="fgs-tile-label">' + esc(t.label) + '</div>' +
                '<div class="fgs-tile-value" style="color:' + scoreColor(v) + '">' + v + '</div>' +
                '<div class="fgs-tile-verdict">' + esc(scoreVerdict(v)) + '</div>' +
            '</div>';
        });
        return html;
    }

    function kwPills(kw) {
        var pills = [];
        var pos = kw.organic_position;
        if (pos != null && pos <= 3) {
            pills.push({ cls: 'pos', text: strings.pillTop3 || 'Top 3 positie' });
        } else if (pos != null && pos <= 10) {
            pills.push({ cls: 'pos', text: strings.pillPage1 || 'Pagina 1' });
        } else {
            pills.push({ cls: 'neg', text: strings.pillNoTop10 || 'Niet in top 10' });
        }
        if (kw.has_ai_overview) {
            pills.push({ cls: 'pos', text: strings.pillOverview || 'AI Overview aanwezig' });
            pills.push(kw.target_cited
                ? { cls: 'pos', text: strings.pillCited || 'Geciteerd in AI Overview' }
                : { cls: 'neg', text: strings.pillNotCited || 'Niet geciteerd' });
        } else {
            pills.push({ cls: 'warn', text: strings.pillNoOverview || 'Geen AI Overview' });
        }
        if (kw.competitors_count > 0) {
            pills.push({ cls: 'warn', text: kw.competitors_count + ' ' + (strings.pillCompetitors || 'concurrenten geciteerd') });
        }
        return pills.map(function (p) {
            return '<span class="fgs-pill fgs-pill-' + p.cls + '">' + esc(p.text) + '</span>';
        }).join('');
    }

    function kwCard(kw, i) {
        var pos = kw.organic_position;
        var posHtml = pos != null
            ? '<div class="fgs-kw-rank" style="color:' + (pos <= 3 ? '#10b981' : pos <= 10 ? '#10b981' : '#f59e0b') + '">#' + esc(pos) + '</div>'
            : '<div class="fgs-kw-rank" style="color:#9ca3af">—</div>';

        var overview = kw.has_ai_overview
            ? '<div class="fgs-kw-flag" style="color:#10b981">' + esc(strings.aiOverviewYes || 'Aanwezig') + '</div>'
            : '<div class="fgs-kw-flag" style="color:#9ca3af">' + esc(strings.aiOverviewNo || 'Geen') + '</div>';

        var cited = kw.target_cited
            ? '<div class="fgs-kw-flag" style="color:#10b981">' + esc(strings.cited || 'Geciteerd') + '</div>'
            : '<div class="fgs-kw-flag" style="color:#ef4444">' + esc(strings.notCited || 'Niet gevonden') + '</div>';

        return '<div class="fgs-kw">' +
            '<div class="fgs-kw-head">' +
                '<span class="fgs-kw-badge">' + (i + 1) + '</span>' +
                '<div class="fgs-kw-name">"' + esc(kw.keyword) + '"</div>' +
                '<div class="fgs-kw-stats">' +
                    '<div class="fgs-kw-stat"><div class="fgs-kw-stat-label">Google</div>' + posHtml + '</div>' +
                    '<div class="fgs-kw-stat"><div class="fgs-kw-stat-label">' + esc(strings.aiOverview || 'AI Overview') + '</div>' + overview + '</div>' +
                    '<div class="fgs-kw-stat"><div class="fgs-kw-stat-label">' + esc(strings.citation || 'Citatie') + '</div>' + cited + '</div>' +
                '</div>' +
            '</div>' +
            '<div class="fgs-kw-pills">' + kwPills(kw) + '</div>' +
        '</div>';
    }

    var REC_LEVELS = [
        { badge: 'high',   icon: '●' },
        { badge: 'high',   icon: '●' },
        { badge: 'medium', icon: '▲' },
        { badge: 'medium', icon: '▲' },
        { badge: 'low',    icon: 'ℹ' }
    ];

    function recItem(text, i) {
        var lvl = REC_LEVELS[Math.min(i, REC_LEVELS.length - 1)];
        var badgeText = lvl.badge === 'high' ? (strings.prioHigh || 'Hoog')
            : lvl.badge === 'medium' ? (strings.prioMedium || 'Medium')
            : (strings.prioLow || 'Laag');
        return '<div class="fgs-rec">' +
            '<div class="fgs-rec-icon fgs-rec-icon-' + lvl.badge + '"><span>' + lvl.icon + '</span></div>' +
            '<div class="fgs-rec-body">' + esc(text) + '</div>' +
            '<span class="fgs-rec-badge fgs-rec-badge-' + lvl.badge + '">' + esc(badgeText) + '</span>' +
        '</div>';
    }

    function renderResult(teaser, scanUrl) {
        var s = teaser || {};
        var score = parseInt(s.score, 10) || 0;

        var kwCards = (s.keywords_analysis || []).map(kwCard).join('');
        var recs = (s.recommendations || []).map(recItem).join('');

        var html = '' +
            '<div class="fgs-result-head">' +
                '<span class="fgs-badge-pill">' + esc(strings.resultBadge || 'Jouw resultaat') + '</span>' +
                '<h2 class="fgs-result-title">' + esc(strings.resultTitle || 'Jouw GEO Scan resultaat') + '</h2>' +
                '<p class="fgs-result-meta">' + esc(scanUrl) + ' · ' + (s.keywords_analysis || []).length + ' ' + esc(strings.keywordsWord || 'keywords') + '</p>' +
            '</div>' +

            '<div class="fgs-hero">' +
                '<div class="fgs-hero-ring">' + scoreRing(score) + '</div>' +
                '<div class="fgs-hero-info">' +
                    '<div class="fgs-hero-title">' + esc(strings.totalScore || 'Totale GEO Score') + '</div>' +
                    '<div class="fgs-hero-label">' + esc(s.label || '') + '</div>' +
                    '<div class="fgs-tiles">' + subscoreTiles(s.subscores) + '</div>' +
                '</div>' +
            '</div>' +

            (kwCards ? '<div class="fgs-kws">' + kwCards + '</div>' : '') +

            (recs ? '<div class="fgs-recs">' +
                '<h3 class="fgs-recs-title">' + esc(strings.recTitle || 'Aanbevolen acties') + '</h3>' +
                '<p class="fgs-recs-sub">' + esc(strings.recSub || 'Prioriteer deze verbeterpunten om je GEO Score te verhogen.') + '</p>' +
                '<div class="fgs-recs-list">' + recs + '</div>' +
            '</div>' : '') +

            '<div class="fgs-cta">' +
                '<h4>' + esc(strings.ctaTitle || 'Wil je het volledige rapport?') + '</h4>' +
                '<p>' + esc(strings.ctaText || 'Wij nemen contact met je op om de volledige analyse en verbeterpunten door te nemen.') + '</p>' +
            '</div>';

        resultBox.innerHTML = html;
        resultBox.hidden = false;
        form.hidden = true;
        resultBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function pollStatus(scanId, scanUrl) {
        fetch(restUrl + '/geo-scan/' + encodeURIComponent(scanId) + '/status?_=' + Date.now(), {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || data.success === false) {
                // transient — keep polling
                if (Date.now() - pollStarted > MAX_POLL_MS) {
                    fail(strings.timeout);
                    return;
                }
                pollTimer = setTimeout(function () { pollStatus(scanId, scanUrl); }, 4000);
                return;
            }

            if (data.progress >= 0) {
                setProgress(data.progress, data.progress_label);
            }

            if (data.status === 'completed') {
                reported = 100;
                shown = 100;
                render();
                stopTicker();
                clearPending();
                renderResult(data.teaser, scanUrl);
                return;
            }

            if (data.status === 'failed') {
                clearPending();
                fail(data.error || strings.error);
                return;
            }

            if (Date.now() - pollStarted > MAX_POLL_MS) {
                fail(strings.timeout);
                return;
            }

            pollTimer = setTimeout(function () { pollStatus(scanId, scanUrl); }, 3000);
        })
        .catch(function () {
            if (Date.now() - pollStarted > MAX_POLL_MS) {
                fail(strings.timeout);
                return;
            }
            pollTimer = setTimeout(function () { pollStatus(scanId, scanUrl); }, 5000);
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errorBox.hidden = true;
        resultBox.hidden = true;

        var url = form.querySelector('#fgs-url').value.trim();
        var email = form.querySelector('#fgs-email').value.trim();
        var name = form.querySelector('#fgs-name').value.trim();
        var company = form.querySelector('#fgs-company').value.trim();
        var consent = form.querySelector('#fgs-consent').checked;
        var honeypot = form.querySelector('input[name="website"]').value;
        var keywords = Array.prototype.slice.call(form.querySelectorAll('.fgs-keyword'))
            .map(function (i) { return i.value.trim(); })
            .filter(function (k) { return k.length > 0; });

        if (!url || !/^https?:\/\/.+\..+/.test(url)) {
            showError('Vul een geldige URL in (incl. https://).');
            return;
        }
        if (keywords.length === 0) {
            showError('Vul minimaal 1 keyword in.');
            return;
        }
        if (!email || email.indexOf('@') === -1) {
            showError('Vul een geldig e-mailadres in.');
            return;
        }
        if (!consent) {
            showError('Vink de toestemming aan om door te gaan.');
            return;
        }

        submitBtn.disabled = true;
        progress.hidden = false;
        shown = 0;
        reported = 0;
        setProgress(0, strings.queued);
        startTicker();

        fetch(restUrl + '/geo-scan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                url: url,
                keywords: keywords,
                email: email,
                name: name,
                company: company,
                consent: consent,
                website: honeypot
            })
        })
        .then(function (r) { return r.json().then(function (d) { return { status: r.status, data: d }; }); })
        .then(function (res) {
            var data = res.data || {};
            if (res.status === 200 && data.success && data.scan_id) {
                savePending(data.scan_id, url);
                pollStarted = Date.now();
                pollStatus(data.scan_id, url);
                return;
            }
            // Honeypot success (scan_id 0) — silently do nothing.
            if (res.status === 200 && data.success) {
                resetUI();
                return;
            }
            fail(data.message || strings.error);
        })
        .catch(function () {
            fail(strings.error);
        });
    });

    // Resume a scan that was still running when the visitor left or refreshed
    // the page — the teaser is shown as soon as the portal has finished it.
    var pending = loadPending();
    if (pending) {
        submitBtn.disabled = true;
        progress.hidden = false;
        shown = 0;
        reported = 0;
        setProgress(0, strings.queued);
        startTicker();
        pollStarted = Date.now();
        pollStatus(pending.id, pending.url || '');
    }
})();
