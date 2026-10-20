<?php

namespace App\Builder\Support;

use Modules\Builder\Services\StorefrontContext;

/**
 * Shared default $context array for ItemCardResource calls.
 *
 * Used by ItemProvider and CategoryProvider so currency (and future
 * cart/wishlist hooks) come from one place. Falls back to the host's global
 * currency symbol when no storefront scope has populated the context (e.g. the
 * vendor BuilderSetup preview path).
 */
class CardContext
{
    public static function default(): array
    {
        return [
            'currency' => self::currencySymbol(),
            // 'cart_lookup'     => fn (int $id) => /* hook a cart lookup */,
            // 'wishlist_lookup' => fn (int $id) => /* hook a wishlist lookup */,
        ];
    }

    private static function currencySymbol(): string
    {
        try {
            $symbol = app(StorefrontContext::class)->getCurrencySymbol();
            if ($symbol !== '') {
                return $symbol;
            }
        } catch (\Throwable) {
        }

        return \App\Utils\currency_symbol() ?: '$';
    }
}
