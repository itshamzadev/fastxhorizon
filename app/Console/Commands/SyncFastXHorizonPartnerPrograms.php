<?php

namespace App\Console\Commands;

use App\Models\BusinessSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncFastXHorizonPartnerPrograms extends Command
{
    protected $signature = 'fastxhorizon:sync-partner-programs {--apply : Apply settings; the default is a dry run}';
    protected $description = 'Preview or apply FastXHorizon reseller and affiliate program configuration';

    public function handle(): int
    {
        $programs = config('fastxhorizon.partner_programs');
        if (!is_array($programs) || !isset($programs['reseller'], $programs['affiliate'])) {
            $this->error('Invalid partner program configuration. Expected reseller and affiliate sections in config/fastxhorizon.php.');
            $this->line('If the key exists in source, run: php artisan optimize:clear');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Applying partner-program configuration.' : 'Dry run only; no records will be changed.');
        $this->warn('This command never creates partner accounts, orders, commissions, payouts, or catalog data.');

        foreach ($programs as $program => $settings) {
            foreach ($settings as $key => $value) {
                $type = $program . '_' . $key;
                $encoded = is_array($value)
                    ? json_encode($value, JSON_UNESCAPED_SLASHES)
                    : (is_bool($value) ? (string) (int) $value : (string) $value);
                $this->line(($apply ? 'Syncing ' : 'Would sync ') . $type . ' = ' . $encoded);
                if ($apply) {
                    if (!Schema::hasTable('business_settings')) {
                        $this->error('business_settings table is missing; run migrations before applying partner settings.');
                        return self::FAILURE;
                    }
                    BusinessSetting::updateOrCreate(['type' => $type], ['value' => $encoded, 'updated_at' => now()]);
                }
            }
        }

        $this->line('Admin module permissions: reseller_management, affiliate_management (guarded by the existing Master Admin role and people module).');
        $this->line('No demo reseller, affiliate, customer, seller, order, commission, payout, wallet, or inventory records will be created.');
        return self::SUCCESS;
    }
}
