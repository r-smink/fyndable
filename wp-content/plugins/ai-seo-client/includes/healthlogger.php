<?php

namespace SSEOAIClient;

class HealthLogger
{
    private const OPTION = 'aiseoclient_health_log';
    private const LIMIT = 20;
    private ?AlertNotifier $notifier = null;

    public function __construct(?AlertNotifier $notifier = null)
    {
        $this->notifier = $notifier;
    }

    public function log(string $type, string $provider, string $status, string $message): void
    {
        $log = get_option(self::OPTION, []);
        $entry = [
            'type'     => $type,
            'provider' => $provider,
            'status'   => $status,
            'message'  => $message,
            'time'     => current_time('mysql', true),
        ];
        array_unshift($log, $entry);
        $log = array_slice($log, 0, self::LIMIT);
        update_option(self::OPTION, $log, false);

        if ($status === 'error' && $this->notifier) {
            $this->notifier->send($this->notifier->formatMessage($type, $provider, $status, $message));
        }
    }

    /**
     * @return array<int,array{type:string,provider:string,status:string,message:string,time:string}>
     */
    public function latest(int $limit = 10): array
    {
        $log = get_option(self::OPTION, []);
        return array_slice($log, 0, $limit);
    }

    public function all(): array
    {
        return get_option(self::OPTION, []);
    }

    public function clear(): void
    {
        update_option(self::OPTION, [], false);
    }
}
