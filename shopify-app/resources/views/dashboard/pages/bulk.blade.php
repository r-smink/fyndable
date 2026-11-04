@extends('layouts.app')

@section('content')
    <s-section heading="{{ __('bulk.new_run') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('bulk.intro') }}</s-paragraph>
            <s-select id="bulk-action" label="{{ __('bulk.action') }}" value="meta">
                <s-option value="meta" selected>{{ __('bulk.action_meta') }}</s-option>
                <s-option value="titles">{{ __('bulk.action_titles') }}</s-option>
                <s-option value="alt_text">{{ __('bulk.action_alt') }}</s-option>
                <s-option value="descriptions">{{ __('bulk.action_descriptions') }}</s-option>
            </s-select>
            <s-number-field id="bulk-limit" label="{{ __('bulk.limit') }}" value="100" min="1" max="500"></s-number-field>
            <s-stack direction="inline" gap="base">
                <s-button variant="primary" onclick="startBulkOptimize(this)">{{ __('bulk.start') }}</s-button>
                <s-button onclick="checkBulkProgress(this)">{{ __('bulk.check') }}</s-button>
                <s-button tone="critical" onclick="cancelBulkOptimize(this)">{{ __('bulk.cancel') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('bulk.status') }}">
        <div id="bulk-result">
            <s-text color="subdued">{{ __('bulk.empty') }}</s-text>
        </div>
    </s-section>
@endsection
