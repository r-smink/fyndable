<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\StoreSchemaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * SchemaController (1.4 store-wide structured data)
 *
 * GET  /api/schema              - settings, plan access, theme check, publish status
 * POST /api/schema              - save settings and publish them to the app embed
 * POST /api/schema/theme-check  - scan the main theme for its own JSON-LD
 */
class SchemaController extends Controller
{
    public function __construct(private StoreSchemaService $schema) {}

    public function show(Request $request): array
    {
        $shop = $this->shop($request);
        $record = $this->schema->record($shop);

        return [
            'success' => true,
            'settings' => $this->schema->settings($shop),
            'allowed' => $this->schema->allowed($shop),
            'business_types' => StoreSchemaService::BUSINESS_TYPES,
            'theme_check' => $record?->theme_check,
            'theme_checked_at' => $record?->theme_checked_at?->toIso8601String(),
            'published_at' => $record?->published_at?->toIso8601String(),
            'publish_error' => $record?->publish_error,
        ];
    }

    public function save(Request $request): array
    {
        $shop = $this->shop($request);
        $validator = validator($request->all(), [
            'flags' => ['required', 'array'],
            'flags.*' => ['boolean'],
            'organization' => ['required', 'array'],
            'organization.name' => ['nullable', 'string', 'max:255'],
            'organization.logo' => ['nullable', 'url:https', 'max:500'],
            'organization.description' => ['nullable', 'string', 'max:1000'],
            'organization.email' => ['nullable', 'email', 'max:255'],
            'organization.phone' => ['nullable', 'string', 'max:50'],
            'organization.same_as' => ['nullable', 'array', 'max:10'],
            'organization.same_as.*' => ['url:https', 'max:500'],
            'local_business' => ['required', 'array'],
            'local_business.type' => ['nullable', Rule::in(StoreSchemaService::BUSINESS_TYPES)],
            'local_business.street' => ['nullable', 'string', 'max:255'],
            'local_business.city' => ['nullable', 'string', 'max:120'],
            'local_business.postal_code' => ['nullable', 'string', 'max:20'],
            'local_business.region' => ['nullable', 'string', 'max:120'],
            'local_business.country' => ['nullable', 'string', 'regex:/^[A-Za-z]{2}$/'],
            'local_business.phone' => ['nullable', 'string', 'max:50'],
            'local_business.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'local_business.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'local_business.opening_hours' => ['nullable', 'array', 'max:14'],
            'local_business.opening_hours.*' => ['string', 'regex:/^(Mo|Tu|We|Th|Fr|Sa|Su)(-(Mo|Tu|We|Th|Fr|Sa|Su))?(,(Mo|Tu|We|Th|Fr|Sa|Su))* \d{2}:\d{2}-\d{2}:\d{2}$/'],
            'local_business.price_range' => ['nullable', 'string', 'max:20'],
        ]);
        if ($validator->fails()) {
            return ['error' => 'invalid_settings', 'fields' => $validator->errors()->keys()];
        }

        $data = $validator->validated();
        $defaults = $this->schema->defaults();
        $settings = [
            'flags' => array_map('boolval', array_intersect_key($data['flags'] + $defaults['flags'], $defaults['flags'])),
            'organization' => array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_intersect_key(($data['organization'] ?? []) + $defaults['organization'], $defaults['organization'])),
            'local_business' => array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_intersect_key(($data['local_business'] ?? []) + $defaults['local_business'], $defaults['local_business'])),
        ];
        $settings['organization']['same_as'] = array_values($settings['organization']['same_as'] ?? []);
        $settings['local_business']['opening_hours'] = array_values($settings['local_business']['opening_hours'] ?? []);
        $settings['local_business']['country'] = strtoupper((string) $settings['local_business']['country']);

        $this->schema->save($shop, $settings);
        $error = $this->schema->publish($shop);

        return $error === null
            ? ['success' => true, 'settings' => $this->schema->settings($shop)]
            : ['error' => 'publish_failed', 'message' => $error];
    }

    public function themeCheck(Request $request): array
    {
        $result = $this->schema->themeCheck($this->shop($request));

        return isset($result['error']) ? $result : ['success' => true, 'theme_check' => $result];
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}
