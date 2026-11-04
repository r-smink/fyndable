@extends('layouts.app')

@section('actions')
    <s-button slot="primary-action" variant="primary" onclick="saveCrawlers(this)">{{ __('aicrawlers.save') }}</s-button>
    <s-button slot="secondary-actions" onclick="checkCrawlers(this)">{{ __('aicrawlers.check') }}</s-button>
@endsection

@section('content')
    <s-section>
        <s-paragraph>{{ __('aicrawlers.intro') }}</s-paragraph>
    </s-section>

    <s-section heading="{{ __('aicrawlers.bots_title') }}">
        <s-stack gap="base">
            <s-stack direction="inline" gap="base">
                <s-button onclick="presetCrawlers('all')">{{ __('aicrawlers.preset_all') }}</s-button>
                <s-button onclick="presetCrawlers('training')">{{ __('aicrawlers.preset_training') }}</s-button>
            </s-stack>
            <div id="ac-bots"><s-spinner></s-spinner></div>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('aicrawlers.apply_title') }}">
        <s-stack gap="base">
            <div id="ac-theme"></div>
            <s-paragraph>{{ __('aicrawlers.apply_intro') }}</s-paragraph>
            <s-ordered-list>
                <s-list-item>{{ __('aicrawlers.step_1') }}</s-list-item>
                <s-list-item>{{ __('aicrawlers.step_2') }}</s-list-item>
                <s-list-item>{{ __('aicrawlers.step_3_new') }} {{ __('aicrawlers.step_3_existing') }}</s-list-item>
                <s-list-item>{{ __('aicrawlers.step_4') }}</s-list-item>
            </s-ordered-list>
            <s-stack direction="inline">
                <s-button href="shopify://admin/themes" target="_top">{{ __('aicrawlers.open_themes') }}</s-button>
            </s-stack>
            <s-text-area id="ac-template" label="{{ __('aicrawlers.full_template') }}" rows="12" readOnly></s-text-area>
            <s-stack direction="inline"><s-button onclick="copyCrawlerCode('ac-template')">{{ __('aicrawlers.copy') }}</s-button></s-stack>
            <s-text-area id="ac-block" label="{{ __('aicrawlers.fyndable_block') }}" rows="6" readOnly></s-text-area>
            <s-stack direction="inline"><s-button onclick="copyCrawlerCode('ac-block')">{{ __('aicrawlers.copy') }}</s-button></s-stack>
        </s-stack>
    </s-section>
@endsection
