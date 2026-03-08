<?php

namespace AISEOAssistant;

class Settings
{
    public const OPTION_KEY = 'aiseoassistant_options';

    public function get(string $key, $default = null)
    {
        $options = get_option(self::OPTION_KEY, []);
        return $options[$key] ?? $default;
    }

    public function set(array $values): void
    {
        update_option(self::OPTION_KEY, $values, false);
    }

    public function all(): array
    {
        return get_option(self::OPTION_KEY, []);
    }

    public function llmProvider(): string
    {
        return $this->get('llm_provider', 'openai');
    }

    public function temperature(): float
    {
        return (float)($this->get('llm_temperature', 0.4));
    }

    public function maxTokens(): int
    {
        return (int)($this->get('llm_max_tokens', 600));
    }

    public function webhookEnabled(): bool
    {
        return !empty($this->get('webhook_enabled'));
    }

    public function webhookUrl(): string
    {
        return (string)$this->get('webhook_url', '');
    }

    public function imageProvider(): string
    {
        return $this->get('img_provider', 'openai');
    }

    public function gscProxy(): string
    {
        return $this->get('gsc_proxy', '');
    }

    public function imgProvider(): string
    {
        return $this->get('img_provider', 'openai');
    }

    public function perfThreshold(): float
    {
        return (float)$this->get('perf_threshold', 10);
    }

    public function psiStrategy(): string
    {
        return $this->get('psi_strategy', 'mobile');
    }

    public function lcpLimit(): int
    {
        return (int)$this->get('lcp_limit', 2500);
    }

    public function clsLimit(): float
    {
        return (float)$this->get('cls_limit', 0.1);
    }

    public function inpLimit(): int
    {
        return (int)$this->get('inp_limit', 200);
    }

    public function tenantKey(): string
    {
        return (string)$this->get('tenant_key', '');
    }

    public function saasApiBase(): string
    {
        return (string)$this->get('saas_api_base', '');
    }

    public function webhookSecret(): string
    {
        return (string)$this->get('webhook_secret', '');
    }

    public function contentLanguage(): string
    {
        return (string)$this->get('content_language', 'en');
    }

    public function openaiModel(): string
    {
        return (string)$this->get('openai_model', 'gpt-4.1');
    }

    public function anthropicModel(): string
    {
        return (string)$this->get('anthropic_model', 'claude-sonnet-4-20250514');
    }

    public function mistralModel(): string
    {
        return (string)$this->get('mistral_model', 'mistral-large-latest');
    }

    public function llmRateLimit(): int
    {
        return (int)$this->get('llm_rate_limit', 60);
    }
}
