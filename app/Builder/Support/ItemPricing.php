<?php

namespace App\Builder\Support;

use App\Models\Product;

/**
 * Single source of truth for storefront prices and discounts.
 *
 * Wraps the host's `getProductPriceByType()` helper (which already resolves
 * the highest applicable discount — clearance sale first, then the product's
 * own discount) so every Builder resource — card list, detail page, quick-view,
 * per-variation combination — emits the same numbers. Prices are stored against
 * the USD base and converted to the shopper's active currency via
 * `webCurrencyConverterOnlyDigit()` so the wire values are display-ready. That
 * helper reads the session currency (set by the storefront currency switcher),
 * falling back to the system default when nothing is selected — so the numbers
 * track the navbar currency the same way the symbol does.
 *
 * Pass `$basePrice` to compute pricing for a specific variation row; otherwise
 * the product's `unit_price` is used.
 */
final class ItemPricing
{
    public static function compute(Product $product, ?float $basePrice = null): array
    {
        $base = $basePrice ?? (float) ($product->unit_price ?? 0);

        $discountAmount = (float) getProductPriceByType(
            product: $product,
            type: 'discounted_amount',
            result: 'value',
            price: $base,
        );
        $final = max(0.0, $base - $discountAmount);
        $percentOff = $base > 0 ? (int) round((1 - ($final / $base)) * 100) : 0;

        $hasClearance = !empty($product->clearanceSale) || !empty($product['clearance_sale'] ?? null);
        $source = $hasClearance
            ? 'clearance_sale'
            : (((float) ($product->discount ?? 0)) > 0 ? 'product_discount' : null);

        return [
            'price'           => webCurrencyConverterOnlyDigit(amount: $final),
            'oldPrice'        => webCurrencyConverterOnlyDigit(amount: $base),
            'discountAmount'  => webCurrencyConverterOnlyDigit(amount: $discountAmount),
            'discountPercent' => $percentOff,
            'discountType'    => getProductPriceByType(product: $product, type: 'discount_type'),
            'discountSource'  => $source, // 'clearance_sale' | 'product_discount' | null
            'isClearance'     => $hasClearance,
        ];
    }
}
