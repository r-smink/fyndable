<?php

namespace AISEOAssistant;

class TechChecker
{
    private SchemaValidator $schema;
    private HreflangChecker $hreflang;
    private RobotsChecker $robots;
    private RedirectChecker $redirects;

    public function __construct()
    {
        $this->schema = new SchemaValidator();
        $this->hreflang = new HreflangChecker();
        $this->robots = new RobotsChecker();
        $this->redirects = new RedirectChecker();
    }

    public function fullCheck(string $url): array
    {
        $resp = wp_remote_get($url, ['timeout' => 12]);
        $code = wp_remote_retrieve_response_code($resp);
        $body = is_wp_error($resp) ? '' : wp_remote_retrieve_body($resp);
        $issues = [];
        if (is_wp_error($resp) || $code >= 400) {
            $issues[] = 'HTTP ' . ($code ?: 'error');
        } else {
            $issues = array_merge($issues, $this->schema->validateHtml($body));
        }
        $hre = $this->hreflang->check($url);
        if (!empty($hre['issues'])) {
            $issues = array_merge($issues, $hre['issues']);
        }
        $robots = $this->robots->fetch($url);
        if (isset($robots['error'])) {
            $issues[] = $robots['error'];
        }
        $redirectChain = $this->redirects->chain($url, 5);
        $longChain = count($redirectChain) > 3;
        if ($longChain) {
            $issues[] = 'Redirect chain >3 hops';
        }
        return [
            'issues' => array_unique($issues),
            'hreflang_map' => $hre['map'] ?? [],
            'sitemaps' => $robots['sitemaps'] ?? [],
            'redirects' => $redirectChain,
        ];
    }
}
