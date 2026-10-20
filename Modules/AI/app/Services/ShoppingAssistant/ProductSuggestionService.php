<?php

namespace Modules\AI\app\Services\ShoppingAssistant;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\FlashDeal;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Translation;
use App\Utils\Helpers;
use App\Utils\ProductManager;
use Illuminate\Database\Eloquent\Builder;
use Modules\AI\app\Contracts\ProductSuggestionInterface;

class ProductSuggestionService implements ProductSuggestionInterface
{
    private const GENERIC_QUERY_TERMS = [
        'product', 'products', 'item', 'items', 'thing', 'things', 'stuff',
        'good', 'goods', 'merchandise', 'catalog', 'catalogue',
        'trending', 'popular', 'bestseller', 'bestsellers', 'best-seller',
        'all', 'everything', 'anything', 'something', 'available',
        'vendor', 'vendors', 'store', 'stores', 'shop', 'shops',
        'seller', 'sellers', 'brand', 'brands',
    ];

    public function suggest(
        string $query,
        ?string $category = null,
        array $attributes = [],
        int $limit = 5,
        ?float $maxPriceUsd = null,
        ?bool $hasVariation = null,
        ?string $sort = null,
        ?string $brand = null,
        ?string $store = null,
    ): array {
        $query         = $this->normalizeQuery($query);
        $categoryId    = $this->resolveCategoryId($category);
        $brandId       = $this->resolveBrandId($brand);
        $sellerUserId  = $this->resolveSellerUserId($store);
        $priceSort     = in_array($sort, ['price_high', 'price_low'], true) ? $sort : null;
        $locale        = app()->getLocale();
        $products      = [];

        if ($query === '' && $categoryId === null && $brandId === null && $sellerUserId === null) {
            $products = $priceSort !== null
                ? $this->priceBrowse(direction: $priceSort, limit: min($limit * 3, 30))
                : $this->popularBrowse(limit: min($limit * 3, 30));

            $products = $this->finalize($products, $maxPriceUsd, $hasVariation, $priceSort, rerankByPopularity: $priceSort === null, limit: $limit);

            return [
                'total'    => count($products),
                'products' => $this->formatProducts($products),
            ];
        }

        if ($brandId !== null || $sellerUserId !== null) {
            $products = $this->filteredBrowse(
                query:        $query,
                categoryId:   $categoryId,
                brandId:      $brandId,
                sellerUserId: $sellerUserId,
                limit:        min($limit * 3, 30),
            );

            $products = $this->finalize($products, $maxPriceUsd, $hasVariation, $priceSort, rerankByPopularity: $priceSort === null, limit: $limit);

            return [
                'total'    => count($products),
                'products' => $this->formatProducts($products),
            ];
        }

        $rerank     = $sort === 'popularity';
        $fetchLimit = ($maxPriceUsd !== null || $hasVariation !== null || $rerank || $priceSort !== null) ? min($limit * 3, 30) : $limit;

        $baseLocale = config('app.fallback_locale', 'en');
        if ($query !== '' && $locale !== $baseLocale) {
            $products = $this->localeSearch(query: $query, categoryId: $categoryId, locale: $locale, limit: $fetchLimit);

            if (empty($products) && $categoryId) {
                $products = $this->localeSearch(query: $query, categoryId: null, locale: $locale, limit: $fetchLimit);
            }
        }

        if (empty($products) && $query !== '') {
            $products = $this->keywordSearch(query: $query, categoryId: $categoryId, limit: $fetchLimit);
        }

        if (empty($products) && $query !== '' && $categoryId) {
            $products = $this->keywordSearch(query: $query, categoryId: null, limit: $fetchLimit);
        }

        if (empty($products) && $query !== '') {
            $products = $this->translatedSearch(query: $query, categoryId: $categoryId, limit: $fetchLimit);
        }

        if (empty($products) && $query !== '' && $categoryId) {
            $products = $this->translatedSearch(query: $query, categoryId: null, limit: $fetchLimit);
        }

        if (empty($products) && $query !== '') {
            $products = $this->tokenizedSearch(query: $query, categoryId: $categoryId, limit: $fetchLimit);
        }

        if (empty($products) && $categoryId) {
            $products = $this->categoryBrowse(categoryId: $categoryId, limit: $fetchLimit);
        }

        $products = $this->finalize($products, $maxPriceUsd, $hasVariation, $priceSort, rerankByPopularity: $rerank, limit: $limit);

        return [
            'total'    => count($products),
            'products' => $this->formatProducts($products),
        ];
    }

