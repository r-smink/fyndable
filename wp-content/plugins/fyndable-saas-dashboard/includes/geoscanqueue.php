<?php

namespace SSEOAISaaS;

/**
 * GEO Scan Queue
 *
 * Runs GEO scans asynchronously via WP-Cron so the originating HTTP request
 * (admin-ajax or REST) returns immediately — avoiding gateway 504 timeouts.
 *
 * Flow: insertQueued() → enqueue() → wp-cron runs runJob() → progress is
 * written to the scan row → frontends poll getStatus().
 */
class GeoScanQueue
{
    public const RUN_HOOK = 'sseo_geo_scan_run_job';
    public const SWEEP_HOOK = 'sseo_geo_scan_sweep';

    private const LOCK_TTL = 600;          // 10 min — prevents double processing
    private const STALE_RUNNING = 900;     // running > 15 min = presumed dead
    private const STALE_QUEUED = 120;      // queued > 2 min = cron probably missed it

    private GeoScanner $geoScanner;
    private GeoScanRepository $repository;
    private SaaSSettings $settings;

    public function __construct(GeoScanner $geoScanner, GeoScanRepository $repository, SaaSSettings $settings)
    {
        $this->geoScanner = $geoScanner;
        $this->repository = $repository;
        $this->settings = $settings;
    }

    /**
     * Register cron hooks + custom schedule. Call on init.
     */
    public function register(): void
    {
        add_action(self::RUN_HOOK, [$this, 'runJob'], 10, 1);
        add_action(self::SWEEP_HOOK, [$this, 'sweep']);
        add_filter('cron_schedules', [$this, 'addSchedules']);

        if (!wp_next_scheduled(self::SWEEP_HOOK)) {
            wp_schedule_event(time(), 'every_5_minutes', self::SWEEP_HOOK);
        }
    }

    public function addSchedules(array $schedules): array
    {
        if (!isset($schedules['every_5_minutes'])) {
            $schedules['every_5_minutes'] = [
                'interval' => 300,
                'display'  => __('Every 5 minutes', 'sseo-ai-saas'),
            ];
        }
        return $schedules;
    }

    /**
     * Queue a scan for background processing and kick cron immediately.
     */
    public function enqueue(int $scanId): void
    {
        wp_schedule_single_event(time(), self::RUN_HOOK, [$scanId]);

        // Fire wp-cron non-blocking so processing starts within ~1s instead of
        // waiting for the next organic pageview.
        spawn_cron();
    }

    /**
     * Cron callback: process one queued scan end-to-end.
     */
    public function runJob(int $scanId): void
    {
        $scan = $this->repository->getById($scanId);
        if (!$scan || !in_array($scan['status'], ['queued', 'running'], true)) {
            return;
        }

        // Prevent double processing when cron fires twice.
        $lockKey = 'sseo_geo_lock_' . $scanId;
        if (get_transient($lockKey)) {
            return;
        }
        set_transient($lockKey, time(), self::LOCK_TTL);

        $this->repository->markRunning($scanId);

        // Prefer the original array from meta (keywords may contain commas).
        $keywords = $scan['meta']['keywords'] ?? [];
        if (empty($keywords)) {
            $keywords = array_values(array_filter(array_map('trim', explode(',', (string)($scan['keywords'] ?? '')))));
        }

        $result = $this->geoScanner->scan(
            (string)($scan['url'] ?? ''),
            $keywords,
            (string)($scan['language'] ?? 'auto'),
            null,
            $scanId
        );

        delete_transient($lockKey);

        if (is_wp_error($result)) {
            $this->repository->markFailed($scanId, $result->get_error_message());
            return;
        }

        if (($scan['source'] ?? 'admin') === 'website') {
            $this->notifyNewWebsiteScan($scanId);
        }
    }

    /**
     * Pick up scans that were never processed (cron dead at enqueue time)
     * and fail scans stuck in 'running'.
     */
    public function sweep(): void
    {
        foreach ($this->repository->getStaleQueued(self::STALE_QUEUED) as $id) {
            wp_schedule_single_event(time(), self::RUN_HOOK, [(int)$id]);
        }

        foreach ($this->repository->getStaleRunning(self::STALE_RUNNING) as $id) {
            $this->repository->markFailed((int)$id, __('Scan timed out during processing.', 'sseo-ai-saas'));
        }
    }

    /**
     * Notify the team that a new website (lead) scan completed.
     */
    private function notifyNewWebsiteScan(int $scanId): void
    {
        $scan = $this->repository->getById($scanId);
        if (!$scan) {
            return;
        }

        $to = $this->settings->getSupportEmail();
        if (empty($to)) {
            return;
        }

        $subject = sprintf(
            __('[GEO Scan] Nieuwe website-scan: %s', 'sseo-ai-saas'),
            $scan['url']
        );

        $lines = [
            sprintf(__('Er is een nieuwe GEO-scan aangevraagd via de website.', 'sseo-ai-saas')),
            '',
            'URL: ' . $scan['url'],
            'E-mail: ' . ($scan['email'] ?: '-'),
            'Keywords: ' . $scan['keywords'],
            'Score: ' . (int)($scan['score'] ?? 0) . '/100',
            'Consent: ' . (!empty($scan['consent']) ? 'ja' : 'nee'),
            '',
            __('Bekijk het rapport:', 'sseo-ai-saas') . ' ' . admin_url('admin.php?page=sseo-ai-geo-scan&view=report&scan_id=' . $scanId),
        ];

        wp_mail($to, $subject, implode("\n", $lines));
    }
}
