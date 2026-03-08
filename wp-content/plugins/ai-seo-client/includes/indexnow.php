<?php

namespace AISEOClient;

/**
 * IndexNow Integration
 * 
 * Instantly notifies search engines (Bing, Yandex, Naver) when content
 * is published, updated, or deleted. Uses the IndexNow protocol for
 * near-instant indexing instead of waiting for crawlers.
 * 
 * Comparable to RankMath's Instant Indexing feature.
 */
class IndexNow
{
    private Settings $settings;
    private string $apiKey;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->apiKey = $this->getOrCreateApiKey();
    }

    public function register(): void
    {
        add_action('publish_post', [$this, 'onPublish'], 10, 2);
        add_action('save_post', [$this, 'onUpdate'], 20, 3);
        add_action('delete_post', [$this, 'onDelete']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Serve the API key verification file
        add_action('init', [$this, 'addRewriteRule']);
        add_action('template_redirect', [$this, 'serveKeyFile']);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('aiseoclient/v1', '/indexnow/submit', [
            'methods' => 'POST',
            'callback' => [$this, 'restSubmitUrls'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'urls' => ['type' => 'array', 'required' => true],
            ],
        ]);

        register_rest_route('aiseoclient/v1', '/indexnow/status', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetStatus'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    /**
     * Get or create IndexNow API key.
     */
    private function getOrCreateApiKey(): string
    {
        $key = get_option('aiseo_indexnow_key', '');
        if (!$key) {
            $key = wp_generate_password(32, false, false);
            update_option('aiseo_indexnow_key', $key);
        }
        return $key;
    }

    /**
     * Add rewrite rule for the IndexNow key verification file.
     */
    public function addRewriteRule(): void
    {
        add_rewrite_rule(
            '^' . preg_quote($this->apiKey, '/') . '\.txt$',
            'index.php?aiseo_indexnow_verify=1',
            'top'
        );
        add_filter('query_vars', function (array $vars) {
            $vars[] = 'aiseo_indexnow_verify';
            return $vars;
        });
    }

    /**
     * Serve the key verification file.
     */
    public function serveKeyFile(): void
    {
        if (!get_query_var('aiseo_indexnow_verify')) {
            return;
        }

        header('Content-Type: text/plain; charset=UTF-8');
        echo $this->apiKey;
        exit;
    }

    /**
     * Submit URL(s) to IndexNow endpoints.
     */
    public function submitUrls(array $urls): array
    {
        if (empty($urls)) {
            return ['status' => 'no_urls'];
        }

        $host = parse_url(home_url(), PHP_URL_HOST);
        $keyLocation = home_url('/' . $this->apiKey . '.txt');

        $payload = [
            'host' => $host,
            'key' => $this->apiKey,
            'keyLocation' => $keyLocation,
            'urlList' => array_slice(array_map('esc_url_raw', $urls), 0, 10000),
        ];

        // Submit to multiple IndexNow endpoints
        $endpoints = [
            'https://api.indexnow.org/indexnow',
            'https://www.bing.com/indexnow',
        ];

        $results = [];
        foreach ($endpoints as $endpoint) {
            $response = wp_remote_post($endpoint, [
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'body' => wp_json_encode($payload),
                'timeout' => 10,
            ]);

            $code = wp_remote_retrieve_response_code($response);
            $results[] = [
                'endpoint' => $endpoint,
                'status' => is_wp_error($response) ? 'error' : $code,
                'message' => is_wp_error($response) ? $response->get_error_message() : '',
            ];
        }

        // Log the submission
        $log = get_option('aiseo_indexnow_log', []);
        $log[] = [
            'urls' => array_slice($urls, 0, 5),
            'url_count' => count($urls),
            'results' => $results,
            'submitted_at' => current_time('mysql'),
        ];
        // Keep last 100 entries
        $log = array_slice($log, -100);
        update_option('aiseo_indexnow_log', $log);

        return [
            'submitted' => count($urls),
            'results' => $results,
        ];
    }

    /**
     * Auto-submit on publish.
     */
    public function onPublish(int $postId, \WP_Post $post): void
    {
        if ($post->post_status !== 'publish') {
            return;
        }

        $url = get_permalink($postId);
        if ($url) {
            $this->submitUrls([$url]);
        }
    }

    /**
     * Auto-submit on update.
     */
    public function onUpdate(int $postId, \WP_Post $post, bool $update): void
    {
        if (!$update || $post->post_status !== 'publish') {
            return;
        }

        // Avoid duplicate with onPublish
        if (did_action('publish_post') > 0) {
            return;
        }

        $url = get_permalink($postId);
        if ($url) {
            $this->submitUrls([$url]);
        }
    }

    /**
     * Notify on delete.
     */
    public function onDelete(int $postId): void
    {
        $url = get_permalink($postId);
        if ($url) {
            $this->submitUrls([$url]);
        }
    }

    // REST handlers
    public function restSubmitUrls(\WP_REST_Request $request): array
    {
        $urls = $request->get_param('urls');
        if (!is_array($urls)) {
            return ['error' => 'Invalid URLs'];
        }
        return $this->submitUrls(array_map('esc_url_raw', $urls));
    }

    public function restGetStatus(\WP_REST_Request $request): array
    {
        $log = get_option('aiseo_indexnow_log', []);
        return [
            'api_key' => substr($this->apiKey, 0, 8) . '...',
            'key_url' => home_url('/' . $this->apiKey . '.txt'),
            'total_submissions' => count($log),
            'recent' => array_slice(array_reverse($log), 0, 20),
        ];
    }
}
