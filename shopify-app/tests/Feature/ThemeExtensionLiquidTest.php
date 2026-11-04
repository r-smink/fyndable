<?php

/*
 * Static checks for the theme app extension, so Liquid syntax errors show
 * up in the test suite instead of at `shopify app deploy`.
 */

function extensionLiquidFiles(): array
{
    return glob(base_path('extensions/*/blocks/*.liquid'));
}

it('has no braces inside Liquid output or tag markup', function () {
    foreach (extensionLiquidFiles() as $file) {
        $liquid = file_get_contents($file);
        // Liquid ends a tag at the first "}}" or "%}", even inside a string.
        preg_match_all('/\{\{(.*?)\}\}|\{%(.*?)%\}/s', $liquid, $tags, PREG_SET_ORDER);
        foreach ($tags as $tag) {
            $inner = $tag[1] !== '' ? $tag[1] : ($tag[2] ?? '');
            expect(str_contains($inner, '{') || str_contains($inner, '}'))->toBeFalse(basename($file).': '.trim($tag[0]));
        }
    }
});

it('balances block tags and has a valid schema in every block', function () {
    foreach (extensionLiquidFiles() as $file) {
        $liquid = file_get_contents($file);
        foreach (['if' => 'endif', 'for' => 'endfor', 'case' => 'endcase', 'capture' => 'endcapture', 'comment' => 'endcomment', 'unless' => 'endunless'] as $open => $close) {
            $opens = preg_match_all('/\{%-?\s*'.$open.'\b/', $liquid);
            $closes = preg_match_all('/\{%-?\s*'.$close.'\b/', $liquid);
            expect($opens)->toBe($closes, basename($file).": {$open}/{$close}");
        }

        expect(preg_match('/\{%\s*schema\s*%\}(.*?)\{%\s*endschema\s*%\}/s', $liquid, $m))->toBe(1, basename($file).': schema');
        $schema = json_decode($m[1], true);
        expect($schema)->toBeArray(basename($file).': schema JSON')
            ->and($schema)->toHaveKeys(['name', 'target']);
    }
});

it('only uses theme-safe targets', function () {
    foreach (extensionLiquidFiles() as $file) {
        preg_match('/\{%\s*schema\s*%\}(.*?)\{%\s*endschema\s*%\}/s', file_get_contents($file), $m);
        expect(json_decode($m[1], true)['target'])->toBeIn(['head', 'body', 'section'], basename($file));
    }
});
