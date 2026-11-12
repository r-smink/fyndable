<?php

use App\Services\DescriptionFormatter;

it('turns AI markdown into description HTML', function () {
    $text = "Introducing the board.\nIt rides well.\n\n## Why choose it?\n1. **Sleek Design**: clean look\n2. *Durable* build\n\n- Light\n- Fast";

    expect((new DescriptionFormatter)->toHtml($text))->toBe(
        '<p>Introducing the board.<br>It rides well.</p>'
        .'<h2>Why choose it?</h2>'
        .'<ol><li><strong>Sleek Design</strong>: clean look</li><li><em>Durable</em> build</li></ol>'
        .'<ul><li>Light</li><li>Fast</li></ul>'
    );
});

it('escapes HTML in the source text', function () {
    expect((new DescriptionFormatter)->toHtml('5 * 3 < 20 & <script>x</script>'))
        ->toBe('<p>5 * 3 &lt; 20 &amp; &lt;script&gt;x&lt;/script&gt;</p>');
});

it('cleans HTML and leftover markdown for schema descriptions', function () {
    $html = '<p>Why choose it?<br>1. **Sleek Design**: clean &amp; light</p><p>## Specs</p>';

    expect((new DescriptionFormatter)->plain($html))->toBe('Why choose it? 1. Sleek Design: clean & light Specs');
});
