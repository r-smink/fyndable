<?php

namespace App\Http\Controllers;

use App\Jobs\TranslateJob;
use App\Models\Shop;
use App\Services\AiTranslator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * TranslationsController (2.3 AI translations for Markets)
 *
 * GET  /api/translations              - store languages + content types
 * GET  /api/translations/gaps         - ?type=&locale=&page= resources with missing/outdated fields
 * POST /api/translations/preview      - {type, resource_id, locale, all} AI translation (not saved)
 * POST /api/translations/save         - {resource_id, locale, entries[]} register the (edited) translation
 * POST /api/translations/run          - {type, locale, limit} translate all gaps in the background
 * GET  /api/translations/progress     - progress of the background run
 * POST /api/translations/cancel       - stop the run after the current item
 */
class TranslationsController extends Controller
{
    private const PER_PAGE = 25;

    public const MAX_RUN = 250;

    public function __construct(private AiTranslator $translator) {}

    public function index(Request $request): array
    {
        $locales = $this->translator->locales($this->shop($request));

        return isset($locales['error']) ? $locales : ['success' => true, 'types' => array_keys(AiTranslator::TYPES)] + $locales;
    }

    public function gaps(Request $request): array
    {
        $shop = $this->shop($request);
        [$type, $locale, $error] = $this->target($request, $shop);
        if ($error) {
            return $error;
        }

        $gaps = Cache::remember("translation_gaps:{$shop->id}:{$type}:{$locale}", 300, fn () => $this->translator->gaps($shop, $type, $locale));
        if (isset($gaps['error'])) {
            Cache::forget("translation_gaps:{$shop->id}:{$type}:{$locale}");

            return $gaps;
        }

        $page = max(1, (int) $request->query('page', 1));

        return [
            'success' => true,
            'items' => array_slice($gaps, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total' => count($gaps),
            'fields' => array_sum(array_map(fn ($g) => count($g['missing']) + count($g['outdated']), $gaps)),
            'page' => $page,
            'pages' => (int) ceil(count($gaps) / self::PER_PAGE),
        ];
    }

    public function preview(Request $request): array
    {
        $shop = $this->shop($request);
        [$type, $locale, $error, $name] = $this->target($request, $shop);
        if ($error) {
            return $error;
        }
        $resourceId = (string) $request->input('resource_id', '');
        if (! preg_match('#^gid://shopify/[A-Za-z]+/\d+$#', $resourceId)) {
            return ['error' => 'invalid_resource'];
        }

        $result = $this->translator->translate($shop, $type, $resourceId, $locale, $name, $request->boolean('all'));

        return isset($result['error']) ? $result : ['success' => true] + $result;
    }

    public function save(Request $request): array
    {
        $shop = $this->shop($request);
        [, $locale, $error] = $this->target($request, $shop, false);
        if ($error) {
            return $error;
        }
        $resourceId = (string) $request->input('resource_id', '');
        $entries = $request->input('entries');
        if (! preg_match('#^gid://shopify/[A-Za-z]+/\d+$#', $resourceId) || ! is_array($entries)) {
            return ['error' => 'invalid_request'];
        }

        $error = $this->translator->register($shop, $resourceId, $locale, $entries);
        Cache::forget("translation_gaps:{$shop->id}:".$request->input('type', '').":{$locale}");

        return $error === null ? ['success' => true] : ['error' => 'save_failed', 'message' => $error];
    }

    public function run(Request $request): array
    {
        $shop = $this->shop($request);
        [$type, $locale, $error, $name] = $this->target($request, $shop);
        if ($error) {
            return $error;
        }

        $key = TranslateJob::cacheKey($shop->id);
        if (in_array(Cache::get($key)['status'] ?? null, ['queued', 'running'], true)) {
            return ['error' => 'job_running'];
        }

        $gaps = $this->translator->gaps($shop, $type, $locale);
        if (isset($gaps['error'])) {
            return $gaps;
        }
        $limit = max(1, min((int) $request->input('limit', 50), self::MAX_RUN));
        $resources = array_map(fn ($g) => ['resource_id' => $g['resource_id'], 'title' => $g['title']], array_slice($gaps, 0, $limit));
        if ($resources === []) {
            return ['error' => 'no_gaps'];
        }

        Cache::put($key, ['status' => 'queued', 'type' => $type, 'locale' => $locale, 'total' => count($resources), 'processed' => 0, 'succeeded' => 0, 'failed' => 0, 'fields' => 0, 'errors' => []], now()->addDay());
        Cache::forget("translation_gaps:{$shop->id}:{$type}:{$locale}");
        TranslateJob::dispatch($shop->id, $type, $locale, $name, $resources);

        return ['success' => true, 'total' => count($resources)];
    }

    public function progress(Request $request): array
    {
        return Cache::get(TranslateJob::cacheKey($this->shop($request)->id)) ?: ['status' => 'idle'];
    }

    public function cancel(Request $request): array
    {
        $key = TranslateJob::cacheKey($this->shop($request)->id);
        $progress = Cache::get($key);
        if (! $progress || ! in_array($progress['status'] ?? null, ['queued', 'running'], true)) {
            return ['error' => 'no_job_running'];
        }
        Cache::put($key, array_merge($progress, ['status' => 'cancelled']), now()->addDay());

        return ['success' => true];
    }

    /**
     * Validate type + locale against the store's languages.
     *
     * @return array{0:?string, 1:?string, 2:?array, 3:?string}
     */
    private function target(Request $request, Shop $shop, bool $needType = true): array
    {
        $type = (string) $request->input('type', 'product');
        $locale = (string) $request->input('locale', '');
        if ($needType && ! isset(AiTranslator::TYPES[$type])) {
            return [null, null, ['error' => 'invalid_type'], null];
        }

        $locales = $this->translator->locales($shop);
        if (isset($locales['error'])) {
            return [null, null, $locales, null];
        }
        foreach ($locales['targets'] as $target) {
            if ($target['locale'] === $locale) {
                return [$type, $locale, null, $target['name']];
            }
        }

        return [null, null, ['error' => 'invalid_locale'], null];
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}
