@extends('layouts.app')

@section('actions')
    <s-button slot="primary-action" variant="primary" onclick="startGeoScan(this)">{{ __('geoscan.start') }}</s-button>
@endsection

@section('content')
    <s-section heading="{{ __('geoscan.start_title') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('geoscan.intro') }}</s-paragraph>
            <s-text-field id="geo-keywords" label="{{ __('geoscan.keywords') }}" details="{{ __('geoscan.keywords_details') }}" placeholder="{{ __('geoscan.keywords_ph') }}"></s-text-field>
            <div id="geo-result"></div>
        </s-stack>
    </s-section>

    <s-section id="geo-detail-card" heading="{{ __('geoscan.results_title') }}" hidden>
        <div id="geo-detail"></div>
    </s-section>

    <s-section slot="aside" heading="{{ __('geoscan.recent_title') }}">
        <div id="geo-recent">
            <s-spinner></s-spinner>
        </div>
    </s-section>
@endsection
