@extends('layouts.app')

@section('content')
    <s-section>
        <s-stack gap="base">
            <s-paragraph>{{ __('translations.intro') }}</s-paragraph>
            <div id="tr-notice"></div>
            <s-grid gridTemplateColumns="1fr 1fr" gap="base" id="tr-filters">
                <s-select id="tr-locale" label="{{ __('translations.language') }}" onchange="loadGaps(1)"></s-select>
                <s-select id="tr-type" label="{{ __('translations.type') }}" onchange="loadGaps(1)">
                    @foreach (array_keys(\App\Services\AiTranslator::TYPES) as $type)
                        <s-option value="{{ $type }}">{{ __('translations.type_'.$type) }}</s-option>
                    @endforeach
                </s-select>
            </s-grid>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('translations.gaps_title') }}" id="tr-gaps-section">
        <s-stack gap="base">
            <s-text color="subdued" id="tr-summary"></s-text>
            <div id="tr-gaps"><s-spinner></s-spinner></div>
            <s-stack direction="inline" gap="base" alignItems="center" id="tr-pager" hidden>
                <s-button id="tr-prev" onclick="loadGaps(trPage - 1)">{{ __('translations.js.prev') }}</s-button>
                <s-text color="subdued" id="tr-page"></s-text>
                <s-button id="tr-next" onclick="loadGaps(trPage + 1)">{{ __('translations.js.next') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section slot="aside" heading="{{ __('translations.run_title') }}" id="tr-run-section">
        <s-stack gap="base">
            <s-number-field id="tr-limit" label="{{ __('translations.limit') }}" value="50" min="1" max="250"></s-number-field>
            <s-stack direction="inline" gap="base">
                <s-button variant="primary" id="tr-run" onclick="runTranslations(this)">{{ __('translations.run') }}</s-button>
                <s-button tone="critical" id="tr-cancel" onclick="cancelTranslations(this)" hidden>{{ __('translations.cancel') }}</s-button>
            </s-stack>
            <div id="tr-progress"></div>
        </s-stack>
    </s-section>
@endsection

@push('modals')
    <s-modal id="tr-modal" heading="{{ __('translations.modal_title') }}" size="large">
        <s-stack gap="base">
            <div id="tr-modal-status"></div>
            <div id="tr-fields"></div>
        </s-stack>
        <s-button slot="primary-action" variant="primary" id="tr-save" onclick="saveTranslation(this)">{{ __('translations.save') }}</s-button>
        <s-button slot="secondary-actions" commandFor="tr-modal" command="--hide">{{ __('app.js.cancel') }}</s-button>
    </s-modal>
@endpush
