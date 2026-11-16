<?php

namespace SSEOAISaaS;

/**
 * Email Template Renderer
 *
 * Renders email subject and HTML body from stored templates, replacing
 * placeholders with runtime context and wrapping the body in a layout.
 */
class EmailTemplateRenderer
{
    private EmailTemplateRepository $repository;
    private TenantRepository $tenants;
    private EmailBlockRenderer $blockRenderer;

    public function __construct(EmailTemplateRepository $repository, TenantRepository $tenants)
    {
        $this->repository = $repository;
        $this->tenants = $tenants;
        $this->blockRenderer = new EmailBlockRenderer();
    }

    /**
     * Render a template for a given tenant and context.
     *
     * @return array{subject: string, body: string, is_active: bool}
     */
    public function render(string $templateKey, string $tenantKey, array $context): array
    {
        $template = $this->repository->getTemplate($templateKey);

        if (!$template || (empty($template['body_html']) && empty($template['body_blocks']))) {
            $fallback = $this->renderFallback($templateKey, $tenantKey, $context);
            $fallback['is_active'] = true;
            return $fallback;
        }

        if (empty($template['is_active'])) {
            return [
                'subject' => '',
                'body' => '',
                'is_active' => false,
            ];
        }

        $brand = $this->getBrandData($template);
        $fullContext = array_merge($brand, $this->getGlobalPlaceholders(), $this->getTenantPlaceholders($tenantKey), $context);

        $subject = $this->replacePlaceholders($template['subject'], $fullContext, true);

        // Prefer block-based body when blocks are defined; fall back to raw HTML.
        if (!empty($template['body_blocks'])) {
            $blockHtml = $this->blockRenderer->render($template['body_blocks']);
            $bodyHtml = $this->replacePlaceholders($blockHtml, $fullContext, false);
        } else {
            $bodyHtml = $this->replacePlaceholders($template['body_html'], $fullContext, false);
        }

        $body = $this->wrapLayout($bodyHtml, $template['layout'] ?? 'default', $fullContext);

        return [
            'subject' => $subject,
            'body' => $body,
            'is_active' => true,
        ];
    }

    /**
     * Build the global placeholder values.
     *
     * Global email brand options are always read. White-label settings
     * override them only when white-label is enabled and the field is filled.
     */
    private function getGlobalPlaceholders(): array
    {
        $logoId = (int) get_option('sseo_ai_saas_email_brand_logo', 0);
        $globalLogo = $logoId > 0 ? wp_get_attachment_url($logoId) : '';

        $brand = [
            'company_name' => get_option('sseo_ai_saas_email_brand_company_name', ''),
            'company_address' => get_option('sseo_ai_saas_email_brand_company_address', ''),
            'company_postal_code' => get_option('sseo_ai_saas_email_brand_company_postal_code', ''),
            'company_city' => get_option('sseo_ai_saas_email_brand_company_city', ''),
            'company_country' => get_option('sseo_ai_saas_email_brand_company_country', ''),
            'company_vat' => get_option('sseo_ai_saas_email_brand_company_vat', ''),
            'company_kvk' => get_option('sseo_ai_saas_email_brand_company_kvk', ''),
            'company_iban' => get_option('sseo_ai_saas_email_brand_company_iban', ''),
            'company_email' => get_option('sseo_ai_saas_email_brand_company_email', ''),
            'company_website' => get_option('sseo_ai_saas_email_brand_company_website', ''),
            'white_label_logo' => $globalLogo,
            'primary_color' => get_option('sseo_ai_saas_email_brand_primary_color', '#379fd3'),
            'secondary_color' => get_option('sseo_ai_saas_email_brand_secondary_color', '#8f39ac'),
            'button_color' => get_option('sseo_ai_saas_email_brand_button_color', ''),
            'footer_text' => get_option('sseo_ai_saas_email_brand_footer_text', ''),
            'support_email' => get_option('ai_seo_saas_support_email', '') ?: get_option('admin_email'),
            'support_url' => '',
        ];

        // White-label overrides take precedence when enabled and filled.
        if (get_option('sseo_ai_saas_wl_enabled', false)) {
            $wlCompanyName = get_option('sseo_ai_saas_wl_company_name', '');
            if ($wlCompanyName !== '') {
                $brand['company_name'] = $wlCompanyName;
            }
            $wlSupportEmail = get_option('sseo_ai_saas_wl_support_email', '');
            if ($wlSupportEmail !== '') {
                $brand['support_email'] = $wlSupportEmail;
            }
            $wlSupportUrl = get_option('sseo_ai_saas_wl_support_url', '');
            if ($wlSupportUrl !== '') {
                $brand['support_url'] = $wlSupportUrl;
            }
            $wlLogo = get_option('sseo_ai_saas_wl_company_logo', '');
            if ($wlLogo !== '') {
                $brand['white_label_logo'] = $wlLogo;
            }
            $wlPrimary = get_option('sseo_ai_saas_wl_primary_color', '');
            if ($wlPrimary !== '') {
                $brand['primary_color'] = $wlPrimary;
            }
            $wlSecondary = get_option('sseo_ai_saas_wl_secondary_color', '');
            if ($wlSecondary !== '') {
                $brand['secondary_color'] = $wlSecondary;
            }
        }

        // Fallbacks for company name if nothing is set.
        if ($brand['company_name'] === '') {
            $brand['company_name'] = get_bloginfo('name');
        }

        return array_merge($brand, [
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'admin_email' => get_option('admin_email'),
            'current_date' => date_i18n(get_option('date_format')),
            'year' => date_i18n('Y'),
        ]);
    }

