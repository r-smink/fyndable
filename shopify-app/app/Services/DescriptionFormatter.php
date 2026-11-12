<?php

namespace App\Services;

/**
 * DescriptionFormatter
 *
 * AI models often answer in light Markdown (**bold**, "1." lists, "## headings")
 * even when asked for plain text. Saving that through nl2br() put literal
 * asterisks and list markers in Shopify descriptions, and from there in the
 * Product JSON-LD. Pure helper:
 *
 * - toHtml(): plain/Markdown text -> safe description HTML (p, br, strong, em,
 *   h2/h3, ul/ol/li). Everything is escaped first; only these tags are created.
 * - plain(): HTML or Markdown -> one line of clean text (schema descriptions).
 */
class DescriptionFormatter
{
    public function toHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return '';
        }

        $html = [];
        $paragraph = [];
        $list = null;   // ['tag' => 'ul'|'ol', 'items' => []]

        $flushParagraph = function () use (&$paragraph, &$html) {
            if ($paragraph !== []) {
                $html[] = '<p>'.implode('<br>', $paragraph).'</p>';
                $paragraph = [];
            }
        };
        $flushList = function () use (&$list, &$html) {
            if ($list !== null) {
                $html[] = "<{$list['tag']}>".implode('', array_map(fn ($i) => "<li>{$i}</li>", $list['items']))."</{$list['tag']}>";
                $list = null;
            }
        };

        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                $flushParagraph();
                $flushList();

                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.+)$/u', $line, $m)) {
                $flushParagraph();
                $flushList();
                $tag = strlen($m[1]) <= 2 ? 'h2' : 'h3';
                $html[] = "<{$tag}>".$this->inline(rtrim($m[2], " #")).'</'.$tag.'>';

                continue;
            }
            if (preg_match('/^(?:([-*+•])|(\d+)[.)])\s+(.+)$/u', $line, $m)) {
                $flushParagraph();
                $tag = $m[1] !== '' ? 'ul' : 'ol';
                if ($list !== null && $list['tag'] !== $tag) {
                    $flushList();
                }
                $list ??= ['tag' => $tag, 'items' => []];
                $list['items'][] = $this->inline($m[3]);

                continue;
            }
            $flushList();
            $paragraph[] = $this->inline($line);
        }
        $flushParagraph();
        $flushList();

        return implode('', $html);
    }

    public function plain(string $value): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/li|\/h[1-6])\b[^>]*>/i', ' ', $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/(\*\*|__)(.+?)\1/u', '$2', $text);
        $text = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '$1', $text);
        $text = preg_replace('/(^|\s)#{1,6}\s+/u', '$1', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** Escape one line, then turn **bold** / __bold__ and *italic* into tags. */
    private function inline(string $line): string
    {
        $line = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $line = preg_replace('/(\*\*|__)(?!\s)(.+?)(?<!\s)\1/u', '<strong>$2</strong>', $line);

        return preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '<em>$1</em>', $line);
    }
}
