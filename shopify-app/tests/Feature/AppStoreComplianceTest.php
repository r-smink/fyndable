<?php

/*
 * Static checks for Shopify App Store requirements found in the
 * pre-submission review (Shopify AI Toolkit, app-store-review).
 */

function appSourceFiles(string $dir, string $pattern): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (preg_match($pattern, $file->getFilename())) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

it('uses only the GraphQL Admin API (2.2.4)', function () {
    foreach (appSourceFiles('app', '/\.php$/') as $file) {
        preg_match_all('#/admin/api/[^"\'\s]*?\.json#', file_get_contents($file), $m);
        $rest = array_filter($m[0], fn ($path) => ! str_ends_with($path, '/graphql.json'));
        expect($rest)->toBe([], basename($file).' calls the REST Admin API');
    }
});

it('never asks for the myshopify domain in the UI (2.3.1)', function () {
    foreach (array_merge(appSourceFiles('resources/views', '/\.blade\.php$/'), appSourceFiles('public/js', '/\.js$/')) as $file) {
        expect(file_get_contents($file))->not->toMatch('/<input[^>]+myshopify|placeholder="[^"]*\.myshopify\.com/i', basename($file));
    }
});

it('loads App Bridge as the first script on every app page (2.2.3)', function () {
    $html = $this->get('/dashboard?shop='.TEST_SHOP)->assertOk()->getContent();

    preg_match('/<script[^>]*>/', $html, $first);
    expect($first[0])->toContain('https://cdn.shopify.com/shopifycloud/app-bridge.js');
});
