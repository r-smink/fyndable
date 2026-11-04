@extends('layouts.app')

@section('actions')
    <s-button slot="secondary-actions" onclick="Fyn.busy(this, loadCalendar)">{{ __('calendar.refresh') }}</s-button>
@endsection

@section('content')
    <s-section heading="{{ __('calendar.scheduled') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('calendar.intro') }}</s-paragraph>
            <s-grid gridTemplateColumns="1fr 1fr" gap="base">
                <s-select id="cal-status" label="{{ __('calendar.status') }}" value="" onchange="loadCalendarArticles()">
                    <s-option value="" selected>{{ __('calendar.status_all') }}</s-option>
                    <s-option value="scheduled">{{ __('calendar.status_scheduled') }}</s-option>
                    <s-option value="published">{{ __('calendar.status_published') }}</s-option>
                    <s-option value="failed">{{ __('calendar.status_failed') }}</s-option>
                    <s-option value="cancelled">{{ __('calendar.status_cancelled') }}</s-option>
                </s-select>
                <s-text-field id="cal-month" label="{{ __('calendar.month') }}" placeholder="YYYY-MM" details="{{ __('calendar.month_details') }}" maxLength="7" onchange="loadCalendarArticles()"></s-text-field>
            </s-grid>
            <div id="cal-articles">
                <s-spinner></s-spinner>
            </div>
        </s-stack>
    </s-section>

    <s-section slot="aside" heading="{{ __('calendar.new') }}">
        <s-stack gap="base">
            <s-select id="cal-blog" label="{{ __('calendar.blog') }}"></s-select>
            <s-text-field id="cal-title" label="{{ __('calendar.title') }}"></s-text-field>
            <s-text-area id="cal-body" label="{{ __('calendar.body') }}" rows="6"></s-text-area>
            <s-text-field id="cal-tags" label="{{ __('calendar.tags') }}" details="{{ __('calendar.tags_details') }}" placeholder="seo, tips"></s-text-field>
            <s-date-field id="cal-date" label="{{ __('calendar.date') }}"></s-date-field>
            <s-text-field id="cal-time" label="{{ __('calendar.time') }}" placeholder="HH:MM" value="09:00" maxLength="5"></s-text-field>
            <s-text-field id="cal-author" label="{{ __('calendar.author') }}" details="{{ __('calendar.author_details') }}"></s-text-field>
            <s-stack direction="inline">
                <s-button variant="primary" onclick="scheduleArticle(this)">{{ __('calendar.schedule') }}</s-button>
            </s-stack>
            <div id="cal-result"></div>
            <s-text color="subdued">{{ __('calendar.tip') }}</s-text>
        </s-stack>
    </s-section>
@endsection
