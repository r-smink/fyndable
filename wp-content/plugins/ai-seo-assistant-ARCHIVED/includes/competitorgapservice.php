<?php

namespace AISEOAssistant;

class CompetitorGapService
{
    private SerpService $serp;

    public function __construct(SerpService $serp)
    {
        $this->serp = $serp;
    }

    /**
     * Compare SERP for keyword and mark matches vs own domain and competitors.
     *
     * @param string $keyword
     * @param array<string> $competitors
     * @return array{rows:array<int,array>, coverage:float, self_hits:int, competitor_hits:int}
     */
    public function analyze(string $keyword, array $competitors, string $ownHost): array
    {
        $res = $this->serp->fetch($keyword, true); // prefer scrape for URLs
        if (is_wp_error($res)) {
            return ['error' => $res->get_error_message()];
        }
        $rows = [];
        $selfHits = 0;
        $competitorHits = 0;

        foreach ($res['results'] as $item) {
            $host = parse_url($item['link'] ?? '', PHP_URL_HOST) ?: '';
            $type = 'other';
            if ($host && $this->isSameDomain($host, $ownHost)) {
                $type = 'self';
                $selfHits++;
            } elseif ($host && $this->isCompetitor($host, $competitors)) {
                $type = 'competitor';
                $competitorHits++;
            }
            $rows[] = [
                'position' => $item['position'] ?? null,
                'title'    => $item['title'] ?? '',
                'link'     => $item['link'] ?? '',
                'host'     => $host,
                'type'     => $type,
            ];
        }

        $coverage = count($rows) ? round(($selfHits / count($rows)) * 100, 1) : 0;

        return [
            'rows' => $rows,
            'coverage' => $coverage,
            'self_hits' => $selfHits,
            'competitor_hits' => $competitorHits,
            'provider' => $res['provider'],
        ];
    }

    private function isSameDomain(string $host, string $ownHost): bool
    {
        return $ownHost && str_ends_with($host, $ownHost);
    }

    private function isCompetitor(string $host, array $competitors): bool
    {
        foreach ($competitors as $c) {
            $c = trim($c);
            if ($c && str_ends_with($host, $c)) {
                return true;
            }
        }
        return false;
    }
}
