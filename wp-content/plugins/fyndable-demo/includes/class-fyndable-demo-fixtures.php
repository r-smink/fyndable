<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Fyndable_Demo_Fixtures
{
    private const AUDIO_BITRATE = 128000; // 128 kbps CBR MP3
    private const AUDIO_PAUSE_MS = 2500;  // breathing room after the voiceover ends

    private static function audioDuration(string $relativePath): int
    {
        $path = FYNDABLE_DEMO_DIR . $relativePath;
        if (!file_exists($path)) {
            return 9000; // fallback reading time when no audio file is present
        }

        $offset = 0;
        $handle = @fopen($path, 'rb');
        if ($handle) {
            $header = fread($handle, 10);
            fclose($handle);
            if (strlen($header) >= 10 && substr($header, 0, 3) === 'ID3') {
                $bytes = array_values(unpack('C*', substr($header, 6, 4)));
                $size = (($bytes[0] & 0x7F) << 21)
                      | (($bytes[1] & 0x7F) << 14)
                      | (($bytes[2] & 0x7F) << 7)
                      | ($bytes[3] & 0x7F);
                $flags = ord($header[5]);
                $footer = ($flags & 0x10) ? 10 : 0;
                $offset = 10 + $size + $footer;
            }
        }

        $audioBytes = max(0, filesize($path) - $offset);
        $ms = (int) round(($audioBytes * 8 / self::AUDIO_BITRATE) * 1000);
        return $ms + self::AUDIO_PAUSE_MS;
    }

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

    public static function createdPosts(): array
    {
        $posts = [
            ['id' => 9001, 'title' => 'Wat kost een airco inclusief installatie in Utrecht?', 'date' => gmdate('Y-m-d H:i:s', strtotime('-6 days')), 'status' => 'publish', 'edit_status' => 'completed', 'review_status' => 'yes', 'language' => 'nl', 'word_count' => 1620, 'cluster_name' => 'Klimaatoplossingen Utrecht', 'generated_date' => gmdate('Y-m-d H:i:s', strtotime('-7 days'))],
            ['id' => 9002, 'title' => 'Warmtepomp kiezen: 7 aandachtspunten voor woningen uit de jaren 90', 'date' => gmdate('Y-m-d H:i:s', strtotime('-3 days')), 'status' => 'publish', 'edit_status' => 'completed', 'review_status' => 'needs_attention', 'language' => 'nl', 'word_count' => 1840, 'cluster_name' => 'Klimaatoplossingen Utrecht', 'generated_date' => gmdate('Y-m-d H:i:s', strtotime('-4 days'))],
            ['id' => 9003, 'title' => 'Zo verlaagt periodiek airco-onderhoud uw energieverbruik', 'date' => gmdate('Y-m-d H:i:s', strtotime('+2 days')), 'status' => 'future', 'edit_status' => 'pending', 'review_status' => 'reviewing', 'language' => 'nl', 'word_count' => 1210, 'cluster_name' => 'Klimaatoplossingen Utrecht', 'generated_date' => gmdate('Y-m-d H:i:s', strtotime('-1 day'))],
            ['id' => 9004, 'title' => 'Monoblock of split-airco: welke past bij uw woning?', 'date' => gmdate('Y-m-d H:i:s', strtotime('-10 days')), 'status' => 'publish', 'edit_status' => 'completed', 'review_status' => 'yes', 'language' => 'nl', 'word_count' => 1450, 'cluster_name' => 'Klimaatoplossingen Utrecht', 'generated_date' => gmdate('Y-m-d H:i:s', strtotime('-11 days'))],
            ['id' => 9005, 'title' => 'Warmtepompsubsidie 2026: zo vraagt u die aan', 'date' => gmdate('Y-m-d H:i:s', strtotime('+5 days')), 'status' => 'future', 'edit_status' => 'pending', 'review_status' => 'no', 'language' => 'nl', 'word_count' => 980, 'cluster_name' => 'Klimaatoplossingen Utrecht', 'generated_date' => gmdate('Y-m-d H:i:s', strtotime('-1 day'))],
        ];

        return array_map(static function (array $post): array {
            $post['modified'] = $post['date'];
            $post['thumbnail'] = '';
            $post['cluster_id'] = 1;
            $post['edit_url'] = '#';
            $post['view_url'] = home_url('/');
            $post['post_type'] = 'post';
            $post['is_demo'] = true;
            return $post;
        }, $posts);
    }

    public static function createdPostStats(): array
    {
        return [
            'edit_status' => ['completed' => 3, 'pending' => 2, 'failed' => 0],
            'review_status' => ['yes' => 2, 'no' => 1, 'needs_attention' => 1, 'reviewing' => 1, 'failed' => 0],
            'post_status' => ['published' => 3, 'drafts' => 0, 'future' => 2, 'private' => 0, 'total' => 5],
            'languages' => ['nl' => 5],
            'is_demo' => true,
        ];
    }

    public static function competitors(): array
    {
        $domain = wp_parse_url(home_url(), PHP_URL_HOST);
        return [
            'klimaatstad.nl' => [
                'domain' => 'klimaatstad.nl',
                'keyword_count' => 84,
                'content_strategy' => 'Commercial + blog',
                'ai_content_percentage' => 62,
                'last_updated' => gmdate('Y-m-d H:i:s', strtotime('-1 day')),
                'sitemap_url' => '',
                'full_site' => false,
            ],
            'verwarming-expert.nl' => [
                'domain' => 'verwarming-expert.nl',
                'keyword_count' => 121,
                'content_strategy' => 'Informational long-tail',
                'ai_content_percentage' => 31,
                'last_updated' => gmdate('Y-m-d H:i:s', strtotime('-3 days')),
                'sitemap_url' => '',
                'full_site' => true,
            ],
            'duurzaamverwarmen.nl' => [
                'domain' => 'duurzaamverwarmen.nl',
                'keyword_count' => 57,
                'content_strategy' => 'Lokale landingspagina’s',
                'ai_content_percentage' => 44,
                'last_updated' => gmdate('Y-m-d H:i:s', strtotime('-5 days')),
                'sitemap_url' => '',
                'full_site' => false,
            ],
        ];
    }

    public static function apexflowPlanOption(): array
    {
        return array_merge(self::apexflowPlan(), [
            ['title' => 'Hoe vaak heeft een airco onderhoud nodig?', 'keyword' => 'hoe vaak airco onderhoud', 'date' => gmdate('Y-m-d', strtotime('+14 days')), 'status' => 'planned'],
            ['title' => 'Is mijn woning geschikt voor een warmtepomp?', 'keyword' => 'woning geschikt warmtepomp', 'date' => gmdate('Y-m-d', strtotime('+17 days')), 'status' => 'planned'],
        ]);
    }

    public static function apexflowPool(): array
    {
        return [
            'generated_at' => gmdate('Y-m-d H:i:s', strtotime('-4 days')),
            'keywords' => [
                ['keyword' => 'airco installateur amersfoort', 'volume' => 320, 'difficulty' => 34, 'score' => 82, 'status' => 'new', 'local_intent' => true],
                ['keyword' => 'warmtepomp offerte aanvragen', 'volume' => 480, 'difficulty' => 41, 'score' => 78, 'status' => 'new'],
                ['keyword' => 'kosten split unit airco', 'volume' => 260, 'difficulty' => 27, 'score' => 74, 'status' => 'planned'],
                ['keyword' => 'ventilatie warmteterugwinning voordelen', 'volume' => 190, 'difficulty' => 22, 'score' => 69, 'status' => 'new'],
                ['keyword' => 'airco slaapkamer stil', 'volume' => 540, 'difficulty' => 36, 'score' => 66, 'status' => 'new', 'local_intent' => false],
                ['keyword' => 'iserver subsidie warmtepomp 2026', 'volume' => 1300, 'difficulty' => 55, 'score' => 64, 'status' => 'planned'],
                ['keyword' => 'airco inverter vs on-off', 'volume' => 140, 'difficulty' => 18, 'score' => 58, 'status' => 'new'],
            ],
        ];
    }

    public static function tour(): array
    {
        $a = static fn(string $id): string => 'assets/audio/' . $id . '.mp3';
        $steps = [
            // 1. Dashboard / SEO-nulmeting
            [
                'id' => 'step-01-dashboard-score', 'slug' => 'ai-seo-dashboard',
                'selector' => '#seo-dash-scan', 'section' => 'SEO-nulmeting', 'substep' => '1/3',
                'title' => 'SEO-score & site-analyse',
                'what' => 'Het dashboard is de gezondheidscheck van de hele website. Fyndable scant alle pagina’s en posts en vertaalt die analyse naar één score en een overzicht van wat er goed gaat en wat aandacht nodig heeft.',
                'how' => [
                    'Klik op "Analyze Site" om de volledige scan te starten — in deze demo is dat voorgeladen.',
                    'De scorecirkel links toont de totale SEO-score: groen is gezond (80+), oranje vergt werk, rood is kritiek.',
                    'Onder de score zie je hoeveel posts zijn geanalyseerd.',
                ],
                'tip' => 'Start elke klantpresentatie hier: de score van 74 is direct begrijpelijk en creëert urgentie zonder technisch jargon.',
                'audio' => $a('step-01-dashboard-score'),
                'voiceover' => 'Welkom in Fyndable. We beginnen bij de gezondheidscheck van de website. Met één klik op "Analyze Site" scant de plugin alle pagina\'s en posts en vertaalt dat naar een totale SEO-score. Groen betekent gezond, oranje vraagt aandacht, rood is kritiek. Deze demowebsite van Van Dijk Klimaattechniek scoort 74 — er is dus ruimte voor winst. Onder de score zie je hoeveel pagina\'s zijn meegenomen in de analyse.',
            ],
            [
                'id' => 'step-02-dashboard-breakdown', 'slug' => 'ai-seo-dashboard',
                'selector' => '#seo-breakdown', 'section' => 'SEO-nulmeting', 'substep' => '2/3',
                'title' => 'SEO Breakdown per onderdeel',
                'what' => 'De breakdown toont per SEO-onderdeel (titels, meta descriptions, keyphrases, Open Graph, interne links, contentlengte) hoeveel pagina’s al op orde zijn.',
                'how' => [
                    'Lees elke balk als een voortgangsmeter: 30/38 betekent dat 30 pagina’s een SEO-titel hebben.',
                    'Oranje balken zijn de snelste verbeterpunten — hier ligt de grootste relatieve winst.',
                ],
                'tip' => 'Gebruik de breakdown om te laten zien dat SEO geen black box is maar een checklist die systematisch wordt afgerond.',
                'audio' => $a('step-02-dashboard-breakdown'),
                'voiceover' => 'Naast de score zie je de breakdown: per SEO-onderdeel hoeveel pagina\'s al op orde zijn. Denk aan titels, meta descriptions, focus keyphrases, Open Graph-tags, interne links en contentlengte. Elke balk is een voortgangsmeter. De oranje balken zijn je snelste verbeterpunten — daar ligt de grootste relatieve winst voor de klant.',
            ],
            [
                'id' => 'step-03-dashboard-issues', 'slug' => 'ai-seo-dashboard',
                'selector' => '#seo-quickwins', 'section' => 'SEO-nulmeting', 'substep' => '3/3',
                'title' => 'Issues & Quick Wins',
                'what' => 'Rechts staan concrete bevindingen: kritieke issues, waarschuwingen en "quick wins" — pagina’s met goede content maar ontbrekende meta-data.',
                'how' => [
                    'Klik op een issue om de detailmodal met alle getroffen pagina’s te openen.',
                    'Quick wins zijn de laaghangende fruit-pagina’s: met de Bulk Optimizer zijn ze in minuten opgelost.',
                ],
                'tip' => 'Wijs hier op de 8 ontbrekende SEO-titels: dat is exact het soort werk dat Fyndable geautomatiseerd oplost.',
                'audio' => $a('step-03-dashboard-issues'),
                'voiceover' => 'Hieronder de concrete bevindingen. Links de issues: acht pagina\'s missen bijvoorbeeld een SEO-titel. Klik erop en je ziet precies welke pagina\'s. Rechts de quick wins — pagina\'s met goede content maar ontbrekende meta-data. Met de Bulk Optimizer los je die in minuten op. Perfecte openingscasus voor een demo: dit is precies het werk dat Fyndable geautomatiseerd oppakt.',
            ],
            // 2. Keywords
            [
                'id' => 'step-04-keywords-toolbar', 'slug' => 'ai-seo-keywords',
                'selector' => '.keywords-actions-bar', 'section' => 'Zoekwoordkansen', 'substep' => '1/3',
                'title' => 'Zoekwoorden toevoegen & verkennen',
                'what' => 'Dit is het centrale zoekwoordbeheer: één workflow waarin je zoekwoorden toevoegt, AI-varianten genereert en SERP-data verzamelt.',
                'how' => [
                    'Typ een zoekwoord in het invoerveld en klik op het vergrootglas om het toe te voegen.',
                    'Via "Generate keywords" en "Keywords explorer" laat je AI nieuwe varianten met volume en difficulty voorstellen.',
                    'Met de prullenbak en refresh-knop beheer je selecties en verse data.',
                ],
                'tip' => 'Probeer in de demo "airco onderhoud" toe te voegen — het zoekwoord verschijnt direct in de tabel en blijft bewaard via "Demo resetten".',
                'audio' => $a('step-04-keywords-toolbar'),
                'voiceover' => 'Dit is het centrale zoekwoordbeheer. Typ een zoekwoord in en voeg het toe, of laat AI via "Generate keywords" en de "Keywords explorer" nieuwe varianten voorstellen — compleet met zoekvolume en moeilijkheidsgraad. Probeer het gerust: voeg bijvoorbeeld "airco onderhoud" toe. In de demo blijft je invoer bewaard tot je de demo reset.',
            ],
            [
                'id' => 'step-05-keywords-filters', 'slug' => 'ai-seo-keywords',
                'selector' => '.keywords-tabs', 'section' => 'Zoekwoordkansen', 'substep' => '2/3',
                'title' => 'Filter-tabs & snelle inzichten',
                'what' => 'De tabs boven de tabel filteren de zoekwoorden: alle, alleen getrackt, gerangschikt, positieveranderingen en nieuwe rankings.',
                'how' => [
                    'Klik op "Position changes" om direct te zien welke zoekwoorden zijn gestegen of gezakt.',
                    'Gebruik de cluster-dropdown om per onderwerpgroep te filteren.',
                    'Sorteer op volume, difficulty of rank via de kolomkoppen.',
                ],
                'tip' => 'De tellers op de tabs geven in één blik de omvang van je zoekwoordportfolio.',
                'audio' => $a('step-05-keywords-filters'),
                'voiceover' => 'De tabs boven de tabel filteren je portfolio: alle zoekwoorden, alleen getrackte, gerangschikte, positieveranderingen en nieuwe rankings. De tellers geven in één blik de omvang. Sorteer op volume, difficulty of rank via de kolomkoppen, of filter op onderwerpgroep met de cluster-dropdown.',
            ],
            [
                'id' => 'step-06-keywords-table', 'slug' => 'ai-seo-keywords',
                'selector' => '#keywords-table', 'section' => 'Zoekwoordkansen', 'substep' => '3/3',
                'title' => 'De zoekwoordentabel',
                'what' => 'Elke regel combineert zoekvolume, difficulty, CPC, actuele positie, zoekintentie en SERP-concurrenten — alle data die je nodig hebt om te prioriteren.',
                'how' => [
                    'Vergelijk volume met difficulty: veel volume + lage difficulty = ideale kans.',
                    'De kolom SERP Competitors toont wie je moet verslaan voor dat zoekwoord.',
                    'Intentie (informational/commercial/transactional) bepaalt welk type pagina je maakt.',
                ],
                'tip' => 'Let op "subsidie warmtepomp 2026": hoog volume (1.300) — een perfecte case voor een informatief artikel.',
                'audio' => $a('step-06-keywords-table'),
                'voiceover' => 'Elke regel combineert alles wat je nodig hebt om te prioriteren: zoekvolume, difficulty, cost-per-click, actuele positie, zoekintentie en SERP-concurrenten. De truc: veel volume plus lage difficulty is een ideale kans. Let op "subsidie warmtepomp 2026" — dertienhonderd zoekopdrachten per maand. Een perfecte case voor een informatief artikel.',
            ],
            // 3. Competitor Research
            [
                'id' => 'step-07-competitors-add', 'slug' => 'ai-seo-competitor-research',
                'selector' => '#competitor-domain', 'section' => 'Concurrentieanalyse', 'substep' => '1/2',
                'title' => 'Concurrent toevoegen',
                'what' => 'Voer het domein van een concurrent in en Fyndable bouwt een compleet profiel: zoekwoordstrategie, contentkalender, AI-content-percentage, win/loss-analyse, backlinks en advertenties.',
                'how' => [
                    'Vul het concurrent-domein in (bijv. klimaatstad.nl).',
                    'Optioneel: geef een sitemap-URL en vink "Full site" aan om álle sub-sitemaps te volgen.',
                    'Klik "Add & Analyze" — de eerste analyse duurt enkele minuten.',
                ],
                'tip' => 'De AI-content-detectie laat zien welke concurrenten massaal geautomatiseerd publiceren — een opening om met kwaliteit te winnen.',
                'audio' => $a('step-07-competitors-add'),
                'voiceover' => 'Bij Competitor Research vul je het domein van een concurrent in en bouwt Fyndable een compleet profiel: zoekwoordstrategie, publicatietempo, AI-content-percentage, win-loss-analyse, backlinks en advertenties. Optioneel geef je een sitemap mee en vink je "Full site" aan om alle sub-sitemaps te volgen. De eerste analyse duurt enkele minuten; daarna staat de concurrent in je dashboard.',
            ],
            [
                'id' => 'step-08-competitors-overview', 'slug' => 'ai-seo-competitor-research',
                'selector' => '#competitor-tabs', 'section' => 'Concurrentieanalyse', 'substep' => '2/2',
                'title' => 'Concurrentieprofiel & tabs',
                'what' => 'Per concurrent vergelijk je vijf dimensies via de tabs: Keyword Strategy, Content Calendar, Win/Loss, Backlink Strategy en Ad Copy.',
                'how' => [
                    'Klik "View Details" bij een concurrent om alle tabs te vullen.',
                    'Win/Loss toont op welke zoekwoorden jij hoger staat en waar zij jou verslaan.',
                    'De Ad Copy-tab onthult welke advertentieboodschappen de concurrent gebruikt.',
                ],
                'tip' => 'Win/Loss is de sterkste sales-demo: "Ze scoren beter op X, maar jij wint op Y" is een concreet verhaal.',
                'audio' => $a('step-08-competitors-overview'),
                'voiceover' => 'Per concurrent vergelijk je vijf dimensies via de tabs: Keyword Strategy, Content Calendar, Win/Loss, Backlink Strategy en Ad Copy. De Win/Loss-tab is de sterkste in een salesgesprek: je ziet zwart op wit waar de concurrent wint en waar jij wint. En de AI-content-detectie laat zien welke concurrenten massaal geautomatiseerd publiceren — een opening om met kwaliteit te winnen.',
            ],
            // 4. Topic Clusters
            [
                'id' => 'step-09-clusters-generator', 'slug' => 'ai-seo-topic-clusters',
                'selector' => '[data-card-id="tc-generator"]', 'section' => 'Onderwerpgroepen', 'substep' => '1/3',
                'title' => 'Topic Cluster Generator',
                'what' => 'De generator zet één kernthema om in een complete contentarchitectuur: een pillar page met hub- en ondersteunende pagina’s voor topical authority.',
                'how' => [
                    'Vul het kernthema in, kies taal en diepte (Standard 10-15 of Deep 20-30 clusters).',
                    'Met "Audit Existing Content" check je welke pagina’s je al hebt en waar gaten zitten.',
                    'Klik "Generate Map" — de map verschijnt hieronder in een paar minuten.',
                ],
                'tip' => 'Voor Van Dijk Klimaattechniek is "klimaatbeheersing utrecht" het kernthema — hieronder staat de opgeslagen voorbeeldmap.',
                'audio' => $a('step-09-clusters-generator'),
                'voiceover' => 'Nu komt het strategische deel. De Topic Cluster Generator zet één kernthema om in een complete contentarchitectuur: een pillar page met hub- en ondersteunende pagina\'s. Voor Van Dijk is "klimaatbeheersing utrecht" het kernthema. Kies taal en diepte, audit eventueel je bestaande content, en klik "Generate Map". Hieronder staat al een opgeslagen voorbeeld.',
            ],
            [
                'id' => 'step-10-clusters-pillar', 'slug' => 'ai-seo-topic-clusters',
                'selector' => '#tc-saved-list', 'section' => 'Onderwerpgroepen', 'substep' => '2/3',
                'title' => 'Pillar page & clusters',
                'what' => 'De pillar page is de complete gids waar alle clusters naartoe linken. Eronder zie je de hub- en ondersteunende pagina’s met doelzoekwoord, woordenaantal en prioriteit.',
                'how' => [
                    'De statistiekkaarten bovenaan tonen totaal pagina’s, clusters, geschatte maanden en autoriteitspotentieel.',
                    'Groene prioriteit = hoog; grijs = nice-to-have. Bouw vanuit de pillar naar buiten.',
                    'Elke pagina heeft een doelzoekwoord en aanbevolen lengte voor de contentbrief.',
                ],
                'tip' => '13 geplande pagina’s in 3 clusters = drie maanden gestructureerde contentvoorraad.',
                'audio' => $a('step-10-clusters-pillar'),
                'voiceover' => 'Dit is een gegenereerde clustermap. Bovenaan een complete gids — de pillar page — waar alle clusters naartoe linken. Eronder drie clusters met elk een hubpagina en ondersteunende artikelen, compleet met doelzoekwoord, woordenaantal en prioriteit. Dertien geplande pagina\'s, gespreid over drie maanden: een hele kwartaalplanning in één scherm.',
            ],
            [
                'id' => 'step-11-clusters-generate', 'slug' => 'ai-seo-topic-clusters',
                'selector' => '#tc-toggle-review', 'section' => 'Onderwerpgroepen', 'substep' => '3/3',
                'title' => 'Reviewen & bulk-genereren',
                'what' => 'Van plan naar publicatie: review alle pagina’s, selecteer welke je wilt genereren en zet ze in bulk om naar drafts of geplande posts — inclusief interne linkstrategie en kalendersync.',
                'how' => [
                    'Klik "Review All Pages" om de paginatabel te openen.',
                    'Selecteer pagina’s en klik "Generate Selected" — content wordt via WP-Cron op de achtergrond geschreven.',
                    'Met "Sync to Content Calendar" plan je de hele map op startdatum + interval.',
                ],
                'tip' => 'De geschatte API-kosten staan erbij — je weet vooraf wat een bulk-run kost.',
                'audio' => $a('step-11-clusters-generate'),
                'voiceover' => 'Van plan naar publicatie. Klik "Review All Pages", selecteer welke pagina\'s je wilt schrijven en klik "Generate Selected". De content wordt op de achtergrond via WP-Cron geschreven — je kunt de pagina gewoon sluiten. Met "Sync to Content Calendar" plan je de hele map automatisch op basis van startdatum en interval. De geschatte API-kosten zie je vooraf.',
            ],
            // 5. Content Calendar
            [
                'id' => 'step-12-calendar-grid', 'slug' => 'ai-seo-content-calendar',
                'selector' => '.sseo-calendar', 'section' => 'Contentplanning', 'substep' => '1/2',
                'title' => 'Visuele publicatiekalender',
                'what' => 'Een maandoverzicht van alle content: gepubliceerd, gepland, concepten en ter review. Concepten en geplande posts zijn versleepbaar.',
                'how' => [
                    'Kleurcode: groen = gepland/gepubliceerd, geel = concept, blauw = pending review.',
                    'Sleep een concept of geplande post naar een andere dag om te herplannen.',
                    'Klik op een item om het te openen; navigeer met ← en → tussen maanden.',
                ],
                'tip' => 'Laat de klant zelf een item verslepen — drag-and-drop maakt de planning meteen tastbaar.',
                'audio' => $a('step-12-calendar-grid'),
                'voiceover' => 'De contentkalender geeft een maandoverzicht van alles: gepubliceerd, gepland, concept en ter review. De kleurcode is intuïtief: groen is gepland of live, geel is concept, blauw wacht op review. Concepten en geplande posts sleep je eenvoudig naar een andere dag. Laat de klant gerust zelf even verslepen — dat maakt de planning meteen tastbaar.',
            ],
            [
                'id' => 'step-13-calendar-workflow', 'slug' => 'ai-seo-content-calendar',
                'selector' => '.sseo-two-columns > div:last-child', 'section' => 'Contentplanning', 'substep' => '2/2',
                'title' => 'Content gaps & workflow',
                'what' => 'Rechts staat de operationele sturing: gedetecteerde gaten in de planning, de workflow-status (drafts → review → gepland → gepubliceerd) en wachtende goedkeuringen.',
                'how' => [
                    'Klik "Fill Gap" bij een gemiste dag om die met content te vullen.',
                    'De vier statuskaarten tonen waar content in de pijplijn vastzit.',
                    'Onder "Pending Approvals" keurt of weigert het team ingediende stukken.',
                    'Helemaal onderaan: de Keyword Opportunity Timeline met voorstellen op datum.',
                ],
                'tip' => 'Combineer met ApexFlow: de autopilot vult gaten automatisch, deze kalender houdt je in controle.',
                'audio' => $a('step-13-calendar-workflow'),
                'voiceover' => 'Rechts zie je de operationele sturing. De Content Gap Calendar wijst dagen aan zonder content — één klik op "Fill Gap" vult ze. De vier statuskaarten tonen waar content in de pijplijn zit: drafts, pending review, gepland en gepubliceerd. En onder Pending Approvals keurt het team ingediende stukken goed of af. Volledig onderaan vind je de Keyword Opportunity Timeline met voorstellen op datum.',
            ],
            // 6. ApexFlow
            [
                'id' => 'step-14-apexflow-profile', 'slug' => 'ai-seo-automation',
                'selector' => '#profile_industry', 'section' => 'ApexFlow', 'substep' => '1/3',
                'title' => 'Site-profiel & context',
                'what' => 'ApexFlow scant de site en bepaalt zelf niche, doelgroep en kernthema’s. Hier stuur je dat profiel bij met business-context, eigen zoekwoorden en uit te sluiten onderwerpen.',
                'how' => [
                    'Zet de schakelaar aan om de wekelijkse autopilot te activeren.',
                    'Vul industry, doelgroep en kernthema’s — of klik "Rescan Site Profile" voor een verse AI-scan.',
                    'Beschrijf in "Business context" wat de content moet benadrukken en vermijden.',
                    'Own keywords krijgen voorrang; excluded topics blijven buiten de deur.',
                ],
                'tip' => 'Hoe beter de business-context, hoe scherper de gegenereerde content. Denk aan USP’s, regio en no-go-onderwerpen.',
                'audio' => $a('step-14-apexflow-profile'),
                'voiceover' => 'ApexFlow is de content-autopilot. Hij scant de site en bepaalt zelf niche, doelgroep en kernthema\'s. Hier stuur je dat bij: industry, doelgroep, business-context en eigen zoekwoorden die voorrang krijgen. Onderwerpen die je uitsluit, blijven buiten de deur. Hoe beter de context, hoe scherper de output — denk aan USP\'s, regio en no-go-onderwerpen.',
            ],
            [
                'id' => 'step-15-apexflow-schedule', 'slug' => 'ai-seo-automation',
                'selector' => '#posts_per_week', 'section' => 'ApexFlow', 'substep' => '2/3',
                'title' => 'Publicatie-instellingen',
                'what' => 'Bepaal ritme en veiligheid: posts per week (begrensd door je licentie), publicatiedagen en -tijd, en of posts direct publiceren of eerst als concept landen.',
                'how' => [
                    'Stel posts/week in (1-7, begrensd door je licentie).',
                    'Vink de publicatiedagen en kies een tijdstip.',
                    'Publish mode: "Auto-publish" of "Create drafts for review" — veilig starten, later opschalen.',
                    'Kies posttype, categorie, auteur, woordenaantal en eventueel AI-uitgelichte afbeeldingen + Brand Voice.',
                ],
                'tip' => 'Adviseer klanten eerst in draft-modus: één week reviewen bouwt vertrouwen voordat je volledig automatisch gaat.',
                'audio' => $a('step-15-apexflow-schedule'),
                'voiceover' => 'Hier bepaal je ritme en veiligheid: het aantal posts per week — begrensd door de licentie — de publicatiedagen, het tijdstip en de modus. Auto-publish publiceert direct; draft-modus legt alles eerst in concepten ter review. Onze tip: start een week in draft-modus. Dat bouwt vertrouwen, daarna kan de autopilot volledig.',
            ],
            [
                'id' => 'step-16-apexflow-pool', 'slug' => 'ai-seo-automation',
                'selector' => '.sseo-apexflow-page .form-table', 'section' => 'ApexFlow', 'substep' => '3/3',
                'title' => 'Keyword pool & geplande posts',
                'what' => 'ApexFlow bouwt een keyword pool van max. 20 kandidaten uit SERP-data, volumes en lokale intentie — en vertaalt die naar geplande posts op vrije kalenderslots.',
                'how' => [
                    '"Preview Calendar" toont een droge run zonder iets te plannen.',
                    'Wees een keyword af via "Reject" — het verdwijnt naar excluded topics.',
                    '"Refresh Pool Now" herbouwt de pool direct (verbruikt SERP/AI-credits).',
                    '"Run ApexFlow Now" forceert een run buiten het wekelijkse schema.',
                ],
                'tip' => 'Het pinnetjes-icoon bij een keyword betekent lokale intentie — relevant als Local Business-coördinaten zijn ingesteld.',
                'audio' => $a('step-16-apexflow-pool'),
                'voiceover' => 'ApexFlow bouwt een keyword pool van maximaal twintig kandidaten uit SERP-data, volumes en lokale intentie — het pinnetje betekent lokaal zoekgedrag. Met "Preview Calendar" doe je een droge run; met "Reject" schuif je een keyword naar de uitsluitingen. Onderaan staat het plan: welke post, welke dag, welk zoekwoord. "Run ApexFlow Now" forceert meteen een run.',
            ],
            // 7. Rank Tracker
            [
                'id' => 'step-17-rank-national', 'slug' => 'ai-seo-rank-tracker',
                'selector' => '#rank-table', 'section' => 'Rankings & lokaal', 'substep' => '1/2',
                'title' => 'Landelijke posities volgen',
                'what' => 'De rank tracker controleert dagelijks je Google-posities per zoekwoord, land en doel-URL — met positie, verandering, beste positie en provider.',
                'how' => [
                    'Voeg een zoekwoord + doel-URL + land toe via het formulier bovenaan.',
                    'Groene driehoek omhoog = gestegen, rood omlaag = gezakt t.o.v. de vorige check.',
                    'Klik op een zoekwoord voor de 30-daagse positiereeks; "Check All Now" forceert een meting.',
                ],
                'tip' => 'Laat op "airco installeren utrecht" zien hoe positie 7 naar 4 ging — meetbaar bewijs van het traject.',
                'audio' => $a('step-17-rank-national'),
                'voiceover' => 'De Rank Tracker controleert dagelijks je Google-posities per zoekwoord, land en doelpagina. Groen omhoog betekent gestegen, rood omlaag betekent gezakt. Klik op een zoekwoord voor de dertigdaagse positiereeks. "airco installeren utrecht" ging hier van positie zeven naar vier — meetbaar bewijs dat het traject werkt. "Check All Now" forceert een verse meting.',
            ],
            [
                'id' => 'step-18-rank-local', 'slug' => 'ai-seo-rank-tracker',
                'selector' => '.rank-tab[data-tab="rank-local-panel"]', 'section' => 'Rankings & lokaal', 'substep' => '2/2',
                'title' => 'Local SERP & cannibalization',
                'what' => 'Twee extra tabbladen: Local SERP toont je positie in de Google Maps-pack via een geo-grid rond je vestiging; Cannibalization detecteert pagina’s die onderling om hetzelfde zoekwoord strijden.',
                'how' => [
                    'Open "Local SERP": kies zoekwoord, radius en grid (3x3 tot 7x7 afhankelijk van je licentie).',
                    'De grid toont per meetpunt of je bedrijf in de local pack staat — ideaal voor lokale dienstverleners.',
                    'Open "Cannibalization" en klik "Rescan": high-severity groepen fix je door samenvoegen of differentiëren.',
                ],
                'tip' => 'Voor Van Dijk Klimaattechniek is de geo-grid dé eye-opener: zichtbaarheid verschilt echt per stadswijk.',
                'audio' => $a('step-18-rank-local'),
                'voiceover' => 'Twee extra tabbladen. Local SERP meet je positie in de Google Maps-pack via een geo-grid rond de vestiging — per meetpunt zie je of je bedrijf in de local pack staat. Voor een lokale dienstverlener als Van Dijk is dat dé eye-opener: zichtbaarheid verschilt echt per stadswijk. De Cannibalization-tab detecteert pagina\'s die onderling om hetzelfde zoekwoord strijden.',
            ],
            // 8. LLM Tracker
            [
                'id' => 'step-19-llm-config', 'slug' => 'ai-seo-llm-tracker',
                'selector' => '#bv-settings-form', 'section' => 'AI-zichtbaarheid', 'substep' => '1/2',
                'title' => 'Merk & prompts instellen',
                'what' => 'De LLM Tracker vraagt ChatGPT, Perplexity, Gemini en Claude periodiek om aanbevelingen en meet of jouw merk wordt genoemd — de nieuwe vorm van vindbaarheid.',
                'how' => [
                    'Vul merknaam, categorie, productnamen en concurrenten in.',
                    'Pas de zoekopdrachten aan met placeholders zoals {category}, {brand} en {location}.',
                    'Vink de te tracken platforms aan en klik "Save Settings".',
                    'Met "Run Scan Now" start je direct een ronde over alle platforms en queries.',
                ],
                'tip' => 'Gebruik de concurrentenlijst bewust: zo vergelijk je jouw AI-share-of-voice met directe rivals.',
                'audio' => $a('step-19-llm-config'),
                'voiceover' => 'Tot slot: de nieuwe vorm van vindbaarheid. De LLM Tracker vraagt ChatGPT, Perplexity, Gemini en Claude om aanbevelingen en meet of jouw merk wordt genoemd. Vul merknaam, categorie, producten en concurrenten in, pas de zoekopdrachten aan met placeholders en vink de platforms aan. Met "Run Scan Now" start je direct een meetronde.',
            ],
            [
                'id' => 'step-20-llm-results', 'slug' => 'ai-seo-llm-tracker',
                'selector' => '.sseo-ai-content', 'section' => 'AI-zichtbaarheid', 'substep' => '2/2',
                'title' => 'Visibility score & sentiment',
                'what' => 'Na een scan zie je de Visibility Score (in hoeveel procent van de antwoorden wordt je merk genoemd), het aantal vermeldingen, gemiddelde positie, sentiment en een platform- en concurrentievergelijking.',
                'how' => [
                    'Wissel periode (7/30/90 dagen) met de tabs.',
                    'Platform Breakdown toont waar je zwak scoort — richt optimalisatie op dat platform.',
                    'Onderaan staat per vermelding het antwoordfragment, positie en sentiment.',
                ],
                'tip' => 'Koppel dit aan GEO-scans en llms.txt: zo meet én verbeter je AI-vindbaarheid in dezelfde plugin.',
                'audio' => $a('step-20-llm-results'),
                'voiceover' => 'Na een scan zie je de Visibility Score: in hoeveel procent van de AI-antwoorden wordt je merk genoemd. Plus vermeldingen, gemiddelde positie en sentiment — positief of negatief. De platform-breakdown toont waar je zwak scoort, en per vermelding lees je het antwoordfragment. Koppel dit aan de GEO-scan en llms.txt, en je meet én verbetert AI-vindbaarheid in dezelfde plugin.',
            ],
            // 9. Created Posts
            [
                'id' => 'step-21-posts-filters', 'slug' => 'ai-seo-created-posts',
                'selector' => '.posts-mass-filters', 'section' => 'Gemaakte content', 'substep' => '1/2',
                'title' => 'Filters & reviewstatus',
                'what' => 'Alle door AI gemaakte content in één controlepaneel: filter op zoekterm, cluster, woordenaantal, publicatiedata, edit-status, reviewstatus, taal en poststatus.',
                'how' => [
                    'Gebruik de statuspills om bijv. alle "Requires attention"-posts te isoleren.',
                    'Stel het woordenaantalbereik in met de sliders of filter op datumbereiken.',
                    '"All filters" klapt de volledige filterbalk in/uit; per pagina 10-100 resultaten.',
                ],
                'tip' => 'De pillen met telling geven direct een kwaliteitsindicatie van de geautomatiseerde output.',
                'audio' => $a('step-21-posts-filters'),
                'voiceover' => 'Alle door AI gemaakte content komt terecht in dit controlepaneel. Filter op zoekterm, cluster, woordenaantal, datumbereiken en status. De statuspills tonen met tellers hoeveel posts gereed zijn, aandacht vragen of in review zijn. Zo behoudt het team volledige grip op geautomatiseerde output.',
            ],
            [
                'id' => 'step-22-posts-table', 'slug' => 'ai-seo-created-posts',
                'selector' => '#posts-table', 'section' => 'Gemaakte content', 'substep' => '2/2',
                'title' => 'Overzicht & bulk-acties',
                'what' => 'De tabel toont per post: afbeelding, titel, zichtbaarheid, status, mass-edit- en reviewstatus. Selecteer posts voor bulk-acties (status, review, verwijderen) of open details om te bewerken of opnieuw te genereren.',
                'how' => [
                    'Selecteer via de checkboxen en open "Bulk Actions" voor status- of reviewwijzigingen.',
                    'Klik op een post voor details, "View on site" en "Edit Post".',
                    'Regenerate herschrijft een post die niet afdoende is — je hoeft niets handmatig te doen.',
                ],
                'tip' => 'Dit scherm is het bewijs dat AI-content in Fyndable altijd onder menselijke controle blijft.',
                'audio' => $a('step-22-posts-table'),
                'voiceover' => 'En dit is het bewijs: elke post met afbeelding, status, edit- en reviewstatus. Selecteer posts voor bulk-acties — status wijzigen, reviewstatus aanpassen of verwijderen — of open een post om te bewerken of opnieuw te laten genereren. Fyndable schrijft, maar de mens blijft eindverantwoordelijk. Dat is de tour. Vragen? De demo blijft openstaan om zelf te verkennen.',
            ],
        ];

        foreach ($steps as &$step) {
            $step['duration'] = !empty($step['audio']) ? self::audioDuration($step['audio']) : 9000;
        }
        unset($step);

        return $steps;
    }
}