    /**
     * Brand values from the template itself, overriding global brand.
     * Empty template fields fall back to the global brand placeholders.
     */
    private function getBrandData(array $template): array
    {
        return [
            'company_logo' => !empty($template['brand_logo']) ? $template['brand_logo'] : '{{white_label_logo}}',
            'primary_color' => !empty($template['primary_color']) ? $template['primary_color'] : '{{primary_color}}',
            'secondary_color' => !empty($template['secondary_color']) ? $template['secondary_color'] : '{{secondary_color}}',
            'button_color' => !empty($template['button_color']) ? $template['button_color'] : '{{button_color}}',
            'footer_text' => $template['footer_text'] ?? '{{footer_text}}',
        ];
    }

    /**
     * Add tenant-level placeholders.
     */
    private function getTenantPlaceholders(string $tenantKey): array
    {
        $tenant = $tenantKey ? $this->tenants->getTenant($tenantKey) : null;

        if (!$tenant) {
            return [];
        }

        return [
            'tenant_name' => $tenant['name'] ?? '',
            'tenant_email' => $tenant['email'] ?? '',
            'tenant_domain' => $tenant['domain'] ?? '',
            'tenant_key' => $tenant['tenant_key'] ?? '',
            'license_key' => $tenant['license_key'] ?? '',
            'tier' => ucfirst($tenant['tier'] ?? 'starter'),
            'current_tier' => ucfirst($tenant['tier'] ?? 'starter'),
        ];
    }

