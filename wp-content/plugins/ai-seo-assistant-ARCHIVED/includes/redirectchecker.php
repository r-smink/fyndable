<?php

namespace AISEOAssistant;

class RedirectChecker
{
    public function chain(string $url, int $max = 5): array
    {
        $chain = [];
        $current = $url;
        for ($i = 0; $i < $max; $i++) {
            $resp = wp_remote_head($current, ['redirection' => 0, 'timeout' => 10]);
            if (is_wp_error($resp)) {
                $chain[] = ['url' => $current, 'code' => 'error', 'error' => $resp->get_error_message()];
                break;
            }
            $code = wp_remote_retrieve_response_code($resp);
            $chain[] = ['url' => $current, 'code' => $code];
            if ($code >= 300 && $code < 400) {
                $loc = wp_remote_retrieve_header($resp, 'location');
                if (!$loc) break;
                $current = $loc;
                continue;
            }
            break;
        }
        return $chain;
    }
}
