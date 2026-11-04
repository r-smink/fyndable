@extends('layouts.app')

@section('actions')
    <s-button slot="primary-action" variant="primary" onclick="openRedirectModal()">{{ __('redirects.add') }}</s-button>
@endsection

@section('content')
    <s-section heading="{{ __('redirects.not_found_title') }}">
        <s-stack gap="base">
            <s-banner tone="info">
                {{ __('redirects.embed_banner') }}
                <s-button slot="secondary-actions" href="shopify://admin/themes/current/editor?context=apps&amp;activateAppId={{ $apiKey }}/fyndable-404-monitor" target="_top">{{ __('redirects.embed_open') }}</s-button>
            </s-banner>
            <s-text color="subdued" id="nf-count"></s-text>
            <div id="nf-list"><s-spinner></s-spinner></div>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('redirects.redirects_title') }}">
        <s-stack gap="base">
            <s-stack direction="inline" gap="base" alignItems="end">
                <s-search-field id="rd-search" label="{{ __('redirects.search') }}" labelAccessibilityVisibility="exclusive" placeholder="{{ __('redirects.search') }}" onkeydown="if (event.key === 'Enter') loadRedirects(true)"></s-search-field>
                <s-button onclick="loadRedirects(true)">{{ __('redirects.search_button') }}</s-button>
            </s-stack>
            <div id="rd-list"><s-spinner></s-spinner></div>
            <s-stack direction="inline" id="rd-more" hidden>
                <s-button onclick="Fyn.busy(this, () => loadRedirects(false))">{{ __('redirects.load_more') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('redirects.csv_title') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('redirects.csv_intro') }}</s-paragraph>
            <s-text-area id="csv-input" label="{{ __('redirects.csv') }}" rows="6" placeholder="Redirect from,Redirect to&#10;/products/old,/products/new"></s-text-area>
            <s-stack direction="inline" gap="base">
                <s-button onclick="importCsv(this)">{{ __('redirects.import') }}</s-button>
                <s-button onclick="exportCsv(this)">{{ __('redirects.export') }}</s-button>
            </s-stack>
            <div id="csv-status"></div>
        </s-stack>
    </s-section>

    <s-section slot="aside" heading="{{ __('redirects.auto_title') }}">
        <s-stack gap="base">
            <s-checkbox id="set-handle" label="{{ __('redirects.on_handle') }}"></s-checkbox>
            <s-checkbox id="set-delete" label="{{ __('redirects.on_delete') }}"></s-checkbox>
            <s-select id="set-target" label="{{ __('redirects.delete_target') }}">
                <s-option value="collection">{{ __('redirects.target_collection') }}</s-option>
                <s-option value="all">{{ __('redirects.target_all') }}</s-option>
                <s-option value="home">{{ __('redirects.target_home') }}</s-option>
                <s-option value="search">{{ __('redirects.target_search') }}</s-option>
            </s-select>
            <s-checkbox id="set-monitor" label="{{ __('redirects.monitor') }}"></s-checkbox>
            <s-stack direction="inline">
                <s-button onclick="saveRedirectSettings(this)">{{ __('redirects.save') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section slot="aside" heading="{{ __('redirects.events_title') }}">
        <div id="rd-events"><s-spinner></s-spinner></div>
    </s-section>
@endsection

@push('modals')
    <s-modal id="rd-modal" heading="{{ __('redirects.modal_title') }}">
        <s-stack gap="base">
            <div id="rd-modal-error"></div>
            <s-text-field id="rd-path" label="{{ __('redirects.path') }}" details="{{ __('redirects.path_details') }}"></s-text-field>
            <s-text-field id="rd-target" label="{{ __('redirects.target') }}" details="{{ __('redirects.target_details') }}"></s-text-field>
        </s-stack>
        <s-button slot="primary-action" variant="primary" id="rd-save" onclick="saveRedirect(this)">{{ __('redirects.modal_save') }}</s-button>
        <s-button slot="secondary-actions" commandFor="rd-modal" command="--hide">{{ __('app.js.cancel') }}</s-button>
    </s-modal>
@endpush
