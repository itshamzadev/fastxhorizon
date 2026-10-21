<?php

namespace App\Console\Commands;

use App\Models\BusinessSetting;
use App\Models\Currency;
use Illuminate\Console\Command;
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
        }

        if ($apply) {
            clearWebConfigCacheKeys();
            $this->info('FastXHorizon branding sync completed.');
        } else {
            $this->comment('Re-run with --apply after reviewing the allowlist.');
        }

        return self::SUCCESS;
    }
}
