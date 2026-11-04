{{--
    Onboarding wizard (0.2) as a Polaris modal. Opens only on a click
    (Built for Shopify 4.3.6). Logic: public/js/onboarding.js.
--}}
<s-modal id="onb-modal" heading="{{ __('app.js.onb_title') }}">
    <s-stack gap="base">
        <s-text color="subdued" id="onb-steplabel"></s-text>
        <div id="onb-body"></div>
    </s-stack>
    <s-button slot="primary-action" variant="primary" id="onb-primary" onclick="FynOnboarding.primary()">{{ __('app.js.onb_next') }}</s-button>
    <s-button slot="secondary-actions" id="onb-back" onclick="FynOnboarding.back()">{{ __('app.js.onb_back') }}</s-button>
    <s-button slot="secondary-actions" id="onb-skip" onclick="FynOnboarding.skip()">{{ __('app.js.onb_skip') }}</s-button>
</s-modal>
