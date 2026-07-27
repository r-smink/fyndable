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

    public function __construct(EmailTemplateRepository $repository, TenantRepository $tenants)
    {
        $this->repository = $repository;
        $this->tenants = $tenants;
    }

    /**
     * Render a template for a given tenant and context.
     *
     * @return array{subject: string, body: string}
     */
    public function render(string $templateKey, string $tenantKey, array $context): array
    {
        $template = $this->repository->getTemplate($templateKey);

        if (!$template || empty($template['body_html'])) {
            return $this->renderFallback($templateKey, $tenantKey, $context);
        }

        $brand = $this->getBrandData($template);
        $fullContext = array_merge($brand, $this->getGlobalPlaceholders(), $this->getTenantPlaceholders($tenantKey), $context);

        $subject = $this->replacePlaceholders($template['subject'], $fullContext, true);
        $bodyHtml = $this->replacePlaceholders($template['body_html'], $fullContext, false);
        $body = $this->wrapLayout($bodyHtml, $template['layout'] ?? 'default', $fullContext);

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Build the global placeholder values.
     */
    private function getGlobalPlaceholders(): array
    {
        $whiteLabel = get_option('sseo_ai_saas_wl_enabled', false)
            ? [
                'company_name' => get_option('sseo_ai_saas_wl_company_name', get_bloginfo('name')),
                'support_email' => get_option('sseo_ai_saas_wl_support_email', get_option('admin_email')),
                'support_url' => get_option('sseo_ai_saas_wl_support_url', ''),
                'white_label_logo' => get_option('sseo_ai_saas_wl_company_logo', ''),
                'primary_color' => get_option('sseo_ai_saas_wl_primary_color', '#379fd3'),
                'secondary_color' => get_option('sseo_ai_saas_wl_secondary_color', '#8f39ac'),
            ]
            : [
                'company_name' => 'Fyndable',
                'support_email' => get_option('admin_email'),
                'support_url' => '',
                'white_label_logo' => '',
                'primary_color' => '#379fd3',
                'secondary_color' => '#8f39ac',
            ];

        return array_merge($whiteLabel, [
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'admin_email' => get_option('admin_email'),
            'current_date' => date_i18n(get_option('date_format')),
            'year' => date_i18n('Y'),
        ]);
    }

    /**
     * Brand values from the template itself, overriding white-label/global.
     */
    private function getBrandData(array $template): array
    {
        return [
            'company_logo' => !empty($template['brand_logo']) ? $template['brand_logo'] : '{{white_label_logo}}',
            'primary_color' => !empty($template['primary_color']) ? $template['primary_color'] : '{{primary_color}}',
            'secondary_color' => !empty($template['secondary_color']) ? $template['secondary_color'] : '{{secondary_color}}',
            'button_color' => !empty($template['button_color']) ? $template['button_color'] : '{{primary_color}}',
            'footer_text' => $template['footer_text'] ?? '',
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
            'tier' => ucfirst($tenant['tier'] ?? 'free'),
            'current_tier' => ucfirst($tenant['tier'] ?? 'free'),
        ];
    }

    /**
     * Wrap the content in the chosen layout.
     */
    private function wrapLayout(string $content, string $layout, array $context): string
    {
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

        $html = "<!DOCTYPE html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'></head>";
        $html .= "<body style='margin:0;padding:0;font-family:Outfit,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f3f4f6;'>";
        $html .= "<div style='max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06);'>";
        $html .= $header;
        $html .= "<div style='padding:30px 40px;'>"> . $content . "</div>";
        $html .= "<div style='padding:20px 40px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;'>";
        $html .= $footerExtra;
        $html .= "<p style='margin:0;font-size:13px;color:#9ca3af;'>" . esc_html($siteName) . ' — ' . __('This is an automated email, please do not reply.', 'sseo-ai-saas') . '</p>';
        $html .= '</div></div></body></html>';

        // Replace color variables inside the generated HTML, including inside inline styles
        $html = $this->replacePlaceholders($html, $context, false);

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
