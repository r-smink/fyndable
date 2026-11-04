<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopProfile;

/**
 * BrandVoice
 *
 * Turns the store profile from the onboarding wizard (content language,
 * target market, brand voice) into instructions for every AI text
 * generator, so product, collection, page and article texts are written in
 * the store's language and tone.
 *
 * Usage: $messages = $voice->apply($shop, $messages);
 * Adds the instructions to the first system message (or prepends one).
 */
class BrandVoice
{
    /** Supported content languages: code => English name for prompts. */
    public const LANGUAGES = [
        'en' => 'English',
        'nl' => 'Dutch',
        'de' => 'German',
        'fr' => 'French',
        'es' => 'Spanish',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'da' => 'Danish',
        'sv' => 'Swedish',
        'no' => 'Norwegian',
        'fi' => 'Finnish',
        'pl' => 'Polish',
    ];

    /** Brand tones: key => prompt description. */
    public const TONES = [
        'professional' => 'professional and trustworthy',
        'friendly' => 'friendly and approachable',
        'playful' => 'playful and energetic',
        'luxury' => 'premium and refined',
        'technical' => 'precise and technical',
        'informative' => 'clear and informative',
    ];

    public function profile(Shop $shop): ?ShopProfile
    {
        return $shop->relationLoaded('profile') ? $shop->profile : $shop->profile()->first();
    }

    /**
     * Content language code for the shop (null = not set).
     */
    public function languageCode(Shop $shop): ?string
    {
        $code = $this->profile($shop)?->content_language;

        return $code !== null && isset(self::LANGUAGES[$code]) ? $code : null;
    }

    /**
     * Prompt instructions from the store profile ('' when nothing is set).
     *
     * @param  bool  $voice  false = language/market only (e.g. alt text)
     */
    public function instructions(Shop $shop, bool $voice = true, bool $market = true): string
    {
        $profile = $this->profile($shop);
        if ($profile === null) {
            return '';
        }

        $lines = [];
        $language = $market ? $this->languageCode($shop) : null;
        if ($language !== null) {
            $lines[] = 'Write all text in '.self::LANGUAGES[$language].'.';
        }
        if ($market && ! empty($profile->target_country)) {
            $lines[] = 'The store sells to customers in country '.strtoupper($profile->target_country).'; use local spelling, terms and units.';
        }

        if ($voice) {
            if (! empty($profile->brand_tone) && isset(self::TONES[$profile->brand_tone])) {
                $lines[] = 'Brand voice: '.self::TONES[$profile->brand_tone].'.';
            }
            if (! empty($profile->brand_audience)) {
                $lines[] = 'Target audience: '.$this->clean($profile->brand_audience, 255).'.';
            }
            if (! empty($profile->brand_description)) {
                $lines[] = 'About the brand: '.$this->clean($profile->brand_description, 600);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Add the store profile instructions to an OpenAI-style message list.
     */
    /**
     * $market = false leaves out the store language and country (AI
     * translations set the target language themselves, 2.3).
     */
    public function apply(Shop $shop, array $messages, bool $voice = true, bool $market = true): array
    {
        $instructions = $this->instructions($shop, $voice, $market);
        if ($instructions === '') {
            return $messages;
        }

        foreach ($messages as $i => $message) {
            if (($message['role'] ?? '') === 'system' && is_string($message['content'] ?? null)) {
                $messages[$i]['content'] = rtrim($message['content'])."\n\n".$instructions;

                return $messages;
            }
        }

        array_unshift($messages, ['role' => 'system', 'content' => $instructions]);

        return $messages;
    }

    /**
     * Merchant input goes into a prompt: single line, length-capped.
     */
    private function clean(string $value, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $value) ?? ''), 0, $max);
    }
}
