<?php

namespace SSEOAISaaS;

/**
 * Email Block Renderer
 *
 * Converts a JSON array of email blocks (stored in the body_blocks column)
 * into email-safe HTML using table-based layout for maximum client
 * compatibility.
 *
 * Supported block types:
 *  - heading:  { type, text, level (h1|h2|h3), align }
 *  - text:     { type, text (HTML allowed), align }
 *  - button:   { type, text, url, align, color }
 *  - image:    { type, src, alt, width, align }
 *  - spacer:   { type, height (px) }
 *  - divider:  { type, color }
 */
class EmailBlockRenderer
{
    /**
     * Render an array of blocks (or a JSON string) to email-safe HTML.
     *
     * @param array|string|null $blocks
     */
    public function render($blocks): string
    {
        if (is_string($blocks)) {
            $blocks = json_decode($blocks, true);
        }
        if (!is_array($blocks) || empty($blocks)) {
            return '';
        }

        $html = '';
        foreach ($blocks as $block) {
            if (!is_array($block) || empty($block['type'])) {
                continue;
            }
            $html .= $this->renderBlock($block);
        }

        return $html;
    }

    private function renderBlock(array $block): string
    {
        switch ($block['type']) {
            case 'heading':
                return $this->renderHeading($block);
            case 'text':
                return $this->renderText($block);
            case 'button':
                return $this->renderButton($block);
            case 'image':
                return $this->renderImage($block);
            case 'spacer':
                return $this->renderSpacer($block);
            case 'divider':
                return $this->renderDivider($block);
            default:
                return '';
        }
    }

    private function alignStyle(string $align): string
    {
        $map = [
            'left' => 'text-align:left;',
            'center' => 'text-align:center;',
            'right' => 'text-align:right;',
        ];
        return $map[$align] ?? 'text-align:left;';
    }

    private function renderHeading(array $block): string
    {
        $level = in_array(($block['level'] ?? 'h2'), ['h1', 'h2', 'h3'], true) ? $block['level'] : 'h2';
        $align = $block['align'] ?? 'left';
        $text = $block['text'] ?? '';
        $sizes = ['h1' => '28px', 'h2' => '22px', 'h3' => '18px'];

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px 0;">'
            . '<tr><td style="' . $this->alignStyle($align) . '">'
            . '<' . $level . ' style="margin:0;font-family:Outfit,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;font-size:' . $sizes[$level] . ';font-weight:700;color:#111827;line-height:1.3;">' . esc_html($text) . '</' . $level . '>'
            . '</td></tr></table>';
    }

    private function renderText(array $block): string
    {
        $align = $block['align'] ?? 'left';
        $text = $block['text'] ?? '';

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px 0;">'
            . '<tr><td style="' . $this->alignStyle($align) . 'font-family:Outfit,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;font-size:15px;line-height:1.6;color:#374151;">'
            . $text
            . '</td></tr></table>';
    }

    private function renderButton(array $block): string
    {
        $align = $block['align'] ?? 'center';
        $text = $block['text'] ?? '';
        $url = $block['url'] ?? '';
        $color = !empty($block['color']) ? $block['color'] : '#379fd3';

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px 0;">'
            . '<tr><td style="' . $this->alignStyle($align) . '">'
            . '<a href="' . esc_url($url) . '" style="display:inline-block;padding:12px 28px;background:' . esc_attr($color) . ';color:#fff;font-family:Outfit,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;font-size:15px;font-weight:600;text-decoration:none;border-radius:6px;">' . esc_html($text) . '</a>'
            . '</td></tr></table>';
    }

    private function renderImage(array $block): string
    {
        $align = $block['align'] ?? 'center';
        $src = $block['src'] ?? '';
        $alt = $block['alt'] ?? '';
        $width = $block['width'] ?? '100%';

        if (empty($src)) {
            return '';
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px 0;">'
            . '<tr><td style="' . $this->alignStyle($align) . '">'
            . '<img src="' . esc_url($src) . '" alt="' . esc_attr($alt) . '" style="max-width:' . esc_attr($width) . ';height:auto;border-radius:6px;">'
            . '</td></tr></table>';
    }

    private function renderSpacer(array $block): string
    {
        $height = max(8, min(80, (int) ($block['height'] ?? 24)));

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0;"><tr><td style="height:' . $height . 'px;line-height:' . $height . 'px;font-size:1px;">&nbsp;</td></tr></table>';
    }

    private function renderDivider(array $block): string
    {
        $color = !empty($block['color']) ? $block['color'] : '#e5e7eb';

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px 0;">'
            . '<tr><td style="border-top:1px solid ' . esc_attr($color) . ';font-size:0;line-height:0;">&nbsp;</td></tr></table>';
    }
}
