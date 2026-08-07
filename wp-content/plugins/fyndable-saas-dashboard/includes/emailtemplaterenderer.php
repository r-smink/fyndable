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
            'support_email' => get_option('ai_seo_saas_support_email', get_option('admin_email')),
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

        $headerGradient = "background:linear-gradient(135deg, {$primary} 0%, {$secondary} 100%);";

        if ($layout === 'minimal') {
            $header = "<div style='padding:24px 40px;text-align:center;background:#fff;border-bottom:1px solid #e5e7eb;'>";
            $header .= $logo ? "<img src='{$logo}' alt='{$siteName}' style='max-height:40px;'>" : "<h1 style='margin:0;color:#111827;font-size:20px;'>{$siteName}</h1>";
            $header .= '</div>';
        } elseif ($layout === 'announcement') {
            $header = "<div style='{$headerGradient}padding:48px 40px;text-align:center;'>";
            $header .= $logo ? "<img src='{$logo}' alt='{$siteName}' style='max-height:48px;margin-bottom:16px;'>" : '';
            $header .= "<h1 style='color:#fff;margin:0;font-size:28px;font-weight:700;'>{$siteName}</h1>";
            $header .= '</div>';
        } else {
            $header = "<div style='{$headerGradient}padding:30px 40px;text-align:center;'>";
            $header .= $logo ? "<img src='{$logo}' alt='{$siteName}' style='max-height:50px;margin-bottom:10px;'>" : '';
            $header .= "<h1 style='color:#fff;margin:0;font-size:24px;font-weight:700;'>{$siteName}</h1>";
            $header .= '</div>';
        }

        $footerExtra = $footerText ? "<p style='margin:0 0 8px 0;color:#6b7280;'>{$footerText}</p>" : '';

        // Build company details block for the footer.
        $companyBlock = $this->buildCompanyFooterBlock($context);

        $html = "<!DOCTYPE html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'></head>";
        $html .= "<body style='margin:0;padding:0;font-family:Outfit,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f3f4f6;'>";
        $html .= "<div style='max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06);'>";
        $html .= $header;
        $html .= '<div style="padding:30px 40px;">' . $content . '</div>';
        $html .= "<div style='padding:20px 40px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;'>";
        $html .= $footerExtra;
        $html .= $companyBlock;
        $html .= "<p style='margin:0;font-size:13px;color:#9ca3af;'>" . esc_html($siteName) . ' — ' . __('This is an automated email, please do not reply.', 'sseo-ai-saas') . '</p>';
        $html .= '</div></div></body></html>';

        // Replace color variables inside the generated HTML, including inside inline styles
        $html = $this->replacePlaceholders($html, $context, false);

        return $html;
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
                return (string) $value;
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
