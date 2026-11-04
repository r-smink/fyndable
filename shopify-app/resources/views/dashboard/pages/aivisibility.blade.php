@extends('layouts.app')

@section('content')
    <s-section heading="{{ __('aivisibility.llm_title') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('aivisibility.intro') }}</s-paragraph>
            <s-text-field id="llm-prompt" label="{{ __('aivisibility.question') }}" placeholder="{{ __('aivisibility.question_ph') }}"></s-text-field>
            <s-select id="llm-provider" label="{{ __('aivisibility.provider') }}" value="chatgpt">
                <s-option value="chatgpt">ChatGPT</s-option>
                <s-option value="claude">Claude</s-option>
                <s-option value="gemini">Gemini</s-option>
                <s-option value="perplexity">Perplexity</s-option>
            </s-select>
            <s-stack direction="inline">
                <s-button variant="primary" onclick="runLlmCheck(this)">{{ __('aivisibility.ask') }}</s-button>
            </s-stack>
            <div id="llm-result"></div>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('aivisibility.kw_title') }}">
        <s-stack gap="base">
            <s-text-field id="kw-data-input" label="{{ __('aivisibility.keywords') }}" details="{{ __('aivisibility.keywords_details') }}" placeholder="{{ __('aivisibility.keywords_ph') }}"></s-text-field>
            <s-stack direction="inline">
                <s-button onclick="loadKeywordData(this)">{{ __('aivisibility.get_volumes') }}</s-button>
            </s-stack>
            <div id="kw-data-result"></div>
        </s-stack>
    </s-section>

    <s-section slot="aside" heading="{{ __('aivisibility.mentions_title') }}">
        <s-stack gap="base">
            <s-text-field id="llm-mentions-target" label="{{ __('aivisibility.target') }}" placeholder="{{ $shopDomain }}"></s-text-field>
            <s-stack direction="inline">
                <s-button onclick="loadLlmMentions(this)">{{ __('aivisibility.search_mentions') }}</s-button>
            </s-stack>
            <div id="llm-mentions-result"></div>
        </s-stack>
    </s-section>
@endsection
