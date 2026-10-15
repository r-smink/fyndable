<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Fyndable_Demo_Fixtures
{
    public static function dashboard(): array
    {
        return [
            'score' => 74,
            'total_posts' => 38,
            'issues' => [
                ['type' => 'critical', 'label' => '8 pagina’s missen een SEO-titel', 'count' => 8, 'action' => 'bulk_title', 'key' => 'missing_title', 'affected' => []],
                ['type' => 'warning', 'label' => '11 pagina’s missen een focuszoekwoord', 'count' => 11, 'action' => 'bulk_keyphrase', 'key' => 'missing_keyphrase', 'affected' => []],
                ['type' => 'info', 'label' => '6 pagina’s hebben te weinig interne links', 'count' => 6, 'action' => 'link_assistant', 'key' => 'missing_links', 'affected' => []],
            ],
            'quick_wins' => [
                ['post_id' => 9001, 'title' => 'Airco onderhoud in Utrecht', 'edit_url' => '#', 'reason' => 'Sterke pagina, maar de meta description kan meer lokale intentie bevatten.'],
                ['post_id' => 9002, 'title' => 'Warmtepomp of cv-ketel?', 'edit_url' => '#', 'reason' => 'Goed bereik, maar nog geen duidelijke focuskeyphrase.'],
                ['post_id' => 9003, 'title' => 'Zakelijke klimaatbeheersing', 'edit_url' => '#', 'reason' => 'Voeg interne links toe naar de belangrijkste dienstenpagina’s.'],
            ],
            'breakdown' => [
                ['label' => 'SEO Titles', 'done' => 30, 'total' => 38, 'pct' => 79],
                ['label' => 'Meta Descriptions', 'done' => 32, 'total' => 38, 'pct' => 84],
                ['label' => 'Focus Keyphrases', 'done' => 27, 'total' => 38, 'pct' => 71],
                ['label' => 'Open Graph Tags', 'done' => 29, 'total' => 38, 'pct' => 76],
                ['label' => 'Internal Links', 'done' => 32, 'total' => 38, 'pct' => 84],
                ['label' => 'Content Length', 'done' => 34, 'total' => 38, 'pct' => 89],
            ],
            'is_demo' => true,
        ];
    }

    public static function keywords(): array
    {
        $rows = [
            [1, 'airco installeren utrecht', 720, 'medium', 'transactional', 4, 3, 'active'],
            [2, 'warmtepomp installateur utrecht', 590, 'high', 'transactional', 8, 5, 'active'],
            [3, 'airco onderhoud kosten', 480, 'medium', 'commercial', 12, -2, 'active'],
            [4, 'beste airco voor slaapkamer', 390, 'low', 'commercial', 6, 7, 'active'],
            [5, 'klimaatbeheersing kantoor', 260, 'medium', 'commercial', 15, 4, 'active'],
            [6, 'subsidie warmtepomp 2026', 1300, 'high', 'informational', 19, 9, 'active'],
            [7, 'airco energieverbruik berekenen', 210, 'low', 'informational', 9, 6, 'active'],
            [8, 'monoblock airco zonder buitenunit', 880, 'medium', 'commercial', 22, 11, 'active'],
        ];

        return array_map(static function (array $row): array {
            return [
                'id' => $row[0], 'keyword' => $row[1], 'search_volume' => $row[2],
                'difficulty' => $row[3], 'search_intent' => $row[4], 'rank' => $row[5],
                'position_change' => $row[6], 'status' => $row[7], 'language' => 'nl',
                'cpc' => '2.40', 'cluster_id' => 1, 'cluster_name' => 'Klimaatoplossingen Utrecht',
                'is_new' => 0, 'created_at' => gmdate('Y-m-d H:i:s', strtotime('-30 days')),
            ];
        }, $rows);
    }

    public static function ranks(): array
    {
        return [
            ['id' => 1, 'keyword' => 'airco installeren utrecht', 'url' => home_url('/airco-utrecht/'), 'country' => 'nl', 'language' => 'nl', 'current_position' => 4, 'best_position' => 3, 'previous_position' => 7, 'last_checked' => current_time('mysql'), 'last_provider' => 'demo', 'active' => 1],
            ['id' => 2, 'keyword' => 'warmtepomp installateur utrecht', 'url' => home_url('/warmtepompen/'), 'country' => 'nl', 'language' => 'nl', 'current_position' => 8, 'best_position' => 5, 'previous_position' => 13, 'last_checked' => current_time('mysql'), 'last_provider' => 'demo', 'active' => 1],
            ['id' => 3, 'keyword' => 'airco onderhoud kosten', 'url' => home_url('/airco-onderhoud/'), 'country' => 'nl', 'language' => 'nl', 'current_position' => 12, 'best_position' => 9, 'previous_position' => 10, 'last_checked' => current_time('mysql'), 'last_provider' => 'demo', 'active' => 1],
        ];
    }

    public static function rankHistory(int $keywordId): array
    {
        $start = [1 => 18, 2 => 24, 3 => 17][$keywordId] ?? 20;
        $history = [];
        for ($day = 29; $day >= 0; $day--) {
            $progress = (29 - $day) / 29;
            $position = max(3, (int) round($start - ($progress * ($start - (3 + $keywordId * 2))) + sin($day) * 1.5));
            $history[] = ['position' => $position, 'url' => home_url('/demo-resultaat/'), 'checked_at' => gmdate('Y-m-d', strtotime('-' . $day . ' days'))];
        }
        return $history;
    }

    public static function topicCluster(): array
    {
        return [
            'id' => 1,
            'topic' => 'Klimaatoplossingen Utrecht',
            'language' => 'nl',
            'generated_at' => gmdate('Y-m-d H:i:s', strtotime('-5 days')),
            'saved_at' => gmdate('Y-m-d H:i:s', strtotime('-5 days')),
            'total_pages' => 13,
            'estimated_months' => 3,
            'topical_authority_score_potential' => 88,
            'pillar_page' => [
                'title' => 'Complete gids voor een comfortabel en energiezuinig binnenklimaat',
                'description' => 'Overzichtspagina die airco, warmtepompen, ventilatie, kosten en onderhoud met elkaar verbindt.',
                'target_keyword' => 'klimaatbeheersing utrecht',
                'target_word_count' => 2800,
                'search_intent' => 'commercial',
                'priority' => 'high',
            ],
            'clusters' => [
                [
                    'name' => 'Airco installeren',
                    'description' => 'Keuze, installatie en lokale dienstverlening.',
                    'hub_page' => ['title' => 'Airco installeren in Utrecht: mogelijkheden en kosten', 'target_keyword' => 'airco installeren utrecht', 'target_word_count' => 1800, 'priority' => 'high'],
                    'supporting_pages' => [
                        ['title' => 'Welke airco past bij een slaapkamer?', 'target_keyword' => 'beste airco slaapkamer', 'target_word_count' => 1200, 'priority' => 'high'],
                        ['title' => 'Monoblock of split-airco vergelijken', 'target_keyword' => 'monoblock of split airco', 'target_word_count' => 1400, 'priority' => 'medium'],
                        ['title' => 'Wat verbruikt een airco per jaar?', 'target_keyword' => 'airco energieverbruik', 'target_word_count' => 1100, 'priority' => 'medium'],
                    ],
                ],
                [
                    'name' => 'Warmtepompen',
                    'description' => 'Advies, subsidies en woninggeschiktheid.',
                    'hub_page' => ['title' => 'Warmtepomp laten installeren in regio Utrecht', 'target_keyword' => 'warmtepomp installateur utrecht', 'target_word_count' => 1900, 'priority' => 'high'],
                    'supporting_pages' => [
                        ['title' => 'Is mijn woning geschikt voor een warmtepomp?', 'target_keyword' => 'woning geschikt warmtepomp', 'target_word_count' => 1300, 'priority' => 'high'],
                        ['title' => 'Warmtepompsubsidie uitgelegd', 'target_keyword' => 'subsidie warmtepomp 2026', 'target_word_count' => 1200, 'priority' => 'medium'],
                        ['title' => 'Hybride en volledig elektrische warmtepomp vergelijken', 'target_keyword' => 'hybride of elektrische warmtepomp', 'target_word_count' => 1500, 'priority' => 'medium'],
                    ],
                ],
                [
                    'name' => 'Service en onderhoud',
                    'description' => 'Onderhoud, storingen en levensduur.',
                    'hub_page' => ['title' => 'Onderhoud voor airco en warmtepomp', 'target_keyword' => 'airco onderhoud utrecht', 'target_word_count' => 1600, 'priority' => 'high'],
                    'supporting_pages' => [
                        ['title' => 'Hoe vaak heeft een airco onderhoud nodig?', 'target_keyword' => 'hoe vaak airco onderhoud', 'target_word_count' => 1000, 'priority' => 'medium'],
                        ['title' => 'De meest voorkomende aircostoringen', 'target_keyword' => 'airco storing oplossen', 'target_word_count' => 1100, 'priority' => 'low'],
                    ],
                ],
            ],
            'internal_linking_strategy' => [
                'Link iedere ondersteunende pagina naar de eigen hubpagina.',
                'Verbind kosten- en keuzeartikelen met de centrale pillar page.',
                'Gebruik lokale ankerteksten voor de dienstenpagina’s.',
            ],
            'content_calendar' => [
                ['week' => 1, 'action' => 'Publiceer de centrale pillar page', 'pages' => ['Complete gids binnenklimaat']],
                ['week' => 2, 'action' => 'Bouw het aircocluster uit', 'pages' => ['Airco installeren', 'Airco voor slaapkamer']],
                ['week' => 3, 'action' => 'Publiceer het warmtepompcluster', 'pages' => ['Warmtepomp installeren', 'Woninggeschiktheid']],
                ['week' => 4, 'action' => 'Versterk service en interne links', 'pages' => ['Onderhoud', 'Storingen']],
            ],
            'is_demo' => true,
        ];
    }

    public static function apexflow(): array
    {
        return [
            'enabled' => true,
            'monthly_limit' => 20,
            'posts_this_month' => 6,
            'pool_size' => 14,
            'planned' => 5,
            'next_cron' => time() + DAY_IN_SECONDS,
            'last_run' => gmdate('Y-m-d H:i:s', strtotime('-2 days')),
            'profile' => [
                'business' => 'Van Dijk Klimaattechniek',
                'industry' => 'Installatie en klimaatbeheersing',
                'audience' => 'Huiseigenaren en mkb in regio Utrecht',
                'proposition' => 'Energiezuinige airco’s en warmtepompen met lokale service',
            ],
            'is_demo' => true,
        ];
    }

    public static function apexflowPlan(): array
    {
        return [
            ['title' => 'Wat kost een airco inclusief installatie in Utrecht?', 'keyword' => 'airco installeren utrecht', 'date' => gmdate('Y-m-d', strtotime('+3 days')), 'status' => 'planned'],
            ['title' => 'Warmtepomp kiezen: 7 aandachtspunten voor woningen uit de jaren 90', 'keyword' => 'warmtepomp installateur utrecht', 'date' => gmdate('Y-m-d', strtotime('+7 days')), 'status' => 'planned'],
            ['title' => 'Zo verlaagt periodiek airco-onderhoud uw energieverbruik', 'keyword' => 'airco onderhoud kosten', 'date' => gmdate('Y-m-d', strtotime('+10 days')), 'status' => 'planned'],
        ];
    }

    public static function contentBrief(string $keyword): array
    {
        $keyword = $keyword ?: 'airco installeren utrecht';
        return [
            'keyword' => $keyword,
            'generated_at' => current_time('mysql'),
            'serp_overview' => ['result_count' => 10, 'common_formats' => ['dienstpagina', 'koopgids', 'lokale landingspagina']],
            'difficulty' => 46,
            'competitor_analysis' => ['avg_word_count' => 1450, 'common_topics' => ['kosten', 'installatie', 'energieverbruik', 'onderhoud']],
            'recommended_word_count' => 1600,
            'recommended_headings' => ['Wat kost een airco inclusief installatie?', 'Welke airco past bij uw woning?', 'Installatie en onderhoud in regio Utrecht'],
            'recommended_questions' => ['Hoe lang duurt een airco-installatie?', 'Heb ik een vergunning nodig?', 'Wat verbruikt een airco per jaar?'],
            'recommended_entities' => ['Utrecht', 'warmtepomp', 'energielabel', 'split-unit'],
            'recommended_lsi' => ['airco laten plaatsen', 'energiezuinige airco', 'airco installateur'],
            'content_outline' => 'Open met het lokale probleem, vergelijk oplossingen, geef transparante prijsfactoren en sluit af met een adviesgesprek.',
            'search_intent' => 'commercial',
            'target_audience' => 'Huiseigenaren in regio Utrecht die binnen drie maanden willen installeren.',
            'content_angle' => 'Praktisch lokaal advies met transparante installatie- en gebruikskosten.',
            'internal_link_opportunities' => [],
            'sources' => [],
            'brief_id' => 'fyndable_demo_brief',
            'is_demo' => true,
        ];
    }

    public static function tour(): array
    {
        return [
            ['slug' => 'ai-seo-dashboard', 'selector' => '#seo-dash-main', 'title' => 'SEO-nulmeting', 'text' => 'Begin met de gezondheid van de website, concrete problemen en snelle verbeterkansen.'],
            ['slug' => 'ai-seo-keywords', 'selector' => '#keywords-tbody', 'title' => 'Zoekwoordkansen', 'text' => 'Fyndable combineert volume, intentie, moeilijkheid en actuele positie in één workflow.'],
            ['slug' => 'ai-seo-competitor-research', 'selector' => '.wrap', 'title' => 'Concurrentieanalyse', 'text' => 'Vergelijk concurrenten en ontdek onderwerpen waarop zij nu verkeer winnen.'],
            ['slug' => 'ai-seo-topic-clusters', 'selector' => '#tc-saved-list', 'title' => 'Onderwerpgroepen', 'text' => 'Zet losse zoekwoorden om in een contentarchitectuur met pillar- en ondersteunende pagina’s. Open de voorbeeldgroep om alle 13 geplande pagina’s te bekijken.'],
            ['slug' => 'ai-seo-content-calendar', 'selector' => '.wrap', 'title' => 'Contentplanning', 'text' => 'Plan de gekozen onderwerpen in een haalbare publicatiekalender.'],
            ['slug' => 'ai-seo-automation', 'selector' => '.wrap', 'title' => 'ApexFlow', 'text' => 'ApexFlow houdt onderzoek, planning en contentproductie automatisch op gang.'],
            ['slug' => 'ai-seo-rank-tracker', 'selector' => '.wrap', 'title' => 'Rankings en lokale vindbaarheid', 'text' => 'Volg landelijke posities en lokale zichtbaarheid vanuit hetzelfde scherm.'],
            ['slug' => 'ai-seo-llm-tracker', 'selector' => '.wrap', 'title' => 'AI-zichtbaarheid', 'text' => 'Meet of het merk wordt genoemd in AI-antwoorden en waar optimalisatie nodig is.'],
            ['slug' => 'ai-seo-created-posts', 'selector' => '.wrap', 'title' => 'Van kans naar resultaat', 'text' => 'Alle gemaakte en geplande content blijft controleerbaar voor het team.'],
        ];
    }
}
