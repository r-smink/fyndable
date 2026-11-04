<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopProfile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * AiCrawlers (2.2)
 *
 * Allow or block AI crawlers through the theme's robots.txt.liquid. The app
 * has no write_themes access, so it generates the Fyndable block for the
 * merchant to paste, checks the theme file (read_themes) and the live
 * /robots.txt.
 *
 * Allowed bots get no rules at all: a bot-specific group would replace
 * Shopify's default rules for that bot (cart, checkout, admin...). Blocked
 * bots get "Disallow: /".
 */
class AiCrawlers
{
    public const BEGIN = '# Fyndable AI crawlers - begin';

    public const END = '# Fyndable AI crawlers - end';

    public const TEMPLATE = 'templates/robots.txt.liquid';

    /**
     * user agent => [company, purpose]. Purpose: search (answers in AI search
     * and assistants, blocking hides the store there), user (fetches a page
     * when a user asks for it), training (model training).
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
     * Choice per bot; everything allowed by default (maximum AI visibility).
     *
     * @return array<string, string> user agent => allow|block
     */
    public function settings(Shop $shop): array
    {
        $saved = ShopProfile::where('shop_id', $shop->id)->value('ai_crawlers');
        $saved = is_string($saved) ? json_decode($saved, true) : $saved;

        $settings = [];
        foreach (array_keys(self::BOTS) as $bot) {
            $settings[$bot] = ($saved[$bot] ?? 'allow') === 'block' ? 'block' : 'allow';
        }

        return $settings;
    }

    public function save(Shop $shop, array $choices): array
    {
        $settings = [];
        foreach (array_keys(self::BOTS) as $bot) {
            $settings[$bot] = ($choices[$bot] ?? 'allow') === 'block' ? 'block' : 'allow';
        }
        $profile = ShopProfile::firstOrCreate(['shop_id' => $shop->id]);
        $profile->ai_crawlers = $settings;
        $profile->save();

        return $settings;
    }

    /**
     * The Fyndable block (between markers) for robots.txt.liquid.
     */
    public function block(array $settings): string
    {
        $lines = [self::BEGIN, '# Managed in Fyndable Smart SEO. Allowed AI crawlers follow the default rules above.'];
        foreach ($settings as $bot => $choice) {
            if ($choice === 'block') {
                $lines[] = '';
                $lines[] = 'User-agent: '.$bot;
                $lines[] = 'Disallow: /';
            }
        }
        $lines[] = self::END;

        return implode("\n", $lines)."\n";
    }

    /**
     * A complete robots.txt.liquid: Shopify's default output + the block.
     */
    public function fullTemplate(array $settings): string
    {
        return <<<'LIQUID'
{% for group in robots.default_groups %}
  {{- group.user_agent }}

  {%- for rule in group.rules -%}
    {{ rule }}
  {%- endfor -%}

  {%- if group.sitemap != blank -%}
    {{ group.sitemap }}
  {%- endif %}
{% endfor %}

LIQUID.$this->block($settings);
    }

    /**
     * Theme status: does the live theme have robots.txt.liquid, does it
     * contain the Fyndable block and is that block up to date?
     *
     * @return array{exists:bool, has_block:bool, in_sync:bool, theme:string}|array{error:string}
     */
    public function themeStatus(Shop $shop, array $settings, ShopifyContentFetcher $fetcher): array
    {
        $result = $fetcher->getMainThemeFiles($shop, [self::TEMPLATE], 1);
        if (isset($result['error'])) {
            return $result;
        }

        $content = $result['files'][0]['content'] ?? null;
        $current = $content !== null ? $this->extractBlock($content) : null;

        return [
            'theme' => $result['theme'],
            'exists' => $content !== null,
            'has_block' => $current !== null,
            'in_sync' => $current !== null && $this->normalize($current) === $this->normalize($this->block($settings)),
        ];
    }

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

    private function extractBlock(string $content): ?string
    {
        $start = strpos($content, self::BEGIN);
        $end = strpos($content, self::END);
        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        return substr($content, $start, $end - $start + strlen(self::END))."\n";
    }

    private function normalize(string $block): string
    {
        return trim(preg_replace('/[ \t]+/', ' ', str_replace("\r\n", "\n", $block)));
    }
}
