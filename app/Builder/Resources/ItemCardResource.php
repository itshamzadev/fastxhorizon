<?php

namespace App\Builder\Resources;

use App\Builder\Support\ItemPricing;
use App\Models\Product;
use Modules\Builder\ValueObjects\Storefront\ItemCardDTO;

/**
 * Canonical transformer that maps an App\Models\Product into the exact shape
 * the React storefront's ItemCard consumes.
 *
 * 6Valley is a catalogue e-commerce marketplace, so the food-only axes of the
 * DTO don't apply: `isVeg`/`isNonVeg` are always false and `moduleType` is null.
 * `needsConfig` is true when the product carries catalogue variations (the card
 * must route Add-to-Cart through the choose-variation step) or is a digital
 * product needing variant selection.
 *
 * Accepts a $context array so callers can inject:
 *   - 'currency'         => string symbol (default '$')
 *   - 'cart_lookup'      => callable(int $productId): ?array
 *   - 'wishlist_lookup'  => callable(int $productId): bool
 */
class ItemCardResource
{
    public static function fromCollection(iterable $products, array $context = []): array
    {
        $result = [];
        foreach ($products as $product) {
            $result[] = self::fromOne($product, $context);
        }
        return $result;
    }

    public static function fromOne(Product $product, array $context = []): array
    {
        $pricing = ItemPricing::compute($product);

        $currency       = $context['currency']        ?? '$';
        $cartLookup     = $context['cart_lookup']     ?? null;
        $wishlistLookup = $context['wishlist_lookup'] ?? null;

        $cartEntry = is_callable($cartLookup) ? $cartLookup($product->id) : null;

        $data = [
            'id'              => $product->id,
            'name'            => $product->name,
            'slug'            => $product->slug ?? null,
            'image'           => getStorageImages(path: $product->thumbnail_full_url, type: 'product'),
            'price'           => $pricing['price'],
            'oldPrice'        => $pricing['oldPrice'],
            'discountAmount'  => $pricing['discountAmount'],
            'discountPercent' => $pricing['discountPercent'],
            'discountType'    => $pricing['discountType'],
            'discountSource'  => $pricing['discountSource'],
            'rating'          => round((float) ($product->reviews_avg_rating ?? 0), 1),
            'ratingCount'     => (int) ($product->reviews_count ?? 0),
            'currency'        => $currency,
            'isVeg'           => false,
            'isNonVeg'        => false,
            'inCart'          => $cartEntry !== null,
            'cartQty'         => (int) ($cartEntry['qty'] ?? 0),
            'isWishlist'      => is_callable($wishlistLookup) ? (bool) $wishlistLookup($product->id) : false,
            'moduleType'      => null,
            'needsConfig'     => self::needsConfig($product),
        ];

        return ItemCardDTO::fromArray($data)->toArray();
    }

    /**
     * A product needs a pre-cart configuration step when it exposes catalogue
     * variations (color/choice options) or digital-product variations.
     */
    private static function needsConfig(Product $product): bool
    {
        $variations = self::decodeJsonField($product->getAttributes()['variation'] ?? null);
        if (\count($variations) > 0) {
            return true;
        }

        $choiceOptions = self::decodeJsonField($product->getAttributes()['choice_options'] ?? null);
        if (\count($choiceOptions) > 0) {
            return true;
        }

        // Digital variations live in a separate table; their axes are recorded on
        // the product as `digital_product_extensions` ({type: [options]}). The
        // previous check read `digital_product_type` (a scalar like
        // 'ready_product' → decodes to []), so it never flagged digital variants.
        return ($product->product_type ?? null) === 'digital'
            && \count(self::decodeJsonField($product->getAttributes()['digital_product_extensions'] ?? null)) > 0;
    }

    private static function decodeJsonField($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
