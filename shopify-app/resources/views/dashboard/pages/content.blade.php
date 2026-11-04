@extends('layouts.app')

@section('content')
    <s-section>
        <s-stack gap="base">
            <s-paragraph>{{ __('content.intro') }}</s-paragraph>
            <s-select id="content-type" label="{{ __('content.type') }}" value="product" onchange="onContentTypeChange()">
                <s-option value="product" selected>{{ __('content.type_product') }}</s-option>
                <s-option value="collection">{{ __('content.type_collection') }}</s-option>
                <s-option value="page">{{ __('content.type_page') }}</s-option>
                <s-option value="article">{{ __('content.type_article') }}</s-option>
            </s-select>
        </s-stack>
    </s-section>

    <div id="content-result"></div>

    <s-section id="content-empty">
        <s-paragraph>{{ __('content.empty') }}</s-paragraph>
    </s-section>

    <s-section id="content-editor-head" hidden>
        <s-stack gap="base">
            <s-heading id="content-selected"></s-heading>
            <div id="content-seo"></div>
            <s-text color="subdued">{{ __('content.editor_sub') }}</s-text>
            <s-text-area id="product-context" label="{{ __('content.context') }}" details="{{ __('content.context_details') }}" rows="2" placeholder="{{ __('content.context_ph') }}"></s-text-area>
            <s-stack direction="inline" gap="base">
                <s-button onclick="generateMeta(this)">{{ __('content.gen_meta') }}</s-button>
                <s-button onclick="generateDescription(this, 'long')">{{ __('content.gen_description') }}</s-button>
                <s-button onclick="generateDescription(this, 'short')">{{ __('content.gen_short') }}</s-button>
                <s-button onclick="generateSchema(this)">{{ __('content.gen_schema') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section id="sec-meta" heading="{{ __('content.meta_title') }}" hidden>
        <s-stack gap="base">
            <s-text-field id="edit-meta-title" label="{{ __('content.meta_title_field') }}"></s-text-field>
            <s-text-area id="edit-meta-desc" label="{{ __('content.meta_desc_field') }}" rows="2"></s-text-area>
            <s-stack direction="inline">
                <s-button variant="primary" onclick="saveMeta(this)">{{ __('content.save_meta') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section id="sec-description" heading="{{ __('content.description_title') }}" hidden>
        <s-stack gap="base">
            <s-text-area id="edit-description" label="{{ __('content.description_field') }}" rows="8"></s-text-area>
            <s-stack direction="inline">
                <s-button variant="primary" onclick="saveDescription(this)">{{ __('content.save_description') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section id="alt-text-section" heading="{{ __('content.alt_title') }}" hidden>
        <s-stack gap="base">
            <s-select id="edit-image" label="{{ __('content.image') }}"></s-select>
            <s-text-field id="edit-alt-text" label="{{ __('content.alt_field') }}"></s-text-field>
            <s-stack direction="inline" gap="base">
                <s-button onclick="generateAltText(this)">{{ __('content.generate') }}</s-button>
                <s-button variant="primary" onclick="saveAltText(this)">{{ __('content.save') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section id="sec-schema" heading="{{ __('content.schema_title') }}" hidden>
        <s-stack gap="base">
            <s-text-area id="edit-schema" label="{{ __('content.schema_field') }}" details="{{ __('content.schema_details') }}" rows="10"></s-text-area>
            <s-stack direction="inline">
                <s-button variant="primary" onclick="saveSchema(this)">{{ __('content.save_schema') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section id="sec-faq" heading="{{ __('content.faq_title') }}" hidden>
        <s-stack gap="base">
            <s-number-field id="faq-count" label="{{ __('content.faq_count') }}" value="5" min="2" max="10"></s-number-field>
            <s-text-area id="edit-faq" label="{{ __('content.faq_field') }}" details="{{ __('content.faq_details') }}" rows="6" placeholder='[{"question": "...", "answer": "..."}]'></s-text-area>
            <s-stack direction="inline" gap="base">
                <s-button onclick="generateFaq(this)">{{ __('content.faq_generate') }}</s-button>
                <s-button variant="primary" onclick="saveFaq(this)">{{ __('content.faq_save') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section id="sec-links" heading="{{ __('content.links_title') }}" hidden>
        <s-stack gap="base">
            <s-stack direction="inline">
                <s-button onclick="findLinkSuggestions(this)">{{ __('content.links_find') }}</s-button>
            </s-stack>
            <div id="link-suggestions"></div>
        </s-stack>
    </s-section>

    <s-section id="ai-image-section" heading="{{ __('content.ai_image_title') }}" hidden>
        <s-stack gap="base">
            <s-text-field id="ai-image-prompt" label="{{ __('content.ai_image_prompt') }}" details="{{ __('content.ai_image_details') }}" placeholder="{{ __('content.ai_image_ph') }}"></s-text-field>
            <s-stack direction="inline">
                <s-button onclick="generateAiImage(this)">{{ __('content.ai_image_generate') }}</s-button>
            </s-stack>
            <div id="ai-image-result"></div>
        </s-stack>
    </s-section>

    <s-section id="push-all-section" heading="{{ __('content.push_title') }}" hidden>
        <s-stack gap="base">
            <s-checkbox id="product-overwrite" label="{{ __('content.push_overwrite') }}"></s-checkbox>
            <s-stack direction="inline">
                <s-button variant="primary" onclick="pushAll(this)">{{ __('content.push_all') }}</s-button>
            </s-stack>
            <s-paragraph>
                <s-text color="subdued">{{ __('content.schema_note') }}</s-text>
            </s-paragraph>
        </s-stack>
    </s-section>

    <s-section slot="aside" heading="{{ __('content.items') }}">
        <s-stack gap="base">
            <s-text-field id="content-search" label="{{ __('content.search') }}" labelAccessibilityVisibility="exclusive" placeholder="{{ __('content.search_ph') }}" onkeydown="if (event.key === 'Enter') { event.preventDefault(); searchContent(); }"></s-text-field>
            <s-stack direction="inline">
                <s-button onclick="Fyn.busy(this, searchContent)">{{ __('content.search') }}</s-button>
            </s-stack>
            <div id="content-list"></div>
        </s-stack>
    </s-section>
@endsection
