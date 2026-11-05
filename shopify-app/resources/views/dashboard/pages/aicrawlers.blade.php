@extends('layouts.app')

@section('actions')
    <s-button slot="primary-action" variant="primary" onclick="checkCrawlers(this)">{{ __('aicrawlers.check') }}</s-button>
@endsection

@section('content')
    <s-section>
        <s-stack gap="base">
            <s-paragraph>{{ __('aicrawlers.intro') }}</s-paragraph>
            <s-paragraph>{{ __('aicrawlers.default_note') }}</s-paragraph>
            <div id="ac-status"></div>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('aicrawlers.bots_title') }}">
        <div id="ac-bots"><s-spinner></s-spinner></div>
    </s-section>
@endsection
