<?php

use App\Services\DataForSeoFormat;

/** A SaaS response wrapping one DataForSEO result object. */
function dfsEnvelope(array $result): array
{
    return ['success' => true, 'data' => ['version' => '0.1', 'status_code' => 20000, 'tasks' => [['status_code' => 20000, 'result' => [$result]]]]];
}

it('extracts the answer, sources and brand mention from an LLM response', function () {
    $response = dfsEnvelope([
        'model_name' => 'gpt-4.1-mini',
        'web_search' => true,
        'items' => [
            ['type' => 'reasoning', 'sections' => [['type' => 'summary_text', 'text' => 'thinking...']]],
            ['type' => 'message', 'sections' => [[
                'type' => 'text',
                'text' => "Top stores:\n- **Acme Outdoor**\n- Other",
                'annotations' => [
                    ['title' => 'Acme', 'url' => 'https://acme.com/tents'],
                    ['title' => 'Dup', 'url' => 'https://acme.com/tents'],
                    ['title' => 'Bad', 'url' => 'javascript:alert(1)'],
                ],
            ]]],
        ],
        'search_results' => [['title' => 'Review', 'url' => 'https://review.example/x']],
    ]);

    $out = DataForSeoFormat::llmAnswer($response, DataForSeoFormat::brandTerms('Acme Outdoor', 'acme-outdoor.myshopify.com'));

    expect($out['answer'])->toBe("Top stores:\n- **Acme Outdoor**\n- Other")
        ->and($out['model'])->toBe('gpt-4.1-mini')
        ->and($out['web_search'])->toBeTrue()
        ->and($out['brand_mentioned'])->toBeTrue()
        ->and(array_column($out['sources'], 'url'))->toBe(['https://acme.com/tents', 'https://review.example/x']);
});

it('reports a missing brand mention and handles an empty result', function () {
    $out = DataForSeoFormat::llmAnswer(dfsEnvelope(['items' => [['type' => 'message', 'sections' => [['text' => 'Try Globex.']]]]]), ['acme']);
    expect($out['brand_mentioned'])->toBeFalse();

    $empty = DataForSeoFormat::llmAnswer(['success' => true, 'data' => ['tasks' => []]]);
    expect($empty['answer'])->toBe('')->and($empty['brand_mentioned'])->toBeNull();
});

it('reads target metrics and sorts platforms by mentions', function () {
    $out = DataForSeoFormat::targetMetrics(dfsEnvelope(['aggregated_metrics' => [
        'total' => ['mentions' => 42, 'ai_search_volume' => 1300],
        'platform' => [['key' => 'google', 'mentions' => 10], ['key' => 'chat_gpt', 'mentions' => 32]],
        'sources_domain' => [['key' => 'acme.com', 'mentions' => 7]],
    ]]));

    expect($out['mentions'])->toBe(42)
        ->and($out['ai_search_volume'])->toBe(1300)
        ->and($out['platforms'][0]['label'])->toBe('chat_gpt')
        ->and($out['source_domains'][0])->toBe(['label' => 'acme.com', 'mentions' => 7, 'ai_search_volume' => 0]);
});

it('reads current and legacy top pages', function () {
    $current = DataForSeoFormat::topItems(dfsEnvelope(['items' => [
        ['page' => 'https://acme.com/a', 'total' => ['mentions' => 3, 'ai_search_volume' => 50]],
        ['page' => 'https://acme.com/b', 'total' => ['mentions' => 9, 'ai_search_volume' => 10]],
    ]]));
    expect(array_column($current, 'label'))->toBe(['https://acme.com/b', 'https://acme.com/a']);

    $legacy = DataForSeoFormat::topItems(dfsEnvelope(['items' => [
        ['key' => 'https://acme.com/c', 'platform' => [['key' => 'google', 'mentions' => 2], ['key' => 'chat_gpt', 'mentions' => 5]]],
    ]]));
    expect($legacy[0])->toBe(['label' => 'https://acme.com/c', 'mentions' => 7, 'ai_search_volume' => 0]);
});

