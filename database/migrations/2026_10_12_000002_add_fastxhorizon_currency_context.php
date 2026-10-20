<?php

use App\Models\BusinessSetting;
use App\Models\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'preferred_currency')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('preferred_currency', 3)->nullable()->after('country')->index());
        }
        foreach (['rate_mode', 'rate_source', 'rate_updated_at', 'fallback_rate'] as $column) {
            if (!Schema::hasColumn('currencies', $column)) {
                Schema::table('currencies', function (Blueprint $table) use ($column) {
                    if ($column === 'rate_updated_at') $table->timestamp($column)->nullable();
                    elseif ($column === 'fallback_rate') $table->decimal($column, 24, 12)->nullable();
                    else $table->string($column, 64)->nullable();
                });
            }
        }
        $currencies = [
            ['name' => 'Pakistani Rupee', 'symbol' => 'PKR', 'code' => 'PKR', 'exchange_rate' => '280', 'rate_mode' => 'fixed', 'fallback_rate' => '280'],
            ['name' => 'United Arab Emirates Dirham', 'symbol' => 'AED', 'code' => 'AED', 'exchange_rate' => '3.6725', 'rate_mode' => 'auto'],
            ['name' => 'British Pound', 'symbol' => '£', 'code' => 'GBP', 'exchange_rate' => '0.79', 'rate_mode' => 'auto'],
            ['name' => 'Saudi Riyal', 'symbol' => 'SAR', 'code' => 'SAR', 'exchange_rate' => '3.75', 'rate_mode' => 'auto'],
        ];
        foreach ($currencies as $currency) {
            Currency::query()->updateOrCreate(['code' => $currency['code']], array_merge($currency, ['status' => 1, 'rate_source' => 'Fast X Horizon fallback', 'rate_updated_at' => now()]));
        }
        Currency::query()->whereIn('code', ['USD', 'EUR', 'INR', 'JPY'])->update(['status' => 1]);
        Currency::query()->where('code', 'USD')->update(['rate_mode' => 'fixed', 'fallback_rate' => 1, 'rate_source' => 'USD reference', 'rate_updated_at' => now()]);
        Currency::query()->where('code', 'EUR')->whereNull('rate_mode')->update(['rate_mode' => 'auto']);
        BusinessSetting::query()->updateOrCreate(['type' => 'currency_model'], ['value' => 'multi_currency']);
        BusinessSetting::query()->updateOrCreate(['type' => 'system_default_currency'], ['value' => (string) Currency::query()->where('code', 'PKR')->value('id')]);
        BusinessSetting::query()->updateOrCreate(['type' => 'fxh_base_currency'], ['value' => 'PKR']);
    }

    public function down(): void
    {
        // Deliberately non-destructive: currency context and rate metadata can be referenced by orders.
    }
};
