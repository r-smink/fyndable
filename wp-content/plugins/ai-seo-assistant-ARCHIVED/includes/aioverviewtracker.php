<?php

namespace AISEOAssistant;

class AiOverviewTracker
{
    public const CRON_HOOK = 'aiseoassistant_ai_overview';

    private Settings $settings;
    private AiOverviewRepository $repo;
    private SerpService $serp;

    public function __construct(Settings $settings, AiOverviewRepository $repo, SerpService $serp)
    {
        $this->settings = $settings;
        $this->repo = $repo;
        $this->serp = $serp;
    }

    public function scheduled(): void
    {
        $keywords = apply_filters('aiseoassistant_ai_overview_keywords', apply_filters('aiseoassistant_tracked_keywords', []));
        foreach ($keywords as $kw) {
            $result = $this->serp->fetch($kw, true); // prefer scrape for AO detection
            if (!is_wp_error($result)) {
                $flag = !empty($result['ai_overview']);
                $cit = $result['ai_citations'] ?? [];
                $this->repo->save($kw, $result['provider'], $flag, $cit);
            }
        }
    }
}
