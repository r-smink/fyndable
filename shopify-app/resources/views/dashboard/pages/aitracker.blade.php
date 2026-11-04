@extends('layouts.app')

@section('actions')
    <s-button slot="secondary-actions" onclick="Fyn.busy(this, loadAiTrackerOverview)">{{ __('aitracker.refresh') }}</s-button>
@endsection

@section('content')
    <s-section>
        <s-stack gap="base">
            <s-paragraph>{{ __('aitracker.intro') }}</s-paragraph>
            <div id="ait-stats">
                <s-spinner></s-spinner>
            </div>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('aitracker.trends_title') }}">
        <div id="ait-timeseries">
            <s-spinner></s-spinner>
        </div>
    </s-section>

    <s-section heading="{{ __('aitracker.check_title') }}">
        <s-stack gap="base">
            <s-text-field id="ait-prompt" label="{{ __('aitracker.check_prompt') }}" placeholder="{{ __('aitracker.check_prompt_ph') }}"></s-text-field>
            <s-stack direction="inline">
                <s-button variant="primary" onclick="runAiTrackerCheck(this)">{{ __('aitracker.check') }}</s-button>
            </s-stack>
            <div id="ait-check-result"></div>
        </s-stack>
    </s-section>

    <s-section slot="aside" heading="{{ __('aitracker.recs_title') }}">
        <div id="ait-recommendations">
            <s-spinner></s-spinner>
        </div>
    </s-section>

    <s-section slot="aside" heading="{{ __('aitracker.pages_title') }}">
        <div id="ait-top-pages">
            <s-spinner></s-spinner>
        </div>
    </s-section>
@endsection
