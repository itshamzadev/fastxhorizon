<?php

namespace App\Console\Commands;

use App\Models\BusinessSetting;
use App\Models\BusinessPage;
use App\Models\CompanyLeadershipProfile;
use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SyncFastXHorizonBranding extends Command
{
    protected $signature = 'fastxhorizon:sync-branding
                            {--apply : Apply the allowlisted settings and assets}
                            {--skip-assets : Do not copy the allowlisted branding assets}';

    protected $description = 'Preview or apply the allowlisted FastXHorizon production branding sync';

    public function handle(): int
    {
        $configuration = config('fastxhorizon');
        $apply = (bool) $this->option('apply');

        $configurationError = $this->validateConfiguration($configuration);
        if ($configurationError !== null) {
            $this->error($configurationError);
            $this->line('If the source config contains the key, run: php artisan optimize:clear');
            return self::FAILURE;
        }

        $this->info($apply ? 'Applying FastXHorizon presentation sync.' : 'Dry run only; no database or files will be changed.');
        $this->warn('Catalog, sellers, customers, reviews, orders, wallets, coupons, inventory and payment records are never part of this command.');

        $currency = Currency::where('code', $configuration['currency_code'])->first();
        if (!$currency) {
            $this->error('Required presentation currency is missing: ' . $configuration['currency_code']);
            return self::FAILURE;
        }

        $settings = $configuration['settings'];
        $settings['system_default_currency'] = (string) $currency->id;
        $settings = array_merge($settings, $configuration['branding_setting_values']);

        foreach ($settings as $type => $value) {
            $encodedValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (string) $value;
            $this->line(($apply ? 'Syncing ' : 'Would sync ') . $type);

            if ($apply) {
                BusinessSetting::updateOrCreate(
                    ['type' => $type],
                    ['value' => $encodedValue, 'updated_at' => now()]
                );
            }
        }

        if (!$this->option('skip-assets')) {
            foreach ($configuration['branding_assets'] as $target => $sourceName) {
                $source = public_path('assets/fastxhorizon-branding/' . $sourceName);
                if (!is_file($source)) {
                    $this->error('Missing source-controlled branding asset: ' . $sourceName);
                    return self::FAILURE;
                }

                $this->line(($apply ? 'Copying ' : 'Would copy ') . $sourceName . ' -> ' . $target);
                if ($apply && !Storage::disk('public')->put($target, file_get_contents($source))) {
                    $this->error('Unable to write branding asset: ' . $target);
                    return self::FAILURE;
                }
            }

            foreach ($configuration['presentation_assets'] as $relativePath) {
                $source = base_path($relativePath);
                if (!is_file($source)) {
                    $this->error('Missing source-controlled presentation asset: ' . $relativePath);
                    return self::FAILURE;
                }
                $this->line(($apply ? 'Verified ' : 'Would verify ') . $relativePath . ' (source-controlled; preserved at the Blade URL)');
            }
        }

        if (Schema::hasTable('business_pages')) {
            foreach ($configuration['business_page_visuals'] as $slug => $visuals) {
                $page = BusinessPage::query()->where('slug', $slug)->first();
                if (!$page) {
                    $this->warn('Presentation page not found; no record created: ' . $slug);
                    continue;
                }

                $this->line(($apply ? 'Syncing ' : 'Would sync ') . 'business page visuals: ' . $slug);
                if ($apply) {
                    $page->fill($visuals);
                    $page->save();
                }
            }
        }

        if (Schema::hasTable('company_leadership_profiles')) {
            foreach ($configuration['leadership_profiles'] as $profile) {
                $this->line(($apply ? 'Syncing ' : 'Would sync ') . 'leadership profile: ' . $profile['name'] . ' -> ' . $profile['photo']);
                if ($apply) {
                    CompanyLeadershipProfile::updateOrCreate(
                        ['name' => $profile['name']],
                        $profile
                    );
                }
            }
        }

        if ($apply) {
            clearWebConfigCacheKeys();
            $this->info('FastXHorizon branding sync completed.');
        } else {
            $this->comment('Re-run with --apply after reviewing the allowlist.');
        }

        return self::SUCCESS;
    }

    /**
     * Keep the command/config contract explicit. A stale config cache must
     * fail with an actionable message instead of an Undefined array key.
     */
    private function validateConfiguration(mixed $configuration): ?string
    {
        if (!is_array($configuration)) {
            return 'Invalid FastXHorizon sync configuration: config(fastxhorizon) must be an array.';
        }

        $requiredKeys = [
            'settings',
            'currency_code',
            'branding_assets',
            'branding_setting_values',
            'presentation_assets',
            'business_page_visuals',
            'leadership_profiles',
        ];

        $missingKeys = array_values(array_filter(
            $requiredKeys,
            static fn (string $key): bool => !array_key_exists($key, $configuration)
        ));

        if ($missingKeys !== []) {
            return 'Invalid FastXHorizon sync configuration: missing required key(s): ' . implode(', ', $missingKeys) . '.';
        }

        if (!is_array($configuration['branding_assets']) || $configuration['branding_assets'] === []) {
            return 'Invalid FastXHorizon sync configuration: branding_assets must be a non-empty map.';
        }

        if (!is_array($configuration['branding_setting_values']) || $configuration['branding_setting_values'] === []) {
            return 'Invalid FastXHorizon sync configuration: branding_setting_values must be a non-empty map.';
        }

        if (!is_array($configuration['presentation_assets'])) {
            return 'Invalid FastXHorizon sync configuration: presentation_assets must be an array of source paths.';
        }

        if (!is_array($configuration['business_page_visuals'])) {
            return 'Invalid FastXHorizon sync configuration: business_page_visuals must be a map.';
        }

        if (!is_array($configuration['leadership_profiles'])) {
            return 'Invalid FastXHorizon sync configuration: leadership_profiles must be an array.';
        }

        $profileFields = ['name', 'role', 'photo', 'bio', 'portfolio_url', 'display_order', 'is_active'];
        foreach ($configuration['leadership_profiles'] as $index => $profile) {
            if (!is_array($profile)) {
                return "Invalid FastXHorizon sync configuration: leadership_profiles[$index] must be an array.";
            }

            $missingProfileFields = array_values(array_filter(
                $profileFields,
                static fn (string $field): bool => !array_key_exists($field, $profile)
            ));

            if ($missingProfileFields !== []) {
                return "Invalid FastXHorizon sync configuration: leadership_profiles[$index] is missing: " . implode(', ', $missingProfileFields) . '.';
            }
        }

        return null;
    }
}
