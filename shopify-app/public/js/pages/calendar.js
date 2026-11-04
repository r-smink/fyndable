/*
 * Content calendar: schedule blog articles for later publishing (published
 * by the scheduler every 15 minutes) and cancel scheduled ones.
 */
(function () {
    'use strict';

    const { t, esc } = Fyn;
    const STATUS_TONE = { published: 'success', failed: 'critical', cancelled: 'neutral', scheduled: 'warning' };

    async function loadCalendarBlogs() {
        const data = await Fyn.api('/blogs');
        if (data.error || !data.blogs) {
            Fyn.html('cal-blog', `<s-option value="">${esc(t('blogs_error'))}</s-option>`);
            return;
        }
        Fyn.html('cal-blog', data.blogs.length
            ? data.blogs.map(b => `<s-option value="${esc(b.id.split('/').pop())}">${esc(b.title)}</s-option>`).join('')
            : `<s-option value="">${esc(t('no_blogs'))}</s-option>`);
        if (data.blogs.length) Fyn.setVal('cal-blog', data.blogs[0].id.split('/').pop());
    }

    async function loadCalendarArticles() {
        const status = Fyn.val('cal-status');
        const month = Fyn.val('cal-month').trim();
        let url = '/calendar/articles?';
        if (status) url += `status=${encodeURIComponent(status)}&`;
        if (/^\d{4}-\d{2}$/.test(month)) url += `month=${encodeURIComponent(month)}&`;
        Fyn.html('cal-articles', Fyn.loading());
        const data = await Fyn.api(url);
        if (data.error) {
            Fyn.html('cal-articles', Fyn.errorBanner(data));
            return;
        }
        const articles = data.articles || [];
        if (!articles.length) {
            Fyn.html('cal-articles', `<s-text color="subdued">${esc(t('no_articles'))}</s-text>`);
            return;
        }
        Fyn.html('cal-articles', Fyn.table(
            [t('col_title'), t('col_scheduled'), t('col_status'), t('col_action')],
            articles.map(a => [
                esc(a.title),
                esc(Fyn.fmtDate(a.scheduled_at, true)),
                Fyn.badge(STATUS_TONE[a.status] || 'neutral', t('status_' + a.status)),
                a.status === 'scheduled'
                    ? `<s-button variant="tertiary" tone="critical" onclick="cancelScheduled(this, ${Number(a.id)})">${esc(t('cancel_article'))}</s-button>`
                    : '',
            ])
        ));
    }

    async function loadCalendar() {
        await Promise.all([loadCalendarBlogs(), loadCalendarArticles()]);
    }

    window.loadCalendar = loadCalendar;
    window.loadCalendarArticles = loadCalendarArticles;

    window.scheduleArticle = async function (button) {
        const blogId = Fyn.val('cal-blog');
        const title = Fyn.val('cal-title').trim();
        const body = Fyn.val('cal-body').trim();
        const date = Fyn.val('cal-date').trim();
        const time = Fyn.val('cal-time').trim() || '09:00';
        if (!title || !body || !date) {
            Fyn.toast(t('required'), true);
            return;
        }
        if (!/^\d{2}:\d{2}$/.test(time)) {
            Fyn.toast(t('time_invalid'), true);
            return;
        }
        const data = await Fyn.busy(button, () => Fyn.post('/calendar/schedule', {
            blog_id: blogId,
            title,
            body_html: body,
            tags: Fyn.val('cal-tags'),
            scheduled_at: `${date}T${time}`,
            author: Fyn.val('cal-author').trim(),
        }));
        if (data.success) {
            Fyn.html('cal-result', '');
            Fyn.toast(t('scheduled_ok'));
            Fyn.setVal('cal-title', '');
            Fyn.setVal('cal-body', '');
            loadCalendarArticles();
        } else {
            Fyn.html('cal-result', Fyn.errorBanner(data));
        }
    };

    window.cancelScheduled = async function (button, id) {
        if (!(await Fyn.confirm(t('cancel_confirm')))) return;
        const data = await Fyn.busy(button, () => Fyn.api(`/calendar/${id}`, { method: 'DELETE' }));
        if (data.error) Fyn.toast(Fyn.errorText(data), true);
        loadCalendarArticles();
    };

    Fyn.page(loadCalendar);
})();
