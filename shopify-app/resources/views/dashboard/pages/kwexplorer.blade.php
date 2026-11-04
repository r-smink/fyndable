@extends('layouts.app')

@section('content')
    <s-section heading="{{ __('kwexplorer.explore_title') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('kwexplorer.intro') }}</s-paragraph>
            <s-text-field id="kwe-seed" label="{{ __('kwexplorer.seed') }}" placeholder="{{ __('kwexplorer.seed_ph') }}"></s-text-field>
            <s-grid gridTemplateColumns="1fr 1fr" gap="base">
                <s-select id="kwe-country" label="{{ __('kwexplorer.country') }}" value="us">
                    @foreach (['us', 'gb', 'nl', 'de', 'fr', 'es', 'it', 'au', 'ca'] as $code)
                        <s-option value="{{ $code }}">{{ __('kwexplorer.countries.'.$code) }}</s-option>
                    @endforeach
                </s-select>
                <s-select id="kwe-language" label="{{ __('kwexplorer.language') }}" value="en">
                    @foreach (['en', 'nl', 'de', 'fr', 'es', 'it'] as $code)
                        <s-option value="{{ $code }}">{{ __('kwexplorer.languages.'.$code) }}</s-option>
                    @endforeach
                </s-select>
            </s-grid>
            <s-stack direction="inline" gap="base">
                <s-button variant="primary" onclick="exploreKeywords(this)">{{ __('kwexplorer.explore') }}</s-button>
                <s-button onclick="clusterKeywords(this)">{{ __('kwexplorer.cluster') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section id="kwe-results-card" heading="{{ __('kwexplorer.results') }}" hidden>
        <div id="kwe-results"></div>
    </s-section>

    <s-section slot="aside" heading="{{ __('kwexplorer.volume_title') }}">
        <s-stack gap="base">
            <s-text-area id="kwe-volume-input" label="{{ __('kwexplorer.keywords') }}" details="{{ __('kwexplorer.comma_separated') }}" rows="3" placeholder="keyword1, keyword2, keyword3"></s-text-area>
            <s-stack direction="inline">
                <s-button onclick="lookupVolume(this)">{{ __('kwexplorer.get_volume') }}</s-button>
            </s-stack>
            <div id="kwe-volume-result"></div>
        </s-stack>
    </s-section>

    <s-section slot="aside" heading="{{ __('kwexplorer.trends_title') }}">
        <s-stack gap="base">
            <s-text-field id="kwe-trends-input" label="{{ __('kwexplorer.keywords') }}" details="{{ __('kwexplorer.comma_separated') }}" placeholder="keyword1, keyword2"></s-text-field>
            <s-stack direction="inline">
                <s-button onclick="lookupTrends(this)">{{ __('kwexplorer.get_trends') }}</s-button>
            </s-stack>
            <div id="kwe-trends-result"></div>
        </s-stack>
    </s-section>
@endsection
