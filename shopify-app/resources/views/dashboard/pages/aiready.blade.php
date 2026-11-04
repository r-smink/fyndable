@extends('layouts.app')

@section('actions')
    <s-button slot="secondary-actions" onclick="rebuildFeed(this)">{{ __('aiready.rebuild') }}</s-button>
@endsection

@section('content')
    <s-section>
        <s-paragraph>{{ __('aiready.intro') }}</s-paragraph>
    </s-section>

    <s-section heading="{{ __('aiready.score_title') }}">
        <s-stack gap="base">
            <div id="ar-stats"><s-spinner></s-spinner></div>
            <div id="ar-issues"></div>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('aiready.products_title') }}">
        <s-stack gap="base">
            <s-select id="ar-filter" label="{{ __('aiready.filter_issue') }}" onchange="loadProducts(1)"></s-select>
            <div id="ar-products"></div>
            <s-stack direction="inline" gap="base" alignItems="center" id="ar-pager" hidden>
                <s-button id="ar-prev" onclick="loadProducts(arPage - 1)">{{ __('aiready.js.prev') }}</s-button>
                <s-text color="subdued" id="ar-page"></s-text>
                <s-button id="ar-next" onclick="loadProducts(arPage + 1)">{{ __('aiready.js.next') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section slot="aside" heading="{{ __('aiready.feed_title') }}">
        <s-stack gap="base">
            <s-switch id="ar-enabled" label="{{ __('aiready.feed_enable') }}" details="{{ __('aiready.feed_details') }}" onchange="toggleFeed(this)"></s-switch>
            <div id="ar-feed-status"></div>
            <s-stack gap="base" id="ar-urls" hidden>
                <s-text-field id="ar-url-google" label="{{ __('aiready.google_url') }}" readOnly onclick="copyFeedUrl(this)"></s-text-field>
                <s-text-field id="ar-url-openai" label="{{ __('aiready.openai_url') }}" readOnly onclick="copyFeedUrl(this)"></s-text-field>
                <s-stack direction="inline">
                    <s-button variant="tertiary" onclick="rotateFeedToken(this)">{{ __('aiready.rotate') }}</s-button>
                </s-stack>
            </s-stack>
        </s-stack>
    </s-section>
@endsection

@push('modals')
    <s-modal id="ar-modal" heading="{{ __('aiready.modal_title') }}" size="large">
        <s-stack gap="base">
            <s-paragraph>{{ __('aiready.modal_intro') }}</s-paragraph>
            <div id="ar-modal-status"></div>
            <s-grid gridTemplateColumns="1fr 1fr" gap="base">
                <s-text-field id="ar-material" label="{{ __('aiready.material') }}" maxLength="150"></s-text-field>
                <s-text-field id="ar-color" label="{{ __('aiready.color') }}" maxLength="150"></s-text-field>
            </s-grid>
            <s-text-area id="ar-features" label="{{ __('aiready.features') }}" details="{{ __('aiready.features_details') }}" rows="4"></s-text-area>
            <s-text-area id="ar-use-cases" label="{{ __('aiready.use_cases') }}" details="{{ __('aiready.use_cases_details') }}" rows="3"></s-text-area>
            <s-text-field id="ar-audience" label="{{ __('aiready.audience') }}" maxLength="255"></s-text-field>
            <s-text-field id="ar-care" label="{{ __('aiready.care') }}" maxLength="500"></s-text-field>
            <s-grid gridTemplateColumns="1fr 1fr" gap="base">
                <s-select id="ar-gender" label="{{ __('aiready.gender') }}">
                    @foreach (['', 'male', 'female', 'unisex'] as $value)
                        <s-option value="{{ $value }}">{{ $value === '' ? __('aiready.none') : $value }}</s-option>
                    @endforeach
                </s-select>
                <s-select id="ar-age" label="{{ __('aiready.age_group') }}">
                    @foreach (['', 'newborn', 'infant', 'toddler', 'kids', 'adult'] as $value)
                        <s-option value="{{ $value }}">{{ $value === '' ? __('aiready.none') : $value }}</s-option>
                    @endforeach
                </s-select>
            </s-grid>
        </s-stack>
        <s-button slot="primary-action" variant="primary" id="ar-save" onclick="saveAttributes(this)">{{ __('aiready.save') }}</s-button>
        <s-button slot="secondary-actions" commandFor="ar-modal" command="--hide">{{ __('app.js.cancel') }}</s-button>
    </s-modal>
@endpush
