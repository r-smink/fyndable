@extends('layouts.app')

@section('content')
    <s-section heading="{{ __('blogwriter.new_article') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('blogwriter.intro') }}</s-paragraph>
            <s-select id="bw-blog" label="{{ __('blogwriter.blog') }}"></s-select>
            <s-text-field id="bw-topic" label="{{ __('blogwriter.topic') }}" placeholder="{{ __('blogwriter.topic_ph') }}"></s-text-field>
            <s-text-field id="bw-keywords" label="{{ __('blogwriter.keywords') }}" details="{{ __('blogwriter.keywords_details') }}" placeholder="{{ __('blogwriter.keywords_ph') }}"></s-text-field>
            <s-grid gridTemplateColumns="1fr 1fr" gap="base">
                <s-number-field id="bw-words" label="{{ __('blogwriter.words') }}" value="800" min="200" max="2500"></s-number-field>
                <s-select id="bw-lang" label="{{ __('blogwriter.language') }}" value="en">
                    @foreach (\App\Services\BrandVoice::LANGUAGES as $code => $name)
                        <s-option value="{{ $code }}" @if ($code === 'en') selected @endif>{{ $name }}</s-option>
                    @endforeach
                </s-select>
            </s-grid>
            <s-stack direction="inline">
                <s-button variant="primary" onclick="generateArticle(this)">{{ __('blogwriter.generate') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <div id="bw-result"></div>

    <s-section heading="{{ __('blogwriter.draft') }}">
        <s-stack gap="base">
            <s-stack direction="inline">
                <s-badge tone="warning">{{ __('blogwriter.not_published') }}</s-badge>
            </s-stack>
            <s-paragraph id="bw-empty">
                <s-text color="subdued">{{ __('blogwriter.draft_empty') }}</s-text>
            </s-paragraph>
            <s-stack gap="base" id="bw-draft" hidden>
                <s-text-field id="bw-title" label="{{ __('blogwriter.title') }}"></s-text-field>
                <s-text-area id="bw-body" label="{{ __('blogwriter.body') }}" details="{{ __('blogwriter.body_details') }}" rows="14"></s-text-area>
                <s-grid gridTemplateColumns="1fr 1fr" gap="base">
                    <s-text-field id="bw-author" label="{{ __('blogwriter.author') }}" details="{{ __('blogwriter.author_details') }}" placeholder="{{ __('blogwriter.author_ph') }}"></s-text-field>
                    <s-text-field id="bw-tags" label="{{ __('blogwriter.tags') }}" details="{{ __('blogwriter.tags_details') }}"></s-text-field>
                </s-grid>
                <s-stack direction="inline" gap="base">
                    <s-button variant="primary" onclick="createArticle(this, true)">{{ __('blogwriter.publish') }}</s-button>
                    <s-button onclick="createArticle(this, false)">{{ __('blogwriter.save_draft') }}</s-button>
                </s-stack>
            </s-stack>
        </s-stack>
    </s-section>
@endsection
