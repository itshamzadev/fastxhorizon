<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class FastXHorizonCurrencyService
{
    private ?array $currencies = null;

    public function usesBaseCurrencyStorage(): bool
    {
        return strtoupper((string) getWebConfig('fxh_base_currency')) === strtoupper((string) config('fastxhorizon_currency.base_currency', 'PKR'));
    }

    public function baseCode(): string
    {
        return strtoupper((string) (getWebConfig('fxh_base_currency') ?: config('fastxhorizon_currency.base_currency', 'PKR')));
    }

    public function fallbackCode(): string
    {
        return strtoupper((string) config('fastxhorizon_currency.fallback_currency', 'USD'));
    }

    public function currencies(): array
    {
        return $this->currencies ??= Currency::query()->where('status', 1)->get()->keyBy(fn ($currency) => strtoupper($currency->code))->all();
    }

    public function currency(string|null $code): ?Currency
    {
        return $this->currencies()[strtoupper((string) $code)] ?? null;
    }

    public function rate(string $code): float
    {
        return max(0.0000000001, (float) ($this->currency($code)?->exchange_rate ?? 1));
    }

    public function displayRate(string|null $code = null): float
    {
        $code ??= session('currency_code', $this->baseCode());
        return $this->rate($code) / $this->rate($this->baseCode());
    }

    public function toBase(float|int|null $amount, string|null $fromCode = null): float
    {
        $fromCode ??= session('currency_code', $this->baseCode());
        return (float) $amount * $this->rate($this->baseCode()) / $this->rate($fromCode);
    }

    public function fromBase(float|int|null $amount, string|null $toCode = null): float
    {
        $toCode ??= session('currency_code', $this->baseCode());
        return (float) $amount * $this->rate($toCode) / $this->rate($this->baseCode());
    }

    public function formatCurrencyContext(string $code): void
    {
        $currency = $this->currency($code) ?: $this->currency($this->fallbackCode()) ?: $this->currency($this->baseCode());
        if (!$currency) {
            return;
        }
        session()->put('currency_code', $currency->code);
        session()->put('currency_symbol', $currency->symbol);
        session()->put('currency_exchange_rate', $currency->exchange_rate);
        session()->forget(['default', 'usd', 'builder.checkout.quote', 'builder.checkout.state']);
    }

    public function resolveAndApply(): string
    {
        $selected = session('fxh_currency_manual') === true
            ? $this->validCode(session('currency_code'))
            : (session('fxh_currency_source') === 'auto' ? $this->validCode(session('currency_code')) : null);
        if (session('fxh_currency_manual') === true && $selected) {
            $this->formatCurrencyContext($selected);
            return $selected;
        }

        $user = Auth::guard('customer')->user();
        $source = null;
        if ($user && $this->hasPreferredCurrencyColumn() && $this->validCode($user->preferred_currency)) {
            $selected = $this->validCode($user->preferred_currency);
            $source = 'account';
        }

        if (!$selected && $user) {
            $selected = $this->currencyForUser($user);
            $source = $selected ? 'account-country' : null;
        }

        if (!$selected) {
            $country = session('fxh_visitor_country');
            if (!$country) {
                $country = $this->detectVisitorCountry();
                if ($country) {
                    session()->put('fxh_visitor_country', $country);
                }
            }
            $selected = $this->currencyForCountry($country);
            $source = $selected ? 'ip-country' : null;
        }

        $selected = $this->validCode($selected) ?: $this->validCode($this->fallbackCode()) ?: $this->validCode($this->baseCode());
        $this->formatCurrencyContext($selected);
        session()->put('fxh_currency_source', $source ?: 'fallback');
        return $selected;
    }

    public function setManual(string $code): bool
    {
        $code = $this->validCode($code);
        if (!$code) {
            return false;
        }
        session()->put('fxh_currency_manual', true);
        session()->put('fxh_currency_source', 'manual');
        $this->formatCurrencyContext($code);
        $user = Auth::guard('customer')->user();
        if ($user && $this->hasPreferredCurrencyColumn()) {
            $user->forceFill(['preferred_currency' => $code])->save();
        }
        return true;
    }

    /**
     * Customer guards in this installation can return either the legacy
     * App\\User model or the current App\\Models\\User model.  Currency
     * resolution only needs the shared customer attributes/relations, so do
     * not type-bind this helper to one of those two model classes.
     */
    public function currencyForUser($user): ?string
    {
        $country = $this->normalizeCountry($user->country ?? null);
        $mapped = $this->currencyForCountry($country);
        if ($mapped) {
            return $mapped;
        }
        $addressCountry = null;
        if (method_exists($user, 'addresses')) {
            $addressCountry = $user->addresses()
                ->whereNotNull('country')
                ->where('country', '!=', '')
                ->orderByDesc('is_billing')
                ->value('country');
        }
        $mapped = $this->currencyForCountry($addressCountry);
        if ($mapped) {
            return $mapped;
        }
        $phone = preg_replace('/\D+/', '', (string) $user->phone);
        foreach (array_keys(config('fastxhorizon_currency.phone_country_currency_map', [])) as $prefix) {
            if (str_starts_with($phone, $prefix) && strlen($prefix) >= 2) {
                $phoneCurrency = config("fastxhorizon_currency.phone_country_currency_map.{$prefix}");
                return $this->validCode($phoneCurrency);
            }
        }
        return null;
    }

    public function currencyForCountry(?string $country): ?string
    {
        $country = $this->normalizeCountry($country);
        return $this->validCode(config("fastxhorizon_currency.country_currency_map.{$country}"));
    }

    public function normalizeCountry(?string $country): ?string
    {
        $country = strtoupper(trim((string) $country));
        if (strlen($country) === 2) {
            return $country;
        }
        return match (strtolower($country)) {
            'pakistan' => 'PK', 'united states', 'usa', 'united states of america' => 'US',
            'united kingdom', 'uk', 'england' => 'GB', 'united arab emirates', 'uae' => 'AE',
            'saudi arabia' => 'SA', 'qatar' => 'QA', 'kuwait' => 'KW', 'oman' => 'OM',
            'bahrain' => 'BH', 'canada' => 'CA', 'australia' => 'AU', 'japan' => 'JP',
            'china' => 'CN', 'india' => 'IN', 'turkey', 'türkiye' => 'TR',
            default => null,
        };
    }

    private function validCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));
        return $this->currency($code) ? $code : null;
    }

    private function hasPreferredCurrencyColumn(): bool
    {
        return Schema::hasColumn('users', 'preferred_currency');
    }

    private function detectVisitorCountry(): ?string
    {
        if (config('fastxhorizon_currency.country_override') && app()->environment(['local', 'testing'])) {
            return $this->normalizeCountry(config('fastxhorizon_currency.country_override'));
        }
        $ip = request()->ip();
        if (!$ip || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }
        try {
            $geo = laravelGeoGenius()->geo()->locateVisitor();
            return $this->normalizeCountry($geo['country_code'] ?? $geo['countryCode'] ?? null);
        } catch (\Throwable) {
            return null;
        }
    }
}