    /**
     * Wrap the content in the chosen layout.
     */
    private function wrapLayout(string $content, string $layout, array $context): string
    {
        if (!in_array($layout, ['default', 'minimal', 'announcement'], true)) {
            $layoutConfig = $this->repository->getLayoutConfig($layout);
            if ($layoutConfig && !empty($layoutConfig['html'])) {
                return $this->renderCustomLayout($content, $layoutConfig, $context);
            }
            $layout = 'default';
        }

        $primary = esc_attr($context['primary_color'] ?? '#379fd3');
        $secondary = esc_attr($context['secondary_color'] ?? '#8f39ac');
        $button = esc_attr($context['button_color'] ?? $primary);
        $logo = esc_url($context['company_logo'] ?? '');
        $siteName = esc_html($context['site_name'] ?? get_bloginfo('name'));
        $footerText = esc_html($context['footer_text'] ?? '');

        // Huisstijl van de portal: donkere kop met merkverloop-balk, witte kaart op lichtgrijs, Outfit.
        // Geen aanhalingstekens: de string staat in style='...' en style="...".
        $font = 'font-family:Outfit,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;';
        $bar = "<div style='height:4px;line-height:4px;font-size:0;background-color:{$secondary};background-image:linear-gradient(90deg,{$secondary},{$primary});'>&nbsp;</div>";
        $darkHeader = 'background-color:#1f1a33;background-image:linear-gradient(135deg,#2a1533 0%,#132a38 100%);';

        if ($layout === 'minimal') {
            $header = "<div style='padding:22px 40px;background:#ffffff;border-bottom:1px solid #efeef4;'>";
            $header .= $logo ? "<img src='{$logo}' alt='{$siteName}' style='max-height:34px;display:block;'>" : "<p style='margin:0;{$font}color:#1a1430;font-size:18px;font-weight:700;'>{$siteName}</p>";
            $header .= '</div>' . $bar;
        } elseif ($layout === 'announcement') {
            $header = "<div style='{$darkHeader}padding:44px 40px;text-align:center;'>";
            $header .= $logo ? "<img src='{$logo}' alt='{$siteName}' style='max-height:44px;margin:0 auto 16px;display:block;'>" : '';
            $header .= "<h1 style='{$font}color:#ffffff;margin:0;font-size:26px;font-weight:700;letter-spacing:-0.01em;'>{$siteName}</h1>";
            $header .= '</div>' . $bar;
        } else {
            $header = "<div style='{$darkHeader}padding:26px 40px;'>";
            $header .= $logo ? "<img src='{$logo}' alt='{$siteName}' style='max-height:38px;display:block;'>" : "<p style='{$font}color:#ffffff;margin:0;font-size:20px;font-weight:700;letter-spacing:-0.01em;'>{$siteName}</p>";
            $header .= '</div>' . $bar;
        }

        $footerExtra = $footerText ? "<p style='margin:0 0 10px 0;color:#746e82;font-size:13px;'>{$footerText}</p>" : '';

        // Build company details block for the footer.
        $companyBlock = $this->buildCompanyFooterBlock($context);

        $html = "<!DOCTYPE html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'><meta name='color-scheme' content='light'></head>";
        $html .= "<body style='margin:0;padding:0;background:#f4f5f8;{$font}'>";
        $html .= "<table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background:#f4f5f8;'><tr><td style='padding:32px 12px;'>";
        $html .= "<div style='max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #ececf1;border-radius:16px;overflow:hidden;box-shadow:0 10px 24px -8px rgba(20,14,30,0.14);'>";
        $html .= $header;
        $html .= "<div style='padding:36px 40px 32px;{$font}font-size:15px;line-height:1.65;color:#413b52;'>" . $this->styleContent($content, $primary, $secondary, $button, $font) . '</div>';
        $html .= "<div style='padding:22px 40px;background:#f7f6fa;border-top:1px solid #efeef4;text-align:center;{$font}'>";
        $html .= $footerExtra;
        $html .= $companyBlock;
        $html .= "<p style='margin:0;font-size:12px;color:#a39dae;'>" . esc_html($siteName) . ' — ' . __('This is an automated email, please do not reply.', 'sseo-ai-saas') . '</p>';
        $html .= '</div></div></td></tr></table></body></html>';

        // Replace color variables inside the generated HTML, including inside inline styles
        $html = $this->replacePlaceholders($html, $context, false);

        return $html;
    }

