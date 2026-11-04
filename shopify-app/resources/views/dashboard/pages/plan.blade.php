@extends('layouts.app')

@section('content')
    <s-section>
        <s-stack gap="base">
            <s-paragraph>{{ __('app.plan.intro') }}</s-paragraph>
            <div id="billing-card">
                <s-spinner></s-spinner>
            </div>
        </s-stack>
    </s-section>

    {{-- Agency / enterprise keys only; App Pricing licenses are managed in Shopify. --}}
    <s-section id="agency-license" heading="{{ __('app.plan.agency_title') }}" hidden>
        <s-stack gap="base">
            <s-paragraph>{{ __('app.plan.agency_intro') }}</s-paragraph>
            <div id="license-status"></div>
            <s-stack gap="base" id="license-form">
                <s-text-field id="license-key" label="{{ __('app.plan.license_key') }}" placeholder="FYND-XXXX-XXXX-XXXX"></s-text-field>
                <s-stack direction="inline">
                    <s-button onclick="activateLicense(this)">{{ __('app.plan.activate') }}</s-button>
                </s-stack>
            </s-stack>
            <s-stack direction="inline" id="license-active" hidden>
                <s-button tone="critical" onclick="deactivateLicense(this)">{{ __('app.plan.deactivate') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section slot="aside" heading="{{ __('app.plan.modules') }}">
        <s-unordered-list>
            @foreach (['content', 'bulk', 'blogwriter', 'calendar', 'ranktracker', 'kwexplorer', 'competitor', 'backlinks', 'markets', 'aivisibility', 'geoscan', 'aitracker', 'llmstxt', 'tools'] as $module)
                <s-list-item>{{ __('app.nav.'.$module) }}</s-list-item>
            @endforeach
        </s-unordered-list>
    </s-section>
@endsection
