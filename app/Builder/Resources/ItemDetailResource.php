<?php

namespace App\Builder\Resources;

use App\Builder\Support\CardContext;
use App\Builder\Support\ItemPricing;
use App\Models\Product;
use App\Models\Review;
use App\Utils\Helpers;
use Illuminate\Support\Str;
use Modules\Builder\Contracts\WishlistProvider;
use Modules\Builder\ValueObjects\Storefront\ItemDetailDTO;

/**
 * Maps an App\Models\Product into the exact ItemDetailDTO shape the React
 * storefront's item-details page consumes.
 *
 * 6Valley has no food module, so the food-only axes are constant: moduleType is
 * null, genericName/nutritionsName/allergiesName are empty. Prices and discounts
 * flow through ItemPricing so the detail header matches list cards and the cart.
 */
class ItemDetailResource
{
    public static function fromOne(Product $product): array
    {
        $product = Helpers::product_data_formatting($product) ?: $product;

        $images = collect($product->images_full_url ?? [])
            ->map(fn ($image) => getStorageImages(path: $image, type: 'product'))
            ->filter()
            ->values();

        if ($images->isEmpty()) {
            $images = collect([getStorageImages(path: $product->thumbnail_full_url, type: 'product')]);
        }

        $pricing = ItemPricing::compute($product);
        $stock = (int) ($product->current_stock ?? 0);

        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit,
            'currency' => CardContext::default()['currency'] ?? '$',
            'price' => $pricing['price'],
            'oldPrice' => $pricing['oldPrice'],
            'discountAmount' => $pricing['discountAmount'],
            'discountPercent' => $pricing['discountPercent'],
            'discountType' => $pricing['discountType'],
            'discountSource' => $pricing['discountSource'],
            'rating' => round((float) ($product->reviews_avg_rating ?? self::averageRating($product)), 1),
            'ratingCount' => (int) ($product->reviews_count ?? $product->reviews()->where('status', 1)->count()),
            'reviewCount' => (int) $product->reviews()->where('status', 1)->count(),
            'ratingDistribution' => self::ratingDistribution($product),
            'inStock' => $stock > 0,
            'stock' => $stock,
            'lowStockThreshold' => 10,
            'maxCartQuantity' => (int) ($product->maximum_order_quantity ?? 0),
            'moduleType' => null,
            'genericName' => null,
            'images' => $images->all(),
            'variations' => self::detailVariations($product),
            'variationCombinations' => self::detailVariationCombinations($product),
            'tags' => $product->tags->pluck('tag')->filter()->values()->all(),
            'description' => (string) ($product->details ?? ''),
            // Optional promotional YouTube link — drives the storefront's
            // "Video" tab. Trimmed to null when unset so the tab stays hidden.
            'videoUrl' => \filled($product->video_url) ? trim((string) $product->video_url) : null,
            'reviews' => self::detailReviews($product),
            'isWishlist' => app(WishlistProvider::class)->has((int) $product->id),
            'nutritionsName' => [],
            'allergiesName' => [],
            'seoTitle' => $product->meta_title ?: null,
            'seoDescription' => $product->meta_description ?: null,
            'seoImage' => $product->meta_image
                ? getStorageImages(path: $product->meta_image_full_url, type: 'product')
                : ($images->first() ?: null),
        ];

