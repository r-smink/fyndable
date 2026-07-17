<?php

namespace SSEOAIClient;

/**
 * Alert Notifier
 * 
 * Sends alert notifications when errors occur in the health logger.
 * Can be extended to support email, Slack, or other notification channels.
 */
class AlertNotifier
{
    private string $adminEmail;

    public function __construct()
    {
        $this->adminEmail = get_option('admin_email', '');
    }

    /**
     * Format an alert message
     */
    public function formatMessage(string $type, string $provider, string $status, string $message): string
    {
        return sprintf(
            '[%s] %s - %s (%s): %s',
            strtoupper($status),
            $type,
            $provider,
            current_time('mysql'),
            $message
        );
    }

    /**
     * Send an alert notification
     */
    public function send(string $message): bool
    {
        if (empty($this->adminEmail)) {
            return false;
        }

        $whiteLabel = get_option('sseo_ai_white_label', []);
        $companyName = !empty($whiteLabel['company_name']) ? $whiteLabel['company_name'] : 'Fyndable';
        $subject = sprintf(__('%s Alert', 'ai-seo-client'), $companyName);

        return wp_mail(
            $this->adminEmail,
            $subject,
            $message
        );
    }

    /**
     * Return a clear, human-readable explanation for a provider error code.
     */
    public function explainProviderError(string $provider, string $code, string $rawMessage = ''): string
    {
        $provider = strtolower($provider);
        $code = strtolower($code);

        $explanations = [
            'openrouter' => [
                'invalid_api_key'     => __('OpenRouter rejected the API key. Add a valid key in SaaS Settings → AI Models.', 'ai-seo-client'),
                'insufficient_credits' => __('OpenRouter credits are exhausted. Top up your account at openrouter.ai.', 'ai-seo-client'),
                'rate_limited'        => __('OpenRouter rate limit reached. Wait a moment or upgrade your OpenRouter plan.', 'ai-seo-client'),
                'openrouter_error'    => __('OpenRouter returned an error. Check the provider status at status.openrouter.ai.', 'ai-seo-client'),
                'openrouter_request_failed' => __('Could not reach OpenRouter. Verify the network connection and API key.', 'ai-seo-client'),
            ],
            'openai' => [
                'invalid_api_key'     => __('OpenAI rejected the API key. Add a valid key in SaaS Settings → API Credentials.', 'ai-seo-client'),
                'insufficient_quota'  => __('OpenAI quota exceeded. Add credits at platform.openai.com/billing.', 'ai-seo-client'),
                'rate_limited'        => __('OpenAI rate limit reached. Slow down or upgrade your OpenAI plan.', 'ai-seo-client'),
                'openai_request_failed' => __('Could not reach OpenAI. Verify the network connection and API key.', 'ai-seo-client'),
            ],
            'anthropic' => [
                'authentication_error' => __('Anthropic API key is invalid. Check the key in SaaS Settings.', 'ai-seo-client'),
                'rate_limited'         => __('Anthropic rate limit reached. Wait briefly or upgrade your Anthropic plan.', 'ai-seo-client'),
            ],
            'deepseek' => [
                'invalid_api_key'      => __('Deepseek API key is invalid. Check the key in SaaS Settings.', 'ai-seo-client'),
                'rate_limited'         => __('Deepseek rate limit reached. Wait briefly or upgrade your Deepseek plan.', 'ai-seo-client'),
            ],
            'stability' => [
                'authorization_required' => __('Stability AI authentication failed. Check your Stability API key.', 'ai-seo-client'),
                'rate_limited'           => __('Stability AI rate limit reached. Wait briefly or upgrade your plan.', 'ai-seo-client'),
            ],
            'serpapi' => [
                'invalid_api_key' => __('SerpApi API key is invalid. Check the key in SaaS Settings.', 'ai-seo-client'),
                'rate_limited'    => __('SerpApi rate limit reached. Upgrade your SerpApi plan.', 'ai-seo-client'),
            ],
            'dataforseo' => [
                'authentication_error' => __('DataForSEO credentials are invalid. Check the API key in SaaS Settings.', 'ai-seo-client'),
            ],
        ];

        return $explanations[$provider][$code] ?? $rawMessage;
    }
}