    private function finalize(array $products, ?float $maxPriceUsd, ?bool $hasVariation, ?string $priceSort, bool $rerankByPopularity, int $limit): array
    {
        $products = $this->applyPostFilters($products, $maxPriceUsd, $hasVariation);

        if ($priceSort !== null) {
            $products = $this->sortByPrice($products, $priceSort);
        } elseif ($rerankByPopularity) {
            $products = $this->sortByPopularity($products);
        }

        return array_slice($products, 0, $limit);
    }

    private function sortByPrice(array $products, string $direction): array
    {
        usort($products, function ($a, $b) use ($direction) {
            $priceA = (float) ($a->unit_price ?? 0);
            $priceB = (float) ($b->unit_price ?? 0);

            return $direction === 'price_high' ? $priceB <=> $priceA : $priceA <=> $priceB;
        });

        return $products;
    }

    private function sortByPopularity(array $products): array
    {
        $scored = [];
        foreach ($products as $index => $product) {
            $scored[] = [
                'product' => $product,
                'index'   => $index,
                'score'   => $this->popularityScore($product),
            ];
        }

        usort($scored, function ($a, $b) {
            return $b['score'] <=> $a['score']
                ?: $a['index'] <=> $b['index'];
        });

        return array_map(fn($entry) => $entry['product'], $scored);
    }

    private function popularityScore(mixed $product): float
    {
        $ratings   = collect($product->rating ?? []);
        $avgRating = $product->reviews_avg_rating ?? ($ratings->isNotEmpty() ? (float) $ratings->avg('rating') : 0.0);
        $reviews   = $product->reviews_count ?? $ratings->count();
        $orders    = $product->order_details_count ?? 0;

        return ((float) $avgRating * 1000)
            + (min((int) $reviews, 999))
            + (min((int) $orders, 999) / 1000);
    }

    private function applyPostFilters(array $products, ?float $maxPriceUsd, ?bool $hasVariation): array
    {
        if ($maxPriceUsd !== null) {
            $products = array_values(
                array_filter($products, function ($p) use ($maxPriceUsd) {
                    $unitPrice = (float) ($p->unit_price ?? 0);
                    $effective = max(0.0, $unitPrice - Helpers::getProductDiscount(product: $p, price: $unitPrice));
                    return $effective <= $maxPriceUsd;
                })
            );
        }

        if ($hasVariation !== null) {
            $this->ensureDigitalVariationsLoaded($products);
            $products = array_values(
                array_filter($products, fn($p) => $this->productHasVariation($p) === $hasVariation)
            );
        }

        return $products;
    }

