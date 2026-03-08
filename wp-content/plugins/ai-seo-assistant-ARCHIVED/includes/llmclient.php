<?php

namespace AISEOAssistant;

use WP_Error;

class LlmClient
{
    private Settings $settings;
    private HealthLogger $health;
    private ?TenantRepository $tenants;
    private const RATE_LIMIT_KEY = 'aiseo_llm_calls';
    private const COST_TRACKING_KEY = 'aiseo_llm_costs';

    public function __construct(Settings $settings, HealthLogger $health, ?TenantRepository $tenants = null)
    {
        $this->settings = $settings;
        $this->health = $health;
        $this->tenants = $tenants;
    }
    
    /**
     * Get current tenant key if available
     */
    private function getTenantKey(): ?string
    {
        return $this->tenants?->getCurrentTenant();
    }
    
    /**
     * Get rate limit key (tenant-specific or global)
     */
    private function getRateLimitKey(): string
    {
        $tenantKey = $this->getTenantKey();
        return $tenantKey ? self::RATE_LIMIT_KEY . '_' . $tenantKey : self::RATE_LIMIT_KEY;
    }
    
    /**
     * Get cost tracking key (tenant-specific or global)
     */
    private function getCostTrackingKey(): string
    {
        $tenantKey = $this->getTenantKey();
        return $tenantKey ? self::COST_TRACKING_KEY . '_' . $tenantKey : self::COST_TRACKING_KEY;
    }

    /**
     * Get the configured content language.
     */
    public function getLanguage(): string
    {
        return $this->settings->get('content_language', 'en');
    }

    /**
     * Build system prompt based on configured language.
     */
    private function buildSystemPrompt(?string $role = null): string
    {
        $lang = $this->getLanguage();
        $langNames = [
            'nl' => 'Dutch', 'en' => 'English', 'de' => 'German', 'fr' => 'French',
            'es' => 'Spanish', 'it' => 'Italian', 'pt' => 'Portuguese', 'pl' => 'Polish',
            'sv' => 'Swedish', 'da' => 'Danish', 'no' => 'Norwegian', 'fi' => 'Finnish',
            'ja' => 'Japanese', 'ko' => 'Korean', 'zh' => 'Chinese', 'ar' => 'Arabic',
            'tr' => 'Turkish', 'ru' => 'Russian', 'cs' => 'Czech', 'ro' => 'Romanian',
        ];
        $langName = $langNames[$lang] ?? 'English';
        $base = $role ?: 'You are an expert SEO content strategist and copywriter.';
        return "{$base} Always reply in {$langName}.";
    }

    /**
     * Check rate limit before making API call.
     * Supports per-tenant rate limiting.
     */
    private function checkRateLimit(): bool
    {
        // Check tenant-specific rate limit first
        $tenantKey = $this->getTenantKey();
        if ($tenantKey && $this->tenants) {
            $tenant = $this->tenants->getTenant($tenantKey);
            if ($tenant) {
                $tenantRateLimit = (int)$tenant['rate_limit'];
                $tenantCalls = get_transient($this->getRateLimitKey()) ?: 0;
                
                if ($tenantCalls >= $tenantRateLimit) {
                    return false;
                }
                
                // Also track usage in tenant repository
                $this->tenants->trackUsage($tenantKey, 'api_calls', 1);
            }
        }
        
        // Check global rate limit as fallback
        $maxCallsPerHour = (int)$this->settings->get('llm_rate_limit', 60);
        $calls = get_transient(self::RATE_LIMIT_KEY) ?: 0;
        return $calls < $maxCallsPerHour;
    }

    /**
     * Increment rate limit counter.
     * Supports per-tenant tracking.
     */
    private function incrementRateLimit(): void
    {
        // Increment tenant-specific counter
        $rateKey = $this->getRateLimitKey();
        $calls = get_transient($rateKey) ?: 0;
        set_transient($rateKey, $calls + 1, HOUR_IN_SECONDS);
        
        // Also increment global counter
        $globalCalls = get_transient(self::RATE_LIMIT_KEY) ?: 0;
        set_transient(self::RATE_LIMIT_KEY, $globalCalls + 1, HOUR_IN_SECONDS);
    }

    /**
     * Track estimated API cost.
     * Supports per-tenant cost tracking.
     */
    private function trackCost(string $provider, int $inputTokens, int $outputTokens): void
    {
        $rates = [
            'openai'    => ['input' => 0.002, 'output' => 0.008],  // per 1K tokens
            'anthropic' => ['input' => 0.003, 'output' => 0.015],
            'mistral'   => ['input' => 0.002, 'output' => 0.006],
        ];
        $rate = $rates[$provider] ?? $rates['openai'];
        $cost = ($inputTokens / 1000 * $rate['input']) + ($outputTokens / 1000 * $rate['output']);

        // Track tenant-specific costs
        $tenantKey = $this->getTenantKey();
        if ($tenantKey && $this->tenants) {
            $this->tenants->trackUsage($tenantKey, 'api_cost', 0, $cost);
        }

        // Also track in global costs
        $costKey = $this->getCostTrackingKey();
        $monthly = get_option($costKey, ['month' => '', 'total' => 0, 'calls' => 0]);
        $currentMonth = date('Y-m');
        if ($monthly['month'] !== $currentMonth) {
            $monthly = ['month' => $currentMonth, 'total' => 0, 'calls' => 0];
        }
        $monthly['total'] += $cost;
        $monthly['calls']++;
        update_option($costKey, $monthly, false);
    }

