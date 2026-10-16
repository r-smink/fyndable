(function () {
    'use strict';

    if (typeof window.fyndableDemo === 'undefined') {
        return;
    }

    var frame;
    var steps = window.fyndableDemo.steps || [];
    var current = 0;
    var running = false;
    var autoplay = false;
    var timer = null;
    var panel;
    var badge;
    var audio = null;
    var muted = false;
    var audioEnded = false;
    var PAUSE_MS = 2500; // breathing room after each voiceover finishes

    function labels() {
        return fyndableDemo.labels || {};
    }

    function shellReady() {
        frame = document.getElementById('fyndable-frame');
        return !!frame;
    }

    function createControls() {
        if (document.getElementById('fyndable-demo-controls')) return;
        var controls = document.createElement('div');
        controls.id = 'fyndable-demo-controls';
        controls.innerHTML = '<span class="fyndable-demo-pill">' + escapeHtml(labels().demo) + '</span>' +
            '<button type="button" data-demo-action="start">Tour</button>' +
            '<button type="button" data-demo-action="autoplay">Autoplay</button>' +
            '<button type="button" data-demo-action="reset">' + escapeHtml(labels().reset) + '</button>';
        document.body.appendChild(controls);
        controls.addEventListener('click', function (event) {
            var action = event.target.getAttribute('data-demo-action');
            if (action === 'start') start(false);
            if (action === 'autoplay') start(true);
            if (action === 'reset') resetDemo();
        });
    }

    function createPanel() {
        panel = document.getElementById('fyndable-demo-tour');
        if (panel) return;
        panel = document.createElement('aside');
        panel.id = 'fyndable-demo-tour';
        panel.setAttribute('aria-live', 'polite');
        document.body.appendChild(panel);
        panel.addEventListener('click', function (event) {
            var action = event.target.getAttribute('data-tour-action');
            if (action === 'close') stop();
            if (action === 'previous') {
                autoplay = false;
                show(Math.max(0, current - 1));
            }
            if (action === 'next') {
                autoplay = false;
                current >= steps.length - 1 ? stop() : show(current + 1);
            }
            if (action === 'toggle') {
                autoplay = !autoplay;
                renderPanel(steps[current]);
                schedule();
            }
            if (action === 'mute') {
                muted = !muted;
                if (audio) audio.muted = muted;
                renderPanel(steps[current]);
            }
        });
        panel.addEventListener('change', function (event) {
            if (event.target.id === 'fyndable-demo-jump') {
                var idx = parseInt(event.target.value, 10);
                if (!isNaN(idx) && steps[idx]) {
                    autoplay = false;
                    show(idx);
                }
            }
        });
    }

    function start(auto) {
        if (!steps.length) return;
        running = true;
        autoplay = !!auto;
        current = 0;
        createPanel();
        panel.classList.add('is-visible');
        show(0);
    }

    function stop() {
        running = false;
        autoplay = false;
        clearTimeout(timer);
        clearHighlight();
        stopAudio();
        if (panel) {
            panel.classList.remove('is-visible');
            panel.classList.remove('fyndable-pos-top', 'fyndable-pos-bottom', 'fyndable-pos-left', 'fyndable-pos-right');
        }
    }

    function show(index) {
        if (!running || !steps[index]) return;
        clearTimeout(timer);
        clearHighlight();
        stopAudio();
        current = index;
        var step = steps[index];
        renderPanel(step);
        navigate(step.slug, function () {
            prepareStep(step, function () {
                highlight(step);
                positionPanel(step);
                playAudio(step, function (played) {
                    if (played && autoplay) {
                        // Voiceover finished — give the viewer a moment, then advance.
                        clearTimeout(timer);
                        audioEnded = true;
                        timer = window.setTimeout(advanceOnce, PAUSE_MS);
                    }
                });
                schedule();
            });
        });
    }

    function prepareStep(step, done) {
        if (step.slug !== 'ai-seo-topic-clusters' || !frame) {
            done();
            return;
        }
        var attempts = 0;
        var openSavedCluster = function () {
            attempts++;
            try {
                var viewButton = frame.contentDocument.querySelector('.tc-view-saved');
                var result = frame.contentDocument.getElementById('tc-result');
                if (result && result.style.display !== 'none') {
                    done();
                    return;
                }
                if (viewButton) {
                    viewButton.click();
                    window.setTimeout(function () {
                        if (step.selector === '#tc-saved-list') {
                            step.selector = '#tc-pillar';
                        }
                        done();
                    }, 600);
                    return;
                }
            } catch (e) {}
            if (attempts < 12) window.setTimeout(openSavedCluster, 200);
            else done();
        };
        openSavedCluster();
    }

    function navigate(slug, done) {
        var link = document.querySelector('.fyndable-sidebar-nav a[data-slug="' + cssEscape(slug) + '"]');
        if (!frame || !link) {
            done();
            return;
        }

        var currentSlug = '';
        try {
            currentSlug = new URL(frame.src).searchParams.get('page') || '';
        } catch (e) {}
        if (currentSlug === slug && frame.contentDocument && frame.contentDocument.readyState === 'complete') {
            window.setTimeout(done, 250);
            return;
        }

        var loaded = false;
        var onLoad = function () {
            if (loaded) return;
            loaded = true;
            frame.removeEventListener('load', onLoad);
            window.setTimeout(done, 350);
        };
        frame.addEventListener('load', onLoad);
        link.click();
        window.setTimeout(onLoad, 5000);
    }

    function findTarget(step) {
        if (!frame || !frame.contentDocument) return null;
        var doc = frame.contentDocument;
        try {
            return doc.querySelector(step.selector) || doc.querySelector('.wrap') || doc.body;
        } catch (e) {
            return null;
        }
    }

    function highlight(step) {
        var target = findTarget(step);
        if (!target) return;
        try {
            target.classList.add('fyndable-demo-highlight');
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            badge = target.ownerDocument.createElement('div');
            badge.className = 'fyndable-demo-badge';
            badge.textContent = (current + 1);
            badge.setAttribute('aria-hidden', 'true');
            target.appendChild(badge);
        } catch (e) {}
    }

    function clearHighlight() {
        if (badge && badge.parentNode) {
            badge.parentNode.removeChild(badge);
        }
        badge = null;
        if (!frame) return;
        try {
            frame.contentDocument.querySelectorAll('.fyndable-demo-highlight').forEach(function (node) {
                node.classList.remove('fyndable-demo-highlight');
            });
        } catch (e) {}
    }

    function positionPanel(step) {
        if (!panel) return;
        var frameRect = frame ? frame.getBoundingClientRect() : null;
        var target = findTarget(step);
        var rect = null;
        if (target) {
            try { rect = target.getBoundingClientRect(); } catch (e) {}
        }

        var pw = 440;
        var margin = 18;
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var top, left;
        var pos = 'right';

        panel.classList.remove('fyndable-pos-top', 'fyndable-pos-bottom', 'fyndable-pos-left', 'fyndable-pos-right');

        if (rect && frameRect) {
            var elLeft = frameRect.left + rect.left;
            var elTop = frameRect.top + rect.top;
            var elRight = frameRect.left + rect.right;
            var elBottom = frameRect.top + rect.bottom;
            var elW = rect.width;
            var elH = rect.height;

            // Prefer right side if there is room next to the element
            var spaceRight = vw - elRight;
            var spaceLeft = elLeft;
            var spaceBelow = vh - elBottom;
            var spaceAbove = elTop;

            if (spaceRight >= pw + margin * 2) {
                pos = 'right';
                left = elRight + margin;
                top = elTop + elH / 2;
            } else if (spaceLeft >= pw + margin * 2) {
                pos = 'left';
                left = elLeft - pw - margin;
                top = elTop + elH / 2;
            } else if (spaceBelow >= 320) {
                pos = 'bottom';
                left = elLeft + elW / 2 - pw / 2;
                top = elBottom + margin;
            } else if (spaceAbove >= 320) {
                pos = 'top';
                left = elLeft + elW / 2 - pw / 2;
                top = elTop - margin;
            } else {
                pos = 'right';
                left = Math.min(elRight + margin, vw - pw - margin);
                top = elTop + elH / 2;
            }
        } else {
            left = vw - pw - 80;
            top = 120;
        }

        // Clamp within viewport (estimate height; re-clamp after layout)
        left = Math.max(margin, Math.min(left, vw - pw - margin));
        top = Math.max(70, top);

        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
        panel.style.bottom = 'auto';
        panel.classList.add('fyndable-pos-' + pos);

        // Re-clamp vertically once height is known
        window.requestAnimationFrame(function () {
            var ph = panel.offsetHeight;
            var newTop = Math.max(70, Math.min(top, vh - ph - 20));
            panel.style.top = newTop + 'px';
            if (pos === 'top') {
                // Arrow points down toward the element
                panel.style.top = Math.max(70, (rect ? frameRect.top + rect.top : 200) - ph - margin) + 'px';
            }
        });
    }

    function stopAudio() {
        if (audio) {
            try { audio.pause(); } catch (e) {}
            audio = null;
        }
        audioEnded = false;
    }

    function playAudio(step, done) {
        stopAudio();
        if (!step.audio || muted) {
            done(false);
            return;
        }
        var url = (fyndableDemo.audioBaseUrl || '') + step.audio;
        var called = false;
        function finish(result) {
            if (called) return;
            called = true;
            done(result);
        }
        try {
            audio = new Audio(url);
            audio.muted = muted;
            audio.preload = 'auto';
            audio.addEventListener('ended', function () {
                audioEnded = true;
                finish(true);
            });
            audio.addEventListener('error', function () {
                audio = null;
                finish(false);
            });
            audio.addEventListener('playing', function () {
                // Audio actually started: reset fallback to the real remaining length.
                if (autoplay && audio && !isNaN(audio.duration) && audio.duration > 0) {
                    clearTimeout(timer);
                    timer = window.setTimeout(function () {
                        if (!audioEnded) advanceOnce();
                    }, Math.round(audio.duration * 1000) + PAUSE_MS);
                }
            });
            audio.addEventListener('canplay', function () {
                if (autoplay && audio && audio.paused) {
                    audio.play().catch(function () {});
                }
            });
            // Try to play immediately if the file is ready fast enough
            var p = audio.play();
            if (p && p.catch) {
                p.catch(function () {
                    // Autoplay blocked: canplay will retry; fallback timer covers us.
                });
            }
        } catch (e) {
            finish(false);
        }
    }

    function renderPanel(step) {
        if (!panel || !step) return;
        var L = labels();
        var last = current === steps.length - 1;
        var total = steps.length;
        var pct = Math.round(((current + 1) / total) * 100);

        var html = '';
        html += '<button class="fyndable-demo-close" data-tour-action="close" aria-label="Sluiten">&times;</button>';
        html += '<div class="fyndable-demo-meta">';
        if (step.section) {
            html += '<span class="fyndable-demo-section">' + escapeHtml(step.section) + '</span>';
        }
        if (step.substep) {
            html += '<span class="fyndable-demo-substep">onderdeel ' + escapeHtml(step.substep) + '</span>';
        }
        html += '</div>';
        html += '<div class="fyndable-demo-progressbar"><div class="fyndable-demo-progressbar-fill" style="width:' + pct + '%"></div></div>';
        html += '<div class="fyndable-demo-progress">' + escapeHtml(L.stepOf ? L.stepOf : 'Stap') + ' ' + (current + 1) + ' ' + escapeHtml(L.of ? L.of : 'van') + ' ' + total + '</div>';
        html += '<h2>' + escapeHtml(step.title) + '</h2>';

        if (step.what) {
            html += '<div class="fyndable-demo-block fyndable-demo-what">' +
                '<h3>' + escapeHtml(L.whatTitle || 'Wat doet het?') + '</h3>' +
                '<p>' + escapeHtml(step.what) + '</p></div>';
        }
        if (step.how && step.how.length) {
            html += '<div class="fyndable-demo-block fyndable-demo-how">' +
                '<h3>' + escapeHtml(L.howTitle || 'Hoe gebruik je het?') + '</h3><ol>';
            step.how.forEach(function (item) {
                html += '<li>' + escapeHtml(item) + '</li>';
            });
            html += '</ol></div>';
        }
        if (step.tip) {
            html += '<div class="fyndable-demo-tip"><strong>' + escapeHtml(L.tipTitle || 'Tip') + ':</strong> ' + escapeHtml(step.tip) + '</div>';
        }

        html += '<div class="fyndable-demo-actions">' +
            '<button type="button" data-tour-action="mute" class="fyndable-demo-audio" title="' + escapeHtml(muted ? (L.unmute || 'Geluid aan') : (L.mute || 'Geluid uit')) + '">' +
                (muted ? '🔇' : '🔊') +
            '</button>' +
            '<button type="button" data-tour-action="previous"' + (current === 0 ? ' disabled' : '') + '>' + escapeHtml(L.previous) + '</button>' +
            '<button type="button" data-tour-action="toggle">' + escapeHtml(autoplay ? L.pause : L.play) + '</button>' +
            '<button type="button" class="primary" data-tour-action="next">' + escapeHtml(last ? L.finish : L.next) + '</button>' +
            '</div>';

        // Chapter jump selector
        var curSection = step.section || '';
        var seen = {};
        var options = '';
        steps.forEach(function (s, i) {
            if (s.section && !seen[s.section]) {
                seen[s.section] = true;
                options += '<option value="' + i + '"' + (s.section === curSection ? ' selected' : '') + '>' + escapeHtml(s.section) + '</option>';
            }
        });
        if (options) {
            html += '<div class="fyndable-demo-jump-wrap"><select id="fyndable-demo-jump" aria-label="' + escapeHtml(L.jumpTo || 'Ga naar onderdeel') + '">' + options + '</select></div>';
        }

        panel.innerHTML = html;
    }

    function advanceOnce() {
        if (!running || !autoplay) return;
        if (current >= steps.length - 1) stop();
        else show(current + 1);
    }

    function schedule() {
        clearTimeout(timer);
        if (!running || !autoplay) return;

        var step = steps[current];
        // Fallback wait matches the voiceover length plus breathing room.
        // The audio 'ended' handler normally cancels this timer early.
        var fallbackMs = step.duration || 9000;
        if (audio && !isNaN(audio.duration) && audio.duration > 0) {
            fallbackMs = Math.max(fallbackMs, Math.round(audio.duration * 1000) + PAUSE_MS);
        }
        timer = window.setTimeout(function () {
            if (!audioEnded) advanceOnce();
        }, fallbackMs);
    }

    function setupLiveMiddleware() {
        if (!fyndableDemo.liveEnabled || !window.wp || !window.wp.apiFetch || typeof window.wp.apiFetch.use !== 'function') return;
        window.wp.apiFetch.use(function (options, next) {
            var path = options.path || options.url || '';
            if (path.indexOf('sseo-ai/v1/content-brief') !== -1 && path.indexOf('/score') === -1) {
                var approved = window.confirm('Deze stap gebruikt een live AI-call en één demo-credit. Doorgaan?');
                if (approved) {
                    options.headers = Object.assign({}, options.headers || {}, { 'X-Fyndable-Demo-Live': '1' });
                }
            }
            return next(options);
        });
    }

    function setupSettings() {
        var button = document.getElementById('fyndable-demo-save-settings');
        if (!button) return;
        button.addEventListener('click', function () {
            var body = new URLSearchParams();
            body.append('action', 'fyndable_demo_save_settings');
            body.append('nonce', fyndableDemo.nonce);
            body.append('live_enabled', document.getElementById('fyndable-demo-live').checked ? '1' : '0');
            button.disabled = true;
            fetch(fyndableDemo.ajaxUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            }).then(function (response) { return response.json(); }).then(function (result) {
                if (result.success) window.location.reload();
            }).finally(function () { button.disabled = false; });
        });
    }

    function resetDemo() {
        var body = new URLSearchParams();
        body.append('action', 'fyndable_demo_reset');
        body.append('nonce', fyndableDemo.nonce);
        fetch(fyndableDemo.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (response) { return response.json(); }).then(function (result) {
            if (result.success && frame) frame.contentWindow.location.reload();
        });
    }

    function escapeHtml(value) {
        var element = document.createElement('div');
        element.textContent = String(value || '');
        return element.innerHTML;
    }

    function cssEscape(value) {
        return window.CSS && window.CSS.escape ? window.CSS.escape(value) : value.replace(/[^a-zA-Z0-9_-]/g, '');
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupLiveMiddleware();
        setupSettings();
        if (!shellReady()) return;
        createControls();
        createPanel();
        document.addEventListener('keydown', function (event) {
            if (!running || ['INPUT', 'TEXTAREA', 'SELECT'].indexOf(event.target.tagName) !== -1) return;
            if (event.key === 'ArrowLeft' && current > 0) {
                autoplay = false;
                show(current - 1);
            }
            if (event.key === 'ArrowRight') {
                autoplay = false;
                current >= steps.length - 1 ? stop() : show(current + 1);
            }
            if (event.key === 'Escape') stop();
        });
        if (fyndableDemo.autoStart) {
            window.setTimeout(function () { start(!!fyndableDemo.autoplay); }, 700);
        }
    });
})();
