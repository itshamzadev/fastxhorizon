<?php

namespace App\Builder;

use App\Builder\Resources\ItemCardResource;
use App\Builder\Resources\ItemDetailResource;
use App\Builder\Support\CardContext;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\Review;
use App\Models\Wishlist;
use App\Utils\Helpers;
use Illuminate\Database\Eloquent\Builder;
use Modules\Builder\Contracts\ItemProvider as ItemProviderContract;
use Modules\Builder\Services\StorefrontContext;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * 6Valley host adapter for the storefront item (product) surface.
 *
 * Maps App\Models\Product onto the module's ItemProvider contract. 6Valley has
 * no delivery modules or zones, so scope collapses to an optional shop filter.
 * The shop is matched by canonical seller ownership (added_by + user_id via
 * StorefrontScope::tenantId), NOT products.shop_id, which is not reliable for
 * ownership — see applyShopScope(). There is no food module, so foodDetails()
 * always returns null and view() always renders the standard item-detail shape.
 */
class ItemProvider implements ItemProviderContract
{
    /**
     * The product logView() recorded this request, if any. On the item-details
     * page the controller calls logView() then recent() on the same injected
     * instance, so recent() uses this to drop the product being viewed from its
     * own "recently viewed" strip.
     */
    private ?int $currentViewedItemId = null;

    public function search(?StorefrontScope $scope, ?string $query, int $limit = 20): array
    {
        return $this->listing(
            $scope,
            ['search' => \trim((string) $query), 'sort' => 'rating'],
            \max(1, \min($limit, 50)),
            1,
        )['products'];
    }

    public function discounted(?StorefrontScope $scope, int $limit = 12): array
    {
        return $this->listing(
            $scope,
            ['discounted' => true, 'sort' => 'high_to_low'],
            $limit,
            1,
        )['products'];
    }

    public function recent(?StorefrontScope $scope, int $limit = 10): array
    {
        $limit = \max(1, \min($limit, 100));
        $customerId = $this->resolveUserId();

        $excludeId = $this->currentViewedItemId;

        // On a vendor storefront baseQuery() already limits to the shop's own
        // products. On the global marketplace (no scope) keep the item-details
        // "recently viewed" strip to the shop of the product being viewed, so it
        // doesn't pull in other shops' products. Ownership is added_by + user_id
        // (products.shop_id is not reliable — see applyShopScope()).
        $ownerFilter = null;
        if ($scope === null && $excludeId) {
            $viewed = Product::query()->whereKey($excludeId)->first(['added_by', 'user_id']);
            if ($viewed) {
                $ownerFilter = function (Builder $query) use ($viewed) {
                    $query->where('added_by', $viewed->added_by);
                    if ($viewed->added_by === 'seller') {
                        $query->where('user_id', $viewed->user_id);
                    }
                };
            }
        }

        // Logged-in customer: surface the products they actually viewed, most
        // recent first (logView records these in product_views). The product
        // currently being viewed is excluded so it never lists itself.
        if ($customerId) {
            $viewedIds = ProductView::query()
                ->where('customer_id', $customerId)
                ->when($excludeId, fn ($q) => $q->where('product_id', '!=', $excludeId))
                ->orderByDesc('viewed_at')
                ->limit($limit * 3) // over-fetch: the shop filter below drops other shops' views
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($viewedIds !== []) {
                $products = $this->baseQuery($scope)
                    ->whereIn('products.id', $viewedIds)
                    ->when($ownerFilter, fn (Builder $q) => $ownerFilter($q))
                    ->get()
                    ->sortBy(fn (Product $product) => \array_search((int) $product->id, $viewedIds, true))
                    ->values()
                    ->take($limit);

                if ($products->isNotEmpty()) {
                    return ItemCardResource::fromCollection($products->all(), $this->cardContext());
                }
            }
        }

        // Guest, or no view history yet: fall back to the newest active products.
        $products = $this->baseQuery($scope)
            ->when($excludeId, fn (Builder $q) => $q->whereKeyNot($excludeId))
            ->when($ownerFilter, fn (Builder $q) => $ownerFilter($q))
            ->latest('products.created_at')
            ->limit($limit)
            ->get();

        return ItemCardResource::fromCollection($products->all(), $this->cardContext());
    }

