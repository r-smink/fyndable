<?php

namespace App\Http\Middleware;

use App\Models\ActivityEvent;
use App\Models\Shop;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs what Fyndable changed in the store for the reports (1.7): every
 * successful save/push/publish in the API, recognised by its route.
 */
class RecordActivity
{
    /** Route URI pattern => activity kind. First match wins. */
    private const ROUTES = [
        '#/save-meta$|/push-title$#' => 'meta',
        '#/save-description$|/push-description$#' => 'description',
        '#/save-schema$|/save-faq$#' => 'schema',
        '#/push-alt-text$#' => 'alt_text',
        '#/push-all$#' => 'product',
        '#/apply-link$#' => 'internal_link',
        '#/generate-image$#' => 'image',
        '#^api/blogs/[^/]+/articles$#' => 'article',
        '#^api/calendar/schedule$#' => 'article',
        '#/attributes/save$#' => 'ai_attributes',
        '#^api/translations/save$#' => 'translation',
        '#^api/collection-pages/[^/]+/save$#' => 'collection_page',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('POST') && $response->isSuccessful()) {
            $kind = $this->kind((string) $request->route()?->uri());
            $shop = $request->attributes->get('shop');
            if ($kind && $shop instanceof Shop && $this->succeeded($response)) {
                ActivityEvent::record($shop->id, $kind);
            }
        }

        return $response;
    }

    private function kind(string $uri): ?string
    {
        foreach (self::ROUTES as $pattern => $kind) {
            if (preg_match($pattern, $uri)) {
                return $kind;
            }
        }

        return null;
    }

    private function succeeded(Response $response): bool
    {
        $data = $response instanceof JsonResponse ? $response->getData(true) : json_decode((string) $response->getContent(), true);

        return is_array($data) && ($data['success'] ?? false) === true;
    }
}
