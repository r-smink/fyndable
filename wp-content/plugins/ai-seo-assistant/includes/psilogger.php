<?php

namespace AISEOAssistant;

class PsiLogger
{
    private const OPTION = 'aiseoassistant_psi_log';
    private const LIMIT = 30;

    public function log(string $url, array $metrics): void
    {
        $log = get_option(self::OPTION, []);
        array_unshift($log, [
            'url' => $url,
            'metrics' => $metrics,
            'time' => current_time('mysql', true),
        ]);
        $log = array_slice($log, 0, self::LIMIT);
        update_option(self::OPTION, $log, false);
    }

    public function latest(int $limit = 10): array
    {
        $log = get_option(self::OPTION, []);
        return array_slice($log, 0, $limit);
    }
}
