<?php

namespace SSEOAISaaS;

/**
 * HTML Fetcher
 *
 * Fetches the readable text of a prospect URL.
 * Primary provider: Jina Reader (free, no API key).
 * Fallback provider: Firecrawl (if configured).
 */
class HtmlFetcher
{
    private SaaSSettings $settings;

    public function __construct(SaaSSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Fetch readable text for a URL.
     *
     * @return array|\WP_Error ['source' => 'jina'|'firecrawl', 'text' => string, 'url' => string]
     */
    public function fetch(string $url): array|\WP_Error
    {
        $url = esc_url_raw($url);

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', __('Invalid URL provided', 'sseo-ai-saas'));
        }

        $jinaResult = $this->fetchJina($url);
        if (!is_wp_error($jinaResult)) {
            return $jinaResult;
        }

        $firecrawlKey = $this->settings->getFirecrawlApiKey();
        if (!empty($firecrawlKey)) {
            $firecrawlResult = $this->fetchFirecrawl($url, $firecrawlKey);
            if (!is_wp_error($firecrawlResult)) {
                return $firecrawlResult;
            }
        }

        return $jinaResult;
    }

    private function fetchJina(string $url): array|\WP_Error
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $hostPath = substr($url, strlen((string)$scheme) + 3);
        $jinaUrl = 'https://r.jina.ai/' . $scheme . '://' . $hostPath;

        $response = wp_remote_get($jinaUrl, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'FyndableBot/1.0',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            return new \WP_Error('jina_failed', sprintf(__('Jina Reader returned status %d', 'sseo-ai-saas'), $statusCode));
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return new \WP_Error('jina_empty', __('Jina Reader returned an empty response', 'sseo-ai-saas'));
        }

        return [
            'source' => 'jina',
            'text'   => $this->cleanText($body),
            'url'    => $url,
        ];
    }

    private function fetchFirecrawl(string $url, string $apiKey): array|\WP_Error
    {
        $response = wp_remote_post('https://api.firecrawl.dev/v1/scrape', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'url'             => $url,
                'formats'         => ['markdown'],
                'onlyMainContent' => true,
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200 || empty($body['data']['markdown'])) {
            $message = $body['message'] ?? __('Firecrawl scrape failed', 'sseo-ai-saas');
            return new \WP_Error('firecrawl_failed', $message);
        }

        return [
            'source' => 'firecrawl',
            'text'   => $this->cleanText($body['data']['markdown']),
            'url'    => $url,
        ];
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);
        return mb_substr($text, 0, 20000);
    }
}
