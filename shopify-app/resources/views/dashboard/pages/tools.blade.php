@extends('layouts.app')

@section('content')
    <s-section heading="{{ __('tools.indexnow_title') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('tools.indexnow_intro') }}</s-paragraph>
            <s-text-area id="indexnow-urls" label="{{ __('tools.urls') }}" details="{{ __('tools.urls_details') }}" rows="5" placeholder="https://{{ $shopDomain }}/products/example"></s-text-area>
            <s-stack direction="inline" gap="base">
                <s-button variant="primary" onclick="submitIndexNow(this)">{{ __('tools.submit') }}</s-button>
                <s-button onclick="setupIndexNow(this)">{{ __('tools.setup') }}</s-button>
            </s-stack>
            <div id="indexnow-result"></div>
        </s-stack>
    </s-section>

    <s-section slot="aside" heading="{{ __('tools.status_title') }}">
        <div id="indexnow-status">
            <s-spinner></s-spinner>
        </div>
    </s-section>
@endsection
