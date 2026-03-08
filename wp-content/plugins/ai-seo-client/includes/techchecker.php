<?php

namespace AISEOClient;

/**
 * Technical SEO Checker
 * 
 * Performs a full technical SEO check on a URL: HTTP status, schema/JSON-LD,
 * hreflang tags, robots.txt, and redirect chain analysis.
 */
class TechChecker
{
    public function __construct()
    {
    }

    /**
     * Run a full technical SEO check on a URL.
     */
    public function fullCheck(string $url): array
    {
        $resp = wp_remote_get($url, ['timeout' => 15, 'redirection' => 0]);
        $code = wp_remote_retrieve_response_code($resp);
        $body = is_wp_error($resp) ? '' : wp_remote_retrieve_body($resp);
        $headers = is_wp_error($resp) ? [] : wp_remote_retrieve_headers($resp)->getAll();
        $issues = [];

        // HTTP status check
        if (is_wp_error($resp)) {
            $issues[] = 'HTTP connection error: ' . $resp->get_error_message();
        } elseif ($code >= 400) {
            $issues[] = 'HTTP ' . $code . ' error';
        } elseif ($code >= 300 && $code < 400) {
            $issues[] = 'Page returns redirect (' . $code . ')';
        }

        if ($body) {
            // Schema/JSON-LD validation
            $schemaIssues = $this->checkSchema($body);
            $issues = array_merge($issues, $schemaIssues);

            // Hreflang check
            $hreflangData = $this->checkHreflang($body, $url);
            if (!empty($hreflangData['issues'])) {
                $issues = array_merge($issues, $hreflangData['issues']);
            }

            // Meta robots check
            if (preg_match('/<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']+)/i', $body, $m)) {
                $robotsMeta = strtolower($m[1]);
                if (strpos($robotsMeta, 'noindex') !== false) {
                    $issues[] = 'Page has noindex meta tag';
                }
            }

            // Title tag check
            if (!preg_match('/<title[^>]*>.+<\/title>/is', $body)) {
                $issues[] = 'Missing <title> tag';
            }

            // Canonical check
            if (!preg_match('/<link[^>]+rel=["\']canonical["\']/i', $body)) {
                $issues[] = 'Missing canonical link tag';
            }

            // Meta description check
            if (!preg_match('/<meta[^>]+name=["\']description["\']/i', $body)) {
                $issues[] = 'Missing meta description';
            }
        }

        // Robots.txt check
        $robotsData = $this->checkRobotsTxt($url);
        if (isset($robotsData['error'])) {
            $issues[] = $robotsData['error'];
        }

        // Redirect chain check
        $redirectChain = $this->checkRedirectChain($url, 5);
        if (count($redirectChain) > 3) {
            $issues[] = 'Redirect chain >3 hops (' . count($redirectChain) . ' total)';
        }

        return [
            'url' => $url,
            'http_code' => $code ?: 0,
            'issues' => array_unique($issues),
            'issue_count' => count(array_unique($issues)),
            'hreflang_map' => $hreflangData['map'] ?? [],
            'sitemaps' => $robotsData['sitemaps'] ?? [],
            'redirects' => $redirectChain,
            'has_schema' => !empty($this->extractJsonLd($body)),
        ];
    }

    /**
     * Validate JSON-LD schema in HTML.
     */
    private function checkSchema(string $html): array
    {
        $issues = [];
        $schemas = $this->extractJsonLd($html);

        if (empty($schemas)) {
            $issues[] = 'No JSON-LD structured data found';
            return $issues;
        }

        foreach ($schemas as $schema) {
            if (empty($schema['@type'])) {
                $issues[] = 'JSON-LD missing @type property';
            }
            if (empty($schema['@context'])) {
                $issues[] = 'JSON-LD missing @context property';
            }
        }

        return $issues;
    }

    /**
     * Extract JSON-LD blocks from HTML.
     */
    private function extractJsonLd(string $html): array
    {
        $schemas = [];
        if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $json) {
                $parsed = json_decode(trim($json), true);
                if (is_array($parsed)) {
                    $schemas[] = $parsed;
                }
            }
        }
        return $schemas;
    }

    /**
     * Check hreflang tags in HTML.
     */
    private function checkHreflang(string $html, string $currentUrl): array
    {
        $map = [];
        $issues = [];

        if (preg_match_all('/<link[^>]+rel=["\']alternate["\'][^>]+hreflang=["\']([^"\']+)["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $lang = $match[1];
                $href = $match[2];
                $map[$lang] = $href;
            }
        }

        if (!empty($map) && !isset($map['x-default'])) {
            $issues[] = 'Hreflang tags present but missing x-default';
        }

        // Check self-referencing
        $hasSelfRef = false;
        foreach ($map as $lang => $href) {
            if (rtrim($href, '/') === rtrim($currentUrl, '/')) {
                $hasSelfRef = true;
                break;
            }
        }
        if (!empty($map) && !$hasSelfRef) {
            $issues[] = 'Hreflang tags missing self-referencing URL';
        }

        return ['map' => $map, 'issues' => $issues];
    }

    /**
     * Fetch and parse robots.txt.
     */
    private function checkRobotsTxt(string $url): array
    {
        $parsed = parse_url($url);
        $robotsUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . '/robots.txt';

        $resp = wp_remote_get($robotsUrl, ['timeout' => 8]);
        if (is_wp_error($resp)) {
            return ['error' => 'Could not fetch robots.txt'];
        }

        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            return ['error' => 'robots.txt returned HTTP ' . $code, 'sitemaps' => []];
        }

        $body = wp_remote_retrieve_body($resp);
        $sitemaps = [];
        if (preg_match_all('/^Sitemap:\s*(.+)$/mi', $body, $matches)) {
            $sitemaps = array_map('trim', $matches[1]);
        }

        return ['sitemaps' => $sitemaps];
    }

    /**
     * Follow redirect chain up to max hops.
     */
    private function checkRedirectChain(string $url, int $maxHops = 5): array
    {
        $chain = [];
        $current = $url;

        for ($i = 0; $i < $maxHops; $i++) {
            $resp = wp_remote_head($current, ['timeout' => 8, 'redirection' => 0]);
            if (is_wp_error($resp)) {
                break;
            }

            $code = wp_remote_retrieve_response_code($resp);
            if ($code < 300 || $code >= 400) {
                break;
            }

            $location = wp_remote_retrieve_header($resp, 'location');
            if (!$location) {
                break;
            }

            $chain[] = [
                'from' => $current,
                'to' => $location,
                'status' => $code,
            ];
            $current = $location;
        }

        return $chain;
    }
}
