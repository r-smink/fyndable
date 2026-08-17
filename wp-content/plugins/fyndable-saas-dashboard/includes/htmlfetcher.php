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

        // SSRF protection: block loopback, private, and link-local targets.
        // Although Jina/Firecrawl fetch the URL on their side (not our server),
        // this prevents abuse of the service to probe internal addresses and
        // protects against future architecture changes where we might fetch directly.
        if (!$this->isUrlSafe($url)) {
            return new \WP_Error('blocked_url', __('URL points to a blocked (internal/loopback) address', 'sseo-ai-saas'));
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

    /**
     * SSRF protection: validate that a URL does not resolve to a loopback,
     * private, or link-local address. Also blocks common internal hostnames.
     */
    private function isUrlSafe(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            return false;
        }

        // Block common internal/loopback hostnames.
        $blockedHosts = ['localhost', 'metadata.google.internal'];
        foreach ($blockedHosts as $blocked) {
            if (strcasecmp($host, $blocked) === 0) {
                return false;
            }
        }

        // Resolve host to IPs and check each against blocked ranges.
        // Note: gethostbynamel() returns only IPv4; for IPv6 we rely on
        // host-name blocklist above. This is defense-in-depth, not exhaustive.
        $ips = gethostbynamel($host);
        if (is_array($ips)) {
            foreach ($ips as $ip) {
                if (!$this->isIpSafe($ip)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check whether an IP is outside loopback/private/link-local ranges.
     */
    private function isIpSafe(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv6: block ::1 (loopback) and fc00::/7 (unique local)
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                if ($ip === '::1') {
                    return false;
                }
                $packed = inet_pton($ip);
                if ($packed !== false && (ord($packed[0]) & 0xfe) === 0xfc) {
                    return false; // ULA fc00::/7
                }
                return true;
            }
            return false;
        }

        // Block loopback, private, link-local, reserved ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }
        return false;
    }
}