it('reads search mentions, timeseries delta and keyword volumes', function () {
    $mentions = DataForSeoFormat::searchMentions(dfsEnvelope(['total_count' => 120, 'items' => [[
        'platform' => 'chat_gpt', 'question' => 'best tents?', 'answer' => str_repeat('a', 700),
        'ai_search_volume' => 90, 'sources' => [['url' => 'https://acme.com', 'title' => 'Acme']],
    ]]]));
    expect($mentions['total'])->toBe(120)
        ->and(mb_strlen($mentions['items'][0]['answer']))->toBe(603)
        ->and($mentions['items'][0]['sources'][0]['title'])->toBe('Acme');

    $points = DataForSeoFormat::timeseriesDelta(dfsEnvelope(['items' => [
        ['date' => '2026-10-01', 'delta_mentions' => -2, 'delta_ai_search_volume' => 10],
        ['date' => '2026-09-01', 'delta_mentions' => 5, 'delta_ai_search_volume' => 0],
    ]]));
    expect(array_column($points, 'date'))->toBe(['2026-09-01', '2026-10-01']);

    $volumes = DataForSeoFormat::keywordVolumes(dfsEnvelope(['items' => [[
        'keyword' => 'tent', 'ai_search_volume' => 400,
        'ai_monthly_searches' => [['year' => 2026, 'month' => 9, 'ai_search_volume' => 350], ['year' => 2026, 'month' => 8, 'ai_search_volume' => 300]],
    ]]]));
    expect($volumes[0]['ai_search_volume'])->toBe(400)
        ->and(array_column($volumes[0]['monthly'], 'month'))->toBe(['2026-08', '2026-09']);
});

it('reads google trends graph points and related queries', function () {
    $out = DataForSeoFormat::trends(dfsEnvelope(['keywords' => ['tent'], 'items' => [
        ['type' => 'google_trends_graph', 'keywords' => ['tent', 'tarp'], 'averages' => [60, 20], 'data' => [
            ['date_from' => '2026-10-01', 'values' => [54, 18]],
            ['date_from' => '2026-10-08', 'missing_data' => true, 'values' => [null, null]],
        ]],
        ['type' => 'google_trends_queries_list', 'data' => ['top' => [['query' => 'tent sale', 'value' => '100']], 'rising' => [['query' => 'tent 2026', 'value' => '250']]]],
    ]]));

    expect($out['keywords'])->toBe(['tent', 'tarp'])
        ->and($out['averages'])->toBe([60, 20])
        ->and($out['points'])->toBe([['date' => '2026-10-01', 'values' => [54, 18]]])
        ->and($out['rising_queries'][0]['query'])->toBe('tent 2026');
});

it('reads a backlink summary and live backlinks', function () {
    $summary = DataForSeoFormat::backlinkSummary(dfsEnvelope([
        'target' => 'acme.com', 'rank' => 41, 'backlinks' => 1200, 'referring_domains' => 88,
        'backlinks_spam_score' => 3, 'first_seen' => '2019-04-01 10:00:00 +00:00',
    ]));
    expect($summary['rank'])->toBe(41)->and($summary['spam_score'])->toBe(3)->and($summary['first_seen'])->toBe('2019-04-01')
        ->and($summary['broken_backlinks'])->toBeNull();

    $live = DataForSeoFormat::backlinks(dfsEnvelope(['total_count' => 500, 'items' => [[
        'url_from' => 'https://blog.example/post', 'domain_from' => 'blog.example', 'anchor' => ' Acme ', 'dofollow' => true, 'domain_from_rank' => 30,
    ]]]));
    expect($live['total'])->toBe(500)->and($live['items'][0]['anchor'])->toBe('Acme')->and($live['items'][0]['dofollow'])->toBeTrue();
});

it('builds brand terms and recognizes proxy errors', function () {
    expect(DataForSeoFormat::brandTerms('Acme', 'acme-outdoor.myshopify.com', 'https://www.acme.com/'))
        ->toBe(['acme', 'acme.com', 'acme-outdoor.myshopify.com', 'acme-outdoor'])
        ->and(DataForSeoFormat::failed(['error' => 'x']))->toBeTrue()
        ->and(DataForSeoFormat::failed(['success' => false]))->toBeTrue()
        ->and(DataForSeoFormat::failed(['success' => true]))->toBeFalse();
});
