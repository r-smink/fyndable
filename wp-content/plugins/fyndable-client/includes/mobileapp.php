<?php

namespace SSEOAIClient;

/**
 * Mobile App PWA Server
 *
 * Serves a Progressive Web App at /sseo-ai-mobile/ that lets end-users
 * manage keywords, topic clusters, content generation, scheduling, and
 * performance tracking from their mobile device.
 *
 * Authentication uses WordPress Application Passwords (Basic auth).
 * All API calls go to the existing sseo-ai/v1 REST endpoints.
 */
class MobileApp
{
    public function register(): void
    {
        add_action('init', [$this, 'addRewriteRules'], 10, 0);
        add_filter('query_vars', [$this, 'addQueryVars']);
        add_action('parse_request', [$this, 'interceptRequest'], 1);
        add_action('template_redirect', [$this, 'maybeServe']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        $this->maybeFlushRewriteRules();
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/mobile/generate-qr', [
            'methods'  => 'POST',
            'callback' => [$this, 'handleGenerateQr'],
            'permission_callback' => [$this, 'checkQrPermission'],
        ]);
    }

    public function checkQrPermission(\WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }

    public function handleGenerateQr(\WP_REST_Request $request): \WP_REST_Response
    {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Not logged in',
            ], 401);
        }

        $appName = 'Fyndable Mobile App';

        try {
            $result = \WP_Application_Passwords::create_new(
                $user->ID,
                $appName
            );

            if (is_wp_error($result)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $result->get_error_message(),
                ], 500);
            }

            $password = $result['password'];
            $uuid = $result['uuid'];

            $payload = [
                'site'   => home_url(),
                'user'   => $user->user_login,
                'pass'   => $password,
                'app'    => $appName,
                'uuid'   => $uuid,
                'ts'     => time(),
            ];

            return new \WP_REST_Response([
                'success'  => true,
                'payload'  => $payload,
                'qr_data'  => wp_json_encode($payload),
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function addRewriteRules(): void
    {
        add_rewrite_rule('^sseo-ai-mobile/?$', 'index.php?aiseo_mobile=1', 'top');
        add_rewrite_rule('^sseo-ai-mobile/manifest\.json$', 'index.php?aiseo_mobile=manifest', 'top');
        add_rewrite_rule('^sseo-ai-mobile/sw\.js$', 'index.php?aiseo_mobile=sw', 'top');
    }

    public function addQueryVars(array $vars): array
    {
        $vars[] = 'aiseo_mobile';
        return $vars;
    }

    public function interceptRequest(\WP $wp): void
    {
        if (!isset($wp->query_vars['aiseo_mobile'])) {
            return;
        }
        $mode = $wp->query_vars['aiseo_mobile'];
        if ($mode === 'manifest') {
            $this->serveManifest();
            exit;
        }
        if ($mode === 'sw') {
            $this->serveServiceWorker();
            exit;
        }
    }

    public function maybeServe(): void
    {
        $val = get_query_var('aiseo_mobile');
        if ($val === '1') {
            $this->serveApp();
            exit;
        }
    }

    private function maybeFlushRewriteRules(): void
    {
        if (get_option('sseo_ai_mobile_rewrite_flushed') !== '1') {
            flush_rewrite_rules();
            update_option('sseo_ai_mobile_rewrite_flushed', '1');
        }
    }

    private function getBrandName(): string
    {
        $whiteLabel = get_option('sseo_ai_white_label', []);
        if (!empty($whiteLabel['company_name'])) {
            return $whiteLabel['company_name'];
        }
        $tier = get_option('sseo_ai_client_license_tier', 'free');
        if ($tier === 'agency') {
            return 'Smart SEO';
        }
        return 'Fyndable';
    }

    private function serveManifest(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        $brandName = $this->getBrandName();
        echo json_encode([
            'name' => $brandName . ' Mobile',
            'short_name' => $brandName,
            'start_url' => home_url('/sseo-ai-mobile/'),
            'display' => 'standalone',
            'background_color' => '#0f172a',
            'theme_color' => '#379fd3',
            'orientation' => 'portrait',
            'icons' => [
                [
                    'src' => SSEO_AI_CLIENT_PLUGIN_URL . 'assets/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                ],
                [
                    'src' => SSEO_AI_CLIENT_PLUGIN_URL . 'assets/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);
    }

    private function serveServiceWorker(): void
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        echo <<<JS
const CACHE = 'sseo-mobile-v1';
self.addEventListener('install', e => { self.skipWaiting(); });
self.addEventListener('activate', e => { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', e => {
  if (e.request.url.includes('/wp-json/')) return;
  e.respondWith(
    caches.open(CACHE).then(cache =>
      cache.match(e.request).then(cached => {
        const fetchPromise = fetch(e.request).then(resp => {
          if (resp.ok) cache.put(e.request, resp.clone());
          return resp;
        }).catch(() => cached);
        return cached || fetchPromise;
      })
    )
  );
});
JS;
    }

    private function serveApp(): void
    {
        $brandName = $this->getBrandName();
        $homeUrl = home_url();
        $restUrl = esc_url_raw(rest_url('sseo-ai/v1'));

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');

        $templateFile = SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/mobile-app-template.html';
        if (!file_exists($templateFile)) {
            echo '<!-- Mobile app template not found -->';
            return;
        }

        $html = file_get_contents($templateFile);
        $html = str_replace(
            ['{{BRAND}}', '{{REST_URL}}', '{{HOME_URL}}'],
            [htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'), htmlspecialchars($restUrl, ENT_QUOTES, 'UTF-8'), htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8')],
            $html
        );
        echo $html;
    }
}