    private function filteredBrowse(string $query, ?int $categoryId, ?int $brandId, ?int $sellerUserId, int $limit): array
    {
        $builder = Product::active()
            ->with(['rating', 'tags'])
            ->withCount(['orderDetails', 'reviews'])
            ->withAvg('reviews', 'rating');

        if ($query !== '') {
            $builder->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhereHas('tags', fn(Builder $t) => $t->where('tag', 'like', "%{$query}%"));
            });
        }

        if ($categoryId) {
            $builder->where(function (Builder $q) use ($categoryId) {
                $q->where('category_id', $categoryId)
                  ->orWhere('sub_category_id', $categoryId)
                  ->orWhere('sub_sub_category_id', $categoryId);
            });
        }

        if ($brandId) {
            $builder->where('brand_id', $brandId);
        }

        // Seller/store scope: seller-added products key on user_id = the shop's seller_id.
        if ($sellerUserId) {
            $builder->where('added_by', 'seller')->where('user_id', $sellerUserId);
        }

        return $builder->orderByDesc('id')->limit($limit)->get()->all();
    }

    private function normalizeQuery(string $query): string
    {
        $query = trim($query);
        if ($query === '') {
            return '';
        }

        $tokens = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept   = array_filter(
            $tokens,
            fn($token) => !in_array(strtolower($token), self::GENERIC_QUERY_TERMS, true),
        );

        return trim(implode(' ', $kept));
    }

    private function popularBrowse(int $limit): array
    {
        return Product::active()
            ->with(['rating', 'tags'])
            ->withCount(['orderDetails', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->orderByDesc('order_details_count')
            ->limit($limit)
            ->get()
            ->all();
    }

    private function priceBrowse(string $direction, int $limit): array
    {
        return Product::active()
            ->with(['rating', 'tags'])
            ->withCount(['orderDetails', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->orderBy('unit_price', $direction === 'price_high' ? 'desc' : 'asc')
            ->limit($limit)
            ->get()
            ->all();
    }

    private function resolveBrandId(?string $brand): ?int
    {
        if (!$brand) {
            return null;
        }

        $id = Brand::where('status', 1)->where('name', 'like', "%{$brand}%")->value('id');
        if ($id) {
            return (int) $id;
        }

        $translatedId = Translation::where('translationable_type', Brand::class)
            ->where('key', 'name')
            ->where('value', 'like', "%{$brand}%")
            ->value('translationable_id');

        return $translatedId ? (int) $translatedId : null;
    }

    private function resolveSellerUserId(?string $store): ?int
    {
        if (!$store) {
            return null;
        }

        $sellerId = Shop::where('name', 'like', "%{$store}%")->value('seller_id');

        return $sellerId ? (int) $sellerId : null;
    }

    public function vendors(int $limit = 10, ?string $query = null): array
    {
        $limit = max(1, min($limit, 20));

        $shops = Shop::query()
            ->whereHas('seller', fn(Builder $q) => $q->where('status', 'approved'))
            ->when($query, fn(Builder $q) => $q->where('name', 'like', "%{$query}%"))
            ->withCount(['products'])
            ->orderByDesc('products_count')
            ->limit($limit)
            ->get();

        return $shops->map(fn($shop) => [
            'name'          => (string) ($shop->name ?? ''),
            'product_count' => (int) ($shop->products_count ?? 0),
        ])->all();
    }

    public function brands(int $limit = 15, ?string $query = null): array
    {
        $limit = max(1, min($limit, 30));

        $brands = Brand::where('status', 1)
            ->withCount('brandProducts')
            ->having('brand_products_count', '>', 0)
            ->when($query, fn(Builder $q) => $q->where('name', 'like', "%{$query}%"))
            ->orderByDesc('brand_products_count')
            ->limit($limit)
            ->get();

        return $brands->map(fn($brand) => [
            'name'          => (string) ($brand->name ?? ''),
            'product_count' => (int) ($brand->brand_products_count ?? 0),
        ])->all();
    }

    public function flashDeals(int $limit = 5, ?string $query = null, ?float $maxPriceUsd = null): array
    {
        return $this->campaignDeals('flash_deal', $limit, $query, $maxPriceUsd);
    }

    public function featuredDeals(int $limit = 5, ?string $query = null, ?float $maxPriceUsd = null): array
    {
        return $this->campaignDeals('feature_deal', $limit, $query, $maxPriceUsd);
    }

    private function campaignDeals(string $dealType, int $limit, ?string $query, ?float $maxPriceUsd): array
    {
        $campaign = FlashDeal::where(['deal_type' => $dealType, 'status' => 1])
            ->whereDate('start_date', '<=', date('Y-m-d'))
            ->whereDate('end_date', '>=', date('Y-m-d'))
            ->first();

        if (!$campaign) {
            return ['total' => 0, 'products' => []];
        }

        $fetchLimit = $maxPriceUsd !== null ? min($limit * 3, 30) : $limit;

        $products = Product::active()
            ->flashDeal($campaign->id)
            ->with(['rating', 'tags', 'clearanceSale' => fn($q) => $q->active()])
            ->withCount(['orderDetails', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->when($query, function (Builder $q) use ($query) {
                $q->where(function (Builder $inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                          ->orWhereHas('tags', fn(Builder $t) => $t->where('tag', 'like', "%{$query}%"));
                });
            })
            ->limit($fetchLimit)
            ->get()
            ->all();

        $products = $this->applyPostFilters($products, $maxPriceUsd, null);
        $products = array_slice($this->sortByPopularity($products), 0, $limit);

        return [
            'total'    => count($products),
            'products' => $this->formatProducts($products),
        ];
    }

    public function offers(int $limit = 5, ?string $query = null, ?float $maxPriceUsd = null): array
    {
        $fetchLimit = $maxPriceUsd !== null ? min($limit * 3, 30) : $limit;

        $products = Product::active()
            ->with(['rating', 'tags', 'clearanceSale' => fn($q) => $q->active()])
            ->withCount(['orderDetails', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->where(function (Builder $q) {
                $q->where('discount', '>', 0)
                  ->orWhereHas('clearanceSale', fn($c) => $c->active());
            })
            ->when($query, function (Builder $q) use ($query) {
                $q->where(function (Builder $inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                          ->orWhereHas('tags', fn(Builder $t) => $t->where('tag', 'like', "%{$query}%"));
                });
            })
            ->orderByDesc('discount')
            ->limit($fetchLimit)
            ->get()
            ->all();

        $products = $this->applyPostFilters($products, $maxPriceUsd, null);
        $products = array_slice($this->sortByPopularity($products), 0, $limit);

        return [
            'total'    => count($products),
            'products' => $this->formatProducts($products),
        ];
    }

    private function productHasVariation(mixed $product): bool
    {
        if (($product->product_type ?? 'physical') === 'digital') {
            return !empty($this->parseDigitalVariations($product));
        }

        return !empty($this->parseChoiceOptions($product)) || !empty($this->parseVariation($product));
    }

    private function ensureDigitalVariationsLoaded(array $products): void
    {
        $needsDigital = array_values(array_filter(
            $products,
            fn($p) => $p instanceof \Illuminate\Database\Eloquent\Model
                && ($p->product_type ?? 'physical') === 'digital'
                && !$p->relationLoaded('digitalVariation'),
        ));

        if (!empty($needsDigital)) {
            (new \Illuminate\Database\Eloquent\Collection($needsDigital))->load('digitalVariation');
        }
    }

    public function formatById(int $productId): ?array
    {
        $product = Product::active()
            ->with(['rating', 'digitalVariation'])
            ->where('id', $productId)
            ->first();

        return $product ? $this->formatProduct($product) : null;
    }

    public function similar(int $productId, int $limit = 5): array
    {
        $product = Product::active()
            ->with(['rating', 'digitalVariation'])
            ->where('id', $productId)
            ->first();

        if (!$product) {
            return ['total' => 0, 'products' => []];
        }

        $collected = [];
        $seen      = [$productId => true];

        $push = function (array $products) use (&$collected, &$seen) {
            foreach ($products as $candidate) {
                $id = $candidate->id ?? null;
                if ($id === null || isset($seen[$id])) {
                    continue;
                }
                $seen[$id]   = true;
                $collected[] = $candidate;
            }
        };

        foreach ([$product->category_id, $product->sub_category_id, $product->sub_sub_category_id] as $categoryId) {
            if (count($collected) >= $limit) {
                break;
            }
            if ($categoryId) {
                $push($this->categoryBrowse((int) $categoryId, $limit * 3));
            }
        }

        if (count($collected) < $limit && !empty($product->brand_id)) {
            $push($this->brandProducts((int) $product->brand_id, $limit * 2));
        }

        if (count($collected) < $limit) {
            $push($this->tokenizedSearch((string) $product->name, null, $limit * 2));
        }

        return [
            'total'    => count($collected),
            'products' => $this->formatProducts(array_slice($collected, 0, $limit)),
        ];
    }

    private function brandProducts(int $brandId, int $limit): array
    {
        return Product::active()
            ->with(['rating'])
            ->where('brand_id', $brandId)
            ->limit($limit)
            ->get()
            ->all();
    }

    private function localeSearch(string $query, ?int $categoryId, string $locale, int $limit): array
    {
        $productIds = Translation::where('translationable_type', Product::class)
            ->where('locale', $locale)
            ->where('key', 'name')
            ->where('value', 'like', "%{$query}%")
            ->pluck('translationable_id')
            ->unique()
            ->toArray();

        if (empty($productIds)) {
            $productIds = $this->localeTokenMatchIds($query, $locale, $limit);
        }

        if (empty($productIds)) {
            return [];
        }

        $builder = Product::active()
            ->with(['rating'])
            ->whereIn('id', $productIds);

        if ($categoryId) {
            $builder->where(function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId)
                  ->orWhere('sub_category_id', $categoryId)
                  ->orWhere('sub_sub_category_id', $categoryId);
            });
        }

        // whereIn returns DB order, not $productIds token-match relevance; re-sort below.
        $rank     = array_flip(array_values($productIds));
        $products = $builder->get()->all();
        usort($products, fn($a, $b) => ($rank[$a->id] ?? PHP_INT_MAX) <=> ($rank[$b->id] ?? PHP_INT_MAX));

        return array_slice($products, 0, $limit);
    }

    private function localeTokenMatchIds(string $query, string $locale, int $limit): array
    {
        $tokens = $this->significantTokens($query);
        if (empty($tokens)) {
            return [];
        }

        $scores = [];
        foreach ($tokens as $token) {
            $ids = Translation::where('translationable_type', Product::class)
                ->where('locale', $locale)
                ->where('key', 'name')
                ->where('value', 'like', "%{$token}%")
                ->limit($limit * 4)
                ->pluck('translationable_id');

            foreach ($ids as $id) {
                $scores[$id] = ($scores[$id] ?? 0) + 1;
            }
        }

        arsort($scores);

        return array_keys($scores);
    }

    private function keywordSearch(string $query, ?int $categoryId, int $limit): array
    {
        $result = ProductManager::getSearchProductsForWeb(
            name:     $query,
            category: $categoryId ?? 'all',
            limit:    $limit,
            offset:   1,
        );
        return $result['products'] ?? [];
    }

    private function translatedSearch(string $query, ?int $categoryId, int $limit): array
    {
        $result = ProductManager::translated_product_search(
            name:     base64_encode($query),
            category: $categoryId ?? 'all',
            limit:    $limit,
            offset:   1,
        );

        // translated_product_search skips active(); re-filter so we never surface a product the storefront hides.
        return $this->keepActive($result['products'] ?? []);
    }

    private function keepActive(array $products): array
    {
        if (empty($products)) {
            return [];
        }

        $ids       = array_values(array_filter(array_map(fn($p) => $p->id ?? null, $products)));
        $activeSet = array_flip(Product::active()->whereIn('id', $ids)->pluck('id')->all());

        return array_values(array_filter($products, fn($p) => isset($activeSet[$p->id])));
    }

    private function categoryBrowse(int $categoryId, int $limit): array
    {
        $result = ProductManager::getSearchProductsForWeb(
            name:     '',
            category: $categoryId,
            limit:    $limit,
            offset:   1,
        );
        return $result['products'] ?? [];
    }

    private function tokenizedSearch(string $query, ?int $categoryId, int $limit): array
    {
        $tokens = $this->significantTokens($query);
        if (empty($tokens)) {
            return [];
        }

        $scored = [];
        foreach ($tokens as $token) {
            foreach ($this->keywordSearch(query: $token, categoryId: $categoryId, limit: $limit * 2) as $product) {
                $id = $product->id;
                if (!isset($scored[$id])) {
                    $scored[$id] = ['product' => $product, 'score' => 0];
                }
                $scored[$id]['score']++;
            }
        }

        if (empty($scored) && $categoryId) {
            return $this->tokenizedSearch(query: $query, categoryId: null, limit: $limit);
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn($entry) => $entry['product'], array_slice($scored, 0, $limit));
    }

    private function significantTokens(string $query): array
    {
        static $stop = [
            'the', 'and', 'for', 'with', 'this', 'that', 'any', 'some', 'one',
            'please', 'want', 'need', 'buy', 'show', 'find', 'get', 'looking',
            'have', 'you', 'your', 'are', 'phone', 'mobile', 'smartphone', 'device',
        ];

        return collect(preg_split('/[^\p{L}\p{N}]+/u', strtolower($query), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn($token) => mb_strlen($token) >= 2 && !in_array($token, $stop, true))
            ->unique()
            ->values()
            ->all();
    }

    private function formatProducts(array $products): array
    {
        $this->ensureDigitalVariationsLoaded($products);

        $codes = [];
        foreach ($products as $product) {
            foreach ($this->extractColorCodes($product) as $code) {
                $codes[$code] = true;
            }
        }

        $colorNameMap = empty($codes)
            ? []
            : Color::whereIn('code', array_keys($codes))->pluck('name', 'code')->toArray();

        return array_map(fn($product) => $this->formatProduct($product, $colorNameMap), $products);
    }

    private function formatProduct(mixed $product, ?array $colorNameMap = null): array
    {
        $unitPrice       = (float) ($product->unit_price ?? 0);
        $discountAmt     = Helpers::getProductDiscount(product: $product, price: $unitPrice);
        $discountedPrice = max(0.0, $unitPrice - $discountAmt);

        $thumbnailUrl = '';
        try {
            $thumbnailUrl = \getStorageImages(path: $product->thumbnail_full_url, type: 'product');
        } catch (\Throwable) {}

        $ratings     = collect($product->rating ?? []);
        $avgRating   = round((float) $ratings->avg('rating'), 1);
        $ratingCount = $ratings->count();

        return [
            'id'                         => $product->id,
            'name'                       => $product->name ?? '',
            'slug'                       => $product->slug ?? '',
            'thumbnail_full_url'         => $thumbnailUrl,
            // Base-currency prices converted to the shopper's currency for display; cart writes recompute from the DB base, so these are display-only.
            'unit_price'                 => webCurrencyConverterOnlyDigit($unitPrice),
            'discount'                   => (float) ($product->discount ?? 0),
            'discount_type'              => $product->discount_type ?? 'flat',
            'discounted_price'           => webCurrencyConverterOnlyDigit($discountedPrice),
            'unit_price_formatted'       => webCurrencyConverter($unitPrice),
            'discounted_price_formatted' => webCurrencyConverter($discountedPrice),
            'avg_rating'                 => $avgRating,
            'rating_count'               => $ratingCount,
            'product_type'               => $product->product_type ?? 'physical',
            'colors'                     => $this->parseColors($product, $colorNameMap),
            'color_images'               => $this->parseColorImages($product),
            'choice_options'             => $this->parseChoiceOptions($product),
            'variation'                  => $this->parseVariation($product),
            'digital_variations'         => $this->parseDigitalVariations($product),
            'digital_product_extensions' => $this->parseDigitalProductExtensions($product),
            'current_stock'              => (int) ($product->current_stock ?? 0),
            'minimum_order_qty'          => (int) ($product->minimum_order_qty ?? 1),
        ];
    }

    private function parseColorImages(mixed $product): array
    {
        try {
            $entries = $product->color_images_full_url ?? [];
            if (empty($entries)) return [];

            return array_values(array_filter(array_map(function ($item) {
                $item = (array) $item;
                $code = ltrim((string) ($item['color'] ?? ''), '#');
                if ($code === '') return null;
                $url = \getStorageImages(path: $item['image_name'] ?? [], type: 'product');
                return ['code' => '#' . $code, 'url' => $url];
            }, $entries)));
        } catch (\Throwable) {
            return [];
        }
    }

    private function extractColorCodes(mixed $product): array
    {
        try {
            $raw   = $product->colors ?? '[]';
            $codes = is_array($raw) ? $raw : (json_decode($raw, true) ?? []);

            return array_values(array_filter($codes, fn($c) => is_string($c) && $c !== ''));
        } catch (\Throwable) {
            return [];
        }
    }

    private function parseColors(mixed $product, ?array $colorNameMap = null): array
    {
        $codes = $this->extractColorCodes($product);
        if (empty($codes)) return [];

        $nameMap = $colorNameMap ?? Color::whereIn('code', $codes)->pluck('name', 'code')->toArray();

        return array_map(fn($code) => [
            'code' => $code,
            'name' => $nameMap[$code] ?? ltrim($code, '#'),
        ], $codes);
    }

    private function parseChoiceOptions(mixed $product): array
    {
        try {
            $raw    = $product->choice_options ?? '[]';
            $parsed = is_array($raw) ? $raw : (json_decode($raw, true) ?? []);

            if (empty($parsed)) return [];

            return array_values(array_map(function ($choice) {
                $isArr = is_array($choice);
                return [
                    'title'   => (string) ($isArr ? ($choice['title'] ?? '') : ($choice->title ?? '')),
                    'name'    => (string) ($isArr ? ($choice['name'] ?? '') : ($choice->name ?? '')),
                    'options' => array_values((array) ($isArr ? ($choice['options'] ?? []) : ($choice->options ?? []))),
                ];
            }, $parsed));
        } catch (\Throwable) {
            return [];
        }
    }

    // `type` is the dash-joined combination key (e.g. "Red-M") the cart matches on.
    private function parseVariation(mixed $product): array
    {
        try {
            $raw    = $product->variation ?? '[]';
            $parsed = is_array($raw) ? $raw : (json_decode($raw, true) ?? []);

            if (empty($parsed)) return [];

            return array_values(array_map(function ($var) {
                $isArr = is_array($var);
                $basePrice = (float) ($isArr ? ($var['price'] ?? 0) : ($var->price ?? 0));
                return [
                    'type'  => (string) ($isArr ? ($var['type'] ?? '') : ($var->type ?? '')),
                    'price' => (float) webCurrencyConverterOnlyDigit($basePrice),
                    'sku'   => (string) ($isArr ? ($var['sku'] ?? '') : ($var->sku ?? '')),
                    'qty'   => (int) ($isArr ? ($var['qty'] ?? 0) : ($var->qty ?? 0)),
                ];
            }, $parsed));
        } catch (\Throwable) {
            return [];
        }
    }

    // variant_key is the cart match value for digital products.
    private function parseDigitalVariations(mixed $product): array
    {
        if (($product->product_type ?? 'physical') !== 'digital') {
            return [];
        }

        try {
            $variations = $product->digitalVariation ?? [];

            return array_values(array_map(function ($var) {
                $key = (string) ($var->variant_key ?? '');
                return [
                    'variant_key' => $key,
                    'label'       => str_replace('_', ' ', $key),
                    'price'       => (float) webCurrencyConverterOnlyDigit((float) ($var->price ?? 0)),
                    'sku'         => (string) ($var->sku ?? ''),
                ];
            }, $variations instanceof \Illuminate\Support\Collection ? $variations->all() : (array) $variations));
        } catch (\Throwable) {
            return [];
        }
    }

    private function parseDigitalProductExtensions(mixed $product): array
    {
        if (($product->product_type ?? 'physical') !== 'digital') {
            return [];
        }

        try {
            $raw    = $product->digital_product_extensions ?? [];
            $parsed = is_array($raw) ? $raw : (json_decode($raw, true) ?? []);

            if (empty($parsed)) return [];

            $extensions = [];
            foreach ($parsed as $type => $options) {
                $extensions[] = [
                    'type'    => (string) $type,
                    'options' => array_values(array_filter((array) $options, fn($o) => $o !== null && $o !== '')),
                ];
            }

            return $extensions;
        } catch (\Throwable) {
            return [];
        }
    }

    private function resolveCategoryId(?string $categoryName): ?int
    {
        if (!$categoryName) {
            return null;
        }

        $id = Category::where('name', 'like', "%{$categoryName}%")->value('id');
        if ($id) {
            return (int) $id;
        }

        $translatedId = Translation::where('translationable_type', Category::class)
            ->where('key', 'name')
            ->where('value', 'like', "%{$categoryName}%")
            ->value('translationable_id');

        return $translatedId ? (int) $translatedId : null;
    }
}
