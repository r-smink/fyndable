<?php

namespace AISEOAssistant;

class AuditService
{
    /**
     * Basic content audit for last N posts.
     */
    public function auditPosts(int $limit = 200): array
    {
        $q = new \WP_Query([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $issues = [];
        foreach ($q->posts as $post) {
            $content = $post->post_content ?? '';
            $title = $post->post_title ?? '';
            $wordCount = str_word_count(wp_strip_all_tags($content));
            $hasH2 = stripos($content, '<h2') !== false;
            $internalLinks = $this->countInternalLinks($content);
            $excerpt = trim($post->post_excerpt ?? '');

            if ($wordCount < 300 || !$hasH2 || $internalLinks === 0 || $excerpt === '') {
                $issues[] = [
                    'ID' => $post->ID,
                    'title' => $title,
                    'word_count' => $wordCount,
                    'has_h2' => $hasH2,
                    'internal_links' => $internalLinks,
                    'excerpt' => $excerpt,
                    'edit_link' => get_edit_post_link($post->ID, ''),
                ];
            }
        }
        wp_reset_postdata();
        return $issues;
    }

    private function countInternalLinks(string $html): int
    {
        $count = 0;
        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', $html, $m)) {
            $home = parse_url(home_url(), PHP_URL_HOST);
            foreach ($m[1] as $href) {
                $host = parse_url($href, PHP_URL_HOST);
                if (!$host || str_ends_with($host, $home)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Cannibalization: posts whose titles contain the same tracked keyword.
     * @param array<string> $keywords
     */
    public function cannibalization(array $keywords): array
    {
        $results = [];
        foreach ($keywords as $kw) {
            $posts = get_posts([
                's' => $kw,
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 10,
            ]);
            if (count($posts) > 1) {
                $results[$kw] = array_map(function ($p) {
                    return [
                        'ID' => $p->ID,
                        'title' => $p->post_title,
                        'edit_link' => get_edit_post_link($p->ID, ''),
                    ];
                }, $posts);
            }
        }
        return $results;
    }

    /**
     * Light technical audit: hreflang, canonical header, HTTP status (head request).
     */
    public function technicalAudit(array $urls): array
    {
        $results = [];
        foreach ($urls as $url) {
            $head = wp_remote_head($url, ['redirection' => 5, 'timeout' => 12]);
            $code = wp_remote_retrieve_response_code($head);
            $headers = wp_remote_retrieve_headers($head);
            $canonical = $headers['link'] ?? '';
            $hreflang = $headers['content-language'] ?? '';

            $issues = [];
            if ($code >= 400) {
                $issues[] = 'HTTP ' . $code;
            }
            if ($canonical && stripos($canonical, 'rel="canonical"') === false) {
                $issues[] = 'Canonical header format?';
            }

            // Fetch body for schema/hreflang tags
            $bodyResp = wp_remote_get($url, ['timeout' => 12]);
            if (!is_wp_error($bodyResp) && wp_remote_retrieve_response_code($bodyResp) < 400) {
                $html = wp_remote_retrieve_body($bodyResp);
                // body-level canonical/hreflang/schema
                if (stripos($html, 'rel="canonical"') === false) {
                    $issues[] = 'Missing canonical tag';
                }
                if (stripos($html, 'hreflang') === false) {
                    $issues[] = 'No hreflang tags';
                }
                if (stripos($html, 'application/ld+json') === false) {
                    $issues[] = 'No schema JSON-LD';
                }
                // schema validation light
                $schemaIssues = (new SchemaValidator())->validateHtml($html);
                $issues = array_merge($issues, $schemaIssues);
            }

            $results[] = [
                'url' => $url,
                'code' => $code,
                'canonical' => $canonical,
                'hreflang' => $hreflang,
                'issues' => $issues,
            ];
        }
        return $results;
    }
}
