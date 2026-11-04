@extends('layouts.app')

@section('actions')
    <s-button slot="primary-action" variant="primary" onclick="checkRankings(this)">{{ __('ranktracker.check_now') }}</s-button>
@endsection

@section('content')
    <s-section>
        <s-paragraph>{{ __('ranktracker.intro') }}</s-paragraph>
    </s-section>

    <s-section>
        <div id="rank-stats"><s-spinner></s-spinner></div>
    </s-section>

    <s-section heading="{{ __('ranktracker.add_title') }}">
        <s-stack gap="base">
            <s-text-field id="rt-keyword" label="{{ __('ranktracker.keyword') }}" placeholder="{{ __('ranktracker.keyword_ph') }}"></s-text-field>
            <s-text-field id="rt-url" label="{{ __('ranktracker.url') }}" details="{{ __('ranktracker.url_details') }}" placeholder="https://your-store.com/products/..."></s-text-field>
            <s-stack direction="inline">
                <s-button onclick="addKeyword(this)">{{ __('ranktracker.add') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('ranktracker.tracked') }}">
        <div id="rank-keywords"><s-spinner></s-spinner></div>
    </s-section>
@endsection
