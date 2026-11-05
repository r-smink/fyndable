<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * AiCrawlers (2.2)
 *
 * Shows which AI crawlers may read the store, from the live /robots.txt.
 * The app does not change robots.txt: that is only possible in theme code,
 * and App Store requirement 5.1.1 does not allow apps to ask merchants to
 * edit their theme. Shopify's default robots.txt allows all of these bots.
 */
class AiCrawlers
{
    /**
     * user agent => [company, purpose]. Purpose: search (answers in AI search
     * and assistants), user (fetches a page when a user asks for it),
     * training (model training).
     */
    public const BOTS = [
        'OAI-SearchBot' => ['OpenAI', 'search'],
        'ChatGPT-User' => ['OpenAI', 'user'],
        'GPTBot' => ['OpenAI', 'training'],
        'Claude-SearchBot' => ['Anthropic', 'search'],
        'Claude-User' => ['Anthropic', 'user'],
        'ClaudeBot' => ['Anthropic', 'training'],
        'PerplexityBot' => ['Perplexity', 'search'],
        'Perplexity-User' => ['Perplexity', 'user'],
        'Google-Extended' => ['Google', 'training'],
        'Applebot-Extended' => ['Apple', 'training'],
        'meta-externalagent' => ['Meta', 'training'],
        'Amazonbot' => ['Amazon', 'training'],
        'CCBot' => ['Common Crawl', 'training'],
        'Bytespider' => ['ByteDance', 'training'],
    ];

    /**
     * Fetch the live robots.txt and work out which AI bots may crawl.
     *
     * @return array{url:string, bots:array<string,string>}|array{error:string}
     */
    public function liveStatus(string $storefrontUrl): array
    {
        $host = parse_url($storefrontUrl, PHP_URL_HOST);
        if (! $host) {
            return ['error' => 'no_storefront'];
        }
        $url = 'https://'.$host.'/robots.txt';

        try {
            $response = Http::timeout(10)->withHeaders(['User-Agent' => 'FyndableBot/1.0 (+https://fyndable.ai)'])->get($url);
        } catch (ConnectionException) {
            return ['error' => 'unreachable'];
        }
        if (! $response->successful() || ! str_contains(strtolower($response->body()), 'user-agent')) {
            return ['error' => 'no_robots_txt'];
        }

        $groups = $this->parse($response->body());
        $bots = [];
        foreach (array_keys(self::BOTS) as $bot) {
            $bots[$bot] = $this->isBlocked($groups, $bot) ? 'block' : 'allow';
        }

        return ['url' => $url, 'bots' => $bots];
    }

    /**
     * robots.txt groups: [[agents[], rules[[field, value]]], ...]
     */
    public function parse(string $robots): array
    {
        $groups = [];
        $current = null;
        $lastWasAgent = false;
        foreach (preg_split('/\r\n|\r|\n/', $robots) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);
            if ($field === 'user-agent') {
                if (! $lastWasAgent || $current === null) {
                    $groups[] = ['agents' => [], 'rules' => []];
                    $current = count($groups) - 1;
                }
                $groups[$current]['agents'][] = strtolower($value);
                $lastWasAgent = true;
            } elseif (in_array($field, ['allow', 'disallow'], true) && $current !== null) {
                $groups[$current]['rules'][] = [$field, $value];
                $lastWasAgent = false;
            } else {
                $lastWasAgent = false;
            }
        }

        return $groups;
    }

    /**
     * Whole site disallowed for this bot (its own group, else the * group).
     */
    public function isBlocked(array $groups, string $bot): bool
    {
        $bot = strtolower($bot);
        $rules = null;
        foreach ($groups as $group) {
            if (in_array($bot, $group['agents'], true)) {
                $rules = array_merge($rules ?? [], $group['rules']);
            }
        }
        if ($rules === null) {
            foreach ($groups as $group) {
                if (in_array('*', $group['agents'], true)) {
                    $rules = array_merge($rules ?? [], $group['rules']);
                }
            }
        }

        $disallowAll = false;
        foreach ($rules ?? [] as [$field, $value]) {
            if ($field === 'allow' && $value === '/') {
                return false;
            }
            if ($field === 'disallow' && $value === '/') {
                $disallowAll = true;
            }
        }

        return $disallowAll;
    }
}