    public function topSelling(?StorefrontScope $scope, int $limit = 8): array
    {
        $limit = \max(1, \min($limit, 50));

        $products = $this->baseQuery($scope)
            ->withCount('orderDetails')
            ->orderByDesc('order_details_count')
            ->orderByDesc('products.created_at')
            ->limit($limit)
            ->get();

        return ItemCardResource::fromCollection($products->all(), $this->cardContext());
    }

    public function listing(?StorefrontScope $scope, array $filters = [], int $limit = 12, int $offset = 1): array
    {
        $limit = \max(1, \min($limit, 100));
        $offset = \max(1, $offset);

        $query = $this->baseQuery($scope);

        if (($filters['discounted'] ?? false) === true) {
            $query->where(function (Builder $builder) {
                $builder->where('discount', '>', 0)
                    ->orWhereHas('clearanceSale', fn (Builder $inner) => $inner->where('status', 1));
            });
        }

        $this->applyFilters($query, $filters);
        $this->applySort($query, (string) ($filters['sort'] ?? 'high_to_low'));

        $paginator = $query->paginate($limit, ['*'], 'page', $offset);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => ItemCardResource::fromCollection($paginator->items(), $this->cardContext()),
            'categories' => $this->resultCategories($scope, $paginator->items()),
        ];
    }

    public function wishlist(?StorefrontScope $scope, int $limit = 12, int $offset = 1): array
    {
        $limit = \max(1, \min($limit, 100));
        $offset = \max(1, $offset);
        $customerId = $this->resolveUserId();

        if (!$customerId) {
            return ['total_size' => 0, 'limit' => $limit, 'offset' => $offset, 'products' => [], 'categories' => []];
        }

        $query = $this->baseQuery($scope)
            ->whereHas('wishList', fn (Builder $q) => $q->where('customer_id', $customerId))
            ->orderByDesc(
                Wishlist::select('created_at')
                    ->whereColumn('product_id', 'products.id')
                    ->where('customer_id', $customerId)
                    ->latest()
                    ->limit(1)
            );

        $paginator = $query->paginate($limit, ['*'], 'page', $offset);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => ItemCardResource::fromCollection($paginator->items(), $this->cardContext()),
            'categories' => $this->resultCategories($scope, $paginator->items()),
        ];
    }

    public function priceRange(?StorefrontScope $scope): array
    {
        $row = $this->baseQuery($scope)
            ->selectRaw('MIN(unit_price) as min_price, MAX(unit_price) as max_price')
            ->first();

        // unit_price is stored in USD; convert the bounds to the active web
        // currency so the slider matches the converted item-card prices.
        return [
            'min' => (int) floor((float) webCurrencyConverterOnlyDigit(amount: (float) ($row->min_price ?? 0))),
            'max' => (int) ceil((float) webCurrencyConverterOnlyDigit(amount: (float) ($row->max_price ?? 0))),
        ];
    }

    public function findIdBySlug(?StorefrontScope $scope, string $slug): ?int
    {
        $slug = \trim($slug);
        if ($slug === '') {
            return null;
        }

        $id = $this->baseQuery($scope)->where('slug', $slug)->value('id');

        return $id ? (int) $id : null;
    }

    public function details(?StorefrontScope $scope, int $itemId): ?array
    {
        $product = $this->baseQuery($scope)
            ->whereKey($itemId)
            ->with(['tags', 'reviews.customer', 'reviews.reply', 'seoInfo', 'clearanceSale', 'digitalVariation'])
            ->first();

        return $product ? ItemDetailResource::fromOne($product) : null;
    }

    public function view(?StorefrontScope $scope, int $itemId): ?array
    {
        if ($itemId <= 0) {
            return null;
        }

        $product = $this->baseQuery($scope)
            ->whereKey($itemId)
            ->with(['tags', 'reviews.customer', 'reviews.reply', 'clearanceSale', 'digitalVariation'])
            ->first();

        if (!$product) {
            return null;
        }

        $this->logView($scope, (int) $product->id, (int) ($this->resolveUserId() ?? 0));

        return ItemDetailResource::fromOne($product);
    }

    public function logView(?StorefrontScope $scope, int $itemId, int $customerId): void
    {
        if ($itemId <= 0) {
            return;
        }

        // Remember the product being viewed so recent() can exclude it from the
        // same page's "recently viewed" strip (both run this request).
        $this->currentViewedItemId = $itemId;

        if ($customerId <= 0) {
            return;
        }

        $view = ProductView::firstOrNew([
            'customer_id' => $customerId,
            'product_id' => $itemId,
        ]);

        // Always refresh the timestamp so "recently viewed" reflects true last-view
        // order; bump the frequency signal at most once per day.
        if (!$view->exists) {
            $view->view_count = 1;
        } elseif (!$view->viewed_at || $view->viewed_at->lt(now()->startOfDay())) {
            $view->view_count = (int) $view->view_count + 1;
        }
        $view->viewed_at = now();
        $view->save();
    }

    public function foodDetails(?StorefrontScope $scope, int $itemId): ?array
    {
        // 6Valley has no food module.
        return null;
    }

    public function similar(?StorefrontScope $scope, int $itemId, int $limit = 6): array
    {
        $product = $this->baseQuery($scope)->whereKey($itemId)->first();

        if (!$product) {
            return [];
        }

        // Similar items are products in the same category. baseQuery already
        // constrains to the storefront's shop when scoped, so a vendor store
        // shows same-category items from that shop only, never other vendors'.
        $products = $this->baseQuery($scope)
            ->whereKeyNot($itemId)
            ->where('category_id', $product->category_id)
            ->orderByDesc('reviews_avg_rating')
            ->latest('products.created_at')
            ->limit(\max(1, \min($limit, 20)))
            ->get();

        return ItemCardResource::fromCollection($products->all(), $this->cardContext());
    }

    public function listReviews(?StorefrontScope $scope, int $itemId, int $page = 1, int $perPage = 10): array
    {
        $exists = $this->baseQuery($scope)->whereKey($itemId)->exists();
        if (!$exists) {
            return ['reviews' => [], 'page' => $page, 'perPage' => $perPage, 'total' => 0, 'hasMore' => false];
        }

        $page = \max(1, $page);
        $perPage = \max(1, \min($perPage, 50));

        $query = Review::query()
            ->where('product_id', $itemId)
            ->where('status', 1)
            ->latest()
            ->with(['customer', 'reply']);

        $total = (clone $query)->count();
        $reviews = $query->forPage($page, $perPage)->get();
        $loaded = ($page - 1) * $perPage + $reviews->count();

        return [
            'reviews' => $reviews->map(fn (Review $review) => ItemDetailResource::reviewRow($review))->all(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'hasMore' => $loaded < $total,
        ];
    }

    public function byCategories(?StorefrontScope $scope, array $categoryIds, int $limit = 12): array
    {
        $result = [];

        foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
            if ($categoryId <= 0) {
                continue;
            }

            $result[$categoryId] = $this->listing(
                $scope,
                ['categoryIds' => [$categoryId], 'sort' => 'rating'],
                $limit,
                1,
            )['products'];
        }

        return $result;
    }

    private function baseQuery(?StorefrontScope $scope, string $type = 'all'): Builder
    {
        $query = Product::query()
            ->active()
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->with('clearanceSale');

        $this->applyShopScope($query, $scope);

        return $query;
    }

    /**
     * Restrict the storefront to its shop's own products using 6Valley's
     * canonical ownership (added_by + user_id) — the same fields the legacy
     * ProductRepository and shop pages use. products.shop_id is NOT reliable
     * for this (every vendor's rows can share one shop_id), so scoping on it
     * leaks other vendors' products onto the storefront. A vendor storefront
     * (seller_id > 0) shows only that seller's products; the in-house
     * storefront (seller_id 0) shows admin products. No scope (global
     * marketplace) is unrestricted.
     */
    private function applyShopScope(Builder $query, ?StorefrontScope $scope): void
    {
        if ($scope === null) {
            return;
        }

        $sellerId = $scope->tenantId;

        if ($sellerId) {
            $query->where('added_by', 'seller')->where('user_id', $sellerId);
        } else {
            $query->where('added_by', 'admin');
        }
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $categoryIds = collect($filters['categoryIds'] ?? [])
            ->filter(fn ($id) => \is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $search = \trim((string) ($filters['search'] ?? ''));
        $priceMin = $filters['priceMin'] ?? null;
        $priceMax = $filters['priceMax'] ?? null;
        $rating = $filters['rating'] ?? null;
        $excludeItemId = $filters['excludeItemId'] ?? null;

        $query
            ->when($categoryIds !== [], function (Builder $builder) use ($categoryIds) {
                $builder->where(function (Builder $nested) use ($categoryIds) {
                    $nested->whereIn('category_id', $categoryIds);
                    foreach ($categoryIds as $categoryId) {
                        $nested->orWhereJsonContains('category_ids', ['id' => (string) $categoryId]);
                    }
                });
            })
            ->when($search !== '', function (Builder $builder) use ($search) {
                $words = \preg_split('/\s+/', $search) ?: [];
                $matchedCategoryIds = $this->categoryIdsMatchingSearch($words);
                $builder->where(function (Builder $nested) use ($words, $matchedCategoryIds) {
                    $nested->where(function (Builder $textQuery) use ($words) {
                        foreach ($words as $word) {
                            $textQuery->where(function (Builder $wordQuery) use ($word) {
                                $wordQuery->where('name', 'like', '%' . $word . '%')
                                    ->orWhereHas('translations', fn (Builder $t) => $t->where('value', 'like', '%' . $word . '%'));
                            });
                        }
                    });
                    // Let a category name in the search box surface that category's
                    // products, matching the "search for products, categories" prompt.
                    if ($matchedCategoryIds !== []) {
                        $nested->orWhere(function (Builder $categoryQuery) use ($matchedCategoryIds) {
                            $categoryQuery->whereIn('category_id', $matchedCategoryIds)
                                ->orWhereIn('sub_category_id', $matchedCategoryIds)
                                ->orWhereIn('sub_sub_category_id', $matchedCategoryIds);
                            foreach ($matchedCategoryIds as $categoryId) {
                                $categoryQuery->orWhereJsonContains('category_ids', ['id' => (string) $categoryId]);
                            }
                        });
                    }
                });
            })
            // priceMin/priceMax arrive in the active web currency (the slider is
            // shown converted); unit_price is USD, so convert back before comparing.
            ->when(\is_numeric($priceMin), fn (Builder $builder) => $builder->where('unit_price', '>=', (float) Helpers::convert_currency_to_usd((float) $priceMin)))
            ->when(\is_numeric($priceMax) && (float) $priceMax >= 0, fn (Builder $builder) => $builder->where('unit_price', '<=', (float) Helpers::convert_currency_to_usd((float) $priceMax)))
            ->when(\is_numeric($rating), fn (Builder $builder) => $builder->having('reviews_avg_rating', '>=', (float) $rating))
            ->when(\is_numeric($excludeItemId), fn (Builder $builder) => $builder->whereKeyNot((int) $excludeItemId));
    }

    private function categoryIdsMatchingSearch(array $words): array
    {
        $words = \array_values(\array_filter($words, fn ($word) => \trim((string) $word) !== ''));
        if ($words === []) {
            return [];
        }

        return Category::query()
            ->where(function (Builder $builder) use ($words) {
                foreach ($words as $word) {
                    $builder->where(function (Builder $wordQuery) use ($word) {
                        $wordQuery->where('name', 'like', '%' . $word . '%')
                            ->orWhereHas('translations', fn (Builder $t) => $t->where('value', 'like', '%' . $word . '%'));
                    });
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'low_to_high' => $query->orderBy('unit_price'),
            'a_to_z' => $query->orderBy('name'),
            'z_to_a' => $query->orderByDesc('name'),
            'rating' => $query->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count'),
            'newest' => $query->latest('products.created_at'),
            'random' => $query->inRandomOrder(),
            default => $query->orderByDesc('unit_price'),
        };
    }

    private function cardContext(): array
    {
        $customerId = $this->resolveUserId();
        if (!$customerId) {
            return CardContext::default();
        }

        $wishlistIds = Wishlist::query()
            ->where('customer_id', $customerId)
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $lookup = \array_fill_keys($wishlistIds, true);

        return [
            ...CardContext::default(),
            'wishlist_lookup' => static fn (int $itemId): bool => isset($lookup[$itemId]),
        ];
    }

    private function resultCategories(?StorefrontScope $scope, array $products): array
    {
        $categoryIds = collect($products)
            ->map(fn (Product $product) => (int) $product->category_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($categoryIds === []) {
            return [];
        }

        return Category::query()
            ->whereIn('id', $categoryIds)
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'image' => getStorageImages(path: $category->icon_full_url ?? null, type: 'category'),
            ])
            ->values()
            ->all();
    }

    private function resolveUserId(): ?int
    {
        return app(StorefrontContext::class)->getUserId();
    }
}
