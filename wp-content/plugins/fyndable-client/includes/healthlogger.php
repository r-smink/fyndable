<?php

namespace SSEOAIClient;

class HealthLogger
{
    private const OPTION = 'sseo_ai_client_health_log';
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
     * Log an error with a clear, provider-specific message.
     */
    public function logProviderError(string $provider, string $code, string $rawMessage = '', string $type = 'ai'): void
    {
        $message = ($this->notifier)
            ? $this->notifier->explainProviderError($provider, $code, $rawMessage)
            : ($rawMessage ?: $code);

        $this->log($type, $provider, 'error', $message);
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
