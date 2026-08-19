<?php

namespace SSEOAIClient;

/**
 * GDPR Privacy Export & Erasure
 *
 * Hooks into WordPress core's privacy tools (Tools → Export Personal Data
 * and Tools → Erase Personal Data) so the plugin is GDPR-compliant.
 */
class PrivacyExport
{
    public function register(): void
    {
        add_filter('wp_privacy_personal_data_exporters', [$this, 'registerExporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'registerEraser']);
    }

    /**
     * Register the plugin's data exporter.
     */
    public function registerExporter(array $exporters): array
    {
        $exporters['sseo-ai-client'] = [
            'exporter_friendly_name' => __('Fyndable SEO Data', 'ai-seo-client'),
            'callback'               => [$this, 'exportUserData'],
        ];

        return $exporters;
    }

    /**
     * Register the plugin's data eraser.
     */
    public function registerEraser(array $erasers): array
    {
        $erasers['sseo-ai-client'] = [
            'eraser_friendly_name' => __('Fyndable SEO Data', 'ai-seo-client'),
            'callback'             => [$this, 'eraseUserData'],
        ];

        return $erasers;
    }

    /**
     * Export all SEO meta data associated with a user's posts.
     */
    public function exportUserData(string $emailAddress, int $page = 1): array
    {
        $user = get_user_by('email', $emailAddress);
        if (!$user) {
            return [
                'data' => [],
                'done' => true,
            ];
        }

        global $wpdb;

        $perPage = 100;
        $offset = ($page - 1) * $perPage;

        $posts = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_author = %d ORDER BY ID ASC LIMIT %d OFFSET %d",
            $user->ID,
            $perPage,
            $offset
        ));

        $data = [];

        $metaKeys = $this->getSeoMetaKeys();

        foreach ($posts as $postId) {
            foreach ($metaKeys as $metaKey) {
                $values = get_post_meta($postId, $metaKey, false);
                if (empty($values)) {
                    continue;
                }

                foreach ($values as $value) {
                    $data[] = [
                        'group_id'    => 'sseo-ai-post-' . $postId,
                        'group_label' => sprintf(
                            __('Fyndable SEO — Post #%d: %s', 'ai-seo-client'),
                            $postId,
                            get_the_title($postId) ?: '(no title)'
                        ),
                        'item_id'     => 'sseo-ai-' . $postId . '-' . $metaKey,
                        'data'        => [
                            [
                                'name'  => $metaKey,
                                'value' => $value,
                            ],
                        ],
                    ];
                }
            }
        }

        $totalPosts = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d",
            $user->ID
        ));

        $done = ($offset + $perPage) >= $totalPosts;

        return [
            'data' => $data,
            'done' => $done,
        ];
    }

    /**
     * Erase all SEO meta data associated with a user's posts.
     */
    public function eraseUserData(string $emailAddress, int $page = 1): array
    {
        $user = get_user_by('email', $emailAddress);
        if (!$user) {
            return [
                'items_removed'  => 0,
                'items_retained' => false,
                'messages'       => [],
                'done'           => true,
            ];
        }

        global $wpdb;

        $perPage = 100;
        $offset = ($page - 1) * $perPage;

        $posts = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_author = %d ORDER BY ID ASC LIMIT %d OFFSET %d",
            $user->ID,
            $perPage,
            $offset
        ));

        $itemsRemoved = 0;
        $metaKeys = $this->getSeoMetaKeys();

        foreach ($posts as $postId) {
            foreach ($metaKeys as $metaKey) {
                $deleted = delete_post_meta($postId, $metaKey);
                if ($deleted) {
                    $itemsRemoved++;
                }
            }
        }

        $totalPosts = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d",
            $user->ID
        ));

        $done = ($offset + $perPage) >= $totalPosts;

        return [
            'items_removed'  => $itemsRemoved,
            'items_retained' => false,
            'messages'       => [],
            'done'           => $done,
        ];
    }

    /**
     * Get all SEO meta keys used by the plugin.
     */
    private function getSeoMetaKeys(): array
    {
        return [
            '_sseo_ai_focus_keyphrase',
            '_sseo_ai_secondary_keyphrases',
            '_sseo_ai_title',
            '_sseo_ai_description',
            '_sseo_ai_score',
            '_sseo_ai_og_title',
            '_sseo_ai_og_description',
            '_sseo_ai_og_image',
            '_sseo_ai_twitter_title',
            '_sseo_ai_twitter_description',
            '_sseo_ai_twitter_image',
            '_sseo_ai_canonical_url',
            '_sseo_ai_schema_type',
            '_sseo_ai_schema_data',
            '_sseo_ai_faq_schema',
            '_sseo_ai_video_schema',
            '_sseo_ai_local_seo',
            '_sseo_ai_robots_meta',
            '_sseo_ai_content_score',
            '_sseo_ai_topic_model',
            '_sseo_ai_content_brief',
            '_sseo_ai_cluster_id',
            '_sseo_ai_eeat_score',
            '_sseo_ai_readability_score',
            '_sseo_ai_lsi_keywords',
            '_sseo_ai_alt_text',
            '_sseo_ai_generated_content',
            '_sseo_ai_content_decay_score',
            '_sseo_ai_ab_test_id',
            '_sseo_ai_brand_visibility',
        ];
    }
}
