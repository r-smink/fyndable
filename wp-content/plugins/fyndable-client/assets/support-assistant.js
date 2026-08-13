(function () {
    'use strict';

    var settings = window.SupportAssistantSettings || {};
    var restUrl = settings.restUrl || '';
    var nonce = settings.nonce || '';
    var chatbot = settings.chatbot || {};
    var colors = settings.colors || {};
    var i18n = settings.i18n || {};
    var suggested = settings.suggestedQuestions || [];

    var STORAGE_KEY = 'sseo_ai_support_chat_history';
    var isOpen = false;
    var messages = [];
    var ticketMode = false;

    // --- DOM ---
    var widget, button, panel, messagesEl, inputEl, sendBtn;

    function init() {
        if (!restUrl) return;
        loadHistory();
        injectCSS();
        renderButton();
        renderPanel();
    }

    function injectCSS() {
        // Inject dynamic colors as CSS custom properties
        var style = document.createElement('style');
        style.textContent = ':root {' +
            '--sseo-sa-primary: ' + (colors.primary || '#379fd3') + ';' +
            '--sseo-sa-secondary: ' + (colors.secondary || '#8f39ac') + ';' +
        '}';
        document.head.appendChild(style);
    }

    function renderButton() {
        button = document.createElement('div');
        button.className = 'sseo-sa-fab';
        button.setAttribute('role', 'button');
        button.setAttribute('aria-label', i18n.title || 'Support Assistant');
        button.title = i18n.title || 'Support Assistant';

        if (chatbot.avatarUrl) {
            button.innerHTML = '<img src="' + escapeHtml(chatbot.avatarUrl) + '" alt="" class="sseo-sa-fab-avatar">';
        } else {
            button.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
        }

        button.addEventListener('click', togglePanel);
        document.body.appendChild(button);
    }

    function renderPanel() {
        panel = document.createElement('div');
        panel.className = 'sseo-sa-panel';
        panel.style.display = 'none';

        var headerHtml = '<div class="sseo-sa-header">' +
            '<div class="sseo-sa-header-info">' +
                (chatbot.avatarUrl
                    ? '<img src="' + escapeHtml(chatbot.avatarUrl) + '" alt="" class="sseo-sa-header-avatar">'
                    : '<div class="sseo-sa-header-avatar-placeholder">' + escapeHtml(initials(chatbot.name || 'F')) + '</div>') +
                '<div class="sseo-sa-header-text">' +
                    '<div class="sseo-sa-header-name">' + escapeHtml(chatbot.name || 'Fyndable Assistant') + '</div>' +
                    '<div class="sseo-sa-header-status">' + escapeHtml(i18n.greeting || 'Online') + '</div>' +
                '</div>' +
            '</div>' +
            '<button class="sseo-sa-close" aria-label="Close">&times;</button>' +
        '</div>';

        panel.innerHTML = headerHtml +
            '<div class="sseo-sa-messages"></div>' +
            '<div class="sseo-sa-suggestions"></div>' +
            '<div class="sseo-sa-input-wrap">' +
                '<input type="text" class="sseo-sa-input" placeholder="' + escapeHtml(i18n.placeholder || 'Stel je vraag...') + '">' +
                '<button class="sseo-sa-send" aria-label="Send">' +
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>' +
                '</button>' +
            '</div>';

        document.body.appendChild(panel);

        messagesEl = panel.querySelector('.sseo-sa-messages');
        inputEl = panel.querySelector('.sseo-sa-input');
        sendBtn = panel.querySelector('.sseo-sa-send');

        panel.querySelector('.sseo-sa-close').addEventListener('click', closePanel);
        sendBtn.addEventListener('click', sendMessage);
        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendMessage();
            }
        });

        renderMessages();
        renderSuggestions();
    }

    function renderSuggestions() {
        var sugEl = panel.querySelector('.sseo-sa-suggestions');
        if (!sugEl) return;
        if (messages.length > 0 || suggested.length === 0) {
            sugEl.innerHTML = '';
            return;
        }
        var html = '';
        suggested.forEach(function (q) {
            html += '<button class="sseo-sa-suggestion">' + escapeHtml(q) + '</button>';
        });
        sugEl.innerHTML = html;
        sugEl.querySelectorAll('.sseo-sa-suggestion').forEach(function (btn) {
            btn.addEventListener('click', function () {
                inputEl.value = btn.textContent;
                sendMessage();
            });
        });
    }

    function renderMessages() {
        if (!messagesEl) return;
        messagesEl.innerHTML = '';
        messages.forEach(function (msg) {
            var div = document.createElement('div');
            div.className = 'sseo-sa-message sseo-sa-message-' + msg.role;

            if (msg.role === 'assistant') {
                var sourceLabel = '';
                if (msg.source === 'manual') {
                    sourceLabel = '<span class="sseo-sa-source sseo-sa-source-manual">' + escapeHtml(i18n.sourceManual || 'Uit handleiding') + '</span>';
                } else if (msg.source === 'ai') {
                    sourceLabel = '<span class="sseo-sa-source sseo-sa-source-ai">' + escapeHtml(i18n.sourceAi || 'AI-assistent') + '</span>';
                }
                div.innerHTML = '<div class="sseo-sa-bubble">' + formatAnswer(msg.content) + sourceLabel + '</div>';
            } else if (msg.role === 'user') {
                div.innerHTML = '<div class="sseo-sa-bubble">' + escapeHtml(msg.content) + '</div>';
            } else if (msg.role === 'system') {
                div.innerHTML = '<div class="sseo-sa-bubble sseo-sa-bubble-system">' + escapeHtml(msg.content) + '</div>';
            }

            // Ticket button
            if (msg.ticketSuggested && !msg.ticketCreated) {
                var ticketBtn = document.createElement('button');
                ticketBtn.className = 'sseo-sa-ticket-btn';
                ticketBtn.textContent = i18n.createTicket || 'Maak support ticket';
                ticketBtn.addEventListener('click', function () { showTicketForm(msg); });
                div.appendChild(ticketBtn);
            }

            // Ticket created link
            if (msg.ticketCreated && msg.ticketUrl) {
                var link = document.createElement('a');
                link.className = 'sseo-sa-ticket-link';
                link.href = msg.ticketUrl;
                link.target = '_blank';
                link.textContent = i18n.viewTicket || 'Bekijk ticket';
                div.appendChild(link);
            }

            messagesEl.appendChild(div);
        });
        messagesEl.scrollTop = messagesEl.scrollHeight;
        renderSuggestions();
    }

    function showTicketForm(assistantMsg) {
        if (ticketMode) return;
        ticketMode = true;

        var form = document.createElement('div');
        form.className = 'sseo-sa-ticket-form';
        form.innerHTML =
            '<input type="text" class="sseo-sa-ticket-subject" placeholder="' + escapeHtml(i18n.ticketSubject || 'Onderwerp') + '" value="' + escapeHtml(assistantMsg.content.substring(0, 80)) + '">' +
            '<textarea class="sseo-sa-ticket-message" placeholder="' + escapeHtml(i18n.ticketMessage || 'Bericht') + '" rows="4">' + escapeHtml(buildTicketMessage()) + '</textarea>' +
            '<button class="sseo-sa-ticket-submit">' + escapeHtml(i18n.ticketSubmit || 'Verstuur ticket') + '</button>';

        messagesEl.appendChild(form);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        form.querySelector('.sseo-sa-ticket-submit').addEventListener('click', function () {
            var subject = form.querySelector('.sseo-sa-ticket-subject').value.trim();
            var message = form.querySelector('.sseo-sa-ticket-message').value.trim();
            if (!subject || !message) return;

            form.querySelector('.sseo-sa-ticket-submit').disabled = true;
            form.querySelector('.sseo-sa-ticket-submit').textContent = i18n.loading || '...';

            submitTicket(subject, message, function (result) {
                ticketMode = false;
                form.remove();

                if (result && result.success) {
                    assistantMsg.ticketCreated = true;
                    assistantMsg.ticketUrl = result.support_url;
                    var msgText = (i18n.ticketSuccess || 'Ticket aangemaakt! Ticket #%d').replace('%d', result.ticket_id || '');
                    addMessage('assistant', msgText, { source: 'none' });
                } else {
                    addMessage('assistant', i18n.ticketError || 'Kon ticket niet aanmaken.', { source: 'none' });
                }
                renderMessages();
            });
        });
    }

    function buildTicketMessage() {
        var text = '';
        messages.forEach(function (msg) {
            if (msg.role === 'user') {
                text += 'Vraag: ' + msg.content + '\n';
            } else if (msg.role === 'assistant' && msg.content) {
                text += 'Antwoord: ' + msg.content.substring(0, 200) + '\n';
            }
        });
        return text.trim();
    }

    function submitTicket(subject, message, callback) {
        fetch(restUrl + '/support-assistant/ticket', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify({ subject: subject, message: message }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) { callback(data); })
            .catch(function () { callback(null); });
    }

    function togglePanel() {
        if (isOpen) {
            closePanel();
        } else {
            openPanel();
        }
    }

    function openPanel() {
        isOpen = true;
        panel.style.display = 'flex';
        button.classList.add('sseo-sa-fab-active');
        setTimeout(function () { inputEl.focus(); }, 100);
    }

    function closePanel() {
        isOpen = false;
        panel.style.display = 'none';
        button.classList.remove('sseo-sa-fab-active');
    }

    function sendMessage() {
        var text = inputEl.value.trim();
        if (!text) return;

        addMessage('user', text);
        inputEl.value = '';
        renderMessages();

        showLoading();

        var history = messages.slice(0, -1).map(function (m) {
            return { role: m.role === 'assistant' ? 'assistant' : 'user', content: m.content };
        });

        fetch(restUrl + '/support-assistant/ask', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify({ question: text, history: history }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                hideLoading();
                if (data && data.success) {
                    addMessage('assistant', data.answer, {
                        source: data.source,
                        ticketSuggested: data.ticket_suggested,
                    });
                } else {
                    addMessage('assistant', (data && data.message) || 'Er ging iets mis.', { source: 'none' });
                }
                renderMessages();
                saveHistory();
            })
            .catch(function () {
                hideLoading();
                addMessage('assistant', 'Er ging iets mis bij het ophalen van het antwoord.', { source: 'none' });
                renderMessages();
            });
    }

    function showLoading() {
        var div = document.createElement('div');
        div.className = 'sseo-sa-message sseo-sa-message-assistant sseo-sa-loading-msg';
        div.innerHTML = '<div class="sseo-sa-bubble"><span class="sseo-sa-typing"><span></span><span></span><span></span></span></div>';
        div.id = 'sseo-sa-loading';
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function hideLoading() {
        var el = document.getElementById('sseo-sa-loading');
        if (el) el.remove();
    }

    function addMessage(role, content, opts) {
        opts = opts || {};
        messages.push({
            role: role,
            content: content,
            source: opts.source || null,
            ticketSuggested: opts.ticketSuggested || false,
            ticketCreated: false,
            ticketUrl: null,
        });
        saveHistory();
    }

    function loadHistory() {
        try {
            var stored = sessionStorage.getItem(STORAGE_KEY);
            if (stored) {
                messages = JSON.parse(stored) || [];
            }
        } catch (e) {
            messages = [];
        }
        if (messages.length === 0) {
            addMessage('assistant', i18n.greeting || 'Hoi! Waar kan ik je mee helpen?', { source: 'none' });
        }
    }

    function saveHistory() {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(messages.slice(-20)));
        } catch (e) { /* ignore */ }
    }

    // --- Helpers ---
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatAnswer(text) {
        if (!text) return '';
        // Basic markdown: **bold**, line breaks, ### headers
        var html = escapeHtml(text);
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/^### (.+)$/gm, '<div class="sseo-sa-answer-h">$1</div>');
        html = html.replace(/\n/g, '<br>');
        return html;
    }

    function initials(name) {
        if (!name) return '?';
        var parts = name.trim().split(/\s+/);
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
