<?php

namespace AISEOAssistant;

class RobotsChecker
{
    public function fetch(string $site): array
    {
        $robotsUrl = rtrim($site, '/') . '/robots.txt';
        $resp = wp_remote_get($robotsUrl, ['timeout' => 10]);
        if (is_wp_error($resp)) {
            return ['error' => $resp->get_error_message()];
        }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code >= 400) {
            return ['error' => 'robots.txt HTTP ' . $code];
        }
        $body = wp_remote_retrieve_body($resp);
        $sitemaps = [];
        foreach (preg_split('/\r?\n/', $body) as $line) {
            if (stripos($line, 'sitemap:') === 0) {
                $sitemaps[] = trim(substr($line, 8));
            }
        }
        return [
            'robots' => $body,
            'sitemaps' => $sitemaps,
        ];
    }
}
