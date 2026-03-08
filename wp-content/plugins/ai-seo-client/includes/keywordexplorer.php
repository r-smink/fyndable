<?php

namespace AISEOClient;

class KeywordExplorer
{
    private SerpService $serp;
    private TopicRepository $topics;

    public function __construct(SerpService $serp, TopicRepository $topics)
    {
        $this->serp = $serp;
        $this->topics = $topics;
    }

    /**
     * Expand a seed keyword by scraping top titles and extracting n-grams.
     *
     * @return array{related: array<string,int>, serp: array}
     */
    public function expand(string $seed): array
    {
        $res = $this->serp->fetch($seed, true);
        if (is_wp_error($res)) {
            return ['error' => $res->get_error_message()];
        }
        $ngrams = [];
        $related = [];
        foreach ($res['results'] as $item) {
            $title = strtolower($item['title'] ?? '');
            $tokens = preg_split('/[^a-z0-9]+/i', $title, -1, PREG_SPLIT_NO_EMPTY);
            $tokens = array_values(array_filter($tokens, fn($t) => strlen($t) > 2));
            for ($i = 0; $i < count($tokens) - 1; $i++) {
                $ng = $tokens[$i] . ' ' . $tokens[$i + 1];
                $ngrams[$ng] = ($ngrams[$ng] ?? 0) + 1;
            }
            // quick related: take title tokens of 3-5 chars as candidates
            foreach ($tokens as $t) {
                if (strlen($t) >= 4) {
                    $related[$t] = ($related[$t] ?? 0) + 1;
                }
            }
        }
        arsort($ngrams);
        arsort($related);
        $payload = [
            'related' => array_slice($ngrams + $related, 0, 25, true),
            'serp'    => $res['results'],
        ];
        $this->topics->saveCluster($seed, array_keys($payload['related']), 'expand');
        return $payload;
    }

    /**
     * Very light clustering of given keywords using Jaccard similarity.
     *
     * @param array<string> $keywords
     * @return array<int, array<string>>
     */
    public function cluster(array $keywords): array
    {
        $clusters = [];
        foreach ($keywords as $kw) {
            $tokens = $this->tokenize($kw);
            $placed = false;
            foreach ($clusters as &$cluster) {
                $rep = $this->tokenize($cluster[0]);
                $sim = $this->jaccard($tokens, $rep);
                if ($sim >= 0.25) {
                    $cluster[] = $kw;
                    $placed = true;
                    break;
                }
            }
            if (!$placed) {
                $clusters[] = [$kw];
            }
        }
        // store clusters
        foreach ($clusters as $idx => $group) {
            $label = 'cluster-' . ($idx + 1);
            $this->topics->saveCluster($label, $group, 'cluster');
        }
        return $clusters;
    }

    private function tokenize(string $s): array
    {
        $parts = preg_split('/[^a-z0-9]+/i', strtolower($s), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique($parts));
    }

    private function jaccard(array $a, array $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }
        $ia = array_intersect($a, $b);
        $ua = array_unique(array_merge($a, $b));
        return count($ia) / count($ua);
    }
}
