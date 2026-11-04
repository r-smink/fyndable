@extends('layouts.app')

@section('actions')
    <s-button slot="primary-action" variant="primary" onclick="saveLlmsTxtSettings(this)">{{ __('llmstxt.save') }}</s-button>
    <s-button slot="secondary-actions" onclick="regenerateLlmsTxt(this)">{{ __('llmstxt.regenerate') }}</s-button>
    <s-button slot="secondary-actions" onclick="previewLlmsTxt(this)">{{ __('llmstxt.preview') }}</s-button>
@endsection

@section('content')
    <s-section heading="{{ __('llmstxt.settings_title') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('llmstxt.intro') }}</s-paragraph>
            <div id="llmstxt-status"></div>
            <s-switch id="llmstxt-enabled" label="{{ __('llmstxt.enabled') }}" details="{{ __('llmstxt.enabled_details') }}"></s-switch>
            <s-switch id="llmstxt-full-enabled" label="{{ __('llmstxt.full_enabled') }}" details="{{ __('llmstxt.full_enabled_details') }}"></s-switch>
            <s-stack gap="small-200">
                <s-text type="strong">{{ __('llmstxt.content_types') }}</s-text>
                <s-checkbox id="llmstxt-products" label="{{ __('llmstxt.products') }}" checked></s-checkbox>
                <s-checkbox id="llmstxt-collections" label="{{ __('llmstxt.collections') }}" checked></s-checkbox>
                <s-checkbox id="llmstxt-pages" label="{{ __('llmstxt.pages') }}" checked></s-checkbox>
                <s-checkbox id="llmstxt-blogs" label="{{ __('llmstxt.blogs') }}" checked></s-checkbox>
            </s-stack>
            <s-grid gridTemplateColumns="1fr 1fr" gap="base">
                <s-number-field id="llmstxt-max-products" label="{{ __('llmstxt.max_products') }}" value="100" min="1" max="1000"></s-number-field>
                <s-number-field id="llmstxt-max-chars" label="{{ __('llmstxt.max_chars') }}" value="50000" min="100" max="500000" step="500"></s-number-field>
            </s-grid>
            <s-text-field id="llmstxt-description" label="{{ __('llmstxt.description') }}" details="{{ __('llmstxt.description_details') }}"></s-text-field>
            <s-text-area id="llmstxt-custom" label="{{ __('llmstxt.custom') }}" details="{{ __('llmstxt.custom_details') }}" rows="4" placeholder="## Additional info&#10;- [About](/pages/about)"></s-text-area>
            <s-stack direction="inline">
                <s-button onclick="setupLlmsTxtRedirects(this)">{{ __('llmstxt.redirects') }}</s-button>
            </s-stack>
            <div id="llmstxt-redirect-status"></div>
        </s-stack>
    </s-section>

    <s-section slot="aside" heading="{{ __('llmstxt.preview_title') }}">
        <pre id="llmstxt-preview" class="fyn-preview">{{ __('llmstxt.preview_empty') }}</pre>
    </s-section>
@endsection
