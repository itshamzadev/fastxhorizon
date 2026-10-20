<?php

namespace App\Console\Commands;

use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FastXHorizonUpdateCurrencyRates extends Command
{
    protected $signature = 'fastxhorizon:currency-rates-update {--dry-run : Validate the provider response without saving rates}';
    protected $description = 'Update automatic Fast X Horizon currency rates while preserving fixed rates';

    public function handle(): int
    {
        $url = config('fastxhorizon_currency.rate_provider_url');
        try {
            $response = Http::acceptJson()->connectTimeout(4)->timeout(8)->get($url);
            if (!$response->successful()) {
                $this->error('Currency provider HTTP failure: ' . $response->status());
                return self::FAILURE;
            }
            $rates = $response->json('rates');
            if (!is_array($rates) || !isset($rates['USD'])) {
                $this->error('Currency provider response did not contain a valid rates map.');
                return self::FAILURE;
            }

            $currencies = Currency::query()->where('status', 1)->where(function ($query) {
                $query->whereNull('rate_mode')->orWhere('rate_mode', 'auto');
            })->get();
            $updates = [];
            foreach ($currencies as $currency) {
                $rate = $currency->code === 'USD' ? 1 : ($rates[$currency->code] ?? null);
                if ($rate === null || !is_numeric($rate) || (float) $rate <= 0) {
                    continue;
                }
                $updates[$currency->code] = (float) $rate;
            }
            if (!$updates) {
                $this->error('No enabled automatic currency rates were returned.');
                return self::FAILURE;
            }
            if ($this->option('dry-run')) {
                $this->table(['Currency', 'Provider rate'], collect($updates)->map(fn ($rate, $code) => [$code, $rate])->values()->all());
                return self::SUCCESS;
            }

            DB::transaction(function () use ($updates) {
                foreach ($updates as $code => $rate) {
                    Currency::query()->where('code', $code)->where(function ($query) {
                        $query->whereNull('rate_mode')->orWhere('rate_mode', 'auto');
                    })->update([
                        'exchange_rate' => $rate,
                        'rate_source' => 'open.er-api.com',
                        'rate_updated_at' => now(),
                    ]);
                }
            });
            $this->info('Updated ' . count($updates) . ' automatic currency rates. Fixed rates were preserved.');
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Currency rate update failed; last known rates were preserved.');
            report($exception);
            return self::FAILURE;
        }
    }
}
