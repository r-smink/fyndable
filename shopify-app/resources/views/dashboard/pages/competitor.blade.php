@extends('layouts.app')

@section('content')
    <s-section heading="{{ __('competitor.analyze_title') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('competitor.intro') }}</s-paragraph>
            <s-text-field id="comp-keyword" label="{{ __('competitor.keyword') }}" placeholder="{{ __('competitor.keyword_ph') }}"></s-text-field>
            <s-text-field id="comp-target" label="{{ __('competitor.your_url') }}" details="{{ __('competitor.your_url_details') }}" placeholder="https://your-store.com"></s-text-field>
            <s-grid gridTemplateColumns="1fr 1fr" gap="base">
                <s-select id="comp-country" label="{{ __('competitor.country') }}" value="us">
                    @foreach (['us' => 'US', 'gb' => 'UK', 'nl' => 'NL', 'de' => 'DE', 'fr' => 'FR'] as $code => $label)
                        <s-option value="{{ $code }}">{{ $label }}</s-option>
                    @endforeach
                </s-select>
                <s-select id="comp-lang" label="{{ __('competitor.language') }}" value="en">
                    @foreach (['en' => 'EN', 'nl' => 'NL', 'de' => 'DE', 'fr' => 'FR'] as $code => $label)
                        <s-option value="{{ $code }}">{{ $label }}</s-option>
                    @endforeach
                </s-select>
            </s-grid>
            <s-stack direction="inline">
                <s-button variant="primary" onclick="analyzeCompetitors(this)">{{ __('competitor.analyze') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section id="comp-results-card" heading="{{ __('competitor.results') }}" hidden>
        <div id="comp-results"></div>
    </s-section>

    <s-section slot="aside" heading="{{ __('competitor.compare_title') }}">
        <s-stack gap="base">
            <s-text-field id="comp-domain-a" label="{{ __('competitor.domain_a') }}" placeholder="your-store.com"></s-text-field>
            <s-text-field id="comp-domain-b" label="{{ __('competitor.domain_b') }}" placeholder="competitor.com"></s-text-field>
            <s-stack direction="inline">
                <s-button onclick="compareDomains(this)">{{ __('competitor.compare') }}</s-button>
            </s-stack>
            <div id="comp-compare-result"></div>
        </s-stack>
    </s-section>
@endsection