    /**
     * Inline styles for the template body. The stored templates use classes (button, license-box, ...) that
     * mail clients would ignore without a stylesheet; they get the portal look here. A style attribute that is
     * already on the element wins (e.g. the red button in payment_failed).
     */
    private function styleContent(string $html, string $primary, string $secondary, string $button, string $font): string
    {
        $classes = [
            'button' => "display:inline-block;padding:13px 26px;margin:8px 0 18px;border-radius:10px;background-color:{$button};background-image:linear-gradient(135deg,{$secondary},{$primary});color:#ffffff;{$font}font-size:14.5px;font-weight:600;line-height:1.2;text-decoration:none;",
            'license-box' => "margin:22px 0;padding:16px 20px;background:#f7f6fa;border:1px solid #edecf2;border-left:4px solid {$secondary};border-radius:12px;",
            'success-box' => 'margin:22px 0;padding:18px 20px;background:#ecfdf5;border:1px solid #d1fae5;border-radius:12px;text-align:center;',
            'label' => 'margin:0 0 6px;font-size:11.5px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#746e82;',
            'value' => 'margin:0;font-family:SFMono-Regular,Menlo,Consolas,monospace;font-size:16px;font-weight:600;color:#1a1430;word-break:break-all;',
            'amount' => "margin:0;{$font}font-size:28px;font-weight:700;color:#1a1430;letter-spacing:-0.01em;",
            'status' => 'margin:4px 0 0;font-size:13px;font-weight:600;color:#047857;',
            'details' => 'width:100%;margin:8px 0 22px;border-collapse:collapse;font-size:14px;',
            'help' => 'margin:6px 0 0;font-size:13px;color:#746e82;',
        ];
        $tags = [
            'h1' => "margin:0 0 14px;{$font}font-size:24px;font-weight:700;line-height:1.3;color:#1a1430;letter-spacing:-0.01em;",
            'h2' => "margin:0 0 14px;{$font}font-size:22px;font-weight:700;line-height:1.3;color:#1a1430;letter-spacing:-0.01em;",
            'h3' => "margin:0 0 10px;{$font}font-size:17px;font-weight:700;line-height:1.35;color:#1a1430;",
            'p' => 'margin:0 0 14px;',
            'a' => "color:{$secondary};font-weight:600;",
            'td' => 'padding:9px 0;border-bottom:1px solid #efeef4;color:#413b52;',
            'hr' => 'border:0;border-top:1px solid #efeef4;margin:24px 0;',
            'ul' => 'margin:0 0 14px;padding-left:20px;',
            'ol' => 'margin:0 0 14px;padding-left:20px;',
        ];

        return (string) preg_replace_callback('/<(h1|h2|h3|p|a|td|hr|ul|ol|div|table|span)\b([^>]*)>/i', function ($m) use ($classes, $tags) {
            $tag = strtolower($m[1]);
            $attrs = $m[2];
            $base = '';
            if (preg_match('/\sclass\s*=\s*([\'"])(.*?)\1/i', $attrs, $c)) {
                foreach (preg_split('/\s+/', trim($c[2])) as $class) {
                    $base .= $classes[$class] ?? '';
                }
            }
            if ($base === '') {
                // Elementen die al eigen opmaak hebben (o.a. uit de blokeditor) blijven zoals ze zijn.
                if (preg_match('/\sstyle\s*=/i', $attrs)) {
                    return $m[0];
                }
                $base = $tags[$tag] ?? '';
            }
            if ($base === '') {
                return $m[0];
            }
            $own = '';
            if (preg_match('/\sstyle\s*=\s*([\'"])(.*?)\1/i', $attrs, $s)) {
                $own = $s[2];
                $attrs = str_replace($s[0], '', $attrs);
            }
            $merged = $this->mergeStyles($base, $own);

            return '<' . $m[1] . $attrs . ' style="' . str_replace('"', "'", $merged) . '">';
        }, $html);
    }

