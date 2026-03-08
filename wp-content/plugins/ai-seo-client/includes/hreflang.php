<?php

namespace AISEOClient;

/**
 * Hreflang / Multi-language SEO
 * 
 * Outputs <link rel="alternate" hreflang="..."> tags for multilingual sites.
 * Supports WPML, Polylang, TranslatePress, and manual per-post mappings.
 * Comparable to Yoast Hreflang, RankMath Hreflang.
 */
class Hreflang
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('wp_head', [$this, 'outputHreflangTags'], 2);
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('save_post', [$this, 'saveMeta'], 10, 2);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerSettings(): void
    {
        register_setting('ai_seo_client_settings', 'aiseo_hreflang_default_lang', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('ai_seo_client_settings', 'aiseo_hreflang_mode', ['sanitize_callback' => 'sanitize_text_field']);
    }

    /**
     * Output hreflang tags in <head>
     */
    public function outputHreflangTags(): void
    {
        if (is_admin()) {
            return;
        }

        $alternates = $this->getAlternates();
        if (empty($alternates)) {
            return;
        }

        echo "\n<!-- AI SEO: Hreflang Tags -->\n";
        foreach ($alternates as $lang => $url) {
            printf(
                '<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
                esc_attr($lang),
                esc_url($url)
            );
        }
        echo "<!-- / AI SEO: Hreflang Tags -->\n\n";
    }

    /**
     * Get alternate language URLs for current page
     */
    private function getAlternates(): array
    {
        $mode = get_option('aiseo_hreflang_mode', 'auto');

        // Auto-detect multilingual plugin
        if ($mode === 'auto' || $mode === 'wpml') {
            $wpml = $this->getWpmlAlternates();
            if (!empty($wpml)) return $wpml;
        }

        if ($mode === 'auto' || $mode === 'polylang') {
            $pll = $this->getPolylangAlternates();
            if (!empty($pll)) return $pll;
        }

        if ($mode === 'auto' || $mode === 'translatepress') {
            $tp = $this->getTranslatePressAlternates();
            if (!empty($tp)) return $tp;
        }

        // Manual mode — per-post hreflang mappings
        if (is_singular()) {
            return $this->getManualAlternates();
        }

        return [];
    }

    /**
     * WPML integration
     */
    private function getWpmlAlternates(): array
    {
        if (!function_exists('icl_get_languages')) {
            return [];
        }

        $languages = icl_get_languages('skip_missing=0');
        if (empty($languages)) {
            return [];
        }

        $alternates = [];
        foreach ($languages as $lang) {
            if (!empty($lang['url'])) {
                $code = $lang['language_code'];
                if (!empty($lang['country_flag_url'])) {
                    // Try to get xx-YY format
                    $code = $lang['default_locale'] ?? $code;
                    $code = str_replace('_', '-', strtolower($code));
                }
                $alternates[$code] = $lang['url'];
            }
        }

        // Add x-default (usually the default language)
        $defaultLang = apply_filters('wpml_default_language', '');
        if ($defaultLang && isset($languages[$defaultLang]['url'])) {
            $alternates['x-default'] = $languages[$defaultLang]['url'];
        }

        return $alternates;
    }

    /**
     * Polylang integration
     */
    private function getPolylangAlternates(): array
    {
        if (!function_exists('pll_the_languages')) {
            return [];
        }

        $alternates = [];

        if (is_singular()) {
            global $post;
            if (!$post) return [];

            $languages = pll_languages_list(['fields' => 'locale']);
            foreach ($languages as $locale) {
                $langCode = substr($locale, 0, 2);
                $translationId = pll_get_post($post->ID, $langCode);
                if ($translationId) {
                    $url = get_permalink($translationId);
                    if ($url) {
                        $alternates[str_replace('_', '-', strtolower($locale))] = $url;
                    }
                }
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term && function_exists('pll_get_term')) {
                $languages = pll_languages_list(['fields' => 'locale']);
                foreach ($languages as $locale) {
                    $langCode = substr($locale, 0, 2);
                    $translationId = pll_get_term($term->term_id, $langCode);
                    if ($translationId) {
                        $url = get_term_link($translationId);
                        if (!is_wp_error($url)) {
                            $alternates[str_replace('_', '-', strtolower($locale))] = $url;
                        }
                    }
                }
            }
        }

        // x-default
        if (!empty($alternates) && function_exists('pll_default_language')) {
            $defaultLocale = pll_default_language('locale');
            $defaultKey = str_replace('_', '-', strtolower($defaultLocale));
            if (isset($alternates[$defaultKey])) {
                $alternates['x-default'] = $alternates[$defaultKey];
            }
        }

        return $alternates;
    }

    /**
     * TranslatePress integration
     */
    private function getTranslatePressAlternates(): array
    {
        if (!class_exists('TRP_Translate_Press')) {
            return [];
        }

        $trp = \TRP_Translate_Press::get_trp_instance();
        if (!$trp) return [];

        $urlConverter = $trp->get_component('url_converter');
        if (!$urlConverter) return [];

        $settings = $trp->get_component('settings');
        if (!$settings) return [];

        $trpSettings = $settings->get_settings();
        $languages = $trpSettings['publish-languages'] ?? [];

        if (empty($languages)) return [];

        $currentUrl = $this->getCurrentUrl();
        $alternates = [];

        foreach ($languages as $lang) {
            $url = $urlConverter->get_url_for_language($lang, $currentUrl, '');
            if ($url) {
                $alternates[str_replace('_', '-', strtolower($lang))] = $url;
            }
        }

        // x-default
        $defaultLang = $trpSettings['default-language'] ?? '';
        if ($defaultLang) {
            $defaultKey = str_replace('_', '-', strtolower($defaultLang));
            if (isset($alternates[$defaultKey])) {
                $alternates['x-default'] = $alternates[$defaultKey];
            }
        }

        return $alternates;
    }

    /**
     * Manual per-post hreflang mappings
     */
    private function getManualAlternates(): array
    {
        global $post;
        if (!$post) return [];

        $mappings = get_post_meta($post->ID, '_aiseo_hreflang_map', true);
        if (!is_array($mappings) || empty($mappings)) {
            return [];
        }

        $alternates = [];
        $defaultLang = get_option('aiseo_hreflang_default_lang', get_locale());

        foreach ($mappings as $mapping) {
            if (!empty($mapping['lang']) && !empty($mapping['url'])) {
                $alternates[$mapping['lang']] = $mapping['url'];
            }
        }

        // Add current page as default language
        $currentLangKey = str_replace('_', '-', strtolower($defaultLang));
        if (!isset($alternates[$currentLangKey])) {
            $alternates[$currentLangKey] = get_permalink($post->ID);
        }

        // x-default
        if (isset($alternates[$currentLangKey])) {
            $alternates['x-default'] = $alternates[$currentLangKey];
        }

        return $alternates;
    }

    /**
     * Meta box for manual hreflang mapping
     */
    public function addMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true]);
        foreach ($postTypes as $postType) {
            add_meta_box(
                'aiseo_hreflang',
                __('AI SEO: Hreflang / Language Alternatives', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'normal',
                'low'
            );
        }
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $mappings = get_post_meta($post->ID, '_aiseo_hreflang_map', true);
        if (!is_array($mappings)) {
            $mappings = [];
        }

        wp_nonce_field('aiseo_hreflang_save', 'aiseo_hreflang_nonce');

        $commonLangs = [
            'nl-nl' => 'Nederlands (NL)',
            'nl-be' => 'Nederlands (BE)',
            'en-us' => 'English (US)',
            'en-gb' => 'English (GB)',
            'de-de' => 'Deutsch (DE)',
            'fr-fr' => 'Français (FR)',
            'fr-be' => 'Français (BE)',
            'es-es' => 'Español (ES)',
            'it-it' => 'Italiano (IT)',
            'pt-br' => 'Português (BR)',
            'pl-pl' => 'Polski (PL)',
            'sv-se' => 'Svenska (SE)',
            'da-dk' => 'Dansk (DK)',
            'nb-no' => 'Norsk (NO)',
        ];
        ?>
        <p class="description">
            <?php esc_html_e('Map this page to its translations in other languages. If you use WPML, Polylang, or TranslatePress, this is detected automatically.', 'ai-seo-client'); ?>
        </p>

        <div id="aiseo-hreflang-rows">
            <?php if (empty($mappings)): ?>
                <p style="color:#999;" id="aiseo-hreflang-empty"><?php esc_html_e('No language alternatives set. Click "Add Language" to start.', 'ai-seo-client'); ?></p>
            <?php else: ?>
                <?php foreach ($mappings as $i => $map): ?>
                <div class="aiseo-hreflang-row" style="display:flex;gap:10px;margin-bottom:8px;align-items:center;">
                    <select name="aiseo_hreflang[<?php echo $i; ?>][lang]" style="width:200px;">
                        <?php foreach ($commonLangs as $code => $label): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($map['lang'] ?? '', $code); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="url" name="aiseo_hreflang[<?php echo $i; ?>][url]" value="<?php echo esc_url($map['url'] ?? ''); ?>" placeholder="https://..." style="flex:1;">
                    <button type="button" class="button aiseo-hreflang-remove" style="color:#d63638;">✕</button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <button type="button" class="button" id="aiseo-hreflang-add"><?php esc_html_e('+ Add Language', 'ai-seo-client'); ?></button>

        <script>
        jQuery(document).ready(function($) {
            var idx = <?php echo count($mappings); ?>;
            var langs = <?php echo json_encode($commonLangs); ?>;

            $('#aiseo-hreflang-add').on('click', function() {
                $('#aiseo-hreflang-empty').hide();
                var opts = '';
                $.each(langs, function(code, label) {
                    opts += '<option value="' + code + '">' + label + '</option>';
                });
                var row = '<div class="aiseo-hreflang-row" style="display:flex;gap:10px;margin-bottom:8px;align-items:center;">' +
                    '<select name="aiseo_hreflang[' + idx + '][lang]" style="width:200px;">' + opts + '</select>' +
                    '<input type="url" name="aiseo_hreflang[' + idx + '][url]" placeholder="https://..." style="flex:1;">' +
                    '<button type="button" class="button aiseo-hreflang-remove" style="color:#d63638;">✕</button>' +
                    '</div>';
                $('#aiseo-hreflang-rows').append(row);
                idx++;
            });

            $(document).on('click', '.aiseo-hreflang-remove', function() {
                $(this).closest('.aiseo-hreflang-row').remove();
            });
        });
        </script>
        <?php
    }

    public function saveMeta(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['aiseo_hreflang_nonce']) || !wp_verify_nonce($_POST['aiseo_hreflang_nonce'], 'aiseo_hreflang_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $mappings = [];
        if (isset($_POST['aiseo_hreflang']) && is_array($_POST['aiseo_hreflang'])) {
            foreach ($_POST['aiseo_hreflang'] as $row) {
                $lang = sanitize_text_field($row['lang'] ?? '');
                $url = esc_url_raw($row['url'] ?? '');
                if ($lang && $url) {
                    $mappings[] = ['lang' => $lang, 'url' => $url];
                }
            }
        }

        if (!empty($mappings)) {
            update_post_meta($postId, '_aiseo_hreflang_map', $mappings);
        } else {
            delete_post_meta($postId, '_aiseo_hreflang_map');
        }
    }

    private function getCurrentUrl(): string
    {
        $protocol = is_ssl() ? 'https' : 'http';
        return $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
    }
}