    /**
     * Get monthly cost stats.
     * Supports tenant-specific or global costs.
     */
    public function getMonthlyCosts(): array
    {
        $costKey = $this->getCostTrackingKey();
        return get_option($costKey, ['month' => date('Y-m'), 'total' => 0, 'calls' => 0]);
    }
    
    /**
     * Check tenant limits and return status
     */
    public function checkTenantLimits(): array
    {
        $tenantKey = $this->getTenantKey();
        if (!$tenantKey || !$this->tenants) {
            return ['valid' => true, 'tenant' => false];
        }
        
        return $this->tenants->checkTenantLimits($tenantKey);
    }

    /**
     * Call the configured LLM with fallback, rate limiting, and cost tracking.
     * @param string $prompt The user prompt
     * @param string|null $systemRole Optional custom system role override
     * @param int|null $maxTokens Optional max tokens override
     */
    public function call(string $prompt, ?string $systemRole = null, ?int $maxTokens = null)
    {
        if (!$this->checkRateLimit()) {
            return new WP_Error('rate_limited', __('API rate limit reached. Try again later.', 'ai-seo-assistant'));
        }

        $providers = $this->buildProviderOrder($this->settings->llmProvider());
        $lastError = null;
        $systemPrompt = $this->buildSystemPrompt($systemRole);
        $tokens = $maxTokens ?? $this->settings->maxTokens();

        foreach ($providers as $provider) {
            $res = match ($provider) {
                'openai'    => $this->openaiCall($prompt, $systemPrompt, $tokens),
                'anthropic' => $this->anthropicCall($prompt, $systemPrompt, $tokens),
                'mistral'   => $this->mistralCall($prompt, $systemPrompt, $tokens),
                default     => new WP_Error('llm_provider', __('Unknown LLM provider', 'ai-seo-assistant')),
            };
            if (is_wp_error($res)) {
                $lastError = $res;
                continue;
            }
            if ($res) {
                $this->incrementRateLimit();
                // Estimate tokens for cost tracking
                $inputTokens = (int)(strlen($prompt) / 4);
                $outputTokens = (int)(strlen($res) / 4);
                $this->trackCost($provider, $inputTokens, $outputTokens);
                return ['provider' => $provider, 'text' => $res];
            }
        }
        return $lastError ?: new WP_Error('llm_failed', __('No LLM response', 'ai-seo-assistant'));
    }

    public function healthcheck(string $prompt = 'Test prompt')
    {
        $res = $this->call($prompt);
        if (is_wp_error($res)) {
            return $res;
        }
        $wordCount = str_word_count($res['text']);
        return [
            'provider' => $res['provider'],
            'words'    => $wordCount,
        ];
    }

    /**
     * Cron-invoked healthcheck logger.
     */
    public function runHealthcheckJob(): void
    {
        $res = $this->healthcheck('Short SEO outline for test keyword; 3 bullets');
        $status = is_wp_error($res) ? 'error' : 'ok';
        $provider = is_wp_error($res) ? '' : ($res['provider'] ?? '');
        $message = is_wp_error($res) ? $res->get_error_message() : sprintf('OK (%d words)', $res['words'] ?? 0);
        $this->health->log('llm', $provider, $status, $message);
    }

    private function buildProviderOrder(string $primary): array
    {
        $priority = ['openai', 'anthropic', 'mistral'];
        $ordered = array_merge([$primary], array_diff($priority, [$primary]));
        return array_values(array_unique($ordered));
    }

    private function openaiCall(string $prompt, string $systemPrompt, int $maxTokens)
    {
        $apiKey = $this->settings->get('openai_key');
        if (!$apiKey) {
            return new WP_Error('openai_key', __('OpenAI API key is missing', 'ai-seo-assistant'));
        }
        $model = $this->settings->get('openai_model', 'gpt-4.1');
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $this->settings->temperature(),
                'max_tokens'  => $maxTokens,
            ]),
        ]);
        return $this->extractOpenAI($response);
    }

    private function anthropicCall(string $prompt, string $systemPrompt, int $maxTokens)
    {
        $apiKey = $this->settings->get('anthropic_key');
        if (!$apiKey) {
            return new WP_Error('anthropic_key', __('Anthropic API key is missing', 'ai-seo-assistant'));
        }
        $model = $this->settings->get('anthropic_model', 'claude-sonnet-4-20250514');
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 60,
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'system' => $systemPrompt,
                'max_tokens' => $maxTokens,
                'temperature' => $this->settings->temperature(),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]),
        ]);
        return $this->extractAnthropic($response);
    }

    private function mistralCall(string $prompt, string $systemPrompt, int $maxTokens)
    {
        $apiKey = $this->settings->get('mistral_key');
        if (!$apiKey) {
            return new WP_Error('mistral_key', __('Mistral API key is missing', 'ai-seo-assistant'));
        }
        $model = $this->settings->get('mistral_model', 'mistral-large-latest');
        $response = wp_remote_post('https://api.mistral.ai/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $this->settings->temperature(),
                'max_tokens'  => $maxTokens,
            ]),
        ]);
        return $this->extractOpenAI($response);
    }

    private function extractOpenAI($response)
    {
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('llm_http', __('LLM API error', 'ai-seo-assistant'), $response);
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $text = $body['choices'][0]['message']['content'] ?? '';
        return $text ?: new WP_Error('llm_empty', __('Empty response from LLM', 'ai-seo-assistant'));
    }

    private function extractAnthropic($response)
    {
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('llm_http', __('LLM API error', 'ai-seo-assistant'), $response);
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $text = $body['content'][0]['text'] ?? '';
        return $text ?: new WP_Error('llm_empty', __('Empty response from LLM', 'ai-seo-assistant'));
    }
}
