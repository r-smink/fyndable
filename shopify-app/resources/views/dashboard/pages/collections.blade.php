@extends('layouts.app')

@section('content')
    <s-section>
        <s-paragraph>{{ __('collections.intro') }}</s-paragraph>
    </s-section>

    <s-section heading="{{ __('collections.list_title') }}">
        <div id="cl-list"><s-spinner></s-spinner></div>
    </s-section>

    <s-section slot="aside" heading="{{ __('collections.blocks_title') }}">
        <s-stack gap="base">
            <div id="cl-blocks"></div>
            <s-paragraph>{{ __('collections.blocks_text') }}</s-paragraph>
            <s-stack direction="inline" gap="base">
                <s-button href="shopify://admin/themes/current/editor?template=collection&amp;addAppBlockId={{ $apiKey }}/fyndable-collection-intro&amp;target=mainSection" target="_top">{{ __('collections.add_intro') }}</s-button>
                <s-button href="shopify://admin/themes/current/editor?template=collection&amp;addAppBlockId={{ $apiKey }}/fyndable-collection-faq&amp;target=mainSection" target="_top">{{ __('collections.add_faq') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>
@endsection

@push('modals')
    <s-modal id="cl-modal" heading="{{ __('collections.modal_title') }}" size="large">
        <s-stack gap="large">
            <div id="cl-status"></div>
            <s-stack gap="base">
                <s-heading>{{ __('collections.keywords_title') }}</s-heading>
                <s-text color="subdued">{{ __('collections.keywords_text') }}</s-text>
                <s-stack direction="inline"><s-button onclick="suggestKeywords(this)">{{ __('collections.suggest') }}</s-button></s-stack>
                <div id="cl-suggestions"></div>
                <s-text-area id="cl-keywords" label="{{ __('collections.keywords_field') }}" details="{{ __('collections.keywords_details') }}" rows="4"></s-text-area>
            </s-stack>
            <s-stack gap="base">
                <s-heading>{{ __('collections.content_title') }}</s-heading>
                <s-stack direction="inline"><s-button onclick="writeLanding(this)">{{ __('collections.write') }}</s-button></s-stack>
                <s-text-area id="cl-intro" label="{{ __('collections.intro_field') }}" details="{{ __('collections.intro_details') }}" rows="6"></s-text-area>
                <s-text-area id="cl-faq" label="{{ __('collections.faq_field') }}" details="{{ __('collections.faq_details') }}" rows="10"></s-text-area>
            </s-stack>
        </s-stack>
        <s-button slot="primary-action" variant="primary" onclick="saveLanding(this)">{{ __('collections.save') }}</s-button>
        <s-button slot="secondary-actions" commandFor="cl-modal" command="--hide">{{ __('app.js.cancel') }}</s-button>
    </s-modal>
@endpush