        return ItemDetailDTO::fromArray($data)->toArray();
    }

    private static function detailVariations(Product $product): array
    {
        // Digital products store their variations in a separate table
        // (digital_product_variation), NOT in choice_options/colors — which are
        // forced to `[]` for digital on save. Build a single selectable axis from
        // those rows so the storefront can render + price them.
        if (($product->product_type ?? null) === 'digital') {
            return self::detailDigitalVariations($product);
        }

        $choiceAxes = collect(self::decode($product->getAttributes()['choice_options'] ?? null))
            ->map(function ($choice) {
                $title = (string) ($choice['title'] ?? $choice['name'] ?? 'Option');
                $isColor = \str_contains(\strtolower($title), 'color');

                return [
                    'title' => $title,
                    'type' => $isColor ? 'color' : 'text',
                    'required' => true,
                    'options' => collect($choice['options'] ?? [])
                        ->map(fn ($option) => [
                            'id' => Str::slug($title . '-' . (string) $option),
                            'label' => (string) $option,
                            'value' => $isColor ? (string) $option : null,
                            'variantKey' => \preg_replace('/\s+/', '', \trim((string) $option)) ?? '',
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $choice) => $choice['options'] !== [])
            ->values()
            ->all();

        // Colors live in their own `colors` column, not in choice_options.
        // Prepend a color axis so it renders and its variantKey (color name)
        // matches the variation combination keys, which CartManager builds as
        // "<colorName>-<choiceOption>-..." with color first.
        $colorAxis = self::detailColorVariation($product);

        return $colorAxis ? \array_merge([$colorAxis], $choiceAxes) : $choiceAxes;
    }

    private static function detailColorVariation(Product $product): ?array
    {
        $colors = collect($product->colors_formatted ?? [])
            ->filter(fn ($color) => \filled($color['name'] ?? null) && \filled($color['code'] ?? null))
            ->map(fn ($color) => [
                'id' => Str::slug('color-' . (string) $color['name']),
                'label' => (string) $color['name'],
                'value' => (string) $color['code'],
                'variantKey' => \preg_replace('/\s+/', '', \trim((string) $color['name'])) ?? '',
            ])
            ->values()
            ->all();

        if ($colors === []) {
            return null;
        }

        return [
            'title' => 'Color',
            'type' => 'color',
            'required' => true,
            'options' => $colors,
        ];
    }

    /**
     * Digital variations: each `digital_product_variation` row is one selectable
     * variant (variant_key like "Format-PDF"), each with its own price. There is
     * no cartesian multi-axis structure, so expose a single axis whose option
     * `variantKey` equals the row's variant_key — that key is what the combination
     * map is keyed by and what the cart stores (CartManager::addToCartDigitalProduct
     * looks the price up by `variant_key`).
     */
    private static function detailDigitalVariations(Product $product): array
    {
        $options = collect($product->digitalVariation ?? [])
            ->filter(fn ($variation) => \filled($variation->variant_key ?? null))
            ->map(fn ($variation) => [
                'id' => Str::slug((string) $variation->variant_key) ?: (string) $variation->variant_key,
                'label' => self::digitalVariantLabel((string) $variation->variant_key),
                'value' => null,
                'variantKey' => (string) $variation->variant_key,
            ])
            ->values()
            ->all();

        if ($options === []) {
            return [];
        }

        return [[
            'title' => translate('Variation') ?: 'Variation',
            'type' => 'text',
            'required' => true,
            'options' => $options,
        ]];
    }

    /**
     * Human-readable label for a digital variant_key. Keys are "<type>-<option>"
     * (e.g. "Format-PDF"); show "Type: Option" and de-underscore the option
     * (options are stored with '.'→'_' and spaces stripped).
     */
    private static function digitalVariantLabel(string $variantKey): string
    {
        $parts = \explode('-', $variantKey, 2);
        $option = \str_replace('_', '.', $parts[1] ?? $variantKey);
        return isset($parts[1]) ? ($parts[0] . ': ' . $option) : $option;
    }

    private static function detailVariationCombinations(Product $product): array
    {
        if (($product->product_type ?? null) === 'digital') {
            // Digital products are unlimited stock; key each combination by the
            // exact variant_key so the front's combinationKey (single-axis join)
            // and the cart payload line up.
            return collect($product->digitalVariation ?? [])
                ->filter(fn ($variation) => \filled($variation->variant_key ?? null))
                ->mapWithKeys(function ($variation) use ($product) {
                    $pricing = ItemPricing::compute($product, (float) ($variation->price ?? 0));

                    return [
                        (string) $variation->variant_key => [
                            'price' => $pricing['price'],
                            'oldPrice' => $pricing['oldPrice'],
                            'discountPercent' => $pricing['discountPercent'],
                            'stock' => 999999,
                            'inStock' => true,
                        ],
                    ];
                })
                ->all();
        }

        return collect(self::decode($product->getAttributes()['variation'] ?? null))
            ->filter(fn ($variation) => \filled($variation['type'] ?? null))
            ->mapWithKeys(function ($variation) use ($product) {
                $pricing = ItemPricing::compute($product, (float) ($variation['price'] ?? 0));
                $stock = (int) ($variation['qty'] ?? 0);

                return [
                    (string) $variation['type'] => [
                        'price' => $pricing['price'],
                        'oldPrice' => $pricing['oldPrice'],
                        'discountPercent' => $pricing['discountPercent'],
                        'stock' => $stock,
                        'inStock' => $stock > 0,
                    ],
                ];
            })
            ->all();
    }

    /**
     * Map a single Review row to the frontend shape. Shared with
     * ItemProvider::listReviews so the drawer and the bootstrap render identical
     * cards.
     */
    public static function reviewRow(Review $review): array
    {
        $images = collect($review->attachment_full_url ?? [])
            ->map(fn ($image) => getStorageImages(path: $image, type: 'backend-basic'))
            ->filter()
            ->values()
            ->all();

        $reply = $review->reply?->reply_text ?? '';

        return [
            'id' => $review->id,
            'authorName' => \trim(($review->customer?->f_name ?? '') . ' ' . ($review->customer?->l_name ?? '')) ?: 'Customer',
            'avatar' => $review->customer && !empty($review->customer->image)
                ? getStorageImages(path: $review->customer->image_full_url, type: 'backend-profile')
                : null,
            'rating' => round((float) ($review->rating ?? 0), 1),
            'date' => optional($review->created_at)->format('F j, Y'),
            'text' => (string) ($review->comment ?? ''),
            'images' => $images,
            'hasReply' => \filled($reply),
            'reply' => (string) $reply,
            'replyAuthor' => null,
            'replyDate' => \filled($reply) ? optional($review->reply?->created_at)->format('F j, Y') : null,
        ];
    }

    private static function detailReviews(Product $product): array
    {
        return $product->reviews()
            ->where('status', 1)
            ->latest()
            ->take(12)
            ->with(['customer', 'reply'])
            ->get()
            ->map(fn (Review $review) => self::reviewRow($review))
            ->values()
            ->all();
    }

    /** Approved-review counts grouped by integer star bucket (5..1). */
    public static function ratingDistribution(Product $product): array
    {
        $rows = Review::query()
            ->where('product_id', $product->id)
            ->where('status', 1)
            ->selectRaw('ROUND(rating) as bucket, COUNT(*) as cnt')
            ->groupBy('bucket')
            ->pluck('cnt', 'bucket');

        $out = [];
        foreach ([5, 4, 3, 2, 1] as $star) {
            $out[$star] = (int) ($rows[$star] ?? 0);
        }
        return $out;
    }

    private static function averageRating(Product $product): float
    {
        return (float) $product->reviews()->where('status', 1)->avg('rating');
    }

    private static function decode($value): array
    {
        if (is_string($value) && $value !== '') {
            $value = json_decode($value, true);
        }
        // Deep-normalize any stdClass (from a pre-decoded/object-cast
        // attribute) to associative arrays so callers can array-access
        // nested keys like $choice['title'] and $variation['type'].
        if (is_object($value) || is_array($value)) {
            return json_decode(json_encode($value), true) ?: [];
        }
        return [];
    }
}
