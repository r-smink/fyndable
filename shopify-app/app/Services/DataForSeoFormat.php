<?php

namespace App\Services;

/**
 * DataForSeoFormat
 *
 * The SaaS passes DataForSEO responses through untouched
 * ({success, data: {tasks: [{result: [...]}]}}). These pure helpers pull out
 * the fields the dashboard shows, so the UI never has to render the raw
 * envelope. Field names follow the DataForSEO v3 docs; legacy variants
 * (llm_mentions top_pages/top_domains) are read too.
 */
class DataForSeoFormat
{
    /** True for a proxy error ({error} or {success: false}). */
    public static function failed(array $response): bool
    {
        return isset($response['error']) || (($response['success'] ?? true) === false);
    }

    /** The first result object of the first task (or [] when empty). */
    public static function result(array $response): array
    {
        $data = $response['data'] ?? $response;
        $result = $data['tasks'][0]['result'][0] ?? null;

        return is_array($result) ? $result : [];
    }

    /**
     * Live LLM answer: the message text, cited sources and whether one of the
     * brand terms (store name, domain) appears in the text or sources.
     *
     * @param  array<int, string>  $brandTerms
     */
    public static function llmAnswer(array $response, array $brandTerms = []): array
    {
        $result = self::result($response);
        $texts = [];
        $sources = [];

        foreach ((array) ($result['items'] ?? []) as $item) {
            if (! is_array($item) || ($item['type'] ?? 'message') !== 'message') {
                continue;
            }
            foreach ((array) ($item['sections'] ?? []) as $section) {
                if (! is_array($section)) {
                    continue;
                }
                $text = trim((string) ($section['text'] ?? ''));
                if ($text !== '') {
                    $texts[] = $text;
                }
                foreach ((array) ($section['annotations'] ?? []) as $note) {
                    self::addSource($sources, $note['url'] ?? '', $note['title'] ?? '');
                }
            }
        }

        foreach ((array) ($result['search_results'] ?? []) as $hit) {
            self::addSource($sources, $hit['url'] ?? '', $hit['title'] ?? '');
        }

        $answer = implode("\n\n", $texts);

        return [
            'answer' => $answer,
            'sources' => array_slice(array_values($sources), 0, 10),
            'model' => (string) ($result['model_name'] ?? ''),
            'web_search' => (bool) ($result['web_search'] ?? false),
            'brand_mentioned' => self::mentions($answer, array_column($sources, 'url'), $brandTerms),
        ];
    }

    /** Search mentions: questions where AI answers mention the target. */
    public static function searchMentions(array $response, int $limit = 20): array
    {
        $result = self::result($response);
        $items = [];
        foreach (array_slice((array) ($result['items'] ?? []), 0, $limit) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sources = [];
            foreach ((array) ($item['sources'] ?? []) as $source) {
                self::addSource($sources, $source['url'] ?? '', $source['title'] ?? ($source['domain'] ?? ''));
            }
            $items[] = [
                'platform' => (string) ($item['platform'] ?? ''),
                'question' => (string) ($item['question'] ?? ''),
                'answer' => self::excerpt((string) ($item['answer'] ?? ''), 600),
                'ai_search_volume' => isset($item['ai_search_volume']) ? (int) $item['ai_search_volume'] : null,
                'sources' => array_slice(array_values($sources), 0, 5),
            ];
        }

        return [
            'total' => (int) ($result['total_count'] ?? count($items)),
            'items' => $items,
        ];
    }

    /** Target metrics: total mentions, AI search volume, per platform and top source domains. */
    public static function targetMetrics(array $response): array
    {
        $agg = self::result($response)['aggregated_metrics'] ?? [];

        return [
            'mentions' => isset($agg['total']['mentions']) ? (int) $agg['total']['mentions'] : null,
            'ai_search_volume' => isset($agg['total']['ai_search_volume']) ? (int) $agg['total']['ai_search_volume'] : null,
            'platforms' => self::groups($agg['platform'] ?? [], 10),
            'source_domains' => self::groups($agg['sources_domain'] ?? [], 10),
        ];
    }

