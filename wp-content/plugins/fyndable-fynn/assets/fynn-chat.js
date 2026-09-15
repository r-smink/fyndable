(function () {
    'use strict';

    const config = window.fynnConfig || {};
    const restUrl = (config.restUrl || '').replace(/\/$/, '');

    if (!restUrl) {
        return;
    }

    const root = document.getElementById('fynn-chat-root');
    const fab = document.getElementById('fynn-fab');
    const panel = document.getElementById('fynn-panel');
    const closeBtn = document.getElementById('fynn-close');
    const messages = document.getElementById('fynn-messages');
    const suggestions = document.getElementById('fynn-suggestions');
    const input = document.getElementById('fynn-input');
    const sendBtn = document.getElementById('fynn-send');
    const status = panel ? panel.querySelector('.fynn-status') : null;
    const fabAvatar = fab ? fab.querySelector('.fynn-avatar') : null;

    let history = [];

    if (!root || !fab || !panel || !input || !sendBtn) {
        return;
    }

    function getFynnConfig() {
        fetch(restUrl + '/config', {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data) {
                    if (data.i18n) {
                        config.i18n = Object.assign({}, config.i18n, data.i18n);
                        input.placeholder = config.i18n.placeholder || input.placeholder;
                    }
                    if (data.suggestedQuestions && Array.isArray(data.suggestedQuestions) && suggestions) {
                        renderSuggestions(data.suggestedQuestions);
                    }
                }
            })
            .catch(function () {});
    }

    function renderSuggestions(questions) {
        if (!suggestions) return;
        suggestions.innerHTML = '';
        questions.forEach(function (q) {
            const btn = document.createElement('button');
            btn.className = 'fynn-suggestion';
            btn.type = 'button';
            btn.textContent = q;
            btn.addEventListener('click', function () {
                input.value = q;
                send();
            });
            suggestions.appendChild(btn);
        });
    }

    function togglePanel(open) {
        const shouldOpen = typeof open === 'boolean' ? open : panel.classList.contains('fynn-hidden');
        panel.classList.toggle('fynn-hidden', !shouldOpen);
        panel.setAttribute('aria-hidden', String(!shouldOpen));
        if (shouldOpen) {
            input.focus();
            setPose(fabAvatar, 'wave');
        } else {
            setPose(fabAvatar, 'idle');
        }
    }

    function setPose(element, pose) {
        if (!element) return;
        element.setAttribute('data-pose', pose);
    }

    function appendMessage(text, role) {
        const div = document.createElement('div');
        div.className = 'fynn-message fynn-message--' + role;
        div.textContent = text;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        return div;
    }

    function removeMessage(element) {
        if (element && element.parentNode) {
            element.parentNode.removeChild(element);
        }
    }

    function setStatus(text) {
        if (status) {
            status.textContent = text;
        }
    }

    async function send() {
        const question = input.value.trim();
        if (!question) return;

        appendMessage(question, 'user');
        history.push({ role: 'user', content: question });

        input.value = '';
        input.disabled = true;
        sendBtn.disabled = true;

        setPose(fabAvatar, 'thinking');
        setStatus(config.i18n && config.i18n.thinking ? config.i18n.thinking : 'Fynn denkt na...');

        const thinking = appendMessage('...', 'assistant');
        thinking.classList.add('fynn-message--thinking');

        try {
            const response = await fetch(restUrl + '/public/ask', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    question: question,
                    history: history.slice(0, -1),
                }),
            });

            const result = await response.json();
            removeMessage(thinking);

            if (!result || !result.success) {
                appendMessage(result && result.message ? result.message : getI18n('error', 'Er ging iets mis.'), 'error');
                setPose(fabAvatar, 'idle');
                setStatus(getI18n('status', 'Klaar om te helpen'));
                return;
            }

            appendMessage(result.answer, 'assistant');
            history.push({ role: 'assistant', content: result.answer });

            setPose(fabAvatar, 'found');
            setStatus(getI18n('found', 'Antwoord gevonden'));
            setTimeout(function () {
                setPose(fabAvatar, 'idle');
                setStatus(getI18n('status', 'Klaar om te helpen'));
            }, 2000);
        } catch (err) {
            removeMessage(thinking);
            appendMessage(getI18n('error', 'Er ging iets mis. Probeer het opnieuw.'), 'error');
            setPose(fabAvatar, 'idle');
            setStatus(getI18n('status', 'Klaar om te helpen'));
        } finally {
            input.disabled = false;
            sendBtn.disabled = false;
            input.focus();
        }
    }

    function getI18n(key, fallback) {
        return (config.i18n && config.i18n[key]) ? config.i18n[key] : fallback;
    }

    fab.addEventListener('click', function () { togglePanel(true); });
    closeBtn.addEventListener('click', function () { togglePanel(false); });
    sendBtn.addEventListener('click', send);
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            send();
        }
    });

    getFynnConfig();
})();
