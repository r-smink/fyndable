@extends('layouts.app')
@php($scripts = ['onboarding'])

@section('inline-size', 'small')

@section('actions')
    <s-button slot="primary-action" variant="primary" id="settings-save" onclick="saveSettings(this)">{{ __('app.settings.save') }}</s-button>
@endsection

@section('content')
    <s-section heading="{{ __('app.settings.store_title') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('app.settings.store_intro') }}</s-paragraph>
            <s-select id="set-language" label="{{ __('app.settings.language') }}" details="{{ __('app.settings.language_details') }}"></s-select>
            <s-text-field id="set-country" label="{{ __('app.settings.country') }}" details="{{ __('app.settings.country_details') }}" maxLength="2"></s-text-field>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('app.settings.voice_title') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('app.settings.voice_intro') }}</s-paragraph>
            <s-select id="set-tone" label="{{ __('app.settings.tone') }}"></s-select>
            <s-text-field id="set-audience" label="{{ __('app.settings.audience') }}" placeholder="{{ __('app.settings.audience_ph') }}" maxLength="255"></s-text-field>
            <s-text-area id="set-brand" label="{{ __('app.settings.brand') }}" placeholder="{{ __('app.settings.brand_ph') }}" rows="4" maxLength="1000"></s-text-area>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('app.settings.onboarding_title') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('app.settings.onboarding_intro') }}</s-paragraph>
            <s-stack direction="inline">
                <s-button onclick="FynOnboarding.open()">{{ __('app.settings.onboarding_open') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>
@endsection

@push('modals')
    @include('dashboard.partials.onboarding')
@endpush
