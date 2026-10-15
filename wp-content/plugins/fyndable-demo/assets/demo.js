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

    function shellReady() {
        frame = document.getElementById('fyndable-frame');
        return !!frame;
    }

    function createControls() {
        if (document.getElementById('fyndable-demo-controls')) return;
        var controls = document.createElement('div');
        controls.id = 'fyndable-demo-controls';
        controls.innerHTML = '<span class="fyndable-demo-pill">' + escapeHtml(fyndableDemo.labels.demo) + '</span>' +
            '<button type="button" data-demo-action="start">Tour</button>' +
            '<button type="button" data-demo-action="autoplay">Autoplay</button>' +
            '<button type="button" data-demo-action="reset">' + escapeHtml(fyndableDemo.labels.reset) + '</button>';
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
        if (panel) panel.classList.remove('is-visible');
    }

    function show(index) {
        if (!running || !steps[index]) return;
        clearTimeout(timer);
        clearHighlight();
        current = index;
        var step = steps[index];
        renderPanel(step);
        navigate(step.slug, function () {
            prepareStep(step, function () {
                highlight(step.selector);
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
                if (viewButton) {
                    viewButton.click();
                    window.setTimeout(function () {
                        step.selector = '#tc-result';
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

    function highlight(selector) {
        if (!frame) return;
        try {
            var doc = frame.contentDocument;
            var target = doc.querySelector(selector) || doc.querySelector('.wrap') || doc.body;
            target.classList.add('fyndable-demo-highlight');
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (e) {}
    }

    function clearHighlight() {
        if (!frame) return;
        try {
            frame.contentDocument.querySelectorAll('.fyndable-demo-highlight').forEach(function (node) {
                node.classList.remove('fyndable-demo-highlight');
            });
        } catch (e) {}
    }

    function renderPanel(step) {
        if (!panel) return;
        var last = current === steps.length - 1;
        panel.innerHTML = '<button class="fyndable-demo-close" data-tour-action="close" aria-label="Sluiten">&times;</button>' +
            '<div class="fyndable-demo-progress">Stap ' + (current + 1) + ' van ' + steps.length + '</div>' +
            '<h2>' + escapeHtml(step.title) + '</h2>' +
            '<p>' + escapeHtml(step.text) + '</p>' +
            '<div class="fyndable-demo-actions">' +
                '<button type="button" data-tour-action="previous"' + (current === 0 ? ' disabled' : '') + '>' + escapeHtml(fyndableDemo.labels.previous) + '</button>' +
                '<button type="button" data-tour-action="toggle">' + escapeHtml(autoplay ? fyndableDemo.labels.pause : fyndableDemo.labels.play) + '</button>' +
                '<button type="button" class="primary" data-tour-action="next">' + escapeHtml(last ? fyndableDemo.labels.finish : fyndableDemo.labels.next) + '</button>' +
            '</div>';
    }

    function schedule() {
        clearTimeout(timer);
        if (!running || !autoplay) return;
        timer = window.setTimeout(function () {
            if (current >= steps.length - 1) stop();
            else show(current + 1);
        }, 6500);
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
