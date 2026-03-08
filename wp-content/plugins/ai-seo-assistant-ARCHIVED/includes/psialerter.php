<?php

namespace AISEOAssistant;

class PsiAlerter
{
    private HealthLogger $health;
    private Settings $settings;

    public function __construct(HealthLogger $health, Settings $settings)
    {
        $this->health = $health;
        $this->settings = $settings;
    }

    public function check(string $url, array $metrics): void
    {
        $lcpLimit = $this->settings->lcpLimit();
        $clsLimit = $this->settings->clsLimit();
        $inpLimit = $this->settings->inpLimit();

        $warn = [];
        if (($metrics['lcp'] ?? 0) > $lcpLimit) $warn[] = 'LCP ' . $metrics['lcp'] . 'ms';
        if (($metrics['cls'] ?? 0) > $clsLimit) $warn[] = 'CLS ' . $metrics['cls'];
        if (($metrics['inp'] ?? 0) > $inpLimit) $warn[] = 'INP ' . $metrics['inp'] . 'ms';

        if (!empty($warn)) {
            $this->health->log('psi', $metrics['strategy'] ?? 'mobile', 'warn', $url . ' : ' . implode(', ', $warn));
        }
    }
}
