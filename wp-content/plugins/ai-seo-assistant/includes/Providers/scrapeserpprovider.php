<?php

namespace AISEOAssistant\Providers;

use AISEOAssistant\Settings;
use WP_Error;

class ScrapeSerpProvider implements SerpProviderInterface
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function search(string $keyword, array $args = [])
    {
        $country = $args['country'] ?? 'us';
        $url = add_query_arg([
            'q'  => $keyword,
            'gl' => $country,
            'hl' => 'en',
        ], 'https://www.google.com/search');

        $response = wp_remote_get($url, [
            'timeout'  => 15,
            'sslverify' => true,
            'headers'  => [
                'User-Agent' => 'Mozilla/5.0 (compatible; AISEOAssistant/0.1; +https://example.com/bot)',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('scrape_error', __('Failed to scrape SERP', 'ai-seo-assistant'), $response);
        }

        $html = wp_remote_retrieve_body($response);
        $parsed = $this->parseOrganicResults($html);
        $parsed['ai_overview'] = $this->detectAiOverview($html);
        $parsed['ai_citations'] = $this->extractCitations($html);
        return $parsed;
    }

    /**
     * Very lightweight parser; replace with DOM/CSS selectors if you install a parser lib.
     */
    private function parseOrganicResults(string $html): array
    {
        $results = [];
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//div[@class='g' or @data-sokoban-container='true']//a[h3]");
        $position = 1;
        foreach ($nodes as $node) {
            /** @var \DOMElement $node */
            $link = $node->getAttribute('href');
            $titleNode = $xpath->query('.//h3', $node)->item(0);
            $title = $titleNode ? $titleNode->textContent : '';
            $snippetNode = $xpath->query(".//div[contains(@class,'VwiC3b')]", $node)->item(0);
            $snippet = $snippetNode ? trim($snippetNode->textContent) : '';
            if (!$title || !$link) {
                continue;
            }
            $results[] = [
                'title'    => trim($title),
                'link'     => $link,
                'snippet'  => $snippet,
                'position' => $position++,
            ];
            if (count($results) >= 10) {
                break;
            }
        }

        return ['results' => $results];
    }

    private function detectAiOverview(string $html): bool
    {
        // heuristic markers observed in Google "AI Overview"
        $markers = [
            'AI Overview',
            'Generative AI is experimental',
            'About this result',
            'gai-long-text',
            'AIOv'
        ];
        $hay = strtolower($html);
        foreach ($markers as $m) {
            if (str_contains($hay, strtolower($m))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Try to capture cited links inside AI Overview block.
     */
    private function extractCitations(string $html): array
    {
        $citations = [];
        // crude: look for data-relcanonical or cite tags inside AI blocks
        if (preg_match_all('/<cite[^>]*>([^<]+)<\\/cite>/', $html, $m)) {
            foreach ($m[1] as $c) {
                $citations[] = trim(strip_tags($c));
            }
        }
        // fallback links within AI block containers (rough)
        if (preg_match_all('/<div[^>]*AIOv[^>]*>.*?<a[^>]+href=\"([^\"]+)\"/is', $html, $m2)) {
            foreach ($m2[1] as $u) {
                $citations[] = $u;
            }
        }
        return array_slice(array_values(array_unique($citations)), 0, 10);
    }
}
