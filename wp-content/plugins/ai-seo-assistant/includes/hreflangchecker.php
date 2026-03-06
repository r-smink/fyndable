<?php

namespace AISEOAssistant;

class HreflangChecker
{
    public function check(string $url): array
    {
        $resp = wp_remote_get($url, ['timeout' => 12]);
        if (is_wp_error($resp)) return ['error' => $resp->get_error_message()];
        if (wp_remote_retrieve_response_code($resp) >= 400) return ['error' => 'HTTP error'];
        $html = wp_remote_retrieve_body($resp);
        preg_match_all('/<link[^>]+rel=["\\\']alternate["\\\'][^>]*hreflang=["\\\']([^"\\\']+)["\\\'][^>]*href=["\\\']([^"\\\']+)["\\\'][^>]*>/i', $html, $m, PREG_SET_ORDER);
        $map = [];
        foreach ($m as $match) {
            $map[$match[1]] = $match[2];
        }
        $issues = [];
        if (empty($map)) {
            $issues[] = 'No hreflang tags';
        }
        // simple reciprocity check: fetch first lang and see if it links back
        if (!empty($map)) {
            $firstUrl = reset($map);
            $resp2 = wp_remote_get($firstUrl, ['timeout' => 12]);
            if (!is_wp_error($resp2) && wp_remote_retrieve_response_code($resp2) < 400) {
                $html2 = wp_remote_retrieve_body($resp2);
                if (!preg_match('/href=["\\\']'.preg_quote($url,'/').'["\\\']/', $html2)) {
                    $issues[] = 'Hreflang not reciprocated from ' . $firstUrl;
                }
            }
        }
        return ['map' => $map, 'issues' => $issues];
    }
}
