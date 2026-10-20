<?php

namespace App\Builder;

use Modules\Builder\Contracts\LocaleProvider as LocaleProviderContract;

class LocaleProvider implements LocaleProviderContract
{
    public function availableLanguages(): array
    {
        try {
            $languages = getWebConfig(name: 'language');

            if (!\is_array($languages) || empty($languages)) {
                return [self::baselineEnglish()];
            }

            return collect($languages)
                // 6Valley language rows carry no status flag; a row is skipped
                // only when it explicitly opts out.
                ->filter(fn ($language) => !\array_key_exists('status', $language) || $language['status'])
                ->map(fn ($language) => [
                    'code'      => $language['code'] ?? 'en',
                    // Display name + flag come from the host so the storefront
                    // switcher works for 6Valley's non-standard codes (Arabic=sa,
                    // Bangla=bd, Hindi=in, …) instead of falling back to a globe.
                    'name'      => $language['name'] ?? strtoupper((string) ($language['code'] ?? 'en')),
                    'direction' => $language['direction'] ?? 'ltr',
                    'default'   => (bool) ($language['default'] ?? false),
                    'flag'      => self::flagUrl($language['code'] ?? 'en'),
                ])
                ->values()
                ->toArray();
        } catch (\Throwable) {
            return [self::baselineEnglish()];
        }
    }

    private static function baselineEnglish(): array
    {
        return ['code' => 'en', 'name' => 'English', 'direction' => 'ltr', 'default' => true, 'flag' => self::flagUrl('en')];
    }

    /**
     * Flag image URL from the shared front-end asset set (same source the
     * admin/vendor/web headers use). The file name is the 6Valley language
     * code, so a flag exists for every enabled language.
     */
    private static function flagUrl(string $code): string
    {
        return dynamicAsset(path: 'public/assets/front-end/img/flags/' . strtolower($code) . '.png');
    }
}