    /** Merge two inline style strings; properties from $own win, and an own "background" replaces the base background. */
    private function mergeStyles(string $base, string $own): string
    {
        $parse = function (string $style): array {
            $out = [];
            foreach (explode(';', $style) as $rule) {
                $parts = explode(':', $rule, 2);
                if (count($parts) === 2 && trim($parts[0]) !== '') {
                    $out[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
            }
            return $out;
        };
        $rules = $parse($base);
        $mine = $parse($own);
        if (isset($mine['background'])) {
            unset($rules['background-color'], $rules['background-image']);
        }
        $rules = array_merge($rules, $mine);
        $css = '';
        foreach ($rules as $property => $value) {
            $css .= $property . ':' . $value . ';';
        }
        return $css;
    }

    /**
     * Build a company details block for the email footer from global brand settings.
     */
    private function buildCompanyFooterBlock(array $context): string
    {
        $name = $context['company_name'] ?? '';
        $address = $context['company_address'] ?? '';
        $postalCode = $context['company_postal_code'] ?? '';
        $city = $context['company_city'] ?? '';
        $country = $context['company_country'] ?? '';
        $vat = $context['company_vat'] ?? '';
        $kvk = $context['company_kvk'] ?? '';
        $iban = $context['company_iban'] ?? '';
        $email = $context['company_email'] ?? '';
        $website = $context['company_website'] ?? '';

        // Only show the block if at least the company name or address is set.
        if ($name === '' && $address === '' && $city === '') {
            return '';
        }

        $lines = [];
        if ($name !== '') {
            $lines[] = '<strong style="color:#374151;">' . esc_html($name) . '</strong>';
        }
        $addressLine = trim($address);
        if ($addressLine !== '') {
            $lines[] = esc_html($addressLine);
        }
        $cityLine = trim(($postalCode . ' ' . $city));
        if (trim($cityLine) !== '') {
            $lines[] = esc_html(trim($cityLine));
        }
        if ($country !== '') {
            $lines[] = esc_html($country);
        }

        $regLines = [];
        if ($vat !== '') {
            $regLines[] = esc_html__('VAT', 'sseo-ai-saas') . ': ' . esc_html($vat);
        }
        if ($kvk !== '') {
            $regLines[] = esc_html__('KvK', 'sseo-ai-saas') . ': ' . esc_html($kvk);
        }
        if ($iban !== '') {
            $regLines[] = esc_html__('IBAN', 'sseo-ai-saas') . ': ' . esc_html($iban);
        }
        if (!empty($regLines)) {
            $lines[] = implode(' &nbsp;|&nbsp; ', $regLines);
        }

        $contactLines = [];
        if ($email !== '') {
            $contactLines[] = '<a href="mailto:' . esc_attr($email) . '" style="color:#6b7280;text-decoration:none;">' . esc_html($email) . '</a>';
        }
        if ($website !== '') {
            $contactLines[] = '<a href="' . esc_url($website) . '" style="color:#6b7280;text-decoration:none;">' . esc_html($website) . '</a>';
        }
        if (!empty($contactLines)) {
            $lines[] = implode(' &nbsp;|&nbsp; ', $contactLines);
        }

        if (empty($lines)) {
            return '';
        }

        return "<div style='margin:0 0 8px 0;font-size:12px;line-height:1.6;color:#6b7280;'>"
            . implode("<br>", $lines)
            . '</div>';
    }

    /**
     * Render a custom layout defined by the user.
     */
    private function renderCustomLayout(string $content, array $layoutConfig, array $context): string
    {
        $safeContext = [
            'site_name' => esc_html($context['site_name'] ?? get_bloginfo('name')),
            'company_logo' => esc_url($context['company_logo'] ?? ''),
            'primary_color' => esc_attr($context['primary_color'] ?? '#379fd3'),
            'secondary_color' => esc_attr($context['secondary_color'] ?? '#8f39ac'),
            'button_color' => esc_attr($context['button_color'] ?? $context['primary_color'] ?? '#379fd3'),
            'footer_text' => esc_html($context['footer_text'] ?? ''),
        ];

        $html = $layoutConfig['html'] ?? '';
        $html = str_replace('{{content}}', $content, $html);
        $html = $this->replacePlaceholders($html, $safeContext, false);

        return $html;
    }

    /**
     * Replace {{placeholder}} tags in the provided text.
     */
    private function replacePlaceholders(string $text, array $context, bool $escapeHtml): string
    {
        return preg_replace_callback('/\{\{([a-z0-9_]+)\}\}/', function ($matches) use ($context, $escapeHtml) {
            $key = $matches[1];
            $value = array_key_exists($key, $context) ? $context[$key] : '';

            if (str_ends_with($key, '_url')) {
                return esc_url($value);
            }

            if (str_ends_with($key, '_html')) {
                // Allow safe HTML subset but strip scripts/event handlers.
                // This prevents XSS even if a placeholder value is influenced
                // by untrusted tenant data.
                return wp_kses_post((string) $value);
            }

            if (str_ends_with($key, '_message')) {
                return nl2br(esc_html($value));
            }

            return esc_html($value);
        }, $text);
    }

    /**
     * Basic fallback when a template is missing.
     */
    private function renderFallback(string $templateKey, string $tenantKey, array $context): array
    {
        return [
            'subject' => sprintf(__('[%s] Notification', 'sseo-ai-saas'), get_bloginfo('name')),
            'body' => '<p>' . __('This is an automated notification.', 'sseo-ai-saas') . '</p>',
        ];
    }
}
