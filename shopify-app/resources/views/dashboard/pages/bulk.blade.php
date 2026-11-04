@extends('layouts.app')

@section('actions')
    <s-button slot="primary-action" variant="primary" id="bulk-start" onclick="startBulkOptimize(this)">{{ __('bulk.start') }}</s-button>
@endsection

@section('content')
    <s-section heading="{{ __('bulk.new_run') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('bulk.intro') }}</s-paragraph>
            <s-grid gridTemplateColumns="1fr 1fr" gap="base">
                <s-select id="bulk-type" label="{{ __('bulk.type') }}" onchange="onBulkTypeChange()">
                    @foreach (\App\Services\BulkOptimizer::TYPES as $type)
                        <s-option value="{{ $type }}">{{ __('bulk.type_'.$type) }}</s-option>
                    @endforeach
                </s-select>
                <s-select id="bulk-action" label="{{ __('bulk.action') }}" onchange="onBulkActionChange()"></s-select>
            </s-grid>

            <s-stack gap="small-200" id="bulk-mode-box">
                <s-choice-list id="bulk-mode" label="{{ __('bulk.mode') }}" onchange="onBulkModeChange()">
                    <s-choice value="ai" selected>{{ __('bulk.mode_ai') }}</s-choice>
                    <s-choice value="template">{{ __('bulk.mode_template') }}</s-choice>
                </s-choice-list>
            </s-stack>

            <s-stack gap="base" id="bulk-template-box" hidden>
                <s-text-field id="bulk-template" label="{{ __('bulk.template') }}" details="{{ __('bulk.template_details') }}" maxLength="500"></s-text-field>
                <s-stack direction="inline">
                    <s-button onclick="previewBulkTemplate(this)">{{ __('bulk.preview') }}</s-button>
                </s-stack>
                <div id="bulk-preview"></div>
            </s-stack>

            <s-checkbox id="bulk-only-needed" label="{{ __('bulk.only_needed') }}" details="{{ __('bulk.only_needed_details') }}" checked></s-checkbox>
            <s-number-field id="bulk-limit" label="{{ __('bulk.limit') }}" value="100" min="1" max="500"></s-number-field>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('bulk.status') }}">
        <s-stack gap="base">
            <div id="bulk-result">
                <s-text color="subdued">{{ __('bulk.empty') }}</s-text>
            </div>
            <s-stack direction="inline" id="bulk-cancel-box" hidden>
                <s-button tone="critical" onclick="cancelBulkOptimize(this)">{{ __('bulk.cancel') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>
@endsection
