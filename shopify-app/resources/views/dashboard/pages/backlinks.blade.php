@extends('layouts.app')

@section('content')
    <s-section heading="{{ __('backlinks.profile_title') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('backlinks.intro') }}</s-paragraph>
            <s-text-field id="bl-target" label="{{ __('backlinks.target') }}" details="{{ __('backlinks.target_details') }}" placeholder="{{ $shopDomain }}"></s-text-field>
            <s-stack direction="inline" gap="base">
                <s-button variant="primary" onclick="loadBacklinks(false, this)">{{ __('backlinks.summary') }}</s-button>
                <s-button onclick="loadBacklinks(true, this)">{{ __('backlinks.live') }}</s-button>
            </s-stack>
            <div id="backlinks-result"></div>
        </s-stack>
    </s-section>
@endsection
