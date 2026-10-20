<?php

namespace App\Builder;

use App\Models\Currency;
use Modules\Builder\Contracts\CurrencyProvider as CurrencyProviderContract;

class CurrencyProvider implements CurrencyProviderContract
{
    public function getCurrencySettings(): array
    {
        return [
            'symbol'   => \App\Utils\currency_symbol() ?? '$',
            'position' => getWebConfig(name: 'currency_symbol_position') ?? 'left',
            'decimals' => (int) (getWebConfig(name: 'decimal_point_settings') ?? 2),
        ];
    }

    public function availableCurrencies(): array
    {
        // Only multi-currency stores expose a switch; single-currency hosts
        // return an empty list so the storefront never renders the control.
        if (getWebConfig(name: 'currency_model') !== 'multi_currency') {
            return [];
        }

        return Currency::where('status', 1)
            ->get(['code', 'symbol', 'name'])
            ->map(fn ($currency) => [
                'code'   => $currency->code,
                'symbol' => $currency->symbol,
                'name'   => $currency->name,
            ])
            ->values()
            ->toArray();
    }

    public function currentCurrencyCode(): ?string
    {
        // Selected currency, falling back to the system default when the
        // shopper hasn't switched yet this session.
        return session('currency_code') ?? getCurrencyCode();
    }

    public function setCurrentCurrency(string $code): void
    {
        $currency = Currency::where('status', 1)->where('code', $code)->first();
        if (!$currency) {
            return;
        }

        // Mirrors Web\CurrencyController::changeCurrency session writes so
        // price rendering picks up the new symbol/rate on the next render.
        session()->put('currency_code', $currency->code);
        session()->put('currency_symbol', $currency->symbol);
        session()->put('currency_exchange_rate', $currency->exchange_rate);
        session()->forget('default');
        session()->forget('usd');
        // Drop the cached checkout quote/state so the page recomputes amounts
        // at the new rate; otherwise the page controller replays digits that
        // were converted at the previous currency's rate.
        session()->forget(['builder.checkout.quote', 'builder.checkout.state']);
    }
}