    /**
     * Top mentioned pages/domains/brands as [{label, mentions, ai_search_volume}].
     * Current endpoints key the label as page/domain with a total object;
     * legacy ones use key and only per-platform counts.
     */
    public static function topItems(array $response, int $limit = 10): array
    {
        $rows = [];
        foreach ((array) (self::result($response)['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $label = (string) ($item['page'] ?? $item['domain'] ?? $item['brand'] ?? $item['title'] ?? $item['key'] ?? '');
            if ($label === '') {
                continue;
            }
            $mentions = $item['total']['mentions'] ?? null;
            $volume = $item['total']['ai_search_volume'] ?? null;
            if ($mentions === null) {
                $platforms = (array) ($item['platform'] ?? []);
                $mentions = array_sum(array_map(fn ($g) => (int) ($g['mentions'] ?? 0), $platforms));
                $volume = array_sum(array_map(fn ($g) => (int) ($g['ai_search_volume'] ?? 0), $platforms));
            }
            $rows[] = ['label' => $label, 'mentions' => (int) $mentions, 'ai_search_volume' => (int) $volume];
        }
        usort($rows, fn ($a, $b) => $b['mentions'] <=> $a['mentions']);

        return array_slice($rows, 0, $limit);
    }

    /** Timeseries delta points: [{date, mentions, ai_search_volume}] (oldest first). */
    public static function timeseriesDelta(array $response, int $limit = 12): array
    {
        $points = [];
        foreach ((array) (self::result($response)['items'] ?? []) as $item) {
            if (! is_array($item) || empty($item['date'])) {
                continue;
            }
            $points[] = [
                'date' => (string) $item['date'],
                'mentions' => (int) ($item['delta_mentions'] ?? 0),
                'ai_search_volume' => (int) ($item['delta_ai_search_volume'] ?? 0),
            ];
        }
        usort($points, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return array_slice($points, -$limit);
    }

    /** AI keyword search volume: [{keyword, ai_search_volume, monthly: [{month, volume}]}]. */
    public static function keywordVolumes(array $response): array
    {
        $rows = [];
        foreach ((array) (self::result($response)['items'] ?? []) as $item) {
            if (! is_array($item) || ! isset($item['keyword'])) {
                continue;
            }
            $monthly = [];
            foreach ((array) ($item['ai_monthly_searches'] ?? $item['monthly_searches'] ?? []) as $m) {
                if (! isset($m['year'], $m['month'])) {
                    continue;
                }
                $monthly[] = [
                    'month' => sprintf('%04d-%02d', $m['year'], $m['month']),
                    'volume' => (int) ($m['ai_search_volume'] ?? $m['search_volume'] ?? 0),
                ];
            }
            usort($monthly, fn ($a, $b) => strcmp($a['month'], $b['month']));
            $rows[] = [
                'keyword' => (string) $item['keyword'],
                'ai_search_volume' => isset($item['ai_search_volume']) ? (int) $item['ai_search_volume'] : null,
                'monthly' => array_slice($monthly, -12),
            ];
        }

        return $rows;
    }

    /**
     * Google Trends explore: keywords, interest over time (0-100) and the
     * top/rising related queries.
     */
    public static function trends(array $response, int $limit = 12): array
    {
        $result = self::result($response);
        $out = ['keywords' => array_values((array) ($result['keywords'] ?? [])), 'averages' => [], 'points' => [], 'top_queries' => [], 'rising_queries' => []];

        foreach ((array) ($result['items'] ?? []) as $item) {
            $type = $item['type'] ?? '';
            if ($type === 'google_trends_graph') {
                if (! empty($item['keywords'])) {
                    $out['keywords'] = array_values((array) $item['keywords']);
                }
                $out['averages'] = array_map(fn ($v) => $v === null ? null : (int) $v, array_values((array) ($item['averages'] ?? [])));
                foreach ((array) ($item['data'] ?? []) as $point) {
                    if (! empty($point['missing_data'])) {
                        continue;
                    }
                    $values = $point['values'] ?? [];
                    $out['points'][] = [
                        'date' => (string) ($point['date_from'] ?? ''),
                        'values' => array_map(fn ($v) => $v === null ? null : (int) $v, array_values(is_array($values) ? $values : [$values])),
                    ];
                }
            } elseif ($type === 'google_trends_queries_list') {
                foreach (['top' => 'top_queries', 'rising' => 'rising_queries'] as $src => $dest) {
                    foreach (array_slice((array) ($item['data'][$src] ?? []), 0, 10) as $q) {
                        if (! empty($q['query'])) {
                            $out[$dest][] = ['query' => (string) $q['query'], 'value' => (string) ($q['value'] ?? '')];
                        }
                    }
                }
            }
        }
        $out['points'] = array_slice($out['points'], -$limit);

        return $out;
    }

    /** Backlink summary: the headline numbers of a domain's profile. */
    public static function backlinkSummary(array $response): array
    {
        $r = self::result($response);
        $int = fn (string $k) => isset($r[$k]) ? (int) $r[$k] : null;

        return [
            'target' => (string) ($r['target'] ?? ''),
            'rank' => $int('rank'),
            'backlinks' => $int('backlinks'),
            'referring_domains' => $int('referring_domains'),
            'referring_domains_nofollow' => $int('referring_domains_nofollow'),
            'referring_main_domains' => $int('referring_main_domains'),
            'referring_ips' => $int('referring_ips'),
            'broken_backlinks' => $int('broken_backlinks'),
            'spam_score' => $int('backlinks_spam_score'),
            'first_seen' => isset($r['first_seen']) ? substr((string) $r['first_seen'], 0, 10) : null,
        ];
    }

    /** Live backlinks: total plus one row per link. */
    public static function backlinks(array $response): array
    {
        $result = self::result($response);
        $items = [];
        foreach ((array) ($result['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $items[] = [
                'url_from' => (string) ($item['url_from'] ?? ''),
                'domain_from' => (string) ($item['domain_from'] ?? ''),
                'url_to' => (string) ($item['url_to'] ?? ''),
                'anchor' => trim((string) ($item['anchor'] ?? '')),
                'dofollow' => (bool) ($item['dofollow'] ?? false),
                'domain_rank' => isset($item['domain_from_rank']) ? (int) $item['domain_from_rank'] : null,
                'spam_score' => isset($item['backlink_spam_score']) ? (int) $item['backlink_spam_score'] : null,
                'is_broken' => (bool) ($item['is_broken'] ?? false),
                'first_seen' => isset($item['first_seen']) ? substr((string) $item['first_seen'], 0, 10) : null,
            ];
        }

        return [
            'total' => (int) ($result['total_count'] ?? count($items)),
            'items' => $items,
        ];
    }

    /** Brand terms for a shop: store name, primary-domain label and myshopify handle. */
    public static function brandTerms(?string $shopName, string $shopDomain, string $target = ''): array
    {
        $terms = [];
        foreach ([$shopName, $target, $shopDomain] as $value) {
            $value = strtolower(trim((string) $value));
            if ($value === '') {
                continue;
            }
            $host = preg_replace('#^https?://#', '', $value);
            $host = preg_replace('#^www\.#', '', explode('/', $host)[0]);
            $terms[] = $host;
            // "acme.myshopify.com" / "acme.com" -> "acme"
            $label = explode('.', $host)[0];
            if (strlen($label) >= 3) {
                $terms[] = $label;
            }
        }

        return array_values(array_unique(array_filter($terms, fn ($t) => strlen($t) >= 3)));
    }

    /** Group elements ({key, mentions, ai_search_volume}) sorted by mentions. */
    private static function groups(mixed $groups, int $limit): array
    {
        $rows = [];
        foreach ((array) $groups as $g) {
            if (is_array($g) && isset($g['key'])) {
                $rows[] = ['label' => (string) $g['key'], 'mentions' => (int) ($g['mentions'] ?? 0), 'ai_search_volume' => (int) ($g['ai_search_volume'] ?? 0)];
            }
        }
        usort($rows, fn ($a, $b) => $b['mentions'] <=> $a['mentions']);

        return array_slice($rows, 0, $limit);
    }

    /** Add a source once per URL (http(s) only, so the UI can link it safely). */
    private static function addSource(array &$sources, mixed $url, mixed $title): void
    {
        $url = trim((string) $url);
        if (! preg_match('#^https?://#i', $url) || isset($sources[$url])) {
            return;
        }
        $title = trim((string) $title);
        $sources[$url] = ['url' => $url, 'title' => $title !== '' ? $title : (string) parse_url($url, PHP_URL_HOST)];
    }

    /** @param  array<int, string>  $urls */
    private static function mentions(string $text, array $urls, array $terms): ?bool
    {
        if (! $terms) {
            return null;
        }
        $haystack = strtolower($text.' '.implode(' ', $urls));
        foreach ($terms as $term) {
            if (str_contains($haystack, $term)) {
                return true;
            }
        }

        return false;
    }

    private static function excerpt(string $text, int $max): string
    {
        $text = trim($text);

        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max)).'...' : $text;
    }
}
